<?php
/**
 * Title: Exercise training log loop
 * Slug: courtneyr-child/cr-exercise-training-log-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the exercise archive, with personal-best medals above calendar weeks and core pagination after the training log.
 * Keywords: exercise, training, calendar, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Exercise training log"},"align":"wide","className":"cr-exercise","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-exercise">
	<?php if ( class_exists( '\WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/medal-row' ) ) : ?>
	<!-- wp:post-kinds-indieweb/medal-row {"heading":"","count":3} /-->
	<?php endif; ?>

	<!-- wp:query {"queryId":245,"query":{"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-exercise__query"} -->
	<div class="wp-block-query cr-exercise__query">
		<!-- wp:post-template {"className":"cr-exercise__weeks"} -->
			<?php if ( class_exists( '\WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/exercise-entry' ) ) : ?>
			<!-- wp:post-kinds-indieweb/exercise-entry {"weeksPerPage":4,"className":"is-style-cr-bib"} /-->
			<?php else : ?>
			<!-- wp:post-title {"level":3,"isLink":true} /-->
			<!-- wp:post-date /-->
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
