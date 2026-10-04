<?php
/**
 * Recipes (PKIW #229): a ring-binder archive, a binder-page single and a
 * 3x5 card on the Stream.
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
 *   Single   one sheet of binder paper behind the post header, the
 *            featured image and the content. The recipe plugin's card is
 *            the theme's cr-binder template, without the name or picture
 *            the page already shows. A recipe post with no recipe card
 *            keeps the default single.
 *   Stream   a 3x5 card: picture, kind label, h2 title link, course and
 *            time, "Read more". No date, no excerpt.
 *
 * assets/css/cr-recipe.css paints it.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Recipe;

use function Courtneyr\Child\HomeSections\is_stream_surface;
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
 * Block style the binder's pattern gives its stream card.
 *
 * The editor asks the server for each card on its own, with no archive
 * query behind the request. The style travels with the block, so the card
 * is cut the same way in the Site Editor as on the archive.
 */
const BINDER_CARD_STYLE = 'is-style-cr-binder-card';

/**
 * Is this stream card one of the binder's?
 *
 * @param array<string, mixed> $block Parsed stream-card block.
 * @return bool
 */
function is_binder_card( array $block ): bool {
	return is_binder() || false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), BINDER_CARD_STYLE );
}

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
				'name'  => 'cr-binder-card',
				'label' => __( 'Recipe binder card', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style' );

/**
 * The Site Editor previews the recipe archive BINDER_SIZE recipes to a page.
 *
 * @param int    $per_page Posts per page. Zero keeps the editor's own size.
 * @param string $kind     Kind slug.
 * @return int
 */
function preview_page_size( $per_page, $kind ): int {
	return 'recipe' === $kind ? BINDER_SIZE : (int) $per_page;
}
add_filter( 'pkiw_kind_archive_preview_per_page', __NAMESPACE__ . '\\preview_page_size', 10, 2 );

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
	if ( ! is_admin() && ! is_binder() && ! is_recipe_single() && ! is_stream_surface() ) {
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

	if ( ! is_binder_card( $block ) || false === strpos( $html, 'pk-card' ) ) {
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
 * A recipe on the Stream: a 3x5 card.
 *
 * What stays is the picture, the kind label, the h2 title link, the course
 * and time line and the "Read more" link. The date and the excerpt are cut:
 * the card is for scanning, and both are on the single.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param \WP_Block|null       $instance Block instance.
 * @return string
 */
function stream_card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- filter signature.
	if ( is_binder_card( $block ) || ! is_stream_surface() || 1 !== preg_match( '/<article\b[^>]*\bclass="[^"]*(?<![\w-])k-recipe(?![\w-])/', $html ) ) {
		return $html;
	}

	$cuts = array(
		array( 'div', 'pk-badge' ),
		array( 'p', 'pk-stream-date' ),
		array( 'p', 'pk-excerpt' ),
	);
	foreach ( $cuts as $cut ) {
		$html = cut_elements( $html, $cut[0], $cut[1] );
	}

	$classes = 'cr-recipe-stream ' . ( false !== strpos( $html, 'pk-media' ) ? 'has-picture' : 'cr-recipe-stream--no-picture' );

	return (string) preg_replace( '/(<article\b[^>]*\bclass=")/', '$1' . $classes . ' ', $html, 1 );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stream_card', 10, 3 );

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
