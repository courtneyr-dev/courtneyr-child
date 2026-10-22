<?php
/**
 * Collection cabinet registration and shared presentation contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Collection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND          = 'collection';
const STYLE_HANDLE  = 'cr-collection';
const GROUP_CLASS   = 'pkiw-collection';
const CABINET_STYLE = 'cr-cabinet';
const HALL_STYLE    = 'cr-cabinet-hall';
const PAGE_SIZE     = 6;

/** Register the cabinet and cabinet-hall block styles. */
function register_styles(): void {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	register_block_style(
		'core/group',
		array(
			'name'  => CABINET_STYLE,
			'label' => __( 'Cabinet', 'courtneyr-child' ),
		)
	);
	register_block_style(
		'core/post-template',
		array(
			'name'  => HALL_STYLE,
			'label' => __( 'Cabinet hall', 'courtneyr-child' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_styles' );

/** Attach collection paint to both plugin blocks. */
function register_card_style(): void {
	if ( ! defined( 'COURTNEYR_CHILD_DIR' ) || ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) || ! function_exists( 'wp_enqueue_block_style' ) ) {
		return;
	}

	$file_path = COURTNEYR_CHILD_DIR . '/assets/css/cr-collection.css';
	if ( ! is_readable( $file_path ) ) {
		return;
	}

	$style = array(
		'handle' => STYLE_HANDLE,
		'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-collection.css',
		'path'   => $file_path,
		'ver'    => COURTNEYR_CHILD_VERSION,
		'deps'   => array( 'courtneyr-paper', 'courtneyr-nav' ),
	);
	foreach ( array( 'post-kinds-indieweb/collection-item', 'post-kinds-indieweb/stream-card' ) as $block_name ) {
		wp_enqueue_block_style( $block_name, $style );
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style' );

/**
 * Seed the cabinet style when the author did not choose another group style.
 *
 * @param mixed $html  Rendered group markup.
 * @param mixed $block Parsed group block.
 * @return string
 */
function cabinet_default( $html, $block ): string {
	$html = is_string( $html ) ? $html : '';
	if ( '' === $html || ! is_array( $block ) ) {
		return $html;
	}

	$class_name = $block['attrs']['className'] ?? '';
	if ( ! is_string( $class_name ) ) {
		return $html;
	}
	$tokens = array_values( array_filter( array_map( 'trim', explode( ' ', $class_name ) ), 'strlen' ) );
	if ( ! in_array( GROUP_CLASS, $tokens, true ) ) {
		return $html;
	}
	foreach ( $tokens as $token ) {
		if ( str_starts_with( $token, 'is-style-' ) ) {
			return $html;
		}
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag() || ! $tags->has_class( GROUP_CLASS ) ) {
		return $html;
	}
	$tags->add_class( 'is-style-' . CABINET_STYLE );
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/group', __NAMESPACE__ . '\\cabinet_default', 11, 2 );

/**
 * Set the collection archive page size.
 *
 * @param array<string, int> $sizes Kind page sizes.
 * @return array<string, int>
 */
function page_size( array $sizes ): array {
	$sizes[ KIND ] = PAGE_SIZE;
	return $sizes;
}
add_filter( 'courtneyr_child_kind_archive_page_sizes', __NAMESPACE__ . '\\page_size' );

/**
 * Add collection single classes to the editor canvas.
 *
 * @param array<string, array{classes?: array<int, string>}> $kinds Kind registry.
 * @return array<string, array{classes?: array<int, string>}>
 */
function register_editor_parity( array $kinds ): array {
	$classes = isset( $kinds[ KIND ]['classes'] ) && is_array( $kinds[ KIND ]['classes'] )
		? array_values( array_unique( $kinds[ KIND ]['classes'] ) )
		: array();
	foreach ( array( 'single-post', 'kind-' . KIND ) as $class_name ) {
		if ( ! in_array( $class_name, $classes, true ) ) {
			$classes[] = $class_name;
		}
	}
	$kinds[ KIND ]['classes'] = $classes;
	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );

/**
 * Keep both collection stylesheets out of Remove Unused CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return array<int, mixed>
 */
function rucss( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-collection.css';
	$exclusions[] = 'collection-item.css';
	return array_values( array_unique( $exclusions, SORT_REGULAR ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss', 11 );
