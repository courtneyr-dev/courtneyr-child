<?php
/**
 * Board-game object adapters for Play archive, Stream and singles.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\PlayBoard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Palette papers available to board-game objects.
 */
const PAPERS = array( 'sky-blue', 'periwinkle', 'light-orange', 'selective-yellow', 'ut-orange', 'printer-ivory', 'prussian-blue', 'cerulean', 'russian-violet' );

/**
 * Pick the stable paper for one post.
 *
 * @param int $post_id Post ID.
 * @return string Paper token suffix, or '' when stamps are unavailable.
 */
function paper_for( int $post_id ): string {
	if ( ! function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		return '';
	}

	return \Courtneyr\Child\Stamps\seeded_paper( $post_id, PAPERS );
}

/**
 * Read the first pk-title heading level in a rendered card.
 *
 * @param string $html     Rendered HTML.
 * @param int    $fallback Fallback heading level.
 * @return int Heading level from 2 to 6.
 */
function heading_level( string $html, int $fallback ): int {
	if ( preg_match( '/<h([2-6])\b[^>]*class=(["\'])(?=[^"\']*\bpk-title\b)[^"\']*\2/i', $html, $match ) ) {
		return (int) $match[1];
	}

	return max( 2, min( 6, $fallback ) );
}

/**
 * Get the visible object title.
 *
 * @param \WP_Post             $post  Post object.
 * @param array<string, mixed> $facts Play facts.
 * @return array{name: string, synthetic: bool}
 */
function object_title( \WP_Post $post, array $facts ): array {
	$name = is_scalar( $facts['title'] ?? null ) ? trim( (string) $facts['title'] ) : '';
	if ( '' !== $name ) {
		return array(
			'name'      => $name,
			'synthetic' => false,
		);
	}

	$name = trim( wp_strip_all_tags( get_the_title( $post ) ) );
	if ( '' !== $name ) {
		return array(
			'name'      => $name,
			'synthetic' => false,
		);
	}

	$name = function_exists( '\\PKIW\\untitled_name' ) ? \PKIW\untitled_name( $post ) : __( 'Untitled', 'courtneyr-child' );
	return array(
		'name'      => $name,
		'synthetic' => true,
	);
}

/**
 * Hidden microformat properties shared by board objects.
 *
 * @param \WP_Post $post Post object.
 * @return string Hidden property markup.
 */
function hidden_props( \WP_Post $post ): string {
	$html = '';
	if ( function_exists( '\\PKIW\\stream_card_kind_cite' ) ) {
		$html .= \PKIW\stream_card_kind_cite( $post );
	}
	if ( function_exists( '\\PKIW\\entry_author_html' ) ) {
		$author = \PKIW\entry_author_html( $post );
		if ( '' !== $author ) {
			$html .= '<span class="pk-entry-props" hidden>' . $author . '</span>';
		}
	}

	return $html;
}

/**
 * Read board facts for a post, or return an empty array when unavailable.
 *
 * @param \WP_Post $post Post object.
 * @return array<string, mixed>
 */
function board_facts( \WP_Post $post ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}

	$facts = \PKIW\kind_facts( $post->ID );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Whether a board adapter should leave this render alone.
 *
 * @param string  $html  Incoming HTML.
 * @param string  $group Play group.
 * @param mixed   $post  Post candidate.
 * @param mixed[] $facts Facts from the plugin.
 * @return bool
 */
function should_passthrough( string $html, string $group, $post, array $facts ): bool {
	return '' === $html
		|| 'board' !== $group
		|| ! $post instanceof \WP_Post
		|| post_password_required( $post )
		|| empty( $facts );
}

/**
 * Board-game spine for the Play archive.
 *
 * @param string              $html  Incoming card HTML.
 * @param string              $group Play group.
 * @param mixed               $post  Post object.
 * @param array<string,mixed> $ids   Accessible label IDs.
 * @return string Adapted HTML.
 */
function spine( string $html, string $group, $post, array $ids ): string {
	$facts = $post instanceof \WP_Post ? board_facts( $post ) : array();
	if ( should_passthrough( $html, $group, $post, $facts ) ) {
		return $html;
	}

	$level     = heading_level( $html, 3 );
	$paper     = paper_for( $post->ID );
	$title     = object_title( $post, $facts );
	$permalink = (string) get_permalink( $post );
	$date_id   = (string) ( $ids['date'] ?? '' );
	$title_id  = (string) ( $ids['title'] ?? '' );
	$classes   = 'pk-card k-play cr-play-item cr-spine';
	if ( '' !== $paper ) {
		$classes .= ' cr-paper cr-paper--' . $paper;
	}

	$heading_class = $title['synthetic'] ? 'pk-title' : 'pk-title p-name';
	$labelledby    = '';
	if ( ! empty( $ids['repeats'] ) && '' !== $title_id && '' !== $date_id ) {
		$labelledby = ' aria-labelledby="' . esc_attr( $title_id . ' ' . $date_id ) . '"';
	}
	$title_id_attr = '' !== $title_id ? ' id="' . esc_attr( $title_id ) . '"' : '';
	$date_id_attr  = '' !== $date_id ? ' id="' . esc_attr( $date_id ) . '"' : '';

	return '<article class="' . esc_attr( $classes ) . '" data-pkiw-play-group="board">'
		. '<h' . $level . ' class="' . esc_attr( $heading_class ) . '"><a class="u-url" href="' . esc_url( $permalink ) . '"' . $labelledby . '><span' . $title_id_attr . '>' . esc_html( $title['name'] ) . '</span></a></h' . $level . '>'
		. '<p class="cr-spine__date"><time' . $date_id_attr . ' class="dt-published" datetime="' . esc_attr( (string) get_post_time( 'Y-m-d', false, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></p>'
		. hidden_props( $post )
		. '</article>';
}

/**
 * Build the decorative thumbnail for a Stream score pad.
 *
 * @param \WP_Post $post Post object.
 * @return string Thumbnail markup.
 */
function scorepad_thumb( \WP_Post $post ): string {
	if ( function_exists( '\\PKIW\\kind_picture' ) ) {
		$picture = \PKIW\kind_picture( $post->ID );
		if ( is_array( $picture ) && '' !== (string) ( $picture['url'] ?? '' ) ) {
			$attachment_id = (int) ( $picture['attachment_id'] ?? 0 );
			$image         = '';
			if ( $attachment_id > 0 ) {
				$image = (string) wp_get_attachment_image(
					$attachment_id,
					'medium',
					false,
					array(
						'alt'     => '',
						'loading' => 'lazy',
						'class'   => 'cr-scorepad__image',
					)
				);
			}
			if ( '' === $image ) {
				$image = '<img class="cr-scorepad__image" src="' . esc_url( (string) $picture['url'] ) . '" alt="" loading="lazy">';
			}
			return '<div class="cr-scorepad__thumb" data-cr-cover-fallback aria-hidden="true">' . $image . '</div>';
		}
	}

	return '<div class="cr-scorepad__thumb cr-scorepad__thumb--meeple" aria-hidden="true"></div>';
}

/**
 * Board-game score-pad slip for the Stream.
 *
 * @param string $html  Incoming card HTML.
 * @param string $group Play group.
 * @param mixed  $post  Post object.
 * @return string Adapted HTML.
 */
function slip( string $html, string $group, $post ): string {
	$facts = $post instanceof \WP_Post ? board_facts( $post ) : array();
	if ( should_passthrough( $html, $group, $post, $facts ) ) {
		return $html;
	}

	$level         = heading_level( $html, 2 );
	$title         = object_title( $post, $facts );
	$permalink     = (string) get_permalink( $post );
	$kind_label    = function_exists( '\\PKIW\\get_kind_label' )
		? \PKIW\get_kind_label( __( 'Play', 'courtneyr-child' ), 'play', 'stream-card' )
		: __( 'Play', 'courtneyr-child' );
	$platform      = is_scalar( $facts['platform'] ?? null ) ? trim( (string) $facts['platform'] ) : '';
	$rating_label  = is_scalar( $facts['rating_label'] ?? null ) ? trim( (string) $facts['rating_label'] ) : '';
	$heading_class = $title['synthetic'] ? 'pk-title cr-scorepad__title' : 'pk-title p-name cr-scorepad__title';

	$out = '<article class="pk-card k-play cr-scorepad" data-pkiw-play-group="board">'
		. '<span class="cr-scorepad__label">' . esc_html( $kind_label ) . '</span>'
		. '<h' . $level . ' class="' . esc_attr( $heading_class ) . '"><a class="u-url" href="' . esc_url( $permalink ) . '">' . esc_html( $title['name'] ) . '</a></h' . $level . '>';
	if ( '' !== $platform ) {
		$out .= '<p class="cr-scorepad__platform">' . esc_html( $platform ) . '</p>';
	}
	$out .= '<p class="cr-scorepad__date"><time class="dt-published" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></p>';
	if ( '' !== $rating_label ) {
		$out .= '<p class="cr-scorepad__rating">' . esc_html( $rating_label ) . '</p>';
	}
	$out .= scorepad_thumb( $post )
		. hidden_props( $post )
		. '</article>';

	return $out;
}

/**
 * Decorate Staff Picks with paper classes and fallback titles.
 *
 * @param string $html Staff Picks block HTML.
 * @return string Adapted HTML.
 */
function picks( string $html ): string {
	if ( '' === $html || false === strpos( $html, 'pkiw-staff-picks__item' ) ) {
		return $html;
	}

	$out = preg_replace_callback(
		'#<li\b[^>]*\bpkiw-staff-picks__item\b[^>]*>.*?</li>#s',
		static function ( array $matches ): string {
			$item  = $matches[0];
			$title = '';
			$href  = '';
			if ( preg_match( '#<a\b[^>]*\bpkiw-staff-picks__link\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#s', $item, $link ) ) {
				$href  = html_entity_decode( $link[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$title = wp_strip_all_tags( html_entity_decode( $link[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			}

			$post_id = '' !== $href && function_exists( 'url_to_postid' ) ? (int) url_to_postid( $href ) : 0;
			$paper   = $post_id > 0 ? paper_for( $post_id ) : '';
			$tags    = new \WP_HTML_Tag_Processor( $item );
			if ( ! $tags->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'pkiw-staff-picks__box',
				)
			) ) {
				return $item;
			}

			$class_attr = (string) ( $tags->get_attribute( 'class' ) ?? '' );
			$is_text    = false !== strpos( ' ' . $class_attr . ' ', ' pkiw-staff-picks__box--text ' );
			if ( '' !== $paper ) {
				$tags->add_class( 'cr-paper' );
				$tags->add_class( 'cr-paper--' . $paper );
			}
			if ( ! $is_text ) {
				$tags->set_attribute( 'data-cr-cover-fallback', true );
			}
			$item = $tags->get_updated_html();

			if ( $is_text ) {
				return $item;
			}

			$span = '<span class="cr-box__title" aria-hidden="true">' . esc_html( $title ) . '</span>';
			return preg_replace( '#(</div>)#', $span . '$1', $item, 1 );
		},
		$html
	);

	return null === $out ? $html : $out;
}

/**
 * Drop the template featured image when the tabletop object already prints it.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string Rendered block HTML.
 */
function drop_featured_image( string $html, array $block ): string {
	$name  = (string) ( $block['blockName'] ?? '' );
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( 'core/post-featured-image' !== $name || false === strpos( $class, 'single-post__featured' ) || ! is_singular( 'post' ) ) {
		return $html;
	}
	if ( ! function_exists( '\\PKIW\\play_group' ) || ! function_exists( '\\PKIW\\kind_picture' ) ) {
		return $html;
	}

	$post_id = (int) get_queried_object_id();
	if ( 'board' !== \PKIW\play_group( $post_id ) ) {
		return $html;
	}

	$picture = \PKIW\kind_picture( $post_id );
	return is_array( $picture ) && ! empty( $picture['suppress_featured'] ) ? '' : $html;
}

/**
 * Load the board-game paint where board objects can render.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! function_exists( '\\Courtneyr\\Child\\Play\\is_play_archive' ) || ! function_exists( '\\Courtneyr\\Child\\Play\\is_play_single' ) ) {
		return;
	}

	$is_stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();

	if ( ! is_admin() && ! \Courtneyr\Child\Play\is_play_archive() && ! \Courtneyr\Child\Play\is_play_single() && ! $is_stream ) {
		return;
	}

	wp_enqueue_style(
		'courtneyr-play-board',
		COURTNEYR_CHILD_URI . '/assets/css/cr-play-board.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}

add_filter( 'courtneyr_child_play_item', __NAMESPACE__ . '\\spine', 10, 4 );
add_filter( 'courtneyr_child_play_stream_card', __NAMESPACE__ . '\\slip', 10, 3 );
add_filter( 'render_block_post-kinds-indieweb/staff-picks', __NAMESPACE__ . '\\picks', 10, 1 );
add_filter( 'render_block', __NAMESPACE__ . '\\drop_featured_image', 10, 2 );
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles', 10, 0 );
