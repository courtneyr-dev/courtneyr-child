<?php
/**
 * Title: Series methods and sources
 * Slug: courtneyr-child/cr-series-methods
 * Categories: cr-zine
 * Description: Sky-blue torn-paper band with a notebook drawing that sends readers to the Merge Conflicts methods page. The link paragraph (class cr-series-methods__link) renders only while its page is published; until then it says the page isn't up yet (inc/series.php).
 * Keywords: series, methods, sources, callout, research
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"tagName":"aside","metadata":{"name":"Methods and Sources"},"align":"wide","className":"cr-series-methods","ariaLabel":"<?php echo esc_attr__( 'How this series was researched', 'courtneyr-child' ); ?>","layout":{"type":"default"}} -->
<aside class="wp-block-group alignwide cr-series-methods" aria-label="<?php echo esc_attr__( 'How this series was researched', 'courtneyr-child' ); ?>">

	<!-- wp:courtneyr/series-guide {"view":"methods-art"} /-->

	<!-- wp:group {"className":"cr-series-methods__head","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-series-methods__head">

		<!-- wp:paragraph {"className":"is-style-cr-eyebrow cr-series-methods__eyebrow"} -->
		<p class="is-style-cr-eyebrow cr-series-methods__eyebrow"><?php esc_html_e( 'How this series was researched', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"className":"cr-series-methods__title","fontFamily":"accent"} -->
		<h2 class="wp-block-heading cr-series-methods__title has-accent-font-family"><?php esc_html_e( 'The Methods & Sources', 'courtneyr-child' ); ?></h2>
		<!-- /wp:heading -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"cr-series-methods__body","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-series-methods__body">

		<!-- wp:paragraph {"className":"cr-series-methods__desc"} -->
		<p class="cr-series-methods__desc"><?php esc_html_e( 'How I picked the twelve projects, which sources count as evidence, how each claim gets checked, and how to read the counts and labels you’ll see in every part.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"cr-series-methods__link"} -->
		<p class="cr-series-methods__link"><a href="/merge-conflicts-methods/"><?php esc_html_e( 'Read the research methods', 'courtneyr-child' ); ?></a></p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

</aside>
<!-- /wp:group -->
