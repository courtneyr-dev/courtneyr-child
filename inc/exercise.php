<?php
/**
 * Exercise archive wiring and shared presentation hooks (issue 245).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Exercise;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'exercise';
const STYLE_HANDLE = 'courtneyr-exercise';

/** Whether this request is the exercise kind archive. */
function is_exercise_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single exercise post. */
function is_exercise_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/** Attach the exercise sheet to its calendar block after the shared calendar sheet. */
function register_archive_style(): void {
	$file = COURTNEYR_CHILD_DIR . '/assets/css/cr-exercise.css';
	if ( ! is_readable( $file ) ) {
		return;
	}

	wp_enqueue_block_style(
		'post-kinds-indieweb/exercise-entry',
		array(
			'handle' => STYLE_HANDLE,
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-exercise.css',
			'path'   => $file,
			'ver'    => COURTNEYR_CHILD_VERSION,
			'deps'   => array( 'courtneyr-calendar' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_archive_style', 10 );

/** Enqueue the exercise sheet without calendar dependencies on non-archive surfaces. */
function enqueue_surface_style(): void {
	if ( ! is_admin() && ! is_exercise_single() && ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-exercise.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_surface_style' );

/** Register the editor-facing exercise block styles when the plugin blocks exist. */
function register_block_styles(): void {
	if ( ! function_exists( 'register_block_style' ) || ! class_exists( '\\WP_Block_Type_Registry' ) ) {
		return;
	}

	$registry = \WP_Block_Type_Registry::get_instance();
	if ( $registry->is_registered( 'post-kinds-indieweb/exercise-entry' ) ) {
		register_block_style(
			'post-kinds-indieweb/exercise-entry',
			array(
				'name'       => 'cr-bib',
				'label'      => __( 'Race bib', 'courtneyr-child' ),
				'is_default' => true,
			)
		);
	}
	if ( $registry->is_registered( 'post-kinds-indieweb/medal-row' ) ) {
		register_block_style(
			'post-kinds-indieweb/medal-row',
			array(
				'name'       => 'cr-medals',
				'label'      => __( 'Medal rack', 'courtneyr-child' ),
				'is_default' => true,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles' );

/**
 * Add the exercise archive body class.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_exercise_archive() && ! in_array( 'cr-archive--exercise', $classes, true ) ) {
		$classes[] = 'cr-archive--exercise';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Give each archive bib a stable palette-paper class.
 *
 * @param string[] $classes   Post classes.
 * @param string[] $css_class Extra requested classes (unused).
 * @param int      $post_id   Post ID.
 * @return string[]
 */
function bib_paper_class( array $classes, array $css_class, int $post_id ): array {
	unset( $css_class );

	if (
		! is_exercise_archive()
		|| 'post' !== get_post_type( $post_id )
		|| ! has_term( KIND, 'kind', $post_id )
		|| ! function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' )
		|| ! defined( 'Courtneyr\\Child\\Stamps\\BIB_PAPERS' )
	) {
		return $classes;
	}

	$paper = \Courtneyr\Child\Stamps\seeded_paper( $post_id, \Courtneyr\Child\Stamps\BIB_PAPERS );
	$class = '' !== $paper ? 'cr-bib--' . $paper : '';
	if ( '' !== $class && ! in_array( $class, $classes, true ) ) {
		$classes[] = $class;
	}

	return $classes;
}
add_filter( 'post_class', __NAMESPACE__ . '\\bib_paper_class', 10, 3 );
