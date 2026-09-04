=== Npcink Ad ===
Contributors: muze233
Tags: promotion, advertising, marketing, blocks, announcements
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.3.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create, preview, schedule, and publish site-owned promotions with WordPress blocks and privacy-first delivery.

== Description ==

Npcink Ad is a focused, WordPress-native workflow for announcements, affiliate cards, campaign creative, and other promotions owned by your site.

Create one Promotion with core WordPress blocks, choose where and on which standard posts or pages it may appear, preview the real themed page, and publish or pause it. You do not need to create an ad group, slot, tracking object, or separate settings page.

**Focused publishing workflow**

* Build promotion content with core blocks, including a site-controlled Video block.
* Place it manually, before or after content, after paragraph 1-20, or in a normal-flow top or bottom page bar.
* Target all standard posts and pages, posts, pages, selected content, or posts matching selected core categories and tags. Explicit exclusions always win.
* Choose all devices, desktop (`782px` and above), or mobile (`781px` and below) without branching cached HTML by User-Agent.
* Set an optional start and end time.
* Preview on a real page and read the same server-side eligibility verdict used by live delivery.
* Review current state, placement, content scope, stop time, and inactivity reasons in the Promotion list; pause published or scheduled Promotions and resume drafts without another screen.
* Follow bounded links from a possible-overlap advisory to inspect up to three exact Promotions; the advisory never changes delivery or publication.
* Duplicate an existing Promotion into a current-user draft while preserving only its creative and accepted placement rules; publication state and schedule are reset.
* Catch empty creative, invalid targets, missing paragraph anchors, unsafe video sources, and incorrect schedules before publication.

**Privacy-first by default**

Npcink Ad does not collect impressions, clicks, IP addresses, user agents, referrers, cookies, or visitor identities. It creates no analytics table or event queue and makes no plugin-initiated external API request. A visitor's browser may still request an image or video URL selected by the site operator.

Npcink Ad is not an AdSense manager, arbitrary-code inserter, tracking platform, popup builder, A/B-testing suite, or consent manager. It is deliberately optimized for publishing site-owned promotions through WordPress-native content and controls.

The WordPress.org release supports complete Simplified Chinese language packs for PHP screens and the block editor.

== Installation ==

1. Install and activate Npcink Ad.
2. Open Npcink Ad > Promotions and add a Promotion.
3. Build the creative with WordPress blocks and open Edit delivery settings.
4. Choose placement, content scope, device, and optional schedule.
5. Open Real-page preview directly from the editor sidebar and confirm the runtime verdict; use the Preview settings tab to choose or change its page.
6. Publish the Promotion. Change it to Draft whenever it should be paused.

For Manual block placement, save the Promotion, insert the Npcink Ad Promotion block on the intended page, and select that same Promotion. The `[npcink_ad promotion="ID"]` shortcode is an expert alternative.

== Screenshots ==

1. The Promotion list shows state, placement, content scope, stop time, inactivity reasons, and quick pause or resume actions.
2. Delivery settings use focused tabs for placement, content scope, device and schedule, and real-page preview.
3. Live delivery uses the selected WordPress theme and the same server-side rules shown by Real-page preview.

== Frequently Asked Questions ==

= Does Npcink Ad track visitors? =

No. It does not collect impression or click analytics, set visitor cookies, persist page-bar dismissal, or make a plugin-initiated external API request. The browser requests only creative media selected by the site operator.

= Does Manual block placement insert the Promotion automatically? =

No. Save the Promotion, insert the Npcink Ad Promotion block where it should appear, and select that same Promotion. A missing-block warning is advisory because a template, synced pattern, or shortcode may provide the entrypoint; verify the intended page with Real-page preview.

= How do page caches affect schedules? =

Cached pages must be regenerated after publish, pause, resume, start, and end transitions. Configure the cache TTL or purge affected pages at those boundaries. Npcink Ad warns when WordPress exposes an enabled advanced-cache drop-in, but it cannot guarantee minute-accurate changes through every cache provider.

= What kind of video is supported? =

A site-controlled core Video block can be Promotion creative when it has a root-relative or explicit HTTP(S) source. It uses the same preview, schedule, device, and placement rules. Npcink Ad does not provide VAST/IMA, pre-roll, rewarded video, playback analytics, popup video, or an ad-network player.

= Are page bars sticky or remembered after closing? =

No. Top and bottom page bars remain in normal document flow. The close button hides a bar only for the current page view and does not use cookies or local storage.

== Development ==

The complete human-readable source and build instructions are available at https://github.com/npcink/npcink-ad.

The JavaScript and CSS files in `build/` are generated with Node.js 20 and pnpm 10:

1. Run `pnpm install --frozen-lockfile`.
2. Run `pnpm run build`.

Composer dependencies are development-only quality tools and are not bundled in the release package.

== Changelog ==

= 0.3.5 =

* Harden the release contract for WordPress 7.1 compatibility and dependency auditing.
* Keep the single Promotion model, privacy boundary, and server-side delivery rules unchanged.

= 0.3.4 =

* Package WordPress.org translations through the directory language-pack service instead of the plugin ZIP.
* Let WordPress place the Npcink Ad top-level admin menu after existing menus instead of reserving a high core-menu position.

= 0.3.3 =

* Add a nonce-bound POST action that duplicates one editable Promotion as a current-user draft.
* Copy native block content and an explicit placement-meta allowlist while resetting publication state and schedule.
* Verify partial-write cleanup and preserve the existing delivery, privacy, and no-tracking boundaries.
* Pause a native WordPress-scheduled Promotion from the list before it starts and resume the resulting reviewed draft for immediate publication.
* Make overlap advisories actionable with up to three exact Promotion links and a remaining count, without adding priority or rotation.
* Align page-bar creative and dismissal controls to the active theme's wide content size while keeping the bar background full width.
* Refine the page-bar close control without shrinking its 44 by 44 touch target or changing current-page dismissal.
* Open the real-page preview directly from the editor sidebar while keeping preview-page selection and diagnostics in delivery settings.
* Add a Plugins-screen Settings shortcut and align Promotion stop times with the native Date column.

= 0.3.2 =

* Prepare the concise WordPress.org listing and project-owned directory assets.
* Use WordPress.org language packs for PHP translations while retaining complete Simplified Chinese source catalogs and editor translations.
* Fix the GitHub prerelease workflow repository context without changing the Promotion delivery model.
* Keep the runtime behavior and privacy boundary established in 0.3.1.

= 0.3.1 =

* Improve page-bar touch targets, RTL spacing, wrapping, and the editable announcement pattern.
* Add official Plugin Check, classic/block theme compatibility, release ZIP and SHA-256 artifacts, and gated GitHub prerelease automation.
* Keep the single Promotion data and delivery model unchanged.

See `changelog.txt` for earlier pre-GA development history.
