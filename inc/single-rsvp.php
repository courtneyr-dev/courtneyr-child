<?php
/**
 * Single RSVP event ticket.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleRsvp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the rendered card's post ID.
 *
 * @param mixed $instance Block instance.
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Read visitor-safe RSVP facts.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Compare an event name with the single's h1 text.
 *
 * @param string $name  Event name.
 * @param string $title Post title.
 */
function same_name( string $name, string $title ): bool {
	$name  = trim( wp_strip_all_tags( $name ) );
	$title = trim( wp_strip_all_tags( $title ) );
	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $name ) === mb_strtolower( $title );
	}
	return strtolower( $name ) === strtolower( $title );
}

/**
 * Build the stored relationship plus mandatory security values.
 *
 * @param string $stored Stored rel value when the facts reader supplies one.
 */
function link_rel( string $stored = '' ): string {
	$values = array_filter( explode( ' ', str_replace( array( "\t", "\n", "\r" ), ' ', $stored ) ) );
	$values = array_merge( array( 'noopener', 'noreferrer' ), $values );
	return implode( ' ', array_values( array_unique( $values ) ) );
}

/**
 * Render the event name or provider tape inside the nested h-event.
 *
 * @param array<string, mixed> $facts RSVP facts.
 * @param string               $title Plain post title.
 */
function event_name( array $facts, string $title ): string {
	$name = trim( (string) ( $facts['name'] ?? '' ) );
	$url  = trim( (string) ( $facts['url'] ?? '' ) );
	$same = same_name( $name, $title );

	if ( '' === $url ) {
		return $same
			? '<data class="p-name" value="' . esc_attr( $name ) . '" hidden></data>'
			: '<span class="cr-rsvp-ticket__event-name p-name">' . esc_html( $name ) . '</span>';
	}

	$visible = $same ? (string) ( $facts['url_host'] ?? '' ) : $name;
	if ( '' === trim( $visible ) ) {
		$visible = $name;
	}
	$label = function_exists( '\\PKIW\\pkiw_new_tab_label_attr' )
		? \PKIW\pkiw_new_tab_label_attr( $visible )
		: ' aria-label="' . esc_attr( $visible . ' (' . __( 'opens in a new tab', 'courtneyr-child' ) . ')' ) . '"';
	$out   = $same ? '<data class="p-name" value="' . esc_attr( $name ) . '" hidden></data>' : '';
	return $out . '<a class="cr-rsvp-ticket__event-link u-url' . ( $same ? '' : ' p-name' ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="' . esc_attr( link_rel( (string) ( $facts['rel'] ?? '' ) ) ) . '"' . $label . '>' . esc_html( $visible ) . '</a>';
}

/**
 * Return the visible RSVP kind label through the plugin's label filter.
 */
function kind_label(): string {
	$label = __( 'RSVP', 'courtneyr-child' );
	return function_exists( '\\PKIW\\get_kind_label' ) ? (string) \PKIW\get_kind_label( $label, 'rsvp', 'rsvp-card' ) : $label;
}

/**
 * Build one facts-list row.
 *
 * @param string $label Row label.
 * @param string $value Escaped value markup.
 */
function fact_row( string $label, string $value ): string {
	return '<div class="cr-rsvp-ticket__fact"><dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd></div>';
}

/**
 * Replace the first RSVP card on its queried, unprotected RSVP single.
 *
 * @param string               $html     Rendered RSVP card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function ticket( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	static $rendered = array();

	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( '' === $html || ! $post instanceof \WP_Post || isset( $rendered[ $post_id ] ) || ! is_singular( 'post' ) || (int) get_queried_object_id() !== $post_id || ! has_term( 'rsvp', 'kind', $post ) || post_password_required( $post ) ) {
		return $html;
	}

	$rsvp = facts( $post_id );
	if ( array() === $rsvp ) {
		return $html;
	}
	$rendered[ $post_id ] = true;

	$title = wp_strip_all_tags( (string) get_the_title( $post ) );
	$out   = '<article class="cr-rsvp-ticket h-entry">';
	$out  .= '<div class="cr-rsvp-ticket__front"><div class="cr-rsvp-ticket__event p-in-reply-to h-event">';
	if ( '' !== (string) ( $rsvp['image'] ?? '' ) ) {
		$out .= '<figure class="cr-rsvp-ticket__poster"><img class="u-photo" src="' . esc_url( (string) $rsvp['image'] ) . '" alt="' . esc_attr( (string) ( $rsvp['image_alt'] ?? '' ) ) . '" loading="lazy"></figure>';
	}
	$out .= '<div class="cr-rsvp-ticket__event-heading">' . event_name( $rsvp, $title ) . '</div>';

	$facts_html = '';
	if ( '' !== (string) ( $rsvp['start_iso'] ?? '' ) ) {
		$facts_html .= fact_row( __( 'Date', 'courtneyr-child' ), '<time class="dt-start" datetime="' . esc_attr( (string) $rsvp['start_iso'] ) . '">' . esc_html( (string) ( $rsvp['day_display'] ?? '' ) ) . '</time>' );
	}
	if ( '' !== (string) ( $rsvp['time_display'] ?? '' ) ) {
		$facts_html .= fact_row( __( 'Time', 'courtneyr-child' ), esc_html( (string) $rsvp['time_display'] ) );
	}
	if ( '' !== (string) ( $rsvp['location'] ?? '' ) ) {
		$facts_html .= fact_row( __( 'Location', 'courtneyr-child' ), '<span class="p-location">' . esc_html( (string) $rsvp['location'] ) . '</span>' );
	}
	if ( '' !== $facts_html ) {
		$out .= '<dl class="cr-rsvp-ticket__facts">' . $facts_html . '</dl>';
	}
	if ( '' !== (string) ( $rsvp['end_iso'] ?? '' ) ) {
		$out .= '<data class="dt-end" value="' . esc_attr( (string) $rsvp['end_iso'] ) . '" hidden></data>';
	}
	if ( '' !== (string) ( $rsvp['description'] ?? '' ) ) {
		$out .= '<p class="cr-rsvp-ticket__summary p-summary">' . esc_html( (string) $rsvp['description'] ) . '</p>';
	}
	$out .= '</div>';

	$labels = function_exists( '\\PKIW\\rsvp_status_labels' ) ? \PKIW\rsvp_status_labels() : array();
	$out   .= '<aside class="cr-rsvp-ticket__stub"><span class="pk-kindlabel cr-rsvp-ticket__kind">' . esc_html( kind_label() ) . '</span><div class="cr-rsvp-ticket__punches" aria-hidden="true">';
	foreach ( $labels as $value => $label ) {
		$out .= '<span class="cr-rsvp-ticket__punch' . ( (string) ( $rsvp['status'] ?? '' ) === (string) $value ? ' is-punched' : '' ) . '" data-rsvp-status="' . esc_attr( (string) $value ) . '">' . esc_html( (string) $label ) . '</span>';
	}
	$out .= '</div>';
	if ( '' !== (string) ( $rsvp['status'] ?? '' ) ) {
		$out .= '<data class="cr-rsvp-ticket__status p-rsvp" value="' . esc_attr( (string) $rsvp['status'] ) . '">' . esc_html( (string) ( $rsvp['status_label'] ?? '' ) ) . '</data>';
	}
	$out .= '<span class="cr-rsvp-ticket__perf" aria-hidden="true"></span></aside></div>';

	if ( '' !== (string) ( $rsvp['note'] ?? '' ) || '' !== (string) ( $rsvp['rsvp_at_iso'] ?? '' ) ) {
		$out .= '<div class="cr-rsvp-ticket__back">';
		if ( '' !== (string) ( $rsvp['note'] ?? '' ) ) {
			$out .= '<div class="cr-rsvp-ticket__note p-content">' . wp_kses_post( (string) $rsvp['note'] ) . '</div>';
		}
		if ( '' !== (string) ( $rsvp['rsvp_at_iso'] ?? '' ) ) {
			/* translators: %s: date the RSVP was recorded. */
			$recorded = sprintf( __( 'RSVPed %s', 'courtneyr-child' ), (string) ( $rsvp['rsvp_at_display'] ?? '' ) );
			$out     .= '<time class="cr-rsvp-ticket__recorded dt-published" datetime="' . esc_attr( (string) $rsvp['rsvp_at_iso'] ) . '">' . esc_html( $recorded ) . '</time>';
		}
		$out .= '</div>';
	}
	if ( '' !== (string) ( $rsvp['url'] ?? '' ) ) {
		$out .= '<data class="u-in-reply-to" value="' . esc_url( (string) $rsvp['url'] ) . '" hidden></data>';
	}
	return $out . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/rsvp-card', __NAMESPACE__ . '\\ticket', 20, 3 );
