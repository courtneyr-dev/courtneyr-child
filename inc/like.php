<?php
/**
 * Like archive, Stream and single presentation.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Like;

use function Courtneyr\Child\HomeSections\is_stream_surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The stream-card block style name used by the like archive.
 */
const ITEM_STYLE = 'cr-sticker';

/**
 * The class WordPress gives the like archive's block style.
 */
const ITEM_CLASS = 'is-style-cr-sticker';

/**
 * Paper fills used by archive stickers.
 */
const FILLS = array( 'sky-blue', 'periwinkle', 'light-orange', 'selective-yellow', 'light-gray', 'printer-ivory' );

/**
 * Number of outline variants.
 */
const OUTLINES = 4;

/**
 * Number of archive images loaded eagerly on the first page.
 */
const PAGE_1_EAGER = 3;

/**
 * Stylesheet handle.
 */
const STYLE_HANDLE = 'courtneyr-like';

/**
 * Elements removed from like archive cards.
 */
const ARCHIVE_CUTS = array(
	array( 'div', 'pk-badge' ),
	array( 'span', 'pk-kindlabel' ),
	array( 'p', 'pk-sub' ),
	array( 'p', 'pk-note' ),
	array( 'div', 'pk-note' ),
	array( 'div', 'pk-meta' ),
	array( 'div', 'pk-media' ),
	array( 'p', 'pk-excerpt' ),
	array( 'div', 'pk-embed' ),
);

/**
 * Elements removed from like Stream cards.
 */
const STREAM_CUTS = array(
	array( 'p', 'pk-note' ),
	array( 'div', 'pk-note' ),
	array( 'div', 'pk-embed' ),
	array( 'div', 'pk-media' ),
	array( 'p', 'pk-excerpt' ),
	array( 'div', 'pk-meta' ),
);

/**
 * Register the archive sticker style for the plugin stream card.
 *
 * @return void
 */
function register_item_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => ITEM_STYLE,
				'label' => __( 'Sticker', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_item_style' );

/**
 * Use the settled visible label for like cards.
 *
 * @param string $label   Current label.
 * @param string $kind    Kind slug.
 * @param string $context Label context.
 * @return string
 */
function kind_label( string $label, string $kind, string $context ): string {
	if ( 'like' === $kind && in_array( $context, array( 'like-card', 'stream-card' ), true ) ) {
		return __( 'Liked', 'courtneyr-child' );
	}
	return $label;
}
add_filter( 'pkiw_kind_label', __NAMESPACE__ . '\\kind_label', 10, 3 );

/**
 * Whether the current request is the like kind archive.
 *
 * @return bool
 */
function is_like_archive(): bool {
	return \is_tax( 'kind', 'like' );
}

/**
 * Whether the current request is a single like post.
 *
 * @return bool
 */
function is_like_single(): bool {
	return \is_singular( 'post' ) && has_term( 'like', 'kind', get_queried_object_id() );
}

/**
 * Resolve a block's post ID.
 *
 * @param mixed $instance Block instance.
 * @return int
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Whether a rendered stream-card block belongs to the archive treatment.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return bool
 */
function is_archive_item( array $block ): bool {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	return false !== strpos( $class_name, ITEM_CLASS ) || is_like_archive();
}

/**
 * Cut every element of one tag that carries a class from a fragment.
 *
 * @param string $html       Fragment.
 * @param string $tag        Tag name.
 * @param string $class_name One class the element carries.
 * @return string
 */
function cut_elements( string $html, string $tag, string $class_name ): string {
	$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
	while ( preg_match( $open, $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$start = (int) $m[0][1];
		$pos   = $start + strlen( $m[0][0] );
		$depth = 1;
		while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
			$pos    = (int) $t[0][1] + strlen( $t[0][0] );
			$depth += '/' === $t[1][0] ? -1 : 1;
		}
		if ( $depth > 0 ) {
			return $html;
		}
		$html = substr( $html, 0, $start ) . substr( $html, $pos );
	}
	return $html;
}

/**
 * Cut Stream byline rows while keeping the date row.
 *
 * @param string $html Fragment.
 * @return string
 */
function cut_stream_byline( string $html ): string {
	return (string) preg_replace_callback(
		'/<p\b[^>]*\bclass="([^"]*(?<![\w-])pk-sub(?![\w-])[^"]*)"[^>]*>.*?<\/p>/s',
		static function ( array $parts ): string {
			return false !== strpos( $parts[1], 'pk-stream-date' ) ? $parts[0] : '';
		},
		$html
	);
}

/**
 * Add CSS classes to the first card article.
 *
 * @param string $html    Fragment.
 * @param string ...$add Classes to add.
 * @return string
 */
function add_article_classes( string $html, string ...$add ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pk-card',
		)
	) ) {
		foreach ( $add as $class_name ) {
			$tags->add_class( $class_name );
		}
		return $tags->get_updated_html();
	}
	return $html;
}

/**
 * Add an attribute to the first badge.
 *
 * @param string $html Fragment.
 * @return string
 */
function hide_badge( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'div',
			'class_name' => 'pk-badge',
		)
	) && null === $tags->get_attribute( 'aria-hidden' ) ) {
		$tags->set_attribute( 'aria-hidden', 'true' );
		return $tags->get_updated_html();
	}
	return $html;
}

/**
 * Return plugin facts for a like post.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function like_facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Return plugin picture facts for a like post.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function picture_facts( int $post_id ): array {
	if ( function_exists( '\\PKIW\\kind_picture' ) ) {
		$picture = \PKIW\kind_picture( $post_id );
		return is_array( $picture ) ? $picture : array();
	}
	$facts   = like_facts( $post_id );
	$picture = $facts['picture'] ?? array();
	return is_array( $picture ) ? $picture : array();
}

/**
 * Pick the archive paper class.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function paper_class( int $post_id ): string {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		$fill = \Courtneyr\Child\Stamps\seeded_paper( $post_id, FILLS );
	} else {
		$fill = FILLS[ $post_id % count( FILLS ) ];
	}
	return '' !== $fill ? 'cr-paper--' . $fill : '';
}

/**
 * Pick the archive outline class.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function shape_class( int $post_id ): string {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		$shape = \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( (string) $post_id ), 12, OUTLINES );
	} else {
		$shape = $post_id % OUTLINES;
	}
	return 'cr-like-sticker--shape-' . $shape;
}

/**
 * Return the cited host group key.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function group_key( int $post_id ): string {
	if ( class_exists( '\\PKIW\\Grouped_Archive' ) && method_exists( '\\PKIW\\Grouped_Archive', 'group_of_post' ) ) {
		$group = \PKIW\Grouped_Archive::group_of_post( 'like', $post_id );
		if ( is_object( $group ) && method_exists( $group, 'key' ) ) {
			return (string) $group->key();
		}
	}
	return '';
}

/**
 * Normalize visible title text for duplicate detection.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function title_key( int $post_id ): string {
	$facts = like_facts( $post_id );
	$title = (string) ( $facts['cited_title'] ?? '' );
	if ( '' === trim( $title ) ) {
		$title = (string) ( $facts['host'] ?? '' );
	}
	if ( '' === trim( $title ) ) {
		$title = (string) get_the_title( $post_id );
	}
	$title = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $title ) ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
}

/**
 * Stable IDs for repeated archive titles.
 *
 * @param int $post_id Post ID.
 * @return array{title: string, date: string, repeats: bool}
 */
function label_ids( int $post_id ): array {
	static $cache = array();

	$posts = array();
	$query = $GLOBALS['wp_query'] ?? null;
	if ( is_object( $query ) && isset( $query->posts ) && is_array( $query->posts ) ) {
		$posts = $query->posts;
	}

	$ids = array();
	foreach ( $posts as $post ) {
		$ids[] = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
	}
	$cache_key = implode( ',', $ids );

	if ( ! isset( $cache[ $cache_key ] ) ) {
		$counts  = array();
		$members = array();
		foreach ( $posts as $post ) {
			$id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
			if ( ! $id || ! has_term( 'like', 'kind', $post ) ) {
				continue;
			}
			$key            = group_key( $id ) . '|' . title_key( $id );
			$members[ $id ] = $key;
			if ( '|' !== $key ) {
				$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
			}
		}
		$cache[ $cache_key ] = array(
			'counts'  => $counts,
			'members' => $members,
		);
	}

	$key     = $cache[ $cache_key ]['members'][ $post_id ] ?? '';
	$repeats = '' !== $key && 1 < ( $cache[ $cache_key ]['counts'][ $key ] ?? 0 );

	return array(
		'title'   => 'cr-like-title-' . $post_id,
		'date'    => 'cr-like-date-' . $post_id,
		'repeats' => $repeats,
	);
}

/**
 * Add repeated-title labels to the card title link.
 *
 * @param string $html    Fragment.
 * @param int    $post_id Post ID.
 * @return string
 */
function add_duplicate_labels( string $html, int $post_id ): string {
	$ids = label_ids( $post_id );
	if ( empty( $ids['repeats'] ) ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'/(<h[1-6]\b[^>]*\bclass="[^"]*(?<![\w-])pk-title(?![\w-])[^"]*"[^>]*>\s*<a\b)([^>]*>)(.*?)(<\/a>\s*<\/h[1-6]>)/s',
		static function ( array $parts ) use ( $ids, $post_id ): string {
			$open = $parts[1] . ' aria-labelledby="' . esc_attr( $ids['title'] . ' ' . $ids['date'] ) . '"' . $parts[2];
			$date = '<span id="' . esc_attr( $ids['date'] ) . '" class="screen-reader-text">, ' . esc_html( get_the_date( '', $post_id ) ) . '</span>';
			return $open . '<span id="' . esc_attr( $ids['title'] ) . '">' . $parts[3] . '</span>' . $date . $parts[4];
		},
		$html,
		1
	);
}

/**
 * Whether this archive item should load its image eagerly.
 *
 * @return bool
 */
function should_eager_load(): bool {
	$query = $GLOBALS['wp_query'] ?? null;
	$index = is_object( $query ) && isset( $query->current_post ) ? (int) $query->current_post : 0;
	return $index < PAGE_1_EAGER && (int) get_query_var( 'paged', 1 ) < 2;
}

/**
 * Build the archive picture markup.
 *
 * @param array<string, mixed> $picture Picture facts.
 * @return string
 */
function archive_picture( array $picture ): string {
	$url = (string) ( $picture['url'] ?? '' );
	if ( '' === $url ) {
		return '';
	}
	$eager = should_eager_load();
	return '<div class="pk-embed pk-embed--photo" data-cr-cover-fallback><img class="u-photo" src="' . esc_url( $url ) . '" alt="" loading="' . ( $eager ? 'eager' : 'lazy' ) . '" decoding="async"' . ( $eager ? ' fetchpriority="high"' : '' ) . '></div>';
}

/**
 * Insert markup after the caption in the card body.
 *
 * @param string $html   Fragment.
 * @param string $insert Markup to insert.
 * @return string
 */
function insert_after_caption( string $html, string $insert ): string {
	if ( '' === $insert ) {
		return $html;
	}
	return (string) preg_replace(
		'/(<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-caption(?![\w-])[^"]*"[^>]*>.*?<\/div>)/s',
		'$1' . $insert,
		$html,
		1
	);
}

/**
 * Apply archive card treatment.
 *
 * @param string   $html Fragment.
 * @param \WP_Post $post Post.
 * @return string
 */
function archive_card( string $html, \WP_Post $post ): string {
	$html = add_article_classes( $html, 'cr-like-sticker', paper_class( $post->ID ), shape_class( $post->ID ) );
	if ( post_password_required( $post ) || false !== strpos( $html, 'pk-card--protected' ) ) {
		return $html;
	}

	foreach ( ARCHIVE_CUTS as $cut ) {
		$html = cut_elements( $html, $cut[0], $cut[1] );
	}

	$facts   = like_facts( $post->ID );
	$picture = $facts['picture'] ?? array();
	$html    = insert_after_caption( $html, is_array( $picture ) ? archive_picture( $picture ) : '' );
	return add_duplicate_labels( $html, $post->ID );
}

/**
 * Apply Stream card treatment.
 *
 * @param string $html Fragment.
 * @return string
 */
function stream_card( string $html ): string {
	if ( false !== strpos( $html, 'pk-card--protected' ) ) {
		return $html;
	}
	$html = hide_badge( $html );
	$html = cut_stream_byline( $html );
	foreach ( STREAM_CUTS as $cut ) {
		$html = cut_elements( $html, $cut[0], $cut[1] );
	}
	return add_article_classes( $html, 'cr-like-sticker', 'cr-like-sticker--stream', 'cr-paper--selective-yellow' );
}

/**
 * Route a like stream card to its archive or Stream adapter.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block.
 * @param \WP_Block $instance Block instance with the Query Loop's post ID.
 * @return string
 */
function dispatch( string $html, array $block, $instance ): string {
	if ( '' === $html ) {
		return $html;
	}

	$archive = is_archive_item( $block );
	if ( ! $archive && ! is_stream_surface() ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'like', 'kind', $post ) ) {
		return $html;
	}

	return $archive ? archive_card( $html, $post ) : stream_card( $html );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\dispatch', 10, 3 );

/**
 * Mark the single clipping's picture wrapper.
 *
 * @param string $html Fragment.
 * @return string
 */
function mark_embed( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'div',
			'class_name' => 'pk-embed',
		)
	) ) {
		$tags->set_attribute( 'data-cr-cover-fallback', true );
		return $tags->get_updated_html();
	}
	return $html;
}

/**
 * Replace the first image source and alt text.
 *
 * @param string $html Fragment.
 * @param string $url  Image URL.
 * @param string $alt  Image alt text.
 * @return string
 */
function replace_image_source( string $html, string $url, string $alt ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( 'img' ) ) {
		$tags->set_attribute( 'src', esc_url( $url ) );
		$tags->set_attribute( 'alt', $alt );
		return $tags->get_updated_html();
	}
	return $html;
}

/**
 * Build single picture markup.
 *
 * @param array<string, mixed> $picture Picture facts.
 * @return string
 */
function single_picture( array $picture ): string {
	$url = (string) ( $picture['url'] ?? '' );
	if ( '' === $url ) {
		return '';
	}
	return '<div class="pk-embed pk-embed--photo" data-cr-cover-fallback><img class="u-photo" src="' . esc_url( $url ) . '" alt="' . esc_attr( (string) ( $picture['alt'] ?? '' ) ) . '" loading="lazy" decoding="async"></div>';
}

/**
 * Apply the single clipping treatment to a like card.
 *
 * @param string    $html     Rendered like card.
 * @param array     $block    Parsed block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function single_clipping( string $html, array $block, $instance ): string {
	if ( '' === $html || ! is_like_single() ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	if ( ! $post_id || get_queried_object_id() !== $post_id || ! has_term( 'like', 'kind', $post_id ) ) {
		return $html;
	}

	$html    = add_article_classes( $html, 'cr-like-clipping', 'cr-paper--printer-ivory' );
	$picture = picture_facts( $post_id );
	$url     = (string) ( $picture['url'] ?? '' );
	$has_box = false !== strpos( $html, 'pk-embed' );
	if ( $has_box ) {
		$html = mark_embed( $html );
		if ( '' !== $url ) {
			$html = replace_image_source( $html, $url, (string) ( $picture['alt'] ?? '' ) );
		}
	} elseif ( '' !== $url ) {
		$html = insert_after_caption( $html, single_picture( $picture ) );
	}
	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/like-card', __NAMESPACE__ . '\\single_clipping', 10, 3 );

/**
 * Drop a suppressed featured image on the like single.
 *
 * @param string $html  Rendered featured image block.
 * @param array  $block Parsed block.
 * @return string
 */
function drop_featured_image( string $html, array $block ): string {
	if ( '' === $html || ! is_like_single() ) {
		return $html;
	}
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( false === strpos( $class_name, 'single-post__featured' ) ) {
		return $html;
	}
	$picture = picture_facts( (int) get_queried_object_id() );
	return ! empty( $picture['suppress_featured'] ) ? '' : $html;
}
add_filter( 'render_block_core/post-featured-image', __NAMESPACE__ . '\\drop_featured_image', 10, 2 );

/**
 * Load the like stylesheet where a like card can appear.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_like_archive() && ! is_like_single() && ! is_stream_surface() ) {
		return;
	}
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-like.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
