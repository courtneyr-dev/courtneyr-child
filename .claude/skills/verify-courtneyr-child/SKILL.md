---
name: verify-courtneyr-child
description: "Launch the courtneyr-child block theme from a given checkout on a dedicated local WordPress Studio site, prove the served theme is that checkout, drive a feature in Chrome like a visitor, save evidence, and stop the site. Use before claiming a theme change works, before capturing rendered HTML for Storybook fixtures, or whenever you need to know which copy of the theme a local site serves."
---

# Verify courtneyr-child

`verify.mjs` beside this file does every step. It runs without a model and prints one result line per step. Run it with Node. Other scripts, such as a fixture capture, can import `launch`, `doctor`, `drive` and `cleanup` from it.

A target is one JSON object. `launch` prints it and keeps it in the site's lock at `~/Studio/<site>/.verify-lock/target.json`:

| Field | Meaning |
| --- | --- |
| `runId` | Owner token. `drive` and `cleanup` refuse without it. |
| `checkout` | Realpath of the theme checkout under test. |
| `site`, `sitePath` | Studio site name (default `courtneyr-verify`) and `~/Studio/<site>`. |
| `port`, `baseUrl` | Studio's assigned port and `http://localhost:<port>`. |
| `version`, `treeHash`, `git` | `style.css` Version, a SHA-256 over the checkout's files (skips `.git`, `node_modules`, `tests/output`), and `{ head, dirty }`. |
| `evidenceDir` | `tests/output/verify/<runId>/` in the repo that holds this skill. |

## Prerequisites

- Studio CLI: `studio --version` prints 2.3 or later.
- Google Chrome in `/Applications`. Drive uses it through Playwright's `chrome` channel. Set `CR_BROWSER_CHANNEL=` (empty) to use Playwright's own Chromium after `npx playwright install chromium`.
- `npm install --no-package-lock` once in the repo that holds this skill, for `@playwright/test`.

## Launch

```sh
node .claude/skills/verify-courtneyr-child/verify.mjs launch --checkout <theme-checkout> [--site courtneyr-verify] [--seed courtneyr-x-t269]
```

Ready when stderr says `LAUNCH READY <baseUrl> run <runId>` and stdout holds the target JSON. Keep the `runId`. Ready means `GET /` returned 200 with links into `/wp-content/themes/courtneyr-child/`.

Launch converges from whatever exists:

1. If Studio has no site at `~/Studio/<site>`, launch copies the stopped seed site. It hashes the seed's `wp-content` before and after the copy and aborts if they differ. Then it writes the `.verify-site` marker, registers the copy with `studio create`, and rewrites the seed's URL to the new port with `wp search-replace`. Launch refuses a running seed.
2. It refuses a site without the `.verify-site` marker. Launch never repoints another session's site.
3. It takes the lock. A second launch on a held site exits 1 with `REFUSED: <site> is held by run <id> (checkout <path>, since <time>)`. A running site with no lock is refused too. A lock whose site is stopped and whose launcher process is gone is stale, and launch replaces it. Passing `--run <your runId>` re-launches your own lock.
4. It points `wp-content/themes/courtneyr-child` at the checkout, activates the theme if needed, and runs `studio start`.

For parallel runs, give each agent its own `--site` (for example `courtneyr-verify-2`). The first launch provisions it.

## Doctor

```sh
node .claude/skills/verify-courtneyr-child/verify.mjs doctor [--site courtneyr-verify] [--checkout <theme-checkout>]
```

Read-only. Without `--checkout` it checks against the checkout in the lock. Exit 0 and `DOCTOR PASS` only when every line passes:

- `site-running` and `lock-held`: Studio reports the site running, and a verify lock owns it.
- `theme-dir-realpath`: the site's theme symlink resolves to the checkout.
- `wp-active-theme`, `wp-theme-dir`, `wp-theme-version`: WordPress (`studio wp eval`) has `courtneyr-child` on `ollie` active, loads it from the checkout's realpath, and reports the checkout's Version.
- `served-style.css`, `served-theme.json`: the bytes the web server returns hash the same as the checkout's files.
- `rendered-version`: `GET /` links `tokens.css?ver=<checkout Version>`, so the live PHP process read this Version.

A FAIL line names both sides, for example `FAIL wp-theme-dir: WordPress loads /…/courtneyr-child-verify-skill; expected /…/theme-0.7.175`. Run Doctor first whenever a result looks wrong.

## Drive

```sh
node .claude/skills/verify-courtneyr-child/verify.mjs drive <feature> --run <runId> [--site courtneyr-verify]
```

Drive refuses unless `--run` owns the lock and Doctor passes. It runs `drive/<feature>.mjs` in a fresh Chrome context. Each step prints `PASS|FAIL <step>: <observed>`, and the last line is `DRIVE PASS|FAIL <feature>; evidence in <dir>`. Journeys today: `theme-toggle`. Read `features/README.md` before driving, and add a journey as `drive/<feature>.mjs` with the same default-export shape as `drive/theme-toggle.mjs`.

## Evidence

Drive writes to `<evidenceDir>/<feature>/`:

- `target.json` and `doctor.json`: which checkout, commit, tree hash and Version produced the run, and the Doctor result taken just before driving.
- `home.server.html`: the HTML the server returned for the first request, before any script ran. This is the file to cut a Storybook fixture from.
- Numbered screenshots and `*.aria.yml` ARIA snapshots for the state before and after each user action.
- `result.json`: every step with its observed value, and `pass`.

A proof drives the real control (a click by role and name), records the state before and after, and reads the side effect back after a reload. Evidence lives under `tests/output/`, which git ignores. Cleanup never deletes it.

## Cleanup

```sh
node .claude/skills/verify-courtneyr-child/verify.mjs cleanup --run <runId> [--site courtneyr-verify]
```

Stops the site with `studio stop --path`, checks Studio reports it stopped, and removes the lock. It prints `CLEANUP DONE … evidence kept at <dir>`. A wrong `--run` is refused. `--force` releases a lock whose launcher died with the site still running. Use it only after you confirm that no agent still drives the site. Cleanup leaves the site, its data and the theme symlink in place for the next launch. Run cleanup after every failed attempt too.

## Gotchas

- The seed `courtneyr-x-t269` has 73 posts in 14 kinds. It has none of the `cr-seed-24x` fixtures that `tests/routes.mjs` maps, so seeded routes in `npm run evidence` and `npm run captures` report `no path found` here.
- `wp-content/plugins/post-kinds-for-indieweb` in the seed is a symlink into `~/Developer/courtneyrdev-pantheon-x5`. The site runs whatever that plugin checkout holds. Doctor proves the theme only.
- `npm run evidence` and `npm run captures` read `CR_BASE_URL`, which defaults to port 8894, not this site. Set it to the launched `baseUrl`. They launch Playwright's own Chromium, so run `npx playwright install chromium` first.
- Never launch, start, or stop `courtneyr-n7` or `courtneyr-n7-evidence`. The marker check refuses them, and `--seed` only copies a stopped site.
