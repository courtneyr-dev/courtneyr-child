<?php
/**
 * `CR_WP_SITE=<root> CR_WP_URL=<url> php tests/php/native-breadcrumbs-test.php`:
 * every page gets exactly one breadcrumb landmark. On templates/single.html
 * the Breadcrumbs block marked `cr-page-trail` draws a Stream post's trail
 * (Home, Stream, its kind, its title), Yoast's trail for other posts, or
 * core's when Yoast prints none, wherever the template puts it. Every other
 * Breadcrumbs block is core's, untouched. Checks named R1-n and R2-n cover
 * the findings of review rounds 1 and 2.
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

/** Start a fresh request: the page trail isn't printed yet, and Yoast forgets the last page it described. */
function fresh_request(): void {
	if ( function_exists( 'Courtneyr\\Child\\SingleNavigation\\trail_printed' ) ) {
		\Courtneyr\Child\SingleNavigation\trail_printed( false );
	}
	if ( function_exists( 'YoastSEO' ) && class_exists( 'Yoast\\WP\\SEO\\Memoizers\\Meta_Tags_Context_Memoizer' ) ) {
		YoastSEO()->classes->get( 'Yoast\\WP\\SEO\\Memoizers\\Meta_Tags_Context_Memoizer' )->clear();
	}
}

/** Render a block template for a query the way the template canvas does. */
function render_page( array $query, string $content, string $slug = 'single' ): string {
	global $wp_query, $wp_the_query, $_wp_current_template_id, $_wp_current_template_content;
	fresh_request();
	$wp_query                     = new WP_Query( $query );
	$wp_the_query                 = $wp_query;
	$GLOBALS['post']              = $wp_query->post;
	$_wp_current_template_id      = get_stylesheet() . '//' . $slug;
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
	fresh_request();
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

$marked = '<!-- wp:breadcrumbs {"className":"cr-single-breadcrumbs cr-page-trail","fontSize":"small"} /-->';
check( 1 === substr_count( $template, '<!-- wp:breadcrumbs ' ) && str_contains( $template, $marked ), 'templates/single.html holds one core/breadcrumbs block, marked cr-page-trail' );

/** Turn Yoast's breadcrumbs setting off (true) or back to what it was (false), without saving it. */
function yoast_setting_off( bool $off ): void {
	static $filter = null;
	$filter ??= static fn( $value ) => is_array( $value ) ? array_merge( $value, array( 'breadcrumbs-enable' => false ) ) : $value;
	$off ? add_filter( 'option_wpseo_titles', $filter ) : remove_filter( 'option_wpseo_titles', $filter );
	if ( class_exists( 'WPSEO_Options' ) ) {
		WPSEO_Options::clear_cache();
	}
}

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
$blog_nav = one_landmark( render_single( $blog ), 'blog post', $yoast_off );
check( $blog_nav && str_ends_with( trim( $blog_nav->textContent ), shown( 'A blog article' ) ), 'blog post: the trail ends at its title' );

// R2-1. Yoast active with its breadcrumbs setting off: the Blog post still gets core's trail.
yoast_setting_off( true );
$blog_off = entry( 'A blog article with Yoast breadcrumbs off', 'article', 'main' );
$off_nav  = one_landmark( render_single( $blog_off ), 'blog post with Yoast\'s breadcrumbs setting off', true );
check( $off_nav && shown( 'A blog article with Yoast breadcrumbs off' ) === ( end( items( $off_nav ) )[0] ?? '' ), 'blog post with Yoast\'s breadcrumbs setting off: core\'s trail ends at its title' );
yoast_setting_off( false );

// R1-1. Parent pages never enter a Stream trail: no private or draft title, no lost Stream / kind.
$grandparent = page( 'P36 secret grandparent', 'private' );
$parent      = page( 'P36 public parent', 'publish', $grandparent );
$draft       = page( 'P36 draft parent', 'draft' );
$under_page  = entry( 'A note under a page', 'note', 'stream', array( 'post_parent' => $parent ) );
$under_draft = entry( 'A note under a draft', 'note', 'stream', array( 'post_parent' => $draft ) );
check_stream_page( $under_page, 'note whose parent page has a private parent', $stream_label, $stream_url );
check( ! preg_match( '/secret grandparent|public parent/', render_single( $under_page ) ), 'note whose parent page has a private parent: no parent or ancestor title on the page' );
check_stream_page( $under_draft, 'note whose parent is a draft page', $stream_label, $stream_url );

// R1-4. A Posts page stays out of a Stream trail.
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

// R1-5. An untitled Stream page reads "Stream"; a Stream URL esc_url() drops leaves the item out.
$set_stream_title = static function ( string $title ) use ( $stream ): void {
	$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_title' => $title ), array( 'ID' => $stream ) );
	clean_post_cache( $stream );
};
$set_stream_title( '' );
check_stream_page( $photo, 'photo with an untitled Stream page', 'Stream', $stream_url );
$bad_link = static fn( $link, $id ) => $id === $stream ? 'javascript:alert(1)' : $link;
add_filter( 'page_link', $bad_link, 10, 2 );
check_stream_page( $photo, 'photo whose Stream URL esc_url() drops', 'Stream', null );
remove_filter( 'page_link', $bad_link, 10 );

// R2-5. A Stream page titled only with non-breaking spaces, raw or as entities, reads "Stream".
$set_stream_title( "\u{00A0}\u{00A0}" );
check_stream_page( $photo, 'photo whose Stream page title is two non-breaking spaces', 'Stream', $stream_url );
$set_stream_title( '&nbsp; &#160;' );
check_stream_page( $photo, 'photo whose Stream page title is &nbsp; entities', 'Stream', $stream_url );
$set_stream_title( "\u{2003}\u{3000} " );
check_stream_page( $photo, 'photo whose Stream page title is an em space and an ideographic space', 'Stream', $stream_url );
$set_stream_title( $stream_label );

// R2-4. Page 2 of a split Stream post: the title is the trail's one current item, unlinked; no "Page 2".
$split = entry( 'A split note', 'note', 'stream', array( 'post_content' => '<!-- wp:paragraph --><p>One.</p><!-- /wp:paragraph --><!--nextpage--><!-- wp:paragraph --><p>Two.</p><!-- /wp:paragraph -->' ) );
$paged = one_landmark( render_page( array( 'p' => $split, 'page' => 2 ), $template ), 'split note, page 2', true );
if ( $paged ) {
	$got = items( $paged );
	check( stream_expected( $split, $stream_label, $stream_url ) === $got, 'split note, page 2: trail reads Home / Stream / Note / title, the title unlinked and current; got ' . wp_json_encode( $got ) );
}

// R2-2. One trail when the marked block sits in a template part, in a pattern, or twice in the template.
$part = (int) wp_insert_post(
	array(
		'post_type'    => 'wp_template_part',
		'post_status'  => 'publish',
		'post_name'    => 'p36-trail',
		'post_title'   => 'p36 trail',
		'post_content' => $marked,
	)
);
wp_set_object_terms( $part, get_stylesheet(), 'wp_theme' );
register_block_pattern( 'courtneyr-child/p36-breadcrumbs', array( 'title' => 'p36', 'content' => $marked ) );
$h1_block     = (string) ( preg_match( '/<!-- wp:post-title \{"level":1[^>]*\/-->/', $template, $m ) ? $m[0] : '' );
$part_block   = '<!-- wp:template-part {"slug":"p36-trail","theme":"' . get_stylesheet() . '"} /-->';
$pattern_tag  = '<!-- wp:pattern {"slug":"courtneyr-child/p36-breadcrumbs"} /-->';
$tpl_block    = (string) ( preg_match( '/<!-- wp:breadcrumbs [^>]*\/-->/', $template, $m ) ? $m[0] : '' );
$h1_first     = str_replace( array( $tpl_block, $h1_block ), array( '', $h1_block . "\n" . $tpl_block ), $template );
$layouts      = array(
	'the marked block in a template part'          => str_replace( $tpl_block, $part_block, $template ),
	'the marked block in a pattern'                => str_replace( $tpl_block, $pattern_tag, $template ),
	'two marked blocks'                            => str_replace( $h1_block, $h1_block . "\n" . $tpl_block, $template ),
	'the title before the marked block'            => $h1_first,
	'the title before a template part holding it'  => str_replace( $tpl_block, $part_block, $h1_first ),
	'the title before a pattern holding it'        => str_replace( $tpl_block, $pattern_tag, $h1_first ),
);
check( '' !== $h1_block && '' !== $tpl_block && 1 === substr_count( $h1_first, $tpl_block ) && strpos( $h1_first, $h1_block ) < strpos( $h1_first, $tpl_block ), 'fixture: single.html with the title moved before the marked block' );
foreach ( $layouts as $layout => $markup ) {
	check( $markup !== $template, "fixture: single.html with $layout" );
	$layout_note = entry( "A note with $layout", 'note', 'stream' );
	if ( str_starts_with( $layout, 'the title before' ) ) {
		// The title renders first, so with Yoast it prints Yoast's trail and the marked block adds none.
		one_landmark( render_single( $layout_note, $markup ), "note on single.html with $layout", $yoast_off );
	} else {
		check_stream_page( $layout_note, "note on single.html with $layout", $stream_label, $stream_url, $markup );
	}
	one_landmark( render_single( entry( "A blog article with $layout", 'article', 'main' ), $markup ), "blog post on single.html with $layout", $yoast_off );
}
yoast_setting_off( true );
one_landmark( render_single( entry( 'A blog article under a title that renders first, Yoast breadcrumbs off', 'article', 'main' ), $h1_first ), 'blog post on single.html with the title first and Yoast\'s breadcrumbs setting off', true );
yoast_setting_off( false );

// R2-2. A level-1 title inside post content, under a level-2 template title: one trail.
$h2_template = str_replace( $h1_block, str_replace( '{"level":1,', '{"level":2,', $h1_block ), $template );
$h1_body     = '<!-- wp:post-title {"level":1} /--><!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->';
check( $h2_template !== $template, 'fixture: single.html with a level-2 title' );
one_landmark( render_single( entry( 'A blog article with a title in its content', 'article', 'main', array( 'post_content' => $h1_body ) ), $h2_template ), 'blog post with a level-1 title in its content', $yoast_off );
check_stream_page( entry( 'A note with a title in its content', 'note', 'stream', array( 'post_content' => $h1_body ) ), 'note with a level-1 title in its content', $stream_label, $stream_url, $h2_template );

// Query Loop rows: a marked block or a level-1 title in a row adds no trail.
$loop      = '<!-- wp:query {"queryId":9,"query":{"perPage":5,"postType":"post","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"level":1} /-->' . $marked . '<!-- /wp:post-template --></div><!-- /wp:query -->';
$with_loop = str_replace( '<!-- wp:template-part {"slug":"footer"', $loop . '<!-- wp:template-part {"slug":"footer"', $template );
check( $with_loop !== $template, 'fixture: single.html with a Query Loop before the footer' );
check_stream_page( entry( 'A note on a page with a Query Loop', 'note', 'stream' ), 'note on single.html with marked Breadcrumbs and level-1 titles in Query Loop rows', $stream_label, $stream_url, $with_loop );
one_landmark( render_single( entry( 'A blog article on a page with a Query Loop', 'article', 'main' ), $with_loop ), 'blog post on single.html with marked Breadcrumbs and level-1 titles in Query Loop rows', $yoast_off );

$search = $marked . '<!-- wp:query {"queryId":3,"query":{"inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"isLink":true} /-->' . $marked . '<!-- /wp:post-template --></div><!-- /wp:query -->';
entry( 'P36 searchable one', 'note', 'stream' );
entry( 'P36 searchable two', 'article', 'main' );
$search_html = render_page( array( 's' => 'P36 searchable' ), $search, 'search' );
check( 2 === substr_count( $search_html, 'wp-block-post-title' ), 'search fixture: the Query Loop lists two results' );
$search_nav = one_landmark( $search_html, 'search results with the marked block in each Query Loop row', true );
check( $search_nav && str_contains( $search_nav->textContent, 'Search results for' ), 'search results: the one trail is core\'s search trail' );

// R2-3. An ordinary Page with an unmarked Breadcrumbs block in its body keeps that trail.
$page_body = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'P36 page with breadcrumbs', 'post_content' => '<!-- wp:breadcrumbs /--><!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->' ) );
$page_nav  = one_landmark( render_page( array( 'page_id' => $page_body ), (string) file_get_contents( $root . '/templates/page.html' ), 'page' ), 'page with an unmarked Breadcrumbs block in its body', true );
check( $page_nav && shown( 'P36 page with breadcrumbs' ) === ( end( items( $page_nav ) )[0] ?? '' ), 'page with an unmarked Breadcrumbs block: core\'s trail ends at its title' );

// R1-3. A custom post type gets one trail: Yoast's where Yoast prints one, else core's.
register_post_type( 'p36_thing', array( 'public' => true, 'show_in_rest' => true, 'label' => 'Things', 'supports' => array( 'title', 'editor' ) ) );
$thing = (int) wp_insert_post( array( 'post_type' => 'p36_thing', 'post_status' => 'publish', 'post_title' => 'A thing' ) );
$bare  = one_landmark( render_single( $thing, $marked . '<!-- wp:post-content /-->' ), 'custom post type on a template with the marked block and no title', $yoast_off );
check( $bare && str_ends_with( trim( $bare->textContent ), shown( 'A thing' ) ), 'custom post type: the trail ends at its title; got ' . ( $bare ? trim( $bare->textContent ) : '' ) );
$thing_two = (int) wp_insert_post( array( 'post_type' => 'p36_thing', 'post_status' => 'publish', 'post_title' => 'Another thing' ) );
one_landmark( render_single( $thing_two ), 'custom post type on single.html', $yoast_off );

// R1-3. Another theme on the site: none of this theme's trail handling applies.
$other_theme = static fn() => 'twentytwentyfive';
add_filter( 'pre_option_stylesheet', $other_theme );
$foreign = trails( render_loose( $note, $marked ) );
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
	$sub_trails = trails( render_loose( $sub_note, $marked ) );
	check( 1 === count( $sub_trails ) && ! in_array( get_permalink( $sub_stream ), array_column( items( $sub_trails[0] ), 1 ), true ), 'subsite running ' . get_option( 'stylesheet' ) . ': a Stream note gets core\'s trail, no Stream item' );
	restore_current_blog();
} else {
	echo "note no subsite with another theme here; the stylesheet check above covers the guard\n";
}

// A pattern instance of the marked block outside a template draws the template's trail.
$front   = trails( render_single( $photo ) );
$pattern = trails( render_loose( $photo, $pattern_tag ) );
check( $pattern && $front && items( $pattern[0] ) === items( $front[0] ), 'pattern: a pattern instance of the marked block draws the template\'s trail' );

// R2-4. Editor parity: an unmarked block draws core's trail in the editor and on the front end alike.
// The editor's ServerSideRender drops className, so the block renderer only ever sees unmarked blocks.
$body_note = entry( 'A note with breadcrumbs in its body', 'note', 'stream', array( 'post_content' => '<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph --><!-- wp:breadcrumbs /-->' ) );
$body_navs = array_values( array_filter( trails( render_single( $body_note ) ), static fn( $nav ) => null !== $nav->closest( '.wp-block-post-content' ) ) );
check( 1 === count( $body_navs ) && is_native( $body_navs[0] ), 'note with an unmarked Breadcrumbs block in its body: the body block draws a trail' );
$admin = get_user_by( 'login', get_super_admins()[0] ?? '' );
wp_set_current_user( $admin ? $admin->ID : 1 );
$GLOBALS['wp_query']     = new WP_Query();
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
fresh_request();
$request = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/core/breadcrumbs' );
$request->set_query_params( array( 'context' => 'edit', 'post_id' => $body_note, 'attributes' => array() ) );
$response = rest_do_request( $request );
$editor   = trails( (string) ( $response->get_data()['rendered'] ?? '' ) );
wp_set_current_user( 0 );
check( 200 === $response->get_status() && $editor && $body_navs && items( $editor[0] ) === items( $body_navs[0] ), 'editor: the block renderer draws the body block\'s front-end trail' . ( $editor && $body_navs ? '; editor ' . wp_json_encode( items( $editor[0] ) ) . ', front ' . wp_json_encode( items( $body_navs[0] ) ) : '' ) );

// R3-1. A Query Loop row never claims the page trail, wherever the loop sits.
/** The landmarks inside Query Loop rows. */
function row_trails( string $html ): array {
	return array_values( array_filter( trails( $html ), static fn( $nav ) => null !== $nav->closest( '.wp-block-post-template' ) ) );
}
$all_loop   = '<!-- wp:query {"queryId":11,"query":{"perPage":100,"postType":"post","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"level":1} /-->' . $marked . '<!-- /wp:post-template --></div><!-- /wp:query -->';
$loop_first = str_replace( $tpl_block, $all_loop . "\n" . $tpl_block, $template );
check( $loop_first !== $template && strpos( $loop_first, $all_loop ) < strpos( $loop_first, $tpl_block ), 'fixture: single.html with a Query Loop before the marked block' );
$loop_note = entry( 'A note after a Query Loop that lists it', 'note', 'stream' );
$loop_html = render_single( $loop_note, $loop_first );
check( 0 === count( row_trails( $loop_html ) ), 'note on single.html with a Query Loop first: no trail inside a row (found ' . count( row_trails( $loop_html ) ) . ')' );
check_stream_page( $loop_note, 'note on single.html with a Query Loop first', $stream_label, $stream_url, $loop_first );
$loop_blog = entry( 'A blog article after a Query Loop that lists it', 'article', 'main' );
$blog_html = render_single( $loop_blog, $loop_first );
check( 0 === count( row_trails( $blog_html ) ), 'blog post on single.html with a Query Loop first: no trail inside a row (found ' . count( row_trails( $blog_html ) ) . ')' );
one_landmark( $blog_html, 'blog post on single.html with a Query Loop first', $yoast_off );
$row_only    = '<!-- wp:query {"queryId":12,"query":{"inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"isLink":true} /-->' . $marked . '<!-- /wp:post-template --></div><!-- /wp:query -->';
$search_rows = render_page( array( 's' => 'P36 searchable' ), $row_only, 'search' );
check( 0 === count( trails( $search_rows ) ), 'search results with the marked block only inside Query Loop rows: no trail (found ' . count( trails( $search_rows ) ) . ')' );

// R3-2. A Stream post's title prints as text: no link and no second aria-current inside the current item.
$linked = entry( 'P36 linked title placeholder', 'note', 'stream' );
$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_title' => '<a href="https://example.com/" aria-current="page">Linked</a> title' ), array( 'ID' => $linked ) );
clean_post_cache( $linked );
$linked_nav = one_landmark( render_single( $linked ), 'note whose title holds a link', true );
if ( $linked_nav ) {
	$last = $linked_nav->querySelector( 'li:last-child' );
	check( $last && null === $last->querySelector( 'a' ) && 'Linked title' === trim( $last->textContent ), 'note whose title holds a link: the current item is the plain text "Linked title"' );
	check( 1 === count( iterator_to_array( $linked_nav->querySelectorAll( '[aria-current]' ) ) ), 'note whose title holds a link: one aria-current in the trail' );
}

// R3-3. Attachment pages keep main's behavior: no trail from the marked block or the title.
$attachments_on = static fn() => '1';
add_filter( 'pre_option_wp_attachment_pages_enabled', $attachments_on );
foreach ( array( 'inherit' => $note, 'publish' => 0 ) as $status => $parent_id ) {
	$attachment = (int) wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => $status, 'post_title' => "P36 $status attachment", 'post_parent' => $parent_id, 'post_mime_type' => 'image/png', 'guid' => home_url( "/p36-$status.png" ) ) );
	$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_status' => $status ), array( 'ID' => $attachment ) );
	clean_post_cache( $attachment );
	$found = count( trails( render_single( $attachment ) ) );
	check( 0 === $found, "$status attachment on single.html: no breadcrumb trail, as on main (found $found)" );
}
remove_filter( 'pre_option_wp_attachment_pages_enabled', $attachments_on );

if ( $failures ) {
	echo count( $failures ), " failed\n";
	exit( 1 );
}
echo "all passed\n";
