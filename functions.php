<?php
/**
 * Courtneyr Child theme entry point.
 *
 * This file is structural only. All runtime behavior lives in inc/*.php
 * so each concern is isolated and easy to disable in a hotfix.
 *
 * Load order matters: theme-supports first (so the editor knows what we
 * want), then enqueue (so block-scoped CSS is registered before blocks
 * render), then block-styles (variations registered before patterns
 * reference them), then patterns and interactivity last.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COURTNEYR_CHILD_VERSION', '0.5.23' );
define( 'COURTNEYR_CHILD_DIR', __DIR__ );
define( 'COURTNEYR_CHILD_URI', get_stylesheet_directory_uri() );

require_once __DIR__ . '/inc/theme-supports.php';
require_once __DIR__ . '/inc/enqueue.php';
require_once __DIR__ . '/inc/block-styles.php';
require_once __DIR__ . '/inc/block-patterns.php';
require_once __DIR__ . '/inc/interactivity.php';
require_once __DIR__ . '/inc/theme-json-overrides.php';
require_once __DIR__ . '/inc/social-services.php';
