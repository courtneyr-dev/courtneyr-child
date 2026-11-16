<?php
/**
 * `php tests/php/tag-stream-test.php`: Stream name-tag card (A-T3, C17).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/tag-bootstrap.php';
	require CR_TAG_THEME_DIR . '/inc/stamps.php';
	require CR_TAG_THEME_DIR . '/inc/tag.php';
	require CR_TAG_THEME_DIR . '/inc/stream-tag.php';

	use Courtneyr\Child\StreamTag;

	const CR_TAG_STREAM_CARD = '<article class="pk-card pk-card--stream k-tag h-entry"><span class="pk-kindlabel">Tag</span><h3 class="pk-title"><a class="u-url" href="https://example.test/tag-1/">Plugin</a></h3></article>';

	/** Render the Stream adapter for one post. */
	function cr_tag_stream( int $id, array $facts, string $html = CR_TAG_STREAM_CARD ): string {
		$GLOBALS['cr_tag_facts'][ $id ] = $facts;
		$GLOBALS['cr_tag_stream']       = true;
		return StreamTag\name_tag_card( $html, array( 'attrs' => array() ), new WP_Block( array( 'postId' => $id ) ) );
	}

	cr_tag_check(
		'content card: DOM order, one link, hidden props for author, tag-of and later labels',
		static function (): void {
			$html = cr_tag_stream( 201, cr_tag_facts() );
			cr_tag_contains( '<article class="pk-card pk-card--stream cr-name-tag-card cr-name-tag-tilt-', $html, 'card classes' );
			cr_tag_contains( ' h-entry" style="--cr-name-tag-band:var(--cr-', $html, 'h-entry with band style' );
			cr_tag_in_order(
				array(
					'<p class="cr-name-tag-card__kind">Tag</p>',
					'<p class="cr-name-tag-card__sticker cr-name-tag"><span class="cr-name-tag__band" aria-hidden="true">',
					'<span class="cr-name-tag__write-in p-category">Accessibility</span>',
					'<h3 class="cr-name-tag-card__title"><a class="u-url" href="https://example.test/tag-201/">Alt text decision guide</a></h3>',
					'<p class="cr-name-tag-card__host">guides.example.com</p>',
					'<time class="dt-published cr-name-tag-card__date" datetime="2026-11-16T12:00:00+00:00">Nov 16</time>',
					'<span hidden>',
				),
				$html,
				'C17 DOM order'
			);
			cr_tag_same( 1, substr_count( $html, '<a ' ) - substr_count( $html, 'p-author' ), 'exactly one visible link' );
			cr_tag_contains( '<data class="u-tag-of" value="https://guides.example.com/alt-text/"></data>', $html, 'hidden tag-of' );
			cr_tag_contains( '<data class="p-category" value="Images"></data><data class="p-category" value="Writing"></data>', $html, 'later labels hidden' );
			cr_tag_not_contains( 'value="Accessibility"', $html, 'first label not repeated' );
			cr_tag_not_contains( 'View tag post', $html, 'no CTA' );
			cr_tag_not_contains( 'target="_blank"', $html, 'no external link' );
			cr_tag_not_contains( '<img', $html, 'no image' );
		}
	);

	cr_tag_check(
		'person card: h-card in hidden props, no photo or initial visible; no sticker without a label',
		static function (): void {
			$html = cr_tag_stream(
				202,
				cr_tag_facts(
					array(
						'target_type'   => 'person',
						'url'           => 'https://person-two.example.org/',
						'url_host'      => 'person-two.example.org',
						'title'         => 'Example Person Two',
						'display_title' => 'Example Person Two',
						'labels'        => array(),
						'label'         => '',
						'initial'       => 'E',
					)
				)
			);
			cr_tag_contains( '<span class="u-tag-of h-card"><data class="p-name" value="Example Person Two"></data><data class="u-url" value="https://person-two.example.org/"></data></span>', $html, 'hidden person h-card' );
			cr_tag_not_contains( 'cr-name-tag-card__sticker', $html, 'no sticker without a label' );
			cr_tag_not_contains( 'initial', $html, 'no initial tile' );
			cr_tag_not_contains( 'u-photo', $html, 'no photo' );
		}
	);

	cr_tag_check(
		'p-name only on a synthetic title; heading level follows the incoming card',
		static function (): void {
			$html = cr_tag_stream( 203, cr_tag_facts( array( 'title_is_target' => false ) ), str_replace( 'h3', 'h2', CR_TAG_STREAM_CARD ) );
			cr_tag_contains( '<h2 class="cr-name-tag-card__title"><a class="u-url p-name"', $html, 'h2 with p-name' );
		}
	);

	cr_tag_check(
		'pass-through: protected, non-tag, off the Stream, not public, already adapted',
		static function (): void {
			$protected = '<article class="pk-card pk-card--stream pk-card--protected">Locked</article>';
			cr_tag_same( $protected, cr_tag_stream( 204, cr_tag_facts(), $protected ), 'protected card' );

			$GLOBALS['cr_tag_kind'][205] = 'bookmark';
			cr_tag_same( CR_TAG_STREAM_CARD, cr_tag_stream( 205, cr_tag_facts() ), 'non-tag card' );

			cr_tag_same( CR_TAG_STREAM_CARD, cr_tag_stream( 206, array() ), 'no facts: plugin card stays' );

			$GLOBALS['cr_tag_facts'][207] = cr_tag_facts();
			$GLOBALS['cr_tag_stream']     = false;
			cr_tag_same( CR_TAG_STREAM_CARD, StreamTag\name_tag_card( CR_TAG_STREAM_CARD, array(), new WP_Block( array( 'postId' => 207 ) ) ), 'off the Stream surface' );

			$adapted = cr_tag_stream( 208, cr_tag_facts() );
			cr_tag_same( $adapted, cr_tag_stream( 208, cr_tag_facts(), $adapted ), 'already adapted' );

			cr_tag_assert( null !== cr_tag_hook( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamTag\\name_tag_card' ), 'hooked on stream-card' );
		}
	);

	cr_tag_finish();
}
