<?php
/**
 * Audio archive log integration (PKIW issue 243).
 *
 * The plugin owns audio facts, grouping and entry properties. This file
 * registers the log style, enqueues its paint and reshapes generic audio cards
 * for the archive without reading stored post data.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Audio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND          = 'audio';
const STYLE_HANDLE  = 'courtneyr-audio';
const ARCHIVE_STYLE = 'cr-audio-log';
const CARD_CLASS    = 'cr-audio-log-card';

/** Whether this request is the audio archive. */
function is_audio_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single audio post. */
function is_audio_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/** Register the audio log style for the Site Editor. */
function register_styles(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'core/post-template',
			array(
				'name'  => ARCHIVE_STYLE,
				'label' => __( 'Audio log', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_styles' );

/** Enqueue audio paint only where audio archive or single markup appears. */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_audio_archive() && ! is_audio_single() ) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-audio.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Register the audio single classes with editor parity.
 *
 * @param array<string, array{classes?: array<int, string>}> $kinds Registry so far.
 * @return array<string, array{classes?: array<int, string>}>
 */
function register_editor_parity( array $kinds ): array {
	$classes = isset( $kinds[ KIND ]['classes'] ) && is_array( $kinds[ KIND ]['classes'] )
		? array_values( array_unique( $kinds[ KIND ]['classes'] ) )
		: array();
	foreach ( array( 'single-post', 'kind-' . KIND, 'cr-single--audio' ) as $class_name ) {
		if ( ! in_array( $class_name, $classes, true ) ) {
			$classes[] = $class_name;
		}
	}
	$kinds[ KIND ]['classes'] = $classes;

	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );

/**
 * Whether a stream-card block belongs to the audio log pattern.
 *
 * @param array<string, mixed> $block Parsed stream-card block.
 * @return bool
 */
function is_log_card( array $block ): bool {
	if ( is_audio_archive() ) {
		return true;
	}

	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	$classes    = preg_split( '/\s+/', trim( $class_name ) );

	return is_array( $classes ) && in_array( CARD_CLASS, $classes, true );
}

/**
 * Cut the first element of one tag that carries a class, and return it.
 *
 * @param string $html       Fragment, modified in place.
 * @param string $tag        Tag name.
 * @param string $class_name One class the element carries.
 * @return string The removed element, or '' when there is none.
 */
function take_element( string &$html, string $tag, string $class_name ): string {
	$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
	if ( ! preg_match( $open, $html, $match, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}
	$start = (int) $match[0][1];
	$pos   = $start + strlen( $match[0][0] );
	$depth = 1;
	while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $found, PREG_OFFSET_CAPTURE, $pos ) ) {
		$pos    = (int) $found[0][1] + strlen( $found[0][0] );
		$depth += '/' === $found[1][0] ? -1 : 1;
	}
	if ( $depth > 0 ) {
		return '';
	}
	$taken = substr( $html, $start, $pos - $start );
	$html  = substr( $html, 0, $start ) . substr( $html, $pos );

	return $taken;
}

/**
 * Reshape a generic audio card into one log cassette and its metadata row.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed stream-card block.
 * @param mixed                $instance Block instance, unused.
 * @return string
 */
function log_item( string $html, array $block, $instance ): string {
	unset( $instance );

	if ( '' === $html || ! is_log_card( $block ) ) {
		return $html;
	}

	$root = new \WP_HTML_Tag_Processor( $html );
	if (
		! $root->next_tag()
		|| 'ARTICLE' !== $root->get_tag()
		|| ! $root->has_class( 'pk-card--stream' )
		|| ! $root->has_class( 'k-audio' )
		|| $root->has_class( 'pk-card--protected' )
		|| $root->has_class( 'cr-cassette--log' )
	) {
		return $html;
	}

	$work     = $html;
	$date     = take_element( $work, 'p', 'pk-stream-date' );
	$duration = take_element( $work, 'p', 'pk-audio-duration' );
	if ( '' === $date ) {
		return $html;
	}
	take_element( $work, 'span', 'pk-kindlabel' );

	$date_tag = new \WP_HTML_Tag_Processor( $date );
	if (
		$date_tag->next_tag(
			array(
				'tag_name'   => 'TIME',
				'class_name' => 'dt-published',
			)
		)
	) {
		$datetime = $date_tag->get_attribute( 'datetime' );
		$stamp    = is_string( $datetime ) ? strtotime( $datetime ) : false;
		if ( false !== $stamp ) {
			$date_tag->set_attribute( 'datetime', wp_date( 'Y-m-d', $stamp ) );
			$date = $date_tag->get_updated_html();
		}
	}

	$card = new \WP_HTML_Tag_Processor( $work );
	if ( ! $card->next_tag( 'ARTICLE' ) ) {
		return $html;
	}
	$card->remove_class( 'h-entry' );
	$card->add_class( 'cr-cassette--log' );

	return $card->get_updated_html() . '<div class="cr-audio-log__meta">' . $date . $duration . '</div>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\log_item', 10, 3 );
