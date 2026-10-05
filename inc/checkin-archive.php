<?php
/**
 * Check-in archive: a map and a list on a placemat (PKIW issue 224).
 *
 * The plugin owns the data and the markup. Its Check-ins Feed block, in
 * archive mode, prints the page's check-ins as a linked list and a Leaflet
 * map of the ones whose coordinates a visitor may see, and it sets the
 * archive to 24 a page because patterns/cr-checkin-archive-loop.php places
 * the block. This file loads the paper, assets/css/cr-checkin-archive.css.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\CheckinArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the placemat on the archive (and in the editor, where the template
 * is edited).
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! \is_tax( 'kind', 'checkin' ) ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-checkin-archive',
		COURTNEYR_CHILD_URI . '/assets/css/cr-checkin-archive.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
