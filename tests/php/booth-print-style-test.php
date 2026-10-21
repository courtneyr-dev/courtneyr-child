<?php
/**
 * `php tests/php/booth-print-style-test.php`: Gallery block style registration,
 * author choice and Stream booth-print behavior, without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/photo-bootstrap.php';

use function Courtneyr\Child\StreamGallery\booth_print_mode;
use function Courtneyr\Child\StreamGallery\booth_strip_card;
use function Courtneyr\Child\StreamGallery\gallery_block_style;

/* WordPress stand-ins used by the gallery walker and strip renderer. */
if ( ! function_exists( 'parse_blocks' ) ) {
	function parse_blocks( string $content ): array {
		return $GLOBALS['cr_booth_blocks'][ $content ] ?? array();
	}
	function get_attached_media( $type, $post = 0 ): array {
		return array();
	}
	function has_post_thumbnail( $post = null ): bool {
		return false;
	}
	function get_post_thumbnail_id( $post = null ): int {
		return 0;
	}
	function get_post_field( $field, $post = null, $context = 'display' ): string {
		return '';
	}
	function _n( $single, $plural, $number, $domain = null ): string {
		return 1 === (int) $number ? $single : $plural;
	}
	function get_post_time( $format = 'U', $gmt = false, $post = null, $translate = false ) {
		return 1788876000;
	}
	function wp_date( $format, $timestamp = null, $timezone = null ): string {
		return gmdate( $format, (int) $timestamp );
	}
	function get_the_category( $post_id = false ): array {
		return array();
	}
}

/** One parsed block in the same shape WordPress parse_blocks() returns. */
function cr_booth_block( string $name, array $attrs = array(), array $inner = array() ): array {
	return array(
		'blockName'   => $name,
		'attrs'       => $attrs,
		'innerBlocks' => $inner,
	);
}

/** A parsed Gallery block with three image children. */
function cr_booth_gallery( ?string $classes = null, int $first_id = 701 ): array {
	$attrs = null === $classes ? array() : array( 'className' => $classes );
	return cr_booth_block(
		'core/gallery',
		$attrs,
		array(
			cr_booth_block( 'core/image', array( 'id' => $first_id ) ),
			cr_booth_block( 'core/image', array( 'id' => $first_id + 1 ) ),
			cr_booth_block( 'core/image', array( 'id' => $first_id + 2 ) ),
		)
	);
}

/** Register a parsed block fixture as one post's content. */
function cr_booth_post( int $id, array $blocks, array $kinds = array( 'photo' ) ): WP_Post {
	$post = cr_photo_reset( $id );
	$key  = 'cr-booth-blocks-' . $id;
	$GLOBALS['cr_booth_blocks'][ $key ]             = $blocks;
	$GLOBALS['cr_photo_posts'][ $id ]->post_content = $key;
	$GLOBALS['cr_photo_request']['stream']           = true;
	$GLOBALS['cr_photo_request']['format'][ $id ]    = 'gallery';
	$GLOBALS['cr_photo_request']['post_kinds'][ $id ] = $kinds;
	return $post;
}

/** A plugin photo card whose media block can be replaced byte-for-byte. */
function cr_booth_photo_card(): string {
	return '<article class="pk-card pk-card--stream pk-card--photo k-photo h-entry"><div class="pk-body"><div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/photo/42/">Blue Hour</a></h2></div><div class="pk-media pk-media--stream"><img src="lead.jpg" alt="" /><p>Caption</p></div></div></article>';
}

cr_photo_group(
	'booth_print_mode covers the author choice and seeded default truth table',
	static function (): void {
		$cases = array(
			array( 'booth-print', true, 'print' ),
			array( '', true, 'print' ),
			array( 'other', true, 'none' ),
			array( 'lightbox-slideshow', true, 'none' ),
			array( 'booth-print', false, 'strip' ),
			array( '', false, 'strip' ),
			array( 'other', false, 'strip' ),
			array( 'lightbox-slideshow', false, 'strip' ),
		);
		foreach ( $cases as $case ) {
			cr_photo_same( $case[2], booth_print_mode( $case[0], $case[1] ), $case[0] . '/' . ( $case[1] ? 'photo' : 'not-photo' ) );
		}
	}
);

cr_photo_group(
	'gallery_block_style classifies the first Gallery block recursively',
	static function (): void {
		$cases = array(
			'no gallery'     => array( array( cr_booth_block( 'core/paragraph' ) ), '' ),
			'no class'       => array( array( cr_booth_gallery() ), '' ),
			'booth print'    => array( array( cr_booth_gallery( "alignwide\tis-style-cr-booth-print\nextra" ) ), 'booth-print' ),
			'other style'    => array( array( cr_booth_gallery( 'is-style-lightbox-slideshow' ) ), 'other' ),
			'nested gallery' => array( array( cr_booth_block( 'core/group', array(), array( cr_booth_gallery( 'is-style-cr-booth-print' ) ) ) ), 'booth-print' ),
			'first gallery'  => array( array( cr_booth_gallery( 'is-style-lightbox-slideshow' ), cr_booth_gallery( 'is-style-cr-booth-print', 801 ) ), 'other' ),
		);
		$id = 100;
		foreach ( $cases as $label => $case ) {
			$post = cr_booth_post( ++$id, $case[0] );
			cr_photo_same( $case[1], gallery_block_style( $post ), $label );
		}
	}
);

cr_photo_group(
	'the Stream card honors the photo Gallery style and preserves non-photo strips',
	static function (): void {
		$card = cr_booth_photo_card();

		$post = cr_booth_post( 201, array( cr_booth_gallery() ) );
		$html = booth_strip_card( $card, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_photo_contains( 'cr-booth__frames', $html, 'default photo print frames' );
		cr_photo_not_contains( 'cr-booth__footer', $html, 'default photo print has no footer' );

		$post = cr_booth_post( 202, array( cr_booth_gallery( 'is-style-cr-booth-print' ) ) );
		$html = booth_strip_card( $card, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_photo_contains( 'cr-booth__frames', $html, 'chosen photo print frames' );
		cr_photo_not_contains( 'cr-booth__footer', $html, 'chosen photo print has no footer' );

		$post = cr_booth_post( 203, array( cr_booth_gallery( 'is-style-lightbox-slideshow' ) ) );
		cr_photo_same( $card, booth_strip_card( $card, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'another chosen style leaves the photo card byte-identical' );

		$post = cr_booth_post( 204, array( cr_booth_gallery( 'is-style-cr-booth-print' ) ), array( 'note' ) );
		$note = '<article class="pk-card pk-card--stream k-note"><div class="pk-media pk-media--stream"><img src="lead.jpg" alt="" /></div><div class="pk-meta"><a href="https://example.test/photo/204/">More</a></div></article>';
		$html = booth_strip_card( $note, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_photo_contains( 'cr-booth__frames', $html, 'non-photo strip frames' );
		cr_photo_contains( 'cr-booth__footer', $html, 'non-photo strip keeps its footer' );
	}
);

cr_photo_group(
	'the Gallery block style and its token-only stylesheet are present',
	static function (): void {
		$php_path = dirname( __DIR__, 2 ) . '/inc/stream-gallery.php';
		$css_path = dirname( __DIR__, 2 ) . '/assets/css/cr-booth-print.css';
		$php      = (string) file_get_contents( $php_path );
		cr_photo_contains( "register_block_style(\n\t\t\t'core/gallery'", $php, 'core/gallery style registration' );
		cr_photo_contains( "'name'  => 'cr-booth-print'", $php, 'registered style slug' );
		cr_photo_assert( file_exists( $css_path ), 'booth print stylesheet exists' );
		$css = (string) file_get_contents( $css_path );
		foreach ( array( '.is-style-cr-booth-print', 'var(--cr-shadow-hard)', 'forced-colors' ) as $needle ) {
			cr_photo_contains( $needle, $css, 'stylesheet marker' );
		}
		cr_photo_assert( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b/', $css ), 'stylesheet has no hex color' );
	}
);

cr_photo_finish();
