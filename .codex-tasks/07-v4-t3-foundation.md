# T3 — xz_visit_stats v4.0 会话、页面生命周期与事件采集基础

## 目标

基于已经完成的真实 Windows schema 审计和 `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`，实现 v4.0 的采集与统计底座：

- 会话识别
- 页面序列 / 页面生命周期
- 真实访客停留时长
- 跳出率与页面深度基础口径
- 代码事件采集
- IP 过滤规则
- 必要的增量汇总 / 查询基础

本任务不实现 T4 分析后台，不实现渠道追踪、热力图或屏幕录制。

## 强制规则

1. 先读取仓库根目录 `AGENTS.md`。
2. 读取并以以下文档为唯一设计依据：
   - `docs/v4.0.0/PRD-v1.0.md`
   - `docs/v4.0.0/GAP-ANALYSIS-v1.0.md`
   - `docs/v4.0.0/SCHEMA-AUDIT-v1.0.md`
   - `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`
3. 使用 Superpowers 的计划 / TDD / 系统化调试 / 完成前验证规范；生产代码必须先有失败测试（RED → GREEN → REFACTOR）。
4. 不覆盖或丢弃任何无关本地未提交修改。
5. 当前开发分支：`feature/visit-stats-4.0`。
6. 真实运行环境自动检测；当前已确认可用环境：

```text
Z-Blog root: D:\wwwroot\www.xzhao.net
Plugin root: D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats
PHP CLI:     D:\BtSoft\php\83\php.exe
Local site:  http://127.0.0.1
MySQL:       5.7.38-log
Z-BlogPHP:   173540
PHP:         8.3.8
```

7. Git 工作树与 Z-Blog 运行目录保持隔离；开发源码在 Git 工作树，运行目录只用于部署副本和实机验证。
8. `vs_DurationMs` 永远是服务器处理耗时，禁止作为页面停留时长或会话时长。
9. v3 原始日志和历史表必须保留；不得删除、重命名、覆盖 `keywords`、`page_uv`、`pages` 等真实历史表。
10. 迁移必须幂等；发现同名表/列/索引结构冲突必须停止，禁止自动 DROP / 重建。
11. Release Gate 在本任务结束时仍应为 `NOT READY`；不得创建 Tag/Release，不得合并到 `main`。

## 开始前验证

先执行并记录：

```powershell
git status --short
git branch --show-current
git rev-parse HEAD
git fetch origin
git log -1 --oneline origin/feature/visit-stats-4.0
```

确认基线至少包含 T2 完成提交：

```text
1390098c8621836e40dd5bfd408a220a18148704
```

如远端已有更新，以远端最新提交为准，不强制回退。

## 实施分解

### T3-A：增量迁移框架与 v4 新表

按 `MIGRATION-DESIGN-v1.0.md` 实现并测试以下新表，表名前缀使用 Z-Blog `%pre%`：

- `xz_visit_stats_sessions`
- `xz_visit_stats_session_pages`
- `xz_visit_stats_events`
- `xz_visit_stats_directory_rules`
- `xz_visit_stats_export_tasks`
- `xz_visit_stats_ip_filters`

要求：

- 复用现有 `inc/upgrade/` 升级框架，不另造第二套不可追踪迁移系统。
- 每张表创建前检查存在性；存在时验证必需列、类型和索引列顺序。
- 同名结构不一致时返回明确错误并停止后续 v4 迁移。
- 不修改 v3 九张真实表的业务字段。
- 如必须新增 v4 schema version / migration state，使用现有插件配置/升级状态机制并保持可重复运行。

TDD 最低测试：

- 第一次迁移会生成预期 schema SQL / schema definition。
- 第二次执行不重复创建、不报错、不破坏已有表。
- 模拟同名结构冲突时停止。
- v3 历史表不会进入删除/重建路径。
- `vs_DurationMs` 不被迁移为 visitor dwell 字段。

优先扩展：

- `tests/UpgradeFrameworkTest.php`

必要时新建聚焦 v4 迁移的测试文件，例如：

- `tests/V4MigrationTest.php`

### T3-B：会话与页面生命周期

新增聚焦模块，避免继续把所有逻辑塞进 `inc/collector.php`。建议边界：

- `inc/session.php`：会话键、30 分钟默认超时（若 PRD/现有配置另有明确值则服从文档）、新/老访客会话归属、入口/退出、页深更新。
- `inc/page_lifecycle.php`：页面进入/离开、sequence、Beacon 生命周期、停留时长合法化。

如果现有命名模式更合适，可以在不降低边界清晰度的前提下调整文件名，并在完成报告中说明。

会话原则：

- SessionKey 必须不可逆、不可暴露原始访客标识。
- 同一 VisitorHash 在超时窗口内连续访问归入同一会话。
- 超时后创建新会话。
- Session 第一页固定入口来源快照，不被后续页覆盖。
- Page sequence 单调递增；幂等重试不能重复插入同一序号。
- 页面生命周期的 `DurationMs` 仅来自客户端 Beacon / lifecycle 时间差，并设置合理上下限。
- 没有可信离开事件时允许 NULL，禁止用服务端 `vs_DurationMs` 填充。
- 跳出定义基于完成/超时后的单页会话；历史 v3 无生命周期数据时不得伪造跳出率。

TDD 最低测试：

- 首次页面访问创建 Session + sequence=1。
- 同访客窗口内第二页复用 Session、sequence=2、PageCount=2。
- 超时创建新 Session。
- 重复 Beacon 幂等。
- 负数/超大/未来时间等异常生命周期值被拒绝或规范化。
- 缺少离开事件时 dwell 为 NULL。
- server `vs_DurationMs` 无法进入 visitor dwell 计算。
- 单页会话在结束后为 bounce，多页会话不是 bounce。

建议新建：

- `tests/V4SessionTest.php`
- `tests/V4PageLifecycleTest.php`

### T3-C：Beacon / RUM 兼容扩展

读取现有 RUM / Beacon 实现，不破坏 v3 Web Vitals 采集。

要求：

- 在现有 Beacon 接收路径上扩展页面生命周期数据，或新增独立受限 endpoint；选择改动更小、权限边界更清晰的方案。
- 保持已有 LCP/INP/CLS/TTFB/FCP 语义。
- 请求大小、字段名、数值范围、来源页面必须校验。
- 禁止接收 Cookie、Token、完整原始请求头或不受限任意 JSON。
- CSRF/nonce/同源策略按 Z-Blog 插件现有安全模式处理；对匿名前台 Beacon 不得要求只有后台用户才拥有的凭据。
- 页面关闭时优先支持 `navigator.sendBeacon`；必要时 fetch keepalive 作为兼容路径。

TDD 最低测试：

- 合法 lifecycle payload 可被解析。
- 未知字段被丢弃。
- 超长字段/参数拒绝。
- 非法 SessionKey/PathKey 拒绝。
- 重复事件不会制造重复页面序列。

### T3-D：代码事件采集

新增聚焦模块，例如：

- `inc/events.php`

事件 API 只支持代码埋点，不实现可视化事件编辑器。

最低字段：

- event name
- SessionID（可空）
- VisitorHash（匿名）
- PathKey
- TriggeredAt
- allowlisted params

要求：

- 事件名字符集和长度限制。
- 参数 key 白名单/长度限制/总 payload 大小限制。
- 禁止 IP、Cookie、Token、完整 UA、Referer 等敏感数据进入 `ev_Params`。
- 支持统计所需：事件总量、触发次数、独立触发用户、人均触发。
- 高基数参数不自动建索引。

建议测试：

- `tests/V4EventsTest.php`

至少覆盖合法事件、非法名称、超长 payload、敏感 key 拒绝/剔除、VisitorHash 独立用户口径。

### T3-E：IP 过滤

在现有 IP 获取 / trusted proxy 逻辑之后、写入主日志/会话/事件之前执行过滤。

要求：

- 支持单 IP 与 CIDR。
- IPv4 / IPv6 都要测试。
- 规则唯一性使用哈希/规范化值，不因文本大小写/IPv6 表示差异制造重复规则。
- trusted proxy / `X-Forwarded-For` 的现有安全边界不能被绕过。
- 被过滤请求不得写主日志、Session/Page/Event。
- 后台展示继续遵守现有 IP 脱敏设置。

建议新建：

- `inc/ip_filter.php`
- `tests/V4IpFilterTest.php`

### T3-F：基础汇总与查询契约

T3 只实现 T4 所需的统计底座，不实现最终后台页面。

必须能提供：

- session count
- new/returning session foundation
- average page depth
- valid dwell average
- bounce sessions / bounce rate foundation
- event total
- event unique visitors
- per-user event average

要求：

- 优先新增独立会话/事件汇总逻辑，不破坏现有 v3 PV/UV/IP/蜘蛛/错误/服务器耗时汇总。
- 不在后台请求路径扫描全量 Session/Event。
- 小数据可以实时校验口径，但正式路径必须支持小时/日增量汇总或可索引查询。
- 历史 v3 无生命周期数据时明确返回 unavailable / partial，不伪造 0。

可按现有模式扩展：

- `inc/rollup.php`
- `inc/query_v2.php`

如果文件职责开始过大，应创建 `inc/session_rollup.php` / `inc/event_rollup.php` 等独立模块，而不是无关重构。

建议测试：

- `tests/V4MetricsTest.php`

### T3-G：真实 Windows 实机验证

完成单元测试和静态检查后，把 Git 工作树代码安全同步/部署到：

```text
D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats
```

部署前备份当前插件运行副本；不得覆盖站点其他插件或 Z-Blog 核心。

实机验证至少包括：

1. Z-Blog 首页正常访问。
2. 后台插件页面无 PHP Fatal/Error。
3. v4 迁移首次运行后新表存在且结构符合设计。
4. 再次运行升级流程保持幂等。
5. 访问测试页生成 Session 和 Page sequence。
6. 第二页访问保持同一会话并增加 PageCount。
7. lifecycle Beacon 能更新页面离开/停留信息。
8. 测试事件能入库，敏感参数不能入库。
9. 测试 IP filter 命中后不新增主日志/会话/事件。
10. v3 主日志 288 行基线和历史表不被删除/清空；若测试期间自然增加访问量，只允许行数增加。
11. 现有 v3 PV/UV/IP/蜘蛛/RUM 基础功能不出现明显回归。

测试数据必须使用明确可识别的本地测试路径/事件名，并在不删除历史数据的前提下，仅清理本任务自己创建、且可安全确认的测试数据；无法安全确认时宁可保留并记录，不执行宽泛 DELETE。

## 自动验证命令

根据实际仓库依赖自动选择可用命令；至少执行：

```powershell
git diff --check
D:\BtSoft\php\83\php.exe -l <所有本任务改动的PHP文件>
```

如 Composer 依赖可用：

```powershell
vendor\bin\phpunit
```

如果仓库 CI 只跑特定检查，也必须本地跑相同或更严格的可用检查。PHPStan/Semgrep 仅按 `AGENTS.md` 和风险门禁启用，不为了形式机械执行。

## 失败处理

- 任一测试 RED 的原因与预期不符：先修测试环境/测试本身，不写生产代码。
- 迁移遇到真实结构冲突：停止 T3-A，记录实际 schema，不自动 DROP/重建。
- 本机运行出现 Fatal/数据库异常：自动恢复插件运行副本备份并继续定位根因。
- 如果连续 3 次修复仍出现不同层面的新故障，停止继续堆补丁，重新检查架构并在报告中明确阻塞点。
- 不因为 T3 失败而回退或删除 v3 历史数据。

## Git 与提交

建议按可独立验证的功能提交，不把整个 T3 压成一个巨型 commit：

1. `feat(v4): add idempotent foundation schema`
2. `feat(v4): add session and page lifecycle tracking`
3. `feat(v4): add code event collection`
4. `feat(v4): add IP filtering`
5. `feat(v4): add session and event metric foundation`
6. `test(v4): verify Windows runtime foundation`

实际提交数量可根据真实改动合并，但每个提交必须可解释、可回滚。

全部通过后 push：

```text
origin/feature/visit-stats-4.0
```

不得自动 merge main，不创建 PR/Tag/Release，除非 `AGENTS.md` 明确要求该阶段创建 PR 且所有对应门禁已满足；即使创建 PR，也不得宣称 Release Ready。

## 完成报告必须包含

- 实际 Git 工作树路径
- 实际 Z-Blog 运行路径
- 起始 commit 与结束 commit
- 修改/新增文件清单
- 新建数据库表及实际结构验证结果
- 会话超时与跳出率最终口径
- dwell 的来源与异常值规则
- 事件 payload 白名单规则
- IP/CIDR 过滤规则
- 单元测试命令与通过数量
- PHP 语法检查结果
- Windows 实机验证项目逐项 PASS/FAIL
- v3 历史数据保护验证结果
- GitHub CI run id / conclusion
- 是否存在未解决风险
- Release Gate（本任务默认应为 `NOT READY`）

如果任何“真实 Windows 实机验证”未执行，不得把 T3 标记完成，只能报告为部分完成/阻塞。
