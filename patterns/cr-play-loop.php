<?php
/**
 * Title: Play loop
 * Slug: courtneyr-child/cr-play-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the play archive, with optional Staff Picks followed by grouped video games, board games and ungrouped plays on shared shelf boards.
 * Keywords: play, games, video, board, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Play shelves"},"align":"wide","className":"cr-archive-stream cr-play cr-shelf-boards","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-play cr-shelf-boards">
	<?php if ( WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/staff-picks' ) ) : ?>
	<!-- wp:post-kinds-indieweb/staff-picks {"className":"cr-play__picks"} /-->
	<?php endif; ?>

	<!-- wp:query {"queryId":232,"query":{"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-play__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-play__query">
		<!-- wp:post-template -->
			<!-- wp:post-kinds-indieweb/archive-sections <?php echo wp_json_encode( array( 'linesPerPage' => 12, 'headingLevel' => 2, 'emptyLabel' => __( 'Play', 'courtneyr-child' ), 'groupLabels' => array( 'video' => __( 'Video games', 'courtneyr-child' ), 'board' => __( 'Game Night', 'courtneyr-child' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound, WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-play-item"} /-->
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
