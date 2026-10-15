<?php
/**
 * `php tests/php/acquisition-single-test.php`: checks the single acquisition receipt.
 *
 * Covers PKIW issue 239 single lines: one H1 inside the receipt, rows in order
 * with stored values only, the cost row from the reader alone, the From link,
 * notes, the taped photo, later cards left as the plugin printed them, and the
 * rule that the adapter reads facts and never block attributes or post meta.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/acquisition-bootstrap.php';

use function Courtneyr\Child\SingleAcquisition\body_class;
use function Courtneyr\Child\SingleAcquisition\holds_h1;
use function Courtneyr\Child\SingleAcquisition\is_receipt;
use function Courtneyr\Child\SingleAcquisition\receipt;

if ( ! empty( $GLOBALS['cr_acq_no_plugin'] ) ) {
	exit( 0 );
}

/** The article the plugin's card prints, as get_block_wrapper_attributes() wraps it. */
function cr_acq_card( string $title = 'Field Recorder', string $extra_class = '' ): string {
	return '<article class="wp-block-post-kinds-indieweb-acquisition-card pk-card k-acquisition h-cite u-acquisition-of' . $extra_class . '"><div class="pk-badge"><svg></svg></div><div class="pk-body"><span class="pk-kindlabel">Purchase</span><div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://shop.example.com/field-recorder">' . $title . '</a></h2><p class="pk-sub"><span class="p-location">from Riverside Audio</span></p></div><div class="pk-media"><img class="pk-thumb u-photo" src="https://example.test/uploads/old.jpg" alt=""></div><p class="pk-note p-content">Records the morning birds.</p><div class="pk-meta"><time class="dt-published" datetime="2026-08-01">August 1, 2026</time></div></div></article>';
}

/** Post content with the first card, a paragraph and a second card. */
function cr_acq_content(): string {
	return '<p class="intro">Intro</p>' . "\n" . cr_acq_card() . "\n" . '<p>Between</p>' . "\n" . cr_acq_card( 'Spare Bell' );
}

/** Render the post-content block the way the template does. */
function cr_acq_render_single( string $content = '' ): string {
	return receipt( '' === $content ? cr_acq_content() : $content, array( 'blockName' => 'core/post-content' ) );
}

/** The h1 elements in a fragment. */
function cr_acq_h1s( string $html ): array {
	preg_match_all( '#<h1\b[^>]*>(.*?)</h1>#s', $html, $found );
	return $found[1];
}

/** Set up a single request for post 42. */
function cr_acq_single_request( array $facts_overrides = array() ): void {
	cr_acq_reset();
	$GLOBALS['cr_acq_request']['single'] = true;
	$GLOBALS['cr_acq_facts'][42]         = cr_acq_facts( $facts_overrides );
}

cr_acq_group(
	'the first card becomes the receipt with the one h1 and a hidden p-name',
	static function (): void {
		cr_acq_single_request();
		$html = cr_acq_render_single();

		cr_acq_same( array( 'Field Recorder' ), cr_acq_h1s( $html ), 'exactly one h1, the name' );
		cr_acq_contains( 'class="wp-block-post-kinds-indieweb-acquisition-card k-acquisition h-cite u-acquisition-of cr-acq-single"', $html, 'the article keeps its h-cite root and drops pk-card' );
		cr_acq_same( 1, cr_acq_count( '/<h2 class="pk-title/', $html ), 'no card title h2 for the first card, only the later card keeps its own' );
		cr_acq_contains( '<data class="p-name" value="Field Recorder" hidden></data>', $html, 'hidden p-name data' );
		cr_acq_not_contains( 'Visit store', $html, 'no Visit store link' );
		cr_acq_not_contains( 'pk-badge', substr( $html, 0, (int) strpos( $html, '<p>Between</p>' ) ), 'badge dropped from the receipt' );
		cr_acq_contains( '<p class="intro">Intro</p>', $html, 'content before the card kept' );
		cr_acq_contains( '<p>Between</p>', $html, 'content between the cards kept' );
		cr_acq_not_contains( 'Records the morning birds.</p></div></div></article>', substr( $html, 0, (int) strpos( $html, '<p>Between</p>' ) ), 'the old card body is gone' );
	}
);

cr_acq_group(
	'rows print in order with stored values only, and the From link opens safely',
	static function (): void {
		cr_acq_single_request( array( 'cost' => '$129.00' ) );
		$html = cr_acq_render_single();
		preg_match_all( '#<dt>(.*?)</dt>#', $html, $labels );
		cr_acq_same( array( 'Acquisition', 'From', 'Acquired', 'Cost' ), $labels[1], 'dt order' );
		cr_acq_contains( '<dd>Purchase</dd>', $html, 'type label' );
		cr_acq_contains( '<a class="u-url cr-acq-single__link" href="https://shop.example.com/field-recorder" target="_blank" rel="noopener noreferrer" aria-label="Riverside Audio (opens in a new tab)">', $html, 'From link' );
		cr_acq_contains( '<span class="p-location">Riverside Audio</span>', $html, 'p-location text' );
		cr_acq_same( 1, cr_acq_count( '/<svg\b[^>]*aria-hidden="true"/', substr( $html, 0, (int) strpos( $html, '<p>Between</p>' ) ) ), 'one aria-hidden icon' );
		cr_acq_contains( '<time class="dt-published" datetime="2026-08-01">August 1, 2026</time>', $html, 'acquired day from the reader' );
		cr_acq_contains( '<dd>$129.00</dd>', $html, 'cost row from the reader' );

		cr_acq_single_request();
		$private = cr_acq_render_single();
		cr_acq_not_contains( '<dt>Cost</dt>', $private, 'no cost row when the reader returns no cost' );
		cr_acq_not_contains( '$', substr( $private, 0, (int) strpos( $private, '<p>Between</p>' ) ), 'no dollar sign anywhere in the receipt' );

		cr_acq_single_request( array( 'where_url' => '', 'acquired_at' => '', 'acquired_display' => '', 'type_label' => 'Gift' ) );
		$plain = cr_acq_render_single();
		cr_acq_contains( '<dd><span class="p-location">Riverside Audio</span></dd>', $plain, 'plain-text From with no URL' );
		cr_acq_not_contains( '<dt>Acquired</dt>', $plain, 'no Acquired row when none is stored' );
		cr_acq_contains( '<dd>Gift</dd>', $plain, 'gift type' );

		cr_acq_single_request( array( 'where' => '', 'where_url' => 'https://shop.example.com/x', 'type_label' => 'Inherited' ) );
		$host = cr_acq_render_single();
		cr_acq_contains( 'aria-label="shop.example.com (opens in a new tab)"', $host, 'a URL with no place text names its host' );
		cr_acq_not_contains( 'p-location', substr( $host, 0, (int) strpos( $host, '<p>Between</p>' ) ), 'and prints no p-location' );
		cr_acq_contains( '<dd>Inherited</dd>', $host, 'inherited label' );

		cr_acq_single_request( array( 'type_label' => '', 'where' => '', 'where_url' => '', 'acquired_at' => '', 'notes' => '' ) );
		$bare = cr_acq_render_single();
		cr_acq_not_contains( '<dl', substr( $bare, 0, (int) strpos( $bare, '<p>Between</p>' ) ), 'no dl when nothing is stored' );
	}
);

cr_acq_group(
	'notes print under an upright h2 and only when stored',
	static function (): void {
		cr_acq_single_request( array( 'notes' => "Records the morning birds.\nBattery lasts a week." ) );
		$html = cr_acq_render_single();
		cr_acq_contains( '<div class="cr-acq-single__notes"><h2 class="cr-acq-single__notes-title">Notes</h2><p class="p-content">Records the morning birds.<br />' . "\n" . 'Battery lasts a week.</p></div>', $html, 'notes sheet' );
		cr_acq_single_request( array( 'notes' => '' ) );
		cr_acq_not_contains( 'cr-acq-single__notes', cr_acq_render_single(), 'no notes element when none is stored' );
	}
);

cr_acq_group(
	'the photo is a taped figure with the reader alt, size and no filter, and never remote',
	static function (): void {
		cr_acq_single_request();
		$html = cr_acq_render_single();
		cr_acq_contains( '<figure class="cr-acq-single__photo" data-cr-cover-fallback><span class="cr-acq-single__tape" aria-hidden="true"></span><img', $html, 'figure with an aria-hidden tape' );
		cr_acq_contains( 'class="cr-acq-single__img u-photo skip-lazy"', $html, 'u-photo image' );
		cr_acq_contains( 'alt="A handheld recorder with two microphones"', $html, 'reader alt' );
		cr_acq_contains( 'width="800" height="600"', $html, 'width and height' );
		cr_acq_not_contains( 'filter', substr( $html, 0, (int) strpos( $html, '<p>Between</p>' ) ), 'no filter' );

		$picture = cr_acq_facts()['picture'];
		cr_acq_single_request( array( 'picture' => array_merge( $picture, array( 'attachment_id' => 0, 'remote' => true, 'url' => 'https://images.example.com/x.jpg' ) ) ) );
		$remote = cr_acq_render_single();
		cr_acq_not_contains( 'cr-acq-single__photo', $remote, 'a remote picture prints no figure' );
		cr_acq_not_contains( 'images.example.com', $remote, 'and never names the host' );
		cr_acq_single_request( array( 'picture' => array() ) );
		cr_acq_not_contains( 'cr-acq-single__photo', cr_acq_render_single(), 'no picture, no frame' );
	}
);

cr_acq_group(
	'a card with no post title reads its name from the reader',
	static function (): void {
		cr_acq_single_request( array( 'name' => 'Watering Can', 'name_is_title' => false, 'title' => 'Watering Can' ) );
		$GLOBALS['cr_acq_posts'][42]->post_title = '';
		cr_acq_same( array( 'Watering Can' ), cr_acq_h1s( cr_acq_render_single() ), 'h1 from facts name when post_title is empty' );

		cr_acq_single_request( array( 'name' => 'Acquisition, July 10, 2026', 'title' => '', 'name_is_title' => false ) );
		$synthetic = cr_acq_render_single();
		cr_acq_same( array( 'Acquisition, July 10, 2026' ), cr_acq_h1s( $synthetic ), 'a synthetic name still fills the h1' );
		cr_acq_not_contains( '<data class="p-name"', $synthetic, 'and carries no p-name' );
	}
);

cr_acq_group(
	'later cards stay as the plugin printed them',
	static function (): void {
		cr_acq_single_request();
		$html  = cr_acq_render_single();
		$later = cr_acq_card( 'Spare Bell' );
		cr_acq_contains( $later, $html, 'the second card is byte for byte the plugin card' );
		cr_acq_same( 1, cr_acq_count( '/pk-card k-acquisition/', $html ), 'only one card keeps pk-card' );
	}
);

cr_acq_group(
	'the receipt leaves other blocks, protected posts and facts-less posts alone',
	static function (): void {
		cr_acq_single_request();
		$content = cr_acq_content();
		cr_acq_same( $content, receipt( $content, array( 'blockName' => 'core/paragraph' ) ), 'another block' );

		$GLOBALS['cr_acq_request']['protected'][42] = true;
		cr_acq_same( $content, cr_acq_render_single( $content ), 'a protected post' );
		cr_acq_same( false, is_receipt(), 'protected is no receipt' );

		cr_acq_single_request();
		$GLOBALS['cr_acq_facts'][42] = array();
		cr_acq_same( $content, cr_acq_render_single( $content ), 'facts []' );

		cr_acq_single_request( array( 'has_card' => false ) );
		cr_acq_same( $content, cr_acq_render_single( $content ), 'a post with no card (a Quick Post) keeps the template header and its content' );
		cr_acq_same( false, is_receipt(), 'no card is no receipt' );

		cr_acq_single_request();
		$no_card = '<p>No card here</p>';
		cr_acq_same( $no_card, cr_acq_render_single( $no_card ), 'content with no card article' );

		cr_acq_single_request();
		$GLOBALS['cr_acq_request']['single'] = false;
		cr_acq_same( $content, cr_acq_render_single( $content ), 'not a single' );
	}
);

cr_acq_group(
	'a receipt owns the page h1 and the body class',
	static function (): void {
		cr_acq_single_request();
		$post = $GLOBALS['cr_acq_posts'][42];
		cr_acq_same( true, holds_h1( false, $post ), 'a card post holds the h1' );
		cr_acq_same( true, holds_h1( true, $post ), 'a lane that already holds it keeps it' );
		cr_acq_same( true, is_receipt(), 'is_receipt' );
		cr_acq_same( array( 'a', 'cr-acq-single-page' ), body_class( array( 'a' ) ), 'body class' );
		cr_acq_same( array( 'cr-acq-single-page' ), body_class( array( 'cr-acq-single-page' ) ), 'body class once' );
		cr_acq_assert( cr_acq_has_hook( 'courtneyr_child_single_object_holds_h1', 'Courtneyr\\Child\\SingleAcquisition\\holds_h1', 10, 2 ), 'holds_h1 hooked' );
		cr_acq_assert( cr_acq_has_hook( 'render_block', 'Courtneyr\\Child\\SingleAcquisition\\receipt', 20, 2 ), 'receipt on render_block at 20' );

		cr_acq_single_request( array( 'has_card' => false ) );
		cr_acq_same( false, holds_h1( false, $GLOBALS['cr_acq_posts'][42] ), 'no card, the template header keeps the h1' );
		cr_acq_same( array( 'a' ), body_class( array( 'a' ) ), 'no class without a receipt' );

		cr_acq_single_request();
		$GLOBALS['cr_acq_request']['kinds'][42] = array( 'bookmark' );
		cr_acq_same( false, holds_h1( false, $GLOBALS['cr_acq_posts'][42] ), 'another kind never holds it' );
	}
);

cr_acq_group(
	'the adapters read facts only: no block attributes, post meta or plugin readers, and no color literals',
	static function (): void {
		foreach ( array( 'acquisition.php', 'single-acquisition.php', 'stream-acquisition.php' ) as $file ) {
			$source = (string) file_get_contents( CR_ACQ_THEME_DIR . '/inc/' . $file );
			foreach ( array( 'get_post_meta', 'parse_blocks', 'find_block', '$attributes', 'untitled_name', 'kind_picture', 'lead_image', 'featured_in_content', 'acquisition_facts', '_pkiw_' ) as $banned ) {
				cr_acq_not_contains( $banned, $source, $file . ' uses ' . $banned );
			}
			cr_acq_same( 0, cr_acq_count( '/#[0-9a-fA-F]{3,8}\b/', $source ), $file . ' has no color-like literal' );
		}
		$css = (string) file_get_contents( CR_ACQ_THEME_DIR . '/assets/css/cr-acquisition.css' );
		cr_acq_same( 0, cr_acq_count( '/#[0-9a-fA-F]{3,8}\b/', $css ), 'the sheet has no color literal' );
	}
);

cr_acq_finish();
