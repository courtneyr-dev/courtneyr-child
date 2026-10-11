<?php
/**
 * `php tests/php/bookmark-single-test.php`: checks bookmark single index cards.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test-version' );
	define( 'CR_BM_SINGLE_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_hooks']      = array();
	$GLOBALS['cr_request']    = array();
	$GLOBALS['cr_posts']      = array();
	$GLOBALS['cr_terms']      = array();
	$GLOBALS['cr_kind_facts'] = array();

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

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
		return true;
	}

	function is_singular( $post_type = '' ) {
		return ! empty( $GLOBALS['cr_request']['single'] ) && ( '' === $post_type || 'post' === $post_type );
	}

	function get_queried_object_id() {
		return (int) ( $GLOBALS['cr_request']['queried_id'] ?? 0 );
	}

	function has_term( $term, $taxonomy, $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'kind' === $taxonomy && 'bookmark' === $term && ! empty( $GLOBALS['cr_terms'][ $id ] );
	}

	function post_password_required() {
		return ! empty( $GLOBALS['cr_request']['protected'] );
	}

	function get_post( $post = null ) {
		$id = null === $post ? get_queried_object_id() : ( $post instanceof WP_Post ? $post->ID : (int) $post );
		return $GLOBALS['cr_posts'][ $id ] ?? null;
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

	function wp_get_attachment_image( $attachment_id, $size, $icon = false, $attr = array() ) {
		$attrs = '';
		foreach ( $attr as $key => $value ) {
			$attrs .= ' ' . $key . '="' . esc_attr( (string) $value ) . '"';
		}
		return '<img data-attachment-id="' . (int) $attachment_id . '"' . $attrs . ' src="attachment-' . (int) $attachment_id . '.jpg">';
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_kind_facts'][ $post_id ] ?? array() );
	}

	function get_kind_label( string $label, string $kind, string $context ): string {
		return $GLOBALS['cr_kind_label'] ?? $label;
	}

	function get_kind_icon_svg( string $kind ): string {
		return '<svg class="stub-bookmark"></svg>';
	}

	function pkiw_new_tab_label_attr( string $visible_text ): string {
		return ' aria-label="' . \esc_attr( $visible_text . ' (opens in a new tab)' ) . '"';
	}
}

namespace {
	$single_path = CR_BM_SINGLE_THEME_DIR . '/inc/single-bookmark.php';
	if ( is_file( $single_path ) ) {
		require $single_path;
	}
}

namespace Courtneyr\Child\SingleBookmark\Tests {
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
		$GLOBALS['cr_request']    = array();
		$GLOBALS['cr_posts']      = array();
		$GLOBALS['cr_terms']      = array();
		$GLOBALS['cr_kind_facts'] = array();
		unset( $GLOBALS['cr_kind_label'] );
		if ( function_exists( 'Courtneyr\\Child\\SingleBookmark\\in_content' ) ) {
			\Courtneyr\Child\SingleBookmark\in_content( false );
		}
	}

	function hostile( string $name ): string {
		return '"><img src=x onerror=1 data-sink=' . $name . '>';
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

	function bookmark_single( array $facts = array(), string $title = 'Release notes', bool $bookmark = true, bool $protected = false ): \WP_Post {
		$post                       = new \WP_Post( 41, $title );
		$GLOBALS['cr_posts'][41]    = $post;
		$GLOBALS['cr_terms'][41]    = $bookmark;
		$GLOBALS['cr_kind_facts'][41] = $facts ? $facts : full_facts();
		$GLOBALS['cr_request']      = array( 'single' => true, 'queried_id' => 41 );
		if ( $protected ) {
			$GLOBALS['cr_request']['protected'] = true;
		}
		if ( function_exists( 'Courtneyr\\Child\\SingleBookmark\\single_facts' ) ) {
			\Courtneyr\Child\SingleBookmark\single_facts( true );
		}
		return $post;
	}

	function dom( string $html ): \DOMXPath {
		$doc = new \DOMDocument();
		$doc->loadHTML( '<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING );
		return new \DOMXPath( $doc );
	}

	function norm( string $text ): string {
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	function text_of( \DOMXPath $xp, string $query ): string {
		$node = $xp->query( $query )->item( 0 );
		return $node ? norm( $node->textContent ) : '';
	}

	function classes( ?\DOMNode $node ): array {
		return $node instanceof \DOMElement ? preg_split( '/\s+/', $node->getAttribute( 'class' ) ) : array();
	}

	function dt_sequence( string $html ): array {
		$xp = dom( $html );
		$out = array();
		foreach ( $xp->query( '//dt' ) as $dt ) {
			$out[] = norm( $dt->textContent );
		}
		return $out;
	}

	check_case(
		'registers the single bookmark filters',
		static function (): bool {
			$hooks = $GLOBALS['cr_hooks'];
			return 2 === ( $hooks['pre_render_block'][10][0][1] ?? null )
				&& 2 === ( $hooks['render_block_post-kinds-indieweb/bookmark-card'][10][0][1] ?? null )
				&& 2 === ( $hooks['render_block_core/paragraph'][10][0][1] ?? null )
				&& 2 === ( $hooks['render_block_core/post-featured-image'][10][0][1] ?? null )
				&& 2 === ( $hooks['render_block_core/post-content'][10][0][1] ?? null );
		}
	);

	check_case(
		'prepend_card prints the full single index card before content',
		static function (): bool {
			reset_state();
			bookmark_single();
			\Courtneyr\Child\SingleBookmark\set_content_flag( null, array( 'blockName' => 'core/post-content' ) );
			$out = \Courtneyr\Child\SingleBookmark\prepend_card( '<p>After</p>', array( 'blockName' => 'core/post-content' ) );
			$xp  = dom( $out );
			$article = $xp->query( '//article' )->item( 0 );
			$source  = $xp->query( '//div[contains(@class,"cr-index-card__row--source")]//a' )->item( 0 );
			return str_starts_with( $out, '<div class="cr-bookmark-single">' )
				&& str_ends_with( $out, '<p>After</p>' )
				&& 'Alpha' === text_of( $xp, '//p[contains(@class,"cr-bookmark-single__tab")]' )
				&& 0 === $xp->query( '//p[contains(@class,"cr-bookmark-single__tab")]//a' )->length
				&& in_array( 'cr-index-card', classes( $article ), true )
				&& in_array( 'cr-index-card--single', classes( $article ), true )
				&& in_array( 'cr-paper-sheet', classes( $article ), true )
				&& in_array( 'cr-paper--printer-ivory', classes( $article ), true )
				&& 'Bookmark' === text_of( $xp, '//p[contains(@class,"cr-index-card__label")]' )
				&& 'true' === $xp->query( '//span[contains(@class,"cr-index-card__glyph")]' )->item( 0 )->getAttribute( 'aria-hidden' )
				&& array( 'Source', 'Author', 'Date filed', 'Note' ) === dt_sequence( $out )
				&& $source instanceof \DOMElement
				&& 'https://example.com/p?q=1' === $source->getAttribute( 'href' )
				&& '_blank' === $source->getAttribute( 'target' )
				&& 'noopener noreferrer nofollow' === $source->getAttribute( 'rel' )
				&& 'example.com (opens in a new tab)' === $source->getAttribute( 'aria-label' )
				&& 'example.com' === norm( $source->textContent )
				&& 1 === $xp->query( '//article//a' )->length
				&& 'July 4, 2026' === text_of( $xp, '//time[@datetime="2026-07-04"]' )
				&& str_contains( $out, 'Worth rereading.<br />' )
				&& str_contains( $out, 'Second line.' )
				&& 'Accessible drawers, explained' === text_of( $xp, '//p[contains(@class,"cr-index-card__cited")]' )
				&& ! preg_match( '/h-cite|u-bookmark-of|u-url|p-name|dt-published|h-entry|<data|<h1|<h2|<h3|figcaption/', $out );
		}
	);

	check_case(
		'optional author and note rows collapse without reordering',
		static function (): bool {
			reset_state();
			bookmark_single( full_facts( array( 'author' => '' ) ) );
			$a = dt_sequence( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			bookmark_single( full_facts( array( 'note' => '' ) ) );
			$b = dt_sequence( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			bookmark_single( full_facts( array( 'author' => '', 'note' => '' ) ) );
			$c = dt_sequence( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			return array( 'Source', 'Date filed', 'Note' ) === $a
				&& array( 'Source', 'Author', 'Date filed' ) === $b
				&& array( 'Source', 'Date filed' ) === $c;
		}
	);

	check_case(
		'cited title prints only when it differs from the stored title',
		static function (): bool {
			reset_state();
			bookmark_single( full_facts( array( 'cited_title' => ' release NOTES ' ) ), 'Release notes' );
			$same = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) )->query( '//p[contains(@class,"cr-index-card__cited")]' )->length;
			bookmark_single( full_facts( array( 'cited_title' => 'Pocket Guide  To Small Sites' ) ), 'Pocket guide to small sites' );
			$spaced = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			bookmark_single( full_facts( array( 'cited_title' => 'Accessible drawers, explained' ) ), '' );
			$empty = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) )->query( '//p[contains(@class,"cr-index-card__cited")]' )->length;
			bookmark_single( full_facts( array( 'cited_title' => '' ) ), 'Release notes' );
			$none = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) )->query( '//p[contains(@class,"cr-index-card__cited")]' )->length;
			return 0 === $same && 1 === $empty && 0 === $none
				&& 'Pocket Guide  To Small Sites' === $spaced->query( '//p[contains(@class,"cr-index-card__cited")]' )->item( 0 )?->textContent;
		}
	);

	check_case(
		'no url removes the Source row and all anchors',
		static function (): bool {
			reset_state();
			bookmark_single( full_facts( array( 'url' => '', 'host' => '' ) ) );
			$xp = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			return 0 === $xp->query( '//div[contains(@class,"cr-index-card__row--source")]' )->length
				&& 0 === $xp->query( '//article//a' )->length;
		}
	);

	check_case(
		'picture markup uses remote or attachment image with verbatim alt',
		static function (): bool {
			reset_state();
			bookmark_single();
			$remote = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			bookmark_single( full_facts( array( 'picture' => array( 'source' => 'featured', 'attachment_id' => 0, 'url' => 'https://img.example/f.jpg', 'alt' => '', 'remote' => true, 'suppress_featured' => true ) ) ) );
			$empty_alt = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			bookmark_single( full_facts( array( 'picture' => array( 'source' => 'featured', 'attachment_id' => 77, 'url' => 'https://img.example/f.jpg', 'alt' => 'A filing cabinet', 'remote' => false, 'suppress_featured' => true ) ) ) );
			$attach = \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) );
			bookmark_single( full_facts( array( 'picture' => array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => '', 'remote' => false, 'suppress_featured' => false ) ) ) );
			$none = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			return 1 === $remote->query( '//figure[contains(@class,"cr-index-card__picture") and @data-cr-cover-fallback]//img' )->length
				&& 'A filing cabinet' === $remote->query( '//figure//img' )->item( 0 )->getAttribute( 'alt' )
				&& '' === $empty_alt->query( '//figure//img' )->item( 0 )->getAttribute( 'alt' )
				&& str_contains( $attach, 'data-attachment-id="77"' )
				&& 0 === $none->query( '//figure[contains(@class,"cr-index-card__picture")]' )->length;
		}
	);

	check_case(
		'rel_tokens keeps safe lowercase tokens after noopener noreferrer',
		static function (): bool {
			return 'noopener noreferrer' === \Courtneyr\Child\SingleBookmark\rel_tokens( '' )
				&& 'noopener noreferrer nofollow ugc' === \Courtneyr\Child\SingleBookmark\rel_tokens( 'nofollow ugc nofollow NOOPENER' )
				&& 'noopener noreferrer sponsored' === \Courtneyr\Child\SingleBookmark\rel_tokens( 'javascript:alert(1) " onclick=x sponsored' );
		}
	);

	check_case(
		'card escapes author and note markup',
		static function (): bool {
			reset_state();
			bookmark_single( full_facts( array( 'author' => '<script>x</script>', 'note' => '<b>bold</b>' ) ) );
			$out = \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) );
			return ! str_contains( $out, '<script>' )
				&& str_contains( $out, '&lt;script&gt;x&lt;/script&gt;' )
				&& str_contains( $out, '&lt;b&gt;bold&lt;/b&gt;' );
		}
	);

	check_case(
		'single card escapes cited title, drawer, source, date, label, picture alt and picture url',
		static function (): bool {
			reset_state();
			$url       = hostile( 'url' );
			$cited     = hostile( 'cited' );
			$drawer    = hostile( 'drawer' );
			$date_attr = hostile( 'date-attr' );
			$date_text = hostile( 'date-text' );
			$label     = hostile( 'label' );
			$alt       = hostile( 'alt' );
			$srchost   = hostile( 'srchost' );
			$picurl    = hostile( 'picurl' );
			bookmark_single(
				full_facts(
					array(
						'url'         => 'https://example.com/p?q=' . $url,
						'host'        => $srchost,
						'cited_title' => $cited,
						'date'        => array( $date_attr, $date_text ),
						'drawer'      => array( 'key' => 'alpha', 'label' => $drawer ),
						'picture'     => array( 'source' => 'featured', 'attachment_id' => 0, 'url' => 'https://img.example/f.jpg?x=' . $picurl, 'alt' => $alt, 'remote' => true, 'suppress_featured' => true ),
					)
				),
				'Release notes'
			);
			$GLOBALS['cr_kind_label'] = $label;
			$xp          = dom( \Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) ) );
			$source      = $xp->query( '//div[contains(@class,"cr-index-card__row--source")]//a' )->item( 0 );
			$cited_node  = $xp->query( '//p[contains(@class,"cr-index-card__cited")]' )->item( 0 );
			$drawer_node = $xp->query( '//p[contains(@class,"cr-bookmark-single__tab")]' )->item( 0 );
			$time        = $xp->query( '//time' )->item( 0 );
			$img         = $xp->query( '//figure//img' )->item( 0 );
			$kind        = $xp->query( '//span[contains(@class,"cr-index-card__kind")]' )->item( 0 );
			return $source instanceof \DOMElement
				&& $cited_node instanceof \DOMElement
				&& $drawer_node instanceof \DOMElement
				&& $time instanceof \DOMElement
				&& $img instanceof \DOMElement
				&& $kind instanceof \DOMElement
				&& 0 === $xp->query( '//*[@onerror]' )->length
				&& 0 === $xp->query( '//*[@data-sink]' )->length
				&& 1 === $xp->query( '//img' )->length
				&& 'https://img.example/f.jpg?x=' . $picurl === $img->getAttribute( 'src' )
				&& 'https://example.com/p?q=' . $url === $source->getAttribute( 'href' )
				&& $srchost === $source->textContent
				&& $cited === $cited_node->textContent
				&& $drawer === $drawer_node->textContent
				&& $date_attr === $time->getAttribute( 'datetime' )
				&& $date_text === $time->textContent
				&& $alt === $img->getAttribute( 'alt' )
				&& $label === $kind->textContent;
		}
	);

	check_case(
		'drop_duplicate_note removes only duplicated normalized note paragraphs while in content',
		static function (): bool {
			reset_state();
			bookmark_single( full_facts( array( 'note' => 'Great article.' ) ) );
			\Courtneyr\Child\SingleBookmark\in_content( true );
			$drop = array( '<p>Great article.</p>', '<p> great   ARTICLE. </p>', '<p>Great&nbsp;article.</p>' );
			foreach ( $drop as $html ) {
				if ( '' !== \Courtneyr\Child\SingleBookmark\drop_duplicate_note( $html, array( 'blockName' => 'core/paragraph' ) ) ) {
					return false;
				}
			}
			if ( '<p>Something else</p>' !== \Courtneyr\Child\SingleBookmark\drop_duplicate_note( '<p>Something else</p>', array() ) ) {
				return false;
			}
			if ( '<p>Great article. And more.</p>' !== \Courtneyr\Child\SingleBookmark\drop_duplicate_note( '<p>Great article. And more.</p>', array() ) ) {
				return false;
			}
			bookmark_single( full_facts( array( 'note' => '' ) ) );
			if ( '<p>Great article.</p>' !== \Courtneyr\Child\SingleBookmark\drop_duplicate_note( '<p>Great article.</p>', array() ) ) {
				return false;
			}
			\Courtneyr\Child\SingleBookmark\in_content( false );
			return '<p>Great article.</p>' === \Courtneyr\Child\SingleBookmark\drop_duplicate_note( '<p>Great article.</p>', array() );
		}
	);

	check_case(
		'content flag controls card dropping and is cleared by prepend_card',
		static function (): bool {
			reset_state();
			bookmark_single();
			\Courtneyr\Child\SingleBookmark\in_content( true );
			if ( '' !== \Courtneyr\Child\SingleBookmark\drop_card_block( '<div>card</div>', array() ) ) {
				return false;
			}
			\Courtneyr\Child\SingleBookmark\in_content( false );
			if ( '<div>card</div>' !== \Courtneyr\Child\SingleBookmark\drop_card_block( '<div>card</div>', array() ) ) {
				return false;
			}
			if ( 'pre' !== \Courtneyr\Child\SingleBookmark\set_content_flag( 'pre', array( 'blockName' => 'core/post-content' ) ) || ! \Courtneyr\Child\SingleBookmark\in_content() ) {
				return false;
			}
			\Courtneyr\Child\SingleBookmark\prepend_card( '', array( 'blockName' => 'core/post-content' ) );
			if ( \Courtneyr\Child\SingleBookmark\in_content() ) {
				return false;
			}
			\Courtneyr\Child\SingleBookmark\set_content_flag( null, array( 'blockName' => 'core/image' ) );
			if ( \Courtneyr\Child\SingleBookmark\in_content() ) {
				return false;
			}
			bookmark_single( array(), 'Release notes', true, true );
			\Courtneyr\Child\SingleBookmark\set_content_flag( null, array( 'blockName' => 'core/post-content' ) );
			return ! \Courtneyr\Child\SingleBookmark\in_content();
		}
	);

	check_case(
		'drop_featured_image runs only on visible bookmark singles',
		static function (): bool {
			reset_state();
			bookmark_single();
			if ( '' !== \Courtneyr\Child\SingleBookmark\drop_featured_image( '<figure>featured</figure>', array( 'blockName' => 'core/post-featured-image' ) ) ) {
				return false;
			}
			bookmark_single( full_facts(), 'Release notes', false );
			if ( '<figure>featured</figure>' !== \Courtneyr\Child\SingleBookmark\drop_featured_image( '<figure>featured</figure>', array( 'blockName' => 'core/post-featured-image' ) ) ) {
				return false;
			}
			bookmark_single( array(), 'Release notes', true, true );
			if ( '<figure>featured</figure>' !== \Courtneyr\Child\SingleBookmark\drop_featured_image( '<figure>featured</figure>', array( 'blockName' => 'core/post-featured-image' ) ) ) {
				return false;
			}
			$GLOBALS['cr_request'] = array();
			return '<figure>featured</figure>' === \Courtneyr\Child\SingleBookmark\drop_featured_image( '<figure>featured</figure>', array( 'blockName' => 'core/post-featured-image' ) );
		}
	);

	check_case(
		'protected singles leave content and duplicate blocks untouched',
		static function (): bool {
			reset_state();
			bookmark_single( array(), 'Release notes', true, true );
			return '<p>After</p>' === \Courtneyr\Child\SingleBookmark\prepend_card( '<p>After</p>', array( 'blockName' => 'core/post-content' ) )
				&& ! \Courtneyr\Child\SingleBookmark\in_content()
				&& '<div>card</div>' === \Courtneyr\Child\SingleBookmark\drop_card_block( '<div>card</div>', array() )
				&& '<p>note</p>' === \Courtneyr\Child\SingleBookmark\drop_duplicate_note( '<p>note</p>', array() );
		}
	);

	check_case(
		'single bookmark file is self-contained',
		static function (): bool {
			return function_exists( 'Courtneyr\\Child\\SingleBookmark\\index_card' )
				&& ! function_exists( 'Courtneyr\\Child\\Bookmark\\dispatch' );
		}
	);

	printf( "TOTAL %d PASS %d FAIL %d\n", $case_count, $case_count - count( $failures ), count( $failures ) );
	exit( empty( $failures ) ? 0 : 1 );
}
