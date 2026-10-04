<?php
/**
 * Title: Recipe binder loop
 * Slug: courtneyr-child/cr-recipe-binder-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the recipe archive — inherits the archive query and files each recipe as a card in a ring binder, four to a page; inc/recipe.php reduces a recipe to its picture, title link, course and time, and adds the course tabs (PKIW #229).
 * Keywords: binder, recipe, recipes, cookbook, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Recipe binder"},"align":"wide","className":"cr-archive-stream cr-recipe-binder","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-recipe-binder">
	<!-- wp:query {"queryId":229,"query":{"perPage":4,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-recipe-binder__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-recipe-binder__query">
		<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/recipe-courses' ) ) : ?>
		<!-- wp:post-kinds-indieweb/recipe-courses {"className":"cr-recipe-tabs"} /-->
		<?php endif; ?>

		<!-- wp:post-template {"className":"cr-archive-stream__list cr-recipe-binder__list"} -->
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":2,"className":"is-style-cr-binder-card"} /-->
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
