<?php
/**
 * Drawn comic strip archive, single and Stream presentation.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\ComicStrip;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PANELS_STYLE = 'cr-comic-panels';
const PAGE_STYLE   = 'cr-sketchbook-strip';
const TILTS        = array( '--cr-rotate-1', '--cr-rotate-2', '--cr-rotate-neg', '--cr-rotate-neg2' );
const STYLE_HANDLE = 'courtneyr-comic-strip';

/**
 * Register the comic panel and sketchbook block styles.
 */
function register_styles(): void {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	$inline = '.wp-block-gallery.is-style-cr-comic-panels{counter-reset:cr-comic-panel}.wp-block-gallery.is-style-cr-comic-panels .wp-block-image{position:relative;border:2px solid var(--cr-ink);counter-increment:cr-comic-panel}.wp-block-gallery.is-style-cr-comic-panels .wp-block-image::before{content:counter(cr-comic-panel);position:absolute;inset-block-start:calc(-1 * var(--cr-space-sm));inset-inline-start:0;display:grid;place-items:center;min-width:2rem;min-height:2rem;border-radius:0;background:var(--cr-selective-yellow);color:var(--cr-russian-violet)}.wp-block-gallery.is-style-cr-comic-panels figcaption{font-size:var(--cr-text-sm)}';
	register_block_style(
		'core/gallery',
		array(
			'name'         => PANELS_STYLE,
			'label'        => __( 'Comic panels', 'courtneyr-child' ),
			'inline_style' => $inline,
		)
	);
	register_block_style(
		'post-kinds-indieweb/stream-card',
		array(
			'name'  => PAGE_STYLE,
			'label' => __( 'Sketchbook strip', 'courtneyr-child' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_styles' );

/**
 * Add the theme's gallery style to the plugin's strip selectors.
 *
 * @param mixed $styles Existing style slugs.
 * @return string[] Comic strip style slugs.
 */
function strip_styles( $styles ): array {
	$styles = is_array( $styles ) ? $styles : array();
	if ( ! in_array( PANELS_STYLE, $styles, true ) ) {
		$styles[] = PANELS_STYLE;
	}
	return $styles;
}
add_filter( 'pkiw_comic_strip_styles', __NAMESPACE__ . '\\strip_styles' );

/**
 * Public drawn-comic facts for a post.
 *
 * @param \WP_Post $post Post to inspect.
 * @return array<string, mixed> Drawn comic facts, or an empty array.
 */
function facts( \WP_Post $post ): array {
	static $cache      = array();
	static $generation = -1;

	$current_generation = (int) ( $GLOBALS['courtneyr_child_comic_strip_cache_generation'] ?? 0 );
	if ( $generation !== $current_generation ) {
		$cache      = array();
		$generation = $current_generation;
	}
	if ( array_key_exists( $post->ID, $cache ) ) {
		return $cache[ $post->ID ];
	}
	if ( ! \has_term( 'comics', 'kind', $post ) || ! function_exists( '\\PKIW\\kind_facts' ) ) {
		$cache[ $post->ID ] = array();
		return $cache[ $post->ID ];
	}

	$found              = \PKIW\kind_facts( $post->ID );
	$cache[ $post->ID ] = is_array( $found ) && ! empty( $found['drawn'] ) && is_array( $found['panels'] ?? null )
		? $found
		: array();
	return $cache[ $post->ID ];
}

/**
 * Reset request-local comic facts and print-once state for tests.
 */
function reset_facts_cache(): void {
	$GLOBALS['courtneyr_child_comic_strip_cache_generation'] = 1 + (int) ( $GLOBALS['courtneyr_child_comic_strip_cache_generation'] ?? 0 );
	$GLOBALS['courtneyr_child_comic_strip_rendered']         = false;
}

/**
 * Whether the current request is the drawn comics archive view.
 */
function is_drawn_view(): bool {
	return \is_tax( 'kind', 'comics' ) && 'drawn' === \get_query_var( 'pkiw_comic_view' );
}

/**
 * Whether the queried post is an unprotected drawn comic single.
 */
function is_strip_single(): bool {
	if ( ! \is_singular( 'post' ) ) {
		return false;
	}
	$post = \get_post( \get_queried_object_id() );
	return $post instanceof \WP_Post && ! \post_password_required( $post ) && array() !== facts( $post );
}

/**
 * Stable tape tilt token for a post.
 *
 * @param int $post_id Post ID.
 */
function tilt( int $post_id ): string {
	return TILTS[ \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( 'comic-strip', (string) $post_id ), 3, count( TILTS ) ) ];
}

/**
 * Series and issue label from public comic facts.
 *
 * @param array<string, mixed> $comic_facts Comic facts.
 */
function series_tag( array $comic_facts ): string {
	$series = trim( (string) ( $comic_facts['series'] ?? '' ) );
	$issue  = trim( (string) ( $comic_facts['issue'] ?? '' ) );
	if ( '' !== $series && '' !== $issue ) {
		return $series . ' #' . $issue;
	}
	if ( '' !== $series ) {
		return $series;
	}
	return '' !== $issue ? '#' . $issue : '';
}

/**
 * Let the strip spread own the single page H1.
 *
 * @param mixed         $holds Whether another object already owns the H1.
 * @param \WP_Post|null $post  Post being rendered.
 */
function holds_h1( $holds, $post = null ): bool {
	return true === $holds || ( $post instanceof \WP_Post && \get_queried_object_id() === $post->ID && is_strip_single() );
}
add_filter( 'courtneyr_child_single_object_holds_h1', __NAMESPACE__ . '\\holds_h1', 10, 2 );

/**
 * Normalize a space-separated class list without a regular expression.
 *
 * @param string $classes Class list.
 * @return string[] Class tokens.
 */
function class_tokens( string $classes ): array {
	$classes = str_replace( array( "\t", "\n", "\r", "\f", "\v" ), ' ', $classes );
	while ( str_contains( $classes, '  ' ) ) {
		$classes = str_replace( '  ', ' ', $classes );
	}
	return array_values( array_filter( explode( ' ', trim( $classes ) ), static fn( string $token ): bool => '' !== $token ) );
}

/**
 * Image source from a saved Image block.
 *
 * @param array<string, mixed> $block Parsed Image block.
 */
function block_image_src( array $block ): string {
	$html = is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '';
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'tag_name' => 'img' ) ) ) {
		$src = $tags->get_attribute( 'src' );
		return is_string( $src ) ? $src : '';
	}
	return '';
}

/**
 * Ordered attachment/source signature for a rendered Gallery or Image block.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return string[] Ordered image signature.
 */
function block_signature( array $block ): array {
	$name = (string) ( $block['blockName'] ?? '' );
	if ( 'core/image' === $name ) {
		$images = array( $block );
	} elseif ( 'core/gallery' === $name ) {
		$images = array_values(
			array_filter(
				is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array(),
				static fn( $image ): bool => is_array( $image ) && 'core/image' === ( $image['blockName'] ?? '' )
			)
		);
	} else {
		return array();
	}

	$signature = array();
	foreach ( $images as $image ) {
		$id          = (int) ( $image['attrs']['id'] ?? 0 );
		$signature[] = $id > 0 ? 'id:' . $id : 'src:' . block_image_src( $image );
	}
	return $signature;
}

/**
 * Ordered attachment/source signature from public panel facts.
 *
 * @param array<int, array<string, mixed>> $panels Panel facts.
 * @return string[] Ordered image signature.
 */
function panel_signature( array $panels ): array {
	$signature = array();
	foreach ( $panels as $panel ) {
		$id          = (int) ( $panel['attachment_id'] ?? 0 );
		$signature[] = $id > 0 ? 'id:' . $id : 'src:' . (string) ( $panel['src'] ?? '' );
	}
	return $signature;
}

/**
 * Render one panel image from facts.
 *
 * @param array<string, mixed> $panel Panel facts.
 * @param string               $size  WordPress image size.
 * @param bool                 $eager Whether to prioritize this image.
 */
function panel_image( array $panel, string $size, bool $eager = false ): string {
	$id    = (int) ( $panel['attachment_id'] ?? 0 );
	$alt   = (string) ( $panel['alt'] ?? '' );
	$attrs = array(
		'alt'   => $alt,
		'class' => 'u-photo',
	);
	if ( $eager ) {
		$attrs['loading']       = 'eager';
		$attrs['fetchpriority'] = 'high';
	}
	if ( $id > 0 ) {
		return (string) \wp_get_attachment_image( $id, $size, false, $attrs );
	}

	$loading = $eager ? ' loading="eager" fetchpriority="high"' : '';
	return '<img class="u-photo" src="' . \esc_url( (string) ( $panel['src'] ?? '' ) ) . '" alt="' . \esc_attr( $alt ) . '"' . $loading . '>';
}

/**
 * Render the drawn comic spread in place of its saved Gallery or Image block.
 *
 * @param string $html     Rendered block HTML.
 * @param array  $block    Parsed block.
 * @param mixed  $instance Block instance.
 */
function strip_page( string $html, array $block, $instance = null ): string {
	unset( $instance );
	if ( ! is_strip_single() || ! empty( $GLOBALS['courtneyr_child_comic_strip_rendered'] ) ) {
		return $html;
	}
	$post = \get_post( \get_queried_object_id() );
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$comic_facts = facts( $post );
	$panels      = is_array( $comic_facts['panels'] ?? null ) ? $comic_facts['panels'] : array();
	if ( panel_signature( $panels ) !== block_signature( $block ) ) {
		return $html;
	}
	$GLOBALS['courtneyr_child_comic_strip_rendered'] = true;

	$title       = (string) \get_the_title( $post );
	$title_class = '' !== trim( $title ) ? ' p-name' : '';
	$title       = '' !== trim( $title ) ? $title : __( 'Untitled comic', 'courtneyr-child' );
	$date_iso    = (string) \get_the_date( 'c', $post );
	$date        = (string) \get_the_date( '', $post );
	$tag         = series_tag( $comic_facts );
	$series      = trim( (string) ( $comic_facts['series'] ?? '' ) );

	$out  = '<div class="cr-strip cr-strip--single alignwide" style="--cr-strip-tilt:var(' . \esc_attr( tilt( $post->ID ) ) . ')">';
	$out .= '<header class="cr-strip__head"><h1 class="cr-strip__title' . $title_class . '">' . \esc_html( $title ) . '</h1>';
	$out .= '<dl class="cr-strip__record"><div class="cr-strip__row"><dt>' . \esc_html__( 'Date', 'courtneyr-child' ) . '</dt><dd><time class="dt-published" datetime="' . \esc_attr( $date_iso ) . '">' . \esc_html( $date ) . '</time></dd></div>';
	if ( '' !== $tag ) {
		$value = \esc_html( $tag );
		if ( '' !== $series && class_exists( '\\PKIW\\Comic_Strip' ) && method_exists( '\\PKIW\\Comic_Strip', 'series_url' ) ) {
			$url = (string) \PKIW\Comic_Strip::series_url( $series );
			if ( '' !== $url ) {
				$value = '<a href="' . \esc_url( $url ) . '">' . $value . '</a>';
			}
		}
		$out .= '<div class="cr-strip__row"><dt>' . \esc_html__( 'Series', 'courtneyr-child' ) . '</dt><dd>' . $value . '</dd></div>';
	}
	$out       .= '</dl></header><ol class="cr-strip__panels" role="list">';
	$transcript = '';
	foreach ( $panels as $index => $panel ) {
		$position = $index + 1;
		$out     .= '<li class="cr-strip__panel"><span class="cr-strip__tab"><span class="screen-reader-text">' . \esc_html__( 'Panel ', 'courtneyr-child' ) . '</span>' . $position . '</span><figure class="cr-strip__figure">' . panel_image( $panel, 'large', 0 === $index ) . '</figure></li>';
		$text     = trim( (string) ( $panel['transcript'] ?? '' ) );
		if ( '' !== $text ) {
			/* translators: %d: panel number. */
			$transcript .= '<div class="cr-strip__entry"><dt>' . sprintf( \esc_html__( 'Panel %d', 'courtneyr-child' ), $position ) . '</dt><dd>' . \esc_html( $text ) . '</dd></div>';
		}
	}
	$out .= '</ol>';
	if ( '' !== $transcript ) {
		$out .= '<section class="cr-strip__transcript"><h2 class="cr-strip__transcript-title">' . \esc_html__( 'Transcript', 'courtneyr-child' ) . '</h2><dl class="cr-strip__entries">' . $transcript . '</dl></section>';
	}
	return $out . '</div>';
}
add_filter( 'render_block_core/gallery', __NAMESPACE__ . '\\strip_page', 12, 3 );
add_filter( 'render_block_core/image', __NAMESPACE__ . '\\strip_page', 12, 3 );

/**
 * Post for a rendered stream-card block.
 *
 * @param mixed $instance Block instance.
 */
function block_post( $instance ): ?\WP_Post {
	$post_id = is_object( $instance ) && isset( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: (int) \get_the_ID();
	$post    = $post_id > 0 ? \get_post( $post_id ) : null;
	return $post instanceof \WP_Post ? $post : null;
}

/**
 * Heading level from a stream-card block.
 *
 * @param array<string, mixed> $block Parsed block.
 */
function heading_level( array $block ): int {
	return max( 2, min( 4, (int) ( $block['attrs']['headingLevel'] ?? 3 ) ) );
}

/**
 * Whether archive panels in this loop row should load eagerly.
 */
function archive_eager(): bool {
	return ! \is_paged() && \in_the_loop() && isset( $GLOBALS['wp_query']->current_post ) && (int) $GLOBALS['wp_query']->current_post < 2;
}

/**
 * Replace a styled stream card with a sketchbook archive item.
 *
 * @param string $html     Rendered card.
 * @param array  $block    Parsed block.
 * @param mixed  $instance Block instance.
 */
function sketchbook_item( string $html, array $block, $instance = null ): string {
	$tokens = class_tokens( (string) ( $block['attrs']['className'] ?? '' ) );
	if ( ! in_array( 'is-style-' . PAGE_STYLE, $tokens, true ) ) {
		return $html;
	}
	$post = block_post( $instance );
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$comic_facts = facts( $post );
	if ( array() === $comic_facts ) {
		return $html;
	}

	$panels = array_slice( is_array( $comic_facts['panels'] ?? null ) ? $comic_facts['panels'] : array(), 0, 2 );
	$media  = '';
	if ( array() !== $panels ) {
		$images = '';
		foreach ( $panels as $panel ) {
			$images .= panel_image( $panel, 'medium_large', archive_eager() );
		}
		$media = '<div class="cr-sketch-item__panels cr-sketch-item__panels--' . count( $panels ) . '">' . $images . '</div>';
	}

	$level    = heading_level( $block );
	$title    = trim( (string) \get_the_title( $post ) );
	$title    = '' !== $title ? $title : __( 'Untitled comic', 'courtneyr-child' );
	$title_id = \wp_unique_id( 'cr-sk-title-' );
	$tag      = series_tag( $comic_facts );
	$tag_id   = '' !== $tag ? \wp_unique_id( 'cr-sk-tag-' ) : '';
	$labelled = '' !== $tag_id ? ' aria-labelledby="' . \esc_attr( $title_id . ' ' . $tag_id ) . '"' : '';
	$anchor   = '<a class="u-url" href="' . \esc_url( (string) \get_permalink( $post ) ) . '" id="' . \esc_attr( $title_id ) . '"' . $labelled . '>' . \esc_html( $title ) . '</a>';
	$tag_html = '' !== $tag ? '<p class="cr-sketch-item__tag" id="' . \esc_attr( $tag_id ) . '">' . \esc_html( $tag ) . '</p>' : '';

	return '<article class="pk-card pk-card--stream k-comics h-entry cr-sketch-item" style="--cr-strip-tilt:var(' . \esc_attr( tilt( $post->ID ) ) . ')">' . $media . '<div class="cr-sketch-item__body"><h' . $level . ' class="pk-title p-name">' . $anchor . '</h' . $level . '>' . $tag_html . '</div></article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\sketchbook_item', 11, 3 );

/**
 * Kind label for a drawn comic Stream card.
 *
 * @param \WP_Post $post Comic post.
 */
function kind_label( \WP_Post $post ): string {
	if ( function_exists( '\\PKIW\\stream_card_kind_label' ) && function_exists( '\\PKIW\\get_kind_label' ) ) {
		return (string) \PKIW\get_kind_label( \PKIW\stream_card_kind_label( $post ), 'comics', 'stream-card' );
	}
	$term = function_exists( 'get_term_by' ) ? \get_term_by( 'slug', 'comics', 'kind' ) : false;
	return is_object( $term ) && isset( $term->name ) && '' !== trim( (string) $term->name )
		? (string) $term->name
		: __( 'Comics', 'courtneyr-child' );
}

/**
 * Replace a generic drawn-comic Stream card with the strip card.
 *
 * @param string $html     Rendered card.
 * @param array  $block    Parsed block.
 * @param mixed  $instance Block instance.
 */
function stream_strip( string $html, array $block, $instance = null ): string {
	$stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) && \Courtneyr\Child\HomeSections\is_stream_surface();
	if ( ! $stream || in_array( 'is-style-' . PAGE_STYLE, class_tokens( (string) ( $block['attrs']['className'] ?? '' ) ), true ) ) {
		return $html;
	}
	$post = block_post( $instance );
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}
	$comic_facts = facts( $post );
	if ( array() === $comic_facts ) {
		return $html;
	}

	$panels = is_array( $comic_facts['panels'] ?? null ) ? $comic_facts['panels'] : array();
	$frame  = array() !== $panels
		? '<div class="cr-strip-card__frame"><span class="cr-strip__tab" aria-hidden="true">1</span>' . panel_image( $panels[0], 'medium_large' ) . '</div>'
		: '';
	$level  = heading_level( $block );
	$title  = trim( (string) \get_the_title( $post ) );
	$title  = '' !== $title ? $title : __( 'Untitled comic', 'courtneyr-child' );
	$tag    = series_tag( $comic_facts );
	$date   = (string) \get_the_date( '', $post );
	$iso    = (string) \get_the_date( 'c', $post );
	$text   = array() !== $panels ? trim( (string) ( $panels[0]['transcript'] ?? '' ) ) : '';

	$out  = '<article class="pk-card pk-card--stream k-comics h-entry cr-strip-card" style="--cr-strip-tilt:var(' . \esc_attr( tilt( $post->ID ) ) . ')">' . $frame;
	$out .= '<span class="pk-kindlabel">' . \esc_html( kind_label( $post ) ) . '</span>';
	$out .= '<h' . $level . ' class="pk-title p-name"><a class="u-url" href="' . \esc_url( (string) \get_permalink( $post ) ) . '">' . \esc_html( $title ) . '</a></h' . $level . '>';
	if ( '' !== $tag ) {
		$out .= '<p class="cr-strip-card__tag">' . \esc_html( $tag ) . '</p>';
	}
	$out .= '<p class="pk-sub pk-stream-date"><time class="dt-published" datetime="' . \esc_attr( $iso ) . '">' . \esc_html( $date ) . '</time></p>';
	if ( '' !== $text ) {
		$out .= '<details class="cr-strip__transcript-mini"><summary>' . \esc_html__( 'Transcript', 'courtneyr-child' ) . '</summary><p>' . \esc_html( $text ) . '</p></details>';
	}
	return $out . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stream_strip', 11, 3 );

/**
 * Register the shared navigation stylesheet if another module has not.
 */
function register_nav_dependency(): void {
	if ( function_exists( 'wp_style_is' ) && \wp_style_is( 'courtneyr-nav', 'registered' ) ) {
		return;
	}
	if ( ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) || ! defined( 'COURTNEYR_CHILD_DIR' ) || ! function_exists( 'wp_register_style' ) ) {
		return;
	}
	\wp_register_style( 'courtneyr-nav', COURTNEYR_CHILD_URI . '/assets/css/cr-nav.css', array(), COURTNEYR_CHILD_VERSION );
	if ( function_exists( 'wp_style_add_data' ) ) {
		\wp_style_add_data( 'courtneyr-nav', 'path', COURTNEYR_CHILD_DIR . '/assets/css/cr-nav.css' );
	}
}

/**
 * Load drawn-comic paint on every surface that can render it.
 */
function enqueue_styles(): void {
	$stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) && \Courtneyr\Child\HomeSections\is_stream_surface();
	if ( ! \is_admin() && ! is_drawn_view() && ! is_strip_single() && ! $stream ) {
		return;
	}
	if ( ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) ) {
		return;
	}
	register_nav_dependency();
	\wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-comic-strip.css',
		array( 'courtneyr-paper', 'courtneyr-nav' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Add a body class for the drawn comic single.
 *
 * @param string[] $classes Body classes.
 * @return string[] Body classes.
 */
function body_class( array $classes ): array {
	if ( is_strip_single() && ! in_array( 'cr-comic-strip-single', $classes, true ) ) {
		$classes[] = 'cr-comic-strip-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );
