<?php
/**
 * Standalone checks for jam archive and Stream strip decoration.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/jam-bootstrap.php';

use Courtneyr\Child\Jam as Jam;

/** Plugin Jam_Archive listing output after ensure_entry_properties(). */
function cr_jam_listing_fixture( int $id = 42, bool $cover = true ): string {
	$media = $cover ? '<div class="pk-media"><img class="pk-cover" src="https://example.test/cover.jpg" alt="" loading="lazy" decoding="async" /></div>' : '';
	return '<article class="pk-card pk-card--stream pk-card--jam k-jam h-entry pk-card--jam-listing">' . $media
		. '<h3 class="pk-title"><a class="u-url p-name" href="https://example.test/jam/' . $id . '/" aria-labelledby="pkiw-jam-t-' . $id . ' pkiw-jam-a-' . $id . '"><span id="pkiw-jam-t-' . $id . '">Electric Morning</span></a></h3>'
		. '<p class="pk-sub" id="pkiw-jam-a-' . $id . '">The Paper Satellites</p>'
		. '<span class="pk-kind-cite h-cite u-jam-of" hidden><data class="p-name" value="Electric Morning"></data><span class="p-author h-card"><data class="p-name" value="The Paper Satellites"></data></span><data class="u-url" value="https://music.example/track"></data></span>'
		. '<span class="pk-entry-props" hidden><span class="p-author h-card"><span class="p-name">Courtney</span><data class="u-url" value="https://example.test/"></data></span><data class="dt-published" value="2026-09-27T12:00:00+00:00"></data></span></article>';
}

/** Plugin Jam_Archive Stream output. */
function cr_jam_stream_fixture( int $id = 42 ): string {
	return '<article class="pk-card pk-card--stream pk-card--jam k-jam h-entry pk-card--jam-stream"><span class="pk-kindlabel">Jam</span><h3 class="pk-title"><a class="u-url p-name" href="https://example.test/jam/' . $id . '/">Electric Morning</a></h3><p class="pk-sub">The Paper Satellites</p><p class="pk-sub pk-stream-date"><time datetime="2026-09-27">September 27, 2026</time></p><span class="pk-kind-cite h-cite u-jam-of" hidden><data class="p-name" value="Electric Morning"></data><span class="p-author h-card"><data class="p-name" value="The Paper Satellites"></data></span><data class="u-url" value="https://music.example/track"></data></span></article>';
}

/** Remove only attributes and classes the adapter is allowed to add. */
function cr_jam_undress_strip( string $html, bool $listing ): string {
	$added = $listing
		? array( ' cr-jam-strip--listing', ' cr-jam-strip' )
		: array( ' cr-jam-strip--stream', ' cr-paper-sheet--torn', ' cr-paper--sky-blue', ' cr-paper-sheet', ' cr-jam-strip' );
	$html = str_replace( $added, '', $html );
	$html = (string) preg_replace( '/ data-cr-cover-fallback="true"/', '', $html );
	$html = (string) preg_replace( '/ data-cr-rotate="[0-3]"/', '', $html );
	$html = (string) preg_replace( '/ fetchpriority="high"/', '', $html );
	$html = str_replace( 'loading="eager"', 'loading="lazy"', $html );
	return $html;
}

cr_jam_group(
	'listing decoration changes only approved classes and attributes',
	static function (): void {
		cr_jam_reset();
		$input = cr_jam_listing_fixture();
		$out   = Jam\jukebox_strip( $input, array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_contains( 'cr-jam-strip cr-jam-strip--listing', $out, 'listing classes' );
		cr_jam_contains( 'data-cr-cover-fallback="true"', $out, 'failed-cover contract' );
		cr_jam_contains( 'loading="eager"', $out, 'lead eager' );
		cr_jam_contains( 'fetchpriority="high"', $out, 'lead high priority' );
		cr_jam_same( $input, cr_jam_undress_strip( $out, true ), 'only allowed listing mutations' );
		cr_jam_same( 1, cr_jam_rendered_anchors( $out ), 'one rendered anchor' );
		cr_jam_same( 0, cr_jam_visible_negative_tabindex( $out ), 'no visible negative tabindex anchor' );
	}
);

cr_jam_group(
	'page-one and paged cover loading windows count coverless cards',
	static function (): void {
		cr_jam_reset();
		$page_one = array();
		for ( $n = 1; $n <= 5; $n++ ) {
			$page_one[] = Jam\jukebox_strip( cr_jam_listing_fixture( 40 + $n ), array(), new WP_Block( array( 'postId' => 40 + $n ) ) );
		}
		cr_jam_contains( 'loading="eager"', $page_one[0], 'lead eager' );
		cr_jam_contains( 'fetchpriority="high"', $page_one[0], 'lead high priority' );
		foreach ( array( 1, 2, 3 ) as $at ) {
			cr_jam_contains( 'loading="eager"', $page_one[ $at ], 'first row eager ' . $at );
			cr_jam_not_contains( 'fetchpriority', $page_one[ $at ], 'only lead high priority ' . $at );
		}
		cr_jam_contains( 'loading="lazy"', $page_one[4], 'later cover lazy' );
		cr_jam_not_contains( 'fetchpriority', $page_one[4], 'later cover no priority' );

		cr_jam_reset();
		Jam\jukebox_strip( cr_jam_listing_fixture( 50, false ), array(), new WP_Block( array( 'postId' => 50 ) ) );
		Jam\jukebox_strip( cr_jam_listing_fixture( 51 ), array(), new WP_Block( array( 'postId' => 51 ) ) );
		Jam\jukebox_strip( cr_jam_listing_fixture( 52 ), array(), new WP_Block( array( 'postId' => 52 ) ) );
		Jam\jukebox_strip( cr_jam_listing_fixture( 53 ), array(), new WP_Block( array( 'postId' => 53 ) ) );
		$fifth = Jam\jukebox_strip( cr_jam_listing_fixture( 54 ), array(), new WP_Block( array( 'postId' => 54 ) ) );
		cr_jam_contains( 'loading="lazy"', $fifth, 'coverless lead still consumes its position' );

		cr_jam_reset();
		$GLOBALS['cr_jam_request']['query_vars']['paged'] = 2;
		$paged = array();
		for ( $n = 1; $n <= 4; $n++ ) {
			$paged[] = Jam\jukebox_strip( cr_jam_listing_fixture( 60 + $n ), array(), new WP_Block( array( 'postId' => 60 + $n ) ) );
		}
		foreach ( array( 0, 1, 2 ) as $at ) {
			cr_jam_contains( 'loading="eager"', $paged[ $at ], 'paged first row eager ' . $at );
			cr_jam_not_contains( 'fetchpriority', $paged[ $at ], 'paged request never high priority ' . $at );
		}
		cr_jam_contains( 'loading="lazy"', $paged[3], 'paged fourth cover lazy' );
	}
);

cr_jam_group(
	'Stream strip receives stable bounded rotation and preserves markup',
	static function (): void {
		cr_jam_reset();
		$input = cr_jam_stream_fixture();
		$out   = Jam\jukebox_strip( $input, array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_contains( 'cr-jam-strip cr-jam-strip--stream cr-paper-sheet cr-paper-sheet--torn cr-paper--sky-blue', $out, 'Stream classes' );
		cr_jam_assert( 1 === preg_match( '/data-cr-rotate="[0-3]"/', $out ), 'one bounded rotation' );
		cr_jam_same( $out, Jam\jukebox_strip( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'rotation stable for one post' );
		cr_jam_same( $input, cr_jam_undress_strip( $out, false ), 'only allowed Stream mutations' );
		cr_jam_same( 1, cr_jam_rendered_anchors( $out ), 'one Stream anchor' );
		cr_jam_same( 0, cr_jam_visible_negative_tabindex( $out ), 'no Stream negative tabindex anchor' );

		$rotations = array();
		for ( $id = 1; $id <= 12; $id++ ) {
			$card = Jam\jukebox_strip( cr_jam_stream_fixture( $id ), array(), new WP_Block( array( 'postId' => $id ) ) );
			preg_match( '/data-cr-rotate="([0-3])"/', $card, $match );
			$rotations[] = $match[1] ?? '';
		}
		cr_jam_assert( count( array_unique( $rotations ) ) > 1, 'several post IDs yield more than one rotation' );
	}
);

cr_jam_group(
	'unrelated and unsupported cards are byte-identical',
	static function (): void {
		$protected = '<article class="pk-card pk-card--stream pk-card--protected k-jam h-entry"><h3 class="pk-title"><a class="u-url" href="https://example.test/jam/42/">Protected: Jam</a></h3></article>';
		$non_jam   = str_replace( array( 'k-jam', 'pk-card--jam-listing' ), array( 'k-note', 'pk-card--note' ), cr_jam_listing_fixture() );
		$unshaped  = str_replace( ' pk-card--jam-listing', '', cr_jam_listing_fixture() );
		foreach ( array( '', $protected, $non_jam, $unshaped ) as $input ) {
			cr_jam_same( $input, Jam\jukebox_strip( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'unsupported input unchanged' );
		}
	}
);

cr_jam_group(
	'hook and source contract',
	static function (): void {
		cr_jam_assert( cr_jam_hooked( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\Jam\\jukebox_strip', 10, 3 ), 'strip hook' );
		$source = file_get_contents( CR_JAM_THEME_DIR . '/inc/jam.php' );
		cr_jam_assert( is_string( $source ), 'source read' );
		foreach ( array( 'get_post_meta', 'parse_blocks', 'untitled_name', 'kind_picture', 'cut_elements', "['attrs']", '["attrs"]' ) as $forbidden ) {
			cr_jam_not_contains( $forbidden, (string) $source, 'source excludes ' . $forbidden );
		}
	}
);

if ( empty( $GLOBALS['cr_jam_no_plugin'] ) ) {
	cr_jam_group(
		'strip adapter runs without the plugin',
		static function (): void {
			$child = proc_open(
				array( PHP_BINARY, __FILE__, '--no-plugin' ),
				array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
				$pipes
			);
			cr_jam_assert( is_resource( $child ), 'could launch no-plugin child' );
			$out  = stream_get_contents( $pipes[1] );
			$err  = stream_get_contents( $pipes[2] );
			$code = proc_close( $child );
			cr_jam_same( 0, $code, 'no-plugin exit (' . trim( $out . $err ) . ')' );
		}
	);
}

cr_jam_finish();
