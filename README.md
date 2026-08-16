# courtneyr-child

Block theme for courtneyr.dev. Zine aesthetic with printer-ivory surface,
hard shadows, light rotation. Built on the courtneyr.dev design system
and forward-compatible with WordPress 7.

**Status:** v0.1.0 scaffold. Architecture is in place; templates and
patterns will be filled in incrementally as features land.

## Architecture

```
courtneyr-child/
├── style.css              ← Theme header. Declares Ollie as the parent.
├── theme.json             ← Schema v3. Palette, type scale, spacing,
│                            shadow presets, post-type custom templates,
│                            font face declarations for self-hosted woff2.
├── functions.php          ← Entry point. Requires inc/*.php only.
├── inc/
│   ├── theme-supports.php ← add_theme_support() calls (post-formats,
│   │                        custom-logo, html5, etc.)
│   ├── enqueue.php        ← Per-block CSS via wp_enqueue_block_style.
│   │                        BLOCK_STYLE_MAP controls the registry.
│   ├── block-styles.php   ← register_block_style() variations
│   │                        (cr-tape, cr-highlight, cr-rotate-*, ...)
│   ├── block-patterns.php ← Pattern category registration.
│   └── interactivity.php  ← Theme toggle via the Interactivity API +
│                            inline pre-paint script for flash prevention.
├── styles/
│   └── dark.json          ← Dark mode style variation. WP-native; users
│                            choose it in Site Editor. Co-exists with the
│                            JS toggle which overrides per-visitor.
├── assets/
│   ├── css/
│   │   ├── tokens.css     ← Authoritative production CSS (see Design system sync).
│   │   ├── components.css ← Authoritative production CSS (see Design system sync).
│   │   ├── blocks/        ← Per-block CSS (loaded only when block renders)
│   │   └── interactivity/ ← Theme toggle button styling.
│   ├── fonts/             ← Self-hosted woff2 files (see fonts/README.md).
│   └── js/
│       └── theme-toggle/
│           └── view.js    ← Interactivity API store.
├── patterns/              ← Block patterns (post-format, zine, indieweb).
├── parts/                 ← Template part overrides (inherits from Ollie).
├── templates/             ← Custom templates for the 18 post types.
└── .github/
    └── workflows/
        └── deploy.yml.example  ← GoDaddy CI/CD template.
```

## Design system sync — SUSPENDED

**This theme's `assets/css/tokens.css` and `assets/css/components.css` are
the authoritative production CSS.** They began as copies from
`courtneyr-design-system`, but that repository has not moved since
2026-04-27 while these files carried months of theme releases (as of
v0.5.200: 8,037 lines in components.css vs 659 upstream).

**Do not overwrite these files from the design-system repository.** The
old copy commands previously documented here would erase that work,
including the reduced-motion View Transition rule in tokens.css §13,
which must be preserved.

A future reverse-sync (theme → design system) or source-of-truth
reconciliation is separate work, tracked outside this repo.

## WordPress 7 readiness

This scaffold uses every API that WordPress 7 is expected to ship as
default behavior:

- **theme.json schema v3** with `appearanceTools: true` and
  `useRootPaddingAwareAlignments: true`
- **Style variations** as separate `/styles/*.json` files
- **Block style variations** registered via `register_block_style()`
- **Per-block CSS** via `wp_enqueue_block_style()` (only loads when the
  block is on the page)
- **Self-hosted fonts** declared via `fontFamilies[].fontFace[]` so no
  Google Fonts CDN is touched
- **Interactivity API** (`@wordpress/interactivity`) as a script module
  for the theme toggle, with inline pre-paint to prevent FOUC
- **Pattern overrides** (`metadata.bindings`) supported in patterns
- **Custom templates** declared in theme.json for the 18 post types
- **Modern PHP**: `declare(strict_types=1)`, prefix-namespaced helpers,
  no globals

## Local development

```bash
# Mount as a child theme of Ollie inside any WordPress install:
ln -s "$(pwd)" /path/to/wp-content/themes/courtneyr-child

# In wp-admin: Appearance → Themes → activate Courtneyr Child.
```

The theme expects Ollie to be present as the parent. Ollie Pro is
optional (its patterns are usable but not required).

## CI/CD

`deploy.yml.example` matches the gd-wordpress-deployer pattern used
for `courtneyr-dev-site`. Rename to `deploy.yml`, adjust secrets, and
push. The workflow validates `theme.json` + every `styles/*.json` and
PHP-lints all `.php` files before deploying.

## Roadmap

**v0.1** (this scaffold): theme.json, style variation, Interactivity
API toggle, baseline enqueue, one example pattern.

**v0.2**: Header and footer template parts, IndieWeb h-card sidebar,
zine post-meta layout, theme toggle integrated into header.

**v0.3**: All 18 post-type templates filled in. One pattern per
post format.

**v0.4**: Per-block CSS split out from components.css for full
performance benefit.

**v1.0**: Validated against `@courtneyr-dev/wp-design-bridge`. Output
of converter matches Path B (this theme) for the same brand.json input.
