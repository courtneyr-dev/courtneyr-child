<?php
/**
 * Wish single integration (PKIW issue 238).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleWish;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mark wish singles for their stylesheet.
 *
 * @param array $classes Body classes.
 */
function body_class( array $classes ): array {
	if ( function_exists( '\\Courtneyr\\Child\\Wish\\is_wish_single' ) && \Courtneyr\Child\Wish\is_wish_single() && ! in_array( 'cr-wish-single', $classes, true ) ) {
		$classes[] = 'cr-wish-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Give the single's photo panel the shared failed-image swap (site-ux.js).
 *
 * The tile repeats the H1, so it stays aria-hidden.
 *
 * @param string $html     Rendered wish card.
 * @param array  $block    Parsed block.
 * @param mixed  $instance Block instance.
 */
function photo_fallback( string $html, array $block, $instance ): string {
	unset( $block );
	if ( '' === $html || false === strpos( $html, 'pk-wish-photo' ) || false !== strpos( $html, 'data-cr-cover-fallback' ) ) {
		return $html;
	}
	if ( ! function_exists( '\\Courtneyr\\Child\\Wish\\is_wish_single' ) || ! \Courtneyr\Child\Wish\is_wish_single() ) {
		return $html;
	}
	$title = trim( wp_strip_all_tags( (string) get_the_title( \Courtneyr\Child\Wish\block_post_id( $instance ) ) ) );
	$tile  = \Courtneyr\Child\Wish\typographic_tile( '' !== $title ? $title : __( 'Wish', 'courtneyr-child' ) );
	$out   = preg_replace_callback(
		'#(<div class="pk-media pk-wish-photo")(>.*?)(</div>)#s',
		static function ( array $m ) use ( $tile ): string {
			return $m[1] . ' data-cr-cover-fallback' . $m[2] . $tile . $m[3];
		},
		$html,
		1
	);
	return is_string( $out ) ? $out : $html;
}
add_filter( 'render_block_post-kinds-indieweb/wish-card', __NAMESPACE__ . '\\photo_fallback', 10, 3 );

/**
 * Register wish with the shared featured-repeat filter.
 *
 * @param mixed $kinds Kind slugs.
 */
function repeat_kinds( $kinds ) {
	if ( ! is_array( $kinds ) ) {
		return $kinds;
	}
	if ( ! in_array( 'wish', $kinds, true ) ) {
		$kinds[] = 'wish';
	}
	return $kinds;
}
add_filter( 'courtneyr_child_featured_repeat_kinds', __NAMESPACE__ . '\\repeat_kinds' );

/**
 * Suppress a featured image already rendered by the wish card.
 *
 * @param bool  $repeats Existing repeat result.
 * @param mixed $post    Post object.
 */
function featured_repeats( bool $repeats, $post ): bool {
	if ( $repeats || ! $post instanceof \WP_Post || ! has_term( 'wish', 'kind', $post ) || ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return $repeats;
	}
	$facts   = \PKIW\kind_facts( $post->ID );
	$picture = is_array( $facts ) && is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array();
	return ! empty( $picture['suppress_featured'] );
}
add_filter( 'courtneyr_child_featured_repeats', __NAMESPACE__ . '\\featured_repeats', 10, 2 );

/**
 * Register the wish single class with editor parity.
 *
 * @param array $kinds Editor parity map.
 */
function register_editor_parity( array $kinds ): array {
	$classes = isset( $kinds['wish']['classes'] ) && is_array( $kinds['wish']['classes'] )
		? $kinds['wish']['classes']
		: array( 'single-post', 'kind-wish' );
	if ( ! in_array( 'cr-wish-single', $classes, true ) ) {
		$classes[] = 'cr-wish-single';
	}
	$kinds['wish']['classes'] = $classes;
	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );
