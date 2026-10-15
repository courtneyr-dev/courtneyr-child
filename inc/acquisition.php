<?php
/**
 * Acquisition archive: month bags of receipts (PKIW issue 239).
 *
 * The plugin owns acquisition facts and the card block. This file reads those
 * facts through \PKIW\kind_facts() only and sets each archive item out as a
 * paper receipt: the item's title as the one link, plus its stored local photo.
 * Type, place, cost, notes and dates stay off the archive, so the markup never
 * holds them. The month bags themselves come from the plugin's archive-sections
 * block; assets/css/cr-acquisition.css paints them.
 *
 * Two helpers here serve the single and Stream adapters too: facts(),
 * local_picture() and image_html().
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Acquisition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND          = 'acquisition';
const STYLE_HANDLE  = 'courtneyr-acquisition';
const RECEIPT_STYLE = 'cr-receipt';
const RECEIPT_CLASS = 'is-style-cr-receipt';
const RECEIPT_SIZES = '(min-width: 64rem) 11rem, (min-width: 48rem) 20vw, 40vw';

/** Sections whose first receipts load eagerly, and how many receipts count as the first row. */
const FIRST_ROW_SECTIONS = 3;
const FIRST_ROW_RECEIPTS = 2;

/** Whether this request is the acquisition kind archive. */
function is_acquisition_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single acquisition post. */
function is_acquisition_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/** Whether a Stream surface (the Stream page, the front page, the editor preview) is being served. */
function on_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) && \Courtneyr\Child\HomeSections\is_stream_surface();
}

/**
 * Acquisition facts from the plugin's render-time reader.
 *
 * The reader returns [] for a protected post and for a post the visitor may
 * not see, so every caller treats [] as "print nothing from the card".
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
 * Decode entities and trim, so esc_html() can run once on the result.
 *
 * @param string $text Text that may hold entities.
 * @return string
 */
function plain_text( string $text ): string {
	return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * The name an item prints under: the reader's name, else its title.
 *
 * The reader builds `name` (post title, else card title, else the plugin's
 * untitled name), so the theme never rebuilds it. The get_the_title() step
 * only covers a plugin that predates the key.
 *
 * @param \WP_Post             $post  Post.
 * @param array<string, mixed> $facts Acquisition facts.
 * @return string
 */
function name_of( \WP_Post $post, array $facts ): string {
	foreach ( array( 'name', 'title' ) as $key ) {
		$text = plain_text( (string) ( $facts[ $key ] ?? '' ) );
		if ( '' !== $text ) {
			return $text;
		}
	}
	return plain_text( (string) get_the_title( $post ) );
}

/**
 * The Query Loop block's heading level, clamped to 2-4.
 *
 * @param array<string, mixed> $block    Parsed block.
 * @param int                  $fallback Level when the block sets none.
 * @return int
 */
function heading_level( array $block, int $fallback = 3 ): int {
	return max( 2, min( 4, (int) ( $block['attrs']['headingLevel'] ?? $fallback ) ) );
}

/**
 * The picture a surface may print: a stored picture that is not hosted elsewhere.
 *
 * A remote picture prints nowhere (Courtney's answer to gate 6), so a
 * visitor's browser never tells a third-party host its address.
 *
 * @param array<string, mixed> $facts Acquisition facts.
 * @return array<string, mixed> The reader's picture, or [] when there is none to print.
 */
function local_picture( array $facts ): array {
	$picture = is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array();
	if ( '' === (string) ( $picture['url'] ?? '' ) || ! empty( $picture['remote'] ) ) {
		return array();
	}
	return $picture;
}

/**
 * One <img> for a local picture.
 *
 * Eager images take Perfmatters' skip-lazy marks and a `sizes` value without
 * `auto`, which is valid only on a lazy image.
 *
 * @param array<string, mixed> $picture Picture from local_picture().
 * @param array<string, mixed> $args    class, alt, eager, size, sizes.
 * @return string Image HTML, or '' when the picture has nothing to print.
 */
function image_html( array $picture, array $args ): string {
	$attrs = array(
		'class'    => (string) ( $args['class'] ?? '' ),
		'alt'      => (string) ( $args['alt'] ?? '' ),
		'decoding' => 'async',
		'sizes'    => (string) ( $args['sizes'] ?? RECEIPT_SIZES ),
	);
	if ( ! empty( $args['eager'] ) ) {
		$attrs['class']          = trim( $attrs['class'] . ' skip-lazy' );
		$attrs['loading']        = 'eager';
		$attrs['fetchpriority']  = 'high';
		$attrs['data-skip-lazy'] = '1';
	} else {
		$attrs['loading'] = 'lazy';
	}

	$id = (int) ( $picture['attachment_id'] ?? 0 );
	if ( $id > 0 ) {
		$img = (string) wp_get_attachment_image( $id, (string) ( $args['size'] ?? 'medium_large' ), false, $attrs );
		if ( '' !== $img ) {
			return $img;
		}
	}

	$url = (string) ( $picture['url'] ?? '' );
	if ( '' === $url ) {
		return '';
	}
	$width  = (int) ( $picture['width'] ?? 0 );
	$height = (int) ( $picture['height'] ?? 0 );
	if ( $width > 0 && $height > 0 ) {
		$attrs['width']  = (string) $width;
		$attrs['height'] = (string) $height;
	}
	$out = '<img src="' . esc_url( $url ) . '"';
	foreach ( $attrs as $name => $value ) {
		$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
	}
	return $out . '>';
}

/**
 * Register the receipt style on the plugin's stream-card block.
 *
 * @return void
 */
function register_receipt_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => RECEIPT_STYLE,
				'label' => __( 'Receipt', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_receipt_style' );

/**
 * Per-request counters for the first-row image rule.
 *
 * @return array{groups: array<string, int>, counts: array<string, int>}
 */
function &page_state(): array {
	static $state = array(
		'groups' => array(),
		'counts' => array(),
	);
	return $state;
}

/** Reset the first-row counters between requests and tests. */
function reset_page_state(): void {
	$state = &page_state();
	$state = array(
		'groups' => array(),
		'counts' => array(),
	);
}

/**
 * The engine's current section, or '' when items aren't in sections.
 *
 * @return string
 */
function current_section_key(): string {
	if ( class_exists( '\\PKIW\\Grouped_Archive' ) && is_callable( array( '\\PKIW\\Grouped_Archive', 'current_group' ) ) ) {
		$group = \PKIW\Grouped_Archive::current_group();
		if ( is_object( $group ) && method_exists( $group, 'slug' ) ) {
			return (string) $group->slug();
		}
	}
	return '';
}

/**
 * Whether the receipt being built is one of the first two in the first three sections.
 *
 * Only page 1 has a first row. Every receipt counts, so the order of calls is
 * the order of the page.
 *
 * @return bool
 */
function takes_first_row(): bool {
	$state = &page_state();
	$key   = current_section_key();
	if ( ! isset( $state['groups'][ $key ] ) ) {
		$state['groups'][ $key ] = count( $state['groups'] );
		$state['counts'][ $key ] = 0;
	}
	$position = $state['counts'][ $key ];
	++$state['counts'][ $key ];

	if ( (int) get_query_var( 'paged' ) > 1 ) {
		return false;
	}
	return $state['groups'][ $key ] < FIRST_ROW_SECTIONS && $position < FIRST_ROW_RECEIPTS;
}

/**
 * Normalized names that repeat on this page, so each repeat can carry its date.
 *
 * @return array<string, true>
 */
function repeated_names(): array {
	static $cache = array();

	$query = $GLOBALS['wp_query'] ?? null;
	$posts = is_object( $query ) && isset( $query->posts ) && is_array( $query->posts ) ? $query->posts : array();
	$ids   = array();
	foreach ( $posts as $post ) {
		$ids[] = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
	}
	$cache_key = implode( ',', $ids );
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$counts = array();
	foreach ( $posts as $post ) {
		$post = $post instanceof \WP_Post ? $post : get_post( $post );
		if ( ! $post instanceof \WP_Post || ! has_term( KIND, 'kind', $post ) ) {
			continue;
		}
		$name = post_password_required( $post ) ? plain_text( (string) get_the_title( $post ) ) : name_of( $post, facts( $post->ID ) );
		$key  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
		if ( '' !== $key ) {
			$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
		}
	}

	$repeats = array();
	foreach ( $counts as $key => $count ) {
		if ( $count > 1 ) {
			$repeats[ (string) $key ] = true;
		}
	}
	$cache[ $cache_key ] = $repeats;
	return $repeats;
}

/**
 * The post a stream-card block is rendering.
 *
 * @param mixed $instance Block instance with the Query Loop's post ID.
 * @return \WP_Post|null
 */
function block_post( $instance ): ?\WP_Post {
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	return $post instanceof \WP_Post ? $post : null;
}

/**
 * Set an archive item out as a receipt.
 *
 * Runs on the stream-card block when the Query Loop gave it the receipt style.
 * A protected post gets a text-only receipt with its title; a post the reader
 * returns nothing for, or a site without the plugin's reader, keeps the
 * plugin's own card.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 * @return string
 */
function receipt( string $html, array $block, $instance = null ): string {
	if ( '' === $html || ! str_contains( (string) ( $block['attrs']['className'] ?? '' ), RECEIPT_CLASS ) ) {
		return $html;
	}
	$post = block_post( $instance );
	if ( ! $post instanceof \WP_Post || ! has_term( KIND, 'kind', $post ) ) {
		return $html;
	}

	$level = heading_level( $block );
	$link  = esc_url( (string) get_permalink( $post ) );

	if ( post_password_required( $post ) ) {
		return '<article class="cr-receipt cr-receipt--text cr-ticket cr-ticket--zigzag"><h' . $level . ' class="cr-receipt__title"><a href="' . $link . '">' . esc_html( plain_text( (string) get_the_title( $post ) ) ) . '</a></h' . $level . '></article>';
	}

	$facts = facts( $post->ID );
	if ( array() === $facts ) {
		return $html;
	}

	$id      = (int) $post->ID;
	$name    = name_of( $post, $facts );
	$key     = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
	$repeats = isset( repeated_names()[ $key ] );
	$eager   = takes_first_row();

	$photo   = '';
	$picture = local_picture( $facts );
	if ( array() !== $picture ) {
		$img = image_html(
			$picture,
			array(
				'class' => 'cr-receipt__img',
				'alt'   => '',
				'eager' => $eager,
				'size'  => 'medium_large',
				'sizes' => RECEIPT_SIZES,
			)
		);
		if ( '' !== $img ) {
			$photo = '<figure class="cr-receipt__photo" data-cr-cover-fallback>' . $img . '</figure>';
		}
	}

	$title_html = $repeats ? '<span id="cr-receipt-t-' . $id . '">' . esc_html( $name ) . '</span>' : esc_html( $name );
	$link_attrs = $repeats ? ' aria-labelledby="cr-receipt-t-' . $id . ' cr-receipt-d-' . $id . '"' : '';

	$out  = '<article class="cr-receipt cr-ticket cr-ticket--zigzag' . ( '' === $photo ? ' cr-receipt--text' : '' ) . '">';
	$out .= '<h' . $level . ' class="cr-receipt__title"><a href="' . $link . '"' . $link_attrs . '>' . $title_html . '</a></h' . $level . '>';
	$out .= $photo;
	if ( $repeats ) {
		// Text a reader hears after a repeated title. The time has no mf2 class: the entry's hidden dt-published stays the plugin's.
		$out .= '<time id="cr-receipt-d-' . $id . '" class="screen-reader-text" datetime="' . esc_attr( (string) get_the_date( 'Y-m-d', $post ) ) . '">' . esc_html( (string) get_the_date( '', $post ) ) . '</time>';
	}
	if ( function_exists( '\\PKIW\\stream_card_kind_cite' ) ) {
		$out .= (string) \PKIW\stream_card_kind_cite( $post );
	}
	return $out . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\receipt', 10, 3 );

/**
 * Load the acquisition paint where its markup can appear.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_acquisition_archive() && ! is_acquisition_single() && ! on_stream_surface() ) {
		return;
	}
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-acquisition.css',
		array( 'courtneyr-paper', 'courtneyr-ticket' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Keep Perfmatters from preloading an image the archive already prioritizes.
 *
 * The first receipts carry fetchpriority high themselves.
 *
 * @param mixed $count Images Perfmatters preloads.
 * @return mixed
 */
function no_critical_image_preload( $count ) {
	return is_acquisition_archive() ? 0 : $count;
}
add_filter( 'perfmatters_preload_critical_images', __NAMESPACE__ . '\\no_critical_image_preload' );

/**
 * Give the editor canvas the single page class for acquisition posts.
 *
 * @param mixed $kinds Editor parity registry keyed by kind slug.
 * @return mixed
 */
function editor_parity_kinds( $kinds ) {
	if ( ! is_array( $kinds ) ) {
		return $kinds;
	}
	$entry   = is_array( $kinds[ KIND ] ?? null ) ? $kinds[ KIND ] : array();
	$classes = isset( $entry['classes'] ) && is_array( $entry['classes'] ) ? $entry['classes'] : array( 'single-post', 'kind-' . KIND );
	if ( ! in_array( 'cr-acq-single-page', $classes, true ) ) {
		$classes[] = 'cr-acq-single-page';
	}
	$entry['classes'] = $classes;
	$kinds[ KIND ]    = $entry;
	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\editor_parity_kinds' );
