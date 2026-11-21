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

class CR_Event_Style_Post extends WP_Post {
	public string $post_content = '';
}

cr_event_check(
	'Print public locations in the single flyer and omit locations from the Stream tab',
	static function (): void {
		$name     = 'Sentinel Event Name';
		$location = 'Sentinel Event Venue';
		$parsed   = array(
			'blockName' => 'post-kinds-indieweb/event-card',
			'attrs'     => array( 'eventName' => $name, 'eventLocation' => $location ),
		);
		$render   = static function ( int $id, array $terms, array $meta ) use ( $parsed ): array {
			$GLOBALS['cr_event_posts'][ $id ] = new WP_Post( $id, 'Public event' );
			$GLOBALS['cr_event_terms'][ $id ] = $terms;
			$GLOBALS['cr_event_queried']      = $id;
			$GLOBALS['cr_event_single']       = true;
			$GLOBALS['cr_event_stream']       = true;
			$block                            = new WP_Block( array( 'postId' => $id ) );
			// Model the plugin's rendered location for these Event and RSVP fixtures.
			$is_rsvp = in_array( 'rsvp', $terms, true ) || isset( $meta['_pkiw_rsvp'] );
			$visible = ! $is_rsvp || 'public' === ( $meta['_pkiw_rsvp_location_privacy'] ?? '' );
			$html    = cr_event_card_markup(
				array(
					'name'     => $parsed['attrs']['eventName'],
					'location' => $visible ? $parsed['attrs']['eventLocation'] : '',
				)
			);

			return array(
				'single flyer' => Event\single_flyer( $html, $parsed, $block ),
				'Stream tab'   => Event\stream_tab( $html, $parsed, $block ),
			);
		};

		$before = count( $GLOBALS['cr_event_failures'] );
		$event_only = $render( 233, array( 'event' ), array() );
		foreach ( $event_only as $surface => $html ) {
			cr_event_contains( $name, $html, 'Event-only ' . $surface . ' name; output ' . $html );
		}
		cr_event_contains( $location, $event_only['single flyer'], 'Event-only single flyer location; output ' . $event_only['single flyer'] );
		cr_event_contains( 'cr-event-tab h-entry is-style-cr-flyer', $event_only['Stream tab'], 'Event-only Stream tab root style; output ' . $event_only['Stream tab'] );
		cr_event_lacks( $location, $event_only['Stream tab'], 'Event-only Stream tab never prints location; output ' . $event_only['Stream tab'] );
		if ( $before !== count( $GLOBALS['cr_event_failures'] ) ) {
			return;
		}

		$private_row = $render( 234, array( 'event' ), array( '_pkiw_rsvp' => 'yes', '_pkiw_rsvp_location_privacy' => 'private' ) );
		$term_only   = $render( 235, array( 'event', 'rsvp' ), array() );
		foreach ( array( 'private RSVP row' => $private_row, 'RSVP term without a privacy row' => $term_only ) as $case => $surfaces ) {
			foreach ( $surfaces as $surface => $html ) {
				cr_event_contains( $name, $html, $case . ' ' . $surface . ' retains the event name; output ' . $html );
				$reason = 'Stream tab' === $surface ? ' never prints location' : ' withholds the location';
				cr_event_lacks( $location, $html, $case . ' ' . $surface . $reason . '; output ' . $html );
			}
		}
	}
);

cr_event_check(
	'An unset or foreign saved style renders the same Event flyer and Stream tab',
	static function (): void {
		$render = static function ( int $id, array $attrs ): array {
			$post               = new CR_Event_Style_Post( $id, 'Public event' );
			$post->post_content = '<!-- wp:post-kinds-indieweb/event-card' . ( $attrs ? ' ' . json_encode( $attrs ) : '' ) . ' /-->';
			$GLOBALS['cr_event_posts'][ $id ] = $post;
			$GLOBALS['cr_event_terms'][ $id ] = array( 'event' );
			$GLOBALS['cr_event_queried']      = $id;
			$GLOBALS['cr_event_single']       = true;
			$GLOBALS['cr_event_stream']       = true;
			$parsed                           = ( new WP_Block_Parser() )->parse( $post->post_content )[0];
			$block                            = new WP_Block( array( 'postId' => $id ) );
			$html                             = cr_event_card_markup();
			$style                            = $attrs['className'] ?? 'unset style';

			cr_event_assert( ArtifactStyle\applies( $post, 'cr-flyer' ), 'Event applies with ' . $style );
			cr_event_assert( 'cr-flyer' === ArtifactStyle\resolve( $post ), 'Event resolves to cr-flyer with ' . $style );
			$single = Event\single_flyer( $html, $parsed, $block );
			$stream = Event\stream_tab( $html, $parsed, $block );
			cr_event_contains( 'cr-event-flyer h-event is-style-cr-flyer', $single, 'single flyer renders with ' . $style );
			cr_event_contains( 'cr-event-tab h-entry is-style-cr-flyer', $stream, 'Stream tab renders with ' . $style );

			return array(
				'single flyer' => str_replace( get_permalink( $post ), 'https://example.test/events/fixture/', $single ),
				'Stream tab'   => str_replace( get_permalink( $post ), 'https://example.test/events/fixture/', $stream ),
			);
		};

		$unset   = $render( 236, array() );
		$foreign = $render( 237, array( 'className' => 'is-style-foreign' ) );
		foreach ( $unset as $surface => $html ) {
			cr_event_assert( $html === $foreign[ $surface ], $surface . ' matches unset style; output ' . $foreign[ $surface ] );
		}
	}
);

cr_event_check(
	'A card-less Event single keeps its paragraph and resolves to the flyer style',
	static function (): void {
		$paragraph                       = '<p>A body-only Event fixture.</p>';
		$post                            = new CR_Event_Style_Post( 238, 'Public event' );
		$post->post_content               = '<!-- wp:paragraph -->' . $paragraph . '<!-- /wp:paragraph -->';
		$GLOBALS['cr_event_posts'][238]   = $post;
		$GLOBALS['cr_event_terms'][238]   = array( 'event' );
		$GLOBALS['cr_event_queried']      = 238;
		$GLOBALS['cr_event_single']       = true;
		$GLOBALS['cr_event_stream']       = false;

		cr_event_assert( ArtifactStyle\applies( $post, 'cr-flyer' ), 'Event applies without a card' );
		cr_event_assert( 'cr-flyer' === ArtifactStyle\resolve( $post ), 'Event resolves to cr-flyer without a card' );
		$parsed             = ( new WP_Block_Parser() )->parse( $post->post_content )[0];
		$parsed['rendered'] = $parsed['innerHTML'];
		$html               = render_block( $parsed );
		cr_event_assert( $paragraph === $html, 'card-less Event keeps its rendered paragraph; output ' . $html );
		cr_event_lacks( 'cr-event-flyer', $html, 'card-less Event has no flyer; output ' . $html );
		cr_event_lacks( 'is-style-cr-flyer', $html, 'card-less Event has no flyer style class; output ' . $html );
	}
);

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
