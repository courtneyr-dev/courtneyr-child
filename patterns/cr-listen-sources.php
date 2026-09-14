<?php
/**
 * Title: Listen sources
 * Slug: courtneyr-child/cr-listen-sources
 * Categories: cr-indieweb
 * Description: Rating and listen link for a listen post, read from Post Kinds through the post-kinds/kind-meta bindings source (keys rating and url). Rows with no stored value stay hidden.
 * Inserter: yes
 *
 * @package CourtneyrChild
 */

?>
<!-- wp:group {"metadata":{"name":"Listen sources"},"className":"cr-listen-sources","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group cr-listen-sources"><!-- wp:paragraph {"className":"cr-listen-sources__label"} -->
<p class="cr-listen-sources__label"><?php esc_html_e( 'Listen / find it', 'courtneyr-child' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"Rating"},"className":"cr-listen-sources__rating cr-inline-run","layout":{"type":"default"}} -->
<div class="wp-block-group cr-listen-sources__rating cr-inline-run"><!-- wp:paragraph -->
<p><?php esc_html_e( 'Rated', 'courtneyr-child' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"post-kinds/kind-meta","args":{"key":"rating"}}}},"className":"cr-listen-sources__value"} -->
<p class="cr-listen-sources__value"></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'of 5', 'courtneyr-child' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"cr-listen-sources__links"} -->
<div class="wp-block-buttons cr-listen-sources__links"><!-- wp:button {"className":"is-style-cr-button-outline cr-listen-sources__link","metadata":{"bindings":{"url":{"source":"post-kinds/kind-meta","args":{"key":"url"}}}}} -->
<div class="wp-block-button is-style-cr-button-outline cr-listen-sources__link"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Listen', 'courtneyr-child' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
