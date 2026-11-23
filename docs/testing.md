# Tests and lint

The theme source is unminified and nothing builds it in place: `tools/minify-css.mjs` runs on the deploy repo's export at sync time (see [CSS minify](#css-minify)). Five gates run in CI on every push and pull request: PHPCS, stylelint, a `theme.json` schema check, the CSS minify check and the CSS guardrails check. Two more commands, `npm run evidence` and `npm run captures`, need a running copy of the site, so you run them locally.

## Setup

```sh
composer install   # PHPCS 3.13.6, WordPress Coding Standards 3.4.1
npm install        # stylelint 16.26.1, ajv 8.20.0, Playwright 1.62.1, axe-core 4.13.0
npx playwright install chromium   # once, for evidence and captures only
```

Versions are pinned exactly in `composer.json` and `package.json`. The repo ignores `composer.lock`, and it has no `package-lock.json` yet (see [CI](#ci)).

## Commands

| Command | What it does | CI |
| --- | --- | --- |
| `npm run lint:php` | PHPCS with the `WordPress` ruleset from `phpcs.xml.dist`, compared with `tests/lint/phpcs-baseline.json` | yes |
| `npm run lint:css` | stylelint on `assets/css/**/*.css` with `stylelint.config.mjs`, compared with `tests/lint/stylelint-baseline.json` | yes |
| `npm run lint:theme-json` | Validates `theme.json` and `styles/**/*.json` against the pinned schema in `tests/schemas/` | yes |
| `npm run build:css:check` | Minifies every `assets/css/**/*.css` in memory with the pinned esbuild and fails on a parse error, a larger output, a lost `@font-face`, `@keyframes`, `@supports`, `@container` or `url()`, or a changed class-token set; writes nothing | yes |
| `npm run lint:css-guardrails` | Fails when a sheet over 30 KiB is not in `tools/css-guardrails.baseline.json` or grows past its recorded size, when a selector list names two kinds (`.kind-watch, .kind-read`) beyond the baseline count, when `RUCSS_INLINE_HANDLES` names a `courtneyr-*` handle with no sheet, when a selector list has `:root:not([data-theme])` and no `[data-theme="system"]` beyond the baseline `dataThemeGaps` count, or when a new `assets/css/cr-*.css` is neither in that list nor in the baseline's `notInRucssHandles`. `--write-baseline` records today's state; review the diff by hand | yes |
| `npm run lint` | Runs the three above; stops at the first failure | no |
| `npm run lint:php:baseline` | Rewrites the PHPCS baseline from the current results | no |
| `npm run lint:css:baseline` | Rewrites the stylelint baseline from the current results | no |
| `npm run evidence` | Loads eleven routes, saves screenshots and console errors, runs axe; fails on serious or critical violations | no |
| `npm run captures` | Playwright screenshots of eleven routes at 390 and 1280 px, light and dark | no |

## Baseline policy

The theme had lint violations before these gates existed. Fixing them all at once would reformat files that other branches are editing, so each gate starts from a baseline instead.

- A baseline stores a count per file per rule (the PHPCS sniff code or the stylelint rule name). The check fails when any file/rule count rises above its baseline, which includes a rule appearing in a file for the first time and any violation in a new file. Existing violations pass.
- Counts, not line numbers, because an edit above a violation moves its line. When a count rises, the report lists every match of that rule in the file, and the new one is among them.
- When you fix violations, the check prints the file/rule pairs now below the baseline. Run `npm run lint:php:baseline` or `npm run lint:css:baseline` and commit the smaller baseline with the fix, so the count can't creep back.
- Don't regenerate a baseline to get a new violation past CI. A commit that raises a baseline count names the rule and the reason in its message.
- Renaming or moving a file gives its violations a new key. Regenerate the baseline in the same commit.
- Don't run `phpcbf` or `stylelint --fix` across the tree as part of a lint change. Fix files you're already editing.

Starting counts, taken on 2026-09-13 at `d51d160` (theme 0.7.46):

| Gate | Violations | Files with violations | Files scanned |
| --- | --- | --- | --- |
| PHPCS | 478 (235 errors, 243 warnings) | 45 | 67 |
| stylelint | 3,772 | 11 | 11 |
| `theme.json` schema | 0 | 0 | 2 |

## PHPCS scope

`phpcs.xml.dist` scans every `.php` file in the theme except `vendor/`, `node_modules/`, `tests/` and any `_archive/` folder. It sets the text domain to `courtneyr-child`, the global prefixes to `Courtneyr\Child` and `courtneyr_child`, and `minimum_wp_version` to 7.1, the theme's `Requires at least`.

`_handoffs/` stays in scope. Its scripts run through `wp eval-file` against staging and live and write options, terms and post meta, so the `WordPress.Security` and `WordPress.DB` sniffs matter more there than anywhere else in the theme. One sniff is off for that folder: `WordPress.NamingConventions.PrefixAllGlobals`, because `wp eval-file` includes the script inside a function, so its top-level variables aren't globals.

## stylelint rules

`stylelint.config.mjs` extends `@wordpress/stylelint-config` 23.42.0 and changes two rules:

- `selector-class-pattern` allows BEM names (`block__element--modifier`) and single underscores. The theme names classes this way, as WordPress core does (`.components-panel__body`), and WordPress emits `.taxonomy-post_tag`. The stock pattern flagged 3,330 selectors, 3,328 of them BEM.
- `selector-id-pattern` allows underscores, for wp-admin ids the theme styles but doesn't own (`#dashboard_right_now`).

Every other rule stays on, and the baseline holds its existing hits. The WordPress config has no rule limiting `!important`, so nothing needed disabling for it.

## theme.json schema

`theme.json` and `styles/dark.json` declare `"$schema": "https://schemas.wp.org/trunk/theme.json"`. That URL redirects to Gutenberg trunk and changes without notice, so the check never fetches it. `tests/lint/theme-json-schema.mjs` maps the URL to `tests/schemas/theme.json`, a copy of `schemas/json/theme.json` from WordPress/gutenberg commit `d0ceb5c86f534dfa7f69ed7c852865c1d7a9da6a` (2026-09-11), and validates with ajv 8.20.0 and ajv-formats 2.1.1 (JSON Schema draft-07). A file that declares another `$schema` URL fails until you add a pinned copy for it.

To refresh the pinned copy:

```sh
sha=$(gh api "repos/WordPress/gutenberg/commits?path=schemas/json/theme.json&sha=trunk&per_page=1" --jq '.[0].sha')
curl -fsS -o tests/schemas/theme.json "https://raw.githubusercontent.com/WordPress/gutenberg/$sha/schemas/json/theme.json"
```

Then put the new SHA in `PINNED` in `tests/lint/theme-json-schema.mjs`, run `npm run lint:theme-json`, and commit both files.

## Evidence and captures

Both commands read the target from `CR_BASE_URL`, which defaults to `http://localhost:8894`, the WordPress Studio replica `courtneyr-audit`. They send GET requests only and never log in. They refuse any host other than `localhost`, `127.0.0.1`, `[::1]` or `*.local` unless you set `CR_ALLOW_REMOTE=1`; don't point them at staging or live.

Routes, from `tests/routes.mjs`:

| Name | Path | Override |
| --- | --- | --- |
| `home` | `/` | none |
| `stream` | `/stream/` | none |
| `single-post` | newest post from `/wp-json/wp/v2/posts` | `CR_SINGLE_POST_PATH` |
| `kind-archive` | `/kind/mood/` | `CR_KIND_ARCHIVE_PATH` |
| `story-archive` | `/web-stories/` | `CR_STORY_ARCHIVE_PATH` |
| `play-archive` | `/kind/play/` | `CR_PLAY_ARCHIVE_PATH` |
| `read-archive` | `/kind/read/` | `CR_READ_ARCHIVE_PATH` |
| `review-archive` | `/kind/review/` | `CR_REVIEW_ARCHIVE_PATH` |
| `reply-archive` | `/kind/reply/` | `CR_REPLY_ARCHIVE_PATH` |
| `like-archive` | `/kind/like/` | `CR_LIKE_ARCHIVE_PATH` |
| `bookmark-archive` | `/kind/bookmark/` | `CR_BOOKMARK_ARCHIVE_PATH` |

The story archive uses `templates/archive-web-story.html`, which needs the Web Stories plugin. The replica doesn't have it active, so `/web-stories/` returns 404 there.

### `npm run evidence`

Opens each route in Chromium at 1280 px, in light and dark mode, saves a viewport screenshot, records console errors and `data-theme`, and runs axe-core 4.13.0 with its default rule set. It writes `summary.json` and the screenshots to `tests/output/evidence/<timestamp>/` (override with `CR_EVIDENCE_OUT`).

- Exit 1: a route has a serious or critical axe violation. This is the gate.
- Exit 2: no gating violation, but a route didn't load with HTTP 200.
- Moderate and minor violations appear in the table and `summary.json` but don't fail the run yet.

The script is adapted from the SG-10 accessibility harness in the vault, `1. Projects/courtneyr.dev/homepage-newsletter-field-notes/evidence/implementation/harness/` (`a11y-common.mjs`, `a11y-axe-matrix.mjs`, `shoot.mjs`).

### `npm run captures`

Runs `tests/playwright/captures.spec.mjs` in four projects, `390-light`, `390-dark`, `1280-light` and `1280-dark`, which gives 28 full-page PNGs in `tests/output/captures/`, named `<route>-<width>-<scheme>.png`. `CR_CAPTURE_FULL_PAGE=0` captures the viewport only. A test fails when its route doesn't return HTTP 200, and it saves the screenshot first.

Dark and light mode set both the browser color scheme and the theme's `courtneyr-theme` localStorage value, which the no-flash script reads before first paint.

`tests/output/` is gitignored.

## Storybook

The site Storybook lives in `storybook/` and shows HTML that WordPress rendered from a commit of this repo. Nothing in a story is copied by hand. The directory has its own `package.json`, so the root `npm install` and the exported root `package.json` don't change. `.gitattributes` keeps `/storybook` out of the deploy archive, and the directory holds no PHP, `block.json` or `theme.json` for WordPress to pick up.

| Command | What it does | Needs |
| --- | --- | --- |
| `node storybook/capture/capture.mjs` | Renders every entry of `storybook/recipes.mjs` on the verify site and replaces `storybook/fixtures/<environment>/` | WordPress Studio. No install and no browser |
| `node storybook/check.mjs` | Fails when a fixture is stale or was changed by hand | git and Node. CI runs it |
| `npm --prefix storybook test` | Feeds hostile asset paths to the capture's path check and its file writer | Node. CI runs it |
| `node storybook/prove-harness.mjs` | Runs 20 checks on a live verify site: 17 requests the capture's must-use plugin has to refuse or serve, where its key hash sits, and what teardown leaves | WordPress Studio |
| `npm --prefix storybook ci` | Installs Storybook 10.6.6 and Vitest into `storybook/node_modules` (90 MB) | |
| `npm --prefix storybook run storybook` | Storybook on port 6006 | the install |
| `npm --prefix storybook run build-storybook` | Static build in `storybook/storybook-static/` (13 MB) | the install |
| `npm --prefix storybook run test-storybook` | Opens every story in headless Chrome and fails on an axe violation | the install, Google Chrome |

`capture.mjs` takes `--commit <rev>` (default `HEAD`), `--site <studio-site>` (default `courtneyr-verify`) and `--env <name>`. When another session holds `courtneyr-verify`, pass `--site courtneyr-verify-<suffix>` and the skill provisions that site.

### What a capture does

1. It adds a clean detached worktree of the commit at `<repo>.capture`, next to the repo. Doctor fails after any write inside the checkout a site serves, so the capture never renders from the worktree it writes to.
2. It launches the `verify-courtneyr-child` skill on that worktree. Launch returns only when Doctor passes.
3. It writes a must-use plugin, the harness, into the verify site and asks WordPress for each recipe entry with two GETs. Both must give the same bytes after normalizing. A page's second GET sends no harness header, so the fixture is what a browser receives.
4. It checks the `X-Verify-Live` header of every response it keeps, fetches the stylesheets, fonts and images the documents load that the theme doesn't own, and runs Doctor again.
5. It removes the harness, stops the site, removes the worktree, and swaps the new `fixtures/<environment>/` directory into place in one rename.

A rerun at the same commit on the same site writes the same bytes. `git diff --exit-code storybook/fixtures` after a rerun is the proof.

### Recipe entries

`storybook/recipes.mjs` is the one file to edit. An entry names a route and, for a block or pattern, where WordPress renders it:

- With `in: 'template'`, the route's own template holds the block. The harness keeps that block and the wrappers above it and drops the rest of the template.
- With `in: 'content'`, the default, WordPress renders the block as the content of the route's post, inside the template's own Post Content block. Nothing writes to the database.
- With no `block` and no `pattern`, the fixture is the whole page.

`empty` gives the reason a state prints nothing. The capture fails when a state with `empty` prints markup, and when a state without it prints none. After a recipe change, commit, capture, add a `fixtureStory( '<environment>/<id>' )` line to a file in `storybook/stories/`, and commit the fixtures with it.

### What a fixture is

A fixture is the whole response body after seven rules in `storybook/capture/format.mjs`: scripts and `noscript` elements removed, four spellings of the site origin replaced with `http://fixture.invalid`, WP Recipe Maker's random comment-rating id set to `0`, and a 10-digit file-time `ver=` set to `0`. The capture fails if a normalized document still holds the site's host, the home directory or the run's key.

`fixtures/<environment>/manifest.json` records the commit, verify's tree hash and the theme Version, the WordPress and PHP versions, Ollie's Version and tree hash, each active plugin's Version, git HEAD and tree hash, the site name and its seed, a fingerprint of the site's posts, terms and nine options, and for each fixture its route, render request, SHA-256, the template and the blocks, patterns and parts WordPress rendered, and the theme files those came from.

### When the check fails

`storybook/check.mjs` reads theme files from `HEAD`, never from the working tree. Its lines start with one of five words:

- `STALE` means a file in the deploy archive, the recipe, or `storybook/capture/` changed since the capture. Run the capture and commit the result. On a pull request that changes nothing under `storybook/`, CI reports this as a warning and passes, so a theme change merges first and the recapture follows in its own pull request. The theme input is the file set `git archive HEAD` exports, minus `*.md`, `LICENSE`, `package.json` and `tools/`, so a change to tests, docs, CI or a root dependency doesn't count.
- `TAMPERED` means a file under `storybook/fixtures/` isn't the one the manifest records. Never edit a fixture by hand.
- `UNRESOLVED` means a document loads a file that neither the theme nor the mirror holds, or names a source file the theme dropped.
- `UNLINKED` means a fixture has no story, or a story names a fixture that doesn't exist.
- `UNSAFE` means `git archive` would export `storybook/`, or a `.php`, `block.json` or `theme.json` file sits in it.

The manifest records the commit the fixtures came from, and a squash merge replaces that commit. The check doesn't read it. It compares git blob ids, which a squash keeps.

### The harness

The harness acts only on a front-end `GET` from `127.0.0.1` or `::1`, for the site's own `Host`, that carries the run's key. The key is 32 random bytes held in the capture's memory. The site gets its SHA-256, in a mode-600 file in a temporary directory outside the site. The harness grants `edit_posts` or `read` at most, to the anonymous visitor only, and only inside `template_include`, which the REST API, admin, AJAX, XML-RPC and cron never reach. Teardown deletes the plugin file and the key hash, on a normal exit and on `SIGINT`, `SIGTERM` and `SIGHUP`. A hard kill leaves both behind, inert without the key, until the next capture removes them.

Page and stylesheet content is site data. Every asset path goes through `assetSitePath()` in `storybook/capture/format.mjs`, which rejects a backslash, a NUL, an encoded dot or slash, another origin, and any path that resolves outside `wp-content` and `wp-includes`. Every file the capture writes goes through `writeInside()`, which refuses a path that resolves outside its directory.

### Limits

- Stories are static. A control that needs JavaScript, such as the theme toggle or a callout's collapse button, shows its server-rendered state.
- A fixture sits inside Storybook's root element, so a `body >` child selector doesn't match. `assets/css/components.css` has one such rule today.
- `in: 'template'` reaches a block in a template file, not a block inside a template part.
- On the `courtneyr-x-t269` seed, `series-guide` and a visitor's `contact-form` print nothing, and `weather-log` prints a date row only. PublishPress Series, the site's contact-form plugin and a weather post aren't on the seed.
- Page stories skip two axe rules that fail in WordPress's own output on this seed. Each page's Overview story names them and the reason.

## CSS minify

The sources in `assets/css/` are never minified in this repo. `private/bin/sync-theme.sh` in the deploy repo exports HEAD with `git archive`, runs `tools/minify-css.mjs` on the export's `assets/css/`, then rsyncs it, so deployed stylesheets keep their names and paths. That matters because `prunable_sheets()` in `inc/stream-style-pruning.php` matches on file names, and the sync writes `css-build.txt` (tool, version, source commit, per-file sha256) next to `source-sha.txt`.

- The sync reads the esbuild version from the exported `package.json` and aborts, before copying anything, if the copy in `node_modules` differs. Run `npm install --ignore-scripts` in the checkout you pass to the sync.
- `CR_MINIFY_CSS=0 private/bin/sync-theme.sh <checkout>` copies the sources unchanged (the rollback switch; revert the sync commit for a full rollback).
- Perfmatters' own minify skips this theme on purpose (`perfmatters_exclusions()` in `inc/enqueue.php`), so there is one minifier, not two.
- Bump the theme version in the same release: the sheets keep their URLs, and `?ver=` is what makes browsers and edge caches fetch the new bytes.

## CI

`.github/workflows/lint.yml` runs on push and pull request with `actions/checkout@v7`, `shivammathur/setup-php@v2` (PHP 8.5) and `actions/setup-node@v7` (Node 24). All three actions declare the `node24` runtime. The PHPCS and stylelint steps each run even when an earlier gate fails, so one run reports all three.

`.github/workflows/storybook.yml` has two more jobs. One runs `node storybook/check.mjs` with no install. The other runs `npm ci` in `storybook/`, builds the Storybook, and runs axe on every story in the runner's Chrome. Neither publishes anything.

`storybook/ci-mode.mjs` picks the mode from the paths a push or pull request changes. A change under `storybook/` or to `storybook.yml` is strict: the first job fails on any finding and the second job runs. A push CI can't compare, such as a new branch, is strict too. Every other change is advisory: `node storybook/check.mjs --advisory` prints `STALE` and `UNRESOLVED` findings as one GitHub warning with the recapture command and exits 0, and the build and axe job is skipped. The other three finding words fail in both modes.

The workflow installs with `composer install` and `npm install` against the exact pins. There's no `package-lock.json` because the machine that set this up had too little disk on 2026-09-13 to resolve one. Adding it and switching the step to `npm ci` is a follow-up.
