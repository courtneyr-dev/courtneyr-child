<?php
/**
 * Standalone checks for the review Stream clipping adapter.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/review-bootstrap.php';

/** Put the harness on a Stream review request. */
function cr_review_stream_request( int $post_id = 42 ): WP_Post {
	$post = cr_review_reset();
	if ( $post_id !== $post->ID ) {
		$post = new WP_Post( $post_id, 'Stream review', '' );
		$GLOBALS['cr_review_post'] = $post;
		$GLOBALS['cr_review_posts'][ $post_id ] = $post;
	}
	$GLOBALS['cr_review_request']['stream'] = true;
	$GLOBALS['cr_review_request']['the_id'] = $post_id;
	$GLOBALS['cr_review_request']['post_kind'] = 'review';
	$GLOBALS['cr_review_request']['post_kinds'][ $post_id ] = array( 'review' );
	$GLOBALS['cr_review_facts'][ $post_id ] = array(
		'rating'       => 4.0,
		'best'         => 5,
		'item_name'    => 'The City We Became',
		'item_url'     => 'https://example.test/item',
		'rating_label' => 'Rated 4 of 5',
		'pullquote'    => 'A messy love letter to a city that fights back.',
		'category'     => array( 'name' => 'Books', 'link' => 'https://example.test/category/books/' ),
	);
	return $post;
}

/** Generic review stream card fixture. */
function cr_review_stream_card( string $heading = '<h3 class="pk-title p-name"><a class="u-url" href="https://example.test/review/42/">The City We Became</a></h3>' ): string {
	return '<article class="pk-card pk-card--stream k-review h-entry"><div class="pk-badge"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/></svg></div><div class="pk-body"><span class="pk-kindlabel">Review</span><div class="pk-caption">' . $heading . '<p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-18T12:00:00+00:00">September 18, 2026</time></p></div><div class="pk-media pk-media--stream"><img class="u-photo" src="https://example.test/cover.jpg" alt="Cover" loading="lazy" /></div><p class="pk-excerpt p-summary">A messy love letter to a city that fights back.</p><div class="pk-meta"><a class="pk-link" href="https://example.test/review/42/">Read more<span class="pk-sr-only">: The City We Became</span></a></div></div></article>';
}

/** Extract the first element by class from a fixture. */
function cr_review_stream_element( string $html, string $tag, string $class_name ): string {
	if ( preg_match( '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>.*?<\\/' . $tag . '>/s', $html, $match ) ) {
		return $match[0];
	}
	return '';
}

/** Return string position or a large sentinel. */
function cr_review_stream_pos( string $haystack, string $needle ): int {
	$pos = strpos( $haystack, $needle );
	return false === $pos ? PHP_INT_MAX : (int) $pos;
}

cr_review_group(
	'hook is registered with the frozen Stream callback',
	static function (): void {
		cr_review_contains( serialize( array( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamReview\\clipping', 10, 3 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'stream-card hook' );
	}
);

cr_review_group(
	'main fixture becomes a torn review clipping with one heading link',
	static function (): void {
		$post  = cr_review_stream_request();
		$input = cr_review_stream_card();
		$html  = \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		foreach ( array( 'pk-card', 'pk-card--stream', 'k-review', 'h-entry', 'cr-clipping', 'cr-paper-sheet', 'cr-paper-sheet--torn' ) as $class ) {
			cr_review_contains( $class, $html, 'class ' . $class );
		}
		$order = array_map(
			static fn( string $needle ): int => cr_review_stream_pos( $html, $needle ),
			array( 'pk-kindlabel', 'pk-title', 'cr-clipping__item', 'pkiw-review-rating', 'pk-stream-date', 'cr-clipping__quote' )
		);
		$sorted = $order;
		sort( $sorted );
		cr_review_same( $sorted, $order, 'content order' );
		cr_review_same( 1, substr_count( $html, '<a ' ), 'one link' );
		foreach ( array( '<img', 'pk-badge', 'pk-media', 'pk-excerpt', 'pk-meta', 'Read more' ) as $cut ) {
			cr_review_not_contains( $cut, $html, 'cuts ' . $cut );
		}
		cr_review_contains( cr_review_stream_element( $input, 'h3', 'pk-title' ), $html, 'heading byte-identical' );
		cr_review_contains( cr_review_stream_element( $input, 'p', 'pk-stream-date' ), $html, 'date byte-identical' );
		cr_review_contains( '<span class="pk-kindlabel">Review</span>', $html, 'kind label byte-identical' );
		cr_review_contains( '<p class="cr-clipping__quote">A messy love letter to a city that fights back.</p>', $html, 'quote text with no added marks' );
		cr_review_same( 1, substr_count( $html, 'Rated 4 of 5' ), 'rating label once' );
		cr_review_assert( str_ends_with( $html, '</div></article>' ), 'article still closes after body' );
	}
);

cr_review_group(
	'heading levels and untitled p-name behavior are preserved',
	static function (): void {
		$post = cr_review_stream_request();
		foreach ( array( 'h2', 'h3', 'h4' ) as $tag ) {
			$heading = '<' . $tag . ' class="pk-title p-name"><a class="u-url" href="https://example.test/review/42/">Title</a></' . $tag . '>';
			$html    = \Courtneyr\Child\StreamReview\clipping( cr_review_stream_card( $heading ), array(), new WP_Block( array( 'postId' => $post->ID ) ) );
			cr_review_contains( $heading, $html, $tag . ' heading preserved' );
		}
		$untitled = '<h3 class="pk-title"><a class="u-url" href="https://example.test/review/42/">Review<span class="pk-sr-only"> on September 18</span></a></h3>';
		$input    = cr_review_stream_card( $untitled );
		$html     = \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_review_same( substr_count( $input, 'p-name' ), substr_count( $html, 'p-name' ), 'p-name count does not grow' );
	}
);

cr_review_group(
	'optional item, rating and pullquote pieces follow the facts',
	static function (): void {
		$post  = cr_review_stream_request();
		$input = cr_review_stream_card();
		$GLOBALS['cr_review_facts'][ $post->ID ]['rating'] = 0;
		$GLOBALS['cr_review_facts'][ $post->ID ]['rating_label'] = '';
		cr_review_not_contains( 'pkiw-review-rating', \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'no zero rating element' );
		$GLOBALS['cr_review_facts'][ $post->ID ] = cr_review_default_facts();
		$GLOBALS['cr_review_facts'][ $post->ID ]['item_name'] = '';
		cr_review_not_contains( 'cr-clipping__item', \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'no empty item paragraph' );
		$GLOBALS['cr_review_facts'][ $post->ID ] = cr_review_default_facts();
		$GLOBALS['cr_review_facts'][ $post->ID ]['pullquote'] = '';
		cr_review_not_contains( 'cr-clipping__quote', \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'no empty quote paragraph' );
		$GLOBALS['cr_review_facts'][ $post->ID ] = cr_review_default_facts();
		$GLOBALS['cr_review_facts'][ $post->ID ]['item_name'] = '<script>x</script>';
		$GLOBALS['cr_review_facts'][ $post->ID ]['pullquote'] = 'A & <b>B</b>';
		$html = \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_review_contains( '&lt;script&gt;x&lt;/script&gt;', $html, 'hostile item escaped' );
		cr_review_contains( 'A &amp; &lt;b&gt;B&lt;/b&gt;', $html, 'hostile quote escaped' );
	}
);

cr_review_group(
	'pullquote with regex replacement tokens prints literally',
	static function (): void {
		$post = cr_review_stream_request();
		foreach ( array( '$1', '\\1', '${1}', '$0', '\\0', 'Only $1 well spent', 'Costs \\0 a month', 'Pay ${1}now' ) as $quote ) {
			$GLOBALS['cr_review_facts'][ $post->ID ] = cr_review_default_facts();
			$GLOBALS['cr_review_facts'][ $post->ID ]['pullquote'] = $quote;
			$html = \Courtneyr\Child\StreamReview\clipping( cr_review_stream_card(), array(), new WP_Block( array( 'postId' => $post->ID ) ) );
			cr_review_contains( '<p class="cr-clipping__quote">' . esc_html( $quote ) . '</p>', $html, 'literal pullquote ' . $quote );
			cr_review_same( 1, substr_count( $html, 'class="pk-caption"' ), 'one caption for ' . $quote );
			cr_review_same( 1, substr_count( $html, 'cr-clipping__quote' ), 'one quote paragraph for ' . $quote );
		}
	}
);

cr_review_group(
	'hidden machine-readable elements stay byte-for-byte',
	static function (): void {
		$post   = cr_review_stream_request();
		$hidden = '<span class="pk-kind-cite h-cite u-review-of" hidden><data class="p-name" value="X"></data></span>';
		$input  = str_replace( '<div class="pk-caption">', $hidden . '<div class="pk-caption">', cr_review_stream_card() );
		$html   = \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_review_contains( $hidden, $html, 'hidden h-cite kept' );
	}
);

cr_review_group(
	'unchanged cases return byte-for-byte input',
	static function (): void {
		$post  = cr_review_stream_request();
		$input = cr_review_stream_card();
		$GLOBALS['cr_review_request']['stream'] = false;
		cr_review_same( $input, \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'not stream surface' );
		cr_review_stream_request();
		$GLOBALS['cr_review_request']['post_kinds'][42] = array( 'read' );
		cr_review_same( $input, \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'not review post' );
		cr_review_stream_request();
		$GLOBALS['cr_review_request']['protected'][42] = true;
		cr_review_same( $input, \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'password protected' );
		cr_review_stream_request();
		$protected = '<article class="pk-card pk-card--stream pk-card--protected k-review h-entry"><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/review/42/">Protected: Title</a></h3></article>';
		cr_review_same( $protected, \Courtneyr\Child\StreamReview\clipping( $protected, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'protected class' );
		cr_review_stream_request();
		$GLOBALS['cr_review_facts'][42] = array();
		cr_review_same( $input, \Courtneyr\Child\StreamReview\clipping( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'facts empty' );
		cr_review_stream_request();
		cr_review_same( '', \Courtneyr\Child\StreamReview\clipping( '', array(), new WP_Block( array( 'postId' => 42 ) ) ), 'empty HTML' );
		cr_review_same( '<div class="pk-card"></div>', \Courtneyr\Child\StreamReview\clipping( '<div class="pk-card"></div>', array(), new WP_Block( array( 'postId' => 42 ) ) ), 'no article root' );
		$no_date = str_replace( cr_review_stream_element( $input, 'p', 'pk-stream-date' ), '', $input );
		cr_review_same( $no_date, \Courtneyr\Child\StreamReview\clipping( $no_date, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'no stream date' );
	}
);

cr_review_group(
	'post ID comes from block context or get_the_ID fallback',
	static function (): void {
		$post = cr_review_stream_request( 77 );
		$GLOBALS['cr_review_facts'][77]['item_name'] = 'Context item';
		$html = \Courtneyr\Child\StreamReview\clipping( cr_review_stream_card(), array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_review_contains( 'Context item', $html, 'context post ID' );
		cr_review_stream_request( 88 );
		$GLOBALS['cr_review_request']['the_id'] = 88;
		$GLOBALS['cr_review_facts'][88]['item_name'] = 'Loop item';
		$html = \Courtneyr\Child\StreamReview\clipping( cr_review_stream_card(), array(), null );
		cr_review_contains( 'Loop item', $html, 'get_the_ID fallback' );
	}
);

cr_review_finish();
