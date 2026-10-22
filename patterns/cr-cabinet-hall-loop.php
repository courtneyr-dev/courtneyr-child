<?php
/**
 * Title: Cabinet hall loop
 * Slug: courtneyr-child/cr-cabinet-hall-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Collection cabinet hall with core pagination.
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Cabinet hall"},"align":"wide","className":"cr-archive-stream cr-cabinet-hall","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-cabinet-hall">
	<!-- wp:query {"queryId":240,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-cabinet-hall__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-cabinet-hall__query">
		<!-- wp:post-template {"className":"is-style-cr-cabinet-hall"} -->
			<?php if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/stream-card' ) ) : ?>
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":2,"className":"cr-cabinet-plaque"} /-->
			<?php else : ?>
			<!-- wp:post-title {"level":2,"isLink":true} /-->
			<?php endif; ?>
		<!-- /wp:post-template -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous <?php echo wp_json_encode( array( 'label' => __( 'Previous', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next <?php echo wp_json_encode( array( 'label' => __( 'Next', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
		<!-- /wp:query-pagination -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'No collections yet.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
