. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.AppServer.psm1" -Force

$fixture = Join-Path $PSScriptRoot 'fixtures/fake-app-server.ps1'
$workspace = (Resolve-Path "$PSScriptRoot/../..").Path
$powershellExe = Join-Path $PSHOME 'powershell.exe'
$client = $null

try {
    Assert-PathExists $powershellExe 'Windows PowerShell executable required for fake App Server'
    Assert-PathExists $fixture 'fake App Server fixture required'

    $client = Start-CodexAppServer `
        -Command $powershellExe `
        -Arguments @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $fixture) `
        -WorkingDirectory $workspace

    $session = Initialize-CodexSession -Client $client -Workspace $workspace
    Assert-Equal 'thread-fixture-001' $session.thread_id 'thread id returned from App Server'

    $first = Start-CodexTurn -Client $client -Text 'first fixture turn' -TimeoutSeconds 30
    Assert-True $first.completed 'first turn completed'
    Assert-Equal 'thread-fixture-001' $first.thread_id 'first turn thread continuity'
    Assert-True (@($first.events | Where-Object { $_.method -eq 'turn/completed' }).Count -ge 1) 'first turn completed event'

    $second = Start-CodexTurn -Client $client -Text 'second fixture turn' -TimeoutSeconds 30
    Assert-True $second.completed 'second turn completed'
    Assert-Equal 'thread-fixture-001' $second.thread_id 'second turn must reuse same thread'
    Assert-True (@($second.events | Where-Object { $_.method -eq 'turn/completed' }).Count -ge 1) 'second turn completed event'

    Write-Host 'PASS: App Server initialize/thread/turn continuity'
} finally {
    if ($null -ne $client) {
        Stop-CodexAppServer -Client $client
    }
}
