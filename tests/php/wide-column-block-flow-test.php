<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://theme.test' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	$core = getenv( 'CR_WP_CORE' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	foreach ( array( 'class-wp-block-parser-block.php', 'class-wp-block-parser-frame.php', 'class-wp-block-parser.php', 'blocks.php', 'plugin.php' ) as $file ) {
		require_once $core . '/wp-includes/' . $file;
	}

	class WP_Post {
		public int $ID = 42;
		public string $post_content = '';
	}
	class WP_Block {
		public function __construct( public array $context ) {}
	}

	function wp_json_encode( $value, $options = 0 ) { return json_encode( $value, $options ); }
	function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
	function is_singular( $type ) { return 'post' === $type; }
	function is_main_query() { return $GLOBALS['main'] ?? true; }
	function in_the_loop() { return true; }
	function get_post() {
		$post = new WP_Post();
		$post->post_content = $GLOBALS['content'] ?? '';
		return $post;
	}
	function get_queried_object_id() { return 42; }
	function get_the_ID() { return 42; }
	function post_password_required( $post ) { return $GLOBALS['locked'] ?? false; }
	function is_feed() { return $GLOBALS['feed'] ?? false; }
	function wp_is_serving_rest_request() { return $GLOBALS['rest'] ?? false; }
	function get_the_content() { return $GLOBALS['content']; }
	function strip_shortcodes( $value ) { return $value; }
	function wp_link_pages( $attrs ) { return $GLOBALS['links'] ?? ''; }

	function prove( bool $ok, string $label ): void {
		if ( ! $ok ) {
			throw new RuntimeException( $label );
		}
		echo "PASS $label\n";
	}
}

namespace Courtneyr\Child\Enqueue {
	function primary_kind_slug( $id ) { return $GLOBALS['kind']; }
}

namespace Courtneyr\Child\SingleFavorite {
	function page_sheet( \WP_Post $post, string $content ): ?array {
		$place = \Courtneyr\Child\SingleWideColumn\first_block_place( $content, 'post-kinds-indieweb/favorite-card', $index );
		return 'unknown' === $place ? null : array(
			'wide' => 'nested' !== $place,
			'data' => array( 'test' => 'favorite' ),
			'flow' => null === $index ? null : array( 'class' => 'cr-fav-flow', 'position' => $index ),
		);
	}
	function single_facts() { return array(); }
}

namespace Courtneyr\Child\Repost {
	function page_clipping( \WP_Post $post, string $content ): ?array {
		$place = \Courtneyr\Child\SingleWideColumn\first_block_place( $content, 'post-kinds-indieweb/repost-card', $index );
		return 'unknown' === $place ? null : array(
			'wide' => 'nested' !== $place,
			'data' => array( 'test' => 'repost' ),
			'flow' => null === $index && 'none' !== $place ? null : array( 'class' => 'cr-repost-flow', 'position' => $index ?? -1 ),
		);
	}
}

namespace {
	use Courtneyr\Child\SingleWideColumn as Wide;
	require dirname( __DIR__, 2 ) . '/inc/single-wide-column.php';

	$fake = '<!-- wp:html --><!-- <article class="k-favorite"> -->'
		. '<article id="authored" class="k-favorite">Authored duplicate class.</article>'
		. '<p>Optional paragraph closer<svg xmlns="http://www.w3.org/2000/svg"/>'
		. '<!-- /wp:html -->';
	$after = '<!-- wp:paragraph --><p id="note">After the card</p><!-- /wp:paragraph -->';
	foreach ( array_intersect_key( array( 'favorite' => 'cr-fav-flow', 'repost' => 'cr-repost-flow' ), Wide\KINDS ) as $kind => $flow ) {
		$GLOBALS['kind'] = $kind;
		$card = '<!-- wp:post-kinds-indieweb/' . $kind . '-card {"title":"Exact"} /-->';
		$input = $fake . "\n\n" . $card . "\n\n" . $after;
		$GLOBALS['content'] = $input;
		$place = Wide\first_block_place( $input, 'post-kinds-indieweb/' . $kind . '-card', $index );
		prove( 'top' === $place && 2 === $index, "$kind sees the actual top-level card through HTML decoys" );
		$grouped = Wide\group_content( $input, $flow, $index );
		$blocks = parse_blocks( $grouped );
		prove( 3 === count( $blocks ) && 'core/group' === $blocks[0]['blockName']
			&& 'post-kinds-indieweb/' . $kind . '-card' === $blocks[1]['blockName']
			&& 'core/group' === $blocks[2]['blockName'], "$kind has group/card/group in the real core parser" );
		prove( str_contains( serialize_block( $blocks[0] ), $fake . "\n\n" ), "$kind keeps authored comment, article, optional paragraph and SVG bytes" );
		prove( str_contains( serialize_block( $blocks[2] ), "\n\n" . $after ), "$kind keeps the note bytes" );
		$render = Wide\scope_post_content( array( 'render_callback' => static fn( $a, $c, $b ) =>
			apply_filters( 'the_content', get_the_content() )
		), 'core/post-content' )['render_callback'];
		$main = array( 'className' => 'single-post__content' );
		prove( $grouped === $render( $main, '', new WP_Block( array( 'postId' => 42 ) ) ), "$kind groups the main block through real WP_Hook" );
		prove( $input === apply_filters( 'the_content', $input ), "$kind removes its temporary filter" );
		prove( $input === $render( $main, '', new WP_Block( array( 'postId' => 96 ) ) ), "$kind leaves other post context unchanged" );
		prove( $input === $render( array( 'className' => 'related-posts' ), '', new WP_Block( array( 'postId' => 42 ) ) ), "$kind leaves related content unchanged" );
		foreach ( array( 'locked', 'feed', 'rest' ) as $exclude ) {
			$GLOBALS[$exclude] = true;
			prove( $input === $render( $main, '', new WP_Block( array( 'postId' => 42 ) ) ), "$kind leaves $exclude content unchanged" );
			unset( $GLOBALS[$exclude] );
		}
		prove( 'unknown' === Wide\first_block_place( '<!-- wp:block {"ref":7} /-->' . $card, 'post-kinds-indieweb/' . $kind . '-card', $index ) && null === $index, "$kind does not consume an opaque earlier block" );
	}
	$GLOBALS['kind'] = 'repost';
	$GLOBALS['content'] = "First paragraph.\n\nSecond paragraph.\nA line break.";
	$formatted = "<p>First paragraph.</p>\n<p>Second paragraph.<br />\nA line break.</p>";
	$autop = static fn( string $input ): string => has_blocks( $input ) ? $input : $formatted;
	add_filter( 'the_content', $autop, 10 );
	$render = Wide\scope_post_content( array( 'render_callback' => static fn() =>
		apply_filters( 'the_content', get_the_content() )
	), 'core/post-content' )['render_callback'];
	$main = array( 'className' => 'single-post__content' );
	prove( '<div class="wp-block-group cr-repost-flow">' . $formatted . '</div>'
		=== $render( $main, '', new WP_Block( array( 'postId' => 42 ) ) ), 'classic Repost groups after paragraph and line-break formatting' );
	prove( $formatted === apply_filters( 'the_content', get_the_content() ), 'classic Repost removes its temporary filter' );
	remove_filter( 'the_content', $autop, 10 );
}
