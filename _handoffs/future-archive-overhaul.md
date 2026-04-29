# Future work — archive / category / tag layout overhaul

User raised this on 2026-04-29 while looking at the WordPress category archive (image evidence: dense, list-style, peach Ollie default). Deferred to a dedicated ship; do not slip it into another v0.x.y unless explicitly asked.

## What's wrong with the current state

The site has no child-theme override for `archive.html`, `category.html`, or `tag.html` — every blog index, category, and tag page falls back to Ollie's parent template. Result:

- **Squished card layout** — multiple posts cram into a 2-col grid with very little breathing room between rows. Reads dense, not zine.
- **Bad previews** — auto-generated featured images dominate the card; the excerpts get truncated below them with cramped line-height. The post-card feels like a generic blog grid, not the hand-paged feel of the home Recent Posts list.
- **Tags render as plain inline links** — small, no visual identity. Inside single posts, tags render as `is-style-term-button` (filled teal pills). User wants the SAME pill/chip style on archives so the visual language stays consistent.
- **No post-type icons** — the design system has hand-drawn-style SVG glyphs at `assets/svg/icons.svg` (`post-icon-blog`, `post-icon-aside`, `post-icon-image`, `post-icon-gallery`, `post-icon-video`, `post-icon-audio`, `post-icon-chat`, `post-icon-status`, `post-icon-link`, `post-icon-bookmark`, `post-icon-quote`, `post-icon-speaking`, `post-icon-book`, `post-icon-like`, `post-icon-repost`, `post-icon-reply`, `post-icon-event`, `post-icon-review`). The icon-inject machinery in `inc/enqueue.php:174-198` is wired but the archive doesn't use it.
- **Peach background on the archive shell** — Ollie applies its `tertiary` (light orange #fee2c3) to the index/archive wrapper. User wants this gone — should match the home Field Notes / Recent Posts look (printer-ivory `main-accent` + faint paper texture).

## What "good" looks like

Take cues from the existing home Recent Posts treatment that landed in v0.3.x → v0.4.x:

- **Surface**: `main-accent` ivory + the `body.home .has-main-accent-background-color` paper texture rule (fiber + halftone overlay). For dark mode, `night-elevated` per v0.4.17.
- **Per-row rhythm**: date on the left, then the type-specific icon glyph (from `icons.svg`), category chip, post title, excerpt. No featured image dominating — featured images become tape-pinned inserts when present, not the lede.
- **Tags = pill chips** — apply `is-style-term-button` (or define a new `cr-chip-tag` variant in `inc/block-styles.php`) to `core/post-terms` blocks where `term=post_tag` so the archive matches the single-post tag pill row.
- **Categories = `cr-chip` periwinkle** — same as the home Recent Posts loop's chip placement (above title) with `--primary-accent` background.
- **No featured image grid** — replace with a Query Loop variation that renders a `cr-stream-item`-style article element. The pattern already exists in `patterns/cr-stream-item.php`. The archive becomes a Query Loop with that pattern as the post template.

## Suggested approach

1. **Override `archive.html` in `templates/`** — copy Ollie's archive as the starting point, then replace its post grid with a Query Loop using the `cr-stream-item` pattern (or a new pattern that adds the SVG icon via `core/html` or a custom block).
2. **Override `taxonomy-category.html` and `taxonomy-post_tag.html`** — similar shape, but the taxonomy term name + description heading at the top needs the `cr-eyebrow` + `cr-flank` treatment instead of plain h1.
3. **Add a `cr-section--archive` group style** in `inc/block-styles.php` (or augment an existing one) so the archive shell wrapper gets the ivory + paper rules without depending on `body.home`-scoped CSS.
4. **Ship the SVG icon injection on the archive** — the inject machinery exists at `inc/enqueue.php:174-198`. The archive Query Loop pattern just needs to reference the right symbol id. Consider adding a small Interactivity-API-driven block that picks the right icon based on post format.
5. **Tag/category chip style consistency** — register `is-style-cr-chip-tag` for `core/post-terms` in `inc/block-styles.php`, applying the existing `cr-chip` rule. Then the archive's tag list block just opts into that style.

## What NOT to do

- Don't try to style the existing Ollie archive in place via CSS overrides only — Ollie's `archive.html` uses block markup that doesn't surface the right hooks for hand-drawn icons or stream-item layout. A template override is the right move.
- Don't backport this to single posts — single-post tags are already in good shape (`is-style-term-button`). User's complaint is specifically about the archive list views.
- Don't lift v0.2.6.10's home rotation rules into the archive — the rotation is home-specific zine rhythm. Archive should be uniformly ivory + paper.

## Done criteria

- All `/blog/*`, `/category/*`, `/tag/*`, and `/post-format/*` URLs render with the same surface + visual identity as the home Field Notes section.
- Each archive item shows: date → type-icon glyph → category chip (periwinkle) → title (h2) → excerpt (3-line truncate). Tags render as pills below the excerpt, matching single-post tags.
- No peach `tertiary` surface anywhere on archive views.
- Featured image (when present) appears as a tape-pinned secondary element, not the dominant card hero.
- Both light and dark modes ship together — no "we'll fix dark mode in the next ship."
