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

	require CR_SERIES_THEME_DIR . '/inc/series.php';
}

namespace Courtneyr\Child\Series\Tests {

	use function Courtneyr\Child\Series\part_key;
	use function Courtneyr\Child\Series\part_label;
	use function Courtneyr\Child\Series\published_count_label;
	use function Courtneyr\Child\Series\reading_minutes;
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
	$header  = strpos( $landing, 'courtneyr-child/cr-series-header' );
	$methods = strpos( $landing, 'courtneyr-child/cr-series-methods' );
	$body    = strpos( $landing, 'courtneyr-child/cr-series-body' );
	check( false !== $header && false !== $methods && false !== $body && $header < $methods && $methods < $body, 'Merge Conflicts landing: header, then methods, then the parts' );

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

	// Collage art: hero art above the header, margin art between the methods band and the parts.
	$hero_art   = strpos( $landing, '"view":"hero-art"' );
	$margin_art = strpos( $landing, '"view":"margin-art"' );
	check( false !== $hero_art && $hero_art < $header, 'hero art renders before the header' );
	check( false !== $margin_art && $methods < $margin_art && $margin_art < $body, 'margin art sits between the methods band and the parts' );
	check( false === strpos( $generic, '-art"' ), 'other series archives carry no collage art' );
	check( false !== strpos( $methods_pattern, '"view":"methods-art"' ), 'the methods band carries the notebook' );

	$guide = json_decode( (string) file_get_contents( CR_SERIES_THEME_DIR . '/blocks/series-guide/block.json' ), true );
	check( ! array_diff( array( 'hero-art', 'methods-art', 'margin-art' ), $guide['attributes']['view']['enum'] ?? array() ), 'series-guide accepts the three art views' );

	$pruning = (string) file_get_contents( CR_SERIES_THEME_DIR . '/inc/stream-style-pruning.php' );
	check( false !== strpos( $pruning, "'courtneyr-series-art'" ), 'the art sheet is kept whole by the used-CSS pass' );

	echo $failures ? "\n{$failures} failed\n" : "\nall passed\n";
	exit( $failures ? 1 : 0 );
}
