<?php
/**
 * Title: Blog Grid (Query Loop)
 * Slug: courtneyr-child/cr-blog-grid
 * Categories: cr-zine
 * Description: Wide zine blog index — featured lead (first post) + 2-up card grid. Each card has a per-format gradient media area, category chip, post-format media-glyph, date, title, excerpt, and read-more. Drives the home (blog posts index) template only.
 * Keywords: blog, archive, query, posts, grid, cards, zine
 * Viewport Width: 1100
 * Block Types: core/post-template, core/query
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:query {"queryId":81,"query":{"perPage":11,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"align":"wide","className":"cr-blog__query"} -->
<div class="wp-block-query alignwide cr-blog__query">

	<!-- wp:post-template {"className":"post-grid"} -->

		<!-- wp:group {"tagName":"article","className":"card","layout":{"type":"default"}} -->
		<article class="wp-block-group card">

			<!-- wp:group {"className":"card__media","layout":{"type":"default"}} -->
			<div class="wp-block-group card__media">

				<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->

				<!-- v0.5.159: post-format glyph. transform_blog_card_glyph()
				     (inc/interactivity.php) swaps the #post-icon-* fragment +
				     aria-label per post at render time; default is blog. -->
				<!-- wp:html -->
				<span class="media-glyph" role="img" aria-label="Post format: blog post" data-cr-card-glyph="blog"><svg viewBox="0 0 24 24"><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/svg/icons.svg#post-icon-blog"></use></svg></span>
				<!-- /wp:html -->

			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"card__body","layout":{"type":"default"}} -->
			<div class="wp-block-group card__body">

				<!-- wp:group {"className":"card__meta-row","layout":{"type":"flex","flexWrap":"wrap"}} -->
				<div class="wp-block-group card__meta-row">
					<!-- wp:post-date {"format":"F j, Y","className":"card__meta","fontSize":"xs"} /-->
					<!-- wp:post-terms {"term":"category","className":"card__chip","fontSize":"xs"} /-->
				</div>
				<!-- /wp:group -->

				<!-- wp:post-title {"isLink":true,"className":"card__title","style":{"typography":{"lineHeight":"1.2"}},"fontFamily":"accent"} /-->

				<!-- wp:post-excerpt {"className":"card__excerpt","excerptLength":26,"showMoreOnNewLine":false} /-->

				<!-- wp:read-more {"content":"Read more →","className":"card__more"} /-->

			</div>
			<!-- /wp:group -->

		</article>
		<!-- /wp:group -->

	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph -->
		<p>No posts in this stream yet. Check back soon, or head <a href="/">home</a>.</p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->

	<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
		<!-- wp:query-pagination-previous /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->

</div>
<!-- /wp:query -->
