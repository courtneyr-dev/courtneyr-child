<?php
/**
 * Jam archive and Stream adapters for issue 235.
 *
 * The plugin owns jam facts and card markup. This file adds the theme's
 * jukebox-strip classes, cover loading policy and stable Stream rotation.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Jam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND          = 'jam';
const STYLE_HANDLE  = 'courtneyr-jam';
const LISTING_CLASS = 'pk-card--jam-listing';
const STREAM_CLASS  = 'pk-card--jam-stream';
const FIRST_ROW     = 3;
const ROTATIONS     = 4;

/** Whether this request is the jam kind archive. */
function is_jam_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single jam post. */
function is_jam_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Public jam facts from the plugin.
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

/** Enqueue jam paint only on surfaces that render the jam design. */
function enqueue_styles(): void {
	$is_stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();

	if ( ! is_admin() && ! is_jam_archive() && ! is_jam_single() && ! $is_stream ) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-jam.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Mark the jam archive for its stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_jam_archive() && ! in_array( 'cr-jam-archive', $classes, true ) ) {
		$classes[] = 'cr-jam-archive';
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Register the jam single class with editor parity.
 *
 * @param array<string, array{classes?: array<int, string>}> $kinds Registry so far.
 * @return array<string, array{classes?: array<int, string>}>
 */
function register_editor_parity( array $kinds ): array {
	$classes = isset( $kinds[ KIND ]['classes'] ) && is_array( $kinds[ KIND ]['classes'] )
		? $kinds[ KIND ]['classes']
		: array( 'single-post', 'kind-' . KIND );
	if ( ! in_array( 'cr-jam-single', $classes, true ) ) {
		$classes[] = 'cr-jam-single';
	}
	$kinds[ KIND ]['classes'] = $classes;

	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );

/** Reset the request-level listing-card counter. */
function reset_page_state(): void {
	$GLOBALS['courtneyr_child_jam_listing_position'] = 0;
}

/**
 * Take the next place in the request's run of jam cards.
 *
 * @return int The card's 1-based position.
 */
function next_listing_position(): int {
	$position = (int) ( $GLOBALS['courtneyr_child_jam_listing_position'] ?? 0 ) + 1;

	$GLOBALS['courtneyr_child_jam_listing_position'] = $position;

	return $position;
}

/**
 * Class tokens on the first article root.
 *
 * @param string $html Rendered card HTML.
 * @return string[]
 */
function root_classes( string $html ): array {
	if ( 1 !== preg_match( '/^\s*<article\b[^>]*\bclass=(?:"([^"]*)"|\'([^\']*)\')[^>]*>/i', $html, $root ) ) {
		return array();
	}

	$classes = '' !== (string) ( $root[1] ?? '' ) ? (string) $root[1] : (string) ( $root[2] ?? '' );
	$tokens  = preg_split( '/\s+/', trim( $classes ) );

	return is_array( $tokens ) ? array_values( array_filter( $tokens, 'strlen' ) ) : array();
}

/**
 * Add the jukebox-strip contract to a plugin-owned jam card.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block, unused by this adapter.
 * @param mixed                $instance Block instance with the Query Loop post ID.
 * @return string
 */
function jukebox_strip( string $html, array $block = array(), $instance = null ): string {
	unset( $block );

	if ( '' === $html ) {
		return $html;
	}

	$classes = root_classes( $html );
	if ( ! in_array( 'k-jam', $classes, true ) ) {
		return $html;
	}

	// A protected card is a title link and nothing else, so it stays as printed. It still holds its
	// place in the archive, so the strip after a protected Now Playing card isn't promoted to the lead.
	if ( in_array( 'pk-card--protected', $classes, true ) ) {
		next_listing_position();
		return $html;
	}

	if ( ! in_array( LISTING_CLASS, $classes, true ) && ! in_array( STREAM_CLASS, $classes, true ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	$tags->add_class( 'cr-jam-strip' );

	if ( in_array( LISTING_CLASS, $classes, true ) ) {
		$tags->add_class( 'cr-jam-strip--listing' );
		$position = next_listing_position();

		if (
			$tags->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'pk-media',
				)
			)
		) {
			$tags->set_attribute( 'data-cr-cover-fallback', 'true' );
			if ( $tags->next_tag( 'img' ) ) {
				$page  = function_exists( 'get_query_var' ) ? max( 1, (int) get_query_var( 'paged' ) ) : 1;
				$eager = 1 === $page ? $position <= 1 + FIRST_ROW : $position <= FIRST_ROW;
				$tags->set_attribute( 'alt', '' );
				$tags->set_attribute( 'loading', $eager ? 'eager' : 'lazy' );
				if ( 1 === $page && 1 === $position ) {
					$tags->set_attribute( 'fetchpriority', 'high' );
				} else {
					$tags->remove_attribute( 'fetchpriority' );
				}
			}
		}

		return $tags->get_updated_html();
	}

	$tags->add_class( 'cr-jam-strip--stream' );
	$tags->add_class( 'cr-paper-sheet' );
	$tags->add_class( 'cr-paper-sheet--torn' );
	$tags->add_class( 'cr-paper--sky-blue' );

	$post_id = is_object( $instance ) && isset( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	if (
		function_exists( '\\Courtneyr\\Child\\Stamps\\seed' )
		&& function_exists( '\\Courtneyr\\Child\\Stamps\\pick' )
	) {
		$tags->set_attribute(
			'data-cr-rotate',
			(string) \Courtneyr\Child\Stamps\pick(
				\Courtneyr\Child\Stamps\seed( 'jam-strip', (string) $post_id ),
				3,
				ROTATIONS
			)
		);
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\jukebox_strip', 10, 3 );

reset_page_state();
