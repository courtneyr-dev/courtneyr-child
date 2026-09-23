<?php
/**
 * Wordmark block style on core/site-title: accessible name.
 *
 * The `cr-wordmark` style (inc/block-styles.php, components.css) paints the
 * all-paths wordmark asset over the Site Title link and hides the site name
 * text. The link's accessible name must include the visible "courtneyr.dev"
 * (WCAG 2.5.3), and the site name option can differ from the wordmark, so the
 * link keeps the header's existing label. Scoped to blocks with that style.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SiteTitleWordmark;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Label the wordmark link.
 *
 * @param string               $block_content Rendered Site Title block.
 * @param array<string, mixed> $block         Parsed block.
 * @return string
 */
function label_wordmark_link( string $block_content, array $block ): string {
	$classes = preg_split( '/\s+/', trim( (string) ( $block['attrs']['className'] ?? '' ) ) );
	if ( ! is_array( $classes ) || ! in_array( 'is-style-cr-wordmark', $classes, true ) ) {
		return $block_content;
	}
	$tags = new \WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag( 'a' ) ) {
		$tags->set_attribute( 'aria-label', __( 'Courtney Robertson — CourtneyR.dev home', 'courtneyr-child' ) );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/site-title', __NAMESPACE__ . '\\label_wordmark_link', 10, 2 );
