#!/bin/zsh
# Restore post-format term descriptions from a runner export.
#   rollback-terms.sh <env> <post_format-terms-before.json>
set -e
ENV="$1"; FILE="$2"
[[ -n "$ENV" && -s "$FILE" ]] || { echo "usage: rollback-terms.sh <env> <export.json>"; exit 1; }
python3 - "$FILE" <<'PY' | while IFS=$'\t' read -r id desc; do wp "@$ENV" term update post_format "$id" --description="$desc"; done
import json,sys
for t in json.load(open(sys.argv[1])): print(f"{t['term_id']}\t{t.get('description','')}")
PY
echo "restored descriptions on $ENV from $FILE"
