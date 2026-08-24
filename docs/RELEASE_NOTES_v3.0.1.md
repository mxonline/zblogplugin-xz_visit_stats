# xz_visit_stats v3.0.1 发布说明

v3.0.1 用于补齐 v3.0.0 发布后发现的冻结 P0 门禁，不修改 v3.0.0 Tag/Release。

## 主要变化

- 随插件本地打包 Alpine.js 3.16.3 和 Apache ECharts 6.1.0，无公网 CDN 依赖。
- Dashboard 增加趋势、时段、来源、环境、蜘蛛、错误和 RUM 图表区域。
- 访问记录增加安全转义的右侧详情 Drawer。
- 增加来源、UTM、AI 爬虫历史维度的分批可恢复回填入口。
- 记录 100k/1m 临时 benchmark 结果；1000 万行仍仅作风险估算。

## 兼容与限制

- 与 v3.0.0 数据结构和统计口径兼容，不删除历史日志。
- migration 继续幂等、可重复执行；RUM 仍默认关闭。
- 本机数据库用户缺少创建独立 benchmark 数据库的权限，因此 100k/1m 使用连接级临时表验证；这不等同于生产 SLA。
- VisitorHash 不是用户账号身份；DurationMs 是服务端处理耗时；RUM 指标仅来自启用 Beacon 后的新数据。

安装包：`xz_visit_stats-v3.0.1.zip`
