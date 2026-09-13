<?php
/**
 * Verified server-side backup of one option's current value.
 *
 * Usage: wp eval-file backup-option.php <option_name>
 * Prints "CRJSON:{home, option, exists, backup_file, value}".
 *
 * @package CourtneyrChild
 */

require_once __DIR__ . '/cr-migration-backup.php';

$cr_name = (string) ( $args[0] ?? '' );
if ( '' === $cr_name ) {
	WP_CLI::error( 'backup-option: option name required.' );
}
$cr_value  = get_option( $cr_name, null );
$cr_exists = null !== $cr_value;
$cr_json   = wp_json_encode( array( 'kind' => 'option', 'home' => home_url( '/' ), 'option' => $cr_name, 'exists' => $cr_exists, 'exported' => gmdate( 'c' ), 'value' => $cr_value ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$cr_file   = cr_migration_write_backup( 'option-' . $cr_name, $cr_json, 'option:' . $cr_name );
echo "\nCRJSON:" . wp_json_encode( array( 'home' => home_url( '/' ), 'option' => $cr_name, 'exists' => $cr_exists, 'backup_file' => basename( $cr_file ), 'value' => $cr_value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
