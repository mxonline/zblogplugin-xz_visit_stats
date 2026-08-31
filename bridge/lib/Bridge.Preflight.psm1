Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-GitText {
    param(
        [Parameter(Mandatory = $true)][string]$RepositoryRoot,
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    $output = & git -C $RepositoryRoot @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git $($Arguments -join ' ') failed: $($output -join [Environment]::NewLine)"
    }
    return (($output | Out-String).Trim())
}

function Test-CommandAvailable {
    param([Parameter(Mandatory = $true)][string]$Name)
    return ($null -ne (Get-Command $Name -ErrorAction SilentlyContinue))
}

function Get-ResumeStageFromProjectState {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)][string]$ProjectStatePath)

    if (-not (Test-Path -LiteralPath $ProjectStatePath)) {
        return 'UNKNOWN'
    }

    $text = Get-Content -LiteralPath $ProjectStatePath -Raw -Encoding UTF8
    $t2Verified = $text -match '(?is)Verified\s+T2|T2\s+baseline'
    $t3Verified = $text -match '(?is)Verified\s+T3|T3\s+completion'
    $t4Current = $text -match '(?is)Current\s+phase:\s*`?T4|Current\s+phase.*T4|T4\s+—\s+analytics'
    $t4InProgress = $text -match '(?is)Phase\s+status:\s*`?(?:IN\s+PROGRESS|CODEX\s+HANDOFF\s+READY)|T4.*IN\s+PROGRESS'

    if ($t2Verified -and $t3Verified -and $t4Current -and $t4InProgress) {
        return 'T4_ANALYTICS_ADMIN'
    }

    return 'PROJECT_STATE_RECONCILE'
}

function Invoke-BridgePreflight {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$RepositoryRoot,
        [string]$ConfigPath,
        [switch]$SkipCredentialChecks,
        [switch]$SkipRuntimeChecks
    )

    $root = [System.IO.Path]::GetFullPath($RepositoryRoot)
    if (-not (Test-Path -LiteralPath $root)) {
        throw "Repository root not found: $root"
    }

    if (-not (Test-CommandAvailable -Name 'git')) {
        return [pscustomobject]@{ status = 'BLOCKED_TOOLING'; blocker = 'git'; repo_root = $root }
    }

    $branch = Invoke-GitText -RepositoryRoot $root -Arguments @('branch', '--show-current')
    $head = Invoke-GitText -RepositoryRoot $root -Arguments @('rev-parse', 'HEAD')
    $dirtyText = Invoke-GitText -RepositoryRoot $root -Arguments @('status', '--porcelain=v1')
    $dirtyPaths = @()
    if (-not [string]::IsNullOrWhiteSpace($dirtyText)) {
        $dirtyPaths = @($dirtyText -split "`r?`n" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    }

    $projectState = Join-Path $root 'knowledge/PROJECT-STATE.md'
    $agents = Join-Path $root 'AGENTS.md'
    $task = Join-Path $root '.codex-tasks/08-v4-t4-analytics-admin.md'
    $resumeStage = Get-ResumeStageFromProjectState -ProjectStatePath $projectState

    $missingContext = @()
    foreach ($required in @($agents, $projectState)) {
        if (-not (Test-Path -LiteralPath $required)) { $missingContext += $required }
    }
    if ($resumeStage -eq 'T4_ANALYTICS_ADMIN' -and -not (Test-Path -LiteralPath $task)) {
        $missingContext += $task
    }

    $codexAvailable = Test-CommandAvailable -Name 'codex'
    $ghAvailable = Test-CommandAvailable -Name 'gh'
    $githubAuth = $null
    if ($ghAvailable -and -not $SkipCredentialChecks) {
        & gh auth status *> $null
        $githubAuth = ($LASTEXITCODE -eq 0)
    }

    $openaiAuth = $null
    $notionAuth = $null
    if (-not $SkipCredentialChecks) {
        $openaiAuth = -not [string]::IsNullOrWhiteSpace($env:OPENAI_API_KEY)
        $notionAuth = -not [string]::IsNullOrWhiteSpace($env:NOTION_TOKEN)
    }

    $runtime = [ordered]@{
        checked = (-not $SkipRuntimeChecks)
        zblog_root = $null
        plugin_root = $null
        php_cli = $null
        local_site = $null
        pass = $null
    }

    if (-not $SkipRuntimeChecks) {
        if ([string]::IsNullOrWhiteSpace($ConfigPath)) {
            $ConfigPath = Join-Path $root 'bridge/config.local.json'
            if (-not (Test-Path -LiteralPath $ConfigPath)) {
                $ConfigPath = Join-Path $root 'bridge/config.example.json'
            }
        }
        if (Test-Path -LiteralPath $ConfigPath) {
            $config = Get-Content -LiteralPath $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
            $runtime.zblog_root = $config.local_zblog_root
            $runtime.plugin_root = $config.local_plugin_root
            $runtime.php_cli = $config.php_cli
            $runtime.local_site = $config.local_site
            $runtime.pass = ((Test-Path -LiteralPath $config.local_zblog_root) -and (Test-Path -LiteralPath $config.php_cli))
        } else {
            $runtime.pass = $false
        }
    }

    $status = 'PASS'
    $blocker = $null
    if ($dirtyPaths.Count -gt 0) {
        $status = 'BLOCKED_WORKTREE'
        $blocker = 'uncommitted changes'
    } elseif ($missingContext.Count -gt 0) {
        $status = 'BLOCKED_CONTEXT'
        $blocker = 'missing required project context'
    } elseif (-not $SkipCredentialChecks -and ((-not $openaiAuth) -or (-not $notionAuth))) {
        $status = 'BLOCKED_CREDENTIALS'
        $blocker = 'required API credential missing'
    } elseif (-not $SkipRuntimeChecks -and $runtime.pass -ne $true) {
        $status = 'BLOCKED_RUNTIME'
        $blocker = 'configured local runtime unavailable'
    }

    return [pscustomobject]@{
        status = $status
        blocker = $blocker
        repo_root = $root
        branch = $branch
        head_sha = $head
        dirty_paths = $dirtyPaths
        missing_context = $missingContext
        codex_available = $codexAvailable
        gh_available = $ghAvailable
        github_auth = $githubAuth
        openai_auth = $openaiAuth
        notion_auth = $notionAuth
        zblog_runtime = [pscustomobject]$runtime
        resume_stage = $resumeStage
        legacy_codex_state_authoritative = $false
    }
}

Export-ModuleMember -Function Invoke-BridgePreflight, Get-ResumeStageFromProjectState
