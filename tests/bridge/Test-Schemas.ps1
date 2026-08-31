. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Resolve-Path "$PSScriptRoot/../.."
$schemaDir = Join-Path $root 'bridge/schemas'

Assert-PathExists (Join-Path $schemaDir 'request.schema.json')
Assert-PathExists (Join-Path $schemaDir 'state.schema.json')
Assert-PathExists (Join-Path $schemaDir 'result.schema.json')
Assert-PathExists (Join-Path $root 'bridge/config.example.json')
Assert-PathExists (Join-Path $root 'bridge/request.json')
Assert-PathExists (Join-Path $root 'bridge/state.json')
Assert-PathExists (Join-Path $root 'bridge/result.json')

Get-ChildItem (Join-Path $root 'bridge') -Recurse -Filter '*.json' | ForEach-Object {
    try {
        Get-Content $_.FullName -Raw | ConvertFrom-Json | Out-Null
    } catch {
        Fail-Assertion "invalid JSON: $($_.FullName): $($_.Exception.Message)"
    }
}

$request = Get-Content (Join-Path $root 'bridge/request.json') -Raw | ConvertFrom-Json
Assert-Equal '1.0' $request.schema_version 'request schema version'
Assert-Equal 'PRESERVE_VERIFIED' $request.resume_policy 'resume policy'
Assert-Equal 'MAJOR_VERSION' $request.lane 'current T4 lane'
Assert-Contains $request.forbidden_actions 'REWRITE_T2_T3' 'T2/T3 lock'
Assert-Contains $request.forbidden_actions 'RELEASE_T4_PREMATURELY' 'release lock'
Assert-Contains $request.forbidden_actions 'MANUAL_CODEX_UI_HANDOFF' 'zero-touch lock'

$config = Get-Content (Join-Path $root 'bridge/config.example.json') -Raw | ConvertFrom-Json
Assert-Equal 'PLUGIN_RELEASED' $config.success_terminal_state 'only release terminal state'
Assert-True $config.autonomous_execution 'autonomous execution must be enabled'
Assert-False $config.manual_gpt_codex_copy_paste 'manual relay must be disabled'
Assert-False $config.codex_ui_dependency 'Codex UI dependency must be disabled'
Assert-Equal 'env:OPENAI_CONTROLLER_MODEL' $config.controller_model 'controller model must be config driven'
Assert-Equal 'env:CODEX_MODEL' $config.codex_model 'Codex model must be config driven'

$stateSchema = Get-Content (Join-Path $schemaDir 'state.schema.json') -Raw | ConvertFrom-Json
$statusValues = @($stateSchema.properties.status.enum)
Assert-Contains $statusValues 'CODEX_TURN_COMPLETED' 'turn-complete state required'
Assert-Contains $statusValues 'GPT_REVIEW' 'GPT review state required'
Assert-Contains $statusValues 'GPT_DECISION' 'GPT decision state required'
Assert-Contains $statusValues 'PLUGIN_RELEASED' 'release terminal state required'

$resultSchema = Get-Content (Join-Path $schemaDir 'result.schema.json') -Raw | ConvertFrom-Json
$resultValues = @($resultSchema.properties.result.enum)
Assert-Contains $resultValues 'REPAIRABLE' 'repair result required'
Assert-Contains $resultValues 'RETRYABLE_INFRA' 'infrastructure retry result required'

Write-Host 'PASS: bridge schema and zero-touch contract checks'
