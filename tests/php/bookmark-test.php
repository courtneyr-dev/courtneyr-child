<?php
/**
 * `php tests/php/bookmark-test.php`: checks bookmark archive and Stream adapters.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_BM_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']          = array();
	$GLOBALS['cr_registered']     = array();
	$GLOBALS['cr_request']        = array();
	$GLOBALS['cr_posts']          = array();
	$GLOBALS['cr_terms']          = array();
	$GLOBALS['cr_kind_facts']     = array();
	$GLOBALS['cr_current_post']   = 0;
	$GLOBALS['cr_styles']         = array();
	$GLOBALS['cr_stream_surface'] = false;

	/** Stand-in post. */
	class WP_Post {
		public $ID;
		public $post_title;
		public $post_status = 'publish';
		public $post_password = '';

		public function __construct( int $id, string $title = '' ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	/** Stand-in block instance. */
	class WP_Block {
		public $context;

		public function __construct( array $context = array() ) {
			$this->context = $context;
		}
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}

	function apply_filters( $hook, $value, ...$args ) {
		$priorities = $GLOBALS['cr_hooks'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				$all   = array_merge( array( $value ), $args );
				$value = call_user_func_array( $registered[0], array_slice( $all, 0, $registered[1] ) );
			}
		}
		return $value;
	}

	function register_block_style( $block, $style ) {
		$GLOBALS['cr_registered'][] = array( $block, $style );
	}

	function is_tax( $taxonomy = '', $term = '' ) {
		return 'kind' === $taxonomy && (string) $term === (string) ( $GLOBALS['cr_request']['kind'] ?? '' );
	}

	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried_id'] ?? 0 );
	}

	function is_admin() {
		return ! empty( $GLOBALS['cr_request']['admin'] );
	}

	function has_term( $term, $taxonomy, $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && 'bookmark' === $term && ! empty( $GLOBALS['cr_terms'][ $id ] );
	}

	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? (int) $GLOBALS['cr_current_post'] : (int) $post;
		return $GLOBALS['cr_posts'][ $id ] ?? null;
	}

	function get_the_ID() {
		return (int) $GLOBALS['cr_current_post'];
	}

	function get_the_title( $post = 0 ) {
		$post = $post instanceof WP_Post ? $post : get_post( $post );
		return $post instanceof WP_Post ? $post->post_title : '';
	}

	function get_permalink( $post = 0 ) {
		$post = $post instanceof WP_Post ? $post : get_post( $post );
		$id   = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'https://site.test/bookmarks/' . $id . '/';
	}

	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	function __( $text ) {
		return $text;
	}

	function esc_html__( $text ) {
		return esc_html( $text );
	}

	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
		$GLOBALS['cr_styles'][ $handle ] = array( $src, $deps, $version );
	}

	function wp_has_noncharacters( $text ) {
		return false;
	}

	function wp_kses_uri_attributes() {
		return array( 'href', 'src', 'action', 'cite', 'poster' );
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_stream_surface'];
	}
}

namespace PKIW {
	if ( ! in_array( '--no-plugin', $GLOBALS['argv'], true ) ) {
		function kind_facts( int $post_id ): array {
			return (array) ( $GLOBALS['cr_kind_facts'][ $post_id ] ?? array() );
		}

		function hidden_cite_html( string $property, array $parts ): string {
			$out = '';
			if ( '' !== (string) ( $parts['p-name'] ?? '' ) ) {
				$out .= '<data class="p-name" value="' . \esc_attr( (string) $parts['p-name'] ) . '"></data>';
			}
			if ( '' !== (string) ( $parts['p-author'] ?? '' ) ) {
				$out .= '<span class="p-author h-card"><data class="p-name" value="' . \esc_attr( (string) $parts['p-author'] ) . '"></data></span>';
			}
			if ( '' !== (string) ( $parts['u-url'] ?? '' ) ) {
				$out .= '<data class="u-url" value="' . \esc_attr( (string) $parts['u-url'] ) . '"></data>';
			}
			return '' === $out ? '' : '<span class="pk-kind-cite h-cite ' . \esc_attr( $property ) . '" hidden>' . $out . '</span>';
		}

		function get_kind_label( string $label, string $kind, string $context ): string {
			return $label;
		}

		function get_kind_icon_svg( string $kind ): string {
			return '<svg class="stub-bookmark"></svg>';
		}
	}
}

namespace {
	$core = getenv( 'WP_CORE_DIR' ) ?: getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api/';
	foreach ( array( 'class-wp-html-attribute-token.php', 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		if ( ! is_file( $api . $file ) ) {
			fwrite( STDERR, 'Missing WordPress HTML API file: ' . $api . $file . "\n" );
			exit( 2 );
		}
		require_once $api . $file;
	}

	require CR_BM_THEME_DIR . '/inc/stamps.php';
	$GLOBALS['cr_hooks'] = array();
	$bookmark_path = CR_BM_THEME_DIR . '/inc/bookmark.php';
	if ( is_file( $bookmark_path ) ) {
		require $bookmark_path;
	}

	if ( in_array( '--no-plugin', $argv, true ) ) {
		$post                       = new WP_Post( 91, 'Release notes' );
		$GLOBALS['cr_posts'][91]    = $post;
		$GLOBALS['cr_terms'][91]    = true;
		$GLOBALS['cr_current_post'] = 91;
		$html                       = '<article class="pk-card"><h3 class="pk-title"><a href="/x">Release notes</a></h3></article>';
		try {
			$result = function_exists( 'Courtneyr\\Child\\Bookmark\\dispatch' ) ? \Courtneyr\Child\Bookmark\dispatch( $html, array( 'attrs' => array( 'className' => 'is-style-cr-bookmark-card' ) ), new WP_Block( array( 'postId' => 91 ) ) ) : $html;
			echo $result === $html ? "NO_PLUGIN_OK\n" : "NO_PLUGIN_FAIL\n";
			exit( $result === $html ? 0 : 1 );
		} catch ( Throwable $error ) {
			echo "NO_PLUGIN_FAIL\n";
			exit( 1 );
		}
	}
}

namespace Courtneyr\Child\Bookmark\Tests {
	$failures  = array();
	$case_count = 0;

	function check_case( string $name, callable $test ): void {
		global $failures, $case_count;
		++$case_count;
		try {
			$ok = (bool) $test();
		} catch ( \Throwable $error ) {
			$ok   = false;
			$name = $name . ' (' . $error->getMessage() . ')';
		}
		echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
		if ( ! $ok ) {
			$failures[] = $name;
		}
	}

	function reset_state(): void {
		$GLOBALS['cr_request']        = array();
		$GLOBALS['cr_posts']          = array();
		$GLOBALS['cr_terms']          = array();
		$GLOBALS['cr_kind_facts']     = array();
		$GLOBALS['cr_current_post']   = 0;
		$GLOBALS['cr_styles']         = array();
		$GLOBALS['cr_stream_surface'] = false;
		unset( $GLOBALS['wp_query'] );
	}

	function full_facts( array $overrides = array() ): array {
		return array_merge(
			array(
				'url'         => 'https://example.com/p?q=1',
				'host'        => 'example.com',
				'title'       => 'Release notes',
				'cited_title' => 'Accessible drawers, explained',
				'author'      => 'A. Writer',
				'note'        => "Worth rereading.\nSecond line.",
				'rel'         => 'nofollow',
				'date'        => array( '2026-07-04', 'July 4, 2026' ),
				'picture'     => array( 'source' => 'featured', 'attachment_id' => 0, 'url' => 'https://img.example/f.jpg', 'alt' => 'A filing cabinet', 'remote' => true, 'suppress_featured' => true ),
				'drawer'      => array( 'key' => 'alpha', 'label' => 'Alpha' ),
			),
			$overrides
		);
	}

	function bookmark_post( int $id = 41, string $title = 'Release notes', array $facts = array() ): \WP_Post {
		$post                            = new \WP_Post( $id, $title );
		$GLOBALS['cr_posts'][ $id ]      = $post;
		$GLOBALS['cr_terms'][ $id ]      = true;
		$GLOBALS['cr_current_post']      = $id;
		$GLOBALS['cr_kind_facts'][ $id ] = $facts ? $facts : full_facts();
		return $post;
	}

	function render_card( int $id = 41, array $attrs = array(), string $html = '<article class="pk-card"><h3 class="pk-title"><a href="/old">Old</a></h3><img src="/x.jpg"><p class="pk-note">Note</p><p class="p-author">A</p></article>' ): string {
		return \Courtneyr\Child\Bookmark\dispatch( $html, array( 'attrs' => $attrs ), new \WP_Block( array( 'postId' => $id ) ) );
	}

	function dom( string $html ): \DOMXPath {
		$doc = new \DOMDocument();
		$doc->loadHTML( '<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING );
		return new \DOMXPath( $doc );
	}

	function text_of( \DOMXPath $xp, string $query ): string {
		$node = $xp->query( $query )->item( 0 );
		return $node ? norm( $node->textContent ) : '';
	}

	function visible_text( string $html ): string {
		$xp = dom( $html );
		foreach ( $xp->query( '//*[@hidden or @aria-hidden="true"]' ) as $node ) {
			$node->parentNode->removeChild( $node );
		}
		return norm( $xp->query( '//body' )->item( 0 )->textContent ?? '' );
	}

	function norm( string $text ): string {
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	function has_class( \DOMElement $node, string $class ): bool {
		return in_array( $class, preg_split( '/\s+/', $node->getAttribute( 'class' ) ), true );
	}

	check_case(
		'registers style and bookmark hooks',
		static function (): bool {
			\Courtneyr\Child\Bookmark\register_item_style();
			$style  = $GLOBALS['cr_registered'][0] ?? array();
			$hooks  = $GLOBALS['cr_hooks'];
			$render = $hooks['render_block_post-kinds-indieweb/stream-card'][10][0] ?? null;
			$init   = $hooks['init'][10][0] ?? null;
			$assets = $hooks['enqueue_block_assets'][10][0] ?? null;
			return 'post-kinds-indieweb/stream-card' === ( $style[0] ?? '' )
				&& 'cr-bookmark-card' === ( $style[1]['name'] ?? '' )
				&& 1 === ( $init[1] ?? 0 )
				&& 1 === ( $assets[1] ?? 0 )
				&& 3 === ( $render[1] ?? 0 )
				&& empty( $hooks['courtneyr_child_kind_archive_page_sizes'] ?? array() );
		}
	);

	check_case(
		'archive card prints one permalink title and hidden bookmark-of',
		static function (): bool {
			reset_state();
			bookmark_post();
			$out = render_card( 41, array( 'className' => 'is-style-cr-bookmark-card', 'headingLevel' => 3 ) );
			$xp  = dom( $out );
			$a   = $xp->query( '//a' );
			$h   = $xp->query( '//h3' )->item( 0 );
			$art = $xp->query( '//article' )->item( 0 );
			return 1 === $a->length
				&& 'https://site.test/bookmarks/41/' === $a->item( 0 )->getAttribute( 'href' )
				&& ! $a->item( 0 )->hasAttribute( 'target' )
				&& 'Release notes' === norm( $a->item( 0 )->textContent )
				&& $h instanceof \DOMElement
				&& has_class( $h, 'pk-title' ) && has_class( $h, 'cr-index-card__title' ) && has_class( $h, 'p-name' )
				&& $art instanceof \DOMElement
				&& has_class( $art, 'pk-card' ) && has_class( $art, 'k-bookmark' ) && has_class( $art, 'cr-index-card' ) && has_class( $art, 'cr-paper-sheet' ) && has_class( $art, 'cr-paper--printer-ivory' ) && has_class( $art, 'is-style-cr-bookmark-card' )
				&& ! has_class( $art, 'h-entry' ) && ! has_class( $art, 'h-cite' ) && ! has_class( $art, 'u-bookmark-of' )
				&& 'Release notes example.com' === visible_text( $out )
				&& 0 === $xp->query( '//img|//svg|//time|//dl|//script' )->length
				&& 'example.com' === text_of( $xp, '//p[contains(@class,"cr-index-card__host")]' )
				&& 1 === $xp->query( '//*[contains(concat(" ", @class, " "), " u-bookmark-of ")]' )->length
				&& 'https://example.com/p?q=1' === $xp->query( '//span[contains(concat(" ", @class, " "), " u-bookmark-of ")]/data[contains(concat(" ", @class, " "), " u-url ")]' )->item( 0 )->getAttribute( 'value' )
				&& ! $h->hasAttribute( 'id' )
				&& ! $a->item( 0 )->hasAttribute( 'aria-labelledby' );
		}
	);

	check_case(
		'heading levels default and clamp',
		static function (): bool {
			reset_state();
			bookmark_post();
			$missing = render_card( 41, array( 'className' => 'is-style-cr-bookmark-card' ) );
			$high    = render_card( 41, array( 'className' => 'is-style-cr-bookmark-card', 'headingLevel' => 9 ) );
			$low     = render_card( 41, array( 'className' => 'is-style-cr-bookmark-card', 'headingLevel' => 1 ) );
			return 1 === dom( $missing )->query( '//h2' )->length
				&& 1 === dom( $high )->query( '//h4' )->length
				&& 1 === dom( $low )->query( '//h2' )->length;
		}
	);

	check_case(
		'archive card omits host and bookmark-of when url and host are empty',
		static function (): bool {
			reset_state();
			bookmark_post( 41, 'Release notes', full_facts( array( 'url' => '', 'host' => '' ) ) );
			$xp = dom( render_card( 41, array( 'className' => 'is-style-cr-bookmark-card' ) ) );
			return 1 === $xp->query( '//a' )->length
				&& 0 === $xp->query( '//p[contains(@class,"cr-index-card__host")]' )->length
				&& 0 === $xp->query( '//*[contains(concat(" ", @class, " "), " u-bookmark-of ")]' )->length;
		}
	);

	check_case(
		'p-name appears only when the stored title is the real visible name',
		static function (): bool {
			reset_state();
			bookmark_post( 41, 'Release notes', full_facts( array( 'title' => '  release   NOTES ' ) ) );
			$equal = dom( render_card( 41, array( 'className' => 'is-style-cr-bookmark-card' ) ) )->query( '//*[contains(concat(" ", @class, " "), " p-name ")][not(ancestor::*[@hidden])]' )->length;
			bookmark_post( 42, '', full_facts( array( 'title' => 'example.com' ) ) );
			$empty = dom( render_card( 42, array( 'className' => 'is-style-cr-bookmark-card' ) ) )->query( '//*[contains(concat(" ", @class, " "), " p-name ")][not(ancestor::*[@hidden])]' )->length;
			bookmark_post( 43, 'Bookmarked: X', full_facts( array( 'title' => 'Cited X' ) ) );
			$diff = dom( render_card( 43, array( 'className' => 'is-style-cr-bookmark-card' ) ) )->query( '//*[contains(concat(" ", @class, " "), " p-name ")][not(ancestor::*[@hidden])]' )->length;
			return 1 === $equal && 0 === $empty && 0 === $diff;
		}
	);

	check_case(
		'duplicate archive titles add title and host labels per query',
		static function (): bool {
			reset_state();
			bookmark_post( 51, 'Release notes', full_facts( array( 'title' => 'Release notes', 'host' => 'example.com' ) ) );
			bookmark_post( 52, 'Release Notes', full_facts( array( 'title' => 'Release  Notes', 'host' => 'example.org' ) ) );
			bookmark_post( 53, 'Unique', full_facts( array( 'title' => 'Unique', 'host' => 'example.net' ) ) );
			bookmark_post( 54, 'Release notes', full_facts( array( 'title' => 'Release notes', 'host' => 'ignored.test' ) ) );
			$GLOBALS['cr_terms'][54] = false;
			$GLOBALS['wp_query']     = (object) array( 'posts' => array( $GLOBALS['cr_posts'][51], $GLOBALS['cr_posts'][52], $GLOBALS['cr_posts'][53], $GLOBALS['cr_posts'][54] ) );
			$one = dom( render_card( 51, array( 'className' => 'is-style-cr-bookmark-card' ) ) );
			$two = dom( render_card( 52, array( 'className' => 'is-style-cr-bookmark-card' ) ) );
			$uni = dom( render_card( 53, array( 'className' => 'is-style-cr-bookmark-card' ) ) );
			bookmark_post( 55, 'Release notes', full_facts( array( 'title' => 'Release notes', 'host' => '' ) ) );
			bookmark_post( 56, 'Release notes', full_facts( array( 'title' => 'Release notes', 'host' => 'other.test' ) ) );
			$GLOBALS['wp_query'] = (object) array( 'posts' => array( $GLOBALS['cr_posts'][55], $GLOBALS['cr_posts'][56] ) );
			$nohost = dom( render_card( 55, array( 'className' => 'is-style-cr-bookmark-card' ) ) );
			return 'cr-bookmark-title-51' === $one->query( '//h2' )->item( 0 )->getAttribute( 'id' )
				&& 'cr-bookmark-host-51' === $one->query( '//p[contains(@class,"cr-index-card__host")]' )->item( 0 )->getAttribute( 'id' )
				&& 'cr-bookmark-title-51 cr-bookmark-host-51' === $one->query( '//a' )->item( 0 )->getAttribute( 'aria-labelledby' )
				&& 'cr-bookmark-title-52' === $two->query( '//h2' )->item( 0 )->getAttribute( 'id' )
				&& ! $uni->query( '//h2' )->item( 0 )->hasAttribute( 'id' )
				&& ! $nohost->query( '//h2' )->item( 0 )->hasAttribute( 'id' );
		}
	);

	check_case(
		'archive card escapes title and host',
		static function (): bool {
			reset_state();
			bookmark_post( 41, '<b>x</b> & "q"', full_facts( array( 'title' => '<b>x</b> & "q"', 'host' => 'ex"ample.com' ) ) );
			$out = render_card( 41, array( 'className' => 'is-style-cr-bookmark-card' ) );
			return ! str_contains( $out, '<b>x</b>' )
				&& str_contains( $out, '&lt;b&gt;x&lt;/b&gt; &amp; &quot;q&quot;' )
				&& str_contains( $out, 'ex&quot;ample.com' );
		}
	);

	check_case(
		'protected cards only gain locked index-card classes',
		static function (): bool {
			reset_state();
			bookmark_post( 41, 'Protected: x' );
			$GLOBALS['cr_kind_facts'][41] = array();
			$html = '<article class="pk-card pk-card--stream pk-card--protected k-bookmark h-entry"><h3 class="pk-title p-name"><a class="u-url" href="...">Protected: x</a></h3></article>';
			$out  = render_card( 41, array( 'className' => 'is-style-cr-bookmark-card' ), $html );
			$raw  = render_card( 41, array( 'className' => 'is-style-cr-bookmark-card' ), '<article class="pk-card k-bookmark">x</article>' );
			return str_contains( $out, 'cr-index-card' )
				&& str_contains( $out, 'cr-index-card--locked' )
				&& str_contains( $out, 'h-entry' )
				&& ! str_contains( $out, 'u-bookmark-of' )
				&& ! str_contains( $out, 'example.com' )
				&& '<article class="pk-card k-bookmark">x</article>' === $raw;
		}
	);

	check_case(
		'routing separates archive, Stream, unrelated and non-bookmark cards',
		static function (): bool {
			reset_state();
			bookmark_post();
			$html = '<article class="pk-card k-bookmark">x</article>';
			if ( $html !== render_card( 41, array(), $html ) ) {
				return false;
			}
			$GLOBALS['cr_terms'][41] = false;
			if ( $html !== render_card( 41, array( 'className' => 'is-style-cr-bookmark-card' ), $html ) ) {
				return false;
			}
			$GLOBALS['cr_terms'][41] = true;
			$GLOBALS['cr_request']['kind'] = 'bookmark';
			if ( ! str_contains( render_card( 41, array(), $html ), 'cr-index-card' ) ) {
				return false;
			}
			$GLOBALS['cr_request'] = array();
			if ( ! str_contains( render_card( 41, array( 'className' => 'is-style-cr-bookmark-card' ), $html ), 'cr-index-card' ) ) {
				return false;
			}
			return '' === render_card( 41, array(), '' );
		}
	);

	check_case(
		'Stream card prints label title host date stamp and hidden bookmark-of',
		static function (): bool {
			reset_state();
			bookmark_post();
			$GLOBALS['cr_stream_surface'] = true;
			$out = render_card( 41, array(), '<article class="pk-card k-bookmark">x</article>' );
			$xp  = dom( $out );
			$art = $xp->query( '//article' )->item( 0 );
			$a   = $xp->query( '//a' );
			$time = $xp->query( '//time' );
			$seed = \Courtneyr\Child\Stamps\seed( 'bookmark', '41' );
			$ink  = \Courtneyr\Child\Stamps\INKS[ \Courtneyr\Child\Stamps\pick( $seed, 0, 3 ) ];
			$tilt = \Courtneyr\Child\Stamps\pick( $seed, 6, \Courtneyr\Child\Stamps\TILTS );
			$stamp = $xp->query( '//span[contains(@class,"cr-bookmark-stream__stamp")]' )->item( 0 );
			bookmark_post( 42, 'Release notes', full_facts( array( 'host' => '' ) ) );
			$GLOBALS['cr_stream_surface'] = true;
			$nohost = dom( render_card( 42, array(), '<article class="pk-card k-bookmark">x</article>' ) );
			return $art instanceof \DOMElement
				&& has_class( $art, 'pk-card' ) && has_class( $art, 'pk-card--stream' ) && has_class( $art, 'k-bookmark' ) && has_class( $art, 'cr-bookmark-stream' )
				&& 'Bookmark Release notes example.com July 4, 2026' === visible_text( $out )
				&& 1 === $a->length
				&& 'u-url' === $a->item( 0 )->getAttribute( 'class' )
				&& 'https://site.test/bookmarks/41/' === $a->item( 0 )->getAttribute( 'href' )
				&& ! $a->item( 0 )->hasAttribute( 'target' )
				&& 'true' === $xp->query( '//span[contains(@class,"cr-bookmark-stream__glyph")]' )->item( 0 )->getAttribute( 'aria-hidden' )
				&& 1 === $xp->query( '//svg[contains(@class,"stub-bookmark")]' )->length
				&& 1 === $time->length
				&& '2026-07-04' === $time->item( 0 )->getAttribute( 'datetime' )
				&& ! $time->item( 0 )->hasAttribute( 'class' )
				&& ! str_contains( $out, 'dt-published' )
				&& $stamp instanceof \DOMElement
				&& 'true' === $stamp->getAttribute( 'aria-hidden' )
				&& 1 === $xp->query( '//svg[contains(@class,"cr-stamp")]' )->length
				&& str_contains( $out, '--cr-stamp-ink:' . $ink )
				&& str_contains( $out, 'cr-stamp-mark--tilt-' . $tilt )
				&& 0 === $nohost->query( '//p[contains(@class,"cr-bookmark-stream__host")]' )->length
				&& 0 === $nohost->query( '//span[contains(@class,"cr-bookmark-stream__stamp")]' )->length
				&& 1 === $xp->query( '//*[contains(concat(" ", @class, " "), " u-bookmark-of ")]' )->length
				&& ! str_contains( $out, 'target=' )
				&& 0 === $xp->query( '//img' )->length
				&& ! str_contains( $out, 'Worth rereading' );
		}
	);

	check_case(
		'enqueues bookmark stylesheet only on owned surfaces',
		static function (): bool {
			$cases = array(
				array( array( 'kind' => 'bookmark' ), true ),
				array( array( 'single' => true, 'queried_id' => 41 ), true ),
				array( array(), true, true ),
				array( array( 'admin' => true ), true ),
				array( array(), false ),
			);
			foreach ( $cases as $case ) {
				reset_state();
				bookmark_post();
				$GLOBALS['cr_request']        = $case[0];
				$GLOBALS['cr_stream_surface'] = (bool) ( $case[2] ?? false );
				\Courtneyr\Child\Bookmark\enqueue_styles();
				$has = isset( $GLOBALS['cr_styles']['courtneyr-bookmark'] );
				if ( $has !== $case[1] ) {
					return false;
				}
				if ( $has ) {
					$style = $GLOBALS['cr_styles']['courtneyr-bookmark'];
					if ( 'https://example.test/wp-content/themes/courtneyr-child/assets/css/cr-bookmark.css' !== $style[0] || array( 'courtneyr-paper' ) !== $style[1] || 'test-version' !== $style[2] ) {
						return false;
					}
				}
			}
			return true;
		}
	);

	check_case(
		'title_key normalizes visible text',
		static function (): bool {
			reset_state();
			bookmark_post( 41, 'fallback', full_facts( array( 'title' => " <b>X</b>&nbsp;\nY " ) ) );
			return 'x y' === \Courtneyr\Child\Bookmark\title_key( 41 );
		}
	);

	check_case(
		'facts wrapper returns empty array when no plugin facts exist',
		static function (): bool {
			reset_state();
			return array() === \Courtneyr\Child\Bookmark\facts( 999 );
		}
	);

	check_case(
		'no-plugin dispatch leaves html unchanged',
		static function (): bool {
			$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-plugin';
			exec( $cmd . ' 2>&1', $output, $status );
			return 0 === $status && in_array( 'NO_PLUGIN_OK', $output, true );
		}
	);

	printf( "TOTAL %d PASS %d FAIL %d\n", $case_count, $case_count - count( $failures ), count( $failures ) );
	exit( empty( $failures ) ? 0 : 1 );
}
