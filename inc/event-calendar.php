<?php
/**
 * My Calendar paint on the Event archive.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\EventCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STYLE_HANDLE = 'courtneyr-event-calendar';

/**
 * Theme-token values for every variable My Calendar 3.8.11 defines.
 *
 * @return array<string, mixed>
 */
function variable_map(): array {
	return array(
		'--close-button'          => 'var(--cr-ut-orange)',
		'--search-highlight-bg '  => 'var(--cr-selective-yellow)',
		'--main-background'       => 'var(--cr-surface)',
		'--main-color'            => 'var(--cr-ink)',
		'--navbar-background'     => 'var(--cr-surface)',
		'--nav-button-bg'         => 'var(--cr-printer-ivory)',
		'--nav-button-color'      => 'var(--cr-russian-violet)',
		'--nav-button-border'     => 'var(--cr-russian-violet)',
		'--nav-input-border'      => 'var(--cr-russian-violet)',
		'--nav-input-background'  => 'var(--cr-printer-ivory)',
		'--nav-input-color'       => 'var(--cr-russian-violet)',
		'--grid-cell-border'      => 'var(--cr-periwinkle)',
		'--grid-header-border'    => 'var(--cr-russian-violet)',
		'--grid-header-color'     => 'var(--cr-russian-violet)',
		'--grid-weekend-color'    => 'var(--cr-russian-violet)',
		'--grid-header-bg'        => 'var(--cr-periwinkle)',
		'--grid-weekend-bg'       => 'var(--cr-periwinkle)',
		'--grid-cell-background'  => 'var(--cr-surface)',
		'--current-day-border'    => 'var(--cr-selective-yellow)',
		'--current-day-color'     => 'var(--cr-ink)',
		'--current-day-bg'        => 'var(--cr-surface)',
		'--date-has-events-bg'    => 'var(--cr-periwinkle)',
		'--date-has-events-color' => 'var(--cr-russian-violet)',
		'--primary-dark'          => 'var(--cr-russian-violet)',
		'--primary-light'         => 'var(--cr-printer-ivory)',
		'--secondary-light'       => 'var(--cr-surface)',
		'--secondary-dark'        => 'var(--cr-ink)',
		'--highlight-dark'        => 'var(--cr-russian-violet)',
		'--highlight-light'       => 'var(--cr-selective-yellow)',
		'text'                    => array(
			'--calendar-heading'    => 'var(--cr-text-3xl)',
			'--event-title'         => 'var(--cr-text-md)',
			'--grid-date'           => 'var(--cr-text-md)',
			'--grid-date-heading'   => 'var(--cr-text-md)',
			'--modal-title'         => 'var(--cr-text-xl)',
			'--navigation-controls' => 'var(--cr-text-md)',
			'--card-heading'        => 'var(--cr-text-lg)',
			'--list-date'           => 'var(--cr-text-lg)',
			'--author-card'         => 'var(--cr-text-sm)',
			'--single-event-title'  => 'var(--cr-text-xl)',
			'--mini-time-text'      => 'var(--cr-text-sm)',
			'--list-event-date'     => 'var(--cr-text-lg)',
			'--list-event-title'    => 'var(--cr-text-lg)',
		),
		'sizing'                  => array(
			'--grid-max-width' => 'var(--cr-measure-wide)',
			'--main-padding'   => 'var(--cr-space-lg)',
			'--main-margin'    => 'var(--cr-space-3xl) auto',
		),
		'list-presets'            => array(
			'--list-preset-border-color'          => 'var(--cr-border-strong)',
			'--list-preset-stripe-background'     => 'var(--cr-surface-soft)',
			'--list-preset-date-badge-background' => 'var(--cr-russian-violet)',
			'--list-preset-date-badge-color'      => 'var(--cr-printer-ivory)',
			'--list-preset-background'            => 'var(--cr-surface)',
			'--list-preset-color'                 => 'var(--cr-ink)',
		),
	);
}

/**
 * Replace My Calendar defaults with theme tokens while preserving its shape.
 *
 * @param array<string, mixed> $styles My Calendar variables.
 * @return array<string, mixed>
 */
function style_variables( array $styles ): array {
	foreach ( variable_map() as $key => $value ) {
		if ( is_array( $value ) ) {
			$styles[ $key ] = array_merge( is_array( $styles[ $key ] ?? null ) ? $styles[ $key ] : array(), $value );
		} else {
			$styles[ $key ] = $value;
		}
	}
	return $styles;
}
add_filter( 'mc_style_variables', __NAMESPACE__ . '\\style_variables' );

/**
 * Load calendar paint only when the dynamic block renders.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 */
function enqueue( string $html, array $block ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-event-calendar.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/event-calendar', __NAMESPACE__ . '\\enqueue', 10, 2 );

/**
 * Keep the conditional calendar sheet out of shared used CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return string[]
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-event-calendar.css';
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20 );
