<?php
/**
 * Exercise single presentation hooks (issue 245).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleExercise;

use Courtneyr\Child\Exercise;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the exercise single body class.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( Exercise\is_exercise_single() && ! in_array( 'cr-single--exercise', $classes, true ) ) {
		$classes[] = 'cr-single--exercise';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Mirror the exercise single classes in the editor canvas.
 *
 * @param array<string, array{classes?: array<int, string>}> $kinds Registry so far.
 * @return array<string, array{classes?: array<int, string>}>
 */
function register_editor_parity( array $kinds ): array {
	$classes = isset( $kinds[ Exercise\KIND ]['classes'] ) && is_array( $kinds[ Exercise\KIND ]['classes'] )
		? $kinds[ Exercise\KIND ]['classes']
		: array( 'single-post', 'kind-' . Exercise\KIND );
	if ( ! in_array( 'cr-single--exercise', $classes, true ) ) {
		$classes[] = 'cr-single--exercise';
	}
	$kinds[ Exercise\KIND ]['classes'] = $classes;

	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );
