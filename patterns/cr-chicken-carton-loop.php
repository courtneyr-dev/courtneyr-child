<?php
/**
 * Title: Chicken carton loop
 * Slug: courtneyr-child/cr-chicken-carton-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the chicken archive. Each calendar month is an egg carton and each post is an egg, with core pagination after the last carton.
 * Keywords: chicken, eggs, carton, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Chicken cartons"},"align":"wide","className":"cr-chicken","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-chicken">
	<!-- wp:query {"queryId":250,"query":{"perPage":24,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-chicken__query"} -->
	<div class="wp-block-query cr-chicken__query">
		<!-- wp:post-template {"className":"cr-chicken__months"} -->
			<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/chicken-entry' ) ) : ?>
			<!-- wp:post-kinds-indieweb/chicken-entry {"linesPerPage":24} /-->
			<?php else : ?>
			<!-- wp:post-date {"format":"M d"} /-->
			<!-- wp:post-title {"level":3,"isLink":true} /-->
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
