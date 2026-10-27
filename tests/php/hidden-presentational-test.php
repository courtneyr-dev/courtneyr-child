<?php
/**
 * `php tests/php/hidden-presentational-test.php`: aria-hidden ornaments carry role="presentation".
 *
 * Accessibility Checker Pro's aria_hidden_valid_usage flags each aria-hidden
 * element without role="presentation" on its own. Covers the role being added
 * to hidden elements (nested icon SVGs included), left alone where a role is
 * already set or the element is an <img>, and the outer-block hooks it runs on.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_hp_filters'] = array();

	/** Records hooks instead of running them. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_hp_filters'][] = array( $hook, $callback, $priority );
	}

	/** The HTML API filters attribute values through kses; pass them through. */
	function apply_filters( $hook, $value ) {
		return $value;
	}

	/** Same recorder for actions. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		echo "SKIP: set CR_WP_CORE to a WordPress checkout\n";
		exit( 0 );
	}
	require_once $core . '/wp-includes/compat.php';
	require_once $core . '/wp-includes/utf8.php';
	require_once $core . '/wp-includes/kses.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
	require dirname( __DIR__, 2 ) . '/inc/a11y-output.php';

	$failures = array();

	/** Print one named check. */
	function check_case( string $name, callable $test ): void {
		global $failures;
		try {
			$ok = (bool) $test();
		} catch ( Throwable $error ) {
			$ok   = false;
			$name = $name . ' (' . $error->getMessage() . ')';
		}
		echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
		if ( ! $ok ) {
			$failures[] = $name;
		}
	}

	$run = 'Courtneyr\\Child\\A11yOutput\\hidden_is_presentational';

	check_case(
		'a hidden span and the icon SVG inside it both get role="presentation"',
		static function () use ( $run ): bool {
			$out  = $run( '<span class="cr-bookmark-stream__glyph" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M0 0"/></svg></span>' );
			$tags = new WP_HTML_Tag_Processor( $out );
			$seen = array();
			while ( $tags->next_tag() ) {
				$seen[] = $tags->get_tag() . ':' . $tags->get_attribute( 'aria-hidden' ) . ':' . $tags->get_attribute( 'role' );
			}
			return array( 'SPAN:true:presentation', 'SVG:true:presentation', 'PATH::' ) === $seen;
		}
	);

	check_case(
		'an existing role is kept, not doubled',
		static function () use ( $run ): bool {
			$in = '<span class="cr-archive-identity__glyph" aria-hidden="true" role="presentation">x</span>';
			return $in === $run( $in );
		}
	);

	check_case(
		'an aria-hidden <img> takes no role',
		static function () use ( $run ): bool {
			$in = '<img src="a.jpg" alt="" aria-hidden="true">';
			return $in === $run( $in );
		}
	);

	check_case(
		'visible elements and aria-hidden="false" are untouched',
		static function () use ( $run ): bool {
			$in = '<p class="pk-title"><a href="/x/">X</a></p><span aria-hidden="false">y</span>';
			return $in === $run( $in );
		}
	);

	check_case(
		'markup with no aria-hidden comes back byte for byte',
		static function () use ( $run ): bool {
			$in = "<div class=\"pk-card\">\n\t<p>Plain</p>\n</div>";
			return $in === $run( $in );
		}
	);

	check_case(
		'runs at 999 on post-content, post-template, the stream card and the archive identity',
		static function () use ( $run ): bool {
			$hooks = array();
			foreach ( $GLOBALS['cr_hp_filters'] as $filter ) {
				if ( $run === $filter[1] ) {
					$hooks[ $filter[0] ] = $filter[2];
				}
			}
			ksort( $hooks );
			return array(
				'render_block_core/post-content'               => 999,
				'render_block_core/post-template'              => 999,
				'render_block_courtneyr/archive-identity'      => 999,
				'render_block_post-kinds-indieweb/stream-card' => 999,
			) === $hooks;
		}
	);

	if ( $failures ) {
		echo count( $failures ) . " failed\n";
		exit( 1 );
	}
	echo "all passed\n";
}
