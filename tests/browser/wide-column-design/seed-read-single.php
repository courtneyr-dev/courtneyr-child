<?php
/**
 * Local Studio only. Seeds the Read single states and one post per neighbouring kind for the
 * read-single wide-column proof. No pictures are written (typographic covers; nothing touches uploads),
 * no external lookups, no syndication. Every post carries `_cr_seed_rst`; remove by that key.
 *
 *   studio wp eval-file seed-read-single.php          (seed)
 *   studio wp eval-file seed-read-single.php remove   (remove)
 */
if ( 'local' !== wp_get_environment_type() ) {
	echo "Refusing: not local.\n";
	return;
}
$key = '_cr_seed_rst';

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
	if ( $t ) {
		return (int) $t->term_id;
	}
	$m = wp_insert_term( ucfirst( $slug ), 'kind', array( 'slug' => $slug ) );
	return is_wp_error( $m ) ? 0 : (int) $m['term_id'];
}

$n     = 0;
$posts = array();
$make  = static function ( string $label, $kind, string $title, string $content, string $password = '', array $meta = array() ) use ( &$n, &$posts, $admin, $key ): void {
	$date = gmdate( 'Y-m-d H:i:s', strtotime( '2026-08-31 18:00:00 UTC' ) - ( $n++ ) * 86400 );
	$id   = (int) wp_insert_post(
		wp_slash(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post_author'    => $admin,
				'post_title'     => $title,
				'post_name'      => sanitize_title( $label . '-' . $title ),
				'post_date'      => $date,
				'post_date_gmt'  => $date,
				'post_content'   => $content,
				'post_password'  => $password,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => array( $key => $label ),
			)
		),
		true
	);
	$tids = array_filter( array_map( 'rst_term', (array) $kind ) );
	if ( $id && $tids ) {
		wp_set_object_terms( $id, array_values( $tids ), 'kind' );
		wp_update_post( array( 'ID' => $id ) );
	}
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	$posts[ $label ] = $id;
};

// Reads.
$make( 'read-finished', 'read', 'Slow Rivers, Fast Maps', rst_card( 'read-card', array( 'bookTitle' => 'Slow Rivers, Fast Maps', 'authorName' => 'Teodor Ashby', 'readStatus' => 'finished', 'rating' => 4, 'finishedAt' => '2026-08-02', 'startedAt' => '2026-07-01', 'isbn' => '9781234567897', 'publisher' => 'Hogarth Press', 'publishDate' => '1929', 'pageCount' => 192, 'review' => 'A patient book. My <a href="https://example.org/notes">reading notes</a> are public.' ) ) );
$make( 'read-reading', 'read', 'The Cartographer of Small Rooms', rst_card( 'read-card', array( 'bookTitle' => 'The Cartographer of Small Rooms', 'authorName' => 'Odile Fenwick', 'startedAt' => '2026-09-14', 'currentPage' => 88, 'pageCount' => 192, 'publisher' => 'Hogarth Press' ) ) );
$make( 'read-abandoned', 'read', 'The Unfinished Cathedral', rst_card( 'read-card', array( 'bookTitle' => 'The Unfinished Cathedral', 'authorName' => 'Nils Aubrey', 'readStatus' => 'abandoned', 'startedAt' => '2026-07-01', 'finishedAt' => '2026-07-20' ) ) );
$make( 'read-rowless', 'read', 'Notes from the Salt Marsh', rst_card( 'read-card', array( 'bookTitle' => 'Notes from the Salt Marsh', 'authorName' => 'Wren Aldous' ) ) );
$make( 'read-locked', 'read', 'The Sealed Cabinet Diaries', rst_card( 'read-card', array( 'bookTitle' => 'The Sealed Cabinet Diaries', 'authorName' => 'Rook Ellison', 'readStatus' => 'finished', 'rating' => 4, 'review' => 'A review that has to stay behind the password.', 'finishedAt' => '2026-08-05' ) ), 'cr-seed-rst' );
// A Read whose content carries prose after the card (the Micropub shape's cousin): proves prose stays at the reading width.
$make( 'read-prose', 'read', 'A Treatise on Copper Kettles', rst_card( 'read-card', array( 'bookTitle' => 'A Treatise on Copper Kettles', 'authorName' => 'Rafferty Quill', 'readStatus' => 'finished', 'finishedAt' => '2026-08-12', 'isbn' => '9781234567897', 'publisher' => 'Kettle & Sons', 'publishDate' => '1931' ) ) . "\n\n<!-- wp:paragraph -->\n<p>Extra prose written in the editor after the card: it has to keep the reading width and must not stretch to the wide column. This sentence is long enough to wrap across a few lines at any desktop width so the measure is visible.</p>\n<!-- /wp:paragraph -->" );

// Neighbouring kinds.
$make( 'checkin', 'checkin', 'Corner coffee', rst_card( 'checkin-card', array( 'venueName' => 'Corner Coffee', 'locality' => 'Springfield', 'region' => 'IL', 'checkinAt' => '2026-08-20T09:00:00', 'note' => 'Morning stop.' ) ) );
$make( 'comic', 'comic', 'Saga volume one', rst_card( 'comic-card', array( 'title' => 'Saga', 'creators' => 'Brian K. Vaughan', 'readStatus' => 'finished', 'finishedAt' => '2026-08-01' ) ) );
$make( 'quote', 'quote', 'A line worth keeping', "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>A short quotation worth keeping.</p>\n<!-- /wp:paragraph --><cite>Somebody Wise</cite></blockquote>\n<!-- /wp:quote -->" );
$make( 'watch', 'watch', 'A film night', rst_card( 'watch-card', array( 'mediaTitle' => 'A Quiet Film', 'mediaType' => 'movie', 'releaseYear' => 2020, 'rating' => 4, 'watchedAt' => '2026-08-10', 'review' => 'Watched it twice.' ) ) );
$make( 'listen', 'listen', 'On repeat', rst_card( 'listen-card', array( 'trackTitle' => 'Slow River', 'artistName' => 'The Maps', 'albumTitle' => 'Small Rooms', 'listenedAt' => '2026-08-11T10:00:00' ) ) );
$make( 'like', 'like', 'Liked a post', rst_card( 'like-card', array( 'title' => 'A post worth liking', 'url' => 'https://example.org/liked', 'author' => 'Example Author', 'likedAt' => '2026-08-12T10:00:00' ) ) );
$make( 'eat', 'eat', 'Tacos tonight', rst_card( 'eat-card', array( 'name' => 'Street Tacos', 'restaurant' => 'Taqueria Uno', 'cuisine' => 'Mexican', 'rating' => 4, 'ateAt' => '2026-08-13T19:00:00', 'notes' => 'Good salsa.' ) ) );
$make( 'drink', 'drink', 'A cortado', rst_card( 'drink-card', array( 'name' => 'Cortado', 'drinkType' => 'coffee', 'brand' => 'Corner Coffee', 'rating' => 4, 'drankAt' => '2026-08-14T08:00:00', 'notes' => 'Smooth.' ) ) );
$make( 'repost', 'repost', 'Reposted a post', rst_card( 'repost-card', array( 'title' => 'A post worth sharing', 'url' => 'https://example.org/reposted', 'author' => 'Example Author', 'repostedAt' => '2026-08-15T10:00:00' ) ) );
$make( 'favorite', 'favorite', 'Favorited a post', rst_card( 'favorite-card', array( 'title' => 'A post worth starring', 'url' => 'https://example.org/favorited', 'author' => 'Example Author', 'favoritedAt' => '2026-08-16T10:00:00' ) ) );
$make( 'jam', 'jam', 'A jam', rst_card( 'jam-card', array( 'title' => 'Slow River', 'artist' => 'The Maps', 'album' => 'Small Rooms', 'note' => 'On repeat.', 'jammedAt' => '2026-08-17T10:00:00' ) ) );

// Edge cases for the wide column's decision (the review of PR 337).
$note = 'A patient book. My <a href="https://example.org/notes">reading notes</a> are public.';
$make( 'read-rowless-notes', 'read', 'Notes on a Quiet Shelf', rst_card( 'read-card', array( 'bookTitle' => 'Notes on a Quiet Shelf', 'authorName' => 'Wren Aldous', 'review' => $note ) ) );
$make( 'read-rowless-sample', 'read', 'A Sampled Rowless Book', rst_card( 'read-card', array( 'bookTitle' => 'A Sampled Rowless Book', 'authorName' => 'Wren Aldous', 'review' => $note ) ), '', array( '_pkiw_read_asin' => 'B000000001' ) );
$make( 'read-sample', 'read', 'A Sampled Book With A Record', rst_card( 'read-card', array( 'bookTitle' => 'A Sampled Book With A Record', 'authorName' => 'Odile Fenwick', 'readStatus' => 'finished', 'finishedAt' => '2026-08-02', 'isbn' => '9781234567897', 'review' => $note ) ), '', array( '_pkiw_read_asin' => 'B000000002' ) );
$make( 'read-paged', 'read', 'A Paged Read', rst_card( 'read-card', array( 'bookTitle' => 'A Paged Read', 'authorName' => 'Odile Fenwick', 'readStatus' => 'finished', 'finishedAt' => '2026-08-02', 'isbn' => '9781234567897' ) ) . "\n<!--nextpage-->\n<!-- wp:paragraph -->\n<p>Page two is prose with no record at all. It has to stay at the reading width and load no wide column sheet.</p>\n<!-- /wp:paragraph -->" );
$make( 'read-like', array( 'read', 'like' ), 'A Read That Is Also A Like', rst_card( 'read-card', array( 'bookTitle' => 'A Read That Is Also A Like', 'authorName' => 'Odile Fenwick', 'readStatus' => 'finished', 'finishedAt' => '2026-08-02', 'isbn' => '9781234567897' ) ) );
$make( 'read-watch', array( 'read', 'watch' ), 'A Read That Is Also A Watch', rst_card( 'read-card', array( 'bookTitle' => 'A Read That Is Also A Watch', 'authorName' => 'Odile Fenwick', 'readStatus' => 'finished', 'finishedAt' => '2026-08-02', 'isbn' => '9781234567897' ) ) );
$loop = '<!-- wp:query {"queryId":9,"query":{"perPage":1,"pages":0,"offset":0,"postType":"post","order":"asc","orderby":"date","author":"","search":"Hello world","exclude":[EXCLUDE],"sticky":"","inherit":false}} -->'
	. '<div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-content {"className":"single-post__content wp-block-post-content","layout":{"type":"constrained"}} /--><!-- /wp:post-template --></div><!-- /wp:query -->';
$make( 'read-loop', 'read', 'A Read With A Secondary Loop', rst_card( 'read-card', array( 'bookTitle' => 'A Read With A Secondary Loop', 'authorName' => 'Odile Fenwick', 'readStatus' => 'finished', 'finishedAt' => '2026-08-02', 'isbn' => '9781234567897' ) ) . "\n" . $loop );
// The loop must not find the post that holds it (its own saved content names the search).
$loop_post = get_post( $posts['read-loop'] );
wp_update_post( array( 'ID' => $loop_post->ID, 'post_content' => wp_slash( str_replace( 'EXCLUDE', (string) $loop_post->ID, $loop_post->post_content ) ) ) );

foreach ( $posts as $label => $id ) {
	printf( "%s\t%d\t%s\n", $label, $id, get_permalink( $id ) );
}
