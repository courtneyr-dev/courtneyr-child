<?php
/**
 * Read archive bookshelves (PKIW issue 234).
 *
 * The plugin owns grouping, ordering and facts. This adapter reduces its
 * stream card to one linked shelf object and selects its shelf treatment.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\ReadShelf;

use function Courtneyr\Child\MediaShelf\cut_elements;
use function Courtneyr\Child\SingleRead\find_read_block;
use function Courtneyr\Child\SingleRead\read_attrs;
use function Courtneyr\Child\Stamps\seeded_paper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CARD_STYLE = 'is-style-cr-shelf-book';
const FACE_OUT   = array( 'reading', 'to-read' );
const PAPERS     = array( 'sky-blue', 'periwinkle', 'light-orange', 'prussian-blue', 'cerulean' );
const FIRST_ROW  = 4;

/** Register the shelf treatment for the plugin's stream card. */
function register_card_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => 'cr-shelf-book',
				'label' => __( 'Shelf book', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style' );

/** Whether this request is the read kind archive. */
function is_read_archive(): bool {
	return is_tax( 'kind', 'read' );
}

/** Load shelf paint on the archive and in the editor. */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_read_archive() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-read-shelf',
		COURTNEYR_CHILD_URI . '/assets/css/cr-read-shelf.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Choose the shelf object from the engine's group, never from card facts.
 *
 * @param string $group_key Stored group key.
 * @param bool   $sectioned Whether the engine is printing sections.
 * @return string
 */
function shape_of( string $group_key, bool $sectioned ): string {
	return $sectioned && in_array( $group_key, FACE_OUT, true ) ? 'faceout' : 'slab';
}

/**
 * Mirror the grouped archive engine's section heading id.
 *
 * @param string $slug Group slug.
 * @return string
 */
function group_heading_id( string $slug ): string {
	$slug = strtolower( str_replace( '%', '', $slug ) );
	$slug = (string) preg_replace( '/[^a-z0-9_-]/', '', $slug );
	return 'pkiw-group-' . ( '' === $slug ? 'empty' : $slug );
}

/**
 * Progressive names used only as far as duplicate titles require.
 *
 * @param string $title  Visible title.
 * @param string $author Visible author, or empty.
 * @param string $shelf  Shelf name stand-in, or empty.
 * @param string $date   Display date, or empty.
 * @return string[]
 */
function name_candidates( string $title, string $author, string $shelf, string $date ): array {
	$parts      = array( $title );
	$candidates = array( 1 => $title );
	foreach ( array(
		2 => $author,
		3 => $shelf,
		4 => $date,
	) as $level => $part ) {
		if ( '' === trim( $part ) ) {
			continue;
		}
		$parts[] = $part;
		$name    = implode( ', ', $parts );
		if ( end( $candidates ) !== $name ) {
			$candidates[ $level ] = $name;
		}
	}
	return $candidates;
}

/**
 * Pick the shortest unused name and reserve this item's progressive names.
 *
 * Reserving every level makes later duplicates advance to the first fact
 * that distinguishes them: author, shelf, then date.
 *
 * @param string[]           $candidates Progressive names keyed by level.
 * @param array<string,bool> $used       Names reserved on this page.
 * @return int
 */
function choose_level( array $candidates, array &$used ): int {
	$levels = array_map( 'intval', array_keys( $candidates ) );
	$level  = empty( $levels ) ? 1 : max( $levels );
	foreach ( $candidates as $candidate_level => $candidate ) {
		if ( ! isset( $used[ strtolower( (string) $candidate ) ] ) ) {
			$level = (int) $candidate_level;
			break;
		}
	}
	foreach ( $candidates as $candidate ) {
		$used[ strtolower( (string) $candidate ) ] = true;
	}
	return $level;
}

/** Per-request accessible names. */
function &used_names(): array {
	static $used = array();
	return $used;
}

/** Per-request count of real face-out covers. */
function &cover_count(): int {
	static $count = 0;
	return $count;
}

/** Reset adapter state between archive renders and standalone tests. */
function reset_page_state(): void {
	$used  = &used_names();
	$used  = array();
	$count = &cover_count();
	$count = 0;
}

/**
 * Add classes to the first article without rebuilding plugin markup.
 *
 * @param string   $html    Plugin card.
 * @param string[] $classes Classes to add.
 * @return string
 */
function add_article_classes( string $html, array $classes ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	foreach ( $classes as $class_name ) {
		if ( '' !== $class_name ) {
			$tags->add_class( $class_name );
		}
	}
	return $tags->get_updated_html();
}

/**
 * Whether the first article carries one class.
 *
 * @param string $html       Plugin card.
 * @param string $class_name Class to find.
 * @return bool
 */
function article_has_class( string $html, string $class_name ): bool {
	$tags = new \WP_HTML_Tag_Processor( $html );
	return $tags->next_tag( 'article' ) && true === $tags->has_class( $class_name );
}

/**
 * Remove status/date sublines while retaining the book author's h-card.
 *
 * @param string $html Plugin card.
 * @return string
 */
function cut_non_author_subs( string $html ): string {
	return (string) preg_replace_callback(
		'/<p\b[^>]*\bclass="[^"]*(?<![\\w-])pk-sub(?![\\w-])[^"]*"[^>]*>.*?<\/p>/is',
		static fn( array $found ): string => str_contains( $found[0], 'p-author' ) ? $found[0] : '',
		$html
	);
}

/**
 * Find the end of an opening tag that carries a class.
 *
 * @param string $html       Plugin card.
 * @param string $tag        Tag name.
 * @param string $class_name Class to find.
 * @return int|null
 */
function opening_end( string $html, string $tag, string $class_name ): ?int {
	if ( ! preg_match( '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}
	return (int) $match[0][1] + strlen( $match[0][0] );
}

/**
 * Build the one linked title heading.
 *
 * @param array<string, mixed> $item Normalized shelf item.
 * @return string
 */
function title_heading( array $item ): string {
	$id    = 'cr-shelf-t-' . $item['uid'];
	$attrs = ' id="' . esc_attr( $id ) . '" href="' . esc_url( $item['permalink'] ) . '"';
	if ( 1 < $item['chain'] ) {
		$ids = array( $id );
		if ( 2 <= $item['chain'] && '' !== $item['author'] ) {
			$ids[] = 'cr-shelf-a-' . $item['uid'];
		}
		if ( 3 <= $item['chain'] && '' !== $item['shelf_id'] ) {
			$ids[] = $item['shelf_id'];
		}
		if ( 4 <= $item['chain'] && '' !== $item['date_iso'] ) {
			$ids[] = 'cr-shelf-d-' . $item['uid'];
		}
		$attrs .= ' aria-labelledby="' . esc_attr( implode( ' ', $ids ) ) . '"';
	}
	$level = (int) $item['level'];
	$class = ( $item['named'] ?? true ) ? 'pk-title p-name' : 'pk-title';
	return '<h' . $level . ' class="' . $class . '"><a' . $attrs . '>' . esc_html( $item['title'] ) . '</a></h' . $level . ">\n";
}

/**
 * Build a cover image from the theme's normalized picture.
 *
 * @param array<string, mixed> $item Normalized shelf item.
 * @return string
 */
function cover_image( array $item ): string {
	$picture = $item['picture'];
	if ( ! is_array( $picture ) ) {
		return '';
	}
	$classes = 'cr-shelf-book__img u-photo' . ( $item['eager'] ? ' skip-lazy' : '' );
	$attrs   = array(
		'class'    => $classes,
		'alt'      => '',
		'loading'  => $item['eager'] ? 'eager' : 'lazy',
		'decoding' => 'async',
		'sizes'    => $item['size_attr'],
	);
	if ( 0 < (int) $picture['attachment_id'] ) {
		$image = wp_get_attachment_image( (int) $picture['attachment_id'], 'medium_large', false, $attrs );
	} else {
		$image = '<img class="' . esc_attr( $classes ) . '" src="' . esc_url( $picture['url'] ) . '" alt="" loading="' . ( $item['eager'] ? 'eager' : 'lazy' ) . '" decoding="async" sizes="' . esc_attr( $item['size_attr'] ) . '">';
	}
	if ( $item['eager'] ) {
		$tags = new \WP_HTML_Tag_Processor( $image );
		if ( $tags->next_tag( 'img' ) ) {
			$tags->set_attribute( 'fetchpriority', 'high' );
			$tags->set_attribute( 'data-skip-lazy', '1' );
			$image = $tags->get_updated_html();
		}
	}
	return $image;
}

/**
 * Reduce a plugin card to one shelf book.
 *
 * @param string               $html Plugin card.
 * @param array<string, mixed> $item Normalized item facts and shelf state.
 * @return string
 */
function shelf_item( string $html, array $item ): string {
	foreach ( array(
		array( 'span', 'pk-kindlabel' ),
		array( 'div', 'pk-badge' ),
		array( 'div', 'pk-progress' ),
		array( 'div', 'pk-stars' ),
		array( 'data', 'p-rating' ),
		array( 'div', 'pk-note' ),
		array( 'div', 'pk-meta' ),
		array( 'div', 'pk-media' ),
		array( 'p', 'pk-excerpt' ),
	) as $cut ) {
		$html = cut_elements( $html, $cut[0], $cut[1] );
	}
	$html    = cut_non_author_subs( $html );
	$heading = title_heading( $item );
	if ( preg_match( '/<h[1-6]\b[^>]*\bclass="[^"]*(?<![\\w-])pk-title(?![\\w-])[^"]*"[^>]*>.*?<\/h[1-6]>/is', $html ) ) {
		$html = (string) preg_replace( '/<h[1-6]\b[^>]*\bclass="[^"]*(?<![\\w-])pk-title(?![\\w-])[^"]*"[^>]*>.*?<\/h[1-6]>/is', $heading, $html, 1 );
	} else {
		$caption_end = opening_end( $html, 'div', 'pk-caption' );
		if ( null !== $caption_end ) {
			$html = substr( $html, 0, $caption_end ) . $heading . substr( $html, $caption_end );
		} else {
			$body_end = opening_end( $html, 'div', 'pk-body' );
			if ( null === $body_end ) {
				return $html;
			}
			$html = substr( $html, 0, $body_end ) . '<div class="pk-caption">' . $heading . '</div>' . substr( $html, $body_end );
		}
	}
	if ( 'faceout' === $item['shape'] && null === opening_end( $html, 'div', 'pk-body' ) ) {
		$html = (string) preg_replace(
			'/<h[1-6]\b[^>]*\bclass="[^"]*(?<![\\w-])pk-title(?![\\w-])[^"]*"[^>]*>.*?<\/h[1-6]>/is',
			'<div class="pk-body"><div class="pk-caption">$0</div></div>',
			$html,
			1
		);
	}

	$author_id  = 'cr-shelf-a-' . $item['uid'];
	$tags       = new \WP_HTML_Tag_Processor( $html );
	$has_author = false;
	if ( '' !== $item['author'] && $tags->next_tag( array( 'class_name' => 'p-author' ) ) ) {
		$tags->set_attribute( 'id', $author_id );
		$html       = $tags->get_updated_html();
		$has_author = true;
	}
	if ( '' !== $item['author'] && ! $has_author ) {
		$close = '</h' . (int) $item['level'] . '>';
		$at    = strpos( $html, $close );
		if ( false !== $at ) {
			$at  += strlen( $close );
			$line = '<p class="pk-sub"><span id="' . esc_attr( $author_id ) . '" class="cr-shelf-book__author">' . esc_html( $item['author'] ) . '</span></p>';
			$html = substr( $html, 0, $at ) . $line . substr( $html, $at );
		}
	}

	$image = '';
	if ( 'faceout' === $item['shape'] ) {
		$image    = cover_image( $item );
		$classes  = 'cr-shelf-book__cover' . ( '' === $image ? ' cr-shelf-book__cover--type' : '' );
		$cover    = '<div class="' . esc_attr( $classes ) . '" data-cr-cover-fallback aria-hidden="true" data-title="' . esc_attr( $item['title'] ) . '" data-author="' . esc_attr( $item['author'] ) . '">' . $image . '</div>';
		$body_end = opening_end( $html, 'div', 'pk-body' );
		if ( null !== $body_end ) {
			$html = substr( $html, 0, $body_end ) . $cover . substr( $html, $body_end );
		}
	}

	$classes = array( 'cr-shelf-book', 'cr-shelf-book--' . $item['shape'] );
	if ( 'slab' === $item['shape'] && '' !== $item['paper'] ) {
		$classes[] = 'cr-shelf-book--paper-' . $item['paper'];
	}
	if ( '' !== $image ) {
		$classes[] = 'has-cover';
	}
	$html = add_article_classes( $html, $classes );

	if ( '' !== $item['date_iso'] ) {
		$date = '<time class="dt-published cr-shelf-book__date" id="cr-shelf-d-' . esc_attr( $item['uid'] ) . '" datetime="' . esc_attr( $item['date_iso'] ) . '" hidden>' . esc_html( $item['date_text'] ) . '</time>';
		$at   = strrpos( $html, '</article>' );
		if ( false !== $at ) {
			$at   = article_has_class( $html, 'h-entry' ) ? $at : $at + strlen( '</article>' );
			$html = substr( $html, 0, $at ) . $date . substr( $html, $at );
		}
	}
	return $html;
}

/**
 * Turn the styled stream card into the book selected by archive state.
 *
 * @param string $html     Rendered stream card.
 * @param array  $block    Parsed stream-card block.
 * @param mixed  $instance Block instance with post context.
 * @return string
 */
function shelf_card( string $html, array $block, $instance ): string {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! preg_match( '/(?:^|\\s)' . preg_quote( CARD_STYLE, '/' ) . '(?:\\s|$)/', $class_name ) ) {
		return $html;
	}
	$post_id = is_object( $instance ) && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'read', 'kind', $post ) ) {
		return $html;
	}

	$protected = post_password_required( $post );
	$read      = $protected ? null : find_read_block( $post );
	$a         = $protected ? array() : read_attrs( $post, $read ?? array() );
	$named     = '' !== trim( (string) ( $a['bookTitle'] ?? '' ) ) || '' !== trim( (string) $post->post_title );
	$title     = trim( (string) ( $a['bookTitle'] ?? '' ) );
	if ( '' === $title ) {
		$title = trim( (string) get_the_title( $post ) );
	}
	if ( '' === $title && function_exists( '\\PKIW\\untitled_name' ) ) {
		$title = (string) \PKIW\untitled_name( $post );
	}
	if ( '' === $title ) {
		$title = (string) ( $post->post_name ?? '' );
	}
	$author = trim( (string) ( $a['authorName'] ?? '' ) );

	if ( ! $protected && false === strpos( $html, 'pk-card k-read' ) ) {
		$card_block = $read;
		if ( null === $card_block && ( '' !== trim( (string) ( $a['bookTitle'] ?? '' ) ) || '' !== $author ) ) {
			$attrs = array();
			foreach ( array( 'bookTitle', 'authorName', 'readStatus', 'bookUrl', 'isbn' ) as $key ) {
				if ( '' !== trim( (string) ( $a[ $key ] ?? '' ) ) ) {
					$attrs[ $key ] = $a[ $key ];
				}
			}
			$card_block = array(
				'blockName'    => 'post-kinds-indieweb/read-card',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		}
		if ( is_array( $card_block ) ) {
			$card = render_block( $card_block );
			if ( str_contains( $card, 'pk-card k-read' ) ) {
				$html = function_exists( '\\PKIW\\link_title_to_post' ) ? \PKIW\link_title_to_post( $card, $post ) : $card;
			}
		}
	}

	$sectioned = false;
	$group     = null;
	if ( class_exists( '\\PKIW\\Grouped_Archive' ) ) {
		$sectioned = \PKIW\Grouped_Archive::is_sectioning();
		$group     = $sectioned ? \PKIW\Grouped_Archive::current_group() : null;
		if ( ! $sectioned && \PKIW\Grouped_Archive::is_block_preview() ) {
			$group     = \PKIW\Grouped_Archive::group_of_post( 'read', $post->ID );
			$sectioned = null !== $group;
		}
	}
	$key      = $group ? (string) $group->key() : '';
	$shape    = shape_of( $key, $sectioned );
	$level    = $sectioned ? 3 : 2;
	$shelf_id = $sectioned && $group ? group_heading_id( (string) $group->slug() ) : '';

	$picture = null;
	if ( 'faceout' === $shape && ! $protected && function_exists( '\\PKIW\\kind_picture' ) ) {
		$found = \PKIW\kind_picture( $post->ID );
		if ( '' !== (string) ( $found['source'] ?? '' ) && '' !== (string) ( $found['url'] ?? '' ) ) {
			$picture = array(
				'url'           => (string) $found['url'],
				'attachment_id' => (int) ( $found['attachment_id'] ?? 0 ),
				'remote'        => 0 === (int) ( $found['attachment_id'] ?? 0 ),
			);
		}
	}
	$paper = 'slab' === $shape ? seeded_paper( $post->ID, PAPERS ) : '';
	$eager = false;
	if ( 'faceout' === $shape && null !== $picture ) {
		$count = &cover_count();
		$eager = $count < FIRST_ROW;
		++$count;
	}
	$uid       = wp_unique_id( '' );
	$date_iso  = (string) get_post_time( 'c', true, $post );
	$date_text = (string) get_the_date( '', $post );
	$names     = name_candidates( $title, $author, '' !== $shelf_id ? 'shelf' : '', $date_text );
	$used      = &used_names();
	$chain     = choose_level( $names, $used );

	return shelf_item(
		$html,
		array(
			'post_id'   => $post->ID,
			'uid'       => $uid,
			'title'     => $title,
			'named'     => $named,
			'author'    => $author,
			'permalink' => (string) get_permalink( $post ),
			'shape'     => $shape,
			'level'     => $level,
			'picture'   => $picture,
			'paper'     => $paper,
			'eager'     => $eager,
			'chain'     => $chain,
			'shelf_id'  => $shelf_id,
			'date_iso'  => $date_iso,
			'date_text' => $date_text,
			'size_attr' => '(min-width: 64rem) 22vw, (min-width: 40rem) 30vw, 46vw',
		)
	);
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\shelf_card', 10, 3 );
