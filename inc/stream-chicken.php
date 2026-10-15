<?php
/**
 * Stream chicken cards: a single egg (PKIW issue 250).
 *
 * The plugin prints the generic Stream card for a chicken post. This adapter
 * keeps its article root, kind label, date and title link, and the featured
 * image when the plugin says the post's picture is the featured image. It cuts
 * everything else: the badge, the excerpt, "Read more", the hero caption and
 * the thumbnail row. The title link is the card's one link, and cr-chicken.css
 * stretches it over the whole egg.
 *
 * It runs at priority 9, ahead of the priority 10 booth-strip adapter in
 * inc/stream-gallery.php. The egg has no pk-media wrapper, so that adapter
 * finds nothing to rebuild and leaves the egg alone.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamChicken;

use Courtneyr\Child\Chicken;
use function Courtneyr\Child\StreamRead\take_element;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The first element of a tag family that carries a class token, with its contents.
 *
 * @param string $html       Fragment.
 * @param string $pattern    Tag name pattern, such as 'h[2-4]' or 'span'.
 * @param string $class_name One class the element carries.
 * @return string The element, or '' when there is none.
 */
function pull_element( string $html, string $pattern, string $class_name ): string {
	$regex = '/<(' . $pattern . ')\b[^>]*\bclass="[^"]*(?<![\w-])' . preg_quote( $class_name, '/' ) . '(?![\w-])[^"]*"[^>]*>.*?<\/\1>/s';
	return 1 === preg_match( $regex, $html, $found ) ? $found[0] : '';
}

/**
 * The picture a card's media wrapper holds: a picture element, else the image.
 *
 * Modern Image Formats wraps the image in a picture element on dev.
 *
 * @param string $media The pk-media element.
 * @return string
 */
function pull_picture( string $media ): string {
	if ( 1 === preg_match( '/<picture\b.*?<\/picture>/s', $media, $found ) ) {
		return $found[0];
	}
	return 1 === preg_match( '/<img\b[^>]*>/', $media, $found ) ? $found[0] : '';
}

/**
 * Set a chicken Stream card out as an egg.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block (unused).
 * @param mixed                $instance Block instance with the Query Loop's postId.
 * @return string
 */
function egg_card( string $html, array $block = array(), $instance = null ): string {
	unset( $block );

	if ( '' === $html || ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( Chicken\KIND, 'kind', $post ) ) {
		return $html;
	}

	// Only the generic card and the protected card carry this root; a card block the author wrote stays as the plugin printed it.
	if ( 1 !== preg_match( '/^\s*<article\b[^>]*\bclass="([^"]*)"[^>]*>/i', $html, $root ) ) {
		return $html;
	}
	$classes = preg_split( '/\s+/', trim( $root[1] ) );
	$classes = is_array( $classes ) ? $classes : array();
	if ( ! in_array( 'pk-card--stream', $classes, true ) || ! in_array( 'k-chicken', $classes, true ) ) {
		return $html;
	}

	$heading = pull_element( $html, 'h[2-4]', 'pk-title' );
	if ( '' === $heading ) {
		return $html;
	}

	// A protected card is its title link and nothing else.
	if ( in_array( 'pk-card--protected', $classes, true ) ) {
		return '<article class="pk-card pk-card--stream pk-card--protected k-chicken h-entry cr-egg-card">' . $heading . '</article>';
	}

	$work  = $html;
	$stamp = take_element( $work, 'p', 'pk-stream-date' );
	$time  = 1 === preg_match( '/<time\b.*?<\/time>/s', $stamp, $found ) ? $found[0] : '';
	if ( '' === $time ) {
		// The date carries the card's dt-published; without it the card stays as printed.
		return $html;
	}
	$label = pull_element( $work, 'span', 'pk-kindlabel' );

	// X16: the theme reads the plugin only through its facts. The picture is the featured image or nothing, with no first-image fallback.
	$facts   = function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $post->ID ) : array();
	$picture = '';
	if ( 'featured' === ( $facts['picture']['source'] ?? '' ) ) {
		$picture = pull_picture( take_element( $work, 'div', 'pk-media' ) );
	}

	return '<article class="pk-card pk-card--stream k-chicken h-entry cr-egg-card">'
		. ( '' !== $picture ? '<figure class="cr-egg-card__photo" data-cr-cover-fallback="true">' . $picture . '</figure>' : '' )
		. $label
		. '<p class="cr-egg-card__date">' . $time . '</p>'
		. $heading
		. '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\egg_card', 9, 3 );
