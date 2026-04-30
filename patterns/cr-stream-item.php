<?php
/**
 * Title: Stream Item
 * Slug: courtneyr-child/cr-stream-item
 * Categories: cr-zine
 * Description: Single mixed-feed entry — SVG post-type avatar, chip, date, title, and intro. Mirrors the design-system stream component 1:1.
 * Keywords: stream, feed, item, post, indieweb, zine
 * Viewport Width: 720
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"tagName":"article","className":"cr-stream-item cr-stream-item--blog","layout":{"type":"constrained"}} -->
<article class="wp-block-group cr-stream-item cr-stream-item--blog">

	<!-- v0.5.51: SVG avatar via icons.svg sprite. icons-inject.js
	     inlines the sprite into <body> and rewrites use-href refs so
	     this resolves cross-origin. Switch type by changing both the
	     avatar modifier (cr-icon-avatar--blog) and the use-href fragment
	     (#post-icon-blog) to one of: blog, video, audio, link, bookmark,
	     quote, speaking, like, reply, review (solid). For aside, image,
	     gallery, status, chat, repost, book, event use cr-icon-avatar--outline
	     plus style="--type-color: var(--cr-type-chat);" since those types
	     fail AA as a fill. -->
	<!-- wp:html -->
	<span class="cr-icon-avatar cr-icon-avatar--blog" aria-hidden="true">
		<svg viewBox="0 0 24 24"><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/svg/icons.svg#post-icon-blog"></use></svg>
	</span>
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
