#!/bin/zsh
# courtneyr.dev 0.7.46 content/config migrations — staging.
# Run from the Mac. Every step previews the exact operation it then applies;
# the script stops on the first failed command (set -e, pipefail). Backups are
# verified and immutable on the server (wp-content/cr-homepage-migration, with
# manifest.jsonl); term descriptions are exported here before they change.
# W_CMD overrides the wp binary for recorder tests (gap review G-02).
set -e
set -o pipefail
ENV=staging
EXPECT_HOME="https://41451.us6.myftpupload.com"
EXPECT_THEME_VERSION="0.7.46"
EXPECT_PLUGINS=( "post-kinds-for-indieweb-in-block-themes,1.8.1,active" "outpost-mobile-publishing,1.0.15,active" )
HOME_ID=2651; MENU_ID=9960; TPL_ID=37240
W_CMD="${W_CMD:-wp}"
THEME_SRC="${THEME_SRC:-$HOME/projects/staging-courtneyr-dev/themes/courtneyr-child}"
VAULT="${VAULT:-/Users/courtneyrobertson/Documents/2nd Brain/1. Projects/courtneyr.dev/homepage-newsletter-field-notes/evidence/implementation/0746}"
RUN_DIR="$VAULT/release/runs/$ENV-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$RUN_DIR"
exec > >(tee -a "$RUN_DIR/log.txt") 2>&1
w() { "$W_CMD" "@$ENV" "$@"; }
# value capture: the remote PHP prints warnings around WP-CLI output (a twice-defined WP_DEBUG on the host), so take the last stdout line only.
wv() { "$W_CMD" "@$ENV" "$@" 2>/dev/null | tail -n 1; }
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
for f in theme.json inc/security-headers.php assets/css/cr-home-sections.css _handoffs/homepage-newsletter-field-notes/migrate-homepage-sections.php _handoffs/homepage-newsletter-field-notes/fix-entry-wrappers.php _handoffs/homepage-newsletter-field-notes/build-navigation.php _handoffs/homepage-newsletter-field-notes/cr-migration-backup.php; do
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

echo "## 7. post-format intros (export current values first; restore with rollback-terms.sh)"
w term list post_format --fields=term_id,slug,description --format=json > "$RUN_DIR/post_format-terms-before.json"
[[ -s "$RUN_DIR/post_format-terms-before.json" ]] || die "term export is empty"
while IFS='|' read -r slug desc; do
	current="$(wv term get post_format "post-format-$slug" --by=slug --field=description 2>/dev/null || echo '__missing__')"
	if [[ "$current" == "__missing__" ]]; then echo "term post-format-$slug absent here; skipped"; continue; fi
	if [[ "$current" == "$desc" ]]; then echo "post-format-$slug already: $desc"; continue; fi
	echo "post-format-$slug: '$current' -> '$desc'"
	w term update post_format "post-format-$slug" --by=slug --description="$desc"
done <<'DESC'
quote|Lines worth keeping, with their source.
status|Short updates from the day.
aside|Passing thoughts, no title needed.
image|One photo at a time.
gallery|Sets of photos from one outing.
video|Clips and talks I recorded or shared.
audio|Podcast episodes and sound notes.
DESC

echo "## 8. surface backfill (preview: posts whose stored surface differs, then recompute)"
w eval 'foreach (get_posts(["post_type"=>"post","post_status"=>"any","numberposts"=>-1,"fields"=>"ids"]) as $id) { if (get_post_meta($id,"_pkiw_surface",true) !== \PKIW\Post_Surface::get($id)) $n[] = $id; } echo "would recompute: ", count($n ?? []), " post(s) ", implode(",", array_slice($n ?? [], 0, 20)), "\n";'
w eval 'echo \PKIW\Post_Surface::backfill(), " posts recomputed\n";'

echo "## 9. story posters (manual in the Web Stories editor)"
for sid in 7532 7517; do
	poster="$(wv post meta get "$sid" _thumbnail_id || true)"
	[[ -n "$poster" ]] || PENDING+=("story $sid has no poster (Web Stories editor > Document > Poster image)")
done

echo "## result"
echo "steps 1-8 applied on $ENV; log and term export in $RUN_DIR"
if (( ${#PENDING[@]} )); then
	echo "PENDING (manual): ${#PENDING[@]} item(s)"; for p in "${PENDING[@]}"; do echo "  - $p"; done
	echo "status: COMPLETED WITH PENDING MANUAL ITEMS"
else
	echo "status: COMPLETED"
fi
