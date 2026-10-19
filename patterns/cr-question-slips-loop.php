<?php
/**
 * Title: Question slips loop
 * Slug: courtneyr-child/cr-question-slips-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Block Types: core/query
 * Inserter: false
 *
 * Question archive Query Loop for issue 256.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Question slips"},"align":"wide","className":"cr-archive-stream cr-question","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-question">
	<!-- wp:query {"queryId":256,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-question__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-question__query">
		<!-- wp:post-template {"className":"cr-archive-stream__list cr-question__list"} -->
			<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/question-entry' ) ) : ?>
			<!-- wp:post-kinds-indieweb/question-entry {"linesPerPage":12,"headingLevel":2} /-->
			<?php else : ?>
			<!-- wp:post-title {"level":3,"isLink":true} /-->
			<?php endif; ?>
		<!-- /wp:post-template -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous <?php echo wp_json_encode( array( 'label' => __( 'Previous', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next <?php echo wp_json_encode( array( 'label' => __( 'Next', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
