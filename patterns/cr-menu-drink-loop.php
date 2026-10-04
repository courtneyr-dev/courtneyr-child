<?php
/**
 * Title: Drink menu loop
 * Slug: courtneyr-child/cr-menu-drink-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the drink archive — the plugin's Recent Specials block, then the archive query as menu lines in one section per drink type; the menu entry sets 8 lines a page and cr-menu.css draws the paper.
 * Keywords: menu, drink, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Drink menu"},"align":"wide","className":"cr-archive-stream cr-menu cr-menu--drink","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-menu cr-menu--drink">
	<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/menu-specials' ) ) : ?>
	<!-- wp:post-kinds-indieweb/menu-specials {"kind":"drink","className":"cr-menu__specials"} /-->
	<?php endif; ?>

	<!-- wp:query {"queryId":231,"query":{"perPage":8,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-menu__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-menu__query">
		<!-- wp:post-template {"className":"cr-archive-stream__list is-style-pkiw-menu cr-menu__list"} -->
			<!-- wp:post-kinds-indieweb/menu-entry {"linesPerPage":8} /-->
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'Nothing on the menu yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
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
