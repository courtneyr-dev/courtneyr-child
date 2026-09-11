<?php
/**
 * Mood pins: the mood-card's emoji and mood word printed on a pin-back
 * button, on the single and on /stream.
 *
 * The plugin's mood-card block stores the mood as free text (`mood`) and a
 * Unicode emoji (`emoji`). Both of its renderers — render.php on the single
 * (and for a card-only micro-post on /stream) and the generic stream card —
 * emit one `<span class="pk-mood__emoji">`. This module swaps that span for
 * the pin: the mood word looked up in the theme's catalog
 * (assets/data/moods.json — 194 moods, each with a Twemoji-derived SVG in
 * assets/svg/emoji/, a pattern seed and a palette), with a readable fallback
 * for any mood the catalog doesn't know. Storage is untouched: the block
 * keeps its attributes and their meaning. cr-mood-pin.css paints the pin.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\MoodPin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLOCK = 'post-kinds-indieweb/mood-card';

/** Catalog pattern seeds; a custom mood draws one by hash. */
const PATTERNS = array( 'dots', 'corner-stripe', 'diagonal-lines', 'top-halftone', 'rays', 'edge-dots' );

/** The four locked rotation tokens (tokens.css --cr-rotate-*). */
const TILTS = array( '-1.2deg', '1.5deg', '-2.5deg', '2.8deg' );

/** A custom mood prints on light gray with violet ink and one of these accents. */
const DEFAULT_PALETTE = array(
	'face'      => '#ebebeb',
	'ink'       => '#241c4a',
	'accent'    => '#8ecae6',
	'labelText' => '#241c4a',
);
const ACCENTS         = array( '#8ecae6', '#fb8500', '#fee2c3', '#bcb5e3', '#ffb703' );

/** Longest word printed on the face (the catalog's longest is 13); longer words sit under the pin. */
const ON_FACE_MAX = 13;

/**
 * The mood catalog, keyed by normalized label, plus an emoji → asset map so
 * a custom mood that picked a catalog emoji still prints the artwork.
 *
 * @return array{by_label: array<string, array<string, mixed>>, by_emoji: array<string, string>}
 */
function catalog(): array {
	static $catalog = null;
	if ( null !== $catalog ) {
		return $catalog;
	}
	$catalog = array(
		'by_label' => array(),
		'by_emoji' => array(),
	);
	$file    = COURTNEYR_CHILD_DIR . '/assets/data/moods.json';
	$json    = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-bundled data file.
	$data    = '' !== $json ? json_decode( $json, true ) : null;
	foreach ( (array) ( $data['entries'] ?? array() ) as $entry ) {
		if ( empty( $entry['label'] ) || empty( $entry['asset'] ) ) {
			continue;
		}
		$entry['palette'] = array_map( 'strtolower', array_merge( DEFAULT_PALETTE, (array) ( $entry['palette'] ?? array() ) ) );
		$key              = normalize_label( (string) $entry['label'] );
		$catalog['by_label'][ $key ] = $entry;
		$emoji = (string) ( $entry['emoji'] ?? '' );
		if ( '' !== $emoji && ! isset( $catalog['by_emoji'][ $emoji ] ) ) {
			$catalog['by_emoji'][ $emoji ] = (string) $entry['asset'];
		}
	}
	return $catalog;
}

/**
 * A mood string as the catalog keys it: tags and entities gone, whitespace
 * collapsed, lowercase. "In Love" and "in love" are the same mood.
 *
 * @param string $mood Saved mood text.
 * @return string
 */
function normalize_label( string $mood ): string {
	$mood = wp_strip_all_tags( html_entity_decode( $mood, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	$mood = (string) preg_replace( '/\s+/u', ' ', $mood );
	return mb_strtolower( trim( $mood ) );
}

/**
 * What to print for a saved mood + emoji.
 *
 * A catalog mood prints its own artwork, pattern and palette. Anything else
 * prints the saved word as typed (lowercased) with the saved emoji — as the
 * catalog's SVG when that emoji is in the catalog, as a text glyph otherwise —
 * on the default palette with a hashed accent, pattern and tilt, so the same
 * custom mood always looks the same.
 *
 * @param string $mood  Saved mood text.
 * @param string $emoji Saved emoji.
 * @return array{label: string, asset: string, art: string, glyph: string, pattern: string, palette: array<string, string>, custom: bool}
 */
function resolve( string $mood, string $emoji ): array {
	$label   = normalize_label( $mood );
	$emoji   = trim( wp_strip_all_tags( $emoji ) );
	$catalog = catalog();
	$entry   = $catalog['by_label'][ $label ] ?? null;
	if ( null !== $entry ) {
		return array(
			'label'   => (string) $entry['label'],
			'asset'   => (string) $entry['asset'],
			'art'     => (string) ( $entry['art'] ?? 'motif' ),
			'glyph'   => '',
			'pattern' => in_array( $entry['pattern'] ?? '', PATTERNS, true ) ? (string) $entry['pattern'] : PATTERNS[0],
			'palette' => $entry['palette'],
			'custom'  => false,
		);
	}
	$seed              = crc32( $label . '|' . $emoji );
	$asset             = $catalog['by_emoji'][ $emoji ] ?? '';
	$palette           = DEFAULT_PALETTE;
	$palette['accent'] = ACCENTS[ $seed % count( ACCENTS ) ];
	return array(
		'label'   => $label,
		'asset'   => $asset,
		'art'     => 'face',
		'glyph'   => '' === $asset ? $emoji : '',
		'pattern' => PATTERNS[ ( $seed >> 3 ) % count( PATTERNS ) ],
		'palette' => $palette,
		'custom'  => true,
	);
}

/**
 * The emoji artwork as inline SVG, printed in the pin's inks.
 *
 * The bundled SVGs already lost their yellow face disk (assets/svg/emoji/
 * README.md). Here the Russian violet features take the pin's ink, and on a
 * pin whose ink is light gray the artwork's light gray takes the accent so
 * whites and features stay two inks. Anything else keeps its palette color;
 * an element the color of the face simply doesn't print, the way a
 * screen-printed knock-out reads.
 *
 * @param string                $asset   File name from the catalog.
 * @param array<string, string> $palette Pin palette.
 * @return string Inline SVG, or '' when the asset is unavailable.
 */
function art( string $asset, array $palette ): string {
	static $cache = array();
	$name = basename( $asset );
	$key  = $name . '|' . implode( ',', $palette );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$file = COURTNEYR_CHILD_DIR . '/assets/svg/emoji/' . $name;
	if ( ! preg_match( '/^[0-9a-f-]+\.svg$/', $name ) || ! is_readable( $file ) ) {
		$cache[ $key ] = '';
		return '';
	}
	$svg = trim( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-bundled asset.
	$map = array( '#241c4a' => $palette['ink'] );
	if ( '#ebebeb' === $palette['ink'] && $palette['accent'] !== $palette['ink'] ) {
		$map['#ebebeb'] = $palette['accent'];
	}
	$svg           = strtr( $svg, $map );
	$svg           = (string) preg_replace( '/<svg\b/', '<svg class="cr-pin__art" aria-hidden="true" focusable="false"', $svg, 1 );
	$cache[ $key ] = $svg;
	return $svg;
}

/**
 * The pin.
 *
 * Reading order carries the mood word once, as real text; every other layer
 * (rim, artwork, pattern, gloss, wear) is decorative and hidden from
 * assistive tech. A saved emoji with no artwork prints as a text glyph, and
 * that glyph stays exposed when there is no word to name the mood.
 *
 * @param array<string, mixed> $spec From resolve().
 * @param string               $size 'single' (12rem) or 'stream' (8rem).
 * @return string
 */
function render( array $spec, string $size ): string {
	$p = $spec['palette'];
	foreach ( $p as $k => $v ) {
		if ( ! preg_match( '/^#[0-9a-f]{6}$/', (string) $v ) ) {
			$p[ $k ] = DEFAULT_PALETTE[ $k ] ?? '#241c4a';
		}
	}
	$label   = (string) $spec['label'];
	$seed    = crc32( $label . '|' . $spec['glyph'] . '|' . $spec['asset'] );
	$on_face = '' !== $label && mb_strlen( $label ) <= ON_FACE_MAX;
	$size    = 'single' === $size ? 'single' : 'stream';

	$classes = array( 'cr-pin', 'cr-pin--' . $size, 'cr-pin--p-' . $spec['pattern'], 'cr-pin--art-' . ( 'face' === $spec['art'] ? 'face' : 'motif' ) );
	if ( ! empty( $spec['custom'] ) ) {
		$classes[] = 'cr-pin--custom';
	}
	$style = sprintf(
		'--pin-face:%s;--pin-ink:%s;--pin-accent:%s;--pin-label:%s;--pin-tilt:%s;--pin-wear:%ddeg',
		$p['face'],
		$p['ink'],
		$p['accent'],
		$p['labelText'],
		TILTS[ $seed % count( TILTS ) ],
		$seed % 360
	);

	$art = '' !== $spec['asset'] ? art( (string) $spec['asset'], $p ) : '';
	if ( '' === $art && '' !== $spec['glyph'] ) {
		$art = '<span class="cr-pin__glyph"' . ( '' !== $label ? ' aria-hidden="true"' : '' ) . '>' . esc_html( (string) $spec['glyph'] ) . '</span>';
	}
	$word = '' !== $label ? esc_html( $label ) : '';

	$html  = '<span class="cr-pin-set cr-pin-set--' . $size . '">';
	$html .= '<span class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '">';
	$html .= '<span class="cr-pin__rim" aria-hidden="true"></span>';
	$html .= '<span class="cr-pin__face">' . $art . '<span class="cr-pin__pattern" aria-hidden="true"></span><span class="cr-pin__gloss" aria-hidden="true"></span></span>';
	if ( $on_face ) {
		$html .= '<span class="cr-pin__word">' . $word . '</span>';
	}
	$html .= '<span class="cr-pin__wear" aria-hidden="true"></span>';
	$html .= '</span>';
	if ( '' !== $word && ! $on_face ) {
		$html .= '<span class="cr-pin__word cr-pin__word--below">' . $word . '</span>';
	}
	$html .= '</span>';
	return $html;
}

/**
 * Swap the plugin's emoji span for the pin; with no span (a card saved with
 * an empty emoji), open the .pk-mood box with the pin instead.
 *
 * @param string $html Card HTML.
 * @param string $pin  From render().
 * @return string
 */
function replace_emoji_span( string $html, string $pin ): string {
	$count = 0;
	$out   = (string) preg_replace_callback(
		'/<span class="pk-mood__emoji"[^>]*>.*?<\/span>/su',
		static function () use ( $pin ): string {
			return $pin;
		},
		$html,
		1,
		$count
	);
	if ( 0 === $count ) {
		$out = (string) preg_replace( '/(<div class="pk-mood">)/', '$1' . str_replace( '$', '\\$', $pin ), $html, 1 );
	}
	return $out;
}

/**
 * The first mood-card block in a post body, anywhere in the tree.
 *
 * @param \WP_Post $post Post.
 * @return array<string, mixed>|null Parsed block.
 */
function find_mood_block( \WP_Post $post ): ?array {
	if ( false === strpos( (string) $post->post_content, BLOCK ) ) {
		return null;
	}
	$found = null;
	$walk  = static function ( array $blocks ) use ( &$walk, &$found ): void {
		foreach ( $blocks as $block ) {
			if ( null !== $found ) {
				return;
			}
			if ( BLOCK === ( $block['blockName'] ?? '' ) ) {
				$found = $block;
				return;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( (array) $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ) );
	return $found;
}

/**
 * The mood-card block itself: the single, and a card-only micro-post on
 * /stream. A note that only repeats the mood word is flagged so the
 * stream, where the word is on the pin, can hide it; the single keeps it as
 * the entry's content.
 *
 * @param string $html  Rendered block.
 * @param array  $block Parsed block.
 * @return string
 */
function mood_card( string $html, array $block ): string {
	if ( is_feed() || false === strpos( $html, 'pk-mood' ) ) {
		return $html;
	}
	$attrs = (array) ( $block['attrs'] ?? array() );
	$mood  = (string) ( $attrs['mood'] ?? '' );
	$spec  = resolve( $mood, (string) ( $attrs['emoji'] ?? '' ) );
	$html  = replace_emoji_span( $html, render( $spec, is_singular( 'post' ) ? 'single' : 'stream' ) );

	$note = (string) ( $attrs['note'] ?? '' );
	if ( '' !== $note && normalize_label( $note ) === normalize_label( $mood ) ) {
		$html = (string) preg_replace( '/<p class="pk-mood__note p-content">/', '<p class="pk-mood__note p-content cr-pin-note-dup">', $html, 1 );
	}
	return $html;
}
add_filter( 'render_block_' . BLOCK, __NAMESPACE__ . '\\mood_card', 10, 2 );

/**
 * The plugin's generic stream card for a long-form mood post: it carries
 * the emoji but not the word, so read the word from the post's block.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block (unused).
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function stream_card( string $html, array $block, $instance ): string {
	if ( is_feed() || false === strpos( $html, 'pk-mood__emoji' ) ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'mood', 'kind', $post ) ) {
		return $html;
	}
	$attrs = (array) ( ( find_mood_block( $post ) ?? array() )['attrs'] ?? array() );
	$emoji = (string) ( $attrs['emoji'] ?? '' );
	if ( '' === $emoji && preg_match( '/<span class="pk-mood__emoji"[^>]*>(.*?)<\/span>/su', $html, $m ) ) {
		$emoji = (string) $m[1];
	}
	return replace_emoji_span( $html, render( resolve( (string) ( $attrs['mood'] ?? '' ), $emoji ), 'stream' ) );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stream_card', 10, 3 );

/**
 * The pin's stylesheet, loaded only where a mood card or a stream card
 * renders (same on-demand discipline as cr-post-kinds.css).
 */
function register_style(): void {
	$file = COURTNEYR_CHILD_DIR . '/assets/css/cr-mood-pin.css';
	if ( ! is_readable( $file ) ) {
		return;
	}
	foreach ( array( BLOCK, 'post-kinds-indieweb/stream-card' ) as $block_name ) {
		wp_enqueue_block_style(
			$block_name,
			array(
				'handle' => 'courtneyr-mood-pin',
				'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-mood-pin.css',
				'path'   => $file,
				'ver'    => COURTNEYR_CHILD_VERSION,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_style' );

/**
 * Editor: a searchable "Pick a mood" control on the mood-card block that
 * sets the block's own `mood` and `emoji` attributes, and a live pin
 * preview rendered by the same PHP as the front end (via the REST route
 * below). The plugin's free-text Mood field stays for custom moods.
 */
function enqueue_editor(): void {
	wp_enqueue_style( 'courtneyr-mood-pin', COURTNEYR_CHILD_URI . '/assets/css/cr-mood-pin.css', array(), COURTNEYR_CHILD_VERSION );
	wp_enqueue_script(
		'courtneyr-mood-pin-editor',
		COURTNEYR_CHILD_URI . '/assets/js/cr-mood-pin-editor.js',
		array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch', 'wp-i18n' ),
		COURTNEYR_CHILD_VERSION,
		true
	);
	$moods = array();
	foreach ( catalog()['by_label'] as $entry ) {
		$moods[] = array(
			'label' => (string) $entry['label'],
			'emoji' => (string) $entry['emoji'],
		);
	}
	wp_add_inline_script( 'courtneyr-mood-pin-editor', 'window.courtneyrMoodPins = ' . wp_json_encode( array( 'moods' => $moods ) ) . ';', 'before' );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor' );

/**
 * GET /courtneyr/v1/mood-pin?mood=&emoji= → { html } for the editor preview.
 */
function register_rest_route(): void {
	\register_rest_route(
		'courtneyr/v1',
		'/mood-pin',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'mood'  => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'emoji' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'callback'            => static function ( \WP_REST_Request $request ): array {
				$spec = resolve( (string) $request['mood'], (string) $request['emoji'] );
				return array(
					'html'   => render( $spec, 'single' ),
					'custom' => (bool) $spec['custom'],
				);
			},
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_route' );
