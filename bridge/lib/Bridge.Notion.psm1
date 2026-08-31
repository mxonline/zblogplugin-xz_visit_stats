Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:NotionApiBase = 'https://api.notion.com/v1'
$script:NotionVersion = '2026-03-11'

function Get-NotionHeaders {
    param([Parameter(Mandatory = $true)][string]$Token)

    return @{
        Authorization = "Bearer $Token"
        'Content-Type' = 'application/json'
        'Notion-Version' = $script:NotionVersion
    }
}

function Convert-NotionFailure {
    param(
        [int]$StatusCode,
        [string]$Operation
    )

    switch ($StatusCode) {
        429 { return [pscustomobject]@{ status = 'RETRYABLE_INFRA'; operation = $Operation; status_code = $StatusCode } }
        401 { return [pscustomobject]@{ status = 'BLOCKED_CREDENTIAL'; operation = $Operation; status_code = $StatusCode } }
        403 { return [pscustomobject]@{ status = 'BLOCKED_CREDENTIAL'; operation = $Operation; status_code = $StatusCode } }
        404 { return [pscustomobject]@{ status = 'BLOCKED_TARGET'; operation = $Operation; status_code = $StatusCode } }
        default {
            if ($StatusCode -ge 500) {
                return [pscustomobject]@{ status = 'RETRYABLE_INFRA'; operation = $Operation; status_code = $StatusCode }
            }
            return [pscustomobject]@{ status = 'FAILED'; operation = $Operation; status_code = $StatusCode }
        }
    }
}

function Invoke-NotionRequest {
    param(
        [Parameter(Mandatory = $true)][ValidateSet('GET','PATCH')][string]$Method,
        [Parameter(Mandatory = $true)][string]$Uri,
        [Parameter(Mandatory = $true)][hashtable]$Headers,
        $Body,
        [scriptblock]$Transport
    )

    if ($null -ne $Transport) {
        return (& $Transport $Method $Uri $Headers $Body)
    }

    try {
        if ($Method -eq 'GET') {
            $response = Invoke-WebRequest -UseBasicParsing -Method Get -Uri $Uri -Headers $Headers
        } else {
            $json = if ($null -eq $Body) { '{}' } else { $Body | ConvertTo-Json -Depth 32 }
            $response = Invoke-WebRequest -UseBasicParsing -Method Patch -Uri $Uri -Headers $Headers -Body $json
        }

        $parsed = $null
        if (-not [string]::IsNullOrWhiteSpace([string]$response.Content)) {
            $parsed = $response.Content | ConvertFrom-Json
        }
        return [pscustomobject]@{ status_code = [int]$response.StatusCode; body = $parsed }
    } catch {
        $statusCode = 0
        if ($null -ne $_.Exception.Response -and $null -ne $_.Exception.Response.StatusCode) {
            try { $statusCode = [int]$_.Exception.Response.StatusCode } catch { $statusCode = 0 }
        }
        return [pscustomobject]@{
            status_code = $statusCode
            body = [pscustomobject]@{ object = 'error'; message = $_.Exception.Message }
        }
    }
}

function Get-NotionProjectContext {
    [CmdletBinding()]
    param(
        [string]$Token = $env:NOTION_TOKEN,
        [string]$Target = $env:XZ_VISIT_STATS_NOTION_TARGET,
        [scriptblock]$Transport
    )

    if ([string]::IsNullOrWhiteSpace($Token) -or [string]::IsNullOrWhiteSpace($Target)) {
        return [pscustomobject]@{ status = 'BLOCKED_CREDENTIAL'; operation = 'READ_CONTEXT'; status_code = 0 }
    }

    $headers = Get-NotionHeaders -Token $Token
    $uri = "$script:NotionApiBase/pages/$Target/markdown"
    $response = Invoke-NotionRequest -Method GET -Uri $uri -Headers $headers -Body $null -Transport $Transport
    $code = [int]$response.status_code
    if ($code -lt 200 -or $code -ge 300) {
        return Convert-NotionFailure -StatusCode $code -Operation 'READ_CONTEXT'
    }

    return [pscustomobject]@{
        status = 'PASS'
        operation = 'READ_CONTEXT'
        target = $Target
        markdown = if ($null -ne $response.body) { [string]$response.body.markdown } else { '' }
        truncated = if ($null -ne $response.body -and $null -ne $response.body.PSObject.Properties['truncated']) { [bool]$response.body.truncated } else { $false }
    }
}

function Confirm-NotionStageUpdate {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Marker,
        [string]$Token = $env:NOTION_TOKEN,
        [string]$Target = $env:XZ_VISIT_STATS_NOTION_TARGET,
        [scriptblock]$Transport
    )

    $context = Get-NotionProjectContext -Token $Token -Target $Target -Transport $Transport
    if ($context.status -ne 'PASS') { return $context }

    if ([string]$context.markdown -notlike "*$Marker*") {
        return [pscustomobject]@{
            status = 'FAILED_READBACK'
            operation = 'CONFIRM_WRITE'
            target = $Target
            marker = $Marker
        }
    }

    return [pscustomobject]@{
        status = 'PASS'
        operation = 'CONFIRM_WRITE'
        target = $Target
        marker = $Marker
    }
}

function Write-NotionStageUpdate {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$Payload,
        [string]$Token = $env:NOTION_TOKEN,
        [string]$Target = $env:XZ_VISIT_STATS_NOTION_TARGET,
        [scriptblock]$Transport
    )

    if ([string]::IsNullOrWhiteSpace($Token) -or [string]::IsNullOrWhiteSpace($Target)) {
        return [pscustomobject]@{ status = 'BLOCKED_CREDENTIAL'; operation = 'WRITE_STAGE'; status_code = 0 }
    }

    foreach ($field in @('request_id','stage','status','sha')) {
        if ($null -eq $Payload.PSObject.Properties[$field] -or [string]::IsNullOrWhiteSpace([string]$Payload.$field)) {
            throw "Notion stage payload missing required field '$field'."
        }
    }

    $marker = "BRIDGE_STATUS|$([string]$Payload.request_id)|$([string]$Payload.stage)|$([string]$Payload.sha)"
    $gates = if ($null -ne $Payload.PSObject.Properties['gates']) { (@($Payload.gates) -join ',') } else { '' }
    $release = if ($null -ne $Payload.PSObject.Properties['release_state']) { [string]$Payload.release_state } else { '' }
    $timestamp = if ($null -ne $Payload.PSObject.Properties['timestamp']) { [string]$Payload.timestamp } else { [DateTime]::UtcNow.ToString('o') }
    $text = "$marker | status=$([string]$Payload.status) | gates=$gates | release=$release | at=$timestamp"

    $body = [ordered]@{
        children = @(
            [ordered]@{
                object = 'block'
                type = 'paragraph'
                paragraph = [ordered]@{
                    rich_text = @(
                        [ordered]@{
                            type = 'text'
                            text = [ordered]@{ content = $text }
                        }
                    )
                }
            }
        )
        position = [ordered]@{ type = 'end' }
    }

    $headers = Get-NotionHeaders -Token $Token
    $uri = "$script:NotionApiBase/blocks/$Target/children"
    $response = Invoke-NotionRequest -Method PATCH -Uri $uri -Headers $headers -Body $body -Transport $Transport
    $code = [int]$response.status_code
    if ($code -lt 200 -or $code -ge 300) {
        return Convert-NotionFailure -StatusCode $code -Operation 'WRITE_STAGE'
    }

    $confirmed = Confirm-NotionStageUpdate -Marker $marker -Token $Token -Target $Target -Transport $Transport
    if ($confirmed.status -ne 'PASS') { return $confirmed }

    return [pscustomobject]@{
        status = 'PASS'
        operation = 'WRITE_STAGE'
        target = $Target
        marker = $marker
    }
}

Export-ModuleMember -Function Get-NotionProjectContext, Write-NotionStageUpdate, Confirm-NotionStageUpdate
