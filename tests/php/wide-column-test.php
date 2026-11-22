<?php
/**
 * Standalone checks for the wide column filter (inc/single-wide-column.php).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/read-bootstrap.php';
require dirname( __DIR__, 2 ) . '/inc/single-wide-column.php';

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

/** A fresh post per group: the filter remembers its answer per post ID. */
function cr_wide_post( int $id ): WP_Post {
	cr_read_reset();
	$post                    = new WP_Post( $id, 'Fixture Book', '' );
	$GLOBALS['cr_read_post'] = $post;
	return $post;
}

/** A reading record: a finished read with an ISBN. */
function cr_wide_with_record(): void {
	$GLOBALS['cr_read_blocks'] = array(
		array(
			'blockName' => 'post-kinds-indieweb/read-card',
			'attrs'     => array( 'bookTitle' => 'Fixture Book', 'readStatus' => 'finished', 'finishedAt' => '2026-08-02', 'isbn' => '9781234567897' ),
		),
	);
}

cr_read_group(
	'a read with a record widens its content and header',
	static function (): void {
		cr_wide_post( 11 );
		cr_wide_with_record();
		cr_read_assert( applies(), 'applies' );
		$content = widen( cr_wide_content() );
		cr_read_same( 'wide', $content['attrs']['align'], 'content is align wide' );
		cr_read_same( 'constrained', $content['attrs']['layout']['type'], 'content layout stays constrained' );
		cr_read_same( '100%', $content['attrs']['layout']['contentSize'], 'content inner layout is 100%' );
		cr_read_same( 'single-post__content wp-block-post-content cr-wide-column', $content['attrs']['className'], 'content class' );
		$header = widen( cr_wide_header() );
		cr_read_same( 'wide', $header['attrs']['align'], 'header keeps align wide' );
		cr_read_same( 'var(--wp--style--global--content-size)', $header['attrs']['layout']['contentSize'], 'header lines keep the reading width' );
		cr_read_same( 'left', $header['attrs']['layout']['justifyContent'], 'header lines start at the column edge' );
		cr_read_same( 'single-post__header', $header['attrs']['className'], 'header class attribute is untouched' );
		cr_read_contains( 'cr-wide-column', mark_header( '<div class="wp-block-group single-post__header">x</div>', cr_wide_header() ), 'header markup gets the hook class' );
	}
);

cr_read_group(
	'a read without a record keeps the reading column',
	static function (): void {
		cr_wide_post( 12 );
		$GLOBALS['cr_read_blocks'] = array(
			array(
				'blockName' => 'post-kinds-indieweb/read-card',
				'attrs'     => array( 'bookTitle' => 'Fixture Book' ),
			),
		);
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
		cr_wide_with_record();
		$GLOBALS['cr_read_protected'][ $post->ID ] = true;
		cr_read_assert( ! applies(), 'locked does not apply' );
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'locked content block is unchanged' );
	}
);

cr_read_group(
	'other kinds and other blocks are untouched',
	static function (): void {
		cr_wide_post( 14 );
		cr_wide_with_record();
		$GLOBALS['cr_read_kind'] = 'like';
		cr_read_assert( ! applies(), 'a like does not apply' );
		cr_read_same( cr_wide_content(), widen( cr_wide_content() ), 'like content block is unchanged' );
		$GLOBALS['cr_read_kind'] = 'read';
		$other                   = array( 'blockName' => 'core/post-content', 'attrs' => array( 'className' => 'something-else' ) );
		cr_read_same( $other, widen( $other ), 'a post-content block without the template class is unchanged' );
		$group = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'single-post__meta' ) );
		cr_read_same( $group, widen( $group ), 'another group is unchanged' );
	}
);

cr_read_finish();
