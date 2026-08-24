# xz_visit_stats v2.0.0 Phase 1 数据库架构设计

> 状态：**旧范围下的 Phase 1 设计，待重新评审，不得直接视为 v2.0 最终数据模型。**
>
> v2.0 已重新定位为“站长访问分析中心”。正式数据模型必须先依据 `v1.3-data-baseline.md`，并结合本机 v1.3 真实数据画像，再决定哪些表保留、合并或取消。

## 目标

建立 v2.0 数据基础架构，在保持 v1.x 数据兼容的前提下，为 Dashboard、SEO分析、蜘蛛分析、安全分析提供统一数据支持。

## 设计原则

- 不破坏已有访问记录
- 支持自动升级
- 新字段采用渐进式迁移
- 避免每次访问产生大量统计计算
- 支持后续缓存和定时汇总

## 新增数据表规划

### 页面统计表

表名：xz_visit_pages

字段：

- id
- url
- title
- pv
- uv
- avg_time
- last_visit
- created
- updated

用途：

统计文章和页面访问表现。

---

### SEO来源表

表名：xz_visit_keywords

字段：

- id
- engine
- keyword
- referer_url
- referer_domain
- landing_page
- visit_time

用途：

记录搜索来源、关键词和入口页面。

---

### 错误日志表

表名：xz_visit_errors

字段：

- id
- url
- status
- referer
- user_agent
- created

用途：

分析404、500等异常页面。

---

### 安全分析表

表名：xz_visit_security

字段：

- id
- ip
- ua
- request_count
- risk_level
- status
- updated

用途：

识别异常访问。

## 数据升级方案

升级流程：

1. 检测当前插件版本
2. 执行数据库迁移
3. 创建缺失字段
4. 保留原有访问数据
5. 更新版本号

## Codex开发要求

- 修改前读取现有数据库结构
- 禁止直接删除旧字段
- 每次迁移必须提供回滚说明
- 完成后执行代码检查

## 当前重新评审要求

继续 Phase 2 前必须先完成：

1. v1.3 真实代码和表结构基线；
2. 本机 v1.3 真实数据画像；
3. 对本文件四张规划表逐一判断：保留 / 合并 / 取消 / 改为汇总表；
4. 确认不会重复存储 v1.3 原始日志中已经存在的事实数据；
5. 重新形成正式 v2.0 数据模型。

在上述评审完成前，旧的 `xz_visit_pages`、`xz_visit_keywords`、`xz_visit_errors`、`xz_visit_security` 规划仅作为历史设计参考。
