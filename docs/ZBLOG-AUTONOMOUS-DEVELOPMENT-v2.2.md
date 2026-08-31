# Z-Blog 插件完整开发流程 v2.2 — Release-First Continuous Autonomous Loop

状态：候选标准，待 Bridge 实机闭环验收后激活
日期：2026-08-31

## 定位

v2.2 是 v2.1 的增量层，完整保留 v2.0 工程流程和 v2.1 Zero-Touch 约束。

唯一成功终点：`PLUGIN_RELEASED`。

用户在一次开发运行中只提交一次业务目标。之后 GPT、Bridge、Codex、本机 Z-Blog、GitHub 与 Notion 必须通过程序化接口持续闭环执行，禁止依赖用户继续操作 Codex UI、手动转发结果、点击 Continue/Approve、输入“下一步”或人工推动普通开发阶段。

## Release-First 优先级

固定优先级：

1. P0：数据安全、生产保护、凭据边界、不可逆风险。
2. P1：推进当前插件达到 `PLUGIN_RELEASED`。
3. P2：自动恢复当前阻塞和失败。
4. P3：缩短后续开发、验证、发布时间。
5. P4：Bridge/自动化平台自身优化。

P4 不得阻挡 P1。不得为了完善 Bridge 本身长期暂停当前可安全推进的插件发布。

## 单次用户输入契约

```text
USER_INPUT_COUNT = 1
AUTONOMOUS_EXECUTION = REQUIRED
CODEX_UI_DEPENDENCY = FORBIDDEN
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
MANUAL_NEXT_STEP = FORBIDDEN
NORMAL_USER_CONFIRMATION = FORBIDDEN
SUCCESS_TERMINAL_STATE = PLUGIN_RELEASED
```

首次系统安装/凭据配置属于基础设施部署，不计入某次业务开发运行。正常运行开始后，用户不再承担执行职责。

## 连续 GPT ↔ Codex 闭环

Codex 的一次 turn 完成只表示一个执行单元完成，绝不表示整个开发任务完成。

强制状态流：

```text
TASK_DISPATCH
→ CODEX_RUNNING
→ CODEX_TURN_COMPLETED
→ RESULT_COLLECT
→ EVIDENCE_PERSIST
→ GPT_REVIEW
→ GPT_DECISION
   ├─ REPAIR → TASK_DISPATCH
   ├─ RETRY_INFRA → 自动重试/重连 → TASK_DISPATCH
   ├─ NEXT_STAGE → TASK_DISPATCH
   ├─ REVERIFY → 对应 Gate → RESULT_COLLECT → GPT_REVIEW
   ├─ RELEASE_READY → Release/Artifact/Notion 流程
   └─ BLOCKED → 保存现场并停止
```

`CODEX_TURN_COMPLETED` 永远不能直接跳转 `COMPLETE` 或 `PLUGIN_RELEASED`。

每次 Codex 输出必须由 Bridge 自动收集、结构化、脱敏、写入 Evidence Ledger，然后自动发送给 GPT Controller。GPT Controller 返回机器可读决策后，Bridge 必须自动向 Codex App Server 的同一 thread 派发下一 turn；只有检测到上下文损坏、重复失败或上下文预算风险时才创建新 thread，并自动注入完整安全 handoff。

## Codex 不得中途停给用户

禁止 Codex 通过下列方式把执行责任交回用户：

- “请把结果发给 GPT”；
- “请在 Codex UI 点击继续”；
- “请打开终端执行以下命令”；
- “请手动验证本机页面”；
- “请确认后我再继续”；
- “下一步请……”；
- 因普通测试/CI/runtime 错误而结束整个运行。

上述情况如果由 Codex 文本产生，Bridge 必须将其视为 `EXECUTOR_HANDOFF_VIOLATION`，由 GPT 重新生成无人工依赖的执行指令并自动重派，不得呈现给用户作为正常下一步。

## Approval Proxy

Codex App Server 产生 server-initiated approval/request 时，由 Bridge Approval Proxy 处理。

自动允许：

- 已在 AGENTS/Requirement Gate/当前 Lane 授权范围内的可逆源码修改；
- 测试、PHPUnit、PowerShell、本机 HTTP、开发数据库只读/受控测试操作；
- 安全备份、插件目录同步、Git commit/push 开发分支；
- GitHub CI 查询与普通修复循环。

自动拒绝并转 `BLOCKED`：

- 未授权生产环境操作；
- 无法证明安全的删除/覆盖；
- force push；
- 修改 `zb_system` 或无关插件；
- 凭据缺失且无法自动刷新；
- 不可逆 Schema/数据风险。

Bridge 不得把 App Server 的普通 approval 请求转成“请用户去 Codex UI 点批准”。

## Watchdog / Heartbeat

Bridge 必须持续监督 Codex，而不是等待人工发现它停止。

每个执行 turn 记录：

```text
thread_id
turn_id
request_id
stage
last_event_at
last_progress_at
expected_terminal_event
timeout_policy
retry_count
```

Watchdog 分类：

- `HEALTHY`：持续有事件或任务仍在合理运行窗口；
- `IDLE_WAIT`：Codex turn 已完成，立即进入 GPT_REVIEW，不等待用户；
- `STALL_SUSPECTED`：超过阶段阈值无进展，自动采集 stderr/process/git 状态；
- `PROCESS_LOST`：App Server/Codex 进程退出，自动重连/恢复 thread；
- `RETRYABLE_INFRA`：网络、429、GitHub 排队等，独立重试；
- `BLOCKED`：只有硬安全/凭据条件。

Watchdog 不得通过“请用户查看 Codex 是否还在运行”来恢复。

## GPT Controller 决策契约

Codex 每次结果进入 GPT 后，GPT 必须返回以下之一：

```text
NEXT_STAGE
REPAIR
REVERIFY
RETRY_INFRA
RELEASE_READY
BLOCKED
```

同时返回：

```text
reason
next_stage
codex_instruction
required_gates
evidence_required
safety_class
reuse_same_thread
```

Bridge 不接受普通自然语言“看起来完成了”作为控制指令。

## 修复循环

代码修复默认每个独立 failure cluster 最多 3 轮；基础设施重试独立计数。

三轮代码修复未通过时：

1. GPT 使用高强度根因分析重新审查完整 Evidence Ledger；
2. 判断是否 thread/context 污染；
3. 必要时自动创建新 Codex thread；
4. 注入当前 branch、HEAD、Requirement、Expected Diff、失败证据、已尝试修复、禁止重做阶段；
5. 再执行结构性修复；
6. 只有继续自动修改已不安全时才 `BLOCKED`。

## 新开发前门禁

标准顺序：

```text
Resume Gate
→ Requirement Gate
→ GitHub/官方生态检索
→ Reuse Gate
→ Change Impact Gate
→ Baseline Inheritance Gate
→ Expected Diff Gate
→ PRD/架构/验收标准
→ Lane Routing
→ Bridge Dispatch
```

### Change Impact Gate

明确本次需求会影响和不会影响的 Hook、页面、表、Migration、配置、JS、API、测试与发布面。

### Baseline Inheritance Gate

已 VERIFIED 的版本阶段默认继承并锁定；没有明确影响证据不得重跑、重构或重新迁移。

### Expected Diff Gate

开发前生成允许修改文件/模块范围；Codex 完成后实际 diff 与 Expected Diff 对比。计划外修改必须由 GPT 审查并决定接受、回退该局部修改或重新派发修复。

## Candidate / Manifest

每个准备进入实机或发布门禁的候选必须有唯一 Candidate：

```text
candidate_id
branch
commit_sha
version
changed_files
schema_version
required_environment
required_gates
artifact_sha256
```

本机 Runtime、GitHub CI、最终 ZIP 和 Release 必须绑定同一候选链，不允许测试 A、CI B、发布 C。

## Artifact 一致性

正式发布前至少验证：

```text
Candidate Commit SHA
= Tag 指向 Commit

Candidate ZIP SHA256
= GitHub Release Artifact SHA256

plugin.xml version
= docs/VERSION
= CHANGELOG release version
= Tag version
= Manifest version
= GitHub Release version
```

任一不一致：Release Gate FAIL。

## 完整原开发流程仍然执行

v2.2 不替换 v2.0。

Bridge 必须自动执行完整工程流程：

```text
需求/PRD
→ Reuse Gate
→ Codex 开发
→ 自动测试
→ 本机 Z-Blog 实机验证
→ DB/SQL/EXPLAIN（适用时）
→ 自动修复
→ Git commit/push
→ exact-SHA GitHub CI
→ 安全/性能/兼容性检查（风险驱动）
→ 版本/CHANGELOG/Release Notes
→ Final Runtime
→ Release Gate
→ Rollback Gate
→ ZIP/Manifest/SHA256
→ Tag
→ GitHub Release
→ Notion 写回并读回
→ PROJECT-STATE/Knowledge
→ 六门禁报告
→ PLUGIN_RELEASED
```

## 唯一成功终点

以下都只是中间状态：

```text
CODE_COMPLETE
TEST_PASS
LOCAL_RUNTIME_PASS
CI_PASS
T4_COMPLETE
RELEASE_READY
```

只有以下全部真实验证后：

```text
Final Runtime PASS
Release Gate PASS
Rollback Gate PASS/PASS_FORWARD_ONLY
exact-SHA CI PASS
Candidate Manifest VERIFIED
ZIP VERIFIED
Artifact SHA256 VERIFIED
Tag VERIFIED
GitHub Release VERIFIED
Notion VERIFIED
PROJECT-STATE VERIFIED
```

才允许：

```text
PLUGIN_RELEASED
```

## v2.0/v2.1 Fallback

Bridge 主通道失败时，系统可自动切换程序化 `codex exec` fallback，但不得切换到人工 Codex UI。

Fallback 仅改变执行 transport，不改变任何 v2.0/v2.1/v2.2 工程门禁。

如果所有程序化 Codex transport 均不可用，记录 `BLOCKED_EXECUTOR_TRANSPORT`、保存现场并等待基础设施恢复；禁止要求用户进入 Codex UI继续。

## 激活验收

v2.2 成为默认标准前必须完成真实 Zero-Touch 验收：

1. 用户只提交一次业务目标；
2. Codex 至少完成两个独立 turn；
3. 第一个 Codex turn 结束后 Bridge 自动把结果交 GPT；
4. GPT 自动产生下一决策；
5. Bridge 自动再次派发 Codex，无用户输入；
6. 至少一次自动 Gate/repair/reverify 路径得到验证；
7. Watchdog 能恢复一次模拟 App Server/Codex 中断；
8. 不打开、不依赖 Codex UI；
9. 完成真实本机 Z-Blog 验证；
10. 完成 exact-SHA CI；
11. 通过 Release Gate + Rollback Gate；
12. 形成真实 ZIP/Tag/GitHub Release；
13. 完成 Notion/PROJECT-STATE 回写；
14. 最终状态为 `PLUGIN_RELEASED`。

任一过程中要求用户继续操作 Codex UI、复制结果或输入下一步，v2.2 Zero-Touch 验收直接 FAIL。
