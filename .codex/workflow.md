# xz_visit_stats Codex 开发流程

## 权威执行状态

Codex 执行新任务前先读取 `AGENTS.md` 与 `docs/DEVELOPMENT-RUNTIME.md`。机器运行状态统一由 `scripts/dev_runtime.py` 管理，并持久化到：

```text
.development/runtime/<DEV-YYYYMMDD-NNN>/state.json
.development/runtime/<DEV-YYYYMMDD-NNN>/events.jsonl
.development/runtime/<DEV-YYYYMMDD-NNN>/evidence/index.json
```

`.codex-state.json` 与 `.codex/tasks.json` 是 legacy 文件，不能覆盖当前 DEV Runtime。

## 分工

ChatGPT / 控制器：
- 恢复 Notion Context、PRD、项目状态；
- 创建或恢复原 `DEV-YYYYMMDD-NNN`；
- 需求分析、验收标准、Code Review；
- 读取 GitHub PR/CI；
- Release Gate 与 Notion Writeback。

Codex：
- 读取真实源码与 Git 工作树；
- 修改代码；
- 运行自动测试与要求的本机 Z-Blog 实机验证；
- 普通失败自动修复并复测；
- Git commit / push；
- 通过 `scripts/dev-flow.ps1` / `scripts/dev_runtime.py` 写入可验证 checkpoint。

## Resume 顺序

```text
load Runtime Bundle
→ validate execution_id / state_revision / events / evidence
→ reconcile real Git branch + head SHA
→ restore Notion Context / PRD projection
→ execute exactly one next action
→ persist evidence + state revision + event
→ continue until six Gate contract reaches a legal terminal
```

## 六 Gate

1. Notion Context
2. Codex Development
3. Local Runtime
4. GitHub CI
5. Release Gate
6. Notion Writeback

没有 evidence 的 PASS 无效。GitHub CI 只有与当前 head SHA 精确一致才能复用；实机必需任务不能用 CI 替代。

## 完成规则

- Release Gate 可以是 `NOT READY`，但不能跳过。
- 六 Gate 合法闭环后可 `FINAL: COMPLETE`。
- 只有真实 Tag + GitHub Release + 正式 ZIP 才能 `RELEASE: RELEASED`。
- 普通可逆错误不询问用户是否继续修复；真实权限/生产数据/不可逆风险才进入 BLOCKED。

## 原则

- 保留 Z-Blog 插件兼容性。
- 不跳过测试与实机硬门禁。
- 不直接破坏稳定版本。
- 不创建第三套 Runner/状态机。
- 每个可审查 checkpoint 进入当前开发分支 Git 历史后，才具备跨机器冷恢复能力。
