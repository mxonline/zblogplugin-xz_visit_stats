# xz_visit_stats 发布规范

## 发布边界

正式发布默认指：

```text
开发分支完成
→ 本机必要实机验证
→ GitHub CI
→ 发布文档一致性检查
→ Release Dry Run
→ 合并目标分支
→ Tag
→ GitHub Release
→ 正式 ZIP
→ Notion 发布记录
```

默认不包含上传 Z-Blog 应用中心，也不包含自动覆盖线上网站。只有用户明确提出并且当前环境拥有相应授权时，才进入这些外部发布步骤。

## 发布前必须满足

### 代码状态

- 当前目标版本明确；
- 工作树没有无关修改；
- 版本号与目标版本一致；
- 本轮关键测试通过；
- CI 通过或有明确、批准的非阻断说明；
- 不存在密钥、Token、密码、本地数据库配置等误提交。

### 实机状态

如果本版本涉及大版本、数据库、Hook、采集、后台运行时或兼容性变化，必须有真实本机 Z-Blog 验收记录。

没有实机证据时不能用“CI通过”替代“插件已在 Z-Blog 实机通过”。

### 文档状态

正式版本默认维护：

- `plugin.xml`；
- `README.md`；
- `docs/CHANGELOG.md`；
- `docs/VERSION.md`；
- `docs/RELEASE_NOTES_vX.Y.Z.md`；
- 数据库/不兼容变化存在时增加升级或迁移说明。

文档必须采用真实维护者写法，只写真实完成和真实验证的内容。

## Release Gate

每一个“完整开发流程”都必须经过 Release Gate，即使当前只是中间 Phase、并不准备正式发布。

Release Gate 状态只允许：

```text
PASS
NOT READY
BLOCKED
```

定义：

- `PASS`：当前目标版本已满足正式发布前提，Release Dry Run 可以执行或已经通过。
- `NOT READY`：当前是中间 Phase / 功能批次，尚未达到正式版本发布条件。必须写明原因，例如“v2.0 仅完成 Phase 1，后续 Phase 尚未完成”。
- `BLOCKED`：本应进入发布，但存在实机未通过、CI失败、版本冲突、迁移风险、文档不一致、打包错误等阻断项。

`NOT READY` 表示 Release 节点已真实检查，不表示可以省略 Release Gate。

## Release Dry Run

Dry Run 不创建正式 Tag/Release，不覆盖线上文件，不修改生产数据库。

至少核对：

1. `plugin.xml` 插件 ID 和版本号；
2. README 所写当前正式版本；
3. VERSION 当前状态；
4. CHANGELOG 本版本记录；
5. Release Notes 与真实 diff/PR 一致；
6. 数据库迁移、Hook、配置和兼容说明；
7. 本地实机测试记录；
8. GitHub CI；
9. 发布包白名单/排除项；
10. Tag 与 ZIP 名称；
11. Notion 发布记录预检查。

Dry Run 只能给出：

```text
可发布
```

或：

```text
不可发布：<具体阻塞项>
```

任一关键版本信息冲突、必须实机验证但未执行、发布文档与实际代码不符时，直接判定不可发布。

## 正式 ZIP

正式安装包应只包含插件运行所需内容和必要用户文档，不应直接把整个开发仓库打包。

默认排除：

```text
.git/
.github/
.codex/
.codex-tasks/
.codex-state.json
tests/
scripts/
vendor/
开发日志与缓存
本机配置
CI临时文件
```

如某个目录在未来变为运行时依赖，应以真实代码和打包清单为准调整。

建议包名：

```text
xz_visit_stats-vX.Y.Z.zip
```

## 发布文案

GitHub Release 使用 `RELEASE_NOTES` 的精简版，至少说明：

- 这一版解决了什么；
- 主要新增/修复；
- 升级时需要注意什么；
- 兼容性；
- 已知限制；
- 安装包名称。

禁止用“全面升级”“大幅提升稳定性”之类无法由实际 diff/测试证明的宣传式描述。

## Codex 发布职责

当任务明确要求执行完整发布流程、且当前 Codex 工作区具备 Git/GitHub 权限时，Codex可以自动完成普通可逆步骤：

```text
检查状态
→ 更新文档
→ 本地/发布级测试
→ Commit / Push
→ CI 修复循环
→ Release Gate
→ Dry Run（达到发布条件时）
```

正式合并、Tag 和 GitHub Release 只有在所有发布门槛通过后才能执行。生产部署、Z-Blog 应用中心上传和生产数据库操作不属于默认自动发布范围。

## 发布后回写

GitHub Release 真正创建成功后再更新：

- README 当前正式版本；
- VERSION 状态为已发布；
- CHANGELOG 发布状态；
- Release Notes 最终 Tag/Release/ZIP；
- Notion 的版本、Commit、CI、发布日期和发布包记录。

Release 未真实创建时，不得提前写成“已发布”。

## 完整开发流程最终硬门禁

完整流程收口必须输出：

```text
FULL DEVELOPMENT FLOW GATE

[1] Notion Context       PASS / BLOCKED
[2] Codex Development    PASS / BLOCKED
[3] Local Runtime        PASS / NOT REQUIRED / BLOCKED
[4] GitHub CI            PASS / NOT REQUIRED / BLOCKED
[5] Release Gate         PASS / NOT READY / BLOCKED
[6] Notion Writeback     PASS / BLOCKED

FINAL: COMPLETE / INCOMPLETE
RELEASE: RELEASED / NOT RELEASED
```

Release 规则：

- Gate 5 为 `PASS` 只表示达到发布门槛；只有 Tag、GitHub Release、正式 ZIP 都真实创建后，`RELEASE` 才能写 `RELEASED`。
- Gate 5 为 `NOT READY` 时，当前 Phase 可以在其它 Gate 全通过后标记 `FINAL: COMPLETE`，但 `RELEASE` 必须为 `NOT RELEASED`。
- Gate 5 为 `BLOCKED` 时，`FINAL` 必须为 `INCOMPLETE`。
- Notion Writeback 必须记录 Release Gate 的实际结果，不能在 CI 后直接结束流程。
