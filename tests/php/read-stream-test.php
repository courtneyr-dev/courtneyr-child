<?php
/**
 * Standalone checks for the read Stream adapter.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/read-bootstrap.php';

use function Courtneyr\Child\StreamRead\book_card;
use function Courtneyr\Child\StreamRead\facts_line;
use function Courtneyr\Child\StreamRead\with_rating_value;

/** @return array<string, mixed> */
function cr_read_stream_read_block( array $attrs = array() ): array {
	return array(
		'blockName' => 'post-kinds-indieweb/read-card',
		'attrs'     => array_merge(
			array(
				'bookTitle'   => 'Stream Book',
				'authorName'  => 'S. Author',
				'isbn'        => '9781111111111',
				'pageCount'   => 240,
				'currentPage' => 120,
				'readStatus'  => 'finished',
				'rating'      => 3.5,
				'startedAt'   => '2026-09-01',
				'finishedAt'  => '2026-09-14',
				'coverImage'  => 'https://cards.test/stream-cover.jpg',
			),
			$attrs
		),
	);
}

function cr_read_stream_card(): string {
	return '<article class="wp-block-post-kinds-indieweb-read-card pk-card k-read h-cite u-read-of"><div class="pk-badge"></div><div class="pk-body">'
		. '<span class="pk-kindlabel">Read</span><div class="pk-caption"><h2 class="pk-title p-name"><a href="https://example.test/book">Stream Book</a></h2>'
		. '<p class="pk-sub"><span class="p-author h-card"><span class="p-name">S. Author</span></span></p><p class="pk-sub"><span>Finished</span></p></div>'
		. '<div class="pk-progress"><div class="pk-progress-bar"><div class="pk-progress-fill"></div></div><span class="pk-progress-text">120 of 240 pages</span></div>'
		. '<div class="pk-stars" role="img" aria-label="Rated 3.5 of 5"><svg></svg></div><data class="p-rating" value="3.5" hidden></data>'
		. '<div class="pk-media"><img class="pk-thumb--poster u-photo" src="https://cards.test/stream-cover.jpg" alt="Card cover" loading="lazy"></div>'
		. '<div class="pk-note p-content"><p>Kept review.</p></div><div class="pk-meta"><time class="dt-published" datetime="2026-09-14">Finished: September 14, 2026</time></div>'
		. '</div><data value="" hidden></data></article>';
}

cr_read_group(
	'rating value ignores star attribute order',
	static function (): void {
		$stars = '<div class="pk-stars" role="img" aria-label="Rated 3.5 of 5"><svg></svg></div>';
		$html  = with_rating_value( $stars, 3.5 );
		cr_read_same( '<div class="pk-stars" role="img" aria-label="Rated 3.5 of 5"><svg></svg><span class="pk-rating-value">3.5 / 5</span></div>', $html, 'fractional value' );
		cr_read_contains( '<span class="pk-rating-value">5 / 5</span></div>', with_rating_value( $stars, 5.0 ), 'whole value' );
		cr_read_same( $stars, with_rating_value( $stars, 0.0 ), 'zero unchanged' );
		$plain = '<article>No stars</article>';
		cr_read_same( $plain, with_rating_value( $plain, 3.5 ), 'missing stars unchanged' );
	}
);

cr_read_group(
	'facts line uses status, pages, and calendar dates',
	static function (): void {
		list( $facts, $ts ) = facts_line( array( 'pageCount' => 240, 'currentPage' => 120 ), 'reading', 'Currently Reading' );
		cr_read_same( 'Currently Reading · page 120 of 240', $facts, 'reading progress' );
		cr_read_same( 0, $ts, 'reading timestamp' );
		list( $facts ) = facts_line( array(), 'reading', 'Currently Reading' );
		cr_read_same( 'Currently Reading', $facts, 'reading without pages' );
		list( $facts, $ts ) = facts_line( array( 'pageCount' => 240, 'finishedAt' => '2026-09-14' ), 'finished', 'Finished' );
		cr_read_same( 'Finished · 240 pages · September 14, 2026', $facts, 'finished' );
		cr_read_same( gmmktime( 12, 0, 0, 9, 14, 2026 ), $ts, 'bare finished date timestamp' );
		cr_read_not_contains( 'September 13', $facts, 'timezone shift' );
		foreach ( array( '2026-09-14T08:00:00-04:00', '2026-09-14 08:00:00' ) as $finished_at ) {
			list( $facts, $ts ) = facts_line( array( 'finishedAt' => $finished_at ), 'finished', 'Finished' );
			cr_read_same( 'Finished · September 14, 2026', $facts, 'finished datetime facts ' . $finished_at );
			cr_read_same( gmmktime( 12, 0, 0, 9, 14, 2026 ), $ts, 'finished datetime timestamp ' . $finished_at );
		}
		list( $facts, $ts ) = facts_line( array( 'finishedAt' => '2026-09-14' ), 'abandoned', 'Abandoned' );
		cr_read_same( 'Abandoned · September 14, 2026', $facts, 'abandoned' );
		cr_read_same( gmmktime( 12, 0, 0, 9, 14, 2026 ), $ts, 'abandoned timestamp' );
		list( $facts, $ts ) = facts_line( array( 'finishedAt' => '2026-09-14T08:00:00-04:00' ), 'abandoned', 'Abandoned' );
		cr_read_same( 'Abandoned · September 14, 2026', $facts, 'abandoned datetime facts' );
		cr_read_same( gmmktime( 12, 0, 0, 9, 14, 2026 ), $ts, 'abandoned datetime timestamp' );
		list( $facts, $ts ) = facts_line( array( 'pageCount' => 240 ), 'to-read', 'To Read' );
		cr_read_same( 'To Read · 240 pages', $facts, 'to-read pages' );
		cr_read_same( 0, $ts, 'to-read timestamp' );
	}
);

cr_read_group(
	'Stream card keeps content while replacing progress and cover',
	static function (): void {
		$post                              = cr_read_reset();
		$GLOBALS['cr_read_blocks']         = array( cr_read_stream_read_block() );
		$GLOBALS['cr_read_pictures'][42]   = array( 'source' => 'featured', 'attachment_id' => 91, 'url' => 'https://media.test/stream-featured.jpg', 'alt' => 'Featured Stream' );
		$GLOBALS['cr_read_attachments'][91] = 'https://media.test/stream-featured.jpg';
		$input = cr_read_stream_card();
		$html  = book_card( $input, array( 'attrs' => array() ), new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_read_not_contains( 'cr-book__hand', $html, 'slogan absent' );
		cr_read_not_contains( 'pk-progress', $html, 'progress absent' );
		cr_read_contains( 'pk-note', $html, 'review kept' );
		cr_read_contains( '<span class="pk-rating-value">3.5 / 5</span>', $html, 'rating value' );
		cr_read_contains( '<span class="cr-book__tab cr-book__tab--finished" aria-hidden="true">Finished</span>', $html, 'status tab' );
		cr_read_same( 1, substr_count( $html, 'class="cr-book__stamp"' ), 'one stamp' );
		cr_read_same( 1, substr_count( $html, '<img' ), 'one cover image' );
		cr_read_contains( 'src="https://media.test/stream-featured.jpg"', $html, 'featured-first cover' );
		cr_read_contains( 'p-rating', $html, 'machine rating kept' );
	}
);

cr_read_group(
	'archive style and non-Stream surface return byte-for-byte input',
	static function (): void {
		$post                      = cr_read_reset();
		$GLOBALS['cr_read_blocks'] = array( cr_read_stream_read_block() );
		$input                     = cr_read_stream_card();
		$instance                  = new WP_Block( array( 'postId' => $post->ID ) );
		cr_read_same( $input, book_card( $input, array( 'attrs' => array( 'className' => 'x is-style-cr-shelf-book y' ) ), $instance ), 'archive-owned card' );
		$GLOBALS['cr_read_stream'] = false;
		cr_read_same( $input, book_card( $input, array( 'attrs' => array() ), $instance ), 'non-Stream card' );
	}
);

cr_read_finish();
