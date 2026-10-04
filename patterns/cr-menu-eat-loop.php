<?php
/**
 * Title: Eat menu loop
 * Slug: courtneyr-child/cr-menu-eat-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the eat archive — the plugin's Recent Specials block, then the archive query as menu lines in one section per cuisine; the menu entry sets 6 lines a page and cr-menu.css draws the paper.
 * Keywords: menu, eat, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Eat menu"},"align":"wide","className":"cr-archive-stream cr-menu cr-menu--eat","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-menu cr-menu--eat">
	<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/menu-specials' ) ) : ?>
	<!-- wp:post-kinds-indieweb/menu-specials {"kind":"eat","className":"cr-menu__specials"} /-->
	<?php endif; ?>

	<!-- wp:query {"queryId":230,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-menu__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-menu__query">
		<!-- wp:post-template {"className":"cr-archive-stream__list is-style-pkiw-menu cr-menu__list"} -->
			<!-- wp:post-kinds-indieweb/menu-entry {"linesPerPage":6} /-->
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
