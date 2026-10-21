<?php
/**
 * Collection privacy boundary checks.
 *
 * Run the optional plugin pass with:
 * CR_PKIW_COLLECTION_ITEMS=<scratch>/plugin-class-collection-items.php php tests/php/collection-privacy-test.php
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );

	class WP_Block {
		public $context;
		public function __construct( array $context = array() ) { $this->context = $context; }
	}
	class WP_Post {
		public $ID;
		public $post_status;
		public $post_password;
		public $post_title;
		public function __construct( int $id, string $status, string $title, string $password = '' ) {
			$this->ID = $id;
			$this->post_status = $status;
			$this->post_title = $title;
			$this->post_password = $password;
		}
	}
	class WP_Block_Supports { public static $block_to_render = null; }

	$GLOBALS['cr_collection_hooks'] = array();
	$GLOBALS['cr_collection_calls'] = array();
	$GLOBALS['cr_collection_posts'] = array();
	$GLOBALS['cr_collection_thumbs'] = array();
	$GLOBALS['cr_collection_parents'] = array();

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['cr_collection_hooks'][ $hook ][ $priority ][] = array( $callback, $accepted_args ); }
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { add_action( $hook, $callback, $priority, $accepted_args ); }
	function apply_filters( $hook, $value, ...$args ) { return $value; }
	function __( $text, $domain = '' ) { return $text; }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
	function esc_url( $url ) { return str_starts_with( (string) $url, 'http' ) ? (string) $url : ''; }
	function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class ); }
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function get_block_wrapper_attributes( $attrs = array() ) { return 'class="' . esc_attr( $attrs['class'] ?? '' ) . '"'; }
	function get_the_ID() { $GLOBALS['cr_collection_calls'][] = 'get_the_ID'; return 240; }
	function get_post( $id ) { $GLOBALS['cr_collection_calls'][] = 'get_post'; return $GLOBALS['cr_collection_posts'][ (int) $id ] ?? null; }
	function get_post_field( $field, $id ) { $GLOBALS['cr_collection_calls'][] = 'get_post_field'; return 'Private secret title'; }
	function get_the_title( $post = null ) { $GLOBALS['cr_collection_calls'][] = 'get_the_title'; return $post instanceof WP_Post ? $post->post_title : 'Private secret title'; }
	function get_permalink( $post ) { $GLOBALS['cr_collection_calls'][] = 'get_permalink'; $id = $post instanceof WP_Post ? $post->ID : (int) $post; return 'https://example.test/post/' . $id; }
	function get_the_date( $format = '', $post = null ) { return '2026-10-01'; }
	function get_post_thumbnail_id( $post ) { $id = $post instanceof WP_Post ? $post->ID : (int) $post; return (int) ( $GLOBALS['cr_collection_thumbs'][ $id ] ?? 0 ); }
	function parse_blocks( $content ) { $GLOBALS['cr_collection_calls'][] = 'parse_blocks'; return array(); }
	function is_post_publicly_viewable( $post ) { return $post instanceof WP_Post && 'publish' === $post->post_status; }
	function wp_attachment_is_image( $id ) { return in_array( (int) $id, array( 101, 109 ), true ); }
	function wp_get_post_parent_id( $id ) { return (int) ( $GLOBALS['cr_collection_parents'][ (int) $id ] ?? 0 ); }
	function wp_get_attachment_image( $id, $size = '', $icon = false, $attrs = array() ) {
		$file = 109 === (int) $id ? 'seed-240-private-attachment.png' : 'public-image.png';
		return '<img class="' . esc_attr( $attrs['class'] ?? '' ) . '" src="https://example.test/uploads/' . $file . '" alt="' . esc_attr( $attrs['alt'] ?? '' ) . '">';
	}
	function _prime_post_caches( $ids, $terms = false, $meta = false ) {}
	function _n( $single, $plural, $number, $domain = '' ) { return 1 === (int) $number ? $single : $plural; }
	function number_format_i18n( $number ) { return (string) $number; }

	function cr_collection_load_html_api(): bool {
		$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
		$api = $core . '/wp-includes/html-api';
		if ( ! is_dir( $api ) ) { return false; }
		require_once $core . '/wp-includes/compat.php';
		require_once $core . '/wp-includes/utf8.php';
		require_once $core . '/wp-includes/kses.php';
		foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
			require_once $api . '/' . $file;
		}
		return class_exists( 'WP_HTML_Tag_Processor' );
	}

	function cr_collection_visible_text( string $html ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}
}

namespace PKIW {
	function flatten_blocks( array $blocks ): array {
		$flat = array();
		foreach ( $blocks as $block ) {
			$flat[] = $block;
			if ( ! empty( $block['innerBlocks'] ) ) { $flat = array_merge( $flat, flatten_blocks( $block['innerBlocks'] ) ); }
		}
		return $flat;
	}
	function untitled_name( \WP_Post $post, bool $from_content = true ): string { return 'Untitled'; }
	function card_calendar_date( string $raw ): array { return '' === $raw ? array( '', '' ) : array( substr( $raw, 0, 10 ), substr( $raw, 0, 10 ) ); }
	function web_url( string $url ): string { return str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' ) ? $url : ''; }
	function url_host( string $url ): string { return (string) parse_url( $url, PHP_URL_HOST ); }
	function attachment_alt( int $id ): string { return 'Public attachment'; }
}

namespace {
	function pkiw_get_safe_title( int $id ): string { return (string) ( $GLOBALS['cr_collection_posts'][ $id ]->post_title ?? '' ); }

	if ( ! cr_collection_load_html_api() ) {
		echo "SKIP HTML API unavailable; set CR_WP_CORE\n";
		exit( 2 );
	}
	require dirname( __DIR__, 2 ) . '/inc/stamps.php';
	require dirname( __DIR__, 2 ) . '/inc/collection.php';
	require dirname( __DIR__, 2 ) . '/inc/stream-collection.php';

	$failed = 0;
	function cr_collection_privacy_check( bool $condition, string $message ): void {
		global $failed;
		echo ( $condition ? 'ok   ' : 'FAIL ' ) . $message . "\n";
		$failed += $condition ? 0 : 1;
	}

	$root = dirname( __DIR__, 2 );
	$owned = array(
		$root . '/inc/collection.php', $root . '/inc/stream-collection.php',
		$root . '/patterns/cr-cabinet-hall-loop.php', $root . '/templates/taxonomy-kind-collection.html',
		$root . '/assets/css/cr-collection.css',
	);
	$source = implode( "\n", array_map( static fn( string $path ): string => (string) file_get_contents( $path ), $owned ) );
	foreach ( array( 'get_post(', 'get_post_field(', 'get_the_title(', 'get_permalink(', 'get_post_thumbnail_id(', 'wp_get_attachment_', 'parse_blocks(', 'Collection_Items', 'resolve_item', 'is_public_reference', '->items(', "['innerBlocks']", "['innerHTML']", 'content: attr(', 'url(' ) as $forbidden ) {
		cr_collection_privacy_check( false === strpos( $source, $forbidden ), 'owned source excludes ' . $forbidden );
	}

	$secrets = array( 'Private secret title', 'Draft secret title', 'Password secret title', 'Pending secret title', 'Trashed secret title', 'Check-in secret venue' );
	$block = array(
		'blockName' => 'core/group',
		'attrs' => array( 'className' => 'pkiw-collection', 'postId' => 2, 'mediaId' => 109, 'title' => implode( ' ', $secrets ), 'url' => 'https://private.example/secret' ),
		'innerBlocks' => array( array( 'blockName' => 'post-kinds-indieweb/collection-item', 'attrs' => array( 'postId' => 2, 'mediaId' => 109 ) ) ),
		'innerHTML' => implode( ' ', $secrets ),
	);
	$external = '<div class="wp-block-group pkiw-collection"><div class="pkiw-collection-item h-cite"><div class="pkiw-collection-item__label"><h2 class="pkiw-collection-item__title p-name"><a href="https://public.example/item">Visible external item</a></h2></div></div></div>';
	$listing = '<article class="pk-card pk-card--stream pk-card--collection pk-card--collection-listing k-collection h-entry"><div class="pk-collection__bay"></div><h2 class="pk-title p-name"><a class="pk-collection__link u-url" href="https://example.test/collection">Hidden references collection</a></h2></article>';
	$stream = '<article class="pk-card pk-card--stream pk-card--collection pk-card--collection-stream k-collection h-entry"><span class="pk-kindlabel">Collection</span><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/collection">Hidden references collection</a></h2></article>';
	$cases = array(
		array( '', \Courtneyr\Child\Collection\cabinet_default( '', $block ) ),
		array( $external, \Courtneyr\Child\Collection\cabinet_default( $external, $block ) ),
		array( $listing, \Courtneyr\Child\StreamCollection\paint_card( $listing, $block, new WP_Block( array( 'postId' => 240 ) ) ) ),
		array( $stream, \Courtneyr\Child\StreamCollection\paint_card( $stream, $block, new WP_Block( array( 'postId' => 240 ) ) ) ),
	);
	foreach ( $cases as $index => $case ) {
		cr_collection_privacy_check( cr_collection_visible_text( $case[0] ) === cr_collection_visible_text( $case[1] ), 'privacy transform ' . ( $index + 1 ) . ' preserves visible text' );
		foreach ( $secrets as $secret ) {
			cr_collection_privacy_check( false === strpos( $case[1], $secret ), 'privacy transform ' . ( $index + 1 ) . ' excludes ' . $secret );
		}
	}
	cr_collection_privacy_check( '' === $cases[0][1], 'all-hidden cabinet stays empty' );
	cr_collection_privacy_check( array() === $GLOBALS['cr_collection_calls'], 'theme transforms call no post-reading stub' );

	$plugin = getenv( 'CR_PKIW_COLLECTION_ITEMS' ) ?: '';
	if ( '' === $plugin ) {
		echo "SKIP real-plugin pass: CR_PKIW_COLLECTION_ITEMS is unset\n";
	} elseif ( ! is_readable( $plugin ) ) {
		cr_collection_privacy_check( false, 'real-plugin class file is readable' );
	} else {
		$GLOBALS['cr_collection_posts'] = array(
			1 => new WP_Post( 1, 'publish', 'Visible public title' ),
			2 => new WP_Post( 2, 'draft', 'Draft secret title' ),
			3 => new WP_Post( 3, 'private', 'Private secret title' ),
			4 => new WP_Post( 4, 'pending', 'Pending secret title' ),
			5 => new WP_Post( 5, 'trash', 'Trashed secret title' ),
			6 => new WP_Post( 6, 'publish', 'Password secret title', 'protected' ),
			7 => new WP_Post( 7, 'publish', 'Probe post' ),
		);
		$GLOBALS['cr_collection_thumbs'] = array( 1 => 101, 7 => 109 );
		$GLOBALS['cr_collection_parents'] = array( 101 => 0, 109 => 3 );
		require_once $plugin;

		$hidden_attrs = array_map( static fn( int $id ): array => array( 'postId' => $id ), array( 2, 3, 4, 5, 6, 999999 ) );
		$hidden_html = '';
		$hidden_blocks = array();
		foreach ( $hidden_attrs as $attrs ) {
			$hidden_html .= \PKIW\Collection_Items::render_item( $attrs );
			$hidden_blocks[] = array( 'blockName' => \PKIW\Collection_Items::ITEM_BLOCK, 'attrs' => $attrs, 'innerBlocks' => array() );
		}
		$hidden_group = '<div class="wp-block-group pkiw-collection">' . $hidden_html . '</div>';
		$dropped = \PKIW\Collection_Items::drop_empty_cabinet( $hidden_group, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'pkiw-collection' ), 'innerBlocks' => $hidden_blocks ) );
		cr_collection_privacy_check( '' === $dropped, 'real plugin drops an all-hidden cabinet' );

		$visible_attrs = array(
			array( 'postId' => 1 ),
			array( 'postId' => 7 ),
			array( 'title' => 'Leak probe external', 'mediaId' => 109, 'url' => 'https://public.example/probe' ),
		);
		$mixed = '';
		foreach ( array_merge( $hidden_attrs, $visible_attrs ) as $attrs ) {
			$mixed .= \PKIW\Collection_Items::render_item( $attrs );
		}
		$final = \Courtneyr\Child\Collection\cabinet_default( '<div class="wp-block-group pkiw-collection">' . $mixed . '</div>', array( 'attrs' => array( 'className' => 'pkiw-collection' ) ) );
		foreach ( $secrets as $secret ) {
			cr_collection_privacy_check( false === strpos( $final, $secret ), 'real plugin excludes ' . $secret );
		}
		cr_collection_privacy_check( false === strpos( $final, 'seed-240-private-attachment' ), 'real plugin excludes the private attachment URL' );
		cr_collection_privacy_check( str_contains( $final, 'Visible public title' ) && str_contains( $final, 'Probe post' ) && str_contains( $final, 'Leak probe external' ), 'real plugin keeps visible item text' );
		cr_collection_privacy_check( 1 === substr_count( $final, 'public-image.png' ), 'real plugin prints only the public attachment' );
	}

	echo $failed ? "\n$failed failed\n" : "\nall passed\n";
	exit( $failed ? 1 : 0 );
}
