# WordPress.org review and release checklist

## Acceptance evidence for `v0.3.5`

- Exact artifact: record the GitHub Release `v0.3.5` asset name and byte size
  after the tag-bound workflow succeeds.
- SHA-256: record the checksum from the same tagged artifact.
- Confirm that the independently computed checksum, GitHub asset digest, and
  downloaded `npcink-ad-0.3.5.zip.sha256` agree.
- Record the tag-bound GitHub Release Gate run URL and tag commit.
- Confirm that the `muze233` account email is current and monitored, and that
  `plugins@wordpress.org` is allowlisted before submission.

Commands rerun against the downloaded artifact:

```sh
git diff --check
composer check
pnpm check
gh release download v0.3.5 --repo npcink/npcink-ad \
  --pattern 'npcink-ad-0.3.5.zip*'
shasum -a 256 -c npcink-ad-0.3.5.zip.sha256
PLUGIN_ZIP=/path/to/accepted/npcink-ad-0.3.5.zip
PLUGIN_ZIP="$PLUGIN_ZIP" bash tests/plugin-check/run.sh
WP_VERSION=6.5 PHP_VERSION=8.1 PLUGIN_ZIP="$PLUGIN_ZIP" \
  tests/playground/run.sh
WP_VERSION=7.1 PHP_VERSION=8.5 PLUGIN_ZIP="$PLUGIN_ZIP" \
  tests/playground/run.sh
WP_VERSION=7.1 PHP_VERSION=8.5 PLUGIN_ZIP="$PLUGIN_ZIP" \
  tests/e2e/run.sh tests/e2e/first-promotion.spec.ts
```

Results: local source checks currently pass (344 PHP tests / 868 assertions and
8 JS suites / 140 tests); Composer audit is clear. The npm audit result remains
pending because the registry audit endpoint timed out. Minimum/current disposable
installs returned `NPCINK_AD_SMOKE_OK`; the tag-bound gate, downloaded-artifact
Plugin Check, and WordPress 7.1 browser upload must be recorded after publication.

## Before uploading the ZIP

- [x] Confirm the final display name and requested slug are `Npcink Ad` and
  `npcink-ad`; a directory URL cannot be renamed after submission.
- [x] Use the `muze233` WordPress.org account and verify that its email is
  current, monitored, and suitable for representing the Npcink brand.
- [x] Whitelist `plugins@wordpress.org` and check spam during review.
- [x] Confirm `Contributors: muze233`, Text Domain `npcink-ad`, WordPress 6.5,
  PHP 8.1, and GitHub source/support links in the submission material.
- [ ] Promote the WordPress.org material through `v0.3.5` instead of modifying
  or reusing the already published `v0.3.4` ZIP.
- [ ] Make the root plugin version, `NPCINK_AD_VERSION`, `package.json`, root
  `readme.txt` Stable tag, and Git tag identical. Verify the future SVN tag only
  after directory approval.
- [x] Copy the candidate `readme.txt` and `changelog.txt` into the plugin root;
  keep the root readme below 10 KiB.
- [x] Confirm runtime code contains no manual `load_plugin_textdomain()` call
  and the packaged Playground smoke proves Simplified Chinese through the
  WordPress.org PHP language-pack path and editor JSON catalogs.
- [ ] Confirm the tag-bound GitHub Release Gate succeeded, then independently
  run Plugin Check against the downloaded Release ZIP; accept no error and
  review every warning rather than hiding it.
- [x] Install the exact generated ZIP through Plugins > Add Plugin > Upload
  Plugin on a clean site; do not test only the source symlink.
- [x] Confirm the ZIP is below the official 10 MB upload limit, contains the
  compiled assets, and contains no tests, Node modules, Composer vendor tree,
  development logs, or source maps.
- [x] Recompute the `.zip.sha256` after downloading the final artifact.
- [ ] Submit the exact final ZIP at <https://wordpress.org/plugins/developers/add/>.

## During review

- [ ] Reply inside the existing review email thread; do not submit a duplicate
  plugin if changes are requested.
- [ ] Treat reviewer requests as release blockers and preserve their exact
  wording in a GitHub issue or pull request.
- [ ] Keep the source repository public and include reproducible build steps.
- [ ] Explain that operator-selected image/video URLs are content resources,
  not an Npcink external service.
- [ ] Do not claim analytics, ad-network compatibility, automatic cache purge,
  legal compliance, or minute-accurate delivery through every page cache.

## After approval

- [ ] Check out `https://plugins.svn.wordpress.org/npcink-ad`.
- [ ] Put plugin files directly in `trunk/`; do not nest them in another
  `npcink-ad/` directory.
- [ ] Copy directory PNGs into top-level `assets/`, next to `trunk/` and
  `tags/`, and set their SVN MIME types to `image/png`.
- [ ] Copy the tested trunk to `tags/0.3.5/`; do not use `Stable tag: trunk`.
- [ ] Confirm the Stable tag points to a real SVN tag whose main PHP Version
  matches exactly.
- [ ] Complete any WordPress.org release-confirmation step and verify the
  public download ZIP before announcing availability.
- [ ] Verify the public icon, banners, screenshot order, captions, install
  button, minimum versions, and Simplified Chinese translation.
- [ ] Enter and review the strings in `translation-copy-zh_CN.md` through the
  WordPress.org translation workflow; do not upload that Markdown file to SVN.
- [ ] Make one test install from the WordPress admin directory search and one
  update-path test on the next patch release.

## Proposed SVN layout

```text
npcink-ad/
├── assets/
│   ├── banner-772x250.png
│   ├── banner-1544x500.png
│   ├── banner-772x250-zh_CN.png
│   ├── banner-1544x500-zh_CN.png
│   ├── icon-128x128.png
│   ├── icon-256x256.png
│   ├── screenshot-1.png
│   ├── screenshot-1-zh_CN.png
│   ├── screenshot-2.png
│   ├── screenshot-2-zh_CN.png
│   ├── screenshot-3.png
│   └── screenshot-3-zh_CN.png
├── trunk/
│   ├── npcink-ad.php
│   ├── readme.txt
│   └── ...
└── tags/
    └── 0.3.5/
        ├── npcink-ad.php
        ├── readme.txt
        └── ...
```
