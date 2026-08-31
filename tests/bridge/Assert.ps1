Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Fail-Assertion {
    param([Parameter(Mandatory = $true)][string]$Message)
    throw "ASSERTION FAILED: $Message"
}

function Assert-True {
    param($Value, [string]$Message = 'expected true')
    if (-not $Value) { Fail-Assertion $Message }
}

function Assert-False {
    param($Value, [string]$Message = 'expected false')
    if ($Value) { Fail-Assertion $Message }
}

function Assert-Equal {
    param($Expected, $Actual, [string]$Message = 'values differ')
    if ($Expected -ne $Actual) {
        Fail-Assertion "$Message; expected=[$Expected] actual=[$Actual]"
    }
}

function Assert-Contains {
    param($Collection, $Expected, [string]$Message = 'collection does not contain expected value')
    if ($null -eq $Collection -or -not ($Collection -contains $Expected)) {
        Fail-Assertion "$Message; missing=[$Expected]"
    }
}

function Assert-NotContains {
    param([string]$Actual, [string]$Unexpected, [string]$Message = 'string contains unexpected value')
    if ($null -ne $Actual -and $Actual.Contains($Unexpected)) {
        Fail-Assertion "$Message; unexpected=[$Unexpected]"
    }
}

function Assert-PathExists {
    param([Parameter(Mandatory = $true)][string]$Path, [string]$Message = 'path does not exist')
    if (-not (Test-Path -LiteralPath $Path)) {
        Fail-Assertion "$Message; path=[$Path]"
    }
}
