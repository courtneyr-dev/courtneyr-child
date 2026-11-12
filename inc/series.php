<?php
/**
 * Series (PublishPress Series 3.1.5, `organize-series`): data and hooks.
 *
 * The theme renders every reader-facing series element itself (blocks/series-guide,
 * templates/taxonomy-series*.html, templates/series-toc.html) and switches the
 * plugin's output off through the plugin's own switches:
 *
 * - `pp_series_add_series_content` (orgSeries-setup.php) gates the three
 *   `the_content` insertions (Post Details, Post List Box, Navigation) and the
 *   excerpt meta. Returning false leaves `org_series_options` untouched.
 * - The plugin's stylesheets are dequeued on the front end; the theme's
 *   assets/css/cr-series.css replaces them.
 *
 * Collection model, read from the CMS and never written:
 *
 * - A series is a term in `series`; part order is post meta `_series_part_{term_id}`
 *   (the plugin's `orgseries_part_key` filter appends the term ID).
 * - A series is a collection landing when a top-level `series_group` term has the
 *   same slug. The other series in that group are its seasons. A child group of
 *   that group is a companion collection; its series are companion arcs.
 * - A series with no such group stands alone (how Merge Conflicts, term 1175,
 *   is set up on live today: one term, no seasons).
 *
 * Groups link to series through `series_grouping` shadow posts named
 * `series_grouping_{series_id}`. The plugin's `get_groups_in_series()` inserts
 * one when it is missing, so this file reads the shadow posts directly.
 *
 * Only published, password-free posts are ever listed or counted.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Series;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TAX       = 'series';
const GROUP_TAX = 'series_group';

/** Parts per page on a series archive: 100 essays read as ten pages. */
const PER_PAGE = 10;

/** Front-end stylesheets the plugin and its addons enqueue (3.1.5 source). */
const PLUGIN_STYLE_HANDLES = array(
	'orgseries-default-css',
	'orgseries-box-css',
	'light-style',
	'dark-style',
	'pps-series-overview-style',
	'pps-post-list-box-frontend',
	'pps-post-list-box-dynamic',
	'pps-series-post-details-frontend',
	'pps-series-post-details-dynamic',
	'pps-series-post-navigation-frontend',
	'publishpress-series-blocks',
	'pp-series-group-archive',
);

/**
 * Whether the series taxonomy is registered (plugin active).
 */
function is_available(): bool {
	return taxonomy_exists( TAX );
}

/**
 * Whether this request is the plugin's /series-toc/ overview.
 */
function is_series_toc(): bool {
	global $wp_query;
	return $wp_query instanceof \WP_Query && ! empty( $wp_query->is_seriestoc );
}

/**
 * Whether the current request shows series content (archive, overview, or a post in a series).
 */
function is_series_context(): bool {
	if ( ! is_available() ) {
		return false;
	}
	if ( is_tax( array( TAX, GROUP_TAX ) ) || is_series_toc() ) {
		return true;
	}
	return is_singular() && has_term( '', TAX, get_queried_object_id() );
}

/**
 * Meta key that holds a post's part number in one series.
 *
 * @param int $series_id Series term ID.
 */
function part_key( int $series_id ): string {
	$base = defined( 'SERIES_PART_KEY' ) ? SERIES_PART_KEY : '_series_part';
	$key  = (string) apply_filters( 'orgseries_part_key', $base, $series_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- PublishPress Series' own filter.
	$tail = '_' . $series_id;
	return str_ends_with( $key, $tail ) ? $key : $key . $tail;
}

/**
 * Order rows by stored part number; rows without a part follow, in their given order.
 *
 * @param array<int, array{id:int, part:?int}> $rows Rows in publication order.
 * @return array<int, array{id:int, part:?int}>
 */
function sort_parts( array $rows ): array {
	$indexed = array();
	foreach ( array_values( $rows ) as $i => $row ) {
		$indexed[] = array( $row, $i );
	}
	usort(
		$indexed,
		static function ( array $a, array $b ): int {
			$pa      = $a[0]['part'] ?? PHP_INT_MAX;
			$pb      = $b[0]['part'] ?? PHP_INT_MAX;
			$by_part = $pa <=> $pb;
			return 0 !== $by_part ? $by_part : $a[1] <=> $b[1];
		}
	);
	return array_map( static fn( array $pair ): array => $pair[0], $indexed );
}

/**
 * Post types the series taxonomy is registered for.
 *
 * @return string[]
 */
function post_types(): array {
	$tax = get_taxonomy( TAX );
	return $tax ? array_values( array_filter( (array) $tax->object_type, 'post_type_exists' ) ) : array( 'post' );
}

/**
 * Published, password-free posts in one series, in part order.
 *
 * @param int    $series_id Series term ID.
 * @param string $status    Post status to read; only the launch date reads `future`.
 * @return array<int, array{id:int, part:?int}>
 */
function parts( int $series_id, string $status = 'publish' ): array {
	static $cache = array();
	$key          = $series_id . ':' . $status;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$ids = get_posts(
		array(
			'post_type'        => post_types(),
			'post_status'      => $status,
			'has_password'     => false,
			'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one indexed term.
				array(
					'taxonomy'         => TAX,
					'field'            => 'term_id',
					'terms'            => $series_id,
					'include_children' => false,
				),
			),
			'fields'           => 'ids',
			'posts_per_page'   => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- IDs of one series (at most ~100), needed to order by part.
			'orderby'          => 'date',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);
	$ids = array_map( 'intval', $ids );
	update_meta_cache( 'post', $ids );
	$meta = part_key( $series_id );
	$rows = array();
	foreach ( $ids as $id ) {
		$raw    = get_post_meta( $id, $meta, true );
		$rows[] = array(
			'id'   => $id,
			'part' => is_numeric( $raw ) ? (int) $raw : null,
		);
	}
	$cache[ $key ] = sort_parts( $rows );
	return $cache[ $key ];
}

/**
 * Sort terms by the plugin's `term_order` column, then naturally by name.
 *
 * @param \WP_Term[] $terms Terms.
 * @return \WP_Term[]
 */
function order_terms( array $terms ): array {
	usort(
		$terms,
		static function ( $a, $b ): int {
			$oa       = isset( $a->term_order ) ? (int) $a->term_order : 0;
			$ob       = isset( $b->term_order ) ? (int) $b->term_order : 0;
			$by_order = $oa <=> $ob;
			return 0 !== $by_order ? $by_order : strnatcasecmp( $a->name, $b->name );
		}
	);
	return $terms;
}

/**
 * Series in one series group, read through the shadow posts.
 *
 * @param int $group_id `series_group` term ID.
 * @return \WP_Term[]
 */
function series_in_group( int $group_id ): array {
	if ( ! taxonomy_exists( GROUP_TAX ) ) {
		return array();
	}
	$objects = get_objects_in_term( $group_id, GROUP_TAX );
	if ( is_wp_error( $objects ) || ! $objects ) {
		return array();
	}
	$terms = array();
	foreach ( $objects as $object_id ) {
		$shadow = get_post( (int) $object_id );
		if ( ! $shadow || 'series_grouping' !== $shadow->post_type || 'trash' === $shadow->post_status || ! str_starts_with( $shadow->post_name, 'series_grouping_' ) ) {
			continue;
		}
		$term = get_term( (int) substr( $shadow->post_name, strlen( 'series_grouping_' ) ), TAX );
		if ( $term instanceof \WP_Term ) {
			$terms[ $term->term_id ] = $term;
		}
	}
	return order_terms( array_values( $terms ) );
}

/**
 * Series groups one series belongs to, without creating a missing shadow post.
 *
 * @param int $series_id Series term ID.
 * @return \WP_Term[]
 */
function groups_of_series( int $series_id ): array {
	if ( ! taxonomy_exists( GROUP_TAX ) ) {
		return array();
	}
	$shadow = get_posts(
		array(
			'post_type'        => 'series_grouping',
			'name'             => 'series_grouping_' . $series_id,
			'post_status'      => array( 'publish', 'draft' ),
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	if ( ! $shadow ) {
		return array();
	}
	$terms = wp_get_object_terms( (int) $shadow[0], GROUP_TAX );
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * The collection a series belongs to.
 *
 * `role` is `landing` (the series is the collection), `season`, `companion`
 * (an arc in a companion group) or `standalone`.
 *
 * @param \WP_Term $series Series term.
 * @return array{role:string, landing:\WP_Term, group:?\WP_Term, seasons:\WP_Term[], companions:array<int, array{group:\WP_Term, arcs:\WP_Term[]}>}
 */
function collection( \WP_Term $series ): array {
	static $cache = array();
	if ( isset( $cache[ $series->term_id ] ) ) {
		return $cache[ $series->term_id ];
	}
	$role    = 'standalone';
	$landing = $series;
	$group   = null;
	if ( taxonomy_exists( GROUP_TAX ) ) {
		$own = get_term_by( 'slug', $series->slug, GROUP_TAX );
		if ( $own instanceof \WP_Term && 0 === (int) $own->parent ) {
			$role  = 'landing';
			$group = $own;
		} else {
			foreach ( groups_of_series( $series->term_id ) as $candidate ) {
				$top = $candidate->parent ? get_term( (int) $candidate->parent, GROUP_TAX ) : $candidate;
				if ( ! $top instanceof \WP_Term ) {
					continue;
				}
				$home = get_term_by( 'slug', $top->slug, TAX );
				if ( $home instanceof \WP_Term ) {
					$role    = $candidate->parent ? 'companion' : 'season';
					$landing = $home;
					$group   = $top;
					break;
				}
			}
		}
	}
	$seasons    = array();
	$companions = array();
	if ( $group ) {
		$seasons  = array_values(
			array_filter(
				series_in_group( $group->term_id ),
				static fn( \WP_Term $t ): bool => $t->term_id !== $landing->term_id
			)
		);
		$children = get_terms(
			array(
				'taxonomy'   => GROUP_TAX,
				'parent'     => $group->term_id,
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $children ) ? order_terms( $children ) : array() as $child ) {
			$companions[] = array(
				'group' => $child,
				'arcs'  => series_in_group( $child->term_id ),
			);
		}
	}
	$cache[ $series->term_id ] = array(
		'role'       => $role,
		'landing'    => $landing,
		'group'      => $group,
		'seasons'    => $seasons,
		'companions' => $companions,
	);
	return $cache[ $series->term_id ];
}

/**
 * Series terms that make up the main reading order of a collection: the landing, then each season.
 *
 * @param array $collection Result of collection().
 * @return \WP_Term[]
 */
function main_series( array $collection ): array {
	return array_merge( array( $collection['landing'] ), $collection['seasons'] );
}

/**
 * Published parts across several series, series by series.
 *
 * @param \WP_Term[] $series Series in reading order.
 * @return array<int, array{id:int, part:?int, series:\WP_Term}>
 */
function track( array $series ): array {
	$rows = array();
	foreach ( $series as $term ) {
		foreach ( parts( $term->term_id ) as $row ) {
			$row['series'] = $term;
			$rows[]        = $row;
		}
	}
	return $rows;
}

/**
 * The series a post belongs to (the free plugin allows one).
 *
 * @param int $post_id Post ID.
 */
function series_of_post( int $post_id ): ?\WP_Term {
	$terms = get_the_terms( $post_id, TAX );
	return is_array( $terms ) && $terms ? $terms[0] : null;
}

/**
 * The series a post reads within: its own arc for companions, the main collection otherwise.
 *
 * @param \WP_Term $series The post's series.
 * @return \WP_Term[]
 */
function reading_order_for( \WP_Term $series ): array {
	$c = collection( $series );
	return 'companion' === $c['role'] ? array( $series ) : main_series( $c );
}

/**
 * 1-based season number of a series within its collection, or 0.
 *
 * @param \WP_Term $series Series term.
 */
function season_number( \WP_Term $series ): int {
	$c = collection( $series );
	foreach ( $c['seasons'] as $i => $season ) {
		if ( $season->term_id === $series->term_id ) {
			return $i + 1 + landing_season_offset( $c );
		}
	}
	return 0;
}

/**
 * Parts published on the landing itself are Season 1 (Courtney, 2026-11-04:
 * Merge Conflicts 1.0 to 1.2 are filed on the landing, and the first season
 * term she adds will be Season 2). While the landing holds published parts,
 * every season term counts from 2; once it holds none, from 1.
 *
 * @param array<string, mixed> $collection From collection().
 */
function landing_season_offset( array $collection ): int {
	return $collection['seasons'] && parts( $collection['landing']->term_id ) ? 1 : 0;
}

/**
 * Text for a post's place in its series. The denominator counts published parts
 * only; the stored part number stays the post's identity, so a gap from an
 * unpublished earlier part shows both numbers instead of renumbering.
 *
 * @param ?int $part     Stored part number.
 * @param int  $position 1-based position among published parts.
 * @param int  $total    Published parts in the series.
 */
function part_label( ?int $part, int $position, int $total ): string {
	if ( null === $part || $part < 1 ) {
		/* translators: 1: position among published parts, 2: published parts. */
		return sprintf( __( '%1$d of %2$d published', 'courtneyr-child' ), $position, $total );
	}
	if ( $part === $position ) {
		/* translators: 1: part number, 2: published parts. */
		return sprintf( __( 'Part %1$d of %2$d', 'courtneyr-child' ), $part, $total );
	}
	/* translators: 1: part number, 2: position among published parts, 3: published parts. */
	return sprintf( __( 'Part %1$d · %2$d of %3$d published', 'courtneyr-child' ), $part, $position, $total );
}

/**
 * Text for a count of published parts.
 *
 * @param int $count Published parts.
 */
function published_count_label( int $count ): string {
	if ( 0 === $count ) {
		return __( 'No parts published yet', 'courtneyr-child' );
	}
	/* translators: %d: number of published parts. */
	return sprintf( _n( '%d part published', '%d parts published', $count, 'courtneyr-child' ), $count );
}

/**
 * The earliest scheduled part in a reading order, for the launch line.
 *
 * @param \WP_Term[] $series Series in reading order.
 * @return array{part:?int, date:string}|null
 */
function next_scheduled( array $series ): ?array {
	$best = null;
	foreach ( $series as $term ) {
		foreach ( parts( $term->term_id, 'future' ) as $row ) {
			$date = (string) get_post_field( 'post_date', $row['id'] );
			if ( null === $best || $date < $best['date'] ) {
				$best = array(
					'part' => $row['part'],
					'date' => $date,
				);
			}
		}
	}
	return $best;
}

/**
 * Whether a page at a site path is published and password-free.
 *
 * @param string $url Absolute or root-relative URL.
 */
function page_is_public( string $url ): bool {
	$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return false;
	}
	$page = get_page_by_path( $path );
	return $page instanceof \WP_Post && 'publish' === $page->post_status && '' === $page->post_password;
}

/*
 * Hooks.
 */

/**
 * Switch off the plugin's `the_content` and excerpt insertions.
 */
add_filter( 'pp_series_add_series_content', '__return_false' );

/**
 * Remove the plugin's front-end stylesheets; cr-series.css styles the theme's markup.
 */
function dequeue_plugin_styles(): void {
	if ( is_admin() ) {
		return;
	}
	foreach ( PLUGIN_STYLE_HANDLES as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\dequeue_plugin_styles', 100 );
add_action( 'wp_print_styles', __NAMESPACE__ . '\\dequeue_plugin_styles', 100 );

/**
 * Series stylesheet on series archives, the overview, posts in a series, and the editor.
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_series_context() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-series',
		COURTNEYR_CHILD_URI . '/assets/css/cr-series.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
	// Front matter for a landing template (masthead start line, methods file, contents,
	// follow block); the start, contents and follow views enqueue it as they render.
	wp_register_style(
		'courtneyr-series-front',
		COURTNEYR_CHILD_URI . '/assets/css/cr-series-front.css',
		array( 'courtneyr-series' ),
		COURTNEYR_CHILD_VERSION
	);
	if ( is_admin() || is_tax( TAX, 'merge-conflicts' ) ) {
		wp_enqueue_style(
			'courtneyr-series-publication',
			COURTNEYR_CHILD_URI . '/assets/css/cr-series-publication.css',
			array( 'courtneyr-series-front' ),
			COURTNEYR_CHILD_VERSION
		);
		// Folding seasons (render_season_folds()); its own sheet keeps the
		// publication sheet under the 30 KiB budget.
		wp_enqueue_style(
			'courtneyr-mc-seasons',
			COURTNEYR_CHILD_URI . '/assets/css/cr-mc-seasons.css',
			array( 'courtneyr-series-publication' ),
			COURTNEYR_CHILD_VERSION
		);
	}
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * A series archive lists published parts in part order, ten per page. A landing
 * with seasons lists its own parts, then each season's. Feeds keep date order.
 *
 * @param \WP_Query $query The query about to run.
 */
function order_series_archive( $query ): void {
	if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() || $query->is_feed() || ! is_available() || ! $query->is_tax( TAX ) ) {
		return;
	}
	$term = $query->get_queried_object();
	if ( ! $term instanceof \WP_Term || TAX !== $term->taxonomy ) {
		return;
	}
	$c      = collection( $term );
	$series = 'landing' === $c['role'] ? main_series( $c ) : array( $term );
	$ids    = array_column( track( $series ), 'id' );
	$query->set( 'post__in', $ids ? $ids : array( 0 ) );
	$query->set( 'orderby', 'post__in' );
	$query->set( 'posts_per_page', PER_PAGE );
	$query->set( 'ignore_sticky_posts', true );
	if ( count( $series ) > 1 ) {
		// The queried object stays the landing term; the season posts join through this clause.
		$query->set( TAX, '' );
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy'         => TAX,
					'field'            => 'term_id',
					'terms'            => wp_list_pluck( $series, 'term_id' ),
					'include_children' => false,
				),
			)
		);
	}
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\order_series_archive', 20 );

/**
 * Slugs of a landing's child series categories whose series also go out in the
 * landing's feed. Extras do (Courtney, 2026-11-11); companion arcs keep their own feeds.
 *
 * @return string[]
 */
function feed_child_group_slugs(): array {
	return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'courtneyr_child_series_feed_child_groups', array( 'mc-extras' ) ) ) ) );
}

/**
 * Series a landing's feed carries: the landing, its seasons, and the series of
 * each child category named in feed_child_group_slugs().
 *
 * @param array<string, mixed> $collection From collection().
 * @return \WP_Term[]
 */
function feed_series( array $collection ): array {
	$series = main_series( $collection );
	$slugs  = feed_child_group_slugs();
	foreach ( $collection['companions'] as $child ) {
		if ( in_array( $child['group']->slug, $slugs, true ) ) {
			$series = array_merge( $series, $child['arcs'] );
		}
	}
	return $series;
}

/**
 * A landing's feed (/series/{landing}/feed/) carries its own parts, every
 * season's and the extras, newest first. Core's feed query reads the landing
 * term alone, so without this a post filed on a season term never reaches
 * series subscribers.
 *
 * @param \WP_Query $query The query about to run.
 */
function landing_feed_query( $query ): void {
	if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() || ! $query->is_feed() || ! is_available() || ! $query->is_tax( TAX ) ) {
		return;
	}
	// Reading the queried object here caches it, so the feed's channel stays the landing.
	$term = $query->get_queried_object();
	if ( ! $term instanceof \WP_Term || TAX !== $term->taxonomy ) {
		return;
	}
	$c = collection( $term );
	if ( 'landing' !== $c['role'] ) {
		return;
	}
	$series = feed_series( $c );
	if ( count( $series ) < 2 ) {
		return;
	}
	$query->set( TAX, '' );
	$query->set(
		'tax_query',
		array(
			array(
				'taxonomy'         => TAX,
				'field'            => 'term_id',
				'terms'            => array_values( array_unique( wp_list_pluck( $series, 'term_id' ) ) ),
				'include_children' => false,
			),
		)
	);
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\landing_feed_query', 20 );

/**
 * Serve /series-toc/ from templates/series-toc.html before the plugin includes its PHP template.
 */
function route_series_toc(): void {
	if ( ! is_available() || ! is_series_toc() ) {
		return;
	}
	$template = locate_block_template( '', 'series-toc', array( 'series-toc' ) );
	if ( $template && str_ends_with( $template, 'template-canvas.php' ) ) {
		include $template;
		exit;
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\\route_series_toc', 9 );

/**
 * With `series_group_archive_render_mode` set to `plugin`, the grouping addon
 * swaps in its PHP template (addons/grouping/legacy-includes.php). Hand the
 * request back to the block template hierarchy.
 *
 * @param string $template Template path.
 */
function group_archive_template( $template ) {
	if ( ! is_string( $template ) || ! is_tax( GROUP_TAX ) || ! str_contains( wp_normalize_path( $template ), '/organize-series/' ) ) {
		return $template;
	}
	$term  = get_queried_object();
	$slugs = array( 'taxonomy-' . GROUP_TAX, 'taxonomy', 'archive', 'index' );
	if ( $term instanceof \WP_Term ) {
		array_unshift( $slugs, 'taxonomy-' . GROUP_TAX . '-' . $term->slug );
	}
	// An empty first argument: given the plugin's path, core keeps it as a higher-priority PHP template.
	$block = locate_block_template( '', 'taxonomy', $slugs );
	return is_string( $block ) && '' !== $block ? $block : $template;
}
add_filter( 'template_include', __NAMESPACE__ . '\\group_archive_template', 20 );

/**
 * Body class for the overview, which has no archive classes of its own.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_series_toc() ) {
		$classes[] = 'cr-series-toc';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * The methods link only renders when its page is public; until then the
 * paragraph says so in plain text, so the callout never links to a 404.
 *
 * @param string $content Rendered paragraph.
 * @param array  $block   Parsed block.
 */
function gate_methods_link( string $content, array $block ): string {
	$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
	if ( ! in_array( 'cr-series-methods__link', $classes, true ) || is_admin() ) {
		return $content;
	}
	$processor = new \WP_HTML_Tag_Processor( $content );
	$href      = $processor->next_tag( 'a' ) ? (string) $processor->get_attribute( 'href' ) : '';
	if ( '' !== $href && page_is_public( $href ) ) {
		return $content;
	}
	return '<p class="cr-series-methods__pending"><span class="cr-series-stamp">' . esc_html__( 'Not published yet', 'courtneyr-child' ) . '</span> ' . esc_html__( 'Every part will link to it.', 'courtneyr-child' ) . '</p>';
}
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\gate_methods_link', 10, 2 );

/**
 * Page 2 onward of a series archive skips the methods file and the contents
 * introduction, so later pages open on the parts. The contents list stays as
 * the way between seasons.
 *
 * @param string $content Rendered group.
 * @param array  $block   Parsed block.
 */
function skip_front_matter_when_paged( string $content, array $block ): string {
	$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
	if ( ! is_paged() || ! is_tax( TAX ) || ! array_intersect( array( 'cr-series-methods', 'cr-series-intro', 'cr-mc__methods' ), $classes ) ) {
		return $content;
	}
	return '';
}
add_filter( 'render_block_core/group', __NAMESPACE__ . '\\skip_front_matter_when_paged', 10, 2 );

/**
 * The big numeral on an archive row repeats the visually hidden "Part 3"
 * label, so screen readers hear the label once and skip the numeral.
 *
 * @param string $content Rendered paragraph.
 * @param array  $block   Parsed block.
 */
function hide_part_numeral( string $content, array $block ): string {
	$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
	if ( ! in_array( 'cr-series-card__num', $classes, true ) ) {
		return $content;
	}
	$processor = new \WP_HTML_Tag_Processor( $content );
	if ( $processor->next_tag( 'p' ) ) {
		$processor->set_attribute( 'aria-hidden', 'true' );
		$processor->set_attribute( 'role', 'presentation' );
	}
	return $processor->get_updated_html();
}
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\hide_part_numeral', 10, 2 );

/**
 * Minutes to read a post at 238 words a minute (Brysbaert 2019), never under one.
 *
 * @param int $post_id Post.
 */
function reading_minutes( int $post_id ): int {
	$text = wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) );
	return max( 1, (int) ceil( str_word_count( $text ) / 238 ) );
}

/**
 * Block bindings source `courtneyr/series-part`: the part label, numeral,
 * season label and reading time on series archive rows. Keys: `part`
 * ("Part 3"), `numeral` ("03"), `season` ("Season 2") and `read` ("12 min read").
 *
 * @param array<string, mixed> $source_args Binding arguments.
 * @param \WP_Block            $block       Bound block.
 */
function part_binding_value( array $source_args, $block ): ?string {
	$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
	$series  = $post_id ? series_of_post( $post_id ) : null;
	if ( ! $series ) {
		return '';
	}
	$key = (string) ( $source_args['key'] ?? 'part' );
	if ( 'season' === $key ) {
		$n = season_number( $series );
		/* translators: %d: season number. */
		return $n ? sprintf( __( 'Season %d', 'courtneyr-child' ), $n ) : '';
	}
	if ( 'read' === $key ) {
		/* translators: %d: minutes. */
		return sprintf( __( '%d min read', 'courtneyr-child' ), reading_minutes( $post_id ) );
	}
	foreach ( parts( $series->term_id ) as $i => $row ) {
		if ( $row['id'] === $post_id ) {
			$n = (int) ( $row['part'] ?? $i + 1 );
			/* translators: %d: part number. */
			return 'numeral' === $key ? sprintf( '%02d', $n ) : sprintf( __( 'Part %d', 'courtneyr-child' ), $n );
		}
	}
	return '';
}

/**
 * Wrap the archive's parts list in season sections: a tape label, heading and
 * description per season, each season's parts in its own numbered panel. A
 * series without seasons gets one panel under a visually hidden heading.
 * Grouping happens on the rendered rows, so the main query, its ten-per-page
 * pagination and the post template stay core blocks.
 *
 * @param string $content Rendered post template.
 * @param array  $block   Parsed block.
 */
function group_parts( string $content, array $block ): string {
	$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
	$list    = trim( $content );
	$open    = strpos( $list, '>' );
	if ( ! in_array( 'cr-series-list', $classes, true ) || ! is_tax( TAX ) || ! str_starts_with( $list, '<ul' ) || ! str_ends_with( $list, '</ul>' ) || false === $open ) {
		return $content;
	}
	$term = get_queried_object();
	if ( ! $term instanceof \WP_Term ) {
		return $content;
	}
	// Each post-template item opens with this exact tag prefix (core's render.php).
	$marker = '<li class="wp-block-post ';
	$rows   = array();
	foreach ( array_slice( explode( $marker, substr( $list, $open + 1, -strlen( '</ul>' ) ) ), 1 ) as $item ) {
		$item = $marker . $item;
		$tag  = new \WP_HTML_Tag_Processor( $item );
		$id   = 0;
		if ( $tag->next_tag( 'li' ) ) {
			foreach ( $tag->class_list() as $class ) {
				if ( str_starts_with( $class, 'post-' ) && ctype_digit( substr( $class, 5 ) ) ) {
					$id = (int) substr( $class, 5 );
				}
			}
		}
		if ( $id ) {
			$rows[] = array(
				'id'   => $id,
				'html' => $item,
			);
		}
	}
	return $rows ? render_part_groups( $term, $rows, substr( $list, 3, $open - 3 ) ) : $content;
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\group_parts', 10, 2 );

/**
 * The image set in PublishPress Series' "Series featured image" field. The
 * plugin keeps it in its own table as a URL with the home URL stripped
 * (orgSeries-manage.php), so the full URL is rebuilt with its seriesicons_url().
 * The attachment ID is 0 when the URL isn't in the media library. Read-only.
 *
 * @param \WP_Term $series Series.
 * @return array{id: int, url: string}|null
 */
function series_image( \WP_Term $series ): ?array {
	static $cache = array();
	if ( array_key_exists( $series->term_id, $cache ) ) {
		return $cache[ $series->term_id ];
	}
	$image = null;
	if ( function_exists( 'series_get_icons' ) && function_exists( 'seriesicons_url' ) ) {
		$icon = trim( (string) series_get_icons( $series->term_id ) );
		if ( '' !== $icon ) {
			$url   = str_starts_with( $icon, 'http://' ) || str_starts_with( $icon, 'https://' ) ? $icon : seriesicons_url() . ltrim( $icon, '/' );
			$image = array(
				'id'  => (int) attachment_url_to_postid( $url ),
				'url' => $url,
			);
		}
	}
	$cache[ $series->term_id ] = $image;
	return $image;
}

/**
 * Share image for a series archive: Yoast's own term image wins when one is
 * set; otherwise the series image, a season falling back to its collection's,
 * instead of the site-wide default card. Hooked after Yoast reads the term
 * (wpseo_add_opengraph_additional_images, Yoast 28.5 open-graph-image-generator.php);
 * its template and default steps skip once the container holds an image.
 *
 * @param mixed $container Yoast Open Graph image container.
 * @return mixed
 */
function series_share_image( $container ) {
	if ( ! is_object( $container ) || ! method_exists( $container, 'has_images' ) || $container->has_images() || ! is_tax( TAX ) ) {
		return $container;
	}
	$term = get_queried_object();
	if ( ! $term instanceof \WP_Term || TAX !== $term->taxonomy ) {
		return $container;
	}
	$image = series_image( $term ) ?? series_image( collection( $term )['landing'] );
	if ( ! $image ) {
		return $container;
	}
	if ( $image['id'] && method_exists( $container, 'add_image_by_id' ) ) {
		$container->add_image_by_id( $image['id'] );
	} elseif ( method_exists( $container, 'add_image_by_url' ) ) {
		$container->add_image_by_url( $image['url'] );
	}
	return $container;
}
add_filter( 'wpseo_add_opengraph_additional_images', __NAMESPACE__ . '\\series_share_image' );

/**
 * Register the bindings source and the series-guide block.
 */
function register(): void {
	if ( function_exists( 'register_block_bindings_source' ) ) {
		register_block_bindings_source(
			'courtneyr/series-part',
			array(
				'label'              => __( 'Series part', 'courtneyr-child' ),
				'get_value_callback' => __NAMESPACE__ . '\\part_binding_value',
				'uses_context'       => array( 'postId' ),
			)
		);
	}
	register_block_type( COURTNEYR_CHILD_DIR . '/blocks/series-guide' );
}
add_action( 'init', __NAMESPACE__ . '\\register' );

/**
 * Add loading hints and intrinsic sizes after core/image has validated its
 * editable saved markup. Only the publication's decorative image classes match.
 *
 * @param string $content Rendered image block.
 * @param array  $block Parsed image block.
 */
function publication_image_attributes( string $content, array $block ): string {
	if ( ! is_tax( TAX, 'merge-conflicts' ) ) {
		return $content;
	}
	$sizes = array(
		'cr-mc__hero-art'  => array( 760, 695 ),
		'cr-mc__coda'      => array( 1040, 400 ),
		'cr-mc__notebook'  => array( 360, 393 ),
		'cr-mc__megaphone' => array( 240, 219 ),
		'cr-mc__books'     => array( 110, 100 ),
		'cr-mc__signpost'  => array( 180, 233 ),
	);
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! isset( $sizes[ $class ] ) ) {
		return $content;
	}
	$image = new \WP_HTML_Tag_Processor( $content );
	if ( $image->next_tag( 'img' ) ) {
		$image->set_attribute( 'width', $sizes[ $class ][0] );
		$image->set_attribute( 'height', $sizes[ $class ][1] );
		$image->set_attribute( 'decoding', 'async' );
		$image->set_attribute( 'loading', 'cr-mc__hero-art' === $class ? 'eager' : 'lazy' );
		if ( 'cr-mc__hero-art' === $class ) {
			$image->set_attribute( 'fetchpriority', 'high' );
		}
	}
	return $image->get_updated_html();
}
add_filter( 'render_block_core/image', __NAMESPACE__ . '\\publication_image_attributes', 10, 2 );
