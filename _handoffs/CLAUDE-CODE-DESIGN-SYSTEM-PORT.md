# Claude Code: Port the courtneyr Design System to WordPress (v0.3.0)

**Repo**: `/Users/crobertson/Projects/courtneyr-child` (deployed to staging via gh-actions; staging repo at `/Users/crobertson/Projects/staging-courtneyr-dev`)
**Target version**: v0.3.0 — Block Patterns + Block Styles for the full courtneyr design system
**Approach**: Use the `ollie-theme` skill where applicable. Use wp-cli (alias `@staging`) for content migration only — registration is PHP/JS in the theme.

## Context links you must read first

1. **Design system source**: `/Users/crobertson/Projects/courtneyr-design-system/` — read `components/components.css`, every file under `ui_kits/courtneyr-dev/`, and `Speaking.html`
2. **This child theme**: `/Users/crobertson/Projects/courtneyr-child/`
3. **Production for content scraping**: https://courtneyr.dev — scrape real copy for pattern placeholders
4. **Staging for verification**: https://qkf.b0d.myftpupload.com (wp-cli alias `@staging`)

## Working state to know

- v0.2.6.10 is the current ship. Latest commit on `main`.
- DB template overrides for `front-page` (id 14491) and `single` (id 11864) WERE deleted; file templates are now active.
- 9 orphan `wp_template` posts still exist (2021–2024); ignore them.
- Active theme: courtneyr-child. Parent: ollie. WP 7.0-RC2, PHP 8.4.
- wp-cli alias `@staging` works from any cwd. `~/.wp-cli/config.yml` and `staging-courtneyr-dev/wp-cli.yml` configured.
- All component CSS already lives in `assets/css/components.css`. Don't re-author it.

## Scope: Phase A + Phase B as one v0.3.0 release

### Phase A — Block Patterns (9 patterns)

Each pattern is a PHP file in `patterns/` that returns the `<!-- wp:... -->` block markup. WP auto-discovers patterns in this directory when registered via `register_block_pattern_category()` in functions.php.

| File | Source | Real content to scrape from courtneyr.dev |
|---|---|---|
| `patterns/cr-hero-display.php` | hero.html "Default" variant | Pull from /about/ or homepage hero copy |
| `patterns/cr-hero-section.php` | hero.html "Section" variant (accent-title) | Pull from /speaking/ index page header |
| `patterns/cr-hero-photo.php` | Current home page layout | Pull from current home page (the pirate-hat photo + "passionate about" copy) |
| `patterns/cr-card-default.php` | cards.html | Pull a recent post intro |
| `patterns/cr-callout-note.php` | cards.html "callout note" | Generic placeholder |
| `patterns/cr-callout-warn.php` | cards.html "callout warn" | Generic placeholder |
| `patterns/cr-pull-quote.php` | cards.html | Pull a quote from a recent blog post |
| `patterns/cr-stream-item.php` | stream.html | Sample with ✦ + category + date + title |
| `patterns/cr-tape-label.php` | tape.html | "NEW" or "FRESH" label |

Pattern category: register `cr-zine` so all appear under "Zine" in the inserter.

### Phase B — Block Styles (extend `inc/block-styles.php`)

Already shipped (v0.2.6.8): `cr-inverse`, `cr-journey`, `cr-notebook`, `cr-tape` for `core/group`.

Add to the same file:

| Block | Style name | CSS class | Source |
|---|---|---|---|
| `core/button` | "CTA (primary)" | `is-style-cr-cta` | buttons.html `.cr-cta` |
| `core/button` | "Secondary" | `is-style-cr-button-secondary` | buttons.html `.cr-button--secondary` |
| `core/button` | "Outline" | `is-style-cr-button-outline` | buttons.html `.cr-button--outline` |
| `core/button` | "Soft" | `is-style-cr-button-soft` | buttons.html `.cr-button--soft` |
| `core/separator` | "Marker bar" | `is-style-cr-marker-bar` | tape.html `.cr-marker-bar` |
| `core/separator` | "Marker bar (short)" | `is-style-cr-marker-bar-short` | tape.html `.cr-marker-bar--short` |
| `core/group` | "Card" | `is-style-cr-card` | cards.html `.cr-card` |
| `core/group` | "Halftone" | `is-style-cr-halftone` | tape.html `.cr-halftone` |
| `core/quote` | "Pull quote" | `is-style-cr-pull-quote` | cards.html `.cr-pull-quote` |

For each new style: ensure `assets/css/components.css` has a corresponding `.is-style-cr-{name}` selector that maps to the existing `.cr-{name}` rule. If missing, add it. Do NOT duplicate the rules — use selector-list extension: `.cr-cta, .is-style-cr-cta { ... }`.

### Out of scope for v0.3.0

- Inline format toolbar buttons (`.cr-highlight`, `.cr-sparkle`) — Phase C, separate ship, needs JS bundle
- Custom chip block — Phase D, separate ship, overlaps with Post Formats plugin work
- Stream as a query block variation — Phase E, future

## Required PHP/file structure

```
courtneyr-child/
├── functions.php                          # add: load patterns dir + block styles
├── inc/
│   ├── block-styles.php                   # extend existing — add 9 styles
│   ├── block-patterns.php                 # NEW — register pattern category, auto-load patterns/
│   └── ... (existing files)
├── patterns/                              # NEW directory
│   ├── cr-hero-display.php
│   ├── cr-hero-section.php
│   ├── cr-hero-photo.php
│   ├── cr-card-default.php
│   ├── cr-callout-note.php
│   ├── cr-callout-warn.php
│   ├── cr-pull-quote.php
│   ├── cr-stream-item.php
│   └── cr-tape-label.php
└── assets/css/components.css              # add .is-style-cr-* selectors for new styles
```

Each pattern file has the standard WP pattern header:

```php
<?php
/**
 * Title: Hero — Display
 * Slug: courtneyr-child/cr-hero-display
 * Categories: cr-zine
 * Description: Display-heading hero with Rock Salt brand voice.
 * Keywords: hero, zine, display, courtney
 * Block Types: core/post-content
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"className":"cr-hero","layout":{"type":"constrained"}} -->
... block markup ...
<!-- /wp:group -->
```

## Content scraping — use real copy from courtneyr.dev

Use `wp @staging post list` and `wp @staging post get` to get content from the live staging copy of courtneyr.dev (it's a clone). Or use curl + html2text. Examples:

```bash
# Find a recent post for pull-quote source
wp @staging post list --post_type=post --post_status=publish --posts_per_page=10 --fields=ID,post_title 2>/dev/null

# Get its content
wp @staging post get <ID> --field=post_content 2>/dev/null

# Pull the home page hero copy
wp @staging post get 2651 --field=post_content 2>/dev/null
```

The 2>/dev/null is required — wp-cli phar emits a PHP deprecation warning to stderr that's harmless but noisy.

DO NOT invent content. Pull real text from real posts. The user's voice is specific (no em dashes, no Automattic mentions, conversational tone).

## ollie-theme skill

Ollie is the parent. The `ollie-theme` skill (in `/mnt/skills/user/ollie-theme/SKILL.md` or your local equivalent) covers Ollie's design system, validation rules, and recommended patterns. Read it before:
- Choosing spacing tokens (Ollie defines verbose names like `xx-large` AND short names like `2xl`; both work)
- Picking color slugs (Ollie's parent palette merges with our 25-color child palette)
- Building cover/group structures (Ollie has opinions on layout primitives)

Apply ollie-theme guidance for any block markup decisions.

## Verification protocol per pattern

For each pattern shipped:

1. **Visual**: Insert it in a test page via Site Editor; confirm it renders correctly
2. **Content fidelity**: Real copy from courtneyr.dev present in default placeholder
3. **Editor**: Hit "Replace" / "Edit" on each block — confirm it's editable, not locked
4. **Frontend**: Hard refresh; check both light and dark mode
5. **wp-cli check**: `curl -s https://qkf.b0d.myftpupload.com/<test-page>/ | grep -c '<class>'`

For each block style:

1. Insert the parent block (Group / Button / Separator / Quote)
2. Sidebar → Styles panel → confirm style appears
3. Click it → confirm CSS applies
4. Check both modes

## Gotchas list (things we already learned the hard way)

- **Don't use `:first-of-type`** for hiding the page H1 in homepage post-content — it matches per parent, not globally. Use direct child combinators (`>`).
- **Spectra icon blocks** (`.wp-block-outermost-icon-block`) have inline `width:3.3em` you must beat with longer selector chain + `!important`.
- **Hashed `.wp-elements-{hash}` classes** are auto-generated by WP for per-block element styles. Never write CSS targeting them directly. Use block styles or custom classes.
- **Palette tokens (cr-tertiary, cr-main-accent, etc.) DO NOT FLIP in dark mode by design.** Only semantic tokens (cr-ink, cr-surface, cr-border) flip. Anywhere a palette `has-*-background-color` class appears, you must EITHER explicitly handle dark-mode color OR convert it to a semantic token.
- **DB-stored wp_template posts override file templates silently.** Always run `wp @staging post list --post_type=wp_template --fields=ID,post_name,post_modified` before assuming a file change will render.
- **Newsletter form (TNP shortcode) renders inside a wp-block-shortcode wrapper.** Make form wrappers transparent on parent sections so bg colors show through.
- **wp-cli phar emits deprecation warnings on PHP 8.4.** Always `2>/dev/null` when capturing output.
- **The deploy user `git_deployer_*`** can't run wp-cli only because GoDaddy MWP routes it through git. The actual SSH user works fine, which is what we already configured.
- **Cache layers**: Sucuri WAF + GoDaddy gateway + Object Cache Pro. Cache-bust with `?cb=$(uuidgen)` query string for verification.
- **`is-style-logos-only` social icons**: each li gets `has-{slug}-color` PLUS inline `style="color:#hex"`. SVGs need `fill="currentColor"` for color to flow through. Already fixed in `inc/social-services.php` for the 4 custom services (Readwise, BoardGameGeek, Snipd, OpenProfile).

## Ship procedure (per phase, or all at once)

```bash
# 1. Commit theme changes
cd /Users/crobertson/Projects/courtneyr-child
git add -A
git -c user.email=courtney@courtneyr.dev -c user.name=courtney commit -m "v0.3.0: design system Phase A patterns + Phase B block styles"
git push origin main

# 2. Bump submodule on staging
cd /Users/crobertson/Projects/staging-courtneyr-dev/themes/courtneyr-child
git pull origin main
cd /Users/crobertson/Projects/staging-courtneyr-dev
git add themes/courtneyr-child
git -c user.email=courtney@courtneyr.dev -c user.name=courtney commit -m "Bump child theme: v0.3.0"
git push origin main

# 3. Wait for GitHub Actions deploy (~2 min)
sleep 110
gh run list --limit 1

# 4. Verify
curl -s 'https://qkf.b0d.myftpupload.com/wp-content/themes/courtneyr-child/inc/block-patterns.php?cb=verify' | head -5
wp @staging cache flush 2>/dev/null
```

## Success criteria (definition of done)

- [ ] All 9 patterns appear in editor's Pattern Inserter under "Zine" category
- [ ] All 9 default to real courtneyr.dev content, not Lorem
- [ ] All 9 block styles appear in their respective block's Styles sidebar panel
- [ ] Each style applies the expected CSS visually (test in both light and dark)
- [ ] No console errors in browser devtools
- [ ] No PHP errors in WP_DEBUG log on staging
- [ ] User can rebuild homepage hero by inserting `cr-hero-photo` pattern instead of nesting groups manually
- [ ] Hashed `.wp-elements-{hash}` overrides removed from any user CSS once block styles are in place
- [ ] Documentation: `_handoffs/v0.3.0-design-system-port.md` written, listing every pattern, style, and any decision deviations from this brief

## After v0.3.0 ships — content migration via wp-cli (optional aggressive path)

Once patterns/styles are registered, the user MAY want to migrate the existing home page to use them:

```bash
# Backup
wp @staging post get 2651 --field=post_content > ~/Desktop/home-content-pre-migration.html 2>/dev/null

# Build new content using patterns (write to /tmp/new-home.html with assembled <!-- wp:pattern --> calls)
# Then:
wp @staging post update 2651 --post_content="$(cat /tmp/new-home.html)" 2>/dev/null

# Verify
curl -s "https://qkf.b0d.myftpupload.com/?cb=$(uuidgen)" | grep -c 'cr-hero-photo'
```

This is OPTIONAL and should be done as a separate ship after v0.3.0 patterns/styles are validated. Don't bundle.

## End — start with Phase A patterns. Read the design system kit pages first. Use real courtneyr.dev content.
