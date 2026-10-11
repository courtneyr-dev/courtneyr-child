<?php
/**
 * Standalone checks for reply Stream envelopes.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/reply-bootstrap.php';

use Courtneyr\Child\StreamReply as StreamReply;

function cr_reply_stream_block( int $level = 2, string $class = '' ): array {
	return array( 'attrs' => array( 'headingLevel' => $level, 'className' => $class ) );
}

cr_reply_group(
	'envelope gates on Stream surface, style, kind and protection',
	static function (): void {
		$post  = cr_reply_reset();
		$input = '<article>Plugin card</article>';
		$GLOBALS['cr_reply_facts'][ $post->ID ] = array( 'url' => 'https://target.test/post', 'host' => 'target.test', 'name' => 'Target title' );
		cr_reply_same( $input, StreamReply\envelope_card( $input, cr_reply_stream_block(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'off Stream unchanged' );
		$GLOBALS['cr_reply_stream'] = true;
		cr_reply_same( $input, StreamReply\envelope_card( $input, cr_reply_stream_block( 2, 'is-style-cr-letter' ), new WP_Block( array( 'postId' => $post->ID ) ) ), 'letter style unchanged' );
		$GLOBALS['cr_reply_kind'] = 'like';
		cr_reply_same( $input, StreamReply\envelope_card( $input, cr_reply_stream_block(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'non-reply unchanged' );
		$GLOBALS['cr_reply_kind'] = 'reply';
		$GLOBALS['cr_reply_protected'][ $post->ID ] = true;
		cr_reply_same( $input, StreamReply\envelope_card( $input, cr_reply_stream_block(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'protected unchanged' );
	}
);

cr_reply_group(
	'envelope card prints one linked insert and hidden target',
	static function (): void {
		$post               = cr_reply_reset();
		$post->post_content = implode( ' ', range( 'a', 'z' ) );
		$GLOBALS['cr_reply_stream'] = true;
		$GLOBALS['cr_reply_dates'][ $post->ID ] = array( 'iso' => '2026-10-11T12:00:00+00:00', 'ts' => strtotime( '2026-10-11 12:00:00 UTC' ) );
		$GLOBALS['cr_reply_facts'][ $post->ID ] = array( 'url' => 'https://target.test/post?x=1&y=2', 'host' => 'target.test', 'name' => 'Letter title' );
		$html = StreamReply\envelope_card( '<article>Plugin card</article>', cr_reply_stream_block( 2 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<article class="cr-envelope k-reply">', $html, 'article class' );
		cr_reply_contains( '<span class="cr-envelope__label">Letter</span>', $html, 'plugin label' );
		cr_reply_contains( '<time class="dt-published cr-envelope__date" datetime="2026-10-11T12:00:00+00:00">Oct 11</time>', $html, 'short date' );
		cr_reply_contains( '<h2 class="cr-envelope__title p-name"><a id="cr-envelope-t-1" class="u-url" href="https://example.test/reply/42">Letter title</a></h2>', $html, 'title link' );
		cr_reply_contains( '<p class="cr-envelope__excerpt p-summary">a b c d e f g h i j k l m n o p q r s t</p>', $html, 'twenty word excerpt' );
		cr_reply_contains( '<p class="cr-envelope__address">target.test</p>', $html, 'address' );
		cr_reply_contains( '<data class="u-in-reply-to" value="https://target.test/post?x=1&' . '#038;y=2" hidden></data>', $html, 'target data' );
		cr_reply_same( 1, substr_count( $html, '<a ' ), 'one anchor' );
		foreach ( array( '<img', 'A. Source', 'Quoted', 'in reply to', '>to<', 'pk-card', 'h-entry' ) as $gone ) {
			cr_reply_not_contains( $gone, $html, $gone . ' absent' );
		}
		cr_reply_assert( preg_match( '/cr-envelope__insert( cr-envelope__insert--tilt-[1-4])?"/', $html ) === 1, 'tilt class in range or absent' );
		$again = StreamReply\envelope_card( '<article>Plugin card</article>', cr_reply_stream_block( 2 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		preg_match( '/cr-envelope__insert( cr-envelope__insert--tilt-[1-4])?"/', $html, $first_tilt );
		preg_match( '/cr-envelope__insert( cr-envelope__insert--tilt-[1-4])?"/', $again, $second_tilt );
		cr_reply_same( $first_tilt[0] ?? '', $second_tilt[0] ?? '', 'tilt stable per post id' );
	}
);

cr_reply_group(
	'envelope omits host and p-name when facts are synthetic',
	static function (): void {
		$post             = cr_reply_reset();
		$post->post_title = '';
		$GLOBALS['cr_reply_stream'] = true;
		$GLOBALS['cr_reply_facts'][ $post->ID ] = array( 'url' => 'https://target.test/post', 'host' => '', 'name' => 'Target title' );
		$html = StreamReply\envelope_card( 'x', cr_reply_stream_block( 1 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<h2 class="cr-envelope__title"><a', $html, 'untitled omits p-name and clamps heading' );
		cr_reply_not_contains( 'cr-envelope__address', $html, 'missing host has no address' );
		$html = StreamReply\envelope_card( 'x', cr_reply_stream_block( 9 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<h4 class="cr-envelope__title"><a', $html, 'high heading clamps to h4' );

		$GLOBALS['cr_reply_facts'][ $post->ID ] = array( 'url' => 'javascript:alert(1)', 'host' => 'target.test', 'name' => 'Target title' );
		$html = StreamReply\envelope_card( 'x', cr_reply_stream_block( 2 ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_not_contains( 'u-in-reply-to', $html, 'unsafe target omitted' );
	}
);

cr_reply_finish();
