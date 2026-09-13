#!/bin/zsh
# courtneyr.dev 0.7.46 content/config migrations — live.
# Run from the Mac. Every step previews the exact operation it then applies;
# the script stops on the first failed command (set -e, pipefail). Backups are
# hash-verified and made read-only where the filesystem allows (wp-content/cr-homepage-migration, with
# manifest.jsonl); term descriptions are exported here before they change.
# W_CMD overrides the wp binary for recorder tests (gap review G-02).
set -e
set -o pipefail
ENV=live
EXPECT_HOME="https://courtneyr.dev"
EXPECT_THEME_VERSION="0.7.46"
EXPECT_PLUGINS=( "post-kinds-for-indieweb-in-block-themes,1.8.1,active" "outpost-mobile-publishing,1.0.15,active" )
HOME_ID=2651; MENU_ID=9960; TPL_ID=37240
W_CMD="${W_CMD:-wp}"
THEME_SRC="${THEME_SRC:-$HOME/projects/staging-courtneyr-dev/themes/courtneyr-child}"
VAULT="${VAULT:-/Users/courtneyrobertson/Documents/2nd Brain/1. Projects/courtneyr.dev/homepage-newsletter-field-notes/evidence/implementation/0746}"
RUN_DIR="$VAULT/release/runs/$ENV-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$RUN_DIR"
exec > >(tee -a "$RUN_DIR/log.txt") 2>&1
# every remote call reads /dev/null so ssh can never consume a list this script is iterating
w() { "$W_CMD" "@$ENV" "$@" < /dev/null; }
# value capture: the remote PHP prints warnings around WP-CLI output (a twice-defined WP_DEBUG on the host), so take the last stdout line only.
wv() { "$W_CMD" "@$ENV" "$@" < /dev/null 2>/dev/null | tail -n 1; }
# payload capture: scripts print one CRJSON:{...} line; anything else is host noise. Empty when the script failed.
wj() { "$W_CMD" "@$ENV" "$@" < /dev/null 2>&1 | tee -a "$RUN_DIR/remote.log" | grep '^CRJSON:' | tail -n 1 | sed 's/^CRJSON://'; }
jq_() { python3 -c 'import json,sys
v=json.loads(sys.argv[1])
for k in sys.argv[2].split("."):
    v=v[int(k)] if isinstance(v,list) else v[k]
print(v if not isinstance(v,(dict,list)) else json.dumps(v))' "$1" "$2"; }
die() { echo "ABORT: $*"; exit 1; }
PENDING=()

echo "## 0. preflight — exact environment and candidate identity ($ENV, $(date -u +%FT%TZ))"
home="$(wv option get home)"; siteurl="$(wv option get siteurl)"
[[ "$home" == "$EXPECT_HOME" && "$siteurl" == "$EXPECT_HOME" ]] || die "home/siteurl are $home / $siteurl, expected $EXPECT_HOME"
abspath="$(wv eval 'echo ABSPATH;')"; echo "document root: $abspath"
theme_row="$(w theme list --status=active --fields=name,version --format=csv 2>/dev/null | grep -x "courtneyr-child,$EXPECT_THEME_VERSION" || true)"
[[ -n "$theme_row" ]] || die "active theme is not courtneyr-child $EXPECT_THEME_VERSION"
for expect in "${EXPECT_PLUGINS[@]}"; do
	slug="${expect%%,*}"
	row="$(w plugin list --name="$slug" --fields=name,version,status --format=csv 2>/dev/null | grep -x "$expect" || true)"
	[[ -n "$row" ]] || die "plugin $slug is not '$expect' (got: $(w plugin list --name="$slug" --fields=name,version,status --format=csv | tail -1))"
done
THEME_DIR="$(wv eval 'echo get_stylesheet_directory();')"
for f in theme.json inc/security-headers.php assets/css/cr-home-sections.css _handoffs/homepage-newsletter-field-notes/migrate-homepage-sections.php _handoffs/homepage-newsletter-field-notes/fix-entry-wrappers.php _handoffs/homepage-newsletter-field-notes/build-navigation.php _handoffs/homepage-newsletter-field-notes/append-browse-all.php _handoffs/homepage-newsletter-field-notes/cr-migration-backup.php _handoffs/homepage-newsletter-field-notes/backup-terms.php _handoffs/homepage-newsletter-field-notes/restore-term.php _handoffs/homepage-newsletter-field-notes/surface-backfill.php _handoffs/homepage-newsletter-field-notes/backup-option.php; do
	local_sha="$(shasum -a 256 "$THEME_SRC/$f" | cut -d' ' -f1)"
	remote_sha="$(wv eval "echo hash_file('sha256', '$THEME_DIR/$f');")"
	[[ "$local_sha" == "$remote_sha" ]] || die "served $f ($remote_sha) differs from the candidate ($local_sha)"
done
echo "served theme files match the candidate checkout ($(git -C "$THEME_SRC" rev-parse --short HEAD))"
[[ "$(wv option get page_on_front)" == "$HOME_ID" ]] || die "front page is not $HOME_ID"
[[ "$(wv post get $MENU_ID --field=post_type)" == "wp_navigation" ]] || die "$MENU_ID is not a navigation post"
STREAM_ID="$(wv eval 'echo get_page_by_path("stream")->ID ?? 0;')"; [[ "$STREAM_ID" -gt 0 ]] || die "no page at /stream/"
[[ "$(wv post get $TPL_ID --field=post_name)" == "single" ]] || die "$TPL_ID is not the saved single template"
w eval 'wp_get_theme()->delete_pattern_cache(); echo "pattern cache cleared\n";'
MIG="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/migrate-homepage-sections.php"
FIX="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/fix-entry-wrappers.php"
NAV="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/build-navigation.php"
BROWSE="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/append-browse-all.php"
TERMS="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/backup-terms.php"
RESTORE="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/restore-term.php"
SURFACE="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/surface-backfill.php"
OPTBK="$THEME_DIR/_handoffs/homepage-newsletter-field-notes/backup-option.php"

echo "## 1. homepage placeholders -> blocks (preview, then the same transform)"
w eval-file "$MIG" upgrade "$HOME_ID" preview
w eval-file "$MIG" upgrade "$HOME_ID"

echo "## 2. section roots: layout lock"
w eval-file "$MIG" lock "$HOME_ID" preview
w eval-file "$MIG" lock "$HOME_ID"

echo "## 3. saved single template override: pkiwSurface + markers"
w eval-file "$MIG" upgrade-templates preview
w eval-file "$MIG" upgrade-templates

echo "## 4. authored h-entry wrappers (dry-run, then apply)"
w eval-file "$FIX" dry-run
w eval-file "$FIX" apply

echo "## 5. Primary Menu regrouping from this site's identities"
w eval-file "$NAV" preview "$MENU_ID"
w eval-file "$NAV" apply "$MENU_ID"

echo "## 6. Stream page: Browse all"
w eval-file "$BROWSE" preview "$STREAM_ID"
w eval-file "$BROWSE" apply "$STREAM_ID"

echo "## 7. post-format intros (verified server-side term backup + validated local copy first; restore with rollback-terms.sh)"
terms_json="$(wj eval-file "$TERMS" post_format)"
[[ -n "$terms_json" ]] || die "term export produced no CRJSON payload (see $RUN_DIR/remote.log)"
printf '%s\n' "$terms_json" > "$RUN_DIR/post_format-terms-before.json"
python3 - "$RUN_DIR/post_format-terms-before.json" "$EXPECT_HOME/" <<'PY' || die "term export is not a valid backup; nothing was changed"
import json,sys,base64
d=json.load(open(sys.argv[1])); assert d['home']==sys.argv[2], f"export home {d['home']} != {sys.argv[2]}"; assert d['taxonomy']=='post_format' and d['terms'] and d['backup_file']
for t in d['terms']: assert isinstance(t['term_id'],int) and t['slug'] and base64.b64decode(t['description_b64'],validate=True).decode()==t['description']
print(f"term export valid: {len(d['terms'])} terms, server backup {d['backup_file']}")
PY
while IFS='|' read -r slug desc; do
	row="$(python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); t=[t for t in d["terms"] if t["slug"]==sys.argv[2]]; print(t[0]["term_id"] if t else "")' "$RUN_DIR/post_format-terms-before.json" "post-format-$slug")"
	if [[ -z "$row" ]]; then echo "term post-format-$slug absent here; skipped"; continue; fi
	b64="$(printf '%s' "$desc" | base64 | tr -d '\n')"
	res="$(wj eval-file "$RESTORE" post_format "$row" "post-format-$slug" "$b64")"
	[[ -n "$res" ]] || die "post-format-$slug: description write failed (see $RUN_DIR/remote.log)"
	if [[ "$(jq_ "$res" changed)" == "True" ]]; then echo "post-format-$slug: set '$desc'"; else echo "post-format-$slug already: $desc"; fi
done <<'DESC'
quote|Lines worth keeping, with their source.
status|Short updates from the day.
aside|Passing thoughts, no title needed.
image|One photo at a time.
gallery|Sets of photos from one outing.
video|Clips and talks I recorded or shared.
audio|Podcast episodes and sound notes.
DESC

echo "## 8. surface backfill: export the complete before-state (server backup + local copy), preview the change set, apply, zero-mismatch postcondition"
sb="$(wj eval-file "$SURFACE" export)"; [[ -n "$sb" ]] || die "surface export failed (see $RUN_DIR/remote.log)"
printf '%s\n' "$sb" > "$RUN_DIR/surface-state-before.json"
SURFACE_BACKUP="$(jq_ "$sb" backup_file)"
echo "surface before-state: $(jq_ "$sb" present) present, $(jq_ "$sb" absent) absent; server backup $SURFACE_BACKUP"
cs="$(wj eval-file "$SURFACE" preview)"; [[ -n "$cs" ]] || die "surface preview failed"
printf '%s\n' "$cs" > "$RUN_DIR/surface-change-set.json"
python3 - "$RUN_DIR/surface-change-set.json" <<'PY'
import json,sys; d=json.load(open(sys.argv[1])); c=d['changes']
print(f"proposed changes: {len(c)} of {d['posts']} posts")
for x in c[:40]: print(f"  {x['id']:>6} {x['status']:8} {x['reason']:14} {str(x['from'])[:18]:18} -> {x['to']:6} kinds={','.join(x['kinds'])} formats={','.join(x['formats'])} promote={x['promote']!r} chars={x['chars']} {x['title'][:40]!r}")
if len(c)>40: print(f"  ... {len(c)-40} more in surface-change-set.json")
PY
ap="$(wj eval-file "$SURFACE" apply "$SURFACE_BACKUP")"
[[ -n "$ap" && "$(jq_ "$ap" ok)" == "True" ]] || die "surface apply did not reach zero mismatches (see $RUN_DIR/remote.log); rollback: wp @$ENV eval-file $SURFACE rollback $SURFACE_BACKUP"
printf '%s\n' "$ap" > "$RUN_DIR/surface-apply.json"
echo "surface apply: $(jq_ "$ap" attempted) written, $(jq_ "$ap" posts) posts verified, 0 mismatches; rollback: wp @$ENV eval-file $SURFACE rollback $SURFACE_BACKUP"

echo "## 9. story posters (manual in the Web Stories editor)"
for sid in 7532 7517; do
	poster="$(wv post meta get "$sid" _thumbnail_id || true)"
	[[ -n "$poster" ]] || PENDING+=("story $sid has no poster (Web Stories editor > Document > Poster image)")
done

echo "## 10. delivery cache: Perfmatters exclusions (option backed up first), used-CSS clear, gateway + CDN purge, then verify ordinary URLs"
ob="$(wj eval-file "$OPTBK" perfmatters_options)"; [[ -n "$ob" ]] || die "perfmatters_options backup failed"
printf '%s\n' "$ob" > "$RUN_DIR/perfmatters_options-before.json"; echo "perfmatters_options backed up: $(jq_ "$ob" backup_file)"
before_excl="$(python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); v=d["value"]; print((v or {}).get("assets",{}).get("rucss_excluded_stylesheets",""))' "$RUN_DIR/perfmatters_options-before.json")"
want_excl=$'cr-home-sections.css\ncr-archives.css\ncr-post-kinds.css'
if [[ "$before_excl" == "$want_excl" ]]; then echo "rucss exclusions already set"; else
	w option patch update perfmatters_options assets rucss_excluded_stylesheets "$want_excl"
	now_excl="$(wv eval 'echo str_replace("\n","|",(string)((get_option("perfmatters_options")["assets"]["rucss_excluded_stylesheets"] ?? "")));')"
	[[ "$now_excl" == "cr-home-sections.css|cr-archives.css|cr-post-kinds.css" ]] || die "rucss exclusions did not store ($now_excl); restore from $RUN_DIR/perfmatters_options-before.json"
	echo "rucss exclusions set (were: '${before_excl//$'\n'/|}')"
fi
w eval 'if (class_exists("Perfmatters\\CSS")) { \Perfmatters\CSS::clear_used_css(); echo "perfmatters used css cleared\n"; } else { echo "perfmatters not active; nothing to clear\n"; }'
GATEWAY="${CR_GATEWAY_SERVERS:-$(wv eval 'global $wpdb; $t=$wpdb->prefix."wpaas_activity_log"; $o=[]; foreach ($wpdb->get_col("SELECT activity FROM `$t` WHERE activity LIKE \"Gateway servers servers:%\" ORDER BY timestamp DESC LIMIT 20") as $r) { if (preg_match_all("/=> \x27(\d+\.\d+\.\d+\.\d+)\x27,\s*1 => \x27?(\d+)/", $r, $m, PREG_SET_ORDER)) foreach ($m as $mm) $o[]=$mm[1].":".$mm[2]; } echo implode(",", array_unique($o));')}"
[[ -n "$GATEWAY" ]] || die "no gateway servers known (set CR_GATEWAY_SERVERS=ip:port from the host activity log)"
w eval "if (!defined('VARNISH_SERVERS')) define('VARNISH_SERVERS','$GATEWAY'); \$m=new \\WPaaS\\Cache\\V2_Manager(); \$m->ban(); echo 'gateway PURGE sent to ', implode(',', array_map(fn(\$s)=>implode(':',\$s), \$m->servers)), \"\\n\";"
inv="$(wv eval 'echo (string)(new \WPaaS\API())->flush_cdn();')"
[[ -n "$inv" ]] || die "CDN flush returned no invalidation id; purge from wp-admin (Flush Cache) and rerun cache-probe.sh"
echo "cdn invalidation $inv"
# (zsh: never name a local "path" — it aliases $PATH and every command vanishes)
verify_cache() {
	local ok=1 route age media body
	for route in / /stream/ /kind/mood/ /type/quote/; do
		body="$(curl -s -D "$RUN_DIR/headers.tmp" -A 'Mozilla/5.0 cr-migration-verify' "$EXPECT_HOME$route")"
		age="$(tr -d '\r' < "$RUN_DIR/headers.tmp" | grep -i '^age:' | awk '{print $2}')"
		media="$(printf '%s' "$body" | grep -o "<link[^>]*cr-\(home-sections\|archives\|post-kinds\).css[^>]*>" | grep -o "media=[\"'][a-z]*[\"']" | sort -u | tr '\n' ' ')"
		echo "  $route age=${age:-none} sheets=${media:-none}"
		[[ -n "$body" && -n "$media" ]] || ok=0   # a response with none of the theme sheets is not a verified page
		[[ -z "$age" || "$age" -lt 120 ]] || ok=0
		[[ "$media" != *print* ]] || ok=0
	done
	return $(( 1 - ok ))
}
sleep 30
if verify_cache; then echo "delivery cache verified: fresh ordinary responses, render-blocking theme sheets"; else sleep 60; verify_cache || PENDING+=("delivery cache still stale on an ordinary URL: press Flush Cache in wp-admin, then rerun cache-probe.sh"); fi
rm -f "$RUN_DIR/headers.tmp"

echo "## result"
echo "steps 1-8 and 10 applied on $ENV; log, term export, surface before-state/change set, perfmatters backup in $RUN_DIR"
if (( ${#PENDING[@]} )); then
	echo "PENDING (manual): ${#PENDING[@]} item(s)"; for p in "${PENDING[@]}"; do echo "  - $p"; done
	echo "status: COMPLETED WITH PENDING MANUAL ITEMS"
else
	echo "status: COMPLETED"
fi
