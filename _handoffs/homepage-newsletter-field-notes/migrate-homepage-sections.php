<?php
/**
 * Homepage sections migration: swap the inner blocks of the two homepage
 * sections for the courtneyr-child patterns, keeping each section wrapper
 * (its background colour, alignment, anchor-driven torn edges and position in
 * the page rotation) untouched.
 *
 * Usage:
 *
 *   wp eval-file migrate-homepage-sections.php dry-run [post_id]
 *   wp eval-file migrate-homepage-sections.php apply   [post_id]
 *   wp eval-file migrate-homepage-sections.php rollback <backup-file> [post_id]
 *
 * post_id defaults to the page set as the static front page. `apply` writes a
 * timestamped backup of the previous content to wp-content/cr-homepage-migration/
 * and updates the page through wp_update_post(), which also creates a revision.
 * `rollback` restores a backup file. Run locally first; production needs its own
 * authorization.
 *
 * No strict_types declaration: WP-CLI eval-file evaluates this file inside its own scope.
 *
 * @package CourtneyrChild
 */

$cr_mode    = (string) ( $args[0] ?? 'dry-run' );
$cr_targets = array(
	'cr-home-features'   => 'courtneyr-child/cr-home-newsletter-reasons',
	'cr-home-fieldnotes' => 'courtneyr-child/cr-home-field-notes',
);
$cr_post_id = (int) ( 'rollback' === $cr_mode ? ( $args[2] ?? 0 ) : ( $args[1] ?? 0 ) );
if ( ! $cr_post_id ) {
	$cr_post_id = (int) get_option( 'page_on_front' );
}
$cr_post = $cr_post_id ? get_post( $cr_post_id ) : null;

if ( ! $cr_post instanceof WP_Post || 'page' !== $cr_post->post_type ) {
	WP_CLI::error( 'No page found. Pass a page ID or set a static front page.' );
}
WP_CLI::log( sprintf( 'Target: page %d "%s" (%s)', $cr_post->ID, $cr_post->post_title, get_permalink( $cr_post ) ) );

$cr_backup_dir = WP_CONTENT_DIR . '/cr-homepage-migration';

if ( 'rollback' === $cr_mode ) {
	$cr_file = (string) ( $args[1] ?? '' );
	if ( '' === $cr_file || ! is_readable( $cr_file ) ) {
		WP_CLI::error( 'rollback needs a readable backup file path.' );
	}
	$cr_result = wp_update_post(
		wp_slash(
			array(
				'ID'           => $cr_post->ID,
				'post_content' => (string) file_get_contents( $cr_file ),
			)
		),
		true
	);
	if ( is_wp_error( $cr_result ) ) {
		WP_CLI::error( $cr_result->get_error_message() );
	}
	WP_CLI::success( sprintf( 'Restored %s into page %d.', basename( $cr_file ), $cr_post->ID ) );
	return;
}

$cr_registry = WP_Block_Patterns_Registry::get_instance();
$cr_blocks   = parse_blocks( $cr_post->post_content );
$cr_replaced = array();

foreach ( $cr_blocks as $cr_index => $cr_block ) {
	$cr_class = (string) ( $cr_block['attrs']['className'] ?? '' );
	foreach ( $cr_targets as $cr_marker => $cr_pattern_slug ) {
		$cr_classes = preg_split( '/\s+/', trim( $cr_class ) );
		if ( ! is_array( $cr_classes ) || ! in_array( $cr_marker, $cr_classes, true ) ) {
			continue;
		}
		$cr_pattern = $cr_registry->get_registered( $cr_pattern_slug );
		if ( ! $cr_pattern ) {
			WP_CLI::error( sprintf( 'Pattern %s is not registered. Activate the theme version that ships it.', $cr_pattern_slug ) );
		}
		$cr_new_inner                           = array_values( array_filter( parse_blocks( $cr_pattern['content'] ), static fn( $b ) => ! empty( $b['blockName'] ) ) );
		$cr_strings                             = array_values( array_filter( $cr_block['innerContent'], 'is_string' ) );
		$cr_open                                = $cr_strings[0] ?? '';
		$cr_close                               = count( $cr_strings ) > 1 ? end( $cr_strings ) : '';
		$cr_content                             = array_merge( array( $cr_open ), array_fill( 0, count( $cr_new_inner ), null ), array( $cr_close ) );
		$cr_old_names                           = array_map( static fn( $b ) => (string) $b['blockName'], array_filter( $cr_block['innerBlocks'], static fn( $b ) => ! empty( $b['blockName'] ) ) );
		$cr_blocks[ $cr_index ]['innerBlocks']  = $cr_new_inner;
		$cr_blocks[ $cr_index ]['innerContent'] = $cr_content;
		$cr_replaced[]                          = sprintf( 'block #%d (%s): %d inner block(s) [%s] -> %d block(s) from %s', $cr_index, $cr_marker, count( $cr_old_names ), implode( ', ', $cr_old_names ), count( $cr_new_inner ), $cr_pattern_slug );
	}
}

if ( count( $cr_replaced ) !== count( $cr_targets ) ) {
	WP_CLI::warning( sprintf( 'Matched %d of %d sections. Top-level classes: %s', count( $cr_replaced ), count( $cr_targets ), implode( ' | ', array_map( static fn( $b ) => (string) ( $b['blockName'] ?? '(html)' ) . ' .' . (string) ( $b['attrs']['className'] ?? '' ), $cr_blocks ) ) ) );
}
foreach ( $cr_replaced as $cr_line ) {
	WP_CLI::log( '  ' . $cr_line );
}

if ( 'apply' !== $cr_mode ) {
	WP_CLI::success( 'Dry run only; nothing written. Re-run with "apply" to migrate.' );
	return;
}
if ( empty( $cr_replaced ) ) {
	WP_CLI::error( 'Nothing to replace; aborting.' );
}

if ( ! wp_mkdir_p( $cr_backup_dir ) ) {
	WP_CLI::error( 'Cannot create ' . $cr_backup_dir );
}
$cr_backup = sprintf( '%s/page-%d-%s.html', $cr_backup_dir, $cr_post->ID, gmdate( 'Ymd-His' ) );
file_put_contents( $cr_backup, $cr_post->post_content );
file_put_contents( $cr_backup_dir . '/index.php', "<?php // Silence is golden.\n" );

$cr_result = wp_update_post(
	wp_slash(
		array(
			'ID'           => $cr_post->ID,
			'post_content' => serialize_blocks( $cr_blocks ),
		)
	),
	true
);
if ( is_wp_error( $cr_result ) ) {
	WP_CLI::error( $cr_result->get_error_message() );
}
WP_CLI::success( sprintf( 'Migrated page %d. Backup: %s (also available as a revision).', $cr_post->ID, $cr_backup ) );
