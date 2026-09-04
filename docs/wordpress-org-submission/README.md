# WordPress.org submission pack

This directory prepares the public directory material for Npcink Ad as a
proposed `v0.3.5` release without changing the already published `v0.3.4`
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

Do not replace the readme inside the existing `v0.3.4` ZIP. The `v0.3.5`
candidate is a new release line and its ZIP and checksum must be generated from
the same tagged commit; it must not be renamed as `v0.3.4`.

Promote the final directory copy into the repository root as `v0.3.5`, update
every version contract together, run the complete release gate, publish the
GitHub tag, and submit that exact tagged ZIP to WordPress.org.

Official references:

- <https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/>
- <https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/>
- <https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/>
- <https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/>
