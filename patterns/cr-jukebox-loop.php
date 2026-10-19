<?php
/**
 * Title: Jukebox loop
 * Slug: courtneyr-child/cr-jukebox-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the jam archive. The newest jam leads as Now Playing, followed by month-grouped jukebox strips and core pagination.
 * Keywords: jam, jukebox, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Jukebox"},"align":"wide","className":"cr-jukebox","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-jukebox">
	<!-- wp:query {"queryId":235,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-jukebox__query"} -->
	<div class="wp-block-query cr-jukebox__query">
		<!-- wp:post-template {"className":"cr-jukebox__list"} -->
			<!-- wp:post-kinds-indieweb/archive-sections <?php echo wp_json_encode( array( 'linesPerPage' => 12, 'leadCount' => 1, 'leadLabel' => __( 'Now Playing', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound,WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3} /-->
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'No jams yet.', 'courtneyr-child' ); ?></p>
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
