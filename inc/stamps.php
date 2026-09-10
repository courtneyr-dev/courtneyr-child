<?php
/**
 * Generated stamps: the shared primitive.
 *
 * One inline-SVG stamp drawn from a spec — shape, text slots, a glyph, an
 * ink, a tilt — with a deterministic picker on top. The primitive knows
 * nothing about check-ins or galleries; each artifact on /stream/ has its
 * own adapter (inc/stream-checkin.php, inc/stream-gallery.php) that turns
 * privacy-safe metadata into a spec and hands it here. Both adapters share
 * this geometry, the ink set, the tilt set, and the accessibility contract:
 * every stamp is aria-hidden and non-focusable, and every fact a stamp
 * prints must also exist as visible text in the card that carries it.
 *
 * No randomness: variation comes only from the seed the adapter supplies.
 * Sizes are fixed per shape (cr-post-kinds.css), so a footer's height never
 * depends on which variant a post happens to draw.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Stamps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inks a stamp may use. Raw hex on purpose: ink on a printed page stays
 * the same in dark mode, like the other physical objects in the collage.
 */
const INKS = array( '#241c4a', '#126782', '#b85f00' );

/**
 * Tilt classes (cr-post-kinds.css maps 0–3 to −6°, −2°, 3°, 7°).
 */
const TILTS = 4;

/**
 * Glyphs available to the icon slot, drawn centred on (0,0) in a 24-unit box.
 */
const GLYPHS = array(
	'pin'    => '<path d="M0-10c-4.4 0-8 3.6-8 8 0 5.6 8 13 8 13s8-7.4 8-13c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />',
	'camera' => '<path d="M-10-5h4l2-3h8l2 3h4a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-20a2 2 0 0 1-2-2v-11a2 2 0 0 1 2-2zm10 3a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9z" />',
	'pine'   => '<path d="M0-11l6 8h-3.5l4.5 6h-3.5l4 5.5h-15l4-5.5h-3.5l4.5-6h-3.5z" /><rect x="-1.2" y="8.5" width="2.4" height="3.5" />',
	'film'   => '<path d="M-11-7h22v14h-22zm3 2v3h2v-3zm0 4v3h2v-3zm0 4v3h2v-3zm12-8v3h2v-3zm0 4v3h2v-3zm0 4v3h2v-3zm-9-7v9h10v-9z" fill-rule="evenodd" />',
);

/**
 * A stable integer from any set of stable strings.
 *
 * @param string ...$parts Values that identify the artifact and never change.
 * @return int
 */
function seed( string ...$parts ): int {
	return crc32( implode( '|', $parts ) );
}

/**
 * A bounded choice from a seed: bit-shift first so successive picks from
 * the same seed do not move together.
 *
 * @param int $seed  Seed from seed().
 * @param int $shift Bits to discard before choosing.
 * @param int $mod   Number of options.
 * @return int 0 … $mod-1.
 */
function pick( int $seed, int $shift, int $mod ): int {
	return ( ( $seed >> $shift ) & 0x7fffffff ) % max( 1, $mod );
}

/**
 * Trim a stamp line so it fits its stroke without spilling.
 *
 * A "one, two" pair that overruns drops back to its first part before
 * anything is cut mid-word; only a single over-long word is elided.
 *
 * @param string $text  Text to fit.
 * @param int    $limit Character limit.
 * @return string
 */
function fit( string $text, int $limit ): string {
	if ( mb_strlen( $text ) <= $limit ) {
		return $text;
	}
	$comma = mb_strpos( $text, ',' );
	if ( false !== $comma && $comma > 0 ) {
		return fit( trim( mb_substr( $text, 0, $comma ) ), $limit );
	}
	return rtrim( mb_substr( $text, 0, $limit - 1 ) ) . '…';
}

/**
 * Draw one stamp.
 *
 * Spec keys (all optional except shape):
 *   shape    'rect' | 'seal' | 'octagon'
 *   big      headline (CHECKED IN, ARRIVED, PHOTO LOG …)
 *   mid      second line (a place, a frame count …)
 *   small    third line (a date …)
 *   tiny     fourth line, seal and octagon only (a country, an entry code …)
 *   ring     text around a seal's outer ring
 *   glyph    key of GLYPHS, or '' for none
 *   ink      hex colour, normally INKS[n]
 *   tilt     0 … TILTS-1
 *   postmark bool, seal only: add cancellation waves to the right
 *   uid      unique id fragment for the seal's text path (required for seal)
 *   family   free-text name written as a data attribute for the stylesheet
 *
 * @param array<string, mixed> $spec Stamp spec.
 * @return string Markup: a span wrapper carrying the tilt class, containing the SVG.
 */
function render( array $spec ): string {
	$shape    = in_array( $spec['shape'] ?? '', array( 'rect', 'seal', 'octagon' ), true ) ? $spec['shape'] : 'rect';
	$ink      = (string) ( $spec['ink'] ?? INKS[0] );
	$tilt     = (int) ( $spec['tilt'] ?? 0 ) % TILTS;
	$glyph    = GLYPHS[ $spec['glyph'] ?? '' ] ?? '';
	$family   = (string) ( $spec['family'] ?? $shape );
	$postmark = 'seal' === $shape && ! empty( $spec['postmark'] );

	$big   = strtoupper( (string) ( $spec['big'] ?? '' ) );
	$mid   = strtoupper( fit( (string) ( $spec['mid'] ?? '' ), 'rect' === $shape ? 22 : 16 ) );
	$small = strtoupper( (string) ( $spec['small'] ?? '' ) );
	$tiny  = strtoupper( fit( (string) ( $spec['tiny'] ?? '' ), 18 ) );
	$ring  = strtoupper( fit( (string) ( $spec['ring'] ?? '' ), 24 ) );

	$classes = 'cr-stamp cr-stamp--' . $shape . ( $postmark ? ' cr-stamp--postmark' : '' );
	$style   = ' style="--cr-stamp-ink:' . esc_attr( $ink ) . '"';
	$data    = ' data-stamp-family="' . esc_attr( $family ) . '"';

	if ( 'rect' === $shape ) {
		$svg = '<svg class="' . esc_attr( $classes ) . '" viewBox="0 0 184 100" aria-hidden="true" focusable="false"' . $style . $data . '>'
			. '<rect x="4" y="4" width="176" height="92" rx="5" stroke-width="3" stroke-dasharray="62 3 38 2 118 4 40 3 200" />'
			. '<rect x="11" y="11" width="162" height="78" rx="3" stroke-width="1.5" stroke-dasharray="30 2 70 3 44 2 300" />';
		if ( '' !== $glyph ) {
			$svg .= '<g class="cr-stamp__glyph" transform="translate(30 50) scale(0.9)">' . $glyph . '</g>';
		}
		$tx   = '' !== $glyph ? 104 : 92;
		$svg .= '<text x="' . $tx . '" y="43" class="cr-stamp__big">' . esc_html( $big ) . '</text>'
			. '<text x="' . $tx . '" y="64" class="cr-stamp__mid"' . ( mb_strlen( $mid ) > 15 ? ' textLength="' . ( '' !== $glyph ? 120 : 150 ) . '" lengthAdjust="spacingAndGlyphs"' : '' ) . '>' . esc_html( $mid ) . '</text>'
			. '<text x="' . $tx . '" y="81" class="cr-stamp__small">' . esc_html( $small ) . '</text>'
			. '</svg>';
	} elseif ( 'octagon' === $shape ) {
		$svg = '<svg class="' . esc_attr( $classes ) . '" viewBox="0 0 124 124" aria-hidden="true" focusable="false"' . $style . $data . '>'
			. '<path d="M38 4h48l34 34v48l-34 34h-48l-34-34v-48z" stroke-width="3" stroke-dasharray="70 3 50 2 90 4 300" />'
			. '<path d="M42 13h40l29 29v40l-29 29h-40l-29-29v-40z" stroke-width="1.5" stroke-dasharray="36 2 60 3 300" />';
		if ( '' !== $glyph ) {
			$svg .= '<g class="cr-stamp__glyph" transform="translate(62 40) scale(0.85)">' . $glyph . '</g>';
		}
		$svg .= '<text x="62" y="' . ( '' !== $glyph ? 70 : 58 ) . '" class="cr-stamp__big cr-stamp__big--seal">' . esc_html( $big ) . '</text>'
			. '<text x="62" y="' . ( '' !== $glyph ? 84 : 74 ) . '" class="cr-stamp__mid cr-stamp__mid--seal">' . esc_html( $mid ) . '</text>'
			. '<text x="62" y="' . ( '' !== $glyph ? 97 : 88 ) . '" class="cr-stamp__small">' . esc_html( $small ) . '</text>'
			. ( '' === $glyph && '' !== $tiny ? '<text x="62" y="101" class="cr-stamp__tiny">' . esc_html( $tiny ) . '</text>' : '' )
			. '</svg>';
	} else {
		$uid = (string) ( $spec['uid'] ?? 'cr-seal' );
		$w   = $postmark ? 214 : 124;
		$svg = '<svg class="' . esc_attr( $classes ) . '" viewBox="0 0 ' . $w . ' 124" aria-hidden="true" focusable="false"' . $style . $data . '>'
			// Ring path starts at the left and sweeps over the top: 25% is top centre.
			. '<defs><path id="' . esc_attr( $uid ) . '" d="M 13 62 a 49 49 0 1 1 98 0 a 49 49 0 1 1 -98 0" /></defs>'
			. '<circle cx="62" cy="62" r="58" stroke-width="3" stroke-dasharray="90 3 120 2 60 4 400" />'
			. '<circle cx="62" cy="62" r="40" stroke-width="1.5" stroke-dasharray="40 2 90 3 300" />';
		if ( '' !== $ring ) {
			$svg .= '<text class="cr-stamp__ring"><textPath href="#' . esc_attr( $uid ) . '" startOffset="25%">' . esc_html( $ring ) . '</textPath></text>';
		}
		if ( '' !== $glyph ) {
			$svg .= '<g class="cr-stamp__glyph" transform="translate(62 45) scale(0.8)">' . $glyph . '</g>';
		}
		$svg .= '<text x="62" y="' . ( '' !== $glyph ? 71 : 60 ) . '" class="cr-stamp__big cr-stamp__big--seal">' . esc_html( $big ) . '</text>'
			. '<text x="62" y="' . ( '' !== $glyph ? 83 : 74 ) . '" class="cr-stamp__small">' . esc_html( '' !== $mid ? $mid : $small ) . '</text>'
			. '<text x="62" y="' . ( '' !== $glyph ? 94 : 86 ) . '" class="cr-stamp__tiny">' . esc_html( '' !== $mid ? $small : $tiny ) . '</text>'
			. ( '' !== $mid && '' !== $tiny && '' === $glyph ? '<text x="62" y="97" class="cr-stamp__tiny">' . esc_html( $tiny ) . '</text>' : '' );
		if ( $postmark ) {
			$svg .= '<g class="cr-stamp__waves" stroke-width="2.5" fill="none">'
				. '<path d="M128 46c12-8 22 8 34 0s22 8 34 0" /><path d="M128 62c12-8 22 8 34 0s22 8 34 0" />'
				. '<path d="M128 78c12-8 22 8 34 0s22 8 34 0" /><path d="M128 94c12-8 22 8 34 0s22 8 34 0" />'
				. '</g>';
		}
		$svg .= '</svg>';
	}

	return '<span class="cr-stamp-mark cr-stamp-mark--tilt-' . $tilt . '">' . $svg . '</span>';
}
