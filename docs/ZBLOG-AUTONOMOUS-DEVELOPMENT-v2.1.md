# Z-Blog 插件完整开发流程 v2.1 — Autonomous Execution Layer

状态：候选标准，待 Bridge 实机验证后激活
日期：2026-08-30

## 定位

v2.1 不替代 v2.0。

- v2.0 负责开发方法、Reuse Gate、测试、实机验证、CI、Release Gate、Notion/知识回写等工程规范。
- v2.1 在 v2.0 外增加 GPT-Codex Bridge 自动执行层，使用户无需手工在 GPT 与 Codex 之间复制结果和指令。

长期目标：用户主要负责提出业务目标；GPT 负责需求设计和技术决策；Bridge 负责 GPT ↔ Codex 双向调度；Codex 负责真实执行；系统持续自动验证、修复、恢复和发布，直到 Release Gate、Rollback Gate 和正式发布全部完成，或遇到真正的 BLOCKED 条件。

## 标准流程

```text
用户业务目标
→ Resume Gate
→ Requirement Gate
→ GitHub / 官方生态检索
→ Reuse Gate
→ PRD / 架构 / 验收标准
→ Lane Routing
→ GPT-Codex Bridge
→ Codex 真实开发
→ 自动测试
→ 必要的本机 Z-Blog 实机验证
→ 必要的数据库 / SQL / EXPLAIN
→ Evidence Ledger
→ GPT 复核
→ 自动修复循环
→ Git commit / push
→ exact-SHA GitHub CI
→ 风险驱动的安全 / 性能 / 兼容性检查
→ 版本 / CHANGELOG / Release Notes
→ 最终实机验证
→ Release Gate
→ Rollback Gate
→ 正式 ZIP / Tag / GitHub Release
→ Notion 回写并读回确认
→ PROJECT-STATE / Knowledge 回写
→ 最终门禁报告
→ RELEASED
```

## 用户职责

正常情况下，用户只需要：

1. 提出业务目标或功能需求；
2. 在确实存在无法自动判断的业务分歧时做选择；
3. 在缺少必要凭据、生产不可逆风险等真正 BLOCKED 条件下介入。

普通 PHP 错误、测试失败、页面 500、SQL 错误、GitHub CI 失败、性能查询异常等不应转化为用户确认步骤，应进入自动诊断、修复和复测循环。

## GPT 职责

GPT 负责：

- 恢复当前项目真实状态；
- 需求设计；
- GitHub / 官方生态检索；
- Reuse Gate；
- PRD、架构和验收标准；
- Lane Routing；
- 根因分析；
- 修复策略；
- 门禁判断；
- 发布和回滚技术决策；
- Notion / 项目状态语义回写内容。

## Bridge 职责

Bridge 负责：

- GPT ↔ Codex 自动双向传递；
- Codex App Server 长线程与恢复；
- 状态机；
- Request / Result schema；
- Resume Gate；
- Evidence Ledger；
- 自动重试和修复轮次；
- Lane 执行路由；
- 本机验证、SQL、CI、Release、Rollback 适配器；
- 崩溃恢复；
- 凭据边界和日志脱敏；
- Notion API 自动回写；
- BLOCKED 现场保存。

## Codex 职责

Codex 负责真实执行：

- 读取代码和项目知识；
- 编辑真实源码；
- 编写和运行测试；
- 调试；
- 本机 Z-Blog 验证；
- 数据库查询与 EXPLAIN；
- Git commit / push；
- CI 故障修复；
- 发布准备；
- 返回结构化执行证据。

## Requirement Gate

非 trivial 任务在改代码前必须明确：业务目标、范围、不做什么、兼容要求、数据影响、安全/隐私影响、验收标准、发布目标和 Reuse Gate 是否需要。

能由 GPT 根据仓库、项目规则和行业常规安全确定的技术细节不应打断用户。

## Lane Routing

标准初始 Lane：

- DOC_ONLY
- FAST_FIX
- NORMAL_FEATURE
- RUNTIME_FEATURE
- SCHEMA_CHANGE
- MAJOR_VERSION
- RELEASE

Lane 决定应执行哪些测试和门禁；不允许为了省时间把 runtime/schema/major-version 任务降级到快速 Lane。

## Evidence Ledger

所有 PASS 必须有证据。证据按 request、stage、gate、branch、SHA、环境、命令和结果记录，并经过脱敏。

`bridge/state.json` 表示当前状态；`bridge/evidence/` 记录为什么可以认为某个门禁通过。两者不可互相替代。

## 自动修复策略

代码修复与基础设施重试分开计数：

- 代码修复默认最多 3 轮；
- 基础设施重试有独立上限；
- GitHub 排队、HTTP 429、App Server 重连等不消耗代码修复轮次；
- 三轮代码修复仍失败时，GPT 进行高强度根因升级分析，可重建 Codex Thread；
- 只有继续自动修改已经不安全、缺少必要访问或存在真实数据风险时才进入 BLOCKED。

## Release Gate 与 Rollback Gate

正式发布必须同时满足：

1. Release Gate PASS；
2. Rollback Gate PASS 或对允许的 forward-only 场景为 PASS_FORWARD_ONLY；
3. 最终必要本机验证 PASS；
4. exact-SHA CI PASS；
5. 正式 ZIP 验证；
6. Tag 指向正确 commit；
7. GitHub Release 存在并包含正确产物；
8. Notion 与 PROJECT-STATE 回写完成。

没有真实 Tag + GitHub Release + 正式 ZIP，不得报告 RELEASED。

## v2.0 Fallback

正常模式：`AUTONOMOUS`。

Bridge 自身故障时允许进入：`DEGRADED_V2_FALLBACK`。

Fallback 必须继续完整执行 v2.0 的工程规范，不能借 Bridge 故障跳过 Reuse Gate、Runtime、CI、Release Gate、Rollback Gate 或状态回写。

Fallback 不允许 reset/clean/丢弃当前已验证状态；恢复 Bridge 时必须先执行 Resume Gate 与真实 Git/runtime/CI 对账。

## 强制执行规则

以下两条只有在 Bridge 通过真实环境验收后才激活：

```text
AUTONOMOUS_EXECUTION = REQUIRED
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
```

激活后，手工在 GPT 与 Codex 间复制长结果/长指令不再是正常开发路径。

## BLOCKED 条件

只允许以下类型导致正常无人值守流程暂停：

- 必需凭据不存在或失效且无法自动刷新；
- 将触及未授权生产环境；
- 不可逆/破坏性动作无法证明安全；
- Schema/数据状态冲突可能导致数据丢失；
- 工作区存在无法安全隔离且不能丢弃的用户改动；
- 外部系统明确要求人工授权；
- 多轮根因分析后继续自动修改已不具备足够安全证据。

## 当前 xz_visit_stats v4.0 适用方式

- 当前阶段保持 T4，不重跑 T2/T3；
- Lane = MAJOR_VERSION；
- T4 继续以 `.codex-tasks/08-v4-t4-analytics-admin.md` 为执行任务；
- T4 强制 UNIT_TEST → LOCAL_RUNTIME → SQL_EXPLAIN → exact-SHA GITHUB_CI；
- T4 完成后 Release Gate 仍可为 NOT READY，并自动进入项目定义的后续阶段；
- 最终发布必须再通过 Rollback Gate。

## 激活验收

v2.1 成为默认标准前必须实证通过：

- Requirement Gate；
- Lane Router；
- Evidence Ledger；
- Secret redaction；
- App Server GPT↔Codex 双向闭环；
- crash/resume；
- 自动 repair；
- 本机 Z-Blog；
- SQL/EXPLAIN；
- exact-SHA CI；
- direct Notion write/read-back；
- Rollback Gate；
- v2.0 fallback 与重新接管；
- 当前 T4 实机接管且不重跑 T2/T3。
