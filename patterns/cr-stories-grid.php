<?php
/**
 * Title: Stories poster grid
 * Slug: courtneyr-child/cr-stories-grid
 * Categories: cr-zine
 * Description: Query Loop for the Web Stories archive — each story as a 9:16 poster card (the plugin's poster is the featured image) with title and date; playback stays on the plugin's story URL.
 * Keywords: stories, web stories, archive, posters
 * Block Types: core/query
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:query {"queryId":95,"query":{"perPage":12,"pages":0,"offset":0,"postType":"web-story","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"align":"wide","className":"cr-stories"} -->
<div class="wp-block-query alignwide cr-stories">
	<!-- wp:post-template {"className":"cr-stories__list","layout":{"type":"grid","minimumColumnWidth":"14rem"}} -->
		<!-- wp:group {"tagName":"article","className":"cr-stories__card","layout":{"type":"default"}} -->
		<article class="wp-block-group cr-stories__card">
			<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"9/16","className":"cr-stories__poster"} /-->
			<!-- wp:group {"className":"cr-stories__label","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-stories__label">
				<!-- wp:post-title {"level":2,"isLink":true,"className":"cr-stories__title p-name cr-u-url","fontSize":"medium","fontFamily":"accent"} /-->
				<!-- wp:post-date {"format":"F j, Y","className":"cr-stories__date cr-dt-published","fontSize":"xs"} /-->
			</div>
			<!-- /wp:group -->
		</article>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph {"className":"cr-stories__empty"} -->
		<p class="cr-stories__empty"><?php esc_html_e( 'No stories yet.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->

	<!-- wp:query-pagination {"paginationArrow":"arrow","className":"cr-stream__pagination","layout":{"type":"flex","justifyContent":"space-between"}} -->
		<!-- wp:query-pagination-previous /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->
