<?php
/**
 * Standalone checks for media shelf link names.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/read-bootstrap.php';

use function Courtneyr\Child\MediaShelf\name_listen_date;
use function Courtneyr\Child\MediaShelf\name_watch_date;

function cr_media_shelf_item( string $title, string $extra_link_attrs = '', string $classes = 'post-123 kind-watch' ): string {
	return '<li class="' . $classes . '"><article class="pk-card"><h3 class="pk-title p-name"><a' . $extra_link_attrs . ' class="u-url" href="https://example.test/dune/">' . $title . '</a></h3></article><p class="pk-entry-props"><time class="dt-published" value="2026-05-04">May 4, 2026</time></p></li>';
}

cr_read_group(
	'watch shelf link names date without duplicating the title',
	static function (): void {
		$GLOBALS['cr_read_post']                = new WP_Post( 123, 'Dune', '' );
		$GLOBALS['cr_read_dates'][123]['text'] = 'May 4, 2026';

		$out = name_watch_date( cr_media_shelf_item( 'Dune' ) );

		cr_read_contains( 'aria-label="Dune, watched May 4, 2026"', $out, 'watch title link gets the date label' );
		cr_read_same( 1, substr_count( wp_strip_all_tags( $out ), 'Dune' ), 'Dune appears once in text content' );
		foreach ( array( 'aria-hidden', 'cr-sr-only', 'value-title' ) as $gone ) {
			cr_read_not_contains( $gone, $out, $gone . ' absent' );
		}
		cr_read_contains( '>Dune</a>', $out, 'title link inner markup is unchanged' );
	}
);

cr_read_group(
	'watch shelf label keeps one encoded ampersand',
	static function (): void {
		$GLOBALS['cr_read_post']                = new WP_Post( 123, 'Tom & Jerry', '' );
		$GLOBALS['cr_read_dates'][123]['text'] = 'May 4, 2026';

		$out = name_watch_date( cr_media_shelf_item( 'Tom &amp; Jerry' ) );

		cr_read_contains( 'aria-label="Tom &amp; Jerry, watched May 4, 2026"', $out, 'ampersand is encoded once' );
		cr_read_not_contains( 'Tom &amp;amp; Jerry', $out, 'ampersand is not double encoded' );
	}
);

cr_read_group(
	'watch shelf early returns keep unchanged items',
	static function (): void {
		$GLOBALS['cr_read_post']                = new WP_Post( 123, 'Dune', '' );
		$GLOBALS['cr_read_dates'][123]['text'] = 'May 4, 2026';

		$without_post_class = cr_media_shelf_item( 'Dune', '', 'kind-watch' );
		cr_read_same( $without_post_class, name_watch_date( $without_post_class ), 'item without post id is unchanged' );

		$already_labelled = cr_media_shelf_item( 'Dune', ' aria-label="Existing label"' );
		cr_read_same( $already_labelled, name_watch_date( $already_labelled ), 'existing aria-label is unchanged' );
	}
);

cr_read_group(
	'listen shelf link names use listened wording',
	static function (): void {
		$GLOBALS['cr_read_post']                = new WP_Post( 123, 'Dune', '' );
		$GLOBALS['cr_read_dates'][123]['text'] = 'May 4, 2026';

		$out = name_listen_date( cr_media_shelf_item( 'Dune', '', 'post-123 kind-listen' ) );

		cr_read_contains( 'aria-label="Dune, listened May 4, 2026"', $out, 'listen title link gets listened date label' );
		cr_read_same( 1, substr_count( wp_strip_all_tags( $out ), 'Dune' ), 'Dune appears once in listen text content' );
	}
);

cr_read_finish();
