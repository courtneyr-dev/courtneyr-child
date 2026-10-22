<?php
/**
 * Title: Issue rack loop
 * Slug: courtneyr-child/cr-issue-rack-loop
 * Categories: cr-zine, cr-loops
 * Viewport Width: 1200
 * Description: Query Loop for the issue archive, grouped into repository racks and status lanes.
 * Keywords: issue, repository, rack, archive, kind, query
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$courtneyr_child_issue_section_attrs = array(
	'linesPerPage' => 12,
	'headingLevel' => 2,
	'emptyLabel'   => __( 'Issue', 'courtneyr-child' ),
	'groupLabels'  => array(
		'open'   => __( 'Waiting — Open', 'courtneyr-child' ),
		'closed' => __( 'Picked up — Closed', 'courtneyr-child' ),
	),
);
?>
<!-- wp:group {"metadata":{"name":"Issue racks"},"align":"full","className":"cr-archive-stream cr-issue","style":{"spacing":{"padding":{"right":"var:preset|spacing|md","left":"var:preset|spacing|md"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull cr-archive-stream cr-issue" style="padding-right:var(--wp--preset--spacing--md);padding-left:var(--wp--preset--spacing--md)">
	<!-- wp:query {"queryId":264,"query":{"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"className":"cr-archive-stream__query cr-issue__query"} -->
	<div class="wp-block-query cr-archive-stream__query cr-issue__query">
		<!-- wp:post-template {"className":"cr-issue-rack"} -->
			<!-- wp:post-kinds-indieweb/archive-sections <?php echo wp_json_encode( $courtneyr_child_issue_section_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
			<!-- wp:post-kinds-indieweb/stream-card {"headingLevel":4,"className":"is-style-cr-claim-ticket"} /-->
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"cr-archive-stream__empty"} -->
			<p class="cr-archive-stream__empty"><?php esc_html_e( 'No issues yet.', 'courtneyr-child' ); ?></p>
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
