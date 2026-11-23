<?php
/**
 * The wide column of a kind single, decided once per post and page.
 *
 * The single template sets every single in WordPress's constrained layout:
 * the post content is capped at theme.json's contentSize (720px). A kind whose
 * single is a spread or a sheet (a book beside its notes and record) cannot
 * fit there. WordPress's own answer is `align: wide` on the content block with
 * a contentSize of 100% on its inner layout, so the record takes the wide size
 * (theme.json wideSize, 1100px) while prose keeps the reading width.
 *
 * resolve() is the only place that decides. It asks the kind's resolver about
 * the page WordPress is about to print and keeps the answer per post, page and
 * content hash. Three things read that one answer: the layout filter (which
 * sets the block attributes and notes that the sheet is needed), the header marker, and the
 * kind's own renderer (which prints the record it was handed instead of
 * finding one again). They cannot disagree because none of them computes.
 *
 * The page content is whatever core/post-content is about to render: the same
 * get_the_content() call, so WordPress's page splitting (a leading
 * <!--nextpage--> ignored, the Page Break block's delimiters removed) and the
 * preview of an autosave apply without the theme re-implementing either.
 *
 * To give another kind the column, add `kind slug => resolver` to KINDS (or
 * filter courtneyr_child_wide_column_kinds). A resolver takes the post and the
 * page content and returns null (nothing for this kind on this page) or
 * array( 'wide' => bool, 'data' => mixed ): `wide` says the record takes the
 * column, `data` is what the kind's renderer prints from.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleWideColumn;

use function Courtneyr\Child\Enqueue\primary_kind_slug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Kind slug => callable( \WP_Post, string ): ?array{wide: bool, data: mixed}. */
const KINDS = array(
	'read'     => '\\Courtneyr\\Child\\SingleRead\\page_record',
	'favorite' => '\\Courtneyr\\Child\\SingleFavorite\\page_sheet',
);

/**
 * The one decision for the page WordPress is printing.
 *
 * Answers only while the main query's post is in the loop, so a secondary loop,
 * a feed, an excerpt or the editor never gets a decision; those render as they
 * always did. A post behind a password has no decision either.
 *
 * @return array{kind: string, wide: bool, data: mixed}|null
 */
function resolve(): ?array {
	static $memo = array();

	if ( ! \is_singular( 'post' ) || ! \is_main_query() || ! \in_the_loop() ) {
		return null;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post || (int) \get_queried_object_id() !== $post->ID || \post_password_required( $post ) ) {
		return null;
	}

	$content = (string) \get_the_content();
	$key     = $post->ID . ':' . (int) ( $GLOBALS['page'] ?? 1 ) . ':' . md5( $content );
	if ( ! array_key_exists( $key, $memo ) ) {
		$memo[ $key ] = null;
		$kind         = primary_kind_slug( $post->ID );
		$kinds        = (array) \apply_filters( 'courtneyr_child_wide_column_kinds', KINDS );
		if ( isset( $kinds[ $kind ] ) && \is_callable( $kinds[ $kind ] ) ) {
			$found = ( $kinds[ $kind ] )( $post, $content );
			if ( is_array( $found ) ) {
				$memo[ $key ] = array(
					'kind' => $kind,
					'wide' => (bool) ( $found['wide'] ?? false ),
					'data' => $found['data'] ?? null,
				);
			}
		}
	}
	return $memo[ $key ];
}

/**
 * Blocks whose rendered HTML holds blocks the parsed page does not show.
 *
 * A synced pattern (core/block) is a reference: its blocks live in another
 * post and WordPress expands them while it renders. A registered pattern, a
 * template part, a nested post content or excerpt, a query loop and the
 * latest-posts list do the same with other content. The walker cannot see
 * inside any of them, so it cannot say where a card in them sits.
 */
const OPAQUE_BLOCKS = array(
	'core/block',
	'core/pattern',
	'core/template-part',
	'core/post-content',
	'core/post-excerpt',
	'core/query',
	'core/post-template',
	'core/latest-posts',
	'core/shortcode',
	'core/legacy-widget',
	'core/widget-group',
);

/**
 * Where a page's first block of a type sits.
 *
 * A kind whose record replaces one of its blocks can take the column only when
 * that block is a top-level block; inside an authored Group the record keeps
 * the Group's width. When a block whose output the parsed page cannot show
 * (OPAQUE_BLOCKS, or any block whose HTML holds a registered shortcode) comes
 * before the first card, or anywhere on a page with no card, the structure
 * WordPress will print is unknown and the caller makes no decision, so the
 * page renders as it did without the column. This reads block names and
 * markup only, never attributes.
 *
 * @param string $content The content of the page being printed.
 * @param string $name    Block name.
 * @return string 'top', 'nested', 'none' or 'unknown'.
 */
function first_block_place( string $content, string $name ): string {
	$walk = static function ( array $blocks, int $depth ) use ( &$walk, $name ): string {
		foreach ( $blocks as $block ) {
			$block_name = (string) ( $block['blockName'] ?? '' );
			if ( $name === $block_name ) {
				return 0 === $depth ? 'top' : 'nested';
			}
			$html = (string) ( $block['innerHTML'] ?? '' );
			if ( in_array( $block_name, OPAQUE_BLOCKS, true ) || ( '' !== $html && \strip_shortcodes( $html ) !== $html ) ) {
				return 'unknown';
			}
			$found = $walk( (array) ( $block['innerBlocks'] ?? array() ), $depth + 1 );
			if ( 'none' !== $found ) {
				return $found;
			}
		}
		return 'none';
	};
	return $walk( \parse_blocks( $content ), 0 );
}

/**
 * Does the page being printed take the wide column?
 *
 * @return bool
 */
function applies(): bool {
	$found = resolve();
	return null !== $found && $found['wide'];
}

/**
 * The kind's data for the page being printed, when that kind owns it.
 *
 * @param string $kind Kind slug.
 * @return mixed Null when the page belongs to another kind or has no decision.
 */
function data_for( string $kind ) {
	$found = resolve();
	return null !== $found && $kind === $found['kind'] ? $found['data'] : null;
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
	$layout = (array) ( $block['attrs']['layout'] ?? array( 'type' => 'constrained' ) );
	if ( $content ) {
		// The content fills the wide column.
		$block['attrs']['align']     = 'wide';
		$block['attrs']['className'] = trim( implode( ' ', $classes ) . ' cr-wide-column' );
		$layout['contentSize']       = '100%';
		column_used( true );
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

/**
 * Remember that a block rendered in the wide column, so the sheet loads after it.
 *
 * Block templates render before wp_head, so this is set by the time styles are
 * enqueued; enqueuing then (not while the block renders) keeps the sheet after
 * the shared single-post sheets in the cascade, as every other kind's sheet is.
 *
 * @param bool|null $set True when a block took the column; null to read.
 * @return bool
 */
function column_used( ?bool $set = null ): bool {
	static $used = false;
	if ( null !== $set ) {
		$used = $used || $set;
	}
	return $used;
}

/** Paint the wide column only where it rendered. */
function enqueue_styles(): void {
	if ( column_used() ) {
		\wp_enqueue_style(
			'courtneyr-wide-column',
			COURTNEYR_CHILD_URI . '/assets/css/cr-wide-column.css',
			array(),
			COURTNEYR_CHILD_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_styles', 20 );
