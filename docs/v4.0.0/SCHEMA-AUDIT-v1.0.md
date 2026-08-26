# xz_visit_stats v4.0 — 本地结构审计 v1.0

## 状态

T2 当前状态：进行中。

51.LA 对标需求和 v3 → v4 缺口已经完成，当前必须在真实 Windows Z-BlogPHP 测试环境读取实际数据库结构，再进入迁移设计。

不得用仓库里的 `inc/install.php` 定义代替真实数据库审计结果。

## 审计目标

只读取并记录：

- Z-BlogPHP 版本
- PHP CLI 版本
- 数据库类型 / 驱动 / 服务端版本
- xz_visit_stats 插件版本
- 安全配置项
- 所有 xz_visit_stats 相关表
- 每张表的列、类型、默认值、Null、Extra
- 每张表的索引定义
- 每张表的行数

不读取或导出：

- 单条访客记录
- IP 明细
- VisitorHash 明细
- Referer 明细
- Cookie
- 数据库密码
- Token / Key

## 审计脚本

脚本：

```text
scripts/v4-schema-audit.ps1
```

默认本地环境：

```text
Z-Blog root: D:\wwwroot\xinzhao_net
Plugin root: D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats
PHP CLI:    D:\BtSoft\php\83\php.exe
```

这些只是测试环境默认值，脚本参数允许覆盖。

## 安全设计

审计脚本在加载 Z-Blog bootstrap 前设置：

```text
ZBP_SAFEMODE = true
```

这会阻止主题和插件 include / ActivePlugin 流程运行，避免触发 xz_visit_stats 的升级、采集、自动清理等逻辑。

脚本内部还有第二层 SQL 门禁，只允许：

```text
SELECT
SHOW
DESCRIBE
EXPLAIN
```

任何其他 SQL 前缀都会被脚本直接拒绝。

## 本地执行

在真实插件工作区切到 `feature/visit-stats-4.0` 后，由 Codex 自动执行：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\v4-schema-audit.ps1
```

如实际环境路径不同，Codex应先自动检测，再使用参数覆盖，不要求用户手工改脚本。

示例：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\v4-schema-audit.ps1 `
  -ZBlogRoot 'D:\wwwroot\xinzhao_net' `
  -PhpPath 'D:\BtSoft\php\83\php.exe'
```

## 输出

默认输出：

```text
docs/v4.0.0/audit-output/schema-audit-YYYYMMDD-HHMMSS.json
```

JSON 应包含：

- `runtime`
- `safe_plugin_config`
- `tables`
- `safety`

其中 `safety` 需要明确记录 Safe Mode、允许的 SQL 类型、实际执行的只读查询数量，以及没有导出访客级数据和秘密信息。

## T2 验收标准

- [ ] 已在真实 Windows Z-BlogPHP 环境执行审计脚本。
- [ ] 已记录真实 PHP / Z-Blog / 数据库 / 插件版本。
- [ ] 已记录 xz_visit_stats 相关表的真实列定义。
- [ ] 已记录真实索引。
- [ ] 已记录真实行数。
- [ ] 审计过程没有执行 DDL / DML。
- [ ] 审计 JSON 已由 Codex检查，不含密码、Token、Cookie 或访客级明细。
- [ ] 已把审计结论写回本文件。
- [ ] 已基于真实结果形成 v4 增量迁移设计。

## 当前未完成项

当前 ChatGPT / GitHub 控制侧已经准备好 v4 分支、PRD、缺口分析和只读审计脚本，但尚未获得真实 Windows Z-Blog 运行时输出。

在真实 JSON 产生前：

- 不锁定最终 v4 表结构；
- 不执行迁移；
- 不把 T2 标记为完成；
- 不进入“已通过本地实机验证”状态。

## T2 完成后的直接下一步

读取真实审计 JSON，形成：

```text
docs/v4.0.0/MIGRATION-DESIGN-v1.0.md
```

迁移设计至少应确定：

- 新增表
- 新增列
- 新增索引
- 幂等检测
- 大表现状和风险
- 旧数据兼容策略
- 回滚 / 停止边界
- T3 会话、页面序列、事件和汇总层的实际落库方案
