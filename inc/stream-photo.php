<?php
/**
 * Stream photo cards as slide mounts (PKIW issue 241).
 *
 * The plugin prints one photo card whatever the post format: kind label,
 * title link, date, the lead image and its caption. This adapter keeps that
 * markup and adds what the paint needs: the mount class, one rotation key
 * from the post's stable seed, the failed-image mark on the media, and a
 * caption with no links, so the card stays one link and one tab stop.
 * inc/stream-gallery.php skips photo posts, so no booth strip replaces the
 * card. assets/css/cr-photo.css paints the mount.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamPhoto;

use Courtneyr\Child\Photo;
use function Courtneyr\Child\HomeSections\is_stream_surface;
use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\seed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rotation keys; cr-photo.css maps 0 to 3 to the four rotation tokens.
 */
const ROTATIONS = 4;

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
 * Dress a photo post's stream card as a slide mount.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed stream-card block.
 * @param mixed                $instance Block instance with the Query Loop's postId.
 * @return string
 */
function slide_mount_card( string $html, array $block, $instance = null ): string {
	if ( '' === $html || ! is_stream_surface() ) {
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
	$tags->add_class( 'cr-slide-mount' );
	$tags->set_attribute( 'data-cr-rotate', (string) pick( seed( 'photo-mount', (string) $post->ID ), 3, ROTATIONS ) );
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
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\slide_mount_card', 10, 3 );
