<?php
/**
 * XFN paint and build guard contracts (acceptance items 19 and 20).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/follow-bootstrap.php';
	require CR_FOLLOW_THEME_DIR . '/inc/stamps.php';
	require CR_FOLLOW_THEME_DIR . '/inc/follow.php';

	use Courtneyr\Child\Follow;

	cr_follow_check(
		'XFN CSS is attached only as block styles to blogroll and badge',
		static function (): void {
			$GLOBALS['cr_follow_enqueued'] = array();
			Follow\register_block_styles();
			$xfn = array_values(
				array_filter(
					$GLOBALS['cr_follow_enqueued'],
					static fn( array $row ): bool => str_starts_with( $row[0], 'xfn/' )
				)
			);
			cr_follow_same( array( 'xfn/blogroll', 'xfn/relationship-badge' ), array_column( $xfn, 0 ), 'only two XFN block targets' );
			foreach ( $xfn as $row ) {
				cr_follow_same( 'courtneyr-xfn', $row[1]['handle'] ?? '', 'XFN handle' );
				cr_follow_contains( '/assets/css/cr-xfn.css', $row[1]['src'] ?? '', 'XFN source' );
				cr_follow_assert( ! array_key_exists( 'path', $row[1] ), 'no path key' );
			}
			cr_follow_same( 1, count( $GLOBALS['cr_follow_styles'] ), 'theme registers only its follow-entry style' );
		}
	);

	cr_follow_check(
		'no follow surface places an XFN block',
		static function (): void {
			foreach ( array( 'templates/taxonomy-kind-follow.html', 'patterns/cr-follow-rolodex-loop.php', 'inc/follow.php', 'inc/single-follow.php', 'inc/stream-follow.php' ) as $file ) {
				$code = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/' . $file );
				cr_follow_not_contains( 'wp:xfn/', $code, $file . ' XFN block' );
			}
		}
	);

	cr_follow_check(
		'CSS budgets and XFN style variants stay inside the frozen guardrails',
		static function (): void {
			$follow = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/assets/css/cr-follow.css' );
			$xfn    = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/assets/css/cr-xfn.css' );
			cr_follow_assert( strlen( $follow ) <= 30 * 1024, 'cr-follow.css <= 30 KiB, got ' . strlen( $follow ) );
			cr_follow_assert( strlen( $xfn ) <= 8 * 1024, 'cr-xfn.css <= 8 KiB, got ' . strlen( $xfn ) );
			foreach ( array( '.wp-block-xfn-blogroll', '.wp-block-xfn-relationship-badge', '.is-style-pills', '.is-style-plain' ) as $selector ) {
				cr_follow_contains( $selector, $xfn, 'XFN selector' );
			}
			cr_follow_contains( '@media (prefers-color-scheme: dark)', $xfn, 'system dark rules' );
			cr_follow_contains( ':root[data-theme="dark"]', $xfn, 'manual dark rules' );
			cr_follow_contains( '@media (forced-colors: active)', $xfn, 'forced colors' );
		}
	);

	cr_follow_check(
		'wheel is flat palette SVG without generated effects or text',
		static function (): void {
			$svg = (string) file_get_contents( CR_FOLLOW_THEME_DIR . '/assets/img/follow-wheel.svg' );
			foreach ( array( '<linearGradient', '<radialGradient', '<filter', '<text', 'blur' ) as $banned ) {
				cr_follow_not_contains( $banned, $svg, 'wheel effect' );
			}
			preg_match_all( '/#[0-9a-fA-F]{6}/', $svg, $matches );
			$palette = array( '#241c4a', '#bcb5e3', '#647baf', '#8ecae6', '#219ebc', '#126782', '#023047', '#ffb703', '#fb8500', '#fee2c3', '#fbfaf5', '#ebebeb' );
			foreach ( array_unique( $matches[0] ) as $color ) {
				cr_follow_assert( in_array( strtolower( $color ), $palette, true ), 'wheel palette color ' . $color );
			}
			cr_follow_assert( count( $matches[0] ) > 0, 'wheel has palette fills' );
		}
	);

	cr_follow_finish();
}
