# xz_visit_stats v2.0 技术设计 v1.0

状态：技术设计基线。输入为已冻结的 `PRD-v1.1.md`、v1.3 真实代码/表结构以及本机 v1.3 数据画像。

## 1. 总体原则

- `%pre%xz_visit_stats_log` 继续作为原始访问事实表和唯一事实源，不再为页面、来源、错误、蜘蛛分别复制原始事件。
- v2.0 新增结构只服务于派生维度、日级汇总、状态与性能，不改写历史事实。
- 默认真人指标与蜘蛛指标分离。
- 当前日优先使用有界原始日志查询保证新鲜度；已完成日期优先使用日级汇总。
- 精确多日 UV/IP 不允许简单相加每日 UV/IP，必须使用有界原始日志去重查询或等价精确方案。
- 迁移和汇总必须幂等、可恢复、可重建，不允许一次升级长时间锁死大表。

## 2. v1.3 现状复用

现有原始表 `%pre%xz_visit_stats_log` 已保存 IP、VisitorHash、Url、Path、Referer、UA、浏览器/OS/设备、IsBot、BotName、StatusCode、DurationMs、VisitedAt。

现有 `Path` 已在采集时通过 `parse_url(..., PHP_URL_PATH)` 去除 query string，因此 v2.0 不需要重新创建完整页面事件表。

现有索引保留：
- `xzvs_visited_at (VisitedAt)`
- `xzvs_visitor_time (VisitorHash, VisitedAt)`
- `xzvs_bot_time (IsBot, VisitedAt)`
- `xzvs_ip_time (IP, VisitedAt)`
- `xzvs_status_time (StatusCode, VisitedAt)`

是否增加/替换复合索引必须通过 EXPLAIN 和目标数据量测试决定，不机械堆索引。

## 3. 统计口径技术定义

### 真人访问
- Visitor PV：`IsBot = 0` 的记录数。
- Visitor UV：时间范围内 `IsBot = 0` 的 `VisitorHash` 精确去重数。
- Visitor IP：时间范围内 `IsBot = 0` 的 `IP` 精确去重数。

### 蜘蛛
- Bot PV：`IsBot = 1` 的记录数。
- Bot 类型：`BotName`，空名称统一落入 Other Bot。

### 状态码
- 4xx：400-499。
- 5xx：500-599。
- 404 单独统计。

### 服务端处理耗时
- 数据源：`DurationMs`。
- 对外名称固定为“服务端处理耗时”，不得描述为页面加载时间或前端性能。

### 时间
- 所有日期边界使用 Z-Blog 站点时区。
- 日汇总的 `bucket_day` 使用站点本地日期 `YYYY-MM-DD`。
- 汇总状态记录生成时使用的站点时区；站点时区变化后必须把旧汇总标记为需要重建。

## 4. Path 规范化

v1.3 的 Path 已无 query string。v2.0 增加统一规范化函数：

1. 空值转为 `/`；
2. 确保以 `/` 开头；
3. 根路径始终为 `/`；
4. 非根路径移除末尾 `/`；
5. 不做大小写折叠；
6. 不主动 URL decode，避免改变实际资源语义；
7. 完整 URL 继续只用于访问详情。

为大数据量页面查询新增派生列 `vs_PathKey CHAR(64)`，值为规范化 Path 的 SHA-256。新访问写入时同步生成；历史记录采用可恢复的分批回填，不阻塞正常访问。

新增候选索引：`xzvs_pathkey_time (vs_PathKey, vs_VisitedAt)`。

## 5. 日级通用汇总表

新增 `%pre%xz_visit_stats_rollup_daily`，使用“日期 + 维度类型 + 维度键”统一承载 P0 聚合，避免为每个分析模块创建一张事实副本表。

建议字段：

- `rd_ID BIGINT UNSIGNED`
- `rd_Day CHAR(10)`：站点本地日期
- `rd_Dimension VARCHAR(24)`
- `rd_KeyHash CHAR(64)`
- `rd_Key VARCHAR(512)`
- `rd_VisitorPV BIGINT UNSIGNED`
- `rd_VisitorUV BIGINT UNSIGNED`
- `rd_VisitorIP BIGINT UNSIGNED`
- `rd_BotPV BIGINT UNSIGNED`
- `rd_Error4xx BIGINT UNSIGNED`
- `rd_Error5xx BIGINT UNSIGNED`
- `rd_DurationSum BIGINT UNSIGNED`
- `rd_DurationCount BIGINT UNSIGNED`
- `rd_LastVisitAt BIGINT UNSIGNED`
- `rd_UpdatedAt BIGINT UNSIGNED`

唯一键：`(rd_Day, rd_Dimension, rd_KeyHash)`。

主要维度：
- `site/all`：站点每日总览
- `path/<normalized path>`：页面
- `source_type/<direct|search|external|social|internal|other>`
- `source_domain/<domain>`
- `bot/<BotName>`
- `status/<HTTP status code>`

说明：每日 UV/IP 可以保存用于单日与趋势展示，但跨多日的 UV/IP **不能直接 SUM**。

## 6. 汇总状态表

新增 `%pre%xz_visit_stats_rollup_state`，只保存汇总任务状态，不保存访问事实。

建议字段：
- `rs_Name VARCHAR(64)` 主键
- `rs_LastCompletedDay CHAR(10)`
- `rs_BackfillDay CHAR(10)`
- `rs_Timezone VARCHAR(64)`
- `rs_LastRunAt BIGINT UNSIGNED`
- `rs_Status VARCHAR(24)`
- `rs_LastError TEXT`
- `rs_UpdatedAt BIGINT UNSIGNED`

用途：历史回填、断点恢复、时区变化检测、后台“最近汇总时间/异常状态”。

## 7. 查询职责边界

### 原始日志直接查询
- 访问记录分页/筛选
- 最近 5/15/30 分钟
- 单条详情
- IP 近期访问
- 错误明细
- 蜘蛛明细
- 精确多日 Visitor UV/IP
- 低频自定义范围查询

### 日汇总优先
- 7/30 天 PV 趋势
- 页面 Top N
- 来源类型趋势
- 搜索引擎/外链域名 Top N
- 蜘蛛趋势与 Top N
- 状态码/404/5xx 趋势
- 历史周期对比

### 当前日
当前日数据优先从 `xz_visit_stats_log` 做**有界当日查询**，避免为了实时性在每次前台访问时同步更新大量汇总。已完成日期使用 rollup。

## 8. 多日 UV/IP 的正确处理

每日 UV/IP 不能相加得到 7/30 天 UV/IP。

P0 采用：
- Dashboard 多日 UV/IP：按时间范围在原始表做精确 DISTINCT，查询必须受 `IsBot + VisitedAt` 范围限制并经过索引/EXPLAIN 验证。
- 页面 Top N：先由 rollup 得到 Top Path 候选，再只对 Top N Path 在原始表做精确 UV/IP 查询，避免对全量页面执行高成本 GROUP BY DISTINCT。
- 单页面详情：只对该 PathKey + 时间范围查询原始表。

## 9. 来源、蜘蛛、错误

来源分类在汇总/查询层由 Referer 解析，不新增原始来源事件表。搜索关键词仅在 Referer 实际包含且可解析时展示，不创建 `xz_visit_keywords`。

蜘蛛继续使用 `IsBot/BotName`，P0 不新增蜘蛛事实表。

错误继续使用 `StatusCode`，不创建 `xz_visit_errors` 原始表；错误趋势进入通用日汇总。

`xz_visit_security` 不进入 v2.0。

## 10. 索引策略

现有索引先保留。技术实现阶段通过 10 万、100 万、1000 万行测试和 EXPLAIN 决定是否增加：

- `xzvs_pathkey_time (PathKey, VisitedAt)`：P0 候选，页面查询需要。
- 覆盖型真人 UV 索引 `(IsBot, VisitedAt, VisitorHash)`：性能测试证明有收益后增加。
- 覆盖型真人 IP 索引 `(IsBot, VisitedAt, IP)`：性能测试证明有收益后增加。

禁止为了“看起来更快”无证据增加大量索引，因为每个索引都会增加前台采集 INSERT 成本。

## 11. 汇总生成策略

- 不在每次前台访问时同步更新所有分析表。
- 历史回填按“天”执行，可中断、可续跑。
- 日汇总写入采用同日同维度覆盖/UPSERT，保证重复执行结果一致。
- 当前日可以按需重新计算；已完成日期默认视为稳定。
- 后台首次进入、维护页和手动重建入口均可触发受时间预算控制的补汇总。
- 汇总失败不影响前台访问采集，并在状态表记录失败原因。

## 12. Phase 1 返工结论

旧 Phase 1 设计按冻结 PRD 正式处理：

- `xz_visit_pages`：旧“单行累计页面表”取消，能力并入通用 `rollup_daily` 的 `path` 维度。
- `xz_visit_keywords`：取消。
- `xz_visit_errors`：取消独立原始表。
- `xz_visit_security`：取消/后移。
- 如果开发环境曾创建旧 Phase 1 临时表，不在正式升级路径中自动 DROP；开发环境可备份后清理。正式 v1.3 → v2.0 迁移不执行破坏性删除。

## 13. v1.3 → v2.0 迁移顺序

1. 检查当前原始表和现有索引；
2. 创建 `rollup_daily` 与 `rollup_state`；
3. 给原始表增加 `PathKey` 派生列；
4. 分批回填历史 PathKey，保存进度；
5. 创建/验证 PathKey 索引；
6. 按天回填历史 rollup；
7. 记录数据库版本和汇总状态；
8. 重复执行迁移必须无副作用；
9. 新安装与旧版本升级最终 schema 必须一致。

任何大表回填必须可分批执行，不能在一次页面请求中长事务扫描全部历史记录。

## 14. 开发阶段拆分

- **T1｜数据核心**：返工 Phase 1，完成 schema、PathKey、migration、rollup state、日汇总与重建。
- **T2｜查询层**：统一真人/蜘蛛口径、时区、来源分类、下钻参数、精确 UV/IP 查询。
- **T3｜后台模块**：总览、访问记录、页面、来源、蜘蛛、错误、设置维护。
- **T4｜性能验收**：10 万/100 万/1000 万数据测试、EXPLAIN、索引调整、失败恢复。
- **T5｜完整发布流程**：真实 v1.3 升级、回归、CI、Release Dry Run、GitHub Release、Notion 回写。

T1 完成并通过本机 Z-Blog 数据库实机验证后，才进入 T2/T3 大规模功能开发。

## 15. 下一步

技术设计已经把产品需求映射到数据与迁移方案。下一步直接让 Codex 在真实工作区执行 **T1｜数据核心**：先核对本机当前分支与 Phase 1 代码，再按本设计返工 schema/migration，并执行真实 v1.3 → v2.0 本机升级验证。