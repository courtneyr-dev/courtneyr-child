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

namespace {
	function do_shortcode( string $content ): string {
		return '<a class="wprm-recipe-print" href="https://cards.test/wprm_print/7">Print Recipe</a>';
	}

	require dirname( __DIR__, 2 ) . '/inc/single-watch.php';
	require dirname( __DIR__, 2 ) . '/inc/comic.php';
	require dirname( __DIR__, 2 ) . '/inc/recipe.php';

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

	cr_read_finish();
}
