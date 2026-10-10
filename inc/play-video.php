<?php
/**
 * Video play objects: arcade cabinets, cartridges and single record sheets.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\PlayVideo;

use function Courtneyr\Child\HomeSections\is_stream_surface;
use function Courtneyr\Child\MediaShelf\cut_elements;
use function Courtneyr\Child\StreamMedia\find_block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Archive elements rebuilt as cabinet parts.
 */
const CUT_ARCHIVE = array(
	array( 'span', 'pk-kindlabel' ),
	array( 'div', 'pk-badge' ),
	array( 'p', 'pk-sub' ),
	array( 'p', 'pk-stream-date' ),
	array( 'div', 'pk-stars' ),
	array( 'div', 'pk-note' ),
	array( 'div', 'pk-meta' ),
	array( 'p', 'pk-excerpt' ),
	array( 'div', 'pk-media' ),
);

/**
 * Stream elements rebuilt as cartridge parts.
 */
const CUT_CARTRIDGE = array(
	array( 'div', 'pk-badge' ),
	array( 'p', 'pk-sub' ),
	array( 'div', 'pk-stars' ),
	array( 'div', 'pk-note' ),
	array( 'div', 'pk-meta' ),
	array( 'p', 'pk-excerpt' ),
	array( 'div', 'pk-media' ),
);

/**
 * The first full element span for a tag carrying a class.
 *
 * @param string $html       Fragment.
 * @param string $tag        Tag name.
 * @param string $class_name One class the element carries.
 * @return array{0: int, 1: int}|null Start and end offsets, or null.
 */
function element_span( string $html, string $tag, string $class_name ): ?array {
	$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
	if ( ! preg_match( $open, $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	$start = (int) $m[0][1];
	$pos   = $start + strlen( $m[0][0] );
	$depth = 1;
	while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
		$pos    = (int) $t[0][1] + strlen( $t[0][0] );
		$depth += '/' === $t[1][0] ? -1 : 1;
	}

	return $depth > 0 ? null : array( $start, $pos );
}

/**
 * Visible play facts from the plugin.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Picture data from the plugin.
 *
 * @param int $post_id Post ID.
 * @return array{source: string, attachment_id: int, url: string, alt: string, remote: bool, suppress_featured: bool}
 */
function picture( int $post_id ): array {
	$empty = array(
		'source'            => '',
		'attachment_id'     => 0,
		'url'               => '',
		'alt'               => '',
		'remote'            => false,
		'suppress_featured' => false,
	);
	if ( ! function_exists( '\\PKIW\\kind_picture' ) ) {
		return $empty;
	}
	$picture = \PKIW\kind_picture( $post_id );
	return is_array( $picture ) ? array_merge( $empty, $picture ) : $empty;
}

/**
 * Zero-based video position within the main query.
 *
 * @param int $post_id Post ID.
 * @return int Video rank, or -1.
 */
function video_rank( int $post_id ): int {
	static $cache = array();

	$query = $GLOBALS['wp_query'] ?? null;
	if ( ! is_object( $query ) || ! isset( $query->posts ) || ! is_array( $query->posts ) || ! function_exists( '\\PKIW\\play_group' ) ) {
		return -1;
	}

	$ids = array_map(
		static function ( $post ): int {
			return is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
		},
		$query->posts
	);
	$key = implode( ',', $ids );

	if ( ! isset( $cache[ $key ] ) ) {
		$rank = 0;
		$map  = array();
		foreach ( $ids as $id ) {
			if ( $id > 0 && 'video' === (string) \PKIW\play_group( $id ) ) {
				$map[ $id ] = $rank;
				++$rank;
			}
		}
		$cache[ $key ] = $map;
	}

	return isset( $cache[ $key ][ $post_id ] ) ? (int) $cache[ $key ][ $post_id ] : -1;
}

/**
 * Cover art container with a fallback glyph.
 *
 * @param string               $block   Object block class.
 * @param string               $part    Object part name.
 * @param array<string, mixed> $picture Picture data.
 * @param string               $alt     Image alt text.
 * @param string               $size    WordPress image size.
 * @param string               $loading Loading value.
 * @param bool                 $high    Whether to mark the image high priority.
 * @return string Cover markup.
 */
function art_html( string $block, string $part, array $picture, string $alt, string $size, string $loading, bool $high ): string {
	$glyph = function_exists( '\\PKIW\\get_kind_icon_svg' ) ? (string) \PKIW\get_kind_icon_svg( 'play' ) : '';
	$url   = esc_url( (string) ( $picture['url'] ?? '' ) );
	if ( '' === $url ) {
		return '<div class="' . esc_attr( $block . '__' . $part . ' ' . $block . '__' . $part . '--empty' ) . '"><span class="' . esc_attr( $block . '__glyph' ) . '" aria-hidden="true">' . $glyph . '</span></div>';
	}

	$attrs = array(
		'class'    => $block . '__image u-photo',
		'alt'      => $alt,
		'loading'  => $loading,
		'decoding' => 'async',
	);
	if ( $high ) {
		$attrs['fetchpriority'] = 'high';
	}

	$img = '';
	if ( (int) ( $picture['attachment_id'] ?? 0 ) > 0 ) {
		$img = (string) wp_get_attachment_image( (int) $picture['attachment_id'], $size, false, $attrs );
	}
	if ( '' === $img ) {
		$img = '<img class="' . esc_attr( $block . '__image u-photo' ) . '" src="' . $url . '" alt="' . esc_attr( $alt ) . '" loading="' . esc_attr( $loading ) . '" decoding="async"' . ( $high ? ' fetchpriority="high"' : '' ) . ' />';
	}

	return '<div class="' . esc_attr( $block . '__' . $part ) . '" data-cr-cover-fallback>' . $img . '<span class="' . esc_attr( $block . '__glyph' ) . '" aria-hidden="true">' . $glyph . '</span></div>';
}

/**
 * Arcade marquee state label.
 *
 * @param string $status Play status.
 * @return string Label, or empty.
 */
function arcade_label( string $status ): string {
	if ( in_array( $status, array( 'backlog', 'wishlist' ), true ) ) {
		return __( 'INSERT COIN', 'courtneyr-child' );
	}
	if ( 'playing' === $status ) {
		return __( 'CONTINUE', 'courtneyr-child' );
	}
	if ( in_array( $status, array( 'completed', 'abandoned' ), true ) ) {
		return __( 'GAME OVER', 'courtneyr-child' );
	}
	return '';
}

/**
 * Arcade controls.
 *
 * @return string Panel markup.
 */
function panel_html(): string {
	return '<span class="cr-cabinet__panel" aria-hidden="true"><span class="cr-cabinet__stick"></span><span class="cr-cabinet__button"></span><span class="cr-cabinet__button cr-cabinet__button--alt"></span><span class="cr-cabinet__coins"></span></span>';
}

/**
 * Provider uid data.
 *
 * @param array<string, mixed> $facts Play facts.
 * @return string Hidden uid markup.
 */
function uid_html( array $facts ): string {
	$out = '';
	foreach (
		array(
			'bgg_id'   => 'https://boardgamegeek.com/boardgame/',
			'rawg_id'  => 'https://rawg.io/games/',
			'steam_id' => 'https://store.steampowered.com/app/',
		) as $key => $base
	) {
		$id = trim( (string) ( $facts[ $key ] ?? '' ) );
		if ( '' === $id ) {
			continue;
		}
		$url = esc_url( $base . $id );
		if ( '' !== $url ) {
			$out .= '<data class="u-uid" value="' . $url . '" hidden></data>';
		}
	}
	return $out;
}

/**
 * Whether HTML carries a class token.
 *
 * @param string $html       Fragment.
 * @param string $class_name Class name.
 * @return bool True when present.
 */
function has_class_token( string $html, string $class_name ): bool {
	return (bool) preg_match( '/\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"/', $html );
}

/**
 * Collapse whitespace and lowercase text for title comparison.
 *
 * @param string $text Text.
 * @return string Normalized text.
 */
function comparable_name( string $text ): string {
	$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}

/**
 * One record-sheet fact row.
 *
 * @param string $key   Row key.
 * @param string $label Label.
 * @param string $html  Value HTML, already escaped.
 * @return string Fact row.
 */
function fact_row( string $key, string $label, string $html ): string {
	return '<div class="cr-cabinet__fact cr-cabinet__fact--' . esc_attr( $key ) . '"><dt>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd></div>';
}

/**
 * First play-card article span in post content.
 *
 * @param string $html Rendered post content.
 * @return array{0: int, 1: int}|null Span, or null.
 */
function card_article_span( string $html ): ?array {
	if ( ! preg_match_all( '/<article\\b[^>]*\\bclass="([^"]*)"[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}
	foreach ( $matches[0] as $i => $match ) {
		$classes = preg_split( '/\s+/', trim( $matches[1][ $i ][0] ) );
		if ( ! in_array( 'pk-card', $classes, true ) || ! in_array( 'k-play', $classes, true ) ) {
			continue;
		}
		$start = (int) $match[1];
		$close = strpos( $html, '</article>', $start );
		return false === $close ? null : array( $start, $close + 10 );
	}
	return null;
}

/**
 * Opening span for the plugin's singular entry wrapper.
 *
 * @param string $html Rendered post-content block.
 * @return array{0: int, 1: int}|null Start and end offsets, or null.
 */
function singular_entry_open_span( string $html ): ?array {
	if ( ! preg_match( '/<div\\b[^>]*\\bclass="[^"]*(?<![\\w-])pkiw-singular-entry(?![\\w-])[^"]*"[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	$start = (int) $match[0][1];
	return array( $start, $start + strlen( $match[0][0] ) );
}

/**
 * Turn an archive play item into an arcade cabinet.
 *
 * @param mixed $html  Rendered HTML.
 * @param mixed $group Play group.
 * @param mixed $post  Post.
 * @param mixed $ids   Duplicate-title labels.
 * @return mixed Filtered HTML.
 */
function filter_item( $html, $group, $post, $ids ) {
	if ( 'video' !== $group || ! $post instanceof \WP_Post || ! is_string( $html ) || '' === $html || ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return $html;
	}

	$facts = facts( $post->ID );
	if ( array() === $facts || has_class_token( $html, 'pk-card--protected' ) ) {
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( $tags->next_tag(
			array(
				'tag_name'   => 'article',
				'class_name' => 'pk-card',
			)
		) ) {
			$tags->add_class( 'cr-cabinet' );
			$tags->add_class( 'cr-cabinet--locked' );
			return $tags->get_updated_html();
		}
		return $html;
	}

	foreach ( CUT_ARCHIVE as $cut ) {
		$html = cut_elements( $html, $cut[0], $cut[1] );
	}

	$picture = picture( $post->ID );
	$tags    = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pk-card',
		)
	) ) {
		$tags->add_class( 'cr-cabinet' );
		if ( '' === esc_url( (string) ( $picture['url'] ?? '' ) ) ) {
			$tags->add_class( 'cr-cabinet--no-art' );
		}
	}
	if ( is_array( $ids ) && ! empty( $ids['repeats'] ) ) {
		if ( $tags->next_tag( array( 'class_name' => 'pk-title' ) ) ) {
			$tags->set_attribute( 'id', (string) $ids['title'] );
			if ( $tags->next_tag( 'a' ) ) {
				$tags->set_attribute( 'aria-labelledby', (string) $ids['title'] . ' ' . (string) $ids['date'] );
			}
		}
	}
	$html = $tags->get_updated_html();

	$rank    = video_rank( $post->ID );
	$eager   = $rank >= 0 && $rank <= 2;
	$tail    = art_html( 'cr-cabinet', 'screen', $picture, '', 'medium_large', $eager ? 'eager' : 'lazy', $eager );
	$repeats = is_array( $ids ) && ! empty( $ids['repeats'] );
	if ( $repeats ) {
		$tail .= '<span id="' . esc_attr( (string) $ids['date'] ) . '" hidden>' . esc_html( (string) get_the_date( '', $post ) ) . '</span>';
	}
	if ( '' !== (string) ( $facts['hours_label'] ?? '' ) ) {
		$tail .= '<p class="cr-cabinet__score">' . esc_html( (string) $facts['hours_label'] ) . '</p>';
	}
	$tail .= panel_html();

	$span = element_span( $html, 'div', 'pk-caption' );
	return null === $span ? $html : substr( $html, 0, $span[1] ) . $tail . substr( $html, $span[1] );
}
add_filter( 'courtneyr_child_play_item', __NAMESPACE__ . '\\filter_item', 10, 4 );

/**
 * Turn a Stream play card into a cartridge.
 *
 * @param mixed $html  Rendered HTML.
 * @param mixed $group Play group.
 * @param mixed $post  Post.
 * @return mixed Filtered HTML.
 */
function filter_stream_card( $html, $group, $post ) {
	if ( 'video' !== $group || ! $post instanceof \WP_Post || ! is_string( $html ) || '' === $html || ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return $html;
	}

	$facts = facts( $post->ID );
	if ( array() === $facts || has_class_token( $html, 'pk-card--protected' ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'p',
			'class_name' => 'pk-stream-date',
		)
	) ) {
		$tags->remove_class( 'pk-sub' );
		$tags->add_class( 'cr-cartridge__date' );
	}
	$html = $tags->get_updated_html();

	foreach ( CUT_CARTRIDGE as $cut ) {
		$html = cut_elements( $html, $cut[0], $cut[1] );
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pk-card',
		)
	) ) {
		$tags->add_class( 'cr-cartridge' );
		$html = $tags->get_updated_html();
	}

	$span = element_span( $html, 'div', 'pk-caption' );
	if ( null === $span ) {
		return $html;
	}

	$platform = trim( (string) ( $facts['platform'] ?? '' ) );
	$inside   = '' !== $platform ? '<p class="cr-cartridge__platform">' . esc_html( $platform ) . '</p>' : '';
	$label    = art_html( 'cr-cartridge', 'label', picture( $post->ID ), '', 'medium_large', 'lazy', false );
	$html     = substr( $html, 0, $span[1] - 6 ) . $inside . substr( $html, $span[1] - 6 );
	$span     = element_span( $html, 'div', 'pk-caption' );
	return null === $span ? $html : substr( $html, 0, $span[1] ) . $label . substr( $html, $span[1] );
}
add_filter( 'courtneyr_child_play_stream_card', __NAMESPACE__ . '\\filter_stream_card', 10, 3 );

/**
 * Whether the queried post is a visible video-game play single.
 *
 * @param bool $refresh Whether to recompute the cached answer.
 * @return bool True for the video single.
 */
function is_video_single( bool $refresh = false ): bool {
	static $cache = array();

	$post_id = (int) get_queried_object_id();
	if ( ! $refresh && isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$post = $post_id ? \get_post( $post_id ) : null;
	$ok   = \is_singular( 'post' )
		&& $post instanceof \WP_Post
		&& has_term( 'play', 'kind', $post )
		&& function_exists( '\\PKIW\\play_group' )
		&& 'video' === (string) \PKIW\play_group( $post->ID )
		&& ! post_password_required( $post )
		&& array() !== facts( $post->ID )
		&& null !== find_block( $post, 'post-kinds-indieweb/play-card' );

	$cache[ $post_id ] = $ok;
	return $ok;
}

/**
 * Drop duplicate template title and featured image blocks.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string Rendered HTML.
 */
function drop_template_header( string $html, array $block ): string {
	$name  = (string) ( $block['blockName'] ?? '' );
	$class = (string) ( $block['attrs']['className'] ?? '' );
	$drop  = ( 'core/group' === $name && str_contains( $class, 'single-post__header' ) ) || ( 'core/post-featured-image' === $name && str_contains( $class, 'single-post__featured' ) );
	return $drop && is_video_single() ? '' : $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\drop_template_header', 10, 2 );

/**
 * Build the single-page cabinet and record sheet.
 *
 * @param \WP_Post             $post  Post.
 * @param array<string, mixed> $facts Play facts.
 * @return string Cabinet markup.
 */
function single_cabinet_html( \WP_Post $post, array $facts ): string {
	$post_title = trim( html_entity_decode( (string) get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) );
	$card_title = trim( (string) ( $facts['title'] ?? '' ) );
	$has_real   = '' !== $post_title || '' !== $card_title;
	$title      = '' !== $post_title ? $post_title : $card_title;
	if ( '' === $title ) {
		$title = function_exists( '\\PKIW\\untitled_name' ) ? (string) \PKIW\untitled_name( $post ) : __( 'Play', 'courtneyr-child' );
	}

	$same           = '' === $post_title || '' === $card_title || comparable_name( $post_title ) === comparable_name( $card_title );
	$title_has_name = $has_real && $same;
	$permalink      = esc_url( (string) get_permalink( $post ) );
	$entry          = '<span class="cr-cabinet__entry" hidden>';
	if ( $has_real ) {
		$entry .= '<data class="p-name" value="' . esc_attr( $title ) . '"></data>';
	}
	$entry .= '<data class="u-url" value="' . $permalink . '"></data>';
	$entry .= '<data class="dt-published" value="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '"></data>';
	if ( function_exists( '\\Courtneyr\\Child\\Microformats\\entry_author_html' ) ) {
		$entry .= \Courtneyr\Child\Microformats\entry_author_html( $post->ID );
	}
	$entry .= '</span>';

	$alt = trim( (string) ( $facts['cover_alt'] ?? '' ) );
	if ( '' === $alt ) {
		$alt = sprintf(
			/* translators: %s: game title. */
			__( 'Box art for %s', 'courtneyr-child' ),
			$title
		);
	}

	$body  = '<div class="cr-cabinet__body">';
	$body .= '<div class="cr-cabinet__marquee"><h1 class="cr-cabinet__title' . ( $title_has_name ? ' p-name' : '' ) . '">' . esc_html( $title ) . '</h1></div>';
	$body .= art_html( 'cr-cabinet', 'screen', picture( $post->ID ), $alt, 'large', 'eager', true );
	if ( '' !== (string) ( $facts['hours_label'] ?? '' ) ) {
		$body .= '<p class="cr-cabinet__score">' . esc_html( (string) $facts['hours_label'] ) . '</p>';
	}
	$label = arcade_label( (string) ( $facts['status'] ?? '' ) );
	if ( '' !== $label ) {
		$body .= '<p class="cr-cabinet__label" aria-hidden="true">' . esc_html( $label ) . '</p>';
	}
	$body .= panel_html() . '</div>';

	$rows = '';
	if ( '' !== $post_title && '' !== $card_title && ! $same ) {
		$rows .= fact_row( 'game', __( 'Game', 'courtneyr-child' ), '<span class="p-name">' . esc_html( $card_title ) . '</span>' );
	}
	if ( '' !== trim( (string) ( $facts['platform'] ?? '' ) ) ) {
		$rows .= fact_row( 'platform', __( 'Platform', 'courtneyr-child' ), esc_html( (string) $facts['platform'] ) );
	}
	if ( '' !== (string) ( $facts['status_label'] ?? '' ) ) {
		$rows .= fact_row( 'status', __( 'Status', 'courtneyr-child' ), esc_html( (string) $facts['status_label'] ) );
	}
	$rating       = (float) ( $facts['rating'] ?? 0 );
	$rating_label = function_exists( '\\PKIW\\card_rating_label' ) ? (string) \PKIW\card_rating_label( $rating ) : '';
	if ( $rating > 0 && '' !== $rating_label && function_exists( '\\PKIW\\card_rating_html' ) ) {
		$rows .= fact_row( 'rating', __( 'Rating', 'courtneyr-child' ), (string) \PKIW\card_rating_html( $rating, 5, true ) . '<span class="cr-cabinet__rating-label">' . esc_html( $rating_label ) . '</span>' );
	}
	$played = is_array( $facts['played_at'] ?? null ) ? $facts['played_at'] : array( '', '' );
	if ( '' !== (string) ( $played[0] ?? '' ) ) {
		$rows .= fact_row( 'played', __( 'Played', 'courtneyr-child' ), '<time class="dt-published" datetime="' . esc_attr( (string) $played[0] ) . '">' . esc_html( (string) ( $played[1] ?? '' ) ) . '</time>' );
	}

	$links = '';
	foreach (
		array(
			array( 'game_url', (string) ( $facts['game_url_label'] ?? '' ), ' u-url' ),
			array( 'official_url', __( 'Official Site', 'courtneyr-child' ), '' ),
			array( 'purchase_url', __( 'Buy', 'courtneyr-child' ), '' ),
		) as $link
	) {
		$url = esc_url( (string) ( $facts[ $link[0] ] ?? '' ) );
		if ( '' === $url || '' === trim( $link[1] ) ) {
			continue;
		}
		$hint   = function_exists( '\\PKIW\\pkiw_new_tab_hint' ) ? (string) \PKIW\pkiw_new_tab_hint() : '';
		$links .= '<a class="cr-cabinet__link' . esc_attr( $link[2] ) . '" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $link[1] ) . $hint . '</a>';
	}
	$links = '' === $links ? '' : '<div class="cr-cabinet__links">' . $links . '</div>';

	$review = (string) ( $facts['review'] ?? '' );
	$review = '' !== trim( wp_strip_all_tags( $review ) )
		? '<section class="cr-cabinet__review"><h2 class="cr-cabinet__review-title">' . esc_html__( 'Review', 'courtneyr-child' ) . '</h2><div class="cr-cabinet__review-body p-content">' . wp_kses_post( $review ) . '</div></section>'
		: '';

	$sheet = '<div class="cr-cabinet__sheet"><dl class="cr-cabinet__record">' . $rows . '</dl>' . $links . $review . '</div>';
	return $entry . '<article class="cr-cabinet cr-cabinet--single h-cite u-play-of" data-pkiw-play-group="video">' . $body . $sheet . uid_html( $facts ) . '</article>';
}

/**
 * Replace the play card in post content with the single cabinet.
 *
 * The cabinet lands inside the plugin's h-entry wrapper so the single is one
 * h-entry with a play-of h-cite; prepending remains the fallback when that
 * wrapper is absent.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string Rendered HTML.
 */
function single_cabinet( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_video_single() ) {
		return $html;
	}

	$post = \get_post( \get_queried_object_id() );
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}

	$cabinet = single_cabinet_html( $post, facts( $post->ID ) );
	$span    = card_article_span( $html );
	if ( null !== $span ) {
		$html = substr( $html, 0, $span[0] ) . substr( $html, $span[1] );
	}

	$entry = singular_entry_open_span( $html );
	if ( null !== $entry ) {
		return substr( $html, 0, $entry[1] ) . $cabinet . substr( $html, $entry[1] );
	}

	return $cabinet . $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\single_cabinet', 20, 2 );

/**
 * Mark video play singles for the object stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[] Body classes.
 */
function body_class( array $classes ): array {
	if ( is_video_single() ) {
		$classes[] = 'cr-video-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Load the arcade object stylesheet where it can render.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_video_single() && ! \is_tax( 'kind', 'play' ) && ! is_stream_surface() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-play-video',
		COURTNEYR_CHILD_URI . '/assets/css/cr-play-video.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles', 11 );
