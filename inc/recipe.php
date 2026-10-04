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
 * The WP Recipe Maker template this theme ships: wprm-templates/recipe/cr-binder/.
 */
const TEMPLATE = 'cr-binder';

/**
 * The recipe a post embeds, as Post Kinds reads it from the recipe plugin.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>|null Recipe values, or null with no recipe plugin or no recipe card.
 */
function post_recipe( int $post_id ): ?array {
	if ( ! class_exists( '\\PKIW\\Integrations\\WP_Recipe_Maker' ) || ! method_exists( '\\PKIW\\Integrations\\WP_Recipe_Maker', 'get_post_recipe' ) ) {
		return null;
	}

	return \PKIW\Integrations\WP_Recipe_Maker::get_post_recipe( $post_id );
}

/**
 * Is this the single view of a recipe post that holds a recipe card?
 *
 * A recipe post with no card keeps the default single.
 *
 * @return bool
 */
function is_recipe_single(): bool {
	return \is_singular( 'post' ) && \has_term( 'recipe', 'kind', \get_queried_object_id() ) && null !== post_recipe( (int) \get_queried_object_id() );
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
	if ( ! is_admin() && ! is_binder() && ! is_recipe_single() ) {
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

/**
 * Mark the single view of a recipe for the stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_recipe_single() ) {
		$classes[] = 'cr-recipe-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Use the theme's binder template for recipes, unless the site chose one.
 *
 * WP Recipe Maker finds the template in this theme's wprm-templates/
 * folder; this only makes it the default. A template, print template or
 * stylesheet placement the site set in the plugin's settings is kept.
 *
 *  - Printing stays on one of the plugin's own templates: the print page
 *    is the plugin's page, outside this theme and its tokens.
 *  - Template styles print with the recipe, not in the head of every page.
 *
 * @param mixed $settings Stored WP Recipe Maker settings.
 * @return mixed
 */
function recipe_plugin_settings( $settings ) {
	if ( ! is_array( $settings ) ) {
		return $settings;
	}

	$defaults = array(
		'default_recipe_template_modern' => TEMPLATE,
		'default_print_template_modern'  => 'meadow',
		'recipe_templates_in_footer'     => true,
		'snippet_templates_in_footer'    => true,
	);
	foreach ( $defaults as $key => $value ) {
		if ( ! array_key_exists( $key, $settings ) ) {
			$settings[ $key ] = $value;
		}
	}

	return $settings;
}
add_filter( 'wprm_settings', __NAMESPACE__ . '\\recipe_plugin_settings' );

/**
 * The recipe's summary and the plugin's print link, under the H1.
 *
 * The post title is the page heading, so the template leaves the recipe
 * name out. The summary and the print link sit with the title, beside
 * the picture. The print link is the plugin's own shortcode: it builds
 * the URL and decides who may print.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function add_title_lede( string $html, array $block ): string {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || ! is_recipe_single() || get_the_ID() !== get_queried_object_id() ) {
		return $html;
	}
	$recipe = post_recipe( (int) get_the_ID() );
	if ( null === $recipe ) {
		return $html;
	}

	$out     = '';
	$summary = trim( (string) ( $recipe['summary'] ?? '' ) );
	if ( '' !== $summary ) {
		$out .= '<p class="cr-recipe__summary">' . esc_html( $summary ) . '</p>';
	}

	$print = trim( do_shortcode( '[wprm-recipe-print id="' . (int) $recipe['id'] . '" style="button" icon="printer" text_color="var(--cr-russian-violet)" icon_color="var(--cr-russian-violet)" button_color="var(--cr-ut-orange)" border_color="var(--cr-russian-violet)" border_radius="6px" horizontal_padding="18px" vertical_padding="10px" text_style="bold"]' ) );
	if ( '' !== $print ) {
		$out .= '<p class="cr-recipe__actions">' . $print . '</p>';
	}

	return $html . $out;
}
add_filter( 'render_block', __NAMESPACE__ . '\\add_title_lede', 20, 2 );
