<?php
/**
 * Standalone checks for the review single adapter.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/review-bootstrap.php';

/** Put the harness on a review single request. */
function cr_review_single_request(): WP_Post {
	$post = cr_review_reset();
	$GLOBALS['cr_review_request']['single'] = true;
	$GLOBALS['cr_review_request']['post_kind'] = 'review';
	$GLOBALS['cr_review_request']['post_kinds'][ $post->ID ] = array( 'review' );
	return $post;
}

/** Return string position or a large sentinel. */
function cr_review_single_pos( string $haystack, string $needle ): int {
	$pos = strpos( $haystack, $needle );
	return false === $pos ? PHP_INT_MAX : (int) $pos;
}

if ( in_array( '--no-plugin', $argv ?? array(), true ) ) {
	cr_review_group(
		'fallback behavior without plugin helpers',
		static function (): void {
			cr_review_single_request();
			cr_review_same( array(), \Courtneyr\Child\Review\facts( 42 ), 'facts empty without plugin' );
			cr_review_same( '<div class="cr-review__rating"><p class="pkiw-review-rating"><span class="pkiw-review-rating__label">Rated 3.5 of 5</span></p></div>', \Courtneyr\Child\SingleReview\rating_box_html( array( 'rating' => 3.5, 'rating_label' => 'Rated 3.5 of 5' ) ), 'rating box fallback' );
			cr_review_same( '', \Courtneyr\Child\SingleReview\picture_html( 42 ), 'picture empty without plugin picture helper' );
		}
	);
	cr_review_finish();
}

cr_review_group(
	'hooks are registered with the frozen single callbacks',
	static function (): void {
		cr_review_contains( serialize( array( 'render_block_core/post-terms', 'Courtneyr\\Child\\SingleReview\\header_terms', 10, 2 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'post terms hook' );
		cr_review_contains( serialize( array( 'render_block_core/post-featured-image', 'Courtneyr\\Child\\SingleReview\\drop_featured_image', 10, 2 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'featured image hook' );
		cr_review_contains( serialize( array( 'render_block_core/post-title', 'Courtneyr\\Child\\SingleReview\\title_extras', 30, 3 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'post title hook' );
		cr_review_contains( serialize( array( 'render_block', 'Courtneyr\\Child\\SingleReview\\header_picture', 10, 2 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'render block hook' );
		cr_review_contains( serialize( array( 'render_block_post-kinds-indieweb/star-rating', 'Courtneyr\\Child\\SingleReview\\drop_star_rating', 20, 3 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'star rating hook' );
	}
);

cr_review_group(
	'picture_html prints the stored picture without inventing alt text',
	static function (): void {
		$post = cr_review_single_request();
		$GLOBALS['cr_review_pictures'][ $post->ID ] = array( 'source' => 'featured', 'attachment_id' => 91, 'url' => 'https://example.test/attached.jpg', 'alt' => 'Stored alt' );
		$html = \Courtneyr\Child\SingleReview\picture_html( $post->ID );
		cr_review_contains( '<figure class="cr-review__picture" data-cr-cover-fallback>', $html, 'figure fallback marker' );
		cr_review_same( 1, substr_count( $html, '<img' ), 'one image' );
		cr_review_contains( 'class="cr-review__img"', $html, 'image class' );
		cr_review_contains( 'alt="Stored alt"', $html, 'stored alt' );
		cr_review_contains( 'loading="eager"', $html, 'eager loading' );
		cr_review_contains( 'fetchpriority="high"', $html, 'high priority' );
		cr_review_contains( 'decoding="async"', $html, 'async decoding' );
		cr_review_not_contains( '<a ', $html, 'no link' );
		$GLOBALS['cr_review_pictures'][ $post->ID ] = array( 'source' => 'remote', 'attachment_id' => 0, 'url' => 'https://remote.test/c.jpg?x=1&y=2', 'alt' => '' );
		$html = \Courtneyr\Child\SingleReview\picture_html( $post->ID );
		cr_review_contains( 'src="https://remote.test/c.jpg?x=1&amp;y=2"', $html, 'remote URL escaped' );
		cr_review_contains( 'alt=""', $html, 'empty alt remains empty' );
		cr_review_not_contains( 'Review title', $html, 'title not used as alt' );
		$GLOBALS['cr_review_pictures'][ $post->ID ] = array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => 'No URL' );
		cr_review_same( '', \Courtneyr\Child\SingleReview\picture_html( $post->ID ), 'no URL empty' );
	}
);

cr_review_group(
	'byline_html prints item name only and never adds microformat item classes',
	static function (): void {
		cr_review_same( '', \Courtneyr\Child\SingleReview\byline_html( array( 'item_name' => '   ' ) ), 'empty item name' );
		$plain = \Courtneyr\Child\SingleReview\byline_html( array( 'item_name' => '<b>Book</b>', 'item_url' => '' ) );
		cr_review_same( '<p class="cr-review__byline">&lt;b&gt;Book&lt;/b&gt;</p>', $plain, 'plain escaped byline' );
		$link = \Courtneyr\Child\SingleReview\byline_html( array( 'item_name' => 'Book', 'item_url' => 'https://example.test/book?x=1&y=2' ) );
		cr_review_contains( '<a href="https://example.test/book?x=1&amp;y=2">Book</a>', $link, 'linked byline' );
		foreach ( array( 'p-item', 'h-product', 'p-name', 'u-url' ) as $class ) {
			cr_review_not_contains( $class, $plain . $link, 'no ' . $class );
		}
	}
);

cr_review_group(
	'rating_box_html wraps the rating helper once',
	static function (): void {
		$html = \Courtneyr\Child\SingleReview\rating_box_html( array( 'rating' => 3.5, 'best' => 5, 'rating_label' => 'Rated 3.5 of 5' ) );
		cr_review_contains( '<div class="cr-review__rating">', $html, 'rating wrapper' );
		cr_review_same( 1, substr_count( $html, 'Rated' ), 'one visible rating label' );
		cr_review_same( '', \Courtneyr\Child\SingleReview\rating_box_html( array( 'rating' => 0 ) ), 'zero rating empty' );
	}
);

cr_review_group(
	'chip_html prints the category chip with escaping',
	static function (): void {
		$link = \Courtneyr\Child\SingleReview\chip_html( array( 'category' => array( 'name' => '<Books>', 'link' => 'https://example.test/cat?x=1&y=2' ) ) );
		cr_review_contains( '<div class="cr-review__chip single-post__category">', $link, 'chip wrapper' );
		cr_review_contains( '<a href="https://example.test/cat?x=1&amp;y=2" rel="tag">&lt;Books&gt;</a>', $link, 'linked chip' );
		cr_review_contains( '<span>Books</span>', \Courtneyr\Child\SingleReview\chip_html( array( 'category' => array( 'name' => 'Books', 'link' => '' ) ) ), 'plain chip span' );
		cr_review_same( '', \Courtneyr\Child\SingleReview\chip_html( array( 'category' => array( 'name' => '', 'link' => '' ) ) ), 'empty category' );
	}
);

cr_review_group(
	'header_terms replaces only the kind and category term blocks on a matching review single',
	static function (): void {
		cr_review_single_request();
		cr_review_same( '', \Courtneyr\Child\SingleReview\header_terms( '<p>Kind</p>', array( 'attrs' => array( 'className' => 'cr-chip single-post__kind' ) ) ), 'kind term removed' );
		cr_review_contains( 'cr-review__chip', \Courtneyr\Child\SingleReview\header_terms( '<p>Cat</p>', array( 'attrs' => array( 'className' => 'single-post__category' ) ) ), 'category term replaced' );
		cr_review_same( '<p>Keep</p>', \Courtneyr\Child\SingleReview\header_terms( '<p>Keep</p>', array( 'attrs' => array( 'className' => 'single-post__kind-extra' ) ) ), 'longer class not matched' );
		cr_review_reset();
		cr_review_same( '<p>Keep</p>', \Courtneyr\Child\SingleReview\header_terms( '<p>Keep</p>', array( 'attrs' => array( 'className' => 'single-post__kind' ) ) ), 'archive unchanged' );
		cr_review_single_request();
		$GLOBALS['cr_review_request']['post_kinds'][42] = array( 'read' );
		cr_review_same( '<p>Keep</p>', \Courtneyr\Child\SingleReview\header_terms( '<p>Keep</p>', array( 'attrs' => array( 'className' => 'single-post__kind' ) ) ), 'non-review single unchanged' );
	}
);

cr_review_group(
	'drop_featured_image removes only the single featured image block',
	static function (): void {
		cr_review_single_request();
		cr_review_same( '', \Courtneyr\Child\SingleReview\drop_featured_image( '<figure>Image</figure>', array( 'attrs' => array( 'className' => 'single-post__featured' ) ) ), 'featured removed' );
		cr_review_same( '<figure>Image</figure>', \Courtneyr\Child\SingleReview\drop_featured_image( '<figure>Image</figure>', array( 'attrs' => array( 'className' => 'other' ) ) ), 'other class kept' );
		cr_review_reset();
		cr_review_same( '<figure>Image</figure>', \Courtneyr\Child\SingleReview\drop_featured_image( '<figure>Image</figure>', array( 'attrs' => array( 'className' => 'single-post__featured' ) ) ), 'non-review kept' );
	}
);

cr_review_group(
	'title_extras appends byline then star box only to the queried title block',
	static function (): void {
		$post = cr_review_single_request();
		$h1   = '<h1 class="single-post__title">Title</h1>';
		$html = \Courtneyr\Child\SingleReview\title_extras( $h1, array( 'attrs' => array( 'className' => 'cr-flank single-post__title' ) ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_review_same( $h1, substr( $html, 0, strlen( $h1 ) ), 'H1 first and unchanged' );
		cr_review_assert( cr_review_single_pos( $html, 'cr-review__byline' ) < cr_review_single_pos( $html, 'cr-review__rating' ), 'byline before rating' );
		foreach ( array( 'p-name', 'u-url', 'h-product', 'p-item' ) as $class ) {
			cr_review_not_contains( $class, $html, 'title extras add no ' . $class );
		}
		$GLOBALS['cr_review_facts'][ $post->ID ]['item_name'] = '';
		$html = \Courtneyr\Child\SingleReview\title_extras( $h1, array( 'attrs' => array( 'className' => 'single-post__title' ) ), null );
		cr_review_not_contains( 'cr-review__byline', $html, 'no byline without item' );
		cr_review_contains( 'cr-review__rating', $html, 'rating remains' );
		$GLOBALS['cr_review_facts'][ $post->ID ]['item_name'] = 'Item';
		$GLOBALS['cr_review_facts'][ $post->ID ]['rating'] = 0;
		$html = \Courtneyr\Child\SingleReview\title_extras( $h1, array( 'attrs' => array( 'className' => 'single-post__title' ) ), null );
		cr_review_contains( 'cr-review__byline', $html, 'byline remains' );
		cr_review_not_contains( 'cr-review__rating', $html, 'no rating at zero' );
		$GLOBALS['cr_review_facts'][ $post->ID ]['item_name'] = '';
		$html = \Courtneyr\Child\SingleReview\title_extras( $h1, array( 'attrs' => array( 'className' => 'single-post__title' ) ), null );
		cr_review_same( $h1, $html, 'neither extra leaves H1 unchanged' );
		cr_review_same( $h1, \Courtneyr\Child\SingleReview\title_extras( $h1, array( 'attrs' => array( 'className' => 'other' ) ), null ), 'other class unchanged' );
		cr_review_same( $h1, \Courtneyr\Child\SingleReview\title_extras( $h1, array( 'attrs' => array( 'className' => 'single-post__title' ) ), new WP_Block( array( 'postId' => 99 ) ) ), 'different post context unchanged' );
	}
);

cr_review_group(
	'hold_header_date moves the date between the byline and the star box',
	static function (): void {
		cr_review_contains( serialize( array( 'render_block_core/post-date', 'Courtneyr\\Child\\SingleReview\\hold_header_date', 20, 3 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'post date hook' );
		$post  = cr_review_single_request();
		$date  = '<div class="single-post__date wp-block-post-date"><time class="dt-published">August 30, 2026</time></div>';
		$block = array( 'attrs' => array( 'className' => 'single-post__date cr-dt-published' ) );
		cr_review_same( '', \Courtneyr\Child\SingleReview\hold_header_date( $date, $block, new WP_Block( array( 'postId' => $post->ID ) ) ), 'lede row loses the date' );
		$html = \Courtneyr\Child\SingleReview\title_extras( '<h1 class="single-post__title">Title</h1>', array( 'attrs' => array( 'className' => 'single-post__title' ) ), null );
		cr_review_same( 1, substr_count( $html, 'August 30, 2026' ), 'date prints once' );
		cr_review_assert( cr_review_single_pos( $html, 'cr-review__byline' ) < cr_review_single_pos( $html, 'single-post__date' ), 'byline before date' );
		cr_review_assert( cr_review_single_pos( $html, 'single-post__date' ) < cr_review_single_pos( $html, 'cr-review__rating' ), 'date before rating' );
		$html = \Courtneyr\Child\SingleReview\title_extras( '<h1 class="single-post__title">Title</h1>', array( 'attrs' => array( 'className' => 'single-post__title' ) ), null );
		cr_review_not_contains( 'August 30, 2026', $html, 'the held date prints only once' );
		cr_review_same( $date, \Courtneyr\Child\SingleReview\hold_header_date( $date, array( 'attrs' => array( 'className' => 'other' ) ), null ), 'other date blocks stay' );
		cr_review_same( $date, \Courtneyr\Child\SingleReview\hold_header_date( $date, $block, new WP_Block( array( 'postId' => 99 ) ) ), 'other post context stays' );
		cr_review_reset();
		cr_review_same( $date, \Courtneyr\Child\SingleReview\hold_header_date( $date, $block, null ), 'non-review date stays' );
	}
);

cr_review_group(
	'header_picture inserts the figure after the header group opening tag',
	static function (): void {
		$post = cr_review_single_request();
		$GLOBALS['cr_review_pictures'][ $post->ID ] = array( 'source' => 'remote', 'attachment_id' => 0, 'url' => 'https://example.test/cover.jpg', 'alt' => 'Cover' );
		$input = '<div class="wp-block-group single-post__header" style="--x:\'>\'"><p>First child</p></div>';
		$html  = \Courtneyr\Child\SingleReview\header_picture( $input, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__header' ) ) );
		cr_review_assert( cr_review_single_pos( $html, 'cr-review__picture' ) < cr_review_single_pos( $html, '<p>First child</p>' ), 'picture before first child' );
		cr_review_same( $input, \Courtneyr\Child\SingleReview\header_picture( $input, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'other' ) ) ), 'other group unchanged' );
		$GLOBALS['cr_review_pictures'][ $post->ID ] = array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => '' );
		cr_review_same( $input, \Courtneyr\Child\SingleReview\header_picture( $input, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__header' ) ) ), 'no picture unchanged' );
		cr_review_reset();
		cr_review_same( $input, \Courtneyr\Child\SingleReview\header_picture( $input, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__header' ) ) ), 'non-review unchanged' );
	}
);

cr_review_group(
	'drop_star_rating removes duplicate plugin ratings on review singles',
	static function (): void {
		cr_review_single_request();
		cr_review_same( '', \Courtneyr\Child\SingleReview\drop_star_rating( '<p>Rated 3.5 of 5</p>', array(), null ), 'review single drops star rating block' );
		cr_review_reset();
		cr_review_same( '<p>Rated 3.5 of 5</p>', \Courtneyr\Child\SingleReview\drop_star_rating( '<p>Rated 3.5 of 5</p>', array(), null ), 'other request keeps star rating block' );
	}
);

cr_review_group(
	'drop_protected_syndication removes the whole syndication area on a protected review single',
	static function (): void {
		cr_review_contains( serialize( array( 'render_block_core/group', 'Courtneyr\\Child\\SingleReview\\drop_protected_syndication', 10, 2 ) ), serialize( $GLOBALS['cr_review_hooks'] ), 'group hook' );
		$html  = '<div class="wp-block-group single-post__syndication"><h2 class="wp-block-heading">Likes, Bookmarks, and Reposts</h2></div>';
		$block = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__syndication' ) );
		$post  = cr_review_single_request();
		$GLOBALS['cr_review_request']['protected'][ $post->ID ] = true;
		$out = \Courtneyr\Child\SingleReview\drop_protected_syndication( $html, $block );
		cr_review_not_contains( 'single-post__syndication', $out, 'protected single has no syndication wrapper' );
		cr_review_not_contains( 'Likes, Bookmarks, and Reposts', $out, 'protected single has no interactions heading' );
		cr_review_same( '<div>Other</div>', \Courtneyr\Child\SingleReview\drop_protected_syndication( '<div>Other</div>', array( 'attrs' => array( 'className' => 'single-post__header' ) ) ), 'other groups stay on a protected single' );
		cr_review_single_request();
		cr_review_same( $html, \Courtneyr\Child\SingleReview\drop_protected_syndication( $html, $block ), 'unprotected review keeps the syndication area' );
		$post = cr_review_reset();
		$GLOBALS['cr_review_request']['protected'][ $post->ID ] = true;
		cr_review_same( $html, \Courtneyr\Child\SingleReview\drop_protected_syndication( $html, $block ), 'non-review request keeps the syndication area' );
	}
);

cr_review_group(
	'prints rating, item and category exactly once across header edits',
	static function (): void {
		cr_review_single_request();
		$GLOBALS['cr_review_facts'][42] = array(
			'rating'       => 3.5,
			'best'         => 5,
			'item_name'    => 'The City We Became',
			'item_url'     => 'https://example.test/item',
			'rating_label' => 'Rated 3.5 of 5',
			'category'     => array( 'name' => 'Books', 'link' => 'https://example.test/category/books/' ),
		);
		$out  = \Courtneyr\Child\SingleReview\header_terms( '<p>Kind</p>', array( 'attrs' => array( 'className' => 'single-post__kind' ) ) );
		$out .= \Courtneyr\Child\SingleReview\header_terms( '<p>Cat</p>', array( 'attrs' => array( 'className' => 'single-post__category' ) ) );
		$out .= \Courtneyr\Child\SingleReview\title_extras( '<h1 class="single-post__title">Title</h1>', array( 'attrs' => array( 'className' => 'single-post__title' ) ), null );
		$out .= \Courtneyr\Child\SingleReview\drop_star_rating( '<p>Rated 3.5 of 5</p>', array(), null );
		cr_review_same( 1, substr_count( $out, 'Rated 3.5 of 5' ), 'rating once' );
		cr_review_same( 1, substr_count( $out, 'The City We Became' ), 'item once' );
		cr_review_same( 1, substr_count( $out, 'Books' ), 'category once' );
	}
);

cr_review_group(
	'no-plugin subprocess covers guarded fallbacks',
	static function (): void {
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-plugin';
		exec( $cmd, $output, $status );
		cr_review_same( 0, $status, 'fallback subprocess exit' );
		cr_review_contains( 'all passed', implode( "\n", $output ), 'fallback subprocess passed' );
	}
);

cr_review_finish();
