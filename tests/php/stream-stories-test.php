<?php
/**
 * `php tests/php/stream-stories-test.php`: checks the Stream's web story SQL
 * rewrite, the post type gate and the hooks in inc/stream-stories.php
 * without loading WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );

	$GLOBALS['cr_hooks']     = array();
	$GLOBALS['cr_is_stream'] = true;

	/** Record a hook callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][] = array( $callback, $priority );
		return true;
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook, $callback, $priority, $accepted_args );
	}

	/** Stub. */
	function is_page( $page = '' ) {
		return $GLOBALS['cr_is_stream'] && 'stream' === $page;
	}

	/** Stub. */
	function post_type_exists( $type ) {
		return 'web-story' === $type;
	}

	require dirname( __DIR__, 2 ) . '/inc/stream-stories.php';
}

namespace Courtneyr\Child\StreamStories\Tests {

	use function Courtneyr\Child\StreamStories\flag_query;
	use function Courtneyr\Child\StreamStories\rewrite_meta_sql;
	use function Courtneyr\Child\StreamStories\story_card;

	$failures = 0;

	/** Report one check. */
	function check( bool $ok, string $label ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
		if ( ! $ok ) {
			++$failures;
		}
	}

	// The shape WP_Meta_Query returns for one '=' clause.
	$sql = array(
		'join'  => ' INNER JOIN wp_postmeta ON ( wp_posts.ID = wp_postmeta.post_id )',
		'where' => " AND ( \n  ( wp_postmeta.meta_key = '_pkiw_surface' AND wp_postmeta.meta_value = 'stream' )\n)",
	);
	$out = rewrite_meta_sql( $sql, 'wp_posts', 'ID' );

	check( '' === $out['join'], 'outer join removed' );
	check( str_starts_with( $out['where'], " AND ( wp_posts.post_type = 'web-story' OR wp_posts.ID IN ( SELECT wp_posts.ID FROM wp_posts INNER JOIN wp_postmeta" ), 'stories pass by type, posts by subquery' );
	check( str_contains( $out['where'], "WHERE 1=1 AND ( \n  ( wp_postmeta.meta_key = '_pkiw_surface'" ), 'policy condition kept whole inside the subquery' );
	check( substr_count( $out['where'], '(' ) === substr_count( $out['where'], ')' ), 'parentheses balance' );
	check( $sql === rewrite_meta_sql( $sql, 'wp_posts', 'ID' ) ? false : true, 'rewrite changes the SQL' );
	check( array( 'join' => '', 'where' => '' ) === rewrite_meta_sql( array( 'join' => '', 'where' => '' ), 'wp_posts', 'ID' ), 'no meta clause, no change' );

	check( true === ( flag_query( array( 'post_type' => 'post' ) )['cr_stream_stories'] ?? null ), 'Stream page post query is flagged' );
	check( ! isset( flag_query( array( 'post_type' => 'page' ) )['cr_stream_stories'] ), 'other post types are not flagged' );
	$GLOBALS['cr_is_stream'] = false;
	check( ! isset( flag_query( array( 'post_type' => 'post' ) )['cr_stream_stories'] ), 'other pages are not flagged' );

	check( null === story_card( null, (object) array( 'post_type' => 'post' ), 2 ), 'a non-post object falls through' );
	check( '<x>' === story_card( '<x>', null, 2 ), 'an earlier card is kept' );

	$hooks = $GLOBALS['cr_hooks'];
	check( PHP_INT_MAX === ( $hooks['query_loop_block_query_vars'][0][1] ?? 0 ), 'flag runs last on query vars' );
	check( PHP_INT_MAX === ( $hooks['pre_get_posts'][0][1] ?? 0 ), 'post type widens last on pre_get_posts' );
	check( isset( $hooks['get_meta_sql'], $hooks['pkiw_stream_card_kind_render'], $hooks['init'] ), 'meta SQL, card and style hooks registered' );

	exit( $failures ? 1 : 0 );
}
