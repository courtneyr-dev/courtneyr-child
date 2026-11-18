# Performance and equivalence harnesses

Chrome DevTools Protocol scripts used on 2026-10-22/23 to prove the CSS minify rollout. They need Node 22+ (global `WebSocket`), a Chromium to drive, and a site to read. The scripts use Playwright's Chrome for Testing (`~/Library/Caches/ms-playwright/chromium-*/`, installed by `npx playwright install chromium`), or the binary in `CHROME_BIN`, and fall back to Google Chrome only if neither exists. Each starts its own headed browser with the natural user agent. Do not use branded Google Chrome for this: on macOS every fresh profile makes it ask whether to become the default browser, and `--no-default-browser-check` does not stop that dialog. Chrome for Testing does not ask (headless Chrome's default user agent gets a 403 from the site; do not spoof one).

`urls30.txt` is `label url` per line, 30 pages on courtneyr.dev. Swap the host with `sed` to point at dev or test.

| Script | What it answers | Run |
| --- | --- | --- |
| `cls.mjs` | Throttled mobile CLS and paint (1.6 Mbps, 150 ms RTT, 4x CPU) and theme-sheet bytes, per load | `node cls.mjs <urls> <out.ndjson> <loads> [labels]` |
| `states.mjs` | Source against deployed CSS on the same page: print media, forced hover/focus/active, keyboard focus rings, with a second source load as the noise control | `node states.mjs <urls> <out.ndjson> <repo>/assets/css [labels]` |
| `handles.py` | Which theme stylesheet handles (`courtneyr-*` and `cr-*`, never plugin sheets) each URL loads as `<link>`, delayed or inline. `python3 -m unittest test_handles` checks the matcher against a fixture | `python3 handles.py <host> <out.json> [urls]` |
| `clsab.mjs` | Source-against-minified CLS on one page with modeled sheet timing (see its header; a model, calibrate it) | `SRC_DIR=... MIN_DIR=... node clsab.mjs <urls> <out.ndjson> <loads>` |
| `axe.mjs` | axe-core (the repo's pinned version) WCAG 2.0 to 2.2 A and AA on given URLs | `[WIDTH=390] [SCHEME=light\|dark] node axe.mjs <url>...` (SCHEME forces prefers-color-scheme, so a light run stays light when macOS is dark; either one prints a `state` line with what the page saw) |
| `highlight-contrast.mjs` | Contrast of every highlight element on home, about, blog and stream in five theme states (OS light/dark with no stored choice, stored light, stored dark) | `node highlight-contrast.mjs [origin]` |
| `header-diag.mjs` | Which header element changes size when a stylesheet (default `components.css`) arrives; can inject a candidate inline CSS (`fixtures/blog-header-critical.css`) | `[WIDTH=390] [CAND=fixtures/blog-header-critical.css] node header-diag.mjs <url>` |
| `list-diag.mjs` | Padding, position and matched rules of every `ol.wp-block-list` at 390, 980 and 1280 px | `node list-diag.mjs <post-url>` |
| `lighthouse/run.sh`, `run_dt.sh`, `run_ab.sh` | Local Lighthouse 13.5.0, mobile: simulated (PSI-style), applied (devtools) throttling, and a blocked-URL A/B | `bash lighthouse/run_dt.sh <url> <outbase> [n]` (n defaults to 4). Each call starts a fresh Chrome profile, and run 1 of a fresh profile loads Cloudflare's challenge-platform script (TBT about 1,200 ms): discard `<outbase>_1.json`, so use n >= 2. n=1 prints a warning |
| `pixview.mjs` | Amplified diff of two screenshots, with pixel count and the largest channel change | `node pixview.mjs <name>...` |

Rules that cost time when ignored:

- Pace requests. After about 200 requests in a minute Cloudflare answers 429, and a 429 body is a valid page with no stylesheets. The scripts retry with a cool-down; do not run two at once, because CPU contention moves the throttled timings.
- Any difference needs a same-CSS control in the same run, repeated. Two loads of identical CSS differ by up to 57 of 255 on Complianz focus rings because subpixel layout moves between loads.
- A page load that returned HTTP 301, 403 or 429 is not a measurement; use the canonical URL.
- After clearing Perfmatters' used CSS, load one representative page per type before measuring (a post with a figure, an `hr` and a featured image), or rules are missing and CLS moves.
- PageSpeed Insights' mobile LCP is a simulation. Report the applied-throttle number from `cls.mjs` beside it.

## N7 A/B harness (`artifact-ab.mjs`)

`artifact-ab.mjs` is N7's no-visual-change check: it proves a kind's render didn't change when the kind moved to a registered block style (spec `spec-n7-artifact-styles.md`, "No-visual-change check" steps 1 to 7, and R-N12 (m2)). Its view records come only from the baseline and head `fidelity.json` maps, plus a `-ref` kind's step 4 `spec.json`. It has no width list of its own. Run it from the lane worktree against whatever theme tree the Studio site serves, so the baseline and head use the same harness file (compare checks the file's SHA-256).

| Mode | What it does |
| --- | --- |
| `capture --role baseline` | For each view record: server HTML, a PNG and a computed-style dump of every element under `main` (with `::before` and `::after`) per load, two loads by default so the second is the same-CSS control. Adds an other-scheme twin of each record as extra coverage, each `--expect` post's single (private and password-protected ones also as the administrator), the `/` and `/stream/` handle sets (`handles.py`), `/feed/` and `/wp-json/wp/v2/posts/<id>` |
| `capture --role head` | The same, after it checks `--map-sha256` against the frozen head map and the head records against the baseline's (count, `name`, `path`, `selector`, `width`, `height`, `fullPage`, `colorScheme`). Any mismatch stops it before the first capture |
| `compare` | DOM diff with one whitelist entry (`is-style-<slug>` on the element carrying the kind's root class, `--style <slug>=<root class>`), zero computed-style differences, each screenshot pair identical or within the controls' `pixview.mjs` count and largest channel change, a handle set equal or smaller, mf2, feed and REST unchanged |
| `classify` | R-N12 (m2) for `{ prefixed, bare }` selector pairs over the baseline's rendered DOMs and its two-kind fixture, with `closest()` and `matches()` in the browser |

Exit 0 is clean, 1 is a difference, 2 is a tooling failure (a missing or mismatched record, a changed map, a failed capture); a tooling failure is never a clean result. Twins and fixtures are extra coverage: they must be clean too, but none stands in for a judged record. Usage is in the file's header. `node --test tests/perf/artifact-ab.test.mjs` runs the cases offline on `fixtures/artifact-ab/`.

Local only. `--base-url` must be `localhost`, `127.0.0.1`, `[::1]` or a `.local` host (the `tests/routes.mjs` list), and `CR_ALLOW_REMOTE=1` doesn't lift that here. The browser aborts every request to another host and the manifest records which hosts it blocked. The administrator render runs `studio wp eval` with `wp_set_current_user()`, so nothing logs in and no password is read. Write captures to the vault (`<slug>/n7-baseline-<d>/ab/`), never into this repo.

## Setting up on a new machine

1. `git fetch origin && git checkout chore/perf-handover-2026-10-23` in the theme repo, then `npm install --ignore-scripts` (esbuild, axe-core, stylelint).
2. `npx playwright install chromium` (Chrome for Testing; the scripts find it in `~/Library/Caches/ms-playwright/`, or set `CHROME_BIN`).
3. `cd tests/perf/lighthouse && npm install --ignore-scripts` (Lighthouse 13.5.0). Run the scripts from that folder so `npx --no-install lighthouse` resolves.
4. Terminus logged in (`terminus auth:login --email=<account>`) for the used-CSS and deploy commands. `private/bin/*.sh` live in the deploy repo (`courtneyrdev-pantheon`).
5. The theme sources of the live release for source-against-served runs: `git archive eef5c93 assets/css | tar -x -C <dir>`.

## Baselines

`baselines/2026-10-23.json` holds the numbers to compare against: Lighthouse on live home before the minify (applied and simulated), the post-deploy runs (applied ones are unusable: the machine was overloaded, benchmark index 1,100 to 2,700), and per-page throttled CLS, paint and theme-sheet bytes on dev (before and after), test and live. `baselines/handle-sets-live-2026-10-23.json` is the link/inline handle set of every URL in `urls30.txt` on live. Raw Lighthouse reports are not kept (0.7 MB each); rerun if you need them.

Machine load matters: read `environment.benchmarkIndex` in every Lighthouse report and discard runs below the floor for the machine (next section).

### Lighthouse floor on this Mac (2026-10-24)

On this Mac (Apple M3 Pro, AC power) `environment.benchmarkIndex` tops out around 3,744 even when idle (14 idle applied runs on a local static page: 3,688 to 3,800, SD 29), so the old 3,800 floor rejects almost every run. Use these gates here, all at once:

- benchmarkIndex >= 3,650 (mean minus 3 SD)
- load average (1 min) < 4
- document TTFB < 600 ms
- never count run 1 of a fresh profile (Cloudflare's challenge script)

The 2026-10-22 baseline (FCP = LCP 1,248 to 1,277 ms) came from the other Mac at index 4,200 to 4,600. Translated to this Mac the ratio is 1.34, so the same home page reads about 1,700 ms here, and the 1.5 s gate becomes: home applied LCP <= 2,000 ms in three consecutive valid runs. The ratio also includes the HTML and inlined used CSS that changed since 2026-10-22, so it isn't a pure hardware factor. Source and rows: vault `1. Projects/courtneyr.dev/overnight-report-2026-10-24.md`.
