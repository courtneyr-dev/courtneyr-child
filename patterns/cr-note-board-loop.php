<?php
/**
 * Title: Note board loop
 * Slug: courtneyr-child/cr-note-board-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the note archive — inherits the archive query and groups note-entry blocks into week boards.
 * Keywords: note, board, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Note board"},"align":"wide","className":"cr-archive-stream cr-note-board","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-note-board">
	<!-- wp:query {"queryId":265,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query"} -->
	<div class="wp-block-query cr-archive-stream__query">
		<!-- wp:post-template {"className":"cr-note-board__list is-style-cr-note-board"} -->
			<?php if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/archive-sections' ) ) : ?>
			<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"headingLevel":2} /-->
			<?php endif; ?>
			<?php if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/note-entry' ) ) : ?>
			<!-- wp:post-kinds-indieweb/note-entry {"headingLevel":3,"className":"is-style-cr-sticky"} /-->
			<?php else : ?>
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3} /-->
			<?php endif; ?>
		<!-- /wp:post-template -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous <?php echo wp_json_encode( array( 'label' => __( 'Previous', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next <?php echo wp_json_encode( array( 'label' => __( 'Next', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
		<!-- /wp:query-pagination -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
