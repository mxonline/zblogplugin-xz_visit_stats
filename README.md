# xz_visit_stats

Z-BlogPHP 前台访问统计插件，当前正式版本为 **v1.0.0**。

## 功能范围

- 访问统计与访问记录
- 蜘蛛分析与 SEO 报告
- 来源分析
- IP 分析与只读异常展示
- 实时访问
- 数据维护与日志清理

## 安装

1. 解压 `xz_visit_stats-v1.0.0.zip` 至 `zb_users/plugin/`，保持目录名为 `xz_visit_stats`。
2. 在 Z-BlogPHP 后台的插件管理中启用“访问统计”。
3. 使用具备 `root` 权限的账号打开插件后台；首次启用会初始化访问日志表与必要索引。

## 后台入口

插件后台支持统计概览、访问记录、蜘蛛分析、SEO 报告、来源分析、IP 分析、实时访问和数据维护。

## 文档

- [版本路线](docs/VERSION.md)
- [变更记录](docs/CHANGELOG.md)
- [开发指南](docs/DEVELOPMENT.md)
- [v1.0.0 发布说明](docs/RELEASE_NOTES_v1.0.0.md)

## 注意事项

统计数据来自已记录的前台页面访问。蜘蛛识别基于 User-Agent，不包含反向 DNS 或官方 IP 段真实性验证。
