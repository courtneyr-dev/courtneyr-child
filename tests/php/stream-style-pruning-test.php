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
		$tax = $GLOBALS['cr_ssp']['tax'] ?? null;
		return null !== $tax && ( '' === $taxonomy || $tax === $taxonomy );
	}
	function is_singular( $post_types = '' ) {
		return ( $GLOBALS['cr_ssp']['singular'] ?? '' ) === $post_types;
	}
	function is_category() {
		return $GLOBALS['cr_ssp']['category'] ?? false;
	}
	function is_tag() {
		return false;
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
		'category'     => array( 'category' => true ),
	);
	foreach ( $requests as $label => $state ) {
		$GLOBALS['cr_ssp'] = array_merge( array( 'front' => false, 'stream' => false, 'home' => false, 'tax' => null, 'dequeued' => array() ), $state );
		\Courtneyr\Child\StreamStylePruning\dequeue_listing_plugin_sheets();
		$want = in_array( $label, array( 'single post', 'category' ), true ) ? array() : array( 'syndication-style', 'indieweb' );
		cr_ssp_check( $want === $GLOBALS['cr_ssp']['dequeued'], $label . ': ' . ( $want ? 'dequeues' : 'keeps' ) . ' the Syndication Links and IndieWeb sheets' );
	}
	cr_ssp_check( in_array( 'Courtneyr\\Child\\StreamStylePruning\\dequeue_listing_plugin_sheets', $hooks['wp_enqueue_scripts'][9999] ?? array(), true ), 'the dequeue runs after the plugins enqueue' );

	/*
	 * Perfmatters 2.6.8, CSS.php is_selector_used(): a rule stays when an
	 * excluded selector matches this pattern anywhere in one of its selectors.
	 */
	function cr_ssp_kept( array $excluded, string $selector ): bool {
		foreach ( $excluded as $value ) {
			if ( preg_match( '#(' . preg_quote( $value ) . ')(?=\s|\.|\:|,|\[|$)#', $selector ) ) {
				return true;
			}
		}
		return false;
	}
	$rules = array(
		'post' => array( 'hr', '.wp-block-post-content hr.wp-block-separator:not(.cr-marker-bar)', '.entry-content hr.wp-block-separator:not(.cr-marker-bar):nth-of-type(2n)', 'figcaption', 'div[class*="wp-block-"] figcaption', 'table', 'thead', 'th', 'td', 'tbody tr:nth-child(odd)', 'th h1', '.wp-block-post-content table td', '.wp-block-table table', '.wp-block-table td', 'code', 'pre', 'pre code', '*:not(.wp-block-code)>code' ),
		'tax'  => array( 'ol>li::marker', 'ol > li::marker' ),
	);
	foreach ( $rules as $type => $selectors ) {
		$GLOBALS['cr_ssp'] = array( 'front' => false, 'stream' => false, 'home' => false, 'tax' => 'post' === $type ? null : 'kind', 'singular' => 'post' === $type ? 'post' : '' );
		$excluded = \Courtneyr\Child\StreamStylePruning\rucss_keep_type_classes( array() );
		foreach ( $selectors as $selector ) {
			cr_ssp_check( cr_ssp_kept( $excluded, $selector ), $type . ' used CSS keeps ' . $selector );
		}
	}
	$GLOBALS['cr_ssp'] = array( 'front' => false, 'stream' => false, 'home' => false, 'tax' => 'kind', 'singular' => '' );
	cr_ssp_check( ! cr_ssp_kept( \Courtneyr\Child\StreamStylePruning\rucss_keep_type_classes( array() ), 'table' ), 'tax used CSS does not keep the post-only table rules' );

	echo $failed ? "\n$failed failed\n" : "\nall passed\n";
	exit( $failed ? 1 : 0 );
}
