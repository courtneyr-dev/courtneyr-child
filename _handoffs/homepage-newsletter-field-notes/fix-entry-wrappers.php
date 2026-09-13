<?php
/**
 * Remove the `h-entry` root class from authored Group wrappers around Post
 * Kinds cards. The Query Loop item (or the card itself) supplies the entry
 * root, so a saved `core/group` with className "h-entry" produces a second,
 * property-less entry for every microformats consumer.
 *
 *   wp eval-file fix-entry-wrappers.php dry-run  [post_id ...]
 *   wp eval-file fix-entry-wrappers.php apply    [post_id ...]
 *   wp eval-file fix-entry-wrappers.php rollback <post_id>
 *
 * Without ids, every published/draft/private post whose content carries
 * "className":"h-entry" is a candidate. Only the class token is removed; the
 * Group and its inner blocks stay. Backups go to wp-content/cr-homepage-migration/
 * (post-<id>-entry-wrapper-<ts>.html) and every write creates a revision.
 *
 * @package CourtneyrChild
 */

$cr_mode = (string) ( $args[0] ?? 'dry-run' );
$cr_ids  = array_map( 'intval', array_slice( $args, 1 ) );
$cr_dir  = WP_CONTENT_DIR . '/cr-homepage-migration';

if ( 'rollback' === $cr_mode ) {
	$cr_id = (int) ( $args[1] ?? 0 );
	$cr_files = glob( sprintf( '%s/post-%d-entry-wrapper-*.html', $cr_dir, $cr_id ) ) ?: array();
	if ( ! $cr_id || ! $cr_files ) {
		WP_CLI::error( 'No backup for that post id.' );
	}
	sort( $cr_files );
	$cr_file = end( $cr_files );
	$cr_res  = wp_update_post( wp_slash( array( 'ID' => $cr_id, 'post_content' => (string) file_get_contents( $cr_file ) ) ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( is_wp_error( $cr_res ) ) {
		WP_CLI::error( $cr_res->get_error_message() );
	}
	WP_CLI::success( sprintf( 'Restored post %d from %s.', $cr_id, basename( $cr_file ) ) );
	return;
}

if ( ! $cr_ids ) {
	$cr_ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			's'              => '"className":"h-entry"',
			'no_found_rows'  => true,
		)
	);
}

/**
 * Strip the h-entry token from Group wrappers, recursively.
 *
 * @param array $blocks  Parsed blocks.
 * @param int   $changed Count of wrappers changed (by reference).
 * @return array
 */
function cr_strip_entry_wrappers( array $blocks, int &$changed ): array {
	foreach ( $blocks as $i => $block ) {
		if ( 'core/group' === ( $block['blockName'] ?? '' ) ) {
			$class  = (string) ( $block['attrs']['className'] ?? '' );
			$tokens = preg_split( '/\s+/', trim( $class ) ) ?: array();
			if ( in_array( 'h-entry', $tokens, true ) ) {
				$tokens = array_values( array_diff( $tokens, array( 'h-entry' ) ) );
				if ( $tokens ) {
					$blocks[ $i ]['attrs']['className'] = implode( ' ', $tokens );
				} else {
					unset( $blocks[ $i ]['attrs']['className'] );
				}
				// The opening tag's class attribute carries the token too.
				foreach ( $blocks[ $i ]['innerContent'] as $k => $chunk ) {
					if ( is_string( $chunk ) ) {
						$blocks[ $i ]['innerContent'][ $k ] = preg_replace( '/(class="[^"]*)\bh-entry\b\s?([^"]*")/', '$1$2', $chunk, 1 );
					}
				}
				$blocks[ $i ]['innerHTML'] = preg_replace( '/(class="[^"]*)\bh-entry\b\s?([^"]*")/', '$1$2', (string) $blocks[ $i ]['innerHTML'], 1 );
				++$changed;
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$blocks[ $i ]['innerBlocks'] = cr_strip_entry_wrappers( $block['innerBlocks'], $changed );
		}
	}
	return $blocks;
}

$cr_total = 0;
foreach ( $cr_ids as $cr_id ) {
	$cr_post = get_post( $cr_id );
	if ( ! $cr_post instanceof WP_Post ) {
		continue;
	}
	$cr_changed = 0;
	$cr_new     = serialize_blocks( cr_strip_entry_wrappers( parse_blocks( $cr_post->post_content ), $cr_changed ) );
	$cr_new     = str_replace( array( ' class=""', ' "' ), array( '', '"' ), $cr_new );
	$cr_new     = preg_replace( '/class="([^"]*?) "/', 'class="$1"', $cr_new );
	if ( 0 === $cr_changed || $cr_new === $cr_post->post_content ) {
		WP_CLI::log( sprintf( 'post %d: nothing to change', $cr_id ) );
		continue;
	}
	WP_CLI::log( sprintf( 'post %d "%s": %d wrapper(s) %s', $cr_id, $cr_post->post_title, $cr_changed, 'apply' === $cr_mode ? 'fixed' : 'would change' ) );
	++$cr_total;
	if ( 'apply' !== $cr_mode ) {
		continue;
	}
	wp_mkdir_p( $cr_dir );
	file_put_contents( sprintf( '%s/post-%d-entry-wrapper-%s.html', $cr_dir, $cr_id, gmdate( 'Ymd-His' ) ), $cr_post->post_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( $cr_dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$cr_res = wp_update_post( wp_slash( array( 'ID' => $cr_id, 'post_content' => $cr_new ) ), true );
	if ( is_wp_error( $cr_res ) ) {
		WP_CLI::error( $cr_res->get_error_message() );
	}
}
WP_CLI::success( sprintf( '%s: %d post(s) %s.', $cr_mode, $cr_total, 'apply' === $cr_mode ? 'updated (backup + revision each)' : 'would be updated' ) );
