<?php
/**
 * `php tests/php/zine-template-part-test.php`: X-T269 theme contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace PKIW {
	final class Zine_Request {
		public static function is_print(): bool {
			return ! empty( $GLOBALS['cr_zine_request']['print'] );
		}
	}
}

namespace {
	const CR_ZINE_BASE_BLOCKS  = 48;
	const CR_ZINE_BLOCK_DELTA  = 5;
	const CR_ZINE_PRINT_BYTES  = 2527;
	const CR_ZINE_PRINT_SHA256 = 'a8b2778e7afd8b2fa66fbeb2155ee52aed674cea9a803c363417f5e4ac80c4da';

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', '0.7.171' );

	$GLOBALS['cr_zine_failures'] = array();
	$GLOBALS['cr_zine_request']  = array();
	$GLOBALS['cr_zine_styles']   = array();
	$GLOBALS['cr_zine_inline']   = array();
	$GLOBALS['cr_zine_hooks']    = array();

	/** Minimal processor for the real cutout_heading() implementation. */
	class WP_HTML_Processor {
		private string $html;
		private string $tag = '';
		private string $text = '';
		private int $token = 0;
		private bool $closer = false;

		private function __construct( string $html ) {
			$this->html = $html;
		}

		public static function create_fragment( string $html ): self {
			return new self( $html );
		}

		public function next_tag(): bool {
			if ( 0 !== $this->token || ! preg_match( '/<([a-z0-9]+)/i', $this->html, $match ) ) {
				return false;
			}
			$this->tag = strtoupper( $match[1] );
			return true;
		}

		public function get_tag(): string {
			return $this->tag;
		}

		public function is_tag_closer(): bool {
			return $this->closer;
		}

		/** @return string[] */
		public function get_attribute_names_with_prefix( string $prefix ): array {
			$open = strstr( $this->html, '>', true ) ?: '';
			return preg_match_all( '/\s([a-z:-]+)(?:="[^"]*")?/i', $open, $matches ) ? $matches[1] : array();
		}

		public function get_attribute( string $name ): ?string {
			return preg_match( '/\s' . preg_quote( $name, '/' ) . '="([^"]*)"/i', $this->html, $match ) ? $match[1] : null;
		}

		public function next_token(): bool {
			++$this->token;
			if ( 1 === $this->token ) {
				$this->text = trim( strip_tags( $this->html ) );
				return true;
			}
			if ( 2 === $this->token ) {
				$this->closer = true;
				return true;
			}
			return false;
		}

		public function get_token_type(): string {
			return 1 === $this->token ? '#text' : '#tag';
		}

		public function get_modifiable_text(): string {
			return $this->text;
		}

		public function get_last_error(): ?string {
			return null;
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
		$GLOBALS['cr_zine_hooks'][] = array( $hook, $callback, $priority, $accepted_args );
		return true;
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
		$GLOBALS['cr_zine_hooks'][] = array( $hook, $callback, $priority, $accepted_args );
		return true;
	}

	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_zine_request']['admin'] );
	}

	function is_tax( $taxonomy = '' ): bool {
		return ! empty( $GLOBALS['cr_zine_request']['tax'] ) && ( '' === $taxonomy || 'kind' === $taxonomy );
	}

	function is_page( $page = '' ): bool {
		return ! empty( $GLOBALS['cr_zine_request']['page'] ) && ( '' === $page || 'stream' === $page );
	}

	function is_singular( $post_type = '' ): bool {
		return ! empty( $GLOBALS['cr_zine_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	function is_archive(): bool {
		return false;
	}

	function is_search(): bool {
		return false;
	}

	function is_home(): bool {
		return false;
	}

	function is_front_page(): bool {
		return false;
	}

	function is_date(): bool {
		return false;
	}

	function is_author(): bool {
		return false;
	}

	function wp_is_serving_rest_request(): bool {
		return false;
	}

	function get_query_var( $name, $default = '' ) {
		return $default;
	}

	function register_block_type( $path ): void {}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_zine_styles'][ (string) $handle ] = array( $src, $deps, $version );
	}

	function wp_register_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_zine_styles'][ (string) $handle ] = array( $src, $deps, $version, 'registered' );
	}

	function wp_add_inline_style( $handle, $css ): bool {
		$GLOBALS['cr_zine_inline'][ (string) $handle ] = (string) $css;
		return true;
	}

	function esc_attr( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_html( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
	}

	function wp_strip_all_tags( $value ): string {
		return strip_tags( (string) $value );
	}

	function wp_date( $format ): string {
		return '2026';
	}

	function cr_zine_check( bool $ok, string $message ): void {
		echo $ok ? 'ok   ' : 'FAIL ', $message, "\n";
		if ( ! $ok ) {
			$GLOBALS['cr_zine_failures'][] = $message;
		}
	}

	function cr_zine_contains( string $needle, string $haystack, string $message ): void {
		cr_zine_check( str_contains( $haystack, $needle ), $message );
	}

	/** @return array<int, array<string, mixed>> */
	function cr_zine_named_blocks( array $blocks ): array {
		$named = array();
		foreach ( $blocks as $block ) {
			if ( null !== ( $block['blockName'] ?? null ) ) {
				$named[] = $block;
			}
			$named = array_merge( $named, cr_zine_named_blocks( (array) ( $block['innerBlocks'] ?? array() ) ) );
		}
		return $named;
	}

	/** Remove CSS comments before literal-color checks. */
	function cr_zine_css_without_comments( string $css ): string {
		return (string) preg_replace( '~/\*.*?\*/~s', '', $css );
	}

	/** Return every body for an exact CSS selector. */
	function cr_zine_css_rule_bodies( string $css, string $selector ): array {
		$css     = cr_zine_css_without_comments( $css );
		$pattern = '/(?<![:\w-])' . preg_quote( $selector, '/' ) . '(?![:\[\w-])\s*\{/';
		$offset  = 0;
		$bodies  = array();
		while ( preg_match( $pattern, $css, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			$open  = strpos( $css, '{', $match[0][1] );
			$depth = 1;
			$end   = $open + 1;
			for ( $length = strlen( $css ); $end < $length && 0 < $depth; ++$end ) {
				$depth += '{' === $css[ $end ] ? 1 : ( '}' === $css[ $end ] ? -1 : 0 );
			}
			if ( 0 !== $depth ) {
				break;
			}
			$bodies[] = substr( $css, $open + 1, $end - $open - 2 );
			$offset   = $end;
		}
		return $bodies;
	}

	/** Parse custom-property declarations without splitting function values. */
	function cr_zine_css_custom_properties( string $body ): array {
		$declarations = array();
		$start        = 0;
		$depth        = 0;
		$length       = strlen( $body );
		for ( $index = 0; $index <= $length; ++$index ) {
			$character = $index < $length ? $body[ $index ] : ';';
			$depth    += '(' === $character ? 1 : ( ')' === $character ? -1 : 0 );
			if ( ';' !== $character || 0 !== $depth ) {
				continue;
			}
			$declaration = trim( substr( $body, $start, $index - $start ) );
			$start       = $index + 1;
			if ( ! str_starts_with( $declaration, '--' ) || ! str_contains( $declaration, ':' ) ) {
				continue;
			}
			list( $property, $value ) = explode( ':', $declaration, 2 );
			$declarations[ trim( $property ) ] = trim( (string) preg_replace( '/\s+/', ' ', $value ) );
		}
		return $declarations;
	}

	$core = getenv( 'WP_CORE_DIR' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	foreach ( array( 'class-wp-block-parser-block.php', 'class-wp-block-parser-frame.php', 'class-wp-block-parser.php' ) as $parser_file ) {
		$parser_path = $core . '/wp-includes/' . $parser_file;
		if ( ! is_file( $parser_path ) ) {
			cr_zine_check( false, 'WordPress core block parser is available: ' . $parser_path );
			continue;
		}
		require_once $parser_path;
	}
	if ( ! class_exists( 'WP_Block_Parser' ) ) {
		echo "\n1 failure(s)\n";
		exit( 1 );
	}

	$part_path = COURTNEYR_CHILD_DIR . '/parts/zine-export.html';
	$part      = (string) file_get_contents( $part_path );
	cr_zine_check( "<!-- wp:post-kinds-indieweb/zine-export {\"source\":\"archive\"} /-->\n" === $part, 'T1 part is exactly the frozen one-line block plus newline' );
	$part_blocks = array_values( array_filter( ( new WP_Block_Parser() )->parse( $part ), static fn( array $block ): bool => null !== ( $block['blockName'] ?? null ) ) );
	cr_zine_check( 1 === count( $part_blocks ), 'T1 real parser finds exactly one part block' );
	cr_zine_check( 'post-kinds-indieweb/zine-export' === ( $part_blocks[0]['blockName'] ?? null ), 'T1 parsed part uses the zine-export block' );
	cr_zine_check( array( 'source' => 'archive' ) === ( $part_blocks[0]['attrs'] ?? null ), 'T1 parsed part keeps source=archive' );

	$template_has_part = false;
	$iterator          = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( COURTNEYR_CHILD_DIR . '/templates', FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'html' === $file->getExtension() && str_contains( (string) file_get_contents( $file->getPathname() ), '"slug":"zine-export"' ) ) {
			$template_has_part = true;
		}
	}
	cr_zine_check( ! $template_has_part, 'T1 no template inserts the zine-export part' );

	$zine_css   = (string) file_get_contents( COURTNEYR_CHILD_DIR . '/assets/css/cr-zine.css' );
	$print_css  = (string) file_get_contents( COURTNEYR_CHILD_DIR . '/assets/css/cr-print.css' );
	$tokens_css = (string) file_get_contents( COURTNEYR_CHILD_DIR . '/assets/css/tokens.css' );
	$print_base = substr( $print_css, 0, CR_ZINE_PRINT_BYTES );
	$print_add  = substr( $print_css, CR_ZINE_PRINT_BYTES );
	$pruning    = (string) file_get_contents( COURTNEYR_CHILD_DIR . '/inc/stream-style-pruning.php' );
	cr_zine_check( strlen( $zine_css ) < 30 * 1024, 'T2 cr-zine.css stays below 30 KiB raw' );
	cr_zine_contains( "'courtneyr-zine'", $pruning, 'T2 courtneyr-zine is a RUCSS inline handle' );
	cr_zine_check( CR_ZINE_PRINT_SHA256 === hash( 'sha256', $print_base ), 'T4 original cr-print.css prefix stays byte-identical' );
	cr_zine_check( ! str_contains( $print_add, '!important' ), 'T4 appended cr-print.css has no important declaration' );
	cr_zine_check( ! str_contains( $zine_css, '!important' ), 'cr-zine.css has no important declaration' );
	cr_zine_check( 0 === preg_match( '/#[0-9a-f]{3,8}\b|rgb\(|%23/i', cr_zine_css_without_comments( $zine_css ) ), 'cr-zine.css has no literal hex, rgb or encoded hash color' );
	cr_zine_check( 0 === preg_match( '/#[0-9a-f]{3,8}\b|rgb\(|%23/i', cr_zine_css_without_comments( $print_add ) ), 'appended cr-print.css has no literal hex, rgb or encoded hash color' );
	cr_zine_contains( '.single-post__title.cr-cutout .cr-cutout__tiles', $print_add, 'single print includes the cutout-title fallback asserted when the browser fixture has no cutout H1' );
	cr_zine_contains( 'visibility: hidden', $print_add, 'single print hides lede date and category without relying on display' );
	cr_zine_contains( 'body.single-post:not(.cr-order-single):not(.cr-recipe-single):not([class~="kind-eat"]):not([class~="kind-drink"]):not([class~="kind-recipe"]):not([class~="kind-favorite"]):not([class~="kind-jam"]):not([class~="kind-menu"]):not([class~="kind-evidence"]) .single-post__header', $print_add, 'single print header reordering excludes approved kind layouts' );
	cr_zine_contains( 'body.pkiw-zine-print .cr-cutout__paper::before', $zine_css, 'booklet print forces the cutout paper pseudo-element to light stock' );

	$light_tokens = array();
	foreach ( cr_zine_css_rule_bodies( $tokens_css, ':root' ) as $root_body ) {
		$light_tokens = array_merge( $light_tokens, cr_zine_css_custom_properties( $root_body ) );
	}
	$dark_bodies  = cr_zine_css_rule_bodies( $tokens_css, ':root[data-theme="dark"]' );
	$print_media  = cr_zine_css_rule_bodies( $tokens_css, '@media print' );
	$print_bodies = cr_zine_css_rule_bodies( $print_media[0] ?? '', ':root:root:root' );
	$dark_tokens  = array();
	foreach ( $dark_bodies as $dark_body ) {
		$dark_tokens = array_merge( $dark_tokens, cr_zine_css_custom_properties( $dark_body ) );
	}
	$print_tokens = cr_zine_css_custom_properties( $print_bodies[0] ?? '' );
	cr_zine_check( 64 === count( $dark_tokens ), 'tokens.css manual-dark block declares the expected 64 custom properties' );
	cr_zine_check( array_keys( $dark_tokens ) === array_keys( $print_tokens ), 'tokens.css print block declares exactly the custom properties changed by dark mode' );
	$expected_print_tokens = array_intersect_key( $light_tokens, $dark_tokens );
	ksort( $expected_print_tokens );
	ksort( $print_tokens );
	cr_zine_check( $expected_print_tokens === $print_tokens, 'tokens.css print block copies every dark-mutated property from the light root values' );

	$style     = (string) file_get_contents( COURTNEYR_CHILD_DIR . '/style.css' );
	$functions = (string) file_get_contents( COURTNEYR_CHILD_DIR . '/functions.php' );
	preg_match( '/^Version:\s*([^\s]+)/m', $style, $style_version );
	preg_match( "/COURTNEYR_CHILD_VERSION',\s*'([^']+)'/", $functions, $function_version );
	cr_zine_check( '0.7.171' === ( $style_version[1] ?? '' ) && ( $style_version[1] ?? '' ) === ( $function_version[1] ?? '' ), 'T2 style.css and functions.php versions match at 0.7.171' );

	$single        = (string) file_get_contents( COURTNEYR_CHILD_DIR . '/templates/single.html' );
	$single_blocks = cr_zine_named_blocks( ( new WP_Block_Parser() )->parse( $single ) );
	cr_zine_check( CR_ZINE_BASE_BLOCKS + CR_ZINE_BLOCK_DELTA === count( $single_blocks ), 'T8 single block count rises from 48 to 53' );
	$byline_groups    = array_values( array_filter( $single_blocks, static fn( array $block ): bool => 'core/group' === $block['blockName'] && 'cr-print-only cr-print-byline' === ( $block['attrs']['className'] ?? '' ) ) );
	$permalink_groups = array_values( array_filter( $single_blocks, static fn( array $block ): bool => 'core/group' === $block['blockName'] && 'cr-print-only cr-print-permalink' === ( $block['attrs']['className'] ?? '' ) ) );
	cr_zine_check( 1 === count( $byline_groups ), 'T8 single has one print-only byline group' );
	cr_zine_check( array( 'core/post-author-name', 'core/post-date' ) === array_column( cr_zine_named_blocks( $byline_groups[0]['innerBlocks'] ?? array() ), 'blockName' ), 'T8 byline contains author then stored post date' );
	cr_zine_check( 1 === count( $permalink_groups ), 'T8 single has one print-only permalink group' );
	$permalink_blocks = cr_zine_named_blocks( $permalink_groups[0]['innerBlocks'] ?? array() );
	cr_zine_check( 1 === count( $permalink_blocks ) && 'core/paragraph' === $permalink_blocks[0]['blockName'], 'T8 permalink group contains one paragraph' );
	cr_zine_check(
		array( 'content' => array( 'source' => 'core/post-data', 'args' => array( 'field' => 'link' ) ) ) === ( $permalink_blocks[0]['attrs']['metadata']['bindings'] ?? null ),
		'T8 permalink paragraph binds content to core/post-data link'
	);

	require COURTNEYR_CHILD_DIR . '/inc/zine.php';
	$plain_heading = '<h1 class="pkiw-zine__title">Read</h1>';
	cr_zine_check( $plain_heading === \Courtneyr\Child\Zine\cover_title( $plain_heading, 'Read' ), 'cover filter returns input unchanged without cutout_heading' );

	$GLOBALS['cr_zine_request'] = array();
	\Courtneyr\Child\Zine\enqueue_styles();
	cr_zine_check( ! isset( $GLOBALS['cr_zine_styles']['courtneyr-zine'] ), 'zine sheet stays off unrelated requests' );
	$GLOBALS['cr_zine_request'] = array( 'tax' => true );
	\Courtneyr\Child\Zine\enqueue_styles();
	cr_zine_check( isset( $GLOBALS['cr_zine_styles']['courtneyr-zine'] ), 'zine sheet loads on kind archives' );
	$GLOBALS['cr_zine_styles']  = array();
	$GLOBALS['cr_zine_request'] = array( 'page' => true );
	\Courtneyr\Child\Zine\enqueue_styles();
	cr_zine_check( isset( $GLOBALS['cr_zine_styles']['courtneyr-zine'] ), 'zine sheet loads on the Stream page' );
	$GLOBALS['cr_zine_styles']  = array();
	$GLOBALS['cr_zine_request'] = array( 'print' => true );
	\Courtneyr\Child\Zine\enqueue_styles();
	cr_zine_check( isset( $GLOBALS['cr_zine_styles']['courtneyr-zine'] ), 'zine sheet loads in print mode' );
	$GLOBALS['cr_zine_styles']  = array();
	$GLOBALS['cr_zine_request'] = array( 'tax' => true, 'admin' => true );
	\Courtneyr\Child\Zine\enqueue_styles();
	cr_zine_check( ! isset( $GLOBALS['cr_zine_styles']['courtneyr-zine'] ), 'zine sheet stays out of admin' );

	$GLOBALS['cr_zine_request'] = array( 'single' => true );
	\Courtneyr\Child\Zine\enqueue_print_only_visibility();
	cr_zine_check( isset( $GLOBALS['cr_zine_styles']['courtneyr-print-only'] ), 'single registers and enqueues the print-only screen handle' );
	cr_zine_check( '@media screen{.cr-print-only{display:none}}' === ( $GLOBALS['cr_zine_inline']['courtneyr-print-only'] ?? '' ), 'single uses the exact screen-only visibility rule' );
	cr_zine_contains( '! is_singular()', (string) file_get_contents( COURTNEYR_CHILD_DIR . '/inc/zine.php' ), 'print-only screen handle loads for every singular type that uses templates/single.html' );

	require COURTNEYR_CHILD_DIR . '/inc/archives.php';
	foreach ( array( 'Read', 'Zine 269!', 'Digits 0123456789, punctuation?!', str_repeat( 'Z', 40 ) ) as $title ) {
		$input = '<h1 class="pkiw-zine__title">' . esc_html( $title ) . '</h1>';
		$cut   = \Courtneyr\Child\Zine\cover_title( $input, $title );
		cr_zine_contains( 'class="pkiw-zine__title cr-cutout"', $cut, 'T7 cover keeps original class and adds cr-cutout: ' . $title );
		cr_zine_contains( '<span class="cr-cutout__text screen-reader-text">' . esc_html( $title ) . '</span>', $cut, 'T7 cover keeps exact screen-reader title: ' . $title );
		cr_zine_contains( '<span class="cr-cutout__tiles" aria-hidden="true">', $cut, 'T7 cover tile layer is aria-hidden: ' . $title );
		preg_match_all( '/\bis-r([0-3])\b/', $cut, $matches );
		$adjacent = true;
		for ( $index = 1; $index < count( $matches[1] ); ++$index ) {
			$adjacent = $adjacent && $matches[1][ $index ] !== $matches[1][ $index - 1 ];
		}
		cr_zine_check( $adjacent && array() !== $matches[1], 'T7 adjacent tiles use different rotation classes: ' . $title );
	}

	$zine_source = (string) file_get_contents( COURTNEYR_CHILD_DIR . '/inc/zine.php' );
	cr_zine_check( ! str_contains( $zine_source, 'preg_' ), 'production zine module adds no preg operation' );
	cr_zine_contains( "require_once __DIR__ . '/inc/zine.php';", $functions, 'functions.php loads the zine module' );

	if ( array() !== $GLOBALS['cr_zine_failures'] ) {
		echo "\n", count( $GLOBALS['cr_zine_failures'] ), " failure(s)\n";
		exit( 1 );
	}
	echo "\nAll zine theme checks passed.\n";
}
