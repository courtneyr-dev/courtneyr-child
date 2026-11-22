<?php
/**
 * Single posts whose record needs the wide column.
 *
 * templates/single.html sets every single in WordPress's constrained layout:
 * the post content is capped at theme.json's contentSize (720px) and the
 * header's children are capped the same way. A kind whose single is a
 * two-column spread (a book beside its notes and record) cannot fit there.
 *
 * WordPress's own answer is `align: wide` on the content block, with a
 * contentSize of 100% on its inner layout so the spread takes the wide size
 * (theme.json wideSize, 1100px) and the header's children start at the same
 * left edge. render_block_data sets those two block attributes before the
 * blocks render, so core's layout engine writes the classes and the
 * container rules itself. Nothing else changes: the template, the sparkle's
 * anchor, every other kind and every post that lacks the record (locked,
 * rowless) render the HTML they always did.
 *
 * To give another kind the same column, add `kind slug => predicate` to
 * KINDS; the predicate says whether this post's content carries the record.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleWideColumn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Kind slug => callable( \WP_Post ): bool that says the content carries the record. */
const KINDS = array(
	'read' => '\\Courtneyr\\Child\\SingleRead\\carries_record',
);

/**
 * Does this request show a single whose content takes the wide column?
 *
 * @return bool
 */
function applies(): bool {
	static $memo = array();
	if ( ! \is_singular( 'post' ) ) {
		return false;
	}
	$post = \get_post( \get_queried_object_id() );
	if ( ! $post instanceof \WP_Post || \post_password_required( $post ) ) {
		return false;
	}
	if ( ! isset( $memo[ $post->ID ] ) ) {
		$memo[ $post->ID ] = false;
		foreach ( KINDS as $kind => $carries_record ) {
			if ( \has_term( $kind, 'kind', $post ) && \is_callable( $carries_record ) && $carries_record( $post ) ) {
				$memo[ $post->ID ] = true;
				break;
			}
		}
	}
	return $memo[ $post->ID ];
}

/**
 * Widen the single template's post content and header before they render.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return array<string, mixed>
 */
function widen( array $block ): array {
	$name    = (string) ( $block['blockName'] ?? '' );
	$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
	$content = 'core/post-content' === $name && in_array( 'single-post__content', $classes, true );
	$header  = 'core/group' === $name && in_array( 'single-post__header', $classes, true );
	if ( ! ( $content || $header ) || ! applies() ) {
		return $block;
	}
	if ( $content ) {
		$block['attrs']['align']     = 'wide';
		$block['attrs']['className'] = trim( implode( ' ', $classes ) . ' cr-wide-column' );
	}
	$layout = (array) ( $block['attrs']['layout'] ?? array( 'type' => 'constrained' ) );
	// The content fills the wide column; the header keeps its reading-width lines (a title wraps where it always did) from the column's left edge.
	$block['attrs']['layout'] = array_merge(
		$layout,
		$content ? array( 'contentSize' => '100%' ) : array( 'contentSize' => 'var(--wp--style--global--content-size)', 'justifyContent' => 'left' )
	);
	return $block;
}
add_filter( 'render_block_data', __NAMESPACE__ . '\\widen' );

/**
 * Mark the header group, whose markup the template stores, so cr-wide-column.css can find it.
 *
 * @param string               $html  Rendered group.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function mark_header( string $html, array $block ): string {
	$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
	if ( ! in_array( 'single-post__header', $classes, true ) || ! applies() ) {
		return $html;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag() ) {
		$tags->add_class( 'cr-wide-column' );
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block_core/group', __NAMESPACE__ . '\\mark_header', 10, 2 );

/** Paint the wide column only where it renders. */
function enqueue_styles(): void {
	if ( applies() ) {
		\wp_enqueue_style(
			'courtneyr-wide-column',
			COURTNEYR_CHILD_URI . '/assets/css/cr-wide-column.css',
			array(),
			COURTNEYR_CHILD_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_styles' );
