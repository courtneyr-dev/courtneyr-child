<?php
/**
 * Tests for cr_migration_parse_object(), the manifest object parser both
 * rollback paths use (migrate-homepage-sections.php, migrate-native-blocks.php).
 *
 * Usage: wp eval-file test-migration-objects.php
 * Prints one line per case and exits non-zero (WP_CLI::error) on any failure.
 * Reads nothing from the database and writes nothing.
 *
 * No strict_types declaration: WP-CLI eval-file evaluates this file inside its own scope.
 *
 * @package CourtneyrChild
 */

$cr_cases = array(
	// Accepted identities.
	array(
		'post:2651',
		array(
			'type'       => 'post',
			'id'         => 2651,
			'post_types' => array( 'page', 'post' ),
		),
	),
	array(
		'wp_template:37240',
		array(
			'type'       => 'wp_template',
			'id'         => 37240,
			'post_types' => array( 'wp_template' ),
		),
	),
	array(
		'wp_template_part:10861',
		array(
			'type'       => 'wp_template_part',
			'id'         => 10861,
			'post_types' => array( 'wp_template_part' ),
		),
	),
	// Refused identities.
	array( 'wp_navigation:9960', null ),
	array( 'wp_template_part:', null ),
	array( 'wp_template_part:0', null ),
	array( 'wp_template_part:012', null ),
	array( 'wp_template_partx:12', null ),
	array( 'xwp_template:12', null ),
	array( ' wp_template:12', null ),
	array( "wp_template:12\n", null ),
	array( 'wp_template:12 ', null ),
	array( 'wp_template:-12', null ),
	array( 'wp_template:12:13', null ),
	array( 'WP_TEMPLATE:12', null ),
	array( '', null ),
);

require_once __DIR__ . '/cr-migration-backup.php';

$cr_failures = 0;
foreach ( $cr_cases as $cr_case ) {
	list( $cr_input, $cr_expected ) = $cr_case;
	$cr_actual                      = cr_migration_parse_object( $cr_input );
	$cr_ok                          = $cr_actual === $cr_expected;
	if ( ! $cr_ok ) {
		++$cr_failures;
	}
	WP_CLI::log( sprintf( '%s %s -> %s', $cr_ok ? 'PASS' : 'FAIL', wp_json_encode( $cr_input ), wp_json_encode( $cr_actual ) ) );
}

if ( $cr_failures ) {
	WP_CLI::error( sprintf( '%d of %d cases failed.', $cr_failures, count( $cr_cases ) ) );
}
WP_CLI::success( sprintf( 'All %d cases passed.', count( $cr_cases ) ) );
