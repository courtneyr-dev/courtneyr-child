<?php
/**
 * courtneyr/archive-identity — server render.
 *
 * @var array    $attributes Block attributes (none).
 * @var string   $content    Inner content (none).
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cr_identity = \Courtneyr\Child\Archives\resolve_identity();
// The glyph is decorative: the archive's h1 names it. A kind archive prints the glyph alone.
printf(
	'<div class="cr-archive-identity cr-archive-identity--%1$s%6$s" style="--cr-archive-accent: %2$s;"><span class="cr-archive-identity__glyph media-glyph cr-icon-avatar cr-icon-avatar--%3$s" aria-hidden="true">%4$s</span>%5$s</div>',
	esc_attr( $cr_identity['family'] ),
	esc_attr( $cr_identity['accent'] ),
	esc_attr( $cr_identity['type'] ),
	$cr_identity['glyph'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built from escaped parts.
	'' !== $cr_identity['kicker'] ? '<p class="cr-archive-identity__kicker is-style-cr-tape-label">' . esc_html( $cr_identity['kicker'] ) . '</p>' : '',
	$cr_identity['kind'] ? ' cr-archive-identity--kind' : ''
);
