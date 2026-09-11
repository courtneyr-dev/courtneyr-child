<?php
/**
 * Mood pins: the mood word and a printed motif on a pin-back button, on
 * the single and on /stream.
 *
 * The plugin's mood-card block stores the mood as free text (`mood`) and a
 * Unicode emoji (`emoji`). Both of its renderers — render.php on the single
 * (and for a card-only micro-post on /stream) and the generic stream card —
 * emit one `<span class="pk-mood__emoji">`. This module swaps that span for
 * the pin. A catalog mood (assets/data/moods.json: 194 moods, each with a
 * family, a motif from inc/mood-motifs.php, a word layout, a face, and a
 * deterministic two-or-three-ink palette) prints as screen-printed art:
 * the motif in two plates, a halftone field, the word set into the
 * composition. Anything off the catalog prints the saved word and, when
 * there is one, the saved emoji as a text glyph. Storage is untouched: the
 * block keeps its attributes and their meaning. cr-mood-pin.css paints the
 * shell (rim, dome, wear, contact shadow).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\MoodPin;

use function Courtneyr\Child\MoodMotifs\motif;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLOCK = 'post-kinds-indieweb/mood-card';

/**
 * Spot-ink combinations: face, line ink (also the word), accent. Every
 * ink-on-face pair clears WCAG AA for the word (checked in the vault note).
 * Indices 0–4 are the brief's examples (curious, nostalgic, productive,
 * melancholy, quixotic); the rest are picked per mood by hash at build time
 * and stored in the catalog, so a mood always prints the same way.
 */
const PALETTES = array(
	array( '#bcb5e3', '#241c4a', '#ffb703' ),
	array( '#023047', '#8ecae6', '#bcb5e3' ),
	array( '#fee2c3', '#241c4a', '#fb8500' ),
	array( '#bcb5e3', '#241c4a', '#647baf' ),
	array( '#ebebeb', '#241c4a', '#fb8500' ),
	array( '#ffb703', '#241c4a', '#ebebeb' ),
	array( '#8ecae6', '#023047', '#fb8500' ),
	array( '#fb8500', '#241c4a', '#fee2c3' ),
	array( '#241c4a', '#ebebeb', '#ffb703' ),
	array( '#126782', '#ebebeb', '#8ecae6' ),
	array( '#219ebc', '#241c4a', '#fee2c3' ),
	array( '#fee2c3', '#023047', '#219ebc' ),
	array( '#bcb5e3', '#023047', '#fb8500' ),
	array( '#ebebeb', '#126782', '#ffb703' ),
	array( '#8ecae6', '#241c4a', '#ebebeb' ),
	array( '#ffb703', '#023047', '#241c4a' ),
);

/** Rim shells (highlight, shadow): violet, Prussian, glaucous, and one light rim. */
const RIMS = array(
	array( '#7a6fb8', '#241c4a' ),
	array( '#5f5389', '#1a1436' ),
	array( '#3a6a86', '#023047' ),
	array( '#4f7f9b', '#022a3d' ),
	array( '#a8b6d8', '#3f5285' ),
	array( '#8ea0cc', '#4a5f96' ),
	array( '#f5f4ef', '#8f86c4' ),
);

/** The four locked rotation tokens (tokens.css --cr-rotate-*). */
const TILTS = array( '-1.2deg', '1.5deg', '-2.5deg', '2.8deg' );

const LAYOUTS = array( 'icon-word', 'icon-arc', 'arc-top', 'word-big', 'stacked', 'crooked' );
const FONTS   = array( 'mono', 'condensed', 'slab', 'hand' );

/** Average advance per glyph, in em, used to fit a word to its band. */
const GLYPH_WIDTH = array(
	'mono'      => 0.74,
	'condensed' => 0.66,
	'slab'      => 0.66,
	'hand'      => 0.92,
);

/** Smallest word size in face units (100 = the face); 10 is 12px on a 7.5rem pin. */
const WORD_MIN = 10.0;

/**
 * The mood catalog, keyed by normalized label.
 *
 * @return array<string, array<string, mixed>>
 */
function catalog(): array {
	static $catalog = null;
	if ( null !== $catalog ) {
		return $catalog;
	}
	$catalog = array();
	$file    = COURTNEYR_CHILD_DIR . '/assets/data/moods.json';
	$json    = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-bundled data file.
	$data    = '' !== $json ? json_decode( $json, true ) : null;
	foreach ( (array) ( $data['entries'] ?? array() ) as $entry ) {
		if ( empty( $entry['label'] ) ) {
			continue;
		}
		$catalog[ normalize_label( (string) $entry['label'] ) ] = $entry;
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
 * @param string $mood  Saved mood text.
 * @param string $emoji Saved emoji.
 * @return array{label: string, family: string, motif: string, layout: string, font: string, palette: array<int, string>, rim: array<int, string>, glyph: string, pattern: string, custom: bool}
 */
function resolve( string $mood, string $emoji ): array {
	$label = normalize_label( $mood );
	$emoji = trim( wp_strip_all_tags( $emoji ) );
	$entry = catalog()[ $label ] ?? null;
	$seed  = crc32( $label . '|' . $emoji );
	if ( null !== $entry ) {
		$v = (array) ( $entry['visual'] ?? array() );
		return array(
			'label'   => (string) $entry['label'],
			'family'  => (string) ( $entry['family'] ?? 'other' ),
			'motif'   => (string) ( $v['motif'] ?? '' ),
			'layout'  => in_array( $v['layout'] ?? '', LAYOUTS, true ) ? (string) $v['layout'] : 'icon-word',
			'font'    => in_array( $v['font'] ?? '', FONTS, true ) ? (string) $v['font'] : 'mono',
			'palette' => PALETTES[ (int) ( $v['palette'] ?? 0 ) % count( PALETTES ) ],
			'rim'     => RIMS[ (int) ( $v['rim'] ?? 0 ) % count( RIMS ) ],
			'glyph'   => '',
			'pattern' => (string) ( $v['pattern'] ?? 'dots' ),
			'custom'  => false,
		);
	}
	return array(
		'label'   => $label,
		'family'  => '',
		'motif'   => '',
		'layout'  => '' !== $emoji ? 'icon-word' : 'word-big',
		'font'    => 'mono',
		'palette' => PALETTES[ $seed % count( PALETTES ) ],
		'rim'     => RIMS[ ( $seed >> 3 ) % count( RIMS ) ],
		'glyph'   => $emoji,
		'pattern' => 'dots',
		'custom'  => true,
	);
}

/**
 * A word's size in face units so it fits its band.
 *
 * @param string $word   The word.
 * @param string $font   Font key.
 * @param float  $usable Band width in face units.
 * @param float  $base   Size when the band is not the constraint.
 * @return float
 */
function word_size( string $word, string $font, float $usable, float $base ): float {
	$len = max( 1, mb_strlen( $word ) );
	$fit = $usable / ( $len * ( GLYPH_WIDTH[ $font ] ?? 0.6 ) );
	return round( max( WORD_MIN, min( $base, $fit ) ), 1 );
}

/**
 * The word as printed: uppercase for the typewriter and condensed faces.
 *
 * @param string $word Word.
 * @param string $font Font key.
 * @return string
 */
function printed_word( string $word, string $font ): string {
	return in_array( $font, array( 'mono', 'condensed' ), true ) ? mb_strtoupper( $word ) : $word;
}

/**
 * The face artwork: halftone field, registration mark, the motif in two
 * plates (accent nudged under ink), and the word set by layout. One SVG in
 * a 100×100 box; the shell around it is CSS.
 *
 * @param array<string, mixed> $spec From resolve().
 * @param int                  $uid  Per-pin id for pattern/path ids.
 * @return string
 */
function face_svg( array $spec, int $uid ): string {
	$label  = (string) $spec['label'];
	$font   = (string) $spec['font'];
	$layout = (string) $spec['layout'];
	$word   = printed_word( $label, $font );
	$seed   = crc32( $label );
	$angle  = $seed % 360;
	$ht     = 'crht' . $uid;
	$arc    = 'crarc' . $uid;

	// Motif placement and word band per layout: [scale, cx, cy, word y, usable, base].
	$placement = array(
		'icon-word' => array( 0.54, 50, 41, 81, 64, 11.5 ),
		'crooked'   => array( 0.54, 50, 41, 81, 62, 11.5 ),
		'icon-arc'  => array( 0.56, 50, 44, 0, 80, 11 ),
		'arc-top'   => array( 0.52, 50, 58, 0, 80, 11 ),
		'word-big'  => array( 0.3, 50, 28, 66, 78, 20 ),
		'stacked'   => array( 0.3, 50, 26, 66, 72, 15 ),
	);
	list( $scale, $cx, $cy, $wy, $usable, $base ) = $placement[ $layout ] ?? $placement['icon-word'];
	if ( 'hand' === $font ) {
		$base = min( $base, 15 );
	}

	$svg  = '<svg class="cr-pin__art" viewBox="0 0 100 100" aria-hidden="true" focusable="false">';
	$svg .= '<defs><pattern id="' . $ht . '" width="5.5" height="5.5" patternUnits="userSpaceOnUse" patternTransform="rotate(' . ( $angle % 45 ) . ')"><circle class="a" cx="2.75" cy="2.75" r="1.25"/></pattern>';
	if ( 'icon-arc' === $layout ) {
		$svg .= '<path id="' . $arc . '" d="M16 62A36 36 0 0 0 84 62"/>';
	} elseif ( 'arc-top' === $layout ) {
		$svg .= '<path id="' . $arc . '" d="M16 38A36 36 0 0 1 84 38"/>';
	}
	$svg .= '</defs>';

	// Halftone field: a quarter of the face, where the seed turns it; the
	// 'rays' seed adds short ink ticks around the motif instead.
	if ( 'rays' === $spec['pattern'] ) {
		$svg .= '<g class="cr-pin__rays" transform="rotate(' . ( $angle % 30 ) . ' 50 50)"><path class="i" stroke-width="2.4" d="M50 6v7M50 87v7M6 50h7M87 50h7M19 19l5 5M76 76l5 5M19 81l5-5M76 24l5-5"/></g>';
	} else {
		$svg .= '<path class="cr-pin__halftone" transform="rotate(' . ( $angle % 360 ) . ' 50 50)" d="M50 50L98 50A48 48 0 0 1 50 98z" fill="url(#' . $ht . ')"/>';
	}
	// Registration mark on the upper rim, clear of any word on the lower arc.
	$svg .= '<path class="i cr-pin__reg" stroke-width="1.5" transform="rotate(' . ( ( ( $seed >> 2 ) % 120 ) - 60 ) . ' 50 50)" d="M50 6v6M47 9h6"/>';

	if ( '' !== $spec['motif'] ) {
		$art = motif( (string) $spec['motif'] );
		if ( '' !== $art ) {
			$t    = sprintf( 'translate(%.2f %.2f) scale(%.2f)', $cx - 50 * $scale, $cy - 50 * $scale, $scale );
			$svg .= '<g class="cr-pin__plate cr-pin__plate--a" stroke-width="7" transform="translate(1.4 1.1) ' . $t . '">' . $art . '</g>';
			$svg .= '<g class="cr-pin__plate cr-pin__plate--i" stroke-width="7" filter="url(#cr-ink-rough)" transform="' . $t . '">' . $art . '</g>';
		}
	}

	if ( '' !== $word ) {
		$class = 'cr-pin__word cr-pin__word--' . $font;
		if ( in_array( $layout, array( 'icon-arc', 'arc-top' ), true ) ) {
			$size = word_size( $word, $font, $usable, $base );
			$svg .= '<text class="' . $class . '" font-size="' . $size . '"><textPath href="#' . $arc . '" startOffset="50%" text-anchor="middle">' . esc_html( $word ) . '</textPath></text>';
		} elseif ( 'stacked' === $layout && false !== strpos( $word, ' ' ) ) {
			$lines = explode( ' ', $word, 2 );
			$size  = min( word_size( $lines[0], $font, $usable, $base ), word_size( $lines[1], $font, $usable, $base ) );
			$svg  .= '<text class="' . $class . '" font-size="' . $size . '" text-anchor="middle" x="50" y="' . ( $wy - $size * 0.55 ) . '">' . esc_html( $lines[0] ) . '<tspan x="50" dy="' . ( $size * 1.15 ) . '">' . esc_html( $lines[1] ) . '</tspan></text>';
		} else {
			$size = word_size( $word, $font, $usable, $base );
			$attr = 'crooked' === $layout ? ' transform="rotate(-6 50 ' . $wy . ')"' : '';
			$svg .= '<text class="' . $class . '" font-size="' . $size . '" text-anchor="middle" x="50" y="' . $wy . '"' . $attr . '>' . esc_html( $word ) . '</text>';
		}
	}
	return $svg . '</svg>';
}

/**
 * The shared rough-ink filter, printed once per document before the first
 * pin (inline SVGs reference it by id).
 *
 * @return string
 */
function ink_defs(): string {
	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;
	return '<svg class="cr-pin-defs" width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute;width:0;height:0;overflow:hidden"><filter id="cr-ink-rough" x="-6%" y="-6%" width="112%" height="112%"><feTurbulence type="fractalNoise" baseFrequency="1.1" numOctaves="1" seed="3" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="1.4" xChannelSelector="R" yChannelSelector="G"/></filter></svg>';
}

/**
 * The pin.
 *
 * Reading order carries the mood word once, as real (visually hidden) text;
 * the face artwork, including the printed word, is decorative. A saved
 * emoji with no catalog entry prints as a text glyph, exposed only when
 * there is no word to name the mood.
 *
 * @param array<string, mixed> $spec From resolve().
 * @param string               $size 'single' (12rem) or 'stream' (7.5rem).
 * @return string
 */
function render( array $spec, string $size ): string {
	static $uid = 0;
	++$uid;
	$label = (string) $spec['label'];
	$seed  = crc32( $label . '|' . $spec['glyph'] );
	$size  = 'single' === $size ? 'single' : 'stream';
	$p     = $spec['palette'];
	$r     = $spec['rim'];

	$classes = array( 'cr-pin', 'cr-pin--' . $size, 'cr-pin--l-' . $spec['layout'], 'cr-pin--f-' . $spec['font'] );
	if ( ! empty( $spec['custom'] ) ) {
		$classes[] = 'cr-pin--custom';
	}
	if ( '' !== $spec['family'] ) {
		$classes[] = 'cr-pin--fam-' . $spec['family'];
	}
	$style = sprintf(
		'--pin-face:%s;--pin-ink:%s;--pin-accent:%s;--pin-rim-hi:%s;--pin-rim-lo:%s;--pin-tilt:%s;--pin-wear:%ddeg',
		$p[0],
		$p[1],
		$p[2],
		$r[0],
		$r[1],
		TILTS[ $seed % count( TILTS ) ],
		$seed % 360
	);

	$html  = ink_defs();
	$html .= '<span class="cr-pin-set cr-pin-set--' . $size . '">';
	$html .= '<span class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '">';
	if ( '' !== $label ) {
		$html .= '<span class="cr-sr-only cr-pin__name">' . esc_html( $label ) . '</span>';
	}
	$html .= '<span class="cr-pin__rim" aria-hidden="true"></span>';
	$html .= '<span class="cr-pin__face">' . face_svg( $spec, $uid );
	if ( '' !== $spec['glyph'] ) {
		$html .= '<span class="cr-pin__glyph"' . ( '' !== $label ? ' aria-hidden="true"' : '' ) . '>' . esc_html( (string) $spec['glyph'] ) . '</span>';
	}
	$html .= '<span class="cr-pin__gloss" aria-hidden="true"></span></span>';
	$html .= '<span class="cr-pin__wear" aria-hidden="true"></span>';
	$html .= '</span>';
	if ( 'single' === $size && '' !== $spec['family'] ) {
		$html .= '<span class="cr-pin__family"><span class="cr-sr-only">' . esc_html__( 'Mood family:', 'courtneyr-child' ) . ' </span>' . esc_html( $spec['family'] ) . '</span>';
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
		$out = (string) preg_replace_callback(
			'/<div class="pk-mood">/',
			static function ( array $m ) use ( $pin ): string {
				return $m[0] . $pin;
			},
			$html,
			1
		);
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

	// A card-only micro-post on /stream with no moodAt has no date of its
	// own; the post's date joins it, in the plugin's own meta markup.
	if ( ! is_singular( 'post' ) && false === strpos( $html, 'dt-published' ) ) {
		$post = get_post();
		if ( $post instanceof \WP_Post ) {
			$date = '<div class="pk-meta"><time class="dt-published" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">' . esc_html( (string) get_the_date( '', $post ) ) . '</time></div>';
			$at   = strpos( $html, '<data class="p-name"' );
			if ( false === $at ) {
				$at = strrpos( $html, '</article>' );
			}
			if ( false !== $at ) {
				$html = substr( $html, 0, $at ) . $date . substr( $html, $at );
			}
		}
	}

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
	$html = replace_emoji_span( $html, render( resolve( (string) ( $attrs['mood'] ?? '' ), $emoji ), 'stream' ) );
	// A title-less post gets the plugin's synthetic kind-label heading as its
	// link; under a mood pin that reads "Mood" twice, so it hides visually.
	if ( false === strpos( $html, 'pk-title p-name' ) ) {
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( $tags->next_tag( array( 'tag_name' => 'article', 'class_name' => 'pk-card' ) ) ) {
			$tags->add_class( 'cr-pin-card--untitled' );
			$html = $tags->get_updated_html();
		}
	}
	return $html;
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
	foreach ( catalog() as $entry ) {
		$moods[] = array(
			'label'  => (string) $entry['label'],
			'emoji'  => (string) ( $entry['emoji'] ?? '' ),
			'family' => (string) ( $entry['family'] ?? '' ),
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
					'family' => (string) $spec['family'],
				);
			},
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_route' );
