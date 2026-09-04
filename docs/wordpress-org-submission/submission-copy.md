# Npcink Ad WordPress.org submission copy

## Directory identity

| Field | Copy-ready value |
| --- | --- |
| Plugin name | Npcink Ad |
| Requested slug | `npcink-ad` |
| Proposed first directory release | `0.3.5` |
| WordPress.org owner | `muze233` |
| Author / brand | Npcink |
| License | GPLv2 or later |
| Source | <https://github.com/npcink/npcink-ad> |
| Support | <https://github.com/npcink/npcink-ad/issues> |
| Minimum WordPress | 6.5 |
| Minimum PHP | 8.1 |
| Text domain | `npcink-ad` |

The public WordPress.org plugin API returned `Plugin not found` for
`npcink-ad` on 2026-07-18. This is only an availability check; the Plugins Team
assigns the final slug during review, and the URL cannot be renamed afterward.

## Short description

> Create, preview, schedule, and publish site-owned promotions with WordPress blocks and privacy-first delivery.

The sentence is 110 characters and stays below the WordPress.org 150-character
limit.

## Submission overview

> Npcink Ad is a focused WordPress-native tool for publishing site-owned
> announcements, affiliate cards, campaign creative, and page bars. An editor
> creates one Promotion with core blocks, chooses where and on which standard
> posts or pages it may appear, optionally sets a schedule and device rule,
> verifies the result on a real themed page, and publishes or pauses it.
>
> The plugin uses one custom post type and registered typed post metadata. Live
> eligibility and rendering are server-side. It does not collect impressions,
> clicks, IP addresses, user agents, referrers, or visitor identities; it sets
> no visitor cookies and creates no analytics tables or event queues. It is not
> an ad-network, arbitrary-code insertion, tracking, popup, or A/B-testing
> product.

## Initial review notes

The following English text can accompany the ZIP or be used when replying to a
Plugins Team review email.

### Purpose and scope

Npcink Ad publishes promotions owned by the WordPress site operator. It does
not connect to an advertising network and does not download executable code.
The primary workflow is create content with core blocks, configure bounded
delivery rules, preview the real page, and publish or pause one Promotion.

### External services and visitor data

The plugin itself makes no outgoing HTTP request and has no vendor-controlled
service endpoint. It does not collect or transmit visitor data, set visitor
cookies, write local storage, or persist page-bar dismissal. A rendered image
or video may naturally cause the visitor's browser to request the media URL
selected by the site operator; that URL is content, not an Npcink service.

### Storage and uninstall

The source of truth is the `npcink_promotion` custom post type plus registered
typed post metadata. There are no custom database tables, analytics queues, or
tracking options. Activation grants `manage_npcink_ads` to administrators and
editors. Explicit uninstall deletes the plugin's Promotion posts and metadata
and removes that capability from roles, including multisite sites.

### Permissions and security

Management REST access requires `manage_npcink_ads`. Publication preflight is
performed server-side. Preview requests use a Promotion-bound nonce and do not
expose unpublished creative anonymously. Input is normalized through typed
metadata and bounded enums; rendered content passes through the normal
WordPress content and KSES boundaries.

### Source and build

The complete human-readable source and tagged history are public at
<https://github.com/npcink/npcink-ad>. Production JavaScript and CSS in
`build/` are generated with Node.js 20 and pnpm 10:

```text
pnpm install --frozen-lockfile
pnpm run build
```

Composer packages are development-only quality tools and are not included in
the release ZIP.

### Directory visual assets

The icon and banners are original project-owned artwork created for Npcink Ad;
their editable SVG sources are included in this submission pack and may be
distributed under the plugin's GPLv2-or-later license. They use no third-party
logo, stock artwork, font file, or advertising-network trademark. Screenshots
were captured from a local WordPress test site using synthetic Promotion
content and accounts.

### Validation evidence

The following evidence was refreshed on 2026-07-18 against the unchanged
`npcink-ad-0.3.5.zip` downloaded from GitHub Release `v0.3.5` (final size and
SHA-256 `0ccaacf5452c316184e852180ce7bf9fb7785f36b9db26f59eed0725512e94de`).
The ZIP has not been submitted to WordPress.org.

- official Plugin Check 2.0.0: zero errors and two classified warnings;
- PHPUnit, PHPCS, PHPStan, TypeScript, Jest, ESLint, and Stylelint;
- packaged-plugin checks on WordPress 6.5 / PHP 8.1 and the current WordPress
  release line;
- classic-theme and block-theme coverage;
- Gutenberg editor E2E covering creation, real-page preview, publication,
  pause, resume, and cleanup;
- complete Simplified Chinese PHP and editor translation catalogs.

### Classified Plugin Check warnings

1. `meta_query`: the query is restricted to published Promotion posts, skips
   pagination counts, and primes metadata. No custom table is justified by
   current measured usage.
2. `DONOTCACHEPAGE`: this is the conventional third-party cache constant. It
   is defined only for an authorized real-page preview and only when absent.

## Public support answer

> Npcink Ad is intentionally smaller than a professional ad manager. Please
> include the WordPress version, PHP version, active theme, cache plugin and
> TTL, Promotion placement, and the runtime verdict shown by Real-page preview
> when reporting a delivery problem. Do not post passwords, private preview
> links, or personal visitor data.
