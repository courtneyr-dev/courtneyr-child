<?php
/**
 * Remove the `h-entry` root class from authored Group wrappers around Post
 * Kinds cards. The Query Loop item (or the card itself) supplies the entry
 * root, so a saved `core/group` with className "h-entry" produces a second,
 * property-less entry for every microformats consumer.
 *
 *   wp eval-file fix-entry-wrappers.php dry-run  [post_id ...]
 *   wp eval-file fix-entry-wrappers.php apply    [post_id ...]
 *   wp eval-file fix-entry-wrappers.php rollback <post_id> [backup-file]
 *
 * Without ids, every published/draft/private/pending/future post whose content
 * carries "className":"…h-entry…" on a Group is a candidate.
 *
 * 0.7.46 (gap review G-01/G-03): the edit is two targeted string operations on
 * each matching Group — the `className` value inside its block comment and the
 * `class` attribute of its opening tag. Nothing is re-serialized, so every
 * other byte of the post (quoted prose, whitespace, other attributes, nested
 * Groups, h-cite objects) is untouched. Backups are verified and immutable
 * (cr-migration-backup.php); a second application reports nothing to change.
 *
 * @package CourtneyrChild
 */

require_once __DIR__ . '/cr-migration-backup.php';

$cr_mode = (string) ( $args[0] ?? 'dry-run' );
$cr_ids  = array_map( 'intval', array_slice( $args, 1 ) );

if ( 'rollback' === $cr_mode ) {
	$cr_id   = (int) ( $args[1] ?? 0 );
	$cr_file = (string) ( $args[2] ?? '' );
	if ( '' === $cr_file ) {
		$cr_files = glob( sprintf( '%s/post-%d-entry-wrapper-*.html', cr_migration_backup_dir(), $cr_id ) ) ?: array();
		sort( $cr_files );
		$cr_file = (string) end( $cr_files );
	}
	if ( ! $cr_id || '' === $cr_file || ! is_readable( $cr_file ) ) {
		WP_CLI::error( 'rollback needs a post id with a readable backup.' );
	}
	$cr_contents = (string) file_get_contents( $cr_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	cr_migration_update_content( $cr_id, $cr_contents );
	WP_CLI::success( sprintf( 'Restored post %d from %s (sha256 %s).', $cr_id, basename( $cr_file ), substr( hash( 'sha256', $cr_contents ), 0, 12 ) ) );
	return;
}

if ( ! in_array( $cr_mode, array( 'dry-run', 'apply' ), true ) ) {
	WP_CLI::error( 'Mode must be dry-run, apply or rollback.' );
}

if ( ! $cr_ids ) {
	$cr_ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			's'              => 'h-entry',
			'no_found_rows'  => true,
		)
	);
}

/**
 * Remove one class token from a whitespace-separated class list.
 *
 * @param string $classes Class list.
 * @param string $token   Token to remove.
 * @return string
 */
function cr_remove_class_token( string $classes, string $token ): string {
	$parts = preg_split( '/\s+/', trim( $classes ) ) ?: array();
	$parts = array_values( array_filter( $parts, static fn( $p ) => '' !== $p && $p !== $token ) );
	return implode( ' ', $parts );
}

/**
 * Targeted repair: for every Group block comment whose className carries the
 * h-entry token, rewrite that className and the class attribute of the
 * opening tag that follows the comment. All other bytes are preserved.
 *
 * @param string $content Post content.
 * @param int    $changed Wrappers changed (by reference).
 * @return string
 */
function cr_strip_entry_wrappers( string $content, int &$changed ): string {
	$pattern = '/(<!-- wp:group \{)([^\n]*?)(\} -->)(\s*)(<div class=")([^"]*)(")/';
	return (string) preg_replace_callback(
		$pattern,
		static function ( array $m ) use ( &$changed ): string {
			$json = $m[2];
			if ( ! preg_match( '/"className":"((?:[^"\\\\]|\\\\.)*)"/', $json, $cm ) ) {
				return $m[0];
			}
			$tokens = preg_split( '/\s+/', trim( $cm[1] ) ) ?: array();
			if ( ! in_array( 'h-entry', $tokens, true ) ) {
				return $m[0];
			}
			$new_class = cr_remove_class_token( $cm[1], 'h-entry' );
			if ( '' === $new_class ) {
				// Drop the attribute and whichever comma bound it.
				$json = preg_replace( '/,"className":"[^"]*"/', '', $json, 1, $n );
				if ( 0 === $n ) {
					$json = preg_replace( '/"className":"[^"]*",?/', '', $json, 1 );
				}
			} else {
				$json = str_replace( '"className":"' . $cm[1] . '"', '"className":"' . $new_class . '"', $json );
			}
			$tag_class = cr_remove_class_token( $m[6], 'h-entry' );
			++$changed;
			return $m[1] . $json . $m[3] . $m[4] . $m[5] . $tag_class . $m[7];
		},
		$content
	);
}

$cr_total = 0;
foreach ( $cr_ids as $cr_id ) {
	$cr_post = get_post( $cr_id );
	if ( ! $cr_post instanceof WP_Post ) {
		continue;
	}
	$cr_changed = 0;
	$cr_new     = cr_strip_entry_wrappers( $cr_post->post_content, $cr_changed );
	if ( 0 === $cr_changed || $cr_new === $cr_post->post_content ) {
		WP_CLI::log( sprintf( 'post %d: nothing to change', $cr_id ) );
		continue;
	}
	WP_CLI::log( sprintf( 'post %d "%s": %d wrapper(s) %s', $cr_id, $cr_post->post_title, $cr_changed, 'apply' === $cr_mode ? 'fixed' : 'would change' ) );
	WP_CLI::log( cr_migration_diff_summary( $cr_post->post_content, $cr_new ) );
	++$cr_total;
	if ( 'apply' !== $cr_mode ) {
		continue;
	}
	cr_migration_write_backup( sprintf( 'post-%d-entry-wrapper', $cr_id ), $cr_post->post_content, 'post:' . $cr_id );
	cr_migration_update_content( $cr_id, $cr_new );
}
WP_CLI::success( sprintf( '%s: %d post(s) %s.', $cr_mode, $cr_total, 'apply' === $cr_mode ? 'updated (verified backup + revision each)' : 'would be updated' ) );
