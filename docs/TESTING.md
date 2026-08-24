# xz_visit_stats 测试规范

## 目标

本规范用于让 Codex 在真实工作区中自动完成“代码检查 + 本机 Z-Blog 实机验证”。

测试分为三层：

1. 快速代码检查。
2. 本机 HTTP / PHP 运行时检查。
3. 数据库、Hook、升级与真实采集验证。

只有任务实际需要的层级才执行，但 v2.0、大版本、数据库、Hook、采集链路、后台运行时变化默认必须进入第 3 层。

## 默认环境

当前 Windows 测试环境可使用以下默认值：

```text
Z-Blog 根目录：D:\wwwroot\xinzhao_net
插件目录：      D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats
站点：          http://127.0.0.1
PHP：           D:\BtSoft\php\83\php.exe
```

脚本必须允许参数覆盖，不能把这些开发路径写入插件运行时代码。

## 第一层：快速代码检查

至少检查：

```text
git diff --check
PHP 语法
现有 PHPUnit
JS 语法（仅 JS 发生变化时）
```

推荐入口：

```powershell
.\scripts\local-verify.ps1
```

如只想执行代码层检查：

```powershell
.\scripts\local-verify.ps1 -SkipHttp
```

快速检查失败时，必须先修复再进入实机验证。

## 第二层：本机 HTTP Smoke Test

确认本地 Z-Blog 能够真实响应请求，至少覆盖：

- 首页正常请求；
- 页面型不存在路径；
- 自定义 User-Agent 请求；
- 自定义 Referer 请求。

标准脚本会发送基础请求，但它不能替代数据库断言。

请求示例：

```powershell
Invoke-WebRequest -Uri 'http://127.0.0.1/' -UseBasicParsing
Invoke-WebRequest -Uri 'http://127.0.0.1/__xz_visit_stats_runtime_404__' -UseBasicParsing -SkipHttpErrorCheck
Invoke-WebRequest -Uri 'http://127.0.0.1/' -Headers @{
  'User-Agent' = 'Baiduspider/2.0'
  'Referer' = 'https://www.baidu.com/s?wd=xz_visit_stats_runtime_test'
} -UseBasicParsing
```

PowerShell 5.1 不支持 `-SkipHttpErrorCheck` 时，可以使用 `try/catch` 读取异常响应状态码。

## 第三层：Z-Blog 实机验收

### 插件生命周期

涉及安装/升级/数据库变化时验证：

- 插件首次启用不会出现 Fatal/SQL 错误；
- 已有版本升级后历史访问记录仍可读取；
- 重复执行安装/升级逻辑不会重复建表或重复创建索引导致失败；
- 停用后前台不再采集；
- 再次启用后恢复采集；
- 卸载行为与当前数据保留策略一致。

### 真实访问采集

在执行测试请求前后记录访问日志数量或最新记录，确认：

- 首页/文章等有效页面产生访问记录；
- 页面型 404 记录正确状态码；
- 静态资源请求不产生普通访问日志；
- 后台和插件后台请求不产生普通前台访问日志；
- Referer、URL、UA、状态码和访问时间与测试请求一致。

### 蜘蛛识别

至少验证：

- Googlebot；
- Baiduspider；
- bingbot。

应检查 `vs_IsBot`、蜘蛛名称及相关后台统计，而不是只检查 UA 字符串是否存在。

### 来源与 Referer

使用测试 Referer 生成真实访问后验证：

- 百度搜索来源分类正确；
-搜索词参数按当前规则提取；
- 完整 Referer 在详情中保留；
- HTML 输出经过转义；
- 空 Referer 归类为直接访问；
- 外部域名归类正确。

### 数据库与统计

重要统计指标应至少抽查一次直接数据库结果，例如：

- 指定时间范围记录数；
- 蜘蛛数量；
- 特定状态码数量；
- 特定 Referer/来源数量；
- IP/VisitorHash 的统计结果。

后台 UI 指标与直接数据库聚合不一致时，以数据库和业务定义为基础定位问题，不能只修改前端显示数字。

### 日志检查

本轮运行后检查实际开发环境中的 PHP/Nginx 错误日志。不得出现由本次修改新增的：

- PHP Fatal；
- PHP Warning/Notice（项目约定允许的历史噪音除外，但必须注明）；
- SQL 错误；
- 未处理异常；
- Nginx upstream/PHP FastCGI 错误。

## UI 与后台页面

涉及后台 UI/AJAX 时，还需验证：

- 页面能正常加载；
- 筛选条件提交与分页参数保持；
- AJAX 接口返回合法 JSON；
- 无数据状态正常；
- 长 URL/Referer 不撑破表格；
- 关键字段转义正常；
- 680px、800px、1100px 等典型宽度不出现明显布局破坏。

视觉验收可以使用浏览器截图，但截图不代替数据库或功能断言。

## 性能检查

涉及采集热路径、统计聚合、日志查询时，根据风险执行：

- 检查新增 SQL 是否存在无界全表扫描；
- 检查排序/筛选字段是否有合适索引；
- 避免每个前台请求执行统计聚合；
- 对较大测试数据集测量后台查询耗时；
- 对新索引评估写入成本与磁盘增长。

## 自动修复循环

Codex 遇到测试失败时直接执行：

```text
读取实际失败输出/日志
→ 确认根因
→ 修改代码
→ 重跑最相关测试
→ 再跑本轮必要回归
```

普通可逆修复不等待用户逐次确认。

## 完成条件

任务报告必须区分：

- 已执行并通过；
- 已执行但失败；
- 未执行；
- 当前环境无法覆盖。

对于必须实机验证的任务，只完成 PHP lint/Unit Test 不能标记为“开发完成”或“可发布”。
