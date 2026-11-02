<?php
/**
 * Title: Merge Conflicts masthead
 * Slug: courtneyr-child/cr-series-mc-masthead
 * Categories: cr-zine
 * Description: The Merge Conflicts landing's masthead: kicker, cut-paper title, the thesis set as a statement with its three questions in bold, the way in from courtneyr/series-guide (start), and the series image from PublishPress Series' featured-image field (cover), taped under the title. The thesis mirrors the Merge Conflicts series description (term 1175); change both together.
 * Keywords: series, masthead, merge conflicts, thesis
 * Block Types: core/query-title
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$courtneyr_child_series_thesis = wp_kses(
	__( '<strong>Who decides</strong> in an open source project,<br><strong>how they decide</strong>,<br>and <strong>who can review</strong> the decision.', 'courtneyr-child' ),
	array(
		'strong' => array(),
		'br'     => array(),
	)
);
?>
<!-- wp:group {"metadata":{"name":"Masthead"},"align":"wide","className":"cr-archive__header cr-series-header cr-series-masthead","style":{"spacing":{"blockGap":"var:preset|spacing|small","padding":{"bottom":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide cr-archive__header cr-series-header cr-series-masthead" style="padding-bottom:var(--wp--preset--spacing--medium)">

	<!-- wp:courtneyr/archive-identity /-->

	<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"cr-flank cr-flank--blue cr-archive__title","style":{"typography":{"letterSpacing":"-1px","lineHeight":"1.05"}},"fontSize":"x-large","fontFamily":"accent"} /-->

	<!-- wp:group {"className":"cr-series-thesis","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-series-thesis">

		<!-- wp:paragraph {"className":"cr-series-thesis__text"} -->
		<p class="cr-series-thesis__text"><?php echo $courtneyr_child_series_thesis; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passed through wp_kses above. ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"cr-series-thesis__coda"} -->
		<p class="cr-series-thesis__coda"><?php esc_html_e( 'Twelve projects, the same questions.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:courtneyr/series-guide {"view":"start"} /-->

	</div>
	<!-- /wp:group -->

	<!-- wp:courtneyr/series-guide {"view":"cover"} /-->

</div>
<!-- /wp:group -->
