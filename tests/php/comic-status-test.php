<?php
/**
 * Standalone checks for comic status copy.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/read-bootstrap.php';
require dirname( __DIR__, 2 ) . '/inc/comic.php';

use function Courtneyr\Child\Comic\comic_status_copy;
use function Courtneyr\Child\SingleRead\status_copy;

cr_read_group(
	'comic status copy keeps the approved comic words',
	static function (): void {
		$expected = array(
			'reading'   => array( 'Currently reading', 'Reading' ),
			'to-read'   => array( 'To read', 'To read' ),
			'finished'  => array( 'Finished', 'Finished' ),
			'abandoned' => array( 'Set aside', 'Set aside' ),
			''          => array( 'To read', 'To read' ),
		);

		foreach ( $expected as $status => $copy ) {
			cr_read_same( $copy, comic_status_copy( $status ), '' === $status ? 'unknown' : $status );
		}
	}
);

cr_read_group(
	'read single status copy still uses plugin labels',
	static function (): void {
		cr_read_same( array( 'Currently Reading', 'Currently Reading' ), status_copy( 'reading' ), 'read single reading' );
	}
);

cr_read_finish();
