<?php
/**
 * `php tests/php/wrapped-guard-test.php`: missing-plugin facts guard.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/wrapped-bootstrap.php';
require CR_WRAPPED_THEME_DIR . '/inc/wrapped.php';

cr_wrapped_check( array() === \Courtneyr\Child\Wrapped\facts( 12 ), 'facts are empty when PKIW wrapped_facts is unavailable' );
cr_wrapped_finish();
