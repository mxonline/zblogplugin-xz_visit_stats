# xz_visit_stats 开发指南

## 项目定位

`xz_visit_stats` 是 Z-BlogPHP 的本地访问统计插件。它在前台请求结束时记录有效页面访问，并在后台提供访问记录、统计概览、蜘蛛分析、SEO 报告、来源分析以及 IP 分析与基础异常检测。

当前开发版本为 v1.2.0。插件的数据源为 `zbp_xz_visit_stats_log`（实际表前缀由 Z-BlogPHP 数据库配置决定）。

## v1.2.0 开发范围

- 设置中心使用插件配置保存采集、隐私、日志保留和自动清理选项，不新增日志字段。
- 来源分析复用 Referer、IP、页面地址和访问时间字段，提供 Top100 排行与有界明细查询。
- SEO 报告复用蜘蛛、状态码和访问时间字段，提供抓取状态、页面排行和抓取效率统计。

## 开发原则

- 保持 Z-BlogPHP 插件边界：不修改 `zb_system`，不修改其他插件。
- 采集、查询、后台展示分层组织；SQL 不写入后台 HTML 模板。
- 采集范围仅限有效前台页面请求。后台、安装路径、插件后台路径和常见静态资源不计入访问日志。
- 后台分析统一沿用 `inc/stats.php` 的时间范围语义。
- 访问日志是长期增长数据。列表、排行和详情必须使用有界查询与分页，不能一次读取整张日志表。
- SEO 来源域名、来源链接、目标页面和来路链接明细固定使用 Top100 有界查询；来源数据只读取现有 Referer、IP、页面地址和访问时间字段。
- 基础风控只负责识别和展示，不执行自动封禁、拉黑或远程处置。
- 图表和后台资源保存在插件目录，不依赖第三方公网 CDN。

## 代码结构

```text
xz_visit_stats/
├─ include.php                 插件注册、启用、安装和卸载入口
├─ main.php                    后台单入口与各视图 HTML
├─ plugin.xml                  Z-BlogPHP 插件元数据
├─ assets/
│  ├─ admin.css                后台页面样式与响应式规则
│  ├─ admin.js                 访问记录详情展开行为
│  ├─ filter.js                访问记录高级筛选展开行为
│  ├─ overview.js              统计概览本地图表
│  ├─ spider.js                蜘蛛分析本地图表
│  ├─ source.js                来源分析本地图表
│  ├─ realtime.js              实时访问列表 AJAX 刷新
│  └─ seo.js                   SEO 报告本地图表
└─ inc/
   ├─ settings.php             设置项默认值、读取、保存与隐私处理
   ├─ helpers.php              请求、IP、URL、静态资源、哈希和响应信息工具
   ├─ collector.php            前台访问采集与日志写入
   ├─ bot.php                  搜索引擎蜘蛛 UA 识别
   ├─ ua.php                   UA、浏览器、系统和设备解析
   ├─ install.php              数据表、字段升级与索引维护
   ├─ query.php                访问记录筛选、分页和查询
   ├─ admin.php                后台菜单、转义、链接与显示辅助函数
   ├─ stats.php                概览统计、时间范围和统计数据源接口
   ├─ spider_stats.php         蜘蛛分析与规则型 SEO 报告
   ├─ source_stats.php         来源分类、来源排行与趋势
   ├─ ip_stats.php             IP 排行、详情和基础异常检测
   ├─ maintenance.php          日志概览、保留期配置与手动清理
   ├─ realtime.php             最近访问的有界查询和 JSON 响应
   └─ seo_report.php           SEO 蜘蛛报告统计、排行和异常分析
```

`include.php` 注册 `Filter_Plugin_Zbp_Terminate`，由 `xz_visit_stats_collect()` 在请求结束阶段采集；`main.php` 以 `view` 参数路由后台页面。当前视图为 `overview`、`records`、`spider`、`seo`、`source`、`ip`、`maintenance`、`realtime` 与 `settings`。

## 数据与索引

日志字段以 `vs_` 为前缀，包括：`vs_ID`、`vs_IP`、`vs_VisitorHash`、`vs_Url`、`vs_Path`、`vs_Referer`、`vs_UserAgent`、UA 分类字段、蜘蛛字段、状态码、响应耗时和 Unix 时间戳。

关键字段类型：

- `vs_ID`：`BIGINT UNSIGNED AUTO_INCREMENT`
- `vs_VisitedAt`：`BIGINT UNSIGNED` Unix 时间戳
- `vs_IP`：最长 45 字符，覆盖 IPv4 与 IPv6
- `vs_VisitorHash`：64 字符 SHA-256 HMAC 输出

安装逻辑维护以下索引：`vs_VisitedAt`、`vs_VisitorHash + vs_VisitedAt`、`vs_IsBot + vs_VisitedAt`、`vs_IP + vs_VisitedAt`、`vs_StatusCode + vs_VisitedAt`。对 URL、Referer、User-Agent 等文本字段不建立大文本索引。

## 安全规范

- 访问者哈希通过每站随机 `visitor_secret` 生成。密钥存放于插件配置，不得写入页面、日志、文档或版本库。
- IP 筛选使用 `FILTER_VALIDATE_IP`，兼容 IPv4 和 IPv6。
- 页码、每页数量、状态码、时间范围、蜘蛛名称和来源类型均须校验或白名单限制。
- SQL 中的动态数值必须转换为整数；文本参数必须先校验并转义，禁止直接拼接未验证的请求参数。
- 数据库中的 URL、Referer、User-Agent、IP、蜘蛛名称等均按不可信输入处理。输出到 HTML 前统一使用 `xz_visit_stats_admin_escape()`。
- 后台入口保留 Z-BlogPHP 的 `root` 权限检查和插件启用检查。
- 静态资源、后台请求和插件后台请求不应产生访问日志；页面型 404 仍应记录。

## 测试流程

### 采集回归

1. 访问首页与文章页，确认产生 200 日志。
2. 访问一个页面型不存在路径，确认记录状态码 404。
3. 访问 `favicon.ico`、CSS、JS、图片和其他静态资源，确认不写入日志。
4. 使用 Googlebot、Baiduspider、bingbot 等 UA 请求，确认蜘蛛名称与 `vs_IsBot` 正确。
5. 访问后台页面，确认后台请求不新增日志。
6. 停用后访问前台，确认不采集；重新启用后确认恢复采集且不会重复建表报错。

### 后台查询回归

1. 对访问记录验证基础筛选、高级筛选展开、分页、详情和 HTML 转义。
2. 验证 IP、HTTP 状态、状态码、URL 和 Referer 条件能保留至分页链接。
3. 对 SEO 报告验证百度、Google、Bing、外部链接和直接访问的来源分类、来源排行与来路明细。
4. 对概览、蜘蛛、来源和 IP 页面，将核心指标与同范围直接 SQL 聚合结果比较。
5. 选择无日志的自定义时间段，确认指标为 0、列表为空、不出现 `NaN` 或 `Infinity`。
6. 在 1100px、800px 与 680px 宽度下检查筛选、指标卡、图表和表格横向滚动。
7. 对 IP 分析验证普通访问、高频访问、404 请求和扫描工具 UA 命中规则；异常结果只用于展示。

### 语法检查

在当前本地环境中使用：

```powershell
$phpExe = 'D:\BtSoft\php\83\php.exe'
Get-ChildItem .\zb_users\plugin\xz_visit_stats -Recurse -Filter '*.php' |
  ForEach-Object { & $phpExe -l $_.FullName }
```

## 版本开发流程

1. 阅读当前模块、路由、时间范围与查询接口，确认是否已有可复用能力。
2. 在新模块的 `inc/` 文件中集中实现数据筛选、聚合、分页与阈值；`main.php` 负责路由和展示。
3. 优先复用 `xz_visit_stats_stats_range()`、`xz_visit_stats_stats_table()`、后台转义和 URL 辅助函数。
4. 在本地数据库以真实请求生成最小测试样本，并进行直接 SQL 抽查。
5. 完成无数据、异常参数、响应式与既有模块回归。
6. 运行全部 PHP 语法检查，核对 `zb_system` 和其他插件未被修改。
7. 更新 `VERSION.md` 与 `CHANGELOG.md`，再提交人工验收。

## 发布注意事项

`plugin.xml` 的版本字段已同步为 `1.1.0`。发布前仍应核对版本、说明、发布日期、兼容性声明和后台登录态人工验收；本轮产品化优化不修改运行时数据结构。
