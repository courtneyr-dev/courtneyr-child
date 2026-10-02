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
	$face    = 1 === $paged ? array_slice( $parts, 0, NEW_RELEASES ) : array();
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
