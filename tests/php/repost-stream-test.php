<?php
/**
 * `php tests/php/repost-stream-test.php`: checks the repost Stream clipping in
 * inc/stream-repost.php against the plugin's real card and link helpers,
 * without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/repost-bootstrap.php';

use Courtneyr\Child\StreamRepost as StreamRepost;

/** A parsed stream-card block. */
function cr_rps_block( int $level = 2, string $class = '' ): array {
	return array(
		'attrs' => array(
			'headingLevel' => $level,
			'className'    => $class,
		),
	);
}

/** Render the Stream clipping for a post, as the filter chain does. */
function cr_rps_card( int $post_id, int $level = 2 ): string {
	$GLOBALS['cr_rp']['stream']      = true;
	$GLOBALS['cr_rp']['global_post'] = $post_id;
	return StreamRepost\clipping_card( '<article class="pk-card plugin">Plugin card</article>', cr_rps_block( $level ), cr_rp_instance( $post_id ) );
}

/** The text of the DOM in order, as a screen reader would meet it. */
function cr_rps_text_order( string $html ): array {
	$doc      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$xpath = new DOMXPath( $doc );
	$parts = array();
	foreach ( $xpath->query( '//*[not(ancestor-or-self::*[@hidden])][self::span or self::time or self::h2 or self::h3 or self::a or self::p][not(descendant::p)][not(self::a and ancestor::h2) and not(self::a and ancestor::h3)]' ) as $node ) {
		$text = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
		if ( '' !== $text ) {
			$parts[] = $text;
		}
	}
	return array_values( array_unique( $parts ) );
}

cr_rp_group(
	'a card-only repost becomes one compact clipping',
	static function (): void {
		cr_rp_post( 11, '', cr_rp_facts() );
		$GLOBALS['cr_rp']['labels']['stream-card'] = 'Reposted';
		$html                                      = cr_rps_card( 11 );

		cr_rp_same( 1, cr_rp_visible_links( $html ), 'one visible anchor' );
		cr_rp_contains( '<a href="https://example.test/repost/11/">Paper Trails for the Web</a>', $html, 'the title links to the permalink with no class or target' );
		cr_rp_contains( '<data class="u-url" value="https://blog.example.net/paper-trails" hidden></data>', $html, 'the source URL moves into the h-cite as hidden data' );
		cr_rp_before( 'u-url" value="https://blog.example.net/paper-trails"', '<h2', $html, 'the hidden source URL comes before the heading' );
		cr_rp_contains( 'h-cite u-repost-of', $html, 'the card stays an h-cite u-repost-of' );
		cr_rp_contains( '<span class="pk-kindlabel">Reposted</span>', $html, 'the label comes from the plugin label filter' );
		cr_rp_contains( 'pk-card--stream cr-repost-clip--stream cr-repost-clip--tilt-', $html, 'the stream and tilt classes' );
		cr_rp_contains( '<p class="pk-sub cr-repost-clip__host">blog.example.net</p>', $html, 'the host is plain text' );
		cr_rp_contains( '<p class="pk-note p-content">A short summary of the cited post.</p>', $html, 'the cited summary stays as the description' );
		foreach ( array( 'pk-badge', '<img', 'pk-embed', 'pk-meta', 'View the original', 'target="_blank"' ) as $gone ) {
			cr_rp_lacks( $gone, $html, $gone . ' is not on the Stream card' );
		}
		cr_rp_assert( 1 === preg_match( '/<time datetime="2026-10-14T12:00:00\+00:00">October 14, 2026<\/time>/', $html ), 'the date has no dt-published, so the cite claims no source date' );
		cr_rp_lacks( 'class="dt-published"', $html, 'no dt-published on the card' );
	}
);

cr_rp_group(
	'the card reads label, date, title, author, host, description',
	static function (): void {
		cr_rp_post( 12, '', cr_rp_facts() );
		$order = cr_rps_text_order( cr_rps_card( 12 ) );
		cr_rp_same(
			array( 'Repost', 'October 14, 2026', 'Paper Trails for the Web', 'Jo Example', 'blog.example.net', 'A short summary of the cited post.' ),
			$order,
			'the DOM order'
		);

		cr_rp_post( 13, '', cr_rp_facts( array( 'author' => '' ) ) );
		cr_rp_same(
			array( 'Repost', 'October 14, 2026', 'Paper Trails for the Web', 'blog.example.net', 'A short summary of the cited post.' ),
			cr_rps_text_order( cr_rps_card( 13 ) ),
			'with no author the host follows the title'
		);

		cr_rp_post( 14, '', cr_rp_facts( array( 'summary' => '' ) ) );
		cr_rp_lacks( 'pk-note', cr_rps_card( 14 ), 'no description when none is stored' );
	}
);

cr_rp_group(
	'Courtney\'s note never prints and the card is built from the cited facts only',
	static function (): void {
		$post               = cr_rp_post( 15, '', cr_rp_facts( array( 'summary' => 'The source summary.' ) ) );
		$post->post_content = '<!-- wp:post-kinds-indieweb/repost-card /--><!-- wp:paragraph --><p>Saving this for the next zine.</p><!-- /wp:paragraph -->';
		$html               = cr_rps_card( 15 );
		cr_rp_lacks( 'Saving this for the next zine.', $html, 'the note is absent' );
		cr_rp_same(
			array(
				'title'       => 'Paper Trails for the Web',
				'url'         => 'https://blog.example.net/paper-trails',
				'author'      => 'Jo Example',
				'description' => 'The source summary.',
			),
			$GLOBALS['cr_rp']['rendered'][0]['attrs'] ?? array(),
			'the synthetic card holds no image, rel or repostedAt'
		);
	}
);

cr_rp_group(
	'a URL-only repost names itself by its host, with no p-name',
	static function (): void {
		cr_rp_post(
			16,
			'',
			cr_rp_facts(
				array(
					'url'         => 'https://letters.example.com/notes/9',
					'host'        => 'letters.example.com',
					'cited_title' => '',
					'author'      => '',
					'summary'     => '',
					'name'        => 'letters.example.com',
				)
			)
		);
		$html = cr_rps_card( 16 );
		cr_rp_contains( '<h2 class="pk-title">', $html, 'the heading has no p-name' );
		cr_rp_contains( '>letters.example.com</a>', $html, 'the host heading links to the permalink' );
		cr_rp_contains( '<data class="u-url" value="https://letters.example.com/notes/9" hidden></data>', $html, 'the source URL is still in the cite' );
		cr_rp_same( 1, cr_rp_visible_links( $html ), 'one visible anchor' );
	}
);

cr_rp_group(
	'a repost with a title but no URL links its plain title to the permalink',
	static function (): void {
		cr_rp_post( 17, '', cr_rp_facts( array( 'url' => '', 'host' => '', 'cited_title' => 'A Title With No Link', 'author' => 'R. Placeholder', 'summary' => '' ) ) );
		$html = cr_rps_card( 17 );
		cr_rp_contains( '<a href="https://example.test/repost/17/">A Title With No Link</a>', $html, 'the plain title becomes the one link' );
		cr_rp_lacks( 'cr-repost-clip__host', $html, 'no host line without a host' );
		cr_rp_lacks( 'u-repost-of" value', $html, 'no source URL to move' );
		cr_rp_same( 1, cr_rp_visible_links( $html ), 'one visible anchor' );
	}
);

cr_rp_group(
	'the heading level follows the block',
	static function (): void {
		cr_rp_post( 18, '', cr_rp_facts() );
		$html = cr_rps_card( 18, 3 );
		cr_rp_contains( '<h3 class="pk-title p-name">', $html, 'level 3' );
		cr_rp_lacks( '<h2', $html, 'no h2 left' );
	}
);

cr_rp_group(
	'the clipping leaves other surfaces, styles, kinds and posts alone',
	static function (): void {
		cr_rp_post( 21, '', cr_rp_facts() );
		cr_rp_post( 22, '', cr_rp_facts(), false );
		cr_rp_post( 23, '', cr_rp_facts( array( 'url' => '', 'cited_title' => '' ) ) );
		cr_rp_post( 24, 'Locked Clipping', cr_rp_facts() );
		$GLOBALS['cr_rp']['protected'][24] = true;
		$plugin                            = '<article class="pk-card plugin">Plugin card</article>';

		$GLOBALS['cr_rp']['stream'] = false;
		cr_rp_same( $plugin, StreamRepost\clipping_card( $plugin, cr_rps_block(), cr_rp_instance( 21 ) ), 'off the Stream the card is the plugin\'s' );
		$GLOBALS['cr_rp']['stream'] = true;
		cr_rp_same( $plugin, StreamRepost\clipping_card( $plugin, cr_rps_block( 3, 'is-style-cr-repost-clipping' ), cr_rp_instance( 21 ) ), 'the archive style keeps the archive clipping' );
		cr_rp_same( $plugin, StreamRepost\clipping_card( $plugin, cr_rps_block(), cr_rp_instance( 22 ) ), 'a non-repost is untouched' );
		cr_rp_same( $plugin, StreamRepost\clipping_card( $plugin, cr_rps_block(), cr_rp_instance( 23 ) ), 'a repost with neither URL nor title keeps the plugin card' );
		cr_rp_same( $plugin, StreamRepost\clipping_card( $plugin, cr_rps_block(), cr_rp_instance( 24 ) ), 'a protected repost keeps the plugin\'s protected card' );
		cr_rp_same( $plugin, StreamRepost\clipping_card( $plugin, cr_rps_block(), cr_rp_instance( 99 ) ), 'a missing post is untouched' );
		cr_rp_same( '', StreamRepost\clipping_card( '', cr_rps_block(), cr_rp_instance( 21 ) ), 'an empty card stays empty' );
	}
);

cr_rp_group(
	'the tilt is stable for a post and spread across posts',
	static function (): void {
		cr_rp_post( 31, '', cr_rp_facts() );
		$one = cr_rps_card( 31 );
		$two = cr_rps_card( 31 );
		preg_match( '/cr-repost-clip--tilt-(\d)/', $one, $a );
		preg_match( '/cr-repost-clip--tilt-(\d)/', $two, $b );
		cr_rp_same( $a[1] ?? '', $b[1] ?? 'x', 'the same post tilts the same way every render' );

		$seen = array();
		foreach ( range( 100, 140 ) as $id ) {
			cr_rp_post( $id, '', cr_rp_facts() );
			preg_match( '/cr-repost-clip--tilt-(\d)/', cr_rps_card( $id ), $m );
			$seen[ $m[1] ?? 'none' ] = true;
		}
		cr_rp_assert( count( $seen ) >= 3 && ! isset( $seen['none'] ), 'forty posts use at least three of the four tilts' );
	}
);

cr_rp_finish();
