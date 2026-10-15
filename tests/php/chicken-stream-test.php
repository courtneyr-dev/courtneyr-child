<?php
/**
 * `php tests/php/chicken-stream-test.php`: standalone checks for the Stream
 * egg (inc/stream-chicken.php). Also run with `--no-plugin`.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/chicken-bootstrap.php';

use Courtneyr\Child\StreamChicken;

/** The generic Stream card the plugin prints for a titled chicken post with a featured image. */
function cr_chk_generic_card( bool $picture_element = false, string $title = 'Crossing the path' ): string {
	$img   = '<img width="300" height="225" src="https://example.test/hen-300.jpg" class="u-photo" alt="A speckled hen" loading="lazy" />';
	$media = $picture_element ? '<picture><source type="image/avif" srcset="https://example.test/hen-300.avif">' . $img . '</picture>' : $img;
	return '<article class="pk-card pk-card--stream k-chicken h-entry">'
		. '<div class="pk-badge"><svg aria-hidden="true"></svg></div>'
		. '<div class="pk-body"><span class="pk-kindlabel">Chicken</span>'
		. '<div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/chicken/42/">' . $title . '</a></h2>'
		. '<p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-24T13:05:00+00:00">September 24, 2026</time></p></div>'
		. '<div class="pk-media pk-media--stream">' . $media . '<p class="pk-media-caption">Pepper on the path</p><div class="pk-thumbs"><button type="button">2</button></div><a class="pk-more" href="#">+3 more images</a></div>'
		. '<p class="pk-excerpt p-summary">The hen crossed the path again this morning.</p>'
		. '<div class="pk-meta"><a class="pk-link" href="https://example.test/chicken/42/">Read more<span class="pk-sr-only">: ' . $title . '</span></a></div>'
		. '</div></article>';
}

/** Prepare a Stream request for post 42. */
function cr_chk_stream_setup( array $facts_override = array() ): void {
	cr_chk_reset();
	$GLOBALS['cr_chk_request']['stream']  = true;
	$GLOBALS['cr_chk_facts'][42]          = array_merge( cr_chk_default_facts(), $facts_override );
}

/** Element tags (and first classes) directly under the egg's article. */
function cr_chk_egg_children( string $html ): array {
	$doc = new DOMDocument();
	@$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	$article = $doc->getElementsByTagName( 'article' )->item( 0 );
	$out     = array();
	foreach ( $article ? $article->childNodes : array() as $node ) {
		if ( $node instanceof DOMElement ) {
			$out[] = $node->tagName . '.' . preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) )[0];
		}
	}
	return $out;
}

cr_chk_group(
	'the egg adapter runs at priority 9, ahead of the priority 10 booth-strip adapter',
	static function (): void {
		cr_chk_same( array( 9 ), cr_chk_priorities( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamChicken\\egg_card' ), 'egg_card at 9' );
	}
);

if ( ! $GLOBALS['cr_chk_no_plugin'] ) {
	cr_chk_group(
		'a titled egg reads image, label, date, heading, and cuts everything else',
		static function (): void {
			cr_chk_stream_setup();
			$out = StreamChicken\egg_card( cr_chk_generic_card() );
			cr_chk_same( array( 'figure.cr-egg-card__photo', 'span.pk-kindlabel', 'p.cr-egg-card__date', 'h2.pk-title' ), cr_chk_egg_children( $out ), 'DOM order' );
			cr_chk_contains( '<article class="pk-card pk-card--stream k-chicken h-entry cr-egg-card">', $out, 'root keeps pk-card before h-entry for the plugin regexes' );
			cr_chk_contains( '<time class="dt-published" datetime="2026-09-24T13:05:00+00:00">September 24, 2026</time>', $out, 'the date, with its year and dt-published' );
			cr_chk_contains( '<a class="u-url" href="https://example.test/chicken/42/">Crossing the path</a>', $out, 'the exact title, no added period' );
			cr_chk_contains( 'class="u-photo" alt="A speckled hen"', $out, 'the card image keeps the plugin alt' );
			cr_chk_same( 1, substr_count( $out, '<a ' ), 'one link' );
			foreach ( array( 'pk-badge', 'pk-excerpt', 'pk-meta', 'Read more', 'pk-media', 'pk-thumbs', 'Pepper on the path', 'more images', '<button' ) as $cut ) {
				cr_chk_lacks( $cut, $out, 'cut ' . $cut );
			}
		}
	);

	cr_chk_group(
		'a picture element is kept whole inside the figure',
		static function (): void {
			cr_chk_stream_setup();
			$out = StreamChicken\egg_card( cr_chk_generic_card( true ) );
			cr_chk_contains( '<figure class="cr-egg-card__photo" data-cr-cover-fallback="true"><picture><source type="image/avif"', $out, 'picture inside the figure' );
			cr_chk_contains( '</picture></figure>', $out, 'closed inside the figure' );
		}
	);

	cr_chk_group(
		'no featured image means no picture, even when the card holds one the site inserted',
		static function (): void {
			foreach ( array( 'cover' => array( 'source' => 'cover' ), 'none' => array( 'source' => '' ) ) as $label => $picture ) {
				cr_chk_stream_setup( array( 'picture' => $picture ) );
				$out = StreamChicken\egg_card( cr_chk_generic_card() );
				cr_chk_lacks( '<img', $out, $label . ': no img' );
				cr_chk_lacks( '<figure', $out, $label . ': no placeholder' );
				cr_chk_same( array( 'span.pk-kindlabel', 'p.cr-egg-card__date', 'h2.pk-title' ), cr_chk_egg_children( $out ), $label . ': label, date, heading' );
			}
		}
	);
}

cr_chk_group(
	'an untitled egg keeps the plugin heading with its screen-reader date and no p-name',
	static function (): void {
		cr_chk_stream_setup( array( 'picture' => array( 'source' => '' ) ) );
		$card = str_replace( '<h2 class="pk-title p-name"><a class="u-url" href="https://example.test/chicken/42/">Crossing the path</a>', '<h2 class="pk-title"><a class="u-url" href="https://example.test/chicken/42/">Chicken<span class="pk-sr-only">, September 24, 2026</span></a>', cr_chk_generic_card() );
		$out  = StreamChicken\egg_card( $card );
		cr_chk_contains( '<h2 class="pk-title"><a class="u-url" href="https://example.test/chicken/42/">Chicken<span class="pk-sr-only">, September 24, 2026</span></a></h2>', $out, 'heading kept as printed' );
		cr_chk_lacks( 'p-name', $out, 'no p-name' );
	}
);

cr_chk_group(
	'a protected egg is its title link and nothing else',
	static function (): void {
		cr_chk_stream_setup();
		$card = '<article class="pk-card pk-card--stream pk-card--protected k-chicken h-entry"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/chicken/42/">Protected: Locked coop log</a></h2></article>';
		$out  = StreamChicken\egg_card( $card );
		cr_chk_same( '<article class="pk-card pk-card--stream pk-card--protected k-chicken h-entry cr-egg-card"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/chicken/42/">Protected: Locked coop log</a></h2></article>', $out, 'protected egg' );
		cr_chk_lacks( '<img', $out, 'no picture' );
	}
);

cr_chk_group(
	'other posts and other surfaces get the plugin card byte for byte',
	static function (): void {
		$card = cr_chk_generic_card();

		cr_chk_stream_setup();
		$GLOBALS['cr_chk_request']['post_kinds'][42] = array( 'note' );
		cr_chk_same( $card, StreamChicken\egg_card( $card ), 'a note' );

		cr_chk_stream_setup();
		$GLOBALS['cr_chk_request']['stream'] = false;
		cr_chk_same( $card, StreamChicken\egg_card( $card ), 'a /type/ archive or any non-Stream page' );

		cr_chk_stream_setup();
		$own = '<article class="pk-card k-chicken h-cite"><h2 class="pk-title">A card the author wrote</h2></article>';
		cr_chk_same( $own, StreamChicken\egg_card( $own ), 'a card block, not the generic card' );

		$no_date = str_replace( '<p class="pk-sub pk-stream-date"><time class="dt-published" datetime="2026-09-24T13:05:00+00:00">September 24, 2026</time></p>', '', $card );
		cr_chk_same( $no_date, StreamChicken\egg_card( $no_date ), 'a card with no date keeps dt-published where it was' );
		cr_chk_same( '', StreamChicken\egg_card( '' ), 'empty html' );
	}
);

cr_chk_group(
	'the block context decides the post when the loop does',
	static function (): void {
		cr_chk_stream_setup();
		$GLOBALS['cr_chk_posts'][99]                 = new WP_Post( 99, 'A note' );
		$GLOBALS['cr_chk_request']['post_kinds'][99] = array( 'note' );
		$card = cr_chk_generic_card();
		cr_chk_same( $card, StreamChicken\egg_card( $card, array(), new WP_Block( array( 'postId' => 99 ) ) ), 'the instance post is a note' );
		cr_chk_contains( 'cr-egg-card', StreamChicken\egg_card( $card, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'the instance post is a chicken' );
	}
);

cr_chk_finish();
