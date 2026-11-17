<?php
/**
 * Follow theme privacy boundary (acceptance item 18).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/follow-bootstrap.php';
	require CR_FOLLOW_THEME_DIR . '/inc/stamps.php';
	require CR_FOLLOW_THEME_DIR . '/inc/follow.php';
	require CR_FOLLOW_THEME_DIR . '/inc/single-follow.php';
	require CR_FOLLOW_THEME_DIR . '/inc/stream-follow.php';

	use Courtneyr\Child\Follow;
	use Courtneyr\Child\SingleFollow;
	use Courtneyr\Child\StreamFollow;

	const CR_FOLLOW_SENTINEL_NAME  = 'Fw260 Sentinel Person';
	const CR_FOLLOW_SENTINEL_URL   = 'https://fw260-sentinel.example/';
	const CR_FOLLOW_SENTINEL_PHOTO = 'fw260-sentinel.png';

	cr_follow_check(
		'every adapter passes incoming HTML through when facts are empty',
		static function (): void {
			$GLOBALS['cr_follow_singular'] = true;
			$GLOBALS['cr_follow_stream']   = true;
			foreach ( range( 601, 606 ) as $id ) {
				$post               = new WP_Post( $id );
				$post->post_title   = CR_FOLLOW_SENTINEL_NAME;
				$post->post_content = '<p>Followed <a class="u-follow-of" href="' . CR_FOLLOW_SENTINEL_URL . '">' . CR_FOLLOW_SENTINEL_URL . '</a></p><img src="' . CR_FOLLOW_SENTINEL_PHOTO . '">';
				$GLOBALS['cr_follow_posts'][ $id ] = $post;
				$GLOBALS['cr_follow_facts'][ $id ] = array();
				$GLOBALS['cr_follow_queried']      = $id;
				$block = new WP_Block( array( 'postId' => $id ) );
				$attrs = array( 'attrs' => array( 'name' => CR_FOLLOW_SENTINEL_NAME, 'url' => CR_FOLLOW_SENTINEL_URL, 'photo' => CR_FOLLOW_SENTINEL_PHOTO ) );

				$single = '<div class="pkiw-protected-card">Protected follow</div>';
				$stream = '<article class="pk-card pk-card--stream">Protected follow</article>';
				$entry  = '<article class="pkiw-follow-entry">Protected follow</article>';
				$para   = '<p>Password protected.</p>';
				$outputs = array(
					SingleFollow\card( $single, $attrs, $block ),
					SingleFollow\drop_generated( $para, array( 'crGenerated' => true, 'innerHTML' => $post->post_content ) ),
					StreamFollow\card( $stream, $attrs, $block ),
					Follow\entry_paint( $entry, array( 'attrs' => array( 'className' => 'is-style-cr-rolodex', 'name' => CR_FOLLOW_SENTINEL_NAME ) ), $block ),
				);
				cr_follow_same( array( $single, $para, $stream, $entry ), $outputs, 'all incoming HTML passes through for ' . $id );
				foreach ( $outputs as $output ) {
					foreach ( array( CR_FOLLOW_SENTINEL_NAME, CR_FOLLOW_SENTINEL_URL, CR_FOLLOW_SENTINEL_PHOTO ) as $sentinel ) {
						cr_follow_not_contains( $sentinel, $output, 'sentinel stays out for ' . $id );
					}
				}
			}
		}
	);

	cr_follow_check(
		'the facts emptiness check is the single-card privacy gate',
		static function (): void {
			$sentinel_facts = cr_follow_facts(
				array(
					'name'      => CR_FOLLOW_SENTINEL_NAME,
					'url'       => CR_FOLLOW_SENTINEL_URL,
					'host'      => 'fw260-sentinel.example',
					'photo_alt' => CR_FOLLOW_SENTINEL_PHOTO,
				)
			);
			$negative_control = SingleFollow\card_html( $sentinel_facts, '' );
			cr_follow_contains( CR_FOLLOW_SENTINEL_NAME, $negative_control, 'card_html would print facts if the gate called it' );
			cr_follow_contains( CR_FOLLOW_SENTINEL_URL, $negative_control, 'card_html would print the URL if the gate called it' );
		}
	);

	cr_follow_check(
		'every adapter prints the sentinel with public facts and nothing with empty facts',
		static function (): void {
			$GLOBALS['cr_follow_singular'] = true;
			$GLOBALS['cr_follow_stream']   = true;
			$id                            = 701;
			$post                          = new WP_Post( $id );
			$GLOBALS['cr_follow_posts'][ $id ] = $post;
			$GLOBALS['cr_follow_queried']      = $id;
			$block   = new WP_Block( array( 'postId' => $id ) );
			$attrs   = array( 'attrs' => array( 'className' => 'is-style-cr-rolodex' ) );
			$single  = '<div class="entry-content"><p>Core content</p></div>';
			$stream  = '<article class="pk-card pk-card--stream"><h2 class="pk-title">Incoming</h2></article>';
			$entry   = '<article class="pkiw-follow-entry h-entry">Entry</article>';
			$sentinel = cr_follow_facts(
				array(
					'post_id'     => $id,
					'name'        => CR_FOLLOW_SENTINEL_NAME,
					'url'         => CR_FOLLOW_SENTINEL_URL,
					'host'        => 'fw260-sentinel.example',
					'title_empty' => true,
					'photo_id'    => 7,
				)
			);
			$run = static function () use ( $single, $stream, $entry, $attrs, $block, $post ): array {
				return array(
					'single' => SingleFollow\card( $single, array(), $block ),
					'stream' => StreamFollow\card( $stream, array(), $block ),
					'entry'  => Follow\entry_paint( $entry, $attrs, $block ),
					'h1'     => SingleFollow\holds_h1( false, $post ),
					'repeat' => SingleFollow\featured_repeats( false, $post ),
				);
			};

			$GLOBALS['cr_follow_facts'][ $id ] = array();
			$empty                              = $run();
			cr_follow_same( $single, $empty['single'], 'empty facts leave the single content unchanged' );
			cr_follow_same( $stream, $empty['stream'], 'empty facts leave the Stream card unchanged' );
			cr_follow_same( $entry, $empty['entry'], 'empty facts leave the archive entry unchanged' );
			cr_follow_same( false, $empty['h1'], 'empty facts leave the H1 decision unchanged' );
			cr_follow_same( false, $empty['repeat'], 'empty facts leave the featured-image repeat decision unchanged' );

			$GLOBALS['cr_follow_facts'][ $id ] = $sentinel;
			$public                             = $run();
			cr_follow_contains( CR_FOLLOW_SENTINEL_NAME, $public['single'], 'public facts print the name on the single' );
			cr_follow_contains( CR_FOLLOW_SENTINEL_NAME, $public['stream'], 'public facts print the name on the Stream card' );
			cr_follow_contains( '--cr-follow-paper', $public['entry'], 'public facts paint the archive entry' );
			cr_follow_same( true, $public['h1'], 'public title-less facts let the card hold the H1' );
			cr_follow_same( true, $public['repeat'], 'a public photo makes the card repeat the featured image' );
		}
	);

	cr_follow_finish();
}
