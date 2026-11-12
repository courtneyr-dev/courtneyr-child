<?php
/**
 * `php tests/php/series-test.php`: Merge Conflicts series part order, labels and
 * template placement (inc/series.php, templates/taxonomy-series*.html, single.html).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'CR_SERIES_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks'] = array();

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][] = array( $callback, (int) $priority );
		return true;
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	function apply_filters( $hook, $value, ...$args ) {
		if ( 'orgseries_part_key' === $hook && ! empty( $GLOBALS['cr_plugin_part_key'] ) ) {
			return $value . '_' . $args[0];
		}
		return $value;
	}

	function __( $text, $domain = 'default' ) {
		return $text;
	}

	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}

	function esc_attr__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}

	function esc_attr( $text ) {
		return esc_html( $text );
	}

	function esc_url( $url ) {
		return esc_html( $url );
	}

	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === $number ? $single : $plural;
	}

	function strip_shortcodes( $text ) {
		return $text;
	}

	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}

	function get_post_field( $field, $post_id ) {
		return $GLOBALS['cr_post_content'][ $post_id ] ?? '';
	}

	// Series image and share-image stubs: PublishPress Series' icon table, the media library, the query.
	class WP_Term {
		public $term_id     = 0;
		public $taxonomy    = 'series';
		public $slug        = '';
		public $name        = '';
		public $parent      = 0;
		public $description = '';
	}

	function series_get_icons( $series ) {
		return $GLOBALS['cr_icons'][ $series ] ?? false;
	}

	function seriesicons_url() {
		return 'https://example.test/';
	}

	function attachment_url_to_postid( $url ) {
		return $GLOBALS['cr_attachments'][ $url ] ?? 0;
	}

	function taxonomy_exists( $taxonomy ) {
		return ! empty( $GLOBALS['cr_taxonomies'][ $taxonomy ] );
	}

	function get_taxonomy( $taxonomy ) {
		return false;
	}

	function get_posts( $args ) {
		if ( 'series_grouping' === $args['post_type'] ) {
			return array();
		}
		$GLOBALS['cr_parts_queries'][] = $args;
		$ids = array();
		foreach ( $GLOBALS['cr_parts_posts'] ?? array() as $id => $post ) {
			if ( $post['series'] === $args['tax_query'][0]['terms'] && $post['status'] === $args['post_status'] && ( ! $args['has_password'] ? ! $post['password'] : true ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	function update_meta_cache( $type, $ids ) {}

	function get_post_meta( $id, $key, $single ) {
		return $GLOBALS['cr_parts_posts'][ $id ]['part'] ?? '';
	}

	function get_the_title( $id ) {
		return $GLOBALS['cr_parts_posts'][ $id ]['title'];
	}

	function get_the_date( $format = '', $id = 0 ) {
		return 'c' === $format || DATE_W3C === $format ? '2026-11-02T08:00:00-05:00' : 'Nov 2, 2026';
	}

	function get_permalink( $id ) {
		return 'https://example.test/post-' . $id . '/';
	}

	function get_term_by( $field, $value, $taxonomy ) {
		foreach ( $GLOBALS['cr_terms'][ $taxonomy ] ?? array() as $term ) {
			if ( $term->$field === $value ) {
				return $term;
			}
		}
		return false;
	}

	function get_term( $id, $taxonomy ) {
		return $GLOBALS['cr_terms'][ $taxonomy ][ $id ] ?? null;
	}

	function get_objects_in_term( $id, $taxonomy ) {
		return $GLOBALS['cr_group_objects'][ $id ] ?? array();
	}

	function get_post( $id ) {
		return $GLOBALS['cr_shadow_posts'][ $id ] ?? null;
	}

	function get_terms( $args ) {
		return $GLOBALS['cr_child_groups'][ $args['parent'] ?? -1 ] ?? array();
	}

	function is_admin() {
		return false;
	}

	function wp_list_pluck( $list, $field ) {
		return array_map( static fn( $item ) => is_object( $item ) ? $item->$field : $item[ $field ], $list );
	}

	/** The parts of WP_Query the series hooks read and write. */
	class WP_Query {
		public $vars = array();
		public $feed = false;
		public $queried;
		public function is_main_query() {
			return true;
		}
		public function is_feed() {
			return $this->feed;
		}
		public function is_tax( $taxonomy = '' ) {
			return true;
		}
		public function get_queried_object() {
			return $this->queried;
		}
		public function set( $key, $value ) {
			$this->vars[ $key ] = $value;
		}
	}

	function is_wp_error( $value ) {
		return false;
	}

	function get_term_link( $term ) {
		return 'https://example.test/series/' . $term->slug . '/';
	}

	function get_the_terms( $id, $taxonomy ) {
		return isset( $GLOBALS['cr_post_series'][ $id ] ) ? array( $GLOBALS['cr_post_series'][ $id ] ) : false;
	}

	function is_tax( $taxonomy = '' ) {
		return ! empty( $GLOBALS['cr_is_tax'] );
	}

	function get_queried_object() {
		return $GLOBALS['cr_queried'] ?? null;
	}

	/** Records what the share hook adds, shaped like Yoast's image container. */
	class CR_Fake_Images {
		public $added = array();
		public $start = false;
		public function has_images() {
			return $this->start || (bool) $this->added;
		}
		public function add_image_by_id( $id ) {
			$this->added[] = array( 'id', $id );
		}
		public function add_image_by_url( $url ) {
			$this->added[] = array( 'url', $url );
		}
	}

	require CR_SERIES_THEME_DIR . '/inc/series.php';
	require CR_SERIES_THEME_DIR . '/inc/series-views.php';
}

namespace Courtneyr\Child\Series\Tests {

	use function Courtneyr\Child\Series\landing_feed_query;
	use function Courtneyr\Child\Series\part_key;
	use function Courtneyr\Child\Series\part_label;
	use function Courtneyr\Child\Series\published_count_label;
	use function Courtneyr\Child\Series\reading_minutes;
	use function Courtneyr\Child\Series\render_post_header;
	use function Courtneyr\Child\Series\render_publication_contents;
	use function Courtneyr\Child\Series\series_image;
	use function Courtneyr\Child\Series\series_share_image;
	use function Courtneyr\Child\Series\sort_parts;

	$failures = 0;

	/**
	 * Record one check.
	 *
	 * @param bool   $ok    Passed.
	 * @param string $label What was checked.
	 */
	function check( bool $ok, string $label ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
		if ( ! $ok ) {
			++$failures;
		}
	}

	// Part order: stored part wins over publication order; parts without a number follow.
	$sorted = sort_parts(
		array(
			array( 'id' => 30, 'part' => 3 ),
			array( 'id' => 99, 'part' => null ),
			array( 'id' => 10, 'part' => 1 ),
			array( 'id' => 20, 'part' => 2 ),
			array( 'id' => 98, 'part' => null ),
		)
	);
	check( array( 10, 20, 30, 99, 98 ) === array_column( $sorted, 'id' ), 'sort_parts orders by part, then keeps unnumbered posts in publication order' );

	// Labels: the denominator counts published parts only.
	check( 'Part 1 of 1' === part_label( 1, 1, 1 ), 'one published part reads "Part 1 of 1"' );
	check( 'Part 2 of 3' === part_label( 2, 2, 3 ), 'contiguous parts read "Part N of M"' );
	check( 'Part 3 · 2 of 2 published' === part_label( 3, 2, 2 ), 'a gap keeps the stored part and states the published position' );
	check( '2 of 5 published' === part_label( null, 2, 5 ), 'a post with no stored part shows its published position' );
	check( 'No parts published yet' === published_count_label( 0 ), 'zero published parts say so in words' );
	check( '1 part published' === published_count_label( 1 ), 'singular count' );
	check( '3 parts published' === published_count_label( 3 ), 'plural count' );

	// Part key: the plugin's filter appends the series ID; without the plugin the theme does.
	check( '_series_part_1175' === part_key( 1175 ), 'part_key without the plugin filter' );
	$GLOBALS['cr_plugin_part_key'] = true;
	check( '_series_part_1175' === part_key( 1175 ), 'part_key with the plugin filter appends the ID once' );

	// The plugin's content insertions are switched off through its own filter.
	$switch = $GLOBALS['cr_hooks']['pp_series_add_series_content'][0][0] ?? null;
	check( '__return_false' === $switch, 'pp_series_add_series_content returns false' );

	// Archive order runs after the card-grid page size (priority 10).
	$archive = $GLOBALS['cr_hooks']['pre_get_posts'][0] ?? array( null, 0 );
	check( 20 === $archive[1], 'order_series_archive runs at pre_get_posts priority 20' );

	// Templates: methods before the parts on the landing; part nav right after the post body.
	$landing = (string) file_get_contents( CR_SERIES_THEME_DIR . '/templates/taxonomy-series-merge-conflicts.html' );
	$publication_pattern = (string) file_get_contents( CR_SERIES_THEME_DIR . '/patterns/cr-series-mc-publication.php' );
	$header  = strpos( $publication_pattern, 'cr-mc__hero' );
	$methods = strpos( $publication_pattern, 'cr-mc__methods' );
	$body    = strpos( $publication_pattern, '"view":"publication-parts"' );
	check( false !== $header && false !== $methods && false !== $body && $header < $methods && $methods < $body, 'Merge Conflicts landing: masthead, then methods, then the parts' );

	$generic = (string) file_get_contents( CR_SERIES_THEME_DIR . '/templates/taxonomy-series.html' );
	check( false === strpos( $generic, 'cr-series-methods' ), 'other series archives carry no methods callout' );

	$body_pattern = (string) file_get_contents( CR_SERIES_THEME_DIR . '/patterns/cr-series-body.php' );
	check( strpos( $body_pattern, 'cr-series-rule' ) < strpos( $body_pattern, 'wp:query ' ), 'the orange rule sits above the parts loop' );
	check( false === strpos( $body_pattern, 'No posts in this stream' ) && false !== strpos( $body_pattern, '"view":"launch"' ), 'no results shows the series launch state, not the Stream copy' );

	$single  = (string) file_get_contents( CR_SERIES_THEME_DIR . '/templates/single.html' );
	$content = strpos( $single, 'wp:post-content' );
	$nav     = strpos( $single, '"view":"post-nav"' );
	$head    = strpos( $single, '"view":"post-header"' );
	check( false !== $head && $head < $content, 'series header renders above the post body' );
	check( false !== $nav && $nav > $content && $nav < strpos( $single, 'wp:template-part {"slug":"kind"}' ), 'part navigation renders right after the post body and its footnotes' );

	$methods_pattern = (string) file_get_contents( CR_SERIES_THEME_DIR . '/patterns/cr-series-methods.php' );
	check( false === strpos( $methods_pattern, 'cr-callout' ), 'methods callout avoids the cr-callout class the collapse toggle rewrites' );
	check( false !== strpos( $methods_pattern, 'href="/merge-conflicts-methods/"' ), 'methods link points at /merge-conflicts-methods/' );

	// Accessibility Checker Pro's aria_hidden_valid_usage flags aria-hidden without role="presentation" (house rule since 0.7.138).
	$views = (string) file_get_contents( CR_SERIES_THEME_DIR . '/inc/series-views.php' );
	preg_match_all( '/<[a-z]+\b[^>]*aria-hidden="true"[^>]*>/', $views, $hidden );
	$bare = array_filter( $hidden[0], static fn( string $tag ): bool => false === strpos( $tag, 'role="presentation"' ) );
	check( count( $hidden[0] ) > 0 && ! $bare, 'every aria-hidden element in the series views carries role="presentation"' );

	// Reading time: 238 words a minute, rounded up, never under one.
	$GLOBALS['cr_post_content'] = array(
		1 => '<p>' . str_repeat( 'word ', 476 ) . '</p>',
		2 => '<p>' . str_repeat( 'word ', 477 ) . '</p>',
		3 => '',
	);
	check( 2 === reading_minutes( 1 ) && 3 === reading_minutes( 2 ), 'reading time rounds up at 238 words a minute' );
	check( 1 === reading_minutes( 3 ), 'an empty post still reads as one minute' );

	// Season grouping and the numeral run on the rendered blocks.
	$hooked = static function ( string $hook, string $fn ): bool {
		foreach ( $GLOBALS['cr_hooks'][ $hook ] ?? array() as $entry ) {
			if ( 'Courtneyr\\Child\\Series\\' . $fn === $entry[0] ) {
				return true;
			}
		}
		return false;
	};
	check( $hooked( 'render_block_core/post-template', 'group_parts' ), 'group_parts wraps the post template in season sections' );
	check( $hooked( 'render_block_core/paragraph', 'hide_part_numeral' ), 'hide_part_numeral hides the big numeral from screen readers' );

	// Rows: numeral first, the "Part 3" label visually hidden, part titles one level under the season h2.
	check( strpos( $body_pattern, '"key":"numeral"' ) < strpos( $body_pattern, 'card__body' ), 'the numeral leads each row' );
	check( 1 === preg_match( '/"key":"part"[^>]*"className":"screen-reader-text cr-series-card__part"/', $body_pattern ), 'the part label stays in the text, visually hidden' );
	check( false !== strpos( $body_pattern, 'wp:post-title {"level":3' ), 'part titles are h3 under the season h2' );
	check( false !== strpos( $body_pattern, '"key":"read"' ), 'rows show a reading time' );

	// Front matter on the Merge Conflicts landing: masthead, methods file, contents, parts, one follow block.
	$order = array(
		'cr-mc__hero',
		'cr-mc__methods',
		'"view":"publication-contents"',
		'"view":"publication-parts"',
		'"view":"follow"',
	);
	$at    = array_map( static fn( string $needle ) => strpos( $publication_pattern, $needle ), $order );
	check( ! in_array( false, $at, true ) && $at === array_values( array_unique( $at ) ) && $at == array_values( ( static function ( array $v ): array { sort( $v ); return $v; } )( $at ) ), 'landing order: masthead, methods, contents, parts, follow' );
	check( false !== strpos( $landing, 'courtneyr-child/cr-series-mc-publication' ) && false === strpos( $generic, 'cr-series-mc-publication' ), 'the publication composition belongs only to the Merge Conflicts archive' );

	$direct          = new \WP_Term();
	$direct->term_id = 2175;
	$direct->slug    = 'merge-conflicts';
	$GLOBALS['cr_parts_posts'] = array(
		201 => array( 'series' => 2175, 'status' => 'publish', 'password' => '', 'part' => 2, 'title' => 'Rules & <exceptions>' ),
		202 => array( 'series' => 2175, 'status' => 'publish', 'password' => '', 'part' => 1, 'title' => 'Who decides?' ),
		203 => array( 'series' => 2175, 'status' => 'draft', 'password' => '', 'part' => 3, 'title' => 'Draft title' ),
		204 => array( 'series' => 2175, 'status' => 'future', 'password' => '', 'part' => 4, 'title' => 'Scheduled title' ),
		205 => array( 'series' => 2175, 'status' => 'private', 'password' => '', 'part' => 5, 'title' => 'Private title' ),
		206 => array( 'series' => 2175, 'status' => 'publish', 'password' => 'secret', 'part' => 6, 'title' => 'Protected title' ),
		207 => array( 'series' => 9999, 'status' => 'publish', 'password' => '', 'part' => 7, 'title' => 'Other series' ),
		208 => array( 'series' => 2175, 'status' => 'publish', 'password' => '', 'part' => null, 'title' => 'Another published part' ),
	);
	$contents = render_publication_contents( $direct );
	check( false !== strpos( $contents, '<nav ' ) && false !== strpos( $contents, '<ol ' ) && 3 === substr_count( $contents, '<li ' ), 'direct series posts populate a semantic contents list without seasons' );
	check( strpos( $contents, 'post-202/' ) < strpos( $contents, 'post-201/' ) && strpos( $contents, 'post-201/' ) < strpos( $contents, 'post-208/' ), 'direct contents keep part order with unnumbered posts afterward' );
	check( false !== strpos( $contents, 'Rules &amp; &lt;exceptions&gt;' ) && false === strpos( $contents, 'Season ' ), 'direct contents escape real titles and invent no season assignment' );
	check( false === strpos( $contents, 'Draft title' ) && false === strpos( $contents, 'Scheduled title' ) && false === strpos( $contents, 'Private title' ) && false === strpos( $contents, 'Protected title' ) && false === strpos( $contents, 'Other series' ), 'contents exclude drafts, scheduled, private, password-protected and unrelated posts' );
	$query = $GLOBALS['cr_parts_queries'][0] ?? array();
	check( 'publish' === ( $query['post_status'] ?? '' ) && false === ( $query['has_password'] ?? true ) && 2175 === ( $query['tax_query'][0]['terms'] ?? 0 ) && false === ( $query['tax_query'][0]['include_children'] ?? true ), 'contents query only password-free published posts assigned to the exact series term' );

	$direct->name = 'Merge Conflicts';
	$GLOBALS['cr_post_series'][202] = $direct;
	$drawer = render_post_header( 202 );
	$dom    = new \DOMDocument();
	@$dom->loadHTML( '<?xml encoding="UTF-8">' . $drawer );
	$xpath   = new \DOMXPath( $dom );
	$details = $xpath->query( '//details[@class="cr-series-post__toc"]' );
	$summary = $xpath->query( '//summary[@class="cr-series-post__summary"]' );
	check( 1 === $details->length && ! $details->item( 0 )->hasAttribute( 'open' ) && 1 === $summary->length, 'the post series drawer uses a closed native details disclosure' );
	check( 'In this series 3 parts published' === trim( $summary->item( 0 )->textContent ) && 1 === $xpath->query( '//summary/span[@class="cr-series-post__summary-heading"]/span[@class="cr-series-post__summary-label" and text()="In this series"]' )->length && 1 === $xpath->query( '//summary/span[@class="cr-series-post__summary-heading"]/span[@class="cr-series-post__summary-count" and text()="3 parts published"]' )->length, 'the summary groups its label and published count without parentheses' );
	check( 1 === $xpath->query( '//summary//svg[@role="presentation" and @aria-hidden="true" and @focusable="false"]' )->length, 'the summary chevron is decorative and unfocusable' );
	$links = $xpath->query( '//nav[@class="cr-series-post__contents"]//li/div[@class="cr-series-post__entry-copy"]/a' );
	check( 3 === $links->length && 'https://example.test/post-202/' === $links->item( 0 )->getAttribute( 'href' ) && 'https://example.test/post-201/' === $links->item( 1 )->getAttribute( 'href' ) && 'https://example.test/post-208/' === $links->item( 2 )->getAttribute( 'href' ), 'the post drawer lists only real published links in reading order' );
	check( 3 === $xpath->query( '//li/span[@class="cr-series-post__num" and @aria-hidden="true" and @role="presentation"]' )->length && 3 === $xpath->query( '//nav//a/span[@class="screen-reader-text"]' )->length && 'Part 1: Who decides?' === $links->item( 0 )->textContent, 'decorative two-digit numbers do not duplicate the spoken part labels' );
	check( 1 === $xpath->query( '//nav//a[@aria-current="page" and @href="https://example.test/post-202/"]' )->length && 1 === $xpath->query( '//div[@class="cr-series-post__entry-copy"]/p[@class="cr-series-post__here" and text()="You’re reading this"]' )->length, 'only the current part gets aria-current and reading metadata beside its link' );
	check( false !== strpos( $drawer, '>01</span>' ) && false !== strpos( $drawer, 'Rules &amp; &lt;exceptions&gt;' ) && false === strpos( $drawer, 'Draft title' ) && false === strpos( $drawer, 'Protected title' ), 'the drawer escapes post titles and excludes unpublished or protected entries' );
	check( '' === render_post_header( 999 ), 'posts outside a series have no series drawer' );

	$empty          = new \WP_Term();
	$empty->term_id = 2176;
	$empty->slug    = 'empty-series';
	check( false !== strpos( render_publication_contents( $empty ), 'Eight seasons are planned.' ), 'a series without published posts or announced seasons keeps the planned message' );

	$seasonal         = new \WP_Term();
	$seasonal->term_id = 2177;
	$seasonal->slug    = 'seasonal-series';
	$group            = new \WP_Term();
	$group->term_id    = 3177;
	$group->slug       = 'seasonal-series';
	$season           = new \WP_Term();
	$season->term_id   = 2178;
	$season->slug      = 'first-season';
	$season->name      = 'First season';
	$planned          = new \WP_Term();
	$planned->term_id  = 2179;
	$planned->slug     = 'planned-season';
	$planned->name     = 'Planned season';
	$GLOBALS['cr_taxonomies']['series_group'] = true;
	$GLOBALS['cr_terms'] = array( 'series_group' => array( 3177 => $group ), 'series' => array( 2178 => $season, 2179 => $planned ) );
	$GLOBALS['cr_group_objects'][3177] = array( 4178, 4179 );
	$GLOBALS['cr_shadow_posts'] = array(
		4178 => (object) array( 'post_type' => 'series_grouping', 'post_status' => 'publish', 'post_name' => 'series_grouping_2178' ),
		4179 => (object) array( 'post_type' => 'series_grouping', 'post_status' => 'publish', 'post_name' => 'series_grouping_2179' ),
	);
	$GLOBALS['cr_parts_posts'][209] = array( 'series' => 2178, 'status' => 'publish', 'password' => '', 'part' => 1, 'title' => 'Season post' );
	$contents = render_publication_contents( $seasonal );
	check( false !== strpos( $contents, 'href="https://example.test/series/first-season/"' ) && false !== strpos( $contents, '1 part' ), 'announced seasons retain their archive links and published counts' );
	check( false !== strpos( $contents, 'Planned season' ) && false !== strpos( $contents, 'Upcoming' ), 'unpublished seasons stay upcoming' );
	check( 2 === substr_count( $contents, '<details' ) && 1 === substr_count( $contents, '<details class="cr-mc__fold-details" open>' ), 'each season folds; only a season with published parts opens by default' );
	check( false !== strpos( $contents, 'post-209/' ) && false !== strpos( $contents, 'Season 1, Part 1: ' ) && false !== strpos( $contents, '#cr-series-follow' ), 'an open season lists its parts with full link names; an upcoming one points to the follow block' );
	// A second landing whose own parts become Season 1 (fresh IDs: parts() and collection() cache per term).
	$own_landing          = new \WP_Term();
	$own_landing->term_id = 2187;
	$own_landing->slug    = 'own-parts-series';
	$own_group            = new \WP_Term();
	$own_group->term_id   = 3187;
	$own_group->slug      = 'own-parts-series';
	$own_season           = new \WP_Term();
	$own_season->term_id  = 2188;
	$own_season->slug     = 'own-first-season';
	$own_season->name     = 'First season';
	$own_planned          = new \WP_Term();
	$own_planned->term_id = 2189;
	$own_planned->slug    = 'own-planned-season';
	$own_planned->name    = 'Planned season';
	$GLOBALS['cr_terms']['series_group'][3187] = $own_group;
	$GLOBALS['cr_terms']['series'][2188]      = $own_season;
	$GLOBALS['cr_terms']['series'][2189]      = $own_planned;
	$GLOBALS['cr_group_objects'][3187]        = array( 4188, 4189 );
	$GLOBALS['cr_shadow_posts'][4188]         = (object) array( 'post_type' => 'series_grouping', 'post_status' => 'publish', 'post_name' => 'series_grouping_2188' );
	$GLOBALS['cr_shadow_posts'][4189]         = (object) array( 'post_type' => 'series_grouping', 'post_status' => 'publish', 'post_name' => 'series_grouping_2189' );
	$GLOBALS['cr_parts_posts'][210] = array( 'series' => 2187, 'status' => 'publish', 'password' => '', 'part' => 1, 'title' => 'Landing post' );
	$with_own = render_publication_contents( $own_landing );
	check( false !== strpos( $with_own, 'post-210/' ) && false !== strpos( $with_own, 'Season 1, Part 1: ' ) && false !== strpos( $with_own, '>Season 2</span><span class="cr-mc__fold-name">First season' ) && false !== strpos( $with_own, '>Season 3</span>' ), 'parts on the landing are Season 1 and the season terms count from 2' );
	unset( $GLOBALS['cr_parts_posts'][210] );
	$GLOBALS['cr_taxonomies'] = array();
	check( false === strpos( $generic, 'cr-series-mc-masthead' ) && false === strpos( $generic, '"view":"contents"' ) && false === strpos( $generic, '"view":"follow"' ), 'other series archives carry no front matter' );

	$masthead = (string) file_get_contents( CR_SERIES_THEME_DIR . '/patterns/cr-series-mc-masthead.php' );
	check( false !== strpos( $masthead, '"view":"start"' ) && strpos( $masthead, 'wp:query-title' ) < strpos( $masthead, '"view":"start"' ), 'the masthead puts the way in after the title and thesis' );
	check( 3 === substr_count( $masthead, '<strong>' ), 'the thesis marks its three questions' );

	$contents_pattern = (string) file_get_contents( CR_SERIES_THEME_DIR . '/patterns/cr-series-contents.php' );
	check( false !== strpos( $contents_pattern, 'cr-series-intro' ) && false !== strpos( $contents_pattern, '"view":"contents"' ), 'the contents section has an introduction and the contents list' );
	check( false !== strpos( $contents_pattern, 'Backdrop, the CSS and HTML standards bodies, Drupal, Ghost, Joomla, MariaDB, MySQL, Node.js, OpenJS, PHP, TYPO3 and WordPress' ), 'the comparison set is alphabetical, WordPress unhighlighted (methods draft fairness rule)' );
	check( false !== strpos( $methods_pattern, 'cr-series-methods__list' ) && false === strpos( $methods_pattern, 'methods-art' ), 'the methods file lists what it holds, without the notebook drawing' );

	check( $hooked( 'render_block_core/group', 'skip_front_matter_when_paged' ), 'page 2 on skips the methods file and the introduction' );

	$guide = json_decode( (string) file_get_contents( CR_SERIES_THEME_DIR . '/blocks/series-guide/block.json' ), true );
	$views = $guide['attributes']['view']['enum'] ?? array();
	check( ! array_diff( array( 'start', 'contents', 'follow' ), $views ) && ! array_intersect( array( 'hero-art', 'methods-art', 'margin-art' ), $views ), 'series-guide accepts start, contents and follow, and no art views' );

	$pruning = (string) file_get_contents( CR_SERIES_THEME_DIR . '/inc/stream-style-pruning.php' );
	check( false !== strpos( $pruning, "'courtneyr-series-front'" ) && false === strpos( $pruning, "'courtneyr-series-art'" ), 'the front-matter sheet is kept whole by the used-CSS pass' );

	// Series image: PublishPress stores the URL without the home URL; the theme rebuilds it and finds the attachment.
	$with_image          = new \WP_Term();
	$with_image->term_id = 1175;
	$no_image            = new \WP_Term();
	$no_image->term_id   = 1176;
	$GLOBALS['cr_icons']       = array( 1175 => 'wp-content/uploads/2026/11/merge-conflicts.png' );
	$GLOBALS['cr_attachments'] = array( 'https://example.test/wp-content/uploads/2026/11/merge-conflicts.png' => 4242 );
	$image                     = series_image( $with_image );
	check(
		array(
			'id'  => 4242,
			'url' => 'https://example.test/wp-content/uploads/2026/11/merge-conflicts.png',
		) === $image,
		'series_image rebuilds the stored path into a URL and finds its attachment'
	);
	check( null === series_image( $no_image ), 'a series without an image returns null' );

	// Share image: Yoast's own term image wins; otherwise the series image goes in by attachment ID.
	check( $hooked( 'wpseo_add_opengraph_additional_images', 'series_share_image' ), 'the share-image hook runs after Yoast reads the term image' );
	$GLOBALS['cr_is_tax']  = true;
	$GLOBALS['cr_queried'] = $with_image;
	$yoast_has_one         = new \CR_Fake_Images();
	$yoast_has_one->start  = true;
	series_share_image( $yoast_has_one );
	check( array() === $yoast_has_one->added, 'a Yoast term image set by hand stays the share image' );
	$empty = new \CR_Fake_Images();
	series_share_image( $empty );
	check( array( array( 'id', 4242 ) ) === $empty->added, 'with no Yoast image, the series image becomes the share image' );
	$GLOBALS['cr_queried'] = $no_image;
	$none                  = new \CR_Fake_Images();
	series_share_image( $none );
	check( array() === $none->added, 'no series image leaves Yoast to its default card' );
	$GLOBALS['cr_is_tax'] = false;

	check( strpos( $masthead, '"view":"start"' ) < strpos( $masthead, '"view":"cover"' ), 'the masthead places the series image after the way in' );


	// Feed: a landing's feed carries its own parts, its seasons and the extras; companion arcs keep their own feeds.
	check( $hooked( 'pre_get_posts', 'landing_feed_query' ), 'landing_feed_query runs on pre_get_posts' );
	$GLOBALS['cr_taxonomies'] = array( 'series' => true, 'series_group' => true );
	$feed_landing = new \WP_Term();
	$feed_landing->term_id = 5100;
	$feed_landing->slug    = 'feed-landing';
	$feed_group            = new \WP_Term();
	$feed_group->term_id   = 6100;
	$feed_group->slug      = 'feed-landing';
	$feed_group->taxonomy  = 'series_group';
	$feed_season           = new \WP_Term();
	$feed_season->term_id  = 5102;
	$feed_season->slug     = 'feed-landing-season-2';
	$feed_companion        = new \WP_Term();
	$feed_companion->term_id  = 6101;
	$feed_companion->slug     = 'mc-companion';
	$feed_companion->parent   = 6100;
	$feed_companion->taxonomy = 'series_group';
	$feed_extras_group     = new \WP_Term();
	$feed_extras_group->term_id  = 6102;
	$feed_extras_group->slug     = 'mc-extras';
	$feed_extras_group->parent   = 6100;
	$feed_extras_group->taxonomy = 'series_group';
	$feed_arc              = new \WP_Term();
	$feed_arc->term_id     = 5103;
	$feed_arc->slug        = 'mc-companion-1-before-you-join';
	$feed_extras           = new \WP_Term();
	$feed_extras->term_id  = 5104;
	$feed_extras->slug     = 'feed-landing-extras';
	$GLOBALS['cr_terms']['series_group'][6100] = $feed_group;
	$GLOBALS['cr_terms']['series'][5102]       = $feed_season;
	$GLOBALS['cr_terms']['series'][5103]       = $feed_arc;
	$GLOBALS['cr_terms']['series'][5104]       = $feed_extras;
	$GLOBALS['cr_child_groups'][6100]          = array( $feed_companion, $feed_extras_group );
	$GLOBALS['cr_group_objects'][6100]         = array( 7100, 7102 );
	$GLOBALS['cr_group_objects'][6101]         = array( 7103 );
	$GLOBALS['cr_group_objects'][6102]         = array( 7104 );
	foreach ( array( 7100 => 5100, 7102 => 5102, 7103 => 5103, 7104 => 5104 ) as $shadow => $series_id ) {
		$GLOBALS['cr_shadow_posts'][ $shadow ] = (object) array( 'post_type' => 'series_grouping', 'post_status' => 'publish', 'post_name' => 'series_grouping_' . $series_id );
	}
	$feed_posts = array( 5100 => 'landing post', 5102 => 'season post', 5104 => 'extras post', 5103 => 'companion post' );
	$feed_query          = new \WP_Query();
	$feed_query->feed    = true;
	$feed_query->queried = $feed_landing;
	landing_feed_query( $feed_query );
	$feed_terms = $feed_query->vars['tax_query'][0]['terms'] ?? array();
	$in_feed    = array_values( array_intersect_key( $feed_posts, array_flip( $feed_terms ) ) );
	check( array( 'landing post', 'season post', 'extras post' ) === $in_feed, 'the landing feed carries the landing, season and extras posts, not the companion post' );
	check( '' === ( $feed_query->vars['series'] ?? null ) && false === ( $feed_query->vars['tax_query'][0]['include_children'] ?? true ) && ! isset( $feed_query->vars['orderby'] ), 'the feed swaps the single-term var for an exact tax query and keeps date order' );
	$archive_query          = new \WP_Query();
	$archive_query->queried = $feed_landing;
	landing_feed_query( $archive_query );
	check( array() === $archive_query->vars, 'the landing archive page is left to order_series_archive' );
	$season_query          = new \WP_Query();
	$season_query->feed    = true;
	$season_query->queried = $feed_season;
	landing_feed_query( $season_query );
	check( array() === $season_query->vars, 'a season feed keeps its own term' );
	$lone          = new \WP_Term();
	$lone->term_id = 5199;
	$lone->slug    = 'no-category-series';
	$lone_query          = new \WP_Query();
	$lone_query->feed    = true;
	$lone_query->queried = $lone;
	landing_feed_query( $lone_query );
	check( array() === $lone_query->vars, 'a series with no category keeps the core feed query' );
	$GLOBALS['cr_taxonomies'] = array();

	echo $failures ? "\n{$failures} failed\n" : "\nall passed\n";
	exit( $failures ? 1 : 0 );
}
