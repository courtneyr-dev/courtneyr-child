<?php
/**
 * Standalone checks for inc/stream-style-pruning.php.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return $GLOBALS['cr_ssp']['stream'];
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );

	$GLOBALS['cr_ssp'] = array(
		'front'  => false,
		'stream' => false,
		'hooks'  => array(),
	);

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_ssp']['hooks'][ $hook ][ (int) $priority ][] = $callback;
	}
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_action( $hook, $callback, $priority, $accepted_args );
	}
	function is_front_page() {
		return $GLOBALS['cr_ssp']['front'];
	}
	function is_admin() {
		return false;
	}
	function is_home() {
		return $GLOBALS['cr_ssp']['home'] ?? false;
	}
	function is_tax( $taxonomy = '' ) {
		return ( $GLOBALS['cr_ssp']['tax'] ?? '' ) === $taxonomy;
	}
	function wp_dequeue_style( $handle ) {
		$GLOBALS['cr_ssp']['dequeued'][] = $handle;
	}

	require dirname( __DIR__, 2 ) . '/inc/stream-style-pruning.php';

	$failed = 0;
	function cr_ssp_check( bool $condition, string $message ): void {
		global $failed;
		echo ( $condition ? 'ok   ' : 'FAIL ' ) . $message . "\n";
		$failed += $condition ? 0 : 1;
	}

	$nav_tag = "<link rel='stylesheet' id='courtneyr-nav-css' href='https://example.test/theme/assets/css/cr-nav.css?ver=test' media='all' />\n";

	$GLOBALS['cr_ssp']['front'] = true;
	$out = \Courtneyr\Child\StreamStylePruning\defer_card_sheet_on_front_page( $nav_tag, 'courtneyr-nav' );
	cr_ssp_check( str_contains( $out, "data-cr-href='https://example.test/theme/assets/css/cr-nav.css?ver=test'" ) && ! str_contains( strstr( $out, '<noscript>', true ) ?: $out, " href='" ), 'front page: cr-nav.css waits for the load event' );
	cr_ssp_check( str_contains( $out, '<noscript>' . trim( $nav_tag ) . '</noscript>' ), 'front page: cr-nav.css keeps a noscript copy' );

	$GLOBALS['cr_ssp']['front'] = false;
	cr_ssp_check( $nav_tag === \Courtneyr\Child\StreamStylePruning\defer_card_sheet_on_front_page( $nav_tag, 'courtneyr-nav' ), 'other pages: cr-nav.css tag is unchanged' );

	$hooks    = $GLOBALS['cr_ssp']['hooks'];
	$requests = array(
		'front page'   => array( 'front' => true, 'stream' => true, 'home' => true ),
		'/stream/'     => array( 'stream' => true ),
		'/blog/'       => array( 'home' => true ),
		'/kind/watch/' => array( 'tax' => 'kind' ),
		'single post'  => array(),
		'category'     => array( 'tax' => '' ),
	);
	foreach ( $requests as $label => $state ) {
		$GLOBALS['cr_ssp'] = array_merge( array( 'front' => false, 'stream' => false, 'home' => false, 'tax' => null, 'dequeued' => array() ), $state );
		\Courtneyr\Child\StreamStylePruning\dequeue_listing_plugin_sheets();
		$want = in_array( $label, array( 'single post', 'category' ), true ) ? array() : array( 'syndication-style', 'indieweb' );
		cr_ssp_check( $want === $GLOBALS['cr_ssp']['dequeued'], $label . ': ' . ( $want ? 'dequeues' : 'keeps' ) . ' the Syndication Links and IndieWeb sheets' );
	}
	cr_ssp_check( in_array( 'Courtneyr\\Child\\StreamStylePruning\\dequeue_listing_plugin_sheets', $hooks['wp_enqueue_scripts'][9999] ?? array(), true ), 'the dequeue runs after the plugins enqueue' );

	echo $failed ? "\n$failed failed\n" : "\nall passed\n";
	exit( $failed ? 1 : 0 );
}
