<?php
/**
 * Stream RSVP cards as torn response stubs.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamRsvp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a stream-card post ID.
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
 * Read the incoming Stream title's heading level.
 *
 * @param string $html Rendered Stream card.
 */
function heading_level( string $html ): int {
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return 2;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$tag = (string) $tags->get_tag();
		if ( in_array( $tag, array( 'H2', 'H3', 'H4' ), true ) && $tags->has_class( 'pk-title' ) ) {
			return max( 2, min( 4, (int) substr( $tag, 1 ) ) );
		}
	}
	return 2;
}

/** Return the visitor-facing RSVP kind label. */
function kind_label(): string {
	$term = get_term_by( 'slug', 'rsvp', 'kind' );
	return $term instanceof \WP_Term ? $term->name : __( 'RSVP', 'courtneyr-child' );
}

/**
 * Draw the status stamp with the shared deterministic primitive.
 *
 * @param int    $post_id Post ID.
 * @param string $label   Visible status label.
 */
function status_stamp( int $post_id, string $label ): string {
	if ( '' === $label || ! function_exists( '\\Courtneyr\\Child\\Stamps\\render' ) ) {
		return '';
	}
	$seed = \Courtneyr\Child\Stamps\seed( (string) $post_id );
	return \Courtneyr\Child\Stamps\render(
		array(
			'shape'  => 'rect',
			'big'    => mb_strtoupper( $label ),
			'ink'    => \Courtneyr\Child\Stamps\INKS[ \Courtneyr\Child\Stamps\pick( $seed, 3, count( \Courtneyr\Child\Stamps\INKS ) ) ],
			'tilt'   => \Courtneyr\Child\Stamps\pick( $seed, 6, \Courtneyr\Child\Stamps\TILTS ),
			'glyph'  => '',
			'family' => 'rsvp',
		)
	);
}

/**
 * Replace an RSVP on the Stream with its one-link torn stub.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function stub_card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if ( '' === $html || ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) || ! \Courtneyr\Child\HomeSections\is_stream_surface() || str_contains( $html, 'pk-card--protected' ) || str_contains( $html, 'cr-rsvp-stub' ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'rsvp', 'kind', $post ) || post_password_required( $post ) ) {
		return $html;
	}
	$rsvp = facts( $post_id );
	if ( array() === $rsvp ) {
		return $html;
	}

	$seed  = function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) ? \Courtneyr\Child\Stamps\seed( (string) $post_id ) : $post_id;
	$tilt  = function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ? \Courtneyr\Child\Stamps\pick( $seed, 6, 4 ) : abs( $post_id ) % 4;
	$paper = function_exists( '\\Courtneyr\\Child\\Rsvp\\paper' ) ? \Courtneyr\Child\Rsvp\paper( $post_id ) : 'periwinkle';
	$level = heading_level( $html );
	$name  = trim( (string) ( $rsvp['name'] ?? '' ) );
	if ( '' === $name ) {
		$name = trim( (string) get_the_title( $post ) );
	}

	$out  = '<article class="pk-card pk-card--stream cr-rsvp-stub cr-rsvp-tilt-' . $tilt . ' h-entry" style="--cr-rsvp-paper:var(--cr-' . esc_attr( $paper ) . ')">';
	$out .= '<p class="cr-rsvp-stub__kind">' . esc_html( kind_label() ) . '</p>';
	$out .= '<h' . $level . ' class="cr-rsvp-stub__name"><a class="u-url' . ( ! empty( $rsvp['name_is_title'] ) ? ' p-name' : '' ) . '" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $name ) . '</a></h' . $level . '>';
	if ( '' !== (string) ( $rsvp['start_day'] ?? '' ) ) {
		$out .= '<time class="cr-rsvp-stub__day" datetime="' . esc_attr( (string) $rsvp['start_day'] ) . '">' . esc_html( (string) ( $rsvp['day_display'] ?? '' ) ) . '</time>';
	}
	if ( '' !== (string) ( $rsvp['status'] ?? '' ) ) {
		$out .= '<p class="cr-rsvp-stub__status"><data class="p-rsvp" value="' . esc_attr( (string) $rsvp['status'] ) . '">' . esc_html( (string) ( $rsvp['status_label'] ?? '' ) ) . '</data>' . status_stamp( $post_id, (string) ( $rsvp['status_label'] ?? '' ) ) . '</p>';
	}
	$out  .= '<time class="dt-published" datetime="' . esc_attr( (string) ( $rsvp['date_iso'] ?? '' ) ) . '" hidden></time>';
	$props = function_exists( '\\PKIW\\entry_author_html' ) ? (string) \PKIW\entry_author_html( $post ) : '';
	if ( '' !== (string) ( $rsvp['url'] ?? '' ) ) {
		$props .= '<data class="u-in-reply-to" value="' . esc_url( (string) $rsvp['url'] ) . '"></data>';
	}
	if ( '' !== $props ) {
		$out .= '<span class="cr-rsvp-stub__props" hidden>' . $props . '</span>';
	}
	$out .= '<span class="cr-rsvp-stub__tear" aria-hidden="true" role="presentation"></span></article>';
	return $out;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stub_card', 10, 3 );
