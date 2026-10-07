<?php
/**
 * Kind archives as store shelves (PKIW #226 listen, #227 watch).
 *
 * The listen archive stands the same compact cassettes the Stream shows
 * (inc/stream-media.php paints them) upright on shelves. Structure comes from
 * Post Kinds: templates/taxonomy-kind-listen.html renders the archive query
 * through the plugin's `is-style-pkiw-shelf` post template, and empty shelf
 * space is CSS, never placeholder posts. This file decides which archives are
 * shelves and loads assets/css/cr-media-shelf.css there.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\MediaShelf;

/**
 * Kinds whose archive is a media shelf.
 *
 * @return string[]
 */
function shelf_kinds(): array {
	return array( 'listen', 'watch' );
}

/**
 * Cases per shelf page: the 5 the card-grid rule served both shelves
 * before they had a size of their own. The shelf patterns' Query perPage
 * of 12 inherits the archive query, so it never set the page size.
 */
const SHELF_SIZE = 5;

/**
 * Page each shelf by SHELF_SIZE, and the Site Editor previews it at that
 * size (inc/theme-supports.php applies both).
 *
 * @param array<string, int> $sizes Kind slug => posts per page.
 * @return array<string, int>
 */
function page_size( $sizes ): array {
	$sizes = is_array( $sizes ) ? $sizes : array();
	foreach ( shelf_kinds() as $kind ) {
		$sizes[ $kind ] = SHELF_SIZE;
	}
	return $sizes;
}
add_filter( 'courtneyr_child_kind_archive_page_sizes', __NAMESPACE__ . '\\page_size' );

/**
 * Block style the shelf patterns give their stream card.
 *
 * The style travels with the block, so the Site Editor dresses the case
 * the way the archive does (see stream-media.php).
 */
const SHELF_CARD_STYLE = 'is-style-cr-shelf-case';

/**
 * Name the card style, so the editor lists it for the stream card.
 *
 * @return void
 */
function register_card_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => 'cr-shelf-case',
				'label' => __( 'Shelf case', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style' );

/**
 * The kind of the current shelf archive, or '' when the request isn't one.
 *
 * @return string
 */
function shelf_kind(): string {
	foreach ( shelf_kinds() as $kind ) {
		if ( is_tax( 'kind', $kind ) ) {
			return $kind;
		}
	}
	return '';
}

/**
 * Load the shelf paint on shelf archives (and in the editor, where the
 * template is edited).
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && '' === shelf_kind() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-media-shelf',
		COURTNEYR_CHILD_URI . '/assets/css/cr-media-shelf.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Number of watches that stand face-out under New releases on page 1.
 */
const NEW_RELEASES = 3;

/**
 * Split the watch shelf into labelled shelves (PKIW #227, approved mockup).
 *
 * Page 1: the first NEW_RELEASES posts stand face-out under "New releases",
 * the rest spine-out under "All watches". Later pages: every post spine-out
 * under "All watches". It stays one native Query Loop with core pagination;
 * this only closes the post template's <ul> after the third item and opens a
 * second one, each led by a real h2 (the cards' titles are h3), so the
 * shelves are headings in the outline, not painted labels. Every case's
 * link also names its watch date for screen readers (name_watch_date()).
 *
 * @param string $html  Rendered core/post-template.
 * @param array  $block Parsed block.
 * @return string
 */
function split_vhs_shelf( string $html, array $block ): string {
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( 'watch' !== shelf_kind() || false === strpos( $class, 'cr-vhs-shelf__list' ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( array( 'tag_name' => 'ul' ) ) ) {
		return $html;
	}
	$list_open = substr( $html, 0, (int) strpos( $html, '>' ) + 1 );
	$items     = substr( $html, strlen( $list_open ), (int) strrpos( $html, '</ul>' ) - strlen( $list_open ) );

	// Top-level <li> boundaries: count nesting so a card's own lists
	// (the "Watch / find it" sources) don't end an item early.
	$parts = array();
	$depth = 0;
	$start = null;
	$pos   = 0;
	while ( preg_match( '/<li\b|<\/li>/', $items, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
		$at  = (int) $m[0][1];
		$pos = $at + strlen( $m[0][0] );
		if ( '</li>' === $m[0][0] ) {
			--$depth;
			if ( 0 === $depth && null !== $start ) {
				$parts[] = substr( $items, $start, $pos - $start );
				$start   = null;
			}
			continue;
		}
		if ( 0 === $depth ) {
			$start = $at;
		}
		++$depth;
	}
	if ( array() === $parts ) {
		return $html;
	}

	$paged   = max( 1, (int) get_query_var( 'paged' ) );
	$parts   = array_map( __NAMESPACE__ . '\\name_watch_date', load_order( $parts ) );
	$face    = 1 === $paged ? lead_cover( array_map( __NAMESPACE__ . '\\case_only', array_slice( $parts, 0, NEW_RELEASES ) ) ) : array();
	$spine   = array_map( __NAMESPACE__ . '\\spine_only', 1 === $paged ? array_slice( $parts, NEW_RELEASES ) : $parts );
	$heading = static fn( string $id, string $text ): string => '<h2 class="cr-vhs-shelf__label" id="' . esc_attr( $id ) . '">' . esc_html( $text ) . '</h2>';
	$list    = static function ( string $modifier, string $labelled_by, array $lis ) use ( $list_open ): string {
		$open = new \WP_HTML_Tag_Processor( $list_open );
		$open->next_tag();
		$open->add_class( 'cr-vhs-shelf__list--' . $modifier );
		$open->set_attribute( 'aria-labelledby', $labelled_by );
		return $open->get_updated_html() . implode( '', $lis ) . '</ul>';
	};

	$out = '';
	if ( array() !== $face ) {
		$out .= $heading( 'cr-vhs-new', __( 'New releases', 'courtneyr-child' ) ) . $list( 'face', 'cr-vhs-new', $face );
	}
	if ( array() !== $spine ) {
		$out .= $heading( 'cr-vhs-all', __( 'All watches', 'courtneyr-child' ) ) . $list( 'spine', 'cr-vhs-all', $spine );
	}
	return '<div class="cr-vhs-shelf">' . $out . '</div>';
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\split_vhs_shelf', 10, 2 );

/**
 * Load every image on the shelf lazily (PKIW #227).
 *
 * On the live site lazy means Perfmatters' data-src swap (it drops
 * loading="lazy" for its own). case_only() then lifts page 1's face-out
 * covers, the first row, to eager and marks them skip-lazy, and
 * lead_cover() gives the first of them high priority. Core keeps each
 * attachment's srcset.
 *
 * @param string[] $items Rendered <li> items in query order.
 * @return string[]
 */
function load_order( array $items ): array {
	foreach ( $items as $i => $item ) {
		$tags = new \WP_HTML_Tag_Processor( $item );
		while ( $tags->next_tag( 'img' ) ) {
			$tags->set_attribute( 'loading', 'lazy' );
			$tags->remove_attribute( 'fetchpriority' );
		}
		$items[ $i ] = $tags->get_updated_html();
	}
	return $items;
}

/**
 * Cases in the listen shelf's widest row (4 / 2 / 1 columns at 1440 / 720
 * / 375). The covers of these cases can sit in the first row at any width.
 */
const LISTEN_FIRST_ROW = 4;

/**
 * Width of a cassette's cover print on the shelf: the label's 9cqw square
 * in cr-post-kinds.css measures 20 to 29px from 320 to 1536px wide.
 */
const LISTEN_COVER_SIZES = '2rem';

/**
 * Dress the listen shelf's cover art (PKIW #226 mockup fidelity).
 *
 * Each case's title link covers the whole cassette and names it, so the
 * cover's alt is emptied here and the case isn't announced twice; the
 * stored alt stays on the single and the Stream. The covers of the first
 * LISTEN_FIRST_ROW cases load eagerly at high priority on every page, since
 * that row stands in the first screen; the rest stay lazy. A media library
 * cover's `sizes` names the print's width, so the browser picks the small
 * file from core's srcset; `auto` leads it only on a lazy cover, the one
 * place it is valid.
 *
 * @param string $html  Rendered core/post-template.
 * @param array  $block Parsed block.
 * @return string
 */
function listen_covers( string $html, array $block ): string {
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( 'listen' !== shelf_kind() || false === strpos( $class, 'cr-media-shelf__list' ) ) {
		return $html;
	}

	$tags        = new \WP_HTML_Tag_Processor( $html );
	$item        = 0;
	$after_media = false;
	while ( $tags->next_tag() ) {
		$tag         = $tags->get_tag();
		$is_cover    = $after_media && 'IMG' === $tag;
		$after_media = 'DIV' === $tag && $tags->has_class( 'pk-media' );
		if ( 'LI' === $tag && $tags->has_class( 'wp-block-post' ) ) {
			++$item;
			continue;
		}
		if ( ! $is_cover ) {
			continue;
		}
		$tags->set_attribute( 'alt', '' );
		$first_row = $item <= LISTEN_FIRST_ROW;
		$tags->set_attribute( 'loading', $first_row ? 'eager' : 'lazy' );
		if ( $first_row ) {
			$tags->set_attribute( 'fetchpriority', 'high' );
		} else {
			$tags->remove_attribute( 'fetchpriority' );
		}
		if ( null !== $tags->get_attribute( 'srcset' ) ) {
			$tags->set_attribute( 'sizes', ( $first_row ? '' : 'auto, ' ) . LISTEN_COVER_SIZES );
		}
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\listen_covers', 10, 2 );

/**
 * Keep Perfmatters' critical-image preload off the shelves (PKIW #227, #226).
 *
 * The site option preloads the first two images on a page at
 * fetchpriority high: two face-out covers on page 1, and on page 2 the
 * first spine and the footer avatar. case_only() marks the watch
 * shelf's first-row covers and listen_covers() the listen shelf's
 * first row instead, with no preload link.
 * Perfmatters reads this on `wp`, after the main query.
 *
 * @param mixed $count Images Perfmatters preloads.
 * @return mixed
 */
function no_critical_image_preload( $count ) {
	return '' !== shelf_kind() ? 0 : $count;
}
add_filter( 'perfmatters_preload_critical_images', __NAMESPACE__ . '\\no_critical_image_preload' );

/**
 * Cut every element of one tag that carries a class from a fragment.
 *
 * Counts nesting of the same tag so an element's own children of that
 * tag don't end it early. Used on the Stream card's metadata, whose
 * elements nest at most one level of their own tag.
 *
 * @param string $html       Fragment.
 * @param string $tag        Tag name.
 * @param string $class_name One class the element carries.
 * @return string
 */
function cut_elements( string $html, string $tag, string $class_name ): string {
	$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
	while ( preg_match( $open, $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$start = (int) $m[0][1];
		$pos   = $start + strlen( $m[0][0] );
		$depth = 1;
		while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
			$pos    = (int) $t[0][1] + strlen( $t[0][0] );
			$depth += '/' === $t[1][0] ? -1 : 1;
		}
		if ( $depth > 0 ) {
			return $html; // No closing tag: leave the fragment alone rather than cut blind.
		}
		$html = substr( $html, 0, $start ) . substr( $html, $pos );
	}
	return $html;
}

/**
 * What a watch case leaves on the single post (PKIW #227, Courtney's
 * 2026-10-03 ruling: no metadata on archive pages): the year and rewatch
 * line, the watched date (also a pk-sub), the stars and p-rating, the
 * "Watch / find it" links, the review, the meta links, the kind label
 * and the kind badge, as the comics rack cuts them (inc/comic.php). Each
 * is [ tag, class ] for cut_elements().
 */
const SINGLE_ONLY = array(
	array( 'p', 'pk-sub' ),
	array( 'div', 'pk-stars' ),
	array( 'data', 'p-rating' ),
	array( 'div', 'pk-sources' ),
	array( 'div', 'pk-note' ),
	array( 'div', 'pk-meta' ),
	array( 'span', 'pk-kindlabel' ),
	array( 'div', 'pk-badge' ),
);

/**
 * Cut a list of [ tag, class ] elements from one rendered item.
 *
 * @param string     $item One rendered <li>.
 * @param string[][] $cuts [ tag, class ] pairs.
 * @return string
 */
function cut_all( string $item, array $cuts ): string {
	foreach ( $cuts as $cut ) {
		$item = cut_elements( $item, $cut[0], $cut[1] );
	}
	return $item;
}

/**
 * Reduce a spine-out watch item to its title (PKIW #227).
 *
 * A spine is the h3 title link alone: SINGLE_ONLY and the poster are cut
 * from the markup, so nothing stays behind for CSS to hide. The h-entry,
 * the h-cite's u-watch-of and watched URL, and `.pk-entry-props` (author,
 * url, dt-published) stay for microformats.
 *
 * @param string $item One rendered <li>.
 * @return string
 */
function spine_only( string $item ): string {
	return cut_all( $item, array_merge( SINGLE_ONLY, array( array( 'div', 'pk-media' ) ) ) );
}

/**
 * Name a watch case's link with its watch date for screen readers (PKIW
 * issue 227, Courtney's 2026-10-07 decision).
 *
 * The shelf shows no dates, so a first watch and a rewatch of one title
 * would share a link name. The title link gets the post's published date,
 * from get_the_date() in the site's date format, as hidden text: "Dune,
 * watched 4 May 2026". Nothing visible changes. Face-out cases get it too:
 * SINGLE_ONLY cuts their date line as well. The h3 is the h-cite's p-name,
 * so a hidden, empty `value-title` span opens it with the title: under the
 * microformats value class pattern, parsers take the name from it (a direct
 * child of the h3; php-mf2 doesn't look deeper) and the cited name stays
 * the title alone. The post ID comes from the post template's `post-{ID}`
 * class on the item.
 *
 * @param string $item One rendered <li>.
 * @return string
 */
function name_watch_date( string $item ): string {
	$tags = new \WP_HTML_Tag_Processor( $item );
	if ( ! $tags->next_tag( 'li' ) ) {
		return $item;
	}
	$post_id = 0;
	foreach ( $tags->class_list() as $class_name ) {
		if ( preg_match( '/^post-(\d+)$/', $class_name, $m ) ) {
			$post_id = (int) $m[1];
			break;
		}
	}
	$date = 0 < $post_id ? get_the_date( '', $post_id ) : '';
	if ( ! is_string( $date ) || '' === $date ) {
		return $item;
	}
	if ( ! preg_match( '/(<h3\b[^>]*\bclass="[^"]*(?<![\w-])pk-title(?![\w-])[^"]*"[^>]*>)\s*<a\b[^>]*>/', $item, $open, PREG_OFFSET_CAPTURE ) ) {
		return $item;
	}
	$heading = (int) $open[1][1] + strlen( $open[1][0] );
	$start   = (int) $open[0][1] + strlen( $open[0][0] );
	$end     = strpos( $item, '</a>', $start );
	if ( false === $end ) {
		return $item;
	}
	$title = wp_strip_all_tags( substr( $item, $start, $end - $start ) );
	/* translators: %s: the date the watch was posted, in the site's date format. */
	$hidden = sprintf( __( 'watched %s', 'courtneyr-child' ), $date );
	return substr( $item, 0, $heading )
		. '<span class="value-title" title="' . esc_attr( $title ) . '" hidden></span>'
		. substr( $item, $heading, $end - $heading )
		. '<span class="cr-sr-only">, ' . esc_html( $hidden ) . '</span>'
		. substr( $item, $end );
}

/**
 * Width of a face-out cover: the case's 13rem column less the recess's
 * 2.3rem spine band and 0.65rem inset in cr-media-shelf.css, 161px at
 * most.
 */
const WATCH_COVER_SIZES = 'calc(13rem - 2.95rem)';

/**
 * Reduce a face-out watch item to its case (PKIW #227, approved 2026-10-03).
 *
 * The archive is for browsing cases; SINGLE_ONLY stays on the single
 * post. What remains in the item is the clamshell (cover or title sleeve)
 * and its h3 title link, which the stylesheet turns into the whole case's
 * link. With a cover, the title is the link's one accessible name and the
 * cover's alt is emptied so it isn't announced a second time. The cover
 * is first-row imagery, so it loads eagerly; skip-lazy keeps Perfmatters'
 * swap off it (inc/journal.php), since Perfmatters lazy-loads an eager
 * image that lacks fetchpriority=high. Its `sizes` names the cover's
 * width without the `auto` core leads it with: `auto` is valid only on a
 * lazy image, and browsers fall back to the next slot, wider than the
 * cover. Modern Image Formats in picture mode wraps the cover in a
 * <picture> before this runs, and each <source> copies the lazy-time
 * sizes, so every <source> in it gets the same value. Perfmatters leaves
 * a <picture> that holds skip-lazy alone, sources included.
 * Without a cover, the title shows on the sleeve. `.pk-entry-props`
 * outside the card keeps the entry's dt-published for microformats.
 *
 * @param string $item One rendered <li>.
 * @return string
 */
function case_only( string $item ): string {
	$item  = cut_all( $item, SINGLE_ONLY );
	$card  = array(
		'tag_name'   => 'article',
		'class_name' => 'pk-card',
	);
	$cover = array(
		'tag_name'   => 'div',
		'class_name' => 'pk-media',
	);
	$tags  = new \WP_HTML_Tag_Processor( $item );
	if ( ! $tags->next_tag( $card ) ) {
		return $item;
	}
	$tags->add_class( 'cr-vhs--case' );
	$has_cover  = $tags->next_tag( $cover );
	$in_picture = false;
	while ( $has_cover && $tags->next_tag() ) {
		$tag = $tags->get_tag();
		if ( 'PICTURE' === $tag ) {
			$in_picture = true;
			continue;
		}
		if ( 'SOURCE' === $tag && $in_picture ) {
			if ( null !== $tags->get_attribute( 'sizes' ) ) {
				$tags->set_attribute( 'sizes', WATCH_COVER_SIZES );
			}
			continue;
		}
		if ( 'IMG' !== $tag ) {
			continue;
		}
		$tags->set_attribute( 'alt', '' );
		$tags->set_attribute( 'loading', 'eager' );
		$tags->add_class( 'skip-lazy' );
		$tags->set_attribute( 'data-skip-lazy', '1' );
		if ( null !== $tags->get_attribute( 'srcset' ) ) {
			$tags->set_attribute( 'sizes', WATCH_COVER_SIZES );
		}
		break;
	}
	$item = $tags->get_updated_html();
	if ( $has_cover ) {
		$tags = new \WP_HTML_Tag_Processor( $item );
		$tags->next_tag( $card );
		$tags->add_class( 'has-cover' );
		$item = $tags->get_updated_html();
	}
	return $item;
}

/**
 * Give the first face-out cover high priority (PKIW #227, after #106).
 *
 * The other first-row covers stay eager without it: on a phone the
 * face-out list is one column, so they sit below the first screen and
 * would compete with the first cover for bandwidth.
 *
 * @param string[] $items Face-out items after case_only().
 * @return string[]
 */
function lead_cover( array $items ): array {
	foreach ( $items as $i => $item ) {
		$tags = new \WP_HTML_Tag_Processor( $item );
		while ( $tags->next_tag( 'img' ) ) {
			if ( 'eager' === $tags->get_attribute( 'loading' ) ) {
				$tags->set_attribute( 'fetchpriority', 'high' );
				$items[ $i ] = $tags->get_updated_html();
				return $items;
			}
		}
	}
	return $items;
}
