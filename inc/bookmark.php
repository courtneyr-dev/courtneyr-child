<?php
/**
 * Bookmark archive and Stream card routing.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Bookmark;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ITEM_STYLE = 'cr-bookmark-card';
const ITEM_CLASS = 'is-style-cr-bookmark-card';

/**
 * Register the bookmark archive stream-card style.
 *
 * @return void
 */
function register_item_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => ITEM_STYLE,
				'label' => __( 'Bookmark card', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_item_style' );

/**
 * Whether the current request is the bookmark archive.
 *
 * @return bool
 */
function is_bookmark_archive(): bool {
	return \is_tax( 'kind', 'bookmark' );
}

/**
 * Whether the current request is a bookmark single.
 *
 * @return bool
 */
function is_bookmark_single(): bool {
	return \is_singular( 'post' ) && has_term( 'bookmark', 'kind', get_queried_object_id() );
}

/**
 * Bookmark facts from the plugin.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Normalize text for title comparison.
 *
 * @param string $text Text or HTML.
 * @return string Normalized text.
 */
function comparable_text( string $text ): string {
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}

/**
 * Normalize a bookmark title for duplicate detection.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function title_key( int $post_id ): string {
	$facts = facts( $post_id );
	$title = (string) ( $facts['title'] ?? '' );
	if ( '' === trim( $title ) ) {
		$title = (string) get_the_title( $post_id );
	}
	return comparable_text( $title );
}

/**
 * Whether the stored post title is the real visible bookmark name.
 *
 * @param \WP_Post             $post  Post object.
 * @param array<string, mixed> $facts Bookmark facts.
 * @return bool
 */
function has_real_name( \WP_Post $post, array $facts ): bool {
	$stored = trim( (string) $post->post_title );
	return '' !== $stored && comparable_text( $stored ) === comparable_text( (string) ( $facts['title'] ?? '' ) );
}

/**
 * Stable IDs for one item and whether its title repeats on this query page.
 *
 * @param int $post_id Post ID.
 * @return array{title: string, host: string, repeats: bool}
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
			if ( ! $id || ! has_term( 'bookmark', 'kind', $post ) ) {
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
		'title'   => 'cr-bookmark-title-' . $post_id,
		'host'    => 'cr-bookmark-host-' . $post_id,
		'repeats' => $repeats,
	);
}

/**
 * Clamp the block heading level.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return int Heading level.
 */
function heading_level( array $block ): int {
	$level = (int) ( $block['attrs']['headingLevel'] ?? 2 );
	return max( 2, min( 4, $level ) );
}

/**
 * Hidden bookmark-of cite from plugin facts.
 *
 * @param array<string, mixed> $facts Bookmark facts.
 * @return string Hidden cite HTML.
 */
function hidden_cite( array $facts ): string {
	if ( '' === (string) ( $facts['url'] ?? '' ) || ! function_exists( '\\PKIW\\hidden_cite_html' ) ) {
		return '';
	}
	return (string) \PKIW\hidden_cite_html(
		'u-bookmark-of',
		array(
			'p-name'   => (string) ( $facts['cited_title'] ?? '' ),
			'p-author' => (string) ( $facts['author'] ?? '' ),
			'u-url'    => (string) ( $facts['url'] ?? '' ),
		)
	);
}

/**
 * Decorate a protected card without exposing bookmark facts.
 *
 * @param string $html Rendered HTML.
 * @return string Rendered HTML.
 */
function locked_card( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pk-card',
		)
	) ) {
		$tags->add_class( 'cr-index-card' );
		$tags->add_class( 'cr-index-card--locked' );
		return $tags->get_updated_html();
	}
	return $html;
}

/**
 * Build an archive index-card item.
 *
 * @param \WP_Post             $post  Post object.
 * @param array<string, mixed> $facts Bookmark facts.
 * @param array<string, mixed> $block Parsed block.
 * @return string Card markup.
 */
function archive_card( \WP_Post $post, array $facts, array $block ): string {
	$level = heading_level( $block );
	$title = (string) ( $facts['title'] ?? '' );
	$host  = (string) ( $facts['host'] ?? '' );
	$ids   = label_ids( $post->ID );
	$label = $ids['repeats'] && '' !== $host;

	$heading_attrs = $label ? ' id="' . esc_attr( $ids['title'] ) . '"' : '';
	$host_attrs    = $label ? ' id="' . esc_attr( $ids['host'] ) . '"' : '';
	$link_attrs    = $label ? ' aria-labelledby="' . esc_attr( $ids['title'] . ' ' . $ids['host'] ) . '"' : '';
	$name_class    = has_real_name( $post, $facts ) ? ' p-name' : '';

	$html  = '<article class="pk-card k-bookmark cr-index-card cr-paper-sheet cr-paper--printer-ivory is-style-cr-bookmark-card">' . "\n";
	$html .= '<h' . $level . ' class="pk-title cr-index-card__title' . $name_class . '"' . $heading_attrs . '><a class="u-url" href="' . esc_url( (string) get_permalink( $post ) ) . '"' . $link_attrs . '>' . esc_html( $title ) . '</a></h' . $level . '>' . "\n";
	if ( '' !== $host ) {
		$html .= '<p class="cr-index-card__host"' . $host_attrs . '>' . esc_html( $host ) . '</p>' . "\n";
	}
	$html .= hidden_cite( $facts ) . "\n";
	$html .= '</article>';
	return $html;
}

/**
 * Build a Stream bookmark card.
 *
 * @param \WP_Post             $post  Post object.
 * @param array<string, mixed> $facts Bookmark facts.
 * @param array<string, mixed> $block Parsed block.
 * @return string Card markup.
 */
function stream_card( \WP_Post $post, array $facts, array $block ): string {
	$level = heading_level( $block );
	$title = (string) ( $facts['title'] ?? '' );
	$host  = (string) ( $facts['host'] ?? '' );
	$date  = is_array( $facts['date'] ?? null ) ? $facts['date'] : array( '', '' );
	$label = function_exists( '\\PKIW\\get_kind_label' ) ? (string) \PKIW\get_kind_label( __( 'Bookmark', 'courtneyr-child' ), 'bookmark', 'stream-card' ) : __( 'Bookmark', 'courtneyr-child' );
	$glyph = function_exists( '\\PKIW\\get_kind_icon_svg' ) ? (string) \PKIW\get_kind_icon_svg( 'bookmark' ) : '';

	$html  = '<article class="pk-card pk-card--stream k-bookmark cr-bookmark-stream">' . "\n";
	$html .= '<p class="cr-bookmark-stream__label"><span class="cr-bookmark-stream__glyph" aria-hidden="true">' . $glyph . '</span><span class="cr-bookmark-stream__kind">' . esc_html( $label ) . '</span></p>' . "\n";
	$html .= '<h' . $level . ' class="pk-title' . ( has_real_name( $post, $facts ) ? ' p-name' : '' ) . '"><a class="u-url" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $title ) . '</a></h' . $level . '>' . "\n";
	if ( '' !== $host ) {
		$html .= '<p class="cr-bookmark-stream__host">' . esc_html( $host ) . '</p>' . "\n";
	}
	if ( '' !== (string) ( $date[0] ?? '' ) ) {
		$html .= '<p class="cr-bookmark-stream__date"><time datetime="' . esc_attr( (string) $date[0] ) . '">' . esc_html( (string) ( $date[1] ?? '' ) ) . '</time></p>' . "\n";
	}
	if ( '' !== $host && function_exists( '\\Courtneyr\\Child\\Stamps\\render' ) ) {
		$seed  = \Courtneyr\Child\Stamps\seed( 'bookmark', (string) $post->ID );
		$stamp = \Courtneyr\Child\Stamps\render(
			array(
				'shape'  => 'rect',
				'big'    => $host,
				'ink'    => \Courtneyr\Child\Stamps\INKS[ \Courtneyr\Child\Stamps\pick( $seed, 0, 3 ) ],
				'tilt'   => \Courtneyr\Child\Stamps\pick( $seed, 6, \Courtneyr\Child\Stamps\TILTS ),
				'family' => 'bookmark',
			)
		);
		$html .= '<span class="cr-bookmark-stream__stamp" aria-hidden="true">' . $stamp . '</span>' . "\n";
	}
	$html .= hidden_cite( $facts ) . "\n";
	$html .= '</article>';
	return $html;
}

/**
 * Route a bookmark stream card to its archive or Stream adapter.
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

	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	$archive    = false !== strpos( $class_name, ITEM_STYLE ) || is_bookmark_archive();
	if ( ! $archive ) {
		$is_stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) && \Courtneyr\Child\HomeSections\is_stream_surface();
		if ( ! $is_stream ) {
			return $html;
		}
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'bookmark', 'kind', $post ) ) {
		return $html;
	}

	if ( str_contains( $html, 'pk-card--protected' ) ) {
		return locked_card( $html );
	}

	$facts = facts( $post->ID );
	if ( array() === $facts ) {
		return $html;
	}

	return $archive ? archive_card( $post, $facts, $block ) : stream_card( $post, $facts, $block );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\dispatch', 10, 3 );

/**
 * Load bookmark styles where a bookmark card can appear.
 *
 * @return void
 */
function enqueue_styles(): void {
	$is_stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) && \Courtneyr\Child\HomeSections\is_stream_surface();
	if ( ! is_admin() && ! is_bookmark_archive() && ! is_bookmark_single() && ! $is_stream ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-bookmark',
		COURTNEYR_CHILD_URI . '/assets/css/cr-bookmark.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
