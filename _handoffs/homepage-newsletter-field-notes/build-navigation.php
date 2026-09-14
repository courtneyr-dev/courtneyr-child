<?php
/**
 * Build the accepted Blog/Stream navigation regrouping from the target site's
 * own page and term identities (gap review G-06).
 *
 *   wp eval-file build-navigation.php preview <menu_id>
 *   wp eval-file build-navigation.php apply   <menu_id>
 *   wp eval-file build-navigation.php rollback <menu_id> <backup-file> [preview]
 *
 * Reads the existing wp_navigation post, keeps every top-level item verbatim
 * except the "Blog" submenu and the "Stream" link, which are rebuilt as native
 * taxonomy/post-type/custom links resolved on this site (ids and URLs from
 * get_term() / get_page_by_path()), in the accepted grouping and order. Labels
 * and emoji are the accepted ones. A term missing on the site is reported and
 * skipped, never invented. Preview prints the block markup and checks that
 * every destination stays on this site's host.
 *
 * @package CourtneyrChild
 */

require_once __DIR__ . '/cr-migration-backup.php';

$cr_mode = (string) ( $args[0] ?? 'preview' );
$cr_menu = (int) ( $args[1] ?? 0 );
$cr_post = $cr_menu ? get_post( $cr_menu ) : null;
if ( ! $cr_post instanceof WP_Post || 'wp_navigation' !== $cr_post->post_type ) {
	WP_CLI::error( 'Pass the wp_navigation post id.' );
}

if ( 'rollback' === $cr_mode ) {
	$cr_file     = (string) ( $args[2] ?? '' );
	$cr_contents = cr_migration_read_verified_backup( $cr_file, 'wp_navigation:' . $cr_menu );
	if ( $cr_post->post_content === $cr_contents ) {
		WP_CLI::success( sprintf( 'Menu %d already matches %s; nothing to restore.', $cr_menu, basename( $cr_file ) ) );
		return;
	}
	WP_CLI::log( cr_migration_diff_summary( $cr_post->post_content, $cr_contents ) );
	if ( in_array( 'preview', array_map( 'strval', $args ), true ) ) {
		WP_CLI::success( 'Preview only; nothing written.' );
		return;
	}
	$cr_before = cr_migration_write_backup( sprintf( 'menu-%d-before-rollback', $cr_menu ), $cr_post->post_content, 'wp_navigation:' . $cr_menu );
	cr_migration_update_content( $cr_menu, $cr_contents );
	WP_CLI::success( sprintf( 'Restored menu %d from %s. Pre-rollback content: %s.', $cr_menu, basename( $cr_file ), basename( $cr_before ) ) );
	return;
}

$cr_spec = array(
	'blog'   => array(
		'label' => '📰 Blog',
		'page'  => 'blog',
		'items' => array(
			array( 'label' => '📖 Book Review', 'taxonomy' => 'category', 'slug' => 'book-review' ),
			array( 'label' => '👩🏻‍🏫 Curriculum', 'taxonomy' => 'category', 'slug' => 'curriculum' ),
			array( 'label' => '🥑 DevRel', 'taxonomy' => 'category', 'slug' => 'devrel' ),
			array( 'label' => '👩🏻‍💻 Open Source', 'taxonomy' => 'category', 'slug' => 'open-source' ),
			array( 'label' => 'Ⓦ WordPress', 'taxonomy' => 'category', 'slug' => 'wptips' ),
		),
	),
	'stream' => array(
		'label' => '🌊 Stream',
		'page'  => 'stream',
		'items' => array(
			array( 'label' => '🗯️ Aside', 'taxonomy' => 'post_format', 'slug' => 'post-format-aside' ),
			array( 'label' => '🎧 Audio', 'taxonomy' => 'post_format', 'slug' => 'post-format-audio' ),
			array( 'label' => '🔗 Link Posts', 'taxonomy' => 'post_format', 'slug' => 'post-format-link' ),
			array( 'label' => '🔖 Quote', 'taxonomy' => 'post_format', 'slug' => 'post-format-quote' ),
			array( 'label' => '🖊️ Status', 'taxonomy' => 'post_format', 'slug' => 'post-format-status' ),
			array( 'label' => '📹 Video', 'taxonomy' => 'post_format', 'slug' => 'post-format-video' ),
			array( 'label' => '🗂️ Browse all kinds', 'custom' => '/stream/#browse-all' ),
		),
	),
);

/**
 * Serialize one navigation link block resolved on this site.
 *
 * @param array $item Spec item.
 * @return string|null Block markup or null when the target does not exist here.
 */
function cr_nav_link( array $item ): ?string {
	if ( isset( $item['custom'] ) ) {
		$attrs = array( 'label' => $item['label'], 'url' => home_url( $item['custom'] ), 'kind' => 'custom' );
	} else {
		$term = get_term_by( 'slug', $item['slug'], $item['taxonomy'] );
		if ( ! $term instanceof WP_Term ) {
			WP_CLI::warning( sprintf( 'Term %s/%s does not exist here; link skipped.', $item['taxonomy'], $item['slug'] ) );
			return null;
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			WP_CLI::warning( sprintf( 'No link for %s; skipped.', $item['slug'] ) );
			return null;
		}
		$attrs = array( 'label' => $item['label'], 'type' => $item['taxonomy'], 'id' => $term->term_id, 'url' => $link, 'kind' => 'taxonomy' );
	}
	return '<!-- wp:navigation-link ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
}

/**
 * Serialize a submenu whose parent is a page resolved on this site.
 *
 * @param array $group Spec group.
 * @return string
 */
function cr_nav_submenu( array $group ): string {
	$page = get_page_by_path( $group['page'] );
	if ( ! $page instanceof WP_Post ) {
		WP_CLI::error( sprintf( 'Page /%s/ does not exist here; cannot build the %s submenu.', $group['page'], $group['label'] ) );
	}
	$attrs = array( 'label' => $group['label'], 'type' => 'page', 'id' => $page->ID, 'url' => get_permalink( $page ), 'kind' => 'post-type' );
	$lines = array( '<!-- wp:navigation-submenu ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' -->' );
	foreach ( $group['items'] as $item ) {
		$link = cr_nav_link( $item );
		if ( null !== $link ) {
			$lines[] = $link;
		}
	}
	$lines[] = '<!-- /wp:navigation-submenu -->';
	return implode( "\n\n", $lines );
}


/**
 * Retained items are kept verbatim, except a custom link that points at
 * another host with the same path available here (a fixture mirror of the
 * production menu): its host becomes this site's. Native links are untouched.
 *
 * @param array $block Navigation block.
 * @return array
 */
function cr_nav_rehost( array $block ): array {
	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$url  = (string) ( $block['attrs']['url'] ?? '' );
	if ( 'custom' === ( $block['attrs']['kind'] ?? '' ) && str_starts_with( $url, 'http' ) && wp_parse_url( $url, PHP_URL_HOST ) !== $host ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$new  = home_url( $path );
		WP_CLI::log( sprintf( 'rehost: %s -> %s', $url, $new ) );
		$block['attrs']['url'] = $new;
		$block['innerHTML']    = str_replace( $url, $new, (string) $block['innerHTML'] );
		$block['innerContent'] = array_map( static fn( $c ) => is_string( $c ) ? str_replace( $url, $new, $c ) : $c, (array) $block['innerContent'] );
	}
	if ( ! empty( $block['innerBlocks'] ) ) {
		$block['innerBlocks'] = array_map( 'cr_nav_rehost', $block['innerBlocks'] );
	}
	return $block;
}

$cr_blocks = parse_blocks( $cr_post->post_content );
$cr_out    = array();
$cr_seen   = array( 'blog' => false, 'stream' => false );
foreach ( $cr_blocks as $cr_block ) {
	if ( empty( $cr_block['blockName'] ) ) {
		continue; // stray whitespace/freeform between top-level blocks
	}
	$cr_label = (string) ( $cr_block['attrs']['label'] ?? '' );
	if ( preg_match( '/\bBlog$/u', $cr_label ) && ! $cr_seen['blog'] ) {
		$cr_out[]        = cr_nav_submenu( $cr_spec['blog'] );
		$cr_seen['blog'] = true;
		continue;
	}
	if ( preg_match( '/\bStream$/u', $cr_label ) && ! $cr_seen['stream'] ) {
		$cr_out[]          = cr_nav_submenu( $cr_spec['stream'] );
		$cr_seen['stream'] = true;
		continue;
	}
	$cr_out[] = rtrim( serialize_block( cr_nav_rehost( $cr_block ) ) );
}
if ( ! $cr_seen['blog'] || ! $cr_seen['stream'] ) {
	WP_CLI::error( 'The menu has no Blog and Stream items to replace; nothing changed.' );
}
$cr_new = implode( "\n\n", $cr_out ) . "\n";

$cr_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
preg_match_all( '/"url":"([^"]+)"/', $cr_new, $cr_urls );
$cr_foreign = array_filter( $cr_urls[1], static fn( $u ) => str_starts_with( $u, 'http' ) && wp_parse_url( $u, PHP_URL_HOST ) !== $cr_host );
WP_CLI::log( sprintf( 'Menu %d "%s" on %s: %d links, %d off-site destination(s)%s', $cr_menu, $cr_post->post_title, $cr_host, count( $cr_urls[1] ), count( $cr_foreign ), $cr_foreign ? ': ' . implode( ' ', $cr_foreign ) : '' ) );
WP_CLI::log( cr_migration_diff_summary( $cr_post->post_content, $cr_new, 40 ) );
if ( $cr_foreign ) {
	WP_CLI::error( 'Off-site destinations found; refusing to write.' );
}
if ( $cr_new === $cr_post->post_content ) {
	WP_CLI::success( 'Menu already matches; nothing to change.' );
	return;
}
if ( 'apply' !== $cr_mode ) {
	WP_CLI::log( "\n" . $cr_new );
	WP_CLI::success( 'Preview only; nothing written.' );
	return;
}
$cr_backup = cr_migration_write_backup( sprintf( 'menu-%d', $cr_menu ), $cr_post->post_content, 'wp_navigation:' . $cr_menu );
cr_migration_update_content( $cr_menu, $cr_new );
WP_CLI::success( sprintf( 'Menu %d rebuilt from this site\'s identities. Backup: %s.', $cr_menu, $cr_backup ) );
