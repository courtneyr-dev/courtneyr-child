<?php
/**
 * `php tests/php/single-reactions-test.php`: checks that every single drops
 * the template's reactions wrapper when it holds no likes, bookmarks or
 * reposts, without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	error_reporting( E_ALL & ~E_DEPRECATED );

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'CR_REACTIONS_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']    = array();
	$GLOBALS['cr_singular'] = false;

	/** Register a hook callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	/** Return a registered callback's priority, or false. */
	function has_filter( $hook, $callback ) {
		foreach ( $GLOBALS['cr_hooks'][ $hook ] ?? array() as $priority => $callbacks ) {
			foreach ( $callbacks as $registered ) {
				if ( $registered[0] === $callback ) {
					return (int) $priority;
				}
			}
		}
		return false;
	}

	/** Return a registered callback's accepted arg count. */
	function accepted_args_for( string $hook, string $callback, int $priority = 10 ): int {
		foreach ( $GLOBALS['cr_hooks'][ $hook ][ $priority ] ?? array() as $registered ) {
			if ( $registered[0] === $callback ) {
				return (int) $registered[1];
			}
		}
		return 0;
	}

	/** Singular request flag. */
	function is_singular( $post_type = '' ) {
		return $GLOBALS['cr_singular'];
	}

	require CR_REACTIONS_THEME_DIR . '/inc/post-kinds.php';
}

namespace Courtneyr\Child\SingleReactionsTests {

	$failures = array();

	/** Print one result and remember failures. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'PASS ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Group block fixture. */
	function group( string $class ): array {
		return array(
			'blockName' => 'core/group',
			'attrs'     => array( 'className' => $class ),
		);
	}

	/** Call the shared filter if it exists. */
	function drop( string $html, array $block, bool $singular = true ) {
		$GLOBALS['cr_singular'] = $singular;
		return function_exists( '\\Courtneyr\\Child\\PostKinds\\drop_empty_reactions' )
			? \Courtneyr\Child\PostKinds\drop_empty_reactions( $html, $block )
			: '__missing__';
	}

	$open    = '<div class="wp-block-group single-post__syndication" style="padding-top:var(--wp--preset--spacing--large)">';
	$heading = '<h2 class="wp-block-heading has-large-font-size" id="h-likes-bookmarks-and-reposts">Likes, Bookmarks, and Reposts</h2>';
	$wrapper = group( 'single-post__syndication' );

	$callback = 'Courtneyr\\Child\\PostKinds\\drop_empty_reactions';
	check( function_exists( $callback ), 'shared empty-reactions filter exists in PostKinds' );
	check( 10 === \has_filter( 'render_block_core/group', $callback ), 'shared empty-reactions filter is hooked on render_block_core/group at priority 10' );
	check( 2 === \accepted_args_for( 'render_block_core/group', $callback ), 'shared empty-reactions filter accepts 2 args' );

	$like_source = (string) file_get_contents( CR_REACTIONS_THEME_DIR . '/inc/like.php' );
	check( false === strpos( $like_source, 'function drop_empty_reactions' ), 'like.php no longer carries its own like-only copy' );

	$template = (string) file_get_contents( CR_REACTIONS_THEME_DIR . '/templates/single.html' );
	check( 1 === preg_match( '/"className":"single-post__syndication"[^\n]*\n[^\n]*\n\s*<!-- wp:indieblocks\/facepile /', $template ), 'single template wraps the facepile in the single-post__syndication group' );

	check( '' === drop( "\n\t\t\n\t", $wrapper ), 'drops an empty wrapper (IndieBlocks printed nothing)' );
	check( '' === drop( $open . "\n\t\t\n\t</div>", $wrapper ), 'drops the wrapper when it holds only whitespace' );
	check( '' === drop( $open . '<div class="wp-block-indieblocks-facepile single-post__facepile">' . $heading . '</div></div>', $wrapper ), 'drops the wrapper when the facepile holds only its heading' );

	$with_faces = $open . '<div class="wp-block-indieblocks-facepile single-post__facepile">' . $heading . '<ul class="indieblocks-avatar-size-3"><li class="h-cite like"><a class="u-url" href="https://example.test/"><img class="avatar" alt="A" src="a.jpg"></a></li></ul></div></div>';
	check( $with_faces === drop( $with_faces, $wrapper ), 'keeps the wrapper unchanged when the facepile lists reactions' );

	$with_count = $open . '<div class="wp-block-indieblocks-facepile single-post__facepile">' . $heading . '<div class="indieblocks-count">3</div></div></div>';
	check( $with_count === drop( $with_count, $wrapper ), 'keeps the wrapper unchanged in count-only mode' );

	$heading_only = $open . $heading . '</div>';
	check( $heading_only === drop( $heading_only, $wrapper, false ), 'leaves non-singular requests unchanged' );
	check( $heading_only === drop( $heading_only, group( 'single-post__meta' ) ), 'leaves other groups unchanged' );
	check( '' === drop( '', $wrapper ), 'passes empty HTML through' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
