# v3.0.0 V3-T5 最终验收

日期：2026-08-25
候选分支：`feature/visit-stats-3.0`

## 结论

本轮不能进入发布。下表中的 **BLOCKED** 项属于冻结 PRD 的 P0；虽然已有部分底层采集或查询函数，但没有可用后台功能及实机验收证据时，不视为完成。

独立数据库的新装/升级 parity：**NOT REQUIRED**（本轮用户明确禁止 `CREATE DATABASE` / `DROP DATABASE` 等数据库管理操作）。它没有被伪装为 PASS，也不影响本表中已发现的其它 P0 阻断项。

## P0 验收矩阵

| # | P0 | 状态 | 可复核证据 / 结论 |
|---:|---|---|---|
| 1 | 图表化 Dashboard | PASS | `main.php` 总览与 `assets/v3_dashboard.js`；本机 ECharts canvas 实测。 |
| 2 | PV/UV/IP 趋势 | PASS | `xz_visit_stats_v3_daily_trend()` 与总览折线图。 |
| 3 | 24 小时分析 | PASS | `xz_visit_stats_v3_hour_rows()` 与 `xzvs-chart-hours`。 |
| 4 | 实时面板与流 | BLOCKED | 当前页面用整页 `setTimeout(...location.reload...)`；不符合 UI-SPEC 的 AJAX、页面隐藏暂停和失败恢复。 |
| 5 | IP 分析 | PASS | `view=ip` 本机后台打开，聚合查询和下钻存在。 |
| 6 | 可信代理/真实 IP | PASS | T4 已实测不可信伪造头、可信 IPv4/IPv6 CIDR 链。 |
| 7 | 地域能力与隐私降级 | BLOCKED | 地域字段可采集，但 IP 模块未展示国家/地区及 `ip_mode=masked` 降级状态。 |
| 8 | 浏览器分析 | PASS | `view=environment` 本机后台打开；Browser 聚合存在。 |
| 9 | 操作系统分析 | PASS | `view=environment` 的 OS 聚合存在。 |
| 10 | 设备类型分析 | PASS | `view=environment` 的 Device 聚合存在。 |
| 11 | 页面标题/Z-Blog 内容关联 | BLOCKED | Collector 存储 `vs_PageTitle` / `vs_PostID`，但页面/记录/Drawer 未呈现关联。 |
| 12 | 入口页面分析 | BLOCKED | 未发现入口/着陆页查询或后台视图。 |
| 13 | UTM Campaign | PASS | `view=campaign`、UTM 字段与物化维度已存在。 |
| 14 | AI 助手来源识别 | PASS | 来源维度含 AI source，Campaign/AI 页面可打开。 |
| 15 | AI 爬虫独立分类 | BLOCKED | `vs_AiCrawler` 可写入，但蜘蛛页面未提供 AI crawler 独立统计/筛选。 |
| 16 | CSV 导出 | PASS | `main.php` root 权限、93 天/5000 行边界与公式前缀保护；T4 已验未授权边界。 |
| 17 | 保存常用筛选 | BLOCKED | `xz_visit_stats_v3_saved_filters()` / save 函数存在，但后台未提供保存/调用交互。 |
| 18 | 小时级汇总 | PASS | `rollup_hourly`、状态与 T1/T4 真实重跑记录。 |
| 19 | 高频来源维度物化 | PASS | `SourceType` / `SourceDomain` / UTM 等字段与 v3 dimension 回填。 |
| 20 | Keyset/游标分页 | BLOCKED | `xz_visit_stats_v3_keyset_records()` 存在，但访问记录 UI 仍使用 `page` + OFFSET 查询。 |
| 21 | 同比/环比 | BLOCKED | `xz_visit_stats_v3_comparison()` 仅计算，Dashboard 未渲染对比结果。 |
| 22 | 错误来源/蜘蛛/AI 与 404 关联 | BLOCKED | 错误模块有基础状态与 Path；缺来源、蜘蛛/AI crawler 的关联视图。 |
| 23 | 服务端耗时正式分析 | BLOCKED | 有平均 DurationMs 与慢页面，未提供 UI-SPEC 要求的 P50/P75/P95、慢请求和趋势完整视图。 |
| 24 | v2.x 升级兼容 | PASS | T1/T4 在现有历史库执行幂等 migration/回填/汇总，历史表保留；隔离库 parity 未执行且标记 NOT REQUIRED。 |
| 25 | 可选 Beacon | PASS | 本轮实机验证：默认关闭不注入 `rum.js`；启用后同源 endpoint 返回 204；异常路径隔离。 |
| 26 | RUM 指标及与 DurationMs 分离 | PASS | `rum.php`、`rum.js` 与性能页明确分区；实测 LCP/INP/CLS/TTFB/FCP 写入及 NULL 缺失值。 |
| 27 | 屏幕/视口/语言采集和分析 | BLOCKED | 采集与落库可用，性能/环境页面未提供这些维度的聚合分析或下钻。 |

## 本轮新增实机证据与修复

- Beacon hook 从仅作用于其它页的 Header hook 改为模板标签阶段写入 `$zbp->header`；开启时首页载入本地 `rum.js`，关闭时脚本数为 0。
- 设置表单修复：未勾选 Beacon 时不再由旧值回填为启用。
- RUM 实测同源 POST 返回 204；完整指标写入，缺失指标保持 SQL `NULL`；测试未输出 IP、UA、Cookie 或 payload 中的敏感内容。
- 在 1366px 后台总览实测侧栏双栏布局与 6 个 ECharts canvas；11 个正式模块均可打开且未见 Fatal/SQL 错误。
- 390px 实测：插件内容区修复后宽度不超过视口、模块导航可展开；Z-Blog 原生顶栏仍保持其自身的最小宽度，但插件主内容不再横向撑出。
- 访问记录实测 Drawer 可打开与关闭；现有输出通过 PHP 转义及 JS `textContent` 写入。

## 已执行回归

- `scripts/local-verify.ps1 -PhpExe D:\\BtSoft\\php\\83\\php.exe -SkipHttp`：PASS（PHP 全量 syntax；本机无 PHPUnit 可执行文件）。
- `node --check assets/v3_admin.js`、`node --check assets/rum.js`：PASS。
- `git diff --check`：PASS。
- T4 历史证据：HTTP 200/404、migration repeat、汇总重建、可信代理、安全、CSV 边界、并发轻量验证均 PASS；对应 CI run `32818757687` 为 success。

## 发布决定

Release Gate：**BLOCKED**。在 #4、#7、#11、#12、#15、#17、#20、#21、#22、#23、#27 完成并重新验收前，禁止创建 PR、合并、Tag、GitHub Release 或发布 ZIP。
