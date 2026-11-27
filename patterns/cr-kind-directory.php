<?php
/**
 * Title: Kind directory
 * Slug: courtneyr-child/cr-kind-directory
 * Categories: cr-loops, cr-indieweb
 * Description: Links to published Post Kinds archives with their post counts.
 * Keywords: kinds, terms, archives, directory
 * Block Types: core/terms-query
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

if ( ! is_taxonomy_viewable( 'kind' ) ) {
	return;
}
?>
<!-- wp:terms-query {"termQuery":{"perPage":0,"taxonomy":"kind","order":"asc","orderBy":"name","include":[],"hideEmpty":true,"showNested":true,"inherit":false}} -->
<div class="wp-block-terms-query">
	<!-- wp:term-template {"className":"cr-browse-all__list"} -->
		<!-- wp:group {"style":{"spacing":{"blockGap":"0.25em"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
		<div class="wp-block-group">
			<!-- wp:term-name {"isLink":true} /-->
			<!-- wp:term-count /-->
		</div>
		<!-- /wp:group -->
	<!-- /wp:term-template -->
</div>
<!-- /wp:terms-query -->
