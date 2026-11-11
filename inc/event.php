<?php
/**
 * Event single flyer and Stream tear-off tab.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'event';
const EVENT_CARD   = 'post-kinds-indieweb/event-card';
const STYLE_HANDLE = 'courtneyr-event';

/** Whether the queried object is a public Event post. */
function is_event_single(): bool {
	if ( ! is_singular( 'post' ) ) {
		return false;
	}
	$post_id = get_queried_object_id();
	$post    = get_post( $post_id );
	return $post instanceof \WP_Post
		&& '' === (string) $post->post_password
		&& has_term( KIND, 'kind', $post_id );
}

/** Whether this request renders the Stream surface. */
function is_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
}

/** Register the Event card sheet against the dynamic plugin block. */
function register_block_style(): void {
	if ( ! class_exists( '\\WP_Block_Type_Registry' ) ) {
		return;
	}
	$registry = \WP_Block_Type_Registry::get_instance();
	if ( ! $registry->is_registered( EVENT_CARD ) ) {
		return;
	}
	wp_enqueue_block_style(
		EVENT_CARD,
		array(
			'handle' => STYLE_HANDLE,
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-event.css',
			'deps'   => array( 'courtneyr-paper' ),
			'ver'    => COURTNEYR_CHILD_VERSION,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_style', 20 );

/** Load Event paint only where an Event object can appear. */
function enqueue(): void {
	if ( ! is_admin() && ! is_event_single() && ! is_stream_surface() ) {
		return;
	}
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-event.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue' );

/**
 * Keep the conditional Event sheet out of shared used CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-event.css';
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20 );

/**
 * Read visitor-safe facts from the plugin's rendered card.
 *
 * The plugin has already applied provider privacy and integration precedence at
 * this boundary. The theme never opens the raw attributes or post meta again.
 *
 * @param string $html Rendered Event card.
 * @return array<string, string>
 */
function rendered_facts( string $html ): array {
	$facts = array(
		'name'       => '',
		'start_iso'  => '',
		'start_text' => '',
		'end_iso'    => '',
		'location'   => '',
		'status'     => '',
		'summary'    => '',
		'image_src'  => '',
		'image_alt'  => '',
		'event_url'  => '',
		'event_rel'  => '',
	);
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $facts;
	}

	$root = new \WP_HTML_Tag_Processor( $html );
	if ( ! $root->next_tag( 'ARTICLE' ) || ! $root->has_class( 'k-event' ) ) {
		return $facts;
	}

	$tags   = new \WP_HTML_Tag_Processor( $html );
	$active = '';
	$depth  = 0;
	while ( $tags->next_token() ) {
		$type = $tags->get_token_type();
		if ( '#text' === $type && '' !== $active ) {
			$facts[ $active ] .= $tags->get_modifiable_text();
			continue;
		}
		if ( '#tag' !== $type ) {
			continue;
		}

		if ( '' !== $active ) {
			if ( 'name' === $active && ! $tags->is_tag_closer() && 'A' === $tags->get_tag() && $tags->has_class( 'u-url' ) && '' === $facts['event_url'] ) {
				$facts['event_url'] = (string) $tags->get_attribute( 'href' );
				$facts['event_rel'] = (string) $tags->get_attribute( 'rel' );
			}
			$depth += $tags->is_tag_closer() ? -1 : 1;
			if ( 0 === $depth ) {
				$facts[ $active ] = trim( html_entity_decode( $facts[ $active ], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				$active           = '';
			}
			continue;
		}
		if ( $tags->is_tag_closer() ) {
			continue;
		}

		$tag = $tags->get_tag();
		if ( 'IMG' === $tag && $tags->has_class( 'u-photo' ) ) {
			$facts['image_src'] = (string) $tags->get_attribute( 'src' );
			$facts['image_alt'] = (string) $tags->get_attribute( 'alt' );
			continue;
		}
		if ( 'DATA' === $tag && $tags->has_class( 'dt-end' ) ) {
			$facts['end_iso'] = (string) $tags->get_attribute( 'value' );
			continue;
		}
		if ( 'TIME' === $tag && $tags->has_class( 'dt-start' ) ) {
			$facts['start_iso'] = (string) $tags->get_attribute( 'datetime' );
			$active             = 'start_text';
			$depth              = 1;
			continue;
		}
		if ( 'A' === $tag && $tags->has_class( 'u-url' ) && '' === $facts['event_url'] ) {
			$facts['event_url'] = (string) $tags->get_attribute( 'href' );
			$facts['event_rel'] = (string) $tags->get_attribute( 'rel' );
		}

		foreach (
			array(
				'name'     => 'pk-title',
				'location' => 'pk-event-location',
				'status'   => 'pk-event-status',
				'summary'  => 'p-summary',
			) as $key => $class
		) {
			if ( $tags->has_class( $class ) ) {
				$active = $key;
				$depth  = 1;
				break;
			}
		}
	}
	return $facts;
}

/**
 * Return an H2-H6 level copied from the incoming plugin title.
 *
 * @param string $html     Rendered card.
 * @param int    $fallback Level used when the card has no title.
 */
function heading_level( string $html, int $fallback = 2 ): int {
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $fallback;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$tag = (string) $tags->get_tag();
		if ( $tags->has_class( 'pk-title' ) && in_array( $tag, array( 'H2', 'H3', 'H4', 'H5', 'H6' ), true ) ) {
			return (int) substr( $tag, 1 );
		}
	}
	return $fallback;
}

/**
 * Parse one plugin ISO date without changing its represented instant.
 *
 * @param string $iso Plugin-provided ISO date.
 */
function parse_date( string $iso ): ?\DateTimeImmutable {
	if ( '' === $iso ) {
		return null;
	}
	try {
		return new \DateTimeImmutable( $iso, wp_timezone() );
	} catch ( \Exception $error ) {
		return null;
	}
}

/**
 * Build the date and time rows shown on the flyer.
 *
 * @param array<string, string> $facts Safe rendered facts.
 * @return array{date:string,time:string}
 */
function when_rows( array $facts ): array {
	$start = parse_date( $facts['start_iso'] );
	$end   = parse_date( $facts['end_iso'] );
	if ( ! $start ) {
		return array(
			'date' => esc_html( $facts['start_text'] ),
			'time' => '',
		);
	}

	$all_day   = ! str_contains( $facts['start_iso'], 'T' );
	$date_text = (string) wp_date( get_option( 'date_format' ), $start->getTimestamp(), $start->getTimezone() );
	if ( $end && $end->format( 'Y-m-d' ) !== $start->format( 'Y-m-d' ) ) {
		$date_text .= ' – ' . wp_date( get_option( 'date_format' ), $end->getTimestamp(), $end->getTimezone() );
	}
	$date = '<time class="dt-start" datetime="' . esc_attr( $facts['start_iso'] ) . '">' . esc_html( $date_text ) . '</time>';
	if ( '' !== $facts['end_iso'] ) {
		$date .= '<data class="dt-end" value="' . esc_attr( $facts['end_iso'] ) . '" hidden></data>';
	}

	$time = '';
	if ( ! $all_day ) {
		$time = (string) wp_date( get_option( 'time_format' ), $start->getTimestamp(), $start->getTimezone() );
		if ( $end && $end->format( 'Y-m-d' ) === $start->format( 'Y-m-d' ) ) {
			$time .= ' – ' . wp_date( get_option( 'time_format' ), $end->getTimestamp(), $end->getTimezone() );
		}
		$time = esc_html( $time );
	}
	return array(
		'date' => $date,
		'time' => $time,
	);
}

/**
 * A definition-list row whose value is already escaped markup.
 *
 * @param string $label      Visible fact label.
 * @param string $value      Escaped value markup.
 * @param string $class_name Row class.
 */
function fact_row( string $label, string $value, string $class_name ): string {
	if ( '' === trim( $value ) ) {
		return '';
	}
	return '<div class="cr-event-flyer__fact ' . esc_attr( $class_name ) . '"><dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd></div>';
}

/**
 * Replace the single Event card with the theme flyer.
 *
 * @param string               $html  Rendered Event card.
 * @param array<string, mixed> $block Parsed block.
 */
function single_flyer( string $html, array $block ): string {
	static $rendered = array();
	if ( ! is_event_single() ) {
		return $html;
	}
	$post_id = (int) ( $block['context']['postId'] ?? get_queried_object_id() );
	if ( get_queried_object_id() !== $post_id || isset( $rendered[ $post_id ] ) ) {
		return $html;
	}
	$facts = rendered_facts( $html );
	if ( '' === $facts['name'] && '' === $facts['start_iso'] ) {
		return $html;
	}
	$rendered[ $post_id ] = true;

	$post_title = trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	$event_name = '' !== $facts['name'] ? $facts['name'] : $post_title;
	$same_name  = 0 === strcasecmp( $event_name, $post_title );
	$name       = $same_name
		? '<data class="p-name" value="' . esc_attr( $event_name ) . '" hidden></data>'
		: '<h2 class="cr-event-flyer__name p-name">' . esc_html( $event_name ) . '</h2>';

	$image = '';
	if ( '' !== $facts['image_src'] ) {
		$image = '<figure class="cr-event-flyer__image"><img class="u-photo" src="' . esc_url( $facts['image_src'] ) . '" alt="' . esc_attr( $facts['image_alt'] ) . '" loading="lazy"></figure>';
	} elseif ( has_post_thumbnail( $post_id ) ) {
		$image = '<figure class="cr-event-flyer__image">' . wp_get_attachment_image( get_post_thumbnail_id( $post_id ), 'large', false, array( 'class' => 'u-photo' ) ) . '</figure>';
	}

	$when  = when_rows( $facts );
	$rows  = fact_row( __( 'Date', 'courtneyr-child' ), $when['date'], 'cr-event-flyer__date' );
	$rows .= fact_row( __( 'Time', 'courtneyr-child' ), $when['time'], 'cr-event-flyer__time' );
	$rows .= fact_row( __( 'Location', 'courtneyr-child' ), esc_html( $facts['location'] ), 'cr-event-flyer__location p-location' );

	$status = '' !== $facts['status'] ? '<p class="cr-event-flyer__status">' . esc_html( $facts['status'] ) . '</p>' : '';
	$url    = '';
	if ( '' !== $facts['event_url'] ) {
		$host  = (string) wp_parse_url( $facts['event_url'], PHP_URL_HOST );
		$label = '' !== $host ? $host : __( 'Event website', 'courtneyr-child' );
		$rel   = '' !== $facts['event_rel'] ? ' rel="' . esc_attr( $facts['event_rel'] ) . '"' : '';
		$url   = '<p class="cr-event-flyer__url"><a class="u-url" href="' . esc_url( $facts['event_url'] ) . '"' . $rel . '>' . esc_html( $label ) . '</a></p>';
	}
	$summary = '' !== $facts['summary'] ? '<p class="cr-event-flyer__summary p-summary">' . esc_html( $facts['summary'] ) . '</p>' : '';

	return '<article class="cr-event-flyer h-event">'
		. '<span class="cr-event-flyer__kind">' . esc_html__( 'Event', 'courtneyr-child' ) . '</span>'
		. $name . $image . $status
		. '<dl class="cr-event-flyer__facts">' . $rows . '</dl>'
		. $summary . $url
		. '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/event-card', __NAMESPACE__ . '\\single_flyer', 15, 2 );

/** Event kind label after the plugin's context-aware label filter. */
function stream_label(): string {
	$term     = get_term_by( 'slug', KIND, 'kind' );
	$fallback = $term instanceof \WP_Term ? $term->name : __( 'Event', 'courtneyr-child' );
	return function_exists( '\\PKIW\\get_kind_label' )
		? (string) \PKIW\get_kind_label( $fallback, KIND, 'stream-card' )
		: $fallback;
}

/**
 * Replace the Stream Event card with one compact tear-off tab.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function stream_tab( string $html, array $block, $instance = null ): string {
	if ( ! is_stream_surface() || str_contains( $html, 'pk-card--protected' ) || str_contains( $html, 'cr-event-tab' ) ) {
		return $html;
	}
	$post_id = $instance instanceof \WP_Block && ! empty( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: (int) ( $block['context']['postId'] ?? get_the_ID() );
	$post    = get_post( $post_id );
	if ( ! $post instanceof \WP_Post || '' !== (string) $post->post_password || ! has_term( KIND, 'kind', $post_id ) ) {
		return $html;
	}
	$facts = rendered_facts( $html );
	if ( '' === $facts['name'] && '' === $facts['start_iso'] ) {
		return $html;
	}

	$name      = '' !== $facts['name'] ? $facts['name'] : (string) get_the_title( $post_id );
	$permalink = (string) get_permalink( $post_id );
	$heading   = heading_level( $html, 3 );
	$start     = parse_date( $facts['start_iso'] );
	$date      = $start
		? (string) wp_date( 'M j · ' . get_option( 'time_format' ), $start->getTimestamp(), $start->getTimezone() )
		: $facts['start_text'];
	$published = (string) get_post_time( 'c', true, $post );
	$author    = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
	$status    = '' !== $facts['status'] ? '<span class="cr-event-tab__status">' . esc_html( $facts['status'] ) . '</span>' : '';

	return '<article class="pk-card pk-card--stream k-event cr-event-tab h-entry">'
		. '<div class="cr-event-tab__event h-event">'
		. '<span class="cr-event-tab__kind">' . esc_html( stream_label() ) . '</span>'
		. '<h' . $heading . ' class="cr-event-tab__name p-name"><a class="u-url" href="' . esc_url( $permalink ) . '">' . esc_html( $name ) . '</a></h' . $heading . '>'
		. '<p class="cr-event-tab__when"><time class="dt-start" datetime="' . esc_attr( $facts['start_iso'] ) . '">' . esc_html( $date ) . '</time>' . $status . '</p>'
		. '</div>'
		. '<time class="dt-published" datetime="' . esc_attr( $published ) . '" hidden></time>'
		. '<span class="p-author h-card" hidden><span class="p-name">' . esc_html( $author ) . '</span></span>'
		. '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/event-card', __NAMESPACE__ . '\\stream_tab', 20, 2 );
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stream_tab', 20, 3 );
