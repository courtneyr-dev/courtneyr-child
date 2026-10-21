<?php
/**
 * Stream issue cards as compact claim stubs (PKIW issue 264).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamIssue;

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
 * Return guarded public issue facts.
 *
 * @param int $post_id Post ID.
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Read the rendered title's heading level.
 *
 * @param string $html Rendered card HTML.
 */
function heading_level( string $html ): int {
	if ( function_exists( '\\Courtneyr\\Child\\Issue\\heading_level' ) ) {
		return \Courtneyr\Child\Issue\heading_level( $html, 2 );
	}
	if ( class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			$tag = (string) $tags->get_tag();
			if ( in_array( $tag, array( 'H2', 'H3', 'H4' ), true ) && $tags->has_class( 'pk-title' ) ) {
				return max( 2, min( 4, (int) substr( $tag, 1 ) ) );
			}
		}
	}
	return 2;
}

/**
 * Hidden author and repository properties retained by the replacement stub.
 *
 * @param \WP_Post             $post        Issue post.
 * @param array<string, mixed> $issue_facts Public issue facts.
 */
function hidden_properties( \WP_Post $post, array $issue_facts ): string {
	$out = '';
	if ( function_exists( '\\PKIW\\entry_author_html' ) ) {
		$out .= (string) \PKIW\entry_author_html( $post );
	}
	if ( class_exists( '\\PKIW\\Issue_Archive' ) && method_exists( '\\PKIW\\Issue_Archive', 'cite' ) ) {
		$out .= (string) \PKIW\Issue_Archive::cite( $issue_facts, $post );
	}
	return '<span class="cr-claim-stub__props" hidden>' . $out . '</span>';
}

/** The issue kind label when plugin facts are unavailable. */
function fallback_kind_label(): string {
	$term = get_term_by( 'slug', 'issue', 'kind' );
	return $term instanceof \WP_Term ? $term->name : __( 'Issue', 'courtneyr-child' );
}

/**
 * Replace a Stream issue with its one-link claim stub.
 *
 * @param string $html     Rendered stream-card HTML.
 * @param array  $block    Parsed block (unused).
 * @param mixed  $instance Block instance.
 */
function stub_card( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if ( '' === $html
		|| ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		|| ! \Courtneyr\Child\HomeSections\is_stream_surface()
		|| str_contains( $html, 'pk-card--protected' )
		|| str_contains( $html, 'cr-claim-ticket' )
	) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'issue', 'kind', $post ) || post_password_required( $post ) ) {
		return $html;
	}

	$issue_facts  = facts( $post->ID );
	$name         = trim( (string) ( $issue_facts['name'] ?? '' ) );
	$kind_label   = trim( (string) ( $issue_facts['kind_label'] ?? '' ) );
	$date_iso     = trim( (string) ( $issue_facts['date_iso'] ?? '' ) );
	$date_display = trim( (string) ( $issue_facts['date_display'] ?? '' ) );
	if ( '' === $name ) {
		$name = trim( (string) get_the_title( $post ) );
	}
	if ( '' === $name ) {
		$name = __( 'Issue', 'courtneyr-child' );
	}
	if ( '' === $kind_label ) {
		$kind_label = fallback_kind_label();
	}
	if ( '' === $date_iso ) {
		$date_iso = (string) get_the_date( 'c', $post );
	}
	if ( '' === $date_display ) {
		$date_display = (string) get_the_date( '', $post );
	}

	$level = heading_level( $html );
	$out   = '<article class="pk-card pk-card--stream cr-claim-stub h-entry">';
	$out  .= '<p class="cr-claim-stub__kind">' . esc_html( $kind_label ) . '</p>';

	$repo_name = trim( (string) ( $issue_facts['repo_name'] ?? '' ) );
	$repo_url  = trim( (string) ( $issue_facts['repo_url'] ?? '' ) );
	if ( '' !== $repo_name || '' !== $repo_url ) {
		$repo_label = trim( (string) ( $issue_facts['repo_label'] ?? '' ) );
		$number     = trim( (string) ( $issue_facts['number_text'] ?? '' ) );
		$repo_line  = trim( $repo_label . ( '' !== $number ? ' ' . $number : '' ) );
		if ( '' !== $repo_line ) {
			$out .= '<p class="cr-claim-stub__repo">' . esc_html( $repo_line ) . '</p>';
		}
	}
	$out .= '<h' . $level . ' class="cr-claim-stub__title"><a class="' . ( array() === $issue_facts || ! empty( $issue_facts['name_is_title'] ) ? 'u-url p-name' : 'u-url' ) . '" href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $name ) . '</a></h' . $level . '>';

	$status = (string) ( $issue_facts['status'] ?? '' );
	if ( in_array( $status, array( 'open', 'closed' ), true ) && '' !== trim( (string) ( $issue_facts['status_label'] ?? '' ) ) ) {
		$out .= '<span class="cr-claim-stub__status cr-claim-stub__status--' . esc_attr( $status ) . '">' . esc_html( (string) $issue_facts['status_label'] ) . '</span>';
	}
	$out .= '<time class="dt-published" datetime="' . esc_attr( $date_iso ) . '">' . esc_html( $date_display ) . '</time>';
	$out .= hidden_properties( $post, $issue_facts );
	$out .= '<span class="cr-claim-stub__tear" aria-hidden="true" role="presentation"></span></article>';
	return $out;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stub_card', 10, 3 );
