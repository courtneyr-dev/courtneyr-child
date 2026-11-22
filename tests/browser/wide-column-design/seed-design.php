<?php
/**
 * Local Studio only. Adds the page-splitting, multi-card and autosave cases of the wide-column design on top of seed-read-single.php. No pictures are written (typographic covers; nothing touches uploads),
 * no external lookups, no syndication. Every post carries `_cr_seed_wcd`; remove by that key.
 *
 *   studio wp eval-file seed-design.php          (seed)
 *   studio wp eval-file seed-design.php remove   (remove)
 */
if ( 'local' !== wp_get_environment_type() ) {
	echo "Refusing: not local.\n";
	return;
}
$key = '_cr_seed_wcd';

function rst_hooks_off(): void {
	global $wp_filter;
	$pat = array(
		'publish_post'           => '/^(Webmention\\\\Sender::publish_hook|_publish_post_hook|IndieBlocks\\\\Webmention\\\\Webmention_Sender::schedule_webmention|Pubsubhubbub\\\\Publisher::publish_post)$/',
		'transition_post_status' => '/(Atmosphere|IndexNow|ping_indexnow|Outpost_|PKIW\\\\Sync)/',
		'wp_after_insert_post'   => '/RSS_Chat/',
		'rest_after_insert_post' => '/RSS_Chat/',
		'save_post'              => '/^(Webmention\\\\Sender::save_hook|PKIW\\\\Standard_Site::schedule_resolution)$/',
	);
	foreach ( $pat as $hook => $re ) {
		if ( empty( $wp_filter[ $hook ] ) ) {
			continue;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
			foreach ( $cbs as $e ) {
				$f = $e['function'];
				$l = is_string( $f ) ? ltrim( $f, '\\' ) : ( is_array( $f ) ? ( ( is_object( $f[0] ) ? get_class( $f[0] ) : $f[0] ) . '::' . $f[1] ) : '' );
				if ( '' !== $l && preg_match( $re, $l ) ) {
					remove_filter( $hook, $f, $prio );
				}
			}
		}
	}
}

$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
wp_set_current_user( $admin );
rst_hooks_off();

if ( isset( $args[0] ) && 'remove' === $args[0] ) {
	$ids = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => $key, 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	echo 'removed ' . count( $ids ) . " posts\n";
	return;
}
add_filter( 'pkiw_set_featured_from_artwork', '__return_false' );
add_filter( 'pkiw_book_completion_service', static function () {
	return new class() {
		public function complete( array $book ): array {
			return array();
		}
	};
} );

function rst_card( string $block, array $attrs ): string {
	return '<!-- wp:post-kinds-indieweb/' . $block . ' ' . serialize_block_attributes( $attrs ) . ' /-->';
}
function rst_term( string $slug ): int {
	$t = get_term_by( 'slug', $slug, 'kind' );
	return $t ? (int) $t->term_id : 0;
}
$rec  = static fn( string $t ): string => rst_card( 'read-card', array( 'bookTitle' => $t, 'authorName' => 'Odile Fenwick', 'readStatus' => 'finished', 'finishedAt' => '2026-08-02', 'isbn' => '9781234567897', 'review' => 'Review text for ' . $t . '.' ) );
$bare = static fn( string $t ): string => rst_card( 'read-card', array( 'bookTitle' => $t, 'authorName' => 'Wren Aldous' ) );
$para = static fn( string $t ): string => "<!-- wp:paragraph -->\n<p>" . $t . "</p>\n<!-- /wp:paragraph -->";

$n     = 40;
$posts = array();
$make  = static function ( string $label, string $kind, string $title, string $content ) use ( &$n, &$posts, $admin, $key ): void {
	$date = gmdate( 'Y-m-d H:i:s', strtotime( '2026-07-31 18:00:00 UTC' ) - ( $n++ ) * 86400 );
	$id   = (int) wp_insert_post( wp_slash( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $admin, 'post_title' => $title, 'post_name' => sanitize_title( $label ), 'post_date' => $date, 'post_date_gmt' => $date, 'post_content' => $content, 'comment_status' => 'closed', 'ping_status' => 'closed', 'meta_input' => array( $key => $label ) ) ), true );
	wp_set_object_terms( $id, array( rst_term( $kind ) ), 'kind' );
	wp_update_post( array( 'ID' => $id ) );
	$posts[ $label ] = $id;
};

$make( 'd-lead-nextpage', 'read', 'Lead Break', "<!--nextpage-->\n" . $rec( 'Lead Break' ) );
$make( 'd-pagebreak-block', 'read', 'Page Break Block', $para( 'Prose on page one before the card.' ) . "\n\n<!-- wp:nextpage -->\n<!--nextpage-->\n<!-- /wp:nextpage -->\n\n" . $rec( 'Page Break Block' ) );
$make( 'd-rowless-then-record', 'read', 'Rowless Then Record', $bare( 'Rowless First' ) . "\n<!--nextpage-->\n" . $rec( 'Record Second' ) );
$make( 'd-record-then-rowless', 'read', 'Record Then Rowless', $rec( 'Record First' ) . "\n<!--nextpage-->\n" . $bare( 'Rowless Second' ) );
$make( 'd-two-cards-one-page', 'read', 'Two Cards One Page', $bare( 'Rowless Card' ) . "\n\n" . $rec( 'Record Card' ) );
$make( 'd-single-record', 'read', 'Single Record', $rec( 'Single Record' ) );
$make( 'd-autosave-remove', 'read', 'Autosave Removes Card', $rec( 'Autosave Removes Card' ) );
$make( 'd-autosave-add', 'read', 'Autosave Adds Card', $para( 'Only prose is saved.' ) );
$make( 'd-repost-two-pages', 'repost', 'Repost On Page Two', $para( 'Intro prose.' ) . "\n<!--nextpage-->\n" . rst_card( 'repost-card', array( 'title' => 'Shared Post', 'url' => 'https://example.org/reposted', 'author' => 'Example Author', 'repostedAt' => '2026-08-15T10:00:00' ) ) );
$make( 'd-favorite-two-pages', 'favorite', 'Favorite Two Pages', rst_card( 'favorite-card', array( 'title' => 'Starred Post', 'url' => 'https://example.org/favorited', 'author' => 'Example Author', 'favoritedAt' => '2026-08-16T10:00:00' ) ) . "\n<!--nextpage-->\n" . $para( 'Page two prose.' ) );


// A secondary loop that prints a record-bearing Read inside a page that is not a Read: the column must stay off.
$loop = '<!-- wp:query {"queryId":11,"query":{"perPage":1,"pages":0,"offset":0,"postType":"post","order":"asc","orderby":"date","author":"","search":"Slow Rivers","sticky":"","inherit":false}} -->'
	. '<div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-content {"className":"single-post__content wp-block-post-content","layout":{"type":"constrained"}} /--><!-- /wp:post-template --></div><!-- /wp:query -->';
$make( 'd-loop-in-checkin', 'checkin', 'Loop Of Reads In A Check-in', $para( 'A check-in whose body lists a Read.' ) . "\n" . $loop );

// Autosaves are the preview content: one removes the card, one adds a record card.
wp_create_post_autosave( array( 'post_ID' => $posts['d-autosave-remove'], 'post_title' => 'Autosave Removes Card', 'post_content' => $para( 'The card was removed in the editor.' ), 'post_excerpt' => '' ) );
wp_create_post_autosave( array( 'post_ID' => $posts['d-autosave-add'], 'post_title' => 'Autosave Adds Card', 'post_content' => $para( 'Only prose is saved.' ) . "\n\n" . $rec( 'Autosave Adds Card' ), 'post_excerpt' => '' ) );

foreach ( $posts as $label => $id ) {
	printf( "%s\t%d\t%s\n", $label, $id, get_permalink( $id ) );
}
