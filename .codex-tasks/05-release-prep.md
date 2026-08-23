# v1.3 第一批改动发布前整理

只有前面回归已通过后才执行。

## 目标
把当前开发成果整理成可提交状态，但默认仍不自动 commit/push。

## 要求
1. 检查版本相关文档：
   - `docs/CHANGELOG.md`
   - `docs/VERSION.md`
   - 必要时 v1.3 开发记录
2. 只记录实际已经完成的内容，不写尚未完成的未来功能。
3. 版本文案必须包含：
   - 筛选区域紧凑化/高级筛选
   - 隐私设置 radio Bug 修复
   - 来源 URL/Referer 展示增强
   - 相关兼容性和测试说明
4. 不虚构测试通过项。
5. 输出建议的 Git commit message。
6. 输出建议的 Notion 同步摘要。
7. 不自动 commit、push、merge 或 tag。

## 最终输出
- 变更摘要
- 已跑测试
- 未覆盖验证
- 建议 commit message
- 建议 Notion 更新内容
