<?php
/**
 * Set one term's description to exact bytes and verify the stored value.
 *
 * Usage: wp eval-file restore-term.php <taxonomy> <term_id> <slug> <description_b64>
 *
 * Refuses when the term is missing or its slug differs from the recorded one
 * (target identity). Prints one line "CRJSON:{ok, term_id, slug, before_b64,
 * after_b64, changed}". Exits non-zero on any failure so a caller running
 * with pipefail stops.
 *
 * @package CourtneyrChild
 */

$cr_taxonomy = (string) ( $args[0] ?? '' );
$cr_term_id  = (int) ( $args[1] ?? 0 );
$cr_slug     = (string) ( $args[2] ?? '' );
$cr_b64      = (string) ( $args[3] ?? '' );
$cr_desc     = base64_decode( $cr_b64, true );
if ( '' === $cr_taxonomy || $cr_term_id <= 0 || '' === $cr_slug || false === $cr_desc || base64_encode( $cr_desc ) !== $cr_b64 ) {
	WP_CLI::error( 'restore-term: invalid arguments (taxonomy, term_id, slug, strict base64 description).' );
}
$cr_term = get_term( $cr_term_id, $cr_taxonomy );
if ( ! $cr_term instanceof WP_Term ) {
	WP_CLI::error( sprintf( 'restore-term: term %d does not exist in %s.', $cr_term_id, $cr_taxonomy ) );
}
if ( $cr_term->slug !== $cr_slug ) {
	WP_CLI::error( sprintf( 'restore-term: term %d is "%s" here, not "%s"; refusing.', $cr_term_id, $cr_term->slug, $cr_slug ) );
}
$cr_before = (string) $cr_term->description;
if ( $cr_before !== $cr_desc ) {
	$cr_result = wp_update_term( $cr_term_id, $cr_taxonomy, array( 'description' => $cr_desc ) );
	if ( is_wp_error( $cr_result ) ) {
		WP_CLI::error( sprintf( 'restore-term: update of %d failed: %s', $cr_term_id, $cr_result->get_error_message() ) );
	}
}
clean_term_cache( $cr_term_id, $cr_taxonomy );
$cr_after = get_term( $cr_term_id, $cr_taxonomy );
$cr_after = $cr_after instanceof WP_Term ? (string) $cr_after->description : null;
if ( $cr_after !== $cr_desc ) {
	WP_CLI::error( sprintf( 'restore-term: term %d stored %s, expected %s.', $cr_term_id, var_export( $cr_after, true ), var_export( $cr_desc, true ) ) );
}
echo "\nCRJSON:" . wp_json_encode(
	array(
		'ok'         => true,
		'term_id'    => $cr_term_id,
		'slug'       => $cr_slug,
		'before_b64' => base64_encode( $cr_before ),
		'after_b64'  => base64_encode( $cr_after ),
		'changed'    => $cr_before !== $cr_after,
	)
) . "\n";
