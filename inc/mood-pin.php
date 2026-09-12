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
	array( '#bcb5e3', '#241c4a', '#ffb703' ), // 0 curious: periwinkle · violet · yellow
	array( '#023047', '#8ecae6', '#bcb5e3' ), // 1 nostalgic: Prussian · sky · periwinkle
	array( '#fee2c3', '#241c4a', '#fb8500' ), // 2 productive: light orange · violet · UT orange
	array( '#126782', '#fee2c3', '#bcb5e3' ), // 3 melancholy: cerulean · light orange · periwinkle
	array( '#ebebeb', '#241c4a', '#fb8500' ), // 4 quixotic: light gray (the one intentional neutral) · violet · UT orange
	array( '#ffb703', '#241c4a', '#126782' ), // 5 yellow · violet · cerulean
	array( '#8ecae6', '#023047', '#fb8500' ), // 6 sky · Prussian · UT orange
	array( '#fb8500', '#241c4a', '#fee2c3' ), // 7 UT orange · violet · light orange
	array( '#241c4a', '#fee2c3', '#8ecae6' ), // 8 violet · light orange · sky
	array( '#126782', '#ebebeb', '#ffb703' ), // 9 cerulean · light gray · yellow
	array( '#219ebc', '#241c4a', '#ffb703' ), // 10 blue-green · violet · yellow
	array( '#fee2c3', '#023047', '#219ebc' ), // 11 light orange · Prussian · blue-green
	array( '#bcb5e3', '#023047', '#fb8500' ), // 12 periwinkle · Prussian · UT orange
	array( '#023047', '#ffb703', '#8ecae6' ), // 13 Prussian · yellow · sky
	array( '#ffb703', '#023047', '#fb8500' ), // 14 yellow · Prussian · UT orange
	array( '#8ecae6', '#241c4a', '#fb8500' ), // 15 sky · violet · UT orange
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
	$with_glyph = array( 'icon-word', 'crooked', 'icon-arc', 'arc-top' );
	return array(
		'label'   => $label,
		'family'  => '',
		'motif'   => '',
		'layout'  => '' !== $emoji ? $with_glyph[ ( $seed >> 2 ) % 4 ] : ( false !== strpos( $label, ' ' ) ? 'stacked' : 'word-big' ),
		'font'    => array( 'mono', 'condensed', 'slab' )[ ( $seed >> 4 ) % 3 ],
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

/** Motifs allowed to fill the face and run toward the rim. */
const BIG_MOTIFS = array( 'lightning', 'battery-empty', 'cassette', 'moon', 'moon-zzz', 'cloud', 'rain-cloud', 'flower', 'magnifier', 'mug', 'sun', 'horizon-sun', 'burst', 'windmill', 'checkbox', 'waves', 'sprout', 'heart', 'heart-rays', 'snowflake', 'flame', 'medal', 'alarm', 'clock', 'bulb', 'thermometer', 'hourglass' );

/** Editorial stacks for single words: where the two lines break. */
const STACKS = array(
	'restless'      => array( 'rest', 'less' ),
	'indescribable' => array( 'in', 'describable' ),
	'uncomfortable' => array( 'un', 'comfortable' ),
	'contemplative' => array( 'con', 'templative' ),
	'heartbroken'   => array( 'heart', 'broken' ),
	'incomplete'    => array( 'in', 'complete' ),
	'exanimate'     => array( 'ex', 'animate' ),
	'hung-over'     => array( 'hung', 'over' ),
	'rejuvenated'   => array( 're', 'juvenated' ),
	'disappointed'  => array( 'dis', 'appointed' ),
	'accomplished'  => array( 'accom', 'plished' ),
);

/**
 * Print-defect level from the seed: 0 clean (~10%), 1 subtle (~65%), 2
 * visibly off-register (~25%).
 *
 * @param int $seed Stable seed.
 * @return int
 */
function defect_level( int $seed ): int {
	$n = ( $seed >> 7 ) % 20;
	return $n < 2 ? 0 : ( $n < 15 ? 1 : 2 );
}

/**
 * The face artwork: halftone field, registration mark, the motif in two
 * plates (accent nudged under ink), and the word set by layout. One SVG in
 * a 100×100 box; the shell around it is CSS. Every choice below comes
 * from the mood's seed, so a mood always prints the same way.
 *
 * @param array<string, mixed> $spec From resolve().
 * @param int                  $uid  Per-pin id for pattern/path ids.
 * @return string
 */
function face_svg( array $spec, int $uid ): string {
	$label  = (string) $spec['label'];
	$font   = (string) $spec['font'];
	$layout = (string) $spec['layout'];
	$word   = ! empty( $spec['word_below'] ) ? '' : printed_word( $label, $font );
	$seed   = crc32( $label );
	$angle  = $seed % 360;
	$defect = defect_level( $seed );
	$side   = ( ( $seed >> 11 ) % 2 ) ? 1 : -1;
	$big    = in_array( $spec['motif'], BIG_MOTIFS, true );
	$ht     = 'crht' . $uid;
	$arc    = 'crarc' . $uid;
	$dirs   = array( array( 1, 1 ), array( -1, 1 ), array( 1, -1 ), array( -1, -0.6 ) );
	$dir    = $dirs[ ( $seed >> 9 ) % 4 ];
	$nudge  = array( 0.8, 2.2, 3.4 )[ $defect ];

	// Placement per layout: motif scale, centre, rotation; word band.
	switch ( $layout ) {
		case 'crooked':
			$scale = $big ? 0.72 : 0.62;
			$cx    = 50 - $side * 9;
			$cy    = 37;
			$rot   = -8 * $side;
			break;
		case 'icon-arc':
			$scale = $big ? 0.7 : 0.62;
			$cx    = 50 + $side * 2;
			$cy    = 43;
			$rot   = 0;
			break;
		case 'arc-top':
			$scale = $big ? 0.58 : 0.52;
			$cx    = 50 - $side * 3;
			$cy    = 63;
			$rot   = 0;
			break;
		case 'word-big':
			$scale = 0.24;
			$cx    = 50 + $side * 22;
			$cy    = 21;
			$rot   = 12 * $side;
			break;
		case 'stacked':
			$scale = 0.22;
			$cx    = 50 - $side * 26;
			$cy    = 19;
			$rot   = -10 * $side;
			break;
		default: // icon-word
			$scale = $big ? 0.84 : 0.72;
			$cx    = 50 + $side * ( $big ? 5 : 3 );
			$cy    = 40;
			$rot   = 0;
	}

	$svg  = '<svg class="cr-pin__art" viewBox="0 0 100 100" aria-hidden="true" focusable="false">';
	$svg .= '<defs><pattern id="' . $ht . '" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(' . ( $angle % 45 ) . ')"><circle class="a" cx="3" cy="3" r="' . ( 2 === $defect ? 2 : 1.7 ) . '"/></pattern>';
	if ( 'icon-arc' === $layout ) {
		$svg .= '<path id="' . $arc . '" d="M13 60A39 39 0 0 0 87 60"/>';
	} elseif ( 'arc-top' === $layout ) {
		$svg .= '<path id="' . $arc . '" d="M14 41.8A38 38 0 0 1 86 41.8"/>';
	}
	$svg .= '</defs>';

	// Halftone field: one of eight shapes by seed, turned by seed, cropped
	// by the face; the 'rays' pattern seed prints short ink ticks instead.
	$fill = 'fill="url(#' . $ht . ')"';
	$turn = 'transform="rotate(' . $angle . ' 50 50)"';
	if ( 'rays' === $spec['pattern'] ) {
		$svg .= '<g class="cr-pin__rays" transform="rotate(' . ( $angle % 30 ) . ' 50 50)"><path class="i" stroke-width="2.6" d="M50 5v8M50 87v8M5 50h8M87 50h8M18 18l6 6M76 76l6 6M18 82l6-6M76 24l6-6"/></g>';
	} else {
		$shapes = array(
			'M50 50L98 50A48 48 0 0 1 50 98z',            // quarter wedge
			'M2 50A48 48 0 0 1 98 50z',                    // half face
			'M-10 30h120v24h-120z',                        // band across
			'M56 4h60v52H56z',                             // offset block
			'M50 2A48 48 0 1 1 49.9 2zM50 14A36 36 0 1 0 50.1 14z', // ring by the rim
			'M76 -10h40v120H76z',                          // narrow side strip
			'M-10 41h120v18h-120z',                        // central band
			'M50 2A48 48 0 1 1 49.9 2zM62 6A44 44 0 1 0 62.1 6z',   // crescent
		);
		$shape  = $shapes[ ( $seed >> 13 ) % count( $shapes ) ];
		$evenodd = ( false !== strpos( $shape, 'M50 14' ) || false !== strpos( $shape, 'M62 6' ) ) ? ' fill-rule="evenodd"' : '';
		$svg   .= '<path class="cr-pin__halftone" ' . $turn . ' d="' . $shape . '"' . $evenodd . ' ' . $fill . '/>';
	}
	// Registration mark on the upper rim; a second one on the strong-defect subset.
	$svg .= '<path class="i cr-pin__reg" stroke-width="1.5" transform="rotate(' . ( ( ( $seed >> 2 ) % 120 ) - 60 ) . ' 50 50)" d="M50 6v6M47 9h6"/>';
	if ( 2 === $defect ) {
		$svg .= '<path class="i cr-pin__reg" stroke-width="1.5" transform="rotate(' . ( 120 + ( ( $seed >> 3 ) % 120 ) ) . ' 50 50)" d="M50 6v6M47 9h6"/>';
	}

	if ( '' !== $spec['motif'] ) {
		$art = motif( (string) $spec['motif'] );
		if ( '' !== $art ) {
			$t      = sprintf( 'translate(%.2f %.2f) rotate(%d 50 50) scale(%.2f)', $cx - 50 * $scale, $cy - 50 * $scale, $rot, $scale );
			$filter = $defect > 0 ? ' filter="url(#cr-ink-rough)"' : '';
			$svg   .= '<g class="cr-pin__plate cr-pin__plate--a" stroke-width="8" transform="translate(' . ( $nudge * $dir[0] ) . ' ' . ( $nudge * $dir[1] ) . ') ' . $t . '">' . $art . '</g>';
			$svg   .= '<g class="cr-pin__plate cr-pin__plate--i" stroke-width="8"' . $filter . ' transform="' . $t . '">' . $art . '</g>';
			if ( 2 === $defect ) {
				// A patch where the ink didn't take.
				$svg .= '<ellipse class="cr-pin__void" cx="' . ( $cx + $side * 9 ) . '" cy="' . ( $cy + 6 ) . '" rx="7" ry="3.5" transform="rotate(' . ( $angle % 60 - 30 ) . ' ' . $cx . ' ' . $cy . ')"/>';
			}
		}
	}

	if ( '' !== $word ) {
		$class = 'cr-pin__word cr-pin__word--' . $font;
		$lean  = 2 === $defect ? ( ( ( $seed >> 15 ) % 2 ) ? 1.5 : -1.5 ) : 0;
		if ( in_array( $layout, array( 'icon-arc', 'arc-top' ), true ) ) {
			$size = word_size( $word, $font, 84, 'hand' === $font ? 11 : 13 );
			$svg .= '<text class="' . $class . '" font-size="' . $size . '"><textPath href="#' . $arc . '" startOffset="50%" text-anchor="middle">' . esc_html( $word ) . '</textPath></text>';
		} elseif ( 'stacked' === $layout ) {
			$parts = STACKS[ $label ] ?? ( false !== strpos( $word, ' ' ) ? explode( ' ', $word, 2 ) : array( $word ) );
			$parts = array_map( static fn( $x ) => printed_word( (string) $x, $font ), $parts );
			$s1    = word_size( $parts[0], $font, 82, mb_strlen( $parts[0] ) <= 4 ? 30 : 22 );
			$svg  .= '<text class="' . $class . '" font-size="' . $s1 . '" text-anchor="middle" x="' . ( 50 - $side * 4 ) . '" y="' . ( isset( $parts[1] ) ? 52 : 62 ) . '" transform="rotate(' . $lean . ' 50 50)">' . esc_html( $parts[0] ) . '</text>';
			if ( isset( $parts[1] ) ) {
				$s2   = word_size( $parts[1], $font, 82, 18 );
				$svg .= '<text class="' . $class . '" font-size="' . $s2 . '" text-anchor="middle" x="' . ( 50 + $side * 3 ) . '" y="' . ( 52 + $s2 * 1.15 ) . '" transform="rotate(' . $lean . ' 50 50)">' . esc_html( $parts[1] ) . '</text>';
				$svg .= '<path class="as" stroke-width="2.5" d="M' . ( 50 - $side * 18 ) . ' ' . ( 58 + $s2 * 1.15 ) . 'h' . ( 26 + ( $seed >> 4 ) % 12 ) . '"/>';
			}
		} elseif ( 'word-big' === $layout ) {
			$size = word_size( $word, $font, 84, 'hand' === $font ? 22 : 30 );
			$svg .= '<text class="' . $class . '" font-size="' . $size . '" text-anchor="middle" x="50" y="' . ( 60 + $size * 0.3 ) . '" transform="rotate(' . $lean . ' 50 60)">' . esc_html( $word ) . '</text>';
			if ( ( $seed >> 6 ) % 2 ) {
				// An echo of the word in the accent plate, like a second pull of the screen.
				$svg .= '<text class="' . $class . ' cr-pin__echo" font-size="' . round( $size * 0.5, 1 ) . '" text-anchor="middle" x="' . ( 50 + $side * 12 ) . '" y="' . ( 60 + $size * 0.3 + $size * 0.62 ) . '" transform="rotate(' . ( -6 * $side ) . ' 50 70)">' . esc_html( $word ) . '</text>';
			} else {
				$svg .= '<path class="as" stroke-width="3" d="M' . ( 50 - $side * 22 ) . ' ' . ( 64 + $size * 0.3 + 4 ) . 'h' . ( 24 + ( $seed >> 4 ) % 14 ) . '"/>';
			}
		} elseif ( 'crooked' === $layout ) {
			$size = word_size( $word, $font, 58, 12.5 );
			$svg .= '<text class="' . $class . '" font-size="' . $size . '" text-anchor="middle" x="' . ( 50 + $side * 7 ) . '" y="80" transform="rotate(' . ( -13 * $side + $lean ) . ' ' . ( 50 + $side * 7 ) . ' 80)">' . esc_html( $word ) . '</text>';
			$svg .= '<path class="a" transform="translate(' . ( 50 + $side * 30 ) . ' 16) rotate(' . ( $angle % 40 ) . ')" d="M0-5l1.5 3.5 3.5 1.5-3.5 1.5L0 5l-1.5-3.5L-5 0l3.5-1.5z"/>';
		} else {
			$size = word_size( $word, $font, 62, 10.5 );
			$svg .= '<text class="' . $class . '" font-size="' . $size . '" text-anchor="middle" x="50" y="86" transform="rotate(' . $lean . ' 50 86)">' . esc_html( $word ) . '</text>';
		}
	}
	if ( 'arc-top' === $layout ) {
		$svg .= '<path class="a" d="M' . ( 50 - $side * 10 ) . ' 88a2.6 2.6 0 1 0 .1 0zM' . ( 50 - $side * 2 ) . ' 88a2.6 2.6 0 1 0 .1 0zM' . ( 50 + $side * 6 ) . ' 88a2.6 2.6 0 1 0 .1 0z"/>';
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

	// A custom mood longer than the face can hold prints its word under the
	// pin, as real text, instead of inside the artwork.
	$spec['word_below'] = ! empty( $spec['custom'] ) && mb_strlen( $label ) > 14;
	$classes            = array( 'cr-pin', 'cr-pin--' . $size, 'cr-pin--l-' . $spec['layout'], 'cr-pin--f-' . $spec['font'] );
	if ( ! empty( $spec['custom'] ) ) {
		$classes[] = 'cr-pin--custom';
	}
	if ( '' !== $spec['family'] ) {
		$classes[] = 'cr-pin--fam-' . $spec['family'];
	}
	$classes[] = 'cr-pin--print-' . defect_level( crc32( $label ) );
	$scales = array( '0.95', '1', '1.1' );
	$style  = sprintf(
		'--pin-face:%s;--pin-ink:%s;--pin-accent:%s;--pin-rim-hi:%s;--pin-rim-lo:%s;--pin-tilt:%s;--pin-wear:%ddeg;--pin-scale:%s',
		$p[0],
		$p[1],
		$p[2],
		$r[0],
		$r[1],
		TILTS[ $seed % count( TILTS ) ],
		$seed % 360,
		$scales[ ( $seed >> 5 ) % count( $scales ) ]
	);

	$html  = ink_defs();
	$html .= '<span class="cr-pin-set cr-pin-set--' . $size . '">';
	$html .= '<span class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '">';
	if ( '' !== $label && ! $spec['word_below'] ) {
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
	if ( $spec['word_below'] ) {
		$html .= '<span class="cr-pin__name cr-pin__word-below">' . esc_html( $label ) . '</span>';
	}
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
	if ( is_page( 'stream' ) && false === strpos( $html, 'dt-published' ) ) {
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
