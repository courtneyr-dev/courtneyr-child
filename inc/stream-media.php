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
use function Courtneyr\Child\SingleWatch\film_facts;
use function Courtneyr\Child\SingleWatch\vhs_label;
use function Courtneyr\Child\SingleWatch\vhs_mechanics;
use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\seed;

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
	if ( '' !== $url && '' !== (string) wp_parse_url( $url, PHP_URL_HOST ) && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
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
	if ( '' !== $url && '' !== (string) wp_parse_url( $url, PHP_URL_HOST ) && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
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
 * The compact cassette's mechanics SVG for a listen card: hubs, a run of
 * tape and a clear window. (A watch card draws its reels and transport
 * with the spans from inc/single-watch.php, shared with the single.)
 *
 * @param int $seed Fixes which reel carries the tape.
 * @return string
 */
function mech_svg( int $seed = 0 ): string {
	// A compact cassette part-way through: the tape mass sits mostly on
	// one reel (which one is fixed per post by the seed), the other reel
	// is nearly bare, a run of tape crosses between them, and the window
	// is clear enough to see the hubs and a glass sheen.
	$heavy_left = 0 === pick( $seed, 5, 2 );
	$hub        = static function ( float $cx, float $mass ): string {
		$rings = '';
		for ( $r = $mass - 1.2; $r > 4.2; $r -= 1.1 ) {
			$rings .= '<circle cx="' . $cx . '" cy="9" r="' . $r . '" fill="none" stroke="#2b2620" stroke-width="0.35"/>';
		}
		return '<circle cx="' . $cx . '" cy="9" r="' . $mass . '" fill="#1d1913"/>' . $rings
			. '<circle cx="' . $cx . '" cy="9" r="3.6" fill="none" stroke="#ece8dd" stroke-width="1.3" stroke-dasharray="1 1.1"/>'
			. '<circle cx="' . $cx . '" cy="9" r="2.6" fill="#ece8dd"/>'
			. '<circle cx="' . $cx . '" cy="9" r="0.9" fill="#17140f"/>';
	};
	$lm         = $heavy_left ? 7.2 : 4.1;
	$rm         = $heavy_left ? 4.1 : 7.2;
	return '<svg class="cr-media__mech" viewBox="0 0 100 18" aria-hidden="true" focusable="false">'
		. '<rect x="21" y="1.2" width="58" height="15.6" rx="1.6" fill="#2a2620" stroke="#0d0b09" stroke-width="0.6"/>'
		. '<rect x="34" y="7.6" width="32" height="2.8" fill="#100e0b"/>'
		. '<path d="M' . ( 34 + $lm ) . ' ' . ( 9 - $lm + 1.2 ) . 'L' . ( 66 - $rm ) . ' ' . ( 9 - $rm + 1.2 ) . '" stroke="#100e0b" stroke-width="1.1" fill="none"/>'
		. $hub( 34, $lm ) . $hub( 66, $rm )
		. '<path d="M23 3.2Q50 -0.5 77 3.2L76 6.4Q50 3.4 24 6.4Z" fill="#fff" opacity="0.1"/>'
		. '<rect x="21" y="1.2" width="58" height="15.6" rx="1.6" fill="none" stroke="rgba(255,255,255,0.14)" stroke-width="0.5"/>'
		. '</svg>';
}

/**
 * Remove one element from the card's HTML: the block that starts with
 * `$open` up to its matching `</div>`, counting nested divs. Returns the
 * HTML unchanged when the opening tag is absent or unbalanced.
 *
 * @param string $html Card HTML.
 * @param string $open Exact opening tag of a <div>.
 * @return string
 */
function cut_div( string $html, string $open ): string {
	$start = strpos( $html, $open );
	if ( false === $start ) {
		return $html;
	}
	$depth = 0;
	$pos   = $start;
	while ( preg_match( '/<div\b|<\/div>/', $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
		$pos    = (int) $m[0][1] + strlen( $m[0][0] );
		$depth += '<div' === substr( $m[0][0], 0, 4 ) ? 1 : -1;
		if ( 0 === $depth ) {
			return substr( $html, 0, $start ) . substr( $html, $pos );
		}
	}
	return $html;
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

	// A watch card is "playable" when the plugin rendered a real player
	// (a <video> for Able Player, or a provider iframe) in its video
	// embed, as opposed to a poster only. The class lets the stream give
	// that card two columns.
	$playable = false;
	if ( 'watch' === $kind ) {
		$e_open  = '<div class="pk-embed pk-embed--video">';
		$e_start = strpos( $html, $e_open );
		if ( false !== $e_start ) {
			$e_end    = strpos( $html, '<div class="pk-meta">', $e_start );
			$embed    = substr( $html, $e_start, false === $e_end ? null : $e_end - $e_start );
			$playable = false !== strpos( $embed, '<video' ) || false !== strpos( $embed, '<iframe' );
		}
		/**
		 * Whether a /stream watch card embeds its player. Off: the stream
		 * shows every watch as a poster card and playing happens on the
		 * single (Courtney, 2026-09-10). The deck layout in
		 * cr-post-kinds.css (v0.7.30) is ready if this is ever turned on.
		 *
		 * @param bool     $allow Default false.
		 * @param \WP_Post $post  The post.
		 */
		if ( $playable && ! apply_filters( 'courtneyr_child_stream_watch_player', false, $post ) ) {
			$html     = cut_div( $html, $e_open );
			$playable = false;
		}
		// The print slot shows the post's featured image when it has one
		// (in place of the plugin's poster art), as the generic stream card
		// did; a post without one keeps whatever the plugin rendered.
		if ( has_post_thumbnail( $post ) ) {
			$thumb = '<div class="pk-media pk-media--stream">' . get_the_post_thumbnail( $post, 'medium_large', array( 'class' => 'u-photo', 'loading' => 'lazy' ) ) . '</div>';
			$m_pos = strpos( $html, '<div class="pk-media' );
			if ( false !== $m_pos ) {
				$m_open = substr( $html, $m_pos, strpos( $html, '>', $m_pos ) - $m_pos + 1 );
				$html   = cut_div( $html, $m_open );
				$html   = substr( $html, 0, $m_pos ) . $thumb . substr( $html, $m_pos );
			} else {
				$meta = strpos( $html, '<div class="pk-meta">' );
				$html = false === $meta ? $html : substr( $html, 0, $meta ) . $thumb . substr( $html, $meta );
			}
		}
	}

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

	// 3. The kind label is the object's printed marking; the mechanics
	//    follow it in the DOM so they paint under everything else.
	$label = '<p class="pk-kindlabel">';
	$lpos  = strpos( $html, $label );
	if ( false !== $lpos ) {
		$lend = strpos( $html, '</p>', $lpos );
		if ( false !== $lend ) {
			$text = 'watch' === $kind ? __( 'Watch · VHS', 'courtneyr-child' ) : __( 'Listen · Cassette', 'courtneyr-child' );
			// A VHS draws the same reels, transport and handwritten label as
			// the single (inc/single-watch.php); a playable card has room for
			// the stored facts on its label, a poster card does not.
			$mech = 'watch' === $kind
				? vhs_mechanics() . vhs_label( $playable ? film_facts( $attrs ) : array() )
				: mech_svg( seed( (string) $post->ID, (string) ( $attrs['listenUrl'] ?? '' ) ) );
			$html = substr( $html, 0, $lpos ) . $label . esc_html( $text ) . '</p>' . $mech . substr( $html, $lend + 4 );
		}
	}

	// 4. The date leaves the label for the tail under the object. A card
	//    the plugin could not date (it had no title to hang one on) gets
	//    the post date here.
	$date_html = '';
	if ( preg_match( '/<p class="pk-sub pk-stream-date">.*?<\/p>/s', $html, $dm, PREG_OFFSET_CAPTURE ) ) {
		$date_html = $dm[0][0];
		$html      = substr( $html, 0, $dm[0][1] ) . substr( $html, $dm[0][1] + strlen( $date_html ) );
	} elseif ( false === strpos( $html, 'dt-published' ) ) {
		$date_html = '<p class="pk-sub pk-stream-date"><time class="dt-published" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></p>';
	}

	// 5. Sources row from stored links only, then the date, ahead of the
	//    plugin's meta.
	$row  = sources_row( 'watch' === $kind ? watch_links( $attrs ) : listen_links( $attrs ), $kind );
	$meta = strpos( $html, '<div class="pk-meta">' );
	if ( false !== $meta ) {
		$html = substr( $html, 0, $meta ) . $row . $date_html . substr( $html, $meta );
	} elseif ( '' !== $date_html ) {
		$close = strrpos( $html, '</article>' );
		$html  = false === $close ? $html . $date_html : substr( $html, 0, $close ) . $date_html . substr( $html, $close );
	}

	$html = \Courtneyr\Child\Journal\skip_lazy_iframes( $html );
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'pk-card--stream' );
		$tags->add_class( 'watch' === $kind ? 'cr-vhs' : 'cr-cassette' );
		$tags->add_class( 'watch' === $kind ? 'cr-vhs--stream' : 'cr-cassette--stream' );
		if ( $playable ) {
			$tags->add_class( 'has-video-player' );
		}
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\media_card', 10, 3 );
