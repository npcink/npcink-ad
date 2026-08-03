# WordPress.org 目录审核预检规范

## 目的与适用范围

本规范用于 Npcink Ad 每次提交 WordPress.org 首次审核、根据审核邮件重新提交，或
准备 SVN 发布前的目录审核预检。它补充而不替代仓库的技术发布门禁。

目录规则和审核员邮件的当前原文优先于本文件；发现冲突时，先按当前规则修正本文件和
自动化检查，再准备下一份制品。

## 两道独立的门

| 门 | 证明什么 | 不证明什么 |
| --- | --- | --- |
| 技术制品门 | 源码、构建、ZIP、校验和、Plugin Check、Playground 和浏览器测试在约定范围内通过 | WordPress.org 已接受、审核通过或公开发布 |
| 目录审核门 | 制品和管理界面符合当前 Directory Guidelines 与审核员的具体要求 | 真实站点采用、业务效果或后续 SVN 发布已经完成 |

`bash scripts/release-gate.sh` 和官方 Plugin Check 是技术制品门的必要证据。即使
Plugin Check 没有 error，也必须完成目录审核门；不得把 PCP 结果表述为“已通过
WordPress.org 审核”。

## 提交前必检项

### 1. 使用唯一、可追溯的制品

1. 每次需要改动已提交或已发布版本时，递增版本并创建新的候选制品；不得覆盖已有
   Git tag、Release ZIP 或校验和。
2. 标签 CI 完成后，从 GitHub Release 下载 ZIP 与 `.sha256`，在下载目录独立运行
   `shasum -a 256 -c <checksum-file>`。
3. 上传 WordPress.org 的必须是这份 CI 制品，不是本地重新压缩的同名 ZIP。
4. 保存标签提交、Release URL、校验和、上传时间和 WordPress.org 回执；这些分别是
   代码、制品和目录状态的证据。

### 2. 审核 ZIP 内容，而不是只审源码目录

运行发布门禁后，检查 ZIP 只有一个 `npcink-ad/` 顶层目录，并且没有：

- `.git`、`.github`、`docs`、`tests`、`node_modules`、`vendor`、源码编辑器入口、
  source map 或构建日志；
- `languages/` 目录。WordPress.org 托管语言包；仓库可以继续保存 POT/PO/MO/JSON
  翻译源，但这些文件不进入提交 ZIP；
- 旧品牌标识、密钥、凭据、调试输出或与运行无关的流程文件。

发布脚本必须把这些约束作为拒绝条件，而不是只依赖人工记忆。

### 3. 前缀与 WordPress 核心例外

逐项检查项目自定义的函数、无命名空间类、常量、option/meta/transient 键、CPT、
shortcode、REST 路由、AJAX action、脚本/样式 handle 和管理页 slug。它们应使用
`npcink_ad` 或 `npcink-ad` 的稳定前缀。

`wp-*` 不能一律改名：它可能是 WordPress 核心公开依赖 handle，例如 `wp-data` 与
`wp-dom-ready`。保留此类核心 handle 前，应确认它不是本插件声明的 handle，并在
审核回复中仅在被询问时作简短说明。不得为了消除扫描提示而破坏核心依赖。

### 4. 后台整合与提示

1. 顶级菜单不是产品曝光位；除非确有必要，使用“设置”或“工具”等合适父菜单。
2. 必须使用顶级菜单时，不固定占用 WordPress 核心菜单的高位；省略 `add_menu_page()`
   的 position 参数，交由 WordPress 在既有菜单之后放置。
3. 后台 notice、upgrade prompt 或 alert 必须与当前页面和当前操作相关、可关闭、数量
   有界；不得在 Dashboard 等无关页面投放营销或升级提示。

### 5. 当前指南与人工复核

每次首次提交或审核重提前，阅读当前版本的：

- [Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)；
- [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)；
- 当前审核邮件及其中链接。

人工复核需记录“已检查/不适用/需修复”及证据位置。自动化扫描结果是候选，不是许可：
对每个警告都要判断为项目缺陷、WordPress 核心例外，或需要向审核员说明的上下文。

## 收到审核邮件后的处理

1. 将邮件原文与 review ID 保存到受控的 issue、PR 或内部记录，避免只靠聊天摘要；
   不公开个人数据或账户凭据。
2. 先完整阅读邮件并在整个代码库搜索同类模式，不能只修邮件列出的单一行。
3. 将每一项归类为代码、打包、元数据/目录资料或需澄清的核心例外；完成后运行完整
   技术制品门和本规范的目录审核门。
4. 用新的版本和新制品上传到原提交页面，随后**回复同一封邮件线程**。回复应简短，
   只说明已处理并已上传；不要用 AI 生成的长篇逐项辩解。
5. 在获得上传回执前，状态只能是“准备重提”；在人工批准、SVN 与公开下载核验前，
   不得表述为“已发布”。

## 自动化维护要求

新增或调整发布流程时，必须同时更新本规范和相应的自动化断言。每次发布前至少
应满足以下断言；尚未自动化的项目必须记录为人工复核，不能假定它已由现有门禁覆盖：

- 版本字段、ZIP 根目录与校验和一致；
- `languages/`、开发文件和已知禁止路径不在 ZIP；
- 官方 Plugin Check 不报告 error；
- 禁止在源码中固定顶级菜单 position 为核心高位；
- 对项目自定义标识执行前缀审查，同时显式允许已确认的 WordPress 核心依赖 handle。

审核员提出新类别的问题后，应在一次修复中将其转化为可重复的预检项；不能只修复
当前版本，然后把同类风险留给下一次提交。

## 状态用语

| 允许表述 | 所需证据 |
| --- | --- |
| 技术候选包已验证 | 本地或 CI 技术制品门记录 |
| 已上传，等待审核 | WordPress.org 上传回执 |
| 审核通过 | 审核员明确批准通知 |
| 已发布 | SVN/公开目录下载与安装路径均已核验 |
