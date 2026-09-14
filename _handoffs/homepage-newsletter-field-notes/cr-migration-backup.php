<?php
/**
 * Shared, verified backups for the 0.7.46 content migrations.
 *
 * Every write first stores the previous value as a file whose bytes are read
 * back and hashed, then made read-only (0444) where the filesystem allows; a
 * manifest line records environment, object, bytes, sha256 and the verified
 * file mode. Read-only is not immutable: the owning account can still change
 * or delete the file, so the sha256 in the manifest is the integrity check.
 * Any failure to write or verify the bytes aborts before the WordPress write.
 *
 * Included by the migration scripts (WP-CLI eval-file scope, no strict_types).
 *
 * @package CourtneyrChild
 */

if ( ! function_exists( 'cr_migration_backup_dir' ) ) {
	/**
	 * Backup directory (overridable for tests via CR_MIGRATION_BACKUP_DIR).
	 *
	 * @return string
	 */
	function cr_migration_backup_dir(): string {
		$env = getenv( 'CR_MIGRATION_BACKUP_DIR' );
		return is_string( $env ) && '' !== $env ? $env : WP_CONTENT_DIR . '/cr-homepage-migration';
	}

	/**
	 * Write a hash-verified backup, made read-only where possible; abort if the bytes do not verify.
	 *
	 * @param string $label    File label (e.g. "page-2651-upgrade").
	 * @param string $contents Previous value.
	 * @param string $object   Object identity for the manifest (e.g. "post:2651").
	 * @return string Backup file path.
	 */
	function cr_migration_write_backup( string $label, string $contents, string $object ): string {
		$dir = cr_migration_backup_dir();
		if ( ! wp_mkdir_p( $dir ) || ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			WP_CLI::error( sprintf( 'Backup directory %s is not writable; nothing was changed.', $dir ) );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) && false === file_put_contents( $index, "<?php // Silence is golden.\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::error( 'Cannot write the backup directory index; nothing was changed.' );
		}
		$file = sprintf( '%s/%s-%s.html', $dir, preg_replace( '/[^a-z0-9_-]+/i', '-', $label ), gmdate( 'Ymd-His' ) );
		if ( file_exists( $file ) ) {
			$file = sprintf( '%s/%s-%s-%s.html', $dir, preg_replace( '/[^a-z0-9_-]+/i', '-', $label ), gmdate( 'Ymd-His' ), wp_generate_password( 6, false ) );
		}
		$written = file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written || $written !== strlen( $contents ) ) {
			WP_CLI::error( sprintf( 'Backup write to %s failed (%s of %d bytes); nothing was changed.', $file, var_export( $written, true ), strlen( $contents ) ) );
		}
		$read = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $read || hash( 'sha256', $read ) !== hash( 'sha256', $contents ) ) {
			WP_CLI::error( sprintf( 'Backup %s does not read back identically; nothing was changed.', $file ) );
		}
		@chmod( $file, 0444 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		clearstatcache( true, $file );
		$mode = substr( sprintf( '%o', (int) fileperms( $file ) ), -4 );
		if ( '0444' !== $mode ) {
			WP_CLI::warning( sprintf( 'Backup %s stays mode %s (chmod 0444 did not take effect); its manifest sha256 remains the integrity check.', basename( $file ), $mode ) );
		}
		$line = wp_json_encode(
			array(
				'time'   => gmdate( 'c' ),
				'home'   => home_url( '/' ),
				'object' => $object,
				'file'   => basename( $file ),
				'bytes'  => strlen( $contents ),
				'sha256' => hash( 'sha256', $contents ),
				'mode'   => $mode,
			)
		) . "\n";
		if ( false === file_put_contents( $dir . '/manifest.jsonl', $line, FILE_APPEND | LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::error( 'Cannot append to the backup manifest; nothing was changed.' );
		}
		WP_CLI::log( sprintf( 'backup: %s (%d bytes, sha256 %s, mode %s)', basename( $file ), strlen( $contents ), substr( hash( 'sha256', $contents ), 0, 12 ), $mode ) );
		return $file;
	}

	/**
	 * Update a post's content through wp_update_post(), aborting on error.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content New content.
	 */
	function cr_migration_update_content( int $post_id, string $content ): void {
		$result = wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $content ) ), true );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		$check = get_post( $post_id );
		if ( ! $check instanceof WP_Post || $check->post_content !== $content ) {
			WP_CLI::error( sprintf( 'Post %d did not store the expected content.', $post_id ) );
		}
	}

	/**
	 * Read a backup for restore only after proving it is the manifest-recorded
	 * backup of $object on this site: the file has a manifest line, its sha256
	 * matches that line, the line's home is this site and its object is exactly
	 * $object. Any mismatch stops with nothing changed.
	 *
	 * @param string $file   Backup file path.
	 * @param string $object Expected manifest object (e.g. "wp_navigation:9960").
	 * @return string Backup contents.
	 */
	function cr_migration_read_verified_backup( string $file, string $object ): string {
		if ( '' === $file || ! is_readable( $file ) ) {
			WP_CLI::error( 'restore needs a readable backup file path.' );
		}
		$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$manifest = dirname( $file ) . '/manifest.jsonl';
		$entry    = null;
		foreach ( is_readable( $manifest ) ? (array) file( $manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array() as $line ) {
			$row = json_decode( (string) $line, true );
			if ( is_array( $row ) && ( $row['file'] ?? '' ) === basename( $file ) ) {
				$entry = $row;
			}
		}
		if ( ! $entry ) {
			WP_CLI::error( sprintf( '%s has no line in %s; refusing to guess its target.', basename( $file ), $manifest ) );
		}
		if ( ! hash_equals( (string) ( $entry['sha256'] ?? '' ), hash( 'sha256', $contents ) ) ) {
			WP_CLI::error( sprintf( '%s does not match its manifest sha256; refusing to restore.', basename( $file ) ) );
		}
		if ( untrailingslashit( (string) ( $entry['home'] ?? '' ) ) !== untrailingslashit( home_url( '/' ) ) ) {
			WP_CLI::error( sprintf( 'Backup was taken on %s, not %s; refusing to restore.', $entry['home'] ?? '?', home_url( '/' ) ) );
		}
		if ( (string) ( $entry['object'] ?? '' ) !== $object ) {
			WP_CLI::error( sprintf( '%s was recorded for %s, not %s; refusing to restore.', basename( $file ), $entry['object'] ?? '?', $object ) );
		}
		return $contents;
	}

	/**
	 * Line diff summary for previews: changed line count and the first changed lines.
	 *
	 * @param string $before Before.
	 * @param string $after  After.
	 * @param int    $show   Lines to show.
	 * @return string
	 */
	function cr_migration_diff_summary( string $before, string $after, int $show = 12 ): string {
		$a   = explode( "\n", $before );
		$b   = explode( "\n", $after );
		$out = array();
		$max = max( count( $a ), count( $b ) );
		$n   = 0;
		for ( $i = 0; $i < $max; $i++ ) {
			$la = $a[ $i ] ?? null;
			$lb = $b[ $i ] ?? null;
			if ( $la === $lb ) {
				continue;
			}
			++$n;
			if ( count( $out ) < $show ) {
				$out[] = sprintf( "  line %d\n  - %s\n  + %s", $i + 1, mb_substr( (string) $la, 0, 160 ), mb_substr( (string) $lb, 0, 160 ) );
			}
		}
		return sprintf( "%d line(s) differ, %d -> %d bytes\n%s", $n, strlen( $before ), strlen( $after ), implode( "\n", $out ) );
	}
}
