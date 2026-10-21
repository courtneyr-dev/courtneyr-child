<?php
/**
 * Register the contact form block that delegates to the site's shortcode.
 *
 * The site plugin remains the single owner of the form markup and submission
 * handler; the theme provides a block placement surface and editor preview.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\ContactForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the contact form block. */
function register_block(): void {
	$block_path = COURTNEYR_CHILD_DIR . '/blocks/contact-form';
	if ( ! function_exists( 'register_block_type' ) || ! is_readable( $block_path . '/block.json' ) ) {
		return;
	}
	register_block_type( $block_path );
}
add_action( 'init', __NAMESPACE__ . '\\register_block' );
