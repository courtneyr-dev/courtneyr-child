<?php
/**
 * Post Kinds + video accessibility integration.
 *
 * Renders YouTube videos through Able Player instead of the raw oEmbed iframe,
 * so every video on the site is an accessible, keyboard-operable player with
 * captions and (when a WebVTT file is supplied) an interactive transcript.
 *
 * Three entry points, all sharing the same Able Player builder:
 *   1. Post Kinds watch/video CARD previews — via the plugin's
 *      `pkiw_card_embed_html` short-circuit filter (carries a caption file).
 *   2. Core Embed BLOCKS (wp:embed) in post content — via `render_block`.
 *   3. Bare-URL autoembeds — via `embed_oembed_html`.
 *
 * Everything falls back to the default embed whenever Able Player is inactive
 * or the URL isn't YouTube, so nothing breaks without Able Player.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\PostKinds;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build an Able Player embed for a YouTube video ID.
 *
 * @param string $youtube_id 11-character YouTube video ID.
 * @param string $captions   Optional WebVTT caption file (URL or attachment ID).
 * @return string Rendered Able Player markup.
 */
function ableplayer_youtube( string $youtube_id, string $captions = '' ): string {
	$captions_attr = ( '' !== $captions )
		? sprintf( ' captions="%s"', esc_attr( $captions ) )
		: '';

	return do_shortcode(
		sprintf(
			'[ableplayer youtube-id="%s" youtube-nocookie="true"%s]',
			esc_attr( $youtube_id ),
			$captions_attr
		)
	);
}

/**
 * Extract an 11-character YouTube video ID from common URL shapes
 * (watch?v=, youtu.be/, embed/, shorts/, live/).
 *
 * @param string $url The URL to parse.
 * @return string The video ID, or '' when the URL isn't a YouTube video.
 */
function youtube_id( string $url ): string {
	$pattern = '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

	if ( preg_match( $pattern, $url, $matches ) ) {
		return $matches[1];
	}

	return '';
}

/**
 * Substitute an Able Player embed for a watch/video card's YouTube URL.
 *
 * When the card supplies a WebVTT caption file (context['captions']), it's
 * passed to Able Player, which renders accessible captions AND an interactive
 * transcript from it — even for a YouTube video.
 *
 * @param string|null $pre     Replacement embed HTML, or null to use oEmbed.
 * @param mixed       $url     The URL being embedded.
 * @param string      $kind    The card kind slug.
 * @param array       $context Extra card data (e.g. 'captions' => VTT URL).
 * @return string|null Able Player markup, or the incoming value to fall back.
 */
function card_embed( $pre, $url, $kind, $context = array() ) {
	if ( null !== $pre ) {
		return $pre;
	}

	if ( ! is_string( $url ) || '' === $url ) {
		return $pre;
	}

	if ( ! in_array( $kind, array( 'watch', 'video' ), true ) ) {
		return $pre;
	}

	if ( ! shortcode_exists( 'ableplayer' ) ) {
		return $pre;
	}

	$youtube_id = youtube_id( $url );

	if ( '' === $youtube_id ) {
		return $pre;
	}

	$captions = ( isset( $context['captions'] ) && is_string( $context['captions'] ) )
		? $context['captions']
		: '';

	return ableplayer_youtube( $youtube_id, $captions );
}
add_filter( 'pkiw_card_embed_html', __NAMESPACE__ . '\\card_embed', 10, 4 );

/**
 * Render core Embed blocks (wp:embed) that point at YouTube through Able Player.
 *
 * Core embeds carry no caption file, so this gives the accessible player plus
 * YouTube's own captions. For a local VTT transcript, use the watch card.
 *
 * @param string $content The block's rendered HTML.
 * @param array  $block   The parsed block (blockName, attrs, …).
 * @return string Able Player markup for YouTube embeds, else the original HTML.
 */
function embed_block( $content, $block ) {
	if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== 'core/embed' ) {
		return $content;
	}

	if ( ! shortcode_exists( 'ableplayer' ) ) {
		return $content;
	}

	$url = $block['attrs']['url'] ?? '';

	if ( ! is_string( $url ) ) {
		return $content;
	}

	$youtube_id = youtube_id( $url );

	if ( '' === $youtube_id ) {
		return $content;
	}

	return ableplayer_youtube( $youtube_id );
}
add_filter( 'render_block', __NAMESPACE__ . '\\embed_block', 10, 2 );

/**
 * Render bare-URL YouTube autoembeds through Able Player.
 *
 * Fires when WordPress builds oEmbed HTML for a URL on the front end (e.g. a
 * YouTube link on its own line in content). Skipped in admin/REST so the block
 * editor keeps its normal, editable embed preview.
 *
 * @param string $html The oEmbed HTML.
 * @param string $url  The embedded URL.
 * @return string Able Player markup for YouTube, else the original HTML.
 */
function oembed_html( $html, $url ) {
	if ( is_admin() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
		return $html;
	}

	if ( ! shortcode_exists( 'ableplayer' ) || ! is_string( $url ) ) {
		return $html;
	}

	$youtube_id = youtube_id( $url );

	if ( '' === $youtube_id ) {
		return $html;
	}

	return ableplayer_youtube( $youtube_id );
}
add_filter( 'embed_oembed_html', __NAMESPACE__ . '\\oembed_html', 10, 2 );

/**
 * Drop the featured image from a photo post's single view.
 *
 * `single.html` renders `wp:post-featured-image`, and a photo posted from
 * Outpost already carries its picture in the body as a core/image block. Once
 * Outpost starts setting a featured image so the Stream polaroid has something
 * to show, that same picture would appear twice on the post itself: once above
 * the title and once in the entry. Suppressing it here keeps the single view
 * reading as one photo, and leaves the thumbnail free to do its real job in
 * cards, archives, and previews.
 *
 * Scoped to the `photo` kind. Other kinds keep their featured image, since for
 * them it is a lede rather than a duplicate of the body.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function suppress_featured_image_on_photo_single( string $html, array $block ): string {
	if ( 'core/post-featured-image' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	if ( ! is_singular( 'post' ) ) {
		return $html;
	}

	$post = get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	if ( ! has_term( 'photo', 'kind', $post ) ) {
		return $html;
	}

	return '';
}
add_filter( 'render_block', __NAMESPACE__ . '\\suppress_featured_image_on_photo_single', 10, 2 );
