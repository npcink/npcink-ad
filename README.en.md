# Npcink Ad

Npcink Ad 0.3.5 is a WordPress-native, privacy-first workflow for site-owned promotions. It has one primary path: **create creative → set delivery rules → verify a real-page verdict → publish or pause**.

> Version 0.3.5 continues the new pre-GA contract. It provides no compatibility layer for previous development data, APIs, blocks, or storage identifiers.

## Product boundary

- `npcink_promotion` is the only content type. Title, block content, draft/published state, and every delivery rule live on one record.
- Typed metadata stores location, canonical content scope, included/excluded content, Core category/tag IDs, device, start time, end time, and paragraph number.
- Locations include a manual block, before content, after content, after paragraph 1–20 (default paragraph 3), and top/bottom page bars. Page bars use standard theme hooks and remain in normal document flow; dismissal lasts only for the current page view and stores no visitor state. Manual placement does not create an entrypoint automatically: save the Promotion, then insert the Npcink Ad Promotion block and select that same Promotion, or use the existing expert `[npcink_ad promotion="ID"]` shortcode.
- The manual block selector searches Promotion titles on the server, loads real 20-record pages on demand, and resolves the saved Promotion ID independently so its selection is not treated as deleted merely because it is outside the current result page.
- Gutenberg paragraph placement counts only top-level paragraph blocks; Classic content counts actual `<p>` elements. Live delivery renders nothing when the configured paragraph anchor is missing instead of silently moving the Promotion to the end.
- Real-page preview uses the site's theme and the same PHP evaluator as live delivery. Managers may inspect blocked creative, but the verdict remains truthful.
- The Promotion list summarizes rule status, placement, content scope, stop time, and reasons for inactivity. The same protected action pauses published or not-yet-started native scheduled Promotions and resumes drafts.
- List and pre-publish “may appear together” advisories link to at most three exact Promotions and report any remainder as a count. The links support human review; they do not express priority, a winner, or a proven conflict.
- The Promotion list also exposes a nonce-bound, POST-only **Duplicate as draft** action that copies native block content and explicitly accepted delivery rules while resetting publication state and schedule.
- A genuinely empty Promotion list explains the three-step path; incomplete Promotion editors show progress derived from the existing publication preflight without storing wizard state.
- Server preflight rejects empty creative, missing public targets, invalid paragraph numbers, unavailable configured categories or tags, and invalid schedules before publication or scheduling. The editor mirrors those checks and advisory-checks a manual block or paragraph anchor on the selected preview page.
- A site-controlled Core Video block remains creative in the same Promotion workflow. The editor and server require a root-relative or explicit HTTP(S) source. Npcink Ad sends no video API or tracking request, while the visitor's browser naturally requests the operator-selected media URL. This is not a VAST, pre-roll, playback-analytics, or popup-video system.
- Live delivery evaluates publication status, canonical content scope, and schedule on the server. A fixed CSS boundary shows desktop at `782px` and above, mobile at `781px` and below, and all devices at every width, so cached HTML is not split by User-Agent. The preview's `390px` mobile canvas is representative, not the production breakpoint. Only pages that render a page bar load the small dismissal script; manual and in-content delivery require no frontend JavaScript.
- Management REST requires `manage_npcink_ads`; activation grants it to WordPress administrators and editors.
- Default delivery adds no tracking request, visitor cookie, custom table, or statistics queue.

See [the product contract](docs/product-contract.md), [ADR 003](docs/decisions/003-single-promotion-record.md), [ADR 008](docs/decisions/008-manual-placement-and-device-guidance.md), [ADR 010](docs/decisions/010-site-controlled-video-creative.md), [ADR 011](docs/decisions/011-bounded-page-bar-delivery.md), [ADR 012](docs/decisions/012-safe-promotion-duplication.md), [ADR 013](docs/decisions/013-pause-scheduled-promotions.md), [the architecture overview](docs/architecture-overview.md), the [Chinese project history and development retrospective](docs/PROJECT-HISTORY.zh-CN.md), the [first-Promotion user pilot protocol](docs/first-promotion-pilot.md), and the [0.3.2 WordPress.org release-readiness record](docs/0.3.2-wordpress-org-release-readiness.md).

Version 0.2.0 established the controlled delivery scope in [ADR 005](docs/decisions/005-controlled-delivery-expansion.md): [ADR 006](docs/decisions/006-paragraph-anchor-delivery.md) defines after-paragraph placement, [ADR 007](docs/decisions/007-canonical-editorial-scope.md) defines the mutually exclusive editorial scope, and [ADR 008](docs/decisions/008-manual-placement-and-device-guidance.md) defines explicit manual entrypoints and fixed device guidance. Version 0.2.1 closes the manual block selector reliability gap. Version 0.2.2 closes the first-Promotion, editor asset, cache-disclosure, and site-controlled-video validation gaps. Version 0.3.0 adds bounded normal-flow page bars without adding a second delivery system; 0.3.1 closes their mobile touch, RTL spacing, announcement-pattern, and release-readiness gaps; 0.3.2 keeps delivery unchanged while preparing the WordPress.org listing, standard language-pack boundary, and release contract; 0.3.3 adds bounded draft duplication, native scheduled-Promotion pause, and actionable overlap advisories without changing frontend delivery.

## Non-goals

Version 0.3.5 has no AdSense management, analytics, tracking, reports, A/B tests, CMP, popup builder, sticky/floating bar, frequency or geographic targeting, arbitrary PHP/JavaScript, template marketplace, custom database table, or legacy administration SPA.

## Development and verification

Use PHP 8.1+, Node.js 20+, pnpm 10, Composer 2, WP-CLI, and GNU gettext.

```bash
composer install
pnpm install --frozen-lockfile

composer check
pnpm check
bash scripts/release-gate.sh
```

Read [CONTRIBUTING.md](CONTRIBUTING.md) before contributing. It defines the
current sources of truth, product boundary, compatibility policy, and immutable
release-artifact rules.

The repository maintains complete Simplified Chinese (`zh_CN`) sources for PHP and the block editor. WordPress.org-hosted versions load PHP and editor translations through the standard language-pack path; translation sources are not included in the WordPress.org submission ZIP. After adding or changing interface copy, run:

```bash
composer i18n:refresh
composer i18n:check
```

The refresh command extracts the current PHP, block metadata, and production JavaScript strings while preserving existing translations. The completeness check fails until every new string has a Simplified Chinese translation.

Run the minimum and current WordPress Playground matrices:

```bash
WP_VERSION=6.5 PHP_VERSION=8.1 tests/playground/run.sh
WP_VERSION=latest PHP_VERSION=8.5 tests/playground/run.sh
tests/e2e/run-theme-matrix.sh
tests/plugin-check/run.sh
```

The fixture verifies the standard WordPress.org Simplified Chinese language-pack path, the single Promotion model, typed meta, publish preflight, all five canonical content scopes, direct Core category/tag relationships and their dynamic changes, fail-closed behavior for deleted configured terms, time/device rules, block/shortcode/automatic delivery, the fixed `781px`/`782px` CSS boundary, the block's three-attribute contract, preview breakpoint guidance, top-level Gutenberg and Classic paragraph anchors, manager/subscriber/anonymous REST boundaries, promotion-bound preview nonces, timezone and schedule boundaries, absence of Placement/options/custom tables, and explicit uninstall cleanup. Separate packaged-plugin Gutenberg editor E2E covers the selector, editor asset boundary, and the complete Add New through selected-page preview, publish, pause, and resume path. Each deployment must still define a third-party full-page-cache TTL or purge path; [ADR 004](docs/decisions/004-delivery-confidence-and-cache-boundary.md) records real WP Super Cache boundary evidence.

## Release package

```bash
bash scripts/release-gate.sh
```

The artifacts are `dist/npcink-ad-<Version>.zip` and `dist/npcink-ad-<Version>.zip.sha256`, with the fixed ZIP top-level directory `npcink-ad/`. The gate requires the plugin header, `NPCINK_AD_VERSION`, `package.json`, and the readme Stable tag to share that version. In a tag-triggered build, `GITHUB_REF_NAME` must be `v<Version>`. It also verifies bundle budgets, required files, forbidden content, the SHA-256 digest, zero official Plugin Check errors, and the absence of legacy brand identifiers from the package. After all tag checks pass, GitHub Actions creates a prerelease containing both artifacts.

Full-page-cache deployments must regenerate affected pages at publish, pause, resume, start, and stop boundaries. Version 0.3.5 continues to warn when a WordPress advanced-cache drop-in is detected, but sites still need an appropriate TTL or purge path; Npcink Ad does not claim minute-accurate switching through arbitrary third-party caches.
