<?php
/**
 * Title: Repost zine loop
 * Slug: courtneyr-child/cr-repost-zine-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the repost archive, one photocopied zine spread per month with the reposts taped on as clippings; the archive-sections marker sets 12 a page and cr-repost.css draws the paper.
 * Keywords: repost, zine, clipping, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$courtneyr_child_registry     = WP_Block_Type_Registry::get_instance();
$courtneyr_child_has_sections = $courtneyr_child_registry->is_registered( 'post-kinds-indieweb/archive-sections' );
$courtneyr_child_has_card     = $courtneyr_child_registry->is_registered( 'post-kinds-indieweb/stream-card' );
?>
<!-- wp:group {"metadata":{"name":"Repost zine"},"align":"wide","className":"cr-repost-zine","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-repost-zine">
	<!-- wp:query {"queryId":255,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-repost-zine__query"} -->
	<div class="wp-block-query cr-repost-zine__query">
		<!-- wp:post-template {"className":"cr-repost-zine__spreads"} -->
			<?php if ( $courtneyr_child_has_sections && $courtneyr_child_has_card ) : ?>
			<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"headingLevel":2} /-->
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-repost-clipping"} /-->
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
