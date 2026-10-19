<?php
/**
 * Video archive tapes.
 *
 * The plugin owns video facts and entry properties. This adapter removes the
 * generic Stream-card presentation and keeps its semantic entry shell.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'video';
const CARD_STYLE   = 'is-style-cr-shelf-tape';
const FIRST_ROW    = 3;
const STYLE_HANDLE = 'courtneyr-video';

/** Register the tape treatment for the plugin's Stream card block. */
function register_card_style(): void {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}
	register_block_style(
		'post-kinds-indieweb/stream-card',
		array(
			'name'  => 'cr-shelf-tape',
			'label' => __( 'Shelf tape', 'courtneyr-child' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style' );

/** Load the video paint on its archive, single, Stream surfaces and editor. */
function enqueue_styles(): void {
	$stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
	$single = is_singular( 'post' ) && has_term( KIND, 'kind' );
	if ( ! is_admin() && ! is_tax( 'kind', KIND ) && ! $stream && ! $single ) {
		return;
	}
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-video.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/** Per-request count of every rendered tape. */
function &tape_count(): int {
	static $count = 0;
	return $count;
}

/** Per-request plugin facts, keyed by post ID. */
function &facts_cache(): array {
	static $facts = array();
	return $facts;
}

/** Per-request duplicate-name sets, keyed by the current ID list. */
function &duplicate_cache(): array {
	static $duplicates = array();
	return $duplicates;
}

/** Reset request state between archive renders and standalone tests. */
function reset_page_state(): void {
	$count      = &tape_count();
	$count      = 0;
	$facts      = &facts_cache();
	$facts      = array();
	$duplicates = &duplicate_cache();
	$duplicates = array();
}

/**
 * Read one post's normalized facts once this request.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function post_facts( int $post_id ): array {
	$cache = &facts_cache();
	if ( ! array_key_exists( $post_id, $cache ) ) {
		$cache[ $post_id ] = function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $post_id ) : array();
	}
	return $cache[ $post_id ];
}

/**
 * IDs in the grouped page, with the main query as the compatibility path.
 *
 * @return int[]
 */
function page_post_ids(): array {
	$ids = array();
	if ( class_exists( '\\PKIW\\Grouped_Archive' ) && method_exists( '\\PKIW\\Grouped_Archive', 'current_page_ids' ) ) {
		$ids = (array) \PKIW\Grouped_Archive::current_page_ids();
	}
	if ( empty( $ids ) ) {
		global $wp_query;
		$posts = is_object( $wp_query ) && isset( $wp_query->posts ) ? (array) $wp_query->posts : array();
		foreach ( $posts as $post ) {
			$ids[] = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
		}
	}
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	if ( empty( $ids ) ) {
		$current = (int) get_the_ID();
		if ( 0 < $current ) {
			$ids[] = $current;
		}
	}
	return $ids;
}

/**
 * Names occurring more than once on the current grouped page.
 *
 * @return array<string, bool>
 */
function page_duplicate_names(): array {
	$ids   = page_post_ids();
	$key   = implode( ',', $ids );
	$cache = &duplicate_cache();
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$counts = array();
	foreach ( $ids as $post_id ) {
		$facts = post_facts( $post_id );
		$name  = trim( (string) ( $facts['name'] ?? '' ) );
		if ( '' !== $name ) {
			$counts[ $name ] = ( $counts[ $name ] ?? 0 ) + 1;
		}
	}
	$cache[ $key ] = array_fill_keys(
		array_keys( array_filter( $counts, static fn( int $count ): bool => 1 < $count ) ),
		true
	);
	return $cache[ $key ];
}

/**
 * Build the decorative archive picture from normalized facts.
 *
 * @param array<string, mixed> $facts Video facts.
 * @param bool                 $eager Whether this tape is in the first row.
 * @return string
 */
function tape_picture( array $facts, bool $eager ): string {
	$picture = is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array();
	$id      = (int) ( $picture['attachment_id'] ?? 0 );
	if ( 0 >= $id || '' === (string) ( $picture['source'] ?? '' ) ) {
		return '';
	}
	$classes    = 'cr-shelf-tape__img' . ( $eager ? ' skip-lazy' : '' );
	$attributes = array(
		'class'    => $classes,
		'alt'      => '',
		'decoding' => 'async',
		'sizes'    => '(min-width: 64rem) 22rem, (min-width: 40rem) 30vw, 92vw',
		'loading'  => $eager ? 'eager' : 'lazy',
	);
	if ( $eager ) {
		$attributes['fetchpriority']  = 'high';
		$attributes['data-skip-lazy'] = '1';
	}
	return (string) wp_get_attachment_image( $id, 'medium_large', false, $attributes );
}

/**
 * Build a trusted runtime element from normalized facts.
 *
 * @param array<string, mixed> $facts Video facts.
 * @return string
 */
function runtime_time( array $facts ): string {
	if ( 0 >= (int) ( $facts['runtime_seconds'] ?? 0 ) ) {
		return '';
	}
	return '<time class="cr-shelf-tape__runtime" datetime="' . esc_attr( (string) ( $facts['runtime_iso'] ?? '' ) ) . '">'
		. esc_html( (string) ( $facts['runtime_clock'] ?? '' ) )
		. '<span class="pk-sr-only"> (' . esc_html( (string) ( $facts['runtime_human'] ?? '' ) ) . ')</span></time>';
}

/**
 * Add classes to the first article in a card fragment.
 *
 * @param string   $html    Card fragment.
 * @param string[] $classes Classes to add.
 * @return string
 */
function add_article_classes( string $html, array $classes ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	foreach ( $classes as $class_name ) {
		$tags->add_class( $class_name );
	}
	return $tags->get_updated_html();
}

/**
 * Add the tape-label class to the plugin's caption.
 *
 * @param string $html Card fragment.
 * @return string
 */
function add_label_class( string $html ): string {
	$tags  = new \WP_HTML_Tag_Processor( $html );
	$query = array(
		'tag_name'   => 'div',
		'class_name' => 'pk-caption',
	);
	if ( ! $tags->next_tag( $query ) ) {
		return $html;
	}
	$tags->add_class( 'cr-shelf-tape__label' );
	return $tags->get_updated_html();
}

/**
 * Insert content immediately after the first article opening tag.
 *
 * @param string $html  Card fragment.
 * @param string $child Markup to insert.
 * @return string
 */
function prepend_article_child( string $html, string $child ): string {
	if ( ! preg_match( '/<article\b[^>]*>/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$at = (int) $match[0][1] + strlen( $match[0][0] );
	return substr( $html, 0, $at ) . $child . substr( $html, $at );
}

/**
 * Turn the styled plugin card into one archive tape.
 *
 * @param string $html     Rendered Stream card.
 * @param array  $block    Parsed Stream-card block.
 * @param mixed  $instance Block instance with post context.
 * @return string
 */
function tape_card( string $html, array $block, $instance ): string {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! preg_match( '/(?:^|\s)' . preg_quote( CARD_STYLE, '/' ) . '(?:\s|$)/', $class_name ) ) {
		return $html;
	}
	$post_id = is_object( $instance ) && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	if ( 0 >= $post_id || ! has_term( KIND, 'kind', $post_id ) ) {
		return $html;
	}
	if ( ! function_exists( '\\PKIW\\kind_facts' ) || ! function_exists( '\\Courtneyr\\Child\\MediaShelf\\cut_elements' ) ) {
		return $html;
	}

	$protected = post_password_required( $post_id );
	$facts     = post_facts( $post_id );
	$count     = &tape_count();
	++$count;

	foreach ( array(
		array( 'div', 'pk-badge' ),
		array( 'span', 'pk-kindlabel' ),
		array( 'p', 'pk-sub' ),
		array( 'div', 'pk-media' ),
		array( 'p', 'pk-excerpt' ),
		array( 'div', 'pk-meta' ),
	) as $cut ) {
		$html = \Courtneyr\Child\MediaShelf\cut_elements( $html, $cut[0], $cut[1] );
	}

	$picture = '';
	$runtime = '';
	if ( ! $protected && ! empty( $facts ) ) {
		$name       = (string) ( $facts['name'] ?? '' );
		$duplicates = page_duplicate_names();
		$date       = isset( $duplicates[ $name ] ) && '' !== (string) ( $facts['date_display'] ?? '' )
			? '<span class="pk-sr-only">, ' . esc_html( (string) $facts['date_display'] ) . '</span>'
			: '';
		$class      = ! empty( $facts['name_is_title'] ) ? 'pk-title p-name' : 'pk-title';
		if ( preg_match( '/<h([1-6])\b[^>]*\bclass="[^"]*(?<![\w-])pk-title(?![\w-])[^"]*"[^>]*>.*?<\/h\1>/is', $html, $heading ) ) {
			$level   = (int) $heading[1];
			$rebuilt = '<h' . $level . ' class="' . $class . '"><a class="u-url" href="' . esc_url( (string) get_permalink( $post_id ) ) . '">'
				. esc_html( $name ) . $date . '</a></h' . $level . '>';
			$html    = (string) preg_replace_callback(
				'/<h[1-6]\b[^>]*\bclass="[^"]*(?<![\w-])pk-title(?![\w-])[^"]*"[^>]*>.*?<\/h[1-6]>/is',
				static fn(): string => $rebuilt,
				$html,
				1
			);
		}
		$picture = tape_picture( $facts, $count <= FIRST_ROW );
		$runtime = runtime_time( $facts );
	}

	if ( ! preg_match( '/<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-caption(?![\w-])[^"]*"[^>]*>/i', $html ) ) {
		$html = (string) preg_replace(
			'/(<h[1-6]\b[^>]*\bclass="[^"]*(?<![\w-])pk-title(?![\w-])[^"]*"[^>]*>.*?<\/h[1-6]>)/is',
			'<div class="pk-body"><div class="pk-caption cr-shelf-tape__label">$1</div></div>',
			$html,
			1
		);
	} else {
		$html = add_label_class( $html );
	}
	if ( '' !== $runtime ) {
		$html = (string) preg_replace( '/(<\/h[1-6]>)/i', '$1' . $runtime, $html, 1 );
	}

	$classes = array( 'cr-shelf-tape' );
	if ( $protected ) {
		$classes[] = 'cr-shelf-tape--protected';
	}
	$html  = add_article_classes( $html, $classes );
	$shell = '' !== $picture
		? '<div class="cr-shelf-tape__shell" aria-hidden="true"><span class="cr-shelf-tape__reel"></span><span class="cr-shelf-tape__reel"></span>'
			. '<span class="cr-shelf-tape__window">' . $picture . '</span><span class="cr-shelf-tape__strip"></span></div>'
		: '<div class="cr-shelf-tape__shell cr-shelf-tape__shell--reels" aria-hidden="true"><span class="cr-shelf-tape__reel"></span><span class="cr-shelf-tape__reel"></span></div>';
	return prepend_article_child( $html, $shell );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\tape_card', 10, 3 );
