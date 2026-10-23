# Performance and equivalence harnesses

Chrome DevTools Protocol scripts used on 2026-10-22/23 to prove the CSS minify rollout. They need Node 22+ (global `WebSocket`), Google Chrome at `/Applications/Google Chrome.app`, and a site to read. Each starts its own headed Chrome off screen with the natural user agent (headless Chrome's default user agent gets a 403 from the site; do not spoof one).

`urls30.txt` is `label url` per line, 30 pages on courtneyr.dev. Swap the host with `sed` to point at dev or test.

| Script | What it answers | Run |
| --- | --- | --- |
| `cls.mjs` | Throttled mobile CLS and paint (1.6 Mbps, 150 ms RTT, 4x CPU) and theme-sheet bytes, per load | `node cls.mjs <urls> <out.ndjson> <loads> [labels]` |
| `states.mjs` | Source against deployed CSS on the same page: print media, forced hover/focus/active, keyboard focus rings, with a second source load as the noise control | `node states.mjs <urls> <out.ndjson> <repo>/assets/css [labels]` |
| `handles.py` | Which stylesheet handles each URL loads as `<link>`, delayed or inline | `python3 handles.py <host> <out.json> [urls]` |
| `clsab.mjs` | Source-against-minified CLS on one page with modeled sheet timing (see its header; a model, calibrate it) | `SRC_DIR=... MIN_DIR=... node clsab.mjs <urls> <out.ndjson> <loads>` |
| `axe.mjs` | axe-core (the repo's pinned version) WCAG 2.0 to 2.2 A and AA on given URLs | `node axe.mjs <url>...` |
| `pixview.mjs` | Amplified diff of two screenshots, with pixel count and the largest channel change | `node pixview.mjs <name>...` |

Rules that cost time when ignored:

- Pace requests. After about 200 requests in a minute Cloudflare answers 429, and a 429 body is a valid page with no stylesheets. The scripts retry with a cool-down; do not run two at once, because CPU contention moves the throttled timings.
- Any difference needs a same-CSS control in the same run, repeated. Two loads of identical CSS differ by up to 57 of 255 on Complianz focus rings because subpixel layout moves between loads.
- A page load that returned HTTP 301, 403 or 429 is not a measurement; use the canonical URL.
- After clearing Perfmatters' used CSS, load one representative page per type before measuring (a post with a figure, an `hr` and a featured image), or rules are missing and CLS moves.
- PageSpeed Insights' mobile LCP is a simulation. Report the applied-throttle number from `cls.mjs` beside it.
