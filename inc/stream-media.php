<?php
/**
 * Stream watch and listen cards as physical media.
 *
 * On /stream/ a watch post is a VHS and a listen post is a compact
 * cassette. The plugin's own card markup stays (title, date, stars, the
 * real player or poster); this file only adds what the object needs:
 * the rating as a number beside the stars, the post title when the card
 * printed none, the kind label as the object's printed marking, and a
 * compact "Watch / find it" or "Listen / find it" row built from stored
 * links. cr-post-kinds.css paints the shells, keyed on the classes
 * added here. Nothing here touches other kinds.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamMedia;

use function Courtneyr\Child\SingleListen\provider_label;
use function Courtneyr\Child\SingleWatch\classify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The first block of a name anywhere in a post's content.
 *
 * @param \WP_Post $post Post.
 * @param string   $name Block name, e.g. 'post-kinds-indieweb/listen-card'.
 * @return array<string, mixed>|null
 */
function find_block( \WP_Post $post, string $name ): ?array {
	$found = null;
	$walk  = static function ( array $blocks ) use ( &$walk, &$found, $name ): void {
		foreach ( $blocks as $block ) {
			if ( null !== $found ) {
				return;
			}
			if ( $name === ( $block['blockName'] ?? '' ) ) {
				$found = $block;
				return;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ) );
	return $found;
}

/**
 * Links for a watch: the watch URL classified by service, IMDb, TMDb.
 *
 * @param array<string, mixed> $attrs Watch-card attributes.
 * @return array<int, array{0: string, 1: string, 2: string}> url, label, variant.
 */
function watch_links( array $attrs ): array {
	$links = array();
	$url   = trim( (string) ( $attrs['watchUrl'] ?? '' ) );
	if ( '' !== $url && false !== wp_parse_url( $url, PHP_URL_HOST ) ) {
		$links[] = array_merge( array( $url ), classify( $url ) );
	}
	$imdb = trim( (string) ( $attrs['imdbId'] ?? '' ) );
	if ( '' !== $imdb ) {
		$links[] = array( 'https://www.imdb.com/title/' . rawurlencode( $imdb ), 'IMDb', 'details' );
	}
	$tmdb = trim( (string) ( $attrs['tmdbId'] ?? '' ) );
	if ( '' !== $tmdb ) {
		$type    = ( 'movie' === ( $attrs['mediaType'] ?? 'movie' ) ) ? 'movie' : 'tv';
		$links[] = array( 'https://www.themoviedb.org/' . $type . '/' . rawurlencode( $tmdb ), 'TMDb', 'details' );
	}
	return $links;
}

/**
 * Links for a listen: the listen URL labelled by provider, MusicBrainz.
 *
 * @param array<string, mixed> $attrs Listen-card attributes.
 * @return array<int, array{0: string, 1: string, 2: string}>
 */
function listen_links( array $attrs ): array {
	$links = array();
	$url   = trim( (string) ( $attrs['listenUrl'] ?? '' ) );
	if ( '' !== $url && false !== wp_parse_url( $url, PHP_URL_HOST ) ) {
		$links[] = array( $url, provider_label( $url ), 'listen' );
	}
	$mbid = trim( (string) ( $attrs['musicbrainzId'] ?? '' ) );
	if ( '' !== $mbid ) {
		$links[] = array( 'https://musicbrainz.org/recording/' . rawurlencode( $mbid ), 'MusicBrainz', 'details' );
	}
	return $links;
}

/**
 * The compact sources row.
 *
 * @param array<int, array{0: string, 1: string, 2: string}> $links Links.
 * @param string                                              $kind  'watch' or 'listen'.
 * @return string Empty when there are no links.
 */
function sources_row( array $links, string $kind ): string {
	if ( empty( $links ) ) {
		return '';
	}
	$label = 'watch' === $kind ? __( 'Watch / find it', 'courtneyr-child' ) : __( 'Listen / find it', 'courtneyr-child' );
	$aria  = 'watch' === $kind ? __( 'Watch or find it', 'courtneyr-child' ) : __( 'Listen or find it', 'courtneyr-child' );
	$row   = '<nav class="pk-sources pk-sources--' . esc_attr( $kind ) . ' pk-sources--stream" aria-label="' . esc_attr( $aria ) . '">';
	$row  .= '<p class="pk-sources__label">' . esc_html( $label ) . '</p><ul class="pk-sources__list">';
	foreach ( $links as $link ) {
		$row .= '<li><a class="pk-sources__link pk-sources__link--' . esc_attr( $link[2] ) . '" href="' . esc_url( $link[0] ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html( $link[1] )
			. '<span class="pk-sr-only"> ' . esc_html__( '(opens in a new tab)', 'courtneyr-child' ) . '</span>'
			. '<span class="pk-sources__arrow" aria-hidden="true">↗</span></a></li>';
	}
	return $row . '</ul></nav>';
}

/**
 * Dress a watch or listen stream card as its object.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block (unused).
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function media_card( string $html, array $block, $instance ): string {
	if ( ! is_page( 'stream' ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}

	$kind = has_term( 'watch', 'kind', $post ) ? 'watch' : ( has_term( 'listen', 'kind', $post ) ? 'listen' : '' );
	if ( '' === $kind || false === strpos( $html, 'pk-card k-' . $kind ) ) {
		return $html;
	}

	$card  = find_block( $post, 'post-kinds-indieweb/' . $kind . '-card' );
	$attrs = (array) ( $card['attrs'] ?? array() );

	// 1. A card without a title gets the post's, linked, in the caption.
	if ( false === strpos( $html, 'pk-title' ) ) {
		$cap = strpos( $html, '<div class="pk-caption">' );
		if ( false !== $cap ) {
			$cap  += 24;
			$title = '<h2 class="pk-title p-name"><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2>';
			$html  = substr( $html, 0, $cap ) . $title . substr( $html, $cap );
		}
	}

	// 2. The rating as a number, read back from the plugin's own stars.
	if ( preg_match( '/<div class="pk-stars[^"]*" aria-label="[^"]*?(\d)[^"]*"/', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$s_end = strpos( $html, '</div>', (int) $m[0][1] );
		if ( false !== $s_end ) {
			$value = '<span class="pk-rating-value">' . esc_html( sprintf( '%d / 5', (int) $m[1][0] ) ) . '</span>';
			$html  = substr( $html, 0, $s_end ) . $value . substr( $html, $s_end );
		}
	}

	// 3. The kind label is the object's printed marking.
	$label = '<p class="pk-kindlabel">';
	$lpos  = strpos( $html, $label );
	if ( false !== $lpos ) {
		$lend = strpos( $html, '</p>', $lpos );
		if ( false !== $lend ) {
			$text = 'watch' === $kind ? __( 'Watch · VHS', 'courtneyr-child' ) : __( 'Listen · Cassette', 'courtneyr-child' );
			$html = substr( $html, 0, $lpos ) . $label . esc_html( $text ) . substr( $html, $lend );
		}
	}

	// 4. Sources row from stored links only, ahead of the plugin's meta.
	$row = sources_row( 'watch' === $kind ? watch_links( $attrs ) : listen_links( $attrs ), $kind );
	if ( '' !== $row ) {
		$meta = strpos( $html, '<div class="pk-meta">' );
		if ( false !== $meta ) {
			$html = substr( $html, 0, $meta ) . $row . substr( $html, $meta );
		}
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'pk-card--stream' );
		$tags->add_class( 'watch' === $kind ? 'cr-vhs' : 'cr-cassette' );
		$tags->add_class( 'watch' === $kind ? 'cr-vhs--stream' : 'cr-cassette--stream' );
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\media_card', 10, 3 );
