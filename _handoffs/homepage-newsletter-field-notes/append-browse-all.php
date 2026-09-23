<?php
/**
 * Append the Browse all pattern reference to the Stream page (accepted 2026-09-13).
 *
 *   wp eval-file append-browse-all.php preview <page_id>
 *   wp eval-file append-browse-all.php apply   <page_id>
 *   wp eval-file append-browse-all.php rollback <page_id> <backup-file> [preview]
 *
 * @package CourtneyrChild
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

require_once __DIR__ . '/cr-migration-backup.php';

$cr_mode = (string) ( $args[0] ?? 'preview' );
$cr_id   = (int) ( $args[1] ?? 0 );
$cr_post = $cr_id ? get_post( $cr_id ) : null;
if ( ! $cr_post instanceof WP_Post || 'page' !== $cr_post->post_type || 'stream' !== $cr_post->post_name ) {
	WP_CLI::error( 'Pass the id of the page whose slug is "stream".' );
}
if ( 'rollback' === $cr_mode ) {
	$cr_file     = (string) ( $args[2] ?? '' );
	$cr_contents = cr_migration_read_verified_backup( $cr_file, 'post:' . $cr_id );
	if ( $cr_post->post_content === $cr_contents ) {
		WP_CLI::success( sprintf( 'Page %d already matches %s; nothing to restore.', $cr_id, basename( $cr_file ) ) );
		return;
	}
	WP_CLI::log( cr_migration_diff_summary( $cr_post->post_content, $cr_contents ) );
	if ( in_array( 'preview', array_map( 'strval', $args ), true ) ) {
		WP_CLI::success( 'Preview only; nothing written.' );
		return;
	}
	$cr_before = cr_migration_write_backup( sprintf( 'page-%d-before-rollback', $cr_id ), $cr_post->post_content, 'post:' . $cr_id );
	cr_migration_update_content( $cr_id, $cr_contents );
	WP_CLI::success( sprintf( 'Restored page %d from %s. Pre-rollback content: %s.', $cr_id, basename( $cr_file ), basename( $cr_before ) ) );
	return;
}
if ( ! WP_Block_Patterns_Registry::get_instance()->is_registered( 'courtneyr-child/cr-browse-all' ) ) {
	WP_CLI::error( 'Pattern courtneyr-child/cr-browse-all is not registered on this site (theme 0.7.46 + pattern cache).' );
}
if ( str_contains( $cr_post->post_content, 'courtneyr-child/cr-browse-all' ) ) {
	WP_CLI::success( sprintf( 'Page %d already carries the Browse all pattern; nothing to change.', $cr_id ) );
	return;
}
$cr_new = rtrim( $cr_post->post_content ) . "\n\n<!-- wp:pattern {\"slug\":\"courtneyr-child/cr-browse-all\"} /-->\n";
WP_CLI::log( cr_migration_diff_summary( $cr_post->post_content, $cr_new ) );
if ( 'apply' !== $cr_mode ) {
	WP_CLI::success( 'Preview only; nothing written.' );
	return;
}
$cr_backup = cr_migration_write_backup( sprintf( 'page-%d-browse-all', $cr_id ), $cr_post->post_content, 'post:' . $cr_id );
cr_migration_update_content( $cr_id, $cr_new );
WP_CLI::success( sprintf( 'Browse all appended to page %d. Backup: %s.', $cr_id, $cr_backup ) );
