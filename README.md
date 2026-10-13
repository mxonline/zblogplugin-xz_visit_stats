# xz_visit_stats

Z-BlogPHP 站长访问分析中心。当前插件版本为 **v4.0.1**，版本记录与下载地址见 [GitHub Release v4.0.1](https://github.com/mxonline/zblogplugin-xz_visit_stats/releases/tag/v4.0.1)。

v4.0.1 是 v4.0.0 的文档与版本元数据修正版，不更改统计运行逻辑或数据库结构。v4.0.0 的 Tag、GitHub Release 和原安装包保持不变。

## 功能

- 访问总览、实时访客与按小时/日/周/月查看的趋势；
- 访问记录、来源、内容、访客、蜘蛛与事件分析；
- 会话页面序列、受控下钻、IP/CIDR 过滤与权限受控 CSV 导出；
- 保留 v3 原始访问日志和既有汇总结构，增量新增 v4 会话、页面、事件等数据表；
- Beacon 默认关闭，IP 展示遵循插件隐私设置。

## 兼容与验证环境

- PHP 语法最低要求为 7.1；T5 Windows 本机验收使用 PHP 8.3.8；
- 实机验收使用 Z-BlogPHP build 173540、IIS 10 和 MySQL 5.7.38；
- v3 历史访问记录保留。升级前的数据没有 v4 会话生命周期信息时，停留时长与跳出率显示为不可用或部分覆盖，不会从服务器处理耗时推算；
- `vs_DurationMs` 表示服务器处理耗时，不代表访客停留时间。

## 安装与升级

1. 备份插件目录和数据库，再解压 `xz_visit_stats-v4.0.1.zip` 到 `zb_users/plugin/`，目录名保持 `xz_visit_stats`。
2. 在 Z-BlogPHP 插件管理中启用“访问统计”。安装/升级过程会验证并补齐 v4 结构，迁移可重复运行。
3. 打开“访问分析”，检查总览、访问记录、来源、访客、蜘蛛、事件和设置页面。
4. v3 升级细节见 [v3 → v4 升级说明](docs/v4.0.0/UPGRADE-v3-to-v4.md)。

停用插件会保留历史访问数据和数据库表。不要通过删除 v3/v4 数据表来回滚。

## 文档

- [版本信息](docs/VERSION.md)
- [变更记录](docs/CHANGELOG.md)
- [v4.0.1 发布说明](docs/RELEASE_NOTES_v4.0.1.md)
- [v4.0.0 发布说明](docs/RELEASE_NOTES_v4.0.0.md)
- [v3 → v4 升级说明](docs/v4.0.0/UPGRADE-v3-to-v4.md)
- [第三方组件与许可证](THIRD-PARTY-NOTICES.md)

## 隐私说明

VisitorHash 是不可逆匿名统计标识，不是账号身份。事件参数仅接收白名单字段。历史记录的 IP 不会因设置变更而回写；导出仅允许有权限的管理员，并且任务只允许所属管理员查看或下载。
