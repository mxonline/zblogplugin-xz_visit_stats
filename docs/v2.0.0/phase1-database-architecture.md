# xz_visit_stats v2.0.0 Phase 1 数据库架构设计

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

## 下一阶段

Phase 1完成后进入：

Phase 2 Dashboard 数据中心开发。
