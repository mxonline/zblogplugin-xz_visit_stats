# v3.0.0 本机 Schema Inventory

状态：开发环境只读盘点记录；不包含数据库凭据、原始 IP 或访问明细。

## 盘点结论

- 原始事实表 `zbp_xz_visit_stats_log` 存在，历史日志保留。
- v2.0 日汇总表与汇总状态表存在。
- 上一轮 v3 尝试残留的小时汇总表、保存筛选表及物化维度字段存在，已按兼容结构复用。
- 旧 `zbp_xz_visit_stats_pages`、`zbp_xz_visit_stats_keywords` 表存在；本轮不删除、不写入、不纳入新事实路径。
- `xz_visit_stats_errors`、`xz_visit_stats_security` 未发现。

## 已核对结构

原始日志包含：PathKey、来源类型/域名、AI 来源、UTM 五字段、页面标题/PostID、地域降级字段、AI crawler 字段，以及既有 PV/UV/IP、蜘蛛、状态码和 DurationMs 字段。

关键索引已核对：VisitedAt、VisitorHash+VisitedAt、IsBot+VisitedAt、IP+VisitedAt、StatusCode+VisitedAt、PathKey+VisitedAt、SourceType+VisitedAt、SourceDomain 前缀索引、UtmCampaign 前缀索引。

小时汇总包含小时、维度、维度键和真人/蜘蛛/错误/耗时聚合字段；保存筛选包含用户、名称、模块、JSON 筛选条件和更新时间字段。

## 迁移安全规则

- 已存在表/字段/索引先校验兼容性；兼容则复用。
- 缺失结构才创建或增加。
- 不执行 DROP，不清空原始日志，不删除未知旧表。
- 结构不兼容时抛出迁移错误，等待人工制定非破坏性兼容方案。
- 迁移可重复执行；本机重复执行验证通过。
