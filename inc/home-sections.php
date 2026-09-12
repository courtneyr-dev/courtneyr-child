<?php
/**
 * Homepage sections: newsletter reasons and Field Notes (Blog + Stream).
 *
 * Owns three small integration points for the two homepage patterns:
 *
 * 1. Two `core/query` variations (`courtneyr/home-blog`, `courtneyr/home-stream`)
 *    that carry a `pkiwSurface` query key. The Post Kinds plugin computes the
 *    surface of every post (`_pkiw_surface` = 'stream' | 'main') from the
 *    site's `pkiw_stream_kinds` filter; this file only reads that signal, so
 *    the homepage Blog and Stream follow the same routing as /blog/ and
 *    /stream/ without a second copy of the rule.
 * 2. The matching query filters for the front end (`query_loop_block_query_vars`)
 *    and the editor preview (`rest_post_query`), so both show the same posts.
 * 3. `is_stream_surface()`: the Stream artifact adapters (booth strip, book,
 *    pinned quote, polaroid…) used to key on `is_page( 'stream' )`; the
 *    homepage Stream lane renders the same plugin card inside `.cr-stream-page`,
 *    so the adapters ask this helper instead.
 *
 * The section stylesheet loads only when the queried content carries one of
 * the two section classes.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\HomeSections;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces the plugin can route a post to. Mirrors PKIW\Post_Surface.
 */
const SURFACE_STREAM = 'stream';
const SURFACE_MAIN   = 'main';

/**
 * Is the current request a Stream surface (the /stream page or the homepage
 * Stream lane)? The homepage lane is the only other place the theme renders
 * the plugin's stream card, so the front page counts.
 *
 * @return bool
 */
function is_stream_surface(): bool {
	return is_page( 'stream' ) || is_front_page();
}

/**
 * Meta clauses that select one surface. `main` also admits posts without the
 * derived meta (posts saved before the plugin stored it), matching the
 * plugin's own recipe for the blog main query.
 *
 * @param string $surface 'stream' or 'main'.
 * @return array<int|string, mixed> A meta_query clause.
 */
function surface_meta_clause( string $surface ): array {
	if ( SURFACE_STREAM === $surface ) {
		return array(
			'key'   => '_pkiw_surface',
			'value' => SURFACE_STREAM,
		);
	}
	return array(
		'relation' => 'OR',
		array(
			'key'     => '_pkiw_surface',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => '_pkiw_surface',
			'value'   => SURFACE_STREAM,
			'compare' => '!=',
		),
	);
}

/**
 * Apply a surface to a WP_Query argument array. Keeps whatever meta_query the
 * query already has and adds the plugin's imported-post exclusion for the
 * main surface when the plugin runs in hidden mode, the same rule
 * PKIW\Query_Filter applies to /blog/.
 *
 * @param array<string, mixed> $query   WP_Query arguments.
 * @param string               $surface 'stream' or 'main'.
 * @return array<string, mixed>
 */
function apply_surface( array $query, string $surface ): array {
	if ( ! in_array( $surface, array( SURFACE_STREAM, SURFACE_MAIN ), true ) ) {
		return $query;
	}
	$meta_query   = isset( $query['meta_query'] ) && is_array( $query['meta_query'] ) ? $query['meta_query'] : array();
	$meta_query[] = surface_meta_clause( $surface );
	if ( SURFACE_MAIN === $surface && class_exists( '\\PKIW\\Post_Type' ) && is_callable( array( '\\PKIW\\Post_Type', 'is_hidden_mode' ) ) && \PKIW\Post_Type::is_hidden_mode() ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_pkiw_imported_from',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_pkiw_imported_from',
				'value'   => '',
				'compare' => '=',
			),
		);
	}
	$query['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	return $query;
}

/**
 * Front end: honour the `pkiwSurface` key a Query Loop carries.
 *
 * @param array<string, mixed> $query WP_Query arguments built from the block.
 * @param \WP_Block            $block The Query block.
 * @return array<string, mixed>
 */
function filter_query_loop( array $query, \WP_Block $block ): array {
	$surface = (string) ( $block->context['query']['pkiwSurface'] ?? '' );
	return '' === $surface ? $query : apply_surface( $query, $surface );
}
add_filter( 'query_loop_block_query_vars', __NAMESPACE__ . '\\filter_query_loop', 10, 2 );

/**
 * Editor preview: the Query Loop block passes unknown `query` keys straight to
 * the REST request, so the same key filters the editor's post list.
 *
 * @param array<string, mixed> $args    WP_Query arguments for the REST request.
 * @param \WP_REST_Request     $request The request.
 * @return array<string, mixed>
 */
function filter_rest_query( array $args, \WP_REST_Request $request ): array {
	$surface = (string) ( $request->get_param( 'pkiwSurface' ) ?? '' );
	return '' === $surface ? $args : apply_surface( $args, $surface );
}
add_filter( 'rest_post_query', __NAMESPACE__ . '\\filter_rest_query', 10, 2 );

/**
 * Register the two Query Loop variations on the server so the inserter, the
 * `isActive` match and the frontend share one definition.
 *
 * @param array<int, array<string, mixed>> $variations Existing variations.
 * @param \WP_Block_Type                   $block_type The block type.
 * @return array<int, array<string, mixed>>
 */
function query_variations( array $variations, \WP_Block_Type $block_type ): array {
	if ( 'core/query' !== $block_type->name ) {
		return $variations;
	}
	$base_query   = array(
		'perPage'  => 4,
		'pages'    => 0,
		'offset'   => 0,
		'postType' => 'post',
		'order'    => 'desc',
		'orderBy'  => 'date',
		'author'   => '',
		'search'   => '',
		'exclude'  => array(),
		'sticky'   => 'exclude',
		'inherit'  => false,
	);
	$variations[] = array(
		'name'        => 'courtneyr/home-blog',
		'title'       => __( 'Blog posts (main surface)', 'courtneyr-child' ),
		'description' => __( 'Newest posts that belong on the blog, using the same surface routing as /blog/.', 'courtneyr-child' ),
		'attributes'  => array(
			'namespace' => 'courtneyr/home-blog',
			'query'     => array_merge( $base_query, array( 'pkiwSurface' => SURFACE_MAIN ) ),
		),
		'isActive'    => array( 'namespace' ),
		'scope'       => array( 'inserter' ),
		'innerBlocks' => array(
			array( 'core/post-template', array(), array( array( 'core/post-title', array( 'isLink' => true ) ) ) ),
		),
	);
	$variations[] = array(
		'name'        => 'courtneyr/home-stream',
		'title'       => __( 'Stream cards (stream surface)', 'courtneyr-child' ),
		'description' => __( 'Newest Stream entries rendered with the Post Kinds stream card, using the same surface routing as /stream/.', 'courtneyr-child' ),
		'attributes'  => array(
			'namespace' => 'courtneyr/home-stream',
			'query'     => array_merge(
				$base_query,
				array(
					'perPage'     => 6,
					'pkiwSurface' => SURFACE_STREAM,
				)
			),
		),
		'isActive'    => array( 'namespace' ),
		'scope'       => array( 'inserter' ),
		'innerBlocks' => array(
			array( 'core/post-template', array(), array( array( 'post-kinds-indieweb/stream-card' ) ) ),
		),
	);
	return $variations;
}
add_filter( 'get_block_type_variations', __NAMESPACE__ . '\\query_variations', 10, 2 );

/**
 * Load the section stylesheet only where a section renders: the queried
 * singular's content carries one of the two wrapper classes.
 */
function enqueue_section_styles(): void {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( ! $post instanceof \WP_Post ) {
		return;
	}
	if ( ! str_contains( $post->post_content, 'cr-reasons' ) && ! str_contains( $post->post_content, 'cr-fieldnotes' ) ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-home-sections',
		COURTNEYR_CHILD_URI . '/assets/css/cr-home-sections.css',
		array( 'courtneyr-components' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_section_styles' );
