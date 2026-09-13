#!/bin/zsh
# courtneyr.dev 0.7.46 content/config migrations — staging.
# Run from your machine (uses the wp @staging alias). Each step prints its dry-run
# first; the script stops at the first error. Re-running is safe: every step is
# idempotent or refuses when nothing changes. Backups: every WordPress write
# creates a revision, and the eval-file scripts write backup files under
# wp-content/cr-homepage-migration/ on the server.
set -e
W="wp @staging"
THEME=wp-content/themes/courtneyr-child
MIG=$THEME/_handoffs/homepage-newsletter-field-notes/migrate-homepage-sections.php
FIX=$THEME/_handoffs/homepage-newsletter-field-notes/fix-entry-wrappers.php
VAULT="/Users/courtneyrobertson/Documents/2nd Brain/1. Projects/courtneyr.dev/homepage-newsletter-field-notes/evidence/implementation/0746"
HOME_ID=2651; MENU_ID=9960; STREAM_ID=37956; TPL_ID=37240

echo "## 0. preflight: versions and identities"
$W theme list --status=active --fields=name,version --format=csv | grep courtneyr-child
$W plugin list --fields=name,version,status --format=csv | grep -E 'post-kinds-for-indieweb-in-block-themes|outpost-mobile-publishing'
[ "$($W option get page_on_front)" = "$HOME_ID" ] || { echo "front page is not $HOME_ID"; exit 1; }
$W post get $MENU_ID --field=post_title | grep -q "Primary Menu" || { echo "menu $MENU_ID is not the Primary Menu"; exit 1; }
$W post get $STREAM_ID --field=post_name | grep -qx stream || { echo "page $STREAM_ID is not /stream/"; exit 1; }
$W eval 'wp_get_theme()->delete_pattern_cache(); echo "pattern cache cleared\n";'

echo "## 1. homepage placeholders -> blocks (dry-run, then apply)"
$W eval-file $MIG dry-run $HOME_ID
$W eval-file $MIG upgrade $HOME_ID

echo "## 2. section roots: layout lock (accepted 2026-09-13)"
$W eval-file $MIG lock $HOME_ID

echo "## 3. saved single template override: pkiwSurface + microformat markers"
$W eval-file $MIG upgrade-templates

echo "## 4. authored h-entry wrappers (dry-run, then apply)"
$W eval-file $FIX dry-run
$W eval-file $FIX apply

echo "## 5. Primary Menu regrouping (backup first; container/remote WP-CLI, not the host phar, to keep stdout clean)"
$W post get $MENU_ID --field=post_content > "$VAULT/release/menu-$MENU_ID-staging-before.html"
grep -q 'Browse all kinds' "$VAULT/release/menu-$MENU_ID-staging-before.html" && echo "menu already regrouped" || $W post update $MENU_ID - < "$VAULT/nav/nav-proposed.html"

echo "## 6. Stream page: Browse all"
$W post get $STREAM_ID --field=post_content > "$VAULT/release/stream-$STREAM_ID-staging-before.html"
if grep -q 'cr-browse-all' "$VAULT/release/stream-$STREAM_ID-staging-before.html"; then echo "already present"; else { cat "$VAULT/release/stream-$STREAM_ID-staging-before.html"; printf '\n\n<!-- wp:pattern {"slug":"courtneyr-child/cr-browse-all"} /-->\n'; } | $W post update $STREAM_ID -; fi

echo "## 7. post-format intros (starting text, edit any time in the term editor)"
while IFS='|' read slug desc; do $W term update post_format "post-format-$slug" --by=slug --description="$desc" || true; done <<'DESC'
quote|Lines worth keeping, with their source.
status|Short updates from the day.
aside|Passing thoughts, no title needed.
image|One photo at a time.
gallery|Sets of photos from one outing.
video|Clips and talks I recorded or shared.
audio|Podcast episodes and sound notes.
DESC

echo "## 8. surface backfill (PKIW 1.8.1 recompute for posts whose terms changed after save)"
$W eval 'echo \PKIW\Post_Surface::backfill(), " posts recomputed\n";'

echo "## 9. story posters: manual — Web Stories editor > Document > Poster image (7532, 7517)"
echo "done: staging"
