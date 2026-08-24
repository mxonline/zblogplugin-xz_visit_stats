# xz_visit_stats 开发指南

## 开发方式

`xz_visit_stats` 的主开发方式调整为：**直接在能够访问真实 Git 工作树和终端的 Codex 工作区中完成开发与本机验证**。

不再把“ChatGPT → 本地 Runner → Codex”作为主链路。正常开发时，Codex 直接打开实际插件工作区，读取 `AGENTS.md`、当前分支、真实源码和任务文档，然后在同一个环境中完成代码修改、测试、实机验证、修复、Git 提交与后续 CI。

```text
需求 / PRD
→ Codex 打开真实工作区
→ 读取 AGENTS.md 与当前代码
→ 修改插件
→ 快速自动测试
→ 必要时运行本机 Z-Blog 实机验证
→ 失败则读取真实错误并修复
→ Git diff / commit / push
→ GitHub CI
→ 发布文档与 Release gate
```

## 项目定位

`xz_visit_stats` 是 Z-BlogPHP 访问统计插件，负责前台访问采集和后台统计分析。功能包括访问记录、PV/UV/IP 统计、蜘蛛识别与分析、来源/Referer 分析、SEO 报告、IP 分析、实时访问和日志维护等。

版本、分支和正式发布状态必须以 `plugin.xml`、`docs/VERSION.md`、Git 分支/Tag/Release 的真实状态为准，不在本页写死一个长期会过期的“当前版本”。

## 本机开发环境

当前 Windows 测试环境的典型配置：

```text
Z-Blog 根目录：D:\wwwroot\xinzhao_net
插件目录：      D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats
本地站点：      http://127.0.0.1
PHP CLI：       D:\BtSoft\php\83\php.exe
```

这些值属于开发环境默认值，不应写入插件运行时代码。脚本需要允许通过参数覆盖。

Codex 工作区最好直接打开插件 Git 工作树，且该工作树位于真实 Z-Blog 测试站的 `zb_users/plugin/xz_visit_stats` 中。这样同一个 Codex 终端既能修改真实代码，也能立即访问本地 Z-Blog 做运行时验证。

## 开始一轮开发前

Codex 必须先确认：

1. `git status` 与当前分支。
2. `plugin.xml` 和 `docs/VERSION.md` 的版本状态。
3. 当前任务/PRD 与受影响模块。
4. 相关 Hook、数据表、配置项和升级逻辑。
5. 本地 Z-Blog、PHP、数据库是否需要参与本轮验收。

禁止在未读真实代码的情况下整体重写插件；禁止覆盖人工未提交修改。

## 任务分级

### 快速通道

适用于文案、CSS、小范围显示问题或纯函数修复。通常执行：

```text
读取真实代码
→ 最小修改
→ PHP/JS 相关检查
→ PHPUnit（相关时）
→ git diff --check
→ Commit / CI
```

如果该小修改实际依赖 Z-Blog 运行时行为，则仍需实机验证。

### 标准功能开发

适用于新增统计维度、后台交互、查询逻辑、蜘蛛/来源/IP 分析等：

```text
需求影响分析
→ 实现
→ 快速测试
→ 本机 HTTP / Z-Blog 验证
→ 数据库结果抽查
→ 修复复测
→ Commit / Push / CI
```

### 大版本、数据库与兼容性任务

例如 v2.0、数据库架构调整、迁移、Hook 生命周期变化。必须进行真实本机 Z-Blog 验证，不能只凭 PHPUnit 或 GitHub Actions 宣布完成。

## 代码边界

- 不修改 `zb_system`。
- 不修改无关插件。
- 优先使用 Z-BlogPHP 原生 Hook/API/模板机制。
- 请求采集属于高频路径，避免在每个前台请求中执行昂贵聚合、全表扫描或外部请求。
- 长期增长日志的列表、排行和明细必须有边界、分页或 Top N 限制。
- URL、Referer、UA、IP、蜘蛛名称等数据库内容均视为不可信输入，输出前必须转义。
- 后台写操作必须保留权限和 CSRF 防护。
- 数据库升级必须考虑旧记录和重复执行。

## 主要代码区域

```text
include.php           插件注册、Hook、安装/卸载入口
main.php              后台入口与页面路由
plugin.xml            插件元数据
inc/install.php       数据表、迁移、索引
inc/collector.php     前台访问采集
inc/query.php         访问记录查询
inc/stats.php         通用统计与时间范围
inc/bot.php           蜘蛛识别
inc/source_stats.php  来源/Referer 分析
inc/ip_stats.php      IP 分析
inc/seo_report.php    SEO 报告
assets/               后台 JS/CSS/图表资源
tests/                PHPUnit 与轻量回归测试
scripts/              本机自动验证、打包等开发脚本
```

实际文件结构发生变化时，以仓库为准。

## 自动测试和实机测试

统一执行规范见 `docs/TESTING.md`。

Codex 应优先运行：

```powershell
.\scripts\local-verify.ps1
```

该脚本负责标准化的 PHP 语法、现有 PHPUnit 和本地 HTTP Smoke Test。涉及数据库、Hook、采集、升级等任务时，Codex还必须按 `docs/TESTING.md` 执行对应的实机验收，并记录实际结果。

## 错误处理

测试失败后不询问用户是否修复，普通可逆错误自动进入：

```text
读取真实错误
→ 定位原因
→ 修改
→ 重跑相关测试
```

只有缺少关键凭据、无法访问必须的外部环境、涉及生产数据或不可逆操作时才暂停。

## Git 与 CI

- 一般功能使用任务分支开发。
- 开发前检查工作树，不能覆盖未提交人工修改。
- 通过本轮验收后检查最终 diff，再 Commit / Push。
- CI 失败时读取真实日志，本机修复并复测，再 Push。
- 普通开发无需用户逐项确认 Git 命令。
- 合并、Tag 和 Release 只能在发布门槛满足后进行。

## 发布

正式发布执行 `docs/RELEASE.md`。

README、CHANGELOG、VERSION 和 Release Notes 必须按真实代码和真实验证结果撰写。没跑过的实机测试不能写“已通过”，仍存在的限制不能为了发布文案好看而隐藏。
