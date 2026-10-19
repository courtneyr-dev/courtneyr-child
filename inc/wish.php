<?php
/**
 * Wish archive cards and shared wish helpers (PKIW issue 238).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Wish;

use function Courtneyr\Child\HomeSections\is_stream_surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND           = 'wish';
const ITEM_STYLE     = 'cr-wish-listing';
const ITEM_CLASS     = 'is-style-cr-wish-listing';
const STYLE_HANDLE   = 'courtneyr-wish';
const EAGER_SECTIONS = 3;

/** Whether the current request is a Stream surface. */
function on_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) && is_stream_surface();
}

/** Register the wish listing style. */
function register_item_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => ITEM_STYLE,
				'label' => __( 'Wish listing', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_item_style' );

/** Attach wish paint to the plugin's card block. */
function register_card_style(): void {
	if ( ! defined( 'COURTNEYR_CHILD_DIR' ) || ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) || ! function_exists( 'wp_enqueue_block_style' ) ) {
		return;
	}
	$file_path = COURTNEYR_CHILD_DIR . '/assets/css/cr-wish.css';
	if ( ! is_readable( $file_path ) ) {
		return;
	}
	wp_enqueue_block_style(
		'post-kinds-indieweb/wish-card',
		array(
			'handle' => STYLE_HANDLE,
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-wish.css',
			'path'   => $file_path,
			'ver'    => COURTNEYR_CHILD_VERSION,
			'deps'   => array( 'courtneyr-paper', 'courtneyr-nav' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style', 10 );

/** Whether this request is the wish archive. */
function is_wish_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a wish single. */
function is_wish_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Resolve the post ID from block context.
 *
 * @param mixed $instance Block instance.
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Whether a stream-card belongs to the wish archive treatment.
 *
 * @param array $block Parsed block.
 */
function is_archive_item( array $block ): bool {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	return false !== strpos( $class_name, ITEM_CLASS ) || is_wish_archive();
}

/**
 * Return public plugin facts for a wish.
 *
 * @param int $post_id Post ID.
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Mark the wish archive body.
 *
 * @param array $classes Body classes.
 */
function body_class( array $classes ): array {
	if ( is_wish_archive() && ! in_array( 'cr-wish-archive', $classes, true ) ) {
		$classes[] = 'cr-wish-archive';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Read the rendered title heading level.
 *
 * @param string $html Rendered card HTML.
 */
function heading_level( string $html ): int {
	if ( preg_match( '/<h([2-4])\\b[^>]*\\bclass="[^"]*(?<![\\w-])pk-title(?![\\w-])[^"]*"/i', $html, $match ) ) {
		return max( 2, min( 4, (int) $match[1] ) );
	}
	return 3;
}

/**
 * Request-scoped eager-loading state.
 *
 * @param bool $reset Whether to clear the state.
 */
function eager_state( bool $reset = false ): array {
	if ( $reset || ! isset( $GLOBALS['courtneyr_child_wish_eager'] ) || ! is_array( $GLOBALS['courtneyr_child_wish_eager'] ) ) {
		$GLOBALS['courtneyr_child_wish_eager'] = array();
	}
	return $GLOBALS['courtneyr_child_wish_eager'];
}

/**
 * Whether this type's first picture should load eagerly.
 *
 * @param string $type_key Normalized wish type key.
 */
function archive_picture_loading( string $type_key ): bool {
	if ( (int) get_query_var( 'paged', 1 ) >= 2 ) {
		return false;
	}
	$state = eager_state();
	if ( in_array( $type_key, $state, true ) ) {
		return false;
	}
	$state[] = $type_key;

	$GLOBALS['courtneyr_child_wish_eager'] = $state;
	return count( $state ) <= EAGER_SECTIONS;
}

/**
 * Build the typographic picture fallback.
 *
 * @param string $title Listing title.
 */
function typographic_tile( string $title ): string {
	return '<div class="cr-wish-listing__tile" aria-hidden="true"><span>' . esc_html( $title ) . '</span></div>';
}

/**
 * Build one archive listing from public facts.
 *
 * @param \WP_Post $post  Wish post.
 * @param array    $facts Public wish facts.
 * @param int      $level Heading level.
 */
function archive_item( \WP_Post $post, array $facts, int $level ): string {
	$stored_title = trim( (string) get_the_title( $post ) );
	$permalink    = esc_url( (string) get_permalink( $post ) );
	$has_facts    = array() !== $facts;
	if ( $has_facts ) {
		$name       = trim( (string) ( $facts['name'] ?? '' ) );
		$name_kind  = (string) ( $facts['name_kind'] ?? '' );
		$title_name = 'untitled' !== $name_kind;
	} else {
		$title_name = '' !== $stored_title;
		$name       = $title_name
			? $stored_title
			: sprintf( /* translators: %s: wish post date. */ __( 'Wish, %s', 'courtneyr-child' ), get_the_date( '', $post ) );
	}
	if ( '' === $name ) {
		$name = __( 'Wish', 'courtneyr-child' );
	}

	$classes = $title_name ? 'u-url p-name' : 'u-url';
	$media   = '';
	if ( $has_facts ) {
		$picture = is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array();
		$url     = (string) ( $picture['url'] ?? '' );
		if ( '' !== $url ) {
			$eager      = archive_picture_loading( (string) ( $facts['type_key'] ?? '' ) );
			$dimensions = '';
			$attachment = (int) ( $picture['attachment_id'] ?? 0 );
			if ( $attachment > 0 && function_exists( 'wp_get_attachment_image_src' ) ) {
				$source = wp_get_attachment_image_src( $attachment, 'medium' );
				if ( is_array( $source ) && ! empty( $source[1] ) && ! empty( $source[2] ) ) {
					$dimensions = ' width="' . (int) $source[1] . '" height="' . (int) $source[2] . '"';
				}
			}
			$image = '<img class="cr-wish-listing__image u-photo" src="' . esc_url( $url ) . '" alt=""' . $dimensions
				. ' loading="' . ( $eager ? 'eager' : 'lazy' ) . '"' . ( $eager ? ' fetchpriority="high"' : '' )
				. ' decoding="async"' . ( ! empty( $picture['remote'] ) ? ' referrerpolicy="no-referrer"' : '' ) . '>';
			$media = '<div class="cr-wish-listing__media" data-cr-cover-fallback>' . $image . typographic_tile( $name ) . '</div>';
		}
	}
	if ( '' === $media ) {
		$media = '<div class="cr-wish-listing__media cr-wish-listing__media--tile">' . typographic_tile( $name ) . '</div>';
	}

	$priority = $has_facts ? (string) ( $facts['priority'] ?? '' ) : '';
	$attrs    = '' !== $priority ? ' data-priority="' . esc_attr( $priority ) . '"' : '';
	$out      = '<article class="cr-wish-listing pk-card--stream"' . $attrs . '>' . $media
		. '<h' . $level . ' class="cr-wish-listing__title"><a class="' . esc_attr( $classes ) . '" href="' . $permalink . '">' . esc_html( $name ) . '</a></h' . $level . '>';
	if ( $has_facts && '' !== (string) ( $facts['priority_text'] ?? '' ) ) {
		$out .= '<p class="cr-wish-listing__priority">' . esc_html( (string) $facts['priority_text'] ) . '</p>';
	}
	$item_url = $has_facts ? esc_url( (string) ( $facts['url'] ?? '' ) ) : '';
	if ( '' !== $item_url ) {
		$out .= '<data class="u-wish-of" value="' . $item_url . '" hidden></data>';
	}
	return $out . '</article>';
}

/**
 * Replace plugin cards with archive listings.
 *
 * @param string $html     Rendered card HTML.
 * @param array  $block    Parsed block.
 * @param mixed  $instance Block instance.
 */
function dispatch( string $html, array $block, $instance ): string {
	if ( '' === $html || ! is_archive_item( $block ) ) {
		return $html;
	}
	$post_id = block_post_id( $instance );
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( KIND, 'kind', $post ) || post_password_required( $post ) || false !== strpos( $html, 'pk-card--protected' ) ) {
		return $html;
	}
	return archive_item( $post, facts( $post->ID ), heading_level( $html ) );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\dispatch', 10, 3 );

/** Whether wish paint should load on this request. */
function should_load_styles(): bool {
	return is_admin() || is_wish_archive() || is_wish_single() || on_stream_surface();
}

/**
 * Register the pager sheet the wish sheet depends on, on wish views only.
 *
 * A missing dependency would stop the wish sheet from printing. The handle,
 * file and path data match the nav module's block-style registration, and a
 * handle that already exists is kept.
 */
function register_nav_dependency(): void {
	if ( function_exists( 'wp_style_is' ) && wp_style_is( 'courtneyr-nav', 'registered' ) ) {
		return;
	}
	if ( ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) || ! defined( 'COURTNEYR_CHILD_DIR' ) || ! function_exists( 'wp_register_style' ) ) {
		return;
	}
	wp_register_style( 'courtneyr-nav', COURTNEYR_CHILD_URI . '/assets/css/cr-nav.css', array(), COURTNEYR_CHILD_VERSION );
	if ( function_exists( 'wp_style_add_data' ) ) {
		wp_style_add_data( 'courtneyr-nav', 'path', COURTNEYR_CHILD_DIR . '/assets/css/cr-nav.css' );
	}
}

/** Load wish paint wherever wish markup can render. */
function enqueue_styles(): void {
	if ( ! should_load_styles() ) {
		return;
	}
	if ( ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) ) {
		return;
	}
	register_nav_dependency();
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-wish.css',
		array( 'courtneyr-paper', 'courtneyr-nav' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
