<?php
/**
 * Title: Sleep chart loop
 * Slug: courtneyr-child/cr-sleep-chart-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the sleep archive. The plugin groups nights into monthly chart sheets, followed by core pagination.
 * Keywords: sleep, moon, chart, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Sleep charts"},"align":"wide","className":"cr-sleep","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-sleep">
	<!-- wp:query {"queryId":247,"query":{"perPage":31,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-sleep__query"} -->
	<div class="wp-block-query cr-sleep__query">
		<!-- wp:post-template {"className":"cr-sleep__months"} -->
			<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/sleep-night' ) ) : ?>
			<!-- wp:post-kinds-indieweb/sleep-night {"linesPerPage":31} /-->
			<?php else : ?>
			<!-- wp:post-date {"format":"M d"} /-->
			<!-- wp:post-title {"level":3,"isLink":true} /-->
			<?php endif; ?>
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
