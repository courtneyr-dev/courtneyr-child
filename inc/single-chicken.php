<?php
/**
 * Chicken single: a coop chalkboard (PKIW issue 250).
 *
 * The board holds the date, the H1, the picture and the post content, in that
 * order. The template's own header group and featured image are dropped by
 * the shared W3-T0 filter once this kind says the single object holds the H1.
 * The plugin keeps the h-entry wrapper around the content; this file adds no
 * microformats class.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleChicken;

use Courtneyr\Child\Chicken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Say that the chicken board holds the single's H1.
 *
 * @param mixed    $holds Whether an earlier callback claimed the H1.
 * @param \WP_Post $post  Single post.
 * @return mixed
 */
function holds_h1( $holds, $post = null ) {
	if ( $post instanceof \WP_Post && has_term( Chicken\KIND, 'kind', $post ) ) {
		return true;
	}
	return $holds;
}
add_filter( 'courtneyr_child_single_object_holds_h1', __NAMESPACE__ . '\\holds_h1', 10, 2 );

/**
 * Whether the block instance belongs to the queried post.
 *
 * @param mixed $instance Block instance.
 * @return bool
 */
function matches_queried_post( $instance ): bool {
	if ( $instance instanceof \WP_Block && isset( $instance->context['postId'] ) ) {
		return (int) get_queried_object_id() === (int) $instance->context['postId'];
	}
	return true;
}

/**
 * Render a Core block for the queried post.
 *
 * @param string               $name  Block name.
 * @param array<string, mixed> $attrs Block attributes.
 * @return string
 */
function core_block( string $name, array $attrs ): string {
	return (string) render_block(
		array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}

/**
 * The H1 for a post with no title: kind, date and time, as plain text.
 *
 * No link and no p-name: a synthetic name is navigation, not the entry's name.
 *
 * @param \WP_Post             $post  Single post.
 * @param array<string, mixed> $facts Chicken facts, [] when the plugin shows none.
 * @return string
 */
function untitled_h1( \WP_Post $post, array $facts ): string {
	$label = trim( (string) ( $facts['kind_label'] ?? '' ) );
	if ( '' === $label ) {
		$label = __( 'Chicken', 'courtneyr-child' );
	}
	/* translators: 1: post kind name, 2: post date, 3: post time. */
	$name = sprintf( __( '%1$s, %2$s at %3$s', 'courtneyr-child' ), $label, get_the_date( 'F j, Y', $post ), get_the_time( '', $post ) );

	return '<h1 class="wp-block-post-title single-post__title cr-coop__title has-accent-font-family is-untitled">' . esc_html( $name ) . '</h1>';
}

/**
 * The featured picture, when the plugin says the post's picture is the featured image.
 *
 * The shared W3-T0 repeat filter returns '' for the featured-image block when
 * the content shows the same attachment, so the picture prints once. The facts
 * gate comes first because Core's featured-image block doesn't check passwords.
 *
 * @param array<string, mixed> $facts Chicken facts.
 * @return string
 */
function picture( array $facts ): string {
	if ( array() === $facts || 'featured' !== ( $facts['picture']['source'] ?? '' ) ) {
		return '';
	}

	$html = core_block(
		'core/post-featured-image',
		array(
			'sizeSlug'  => 'large',
			'className' => 'cr-coop__photo',
		)
	);
	if ( '' === $html ) {
		return '';
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( 'figure' ) ) {
		$tags->set_attribute( 'data-cr-cover-fallback', 'true' );
		return $tags->get_updated_html();
	}
	return $html;
}

/**
 * Wrap the post content in the board: date, H1, picture, content.
 *
 * @param string               $html     Rendered Post Content block.
 * @param array<string, mixed> $block    Parsed block (unused).
 * @param mixed                $instance Block instance.
 * @return string
 */
function board( string $html, array $block = array(), $instance = null ): string {
	unset( $block );

	if ( ! Chicken\is_chicken_single() || ! matches_queried_post( $instance ) ) {
		return $html;
	}
	$post = get_post( get_queried_object_id() );
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}

	// X16: the theme reads the plugin only through its facts. They are [] for a protected post.
	$facts = function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $post->ID ) : array();

	$date = core_block(
		'core/post-date',
		array(
			'format'    => 'F j, Y',
			'className' => 'cr-coop__date',
		)
	);
	if ( '' === trim( (string) get_post_field( 'post_title', $post->ID, 'raw' ) ) ) {
		$title = untitled_h1( $post, $facts );
	} else {
		$title = core_block(
			'core/post-title',
			array(
				'level'      => 1,
				'fontFamily' => 'accent',
				'className'  => 'single-post__title cr-coop__title',
			)
		);
	}

	return '<div class="cr-coop"><div class="cr-coop__board">' . $date . $title . picture( $facts ) . $html . '</div></div>';
}
add_filter( 'render_block_core/post-content', __NAMESPACE__ . '\\board', 20, 3 );
