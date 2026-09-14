<?php
/**
 * Reversible, verified `_pkiw_surface` backfill.
 *
 * Usage: wp eval-file surface-backfill.php <mode> [arg]
 *
 *   export              Write a verified server-side backup of the complete
 *                       current state (every post: all meta rows, including
 *                       absence) and print it.
 *   preview             Print the proposed change set: posts whose stored value
 *                       differs from Post_Surface::get(), plus duplicate rows.
 *   apply <backup>      Refuse unless <backup> names an export made in this
 *                       environment; then set exactly one row per post to the
 *                       computed value, and verify: zero mismatches, zero
 *                       duplicate rows, every post present. Non-zero exit and
 *                       the list of unresolved posts otherwise.
 *   verify              The apply postcondition on its own.
 *   rollback <backup>   Restore the exact rows from the export (delete where
 *                       absent), then compare every post to the export.
 *
 * Every mode prints one "CRJSON:{...}" line; take that line only, the host
 * prints PHP notices around WP-CLI output.
 *
 * @package CourtneyrChild
 */

require_once __DIR__ . '/cr-migration-backup.php';

if ( ! class_exists( '\PKIW\Post_Surface' ) ) {
	WP_CLI::error( 'Post Kinds for IndieWeb is not active; nothing was changed.' );
}

$cr_mode = (string) ( $args[0] ?? '' );
$cr_arg  = (string) ( $args[1] ?? '' );

/**
 * Every post ID (any status), ascending.
 *
 * @return int[]
 */
function cr_surface_ids(): array {
	return array_map( 'intval', get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) ) );
}

/**
 * Snapshot of the stored rows and the computed value for every post.
 *
 * @return array<int, array{rows: string[], expected: string, status: string}>
 */
function cr_surface_state(): array {
	$state = array();
	foreach ( cr_surface_ids() as $id ) {
		wp_cache_delete( $id, 'post_meta' );
		$rows          = array_map( 'strval', (array) get_post_meta( $id, '_pkiw_surface', false ) );
		$state[ $id ] = array( 'rows' => array_values( $rows ), 'expected' => \PKIW\Post_Surface::get( $id ), 'status' => (string) get_post_status( $id ) );
	}
	return $state;
}

/**
 * Posts whose stored state is not exactly one row equal to the computed value.
 *
 * @param array $state State from cr_surface_state().
 * @return array
 */
function cr_surface_changes( array $state ): array {
	$changes = array();
	foreach ( $state as $id => $s ) {
		$stored = $s['rows'][0] ?? null;
		if ( 1 !== count( $s['rows'] ) || $stored !== $s['expected'] ) {
			$changes[] = array(
				'id'       => $id,
				'status'   => $s['status'],
				'title'    => get_the_title( $id ),
				'from'     => $s['rows'],
				'to'       => $s['expected'],
				'reason'   => count( $s['rows'] ) > 1 ? 'duplicate rows' : ( null === $stored ? 'absent' : 'value differs' ),
				'kinds'    => wp_get_object_terms( $id, 'kind', array( 'fields' => 'slugs' ) ),
				'formats'  => wp_get_object_terms( $id, 'post_format', array( 'fields' => 'slugs' ) ),
				'promote'  => (string) get_post_meta( $id, 'pkiw_promote', true ),
				'chars'    => mb_strlen( (string) get_post_field( 'post_content', $id ) ),
			);
		}
	}
	return $changes;
}

/**
 * Read and validate a backup written by the export mode.
 *
 * @param string $name Backup file basename.
 * @return array
 */
function cr_surface_read_backup( string $name ): array {
	$file = cr_migration_backup_dir() . '/' . basename( $name );
	if ( '' === $name || ! is_file( $file ) ) {
		WP_CLI::error( sprintf( 'Backup %s does not exist; nothing was changed.', $name ) );
	}
	$raw  = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$data = json_decode( $raw, true );
	$home = home_url( '/' );
	if ( ! is_array( $data ) || ( $data['home'] ?? '' ) !== $home || ( $data['kind'] ?? '' ) !== 'surface-state' || ! is_array( $data['posts'] ?? null ) ) {
		WP_CLI::error( sprintf( 'Backup %s is not a surface-state export for %s; nothing was changed.', $name, $home ) );
	}
	$manifest = cr_migration_backup_dir() . '/manifest.jsonl';
	$known    = false;
	foreach ( file( $manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: array() as $line ) {
		$m = json_decode( $line, true );
		if ( is_array( $m ) && ( $m['file'] ?? '' ) === basename( $name ) && ( $m['sha256'] ?? '' ) === hash( 'sha256', $raw ) ) {
			$known = true;
		}
	}
	if ( ! $known ) {
		WP_CLI::error( sprintf( 'Backup %s is not in the manifest with a matching sha256; nothing was changed.', $name ) );
	}
	return $data;
}

/**
 * Set exactly one row for a post.
 *
 * @param int    $id    Post ID.
 * @param string $value Value.
 */
function cr_surface_set_single( int $id, string $value ): void {
	delete_post_meta( $id, '_pkiw_surface' );
	if ( ! add_post_meta( $id, '_pkiw_surface', $value, true ) ) {
		WP_CLI::warning( sprintf( 'post %d: could not write _pkiw_surface', $id ) );
	}
	wp_cache_delete( $id, 'post_meta' );
}

/**
 * Postcondition: every post has exactly one row equal to the computed value.
 *
 * @return array{ok: bool, posts: int, unresolved: array}
 */
function cr_surface_verify(): array {
	$state      = cr_surface_state();
	$unresolved = cr_surface_changes( $state );
	return array( 'ok' => empty( $unresolved ), 'posts' => count( $state ), 'unresolved' => $unresolved );
}

switch ( $cr_mode ) {
	case 'export':
		$cr_state = cr_surface_state();
		$cr_posts = array();
		foreach ( $cr_state as $cr_id => $cr_s ) {
			$cr_posts[] = array( 'id' => $cr_id, 'status' => $cr_s['status'], 'rows' => $cr_s['rows'] );
		}
		$cr_payload = array( 'kind' => 'surface-state', 'home' => home_url( '/' ), 'exported' => gmdate( 'c' ), 'pkiw' => defined( 'PKIW_VERSION' ) ? PKIW_VERSION : null, 'posts' => $cr_posts );
		$cr_json    = wp_json_encode( $cr_payload, JSON_UNESCAPED_SLASHES );
		$cr_file    = cr_migration_write_backup( 'surface-state', $cr_json, 'meta:_pkiw_surface' );
		$cr_payload['backup_file'] = basename( $cr_file );
		$cr_payload['present']     = count( array_filter( $cr_posts, static fn( $p ) => ! empty( $p['rows'] ) ) );
		$cr_payload['absent']      = count( $cr_posts ) - $cr_payload['present'];
		echo "\nCRJSON:" . wp_json_encode( $cr_payload, JSON_UNESCAPED_SLASHES ) . "\n";
		break;

	case 'preview':
		$cr_state   = cr_surface_state();
		$cr_changes = cr_surface_changes( $cr_state );
		echo "\nCRJSON:" . wp_json_encode( array( 'kind' => 'surface-change-set', 'home' => home_url( '/' ), 'posts' => count( $cr_state ), 'changes' => $cr_changes ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		break;

	case 'apply':
		cr_surface_read_backup( $cr_arg );
		$cr_before  = cr_surface_state();
		$cr_changes = cr_surface_changes( $cr_before );
		foreach ( $cr_changes as $cr_c ) {
			cr_surface_set_single( (int) $cr_c['id'], (string) $cr_c['to'] );
		}
		$cr_result = cr_surface_verify();
		echo "\nCRJSON:" . wp_json_encode( array( 'kind' => 'surface-apply', 'home' => home_url( '/' ), 'backup' => basename( $cr_arg ), 'attempted' => count( $cr_changes ), 'posts' => $cr_result['posts'], 'ok' => $cr_result['ok'], 'unresolved' => $cr_result['unresolved'] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		if ( ! $cr_result['ok'] ) {
			WP_CLI::error( sprintf( 'surface backfill: %d post(s) still differ after apply.', count( $cr_result['unresolved'] ) ) );
		}
		break;

	case 'verify':
		$cr_result = cr_surface_verify();
		echo "\nCRJSON:" . wp_json_encode( array( 'kind' => 'surface-verify', 'home' => home_url( '/' ), 'posts' => $cr_result['posts'], 'ok' => $cr_result['ok'], 'unresolved' => $cr_result['unresolved'] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		if ( ! $cr_result['ok'] ) {
			WP_CLI::error( sprintf( 'surface verify: %d post(s) differ from the computed value.', count( $cr_result['unresolved'] ) ) );
		}
		break;

	case 'rollback':
		cr_migration_read_verified_backup( (string) $cr_arg, 'meta:_pkiw_surface' );
		$cr_data     = cr_surface_read_backup( $cr_arg );
		$cr_wanted   = array();
		foreach ( $cr_data['posts'] as $cr_p ) {
			$cr_wanted[ (int) $cr_p['id'] ] = array_map( 'strval', (array) $cr_p['rows'] );
		}
		$cr_restored = 0;
		foreach ( $cr_wanted as $cr_id => $cr_rows ) {
			wp_cache_delete( $cr_id, 'post_meta' );
			$cr_now = array_map( 'strval', (array) get_post_meta( $cr_id, '_pkiw_surface', false ) );
			if ( array_values( $cr_now ) === array_values( $cr_rows ) ) {
				continue;
			}
			delete_post_meta( $cr_id, '_pkiw_surface' );
			foreach ( $cr_rows as $cr_row ) {
				add_post_meta( $cr_id, '_pkiw_surface', $cr_row, false );
			}
			wp_cache_delete( $cr_id, 'post_meta' );
			++$cr_restored;
		}
		$cr_bad = array();
		foreach ( $cr_wanted as $cr_id => $cr_rows ) {
			$cr_now = array_map( 'strval', (array) get_post_meta( $cr_id, '_pkiw_surface', false ) );
			if ( array_values( $cr_now ) !== array_values( $cr_rows ) ) {
				$cr_bad[] = array( 'id' => $cr_id, 'now' => $cr_now, 'wanted' => $cr_rows );
			}
		}
		$cr_extra = array_values( array_diff( cr_surface_ids(), array_keys( $cr_wanted ) ) );
		echo "\nCRJSON:" . wp_json_encode( array( 'kind' => 'surface-rollback', 'home' => home_url( '/' ), 'backup' => basename( $cr_arg ), 'posts_in_backup' => count( $cr_wanted ), 'restored' => $cr_restored, 'mismatches' => $cr_bad, 'posts_not_in_backup' => $cr_extra, 'ok' => empty( $cr_bad ) ), JSON_UNESCAPED_SLASHES ) . "\n";
		if ( ! empty( $cr_bad ) ) {
			WP_CLI::error( sprintf( 'surface rollback: %d post(s) do not match the backup.', count( $cr_bad ) ) );
		}
		break;

	default:
		WP_CLI::error( 'Usage: surface-backfill.php export|preview|apply <backup>|verify|rollback <backup>' );
}
