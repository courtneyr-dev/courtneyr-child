<?php
/**
 * Standalone checks for the wide column filter (inc/single-wide-column.php).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/read-bootstrap.php';
require __DIR__ . '/wide-column-bootstrap.php';

use function Courtneyr\Child\SingleWideColumn\applies;
use function Courtneyr\Child\SingleWideColumn\mark_header;
use function Courtneyr\Child\SingleWideColumn\widen;

/** @return array<string, mixed> */
function cr_wide_content(): array {
	return array(
		'blockName' => 'core/post-content',
		'attrs'     => array(
			'align'     => '',
			'className' => 'single-post__content wp-block-post-content',
			'layout'    => array( 'type' => 'constrained' ),
		),
	);
}

/** @return array<string, mixed> */
function cr_wide_header(): array {
	return array(
		'blockName' => 'core/group',
		'attrs'     => array(
			'align'     => 'wide',
			'className' => 'single-post__header',
			'layout'    => array( 'type' => 'constrained' ),
		),
	);
}

/** A fresh post per group: the filter remembers its answer per post and page. */
function cr_wide_post( int $id, string $content = '<!-- wp:post-kinds-indieweb/read-card /-->' ): WP_Post {
	cr_read_reset();
	$post                       = new WP_Post( $id, 'Fixture Book', $content );
	$GLOBALS['cr_read_post']    = $post;
	$GLOBALS['cr_wide_terms']   = array( 'read' );
	$GLOBALS['cr_wide_main']    = true;
	$GLOBALS['cr_wide_in_loop'] = true;
	$GLOBALS['cr_wide_loop_id'] = null;
	$GLOBALS['cr_wide_page']    = 1;
	$GLOBALS['cr_read_parse']   = null;
	return $post;
}

/** A read-card block that prints a record row (a finished date and an ISBN). */
function cr_wide_card( bool $with_record = true ): array {
	return array(
		'blockName' => 'post-kinds-indieweb/read-card',
		'attrs'     => $with_record ? array( 'bookTitle' => 'Fixture Book', 'readStatus' => 'finished', 'finishedAt' => '2026-08-02', 'isbn' => '9781234567897' ) : array( 'bookTitle' => 'Fixture Book' ),
	);
}

/** The blocks of a content string: a card where the content names one. */
function cr_wide_parse( bool $with_record = true ): callable {
	return static fn( string $content ): array => false !== strpos( $content, 'read-card' ) ? array( cr_wide_card( $with_record ) ) : array();
}

cr_read_group(
	'a read with a record widens its content and header',
	static function (): void {
		cr_wide_post( 11 );
		$GLOBALS['cr_read_parse'] = cr_wide_parse();
		cr_read_assert( applies(), 'applies' );
		$content = widen( cr_wide_content() );
		cr_read_same( 'wide', $content['attrs']['align'], 'content is align wide' );
		cr_read_same( 'constrained', $content['attrs']['layout']['type'], 'content layout stays constrained' );
		cr_read_same( '100%', $content['attrs']['layout']['contentSize'], 'content inner layout is 100%' );
		cr_read_same( 'single-post__content wp-block-post-content cr-wide-column', $content['attrs']['className'], 'content class' );
		$header = widen( cr_wide_header() );
		cr_read_same( 'wide', $header['attrs']['align'], 'header keeps align wide' );
		cr_read_same( 'var(--wp--style--global--content-size)', $header['attrs']['layout']['contentSize'], 'header lines keep the reading width' );
		cr_read_assert( ! isset( $header['attrs']['layout']['justifyContent'] ), 'the header sets no physical justification' );
		cr_read_same( 'single-post__header', $header['attrs']['className'], 'header class attribute is untouched' );
		cr_read_contains( 'cr-wide-column', mark_header( '<div class="wp-block-group single-post__header">x</div>', cr_wide_header() ), 'header markup gets the hook class' );
	}
);

cr_read_group(
	'a read without a record keeps the reading column',
	static function (): void {
		cr_wide_post( 12 );
		$GLOBALS['cr_read_parse'] = cr_wide_parse( false );
		cr_read_assert( ! applies(), 'rowless does not apply' );
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'rowless content block is unchanged' );
		cr_read_same( cr_wide_header(), widen( cr_wide_header() ), 'rowless header block is unchanged' );
		$html = '<div class="wp-block-group single-post__header">x</div>';
		cr_read_same( $html, mark_header( $html, cr_wide_header() ), 'rowless header markup is unchanged' );
	}
);

cr_read_group(
	'a locked read keeps the reading column',
	static function (): void {
		$post = cr_wide_post( 13 );
		$GLOBALS['cr_read_parse']                    = cr_wide_parse();
		$GLOBALS['cr_read_protected'][ $post->ID ] = true;
		cr_read_assert( ! applies(), 'locked does not apply' );
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'locked content block is unchanged' );
	}
);

cr_read_group(
	'the first kind term decides, as the body class does',
	static function (): void {
		cr_wide_post( 14 );
		$GLOBALS['cr_read_parse'] = cr_wide_parse();
		$GLOBALS['cr_wide_terms'] = array( 'like' );
		cr_read_assert( ! applies(), 'a like does not apply' );
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'like content block is unchanged' );
		cr_wide_post( 15 );
		$GLOBALS['cr_read_parse'] = cr_wide_parse();
		$GLOBALS['cr_wide_terms'] = array( 'like', 'read' );
		cr_read_assert( ! applies(), 'a post whose first kind is like keeps the reading column even with a read term second' );
		cr_wide_post( 16 );
		$GLOBALS['cr_read_parse'] = cr_wide_parse();
		$GLOBALS['cr_wide_terms'] = array( 'read', 'watch' );
		cr_read_assert( applies(), 'a post whose first kind is read widens with another term second' );
	}
);

cr_read_group(
	'only the main query post widens, on the page that carries the record',
	static function (): void {
		cr_wide_post( 17 );
		$GLOBALS['cr_read_parse'] = cr_wide_parse();
		$GLOBALS['cr_wide_loop_id'] = 999;
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'a block rendering another post in a secondary loop is unchanged' );
		cr_read_same( cr_wide_header(), widen( cr_wide_header() ), 'a header rendered for another post is unchanged' );
		cr_wide_post( 18 );
		$GLOBALS['cr_read_parse'] = cr_wide_parse();
		$GLOBALS['cr_wide_main']  = false;
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'a block outside the main query is unchanged' );
		cr_wide_post( 19 );
		$GLOBALS['cr_read_parse']   = cr_wide_parse();
		$GLOBALS['cr_wide_in_loop'] = false;
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'a block outside the loop is unchanged' );

		cr_wide_post( 20, "<!-- wp:post-kinds-indieweb/read-card /-->\n<!--nextpage-->\n<!-- wp:paragraph --><p>Page two prose.</p><!-- /wp:paragraph -->" );
		$GLOBALS['cr_read_parse'] = cr_wide_parse();
		cr_read_assert( applies(), 'page 1 carries the record' );
		$GLOBALS['cr_wide_page'] = 2;
		cr_read_assert( ! applies(), 'page 2 does not' );
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'the content block on page 2 is unchanged' );
		$html = '<div class="wp-block-group single-post__header">x</div>';
		cr_read_same( $html, mark_header( $html, cr_wide_header() ), 'the header markup on page 2 is unchanged' );
	}
);

cr_read_group(
	'other blocks are untouched',
	static function (): void {
		cr_wide_post( 21 );
		$GLOBALS['cr_read_parse'] = cr_wide_parse();
		$other                    = array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'something-else' ) );
		cr_read_same( $other, widen( $other ), 'a post-content block without the template class is unchanged' );
		$group = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__meta' ) );
		cr_read_same( $group, widen( $group ), 'another group is unchanged' );
	}
);

cr_read_finish();
