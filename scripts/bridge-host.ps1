[CmdletBinding()]
param(
    [string]$WorkspaceRoot = $env:XZ_ZBLOG_WORKSPACE,
    [string]$RequestPath,
    [int]$MaxStepsPerCycle = 100,
    [int]$QuotaRetrySeconds = 900,
    [switch]$RunOnce
)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$BridgeRoot=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if ([string]::IsNullOrWhiteSpace($RequestPath)) { $RequestPath=Join-Path $BridgeRoot 'bridge/request.json' }
foreach ($name in @('Bridge.Common','Bridge.Preflight','Bridge.AppServer','Bridge.Approval','Bridge.Executor','Bridge.Gpt','Bridge.Runner','Bridge.RuntimeGates','Bridge.Notion')) {
    Import-Module (Join-Path $BridgeRoot ('bridge/lib/'+$name+'.psm1')) -Force
}
if ([string]::IsNullOrWhiteSpace($WorkspaceRoot)) {
    throw 'BLOCKED_LOCAL_EXECUTOR_ACCESS: XZ_ZBLOG_WORKSPACE must name the real Windows plugin Git worktree.'
}
$WorkspaceRoot=[IO.Path]::GetFullPath($WorkspaceRoot)
$request=Read-BridgeJson -Path $RequestPath
$id=[string]$request.request_id
if ($id -notmatch '^[A-Za-z0-9._-]{1,100}$') { throw 'Unsafe request_id.' }
$goal=if ($null -ne $request.PSObject.Properties['business_goal']) { [string]$request.business_goal } else { '' }
if ([string]::IsNullOrWhiteSpace($goal)) { $goal='Resume existing xz_visit_stats v4 from current verified project state through all release gates.' }
$configPath=Join-Path $BridgeRoot 'bridge/config.local.json'
if (-not (Test-Path -LiteralPath $configPath)) { $configPath=Join-Path $BridgeRoot 'bridge/config.example.json' }
$config=Read-BridgeJson -Path $configPath
$runtimeRoot=[string]$config.local_zblog_root
$pluginRoot=[string]$config.local_plugin_root
$model=[string]$env:OPENAI_CONTROLLER_MODEL
if ([string]::IsNullOrWhiteSpace($model)) { throw 'BLOCKED_CREDENTIALS: set OPENAI_CONTROLLER_MODEL.' }
$preflight=Invoke-BridgePreflight -RepositoryRoot $WorkspaceRoot -ConfigPath $configPath
if ([string]$preflight.status -ne 'PASS') { throw ('BRIDGE_PREFLIGHT_BLOCKED|'+$preflight.status+'|'+$preflight.blocker) }
if ([string]$preflight.branch -ne [string]$request.branch) { throw 'BLOCKED_BRANCH_IDENTITY: workspace branch differs from request.' }
if (-not $preflight.codex_available -or -not $preflight.gh_available -or $preflight.github_auth -ne $true) {
    throw 'BLOCKED_LOCAL_EXECUTOR_ACCESS: Codex CLI and authenticated GitHub CLI are required.'
}
$ctxNotion=Get-NotionProjectContext
if ($ctxNotion.status -ne 'PASS' -or $ctxNotion.truncated) { throw ('BLOCKED_NOTION_CONTEXT|'+$ctxNotion.status) }

$runtimeDir=Join-Path $WorkspaceRoot ('.development/runtime/'+$id)
$evidenceRoot=Join-Path $runtimeDir 'evidence'
New-Item -ItemType Directory -Path $evidenceRoot -Force | Out-Null
$checkpointPath=Join-Path $runtimeDir 'bridge-checkpoint.json'
$policy=New-BridgeApprovalPolicy -WorkspaceRoot $WorkspaceRoot -AuthorizedRuntimeRoot $pluginRoot -AllowedNetworkHosts @('github.com','api.github.com','api.openai.com','api.notion.com')
$script:BridgeAppClient=$null
$script:BridgeApprovalHandler=New-BridgeAppServerApprovalHandler -Policy $policy
$instructions=Join-Path $BridgeRoot 'bridge/prompts/gpt-controller.md'

$clientInvoker={
    param($prompt,$context,$approvalHandler)
    if ($null -eq $script:BridgeAppClient -or $script:BridgeAppClient.Process.HasExited) {
        $script:BridgeAppClient=Start-CodexAppServer -WorkingDirectory $WorkspaceRoot
        $session=Initialize-CodexSession -Client $script:BridgeAppClient -Workspace $WorkspaceRoot -ThreadId ([string]$context.thread_id) -ServerRequestHandler $approvalHandler
    }
    $turn=Start-CodexTurn -Client $script:BridgeAppClient -Text $prompt -ServerRequestHandler $approvalHandler
    $gitSha=((& git -C $WorkspaceRoot rev-parse HEAD) | Out-String).Trim()
    return [pscustomobject]@{
        completed=[bool]$turn.completed; output=($turn.events | ConvertTo-Json -Depth 16 -Compress)
        thread_id=$turn.thread_id; observed_sha=$gitSha
    }
}.GetNewClosure()
$recover={
    param($context)
    try {
        if ($null -ne $script:BridgeAppClient) { Stop-CodexAppServer -Client $script:BridgeAppClient }
        $script:BridgeAppClient=$null
        return $true
    } catch { return $false }
}.GetNewClosure()
$fallback={
    param($prompt,$context)
    $result=Invoke-CodexExecFallback -Prompt $prompt -WorkingDirectory $WorkspaceRoot
    if ([int]$result.exit_code -eq 0) {
        $result | Add-Member -NotePropertyName observed_sha -NotePropertyValue ((& git -C $WorkspaceRoot rev-parse HEAD | Out-String).Trim())
    }
    return $result
}.GetNewClosure()
$codex={
    param($prompt,$context)
    $result=Invoke-BridgeExecutorTurn -Prompt $prompt -Context $context -ApprovalPolicy $policy -AppServerInvoker $clientInvoker -AppServerRecover $recover -ExecFallback $fallback
    if ($result.status -ne 'PASS') {
        if ($result.error -match '(?i)insufficient_quota|quota exhausted|usage limit reached') {
            throw 'BRIDGE_QUOTA_EXHAUSTED|codex|quota exhausted'
        }
        throw ('BLOCKED_EXECUTOR_TRANSPORT|'+[string]$result.error)
    }
    $observedSha=([string]$result.raw.observed_sha)
    $threadId=if ($null -ne $result.raw.PSObject.Properties['thread_id']) { [string]$result.raw.thread_id } else { [string]$context.thread_id }
    return [pscustomobject]@{
        completed=$true; output=$result.output; thread_id=$threadId
        observed_sha=$observedSha; executor_transport=$result.executor_transport
    }
}.GetNewClosure()
$gpt={
    param($result,$context)
    $fullContext=[pscustomobject]@{
        request_id=$context.request_id;business_goal=$context.business_goal
        branch=$context.branch;stage=$context.stage;head_sha=$context.head_sha
        rule='Keep existing Z-Blog v2.0 full development/release gates. Never stop at T4. No manual Codex UI.'
        notion_context=([string]$ctxNotion.markdown).Substring(0,[Math]::Min(12000,([string]$ctxNotion.markdown).Length))
    }
    Invoke-GptBridgeDecision -Context $fullContext -ExecutionResult $result -Model $model -InstructionsPath $instructions -PreviousResponseId ([string]$context.controller_response_id)
}.GetNewClosure()
$gateVerifier={
    param($gate,$context)
    Invoke-BridgeLiveGate -Gate $gate -Context $context -WorkspaceRoot $WorkspaceRoot -EvidenceRoot $evidenceRoot -Repository ([string]$config.repository)
}.GetNewClosure()
$publisher={
    param($context)
    $releaseScript=Join-Path $BridgeRoot 'scripts/bridge-publish.ps1'
    if (-not (Test-Path -LiteralPath $releaseScript -PathType Leaf)) {
        return [pscustomobject]@{status='BLOCKED';reason='RELEASE_PUBLISHER_NOT_INSTALLED'}
    }
    $output=& powershell -NoProfile -ExecutionPolicy Bypass -File $releaseScript -WorkspaceRoot $WorkspaceRoot -RequestId $id -ExpectedSha $context.head_sha -TargetVersion ([string]$request.target_version) -EvidenceRoot $evidenceRoot
    if ($LASTEXITCODE -ne 0) { throw 'Bridge release publisher failed; inspect preserved release evidence.' }
    return ($output | Out-String | ConvertFrom-Json)
}.GetNewClosure()

$initialPrompt=@"
Single user goal: $goal
Resume existing Git branch: $($request.branch) at actual current SHA. Target version: $($request.target_version).
Read AGENTS.md, knowledge/PROJECT-STATE.md, Notion context, existing PRD, and $($request.task_file).
Preserve verified T2/T3. Start with current T4; verify and advance to T5 only after T4 gates PASS.
Work continuously: develop, test, local Z-Blog + MySQL EXPLAIN, CI repair, final gates and release.
Do not request Codex UI, manual user input or copy/paste. Do not touch zb_system, unrelated plugins or production data.
At each turn, return factual actions and tool evidence for GPT controller review.
"@
try {
    do {
        $freshSha=((& git -C $WorkspaceRoot rev-parse HEAD) | Out-String).Trim()
        $run=Invoke-BridgeAutonomousRun -BusinessGoal $goal -RequestId $id -Stage ([string]$request.current_stage) -Branch ([string]$request.branch) -HeadSha $freshSha -CheckpointPath $checkpointPath -InitialPrompt $initialPrompt -RequiredGates @($request.required_gates) -CodexExecutor $codex -GptReviewer $gpt -GateVerifier $gateVerifier -ReleasePublisher $publisher -MaxSteps $MaxStepsPerCycle
        Write-Host ('BRIDGE_STATUS='+[string]$run.status+' PHASE='+[string]$run.phase+' NEXT='+[string]$run.next_action)
        if ($run.status -eq 'PLUGIN_RELEASED') { exit 0 }
        if ($run.status -eq 'BLOCKED') { exit 2 }
        if ($run.status -eq 'WAITING_QUOTA') { Start-Sleep -Seconds $QuotaRetrySeconds }
        if ($RunOnce) { exit 3 }
    } while ($true)
} finally {
    if ($null -ne $script:BridgeAppClient) {
        try { Stop-CodexAppServer -Client $script:BridgeAppClient } catch {}
    }
}
