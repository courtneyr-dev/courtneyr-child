<?php
/**
 * Recipes (PKIW #229): a ring-binder archive of recipe cards.
 *
 * WP Recipe Maker owns the recipe and its card when it runs; Post Kinds
 * reads the picture, course and time from it as a page renders and falls
 * back to the post for a recipe with no recipe card. This file decides
 * what the archive shows:
 *
 *   Archive  four recipe cards to a page, two on each leaf of the binder.
 *            A card is the picture, the h2 title link and the course and
 *            time. The badge, kind label, date, excerpt and "Read more"
 *            are cut from the markup and stay off the archive. Course tabs
 *            and the A-Z tab are links to the same archive, filtered or
 *            reordered by ordinary query vars, so the pager keeps them.
 *
 * assets/css/cr-recipe.css paints it.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Recipe;

use function Courtneyr\Child\MediaShelf\cut_elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recipes per archive page: two cards on each leaf of the open binder.
 */
const BINDER_SIZE = 4;

/**
 * Is this the recipe archive?
 *
 * @return bool
 */
function is_binder(): bool {
	return \is_tax( 'kind', 'recipe' );
}

/**
 * Fill the binder: the recipe archive pages by BINDER_SIZE.
 *
 * Runs after the theme's card-grid rule (5 per archive page), which would
 * leave a fifth card with no place on the spread.
 *
 * @param \WP_Query $query The query about to run.
 * @return void
 */
function binder_page_size( $query ): void {
	if ( ! is_admin() && $query->is_main_query() && $query->is_tax( 'kind', 'recipe' ) ) {
		$query->set( 'posts_per_page', BINDER_SIZE );
	}
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\binder_page_size', 11 );

/**
 * Load the recipe paint where a recipe can appear.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_binder() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-recipe',
		COURTNEYR_CHILD_URI . '/assets/css/cr-recipe.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Reduce a recipe to its binder card on the archive.
 *
 * What stays is the picture, the h2 title link and the course and time
 * line the plugin prints from the recipe. The stylesheet stretches the
 * title link over the card, so the card has one link and one name.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param \WP_Block|null       $instance Block instance.
 * @return string
 */
function binder_card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- filter signature.
	static $printed = 0;

	if ( ! is_binder() || false === strpos( $html, 'pk-card' ) ) {
		return $html;
	}

	$cuts = array(
		array( 'div', 'pk-badge' ),
		array( 'span', 'pk-kindlabel' ),
		array( 'p', 'pk-stream-date' ),
		array( 'p', 'pk-excerpt' ),
		array( 'div', 'pk-meta' ),
	);
	foreach ( $cuts as $cut ) {
		$html = cut_elements( $html, $cut[0], $cut[1] );
	}

	$has_picture = false !== strpos( $html, 'pk-media' );
	$classes     = 'cr-recipe-card ' . ( $has_picture ? 'has-picture' : 'cr-recipe-card--no-picture' );
	$html        = (string) preg_replace( '/(<article\b[^>]*\bclass=")/', '$1' . $classes . ' ', $html, 1 );

	// The top card of each leaf is above the fold: load its picture now.
	if ( $has_picture && $printed < 2 && ! is_paged() ) {
		$html = (string) preg_replace( '/(<img\b[^>]*?)\sloading="lazy"/', '$1 loading="eager" fetchpriority="high"', $html, 1 );
	}
	++$printed;

	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\binder_card', 10, 3 );

/**
 * The binder's tabs: every recipe, each course in use, and the A-Z index.
 *
 * Each tab is a link to the recipe archive. A course tab carries the
 * plugin's course query var and the A-Z tab carries WordPress's own
 * `orderby=title`, so the view a tab opens is an ordinary archive URL.
 *
 * @return string Navigation markup, or '' when the plugin can't list courses.
 */
function tabs(): string {
	$term = get_queried_object();
	if ( ! $term instanceof \WP_Term || ! function_exists( '\\PKIW\\recipe_archive_courses' ) ) {
		return '';
	}
	$base = get_term_link( $term );
	if ( is_wp_error( $base ) ) {
		return '';
	}

	$course   = sanitize_title( (string) get_query_var( 'pkiw_recipe_course' ) );
	$orderby  = get_query_var( 'orderby' );
	$by_title = '' === $course && ( 'title' === $orderby || ( is_array( $orderby ) && isset( $orderby['title'] ) ) );

	$items = array(
		array(
			'label'   => __( 'All', 'courtneyr-child' ),
			'url'     => $base,
			'current' => '' === $course && ! $by_title,
		),
	);
	foreach ( \PKIW\recipe_archive_courses() as $entry ) {
		$items[] = array(
			'label'   => $entry['name'],
			'url'     => $entry['url'],
			'current' => $entry['current'],
		);
	}
	$items[] = array(
		'label'   => __( 'A–Z index', 'courtneyr-child' ),
		'url'     => add_query_arg(
			array(
				'orderby' => 'title',
				'order'   => 'asc',
			),
			$base
		),
		'current' => $by_title,
	);

	$out = '<nav class="cr-recipe-tabs" aria-label="' . esc_attr__( 'Recipe courses', 'courtneyr-child' ) . '"><ul class="cr-recipe-tabs__list">';
	foreach ( $items as $item ) {
		$out .= '<li class="cr-recipe-tabs__item"><a class="cr-recipe-tabs__tab" href="' . esc_url( $item['url'] ) . '"'
			. ( $item['current'] ? ' aria-current="page"' : '' ) . '>' . esc_html( $item['label'] ) . '</a></li>';
	}

	return $out . '</ul></nav>';
}

/**
 * Put the tabs inside the binder's Query block, ahead of the cards.
 *
 * The tabs depend on the request (which tab is current), so they are
 * rendered here and not written into the pattern.
 *
 * @param string               $html  Rendered Query block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function add_tabs( string $html, array $block ): string {
	if ( ! is_binder() || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'cr-recipe-binder__query' ) ) {
		return $html;
	}

	$tabs = tabs();

	return (string) preg_replace_callback(
		'/^\s*<div\b[^>]*>/',
		static fn( array $open ): string => $open[0] . $tabs,
		$html,
		1
	);
}
add_filter( 'render_block_core/query', __NAMESPACE__ . '\\add_tabs', 10, 2 );
