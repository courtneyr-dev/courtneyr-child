<?php
/**
 * Footnotes: render the core Footnotes block inside a closed details
 * element, so a post's notes take one line until the reader opens them.
 *
 * Courtney asked on 2026-11-02 for every post's footnotes to sit in a
 * details block, in a smaller font. A render filter does it sitewide
 * without editing each post. The wrapper reuses core's Details block
 * classes (wp-block-details), and the font size lives in components.css.
 *
 * A footnote marker links to an item inside the closed element. Chrome
 * opens a details element on fragment navigation; the inline script
 * covers browsers that don't, and also runs on load for a shared link.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Footnotes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HANDLE = 'courtneyr-footnotes';

/**
 * Register the script that opens the footnotes when a link points into them.
 */
function register_script(): void {
	wp_register_script( HANDLE, false, array(), COURTNEYR_CHILD_VERSION, array( 'in_footer' => true ) );
	wp_add_inline_script(
		HANDLE,
		'(function(){function o(h){var i;try{i=decodeURIComponent(h.slice(1))}catch(e){return}'
		. 'var t=i&&document.getElementById(i),d=t&&t.closest("details.cr-footnotes");'
		. 'if(d&&!d.open){d.open=true;t.scrollIntoView()}}'
		. 'document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href^=\'#\']");if(a)o(a.hash)});'
		. 'window.addEventListener("hashchange",function(){o(location.hash)});o(location.hash)})();'
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_script' );

/**
 * Wrap the rendered Footnotes block in a closed details element.
 *
 * @param string $block_content Rendered block HTML.
 * @return string
 */
function wrap( string $block_content ): string {
	if ( '' === trim( $block_content ) ) {
		return $block_content;
	}

	wp_enqueue_script( HANDLE );

	$count = substr_count( $block_content, '<li' );
	/* translators: %d: number of footnotes in the post. */
	$summary = sprintf( __( 'Footnotes (%d)', 'courtneyr-child' ), $count );

	return '<details class="wp-block-details cr-footnotes"><summary>' . esc_html( $summary ) . '</summary>' . $block_content . '</details>';
}
add_filter( 'render_block_core/footnotes', __NAMESPACE__ . '\\wrap' );
