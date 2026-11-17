<?php
/**
 * `php tests/php/wrapped-single-test.php`: Wrapped single contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace PKIW {
	function wrapped_facts( int $post_id ): array {
		return $GLOBALS['cr_wrapped_facts'][ $post_id ] ?? array();
	}
}

namespace Courtneyr\Child\Series {
	function is_series_toc(): bool {
		return false;
	}
}

namespace {
	require __DIR__ . '/wrapped-bootstrap.php';
	require CR_WRAPPED_THEME_DIR . '/inc/archives.php';
	require CR_WRAPPED_THEME_DIR . '/inc/wrapped.php';

	use Courtneyr\Child\Wrapped;

	$GLOBALS['cr_wrapped_posts'][267] = new WP_Post( 267 );
	$GLOBALS['cr_wrapped_facts'][267] = array(
		'kind'         => 'read',
		'title'        => 'Read 2026 Wrapped',
		'card_metrics' => array(),
	);

	cr_wrapped_check( array() === Wrapped\facts( 999 ), 'plugin-empty facts pass through as an empty array' );
	cr_wrapped_check( 'read' === Wrapped\facts( 267 )['kind'], 'plugin facts pass through unchanged' );

	$heading = '<h1 class="wp-block-post-title">Read 2026 Wrapped</h1>';
	$block   = array( 'attrs' => array( 'level' => 1, 'className' => 'cr-flank single-post__title' ) );
	$GLOBALS['cr_wrapped_request'] = array();
	cr_wrapped_check( $heading === Wrapped\cutout_wrapped_title( $heading, $block ), 'non-Wrapped request leaves post title unchanged' );
	$GLOBALS['cr_wrapped_request'] = array( 'single' => true, 'post_id' => 999 );
	cr_wrapped_check( $heading === Wrapped\cutout_wrapped_title( $heading, $block ), 'non-Wrapped single leaves post title unchanged' );
	$GLOBALS['cr_wrapped_request'] = array( 'single' => true, 'post_id' => 267 );
	cr_wrapped_check( $heading === Wrapped\cutout_wrapped_title( $heading, array( 'attrs' => array( 'level' => 3, 'className' => 'related-post__title' ) ) ), 'other post-title context stays unchanged on Wrapped single' );
	$cut = Wrapped\cutout_wrapped_title( $heading, $block );
	cr_wrapped_contains( '<h1', $cut, 'Wrapped title stays h1' );
	cr_wrapped_contains( '<span class="cr-cutout__text screen-reader-text">Read 2026 Wrapped</span>', $cut, 'screen-reader title copy stays intact' );
	cr_wrapped_contains( '<span class="cr-cutout__tiles" aria-hidden="true">', $cut, 'tile layer is aria-hidden' );
	cr_wrapped_check( 15 === substr_count( $cut, 'class="cr-cutout__tile ' ), 'one tile per non-space grapheme, including digits' );
	preg_match_all( '/\bis-r([0-3])\b/', $cut, $rotation_matches );
	$rotations = $rotation_matches[1];
	$adjacent  = true;
	for ( $index = 1; $index < count( $rotations ); ++$index ) {
		$adjacent = $adjacent && $rotations[ $index ] !== $rotations[ $index - 1 ];
	}
	cr_wrapped_check( $adjacent, 'adjacent tiles use different rotation classes' );
	cr_wrapped_check( 1 === substr_count( $cut, '<h1' ) && 1 === substr_count( $cut, '</h1>' ), 'cutout keeps one h1' );

	$GLOBALS['cr_wrapped_styles'] = array();
	Wrapped\enqueue_cutout_on_wrapped();
	cr_wrapped_check( isset( $GLOBALS['cr_wrapped_styles']['courtneyr-cutout'] ), 'Wrapped single enqueues cutout sheet' );
	cr_wrapped_check( ! isset( $GLOBALS['cr_wrapped_styles']['courtneyr-wrapped'] ), 'Wrapped single leaves Wrapped sheet loading to the rendered block' );
	$GLOBALS['cr_wrapped_request'] = array( 'single' => true, 'post_id' => 999 );
	$GLOBALS['cr_wrapped_styles']  = array();
	Wrapped\enqueue_cutout_on_wrapped();
	cr_wrapped_check( array() === $GLOBALS['cr_wrapped_styles'], 'non-Wrapped single enqueues neither sheet' );
	$GLOBALS['cr_wrapped_request'] = array( 'single' => true, 'post_id' => 267, 'admin' => true );
	Wrapped\enqueue_cutout_on_wrapped();
	cr_wrapped_check( array() === $GLOBALS['cr_wrapped_styles'], 'admin request enqueues neither single sheet' );

	$GLOBALS['cr_wrapped_blocks'] = array();
	Wrapped\register_block_styles();
	cr_wrapped_check( 2 === count( $GLOBALS['cr_wrapped_blocks'] ), 'both Wrapped blocks register the shared sheet' );
	cr_wrapped_check( 'post-kinds-indieweb/wrapped' === $GLOBALS['cr_wrapped_blocks'][0][0], 'Wrapped block style registration' );
	cr_wrapped_check( 'post-kinds-indieweb/wrapped-strip' === $GLOBALS['cr_wrapped_blocks'][1][0], 'Wrapped strip style registration' );
	cr_wrapped_check( 'courtneyr-wrapped' === $GLOBALS['cr_wrapped_blocks'][0][1]['handle'] && 'courtneyr-wrapped' === $GLOBALS['cr_wrapped_blocks'][1][1]['handle'], 'one consistent handle across block paths' );
	$GLOBALS['cr_wrapped_request'] = array();
	$GLOBALS['cr_wrapped_styles']['courtneyr-wrapped'] = array();
	Wrapped\prune_unrendered_style();
	cr_wrapped_check( ! isset( $GLOBALS['cr_wrapped_styles']['courtneyr-wrapped'] ), 'empty dynamic surface dequeues shared sheet' );
	Wrapped\record_rendered_surface( '<section class="pkiw-wrapped"></section>' );
	$GLOBALS['cr_wrapped_styles']['courtneyr-wrapped'] = array();
	Wrapped\prune_unrendered_style();
	cr_wrapped_check( isset( $GLOBALS['cr_wrapped_styles']['courtneyr-wrapped'] ), 'rendered Wrapped markup keeps shared sheet' );

	$hook_names = array_column( $GLOBALS['cr_wrapped_hooks'], 0 );
	cr_wrapped_check( in_array( 'render_block_core/post-title', $hook_names, true ), 'filter is scoped to core/post-title' );
	cr_wrapped_finish();
}
