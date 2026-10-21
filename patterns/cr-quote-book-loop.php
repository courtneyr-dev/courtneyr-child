<?php
/**
 * Title: Quote commonplace book loop
 * Slug: courtneyr-child/cr-quote-book-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Block Types: core/query
 * Inserter: false
 *
 * Quote archive Query Loop for issue 257.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<?php if ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/quote-entry' ) ) : ?>
<!-- wp:group {"metadata":{"name":"Quote commonplace book"},"align":"wide","className":"cr-quote-archive","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-quote-archive">
	<!-- wp:query {"queryId":257,"query":{"perPage":8,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-quote-archive__query"} -->
	<div class="wp-block-query cr-quote-archive__query">
		<!-- wp:group {"metadata":{"name":"Commonplace book"},"className":"cr-quote-book","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-quote-book">
			<!-- wp:post-kinds-indieweb/quote-authors /-->

			<!-- wp:post-template {"className":"cr-quote-book__entries"} -->
				<!-- wp:post-kinds-indieweb/quote-entry {"linesPerPage":8,"headingLevel":3,"className":"is-style-cr-commonplace"} /-->
			<!-- /wp:post-template -->

			<!-- wp:post-kinds-indieweb/quote-works /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous <?php echo wp_json_encode( array( 'label' => __( 'Previous', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next <?php echo wp_json_encode( array( 'label' => __( 'Next', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
		<!-- /wp:query-pagination -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
<?php else : ?>
<!-- wp:pattern {"slug":"courtneyr-child/cr-archive-stream-loop"} /-->
<?php endif; ?>
