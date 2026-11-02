<?php
/**
 * Title: Series archive parts
 * Slug: courtneyr-child/cr-series-body
 * Categories: cr-zine, cr-loops
 * Description: The orange rule, the season index, the published parts in part order (inc/series.php orders the main query, ten per page) as numbered zine cards, the launch state when nothing is published, pagination, and companion series.
 * Keywords: series, parts, seasons, query, merge conflicts
 * Block Types: core/query
 * Inserter: false
 *
 * Part chip and season label: core/paragraph bound to the courtneyr/series-part
 * source (inc/series.php). Card classes come from cr-blog.css (.cr-blog .card).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:separator {"align":"wide","className":"is-style-cr-marker-bar cr-series-rule"} -->
<hr class="wp-block-separator alignwide has-alpha-channel-opacity is-style-cr-marker-bar cr-series-rule"/>
<!-- /wp:separator -->

<!-- wp:courtneyr/series-guide {"view":"seasons"} /-->

<!-- wp:query {"queryId":91,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"asc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"align":"wide","className":"cr-series-query"} -->
<div class="wp-block-query alignwide cr-series-query">

	<!-- wp:post-template {"className":"cr-series-list"} -->

		<!-- wp:group {"tagName":"article","className":"card cr-series-card","layout":{"type":"default"}} -->
		<article class="wp-block-group card cr-series-card">

			<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"courtneyr/series-part","args":{"key":"part"}}}},"className":"cr-tape cr-series-card__part"} -->
			<p class="cr-tape cr-series-card__part"></p>
			<!-- /wp:paragraph -->

			<!-- wp:group {"className":"card__body cr-series-card__body","layout":{"type":"default"}} -->
			<div class="wp-block-group card__body cr-series-card__body">

				<!-- wp:group {"className":"card__meta-row","layout":{"type":"flex","flexWrap":"wrap"}} -->
				<div class="wp-block-group card__meta-row">
					<!-- wp:post-date {"format":"F j, Y","className":"card__meta cr-dt-published","fontSize":"xs"} /-->
					<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"courtneyr/series-part","args":{"key":"season"}}}},"className":"card__meta cr-series-card__season","fontSize":"xs"} -->
					<p class="card__meta cr-series-card__season has-xs-font-size"></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:post-title {"isLink":true,"className":"card__title p-name cr-u-url","style":{"typography":{"lineHeight":"1.2"}},"fontFamily":"accent"} /-->

				<!-- wp:post-excerpt {"className":"card__excerpt","excerptLength":30,"showMoreOnNewLine":false} /-->

			</div>
			<!-- /wp:group -->

			<!-- wp:post-featured-image {"isLink":false,"aspectRatio":"4/3","sizeSlug":"medium","className":"cr-series-card__media"} /-->

		</article>
		<!-- /wp:group -->

	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:courtneyr/series-guide {"view":"launch"} /-->
	<!-- /wp:query-no-results -->

	<!-- wp:query-pagination {"paginationArrow":"arrow","className":"cr-series-pagination","layout":{"type":"flex","justifyContent":"center"}} -->
		<!-- wp:query-pagination-previous <?php echo wp_json_encode( array( 'label' => __( 'Earlier parts', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next <?php echo wp_json_encode( array( 'label' => __( 'Later parts', 'courtneyr-child' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block attribute JSON. ?> /-->
	<!-- /wp:query-pagination -->

</div>
<!-- /wp:query -->

<!-- wp:courtneyr/series-guide {"view":"companions"} /-->
