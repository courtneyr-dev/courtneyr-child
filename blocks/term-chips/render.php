<?php
/**
 * courtneyr/term-chips — server render.
 *
 * Renders Core's post-terms block for the loop post and lets the theme's
 * existing render filter (split_term_chips) turn it into per-term chips, so
 * the editor preview and the front end share one resolver and one markup.
 *
 * @var array    $attributes Block attributes (term, fontSize, className).
 * @var string   $content    Inner content (none).
 * @var WP_Block $block      Block instance with Query Loop context.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cr_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
if ( ! $cr_post_id ) {
	return;
}
$cr_attrs = array(
	'term'      => (string) ( $attributes['term'] ?? 'category' ),
	'className' => trim( 'cr-term-chips ' . (string) ( $attributes['className'] ?? '' ) ),
);
if ( ! empty( $attributes['fontSize'] ) ) {
	$cr_attrs['fontSize'] = (string) $attributes['fontSize'];
}
echo render_block( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core block output.
	array(
		'blockName'    => 'core/post-terms',
		'attrs'        => $cr_attrs,
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	),
	array(
		'postId'   => $cr_post_id,
		'postType' => (string) ( $block->context['postType'] ?? get_post_type( $cr_post_id ) ),
	)
);
