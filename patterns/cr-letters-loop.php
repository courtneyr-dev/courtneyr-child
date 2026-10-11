<?php
/**
 * Title: Letters loop
 * Slug: courtneyr-child/cr-letters-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the reply archive, grouped into letters by cited host (PKIW issue 253).
 * Keywords: reply, letters, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$courtneyr_child_letters_registry             = class_exists( 'WP_Block_Type_Registry' ) ? \WP_Block_Type_Registry::get_instance() : null;
$courtneyr_child_letters_has_archive_sections = $courtneyr_child_letters_registry && $courtneyr_child_letters_registry->is_registered( 'post-kinds-indieweb/archive-sections' );
$courtneyr_child_letters_has_stream_card      = $courtneyr_child_letters_registry && $courtneyr_child_letters_registry->is_registered( 'post-kinds-indieweb/stream-card' );
?>
<!-- wp:group {"metadata":{"name":"Letters"},"align":"wide","className":"cr-letters","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-letters">
	<!-- wp:query {"queryId":253,"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-letters__query"} -->
	<div class="wp-block-query cr-letters__query">
		<!-- wp:post-template -->
			<?php if ( $courtneyr_child_letters_has_archive_sections ) : ?>
			<!-- wp:post-kinds-indieweb/archive-sections {"linesPerPage":9,"headingLevel":2,"emptyLabel":""} /-->
			<?php endif; ?>
			<?php if ( $courtneyr_child_letters_has_stream_card ) : ?>
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-letter"} /-->
			<?php else : ?>
			<!-- wp:post-title {"level":3,"isLink":true} /-->
			<!-- wp:post-excerpt {"excerptLength":25,"moreText":""} /-->
			<?php endif; ?>
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-letters__empty"} -->
			<p class="cr-letters__empty"><?php esc_html_e( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.', 'courtneyr-child' ); ?></p>
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
