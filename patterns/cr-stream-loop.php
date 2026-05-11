<?php
/**
 * Title: Stream Loop (Query Loop)
 * Slug: courtneyr-child/cr-stream-loop
 * Categories: cr-zine
 * Description: Query Loop styled as a vertical zine stream — SVG post-type avatar, date + category chip meta row, post title, excerpt, and tag pills. Drives the archive, category, tag, and home (blog) page templates.
 * Keywords: stream, feed, query, archive, posts, zine, indieweb
 * Viewport Width: 720
 * Block Types: core/post-template, core/query
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:html -->
<div class="cr-stream-filter" data-wp-interactive="courtneyr/stream-filter" data-wp-context='{"format":""}' data-wp-bind--data-active-format="context.format">
	<div class="cr-stream-filter__chips" role="group" aria-label="Filter posts by format">
		<button type="button" class="cr-stream-filter__chip" data-wp-on--click="actions.setFormat" data-format="" aria-pressed="true">All</button>
		<button type="button" class="cr-stream-filter__chip" data-wp-on--click="actions.setFormat" data-format="blog" aria-pressed="false">Blog</button>
		<button type="button" class="cr-stream-filter__chip" data-wp-on--click="actions.setFormat" data-format="quote" aria-pressed="false">Quote</button>
		<button type="button" class="cr-stream-filter__chip" data-wp-on--click="actions.setFormat" data-format="link" aria-pressed="false">Link</button>
		<button type="button" class="cr-stream-filter__chip" data-wp-on--click="actions.setFormat" data-format="gallery" aria-pressed="false">Gallery</button>
		<button type="button" class="cr-stream-filter__chip" data-wp-on--click="actions.setFormat" data-format="video" aria-pressed="false">Video</button>
	</div>
<!-- /wp:html -->

<!-- wp:query {"queryId":42,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"align":"wide","className":"cr-stream"} -->
<div class="wp-block-query alignwide cr-stream">

	<!-- wp:post-template {"className":"cr-stream__list"} -->

		<!-- wp:group {"tagName":"article","className":"cr-stream-item","style":{"spacing":{"blockGap":"var:preset|spacing|small"}},"layout":{"type":"constrained"}} -->
		<article class="wp-block-group cr-stream-item">

			<!-- v0.5.51: SVG avatar default. The render_block filter
			     `transform_stream_item_avatar` (inc/interactivity.php) swaps
			     in the right post-format variant at render time based on
			     each post's get_post_format(). The default markup here is
			     the fallback used when no format is set (Standard = "blog"). -->
			<!-- wp:html -->
			<span class="cr-icon-avatar cr-icon-avatar--blog cr-stream-item__avatar" aria-hidden="true" data-cr-stream-avatar="">
				<svg viewBox="0 0 24 24"><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/svg/icons.svg#post-icon-blog"></use></svg>
			</span>
			<!-- /wp:html -->

			<!-- wp:group {"className":"cr-stream-item__body","style":{"spacing":{"blockGap":"var:preset|spacing|small"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group cr-stream-item__body">

				<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9","className":"cr-stream-item__featured"} /-->

				<!-- wp:group {"className":"cr-stream-item__meta","style":{"spacing":{"blockGap":"var:preset|spacing|small"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
				<div class="wp-block-group cr-stream-item__meta">
					<!-- wp:post-date {"format":"F j, Y","fontSize":"small"} /-->

					<!-- wp:html -->
					<span class="cr-format-chip" aria-hidden="true"></span>
					<!-- /wp:html -->

					<!-- wp:post-terms {"term":"category","className":"cr-stream-item__category","fontSize":"xs"} /-->
				</div>
				<!-- /wp:group -->

				<!-- wp:post-title {"isLink":true,"className":"cr-stream-item__title","style":{"typography":{"lineHeight":"1.2"}},"fontSize":"large","fontFamily":"accent"} /-->

				<!-- wp:post-excerpt {"className":"cr-stream-item__excerpt","excerptLength":40,"showMoreOnNewLine":false} /-->

				<!-- wp:read-more {"content":"Read more →","className":"cr-stream-item__readmore"} /-->

				<!-- wp:post-terms {"term":"post_tag","className":"is-style-term-button cr-stream-item__tags","prefix":"","separator":" "} /-->

			</div>
			<!-- /wp:group -->

		</article>
		<!-- /wp:group -->

	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph -->
		<p>No posts found in this stream. Check back soon, or browse <a href="/">the home page</a>.</p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->

	<!-- wp:query-pagination {"paginationArrow":"arrow","className":"cr-stream__pagination","layout":{"type":"flex","justifyContent":"space-between"}} -->
		<!-- wp:query-pagination-previous /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->

</div>
<!-- /wp:query -->

<!-- wp:html -->
</div>
<!-- /wp:html -->
