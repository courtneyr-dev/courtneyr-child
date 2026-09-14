<?php
/**
 * Title: Related posts
 * Slug: courtneyr-child/cr-related-posts
 * Categories: cr-loops
 * Block Types: core/query
 * Description: "More from the blog" band for singles: the three newest main-surface posts (Query Loop variation courtneyr/related-posts, pkiwSurface main) with the h-entry name, URL and published markers.
 * Inserter: yes
 *
 * @package CourtneyrChild
 */

?>
<!-- wp:group {"metadata":{"name":"Related Posts"},"align":"full","className":"related-posts","style":{"spacing":{"padding":{"top":"3rem","bottom":"3rem"},"blockGap":"var:preset|spacing|large","margin":{"top":"var:preset|spacing|x-large"}}},"backgroundColor":"primary-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull related-posts has-primary-accent-background-color has-background" style="margin-top:var(--wp--preset--spacing--x-large);padding-top:3rem;padding-bottom:3rem">

	<!-- wp:heading {"level":2,"className":"related-posts__heading","fontFamily":"accent"} -->
	<h2 class="wp-block-heading related-posts__heading">More from the blog</h2>
	<!-- /wp:heading -->

	<!-- wp:query {"queryId":1,"query":{"perPage":"3","pages":"3","offset":0,"postType":"post","order":"desc","orderBy":"date","exclude":[],"sticky":"exclude","inherit":false,"pkiwSurface":"main"},"align":"wide","className":"related-posts__query","namespace":"courtneyr/related-posts"} -->
	<div class="wp-block-query alignwide related-posts__query">
		<!-- wp:post-template {"className":"related-posts__list","style":{"spacing":{"blockGap":"var:preset|spacing|large"}},"layout":{"type":"grid","columnCount":3}} -->
			<!-- wp:group {"className":"related-post","style":{"spacing":{"blockGap":"var:preset|spacing|small"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center","justifyContent":"left"}} -->
			<div class="wp-block-group related-post">
				<!-- wp:html -->
				<span class="cr-icon-avatar cr-icon-avatar--blog related-post__avatar" aria-hidden="true" data-cr-related-avatar="placeholder"><svg viewBox="0 0 24 24" width="1.5em" height="1.5em" focusable="false"><use href="#post-icon-blog"></use></svg></span>
				<!-- /wp:html -->

				<!-- wp:group {"className":"related-post__text","style":{"spacing":{"blockGap":"var:preset|spacing|2x-small"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"left"}} -->
				<div class="wp-block-group related-post__text">
					<!-- wp:post-title {"isLink":true,"className":"related-post__title is-style-show-format-title p-name cr-u-url","style":{"spacing":{"margin":{"top":"0","bottom":"0"}},"typography":{"fontStyle":"normal","fontWeight":"600","textDecoration":"none","lineHeight":"1.3"}},"fontSize":"medium","fontFamily":"accent"} /-->
					<!-- wp:post-date {"format":"F j, Y","className":"related-post__date cr-dt-published","fontSize":"x-small"} /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->

</div>
<!-- /wp:group -->
