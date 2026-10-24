# Performance and equivalence harnesses

Chrome DevTools Protocol scripts used on 2026-10-22/23 to prove the CSS minify rollout. They need Node 22+ (global `WebSocket`), a Chromium to drive, and a site to read. The scripts use Playwright's Chrome for Testing (`~/Library/Caches/ms-playwright/chromium-*/`, installed by `npx playwright install chromium`), or the binary in `CHROME_BIN`, and fall back to Google Chrome only if neither exists. Each starts its own headed browser with the natural user agent. Do not use branded Google Chrome for this: on macOS every fresh profile makes it ask whether to become the default browser, and `--no-default-browser-check` does not stop that dialog. Chrome for Testing does not ask (headless Chrome's default user agent gets a 403 from the site; do not spoof one).

`urls30.txt` is `label url` per line, 30 pages on courtneyr.dev. Swap the host with `sed` to point at dev or test.

| Script | What it answers | Run |
| --- | --- | --- |
| `cls.mjs` | Throttled mobile CLS and paint (1.6 Mbps, 150 ms RTT, 4x CPU) and theme-sheet bytes, per load | `node cls.mjs <urls> <out.ndjson> <loads> [labels]` |
| `states.mjs` | Source against deployed CSS on the same page: print media, forced hover/focus/active, keyboard focus rings, with a second source load as the noise control | `node states.mjs <urls> <out.ndjson> <repo>/assets/css [labels]` |
| `handles.py` | Which stylesheet handles each URL loads as `<link>`, delayed or inline | `python3 handles.py <host> <out.json> [urls]` |
| `clsab.mjs` | Source-against-minified CLS on one page with modeled sheet timing (see its header; a model, calibrate it) | `SRC_DIR=... MIN_DIR=... node clsab.mjs <urls> <out.ndjson> <loads>` |
| `axe.mjs` | axe-core (the repo's pinned version) WCAG 2.0 to 2.2 A and AA on given URLs | `node axe.mjs <url>...` |
| `highlight-contrast.mjs` | Contrast of every highlight element on home, about, blog and stream in five theme states (OS light/dark with no stored choice, stored light, stored dark) | `node highlight-contrast.mjs [origin]` |
| `header-diag.mjs` | Which header element changes size when a stylesheet (default `components.css`) arrives; can inject a candidate inline CSS (`fixtures/blog-header-critical.css`) | `[WIDTH=390] [CAND=fixtures/blog-header-critical.css] node header-diag.mjs <url>` |
| `list-diag.mjs` | Padding, position and matched rules of every `ol.wp-block-list` at 390, 980 and 1280 px | `node list-diag.mjs <post-url>` |
| `lighthouse/run.sh`, `run_dt.sh`, `run_ab.sh` | Local Lighthouse 13.5.0, mobile: simulated (PSI-style), applied (devtools) throttling, and a blocked-URL A/B | `bash lighthouse/run_dt.sh <url> <outbase> <n>` |
| `pixview.mjs` | Amplified diff of two screenshots, with pixel count and the largest channel change | `node pixview.mjs <name>...` |

Rules that cost time when ignored:

- Pace requests. After about 200 requests in a minute Cloudflare answers 429, and a 429 body is a valid page with no stylesheets. The scripts retry with a cool-down; do not run two at once, because CPU contention moves the throttled timings.
- Any difference needs a same-CSS control in the same run, repeated. Two loads of identical CSS differ by up to 57 of 255 on Complianz focus rings because subpixel layout moves between loads.
- A page load that returned HTTP 301, 403 or 429 is not a measurement; use the canonical URL.
- After clearing Perfmatters' used CSS, load one representative page per type before measuring (a post with a figure, an `hr` and a featured image), or rules are missing and CLS moves.
- PageSpeed Insights' mobile LCP is a simulation. Report the applied-throttle number from `cls.mjs` beside it.

## Setting up on a new machine

1. `git fetch origin && git checkout chore/perf-handover-2026-10-23` in the theme repo, then `npm install --ignore-scripts` (esbuild, axe-core, stylelint).
2. `npx playwright install chromium` (Chrome for Testing; the scripts find it in `~/Library/Caches/ms-playwright/`, or set `CHROME_BIN`).
3. `cd tests/perf/lighthouse && npm install --ignore-scripts` (Lighthouse 13.5.0). Run the scripts from that folder so `npx --no-install lighthouse` resolves.
4. Terminus logged in (`terminus auth:login --email=<account>`) for the used-CSS and deploy commands. `private/bin/*.sh` live in the deploy repo (`courtneyrdev-pantheon`).
5. The theme sources of the live release for source-against-served runs: `git archive eef5c93 assets/css | tar -x -C <dir>`.

## Baselines

`baselines/2026-10-23.json` holds the numbers to compare against: Lighthouse on live home before the minify (applied and simulated), the post-deploy runs (applied ones are unusable: the machine was overloaded, benchmark index 1,100 to 2,700), and per-page throttled CLS, paint and theme-sheet bytes on dev (before and after), test and live. `baselines/handle-sets-live-2026-10-23.json` is the link/inline handle set of every URL in `urls30.txt` on live. Raw Lighthouse reports are not kept (0.7 MB each); rerun if you need them.

Machine load matters: read `environment.benchmarkIndex` in every Lighthouse report and discard runs below about 3,800 on an Apple-silicon Mac.
