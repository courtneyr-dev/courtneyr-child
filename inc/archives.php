<?php
/**
 * Archive family (0.7.46): one identity block, one stylesheet, and the
 * template family that gives every category, format and kind archive the
 * surface it belongs to. Blog-family archives (categories, dates, tags,
 * authors) keep the blog grid; Stream-family archives (kinds and the
 * site's Stream formats) render the plugin's stream cards in the same
 * collage the Stream page and the homepage lane use.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Archives;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Kinds without a sprite of their own borrow the nearest format glyph/colour. */
const KIND_TYPE_ALIASES = array(
	'article'  => 'blog',
	'note'     => 'status',
	'photo'    => 'image',
	'read'     => 'book',
	'listen'   => 'audio',
	'jam'      => 'audio',
	'watch'    => 'video',
	'play'     => 'video',
	'checkin'  => 'event',
	'rsvp'     => 'event',
	'favorite' => 'like',
);

/**
 * Stream formats from the site policy (cr-content-surfaces); falls back to
 * every format but standard/chat when the MU plugin is absent.
 *
 * @return string[]
 */
function stream_formats(): array {
	if ( defined( 'CR_STREAM_FORMATS' ) ) {
		return (array) \CR_STREAM_FORMATS;
	}
	return array( 'status', 'image', 'gallery', 'aside', 'link', 'quote', 'video', 'audio' );
}

/**
 * Sprite types the theme ships (assets/svg/icons.svg#post-icon-*).
 *
 * @return string[]
 */
function sprite_types(): array {
	$solid   = defined( '\Courtneyr\Child\Interactivity\STREAM_AVATAR_SOLID' ) ? \Courtneyr\Child\Interactivity\STREAM_AVATAR_SOLID : array();
	$outline = defined( '\Courtneyr\Child\Interactivity\STREAM_AVATAR_OUTLINE' ) ? \Courtneyr\Child\Interactivity\STREAM_AVATAR_OUTLINE : array();
	return array_merge( $solid, $outline );
}

/**
 * Sprite glyph markup for a type.
 */
function sprite_glyph( string $type ): string {
	return sprintf(
		'<svg viewBox="0 0 24 24" focusable="false"><use href="%1$s/assets/svg/icons.svg#post-icon-%2$s"></use></svg>',
		esc_url( get_stylesheet_directory_uri() ),
		esc_attr( $type )
	);
}

/**
 * Resolve the identity of the archive being rendered (or previewed).
 *
 * @return array{family:string,kicker:string,type:string,glyph:string,accent:string}
 */
function resolve_identity(): array {
	$sprites = sprite_types();
	$object  = get_queried_object();
	$family  = 'blog';
	$kicker  = __( 'Field notes', 'courtneyr-child' );
	$type    = 'blog';
	$glyph   = '';

	if ( $object instanceof \WP_Term ) {
		if ( 'kind' === $object->taxonomy ) {
			$family = 'stream';
			$kicker = __( 'Stream · Kind', 'courtneyr-child' );
			$slug   = $object->slug;
			$type   = KIND_TYPE_ALIASES[ $slug ] ?? ( in_array( $slug, $sprites, true ) ? $slug : 'blog' );
			if ( function_exists( '\PKIW\get_kind_icon_svg' ) ) {
				$glyph = \PKIW\get_kind_icon_svg( $slug );
			}
		} elseif ( 'post_format' === $object->taxonomy ) {
			$slug   = str_replace( 'post-format-', '', $object->slug );
			$family = in_array( $slug, stream_formats(), true ) ? 'stream' : 'blog';
			$kicker = 'stream' === $family ? __( 'Stream · Format', 'courtneyr-child' ) : __( 'Blog · Format', 'courtneyr-child' );
			$type   = in_array( $slug, $sprites, true ) ? $slug : 'blog';
		} elseif ( 'category' === $object->taxonomy ) {
			$kicker = __( 'Blog · Topic', 'courtneyr-child' );
			$type   = in_array( $object->slug, $sprites, true ) ? $object->slug : 'blog';
		} elseif ( 'post_tag' === $object->taxonomy ) {
			$kicker = __( 'Blog · Tag', 'courtneyr-child' );
		} else {
			$kicker = __( 'Blog · Archive', 'courtneyr-child' );
		}
	} elseif ( $object instanceof \WP_Post_Type ) {
		if ( 'web-story' === $object->name ) {
			$family = 'stories';
			$kicker = __( 'Stories', 'courtneyr-child' );
			$type   = 'gallery';
		} else {
			$kicker = $object->labels->name;
		}
	} elseif ( is_date() ) {
		$kicker = __( 'Blog · Date', 'courtneyr-child' );
	} elseif ( is_author() ) {
		$kicker = __( 'Blog · Author', 'courtneyr-child' );
	} elseif ( is_search() ) {
		$kicker = __( 'Search', 'courtneyr-child' );
	} elseif ( isset( $_GET['cr_template'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- editor preview hint, read-only.
		// Site-editor preview: no queried object, so show the family the template serves.
		$template = sanitize_key( wp_unslash( (string) $_GET['cr_template'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( str_starts_with( $template, 'taxonomy-kind' ) ) {
			$family = 'stream';
			$kicker = __( 'Stream · Kind', 'courtneyr-child' );
			$type   = 'status';
		} elseif ( str_starts_with( $template, 'taxonomy-post_format' ) ) {
			$family = 'stream';
			$kicker = __( 'Stream · Format', 'courtneyr-child' );
			$type   = 'quote';
		} elseif ( str_starts_with( $template, 'category' ) ) {
			$kicker = __( 'Blog · Topic', 'courtneyr-child' );
		} elseif ( str_starts_with( $template, 'archive-web-story' ) ) {
			$family = 'stories';
			$kicker = __( 'Stories', 'courtneyr-child' );
			$type   = 'gallery';
		}
	}

	if ( '' === $glyph ) {
		$glyph = sprite_glyph( $type );
	}
	return array(
		'family' => $family,
		'kicker' => $kicker,
		'type'   => $type,
		'glyph'  => $glyph,
		'accent' => sprintf( 'var(--cr-type-%s, var(--cr-cerulean))', $type ),
	);
}

/**
 * Register the identity block.
 */
function register_blocks(): void {
	register_block_type( COURTNEYR_CHILD_DIR . '/blocks/archive-identity' );
}
add_action( 'init', __NAMESPACE__ . '\\register_blocks' );

/**
 * Archive-family stylesheet: front end on archives, search and the Stream
 * page; always in the editor canvas (enqueue_block_assets runs there).
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_archive() && ! is_search() && ! is_page( 'stream' ) ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-archives',
		COURTNEYR_CHILD_URI . '/assets/css/cr-archives.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * The Stories archive is theme-rendered (poster grid, playback on the story
 * URL), so the plugin's standalone amp-story-player assets are dead weight
 * there — and a third-party host (cdn.ampproject.org) the page never uses.
 */
function dequeue_story_player_on_archive(): void {
	if ( ! is_post_type_archive( 'web-story' ) ) {
		return;
	}
	wp_dequeue_script( 'standalone-amp-story-player' );
	wp_dequeue_style( 'standalone-amp-story-player' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\dequeue_story_player_on_archive', 100 );

/**
 * The plugin enqueues the player while rendering the archive's excerpt/content
 * filters (after wp_enqueue_scripts), so also drop it at print time and remove
 * its dns-prefetch hint.
 */
function drop_story_player_at_print(): void {
	if ( is_post_type_archive( 'web-story' ) ) {
		wp_dequeue_script( 'standalone-amp-story-player' );
		wp_dequeue_style( 'standalone-amp-story-player' );
	}
}
add_action( 'wp_print_scripts', __NAMESPACE__ . '\\drop_story_player_at_print', 100 );
add_action( 'wp_print_styles', __NAMESPACE__ . '\\drop_story_player_at_print', 100 );
add_action( 'wp_print_footer_scripts', __NAMESPACE__ . '\\drop_story_player_at_print', 1 );

/**
 * @param string[] $urls Resource hint URLs.
 * @param string   $relation_type Hint type.
 * @return string[]
 */
function drop_story_player_hints( array $urls, string $relation_type ): array {
	if ( 'dns-prefetch' === $relation_type && is_post_type_archive( 'web-story' ) ) {
		$urls = array_values( array_filter( $urls, static fn( $u ) => ! str_contains( is_array( $u ) ? (string) ( $u['href'] ?? '' ) : (string) $u, 'ampproject.org' ) ) );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', __NAMESPACE__ . '\\drop_story_player_hints', 20, 2 );
