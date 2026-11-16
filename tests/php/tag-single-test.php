<?php
/**
 * `php tests/php/tag-single-test.php`: tag single sticker and clipping (A-T2, C16).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/tag-bootstrap.php';
	require CR_TAG_THEME_DIR . '/inc/stamps.php';
	require CR_TAG_THEME_DIR . '/inc/tag.php';
	require CR_TAG_THEME_DIR . '/inc/single-tag.php';

	use Courtneyr\Child\SingleTag;

	const CR_TAG_PLUGIN_CARD = '<article class="pk-card k-tag pkiw-tag-card pkiw-tag-card--content">plugin card</article>';

	/** Render the clipping filter for a post as the queried single. */
	function cr_tag_single( int $id, array $facts ): string {
		$GLOBALS['cr_tag_facts'][ $id ] = $facts;
		$GLOBALS['cr_tag_singular']     = true;
		$GLOBALS['cr_tag_queried']      = $id;
		return SingleTag\clipping( CR_TAG_PLUGIN_CARD, array( 'attrs' => array() ), new WP_Block( array( 'postId' => $id ) ) );
	}

	cr_tag_check(
		'S01: content target with three labels, description and image',
		static function (): void {
			$html = cr_tag_single( 101, cr_tag_facts() );
			cr_tag_not_contains( 'plugin card', $html, 'plugin card replaced' );
			cr_tag_same( 1, substr_count( $html, '<a ' ), 'exactly one link' );
			cr_tag_contains( '<a class="u-tag-of" href="https://guides.example.com/alt-text/" rel="noopener noreferrer" target="_blank" aria-label="Alt text decision guide (opens in a new tab)">Alt text decision guide<svg class="cr-name-tag-clipping__external" aria-hidden="true" focusable="false"', $html, 'external title link with aria-hidden icon' );
			cr_tag_contains( '<p class="cr-name-tag-clipping__host">guides.example.com</p>', $html, 'host as text' );
			cr_tag_contains( '<p class="cr-name-tag-clipping__description">A guide to deciding when images need descriptions.</p>', $html, 'description' );
			cr_tag_contains( 'alt="A decision tree drawn on paper."', $html, 'image with stored alt' );
			cr_tag_contains( '<span class="screen-reader-text">Labels: </span>', $html, 'hidden plural prefix' );
			cr_tag_in_order( array( '<span class="p-category">Accessibility</span>', '<span class="p-category">Images</span>', '<span class="p-category">Writing</span>' ), $html, 'labels in authored order' );
			cr_tag_contains( '<span class="cr-name-tag__band" aria-hidden="true">', $html, 'aria-hidden band' );
			cr_tag_contains( '<span class="cr-name-tag__tape" aria-hidden="true"></span>', $html, 'tape' );
			cr_tag_in_order( array( 'cr-name-tag-single__sticker', 'cr-name-tag-clipping' ), $html, 'sticker before clipping in DOM' );
			cr_tag_same( 1, preg_match_all( '/cr-name-tag-tilt-\d/', $html ), 'one rotated sticker' );
			cr_tag_not_contains( 'View the tagged page', $html, 'no button' );
			cr_tag_not_contains( '<h1', $html, 'the template keeps the only H1' );
			cr_tag_not_contains( 'Tagged:', $html, 'no theme prefix' );
			cr_tag_not_contains( 'Label:</', $html, 'no visible label row' );
		}
	);

	cr_tag_check(
		'S02 and S03: person targets print an h-card with a local photo or an initial tile',
		static function (): void {
			$photo = cr_tag_single(
				102,
				cr_tag_facts(
					array(
						'target_type'     => 'person',
						'url'             => 'https://person-one.example.net/',
						'url_host'        => 'person-one.example.net',
						'title'           => 'Example Person One',
						'display_title'   => 'Example Person One',
						'labels'          => array( 'People' ),
						'label'           => 'People',
						'description'     => '',
						'image_id'        => 12,
						'image_alt'       => '',
					)
				)
			);
			cr_tag_contains( '<span class="u-tag-of h-card"><img src="https://example.test/uploads/12-thumbnail.png" class="u-photo cr-name-tag-clipping__photo" alt="">', $photo, 'local u-photo with empty alt' );
			cr_tag_contains( '<a class="p-name u-url" href="https://person-one.example.net/" rel="noopener noreferrer">Example Person One</a>', $photo, 'name link' );
			cr_tag_contains( '<span class="screen-reader-text">Label: </span>', $photo, 'singular prefix' );
			cr_tag_same( 1, substr_count( $photo, '<a ' ), 'one link' );
			cr_tag_not_contains( 'target="_blank"', $photo, 'person link stays in this tab' );

			$initial = cr_tag_single(
				103,
				cr_tag_facts(
					array(
						'target_type'   => 'person',
						'url'           => 'https://person-two.example.org/',
						'url_host'      => 'person-two.example.org',
						'title'         => 'Example Person Two',
						'display_title' => 'Example Person Two',
						'labels'        => array( 'Mentors' ),
						'label'         => 'Mentors',
						'image_id'      => 0,
						'image'         => '',
						'initial'       => 'E',
					)
				)
			);
			cr_tag_contains( '<span class="cr-name-tag-clipping__initial" aria-hidden="true">E</span>', $initial, 'aria-hidden initial tile' );
			cr_tag_not_contains( 'u-photo', $initial, 'no u-photo without a photo' );
		}
	);

	cr_tag_check(
		'S04: no labels means no sticker and no empty boxes',
		static function (): void {
			$html = cr_tag_single(
				104,
				cr_tag_facts(
					array(
						'url'             => 'https://docs.example.org/page/',
						'url_host'        => 'docs.example.org',
						'title'           => '',
						'display_title'   => 'Docs page worth keeping',
						'title_is_target' => false,
						'labels'          => array(),
						'label'           => '',
						'description'     => '',
						'image_id'        => 0,
						'image'           => '',
						'image_alt'       => '',
					)
				)
			);
			cr_tag_not_contains( 'cr-name-tag-single__sticker', $html, 'no sticker' );
			cr_tag_not_contains( 'cr-name-tag__band', $html, 'no band' );
			cr_tag_contains( 'cr-name-tag-single--no-sticker', $html, 'layout knows there is no sticker' );
			cr_tag_contains( '>docs.example.org<svg', $html, 'host is the link text when no title is stored' );
			cr_tag_not_contains( 'cr-name-tag-clipping__description', $html, 'no empty description' );
			cr_tag_not_contains( '<figure', $html, 'no empty image' );
		}
	);

	cr_tag_check(
		'the plugin card passes through off the queried single, without facts, and after the first card',
		static function (): void {
			$GLOBALS['cr_tag_facts'][105] = cr_tag_facts();
			$GLOBALS['cr_tag_singular']   = true;
			$GLOBALS['cr_tag_queried']    = 999;
			cr_tag_same( CR_TAG_PLUGIN_CARD, SingleTag\clipping( CR_TAG_PLUGIN_CARD, array(), new WP_Block( array( 'postId' => 105 ) ) ), 'another post\'s card' );

			$GLOBALS['cr_tag_queried']  = 105;
			$GLOBALS['cr_tag_singular'] = false;
			cr_tag_same( CR_TAG_PLUGIN_CARD, SingleTag\clipping( CR_TAG_PLUGIN_CARD, array(), new WP_Block( array( 'postId' => 105 ) ) ), 'not singular' );

			cr_tag_same( CR_TAG_PLUGIN_CARD, cr_tag_single( 106, array() ), 'not public: plugin card stays' );

			$GLOBALS['cr_tag_kind'][107] = 'bookmark';
			cr_tag_same( CR_TAG_PLUGIN_CARD, cr_tag_single( 107, cr_tag_facts() ), 'not a tag post' );

			$first  = cr_tag_single( 108, cr_tag_facts() );
			$second = SingleTag\clipping( CR_TAG_PLUGIN_CARD, array(), new WP_Block( array( 'postId' => 108 ) ) );
			cr_tag_not_contains( 'plugin card', $first, 'first card adapted' );
			cr_tag_same( CR_TAG_PLUGIN_CARD, $second, 'second card passes through' );
			cr_tag_assert( null !== cr_tag_hook( 'render_block_post-kinds-indieweb/tag-card', 'Courtneyr\\Child\\SingleTag\\clipping' ), 'hooked' );
			cr_tag_same( 20, cr_tag_hook( 'render_block_post-kinds-indieweb/tag-card', 'Courtneyr\\Child\\SingleTag\\clipping' )[2] ?? 0, 'priority 20' );
		}
	);

	cr_tag_check(
		'labels over 24 characters use the long-label face; text is escaped',
		static function (): void {
			$html = cr_tag_single( 109, cr_tag_facts( array( 'labels' => array( 'Short', 'A label that is much longer than twenty-four', '<b>x</b>' ) ) ) );
			cr_tag_contains( '<span class="p-category">Short</span>', $html, 'short label' );
			cr_tag_contains( '<span class="p-category cr-name-tag__label--long">A label that is much longer than twenty-four</span>', $html, 'long label' );
			cr_tag_contains( '&lt;b&gt;x&lt;/b&gt;', $html, 'escaped label' );
		}
	);

	cr_tag_finish();
}
