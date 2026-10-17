<?php
/**
 * Title: Weather almanac loop
 * Slug: courtneyr-child/cr-weather-almanac-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the weather archive, with one almanac month and core pagination.
 * Keywords: weather, almanac, calendar, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Weather almanac"},"align":"wide","className":"cr-weather-almanac","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-weather-almanac">
	<!-- wp:query {"queryId":246,"query":{"postType":"post","order":"desc","orderBy":"date","inherit":true},"className":"cr-weather-almanac__query"} -->
	<div class="wp-block-query cr-weather-almanac__query">
		<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/almanac-month' ) ) : ?>
		<!-- wp:post-kinds-indieweb/almanac-month {"headingLevel":2,"showYearStrip":true} /-->
		<?php else : ?>
		<!-- wp:post-template -->
			<!-- wp:post-date /-->
			<!-- wp:post-title {"level":3,"isLink":true} /-->
		<!-- /wp:post-template -->
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
