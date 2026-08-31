Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function New-AppServerRequestId {
    param([Parameter(Mandatory = $true)]$Client)
    $Client.NextId = [int]$Client.NextId + 1
    return $Client.NextId
}

function Quote-ProcessArgument {
    param([Parameter(Mandatory = $true)][string]$Value)
    if ($Value -notmatch '[\s"]') { return $Value }
    return '"' + ($Value -replace '(\\*)"', '$1$1\"' -replace '(\\+)$', '$1$1') + '"'
}

function Write-AppServerMessage {
    param(
        [Parameter(Mandatory = $true)]$Client,
        [Parameter(Mandatory = $true)]$Message
    )

    $json = $Message | ConvertTo-Json -Depth 32 -Compress
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    $bytes = $utf8NoBom.GetBytes($json + "`n")
    $stream = $Client.Process.StandardInput.BaseStream
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Flush()
}

function Read-AppServerMessage {
    param(
        [Parameter(Mandatory = $true)]$Client,
        [int]$TimeoutSeconds = 120
    )

    $deadline = [DateTime]::UtcNow.AddSeconds($TimeoutSeconds)
    $task = $Client.Process.StandardOutput.ReadLineAsync()

    while ([DateTime]::UtcNow -lt $deadline) {
        if ($task.Wait(250)) {
            $line = $task.Result
            if ($null -eq $line) {
                if ($Client.Process.HasExited) {
                    $stderr = $Client.Process.StandardError.ReadToEnd()
                    throw "Codex App Server exited with code $($Client.Process.ExitCode): $stderr"
                }
                continue
            }
            if ([string]::IsNullOrWhiteSpace($line)) {
                $task = $Client.Process.StandardOutput.ReadLineAsync()
                continue
            }
            try {
                return ($line | ConvertFrom-Json)
            } catch {
                throw "Invalid JSONL from Codex App Server: $line"
            }
        }

        if ($Client.Process.HasExited) {
            $stderr = $Client.Process.StandardError.ReadToEnd()
            throw "Codex App Server exited with code $($Client.Process.ExitCode): $stderr"
        }
    }

    throw "Timed out waiting for Codex App Server message after $TimeoutSeconds seconds."
}

function Invoke-AppServerRequest {
    param(
        [Parameter(Mandatory = $true)]$Client,
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)]$Params,
        [int]$TimeoutSeconds = 120,
        [scriptblock]$ServerRequestHandler
    )

    $id = New-AppServerRequestId -Client $Client
    Write-AppServerMessage -Client $Client -Message ([ordered]@{
        id = $id
        method = $Method
        params = $Params
    })

    while ($true) {
        $message = Read-AppServerMessage -Client $Client -TimeoutSeconds $TimeoutSeconds

        if ($null -ne $message.PSObject.Properties['id'] -and [string]$message.id -eq [string]$id -and ($null -ne $message.PSObject.Properties['result'] -or $null -ne $message.PSObject.Properties['error'])) {
            if ($null -ne $message.PSObject.Properties['error'] -and $null -ne $message.error) {
                throw "Codex App Server request '$Method' failed: $($message.error | ConvertTo-Json -Depth 8 -Compress)"
            }
            return $message.result
        }

        $isServerRequest = ($null -ne $message.PSObject.Properties['id'] -and $null -ne $message.PSObject.Properties['method'] -and $null -eq $message.PSObject.Properties['result'])
        if ($isServerRequest) {
            if ($null -eq $ServerRequestHandler) {
                throw "Codex App Server requested client input for '$($message.method)' but no approval/input handler is configured."
            }
            $handlerResult = & $ServerRequestHandler $message
            Write-AppServerMessage -Client $Client -Message ([ordered]@{
                id = $message.id
                result = $handlerResult
            })
            continue
        }

        [void]$Client.Events.Add($message)
    }
}

function Start-CodexAppServer {
    [CmdletBinding()]
    param(
        [string]$Command = 'codex',
        [string[]]$Arguments = @('app-server'),
        [string]$WorkingDirectory = (Get-Location).Path
    )

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $Command
    $psi.WorkingDirectory = $WorkingDirectory
    $psi.UseShellExecute = $false
    $psi.RedirectStandardInput = $true
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.CreateNoWindow = $true
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    if ($null -ne $psi.PSObject.Properties['StandardOutputEncoding']) {
        $psi.StandardOutputEncoding = $utf8NoBom
    }
    if ($null -ne $psi.PSObject.Properties['StandardErrorEncoding']) {
        $psi.StandardErrorEncoding = $utf8NoBom
    }
    $psi.Arguments = (($Arguments | ForEach-Object { Quote-ProcessArgument -Value ([string]$_) }) -join ' ')

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $psi
    if (-not $process.Start()) {
        throw 'Failed to start Codex App Server process.'
    }

    return [pscustomobject]@{
        Process = $process
        NextId = 0
        Initialized = $false
        ThreadId = $null
        Workspace = $WorkingDirectory
        Events = (New-Object System.Collections.ArrayList)
    }
}

function Initialize-CodexSession {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$Client,
        [Parameter(Mandatory = $true)][string]$Workspace,
        [string]$ThreadId,
        [string]$ApprovalPolicy = 'on-request',
        [string]$Sandbox = 'workspace-write',
        [scriptblock]$ServerRequestHandler
    )

    $init = Invoke-AppServerRequest -Client $Client -Method 'initialize' -Params ([ordered]@{
        clientInfo = [ordered]@{
            name = 'xz_visit_stats_bridge'
            title = 'xz_visit_stats GPT-Codex Bridge'
            version = '1.0.0'
        }
        capabilities = @{}
    }) -ServerRequestHandler $ServerRequestHandler

    Write-AppServerMessage -Client $Client -Message ([ordered]@{
        method = 'initialized'
        params = @{}
    })
    $Client.Initialized = $true
    $Client.Workspace = $Workspace

    if ([string]::IsNullOrWhiteSpace($ThreadId)) {
        $threadResult = Invoke-AppServerRequest -Client $Client -Method 'thread/start' -Params ([ordered]@{
            approvalPolicy = $ApprovalPolicy
            sandbox = $Sandbox
            cwd = $Workspace
        }) -ServerRequestHandler $ServerRequestHandler
    } else {
        $threadResult = Invoke-AppServerRequest -Client $Client -Method 'thread/resume' -Params ([ordered]@{
            threadId = $ThreadId
            cwd = $Workspace
        }) -ServerRequestHandler $ServerRequestHandler
    }

    $resolvedThreadId = $null
    if ($null -ne $threadResult.PSObject.Properties['thread'] -and $null -ne $threadResult.thread -and $null -ne $threadResult.thread.PSObject.Properties['id']) {
        $resolvedThreadId = [string]$threadResult.thread.id
    } elseif ($null -ne $threadResult.PSObject.Properties['threadId']) {
        $resolvedThreadId = [string]$threadResult.threadId
    } elseif ($null -ne $threadResult.PSObject.Properties['id']) {
        $resolvedThreadId = [string]$threadResult.id
    } elseif (-not [string]::IsNullOrWhiteSpace($ThreadId)) {
        $resolvedThreadId = $ThreadId
    }

    if ([string]::IsNullOrWhiteSpace($resolvedThreadId)) {
        throw 'Codex App Server did not return a thread id.'
    }

    $Client.ThreadId = $resolvedThreadId
    return [pscustomobject]@{
        client = $Client
        initialize_result = $init
        thread_id = $resolvedThreadId
    }
}

function Start-CodexTurn {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$Client,
        [Parameter(Mandatory = $true)][string]$Text,
        [string]$Title = 'xz_visit_stats autonomous development',
        [string]$ApprovalPolicy = 'on-request',
        [string]$SandboxPolicyType = 'workspace-write',
        [scriptblock]$ServerRequestHandler,
        [int]$TimeoutSeconds = 1800
    )

    if (-not $Client.Initialized -or [string]::IsNullOrWhiteSpace([string]$Client.ThreadId)) {
        throw 'Codex App Server session is not initialized.'
    }

    $turnResult = Invoke-AppServerRequest -Client $Client -Method 'turn/start' -Params ([ordered]@{
        threadId = $Client.ThreadId
        input = @(
            [ordered]@{ type = 'text'; text = $Text }
        )
        cwd = $Client.Workspace
        title = $Title
        approvalPolicy = $ApprovalPolicy
        sandboxPolicy = [ordered]@{ type = $SandboxPolicyType }
    }) -TimeoutSeconds $TimeoutSeconds -ServerRequestHandler $ServerRequestHandler

    $turnCompleted = $false
    $turnEvents = New-Object System.Collections.ArrayList

    foreach ($event in @($Client.Events)) {
        [void]$turnEvents.Add($event)
        if ($null -ne $event.PSObject.Properties['method'] -and [string]$event.method -eq 'turn/completed') {
            $turnCompleted = $true
        }
    }
    $Client.Events.Clear()

    while (-not $turnCompleted) {
        $message = Read-AppServerMessage -Client $Client -TimeoutSeconds $TimeoutSeconds
        $isServerRequest = ($null -ne $message.PSObject.Properties['id'] -and $null -ne $message.PSObject.Properties['method'] -and $null -eq $message.PSObject.Properties['result'])
        if ($isServerRequest) {
            if ($null -eq $ServerRequestHandler) {
                throw "Codex App Server requested client input for '$($message.method)' but no approval/input handler is configured."
            }
            $handlerResult = & $ServerRequestHandler $message
            Write-AppServerMessage -Client $Client -Message ([ordered]@{ id = $message.id; result = $handlerResult })
            continue
        }

        [void]$turnEvents.Add($message)
        if ($null -ne $message.PSObject.Properties['method'] -and [string]$message.method -eq 'turn/completed') {
            $turnCompleted = $true
        }
    }

    return [pscustomobject]@{
        thread_id = $Client.ThreadId
        result = $turnResult
        events = @($turnEvents)
        completed = $true
    }
}

function Stop-CodexAppServer {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)]$Client)

    try {
        if (-not $Client.Process.HasExited) {
            $Client.Process.StandardInput.BaseStream.Close()
            if (-not $Client.Process.WaitForExit(1500)) {
                $Client.Process.Kill()
                [void]$Client.Process.WaitForExit(3000)
            }
        }
    } finally {
        $Client.Process.Dispose()
    }
}

function Invoke-CodexExecFallback {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Prompt,
        [Parameter(Mandatory = $true)][string]$WorkingDirectory,
        [string]$Command = 'codex'
    )

    Push-Location $WorkingDirectory
    try {
        $output = & $Command exec --json $Prompt 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        Pop-Location
    }

    return [pscustomobject]@{
        executor_transport = 'codex_exec_fallback'
        exit_code = $exitCode
        output = @($output)
        working_directory = $WorkingDirectory
    }
}

Export-ModuleMember -Function Start-CodexAppServer, Initialize-CodexSession, Start-CodexTurn, Stop-CodexAppServer, Invoke-CodexExecFallback
