. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Common.psm1" -Force

$fakeBearer = 'sk-proj-' + 'SECRET123456789'
$fakeOpenAi = 'sk-' + 'SECRET987654321'
$fakeNotion = 'secret_' + 'abcdef123456'
$raw = @"
Authorization: Bearer $fakeBearer
OPENAI_API_KEY=$fakeOpenAi
NOTION_TOKEN=$fakeNotion
Cookie: session=session123; other=value
Set-Cookie: auth=abc123; Path=/
"@

$safe = Protect-BridgeEvidence $raw
Assert-NotContains $safe $fakeBearer 'OpenAI bearer key redacted'
Assert-NotContains $safe $fakeOpenAi 'OpenAI env key redacted'
Assert-NotContains $safe $fakeNotion 'Notion token redacted'
Assert-NotContains $safe 'session123' 'Cookie value redacted'
Assert-NotContains $safe 'abc123' 'Set-Cookie value redacted'
Assert-True ($safe -match '\[REDACTED\]') 'redaction marker present'

Write-Host 'PASS: bridge evidence redaction'
