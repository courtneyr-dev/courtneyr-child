<?php
/**
 * Server render for courtneyr/series-guide. Markup lives in inc/series-views.php.
 *
 * @package CourtneyrChild
 *
 * @var array    $attributes Block attributes (`view`).
 * @var string   $content    Inner content (none).
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo \Courtneyr\Child\Series\render_view( (string) ( $attributes['view'] ?? 'status' ), $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in inc/series-views.php.
