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
 * rolled back, and changed options are put back. Pages are rendered whole
 * (templates/single.html, or that template rearranged) and their breadcrumb
 * landmarks counted.
 *
 * With Yoast SEO active the checks run twice: here with Yoast, then in a
 * second PHP process that loads WordPress with Yoast SEO left out of the
 * active plugins (CR_P36_YOAST_OFF=1), so Yoast's code never loads. Without
 * Yoast SEO only the second half can run, and the note line says so. Exits 2
 * without a site.
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

$yoast_off = '1' === getenv( 'CR_P36_YOAST_OFF' );
if ( $yoast_off ) {
	// Yoast SEO deactivated for this process: dropped from the active plugin lists before plugins load.
	$drop_yoast = static fn( $plugins ) => is_array( $plugins ) ? array_filter( $plugins, static fn( $value, $key ) => ! str_starts_with( (string) $value, 'wordpress-seo/' ) && ! str_starts_with( (string) $key, 'wordpress-seo/' ), ARRAY_FILTER_USE_BOTH ) : $plugins;
	$wp_filter  = array(
		'option_active_plugins'                => array( 10 => array( array( 'function' => $drop_yoast, 'accepted_args' => 1 ) ) ),
		'site_option_active_sitewide_plugins' => array( 10 => array( array( 'function' => $drop_yoast, 'accepted_args' => 1 ) ) ),
	);
}

$_SERVER['HTTP_HOST']   = (string) parse_url( $url, PHP_URL_HOST ) . ( parse_url( $url, PHP_URL_PORT ) ? ':' . parse_url( $url, PHP_URL_PORT ) : '' );
$_SERVER['REQUEST_URI'] = (string) ( parse_url( $url, PHP_URL_PATH ) ?: '/' );
$_SERVER['SERVER_NAME'] = (string) parse_url( $url, PHP_URL_HOST );
define( 'WP_USE_THEMES', false );
require $site . '/wp-load.php';

$failures = array();

/** Print one result and remember failures. */
function check( bool $ok, string $message ): void {
	global $failures, $yoast_off;
	echo ( $ok ? 'ok   ' : 'FAIL ' ), $yoast_off ? '[Yoast off] ' : '[Yoast on] ', $message, "\n";
	if ( ! $ok ) {
		$failures[] = $message;
	}
}

if ( 'courtneyr-child' !== get_option( 'stylesheet' ) || ! taxonomy_exists( 'kind' ) ) {
	echo "FAIL the site must run courtneyr-child with Post Kinds for IndieWeb active\n";
	exit( 1 );
}

$yoast = function_exists( 'yoast_breadcrumb' );
if ( $yoast_off ) {
	check( ! $yoast && ! array_filter( (array) get_option( 'active_plugins' ), static fn( $p ) => str_starts_with( (string) $p, 'wordpress-seo/' ) ), 'Yoast SEO is not loaded in this process' );
} elseif ( $yoast ) {
	// The Yoast-off half, before this process opens a transaction: SQLite allows one writer.
	$command = escapeshellarg( PHP_BINARY ) . ' -d error_reporting=' . (int) ini_get( 'error_reporting' ) . ' ' . escapeshellarg( __FILE__ ) . ' 2>&1';
	putenv( 'CR_P36_YOAST_OFF=1' );
	exec( $command, $child_output, $child_status );
	putenv( 'CR_P36_YOAST_OFF' );
	echo implode( "\n", $child_output ), "\n";
	if ( 0 !== $child_status ) {
		$failures[] = 'the Yoast-off process failed';
	}
} else {
	$yoast_off = true;
	echo "note Yoast SEO isn't active here, so only the Yoast-off checks run\n";
}
echo 'note WordPress ', get_bloginfo( 'version' ), $yoast_off ? ', Yoast SEO off' : ', Yoast SEO on', "\n";

$GLOBALS['wpdb']->query( 'START TRANSACTION' );
register_shutdown_function( static fn() => $GLOBALS['wpdb']->query( 'ROLLBACK' ) );
wp_set_current_user( 0 );

$root     = dirname( __DIR__, 2 );
$template = (string) file_get_contents( $root . '/templates/single.html' );

/** A published post with a kind on a surface, its kind set after Post Kinds' save hooks. */
function entry( string $title, string $kind, string $surface, array $extra = array() ): int {
	$id = (int) wp_insert_post(
		array_merge(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->',
			),
			$extra
		)
	);
	if ( ! term_exists( $kind, 'kind' ) ) {
		wp_insert_term( ucfirst( $kind ), 'kind', array( 'slug' => $kind ) );
	}
	wp_set_object_terms( $id, $kind, 'kind' );
	update_post_meta( $id, '_pkiw_surface', $surface );
	clean_post_cache( $id );
	return $id;
}

/** A page with a status and parent. */
function page( string $title, string $status, int $parent = 0 ): int {
	return (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => $status,
			'post_title'  => $title,
			'post_parent' => $parent,
		)
	);
}

/** Render a block template for a query the way the template canvas does. */
function render_page( array $query, string $content ): string {
	global $wp_query, $wp_the_query, $_wp_current_template_id, $_wp_current_template_content;
	$wp_query                     = new WP_Query( $query );
	$wp_the_query                 = $wp_query;
	$GLOBALS['post']              = $wp_query->post;
	$_wp_current_template_id      = get_stylesheet() . '//single';
	$_wp_current_template_content = $content;
	$html                         = get_the_block_template_html();
	wp_reset_postdata();
	$_wp_current_template_content = '';
	return $html;
}

/** Render a single entry with templates/single.html, or other template markup. */
function render_single( int $id, ?string $content = null ): string {
	global $template;
	return render_page( array( 'p' => $id, 'post_type' => get_post_type( $id ) ), $content ?? $template );
}

/** Render loose block markup with an entry set up as the queried post, outside any template. */
function render_loose( int $id, string $markup ): string {
	$GLOBALS['wp_query']     = new WP_Query( array( 'p' => $id, 'post_type' => get_post_type( $id ) ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['wp_query']->the_post();
	$html = do_blocks( $markup );
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

/** A title as the trail prints it. */
function shown( string $title ): string {
	return html_entity_decode( wptexturize( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/** Whether a landmark is core's block or the theme's Yoast wrapper. */
function is_native( \Dom\Element $nav ): bool {
	return str_contains( (string) $nav->getAttribute( 'class' ), 'wp-block-breadcrumbs' );
}

/** Check a page carries one landmark, from the expected source. */
function one_landmark( string $html, string $what, ?bool $native = null ): ?\Dom\Element {
	$navs = trails( $html );
	check( 1 === count( $navs ), "$what: exactly one breadcrumb landmark (found " . count( $navs ) . ')' );
	if ( null !== $native && $navs ) {
		check( $native === is_native( $navs[0] ), "$what: the landmark is " . ( $native ? 'core/breadcrumbs' : 'Yoast\'s trail' ) );
	}
	return $navs[0] ?? null;
}

/** The trail a Stream post should read. */
function stream_expected( int $id, string $stream_label, ?string $stream_url ): array {
	$kind = get_the_terms( $id, 'kind' )[0];
	$rows = array( array( 'Home', home_url( '/' ), null ) );
	if ( null !== $stream_url ) {
		$rows[] = array( $stream_label, $stream_url, null );
	}
	$rows[] = array( $kind->name, get_term_link( $kind ), null );
	$rows[] = array( shown( get_post( $id )->post_title ), null, 'page' );
	return $rows;
}

/** Check a Stream post's page reads Home / Stream / kind / title. */
function check_stream_page( int $id, string $what, string $stream_label, ?string $stream_url, ?string $content = null ): void {
	$html = render_single( $id, $content );
	$nav  = one_landmark( $html, $what, true );
	if ( ! $nav ) {
		return;
	}
	$expected = stream_expected( $id, $stream_label, $stream_url );
	$got      = items( $nav );
	check( $expected === $got, "$what: trail reads " . implode( ' / ', array_column( $expected, 0 ) ) . ( $expected === $got ? '' : '; got ' . wp_json_encode( $got ) ) );
	check( 1 === count( iterator_to_array( $nav->querySelectorAll( '[aria-current]' ) ) ), "$what: aria-current sits on the title only" );
	check( ! preg_match( '/href=""/', $nav->outerHTML ), "$what: no empty href" );
}

check( 1 === substr_count( $template, '<!-- wp:breadcrumbs ' ), 'templates/single.html holds one core/breadcrumbs block' );

$existing = get_page_by_path( 'stream' );
$stream   = $existing ? $existing->ID : page( 'Stream', 'publish' );
// Stored as an editor with unfiltered_html could save it: markup a trail must print as text.
$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_title' => 'Stream <img src=x onerror=alert(1)>', 'post_status' => 'publish', 'post_parent' => 0 ), array( 'ID' => $stream ) );
clean_post_cache( $stream );
check( $stream > 0 && get_page_by_path( 'stream' )->ID === $stream, 'fixture: a published page at /stream/' );
$stream_label = 'Stream <img src=x onerror=alert(1)>';
$stream_url   = get_permalink( $stream );

// Two Stream posts of different kinds.
$note  = entry( 'A note & "quotes"', 'note', 'stream' );
$photo = entry( 'A photo', 'photo', 'stream' );
foreach ( array( 'note' => $note, 'photo' => $photo ) as $kind => $id ) {
	check_stream_page( $id, $kind, $stream_label, $stream_url );
	$nav = trails( render_single( $id ) )[0] ?? null;
	check( $nav && '' !== trim( (string) $nav->getAttribute( 'aria-label' ) ), "$kind: the landmark has an accessible name" );
	check( $nav && null === $nav->querySelector( 'img, script' ), "$kind: the Stream page title prints as text, not markup" );
}

// A Blog post: Yoast's trail with Yoast, core's own trail without; one either way.
$blog = entry( 'A blog article', 'article', 'main' );
one_landmark( render_single( $blog ), 'blog post', $yoast_off );

// 1. Parent pages never enter a Stream trail: no private or draft title, no lost Stream / kind.
$grandparent = page( 'P36 secret grandparent', 'private' );
$parent      = page( 'P36 public parent', 'publish', $grandparent );
$draft       = page( 'P36 draft parent', 'draft' );
$under_page  = entry( 'A note under a page', 'note', 'stream', array( 'post_parent' => $parent ) );
$under_draft = entry( 'A note under a draft', 'note', 'stream', array( 'post_parent' => $draft ) );
check_stream_page( $under_page, 'note whose parent page has a private parent', $stream_label, $stream_url );
check( ! preg_match( '/secret grandparent|public parent/', render_single( $under_page ) ), 'note whose parent page has a private parent: no parent or ancestor title on the page' );
check_stream_page( $under_draft, 'note whose parent is a draft page', $stream_label, $stream_url );

// 4. A Posts page stays out of a Stream trail.
$options = array(
	'show_on_front'  => get_option( 'show_on_front' ),
	'page_on_front'  => get_option( 'page_on_front' ),
	'page_for_posts' => get_option( 'page_for_posts' ),
);
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', page( 'P36 front', 'publish' ) );
update_option( 'page_for_posts', page( 'P36 Blog', 'publish' ) );
check_stream_page( $note, 'note with a Posts page set', $stream_label, $stream_url );
foreach ( $options as $option => $value ) {
	update_option( $option, $value );
}

// 5. An untitled Stream page reads "Stream"; a Stream URL esc_url() drops leaves the item out.
$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_title' => '' ), array( 'ID' => $stream ) );
clean_post_cache( $stream );
check_stream_page( $photo, 'photo with an untitled Stream page', 'Stream', $stream_url );
$bad_link = static fn( $link, $id ) => $id === $stream ? 'javascript:alert(1)' : $link;
add_filter( 'page_link', $bad_link, 10, 2 );
check_stream_page( $photo, 'photo whose Stream URL esc_url() drops', 'Stream', null );
remove_filter( 'page_link', $bad_link, 10 );
$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_title' => $stream_label ), array( 'ID' => $stream ) );
clean_post_cache( $stream );

// 2. Composed pages keep one landmark; Breadcrumbs blocks in content or a Query Loop draw nothing.
register_block_pattern( 'courtneyr-child/p36-breadcrumbs', array( 'title' => 'p36', 'content' => '<!-- wp:breadcrumbs {"className":"cr-single-breadcrumbs","fontSize":"small"} /-->' ) );
$in_content = '<!-- wp:pattern {"slug":"courtneyr-child/p36-breadcrumbs"} /--><!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->';
$note_pat   = entry( 'A note with a breadcrumbs pattern', 'note', 'stream', array( 'post_content' => $in_content ) );
check_stream_page( $note_pat, 'note with a Breadcrumbs pattern in its content', $stream_label, $stream_url );
$blog_pat = entry( 'A blog article with a breadcrumbs pattern', 'article', 'main', array( 'post_content' => $in_content ) );
one_landmark( render_single( $blog_pat ), 'blog post with a Breadcrumbs pattern in its content', $yoast_off );

$loop          = '<!-- wp:query {"queryId":9,"query":{"perPage":5,"postType":"post","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- wp:breadcrumbs /--><!-- /wp:post-template --></div><!-- /wp:query -->';
$with_loop     = str_replace( '<!-- wp:template-part {"slug":"footer"', $loop . '<!-- wp:template-part {"slug":"footer"', $template );
$with_loop     = $with_loop === $template ? $template . $loop : $with_loop;
$note_loop     = entry( 'A note on a page with a Query Loop', 'note', 'stream' );
check_stream_page( $note_loop, 'note on single.html with a Query Loop of Breadcrumbs added', $stream_label, $stream_url, $with_loop );
$blog_loop = entry( 'A blog article on a page with a Query Loop', 'article', 'main' );
one_landmark( render_single( $blog_loop, $with_loop ), 'blog post on single.html with a Query Loop of Breadcrumbs added', $yoast_off );

$search      = '<!-- wp:breadcrumbs /--><!-- wp:query {"queryId":3,"query":{"inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"isLink":true} /--><!-- wp:breadcrumbs /--><!-- /wp:post-template --></div><!-- /wp:query -->';
entry( 'P36 searchable one', 'note', 'stream' );
entry( 'P36 searchable two', 'article', 'main' );
$search_html = render_page( array( 's' => 'P36 searchable' ), $search );
check( 2 === substr_count( $search_html, 'wp-block-post-title' ), 'search fixture: the Query Loop lists two results' );
$search_nav = one_landmark( $search_html, 'search results with Breadcrumbs in each Query Loop row', true );
check( $search_nav && str_contains( $search_nav->textContent, 'Search results for' ), 'search results: the one trail is core\'s search trail' );

$h1_first   = str_replace( '<!-- wp:breadcrumbs {"className":"cr-single-breadcrumbs","fontSize":"small"} /-->', '', $template );
$h1_first   = preg_replace( '/(<!-- wp:post-title \{"level":1[^>]*\/-->)/', '$1' . "\n" . '<!-- wp:breadcrumbs {"className":"cr-single-breadcrumbs","fontSize":"small"} /-->', $h1_first, 1 );
check( 1 === substr_count( (string) $h1_first, '<!-- wp:breadcrumbs ' ) && strpos( (string) $h1_first, 'wp:post-title {"level":1' ) < strpos( (string) $h1_first, '<!-- wp:breadcrumbs ' ), 'fixture: single.html with the title moved before Breadcrumbs' );
$note_h1 = entry( 'A note under a title that renders first', 'note', 'stream' );
check_stream_page( $note_h1, 'note when the title renders before Breadcrumbs', $stream_label, $stream_url, (string) $h1_first );
$blog_h1 = entry( 'A blog article under a title that renders first', 'article', 'main' );
one_landmark( render_single( $blog_h1, (string) $h1_first ), 'blog post when the title renders before Breadcrumbs', $yoast_off );

// 3. A custom post type keeps core's trail where Yoast's doesn't print, and gets one trail where it does.
register_post_type( 'p36_thing', array( 'public' => true, 'show_in_rest' => true, 'label' => 'Things', 'supports' => array( 'title', 'editor' ) ) );
$thing = (int) wp_insert_post( array( 'post_type' => 'p36_thing', 'post_status' => 'publish', 'post_title' => 'A thing' ) );
$bare  = one_landmark( render_single( $thing, '<!-- wp:breadcrumbs /--><!-- wp:post-content /-->' ), 'custom post type on a template with Breadcrumbs and no title', true );
check( $bare && shown( 'A thing' ) === ( end( items( $bare ) )[0] ?? '' ), 'custom post type: core\'s trail ends at its title' );
$thing_two = (int) wp_insert_post( array( 'post_type' => 'p36_thing', 'post_status' => 'publish', 'post_title' => 'Another thing' ) );
one_landmark( render_single( $thing_two ), 'custom post type on single.html', $yoast_off );

// 3. Another theme on the site: none of this theme's trail handling applies.
$other_theme = static fn() => 'twentytwentyfive';
add_filter( 'pre_option_stylesheet', $other_theme );
$foreign = trails( render_loose( $note, '<!-- wp:breadcrumbs /-->' ) );
remove_filter( 'pre_option_stylesheet', $other_theme );
check( 1 === count( $foreign ) && ! in_array( $stream_url, array_column( items( $foreign[0] ), 1 ), true ), 'with another stylesheet active, a Stream note gets core\'s trail, no Stream item' );
$subsite = null;
if ( is_multisite() ) {
	foreach ( get_sites( array( 'number' => 20 ) ) as $site_row ) {
		if ( (int) $site_row->blog_id !== get_current_blog_id() && 'courtneyr-child' !== get_blog_option( (int) $site_row->blog_id, 'stylesheet' ) ) {
			$subsite = (int) $site_row->blog_id;
			break;
		}
	}
}
if ( $subsite ) {
	switch_to_blog( $subsite );
	$sub_stream = get_page_by_path( 'stream' ) ? get_page_by_path( 'stream' )->ID : page( 'Stream', 'publish' );
	$sub_note   = entry( 'A subsite note', 'note', 'stream' );
	$sub_trails = trails( render_loose( $sub_note, '<!-- wp:breadcrumbs /-->' ) );
	check( 1 === count( $sub_trails ) && ! in_array( get_permalink( $sub_stream ), array_column( items( $sub_trails[0] ), 1 ), true ), 'subsite running ' . get_option( 'stylesheet' ) . ': a Stream note gets core\'s trail, no Stream item' );
	restore_current_blog();
} else {
	echo "note no subsite with another theme here; the stylesheet check above covers the guard\n";
}

// Editor parity: the block renderer the editor's ServerSideRender calls.
$admin = get_user_by( 'login', get_super_admins()[0] ?? '' );
wp_set_current_user( $admin ? $admin->ID : 1 );
$request = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/core/breadcrumbs' );
$request->set_query_params( array( 'context' => 'edit', 'post_id' => $photo, 'attributes' => array() ) );
$response = rest_do_request( $request );
$editor   = trails( (string) ( $response->get_data()['rendered'] ?? '' ) );
wp_set_current_user( 0 );
$front = trails( render_single( $photo ) );
check( 200 === $response->get_status() && $editor && $front && items( $editor[0] ) === items( $front[0] ), 'editor: the block renderer draws the photo\'s front-end trail' );
wp_set_current_user( $admin ? $admin->ID : 1 );
$request->set_query_params( array( 'context' => 'edit', 'post_id' => $blog, 'attributes' => array() ) );
$blog_editor = trails( (string) ( rest_do_request( $request )->get_data()['rendered'] ?? '' ) );
wp_set_current_user( 0 );
if ( $yoast_off ) {
	$blog_front = trails( render_single( entry( 'Another blog article', 'article', 'main' ) ) );
	check( 1 === count( $blog_editor ) && $blog_front && is_native( $blog_front[0] ) && array_column( items( $blog_editor[0] ), 0 )[0] === 'Home', 'editor: the block renderer draws core\'s trail for a Blog post, as the front end does without Yoast' );
} else {
	check( ! $blog_editor, 'editor: the block renderer draws nothing for a Blog post, as the front end leaves it to Yoast' );
}

// A pattern instance in the template draws the template block's trail.
$pattern = trails( render_loose( $photo, '<!-- wp:pattern {"slug":"courtneyr-child/p36-breadcrumbs"} /-->' ) );
check( $pattern && $front && items( $pattern[0] ) === items( $front[0] ), 'pattern: a pattern instance outside post content draws the template\'s trail' );

if ( $failures ) {
	echo count( $failures ), " failed\n";
	exit( 1 );
}
echo "all passed\n";
