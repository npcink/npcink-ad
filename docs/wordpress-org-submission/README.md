# WordPress.org submission pack

This directory prepares the public directory material for Npcink Ad as a
proposed `v0.3.2` release without changing the already published `v0.3.1`
artifact or checksum.

## Contents

- `submission-copy.md`: copy-ready facts and English review notes;
- `review-checklist.md`: pre-submission, review, SVN, and release steps;
- `review-preflight.md`: durable directory-review preflight that separates technical package checks from WordPress.org review requirements;
- `readme.txt`: concise WordPress.org listing candidate;
- `changelog.txt`: historical changelog moved out of the listing candidate;
- `translation-copy-zh_CN.md`: copy-ready Simplified Chinese directory text;
- `assets/`: final WordPress.org directory images;
- `source/`: editable, project-owned SVG sources for the icon and banners.

The files in `assets/` belong at the top level of the future WordPress.org SVN
checkout, next to `trunk/` and `tags/`. They do not belong inside the plugin
ZIP. The candidate `readme.txt` and `changelog.txt` belong in `trunk/` and the
release tag after the next repository version is cut.

## Version boundary

Do not replace the readme inside the existing `v0.3.1` ZIP. The published ZIP
has SHA-256
`51acbf8d9c86cc4c5bb7a8eb2c3ec199b7abce2007d9eb3e76d2064d5fc58838`.
The `v0.3.2` work began one workflow-only commit past that tag while the plugin
still identified itself as `0.3.1`, so a newly generated same-name ZIP had a
different checksum and must not be submitted as `v0.3.1`.

Promote the final directory copy into the repository root as `v0.3.2`, update
every version contract together, run the complete release gate, publish the
GitHub tag, and submit that exact tagged ZIP to WordPress.org.

Official references:

- <https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/>
- <https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/>
- <https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/>
- <https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/>
