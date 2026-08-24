# xz_visit_stats v1.3 自动开发脚本（按当前进度续接）

这版不是从 v1.2 重新开始，而是从你截图中的已完成状态继续。

## 流程总控

本项目后续插件开发、修复、优化和发布准备统一遵循“Z-Blog 插件完整开发流程 v2.0”。本脚本只负责本地任务续接，不替代 Notion 项目状态、GitHub 真实分支/Commit/CI 和发布验收记录。

## 已视为完成
- 5 个页面常用筛选 + 高级筛选
- 高级筛选展开/收起
- 隐私设置 radio Bug 根因修复
- `full` / `masked` 采集逻辑保持原样
- `git diff --check` 已通过

## 新任务队列
1. 验证当前 v1.3 已完成改动
2. 来源 URL / Referer 识别与悬停详情
3. 来源分析页面轻量 UI 收口
4. 第一批快速回归
5. 发布前文档整理

## 安装
把压缩包内所有文件复制到 `xz_visit_stats` Git 仓库根目录，与 `main.php` 同级。

## 第一次运行

```powershell
codex --version
.\dev-v1.3.ps1 status
.\dev-v1.3.ps1 next
```

以后不需要再复制长提示词。

## 每个任务完成后

本地需要人工验证时先验证，再执行：

```powershell
.\dev-v1.3.ps1 approve
.\dev-v1.3.ps1 next
```

## 查看状态

```powershell
.\dev-v1.3.ps1 status
.\dev-v1.3.ps1 list
.\dev-v1.3.ps1 show
```

## 单独运行指定任务

```powershell
.\dev-v1.3.ps1 run -Task "referer-url-hover"
```

## 安全策略
- 强制要求当前分支为 `feature/visit-stats-1.3`
- 默认不 commit
- 默认不 push
- 默认不 merge
- 默认不建新分支
- PHPStan 不作为阻断条件
- 只运行与当前改动相关的快速检查

## 当前最重要的一点
隐私设置 Bug 已经修复，不要再让 Codex重复开发。新的第一个任务只负责验证当前基线，然后直接进入 Referer 来源增强。
