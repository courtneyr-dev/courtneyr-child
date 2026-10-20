<?php
/**
 * Stream photo cards as polaroids.
 *
 * The plugin prints one photo card whatever the post format: kind label,
 * title link, date, the lead image and its caption. A gallery-format post
 * becomes a booth strip first (inc/stream-gallery.php, priority 10); every
 * other photo card is dressed here as a polaroid: the class the paint keys
 * on, the failed-image mark on the media, and a caption with no links, so
 * the card stays one link and one tab stop. The collage's own column rules
 * rotate it. assets/css/cr-photo.css paints the polaroid.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamPhoto;

use Courtneyr\Child\Photo;
use function Courtneyr\Child\HomeSections\is_stream_surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove link tags from the caption and keep their text.
 *
 * @param string $html Card HTML.
 * @return string
 */
function strip_caption_links( string $html ): string {
	return (string) preg_replace_callback(
		'/(<p\b[^>]*\bclass="[^"]*(?<![\w-])pk-media__caption(?![\w-])[^"]*"[^>]*>)(.*?)(<\/p>)/is',
		static fn( array $found ): string => $found[1] . preg_replace( '/<\/?a\b[^>]*>/i', '', $found[2] ) . $found[3],
		$html
	);
}

/**
 * Dress a photo post's stream card as a polaroid.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed stream-card block.
 * @param mixed                $instance Block instance with the Query Loop's postId.
 * @return string
 */
function polaroid_card( string $html, array $block, $instance = null ): string {
	// A booth strip already replaced this card's picture.
	if ( '' === $html || ! is_stream_surface() || false !== strpos( $html, 'cr-booth' ) ) {
		return $html;
	}
	if ( false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), Photo\FRAME_STYLE ) ) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( Photo\KIND, 'kind', $post ) || post_password_required( $post ) ) {
		return $html;
	}

	if (
		! preg_match( '/^\s*<article\b[^>]*\bclass="[^"]*(?<![\w-])pk-card(?![\w-])[^"]*"[^>]*>/i', $html, $root )
		|| ! Photo\has_class_token( $root[0], 'k-photo' )
		|| Photo\has_class_token( $root[0], 'pk-card--protected' )
	) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	$tags->add_class( 'cr-polaroid' );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'div',
			'class_name' => 'pk-media--stream',
		)
	) ) {
		$tags->set_attribute( 'data-cr-cover-fallback', true );
	}

	return strip_caption_links( $tags->get_updated_html() );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\polaroid_card', 11, 3 );

/**
 * Give a titled Photo card's image its alt text.
 *
 * The plugin's photo card prints the lead image with an empty alt when a
 * title names the link. On a Photo post the image is the content, so the
 * card now carries the alt the plugin already resolves (the image block's,
 * else the attachment's) beside the title link (decision 2026-10-20). An image
 * without alt text keeps the empty alt. Runs on every surface: the Stream,
 * the front page and the format archives print the same card.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed stream-card block.
 * @param mixed                $instance Block instance with the Query Loop's postId.
 * @return string
 */
function restore_photo_alt( string $html, array $block, $instance = null ): string {
	unset( $block );
	if ( '' === $html || ! function_exists( '\PKIW\lead_image' ) || false === strpos( $html, 'pk-media--stream' ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( Photo\KIND, 'kind', $post ) || post_password_required( $post ) ) {
		return $html;
	}
	$lead = \PKIW\lead_image( $post->ID );
	$alt  = trim( (string) ( $lead['alt'] ?? '' ) );
	if ( '' === $alt ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( array( 'class_name' => 'pk-media--stream' ) ) ) {
		return $html;
	}
	while ( $tags->next_tag( 'img' ) ) {
		if ( ! $tags->has_class( 'u-photo' ) ) {
			continue;
		}
		if ( '' === trim( (string) $tags->get_attribute( 'alt' ) ) ) {
			$tags->set_attribute( 'alt', $alt );
		}
		break;
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\restore_photo_alt', 20, 3 );
