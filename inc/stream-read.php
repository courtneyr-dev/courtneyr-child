<?php
/**
 * Stream read cards as small physical books on a library slip.
 *
 * The plugin's read card stays (title link, date, author, status,
 * stars, cover, review); this file adds only what the object needs:
 * classes for the book shell, a status tab hung on the cover (the
 * status text the card already prints), the rating as a number beside
 * the stars, a mono facts line, and a dated stamp for finished or
 * abandoned books. cr-post-kinds.css paints the spine, page block,
 * perspective and slip. Nothing here touches
 * single read posts or other kinds.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamRead;

use function Courtneyr\Child\MediaShelf\cut_elements;
use function Courtneyr\Child\SingleRead\book_cover;
use function Courtneyr\Child\SingleRead\date_pair;
use function Courtneyr\Child\SingleRead\rating_text;
use function Courtneyr\Child\SingleRead\read_attrs;
use function Courtneyr\Child\SingleRead\status_copy;
use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\render;
use function Courtneyr\Child\Stamps\seed;
use const Courtneyr\Child\Stamps\INKS;
use const Courtneyr\Child\Stamps\TILTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The read-card block in a post's content.
 *
 * @param \WP_Post $post Post.
 * @return array<string, mixed>|null
 */
function find_read_block( \WP_Post $post ): ?array {
	$found = null;
	$walk  = static function ( array $blocks ) use ( &$walk, &$found ): void {
		foreach ( $blocks as $block ) {
			if ( null !== $found ) {
				return;
			}
			if ( 'post-kinds-indieweb/read-card' === ( $block['blockName'] ?? '' ) ) {
				$found = $block;
				return;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ) );
	return $found;
}

/**
 * Put the stored rating number inside the plugin's stars element.
 *
 * @param string $html   Card HTML.
 * @param float  $rating Stored rating.
 * @return string
 */
function with_rating_value( string $html, float $rating ): string {
	$value = rating_text( $rating );
	if ( '' === $value ) {
		return $html;
	}
	$start = strpos( $html, '<div class="pk-stars' );
	$end   = false !== $start ? strpos( $html, '</div>', $start ) : false;
	if ( false === $end ) {
		return $html;
	}
	return substr( $html, 0, $end ) . '<span class="pk-rating-value">' . esc_html( $value . ' / 5' ) . '</span>' . substr( $html, $end );
}

/**
 * Visible read facts and the finished-day timestamp used by the stamp.
 *
 * @param array<string, mixed> $a      Resolved attributes.
 * @param string               $status Read status.
 * @param string               $label  Plugin status label.
 * @return array{0: string, 1: int}
 */
function facts_line( array $a, string $status, string $label ): array {
	$facts   = array( $label );
	$pages   = (int) ( $a['pageCount'] ?? 0 );
	$current = (int) ( $a['currentPage'] ?? 0 );
	if ( 'reading' === $status && $pages > 0 && $current > 0 ) {
		$facts[] = sprintf( /* translators: 1: page, 2: pages */ __( 'page %1$d of %2$d', 'courtneyr-child' ), $current, $pages );
	} elseif ( $pages > 0 ) {
		$facts[] = sprintf( /* translators: %d: pages */ __( '%d pages', 'courtneyr-child' ), $pages );
	}
	$done_ts = 0;
	if ( in_array( $status, array( 'finished', 'abandoned' ), true ) ) {
		list( $machine, $display ) = date_pair( (string) ( $a['finishedAt'] ?? '' ) );
		if ( '' !== $machine ) {
			$facts[] = $display;
			$done_ts = (int) strtotime( (string) $a['finishedAt'] . ' 12:00:00 UTC' );
		}
	}
	return array( implode( ' · ', $facts ), $done_ts );
}

/**
 * Dress a read stream card as a book.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block.
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function book_card( string $html, array $block, $instance ): string {
	if ( ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return $html;
	}
	if ( str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'is-style-cr-shelf-book' ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'read', 'kind', $post ) ) {
		return $html;
	}
	$read = find_read_block( $post );
	if ( null === $read ) {
		return $html;
	}

	// A read whose body holds more than the card (an empty paragraph, a
	// Kindle block) gets the plugin's generic stream card. Render the
	// read card itself instead, with the plugin's own stream helpers for
	// the title link and the date, so the book is the card.
	if ( false === strpos( $html, 'pk-card k-read' ) ) {
		$card = render_block( $read );
		if ( '' === $card || false === strpos( $card, 'pk-card k-read' ) ) {
			return $html;
		}
		if ( function_exists( '\\PKIW\\link_title_to_post' ) ) {
			$card = \PKIW\link_title_to_post( $card, $post );
		}
		if ( false === strpos( $card, 'dt-published' ) && function_exists( '\\PKIW\\inject_post_date_into_card' ) ) {
			$card = \PKIW\inject_post_date_into_card( $card, $post );
		}
		$html = $card;
	}
	$a      = read_attrs( $post, $read );
	$status = (string) $a['readStatus'];
	list( $label, $word ) = status_copy( $status );
	$seed   = seed( (string) $post->ID, (string) ( $a['isbn'] ?? '' ), (string) ( $a['bookTitle'] ?? '' ) );

	// 1. The stored rating appears beside the plugin's stars.
	$html = with_rating_value( $html, (float) $a['rating'] );
	$html = cut_elements( $html, 'div', 'pk-progress' );

	$meta_pos = strpos( $html, '<div class="pk-meta">' );
	if ( false === $meta_pos ) {
		return $html;
	}

	// 2. The plugin's featured-first picture replaces the card cover. When
	//    that helper is unavailable, retain the earlier local fallback.
	$cover = book_cover( $post, $a, $read, 'medium_large' );
	if ( '' !== $cover ) {
		$html     = cut_elements( $html, 'div', 'pk-media' );
		$meta_pos = strpos( $html, '<div class="pk-meta">' );
	} elseif ( false === strpos( $html, 'class="pk-media"' ) ) {
		if ( has_post_thumbnail( $post ) ) {
			$cover = '<div class="pk-media cr-book__cover cr-book__cover--featured">' . get_the_post_thumbnail( $post, 'medium_large', array( 'class' => 'cr-book__img u-photo', 'loading' => 'lazy' ) ) . '</div>';
		} else {
			$cover = '<div class="pk-media cr-book__cover cr-book__cover--type" aria-hidden="true"><span class="cr-book__type-title">' . esc_html( (string) ( $a['bookTitle'] ?? $post->post_title ) ) . '</span><span class="cr-book__type-author">' . esc_html( (string) ( $a['authorName'] ?? '' ) ) . '</span></div>';
		}
	}

	// 3. Facts line: status, pages, and the finished date when there is one.
	list( $facts, $done_ts ) = facts_line( $a, $status, $label );
	$insert                  = $cover . '<p class="cr-book__facts">' . esc_html( $facts ) . '</p>';

	// 4. A dated stamp for a finished or abandoned book.
	if ( $done_ts > 0 ) {
		$insert .= '<div class="cr-book__stamp">' . render(
			array(
				'shape'  => 'rect',
				'big'    => $word,
				'mid'    => gmdate( 'd M Y', $done_ts ),
				'small'  => __( 'Reading record', 'courtneyr-child' ),
				'ink'    => INKS[ pick( $seed, 3, count( INKS ) ) ],
				'tilt'   => pick( $seed, 6, TILTS ),
				'family' => 'reading-record',
			)
		) . '</div>';
	}
	$html = substr( $html, 0, $meta_pos ) . $insert . substr( $html, $meta_pos );

	// 5. The status tab hangs on the cover (the card prints the status as
	//    text already, so the tab is decorative).
	$m_start = strpos( $html, 'class="pk-media' );
	$m_end   = false !== $m_start ? strpos( $html, '</div>', $m_start ) : false;
	if ( false !== $m_end ) {
		$html = substr( $html, 0, $m_end ) . '<span class="cr-book__tab cr-book__tab--' . esc_attr( $status ) . '" aria-hidden="true">' . esc_html( $word ) . '</span>' . substr( $html, $m_end );
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		$tags->add_class( 'pk-card--stream' );
		$tags->add_class( 'cr-book' );
		$tags->add_class( 'cr-book--stream' );
		$tags->add_class( 'cr-book--' . $status );
		$html = $tags->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\book_card', 10, 3 );
