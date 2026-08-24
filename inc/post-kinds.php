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
 * Surface the mood emoji on the Stream's generic card.
 *
 * A long-form mood post (a mood-card block plus real paragraphs) falls
 * through to the plugin's generic stream card, which never reads the
 * mood-card's attributes — the emoji vanishes from the Stream. Pull the
 * emoji off the first mood-card block in the post body and drop it in
 * front of the card's caption so cr-post-kinds.css can paint it as the
 * enamel pin.
 *
 * @param string         $content  Rendered stream-card HTML.
 * @param array          $block    Parsed block (unused).
 * @param \WP_Block|null $instance Block instance, carries the loop post context.
 * @return string Card HTML with the mood emoji injected.
 */
function stream_card_mood_emoji( $content, $block, $instance = null ) {
	if ( ! is_string( $content ) || '' === $content || str_contains( $content, 'pk-mood__emoji' ) ) {
		return $content;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: 0;
	$post    = $post_id ? get_post( $post_id ) : get_post();

	if ( ! $post instanceof \WP_Post || ! has_term( 'mood', 'kind', $post ) ) {
		return $content;
	}

	$emoji = mood_card_emoji( (string) $post->post_content );
	if ( '' === $emoji ) {
		return $content;
	}

	$needle = '<div class="pk-caption">';
	$pos    = strpos( $content, $needle );
	if ( false === $pos ) {
		return $content;
	}

	return substr( $content, 0, $pos )
		. '<span class="pk-mood__emoji" aria-hidden="true">' . esc_html( $emoji ) . '</span>'
		. substr( $content, $pos );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stream_card_mood_emoji', 10, 3 );

/**
 * The emoji attribute of the first mood-card block in a post body.
 *
 * Mirrors the mood-card block's own default ('😊') when the block is
 * present without an explicit emoji; a body with no mood-card block
 * yields '' so the card renders without a pin rather than inventing one.
 *
 * @param string $post_content Raw post content.
 * @return string The emoji, or '' when the body has no mood-card block.
 */
function mood_card_emoji( string $post_content ): string {
	if ( ! str_contains( $post_content, 'post-kinds-indieweb/mood-card' ) ) {
		return '';
	}

	$stack = parse_blocks( $post_content );
	while ( $stack ) {
		$block = array_shift( $stack );

		if ( 'post-kinds-indieweb/mood-card' === ( $block['blockName'] ?? '' ) ) {
			$emoji = trim( (string) ( $block['attrs']['emoji'] ?? '' ) );
			return '' !== $emoji ? $emoji : '😊';
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			$stack = array_merge( $stack, $block['innerBlocks'] );
		}
	}

	return '';
}
