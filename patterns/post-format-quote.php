<?php
/**
 * Title: Quote Post (IndieWeb)
 * Slug: courtneyr-child/post-format-quote
 * Categories: cr-post-format, cr-indieweb
 * Description: A quote-format post with a torn-paper pull quote, citation, and source link.
 * Keywords: quote, indieweb, h-cite, post format
 * Inserter: yes
 *
 * 0.7.46 (gap review G-11): the quote and citation are plain editable blocks.
 * The earlier core/post-meta bindings pointed at meta keys (quote_body,
 * quote_source) that nothing registers, so the bound blocks rendered empty
 * and could not be edited. The quote's source name is the <cite>; Post Formats
 * for Block Themes reads it as `quote_attribution` where a binding is wanted.
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"className":"cr-post-quote","style":{"spacing":{"padding":{"top":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group cr-post-quote" style="padding-top:var(--wp--preset--spacing--xl);padding-bottom:var(--wp--preset--spacing--xl)">

	<!-- wp:paragraph {"className":"is-style-cr-eyebrow","fontSize":"sm"} -->
	<p class="is-style-cr-eyebrow has-sm-font-size">Quoted</p>
	<!-- /wp:paragraph -->

	<!-- wp:quote {"className":"is-style-cr-torn-paper","metadata":{"name":"Quote"}} -->
	<blockquote class="wp-block-quote is-style-cr-torn-paper">
		<!-- wp:paragraph -->
		<p>Replace this with the quote you are sharing.</p>
		<!-- /wp:paragraph -->

		<cite>
			<!-- wp:paragraph {"metadata":{"name":"Source"}} -->
			<p>Source name</p>
			<!-- /wp:paragraph -->
		</cite>
	</blockquote>
	<!-- /wp:quote -->

	<!-- wp:paragraph {"className":"cr-cite-link","fontSize":"sm"} -->
	<p class="cr-cite-link has-sm-font-size">
		<a href="#">Read the original →</a>
	</p>
	<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
