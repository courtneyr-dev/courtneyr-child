# Patterns

Block patterns for the courtneyr.dev block theme. WordPress auto-discovers
any `.php` file in this directory.

## Pattern types

### Post format patterns

One per IndieWeb / WordPress post type. Used as quick-start scaffolding
when authoring a new post of that type.

Naming: `post-format-{type}.php` (e.g. `post-format-quote.php`,
`post-format-bookmark.php`, `post-format-speaking.php`).

Each post-format pattern uses the appropriate chip + body structure
from the design system and registers under the `cr-post-format`
category.

### Zine layout patterns

Compositional patterns: collage layouts, masking-tape callouts,
torn-paper pulls, hard-shadow cards. Drop into pages for visual
variety.

Naming: `zine-{layout}.php` (e.g. `zine-collage.php`,
`zine-tape-callout.php`).

Registered under the `cr-zine` category.

### IndieWeb patterns

Patterns specific to IndieWeb building blocks (h-card, h-event,
h-review, webmention display, etc.).

Naming: `indieweb-{thing}.php`.

Registered under the `cr-indieweb` category.

## Pattern overrides (WP 6.6+)

The quote pattern shows the override convention: a block declares
`metadata.bindings` pointing at post meta keys, so the same pattern
on different posts can carry different content. Any pattern that
exposes editable text or media should use bindings rather than
hardcoded content.

## Categories

The three category slugs are registered in `inc/block-patterns.php`.
Add new ones there before referencing them in pattern headers.
