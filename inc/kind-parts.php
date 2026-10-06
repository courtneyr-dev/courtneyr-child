<?php
/**
 * Kind parts: the single template's `kind` template part renders the part for
 * the post's kind.
 *
 * The single template (templates/single.html) places one `core/template-part`
 * with slug `kind`. On a singular post whose Post Kind has a theme part
 * (`parts/kind-listen.html`, and the kinds R-14 adds later), that block renders
 * the `kind-{slug}` part; any other request renders nothing there. The parts hold patterns whose values
 * come from Post Kinds block bindings, so the theme no longer rewrites the
 * plugin's card markup to show them.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\KindParts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ROUTER_SLUG = 'kind';

/**
 * The theme part slug for a post's kind, when the theme ships one.
 *
 * @param int $post_id Post ID.
 * @return string `kind-{slug}`, or '' when the post has no kind or no part exists.
 */
function part_slug_for( int $post_id ): string {
	$terms = get_the_terms( $post_id, 'kind' );
	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return '';
	}
	$slug = 'kind-' . sanitize_key( $terms[0]->slug );
	return get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' ) ? $slug : '';
}

/**
 * Route the `kind` template part to the post's kind part, or render nothing.
 *
 * @param string|null          $pre          Short-circuit output from earlier filters.
 * @param array<string, mixed> $parsed_block Parsed block.
 * @return string|null
 */
function route( ?string $pre, array $parsed_block ): ?string {
	if ( null !== $pre || 'core/template-part' !== ( $parsed_block['blockName'] ?? '' ) || ROUTER_SLUG !== ( $parsed_block['attrs']['slug'] ?? '' ) ) {
		return $pre;
	}
	$slug = is_singular( 'post' ) ? part_slug_for( (int) get_queried_object_id() ) : '';
	if ( '' === $slug ) {
		return '';
	}
	$parsed_block['attrs']['slug'] = $slug;
	return render_block( $parsed_block );
}
add_filter( 'pre_render_block', __NAMESPACE__ . '\\route', 10, 2 );

/**
 * A title reduced to the words a reader sees: no tags or entities, one
 * space between words, lower case.
 *
 * @param string $title Title text or HTML.
 * @return string
 */
function title_key( string $title ): string {
	$text = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $text ) ) );
}

/**
 * Mark the listen card whose title repeats the single's H1.
 *
 * On a listen single the H1 prints the post title and `parts/kind-listen.html`
 * links to the track, so a card title with the same words is a second link
 * to the track under a copy of the H1. The `cr-listen--title-repeats` class
 * lets cr-post-kinds.css hide that title. A post titled apart from its track
 * ("Monday mood" about "American Obituary") gets no class and keeps the
 * card title, the only place the track name shows.
 *
 * @param string               $html     Rendered listen card.
 * @param array<string, mixed> $block    Parsed block.
 * @param \WP_Block|null       $instance Block instance.
 * @return string
 */
function mark_repeated_listen_title( string $html, array $block, $instance = null ): string {
	if ( ! is_singular( 'post' ) ) {
		return $html;
	}
	$post_id = (int) get_queried_object_id();
	if ( $instance instanceof \WP_Block && isset( $instance->context['postId'] ) && (int) $instance->context['postId'] !== $post_id ) {
		return $html;
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof \WP_Post || ! preg_match( '#<h2 class="pk-title[^"]*"[^>]*>(.*?)</h2>#s', $html, $m ) ) {
		return $html;
	}
	$card_title = title_key( $m[1] );
	if ( '' === $card_title || ! in_array( $card_title, array( title_key( $post->post_title ), title_key( get_the_title( $post ) ) ), true ) ) {
		return $html;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( array( 'class_name' => 'pk-card' ) ) ) {
		return $html;
	}
	$tags->add_class( 'cr-listen--title-repeats' );
	return $tags->get_updated_html();
}
add_filter( 'render_block_post-kinds-indieweb/listen-card', __NAMESPACE__ . '\\mark_repeated_listen_title', 10, 3 );
