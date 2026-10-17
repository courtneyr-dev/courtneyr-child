<?php
/**
 * Stream sleep cards as round stickers (PKIW issue 247).
 *
 * This adapter rebuilds both the plugin's sleep skeleton and its generic
 * card from public kind facts. The result has one link and omits the excerpt,
 * kind label, badge and metadata from the markup.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamSleep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace a sleep post's Stream card with the sleep sticker.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed stream-card block.
 * @param mixed                $instance Block instance with the Query Loop post ID.
 * @return string
 */
function sleep_sticker( string $html, array $block, $instance ): string {
	if (
		'' === $html
		|| ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		|| ! \Courtneyr\Child\HomeSections\is_stream_surface()
	) {
		return $html;
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if (
		! $post instanceof \WP_Post
		|| ! has_term( \Courtneyr\Child\Sleep\KIND, 'kind', $post )
		|| post_password_required( $post )
		|| ! function_exists( '\\PKIW\\kind_facts' )
	) {
		return $html;
	}

	$facts = \PKIW\kind_facts( $post->ID );
	if ( ! is_array( $facts ) || array() === $facts ) {
		return $html;
	}

	$level       = max( 2, min( 4, (int) ( $block['attrs']['headingLevel'] ?? 2 ) ) );
	$is_titled   = ! empty( $facts['name_is_title'] );
	$name        = $is_titled ? (string) ( $facts['name'] ?? '' ) : (string) ( $facts['name_kind'] ?? '' );
	$title_id    = 'cr-sleep-t-' . $post->ID;
	$date_id     = 'cr-sleep-d-' . $post->ID;
	$duration_id = 'cr-sleep-h-' . $post->ID;
	$minutes     = (int) ( $facts['duration_minutes'] ?? 0 );
	$has_length  = $minutes > 0;
	$separator   = '<span class="cr-sleep-sticker__sr">, </span>';
	$named_by    = array( $title_id, $date_id );
	if ( $has_length ) {
		$named_by[] = $duration_id;
	}

	$phase = (string) ( $facts['phase'] ?? '' );
	$moon  = '';
	if ( in_array( $phase, \Courtneyr\Child\Sleep\MOON_PHASES, true ) && has_filter( 'pkiw_sleep_moon_html' ) ) {
		$moon = (string) apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin owns this integration hook.
			'pkiw_sleep_moon_html',
			'',
			$phase,
			$post->ID
		);
	}

	// The p-name sits on an inner span so the visually hidden separator never reaches the entry's name.
	$name_html = $is_titled ? '<span class="p-name">' . esc_html( $name ) . '</span>' : esc_html( $name );
	$out       = '<article class="pk-card pk-card--stream k-sleep h-entry cr-sleep-sticker">';
	$out      .= '<h' . $level . ' class="cr-sleep-sticker__title" id="' . esc_attr( $title_id ) . '">'
		. $name_html . $separator . '</h' . $level . '>';
	$out      .= '<a class="u-url cr-sleep-sticker__link" href="' . esc_url( (string) get_permalink( $post ) )
		. '" aria-labelledby="' . esc_attr( implode( ' ', $named_by ) ) . '">';
	$out      .= '<span class="cr-sleep-sticker__moon" aria-hidden="true">'
		. $moon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted markup from the theme's moon filter.
		. '</span>';
	if ( $has_length ) {
		$out .= '<span class="cr-sleep-sticker__duration p-sleep" id="' . esc_attr( $duration_id ) . '"><time datetime="'
			. esc_attr( (string) ( $facts['duration_iso'] ?? '' ) ) . '">'
			. esc_html( (string) ( $facts['duration_label'] ?? '' ) ) . '</time></span>';
	}
	$out .= '<span class="cr-sleep-sticker__phase">' . esc_html( (string) ( $facts['phase_label'] ?? '' ) ) . '</span>';
	$out .= '<time class="dt-published cr-sleep-sticker__date" id="' . esc_attr( $date_id ) . '" datetime="'
		. esc_attr( (string) ( $facts['night'] ?? '' ) ) . '">'
		. esc_html( (string) ( $facts['night_display'] ?? '' ) )
		. ( $has_length ? $separator : '' ) . '</time>';
	$out .= '</a>';
	$out .= '<span class="cr-sleep-sticker__spark" aria-hidden="true"></span>';
	$out .= '<span class="cr-sleep-sticker__spark" aria-hidden="true"></span>';

	return $out . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\sleep_sticker', 10, 3 );
