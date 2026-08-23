# xz_visit_stats v2.0 Codex Task 01

## 目标
实现数据库升级基础系统。

## 开发范围

创建：

- inc/upgrade/version.php
- inc/upgrade/checker.php
- inc/upgrade/runner.php

## 要求

1. 自动读取当前数据库版本。
2. 判断是否需要升级。
3. 支持 v1.3.0 到 v2.0.0。
4. 防止重复执行迁移。
5. 保留旧数据。
6. 不影响正常访问性能。

## 测试

- 新安装测试
- 已存在 v1.3 数据测试
- 重复执行测试
- 异常中断测试

## Codex规则

修改前读取现有代码。
不要大范围重写文件。
保持 Z-Blog PHP 兼容。
完成后执行 PHP 语法检查。