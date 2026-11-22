<?php
/**
 * Stubs the wide column test adds to the read bootstrap.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	$GLOBALS['cr_wide_terms']   = array( 'read' );
	$GLOBALS['cr_wide_main']    = true;
	$GLOBALS['cr_wide_in_loop'] = true;
	$GLOBALS['cr_wide_loop_id'] = null;
	$GLOBALS['cr_wide_page']    = 1;

	function is_wp_error( $thing ): bool {
		return false;
	}

	function get_the_terms( $post_id, $taxonomy ) {
		return array_map( static fn( string $slug ): object => (object) array( 'slug' => $slug ), $GLOBALS['cr_wide_terms'] );
	}

	function is_main_query(): bool {
		return $GLOBALS['cr_wide_main'];
	}

	function in_the_loop(): bool {
		return $GLOBALS['cr_wide_in_loop'];
	}

	function get_query_var( $name ) {
		return 'page' === $name ? $GLOBALS['cr_wide_page'] : '';
	}

	require dirname( __DIR__, 2 ) . '/inc/enqueue.php';
	require dirname( __DIR__, 2 ) . '/inc/single-wide-column.php';
}

