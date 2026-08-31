Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Normalize-BridgePath {
    param([AllowNull()][AllowEmptyString()][string]$Path)
    if ([string]::IsNullOrWhiteSpace($Path)) { return $null }
    try {
        return [System.IO.Path]::GetFullPath($Path).TrimEnd('\','/')
    } catch {
        return $Path.TrimEnd('\','/')
    }
}

function Test-BridgePathWithinRoot {
    param(
        [AllowNull()][AllowEmptyString()][string]$Path,
        [AllowNull()][AllowEmptyString()][string]$Root
    )
    if ([string]::IsNullOrWhiteSpace($Path) -or [string]::IsNullOrWhiteSpace($Root)) { return $false }
    $normalizedPath = Normalize-BridgePath $Path
    $normalizedRoot = Normalize-BridgePath $Root
    if ([string]::Equals($normalizedPath, $normalizedRoot, [System.StringComparison]::OrdinalIgnoreCase)) { return $true }
    $prefix = $normalizedRoot + [System.IO.Path]::DirectorySeparatorChar
    return $normalizedPath.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)
}

function New-BridgeApprovalPolicy {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$WorkspaceRoot,
        [Parameter(Mandatory = $true)][string]$AuthorizedRuntimeRoot,
        [string[]]$AllowedNetworkHosts = @()
    )

    return [pscustomobject]@{
        workspace_root = Normalize-BridgePath $WorkspaceRoot
        authorized_runtime_root = Normalize-BridgePath $AuthorizedRuntimeRoot
        allowed_network_hosts = @($AllowedNetworkHosts | ForEach-Object { ([string]$_).ToLowerInvariant() } | Select-Object -Unique)
    }
}

function New-BridgeApprovalResult {
    param(
        [Parameter(Mandatory = $true)][string]$Status,
        [Parameter(Mandatory = $true)][string]$ReasonCode,
        $Response,
        [AllowNull()][AllowEmptyString()][string]$Method,
        [AllowNull()][AllowEmptyString()][string]$ItemId
    )

    return [pscustomobject]@{
        status = $Status
        reason_code = $ReasonCode
        method = $Method
        item_id = $ItemId
        response = $Response
    }
}

function Test-BridgeDestructiveCommand {
    param([AllowNull()][AllowEmptyString()][string]$Command)
    if ([string]::IsNullOrWhiteSpace($Command)) { return $null }

    if ($Command -match '(?i)\b(?:DROP|TRUNCATE)\s+(?:TABLE|DATABASE)\b') {
        return 'DESTRUCTIVE_DATA_OR_SCHEMA'
    }
    if ($Command -match '(?i)\b(?:mysql|mariadb)\b.*\b(?:DROP|TRUNCATE)\b') {
        return 'DESTRUCTIVE_DATA_OR_SCHEMA'
    }
    if ($Command -match '(?i)\bgit\s+reset\s+--hard\b|\bgit\s+clean\s+-[^\r\n]*f|\brm\s+-rf\b|\bRemove-Item\b[^\r\n]*(?:-Recurse[^\r\n]*-Force|-Force[^\r\n]*-Recurse)|\bdel\b[^\r\n]*/[sq]|\bformat\b|\bdiskpart\b|\bdd\s+if=|\bmtd\b|\bsysupgrade\b') {
        return 'DESTRUCTIVE_COMMAND'
    }
    return $null
}

function Test-BridgeApprovedCwd {
    param($Policy, [AllowNull()][AllowEmptyString()][string]$Cwd)
    return ((Test-BridgePathWithinRoot -Path $Cwd -Root $Policy.workspace_root) -or
            (Test-BridgePathWithinRoot -Path $Cwd -Root $Policy.authorized_runtime_root))
}

function Resolve-BridgeApprovalRequest {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$Request,
        [Parameter(Mandatory = $true)]$Policy
    )

    $method = [string]$Request.method
    $params = $Request.params
    $itemId = if ($null -ne $params -and $null -ne $params.PSObject.Properties['itemId']) { [string]$params.itemId } else { $null }

    if ($method -in @('item/tool/requestUserInput','tool/requestUserInput')) {
        return New-BridgeApprovalResult -Status 'BLOCKED_INPUT_REQUIRED' -ReasonCode 'USER_INPUT_REQUIRED' -Response $null -Method $method -ItemId $itemId
    }

    if ($method -eq 'item/commandExecution/requestApproval') {
        if ($null -ne $params.PSObject.Properties['networkApprovalContext'] -and $null -ne $params.networkApprovalContext) {
            $host = [string]$params.networkApprovalContext.host
            $hostKey = $host.ToLowerInvariant()
            if (@($Policy.allowed_network_hosts) -contains $hostKey) {
                return New-BridgeApprovalResult -Status 'AUTO_APPROVED' -ReasonCode 'NETWORK_ALLOWLISTED' -Response ([pscustomobject]@{ decision = 'acceptForSession' }) -Method $method -ItemId $itemId
            }
            return New-BridgeApprovalResult -Status 'BLOCKED_NETWORK' -ReasonCode 'NETWORK_HOST_NOT_ALLOWLISTED' -Response ([pscustomobject]@{ decision = 'decline' }) -Method $method -ItemId $itemId
        }

        $cwd = if ($null -ne $params.PSObject.Properties['cwd']) { [string]$params.cwd } else { $null }
        if (-not (Test-BridgeApprovedCwd -Policy $Policy -Cwd $cwd)) {
            return New-BridgeApprovalResult -Status 'BLOCKED_RISK' -ReasonCode 'OUTSIDE_AUTHORIZED_ROOT' -Response ([pscustomobject]@{ decision = 'decline' }) -Method $method -ItemId $itemId
        }

        $command = if ($null -ne $params.PSObject.Properties['command']) { [string]$params.command } else { '' }
        $destructiveReason = Test-BridgeDestructiveCommand -Command $command
        if (-not [string]::IsNullOrWhiteSpace($destructiveReason)) {
            return New-BridgeApprovalResult -Status 'BLOCKED_RISK' -ReasonCode $destructiveReason -Response ([pscustomobject]@{ decision = 'decline' }) -Method $method -ItemId $itemId
        }

        $reason = if (Test-BridgePathWithinRoot -Path $cwd -Root $Policy.workspace_root) { 'COMMAND_SAFE_WORKSPACE' } else { 'COMMAND_SAFE_AUTHORIZED_RUNTIME' }
        return New-BridgeApprovalResult -Status 'AUTO_APPROVED' -ReasonCode $reason -Response ([pscustomobject]@{ decision = 'acceptForSession' }) -Method $method -ItemId $itemId
    }

    if ($method -eq 'item/fileChange/requestApproval') {
        $grantRoot = if ($null -ne $params.PSObject.Properties['grantRoot']) { [string]$params.grantRoot } else { $null }
        if ((Test-BridgePathWithinRoot -Path $grantRoot -Root $Policy.workspace_root) -or
            (Test-BridgePathWithinRoot -Path $grantRoot -Root $Policy.authorized_runtime_root)) {
            return New-BridgeApprovalResult -Status 'AUTO_APPROVED' -ReasonCode 'FILE_CHANGE_AUTHORIZED_ROOT' -Response ([pscustomobject]@{ decision = 'acceptForSession' }) -Method $method -ItemId $itemId
        }
        return New-BridgeApprovalResult -Status 'BLOCKED_RISK' -ReasonCode 'OUTSIDE_AUTHORIZED_ROOT' -Response ([pscustomobject]@{ decision = 'decline' }) -Method $method -ItemId $itemId
    }

    if ($method -eq 'item/permissions/requestApproval') {
        return New-BridgeApprovalResult -Status 'BLOCKED_PERMISSIONS' -ReasonCode 'PERMISSION_REQUEST_REQUIRES_BOUNDED_ADAPTER' -Response ([pscustomobject]@{ permissions = @(); scope = 'turn' }) -Method $method -ItemId $itemId
    }

    return New-BridgeApprovalResult -Status 'BLOCKED_UNSUPPORTED_REQUEST' -ReasonCode 'UNSUPPORTED_SERVER_REQUEST' -Response $null -Method $method -ItemId $itemId
}

Export-ModuleMember -Function New-BridgeApprovalPolicy, Resolve-BridgeApprovalRequest
