# xz_visit_stats v4.0 — 本地结构审计 v1.0

## 状态

T2 当前状态：**完成（只读审计通过，未执行 v4 迁移）**。

真实审计时间：2026-08-26 15:28:58（Asia/Hong_Kong；JSON 使用 UTC `2026-08-26T07:28:58+00:00`）。

51.LA 对标需求和 v3 → v4 缺口已经完成，当前必须在真实 Windows Z-BlogPHP 测试环境读取实际数据库结构，再进入迁移设计。

不得用仓库里的 `inc/install.php` 定义代替真实数据库审计结果。

## 审计目标

只读取并记录：

- Z-BlogPHP 版本
- PHP CLI 版本
- 数据库类型 / 驱动 / 服务端版本
- xz_visit_stats 插件版本
- 安全配置项
- 所有 xz_visit_stats 相关表
- 每张表的列、类型、默认值、Null、Extra
- 每张表的索引定义
- 每张表的行数

不读取或导出：

- 单条访客记录
- IP 明细
- VisitorHash 明细
- Referer 明细
- Cookie
- 数据库密码
- Token / Key

## 审计脚本

脚本：

```text
scripts/v4-schema-audit.ps1
```

默认本地环境：

```text
Z-Blog root: D:\wwwroot\xinzhao_net
Plugin root: D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats
PHP CLI:    D:\BtSoft\php\83\php.exe
```

这些只是测试环境默认值，脚本参数允许覆盖。

## 安全设计

审计脚本在加载 Z-Blog bootstrap 前设置：

```text
ZBP_SAFEMODE = true
```

这会阻止主题和插件 include / ActivePlugin 流程运行，避免触发 xz_visit_stats 的升级、采集、自动清理等逻辑。

脚本内部还有第二层 SQL 门禁，只允许：

```text
SELECT
SHOW
DESCRIBE
EXPLAIN
```

任何其他 SQL 前缀都会被脚本直接拒绝。

## 本地执行

在真实插件工作区切到 `feature/visit-stats-4.0` 后，由 Codex 自动执行：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\v4-schema-audit.ps1
```

如实际环境路径不同，Codex应先自动检测，再使用参数覆盖，不要求用户手工改脚本。

示例：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\v4-schema-audit.ps1 `
  -ZBlogRoot 'D:\wwwroot\xinzhao_net' `
  -PhpPath 'D:\BtSoft\php\83\php.exe'
```

## 输出

默认输出：

```text
docs/v4.0.0/audit-output/schema-audit-YYYYMMDD-HHMMSS.json
```

JSON 应包含：

- `runtime`
- `safe_plugin_config`
- `tables`
- `safety`

其中 `safety` 需要明确记录 Safe Mode、允许的 SQL 类型、实际执行的只读查询数量，以及没有导出访客级数据和秘密信息。

## 真实审计结果

执行命令：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\v4-schema-audit.ps1 `
  -ZBlogRoot 'D:\wwwroot\www.xzhao.net' `
  -PhpPath 'D:\BtSoft\php\83\php.exe' `
  -OutputDir '.\docs\v4.0.0\audit-output'
```

运行时版本：

| 项目 | 真实值 |
| --- | --- |
| Z-BlogPHP | `173540` |
| PHP CLI | `8.3.8` |
| 数据库 | MySQL，`Database__MySQLi` |
| 数据库服务端 | `5.7.38-log` |
| 表前缀 | `zbp_` |
| 插件 | `xz_visit_stats`，`3.0.0` |

真实表摘要（共 9 张表；列定义、NULL、默认值、Extra 和索引完整内容见同目录 JSON）：

| 表 | 行数 | 结构摘要 | 主要索引 |
| --- | ---: | --- | --- |
| `zbp_xz_visit_stats_log` | 288 | 现有 v3 访问日志；含 PathKey、来源/UTM、页面、地域、AI 爬虫和服务端耗时字段 | 主键；访问时间、访客+时间、蜘蛛+时间、IP+时间、状态+时间、来源+时间、域名+时间、活动+时间、PathKey+时间 |
| `zbp_xz_visit_stats_keywords` | 0 | 历史关键词表：ID、Engine、Keyword、Url、Count、Updated | 主键 |
| `zbp_xz_visit_stats_page_uv` | 1 | 历史页面 UV 表：ID、Url、VisitorHash、Created | 主键 |
| `zbp_xz_visit_stats_pages` | 1 | 历史页面汇总：ID、Url、Title、PV、UV、LastVisit | 主键 |
| `zbp_xz_visit_stats_rollup_daily` | 85 | 日级维度汇总，含真人 PV/UV/IP、蜘蛛、错误和耗时计数 | 日、日+维度+KeyHash、维度+日 |
| `zbp_xz_visit_stats_rollup_hourly` | 2 | 小时级维度汇总，含真人 PV/UV/IP、蜘蛛、错误和耗时计数 | 小时、小时+维度+KeyHash、维度+小时 |
| `zbp_xz_visit_stats_rollup_state` | 3 | 汇总任务状态、回填游标、时区、错误和更新时间 | 主键 `rs_Name` |
| `zbp_xz_visit_stats_rum` | 5 | Beacon 访客环境和 LCP/INP/CLS/TTFB/FCP；性能指标允许 NULL | 主键；时间、PathKey+时间 |
| `zbp_xz_visit_stats_saved_filters` | 1 | 用户保存筛选：用户、视图、筛选 JSON 文本和时间 | 主键；用户+视图 |

源码与真实结构差异：

1. `inc/install.php` 直接声明的只有主日志表和基础 v3 字段；真实库额外保留了 `keywords`、`page_uv`、`pages` 三张历史表。它们不在当前安装定义中，不得在 v4 迁移中删除。
2. 主日志真实结构已包含 v3 升级逻辑要求的 PathKey、来源/域名/活动索引，且 `vs_ID`、`vs_VisitedAt` 为无符号 BIGINT 自增/时间字段；与 `inc/upgrade/migrate.php` 的 v3 兼容断言一致。
3. 日汇总、小时汇总、汇总状态、保存筛选和 RUM 表由升级逻辑创建，真实列和索引与当前 v3 迁移定义一致。v4 不应重新创建或重命名这些表。
4. 审计脚本只读取列和索引，没有导出存储引擎；源码 DDL 中的引擎选择仍需在 T3 以 `SHOW TABLE STATUS` 单独确认，不在本 T2 猜测。

大表风险：当前主日志仅 288 行，日汇总 85 行，尚未达到压测规模；但主日志包含文本 URL、Referer、UA，v4 会话/事件写入不能通过无界回扫历史日志完成。应采用增量游标、批次上限和独立时间索引。

## 审计安全结论

审计 JSON：`docs/v4.0.0/audit-output/schema-audit-20260826-152858.json`。

- `mode` 为 `read-only`；`zbp_safe_mode` 和 `plugin_and_theme_loading_disabled` 均为 `true`。
- 共执行 29 条查询，全部以 `SELECT`、`SHOW`、`DESCRIBE` 或 `EXPLAIN` 开头；没有 DDL/DML。
- `visitor_level_rows_exported=false`、`secrets_exported=false`；JSON 只含运行时、白名单配置、表结构、索引、行数和查询模板，不含访客级明细、密码、Token、Cookie 或私钥。
- 本任务没有登录后台、重新启用插件、运行升级函数或手工 SQL。

## T2 验收标准

- [x] 已在真实 Windows Z-BlogPHP 环境执行审计脚本。
- [x] 已记录真实 PHP / Z-Blog / 数据库 / 插件版本。
- [x] 已记录 xz_visit_stats 相关表的真实列定义。
- [x] 已记录真实索引。
- [x] 已记录真实行数。
- [x] 审计过程没有执行 DDL / DML。
- [x] 审计 JSON 已检查，不含密码、Token、Cookie 或访客级明细。
- [x] 已把审计结论写回本文件。
- [x] 已基于真实结果形成 v4 增量迁移设计。

## T2 边界

本报告锁定的是 v4 增量迁移的结构依据，不代表迁移已经执行，也不代表 v4 已完成本地实机验证。T3 必须继续在备份/可回滚边界内验证迁移、采集和统计口径。

## T2 完成后的直接下一步

读取真实审计 JSON，形成：

```text
docs/v4.0.0/MIGRATION-DESIGN-v1.0.md
```

迁移设计至少应确定：

- 新增表
- 新增列
- 新增索引
- 幂等检测
- 大表现状和风险
- 旧数据兼容策略
- 回滚 / 停止边界
- T3 会话、页面序列、事件和汇总层的实际落库方案
