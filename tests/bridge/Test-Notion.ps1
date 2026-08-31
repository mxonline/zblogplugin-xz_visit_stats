. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Notion.psm1" -Force

$script:calls = New-Object System.Collections.ArrayList
$transport = {
    param($Method, $Uri, $Headers, $Body)
    [void]$script:calls.Add([pscustomobject]@{ Method = $Method; Uri = $Uri; Body = $Body })

    if ($Method -eq 'GET' -and $Uri -match '/markdown$') {
        return [pscustomobject]@{
            status_code = 200
            body = [pscustomobject]@{
                object = 'page_markdown'
                id = 'target-1'
                markdown = "Bridge checkpoint\nBRIDGE_STATUS|REQ-1|T4_ANALYTICS_ADMIN|abc123"
                truncated = $false
                unknown_block_ids = @()
            }
        }
    }

    return [pscustomobject]@{ status_code = 200; body = [pscustomobject]@{ id = 'target-1' } }
}

$env:NOTION_TOKEN = 'unit-test-token-value'
$env:XZ_VISIT_STATS_NOTION_TARGET = 'target-1'

$payload = [pscustomobject]@{
    request_id = 'REQ-1'
    stage = 'T4_ANALYTICS_ADMIN'
    status = 'PASS'
    branch = 'feature/visit-stats-4.0'
    sha = 'abc123'
    gates = @('UNIT_TEST')
    release_state = 'NOT_READY'
    timestamp = '2026-08-31T15:00:00Z'
}

$result = Write-NotionStageUpdate -Payload $payload -Transport $transport
Assert-Equal 'PASS' $result.status 'Notion write/read-back verified'
Assert-True ($script:calls.Count -ge 2) 'write followed by read-back'
Assert-True (($script:calls | Where-Object { $_.Method -eq 'PATCH' -and $_.Uri -match '/blocks/target-1/children$' }).Count -eq 1) 'write appends page block'
Assert-True (($script:calls | Where-Object { $_.Method -eq 'GET' -and $_.Uri -match '/pages/target-1/markdown$' }).Count -eq 1) 'read-back uses page markdown'
Assert-NotContains (($script:calls | ConvertTo-Json -Depth 16)) 'unit-test-token-value' 'token must not enter body/loggable call payload'

$retryTransport = {
    param($Method, $Uri, $Headers, $Body)
    return [pscustomobject]@{ status_code = 429; body = [pscustomobject]@{} }
}
$retry = Get-NotionProjectContext -Transport $retryTransport
Assert-Equal 'RETRYABLE_INFRA' $retry.status '429 is retryable infrastructure'

$blockedTransport = {
    param($Method, $Uri, $Headers, $Body)
    return [pscustomobject]@{ status_code = 401; body = [pscustomobject]@{} }
}
$blocked = Get-NotionProjectContext -Transport $blockedTransport
Assert-Equal 'BLOCKED_CREDENTIAL' $blocked.status '401 is credential blocker'

Write-Host 'PASS: Notion autonomous adapter contract'
