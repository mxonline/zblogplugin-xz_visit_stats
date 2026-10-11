Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-BaselineInheritance {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)][string[]]$VerifiedStages,
        [string[]]$ChangedPaths=@(),
        [Parameter(Mandatory=$true)][hashtable]$ProtectedSurfaces
    )
    $inherited=New-Object System.Collections.Generic.List[string]
    $affected=New-Object System.Collections.Generic.List[string]
    foreach ($stage in $VerifiedStages) {
        $touch=$false
        foreach ($path in @($ChangedPaths)) {
            $normalized=([string]$path).Replace('\','/').TrimStart('./')
            foreach ($surface in @($ProtectedSurfaces[$stage])) {
                $prefix=([string]$surface).Replace('\','/').TrimEnd('/')
                if ($normalized -eq $prefix -or $normalized.StartsWith($prefix + '/', [StringComparison]::OrdinalIgnoreCase)) {
                    $touch=$true
                }
            }
        }
        if ($touch) { [void]$affected.Add($stage) } else { [void]$inherited.Add($stage) }
    }
    return [pscustomobject]@{
        status=$(if ($affected.Count -eq 0) {'PASS'} else {'REVIEW_REQUIRED'})
        inherited_stages=@($inherited); affected_stages=@($affected)
        next_action=$(if ($affected.Count -eq 0) {'EXPECTED_DIFF_GATE'} else {'GPT_BASELINE_REVIEW'})
    }
}
Export-ModuleMember -Function Get-BaselineInheritance
