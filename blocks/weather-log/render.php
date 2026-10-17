<?php
/**
 * Weather log block — server render.
 *
 * Renders the weather station log for the loop or singular post through the
 * theme adapter, so the editor preview and front end use the same privacy-
 * gated facts and markup.
 *
 * @var array    $attributes Block attributes (className).
 * @var string   $content    Inner content (none).
 * @var WP_Block $block      Block instance with post context.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$courtneyr_child_weather_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
if ( ! $courtneyr_child_weather_post_id || ! function_exists( '\\Courtneyr\\Child\\Weather\\log_html' ) ) {
	return;
}
$courtneyr_child_weather_log = \Courtneyr\Child\Weather\log_html( $courtneyr_child_weather_post_id );
if ( '' === $courtneyr_child_weather_log ) {
	return;
}
echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'cr-weather-station' ) ) . '>' . $courtneyr_child_weather_log . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- log_html() escapes each value and returns fixed theme markup.
