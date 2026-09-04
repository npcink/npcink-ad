# Npcink Ad Product Contract

## Product identity

- Brand: **Npcink**
- Product: **Npcink Ad**
- Visual wordmark: **npcink ad**
- WordPress slug and text domain: `npcink-ad`
- Positioning: a WordPress-native, privacy-first workflow for publishing on-site promotions
- Promise: create, preview, and publish an on-site promotion without learning an ad-operations platform

The product name stays short. Descriptive copy, rather than the name, explains the workflow. In Chinese product surfaces, use “WordPress 原生站内推广发布工具” and “创建、预览并发布站内推广” where a longer explanation is useful.

## Primary user and job

Npcink Ad is for a WordPress editor or site operator who needs to publish a site-owned promotion, announcement, affiliate card, or campaign creative. It is not designed for a professional ad-operations team managing an ad network.

The primary job is:

> Create a promotion with WordPress content, choose where and when it appears, verify the result on a real page, and publish or pause it.

## Product language

The default interface exposes three concepts:

1. **Promotion** — the content and its publishing state.
2. **Placement** — where, on which standard content, when, and on which devices it may appear.
3. **Preview** — the real-page view and the explanation of whether it will appear.

Implementation details such as slots, variants, resolvers, targeting engines, templates, events, queues, and consent adapters must not become primary navigation or required vocabulary.

## Required workflow

One complete vertical workflow is more important than broad feature coverage:

1. Create a promotion using WordPress blocks, including a site-controlled Core Video block when motion creative is needed.
2. Choose a placement: manual block or shortcode, before content, after content, after paragraph 1–20, or a normal-flow top/bottom page bar.
3. Choose all posts/pages, all posts, all pages, posts matching selected Core categories/tags, or specific published posts/pages; optionally exclude explicit content IDs.
4. Optionally set a start and end time.
5. Choose all devices, desktop, or mobile.
6. Preview on a real page at desktop and mobile widths.
7. Read an explicit runtime verdict explaining why the promotion will or will not appear.
8. Publish, pause, or let the promotion expire.

The default path must not require custom HTML or JavaScript. Expert code insertion, if retained, is opt-in and separated from the primary workflow.

## Usability budgets

The product is accepted only when:

- a new user can publish a valid promotion in no more than three minutes without reading documentation;
- no more than seven primary controls are visible before opening advanced settings;
- the user does not need to create or understand a separate ad group, slot, variant, or tracking object;
- the product explains invalid or inactive delivery before publication;
- the basic inline frontend delivery path adds no tracking request and no required frontend JavaScript. A rendered page bar may load only its current-document dismissal script and stores no visitor state.

## Privacy and data boundaries

Npcink Ad:

- stores its source of truth in WordPress posts and registered typed metadata;
- performs eligibility and rendering on the server;
- does not collect impressions, clicks, IP addresses, user agents, referrers, cookies, user IDs, or page-view events;
- does not create statistics tables, event queues, aggregation cron jobs, or visitor profiles;
- keeps management REST access capability-protected and does not expose unpublished creative anonymously;
- removes only its own documented data during explicit uninstall.

Analytics, A/B testing, frequency controls, consent integrations, and visitor targeting require a new decision record backed by observed user demand and a privacy design.

Site-controlled video is Promotion content, not a second delivery model. [ADR 010](decisions/010-site-controlled-video-creative.md) accepts the Core Video block with a root-relative or explicit HTTP(S) source while keeping VAST, video-ad protocols, playback analytics, popup video, and interstitial delivery outside the core product.

## Explicit non-goals

Npcink Ad is not:

- an AdSense or Google Ad Manager integration;
- a generic arbitrary-code inserter;
- an advertising analytics platform;
- a popup, sticky/floating bar, or background-ad builder;
- an A/B testing, rotation, frequency, geolocation, or click-fraud suite;
- a CMP or consent-detection product;
- a template marketplace;
- a compatibility-report or debugging product.

## How to use the old `master`

The Magick AD `master` branch is a product-research corpus, not an implementation target. A feature may be brought forward only when all of the following are true:

1. it directly improves the required workflow;
2. it can be expressed with the current product language;
3. it does not restore deleted tracking, table, queue, migration, or broad targeting dependencies;
4. its permission, privacy, storage, uninstall, and test contracts are explicit;
5. selective reuse is simpler and safer than a fresh implementation.

Real-page preview, device preview, human-readable runtime verdicts, and the bounded normal-flow bar principles were selectively reconstructed. The old administration SPA, tracking system, statistics dashboard, A/B subsystem, CMP integrations, template marketplace, and compatibility/debug menus are not candidates for restoration.

## Feature admission rule

Every proposed feature must answer both questions:

1. Does it reduce the time or uncertainty involved in correctly publishing a promotion?
2. If mature WordPress plugins already provide it, why must Npcink Ad own it?

If either answer is missing, the feature stays outside the core product.

## 0.2 delivery contract

The runtime remains one bounded Promotion workflow. [ADR 005](decisions/005-controlled-delivery-expansion.md) accepts its controlled 0.2 delivery direction. Automatic delivery is limited to standard posts/pages, management surfaces provide a non-blocking advisory when automatic Promotions may appear together, and a Promotion can be placed after paragraph 1–20 using [ADR 006](decisions/006-paragraph-anchor-delivery.md). [ADR 007](decisions/007-canonical-editorial-scope.md) defines one mutually exclusive automatic content population:

- `all`: all standard posts and pages;
- `posts`: all standard posts;
- `pages`: all standard pages;
- `terms`: standard posts directly matching any configured Core category or tag;
- `selected`: explicitly selected published standard posts/pages.

Explicit ID exclusions always win. Terms are not combined with selected IDs, do not expand category descendants, do not apply to pages, and do not support custom taxonomies, CPTs, term exclusions, or boolean condition groups. A Promotion that must cover both a special page and a term-selected post population is intentionally split into two reviewable Promotions.

For manual placement, [ADR 008](decisions/008-manual-placement-and-device-guidance.md) keeps the explicit block and `[npcink_ad promotion="ID"]` shortcode on the same `block` delivery path. Manual scope is `all | selected`: `all` applies wherever the Promotion is explicitly inserted, `selected` remains an allow-list of published standard posts/pages, and explicit ID exclusions always win. Missing-block inspection is contextual advisory evidence and does not block publication or become an eligibility reason.

The manual block selector is a management-only reliability surface: title search and 20-record pagination run through Core REST/Core Data, while the stored Promotion ID is always resolved independently and retained through loading or request failures. This changes no block attribute, Promotion metadata, eligibility rule, REST schema, or frontend delivery behavior.

Device visibility uses one fixed CSS boundary: mobile at `781px` and below, desktop at `782px` and above, and `all` at every width. There is no tablet target or configurable breakpoint. The mobile preview canvas is capped at `390px` as a representative width, not as the production breakpoint; normal HTML remains cache-stable and does not branch by User-Agent.

Version 0.2.0 established the controlled 0.2 delivery scope. Version 0.2.1 changes only the management-side manual block selector and its packaged-plugin Gutenberg editor E2E coverage. Version 0.2.2 adds first-Promotion guidance, editor asset isolation, complete selected-page browser validation, conservative advanced-cache disclosure, and the explicit site-controlled-video creative contract; it does not add a second data or delivery model. Git tags, GitHub Releases, and public distribution remain explicit repository release actions rather than claims made by this contract.

Version 0.3.0 adds `bar_top` and `bar_bottom` as automatic locations on the same Promotion. [ADR 011](decisions/011-bounded-page-bar-delivery.md) keeps them in normal document flow, reuses every existing rule and preview surface, and permits only current-document dismissal. It adds no container record, sticky/floating behavior, popup, frequency state, tracking, Cookie, or local storage.

Version 0.3.1 keeps that delivery contract unchanged while making the dismiss target touch-sized, reserving its space with RTL-safe logical CSS, and refining the existing compact announcement pattern into wrapping short copy plus a Core Button CTA. It adds no location, visitor state, or separate bar builder.

Version 0.3.2 keeps the product and delivery contract unchanged. It prepares the WordPress.org directory listing and project-owned visual assets, aligns PHP translations with the standard WordPress.org language-pack path, and tightens the release package contract. It adds no runtime feature, data migration, visitor state, or delivery model.

Version 0.3.3 adds [ADR 012](decisions/012-safe-promotion-duplication.md)'s bounded **Duplicate as draft** action and [ADR 013](decisions/013-pause-scheduled-promotions.md)'s native scheduled-Promotion pause transition. Duplication copies native block content and an explicit placement-meta allowlist into an unscheduled draft owned by the current user. Status management additionally permits `future → draft` through the same nonce-bound POST path used for live pause, while Resume remains `draft → publish`. Neither change adds a template system, second record type, frontend runtime, tracking, or visitor state. The accepted `v0.3.2` tag and Release artifact remain immutable and separate from this development line.

Version 0.3.5 is a release-hardening version. It keeps the runtime and privacy contract unchanged while tightening preview publicness checks, uninstall ownership boundaries, dependency auditing, package exclusions, and WordPress.org review evidence. It does not add a new delivery model, data type, tracking path, or Cloud control plane.

The private first-publish marker introduced on the 0.3.3 development line is intentionally not backfilled. At this pre-GA point there are no real user records or public upgrade consumers to preserve, so local development fixtures may be recreated instead of adding an upgrade routine and version option. After the first public installation contract exists, any persistent-data change must define and test its upgrade path explicitly. This decision does not modify or replace the accepted `v0.3.2` artifact.

Page-bar presentation in version 0.3.3 keeps its full-width normal-flow shell while centering creative and dismissal inside the active theme's wide content size, with a 1200px fallback for classic themes. Its visible close surface is smaller than the unchanged 44 by 44 CSS pixel target. This changes no eligibility rule, dismissal state, frontend runtime, tracking, or visitor data.

Multiple eligible Promotions at one automatic location continue rendering in deterministic order, while management UI only advises that they **may** appear together. The list and pre-publish panel expose at most three exact management links plus a remaining count, solely to make the existing advisory inspectable. This direction does not add priority, weights, rotation, tablet targeting, arbitrary selectors or hooks, visitor state, tracking, or separate Slot/Placement records.
