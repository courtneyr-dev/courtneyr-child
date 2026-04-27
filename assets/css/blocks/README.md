# Per-block CSS

Each `.css` file in this directory is enqueued only when the corresponding
WordPress block actually renders on the current page. The mapping lives in
`inc/enqueue.php` under `BLOCK_STYLE_MAP`.

## When to add a file here

Add a per-block stylesheet when the block needs:

- A registered style variation from `inc/block-styles.php` (e.g.
  `is-style-cr-tape`, `is-style-cr-torn-paper`)
- Selector chains that `theme.json` cannot express (`:has()`, descendant
  combinators, `[data-*]` attribute selectors)
- Hover, focus-visible, or motion behaviors

## When NOT to add a file here

Skip per-block CSS when the styling can be expressed in `theme.json`:

- Default colors, font sizes, spacing, borders → `theme.json` `styles.blocks`
- Block-specific palette overrides → `theme.json` `settings.blocks`
- Element-level styling (`elements.heading`, `elements.button`) → root
  `styles.elements`

## File naming

`{namespace}-{block-name}.css` — for example `core-quote.css`,
`core-button.css`, `tnp/email-confirmation.css`.

The basename matches the entry in `BLOCK_STYLE_MAP`.

## Adding a new block

1. Create the file: `assets/css/blocks/{namespace}-{block}.css`
2. Add an entry to `BLOCK_STYLE_MAP` in `inc/enqueue.php`:
   ```php
   'core/list' => 'core-list',
   ```
3. The file is automatically loaded when the block appears on the page.

`is_readable()` is checked before each enqueue so missing files fail
silently. You can list a block in `BLOCK_STYLE_MAP` ahead of writing
its CSS file without breaking the site.
