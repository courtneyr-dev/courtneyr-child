<?php
/**
 * Single acquisition posts: a paper receipt beside a taped photo (PKIW issue 239).
 *
 * The plugin's acquisition card prints one article: a badge, a kind label, the
 * item as a heading, a line with the cost and place, a picture, a note and the
 * acquired day. This file replaces the first card in the post content with a
 * receipt built from the plugin's facts only (\PKIW\kind_facts()), never from
 * block attributes or post meta, so the plugin's cost rule is the only one.
 *
 * The article keeps its h-cite u-acquisition-of root. The post title is the
 * page's one h1 and sits inside the receipt; the card's own name stays as a
 * hidden p-name. Later cards in the same post print as the plugin's card.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleAcquisition;

use function Courtneyr\Child\Acquisition\facts;
use function Courtneyr\Child\Acquisition\image_html;
use function Courtneyr\Child\Acquisition\local_picture;
use function Courtneyr\Child\Acquisition\name_of;
use function Courtneyr\Child\Acquisition\plain_text;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND = 'acquisition';
const ICON = '<svg class="cr-acq-single__icon" aria-hidden="true" focusable="false" viewBox="0 0 16 16" width="12" height="12"><path d="M9 2h5v5M14 2 7 9M12 10v4H2V4h4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';

/**
 * The reader's facts for a post that prints a receipt.
 *
 * @param \WP_Post $post Post.
 * @return array<string, mixed> Facts, or [] when the post has no card or may not show it.
 */
function receipt_facts( \WP_Post $post ): array {
	if ( ! has_term( KIND, 'kind', $post ) || post_password_required( $post ) ) {
		return array();
	}
	$facts = facts( $post->ID );
	return ! empty( $facts['has_card'] ) ? $facts : array();
}

/**
 * Whether the queried post prints a receipt.
 *
 * @return bool
 */
function is_receipt(): bool {
	if ( ! is_singular( 'post' ) ) {
		return false;
	}
	$post = get_post( get_queried_object_id() );
	return $post instanceof \WP_Post && array() !== receipt_facts( $post );
}

/**
 * Tell the shared single-object filter that a receipt owns the page H1.
 *
 * @param mixed    $holds Whether another lane already owns the H1.
 * @param \WP_Post $post  Post being rendered.
 * @return bool
 */
function holds_h1( $holds, \WP_Post $post ): bool {
	return true === $holds || array() !== receipt_facts( $post );
}
add_filter( 'courtneyr_child_single_object_holds_h1', __NAMESPACE__ . '\\holds_h1', 10, 2 );

/**
 * One definition-list row.
 *
 * @param string $key  Row key.
 * @param string $label Row label.
 * @param string $html  Value HTML, already escaped.
 * @return string
 */
function fact_row( string $key, string $label, string $html ): string {
	return '<div class="cr-ticket__fact cr-acq-single__fact cr-acq-single__fact--' . esc_attr( $key ) . '"><dt>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd></div>';
}

/**
 * The From row's value: the place, linked when the card stores its URL.
 *
 * @param array<string, mixed> $facts Acquisition facts.
 * @return string Value HTML, or '' when the card names no place.
 */
function where_html( array $facts ): string {
	$where = plain_text( (string) ( $facts['where'] ?? '' ) );
	$url   = (string) ( $facts['where_url'] ?? '' );
	if ( '' === $url ) {
		return '' === $where ? '' : '<span class="p-location">' . esc_html( $where ) . '</span>';
	}

	$shown = '' !== $where ? $where : (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( '' === $shown ) {
		return '';
	}
	$aria = function_exists( '\\PKIW\\pkiw_new_tab_label_attr' )
		? (string) \PKIW\pkiw_new_tab_label_attr( $shown )
		: ' aria-label="' . esc_attr( $shown . ' ' . __( '(opens in a new tab)', 'courtneyr-child' ) ) . '"';
	$text = '' !== $where ? '<span class="p-location">' . esc_html( $shown ) . '</span>' : esc_html( $shown );
	return '<a class="u-url cr-acq-single__link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"' . $aria . '>' . $text . ICON . '</a>';
}

/**
 * The taped photo, when the card has a picture this site may print.
 *
 * @param array<string, mixed> $facts Acquisition facts.
 * @return string Figure HTML, or ''.
 */
function photo_html( array $facts ): string {
	$picture = local_picture( $facts );
	if ( array() === $picture ) {
		return '';
	}
	$img = image_html(
		$picture,
		array(
			'class' => 'cr-acq-single__img u-photo',
			'alt'   => (string) ( $picture['alt'] ?? '' ),
			'eager' => true,
			'size'  => 'large',
			'sizes' => '(min-width: 64rem) 40rem, 100vw',
		)
	);
	if ( '' === $img ) {
		return '';
	}
	return '<figure class="cr-acq-single__photo" data-cr-cover-fallback><span class="cr-acq-single__tape" aria-hidden="true"></span>' . $img . '</figure>';
}

/**
 * The inside of the receipt article, set out from the reader's facts.
 *
 * @param \WP_Post             $post  Post.
 * @param array<string, mixed> $facts Acquisition facts.
 * @return string
 */
function receipt_html( \WP_Post $post, array $facts ): string {
	$name  = name_of( $post, $facts );
	$title = plain_text( (string) ( $facts['title'] ?? '' ) );

	$rows = '';
	if ( '' !== (string) ( $facts['type_label'] ?? '' ) ) {
		$rows .= fact_row( 'type', __( 'Acquisition', 'courtneyr-child' ), esc_html( (string) $facts['type_label'] ) );
	}
	$where = where_html( $facts );
	if ( '' !== $where ) {
		$rows .= fact_row( 'from', __( 'From', 'courtneyr-child' ), $where );
	}
	if ( '' !== (string) ( $facts['acquired_at'] ?? '' ) ) {
		$rows .= fact_row( 'acquired', __( 'Acquired', 'courtneyr-child' ), '<time class="dt-published" datetime="' . esc_attr( (string) $facts['acquired_at'] ) . '">' . esc_html( (string) ( $facts['acquired_display'] ?? '' ) ) . '</time>' );
	}
	if ( '' !== (string) ( $facts['cost'] ?? '' ) ) {
		$rows .= fact_row( 'cost', __( 'Cost', 'courtneyr-child' ), esc_html( (string) $facts['cost'] ) );
	}

	$out = '';
	if ( '' !== $title ) {
		$out .= '<data class="p-name" value="' . esc_attr( $title ) . '" hidden></data>';
	}
	$out .= photo_html( $facts );
	$out .= '<div class="cr-acq-single__receipt cr-ticket cr-ticket--zigzag">';
	$out .= '<h1 class="cr-acq-single__title">' . esc_html( $name ) . '</h1>';
	if ( '' !== $rows ) {
		$out .= '<dl class="cr-acq-single__facts cr-ticket__facts">' . $rows . '</dl>';
	}
	$out .= '</div>';

	// The reader returns plain text with newlines, so the markup only escapes it and keeps the line breaks.
	$notes = trim( (string) ( $facts['notes'] ?? '' ) );
	if ( '' !== $notes ) {
		$out .= '<div class="cr-acq-single__notes"><h2 class="cr-acq-single__notes-title">' . esc_html__( 'Notes', 'courtneyr-child' ) . '</h2><p class="p-content">' . nl2br( esc_html( $notes ) ) . '</p></div>';
	}

	return $out;
}

/**
 * Replace the first acquisition card with the receipt.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function receipt( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_singular( 'post' ) ) {
		return $html;
	}
	$post = get_post( get_queried_object_id() );
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$facts = receipt_facts( $post );
	if ( array() === $facts ) {
		return $html;
	}

	$at    = strpos( $html, 'pk-card k-' . KIND );
	$open  = false !== $at ? strrpos( substr( $html, 0, $at ), '<article' ) : false;
	$close = false !== $open ? strpos( $html, '</article>', $open ) : false;
	$end   = false !== $open ? strpos( $html, '>', $open ) : false;
	if ( false === $close || false === $end ) {
		return $html;
	}

	// The article stays the h-cite root; it is the receipt now, not a card.
	$tags = new \WP_HTML_Tag_Processor( substr( $html, $open, $end + 1 - $open ) );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	$tags->remove_class( 'pk-card' );
	$tags->add_class( 'cr-acq-single' );

	return substr( $html, 0, $open ) . $tags->get_updated_html() . receipt_html( $post, $facts ) . '</article>' . substr( $html, $close + 10 );
}
add_filter( 'render_block', __NAMESPACE__ . '\\receipt', 20, 2 );

/**
 * Mark the page for the stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_receipt() && ! in_array( 'cr-acq-single-page', $classes, true ) ) {
		$classes[] = 'cr-acq-single-page';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );
