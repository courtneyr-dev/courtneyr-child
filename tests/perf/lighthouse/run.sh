#!/bin/bash
CHROME="${CHROME_BIN:-$(ls -d $HOME/Library/Caches/ms-playwright/chromium-*/chrome-mac-arm64/'Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing' 2>/dev/null | sort | tail -1)}"
[ -x "$CHROME" ] || { echo 'No Chrome for Testing found: npx playwright install chromium, or set CHROME_BIN' >&2; exit 1; }
# usage: run.sh <url> <outbase> [n]   Lighthouse mobile, simulated (PSI-style) throttling; writes <outbase>_1.json .. <outbase>_<n>.json (n defaults to 4).
# Every invocation starts a fresh Chrome profile. Run 1 of a fresh profile picks up Cloudflare's challenge-platform script
# (TBT about 1,200 ms, score about 0.73-0.75 on home), so discard <outbase>_1.json. Use n >= 2 and keep runs 2..n.
# Before trusting a run read environment.benchmarkIndex (this Mac: floor 3,650, load1 < 4, TTFB < 600 ms); see ../README.md.
URL="$1"; OUT="$2"; N="${3:-4}"; [ -n "$URL" ] && [ -n "$OUT" ] && [[ "$N" =~ ^[1-9][0-9]*$ ]] || { echo "usage: run.sh <url> <outbase> [n>=1]" >&2; exit 1; }
[ "$N" -eq 1 ] && echo "note: n=1 starts a fresh profile and its only run is run 1, which carries Cloudflare's challenge-platform script (TBT about 1,200 ms). Discard it, or use n >= 2 and drop ${OUT}_1.json." >&2
PORT=$((9600 + RANDOM % 300)); PROF=$(mktemp -d /tmp/lhprof-XXXX)
"$CHROME" --remote-debugging-port=$PORT --user-data-dir=$PROF --no-first-run --no-default-browser-check --disable-extensions --disable-sync --window-position=-2400,0 --window-size=500,900 about:blank >/dev/null 2>&1 &
CPID=$!
for i in $(seq 1 50); do curl -s http://127.0.0.1:$PORT/json/version >/dev/null && break; sleep 0.2; done
for i in $(seq 1 $N); do
  npx --no-install lighthouse "$URL" --port=$PORT --config-path=./config.mjs --form-factor=mobile --output=json --output-path="${OUT}_$i.json" --quiet 2>&1 | tail -2
done
kill $CPID 2>/dev/null; sleep 1; rm -rf $PROF
if [ "$N" -gt 1 ]; then echo "note: discard ${OUT}_1.json (first load of a fresh profile); runs 2..$N are the measurements." >&2; fi
