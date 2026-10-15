<?php
/**
 * `php tests/php/favorite-stream-test.php`: checks the favorite Stream card
 * (issue 259).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/favorite-bootstrap.php';

use Courtneyr\Child\StreamFavorite as Stream;

/** Run the Stream filter for one post. */
function cr_fav_stream_card( WP_Post $post, string $html = '<article class="pk-card plugin">plugin card</article>', int $level = 2, string $class = '' ): string {
	$block = array( 'attrs' => array( 'headingLevel' => $level, 'className' => $class ) );
	return Stream\card( $html, $block, new WP_Block( array( 'postId' => $post->ID ) ) );
}

cr_fav_group(
	'the card prints the star label, title, author and date with one link',
	static function (): void {
		$GLOBALS['cr_fav_stream'] = true;
		$post = cr_fav_post( 601, 'Field Notes on Slow Feeds', cr_fav_facts( array( 'name' => 'Field Notes on Slow Feeds', 'picture' => array( 'source' => 'card', 'attachment_id' => 0, 'url' => 'https://images.example.com/a.png', 'alt' => 'x', 'remote' => true, 'suppress_featured' => false ) ) ) );
		$out  = cr_fav_stream_card( $post );

		cr_fav_same( 1, cr_fav_count( '/<a\b/', $out ), 'exactly one link' );
		cr_fav_assert( str_contains( $out, '<a class="u-url cr-fav-link" href="https://example.test/favorite-601/">Field Notes on Slow Feeds</a>' ), 'the link goes to the permalink and is named by the title' );
		cr_fav_assert( ! str_contains( $out, 'target=' ) && ! str_contains( $out, 'noopener' ), 'no new-tab link' );
		cr_fav_assert( str_contains( $out, '<span class="cr-fav-star"><svg' ) && str_contains( $out, '<span class="pk-kindlabel">Favorite</span>' ), 'the star carries the live kind label as text' );
		cr_fav_assert( str_contains( $out, '<p class="pk-sub cr-fav-card__author">Rowan Vale</p>' ), 'the cited author prints when stored' );
		cr_fav_assert( str_contains( $out, '<time class="dt-published" datetime="2026-08-28T10:00:00+00:00">August 28, 2026</time>' ), 'the post date prints' );
		cr_fav_assert( ! preg_match( '/<(img|figure|button|iframe)\b|pk-note|pk-excerpt|pk-media|pk-badge|pk-meta|Read more|Curated favorite|rating|A short guide/', $out ), 'no image, description, excerpt, badge, rating, CTA or invented line' );
		cr_fav_assert( str_contains( $out, '<h2 class="pk-title cr-fav-card__title p-name">' ), 'h2 by default with p-name for a real title' );
		cr_fav_assert( str_starts_with( $out, '<article class="cr-fav-card k-favorite">' ) && ! str_contains( $out, 'pk-card' ), 'its own article, not the plugin card shell' );
	}
);

cr_fav_group(
	'the cited URL stays hidden data and never becomes a link',
	static function (): void {
		$GLOBALS['cr_fav_stream'] = true;
		$post = cr_fav_post( 602, 'Cited', cr_fav_facts() );
		$out  = cr_fav_stream_card( $post );
		cr_fav_assert( str_contains( $out, '<span class="pk-kind-cite h-cite u-favorite-of" hidden>' ), 'a hidden h-cite carries favorite-of' );
		cr_fav_assert( str_contains( $out, '<data class="u-url" value="https://notes.example.com/quiet-software"></data>' ), 'with the cited URL as data' );
		cr_fav_assert( str_contains( $out, '<data class="p-name" value="Field Guide to Quiet Software"></data>' ) && str_contains( $out, '<span class="p-author h-card"><data class="p-name" value="Rowan Vale"></data></span>' ), 'and the cited name and author' );
		cr_fav_assert( ! preg_match( '/<a\b[^>]*notes\.example\.com/', $out ), 'the cited URL is not a link' );

		$bare = cr_fav_post( 603, '', cr_fav_facts( array( 'url' => '', 'host' => '', 'author' => '', 'name' => 'Favorite, June 30, 2026', 'name_is_title' => false ) ) );
		$out  = cr_fav_stream_card( $bare );
		cr_fav_assert( ! str_contains( $out, 'u-favorite-of' ) && ! str_contains( $out, 'cr-fav-card__author' ), 'no URL and no author print neither' );
		cr_fav_assert( ! str_contains( $out, 'p-name' ) && str_contains( $out, '>Favorite, June 30, 2026</a>' ), 'a synthetic name has no p-name' );
	}
);

cr_fav_group(
	'the card steps aside off the Stream, in the archive style, for protected posts and other kinds',
	static function (): void {
		$post = cr_fav_post( 604, 'Plain', cr_fav_facts() );
		$same = '<article class="plugin">plugin</article>';

		$GLOBALS['cr_fav_stream'] = false;
		cr_fav_same( $same, cr_fav_stream_card( $post, $same ), 'off the Stream: unchanged' );

		$GLOBALS['cr_fav_stream'] = true;
		cr_fav_same( $same, cr_fav_stream_card( $post, $same, 2, 'is-style-cr-favorite-clipping' ), 'the archive style is left to the archive adapter' );
		cr_fav_same( '', cr_fav_stream_card( $post, '' ), 'empty stays empty' );

		$locked = cr_fav_post( 605, 'Locked', cr_fav_facts(), true );
		cr_fav_same( $same, cr_fav_stream_card( $locked, $same ), 'protected: the plugin card stays' );

		$nofacts = cr_fav_post( 606, 'No facts', array() );
		cr_fav_same( $same, cr_fav_stream_card( $nofacts, $same ), 'no facts: unchanged' );

		$GLOBALS['cr_fav_kinds'][604] = false;
		cr_fav_same( $same, cr_fav_stream_card( $post, $same ), 'another kind: unchanged' );
	}
);

cr_fav_group(
	'heading level follows the block and the filter is hooked on the stream card',
	static function (): void {
		$GLOBALS['cr_fav_stream'] = true;
		$post = cr_fav_post( 607, 'Level', cr_fav_facts() );
		cr_fav_assert( str_contains( cr_fav_stream_card( $post, 'x', 3 ), '<h3 class="pk-title' ), 'level 3' );
		cr_fav_assert( str_contains( cr_fav_stream_card( $post, 'x', 7 ), '<h4 class="pk-title' ), 'level 7 clamps to 4' );
		$hook = array_values( array_filter( $GLOBALS['cr_fav_filters'], static fn( array $f ): bool => 'render_block_post-kinds-indieweb/stream-card' === $f[0] && 10 === $f[2] && 3 === $f[3] ) );
		cr_fav_assert( count( $hook ) >= 2, 'the archive and Stream adapters both run at priority 10' );
	}
);

cr_fav_done();
