<?php
/**
 * Jam single J-card adapter for issue 235.
 *
 * The plugin owns the citation fragments and facts. This adapter arranges
 * those fragments as an insert, details, decorative spine and optional note.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleJam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mark jam singles for their stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if (
		function_exists( '\\Courtneyr\\Child\\Jam\\is_jam_single' )
		&& \Courtneyr\Child\Jam\is_jam_single()
		&& ! in_array( 'cr-jam-single', $classes, true )
	) {
		$classes[] = 'cr-jam-single';
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Let the shared featured-image filter inspect jam posts.
 *
 * @param mixed $kinds Kind slugs registered so far.
 * @return mixed
 */
function repeat_kinds( $kinds ) {
	if ( ! is_array( $kinds ) ) {
		return $kinds;
	}
	if ( ! in_array( 'jam', $kinds, true ) ) {
		$kinds[] = 'jam';
	}

	return $kinds;
}
add_filter( 'courtneyr_child_featured_repeat_kinds', __NAMESPACE__ . '\\repeat_kinds' );

/**
 * Suppress a featured image only when the plugin says the J-card repeats it.
 *
 * @param mixed $repeats Existing repeat decision.
 * @param mixed $post    Post under inspection.
 * @return mixed
 */
function featured_repeats( $repeats, $post ) {
	if (
		! $post instanceof \WP_Post
		|| ! has_term( 'jam', 'kind', $post )
		|| ! function_exists( '\\Courtneyr\\Child\\Jam\\facts' )
	) {
		return $repeats;
	}

	$facts   = \Courtneyr\Child\Jam\facts( (int) $post->ID );
	$picture = is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array();
	if ( array() !== $facts && ! empty( $facts['has_card'] ) && ! empty( $picture['suppress_featured'] ) ) {
		return true;
	}

	return $repeats;
}
add_filter( 'courtneyr_child_featured_repeats', __NAMESPACE__ . '\\featured_repeats', 10, 2 );

/**
 * Extract one balanced element with a class token, preserving its bytes.
 *
 * @param string $html       HTML fragment.
 * @param string $tag        Element tag.
 * @param string $class_name Required class token.
 * @return string
 */
function element_fragment( string $html, string $tag, string $class_name ): string {
	$tag_pattern   = preg_quote( $tag, '/' );
	$class_pattern = preg_quote( $class_name, '/' );
	$opening       = '' === $class_name
		? '/<' . $tag_pattern . '\\b[^>]*>/i'
		: '/<' . $tag_pattern . '\\b[^>]*\\bclass=(?:"[^"]*(?<![\\w-])' . $class_pattern . '(?![\\w-])[^"]*"|\'[^\']*(?<![\\w-])' . $class_pattern . '(?![\\w-])[^\']*\')[^>]*>/i';
	if ( 1 !== preg_match( $opening, $html, $found, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}

	$start  = (int) $found[0][1];
	$offset = $start + strlen( $found[0][0] );
	$depth  = 1;
	$tokens = '/<\/?' . $tag_pattern . '\\b[^>]*>/i';
	while ( $depth > 0 && preg_match( $tokens, $html, $token, PREG_OFFSET_CAPTURE, $offset ) ) {
		$markup = $token[0][0];
		$at     = (int) $token[0][1];
		$offset = $at + strlen( $markup );
		if ( str_starts_with( $markup, '</' ) ) {
			--$depth;
		} elseif ( ! str_ends_with( rtrim( $markup ), '/>' ) ) {
			++$depth;
		}
	}

	return 0 === $depth ? substr( $html, $start, $offset - $start ) : '';
}

/**
 * Inner markup from a complete element.
 *
 * @param string $fragment Complete element.
 * @param string $tag      Element tag.
 * @return string
 */
function element_inner( string $fragment, string $tag ): string {
	if ( '' === $fragment ) {
		return '';
	}
	$start = strpos( $fragment, '>' );
	$end   = strripos( $fragment, '</' . $tag . '>' );
	if ( false === $start || false === $end || $end <= $start ) {
		return '';
	}

	return substr( $fragment, $start + 1, $end - $start - 1 );
}

/**
 * Add the J-card class and return the root's opening article tag.
 *
 * @param string $html Plugin card HTML.
 * @return string
 */
function article_opening( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return '';
	}
	$tags->add_class( 'cr-jcard' );
	$updated = $tags->get_updated_html();

	return 1 === preg_match( '/^\s*(<article\b[^>]*>)/i', $updated, $opening ) ? $opening[1] : '';
}

/**
 * The cover insert from public plugin facts.
 *
 * @param array<string, mixed> $facts Jam facts.
 * @return string
 */
function cover_panel( array $facts ): string {
	$picture = is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array();
	$url     = is_scalar( $picture['url'] ?? null ) ? trim( (string) $picture['url'] ) : '';
	if ( '' === $url ) {
		return '';
	}

	// Card alt first, then the "Title by Artist" fallback, and the picture's own alt only after both.
	$alt = is_scalar( $facts['cover_alt'] ?? null ) ? trim( (string) $facts['cover_alt'] ) : '';
	if ( '' === $alt ) {
		$alt = is_scalar( $facts['cover_alt_fallback'] ?? null ) ? trim( (string) $facts['cover_alt_fallback'] ) : '';
	}
	if ( '' === $alt ) {
		$alt = is_scalar( $picture['alt'] ?? null ) ? trim( (string) $picture['alt'] ) : '';
	}

	$image         = '';
	$attachment_id = (int) ( $picture['attachment_id'] ?? 0 );
	if ( $attachment_id > 0 ) {
		$image = wp_get_attachment_image(
			$attachment_id,
			'large',
			false,
			array(
				'class'         => 'u-photo',
				'alt'           => $alt,
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
	}
	if ( '' === $image ) {
		$image = '<img class="u-photo" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="eager" fetchpriority="high" decoding="async">';
	}

	return '<div class="cr-jcard__insert cr-paper-sheet cr-paper-sheet--torn cr-paper--printer-ivory" data-cr-cover-fallback="true">' . $image . '</div>';
}

/**
 * One labelled details row.
 *
 * @param string $label Label text.
 * @param string $value Preserved plugin fragment or escaped theme markup.
 * @param string $modifier Optional modifier class.
 * @return string
 */
function detail_row( string $label, string $value, string $modifier = '' ): string {
	$classes = 'cr-jcard__row' . ( '' !== $modifier ? ' ' . $modifier : '' );

	return '<div class="' . esc_attr( $classes ) . '"><dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd></div>';
}

/**
 * Rebuild a plugin jam card as the four-panel J-card.
 *
 * @param string               $html     Rendered jam-card block.
 * @param array<string, mixed> $block    Parsed block, unused by this adapter.
 * @param mixed                $instance Block instance with the rendered post ID.
 * @return string
 */
function jcard( string $html, array $block = array(), $instance = null ): string {
	unset( $block );

	if ( '' === $html || ! is_singular( 'post' ) ) {
		return $html;
	}

	$queried_id = (int) get_queried_object_id();
	$post_id    = is_object( $instance ) && isset( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	if (
		$post_id <= 0
		|| $post_id !== $queried_id
		|| ! has_term( 'jam', 'kind', $queried_id )
		|| post_password_required( $post_id )
		|| ! function_exists( '\\Courtneyr\\Child\\Jam\\facts' )
		|| ( function_exists( '\\PKIW\\is_rendering_stream_card' ) && \PKIW\is_rendering_stream_card() )
		|| ! str_contains( $html, '<article' )
	) {
		return $html;
	}

	$facts = \Courtneyr\Child\Jam\facts( $post_id );
	if ( array() === $facts || empty( $facts['has_card'] ) ) {
		return $html;
	}

	$opening = article_opening( $html );
	if ( '' === $opening ) {
		return $html;
	}

	$title  = element_fragment( $html, 'h2', 'pk-title' );
	$artist = element_fragment( $html, 'span', 'p-author' );
	$album  = element_fragment( $html, 'em', '' );
	$note   = element_fragment( $html, 'p', 'pk-note' );
	$link   = element_fragment( $html, 'a', 'pk-link' );
	$audio  = element_fragment( $html, 'div', 'pk-embed--audio' );
	$rows   = '';
	$track  = is_scalar( $facts['title'] ?? null ) ? trim( (string) $facts['title'] ) : '';
	if ( '' !== $track && empty( $facts['track_is_h1'] ) && '' !== $title ) {
		$title = (string) preg_replace( '/^<h2\b/i', '<p', $title, 1 );
		$title = (string) preg_replace( '/<\/h2>$/i', '</p>', $title, 1 );
		$rows .= detail_row( esc_html__( 'Track', 'courtneyr-child' ), $title, 'cr-jcard__row--track' );
	}
	$artist_name = is_scalar( $facts['artist'] ?? null ) ? trim( (string) $facts['artist'] ) : '';
	if ( '' !== $artist_name && '' !== $artist ) {
		$rows .= detail_row( esc_html__( 'Artist', 'courtneyr-child' ), $artist );
	}
	$album_name = is_scalar( $facts['album'] ?? null ) ? trim( (string) $facts['album'] ) : '';
	if ( '' !== $album_name && '' !== $album ) {
		$rows .= detail_row( esc_html__( 'Album', 'courtneyr-child' ), $album );
	}
	$date         = is_scalar( $facts['date'] ?? null ) ? trim( (string) $facts['date'] ) : '';
	$date_display = is_scalar( $facts['date_display'] ?? null ) ? trim( (string) $facts['date_display'] ) : '';
	if ( '' !== $date && '' !== $date_display ) {
		$date_class = ! empty( $facts['date_is_stored'] ) ? ' class="dt-published"' : '';
		$rows      .= detail_row(
			esc_html__( 'Date', 'courtneyr-child' ),
			'<time' . $date_class . ' datetime="' . esc_attr( $date ) . '">' . esc_html( $date_display ) . '</time>'
		);
	}

	$hidden = '';
	if ( '' === $track || ! empty( $facts['track_is_h1'] ) ) {
		// The cite's name is the stored track, or the post's real title when no track is stored.
		// A synthetic "Jam, <date>" name is never a p-name.
		$name_source = is_scalar( $facts['name_source'] ?? null ) ? (string) $facts['name_source'] : '';
		$cite_name   = '' !== $track ? $track : ( is_scalar( $facts['name'] ?? null ) ? trim( (string) $facts['name'] ) : '' );
		if ( '' !== $cite_name && 'untitled' !== $name_source ) {
			$hidden .= '<data class="p-name" value="' . esc_attr( $cite_name ) . '" hidden></data>';
		}
		$url = is_scalar( $facts['url'] ?? null ) ? trim( (string) $facts['url'] ) : '';
		if ( '' !== $url ) {
			$hidden .= '<data class="u-url u-jam-of" value="' . esc_url( $url ) . '" hidden></data>';
		}
	}

	$details    = '<div class="cr-jcard__details cr-paper-sheet cr-paper-sheet--torn cr-paper--printer-ivory">';
	$details   .= '' !== $rows ? '<dl class="cr-jcard__rows">' . $rows . '</dl>' : '';
	$details   .= $hidden . $link . '</div>';
	$spine      = '<div class="cr-jcard__spine" aria-hidden="true"></div>';
	$note_panel = '';
	$note_text  = is_scalar( $facts['note'] ?? null ) ? trim( (string) $facts['note'] ) : '';
	if ( '' !== $note_text && '' !== $note ) {
		$note_panel = '<div class="cr-jcard__note cr-paper-sheet cr-paper-sheet--torn cr-paper--printer-ivory"><span class="cr-jcard__label">'
			. esc_html__( 'Note', 'courtneyr-child' ) . '</span>' . $note . '</div>';
	}

	$out = $opening . cover_panel( $facts ) . $details . $spine . $note_panel . '</article>';

	return $out . $audio;
}
add_filter( 'render_block_post-kinds-indieweb/jam-card', __NAMESPACE__ . '\\jcard', 20, 3 );
