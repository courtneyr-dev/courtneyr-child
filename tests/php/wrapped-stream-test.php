<?php
/**
 * `php tests/php/wrapped-stream-test.php`: Wrapped Stream card contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace PKIW {
	function wrapped_facts( int $post_id ): array {
		return $GLOBALS['cr_wrapped_facts'][ $post_id ] ?? array();
	}

	function get_kind_icon_svg( string $kind ): string {
		return '<svg aria-hidden="true" data-kind="' . \esc_attr( $kind ) . '"></svg>';
	}

	function entry_author_html( \WP_Post $post ): string {
		return '<span class="p-author h-card"><span class="p-name">Courtney</span><data class="u-url" value="https://example.test/author/"></data></span>';
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_wrapped_request']['stream'] );
	}
}

namespace {
	require __DIR__ . '/wrapped-bootstrap.php';
	require CR_WRAPPED_THEME_DIR . '/inc/wrapped.php';
	require CR_WRAPPED_THEME_DIR . '/inc/stream-wrapped.php';

	use Courtneyr\Child\StreamWrapped;

	const CR_WRAPPED_PLUGIN_CARD = '<article class="pk-card pk-card--stream h-entry"><h3 class="pk-title"><a href="https://example.test/plugin/">Plugin card</a></h3></article>';

	$GLOBALS['cr_wrapped_posts'][267] = new WP_Post( 267 );
	$GLOBALS['cr_wrapped_facts'][267] = array(
		'kind'         => 'read',
		'title'        => 'Read 2026 Wrapped',
		'card_metrics' => array(
			array( 'display' => '21', 'label' => 'Finished' ),
			array( 'display' => '7,842', 'label' => 'Pages' ),
			array( 'display' => '5-week', 'label' => 'Streak' ),
			array( 'display' => '4.2', 'label' => 'Average' ),
		),
	);
	$GLOBALS['cr_wrapped_request'] = array( 'stream' => true );
	$html = StreamWrapped\wrapped_card( CR_WRAPPED_PLUGIN_CARD, array(), new WP_Block( array( 'postId' => 267 ) ) );
	cr_wrapped_contains( '<article class="pk-card pk-card--stream cr-wrapped-card h-entry"><span class="cr-wrapped-card__paper" aria-hidden="true"></span>', $html, 'card has Stream and h-entry classes and a decorative paper layer first' );
	cr_wrapped_contains( '<h3 class="cr-wrapped-card__title"><a class="u-url p-name"', $html, 'incoming heading level and title microformats are preserved' );
	cr_wrapped_check( 1 === substr_count( $html, '<a ' ), 'card has exactly one anchor' );
	cr_wrapped_check( 3 === substr_count( $html, '<li>' ), 'card caps metrics at three' );
	cr_wrapped_contains( '<span class="cr-wrapped-card__value">21</span> <span class="cr-wrapped-card__label">Finished</span>', $html, 'metric keeps a literal text space' );
	cr_wrapped_contains( 'class="dt-published cr-wrapped-card__date"', $html, 'card has dt-published' );
	cr_wrapped_contains( 'class="p-author h-card"', $html, 'card has hidden p-author' );
	cr_wrapped_contains( '<span class="cr-wrapped-card__glyph" aria-hidden="true"><svg aria-hidden="true"', $html, 'only decorative glyph SVG is aria-hidden' );
	cr_wrapped_not_contains( '<img', $html, 'card has no image' );
	cr_wrapped_not_contains( 'Open Wrapped', $html, 'card has no CTA copy' );
	cr_wrapped_check( isset( $GLOBALS['cr_wrapped_styles']['courtneyr-wrapped'] ), 'first Wrapped card enqueues shared sheet' );

	$protected = '<article class="pk-card pk-card--protected">Protected</article>';
	cr_wrapped_check( $protected === StreamWrapped\wrapped_card( $protected, array(), new WP_Block( array( 'postId' => 267 ) ) ), 'protected card is unchanged' );
	cr_wrapped_check( $html === StreamWrapped\wrapped_card( $html, array(), new WP_Block( array( 'postId' => 267 ) ) ), 'already-built card is unchanged' );
	cr_wrapped_check( CR_WRAPPED_PLUGIN_CARD === StreamWrapped\wrapped_card( CR_WRAPPED_PLUGIN_CARD, array(), new WP_Block( array( 'postId' => 999 ) ) ), 'empty-facts card is unchanged' );
	$GLOBALS['cr_wrapped_request'] = array();
	cr_wrapped_check( CR_WRAPPED_PLUGIN_CARD === StreamWrapped\wrapped_card( CR_WRAPPED_PLUGIN_CARD, array(), new WP_Block( array( 'postId' => 267 ) ) ), 'off-Stream card is unchanged' );
	$GLOBALS['cr_wrapped_request'] = array( 'stream' => true );
	cr_wrapped_check( CR_WRAPPED_PLUGIN_CARD === StreamWrapped\wrapped_card( CR_WRAPPED_PLUGIN_CARD, array(), new WP_Block( array( 'postId' => 998 ) ) ), 'non-Wrapped card is unchanged' );

	cr_wrapped_finish();
}
