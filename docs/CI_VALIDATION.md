# CI 验证记录

## 开发闭环验证

本项目接入 `xinzhou-code-standard` 代码质量规范。

验证流程：

- GitHub Actions
- PHP 语法检查
- PHPStan
- Semgrep
- PHPUnit

每次代码提交自动执行质量检查。

当前验证目标：确认自动检查流程可正常运行，并作为后续插件开发标准流程。
