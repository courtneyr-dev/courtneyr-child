<?php
/**
 * Title: Acquisition bags loop
 * Slug: courtneyr-child/cr-acquisition-bags-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the acquisition archive, filing PKIW issue 239 receipts into one paper bag per month; the archive-sections block sets 12 receipts a page and cr-acquisition.css draws the bags.
 * Keywords: acquisition, receipt, bag, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$courtneyr_child_acq_registry = WP_Block_Type_Registry::get_instance();
?>
<!-- wp:group {"metadata":{"name":"Acquisition bags"},"align":"wide","className":"cr-archive-stream cr-acq","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-acq">
	<!-- wp:query {"queryId":239,"query":{"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-acq__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-acq__query">
		<!-- wp:post-template {"className":"cr-archive-stream__list cr-acq__bags"} -->
			<?php if ( $courtneyr_child_acq_registry->is_registered( 'post-kinds-indieweb/archive-sections' ) ) : ?>
			<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"headingLevel":2} /-->
			<?php endif; ?>
			<?php if ( $courtneyr_child_acq_registry->is_registered( 'post-kinds-indieweb/stream-card' ) ) : ?>
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-receipt"} /-->
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
