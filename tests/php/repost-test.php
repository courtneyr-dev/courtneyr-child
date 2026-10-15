<?php
/**
 * `php tests/php/repost-test.php`: checks the repost archive clipping, the
 * month spread labels, the single clipping and the meta-only clipping in
 * inc/repost.php without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/repost-bootstrap.php';

use Courtneyr\Child\Repost as Repost;

/** A parsed stream-card block with the archive style. */
function cr_rp_archive_block( int $level = 3 ): array {
	return array(
		'attrs' => array(
			'headingLevel' => $level,
			'className'    => 'is-style-cr-repost-clipping',
		),
	);
}

/** Render the archive clipping for a post. */
function cr_rp_clip( int $post_id, int $level = 3 ): string {
	return Repost\clipping( '<article class="pk-card plugin">Plugin card</article>', cr_rp_archive_block( $level ), cr_rp_instance( $post_id ) );
}

/** Put the request on a repost single and render its card from attributes. */
function cr_rp_single( int $post_id, array $attrs, array $facts = array() ): string {
	$GLOBALS['cr_rp']['single']      = true;
	$GLOBALS['cr_rp']['queried']     = $post_id;
	$GLOBALS['cr_rp']['global_post'] = $post_id;
	if ( array() !== $facts ) {
		$GLOBALS['cr_rp']['facts'][ $post_id ] = $facts;
	}
	return render_block(
		array(
			'blockName' => 'post-kinds-indieweb/repost-card',
			'attrs'     => $attrs,
		)
	);
}

cr_rp_group(
	'archive clipping gates on the block style, the kind, the facts and an empty card',
	static function (): void {
		cr_rp_post( 1, '', cr_rp_facts() );
		cr_rp_post( 2, '', cr_rp_facts(), false );
		cr_rp_post( 3, '', array() );
		$plugin = '<article class="pk-card plugin">Plugin card</article>';
		cr_rp_same( $plugin, Repost\clipping( $plugin, array( 'attrs' => array( 'className' => 'is-style-cr-sticker' ) ), cr_rp_instance( 1 ) ), 'another style stays the plugin card' );
		cr_rp_same( $plugin, Repost\clipping( $plugin, array( 'attrs' => array() ), cr_rp_instance( 1 ) ), 'no style stays the plugin card' );
		cr_rp_same( $plugin, cr_rp_clip( 2 ), 'a non-repost stays the plugin card' );
		cr_rp_same( $plugin, cr_rp_clip( 3 ), 'a repost with no facts keeps the plugin card' );
		cr_rp_same( $plugin, cr_rp_clip( 99 ), 'a missing post keeps the plugin card' );
		cr_rp_same( '', Repost\clipping( '', cr_rp_archive_block(), cr_rp_instance( 1 ) ), 'an empty card stays empty' );
	}
);

cr_rp_group(
	'a card-only clipping holds one title link, one credit and a hidden cite',
	static function (): void {
		$facts = cr_rp_facts(
			array(
				'picture' => array(
					'url'           => 'https://example.test/uploads/55.png',
					'attachment_id' => 55,
				),
			)
		);
		cr_rp_post( 7, '', $facts );
		$html = cr_rp_clip( 7 );

		cr_rp_contains( '<div class="cr-repost-clip cr-repost-clip--tape-', $html, 'the unclipped wrapper' );
		cr_rp_contains( 'class="cr-repost-clip__paper cr-paper-sheet cr-paper-sheet--torn cr-paper-sheet--flat"', $html, 'the paper takes the shared torn edge' );
		cr_rp_contains( '<h3 class="cr-repost-clip__title"><a id="cr-repost-title-7" class="u-url" href="https://example.test/repost/7/">Paper Trails for the Web</a></h3>', $html, 'the one title link, in an h3' );
		cr_rp_contains( '<p class="cr-repost-clip__credit" id="cr-repost-credit-7">Jo Example</p>', $html, 'the author is the credit' );
		cr_rp_same( 1, cr_rp_visible_links( $html ), 'one visible anchor' );
		cr_rp_same( 1, substr_count( $html, '<a ' ), 'no second anchor, hidden or not' );
		cr_rp_lacks( 'p-name', str_replace( 'class="p-name"', '', substr( $html, 0, (int) strpos( $html, 'pk-kind-cite' ) ) ), 'the title carries no p-name' );
		cr_rp_lacks( 'pk-card', $html, 'no pk-card and so no h-entry of its own' );
		cr_rp_lacks( 'h-entry', $html, 'no h-entry class' );
		foreach ( array( '<time', 'pk-kindlabel', 'pk-note', 'pk-badge', 'REPOSTED' ) as $gone ) {
			cr_rp_lacks( $gone, $html, $gone . ' is cut' );
		}
		cr_rp_contains( '<span class="pk-kind-cite h-cite u-repost-of" hidden>', $html, 'the hidden h-cite stays' );
		cr_rp_contains( '<data class="u-url" value="https://blog.example.net/paper-trails"></data>', $html, 'the cite holds the source URL' );
		cr_rp_contains( '<div class="cr-repost-clip__frame" data-cr-cover-fallback><img', $html, 'the frame carries the failed-load hook' );
		cr_rp_contains( 'alt=""', $html, 'the archive image has empty alt' );
		cr_rp_same( 'medium_large', $GLOBALS['cr_rp']['attachment_calls'][0][1] ?? '', 'the attachment uses medium_large' );
		cr_rp_before( 'cr-repost-clip__frame', 'cr-repost-clip__title', $html, 'the image comes before the title' );
	}
);

cr_rp_group(
	'the credit falls back from the author to the host and then to nothing',
	static function (): void {
		cr_rp_post( 1, '', cr_rp_facts( array( 'author' => '' ) ) );
		cr_rp_contains( '>blog.example.net</p>', cr_rp_clip( 1 ), 'the host is the credit with no author' );
		cr_rp_post( 2, '', cr_rp_facts( array( 'author' => '', 'host' => '' ) ) );
		cr_rp_lacks( 'cr-repost-clip__credit', cr_rp_clip( 2 ), 'no credit when there is neither' );
	}
);

cr_rp_group(
	'the name comes from the facts, with a title fallback for an older plugin',
	static function (): void {
		cr_rp_post( 1, '', cr_rp_facts( array( 'name' => 'quiet.example.com', 'cited_title' => '', 'host' => 'quiet.example.com' ) ) );
		cr_rp_contains( '>quiet.example.com</a>', cr_rp_clip( 1 ), 'a URL-only repost is named by its host' );
		$facts = cr_rp_facts();
		unset( $facts['name'] );
		cr_rp_post( 2, 'Why I Kept This', cr_rp_facts( array( 'cited_title' => '', 'name' => '' ) ) );
		cr_rp_contains( '>Why I Kept This</a>', cr_rp_clip( 2 ), 'the post title stands in when the name key is empty' );
		cr_rp_post( 3, '', cr_rp_facts( array( 'cited_title' => '', 'name' => '', 'host' => '' ) ) );
		cr_rp_contains( '>Repost</a>', cr_rp_clip( 3 ), 'the kind word is the last resort' );
		cr_rp_post( 4, '', cr_rp_facts( array( 'name' => '<script>alert(1)</script>Tricky' ) ) );
		$html = cr_rp_clip( 4 );
		cr_rp_lacks( '<script>', $html, 'the name is escaped' );
		cr_rp_contains( '&lt;script&gt;', $html, 'the escaped name prints' );
	}
);

cr_rp_group(
	'the heading level follows the block and stays between 2 and 4',
	static function (): void {
		cr_rp_post( 1, '', cr_rp_facts() );
		cr_rp_contains( '<h2 class="cr-repost-clip__title">', cr_rp_clip( 1, 2 ), 'level 2' );
		cr_rp_contains( '<h4 class="cr-repost-clip__title">', cr_rp_clip( 1, 6 ), 'level 6 clamps to 4' );
		cr_rp_contains( '<h2 class="cr-repost-clip__title">', cr_rp_clip( 1, 1 ), 'level 1 clamps to 2' );
	}
);

cr_rp_group(
	'a protected repost keeps the plugin card whole inside a clipping',
	static function (): void {
		cr_rp_post( 5, 'Locked Clipping', cr_rp_facts() );
		$GLOBALS['cr_rp']['protected'][5] = true;
		$html                              = cr_rp_clip( 5 );
		cr_rp_contains( 'cr-repost-clip cr-repost-clip--protected cr-repost-clip--tape-', $html, 'the protected wrapper' );
		cr_rp_contains( '<article class="pk-card plugin">Plugin card</article>', $html, 'the plugin card sits inside, untouched' );
		cr_rp_lacks( 'Paper Trails', $html, 'no cited title leaks' );
		cr_rp_lacks( 'blog.example.net', $html, 'no source host leaks' );
		cr_rp_lacks( 'pk-kind-cite', $html, 'no hidden cite' );
		cr_rp_same( 0, count( $GLOBALS['cr_rp']['attachment_calls'] ), 'no image is read' );
	}
);

cr_rp_group(
	'images in the first four places of the first section load eagerly',
	static function (): void {
		foreach ( range( 10, 16 ) as $id ) {
			$picture = array(
				'url'           => 'https://example.test/uploads/' . $id . '.png',
				'attachment_id' => $id,
			);
			cr_rp_post( $id, '', cr_rp_facts( array( 'picture' => $picture, 'name' => 'Item ' . $id ) ) );
		}
		$GLOBALS['cr_rp']['page_ids'] = range( 10, 16 );
		foreach ( range( 10, 14 ) as $id ) {
			$GLOBALS['cr_rp']['groups'][ $id ] = '2026-10';
		}
		$GLOBALS['cr_rp']['groups'][15] = '2026-09';
		$GLOBALS['cr_rp']['groups'][16] = '2026-09';

		$first = cr_rp_clip( 10 );
		cr_rp_contains( 'loading="eager"', $first, 'the first item is eager' );
		cr_rp_contains( 'fetchpriority="high"', $first, 'the first item has high fetch priority' );
		cr_rp_contains( 'skip-lazy', $first, 'the first item opts out of lazy loading' );
		cr_rp_contains( 'data-skip-lazy', $first, 'the first item carries data-skip-lazy' );
		cr_rp_contains( 'loading="eager"', cr_rp_clip( 13 ), 'the fourth item is eager' );
		$fifth = cr_rp_clip( 14 );
		cr_rp_contains( 'loading="lazy"', $fifth, 'the fifth item is lazy' );
		cr_rp_lacks( 'fetchpriority', $fifth, 'the fifth item sets no fetch priority' );
		cr_rp_contains( 'loading="lazy"', cr_rp_clip( 15 ), 'an item in the second section is lazy' );
		cr_rp_contains( 'loading="lazy"', cr_rp_clip( 16 ), 'the second section stays lazy' );
	}
);

cr_rp_group(
	'a remote or unreadable attachment image falls back to a plain img',
	static function (): void {
		cr_rp_post( 1, '', cr_rp_facts( array( 'picture' => array( 'url' => 'https://images.example.org/a.jpg', 'attachment_id' => 0 ) ) ) );
		$html = cr_rp_clip( 1 );
		cr_rp_contains( '<img class="cr-repost-clip__img" src="https://images.example.org/a.jpg" alt="" loading="lazy" decoding="async">', $html, 'a remote image is a plain img' );
		cr_rp_same( 0, count( $GLOBALS['cr_rp']['attachment_calls'] ), 'no attachment lookup for a remote image' );

		$GLOBALS['cr_rp']['no_attachment_image'] = true;
		cr_rp_post( 2, '', cr_rp_facts( array( 'picture' => array( 'url' => 'https://example.test/uploads/2.png', 'attachment_id' => 2 ) ) ) );
		cr_rp_contains( 'src="https://example.test/uploads/2.png"', cr_rp_clip( 2 ), 'an attachment that renders nothing falls back to its URL' );

		cr_rp_post( 3, '', cr_rp_facts( array( 'picture' => array() ) ) );
		cr_rp_lacks( 'cr-repost-clip__frame', cr_rp_clip( 3 ), 'no picture means no frame' );
	}
);

cr_rp_group(
	'two items with one name get labels that tell them apart',
	static function (): void {
		cr_rp_post( 21, '', cr_rp_facts( array( 'name' => 'Notes on Local Files', 'author' => 'Sample Author' ) ) );
		cr_rp_post( 22, '', cr_rp_facts( array( 'name' => '  notes on LOCAL files ', 'author' => '', 'host' => 'blog.example.net' ) ) );
		cr_rp_post( 23, '', cr_rp_facts( array( 'name' => 'Something Else' ) ) );
		cr_rp_post( 24, 'Notes on Local Files', cr_rp_facts( array( 'name' => 'Notes on Local Files' ) ) );
		$GLOBALS['cr_rp']['protected'][24] = true;
		$GLOBALS['cr_rp']['page_ids']      = array( 21, 22, 23, 24 );

		cr_rp_contains( ' aria-labelledby="cr-repost-title-21 cr-repost-credit-21"', cr_rp_clip( 21 ), 'the first repeat names title and credit' );
		cr_rp_contains( ' aria-labelledby="cr-repost-title-22 cr-repost-credit-22"', cr_rp_clip( 22 ), 'the second repeat names its host credit' );
		cr_rp_lacks( 'aria-labelledby', cr_rp_clip( 23 ), 'a unique name needs no label' );
		cr_rp_lacks( 'aria-labelledby', cr_rp_clip( 24 ), 'a protected item stays out of the comparison' );
	}
);

cr_rp_group(
	'a repeat with no credit takes the month heading as its second name',
	static function (): void {
		cr_rp_post( 31, '', cr_rp_facts( array( 'name' => 'Same', 'author' => '', 'host' => '' ) ) );
		cr_rp_post( 32, '', cr_rp_facts( array( 'name' => 'Same', 'author' => '', 'host' => '' ) ) );
		$GLOBALS['cr_rp']['page_ids']   = array( 31, 32 );
		$GLOBALS['cr_rp']['group_slug'] = '2026-08';
		cr_rp_contains( ' aria-labelledby="cr-repost-title-31 pkiw-group-2026-08"', cr_rp_clip( 31 ), 'the heading id stands in for the credit' );
		unset( $GLOBALS['cr_rp']['group_slug'] );
		cr_rp_contains( ' aria-labelledby="cr-repost-title-32"', cr_rp_clip( 32 ), 'with no group known only the title id is named' );
	}
);

cr_rp_group(
	'month sections are named by their headings',
	static function (): void {
		$html = '<div class="wp-block-post-template cr-repost-zine__spreads pkiw-grouped">'
			. '<section class="pkiw-group" data-pkiw-sections="2"><h2 id="pkiw-group-2026-08" class="pkiw-group__heading">August 2026</h2><ul class="pkiw-group__items"><li><a href="#">One</a></li></ul></section>'
			. '<section class="pkiw-group" data-pkiw-sections="2"><h2 id="pkiw-group-2026-07" class="pkiw-group__heading">July 2026</h2><ul class="pkiw-group__items"><li>Two</li></ul></section>'
			. '<section class="pkiw-group"><h2 class="pkiw-group__heading">No id</h2></section>'
			. '</div>';
		$block = array( 'attrs' => array( 'className' => 'cr-repost-zine__spreads' ) );
		$out   = Repost\label_sections( $html, $block );
		cr_rp_assert( 1 === preg_match( '/<section\b[^>]*aria-labelledby="pkiw-group-2026-08"[^>]*class="pkiw-group"/', $out ), 'the first section is named' );
		cr_rp_assert( 1 === preg_match( '/<section\b[^>]*aria-labelledby="pkiw-group-2026-07"[^>]*class="pkiw-group"/', $out ), 'the second section is named' );
		cr_rp_contains( '<section class="pkiw-group"><h2 class="pkiw-group__heading">No id</h2>', $out, 'a heading with no id leaves its section unnamed' );
		cr_rp_same( 2, substr_count( $out, 'aria-labelledby' ), 'two sections are named' );
		cr_rp_same( $html, Repost\label_sections( $html, array( 'attrs' => array( 'className' => 'other' ) ) ), 'another Post Template is untouched' );
		cr_rp_same( '', Repost\label_sections( '', $block ), 'nothing in, nothing out' );
		$h3 = '<div><section class="pkiw-group"><h3 id="h3-id">Month</h3></section></div>';
		cr_rp_contains( 'aria-labelledby="h3-id"', Repost\label_sections( $h3, $block ), 'a month heading at level 3 names its section too' );
	}
);

cr_rp_group(
	'the single clipping cuts the label and badge and hides the card date',
	static function (): void {
		cr_rp_post( 41, '', cr_rp_facts() );
		$html = cr_rp_single(
			41,
			array(
				'title'      => 'Paper Trails for the Web',
				'url'        => 'https://blog.example.net/paper-trails',
				'author'     => 'Jo Example',
				'description' => 'The cited summary.',
				'image'      => 'https://images.example.org/a01.jpg',
				'imageAlt'   => 'A cabin',
				'repostedAt' => '2026-10-14T08:00:00',
			)
		);
		cr_rp_lacks( 'pk-badge', $html, 'no badge' );
		cr_rp_lacks( 'pk-kindlabel', $html, 'no kind label' );
		cr_rp_assert( 1 === preg_match( '/<div\b[^>]*\bhidden\b[^>]*class="pk-meta"/', $html ), 'the meta row is hidden' );
		cr_rp_contains( '<time class="dt-published"', $html, 'its dt-published stays in the DOM' );
		cr_rp_contains( '<p class="pk-sub cr-repost-clip__from">From blog.example.net</p>', $html, 'the host line' );
		cr_rp_contains( 'by <span class="p-author h-card"><span class="p-name">Jo Example</span></span>', $html, 'the by-credit wraps the unchanged author span' );
		cr_rp_same( 1, cr_rp_visible_links( $html ), 'one visible anchor, to the source' );
		cr_rp_lacks( 'View the original', $html, 'no second link text' );
		cr_rp_lacks( 'figcaption', $html, 'no figcaption' );
		cr_rp_contains( 'alt="A cabin"', $html, 'the stored alt stays' );
		cr_rp_contains( 'class="pk-card k-repost h-cite u-repost-of wp-block-post-kinds-indieweb-repost-card cr-repost-clip cr-repost-clip--single cr-paper-sheet cr-paper-sheet--torn"', $html, 'the article keeps the plugin classes and gains the clipping classes' );
		cr_rp_before( 'pk-embed', 'pk-caption', $html, 'the image comes before the text' );
		cr_rp_contains( '<div data-cr-cover-fallback class="pk-embed pk-embed--photo">', $html, 'the picture carries the failed-load hook' );
		cr_rp_before( 'cr-repost-clip__from', '<h2', $html, 'the host line sits above the title' );
		cr_rp_contains( '<h2 class="pk-title p-name">', $html, 'the cited title stays an h2 under the post title' );
		cr_rp_lacks( '<h1', $html, 'no h1 when the post has its own title' );
	}
);

cr_rp_group(
	'the cited title becomes the one h1 when the post has no title of its own',
	static function (): void {
		cr_rp_post( 42, '', cr_rp_facts() );
		$GLOBALS['cr_rp']['h1'][42] = true;
		$html                        = cr_rp_single( 42, array( 'title' => 'Quiet Web, Loud Links', 'url' => 'https://blog.example.net/q' ) );
		cr_rp_contains( '<h1 class="pk-title p-name">', $html, 'the card heading is retagged' );
		cr_rp_same( 1, substr_count( $html, '<h1' ), 'one h1' );
		cr_rp_same( 1, substr_count( $html, '</h1>' ), 'one closing h1' );
		cr_rp_lacks( '<h2', $html, 'no h2 left' );
		cr_rp_contains( '>Quiet Web, Loud Links</a>', $html, 'the cited title is the text' );
	}
);

cr_rp_group(
	'a URL-only card prints its host heading and a repost with no URL prints plain text',
	static function (): void {
		cr_rp_post( 43, '', cr_rp_facts( array( 'cited_title' => '', 'name' => 'quiet.example.com', 'host' => 'quiet.example.com', 'url' => 'https://quiet.example.com/posts/14' ) ) );
		$html = cr_rp_single( 43, array( 'title' => 'https://quiet.example.com/posts/14', 'url' => 'https://quiet.example.com/posts/14' ) );
		cr_rp_contains( '<h2 class="pk-title">', $html, 'a synthetic heading has no p-name' );
		cr_rp_contains( '>quiet.example.com</a>', $html, 'the host is the link text' );
		cr_rp_contains( 'From quiet.example.com', $html, 'the host line prints' );

		cr_rp_post( 44, '', cr_rp_facts( array( 'url' => '', 'host' => '', 'cited_title' => 'A Title With No Link', 'author' => 'R. Placeholder' ) ) );
		$plain = cr_rp_single( 44, array( 'title' => 'A Title With No Link', 'author' => 'R. Placeholder' ) );
		cr_rp_same( 0, cr_rp_visible_links( $plain ), 'no source anchor' );
		cr_rp_lacks( 'cr-repost-clip__from', $plain, 'no host line' );
		cr_rp_contains( 'by <span class="p-author h-card">', $plain, 'the author still prints' );

		cr_rp_post( 45, '', cr_rp_facts( array( 'author' => '' ) ) );
		cr_rp_lacks( 'by <span', cr_rp_single( 45, array( 'title' => 'T', 'url' => 'https://blog.example.net/t' ) ), 'no by-line without an author' );
	}
);

cr_rp_group(
	'the single clipping leaves protected posts, other requests and other posts alone',
	static function (): void {
		cr_rp_post( 51, '', cr_rp_facts() );
		$attrs = array( 'title' => 'T', 'url' => 'https://blog.example.net/t', 'author' => 'A' );
		$plain = cr_rp_card_html( $attrs );

		$GLOBALS['cr_rp']['protected'][51] = true;
		cr_rp_lacks( 'cr-repost-clip', cr_rp_single( 51, $attrs ), 'a protected post is untouched' );
		unset( $GLOBALS['cr_rp']['protected'][51] );

		cr_rp_reset();
		cr_rp_post( 51, '', cr_rp_facts() );
		$GLOBALS['cr_rp']['global_post'] = 51;
		cr_rp_same( $plain, render_block( array( 'blockName' => 'post-kinds-indieweb/repost-card', 'attrs' => $attrs ) ), 'off a single the card is untouched' );

		$GLOBALS['cr_rp']['single']  = true;
		$GLOBALS['cr_rp']['queried'] = 99;
		cr_rp_same( $plain, render_block( array( 'blockName' => 'post-kinds-indieweb/repost-card', 'attrs' => $attrs ) ), 'a card for another post is untouched' );

		$GLOBALS['cr_rp']['queried']          = 51;
		$GLOBALS['cr_rp']['reposts'][51]      = false;
		cr_rp_same( $plain, render_block( array( 'blockName' => 'post-kinds-indieweb/repost-card', 'attrs' => $attrs ) ), 'a post that is not a repost is untouched' );
		cr_rp_same( '', Repost\single_card( '', array(), cr_rp_instance( 51 ) ), 'an empty card stays empty' );
		cr_rp_same( '<p>x</p>', Repost\single_card( '<p>x</p>', array(), cr_rp_instance( 51 ) ), 'markup that is not a repost card stays' );
	}
);

cr_rp_group(
	'a repost with no card block gets a clipping built from its facts',
	static function (): void {
		$post = cr_rp_post(
			61,
			'',
			cr_rp_facts(
				array(
					'url'         => 'https://wiki.example.com/meta-only',
					'host'        => 'wiki.example.com',
					'cited_title' => 'Meta Only Clipping',
					'author'      => 'Sample Author',
					'summary'     => 'A one-sentence summary.',
					'card_image'  => 'https://images.example.org/u8.png',
				)
			)
		);
		$post->post_content                   = '<p>Filed from the classic editor.</p>';
		$GLOBALS['cr_rp']['single']           = true;
		$GLOBALS['cr_rp']['queried']          = 61;
		$GLOBALS['cr_rp']['global_post']      = 61;
		$GLOBALS['cr_rp']['loop']             = true;
		$content                              = '<p>Filed from the classic editor.</p>';
		$out                                  = Repost\meta_only_clipping( $content );

		cr_rp_same(
			array(
				'title'       => 'Meta Only Clipping',
				'url'         => 'https://wiki.example.com/meta-only',
				'author'      => 'Sample Author',
				'description' => 'A one-sentence summary.',
				'image'       => 'https://images.example.org/u8.png',
			),
			$GLOBALS['cr_rp']['rendered'][0]['attrs'] ?? array(),
			'the synthetic card carries the facts and no alt, rel or date'
		);
		cr_rp_same( 'post-kinds-indieweb/repost-card', $GLOBALS['cr_rp']['rendered'][0]['blockName'] ?? '', 'it is a repost card' );
		cr_rp_same( $content, substr( $out, -strlen( $content ) ), 'the content follows, in its stored order' );
		cr_rp_contains( 'k-repost h-cite u-repost-of', $out, 'the u-repost-of h-cite is printed' );
		cr_rp_contains( 'cr-repost-clip--single', $out, 'the card goes through the single clipping' );
		cr_rp_before( 'cr-repost-clip--single', 'Filed from the classic editor.', $out, 'the clipping is ahead of the paragraph' );
		cr_rp_contains( 'From wiki.example.com', $out, 'the host line prints' );

		$GLOBALS['cr_rp']['facts'][61] = cr_rp_facts( array( 'url' => 'https://wiki.example.com/x', 'cited_title' => '', 'author' => '', 'summary' => '', 'card_image' => '' ) );
		$GLOBALS['cr_rp']['rendered']  = array();
		Repost\meta_only_clipping( $content );
		cr_rp_same( array( 'url' => 'https://wiki.example.com/x' ), $GLOBALS['cr_rp']['rendered'][0]['attrs'] ?? array(), 'empty facts are left out of the card' );
	}
);

cr_rp_group(
	'the meta-only clipping stays off every request that is not a repost single page',
	static function (): void {
		$post                            = cr_rp_post( 62, '', cr_rp_facts() );
		$post->post_content              = '<p>Body.</p>';
		$GLOBALS['cr_rp']['single']      = true;
		$GLOBALS['cr_rp']['queried']     = 62;
		$GLOBALS['cr_rp']['global_post'] = 62;
		$GLOBALS['cr_rp']['loop']        = true;
		$content                         = '<p>Body.</p>';

		$cases = array(
			'not in the loop'     => static function (): void {
				$GLOBALS['cr_rp']['loop'] = false;
			},
			'a feed'              => static function (): void {
				$GLOBALS['cr_rp']['feed'] = true;
			},
			'an excerpt'          => static function (): void {
				$GLOBALS['cr_rp']['doing'] = 'get_the_excerpt';
			},
			'an atproto request'  => static function (): void {
				$GLOBALS['cr_rp']['query_vars']['atproto'] = '1';
			},
			'another queried post' => static function (): void {
				$GLOBALS['cr_rp']['queried'] = 7;
			},
			'a protected post'    => static function (): void {
				$GLOBALS['cr_rp']['protected'][62] = true;
			},
			'a post with a card'  => static function () use ( $post ): void {
				$post->post_content = '<!-- wp:post-kinds-indieweb/repost-card {"title":"T"} /-->';
			},
			'a clipping already there' => static function (): void {
				$GLOBALS['cr_rp']['already'] = true;
			},
			'a non-repost'        => static function (): void {
				$GLOBALS['cr_rp']['reposts'][62] = false;
			},
			'no singular request' => static function (): void {
				$GLOBALS['cr_rp']['single'] = false;
			},
		);
		foreach ( $cases as $name => $change ) {
			$GLOBALS['cr_rp']['single']      = true;
			$GLOBALS['cr_rp']['queried']     = 62;
			$GLOBALS['cr_rp']['loop']        = true;
			$GLOBALS['cr_rp']['feed']        = false;
			$GLOBALS['cr_rp']['doing']       = null;
			$GLOBALS['cr_rp']['query_vars']  = array();
			$GLOBALS['cr_rp']['protected']   = array();
			$GLOBALS['cr_rp']['reposts'][62] = true;
			$post->post_content              = $content;
			$change();
			$input = isset( $GLOBALS['cr_rp']['already'] ) ? '<article class="k-repost">x</article>' . $content : $content;
			unset( $GLOBALS['cr_rp']['already'] );
			cr_rp_same( $input, Repost\meta_only_clipping( $input ), $name . ' leaves the content alone' );
		}

		$GLOBALS['cr_rp']['single']      = true;
		$GLOBALS['cr_rp']['queried']     = 62;
		$GLOBALS['cr_rp']['loop']        = true;
		$GLOBALS['cr_rp']['feed']        = false;
		$GLOBALS['cr_rp']['doing']       = null;
		$GLOBALS['cr_rp']['query_vars']  = array();
		$GLOBALS['cr_rp']['protected']   = array();
		$GLOBALS['cr_rp']['reposts'][62] = true;
		$post->post_content              = $content;
		$GLOBALS['cr_rp']['facts'][62]   = cr_rp_facts( array( 'url' => '', 'cited_title' => '' ) );
		cr_rp_same( $content, Repost\meta_only_clipping( $content ), 'a repost with no URL and no title has nothing to print' );
	}
);

cr_rp_group(
	'the featured image counts as a repeat when the card holds an image',
	static function (): void {
		$with    = cr_rp_post( 71, '', cr_rp_facts( array( 'card_image' => 'https://images.example.org/a.png' ) ) );
		$without = cr_rp_post( 72, '', cr_rp_facts( array( 'card_image' => '' ) ) );
		$other   = cr_rp_post( 73, '', cr_rp_facts( array( 'card_image' => 'https://images.example.org/a.png' ) ), false );
		cr_rp_same( true, Repost\featured_repeats( false, $with ), 'a card image repeats the featured image' );
		cr_rp_same( false, Repost\featured_repeats( false, $without ), 'no card image, no repeat' );
		cr_rp_same( false, Repost\featured_repeats( false, $other ), 'another kind is not decided here' );
		cr_rp_same( true, Repost\featured_repeats( true, $without ), 'an earlier repeat stays a repeat' );
		cr_rp_same( false, Repost\featured_repeats( false, null ), 'no post, no repeat' );
		cr_rp_same( 10, cr_rp_priority( 'courtneyr_child_featured_repeats', 'Courtneyr\\Child\\Repost\\featured_repeats' ), 'the predicate hooks the shared filter' );
	}
);

cr_rp_group(
	'the block style, parity entry, hooks and stylesheet are wired',
	static function (): void {
		Repost\register_item_style();
		cr_rp_same( 'post-kinds-indieweb/stream-card', $GLOBALS['cr_rp']['styles'][0][0] ?? '', 'the style belongs to the stream card' );
		cr_rp_same( 'cr-repost-clipping', $GLOBALS['cr_rp']['styles'][0][1]['name'] ?? '', 'the style name' );

		$entry = Repost\register_editor_parity( array() );
		cr_rp_same( array( 'classes' => array( 'single-post', 'kind-repost' ) ), $entry['repost'] ?? array(), 'repost joins an empty registry' );
		$keep = array( 'repost' => array( 'classes' => array( 'single-post', 'kind-repost', 'extra' ), 'part' => 'kind-repost' ) );
		cr_rp_same( $keep, Repost\register_editor_parity( $keep ), 'an existing entry is left alone' );
		cr_rp_same( array( 'repost' => array( 'classes' => array( 'single-post', 'kind-repost' ) ) ), Repost\register_editor_parity( 'broken' ), 'a filter value that is not an array is replaced' );

		cr_rp_same( 10, cr_rp_priority( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\Repost\\clipping' ), 'the archive clipping runs at 10' );
		cr_rp_same( 10, cr_rp_priority( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamRepost\\clipping_card' ), 'the Stream clipping runs at 10' );
		cr_rp_same( 10, cr_rp_priority( 'render_block_post-kinds-indieweb/repost-card', 'Courtneyr\\Child\\Repost\\single_card' ), 'the single clipping runs at 10' );
		cr_rp_same( 10, cr_rp_priority( 'render_block_core/post-template', 'Courtneyr\\Child\\Repost\\label_sections' ), 'the section labels run at 10' );
		cr_rp_same( 20, cr_rp_priority( 'the_content', 'Courtneyr\\Child\\Repost\\meta_only_clipping' ), 'the meta-only clipping runs at 20, after wpautop' );
		cr_rp_same( 20, cr_rp_priority( 'wp_enqueue_scripts', 'Courtneyr\\Child\\Repost\\enqueue_front_end_styles' ), 'the front end sheet loads after the theme sheets' );
		cr_rp_same( 10, cr_rp_priority( 'enqueue_block_assets', 'Courtneyr\\Child\\Repost\\enqueue_editor_styles' ), 'the editor sheet loads on enqueue_block_assets' );
	}
);

cr_rp_group(
	'the stylesheet loads on the archive, the single and the Stream, and in the editor',
	static function (): void {
		Repost\enqueue_front_end_styles();
		cr_rp_same( array(), $GLOBALS['cr_rp']['enqueued'], 'not on an unrelated page' );

		$GLOBALS['cr_rp']['registered_styles'] = array( 'courtneyr-components', 'courtneyr-paper' );
		foreach ( array( 'tax', 'stream' ) as $flag ) {
			$GLOBALS['cr_rp'][ $flag ] = true;
			Repost\enqueue_front_end_styles();
			cr_rp_assert( isset( $GLOBALS['cr_rp']['enqueued']['courtneyr-repost'] ), $flag . ' loads the sheet' );
			$GLOBALS['cr_rp']['enqueued'] = array();
			$GLOBALS['cr_rp'][ $flag ]    = false;
		}

		$GLOBALS['cr_rp']['single']  = true;
		$GLOBALS['cr_rp']['queried'] = 5;
		cr_rp_post( 5, '', cr_rp_facts() );
		Repost\enqueue_front_end_styles();
		$call = $GLOBALS['cr_rp']['enqueued']['courtneyr-repost'] ?? array();
		cr_rp_same( 'https://example.test/theme/assets/css/cr-repost.css', $call[0] ?? '', 'the sheet path' );
		cr_rp_same( array( 'courtneyr-components', 'courtneyr-paper' ), $call[1] ?? array(), 'only registered handles are dependencies, so a missing one cannot drop the sheet' );
		cr_rp_same( 'test', $call[2] ?? '', 'the sheet carries the theme version' );

		$GLOBALS['cr_rp']['enqueued'] = array();
		$GLOBALS['cr_rp']['admin']    = true;
		Repost\enqueue_front_end_styles();
		cr_rp_same( array(), $GLOBALS['cr_rp']['enqueued'], 'the front end hook does nothing in the admin' );
		Repost\enqueue_editor_styles();
		cr_rp_assert( isset( $GLOBALS['cr_rp']['enqueued']['courtneyr-repost'] ), 'the editor gets the sheet' );

		$GLOBALS['cr_rp']['enqueued'] = array();
		$GLOBALS['cr_rp']['admin']    = false;
		Repost\enqueue_editor_styles();
		cr_rp_same( array(), $GLOBALS['cr_rp']['enqueued'], 'the editor hook does nothing on the front end' );
	}
);

cr_rp_finish();
