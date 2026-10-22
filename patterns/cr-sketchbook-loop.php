<?php
/**
 * Title: Comics sketchbook loop
 * Slug: courtneyr-child/cr-sketchbook-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for drawn comics, grouped by series on one sketchbook page.
 * Keywords: comics, sketchbook, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Comics sketchbook"},"align":"wide","className":"cr-archive-stream cr-sketchbook-loop","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-sketchbook-loop">
	<!-- wp:query {"queryId":266,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-sketchbook-loop__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-sketchbook-loop__query">
		<!-- wp:post-template {"className":"cr-sketchbook"} -->
			<!-- wp:post-kinds-indieweb/archive-sections {"headingLevel":2,"linesPerPage":12} /-->
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-sketchbook-strip"} /-->
		<!-- /wp:post-template -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous <?php echo wp_json_encode( array( 'label' => __( 'Previous', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next <?php echo wp_json_encode( array( 'label' => __( 'Next', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
