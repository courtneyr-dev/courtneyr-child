<?php
/**
 * Guards and memo key of the wide-column decision.
 *
 * The browser harness only renders the main loop, so it cannot show that a
 * secondary loop, a feed or a password-protected post gets no decision, or that
 * each page of a split post gets its own. These checks do.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	class WP_Post {
		public int $ID = 7;
	}

	$GLOBALS['t'] = array(
		'singular' => true,
		'main'     => true,
		'loop'     => true,
		'queried'  => 7,
		'password' => false,
		'content'  => 'page one',
		'calls'    => 0,
	);
	$GLOBALS['page'] = 1;

	function add_filter() {}
	function add_action() {}
	function is_singular( $t = '' ): bool {
		return $GLOBALS['t']['singular'];
	}
	function is_main_query(): bool {
		return $GLOBALS['t']['main'];
	}
	function in_the_loop(): bool {
		return $GLOBALS['t']['loop'];
	}
	function get_post() {
		return new WP_Post();
	}
	function get_queried_object_id(): int {
		return $GLOBALS['t']['queried'];
	}
	function post_password_required( $p = null ): bool {
		return $GLOBALS['t']['password'];
	}
	function get_the_content(): string {
		return $GLOBALS['t']['content'];
	}
	function apply_filters( $hook, $value ) {
		return $value;
	}
}

namespace Courtneyr\Child\Enqueue {
	function primary_kind_slug( int $id ): string {
		return 'read';
	}
}

namespace Courtneyr\Child\SingleRead {
	function page_record( $post, string $content ): array {
		++$GLOBALS['t']['calls'];
		return array(
			'wide' => true,
			'data' => $content,
		);
	}
}

namespace {
	require dirname( __DIR__, 2 ) . '/inc/single-wide-column.php';

	use function Courtneyr\Child\SingleWideColumn\applies;
	use function Courtneyr\Child\SingleWideColumn\data_for;

	$fail = 0;
	function check( string $name, bool $ok ): void {
		global $fail;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
		$fail += $ok ? 0 : 1;
	}

	check( 'main loop of a post decides', applies() && 'page one' === data_for( 'read' ) );
	check( 'another kind gets no data', null === data_for( 'watch' ) );

	$GLOBALS['t']['loop'] = false;
	check( 'outside the loop: no decision', ! applies() && null === data_for( 'read' ) );
	$GLOBALS['t']['loop'] = true;

	$GLOBALS['t']['main'] = false;
	check( 'secondary query: no decision', ! applies() && null === data_for( 'read' ) );
	$GLOBALS['t']['main'] = true;

	$GLOBALS['t']['queried'] = 99;
	check( 'loop post is not the queried post: no decision', ! applies() );
	$GLOBALS['t']['queried'] = 7;

	$GLOBALS['t']['singular'] = false;
	check( 'not a single post: no decision', ! applies() );
	$GLOBALS['t']['singular'] = true;

	$GLOBALS['t']['password'] = true;
	check( 'password-protected: no decision', ! applies() );
	$GLOBALS['t']['password'] = false;

	$GLOBALS['t']['content'] = 'fresh';
	$GLOBALS['t']['calls']   = 0;
	applies();
	applies();
	check( 'same post, page and content: resolver runs once', 1 === $GLOBALS['t']['calls'] );

	$GLOBALS['page'] = 2;
	check( 'next page of a split post decides again', 'fresh' === data_for( 'read' ) && 2 === $GLOBALS['t']['calls'] );
	$GLOBALS['page'] = 1;

	$GLOBALS['t']['content'] = 'edited';
	check( 'changed content (autosave preview) decides again', 'edited' === data_for( 'read' ) );

	exit( $fail ? 1 : 0 );
}
