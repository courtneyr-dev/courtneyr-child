<?php
/**
 * `php tests/php/chicken-single-test.php`: standalone checks for the chicken
 * single (inc/single-chicken.php). Also run with `--no-plugin`.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/chicken-bootstrap.php';

use Courtneyr\Child\SingleChicken;

const CR_CHK_CONTENT = '<div class="entry-content wp-block-post-content single-post__content"><div class="h-entry"><p>Pepper on the path.</p></div></div>';

/** Fixtures for the Core blocks the board renders. */
function cr_chk_single_setup( array $facts_override = array(), bool $featured = true ): WP_Post {
	$post                                      = cr_chk_reset();
	$GLOBALS['cr_chk_request']['single']       = true;
	$GLOBALS['cr_chk_facts'][ $post->ID ]      = array_merge( cr_chk_default_facts(), $facts_override );
	$GLOBALS['cr_chk_blocks']['core/post-date'] = '<div class="wp-block-post-date cr-coop__date"><time datetime="2026-09-24T09:05:00-04:00">September 24, 2026</time></div>';
	$GLOBALS['cr_chk_blocks']['core/post-title'] = static fn( array $block ): string => '<h' . (int) $block['attrs']['level'] . ' class="wp-block-post-title has-accent-font-family ' . $block['attrs']['className'] . '">' . get_post( 42 )->post_title . '</h1>';
	$GLOBALS['cr_chk_blocks']['core/post-featured-image'] = $featured ? '<figure class="wp-block-post-featured-image cr-coop__photo"><img src="https://example.test/hen.jpg" alt="A hen" /></figure>' : '';
	return $post;
}

/** Top-level element tags (and classes) of the board. */
function cr_chk_board_children( string $html ): array {
	$doc = new DOMDocument();
	@$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
	$board = null;
	foreach ( $doc->getElementsByTagName( 'div' ) as $div ) {
		if ( str_contains( (string) $div->getAttribute( 'class' ), 'cr-coop__board' ) ) {
			$board = $div;
			break;
		}
	}
	$out = array();
	if ( $board ) {
		foreach ( $board->childNodes as $node ) {
			if ( $node instanceof DOMElement ) {
				$out[] = $node->tagName . '.' . preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) )[0];
			}
		}
	}
	return $out;
}

cr_chk_group(
	'the filter, the claim on the H1 and the post-content hook are registered',
	static function (): void {
		cr_chk_same( array( 20 ), cr_chk_priorities( 'render_block_core/post-content', 'Courtneyr\\Child\\SingleChicken\\board' ), 'board at 20 on core/post-content' );
		cr_chk_same( array( 10 ), cr_chk_priorities( 'courtneyr_child_single_object_holds_h1', 'Courtneyr\\Child\\SingleChicken\\holds_h1' ), 'holds_h1 at 10' );
		$post = cr_chk_reset();
		cr_chk_same( true, SingleChicken\holds_h1( false, $post ), 'a chicken post holds the H1' );
		$GLOBALS['cr_chk_request']['post_kinds'][42] = array( 'read' );
		cr_chk_same( false, SingleChicken\holds_h1( false, $post ), 'another kind keeps the answer it was given' );
		cr_chk_same( true, SingleChicken\holds_h1( true, $post ), 'and does not take it back' );
		cr_chk_same( false, SingleChicken\holds_h1( false, null ), 'no post, no claim' );
	}
);

if ( ! $GLOBALS['cr_chk_no_plugin'] ) {
	cr_chk_group(
		'a titled single prints date, one H1, the picture, then the content, in one board',
		static function (): void {
			cr_chk_single_setup();
			$out = SingleChicken\board( CR_CHK_CONTENT );
			cr_chk_same( array( 'div.wp-block-post-date', 'h1.wp-block-post-title', 'figure.wp-block-post-featured-image', 'div.entry-content' ), cr_chk_board_children( $out ), 'children in order' );
			cr_chk_same( 1, substr_count( $out, '<h1' ), 'one H1' );
			cr_chk_same( 1, substr_count( $out, '<img' ), 'one image' );
			cr_chk_contains( 'class="cr-coop"><div class="cr-coop__board">', $out, 'frame and board' );
			cr_chk_contains( 'data-cr-cover-fallback="true"', $out, 'the picture can fall back' );
			cr_chk_contains( 'cr-coop__title', $out, 'the H1 carries the board class' );
			cr_chk_contains( 'Crossing the path', $out, 'the stored title' );
			cr_chk_lacks( 'u-url', $out, 'the theme adds no microformats class' );
			cr_chk_lacks( 'dt-published', $out, 'the date block carries no dt-published' );
			cr_chk_lacks( 'p-name', $out, 'the board adds no p-name' );
			cr_chk_contains( CR_CHK_CONTENT, $out, 'the content, with the plugin wrapper, passes through whole' );
		}
	);
}

if ( ! $GLOBALS['cr_chk_no_plugin'] ) {
	cr_chk_group(
		'the Core blocks are asked for with the contract attributes',
		static function (): void {
			cr_chk_single_setup();
			$seen = array();
			$GLOBALS['cr_chk_blocks']['core/post-date']           = static function ( array $block ) use ( &$seen ): string {
				$seen['date'] = $block['attrs'];
				return '<div class="wp-block-post-date"><time>x</time></div>';
			};
			$GLOBALS['cr_chk_blocks']['core/post-title']          = static function ( array $block ) use ( &$seen ): string {
				$seen['title'] = $block['attrs'];
				return '<h1>x</h1>';
			};
			$GLOBALS['cr_chk_blocks']['core/post-featured-image'] = static function ( array $block ) use ( &$seen ): string {
				$seen['image'] = $block['attrs'];
				return '<figure><img src="x" alt="" /></figure>';
			};
			SingleChicken\board( CR_CHK_CONTENT );
			cr_chk_same( array( 'format' => 'F j, Y', 'className' => 'cr-coop__date' ), $seen['date'], 'date' );
			cr_chk_same( array( 'level' => 1, 'fontFamily' => 'accent', 'className' => 'single-post__title cr-coop__title' ), $seen['title'], 'title' );
			cr_chk_same( array( 'sizeSlug' => 'large', 'className' => 'cr-coop__photo' ), $seen['image'], 'image' );
		}
	);
}

cr_chk_group(
	'an untitled single prints one plain H1 with kind, date and time, and no link or p-name',
	static function (): void {
		$post = cr_chk_single_setup( array( 'named' => false, 'name' => 'Chicken, September 24, 2026' ) );
		$post->post_title = '';
		$out = SingleChicken\board( CR_CHK_CONTENT );
		cr_chk_same( 1, substr_count( $out, '<h1' ), 'one H1' );
		cr_chk_contains( 'is-untitled">Chicken, September 24, 2026 at 9:05 am</h1>', $out, 'kind, date and time' );
		cr_chk_lacks( '<a ', $out, 'no link' );
		cr_chk_lacks( 'p-name', $out, 'no p-name' );
		cr_chk_lacks( 'u-url', $out, 'no u-url' );
	}
);

cr_chk_group(
	'a protected single prints the title and the form, and no picture',
	static function (): void {
		$post                                      = cr_chk_single_setup();
		$post->post_title                          = 'Locked coop log';
		$GLOBALS['cr_chk_request']['protected'][42] = true;
		$GLOBALS['cr_chk_facts'][42]               = array();
		$GLOBALS['cr_chk_blocks']['core/post-title'] = '<h1 class="wp-block-post-title cr-coop__title">Protected: Locked coop log</h1>';
		$GLOBALS['cr_chk_blocks']['core/post-date']  = '';
		$form = '<div class="entry-content wp-block-post-content"><form class="post-password-form"></form></div>';
		$out  = SingleChicken\board( $form );
		cr_chk_same( array( 'div.wp-block-post-date', 'h1.wp-block-post-title', 'div.entry-content' ), cr_chk_board_children( $out ), 'date, title, form' );
		cr_chk_lacks( '<img', $out, 'no image' );
		cr_chk_lacks( '<figure', $out, 'no figure' );
		cr_chk_contains( 'Protected: Locked coop log', $out, 'the protected title' );
		cr_chk_contains( '<div class="wp-block-post-date cr-coop__date"><time datetime="', $out, 'the date prints though Core renders none' );
		cr_chk_contains( 'September 24, 2026</time></div>', $out, 'with the post date text' );

		$GLOBALS['cr_chk_request']['protected'][42] = false;
		cr_chk_lacks( 'cr-coop__date', SingleChicken\board( $form ), 'an unprotected post with no date block prints none' );
	}
);

cr_chk_group(
	'the picture prints only when the plugin says it is the featured image, and only when the repeat filter lets it through',
	static function (): void {
		cr_chk_single_setup( array( 'picture' => array( 'source' => 'cover', 'url' => 'https://example.test/c.jpg' ) ) );
		cr_chk_lacks( '<figure', SingleChicken\board( CR_CHK_CONTENT ), 'a cover is not a featured image' );

		cr_chk_single_setup( array( 'picture' => array( 'source' => '' ) ) );
		cr_chk_lacks( '<figure', SingleChicken\board( CR_CHK_CONTENT ), 'no picture' );

		// The shared W3-T0 filter returns '' when the content shows the same attachment.
		cr_chk_single_setup( array( 'featured_in_content' => true ), false );
		$out = SingleChicken\board( CR_CHK_CONTENT );
		cr_chk_lacks( '<figure', $out, 'suppressed duplicate leaves no empty figure' );
		cr_chk_same( array( 'div.wp-block-post-date', 'h1.wp-block-post-title', 'div.entry-content' ), cr_chk_board_children( $out ), 'date, title, content' );
	}
);

cr_chk_group(
	'the board stays out of the way on other requests',
	static function (): void {
		cr_chk_single_setup();
		$GLOBALS['cr_chk_request']['single'] = false;
		cr_chk_same( CR_CHK_CONTENT, SingleChicken\board( CR_CHK_CONTENT ), 'not a single' );

		cr_chk_single_setup();
		$GLOBALS['cr_chk_request']['post_kinds'][42] = array( 'read' );
		cr_chk_same( CR_CHK_CONTENT, SingleChicken\board( CR_CHK_CONTENT ), 'not a chicken' );

		cr_chk_single_setup();
		cr_chk_same( CR_CHK_CONTENT, SingleChicken\board( CR_CHK_CONTENT, array(), new WP_Block( array( 'postId' => 99 ) ) ), 'a post-content block for another post' );
	}
);

if ( $GLOBALS['cr_chk_no_plugin'] ) {
	cr_chk_group(
		'without the plugin the board prints date, title and content and no picture',
		static function (): void {
			cr_chk_single_setup();
			$out = SingleChicken\board( CR_CHK_CONTENT );
			cr_chk_same( array( 'div.wp-block-post-date', 'h1.wp-block-post-title', 'div.entry-content' ), cr_chk_board_children( $out ), 'children' );
			cr_chk_lacks( '<img', $out, 'no image' );
		}
	);
}

cr_chk_finish();
