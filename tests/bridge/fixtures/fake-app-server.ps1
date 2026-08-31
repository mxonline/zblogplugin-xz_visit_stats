Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$threadId = 'thread-fixture-001'
$turn = 0

while (($line = [Console]::In.ReadLine()) -ne $null) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    try {
        $message = $line | ConvertFrom-Json
    } catch {
        [Console]::Error.WriteLine('FAKE_APP_SERVER_INVALID_STDIN=' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($line)))
        [Console]::Error.Flush()
        throw
    }
    $method = [string]$message.method

    switch ($method) {
        'initialize' {
            [Console]::Out.WriteLine((@{
                id = $message.id
                result = @{ userAgent = 'fake-app-server/1.0' }
            } | ConvertTo-Json -Compress -Depth 8))
            [Console]::Out.Flush()
        }
        'initialized' {
            # notification; no response
        }
        'thread/start' {
            [Console]::Out.WriteLine((@{
                id = $message.id
                result = @{ thread = @{ id = $threadId } }
            } | ConvertTo-Json -Compress -Depth 8))
            [Console]::Out.WriteLine((@{
                method = 'thread/started'
                params = @{ thread = @{ id = $threadId } }
            } | ConvertTo-Json -Compress -Depth 8))
            [Console]::Out.Flush()
        }
        'thread/resume' {
            $resumeId = [string]$message.params.threadId
            if (-not [string]::IsNullOrWhiteSpace($resumeId)) { $threadId = $resumeId }
            [Console]::Out.WriteLine((@{
                id = $message.id
                result = @{ thread = @{ id = $threadId } }
            } | ConvertTo-Json -Compress -Depth 8))
            [Console]::Out.Flush()
        }
        'turn/start' {
            $turn++
            $turnId = "turn-$turn"
            [Console]::Out.WriteLine((@{
                id = $message.id
                result = @{ turn = @{ id = $turnId } }
            } | ConvertTo-Json -Compress -Depth 8))
            [Console]::Out.WriteLine((@{
                method = 'turn/started'
                params = @{ threadId = $threadId; turn = @{ id = $turnId } }
            } | ConvertTo-Json -Compress -Depth 8))
            [Console]::Out.WriteLine((@{
                method = 'item/completed'
                params = @{ item = @{ type = 'agentMessage'; text = "fixture response $turn" } }
            } | ConvertTo-Json -Compress -Depth 8))
            [Console]::Out.WriteLine((@{
                method = 'turn/completed'
                params = @{ threadId = $threadId; turn = @{ id = $turnId }; status = 'completed' }
            } | ConvertTo-Json -Compress -Depth 8))
            [Console]::Out.Flush()
        }
        default {
            if ($null -ne $message.PSObject.Properties['id']) {
                [Console]::Out.WriteLine((@{
                    id = $message.id
                    error = @{ code = -32601; message = "Unknown method: $method" }
                } | ConvertTo-Json -Compress -Depth 8))
                [Console]::Out.Flush()
            }
        }
    }
}
