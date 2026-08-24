# xz_visit_stats

Z-BlogPHP 站长访问分析中心，当前正式版本为 **v2.0.0**。

## 功能范围

- 总览、访问记录、页面分析、来源分析、蜘蛛分析和错误分析
- 设置与维护：采集、隐私、迁移、PathKey 回填和日汇总重建
- 访问记录组合筛选、分页和下钻

## 安装

1. 解压 `xz_visit_stats-v2.0.0.zip` 至 `zb_users/plugin/`，保持目录名为 `xz_visit_stats`。
2. 在 Z-BlogPHP 后台的插件管理中启用“访问统计”。
3. 使用具备 `root` 权限的账号打开“访问分析”；从 v1.3 升级会保留原始日志并初始化 PathKey、日汇总和汇总状态结构。

## 后台入口

插件后台固定提供七个模块：总览、访问记录、页面分析、来源分析、蜘蛛分析、错误分析、设置与维护。

## 文档

- [版本信息](docs/VERSION.md)
- [变更记录](docs/CHANGELOG.md)
- [开发指南](docs/DEVELOPMENT.md)
- [v2.0.0 发布说明](docs/RELEASE_NOTES_v2.0.0.md)

## 注意事项

VisitorHash 是匿名统计标识，不是用户账号身份；DurationMs 是服务端处理耗时。蜘蛛识别基于 User-Agent，不包含反向 DNS 或官方 IP 段真实性验证。关键词仅来自 Referer 实际可解析内容。1000 万行未做真实压测，复杂聚合不作毫秒级承诺。
