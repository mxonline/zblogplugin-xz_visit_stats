. "$PSScriptRoot/Assert.ps1"
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Runner.psm1" -Force
$root=Join-Path $env:TEMP ('bridge-runner-'+[Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $root -Force | Out-Null
$sha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
$script:turns=0
$script:reviews=0
$script:publishCount=0
$codex={
  param($prompt,$context)
  $script:turns++
  return [pscustomobject]@{ completed=$true; output="codex turn $script:turns"; observed_sha=$context.head_sha }
}
$gpt={
  param($result,$context)
  $script:reviews++
  if ($script:reviews -eq 1) { return [pscustomobject]@{ decision='NEXT_STAGE'; codex_prompt='continue automatically'; reason='next' } }
  return [pscustomobject]@{ decision='RELEASE_READY'; codex_prompt=$null; reason='ready for verified gates' }
}
$gates={
  param($gate,$context)
  return [pscustomobject]@{ status='PASS'; observed_sha=$context.head_sha; evidence_id=('evidence-'+$gate) }
}
$publish={
  param($context)
  $script:publishCount++
  return [pscustomobject]@{
    status='PASS'; release_created=$true; tag_verified=$true; zip_hash_verified=$true
    local_runtime_verified=$true; ci_verified=$true; rollback_verified=$true
    notion_verified=$true; project_state_verified=$true; release_sha=$context.head_sha
  }
}
try {
  $path=Join-Path $root 'run.json'
  $result=Invoke-BridgeAutonomousRun -BusinessGoal 'finish plugin and release' -RequestId 'REQ-001' -Stage 'T5_RELEASE' -Branch 'feature/visit-stats-4.0' -HeadSha $sha -CheckpointPath $path -InitialPrompt 'first turn' -RequiredGates @('UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI') -CodexExecutor $codex -GptReviewer $gpt -GateVerifier $gates -ReleasePublisher $publish -MaxSteps 10
  Assert-Equal 'PLUGIN_RELEASED' $result.status 'real release proof is only successful terminal'
  Assert-Equal 2 $script:turns 'two turns without user input'
  Assert-Equal 2 $script:reviews 'each Codex result reviewed by GPT'
  Assert-Equal 1 $script:publishCount 'release publisher called once'
  $checkpoint=Get-Content $path -Raw | ConvertFrom-Json
  Assert-Equal 'PLUGIN_RELEASED' $checkpoint.status 'persisted terminal checkpoint'
  Assert-Equal 0 $checkpoint.user_followups 'zero Codex UI followups'

  $script:publishCount=0
  $blockedGates={param($gate,$context)
      if ($gate -eq 'LOCAL_RUNTIME') { return [pscustomobject]@{status='BLOCKED'; observed_sha=$context.head_sha; evidence_id=$null} }
      return [pscustomobject]@{ status='PASS'; observed_sha=$context.head_sha; evidence_id='proof' }
  }
  $stop=Invoke-BridgeAutonomousRun -BusinessGoal 'only CI cannot publish' -RequestId 'REQ-002' -Stage 'T5_RELEASE' -Branch 'feature/visit-stats-4.0' -HeadSha $sha -CheckpointPath (Join-Path $root 'blocked.json') -InitialPrompt 'run' -RequiredGates @('UNIT_TEST','LOCAL_RUNTIME','GITHUB_CI') -CodexExecutor $codex -GptReviewer {param($r,$c) [pscustomobject]@{decision='RELEASE_READY';codex_prompt=$null}} -GateVerifier $blockedGates -ReleasePublisher $publish -MaxSteps 5
  Assert-Equal 'BLOCKED' $stop.status 'missing real local gate prevents release'
  Assert-Equal 0 $script:publishCount 'no release on blocked runtime'

  $script:quotaCount=0
  $quotaGpt={param($r,$c)
     $script:quotaCount++
     if ($script:quotaCount -eq 1) { throw 'BRIDGE_QUOTA_EXHAUSTED|openai_responses|quota exhausted' }
     return [pscustomobject]@{decision='RELEASE_READY';codex_prompt=$null}
  }
  $beforeTurns=$script:turns
  $quotaPath=Join-Path $root 'quota.json'
  $paused=Invoke-BridgeAutonomousRun -BusinessGoal 'resume quota' -RequestId 'REQ-003' -Stage 'T5_RELEASE' -Branch 'feature/visit-stats-4.0' -HeadSha $sha -CheckpointPath $quotaPath -InitialPrompt 'run' -RequiredGates @('UNIT_TEST') -CodexExecutor $codex -GptReviewer $quotaGpt -GateVerifier $gates -ReleasePublisher $publish -MaxSteps 5
  Assert-Equal 'WAITING_QUOTA' $paused.status 'quota pause persists state'
  $savedTurns=$script:turns
  $resumed=Invoke-BridgeAutonomousRun -BusinessGoal 'resume quota' -RequestId 'REQ-003' -Stage 'T5_RELEASE' -Branch 'feature/visit-stats-4.0' -HeadSha $sha -CheckpointPath $quotaPath -InitialPrompt 'run' -RequiredGates @('UNIT_TEST') -CodexExecutor $codex -GptReviewer $quotaGpt -GateVerifier $gates -ReleasePublisher $publish -MaxSteps 5
  Assert-Equal 'PLUGIN_RELEASED' $resumed.status 'quota resumes automatically to publish'
  Assert-Equal $savedTurns $script:turns 'resume does not rerun completed Codex turn'
} finally { Remove-Item -LiteralPath $root -Recurse -Force -ErrorAction SilentlyContinue }
Write-Host 'PASS: autonomous checkpoint GPT-Codex release loop'
