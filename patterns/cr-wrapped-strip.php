<?php
/**
 * Title: Wrapped archive strip
 * Slug: courtneyr-child/cr-wrapped-strip
 * Categories: cr-marks
 * Description: Editable Wrapped archive strip with a bound title link and count line.
 * Inserter: yes
 *
 * @package CourtneyrChild
 */

?>
<!-- wp:post-kinds-indieweb/wrapped-strip -->
<!-- wp:group {"className":"cr-wrapped-strip","layout":{"type":"default"}} -->
<div class="wp-block-group cr-wrapped-strip"><!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"metadata":{"bindings":{"text":{"source":"post-kinds/wrapped","args":{"key":"title"}},"url":{"source":"post-kinds/wrapped","args":{"key":"url"}}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button"></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"post-kinds/wrapped","args":{"key":"count_line"}}}},"className":"pkiw-wrapped-strip__count"} -->
<p class="pkiw-wrapped-strip__count"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:post-kinds-indieweb/wrapped-strip -->
