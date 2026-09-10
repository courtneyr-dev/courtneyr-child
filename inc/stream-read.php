<?php
/**
 * Stream read cards as small physical books on a library slip.
 *
 * The plugin's read card stays (title link, date, author, status,
 * stars, cover, review); this file adds only what the object needs:
 * classes for the book shell, a status tab hung on the cover (the
 * status text the card already prints), the rating as a number beside
 * the stars, a mono facts line, a short generic handwritten mark, and a
 * dated stamp for finished or set-aside books. cr-post-kinds.css paints
 * the spine, page block, perspective and slip. Nothing here touches
 * single read posts or other kinds.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamRead;

use function Courtneyr\Child\Journal\card_attrs;
use function Courtneyr\Child\Journal\margin_lines;
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
 * Dress a read stream card as a book.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block (unused).
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function book_card( string $html, array $block, $instance ): string {
	if ( ! is_page( 'stream' ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'read', 'kind', $post ) || false === strpos( $html, 'pk-card k-read' ) ) {
		return $html;
	}
	$read = find_read_block( $post );
	if ( null === $read ) {
		return $html;
	}
	$a      = card_attrs( $post, 'post-kinds-indieweb/read-card', (array) ( $read['attrs'] ?? array() ) );
	$status = (string) ( $a['readStatus'] ?? 'to-read' );
	list( $label, $word ) = status_copy( $status );
	$seed   = seed( (string) $post->ID, (string) ( $a['isbn'] ?? '' ), (string) ( $a['bookTitle'] ?? '' ) );

	// 1. The rating as a number, read back from the plugin's own stars.
	if ( preg_match( '/<div class="pk-stars[^"]*" aria-label="[^"]*?(\d)[^"]*"/', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$s_end = strpos( $html, '</div>', (int) $m[0][1] );
		if ( false !== $s_end ) {
			$html = substr( $html, 0, $s_end ) . '<span class="pk-rating-value">' . esc_html( sprintf( '%d / 5', (int) $m[1][0] ) ) . '</span>' . substr( $html, $s_end );
		}
	}

	$meta_pos = strpos( $html, '<div class="pk-meta">' );
	if ( false === $meta_pos ) {
		return $html;
	}

	// 2. No stored cover: a typographic cover so the book still has a face.
	$insert = '';
	if ( false === strpos( $html, 'class="pk-media"' ) ) {
		$insert .= '<div class="pk-media cr-book__cover cr-book__cover--type" aria-hidden="true"><span class="cr-book__type-title">' . esc_html( (string) ( $a['bookTitle'] ?? $post->post_title ) ) . '</span><span class="cr-book__type-author">' . esc_html( (string) ( $a['authorName'] ?? '' ) ) . '</span></div>';
	}

	// 3. Facts line: status, pages, and the finished date when there is one.
	$facts   = array( $label );
	$pages   = (int) ( $a['pageCount'] ?? 0 );
	$current = (int) ( $a['currentPage'] ?? 0 );
	if ( 'reading' === $status && $pages > 0 && $current > 0 ) {
		$facts[] = sprintf( /* translators: 1: page, 2: pages */ __( 'page %1$d of %2$d', 'courtneyr-child' ), $current, $pages );
	} elseif ( $pages > 0 ) {
		$facts[] = sprintf( /* translators: %d: pages */ __( '%d pages', 'courtneyr-child' ), $pages );
	}
	$done_ts = 0;
	if ( in_array( $status, array( 'finished', 'abandoned' ), true ) && ! empty( $a['finishedAt'] ) ) {
		$done_ts = (int) strtotime( (string) $a['finishedAt'] );
		if ( $done_ts > 0 ) {
			$facts[] = (string) wp_date( (string) get_option( 'date_format' ), $done_ts );
		}
	}
	$insert .= '<p class="cr-book__facts">' . esc_html( implode( ' · ', $facts ) ) . '</p>';

	// 4. One short handwritten mark, generic, decorative.
	$lines   = margin_lines( $seed, 'read' );
	$insert .= '<p class="cr-hand cr-book__hand" aria-hidden="true">' . esc_html( $lines[ pick( $seed, 4, 3 ) ] ) . '</p>';

	// 5. A dated stamp for a finished or set-aside book.
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

	// 6. The status tab hangs on the cover (the card prints the status as
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
