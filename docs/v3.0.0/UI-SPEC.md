# xz_visit_stats v3.0.0 UI Specification

状态：**FROZEN IMPLEMENTATION BASELINE**

本文件定义 v3.0.0 后台 UI 的正式实现技术栈与交互基线。它不改变 `PRD-v1.0.md` 的 27 项 P0 产品范围，只约束实现方式，避免开发阶段自行切换到重型 SPA 或与 Z-Blog 后台冲突的整套 UI 框架。

## 1. UI 技术栈

正式采用：

- **Z-Blog 原生后台 Shell / PHP SSR**：继续使用 Z-Blog 后台头部、权限与页面生命周期。
- **xz_visit_stats Scoped CSS Design System**：插件自有组件样式全部使用 `xzvs-` 命名空间，避免污染 Z-Blog 全局样式。
- **Alpine.js 3.x**：负责轻量交互，例如筛选展开、Drawer、Tab、Dropdown、实时轮询状态与局部 UI 状态。
- **Apache ECharts 6.x**：负责 Dashboard、趋势、时段、来源、环境、蜘蛛、错误、RUM/Core Web Vitals 等图表。
- **Fetch / AJAX**：用于实时分析 15–30 秒轮询、局部查询和异步明细加载。
- **原生 JavaScript Beacon**：PerformanceObserver / Performance API + `navigator.sendBeacon()`，必要时 `fetch(..., {keepalive:true})` 降级。
- **本地 SVG 图标**：不依赖外部图标 CDN。

所有前端运行时资源必须随插件本地打包，**后台打开不得依赖公网 CDN**。

## 2. 明确不采用

v3.0.0 不采用以下方案作为主 UI 架构：

- React SPA
- Vue SPA
- Next/Nuxt
- 整套 Element Plus
- 整套 Ant Design
- 整套 Bootstrap / Tabler 覆盖 Z-Blog 后台

可以参考 Tabler 等 Admin UI 的视觉语言，但不整包引入，避免 CSS 冲突、构建链膨胀和维护复杂度上升。

## 3. CSS 命名空间

新增样式优先使用以下命名方式：

```text
.xzvs-app
.xzvs-shell
.xzvs-sidebar
.xzvs-nav
.xzvs-card
.xzvs-kpi
.xzvs-chart
.xzvs-table
.xzvs-filter
.xzvs-badge
.xzvs-drawer
.xzvs-modal
.xzvs-empty
.xzvs-skeleton
.xzvs-toolbar
```

不得新增会广泛覆盖 Z-Blog 全局元素的裸选择器，例如 `table {}`, `button {}`, `.button {}` 等，除非限定在 `.xzvs-app` 内。

## 4. 后台导航结构

11 个能力模块不得继续全部堆在顶部横向 Tab 中。大屏优先使用插件内部左侧分组导航，小屏折叠为单一模块选择器。

建议信息架构：

```text
概览
  总览
  实时分析

流量分析
  访问记录
  页面分析
  来源分析

访客分析
  IP分析
  访客环境

技术分析
  蜘蛛分析
  错误分析
  性能分析

系统
  设置与维护
```

要求：

- 保留 Z-Blog 顶部原生后台结构。
- 插件内部导航只管理 xz_visit_stats 页面。
- 当前模块有清晰选中态。
- 1280px 及以上优先侧栏布局；窄屏自动折叠。
- 不因模块数量增加造成横向溢出。

## 5. Dashboard

第一屏优先展示 KPI，不直接堆大表格。

核心 KPI：

- 真人 PV
- 真人 UV
- 独立 IP
- 蜘蛛访问
- 4xx
- 5xx
- 服务端平均/分位耗时
- Beacon 开启且有数据时增加 LCP / INP / CLS 摘要

主要图表：

- PV / UV / IP 折线图
- 真人 / 蜘蛛趋势
- 24 小时访问柱状图
- 来源类型环形/条形图
- 浏览器 / OS / 设备分布
- 热门页面
- 热门 IP
- 搜索引擎来源
- AI 来源
- 蜘蛛排行
- 错误摘要

图表和排行在语义明确时必须可下钻。

## 6. 实时分析

实时页面至少展示：

- 当前活跃访客（短窗口定义）
- 最近 5 分钟 PV / UV
- 最近 30 分钟 PV / UV
- 实时访问流
- 当前热门页面
- 实时来源
- 实时地域
- 实时设备
- 实时蜘蛛
- 实时 404 / 5xx

更新机制：

- 默认使用 15–30 秒轻量轮询。
- 页面不可见时应降低或暂停轮询，减少后台资源浪费。
- 不要求 WebSocket。
- 请求失败采用非阻塞错误状态，不打断整个后台页面。

## 7. 访问记录

筛选区保留服务端语义，前端只负责交互体验。

标准操作：

- 查询
- 清除/重置筛选
- 保存筛选
- CSV 导出

列表建议字段：

- 时间
- IP / 地域
- 真人 / 蜘蛛 / AI crawler 类型
- 页面
- 来源
- 设备
- 状态码
- 服务端耗时
- 详情

单条详情优先使用右侧 Drawer，而不是频繁跳转新页面。Drawer 中按组展示：

- 请求信息
- 访客 / IP / 地域
- 来源 / Referer / Campaign
- 浏览器 / OS / 设备
- 页面 / Z-Blog 内容关联
- 状态码与服务端耗时
- 能可靠关联时的 Beacon 性能摘要

## 8. 性能分析

必须分成两个明确区域，禁止把服务器耗时和浏览器真实体验混为一谈。

### 服务器性能

- `DurationMs`
- P50 / P75 / P95（数据量允许时）
- 慢页面
- 慢请求
- 趋势

### 真实用户体验 RUM

Beacon 开启后展示：

- LCP
- INP
- CLS
- TTFB
- FCP

规则：

- LCP / INP / CLS 至少提供 P75。
- 不支持的浏览器允许缺失，不补 0。
- 按页面、浏览器、OS、设备、语言、屏幕/视口维度下钻。
- RUM 数据与 `DurationMs` 使用不同视觉分区与标签。

## 9. ECharts 使用边界

优先使用少量稳定图表类型：

- Line：PV / UV / IP、时间趋势、性能趋势
- Bar：24 小时访问、分类排行
- Stacked Bar：真人 / 蜘蛛等可加总维度
- Donut/Pie：来源/设备等少量占比
- Horizontal Bar：热门页面、IP、蜘蛛、来源域名
- Heatmap：仅用于“星期 × 小时”访问热度等统计热图，不代表用户行为 Heatmap

要求：

- 图表数据由统一 PHP 查询层输出，不在浏览器重复计算统计口径。
- 所有图表有空数据状态。
- 所有时间图表使用站点时区。
- Tooltip、Legend、Axis 数值和单位统一。
- 可点击图表必须明确 cursor/hover 状态和下钻目标。

## 10. Alpine.js 使用边界

Alpine.js 只负责轻量 UI 状态，不承载业务数据真源。

允许：

- 展开/收起高级筛选
- Drawer / Modal
- Dropdown
- 简单 Tab
- 轮询状态
- Loading / Empty / Error 状态
- 局部 AJAX 请求后的展示状态

不允许：

- 在前端重写整套统计查询逻辑
- 把权限判断只放在浏览器
- 把核心筛选/统计口径只存在 Alpine state 中
- 引入大型前端 Store 形成隐性 SPA

## 11. 响应式与可用性

- 后台最低保证常见桌面宽度可用。
- 1280px+ 使用完整侧栏和多列卡片。
- 中等宽度自动压缩为 2 列/1 列。
- 小屏表格允许受控横向滚动或转为重点字段卡片，不允许页面整体布局崩溃。
- KPI 数字、状态码、百分比、毫秒、秒等单位必须清晰。
- 颜色不是唯一状态提示方式，错误/成功/趋势同时使用文字或符号。
- 空数据、加载中、查询失败必须有独立状态。

## 12. 性能要求

- ECharts 和 Alpine 仅在插件后台需要的页面加载。
- 资源本地缓存并使用版本号控制缓存刷新。
- 不在每个图表重复加载 ECharts。
- 大量表格数据继续由服务端分页，不将数万行 JSON 一次性下发浏览器。
- 实时轮询只请求所需数据，不刷新整个后台 HTML。
- 前台 Beacon 与后台 UI 代码物理/逻辑分离，关闭 Beacon 时前台不加载后台依赖。

## 13. 安全要求

- 后台 API/AJAX 权限继续由 PHP/Z-Blog 服务端验证。
- 所有写操作使用 CSRF。
- AJAX 参数继续经过统一过滤/校验。
- Drawer、Tooltip、表格、图表标签中的用户输入数据必须安全转义。
- 不允许通过 ECharts formatter/HTML Tooltip 注入未转义的 Referer、URL、UA、Campaign 等原始字符串。

## 14. 验收

UI 验收至少包括：

1. 11 个模块可正常导航，无顶部 Tab 溢出。
2. Dashboard 首屏 KPI 与核心图表在真实数据/空数据下均正常。
3. ECharts 不依赖公网资源。
4. Alpine 交互在无构建服务器情况下可直接运行。
5. 实时页面轮询可启动、暂停、失败恢复。
6. 访问记录详情 Drawer 正常。
7. Dashboard/图表/排行下钻正确保留筛选语义。
8. 服务端性能与 RUM 性能视觉和语义明确分离。
9. 窄屏/中等宽度无严重布局错位。
10. Z-Blog 原生后台其他页面不受 xz_visit_stats CSS/JS 污染。
11. 所有资源均随插件本地打包。
12. PHP SSR 关闭 JavaScript 后，基础后台数据和服务端筛选仍保持可用的合理降级。

## 15. 实现优先级

UI 实现随 v3 阶段推进：

- V3-T1 / T2：准备统一数据接口和 JSON 输出结构，不提前做完整视觉页面。
- V3-T3：完成 Design System、导航、Dashboard、全部模块 UI、Alpine 交互和 ECharts。
- V3-T4：做 UI 性能、轮询、数据量、XSS/Tooltip、响应式和无 CDN 验收。
- V3-T5：完整后台回归与正式发布验收。

本规范与 `PRD-v1.0.md` 一起构成 v3.0.0 的冻结实施基线。