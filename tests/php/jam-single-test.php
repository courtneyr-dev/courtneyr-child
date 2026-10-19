<?php
/**
 * Standalone checks for the jam single J-card adapter.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/jam-bootstrap.php';

use Courtneyr\Child\SingleJam as SingleJam;

/** Plugin jam-card render.php output for the requested field combination. */
function cr_jam_card_fixture( array $values = array() ): string {
	$values = array_merge(
		array(
			'title'  => 'Electric Morning',
			'artist' => 'The Paper Satellites',
			'album'  => 'Signal Bloom',
			'url'    => 'https://music.example/track',
			'note'   => 'Bright synths for an early start.',
			'rel'    => 'noopener noreferrer enclosure',
			'jammed' => '2026-09-27',
			'embed'  => '<iframe src="https://player.example/embed?autoplay=1" title="Player"></iframe>',
			'cover'  => 'https://example.test/uploads/cover.jpg',
		),
		$values
	);

	$out  = '<article class="wp-block-post-kinds-indieweb-jam-card pk-card k-jam h-cite">';
	$out .= '<div class="pk-badge"><svg aria-hidden="true"><path d="M0 0h1v1z"/></svg></div><div class="pk-body"><span class="pk-kindlabel">Jam</span><div class="pk-caption">';
	if ( '' !== $values['title'] ) {
		$out .= '<h2 class="pk-title p-name">';
		$out .= '' !== $values['url']
			? '<a class="u-url u-jam-of" href="' . esc_url( $values['url'] ) . '" target="_blank" rel="' . esc_attr( $values['rel'] ) . '" aria-label="Electric Morning (opens in a new tab)">Electric Morning</a>'
			: 'Electric Morning';
		$out .= '</h2>';
	}
	if ( '' !== $values['artist'] || '' !== $values['album'] ) {
		$out .= '<p class="pk-sub">';
		if ( '' !== $values['artist'] ) {
			$out .= '<span class="p-author h-card"><span class="p-name">' . esc_html( $values['artist'] ) . '</span></span>';
		}
		if ( '' !== $values['artist'] && '' !== $values['album'] ) {
			$out .= ' &mdash; ';
		}
		if ( '' !== $values['album'] ) {
			$out .= '<em>' . esc_html( $values['album'] ) . '</em>';
		}
		$out .= '</p>';
	}
	$out .= '</div>';
	if ( '' !== $values['note'] ) {
		$out .= '<p class="pk-note p-content">' . esc_html( $values['note'] ) . '</p>';
	}
	if ( '' !== $values['embed'] ) {
		$out .= '<div class="pk-embed pk-embed--audio">' . $values['embed'] . '</div>';
	} elseif ( '' !== $values['cover'] ) {
		$out .= '<div class="pk-embed pk-embed--photo"><img class="u-photo" src="' . esc_url( $values['cover'] ) . '" alt="Blue mountain album cover" loading="lazy" /></div>';
	}
	$out .= '<div class="pk-meta">';
	if ( '' !== $values['url'] ) {
		$out .= '<a class="pk-link" href="' . esc_url( $values['url'] ) . '" target="_blank" rel="' . esc_attr( $values['rel'] ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3l14 9-14 9z"/></svg>Jam<span class="screen-reader-text"> (opens in a new tab)</span></a>';
	}
	if ( '' !== $values['url'] && '' !== $values['jammed'] ) {
		$out .= '<span class="pk-dot"></span>';
	}
	if ( '' !== $values['jammed'] ) {
		$out .= '<time class="dt-published" datetime="2026-09-27">September 27, 2026</time>';
	}
	return $out . '</div></div></article>';
}

/** Prepare a jam single request and return its fixture. */
function cr_jam_single_case( array $facts = array(), array $fixture = array() ): string {
	cr_jam_reset();
	$GLOBALS['cr_jam_request']['single'] = true;
	$GLOBALS['cr_jam_facts'][42]         = cr_jam_facts( $facts );
	return cr_jam_card_fixture( $fixture );
}

if ( ! empty( $GLOBALS['cr_jam_no_plugin'] ) ) {
	cr_jam_group(
		'no plugin: the J-card adapter returns plugin markup unchanged',
		static function (): void {
			$input = cr_jam_single_case();
			cr_jam_same( $input, SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'facts unavailable' );
		}
	);
	cr_jam_finish();
}

cr_jam_group(
	'full card preserves plugin fragments in four-panel order',
	static function (): void {
		$input = cr_jam_single_case();
		$audio = '<div class="pk-embed pk-embed--audio"><iframe src="https://player.example/embed?autoplay=1" title="Player"></iframe></div>';
		$out   = SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 42 ) ) );
		foreach ( array( 'cr-jcard__insert', 'cr-jcard__details', 'cr-jcard__spine', 'cr-jcard__note' ) as $class ) {
			cr_jam_contains( $class, $out, $class );
		}
		$positions = array_map( static fn( string $class ): int => (int) strpos( $out, $class ), array( 'cr-jcard__insert', 'cr-jcard__details', 'cr-jcard__spine', 'cr-jcard__note' ) );
		cr_jam_assert( $positions === array_values( $positions ) && $positions[0] < $positions[1] && $positions[1] < $positions[2] && $positions[2] < $positions[3], 'panel DOM order' );
		cr_jam_contains( '<p class="pk-title p-name"><a class="u-url u-jam-of"', $out, 'Track becomes a paragraph and keeps link classes' );
		cr_jam_contains( '<span class="p-author h-card"><span class="p-name">The Paper Satellites</span></span>', $out, 'artist span verbatim' );
		cr_jam_contains( '<em>Signal Bloom</em>', $out, 'album fragment' );
		cr_jam_contains( '<dt>Date</dt><dd><time class="dt-published" datetime="2026-09-27">September 27, 2026</time>', $out, 'stored date row' );
		cr_jam_not_contains( '>Jammed<', $out, 'label is never Jammed' );
		cr_jam_contains( 'rel="noopener noreferrer enclosure" aria-label="Electric Morning (opens in a new tab)"', $out, 'Track rel and new-tab label' );
		cr_jam_contains( 'class="pk-link" href="https://music.example/track" target="_blank" rel="noopener noreferrer enclosure"', $out, 'Jam link rel' );
		cr_jam_same( 1, substr_count( $out, 'class="u-photo"' ), 'one cover image' );
		cr_jam_contains( 'alt="Blue mountain album cover" loading="eager" fetchpriority="high" decoding="async"', $out, 'attachment image attributes' );
		cr_jam_assert( 1 === preg_match( '/<div class="cr-jcard__spine" aria-hidden="true"><\/div>/', $out ), 'spine has no text node' );
		cr_jam_not_contains( 'pk-badge', $out, 'badge removed' );
		cr_jam_not_contains( 'pk-kindlabel', $out, 'kind label removed' );
		cr_jam_not_contains( 'pk-embed--photo', $out, 'photo embed removed' );
		cr_jam_same( 1, substr_count( $out, $audio ), 'audio embed appears once byte-identical' );
		cr_jam_assert( strpos( $out, $audio ) === strpos( $out, '</article>' ) + strlen( '</article>' ), 'audio immediately follows article' );
		cr_jam_contains( 'autoplay=1', $out, 'autoplay query untouched' );
	}
);

cr_jam_group(
	'Track matching the H1 becomes hidden data with no hidden anchor',
	static function (): void {
		$input = cr_jam_single_case( array( 'track_is_h1' => true ) );
		$out   = SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_not_contains( '<dt>Track</dt>', $out, 'Track row omitted' );
		cr_jam_contains( '<data class="p-name" value="Electric Morning" hidden></data>', $out, 'hidden cite name' );
		cr_jam_contains( '<data class="u-url u-jam-of" value="https://music.example/track" hidden></data>', $out, 'hidden cite URL' );
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><body>' . $out . '</body>' );
		libxml_clear_errors();
		cr_jam_same( 0, ( new DOMXPath( $dom ) )->query( '//*[@hidden]//a' )->length, 'no anchor under hidden' );
	}
);

cr_jam_group(
	'an empty track with a real post title keeps hidden p-name and URL, but an untitled name gets no p-name',
	static function (): void {
		$facts = array(
			'title'       => '',
			'name'        => 'Hollow Light by Marlowe Kite',
			'name_source' => 'title',
			'track_is_h1' => false,
		);
		$out = SingleJam\jcard( cr_jam_single_case( $facts, array( 'title' => '' ) ), array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_not_contains( '<dt>Track</dt>', $out, 'no stored track, so no Track row' );
		cr_jam_contains( '<data class="p-name" value="Hollow Light by Marlowe Kite" hidden></data>', $out, 'hidden p-name from the real post title' );
		cr_jam_contains( '<data class="u-url u-jam-of" value="https://music.example/track" hidden></data>', $out, 'hidden jam-of URL' );

		$facts['name']        = 'Jam, September 27, 2026 at 8:00 am';
		$facts['name_source'] = 'untitled';
		$out                  = SingleJam\jcard( cr_jam_single_case( $facts, array( 'title' => '' ) ), array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_not_contains( 'class="p-name" value=', $out, 'a synthetic name never becomes p-name' );
		cr_jam_contains( '<data class="u-url u-jam-of" value="https://music.example/track" hidden></data>', $out, 'URL still kept' );
	}
);

cr_jam_group(
	'optional rows, note, link and cover are omitted whole',
	static function (): void {
		$facts = array(
			'artist'         => '',
			'album'          => '',
			'note'           => '',
			'url'            => '',
			'date'           => '2026-09-26',
			'date_display'   => 'September 26, 2026',
			'date_is_stored' => false,
			'picture'        => array(
				'source'            => '',
				'attachment_id'     => 0,
				'url'               => '',
				'alt'               => '',
				'remote'            => false,
				'suppress_featured' => false,
			),
		);
		$fixture = array( 'artist' => '', 'album' => '', 'note' => '', 'url' => '', 'jammed' => '', 'embed' => '', 'cover' => '' );
		$out     = SingleJam\jcard( cr_jam_single_case( $facts, $fixture ), array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_not_contains( '<dt>Artist</dt>', $out, 'artist row omitted' );
		cr_jam_not_contains( '<dt>Album</dt>', $out, 'album row omitted' );
		cr_jam_not_contains( 'cr-jcard__note', $out, 'note panel omitted' );
		cr_jam_not_contains( 'cr-jcard__insert', $out, 'insert omitted' );
		cr_jam_not_contains( 'class="pk-link"', $out, 'Jam link omitted' );
		cr_jam_not_contains( 'u-jam-of" value=', $out, 'URL data omitted' );
		cr_jam_contains( '<time datetime="2026-09-26">September 26, 2026</time>', $out, 'fallback date has no dt-published' );
		cr_jam_not_contains( '<iframe', $out, 'no invented iframe' );
	}
);

cr_jam_group(
	'remote and featured pictures use the facts alt fallbacks',
	static function (): void {
		$facts = array(
			'cover_alt' => '',
			'picture'   => array(
				'source'        => 'featured',
				'attachment_id' => 777,
				'url'           => 'https://example.test/uploads/featured.jpg',
				'alt'           => 'Featured sunset',
			),
		);
		$out = SingleJam\jcard( cr_jam_single_case( $facts ), array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_contains( 'attachment-777-large.jpg', $out, 'featured attachment image' );
		cr_jam_contains( 'alt="Electric Morning by The Paper Satellites"', $out, 'no card alt: the Title by Artist fallback wins over the picture alt' );

		$facts['cover_alt'] = 'Card cover alt';
		$out                = SingleJam\jcard( cr_jam_single_case( $facts ), array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_contains( 'alt="Card cover alt"', $out, 'card cover alt beats a featured picture alt' );
		cr_jam_not_contains( 'alt="Featured sunset"', $out, 'featured picture alt not used while a card alt exists' );

		$facts['cover_alt']          = '';
		$facts['cover_alt_fallback'] = '';
		$out                         = SingleJam\jcard( cr_jam_single_case( $facts ), array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_contains( 'alt="Featured sunset"', $out, 'picture alt only when both card alts are empty' );

		cr_jam_reset();
		$GLOBALS['cr_jam_request']['single']                 = true;
		$GLOBALS['cr_jam_request']['missing_attachments'][901] = true;
		$GLOBALS['cr_jam_facts'][42] = cr_jam_facts(
			array(
				'cover_alt' => '',
				'picture'   => array( 'alt' => '' ),
			)
		);
		$out = SingleJam\jcard( cr_jam_card_fixture(), array(), new WP_Block( array( 'postId' => 42 ) ) );
		cr_jam_contains( 'src="https://example.test/uploads/cover.jpg"', $out, 'URL fallback image' );
		cr_jam_contains( 'alt="Electric Morning by The Paper Satellites"', $out, 'computed alt fallback' );
	}
);

cr_jam_group(
	'guards return plugin output byte-identical',
	static function (): void {
		$input = cr_jam_single_case();
		$GLOBALS['cr_jam_request']['protected'][42] = true;
		cr_jam_same( $input, SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'protected' );
		$GLOBALS['cr_jam_request']['protected'] = array();
		$GLOBALS['cr_jam_facts'][42]             = array();
		cr_jam_same( $input, SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'empty facts' );
		$GLOBALS['cr_jam_facts'][42] = cr_jam_facts( array( 'has_card' => false ) );
		cr_jam_same( $input, SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'card-less' );
		$GLOBALS['cr_jam_facts'][42] = cr_jam_facts();
		$GLOBALS['cr_jam_request']['single'] = false;
		cr_jam_same( $input, SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'not singular' );
		$GLOBALS['cr_jam_request']['single']           = true;
		$GLOBALS['cr_jam_request']['stream_rendering'] = true;
		cr_jam_same( $input, SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 42 ) ) ), 'Stream render' );
		$GLOBALS['cr_jam_request']['stream_rendering'] = false;
		cr_jam_same( $input, SingleJam\jcard( $input, array(), new WP_Block( array( 'postId' => 77 ) ) ), 'different post' );
		cr_jam_same( '<p>No article</p>', SingleJam\jcard( '<p>No article</p>', array(), new WP_Block( array( 'postId' => 42 ) ) ), 'no article' );
	}
);

cr_jam_group(
	'featured repeat, kind registry and body class stay scoped',
	static function (): void {
		$post = cr_jam_reset();
		$GLOBALS['cr_jam_request']['single'] = true;
		$GLOBALS['cr_jam_facts'][42]['picture']['suppress_featured'] = true;
		cr_jam_same( true, SingleJam\featured_repeats( false, $post ), 'repeated featured cover suppressed' );
		$GLOBALS['cr_jam_facts'][42]['has_card'] = false;
		cr_jam_same( false, SingleJam\featured_repeats( false, $post ), 'card-less jam keeps incoming false' );
		$GLOBALS['cr_jam_facts'][42]['has_card'] = true;
		$GLOBALS['cr_jam_request']['post_kinds'][42] = array( 'note' );
		cr_jam_same( false, SingleJam\featured_repeats( false, $post ), 'non-jam keeps incoming false' );
		cr_jam_same( array( 'note', 'jam' ), SingleJam\repeat_kinds( array( 'note' ) ), 'jam added' );
		cr_jam_same( array( 'note', 'jam' ), SingleJam\repeat_kinds( array( 'note', 'jam' ) ), 'jam added once' );
		cr_jam_same( 'unchanged', SingleJam\repeat_kinds( 'unchanged' ), 'non-array registry untouched' );
		$GLOBALS['cr_jam_request']['post_kinds'][42] = array( 'jam' );
		cr_jam_same( array( 'single', 'cr-jam-single' ), SingleJam\body_class( array( 'single' ) ), 'single body class' );
	}
);

cr_jam_group(
	'hooks and source restrictions',
	static function (): void {
		cr_jam_assert( cr_jam_hooked( 'render_block_post-kinds-indieweb/jam-card', 'Courtneyr\\Child\\SingleJam\\jcard', 20, 3 ), 'J-card hook' );
		cr_jam_assert( cr_jam_hooked( 'courtneyr_child_featured_repeats', 'Courtneyr\\Child\\SingleJam\\featured_repeats', 10, 2 ), 'repeat decision hook' );
		$source = (string) file_get_contents( CR_JAM_THEME_DIR . '/inc/single-jam.php' );
		foreach ( array( 'attrs', 'get_post_meta', 'get_cached_embed_html', 'parse_blocks' ) as $forbidden ) {
			cr_jam_not_contains( $forbidden, $source, 'source excludes ' . $forbidden );
		}
	}
);

cr_jam_group(
	'J-card adapter runs without the plugin',
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

cr_jam_finish();
