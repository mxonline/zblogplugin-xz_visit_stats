param(
    [ValidateSet("next","run","status","list","approve","reset","skip","show")]
    [string]$Action = "next",

    [string]$Task = "",

    [string]$Repo = "."
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

# Keep Windows PowerShell 5.1 compatible and avoid non-ASCII parser issues.
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[Console]::InputEncoding = $Utf8NoBom
[Console]::OutputEncoding = $Utf8NoBom
$OutputEncoding = $Utf8NoBom

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$TaskDir = Join-Path $ScriptDir ".codex-tasks"
$StateFile = Join-Path $ScriptDir ".codex-state.json"
$ExpectedBranch = "feature/visit-stats-1.3"

function Get-TaskFiles {
    @(Get-ChildItem -Path $TaskDir -Filter "*.md" | Sort-Object Name)
}

function New-DefaultState {
    [pscustomobject]@{
        current = 0
        completed = @()
        skipped = @()
        last_run = ""
        last_exit_code = $null
    }
}

function Read-State {
    if (-not (Test-Path $StateFile)) {
        return New-DefaultState
    }

    $raw = Get-Content -Raw -Encoding UTF8 $StateFile
    if ([string]::IsNullOrWhiteSpace($raw)) {
        return New-DefaultState
    }

    return ($raw | ConvertFrom-Json)
}

function Write-State($state) {
    $json = $state | ConvertTo-Json -Depth 8
    [System.IO.File]::WriteAllText($StateFile, $json, $Utf8NoBom)
}

function Resolve-RepoPath([string]$PathValue) {
    return (Resolve-Path $PathValue).Path
}

function Assert-GitRepo([string]$RepoPath) {
    Push-Location $RepoPath
    try {
        & git rev-parse --is-inside-work-tree *> $null
        if ($LASTEXITCODE -ne 0) {
            throw "Not a Git repository: $RepoPath"
        }

        $branch = (& git branch --show-current).Trim()
        if ($branch -ne $ExpectedBranch) {
            throw "Wrong branch '$branch'. Expected '$ExpectedBranch'. Script stopped for safety."
        }
    }
    finally {
        Pop-Location
    }
}

function Get-CodexCommand {
    $cmd = Get-Command codex -ErrorAction SilentlyContinue
    if ($null -eq $cmd) {
        throw "Codex CLI was not found. Run: codex --version"
    }
    return $cmd.Source
}

function Get-CurrentTask($tasks, $state) {
    $index = [int]$state.current
    if ($index -ge $tasks.Count) {
        return $null
    }
    return $tasks[$index]
}

function Show-Status([string]$RepoPath) {
    $tasks = Get-TaskFiles
    $state = Read-State

    Write-Host "Repo: $RepoPath"
    Push-Location $RepoPath
    try {
        $branch = (& git branch --show-current).Trim()
        Write-Host "Branch: $branch"
        Write-Host ""
        & git status --short
    }
    finally {
        Pop-Location
    }

    Write-Host ""
    $current = Get-CurrentTask $tasks $state
    if ($null -eq $current) {
        Write-Host "Current task: all tasks finished"
    }
    else {
        Write-Host ("Current task: [{0}/{1}] {2}" -f ([int]$state.current + 1), $tasks.Count, $current.Name)
    }

    Write-Host "Completed: $(@($state.completed).Count)"
    Write-Host "Skipped: $(@($state.skipped).Count)"

    if (-not [string]::IsNullOrWhiteSpace([string]$state.last_run)) {
        Write-Host "Last run: $($state.last_run)"
        Write-Host "Last exit code: $($state.last_exit_code)"
    }
}

function Resolve-TaskByName($tasks, [string]$Name) {
    $matches = @(
        $tasks | Where-Object {
            $_.BaseName -eq $Name -or
            $_.Name -eq $Name -or
            $_.BaseName -like "*$Name*"
        }
    )

    if ($matches.Count -eq 0) {
        throw "Task not found: $Name"
    }

    if ($matches.Count -gt 1) {
        throw "Multiple tasks matched. Use a more specific task name."
    }

    return $matches[0]
}

function Invoke-Task([string]$RepoPath, [System.IO.FileInfo]$TaskFile) {
    $codex = Get-CodexCommand
    $taskText = Get-Content -Raw -Encoding UTF8 $TaskFile.FullName

    $prompt = @"
Continue development of the Z-BlogPHP plugin xz_visit_stats v1.3.

Read and obey AGENTS.md before doing anything.

Current task file:
$($TaskFile.Name)

Task:
$taskText

Important context:
- Earlier v1.3 changes already exist in the current working tree.
- Inspect the existing diff before editing.
- Do not redo completed filter UI or privacy-radio work unless verification proves it is still wrong.
- Stay on the current branch.
- Do not commit or push.
- Keep changes focused and small.
- At the end, report files changed, checks run, browser/runtime verification still needed, and remaining risks.
"@

    Write-Host ""
    Write-Host "Running task: $($TaskFile.Name)"
    Write-Host "Repo: $RepoPath"
    Write-Host ""

    Push-Location $RepoPath
    try {
        & $codex exec $prompt
        $exitCode = $LASTEXITCODE
    }
    finally {
        Pop-Location
    }

    $state = Read-State
    $state.last_run = $TaskFile.Name
    $state.last_exit_code = $exitCode
    Write-State $state

    if ($exitCode -ne 0) {
        throw "Codex failed with exit code: $exitCode"
    }

    Write-Host ""
    Write-Host "Task finished."
    Write-Host "After local verification, run:"
    Write-Host "  .\dev-v1.3.ps1 approve"
}

$RepoPath = Resolve-RepoPath $Repo
Assert-GitRepo $RepoPath

if (-not (Test-Path $TaskDir)) {
    throw "Task directory not found: $TaskDir"
}

$tasks = Get-TaskFiles
if ($tasks.Count -eq 0) {
    throw "No task files found in: $TaskDir"
}

$state = Read-State

switch ($Action) {
    "list" {
        for ($i = 0; $i -lt $tasks.Count; $i++) {
            if ($i -eq [int]$state.current) {
                $marker = ">"
            }
            elseif ($i -lt [int]$state.current) {
                $marker = "+"
            }
            else {
                $marker = " "
            }

            Write-Host ("{0} {1}. {2}" -f $marker, ($i + 1), $tasks[$i].Name)
        }
    }

    "status" {
        Show-Status $RepoPath
    }

    "show" {
        $current = Get-CurrentTask $tasks $state
        if ($null -eq $current) {
            Write-Host "No pending task."
        }
        else {
            Get-Content -Raw -Encoding UTF8 $current.FullName
        }
    }

    "next" {
        $current = Get-CurrentTask $tasks $state
        if ($null -eq $current) {
            Write-Host "All tasks are finished."
            exit 0
        }

        Invoke-Task $RepoPath $current
    }

    "run" {
        if ([string]::IsNullOrWhiteSpace($Task)) {
            throw "run requires -Task."
        }

        $selected = Resolve-TaskByName $tasks $Task
        Invoke-Task $RepoPath $selected
    }

    "approve" {
        $current = Get-CurrentTask $tasks $state
        if ($null -eq $current) {
            Write-Host "All tasks are already finished."
            exit 0
        }

        $completed = @($state.completed)
        $completed += $current.Name
        $state.completed = $completed
        $state.current = [int]$state.current + 1
        Write-State $state

        Write-Host "Approved: $($current.Name)"

        $nextTask = Get-CurrentTask $tasks $state
        if ($null -ne $nextTask) {
            Write-Host "Next task: $($nextTask.Name)"
            Write-Host "Run: .\dev-v1.3.ps1 next"
        }
        else {
            Write-Host "All v1.3 queued tasks are finished."
        }
    }

    "skip" {
        $current = Get-CurrentTask $tasks $state
        if ($null -eq $current) {
            Write-Host "No task to skip."
            exit 0
        }

        $skipped = @($state.skipped)
        $skipped += $current.Name
        $state.skipped = $skipped
        $state.current = [int]$state.current + 1
        Write-State $state

        Write-Host "Skipped: $($current.Name)"
    }

    "reset" {
        $fresh = New-DefaultState
        $fresh.completed = @(
            "legacy: compact-filter-ui",
            "legacy: privacy-ip-radio-fix"
        )
        $fresh.last_run = "privacy-ip-radio-fix (completed before automation revision)"
        $fresh.last_exit_code = 0
        Write-State $fresh

        Write-Host "State reset to current v1.3 baseline."
    }
}
