# T4 — xz_visit_stats v4.0 分析后台、筛选与会话下钻

## 执行目标

从远端 `feature/visit-stats-4.0` 的真实最新 HEAD 恢复状态，连续执行 T4 报表与后台体验实现。不要回退、重做或重解释已完成的 T2/T3。

T4 完成后停止在 T5 之前：**不得 Merge、不得 Tag、不得 Release**，Release Gate 保持 `NOT READY`。

## 强制读取

开始前依次读取并服从：

1. `AGENTS.md`
2. `knowledge/PROJECT-STATE.md`
3. `knowledge/INDEX.md`
4. `knowledge/KNOWN-FAILURES.md`
5. `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md`
6. `knowledge/REUSE-GATE.md`
7. `docs/v4.0.0/PRD-v1.0.md`
8. `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`
9. `docs/v4.0.0/REUSE-GATE-T4-v1.0.md`
10. `docs/superpowers/plans/2026-08-27-v4-t4-analytics-admin.md`
11. 本任务文件

使用 Superpowers 的 TDD、systematic-debugging、verification-before-completion；有子代理能力时优先使用 subagent-driven-development，没有则使用 executing-plans。

## 已验证基线

T3 已完成。必须保留这些事实：

- T3 实现提交：`521d68b0d4e67d07b3e7657c502f292295c366f3`。
- T3 已验证交付链曾到达：`95cab5150a75580c8d459002abfd184860a2e156`。
- PHPUnit：`21 tests / 93 assertions` PASS。
- Windows 本机：IIS / PHP 8.3.8 / MySQL 5.7 / Z-Blog 实机迁移、Session/Page/Beacon dwell/bounce/Event/IPv4/IPv6 CIDR 已通过。
- T3 运行插件备份：`D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats.t3-backup-20260827-012911`。
- v3 9 张历史表与既有数据受保护。
- `vs_DurationMs` 只表示服务器处理时间，永远不能作为访客停留时长。

这些 SHA 只是历史证据。执行开始时必须 `git fetch` 并以远端真实最新 HEAD 为准，禁止强制回退。

## T4 Reuse Gate 已完成

结论：**USE EXISTING + BUILD PROJECT-SPECIFIC**。

- 使用仓库现有 ECharts；禁止再引入 Chart.js/ApexCharts/Highcharts 等第二图表引擎。
- 使用仓库现有 Alpine.js；禁止引入 Vue/React/Svelte。
- 使用现有 Z-Blog admin shell、权限/CSRF、query/filter helpers、keyset pagination 和 CSV-safe escaping。
- 表格、会话/事件查询、目录分析、列显示配置、export-task orchestration 按 xz_visit_stats 自身需求实现。
- 不引入 DataTables/grid framework、datepicker library、Redis/RabbitMQ/queue framework。
- Matomo/51.LA 仅作为语义和信息架构参考，不导入、不 Fork、不复制代码。

除非实施中出现一个计划未覆盖、确实需要新增通用依赖的 subsystem，否则不要重新跑已有 Reuse Gate。若需要新增依赖，先停止该 subsystem 并记录新的 Gate 决策，其他独立 T4 工作可继续。

## 连续执行要求

直接执行完整计划 `docs/superpowers/plans/2026-08-27-v4-t4-analytics-admin.md` 的 Task 1 到 Task 10，不要每一步询问用户。

正常可逆操作可直接进行：读取/修改源码、写测试、运行 PHPUnit/PHP/JS 检查、部署到本机测试 Z-Blog、查询本地测试数据库、备份运行插件、commit/push 开发分支、检查/修复 CI。

只有以下情况暂停：

- 需要当前不存在的账号凭据/权限；
- 会触碰生产站点/生产数据库；
- 需要不可逆删除或覆盖用户数据；
- 发现真实 schema 与 T3 验证证据冲突，继续可能破坏数据；
- 本地管理员授权会话完全无法取得，导致真实后台 UI 验证无法完成。

本地 Codex 没有 Notion connector **不构成阻塞**。ChatGPT controller 负责 Notion Context 与最终 Notion Writeback；Codex 只需在 `knowledge/PROJECT-STATE.md` 写入可验证交付证据，并在最终报告明确请求 controller 写回 Notion。

## T4 产品边界

主信息架构必须覆盖：

- 总览 `overview`
- 实时 `realtime`
- 趋势 `trend`
- 访问明细 `records`
- 来源 `source`
- 内容 `content`
- 访客 `visitors`
- 蜘蛛 `spider`
- 事件 `events`
- 设置/数据管理 `settings`

必须保留 v3 既有 errors/performance/pages/ip/environment/campaign 等能力或兼容旧 deep link，不允许为了 T4 信息架构把已有功能删掉。

明确禁止：

- 渠道追踪独立模块
- 热力图
- 屏幕录制
- 可视化事件编辑器
- 51.LA 商业配额体系

## 数据语义硬门禁

- PV/UV/IP 继续服从现有 v3 采集与汇总口径。
- Session、bounce、visitor dwell、page depth 只读 v4 Session/Page 生命周期数据。
- 生命周期覆盖不足的历史区间必须返回/显示 `partial` 或 `unavailable`，禁止伪造 `0`。
- 访客/Session 页面不得渲染 `se_SessionKey`；VisitorHash 只能脱敏/截断展示。
- IP 展示服从现有 `ip_mode`。
- Event params 只能展示已入库的 allowlisted params。
- 所有用户可见路径、Referer、事件参数等必须 HTML escape。

## 查询/性能硬门禁

- 7d/30d 常用趋势优先使用现有小时/日汇总和索引化 Session/Event 查询。
- raw visits/sessions/events 使用 keyset/cursor，禁止正常后台请求使用无界 OFFSET。
- 禁止每次请求全表重算目录/会话/事件。
- T4 本机验收必须对代表性 MySQL 5.7 查询运行 `EXPLAIN` 并记录索引使用情况。

## Windows 实机门禁

真实运行环境仍以自动检测为准；上次验证值：

```text
Z-Blog root: D:\wwwroot\www.xzhao.net
Plugin root: D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats
PHP CLI:     D:\BtSoft\php\83\php.exe
Local site:  http://127.0.0.1
PHP:         8.3.8
MySQL:       5.7.38-log
```

部署前必须再次创建时间戳备份。只同步 xz_visit_stats，不碰 `zb_system`、其他插件和站点其他目录。

实际使用授权本地管理员会话逐页验证 T4 主页面。若没有管理员会话，不能把“unauthenticated permission page 正常”冒充 UI PASS。

实机至少验证：

- 10 个 T4 主页面可正常打开且无 Fatal/SQL Error；
- today/yesterday/7d/30d/custom；
- device/source/geo/new-returning 等筛选；
- records -> session、realtime -> session、event -> session/path 下钻；
- KPI 与直接 SQL 抽查一致；
- `vs_DurationMs` 与 visitor dwell UI/查询严格隔离；
- v3 历史周期 lifecycle 指标显示 partial/unavailable；
- IP filter 与 directory rules CRUD；
- controlled export 的权限、文件名/目录、状态机和 CSV formula injection 防护；
- 代表性查询 EXPLAIN 使用正确索引；
- v3 现有功能无明显回归，历史表/数据行数不减少。

失败时自动 systematic debugging：读取真实错误 → 定位根因 → 最小修复 → focused test → 受影响实机复测。不要遇到一个失败就跳过其验收项，也不要反复执行同一无效命令。

## 最终交付

T4 全部验收通过后：

1. 运行 `git diff --check`。
2. PHP 语法检查。
3. changed JS 的 Node syntax check。
4. PHPUnit 全量测试。
5. UI terminology / secrets / generated-junk 检查。
6. 按风险门禁运行现有 Semgrep/PHPStan/CI 等必要检查。
7. 更新 `knowledge/PROJECT-STATE.md`，只写真实证据。
8. commit/push `feature/visit-stats-4.0`。
9. 等待并核验 exact SHA 的 GitHub Actions；失败自动修复并重跑。
10. 不 Merge、不 Tag、不 Release。
11. 输出 `FULL DEVELOPMENT FLOW GATE` 六门禁报告。
12. T4 报告最后明确：`Next phase: T5 — Windows final verification and release preparation`。

Release Gate 在本任务结束时应为 `NOT READY`，这是正常结果，不是失败。
