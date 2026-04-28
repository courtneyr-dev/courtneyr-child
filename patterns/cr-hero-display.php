<?php
/**
 * Title: Hero — Display
 * Slug: courtneyr-child/cr-hero-display
 * Categories: cr-zine
 * Description: Full display hero with Rock Salt brand-voice heading, lead, and paired CTA + outline buttons. Use once per page — the brand voice does not scale.
 * Keywords: hero, display, brand, rock salt, courtney
 * Block Types: core/post-content
 * Viewport Width: 1280
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"tagName":"section","className":"cr-hero","align":"full","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull cr-hero">

	<!-- wp:group {"className":"cr-hero__inner","layout":{"type":"constrained"}} -->
	<div class="wp-block-group cr-hero__inner">

		<!-- wp:paragraph {"className":"cr-eyebrow"} -->
		<p class="cr-eyebrow">Welcome</p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1,"className":"cr-hero__display"} -->
		<h1 class="wp-block-heading cr-hero__display">Are you passionate about</h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"cr-hero__lead"} -->
		<p class="cr-hero__lead">Open source, WordPress, teaching web development, community management, and developer relations? Clear, practical insights from a developer advocate, professional educator, and WordPress contributor — no fluff.</p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">

			<!-- wp:button {"className":"is-style-cr-cta"} -->
			<div class="wp-block-button is-style-cr-cta"><a class="wp-block-button__link wp-element-button" href="/blog/">Read the latest →</a></div>
			<!-- /wp:button -->

			<!-- wp:button {"className":"is-style-cr-button-outline"} -->
			<div class="wp-block-button is-style-cr-button-outline"><a class="wp-block-button__link wp-element-button" href="/feed/">RSS feed</a></div>
			<!-- /wp:button -->

		</div>
		<!-- /wp:buttons -->

	</div>
	<!-- /wp:group -->

</section>
<!-- /wp:group -->
