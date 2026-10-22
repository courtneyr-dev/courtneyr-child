<?php
/**
 * Quote archive commonplace-book presentation.
 *
 * The plugin owns quote grouping and card markup. This file registers the
 * theme's block style, adds deterministic paper and strip colours without
 * changing card content, and loads the book stylesheet where it can render.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\QuoteBook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'quote';
const STYLE_HANDLE = 'courtneyr-quote-book';
const STYLE_SLUG   = 'cr-commonplace';
const STRIPS       = array(
	'var(--cr-periwinkle)',
	'var(--cr-sky-blue)',
	'var(--cr-light-orange)',
	'var(--cr-selective-yellow)',
	'#f3e5b8',
	'var(--cr-light-gray)',
	'var(--cr-blue-green)',
);
const EMPTY_STRIP  = 'var(--cr-light-gray)';

/** Register the commonplace-book style when the plugin block is available. */
function register_block_styles(): void {
	if (
		! function_exists( 'register_block_style' )
		|| ! class_exists( '\\WP_Block_Type_Registry' )
		|| ! \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/quote-entry' )
	) {
		return;
	}

	register_block_style(
		'post-kinds-indieweb/quote-entry',
		array(
			'name'  => STYLE_SLUG,
			'label' => __( 'Commonplace book', 'courtneyr-child' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles', 20, 1 );

/**
 * Append one custom property to the processor's current tag.
 *
 * @param \WP_HTML_Tag_Processor $tags  Processor on the target tag.
 * @param string                 $name  Custom-property name.
 * @param string                 $value Custom-property value.
 */
function append_property( \WP_HTML_Tag_Processor $tags, string $name, string $value ): void {
	$style = trim( (string) $tags->get_attribute( 'style' ) );
	if ( '' !== $style && ! str_ends_with( $style, ';' ) ) {
		$style .= ';';
	}
	$tags->set_attribute( 'style', $style . $name . ':' . $value );
}

/**
 * Put the single and Stream paper on one commonplace-book card.
 *
 * @param string               $html     Rendered quote entry.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance with Query Loop context.
 * @return string
 */
function entry_paper( string $html, array $block, $instance ): string {
	if ( '' === $html || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	$tokens     = explode( ' ', str_replace( array( "\t", "\n", "\r", "\f" ), ' ', $class_name ) );
	if ( ! in_array( 'is-style-' . STYLE_SLUG, $tokens, true ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if (
		! $tags->next_tag(
			array(
				'tag_name'   => 'div',
				'class_name' => 'pkiw-quote-entry',
			)
		)
		|| $tags->has_class( 'pkiw-quote-entry--continued' )
	) {
		return $html;
	}

	$post_id = $instance instanceof \WP_Block && ! empty( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = get_post( $post_id );
	$paper   = 'var(--cr-printer-ivory)';
	if (
		$post instanceof \WP_Post
		&& ! post_password_required( $post )
		&& function_exists( '\\Courtneyr\\Child\\QuoteNote\\paper_for' )
	) {
		$paper = \Courtneyr\Child\QuoteNote\paper_for( $post );
	}

	$tags->add_class( 'is-style-' . STYLE_SLUG );
	append_property( $tags, '--cr-quote-paper', $paper );
	return $tags->get_updated_html();
}
add_filter( 'render_block_post-kinds-indieweb/quote-entry', __NAMESPACE__ . '\\entry_paper', 10, 3 );

/**
 * Return the decorative strip selected by a stored-value slug.
 *
 * @param string $slug Stored group or work slug.
 * @return string
 */
function strip_for( string $slug ): string {
	if ( '' === $slug ) {
		return EMPTY_STRIP;
	}

	$index = ( crc32( $slug ) & 0xffffffff ) % count( STRIPS );
	return STRIPS[ $index ];
}

/**
 * Add deterministic strip colours to plugin section and work wrappers.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function strip_seeds( string $html, array $block ): string {
	if ( '' === $html || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$block_name = (string) ( $block['blockName'] ?? '' );
	if ( 'core/post-template' === $block_name ) {
		$probe = new \WP_HTML_Tag_Processor( $html );
		if (
			! $probe->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'pkiw-quote--sectioned',
				)
			)
		) {
			return $html;
		}

		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'section' ) ) {
			$slug = $tags->get_attribute( 'data-pkiw-group' );
			if ( null === $slug ) {
				continue;
			}
			append_property( $tags, '--cr-strip', strip_for( (string) $slug ) );
		}
		return $tags->get_updated_html();
	}

	if ( 'post-kinds-indieweb/quote-works' === $block_name ) {
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'li' ) ) {
			$slug = $tags->get_attribute( 'data-pkiw-work' );
			if ( null === $slug ) {
				continue;
			}
			append_property( $tags, '--cr-strip', strip_for( (string) $slug ) );
		}
		return $tags->get_updated_html();
	}

	return $html;
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\strip_seeds', 10, 2 );
add_filter( 'render_block_post-kinds-indieweb/quote-works', __NAMESPACE__ . '\\strip_seeds', 10, 2 );

/** Load the book paint on the quote archive and in the editor. */
function enqueue_styles(): void {
	if ( ! is_admin() && ! \is_tax( 'kind', KIND ) ) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-quote-book.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles', 10, 1 );

/**
 * Keep the theme and plugin quote sheets out of Remove Unused CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return array<int, mixed>
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-quote-book.css';
	$exclusions[] = 'quote-archive.css';
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20, 1 );
