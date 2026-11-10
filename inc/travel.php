<?php
/**
 * Trip and itinerary archive, single and editor paint.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Travel;

use const Courtneyr\Child\Stamps\GLYPHS;
use const Courtneyr\Child\Stamps\INKS;
use const Courtneyr\Child\Stamps\TILTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TRIP            = 'trip';
const ITINERARY       = 'itinerary';
const TRIP_ENTRY      = 'post-kinds-indieweb/trip-entry';
const ITINERARY_ENTRY = 'post-kinds-indieweb/itinerary-entry';
const TRIP_CARD       = 'post-kinds-indieweb/trip-card';
const ITINERARY_CARD  = 'post-kinds-indieweb/itinerary-card';
const TRIP_STYLE      = 'cr-passport';
const ITINERARY_STYLE = 'cr-wallet';
const STYLE_HANDLE    = 'courtneyr-travel';

/** Whether the request is one of the two travel archives. */
function is_travel_archive(): bool {
	return is_tax( 'kind', TRIP ) || is_tax( 'kind', ITINERARY );
}

/** Whether the queried post is a trip or itinerary. */
function is_travel_single(): bool {
	if ( ! is_singular( 'post' ) ) {
		return false;
	}
	$post_id = get_queried_object_id();
	return has_term( TRIP, 'kind', $post_id ) || has_term( ITINERARY, 'kind', $post_id );
}

/** Whether the request is a Stream surface. */
function is_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
}

/** Register the shared navigation dependency when the nav module has not done so. */
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

/** Register both archive entry styles and their editor paint. */
function register_block_styles(): void {
	if ( ! class_exists( '\\WP_Block_Type_Registry' ) ) {
		return;
	}
	$registry = \WP_Block_Type_Registry::get_instance();
	register_nav_dependency();
	$styles = array(
		TRIP_ENTRY      => TRIP_STYLE,
		ITINERARY_ENTRY => ITINERARY_STYLE,
	);
	foreach ( $styles as $block => $style ) {
		if ( ! $registry->is_registered( $block ) ) {
			continue;
		}
		register_block_style(
			$block,
			array(
				'name'  => $style,
				'label' => TRIP_ENTRY === $block ? __( 'Passport', 'courtneyr-child' ) : __( 'Ticket wallet', 'courtneyr-child' ),
			)
		);
	}
	foreach ( array( TRIP_ENTRY, ITINERARY_ENTRY, TRIP_CARD, ITINERARY_CARD ) as $block ) {
		if ( ! $registry->is_registered( $block ) ) {
			continue;
		}
		wp_enqueue_block_style(
			$block,
			array(
				'handle' => STYLE_HANDLE,
				'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-travel.css',
				'deps'   => array( 'courtneyr-paper', 'courtneyr-nav' ),
				'ver'    => COURTNEYR_CHILD_VERSION,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles', 20 );

/**
 * Add one deterministic passport stamp to a public trip archive entry.
 *
 * @param string   $html Existing badge markup.
 * @param \WP_Post $post Trip post.
 */
function entry_stamp( string $html, \WP_Post $post ): string {
	$seed  = \Courtneyr\Child\Stamps\seed( (string) $post->ID );
	$glyph = array_keys( GLYPHS )[ \Courtneyr\Child\Stamps\pick( $seed, 9, count( GLYPHS ) ) ];
	return $html . \Courtneyr\Child\Stamps\render(
		array(
			'shape'  => 'seal',
			'big'    => 'TRIP',
			'glyph'  => $glyph,
			'ink'    => INKS[ \Courtneyr\Child\Stamps\pick( $seed, 3, count( INKS ) ) ],
			'tilt'   => \Courtneyr\Child\Stamps\pick( $seed, 6, TILTS ),
			'uid'    => 'cr-trip-' . $post->ID,
			'family' => 'trip-entry',
		)
	);
}
add_filter( 'pkiw_trip_entry_badge_html', __NAMESPACE__ . '\\entry_stamp', 10, 2 );

/**
 * Add a deterministic stamp whose text repeats only the visible stop label and day.
 *
 * @param string               $html    Existing stamp markup.
 * @param array<string, mixed> $stop    Visitor-safe stop facts.
 * @param int                  $post_id Post ID.
 */
function stop_stamp( string $html, array $stop, int $post_id ): string {
	if ( ( function_exists( 'is_feed' ) && is_feed() ) || ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) ) {
		return $html;
	}
	$label = trim( (string) ( $stop['label'] ?? '' ) );
	$day   = trim( (string) ( $stop['day_display'] ?? '' ) );
	if ( '' === $label && '' === $day ) {
		return $html;
	}
	$number = (int) ( $stop['number'] ?? 0 );
	$seed   = \Courtneyr\Child\Stamps\seed( (string) $post_id, (string) $number );
	return $html . \Courtneyr\Child\Stamps\render(
		array(
			'shape'  => 'rect',
			'mid'    => $label,
			'small'  => $day,
			'glyph'  => 'pin',
			'ink'    => INKS[ \Courtneyr\Child\Stamps\pick( $seed, 3, count( INKS ) ) ],
			'tilt'   => \Courtneyr\Child\Stamps\pick( $seed, 6, TILTS ),
			'family' => 'trip-stop',
		)
	);
}
add_filter( 'pkiw_trip_stop_stamp_html', __NAMESPACE__ . '\\stop_stamp', 10, 3 );

/** Load travel paint on each surface that can render a travel object. */
function enqueue(): void {
	if ( ! is_admin() && ! is_travel_archive() && ! is_travel_single() && ! is_stream_surface() ) {
		return;
	}
	register_nav_dependency();
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-travel.css',
		array( 'courtneyr-paper', 'courtneyr-nav' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue' );

/**
 * Keep the conditional theme and plugin travel sheets out of shared used CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return string[]
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-travel.css';
	$exclusions[] = 'travel-archive.css';
	$exclusions[] = 'travel-card.css';
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20 );
