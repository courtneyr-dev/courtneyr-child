<?php
/**
 * Single posts whose record needs the wide column.
 *
 * The single template sets every single in WordPress's constrained layout:
 * the post content is capped at theme.json's contentSize (720px) and the
 * header's children are capped the same way. A kind whose single is a
 * two-column spread (a book beside its notes and record) cannot fit there.
 *
 * WordPress's own answer is `align: wide` on the content block, with a
 * contentSize of 100% on its inner layout so the spread takes the wide size
 * (theme.json wideSize, 1100px). render_block_data sets those block
 * attributes before the blocks render, so core's layout engine writes the
 * classes and the container rules itself. A block is widened only when it
 * renders the main query's post, and only when the page of that post it is
 * showing carries the record, so every other kind, locked and rowless reads,
 * later pages of a paged post and secondary loops render the HTML they always
 * did. The kind is the first kind term, the one the `kind-{slug}` body class
 * carries, so this filter and cr-wide-column.css always agree.
 *
 * To give another kind the same column, add `kind slug => predicate` to
 * KINDS; the predicate says whether the page content carries the record.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleWideColumn;

use function Courtneyr\Child\Enqueue\primary_kind_slug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Kind slug => callable( \WP_Post, string ): bool that says the page content carries the record. */
const KINDS = array(
	'read' => '\\Courtneyr\\Child\\SingleRead\\carries_record',
);

/**
 * Does the main singular query show a page whose content takes the wide column?
 *
 * @param bool $rendering True while a block renders: the block must be rendering the queried post.
 * @return bool
 */
function applies( bool $rendering = false ): bool {
	static $memo = array();
	if ( ! \is_singular( 'post' ) ) {
		return false;
	}
	$post = \get_post( \get_queried_object_id() );
	if ( ! $post instanceof \WP_Post || \post_password_required( $post ) ) {
		return false;
	}
	if ( $rendering && ( ! \is_main_query() || ! \in_the_loop() || (int) \get_the_ID() !== $post->ID ) ) {
		return false;
	}
	$page = max( 1, (int) \get_query_var( 'page' ) );
	$key  = $post->ID . ':' . $page;
	if ( ! isset( $memo[ $key ] ) ) {
		$memo[ $key ] = false;
		$kind         = primary_kind_slug( $post->ID );
		$pages        = preg_split( '/<!--nextpage-->/', (string) $post->post_content );
		$content      = is_array( $pages ) ? (string) ( $pages[ $page - 1 ] ?? '' ) : '';
		if ( isset( KINDS[ $kind ] ) && \is_callable( KINDS[ $kind ] ) ) {
			$memo[ $key ] = (bool) ( KINDS[ $kind ] )( $post, $content );
		}
	}
	return $memo[ $key ];
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
	if ( ! ( $content || $header ) || ! applies( true ) ) {
		return $block;
	}
	$layout = (array) ( $block['attrs']['layout'] ?? array( 'type' => 'constrained' ) );
	if ( $content ) {
		// The content fills the wide column.
		$block['attrs']['align']     = 'wide';
		$block['attrs']['className'] = trim( implode( ' ', $classes ) . ' cr-wide-column' );
		$layout['contentSize']       = '100%';
	} else {
		// The header keeps the reading width (a title wraps where it always did); cr-wide-column.css starts it at the column's edge.
		$layout['contentSize'] = 'var(--wp--style--global--content-size)';
	}
	$block['attrs']['layout'] = $layout;
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
	if ( ! in_array( 'single-post__header', $classes, true ) || ! applies( true ) ) {
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
