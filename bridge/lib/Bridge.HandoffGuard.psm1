Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Test-ExecutorHandoffViolation {
    [CmdletBinding()]
    param([AllowNull()][AllowEmptyString()][string]$Text)

    $violations = New-Object System.Collections.Generic.List[string]
    if ([string]::IsNullOrWhiteSpace($Text)) {
        return [pscustomobject]@{ has_violation = $false; violation_types = @(); safe_text = $Text }
    }

    # ASCII source keeps Windows PowerShell 5.1 parsing deterministic. Chinese
    # phrases are represented with .NET regex Unicode escapes.
    $patterns = [ordered]@{
        CODEX_UI_HANDOFF = '(?i)(?:\u8BF7[^\r\n]{0,80}Codex\s*UI[^\r\n]{0,80}(?:\u70B9\u51FB|\u7EE7\u7EED)|(?:open|click|continue)[^\r\n]{0,80}Codex\s*UI|Codex\s*UI[^\r\n]{0,80}(?:open|click|continue))'
        RESULT_RELAY_HANDOFF = '(?i)(?:\u628A[^\r\n]{0,40}\u7ED3\u679C[^\r\n]{0,40}\u53D1\u7ED9\s*GPT|(?:send|paste|relay)[^\r\n]{0,80}(?:result|output)[^\r\n]{0,80}(?:to\s*)?GPT)'
        MANUAL_COMMAND_HANDOFF = '(?i)(?:\u8BF7\u624B\u52A8\u6267\u884C\u547D\u4EE4|(?:manually\s+(?:execute|run)|run\s+this\s+command\s+manually))'
    }

    foreach ($entry in $patterns.GetEnumerator()) {
        if ($Text -match [string]$entry.Value) {
            [void]$violations.Add([string]$entry.Key)
        }
    }

    return [pscustomobject]@{
        has_violation = ($violations.Count -gt 0)
        violation_types = @($violations)
        safe_text = $Text
    }
}

Export-ModuleMember -Function Test-ExecutorHandoffViolation
