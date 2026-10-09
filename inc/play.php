<?php
/**
 * Play archive and Stream card routing.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Play;

use function Courtneyr\Child\HomeSections\is_stream_surface;
use function Courtneyr\Child\MediaShelf\cut_elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The stream-card block style name used by the play archive.
 */
const ITEM_STYLE = 'cr-play-item';

/**
 * The class WordPress gives the play archive's block style.
 */
const ITEM_CLASS = 'is-style-cr-play-item';

/**
 * Elements removed from an ungrouped play card on the archive.
 */
const EMPTY_GROUP_CUTS = array(
	array( 'span', 'pk-kindlabel' ),
	array( 'div', 'pk-badge' ),
	array( 'p', 'pk-sub' ),
	array( 'div', 'pk-stars' ),
	array( 'div', 'pk-note' ),
	array( 'div', 'pk-meta' ),
	array( 'p', 'pk-stream-date' ),
	array( 'p', 'pk-excerpt' ),
	array( 'p', 'pk-media__caption' ),
	array( 'ul', 'pk-media__thumbs' ),
);

/**
 * Register the archive item style for the plugin's stream card.
 *
 * @return void
 */
function register_item_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => ITEM_STYLE,
				'label' => __( 'Play item', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_item_style' );

/**
 * Whether the current request is the play kind archive.
 *
 * @return bool
 */
function is_play_archive(): bool {
	return \is_tax( 'kind', 'play' );
}

/**
 * Whether the current request is a single play post.
 *
 * @return bool
 */
function is_play_single(): bool {
	return \is_singular( 'post' ) && has_term( 'play', 'kind', get_queried_object_id() );
}

/**
 * Whether a rendered stream-card block belongs to the archive treatment.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return bool
 */
function is_archive_item( array $block ): bool {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	return false !== strpos( $class_name, ITEM_STYLE ) || is_play_archive();
}

/**
 * Normalize a play post's visible title for duplicate detection.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function title_key( int $post_id ): string {
	$title = '';
	if ( function_exists( '\\PKIW\\kind_facts' ) ) {
		$facts = \PKIW\kind_facts( $post_id );
		$title = is_array( $facts ) ? (string) ( $facts['title'] ?? '' ) : '';
	}
	if ( '' === trim( $title ) ) {
		$title = (string) get_the_title( $post_id );
	}
	$title = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $title ) ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
}

/**
 * Stable IDs for one item and whether its title repeats on this query page.
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

	$ids       = array_map(
		static function ( $post ): int {
			return is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
		},
		$posts
	);
	$cache_key = implode( ',', $ids );

	if ( ! isset( $cache[ $cache_key ] ) ) {
		$counts  = array();
		$members = array();
		foreach ( $posts as $post ) {
			$id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
			if ( ! $id || ! has_term( 'play', 'kind', $post ) ) {
				continue;
			}
			$key            = title_key( $id );
			$members[ $id ] = $key;
			if ( '' !== $key ) {
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
		'title'   => 'cr-play-title-' . $post_id,
		'date'    => 'cr-play-date-' . $post_id,
		'repeats' => $repeats,
	);
}

/**
 * Route a play stream card to its archive or Stream adapter.
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

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'play', 'kind', $post ) || ! function_exists( '\\PKIW\\play_group' ) ) {
		return $html;
	}

	$group = (string) \PKIW\play_group( $post->ID );
	if ( $archive ) {
		$has_cover = false;
		if ( '' === $group ) {
			foreach ( EMPTY_GROUP_CUTS as $cut ) {
				$html = cut_elements( $html, $cut[0], $cut[1] );
			}
			$cover     = new \WP_HTML_Tag_Processor( $html );
			$has_cover = $cover->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'pk-media',
				)
			);
		}

		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( $tags->next_tag(
			array(
				'tag_name'   => 'article',
				'class_name' => 'pk-card',
			)
		) ) {
			$tags->add_class( 'cr-play-item' );
			if ( '' === $group ) {
				$tags->add_class( 'cr-play-item--ungrouped' );
			}
			if ( $has_cover ) {
				$tags->add_class( 'has-cover' );
			}
			if ( $has_cover && $tags->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'pk-media',
				)
			) ) {
				$tags->set_attribute( 'data-cr-cover-fallback', true );
			}
			$html = $tags->get_updated_html();
		}

		$filtered = apply_filters( 'courtneyr_child_play_item', $html, $group, $post, label_ids( $post->ID ) );
		return is_string( $filtered ) ? $filtered : $html;
	}

	$filtered = apply_filters( 'courtneyr_child_play_stream_card', $html, $group, $post );
	return is_string( $filtered ) ? $filtered : $html;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\dispatch', 10, 3 );

/**
 * Load the play scaffold where a play card can appear.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_play_archive() && ! is_play_single() && ! is_stream_surface() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-play',
		COURTNEYR_CHILD_URI . '/assets/css/cr-play.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
