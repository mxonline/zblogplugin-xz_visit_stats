# xz_visit_stats v3.0.0 UI Specification

状态：**FROZEN IMPLEMENTATION BASELINE｜2026-08-25 重新核对**

本文件定义重新立项后的 v3.0.0 后台 UI 正式实现技术栈与交互基线，并与 `docs/v3.0.0/PRD-v1.0.md` 重新核对一致。上一轮 v3.0 实现结果不继承，UI 必须重新按本规范真实验收。

## 1. UI 技术栈

正式采用：

- **Z-Blog 原生后台 Shell / PHP SSR**：继续使用 Z-Blog 后台头部、权限与页面生命周期。
- **xz_visit_stats Scoped CSS Design System**：插件自有组件样式全部使用 `xzvs-` 命名空间，避免污染 Z-Blog 全局样式。
- **Alpine.js 3.x**：负责轻量交互，例如筛选展开、Drawer、移动端导航、Dropdown、实时轮询状态与局部 UI 状态。
- **Apache ECharts 6.x**：负责 Dashboard、趋势、时段、来源、环境、蜘蛛、错误和 RUM/Core Web Vitals 图表。
- **Fetch / AJAX**：用于实时分析 15–30 秒轮询、局部查询和异步明细加载。
- **原生 JavaScript Beacon**：PerformanceObserver / Performance API + `navigator.sendBeacon()`，必要时 `fetch(..., {keepalive:true})` 降级。
- **本地 SVG 图标**：不依赖外部图标 CDN。

所有前端运行时资源必须随插件本地打包，后台打开不得依赖公网 CDN。

## 2. 明确不采用

v3.0.0 不采用以下方案作为主 UI 架构：

- React SPA
- Vue SPA
- Next/Nuxt
- 整套 Element Plus
- 整套 Ant Design
- 整套 Bootstrap / Tabler 覆盖 Z-Blog 后台

可以参考现代 Admin UI 的视觉语言，但不整包引入。

## 3. CSS 命名空间

新增样式优先使用：

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

不得新增会广泛覆盖 Z-Blog 全局元素的裸选择器，除非限定在 `.xzvs-app` 内。

## 4. 后台导航结构

11 个能力模块不得继续全部堆在顶部横向 Tab 中。1280px+ 使用插件内部真正左侧分组导航，小屏折叠。

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

- 保留 Z-Blog 顶部原生后台结构；
- 当前模块有清晰选中态；
- 1280px+ 必须是真正侧栏布局，不得用横向 grid 伪装；
- 窄屏自动折叠，不横向溢出。

## 5. Dashboard

第一屏必须以 KPI + 图表为主，不直接堆大表格。

核心 KPI：真人 PV、真人 UV、独立 IP、蜘蛛访问、4xx、5xx、服务端平均/分位耗时；Beacon 开启且有数据时增加 LCP/INP/CLS 摘要。

主要图表：

- PV / UV / IP 折线图
- 真人 / 蜘蛛趋势
- 24 小时访问柱状图
- 来源类型图
- 浏览器 / OS / 设备分布
- 热门页面
- 热门 IP
- 搜索引擎来源
- AI 来源
- 蜘蛛排行
- 错误摘要

旧式表格只作为明细/下钻，不得主导总览第一屏。

## 6. 实时分析

至少展示：活跃访客短窗口、最近 5/30 分钟 PV/UV、实时访问流、热门页面、来源、地域、设备、蜘蛛、404/5xx。

默认使用 15–30 秒轻量轮询；页面不可见时降低或暂停；失败使用非阻塞错误状态；不要求 WebSocket。

## 7. 访问记录

保留服务端筛选语义，前端负责交互体验。

标准操作：查询、重置、保存筛选、CSV 导出。

详情优先使用右侧 Drawer，包含请求、IP/地域、来源/Referer/Campaign、浏览器/OS/设备、页面/Z-Blog 内容关联、状态码、服务端耗时，以及可靠关联时的 Beacon 性能摘要。

Drawer 必须支持 Esc 关闭、小屏可用、安全转义。

## 8. 性能分析

必须分成两个明确区域：

### 服务器性能
- `DurationMs`
- P50 / P75 / P95（数据量允许时）
- 慢页面
- 慢请求
- 趋势

### RUM
- LCP
- INP
- CLS
- TTFB
- FCP

LCP/INP/CLS 至少 P75；不支持的浏览器允许缺失，不补 0；RUM 与 `DurationMs` 使用不同视觉分区和标签。

## 9. ECharts 使用边界

优先使用 Line、Bar、Stacked Bar、Donut/Pie、Horizontal Bar。Heatmap 仅用于星期×小时等统计热图，不代表用户行为 Heatmap。

## 10. 页面状态

所有模块必须具备：

- Loading
- Empty
- Error
- Normal

图表无数据不能出现 JS 报错或无限 loading。

## 11. Alpine 使用要求

Alpine.js 必须实际承担轻量交互状态，例如移动端导航、Drawer、筛选折叠或 Dropdown 中至少一类核心交互；不得仅打包加载文件就宣称“已使用 Alpine”。

## 12. 响应式

- ≥1280px：左侧导航 + 右侧主内容；
- 中等宽度：侧栏合理收缩；
- 手机：导航折叠，表格可安全横向滚动或转卡片，不撑破 Z-Blog 后台。

## 13. 资源与缓存

- Alpine/ECharts 等均本地打包；
- CSS/JS 使用与当前插件版本一致或基于文件 hash 的 cache-buster；
- 不得继续使用明显过期的旧版本参数导致缓存错配。

## 14. 安全

- URL/Referer/UA/标题安全转义；
- Drawer/Tooltip 不直接插入未经处理的 HTML；
- AJAX 接口做管理员权限、参数校验、CSRF/方法约束（按读写语义）；
- 不在前端资源中暴露数据库凭据或敏感配置。

## 15. 真实验收硬门禁

UI Runtime 不能通过“文件存在”“语法通过”“资源已加载”判 PASS。

必须实际打开本机 Z-Blog 后台，至少验证：

- 11 个模块可打开；
- 1280px+ 真正左侧导航；
- Dashboard 第一屏 KPI + ECharts；
- 移动端导航展开/收起；
- Drawer；
- 空数据/错误状态；
- DurationMs 与 RUM 视觉分离；
- 浏览器控制台无阻断性 JS 错误。

若环境无法进行真实页面验证，则：

```text
UI Runtime = BLOCKED
Release Gate = BLOCKED
```

禁止因此提前创建 Tag / Release。

**状态：FROZEN。进入 V3-T3 时以本文件作为唯一 UI 实现与验收基线。**