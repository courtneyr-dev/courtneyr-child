<?php
/**
 * Title: Merge Conflicts publication
 * Slug: courtneyr-child/cr-series-mc-publication
 * Categories: cr-zine
 * Description: Archive-only publication layout, with CMS seasons and published-only reading links.
 * Inserter: false
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$courtneyr_child_mc_art    = COURTNEYR_CHILD_URI . '/assets/img/';
$courtneyr_child_mc_thesis = wp_kses(
	__( '<strong>Who decides</strong> in an open source project,<br><strong>how they decide</strong>,<br>and <strong>who can review</strong> the decision.', 'courtneyr-child' ),
	array(
		'strong' => array(),
		'br'     => array(),
	)
);
?>
<!-- wp:group {"align":"wide","className":"cr-mc__hero","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-mc__hero">
	<!-- wp:group {"className":"cr-mc__masthead","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-mc__masthead">
		<!-- wp:paragraph {"className":"cr-mc__eyebrow"} -->
		<p class="cr-mc__eyebrow"><?php esc_html_e( 'Series', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"cr-archive__title cr-mc__title"} /-->
		<!-- wp:paragraph {"className":"cr-mc__fieldguide"} -->
		<p class="cr-mc__fieldguide"><?php esc_html_e( 'A field guide to decisions', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:image {"className":"cr-mc__hero-art"} -->
	<figure class="wp-block-image cr-mc__hero-art"><img src="<?php echo esc_url( $courtneyr_child_mc_art . 'mc-decision-collage.webp' ); ?>" alt=""/></figure>
	<!-- /wp:image -->
	<!-- wp:group {"className":"cr-mc__thesis","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-mc__thesis">
		<!-- wp:paragraph -->
		<p><?php echo $courtneyr_child_mc_thesis; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passed through wp_kses above. ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:image {"className":"cr-mc__coda"} -->
		<figure class="wp-block-image cr-mc__coda"><img src="<?php echo esc_url( $courtneyr_child_mc_art . 'mc-concert-patch.webp' ); ?>" alt="<?php esc_attr_e( 'Twelve projects. The same questions.', 'courtneyr-child' ); ?>"/></figure>
		<!-- /wp:image -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"cr-mc__annotation-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-mc__annotation-wrap">
	<!-- wp:paragraph {"className":"cr-mc__annotation"} -->
	<p class="cr-mc__annotation"><?php esc_html_e( 'Open source governance, examined through the record.', 'courtneyr-child' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"aside","align":"wide","className":"cr-mc__methods","ariaLabel":"Methods and sources","layout":{"type":"default"}} -->
<aside class="wp-block-group alignwide cr-mc__methods" aria-label="<?php esc_attr_e( 'Methods and sources', 'courtneyr-child' ); ?>">
	<!-- wp:image {"className":"cr-mc__notebook"} -->
	<figure class="wp-block-image cr-mc__notebook"><img src="<?php echo esc_url( $courtneyr_child_mc_art . 'mc-methods-notebook.webp' ); ?>" alt=""/></figure>
	<!-- /wp:image -->
	<!-- wp:group {"className":"cr-mc__methods-copy","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-mc__methods-copy">
		<!-- wp:paragraph {"className":"cr-mc__eyebrow"} -->
		<p class="cr-mc__eyebrow"><?php esc_html_e( 'How this series was researched', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'The Methods & Sources', 'courtneyr-child' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'How the twelve projects are selected, what counts as evidence, how claims are labeled, and the fairness and correction process that keeps the work accurate.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"cr-mc__methods-action","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-mc__methods-action">
		<!-- wp:paragraph {"className":"cr-series-methods__link"} -->
		<p class="cr-series-methods__link"><a href="/merge-conflicts-methods/"><?php esc_html_e( 'Read the research methods', 'courtneyr-child' ); ?></a></p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"className":"cr-mc__method-note"} -->
		<p class="cr-mc__method-note"><?php esc_html_e( 'Project selection, evidence categories, fairness rules, and how corrections are recorded.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</aside>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"cr-mc__layout","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-mc__layout">
	<!-- wp:group {"tagName":"section","className":"cr-mc__investigation","layout":{"type":"default"}} -->
	<section class="wp-block-group cr-mc__investigation">
		<!-- wp:paragraph {"className":"cr-mc__eyebrow"} -->
		<p class="cr-mc__eyebrow"><?php esc_html_e( 'The investigation', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'Eight seasons. One recurring question.', 'courtneyr-child' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:courtneyr/series-guide {"view":"publication-contents"} /-->
	</section>
	<!-- /wp:group -->
	<!-- wp:group {"tagName":"aside","className":"cr-mc__sidebar","ariaLabel":"Reading and companion research","layout":{"type":"default"}} -->
	<aside class="wp-block-group cr-mc__sidebar" aria-label="<?php esc_attr_e( 'Reading and companion research', 'courtneyr-child' ); ?>">
		<!-- wp:group {"tagName":"section","className":"cr-mc__desk","layout":{"type":"default"}} -->
		<section class="wp-block-group cr-mc__desk">
			<!-- wp:paragraph {"className":"cr-mc__eyebrow"} -->
			<p class="cr-mc__eyebrow"><?php esc_html_e( 'The reading desk', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:image {"className":"cr-mc__megaphone"} -->
			<figure class="wp-block-image cr-mc__megaphone"><img src="<?php echo esc_url( $courtneyr_child_mc_art . 'mc-megaphone.webp' ); ?>" alt=""/></figure>
			<!-- /wp:image -->
			<!-- wp:courtneyr/series-guide {"view":"publication-desk"} /-->
		</section>
		<!-- /wp:group -->
		<!-- wp:group {"tagName":"section","className":"cr-mc__companion","layout":{"type":"default"}} -->
		<section class="wp-block-group cr-mc__companion">
			<!-- wp:paragraph {"className":"cr-mc__eyebrow"} -->
			<p class="cr-mc__eyebrow"><?php esc_html_e( 'Alongside the main series', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:image {"className":"cr-mc__books"} -->
			<figure class="wp-block-image cr-mc__books"><img src="<?php echo esc_url( $courtneyr_child_mc_art . 'mc-book-stack.webp' ); ?>" alt=""/></figure>
			<!-- /wp:image -->
			<!-- wp:heading -->
			<h2 class="wp-block-heading"><?php esc_html_e( 'Companion research', 'courtneyr-child' ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:courtneyr/series-guide {"view":"publication-companion"} /-->
		</section>
		<!-- /wp:group -->
	</aside>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"cr-mc__parts","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-mc__parts">
	<!-- wp:courtneyr/series-guide {"view":"publication-parts"} /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"cr-mc__follow","layout":{"type":"default"}} -->
<section class="wp-block-group alignfull cr-mc__follow">
	<!-- wp:image {"className":"cr-mc__signpost"} -->
	<figure class="wp-block-image cr-mc__signpost"><img src="<?php echo esc_url( $courtneyr_child_mc_art . 'mc-follow-signpost.webp' ); ?>" alt=""/></figure>
	<!-- /wp:image -->
	<!-- wp:courtneyr/series-guide {"view":"follow"} /-->
</section>
<!-- /wp:group -->
