<?php
/**
 * Video single tape deck.
 *
 * The plugin chooses and marks the primary player. This adapter gives that
 * player the single-page deck while keeping feeds and machine formats plain.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleVideo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized facts for one post.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function post_facts( int $post_id ): array {
	static $kept = array();

	if ( ! array_key_exists( $post_id, $kept ) ) {
		$kept[ $post_id ] = function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $post_id ) : array();
	}

	return $kept[ $post_id ];
}

/**
 * Whether one post owns the tape-deck single treatment.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function is_tape_post( int $post_id ): bool {
	static $kept = array();

	if ( ! array_key_exists( $post_id, $kept ) ) {
		$eligible = 0 < $post_id
			&& is_singular( 'post' )
			&& ! post_password_required( $post_id )
			&& has_term( 'video', 'kind', $post_id );
		$facts    = $eligible ? post_facts( $post_id ) : array();

		$kept[ $post_id ] = $eligible && ! empty( $facts['has_player'] );
	}

	return $kept[ $post_id ];
}

/** Whether the queried post owns the tape-deck single treatment. */
function is_tape_single(): bool {
	return is_tape_post( (int) get_queried_object_id() );
}

/**
 * Tell the shared single-object filter that the deck owns the page H1.
 *
 * @param mixed    $holds Whether another lane already owns the H1.
 * @param \WP_Post $post  Post being rendered.
 * @return bool
 */
function holds_h1( $holds, \WP_Post $post ): bool {
	return true === $holds || ( (int) get_queried_object_id() === (int) $post->ID && is_tape_post( (int) $post->ID ) );
}
add_filter( 'courtneyr_child_single_object_holds_h1', __NAMESPACE__ . '\\holds_h1', 10, 2 );

/**
 * Whether this render must remain authored content.
 *
 * @return bool
 */
function is_plain_render(): bool {
	return is_feed()
		|| is_embed()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| null !== get_query_var( 'atproto', null )
		|| ! empty( get_query_var( 'markdown_request' ) )
		|| 'markdown' === get_query_var( 'format' );
}

/**
 * Whether the first tag carries the plugin's primary-player marker.
 *
 * @param string $html   Rendered player block.
 * @param string $marker Marker attribute name.
 * @return bool
 */
function is_primary_player( string $html, string $marker ): bool {
	if ( '' === $marker ) {
		return false;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	return $tags->next_tag() && '1' === $tags->get_attribute( $marker );
}

/**
 * Build the tape label from normalized facts.
 *
 * @param array<string, mixed> $facts Video facts.
 * @param \WP_Post             $post  Current post.
 * @return string
 */
function label_html( array $facts, \WP_Post $post ): string {
	$is_title = ! empty( $facts['name_is_title'] );
	$name     = (string) ( $facts['name'] ?? '' );
	if ( ! $is_title && function_exists( '\\Courtneyr\\Child\\Nav\\kind_date_name' ) ) {
		$theme_name = (string) \Courtneyr\Child\Nav\kind_date_name( $post );
		if ( '' !== $theme_name ) {
			$name = $theme_name;
		}
	}

	$title_class = 'cr-tape-label__title' . ( $is_title ? ' p-name' : '' );
	$facts_html  = '';
	$date_iso    = (string) ( $facts['date_iso'] ?? '' );
	if ( '' !== $date_iso ) {
		$facts_html .= '<time class="dt-published cr-tape-label__date" datetime="' . esc_attr( $date_iso ) . '">'
			. esc_html( (string) ( $facts['date_display'] ?? '' ) ) . '</time>';
	}
	if ( 0 < (int) ( $facts['runtime_seconds'] ?? 0 ) ) {
		$facts_html .= ( '' !== $facts_html ? ' ' : '' )
			. '<time class="cr-tape-label__runtime" datetime="' . esc_attr( (string) ( $facts['runtime_iso'] ?? '' ) ) . '">'
			. esc_html( (string) ( $facts['runtime_clock'] ?? '' ) )
			. '<span class="screen-reader-text"> (' . esc_html( (string) ( $facts['runtime_human'] ?? '' ) ) . ')</span></time>';
	}

	return '<header class="cr-tape-label"><h1 class="' . esc_attr( $title_class ) . '">' . esc_html( $name ) . '</h1>'
		. '<p class="cr-tape-label__facts">' . $facts_html . '</p></header>';
}

/**
 * Give each video element an explicit Able Player heading level.
 *
 * Able Player reads only headings that are direct children of an ancestor, so it
 * never sees the label's H1 inside its header and prints a second, offscreen H1.
 * Level 2 puts its "Media player" heading under the label's H1. An authored
 * data-heading-level stays.
 *
 * @param string $html Rendered player block.
 * @return string
 */
function with_heading_level( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'video' ) ) {
		if ( null === $tags->get_attribute( 'data-heading-level' ) ) {
			$tags->set_attribute( 'data-heading-level', '2' );
		}
	}

	return $tags->get_updated_html();
}

/**
 * Wrap the one marked player in the single-page tape deck.
 *
 * @param string $html  Rendered video or embed block.
 * @param array  $block Parsed block.
 * @return string
 */
function deck( string $html, array $block ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$post_id = (int) get_queried_object_id();
	if ( ! is_tape_post( $post_id ) || is_plain_render() ) {
		return $html;
	}

	static $wrapped = array();
	if ( ! empty( $wrapped[ $post_id ] ) ) {
		return $html;
	}

	$facts  = post_facts( $post_id );
	$marker = (string) ( $facts['primary_marker'] ?? '' );
	if ( ! is_primary_player( $html, $marker ) ) {
		return $html;
	}

	$post = get_post( $post_id );
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}

	$wrapped[ $post_id ] = true;
	return '<div class="cr-tape-deck">' . label_html( $facts, $post )
		. '<div class="cr-tape-deck__frame">' . with_heading_level( $html ) . '</div></div>';
}
add_filter( 'render_block_core/video', __NAMESPACE__ . '\\deck', 250, 2 );
add_filter( 'render_block_core/embed', __NAMESPACE__ . '\\deck', 250, 2 );

/**
 * Add the tape-single body class once.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_tape_single() && ! in_array( 'cr-tape-single', $classes, true ) ) {
		$classes[] = 'cr-tape-single';
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );
