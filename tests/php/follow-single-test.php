<?php
/**
 * Follow single card contracts (acceptance item 16).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/follow-bootstrap.php';
	require CR_FOLLOW_THEME_DIR . '/inc/stamps.php';
	require CR_FOLLOW_THEME_DIR . '/inc/follow.php';
	require CR_FOLLOW_THEME_DIR . '/inc/single-follow.php';

	use Courtneyr\Child\SingleFollow;

	/** Render one queried follow single. */
	function cr_follow_single( int $id, array $facts, string $content = '' ): string {
		$GLOBALS['cr_follow_facts'][ $id ] = $facts;
		$GLOBALS['cr_follow_singular']     = true;
		$GLOBALS['cr_follow_queried']      = $id;
		$GLOBALS['cr_follow_posts'][ $id ] = new WP_Post( $id );
		return SingleFollow\card( $content, array( 'attrs' => array() ), new WP_Block( array( 'postId' => $id ) ) );
	}

	cr_follow_check(
		'titled card uses the template H1 and prints the frozen DOM order once',
		static function (): void {
			$facts = cr_follow_facts( array( 'post_id' => 101 ) );
			$html  = cr_follow_single( 101, $facts, '<p>A useful note.</p>' );
			cr_follow_in_order(
				array(
					'<span class="cr-follow-card__initial" aria-hidden="true">A</span>',
					'<p class="cr-follow-card__name">Amara Okafor</p>',
					'<a class="cr-follow-card__site" href="https://amara.example/" rel="noopener noreferrer">amara.example</a>',
					'<span class="cr-follow-card__label">Followed</span> <time datetime="2026-11-15">November 15, 2026</time>',
					'<div class="cr-follow-card__note"><p class="cr-follow-card__label">Note</p><p>A useful note.</p>',
				),
				$html,
				'C13 card order'
			);
			cr_follow_same( 0, substr_count( $html, '<h1' ), 'template retains the H1' );
			cr_follow_same( 1, substr_count( $html, 'Amara Okafor' ), 'name prints once' );
			cr_follow_same( 1, substr_count( $html, '<a ' ), 'one anchor' );
			cr_follow_same( 1, substr_count( $html, 'href="https://amara.example/"' ), 'one anchor to followed URL' );
		}
	);

	cr_follow_check(
		'title-less card owns the H1; no photo uses only the initial tile',
		static function (): void {
			$facts = cr_follow_facts( array( 'post_id' => 102, 'title_empty' => true ) );
			$html  = cr_follow_single( 102, $facts );
			cr_follow_contains( '<h1 class="cr-follow-card__name">Amara Okafor</h1>', $html, 'card H1' );
			cr_follow_same( 1, substr_count( $html, 'Amara Okafor' ), 'name prints once' );
			cr_follow_contains( 'cr-follow-card__initial', $html, 'initial tile' );
			cr_follow_not_contains( '<figure', $html, 'no photo figure' );
			$post = $GLOBALS['cr_follow_posts'][102];
			cr_follow_assert( SingleFollow\holds_h1( false, $post ), 'holds H1 filter' );
		}
	);

	cr_follow_check(
		'photo prints once and enables the shared featured-image repeat gate',
		static function (): void {
			$facts = cr_follow_facts( array( 'post_id' => 103, 'photo_id' => 77, 'photo_alt' => 'Amara beside a garden.' ) );
			$html  = cr_follow_single( 103, $facts );
			cr_follow_same( 1, substr_count( $html, '<img ' ), 'one image' );
			cr_follow_contains( '77-large.jpg', $html, 'large attachment' );
			cr_follow_contains( 'alt="Amara beside a garden."', $html, 'stored alt' );
			cr_follow_same( array( 'photo', 'follow' ), SingleFollow\repeat_kinds( array( 'photo' ) ), 'follow repeat kind' );
			cr_follow_assert( SingleFollow\featured_repeats( false, $GLOBALS['cr_follow_posts'][103] ), 'photo repeats in the card' );
			$GLOBALS['cr_follow_facts'][103]['photo_id'] = 0;
			cr_follow_assert( ! SingleFollow\featured_repeats( false, $GLOBALS['cr_follow_posts'][103] ), 'no photo, no repeat' );
		}
	);

	cr_follow_check(
		'Note label requires visible text or media but hidden mf2 stays in the wrapper',
		static function (): void {
			$hidden = '<span class="u-follow-of h-card" hidden><data class="p-name" value="Amara Okafor"></data></span>';
			$html   = cr_follow_single( 104, cr_follow_facts( array( 'post_id' => 104 ) ), $hidden );
			cr_follow_not_contains( '>Note</p>', $html, 'no label for hidden mf2 only' );
			cr_follow_contains( $hidden, $html, 'hidden mf2 preserved' );
			cr_follow_in_order( array( '<div class="cr-follow-card__note">', $hidden, '</div></div>' ), $html, 'hidden h-card stays in note wrapper' );

			$media = cr_follow_single( 105, cr_follow_facts( array( 'post_id' => 105 ) ), '<figure><img src="note.jpg" alt=""></figure>' );
			cr_follow_contains( '>Note</p>', $media, 'media gets Note label' );
		}
	);

	cr_follow_check(
		'note detection survives self-closing foreign and atomic hidden tags',
		static function (): void {
			$rows = array(
				'<span hidden><svg><path d="M0 0"/></svg></span><p>Visible note.</p>' => true,
				'<style hidden>.a{}</style><p>Visible note.</p>'                       => true,
				'<p>&nbsp;</p>'                                                        => false,
				'<span hidden><svg><path d="M0 0"/></svg>Hidden.</span>'               => false,
				'<svg hidden><path d="M0 0"/></svg><p>Visible note.</p>'               => true,
			);
			foreach ( $rows as $input => $expected ) {
				cr_follow_same( $expected, SingleFollow\has_visible_note( $input ), 'has_visible_note: ' . $input );
			}
		}
	);

	cr_follow_check(
		'generated paragraph drops only on its own eligible follow single',
		static function (): void {
			$GLOBALS['cr_follow_singular'] = true;
			$GLOBALS['cr_follow_queried']  = 106;
			$GLOBALS['cr_follow_facts'][106] = cr_follow_facts( array( 'post_id' => 106 ) );
			$paragraph = '<p>Followed <a class="u-follow-of" href="https://amara.example/">https://amara.example/</a></p>';
			cr_follow_same( '', SingleFollow\drop_generated( $paragraph, array( 'crGenerated' => true ) ), 'generated paragraph drops' );
			cr_follow_same( $paragraph, SingleFollow\drop_generated( $paragraph, array( 'crGenerated' => false ) ), 'authored paragraph remains' );
			$GLOBALS['cr_follow_facts'][106] = array();
			cr_follow_same( $paragraph, SingleFollow\drop_generated( $paragraph, array( 'crGenerated' => true ) ), 'ineligible follow remains untouched' );
			$GLOBALS['cr_follow_kind'][106] = 'bookmark';
			cr_follow_same( $paragraph, SingleFollow\drop_generated( $paragraph, array( 'crGenerated' => true ) ), 'other kind remains untouched' );
		}
	);

	cr_follow_check(
		'card adds no mf2 class and contains none of the banned copy',
		static function (): void {
			$html = cr_follow_single( 107, cr_follow_facts( array( 'post_id' => 107 ) ) );
			foreach ( array( 'PUBLIC FOLLOW', 'Visit', 'Request correction', 'removal', 'Found via' ) as $banned ) {
				cr_follow_not_contains( $banned, $html, 'banned copy' );
			}
			preg_match_all( '/class="([^"]*)"/', $html, $matches );
			foreach ( $matches[1] as $classes ) {
				foreach ( explode( ' ', $classes ) as $class_name ) {
					cr_follow_assert( 0 === preg_match( '/^(?:p-|u-|dt-|e-|h-)/', $class_name ), 'theme card mf2 class: ' . $class_name );
				}
			}
		}
	);

	cr_follow_finish();
}
