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
