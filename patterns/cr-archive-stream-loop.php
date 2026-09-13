<?php
/**
 * Title: Archive stream loop
 * Slug: courtneyr-child/cr-archive-stream-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for kind and Stream-format archives — inherits the archive query and renders the plugin's stream cards in the Stream collage.
 * Keywords: stream, archive, kind, format, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Stream objects"},"align":"wide","className":"cr-archive-stream cr-stream-page","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-stream-page">
	<!-- wp:query {"queryId":94,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query"} -->
	<div class="wp-block-query cr-archive-stream__query">
		<!-- wp:post-template {"className":"cr-archive-stream__list"} -->
			<!-- wp:post-kinds-indieweb/stream-card /-->
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","className":"cr-stream__pagination","layout":{"type":"flex","justifyContent":"space-between"}} -->
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
