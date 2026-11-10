<?php
/**
 * Title: Presentation badge wall
 * Slug: courtneyr-child/cr-presentation-wall-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<?php if ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/presentation-entry' ) ) : ?>
<!-- wp:query {"queryId":262,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"align":"wide","className":"cr-badge-wall__query"} -->
<div class="wp-block-query alignwide cr-badge-wall__query">
	<!-- wp:group {"metadata":{"name":"Presentation badge wall"},"align":"wide","className":"cr-badge-wall","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide cr-badge-wall">
		<!-- wp:post-template {"className":"cr-badge-wall__entries"} -->
			<!-- wp:post-kinds-indieweb/presentation-entry {"linesPerPage":12,"headingLevel":2,"sectionOrder":"desc","className":"is-style-cr-badge-wall"} /-->
		<!-- /wp:post-template -->
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
<?php else : ?>
<!-- wp:pattern {"slug":"courtneyr-child/cr-archive-stream-loop"} /-->
<?php endif; ?>
