<?php
/**
 * `php tests/php/table-test.php`: checks the Table block's column labels and
 * roles (inc/table.php) without loading WordPress, using the HTML API from
 * the WordPress core that CI unpacks (CR_WP_CORE).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['cr_hooks'] = array();

	/** Record a filter. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][] = $callback;
		return true;
	}

	/** Translation stubs the HTML API calls. */
	function __( $text ) {
		return $text;
	}

	/** Stub. */
	function _doing_it_wrong() {}

	/** Stub: no filters run in this test. */
	function apply_filters( $hook, $value ) {
		return $value;
	}

	/** Stub. */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}

	$wp = getenv( 'CR_WP_CORE' ) ?: ( getenv( 'CR_WP_DIR' ) ?: '' );
	if ( '' === $wp || ! is_readable( $wp . '/wp-includes/html-api/class-wp-html-tag-processor.php' ) ) {
		echo "SKIP set CR_WP_CORE to a WordPress checkout for the HTML API\n";
		exit( 0 );
	}
	require_once $wp . '/wp-includes/compat.php';
	require_once $wp . '/wp-includes/utf8.php';
	require_once $wp . '/wp-includes/kses.php';
	require_once $wp . '/wp-includes/class-wp-token-map.php';
	require_once $wp . '/wp-includes/html-api/html5-named-character-references.php';
	foreach ( array( 'class-wp-html-attribute-token', 'class-wp-html-span', 'class-wp-html-text-replacement', 'class-wp-html-decoder', 'class-wp-html-tag-processor' ) as $file ) {
		require_once $wp . '/wp-includes/html-api/' . $file . '.php';
	}
	require dirname( __DIR__, 2 ) . '/inc/table.php';
}

namespace Courtneyr\Child\Table\Tests {

	use function Courtneyr\Child\Table\header_labels;
	use function Courtneyr\Child\Table\label_cells;

	$failures = 0;

	/** Report one check. */
	function check( bool $ok, string $label ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
		if ( ! $ok ) {
			++$failures;
		}
	}

	$html = '<figure class="wp-block-table"><table><thead><tr><th>Question</th><th>Free software, per the <abbr>FSF</abbr></th><th>Open &amp; source</th></tr></thead>'
		. '<tbody><tr><td>Same programs?</td><td>Yes<sup>30</sup></td><td>“two terms”</td></tr>'
		. '<tr><th scope="row">Price</th><td colspan="2">Both</td></tr></tbody></table><figcaption class="wp-element-caption">Cap</figcaption></figure>';

	check( array( 'Question', 'Free software, per the FSF', 'Open & source' ) === header_labels( $html ), 'header texts, nested markup and entities flattened' );
	$out = label_cells( $html );
	check( false !== strpos( $out, '<table role="table">' ), 'table role stated' );
	check( 2 === substr_count( $out, 'role="rowgroup"' ) && 3 === substr_count( $out, 'role="row"' ), 'row groups and rows stated' );
	check( 3 === substr_count( $out, 'role="columnheader"' ), 'header cells are column headers' );
	check( 1 === preg_match( '/<td(?=[^>]*role="cell")(?=[^>]*data-cr-label="Free software, per the FSF")[^>]*>Yes/', $out ), 'a body cell carries its column name' );
	check( false !== strpos( $out, 'data-cr-label="Open &amp; source"' ), 'labels are attribute-escaped' );
	check( 1 === preg_match( '/<th(?=[^>]*scope="row")(?=[^>]*role="rowheader")(?=[^>]*data-cr-label="Question")[^>]*>Price/', $out ), 'a row header keeps its scope and gets its column name' );
	check( 0 === substr_count( substr( $out, strpos( $out, '<thead' ), strpos( $out, '</thead>' ) - strpos( $out, '<thead' ) ), 'data-cr-label' ), 'header cells carry no label' );
	$plain = '<figure class="wp-block-table"><table><tbody><tr><td>a</td></tr></tbody></table></figure>';
	check( $plain === label_cells( $plain ), 'a table without a header row is left as it is' );
	check( isset( $GLOBALS['cr_hooks']['render_block_core/table'] ), 'render filter registered' );

	exit( $failures ? 1 : 0 );
}
