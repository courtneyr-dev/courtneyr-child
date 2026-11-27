<?php
/**
 * `php tests/php/ollie-woo-patterns-test.php`: checks that the theme swaps
 * Ollie's WooCommerce pattern cleanup for one that removes the same
 * ollie/woo-* patterns without loading any pattern file (inc/block-patterns.php),
 * using the pattern registry from the WordPress core that CI unpacks (CR_WP_CORE).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['cr_actions'] = array();
	$GLOBALS['cr_loaded']  = array();

	/** Record an action. */
	function add_action( $hook, $callback, $priority = 10 ) {
		$GLOBALS['cr_actions'][ $hook ][ $priority ][] = $callback;
		return true;
	}

	/** Remove a recorded action; true when it was there. */
	function remove_action( $hook, $callback, $priority = 10 ) {
		$list = $GLOBALS['cr_actions'][ $hook ][ $priority ] ?? array();
		$key  = array_search( $callback, $list, true );
		if ( false === $key ) {
			return false;
		}
		unset( $GLOBALS['cr_actions'][ $hook ][ $priority ][ $key ] );
		return true;
	}

	/** Stubs the registry calls. */
	function __( $text ) {
		return $text;
	}

	/** Stub. */
	function _doing_it_wrong() {}

	/** Stub: the fixture registers patterns during init. */
	function current_action() {
		return 'init';
	}

	/** Stub: reading a pattern's content applies block hooks. */
	function apply_block_hooks_to_content( $content ) {
		return $content;
	}

	/** Stub. */
	function unregister_block_pattern( $name ) {
		return WP_Block_Patterns_Registry::get_instance()->unregister( $name );
	}

	/** Fixture parent theme. */
	function get_template_directory() {
		return $GLOBALS['cr_parent'];
	}

	$wp = getenv( 'CR_WP_CORE' ) ?: ( getenv( 'CR_WP_DIR' ) ?: '' );
	if ( '' === $wp || ! is_readable( $wp . '/wp-includes/class-wp-block-patterns-registry.php' ) ) {
		echo "SKIP set CR_WP_CORE to a WordPress checkout for the pattern registry\n";
		exit( 0 );
	}
	require_once $wp . '/wp-includes/class-wp-block-patterns-registry.php';

	// A fixture parent theme: two WooCommerce patterns and one other, each recording when it is loaded.
	$GLOBALS['cr_parent'] = sys_get_temp_dir() . '/cr-ollie-' . getmypid();
	mkdir( $GLOBALS['cr_parent'] . '/patterns', 0777, true );
	foreach ( array( 'woo-cart', 'woo-header', 'hero-1' ) as $slug ) {
		$file = $GLOBALS['cr_parent'] . "/patterns/{$slug}.php";
		file_put_contents( $file, "<?php \$GLOBALS['cr_loaded'][] = '{$slug}'; ?><!-- wp:paragraph --><p>{$slug}</p><!-- /wp:paragraph -->" );
		WP_Block_Patterns_Registry::get_instance()->register(
			"ollie/{$slug}",
			array(
				'title'    => $slug,
				'filePath' => $file,
			)
		);
	}

	add_action( 'init', 'Ollie\\unregister_ollie_woocommerce_patterns', 999 );
	require dirname( __DIR__, 2 ) . '/inc/block-patterns.php';
}

namespace Courtneyr\Child\BlockPatterns\Tests {

	use WP_Block_Patterns_Registry;
	use function Courtneyr\Child\BlockPatterns\replace_ollie_woo_pattern_cleanup;
	use function Courtneyr\Child\BlockPatterns\unregister_ollie_woo_patterns;

	$failures = 0;

	/** Report one check. */
	function check( bool $ok, string $label ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
		if ( ! $ok ) {
			++$failures;
		}
	}

	check( 3 === count( array_filter( array( 'woo-cart', 'woo-header', 'hero-1' ), static fn( $s ) => WP_Block_Patterns_Registry::get_instance()->is_registered( "ollie/{$s}" ) ) ), 'the fixture registers its three patterns' );

	replace_ollie_woo_pattern_cleanup();
	$init = $GLOBALS['cr_actions']['init'][999] ?? array();
	check( ! in_array( 'Ollie\\unregister_ollie_woocommerce_patterns', $init, true ), "Ollie's cleanup no longer runs on init" );
	check( in_array( 'Courtneyr\\Child\\BlockPatterns\\unregister_ollie_woo_patterns', $init, true ), 'the name-based cleanup runs on init at 999' );

	unregister_ollie_woo_patterns();
	$registry = WP_Block_Patterns_Registry::get_instance();
	check( ! $registry->is_registered( 'ollie/woo-cart' ) && ! $registry->is_registered( 'ollie/woo-header' ), 'ollie/woo-* patterns are unregistered' );
	check( $registry->is_registered( 'ollie/hero-1' ), 'other Ollie patterns stay registered' );
	check( array() === $GLOBALS['cr_loaded'], 'no pattern file is loaded (loaded: ' . implode( ', ', $GLOBALS['cr_loaded'] ) . ')' );

	array_map( 'unlink', glob( $GLOBALS['cr_parent'] . '/patterns/*.php' ) );
	rmdir( $GLOBALS['cr_parent'] . '/patterns' );
	rmdir( $GLOBALS['cr_parent'] );

	exit( $failures > 0 ? 1 : 0 );
}
