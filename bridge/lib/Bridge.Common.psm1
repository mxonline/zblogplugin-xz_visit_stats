Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Read-BridgeJson {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Bridge JSON file not found: $Path"
    }

    return (Get-Content -LiteralPath $Path -Raw -Encoding UTF8 | ConvertFrom-Json)
}

function Write-BridgeJsonAtomic {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)]$Value
    )

    $fullPath = [System.IO.Path]::GetFullPath($Path)
    $directory = [System.IO.Path]::GetDirectoryName($fullPath)
    if (-not [string]::IsNullOrWhiteSpace($directory) -and -not (Test-Path -LiteralPath $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $tmp = "$fullPath.$([Guid]::NewGuid().ToString('N')).tmp"
    $json = $Value | ConvertTo-Json -Depth 32
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($tmp, $json + [Environment]::NewLine, $utf8NoBom)

    try {
        if (Test-Path -LiteralPath $fullPath) {
            try {
                [System.IO.File]::Replace($tmp, $fullPath, $null, $true)
            } catch {
                Move-Item -LiteralPath $tmp -Destination $fullPath -Force
            }
        } else {
            [System.IO.File]::Move($tmp, $fullPath)
        }
    } finally {
        if (Test-Path -LiteralPath $tmp) {
            Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
        }
    }
}

function Protect-BridgeEvidence {
    [CmdletBinding()]
    param([AllowNull()][AllowEmptyString()][string]$Text)

    if ($null -eq $Text) { return $null }

    $safe = $Text
    $patterns = @(
        '(?i)sk-(?:proj-)?[A-Za-z0-9_-]{8,}',
        '(?i)secret_[A-Za-z0-9_-]{6,}',
        '(?i)(Authorization\s*:\s*Bearer\s+)[A-Za-z0-9._~+\/-]+',
        '(?i)(Bearer\s+)[A-Za-z0-9._~+\/-]+',
        '(?i)(NOTION_TOKEN\s*=\s*)[^\s\r\n]+',
        '(?i)(OPENAI_API_KEY\s*=\s*)[^\s\r\n]+',
        '(?i)((?:api[_-]?key|access[_-]?token|refresh[_-]?token|token)\s*[:=]\s*)[^\s\r\n,;]+',
        '(?i)(Cookie\s*:\s*)[^\r\n]+',
        '(?i)(Set-Cookie\s*:\s*)[^\r\n]+'
    )

    foreach ($pattern in $patterns) {
        if ($pattern -match '^\(\?i\)\(') {
            $safe = [regex]::Replace($safe, $pattern, '$1[REDACTED]')
        } else {
            $safe = [regex]::Replace($safe, $pattern, '[REDACTED]')
        }
    }

    return $safe
}

function Get-UtcIsoTimestamp {
    return [DateTime]::UtcNow.ToString('o')
}

Export-ModuleMember -Function Read-BridgeJson, Write-BridgeJsonAtomic, Protect-BridgeEvidence, Get-UtcIsoTimestamp
