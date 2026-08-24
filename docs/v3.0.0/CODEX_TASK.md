# xz_visit_stats v3.0.0 Codex Master Task

## 任务状态

产品需求已冻结。

唯一正式需求基线：

- `docs/v3.0.0/PRD-v1.0.md`
- `docs/v3.0.0/UI-SPEC.md`
- GitHub Issue `#18`

`UI-SPEC.md` 是 v3.0.0 后台 UI 的冻结实施基线。必须采用：Z-Blog 原生后台 Shell / PHP SSR + `xzvs-` Scoped CSS Design System + Alpine.js 3.x + Apache ECharts 6.x + Fetch/AJAX；所有前端运行时资源随插件本地打包，不依赖公网 CDN。不要改造成 React/Vue SPA，不整套引入 Bootstrap/Tabler/Element Plus/Ant Design 覆盖 Z-Blog 后台。

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
   - `docs/v3.0.0/UI-SPEC.md`
   - `docs/v2.0.0/technical-design-v1.0.md`
   - `docs/v2.0.0/performance-validation.md`
   - `docs/TESTING.md`
   - `docs/RELEASE.md`
8. 阅读当前真实实现，至少包括：
   - `main.php`
   - `include.php`
   - `inc/admin.php`
   - `inc/collector.php`
   - `inc/helpers.php`
   - `inc/settings.php`
   - `inc/query_v2.php`
   - `inc/ip_stats.php`
   - `inc/source_stats.php`
   - `inc/spider_stats.php`
   - `inc/realtime.php`
   - `inc/seo_report.php`
   - `inc/rollup.php`
   - `inc/install.php`
   - `inc/upgrade/*`

## 实施原则

- 先做一次真实代码影响分析，但不要停下来等待用户确认普通实现细节。
- 需求已经 FROZEN；不要反复回到产品设计。
- 表字段、函数、类、索引、文件拆分由你根据真实代码决定。
- 保留 v2.0.1 历史数据兼容。
- 不破坏 `xz_visit_stats_log` 原始历史数据。
- 不能可靠回填的新维度明确标记“升级后统计”，不得伪造。
- `VisitorHash` 不是登录用户身份。
- `DurationMs` 始终表示服务端处理耗时。
- 精确跨日 UV/IP 不得直接相加小时/日 UV/IP。
- 服务器端采集继续是 P0 核心；P0 Beacon 仍必须可选，关闭时基础统计完整可用。
- 不实现 P2。
- 不引入运行时外部 CDN 依赖；图表使用仓库内本地打包的 ECharts 6.x。
- 后台轻量交互使用本地打包 Alpine.js 3.x，不引入 React/Vue SPA。
- 普通可逆操作自动继续，不逐步询问。
- 测试失败自动分析、修复、复测。

## v3.0 P0 必须完成

按 `PRD-v1.0.md` 实现全部 P0，至少包括：

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
25. 可选轻量浏览器 Beacon，后台可启停，关闭时不加载/不上报。
26. 真实用户性能 LCP / INP / CLS / TTFB / FCP，与 `DurationMs` 严格分离。
27. 屏幕分辨率、视口尺寸、浏览器语言采集和分析，不用于生成新的持久浏览器指纹。

## 推荐开发阶段

为了减少返工，按以下顺序实施，但不要每阶段都暂停等待用户：

### V3-T1｜数据与采集基础

- 可信代理/CIDR/真实 IP。
- 地域能力及隐私降级。
- 来源类型、来源域名、AI 来源、UTM 等高频维度物化。
- 页面标题/Z-Blog 内容关联所需数据。
- AI 爬虫分类。
- 小时级汇总。
- Beacon/RUM 数据模型、同源上报端点、采集开关及 migration。
- LCP/INP/CLS/TTFB/FCP、屏幕/视口、语言字段与聚合基础。
- migration / backfill / state。
- 新安装与 v2.0.1 升级 schema parity。

### V3-T2｜统一查询与性能

- 小时/日/raw 混合查询。
- 同比/环比。
- 24 小时分布。
- IP/环境/Campaign/AI 来源查询。
- RUM/Core Web Vitals 分位数查询与页面/环境维度聚合。
- Keyset/游标分页。
- CSV 导出查询边界。
- 保存筛选数据模型。
- 保持精确 UV/IP 口径。

### V3-T3｜后台 UI

严格执行 `docs/v3.0.0/UI-SPEC.md`：

- Z-Blog 原生后台 Shell / PHP SSR。
- `xzvs-` Scoped CSS Design System。
- Alpine.js 3.x 轻量交互。
- Apache ECharts 6.x 图表，本地打包。
- 插件内部左侧分组导航，小屏折叠；不把 11 个模块继续堆在顶部。
- Dashboard KPI + 图表 + 下钻。
- 实时分析轮询。
- 访问记录右侧 Drawer 详情。
- 性能页将服务器 `DurationMs` 与 RUM/Core Web Vitals 明确分区。
- 全部模块响应式、空数据、加载、失败状态。
- 图表化总览。
- 实时分析。
- 访问记录增强。
- 页面分析增强。
- 来源/Campaign/AI 来源。
- IP 分析。
- 访客环境。
- 蜘蛛增强。
- 错误增强。
- 性能分析。
- 设置与维护。

### V3-T4｜性能与安全验收

- 10 万与 100 万实际数据关键查询。
- 1000 万如资源不足可 EXPLAIN + 风险估算，但必须明确不是实测。
- 重点验证宽范围 DISTINCT、小时汇总、来源物化、Campaign、Keyset 分页、实时查询、RUM 聚合。
- 可信代理伪造头安全测试。
- CSV 权限/范围/最大行数测试。
- Beacon 同源、字段、payload、频率、异常隔离测试。
- UI 无公网 CDN、CSS 污染、Tooltip/XSS、轮询、响应式验收。
- 并发 INSERT + 查询 + 汇总轻量验证。

### V3-T5｜完整回归与发布

只有全部 P0 验收通过后才进入：
- v2.0.1 → v3.0.0 真实升级。
- 新安装。
- schema parity。
- 全模块逐页回归。
- Beacon 关闭/开启两套前台 smoke。
- UI 11 模块导航、图表、Drawer、轮询和 RUM 页面回归。
- PHP syntax / 自动测试 / HTTP / DB / 日志 / 敏感信息扫描。
- GitHub CI。
- PR → main。
- Release Dry Run。
- `v3.0.0` Tag / GitHub Release / 正式 ZIP。

## 可信代理安全要求

必须以 `REMOTE_ADDR` 为信任根。

只有当 `REMOTE_ADDR` 命中管理员配置的可信代理 IP/CIDR 时，才允许读取：

- `CF-Connecting-IP`
- `X-Forwarded-For`
- `X-Real-IP`
- 自定义 Header

测试必须覆盖：

- 无代理。
- 可信代理 + 合法 Header。
- 不可信 REMOTE_ADDR + 伪造 X-Forwarded-For。
- 多级 X-Forwarded-For。
- IPv4 / IPv6 / CIDR。
- masked IP 模式。

不得因为支持 CDN 而允许任意客户端伪造 IP。

## 实时定义

“当前活跃访客”采用短时间窗口统计，例如最近 5 分钟的去重 VisitorHash/IP，不得描述为真实 TCP/WebSocket 在线连接数。

实现可使用 15–30 秒轻量 AJAX polling；v3.0.0 不要求 WebSocket。页面隐藏时应降低或暂停轮询。

## 地域能力

优先选择适合离线/本地或可配置的数据源方案，避免强依赖外部 API 导致前台请求阻塞。

- full IP：允许做国家/地区推导。
- masked IP：只能输出数据源实际能够可靠推导的粗粒度地域。
- 旧历史脱敏 IP 不得声称可恢复精确地域。

如果地域数据库需要用户额外安装或更新，必须提供明确降级：没有地域库时插件其他功能正常工作。

## 图表与 UI 要求

以 `docs/v3.0.0/UI-SPEC.md` 为唯一 UI 实施规范。

核心要求：

- 图表数据必须来自统一 PHP 查询层，不在浏览器重算统计口径。
- 支持空数据状态。
- 支持今天/昨天/7d/30d/自定义范围。
- 可点击图表/排行应下钻到相应模块或访问记录。
- Alpine.js 仅管理轻量 UI 状态，不承载业务数据真源。
- ECharts 6.x 与 Alpine.js 3.x 均本地打包，不依赖公网 CDN。
- 所有插件 UI CSS 使用 `xzvs-` 命名空间并限制在插件容器内。
- 11 个模块使用插件内部侧栏分组导航，小屏折叠，避免顶部 Tab 溢出。
- 访问记录详情优先右侧 Drawer。
- 服务端性能与 RUM 性能使用不同区域和标签。
- 保持 Z-Blog 后台整体风格，不做无关全局视觉重构。

## Beacon / RUM 要求

- Beacon 对管理员可选，默认关闭；关闭时不加载脚本、不上报。
- 优先原生 PerformanceObserver / Performance API。
- 同源 `navigator.sendBeacon()` 上报，必要时 `fetch(...,{keepalive:true})` 降级。
- 不采集 DOM 文本、表单值、Cookie、LocalStorage、键盘输入。
- LCP/INP/CLS 至少提供 P75；指标不可用允许缺失，不得补零。
- 屏幕/视口/语言只用于统计聚合，不用于新的浏览器指纹。
- 失败不得影响前台页面。

## CSV 导出

- 仅 root/授权后台用户可执行。
- 复用当前筛选条件。
- 必须限制日期范围和最大导出行数。
- 防止 CSV Formula Injection：以 `=`, `+`, `-`, `@` 等开头的用户数据必须安全处理。
- 不导出不应暴露的凭据或内部配置。

## 历史兼容

升级前后至少核对：

- 原始日志总数。
- 最早/最近时间。
- PV/UV/IP 核心口径。
- 4xx/5xx/404。
- 蜘蛛历史。
- PathKey。
- 日汇总。
- 新小时汇总。
- 旧设置。

能从历史 `Url/Path/Referer/IP/UA` 安全派生的维度可以分批回填；不能可靠派生的维度不得假填。Beacon/RUM、屏幕/视口和语言从 v3.0 启用后开始统计，不回填 v2.x 历史。

## Git 与 CI

- 使用 `feature/visit-stats-3.0`。
- 在有意义的阶段提交，不要为每个小改动创建 Commit。
- 本机快速验证优先；GitHub CI 在关键阶段/最终集成时执行，避免机械消耗额度。
- CI 失败自动读取日志、修复并重新 Push。
- 不提交 benchmark 临时数据库、真实凭据、IP 原始样本、Cookie、Token。

## 发布门禁

正式 v3.0.0 Release 前必须满足：

- P0 全部完成。
- v2.0.1 真实升级 PASS。
- 新安装 PASS。
- Local Runtime PASS。
- GitHub CI PASS。
- UI-SPEC 验收 PASS。
- Beacon/RUM 验收 PASS。
- Release Dry Run PASS。
- Tag、Release、ZIP 版本一致。

若尚处于中间阶段：

`Release Gate = NOT READY`，不得为了流程完整提前发布。

## 最终输出格式

每个有意义阶段结束后简短报告：

- 当前阶段。
- Commit SHA。
- 主要完成内容。
- 本机验证结果。
- CI 结果（如本阶段执行）。
- 剩余 P0。
- 是否自动进入下一阶段。

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
