# xz_visit_stats v3.0.0 Codex Master Task

## 任务状态

产品需求已冻结。

唯一正式需求基线：

- `docs/v3.0.0/PRD-v1.0.md`
- GitHub Issue `#18`

2026-08-25 已按冻结规则完成一次 P0 范围修订：可选前端 Beacon、LCP/INP/CLS/TTFB/FCP、屏幕/视口与语言已经从 P1 提升为 v3.0.0 P0。不要再按旧任务把 Beacon 留到后续版本。

不要重新设计产品范围，不要生成新的 PRD 版本。只有遇到 P0 无法安全实现、统计语义冲突或重大兼容阻断时才暂停并明确报告。

## 本机环境

- 插件工作区：`D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats`
- Z-Blog 根目录：`D:\wwwroot\xinzhao_net`
- 本机站点：`http://127.0.0.1`
- PHP：`D:\BtSoft\php\83\php.exe`
- 当前正式基线：`v2.0.1`

## 启动步骤

1. 读取 `AGENTS.md`。
2. 执行 `git status`。
3. `git fetch origin`。
4. 安全同步 `origin/main`，不得覆盖有效未提交工作。
5. 确认 `plugin.xml` 当前版本为 `2.0.1`。
6. 创建并切换分支：`feature/visit-stats-3.0`。
7. 读取：
   - `docs/v3.0.0/PRD-v1.0.md`
   - `docs/v2.0.0/technical-design-v1.0.md`
   - `docs/v2.0.0/performance-validation.md`
   - `docs/TESTING.md`
   - `docs/RELEASE.md`
8. 阅读当前真实实现，至少包括：`main.php`、`include.php`、`inc/admin.php`、`inc/collector.php`、`inc/helpers.php`、`inc/settings.php`、`inc/query_v2.php`、`inc/ip_stats.php`、`inc/source_stats.php`、`inc/spider_stats.php`、`inc/realtime.php`、`inc/seo_report.php`、`inc/rollup.php`、`inc/install.php`、`inc/upgrade/*`。

## 实施原则

- 先做一次真实代码影响分析，但不要停下来等待用户确认普通实现细节。
- 需求已经 FROZEN；不要反复回到产品设计。
- 表字段、函数、类、索引、文件拆分由你根据真实代码决定。
- 保留 v2.0.1 历史数据兼容，不破坏 `xz_visit_stats_log` 原始历史数据。
- 不能可靠回填的新维度明确标记“升级后统计”，不得伪造。
- `VisitorHash` 不是登录用户身份。
- `DurationMs` 始终表示服务端处理耗时。
- 精确跨日 UV/IP 不得直接相加小时/日 UV/IP。
- 服务器端采集继续是 P0 核心；**Beacon 也是 v3.0.0 P0，但必须是管理员可选开关，关闭时不得加载脚本或发送请求。**
- 不实现 P2。
- 不引入运行时外部 CDN 依赖；图表与 Beacon 均优先使用仓库内/浏览器原生能力。
- 普通可逆操作自动继续，不逐步询问。
- 测试失败自动分析、修复、复测。

## v3.0 P0 必须完成

按 `PRD-v1.0.md` 实现全部 P0：

1. 图表化 Dashboard。
2. PV/UV/IP 趋势。
3. 24 小时时段分析。
4. 实时分析面板和访问流。
5. 正式 IP 分析模块。
6. 可信代理/CDN 真实客户端 IP。
7. IP 国家/地区归属及 masked IP 降级。
8. 浏览器分析。
9. 操作系统分析。
10. 设备类型分析。
11. 页面标题/Z-Blog 内容关联。
12. 入口页分析。
13. UTM Campaign。
14. AI 助手来源分类。
15. AI 爬虫独立分类。
16. CSV 导出。
17. 保存常用筛选。
18. 小时级汇总。
19. 高频来源类型/域名等维度物化。
20. Keyset/游标分页。
21. 同比/环比。
22. 404/错误与来源、搜索蜘蛛、AI 爬虫关联。
23. 服务端处理耗时分析。
24. v2.0.1 → v3.0 真实升级兼容。
25. 可选轻量浏览器 Beacon。
26. LCP / INP / CLS / TTFB / FCP 真实用户性能采集与分析。
27. 屏幕分辨率、视口尺寸、浏览器语言采集与分析。

## 推荐开发阶段

### V3-T1｜数据与采集基础

- 可信代理/CIDR/真实 IP。
- 地域能力及隐私降级。
- 来源类型、来源域名、AI 来源、UTM 等高频维度物化。
- 页面标题/Z-Blog 内容关联所需数据。
- AI 爬虫分类。
- 小时级汇总。
- Beacon 数据结构、同源接收端、字段校验、限流/滥用防护。
- 真实用户性能字段：LCP、INP、CLS、TTFB、FCP；允许 NULL/缺失。
- 环境增强字段：screen width/height、viewport width/height、language。
- migration / backfill / state。
- 新安装与 v2.0.1 升级 schema parity。

### V3-T2｜统一查询与性能

- 小时/日/raw 混合查询。
- 同比/环比。
- 24 小时分布。
- IP/环境/Campaign/AI 来源查询。
- Keyset/游标分页。
- CSV 导出查询边界。
- 保存筛选数据模型。
- 保持精确 UV/IP 口径。
- RUM 查询层：按页面、浏览器、系统、设备、语言、屏幕/视口聚合 LCP/INP/CLS/TTFB/FCP。
- LCP/INP/CLS 至少支持 P75；TTFB/FCP 至少支持稳定分位数统计。
- `DurationMs` 与 RUM 数据查询层严格分离。

### V3-T3｜后台 UI

- 图表化总览。
- 实时分析。
- 访问记录增强。
- 页面分析增强。
- 来源/Campaign/AI 来源。
- IP 分析。
- 访客环境。
- 蜘蛛增强。
- 错误增强。
- 性能分析：分为“服务端处理耗时”和“真实用户性能”两个明确区域。
- 访客环境中展示语言、屏幕、视口分析（Beacon 开启且有数据时）。
- 设置与维护增加 Beacon 独立开关和状态说明。
- 菜单按合理分组呈现，不堆满顶部导航。

### V3-T4｜性能与安全验收

- 10 万与 100 万实际数据关键查询。
- 1000 万如资源不足可 EXPLAIN + 风险估算，但必须明确不是实测。
- 重点验证宽范围 DISTINCT、小时汇总、来源物化、Campaign、Keyset 分页、实时查询。
- 可信代理伪造头安全测试。
- CSV 权限/范围/最大行数测试。
- 并发 INSERT + 查询 + 汇总轻量验证。
- Beacon 关闭时零前端请求验证。
- Beacon 开启时对 PerformanceObserver / Performance API 采集、sendBeacon/fetch keepalive 上报、字段校验、异常指标、超大 payload、重复上报和速率限制做测试。
- 验证 Beacon 失败不会影响页面渲染或服务器核心统计。

### V3-T5｜完整回归与发布

只有全部 P0 验收通过后才进入：
- v2.0.1 → v3.0.0 真实升级。
- 新安装。
- schema parity。
- 全模块逐页回归。
- Beacon 开/关两套前台回归。
- 至少在一个支持现代 Performance API 的真实浏览器完成 LCP/INP/CLS/TTFB/FCP、屏幕/视口、语言实测。
- PHP syntax / 自动测试 / HTTP / DB / 日志 / 敏感信息扫描。
- GitHub CI。
- PR → main。
- Release Dry Run。
- `v3.0.0` Tag / GitHub Release / 正式 ZIP。

## 可信代理安全要求

必须以 `REMOTE_ADDR` 为信任根。只有当 `REMOTE_ADDR` 命中管理员配置的可信代理 IP/CIDR 时，才允许读取 `CF-Connecting-IP`、`X-Forwarded-For`、`X-Real-IP` 或自定义 Header。

测试必须覆盖无代理、可信代理+合法 Header、不可信 REMOTE_ADDR+伪造 X-Forwarded-For、多级 X-Forwarded-For、IPv4/IPv6/CIDR、masked IP。

## 实时定义

“当前活跃访客”采用短时间窗口统计，例如最近 5 分钟去重 VisitorHash/IP，不得描述为真实 TCP/WebSocket 在线连接数。实现可使用 15–30 秒轻量 AJAX polling；v3.0.0 不要求 WebSocket。

## 地域能力

优先选择适合离线/本地或可配置的数据源方案，避免强依赖外部 API 导致前台请求阻塞。没有地域库时插件其他功能必须正常工作。

## Beacon / 真实用户性能硬要求

- Beacon 是 P0 功能，但管理员必须可以单独开启/关闭；兼容安全基线默认关闭。
- 关闭时不得输出 Beacon JS，不得产生 Beacon 网络请求，服务器端统计照常工作。
- 开启时优先使用浏览器原生 PerformanceObserver / Performance API。
- 上报优先使用同源 `navigator.sendBeacon()`；不可用时允许同源 `fetch(..., {keepalive:true})` 降级。
- 不依赖第三方统计服务、外部 CDN 或远程 JS。
- 采集：LCP、INP、CLS、TTFB、FCP、screen width/height、viewport width/height、navigator.language（或等价标准语言字段）。
- 不采集 DOM 文本、正文、表单值、Cookie、LocalStorage、键盘输入、剪贴板或其他页面敏感内容。
- 屏幕/视口/语言不得用于生成新的持久浏览器指纹。
- RUM 与服务器 `DurationMs` 必须字段和语义分离。
- 浏览器不支持的性能指标保持 NULL/缺失，不允许补 0。
- LCP/INP/CLS 至少用 P75 做后台聚合；CLS 不使用毫秒单位。
- Beacon 接口必须限制方法、来源、字段长度、数值范围、payload 大小和速率；失败要静默隔离，不影响页面响应。
- Beacon 数据是“升级到 3.0 且开启后统计”，不得回填伪造 v2.x 历史数据。

## 图表要求

- 图表只是展示层，数据必须来自统一查询层。
- 支持空数据状态和今天/昨天/7d/30d/自定义范围。
- 可点击的图表/排行应下钻到相应模块或访问记录。
- 不依赖公网 CDN 才能打开后台。
- 保持 Z-Blog 后台整体风格，不做无关视觉重构。

## CSV 导出

- 仅 root/授权后台用户可执行。
- 复用当前筛选条件。
- 必须限制日期范围和最大导出行数。
- 防止 CSV Formula Injection：以 `=`, `+`, `-`, `@` 等开头的用户数据必须安全处理。
- 不导出不应暴露的凭据或内部配置。

## 历史兼容

升级前后至少核对原始日志总数、最早/最近时间、PV/UV/IP、4xx/5xx/404、蜘蛛历史、PathKey、日汇总、新小时汇总、旧设置。

能从历史 `Url/Path/Referer/IP/UA` 安全派生的维度可以分批回填；Beacon/RUM/屏幕/语言不能从旧数据可靠得到，不得假填。

## Git 与 CI

- 使用 `feature/visit-stats-3.0`。
- 在有意义的阶段提交，不要为每个小改动创建 Commit。
- 本机快速验证优先；GitHub CI 在关键阶段/最终集成时执行，避免机械消耗额度。
- CI 失败自动读取日志、修复并重新 Push。
- 不提交 benchmark 临时数据库、真实凭据、IP 原始样本、Cookie、Token。

## 发布门禁

正式 v3.0.0 Release 前必须满足：P0 全部完成、v2.0.1 真实升级 PASS、新安装 PASS、Local Runtime PASS、GitHub CI PASS、Release Dry Run PASS、Tag/Release/ZIP 版本一致。

若尚处于中间阶段：`Release Gate = NOT READY`，不得为了流程完整提前发布。

## 最终输出格式

每个有意义阶段结束后简短报告当前阶段、Commit SHA、主要完成内容、本机验证、CI（如执行）、剩余 P0、是否自动进入下一阶段。

最终发布时输出：

```text
FULL DEVELOPMENT FLOW GATE

[1] Notion Context       PASS / BLOCKED
[2] Codex Development    PASS / BLOCKED
[3] Local Runtime        PASS / BLOCKED
[4] GitHub CI            PASS / BLOCKED
[5] Release Gate         PASS / BLOCKED
[6] Notion Writeback     PASS / BLOCKED

FINAL: COMPLETE / INCOMPLETE
RELEASE: RELEASED / NOT RELEASED
```

如果本机 Codex 没有 Notion 能力，不要因此停止开发；由 ChatGPT Controller 完成 Notion Context / Writeback。
