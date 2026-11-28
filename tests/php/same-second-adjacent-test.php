<?php
/**
 * `CR_WP_SITE=<root> CR_WP_URL=<url> php tests/php/same-second-adjacent-test.php`:
 * two posts published in the same second link to each other through the
 * single template's Previous and Next, on the Stream (where Previous/Next
 * stays inside the kind) and on the Blog.
 *
 * Core breaks same-second ties by ID since WordPress 6.9; this guards the
 * theme's surface and kind scoping (inc/nav.php) and Post Kinds' adjacency
 * filter against undoing that. CR_WP_SITE is a WordPress root whose main site
 * runs this theme as `courtneyr-child` with Post Kinds for IndieWeb active.
 * Everything the test writes is rolled back. Exits 2 without a site.
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

$GLOBALS['wpdb']->query( 'START TRANSACTION' );
register_shutdown_function( static fn() => $GLOBALS['wpdb']->query( 'ROLLBACK' ) );
wp_set_current_user( 0 );

/** A published post at a fixed second, with a kind and a surface. */
function entry( string $title, string $kind, string $surface, string $date ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => $title,
			'post_content'  => '<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->',
			'post_date'     => $date,
			'post_date_gmt' => $date,
		)
	);
	wp_set_object_terms( $id, $kind, 'kind' );
	update_post_meta( $id, '_pkiw_surface', $surface );
	clean_post_cache( $id );
	return $id;
}

/** The single template's Previous and Next hrefs for a post: [ previous, next ]. */
function adjacent_links( int $id ): array {
	global $wp_query, $wp_the_query, $_wp_current_template_id, $_wp_current_template_content;
	$wp_query                     = new WP_Query( array( 'p' => $id ) );
	$wp_the_query                 = $wp_query;
	$_wp_current_template_id      = get_stylesheet() . '//single';
	$_wp_current_template_content = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/single.html' );
	$doc                          = \Dom\HTMLDocument::createFromString( '<!doctype html><body>' . get_the_block_template_html(), LIBXML_NOERROR );
	wp_reset_postdata();
	$href = static fn( string $selector ) => $doc->querySelector( $selector )?->getAttribute( 'href' );
	return array( $href( '.cr-post-nav .post-navigation-link-previous a' ), $href( '.cr-post-nav .post-navigation-link-next a' ) );
}

$cases = array(
	'Stream note' => array( 'note', 'stream', '2001-02-03 04:05:06' ),
	'Blog article' => array( 'article', 'main', '2001-02-04 04:05:06' ),
);
foreach ( $cases as $label => [ $kind, $surface, $date ] ) {
	$first  = entry( "$label one", $kind, $surface, $date );
	$second = entry( "$label two", $kind, $surface, $date );
	check( get_post( $first )->post_date === get_post( $second )->post_date, "$label: both posts carry the same post_date ($date)" );

	[ , $first_next ]     = adjacent_links( $first );
	[ $second_prev, $_ ]  = adjacent_links( $second );
	check( get_permalink( $second ) === $first_next, "$label: the first post's Next is the second (" . var_export( $first_next, true ) . ')' );
	check( get_permalink( $first ) === $second_prev, "$label: the second post's Previous is the first (" . var_export( $second_prev, true ) . ')' );
}

if ( $failures ) {
	echo count( $failures ), " failed\n";
	exit( 1 );
}
echo "all passed\n";
