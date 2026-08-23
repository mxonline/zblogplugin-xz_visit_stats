# xz_visit_stats v2.0 Phase 1 升级底座实现

## 目标

基于 v1.3.0 真实代码结构，建立安全升级机制。

## 当前确认

现有核心数据表：xz_visit_stats_log。

v2.0 不直接重构旧表，采用增量升级。

## 第一阶段代码目标

新增：

- inc/upgrade/version.php
- inc/upgrade/checker.php
- inc/upgrade/runner.php
- inc/upgrade/migrate.php

## 开发要求

- 保留 v1.3 数据
- 支持重复执行检测
- 升级失败不能破坏已有数据
- 使用 Z-Blog 数据库接口
- PHP 5.6+兼容

## 验收

- 新安装正常
- v1.3升级正常
- 重复升级无副作用
