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

/**
 * Keep the term name as the check-in archive's title when the URL narrows it.
 *
 * Core's get_the_archive_title() tests is_month() before is_tax(), so
 * /kind/checkin/?monthnum=7 titled itself "July 2026" and the cut-paper tiles
 * spelled the month. The month only filters the list; the page is still the
 * Check-in archive. The template's query-title hides the prefix, so the term
 * name is returned bare.
 *
 * @param string $title          Archive title.
 * @param string $original_title Archive title without its prefix.
 * @return string
 */
function term_archive_title( string $title, string $original_title = '' ): string {
	if ( ! \is_tax( 'kind', 'checkin' ) ) {
		return $title;
	}
	$name = (string) \single_term_title( '', false );
	return ( '' === $name || $name === $original_title ) ? $title : $name;
}
add_filter( 'get_the_archive_title', __NAMESPACE__ . '\\term_archive_title', 10, 2 );
