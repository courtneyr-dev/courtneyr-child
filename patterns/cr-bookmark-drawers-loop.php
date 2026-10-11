<?php
/**
 * Title: Bookmark drawers loop
 * Slug: courtneyr-child/cr-bookmark-drawers-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the bookmark archive, filing PKIW issue 258 index cards into grouped drawers.
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Bookmark drawers"},"align":"wide","className":"cr-archive-stream cr-bookmark-drawers","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-bookmark-drawers">
	<!-- wp:query {"queryId":258,"query":{"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-bookmark-drawers__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-bookmark-drawers__query">
		<!-- wp:post-template {"className":"cr-archive-stream__list cr-bookmark-drawers__list"} -->
			<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/archive-sections' ) ) : ?>
			<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"headingLevel":2} /-->
			<?php endif; ?>
			<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/stream-card' ) ) : ?>
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-bookmark-card"} /-->
			<?php endif; ?>
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous {"label":"Previous"} /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next {"label":"Next"} /-->
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
