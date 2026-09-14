<?php
/**
 * Restore one option from a backup written by backup-option.php.
 *
 * Usage: wp eval-file restore-option.php <backup-file> [preview] [allow-sanitized=key,key]
 *
 * Refuses unless the file is the manifest-recorded backup of option:<name> on
 * this site (sha256, home and object all match). Compares the stored value with
 * the backup as JSON, prints the differing top-level keys, writes a backup of
 * the current value first (so the restore is itself reversible), then restores
 * the value (or deletes the option if the backup recorded it as absent) and
 * verifies the stored result. Prints "CRJSON:{...}".
 *
 * update_option() runs the value through sanitize_option(), so a backup can hold
 * keys the owning plugin no longer accepts (Yoast drops display-metabox-tax-*
 * keys for taxonomies that aren't registered). The script runs the same
 * sanitizer without writing, reports those keys as sanitizer_changes, refuses
 * to write unless allow-sanitized names exactly that set, and then verifies the
 * stored value equals the sanitized backup.
 *
 * @package CourtneyrChild
 */

require_once __DIR__ . '/cr-migration-backup.php';

$cr_file    = (string) ( $args[0] ?? '' );
$cr_preview = in_array( 'preview', array_map( 'strval', $args ), true );
$cr_allowed = array();
foreach ( array_map( 'strval', $args ) as $cr_arg ) {
	if ( 0 === strpos( $cr_arg, 'allow-sanitized=' ) ) {
		$cr_allowed = array_values( array_filter( explode( ',', substr( $cr_arg, strlen( 'allow-sanitized=' ) ) ) ) );
	}
}
$cr_data = is_readable( $cr_file ) ? json_decode( (string) file_get_contents( $cr_file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
if ( ! is_array( $cr_data ) || 'option' !== ( $cr_data['kind'] ?? '' ) || '' === (string) ( $cr_data['option'] ?? '' ) ) {
	WP_CLI::error( 'restore-option needs a readable backup written by backup-option.php.' );
}
$cr_name = (string) $cr_data['option'];
cr_migration_read_verified_backup( $cr_file, 'option:' . $cr_name );

$cr_exists  = ! empty( $cr_data['exists'] );
$cr_value   = $cr_data['value'] ?? null;
$cr_current = get_option( $cr_name, null );
$cr_enc     = static fn( $v ) => (string) wp_json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$cr_diff    = static function ( $a, $b ) use ( $cr_enc ) {
	if ( ! is_array( $a ) || ! is_array( $b ) ) {
		return $cr_enc( $a ) === $cr_enc( $b ) ? array() : array( '(value)' );
	}
	$keys = array();
	foreach ( array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) ) as $k ) {
		if ( ! array_key_exists( $k, $a ) || ! array_key_exists( $k, $b ) || $cr_enc( $a[ $k ] ) !== $cr_enc( $b[ $k ] ) ) {
			$keys[] = (string) $k;
		}
	}
	return $keys;
};

$cr_expected  = $cr_exists ? sanitize_option( $cr_name, $cr_value ) : null;
$cr_sanitized = $cr_exists ? $cr_diff( $cr_value, $cr_expected ) : array();
$cr_same      = $cr_exists ? ( null !== $cr_current && ( $cr_enc( $cr_current ) === $cr_enc( $cr_value ) || $cr_enc( $cr_current ) === $cr_enc( $cr_expected ) ) ) : null === $cr_current;
$cr_keys      = ( is_array( $cr_current ) && is_array( $cr_value ) ) ? $cr_diff( $cr_current, $cr_value ) : array();
$cr_report    = array( 'home' => home_url( '/' ), 'option' => $cr_name, 'backup' => basename( $cr_file ), 'identical' => $cr_same, 'differing_keys' => $cr_keys, 'sanitizer_changes' => $cr_sanitized, 'preview' => $cr_preview );
if ( $cr_same ) {
	echo "\nCRJSON:" . wp_json_encode( $cr_report + array( 'restored' => false ), JSON_UNESCAPED_SLASHES ) . "\n";
	WP_CLI::success( sprintf( 'Option %s already matches %s; nothing to restore.', $cr_name, basename( $cr_file ) ) );
	return;
}
if ( $cr_preview ) {
	echo "\nCRJSON:" . wp_json_encode( $cr_report + array( 'restored' => false ), JSON_UNESCAPED_SLASHES ) . "\n";
	WP_CLI::success( 'Preview only; nothing written.' );
	return;
}
$cr_unapproved = array_diff( $cr_sanitized, $cr_allowed );
$cr_unexpected = array_diff( $cr_allowed, $cr_sanitized );
if ( $cr_unapproved || $cr_unexpected ) {
	echo "\nCRJSON:" . wp_json_encode( $cr_report + array( 'restored' => false ), JSON_UNESCAPED_SLASHES ) . "\n";
	WP_CLI::error( sprintf( 'sanitize_option( %s ) changes keys [%s] of the backup; rerun with allow-sanitized=%s after checking them. Nothing written.', $cr_name, implode( ',', $cr_sanitized ), implode( ',', $cr_sanitized ) ) );
}
$cr_before_json = wp_json_encode( array( 'kind' => 'option', 'home' => home_url( '/' ), 'option' => $cr_name, 'exists' => null !== $cr_current, 'exported' => gmdate( 'c' ), 'value' => $cr_current ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$cr_before      = cr_migration_write_backup( 'option-' . $cr_name . '-before-restore', (string) $cr_before_json, 'option:' . $cr_name );
if ( $cr_exists ) {
	update_option( $cr_name, $cr_value );
} else {
	delete_option( $cr_name );
}
wp_cache_delete( $cr_name, 'options' );
wp_cache_delete( 'alloptions', 'options' );
$cr_after = get_option( $cr_name, null );
$cr_ok    = $cr_exists ? ( null !== $cr_after && $cr_enc( $cr_after ) === $cr_enc( $cr_expected ) ) : null === $cr_after;
echo "\nCRJSON:" . wp_json_encode( $cr_report + array( 'restored' => $cr_ok, 'before_restore_backup' => basename( $cr_before ) ), JSON_UNESCAPED_SLASHES ) . "\n";
if ( ! $cr_ok ) {
	WP_CLI::error( sprintf( 'Option %s did not store the sanitized backup value; the pre-restore value is in %s.', $cr_name, basename( $cr_before ) ) );
}
WP_CLI::success( sprintf( 'Restored option %s from %s. Pre-restore value: %s.', $cr_name, basename( $cr_file ), basename( $cr_before ) ) );
