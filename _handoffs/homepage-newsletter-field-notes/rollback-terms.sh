#!/bin/zsh
# Restore term descriptions from a backup-terms.php export, byte for byte.
#   rollback-terms.sh <env> <export.json>
# Exit codes: 1 usage, 2 export invalid, 3 target identity mismatch, 4 a term
# write failed or was refused, 5 post-restore comparison found a difference.
# Every remote call reads /dev/null so ssh can never consume the term list;
# the list itself is read from a file descriptor, not stdin. W_CMD overrides
# the wp binary for recorder tests.
set -e
set -u
set -o pipefail
ENV="${1:-}"; FILE="${2:-}"
[[ -n "$ENV" && -n "$FILE" ]] || { echo "usage: rollback-terms.sh <env> <export.json>"; exit 1; }
W_CMD="${W_CMD:-wp}"
w() { "$W_CMD" "@$ENV" "$@" < /dev/null; }
# run a remote command; print its CRJSON payload, or the remote error lines when there is none
crjson() {
	local out; out="$("$W_CMD" "@$ENV" "$@" < /dev/null 2>&1 || true)"
	local line; line="$(printf '%s\n' "$out" | grep '^CRJSON:' | tail -n 1 | sed 's/^CRJSON://')"
	if [[ -n "$line" ]]; then printf '%s\n' "$line"; else printf '%s\n' "$out" | grep -E '^(Error|ABORT|Fatal)' | tail -n 3; fi
}

# 1. validate the export before any remote call
[[ -s "$FILE" ]] || { echo "ABORT: export $FILE is missing or empty"; exit 2; }
if ! python3 - "$FILE" <<'PY'
import json,sys,base64
try:
    d=json.load(open(sys.argv[1],encoding='utf-8'))
except Exception as e:
    print(f"ABORT: export is not valid JSON: {e}"); sys.exit(2)
ok=isinstance(d,dict) and isinstance(d.get('home'),str) and d['home'].startswith('http') and isinstance(d.get('taxonomy'),str) and isinstance(d.get('terms'),list) and len(d['terms'])>0
if ok:
    for t in d['terms']:
        if not (isinstance(t,dict) and isinstance(t.get('term_id'),int) and t['term_id']>0 and isinstance(t.get('slug'),str) and t['slug'] and isinstance(t.get('description_b64'),str)):
            ok=False; break
        try:
            raw=base64.b64decode(t['description_b64'],validate=True)
            if 'description' in t and raw.decode('utf-8')!=t['description']: ok=False; break
        except Exception: ok=False; break
if not ok: print("ABORT: export does not carry {home, taxonomy, terms[{term_id, slug, description_b64}]} with strict base64"); sys.exit(2)
print(f"export valid: {len(d['terms'])} term(s) in {d['taxonomy']} exported {d.get('exported')} from {d['home']}")
PY
then exit 2; fi
TAXONOMY="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["taxonomy"])' "$FILE")"
EXPECT_HOME="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["home"])' "$FILE")"

# 2. target identity
home="$(w option get home 2>/dev/null | tail -n 1)"
home="${home%/}/"
[[ "$home" == "$EXPECT_HOME" ]] || { echo "ABORT: @$ENV home is '$home', export came from '$EXPECT_HOME'"; exit 3; }
THEME_DIR="$(w eval 'echo get_stylesheet_directory();' 2>/dev/null | tail -n 1)"
RESTORE="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/restore-term.php"
BACKUP="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/backup-terms.php"
echo "target @$ENV $home; taxonomy $TAXONOMY"

# 3. restore every term, exact bytes, verified by restore-term.php; fail on the first error
LIST="$(mktemp)"; trap 'rm -f "$LIST"' EXIT
python3 - "$FILE" > "$LIST" <<'PY'
import json,sys
for t in json.load(open(sys.argv[1]))["terms"]:
    print(f"{t['term_id']}\t{t['slug']}\t{t['description_b64']}")
PY
n=0; changed=0
while IFS=$'\t' read -r -u 3 id slug b64; do
	n=$((n+1))
	line="$(crjson eval-file "$RESTORE" "$TAXONOMY" "$id" "$slug" "$b64" || true)"
	if [[ -z "$line" ]] || ! python3 -c 'import json,sys; d=json.loads(sys.argv[1]); sys.exit(0 if d.get("ok") is True and d.get("term_id")==int(sys.argv[2]) and d.get("after_b64")==sys.argv[3] else 1)' "$line" "$id" "$b64"; then
		echo "ABORT: term $id ($slug) was not restored: ${line:-no CRJSON line from restore-term.php}"; exit 4
	fi
	if python3 -c 'import json,sys; sys.exit(0 if json.loads(sys.argv[1]).get("changed") else 1)' "$line"; then changed=$((changed+1)); echo "restored $id ($slug)"; else echo "unchanged $id ($slug)"; fi
done 3< "$LIST"

# 4. compare every restored value against the export
now="$(crjson eval-file "$BACKUP" "$TAXONOMY" preview || true)"
[[ -n "$now" ]] || { echo "ABORT: could not re-export $TAXONOMY for comparison"; exit 5; }
if ! python3 - "$FILE" "$now" <<'PY'
import json,sys
want={t['term_id']:t for t in json.load(open(sys.argv[1]))['terms']}
have={t['term_id']:t for t in json.loads(sys.argv[2])['terms']}
bad=[]
for i,t in want.items():
    h=have.get(i)
    if h is None: bad.append((i,'missing on target'))
    elif h['slug']!=t['slug']: bad.append((i,f"slug {h['slug']} != {t['slug']}"))
    elif h['description_b64']!=t['description_b64']: bad.append((i,'description differs'))
if bad:
    for b in bad: print('MISMATCH', *b)
    sys.exit(5)
print(f"verified: {len(want)} term(s) match the export byte for byte")
PY
then exit 5; fi
echo "rollback-terms: $n term(s) processed, $changed changed, all verified on @$ENV"
