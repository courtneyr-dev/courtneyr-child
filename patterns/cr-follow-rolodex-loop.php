<?php
/**
 * Title: Follow Rolodex loop
 * Slug: courtneyr-child/cr-follow-rolodex-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<?php if ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/follow-entry' ) ) : ?>
<!-- wp:group {"metadata":{"name":"Follow Rolodex"},"align":"wide","className":"cr-rolodex","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-rolodex">
	<!-- wp:query {"queryId":260,"query":{"perPage":8,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-rolodex__query"} -->
	<div class="wp-block-query cr-rolodex__query">
		<!-- wp:post-kinds-indieweb/follow-letters /-->

		<!-- wp:post-template {"className":"cr-rolodex__cards"} -->
			<!-- wp:post-kinds-indieweb/follow-entry {"headingLevel":2,"className":"is-style-cr-rolodex"} /-->
		<!-- /wp:post-template -->

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
