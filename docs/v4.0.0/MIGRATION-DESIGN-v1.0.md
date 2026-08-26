# xz_visit_stats v4.0 — 增量迁移设计 v1.0

## 1. 设计依据与边界

本设计基于 2026-08-26 在真实 Windows Z-BlogPHP 环境执行的只读审计：PHP 8.3.8、Z-BlogPHP 173540、MySQL 5.7.38-log、插件 3.0.0。真实表结构见 `audit-output/schema-audit-20260826-152858.json` 和 `SCHEMA-AUDIT-v1.0.md`。

本文件只定义 T3 实施依据，不执行任何 DDL/DML，不改变现有数据库。v3 原始日志及历史表必须保留。

## 2. v3 结构复用原则

- 继续读取 `zbp_xz_visit_stats_log` 的 `vs_VisitedAt`、`vs_VisitorHash`、`vs_PathKey`、来源、设备、地域和机器人字段。
- 继续使用现有日/小时汇总，不重建或重命名 `rollup_daily`、`rollup_hourly`、`rollup_state`、`rum`、`saved_filters`。
- 不把 `vs_DurationMs` 当作页面停留时长；它仍是服务器处理耗时。
- `keywords`、`page_uv`、`pages` 是真实库中的历史遗留表，虽然不在当前 `install.php` 中，不删除、不迁移、不覆盖。
- 不向主日志追加 v4 语义列作为第一步。会话与事件通过关联表落库，避免对 288 行以上的热表做高风险重写；只有 T3 实测证明需要时，才单独提出可回滚的列变更。

## 3. v4 新增表

表名均使用 Z-Blog 前缀：`%pre%xz_visit_stats_...`。所有表使用 BIGINT 自增主键、明确的 `NOT NULL` 默认值，度量/离开时间等未知值使用可解释的 NULL；不使用隐式动态 SQL 表名。

### 3.1 会话表：`xz_visit_stats_sessions`

建议列：

| 列 | 类型/规则 | 用途 |
| --- | --- | --- |
| `se_ID` | BIGINT UNSIGNED PK AUTO_INCREMENT | 内部主键 |
| `se_SessionKey` | CHAR(64) NOT NULL | 不可逆会话键，唯一 |
| `se_VisitorHash` | CHAR(64) NOT NULL | 站内匿名访客关联 |
| `se_StartedAt` / `se_LastSeenAt` | BIGINT UNSIGNED NOT NULL | 会话边界 |
| `se_EntryPathKey` / `se_ExitPathKey` | CHAR(64) NOT NULL DEFAULT '' | 入口/退出页面 |
| `se_PageCount` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | 有效页面数 |
| `se_DurationMs` | BIGINT UNSIGNED NULL | Beacon/页面生命周期推导的停留时长，不填服务器耗时 |
| `se_IsBounce` | TINYINT(1) NOT NULL DEFAULT 0 | 会话结束后计算 |
| `se_SourceType` / `se_SourceDomain` | VARCHAR(24) / VARCHAR(253) | 首次访问来源快照 |
| `se_UpdatedAt` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | 幂等更新时间 |

索引：唯一 `se_SessionKey`；`(se_VisitorHash,se_LastSeenAt)`；`(se_StartedAt,se_LastSeenAt)`；`(se_EntryPathKey,se_StartedAt)`。写入成本是每个会话首次创建一次、后续按页更新一次，不能每次报表查询临时拼接。

### 3.2 页面序列表：`xz_visit_stats_session_pages`

建议列：`sp_ID`、`sp_SessionID`、`sp_LogID`（可 NULL）、`sp_Sequence`、`sp_PathKey`、`sp_Path`、`sp_EnteredAt`、`sp_LeftAt`（可 NULL）、`sp_DurationMs`（可 NULL）、`sp_ExitReason`、`sp_UpdatedAt`。

索引：唯一 `(sp_SessionID,sp_Sequence)`；`(sp_SessionID,sp_EnteredAt)`；`(sp_PathKey,sp_EnteredAt)`；`sp_LogID`。原始日志仅作为可选来源关联，不复制 Referer/UA/IP 明细。

### 3.3 事件表：`xz_visit_stats_events`

建议列：`ev_ID`、`ev_SessionID`（可 NULL）、`ev_VisitorHash`、`ev_Name`（VARCHAR(128)）、`ev_Params`（受大小限制的 TEXT，白名单字段后再存储）、`ev_PathKey`、`ev_TriggeredAt`、`ev_UpdatedAt`。

索引：`(ev_Name,ev_TriggeredAt)`；`(ev_SessionID,ev_TriggeredAt)`；`(ev_VisitorHash,ev_TriggeredAt)`；`(ev_PathKey,ev_TriggeredAt)`。事件参数禁止存 Cookie、Token、完整 UA、原始 IP 或未限制长度的用户输入。

### 3.4 目录规则表：`xz_visit_stats_directory_rules`

建议列：`dr_ID`、`dr_Name`、`dr_MatchType`、`dr_Pattern`、`dr_Action`（include/exclude）、`dr_Enabled`、`dr_SortOrder`、`dr_CreatedAt`、`dr_UpdatedAt`。

索引：`(dr_Enabled,dr_SortOrder)`；必要时对规范化规则名建立唯一约束。规则匹配在采集/查询边界使用，不能对每条历史日志无界重算。

### 3.5 导出任务表：`xz_visit_stats_export_tasks`

建议列：`ex_ID`、`ex_UserID`、`ex_Status`、`ex_Filters`（受限 JSON/TEXT）、`ex_FileName`、`ex_RequestedAt`、`ex_StartedAt`、`ex_FinishedAt`、`ex_RowCount`、`ex_ErrorCode`、`ex_UpdatedAt`。

索引：`(ex_UserID,ex_RequestedAt)`；`(ex_Status,ex_RequestedAt)`。文件路径必须由服务端生成并限制在专用导出目录，任务状态机只允许 pending → running → completed/failed。

### 3.6 IP 过滤规则表：`xz_visit_stats_ip_filters`

建议列：`if_ID`、`if_RuleType`（IP/CIDR）、`if_Value`、`if_ValueHash`、`if_Enabled`、`if_Note`、`if_CreatedAt`、`if_UpdatedAt`。

索引：唯一 `(if_RuleType,if_ValueHash)`；`(if_Enabled,if_RuleType)`。展示和日志中仍遵守现有 IP 脱敏策略。

## 4. 需要新增的列

T2 不建议向现有九类表新增 v4 业务列：真实审计显示 v3 表结构已经稳定，Session/Page/Event 可以通过关联表完成。T3 若证明写入关联需要加速，再提出以下候选列并单独迁移：

- `zbp_xz_visit_stats_log.vs_SessionID BIGINT UNSIGNED NULL`：仅作为可选反向关联，不作为会话真相来源；
- `zbp_xz_visit_stats_rollup_daily.rd_SessionPV/rd_BounceSessions` 与小时等价列：只有汇总口径和回填成本验证后才添加。

候选列必须先用 `SHOW COLUMNS`/`SHOW INDEX` 幂等检测，禁止直接 ALTER；T2 不执行这些变更。

## 5. 汇总与目录规则方案

- 会话、跳出、页面深度从 Session/Page 表增量汇总；v3 日/小时表继续承担 PV/UV/IP/蜘蛛/错误/服务器耗时。
- v4 新增会话/事件汇总表（若查询实测需要）应按小时或日 + 维度 + KeyHash 设计，不在报表请求中扫描全量 Session/Event。
- 目录规则先在规则表保存，运行时将 PathKey 映射到规则结果；规则变更只影响之后的增量计算，历史重算必须显式创建任务。

## 6. 幂等检测与迁移顺序

每张新表按以下顺序执行：检查表存在 → 检查必需列及类型 → 检查索引名称和列顺序 → 仅缺失时创建；发现同名但定义不一致时停止，不自动 DROP/重建。迁移版本写入插件配置或专用状态项前，必须在所有结构断言成功后提交。

T3 顺序：

1. 备份与只读基线：记录表结构、行数、错误日志和关键聚合。
2. 创建 Session、SessionPages、Events、DirectoryRules、ExportTasks、IPFilters 表。
3. 对小批量新请求写入 Session/Page；验证幂等键、并发和失败重试。
4. 通过增量游标从 v3 日志派生可重建的 Session/Page 数据；不覆盖原始日志。
5. 增加事件接收与参数白名单，再建立事件汇总。
6. 增加目录规则、导出任务和权限验证。
7. 执行新旧统计对照、回滚演练和大表性能评估。

## 7. v3 历史兼容与大表策略

- v3 历史日志只读保留；缺少 Session 的历史记录不伪造停留时长或跳出率，页面上显示“升级前不可用”。
- 回填按 `vs_ID` 游标分批，单批上限和总耗时可配置；每批提交前后记录状态，失败从最后确认游标继续。
- 不使用 OFFSET；不在请求热路径执行全表回填；Session 生成规则固定版本，规则变更通过新任务重算而非覆盖旧结果。
- 当前主日志 288 行只是结构审计样本，不能代表生产规模；生产迁移必须先在备份/副本评估锁表、索引构建和磁盘成本。

## 8. 失败停止与回滚边界

遇到同名结构不一致、索引列顺序不一致、权限不足、磁盘不足、锁等待超时、数据校验不一致或敏感数据进入事件参数时立即停止后续步骤。不得自动删除旧表、旧列或历史数据。

回滚只允许：停止新写入、标记 v4 任务不可用、删除尚未投入使用且经确认为空的新表；不得回滚性删除已有 v3 表或通过 DROP/重建恢复结构。正式生产回滚方案必须由 T3/T5 在备份和演练后批准。

## 9. T3 交付边界

T2 交付的是审计 JSON、真实结构结论和本迁移设计。T3 才能实现 DDL、会话识别、页面序列、事件接收、汇总和后台接口；在 T3 之前不得声称 v4 已迁移、已升级或已通过本地运行时验收。
