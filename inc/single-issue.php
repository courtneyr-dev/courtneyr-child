<?php
/**
 * Single issue claim-ticket heading and facts sheet (PKIW issue 264).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleIssue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
 * Whether this post is the unprotected queried issue with usable facts.
 *
 * @param \WP_Post $post Candidate post.
 */
function is_ticket_post( \WP_Post $post ): bool {
	if ( ! is_singular( 'post' ) || (int) get_queried_object_id() !== $post->ID || ! has_term( 'issue', 'kind', $post ) || post_password_required( $post ) ) {
		return false;
	}
	return ! empty( facts( $post->ID )['has_facts'] );
}

/**
 * Tell the single template that the issue object supplies its h1.
 *
 * @param mixed    $holds Existing decision.
 * @param \WP_Post $post  Candidate post.
 */
function holds_h1( $holds, \WP_Post $post ): bool {
	return true === $holds || is_ticket_post( $post );
}
add_filter( 'courtneyr_child_single_object_holds_h1', __NAMESPACE__ . '\\holds_h1', 10, 2 );

/**
 * Whether an HTML fragment already contains the ticket heading.
 *
 * @param string $html Rendered post-content HTML.
 */
function has_ticket_head( string $html ): bool {
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return str_contains( $html, 'cr-claim-ticket-head' );
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( $tags->has_class( 'cr-claim-ticket-head' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Add issue sheet classes to the plugin's singular wrapper and sections.
 *
 * @param string $html Rendered post-content HTML.
 */
function dress_content( string $html ): string {
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$has_wrapper = false;
	$scan        = new \WP_HTML_Tag_Processor( $html );
	while ( $scan->next_tag() ) {
		if ( $scan->has_class( 'pkiw-singular-entry' ) ) {
			$has_wrapper = true;
			break;
		}
	}

	$first = true;
	$tags  = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( ( $has_wrapper && $tags->has_class( 'pkiw-singular-entry' ) ) || ( ! $has_wrapper && $first ) ) {
			$tags->add_class( 'cr-claim-sheet' );
		}
		if ( 'DIV' === $tags->get_tag() && $tags->has_class( 'pkiw-issue-facts' ) ) {
			$tags->add_class( 'cr-claim-facts' );
		}
		if ( 'DIV' === $tags->get_tag() && $tags->has_class( 'pkiw-issue-body' ) ) {
			$tags->add_class( 'cr-claim-body' );
		}
		$first = false;
	}
	return $tags->get_updated_html();
}

/**
 * Prepend the issue's one visible h1 to its post content.
 *
 * @param string $html  Rendered post-content HTML.
 * @param array  $block Parsed block.
 */
function ticket_head( string $html, array $block ): string {
	if ( 'core/post-content' !== (string) ( $block['blockName'] ?? '' ) || ! in_the_loop() || has_ticket_head( $html ) ) {
		return $html;
	}
	$post = get_post( get_queried_object_id() );
	if ( ! $post instanceof \WP_Post || ! is_ticket_post( $post ) ) {
		return $html;
	}

	$issue_facts = facts( $post->ID );
	$kind_label  = trim( (string) ( $issue_facts['kind_label'] ?? '' ) );
	$name        = trim( (string) ( $issue_facts['name'] ?? '' ) );
	$number      = trim( (string) ( $issue_facts['number_text'] ?? '' ) );
	if ( '' === $kind_label ) {
		$kind_label = __( 'Issue', 'courtneyr-child' );
	}
	if ( '' === $name ) {
		$name = trim( (string) get_the_title( $post ) );
	}
	if ( '' === $name ) {
		$name = __( 'Issue', 'courtneyr-child' );
	}

	$head  = '<header class="cr-claim-ticket-head">';
	$head .= '<div class="cr-claim-ticket-head__main"><p class="cr-claim-ticket-head__kicker">' . esc_html( $kind_label ) . '</p>';
	$head .= '<h1 class="cr-claim-ticket-head__title">' . esc_html( $name ) . '</h1></div>';
	$head .= '<span class="cr-claim-ticket-head__stub" aria-hidden="true" role="presentation">' . esc_html( $number ) . '</span></header>';
	return $head . dress_content( $html );
}
add_filter( 'render_block', __NAMESPACE__ . '\\ticket_head', 20, 2 );
