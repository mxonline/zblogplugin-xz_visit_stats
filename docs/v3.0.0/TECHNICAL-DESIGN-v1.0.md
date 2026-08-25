# xz_visit_stats v3.0.0 技术设计 v1.0

状态：**FROZEN IMPLEMENTATION DESIGN｜2026-08-25 重新立项版**

本设计以 `v2.0.1` 代码为唯一正式基线，对应 `docs/v3.0.0/PRD-v1.0.md` 与 `docs/v3.0.0/UI-SPEC.md`。上一轮 v3 实现只允许参考，不继承 PASS。

## 1. 总体架构

```text
前台请求
→ v2.0.1 服务器端采集核心
→ v3 增强采集/物化字段
→ 原始访问事实表
→ 小时/日汇总
→ 统一查询层
→ PHP SSR 后台模块
→ ECharts / Alpine / Fetch

可选 Beacon
→ 同源 RUM Endpoint
→ 独立 RUM 数据
→ 性能聚合查询
→ 性能分析 / Dashboard
```

设计原则：服务器端采集始终是核心；Beacon 关闭时，PV/UV/IP、来源、蜘蛛、错误、页面等基础统计完整可用。

## 2. 兼容策略

- 保留 v2.0.1 原始日志表作为历史事实来源。
- 不清空、不重建真实历史日志。
- 迁移通过版本/状态记录实现幂等。
- 已存在 v3 残留字段/表时，迁移先检测再补齐，不重复创建、不删除历史数据。
- 历史可可靠派生的来源域名、来源类型、PathKey 等允许分批回填。
- RUM、屏幕、viewport、语言等 v2 不存在的数据仅升级后产生。
- 用户明确禁止额外数据库管理操作：不创建/删除独立测试库，不要求额外账号授权；插件自身非破坏性 migration 属于正常升级行为。

## 3. 数据层方向

### 3.1 原始访问日志

继续复用 v2.0.1 原始事实表，在兼容前提下补充/确认以下高频字段方向：

- PathKey / 规范化 Path
- SourceType
- SourceDomain
- UTM Source / Medium / Campaign / Content / Term
- AI Source
- AI Crawler 标识/类型
- 页面标题缓存值或可解析内容 ID
- 国家/地区等地域字段（按 IP 模式可为空/降级）

是否新增具体字段必须先读取当前真实 schema；不得凭旧 v3 代码直接假定。

### 3.2 小时汇总

新增/恢复小时级汇总，用于：

- PV / 蜘蛛 / 错误趋势
- 24 小时时段分布
- 高频来源/环境维度
- 实时之外的短周期聚合

UV/IP 的精确跨范围去重不能简单累加小时去重值。

### 3.3 日汇总

沿用 v2.0.1 日汇总能力，必要时扩展维度，但不得破坏现有统计口径。

### 3.4 RUM

RUM 独立存储，不与 `DurationMs` 混用。字段方向：

- 时间
- PathKey / 页面关联键
- LCP
- INP
- CLS
- TTFB
- FCP
- screen width/height
- viewport width/height
- browser language
- 必要的匿名关联键

不采集 DOM 文本、表单值、Cookie、LocalStorage、键盘输入。

### 3.5 保存筛选

保存筛选只保存管理员后台筛选条件，不保存敏感访问内容。必须做管理员权限和字段白名单校验。

## 4. 真实 IP

`REMOTE_ADDR` 为唯一信任根。

流程：

1. 读取 `REMOTE_ADDR`；
2. 判断是否命中管理员可信代理 IP/CIDR；
3. 只有命中才允许解析 CF-Connecting-IP / X-Forwarded-For / X-Real-IP / 自定义 Header；
4. 多级 XFF 按可信代理链规则解析；
5. 所有 IP 做 IPv4/IPv6 格式校验；
6. masked 模式先执行隐私策略，再进入展示/地域逻辑。

不得无条件信任用户可伪造 Header。

## 5. 来源与 Campaign

采集阶段尽量将高频维度物化，查询阶段避免重复解析完整 Referer。

来源分类至少：

- direct
- search
- ai
- social
- external
- internal
- campaign
- other

AI 助手映射独立维护，至少覆盖 PRD 指定平台。UTM 字段限制长度并进行规范化；完整 Referer 仍保留用于明细。

## 6. AI 爬虫

在现有 Bot 识别基础上增加 AI crawler 独立分类。分类规则集中管理，不散落在页面/查询代码中。P0 只要求 UA/规则识别，DNS/IP 官方真实性验证留 P1。

## 7. 页面与 Z-Blog 关联

- 统计主维度使用规范化 Path / PathKey；
- 可解析时关联 Z-Blog Post/Page ID 与标题；
- 标题历史无法证明时仅显示当前解析值或“升级后可用”；
- 不将随机 query 参数扩张为页面维度。

## 8. 查询层

统一查询层负责：

- Dashboard
- 趋势与 24 小时
- 实时
- 访问记录
- 页面
- 来源/UTM/AI
- IP
- 环境
- 蜘蛛
- 错误
- DurationMs 性能
- RUM 性能

原则：

- 时间、状态、类型、排序字段全部白名单；
- 大列表使用 Keyset/游标；
- 7/30 天常用趋势优先小时/日汇总；
- 精确 UV/IP 在需要时回到可证明的精确去重方案；
- 不因性能优化改变指标语义。

## 9. 实时分析

采用最近 5/30 分钟窗口，不宣称真实并发在线人数。后台 15–30 秒 Fetch 轮询；页面隐藏时暂停/降频；查询必须有时间索引边界。

## 10. CSV

- 只允许管理员；
- 使用当前筛选条件；
- 限制最大导出行数/时间范围；
- 采用流式/分批输出避免大内存；
- 对以 `= + - @` 等开头的单元格做公式注入防护。

## 11. Beacon Endpoint

- 默认关闭；
- 仅同源前台请求；
- 只允许规定方法；
- JSON/Form 字段白名单；
- payload 大小限制；
- 指标数值范围检查；
- 简单频率限制/去滥用；
- 任何异常不影响页面正常响应；
- 不返回数据库错误细节。

## 12. RUM 聚合

- LCP / INP / CLS 至少 P75；
- TTFB / FCP 至少稳定均值/分位数之一；
- 不支持浏览器产生 NULL/缺失，不补 0；
- 支持页面、浏览器、OS、设备、语言、屏幕/viewport 维度；
- 与服务器 `DurationMs` 使用独立查询函数和 UI 区块。

## 13. 后台 UI

严格执行 `UI-SPEC.md`：

- Z-Blog Shell + PHP SSR；
- 1280px+ 真正左侧导航；
- Alpine.js 实际承担轻量交互；
- ECharts 图表本地打包；
- Dashboard 首屏 KPI + 图表；
- Drawer 访问详情；
- Loading / Empty / Error / Normal；
- 小屏折叠；
- CSS 全部限制在 xzvs 命名空间；
- JS/CSS cache-buster 与当前版本或文件 hash 一致。

## 14. 性能策略

- 高频趋势：小时/日汇总；
- 来源解析：采集时物化；
- 深分页：Keyset；
- 实时：严格短窗口；
- 复杂排行：限制时间范围和结果数；
- 10 万/100 万关键查询提供可重复性能证据；
- 1000 万未实测时只给风险估算。

## 15. 安全

- 后台管理员权限；
- 写操作 CSRF；
- SQL 参数化/白名单；
- HTML 输出转义；
- Drawer/Tooltip 不直接插入不可信 HTML；
- Beacon/CSV/设置接口做权限和输入边界；
- 日志、CI、Issue、PR、Release 不输出数据库密码或敏感配置。

## 16. 阶段实施

### T1
读取 v2.0.1 真实 schema/代码，完成数据/采集/迁移/真实 IP/来源/UTM/AI/RUM 基础。已有 `457735a6...` 只做 WIP 对照，逐项确认后才能保留。

### T2
完成统一查询、小时/日/raw 协作、实时、Keyset、同比/环比、RUM 聚合与性能优化。

### T3
实现 11 模块与冻结 UI-SPEC，必须真实浏览器验收。

### T4
本机运行、安全、性能、兼容、Beacon、CSV、可信代理等验证；不做额外数据库管理操作。

### T5
建立 27 项 P0 矩阵和完整 Gate，所有必须项 PASS 后才发布。

## 17. 发布约束

任何必须 Gate = BLOCKED：禁止 Tag、GitHub Release、正式 ZIP。

T1–T4：`Release Gate = NOT READY`。

只有 T5 完整通过才重新发布正式 `v3.0.0`。

**技术设计状态：FROZEN。**