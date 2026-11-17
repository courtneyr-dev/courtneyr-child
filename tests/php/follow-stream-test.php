<?php
/**
 * Follow Stream adapter contracts (acceptance item 17).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/follow-bootstrap.php';
	require CR_FOLLOW_THEME_DIR . '/inc/stamps.php';
	require CR_FOLLOW_THEME_DIR . '/inc/follow.php';
	require CR_FOLLOW_THEME_DIR . '/inc/stream-follow.php';

	use Courtneyr\Child\StreamFollow;

	const CR_FOLLOW_STREAM_CARD = '<article class="pk-card pk-card--stream k-follow h-entry"><h3 class="pk-title"><a class="u-url" href="/plugin/">Plugin</a></h3><p>Plugin note</p></article>';

	/** Render one Stream follow. */
	function cr_follow_stream( int $id, array $facts, string $html = CR_FOLLOW_STREAM_CARD ): string {
		$GLOBALS['cr_follow_facts'][ $id ] = $facts;
		$GLOBALS['cr_follow_stream']       = true;
		$GLOBALS['cr_follow_posts'][ $id ] = new WP_Post( $id );
		return StreamFollow\card( $html, array( 'attrs' => array() ), new WP_Block( array( 'postId' => $id ) ) );
	}

	cr_follow_check(
		'Stream card follows C13 order with one link and hidden properties',
		static function (): void {
			$facts = cr_follow_facts( array( 'post_id' => 201, 'photo_id' => 45 ) );
			$html  = cr_follow_stream( 201, $facts );
			cr_follow_in_order(
				array(
					'<span class="cr-follow-stream__wheel" aria-hidden="true"></span>',
					'<p class="cr-follow-stream__kind">Follow</p>',
					'<figure class="cr-follow-stream__photo" data-cr-cover-fallback aria-hidden="true">',
					'<span class="cr-follow-stream__initial" aria-hidden="true">A</span>',
					'<h3 class="cr-follow-stream__name"><a class="u-url" id="cr-follow-201-name" href="https://example.test/seed-260-f201/" aria-labelledby="cr-follow-201-name cr-follow-201-host">Amara Okafor</a></h3>',
					'<p class="cr-follow-stream__host" id="cr-follow-201-host">amara.example</p>',
					'<time class="cr-follow-stream__day dt-published" datetime="2026-11-15T14:00:00+00:00">November 15, 2026</time>',
					'<span hidden><span class="p-author h-card">',
					'<span class="u-follow-of h-card"><data class="p-name" value="Amara Okafor"></data>',
				),
				$html,
				'C13 Stream DOM order'
			);
			cr_follow_same( 1, substr_count( $html, '<a ' ), 'exactly one anchor' );
			cr_follow_not_contains( 'Plugin note', $html, 'no note' );
			cr_follow_not_contains( 'k-follow', $html, 'no legacy class' );
			cr_follow_contains( '--cr-follow-paper:var(--cr-', $html, 'same seeded paper custom property' );
			cr_follow_contains( '--cr-follow-rotate:var(--cr-rotate-', $html, 'one rotation token' );
			cr_follow_contains( '<data class="u-photo" value="https://example.test/wp-content/uploads/45-medium.jpg"></data>', $html, 'local hidden photo' );
		}
	);

	cr_follow_check(
		'host is omitted when the name comes from the host and no URL omits the h-card',
		static function (): void {
			$html = cr_follow_stream( 202, cr_follow_facts( array( 'post_id' => 202, 'name' => 'amara.example', 'name_source' => 'host' ) ) );
			cr_follow_not_contains( 'cr-follow-stream__host', $html, 'host is not repeated' );
			$none = cr_follow_stream( 203, cr_follow_facts( array( 'post_id' => 203, 'url' => '', 'host' => '' ) ) );
			cr_follow_not_contains( 'u-follow-of h-card', $none, 'no followed h-card without URL' );
		}
	);

	cr_follow_check(
		'initial tile renders without a photo',
		static function (): void {
			$html = cr_follow_stream( 204, cr_follow_facts( array( 'post_id' => 204, 'photo_id' => 0 ) ) );
			cr_follow_contains( '<span class="cr-follow-stream__initial" aria-hidden="true">A</span>', $html, 'initial tile' );
			cr_follow_not_contains( '<figure', $html, 'no empty photo figure' );
			cr_follow_not_contains( 'u-photo', $html, 'no hidden photo' );
		}
	);

	cr_follow_check(
		'protected, non-follow, non-Stream and ineligible cards pass through',
		static function (): void {
			$protected = '<article class="pk-card pk-card--stream pk-card--protected">Protected</article>';
			cr_follow_same( $protected, cr_follow_stream( 205, cr_follow_facts(), $protected ), 'protected card' );
			$GLOBALS['cr_follow_kind'][206] = 'bookmark';
			cr_follow_same( CR_FOLLOW_STREAM_CARD, cr_follow_stream( 206, cr_follow_facts() ), 'non-follow card' );
			cr_follow_same( CR_FOLLOW_STREAM_CARD, cr_follow_stream( 207, array() ), 'empty facts' );

			$GLOBALS['cr_follow_facts'][208] = cr_follow_facts();
			$GLOBALS['cr_follow_posts'][208] = new WP_Post( 208 );
			$GLOBALS['cr_follow_stream']     = false;
			cr_follow_same( CR_FOLLOW_STREAM_CARD, StreamFollow\card( CR_FOLLOW_STREAM_CARD, array(), new WP_Block( array( 'postId' => 208 ) ) ), 'off Stream' );
			cr_follow_assert( null !== cr_follow_hook( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\Child\StreamFollow\card' ), 'adapter hook' );
		}
	);

	cr_follow_finish();
}
