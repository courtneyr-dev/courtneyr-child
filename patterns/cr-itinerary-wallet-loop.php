<?php
/**
 * Title: Itinerary ticket wallet archive loop
 * Slug: courtneyr-child/cr-itinerary-wallet-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<?php if ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/itinerary-entry' ) ) : ?>
<!-- wp:query {"queryId":245,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"align":"wide","className":"cr-travel-query cr-travel-query--itinerary"} -->
<div class="wp-block-query alignwide cr-travel-query cr-travel-query--itinerary">
	<!-- wp:group {"metadata":{"name":"Itinerary ticket wallet archive"},"align":"wide","className":"cr-travel","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide cr-travel">
		<!-- wp:post-kinds-indieweb/travel-map {"kind":"itinerary","inherit":true} /-->

		<!-- wp:post-template {"className":"cr-travel__entries"} -->
			<!-- wp:post-kinds-indieweb/itinerary-entry {"linesPerPage":12,"headingLevel":2,"className":"is-style-cr-wallet"} /-->
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
		<p class="cr-archive-stream__empty"><?php esc_html_e( 'No itineraries yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
<?php else : ?>
<!-- wp:pattern {"slug":"courtneyr-child/cr-archive-stream-loop"} /-->
<?php endif; ?>
