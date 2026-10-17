<?php
/**
 * Weather archive, station log and Stream card adapters (issue 246).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Weather;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'weather';
const STYLE_HANDLE = 'courtneyr-weather';
const LOG_BLOCK    = 'courtneyr/weather-log';

/** Whether this request is the weather kind archive. */
function is_weather_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single weather post. */
function is_weather_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Attach the weather sheet to the plugin's almanac block.
 *
 * The shared calendar registration runs at init priority 9. This call stays
 * at priority 10 so the dependency exists first, and the block attachment is
 * required because WordPress drops a dependent sheet when no attached
 * calendar block renders.
 *
 * @return void
 */
function register_styles(): void {
	$file_path = COURTNEYR_CHILD_DIR . '/assets/css/cr-weather.css';
	if ( ! function_exists( 'wp_enqueue_block_style' ) || ! is_readable( $file_path ) ) {
		return;
	}

	wp_enqueue_block_style(
		'post-kinds-indieweb/almanac-month',
		array(
			'handle' => STYLE_HANDLE,
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-weather.css',
			'path'   => $file_path,
			'ver'    => COURTNEYR_CHILD_VERSION,
			'deps'   => array( 'courtneyr-calendar' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_styles', 10 );

/**
 * Load weather paint where no almanac block can supply it.
 *
 * The archive receives this handle through register_styles(). Singles and
 * Stream surfaces register it without the calendar dependency because those
 * requests render no calendar block.
 *
 * @return void
 */
function enqueue_styles(): void {
	$is_stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
	if ( ! is_admin() && ! is_weather_single() && ! $is_stream ) {
		return;
	}

	if ( ! wp_style_is( STYLE_HANDLE, 'registered' ) ) {
		wp_register_style(
			STYLE_HANDLE,
			COURTNEYR_CHILD_URI . '/assets/css/cr-weather.css',
			array(),
			COURTNEYR_CHILD_VERSION
		);
	}
	wp_enqueue_style( STYLE_HANDLE );
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Add the weather body class on the archive and weather singles.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( ( is_weather_archive() || is_weather_single() ) && ! in_array( 'cr-weather', $classes, true ) ) {
		$classes[] = 'cr-weather';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/** Register the weather station-log block. */
function register_blocks(): void {
	$block_path = COURTNEYR_CHILD_DIR . '/blocks/weather-log';
	if ( ! function_exists( 'register_block_type' ) || ! is_readable( $block_path . '/block.json' ) ) {
		return;
	}
	register_block_type( $block_path );
}
add_action( 'init', __NAMESPACE__ . '\\register_blocks' );

/**
 * Add the locale weekday row and every empty day to an almanac month.
 *
 * Existing day cells stay intact except day 1, whose opening tag receives the
 * grid offset. Empty cells carry no date attribute, link or visible text.
 *
 * @param string               $html  Rendered almanac-month block.
 * @param array<string, mixed> $block Parsed block (unused).
 * @return string
 */
function paint_almanac( string $html, array $block = array() ): string {
	unset( $block );

	if ( 1 !== preg_match( '/data-pkiw-month="(\d{4}-\d{2})"/', $html, $month_match ) ) {
		return $html;
	}

	$ol_open  = '<ol class="pkiw-almanac__days">';
	$ol_start = strpos( $html, $ol_open );
	if ( false === $ol_start ) {
		return $html;
	}
	$inner_start = $ol_start + strlen( $ol_open );
	$ol_end      = strpos( $html, '</ol>', $inner_start );
	if ( false === $ol_end ) {
		return $html;
	}

	$month = $month_match[1];
	try {
		$first = new \DateTimeImmutable( $month . '-01 12:00:00', wp_timezone() );
	} catch ( \Exception $exception ) {
		return $html;
	}
	if ( $month !== $first->format( 'Y-m' ) ) {
		return $html;
	}

	$start_of_week = (int) get_option( 'start_of_week' );
	$start_of_week = ( ( $start_of_week % 7 ) + 7 ) % 7;
	$weekday       = (int) $first->format( 'w' );
	$offset        = ( ( $weekday - $start_of_week + 7 ) % 7 ) + 1;

	$inner = substr( $html, $inner_start, $ol_end - $inner_start );
	$cells = preg_split( '/(?=<li\b[^>]*\bdata-pkiw-date=)/i', $inner, -1, PREG_SPLIT_NO_EMPTY );
	if ( false === $cells ) {
		return $html;
	}

	$dated_cells = array();
	foreach ( $cells as $cell ) {
		if ( '' === trim( $cell ) ) {
			continue;
		}
		if ( 1 !== preg_match( '/^\s*<li\b[^>]*\bdata-pkiw-date=(["\'])(\d{4}-\d{2}-\d{2})\1/i', $cell, $date_match ) ) {
			return $html;
		}
		if ( str_starts_with( $date_match[2], $month . '-' ) ) {
			$dated_cells[ $date_match[2] ] = $cell;
		}
	}

	$year        = (int) substr( $month, 0, 4 );
	$month_num   = (int) substr( $month, 5, 2 );
	$month_stamp = gmmktime( 12, 0, 0, $month_num, 1, $year );
	$day_count   = (int) gmdate( 't', $month_stamp );
	$rebuilt     = '';
	for ( $day = 1; $day <= $day_count; $day++ ) {
		$date = sprintf( '%s-%02d', $month, $day );
		if ( isset( $dated_cells[ $date ] ) ) {
			$cell = $dated_cells[ $date ];
			if ( 1 === $day ) {
				$cell = preg_replace(
					'/^(\s*<li\b[^>]*)>/i',
					'$1 style="--cr-almanac-offset:' . $offset . '">',
					$cell,
					1
				);
				if ( null === $cell ) {
					return $html;
				}
			}
			$rebuilt .= $cell;
			continue;
		}

		$style    = 1 === $day ? ' style="--cr-almanac-offset:' . $offset . '"' : '';
		$rebuilt .= '<li class="pkiw-almanac__day pkiw-almanac__day--empty pkiw-cal__day"' . $style . ' aria-hidden="true"><span class="pkiw-almanac__daynum">' . $day . '</span></li>';
	}

	global $wp_locale;
	if ( ! is_object( $wp_locale ) || ! method_exists( $wp_locale, 'get_weekday' ) || ! method_exists( $wp_locale, 'get_weekday_abbrev' ) ) {
		return $html;
	}
	$weekdays = '';
	for ( $index = 0; $index < 7; $index++ ) {
		$name      = $wp_locale->get_weekday( ( $start_of_week + $index ) % 7 );
		$weekdays .= '<span class="pkiw-almanac__weekday">' . esc_html( $wp_locale->get_weekday_abbrev( $name ) ) . '</span>';
	}
	$header = '<div class="pkiw-almanac__weekdays" aria-hidden="true">' . $weekdays . '</div>';

	return substr( $html, 0, $ol_start )
		. $header
		. $ol_open
		. $rebuilt
		. '</ol>'
		. substr( $html, $ol_end + strlen( '</ol>' ) );
}
add_filter( 'render_block_post-kinds-indieweb/almanac-month', __NAMESPACE__ . '\\paint_almanac', 10, 2 );

/**
 * Render the weather station log for one post.
 *
 * Weather and place data cross the plugin's privacy boundary only through
 * kind_facts(). A protected or unreadable post therefore receives the Date
 * row and nothing else.
 *
 * @param int $post_id Post ID.
 * @return string Station-log markup, or an empty string without a post.
 */
function log_html( int $post_id ): string {
	$post = get_post( $post_id );
	if ( ! $post instanceof \WP_Post ) {
		return '';
	}

	$facts = function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $post_id ) : array();
	$iso   = array() !== $facts
		? (string) ( $facts['date_iso'] ?? '' )
		: (string) get_post_time( 'c', true, $post );
	$when  = sprintf(
		/* translators: 1: post date, 2: post time. */
		__( '%1$s at %2$s', 'courtneyr-child' ),
		get_the_date( '', $post ),
		get_the_time( '', $post )
	);

	$html  = '<dl class="cr-weather-log">';
	$html .= '<dt>' . esc_html__( 'Date', 'courtneyr-child' ) . '</dt>';
	$html .= '<dd><time datetime="' . esc_attr( $iso ) . '">' . esc_html( $when ) . '</time></dd>';

	$rows = isset( $facts['log'] ) && is_array( $facts['log'] ) ? $facts['log'] : array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$html .= '<dt>' . esc_html( (string) ( $row['label'] ?? '' ) ) . '</dt>';
		$html .= '<dd>' . esc_html( (string) ( $row['value'] ?? '' ) ) . '</dd>';
	}

	$place = array();
	foreach ( array( 'place_name', 'place_locality', 'place_region', 'place_country' ) as $key ) {
		$value = $facts[ $key ] ?? '';
		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			$place[] = trim( (string) $value );
		}
	}
	if ( array() !== $place ) {
		$html .= '<dt>' . esc_html__( 'Place', 'courtneyr-child' ) . '</dt>';
		$html .= '<dd>' . esc_html( implode( ', ', $place ) ) . '</dd>';
	}
	$html .= '</dl>';

	$icon = $facts['icon'] ?? '';
	if ( is_scalar( $icon ) && '' !== trim( (string) $icon ) ) {
		$html .= '<span class="cr-weather-art" data-pkiw-weather-icon="' . esc_attr( trim( (string) $icon ) ) . '" aria-hidden="true"></span>';
	}
	if ( ! empty( $facts['temperature_visible'] ) ) {
		$html .= '<span class="cr-weather-thermo" aria-hidden="true"></span>';
	}

	return $html;
}

/**
 * Add the thermometer paint hooks to a weather Stream card.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block (unused).
 * @param mixed                $instance Block instance with Query Loop context.
 * @return string
 */
function thermometer_card( string $html, array $block = array(), $instance = null ): string {
	unset( $block );

	if ( '' === $html ) {
		return $html;
	}
	if ( ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) || ! \Courtneyr\Child\HomeSections\is_stream_surface() ) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( KIND, 'kind', $post ) ) {
		return $html;
	}

	if ( 1 !== preg_match( '/^\s*<article\b[^>]*\bclass="([^"]*)"[^>]*>/i', $html, $root ) ) {
		return $html;
	}
	$classes = preg_split( '/\s+/', trim( $root[1] ) );
	$classes = is_array( $classes ) ? $classes : array();
	if ( ! in_array( 'pk-card--stream', $classes, true ) || ! in_array( 'k-weather', $classes, true ) ) {
		return $html;
	}
	if ( in_array( 'pk-card--protected', $classes, true ) ) {
		return $html;
	}
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( array( 'tag_name' => 'article' ) ) ) {
		return $html;
	}
	$tags->add_class( 'cr-thermo' );
	$updated = $tags->get_updated_html();

	$facts = function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $post->ID ) : array();
	if ( empty( $facts['temperature_visible'] ) ) {
		return $updated;
	}

	$close = strripos( $updated, '</article>' );
	if ( false === $close ) {
		return $updated;
	}

	return substr_replace( $updated, '<span class="cr-thermo__glass" aria-hidden="true"></span>', $close, 0 );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\thermometer_card', 10, 3 );
