# Read single wide column: browser test fixture

`tests/browser/read-single-wide-column.mjs` renders real pages, not a rebuild. The pages come from a local WordPress Studio site
(WordPress 7.1.5, theme mounted from a git export, Post Kinds for IndieWeb 1.10.3 at 92ef8ada), so the layout CSS in them is what
WordPress generated: `is-layout-constrained`, the `wp-container-*` rules in `core-block-supports-inline-css`, the global
`.is-layout-constrained > .alignwide` rule.

1. Mount the tree under test as the site's `courtneyr-child` theme. Seed: `studio wp eval-file seed-read-single.php` (no pictures, no network; remove with `... remove`). Seed output lists each post's URL.
2. `node capture-pages.mjs <base|head> <urls.tsv> <outdir>` with a tab-separated `name<TAB>url` file. It saves each page's served HTML and every same-origin asset into `<outdir>/<tree>/pages` and `<outdir>/<tree>/assets`. Capture `base` (origin/main) and `head` (this branch) into the same `<outdir>`.
3. `CR_READ_FIXTURE=<outdir> node tests/browser/read-single-wide-column.mjs`. `CR_READ_TREE=base` shows it failing on the base, `--mutants` proves each assertion class can fail, `CR_LEGACY=1` strips `:has()` and `@property` rules.

Page names the test expects: `read-finished`, `read-reading`, `read-abandoned`, `read-rowless`, `read-locked`, `read-prose`, `checkin`, `comic`, `quote`, `watch`, `listen`, `like`, `eat`, `drink`, `repost`, `favorite`, `jam`, `kind-read`, `kind-like`, `kind-repost`, `kind-favorite`, `kind-checkin`, `stream`, `home`, `hello`.
