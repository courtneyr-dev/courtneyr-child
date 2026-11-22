<?php
/**
 * Prints the Read single journal markup as JSON for tests/browser/read-single-record.mjs.
 *
 * It runs the theme's own journal_page() filter over a read card in the shape the plugin emits,
 * so the browser test measures real markup, not a hand-written copy.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require dirname( __DIR__ ) . '/php/read-bootstrap.php';

use function Courtneyr\Child\SingleRead\journal_page;

/**
 * One rendered journal page.
 *
 * @param array<string, mixed> $attrs  Read card attributes.
 * @param bool                 $notes  Whether the post has a review.
 * @return string
 */
function cr_read_fixture_journal( array $attrs, bool $notes ): string {
	cr_read_reset();
	$GLOBALS['cr_read_blocks'] = array(
		array(
			'blockName' => 'post-kinds-indieweb/read-card',
			'attrs'     => array_merge(
				array(
					'bookTitle'     => 'A Treatise on Copper Kettles',
					'authorName'    => 'Rafferty Quill',
					'isbn'          => '9781234567897',
					'publisher'     => 'Brindle & Moss Press',
					'publishDate'   => '2019-05-14',
					'pageCount'     => 320,
					'readStatus'    => 'finished',
					'startedAt'     => '2026-07-30',
					'finishedAt'    => '2026-08-12',
					'rating'        => 4,
				),
				$attrs
			),
		),
	);
	$review = $notes ? '<div class="pk-note p-content"><p>The kettle chapters keep returning to one idea: a pot that holds heat tells you how it was made. I read the plates twice and copied three of the tinning recipes into my notebook.</p><p>The index is thin, so I added my own.</p></div>' : '';
	$card   = '<div class="entry-content"><article class="wp-block-post-kinds-indieweb-read-card pk-card k-read h-cite u-read-of">'
		. '<div class="pk-badge"></div><div class="pk-body"><span class="pk-kindlabel">Read</span><div class="pk-caption">'
		. '<h2 class="pk-title p-name">A Treatise on Copper Kettles</h2><p class="pk-sub"><span class="p-author h-card"><span class="p-name">Rafferty Quill</span></span></p>'
		. '<p class="pk-sub"><span>Finished</span></p></div><div class="pk-progress"><div class="pk-progress-bar"></div></div>'
		. '<div class="pk-stars" role="img" aria-label="Rated 4 of 5"><svg></svg></div><data class="p-rating" value="4" hidden></data>'
		. $review . '<div class="pk-meta"><time datetime="2026-08-12">Finished: August 12, 2026</time></div>'
		. '</div><data value="" hidden></data></article></div>';
	return journal_page( $card, array( 'blockName' => 'core/post-content' ) );
}

echo json_encode(
	array(
		'finished' => cr_read_fixture_journal( array(), true ),
		'reading'  => cr_read_fixture_journal(
			array(
				'readStatus'  => 'reading',
				'currentPage' => 88,
				'finishedAt'  => '',
			),
			false
		),
	),
	JSON_THROW_ON_ERROR
);
