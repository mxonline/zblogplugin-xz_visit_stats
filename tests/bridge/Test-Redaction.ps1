. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Common.psm1" -Force

$raw = @'
Authorization: Bearer sk-proj-SECRET123456789
OPENAI_API_KEY=sk-SECRET987654321
NOTION_TOKEN=secret_abcdef123456
Cookie: session=session123; other=value
Set-Cookie: auth=abc123; Path=/
'@

$safe = Protect-BridgeEvidence $raw
Assert-NotContains $safe 'sk-proj-SECRET123456789' 'OpenAI bearer key redacted'
Assert-NotContains $safe 'sk-SECRET987654321' 'OpenAI env key redacted'
Assert-NotContains $safe 'secret_abcdef123456' 'Notion token redacted'
Assert-NotContains $safe 'session123' 'Cookie value redacted'
Assert-NotContains $safe 'abc123' 'Set-Cookie value redacted'
Assert-True ($safe -match '\[REDACTED\]') 'redaction marker present'

Write-Host 'PASS: bridge evidence redaction'
