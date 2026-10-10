<?php
/**
 * Standalone checks for listen single star output.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/read-bootstrap.php';

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $post_id, $taxonomy ) {
		return array( (object) array( 'slug' => $GLOBALS['cr_read_kind'] ) );
	}
}

if ( ! function_exists( 'get_stylesheet' ) ) {
	function get_stylesheet(): string {
		return 'courtneyr-child';
	}
}

if ( ! function_exists( 'get_block_template' ) ) {
	function get_block_template( $id, $template_type = 'wp_template' ): object {
		return (object) array( 'id' => $id, 'type' => $template_type );
	}
}

require dirname( __DIR__, 2 ) . '/inc/kind-parts.php';

use function Courtneyr\Child\KindParts\hide_duplicate_listen_stars_label;

function cr_listen_card( string $extra = '' ): string {
	return '<article class="pk-card k-listen h-cite"><div class="pk-body"><div class="pk-stars" role="img" aria-label="Rated 4 of 5">stars</div>' . $extra . '</div></article>';
}

cr_read_group(
	'listen single stars stay visible but become decorative when sources print rating',
	static function (): void {
		$post                       = cr_read_reset();
		$GLOBALS['cr_read_kind']    = 'listen';
		$GLOBALS['cr_read_meta'][42] = array( '_pkiw_listen_rating' => '4' );
		$html                       = hide_duplicate_listen_stars_label( cr_listen_card( '<data class="p-rating" value="4"></data>' ), array( 'attrs' => array( 'rating' => 4 ) ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_read_contains( 'class="pk-stars"', $html, 'stars still present' );
		cr_read_contains( 'aria-hidden="true"', $html, 'stars hidden from accessibility tree' );
		cr_read_not_contains( 'role="img"', $html, 'duplicate role removed' );
		cr_read_not_contains( 'aria-label="Rated 4 of 5"', $html, 'duplicate label removed' );
	}
);

cr_read_group(
	'listen card stars are untouched when sources have no rating',
	static function (): void {
		$post                    = cr_read_reset();
		$GLOBALS['cr_read_kind'] = 'listen';
		$html                    = cr_listen_card();
		cr_read_same( $html, hide_duplicate_listen_stars_label( $html, array( 'attrs' => array( 'rating' => 0 ) ), new WP_Block( array( 'postId' => $post->ID ) ) ), 'no rating unchanged' );
		$GLOBALS['cr_read_kind'] = 'watch';
		cr_read_same( $html, hide_duplicate_listen_stars_label( $html, array( 'attrs' => array( 'rating' => 4 ) ), new WP_Block( array( 'postId' => $post->ID ) ) ), 'other kind unchanged' );
	}
);

cr_read_finish();
