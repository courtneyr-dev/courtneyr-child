<?php
/**
 * Title: Browse all topics, formats and kinds
 * Slug: courtneyr-child/cr-browse-all
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Native Terms List directory of every non-empty category, post format and kind, with counts. Lives at the foot of the archive family and on the Stream page.
 * Keywords: browse, directory, categories, formats, kinds, terms
 * Block Types: core/categories
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"tagName":"nav","anchor":"browse-all","ariaLabel":"Browse all topics, formats and kinds","metadata":{"name":"Browse all"},"align":"wide","className":"cr-browse-all","layout":{"type":"constrained"}} -->
<nav class="wp-block-group alignwide cr-browse-all" id="browse-all" aria-label="Browse all topics, formats and kinds">
	<!-- wp:heading {"level":2,"className":"cr-browse-all__title","fontFamily":"accent","fontSize":"large"} -->
	<h2 class="wp-block-heading cr-browse-all__title has-accent-font-family has-large-font-size"><?php esc_html_e( 'Browse all', 'courtneyr-child' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"className":"cr-browse-all__columns"} -->
	<div class="wp-block-columns cr-browse-all__columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"className":"cr-browse-all__group","fontSize":"small"} -->
			<h3 class="wp-block-heading cr-browse-all__group has-small-font-size"><?php esc_html_e( '📰 Blog topics', 'courtneyr-child' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:categories {"taxonomy":"category","showPostCounts":true,"showEmpty":false,"className":"cr-browse-all__list"} /-->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"className":"cr-browse-all__group","fontSize":"small"} -->
			<h3 class="wp-block-heading cr-browse-all__group has-small-font-size"><?php esc_html_e( '🌊 Stream formats', 'courtneyr-child' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:categories {"taxonomy":"post_format","showPostCounts":true,"showEmpty":false,"className":"cr-browse-all__list"} /-->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"className":"cr-browse-all__group","fontSize":"small"} -->
			<h3 class="wp-block-heading cr-browse-all__group has-small-font-size"><?php esc_html_e( '🌊 Stream kinds', 'courtneyr-child' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:categories {"taxonomy":"kind","showPostCounts":true,"showEmpty":false,"className":"cr-browse-all__list"} /-->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</nav>
<!-- /wp:group -->
