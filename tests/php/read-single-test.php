<?php
/**
 * Standalone checks for the read single adapter and shared helpers.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/read-bootstrap.php';

use function Courtneyr\Child\Journal\card_attrs;
use function Courtneyr\Child\SingleRead\add_title_lede;
use function Courtneyr\Child\SingleRead\book_cover;
use function Courtneyr\Child\SingleRead\date_pair;
use function Courtneyr\Child\SingleRead\journal_page;
use function Courtneyr\Child\SingleRead\rating_html;
use function Courtneyr\Child\SingleRead\rating_text;
use function Courtneyr\Child\SingleRead\read_attrs;
use function Courtneyr\Child\SingleRead\reading_record;
use function Courtneyr\Child\SingleRead\status_copy;

/** @return array<string, mixed> */
function cr_read_single_block( array $attrs = array() ): array {
	return array(
		'blockName' => 'post-kinds-indieweb/read-card',
		'attrs'     => array_merge(
			array(
				'bookTitle'    => 'Fixture Book',
				'authorName'   => 'A. Reader',
				'isbn'         => '9780000000000',
				'pageCount'    => 320,
				'currentPage'  => 80,
				'readStatus'   => 'reading',
				'rating'       => 3.5,
				'startedAt'    => '2026-09-14',
				'coverImage'   => 'https://cards.test/cover.jpg',
				'coverImageAlt'=> 'Stored card alt',
			),
			$attrs
		),
	);
}

function cr_read_single_card( bool $with_cover = true ): string {
	$cover = $with_cover ? '<div class="pk-media"><img class="pk-thumb--poster u-photo" src="https://cards.test/cover.jpg" alt="Stored card alt" loading="lazy"></div>' : '';
	return '<div class="entry-content"><article class="wp-block-post-kinds-indieweb-read-card pk-card k-read h-cite u-read-of">'
		. '<div class="pk-badge"></div><div class="pk-body"><span class="pk-kindlabel">Read</span><div class="pk-caption">'
		. '<h2 class="pk-title p-name">Fixture Book</h2><p class="pk-sub"><span class="p-author h-card"><span class="p-name">A. Reader</span></span></p>'
		. '<p class="pk-sub"><span>Currently Reading</span></p></div><div class="pk-progress"><div class="pk-progress-bar"></div></div>'
		. '<div class="pk-stars" role="img" aria-label="Rated 3.5 of 5"><svg></svg></div><data class="p-rating" value="3.5" hidden></data>'
		. $cover . '<div class="pk-note p-content"><p>Review text.</p></div><div class="pk-meta"><time datetime="2026-09-14">Started: September 14, 2026</time></div>'
		. '</div><data value="" hidden></data></article></div>';
}

cr_read_group(
	'status labels match the plugin',
	static function (): void {
		$expected = array(
			'reading'   => 'Currently Reading',
			'to-read'   => 'To Read',
			'finished'  => 'Finished',
			'abandoned' => 'Abandoned',
			'unknown'   => 'To Read',
		);
		foreach ( $expected as $status => $label ) {
			cr_read_same( array( $label, $label ), status_copy( $status ), $status );
		}
	}
);

cr_read_group(
	'calendar dates keep the stored day',
	static function (): void {
		cr_read_same( array( '2026-09-14', 'September 14, 2026' ), date_pair( '2026-09-14' ), 'valid date' );
		cr_read_same( array( '', '' ), date_pair( '' ), 'empty date' );
		cr_read_same( array( '', '' ), date_pair( 'nonsense' ), 'invalid date' );
	}
);

cr_read_group(
	'rating text keeps stored precision',
	static function (): void {
		cr_read_same( '5', rating_text( 5.0 ), 'five' );
		cr_read_same( '3.5', rating_text( 3.5 ), 'half' );
		cr_read_same( '', rating_text( 0.0 ), 'zero' );
		cr_read_same( '5', rating_text( 9.0 ), 'clamped' );
	}
);

cr_read_group(
	'read attributes preserve float ratings and defaults',
	static function (): void {
		$post = cr_read_reset();
		$a    = read_attrs( $post, cr_read_single_block() );
		cr_read_same( 3.5, $a['rating'], 'block float' );
		$GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_read_rating'] = '4.5';
		$block = cr_read_single_block( array( 'rating' => 0 ) );
		unset( $block['attrs']['rating'] );
		cr_read_same( 4.5, read_attrs( $post, $block )['rating'], 'meta float' );
		cr_read_same( 4.5, read_attrs( $post, cr_read_single_block( array( 'rating' => -1 ) ) )['rating'], 'negative block falls back to meta' );
		cr_read_same( 4.5, read_attrs( $post, cr_read_single_block( array( 'rating' => 'nope' ) ) )['rating'], 'non-numeric block falls back to meta' );
		cr_read_same( 4.5, read_attrs( $post, cr_read_single_block( array( 'rating' => 0 ) ) )['rating'], 'zero block falls back to meta' );
		cr_read_same( 3.5, read_attrs( $post, cr_read_single_block( array( 'rating' => 3.5 ) ) )['rating'], 'positive block wins over meta' );
		$GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_read_rating'] = '5';
		cr_read_same( 5.0, read_attrs( $post, $block )['rating'], 'meta five' );
		$GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_read_rating'] = '3.5';
		cr_read_same( 3.5, read_attrs( $post, $block )['rating'], 'meta half' );
		$GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_read_rating'] = '9';
		cr_read_same( 5.0, read_attrs( $post, $block )['rating'], 'meta clamp' );
		unset( $GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_read_rating'] );
		cr_read_same( 0.0, read_attrs( $post, $block )['rating'], 'missing rating' );
		cr_read_same( 0.0, read_attrs( $post, cr_read_single_block( array( 'rating' => -1 ) ) )['rating'], 'negative block without meta' );
		cr_read_same( 0.0, read_attrs( $post, cr_read_single_block( array( 'rating' => 'nope' ) ) )['rating'], 'non-numeric block without meta' );
		cr_read_same( 'reading', read_attrs( $post, cr_read_single_block( array( 'readStatus' => 'other' ) ) )['readStatus'], 'invalid status' );
		$missing = cr_read_single_block();
		unset( $missing['attrs']['readStatus'] );
		cr_read_same( 'reading', read_attrs( $post, $missing )['readStatus'], 'missing status' );
	}
);

cr_read_group(
	'card_attrs keeps read ratings fractional and leaves watch ratings alone',
	static function (): void {
		$post = cr_read_reset();
		$GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_read_rating'] = '3.5';
		cr_read_same( 3.5, card_attrs( $post, 'post-kinds-indieweb/read-card', array() )['rating'], 'read meta half' );
		$GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_read_rating'] = '5';
		cr_read_same( 5.0, card_attrs( $post, 'post-kinds-indieweb/read-card', array() )['rating'], 'read meta five' );
		unset( $GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_read_rating'] );
		cr_read_same( 4.5, card_attrs( $post, 'post-kinds-indieweb/read-card', array( 'rating' => 4.5 ) )['rating'], 'read block half' );
		$GLOBALS['cr_read_meta'][ $post->ID ]['_pkiw_watch_rating'] = '3.5';
		cr_read_same( 3, card_attrs( $post, 'post-kinds-indieweb/watch-card', array() )['rating'], 'watch meta integer' );
		cr_read_same( 'nope', card_attrs( $post, 'post-kinds-indieweb/watch-card', array( 'rating' => 'nope' ) )['rating'], 'watch non-numeric block stays filled' );
	}
);

cr_read_group(
	'rating markup renders fractional stars',
	static function (): void {
		$html = rating_html( 3.5 );
		cr_read_contains( 'aria-label="Rated 3.5 of 5"', $html, 'label' );
		cr_read_contains( '<span class="cr-journal__rating-value">3.5 / 5</span>', $html, 'value' );
		cr_read_same( 3, substr_count( $html, '<span class="cr-journal__star">' ), 'full stars' );
		cr_read_same( 1, substr_count( $html, 'cr-journal__star cr-journal__star--half' ), 'half star' );
		cr_read_same( 1, substr_count( $html, 'cr-journal__star cr-journal__star--off' ), 'empty star' );
		cr_read_contains( '>5 / 5</span>', rating_html( 5.0 ), 'five value' );
		cr_read_same( '', rating_html( 0.0 ), 'zero markup' );
	}
);

cr_read_group(
	'book covers use the featured-first picture and alt order',
	static function (): void {
		$post = cr_read_reset();
		$a    = cr_read_single_block()['attrs'];
		$GLOBALS['cr_read_attachments'][71] = 'https://media.test/featured.jpg';
		$GLOBALS['cr_read_pictures'][42]    = array( 'source' => 'featured', 'attachment_id' => 71, 'url' => 'https://media.test/featured.jpg', 'alt' => 'Picture alt' );
		$a_with_block_alt = array_merge( $a, array( 'coverImageAlt' => 'Block alt' ) );
		$a_without_alt    = array_merge( $a, array( 'coverImageAlt' => '' ) );
		$html             = book_cover( $post, $a_with_block_alt, cr_read_single_block( array( 'coverImageAlt' => 'Block alt' ) ), 'large' );
		cr_read_contains( 'cr-book__cover--featured', $html, 'featured class' );
		cr_read_contains( 'class="cr-book__img u-photo"', $html, 'image class' );
		cr_read_contains( 'alt="Block alt"', $html, 'block alt' );
		$html = book_cover( $post, $a_with_block_alt, array(), 'large' );
		cr_read_contains( 'alt="Block alt"', $html, 'resolved block alt' );
		$html = book_cover( $post, $a_without_alt, cr_read_single_block( array( 'coverImageAlt' => '' ) ), 'large' );
		cr_read_contains( 'alt="Picture alt"', $html, 'picture alt' );
		$GLOBALS['cr_read_pictures'][42]['alt'] = '';
		$html = book_cover( $post, $a_without_alt, cr_read_single_block( array( 'coverImageAlt' => '' ) ), 'large' );
		cr_read_contains( 'alt="Cover of Fixture Book"', $html, 'fallback alt' );
		$GLOBALS['cr_read_pictures'][42] = array( 'source' => 'cover', 'attachment_id' => 0, 'url' => 'https://remote.test/cover.jpg', 'alt' => '' );
		cr_read_contains( '<img class="cr-book__img u-photo" src="https://remote.test/cover.jpg"', book_cover( $post, $a_without_alt, cr_read_single_block( array( 'coverImageAlt' => '' ) ), 'large' ), 'remote cover' );
		$GLOBALS['cr_read_pictures'][42] = array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => '' );
		$html = book_cover( $post, $a, cr_read_single_block(), 'large' );
		cr_read_contains( 'cr-book__cover--type" aria-hidden="true"', $html, 'type cover' );
		cr_read_contains( '>Fixture Book</span><span class="cr-book__type-author">A. Reader</span>', $html, 'type text' );
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/inc/single-read.php' );
		cr_read_contains( "function_exists( '\\\\PKIW\\\\kind_picture' )", (string) $source, 'undefined helper guard' );
	}
);

cr_read_group(
	'title lede prints one precise rating and calendar date',
	static function (): void {
		cr_read_reset();
		$GLOBALS['cr_read_blocks'] = array( cr_read_single_block() );
		$html = add_title_lede( '<h1>Reading now</h1>', array( 'blockName' => 'core/post-title' ) );
		cr_read_contains( 'Currently Reading', $html, 'status' );
		cr_read_contains( 'since', $html, 'since' );
		cr_read_contains( '<time datetime="2026-09-14">September 14, 2026</time>', $html, 'date' );
		cr_read_same( 1, substr_count( $html, 'Rated 3.5 of 5' ), 'rating label count' );
		cr_read_same( 1, substr_count( $html, '3.5 / 5' ), 'rating value count' );
		cr_read_not_contains( 'Set aside', $html, 'old copy' );
	}
);

cr_read_group(
	'abandoned reading record uses the plugin word and calendar dates',
	static function (): void {
		$post  = cr_read_reset();
		$block = cr_read_single_block( array( 'readStatus' => 'abandoned', 'finishedAt' => '2026-09-20' ) );
		$html  = reading_record( $block, $post );
		cr_read_contains( '<dt>Abandoned</dt>', $html, 'row label' );
		cr_read_not_contains( 'Set aside', $html, 'old row label' );
		cr_read_contains( '<time datetime="2026-09-14">September 14, 2026</time>', $html, 'started date' );
		cr_read_contains( '<time datetime="2026-09-20">September 20, 2026</time>', $html, 'finished date' );
		cr_read_not_contains( 'cr-journal__rating', $html, 'rating absent' );
		cr_read_not_contains( 'cr-read__status', $html, 'status row absent' );
	}
);

cr_read_group(
	'reading record stamp uses the calendar day the rows print',
	static function (): void {
		$post  = cr_read_reset();
		$block = cr_read_single_block(
			array(
				'readStatus' => 'finished',
				'startedAt'  => '2026-09-01',
				'finishedAt' => '2026-09-14T00:30:00+14:00',
			)
		);
		$html  = reading_record( $block, $post );
		cr_read_contains( '<time datetime="2026-09-14">', $html, 'positive offset row date' );
		cr_read_contains( '<text x="62" y="74" class="cr-stamp__small">14 SEP 2026</text>', $html, 'positive offset finished stamp' );
		cr_read_not_contains( '<text x="62" y="74" class="cr-stamp__small">13 SEP 2026</text>', $html, 'shifted positive offset stamp' );

		$post  = cr_read_reset();
		$block = cr_read_single_block(
			array(
				'readStatus' => 'finished',
				'finishedAt' => '2026-09-14 23:30:00-05:00',
			)
		);
		$html  = reading_record( $block, $post );
		cr_read_contains( '<text x="62" y="74" class="cr-stamp__small">14 SEP 2026</text>', $html, 'negative offset finished stamp' );

		$post  = cr_read_reset();
		$block = cr_read_single_block(
			array(
				'readStatus' => 'abandoned',
				'finishedAt' => '2026-09-14T00:30:00+14:00',
			)
		);
		$html  = reading_record( $block, $post );
		cr_read_contains( '<text x="62" y="74" class="cr-stamp__small">14 SEP 2026</text>', $html, 'abandoned stamp' );

		$post  = cr_read_reset();
		$block = cr_read_single_block(
			array(
				'readStatus' => 'reading',
				'startedAt'  => '2026-09-14T00:30:00+14:00',
				'finishedAt' => '',
			)
		);
		$html  = reading_record( $block, $post );
		cr_read_contains( '<text x="62" y="74" class="cr-stamp__small">14 SEP 2026</text>', $html, 'started stamp' );

		$post  = cr_read_reset();
		$block = cr_read_single_block(
			array(
				'readStatus' => 'finished',
				'finishedAt' => '2026-09-14',
			)
		);
		$html  = reading_record( $block, $post );
		cr_read_contains( '<text x="62" y="74" class="cr-stamp__small">14 SEP 2026</text>', $html, 'bare finished stamp' );
	}
);

cr_read_group(
	'journal page removes slogans and uses one featured-first cover',
	static function (): void {
		foreach ( array( true, false ) as $with_card_cover ) {
			$post                       = cr_read_reset();
			$block                      = cr_read_single_block();
			$GLOBALS['cr_read_blocks']  = array( $block );
			$GLOBALS['cr_read_pictures'][42] = $with_card_cover
				? array( 'source' => 'featured', 'attachment_id' => 81, 'url' => 'https://media.test/featured-first.jpg', 'alt' => 'Featured alt' )
				: array( 'source' => 'cover', 'attachment_id' => 0, 'url' => 'https://media.test/plugin-cover.jpg', 'alt' => 'Plugin alt' );
			$GLOBALS['cr_read_attachments'][81] = 'https://media.test/featured-first.jpg';
			$html = journal_page( cr_read_single_card( $with_card_cover ), array( 'blockName' => 'core/post-content' ) );
			cr_read_not_contains( 'Good books', $html, 'first slogan' );
			cr_read_not_contains( 'Pages over pixels', $html, 'second slogan' );
			cr_read_not_contains( 'peek inside', $html, 'sample slogan' );
			cr_read_not_contains( 'cr-journal__margin', $html, 'margin' );
			cr_read_not_contains( 'pk-stars', $html, 'plugin stars' );
			cr_read_contains( 'p-rating', $html, 'machine rating' );
			cr_read_same( 1, substr_count( $html, '<img' ), 'cover image count' );
			$expected_src = $with_card_cover ? 'https://media.test/featured-first.jpg' : 'https://media.test/plugin-cover.jpg';
			cr_read_contains( 'src="' . $expected_src . '"', $html, 'plugin picture' );
			cr_read_assert( 1 === preg_match( '/<div class="pk-media[^>]*>.*cr-book__ribbon.*<\/div>/sU', $html ), 'ribbon is inside cover' );
			cr_read_same( 1, substr_count( $html, 'class="cr-journal__meta-item ' ), 'meta item count' );
			cr_read_not_contains( 'cr-journal__meta-item--book', $html, 'book meta absent' );
			$footer = preg_match( '/<footer class="cr-journal__meta">.*?<\/footer>/s', $html, $m ) ? $m[0] : '';
			cr_read_not_contains( 'Currently Reading', $footer, 'footer status absent' );
		}
	}
);

cr_read_finish();
