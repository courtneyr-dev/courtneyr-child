<?php
/**
 * Title: Hero — Section
 * Slug: courtneyr-child/cr-hero-section
 * Categories: cr-zine
 * Description: Shorter hero for index-page section headers, archive lists, and tag landings. Uses Roboto Slab 800 with marker-color accent on the title — calmer than the full display variant.
 * Keywords: hero, section, archive, index, accent
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
		<p class="cr-eyebrow">Speaking</p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":2,"className":"cr-hero__title cr-hero__title--accent"} -->
		<h2 class="wp-block-heading cr-hero__title cr-hero__title--accent">Audiences of all sizes</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"cr-hero__lead"} -->
		<p class="cr-hero__lead">Courtney's ability to communicate complex technical information in a way that everyone can understand has made her a sought-after speaker for conferences, online training, and classrooms.</p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

</section>
<!-- /wp:group -->
