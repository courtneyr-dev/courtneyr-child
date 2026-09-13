<?php
/**
 * Export a taxonomy's terms as a verified backup and print the payload.
 *
 * Usage: wp eval-file backup-terms.php <taxonomy> [preview]
 *
 *   <taxonomy>  Taxonomy slug (post_format).
 *   preview     Print the payload only; do not write the server-side backup.
 *
 * Prints a single line "CRJSON:{...}" carrying {home, taxonomy, exported,
 * backup_file, terms:[{term_id, slug, name, description, description_b64}]}.
 * Callers must take the CRJSON line only: the host prints PHP notices around
 * WP-CLI output. Descriptions are carried as base64 so tabs, newlines and
 * quotes survive every shell boundary byte for byte.
 *
 * @package CourtneyrChild
 */

require_once __DIR__ . '/cr-migration-backup.php';

$cr_taxonomy = (string) ( $args[0] ?? '' );
$cr_preview  = in_array( 'preview', $args, true );
if ( '' === $cr_taxonomy || ! taxonomy_exists( $cr_taxonomy ) ) {
	WP_CLI::error( sprintf( 'Unknown taxonomy "%s".', $cr_taxonomy ) );
}
$cr_terms = get_terms( array( 'taxonomy' => $cr_taxonomy, 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC' ) );
if ( is_wp_error( $cr_terms ) ) {
	WP_CLI::error( $cr_terms->get_error_message() );
}
$cr_rows = array();
foreach ( $cr_terms as $cr_term ) {
	$cr_rows[] = array(
		'term_id'         => (int) $cr_term->term_id,
		'slug'            => (string) $cr_term->slug,
		'name'            => (string) $cr_term->name,
		'description'     => (string) $cr_term->description,
		'description_b64' => base64_encode( (string) $cr_term->description ),
	);
}
$cr_payload = array(
	'home'        => home_url( '/' ),
	'taxonomy'    => $cr_taxonomy,
	'exported'    => gmdate( 'c' ),
	'backup_file' => null,
	'terms'       => $cr_rows,
);
if ( ! $cr_preview ) {
	$cr_json                  = wp_json_encode( $cr_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$cr_file                  = cr_migration_write_backup( 'terms-' . $cr_taxonomy, $cr_json, 'taxonomy:' . $cr_taxonomy );
	$cr_payload['backup_file'] = basename( $cr_file );
}
echo "\nCRJSON:" . wp_json_encode( $cr_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
