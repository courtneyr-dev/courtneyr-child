<?php
/**
 * Title: Stream Item
 * Slug: courtneyr-child/cr-stream-item
 * Categories: cr-zine
 * Description: Single mixed-feed entry — typographic glyph avatar, post-type chip, date, title, and intro. Use to compose a hand-built stream. (For an automated stream, see Phase E Query Loop variation in v0.4.)
 * Keywords: stream, feed, item, post, indieweb, zine
 * Viewport Width: 720
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"tagName":"article","className":"cr-stream-item cr-stream-item--blog","layout":{"type":"constrained"}} -->
<article class="wp-block-group cr-stream-item cr-stream-item--blog">

	<!-- wp:html -->
	<p class="cr-stream-item__avatar" aria-hidden="true">✦</p>
	<!-- /wp:html -->

	<!-- wp:group {"className":"cr-stream-item__body","layout":{"type":"constrained"}} -->
	<div class="wp-block-group cr-stream-item__body">

		<!-- wp:paragraph {"className":"cr-stream-item__meta"} -->
		<p class="cr-stream-item__meta">April 15 · <span class="cr-chip cr-chip--blog">Blog</span></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":3,"className":"cr-stream-item__title"} -->
		<h3 class="wp-block-heading cr-stream-item__title"><a href="#">LLM Wiki: From Brain Fog to AI-Organized Second Brain Clarity</a></h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p>The LLM Wiki pattern changed how I think about notes, documents, and AI — four frameworks finally talking to each other.</p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

</article>
<!-- /wp:group -->
