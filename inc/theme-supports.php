<?php
/**
 * Theme supports — declares what the editor should expose.
 *
 * Most modern theme support is driven by theme.json (schema v3 with
 * appearanceTools: true), but a few features still require explicit
 * add_theme_support() calls. This file collects all of them.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\ThemeSupports;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme features that are not driven by theme.json.
 */
function setup(): void {
	// Title tag (required for proper <title> output).
	add_theme_support( 'title-tag' );

	// Featured images for posts and the 18 custom-template post types.
	add_theme_support( 'post-thumbnails' );

	// Block editor wide and full alignment.
	add_theme_support( 'align-wide' );

	// Allow themes to add HTML5 markup for these elements.
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);

	// Editor styles. The actual file is enqueued via wp_enqueue_block_style;
	// this support flag tells the editor to load editor-specific CSS.
	add_theme_support( 'editor-styles' );

	// Custom logo for the site identity block.
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 80,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// Responsive embeds (YouTube, Vimeo, etc. fluid in containers).
	add_theme_support( 'responsive-embeds' );

	// Automatic feed links in <head>.
	add_theme_support( 'automatic-feed-links' );

	// IndieWeb-friendly: support post formats so plugins like Post Formats
	// for Block Themes have a stable surface to extend.
	add_theme_support(
		'post-formats',
		array( 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat' )
	);
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\setup' );

/**
 * Remove WP-default emoji and other legacy bloat the zine aesthetic
 * does not need. Per-feature, easy to comment out individually.
 */
function trim_default_head_output(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	// wp-i18n inline localization is left intact — Perfmatters handles
	// the targeted ReferenceError fix on courtneyr.dev. See the design
	// system tokens.css for context.
}
add_action( 'init', __NAMESPACE__ . '\\trim_default_head_output' );

/**
 * Add aria-current="page" to the active navigation item — both forms.
 *
 * V0.5.84/v0.5.85: screen-reader users can't tell which page they're
 * on while navigating the menu without aria-current. This theme uses
 * the block-based navigation (core/navigation), which does NOT fire
 * the legacy nav_menu_link_attributes filter, so we register both:
 *
 *   1. nav_menu_link_attributes — for any wp_nav_menu()-based menus
 *      (widgets, classic menus, custom code paths). Cheap to register;
 *      no-op when not used.
 *
 *   2. render_block (core/navigation-link) — fires for the block-based
 *      navigation. We compare the link's URL with the current request
 *      URL and inject aria-current="page" into the rendered <a> tag
 *      when they match.
 *
 * WCAG SC 4.1.2 (Name, Role, Value) — orientation cue.
 *
 * @param array        $atts HTML attributes applied to the menu item's <a> element.
 * @param WP_Post|null $item Menu item data object.
 * @return array Filtered attributes with aria-current added when active.
 */
function add_aria_current_to_nav( $atts, $item ) {
	if ( isset( $item->classes ) && is_array( $item->classes ) ) {
		if ( in_array( 'current-menu-item', $item->classes, true ) ) {
			$atts['aria-current'] = 'page';
		} elseif ( in_array( 'current-menu-ancestor', $item->classes, true ) ) {
			$atts['aria-current'] = 'true';
		}
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', __NAMESPACE__ . '\\add_aria_current_to_nav', 10, 2 );

/**
 * Block-nav variant — adds aria-current="page" to the rendered <a>
 * when its URL matches the current request URL.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block data.
 * @return string Block HTML with aria-current added on a match.
 */
function add_aria_current_to_navigation_link( $block_content, $block ) {
	if ( ! isset( $block['blockName'] ) || 'core/navigation-link' !== $block['blockName'] ) {
		return $block_content;
	}
	$url = $block['attrs']['url'] ?? '';
	if ( '' === $url ) {
		return $block_content;
	}
	$current_path = isset( $_SERVER['REQUEST_URI'] )
		? \wp_parse_url( \esc_url_raw( \wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH )
		: '/';
	$link_path    = \wp_parse_url( $url, PHP_URL_PATH );
	if ( null === $link_path ) {
		return $block_content;
	}
	$current_path = '/' . trim( (string) $current_path, '/' );
	$link_path    = '/' . trim( (string) $link_path, '/' );
	if ( $current_path !== $link_path ) {
		return $block_content;
	}
	// Inject aria-current="page" into the first <a ...> in the block output.
	$replaced = \preg_replace(
		'/<a\b(?![^>]*\baria-current=)/',
		'<a aria-current="page"',
		$block_content,
		1
	);
	return is_string( $replaced ) ? $replaced : $block_content;
}
add_filter( 'render_block', __NAMESPACE__ . '\\add_aria_current_to_navigation_link', 10, 2 );
