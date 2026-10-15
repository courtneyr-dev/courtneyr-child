<?php
/**
 * Favorite archive: star-chart bands, clipping items and the shared helpers.
 *
 * The favorite archive is the plugin's month-section loop (archive-sections)
 * with each item painted as a paper clipping and a flat yellow star. The item
 * is rebuilt from `\PKIW\kind_facts()` and prints the linked title only: the
 * author, date, description, image and external link live on the single
 * (issue 259, Courtney's ruling on archive metadata in issue 227). Theme code
 * never reads block attributes or post meta for a favorite.
 *
 * The single sheet is inc/single-favorite.php and the Stream card is
 * inc/stream-favorite.php. Both call the helpers here, so functions.php loads
 * the three files together.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Favorite;

use function Courtneyr\Child\HomeSections\is_stream_surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The kind slug this lane paints.
 */
const KIND = 'favorite';

/**
 * The stream-card block style name used by the favorite archive.
 */
const ITEM_STYLE = 'cr-favorite-clipping';

/**
 * The class WordPress gives the favorite archive's block style.
 */
const ITEM_CLASS = 'is-style-cr-favorite-clipping';

/**
 * Stylesheet handle.
 */
const STYLE_HANDLE = 'courtneyr-favorite';

/**
 * The four rotation tokens a star may take.
 */
const TILTS = array( '--cr-rotate-1', '--cr-rotate-2', '--cr-rotate-neg', '--cr-rotate-neg2' );

/**
 * The star outline both the archive and the single print, on a 24-unit box.
 */
const STAR_PATH = 'M12 2l3 6.5 7 .6-5.3 4.6 1.6 6.8L12 17l-6.9 3.5 1.6-6.8L1.4 9.1l7-.6z';

/**
 * Register the clipping style for the plugin stream card.
 *
 * @return void
 */
function register_item_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => ITEM_STYLE,
				'label' => __( 'Favorite clipping', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_item_style' );

/**
 * Whether a post is a favorite.
 *
 * @param int|\WP_Post $post Post or ID.
 * @return bool
 */
function is_favorite( $post ): bool {
	return function_exists( 'has_term' ) && has_term( KIND, 'kind', $post );
}

/**
 * The plugin's facts for a favorite, or an empty array.
 *
 * The plugin returns no facts for a post behind a password or one the
 * requester can't read, so an empty array is the protected case too.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) || ! is_favorite( $post_id ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
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
 * The heading level a stream-card block asked for, clamped to 2-4.
 *
 * @param array<string, mixed> $block   Parsed block.
 * @param int                  $fallback Level when the block names none.
 * @return int
 */
function heading_level( array $block, int $fallback ): int {
	return max( 2, min( 4, (int) ( $block['attrs']['headingLevel'] ?? $fallback ) ) );
}

/**
 * The visible kind label, from the plugin when it is loaded.
 *
 * @param string $context Label context the plugin passes to `pkiw_kind_label`.
 * @return string
 */
function kind_label( string $context ): string {
	if ( function_exists( '\\PKIW\\get_kind_label' ) ) {
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- the plugin's own string and domain.
		return (string) \PKIW\get_kind_label( __( 'Favorite', 'post-kinds-for-indieweb-in-block-themes' ), KIND, $context );
	}
	return __( 'Favorite', 'courtneyr-child' );
}

/**
 * The flat star as inline art.
 *
 * Fill and edge come from the stylesheet (selective yellow, 2px Russian
 * violet), so the shape holds no color of its own.
 *
 * @return string
 */
function star_svg(): string {
	return '<svg class="cr-fav-star__shape" viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="' . STAR_PATH . '"/></svg>';
}

/**
 * The tilt token of the previous item in the current Post Template render.
 *
 * @param string|null $set New value, or null to read.
 * @return string
 */
function last_tilt( ?string $set = null ): string {
	static $last = '';
	if ( null !== $set ) {
		$last = $set;
	}
	return $last;
}

/**
 * Start each Post Template render with no previous star.
 *
 * @param array<string, mixed> $parsed_block Parsed block.
 * @return array<string, mixed>
 */
function reset_tilt( array $parsed_block ): array {
	if ( 'core/post-template' === ( $parsed_block['blockName'] ?? '' ) ) {
		last_tilt( '' );
	}
	return $parsed_block;
}
add_filter( 'render_block_data', __NAMESPACE__ . '\\reset_tilt' );

/**
 * One of the four rotation tokens for a post's star.
 *
 * The token comes from the post ID and steps on by one when it matches the
 * previous item's, so no two neighbours lean the same way.
 *
 * @param int    $post_id  Post ID.
 * @param string $previous Previous item's token, or ''.
 * @return string A custom property name from TILTS.
 */
function star_tilt( int $post_id, string $previous = '' ): string {
	$count = count( TILTS );
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) ) {
		$index = \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( 'favorite-star', (string) $post_id ), 3, $count );
	} else {
		$index = abs( $post_id ) % $count;
	}
	$token = TILTS[ $index ];
	return $token === $previous ? TILTS[ ( $index + 1 ) % $count ] : $token;
}

/**
 * Normalize a name for duplicate detection.
 *
 * @param string $name Visible name.
 * @return string
 */
function comparable( string $name ): string {
	$name = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $name ) ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
}

/**
 * Whether another favorite on this page prints the same visible name.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function repeats_name( int $post_id ): bool {
	static $cache = array();

	$query = $GLOBALS['wp_query'] ?? null;
	$posts = is_object( $query ) && isset( $query->posts ) && is_array( $query->posts ) ? $query->posts : array();
	$ids   = array();
	foreach ( $posts as $post ) {
		$ids[] = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
	}
	$key = implode( ',', $ids );

	if ( ! isset( $cache[ $key ] ) ) {
		$names = array();
		foreach ( $ids as $id ) {
			$name = comparable( (string) ( facts( $id )['name'] ?? '' ) );
			if ( '' !== $name ) {
				$names[ $id ] = $name;
			}
		}
		$cache[ $key ] = array(
			'names'  => $names,
			'counts' => array_count_values( $names ),
		);
	}

	$name = $cache[ $key ]['names'][ $post_id ] ?? '';
	return '' !== $name && 1 < ( $cache[ $key ]['counts'][ $name ] ?? 0 );
}

/**
 * The id of the month heading a post sits under, as the plugin prints it.
 *
 * @param int $post_id Post ID.
 * @return string Heading id, or '' when the plugin gives no month.
 */
function month_heading_id( int $post_id ): string {
	if ( ! class_exists( '\\PKIW\\Grouped_Archive' ) || ! method_exists( '\\PKIW\\Grouped_Archive', 'group_of_post' ) ) {
		return '';
	}
	$group = \PKIW\Grouped_Archive::group_of_post( KIND, $post_id );
	if ( ! is_object( $group ) || ! method_exists( $group, 'slug' ) ) {
		return '';
	}
	$slug = strtolower( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', str_replace( '%', '', (string) $group->slug() ) ) );
	return '' === $slug ? '' : 'pkiw-group-' . $slug;
}

/**
 * Whether a rendered stream-card block carries the clipping style.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return bool
 */
function is_clipping( array $block ): bool {
	return false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), ITEM_CLASS );
}

/**
 * One archive item: a star and a clipping that holds the title and nothing else.
 *
 * The cited URL rides as hidden data so the entry still says what it
 * favorites. The plugin adds the entry's hidden url, date and author after
 * this markup.
 *
 * @param array<string, mixed> $facts Favorite facts.
 * @param int                  $level Heading level.
 * @param int                  $post_id Post ID.
 * @param string               $tilt  Rotation token for the star.
 * @return string
 */
function item_html( array $facts, int $level, int $post_id, string $tilt ): string {
	$name   = (string) ( $facts['name'] ?? '' );
	$url    = (string) ( $facts['url'] ?? '' );
	$link   = ' href="' . esc_url( (string) get_permalink( $post_id ) ) . '"';
	$inner  = esc_html( $name );
	$escurl = '' === $url ? '' : esc_url( $url );

	if ( repeats_name( $post_id ) ) {
		$month = month_heading_id( $post_id );
		if ( '' !== $month ) {
			$title_id = 'cr-fav-title-' . $post_id;
			$link    .= ' aria-labelledby="' . esc_attr( $title_id . ' ' . $month ) . '"';
			$inner    = '<span id="' . esc_attr( $title_id ) . '">' . $inner . '</span>';
		}
	}

	$classes = 'cr-fav-title' . ( ! empty( $facts['name_is_title'] ) ? ' p-name' : '' );
	$out     = '<div class="cr-fav-item">';
	$out    .= '<span class="cr-fav-star" aria-hidden="true" style="--cr-fav-tilt:var(' . esc_attr( $tilt ) . ')">' . star_svg() . '</span>';
	$out    .= '<div class="cr-fav-clipping"><h' . $level . ' class="' . esc_attr( $classes ) . '"><a class="u-url cr-fav-link"' . $link . '>' . $inner . '</a></h' . $level . '></div>';
	if ( '' !== $escurl ) {
		$out .= '<data class="u-favorite-of" value="' . $escurl . '" hidden></data>';
	}
	return $out . '</div>';
}

/**
 * Paint a favorite's stream-card block as a month-band clipping.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block.
 * @param \WP_Block $instance Block instance with the Query Loop's post ID.
 * @return string
 */
function item( string $html, array $block, $instance ): string {
	if ( '' === $html || ! is_clipping( $block ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! is_favorite( $post ) ) {
		return $html;
	}

	// A protected favorite keeps the plugin's generic protected card: the title
	// link and nothing else. No clipping, star or tilt, and no facts are read.
	$facts = post_password_required( $post ) ? array() : facts( $post_id );
	if ( array() === $facts ) {
		return $html;
	}

	$tilt = star_tilt( $post_id, last_tilt() );
	last_tilt( $tilt );

	return item_html( $facts, heading_level( $block, 3 ), $post_id, $tilt );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\item', 10, 3 );

/**
 * Whether the favorite stylesheet has anywhere to paint.
 *
 * @return bool
 */
function needs_styles(): bool {
	if ( is_admin() ) {
		return true;
	}
	if ( \is_tax( 'kind', KIND ) ) {
		return true;
	}
	if ( \is_singular( 'post' ) && is_favorite( (int) get_queried_object_id() ) ) {
		return true;
	}
	return is_stream_surface();
}

/**
 * Load the favorite stylesheet where a favorite can appear.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! needs_styles() ) {
		return;
	}
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-favorite.css',
		array( 'courtneyr-paper', 'courtneyr-sticker' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
