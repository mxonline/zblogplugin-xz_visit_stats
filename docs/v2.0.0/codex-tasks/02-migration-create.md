# xz_visit_stats v2.0 Codex Task 02

## 目标
实现 v2.0 数据表迁移脚本。

## 新增数据表

- xz_visit_pages
- xz_visit_keywords
- xz_visit_errors
- xz_visit_security

## 开发要求

1. 使用 Z-Blog 数据库接口。
2. 检查表是否存在。
3. 不重复创建。
4. 升级失败需要记录错误。
5. 保持 v1.3 数据兼容。

## 测试

- 空数据库安装
- v1.3升级
- 多次升级
- 数据完整性检查

## 注意

禁止删除旧表。
禁止修改现有访问记录结构，除非经过需求确认。