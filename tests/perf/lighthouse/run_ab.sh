#!/bin/bash
CHROME="${CHROME_BIN:-$(ls -d $HOME/Library/Caches/ms-playwright/chromium-*/chrome-mac-arm64/'Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing' 2>/dev/null | sort | tail -1)}"
[ -x "$CHROME" ] || { echo 'No Chrome for Testing found: npx playwright install chromium, or set CHROME_BIN' >&2; exit 1; }
# usage: run_ab.sh <url> <outbase> <n>   interleaves control and blocked-sheet runs (simulated throttling, as PSI)
URL="$1"; OUT="$2"; N="$3"; PORT=$((9600 + RANDOM % 300)); PROF=$(mktemp -d /tmp/lhprof-XXXX)
"$CHROME" --remote-debugging-port=$PORT --user-data-dir=$PROF --no-first-run --no-default-browser-check --disable-extensions --disable-sync --window-position=-2400,0 --window-size=500,900 about:blank >/dev/null 2>&1 &
CPID=$!
for i in $(seq 1 50); do curl -s http://127.0.0.1:$PORT/json/version >/dev/null && break; sleep 0.2; done
for i in $(seq 1 $N); do
  npx --no-install lighthouse "$URL" --port=$PORT --config-path=./config.mjs --form-factor=mobile --output=json --output-path="${OUT}_ctl_$i.json" --quiet 2>&1 | tail -1
  npx --no-install lighthouse "$URL" --port=$PORT --config-path=./config.mjs --form-factor=mobile --blocked-url-patterns='*syn.min.css*' --blocked-url-patterns='*indieweb/static/css/indieweb.css*' --output=json --output-path="${OUT}_blk_$i.json" --quiet 2>&1 | tail -1
done
kill $CPID 2>/dev/null; sleep 1; rm -rf $PROF
