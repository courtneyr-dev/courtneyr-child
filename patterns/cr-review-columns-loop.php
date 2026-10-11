<?php
/**
 * Title: Review columns loop
 * Slug: courtneyr-child/cr-review-columns-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the review archive with Four Stars followed by category columns and pagination.
 * Keywords: review, reviews, critics, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Review columns"},"align":"wide","className":"cr-archive-stream cr-review cr-review-columns","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-review cr-review-columns">
	<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/four-stars' ) ) : ?>
	<!-- wp:post-kinds-indieweb/four-stars {"kind":"review","className":"cr-review__four-stars cr-four-stars cr-paper-sheet cr-paper--light-orange cr-paper-sheet--torn"} /-->
	<?php endif; ?>

	<!-- wp:query {"queryId":252,"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-review__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-review__query">
		<!-- wp:post-template {"className":"cr-archive-stream__list cr-review__list"} -->
			<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/review-entry' ) ) : ?>
			<!-- wp:post-kinds-indieweb/review-entry {"linesPerPage":9} /-->
			<?php else : ?>
			<!-- wp:post-title {"level":3,"isLink":true} /-->
			<?php endif; ?>
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
