<?php
/**
 * `php tests/php/on-this-day-test.php`: X-T268 theme contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'CR_OTD_THEME_DIR', COURTNEYR_CHILD_DIR );

	$GLOBALS['cr_otd_failures'] = array();
	$GLOBALS['cr_otd_request']  = array();
	$GLOBALS['cr_otd_styles']   = array();

	class WP_Term {}
	class WP_Post_Type {}

	/** Minimal processor for the one rendered heading used by this test. */
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
			return array( 'class' );
		}

		public function get_attribute( string $name ): ?string {
			if ( 'class' === $name && preg_match( '/class="([^"]*)"/', $this->html, $match ) ) {
				return $match[1];
			}
			return null;
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
		return true;
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
		return true;
	}

	function get_query_var( $name, $default = '' ) {
		return $GLOBALS['cr_otd_request'][ $name ] ?? $default;
	}

	function get_queried_object() {
		return null;
	}

	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_otd_request']['admin'] );
	}

	function wp_is_serving_rest_request(): bool {
		return ! empty( $GLOBALS['cr_otd_request']['rest'] );
	}

	function is_archive(): bool {
		return ! empty( $GLOBALS['cr_otd_request']['archive'] );
	}

	function is_front_page(): bool {
		return ! empty( $GLOBALS['cr_otd_request']['front_page'] );
	}

	function is_home(): bool {
		return ! empty( $GLOBALS['cr_otd_request']['home'] );
	}

	function is_page( $page = '' ): bool {
		return ! empty( $GLOBALS['cr_otd_request']['page'] )
			&& ( '' === $page || $GLOBALS['cr_otd_request']['page'] === $page );
	}

	function is_singular( $post_type = '' ): bool {
		return ! empty( $GLOBALS['cr_otd_request']['single'] )
			&& ( '' === $post_type || 'post' === $post_type );
	}

	function is_date(): bool {
		return false;
	}

	function is_author(): bool {
		return false;
	}

	function is_search(): bool {
		return false;
	}

	function sanitize_key( $value ): string {
		return strtolower( str_replace( array( '_', ' ' ), '-', (string) $value ) );
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function get_stylesheet_directory_uri(): string {
		return COURTNEYR_CHILD_URI;
	}

	function esc_url( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_attr( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_html( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', false );
	}

	function __( $value, $domain = null ): string {
		return (string) $value;
	}

	function wp_strip_all_tags( $value ): string {
		return strip_tags( (string) $value );
	}

	function wp_date( $format ): string {
		return 'm-d' === $format ? '09-28' : 'September 28';
	}

	function register_block_type( $path ): void {}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_otd_styles'][ (string) $handle ] = (string) $src;
	}

	function cr_otd_check( bool $ok, string $message ): void {
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$GLOBALS['cr_otd_failures'][] = $message;
		}
	}

	function cr_otd_contains( string $needle, string $haystack, string $message ): void {
		cr_otd_check( false !== strpos( $haystack, $needle ), $message );
	}

	function cr_otd_not_contains( string $needle, string $haystack, string $message ): void {
		cr_otd_check( false === strpos( $haystack, $needle ), $message );
	}

	/** @param string[] $needles */
	function cr_otd_in_order( array $needles, string $haystack, string $message ): void {
		$offset = -1;
		foreach ( $needles as $needle ) {
			$next = strpos( $haystack, $needle, $offset + 1 );
			if ( false === $next ) {
				cr_otd_check( false, $message );
				return;
			}
			$offset = $next;
		}
		cr_otd_check( true, $message );
	}
}

namespace PKIW {
	function get_kind_icon_svg( string $kind ): string {
		return '<svg data-kind="' . \esc_attr( $kind ) . '"></svg>';
	}

	final class On_This_Day {
		public static bool $edge = false;

		public static function edge_can_purge(): bool {
			return self::$edge;
		}
	}
}

namespace Courtneyr\Child\Series {
	function is_series_toc(): bool {
		return false;
	}
}

namespace {
	require CR_OTD_THEME_DIR . '/inc/enqueue.php';
	require CR_OTD_THEME_DIR . '/inc/archives.php';

	use Courtneyr\Child\Archives;
	use Courtneyr\Child\Enqueue;

	$template = (string) file_get_contents( CR_OTD_THEME_DIR . '/templates/on-this-day.html' );
	$single   = (string) file_get_contents( CR_OTD_THEME_DIR . '/templates/single.html' );

	cr_otd_check( in_array( 'cr-on-this-day.css', Enqueue\perfmatters_rucss_exclusions( array() ), true ), 'RUCSS excludes cr-on-this-day.css' );

	foreach ( array( 'group', 'heading', 'paragraph', 'query', 'post-template', 'query-pagination', 'query-no-results' ) as $paired_block ) {
		cr_otd_check(
			substr_count( $template, '<!-- wp:' . $paired_block . ' ' ) === substr_count( $template, '<!-- /wp:' . $paired_block . ' -->' ),
			$paired_block . ' block comments are balanced'
		);
	}
	cr_otd_in_order(
		array(
			'<!-- wp:group {"className":"is-position-sticky"',
			'<!-- wp:template-part {"slug":"header"',
			'<!-- wp:group {"tagName":"main"',
			'<!-- wp:courtneyr/archive-identity /-->',
			'<!-- wp:heading {"level":1,"className":"cr-archive__title cr-otd__title"}',
			'<!-- wp:paragraph {"className":"cr-otd__date"}',
			'<!-- wp:paragraph {"className":"cr-otd__description"}',
			'<!-- wp:query {"query":{"inherit":true}} -->',
			'<!-- wp:post-template {"className":"cr-archive-stream__list is-style-pkiw-shelf cr-media-shelf__list"} -->',
			'<!-- wp:post-kinds-indieweb/on-this-day-entry {"headingLevel":3} /-->',
			'<!-- wp:query-pagination ',
			'<!-- wp:query-pagination-previous ',
			'<!-- wp:query-pagination-numbers /-->',
			'<!-- wp:query-pagination-next ',
			'<!-- wp:query-no-results -->',
			'<p>Nothing here yet.</p>',
			'<!-- wp:template-part {"slug":"footer"',
		),
		$template,
		'template blocks follow the frozen order'
	);
	cr_otd_check( 1 === substr_count( $template, 'wp:post-kinds-indieweb/on-this-day-entry' ), 'template has one entry block' );
	cr_otd_contains( 'class="wp-block-group alignfull cr-page-style"', $template, 'template main uses cr-page-style' );
	cr_otd_not_contains( 'wp:query-title', $template, 'template has no query-title' );
	cr_otd_not_contains( 'wp:term-description', $template, 'template has no term-description' );

	cr_otd_in_order(
		array(
			'<!-- wp:template-part {"slug":"kind"} /-->',
			'<!-- wp:post-kinds-indieweb/on-this-day {"context":"single"} /-->',
			'className":"single-post__comments-wrapper"',
			'className":"cr-post-nav"',
		),
		$single,
		'single block follows kind and precedes comments and post navigation'
	);
	cr_otd_check( 1 === substr_count( $single, 'wp:post-kinds-indieweb/on-this-day ' ), 'single template has exactly one memory block' );
	foreach ( glob( CR_OTD_THEME_DIR . '/templates/taxonomy-kind-*.html' ) as $taxonomy_template ) {
		cr_otd_not_contains( 'on-this-day', (string) file_get_contents( $taxonomy_template ), basename( $taxonomy_template ) . ' has no On This Day reference' );
	}

	$GLOBALS['cr_otd_request'] = array( 'pkiw_on_this_day' => 1, 'archive' => true );
	$identity = Archives\resolve_identity();
	cr_otd_check( '' === $identity['kicker'], 'route identity has no kicker' );
	cr_otd_check( 'event' === $identity['type'], 'route identity uses event type' );
	cr_otd_check( '' !== $identity['glyph'], 'route identity has a glyph' );

	$heading = '<h1 class="wp-block-heading cr-archive__title cr-otd__title">On This Day</h1>';
	$cut     = Archives\cutout_archive_title(
		$heading,
		array( 'blockName' => 'core/heading', 'attrs' => array( 'className' => 'cr-archive__title cr-otd__title' ) )
	);
	cr_otd_contains( 'cr-cutout', $cut, 'route title is cut out' );
	cr_otd_contains( 'aria-hidden="true"', $cut, 'cutout tiles are hidden from assistive technology' );
	$GLOBALS['cr_otd_request'] = array();
	cr_otd_check( $heading === Archives\cutout_archive_title( $heading, array( 'blockName' => 'core/heading', 'attrs' => array( 'className' => 'cr-archive__title cr-otd__title' ) ) ), 'off-route title is unchanged' );

	$GLOBALS['cr_otd_request'] = array( 'pkiw_on_this_day' => 1, 'archive' => true );
	$date = Archives\render_on_this_day_paragraph( '<p class="cr-otd__date"></p>', array( 'attrs' => array( 'className' => 'cr-otd__date' ) ) );
	cr_otd_check( '<p class="cr-otd__date"><time datetime="--09-28">September 28</time></p>' === $date, 'date line uses exact pinned site-local time markup' );
	cr_otd_check( '' === Archives\render_on_this_day_paragraph( '<p class="cr-otd__description"></p>', array( 'attrs' => array( 'className' => 'cr-otd__description' ) ) ), 'empty description is removed' );
	cr_otd_check( '<p class="cr-otd__description">Authored text.</p>' === Archives\render_on_this_day_paragraph( '<p class="cr-otd__description">Authored text.</p>', array( 'attrs' => array( 'className' => 'cr-otd__description' ) ) ), 'authored description remains' );

	$cases = array(
		'route'                  => array( array( 'pkiw_on_this_day' => 1, 'archive' => true ), false, true ),
		'Stream page'            => array( array( 'page' => 'stream' ), false, true ),
		'ordinary single'        => array( array( 'single' => true ), false, false ),
		'purgeable single'       => array( array( 'single' => true ), true, true ),
		'front page'             => array( array( 'front_page' => true ), false, false ),
	);
	foreach ( $cases as $label => list( $request, $edge, $expected ) ) {
		$GLOBALS['cr_otd_request'] = $request;
		$GLOBALS['cr_otd_styles']  = array();
		\PKIW\On_This_Day::$edge  = $edge;
		Archives\enqueue_on_this_day_styles();
		cr_otd_check( $expected === isset( $GLOBALS['cr_otd_styles']['courtneyr-on-this-day'] ), $label . ' enqueue condition' );
	}

	if ( array() !== $GLOBALS['cr_otd_failures'] ) {
		echo "\n", count( $GLOBALS['cr_otd_failures'] ), " failure(s)\n";
		exit( 1 );
	}
	echo "\nAll On This Day theme checks passed.\n";
}
