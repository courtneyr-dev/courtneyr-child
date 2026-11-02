<?php
/**
 * Title: Series archive header
 * Slug: courtneyr-child/cr-series-header
 * Categories: cr-zine, cr-loops
 * Description: Series archive header: kicker, cut-paper title, the series description and the published-parts status line from courtneyr/series-guide. Used by the taxonomy-series templates.
 * Keywords: series, archive, header, merge conflicts
 * Block Types: core/query-title
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Series Header"},"align":"wide","className":"cr-archive__header cr-series-header","style":{"spacing":{"blockGap":"var:preset|spacing|small","padding":{"bottom":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide cr-archive__header cr-series-header" style="padding-bottom:var(--wp--preset--spacing--medium)">

	<!-- wp:courtneyr/archive-identity /-->

	<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"cr-flank cr-flank--blue cr-archive__title","style":{"typography":{"letterSpacing":"-1px","lineHeight":"1.05"}},"fontSize":"x-large","fontFamily":"accent"} /-->

	<!-- wp:term-description {"className":"cr-archive__description","style":{"typography":{"lineHeight":"1.5"},"elements":{"link":{"color":{"text":"var:preset|color|primary"}}}},"fontSize":"md"} /-->

	<!-- wp:courtneyr/series-guide {"view":"status"} /-->

</div>
<!-- /wp:group -->
