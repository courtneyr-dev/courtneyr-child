<?php
/**
 * Craft Stream tool tags (PKIW issue 249).
 *
 * The plugin owns the card facts and their order. This adapter removes the
 * generic card furniture and adds the tag's decorative class contract.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamCraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turn a generic craft Stream card into a one-link tool tag.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance with the Query Loop post ID.
 * @return string
 */
function tool_tag( string $html, array $block, $instance ): string {
	if (
		'' === $html
		|| ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		|| ! \Courtneyr\Child\HomeSections\is_stream_surface()
		|| ! function_exists( '\\Courtneyr\\Child\\Craft\\is_peg_card' )
		|| \Courtneyr\Child\Craft\is_peg_card( $block )
		|| ! function_exists( '\\Courtneyr\\Child\\MediaShelf\\cut_elements' )
	) {
		return $html;
	}

	if ( 1 !== preg_match( '/^\s*<article\b[^>]*\bclass="([^"]*)"[^>]*>/i', $html, $root ) ) {
		return $html;
	}
	$classes = preg_split( '/\s+/', trim( $root[1] ) );
	$classes = is_array( $classes ) ? $classes : array();
	if (
		! in_array( 'pk-card--stream', $classes, true )
		|| ! in_array( 'k-craft', $classes, true )
		|| in_array( 'pk-card--protected', $classes, true )
	) {
		return $html;
	}

	$cuts = array(
		array( 'div', 'pk-badge' ),
		array( 'p', 'pk-stream-date' ),
		array( 'p', 'pk-excerpt' ),
		array( 'div', 'pk-meta' ),
	);
	foreach ( $cuts as $cut ) {
		$html = \Courtneyr\Child\MediaShelf\cut_elements( $html, $cut[0], $cut[1] );
	}

	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	$tags->add_class( 'cr-craft-tag' );
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		$paper = sanitize_html_class(
			\Courtneyr\Child\Stamps\seeded_paper(
				$post_id,
				array( 'light-orange', 'printer-ivory' )
			)
		);
		if ( '' !== $paper ) {
			$tags->add_class( 'cr-craft-tag--' . $paper );
		}
	}

	$media_found = false;
	while ( $tags->next_tag() ) {
		if ( 'DIV' === $tags->get_tag() && $tags->has_class( 'pk-media' ) ) {
			$tags->set_attribute( 'data-cr-cover-fallback', 'true' );
			$media_found = true;
			continue;
		}
		if ( $media_found && 'IMG' === $tags->get_tag() ) {
			$tags->set_attribute( 'alt', '' );
			break;
		}
	}
	$html = $tags->get_updated_html();

	if ( 1 !== preg_match( '/^\s*<article\b[^>]*>/i', $html, $opening, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$insert_at  = (int) $opening[0][1] + strlen( $opening[0][0] );
	$decoration = '<span class="cr-craft-tag__hole" aria-hidden="true"></span><span class="cr-craft-tag__tape" aria-hidden="true"></span>';

	return substr( $html, 0, $insert_at ) . $decoration . substr( $html, $insert_at );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\tool_tag', 10, 3 );
