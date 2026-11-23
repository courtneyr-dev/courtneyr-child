<?php
/**
 * `php tests/php/favorite-single-test.php`: checks the favorite single sheet
 * (issue 259).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/favorite-bootstrap.php';

use Courtneyr\Child\SingleFavorite as Single;
use Courtneyr\Child\SingleWideColumn as Wide;

/** The plugin card a favorite post prints inside the singular wrapper. */
function cr_fav_card_html( string $extra = '' ): string {
	return '<article class="wp-block-post-kinds-indieweb-favorite-card pk-card k-favorite h-cite u-favorite-of"><div class="pk-badge"><svg></svg></div><div class="pk-body"><span class="pk-kindlabel">Favorite</span><div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://notes.example.com/quiet-software" target="_blank" rel="noopener noreferrer nofollow">Field Guide to Quiet Software</a></h2><p class="pk-sub">Rowan Vale</p></div><p class="pk-note p-content">A short guide.</p>' . $extra . '</div></article>';
}

/** The post-content block HTML as the theme and the plugin leave it. */
function cr_fav_page( string $content, bool $avatar = true ): string {
	return '<div class="entry-content wp-block-post-content single-post__content is-layout-constrained">'
		. ( $avatar ? '<span class="cr-icon-avatar cr-icon-avatar--aside single-post__format-avatar" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="x#post-icon-aside"></use></svg></span>' : '' )
		. '<div class="h-entry kind-favorite pkiw-singular-entry">' . $content
		. '<a class="u-url" href="https://example.test/favorite-1/" hidden></a><time class="dt-published" datetime="2026-08-28T10:00:00+00:00" hidden></time><span hidden><span class="p-author h-card"><span class="p-name">Courtney</span></span></span></div></div>';
}

/** Run the sheet filter for the queried post. */
function cr_fav_sheet( WP_Post $post, string $html ): string {
	$GLOBALS['cr_fav_single']  = true;
	$GLOBALS['cr_fav_queried'] = $post->ID;
	Single\single_facts( true );
	return Single\sheet( $html, array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'single-post__content wp-block-post-content' ) ) );
}

cr_fav_group(
	'a card favorite becomes one sheet with one H1 outside the h-cite',
	static function (): void {
		$post = cr_fav_post( 1, 'Field Guide to Quiet Software', cr_fav_facts( array( 'picture' => array( 'source' => 'card', 'attachment_id' => 0, 'url' => 'https://images.example.com/a.png', 'alt' => 'A flowchart', 'remote' => true, 'suppress_featured' => false ) ) ) );
		$out  = cr_fav_sheet( $post, cr_fav_page( cr_fav_card_html() . '<p>Body after.</p>' ) );

		cr_fav_same( 1, cr_fav_count( '/<h1\b/', $out ), 'exactly one h1' );
		cr_fav_assert( str_contains( $out, '<h1 class="cr-fav-sheet__title p-name">Field Guide to Quiet Software</h1>' ), 'the h1 holds the exact title with p-name' );
		cr_fav_assert( ! preg_match( '/<h1[^>]*>\s*<a/', $out ) && ! preg_match( '/<article[^>]*h-cite[^>]*>(?:(?!<\/article>).)*<h1/s', $out ), 'the h1 is not a link and not inside the h-cite' );
		cr_fav_assert( ! str_contains( $out, 'single-post__format-avatar' ), 'the format avatar is gone' );
		cr_fav_assert( ! str_contains( $out, 'pk-card' ) && ! str_contains( $out, 'pk-title' ) && ! str_contains( $out, 'pk-badge' ), 'the plugin card markup is replaced, not restyled' );
		cr_fav_assert( str_contains( $out, '<article class="cr-fav-sheet__record h-cite u-favorite-of">' ), 'the record is the h-cite with favorite-of' );
		cr_fav_same( 1, cr_fav_count( '/<a\b[^>]*href="https:\/\/notes\.example\.com\/quiet-software"/', $out ), 'exactly one link to the cited URL' );
		cr_fav_assert( str_contains( $out, '<a class="u-url cr-fav-link" href="https://notes.example.com/quiet-software" target="_blank" rel="noopener noreferrer nofollow" aria-label="notes.example.com (opens in a new tab)">notes.example.com</a>' ), 'the Source link is named by its host with the new-tab warning' );
		cr_fav_assert( str_contains( $out, '<dt>By</dt><dd><span class="p-author h-card"><span class="p-name">Rowan Vale</span></span></dd>' ), 'By row with an h-card' );
		cr_fav_assert( str_contains( $out, '<dt>Favorited</dt><dd><time class="dt-published" datetime="2026-08-14">August 14, 2026</time></dd>' ), 'Favorited row prints the stored calendar day' );
		cr_fav_assert( str_contains( $out, '<p class="cr-fav-sheet__description p-content">A short guide.</p>' ), 'the description prints below the rows' );
		cr_fav_assert( str_contains( $out, '<data class="p-name" value="Field Guide to Quiet Software" hidden></data>' ) && ! str_contains( $out, 'cr-fav-sheet__cited' ), 'an equal cited title stays hidden data' );
		cr_fav_assert( str_contains( $out, '<data class="u-photo" value="https://images.example.com/a.png" hidden></data>' ), 'the card image rides as u-photo data' );
		cr_fav_assert( str_contains( $out, '<span class="pk-kindlabel">Favorite</span>' ), 'the tape holds the live kind label' );
		cr_fav_assert( str_contains( $out, '<p>Body after.</p>' ) && strpos( $out, 'cr-fav-sheet__title' ) < strpos( $out, 'Body after.' ), 'body copy follows the sheet' );
		cr_fav_assert( str_contains( $out, '<a class="u-url" href="https://example.test/favorite-1/" hidden></a><time class="dt-published"' ), 'the plugin entry meta stays after the sheet' );
		cr_fav_assert( ! preg_match( '/View favorite source|Curated favorite|Flowchart preview|SAMPLE CONTENT|<figcaption|<button/', $out ), 'no invented copy, caption or button' );
	}
);

cr_fav_group(
	'the picture prints once, eager, with the stored alt and no caption',
	static function (): void {
		$post = cr_fav_post( 2, 'Field Guide', cr_fav_facts( array( 'picture' => array( 'source' => 'featured', 'attachment_id' => 77, 'url' => 'https://example.test/uploads/77.png', 'alt' => 'A flowchart of three questions', 'remote' => false, 'suppress_featured' => true ) ) ) );
		$out  = cr_fav_sheet( $post, cr_fav_page( cr_fav_card_html() ) );
		cr_fav_same( 1, cr_fav_count( '/<img\b/', $out ), 'one image' );
		cr_fav_assert( str_contains( $out, 'alt="A flowchart of three questions"' ), 'the stored alt' );
		cr_fav_assert( str_contains( $out, 'fetchpriority="high"' ) && str_contains( $out, 'skip-lazy' ) && ! str_contains( $out, 'loading=' ), 'eager: fetchpriority high, no loading attribute' );
		cr_fav_assert( str_contains( $out, '<div class="cr-fav-sheet__mount" data-cr-cover-fallback>' ), 'the mount carries the cover-fallback hook' );
		cr_fav_assert( ! str_contains( $out, 'u-photo' ), 'a featured picture adds no u-photo to the h-cite' );

		$remote = cr_fav_post( 3, 'Broken', cr_fav_facts( array( 'picture' => array( 'source' => 'card', 'attachment_id' => 0, 'url' => 'https://images.example.com/missing.png', 'alt' => '', 'remote' => true, 'suppress_featured' => false ) ) ) );
		$out    = cr_fav_sheet( $remote, cr_fav_page( cr_fav_card_html() ) );
		cr_fav_assert( str_contains( $out, '<img class="cr-fav-sheet__img skip-lazy" src="https://images.example.com/missing.png" alt="" fetchpriority="high" decoding="async" data-skip-lazy="1">' ), 'a remote picture prints with alt "", no loading, no srcset' );

		$none = cr_fav_post( 4, 'No picture', cr_fav_facts() );
		cr_fav_assert( ! str_contains( cr_fav_sheet( $none, cr_fav_page( cr_fav_card_html() ) ), 'cr-fav-sheet__mount' ), 'no picture, no mount and no empty frame' );
	}
);

cr_fav_group(
	'the cited title prints once above By when it differs, and rows print only when stored',
	static function (): void {
		$post = cr_fav_post( 5, 'Why this zine stuck with me', cr_fav_facts( array( 'cited_title' => 'Small Press Almanac, Issue 3', 'name' => 'Why this zine stuck with me', 'author' => '', 'favorited' => array( '', '' ), 'summary' => '', 'url' => 'https://zine.example.org/almanac-3', 'host' => 'zine.example.org', 'rel' => '' ) ) );
		$out  = cr_fav_sheet( $post, cr_fav_page( cr_fav_card_html() ) );
		cr_fav_same( 1, cr_fav_count( '/Small Press Almanac, Issue 3/', $out ), 'the cited title prints once' );
		cr_fav_assert( str_contains( $out, '<p class="cr-fav-sheet__cited p-name">Small Press Almanac, Issue 3</p>' ), 'as plain text with p-name' );
		cr_fav_assert( ! str_contains( $out, '<dt>By</dt>' ) && ! str_contains( $out, '<dt>Favorited</dt>' ), 'no By or Favorited row when not stored' );
		cr_fav_assert( ! str_contains( $out, 'p-content' ), 'no empty description' );
		cr_fav_assert( str_contains( $out, 'rel="noopener noreferrer"' ) && ! str_contains( $out, 'nofollow' ), 'a card with no rel adds none' );

		$bare = cr_fav_post( 6, 'Kitchen Radio Hour', cr_fav_facts( array( 'url' => '', 'host' => '', 'cited_title' => 'Kitchen Radio Hour', 'name' => 'Kitchen Radio Hour', 'author' => 'Pell Okafor' ) ) );
		$out  = cr_fav_sheet( $bare, cr_fav_page( cr_fav_card_html() ) );
		cr_fav_assert( ! str_contains( $out, '<dt>Source</dt>' ) && ! str_contains( $out, 'u-favorite-of' ) && ! preg_match( '/<a\b[^>]*target="_blank"/', $out ), 'no URL: no Source row, no favorite-of, no outbound link' );
		cr_fav_assert( str_contains( $out, '<article class="cr-fav-sheet__record h-cite">' ), 'the h-cite stays without the favorite-of class' );

		$date = cr_fav_post( 7, 'Notes on Patient Tools', cr_fav_facts( array( 'favorited' => array( '', '' ) ) ) );
		cr_fav_assert( ! str_contains( cr_fav_sheet( $date, cr_fav_page( cr_fav_card_html() ) ), '<dt>Favorited</dt>' ), 'an invalid stored day prints no Favorited row' );
	}
);

cr_fav_group(
	'untitled favorites take the synthetic name without p-name; a different cited title still prints',
	static function (): void {
		$post = cr_fav_post( 8, '', cr_fav_facts( array( 'cited_title' => 'Quiet Hours Handbook', 'name' => 'Quiet Hours Handbook', 'name_is_title' => false ) ) );
		$out  = cr_fav_sheet( $post, cr_fav_page( cr_fav_card_html() ) );
		cr_fav_assert( str_contains( $out, '<h1 class="cr-fav-sheet__title">Quiet Hours Handbook</h1>' ), 'h1 without p-name' );
		cr_fav_assert( str_contains( $out, '<data class="p-name" value="Quiet Hours Handbook" hidden></data>' ), 'the same cited name stays hidden data' );

		$meta = cr_fav_post( 9, '', cr_fav_facts( array( 'cited_title' => 'Seed Meta Name', 'name' => 'blog.example.net', 'name_is_title' => false, 'url' => 'https://blog.example.net/posts/7', 'host' => 'blog.example.net' ) ) );
		$out  = cr_fav_sheet( $meta, cr_fav_page( cr_fav_card_html() ) );
		cr_fav_assert( str_contains( $out, '<h1 class="cr-fav-sheet__title">blog.example.net</h1>' ) && str_contains( $out, 'cr-fav-sheet__cited p-name">Seed Meta Name</p>' ), 'host as h1, the meta name once as the cited title' );
	}
);

cr_fav_group(
	'a favorite with no card keeps its content inside the same sheet',
	static function (): void {
		$quick = cr_fav_post( 10, 'Seed Recipe Index', cr_fav_facts( array( 'has_card' => false, 'cited_title' => 'Seed Recipe Index', 'name' => 'Seed Recipe Index', 'author' => '', 'summary' => '', 'favorited' => array( '', '' ), 'url' => 'https://recipes.example.com/index', 'host' => 'recipes.example.com', 'rel' => '' ) ) );
		$out   = cr_fav_sheet( $quick, cr_fav_page( '<p>My note about it.</p><div class="pkiw-mf2-data" hidden><data class="u-favorite-of" value="https://recipes.example.com/index"></data></div>' ) );
		cr_fav_same( 1, cr_fav_count( '/<h1\b/', $out ), 'one h1' );
		cr_fav_assert( preg_match( '/<div class="cr-fav-sheet">.*<h1.*<dt>Source<\/dt>.*My note about it\..*<\/div><a class="u-url" href="https:\/\/example\.test\/favorite-1\/" hidden>/s', $out ) === 1, 'star, tape, h1, a Source row and the content all sit inside the sheet' );
		cr_fav_assert( ! preg_match( '/<article[^>]*h-cite/', $out ) && ! preg_match( '/<a\b[^>]*class="[^"]*u-url[^"]*"[^>]*recipes\.example\.com/', $out ), 'the theme writes no h-cite and no u-url on the Source link' );
		cr_fav_same( 1, cr_fav_count( '/<data class="u-favorite-of"/', $out ), 'the plugin keeps its one favorite-of data' );

		$micro = cr_fav_post( 11, '', cr_fav_facts( array( 'has_card' => false, 'cited_title' => '', 'url' => '', 'host' => '', 'name' => 'Favorite, June 24, 2026', 'name_is_title' => false, 'author' => '', 'summary' => '', 'favorited' => array( '', '' ), 'rel' => '' ) ) );
		$out   = cr_fav_sheet( $micro, cr_fav_page( '<p>Favorited <a class="u-favorite-of" href="https://blog.example.net/f10">blog.example.net</a></p>' ) );
		cr_fav_assert( str_contains( $out, '<h1 class="cr-fav-sheet__title">Favorite, June 24, 2026</h1>' ), 'the untitled fallback name' );
		cr_fav_assert( ! str_contains( $out, '<dl' ) && ! str_contains( $out, 'cr-fav-sheet__record' ), 'no rows and no empty record' );
		cr_fav_same( 1, cr_fav_count( '/<a\b[^>]*href="https:\/\/blog\.example\.net\/f10"/', $out ), 'the paragraph keeps its one link' );

		$none = cr_fav_post( 12, '', cr_fav_facts( array( 'has_card' => false, 'cited_title' => '', 'url' => '', 'host' => '', 'name' => 'Favorite, June 30, 2026', 'name_is_title' => false, 'author' => '', 'summary' => '', 'favorited' => array( '', '' ), 'rel' => '' ) ) );
		$out  = cr_fav_sheet( $none, cr_fav_page( '<p>Just a paragraph.</p>' ) );
		cr_fav_assert( str_contains( $out, 'Just a paragraph.' ) && ! str_contains( $out, '<dl' ), 'the paragraph prints with no rows' );
	}
);

cr_fav_group(
	'a protected or unreadable favorite keeps the template header and the page untouched',
	static function (): void {
		$post = cr_fav_post( 13, 'Locked', cr_fav_facts(), true );
		$html = cr_fav_page( cr_fav_card_html() );
		cr_fav_same( $html, cr_fav_sheet( $post, $html ), 'protected: content unchanged' );
		cr_fav_same( false, Single\holds_h1( false, $post ), 'protected: the template h1 stays' );

		$empty = cr_fav_post( 14, 'No facts', array() );
		cr_fav_same( $html, cr_fav_sheet( $empty, $html ), 'no facts: content unchanged' );
	}
);

cr_fav_group(
	'the sheet owns the H1 only on a readable favorite single, and only in the main post content',
	static function (): void {
		$post = cr_fav_post( 15, 'Readable', cr_fav_facts() );
		$GLOBALS['cr_fav_single']  = true;
		$GLOBALS['cr_fav_queried'] = $post->ID;
		Single\single_facts( true );
		cr_fav_same( true, Single\holds_h1( false, $post ), 'a readable favorite single holds the H1' );
		cr_fav_same( true, Single\holds_h1( true, $post ), 'a lane that already holds it keeps it' );

		$html = cr_fav_page( cr_fav_card_html() );
		cr_fav_same( $html, Single\sheet( $html, array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'related-posts' ) ) ), 'another post-content block is untouched' );
		cr_fav_same( $html, Single\sheet( $html, array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__content' ) ) ), 'other blocks are untouched' );

		$GLOBALS['cr_fav_single'] = false;
		Single\single_facts( true );
		cr_fav_same( false, Single\holds_h1( false, $post ), 'off a single, no H1 claim' );

		$filter = array_values( array_filter( $GLOBALS['cr_fav_filters'], static fn( array $f ): bool => 'courtneyr_child_single_object_holds_h1' === $f[0] ) );
		cr_fav_assert( count( $filter ) >= 1, 'the lane hooks the shared H1 filter' );
		$render = array_values( array_filter( $GLOBALS['cr_fav_filters'], static fn( array $f ): bool => 'render_block' === $f[0] && 20 === $f[2] ) );
		cr_fav_assert( count( $render ) >= 1, 'the sheet runs on render_block at 20, after the avatar at 10' );
	}
);

/** The card as WordPress hands it to sheet() on a wide page: its renderer marked it. */
function cr_fav_marked_card( string $extra = '' ): string {
	Wide\marking( true );
	$card = Single\mark_card( cr_fav_card_html( $extra ) );
	Wide\marking( false );
	return $card;
}

cr_fav_group(
	'the wide column decides from the facts, and the sheet prints the facts it decided on',
	static function (): void {
		$post = cr_fav_post( 1, 'Field Guide to Quiet Software', cr_fav_facts() );
		$GLOBALS['cr_fav_single']  = true;
		$GLOBALS['cr_fav_queried'] = $post->ID;
		Single\single_facts( true );
		$decided = Single\page_sheet( $post, 'any content' );
		cr_fav_assert( is_array( $decided ) && true === $decided['wide'] && Single\single_facts() === $decided['data'], 'a readable favorite: wide, with the post facts as data' );

		$GLOBALS['cr_fav_wide_data'] = array_merge( Single\single_facts(), array( 'name' => 'Decided Name' ) );
		$out                         = cr_fav_sheet( $post, cr_fav_page( cr_fav_card_html() ) );
		$GLOBALS['cr_fav_wide_data'] = null;
		cr_fav_assert( str_contains( $out, '<h1 class="cr-fav-sheet__title p-name">Decided Name</h1>' ), 'the sheet prints the decision data, not its own lookup' );

		$out = cr_fav_sheet( $post, cr_fav_page( cr_fav_card_html() ) );
		cr_fav_assert( str_contains( $out, '<h1 class="cr-fav-sheet__title p-name">Field Guide to Quiet Software</h1>' ), 'with no decision the sheet reads the post facts, as before' );

		foreach ( array( 'top' => true, 'none' => true, 'nested' => false ) as $place => $wide ) {
			$GLOBALS['cr_fav_card_place'] = $place;
			$decided                      = Single\page_sheet( $post, 'any content' );
			cr_fav_assert( is_array( $decided ) && $wide === $decided['wide'], "first card $place: wide is " . var_export( $wide, true ) );
		}
		$GLOBALS['cr_fav_card_place'] = 'unknown';
		cr_fav_same( null, Single\page_sheet( $post, 'any content' ), 'a block the walker cannot see inside (a synced pattern): no decision, the page renders as without the column' );
		unset( $GLOBALS['cr_fav_card_place'] );

		$page = cr_fav_page( '<p>Before.</p>' . cr_fav_marked_card() . '<p>After.</p>' );
		$GLOBALS['cr_fav_wide_applies'] = true;
		$other                          = cr_fav_sheet( $post, $page );
		$GLOBALS['cr_fav_wide_data']    = Single\single_facts();
		$wide                           = cr_fav_sheet( $post, $page );
		$GLOBALS['cr_fav_wide_applies'] = false;
		$narrow                         = cr_fav_sheet( $post, $page );
		cr_fav_assert( str_contains( $wide, '<div class="cr-fav-flow"><p>Before.</p></div><div class="cr-fav-sheet">' ) && 1 === preg_match( '/<div class="cr-fav-flow"><p>After\.<\/p><\/div><a class="u-url"/', $wide ), 'wide: the blocks before and after the sheet sit in the reading-width box' );
		cr_fav_assert( ! str_contains( $narrow, 'cr-fav-flow' ) && str_contains( $narrow, '<p>Before.</p><div class="cr-fav-sheet">' ), 'not wide: no box, as before' );
		cr_fav_assert( $other === $narrow, "another kind's column, no favorite decision: no box" );
		cr_fav_assert( str_replace( array( '<div class="cr-fav-flow">', '</div><div class="cr-fav-sheet">', '</div><a class="u-url"' ), array( '', '<div class="cr-fav-sheet">', '<a class="u-url"' ), $wide ) === $narrow, 'wide and narrow differ by the box only' );
		$GLOBALS['cr_fav_wide_applies'] = true;
		$alone                          = cr_fav_sheet( $post, cr_fav_page( cr_fav_marked_card() ) );
		$GLOBALS['cr_fav_wide_applies'] = false;
		$GLOBALS['cr_fav_wide_data']    = null;
		cr_fav_assert( ! str_contains( $alone, 'cr-fav-flow' ), 'wide with nothing beside the sheet: no empty box' );

		$locked = cr_fav_post( 2, 'Locked', array() );
		$GLOBALS['cr_fav_queried'] = $locked->ID;
		Single\single_facts( true );
		cr_fav_assert( null === Single\page_sheet( $locked, 'any content' ), 'no facts (protected, unreadable, not a favorite): no decision' );
	}
);

cr_fav_group(
	'the card renderer marks only the card it printed, only while a wide page renders',
	static function (): void {
		cr_fav_assert( in_array( array( 'render_block_post-kinds-indieweb/favorite-card', 'Courtneyr\\Child\\SingleFavorite\\mark_card', 10, 1 ), $GLOBALS['cr_fav_filters'], true ), 'the marker runs on the favorite card block' );
		$card = cr_fav_card_html();
		cr_fav_same( $card, Single\mark_card( $card ), 'not rendering a wide page: the card is untouched' );
		$marked = cr_fav_marked_card();
		cr_fav_assert( str_starts_with( $marked, '<article data-cr-wide-card="' . Wide\card_mark() . '" class="wp-block-post-kinds-indieweb-favorite-card' ), 'rendering a wide page: the card element carries the token' );
		cr_fav_same( $card, Wide\unmark( $marked ), 'the mark comes off byte for byte' );
		Wide\marking( true );
		$plain = '<div class="k-favorite">x</div>';
		cr_fav_same( $plain, Single\mark_card( $plain ), 'a first tag that is not the card article: untouched' );
		Wide\marking( false );
	}
);

cr_fav_group(
	'on a wide page the sheet replaces the marked card element, never something that only names it',
	static function (): void {
		$post = cr_fav_post( 1, 'Field Guide to Quiet Software', cr_fav_facts() );
		$GLOBALS['cr_fav_wide_applies'] = true;
		$GLOBALS['cr_fav_queried']      = $post->ID;
		$GLOBALS['cr_fav_single']       = true;
		Single\single_facts( true );
		$GLOBALS['cr_fav_wide_data'] = Single\single_facts();
		$fake                        = '<article id="authored"><div><code>&lt;article class="k-favorite"&gt;</code></div></article>';
		$out                         = cr_fav_sheet( $post, cr_fav_page( '<p>Before.</p>' . $fake . cr_fav_marked_card() . '<p>After.</p>' ) );
		cr_fav_assert( str_contains( $out, '<div class="cr-fav-flow"><p>Before.</p>' . $fake . '</div><div class="cr-fav-sheet">' ), 'authored code that names the class stays in the box before the sheet' );
		cr_fav_assert( ! str_contains( $out, 'data-cr-wide-card' ) && ! str_contains( $out, 'pk-badge' ), 'the marked card itself is gone' );

		$group = '<div class="wp-block-group"><p>In the group.</p>' . cr_fav_marked_card() . '<p>Still in the group.</p></div>';
		$out   = cr_fav_sheet( $post, cr_fav_page( '<p>Before.</p>' . $group ) );
		cr_fav_assert( ! str_contains( $out, 'cr-fav-flow' ) && str_contains( $out, '<div class="wp-block-group"><p>In the group.</p><div class="cr-fav-sheet">' ) && str_contains( $out, '<p>Still in the group.</p></div>' ), 'a marked card inside a group: no box, the sheet stays inside the group' );

		$out = cr_fav_sheet( $post, cr_fav_page( '<p>Before.' . cr_fav_marked_card() . '<p>After.</p>' ) );
		cr_fav_assert( ! str_contains( $out, 'cr-fav-flow' ), 'tags that do not balance: no box' );

		$out = cr_fav_sheet( $post, cr_fav_page( '<p>Before.</p>' . cr_fav_card_html() . '<p>After.</p>' ) );
		cr_fav_assert( ! str_contains( $out, 'cr-fav-flow' ) && str_contains( $out, '<p>Before.</p><div class="cr-fav-sheet">' ), 'no marked card: the sheet replaces the card in place, as without the column' );

		$GLOBALS['cr_fav_wide_applies'] = false;
		$GLOBALS['cr_fav_wide_data']    = null;
	}
);

cr_fav_done();
