<?php
/**
 * Title: Favorite star chart loop
 * Slug: courtneyr-child/cr-favorite-star-chart-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the favorite archive, one torn-paper band of star-marked clippings for each month.
 * Keywords: favorite, star, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

?>
<!-- wp:group {"metadata":{"name":"Favorite star chart"},"align":"wide","className":"cr-archive-stream cr-favorite-chart","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-favorite-chart">
	<!-- wp:query {"queryId":259,"query":{"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-favorite-chart__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-favorite-chart__query">
		<!-- wp:post-template {"className":"cr-favorite-chart__list"} -->
			<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/archive-sections' ) ) : ?>
			<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"headingLevel":2} /-->
			<?php endif; ?>
			<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/stream-card' ) ) : ?>
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-favorite-clipping"} /-->
			<?php endif; ?>
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous <?php echo wp_json_encode( array( 'label' => __( 'Previous', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next <?php echo wp_json_encode( array( 'label' => __( 'Next', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
