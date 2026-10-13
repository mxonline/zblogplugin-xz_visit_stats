# Z-Blog 无人值守开发 Runtime State

## 定位

本文件定义 `xz_visit_stats` 以及同类 Z-Blog 开发任务的机器执行状态合同。它服务于现有《Z-Blog 插件完整开发流程 v2.0》和六项硬门禁，不建立第二套开发流程。

新的完整开发任务统一使用运行编号：

```text
DEV-YYYYMMDD-NNN
```

同一任务中断后恢复必须继续使用原运行编号，不因换聊天、Codex 重启、PowerShell 中断或 Windows 重启重新编号。

## Canonical Runtime Bundle

每个运行固定保存：

```text
.development/runtime/<execution_id>/state.json
.development/runtime/<execution_id>/events.jsonl
.development/runtime/<execution_id>/evidence/index.json
```

- `state.json`：当前机器可恢复 snapshot，包含版本、分支、head SHA、阶段、next action、六 Gate、CI、阻塞信息和 `state_revision`。
- `events.jsonl`：append-only 事件账本；`seq` 单调递增，历史事件不得由 runtime helper 重写。
- `evidence/index.json`：轻量证据索引，只保存证据 identity/ref/result，不复制 Notion、Actions 日志、数据库或发布包本体。

机器执行位置以有效 Runtime Bundle 为 canonical。聊天上下文、模型记忆、旧 `.codex-state.json` 和旧 `.codex/tasks.json` 都不能独立决定当前阶段。

## 事实证据优先

Canonical Runtime State 只保存“已经接受的执行判断”，不能制造事实。发生冲突时，真实事实边界优先：

1. 当前真实 Git 分支、工作树和 Commit；
2. 与当前 head SHA 精确绑定的 GitHub Actions / PR 结果；
3. 真实本机 Z-Blog、PHP、数据库、HTTP、日志和实机脚本结果；
4. 真实 Tag / GitHub Release / 正式 ZIP；
5. Notion 真实 fetch / update / refetch 结果；
6. Runtime State 中的状态文字。

事实与 state 冲突时 fail closed，返回 `RECONCILE_GIT`、`RUN_GITHUB_CI` 或 `VERIFY_RUNTIME_STATE`，不得靠聊天推断“应该已经完成”。

## 六项硬门禁

Runtime State 只承载现有六 Gate：

```text
[1] Notion Context
[2] Codex Development
[3] Local Runtime
[4] GitHub CI
[5] Release Gate
[6] Notion Writeback
```

Gate 状态规则保持原标准：

- 普通 Gate：`PENDING / PASS / BLOCKED`
- Local Runtime、GitHub CI：另允许 `NOT_REQUIRED`
- Release Gate：另允许 `NOT_READY`

任一非 `PENDING` Gate 都必须绑定至少一个 `evidence:<id>`；没有 evidence 的 PASS 无效。

## 状态与恢复

机器状态使用：

`IDENTIFIED / CONTEXT_RESTORED / ANALYZING / PRD_UPDATED / DEVELOPING / TESTING / FIXING / CI_VERIFYING / RELEASE_PREPARING / COMPLETED / BLOCKED / CANCELED`

恢复固定执行：

```text
load state.json
→ load evidence/index.json
→ load events.jsonl
→ validate execution_id / revision / evidence / event sequence
→ reconcile real Git branch + head SHA + dirty state
→ validate exact-head CI evidence
→ evaluate one next_action
```

合法 next action 包括：

- `RUN_NOTION_CONTEXT`
- `RUN_CODEX_DEVELOPMENT`
- `RUN_LOCAL_RUNTIME`
- `RUN_GITHUB_CI`
- `RUN_RELEASE_GATE`
- `RUN_NOTION_WRITEBACK`
- `RECONCILE_GIT`
- `VERIFY_RUNTIME_STATE`
- `COMPLETE`
- `BLOCKED`

## Git checkpoint reconciliation

`resume` 不能只相信保存的 branch/head。Windows 入口会读取当前工作区的：

```text
git rev-parse --abbrev-ref HEAD
git rev-parse HEAD
git status --porcelain
```

并把真实 Git facts 交给 Runtime。出现 branch/head drift 或 dirty working tree 时，`resume` 返回 `RECONCILE_GIT`，不会继续猜测执行。

当控制器确认新 Commit 是本轮合法 checkpoint 且工作树已 clean 后，使用 `git` 命令接受新 branch/head：

```powershell
.\scripts\dev-flow.ps1 git --root . --execution-id DEV-20260909-001 --branch feat/example --head-sha <new-40-char-sha> --time 2026-09-09T00:00:00Z
```

接受新 Git checkpoint 时：

- `state_revision` 增加；
- append `GIT_RECONCILED` event；
- Runtime `branch/head_sha` 更新；
- 原 GitHub CI snapshot 清为 `PENDING`；
- GitHub CI Gate 清为 `PENDING`；
- 旧 CI evidence 仍保留为历史证据，但不再能作为当前 head 的 PASS。

Dirty working tree 不能直接被 `git` checkpoint 接受，必须先识别/处理未提交修改。

## CLI 与 Windows 入口

核心实现：

```text
scripts/dev_runtime.py
```

Windows/Codex 薄适配器：

```text
scripts/dev-flow.ps1
```

PowerShell 适配器不保存另一份状态，只把 `new / status / resume / transition / evidence / gate / ci / git / reconcile / evaluate` 转交给同一个 Python Runtime。

示例：

```powershell
.\scripts\dev-flow.ps1 new --date 20260909 --project xz_visit_stats --repository mxonline/zblogplugin-xz_visit_stats --current-version 3.0.0 --target-version 3.0.1 --task-type maintenance --task-summary "Fix issue" --branch feat/example --base-branch main --head-sha <40-char-sha> --time 2026-09-09T00:00:00Z

.\scripts\dev-flow.ps1 resume --root . --execution-id DEV-20260909-001
```

## CI 绑定

`GitHub CI = PASS` 只有在以下条件同时成立时可以复用：

- evidence result = PASS；
- evidence 中的 SHA 等于 Runtime `head_sha`；
- Runtime `ci.sha` 等于同一个 `head_sha`；
- 对应 CI evidence ref 可在当前 execution 的 evidence index 中解析。

代码产生新 Commit 后，旧 SHA 的 PASS 自动变成 stale，next action 必须回到 `RUN_GITHUB_CI`。

## 完成与发布是两个维度

`FINAL: COMPLETE` 只有六 Gate 满足原硬门禁且不存在 BLOCKED 时成立。

中间 Phase 可以：

```text
Release Gate = NOT_READY
FINAL = COMPLETE
RELEASE = NOT RELEASED
```

只有真实 Tag + GitHub Release + 正式 ZIP 三类 PASS 证据都存在，并且 Release Gate = PASS，才允许：

```text
RELEASE = RELEASED
```

## Legacy 边界

以下文件继续保留历史兼容，但不是新任务的 canonical runtime：

- `.codex-state.json`：legacy v1.3 队列状态；
- `.codex/tasks.json`：legacy v2.0 任务规划；
- `dev-v1.3.ps1`：legacy v1.3 专用执行入口。

新任务不得因为这些旧文件显示 `completed`、`pending` 或某个 task number，就覆盖当前 DEV Runtime State。

## 持久化边界

`scripts/dev_runtime.py` 每次接受 transition 后原子写 snapshot、append event。它本身不伪造 Git commit/push；现有 Codex/Git 交付层负责把有效 checkpoint 与代码一起提交到当前开发分支。

因此：

- 工作树内状态可用于本机中断恢复；
- checkpoint 进入 Git 历史后可用于跨聊天/跨机器冷恢复；
- 不新增数据库、云盘或第四套外部状态服务。
