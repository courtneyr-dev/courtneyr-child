<?php
/**
 * Eat and drink archives: menus (PKIW issue 230).
 *
 * The plugin owns the data and the markup: its Recent Specials block lists
 * the newest posts, its menu entry prints each post as a menu line, the
 * Post Template holds each cuisine or drink type in a section, and it orders
 * the archive query by that group so pagination stays native. It groups
 * the query because templates/taxonomy-kind-eat.html and -drink.html place
 * the menu entry, through patterns/cr-menu-eat-loop.php and -drink-loop.php.
 *
 * The menu entry in each pattern says how many lines a page holds (six for
 * eat, eight for drink), so that number is edited in the Site Editor. This
 * file loads the paper, assets/css/cr-menu.css.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The kinds whose archive is a menu.
 */
const KINDS = array( 'eat', 'drink' );

/**
 * The menu kind whose archive is being served, or ''.
 */
function menu_kind(): string {
	foreach ( KINDS as $kind ) {
		if ( \is_tax( 'kind', $kind ) ) {
			return $kind;
		}
	}
	return '';
}

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
