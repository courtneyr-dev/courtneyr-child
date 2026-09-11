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
use function Courtneyr\Child\Stamps\pick;
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
	$attrs = \Courtneyr\Child\Journal\card_attrs( $post, 'post-kinds-indieweb/watch-card', (array) ( $watch['attrs'] ?? array() ) );

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
 * The cassette's moving parts: two reel windows and the transport row as
 * empty decorative spans. cr-post-kinds.css paints them in container
 * units, so the single Watch shell and the /stream card draw one cassette
 * at two sizes.
 *
 * @return string
 */
function vhs_mechanics( bool $heavy_left = true ): string {
	// A tape part-way through: one reel carries the pack, the other is
	// nearly bare (a real VHS never shows both reels full).
	$l = $heavy_left ? 'full' : 'bare';
	$r = $heavy_left ? 'bare' : 'full';
	return '<span class="cr-vhs__reel cr-vhs__reel--l cr-vhs__reel--' . $l . '" aria-hidden="true"></span>'
		. '<span class="cr-vhs__reel cr-vhs__reel--r cr-vhs__reel--' . $r . '" aria-hidden="true"></span>'
		. '<span class="cr-vhs__tape" aria-hidden="true"></span>';
}

/**
 * Which reel carries the tape, fixed per post so the /stream card and
 * the single agree.
 *
 * @param \WP_Post $post Post.
 * @return bool True when the left reel is the full one.
 */
function heavy_left( \WP_Post $post ): bool {
	return 0 === pick( seed( (string) $post->ID ), 7, 2 );
}

/**
 * The handwritten VHS label: stock marks, the Rock Salt note, a colour
 * stripe, then any facts. Shared by the single and the /stream card.
 *
 * The stock marks are plainly decorative (a blank tape's T-120 length
 * grade and SP speed panel), never data. No "Side A": a VHS has one
 * side; only audio cassettes flip.
 *
 * @param string[] $facts Real stored facts, plain text; may be empty.
 * @return string
 */
function vhs_label( array $facts = array() ): string {
	$out  = '<div class="cr-vhs__label">';
	$out .= '<span class="cr-vhs__marks" aria-hidden="true"><span>T-120</span><span>SP</span></span>';
	$out .= '<p class="cr-vhs__note cr-hand" aria-hidden="true">' . esc_html__( 'Be kind & rewind', 'courtneyr-child' ) . '</p>';
	$out .= '<span class="cr-vhs__stripe" aria-hidden="true"></span>';
	if ( ! empty( $facts ) ) {
		$out .= '<ul class="cr-vhs__facts">';
		foreach ( $facts as $fact ) {
			$out .= '<li>' . esc_html( $fact ) . '</li>';
		}
		$out .= '</ul>';
	}
	return $out . '</div>';
}

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
	$attrs = \Courtneyr\Child\Journal\card_attrs( $post, 'post-kinds-indieweb/watch-card', (array) ( $watch['attrs'] ?? array() ) );
	$s     = seed( (string) $post->ID, (string) ( $attrs['imdbId'] ?? '' ), (string) ( $attrs['tmdbId'] ?? '' ), (string) ( $attrs['mediaTitle'] ?? '' ) );

	// 1. The review always leaves the cassette for the notes section: the
	//    label zone is for the tape's own facts.
	$note_html = '';
	$n_open    = '<div class="pk-note p-content">';
	$n_start   = strpos( $html, $n_open );
	if ( false !== $n_start ) {
		$n_end = strpos( $html, '</div>', $n_start );
		if ( false !== $n_end ) {
			$n_end    += 6;
			$note_html = substr( $html, $n_start, $n_end - $n_start );
			$html      = substr( $html, 0, $n_start ) . substr( $html, $n_end );
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
	}

	// The plugin's meta line (watched time) leaves the shell too; the row
	// and the meta sit on the page under the cassette.
	$plugin_meta = '';
	$m_start     = strpos( $html, '<div class="pk-meta">' );
	if ( false !== $m_start ) {
		$m_end = strpos( $html, '</div>', $m_start );
		if ( false !== $m_end ) {
			$m_end       += 6;
			$plugin_meta  = substr( $html, $m_start, $m_end - $m_start );
			$html         = substr( $html, 0, $m_start ) . substr( $html, $m_end );
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

	$ts = ! empty( $attrs['watchedAt'] ) ? (int) strtotime( (string) $attrs['watchedAt'] ) : 0;
	$ts = $ts > 0 ? $ts : (int) get_post_time( 'U', true, $post );

	// 3b. The paper label in the middle of the cassette: a handwritten
	//     VHS-era line (decorative) and a checklist of the tape's facts.
	$facts = array();
	if ( 'tv' === ( $attrs['mediaType'] ?? 'movie' ) ) {
		$facts[] = __( 'TV series', 'courtneyr-child' );
		$season  = (int) ( $attrs['seasonNumber'] ?? 0 );
		$episode = (int) ( $attrs['episodeNumber'] ?? 0 );
		if ( $season > 0 ) {
			$facts[] = sprintf( /* translators: %d: season */ __( 'Season %d', 'courtneyr-child' ), $season ) . ( $episode > 0 ? ' · ' . sprintf( /* translators: %d: episode */ __( 'Episode %d', 'courtneyr-child' ), $episode ) : '' );
		}
		$ep_title = trim( (string) ( $attrs['episodeTitle'] ?? '' ) );
		if ( '' !== $ep_title ) {
			$facts[] = $ep_title;
		}
	} else {
		$facts[] = __( 'Film', 'courtneyr-child' );
	}
	$year = (int) ( $attrs['releaseYear'] ?? 0 );
	if ( $year > 0 ) {
		$facts[] = (string) $year;
	}
	$director = trim( (string) ( $attrs['director'] ?? '' ) );
	if ( '' !== $director ) {
		$facts[] = sprintf( /* translators: %s: director */ __( 'Directed by %s', 'courtneyr-child' ), $director );
	}
	// Label stock is narrow: a short month keeps the date on one line. The
	// Rental Record and the meta row keep the site's full date format.
	$facts[] = sprintf( /* translators: %s: date */ __( 'Watched %s', 'courtneyr-child' ), (string) wp_date( 'M j, Y', $ts ) );
	if ( ! empty( $attrs['isRewatch'] ) ) {
		$facts[] = __( 'A rewatch', 'courtneyr-child' );
	}
	// The label is its own grid item beside the poster; the plugin's
	// caption (the h-cite title link and year) stays in the DOM for the
	// microformats and is hidden, since the H1 and the Watch / find it row
	// already carry both. The caption holds no nested div, so its first
	// closing tag is its own.
	$c_open  = '<div class="pk-caption">';
	$c_start = strpos( $html, $c_open );
	if ( false !== $c_start ) {
		$c_end = strpos( $html, '</div>', $c_start );
		if ( false !== $c_end ) {
			$html = substr( $html, 0, $c_end + 6 ) . vhs_label( $facts ) . substr( $html, $c_end + 6 );
		}
	}
	// The reels and transport paint under everything else: first in the body.
	$k_start = strpos( $html, '<p class="pk-kindlabel">' );
	$k_end   = false !== $k_start ? strpos( $html, '</p>', $k_start ) : false;
	if ( false !== $k_end ) {
		$html = substr( $html, 0, $k_end + 4 ) . vhs_mechanics( heavy_left( $post ) ) . substr( $html, $k_end + 4 );
	}

	// 4. Under the cassette: the row, the details card, the notes, the
	//    margin marks, the meta row.
	$copy  = margin_lines( $s, 'film' );
	$after = ( isset( $row ) ? $row : '' ) . $plugin_meta . aside( $copy[0], 1 );
	if ( '' !== $note_html ) {
		$after .= notes_section( __( 'Notes from this watch', 'courtneyr-child' ), $note_html );
		$after .= aside( $copy[1], 2, 'cr-hand--orange' );
	}
	$after .= details_record( $attrs, $post, $ts );
	$after .= aside( $copy[ '' !== $note_html ? 2 : 1 ], 3 );

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
		if ( false !== strpos( $html, 'pk-embed--video' ) ) {
			$tags->add_class( 'cr-vhs--player' );
		}
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\journal_page', 20, 2 );

/**
 * The details card: a rental-store inventory sheet of stored facts with
 * a generated WATCHED seal.
 *
 * @param array<string, mixed> $attrs Watch-card attributes.
 * @param \WP_Post             $post  Post.
 * @param int                  $ts    Watched timestamp.
 * @return string
 */
function details_record( array $attrs, \WP_Post $post, int $ts ): string {
	$rows  = array();
	$is_tv = 'tv' === ( $attrs['mediaType'] ?? 'movie' );
	$rows[] = array( __( 'Type', 'courtneyr-child' ), esc_html( $is_tv ? __( 'TV series', 'courtneyr-child' ) : __( 'Film', 'courtneyr-child' ) ) );
	$title  = trim( (string) ( $attrs['mediaTitle'] ?? '' ) );
	if ( '' !== $title && 0 !== strcasecmp( $title, get_the_title( $post ) ) ) {
		$rows[] = array( __( 'Title', 'courtneyr-child' ), esc_html( $title ) );
	}
	if ( $is_tv ) {
		$show = trim( (string) ( $attrs['showTitle'] ?? '' ) );
		if ( '' !== $show && 0 !== strcasecmp( $show, $title ) ) {
			$rows[] = array( __( 'Show', 'courtneyr-child' ), esc_html( $show ) );
		}
		if ( (int) ( $attrs['seasonNumber'] ?? 0 ) > 0 ) {
			$rows[] = array( __( 'Season', 'courtneyr-child' ), esc_html( (string) (int) $attrs['seasonNumber'] ) );
		}
		if ( (int) ( $attrs['episodeNumber'] ?? 0 ) > 0 ) {
			$ep     = (string) (int) $attrs['episodeNumber'];
			$ep_t   = trim( (string) ( $attrs['episodeTitle'] ?? '' ) );
			$rows[] = array( __( 'Episode', 'courtneyr-child' ), esc_html( $ep . ( '' !== $ep_t ? ' · ' . $ep_t : '' ) ) );
		}
	}
	if ( (int) ( $attrs['releaseYear'] ?? 0 ) > 0 ) {
		$rows[] = array( __( 'Year', 'courtneyr-child' ), esc_html( (string) (int) $attrs['releaseYear'] ) );
	}
	$director = trim( (string) ( $attrs['director'] ?? '' ) );
	if ( '' !== $director ) {
		$rows[] = array( __( 'Director', 'courtneyr-child' ), esc_html( $director ) );
	}
	$rows[] = array( __( 'Watched', 'courtneyr-child' ), '<time datetime="' . esc_attr( (string) wp_date( 'c', $ts ) ) . '">' . esc_html( (string) wp_date( get_option( 'date_format' ), $ts ) ) . '</time>' . ( ! empty( $attrs['isRewatch'] ) ? ' · ' . esc_html__( 'rewatch', 'courtneyr-child' ) : '' ) );
	$rating = (int) ( $attrs['rating'] ?? 0 );
	if ( $rating > 0 ) {
		$rows[] = array( __( 'Rating', 'courtneyr-child' ), esc_html( sprintf( '%d / 5', $rating ) ) );
	}

	$s     = seed( (string) $post->ID, (string) ( $attrs['imdbId'] ?? '' ), (string) ( $attrs['tmdbId'] ?? '' ), (string) $title );
	$stamp = \Courtneyr\Child\Stamps\render(
		array(
			'shape'  => 'seal',
			'big'    => __( 'Watched', 'courtneyr-child' ),
			'small'  => gmdate( 'd M Y', $ts ),
			'ring'   => $is_tv ? __( 'TV club', 'courtneyr-child' ) : __( 'Film club', 'courtneyr-child' ),
			'ink'    => \Courtneyr\Child\Stamps\INKS[ \Courtneyr\Child\Stamps\pick( $s, 3, count( \Courtneyr\Child\Stamps\INKS ) ) ],
			'tilt'   => \Courtneyr\Child\Stamps\pick( $s, 6, \Courtneyr\Child\Stamps\TILTS ),
			'uid'    => 'cr-watch-seal-' . $post->ID,
			'family' => 'watched',
		)
	);

	$out  = '<section class="cr-record cr-vhs__record"><h2 class="cr-record-title">' . esc_html__( 'The details', 'courtneyr-child' ) . '</h2>';
	$out .= '<p class="cr-record-eyebrow">' . esc_html__( 'Rental record', 'courtneyr-child' ) . '</p><dl class="cr-record-rows">';
	foreach ( $rows as $r ) {
		$out .= '<div class="cr-record-row"><dt>' . esc_html( $r[0] ) . '</dt><dd>' . $r[1] . '</dd></div>';
	}
	return $out . '</dl><div class="cr-record-stamp">' . $stamp . '</div></section>';
}
