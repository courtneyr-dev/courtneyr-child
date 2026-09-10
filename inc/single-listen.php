<?php
/**
 * Single listen: rating value beside the stars, source links under the player.
 *
 * The listen-card block stores one rating (0–5) and up to two external
 * references: `listenUrl` (wherever the track was played) and
 * `musicbrainzId` (a MusicBrainz recording). On a listen's single view
 * this file prints the rating as a number next to the plugin's stars and
 * adds a "Listen / find it" row of real links directly under the player:
 * the listen URL labelled by its provider, and MusicBrainz when an id is
 * stored. Nothing is invented; a post with only a listen URL gets a row
 * with one link. cr-post-kinds.css paints the row as label-maker tape.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleListen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider labels by host. A host not listed falls back to "Listen".
 *
 * @return array<string, string>
 */
function providers(): array {
	return array(
		'open.spotify.com'  => 'Spotify',
		'spotify.com'       => 'Spotify',
		'music.apple.com'   => 'Apple Music',
		'bandcamp.com'      => 'Bandcamp',
		'soundcloud.com'    => 'SoundCloud',
		'youtube.com'       => 'YouTube',
		'www.youtube.com'   => 'YouTube',
		'youtu.be'          => 'YouTube',
		'music.youtube.com' => 'YouTube Music',
		'tidal.com'         => 'Tidal',
		'deezer.com'        => 'Deezer',
		'last.fm'           => 'Last.fm',
		'www.last.fm'       => 'Last.fm',
		'musicbrainz.org'   => 'MusicBrainz',
		'discogs.com'       => 'Discogs',
		'www.discogs.com'   => 'Discogs',
	);
}

/**
 * The label a URL gets in the sources row.
 *
 * @param string $url External URL.
 * @return string Provider name, or "Listen" when the host is not known.
 */
function provider_label( string $url ): string {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	foreach ( providers() as $needle => $label ) {
		if ( $host === $needle || str_ends_with( $host, '.' . $needle ) ) {
			return $label;
		}
	}
	return __( 'Listen', 'courtneyr-child' );
}

/**
 * Is this the single view of a listen?
 *
 * @return bool
 */
function is_listen_single(): bool {
	return \is_singular( 'post' ) && \has_term( 'listen', 'kind', \get_queried_object_id() );
}

/**
 * Add the rating value and the sources row to the listen card.
 *
 * Splices at markup the plugin always emits: the `.pk-stars` div (when a
 * rating exists) and `<div class="pk-meta">`. A missing anchor leaves that
 * part alone.
 *
 * @param string               $html  Rendered listen card.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function decorate_listen_card( string $html, array $block ): string {
	if ( ! is_listen_single() ) {
		return $html;
	}
	$attrs  = (array) ( $block['attrs'] ?? array() );
	$rating = (int) ( $attrs['rating'] ?? 0 );

	// Rating value inside the stars container, after the star glyphs.
	if ( $rating > 0 ) {
		$s_start = strpos( $html, '<div class="pk-stars' );
		if ( false !== $s_start ) {
			$s_end = strpos( $html, '</div>', $s_start );
			if ( false !== $s_end ) {
				$value = '<span class="pk-rating-value">' . esc_html( sprintf( '%d / 5', $rating ) ) . '</span>';
				$html  = substr( $html, 0, $s_end ) . $value . substr( $html, $s_end );
			}
		}
	}

	// Sources row: only stored links.
	$links = array();
	$url   = trim( (string) ( $attrs['listenUrl'] ?? '' ) );
	if ( '' !== $url ) {
		$links[] = array( $url, provider_label( $url ) );
	}
	$mbid = trim( (string) ( $attrs['musicbrainzId'] ?? '' ) );
	if ( '' !== $mbid ) {
		$links[] = array( 'https://musicbrainz.org/recording/' . rawurlencode( $mbid ), __( 'MusicBrainz', 'courtneyr-child' ) );
	}
	if ( empty( $links ) ) {
		return $html;
	}

	$row  = '<nav class="pk-sources" aria-label="' . esc_attr__( 'Listen or find it', 'courtneyr-child' ) . '">';
	$row .= '<p class="pk-sources__label">' . esc_html__( 'Listen / find it', 'courtneyr-child' ) . '</p>';
	$row .= '<ul class="pk-sources__list">';
	foreach ( $links as $link ) {
		$row .= '<li><a class="pk-sources__link" href="' . esc_url( $link[0] ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html( $link[1] )
			. '<span class="pk-sr-only"> ' . esc_html__( '(opens in a new tab)', 'courtneyr-child' ) . '</span>'
			. '<span class="pk-sources__arrow" aria-hidden="true">↗</span>'
			. '</a></li>';
	}
	$row .= '</ul></nav>';

	$meta = strpos( $html, '<div class="pk-meta">' );
	if ( false === $meta ) {
		return $html;
	}
	return substr( $html, 0, $meta ) . $row . substr( $html, $meta );
}
add_filter( 'render_block_post-kinds-indieweb/listen-card', __NAMESPACE__ . '\\decorate_listen_card', 10, 2 );
