. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Approval.psm1" -Force

$workspace = 'D:\dev\zblogplugin-xz_visit_stats'
$pluginRoot = 'D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats'

$policy = New-BridgeApprovalPolicy `
    -WorkspaceRoot $workspace `
    -AuthorizedRuntimeRoot $pluginRoot `
    -AllowedNetworkHosts @('github.com','api.github.com','api.openai.com','api.notion.com')

$safeCommand = [pscustomobject]@{
    method = 'item/commandExecution/requestApproval'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-1'
        command = 'powershell -NoProfile -File tests/bridge/Run-All.ps1'
        cwd = $workspace
    }
}
$safeDecision = Resolve-BridgeApprovalRequest -Request $safeCommand -Policy $policy
Assert-Equal 'AUTO_APPROVED' $safeDecision.status 'safe workspace command auto-approved'
Assert-Equal 'acceptForSession' $safeDecision.response.decision 'safe command accepted for session'
Assert-Equal 'COMMAND_SAFE_WORKSPACE' $safeDecision.reason_code 'safe command reason recorded'

$safeRuntimeRead = [pscustomobject]@{
    method = 'item/commandExecution/requestApproval'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-2'
        command = 'php -v'
        cwd = $pluginRoot
    }
}
$safeRuntimeDecision = Resolve-BridgeApprovalRequest -Request $safeRuntimeRead -Policy $policy
Assert-Equal 'AUTO_APPROVED' $safeRuntimeDecision.status 'authorized local runtime read/check auto-approved'

$destructiveCommand = [pscustomobject]@{
    method = 'item/commandExecution/requestApproval'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-3'
        command = 'git reset --hard HEAD~1'
        cwd = $workspace
    }
}
$destructiveDecision = Resolve-BridgeApprovalRequest -Request $destructiveCommand -Policy $policy
Assert-Equal 'BLOCKED_RISK' $destructiveDecision.status 'destructive command blocked'
Assert-Equal 'decline' $destructiveDecision.response.decision 'destructive command declined'
Assert-Equal 'DESTRUCTIVE_COMMAND' $destructiveDecision.reason_code 'destructive command reason recorded'

$schemaDestructive = [pscustomobject]@{
    method = 'item/commandExecution/requestApproval'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-4'
        command = 'mysql -e "DROP TABLE zbp_xz_visit_stats_session"'
        cwd = $workspace
    }
}
$schemaDecision = Resolve-BridgeApprovalRequest -Request $schemaDestructive -Policy $policy
Assert-Equal 'BLOCKED_RISK' $schemaDecision.status 'destructive schema command blocked'
Assert-Equal 'DESTRUCTIVE_DATA_OR_SCHEMA' $schemaDecision.reason_code 'schema destruction classified'

$outsideFile = [pscustomobject]@{
    method = 'item/fileChange/requestApproval'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-5'
        grantRoot = 'D:\wwwroot\www.xzhao.net\zb_system'
    }
}
$outsideDecision = Resolve-BridgeApprovalRequest -Request $outsideFile -Policy $policy
Assert-Equal 'BLOCKED_RISK' $outsideDecision.status 'zb_system file change blocked'
Assert-Equal 'decline' $outsideDecision.response.decision 'outside file change declined'
Assert-Equal 'OUTSIDE_AUTHORIZED_ROOT' $outsideDecision.reason_code 'outside root classified'

$workspaceFile = [pscustomobject]@{
    method = 'item/fileChange/requestApproval'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-6'
        grantRoot = $workspace
    }
}
$workspaceDecision = Resolve-BridgeApprovalRequest -Request $workspaceFile -Policy $policy
Assert-Equal 'AUTO_APPROVED' $workspaceDecision.status 'workspace file change auto-approved'
Assert-Equal 'acceptForSession' $workspaceDecision.response.decision 'workspace file change accepted for session'

$networkSafe = [pscustomobject]@{
    method = 'item/commandExecution/requestApproval'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-7'
        cwd = $workspace
        networkApprovalContext = [pscustomobject]@{ host = 'api.github.com'; protocol = 'https'; port = 443 }
    }
}
$networkSafeDecision = Resolve-BridgeApprovalRequest -Request $networkSafe -Policy $policy
Assert-Equal 'AUTO_APPROVED' $networkSafeDecision.status 'allowlisted development network destination auto-approved'
Assert-Equal 'acceptForSession' $networkSafeDecision.response.decision 'safe network approval accepted for session'

$networkUnknown = [pscustomobject]@{
    method = 'item/commandExecution/requestApproval'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-8'
        cwd = $workspace
        networkApprovalContext = [pscustomobject]@{ host = 'unknown.example.invalid'; protocol = 'https'; port = 443 }
    }
}
$networkUnknownDecision = Resolve-BridgeApprovalRequest -Request $networkUnknown -Policy $policy
Assert-Equal 'BLOCKED_NETWORK' $networkUnknownDecision.status 'unknown network destination blocked'
Assert-Equal 'decline' $networkUnknownDecision.response.decision 'unknown network approval declined'

$userInput = [pscustomobject]@{
    method = 'item/tool/requestUserInput'
    params = [pscustomobject]@{
        threadId = 'thread-1'
        turnId = 'turn-1'
        itemId = 'item-9'
        questions = @([pscustomobject]@{ id = 'q1'; prompt = 'Which user-visible behavior should be chosen?' })
    }
}
$userInputDecision = Resolve-BridgeApprovalRequest -Request $userInput -Policy $policy
Assert-Equal 'BLOCKED_INPUT_REQUIRED' $userInputDecision.status 'unresolved business/user input cannot be invented'
Assert-Equal 'USER_INPUT_REQUIRED' $userInputDecision.reason_code 'input blocker classified'

$unsupported = [pscustomobject]@{
    method = 'mcpServer/elicitation/request'
    params = [pscustomobject]@{ threadId = 'thread-1'; mode = 'form' }
}
$unsupportedDecision = Resolve-BridgeApprovalRequest -Request $unsupported -Policy $policy
Assert-Equal 'BLOCKED_UNSUPPORTED_REQUEST' $unsupportedDecision.status 'unsupported server request fail-closed'

Write-Host 'PASS: approval proxy safety contract'
