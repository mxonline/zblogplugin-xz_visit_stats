# xz_visit_stats 无人值守开发入口

当前默认自动开发方式是 **Codex 真实工作区 + canonical development runtime**。详细合同见 `docs/DEVELOPMENT-RUNTIME.md`。

## 当前 canonical 状态

新开发任务统一使用：

```text
DEV-YYYYMMDD-NNN
.development/runtime/<execution_id>/state.json
.development/runtime/<execution_id>/events.jsonl
.development/runtime/<execution_id>/evidence/index.json
```

Runtime 负责跨中断恢复、state revision、append-only events、evidence 绑定、Git/CI reconciliation 和六 Gate 下一动作判断；真实 Git/CI/本机 Z-Blog/Release/Notion 结果仍是事实证据。

## Windows / Codex 入口

核心逻辑：

```text
scripts/dev_runtime.py
```

PowerShell 薄适配器的仓库规范路径是 `scripts/dev-flow.ps1`；在 Windows PowerShell 中可直接运行：

```powershell
.\scripts\dev-flow.ps1 new <参数>
.\scripts\dev-flow.ps1 status --root . --execution-id DEV-20260909-001
.\scripts\dev-flow.ps1 resume --root . --execution-id DEV-20260909-001
.\scripts\dev-flow.ps1 reconcile --root . --execution-id DEV-20260909-001 --branch <branch> --head-sha <sha>
.\scripts\dev-flow.ps1 evaluate --root . --execution-id DEV-20260909-001
```

`dev-flow.ps1` 不维护第二份状态，只把命令交给同一个 Python Runtime。

## 完整开发仍执行原六 Gate

```text
[1] Notion Context
[2] Codex Development
[3] Local Runtime
[4] GitHub CI
[5] Release Gate
[6] Notion Writeback
```

没有 evidence 的 PASS 无效；本应实机验证的任务不能用 CI 替代；Release Gate 即使是 `NOT READY` 也必须真实判断过。

## Legacy v1.3 compatibility

仓库根目录下列内容继续保留，只服务历史 v1.3 任务链：

- `dev-v1.3.ps1`
- `.codex-state.json`
- `.codex-tasks/`

它们属于 **legacy compatibility**，不是新开发 Run 的 canonical state。`dev-v1.3.ps1 approve` 的人工队列机制不会被带入新的无人值守入口。

`.codex/tasks.json` 同样只视为 legacy v2.0 规划输入，不具有运行状态裁决权。

## 关键原则

- 不要求用户为普通可逆开发动作逐步回复“下一步”。
- 中断后先 `resume` 同一个 DEV Run，不重新创建同一任务。
- CI PASS 必须绑定当前 `head_sha`；出现新 Commit 后旧 CI 自动视为 stale。
- 普通测试失败自动读取真实错误、修复、复测；只有真实权限、生产数据、不可逆操作或缺失环境才允许 BLOCKED。
- 完成开发任务与正式发布分开判定：`FINAL: COMPLETE` 可以与 `RELEASE: NOT RELEASED` 同时成立。
