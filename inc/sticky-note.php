<?php
/**
 * Note kind: an adhesive sticky note lifting at one corner.
 *
 * On /stream the plugin's generic card carries a Note (and any kind-less
 * post, which the plugin badges as a note). This file wraps that card, and
 * the content of a short text-only Note single, in one sticky primitive:
 * a seeded pastel paper with a seeded tilt, no push pin (the adhesive
 * strip is the metaphor; the Quote keeps the pin), a bottom-right corner
 * that curls up, and the thought handwritten. The plugin's kind label and
 * a heading that only repeats the thought stay in the DOM for assistive
 * technology and hide visually. cr-post-kinds.css paints it. A Note whose
 * format is Aside is a margin scrap on its single and on /stream
 * (inc/aside-scrap.php); on the /kind/note/ board it gets the board sticky
 * like every other note (board_item()).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StickyNote;

use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\seed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Paper tints: softened site palette, all ≥ 10:1 against Russian violet ink.
 */
const PAPERS = array( '#ffe8a6', '#fee2c3', '#dedaf2', '#d6ecf6', '#ebebeb' );

/**
 * Tilts a sticky may take, chosen by seed.
 */
const TILTS = array( '-1.2deg', '0.6deg', '-0.5deg', '1.1deg' );

/**
 * Is this the single view of a Note?
 *
 * @return bool
 */
function is_note_single(): bool {
	return \is_singular( 'post' ) && \has_term( 'note', 'kind', \get_queried_object_id() );
}

/**
 * The artifact's inline custom properties: paper tint and tilt, fixed per
 * post.
 *
 * @param int         $seed Stable seed.
 * @param string|null $tilt Optional fixed tilt; null keeps the seeded tilt.
 * @return string A style attribute.
 */
function sticky_style( int $seed, ?string $tilt = null ): string {
	return 'style="--cr-sticky-paper:' . PAPERS[ pick( $seed, 3, count( PAPERS ) ) ] . ';--cr-sticky-tilt:' . ( $tilt ?? TILTS[ pick( $seed, 5, count( TILTS ) ) ] ) . '"';
}

/**
 * Register the Note archive's board and sticky block styles.
 *
 * @return void
 */
function register_board_styles(): void {
	register_block_style(
		'core/post-template',
		array(
			'name'  => 'cr-note-board',
			'label' => __( 'Note board', 'courtneyr-child' ),
		)
	);
	register_block_style(
		'post-kinds-indieweb/note-entry',
		array(
			'name'  => 'cr-sticky',
			'label' => __( 'Sticky', 'courtneyr-child' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_board_styles' );

/**
 * The lifted corner, a decorative layer over the paper's cut corner.
 *
 * @return string
 */
function curl(): string {
	return '<span class="cr-sticky__curl"></span>';
}

/**
 * Text for comparison: tags and entities gone, whitespace collapsed, lowercase.
 *
 * @param string $html Fragment.
 * @return string
 */
function plain( string $html ): string {
	$text = wp_strip_all_tags( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $text ) ) );
}

/**
 * Note archive: wrap a styled note entry in the shared sticky primitive.
 *
 * @param string    $html     Rendered note entry.
 * @param array     $block    Parsed block (unused).
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function board_item( string $html, array $block, $instance ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( array( 'class_name' => 'pkiw-note-entry' ) ) || ! $tags->has_class( 'is-style-cr-sticky' ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$s = seed( (string) $post->ID, $post->post_title );

	return '<div class="cr-sticky-artifact cr-sticky-artifact--board" ' . sticky_style( $s, '0deg' ) . '><div class="cr-sticky cr-sticky--board">' . $html . '</div>' . curl() . '</div>';
}
add_filter( 'render_block_post-kinds-indieweb/note-entry', __NAMESPACE__ . '\\board_item', 10, 3 );

/**
 * Load the Note board paint on its archive and in the editor.
 *
 * @return void
 */
function enqueue_board_styles(): void {
	// Editor parity already lists every kind term; this board needs no body class.
	if ( ! is_admin() && ! is_tax( 'kind', 'note' ) ) {
		return;
	}
	wp_enqueue_style( 'courtneyr-post-kinds', COURTNEYR_CHILD_URI . '/assets/css/cr-post-kinds.css', array(), COURTNEYR_CHILD_VERSION );
	wp_enqueue_style( 'courtneyr-note-board', COURTNEYR_CHILD_URI . '/assets/css/cr-note-board.css', array( 'courtneyr-post-kinds' ), COURTNEYR_CHILD_VERSION );
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_board_styles' );

/**
 * /stream: the plugin's generic note card becomes the sticky.
 *
 * The thought is the excerpt when the post has one, else the title; the
 * heading hides visually (it stays the card's link for assistive tech) when
 * it is the plugin's synthetic kind-label title or when the excerpt already
 * opens with it, so nothing prints twice.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed block (unused).
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function stream_card( string $html, array $block, $instance ): string {
	if ( ! \Courtneyr\Child\HomeSections\is_stream_surface() || false === strpos( $html, 'pk-card--stream k-note' ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || \Courtneyr\Child\AsideScrap\applies( $post ) ) {
		return $html;
	}
	$s = seed( (string) $post->ID, $post->post_title );

	$title   = preg_match( '/<h2 class="pk-title[^"]*">(.*?)<\/h2>/s', $html, $tm ) ? plain( $tm[1] ) : '';
	$excerpt = preg_match( '/<p class="pk-excerpt[^"]*">(.*?)<\/p>/s', $html, $em ) ? plain( $em[1] ) : '';
	$classes = array( 'cr-sticky', 'cr-sticky--stream' );
	if ( '' === $excerpt ) {
		$classes[] = 'cr-sticky--thought-title';
	}
	if ( false === strpos( $html, 'pk-title p-name' ) ) {
		$classes[] = 'cr-sticky--untitled';
	} elseif ( '' !== $excerpt && '' !== $title && str_starts_with( $excerpt, $title ) ) {
		$classes[] = 'cr-sticky--dup-title';
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
		foreach ( $classes as $class ) {
			$tags->add_class( $class );
		}
		$html = $tags->get_updated_html();
	}
	return '<div class="cr-sticky-artifact cr-sticky-artifact--stream" ' . sticky_style( $s ) . '>' . $html . curl() . '</div>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stream_card', 10, 3 );

/**
 * Only paragraphs and lists: a note that carries a figure, an embed, a
 * kind card or code keeps its typeset page.
 *
 * @param \WP_Post $post Post.
 * @return bool
 */
function is_text_only( \WP_Post $post ): bool {
	$ok   = true;
	$walk = static function ( array $blocks ) use ( &$walk, &$ok ): void {
		foreach ( $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );
			if ( '' === $name ) {
				if ( '' !== trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
					$ok = false;
				}
				continue;
			}
			if ( ! in_array( $name, array( 'core/paragraph', 'core/list', 'core/list-item' ), true ) ) {
				$ok = false;
				return;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( (array) $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ) );
	return $ok;
}

/**
 * Single: a short, text-only Note's content becomes a larger sticky; the
 * header's date moves onto the paper. Long or mixed notes keep the page.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function single_sticky( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_note_single() ) {
		return $html;
	}
	$post = \get_post();
	if ( ! $post instanceof \WP_Post || \Courtneyr\Child\AsideScrap\applies( $post ) || ! is_text_only( $post ) ) {
		return $html;
	}
	$words = str_word_count( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ), true ) );
	if ( $words > \Courtneyr\Child\Enqueue\HAND_MAX_WORDS ) {
		return $html;
	}
	$open  = strpos( $html, '>' );
	$close = strrpos( $html, '</div>' );
	if ( false === $open || false === $close || $close <= $open ) {
		return $html;
	}
	$ts    = (int) get_post_time( 'U', true, $post );
	$paper = '<div class="cr-sticky-artifact cr-sticky-artifact--single" ' . sticky_style( seed( (string) $post->ID, $post->post_title ) ) . '>'
		. '<div class="cr-sticky cr-sticky--single">'
		. substr( $html, $open + 1, $close - $open - 1 )
		. '<p class="cr-sticky__date"><time datetime="' . esc_attr( (string) wp_date( 'c', $ts ) ) . '">' . esc_html( (string) wp_date( get_option( 'date_format' ), $ts ) ) . '</time></p>'
		. '</div>' . curl() . '</div>';
	return substr( $html, 0, $open + 1 ) . $paper . substr( $html, $close );
}
add_filter( 'render_block', __NAMESPACE__ . '\\single_sticky', 20, 2 );
