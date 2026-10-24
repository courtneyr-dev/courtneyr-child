<?php
/**
 * Quote archive commonplace-book presentation.
 *
 * The plugin owns quote grouping, card links and entry microformats. This
 * file registers the theme's block style, adds deterministic paper and strip
 * colours, turns each visible quote entry into the single's pinned notecard
 * (the post's own quote block in place of the plugin's plain excerpt), and
 * loads the book stylesheet where it can render.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\QuoteBook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'quote';
const STYLE_HANDLE = 'courtneyr-quote-book';
const STYLE_SLUG   = 'cr-commonplace';
const STRIPS       = array(
	'var(--cr-periwinkle)',
	'var(--cr-sky-blue)',
	'var(--cr-light-orange)',
	'var(--cr-selective-yellow)',
	'#f3e5b8',
	'var(--cr-light-gray)',
	'var(--cr-blue-green)',
);
const EMPTY_STRIP  = 'var(--cr-light-gray)';

/** Register the commonplace-book style when the plugin block is available. */
function register_block_styles(): void {
	if (
		! function_exists( 'register_block_style' )
		|| ! class_exists( '\\WP_Block_Type_Registry' )
		|| ! \WP_Block_Type_Registry::get_instance()->is_registered( 'post-kinds-indieweb/quote-entry' )
	) {
		return;
	}

	register_block_style(
		'post-kinds-indieweb/quote-entry',
		array(
			'name'  => STYLE_SLUG,
			'label' => __( 'Commonplace book', 'courtneyr-child' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles', 20, 1 );

/**
 * Append one custom property to the processor's current tag.
 *
 * @param \WP_HTML_Tag_Processor $tags  Processor on the target tag.
 * @param string                 $name  Custom-property name.
 * @param string                 $value Custom-property value.
 */
function append_property( \WP_HTML_Tag_Processor $tags, string $name, string $value ): void {
	$style = trim( (string) $tags->get_attribute( 'style' ) );
	if ( '' !== $style && ! str_ends_with( $style, ';' ) ) {
		$style .= ';';
	}
	$tags->set_attribute( 'style', $style . $name . ':' . $value );
}

/**
 * Put the single and Stream paper on one commonplace-book card.
 *
 * @param string               $html     Rendered quote entry.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance with Query Loop context.
 * @return string
 */
function entry_paper( string $html, array $block, $instance ): string {
	if ( '' === $html || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	$tokens     = explode( ' ', str_replace( array( "\t", "\n", "\r", "\f" ), ' ', $class_name ) );
	if ( ! in_array( 'is-style-' . STYLE_SLUG, $tokens, true ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if (
		! $tags->next_tag(
			array(
				'tag_name'   => 'div',
				'class_name' => 'pkiw-quote-entry',
			)
		)
		|| $tags->has_class( 'pkiw-quote-entry--continued' )
	) {
		return $html;
	}

	$post_id = $instance instanceof \WP_Block && ! empty( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = get_post( $post_id );
	$paper   = 'var(--cr-printer-ivory)';
	if (
		$post instanceof \WP_Post
		&& ! post_password_required( $post )
		&& function_exists( '\\Courtneyr\\Child\\QuoteNote\\paper_for' )
	) {
		$paper = \Courtneyr\Child\QuoteNote\paper_for( $post );
	}

	$tags->add_class( 'is-style-' . STYLE_SLUG );
	append_property( $tags, '--cr-quote-paper', $paper );
	return $tags->get_updated_html();
}
add_filter( 'render_block_post-kinds-indieweb/quote-entry', __NAMESPACE__ . '\\entry_paper', 10, 3 );

/**
 * Whether the plugin would show this quote's text to the current visitor.
 *
 * @param \WP_Post $post Post.
 * @return bool
 */
function can_show( \WP_Post $post ): bool {
	if ( post_password_required( $post ) ) {
		return false;
	}
	if ( class_exists( '\\PKIW\\Kind_Facts' ) ) {
		return \PKIW\Kind_Facts::can_show( $post );
	}
	return function_exists( 'is_post_publicly_viewable' ) && is_post_publicly_viewable( $post );
}

/**
 * Whether the single sets this quote in handwriting (body.cr-hand-short).
 *
 * @param \WP_Post $post Post.
 * @return bool
 */
function hand_short( \WP_Post $post ): bool {
	if ( function_exists( '\\Courtneyr\\Child\\AsideScrap\\applies' ) && \Courtneyr\Child\AsideScrap\applies( $post ) ) {
		return false;
	}
	$limit = defined( 'Courtneyr\\Child\\Enqueue\\HAND_MAX_WORDS' ) ? (int) constant( 'Courtneyr\\Child\\Enqueue\\HAND_MAX_WORDS' ) : 120;
	return str_word_count( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ), true ) ) <= $limit;
}

/**
 * Take links and tab stops out of quote markup: the card's one link is its overlay.
 *
 * An anchor without href is not a link and takes no tab stop; it keeps the
 * single's underline, because the selectors still match an a element.
 *
 * @param string $html Quote or citation markup.
 * @return string
 */
function without_tab_stops( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$tags->remove_attribute( 'tabindex' );
		if ( 'A' === $tags->get_tag() ) {
			foreach ( array( 'href', 'target', 'rel', 'ping', 'download', 'hreflang', 'type', 'referrerpolicy' ) as $name ) {
				$tags->remove_attribute( $name );
			}
		}
	}
	return $tags->get_updated_html();
}

/**
 * The single's notecard on one quote-book entry.
 *
 * The plugin card keeps its root (the h-entry, now the pinned artifact), its
 * overlay link, its hidden dt-published, author and h-cite. Its plain-text
 * excerpt gives way to the post's own quote block as the single renders it
 * (highlights, emphasis, the paper style), and the block's citation moves
 * out of the blockquote into the plugin's cite slot, so a long quote can be
 * clamped without hiding the cite. Links inside the quote lose their href.
 * A protected, private-to-this-visitor, continued or quote-less entry keeps
 * the plugin card.
 *
 * @param string               $html     Rendered quote entry.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance with Query Loop context.
 * @return string
 */
function entry_notecard( string $html, array $block, $instance ): string {
	if ( '' === $html || ! class_exists( '\\WP_HTML_Tag_Processor' ) || ! function_exists( '\\Courtneyr\\Child\\QuoteNote\\quote_block_html' ) ) {
		return $html;
	}
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	$tokens     = explode( ' ', str_replace( array( "\t", "\n", "\r", "\f" ), ' ', $class_name ) );
	if ( ! in_array( 'is-style-' . STYLE_SLUG, $tokens, true ) ) {
		return $html;
	}

	$probe = new \WP_HTML_Tag_Processor( $html );
	if (
		! $probe->next_tag(
			array(
				'tag_name'   => 'div',
				'class_name' => 'pkiw-quote-entry',
			)
		)
		|| $probe->has_class( 'pkiw-quote-entry--continued' )
		|| $probe->has_class( 'pkiw-quote-entry--protected' )
	) {
		return $html;
	}

	$post_id = $instance instanceof \WP_Block && ! empty( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = get_post( $post_id );
	if ( ! $post instanceof \WP_Post || ! can_show( $post ) ) {
		return $html;
	}

	// The plugin's ids and destination.
	$text_id = 'pkiw-quote-' . $post->ID . '-text';
	$cite_id = 'pkiw-quote-' . $post->ID . '-cite';
	$href    = '';
	$parts   = new \WP_HTML_Tag_Processor( $html );
	while ( $parts->next_tag() ) {
		$id = $parts->get_attribute( 'id' );
		if ( $parts->has_class( 'pkiw-quote-entry__text' ) && is_string( $id ) && '' !== $id ) {
			$text_id = $id;
		} elseif ( $parts->has_class( 'pkiw-quote-entry__cite' ) && is_string( $id ) && '' !== $id ) {
			$cite_id = $id;
		} elseif ( $parts->has_class( 'pkiw-quote-entry__link' ) ) {
			$href = (string) $parts->get_attribute( 'href' );
		}
	}
	$first     = strpos( $html, '<blockquote' );
	$link_at   = strpos( $html, 'pkiw-quote-entry__link' );
	$link_end  = false !== $link_at ? strpos( $html, '</a>', $link_at ) : false;
	$entry_end = strrpos( $html, '</div>' );
	if ( '' === $href || false === $first || false === $link_end || false === $entry_end || $entry_end < $link_end ) {
		return $html;
	}

	// The post's quote, as the single prints it: the blockquote, not a pullquote's figure.
	$rendered = \Courtneyr\Child\QuoteNote\quote_block_html( $post );
	$open     = stripos( $rendered, '<blockquote' );
	$close    = strripos( $rendered, '</blockquote>' );
	if ( false === $open || false === $close || $close < $open ) {
		return $html;
	}
	$quote = substr( $rendered, $open, $close + 13 - $open );

	// The block's citation, when it is the quote's last child, moves to the cite slot.
	$cite    = '';
	$c_open  = strripos( $quote, '<cite' );
	$c_close = strripos( $quote, '</cite>' );
	$c_gt    = false !== $c_open ? strpos( $quote, '>', $c_open ) : false;
	if ( false !== $c_gt && false !== $c_close && $c_gt < $c_close && '' === trim( substr( $quote, $c_close + 7, -13 ) ) ) {
		$cite  = trim( substr( $quote, $c_gt + 1, $c_close - $c_gt - 1 ) );
		$quote = substr( $quote, 0, (int) $c_open ) . substr( $quote, $c_close + 7 );
	}
	if ( '' === trim( wp_strip_all_tags( $cite ) ) ) {
		// No block citation: the plugin's own (escaped) cite text, if any.
		$p_cite = strpos( $html, 'pkiw-quote-entry__cite' );
		$p_gt   = false !== $p_cite ? strpos( $html, '>', $p_cite ) : false;
		$p_end  = false !== $p_gt ? strpos( $html, '</cite>', $p_gt ) : false;
		$cite   = false !== $p_end ? trim( substr( $html, $p_gt + 1, $p_end - $p_gt - 1 ) ) : '';
	}
	$has_cite = '' !== trim( wp_strip_all_tags( $cite ) );

	$tags = new \WP_HTML_Tag_Processor( without_tab_stops( $quote ) );
	if ( ! $tags->next_tag( 'blockquote' ) ) {
		return $html;
	}
	$tags->add_class( 'pkiw-quote-entry__text' );
	$tags->add_class( 'e-content' );
	$tags->set_attribute( 'id', $text_id );
	$quote = $tags->get_updated_html();

	// The artifact: the entry root takes the single's pin colours and tilt.
	$note  = \Courtneyr\Child\QuoteNote\note_style( \Courtneyr\Child\Stamps\seed( (string) $post->ID, $post->post_title ) );
	$entry = new \WP_HTML_Tag_Processor( substr( $html, 0, $first ) );
	if (
		! $entry->next_tag(
			array(
				'tag_name'   => 'div',
				'class_name' => 'pkiw-quote-entry',
			)
		)
	) {
		return $html;
	}
	$entry->add_class( 'cr-note-artifact' );
	$entry->add_class( 'cr-note-artifact--book' );
	if ( hand_short( $post ) ) {
		$entry->add_class( 'cr-hand-short' );
	}
	$style = rtrim( trim( (string) $entry->get_attribute( 'style' ) ), ';' );
	$entry->set_attribute( 'style', ( '' !== $style ? $style . ';' : '' ) . substr( $note, 7, -1 ) );

	$card  = $entry->get_updated_html();
	$card .= \Courtneyr\Child\QuoteNote\pin_svg();
	$card .= '<div class="cr-note cr-note--book">' . \Courtneyr\Child\QuoteNote\mark() . $quote;
	if ( $has_cite ) {
		$card .= '<cite class="pkiw-quote-entry__cite" id="' . esc_attr( $cite_id ) . '">' . without_tab_stops( $cite ) . '</cite>';
	}
	$card .= '<a class="u-url pkiw-quote-entry__link" href="' . esc_url( $href ) . '" aria-labelledby="' . esc_attr( $text_id . ( $has_cite ? ' ' . $cite_id : '' ) ) . '"></a>';
	$card .= substr( $html, $link_end + 4, $entry_end - $link_end - 4 );
	return $card . '</div></div>';
}
add_filter( 'render_block_post-kinds-indieweb/quote-entry', __NAMESPACE__ . '\\entry_notecard', 11, 3 );

/**
 * Return the decorative strip selected by a stored-value slug.
 *
 * @param string $slug Stored group or work slug.
 * @return string
 */
function strip_for( string $slug ): string {
	if ( '' === $slug ) {
		return EMPTY_STRIP;
	}

	$index = ( crc32( $slug ) & 0xffffffff ) % count( STRIPS );
	return STRIPS[ $index ];
}

/**
 * Add deterministic strip colours to plugin section and work wrappers.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function strip_seeds( string $html, array $block ): string {
	if ( '' === $html || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$block_name = (string) ( $block['blockName'] ?? '' );
	if ( 'core/post-template' === $block_name ) {
		$probe = new \WP_HTML_Tag_Processor( $html );
		if (
			! $probe->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'pkiw-quote--sectioned',
				)
			)
		) {
			return $html;
		}

		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'section' ) ) {
			$slug = $tags->get_attribute( 'data-pkiw-group' );
			if ( null === $slug ) {
				continue;
			}
			append_property( $tags, '--cr-strip', strip_for( (string) $slug ) );
		}
		return $tags->get_updated_html();
	}

	if ( 'post-kinds-indieweb/quote-works' === $block_name ) {
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'li' ) ) {
			$slug = $tags->get_attribute( 'data-pkiw-work' );
			if ( null === $slug ) {
				continue;
			}
			append_property( $tags, '--cr-strip', strip_for( (string) $slug ) );
		}
		return $tags->get_updated_html();
	}

	return $html;
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\strip_seeds', 10, 2 );
add_filter( 'render_block_post-kinds-indieweb/quote-works', __NAMESPACE__ . '\\strip_seeds', 10, 2 );

/** Load the book paint on the quote archive and in the editor. */
function enqueue_styles(): void {
	if ( ! is_admin() && ! \is_tax( 'kind', KIND ) ) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-quote-book.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles', 10, 1 );

/**
 * Keep the theme and plugin quote sheets out of Remove Unused CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return array<int, mixed>
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-quote-book.css';
	$exclusions[] = 'quote-archive.css';
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20, 1 );
