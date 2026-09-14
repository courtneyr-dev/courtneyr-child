# Tests and lint

The theme has no build step. Three gates run in CI on every push and pull request: PHPCS, stylelint and a `theme.json` schema check. Two more commands, `npm run evidence` and `npm run captures`, need a running copy of the site, so you run them locally.

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
| `npm run lint` | Runs the three above; stops at the first failure | no |
| `npm run lint:php:baseline` | Rewrites the PHPCS baseline from the current results | no |
| `npm run lint:css:baseline` | Rewrites the stylelint baseline from the current results | no |
| `npm run evidence` | Loads five routes, saves screenshots and console errors, runs axe; fails on serious or critical violations | no |
| `npm run captures` | Playwright screenshots of five routes at 390 and 1280 px, light and dark | no |

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

The story archive uses `templates/archive-web-story.html`, which needs the Web Stories plugin. The replica doesn't have it active, so `/web-stories/` returns 404 there.

### `npm run evidence`

Opens each route in Chromium at 1280 px, in light and dark mode, saves a viewport screenshot, records console errors and `data-theme`, and runs axe-core 4.13.0 with its default rule set. It writes `summary.json` and the screenshots to `tests/output/evidence/<timestamp>/` (override with `CR_EVIDENCE_OUT`).

- Exit 1: a route has a serious or critical axe violation. This is the gate.
- Exit 2: no gating violation, but a route didn't load with HTTP 200.
- Moderate and minor violations appear in the table and `summary.json` but don't fail the run yet.

The script is adapted from the SG-10 accessibility harness in the vault, `1. Projects/courtneyr.dev/homepage-newsletter-field-notes/evidence/implementation/harness/` (`a11y-common.mjs`, `a11y-axe-matrix.mjs`, `shoot.mjs`).

### `npm run captures`

Runs `tests/playwright/captures.spec.mjs` in four projects, `390-light`, `390-dark`, `1280-light` and `1280-dark`, which gives 20 full-page PNGs in `tests/output/captures/`, named `<route>-<width>-<scheme>.png`. `CR_CAPTURE_FULL_PAGE=0` captures the viewport only. A test fails when its route doesn't return HTTP 200, and it saves the screenshot first.

Dark and light mode set both the browser color scheme and the theme's `courtneyr-theme` localStorage value, which the no-flash script reads before first paint.

`tests/output/` is gitignored.

## CI

`.github/workflows/lint.yml` runs on push and pull request with `actions/checkout@v7`, `shivammathur/setup-php@v2` (PHP 8.5) and `actions/setup-node@v7` (Node 24). All three actions declare the `node24` runtime. The PHPCS and stylelint steps each run even when an earlier gate fails, so one run reports all three.

The workflow installs with `composer install` and `npm install` against the exact pins. There's no `package-lock.json` because the machine that set this up had too little disk on 2026-09-13 to resolve one. Adding it and switching the step to `npm ci` is a follow-up.
