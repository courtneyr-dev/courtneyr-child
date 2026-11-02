<?php
/**
 * Title: Series contents
 * Slug: courtneyr-child/cr-series-contents
 * Categories: cr-zine
 * Description: How Merge Conflicts is organized: the planned scale, the comparison set, the companion series, then the contents list from courtneyr/series-guide (contents), which lists only seasons and arcs that exist as series terms, with publication-safe counts. The introduction is a plan from the series outline and the methods page draft; it's skipped from page 2 on.
 * Keywords: series, contents, seasons, companion, merge conflicts
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"Contents"},"align":"wide","className":"cr-series-contents-section","layout":{"type":"default"}} -->
<section class="wp-block-group alignwide cr-series-contents-section">

	<!-- wp:heading {"className":"cr-series-contents-section__title","fontFamily":"accent"} -->
	<h2 class="wp-block-heading cr-series-contents-section__title has-accent-font-family"><?php esc_html_e( 'How the series is organized', 'courtneyr-child' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"cr-series-intro","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-series-intro">

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Merge Conflicts is planned as 100 parts in eight seasons. Parts are numbered in reading order within each season, and only published parts appear and link.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Season 2 asks the same governance questions of twelve projects, in alphabetical order: Backdrop, the CSS and HTML standards bodies, Drupal, Ghost, Joomla, MariaDB, MySQL, Node.js, OpenJS, PHP, TYPO3 and WordPress. Season 3 reads WordPress’s record through dated documents.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'A companion series of first-person pieces runs beside the seasons, with its own numbering.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

	<!-- wp:courtneyr/series-guide {"view":"contents"} /-->

</section>
<!-- /wp:group -->
