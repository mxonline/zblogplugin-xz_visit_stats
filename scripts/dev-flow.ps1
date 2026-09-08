param(
    [Parameter(Position = 0)]
    [ValidateSet('new', 'status', 'resume', 'transition', 'evidence', 'gate', 'ci', 'git', 'reconcile', 'evaluate')]
    [string]$Action = 'status',

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Arguments
)

$ErrorActionPreference = 'Stop'

# Canonical runtime lives under .development/runtime/<DEV-run>/.
# This adapter intentionally owns no second state model; all state, events,
# evidence, resume and completion decisions are delegated to dev_runtime.py.
$RuntimeRoot = '.development/runtime'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RuntimeScript = Join-Path $ScriptDir 'dev_runtime.py'

if (-not (Test-Path $RuntimeScript)) {
    throw "Canonical runtime script not found: $RuntimeScript"
}

$Python = $null
foreach ($Candidate in @('python', 'python3', 'py')) {
    $Command = Get-Command $Candidate -ErrorAction SilentlyContinue
    if ($Command) {
        $Python = $Command.Source
        break
    }
}

if (-not $Python) {
    throw 'Python 3 is required to run the canonical unattended development flow.'
}

$EffectiveArguments = @($Arguments)

if ($Action -eq 'resume') {
    $Git = Get-Command git -ErrorAction SilentlyContinue
    if ($Git) {
        $inside = (& git rev-parse --is-inside-work-tree 2>$null)
        if ($LASTEXITCODE -eq 0 -and "$inside".Trim() -eq 'true') {
            $branch = (& git rev-parse --abbrev-ref HEAD 2>$null).Trim()
            $head = (& git rev-parse HEAD 2>$null).Trim()
            $dirty = & git status --porcelain 2>$null

            if ($branch -and $head) {
                $EffectiveArguments += @('--branch', $branch, '--head-sha', $head)
                if ($dirty) {
                    $EffectiveArguments += '--dirty'
                }
            }
        }
    }
}

& $Python $RuntimeScript $Action @EffectiveArguments
exit $LASTEXITCODE
