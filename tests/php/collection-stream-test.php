<?php
/**
 * Standalone collection card adapter checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );

class WP_Block {
	public $context;
	public function __construct( array $context ) { $this->context = $context; }
}

$GLOBALS['cr_collection_hooks'] = array();
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['cr_collection_hooks'][ $hook ][ $priority ][] = array( $callback, $accepted_args ); }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { add_action( $hook, $callback, $priority, $accepted_args ); }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function get_the_ID() { return 77; }

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
if ( ! cr_collection_load_html_api() ) {
	echo "SKIP HTML API unavailable; set CR_WP_CORE\n";
	exit( 2 );
}

require dirname( __DIR__, 2 ) . '/inc/stamps.php';
require dirname( __DIR__, 2 ) . '/inc/stream-collection.php';

$failed = 0;
function cr_collection_stream_check( bool $condition, string $message ): void {
	global $failed;
	echo ( $condition ? 'ok   ' : 'FAIL ' ) . $message . "\n";
	$failed += $condition ? 0 : 1;
}
function cr_collection_visible_text( string $html ): string {
	return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}
function cr_collection_listing( int $id, int $images ): string {
	$bay = '';
	for ( $i = 1; $i <= $images; $i++ ) {
		$bay .= '<img class="pk-collection__img" src="https://example.test/' . $id . '-' . $i . '.png" alt="old" loading="lazy" fetchpriority="low">';
	}
	return '<article class="pk-card pk-card--stream pk-card--collection pk-card--collection-listing k-collection h-entry"><div class="pk-collection__bay">' . $bay . '</div><h2 class="pk-title p-name"><a class="pk-collection__link u-url" href="https://example.test/c/' . $id . '">Cabinet ' . $id . '</a></h2></article>';
}

foreach ( array(
	'<article class="pk-card pk-card--jam k-jam">Jam</article>',
	'<article class="pk-card pk-card--wish k-wish">Wish</article>',
	'<article class="pk-card pk-card--collection pk-card--protected">Protected</article>',
	'<article class="pk-card pk-card--stream">Generic</article>',
) as $input ) {
	cr_collection_stream_check( $input === \Courtneyr\Child\StreamCollection\paint_card( $input ), 'unrelated or protected card stays byte-identical' );
}

\Courtneyr\Child\StreamCollection\reset_page_state();
$cards = array(
	\Courtneyr\Child\StreamCollection\paint_card( cr_collection_listing( 1, 2 ) ),
	\Courtneyr\Child\StreamCollection\paint_card( cr_collection_listing( 2, 0 ) ),
	\Courtneyr\Child\StreamCollection\paint_card( cr_collection_listing( 3, 1 ) ),
	\Courtneyr\Child\StreamCollection\paint_card( cr_collection_listing( 4, 1 ) ),
);
cr_collection_stream_check( 1 === substr_count( implode( '', $cards ), 'fetchpriority="high"' ), 'one image in the request has high fetch priority' );
cr_collection_stream_check( 3 === substr_count( implode( '', array_slice( $cards, 0, 3 ) ), 'loading="eager"' ), 'images in the first three cabinets are eager' );
cr_collection_stream_check( str_contains( $cards[3], 'loading="lazy"' ) && ! str_contains( $cards[3], 'fetchpriority=' ), 'fourth cabinet image is lazy without priority' );
cr_collection_stream_check( 4 === substr_count( implode( '', $cards ), 'alt=""' ), 'every listing image has empty alt text' );
foreach ( $cards as $index => $card ) {
	cr_collection_stream_check( str_contains( $card, 'cr-cabinet-plaque' ), 'listing ' . ( $index + 1 ) . ' receives the plaque class' );
	cr_collection_stream_check( cr_collection_visible_text( cr_collection_listing( $index + 1, array( 2, 0, 1, 1 )[ $index ] ) ) === cr_collection_visible_text( $card ), 'listing ' . ( $index + 1 ) . ' preserves visible text' );
}

$stream = '<article class="pk-card pk-card--stream pk-card--collection pk-card--collection-stream k-collection h-entry"><div class="pk-media"><img class="pk-cover pk-collection__newest-img" src="https://example.test/new.png" alt="old" loading="eager" fetchpriority="high"></div><span class="pk-kindlabel">Collection</span><h3 class="pk-title p-name"><a class="u-url" href="https://example.test/c/42">Field notes</a></h3><div class="pk-collection__newest"><p class="pk-collection__newest-label">Newest item</p><p class="pk-collection__newest-title">Old reel</p></div></article>';
$out = \Courtneyr\Child\StreamCollection\paint_card( $stream, array(), new WP_Block( array( 'postId' => 42 ) ) );
$again = \Courtneyr\Child\StreamCollection\paint_card( $stream, array(), new WP_Block( array( 'postId' => 42 ) ) );
cr_collection_stream_check( str_contains( $out, 'cr-collection-card cr-paper-sheet cr-paper-sheet--torn cr-paper--printer-ivory' ), 'Stream card receives the four paper classes' );
cr_collection_stream_check( 1 === preg_match( '/data-cr-rotate="[0-3]"/', $out ), 'Stream rotation is bounded' );
cr_collection_stream_check( $out === $again, 'Stream rotation is stable for a post ID' );
cr_collection_stream_check( 1 === preg_match( '/<div(?=[^>]*class="pk-media")(?=[^>]*data-cr-cover-fallback="true")[^>]*>/', $out ), 'failed-cover marker is on the media container' );
cr_collection_stream_check( str_contains( $out, 'alt="" loading="lazy"' ) && ! str_contains( $out, 'fetchpriority=' ), 'Stream image is decorative and lazy' );
cr_collection_stream_check( cr_collection_visible_text( $stream ) === cr_collection_visible_text( $out ), 'Stream painting preserves visible text' );
cr_collection_stream_check( '' === \Courtneyr\Child\StreamCollection\paint_card( '' ), 'empty markup stays empty' );

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit( $failed ? 1 : 0 );
