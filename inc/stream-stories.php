<?php
/**
 * Web Stories in the Stream (#268).
 *
 * Courtney moved 📚 Stories under the 🌊 Stream menu on 2026-11-04 and
 * asked for published stories to show in what /stream/ lists. Stories are
 * the Web Stories plugin's `web-story` post type: no kind, no surface meta.
 *
 * Architecture (CLAUDE.md order):
 * - Query: the Stream page's Query Loop is plain `postType: post`, and the
 *   site's routing policy (cr-content-surfaces) adds the stream surface meta
 *   clause to it. Adding a post type in the block would hand the editor a
 *   query the routing policy doesn't know, so this widens it on the front end
 *   only: `post` plus `web-story`, and stories pass the surface clause by
 *   post type. The theme writes no meta.
 * - Card: Post Kinds' own extension point, `pkiw_stream_card_kind_render`
 *   (1.9.0), returns the poster card; no custom block. It reuses the poster
 *   the Web Stories archive shows (the story's featured image) and links to
 *   the plugin's story URL, where playback stays.
 * - Scope: the /stream/ page only. The front page is also a stream surface,
 *   but nothing asked for stories there.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamStories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POST_TYPE = 'web-story';
const FLAG      = 'cr_stream_stories';
const HANDLE    = 'courtneyr-stream-story';

/**
 * Whether a Query Loop is the Stream page's list of entries.
 *
 * @param array<string, mixed> $query WP_Query arguments built from the block.
 * @return bool
 */
function is_stream_list( array $query ): bool {
	if ( ! is_page( 'stream' ) || ! post_type_exists( POST_TYPE ) ) {
		return false;
	}
	$type = $query['post_type'] ?? 'post';
	return 'post' === $type || array( 'post' ) === $type;
}

/**
 * Flag the Stream page's Query Loop. Runs last so the flag survives any
 * earlier filter that rebuilds the arguments.
 *
 * @param array<string, mixed> $query WP_Query arguments built from the block.
 * @return array<string, mixed>
 */
function flag_query( array $query ): array {
	if ( is_stream_list( $query ) ) {
		$query[ FLAG ] = true;
	}
	return $query;
}
add_filter( 'query_loop_block_query_vars', __NAMESPACE__ . '\\flag_query', PHP_INT_MAX );

/**
 * Add stories to the flagged query after every other pre_get_posts filter
 * has run, so the routing policy still sees the `post` query it expects.
 *
 * @param \WP_Query $query The query.
 */
function widen_post_type( \WP_Query $query ): void {
	if ( true !== $query->get( FLAG ) ) {
		return;
	}
	$query->set( 'post_type', array( 'post', POST_TYPE ) );
	// Published only: a draft story never reaches the Stream.
	$query->set( 'post_status', 'publish' );
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\widen_post_type', PHP_INT_MAX );

/**
 * Let stories through the surface meta clause. Stories carry no surface
 * meta, so the clause's join would drop them. The original join and
 * condition move into a subquery that picks the posts which pass it, and
 * stories pass by post type. The subquery keeps the policy's own SQL
 * whole, joins and indexes included, whatever clauses it holds.
 *
 * @param array<string, string> $sql     `join` and `where` fragments.
 * @param array<mixed>          $queries Meta query clauses (unused).
 * @param string                $type    Meta type.
 * @param string                $table   Primary table.
 * @param string                $column  Primary ID column.
 * @param mixed                 $context The WP_Query, when there is one.
 * @return array<string, string>
 */
function admit_stories( $sql, $queries, $type, $table, $column, $context = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( ! $context instanceof \WP_Query || true !== $context->get( FLAG ) || 'post' !== $type ) {
		return $sql;
	}
	return rewrite_meta_sql( (array) $sql, (string) $table, (string) $column );
}
add_filter( 'get_meta_sql', __NAMESPACE__ . '\\admit_stories', 10, 6 );

/**
 * The SQL rewrite on its own, so it can be tested without a database.
 *
 * @param array<string, string> $sql    `join` and `where` fragments.
 * @param string                $table  Primary table.
 * @param string                $column Primary ID column.
 * @return array<string, string>
 */
function rewrite_meta_sql( array $sql, string $table, string $column ): array {
	$where = trim( (string) ( $sql['where'] ?? '' ) );
	if ( '' === $where ) {
		return $sql;
	}
	$join = trim( (string) ( $sql['join'] ?? '' ) );
	return array(
		'join'  => '',
		'where' => sprintf(
			' AND ( %1$s.post_type = \'%2$s\' OR %1$s.%3$s IN ( SELECT %1$s.%3$s FROM %1$s %4$s WHERE 1=1 %5$s ) )',
			$table,
			POST_TYPE,
			$column,
			$join,
			$where
		),
	);
}

/**
 * Register the poster card sheet.
 */
function register_style(): void {
	wp_register_style( HANDLE, get_stylesheet_directory_uri() . '/assets/css/cr-stream-story.css', array(), COURTNEYR_CHILD_VERSION );
	wp_style_add_data( HANDLE, 'path', get_stylesheet_directory() . '/assets/css/cr-stream-story.css' );
}
add_action( 'init', __NAMESPACE__ . '\\register_style' );

/**
 * The poster card for a story in the Stream.
 *
 * One link (the title), stretched over the card in CSS, so a keyboard
 * stops once per story. The poster's alt text is the attachment's own.
 *
 * @param string|null $html          Card markup from an earlier filter.
 * @param \WP_Post    $post          Post.
 * @param int         $heading_level Heading level, 2-4.
 * @return string|null
 */
function story_card( $html, $post, $heading_level ) {
	if ( is_string( $html ) || ! $post instanceof \WP_Post || POST_TYPE !== $post->post_type ) {
		return $html;
	}
	wp_enqueue_style( HANDLE );

	$level  = max( 2, min( 4, (int) $heading_level ) );
	$url    = (string) get_permalink( $post );
	$title  = get_the_title( $post );
	$poster = (int) get_post_thumbnail_id( $post );
	$image  = $poster ? wp_get_attachment_image(
		$poster,
		'medium_large',
		false,
		array(
			'class'   => 'cr-story-card__img u-photo',
			'loading' => 'lazy',
			'sizes'   => '(min-width: 48rem) 22rem, 90vw',
		)
	) : '';

	$out  = '<article class="pk-card pk-card--stream cr-story-card h-entry">';
	$out .= '' !== $image ? '<div class="cr-story-card__poster">' . $image . '</div>' : '';
	$out .= '<div class="cr-story-card__label">';
	$out .= '<p class="cr-story-card__kind">' . esc_html__( 'Web story', 'courtneyr-child' ) . '</p>';
	$out .= sprintf(
		'<h%1$d class="pk-title cr-story-card__title p-name"><a class="u-url cr-story-card__link" href="%2$s">%3$s</a></h%1$d>',
		$level,
		esc_url( $url ),
		esc_html( $title )
	);
	$out .= sprintf(
		'<time class="dt-published cr-story-card__date" datetime="%1$s">%2$s</time>',
		esc_attr( (string) get_the_date( DATE_W3C, $post ) ),
		esc_html( (string) get_the_date( 'F j, Y', $post ) )
	);
	$out .= '</div></article>';
	return $out;
}
add_filter( 'pkiw_stream_card_kind_render', __NAMESPACE__ . '\\story_card', 10, 3 );
