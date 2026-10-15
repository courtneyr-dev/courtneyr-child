<?php
/**
 * `php tests/php/favorite-archive-test.php`: checks the favorite archive item,
 * template and pattern (issue 259).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/favorite-bootstrap.php';

use Courtneyr\Child\Favorite as Favorite;

/** A stream-card block as the pattern places it. */
function cr_fav_clip_block( int $level = 3 ): array {
	return array( 'attrs' => array( 'headingLevel' => $level, 'className' => 'is-style-cr-favorite-clipping' ) );
}

/** Render one archive item. */
function cr_fav_item( WP_Post $post, array $block = array(), string $html = '<article class="plugin-card">card</article>' ): string {
	$GLOBALS['cr_fav_queried'] = $post->ID;
	return Favorite\item( $html, $block ?: cr_fav_clip_block(), new WP_Block( array( 'postId' => $post->ID ) ) );
}

cr_fav_group(
	'item prints the star and a linked title, and nothing else',
	static function (): void {
		$post = cr_fav_post( 101, 'Field Guide to Quiet Software', cr_fav_facts() );
		$out  = cr_fav_item( $post );
		cr_fav_same( 1, cr_fav_count( '/<a\b/', $out ), 'one anchor' );
		cr_fav_assert( str_contains( $out, 'href="https://example.test/favorite-101/"' ), 'the anchor points at the permalink' );
		cr_fav_assert( str_contains( $out, '>Field Guide to Quiet Software</a>' ), 'the anchor is named by the title' );
		cr_fav_assert( str_contains( $out, '<h3 class="cr-fav-title p-name">' ), 'h3 with p-name when the name is the post title' );
		cr_fav_assert( ! preg_match( '/<(img|time|figure|button)\b/', $out ), 'no image, time, figure or button' );
		cr_fav_assert( ! preg_match( '/Rowan Vale|August|quiet-software\.|A short guide|pk-kindlabel|pk-sub|pk-note|rating|target="_blank"/', str_replace( 'value="https://notes.example.com/quiet-software"', '', $out ) ), 'no author, date, description, label, rating or new-tab link' );
		cr_fav_assert( str_contains( $out, '<data class="u-favorite-of" value="https://notes.example.com/quiet-software" hidden></data>' ), 'the cited URL rides as hidden data' );
		cr_fav_assert( str_contains( $out, 'class="cr-fav-star" aria-hidden="true"' ), 'the star is decorative' );
		cr_fav_assert( ! str_contains( $out, 'tabindex' ), 'nothing else takes a tab stop' );
	}
);

cr_fav_group(
	'a synthetic name carries no p-name and a URL-less favorite no favorite-of data',
	static function (): void {
		$post = cr_fav_post( 102, '', cr_fav_facts( array( 'url' => '', 'host' => '', 'name' => 'Favorite, June 30, 2026', 'name_is_title' => false ) ) );
		$out  = cr_fav_item( $post );
		cr_fav_assert( ! str_contains( $out, 'p-name' ), 'no p-name' );
		cr_fav_assert( ! str_contains( $out, 'u-favorite-of' ), 'no u-favorite-of' );
		cr_fav_assert( str_contains( $out, '>Favorite, June 30, 2026</a>' ), 'the untitled fallback name prints' );

		$bad = cr_fav_post( 103, 'Bad URL', cr_fav_facts( array( 'url' => 'javascript:alert(1)' ) ) );
		cr_fav_assert( ! str_contains( cr_fav_item( $bad ), 'u-favorite-of' ), 'an unsafe URL prints no data' );
	}
);

cr_fav_group(
	'heading level follows the block and clamps to 2-4',
	static function (): void {
		$post = cr_fav_post( 104, 'Title', cr_fav_facts() );
		cr_fav_assert( str_contains( Favorite\item( 'x', cr_fav_clip_block( 2 ), new WP_Block( array( 'postId' => 104 ) ) ), '<h2 class="cr-fav-title' ), 'level 2' );
		cr_fav_assert( str_contains( Favorite\item( 'x', cr_fav_clip_block( 9 ), new WP_Block( array( 'postId' => 104 ) ) ), '<h4 class="cr-fav-title' ), 'level 9 clamps to 4' );
		cr_fav_assert( str_contains( Favorite\item( 'x', cr_fav_clip_block( 0 ), new WP_Block( array( 'postId' => 104 ) ) ), '<h2 class="cr-fav-title' ), 'level 0 clamps to 2' );
	}
);

cr_fav_group(
	'blocks without the clipping style, empty output and other kinds pass through',
	static function (): void {
		$post = cr_fav_post( 105, 'Title', cr_fav_facts() );
		cr_fav_same( '<p>card</p>', Favorite\item( '<p>card</p>', array( 'attrs' => array( 'headingLevel' => 3 ) ), new WP_Block( array( 'postId' => 105 ) ) ), 'no block style, no change' );
		cr_fav_same( '', cr_fav_item( $post, array(), '' ), 'empty stays empty' );
		$GLOBALS['cr_fav_kinds'][105] = false;
		cr_fav_same( '<p>card</p>', cr_fav_item( $post, array(), '<p>card</p>' ), 'a non-favorite is untouched' );
	}
);

cr_fav_group(
	'a protected favorite keeps the plugin card whole: no clipping, no star, no facts, no tilt',
	static function (): void {
		$post = cr_fav_post( 106, 'Locked Favorite Sample', cr_fav_facts(), true );
		$card = '<article class="pk-card pk-card--protected h-entry"><h3 class="pk-title"><a href="https://example.test/favorite-106/">Locked Favorite Sample</a></h3></article>';
		Favorite\last_tilt( '' );
		$out = cr_fav_item( $post, array(), $card );
		cr_fav_same( $card, $out, 'the plugin card comes back byte for byte' );
		cr_fav_assert( ! str_contains( $out, 'cr-fav-' ), 'no favorite class on the protected item' );
		cr_fav_assert( ! str_contains( $out, 'u-favorite-of' ) && ! str_contains( $out, 'Rowan Vale' ), 'no facts leak' );
		cr_fav_same( 1, cr_fav_count( '/<a\b/', $out ), 'one anchor' );
		cr_fav_same( '', Favorite\last_tilt(), 'a protected item does not advance the star tilt' );
	}
);

cr_fav_group(
	'star tilt takes one of four tokens and never repeats the previous one',
	static function (): void {
		$seen = array();
		$prev = '';
		for ( $id = 1; $id <= 200; ++$id ) {
			$token = Favorite\star_tilt( $id, $prev );
			cr_fav_assert( in_array( $token, Favorite\TILTS, true ), 'token ' . $token . ' is one of the four' );
			cr_fav_assert( $token !== $prev, 'item ' . $id . ' differs from the previous token' );
			$seen[ $token ] = true;
			$prev           = $token;
		}
		cr_fav_same( 4, count( $seen ), 'all four tokens appear' );
		cr_fav_same( Favorite\star_tilt( 77, '' ), Favorite\star_tilt( 77, '' ), 'the same post gets the same token' );
		Favorite\last_tilt( '--cr-rotate-1' );
		Favorite\reset_tilt( array( 'blockName' => 'core/post-template' ) );
		cr_fav_same( '', Favorite\last_tilt(), 'a new Post Template starts with no previous star' );
		Favorite\last_tilt( '--cr-rotate-2' );
		Favorite\reset_tilt( array( 'blockName' => 'core/paragraph' ) );
		cr_fav_same( '--cr-rotate-2', Favorite\last_tilt(), 'other blocks leave it alone' );
	}
);

cr_fav_group(
	'two items in one render never lean the same way',
	static function (): void {
		Favorite\reset_tilt( array( 'blockName' => 'core/post-template' ) );
		$tokens = array();
		for ( $id = 300; $id < 340; ++$id ) {
			$post = cr_fav_post( $id, 'Item ' . $id, cr_fav_facts() );
			preg_match( '/--cr-fav-tilt:var\((--cr-rotate-[a-z0-9]+)\)/', cr_fav_item( $post ), $m );
			$tokens[] = $m[1] ?? '';
		}
		for ( $i = 1; $i < count( $tokens ); ++$i ) {
			cr_fav_assert( '' !== $tokens[ $i ] && $tokens[ $i ] !== $tokens[ $i - 1 ], 'item ' . $i . ' leans differently from item ' . ( $i - 1 ) );
		}
	}
);

cr_fav_group(
	'two favorites with one name name their links by title and month',
	static function (): void {
		$a = cr_fav_post( 401, 'Garden Planner Notes', cr_fav_facts( array( 'name' => 'Garden Planner Notes' ) ) );
		$b = cr_fav_post( 402, 'garden planner  notes', cr_fav_facts( array( 'name' => 'garden planner  notes' ) ) );
		$c = cr_fav_post( 403, 'Unique', cr_fav_facts( array( 'name' => 'Unique' ) ) );
		$GLOBALS['wp_query']                 = (object) array( 'posts' => array( $a, $b, $c ) );
		$GLOBALS['cr_fav_months'][401]       = '2026-06';
		$GLOBALS['cr_fav_months'][402]       = '2026-03';
		$one = cr_fav_item( $a );
		$two = cr_fav_item( $b );
		cr_fav_assert( str_contains( $one, 'aria-labelledby="cr-fav-title-401 pkiw-group-2026-06"' ) && str_contains( $one, '<span id="cr-fav-title-401">Garden Planner Notes</span>' ), 'the June copy names its month' );
		cr_fav_assert( str_contains( $two, 'aria-labelledby="cr-fav-title-402 pkiw-group-2026-03"' ), 'the March copy names its month' );
		cr_fav_assert( ! str_contains( cr_fav_item( $c ), 'aria-labelledby' ), 'a unique name keeps a plain link' );
		$GLOBALS['wp_query'] = null;
	}
);

cr_fav_group(
	'the block style registers and the stylesheet loads where a favorite can appear',
	static function (): void {
		Favorite\register_item_style();
		cr_fav_same( 'post-kinds-indieweb/stream-card', $GLOBALS['cr_fav_styles'][0][0] ?? '', 'the style is registered on the stream card' );
		cr_fav_same( 'cr-favorite-clipping', $GLOBALS['cr_fav_styles'][0][1]['name'] ?? '', 'the style is cr-favorite-clipping' );

		$GLOBALS['cr_fav_enqueued'] = array();
		Favorite\enqueue_styles();
		cr_fav_same( 0, count( $GLOBALS['cr_fav_enqueued'] ), 'nothing loads on an unrelated page' );

		foreach ( array( 'cr_fav_tax', 'cr_fav_admin', 'cr_fav_stream' ) as $flag ) {
			$GLOBALS[ $flag ]           = true;
			$GLOBALS['cr_fav_enqueued'] = array();
			Favorite\enqueue_styles();
			cr_fav_same( 'courtneyr-favorite', $GLOBALS['cr_fav_enqueued'][0][0] ?? '', $flag . ' loads the sheet' );
			cr_fav_same( array( 'courtneyr-paper', 'courtneyr-sticker' ), $GLOBALS['cr_fav_enqueued'][0][2] ?? array(), 'with the paper and sticker sheets' );
			$GLOBALS[ $flag ] = false;
		}

		$post                      = cr_fav_post( 501, 'Single', cr_fav_facts() );
		$GLOBALS['cr_fav_single']  = true;
		$GLOBALS['cr_fav_queried'] = $post->ID;
		$GLOBALS['cr_fav_enqueued'] = array();
		Favorite\enqueue_styles();
		cr_fav_same( 'courtneyr-favorite', $GLOBALS['cr_fav_enqueued'][0][0] ?? '', 'a favorite single loads the sheet' );
		$GLOBALS['cr_fav_single'] = false;
	}
);

cr_fav_group(
	'template and pattern follow the approved archive shell',
	static function (): void {
		$template = (string) file_get_contents( CR_FAV_THEME_DIR . '/templates/taxonomy-kind-favorite.html' );
		cr_fav_assert( str_contains( $template, '<!-- wp:courtneyr/archive-identity /-->' ), 'the archive-identity badge' );
		cr_fav_assert( str_contains( $template, '"type":"archive","showPrefix":false' ), 'the query title without a prefix' );
		cr_fav_assert( str_contains( $template, '<!-- wp:term-description' ), 'the term description' );
		cr_fav_assert( str_contains( $template, 'is-style-cr-marker-bar-short' ), 'the marker-bar separator' );
		cr_fav_assert( str_contains( $template, 'cr-archive cr-archive--stream cr-archive--favorite' ), 'the favorite main class' );
		cr_fav_assert( str_contains( $template, 'courtneyr-child/cr-favorite-star-chart-loop' ) && str_contains( $template, 'courtneyr-child/cr-browse-all' ), 'the loop pattern, then browse-all' );
		cr_fav_assert( ! preg_match( '/SAMPLE|Stream · Kind|curated by month/i', $template ), 'no sample marker, tape or invented footer' );

		$pattern = (string) file_get_contents( CR_FAV_THEME_DIR . '/patterns/cr-favorite-star-chart-loop.php' );
		cr_fav_assert( str_contains( $pattern, '"queryId":259' ) && str_contains( $pattern, '"inherit":true' ), 'an inherited query with id 259' );
		cr_fav_assert( str_contains( $pattern, 'post-kinds-indieweb/archive-sections {"linesPerPage":12,"headingLevel":2}' ), '12 a page, H2 month labels' );
		cr_fav_assert( str_contains( $pattern, 'post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-favorite-clipping"}' ), 'H3 clippings in the favorite style' );
		cr_fav_assert( str_contains( $pattern, 'WP_Block_Type_Registry' ) && 2 === cr_fav_count( '/is_registered\(/', $pattern ), 'both plugin blocks sit behind is_registered' );
		cr_fav_assert( str_contains( $pattern, 'wp:query-no-results' ) && str_contains( $pattern, 'wp:query-pagination ' ), 'the empty state and core pagination' );
		cr_fav_assert( strpos( $pattern, 'wp:query-no-results' ) < strpos( $pattern, 'wp:query-pagination ' ), 'the pager is the last element after the loop' );

		$sizes = (string) file_get_contents( CR_FAV_THEME_DIR . '/inc/theme-supports.php' );
		cr_fav_assert( ! preg_match( '/courtneyr_child_kind_archive_page_sizes[^;]*favorite/s', $sizes ), 'favorite stays out of courtneyr_child_kind_archive_page_sizes' );
	}
);

cr_fav_done();
