# Z-Blog 插件完整开发流程 v2.1 — Autonomous Execution Layer

状态：候选标准，待 Bridge 实机验证后激活
日期：2026-08-30

## 定位

v2.1 不替代 v2.0。

- v2.0 负责开发方法、Reuse Gate、测试、实机验证、CI、Release Gate、Notion/知识回写等工程规范。
- v2.1 在 v2.0 外增加 GPT-Codex Bridge 自动执行层，使用户无需手工在 GPT 与 Codex 之间复制结果和指令。

长期目标：用户只负责提交一次业务目标；GPT 负责需求设计和技术决策；Bridge 负责 GPT ↔ Codex 双向调度；Codex 负责真实执行；系统持续自动验证、修复、恢复和发布，直到 Release Gate、Rollback Gate 和正式发布全部完成。正常开发运行不得依赖用户继续操作 Codex UI。

## Zero-Touch Run Contract

Bridge 激活后的每一次正常开发运行都必须满足以下硬约束：

```text
USER_INPUT_COUNT = 1
AUTONOMOUS_EXECUTION = REQUIRED
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
CODEX_UI_DEPENDENCY = FORBIDDEN
CHATGPT_UI_RELAY_DEPENDENCY = FORBIDDEN
NORMAL_USER_CONFIRMATION = FORBIDDEN
```

一次运行从用户提交一个业务目标开始，例如：

```text
“给访问统计增加 XXX 功能并完成正式发布。”
```

从这一刻起，系统必须自行完成状态恢复、需求设计、技术决策、Reuse Gate、PRD、任务拆分、Codex 调度、代码修改、自动测试、本机实机验证、数据库验证、失败修复、Git、GitHub CI、版本处理、Release Gate、Rollback Gate、正式发布、Notion 与项目状态回写。

运行过程中禁止要求用户：

- 打开 Codex UI；
- 在 Codex UI 点击 Continue / Approve / Run；
- 把 GPT 指令复制到 Codex；
- 把 Codex 结果复制给 GPT；
- 手动执行普通开发命令；
- 手动推动“下一步”；
- 对普通可逆开发操作逐步确认；
- 因 PHP、JS、SQL、测试、CI、页面 500、查询计划或普通运行时故障进行人工中转。

Codex 只能通过 Bridge 控制的程序化接口执行。主通道为 Codex App Server；`codex exec` 只允许作为 Bridge 自动调用的程序化 fallback。任何依赖人工操作 Codex UI 的路径都不计为通过 Zero-Touch 验收。

## 单次目标完成定义

一次用户业务目标只有在以下所有条件满足时才算完成：

1. Requirement Gate PASS；
2. 必要的 GitHub / 官方生态检索与 Reuse Gate 完成；
3. PRD / 架构 / 验收标准完成；
4. Codex 真实开发完成；
5. 自动测试通过；
6. 必要的本机 Z-Blog 实机验证通过；
7. 必要的数据库 / SQL / EXPLAIN 验证通过；
8. 所有修复循环收口；
9. exact-SHA GitHub CI 通过；
10. 风险驱动的安全 / 性能 / 兼容性检查完成；
11. 版本号、CHANGELOG、Release Notes 与正式 ZIP 正确；
12. Release Gate PASS；
13. Rollback Gate PASS 或合法的 PASS_FORWARD_ONLY；
14. Tag 指向正确 release commit；
15. GitHub Release 存在并包含正确正式产物；
16. Notion 回写并读回确认；
17. PROJECT-STATE / Knowledge 回写完成；
18. 最终门禁报告满足 `RELEASE: RELEASED`。

未达到以上发布终态，不得把“代码写完”“测试通过”“CI 通过”描述为该业务目标已完成。

## 标准流程

```text
用户提交一次业务目标
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

Bridge 完成一次性环境引导并通过激活验收后，用户在每次正常开发运行中的唯一职责是：

1. 提交一次业务目标。

需求存在技术细节或实现歧义时，GPT 应优先依据仓库现状、项目规则、历史验证证据、兼容性、安全性和可维护性自行作出技术决策，不应把普通工程选择退回给用户。

真正需要业务取舍且无法从现有目标推导时，GPT 应采用最保守且可回退的默认决策继续，除非这样做会改变明确的业务含义或产生不可逆风险。只有安全策略定义的 BLOCKED 条件才允许停止自动运行。

## GPT 职责

GPT 负责：

- 恢复当前项目真实状态；
- 把一次业务目标展开成完整需求；
- 需求设计；
- GitHub / 官方生态检索；
- Reuse Gate；
- PRD、架构和验收标准；
- Lane Routing；
- 技术决策；
- 根因分析；
- 修复策略；
- 门禁判断；
- 发布和回滚技术决策；
- Notion / 项目状态语义回写内容；
- 在不需要业务所有者决策时避免产生人工确认节点。

## Bridge 职责

Bridge 负责：

- GPT ↔ Codex 自动双向传递；
- Codex App Server 长线程与恢复；
- 禁止 Codex UI 作为运行依赖；
- 状态机；
- Request / Result schema；
- Resume Gate；
- Evidence Ledger；
- 自动重试和修复轮次；
- Lane 执行路由；
- 本机验证、SQL、CI、Release、Rollback 适配器；
- GitHub Actions 等待与失败日志收集；
- 崩溃恢复；
- 凭据边界和日志脱敏；
- Notion API 自动回写；
- BLOCKED 现场保存；
- 重启后自动 Resume；
- 保证普通开发运行不存在人工消息中转点。

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

Codex 的执行入口必须由 Bridge 程序化调用。Codex UI 不属于正式自动开发链。

## Requirement Gate

非 trivial 任务在改代码前必须明确：业务目标、范围、不做什么、兼容要求、数据影响、安全/隐私影响、验收标准、发布目标和 Reuse Gate 是否需要。

能由 GPT 根据仓库、项目规则和行业常规安全确定的技术细节不得打断用户。

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
- 自动修复升级过程不得要求用户转发 Codex 输出或操作 Codex UI；
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

Fallback 也必须由 Bridge/自动控制器程序化调用 direct Codex/`codex exec` 路径；不得退化成要求用户继续操作 Codex UI、手动复制 GPT/Codex 消息或逐步执行命令。若程序化 fallback 不可用，则进入 BLOCKED 并保存现场，而不是切换到人工 Codex UI 工作流。

Fallback 不允许 reset/clean/丢弃当前已验证状态；恢复 Bridge 时必须先执行 Resume Gate 与真实 Git/runtime/CI 对账。

## 凭据与一次性环境引导

Zero-Touch 约束针对每一次正式开发运行。系统首次安装时允许一次性配置运行所需的 API Key、GitHub、Notion、本机测试环境与权限，但这些配置必须在 Bridge 激活验收前完成。

激活后，不应在普通开发运行中反复要求用户提供同一凭据或重新连接 Codex UI。凭据应通过 Windows 环境变量、凭据管理器或其他 OS 级安全存储读取，不得写入仓库。

如果凭据过期且无法自动刷新，系统保存完整 Resume 状态并报告 BLOCKED；凭据恢复后自动从最后可信节点继续，而不是重新开始开发。

## 强制执行规则

以下规则只有在 Bridge 通过真实环境验收后才激活：

```text
AUTONOMOUS_EXECUTION = REQUIRED
USER_INPUT_COUNT = 1
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
CODEX_UI_DEPENDENCY = FORBIDDEN
CHATGPT_UI_RELAY_DEPENDENCY = FORBIDDEN
NORMAL_USER_CONFIRMATION = FORBIDDEN
```

激活后，任何要求用户继续操作 Codex UI、复制长结果/长指令、手动点击下一步或普通操作确认的流程都属于规范失败。

## BLOCKED 条件

只允许以下类型导致正常无人值守流程暂停：

- 必需凭据不存在或失效且无法自动刷新；
- 将触及未授权生产环境；
- 不可逆/破坏性动作无法证明安全；
- Schema/数据状态冲突可能导致数据丢失；
- 工作区存在无法安全隔离且不能丢弃的用户改动；
- 外部系统明确要求不可自动化的人工授权；
- 多轮根因分析后继续自动修改已不具备足够安全证据。

进入 BLOCKED 时必须保存 request、stage、branch、SHA、已完成门禁、证据索引、阻塞原因和 next_action。BLOCKED 不允许通过“请打开 Codex UI 继续”解决。

## 当前 xz_visit_stats v4.0 适用方式

- 当前阶段保持 T4，不重跑 T2/T3；
- Lane = MAJOR_VERSION；
- T4 继续以 `.codex-tasks/08-v4-t4-analytics-admin.md` 为执行任务；
- T4 强制 UNIT_TEST → LOCAL_RUNTIME → SQL_EXPLAIN → exact-SHA GITHUB_CI；
- T4 完成后 Release Gate 仍可为 NOT READY，并自动进入项目定义的后续阶段；
- 整个 T4 及后续 Release 运行不得依赖用户继续操作 Codex UI；
- 最终发布必须再通过 Rollback Gate。

## 激活验收

v2.1 成为默认标准前必须实证通过：

- 单次用户目标启动完整流程；
- Requirement Gate；
- Lane Router；
- Evidence Ledger；
- Secret redaction；
- App Server GPT↔Codex 双向闭环；
- 无 Codex UI 依赖；
- 无人工 GPT↔Codex 消息中转；
- 无普通开发阶段用户确认；
- crash/resume；
- 自动 repair；
- 本机 Z-Blog；
- SQL/EXPLAIN；
- exact-SHA CI；
- direct Notion write/read-back；
- Rollback Gate；
- 程序化 v2.0 fallback 与重新接管；
- 当前 T4 实机接管且不重跑 T2/T3；
- 从一次业务目标一直运行到真实 Tag + GitHub Release + 正式 ZIP + `RELEASE: RELEASED`。

只要验收过程中需要用户继续操作 Codex UI、手工转发 GPT/Codex 消息或手工推动普通下一步，本次 Zero-Touch 激活验收即判定失败，Bridge 不得设为默认开发模式。
