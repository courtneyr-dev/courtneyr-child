<?php
/**
 * Local Studio only. Seeds Favorite and Repost singles for the wide-column proof of those kinds.
 * Pictures point at an image already in uploads (nothing is written there), no external lookups,
 * no syndication. Every post carries `_cr_seed_wck`; remove by that key.
 *
 *   studio wp eval-file seed-kinds.php          (seed)
 *   studio wp eval-file seed-kinds.php remove   (remove)
 */
if ( 'local' !== wp_get_environment_type() ) {
	echo "Refusing: not local.\n";
	return;
}
$key = '_cr_seed_wck';

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
	$date = gmdate( 'Y-m-d H:i:s', strtotime( '2026-05-31 18:00:00 UTC' ) - ( $n++ ) * 86400 );
	$have = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => $key, 'meta_value' => $label, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $have ) {
		// Seeding again only adds what is missing; existing posts keep their IDs and URLs.
		$posts[ $label ] = (int) $have[0];
		return;
	}
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


$img   = home_url( '/wp-content/uploads/2026/11/event.jpg' );
$para  = static function ( string $text ): string {
	return "<!-- wp:paragraph -->\n<p>" . $text . "</p>\n<!-- /wp:paragraph -->";
};
$long  = 'A paragraph written in the editor beside the card. It is long enough to wrap across several lines at every desktop width, so the reading measure is visible and any change to it shows.';
$float = "<!-- wp:image {\"width\":\"100px\",\"sizeSlug\":\"full\",\"align\":\"left\"} -->\n<figure class=\"wp-block-image alignleft size-full is-resized\"><img src=\"" . $img . "\" alt=\"A floated picture\" style=\"width:100px\"/></figure>\n<!-- /wp:image -->";
$wide  = "<!-- wp:group {\"align\":\"wide\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group alignwide\">" . $para( 'A wide group.' ) . "</div>\n<!-- /wp:group -->";
$full  = "<!-- wp:group {\"align\":\"full\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group alignfull\">" . $para( 'A full group.' ) . "</div>\n<!-- /wp:group -->";
$quote = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">" . $para( 'A quotation after the card.' ) . "<cite>Somebody</cite></blockquote>\n<!-- /wp:quote -->";
$list  = "<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>One</li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li>Two</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->";
$group = static function ( string $inner ): string {
	return "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">" . $inner . "</div>\n<!-- /wp:group -->";
};

foreach ( array( 'favorite' => 'favoritedAt', 'repost' => 'repostedAt' ) as $kind => $at ) {
	$k    = substr( $kind, 0, 3 );
	$card = static function ( array $more = array() ) use ( $kind, $at, $img ): string {
		return rst_card(
			$kind . '-card',
			array_merge(
				array(
					'title'       => 'Alt text decision tree',
					'url'         => 'https://example.org/alt-text',
					'author'      => 'Example Author',
					'description' => 'A practical flow for writing useful alternative text.',
					'image'       => $img,
					'imageAlt'    => 'A flowchart of yes and no questions',
					$at           => '2026-09-14',
				),
				$more
			)
		);
	};
	$make( "$k-card", $kind, 'Alt text decision tree', $card() );
	$make( "$k-noimg", $kind, 'A card without a picture', $card( array( 'image' => '', 'imageAlt' => '', 'title' => 'A card without a picture' ) ) );
	$make( "$k-cited", $kind, 'My note on a starred post', $card( array( 'title' => 'A different cited title' ) ) );
	$make( "$k-long", $kind, 'A favorite whose title runs long enough to wrap across two lines of the sheet at desktop width', $card( array( 'title' => 'A favorite whose title runs long enough to wrap across two lines of the sheet at desktop width', 'description' => str_repeat( 'A longer description that wraps. ', 8 ) ) ) );
	$make( "$k-siblings", $kind, 'A card with blocks around it', $para( $long ) . "\n" . $card() . "\n" . $float . $para( $long ) . "\n" . $wide . "\n" . $full . "\n" . $quote . "\n" . $list );
	$make( "$k-nested", $kind, 'A card inside a group', $group( $para( 'Before, inside the group.' ) . $card() . $para( 'After, inside the group.' ) ) );
	$make( "$k-plain", $kind, 'A card-less post', $para( $long ), '', array( "_pkiw_cite_url" => 'https://example.org/plain', "_pkiw_cite_name" => 'A plain cited page' ) );
	$make( "$k-locked", $kind, 'A protected post', $card(), 'cr-seed-wck' );
	$make( "$k-two-pages", $kind, 'Two pages', $card() . "\n<!--nextpage-->\n" . $para( 'Page two prose.' ) );
	$make( "$k-page-two", $kind, 'Card on page two', $para( 'Intro prose.' ) . "\n<!--nextpage-->\n" . $card() );
	$make( "$k-and-like", array( $kind, 'like' ), 'First kind ' . $kind . ', then like', $card() );
	$make( "like-and-$k", array( 'like', $kind ), 'First kind like, then ' . $kind, $card() );
	$make( "$k-two-cards", $kind, 'Two cards on one page', $card() . "\n" . $para( $long ) . "\n" . $card( array( 'title' => 'A second card' ) ) );
}
// A first kind that sorts before both (WordPress returns a post's kinds by name): no decision for either.
foreach ( array( 'favorite', 'repost' ) as $kind ) {
	$make( substr( $kind, 0, 3 ) . '-and-bookmark', array( $kind, 'bookmark' ), 'First kind bookmark, also ' . $kind, rst_card( $kind . '-card', array( 'title' => 'Alt text decision tree', 'url' => 'https://example.org/alt-text', 'author' => 'Example Author' ) ) );
}

foreach ( $posts as $label => $id ) {
	printf( "%s\t%d\t%s\n", $label, $id, get_permalink( $id ) );
}
