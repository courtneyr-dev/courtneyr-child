<?php
/**
 * `CR_WP_SITE=<root> CR_WP_URL=<url> php tests/php/native-breadcrumbs-test.php`:
 * on templates/single.html a Stream post with a kind gets core's Breadcrumbs
 * trail (Home, Stream, its kind, its title) as the page's only breadcrumb
 * landmark, and every other post keeps Yoast's.
 *
 * CR_WP_SITE is a WordPress root whose main site runs this theme as
 * `courtneyr-child` with Post Kinds for IndieWeb active (the `kind`
 * taxonomy). CR_WP_URL is that site's URL. Everything the test writes is
 * rolled back. When Yoast SEO isn't active, a stand-in yoast_breadcrumb()
 * prints a one-item trail so the hand-off between the two trails is still
 * checked. Exits 2 without a site.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$site = (string) getenv( 'CR_WP_SITE' );
$url  = (string) getenv( 'CR_WP_URL' );
if ( '' === $site || '' === $url || ! is_file( $site . '/wp-load.php' ) ) {
	echo "SKIP set CR_WP_SITE to a WordPress root running this theme and CR_WP_URL to its URL\n";
	exit( 2 );
}

$_SERVER['HTTP_HOST']   = (string) parse_url( $url, PHP_URL_HOST ) . ( parse_url( $url, PHP_URL_PORT ) ? ':' . parse_url( $url, PHP_URL_PORT ) : '' );
$_SERVER['REQUEST_URI'] = (string) ( parse_url( $url, PHP_URL_PATH ) ?: '/' );
$_SERVER['SERVER_NAME'] = (string) parse_url( $url, PHP_URL_HOST );
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

if ( 'courtneyr-child' !== get_option( 'stylesheet' ) || ! taxonomy_exists( 'kind' ) ) {
	echo "FAIL the site must run courtneyr-child with Post Kinds for IndieWeb active\n";
	exit( 1 );
}

$yoast = function_exists( 'yoast_breadcrumb' );
if ( ! $yoast ) {
	/** Stand-in for Yoast SEO's template tag: one current item. */
	function yoast_breadcrumb( string $before = '', string $after = '', bool $display = true ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '<span><span class="breadcrumb_last" aria-current="page">' . esc_html( get_the_title() ) . '</span></span>';
	}
}
echo 'note Yoast SEO ', $yoast ? 'active' : 'inactive; using a stand-in yoast_breadcrumb()', ', WordPress ', get_bloginfo( 'version' ), "\n";

$GLOBALS['wpdb']->query( 'START TRANSACTION' );
register_shutdown_function( static fn() => $GLOBALS['wpdb']->query( 'ROLLBACK' ) );
wp_set_current_user( 0 );

$root = dirname( __DIR__, 2 );

/** A published post with a kind on a surface, its kind set after Post Kinds' save hooks. */
function entry( string $title, string $kind, string $surface, string $date = '' ): int {
	$args = array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => '<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->',
	);
	if ( '' !== $date ) {
		$args['post_date']     = $date;
		$args['post_date_gmt'] = $date;
	}
	$id = (int) wp_insert_post( $args );
	if ( ! term_exists( $kind, 'kind' ) ) {
		wp_insert_term( ucfirst( $kind ), 'kind', array( 'slug' => $kind ) );
	}
	wp_set_object_terms( $id, $kind, 'kind' );
	update_post_meta( $id, '_pkiw_surface', $surface );
	clean_post_cache( $id );
	return $id;
}

/** Render templates/single.html for a post the way the template canvas does. */
function render_single( int $id ): string {
	global $wp_query, $wp_the_query, $_wp_current_template_id, $_wp_current_template_content;
	$wp_query                     = new WP_Query( array( 'p' => $id ) );
	$wp_the_query                 = $wp_query;
	$_wp_current_template_id      = get_stylesheet() . '//single';
	$_wp_current_template_content = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/single.html' );
	$html                         = get_the_block_template_html();
	wp_reset_postdata();
	return $html;
}

/** The page's breadcrumb landmarks. */
function trails( string $html ): array {
	$doc = \Dom\HTMLDocument::createFromString( '<!doctype html><body>' . $html, LIBXML_NOERROR );
	return array_values( array_filter( iterator_to_array( $doc->querySelectorAll( 'nav' ) ), static fn( $nav ) => 1 === preg_match( '/breadcrumb/i', (string) $nav->getAttribute( 'aria-label' ) ) ) );
}

/** One trail as [ [ text, href|null, aria-current|null ], ... ]. */
function items( \Dom\Element $nav ): array {
	$rows = array();
	foreach ( $nav->querySelectorAll( 'li' ) as $li ) {
		$link    = $li->querySelector( 'a' );
		$current = $li->querySelector( '[aria-current]' );
		$rows[]  = array( trim( $li->textContent ), $link ? $link->getAttribute( 'href' ) : null, $current ? $current->getAttribute( 'aria-current' ) : null );
	}
	return $rows;
}

/** Everything inside a trail that carries aria-current. */
function current_count( \Dom\Element $nav ): int {
	return count( iterator_to_array( $nav->querySelectorAll( '[aria-current]' ) ) );
}

$template = (string) file_get_contents( $root . '/templates/single.html' );
check( 1 === substr_count( $template, '<!-- wp:breadcrumbs ' ), 'templates/single.html holds one core/breadcrumbs block' );

$existing = get_page_by_path( 'stream' );
$stream   = $existing ? $existing->ID : (int) wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Stream',
		'post_name'   => 'stream',
	)
);
// Stored as an editor with unfiltered_html could save it: markup a trail must print as text.
$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_title' => 'Stream <img src=x onerror=alert(1)>', 'post_status' => 'publish' ), array( 'ID' => $stream ) );
clean_post_cache( $stream );
check( $stream > 0 && get_page_by_path( 'stream' )->ID === $stream, 'fixture: a published page at /stream/' );
$home       = home_url( '/' );
$stream_url = get_permalink( $stream );

// Two Stream posts of different kinds.
$ids = array();
foreach ( array( 'note' => 'A note & "quotes"', 'photo' => 'A photo' ) as $kind => $title ) {
	$id    = entry( $title, $kind, 'stream' );
	$ids[ $kind ] = $id;
	$term  = get_term_by( 'slug', $kind, 'kind' );
	$html  = render_single( $id );
	$navs  = trails( $html );
	$label = $term->name;
	check( 1 === count( $navs ), "$kind: exactly one breadcrumb landmark (found " . count( $navs ) . ')' );
	if ( ! $navs ) {
		continue;
	}
	$nav = $navs[0];
	check( str_contains( (string) $nav->getAttribute( 'class' ), 'wp-block-breadcrumbs' ), "$kind: the landmark is core/breadcrumbs, not Yoast's trail" );
	check( '' !== trim( (string) $nav->getAttribute( 'aria-label' ) ), "$kind: the landmark has an accessible name (\"" . $nav->getAttribute( 'aria-label' ) . '")' );
	$expected = array(
		array( 'Home', $home, null ),
		array( 'Stream <img src=x onerror=alert(1)>', $stream_url, null ),
		array( $label, get_term_link( $term ), null ),
		array( html_entity_decode( wptexturize( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), null, 'page' ),
	);
	$got      = items( $nav );
	check( $expected === $got, "$kind: trail is Home > Stream > $label > title, the title current" . ( $expected === $got ? '' : ': got ' . wp_json_encode( $got ) ) );
	check( 1 === current_count( $nav ), "$kind: aria-current sits on the last item only" );
	check( null === $nav->querySelector( 'img, script' ), "$kind: the Stream page title prints as text, not markup" );
}

// A Blog post keeps Yoast's trail; the native block prints nothing.
$blog = entry( 'A blog article', 'article', 'main' );
$navs = trails( render_single( $blog ) );
check( 1 === count( $navs ), 'blog post: exactly one breadcrumb landmark (found ' . count( $navs ) . ')' );
check( $navs && ! str_contains( (string) $navs[0]->getAttribute( 'class' ), 'wp-block-breadcrumbs' ) && str_contains( (string) $navs[0]->getAttribute( 'class' ), 'cr-single-breadcrumbs' ), 'blog post: the landmark is Yoast\'s trail' );

// Editor parity: the block renderer the editor's ServerSideRender calls.
$admin = get_user_by( 'login', get_super_admins()[0] ?? '' );
wp_set_current_user( $admin ? $admin->ID : 1 );
$photo    = $ids['photo'];
$request  = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/core/breadcrumbs' );
$request->set_query_params( array( 'context' => 'edit', 'post_id' => $photo, 'attributes' => array() ) );
$response = rest_do_request( $request );
$rendered = (string) ( $response->get_data()['rendered'] ?? '' );
$editor   = trails( $rendered );
$front    = trails( render_single( $photo ) );
check( 200 === $response->get_status() && $editor && $front && items( $editor[0] ) === items( $front[0] ), 'editor: the block renderer draws the photo\'s front-end trail' );
$request->set_query_params( array( 'context' => 'edit', 'post_id' => $blog, 'attributes' => array() ) );
check( '' === trim( (string) ( rest_do_request( $request )->get_data()['rendered'] ?? 'x' ) ), 'editor: the block renderer draws nothing for a Blog post, as the front end does' );
wp_set_current_user( 0 );

// A Query Loop on the single page draws each listed post's own trail.
$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $photo ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['wp_query']->the_post();
$loop = do_blocks( '<!-- wp:query {"queryId":9,"query":{"search":"quotes","perPage":5,"postType":"post","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:breadcrumbs /--><!-- /wp:post-template --></div><!-- /wp:query -->' );
wp_reset_postdata();
$loop_navs = trails( $loop );
check( 1 === count( $loop_navs ) && html_entity_decode( wptexturize( 'A note & "quotes"' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === html_entity_decode( (string) ( items( $loop_navs[0] )[3][0] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) && 'Note' === ( items( $loop_navs[0] )[2][0] ?? '' ), 'Query Loop: a listed Stream note draws its own trail, not the photo\'s' );

// A pattern instance draws the same trail as the template's block.
register_block_pattern( 'courtneyr-child/p36-breadcrumbs', array( 'title' => 'p36', 'content' => '<!-- wp:breadcrumbs {"className":"cr-single-breadcrumbs","fontSize":"small"} /-->' ) );
$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $photo ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['wp_query']->the_post();
$pattern = trails( do_blocks( '<!-- wp:pattern {"slug":"courtneyr-child/p36-breadcrumbs"} /-->' ) );
wp_reset_postdata();
check( $pattern && $front && items( $pattern[0] ) === items( $front[0] ), 'pattern: a pattern instance draws the template\'s trail' );

if ( $failures ) {
	echo count( $failures ), " failed\n";
	exit( 1 );
}
echo "all passed\n";
