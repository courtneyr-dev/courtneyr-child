<?php
/**
 * Craft single integration (PKIW issue 249).
 *
 * The H1 stays in the template header, and this file adds no single render
 * filter. The craft layout belongs to the stylesheet added in the next lane.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleCraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mark craft singles for their stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if (
		function_exists( '\\Courtneyr\\Child\\Craft\\is_craft_single' )
		&& \Courtneyr\Child\Craft\is_craft_single()
		&& ! in_array( 'cr-craft-single', $classes, true )
	) {
		$classes[] = 'cr-craft-single';
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Let the shared featured-image filter suppress repeated craft covers.
 *
 * @param mixed $kinds Kind slugs registered so far.
 * @return mixed
 */
function repeat_kinds( $kinds ) {
	if ( ! is_array( $kinds ) ) {
		return $kinds;
	}
	if ( ! in_array( 'craft', $kinds, true ) ) {
		$kinds[] = 'craft';
	}

	return $kinds;
}
add_filter( 'courtneyr_child_featured_repeat_kinds', __NAMESPACE__ . '\\repeat_kinds' );

/**
 * Print the cover's height-to-width ratio for the wide craft layout.
 *
 * At 64rem and wider the Facts paper shares the cover's grid area and starts
 * one cover height down, so the middle column's rows never wait for the cover.
 * The ratio comes from the size the template's featured image prints.
 */
function cover_ratio_style(): void {
	if ( ! function_exists( '\\Courtneyr\\Child\\Craft\\is_craft_single' ) || ! \Courtneyr\Child\Craft\is_craft_single() ) {
		return;
	}

	$thumbnail_id = (int) get_post_thumbnail_id( get_queried_object_id() );
	$source       = $thumbnail_id ? wp_get_attachment_image_src( $thumbnail_id, 'post-thumbnail' ) : false;
	if ( ! is_array( $source ) || empty( $source[1] ) || empty( $source[2] ) ) {
		return;
	}

	$ratio = number_format( (int) $source[2] / (int) $source[1], 4, '.', '' );
	wp_add_inline_style( \Courtneyr\Child\Craft\STYLE_HANDLE, 'body.cr-craft-single{--craft-cover-ratio:' . $ratio . ';}' );
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\cover_ratio_style', 20 );
