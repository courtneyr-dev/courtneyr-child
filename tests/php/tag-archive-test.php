<?php
/**
 * `php tests/php/tag-archive-test.php`: tag archive name-tag sheet contracts (A-T1, A-T5).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/tag-bootstrap.php';
	require CR_TAG_THEME_DIR . '/inc/stamps.php';
	require CR_TAG_THEME_DIR . '/inc/tag.php';

	use Courtneyr\Child\Tag;

	/** Render the sheet pattern with the entry block registered or not. */
	function cr_tag_pattern( bool $registered ): string {
		$GLOBALS['cr_tag_registered'] = $registered;
		ob_start();
		include CR_TAG_THEME_DIR . '/patterns/cr-tag-sheets-loop.php';
		$html                         = (string) ob_get_clean();
		$GLOBALS['cr_tag_registered'] = true;
		return $html;
	}

	/** Paint one entry for a post with public facts. */
	function cr_tag_paint( int $id, string $class_name = 'is-style-cr-name-tag-sheet' ): string {
		$GLOBALS['cr_tag_facts'][ $id ] = cr_tag_facts();
		return Tag\entry_paint(
			'<article class="pkiw-tag-entry pkiw-tag-entry--content h-entry"><h3 class="pkiw-tag-entry__title"><a class="u-url" id="pkiw-tag-' . $id . '-title" href="https://example.test/tag-' . $id . '/">Title ' . $id . '</a></h3><p class="pkiw-tag-entry__host">guides.example.com</p></article>',
			array( 'attrs' => array( 'className' => $class_name ) ),
			new WP_Block( array( 'postId' => $id ) )
		);
	}

	cr_tag_check(
		'the template keeps the RSVP archive header and swaps in the sheet pattern (C13)',
		static function (): void {
			$tag  = (string) file_get_contents( CR_TAG_THEME_DIR . '/templates/taxonomy-kind-tag.html' );
			$rsvp = (string) file_get_contents( CR_TAG_THEME_DIR . '/templates/taxonomy-kind-rsvp.html' );
			$same = str_replace( array( '"RSVP archive"', 'cr-archive--rsvp', 'courtneyr-child/cr-rsvp-wall-loop' ), array( '"Tag archive"', 'cr-archive--tag', 'courtneyr-child/cr-tag-sheets-loop' ), $rsvp );
			cr_tag_same( $same, $tag, 'template equals the RSVP template with the tag names' );
			cr_tag_contains( '<!-- wp:courtneyr/archive-identity /-->', $tag, 'archive identity' );
			cr_tag_contains( '"showPrefix":false', $tag, 'query title has no prefix' );
			cr_tag_contains( '<!-- wp:term-description', $tag, 'term description' );
			cr_tag_contains( 'is-style-cr-marker-bar-short', $tag, 'orange divider' );
			cr_tag_contains( '"metadata":{"name":"Tag archive"}', $tag, 'main group name' );
			cr_tag_contains( 'cr-archive cr-archive--stream cr-archive--tag', $tag, 'main group classes' );
			cr_tag_contains( '<!-- wp:pattern {"slug":"courtneyr-child/cr-tag-sheets-loop"} /-->', $tag, 'sheet pattern' );
			cr_tag_contains( '<!-- wp:pattern {"slug":"courtneyr-child/cr-browse-all"} /-->', $tag, 'browse all' );
		}
	);

	cr_tag_check(
		'the sheet pattern uses query 261 with twelve entries and falls back without tag-entry (C14)',
		static function (): void {
			$html = cr_tag_pattern( true );
			cr_tag_contains( '"queryId":261', $html, 'query id' );
			cr_tag_contains( '"perPage":12', $html, 'per page' );
			cr_tag_contains( '"inherit":true', $html, 'inherits the archive query' );
			cr_tag_contains( '<!-- wp:post-kinds-indieweb/tag-entry {"linesPerPage":12,"headingLevel":2,"className":"is-style-cr-name-tag-sheet"} /-->', $html, 'entry block' );
			cr_tag_in_order( array( 'cr-tag-sheets__entries', '<!-- /wp:group -->', '<!-- wp:query-pagination ', '<!-- wp:query-no-results -->' ), $html, 'pager follows the sheets group' );
			cr_tag_contains( '{"label":"Previous"}', $html, 'previous label' );
			cr_tag_contains( 'Nothing here yet.', $html, 'existing no-results copy' );
			cr_tag_not_contains( 'cr-archive-stream-loop', $html, 'no fallback when registered' );

			$fallback = cr_tag_pattern( false );
			cr_tag_contains( '<!-- wp:pattern {"slug":"courtneyr-child/cr-archive-stream-loop"} /-->', $fallback, 'fallback pattern' );
			cr_tag_not_contains( 'tag-entry', $fallback, 'no tag-entry in fallback' );
		}
	);

	cr_tag_check(
		'the sheet style and editor sheet target tag-entry, without an inline path (C15)',
		static function (): void {
			Tag\register_block_styles();
			cr_tag_same( 'post-kinds-indieweb/tag-entry', $GLOBALS['cr_tag_styles'][0][0] ?? '', 'style target' );
			cr_tag_same( 'cr-name-tag-sheet', $GLOBALS['cr_tag_styles'][0][1]['name'] ?? '', 'style slug' );
			cr_tag_same( 'Name-tag sheets', $GLOBALS['cr_tag_styles'][0][1]['label'] ?? '', 'style label' );
			cr_tag_assert( empty( $GLOBALS['cr_tag_styles'][0][1]['is_default'] ), 'style is not default' );
			cr_tag_same( 'courtneyr-tag', $GLOBALS['cr_tag_enqueued'][0][1]['handle'] ?? '', 'editor handle' );
			cr_tag_contains( '/assets/css/cr-tag.css', $GLOBALS['cr_tag_enqueued'][0][1]['src'] ?? '', 'editor src' );
			cr_tag_assert( ! array_key_exists( 'path', $GLOBALS['cr_tag_enqueued'][0][1] ?? array() ), 'no path, so core never inlines it' );
			cr_tag_assert( null !== cr_tag_hook( 'init', 'Courtneyr\\Child\\Tag\\register_block_styles' ), 'registered on init' );
			cr_tag_same( 20, cr_tag_hook( 'init', 'Courtneyr\\Child\\Tag\\register_block_styles' )[2] ?? 0, 'init priority' );

			$GLOBALS['cr_tag_styles']     = array();
			$GLOBALS['cr_tag_registered'] = false;
			Tag\register_block_styles();
			$GLOBALS['cr_tag_registered'] = true;
			cr_tag_same( array(), $GLOBALS['cr_tag_styles'], 'no style without tag-entry' );
		}
	);

	cr_tag_check(
		'band fills come only from the allowed AA pairs, never glaucous',
		static function (): void {
			$allowed = array(
				'russian-violet'   => 'printer-ivory',
				'prussian-blue'    => 'printer-ivory',
				'cerulean'         => 'printer-ivory',
				'ut-orange'        => 'russian-violet',
				'sky-blue'         => 'russian-violet',
				'selective-yellow' => 'russian-violet',
				'periwinkle'       => 'russian-violet',
				'blue-green'       => 'russian-violet',
				'light-orange'     => 'russian-violet',
			);
			cr_tag_same( $allowed, Tag\BAND_PAIRS, 'pair list' );
			$seen = array();
			foreach ( range( 1, 400 ) as $id ) {
				$pair = Tag\band_pair( $id );
				cr_tag_same( $allowed[ $pair['band'] ] ?? null, $pair['ink'], 'pair for ' . $id );
				cr_tag_same( \Courtneyr\Child\Stamps\seeded_paper( $id, array_keys( $allowed ) ), $pair['band'], 'band is seeded from the ID for ' . $id );
				$seen[ $pair['band'] ] = true;
			}
			cr_tag_same( count( $allowed ), count( $seen ), 'every allowed band is reachable' );
			cr_tag_assert( ! isset( $seen['glaucous'] ), 'no glaucous band' );
		}
	);

	cr_tag_check(
		'entry paint adds the sticker band, seeded pair and a tilt that differs from its neighbour',
		static function (): void {
			$previous = null;
			foreach ( range( 1, 60 ) as $id ) {
				$html = cr_tag_paint( $id );
				$tilt = null;
				foreach ( range( 0, 3 ) as $candidate ) {
					if ( str_contains( $html, 'cr-name-tag-tilt-' . $candidate ) ) {
						$tilt = $candidate;
					}
				}
				cr_tag_assert( null !== $tilt, 'tilt for ' . $id );
				cr_tag_assert( $previous !== $tilt, 'adjacent tilt differs for ' . $id );
				$previous = $tilt;
				$pair     = Tag\band_pair( $id );
				cr_tag_contains( 'style="--cr-name-tag-band:var(--cr-' . $pair['band'] . ');--cr-name-tag-ink:var(--cr-' . $pair['ink'] . ')"', $html, 'band custom properties for ' . $id );
				cr_tag_contains( 'class="pkiw-tag-entry pkiw-tag-entry--content h-entry cr-name-tag cr-name-tag-tilt-', $html, 'classes for ' . $id );
				cr_tag_in_order( array( '<article', 'cr-name-tag__band', 'pkiw-tag-entry__title' ), $html, 'band is the first child for ' . $id );
				cr_tag_same( 1, substr_count( $html, '<a ' ), 'exactly one link for ' . $id );
			}
			$html = cr_tag_paint( 5 );
			cr_tag_contains( '<span class="cr-name-tag__band" aria-hidden="true"><span class="cr-name-tag__hello">HELLO</span><span class="cr-name-tag__my-name">my name is</span></span>', $html, 'aria-hidden live band text' );
			cr_tag_same( 'courtneyr-child', $GLOBALS['cr_tag_translated']['HELLO'] ?? '', 'HELLO is translatable' );
			cr_tag_same( 'courtneyr-child', $GLOBALS['cr_tag_translated']['my name is'] ?? '', 'my name is is translatable' );
			cr_tag_not_contains( 'I LABELED THIS', $html, 'no invented band copy' );
			cr_tag_not_contains( 'I labeled this', $html, 'no invented band copy' );
			cr_tag_assert( null !== cr_tag_hook( 'render_block_post-kinds-indieweb/tag-entry', 'Courtneyr\\Child\\Tag\\entry_paint' ), 'entry paint hook' );
		}
	);

	cr_tag_check(
		'entry paint passes through without the sheet style or public facts',
		static function (): void {
			$plain = '<article class="pkiw-tag-entry pkiw-tag-entry--content h-entry"><h3><a href="#">X</a></h3></article>';
			$out   = Tag\entry_paint( $plain, array( 'attrs' => array( 'className' => 'is-style-other' ) ), new WP_Block( array( 'postId' => 3 ) ) );
			cr_tag_same( $plain, $out, 'other style' );
			$out = Tag\entry_paint( $plain, array( 'attrs' => array( 'className' => 'is-style-cr-name-tag-sheet' ) ), new WP_Block( array( 'postId' => 999 ) ) );
			cr_tag_same( $plain, $out, 'no facts (not public)' );
			cr_tag_same( '', Tag\entry_paint( '', array( 'attrs' => array( 'className' => 'is-style-cr-name-tag-sheet' ) ), new WP_Block( array( 'postId' => 3 ) ) ), 'empty entry' );
		}
	);

	cr_tag_check(
		'sheet headings over 24 characters switch to Roboto Slab; the stored label prints verbatim',
		static function (): void {
			$long  = 'A very long label that keeps going to test wrapping sheets';
			$html  = '<div class="pkiw-tag--sectioned"><section class="pkiw-tag-section"><h2 class="pkiw-tag-section__heading" id="pkiw-group-a">Accessibility</h2><ul class="pkiw-tag-section__items"></ul></section>';
			$html .= '<section class="pkiw-tag-section"><h2 class="pkiw-tag-section__heading" id="pkiw-group-b">' . $long . '</h2><ul class="pkiw-tag-section__items"></ul></section>';
			$html .= '<section class="pkiw-tag-section"><h2 class="pkiw-tag-section__heading">documentation &amp; more</h2></section></div>';
			$out   = Tag\sheet_headings( $html, array( 'attrs' => array( 'className' => 'cr-tag-sheets__entries' ) ) );
			cr_tag_contains( '<h2 class="pkiw-tag-section__heading cr-name-tag-sheet__heading" id="pkiw-group-a">Accessibility</h2>', $out, 'short heading' );
			cr_tag_contains( '<h2 class="pkiw-tag-section__heading cr-name-tag-sheet__heading cr-name-tag-sheet__heading--long" id="pkiw-group-b">' . $long . '</h2>', $out, 'long heading' );
			cr_tag_contains( '<h2 class="pkiw-tag-section__heading cr-name-tag-sheet__heading">documentation &amp; more</h2>', $out, 'lowercase label stays verbatim' );
			cr_tag_same( $html, Tag\sheet_headings( $html, array( 'attrs' => array( 'className' => 'something-else' ) ) ), 'other post templates untouched' );
			cr_tag_assert( Tag\is_display_label( str_repeat( 'x', 24 ) ), '24 characters is display' );
			cr_tag_assert( ! Tag\is_display_label( str_repeat( 'x', 25 ) ), '25 characters is not display' );
		}
	);

	cr_tag_check(
		'the stylesheet loads only on tag surfaces and stays out of shared used CSS',
		static function (): void {
			$GLOBALS['cr_tag_enqueued'] = array();
			Tag\enqueue();
			cr_tag_same( array(), $GLOBALS['cr_tag_enqueued'], 'not on other pages' );
			$GLOBALS['cr_tag_archive'] = true;
			Tag\enqueue();
			$GLOBALS['cr_tag_archive'] = false;
			cr_tag_same( 'courtneyr-tag', $GLOBALS['cr_tag_enqueued'][0][0] ?? '', 'archive handle' );
			$GLOBALS['cr_tag_enqueued'] = array();
			$GLOBALS['cr_tag_stream']   = true;
			Tag\enqueue();
			$GLOBALS['cr_tag_stream'] = false;
			cr_tag_same( 'courtneyr-tag', $GLOBALS['cr_tag_enqueued'][0][0] ?? '', 'Stream handle' );
			cr_tag_same( array( 'a.css', 'cr-tag.css' ), Tag\rucss_exclusions( array( 'a.css' ) ), 'RUCSS exclusion' );
			$pruning = (string) file_get_contents( CR_TAG_THEME_DIR . '/inc/stream-style-pruning.php' );
			cr_tag_contains( "\t'courtneyr-rsvp',\n\t'courtneyr-tag',\n", $pruning, 'inline handle after courtneyr-rsvp' );
			$functions = (string) file_get_contents( CR_TAG_THEME_DIR . '/functions.php' );
			cr_tag_contains( "require_once __DIR__ . '/inc/stream-rsvp.php';\nrequire_once __DIR__ . '/inc/tag.php';\nrequire_once __DIR__ . '/inc/single-tag.php';\nrequire_once __DIR__ . '/inc/stream-tag.php';\n", $functions, 'requires after stream-rsvp' );
		}
	);

	cr_tag_check(
		'cr-tag.css stays within the guardrails (A-T5)',
		static function (): void {
			$css = (string) file_get_contents( CR_TAG_THEME_DIR . '/assets/css/cr-tag.css' );
			cr_tag_assert( strlen( $css ) <= 30 * 1024, 'at most 30 KiB raw, got ' . strlen( $css ) );
			$code = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
			cr_tag_assert( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b/', $code ), 'no raw hex' );
			foreach ( array( '--pk-', 'gradient', 'blur(', 'backdrop-filter', '9999px' ) as $banned ) {
				cr_tag_not_contains( $banned, $code, 'banned paint ' . $banned );
			}
			cr_tag_contains( '@media (prefers-color-scheme: dark)', $code, 'system dark block' );
			cr_tag_contains( ':root:not([data-theme="light"])', $code, 'dark selector reaches data-theme=system' );
			cr_tag_contains( ':root[data-theme="dark"]', $code, 'manual dark selector' );
			cr_tag_not_contains( ':root:not([data-theme])', $code, 'no bare missing-attribute dark rule' );
			cr_tag_contains( '@media (forced-colors: active)', $code, 'forced colors' );
			cr_tag_contains( '@media (prefers-reduced-motion: no-preference)', $code, 'motion gated' );
			foreach ( array( 'Accessibility', 'IndieWeb', 'documentation' ) as $label ) {
				cr_tag_not_contains( $label, $code, 'no hardcoded label ' . $label );
			}
		}
	);

	cr_tag_finish();
}
