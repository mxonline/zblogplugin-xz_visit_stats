Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function ConvertTo-BridgeRelativePath {
    param([AllowEmptyString()][string]$Path)
    return $Path.Replace('\','/').TrimStart('./').Trim()
}
function Test-BridgeProtectedPath {
    param([Parameter(Mandatory=$true)][string]$Path)
    $p=ConvertTo-BridgeRelativePath $Path
    if ([string]::IsNullOrWhiteSpace($p)) { return $true }
    if ($Path -match '^([/\\]|[A-Za-z]:)' -or $p -match '(^|/)\.\.(/|$)') { return $true }
    if ($p -match '(?i)(^|/)zb_system(/|$)|(^|/)zb_users/plugin/(?!xz_visit_stats/)|(^|/)(c_option\.php|\.env|id_rsa|credentials\.json)$') { return $true }
    return $false
}
function Get-ExpectedDiff {
    [CmdletBinding()]
    param([Parameter(Mandatory=$true)]$Requirement)
    $allowed=@()
    $reviewed=@()
    if ($null -ne $Requirement.PSObject.Properties['expected_change_paths']) {
        $allowed=@($Requirement.expected_change_paths | ForEach-Object { ConvertTo-BridgeRelativePath ([string]$_) })
    }
    if ($null -ne $Requirement.PSObject.Properties['reviewed_additional_paths']) {
        foreach ($entry in @($Requirement.reviewed_additional_paths)) {
            if ($null -eq $entry) { continue }
            $path=ConvertTo-BridgeRelativePath ([string]$entry.path)
            if (-not [string]::IsNullOrWhiteSpace($path) -and -not [string]::IsNullOrWhiteSpace([string]$entry.reason) -and -not (Test-BridgeProtectedPath $path)) {
                $reviewed+= $path
            }
        }
    }
    return [pscustomobject]@{ allowed_paths=@($allowed); reviewed_paths=@($reviewed); status='PASS' }
}
function Test-ExpectedDiff {
    [CmdletBinding()]
    param([Parameter(Mandatory=$true)][string[]]$ActualPaths,[Parameter(Mandatory=$true)]$Plan)
    $blocked=New-Object System.Collections.Generic.List[string]
    $unplanned=New-Object System.Collections.Generic.List[string]
    foreach ($original in @($ActualPaths)) {
        $p=ConvertTo-BridgeRelativePath $original
        if (Test-BridgeProtectedPath $original) { [void]$blocked.Add($original); continue }
        $accepted=$false
        foreach ($pattern in @($Plan.allowed_paths)+@($Plan.reviewed_paths)) {
            $match=ConvertTo-BridgeRelativePath ([string]$pattern)
            if ($p.Equals($match,[StringComparison]::OrdinalIgnoreCase) -or ($match.EndsWith('/*') -and $p.StartsWith($match.Substring(0,$match.Length-1),[StringComparison]::OrdinalIgnoreCase))) {
                $accepted=$true; break
            }
        }
        if (-not $accepted) { [void]$unplanned.Add($original) }
    }
    $status=if ($blocked.Count -gt 0) {'BLOCKED'} elseif ($unplanned.Count -gt 0) {'REVIEW_REQUIRED'} else {'PASS'}
    return [pscustomobject]@{
        status=$status; blocked_paths=@($blocked); unplanned_paths=@($unplanned)
        next_action=$(if ($status -eq 'PASS') {'LANE_ROUTE'} elseif ($status -eq 'BLOCKED') {'BLOCKED_SAFETY'} else {'GPT_EXPECTED_DIFF_REVIEW'})
    }
}
Export-ModuleMember -Function Get-ExpectedDiff, Test-ExpectedDiff
