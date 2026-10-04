<?php
/**
 * Eat and drink archives: menus (PKIW issue 230).
 *
 * The plugin owns the data and the markup: its Recent Specials block lists
 * the newest posts, its menu entry prints each post as a menu line with a
 * section heading when the cuisine or drink type changes, and it orders
 * the archive query by that group so pagination stays native. It groups
 * the query because templates/taxonomy-kind-eat.html and -drink.html place
 * the menu entry, through patterns/cr-menu-eat-loop.php and -drink-loop.php.
 *
 * This file sets how many lines a page holds and loads the paper,
 * assets/css/cr-menu.css.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lines on a menu page, by kind: what the approved menus show.
 */
const LINES = array(
	'eat'   => 6,
	'drink' => 8,
);

/**
 * The kind of the menu archive being shown, or '' when the request isn't one.
 *
 * @return string
 */
function menu_kind(): string {
	foreach ( array_keys( LINES ) as $kind ) {
		if ( \is_tax( 'kind', $kind ) ) {
			return $kind;
		}
	}
	return '';
}

/**
 * Fill the menu page: the eat and drink archives page by LINES.
 *
 * Runs after the theme's card-grid rule (5 per archive page).
 *
 * @param \WP_Query $query The query about to run.
 * @return void
 */
function menu_page_size( $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	foreach ( LINES as $kind => $lines ) {
		if ( $query->is_tax( 'kind', $kind ) ) {
			$query->set( 'posts_per_page', $lines );
		}
	}
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\menu_page_size', 11 );

/**
 * The Site Editor previews each menu with the same number of lines.
 *
 * @param int    $per_page Posts per page. Zero keeps the editor's own size.
 * @param string $kind     Kind slug.
 * @return int
 */
function preview_page_size( $per_page, $kind ): int {
	return LINES[ $kind ] ?? (int) $per_page;
}
add_filter( 'pkiw_kind_archive_preview_per_page', __NAMESPACE__ . '\\preview_page_size', 10, 2 );

/**
 * Load the menu paper on the two archives (and in the editor, where the
 * templates are edited).
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && '' === menu_kind() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-menu',
		COURTNEYR_CHILD_URI . '/assets/css/cr-menu.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
