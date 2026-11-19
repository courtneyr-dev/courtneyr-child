<?php
/**
 * Check seeded, saved, and protected Eat and Drink cards.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

use Courtneyr\Child\SingleOrder;
use Courtneyr\Child\StreamOrder;

require __DIR__ . '/read-bootstrap.php';

function get_the_terms( int $post_id, string $taxonomy ): array {
	return array( (object) array( 'slug' => $GLOBALS['cr_read_kind'] ) );
}

function get_term_by( string $field, string $value, string $taxonomy ): false {
	return false;
}

require dirname( __DIR__, 2 ) . '/inc/artifact-style.php';
require dirname( __DIR__, 2 ) . '/inc/stream-media.php';
require dirname( __DIR__, 2 ) . '/inc/single-eat-drink.php';
require dirname( __DIR__, 2 ) . '/inc/stream-eat-drink.php';

foreach ( array( 'eat' => 'cr-order-ticket', 'drink' => 'cr-coaster' ) as $kind => $style ) {
	cr_read_group(
		$kind . ' style applies once on the single and Stream',
		static function () use ( $kind, $style ): void {
			$post                     = cr_read_reset();
			$post->ID                 = 'eat' === $kind ? 401 : 402;
			$GLOBALS['cr_read_kind']   = $kind;
			$GLOBALS['cr_read_blocks'] = array( array( 'blockName' => 'post-kinds-indieweb/' . $kind . '-card' ) );
			$token                    = 'is-style-' . $style;
			$article                  = '<article class="pk-card k-' . $kind . ' h-food"><h2 class="pk-title p-name">Test order</h2></article>';
			$saved                    = str_replace( ' h-food', ' h-food ' . $token, $article );
			$block                    = array( 'blockName' => 'core/post-content' );
			$stream                   = new WP_Block( array( 'postId' => $post->ID ) );
			foreach ( array( $article, $saved ) as $input ) {
				$single = SingleOrder\placemat( $input, $block );
				cr_read_contains( 'cr-placemat--' . $kind, $single, 'single keeps its original placemat root' );
				cr_read_same( 1, substr_count( $single, $token ), 'single style is present exactly once' );
				$card = StreamOrder\card( $input, array(), $stream );
				cr_read_contains( 'cr-chit--' . $kind, $card, 'Stream keeps its original chit root' );
				cr_read_same( 1, substr_count( $card, $token ), 'Stream style is present exactly once' );
			}
			$post->post_password = 'local-fixture';
			$protected          = '<form class="post-password-form"></form>';
			cr_read_same( $protected, SingleOrder\placemat( $protected, $block ), 'locked single keeps only the existing password form' );
			cr_read_same( $protected, StreamOrder\card( $protected, array(), $stream ), 'locked Stream content keeps its previous renderer' );
		}
	);
}

exit( empty( $GLOBALS['cr_read_failures'] ) ? 0 : 1 );
