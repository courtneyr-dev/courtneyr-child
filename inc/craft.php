<?php
/**
 * Craft archive cards and shared craft helpers (PKIW issue 249).
 *
 * The plugin owns craft facts, grouping and entry properties. This file
 * registers the pegboard card, enqueues its paint and rebuilds archive items
 * from the plugin facts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Craft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND           = 'craft';
const STYLE_HANDLE   = 'courtneyr-craft';
const PEG_CARD_STYLE = 'is-style-cr-peg-card';

/** Whether this request is the craft pegboard archive. */
function is_pegboard(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single craft post. */
function is_craft_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Whether a stream-card block belongs to the craft pegboard.
 *
 * @param array<string, mixed> $block Parsed stream-card block.
 * @return bool
 */
function is_peg_card( array $block ): bool {
	return is_pegboard() || str_contains( (string) ( $block['attrs']['className'] ?? '' ), PEG_CARD_STYLE );
}

/** Register the pegboard card style for the Site Editor. */
function register_card_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => 'cr-peg-card',
				'label' => __( 'Pegboard card', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style' );

/** Enqueue craft paint only where craft markup can appear. */
function enqueue_styles(): void {
	$is_stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();

	if ( ! is_admin() && ! is_pegboard() && ! is_craft_single() && ! $is_stream ) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-craft.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Mark the craft archive for its stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_pegboard() && ! in_array( 'cr-craft-archive', $classes, true ) ) {
		$classes[] = 'cr-craft-archive';
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Register the craft single class with editor parity.
 *
 * @param array<string, array{classes?: array<int, string>}> $kinds Registry so far.
 * @return array<string, array{classes?: array<int, string>}>
 */
function register_editor_parity( array $kinds ): array {
	$classes = isset( $kinds[ KIND ]['classes'] ) && is_array( $kinds[ KIND ]['classes'] )
		? $kinds[ KIND ]['classes']
		: array( 'single-post', 'kind-' . KIND );
	if ( ! in_array( 'cr-craft-single', $classes, true ) ) {
		$classes[] = 'cr-craft-single';
	}
	$kinds[ KIND ]['classes'] = $classes;

	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );

/**
 * Reduce a craft archive item to its featured picture and linked name.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance with the Query Loop post ID.
 * @return string
 */
function peg_card( string $html, array $block, $instance ): string {
	static $position = 0;

	if ( '' === $html || ! is_peg_card( $block ) || ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( KIND, 'kind', $post ) ) {
		return $html;
	}

	$heading_level = max( 2, min( 4, (int) ( $block['attrs']['headingLevel'] ?? 3 ) ) );
	$facts         = (array) \PKIW\kind_facts( $post->ID );
	$permalink     = esc_url( (string) get_permalink( $post ) );
	$picture       = '';
	$name_class    = 'u-url';

	if ( array() === $facts ) {
		$stored_title = trim( (string) $post->post_title );
		if ( '' !== $stored_title ) {
			$name = get_the_title( $post );
		} elseif ( function_exists( '\\Courtneyr\\Child\\Nav\\kind_date_name' ) ) {
			$name = \Courtneyr\Child\Nav\kind_date_name( $post );
		} else {
			$name = __( 'Craft', 'courtneyr-child' );
		}
	} else {
		$name = (string) ( $facts['name'] ?? '' );
		if ( ! empty( $facts['name_is_title'] ) ) {
			$name_class .= ' p-name';
		}

		$attachment_id = (int) ( $facts['picture']['attachment_id'] ?? 0 );
		if ( 'featured' === ( $facts['picture']['source'] ?? '' ) && 0 < $attachment_id ) {
			$image_attributes = array(
				'alt'   => '',
				'class' => 'cr-peg-card__img',
			);
			if ( $position < 3 ) {
				$image_attributes['loading']       = 'eager';
				$image_attributes['fetchpriority'] = 'high';
			} else {
				$image_attributes['loading']  = 'lazy';
				$image_attributes['decoding'] = 'async';
			}
			$image = wp_get_attachment_image( $attachment_id, 'medium', false, $image_attributes );
			if ( '' !== $image ) {
				$picture = '<figure class="cr-peg-card__cover">' . $image . '</figure>';
			}
		}
	}

	++$position;

	return '<div class="cr-peg-card" data-cr-cover-fallback="true">'
		. $picture
		. '<h' . $heading_level . ' class="cr-peg-card__title"><a class="' . esc_attr( $name_class ) . '" href="' . $permalink . '">'
		. esc_html( (string) $name )
		. '</a></h' . $heading_level . '></div>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\peg_card', 10, 3 );
