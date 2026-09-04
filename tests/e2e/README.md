# Packaged-plugin E2E

This suite exercises the packaged plugin in a real Gutenberg editor without
Docker or `wp-env`. The runner starts the fixed WordPress Playground CLI
`3.1.44`, installs `dist/npcink-ad-<Version>.zip` through a bundled Blueprint,
creates an ephemeral English fixture, writes its JSON record inside the
Blueprint runtime, runs Playwright with one worker, and stops the server
afterward. Playground itself keeps its default server-worker setting. Because
Playground workers share the fixture database but not generated files, the
browser resolves the published fixture page through the real same-origin REST
API and reads Promotion IDs from the prebuilt block instead of transferring the
JSON file between workers. A lowest-post-ID election lock ensures that only one
worker builds each shared fixture even when parallel bootstrap races through an
option-based guard.

The fixture contains 105 filler Promotions, a selected Promotion that sorts
outside the initial 20-result page, and a published page containing that
selected dynamic block. It also creates one valid automatic Promotion whose
selected-content rule targets only that page, plus a scheduled Promotion and a
draft with the same potential delivery context. Twenty-five fillers match one
search term; the selector target sorts onto search page 2. The selector test
first highlights an old suggestion and proves that Enter during the 300 ms
pending-search window cannot change the saved ID. It then loads search page 2,
reaches its target using real ArrowDown/Enter input, saves, hard-refreshes, and
verifies restoration. It also checks the REST query shape and requires zero
`console.error` or page errors. Console warnings are retained as browser
diagnostics but do not fail the test.

The editor-asset test protects the split loading boundary: ordinary page
editors load the manual block entrypoint without registering Promotion document
behavior, while `npcink_promotion` editors load and register the dedicated
Promotion entrypoint. It observes top-level WordPress APIs and asset requests
instead of depending on the Gutenberg iframe DOM.

The status-action test confirms the automatic Promotion on the real fixture
page, pauses it from the Promotion list, verifies the success notice, paused row
state, and missing frontend output, then resumes it and verifies the rule-ready
row state and restored frontend output. It also pauses a native
WordPress-scheduled Promotion before its publication date, confirms that the
future record becomes a draft, resumes it for immediate publication, verifies
live delivery, and pauses it again for deterministic cleanup.

The overlap-advisory test confirms that the list links to the exact published
Promotion by title, then opens the draft editor's pre-publish check and verifies
the bounded ID link, new-tab protection, and unchanged non-blocking wording.

The first-run test uses a separate Playground session with no stored
Promotions. It verifies the empty-list guide, enters through **Add first
Promotion**, and checks the editor guide from incomplete rules through the
ready state and its disappearance after publication. Gutenberg title, content,
and saves use only the top-level WordPress data store so the same test remains
independent of the WordPress 7.1 editor iframe. It also verifies the generated
real-page preview as a focused single-scroll workspace, the delivery modal at
1366, 1280, 782, and 390 pixels, category and tag candidate filters that save
only explicit content IDs, live delivery, pause, the persisted post-publish
sidebar state, resume, and cleanup. A full run starts one standard fixture
session and one isolated first-run session.

Build a release ZIP and install Chromium once:

```sh
pnpm run build
bash scripts/release.sh
pnpm exec playwright install chromium
```

Run the minimum and current compatibility rows:

```sh
WP_VERSION=6.5 PHP_VERSION=8.1 pnpm run test:e2e:editor
WP_VERSION=7.1 PHP_VERSION=8.5 pnpm run test:e2e:editor
```

Pass a spec path after `--` when iterating on one flow:

```sh
WP_VERSION=6.5 PHP_VERSION=8.1 pnpm run test:e2e:editor -- tests/e2e/first-promotion.spec.ts
```

`PLUGIN_ZIP` may override the release package inferred from the plugin Version
header. `NPCINK_AD_E2E_PORT` may fix the local port; otherwise the runner asks
the operating system for an available port.

Playground must satisfy the complete fixture, MU-plugin, Promotion REST, and
login-page readiness contract within 120 seconds. Each readiness request is
also capped at three seconds so a blocked Playground worker cannot extend the
deadline by several minutes. Override those budgets with
`NPCINK_AD_E2E_STARTUP_TIMEOUT_SECONDS` and
`NPCINK_AD_E2E_READINESS_REQUEST_TIMEOUT_SECONDS` when diagnosing a slower
machine.

A startup failure exits with status `75`, while Playwright assertions and
configuration errors keep their ordinary nonzero statuses. The theme matrix
uses `run-with-playground-startup-retry.sh` to rebuild an ephemeral Playground
exactly once only for status `75`; it never retries a functional assertion.
Failure logs, the last fixture response, and probe context are retained under
`test-results/playground-startup/` for local inspection and CI artifacts.
