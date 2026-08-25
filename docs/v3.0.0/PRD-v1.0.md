# xz_visit_stats v3.0.0 正式 PRD v1.0

状态：**FROZEN｜2026-08-25 重新立项版**

> 本 PRD 以正式版本 `v2.0.1` 为唯一代码/产品基线重新建立。上一轮 v3.0.0 / v3.0.1 的开发、PASS、Release、补丁收口结论全部不继承；旧实现只能作为参考，必须重新按本 PRD 验收。
>
> 本 PRD 已完成快速范围检查：产品定位明确、P0 边界明确、统计语义未与 v2.0.1 冲突、数据库操作约束明确、UI 运行时验收被纳入发布门禁。普通开发过程中不再生成 v1.1/v1.2；只有 P0 增删、统计语义变化、用户可见口径变化或重大兼容性阻断时才允许修订。

## 1. 产品定位

**Z-Blog 完整访问统计与分析中心。**

v3.0 的目标是在 v2.0.1“站长访问分析中心”的基础上，补齐实时、IP、地域、访客环境、Campaign、AI 来源、图表化 Dashboard、性能分析等明显缺失能力，同时保持服务器端采集为核心，不把插件扩张成完整营销平台、WAF 或重型用户行为分析平台。

## 2. 版本目标

v3.0 必须让站长在一个后台内回答：

1. 现在和过去一段时间有多少真人访问、UV、独立 IP、蜘蛛和错误请求；
2. 访客从哪里来，包括搜索、外链、社交、UTM、AI 助手；
3. 访客看了什么页面、哪些页面更热、哪些页面更慢或更容易报错；
4. 哪些 IP、设备、浏览器、系统、地域构成当前访问；
5. 搜索蜘蛛和 AI 爬虫抓了什么、是否出现 404/5xx；
6. 服务器处理性能与真实浏览器体验性能分别如何；
7. 能否从聚合指标继续下钻到真实访问记录。

## 3. 基线与兼容原则

- 正式基线：`v2.0.1`。
- v2.x 原始访问事实数据继续作为历史核心来源，不清空历史日志。
- `VisitorHash` 仍是匿名统计标识，不是登录用户身份。
- `DurationMs` 仍只表示服务器/PHP处理耗时，不表示页面加载时间或停留时间。
- 历史无法可靠推导的新字段不得伪造回填；必须标记为“升级后统计”或允许为空。
- masked IP 不得恢复或伪造原始 IP、精确地域。
- 精确跨日 UV/IP 不得直接相加小时/日 UV/IP。
- 所有迁移、汇总、回填必须幂等、可恢复、可重复执行。

## 4. 数据库操作约束

用户明确要求本轮开发**不要进行额外数据库管理操作**。因此：

- 禁止为了测试执行 `CREATE DATABASE` / `DROP DATABASE`；
- 禁止要求用户额外创建测试库、授权数据库账号；
- 禁止手工删除真实表、字段、索引或历史日志；
- 禁止清空真实访问数据；
- 允许插件正常启用/升级流程自身执行**非破坏性、幂等的 schema migration**，前提是保留历史数据并可重复执行；
- 本机真实站点验证以现有 Z-Blog 数据库为运行环境，不进行破坏性数据库管理动作；
- 若某项验收必须依赖独立数据库管理权限，必须标记为 `NOT REQUIRED（用户约束）` 或单独列为后续专项，不得以此阻塞本轮普通开发，也不得伪造 PASS。

## 5. 后台信息架构

插件仍只有一个 Z-Blog 后台入口“访问统计/访问分析”，插件内部提供 11 个模块：

- 总览
- 实时分析
- 访问记录
- 页面分析
- 来源分析
- IP 分析
- 访客环境
- 蜘蛛分析
- 错误分析
- 性能分析
- 设置与维护

桌面端 1280px+ 使用插件内部左侧分组导航，小屏折叠；保留 Z-Blog 原生后台 Shell，不做独立 SPA。

## 6. P0｜v3.0 首发必须完成

1. 新版图表化 Dashboard。
2. PV / UV / IP 趋势图。
3. 24 小时时段分析。
4. 实时分析面板与实时访问流。
5. IP 分析正式恢复并重构。
6. CDN / 反向代理可信代理与真实客户端 IP。
7. IP 国家/地区归属能力及 masked IP 隐私降级。
8. 浏览器分析。
9. 操作系统分析。
10. 设备类型分析。
11. 页面标题与 Z-Blog 内容关联。
12. 入口页面分析。
13. UTM Campaign：`utm_source`、`utm_medium`、`utm_campaign`、`utm_content`、`utm_term`。
14. AI 助手来源识别，至少覆盖 ChatGPT、Claude、Gemini、Perplexity、Copilot、Grok、DeepSeek，并保留可扩展映射。
15. AI 爬虫独立分类。
16. CSV 导出。
17. 保存常用筛选条件。
18. 小时级汇总。
19. 来源类型、来源域名等高频维度物化，避免查询时反复解析 Referer。
20. 访问记录 Keyset / 游标分页，降低深 OFFSET 风险。
21. 同比 / 环比。
22. 404 / 4xx / 5xx 与来源、蜘蛛、AI 爬虫关联增强。
23. 服务端处理耗时独立分析视图。
24. v2.0.1 → v3.0 历史数据与升级兼容逻辑。
25. 可选轻量浏览器 Beacon；管理员独立启停，关闭时不加载脚本、不产生 Beacon 请求。
26. RUM：LCP / INP / CLS / TTFB / FCP；与 `DurationMs` 严格分离，LCP/INP/CLS 至少提供 P75。
27. 屏幕分辨率、viewport 与浏览器语言分析；仅用于统计聚合，不用于构造新的持久浏览器指纹。

缺失任何一项未被明确豁免的 P0，均不得发布正式 v3.0.0。

## 7. 核心模块要求

### 总览

第一屏以 KPI + 图表为主，不得继续以旧式大表格主导视觉层级。至少展示真人 PV、UV、独立 IP、蜘蛛访问、4xx、5xx、平均服务端耗时，以及 PV/UV/IP 趋势、24 小时分布、来源、环境、热门页面/IP、蜘蛛、错误摘要。Beacon 有数据时显示 RUM 摘要。

### 实时分析

展示最近短时间窗口的活跃访客、5/30 分钟 PV/UV、实时访问流、热门页面、来源、地域、设备、蜘蛛和错误。采用 15–30 秒轻量 AJAX 轮询；页面不可见时暂停或降低频率；不要求 WebSocket。

### 访问记录

保留组合筛选并增强来源、Campaign、地域、设备、页面关联。支持 CSV 导出和保存筛选。详情优先使用右侧 Drawer。大数据深分页优先 Keyset/游标方案。

### 页面分析

以规范化 Path 为主维度，展示 PV/UV/IP、蜘蛛、错误、服务端耗时、来源、地域、设备、入口页和最近访问；可解析时关联 Z-Blog 文章/页面标题与内容 ID。

### 来源分析

固定支持直接访问、搜索引擎、AI 助手、社交媒体、外部网站、站内来源、广告/活动、其他。支持来源域名、Referer、着陆页、搜索引擎、UTM Campaign、AI 来源和下钻。搜索关键词仅展示 Referer 实际可解析内容。

### IP 分析

展示访问排行、页面数、首次/最后访问、状态码、404、频率、平均耗时、最近访问和异常提示。异常仅用于分析提示，不自动封禁。IP 地域至少支持国家/地区能力；masked IP 模式必须降级。

### 访客环境

浏览器、操作系统、设备类型为基础；Beacon 开启时增加浏览器语言、屏幕分辨率和 viewport 聚合。

### 蜘蛛分析

保持搜索引擎蜘蛛统计，并将 AI crawler 独立分类。展示排行、趋势、抓取页面、状态码、错误和耗时。DNS/IP 段真实性验证不作为 P0。

### 错误分析

提供 404/4xx/5xx 趋势、状态分布、高频错误 Path、来源、蜘蛛/AI 爬虫关联和最近记录；“疑似死链”不得描述成完整站点死链爬虫扫描。

### 性能分析

必须明确分成：

- 服务器性能：`DurationMs`、趋势、慢页面、慢请求、可行时 P50/P75/P95；
- 真实用户性能：LCP、INP、CLS、TTFB、FCP。

LCP/INP/CLS 至少提供 P75；不支持的浏览器允许缺失，不得补 0。

### 设置与维护

包括采集启停、Referer、UA、蜘蛛、IP 完整/脱敏、可信代理、保留期、自动清理、汇总状态、Beacon 开关、数据健康和安全维护。所有写操作必须 CSRF 校验。

## 8. 真实 IP 信任模型

- `REMOTE_ADDR` 是信任根；
- 只有 `REMOTE_ADDR` 命中配置的可信代理 IP/CIDR 时，才接受 `CF-Connecting-IP`、`X-Forwarded-For`、`X-Real-IP` 或自定义 Header；
- 不得无条件信任任何转发头；
- Header 内容必须校验格式、链顺序和长度；
- 未配置可信代理时保持 v2.0.1 安全行为。

## 9. Beacon / RUM

Beacon 是 P0，但默认关闭。

启用后：

- 使用浏览器原生 `PerformanceObserver` / Performance API；
- 优先同源 `navigator.sendBeacon()`；必要时 `fetch(..., {keepalive:true})` 降级；
- 不采集 DOM 文本、表单值、Cookie、LocalStorage、键盘输入；
- Endpoint 必须限制同源、方法、字段、字段长度、数值范围、payload 大小和请求频率；
- Beacon 失败不得影响前台页面；
- RUM 与服务器 `DurationMs` 使用独立字段、独立查询、独立图表语义。

## 10. UI 实现基线

UI 技术路线保持：

- Z-Blog 原生后台 Shell / PHP SSR；
- `xzvs-` Scoped CSS；
- Alpine.js 3.x 负责轻量交互；
- Apache ECharts 6.x 负责图表；
- Fetch/AJAX 负责实时轮询和局部数据；
- 所有运行时资源随插件本地打包，不依赖公网 CDN；
- 不采用 React/Vue SPA，不整套引入 Bootstrap/Tabler/Element Plus/Ant Design 覆盖 Z-Blog 后台。

`UI-SPEC.md` 必须在进入 V3-T3 前重新快速核对并确认与本 PRD 一致；UI 不能仅凭文件存在判 PASS，必须真实打开本机 Z-Blog 后台进行视觉与交互验收。

## 11. 性能要求

- 小时/日汇总用于高频趋势；
- 高频来源/环境维度优先物化；
- 深分页使用 Keyset/游标；
- 7/30 天常用趋势不得依赖无界全表扫描；
- 10 万、100 万规模关键查询必须有真实或可重复的性能证据；
- 1000 万未实测时必须明确标记估算，不承诺毫秒级；
- 性能优化不得改变冻结统计口径。

## 12. 安全与隐私

- 后台仅管理员可访问；
- 写操作 CSRF 防护；
- 查询参数严格验证；
- URL/Referer/UA/页面标题输出 HTML 转义；
- SQL 不拼接未经白名单/安全处理的输入；
- CSV 防公式注入；
- 不把数据库凭据、Cookie、敏感配置写入日志、Git、Issue、PR 或 Release；
- 插件异常不得中断前台请求。

## 13. P1｜3.x 后续增强

- 自定义事件、目标/转化；
- 外链点击、下载、表单提交；
- 站内搜索词；
- JSON/API；
- ASN / ISP / 城市级地域；
- 蜘蛛 DNS / 官方 IP 段真实性验证；
- 诊断中心；
- 自定义 Dashboard / 数据标注。

## 14. P2｜暂不纳入 3.0

- Session Replay / 用户行为 Heatmap；
- 完整 Session / Bounce / Dwell；
- Journey / Funnel；
- WebSocket；
- 自动 IP 封禁 / 安全中心；
- 第三方威胁情报；
- 完整 SEO 排名系统；
- 自动邮件/短信/Slack 报告。

## 15. 开发阶段

### V3-T1｜数据与采集基础

真实 IP、来源/UTM/AI、页面/环境物化、小时汇总、RUM/Beacon 数据结构、迁移与采集安全。

### V3-T2｜统一查询与性能

统一统计口径、趋势/时段/实时/IP/来源/页面/环境/错误/性能查询、Keyset、汇总与性能优化。

### V3-T3｜后台 11 模块与 UI

完成 11 个模块、Dashboard、ECharts、Alpine、Drawer、响应式、实时轮询和空/加载/失败状态。

### V3-T4｜运行、安全、性能、兼容验收

PHP/JS、HTTP、本机真实 Z-Blog、可信代理、Beacon、CSV、权限、安全、性能、迁移幂等和残留 schema 兼容。遵守“不额外进行数据库管理操作”的用户约束。

### V3-T5｜最终发布验收

对 27 项 P0 建立 `IMPLEMENTED / PARTIAL / MISSING / BLOCKED` 矩阵；所有未豁免 P0 必须真实 PASS，然后才允许 PR → main → main CI → Release Dry Run → Tag → GitHub Release → 正式 ZIP → SHA256 → Notion 回写。

## 16. 发布硬门禁

```text
[1] Notion Context       PASS / BLOCKED
[2] Codex Development    PASS / BLOCKED
[3] UI Runtime           PASS / NOT REQUIRED / BLOCKED
[4] Local Runtime        PASS / NOT REQUIRED / BLOCKED
[5] GitHub CI            PASS / NOT REQUIRED / BLOCKED
[6] Release Gate         PASS / NOT READY / BLOCKED
[7] Notion Writeback     PASS / BLOCKED

FINAL: COMPLETE / INCOMPLETE
RELEASE: RELEASED / NOT RELEASED
```

规则：

- 任一必须 Gate = `BLOCKED` → `FINAL: INCOMPLETE`；
- `Release Gate` 未 PASS 时禁止创建 Tag、GitHub Release、正式 ZIP；
- UI Runtime 必须是真实页面验收，不得以静态文件存在代替；
- 用户明确不要求的额外数据库管理测试可以标 `NOT REQUIRED（用户约束）`，但不得虚构 PASS；
- 中间 T1–T4 正常状态为 `Release Gate = NOT READY`；
- 只有 T5 全部通过才重新发布正式 `v3.0.0`。

## 17. 快速检查结论

- 产品定位：PASS
- v2.0.1 基线：PASS
- P0 范围：27 项，明确
- 统计语义：与 v2.0.1 基础口径兼容
- UI 目标：明确，并新增真实运行时验收硬门禁
- 数据库约束：已明确“禁止额外数据库管理操作；允许插件自身非破坏性幂等迁移”
- 安全/隐私：明确
- 性能边界：明确
- 发布条件：明确，BLOCKED 时禁止 Release

**PRD 状态：FROZEN。下一步：重新核对并冻结技术设计/UI-SPEC，然后由 Codex 从 v2.0.1 基线重新执行 V3-T1。**