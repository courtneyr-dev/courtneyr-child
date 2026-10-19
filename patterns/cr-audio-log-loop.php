<?php
/**
 * Title: Audio log loop
 * Slug: courtneyr-child/cr-audio-log-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the audio archive with grouped log cassettes and core pagination.
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Audio log"},"align":"wide","className":"cr-archive-stream cr-stream-page cr-audio-log","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-archive-stream cr-stream-page cr-audio-log">
	<!-- wp:query {"queryId":243,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-audio-log__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-audio-log__query">
		<!-- wp:post-template {"className":"is-style-cr-audio-log"} -->
			<?php if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/archive-sections' ) ) : ?>
			<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":12,"emptyLabel":"","headingLevel":2} /-->
			<?php endif; ?>
			<?php if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/stream-card' ) ) : ?>
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"cr-audio-log-card"} /-->
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
