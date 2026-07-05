<?php
/**
 * Post Kinds integration.
 *
 * Renders watch/video Post Kinds card previews through Able Player instead of
 * the raw YouTube oEmbed iframe. Able Player gives an accessible, keyboard-
 * operable player and pulls YouTube's own captions into both closed captions
 * and an interactive transcript — matching how videos are embedded elsewhere
 * on the site.
 *
 * Hooks the plugin's `pkiw_card_embed_html` short-circuit filter. Falls back to
 * the default oEmbed whenever Able Player is inactive or the URL isn't YouTube,
 * so nothing breaks if the plugin is present without Able Player.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\PostKinds;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
function ableplayer_video_embed( $pre, $url, $kind, $context = array() ) {
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

	$captions = '';
	if ( isset( $context['captions'] ) && is_string( $context['captions'] ) && '' !== $context['captions'] ) {
		$captions = sprintf( ' captions="%s"', esc_attr( $context['captions'] ) );
	}

	return do_shortcode(
		sprintf(
			'[ableplayer youtube-id="%s" youtube-nocookie="true"%s]',
			esc_attr( $youtube_id ),
			$captions
		)
	);
}
add_filter( 'pkiw_card_embed_html', __NAMESPACE__ . '\\ableplayer_video_embed', 10, 4 );

/**
 * Extract an 11-character YouTube video ID from common URL shapes
 * (watch?v=, youtu.be/, embed/, shorts/).
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
