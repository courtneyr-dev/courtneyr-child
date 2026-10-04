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
 * shelves are headings in the outline, not painted labels.
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
	$face    = 1 === $paged ? array_map( __NAMESPACE__ . '\\case_only', array_slice( $parts, 0, NEW_RELEASES ) ) : array();
	$spine   = 1 === $paged ? array_slice( $parts, NEW_RELEASES ) : $parts;
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
 * Reduce a face-out watch item to its case (PKIW #227, approved 2026-10-03).
 *
 * The archive is for browsing cases; the year, rating, "Watch / find it"
 * links, date and meta links stay on the single post. What remains in the
 * item is the clamshell (cover or title sleeve) and its h3 title link,
 * which the stylesheet turns into the whole case's link. With a cover,
 * the title is the link's one accessible name and the cover's alt is
 * emptied so it isn't announced a second time; without one, the title
 * shows on the sleeve. `.pk-entry-props` outside the card keeps the
 * entry's dt-published for microformats.
 *
 * @param string $item One rendered <li>.
 * @return string
 */
function case_only( string $item ): string {
	foreach ( array( array( 'p', 'pk-sub' ), array( 'div', 'pk-stars' ), array( 'data', 'p-rating' ), array( 'div', 'pk-sources' ), array( 'div', 'pk-meta' ), array( 'span', 'pk-kindlabel' ) ) as $cut ) {
		$item = cut_elements( $item, $cut[0], $cut[1] );
	}
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
	$has_cover = $tags->next_tag( $cover );
	if ( $has_cover && $tags->next_tag( 'img' ) ) {
		$tags->set_attribute( 'alt', '' );
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
