---
name: verify-courtneyr-child
description: "Launch the courtneyr-child block theme from a given checkout on a dedicated local WordPress Studio site, prove the served theme is that checkout, drive a feature in Chrome like a visitor, save evidence, and stop the site. Use before claiming a theme change works, before capturing rendered HTML for Storybook fixtures, or whenever you need to know which copy of the theme a local site serves."
---

# Verify courtneyr-child

`verify.mjs` beside this file does every step. It runs without a model and prints one result line per step. Run it with Node. Other scripts, such as a fixture capture, can import `launch`, `doctor`, `drive`, `cleanup` and `servedBy` from it.

A target is one JSON object. `launch` prints it and keeps it in the site's lock at `~/Studio/<site>/.verify-lock/target.json`:

| Field | Meaning |
| --- | --- |
| `runId` | Owner token. `drive` and `cleanup` refuse without it. |
| `checkout` | Realpath of the theme checkout under test. |
| `treeHash`, `git`, `version` | A SHA-256 over the checkout's files (skips `.git`, `node_modules`, `tests/output`), `{ head, dirty }`, and the `style.css` Version. Launch reads all three. |
| `site`, `sitePath` | Studio site name (default `courtneyr-verify`) and `~/Studio/<site>`. |
| `port`, `baseUrl` | Studio's assigned port and `http://localhost:<port>`. |
| `linkId` | Inode and birth time of the theme symlink that launch made. |
| `evidenceDir` | `tests/output/verify/<runId>/` in the repo that holds this skill. |

## Prerequisites

- Studio CLI: `studio --version` prints 2.3 or later.
- Google Chrome in `/Applications`. Drive uses it through Playwright's `chrome` channel. Set `CR_BROWSER_CHANNEL=` (empty) to use Playwright's own Chromium after `npx playwright install chromium`.
- `npm install --no-package-lock` once in the repo that holds this skill, for `@playwright/test`.

## Launch

```sh
node .claude/skills/verify-courtneyr-child/verify.mjs launch --checkout <theme-checkout> [--site courtneyr-verify] [--seed courtneyr-x-t269] [--run <runId>]
```

Ready when stderr says `LAUNCH READY <baseUrl> run <runId>` and stdout holds the target JSON. Keep the `runId`. Ready means `GET /` returned 200 with links into `/wp-content/themes/courtneyr-child/`, and its `X-Verify-Live` header names this site and this checkout.

Launch converges from whatever exists, including a launch that died part-way:

1. If `~/Studio/<site>` does not exist, launch copies the stopped seed site into `~/Studio/<site>.copying-<pid>`. It hashes the seed's `wp-content` before and after the copy and aborts if they differ. It writes the `.verify-site` marker into the copy, then renames the copy into place. Launch refuses a running seed. It removes a `.copying-<pid>` directory whose launcher is gone.
2. It refuses a site without the `.verify-site` marker. Launch never repoints another session's site.
3. It takes the lock. A second launch on a held site exits 1 with `REFUSED: <site> is held by run <id> (checkout <path>, since <time>)`. A running site with no lock is refused too. A lock whose site is stopped and whose launcher process is gone is stale. One launch moves it aside and takes over. Passing `--run <your runId>` relaunches your own lock.
4. It stops the site if the site runs. A PHP worker that outlives a repoint keeps rendering the old checkout, so launch never moves the theme symlink under a running worker.
5. It registers the site with `studio create` if Studio does not list it. The marker's `contentUrl` names the URL that the site's content uses. When `contentUrl` differs from Studio's URL, launch runs `wp search-replace` and then updates the marker.
6. It points `wp-content/themes/courtneyr-child` at the checkout, copies `live-theme.php` into the site's `mu-plugins`, activates the theme if needed, clears the theme's pattern cache, writes the target into the lock, and runs `studio start`.

Launch again with the same `--run` after any edit in the checkout, or to serve another checkout on the same site. For a base-versus-head capture, launch the base checkout, capture, launch the head checkout with the same `--run`, and capture again.

For parallel runs, give each agent its own `--site` (for example `courtneyr-verify-2`). The first launch provisions it.

## Doctor

```sh
node .claude/skills/verify-courtneyr-child/verify.mjs doctor [--site courtneyr-verify] [--checkout <theme-checkout>]
```

Read-only. Without `--checkout` it checks against the checkout in the lock. Exit 0 and `DOCTOR PASS` only when every line passes:

- `site-running`, `site-provisioned`: Studio reports the site running, and the marker's `contentUrl` is Studio's URL.
- `lock-held`, `lock-url`: a verify lock owns the site, and its `baseUrl` is Studio's URL.
- `checkout-unchanged`: the checkout's path, tree hash and git state are what launch recorded, and no file changed after launch started. An edit, a commit, or another `--checkout` fails it. A file edited and then restored fails it too, because PHP keeps running a changed file's old code for about 2 seconds.
- `theme-link-unchanged`, `theme-dir-realpath`: the site's theme symlink is the one that launch made, and it resolves to the checkout. A link replaced after launch fails, because PHP workers started before the swap keep rendering the old target.
- `wp-active-theme`, `wp-theme-dir`, `wp-theme-version`: WordPress in a separate `studio wp eval` process has `courtneyr-child` on `ollie` active, loads it from the checkout's realpath, and reports the checkout's Version.
- `served-style.css`, `served-theme.json`: the bytes the web server returns hash the same as the checkout's files.
- `rendered-version`: `GET /` links `tokens.css?ver=<checkout Version>`.
- `live-theme`: the `X-Verify-Live` header of that `GET /` response names this site directory and exactly one theme directory, the checkout. The PHP worker that rendered the page lists every directory its realpath cache maps the theme to.

A FAIL line names both sides, for example `FAIL live-theme: GET / a PHP worker of /…/Studio/courtneyr-verify renders the theme from /…/theme-0.7.175; expected /…/courtneyr-child-verify-skill`. Run Doctor first whenever a result looks wrong. After `FAIL checkout-unchanged` or `FAIL theme-link-unchanged`, launch again with your `--run`.

## Drive

```sh
node .claude/skills/verify-courtneyr-child/verify.mjs drive <feature> --run <runId> [--site courtneyr-verify]
```

Drive refuses unless `--run` owns the lock and Doctor passes. It runs `drive/<feature>.mjs` in a fresh Chrome context. Each step prints `PASS|FAIL <step>: <observed>`, and the last line is `DRIVE PASS|FAIL <feature>; evidence in <dir>`. Journeys today: `theme-toggle`. Read `features/README.md` before driving, and add a journey as `drive/<feature>.mjs` with the same default-export shape as `drive/theme-toggle.mjs`. A journey that saves a page's server HTML records `served( response )` as a step, so the saved file names the checkout that rendered it.

## Evidence

Drive writes to `<evidenceDir>/<feature>/`:

- `target.json` and `doctor.json`: which checkout, commit, tree hash and Version produced the run, and the Doctor result taken just before driving. `checkout-unchanged` passed in that result, so `target.json` matched the checkout at that moment.
- `home.server.html`: the HTML the server returned for the first request, before any script ran. This is the file to cut a Storybook fixture from. The `home-html-from-checkout` step passes only when that response's `X-Verify-Live` header names the checkout.
- Numbered screenshots and `*.aria.yml` ARIA snapshots for the state before and after each user action.
- `result.json`: every step with its observed value, and `pass`.

A proof drives the real control (a click by role and name), records the state before and after, and reads the side effect back after a reload. Evidence lives under `tests/output/`, which git ignores. Cleanup never deletes it.

A script that saves its own responses checks each one with `servedBy( response.headers()[ 'x-verify-live' ], target ).ok`. `target` is the object `launch` returned.

## Cleanup

```sh
node .claude/skills/verify-courtneyr-child/verify.mjs cleanup --run <runId> [--site courtneyr-verify]
```

Stops the site with `studio stop --path`, checks Studio reports it stopped, and removes the lock. It prints `CLEANUP DONE … evidence kept at <dir>`. A wrong `--run` is refused. `--force` releases a lock whose launcher died with the site still running. Use it only after you confirm that no agent still drives the site. Cleanup leaves the site, its data, the `live-theme.php` copy and the theme symlink in place for the next launch. Run cleanup after every failed attempt too.

## Regression check

```sh
node .claude/skills/verify-courtneyr-child/regress.mjs [--site courtneyr-verify] [--checkout <theme-checkout>] [--verify <path-to-verify.mjs>]
```

Run it after any change to `verify.mjs` or `live-theme.php`. It needs a stopped site with no lock, and it takes about 30 seconds. It copies the checkout to a temporary `head` directory with the same `style.css` and `theme.json`. Then it replays five failures this skill once had: a relaunch with another checkout on a running site, an edit in the launched checkout, a pattern added between launches, a file edited and then restored, and a theme symlink swapped by hand under running PHP workers. It prints one `ok` or `not ok` line per claim and ends with `REGRESS PASS 13 of 13`. `--verify` points it at another copy of `verify.mjs`, such as the one from an older commit.

## Gotchas

- Studio 2.3 serves a site from four PHP built-in server processes. Each one caches where a path resolves for 120 seconds (`realpath_cache_ttl`), and an entry built from a cached directory lasts longer. `X-Verify-Live` describes only the worker that rendered that response, so check it on every response you save. `theme-link-unchanged` covers all four workers.
- The seed `courtneyr-x-t269` has 73 posts in 14 kinds. It has none of the `cr-seed-24x` fixtures that `tests/routes.mjs` maps, so seeded routes in `npm run evidence` and `npm run captures` report `no path found` here.
- `wp-content/plugins/post-kinds-for-indieweb` in the seed is a symlink into `~/Developer/courtneyrdev-pantheon-x5`. The site runs whatever that plugin checkout holds. Doctor proves the theme only.
- `wp search-replace` does not rewrite array keys, so 21 `_pkiw_cover_attachments` rows stay keyed by the seed URL. Post Kinds then finds each cover's attachment with `attachment_url_to_postid()`.
- `npm run evidence` and `npm run captures` read `CR_BASE_URL`, which defaults to port 8894, not this site. Set it to the launched `baseUrl`. They launch Playwright's own Chromium, so run `npx playwright install chromium` first.
- Never launch, start, or stop `courtneyr-n7` or `courtneyr-n7-evidence`. The marker check refuses them, and `--seed` only copies a stopped site.
