<?php
/**
 * A post that is not publicly viewable prints no card facts: a watch, a comic
 * and a recipe behind a password show their title and the password form only
 * until the visitor enters the password, then print what a public post prints.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/read-bootstrap.php';
}

namespace PKIW\Integrations {
	final class WP_Recipe_Maker {
		public static function get_post_recipe( int $post_id ): ?array {
			return array(
				'id'      => 7,
				'summary' => 'A fictional soup of red lentils and thyme.',
			);
		}
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_card_preview_request(): bool {
		return false;
	}
}

namespace {
	function get_term_by( $field, $value, $taxonomy ) {
		return false;
	}

	function wp_allowed_protocols(): array {
		return array( 'http', 'https' );
	}

	function is_front_page(): bool {
		return false;
	}

	if ( ! defined( 'OBJECT' ) ) {
		define( 'OBJECT', 'OBJECT' );
	}

	final class WPRM_Print {
		public static string $slug = 'wprm_print';

		public static function slug(): string {
			return self::$slug;
		}

		public static function print_page(): void {
		}
	}

	final class WP_Query {
		public bool $is_404 = false;

		public function set_404(): void {
			$this->is_404 = true;
		}
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function get_post_type( $post = null ) {
		return $GLOBALS['cr_pw_types'][ (int) $post ] ?? false;
	}

	function get_page_by_path( $path, $output = OBJECT, $type = 'page' ) {
		return $GLOBALS['cr_pw_slugs'][ $type . ':' . $path ] ?? null;
	}

	function remove_action( $hook, $callback, $priority = 10 ): bool {
		$GLOBALS['cr_pw_removed'][] = array( $hook, $callback, $priority );
		return true;
	}

	function status_header( $code ): void {
		$GLOBALS['cr_pw_status'] = (int) $code;
	}

	function nocache_headers(): void {
		$GLOBALS['cr_pw_nocache'] = true;
	}

	function pkiw_get_visible_location_fields( int $post_id ): array {
		return array( 'street' => true );
	}

	function do_shortcode( string $content ): string {
		return '<a class="wprm-recipe-print" href="https://cards.test/wprm_print/7">Print Recipe</a>';
	}

	require dirname( __DIR__, 2 ) . '/inc/single-watch.php';
	require dirname( __DIR__, 2 ) . '/inc/comic.php';
	require dirname( __DIR__, 2 ) . '/inc/recipe.php';
	require dirname( __DIR__, 2 ) . '/inc/stream-media.php';
	require dirname( __DIR__, 2 ) . '/inc/single-eat-drink.php';
	require dirname( __DIR__, 2 ) . '/inc/stream-eat-drink.php';
	require dirname( __DIR__, 2 ) . '/inc/stream-checkin.php';
	require dirname( __DIR__, 2 ) . '/inc/quote-note.php';
	require dirname( __DIR__, 2 ) . '/inc/sloc-map-alt.php';
	require dirname( __DIR__, 2 ) . '/inc/post-kinds.php';

	const CR_PW_SECRET = 'open-sesame';

	function cr_pw_post( string $kind, array $blocks ): WP_Post {
		$post                = cr_read_reset();
		$post->post_password = CR_PW_SECRET;
		$GLOBALS['cr_read_kind']   = $kind;
		$GLOBALS['cr_read_blocks'] = $blocks;
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		return $post;
	}

	function cr_pw_enter( string $typed ): void {
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = ( new PasswordHash( 8, true ) )->HashPassword( $typed );
	}

	function cr_pw_title_block(): array {
		return array(
			'blockName' => 'core/post-title',
			'attrs'     => array( 'className' => 'single-post__title' ),
		);
	}


	/**
	 * Render one block the way core does: pre_render_block short-circuits,
	 * otherwise the generic and the block-specific render_block filters run
	 * in priority order over the block's own output.
	 *
	 * @param array  $block Parsed block.
	 * @param string $html  What the block prints when nothing intervenes.
	 */
	function cr_pw_render( array $block, string $html ): string {
		$run = static function ( string $hook, $value, array $args ) {
			$filters = array_values( array_filter( $GLOBALS['cr_read_filters'], static fn( array $f ): bool => $f[0] === $hook ) );
			usort( $filters, static fn( array $a, array $b ): int => $a[2] <=> $b[2] );
			foreach ( $filters as $f ) {
				$value = call_user_func_array( $f[1], array_slice( array_merge( array( $value ), $args ), 0, $f[3] ) );
			}
			return $value;
		};
		$pre = $run( 'pre_render_block', null, array( $block, null ) );
		if ( null !== $pre ) {
			return (string) $pre;
		}
		$html = (string) $run( 'render_block', $html, array( $block ) );
		return (string) $run( 'render_block_' . $block['blockName'], $html, array( $block ) );
	}

	/**
	 * Fire a front-end request for one URL the way WordPress would: the
	 * theme's init callbacks run, then the template_redirect callbacks they
	 * queued. Returns what a visitor and WP Recipe Maker would see.
	 *
	 * @param string $uri Request URI.
	 * @return array{printer_unhooked: bool, is_404: bool, status: int}
	 */
	function cr_pw_print_request( string $uri ): array {
		$_SERVER['REQUEST_URI']      = $uri;
		$GLOBALS['cr_pw_removed']    = array();
		$GLOBALS['cr_pw_status']     = 200;
		$GLOBALS['cr_pw_nocache']    = false;
		$GLOBALS['wp_query']         = new WP_Query();
		$mine                        = static fn( array $a ): bool => is_string( $a[1] ) && str_starts_with( $a[1], 'Courtneyr\\Child\\Recipe\\' );
		$init                        = array_values( array_filter( $GLOBALS['cr_read_actions'], static fn( array $a ): bool => 'init' === $a[0] && $mine( $a ) ) );
		usort( $init, static fn( array $a, array $b ): int => $a[2] <=> $b[2] );
		$seen = count( $GLOBALS['cr_read_actions'] );
		foreach ( $init as $action ) {
			call_user_func( $action[1] );
		}
		foreach ( array_slice( $GLOBALS['cr_read_actions'], $seen ) as $action ) {
			if ( 'template_redirect' === $action[0] && $mine( $action ) ) {
				call_user_func( $action[1] );
			}
		}
		return array(
			'printer_unhooked' => in_array( array( 'init', array( 'WPRM_Print', 'print_page' ), 10 ), $GLOBALS['cr_pw_removed'], true ),
			'is_404'           => $GLOBALS['wp_query']->is_404,
			'status'           => $GLOBALS['cr_pw_status'],
		);
	}

	cr_read_group(
		'WP Recipe Maker print page answers 404 while the recipe\'s post is locked',
		static function (): void {
			$post = cr_pw_post( 'recipe', array() );
			$GLOBALS['cr_pw_types']                                  = array( 7 => 'wprm_recipe', 8 => 'wprm_recipe', $post->ID => 'post' );
			$GLOBALS['cr_pw_slugs']                                  = array( 'wprm_recipe:wprm-lentil-soup' => new WP_Post( 7, 'Lentil Soup' ) );
			$GLOBALS['cr_read_meta'][7]['wprm_parent_post_id']       = $post->ID;
			$urls = array(
				'id'                 => '/wprm_print/7',
				'id with a slash'    => '/wprm_print/7/',
				'recipe form'        => '/wprm_print/recipe/7',
				'recipe form + tpl'  => '/wprm_print/recipe/7/chic',
				'slug'               => '/wprm_print/lentil-soup',
				'query form'         => '/?wprm_print=7',
				'id + query string'  => '/wprm_print/7?servings=2',
			);
			$open   = array( 'printer_unhooked' => false, 'is_404' => false, 'status' => 200 );
			$closed = array( 'printer_unhooked' => true, 'is_404' => true, 'status' => 404 );

			$post->post_password = '';
			foreach ( $urls as $label => $uri ) {
				cr_read_same( $open, cr_pw_print_request( $uri ), 'public recipe print by ' . $label . ' is left to WP Recipe Maker' );
			}

			$post->post_password = CR_PW_SECRET;
			foreach ( $urls as $label => $uri ) {
				cr_read_same( $closed, cr_pw_print_request( $uri ), 'locked recipe print by ' . $label . ' answers 404' );
			}
			cr_read_same( true, $GLOBALS['cr_pw_nocache'], 'the 404 is not cached' );

			cr_pw_enter( 'wrong-guess' );
			foreach ( $urls as $label => $uri ) {
				cr_read_same( $closed, cr_pw_print_request( $uri ), 'recipe print by ' . $label . ' with a wrong password cookie answers 404' );
			}

			cr_pw_enter( CR_PW_SECRET );
			foreach ( $urls as $label => $uri ) {
				cr_read_same( $open, cr_pw_print_request( $uri ), 'recipe print by ' . $label . ' after the password is left to WP Recipe Maker' );
			}

			$post->post_password = CR_PW_SECRET;
			unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
			cr_read_same( $open, cr_pw_print_request( '/watch/the-lantern-archive/' ), 'a locked post\'s own page is not a print request' );
			cr_read_same( $open, cr_pw_print_request( '/wprm_print/8' ), 'a recipe with no parent post is left to WP Recipe Maker' );
			cr_read_same( $open, cr_pw_print_request( '/wprm_print/42' ), 'an id that is not a recipe is left to WP Recipe Maker' );
			cr_read_same( $open, cr_pw_print_request( '/wprm_print/no-such-recipe' ), 'an unknown slug is left to WP Recipe Maker' );
			WPRM_Print::$slug = 'print-it';
			cr_read_same( $open, cr_pw_print_request( '/wprm_print/7' ), 'the default print slug is not special once the site renames it' );
			cr_read_same( $closed, cr_pw_print_request( '/print-it/7' ), 'a renamed print slug is blocked too' );
			WPRM_Print::$slug = 'wprm_print';
		}
	);

	cr_read_group(
		'locked single prints no featured image and no kind or category chips',
		static function (): void {
			$header = array(
				'featured image' => array( array( 'blockName' => 'core/post-featured-image', 'attrs' => array( 'className' => 'single-post__featured' ) ), '<figure class="wp-block-post-featured-image single-post__featured"><img src="https://cards.test/cover.jpg" alt=""></figure>' ),
				'kind chip'      => array( array( 'blockName' => 'core/post-terms', 'attrs' => array( 'className' => 'cr-chip single-post__kind' ) ), '<div class="taxonomy-kind cr-chip single-post__kind wp-block-post-terms"><a href="/kind/x/">Kind</a></div>' ),
				'category chip'  => array( array( 'blockName' => 'core/post-terms', 'attrs' => array( 'className' => 'cr-chip single-post__category' ) ), '<div class="taxonomy-category cr-chip single-post__category wp-block-post-terms"><a href="/category/x/">Category</a></div>' ),
			);
			$others = array(
				'related terms'   => array( array( 'blockName' => 'core/post-terms', 'attrs' => array( 'className' => 'related-post__terms' ) ), '<div class="related-post__terms"><a href="/kind/x/">Kind</a></div>' ),
				'card image'      => array( array( 'blockName' => 'core/post-featured-image', 'attrs' => array( 'className' => 'cr-blog-grid__image' ) ), '<figure class="wp-block-post-featured-image cr-blog-grid__image"><img src="https://cards.test/cover.jpg" alt=""></figure>' ),
				'tags'            => array( array( 'blockName' => 'core/post-terms', 'attrs' => array( 'className' => 'single-post__tags is-style-term-button' ) ), '<div class="single-post__tags"><a href="/tag/x/">Tag</a></div>' ),
			);
			foreach ( array( 'watch', 'comics', 'recipe', 'quote', 'read', 'checkin', 'like' ) as $kind ) {
				$post                = cr_pw_post( $kind, array() );
				$post->post_password = '';
				$public = static fn( string $label, array $row ): string => 'read' === $kind && str_contains( $row[1], 'wp-block-post-featured-image' ) ? '' : $row[1];
				foreach ( $header as $label => $row ) {
					cr_read_same( $public( $label, $row ), cr_pw_render( $row[0], $row[1] ), $kind . ' public single prints the ' . $label );
				}

				$post->post_password = CR_PW_SECRET;
				foreach ( $header as $label => $row ) {
					cr_read_same( '', cr_pw_render( $row[0], $row[1] ), $kind . ' before the password prints no ' . $label );
				}
				foreach ( $others as $label => $row ) {
					cr_read_same( $public( $label, $row ), cr_pw_render( $row[0], $row[1] ), $kind . ' before the password keeps the ' . $label );
				}

				cr_pw_enter( 'wrong-guess' );
				foreach ( $header as $label => $row ) {
					cr_read_same( '', cr_pw_render( $row[0], $row[1] ), $kind . ' with a wrong password cookie prints no ' . $label );
				}

				cr_pw_enter( CR_PW_SECRET );
				foreach ( $header as $label => $row ) {
					cr_read_same( $public( $label, $row ), cr_pw_render( $row[0], $row[1] ), $kind . ' after the password prints the ' . $label );
				}
			}
		}
	);

	cr_read_group(
		'watch lede prints no film facts before the password',
		static function (): void {
			$block = array(
				'blockName' => 'post-kinds-indieweb/watch-card',
				'attrs'     => array(
					'mediaTitle'  => 'The Lantern Archive',
					'mediaType'   => 'movie',
					'releaseYear' => 1998,
					'director'    => 'Mara Quill',
					'rating'      => 4,
				),
			);
			$post  = cr_pw_post( 'watch', array( $block ) );
			$post->post_password = '';
			$public              = Courtneyr\Child\SingleWatch\add_title_lede( '<h1>T</h1>', cr_pw_title_block() );
			cr_read_contains( 'Mara Quill', $public, 'watch public post prints the director' );
			cr_read_contains( '1998', $public, 'watch public post prints the year' );
			cr_read_contains( '4 / 5', $public, 'watch public post prints the rating' );

			$post->post_password = CR_PW_SECRET;
			$locked              = Courtneyr\Child\SingleWatch\add_title_lede( '<h1>T</h1>', cr_pw_title_block() );
			cr_read_same( '<h1>T</h1>', $locked, 'watch before the password prints the title block alone' );
			foreach ( array( 'Mara Quill', '1998', 'Film', '4 / 5', 'cr-journal__rating', 'cr-journal__lede' ) as $fact ) {
				cr_read_not_contains( $fact, $locked, 'watch before the password prints ' . $fact );
			}

			cr_pw_enter( 'wrong-guess' );
			cr_read_same( '<h1>T</h1>', Courtneyr\Child\SingleWatch\add_title_lede( '<h1>T</h1>', cr_pw_title_block() ), 'watch with a wrong password cookie prints the title block alone' );

			cr_pw_enter( CR_PW_SECRET );
			cr_read_same( $public, Courtneyr\Child\SingleWatch\add_title_lede( '<h1>T</h1>', cr_pw_title_block() ), 'watch after the password prints what the public post prints' );
		}
	);

	cr_read_group(
		'comics lede prints no creators, rating or status before the password',
		static function (): void {
			$block = array(
				'blockName' => 'post-kinds-indieweb/comic-card',
				'attrs'     => array(
					'title'      => 'Paper Kites #3',
					'creators'   => 'Ivo Penn, Rue Calder',
					'readStatus' => 'finished',
					'rating'     => 4,
				),
			);
			$post  = cr_pw_post( 'comics', array( $block ) );
			$post->post_password = '';
			$public              = Courtneyr\Child\Comic\add_title_lede( '<h1>T</h1>', cr_pw_title_block() );
			cr_read_contains( 'Ivo Penn, Rue Calder', $public, 'comics public post prints the creators' );
			cr_read_contains( '4 / 5', $public, 'comics public post prints the rating' );
			cr_read_contains( 'cr-comic__status', $public, 'comics public post prints the status' );

			$post->post_password = CR_PW_SECRET;
			$locked              = Courtneyr\Child\Comic\add_title_lede( '<h1>T</h1>', cr_pw_title_block() );
			cr_read_same( '<h1>T</h1>', $locked, 'comics before the password prints the title block alone' );
			foreach ( array( 'Ivo Penn', 'Rue Calder', '4 / 5', 'cr-journal__rating', 'cr-comic__status', 'Finished' ) as $fact ) {
				cr_read_not_contains( $fact, $locked, 'comics before the password prints ' . $fact );
			}

			cr_pw_enter( 'wrong-guess' );
			cr_read_same( '<h1>T</h1>', Courtneyr\Child\Comic\add_title_lede( '<h1>T</h1>', cr_pw_title_block() ), 'comics with a wrong password cookie prints the title block alone' );

			cr_pw_enter( CR_PW_SECRET );
			cr_read_same( $public, Courtneyr\Child\Comic\add_title_lede( '<h1>T</h1>', cr_pw_title_block() ), 'comics after the password prints what the public post prints' );
		}
	);

	cr_read_group(
		'recipe lede prints no summary or print link before the password',
		static function (): void {
			$post = cr_pw_post( 'recipe', array() );
			$post->post_password = '';
			$public              = Courtneyr\Child\Recipe\add_title_lede( '<h1>T</h1>', cr_pw_title_block() );
			cr_read_contains( 'red lentils and thyme', $public, 'recipe public post prints the summary' );
			cr_read_contains( 'wprm_print/7', $public, 'recipe public post prints the print link' );

			$post->post_password = CR_PW_SECRET;
			$locked              = Courtneyr\Child\Recipe\add_title_lede( '<h1>T</h1>', cr_pw_title_block() );
			cr_read_same( '<h1>T</h1>', $locked, 'recipe before the password prints the title block alone' );
			foreach ( array( 'red lentils', 'wprm_print', 'Print Recipe', 'cr-recipe__summary' ) as $fact ) {
				cr_read_not_contains( $fact, $locked, 'recipe before the password prints ' . $fact );
			}

			cr_pw_enter( 'wrong-guess' );
			cr_read_same( '<h1>T</h1>', Courtneyr\Child\Recipe\add_title_lede( '<h1>T</h1>', cr_pw_title_block() ), 'recipe with a wrong password cookie prints the title block alone' );

			cr_pw_enter( CR_PW_SECRET );
			cr_read_same( $public, Courtneyr\Child\Recipe\add_title_lede( '<h1>T</h1>', cr_pw_title_block() ), 'recipe after the password prints what the public post prints' );
		}
	);

	/**
	 * Run a Stream card filter for one post through the three states a visitor passes through.
	 *
	 * The plugin prints a title-only card for a locked post, so that card is the
	 * locked input and the filter must hand it back untouched.
	 *
	 * @param string   $label     Kind name for messages.
	 * @param string   $kind      Kind slug the post carries.
	 * @param array    $blocks    Blocks parsed from the post content.
	 * @param callable $filter    The stream-card render filter.
	 * @param string   $full_card The plugin's card for a public post.
	 * @param string[] $facts     Strings the public card prints and the locked one must not.
	 * @param callable $render    What render_block() returns for a card block, or null.
	 */
	function cr_pw_stream_check( string $label, string $kind, array $blocks, callable $filter, string $full_card, array $facts, ?callable $render = null ): void {
		$protected = '<article class="pk-card pk-card--stream pk-card--protected k-' . $kind . ' h-entry"><h2 class="pk-title p-name"><a class="u-url" href="https://cards.test/p">Protected: Fixture</a></h2></article>';
		$post      = cr_pw_post( $kind, $blocks );
		$GLOBALS['cr_read_render'] = $render;
		$instance  = new WP_Block( array( 'postId' => $post->ID ) );
		$run       = static fn( string $html ): string => (string) $filter( $html, array( 'attrs' => array() ), $instance );

		$post->post_password = '';
		$public              = $run( $full_card );
		cr_read_assert( $public !== $full_card, $label . ' public card is rebuilt, so the check can see the change' );
		foreach ( $facts as $fact ) {
			cr_read_contains( $fact, $public, $label . ' public card prints ' . $fact );
		}

		$post->post_password = CR_PW_SECRET;
		$locked              = $run( $protected );
		cr_read_same( $protected, $locked, $label . ' before the password prints the plugin\'s title-only card untouched' );
		foreach ( $facts as $fact ) {
			cr_read_not_contains( $fact, $locked, $label . ' before the password prints ' . $fact );
		}

		cr_pw_enter( 'wrong-guess' );
		cr_read_same( $protected, $run( $protected ), $label . ' with a wrong password cookie prints the title-only card untouched' );

		cr_pw_enter( CR_PW_SECRET );
		cr_read_same( $public, $run( $full_card ), $label . ' after the password prints what the public card prints' );
	}

	cr_read_group(
		'comics Stream card prints no creators, rating or status before the password',
		static function (): void {
			$attrs = array( 'title' => 'Paper Kites #3', 'creators' => 'Ivo Penn, Rue Calder', 'readStatus' => 'finished', 'rating' => 4 );
			$card  = '<article class="pk-card k-comics h-cite u-read-of"><div class="pk-body"><div class="pk-caption"><h2 class="pk-title p-name"><a href="https://cards.test/p">Paper Kites #3</a></h2><p class="pk-sub">Ivo Penn, Rue Calder</p></div><div class="pk-stars" role="img" aria-label="Rated 4 of 5"></div><data class="p-rating" value="4" hidden></data><div class="pk-meta"></div></div></article>';
			cr_pw_stream_check( 'comics', 'comics', array( array( 'blockName' => 'post-kinds-indieweb/comic-card', 'attrs' => $attrs ) ), 'Courtneyr\Child\Comic\bag_card', $card, array( 'Ivo Penn', '4 / 5' ), static fn(): string => $card );
		}
	);

	cr_read_group(
		'check-in Stream card prints no venue or note before the password',
		static function (): void {
			$attrs = array( 'venueName' => 'Example Corner Library', 'locality' => 'Example Town', 'region' => 'PA', 'country' => 'US', 'note' => 'Quiet tables by the window.' );
			$card  = '<article class="pk-card k-checkin h-entry"><h2 class="pk-title p-name">Example Corner Library</h2><p class="pk-sub p-location">Example Town, PA, US</p><div class="pk-note p-content"><p>Quiet tables by the window.</p></div></article>';
			cr_pw_stream_check( 'check-in', 'checkin', array( array( 'blockName' => 'post-kinds-indieweb/checkin-card', 'attrs' => $attrs ) ), 'Courtneyr\Child\StreamCheckin\passport_card', '<article class="pk-card pk-card--stream k-checkin"></article>', array( 'Example Corner Library', 'Quiet tables' ), static fn(): string => $card );
		}
	);

	foreach ( array(
	'eat'   => array( 'Lentil Soup', '<em>Soup</em><span class="p-location">Example Diner</span>', 'Example Diner', 'Warm, peppery and filling.' ),
	'drink' => array( 'Mint Iced Tea', '<span>Tea</span><span class="p-author">Example Tea Co.</span>', 'Example Tea Co.', 'Cold and bright.' ),
) as $cr_pw_kind => $cr_pw_row ) {
		cr_read_group(
			$cr_pw_kind . ' Stream card prints no name, maker, rating or note before the password',
			static function () use ( $cr_pw_kind, $cr_pw_row ): void {
				$card = '<article class="pk-card k-' . $cr_pw_kind . ' h-food"><h2 class="pk-title p-name">' . $cr_pw_row[0] . '</h2><p class="pk-sub">' . $cr_pw_row[1] . '</p><div class="pk-stars" role="img" aria-label="Rated 4 of 5"></div><p class="pk-note p-content">' . $cr_pw_row[3] . '</p></article>';
				cr_pw_stream_check( $cr_pw_kind, $cr_pw_kind, array( array( 'blockName' => 'post-kinds-indieweb/' . $cr_pw_kind . '-card', 'attrs' => array( 'name' => $cr_pw_row[0] ) ) ), 'Courtneyr\Child\StreamOrder\card', '<article class="pk-card pk-card--stream k-' . $cr_pw_kind . '"></article>', array( $cr_pw_row[2], $cr_pw_row[3], 'Rated 4 of 5' ), static fn(): string => $card );
			}
		);
	}

	cr_read_group(
		'quote Stream card prints no citation before the password',
		static function (): void {
			$quote = array( 'blockName' => 'core/quote', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '<blockquote class="wp-block-quote"><p>Fixture words.</p><cite>Locked Ledger</cite></blockquote>', 'innerContent' => array() );
			$card  = '<article class="pk-card pk-card--stream k-quote h-cite"><div class="pk-caption"><p class="pk-sub pk-stream-date">March 5, 2023</p></div><div class="pk-meta"></div></article>';
			cr_pw_stream_check( 'quote', 'quote', array( $quote ), 'Courtneyr\Child\QuoteNote\stream_card', $card, array( 'Locked Ledger' ) );
		}
	);

	cr_read_group(
		'read Stream card prints no author before the password',
		static function (): void {
			$attrs     = array( 'bookTitle' => 'The Quiet Atlas', 'authorName' => 'Lena Orchard', 'readStatus' => 'finished', 'rating' => 4 );
			$card      = '<article class="wp-block-post-kinds-indieweb-read-card pk-card k-read h-cite u-read-of"><div class="pk-body"><div class="pk-caption"><h2 class="pk-title p-name"><a href="https://cards.test/p">The Quiet Atlas</a></h2><p class="pk-sub"><span class="p-author h-card"><span class="p-name">Lena Orchard</span></span></p><p class="pk-sub"><span>Finished</span></p></div><div class="pk-stars" role="img" aria-label="Rated 4 of 5"><svg></svg></div><data class="p-rating" value="4" hidden></data><div class="pk-meta"></div></div></article>';
			$protected = '<article class="pk-card pk-card--stream pk-card--protected k-read h-entry"><h2 class="pk-title p-name"><a class="u-url" href="https://cards.test/p">Protected: The Quiet Atlas</a></h2></article>';
			$post      = cr_pw_post( 'read', array( array( 'blockName' => 'post-kinds-indieweb/read-card', 'attrs' => $attrs ) ) );
			$instance  = new WP_Block( array( 'postId' => $post->ID ) );
			$run       = static fn( string $html ): string => Courtneyr\Child\StreamRead\book_card( $html, array( 'attrs' => array() ), $instance );

			$post->post_password = '';
			$public              = $run( $card );
			cr_read_contains( 'Lena Orchard', $public, 'read public card prints the author' );

			$post->post_password = CR_PW_SECRET;
			$expected            = substr( $protected, 0, -10 ) . '<div class="cr-book__protected-form">' . get_the_password_form( $post ) . '</div></article>';
			$locked              = $run( $protected );
			cr_read_same( $expected, $locked, 'read before the password prints the title-only card and the password form' );
			foreach ( array( 'Lena Orchard', 'cr-book__protected-author' ) as $fact ) {
				cr_read_not_contains( $fact, $locked, 'read before the password prints ' . $fact );
			}

			cr_pw_enter( 'wrong-guess' );
			cr_read_same( $expected, $run( $protected ), 'read with a wrong password cookie prints the title-only card and the password form' );

			cr_pw_enter( CR_PW_SECRET );
			cr_read_same( $public, $run( $card ), 'read after the password prints what the public card prints' );
		}
	);

	foreach ( array( 'eat', 'exercise' ) as $cr_pw_map_kind ) {
		cr_read_group(
			$cr_pw_map_kind . ' map image keeps an alt with no address before the password',
			static function () use ( $cr_pw_map_kind ): void {
				$post                = cr_pw_post( $cr_pw_map_kind, array() );
				$post->post_password = '';
				$GLOBALS['cr_read_meta'][ $post->ID ]['geo_address'] = '9 SENTINELGEO Lane';
				$content = '<p>Fixture post.</p><img class="sloc-map" src="https://cards.test/map.png" loading="lazy" />';
				$public  = Courtneyr\Child\SlocMapAlt\add_map_alt( $content );
				cr_read_contains( 'alt="Map showing 9 SENTINELGEO Lane"', $public, $cr_pw_map_kind . ' public post prints the address in the map alt' );

				$generic = '<p>Fixture post.</p><img alt="Map" class="sloc-map" src="https://cards.test/map.png" loading="lazy" />';

				$post->post_password = CR_PW_SECRET;
				$locked              = Courtneyr\Child\SlocMapAlt\add_map_alt( $content );
				cr_read_same( $generic, $locked, $cr_pw_map_kind . ' before the password gives the map a generic alt' );
				cr_read_not_contains( 'SENTINELGEO', $locked, $cr_pw_map_kind . ' before the password prints no address' );

				$named = '<img alt="Fixture map" class="sloc-map" src="https://cards.test/map.png" />';
				cr_read_same( $named, Courtneyr\Child\SlocMapAlt\add_map_alt( $named ), $cr_pw_map_kind . ' before the password keeps an alt the image already has' );

				cr_pw_enter( 'wrong-guess' );
				cr_read_same( $generic, Courtneyr\Child\SlocMapAlt\add_map_alt( $content ), $cr_pw_map_kind . ' with a wrong password cookie gives the map a generic alt' );

				cr_pw_enter( CR_PW_SECRET );
				cr_read_same( $public, Courtneyr\Child\SlocMapAlt\add_map_alt( $content ), $cr_pw_map_kind . ' after the password prints what the public post prints' );
			}
		);
	}

	cr_read_finish();
}
