<?php
/**
 * Standalone checks for reply archive letters.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/reply-bootstrap.php';

use Courtneyr\Child\Letters as Letters;

function cr_reply_letter_block( int $level = 3, string $class = 'is-style-cr-letter' ): array {
	return array( 'attrs' => array( 'headingLevel' => $level, 'className' => $class ) );
}

function cr_reply_fixture_facts( WP_Post $post, array $overrides = array() ): void {
	$GLOBALS['cr_reply_facts'][ $post->ID ] = array_merge(
		array(
			'url'          => 'https://target.test/post?x=1&y=2',
			'host'         => 'target.test',
			'cited_title'  => 'Target title',
			'author'       => 'A. Source',
			'summary'      => 'Quoted source summary',
			'picture'      => 'https://target.test/image.jpg',
			'name'         => '' !== trim( $post->post_title ) ? $post->post_title : 'Target title',
		),
		$overrides
	);
}

cr_reply_group(
	'letter card gates on class and kind',
	static function (): void {
		$post  = cr_reply_reset();
		$input = '<article>Plugin card</article>';
		cr_reply_fixture_facts( $post );
		cr_reply_same( $input, Letters\letter_card( $input, cr_reply_letter_block( 3, '' ), new WP_Block( array( 'postId' => $post->ID ) ) ), 'missing style unchanged' );
		$GLOBALS['cr_reply_kind'] = 'like';
		cr_reply_same( $input, Letters\letter_card( $input, cr_reply_letter_block(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'non-reply unchanged' );
		$GLOBALS['cr_reply_kind'] = 'reply';
		$other = new WP_Block( array( 'postId' => 999 ) );
		cr_reply_same( $input, Letters\letter_card( $input, cr_reply_letter_block(), $other ), 'missing post unchanged' );
	}
);

cr_reply_group(
	'letter card rebuilds a titled reply',
	static function (): void {
		$post               = cr_reply_reset();
		$post->post_content = implode( ' ', range( 'a', 'z' ) );
		cr_reply_fixture_facts( $post );
		$html = Letters\letter_card( '<article>Plugin card</article>', cr_reply_letter_block( 3 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<article class="cr-letter k-reply">', $html, 'article class' );
		cr_reply_contains( '<h3 class="cr-letter__title p-name"><a id="cr-letter-t-1" class="u-url" href="https://example.test/reply/42">Letter title</a></h3>', $html, 'named title link' );
		cr_reply_contains( '<p class="cr-letter__excerpt p-summary">a b c d e f g h i j k l m n o p q r s t u v w x y</p>', $html, 'excerpt at 25 words' );
		cr_reply_contains( '<time id="cr-letter-d-1" class="dt-published cr-letter__date" datetime="2026-10-11T12:00:00+00:00" hidden>October 11, 2026</time>', $html, 'hidden date' );
		cr_reply_contains( '<data class="u-in-reply-to" value="https://target.test/post?x=1&' . '#038;y=2" hidden></data>', $html, 'target data keeps query' );
		cr_reply_same( 1, substr_count( $html, '<a ' ), 'one anchor' );
		foreach ( array( 'A. Source', 'Quoted source summary', '<img', 'href="https://target.test', 'Reply', 'in reply to', 'pk-card', 'h-entry' ) as $gone ) {
			cr_reply_not_contains( $gone, $html, $gone . ' absent' );
		}
	}
);

cr_reply_group(
	'letter card handles untitled, empty urls, clamped headings and protected posts',
	static function (): void {
		$post             = cr_reply_reset();
		$post->post_title = '';
		cr_reply_fixture_facts( $post, array( 'name' => 'Target title', 'url' => '' ) );
		$html = Letters\letter_card( 'x', cr_reply_letter_block( 1 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<h2 class="cr-letter__title"><a', $html, 'low heading clamps to h2 and omits p-name' );
		cr_reply_contains( '>Target title</a></h2>', $html, 'facts name used' );
		cr_reply_not_contains( 'u-in-reply-to', $html, 'empty target omitted' );

		cr_reply_fixture_facts( $post, array( 'name' => 'Target title', 'url' => 'javascript:alert(1)' ) );
		$html = Letters\letter_card( 'x', cr_reply_letter_block( 2 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_not_contains( 'u-in-reply-to', $html, 'unsafe target omitted' );

		$html = Letters\letter_card( 'x', cr_reply_letter_block( 9 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<h4 class="cr-letter__title"><a', $html, 'high heading clamps to h4' );

		$post = cr_reply_reset();
		$GLOBALS['cr_reply_protected'][ $post->ID ] = true;
		cr_reply_fixture_facts( $post );
		$html = Letters\letter_card( 'x', cr_reply_letter_block(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<article class="cr-letter cr-letter--protected k-reply">', $html, 'protected class' );
		cr_reply_contains( '>Protected: Letter title</a>', $html, 'protected title' );
		foreach ( array( 'p-summary', 'dt-published', 'u-in-reply-to' ) as $gone ) {
			cr_reply_not_contains( $gone, $html, $gone . ' omitted' );
		}
	}
);

cr_reply_group(
	'template decoration adds papers, host ink and duplicate names',
	static function (): void {
		cr_reply_reset();
		$html = '<div class="wp-block-post-template pkiw-grouped"><section class="pkiw-group"><h2 id="pkiw-group-a" class="pkiw-group__heading">Alpha.test</h2><ul class="pkiw-group__items">'
			. '<li class="wp-block-post post-10"><article class="cr-letter k-reply"><h3 class="cr-letter__title p-name"><a id="cr-letter-t-a" href="/a">Same</a></h3><time id="cr-letter-d-a" hidden>Oct 1</time></article></li>'
			. '<li class="wp-block-post post-11"><article class="cr-letter k-reply"><h3 class="cr-letter__title p-name"><a id="cr-letter-t-b" href="/b">Unique</a></h3><time id="cr-letter-d-b" hidden>Oct 2</time></article></li>'
			. '</ul></section><section class="pkiw-group"><h3 id="pkiw-group-b" class="pkiw-group__heading">Beta.test</h3><ul class="pkiw-group__items">'
			. '<li class="wp-block-post post-12"><article class="cr-letter k-reply"><h3 class="cr-letter__title p-name"><a id="cr-letter-t-c" href="/c">same</a></h3><time id="cr-letter-d-c" hidden>Oct 3</time></article></li>'
			. '<li class="wp-block-post post-13"><article class="cr-letter cr-letter--protected k-reply"><h3 class="cr-letter__title p-name"><a id="cr-letter-t-d" href="/d">Private</a></h3><time id="cr-letter-d-d" hidden>Oct 4</time></article></li>'
			. '<li class="wp-block-post"><article class="cr-letter k-reply"><h3 class="cr-letter__title"><a href="/e">No id</a></h3></article></li>'
			. '</ul></section></div>';
		$out = Letters\decorate_post_template( $html, array() );
		foreach ( array( 'post-10', 'post-11', 'post-12' ) as $post_class ) {
			cr_reply_contains( $post_class . ' cr-paper-sheet', $out, $post_class . ' has sheet class' );
			cr_reply_assert( preg_match( '/' . $post_class . '[^"]*cr-paper--(sky-blue|periwinkle|light-orange|selective-yellow|printer-ivory)/', $out ) === 1, $post_class . ' has allowed paper' );
			cr_reply_assert( preg_match( '/' . $post_class . '[^"]*cr-letter-trim--[0-3]/', $out ) === 1, $post_class . ' has trim' );
		}
		cr_reply_assert( preg_match( '/post-13[^"]*cr-paper-sheet[^"]*cr-paper--printer-ivory/', $out ) === 1, 'protected uses printer ivory' );
		cr_reply_not_contains( 'post-13 cr-letter-trim', $out, 'protected has no trim' );
		cr_reply_assert( preg_match( '/pkiw-group__heading[^"]*cr-letters__host[^"]*cr-letters__host--ink-[0-2]/', $out ) === 1, 'host ink added' );
		cr_reply_not_contains( 'style=', $out, 'no inline style' );
		cr_reply_not_contains( 'transform', $out, 'no inline transform text' );
		cr_reply_contains( 'aria-labelledby="cr-letter-t-a cr-letter-d-a"', $out, 'first duplicate labelled' );
		cr_reply_contains( 'aria-labelledby="cr-letter-t-c cr-letter-d-c"', $out, 'second duplicate labelled' );
		cr_reply_not_contains( 'aria-labelledby="cr-letter-t-b cr-letter-d-b"', $out, 'unique unlabelled' );
		cr_reply_same( '<ul><li>No letter</li></ul>', Letters\decorate_post_template( '<ul><li>No letter</li></ul>', array() ), 'no letter unchanged' );
		cr_reply_same( 1, Letters\host_ink( 'Alpha.test' ), 'Alpha.test ink' );
		cr_reply_same( 0, Letters\host_ink( 'Beta.test' ), 'Beta.test ink' );
		cr_reply_same( 1, Letters\host_ink( 'Gamma.test' ), 'Gamma.test ink' );
		$seen = array();
		for ( $i = 1; $i <= 30; $i++ ) {
			$ink = Letters\host_ink( 'Host ' . $i );
			cr_reply_assert( $ink >= 0 && $ink <= 2, 'host ink range ' . $i );
			$seen[ $ink ] = true;
		}
		ksort( $seen );
		cr_reply_same( array( 0, 1, 2 ), array_keys( $seen ), 'thirty labels cover all inks' );
	}
);

cr_reply_group(
	'duplicate names skip protected letters without crossing articles',
	static function (): void {
		cr_reply_reset();
		$html = '<ul class="wp-block-post-template">'
			. '<li class="wp-block-post post-21"><article class="cr-letter k-reply"><h3 class="cr-letter__title p-name"><a id="cr-letter-t-a" href="/a">Same</a></h3><time id="cr-letter-d-a" hidden>Oct 1</time></article></li>'
			. '<li class="wp-block-post post-22"><article class="cr-letter cr-letter--protected k-reply"><h3 class="cr-letter__title p-name"><a id="cr-letter-t-b" href="/b">Protected: Same</a></h3></article></li>'
			. '<li class="wp-block-post post-23"><article class="cr-letter k-reply"><h3 class="cr-letter__title p-name"><a id="cr-letter-t-c" href="/c">Same</a></h3><time id="cr-letter-d-c" hidden>Oct 3</time></article></li>'
			. '<li class="wp-block-post post-24"><article class="cr-letter k-reply"><h3 class="cr-letter__title p-name"><a id="cr-letter-t-d" href="/d">same</a></h3><time id="cr-letter-d-d" hidden>Oct 4</time></article></li>'
			. '</ul>';
		$out  = Letters\decorate_post_template( $html, array() );
		cr_reply_contains( 'id="cr-letter-t-a" href="/a" aria-labelledby="cr-letter-t-a cr-letter-d-a"', $out, 'first public duplicate labelled' );
		cr_reply_contains( 'id="cr-letter-t-c" href="/c" aria-labelledby="cr-letter-t-c cr-letter-d-c"', $out, 'second public duplicate labelled' );
		cr_reply_contains( 'id="cr-letter-t-d" href="/d" aria-labelledby="cr-letter-t-d cr-letter-d-d"', $out, 'third public duplicate labelled' );
		cr_reply_not_contains( 'id="cr-letter-t-b" href="/b" aria-labelledby=', $out, 'protected duplicate skipped' );
	}
);

cr_reply_group(
	'papers vary and enqueue gates match reply surfaces',
	static function (): void {
		cr_reply_reset();
		$papers = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$html = '<ul><li class="wp-block-post post-' . $i . '"><article class="cr-letter k-reply"><h3 class="cr-letter__title"><a href="/p' . $i . '">Title ' . $i . '</a></h3></article></li></ul>';
			$out  = Letters\decorate_post_template( $html, array() );
			if ( preg_match( '/cr-paper--([a-z-]+)/', $out, $m ) ) {
				$papers[] = $m[1];
			}
		}
		cr_reply_assert( count( array_unique( $papers ) ) >= 3, 'twenty posts choose at least three papers' );

		foreach ( array( 'archive', 'single', 'stream', 'admin' ) as $surface ) {
			cr_reply_reset();
			$GLOBALS['cr_reply_tax']    = 'archive' === $surface;
			$GLOBALS['cr_reply_single'] = 'single' === $surface;
			$GLOBALS['cr_reply_stream'] = 'stream' === $surface;
			$GLOBALS['cr_reply_admin']  = 'admin' === $surface;
			Letters\enqueue_styles();
			cr_reply_same( 'courtneyr-letters', $GLOBALS['cr_reply_enqueued'][0][0] ?? '', $surface . ' enqueues handle' );
			cr_reply_same( array( 'courtneyr-paper' ), $GLOBALS['cr_reply_enqueued'][0][2] ?? array(), $surface . ' depends on paper' );
		}
		cr_reply_reset();
		Letters\enqueue_styles();
		cr_reply_same( array(), $GLOBALS['cr_reply_enqueued'], 'other surfaces enqueue nothing' );

		Letters\register_card_style();
		cr_reply_same( 'post-kinds-indieweb/stream-card', $GLOBALS['cr_reply_block_styles'][0][0] ?? '', 'block name' );
		cr_reply_same( 'cr-letter', $GLOBALS['cr_reply_block_styles'][0][1]['name'] ?? '', 'style name' );
		cr_reply_same( 'Letter', $GLOBALS['cr_reply_block_styles'][0][1]['label'] ?? '', 'style label' );
	}
);

cr_reply_finish();
