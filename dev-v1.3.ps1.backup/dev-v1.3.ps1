param(
    [ValidateSet("next","run","status","list","approve","reset","skip","show")]
    [string]$Action = "next",

    [string]$Task = "",

    [string]$Repo = "."
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$TaskDir = Join-Path $ScriptDir ".codex-tasks"
$StateFile = Join-Path $ScriptDir ".codex-state.json"
$ExpectedBranch = "feature/visit-stats-1.3"

function Get-TaskFiles {
    @(Get-ChildItem -Path $TaskDir -Filter "*.md" | Sort-Object Name)
}

function Read-State {
    if (-not (Test-Path $StateFile)) {
        return [pscustomobject]@{
            current = 0
            completed = @()
            skipped = @()
            last_run = ""
            last_exit_code = $null
        }
    }
    return (Get-Content -Raw -Encoding UTF8 $StateFile | ConvertFrom-Json)
}

function Write-State($state) {
    $state | ConvertTo-Json -Depth 8 | Set-Content -Path $StateFile -Encoding UTF8
}

function Resolve-RepoPath([string]$PathValue) {
    return (Resolve-Path $PathValue).Path
}

function Assert-GitRepo([string]$RepoPath) {
    Push-Location $RepoPath
    try {
        & git rev-parse --is-inside-work-tree *> $null
        if ($LASTEXITCODE -ne 0) {
            throw "当前目录不是 Git 仓库：$RepoPath"
        }

        $branch = (& git branch --show-current).Trim()
        if ($branch -ne $ExpectedBranch) {
            throw "当前分支为 '$branch'，预期分支为 '$ExpectedBranch'。为避免误改，脚本已停止。"
        }
    }
    finally {
        Pop-Location
    }
}

function Get-CodexCommand {
    $cmd = Get-Command codex -ErrorAction SilentlyContinue
    if ($null -eq $cmd) {
        throw "未找到 codex 命令。请先执行 codex --version，确认 Codex CLI 已加入 PATH。"
    }
    return $cmd.Source
}

function Get-CurrentTask($tasks, $state) {
    if ([int]$state.current -ge $tasks.Count) {
        return $null
    }
    return $tasks[[int]$state.current]
}

function Show-Status([string]$RepoPath) {
    $tasks = Get-TaskFiles
    $state = Read-State

    Write-Host "Repo: $RepoPath"
    Push-Location $RepoPath
    try {
        Write-Host "Branch: $((& git branch --show-current).Trim())"
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
    } else {
        Write-Host ("Current task: [{0}/{1}] {2}" -f ([int]$state.current + 1), $tasks.Count, $current.Name)
    }
    Write-Host "Completed: $(@($state.completed).Count)"
    Write-Host "Skipped: $(@($state.skipped).Count)"
    if ($state.last_run) {
        Write-Host "Last run: $($state.last_run)"
        Write-Host "Last exit code: $($state.last_exit_code)"
    }
}

function Invoke-Task([string]$RepoPath, [System.IO.FileInfo]$TaskFile) {
    $codex = Get-CodexCommand
    $taskText = Get-Content -Raw -Encoding UTF8 $TaskFile.FullName

    $prompt = @"
You are continuing development of the Z-BlogPHP plugin xz_visit_stats v1.3.

Read and obey AGENTS.md before doing anything.

Current task file:
$($TaskFile.Name)

Task:
$taskText

Important context:
- Earlier v1.3 changes are already in the current working tree.
- Do not redo completed filter UI or privacy-radio work unless verification proves it is still wrong.
- Inspect the existing diff before editing.
- Stay on the current branch.
- Do not commit or push.
- Keep changes focused and small.
- At the end, report files changed, checks run, browser/runtime verification still needed, and remaining risks.
"@

    Write-Host ""
    Write-Host "Running: $($TaskFile.Name)"
    Write-Host ""

    Push-Location $RepoPath
    try {
        & $codex exec $prompt
        $exit = $LASTEXITCODE
    }
    finally {
        Pop-Location
    }

    $state = Read-State
    $state.last_run = $TaskFile.Name
    $state.last_exit_code = $exit
    Write-State $state

    if ($exit -ne 0) {
        throw "Codex 执行失败，退出码：$exit"
    }

    Write-Host ""
    Write-Host "任务执行完成。"
    Write-Host "若需要本地浏览器验证，请验证后运行："
    Write-Host "  .\dev-v1.3.ps1 approve"
}

function Resolve-TaskByName($tasks, [string]$Name) {
    $matches = @($tasks | Where-Object {
        $_.BaseName -eq $Name -or $_.Name -eq $Name -or $_.BaseName -like "*$Name*"
    })
    if ($matches.Count -eq 0) {
        throw "未找到任务：$Name"
    }
    if ($matches.Count -gt 1) {
        throw "匹配到多个任务，请使用更完整的任务名。"
    }
    return $matches[0]
}

$RepoPath = Resolve-RepoPath $Repo
Assert-GitRepo $RepoPath
$tasks = Get-TaskFiles
$state = Read-State

switch ($Action) {
    "list" {
        for ($i = 0; $i -lt $tasks.Count; $i++) {
            $marker = if ($i -eq [int]$state.current) { ">" } elseif ($i -lt [int]$state.current) { "✓" } else { " " }
            Write-Host ("{0} {1}. {2}" -f $marker, ($i + 1), $tasks[$i].Name)
        }
    }

    "status" {
        Show-Status $RepoPath
    }

    "show" {
        $current = Get-CurrentTask $tasks $state
        if ($null -eq $current) {
            Write-Host "没有待执行任务。"
        } else {
            Get-Content -Raw -Encoding UTF8 $current.FullName
        }
    }

    "next" {
        $current = Get-CurrentTask $tasks $state
        if ($null -eq $current) {
            Write-Host "所有任务都已完成。"
            exit 0
        }
        Invoke-Task $RepoPath $current
    }

    "run" {
        if ([string]::IsNullOrWhiteSpace($Task)) {
            throw 'run 模式必须指定 -Task。'
        }
        $selected = Resolve-TaskByName $tasks $Task
        Invoke-Task $RepoPath $selected
    }

    "approve" {
        $current = Get-CurrentTask $tasks $state
        if ($null -eq $current) {
            Write-Host "所有任务已经完成。"
            exit 0
        }

        $completed = @($state.completed)
        $completed += $current.Name
        $state.completed = $completed
        $state.current = [int]$state.current + 1
        Write-State $state

        Write-Host "已标记通过：$($current.Name)"
        $next = Get-CurrentTask $tasks $state
        if ($null -ne $next) {
            Write-Host "下一任务：$($next.Name)"
            Write-Host "执行：.\dev-v1.3.ps1 next"
        } else {
            Write-Host "当前 v1.3 自动任务队列已完成。"
        }
    }

    "skip" {
        $current = Get-CurrentTask $tasks $state
        if ($null -eq $current) {
            Write-Host "没有待跳过任务。"
            exit 0
        }

        $skipped = @($state.skipped)
        $skipped += $current.Name
        $state.skipped = $skipped
        $state.current = [int]$state.current + 1
        Write-State $state
        Write-Host "已跳过：$($current.Name)"
    }

    "reset" {
        $fresh = [pscustomobject]@{
            current = 0
            completed = @()
            skipped = @()
            last_run = ""
            last_exit_code = $null
        }
        Write-State $fresh
        Write-Host "任务状态已重置到新的第 1 项验证任务。"
    }
}
