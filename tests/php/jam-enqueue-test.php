<?php
/**
 * Standalone checks for jam stylesheet loading and body/editor classes.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/jam-bootstrap.php';

use Courtneyr\Child\Jam as Jam;

/** Run enqueue_styles() against a request configuration. */
function cr_jam_enqueue_for( array $request ): array {
	cr_jam_reset();
	$GLOBALS['cr_jam_request']  = array_replace_recursive( $GLOBALS['cr_jam_request'], $request );
	$GLOBALS['cr_jam_enqueued'] = array();
	Jam\enqueue_styles();
	return $GLOBALS['cr_jam_enqueued'];
}

cr_jam_group(
	'jam paint loads on the archive, single, Stream and editor',
	static function (): void {
		$requests = array(
			'archive' => array( 'kind_archive' => 'jam' ),
			'single'  => array( 'single' => true ),
			'Stream'  => array( 'stream' => true ),
			'editor'  => array( 'admin' => true ),
		);
		foreach ( $requests as $name => $request ) {
			$enqueued = cr_jam_enqueue_for( $request );
			cr_jam_same( 1, count( $enqueued ), $name . ' enqueue count' );
			cr_jam_same( 'courtneyr-jam', $enqueued[0][0], $name . ' handle' );
			cr_jam_assert( str_ends_with( (string) $enqueued[0][1], '/assets/css/cr-jam.css' ), $name . ' URI' );
			cr_jam_same( array( 'courtneyr-paper' ), $enqueued[0][2], $name . ' dependency' );
		}
	}
);

cr_jam_group(
	'jam paint stays off unrelated front-end requests',
	static function (): void {
		$requests = array(
			'non-jam single'  => array( 'single' => true, 'post_kinds' => array( 42 => array( 'note' ) ) ),
			'other archive'   => array( 'kind_archive' => 'listen' ),
			'plain page'      => array(),
		);
		foreach ( $requests as $name => $request ) {
			cr_jam_same( array(), cr_jam_enqueue_for( $request ), $name );
		}
	}
);

cr_jam_group(
	'body and editor parity classes are scoped and idempotent',
	static function (): void {
		cr_jam_reset();
		$GLOBALS['cr_jam_request']['kind_archive'] = 'jam';
		cr_jam_same( array( 'archive', 'cr-jam-archive' ), Jam\body_class( array( 'archive' ) ), 'archive class' );
		cr_jam_same( array( 'archive', 'cr-jam-archive' ), Jam\body_class( array( 'archive', 'cr-jam-archive' ) ), 'archive class once' );
		$GLOBALS['cr_jam_request']['kind_archive'] = 'listen';
		cr_jam_same( array( 'archive' ), Jam\body_class( array( 'archive' ) ), 'other archive unchanged' );

		$kinds = Jam\register_editor_parity(
			array(
				'jam'  => array( 'classes' => array( 'single-post', 'kind-jam' ) ),
				'note' => array( 'classes' => array( 'single-post', 'kind-note' ) ),
			)
		);
		cr_jam_same( array( 'single-post', 'kind-jam', 'cr-jam-single' ), $kinds['jam']['classes'], 'jam editor class' );
		cr_jam_same( array( 'single-post', 'kind-note' ), $kinds['note']['classes'], 'other editor entry preserved' );
		cr_jam_same( $kinds, Jam\register_editor_parity( $kinds ), 'editor registration idempotent' );
	}
);

cr_jam_group(
	'hooks are registered without a block style',
	static function (): void {
		cr_jam_assert( cr_jam_hooked( 'enqueue_block_assets', 'Courtneyr\\Child\\Jam\\enqueue_styles' ), 'enqueue hook' );
		cr_jam_assert( cr_jam_hooked( 'body_class', 'Courtneyr\\Child\\Jam\\body_class' ), 'body hook' );
		cr_jam_assert( cr_jam_hooked( 'courtneyr_child_editor_parity_kinds', 'Courtneyr\\Child\\Jam\\register_editor_parity' ), 'editor hook' );
		cr_jam_same( array(), $GLOBALS['cr_jam_block_styles'], 'no register_block_style call' );
		$source = (string) file_get_contents( CR_JAM_THEME_DIR . '/inc/jam.php' );
		cr_jam_not_contains( 'register_block_style', $source, 'source contains no block style registration' );
	}
);

cr_jam_finish();
