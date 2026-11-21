<?php
/**
 * Verify the Event flyer registration and the single and Stream adapters.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

use Courtneyr\Child\ArtifactStyle;
use Courtneyr\Child\Event;

require __DIR__ . '/event-231-test.php';

cr_event_check(
	'Register Event flyer with only name and label',
	static function (): void {
		foreach ( $GLOBALS['cr_event_hooks'] as [ $hook, $callback, $priority ] ) {
			if ( 'init' === $hook && 20 === $priority && 'Courtneyr\\Child\\ArtifactStyle\\register_styles' === $callback ) {
				$callback();
			}
		}
		$expected = array( 'post-kinds-indieweb/event-card', array( 'name' => 'cr-flyer', 'label' => 'Event flyer' ) );
		cr_event_assert( array( $expected ) === $GLOBALS['cr_event_styles'], 'registration uses the frozen block, slug, label and init priority' );
	}
);

cr_event_check(
	'Style the existing single and Stream roots when Event is the second kind',
	static function (): void {
		$post                            = new WP_Post( 232, 'Public event' );
		$GLOBALS['cr_event_posts'][232]  = $post;
		$GLOBALS['cr_event_terms'][232]  = array( 'checkin', 'event' );
		$GLOBALS['cr_event_queried']     = 232;
		$GLOBALS['cr_event_single']      = true;
		$GLOBALS['cr_event_stream']      = true;
		$block                           = new WP_Block( array( 'postId' => 232 ) );
		$html                            = cr_event_card_markup( array( 'location' => '' ) );

		cr_event_assert( ArtifactStyle\applies( $post, 'cr-flyer' ), 'Event matches as a secondary kind' );
		$single = Event\single_flyer( $html, array(), $block );
		$stream = Event\stream_tab( $html, array(), $block );
		cr_event_contains( '<article class="cr-event-flyer h-event is-style-cr-flyer">', $single, 'single class stays on the flyer root' );
		cr_event_contains( '<article class="pk-card pk-card--stream k-event cr-event-tab h-entry is-style-cr-flyer">', $stream, 'Stream class stays on the tab root' );
		cr_event_lacks( 'Authored Venue', $single . $stream, 'both surfaces retain the plugin’s hidden location' );

		$GLOBALS['cr_event_single'] = false;
		$GLOBALS['cr_event_stream'] = false;
		cr_event_assert( $html === Event\single_flyer( $html, array(), $block ), 'single adapter respects its surface' );
		cr_event_assert( $html === Event\stream_tab( $html, array(), $block ), 'Stream adapter respects its surface' );

		$GLOBALS['cr_event_single'] = true;
		$GLOBALS['cr_event_stream'] = true;
		$post->post_password        = 'fixture';
		$locked                     = '<article class="pk-card pk-card--protected">This post is password-protected.</article>';
		cr_event_assert( $locked === Event\single_flyer( $locked, array(), $block ), 'single adapter preserves the locked card' );
		cr_event_assert( $locked === Event\stream_tab( $locked, array(), $block ), 'Stream adapter preserves the locked card' );
	}
);

if ( $GLOBALS['cr_event_failures'] ) {
	fwrite( STDERR, implode( "\n", $GLOBALS['cr_event_failures'] ) . "\n" );
	exit( 1 );
}

echo "PASS N7 Event flyer style contracts\n";
