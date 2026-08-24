# v3.0.0 本机性能验收记录

日期：2026-08-25

## 环境

- Windows 本机 Z-Blog，PHP 8.3.8，MySQL/Z-Blog 当前开发数据库。
- 当前原始访问日志：210 行；未向真实历史表灌入合成压测数据。

## EXPLAIN 结果

在当前真实表上执行 30 天范围查询：

| 查询 | 命中索引 | 访问类型 | 结论 |
| --- | --- | --- | --- |
| 真人按时间 | `xzvs_bot_time` | range | 使用 `vs_IsBot,vs_VisitedAt` |
| VisitorHash 去重 | `xzvs_visitor_time` | index | 使用 VisitorHash/时间复合索引 |
| PathKey 按时间 | `xzvs_pathkey_time` | range | 使用 PathKey/时间复合索引 |
| 404 按时间 | `xzvs_status_time` | range | 使用状态码/时间复合索引 |

当前真实数据规模无法代表 10 万、100 万或 1000 万行生产耗时，因此不作毫秒级 SLA 承诺。宽范围精确 UV/IP 仍会产生高基数 DISTINCT 成本；日/小时汇总主要用于历史趋势和分类排行，不能代替跨日精确去重。

## 优化决策

本轮没有机械增加覆盖索引：现有核心索引已命中，新增索引会增加前台 INSERT 成本。RUM 使用独立表和 PathKey/时间索引，服务器 `DurationMs` 与 RUM 指标分开查询。

## 未实测范围

100k、1m、10m 合成数据压测未在本机执行；后续应在独立 benchmark 数据库完成，并记录冷缓存、并发 INSERT、汇总重建和深页 keyset 分页结果。
