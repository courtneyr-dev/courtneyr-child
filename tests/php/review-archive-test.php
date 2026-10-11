<?php
/**
 * Standalone checks for the review archive adapter.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/review-bootstrap.php';

/** Return the enqueued styles for one request. */
function cr_review_archive_assets_for( array $request ): array {
	cr_review_reset();
	$GLOBALS['cr_review_request'] = array_merge( $GLOBALS['cr_review_request'], $request );
	$GLOBALS['cr_review_enqueued'] = array();
	cr_review_fire_hook( 'enqueue_block_assets' );
	// Other kind modules loaded by the bootstrap enqueue their own styles; this test is about the review handle.
	return array_values( array_filter( $GLOBALS['cr_review_enqueued'], static fn( array $style ): bool => 'courtneyr-review' === $style[0] ) );
}

/** The sectioned entry fixture. */
function cr_review_archive_entry( string $img = '<img class="pkiw-review-entry__cover" src="https://example.test/cover.jpg" alt="" loading="lazy" decoding="async" />' ): string {
	return '<li class="wp-block-post h-review"><div class="pkiw-review-entry"><figure class="pkiw-review-entry__figure">' . $img . '</figure><h3 class="pkiw-review-entry__title p-name"><a class="u-url" href="https://example.test/review/42/">Title</a></h3><p class="pkiw-review-entry__item">Item Name</p><data class="dt-published" value="2026-09-18T12:00:00+00:00" hidden></data><span hidden>HIDDEN AUTHOR H-CARD</span></div></li>';
}

if ( in_array( '--no-plugin', $argv ?? array(), true ) ) {
	cr_review_group(
		'fallback rating helper without plugin class',
		static function (): void {
			$facts = array( 'rating' => 3.5, 'best' => 5, 'rating_label' => 'Rated 3.5 of 5' );
			$html  = \Courtneyr\Child\Review\rating_html( $facts );
			cr_review_same( '<p class="pkiw-review-rating"><span class="pkiw-review-rating__label">Rated 3.5 of 5</span></p>', $html, 'fallback label paragraph' );
			cr_review_same( array(), \Courtneyr\Child\Review\facts( 42 ), 'facts empty without plugin function' );
		}
	);
	cr_review_finish();
}

cr_review_group(
	'enqueue_styles gates the review stylesheet to review surfaces',
	static function (): void {
		$expected = array( 'courtneyr-review', COURTNEYR_CHILD_URI . '/assets/css/cr-review.css', array( 'courtneyr-paper' ), COURTNEYR_CHILD_VERSION );
		foreach ( array(
			'archive' => array( 'kind_archive' => 'review' ),
			'single'  => array( 'single' => true, 'post_kind' => 'review' ),
			'stream'  => array( 'stream' => true ),
			'admin'   => array( 'admin' => true ),
		) as $label => $request ) {
			cr_review_same( array( $expected ), cr_review_archive_assets_for( $request ), $label . ' enqueues review style' );
		}
		foreach ( array(
			'read archive'      => array( 'kind_archive' => 'read' ),
			'plain page'        => array(),
			'non-review single' => array( 'single' => true, 'post_kind' => 'read', 'post_kinds' => array( 42 => array( 'read' ) ) ),
			'front end'         => array( 'stream' => false ),
		) as $label => $request ) {
			cr_review_same( array(), cr_review_archive_assets_for( $request ), $label . ' enqueues nothing' );
		}
	}
);

cr_review_group(
	'hooks are registered with the frozen callbacks',
	static function (): void {
		cr_review_contains( serialize( array( 'enqueue_block_assets', 'Courtneyr\\Child\\Review\\enqueue_styles', 10, 1 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'enqueue hook' );
		cr_review_contains( serialize( array( 'body_class', 'Courtneyr\\Child\\Review\\body_class', 10, 1 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'body class hook' );
		cr_review_contains( serialize( array( 'render_block_post-kinds-indieweb/review-entry', 'Courtneyr\\Child\\Review\\prepare_entry', 10, 3 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'review entry hook' );
	}
);

cr_review_group(
	'body_class adds only the relevant review classes once',
	static function (): void {
		cr_review_reset();
		$GLOBALS['cr_review_request']['kind_archive'] = 'review';
		$classes = \Courtneyr\Child\Review\body_class( array( 'site' ) );
		cr_review_contains( 'cr-review-archive', implode( ' ', $classes ), 'archive body class' );
		cr_review_same( $classes, \Courtneyr\Child\Review\body_class( $classes ), 'archive class not duplicated' );
		cr_review_reset();
		$GLOBALS['cr_review_request']['single'] = true;
		$classes = \Courtneyr\Child\Review\body_class( array( 'single' ) );
		cr_review_contains( 'cr-review-single', implode( ' ', $classes ), 'single body class' );
		cr_review_same( $classes, \Courtneyr\Child\Review\body_class( $classes ), 'single class not duplicated' );
		cr_review_reset();
		$GLOBALS['cr_review_request']['post_kind'] = 'read';
		$GLOBALS['cr_review_request']['post_kinds'] = array( 42 => array( 'read' ) );
		cr_review_same( array( 'plain' ), \Courtneyr\Child\Review\body_class( array( 'plain' ) ), 'other request unchanged' );
	}
);

cr_review_group(
	'request helpers and facts read only the plugin facts helper',
	static function (): void {
		cr_review_reset();
		$GLOBALS['cr_review_request']['kind_archive'] = 'review';
		cr_review_same( true, \Courtneyr\Child\Review\is_review_archive(), 'review archive true' );
		cr_review_same( false, \Courtneyr\Child\Review\is_review_single(), 'archive is not single' );
		cr_review_reset();
		$GLOBALS['cr_review_request']['single'] = true;
		cr_review_same( true, \Courtneyr\Child\Review\is_review_single(), 'review single true' );
		$GLOBALS['cr_review_request']['post_kinds'][42] = array( 'read' );
		cr_review_same( false, \Courtneyr\Child\Review\is_review_single(), 'non-review single false' );
		$GLOBALS['cr_review_facts'][42]['item_name'] = 'Plugin facts';
		cr_review_same( 'Plugin facts', \Courtneyr\Child\Review\facts( 42 )['item_name'] ?? '', 'facts from plugin' );
	}
);

cr_review_group(
	'prepare_entry marks fallback figures and chooses eager covers per request',
	static function (): void {
		cr_review_reset();
		\Courtneyr\Child\Review\reset_page_state();
		$first = \Courtneyr\Child\Review\prepare_entry( cr_review_archive_entry(), array(), null );
		cr_review_contains( 'data-cr-cover-fallback="true"', $first, 'figure fallback marker' );
		cr_review_contains( 'loading="eager"', $first, 'first cover eager' );
		cr_review_contains( 'fetchpriority="high"', $first, 'first cover high priority' );
		cr_review_contains( 'src="https://example.test/cover.jpg"', $first, 'src unchanged' );
		cr_review_contains( 'alt=""', $first, 'empty alt unchanged' );
		cr_review_contains( 'class="pkiw-review-entry__cover"', $first, 'class unchanged' );
		cr_review_contains( 'decoding="async"', $first, 'decoding unchanged' );
		\Courtneyr\Child\Review\prepare_entry( cr_review_archive_entry(), array(), null );
		\Courtneyr\Child\Review\prepare_entry( cr_review_archive_entry(), array(), null );
		$fourth = \Courtneyr\Child\Review\prepare_entry( cr_review_archive_entry( '<img class="pkiw-review-entry__cover" src="https://example.test/cover.jpg" alt="" loading="eager" fetchpriority="high" decoding="async" />' ), array(), null );
		cr_review_contains( 'loading="lazy"', $fourth, 'fourth cover lazy' );
		cr_review_not_contains( 'fetchpriority=', $fourth, 'fourth cover loses fetchpriority' );
		cr_review_contains( '<data class="dt-published" value="2026-09-18T12:00:00+00:00" hidden></data><span hidden>HIDDEN AUTHOR H-CARD</span>', $fourth, 'hidden data kept' );
		$plain = '<li><div class="pkiw-review-entry"><h3>No cover</h3></div></li>';
		cr_review_same( $plain, \Courtneyr\Child\Review\prepare_entry( $plain, array(), null ), 'no figure byte-for-byte unchanged' );
		\Courtneyr\Child\Review\reset_page_state();
		$reset = \Courtneyr\Child\Review\prepare_entry( cr_review_archive_entry(), array(), null );
		cr_review_contains( 'loading="eager"', $reset, 'reset restarts count' );
	}
);

cr_review_group(
	'rating_html delegates to the plugin and suppresses zero ratings',
	static function (): void {
		cr_review_same( '', \Courtneyr\Child\Review\rating_html( array( 'rating' => 0, 'rating_label' => '' ) ), 'zero rating empty' );
		$html = \Courtneyr\Child\Review\rating_html( array( 'rating' => 3.5, 'best' => 5, 'rating_label' => 'Rated 3.5 of 5' ) );
		cr_review_contains( '<p class="pkiw-review-rating">', $html, 'plugin paragraph' );
		cr_review_contains( '<span class="pk-stars" aria-hidden="true">', $html, 'plugin stars' );
		cr_review_contains( 'class="half"', $html, 'plugin half star' );
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-plugin';
		exec( $cmd, $output, $status );
		cr_review_same( 0, $status, 'fallback subprocess exit' );
		cr_review_contains( 'all passed', implode( "\n", $output ), 'fallback subprocess passed' );
	}
);

cr_review_finish();
