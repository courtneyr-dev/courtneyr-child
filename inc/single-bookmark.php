<?php
/**
 * Single bookmark index card.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleBookmark;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize visible text for comparison.
 *
 * @param string $text Text or HTML.
 * @return string Normalized text.
 */
function comparable_text( string $text ): string {
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}

/**
 * Bookmark facts for the queried single.
 *
 * The answer is kept per post for the request, so a paragraph filter doesn't
 * rebuild the facts for every paragraph. The content flag passes `$refresh` to
 * start from the current post.
 *
 * @param bool $refresh Whether to rebuild the kept answer.
 * @return array<string, mixed>
 */
function single_facts( bool $refresh = false ): array {
	static $kept = array();

	if ( ! \is_singular( 'post' ) ) {
		return array();
	}
	$post_id = (int) get_queried_object_id();
	if ( $refresh ) {
		unset( $kept[ $post_id ] );
	}
	if ( isset( $kept[ $post_id ] ) ) {
		return $kept[ $post_id ];
	}

	$facts = array();
	if ( $post_id > 0 && has_term( 'bookmark', 'kind', $post_id ) && ! post_password_required() && function_exists( '\\PKIW\\kind_facts' ) ) {
		$facts = \PKIW\kind_facts( $post_id );
		$facts = is_array( $facts ) ? $facts : array();
	}
	$kept[ $post_id ] = $facts;
	return $facts;
}

/**
 * Static flag for the main post-content block render.
 *
 * @param bool|null $set Optional new state.
 * @return bool Current state.
 */
function in_content( ?bool $set = null ): bool {
	static $inside = false;
	if ( null !== $set ) {
		$inside = $set;
	}
	return $inside;
}

/**
 * Mark the start of the main post-content render.
 *
 * @param mixed                $pre          Existing pre-render value.
 * @param array<string, mixed> $parsed_block Parsed block.
 * @return mixed Existing pre-render value.
 */
function set_content_flag( $pre, array $parsed_block ) {
	if ( 'core/post-content' === ( $parsed_block['blockName'] ?? '' ) && array() !== single_facts( true ) ) {
		in_content( true );
	}
	return $pre;
}
add_filter( 'pre_render_block', __NAMESPACE__ . '\\set_content_flag', 10, 2 );

/**
 * Drop the plugin bookmark card inside the rebuilt content area.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string Rendered block HTML.
 */
function drop_card_block( string $html, array $block ): string {
	unset( $block );
	return in_content() ? '' : $html;
}
add_filter( 'render_block_post-kinds-indieweb/bookmark-card', __NAMESPACE__ . '\\drop_card_block', 10, 2 );

/**
 * Drop a paragraph that duplicates the card note.
 *
 * @param string               $html  Rendered paragraph.
 * @param array<string, mixed> $block Parsed block.
 * @return string Rendered paragraph.
 */
function drop_duplicate_note( string $html, array $block ): string {
	unset( $block );
	if ( ! in_content() ) {
		return $html;
	}
	$note = comparable_text( (string) ( single_facts()['note'] ?? '' ) );
	if ( '' === $note ) {
		return $html;
	}
	$text = comparable_text( $html );
	return '' !== $text && $text === $note ? '' : $html;
}
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\drop_duplicate_note', 10, 2 );

/**
 * Drop the template featured image on visible bookmark singles.
 *
 * @param string               $html  Rendered featured image.
 * @param array<string, mixed> $block Parsed block.
 * @return string Rendered featured image.
 */
function drop_featured_image( string $html, array $block ): string {
	if ( 'core/post-featured-image' === ( $block['blockName'] ?? '' ) && array() !== single_facts() ) {
		return '';
	}
	return $html;
}
add_filter( 'render_block_core/post-featured-image', __NAMESPACE__ . '\\drop_featured_image', 10, 2 );

/**
 * Prepend the index card to the main post content.
 *
 * @param string               $html  Rendered post content.
 * @param array<string, mixed> $block Parsed block.
 * @return string Rendered post content.
 */
function prepend_card( string $html, array $block ): string {
	in_content( false );
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	$facts = single_facts();
	$post  = get_post( get_queried_object_id() );
	if ( array() === $facts || ! $post instanceof \WP_Post ) {
		return $html;
	}
	return index_card( $post, $facts ) . $html;
}
add_filter( 'render_block_core/post-content', __NAMESPACE__ . '\\prepend_card', 10, 2 );

/**
 * Safe rel tokens for the source link.
 *
 * @param string $stored Stored rel string.
 * @return string Rel tokens.
 */
function rel_tokens( string $stored ): string {
	$out    = array( 'noopener', 'noreferrer' );
	$tokens = preg_split( '/\s+/', strtolower( $stored ) );
	foreach ( false === $tokens ? array() : $tokens as $token ) {
		if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $token ) || in_array( $token, $out, true ) ) {
			continue;
		}
		$out[] = $token;
	}
	return implode( ' ', $out );
}

/**
 * Picture markup for the single card.
 *
 * @param array<string, mixed> $picture Picture facts.
 * @return string Figure HTML, or empty.
 */
function picture_html( array $picture ): string {
	$url = (string) ( $picture['url'] ?? '' );
	if ( '' === $url ) {
		return '';
	}

	$alt   = (string) ( $picture['alt'] ?? '' );
	$attrs = array(
		'class'    => 'cr-index-card__image',
		'alt'      => $alt,
		'decoding' => 'async',
		'loading'  => 'lazy',
	);

	$img = '';
	if ( (int) ( $picture['attachment_id'] ?? 0 ) > 0 ) {
		$img = (string) wp_get_attachment_image( (int) $picture['attachment_id'], 'large', false, $attrs );
	}
	if ( '' === $img ) {
		$img = '<img class="cr-index-card__image" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
	}
	return '<figure class="cr-index-card__picture" data-cr-cover-fallback>' . $img . '</figure>';
}

/**
 * One definition-list row.
 *
 * @param string $key   Row key.
 * @param string $label Row label.
 * @param string $html     Row value HTML, already escaped.
 * @param string $dd_class Optional class for the value cell.
 * @return string Row markup.
 */
function row_html( string $key, string $label, string $html, string $dd_class = '' ): string {
	return '<div class="cr-index-card__row cr-index-card__row--' . esc_attr( $key ) . '"><dt>' . esc_html( $label ) . '</dt><dd' . ( '' !== $dd_class ? ' class="' . esc_attr( $dd_class ) . '"' : '' ) . '>' . $html . '</dd></div>';
}

/**
 * Build the single bookmark index card.
 *
 * @param \WP_Post             $post  Post object.
 * @param array<string, mixed> $facts Bookmark facts.
 * @return string Card markup.
 */
function index_card( \WP_Post $post, array $facts ): string {
	$label = function_exists( '\\PKIW\\get_kind_label' ) ? (string) \PKIW\get_kind_label( __( 'Bookmark', 'courtneyr-child' ), 'bookmark', 'stream-card' ) : __( 'Bookmark', 'courtneyr-child' );
	$glyph = function_exists( '\\PKIW\\get_kind_icon_svg' ) ? (string) \PKIW\get_kind_icon_svg( 'bookmark' ) : '';

	$drawer = is_array( $facts['drawer'] ?? null ) ? (string) ( $facts['drawer']['label'] ?? '' ) : '';
	$cited  = (string) ( $facts['cited_title'] ?? '' );
	$rows   = '';
	$url    = (string) ( $facts['url'] ?? '' );
	$host   = (string) ( $facts['host'] ?? '' );
	if ( '' === $host && '' !== $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	}
	if ( '' !== $url ) {
		$aria = ' aria-label="' . esc_attr( $host . __( ' (opens in a new tab)', 'courtneyr-child' ) ) . '"';
		if ( function_exists( '\\PKIW\\pkiw_new_tab_label_attr' ) ) {
			$aria = (string) \PKIW\pkiw_new_tab_label_attr( $host );
		}
		$rows .= row_html( 'source', __( 'Source', 'courtneyr-child' ), '<a href="' . esc_url( $url ) . '" target="_blank" rel="' . esc_attr( rel_tokens( (string) ( $facts['rel'] ?? '' ) ) ) . '"' . $aria . '>' . esc_html( $host ) . '</a>' );
	}
	if ( '' !== (string) ( $facts['author'] ?? '' ) ) {
		$rows .= row_html( 'author', __( 'Author', 'courtneyr-child' ), esc_html( (string) $facts['author'] ) );
	}
	$date = is_array( $facts['date'] ?? null ) ? $facts['date'] : array( '', '' );
	if ( '' !== (string) ( $date[0] ?? '' ) ) {
		$rows .= row_html( 'date', __( 'Date filed', 'courtneyr-child' ), '<time datetime="' . esc_attr( (string) $date[0] ) . '">' . esc_html( (string) ( $date[1] ?? '' ) ) . '</time>' );
	}
	if ( '' !== (string) ( $facts['note'] ?? '' ) ) {
		$rows .= row_html( 'note', __( 'Note', 'courtneyr-child' ), nl2br( esc_html( (string) $facts['note'] ) ), 'cr-index-card__note' );
	}

	$picture = is_array( $facts['picture'] ?? null ) ? picture_html( $facts['picture'] ) : '';
	$body    = '<div class="cr-index-card__body"><dl class="cr-index-card__fields">' . $rows . '</dl>' . $picture . '</div>';

	$html = '<div class="cr-bookmark-single">';
	if ( '' !== $drawer ) {
		$html .= '<p class="cr-bookmark-single__tab">' . esc_html( $drawer ) . '</p>';
	}
	$html .= '<article class="cr-index-card cr-index-card--single cr-paper-sheet cr-paper--printer-ivory">';
	$html .= '<p class="cr-index-card__label"><span class="cr-index-card__glyph" aria-hidden="true">' . $glyph . '</span><span class="cr-index-card__kind">' . esc_html( $label ) . '</span></p>';
	if ( '' !== $cited && comparable_text( $cited ) !== comparable_text( (string) $post->post_title ) ) {
		$html .= '<p class="cr-index-card__cited">' . esc_html( $cited ) . '</p>';
	}
	$html .= $body . '</article></div>';
	return $html;
}
