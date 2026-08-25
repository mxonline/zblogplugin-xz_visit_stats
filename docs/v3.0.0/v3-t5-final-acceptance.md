# v3.0.0 V3-T5 最终验收

日期：2026-08-25
候选分支：`feature/visit-stats-3.0`

## 当前状态

UI Product Gate、Functional Product Gate 和 UI Terminology Gate 均已通过；`functional-acceptance.md` 中 #8–#10 已通过真实 Chrome/Edge 浏览器内核访问完成验证。

## 结论

27 项 P0 已重新按用户操作路径审查，并已取得本机操作、数据或 UI 回显证据；#8–#10 使用真实 Chrome/Edge 内核访问完成，不以自动化 HTTP 客户端替代。

独立数据库的新装/升级 parity：**NOT REQUIRED**（本轮用户明确禁止 `CREATE DATABASE` / `DROP DATABASE` 等数据库管理操作）。它没有被伪装为 PASS，也不影响本表中已发现的其它 P0 阻断项。

## P0 验收矩阵

| # | P0 | 状态 | 可复核证据 / 结论 |
|---:|---|---|---|
| 1 | 图表化 Dashboard | PASS | `main.php` 总览与 `assets/v3_dashboard.js`；本机 ECharts canvas 实测。 |
| 2 | PV/UV/IP 趋势 | PASS | `xz_visit_stats_v3_daily_trend()` 与总览折线图。 |
| 3 | 24 小时分析 | PASS | `xz_visit_stats_v3_hour_rows()` 与 `xzvs-chart-hours`。 |
| 4 | 实时面板与流 | PASS | `realtime_api.php` + `assets/v3_realtime.js`；30 秒同源 AJAX、`document.hidden` 暂停、失败提示后自动重试，本机实测“已更新”。 |
| 5 | IP 分析 | PASS | `view=ip` 本机后台打开，聚合查询和下钻存在。 |
| 6 | 可信代理/真实 IP | PASS | T4 已实测不可信伪造头、可信 IPv4/IPv6 CIDR 链。 |
| 7 | 地域能力与隐私降级 | PASS | IP 页展示地域能力状态及国家聚合；`masked` 明确不显示/推断精确地域，GeoIP 不可用时独立空状态。 |
| 8 | 浏览器分析 | PASS | 本机真实 Chrome/Edge 访问首页和文章页；后台聚合确认 Chrome、Edge。 |
| 9 | 操作系统分析 | PASS | 同一批真实浏览器记录均识别为 Windows。 |
| 10 | 设备类型分析 | PASS | 同一批真实浏览器记录均识别为桌面设备。 |
| 11 | 页面标题/Z-Blog 内容关联 | PASS | 页面页展示规范化 Path、标题、内容 ID、访问数；历史无数据标注“升级前无标题”。 |
| 12 | 入口页面分析 | PASS | 页面页展示真人 VisitorHash 在所选范围首次访问的入口页排行。 |
| 13 | UTM Campaign | PASS | `view=campaign`、UTM 字段与物化维度已存在。 |
| 14 | AI 助手来源识别 | PASS | 来源维度含 AI source，Campaign/AI 页面可打开。 |
| 15 | AI 爬虫独立分类 | PASS | 蜘蛛页新增 AI crawler 独立聚合区；无数据时有明确空状态。 |
| 16 | CSV 导出 | PASS | `main.php` root 权限、93 天/5000 行边界与公式前缀保护；T4 已验未授权边界。 |
| 17 | 保存常用筛选 | PASS | 记录页提供命名保存、CSRF、当前用户筛选列表及恢复链接；本机实测保存和回显。 |
| 18 | 小时级汇总 | PASS | `rollup_hourly`、状态与 T1/T4 真实重跑记录。 |
| 19 | 高频来源维度物化 | PASS | `SourceType` / `SourceDomain` / UTM 等字段与 v3 dimension 回填。 |
| 20 | Keyset/游标分页 | PASS | 记录页实际调用 `xz_visit_stats_v3_keyset_records()`；本机点击“加载更早记录”保留 `cursor=412` 并读取下一页。 |
| 21 | 同比/环比 | PASS | 总览展示当前/上一等长周期的 PV、UV、IP、蜘蛛、4xx、5xx 与变化率。 |
| 22 | 错误来源/蜘蛛/AI 与 404 关联 | PASS | 错误页新增 Path、来源域名、蜘蛛、AI crawler、404/错误数关联表。 |
| 23 | 服务端耗时正式分析 | PASS | 性能页展示 DurationMs P50/P75/P95、慢请求数、慢请求下钻与日期趋势；与 RUM 分区保留。 |
| 24 | v2.x 升级兼容 | PASS | T1/T4 在现有历史库执行幂等 migration/回填/汇总，历史表保留；隔离库 parity 未执行且标记 NOT REQUIRED。 |
| 25 | 可选 Beacon | PASS | 本轮实机验证：默认关闭不注入 `rum.js`；启用后同源 endpoint 返回 204；异常路径隔离。 |
| 26 | RUM 指标及与 DurationMs 分离 | PASS | `rum.php`、`rum.js` 与性能页明确分区；实测 LCP/INP/CLS/TTFB/FCP 写入及 NULL 缺失值。 |
| 27 | 屏幕/视口/语言采集和分析 | PASS | 访客环境页新增 Beacon 语言、屏幕、视口聚合，空数据独立提示。 |

## 本轮新增实机证据与修复

- Beacon hook 从仅作用于其它页的 Header hook 改为模板标签阶段写入 `$zbp->header`；开启时首页载入本地 `rum.js`，关闭时脚本数为 0。
- 设置表单修复：未勾选 Beacon 时不再由旧值回填为启用。
- RUM 实测同源 POST 返回 204；完整指标写入，缺失指标保持 SQL `NULL`；测试未输出 IP、UA、Cookie 或 payload 中的敏感内容。
- 在 1366px 后台总览实测侧栏双栏布局与 6 个 ECharts canvas；12 个正式模块均可打开且未见 Fatal/SQL 错误。
- 390px 实测：插件内容区修复后宽度不超过视口、模块导航可展开；Z-Blog 原生顶栏仍保持其自身的最小宽度，但插件主内容不再横向撑出。
- 访问记录实测 Drawer 可打开与关闭；现有输出通过 PHP 转义及 JS `textContent` 写入。
- 本轮新增实测：实时 AJAX 成功状态、保存筛选 CSRF 提交/回显、Keyset 下一游标、页面标题/入口页、AI crawler、错误关联、地域降级、Duration 分位数及 Beacon 语言/屏幕/视口区均可打开且未见 Fatal/SQL Error。

## 已执行回归

- `scripts/local-verify.ps1 -PhpExe D:\\BtSoft\\php\\83\\php.exe -SkipHttp`：PASS（PHP 全量 syntax；本机无 PHPUnit 可执行文件）。
- `node --check assets/v3_admin.js`、`node --check assets/rum.js`：PASS。
- `git diff --check`：PASS。
- T4 历史证据：HTTP 200/404、migration repeat、汇总重建、可信代理、安全、CSV 边界、并发轻量验证均 PASS；对应 CI run `32818757687` 为 success。
- 本轮代码调整后已执行 PHP 全量语法、JS 语法、`git diff --check` 和本机 HTTP 200/404，均 PASS；本机无 PHPUnit 可执行文件。GitHub CI run `32853304584` 通过。

## 发布决定

Functional Product Gate：**PASS**（真实 Chrome/Edge 浏览器内核分别访问首页与文章页，聚合结果为 Chrome/Edge、Windows、桌面设备）。

UI Product Gate：**PASS**（已完成站长可读性与实现术语强制复审；详见 `ui-semantic-audit.md`）。

UI Terminology Gate：**LOCAL PASS / CI PASS**（GitHub CI run `32853304584`）。

Release Gate：**PASS**。版本、文档、验收证据、CI 和发布包边界已完成最终复核；允许进入 PR、合并、Tag、GitHub Release 和正式 ZIP 流程。
