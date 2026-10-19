<?php
/**
 * Title: Wish catalog loop
 * Slug: courtneyr-child/cr-wish-catalog-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the wish archive, grouped by wish type in a catalog.
 * Keywords: wish, catalog, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Wish catalog"},"align":"full","className":"cr-archive-stream cr-wish","style":{"spacing":{"padding":{"right":"var:preset|spacing|md","left":"var:preset|spacing|md"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull cr-archive-stream cr-wish" style="padding-right:var(--wp--preset--spacing--md);padding-left:var(--wp--preset--spacing--md)">
	<!-- wp:query {"queryId":238,"query":{"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-wish__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-wish__query">
		<!-- wp:post-template {"className":"cr-wish-catalog"} -->
			<!-- wp:post-kinds-indieweb/archive-sections <?php echo wp_json_encode( array( 'linesPerPage' => 12, 'headingLevel' => 2, 'emptyLabel' => __( 'Other', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound, WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-wish-listing"} /-->
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'Nothing wished for yet.', 'courtneyr-child' ); ?></p>
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
