<?php
/**
 * Contact form block — server render.
 *
 * Delegates markup and submission handling to the site's contact-form plugin
 * so the existing shortcode remains the single form implementation.
 *
 * @var array    $attributes Block attributes (className and anchor).
 * @var string   $content    Inner content (none).
 * @var WP_Block $block      Block instance.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! shortcode_exists( 'cr_contact_form' ) && ! current_user_can( 'edit_posts' ) ) {
	return;
}

$courtneyr_child_contact_wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cr-contact-form' ) );
echo '<div ' . $courtneyr_child_contact_wrapper_attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core returns escaped block support attributes.
if ( shortcode_exists( 'cr_contact_form' ) ) {
	echo do_shortcode( '[cr_contact_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The trusted site plugin escapes every value in its fixed shortcode markup.
} else {
	echo '<p class="cr-contact__notice cr-contact__notice--err" role="status">' . esc_html__( "The form needs the site's contact-form plugin.", 'courtneyr-child' ) . '</p>';
}
echo '</div>';
