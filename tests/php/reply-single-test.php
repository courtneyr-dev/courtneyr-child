<?php
/**
 * Standalone checks for reply singles.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/reply-bootstrap.php';

use Courtneyr\Child\SingleReply as SingleReply;

function cr_reply_cite_fixture( string $link_text = 'A cited post' ): string {
	return '<article class="wp-block-post-kinds-indieweb-reply-card pk-card k-reply h-cite u-in-reply-to"><div class="pk-badge">badge</div><span class="pk-kindlabel">Reply</span><div class="pk-body"><h2 class="pk-title p-name"><a class="u-url" href="https://target.test/post">' . esc_html( $link_text ) . '</a></h2><p class="pk-sub"><span class="p-author h-card"><span class="p-name">A. Source</span></span></p><div class="pk-note p-content"><p>Quoted text.</p></div><div class="pk-media"><img class="u-photo" src="https://target.test/img.jpg" alt=""></div><div class="pk-meta"><time class="dt-published" datetime="2026-10-10">October 10, 2026</time></div><data class="u-in-reply-to" value="https://target.test/post" hidden></data></div></article>';
}

cr_reply_group(
	'citation card trims reply chrome and adds one host line',
	static function (): void {
		$post = cr_reply_reset();
		$GLOBALS['cr_reply_single'] = true;
		$GLOBALS['cr_reply_facts'][ $post->ID ] = array( 'host' => 'target.test' );
		$html = SingleReply\cite_card( cr_reply_cite_fixture(), array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( 'cr-letter-cite', $html, 'cite class' );
		cr_reply_not_contains( 'pk-kindlabel', $html, 'kind label removed' );
		cr_reply_not_contains( 'pk-badge', $html, 'badge removed' );
		cr_reply_contains( '<span class="p-author h-card"><span class="p-name">A. Source</span></span><span class="cr-letter-cite__sep" aria-hidden="true">·</span><span class="cr-letter-cite__host">target.test</span></p>', $html, 'host follows the author on one line' );
		cr_reply_not_contains( '</h2><p class="cr-letter-cite__host">', $html, 'host not under the title when an author exists' );
		cr_reply_same( 1, substr_count( $html, 'cr-letter-cite__host' ), 'one host line' );
		foreach ( array( 'h2 class="pk-title p-name"', 'p-author h-card', 'p-content', '<img', 'dt-published', 'u-in-reply-to' ) as $kept ) {
			cr_reply_contains( $kept, $html, $kept . ' kept' );
		}
		$no_author = str_replace( '<p class="pk-sub"><span class="p-author h-card"><span class="p-name">A. Source</span></span></p>', '', cr_reply_cite_fixture() );
		$bare      = SingleReply\cite_card( $no_author, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<h2 class="pk-title p-name"><a class="u-url" href="https://target.test/post">A cited post</a></h2><p class="cr-letter-cite__host">target.test</p>', $bare, 'no author: host after heading' );
		cr_reply_not_contains( 'cr-letter-cite__sep', $bare, 'no author: no separator' );
		$already = SingleReply\cite_card( cr_reply_cite_fixture( 'target.test' ), array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_not_contains( 'cr-letter-cite__host', $already, 'matching link text suppresses host' );
	}
);

cr_reply_group(
	'citation card ignores unrelated contexts and degrades missing structure',
	static function (): void {
		$post = cr_reply_reset();
		$GLOBALS['cr_reply_single'] = true;
		$GLOBALS['cr_reply_facts'][ $post->ID ] = array( 'host' => 'target.test' );
		$input = cr_reply_cite_fixture();
		cr_reply_same( $input, SingleReply\cite_card( $input, array(), new WP_Block( array( 'postId' => 999 ) ) ), 'other post unchanged' );
		$GLOBALS['cr_reply_kind'] = 'like';
		cr_reply_same( $input, SingleReply\cite_card( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'other kind unchanged' );
		$GLOBALS['cr_reply_kind']   = 'reply';
		$GLOBALS['cr_reply_single'] = false;
		cr_reply_same( $input, SingleReply\cite_card( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'archive unchanged' );
		$GLOBALS['cr_reply_single'] = true;
		$GLOBALS['cr_reply_protected'][ $post->ID ] = true;
		cr_reply_same( $input, SingleReply\cite_card( $input, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'protected unchanged' );
		$GLOBALS['cr_reply_protected'] = array();
		$legacy = '<article class="pk-card k-reply"><div class="pk-body"></div></article>';
		$out    = SingleReply\cite_card( $legacy, array(), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_not_contains( '<p class="cr-letter-cite__host"></p>', $out, 'no empty host element' );
	}
);

cr_reply_group(
	'sheet wraps content and appends a plain signature',
	static function (): void {
		$post = cr_reply_reset();
		$GLOBALS['cr_reply_single'] = true;
		$GLOBALS['cr_reply_authors'][ $post->post_author ] = 'Courtney Author';
		$html = SingleReply\sheet( '<div class="entry-content e-content"><p>Body text.</p></div>', array() );
		cr_reply_contains( '<div class="cr-letter-sheet"><div class="entry-content e-content"><p>Body text.</p></div><p class="cr-letter__signature">Courtney Author</p></div>' . SingleReply\LETTER_CLOSE, $html, 'body, signature, then the letter close marker' );
		cr_reply_not_contains( 'p-author', SingleReply\signature_html( $post ), 'signature no p-author' );
		cr_reply_not_contains( 'h-card', SingleReply\signature_html( $post ), 'signature no h-card' );
		cr_reply_not_contains( '<a', SingleReply\signature_html( $post ), 'signature no link' );
		cr_reply_not_contains( 'Reply', $html, 'added markup has no kind copy' );
		cr_reply_not_contains( 'in reply to', $html, 'added markup has no phrase' );

		$GLOBALS['cr_reply_authors'][ $post->post_author ] = '';
		cr_reply_same( '', SingleReply\signature_html( $post ), 'empty display name prints nothing' );
		$GLOBALS['cr_reply_protected'][ $post->ID ] = true;
		$input = '<div class="entry-content e-content">Body</div>';
		cr_reply_same( $input, SingleReply\sheet( $input, array() ), 'protected unchanged' );
	}
);

cr_reply_group(
	'one letter sheet wraps the post header and the body, never half of it',
	static function (): void {
		$post = cr_reply_reset();
		$GLOBALS['cr_reply_single'] = true;
		$header = array( 'attrs' => array( 'className' => 'single-post__header' ) );
		$main   = array( 'attrs' => array( 'className' => 'single-post' ) );
		$opened = SingleReply\letter_open( '<div class="single-post__header"><h1>T</h1></div>', $header );
		cr_reply_same( SingleReply\LETTER_OPEN . '<div class="single-post__header"><h1>T</h1></div>', $opened, 'header gets the open marker' );
		cr_reply_same( '<div class="single-post__lede-meta"></div>', SingleReply\letter_open( '<div class="single-post__lede-meta"></div>', array( 'attrs' => array( 'className' => 'single-post__lede-meta' ) ) ), 'other groups unmarked' );
		$page = '<main>' . $opened . '<div class="cr-letter-sheet">B</div>' . SingleReply\LETTER_CLOSE . '<nav>N</nav></main>';
		$out  = SingleReply\letter( $page, $main );
		cr_reply_same( '<main><div class="cr-letter alignwide cr-paper-sheet cr-paper-sheet--torn cr-paper--printer-ivory"><div class="single-post__header"><h1>T</h1></div><div class="cr-letter-sheet">B</div></div><nav>N</nav></main>', $out, 'header and body inside the letter, nav outside' );
		cr_reply_same( '<main><div class="single-post__header"><h1>T</h1></div><nav>N</nav></main>', SingleReply\letter( '<main>' . $opened . '<nav>N</nav></main>', $main ), 'open without close: markers stripped, no wrapper' );
		cr_reply_same( $page, SingleReply\letter( $page, $header ), 'only the main group wraps' );
		$GLOBALS['cr_reply_protected'][ $post->ID ] = true;
		cr_reply_same( '<div class="single-post__header"></div>', SingleReply\letter_open( '<div class="single-post__header"></div>', $header ), 'protected: no letter' );
		$GLOBALS['cr_reply_protected'] = array();
		$GLOBALS['cr_reply_single']    = false;
		cr_reply_same( '<div class="single-post__header"></div>', SingleReply\letter_open( '<div class="single-post__header"></div>', $header ), 'not a reply single: no letter' );
	}
);

cr_reply_finish();
