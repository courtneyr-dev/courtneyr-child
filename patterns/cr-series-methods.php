<?php
/**
 * Title: Series methods and sources
 * Slug: courtneyr-child/cr-series-methods
 * Categories: cr-zine
 * Description: The Merge Conflicts research file before the parts: why the methods matter, what the methods page holds, and the way to it. The link paragraph (class cr-series-methods__link) renders only while its page is published; until then it shows a "Not published yet" stamp (inc/series.php). Copy follows the methods page draft (page 40270). Skipped from page 2 on.
 * Keywords: series, methods, sources, research, evidence labels
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"tagName":"aside","metadata":{"name":"Methods and Sources"},"align":"wide","className":"cr-series-methods","ariaLabel":"<?php echo esc_attr__( 'Methods and sources', 'courtneyr-child' ); ?>","layout":{"type":"default"}} -->
<aside class="wp-block-group alignwide cr-series-methods" aria-label="<?php echo esc_attr__( 'Methods and sources', 'courtneyr-child' ); ?>">

	<!-- wp:group {"className":"cr-series-methods__head","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-series-methods__head">

		<!-- wp:paragraph {"className":"cr-tape cr-series-methods__tape"} -->
		<p class="cr-tape cr-series-methods__tape"><?php esc_html_e( 'Methods & Sources', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"className":"cr-series-methods__title","fontFamily":"accent"} -->
		<h2 class="wp-block-heading cr-series-methods__title has-accent-font-family"><?php esc_html_e( 'How does Merge Conflicts label evidence, stay fair and fix mistakes?', 'courtneyr-child' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"cr-series-methods__why"} -->
		<p class="cr-series-methods__why"><?php esc_html_e( 'Every post in the series links to it. When a count or a claim changes, its log says what changed and when.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"cr-series-methods__body","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-series-methods__body">

		<!-- wp:paragraph {"className":"cr-series-methods__inside"} -->
		<p class="cr-series-methods__inside"><?php esc_html_e( 'What it holds', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:list {"className":"cr-series-methods__list"} -->
		<ul class="wp-block-list cr-series-methods__list">
			<!-- wp:list-item -->
			<li><?php esc_html_e( 'What the series compares, and what it claims', 'courtneyr-child' ); ?></li>
			<!-- /wp:list-item -->
			<!-- wp:list-item -->
			<li><?php esc_html_e( 'The six evidence labels every claim carries', 'courtneyr-child' ); ?></li>
			<!-- /wp:list-item -->
			<!-- wp:list-item -->
			<li><?php esc_html_e( 'The twelve fairness rules every part passes', 'courtneyr-child' ); ?></li>
			<!-- /wp:list-item -->
			<!-- wp:list-item -->
			<li><?php esc_html_e( 'Right of reply and the corrections log', 'courtneyr-child' ); ?></li>
			<!-- /wp:list-item -->
			<!-- wp:list-item -->
			<li><?php esc_html_e( 'Who’s writing, and every relationship that bears on it', 'courtneyr-child' ); ?></li>
			<!-- /wp:list-item -->
		</ul>
		<!-- /wp:list -->

		<!-- wp:paragraph {"className":"cr-series-methods__link"} -->
		<p class="cr-series-methods__link"><a href="/merge-conflicts-methods/"><?php esc_html_e( 'Read the methods', 'courtneyr-child' ); ?></a></p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

</aside>
<!-- /wp:group -->
