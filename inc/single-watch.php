<?php
/**
 * Single watch: a film-journal page around a VHS cassette.
 *
 * A watch's single view used the generic cover-box single: the plugin's
 * card as a violet box with the poster on top. For posts with the `watch`
 * kind this file:
 *
 *   - puts the stored rating under the H1 as stars plus "N / 5" and hides
 *     the card's own stars;
 *   - adds a date · year · type lede under the H1;
 *   - relabels the card's kind label as the cassette flap, moves a long
 *     review out to "Notes from this watch", and adds a "Watch / find it"
 *     row of stored links (watchUrl labelled by its service, IMDb, TMDb);
 *   - wraps the card and what follows in the journal grid with three
 *     handwritten margin notes and a quiet watched / film meta row.
 *
 * The plugin's card, its embed (a YouTube or Vimeo watchUrl renders a
 * real player through the theme's Able Player filter, anything else shows
 * the poster) and its microformats are untouched. cr-post-kinds.css
 * paints the VHS under body.single-post.kind-watch.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleWatch;

use function Courtneyr\Child\Journal\aside;
use function Courtneyr\Child\Journal\margin_lines;
use function Courtneyr\Child\Journal\meta_item;
use function Courtneyr\Child\Journal\meta_row;
use function Courtneyr\Child\Journal\notes_section;
use function Courtneyr\Child\Journal\wrap;
use function Courtneyr\Child\Stamps\seed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A review longer than this leaves the cassette for the notes section.
 */
const REVIEW_INLINE_LIMIT = 140;

/**
 * Streaming services by host, with the role each plays in the row.
 *
 * @return array<string, array{0: string, 1: string}> host => [label, role].
 */
function services(): array {
	return array(
		'netflix.com'        => array( 'Netflix', 'watch' ),
		'hulu.com'           => array( 'Hulu', 'watch' ),
		'disneyplus.com'     => array( 'Disney+', 'watch' ),
		'max.com'            => array( 'Max', 'watch' ),
		'hbomax.com'         => array( 'Max', 'watch' ),
		'primevideo.com'     => array( 'Prime Video', 'watch' ),
		'amazon.com'         => array( 'Prime Video', 'watch' ),
		'tv.apple.com'       => array( 'Apple TV', 'watch' ),
		'peacocktv.com'      => array( 'Peacock', 'watch' ),
		'paramountplus.com'  => array( 'Paramount+', 'watch' ),
		'crunchyroll.com'    => array( 'Crunchyroll', 'watch' ),
		'kanopy.com'         => array( 'Kanopy', 'watch' ),
		'criterionchannel.com' => array( 'Criterion Channel', 'watch' ),
		'youtube.com'        => array( 'YouTube', 'trailer' ),
		'youtu.be'           => array( 'YouTube', 'trailer' ),
		'vimeo.com'          => array( 'Vimeo', 'trailer' ),
		'imdb.com'           => array( 'IMDb', 'details' ),
		'themoviedb.org'     => array( 'TMDb', 'details' ),
		'letterboxd.com'     => array( 'Letterboxd', 'details' ),
	);
}

/**
 * Label and role for a URL. Unknown hosts are a generic "Watch".
 *
 * @param string $url External URL.
 * @return array{0: string, 1: string}
 */
function classify( string $url ): array {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	foreach ( services() as $needle => $meta ) {
		if ( $host === $needle || str_ends_with( $host, '.' . $needle ) ) {
			return $meta;
		}
	}
	return array( __( 'Watch', 'courtneyr-child' ), 'watch' );
}

/**
 * Is this the single view of a watch?
 *
 * @return bool
 */
function is_watch_single(): bool {
	return \is_singular( 'post' ) && \has_term( 'watch', 'kind', \get_queried_object_id() );
}

/**
 * The watch-card block in a post body, or null.
 *
 * @param \WP_Post $post Post.
 * @return array<string, mixed>|null
 */
function find_watch_block( \WP_Post $post ): ?array {
	$found = null;
	$walk  = static function ( array $blocks ) use ( &$walk, &$found ): void {
		foreach ( $blocks as $block ) {
			if ( null !== $found ) {
				return;
			}
			if ( 'post-kinds-indieweb/watch-card' === ( $block['blockName'] ?? '' ) ) {
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
 * The factual line for a watch: year · type (· S1E2 for television).
 *
 * @param array<string, mixed> $attrs Block attributes.
 * @return string[] Parts, in order.
 */
function film_facts( array $attrs ): array {
	$parts = array();
	$year  = (int) ( $attrs['releaseYear'] ?? 0 );
	if ( $year > 0 ) {
		$parts[] = (string) $year;
	}
	$type = (string) ( $attrs['mediaType'] ?? 'movie' );
	if ( 'movie' === $type ) {
		$parts[] = __( 'Film', 'courtneyr-child' );
	} else {
		$season  = (int) ( $attrs['seasonNumber'] ?? 0 );
		$episode = (int) ( $attrs['episodeNumber'] ?? 0 );
		$parts[] = ( $season > 0 || $episode > 0 )
			? sprintf( 'S%dE%d', $season, $episode )
			: __( 'TV', 'courtneyr-child' );
	}
	$director = trim( (string) ( $attrs['director'] ?? '' ) );
	if ( '' !== $director ) {
		$parts[] = $director;
	}
	return $parts;
}

/**
 * Rating and lede under the H1.
 *
 * @param string               $html  Rendered post-title block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function add_title_lede( string $html, array $block ): string {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || ! is_watch_single() ) {
		return $html;
	}
	if ( ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__title' ) ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$watch = find_watch_block( $post );
	$attrs = (array) ( $watch['attrs'] ?? array() );

	$lede  = '<p class="cr-journal__lede">';
	$lede .= '<time class="cr-journal__lede-date" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
	$facts = film_facts( $attrs );
	if ( ! empty( $facts ) ) {
		$lede .= '<span class="cr-journal__lede-sep" aria-hidden="true"> · </span><span class="cr-journal__lede-place">' . esc_html( implode( ' · ', $facts ) ) . '</span>';
	}
	$lede .= '</p>';

	$rating = (int) ( $attrs['rating'] ?? 0 );
	if ( $rating > 0 ) {
		$star  = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3 6.5 7 .6-5.3 4.6 1.6 6.8L12 17l-6.9 3.5 1.6-6.8L1.4 9.1l7-.6z"/></svg>';
		$lede .= '<p class="cr-journal__rating" aria-label="' . esc_attr( sprintf( /* translators: %d: rating out of five. */ __( 'Rated %d of 5', 'courtneyr-child' ), $rating ) ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$lede .= '<span class="cr-journal__star' . ( $i <= $rating ? '' : ' cr-journal__star--off' ) . '">' . $star . '</span>';
		}
		$lede .= '<span class="cr-journal__rating-value" aria-hidden="true">' . esc_html( sprintf( '%d / 5', $rating ) ) . '</span></p>';
	}

	return $html . $lede;
}
add_filter( 'render_block', __NAMESPACE__ . '\\add_title_lede', 20, 2 );

/**
 * Rebuild the post body into the film-journal page.
 *
 * Splices at plugin markup emitted in a fixed order: `.pk-note.p-content`,
 * `<div class="pk-meta">`, `</article>`. A missing anchor leaves that part.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function journal_page( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_watch_single() ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$watch = find_watch_block( $post );
	if ( null === $watch || false === strpos( $html, 'k-watch' ) ) {
		return $html;
	}
	$attrs = (array) ( $watch['attrs'] ?? array() );
	$s     = seed( (string) $post->ID, (string) ( $attrs['imdbId'] ?? '' ), (string) ( $attrs['tmdbId'] ?? '' ), (string) ( $attrs['mediaTitle'] ?? '' ) );

	// 1. A long review leaves the cassette for the notes section.
	$note_html = '';
	$review    = trim( (string) ( $attrs['review'] ?? '' ) );
	if ( mb_strlen( wp_strip_all_tags( $review ) ) > REVIEW_INLINE_LIMIT ) {
		$n_open  = '<div class="pk-note p-content">';
		$n_start = strpos( $html, $n_open );
		if ( false !== $n_start ) {
			$n_end = strpos( $html, '</div>', $n_start );
			if ( false !== $n_end ) {
				$n_end    += 6;
				$note_html = substr( $html, $n_start, $n_end - $n_start );
				$html      = substr( $html, 0, $n_start ) . substr( $html, $n_end );
			}
		}
	}

	// 2. Watch / find it: only stored links, classified by service.
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
	if ( ! empty( $links ) ) {
		$row  = '<nav class="pk-sources pk-sources--watch" aria-label="' . esc_attr__( 'Watch or find it', 'courtneyr-child' ) . '">';
		$row .= '<p class="pk-sources__label">' . esc_html__( 'Watch / find it', 'courtneyr-child' ) . '</p><ul class="pk-sources__list">';
		foreach ( $links as $link ) {
			$row .= '<li><a class="pk-sources__link pk-sources__link--' . esc_attr( $link[2] ) . '" href="' . esc_url( $link[0] ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html( $link[1] )
				. '<span class="pk-sr-only"> ' . esc_html__( '(opens in a new tab)', 'courtneyr-child' ) . '</span>'
				. '<span class="pk-sources__arrow" aria-hidden="true">↗</span></a></li>';
		}
		$row .= '</ul></nav>';
		$meta = strpos( $html, '<div class="pk-meta">' );
		if ( false !== $meta ) {
			$html = substr( $html, 0, $meta ) . $row . substr( $html, $meta );
		}
	}

	// 3. The kind label becomes the flap marking.
	$label = '<p class="pk-kindlabel">';
	$lpos  = strpos( $html, $label );
	if ( false !== $lpos ) {
		$lend = strpos( $html, '</p>', $lpos );
		if ( false !== $lend ) {
			$html = substr( $html, 0, $lpos ) . $label . esc_html__( 'Watch · VHS', 'courtneyr-child' ) . substr( $html, $lend );
		}
	}

	// 4. Margin notes, notes section, meta row.
	$copy  = margin_lines( $s, 'film' );
	$after = aside( $copy[0], 1 );
	if ( '' !== $note_html ) {
		$after .= notes_section( __( 'Notes from this watch', 'courtneyr-child' ), $note_html );
		$after .= aside( $copy[1], 2, 'cr-hand--orange' );
	}
	$after .= aside( $copy[ '' !== $note_html ? 2 : 1 ], 3 );

	$ts = ! empty( $attrs['watchedAt'] ) ? (int) strtotime( (string) $attrs['watchedAt'] ) : 0;
	$ts = $ts > 0 ? $ts : (int) get_post_time( 'U', true, $post );

	$items   = array();
	$items[] = meta_item(
		'time',
		'<time datetime="' . esc_attr( (string) wp_date( 'c', $ts ) ) . '">' . esc_html( (string) wp_date( get_option( 'date_format' ), $ts ) ) . '<br>' . esc_html( (string) wp_date( get_option( 'time_format' ) . ' (T)', $ts ) ) . '</time>',
		__( 'Watched', 'courtneyr-child' )
	);
	$title = trim( (string) ( $attrs['mediaTitle'] ?? '' ) );
	if ( '' === $title ) {
		$title = get_the_title( $post );
	}
	$facts   = film_facts( $attrs );
	$items[] = meta_item(
		'film',
		esc_html( $title ) . ( ! empty( $facts ) ? '<br>' . esc_html( implode( ' · ', $facts ) ) : '' ),
		( 'movie' === ( $attrs['mediaType'] ?? 'movie' ) ) ? __( 'Film', 'courtneyr-child' ) : __( 'Series', 'courtneyr-child' )
	);
	$after .= meta_row( $items );

	$html = wrap( $html, $after );

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'k-watch' ) ) ) {
		$tags->add_class( 'cr-vhs' );
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\journal_page', 20, 2 );
