<?php
/**
 * `php tests/php/acquisition-stream-test.php`: checks the Stream acquisition stub.
 *
 * Covers PKIW issue 239 Stream lines: content order, exactly one visible link,
 * no cost, notes or place URL, one date whose time carries no microformats
 * class, and the states where the plugin's card stays.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/acquisition-bootstrap.php';

use function Courtneyr\Child\StreamAcquisition\stub;

if ( ! empty( $GLOBALS['cr_acq_no_plugin'] ) ) {
	exit( 0 );
}

/** Render the stub for a post ID on a Stream surface. */
function cr_acq_render_stub( int $post_id, array $attrs = array() ): string {
	$GLOBALS['cr_acq_request']['the_id'] = $post_id;
	return stub( '<article class="pk-card k-acquisition">plugin card</article>', array( 'blockName' => 'post-kinds-indieweb/stream-card', 'attrs' => $attrs ), new WP_Block( array( 'postId' => $post_id ) ) );
}

/** Set up a Stream request for post 42. */
function cr_acq_stream_request( array $facts_overrides = array() ): void {
	cr_acq_reset();
	$GLOBALS['cr_acq_request']['stream'] = true;
	$GLOBALS['cr_acq_facts'][42]         = cr_acq_facts( $facts_overrides );
}

cr_acq_group(
	'the stub reads thumb, title, type label, place, then one date',
	static function (): void {
		cr_acq_stream_request( array( 'cost' => '$129.00' ) );
		$html = cr_acq_render_stub( 42 );

		cr_acq_contains( '<article class="cr-acq-stub cr-ticket">', $html, 'article root' );
		$order = array( '<figure class="cr-acq-stub__thumb" data-cr-cover-fallback>', '<h2 class="cr-acq-stub__title">', '<span class="pk-kindlabel">Purchase</span>', '<p class="cr-acq-stub__where">Riverside Audio</p>', '<time datetime="2026-08-01">August 1, 2026</time>' );
		$last  = -1;
		foreach ( $order as $needle ) {
			$at = strpos( $html, $needle );
			cr_acq_assert( false !== $at && $at > $last, 'stub order: ' . $needle );
			$last = false === $at ? $last : $at;
		}
		cr_acq_contains( 'alt=""', $html, 'thumb alt empty' );
		cr_acq_contains( 'loading="lazy"', $html, 'thumb lazy' );
		cr_acq_contains( 'width="800" height="600"', $html, 'thumb size' );
		cr_acq_not_contains( 'fetchpriority', $html, 'thumb is never prioritized' );
		cr_acq_same( 1, cr_acq_count( '/<a\b/', $html ), 'exactly one anchor' );
		cr_acq_contains( '<a href="https://example.test/acquisition/42/">Field Recorder</a>', $html, 'the title link goes to the single' );
		foreach ( array( '$129.00', 'Records the morning birds', 'shop.example.com', 'target="_blank"' ) as $leak ) {
			cr_acq_not_contains( $leak, $html, 'stub leaks ' . $leak );
		}
	}
);

cr_acq_group(
	'the stub is no mf2 root and its time carries no mf2 class, so the post date stays the entry date',
	static function (): void {
		cr_acq_stream_request();
		$html    = cr_acq_render_stub( 42 );
		$visible = (string) preg_replace( '/<span class="pk-kind-cite[^>]*hidden>.*?<\/span>/s', '', $html );
		foreach ( array( 'h-entry', 'h-cite', 'pk-card', 'k-acquisition', 'dt-published', 'u-url', 'p-name', 'p-location', 'u-photo', 'p-author' ) as $mf2 ) {
			cr_acq_not_contains( $mf2, $visible, 'visible stub carries ' . $mf2 );
		}
		cr_acq_contains( '<span class="pk-kind-cite h-cite u-acquisition-of" hidden><data class="p-name" value="Field Recorder"></data></span>', $html, 'hidden cite from the plugin helper, name only' );
		cr_acq_not_contains( 'u-url', $html, 'the hidden cite carries no URL either' );

		cr_acq_stream_request( array( 'acquired_at' => '', 'acquired_display' => '' ) );
		cr_acq_contains( '<time datetime="2026-08-28">August 28, 2026</time>', cr_acq_render_stub( 42 ), 'the post date when the card stores no acquired day' );
		cr_acq_same( 1, cr_acq_count( '/<time\b/', cr_acq_render_stub( 42 ) ), 'one time element' );
	}
);

cr_acq_group(
	'states without a type, place or picture print a smaller stub with no placeholders',
	static function (): void {
		cr_acq_stream_request( array( 'type_label' => '', 'where' => '', 'picture' => array() ) );
		$html = cr_acq_render_stub( 42 );
		cr_acq_not_contains( 'pk-kindlabel', $html, 'no empty label' );
		cr_acq_not_contains( 'cr-acq-stub__where', $html, 'no empty place' );
		cr_acq_not_contains( '<figure', $html, 'no empty frame' );
		cr_acq_contains( 'cr-acq-stub__title', $html, 'title stays' );

		$picture = cr_acq_facts()['picture'];
		cr_acq_stream_request( array( 'picture' => array_merge( $picture, array( 'attachment_id' => 0, 'remote' => true, 'url' => 'https://images.example.com/x.jpg' ) ) ) );
		$remote = cr_acq_render_stub( 42 );
		cr_acq_not_contains( '<img', $remote, 'a remote picture prints no thumb' );
		cr_acq_not_contains( 'images.example.com', $remote, 'and never names the host' );

		cr_acq_stream_request();
		cr_acq_contains( '<h4 class="cr-acq-stub__title">', cr_acq_render_stub( 42, array( 'headingLevel' => 9 ) ), 'heading level clamps to 4' );
		cr_acq_contains( '<h2 class="cr-acq-stub__title">', cr_acq_render_stub( 42, array( 'headingLevel' => 0 ) ), 'heading level clamps to 2' );
		cr_acq_contains( '<h2 class="cr-acq-stub__title">', cr_acq_render_stub( 42 ), 'default level 2' );
	}
);

cr_acq_group(
	'the plugin card stays off Stream surfaces, on receipts, on protected posts and without facts',
	static function (): void {
		$card = '<article class="pk-card k-acquisition">plugin card</article>';
		cr_acq_stream_request();
		$GLOBALS['cr_acq_request']['stream'] = false;
		cr_acq_same( $card, cr_acq_render_stub( 42 ), 'not a Stream surface' );

		cr_acq_stream_request();
		cr_acq_same( $card, cr_acq_render_stub( 42, array( 'className' => 'is-style-cr-receipt' ) ), 'a receipt in the Site Editor preview keeps its receipt' );
		cr_acq_same( '', stub( '', array( 'attrs' => array() ), new WP_Block( array( 'postId' => 42 ) ) ), 'empty html' );

		cr_acq_stream_request();
		$GLOBALS['cr_acq_request']['protected'][42] = true;
		cr_acq_same( $card, cr_acq_render_stub( 42 ), 'protected posts keep the plugin protected card' );

		cr_acq_stream_request();
		$GLOBALS['cr_acq_facts'][42] = array();
		cr_acq_same( $card, cr_acq_render_stub( 42 ), 'facts []' );

		cr_acq_stream_request();
		$GLOBALS['cr_acq_request']['kinds'][42] = array( 'bookmark' );
		cr_acq_same( $card, cr_acq_render_stub( 42 ), 'another kind' );
	}
);

cr_acq_group(
	'the stub hook is registered on the stream-card block at 10',
	static function (): void {
		cr_acq_assert( cr_acq_has_hook( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamAcquisition\\stub', 10, 3 ), 'stub hooked' );
	}
);

cr_acq_finish();
