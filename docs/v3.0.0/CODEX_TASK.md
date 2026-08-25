# xz_visit_stats v3.0.0 Codex Master Task

状态：**ACTIVE｜重新立项后的正式执行任务**

## 唯一有效基线

- 当前正式代码基线：`v2.0.1`
- PRD：`docs/v3.0.0/PRD-v1.0.md`
- UI：`docs/v3.0.0/UI-SPEC.md`
- 技术设计：`docs/v3.0.0/TECHNICAL-DESIGN-v1.0.md`
- GitHub 主跟踪：Issue #18

上一轮 v3.0.0 / v3.0.1 的开发、PASS、Release 和补丁收口结论全部不继承。旧代码/提交只能作为参考，必须重新对照当前冻结文档验收。

## 本机环境

- 插件工作区：`D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats`
- Z-Blog 根目录：`D:\wwwroot\xinzhao_net`
- 本机站点：`http://127.0.0.1`
- PHP：`D:\BtSoft\php\83\php.exe`
- 正式基线：`v2.0.1`

## 当前分支规则

正式开发分支：`feature/visit-stats-3.0`

当前分支已有 WIP Commit：`457735a6da3f866521009619bdd52fb58205bc49`。

该 Commit **不能直接视为 T1 PASS**。先对照新 PRD / 技术设计重新审查：

- 合格代码可保留；
- 与新 PRD 冲突的代码修正；
- 旧实现存在但没有运行证据的功能不得判 PASS。

## 启动步骤

1. 读取 `AGENTS.md`。
2. `git status`。
3. `git fetch origin`。
4. 确认当前在 `feature/visit-stats-3.0`；若不是，安全切换，不覆盖有效未提交工作。
5. 确认分支基于重新恢复的 v2.0.1 main 基线。
6. 读取：
   - `docs/v3.0.0/PRD-v1.0.md`
   - `docs/v3.0.0/UI-SPEC.md`
   - `docs/v3.0.0/TECHNICAL-DESIGN-v1.0.md`
   - `docs/TESTING.md`
   - `docs/RELEASE.md`
7. 读取真实 v2.0.1 代码和当前 WIP diff。
8. 建立 27 项 P0 实现矩阵，初始状态只允许：`IMPLEMENTED / PARTIAL / MISSING / BLOCKED / NOT VERIFIED`。
9. 开始 V3-T1，不进入 T2 前先完成 T1 验收。

## 用户数据库约束

用户明确要求**不要进行额外数据库管理操作**。

禁止：

- `CREATE DATABASE`
- `DROP DATABASE`
- 要求用户创建测试库
- 要求额外数据库账号授权
- 手工删除真实表/字段/索引
- 清空历史日志

允许：

- 插件正常启用/升级流程自身执行非破坏性、幂等 schema migration；
- 读取当前真实 schema；
- 运行只读检查；
- 对已有残留 v3 schema 做兼容判断；
- 在不破坏历史数据的前提下验证 migration 重复执行。

如果某专项测试必须依赖独立数据库管理权限，标记 `NOT REQUIRED（用户约束）`，不要用临时表冒充独立 DB parity，也不要因此要求用户做额外数据库操作。

## v3.0 P0

必须完整实现 PRD 的 27 项：

1. Dashboard
2. PV/UV/IP 趋势
3. 24 小时
4. 实时分析
5. IP 分析
6. 可信代理/真实 IP
7. 地域与 masked 降级
8. 浏览器
9. OS
10. 设备
11. 页面标题/Z-Blog关联
12. 入口页
13. UTM
14. AI 助手来源
15. AI 爬虫
16. CSV
17. 保存筛选
18. 小时汇总
19. 来源物化
20. Keyset/游标
21. 同比/环比
22. 错误与来源/蜘蛛/AI关联
23. DurationMs 性能
24. v2.0.1→v3 兼容逻辑
25. 可选 Beacon
26. LCP/INP/CLS/TTFB/FCP
27. 屏幕/viewport/语言

## V3-T1｜数据与采集基础

必须完成并验证：

- 读取真实 v2.0.1 schema；
- 真实 IP 信任链与 CIDR；
- SourceType / SourceDomain / UTM / AI 来源物化；
- AI crawler 分类；
- 页面/内容关联基础；
- 小时汇总基础；
- RUM/Beacon 数据结构和 endpoint；
- LCP/INP/CLS/TTFB/FCP、screen、viewport、language；
- migration 幂等；
- 已存在残留 v3 schema 时不报错、不丢历史；
- Beacon 关闭时不加载、不上报。

T1 结束必须输出真实证据，不得只说“代码已写”。

T1 完成后：

```text
Release Gate = NOT READY
RELEASE = NOT RELEASED
```

然后自动进入 T2。

## V3-T2｜统一查询与性能

- raw/hour/day 查询协作；
- 精确 UV/IP；
- 趋势、24小时、实时；
- 页面、来源、UTM、AI、IP、环境、蜘蛛、错误；
- RUM 分位数；
- 同比/环比；
- Keyset/游标；
- CSV 查询边界；
- 保存筛选；
- 10万/100万关键查询可重复性能证据。

不允许为了性能改变统计语义。

## V3-T3｜11 模块与 UI

严格执行 `UI-SPEC.md`。

必须实际做到：

- 1280px+ 真正左侧导航；
- Dashboard 首屏 KPI + ECharts；
- Alpine.js 实际承担核心轻交互；
- 实时轮询；
- 访问记录 Drawer；
- DurationMs / RUM 视觉分离；
- 响应式；
- Loading / Empty / Error；
- 本地 ECharts / Alpine，无公网 CDN；
- CSS xzvs scope；
- cache-buster 正确。

**UI Runtime 必须真实打开本机 Z-Blog 后台验收。**

不能用“文件存在”“JS语法PASS”“ECharts已打包”代替 UI PASS。

如无法真实浏览器验证：

```text
UI Runtime = BLOCKED
Release Gate = BLOCKED
```

禁止提前发布。

## V3-T4｜运行、安全、性能、兼容验收

至少：

- PHP syntax
- JS syntax
- `git diff --check`
- 本机首页/文章/404
- 后台 11 模块 smoke
- trusted proxy 合法/伪造 Header
- IPv4/IPv6/CIDR
- Beacon OFF/ON
- RUM 输入边界
- CSV 权限/公式注入/最大范围
- CSRF / XSS / SQL 输入边界
- migration 重复执行
- 残留 v3 schema 兼容
- 10万/100万关键查询
- 敏感信息扫描

不进行额外数据库管理操作。

## V3-T5｜最终验收与发布

先建立完整 P0 矩阵：

```text
P0-01 ... PASS / BLOCKED
...
P0-27 ... PASS / BLOCKED
```

然后完整 Gate：

```text
[1] Notion Context       BLOCKED（由 ChatGPT Controller补）
[2] Codex Development    PASS / BLOCKED
[3] UI Runtime           PASS / BLOCKED
[4] Local Runtime        PASS / BLOCKED
[5] GitHub CI            PASS / BLOCKED
[6] Release Gate         PASS / BLOCKED
[7] Notion Writeback     BLOCKED（由 ChatGPT Controller补）

FINAL: INCOMPLETE
RELEASE: NOT RELEASED
```

Codex 阶段 Notion 两项由 ChatGPT Controller补，因此在 Controller 补齐前 FINAL 保持 INCOMPLETE。

只有代码、UI Runtime、Local Runtime、CI、Release Gate 全部 PASS 后，才允许：

PR → main → main CI → Release Dry Run → `v3.0.0` Tag → GitHub Release → `xz_visit_stats-v3.0.0.zip` → SHA256。

如果任何必须项 BLOCKED：

- 禁止 Tag
- 禁止 Release
- 禁止正式 ZIP

## 自动执行原则

- 普通可逆操作自动继续；
- 不逐步询问用户；
- 测试失败自动修复并复测；
- CI 失败自动查看原因、修复、push、复测；
- 只有外部授权、不可逆生产风险、重大需求冲突或环境真实阻断才暂停；
- 不得把计划、脚本、文件存在描述成已执行证据。

## 下一步

从当前 `feature/visit-stats-3.0` 开始，先重新审查 `457735a6...` WIP 是否符合新冻结 PRD/技术设计，然后立即完成 V3-T1；T1 PASS 后自动进入 T2，不发布。