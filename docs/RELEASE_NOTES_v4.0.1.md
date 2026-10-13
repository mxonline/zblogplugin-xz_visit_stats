# xz_visit_stats v4.0.1

## 发布内容

v4.0.1 是版本元数据与发布文档修正版。它将插件版本、当前版本文档和安装包名称统一为 `4.0.1`，并明确记录 v4.0.0 的正式发布状态。v4.0.0 原 Tag、GitHub Release 与安装包保持不变。

本版本不更改 v4 统计运行逻辑、数据口径、权限、隐私设置或数据库结构。已安装 v4.0.0 的站点可以直接更新插件文件，无需迁移；数据库 `db_version` 仍为 `4.0.0`。v3 → v4 的升级步骤仍适用，见 [升级说明](v4.0.0/UPGRADE-v3-to-v4.md)。

## 兼容性与验证

- PHP 语法最低要求为 7.1；继承的 Windows 验收环境为 IIS 10、PHP 8.3.8、Z-BlogPHP build 173540、MySQL 5.7.38。
- 继续保留 T5 已验证的安装、v3 → v4 隔离迁移、重复迁移、停用/启用、统计口径、隐私权限、响应式、HTTP Smoke 和 MySQL EXPLAIN 结果。本次未重跑 T2–T5；代码无运行时或 schema 变更。
- 发布核验执行 PHP/JavaScript 语法、PHPUnit 和包内清单/逐文件 SHA-256 检查；结果记录在项目状态与 GitHub Release。

## 安装包

文件名：[`xz_visit_stats-v4.0.1.zip`](https://github.com/mxonline/zblogplugin-xz_visit_stats/releases/download/v4.0.1/xz_visit_stats-v4.0.1.zip)。安装包保留运行时依赖及其许可证，排除开发依赖、测试、配置、缓存和备份。
