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

	cr_read_finish();
}
