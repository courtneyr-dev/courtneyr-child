<?php
/**
 * Title: Quote Post (IndieWeb)
 * Slug: courtneyr-child/post-format-quote
 * Categories: cr-post-format, cr-indieweb
 * Description: A quote-format post with a torn-paper pull quote, citation, and source link.
 * Keywords: quote, indieweb, h-cite, post format
 * Inserter: yes
 *
 * Pattern overrides supported (WP 6.6+): the quote text and citation
 * use metadata.bindings so the same pattern can carry different content
 * across posts.
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"className":"cr-post-quote","style":{"spacing":{"padding":{"top":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group cr-post-quote" style="padding-top:var(--wp--preset--spacing--xl);padding-bottom:var(--wp--preset--spacing--xl)">

	<!-- wp:paragraph {"className":"is-style-cr-eyebrow","fontSize":"sm"} -->
	<p class="is-style-cr-eyebrow has-sm-font-size">Quoted</p>
	<!-- /wp:paragraph -->

	<!-- wp:quote {"className":"is-style-cr-torn-paper","metadata":{"name":"quote-body","bindings":{"content":{"source":"core/post-meta","args":{"key":"quote_body"}}}}} -->
	<blockquote class="wp-block-quote is-style-cr-torn-paper">
		<!-- wp:paragraph -->
		<p>Replace this with the quote you are sharing.</p>
		<!-- /wp:paragraph -->

		<cite>
			<!-- wp:paragraph {"metadata":{"name":"quote-source","bindings":{"content":{"source":"core/post-meta","args":{"key":"quote_source"}}}}} -->
			<p>Source name</p>
			<!-- /wp:paragraph -->
		</cite>
	</blockquote>
	<!-- /wp:quote -->

	<!-- wp:paragraph {"className":"cr-cite-link","fontSize":"sm","style":{"color":{"text":"var:preset|color|cerulean"}}} -->
	<p class="cr-cite-link has-sm-font-size has-cerulean-color has-text-color">
		<a href="#">Read the original →</a>
	</p>
	<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
