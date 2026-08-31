Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-TaskFlag {
    param(
        [Parameter(Mandatory = $true)]$Task,
        [Parameter(Mandatory = $true)][string]$Name
    )
    $property = $Task.PSObject.Properties[$Name]
    if ($null -eq $property) { return $false }
    return [bool]$property.Value
}

function Get-DevelopmentLane {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)]$Task)

    $lane = 'NORMAL_FEATURE'
    $reason = 'Default project feature lane.'

    if (Get-TaskFlag -Task $Task -Name 'docs_only') {
        $lane = 'DOC_ONLY'
        $reason = 'Only documentation/control-plane prose is affected.'
    }

    if (Get-TaskFlag -Task $Task -Name 'isolated_pure_logic') {
        $lane = 'FAST_FIX'
        $reason = 'Change is isolated pure logic with no runtime/schema surface.'
    }

    if (Get-TaskFlag -Task $Task -Name 'feature') {
        $lane = 'NORMAL_FEATURE'
        $reason = 'Normal testable feature without stronger runtime/schema signal.'
    }

    if ((Get-TaskFlag -Task $Task -Name 'admin_ui') -or
        (Get-TaskFlag -Task $Task -Name 'request_lifecycle') -or
        (Get-TaskFlag -Task $Task -Name 'hook_change') -or
        (Get-TaskFlag -Task $Task -Name 'runtime_endpoint')) {
        $lane = 'RUNTIME_FEATURE'
        $reason = 'Runtime/admin/hook/request-lifecycle behavior requires real Z-Blog verification.'
    }

    if ((Get-TaskFlag -Task $Task -Name 'schema_change') -or
        (Get-TaskFlag -Task $Task -Name 'migration') -or
        (Get-TaskFlag -Task $Task -Name 'persistent_config_change') -or
        (Get-TaskFlag -Task $Task -Name 'index_change')) {
        $lane = 'SCHEMA_CHANGE'
        $reason = 'Schema/migration/persistent configuration impact requires stronger gates.'
    }

    if (Get-TaskFlag -Task $Task -Name 'major_version') {
        $lane = 'MAJOR_VERSION'
        $reason = 'Major-version program requires the full engineering verification chain.'
    }

    if (Get-TaskFlag -Task $Task -Name 'release_operation') {
        $lane = 'RELEASE'
        $reason = 'Release/tag/artifact/publish operation requires release and rollback gates.'
    }

    switch ($lane) {
        'DOC_ONLY' {
            $gates = @('REQUIREMENT_GATE','DIFF_CHECK')
        }
        'FAST_FIX' {
            $gates = @('REQUIREMENT_GATE','UNIT_TEST','GITHUB_CI')
        }
        'NORMAL_FEATURE' {
            $gates = @('REQUIREMENT_GATE','UNIT_TEST','GITHUB_CI')
        }
        'RUNTIME_FEATURE' {
            $gates = @('REQUIREMENT_GATE','UNIT_TEST','LOCAL_RUNTIME','GITHUB_CI')
        }
        'SCHEMA_CHANGE' {
            $gates = @('REQUIREMENT_GATE','UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI')
        }
        'MAJOR_VERSION' {
            $gates = @('REQUIREMENT_GATE','UNIT_TEST','LOCAL_RUNTIME','GITHUB_CI')
            if ((Get-TaskFlag -Task $Task -Name 'sql_explain_required') -or (Get-TaskFlag -Task $Task -Name 'schema_change')) {
                $gates = @('REQUIREMENT_GATE','UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI')
            }
        }
        'RELEASE' {
            $gates = @('FINAL_RUNTIME','GITHUB_CI','RELEASE_GATE','ROLLBACK_GATE','VERSION_CONSISTENCY_GATE','ARTIFACT_VERIFY')
        }
        default {
            throw "Unsupported development lane: $lane"
        }
    }

    return [pscustomobject]@{
        lane = $lane
        required_gates = @($gates)
        reason = $reason
    }
}

Export-ModuleMember -Function Get-DevelopmentLane
