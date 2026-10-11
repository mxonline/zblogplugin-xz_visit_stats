Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-ChangeImpact {
    [CmdletBinding()]
    param([Parameter(Mandatory=$true)]$Requirement)
    $scope = @([string]$Requirement.business_goal)
    if ($null -ne $Requirement.PSObject.Properties['in_scope']) { $scope += @($Requirement.in_scope) }
    if ($null -ne $Requirement.PSObject.Properties['expected_change_paths']) { $scope += @($Requirement.expected_change_paths) }
    $text = $scope -join ' '
    $schema = [string]$Requirement.schema_impact
    $schemaRequired = ($schema -notmatch '^(NONE|NO_CHANGE|NOT_REQUIRED)$')
    $runtime = $schemaRequired -or $text -match '(?i)admin|backend|frontend|hook|visit|session|event|database|sql|plugin|analytics|runtime|collect'
    $areas = New-Object System.Collections.Generic.List[string]
    if ($runtime) { [void]$areas.Add('ZBLOG_RUNTIME') }
    if ($schemaRequired) { [void]$areas.Add('DATABASE_SCHEMA') }
    if ($text -match '(?i)assets|javascript|\.js\b|ui|frontend|admin') { [void]$areas.Add('UI_OR_ASSETS') }
    if ($areas.Count -eq 0) { [void]$areas.Add('SOURCE_ONLY') }
    return [pscustomobject]@{
        status='PASS'; impacted_areas=@($areas); runtime_required=$runtime
        schema_review_required=$schemaRequired
        next_action='BASELINE_INHERITANCE_GATE'
    }
}
Export-ModuleMember -Function Get-ChangeImpact
