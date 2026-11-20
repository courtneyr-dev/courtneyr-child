<?php
/**
 * Standalone checks for the Reply block style and mixed-kind membership.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/reply-bootstrap.php';

use Courtneyr\Child\Letters;

cr_reply_group(
	'Letter registers on the Reply card and renders for a second kind term',
	static function (): void {
		$post = cr_reply_reset();
		foreach ( $GLOBALS['cr_reply_actions'] as list( $hook, $callback, $priority ) ) {
			if ( 'init' === $hook && 20 === $priority ) {
				$callback();
			}
		}
		cr_reply_same(
			array( array( 'post-kinds-indieweb/reply-card', array( 'name' => 'cr-letter-sheet', 'label' => 'Letter' ) ) ),
			$GLOBALS['cr_reply_block_styles'],
			'only the artifact name and label register on the Reply card'
		);
		$GLOBALS['cr_reply_kinds'] = array( 'checkin', 'reply' );
		$block                    = array( 'attrs' => array( 'className' => 'is-style-cr-letter' ) );
		$html                     = Letters\letter_card( '', $block, new WP_Block( array( 'postId' => $post->ID ) ) );
		cr_reply_contains( '<article class="cr-letter k-reply is-style-cr-letter-sheet">', $html, 'the second kind still gets its archive letter' );

		$envelope = '<li class="wp-block-post post-43"><article class="cr-envelope k-reply is-style-cr-letter-sheet"></article></li>';
		cr_reply_same( $envelope, Letters\decorate_post_template( $envelope, array() ), 'the archive decorator leaves a styled Stream envelope alone' );
		$archive = '<li class="wp-block-post post-42">' . $html . '</li>';
		$mixed   = Letters\decorate_post_template( $archive . $envelope, array() );
		cr_reply_contains( 'post-42 cr-paper-sheet', $mixed, 'the archive letter still gets paper' );
		cr_reply_contains( $envelope, $mixed, 'the envelope remains byte-identical next to an archive letter' );
	}
);

cr_reply_finish();
