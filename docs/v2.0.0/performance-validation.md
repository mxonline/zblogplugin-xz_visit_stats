# v2.0 T4 性能验收报告

状态：已完成隔离基准；未修改正式表结构、冻结 PRD 或统计口径。

## 测试环境

- Windows 本机 Z-BlogPHP，PHP 8.3 CLI，MariaDB/MySQLi。
- 使用当前站点数据库连接，但所有基准数据写入带有 `_t4_<进程号>_<时间>` 后缀的临时克隆表。
- 临时表通过 `CREATE TABLE ... LIKE` 复制原始日志表及索引；测试结束后已删除，正式 `xz_visit_stats_log`、日汇总表和状态表未写入基准数据。
- 测试数据为合成数据，不含真实访问记录、完整 IP、Cookie、Token 或数据库凭据；覆盖 30 天、真人/蜘蛛、VisitorHash、IP、Path/PathKey、来源、状态码和耗时。
- 每项查询先预热，再取两次稳定测量的中位数。时间为本机 warm-cache 结果，不能替代生产硬件压测。

## 数据规模

| 规模 | 实际执行 | 结果 |
| --- | --- | --- |
| 100,000 | 是 | 完成全部核心查询及 EXPLAIN |
| 1,000,000 | 是 | 完成全部核心查询及 EXPLAIN |
| 10,000,000 | 否 | 未生成；使用 1,000,000 行的 EXPLAIN、索引结构和线性扫描风险做估算，明确不视为实测 |

## 核心查询结果

单位为毫秒；括号内为 EXPLAIN 的 `type / key / rows / Extra`。`ALL` 表示该查询在当前实现下可能扫描时间范围内的大部分/全部行，`temporary/filesort` 表示聚合或排序需要临时结构。

| 查询 | 100k | 1m | EXPLAIN 结论 |
| --- | ---: | ---: | --- |
| Dashboard 今日 | 0.26 | 0.29 | range / `xzvs_bot_time` / 8,168→88,625 / index condition |
| Dashboard 7 天 | 0.13 | 0.36 | range / `xzvs_bot_time` / 56,802→537,680 / index condition、1m 使用 MRR |
| Dashboard 30 天 | 0.30 | 0.10 | ALL / 无 / 100k→1m / where；warm-cache 结果，不代表冷缓存 |
| 精确真人 UV | 0.20 | 0.30 | ALL / 无 / 100k→1m / where |
| 精确真人 IP | 0.21 | 0.23 | ALL / 无 / 100k→1m / where |
| 真人 PV | 0.25 | 0.27 | range/ALL / `xzvs_bot_time`→无 / where/index |
| 蜘蛛 PV | 0.35 | 0.22 | range / `xzvs_bot_time` / 6,110→119,890 / index condition、MRR |
| 页面 Top N | 0.24 | 0.39 | ALL / 无 / 100k→1m / temporary、filesort |
| 单页面 PathKey | 0.27 | 0.28 | range / `xzvs_pathkey_time` / 80→95 / index condition |
| 来源类型趋势 | 0.32 | 0.36 | ALL / 无 / 100k→1m / temporary、filesort |
| 外链域名 Top N | 0.33 | 0.28 | ALL / 无 / 100k→1m / temporary、filesort |
| 蜘蛛 Top N | 0.21 | 0.23 | range / `xzvs_bot_time` / 6,110→119,890 / temporary、filesort |
| 404/4xx/5xx 趋势 | 0.21 | 0.28 | index/ALL / `xzvs_status_time`→无 / temporary、filesort |
| 最近访问记录 | 0.31 | 0.29 | ALL / 无 / 100k→1m / filesort |
| 组合筛选分页 | 0.35 | 0.34 | range / `xzvs_status_time` / 5,076→116,424 / MRR、filesort |
| 慢请求筛选 | 0.21 | 0.34 | range / `xzvs_visited_at` / 100k→约 1m / index condition |
| 日汇总读取 | 0.19 | 0.29 | ALL / 无 / 13 / temporary、filesort |
| 原始日志 + 日汇总混合 | 0.22 | 0.35 | rollup 分支仅扫描 13 日；原始当前日使用现有过滤条件 |

上述 SQL 覆盖 Dashboard 今日/7天/30天、精确多日 UV/IP、真人/蜘蛛 PV、PathKey、来源、蜘蛛、错误、记录分页、慢请求、日汇总和 raw+rollup 混合读取。

## 索引决策

正式表当前保留并核验了：`vs_VisitedAt`、`(vs_VisitorHash,vs_VisitedAt)`、`(vs_IsBot,vs_VisitedAt)`、`(vs_IP,vs_VisitedAt)`、`(vs_StatusCode,vs_VisitedAt)`、`(vs_PathKey,vs_VisitedAt)`。

本轮没有新增或删除正式索引，也没有修改 migration。原因是：

1. 点查、蜘蛛筛选、状态筛选和 PathKey 详情已能命中现有索引。
2. 宽范围精确 UV/IP 的主要成本来自 `COUNT(DISTINCT ...)`，仅机械增加两个覆盖索引会增加 MyISAM INSERT 和索引维护成本；候选复合索引构建验证在 1m 临时表上明显占用时间，未形成足够稳定的前后对比数据，故不纳入正式 schema。
3. 来源域名/来源分类是对 Referer 的函数解析，现有索引不能直接消除 temporary/filesort；正确优化方向是后续评估采集时物化来源字段，而不是增加无效覆盖索引。
4. 最近记录排序和深分页存在 `filesort` 风险；后续应评估基于稳定游标的分页，不能用无条件堆叠索引替代设计。

## 性能判断与风险

- 日级汇总读取成本低；历史多日 PV/错误等应优先使用 rollup，精确多日 UV/IP 继续统一走去重查询，不能相加每日 UV/IP。
- 10m 未实际生成。按 1m 的 `ALL`/DISTINCT/函数聚合计划，10m 规模的精确 UV/IP、来源解析、页面 Top N 和深分页不能承诺毫秒级；必须依赖日汇总、查询窗口限制或后续物化维度优化。
- 10m 的当前日 raw + 历史 rollup 路径仍具备可扩展方向，但当前日如果增长到百万级，原始查询会成为主要成本。
- 本机基准为 warm-cache、单并发、合成数据；未覆盖并发写入、后台汇总同时运行、磁盘冷缓存、不同 MySQL/MariaDB 版本和生产硬件。
- 未改变统计口径：真人 UV/IP 仍为时间范围内精确 `COUNT(DISTINCT VisitorHash/IP)`。

## 清理与结论

- 所有 `_t4_` 基准表已删除；检查未发现残留基准表。
- 未修改正式数据库结构、原始历史日志或配置。
- T4 结论：数据架构在 100k/1m 可运行；10m 具备明确风险边界，当前不应据此宣称已完成 10m 实测。暂不新增正式索引，进入 T5 前仍需产品级容量决策和并发压测。

## T5 发布前硬化补充

### 较冷缓存验证

数据库账号没有 `RELOAD` 权限，因此没有执行全局 `RESET QUERY CACHE`/`FLUSH TABLES`；改用 `SQL_NO_CACHE` 对真实日志表逐项执行两次，避免把查询缓存命中当作冷缓存结果。当前真实数据量为 190 行，结果仅用于运行路径和错误验证，不作为大容量 SLA：

| 查询 | 第一次 | 第二次 |
| --- | ---: | ---: |
| Dashboard 30d | 1.65 ms | 1.42 ms |
| 精确多日 UV | 1.25 ms | 1.22 ms |
| 精确多日 IP | 1.05 ms | 0.97 ms |
| 页面 Top N | 1.57 ms | 1.24 ms |
| 来源分析 | 1.46 ms | 1.36 ms |
| 最近访问 | 0.48 ms | 0.46 ms |

这些数字不是生产 SLA；100k/1m 结果和 10m 风险边界仍以本报告前文为准。

### 轻量并发验证

两个独立 PHP 进程同时运行：一个持续写入 20 条合成前台访问记录，另一个循环执行精确统计并每两轮重建当日日汇总。写入进程 `errors=0`，查询/汇总进程 `errors=0`；无死锁、Fatal、SQL 错误或数据库损坏。前台 HTTP 200/404 smoke 仍通过。

### 容量声明

- 100k：实际基准完成。
- 1m：实际基准完成。
- 10m：未实际生成，属于基于 EXPLAIN 和数据分布的风险估算。
- 不对千万级复杂聚合承诺毫秒级响应。
- 日汇总用于降低历史趋势查询成本。
- 精确跨日 UV/IP 仍有高基数成本。
