<?php
/**
 * `php tests/php/acquisition-archive-test.php`: checks the acquisition archive receipts.
 *
 * Covers PKIW issue 239 archive lines: one visible link per receipt, no card
 * details in the markup, first-row image priority, repeated titles named with
 * the post date, protected and remote-picture states, and the enqueue gates.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/acquisition-bootstrap.php';

use function Courtneyr\Child\Acquisition\receipt;

/** The block the Query Loop renders for a receipt. */
function cr_acq_receipt_block( array $extra = array() ): array {
	return array(
		'blockName' => 'post-kinds-indieweb/stream-card',
		'attrs'     => array_merge(
			array(
				'headingLevel' => 3,
				'className'    => 'is-style-cr-receipt',
			),
			$extra
		),
	);
}

/** Render one receipt for a post ID as the Query Loop would. */
function cr_acq_render_receipt( int $post_id, array $block_extra = array() ): string {
	$GLOBALS['cr_acq_request']['the_id'] = $post_id;
	return receipt( '<article class="pk-card k-acquisition">plugin card</article>', cr_acq_receipt_block( $block_extra ), new WP_Block( array( 'postId' => $post_id ) ) );
}

/** Visible, tabbable anchors in a fragment. */
function cr_acq_visible_links( string $html ): int {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$xpath = new DOMXPath( $doc );
	return (int) $xpath->query( '//a[@href][not(ancestor-or-self::*[@hidden])]' )->length;
}

if ( ! empty( $GLOBALS['cr_acq_no_plugin'] ) ) {
	cr_acq_group(
		'without the plugin reader every adapter keeps the plugin card',
		static function (): void {
			cr_acq_reset();
			cr_acq_same( array(), \Courtneyr\Child\Acquisition\facts( 42 ), 'facts empty without kind_facts' );
			$card = '<article class="pk-card k-acquisition">plugin card</article>';
			cr_acq_same( $card, cr_acq_render_receipt( 42 ), 'receipt keeps the card' );
			$GLOBALS['cr_acq_request']['stream'] = true;
			cr_acq_same( $card, \Courtneyr\Child\StreamAcquisition\stub( $card, array( 'attrs' => array() ), new WP_Block( array( 'postId' => 42 ) ) ), 'stub keeps the card' );
			cr_acq_same( false, \Courtneyr\Child\SingleAcquisition\is_receipt(), 'no receipt' );
		}
	);
	cr_acq_finish();
}

cr_acq_group(
	'a receipt prints the title link and the stored photo, and nothing from the card',
	static function (): void {
		cr_acq_reset();
		$GLOBALS['cr_acq_facts'][42] = cr_acq_facts( array( 'cost' => '$129.00' ) );
		$html                        = cr_acq_render_receipt( 42 );

		cr_acq_contains( '<article class="cr-receipt cr-ticket cr-ticket--zigzag">', $html, 'article root' );
		cr_acq_contains( '<h3 class="cr-receipt__title"><a href="https://example.test/acquisition/42/">Field Recorder</a></h3>', $html, 'h3 title link' );
		cr_acq_same( 1, cr_acq_visible_links( $html ), 'one visible link' );
		cr_acq_not_contains( 'tabindex', $html, 'no tabindex' );
		foreach ( array( 'Purchase', 'Riverside Audio', 'shop.example.com', '$129.00', 'Records the morning birds', 'August 1, 2026', 'August 28, 2026', 'h-entry' ) as $leak ) {
			cr_acq_not_contains( $leak, $html, 'receipt leaks ' . $leak );
		}
		$visible = (string) preg_replace( '/<span class="pk-kind-cite[^>]*hidden>.*?<\/span>/s', '', $html );
		foreach ( array( 'u-url', 'p-name', 'dt-published', 'u-photo', 'p-location', 'e-content' ) as $mf2 ) {
			cr_acq_not_contains( $mf2, $visible, 'visible receipt carries ' . $mf2 );
		}
		cr_acq_contains( '<span class="pk-kind-cite h-cite u-acquisition-of" hidden><data class="p-name" value="Field Recorder"></data></span>', $html, 'hidden cite from the plugin helper' );
		cr_acq_contains( '<figure class="cr-receipt__photo" data-cr-cover-fallback>', $html, 'figure with the cover-fallback hook' );
		cr_acq_contains( 'alt=""', $html, 'empty alt, the title names the link' );
		cr_acq_contains( 'width="800" height="600"', $html, 'width and height' );
		cr_acq_not_contains( 'filter', $html, 'no filter on the image' );
	}
);

cr_acq_group(
	'the first two receipts of the first three sections load eagerly, the rest lazily',
	static function (): void {
		cr_acq_reset();
		$loads = array();
		foreach ( array( 'month-a', 'month-a', 'month-a', 'month-b', 'month-b', 'month-c', 'month-c', 'month-d', 'month-d' ) as $index => $section ) {
			$id = 100 + $index;
			cr_acq_add_post( $id, 'Item ' . $index, array( 'picture' => cr_acq_facts()['picture'] ) );
			$GLOBALS['cr_acq_group'] = $section;
			$loads[]                 = cr_acq_render_receipt( $id );
		}
		$expect = array( true, true, false, true, true, true, true, false, false );
		foreach ( $expect as $index => $eager ) {
			if ( $eager ) {
				cr_acq_contains( 'loading="eager"', $loads[ $index ], 'receipt ' . $index . ' eager' );
				cr_acq_contains( 'fetchpriority="high"', $loads[ $index ], 'receipt ' . $index . ' fetchpriority' );
				cr_acq_contains( 'skip-lazy', $loads[ $index ], 'receipt ' . $index . ' skip-lazy class' );
				cr_acq_contains( 'data-skip-lazy="1"', $loads[ $index ], 'receipt ' . $index . ' data-skip-lazy' );
				cr_acq_not_contains( 'sizes="auto', $loads[ $index ], 'receipt ' . $index . ' sizes without auto' );
			} else {
				cr_acq_contains( 'loading="lazy"', $loads[ $index ], 'receipt ' . $index . ' lazy' );
				cr_acq_not_contains( 'fetchpriority', $loads[ $index ], 'receipt ' . $index . ' no fetchpriority' );
			}
		}

		cr_acq_reset();
		$GLOBALS['cr_acq_request']['paged'] = 2;
		$GLOBALS['cr_acq_group']            = 'month-a';
		cr_acq_contains( 'loading="lazy"', cr_acq_render_receipt( 42 ), 'page 2 has no first row' );

		cr_acq_reset();
		$GLOBALS['cr_acq_group'] = '';
		cr_acq_contains( 'loading="eager"', cr_acq_render_receipt( 42 ), 'items outside sections still count one group' );
	}
);

cr_acq_group(
	'repeated titles on a page carry the post date in their accessible name',
	static function (): void {
		cr_acq_reset();
		$first  = cr_acq_add_post( 201, 'Field Notebook', array() );
		$second = cr_acq_add_post( 202, 'Field Notebook', array() );
		$other  = cr_acq_add_post( 203, 'Locked Box', array() );
		$first->date_display  = 'May 28, 2026';
		$first->date_iso      = '2026-05-28';
		$second->date_display = 'May 20, 2026';
		$second->date_iso     = '2026-05-20';
		$GLOBALS['wp_query']  = (object) array( 'posts' => array( $first, $second, $other ) );

		$one = cr_acq_render_receipt( 201 );
		cr_acq_contains( 'aria-labelledby="cr-receipt-t-201 cr-receipt-d-201"', $one, 'first link names title then date with one space' );
		cr_acq_contains( '<span id="cr-receipt-t-201">Field Notebook</span>', $one, 'title id' );
		cr_acq_contains( '<time id="cr-receipt-d-201" class="screen-reader-text" datetime="2026-05-28">May 28, 2026</time>', $one, 'time has id, text and no mf2 class' );
		cr_acq_contains( 'aria-labelledby="cr-receipt-t-202 cr-receipt-d-202"', cr_acq_render_receipt( 202 ), 'second link' );
		cr_acq_contains( 'May 20, 2026</time>', cr_acq_render_receipt( 202 ), 'second date' );
		$unique = cr_acq_render_receipt( 203 );
		cr_acq_not_contains( 'aria-labelledby', $unique, 'a unique title keeps its plain name' );
		cr_acq_not_contains( '<time', $unique, 'a unique title prints no time' );
		cr_acq_same( 1, cr_acq_visible_links( $one ), 'still one visible link' );
	}
);

cr_acq_group(
	'a protected post prints a text-only receipt with its title and no card data',
	static function (): void {
		cr_acq_reset();
		cr_acq_add_post( 301, 'Locked Box' );
		$GLOBALS['cr_acq_request']['protected'][301] = true;
		$GLOBALS['cr_acq_facts'][301]                = array();
		$html = cr_acq_render_receipt( 301 );
		cr_acq_contains( 'cr-receipt cr-receipt--text', $html, 'text-only class' );
		cr_acq_contains( '<a href="https://example.test/acquisition/301/">Protected: Locked Box</a>', $html, 'title from get_the_title' );
		cr_acq_not_contains( '<figure', $html, 'no figure' );
		cr_acq_not_contains( 'pk-kind-cite', $html, 'no cite' );
		cr_acq_same( 1, cr_acq_visible_links( $html ), 'one visible link' );
	}
);

cr_acq_group(
	'a remote picture or a missing attachment leaves a text-only receipt or a sized fallback',
	static function (): void {
		cr_acq_reset();
		$picture                     = cr_acq_facts()['picture'];
		$GLOBALS['cr_acq_facts'][42] = cr_acq_facts( array( 'picture' => array_merge( $picture, array( 'attachment_id' => 0, 'url' => 'https://images.example.com/missing-lantern.jpg', 'remote' => true ) ) ) );
		$remote                      = cr_acq_render_receipt( 42 );
		cr_acq_contains( 'cr-receipt--text', $remote, 'remote picture: text-only class' );
		cr_acq_not_contains( '<img', $remote, 'remote picture: no img' );
		cr_acq_not_contains( '<figure', $remote, 'remote picture: no empty frame' );
		cr_acq_not_contains( 'images.example.com', $remote, 'remote picture: host never printed' );

		$GLOBALS['cr_acq_facts'][42] = cr_acq_facts( array( 'picture' => array_merge( $picture, array( 'attachment_id' => 0, 'url' => 'https://example.test/uploads/loose.jpg', 'remote' => false ) ) ) );
		$loose                       = cr_acq_render_receipt( 42 );
		cr_acq_contains( '<img src="https://example.test/uploads/loose.jpg"', $loose, 'a local url with no attachment id prints an img' );
		cr_acq_contains( 'width="800" height="600"', $loose, 'with the reader dimensions' );

		$GLOBALS['cr_acq_facts'][42]  = cr_acq_facts();
		$GLOBALS['cr_acq_attachments'][7] = '';
		cr_acq_contains( 'src="https://example.test/uploads/attachment-7.jpg"', cr_acq_render_receipt( 42 ), 'a gone attachment falls back to the stored url' );

		$GLOBALS['cr_acq_facts'][42] = cr_acq_facts( array( 'picture' => array() ) );
		cr_acq_contains( 'cr-receipt--text', cr_acq_render_receipt( 42 ), 'no picture at all' );
	}
);

cr_acq_group(
	'names come from the reader and escape once',
	static function (): void {
		cr_acq_reset();
		$GLOBALS['cr_acq_facts'][42] = cr_acq_facts( array( 'name' => 'Tom &amp; Jerry <b>Mug</b>', 'title' => 'Tom & Jerry' ) );
		$html                        = cr_acq_render_receipt( 42 );
		cr_acq_contains( 'Tom &amp; Jerry Mug</a>', $html, 'entities decoded, tags stripped, escaped once' );
		cr_acq_not_contains( '&amp;amp;', $html, 'no double escape' );

		$GLOBALS['cr_acq_facts'][42] = cr_acq_facts( array( 'name' => '', 'title' => 'Card Title' ) );
		cr_acq_contains( '>Card Title</a>', cr_acq_render_receipt( 42 ), 'falls back to the card title when the plugin has no name key' );

		$GLOBALS['cr_acq_facts'][42] = cr_acq_facts( array( 'name' => 'Acquisition, July 10, 2026', 'title' => '', 'name_is_title' => false ) );
		$synthetic                   = cr_acq_render_receipt( 42 );
		cr_acq_contains( '>Acquisition, July 10, 2026</a>', $synthetic, 'a synthetic name prints as the title' );
		cr_acq_not_contains( 'pk-kind-cite', $synthetic, 'and carries no cite when the card has no title' );
	}
);

cr_acq_group(
	'receipts stay out of other blocks, kinds and facts-less posts',
	static function (): void {
		cr_acq_reset();
		$card = '<article class="pk-card k-acquisition">plugin card</article>';
		cr_acq_same( $card, receipt( $card, array( 'blockName' => 'post-kinds-indieweb/stream-card', 'attrs' => array( 'className' => 'is-style-cr-bookmark-card' ) ), new WP_Block( array( 'postId' => 42 ) ) ), 'another block style' );
		cr_acq_same( $card, receipt( $card, array( 'blockName' => 'post-kinds-indieweb/stream-card', 'attrs' => array() ), new WP_Block( array( 'postId' => 42 ) ) ), 'no class' );
		cr_acq_same( '', receipt( '', cr_acq_receipt_block(), new WP_Block( array( 'postId' => 42 ) ) ), 'empty html' );
		$GLOBALS['cr_acq_request']['kinds'][42] = array( 'bookmark' );
		cr_acq_same( $card, cr_acq_render_receipt( 42 ), 'another kind' );
		$GLOBALS['cr_acq_request']['kinds'][42] = array( 'acquisition' );
		$GLOBALS['cr_acq_facts'][42]            = array();
		cr_acq_same( $card, cr_acq_render_receipt( 42 ), 'facts [] keeps the plugin card' );
		cr_acq_same( 3, \Courtneyr\Child\Acquisition\heading_level( array() ), 'default heading level' );
		cr_acq_same( 2, \Courtneyr\Child\Acquisition\heading_level( array( 'attrs' => array( 'headingLevel' => 1 ) ) ), 'level clamps up to 2' );
		cr_acq_same( 4, \Courtneyr\Child\Acquisition\heading_level( array( 'attrs' => array( 'headingLevel' => 6 ) ) ), 'level clamps down to 4' );
	}
);

cr_acq_group(
	'the stylesheet loads on the archive, a single, the Stream and the editor only',
	static function (): void {
		$expected = array( 'courtneyr-acquisition', COURTNEYR_CHILD_URI . '/assets/css/cr-acquisition.css', array( 'courtneyr-paper', 'courtneyr-ticket' ), COURTNEYR_CHILD_VERSION );
		foreach ( array(
			'archive' => array( 'kind_archive' => 'acquisition' ),
			'single'  => array( 'single' => true, 'kinds' => array( 42 => array( 'acquisition' ) ) ),
			'stream'  => array( 'stream' => true ),
			'admin'   => array( 'admin' => true ),
		) as $label => $request ) {
			cr_acq_reset();
			$GLOBALS['cr_acq_request'] = array_merge( $GLOBALS['cr_acq_request'], $request );
			cr_acq_fire_hook( 'enqueue_block_assets' );
			$mine = array_values( array_filter( $GLOBALS['cr_acq_enqueued'], static fn( array $style ): bool => 'courtneyr-acquisition' === $style[0] ) );
			cr_acq_same( array( $expected ), $mine, $label . ' enqueues the sheet' );
		}
		foreach ( array(
			'read archive'    => array( 'kind_archive' => 'read' ),
			'plain page'      => array(),
			'other kind page' => array( 'single' => true, 'kinds' => array( 42 => array( 'read' ) ) ),
		) as $label => $request ) {
			cr_acq_reset();
			$GLOBALS['cr_acq_request'] = array_merge( $GLOBALS['cr_acq_request'], $request );
			cr_acq_fire_hook( 'enqueue_block_assets' );
			$mine = array_values( array_filter( $GLOBALS['cr_acq_enqueued'], static fn( array $style ): bool => 'courtneyr-acquisition' === $style[0] ) );
			cr_acq_same( array(), $mine, $label . ' enqueues nothing' );
		}
	}
);

cr_acq_group(
	'hooks, the receipt block style, the preload filter and editor parity are registered',
	static function (): void {
		cr_acq_assert( cr_acq_has_hook( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\Acquisition\\receipt', 10, 3 ), 'receipt on the stream-card block at 10' );
		cr_acq_assert( cr_acq_has_hook( 'enqueue_block_assets', 'Courtneyr\\Child\\Acquisition\\enqueue_styles', 10, 1 ), 'enqueue hook' );
		cr_acq_assert( cr_acq_has_hook( 'init', 'Courtneyr\\Child\\Acquisition\\register_receipt_style', 10, 1 ), 'init hook' );

		$GLOBALS['cr_acq_styles'] = array();
		\Courtneyr\Child\Acquisition\register_receipt_style();
		cr_acq_same( 'post-kinds-indieweb/stream-card', $GLOBALS['cr_acq_styles'][0][0] ?? '', 'style target block' );
		cr_acq_same( 'cr-receipt', $GLOBALS['cr_acq_styles'][0][1]['name'] ?? '', 'style name' );

		cr_acq_reset();
		$GLOBALS['cr_acq_request']['kind_archive'] = 'acquisition';
		cr_acq_same( 0, cr_acq_fire_hook( 'perfmatters_preload_critical_images', 3 ), 'no Perfmatters preload on the archive' );
		cr_acq_reset();
		cr_acq_same( 3, cr_acq_fire_hook( 'perfmatters_preload_critical_images', 3 ), 'preload count passes through elsewhere' );

		$kinds = cr_acq_fire_hook( 'courtneyr_child_editor_parity_kinds', array( 'acquisition' => array( 'classes' => array( 'single-post', 'kind-acquisition' ) ), 'read' => array( 'classes' => array( 'single-post' ) ) ) );
		cr_acq_same( array( 'single-post', 'kind-acquisition', 'cr-acq-single-page' ), $kinds['acquisition']['classes'], 'acquisition canvas classes' );
		cr_acq_same( array( 'single-post' ), $kinds['read']['classes'], 'other kinds untouched' );
		$again = cr_acq_fire_hook( 'courtneyr_child_editor_parity_kinds', $kinds );
		cr_acq_same( $kinds['acquisition']['classes'], $again['acquisition']['classes'], 'the class is added once' );
		$fresh = cr_acq_fire_hook( 'courtneyr_child_editor_parity_kinds', array() );
		cr_acq_same( array( 'single-post', 'kind-acquisition', 'cr-acq-single-page' ), $fresh['acquisition']['classes'], 'an empty registry still gets the entry' );
	}
);

cr_acq_group(
	'a site without the plugin reader keeps the plugin card',
	static function (): void {
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-plugin';
		exec( $cmd, $output, $status );
		cr_acq_same( 0, $status, 'no-plugin subprocess exit' );
		cr_acq_contains( 'all passed', implode( "\n", $output ), 'no-plugin subprocess passed' );
	}
);

cr_acq_finish();
