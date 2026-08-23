# xz_visit_stats 编码规范

## 适用范围

本规范适用于 `xz_visit_stats` 的 PHP、CSS、JavaScript、SQL 片段和数据库字段命名。现有代码以 PHP 函数式插件结构为主，新增代码应与其保持一致。

## PHP 命名规范

### 文件与模块

- 模块文件使用小写蛇形命名：`source_stats.php`、`ip_stats.php`。
- 后台入口保持 `main.php`；通用后台辅助函数放入 `inc/admin.php`。
- 一个分析模块的过滤、聚合、分页和构建函数放在同一个 `inc/*_stats.php` 文件中。

### 函数

- 所有插件函数使用 `xz_visit_stats_` 前缀，避免污染 Z-BlogPHP 全局函数空间。
- 函数名使用小写蛇形：`xz_visit_stats_ip_summary()`、`xz_visit_stats_source_domains()`。
- 函数名称表达职责：
  - `*_filters()`：读取并校验请求参数。
  - `*_where()`、`*_condition()`：构建查询条件。
  - `*_summary()`、`*_trend()`、`*_rows()`：返回某类聚合或列表数据。
  - `*_build()`：组合一个后台视图所需的数据。
  - `*_url()`：生成保留筛选条件的后台链接。
- 公共函数应有稳定输入和返回结构；不要让 HTML 依赖未说明的临时数组键。

### 类

当前插件未使用自定义类。后续确有必要引入类时：

- 类名采用 PascalCase，并以 `XZVisitStats` 为前缀，例如 `XZVisitStatsRetentionService`。
- 一个类只承担一个明确职责，不把后台渲染、采集和数据库访问混合在同一类中。
- 不引入自动加载器或第三方框架，除非与 Z-BlogPHP 插件加载机制兼容并经过评审。

### 变量与常量

- 局部变量使用 lowerCamelCase：`$pageSize`、`$sourceData`、`$lastVisit`。
- 布尔变量使用可读的谓词形式：`$isBot`、`$hasRows`。
- 数组键使用与模块一致的小写蛇形：`status_4xx`、`avg_ms`。
- 阈值集中由 `*_thresholds()` 返回，不将同一业务阈值散落在 SQL 和 HTML 中。
- 常量如未来需要定义，使用全大写下划线，并保持插件专属前缀。

## 数据库字段规范

- 插件表字段统一以 `vs_` 为前缀，字段主体使用 PascalCase：`vs_VisitedAt`、`vs_DurationMs`。
- 主键使用 `vs_ID`，日志主键采用无符号 `BIGINT`。
- Unix 时间字段使用 `vs_VisitedAt`，采用无符号 `BIGINT`。
- 布尔字段使用 `vs_Is*`，例如 `vs_IsBot`。
- 毫秒字段使用 `vs_*Ms`，例如 `vs_DurationMs`。
- 索引名使用小写 `xzvs_` 前缀和下划线：`xzvs_ip_time`。
- 新字段或索引必须先评估实际查询路径和写入成本；不得为文本字段随意添加大索引。

## SQL 与数据访问规范

- 查询逻辑放在 `inc/` 模块，HTML 模板仅消费已准备好的数据。
- 使用 `xz_visit_stats_stats_table()` 取得统计数据表，保留未来汇总表替换接口。
- 分页列表必须有固定排序和 `LIMIT`；页码、页大小、偏移量均转为整数。
- 参数化查询优先；在当前 Z-BlogPHP SQL 构造器或受限 SQL 拼接场景中，文本必须先白名单校验并转义。
- 数据库返回的文本在 HTML 输出前始终调用 `xz_visit_stats_admin_escape()`。

## CSS 命名规范

- 插件 CSS 类以 `xz-` 前缀命名：`xz-metric-card`、`xz-ip-table`。
- 使用小写连字符风格，不使用通用或无前缀选择器污染后台。
- 组件修饰使用语义后缀：`xz-status-4xx`、`xz-source-filter-row`。
- 响应式规则沿用现有断点：1100px 和 680px。
- 新样式仅放入 `assets/admin.css`，不修改 Z-BlogPHP 核心 CSS。

## JavaScript 命名规范

- 前端脚本位于 `assets/`，按页面命名：`overview.js`、`spider.js`、`source.js`。
- 避免依赖公网资源；使用原生 JavaScript 与本地 SVG 实现图表。
- 页面初始化数据以有限的 `window.XZVisitStats*` 命名空间传递，例如 `window.XZVisitStatsSource`。
- JavaScript 变量和函数使用 lowerCamelCase；DOM 类名继续使用 `xz-` 前缀。
- 不将未经转义的服务器文本作为 `innerHTML` 拼接；图表标签使用 `textContent`。

## 格式与注释

- PHP 使用 4 个空格缩进，花括号与现有代码风格一致。
- 复杂 SQL、边界判断和安全相关逻辑可以添加简短注释；注释说明原因，而不是重复代码表面含义。
- 保持单个函数聚焦；出现重复的过滤、时间范围、转义或分页逻辑时优先抽取为辅助函数。
