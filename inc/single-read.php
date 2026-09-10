<?php
/**
 * Single read: an opened reading-journal spread around a physical book.
 *
 * The plugin's read-card block owns every fact (title, author, ISBN,
 * publisher, dates, pages, progress, rating, cover, links, review) and
 * its own microformats. This file only rearranges the single view:
 * rating and status under the H1, the cover wrapped as a dimensional
 * book (spine, page block, bookmark), a "Read / find it" row from
 * stored URLs, the review moved to "Notes from this read", a library
 * card "Reading record", the plugin's Kindle preview (rendered through
 * its own bridge) as "Read a sample", Rock Salt margin marks, and a
 * quiet meta row. cr-post-kinds.css paints it. Scope:
 * body.single-post.kind-read.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleRead;

use function Courtneyr\Child\Journal\aside;
use function Courtneyr\Child\Journal\margin_lines;
use function Courtneyr\Child\Journal\meta_item;
use function Courtneyr\Child\Journal\meta_row;
use function Courtneyr\Child\Journal\notes_section;
use function Courtneyr\Child\Journal\wrap;
use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\render;
use function Courtneyr\Child\Stamps\seed;
use const Courtneyr\Child\Stamps\INKS;
use const Courtneyr\Child\Stamps\TILTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this the single view of a read?
 *
 * @return bool
 */
function is_read_single(): bool {
	return \is_singular( 'post' ) && \has_term( 'read', 'kind', \get_queried_object_id() );
}

/**
 * The read-card block in a post's content.
 *
 * @param \WP_Post $post Post.
 * @return array<string, mixed>|null
 */
function find_read_block( \WP_Post $post ): ?array {
	$found = null;
	$walk  = static function ( array $blocks ) use ( &$walk, &$found ): void {
		foreach ( $blocks as $block ) {
			if ( null !== $found ) {
				return;
			}
			if ( 'post-kinds-indieweb/read-card' === ( $block['blockName'] ?? '' ) ) {
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
 * Reader-facing copy for a stored status. The stored value is untouched.
 *
 * @param string $status readStatus attribute.
 * @return array{0: string, 1: string} label, short stamp word.
 */
function status_copy( string $status ): array {
	switch ( $status ) {
		case 'reading':
			return array( __( 'Currently reading', 'courtneyr-child' ), __( 'Reading', 'courtneyr-child' ) );
		case 'finished':
			return array( __( 'Finished', 'courtneyr-child' ), __( 'Finished', 'courtneyr-child' ) );
		case 'abandoned':
			return array( __( 'Set aside', 'courtneyr-child' ), __( 'Set aside', 'courtneyr-child' ) );
		default:
			return array( __( 'To read', 'courtneyr-child' ), __( 'To read', 'courtneyr-child' ) );
	}
}

/**
 * A stored date as ISO + display, or empty.
 *
 * @param string $raw Attribute value.
 * @return array{0: string, 1: string}
 */
function date_pair( string $raw ): array {
	$ts = '' !== $raw ? strtotime( $raw ) : false;
	if ( ! $ts ) {
		return array( '', '' );
	}
	return array( gmdate( 'c', $ts ), wp_date( (string) get_option( 'date_format' ), $ts ) );
}

/**
 * Labels for hosts a book URL commonly points at. Unknown hosts read "Book".
 *
 * @param string $url External URL.
 * @return string
 */
function book_label( string $url ): string {
	$host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$known = array(
		'openlibrary.org'     => 'Open Library',
		'bookshop.org'        => 'Bookshop',
		'goodreads.com'       => 'Goodreads',
		'thestorygraph.com'   => 'StoryGraph',
		'archive.org'         => 'Internet Archive',
		'libbyapp.com'        => 'Libby',
		'overdrive.com'       => 'OverDrive',
		'worldcat.org'        => 'WorldCat',
		'amazon.com'          => 'Amazon',
		'books.google.com'    => 'Google Books',
		'play.google.com'     => 'Google Books',
		'apple.com'           => 'Apple Books',
		'kobo.com'            => 'Kobo',
		'barnesandnoble.com'  => 'Barnes & Noble',
		'librarything.com'    => 'LibraryThing',
	);
	foreach ( $known as $needle => $label ) {
		if ( $host === $needle || str_ends_with( $host, '.' . $needle ) ) {
			return $label;
		}
	}
	return __( 'Book', 'courtneyr-child' );
}

/**
 * Links for the "Read / find it" row: only stored URLs.
 *
 * @param array<string, mixed> $attrs Read-card attributes.
 * @return array<int, array{0: string, 1: string, 2: string}> url, label, variant.
 */
function book_links( array $attrs ): array {
	$links = array();
	$url   = trim( (string) ( $attrs['bookUrl'] ?? '' ) );
	if ( '' !== $url && '' !== (string) wp_parse_url( $url, PHP_URL_HOST ) && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
		$links[] = array( $url, book_label( $url ), 'book' );
	}
	$ol = trim( (string) ( $attrs['openlibraryId'] ?? '' ) );
	if ( '' !== $ol && false === strpos( $url, 'openlibrary.org' ) ) {
		// The plugin's u-uid is 'https://openlibrary.org' . id; the id
		// carries its own leading slash.
		$links[] = array( 'https://openlibrary.org' . ( '/' === $ol[0] ? '' : '/' ) . $ol, 'Open Library', 'details' );
	}
	return $links;
}

/**
 * The sources row.
 *
 * @param array<int, array{0: string, 1: string, 2: string}> $links Links.
 * @return string Empty when there is nothing to link.
 */
function sources_row( array $links ): string {
	if ( empty( $links ) ) {
		return '';
	}
	$row  = '<nav class="pk-sources pk-sources--read" aria-label="' . esc_attr__( 'Read or find it', 'courtneyr-child' ) . '">';
	$row .= '<p class="pk-sources__label">' . esc_html__( 'Read / find it', 'courtneyr-child' ) . '</p><ul class="pk-sources__list">';
	foreach ( $links as $link ) {
		$row .= '<li><a class="pk-sources__link pk-sources__link--' . esc_attr( $link[2] ) . '" href="' . esc_url( $link[0] ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html( $link[1] )
			. '<span class="pk-sr-only"> ' . esc_html__( '(opens in a new tab)', 'courtneyr-child' ) . '</span>'
			. '<span class="pk-sources__arrow" aria-hidden="true">↗</span></a></li>';
	}
	return $row . '</ul></nav>';
}

/**
 * Rating, status and date under the H1.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function add_title_lede( string $html, array $block ): string {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || ! is_read_single() ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$read = find_read_block( $post );
	if ( null === $read ) {
		return $html;
	}
	$a      = (array) ( $read['attrs'] ?? array() );
	$author = trim( (string) ( $a['authorName'] ?? '' ) );
	$status = (string) ( $a['readStatus'] ?? 'to-read' );
	$rating = (int) ( $a['rating'] ?? 0 );
	$pages  = (int) ( $a['pageCount'] ?? 0 );
	$cur    = (int) ( $a['currentPage'] ?? 0 );
	$out    = '';

	if ( '' !== $author ) {
		$out .= '<p class="cr-journal__lede cr-read__author">' . esc_html( $author ) . '</p>';
	}
	if ( $rating > 0 ) {
		$out .= '<p class="cr-journal__rating" aria-label="' . esc_attr( sprintf( /* translators: %d: rating */ __( 'Rated %d of 5', 'courtneyr-child' ), $rating ) ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$out .= '<span class="cr-journal__star' . ( $i <= $rating ? '' : ' cr-journal__star--off' ) . '" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M12 2l3 6.5 7 .6-5.3 4.6 1.6 6.8L12 17l-6.9 3.5 1.6-6.8L1.4 9.1l7-.6z"/></svg></span>';
		}
		$out .= '<span class="cr-journal__rating-value" aria-hidden="true">' . esc_html( sprintf( '%d / 5', $rating ) ) . '</span></p>';
	}

	list( $label ) = status_copy( $status );
	$when          = '';
	if ( in_array( $status, array( 'finished', 'abandoned' ), true ) ) {
		list( $iso, $disp ) = date_pair( (string) ( $a['finishedAt'] ?? '' ) );
		if ( '' !== $iso ) {
			$when = '<time datetime="' . esc_attr( $iso ) . '">' . esc_html( $disp ) . '</time>';
		}
	} elseif ( 'reading' === $status ) {
		list( $iso, $disp ) = date_pair( (string) ( $a['startedAt'] ?? '' ) );
		if ( '' !== $iso ) {
			$when = esc_html__( 'since', 'courtneyr-child' ) . ' <time datetime="' . esc_attr( $iso ) . '">' . esc_html( $disp ) . '</time>';
		}
	}
	$out .= '<p class="cr-read__status cr-read__status--' . esc_attr( $status ) . '"><span class="cr-read__status-chip">' . esc_html( $label ) . '</span>'
		. ( '' !== $when ? '<span class="cr-read__status-when">' . $when . '</span>' : '' );
	if ( 'reading' === $status && $pages > 0 && $cur > 0 ) {
		$pct  = min( 100, (int) round( $cur / $pages * 100 ) );
		$out .= '<span class="cr-read__status-progress">' . esc_html( sprintf( /* translators: 1: current page, 2: pages, 3: percent */ __( '%1$d of %2$d pages · %3$d%%', 'courtneyr-child' ), $cur, $pages, $pct ) ) . '</span>';
	}
	$out .= '</p>';

	return $html . $out;
}
add_filter( 'render_block', __NAMESPACE__ . '\\add_title_lede', 20, 2 );

/**
 * The library card.
 *
 * @param array<string, mixed> $a    Attributes.
 * @param \WP_Post             $post Post.
 * @return string Empty when no fact is stored.
 */
function reading_record( array $a, \WP_Post $post ): string {
	$status = (string) ( $a['readStatus'] ?? 'to-read' );
	$rows   = array();
	list( $s_iso, $s_disp ) = date_pair( (string) ( $a['startedAt'] ?? '' ) );
	list( $f_iso, $f_disp ) = date_pair( (string) ( $a['finishedAt'] ?? '' ) );
	if ( '' !== $s_iso ) {
		$rows[] = array( __( 'Started', 'courtneyr-child' ), '<time datetime="' . esc_attr( $s_iso ) . '">' . esc_html( $s_disp ) . '</time>' );
	}
	if ( '' !== $f_iso && in_array( $status, array( 'finished', 'abandoned' ), true ) ) {
		$rows[] = array( 'abandoned' === $status ? __( 'Set aside', 'courtneyr-child' ) : __( 'Finished', 'courtneyr-child' ), '<time datetime="' . esc_attr( $f_iso ) . '">' . esc_html( $f_disp ) . '</time>' );
	}
	$pages = (int) ( $a['pageCount'] ?? 0 );
	$cur   = (int) ( $a['currentPage'] ?? 0 );
	if ( 'reading' === $status && $pages > 0 && $cur > 0 ) {
		$rows[] = array( __( 'Progress', 'courtneyr-child' ), esc_html( sprintf( /* translators: 1: page, 2: pages */ __( 'page %1$d of %2$d', 'courtneyr-child' ), $cur, $pages ) ) );
	} elseif ( $pages > 0 ) {
		$rows[] = array( __( 'Pages', 'courtneyr-child' ), esc_html( (string) $pages ) );
	}
	foreach ( array( 'isbn' => __( 'ISBN', 'courtneyr-child' ), 'publisher' => __( 'Publisher', 'courtneyr-child' ), 'publishDate' => __( 'Published', 'courtneyr-child' ) ) as $key => $label ) {
		$v = trim( (string) ( $a[ $key ] ?? '' ) );
		if ( '' !== $v ) {
			$rows[] = array( $label, esc_html( $v ) );
		}
	}
	if ( empty( $rows ) ) {
		return '';
	}

	list( , $word ) = status_copy( $status );
	$seed           = seed( (string) $post->ID, (string) ( $a['isbn'] ?? '' ), (string) ( $a['bookTitle'] ?? '' ) );
	$stamp          = render(
		array(
			'shape'  => 'seal',
			'big'    => $word,
			'small'  => '' !== $f_disp && in_array( $status, array( 'finished', 'abandoned' ), true ) ? gmdate( 'd M Y', (int) strtotime( (string) $a['finishedAt'] ) ) : ( '' !== $s_disp ? gmdate( 'd M Y', (int) strtotime( (string) $a['startedAt'] ) ) : '' ),
			'ring'   => __( 'Reading record', 'courtneyr-child' ),
			'ink'    => INKS[ pick( $seed, 3, count( INKS ) ) ],
			'tilt'   => pick( $seed, 6, TILTS ),
			'uid'    => 'cr-read-seal-' . $post->ID,
			'family' => 'reading-record',
		)
	);

	$out  = '<section class="cr-read__record"><h2 class="cr-read__record-title">' . esc_html__( 'Reading record', 'courtneyr-child' ) . '</h2>';
	$out .= '<p class="cr-read__record-eyebrow">' . esc_html( sprintf( /* translators: %s: site name */ __( 'From the library of %s', 'courtneyr-child' ), get_bloginfo( 'name' ) ) ) . '</p>';
	$out .= '<dl class="cr-read__record-rows">';
	foreach ( $rows as $row ) {
		$out .= '<div class="cr-read__record-row"><dt>' . esc_html( $row[0] ) . '</dt><dd>' . $row[1] . '</dd></div>';
	}
	$out .= '</dl><div class="cr-read__record-stamp">' . $stamp . '</div></section>';
	return $out;
}

/**
 * The plugin's Kindle preview figure, rendered through its own bridge.
 *
 * Looks for the marked core/embed the plugin's pattern inserts; when the
 * post has none but does carry an ISBN or ASIN, renders that same block
 * so Kindle_Embed_Bridge can rewrite it. Only a result holding a real
 * iframe is kept.
 *
 * @param string   $html Post content HTML (the figure is cut out of it).
 * @param \WP_Post $post Post.
 * @return string Figure HTML with an iframe, or empty.
 */
function kindle_figure( string &$html, \WP_Post $post ): string {
	$figure = '';
	if ( preg_match( '/<figure class="wp-block-embed[^"]*pkiw-kindle-preview[^"]*">.*?<\/figure>/s', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$figure = $m[0][0];
		$html   = substr( $html, 0, $m[0][1] ) . substr( $html, $m[0][1] + strlen( $figure ) );
	} elseif ( class_exists( '\\PKIW\\Kindle_Embed_Bridge' ) ) {
		$has_key = '' !== (string) get_post_meta( $post->ID, '_pkiw_read_asin', true ) || '' !== (string) get_post_meta( $post->ID, '_pkiw_read_isbn', true );
		if ( $has_key ) {
			$inner  = '<figure class="wp-block-embed is-type-video is-provider-amazon-kindle wp-block-embed-amazon-kindle pkiw-kindle-preview"><div class="wp-block-embed__wrapper">' . "\n" . 'https://read.amazon.com/kp/embed' . "\n" . '</div></figure>';
			$figure = render_block(
				array(
					'blockName'    => 'core/embed',
					'attrs'        => array(
						'url'              => 'https://read.amazon.com/kp/embed',
						'type'             => 'video',
						'providerNameSlug' => 'amazon-kindle',
						'className'        => 'pkiw-kindle-preview',
					),
					'innerBlocks'  => array(),
					'innerHTML'    => $inner,
					'innerContent' => array( $inner ),
				)
			);
		}
	}
	return false !== strpos( $figure, '<iframe' ) ? $figure : '';
}

/**
 * Build the spread around the card.
 *
 * @param string               $html  Rendered post content.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function journal_page( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_read_single() ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$read = find_read_block( $post );
	if ( null === $read || false === strpos( $html, 'k-read' ) ) {
		return $html;
	}
	$a      = (array) ( $read['attrs'] ?? array() );
	$status = (string) ( $a['readStatus'] ?? 'to-read' );
	$seed   = seed( (string) $post->ID, (string) ( $a['isbn'] ?? '' ), (string) ( $a['bookTitle'] ?? '' ) );

	// 1. The Kindle figure leaves the flow (returns as "Read a sample").
	//    Off by default: read.amazon.com answers the preview URL with a
	//    frame-ancestors policy limited to Amazon's own domains (verified
	//    2026-09-10), so browsers block the iframe on this site. The
	//    figure is still cut out so no blocked frame renders; flip the
	//    filter when the plugin's bridge points at an embeddable source.
	$kindle = kindle_figure( $html, $post );
	if ( ! apply_filters( 'courtneyr_child_read_sample', false, $post ) ) {
		$kindle = '';
	}

	// 2. The review leaves the book for the notes section.
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

	// 3. The cover: the card's own, else the post's featured image, else
	//    a typographic cover. The bookmark follows the stored status.
	$meta_pos = strpos( $html, '<div class="pk-meta">' );
	if ( false !== $meta_pos ) {
		$cover = '';
		if ( false === strpos( $html, 'class="pk-media"' ) ) {
			if ( has_post_thumbnail( $post ) ) {
				$cover = '<div class="pk-media cr-book__cover cr-book__cover--featured">' . get_the_post_thumbnail( $post, 'large', array( 'class' => 'cr-book__img', 'loading' => 'lazy' ) ) . '</div>';
			} else {
				$cover = '<div class="pk-media cr-book__cover cr-book__cover--type" aria-hidden="true"><span class="cr-book__type-title">' . esc_html( (string) ( $a['bookTitle'] ?? $post->post_title ) ) . '</span><span class="cr-book__type-author">' . esc_html( (string) ( $a['authorName'] ?? '' ) ) . '</span></div>';
			}
		}
		$row  = sources_row( book_links( $a ) );
		$html = substr( $html, 0, $meta_pos ) . $cover . $row . substr( $html, $meta_pos );

		// The bookmark rides inside the cover element so it can hang off
		// the book's own edges.
		if ( 'to-read' !== $status ) {
			$m_start = strpos( $html, 'class="pk-media' );
			$m_end   = false !== $m_start ? strpos( $html, '</div>', $m_start ) : false;
			if ( false !== $m_end ) {
				$ribbon = '<span class="cr-book__ribbon cr-book__ribbon--' . esc_attr( $status ) . '" aria-hidden="true"></span>';
				$html   = substr( $html, 0, $m_end ) . $ribbon . substr( $html, $m_end );
			}
		}
	}

	// 4. The article gets its object classes.
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'cr-book' );
		$tags->add_class( 'cr-book--' . $status );
		$html = $tags->get_updated_html();
	}

	// 5. What follows the book: margin marks, notes, record, sample, meta.
	$lines = margin_lines( $seed, 'read' );
	$after = aside( $lines[0], 1 ) . aside( $lines[1], 2, 'cr-hand--orange cr-hand--underline' );
	if ( '' !== $note_html ) {
		$after .= notes_section( __( 'Notes from this read', 'courtneyr-child' ), $note_html );
	}
	$after .= reading_record( $a, $post );
	if ( '' !== $kindle ) {
		$after .= '<section class="cr-read__sample"><h2 class="cr-read__sample-title">' . esc_html__( 'Read a sample', 'courtneyr-child' ) . '</h2>'
			. '<p class="cr-hand cr-hand--small cr-read__sample-note" aria-hidden="true">' . esc_html__( 'peek inside →', 'courtneyr-child' ) . '</p>'
			. $kindle . '</section>';
	}
	$after .= aside( $lines[2], 3 );

	$items = array();
	list( $label ) = status_copy( $status );
	list( $s_iso, $s_disp ) = date_pair( (string) ( $a['startedAt'] ?? '' ) );
	list( $f_iso, $f_disp ) = date_pair( (string) ( $a['finishedAt'] ?? '' ) );
	$when = '';
	if ( '' !== $s_iso ) {
		$when .= '<time datetime="' . esc_attr( $s_iso ) . '">' . esc_html( $s_disp ) . '</time>';
	}
	if ( '' !== $f_iso && in_array( $status, array( 'finished', 'abandoned' ), true ) ) {
		$when .= ( '' !== $when ? ' – ' : '' ) . '<time datetime="' . esc_attr( $f_iso ) . '">' . esc_html( $f_disp ) . '</time>';
	}
	$items[] = meta_item( 'time', ( '' !== $when ? $when : esc_html( get_the_date( '', $post ) ) ), $label );
	$book    = esc_html( (string) ( $a['bookTitle'] ?? $post->post_title ) );
	$facts   = array_filter( array( (string) ( $a['authorName'] ?? '' ), ( (int) ( $a['pageCount'] ?? 0 ) > 0 ? sprintf( /* translators: %d: pages */ __( '%d pages', 'courtneyr-child' ), (int) $a['pageCount'] ) : '' ) ) );
	$items[] = meta_item( 'book', $book . ( ! empty( $facts ) ? '<br>' . esc_html( implode( ' · ', $facts ) ) : '' ), __( 'Book', 'courtneyr-child' ) );
	$after  .= meta_row( $items );

	return wrap( $html, $after );
}
add_filter( 'render_block', __NAMESPACE__ . '\\journal_page', 20, 2 );
