<?php
/**
 * Standalone read-shelf adapter tests and static archive fixture.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/read-bootstrap.php';
require dirname( __DIR__, 2 ) . '/inc/read-shelf.php';

use Courtneyr\Child\ReadShelf as Shelf;
use PKIW\Grouped_Archive;
use PKIW\Shelf_Group;

/** A plugin-shaped read card before the shelf adapter cuts it down. */
function cr_shelf_card_fixture( string $title = 'The Midnight Library', string $author = 'Matt Haig' ): string {
	return '<article class="pk-card k-read h-cite u-read-of"><div class="pk-badge">badge</div><div class="pk-body">'
		. '<span class="pk-kindlabel">Read</span><div class="pk-caption"><data class="u-url" value="https://books.test/midnight" hidden></data>'
		. '<h2 class="pk-title p-name"><a href="https://books.test/midnight" target="_blank" rel="external">' . esc_html( $title ) . '</a></h2>'
		. ( '' !== $author ? '<p class="pk-sub"><span class="p-author h-card"><span class="p-name">' . esc_html( $author ) . '</span></span></p>' : '' )
		. '<p class="pk-sub"><span>Finished</span> — Viking, 2020</p><p class="pk-sub pk-stream-date"><time class="dt-published">October 9, 2026</time></p></div>'
		. '<div class="pk-progress">200 of 300 pages 67%</div><div class="pk-stars" role="img" aria-label="Rated 5 of 5">stars</div><data class="p-rating" value="5" hidden></data>'
		. '<div class="pk-media"><img class="pk-thumb--poster u-photo" src="old.jpg" alt="Old cover"></div><div class="pk-note p-content">Review copy</div>'
		. '<div class="pk-meta"><a class="pk-link" href="#">Read more</a></div></div><data value="https://books.test/midnight" hidden></data>'
		. '<data class="p-isbn" value="9780525559474" hidden></data></article>';
}

/** A plugin-shaped generic card for Micropub and meta-only reads. */
function cr_shelf_generic_fixture( string $title = 'Post title' ): string {
	return '<article class="pk-card pk-card--stream k-read h-entry"><div class="pk-badge">badge</div><div class="pk-body"><span class="pk-kindlabel">Read</span>'
		. '<div class="pk-caption"><h2 class="pk-title p-name"><a class="u-url" href="https://example.test/original">' . esc_html( $title ) . '</a></h2>'
		. '<p class="pk-sub pk-stream-date"><time class="dt-published">October 9, 2026</time></p></div><div class="pk-media pk-media--stream"><img src="generic.jpg" alt=""></div>'
		. '<p class="pk-excerpt p-summary">Excerpt</p><span class="pk-kind-cite h-cite u-read-of" hidden><data class="p-name" value="Book"></data></span>'
		. '<div class="pk-meta"><a class="pk-link" href="#">Read more</a></div></div></article>';
}

/** The plugin's password-protected card. */
function cr_shelf_protected_fixture( string $title = 'Private notes' ): string {
	return '<article class="pk-card pk-card--stream pk-card--protected k-read h-entry"><h2 class="pk-title p-name"><a class="u-url" href="#">' . esc_html( $title ) . '</a></h2></article>';
}

/** Normalized shelf_item input. */
function cr_shelf_item( array $overrides = array() ): array {
	return array_merge(
		array(
			'post_id' => 42,
			'uid' => '101',
			'title' => 'The Midnight Library',
			'author' => 'Matt Haig',
			'permalink' => 'https://example.test/read/42',
			'shape' => 'faceout',
			'level' => 3,
			'picture' => array( 'url' => 'https://media.test/cover.jpg', 'attachment_id' => 0, 'remote' => true ),
			'paper' => '',
			'eager' => true,
			'chain' => 1,
			'shelf_id' => 'pkiw-group-reading',
			'date_iso' => '2026-10-09T12:00:00+00:00',
			'date_text' => 'October 9, 2026',
			'size_attr' => '(min-width: 64rem) 22vw, (min-width: 40rem) 30vw, 46vw',
		),
		$overrides
	);
}

/** Visible text after nodes carrying hidden are removed. */
function cr_shelf_visible_text( string $html ): string {
	$previous = libxml_use_internal_errors( true );
	$doc      = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8" ?><div id="scope">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	$xpath = new DOMXPath( $doc );
	foreach ( iterator_to_array( $xpath->query( '//*[@hidden]' ) ) as $node ) {
		$node->parentNode?->removeChild( $node );
	}
	$text = (string) $xpath->query( '//*[@id="scope"]' )->item( 0 )?->textContent;
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

/** Configure the grouped-archive stub. */
function cr_shelf_group_state( bool $sectioning, bool $preview, ?Shelf_Group $group ): void {
	Grouped_Archive::$sectioning = $sectioning;
	Grouped_Archive::$preview    = $preview;
	Grouped_Archive::$group      = $group;
}

/** Render read-card markup from block attrs in shell tests. */
function cr_shelf_render_stub( array $block ): string {
	if ( isset( $block['rendered'] ) ) {
		return (string) $block['rendered'];
	}
	$a = (array) ( $block['attrs'] ?? array() );
	return cr_shelf_card_fixture( (string) ( $a['bookTitle'] ?? 'Untitled' ), (string) ( $a['authorName'] ?? '' ) );
}

/** Style block attrs. */
function cr_shelf_style_block(): array {
	return array( 'attrs' => array( 'className' => 'is-style-cr-shelf-book' ) );
}

/** Reset all shell state. */
function cr_shelf_shell_reset(): WP_Post {
	$post = cr_read_reset();
	Shelf\reset_page_state();
	cr_shelf_group_state( true, false, new Shelf_Group( 'reading', 'reading' ) );
	$GLOBALS['cr_read_render'] = 'cr_shelf_render_stub';
	return $post;
}

if ( ! in_array( '--html', $argv, true ) ) {
	cr_read_group(
		'shapes and heading ids',
		static function (): void {
			foreach ( array( 'reading', 'to-read' ) as $key ) {
				cr_read_same( 'faceout', Shelf\shape_of( $key, true ), $key . ' is face-out' );
			}
			foreach ( array( 'finished', 'abandoned', '' ) as $key ) {
				cr_read_same( 'slab', Shelf\shape_of( $key, true ), $key . ' is slab' );
			}
			cr_read_same( 'slab', Shelf\shape_of( 'reading', false ), 'unsectioned is slab' );
			cr_read_same( 'pkiw-group-reading', Shelf\group_heading_id( 'reading' ), 'reading id' );
			cr_read_same( 'pkiw-group-to-read', Shelf\group_heading_id( 'to-read' ), 'to-read id' );
			cr_read_same( 'pkiw-group-empty', Shelf\group_heading_id( '' ), 'empty id' );
			cr_read_same( 'pkiw-group-a20b', Shelf\group_heading_id( 'a%20b' ), 'encoded id' );
		}
	);

	cr_read_group(
		'progressive accessible names',
		static function (): void {
			$used = array();
			$a = Shelf\name_candidates( 'The Quiet Orchard', 'Ada North', 'reading', 'October 8' );
			$b = Shelf\name_candidates( 'The Quiet Orchard', 'Ben South', 'reading', 'October 9' );
			cr_read_same( 1, Shelf\choose_level( $a, $used ), 'first author pair' );
			cr_read_same( 2, Shelf\choose_level( $b, $used ), 'different author' );
			$used = array();
			cr_read_same( 1, Shelf\choose_level( Shelf\name_candidates( 'Same', 'Author', 'reading', 'October 8' ), $used ), 'first shelf pair' );
			cr_read_same( 3, Shelf\choose_level( Shelf\name_candidates( 'Same', 'Author', 'finished', 'October 9' ), $used ), 'different shelf' );
			$used = array();
			cr_read_same( 1, Shelf\choose_level( Shelf\name_candidates( 'Same', 'Author', 'finished', 'October 8' ), $used ), 'first date pair' );
			cr_read_same( 4, Shelf\choose_level( Shelf\name_candidates( 'Same', 'Author', 'finished', 'October 9' ), $used ), 'different date' );
			$empty_author = Shelf\name_candidates( 'Same', '', 'finished', 'October 9' );
			cr_read_assert( ! isset( $empty_author[2] ) && isset( $empty_author[3] ), 'empty author skips level 2' );
		}
	);

	cr_read_group(
		'p-name only for real titles',
		static function (): void {
			$post = cr_shelf_shell_reset();
			$GLOBALS['cr_read_blocks'] = array(
				array(
					'blockName' => 'post-kinds-indieweb/read-card',
					'attrs'     => array( 'bookTitle' => 'Named Book' ),
					'rendered'  => cr_shelf_card_fixture( 'Named Book', '' ),
				),
			);
			$out = Shelf\shelf_card( cr_shelf_card_fixture( 'Named Book', '' ), cr_shelf_style_block(), new WP_Block( array( 'postId' => $post->ID ) ) );
			cr_read_contains( '<h3 class="pk-title p-name">', $out, 'bookTitle is named' );

			$post             = cr_shelf_shell_reset();
			$post->post_title = '';
			$GLOBALS['cr_read_blocks'] = array(
				array(
					'blockName' => 'post-kinds-indieweb/read-card',
					'attrs'     => array( 'bookTitle' => 'Named Book' ),
					'rendered'  => cr_shelf_card_fixture( 'Named Book', '' ),
				),
			);
			$out = Shelf\shelf_card( cr_shelf_card_fixture( 'Named Book', '' ), cr_shelf_style_block(), new WP_Block( array( 'postId' => $post->ID ) ) );
			cr_read_contains( '<h3 class="pk-title p-name">', $out, 'bookTitle alone is named' );

			$post = cr_shelf_shell_reset();
			$out  = Shelf\shelf_card( cr_shelf_generic_fixture(), cr_shelf_style_block(), new WP_Block( array( 'postId' => $post->ID ) ) );
			cr_read_contains( '<h3 class="pk-title p-name">', $out, 'stored post title is named' );

			$post             = cr_shelf_shell_reset();
			$post->post_title = '';
			$out              = Shelf\shelf_card( cr_shelf_generic_fixture(), cr_shelf_style_block(), new WP_Block( array( 'postId' => $post->ID ) ) );
			cr_read_contains( '<h3 class="pk-title"><a', $out, 'synthetic title is not named' );
			cr_read_not_contains( '<h3 class="pk-title p-name">', $out, 'synthetic heading omits p-name' );
			cr_read_contains( '>Untitled</a></h3>', $out, 'synthetic title remains visible' );

			// The bootstrap always defines \PKIW\untitled_name(), so PHP cannot exercise the post_name fallback in-process.

			$unnamed = Shelf\title_heading( cr_shelf_item( array( 'title' => 'Untitled', 'named' => false ) ) );
			cr_read_contains( '<h3 class="pk-title"><a', $unnamed, 'direct unnamed item omits p-name' );
			cr_read_not_contains( 'p-name', $unnamed, 'direct unnamed item has no p-name' );
			$named = Shelf\title_heading( cr_shelf_item( array( 'named' => true ) ) );
			cr_read_contains( '<h3 class="pk-title p-name">', $named, 'direct named item has p-name' );
			$default = Shelf\title_heading( cr_shelf_item() );
			cr_read_contains( '<h3 class="pk-title p-name">', $default, 'missing named key defaults to named' );
		}
	);

	cr_read_group(
		'shelf item reduction and naming',
		static function (): void {
			$out = Shelf\shelf_item( cr_shelf_card_fixture(), cr_shelf_item() );
			cr_read_same( 'The Midnight Library Matt Haig', cr_shelf_visible_text( $out ), 'face-out visible text' );
			cr_read_same( 1, substr_count( $out, '<a ' ), 'one anchor' );
			foreach ( array( 'pk-kindlabel', 'pk-badge', 'pk-progress', 'pk-stars', 'p-rating', 'pk-note', 'pk-meta', 'pk-stream-date', 'pk-excerpt', 'pk-link', 'pk-media', 'Read more', 'Rated', 'of 5', 'pages', '%', 'Finished' ) as $gone ) {
				cr_read_not_contains( $gone, $out, $gone . ' cut' );
			}
			cr_read_contains( 'data-title="The Midnight Library"', $out, 'type data kept' );
			cr_read_contains( '<h3 class="pk-title p-name">', $out, 'h3 set' );
			cr_read_not_contains( '<h2', $out, 'no stray h2' );
			cr_read_contains( 'href="https://example.test/read/42"', $out, 'permalink set' );
			cr_read_not_contains( 'target=', $out, 'target removed' );
			cr_read_not_contains( ' rel=', $out, 'rel removed' );
			cr_read_not_contains( 'aria-labelledby', $out, 'chain one unnamed' );
			cr_read_assert( strpos( $out, '</article><time ' ) !== false, 'h-cite date follows article' );
			cr_read_contains( '<data class="u-url"', $out, 'book url data kept' );
			cr_read_contains( 'class="p-isbn"', $out, 'isbn kept' );
			cr_read_contains( 'h-cite u-read-of', $out, 'h-cite classes kept' );

			$generic = Shelf\shelf_item( cr_shelf_generic_fixture(), cr_shelf_item( array( 'shape' => 'slab', 'level' => 2, 'picture' => null, 'paper' => 'sky-blue', 'chain' => 4 ) ) );
			cr_read_contains( '<h2 class="pk-title p-name">', $generic, 'h2 set' );
			cr_read_assert( strpos( $generic, '<time class="dt-published cr-shelf-book__date"' ) < strpos( $generic, '</article>' ), 'h-entry date inside article' );
			$label = 'aria-labelledby="cr-shelf-t-101 cr-shelf-a-101 pkiw-group-reading cr-shelf-d-101"';
			cr_read_contains( $label, $generic, 'chain four ids ordered' );
			foreach ( array( 'cr-shelf-t-101', 'cr-shelf-a-101', 'cr-shelf-d-101' ) as $id ) {
				cr_read_same( 1, substr_count( $generic, 'id="' . $id . '"' ), $id . ' occurs once' );
			}
			$plain = Shelf\shelf_item( cr_shelf_protected_fixture( 'TITLE' ), cr_shelf_item( array( 'title' => 'TITLE', 'author' => '', 'shape' => 'slab', 'level' => 2, 'picture' => null, 'paper' => '' ) ) );
			cr_read_same( 'TITLE', cr_shelf_visible_text( $plain ), 'title-only visible text' );
		}
	);

	cr_read_group(
		'covers and paper assignment',
		static function (): void {
			$eager = Shelf\shelf_item( cr_shelf_card_fixture(), cr_shelf_item() );
			cr_read_same( 1, substr_count( $eager, '<img ' ), 'one image' );
			foreach ( array( 'alt=""', 'cr-shelf-book__img u-photo skip-lazy', 'loading="eager"', 'fetchpriority="high"', 'data-skip-lazy="1"', 'data-cr-cover-fallback', 'aria-hidden="true"', 'data-author="Matt Haig"', 'has-cover' ) as $part ) {
				cr_read_contains( $part, $eager, 'eager cover ' . $part );
			}
			$lazy = Shelf\shelf_item( cr_shelf_card_fixture(), cr_shelf_item( array( 'eager' => false ) ) );
			cr_read_contains( 'loading="lazy"', $lazy, 'lazy cover' );
			cr_read_not_contains( 'fetchpriority', $lazy, 'lazy priority absent' );
			cr_read_not_contains( 'data-skip-lazy', $lazy, 'lazy skip absent' );
			$type = Shelf\shelf_item( cr_shelf_card_fixture(), cr_shelf_item( array( 'picture' => null ) ) );
			cr_read_contains( 'cr-shelf-book__cover--type', $type, 'type cover class' );
			cr_read_not_contains( '<img ', $type, 'type cover no image' );
			$papers = array();
			for ( $id = 1; $id <= 20; ++$id ) {
				$paper = Courtneyr\Child\Stamps\seeded_paper( $id, Shelf\PAPERS );
				$papers[] = $paper;
				$slab = Shelf\shelf_item( cr_shelf_card_fixture(), cr_shelf_item( array( 'post_id' => $id, 'shape' => 'slab', 'level' => 2, 'picture' => null, 'paper' => $paper ) ) );
				cr_read_same( 1, preg_match_all( '/cr-shelf-book--paper-[a-z-]+/', $slab ), 'one paper class ' . $id );
				cr_read_contains( 'cr-shelf-book--paper-' . $paper, $slab, 'seeded paper ' . $id );
				cr_read_same( $paper, Courtneyr\Child\Stamps\seeded_paper( $id, Shelf\PAPERS ), 'paper stable ' . $id );
			}
			cr_read_assert( 3 <= count( array_unique( $papers ) ), 'three papers across twenty ids' );
			$slab = Shelf\shelf_item( cr_shelf_card_fixture(), cr_shelf_item( array( 'shape' => 'slab', 'level' => 2, 'picture' => null, 'paper' => 'sky-blue' ) ) );
			cr_read_not_contains( 'cr-shelf-book__cover', $slab, 'slab has no cover' );
			cr_read_not_contains( '<img ', $slab, 'slab has no image' );
		}
	);

	cr_read_group(
		'shelf card shell and archive state',
		static function (): void {
			$post = cr_shelf_shell_reset();
			$input = cr_shelf_generic_fixture();
			cr_read_same( $input, Shelf\shelf_card( $input, array( 'attrs' => array() ), new WP_Block( array( 'postId' => 42 ) ) ), 'style guard' );
			$GLOBALS['cr_read_has_term'] = false;
			cr_read_same( $input, Shelf\shelf_card( $input, cr_shelf_style_block(), new WP_Block( array( 'postId' => 42 ) ) ), 'term guard' );

			$post = cr_shelf_shell_reset();
			$post->post_title = 'Private notes';
			$GLOBALS['cr_read_protected'][42] = true;
			$GLOBALS['cr_read_blocks'] = array( array( 'blockName' => 'post-kinds-indieweb/read-card', 'attrs' => array( 'bookTitle' => 'Secret book', 'authorName' => 'Secret author' ) ) );
			$GLOBALS['cr_read_pictures'][42] = array( 'source' => 'cover', 'attachment_id' => 0, 'url' => 'https://media.test/secret.jpg' );
			$out = Shelf\shelf_card( cr_shelf_protected_fixture(), cr_shelf_style_block(), new WP_Block( array( 'postId' => 42 ) ) );
			cr_read_same( 'Private notes', cr_shelf_visible_text( $out ), 'protected title only' );
			cr_read_not_contains( '<img ', $out, 'protected cover omitted' );
			cr_read_contains( 'cr-shelf-book__cover--type', $out, 'protected face-out gets type cover' );

			$post = cr_shelf_shell_reset();
			$block = array( 'blockName' => 'post-kinds-indieweb/read-card', 'attrs' => array( 'bookTitle' => 'Micropub Book', 'authorName' => 'Mira Page' ), 'rendered' => cr_shelf_card_fixture( 'Micropub Book', 'Mira Page' ) );
			$GLOBALS['cr_read_blocks'] = array( array( 'blockName' => 'core/group', 'innerBlocks' => array( $block ) ) );
			$out = Shelf\shelf_card( cr_shelf_generic_fixture(), cr_shelf_style_block(), new WP_Block( array( 'postId' => 42 ) ) );
			cr_read_same( 'Micropub Book Mira Page', cr_shelf_visible_text( $out ), 'Micropub card rebuilt' );

			$post = cr_shelf_shell_reset();
			$GLOBALS['cr_read_meta'][42] = array( '_pkiw_read_title' => 'Meta Book', '_pkiw_read_author' => 'Meta Writer', '_pkiw_read_status' => 'reading' );
			$out = Shelf\shelf_card( cr_shelf_generic_fixture(), cr_shelf_style_block(), new WP_Block( array( 'postId' => 42 ) ) );
			cr_read_same( 'Meta Book Meta Writer', cr_shelf_visible_text( $out ), 'meta card rebuilt' );

			cr_shelf_shell_reset();
			Shelf\reset_page_state();
			$loads = array();
			for ( $i = 1; $i <= 5; ++$i ) {
				$post = new WP_Post( $i, 'Book ' . $i );
				$GLOBALS['cr_read_post'] = $post;
				$GLOBALS['cr_read_blocks'] = array( array( 'blockName' => 'post-kinds-indieweb/read-card', 'attrs' => array( 'bookTitle' => 'Book ' . $i ), 'rendered' => cr_shelf_card_fixture( 'Book ' . $i, '' ) ) );
				$GLOBALS['cr_read_pictures'][ $i ] = array( 'source' => 'cover', 'attachment_id' => 0, 'url' => 'https://media.test/' . $i . '.jpg' );
				$out = Shelf\shelf_card( cr_shelf_card_fixture( 'Book ' . $i, '' ), cr_shelf_style_block(), new WP_Block( array( 'postId' => $i ) ) );
				$loads[] = str_contains( $out, 'loading="eager"' );
			}
			cr_read_same( array( true, true, true, true, false ), $loads, 'first row eager' );

			$post = cr_shelf_shell_reset();
			cr_shelf_group_state( false, true, new Shelf_Group( 'reading', 'reading' ) );
			$GLOBALS['cr_read_blocks'] = array( array( 'blockName' => 'post-kinds-indieweb/read-card', 'attrs' => array( 'bookTitle' => 'Preview' ), 'rendered' => cr_shelf_card_fixture( 'Preview', '' ) ) );
			$out = Shelf\shelf_card( cr_shelf_card_fixture( 'Preview', '' ), cr_shelf_style_block(), new WP_Block( array( 'postId' => 42 ) ) );
			cr_read_contains( 'cr-shelf-book--faceout', $out, 'preview face-out' );
			cr_read_contains( '<h3 ', $out, 'preview h3' );

			$post = cr_shelf_shell_reset();
			cr_shelf_group_state( false, false, null );
			$GLOBALS['cr_read_blocks'] = array( array( 'blockName' => 'post-kinds-indieweb/read-card', 'attrs' => array( 'bookTitle' => 'Alphabetic' ), 'rendered' => cr_shelf_card_fixture( 'Alphabetic', '' ) ) );
			$out = Shelf\shelf_card( cr_shelf_card_fixture( 'Alphabetic', '' ), cr_shelf_style_block(), new WP_Block( array( 'postId' => 42 ) ) );
			cr_read_contains( 'cr-shelf-book--slab', $out, 'A-Z slab' );
			cr_read_contains( '<h2 ', $out, 'A-Z h2' );
			cr_read_not_contains( 'pkiw-group-reading', $out, 'A-Z no shelf id' );
		}
	);

	cr_read_finish();
	exit( 0 );
}

/** Fixture books in default engine order. */
function cr_shelf_fixture_books(): array {
	$long = 'A Cartography of Every Quiet Room Where We Learned to Read, Remember, Return, and Begin Again Beneath the Same Patient Sky';
	return array(
		array( 1, 'reading', 'The Midnight Library', 'Matt Haig', true, '2026-10-09' ),
		array( 2, 'reading', "A Room of One's Own", 'Virginia Woolf', true, '2026-10-08' ),
		array( 3, 'reading', $long, 'Avery Longname', true, '2026-10-07' ),
		array( 4, 'reading', 'The Book Without a Jacket', 'Nia Reed', false, '2026-10-06' ),
		array( 5, 'to-read', 'Future Library', 'Sofia Chen', false, '2026-10-05' ),
		array( 6, 'to-read', 'The Quiet Orchard', 'Ada North', true, '2026-10-04' ),
		array( 7, 'to-read', 'The Quiet Orchard', 'Ben South', true, '2026-10-03' ),
		array( 8, 'finished', 'Salt and Paper', 'Morgan Lee', false, '2026-10-02' ),
		array( 9, 'finished', 'Salt and Paper', 'Morgan Lee', false, '2026-10-01' ),
		array( 10, 'finished', 'Beloved', 'Toni Morrison', false, '2026-09-30' ),
		array( 11, 'finished', 'The Left Hand of Darkness', 'Ursula K. Le Guin', false, '2026-09-29' ),
		array( 12, 'finished', 'Giovanni’s Room', 'James Baldwin', false, '2026-09-28' ),
		array( 13, 'abandoned', 'The Unfinished Map', 'Rowan West', false, '2026-09-27' ),
		array( 14, '', 'A Shelf of One', '', false, '2026-09-26' ),
		array( 15, '', 'Private reading notes', '', false, '2026-09-25', true ),
	);
}

/** Render one fixture item through the real adapter. */
function cr_shelf_fixture_item( array $book, bool $sectioned ): string {
	list( $id, $group, $title, $author, $picture, $date ) = $book;
	$post = new WP_Post( $id, $title, '<!-- fixture -->' );
	$GLOBALS['cr_read_post'] = $post;
	$GLOBALS['cr_read_dates'][ $id ] = array( 'iso' => $date . 'T12:00:00+00:00', 'text' => date( 'F j, Y', strtotime( $date ) ) );
	$GLOBALS['cr_read_blocks'] = array( array( 'blockName' => 'post-kinds-indieweb/read-card', 'attrs' => array( 'bookTitle' => $title, 'authorName' => $author, 'readStatus' => '' === $group ? 'finished' : $group ), 'rendered' => cr_shelf_card_fixture( $title, $author ) ) );
	if ( $picture ) {
		$colors = array( '8ecae6', 'bcb5e3', 'fee2c3', '126782', '023047' );
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="360"><rect width="240" height="360" fill="#' . $colors[ $id % count( $colors ) ] . '"/></svg>';
		$GLOBALS['cr_read_pictures'][ $id ] = array( 'source' => 'cover', 'attachment_id' => 0, 'url' => 'data:image/svg+xml,' . rawurlencode( $svg ), 'alt' => '' );
	}
	if ( ! empty( $book[6] ) ) {
		$GLOBALS['cr_read_protected'][ $id ] = true;
		$input = cr_shelf_protected_fixture( $title );
	} elseif ( 14 === $id ) {
		$GLOBALS['cr_read_blocks'] = array();
		$input = cr_shelf_generic_fixture( $title );
	} else {
		$input = cr_shelf_card_fixture( $title, $author );
	}
	cr_shelf_group_state( $sectioned, false, $sectioned ? new Shelf_Group( $group, $group ) : null );
	return '<li class="wp-block-post post-' . $id . ' kind-read">' . Shelf\shelf_card( $input, cr_shelf_style_block(), new WP_Block( array( 'postId' => $id ) ) ) . '</li>';
}

/** Print the deterministic static browser fixture. */
function cr_shelf_print_html( array $args ): void {
	$base = '../../';
	$view = 'default';
	foreach ( $args as $arg ) {
		if ( str_starts_with( $arg, '--base=' ) ) {
			$base = substr( $arg, 7 );
		} elseif ( str_starts_with( $arg, '--view=' ) ) {
			$view = substr( $arg, 7 );
		}
	}
	$base = rtrim( $base, '/' ) . '/';
	Shelf\reset_page_state();
	$GLOBALS['cr_read_render'] = 'cr_shelf_render_stub';
	$GLOBALS['cr_read_has_term'] = true;
	$books = cr_shelf_fixture_books();
	if ( 'az' === $view ) {
		usort(
			$books,
			static function ( array $a, array $b ): int {
				$a_author = trim( (string) $a[3] );
				$b_author = trim( (string) $b[3] );
				if ( '' === $a_author && '' !== $b_author ) {
					return 1;
				}
				if ( '' !== $a_author && '' === $b_author ) {
					return -1;
				}
				return strcasecmp( $a_author . ' ' . (string) $a[2], $b_author . ' ' . (string) $b[2] );
			}
		);
	}
	$styles = array( 'tokens.css', 'components.css', 'cr-archives.css', 'cr-nav.css', 'cr-post-kinds.css', 'cr-shelf-boards.css', 'cr-read-shelf.css' );
	$out = '<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
	foreach ( $styles as $style ) {
		$out .= '<link rel="stylesheet" href="' . esc_attr( $base . 'assets/css/' . $style ) . '">';
	}
	$out .= '<title>Read shelf fixture</title></head><body class="archive tax-kind term-read"><main class="wp-block-group alignfull cr-archive cr-archive--stream cr-archive--read">'
		. '<div class="wp-block-group alignwide cr-archive__header"><h1>Read</h1><p>Books I am reading, plan to read, and have finished.</p></div>'
		. '<div class="wp-block-group alignwide cr-read-order"><nav class="wp-block-post-kinds-indieweb-read-order" aria-label="Read order"><ul class="pk-read-order__list"><li class="pk-read-order__item"><a class="pk-read-order__link" href="#" aria-current="page">All</a></li><li class="pk-read-order__item"><a class="pk-read-order__link" href="#">A–Z by author</a></li></ul></nav></div>'
		. '<div class="wp-block-group alignwide cr-archive-stream cr-read-shelf cr-shelf-boards"><div class="wp-block-query cr-archive-stream__query">';
	if ( 'az' === $view ) {
		$out .= '<ul class="wp-block-post-template cr-archive-stream__list cr-read-shelf__list">';
		foreach ( $books as $book ) {
			$out .= cr_shelf_fixture_item( $book, false );
		}
		$out .= '</ul>';
	} else {
		$out .= '<div class="wp-block-post-template cr-archive-stream__list cr-read-shelf__list pkiw-grouped">';
		$labels = array( 'reading' => 'Currently Reading', 'to-read' => 'To Read', 'finished' => 'Finished', 'abandoned' => 'Abandoned', '' => 'Other' );
		foreach ( $labels as $key => $label ) {
			$group_books = array_values( array_filter( $books, static fn( array $book ): bool => $book[1] === $key ) );
			$out .= '<section class="pkiw-group" data-pkiw-sections="5" data-pkiw-group="' . esc_attr( $key ) . '"><h2 id="' . esc_attr( Shelf\group_heading_id( $key ) ) . '" class="pkiw-group__heading">' . esc_html( $label ) . '</h2><ul class="pkiw-group__items">';
			foreach ( $group_books as $book ) {
				$out .= cr_shelf_fixture_item( $book, true );
			}
			$out .= '</ul></section>';
		}
		$out .= '</div>';
	}
	$out .= '<nav class="wp-block-query-pagination" aria-label="Posts"><a href="#">Previous</a><span>1</span><a href="#">Next</a></nav></div></div></main></body></html>';
	echo $out;
}

cr_shelf_print_html( $argv );
