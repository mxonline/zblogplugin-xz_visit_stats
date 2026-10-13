# xz_visit_stats v4.0 — v3 → v4 缺口分析 v1.0

## 1. 当前 v3 数据基础

v3 当前正式基线在 `plugin.xml` 中记录为 3.0.0。

主访问日志表由 `inc/install.php` 定义，当前已具备以下核心字段：

- IP
- VisitorHash
- URL / Path / PathKey
- Referer
- UserAgent
- UaType
- Browser
- Os
- Device
- IsBot / BotName
- StatusCode
- DurationMs
- SourceType / SourceDomain
- AiSource
- UtmSource / UtmMedium / UtmCampaign / UtmContent / UtmTerm
- PageTitle / PostID
- GeoCountry / GeoRegion
- AiCrawler
- VisitedAt

当前已有索引主要覆盖：

- 访问时间
- 访客 + 时间
- 蜘蛛 + 时间
- IP + 时间
- 状态码 + 时间
- 来源 + 时间

这套基础已经能够支持 v4 的大量“单次请求维度”分析，但还不能直接支持完整的会话、停留、跳出和事件分析。

## 2. v3 已经可以直接复用的能力

### 2.1 访问趋势

可以继续基于 VisitedAt、VisitorHash、IP 聚合 PV / UV / IP。

### 2.2 来源分析

SourceType、SourceDomain、Referer、AI 来源和 UTM 字段可以直接作为 v4 来源分析基础。

### 2.3 设备与浏览器

UaType、Browser、Os、Device 可直接用于访客维度统计。

### 2.4 地域

GeoCountry、GeoRegion 可继续复用。

### 2.5 蜘蛛

IsBot、BotName、AiCrawler、IP、URL、StatusCode 和 VisitedAt 可直接支撑蜘蛛排行和抓取明细。

### 2.6 HTTP 错误与性能

StatusCode 可以继续用于错误分析。

DurationMs 继续作为服务器请求处理耗时或性能指标，不改变语义。

## 3. v4 当前缺失的核心数据语义

### 3.1 Session

v3 没有独立 Session ID，也没有会话开始、结束、页数、入口页、退出页等持久语义。

只靠 VisitorHash + 时间临时拼接会话会导致报表重复计算，后续需要会话基础设施或稳定的会话派生方案。

### 3.2 页面序列

v3 每条日志彼此独立，没有“同一会话第几页”“前一页 / 后一页”“页面生命周期”语义。

### 3.3 真实停留时长

`vs_DurationMs` 是服务端处理耗时，不能转换成页面停留时长。

v4 需要客户端 Beacon 或页面生命周期事件补充离开 / 隐藏 / 页面存活信息。

### 3.4 跳出率

没有会话页数就不能稳定计算跳出率。

### 3.5 新老访客状态

VisitorHash 已存在，但 v3 没有把首次访问时间或新老访客状态固化为可高效查询的数据结构。

### 3.6 事件

v3 没有自定义事件模型，无法统计事件名称、参数、独立触发用户与人均触发。

### 3.7 目录规则

v3 Path / PathKey 能表示页面，但没有站长可配置的目录归类与排除规则。

### 3.8 导出任务

v3 没有异步 / 受控的数据下载任务模型。

## 4. v4 建议新增的数据结构类别

最终字段必须以 T2 的真实数据库审计为准，当前只确定逻辑类别，不锁死 SQL：

- Session 表或等价会话汇总结构
- 页面序列 / 页面生命周期数据
- 事件表
- 目录规则表
- 小时 / 日扩展汇总表
- 数据导出任务表
- IP 过滤规则结构

所有新增结构要求：

- 增量创建；
- 幂等；
- 不删除 v3 字段；
- 不覆盖历史日志；
- 可在升级失败时停止继续迁移；
- 索引设计以常用时间范围 + 维度筛选为主。

## 5. 可立即实现与必须等待 T2 的边界

### 可以基于现有代码继续设计

- v4 后台信息架构
- 统计口径
- 来源、设备、蜘蛛、地域的页面设计
- Session / Event 的逻辑模型
- API / 查询服务边界

### 必须等待本地真实结构审计

- 最终表名和列类型
- 是否存在历史版本遗留列 / 索引
- 真实数据量和行数
- MySQL / MariaDB 具体版本
- 索引增量方案
- 大表迁移风险
- 迁移脚本最终 SQL

## 6. 当前风险

### DurationMs 语义误用

这是 v4 最容易出现的统计错误。任何“平均停留时长”报表都不能读取 DurationMs 作为访客停留。

### 日志表增长

访问明细和页面生命周期数据会快速增长。常用图表不能长期依赖原始大表无界聚合。

### 访客隐私

VisitorHash 和 IP 只能用于站内统计、过滤和受控导出。新增会话与事件不能扩展成跨站跟踪标识。

### 历史兼容

v4 必须能在已有 v3 数据上升级，不能假设数据库是全新安装状态。

## 7. 当前结论

v3 已经具备 51.LA 类后台所需的大部分“请求级原料”，v4 的主要技术升级不是继续堆字段，而是补上会话、页面序列、事件与汇总层。

下一工程门槛是 T2：在真实 Windows Z-Blog 环境运行只读结构审计，确认现有表、列、索引、行数、运行时版本与安全配置，再锁定增量迁移设计。
