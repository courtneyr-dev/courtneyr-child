<?php
/**
 * Stream surfaces load only the kind sheets their cards use.
 *
 * Each kind module enqueues its artifact sheet wherever a card of that kind
 * could appear, and the front page and /stream/ count as such surfaces, so
 * both pages loaded every kind's stylesheet (30 render-blocking files, about
 * 230 KB compressed, on 2026-10-19) while rendering cards of five kinds.
 * PageSpeed put mobile LCP at 5.7 s, 1.3 s of it render delay.
 *
 * In a block theme the template renders before wp_head, so by the time
 * styles are printed every card on the page has already been rendered. This
 * file records the classes of every rendered block, then removes a kind
 * sheet when none of its distinctive classes were rendered. A class is
 * distinctive when the shared sheets (components.css, cr-post-kinds.css)
 * don't use it and at most two kind sheets do. A sheet without distinctive
 * classes always stays. Nothing happens on a request that rendered no
 * template part (embeds, feeds, REST), or outside the Stream surfaces.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamStylePruning;

use function Courtneyr\Child\HomeSections\is_stream_surface;

/**
 * Classes seen in rendered block markup during this request.
 *
 * @param string|null $html Block output to record, or null to read the set.
 * @return array<string, true>
 */
function rendered_classes( ?string $html = null ): array {
	static $seen = array();
	if ( null !== $html && '' !== $html && preg_match_all( '/\bclass\s*=\s*(["\'])(.*?)\1/s', $html, $m ) ) {
		foreach ( $m[2] as $attr ) {
			foreach ( preg_split( '/\s+/', $attr, -1, PREG_SPLIT_NO_EMPTY ) as $class ) {
				$seen[ $class ] = true;
			}
		}
	}
	return $seen;
}

/**
 * Record each rendered block's classes on the front end.
 *
 * @param string $block_content Rendered block markup.
 * @return string Unchanged markup.
 */
function record( $block_content ) {
	if ( is_string( $block_content ) && ! is_admin() ) {
		rendered_classes( $block_content );
	}
	return $block_content;
}
add_filter( 'render_block', __NAMESPACE__ . '\\record', 9999 );

/**
 * Class tokens used in a stylesheet's selectors.
 *
 * @param string $file Absolute path of the stylesheet.
 * @return array<string, true>
 */
function sheet_classes( string $file ): array {
	$css = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$out = array();
	if ( preg_match_all( '/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', (string) preg_replace( '/\{[^{}]*\}/', '{}', $css ), $m ) ) {
		foreach ( $m[1] as $class ) {
			$out[ $class ] = true;
		}
	}
	return $out;
}

/**
 * True for classes every kind's markup carries: microformats (h-, p-, u-,
 * dt-, e-), core block and alignment classes, and state classes. They can't
 * tell one kind's card from another's.
 *
 * @param string $name Class name.
 * @return bool
 */
function is_generic( string $name ): bool {
	return 1 === preg_match( '/^(?:(?:h|p|u|dt|e)-|wp-|align|has-|is-|screen-reader)/', $name );
}

/**
 * Distinctive classes per prunable sheet handle, cached per theme version.
 *
 * @param array<string, string> $sheets Handle => absolute path.
 * @return array<string, array<int, string>>
 */
function distinctive_classes( array $sheets ): array {
	$key    = 'stream-style-pruning-' . COURTNEYR_CHILD_VERSION . '-' . md5( implode( ',', array_keys( $sheets ) ) );
	$cached = wp_cache_get( $key, 'courtneyr' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$shared = sheet_classes( COURTNEYR_CHILD_DIR . '/assets/css/components.css' )
		+ sheet_classes( COURTNEYR_CHILD_DIR . '/assets/css/cr-post-kinds.css' );
	$per    = array();
	$count  = array();
	foreach ( $sheets as $handle => $path ) {
		$per[ $handle ] = sheet_classes( $path );
		foreach ( array_keys( $per[ $handle ] ) as $class ) {
			$count[ $class ] = ( $count[ $class ] ?? 0 ) + 1;
		}
	}
	$out = array();
	foreach ( $per as $handle => $classes ) {
		$out[ $handle ] = array_values(
			array_filter(
				array_map( 'strval', array_keys( $classes ) ),
				static fn( string $c ): bool => ! isset( $shared[ $c ] ) && ( $count[ $c ] ?? 0 ) <= 2 && ! is_generic( $c )
			)
		);
	}
	wp_cache_set( $key, $out, 'courtneyr', DAY_IN_SECONDS );
	return $out;
}

/**
 * Theme kind sheets eligible for pruning: theme files under assets/css whose
 * name starts with cr-, minus the shared and page-level sheets.
 *
 * @return array<string, string> Handle => absolute path.
 */
function prunable_sheets(): array {
	$keep  = array( 'cr-post-kinds.css', 'cr-home-sections.css', 'cr-nav.css', 'cr-print.css', 'cr-paper.css', 'cr-ticket.css', 'cr-sticker.css', 'cr-calendar.css' );
	$base  = COURTNEYR_CHILD_URI . '/assets/css/';
	$out   = array();
	$queue = wp_styles()->queue;
	foreach ( $queue as $handle ) {
		$style = wp_styles()->registered[ $handle ] ?? null;
		if ( ! $style || ! is_string( $style->src ) || ! str_starts_with( $style->src, $base ) ) {
			continue;
		}
		$file = basename( (string) wp_parse_url( $style->src, PHP_URL_PATH ) );
		if ( ! str_starts_with( $file, 'cr-' ) || in_array( $file, $keep, true ) ) {
			continue;
		}
		$out[ $handle ] = COURTNEYR_CHILD_DIR . '/assets/css/' . $file;
	}
	return $out;
}

/**
 * Dequeue the kind sheets no rendered card uses.
 *
 * @return void
 */
function prune(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_stream_surface() ) {
		return;
	}
	$seen = rendered_classes();
	if ( ! isset( $seen['wp-block-template-part'] ) ) {
		return;
	}
	foreach ( get_body_class() as $class ) {
		$seen[ $class ] = true;
	}
	$sheets = prunable_sheets();
	foreach ( distinctive_classes( $sheets ) as $handle => $classes ) {
		if ( array() === $classes ) {
			continue;
		}
		foreach ( $classes as $class ) {
			if ( isset( $seen[ $class ] ) ) {
				continue 2;
			}
		}
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\prune', 9999 );
