<?php
/**
 * Title: Mood year loop
 * Slug: courtneyr-child/cr-mood-year-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the mood archive. The plugin's year grid prints every mood in the current calendar year, with core pagination between years.
 * Keywords: mood, year, calendar, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Mood year"},"align":"wide","className":"cr-mood-archive","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-mood-archive">
	<!-- wp:query {"queryId":248,"query":{"perPage":24,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-mood-archive__query"} -->
	<div class="wp-block-query cr-mood-archive__query">
		<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/mood-year-grid' ) ) : ?>
		<!-- wp:post-kinds-indieweb/mood-year-grid {"headingLevel":2,"showLegend":true} /-->
		<?php endif; ?>

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
