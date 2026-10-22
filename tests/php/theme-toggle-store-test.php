<?php
/**
 * Standalone contract checks for the theme-toggle Interactivity API store.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$failures = array();
$theme_dir = dirname( __DIR__, 2 );

function cr_theme_group( string $name, callable $checks ): void {
	global $failures;
	$before = count( $failures );
	$checks();
	$ok = $before === count( $failures );
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
}

function cr_theme_assert( bool $ok, string $message ): void {
	global $failures;
	if ( ! $ok ) {
		$failures[] = $message;
	}
}

function cr_theme_contains( string $needle, string $haystack, string $message ): void {
	cr_theme_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
}

function cr_theme_not_contains( string $needle, string $haystack, string $message ): void {
	cr_theme_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
}

function cr_theme_finish(): void {
	global $failures;
	if ( $failures ) {
		foreach ( $failures as $failure ) {
			fwrite( STDERR, '  ' . $failure . "\n" );
		}
		exit( 1 );
	}
	echo "all passed\n";
}

cr_theme_group(
	'the classic toggle is removed and the no-flash initializer remains',
	static function () use ( $theme_dir ): void {
		$classic = $theme_dir . '/assets/js/theme-toggle/view.js';
		$no_flash = $theme_dir . '/assets/js/theme-toggle/no-flash.js';
		cr_theme_assert( ! file_exists( $classic ), 'classic toggle file is removed' );
		cr_theme_assert( file_exists( $no_flash ), 'no-flash initializer still exists' );
		cr_theme_contains( 'courtneyr-theme', (string) file_get_contents( $no_flash ), 'no-flash initializer keeps the storage key' );
	}
);

cr_theme_group(
	'the Interactivity API store declares the theme contract',
	static function () use ( $theme_dir ): void {
		$module_path = $theme_dir . '/assets/js/interactivity/theme-toggle/view.js';
		cr_theme_assert( file_exists( $module_path ), 'theme-toggle store module exists' );
		$module = file_exists( $module_path ) ? (string) file_get_contents( $module_path ) : '';
		cr_theme_contains( "store( 'courtneyr/theme-toggle'", $module, 'module registers the named store' );
		cr_theme_contains( '@wordpress/interactivity', $module, 'module imports the Interactivity API' );
		cr_theme_contains( 'courtneyr-theme', $module, 'module uses the existing storage key' );
		cr_theme_contains( 'data-theme', $module, 'module controls the root theme attribute' );
	}
);

cr_theme_group(
	'the theme enqueues a script module instead of a classic script',
	static function () use ( $theme_dir ): void {
		$enqueue = (string) file_get_contents( $theme_dir . '/inc/enqueue.php' );
		$flat = (string) preg_replace( '/\s+/', ' ', $enqueue );
		cr_theme_contains( "wp_register_script_module( 'courtneyr/theme-toggle'", $flat, 'theme module is registered' );
		cr_theme_contains( "wp_enqueue_script_module( 'courtneyr/theme-toggle'", $enqueue, 'theme module is enqueued' );
		cr_theme_not_contains( "'courtneyr-theme-toggle'", $enqueue, 'classic script handle is gone' );
		cr_theme_not_contains( "'/assets/js/theme-toggle/view.js'", $enqueue, 'classic script path is gone' );
	}
);

cr_theme_group(
	'the appearance controls carry the store directives and accessible labels',
	static function () use ( $theme_dir ): void {
		$html = (string) file_get_contents( $theme_dir . '/parts/appearance-toggle.html' );
		$html = preg_replace( '/<!--\s*\/?wp:html\s*-->/', '', $html );
		$prior = libxml_use_internal_errors( true );
		$document = new DOMDocument();
		$document->loadHTML( '<?xml encoding="utf-8"?><div id="scope">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prior );
		$xpath = new DOMXPath( $document );

		$segments = $xpath->query( '//*[@data-theme-set]' );
		cr_theme_assert( 3 === $segments->length, 'three theme segments remain' );
		$labels = array();
		foreach ( $segments as $segment ) {
			cr_theme_assert( 'button' === $segment->nodeName, 'each theme segment is a button' );
			cr_theme_assert( 'actions.set' === $segment->getAttribute( 'data-wp-on--click' ), 'segment uses actions.set' );
			cr_theme_assert( $segment->hasAttribute( 'data-wp-bind--aria-pressed' ), 'segment binds aria-pressed' );
			cr_theme_assert( 'false' === $segment->getAttribute( 'aria-pressed' ), 'segment keeps static aria-pressed=false' );
			cr_theme_assert( $segment->hasAttribute( 'aria-label' ), 'segment keeps an accessible label' );
			$labels[] = $segment->getAttribute( 'aria-label' );
		}
		cr_theme_assert( array( 'Light theme', 'Dark theme', 'System theme (follows OS)' ) === $labels, 'theme labels are unchanged' );

		$cycle = $xpath->query( '//*[@data-theme-cycle]' );
		cr_theme_assert( 1 === $cycle->length, 'one cycle button remains' );
		cr_theme_assert( 1 === $cycle->length && 'actions.cycle' === $cycle->item( 0 )->getAttribute( 'data-wp-on--click' ), 'cycle button uses actions.cycle' );

		$status = $xpath->query( '//*[@data-theme-cycle-status]' );
		cr_theme_assert( 1 === $status->length, 'one cycle status remains' );
		cr_theme_assert( 1 === $status->length && 'status' === $status->item( 0 )->getAttribute( 'role' ), 'cycle status keeps role=status' );

		$roots = $xpath->query( '//*[@data-wp-interactive="courtneyr/theme-toggle"]' );
		cr_theme_assert( 3 === $roots->length, 'exactly three roots use the theme-toggle store' );
	}
);

cr_theme_finish();
