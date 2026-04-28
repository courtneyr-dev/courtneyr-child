<?php
/**
 * Title: Hero — Photo
 * Slug: courtneyr-child/cr-hero-photo
 * Categories: cr-zine
 * Description: Two-column hero with portrait cover image on the left and brand-voice headline + lead on the right. Adapted from the live home page. Swap the image after inserting.
 * Keywords: hero, photo, cover, image, courtney, home
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

		<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|xl"}}}} -->
		<div class="wp-block-columns alignwide">

			<!-- wp:column {"verticalAlignment":"center"} -->
			<div class="wp-block-column is-vertically-aligned-center">

				<!-- wp:cover {"url":"/wp-content/uploads/2023/05/cropped-courtney-pirate-jpeg.avif","dimRatio":0,"isUserOverlayColor":true,"minHeight":100,"minHeightUnit":"%","contentPosition":"bottom center","sizeSlug":"full","style":{"border":{"radius":"10px"},"spacing":{"padding":{"top":"var:preset|spacing|md","bottom":"var:preset|spacing|md","left":"var:preset|spacing|md","right":"var:preset|spacing|md"}}},"layout":{"type":"constrained"}} -->
				<div class="wp-block-cover has-custom-content-position is-position-bottom-center" style="border-radius:10px;padding-top:var(--wp--preset--spacing--md);padding-right:var(--wp--preset--spacing--md);padding-bottom:var(--wp--preset--spacing--md);padding-left:var(--wp--preset--spacing--md);min-height:100%">
					<img class="wp-block-cover__image-background" alt="Courtney Robertson, an open source developer relations advocate, wearing a pirate hat, a DSLR camera, and a purple shirt" src="/wp-content/uploads/2023/05/cropped-courtney-pirate-jpeg.avif" data-object-fit="cover"/>
					<span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span>
					<div class="wp-block-cover__inner-container">

						<!-- wp:paragraph {"align":"center","fontSize":"sm"} -->
						<p class="has-text-align-center has-sm-font-size"><strong>Open Source Developer Advocate · Educator · WordPress Contributor</strong></p>
						<!-- /wp:paragraph -->

					</div>
				</div>
				<!-- /wp:cover -->

			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"center"} -->
			<div class="wp-block-column is-vertically-aligned-center">

				<!-- wp:paragraph {"className":"cr-eyebrow"} -->
				<p class="cr-eyebrow">Welcome</p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":1,"className":"cr-hero__display"} -->
				<h1 class="wp-block-heading cr-hero__display">Are you passionate about</h1>
				<!-- /wp:heading -->

				<!-- wp:paragraph -->
				<p><strong>Open source, WordPress, teaching web development, community management, and developer relations?</strong></p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"cr-hero__lead"} -->
				<p class="cr-hero__lead">Join my newsletter for clear, practical insights from a developer advocate, professional educator, and WordPress contributor — delivered straight to your inbox with no fluff.</p>
				<!-- /wp:paragraph -->

				<!-- wp:buttons -->
				<div class="wp-block-buttons">

					<!-- wp:button {"className":"is-style-cr-cta"} -->
					<div class="wp-block-button is-style-cr-cta"><a class="wp-block-button__link wp-element-button" href="/newsletter/">Subscribe →</a></div>
					<!-- /wp:button -->

					<!-- wp:button {"className":"is-style-cr-button-outline"} -->
					<div class="wp-block-button is-style-cr-button-outline"><a class="wp-block-button__link wp-element-button" href="/about/">About me</a></div>
					<!-- /wp:button -->

				</div>
				<!-- /wp:buttons -->

			</div>
			<!-- /wp:column -->

		</div>
		<!-- /wp:columns -->

	</div>
	<!-- /wp:group -->

</section>
<!-- /wp:group -->
