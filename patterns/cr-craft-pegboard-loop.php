<?php
/**
 * Title: Craft pegboard loop
 * Slug: courtneyr-child/cr-craft-pegboard-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the craft archive with grouped pegboard cards and core pagination.
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Craft pegboard"},"align":"wide","className":"cr-archive-stream cr-pegboard-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-pegboard-wrap">
	<!-- wp:query {"queryId":249,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-pegboard__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-pegboard__query">
		<!-- wp:group {"metadata":{"name":"Pegboard"},"className":"cr-pegboard","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-pegboard">
			<!-- wp:post-template {"className":"cr-pegboard__board"} -->
				<?php if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/archive-sections' ) ) : ?>
				<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"emptyLabel":"Craft","headingLevel":2} /-->
				<?php endif; ?>
				<?php if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/stream-card' ) ) : ?>
				<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-peg-card"} /-->
				<?php else : ?>
				<!-- wp:post-title {"level":3,"isLink":true} /-->
				<?php endif; ?>
			<!-- /wp:post-template -->
		</div>
		<!-- /wp:group -->

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
