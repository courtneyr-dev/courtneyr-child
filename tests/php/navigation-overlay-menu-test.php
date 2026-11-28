<?php
/**
 * `CR_WP_SITE=<root> CR_WP_URL=<url> php tests/php/navigation-overlay-menu-test.php`:
 * the overlay part's Navigation shows the header's menu, and nothing else
 * changes, on a real WordPress.
 *
 * CR_WP_SITE is a WordPress multisite root whose main site runs this theme as
 * `courtneyr-child`; site 2 runs another theme. CR_WP_URL is the main site's
 * URL. Everything the test writes is rolled back. Exits 2 without a site.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$site = (string) getenv( 'CR_WP_SITE' );
$url  = (string) getenv( 'CR_WP_URL' );
if ( '' === $site || '' === $url || ! is_file( $site . '/wp-load.php' ) ) {
	echo "SKIP set CR_WP_SITE to a multisite WordPress root running this theme and CR_WP_URL to its URL\n";
	exit( 2 );
}

$_SERVER['HTTP_HOST']   = (string) parse_url( $url, PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = (string) ( parse_url( $url, PHP_URL_PATH ) ?: '/' );
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
define( 'WP_USE_THEMES', false );
require $site . '/wp-load.php';

$failures = array();

/** Print one result and remember failures. */
function check( bool $ok, string $message ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
	if ( ! $ok ) {
		$failures[] = $message;
	}
}

if ( ! is_multisite() || 'courtneyr-child' !== get_option( 'stylesheet' ) || ! get_site( 2 ) ) {
	echo "FAIL the site must be a multisite whose main site runs courtneyr-child, with a site 2\n";
	exit( 1 );
}

$GLOBALS['wpdb']->query( 'START TRANSACTION' );
register_shutdown_function( static fn() => $GLOBALS['wpdb']->query( 'ROLLBACK' ) );
$admin = get_user_by( 'login', get_super_admins()[0] ?? '' );
wp_set_current_user( $admin ? $admin->ID : 1 );

/** A link block. */
function link_block( string $label ): string {
	return '<!-- wp:navigation-link {"label":"' . $label . '","url":"/' . sanitize_title( $label ) . '/","kind":"custom"} /-->';
}

/** A published wp_navigation post. */
function menu( string $title, array $labels, string $date ): int {
	return (int) wp_insert_post(
		array(
			'post_type'     => 'wp_navigation',
			'post_status'   => 'publish',
			'post_title'    => $title,
			'post_content'  => implode( '', array_map( 'link_block', $labels ) ),
			'post_date'     => $date,
			'post_date_gmt' => $date,
		)
	);
}

/** Save a template part for a theme through the REST API, as the Site Editor does. */
function save_part( string $theme, string $slug, string $content, string $area ): WP_REST_Response {
	$id      = $theme . '//' . $slug;
	$exists  = get_block_template( $id, 'wp_template_part' );
	$request = $exists
		? new WP_REST_Request( 'PUT', '/wp/v2/template-parts/' . $id )
		: new WP_REST_Request( 'POST', '/wp/v2/template-parts' );
	$request->set_body_params( $exists ? array( 'content' => $content ) : array( 'slug' => $slug, 'theme' => $theme, 'area' => $area, 'content' => $content, 'title' => $slug ) );
	return rest_do_request( $request );
}

/** The theme's header part with a menu id on its Navigation. */
function header_with( int $ref ): string {
	$header = (string) file_get_contents( dirname( __DIR__, 2 ) . '/parts/header.html' );
	return (string) preg_replace( '/<!-- wp:navigation \{/', '<!-- wp:navigation {"ref":' . $ref . ',', $header, 1 );
}

/** Rendered header part: [ header items, overlay items ] as link texts. */
function rendered_header(): array {
	$html  = do_blocks( '<!-- wp:template-part {"slug":"header","theme":"courtneyr-child","tagName":"header"} /-->' );
	$doc   = \Dom\HTMLDocument::createFromString( '<!doctype html><body>' . $html, LIBXML_NOERROR );
	$texts = static fn( iterable $nodes ) => array_values( array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( $nodes ) ) );
	$items = array_filter( iterator_to_array( $doc->querySelectorAll( '.site-header__nav .wp-block-navigation-item__content' ) ), static fn( $n ) => null === $n->closest( '.cr-nav-overlay' ) );
	return array( $texts( $items ), $texts( $doc->querySelectorAll( '.cr-nav-overlay__nav .wp-block-navigation-item__content' ) ) );
}

/** Link texts a lone block renders. */
function rendered_items( string $markup ): array {
	$doc = \Dom\HTMLDocument::createFromString( '<!doctype html><body>' . do_blocks( $markup ), LIBXML_NOERROR );
	return array_values( array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( $doc->querySelectorAll( '.wp-block-navigation-item__content' ) ) ) );
}

/** The fallback endpoint's answer: [ status, menu id ]. */
function fallback_endpoint(): array {
	$response = rest_do_request( new WP_REST_Request( 'GET', '/wp-block-editor/v1/navigation-fallback' ) );
	return array( $response->get_status(), $response->get_data()['id'] ?? null );
}

/** The `ref` on the saved overlay part's Navigation, or null. */
function saved_overlay_ref(): ?int {
	$part = get_block_template( 'courtneyr-child//navigation-overlay', 'wp_template_part' );
	preg_match( '/<!-- wp:navigation (\{.*?\}) \/-->/', (string) $part->content, $m );
	$attrs = isset( $m[1] ) ? json_decode( $m[1], true ) : array();
	return isset( $attrs['ref'] ) ? (int) $attrs['ref'] : null;
}

$root = dirname( __DIR__, 2 );
check( str_contains( (string) file_get_contents( $root . '/functions.php' ), "require_once __DIR__ . '/inc/navigation-overlay.php';" ), 'functions.php loads inc/navigation-overlay.php' );

$now   = current_time( 'mysql', true );
$alpha = array( 'Alpha one', 'Alpha two' );
$bravo = array( 'Bravo one', 'Bravo two', 'Bravo three' );
$a     = menu( 'Menu A', $alpha, gmdate( 'Y-m-d H:i:s', strtotime( $now ) - 3 * DAY_IN_SECONDS ) );
$e     = menu( 'Menu E (empty)', array(), gmdate( 'Y-m-d H:i:s', strtotime( $now ) - 2 * DAY_IN_SECONDS ) );
$b     = menu( 'Menu B (newest)', $bravo, $now );
check( $bravo === rendered_items( '<!-- wp:navigation /-->' ), 'fixture: core\'s fallback is the newest menu, B' );

save_part( 'courtneyr-child', 'header', header_with( $a ), 'header' );
[ $header, $overlay ] = rendered_header();
check( $alpha === $header && $alpha === $overlay, 'header picks A: the header and the overlay both show A' );

// (a) Core's fallback stays core's.
check( $bravo === rendered_items( '<!-- wp:navigation /-->' ), '(a) a Navigation outside the overlay with no ref keeps core\'s newest-menu fallback, B' );
check( array( 200, $b ) === fallback_endpoint(), '(a) /wp-block-editor/v1/navigation-fallback returns core\'s answer, B' );

// (b) Save the overlay with A, then pick B in the header.
$part    = (string) file_get_contents( $root . '/parts/navigation-overlay.html' );
$saved   = save_part( 'courtneyr-child', 'navigation-overlay', str_replace( '<!-- wp:navigation {', '<!-- wp:navigation {"ref":' . $a . ',', $part ), 'navigation-overlay' );
check( $saved->get_status() < 300 && null === saved_overlay_ref(), '(b) saving the overlay part keeps no menu id on its Navigation' );
save_part( 'courtneyr-child', 'header', header_with( $b ), 'header' );
[ $header, $overlay ] = rendered_header();
check( $bravo === $header && $bravo === $overlay, '(b) after the header switches to B, the saved overlay shows B like the header' );

// A part saved earlier with a stale menu id still follows the header.
$stale = get_block_template( 'courtneyr-child//navigation-overlay', 'wp_template_part' );
$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_content' => str_replace( '<!-- wp:navigation {', '<!-- wp:navigation {"ref":' . $a . ',', $part ) ), array( 'ID' => $stale->wp_id ) );
clean_post_cache( $stale->wp_id );
[ $header, $overlay ] = rendered_header();
check( $bravo === $overlay, '(b) an overlay part stored with ref A before this change shows the header\'s B' );

// (c) An empty menu in the header, with the overlay part back to the theme file.
wp_delete_post( $stale->wp_id, true );
save_part( 'courtneyr-child', 'header', header_with( $e ), 'header' );
[ $header, $overlay ] = rendered_header();
check( $header === $overlay, '(c) header picks the empty menu: the overlay renders what the header renders (' . ( $header ? implode( ', ', $header ) : 'no items' ) . '), not ' . ( $overlay ? implode( ', ', $overlay ) : 'something else' ) );

// (e) A plugin's refusal of the fallback endpoint stays a refusal.
$deny = static fn( $response, $handler, $request ) => '/wp-block-editor/v1/navigation-fallback' === $request->get_route() ? new WP_REST_Response( array( 'code' => 'blocked' ), 403 ) : $response;
add_filter( 'rest_request_after_callbacks', $deny, 5, 3 );
save_part( 'courtneyr-child', 'header', header_with( $a ), 'header' );
check( 403 === fallback_endpoint()[0], '(e) a plugin\'s 403 from the fallback endpoint stays 403' );
remove_filter( 'rest_request_after_callbacks', $deny, 5 );

// Site Editor data.
$enqueue = 'Courtneyr\\Child\\NavigationOverlay\\enqueue_editor';
if ( function_exists( $enqueue ) ) {
	$enqueue();
}
$inline = implode( '', (array) wp_scripts()->get_data( 'courtneyr-navigation-overlay-editor', 'before' ) );
check( str_contains( $inline, '"headerMenuId":' . $a ), 'Site Editor: the overlay editor script gets the header\'s menu id, A' );

// (d) Another site with another theme.
switch_to_blog( 2 );
$other = get_option( 'stylesheet' );
$x     = menu( 'Menu X', array( 'Xray one' ), gmdate( 'Y-m-d H:i:s', strtotime( $now ) - DAY_IN_SECONDS ) );
$y     = menu( 'Menu Y (newest)', array( 'Yankee one', 'Yankee two' ), $now );
save_part( $other, 'header', '<!-- wp:navigation {"ref":' . $x . ',"overlay":"navigation-overlay"} /-->', 'header' );
$marked = '<!-- wp:navigation {"className":"cr-nav-overlay__nav"} /-->';
check( array( 'Yankee one', 'Yankee two' ) === rendered_items( '<!-- wp:navigation /-->' ), '(d) switch_to_blog to ' . $other . ': a Navigation with no ref keeps that site\'s newest menu, Y' );
check( array( 'Yankee one', 'Yankee two' ) === rendered_items( $marked ), '(d) switch_to_blog: a Navigation with the overlay class is left to core too' );
check( array( 200, $y ) === fallback_endpoint(), '(d) switch_to_blog: the fallback endpoint returns that site\'s answer, Y' );
save_part( $other, 'kept', '<!-- wp:navigation {"ref":' . $x . ',"className":"cr-nav-overlay__nav"} /-->', 'uncategorized' );
check( str_contains( (string) get_block_template( $other . '//kept', 'wp_template_part' )->content, '"ref":' . $x ), '(d) switch_to_blog: saving a part there keeps its ref' );
restore_current_blog();

if ( $failures ) {
	echo count( $failures ), " failed\n";
	exit( 1 );
}
echo "all passed\n";
