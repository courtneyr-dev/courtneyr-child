<?php
/**
 * `php tests/php/tag-privacy-test.php`: the theme never widens the plugin's
 * public-eligibility boundary for tag posts (A-T4, spec-261 C1 and S1).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/tag-bootstrap.php';

	const CR_TAG_SENTINEL_NAME = 'Tg261 Sentinel X1';
	const CR_TAG_SENTINEL_URL  = 'https://tg261-x1.example.net/';
	const CR_TAG_SENTINEL_TAG  = 'tg261-label-x1';

	$GLOBALS['cr_tag_hidden']       = array();
	$GLOBALS['cr_tag_series_posts'] = array();
	$GLOBALS['cr_tag_get_posts']    = array();

	function get_taxonomy( $taxonomy ) {
		return (object) array( 'object_type' => array( 'post' ) );
	}

	function post_type_exists( $type ): bool {
		return true;
	}

	function get_posts( $args = array() ): array {
		$GLOBALS['cr_tag_get_posts'][] = $args;
		return $GLOBALS['cr_tag_series_posts'];
	}

	function update_meta_cache( $type, $ids ): void {
	}

	function get_post_meta( $id, $key = '', $single = false ) {
		return '';
	}
}

namespace PKIW {
	/** Predicate stand-in: hides the IDs a test lists. */
	final class Public_Eligibility {
		/** @param array<int|string> $ids Post IDs. */
		public static function filter_ids( array $ids ): array {
			return array_values( array_filter( $ids, static fn( $id ): bool => ! in_array( (int) $id, $GLOBALS['cr_tag_hidden'], true ) ) );
		}
	}
}

namespace {
	require CR_TAG_THEME_DIR . '/inc/stamps.php';
	require CR_TAG_THEME_DIR . '/inc/tag.php';
	require CR_TAG_THEME_DIR . '/inc/single-tag.php';
	require CR_TAG_THEME_DIR . '/inc/stream-tag.php';
	require CR_TAG_THEME_DIR . '/inc/series.php';

	use Courtneyr\Child\SingleTag;
	use Courtneyr\Child\StreamTag;
	use Courtneyr\Child\Tag;

	/** A plugin card for an ineligible person tag: labels only, no person. */
	const CR_TAG_HIDDEN_CARD = '<article class="pk-card k-tag pkiw-tag-card pkiw-tag-card--person"><div class="pk-body"><span class="pk-kindlabel">Tag</span><p class="pkiw-tag-card__labels"><span class="p-category">tg261-label-x1</span></p></div></article>';

	cr_tag_check(
		'every adapter returns the plugin markup unchanged when the plugin gives no facts',
		static function (): void {
			$GLOBALS['cr_tag_facts']    = array();
			$GLOBALS['cr_tag_singular'] = true;
			$GLOBALS['cr_tag_stream']   = true;
			foreach ( array( 601, 602, 603, 604, 605, 606 ) as $id ) {
				$GLOBALS['cr_tag_queried'] = $id;
				$block                     = new WP_Block( array( 'postId' => $id ) );
				cr_tag_same( CR_TAG_HIDDEN_CARD, SingleTag\clipping( CR_TAG_HIDDEN_CARD, array( 'attrs' => array( 'title' => CR_TAG_SENTINEL_NAME, 'url' => CR_TAG_SENTINEL_URL ) ), $block ), 'single for ' . $id );
				$stream = '<article class="pk-card pk-card--stream k-tag h-entry"><h2 class="pk-title"><a class="u-url" href="https://example.test/tag-' . $id . '/">Private</a></h2></article>';
				cr_tag_same( $stream, StreamTag\name_tag_card( $stream, array(), $block ), 'Stream for ' . $id );
				$entry = '<article class="pkiw-tag-entry">x</article>';
				cr_tag_same( $entry, Tag\entry_paint( $entry, array( 'attrs' => array( 'className' => 'is-style-cr-name-tag-sheet' ) ), $block ), 'archive for ' . $id );
			}
		}
	);

	cr_tag_check(
		'the single builds from facts only: block attributes never reach the markup',
		static function (): void {
			$GLOBALS['cr_tag_facts'][610] = cr_tag_facts(
				array(
					'target_type' => 'person',
					'title'       => 'Example Person One',
					'url'         => 'https://person-one.example.net/',
					'url_host'    => 'person-one.example.net',
					'labels'      => array( 'People' ),
				)
			);
			$GLOBALS['cr_tag_singular']   = true;
			$GLOBALS['cr_tag_queried']    = 610;
			$attrs                        = array(
				'targetType' => 'person',
				'title'      => CR_TAG_SENTINEL_NAME,
				'url'        => CR_TAG_SENTINEL_URL,
				'labels'     => array( CR_TAG_SENTINEL_TAG ),
			);
			$html                         = SingleTag\clipping( '<article>card</article>', array( 'attrs' => $attrs ), new WP_Block( array( 'postId' => 610 ) ) );
			foreach ( array( CR_TAG_SENTINEL_NAME, CR_TAG_SENTINEL_URL, CR_TAG_SENTINEL_TAG ) as $sentinel ) {
				cr_tag_not_contains( $sentinel, $html, 'attribute sentinel' );
			}
			cr_tag_contains( 'Example Person One', $html, 'facts name prints' );
		}
	);

	cr_tag_check(
		'tag theme files read no post meta, block attributes or Micropub rows',
		static function (): void {
			foreach ( array( 'inc/tag.php', 'inc/single-tag.php', 'inc/stream-tag.php' ) as $file ) {
				$code = (string) file_get_contents( CR_TAG_THEME_DIR . '/' . $file );
				foreach ( array( 'get_post_meta', '_pkiw_tag_label', '_pkiw_cite_', 'mf2_', 'micropub_get_mf2', 'parse_blocks', 'update_post_meta', 'add_post_meta', 'wp_set_object_terms', "['attrs']['title']", "['attrs']['url']" ) as $banned ) {
					cr_tag_not_contains( $banned, $code, $file . ' ' . $banned );
				}
				cr_tag_contains( '\\PKIW\\kind_facts', (string) file_get_contents( CR_TAG_THEME_DIR . '/inc/tag.php' ), 'facts come from the plugin' );
			}
		}
	);

	cr_tag_check(
		'series part lists drop hidden tag posts through Public_Eligibility::filter_ids()',
		static function (): void {
			$GLOBALS['cr_tag_series_posts'] = array( 701, 702, 703 );
			$GLOBALS['cr_tag_hidden']       = array( 702 );
			$rows                           = \Courtneyr\Child\Series\parts( 41 );
			cr_tag_same( array( 701, 703 ), array_column( $rows, 'id' ), 'password-protected part dropped' );
			cr_tag_same( true, $GLOBALS['cr_tag_get_posts'][0]['suppress_filters'] ?? null, 'the query still suppresses filters, so the predicate is the guard' );

			$GLOBALS['cr_tag_hidden'] = array();
			$rows                     = \Courtneyr\Child\Series\parts( 42 );
			cr_tag_same( array( 701, 702, 703 ), array_column( $rows, 'id' ), 'no hidden posts: list unchanged' );
		}
	);

	cr_tag_finish();
}
