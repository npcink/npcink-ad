# Npcink Ad

Npcink Ad 0.3.4 是一个 WordPress 原生、隐私优先的站内推广发布工具。核心流程只有一条：**创建推广内容 → 设置展示规则 → 在真实页面预览运行结论 → 发布或暂停**。

> 0.3.4 延续全新的 pre-GA 契约，不提供旧开发数据、API、区块或存储标识的兼容层。

## 产品边界

- 唯一内容类型：`npcink_promotion`。标题、区块内容、草稿/发布状态和全部展示规则都在同一条记录中。
- 规则 meta：位置、canonical content scope、包含/排除内容、Core 分类/标签、设备、开始时间和结束时间，全部通过 typed meta 注册。
- 位置包括手动区块、正文前、正文后、第 1–20 段后（默认第 3 段），以及页面顶部/底部横栏。横栏位于主题标准挂钩的正常文档流，可在当前页面关闭，但不会保存访客状态；手动位置仍需显式插入区块或使用 `[npcink_ad promotion="ID"]` 专家短代码。
- 手动区块的 Promotion 选择器按服务器标题搜索，每页读取 20 条并可继续加载；已保存的 Promotion ID 会独立解析，因此不会因为不在当前搜索页而被误报为删除。
- Gutenberg 的段落位置只计算顶层段落区块，Classic 内容按实际 `<p>` 元素计算；正文不存在目标段落时正式投放不会偷偷改到文末。
- 真实页面预览使用站点主题和同一套 PHP evaluator；预览可以让管理者看见被规则阻止的创意，但运行结论不会伪造为成功。
- 推广列表直接汇总规则状态、位置、content scope、停止时间和不展示原因，并可通过同一受保护操作暂停已发布或尚未开始的原生排程 Promotion、恢复草稿。
- 列表和发布前检查会把“可能同时出现”的提示关联到最多 3 条具体 Promotion，其余只显示数量；这些链接用于人工核验，不代表优先级、胜出或确定冲突。
- 推广列表还提供 nonce 绑定、仅接受 POST 的“复制为草稿”操作，只复制原生区块内容和明确允许的投放规则，并重置发布状态与排期。
- 真正空白的推广列表提供三步起步说明；未完成的 Promotion 编辑器显示由现有发布预检派生的三步进度，不保存新的向导状态。
- 发布或排程前由服务端阻止空内容、无有效目标、非法段落号和错误时间范围；编辑器同时给出即时提示，并对手动区块或段落锚点做所选真实页面的非阻断核验。
- 站点控制的 WordPress Core Video 区块属于同一条 Promotion 创意链路；没有可用的根相对或显式 HTTP(S) 视频源时，编辑器与服务端会阻止发布。插件自身不发送视频 API/追踪请求，但访客浏览器会请求运营者选择的媒体地址。它不是 VAST、贴片、播放统计或弹窗视频系统。
- 正式投放由服务端判断发布状态、标准内容范围和时间；设备可见性使用固定 CSS 断点：desktop 为 `782px` 及以上，mobile 为 `781px` 及以下，all devices 在所有宽度可见，避免全页缓存按 User-Agent 串设备。预览中的 `390px` mobile 画布只是代表宽度，不是正式断点。只有真实渲染横栏的页面会加载小型关闭脚本，正文和手动投放仍不需要前端 JavaScript。
- 管理 REST 需要 `manage_npcink_ads`；插件激活时把该能力授予 administrator 和 editor。
- 默认没有追踪请求、访客 Cookie、自定义表、统计队列或必需的前端 JavaScript。

产品取舍见 [产品契约](docs/product-contract.md)，数据模型见 [ADR 003](docs/decisions/003-single-promotion-record.md)，手动入口与设备边界见 [ADR 008](docs/decisions/008-manual-placement-and-device-guidance.md)，视频素材边界见 [ADR 010](docs/decisions/010-site-controlled-video-creative.md)，横栏边界见 [ADR 011](docs/decisions/011-bounded-page-bar-delivery.md)，安全复制边界见 [ADR 012](docs/decisions/012-safe-promotion-duplication.md)，排程暂停边界见 [ADR 013](docs/decisions/013-pause-scheduled-promotions.md)，模块边界见 [架构总览](docs/architecture-overview.md)，项目演进、经验和纠偏见 [项目历史与开发复盘](docs/PROJECT-HISTORY.zh-CN.md)，真实用户验收方法见 [首次成功投放试用协议](docs/first-promotion-pilot.md)，目录提交前的双门预检见 [WordPress.org 目录审核预检规范](docs/wordpress-org-submission/review-preflight.md)。

0.2.0 建立了 [ADR 005](docs/decisions/005-controlled-delivery-expansion.md) 的受控投放范围：[ADR 006](docs/decisions/006-paragraph-anchor-delivery.md) 的第 N 段后位置、[ADR 007](docs/decisions/007-canonical-editorial-scope.md) 的互斥 content scope，以及 [ADR 008](docs/decisions/008-manual-placement-and-device-guidance.md) 的显式手动入口和固定设备说明。0.2.1 收口手动区块选择器可靠性；0.2.2 收口首次成功投放、编辑器资产边界、缓存风险披露和站点控制视频素材验证；0.3.0 继续使用同一 Promotion 与判定器，增加有边界的正常流页面横栏；0.3.1 收口移动端触控、RTL 间距和横栏公告模板；0.3.2 不改变投放模型，只完成 WordPress.org 目录资料、标准语言包边界和发布契约；0.3.3 增加有边界的“复制为草稿”、原生排程暂停和可操作重叠提示，不改变前台投放模型。

## 明确不做

0.3.4 不包含 AdSense 管理、统计追踪、报表、A/B 测试、CMP、Popup、悬浮/吸顶横栏、频控、地理定向、任意 PHP/JavaScript、模板市场、自定义数据库表或旧管理端 SPA。新增能力必须先证明它能缩短或降低“正确发布一条推广”的成本。

## 开发与验证

要求 PHP 8.1+、Node.js 20+、pnpm 10、Composer 2、WP-CLI 和 GNU gettext。

```bash
composer install
pnpm install --frozen-lockfile

composer check
pnpm check
bash scripts/release-gate.sh
```

贡献代码前请阅读 [CONTRIBUTING.md](CONTRIBUTING.md)。其中定义了当前事实来源、
产品边界、兼容策略和发布产物不可变规则。

仓库维护完整的简体中文（`zh_CN`）PHP 与区块编辑器翻译源。WordPress.org 托管版本通过标准语言包路径加载 PHP 和编辑器翻译；翻译源不进入 WordPress.org 提交 ZIP。新增或修改界面文案后执行：

```bash
composer i18n:refresh
composer i18n:check
```

`i18n:refresh` 会从当前 PHP、区块元数据和生产 JavaScript 重新生成翻译模板，并保留已有中文翻译；如出现新字符串，完整性检查会失败，必须先补齐中文译文。

WordPress Playground 覆盖最低和当前版本：

```bash
WP_VERSION=6.5 PHP_VERSION=8.1 tests/playground/run.sh
WP_VERSION=latest PHP_VERSION=8.5 tests/playground/run.sh
tests/e2e/run-theme-matrix.sh
tests/plugin-check/run.sh
```

Playground 会验证标准 WordPress.org 简体中文语言包路径、单 Promotion 数据模型、typed meta、发布预检、五种 canonical content scope、真实 Core 分类/标签直接关系与动态变更、term fail-closed、时间/设备规则、区块/短代码/自动位置、固定 `781px`/`782px` CSS 边界、区块三属性契约、预览断点说明、顶层区块与 Classic 段落锚点、管理员/订阅者/匿名 REST 边界、绑定 Promotion 的预览 nonce、时区与排期边界、无 Placement/Options/自定义表以及显式卸载清理。打包插件的真实 Gutenberg 编辑器 E2E 另行覆盖选择器、编辑器资产边界，以及从 Add New 到指定页面发布、预览、暂停和恢复的完整后台路径。具体部署的第三方整页缓存仍需逐站定义 TTL 或 purge；[ADR 004](docs/decisions/004-delivery-confidence-and-cache-boundary.md) 记录了 WP Super Cache 的真实边界证据。

## 发布包

```bash
bash scripts/release-gate.sh
```

产物为 `dist/npcink-ad-<Version>.zip` 和 `dist/npcink-ad-<Version>.zip.sha256`，包内顶层目录固定为 `npcink-ad/`。发布门禁要求插件头、`NPCINK_AD_VERSION`、`package.json` 和 readme Stable tag 使用同一版本；由标签触发时，`GITHUB_REF_NAME` 必须为 `v<Version>`。门禁还会校验构建体积、必需文件、禁止内容、SHA-256、官方 Plugin Check 不含 error，以及发布包中不存在旧品牌运行标识。标签工作流在全部验证通过后创建 GitHub prerelease，并同时上传 ZIP 与校验和。

整页缓存环境需要在发布、暂停、恢复、开始和停止边界重新生成受影响页面。0.3.4 继续在检测到 WordPress advanced-cache drop-in 时给出明确提示，但仍要求站点配置相应 TTL 或 purge，不宣称能穿透任意第三方缓存实现分钟级切换。
