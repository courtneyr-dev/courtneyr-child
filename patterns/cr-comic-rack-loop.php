<?php
/**
 * Title: Comic rack loop
 * Slug: courtneyr-child/cr-comic-rack-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the comics archive — inherits the archive query and stands each comic face-out on a comic-shop rack; inc/comic.php reduces a comic read to its bagged cover and title link (PKIW #228).
 * Keywords: rack, comic, comics, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Comic rack"},"align":"wide","className":"cr-archive-stream cr-comic-rack","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-comic-rack">
	<!-- wp:query {"queryId":97,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query"} -->
	<div class="wp-block-query cr-archive-stream__query">
		<!-- wp:post-template {"className":"cr-archive-stream__list cr-comic-rack__list"} -->
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":2} /-->
		<!-- /wp:post-template -->

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
