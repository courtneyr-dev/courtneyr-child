<?php
/**
 * Kind archives as store shelves (PKIW #226 listen; #227 watch reuses it).
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
	return array( 'listen' );
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
