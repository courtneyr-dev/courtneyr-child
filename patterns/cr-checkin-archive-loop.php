<?php
/**
 * Title: Check-in archive loop
 * Slug: courtneyr-child/cr-checkin-archive-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the check-in archive: the plugin's Check-ins Feed in archive mode prints the page's check-ins as a map and a linked list, 24 a page; cr-checkin-archive.css draws the placemat.
 * Keywords: checkin, map, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Check-in placemat"},"align":"wide","className":"cr-archive-stream cr-checkin-archive","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-checkin-archive">
	<!-- wp:query {"queryId":224,"query":{"perPage":24,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-checkin-archive__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-checkin-archive__query">
		<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/checkins-feed' ) ) : ?>
		<!-- wp:post-kinds-indieweb/checkins-feed {"inherit":true,"count":24} /-->
		<?php endif; ?>

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'No check-ins yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
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
