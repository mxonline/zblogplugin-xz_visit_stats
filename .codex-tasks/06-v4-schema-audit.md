# T2 — xz_visit_stats v4.0 真实结构审计与迁移设计

## 目标

在真实 Windows Z-BlogPHP 测试环境完成只读数据库结构审计，并基于真实结果形成 v4.0 增量迁移设计。

本任务只允许“审计 + 设计”，禁止实际执行 v4 数据库迁移。

## 开始前

1. 读取仓库根目录 `AGENTS.md`，严格执行其中的直接工作区、安全边界和完整开发流程要求。
2. 检查 `git status`，不得覆盖或丢弃无关的本地未提交修改。
3. 当前目标分支为 `feature/visit-stats-4.0`。如本地尚未有该分支，在不破坏现有修改的前提下 fetch 并切换到该分支。
4. 读取：
   - `docs/v4.0.0/PRD-v1.0.md`
   - `docs/v4.0.0/GAP-ANALYSIS-v1.0.md`
   - `docs/v4.0.0/SCHEMA-AUDIT-v1.0.md`
   - `scripts/v4-schema-audit.ps1`

## 本机环境

先自动检测实际环境，不要盲目硬编码。当前已知默认值：

```text
Z-Blog root: D:\wwwroot\xinzhao_net
Plugin root: D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats
Local site:  http://127.0.0.1
PHP CLI:     D:\BtSoft\php\83\php.exe
```

实际路径不同时，使用脚本参数覆盖。

## 必须执行

### 1. 运行只读审计

执行：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\v4-schema-audit.ps1
```

若默认路径不匹配，自动定位实际 Z-Blog 根目录和 PHP CLI，再传入 `-ZBlogRoot` / `-PhpPath`。

不得通过登录后台、重新启用插件、运行升级函数或手工 SQL 来代替审计脚本。

### 2. 验证审计安全性

检查生成的 JSON：

- `mode` 必须为 `read-only`；
- `safety.zbp_safe_mode` 必须为 true；
- `safety.plugin_and_theme_loading_disabled` 必须为 true；
- 实际 SQL 只能以 `SELECT`、`SHOW`、`DESCRIBE`、`EXPLAIN` 开头；
- 不得包含数据库密码、Token、Cookie、私钥；
- 不得包含单条 IP、VisitorHash、Referer、URL 等访客级明细；
- 只能包含运行时版本、插件安全配置、表结构、索引和行数。

如发现任何秘密信息或访客级明细：立即停止，不提交该 JSON；修复脚本并重新审计。

### 3. 对比真实结构与代码定义

把真实结果与 `inc/install.php`、当前升级逻辑比较，至少检查：

- 实际表名；
- 实际字段及类型；
- 默认值与 NULL 规则；
- 主键 / 自增；
- 现有索引及列顺序；
- 历史遗留字段或索引；
- 行数和大表风险；
- PHP / Z-Blog / MySQL 或 MariaDB 版本；
- 插件实际版本。

不得用源码定义覆盖真实数据库事实。

### 4. 回写审计报告

更新：

```text
docs/v4.0.0/SCHEMA-AUDIT-v1.0.md
```

写入真实执行时间、运行时版本、表结构摘要、索引、行数、与源码差异、迁移风险和审计安全结论。

审计 JSON 如确认无秘密和访客级数据，可保留在：

```text
docs/v4.0.0/audit-output/
```

### 5. 生成迁移设计

新建：

```text
docs/v4.0.0/MIGRATION-DESIGN-v1.0.md
```

迁移设计至少包含：

- v4 新增表；
- 需要新增的列；
- 需要新增的索引；
- Session / 页面序列 / Event / 汇总 / 目录规则 / 导出任务的落库方案；
- 索引依据与写入成本；
- 幂等检测方法；
- v3 历史数据兼容策略；
- 大表迁移策略；
- 失败停止边界；
- 回滚边界；
- T3 实施顺序。

本任务只生成设计，不执行任何 v4 DDL/DML。

## 检查

完成文档后至少执行：

```text
git diff --check
```

如审计脚本发生修改，还要做 PowerShell 语法检查，并重新执行真实只读审计。

检查最终 diff，不得提交：

- 数据库凭据；
- Token / Cookie / Key；
- 访客级明细；
- 与本任务无关的本地文件；
- 生产数据。

## Git

在 `feature/visit-stats-4.0` 分支提交并 push 本任务结果。不要 merge、tag 或 Release。

## 完成报告

报告必须列出：

- 实际执行的命令；
- 审计 JSON 文件名；
- 实际 PHP / Z-Blog / DB / 插件版本；
- 实际发现的 xz_visit_stats 表与行数；
- 与 `inc/install.php` 的差异；
- 修改 / 新增文件；
- 检查结果；
- commit SHA；
- Release Gate = NOT READY；
- 当前剩余风险。

如果真实 Windows 审计没有成功执行，任务不得标记为完成。