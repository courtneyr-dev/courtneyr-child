<?php
/**
 * Follow archive and shared theme contracts (acceptance item 15).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/follow-bootstrap.php';
	require CR_FOLLOW_THEME_DIR . '/inc/stamps.php';
	require CR_FOLLOW_THEME_DIR . '/inc/follow.php';

	use Courtneyr\Child\Follow;

	/** Render the pattern with or without the plugin entry block. */
	function cr_follow_pattern( bool $registered ): string {
		$GLOBALS['cr_follow_registered']['post-kinds-indieweb/follow-entry'] = $registered;
		ob_start();
		include CR_FOLLOW_THEME_DIR . '/patterns/cr-follow-rolodex-loop.php';
		$html = (string) ob_get_clean();
		$GLOBALS['cr_follow_registered']['post-kinds-indieweb/follow-entry'] = true;
		return $html;
	}

	cr_follow_check(
		'template copies the quote header and uses the follow pattern',
		static function (): void {
			$follow = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/templates/taxonomy-kind-follow.html' );
			$quote  = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/templates/taxonomy-kind-quote.html' );
			$same   = str_replace( array( 'Quote archive', 'cr-archive--quote', 'cr-quote-book-loop' ), array( 'Follow archive', 'cr-archive--follow', 'cr-follow-rolodex-loop' ), $quote );
			cr_follow_same( $same, $follow, 'template equals the quote template with only frozen replacements' );
			$blocks = array_values( array_filter( parse_blocks( $follow ), static fn( array $block ): bool => null !== $block['blockName'] ) );
			cr_follow_same( 3, count( $blocks ), 'template parses to three top-level blocks' );
			cr_follow_contains( '<!-- wp:courtneyr/archive-identity /-->', $follow, 'archive identity' );
			cr_follow_contains( '"showPrefix":false', $follow, 'served term title' );
			cr_follow_contains( 'is-style-cr-marker-bar-short', $follow, 'marker divider' );
			cr_follow_contains( 'courtneyr-child/cr-browse-all', $follow, 'browse all' );
		}
	);

	cr_follow_check(
		'pattern uses query 260, letters before eight cards, pagination and fallback',
		static function (): void {
			$html = cr_follow_pattern( true );
			$blocks = array_values( array_filter( parse_blocks( $html ), static fn( array $block ): bool => null !== $block['blockName'] ) );
			cr_follow_same( 1, count( $blocks ), 'pattern parses to one top-level group' );
			cr_follow_contains( '"queryId":260', $html, 'query ID' );
			cr_follow_contains( '"perPage":8', $html, 'eight per page' );
			cr_follow_contains( '"inherit":true', $html, 'inherited archive query' );
			cr_follow_not_contains( '"namespace"', $html, 'no theme Query namespace' );
			cr_follow_in_order(
				array(
					'<!-- wp:post-kinds-indieweb/follow-letters /-->',
					'<!-- wp:post-template {"className":"cr-rolodex__cards"} -->',
					'<!-- wp:post-kinds-indieweb/follow-entry {"headingLevel":2,"className":"is-style-cr-rolodex"} /-->',
					'<!-- wp:query-pagination ',
					'<!-- wp:query-no-results -->',
				),
				$html,
				'pattern order'
			);
			cr_follow_contains( '{"label":"Previous"}', $html, 'Previous label' );
			cr_follow_contains( '{"label":"Next"}', $html, 'Next label' );
			cr_follow_contains( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.', $html, 'existing no-results copy' );
			$fallback = cr_follow_pattern( false );
			cr_follow_contains( 'courtneyr-child/cr-archive-stream-loop', $fallback, 'fallback pattern' );
			cr_follow_not_contains( 'follow-entry', $fallback, 'no unavailable block in fallback' );
		}
	);

	cr_follow_check(
		'Rolodex style and editor sheet target follow-entry at init 20',
		static function (): void {
			Follow\register_block_styles();
			cr_follow_same( 'post-kinds-indieweb/follow-entry', $GLOBALS['cr_follow_styles'][0][0] ?? '', 'style target' );
			cr_follow_same( 'cr-rolodex', $GLOBALS['cr_follow_styles'][0][1]['name'] ?? '', 'style slug' );
			cr_follow_same( 'Rolodex card', $GLOBALS['cr_follow_styles'][0][1]['label'] ?? '', 'style label' );
			cr_follow_assert( empty( $GLOBALS['cr_follow_styles'][0][1]['is_default'] ), 'style is not default' );
			cr_follow_same( 'courtneyr-follow', $GLOBALS['cr_follow_enqueued'][0][1]['handle'] ?? '', 'editor handle' );
			cr_follow_assert( ! array_key_exists( 'path', $GLOBALS['cr_follow_enqueued'][0][1] ?? array() ), 'no path key' );
			$hook = cr_follow_hook( 'init', 'Courtneyr\Child\Follow\register_block_styles' );
			cr_follow_same( 20, $hook[2] ?? 0, 'init priority' );

			$GLOBALS['cr_follow_styles'] = array();
			$GLOBALS['cr_follow_registered']['post-kinds-indieweb/follow-entry'] = false;
			Follow\register_block_styles();
			$GLOBALS['cr_follow_registered']['post-kinds-indieweb/follow-entry'] = true;
			cr_follow_same( array(), $GLOBALS['cr_follow_styles'], 'no registration before the block exists' );
		}
	);

	cr_follow_check(
		'paper comes only from PAPERS and matches seeded_paper by post ID',
		static function (): void {
			cr_follow_same( array( 'printer-ivory', 'light-orange', 'light-gray', 'periwinkle' ), Follow\PAPERS, 'frozen paper list' );
			$seen = array();
			foreach ( range( 1, 200 ) as $id ) {
				$paper = Follow\paper( $id );
				cr_follow_same( \Courtneyr\Child\Stamps\seeded_paper( $id, Follow\PAPERS ), $paper, 'seeded paper for ' . $id );
				cr_follow_assert( in_array( $paper, Follow\PAPERS, true ), 'approved paper for ' . $id );
				$seen[ $paper ] = true;
			}
			cr_follow_same( count( Follow\PAPERS ), count( $seen ), 'all papers reachable' );
		}
	);

	cr_follow_check(
		'entry paint changes only styled entries with public facts',
		static function (): void {
			$id = 31;
			$GLOBALS['cr_follow_facts'][ $id ] = cr_follow_facts( array( 'post_id' => $id ) );
			$entry = '<article class="pkiw-follow-entry h-entry"><h2><a href="/">Amara</a></h2></article>';
			$out   = Follow\entry_paint( $entry, array( 'attrs' => array( 'className' => 'is-style-cr-rolodex' ) ), new WP_Block( array( 'postId' => $id ) ) );
			cr_follow_contains( 'style="--cr-follow-paper:var(--cr-' . Follow\paper( $id ) . ')"', $out, 'paper style' );
			cr_follow_same( 1, substr_count( $out, 'style=' ), 'one style attribute' );
			cr_follow_same( $entry, Follow\entry_paint( $entry, array( 'attrs' => array( 'className' => 'is-style-other' ) ), new WP_Block( array( 'postId' => $id ) ) ), 'other style unchanged' );
			cr_follow_same( $entry, Follow\entry_paint( $entry, array( 'attrs' => array( 'className' => 'is-style-cr-rolodex' ) ), new WP_Block( array( 'postId' => 999 ) ) ), 'empty facts unchanged' );
		}
	);

	cr_follow_check(
		'page size, enqueue surfaces, RUCSS and file-scope guards match C13',
		static function (): void {
			cr_follow_same( array( 'quote' => 8, 'follow' => 8 ), Follow\page_size( array( 'quote' => 8 ) ), 'page size filter' );
			$GLOBALS['cr_follow_enqueued'] = array();
			Follow\enqueue();
			cr_follow_same( array(), $GLOBALS['cr_follow_enqueued'], 'other pages do not load follow CSS' );
			$GLOBALS['cr_follow_archive'] = true;
			Follow\enqueue();
			$GLOBALS['cr_follow_archive'] = false;
			cr_follow_same( 'courtneyr-follow', $GLOBALS['cr_follow_enqueued'][0][0] ?? '', 'archive handle' );
			cr_follow_same( array( 'base.css', 'cr-follow.css', 'cr-xfn.css' ), Follow\rucss_exclusions( array( 'base.css' ) ), 'both RUCSS filenames' );

			$pruning = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/inc/stream-style-pruning.php' );
			cr_follow_contains( "\t'courtneyr-tag',\n\t'courtneyr-follow',\n", $pruning, 'inline handle' );
			$functions = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/functions.php' );
			cr_follow_contains( "require_once __DIR__ . '/inc/table.php';\nrequire_once __DIR__ . '/inc/follow.php';\nrequire_once __DIR__ . '/inc/single-follow.php';\nrequire_once __DIR__ . '/inc/stream-follow.php';\n", $functions, 'three requires after table' );

			$files = array( 'inc/follow.php', 'inc/single-follow.php', 'inc/stream-follow.php', 'templates/taxonomy-kind-follow.html', 'patterns/cr-follow-rolodex-loop.php' );
			foreach ( $files as $file ) {
				$code = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/' . $file );
				foreach ( array( 'kind-meta', 'cite_photo', 'wp:xfn/' ) as $banned ) {
					cr_follow_not_contains( $banned, $code, $file . ' privacy string' );
				}
			}
			foreach ( array( 'inc/follow.php', 'inc/single-follow.php', 'inc/stream-follow.php' ) as $file ) {
				cr_follow_not_contains( 'preg_', (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/' . $file ), $file . ' preg ratchet' );
			}
		}
	);

	cr_follow_finish();
}
