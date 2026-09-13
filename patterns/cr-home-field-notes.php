<?php
/**
 * Title: Field Notes (Blog lane + Stream lane)
 * Slug: courtneyr-child/cr-home-field-notes
 * Categories: cr-zine, cr-sections
 * Description: Homepage Field Notes: masthead, a Blog lane (one featured post plus three supporting previews from the main surface) and a full-width Stream lane (six newest stream-surface entries rendered by the Post Kinds stream card). Insert inside the homepage's Field Notes section.
 * Keywords: home, field notes, blog, stream, query
 * Viewport Width: 1200
 * Block Types: core/post-content
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"templateLock":"all","lock":{"move":true,"remove":true},"metadata":{"name":"Field Notes"},"align":"wide","className":"cr-fieldnotes","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-fieldnotes">

	<!-- wp:group {"metadata":{"name":"Field Notes Masthead"},"className":"cr-fieldnotes__masthead","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group cr-fieldnotes__masthead">
		<!-- wp:heading {"level":2,"className":"cr-fieldnotes__title","fontFamily":"accent"} -->
		<h2 class="wp-block-heading cr-fieldnotes__title has-accent-font-family"><?php esc_html_e( 'Field notes', 'courtneyr-child' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"cr-hand cr-fieldnotes__hand"} -->
		<p class="cr-hand cr-fieldnotes__hand"><?php esc_html_e( 'Collected along the way', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","metadata":{"name":"Blog lane"},"className":"cr-fieldnotes__lane cr-fieldnotes__lane--blog","layout":{"type":"default"}} -->
	<section class="wp-block-group cr-fieldnotes__lane cr-fieldnotes__lane--blog">

		<!-- wp:group {"className":"cr-fieldnotes__lane-head","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group cr-fieldnotes__lane-head">
			<!-- wp:heading {"level":3,"className":"cr-fieldnotes__lane-title cr-fieldnotes__lane-title--blog","fontFamily":"accent"} -->
			<h3 class="wp-block-heading cr-fieldnotes__lane-title cr-fieldnotes__lane-title--blog has-accent-font-family"><?php esc_html_e( 'Blog', 'courtneyr-child' ); ?></h3>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"className":"cr-fieldnotes__caption"} -->
		<p class="cr-fieldnotes__caption"><?php esc_html_e( 'Longer reads on WordPress and open source.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:group {"metadata":{"name":"Blog row"},"className":"cr-fieldnotes__blog-row","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-fieldnotes__blog-row">

			<!-- wp:query {"queryId":91,"query":{"perPage":1,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"exclude","inherit":false,"pkiwSurface":"main"},"namespace":"courtneyr/home-blog","className":"cr-fieldnotes__lead-query"} -->
			<div class="wp-block-query cr-fieldnotes__lead-query">
				<!-- wp:post-template {"className":"cr-fieldnotes__lead-list"} -->
					<!-- wp:group {"tagName":"article","className":"cr-home-story cr-home-story--lead","layout":{"type":"default"}} -->
					<article class="wp-block-group cr-home-story cr-home-story--lead">
						<!-- wp:post-featured-image {"isLink":true,"className":"cr-home-story__image"} /-->

						<!-- wp:group {"className":"cr-home-story__title-row","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
						<div class="wp-block-group cr-home-story__title-row">
							<!-- wp:courtneyr/post-glyph /-->

							<!-- wp:post-title {"level":4,"isLink":true,"className":"cr-home-story__title p-name cr-u-url","fontFamily":"accent"} /-->
						</div>
						<!-- /wp:group -->

						<!-- wp:post-date {"className":"cr-home-story__date cr-dt-published screen-reader-text"} /-->

						<!-- wp:courtneyr/term-chips {"className":"cr-home-story__chips"} /-->

						<!-- wp:post-excerpt {"excerptLength":18,"showMoreOnNewLine":false,"className":"cr-home-story__excerpt"} /-->
					</article>
					<!-- /wp:group -->
				<!-- /wp:post-template -->

				<!-- wp:query-no-results -->
					<!-- wp:paragraph {"className":"cr-fieldnotes__empty"} -->
					<p class="cr-fieldnotes__empty"><?php esc_html_e( 'No blog posts yet.', 'courtneyr-child' ); ?></p>
					<!-- /wp:paragraph -->
				<!-- /wp:query-no-results -->
			</div>
			<!-- /wp:query -->

			<!-- wp:query {"queryId":92,"query":{"perPage":3,"pages":0,"offset":1,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"exclude","inherit":false,"pkiwSurface":"main"},"namespace":"courtneyr/home-blog","className":"cr-fieldnotes__support-query"} -->
			<div class="wp-block-query cr-fieldnotes__support-query">
				<!-- wp:post-template {"className":"cr-fieldnotes__support-list"} -->
					<!-- wp:group {"tagName":"article","className":"cr-home-story","layout":{"type":"default"}} -->
					<article class="wp-block-group cr-home-story">
						<!-- wp:group {"className":"cr-home-story__title-row","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
						<div class="wp-block-group cr-home-story__title-row">
							<!-- wp:courtneyr/post-glyph /-->

							<!-- wp:post-title {"level":4,"isLink":true,"className":"cr-home-story__title p-name cr-u-url","fontFamily":"accent"} /-->
						</div>
						<!-- /wp:group -->

						<!-- wp:post-date {"className":"cr-home-story__date cr-dt-published screen-reader-text"} /-->

						<!-- wp:courtneyr/term-chips {"className":"cr-home-story__chips"} /-->
					</article>
					<!-- /wp:group -->
				<!-- /wp:post-template -->
			</div>
			<!-- /wp:query -->

		</div>
		<!-- /wp:group -->

		<!-- wp:buttons {"className":"cr-fieldnotes__lane-link cr-fieldnotes__lane-link--blog"} -->
		<div class="wp-block-buttons cr-fieldnotes__lane-link cr-fieldnotes__lane-link--blog">
			<!-- wp:button {"className":"is-style-cr-cta"} -->
			<div class="wp-block-button is-style-cr-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>"><?php esc_html_e( 'All blog posts', 'courtneyr-child' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->

	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","metadata":{"name":"Stream lane"},"className":"cr-fieldnotes__lane cr-fieldnotes__lane--stream","layout":{"type":"default"}} -->
	<section class="wp-block-group cr-fieldnotes__lane cr-fieldnotes__lane--stream">

		<!-- wp:group {"className":"cr-fieldnotes__lane-head","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group cr-fieldnotes__lane-head">
			<!-- wp:heading {"level":3,"className":"cr-fieldnotes__lane-title cr-fieldnotes__lane-title--stream","fontFamily":"accent"} -->
			<h3 class="wp-block-heading cr-fieldnotes__lane-title cr-fieldnotes__lane-title--stream has-accent-font-family"><?php esc_html_e( 'Stream', 'courtneyr-child' ); ?></h3>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"className":"cr-fieldnotes__caption"} -->
		<p class="cr-fieldnotes__caption"><?php esc_html_e( 'Snapshots, reading, and everyday discoveries.', 'courtneyr-child' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:group {"metadata":{"name":"Stream objects"},"className":"cr-home-stream cr-stream-page","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-home-stream cr-stream-page">
			<!-- wp:query {"queryId":93,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"exclude","inherit":false,"pkiwSurface":"stream"},"namespace":"courtneyr/home-stream","className":"cr-home-stream__query"} -->
			<div class="wp-block-query cr-home-stream__query">
				<!-- wp:post-template {"className":"cr-home-stream__list"} -->
					<!-- wp:post-kinds-indieweb/stream-card /-->
				<!-- /wp:post-template -->

				<!-- wp:query-no-results -->
					<!-- wp:paragraph {"className":"cr-fieldnotes__empty"} -->
					<p class="cr-fieldnotes__empty"><?php esc_html_e( 'Nothing in the stream yet.', 'courtneyr-child' ); ?></p>
					<!-- /wp:paragraph -->
				<!-- /wp:query-no-results -->
			</div>
			<!-- /wp:query -->
		</div>
		<!-- /wp:group -->

		<!-- wp:buttons {"className":"cr-fieldnotes__lane-link cr-fieldnotes__lane-link--stream"} -->
		<div class="wp-block-buttons cr-fieldnotes__lane-link cr-fieldnotes__lane-link--stream">
			<!-- wp:button {"className":"is-style-cr-button-outline"} -->
			<div class="wp-block-button is-style-cr-button-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/stream/' ) ); ?>"><?php esc_html_e( 'All stream updates', 'courtneyr-child' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->

	</section>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
