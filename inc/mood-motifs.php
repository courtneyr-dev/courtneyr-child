<?php
/**
 * Mood pin motif library: one small shared vocabulary of print-style
 * pictograms, drawn once and reused across the 194 moods.
 *
 * Every motif is an SVG fragment in a 100×100 box. Two classes carry the
 * inks: `i` is the line ink (stroke, currentColor) and `if` its solid
 * fill; `a` is the accent spot ink (fill) and `as` the accent as a stroke.
 * inc/mood-pin.php sets the colours per pin and prints the fragment twice
 * — an accent-only copy nudged under the ink copy — for the slightly
 * misregistered two-plate look of a screen-printed button.
 *
 * Families, roughly: faces (used sparingly), objects, weather, motion,
 * gesture, celestial, plants, marks. Add a motif here, then point moods
 * at it in assets/data/moods.json.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\MoodMotifs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Motif id → SVG inner markup (100×100 box).
 *
 * @return array<string, string>
 */
function library(): array {
	static $lib = null;
	if ( null !== $lib ) {
		return $lib;
	}
	$eyes  = '<circle class="if" cx="36" cy="42" r="6"/><circle class="if" cx="64" cy="42" r="6"/>';
	$lib   = array(
		// ---- faces: a few strokes, never a yellow disk ----
		'smile'        => $eyes . '<path class="i" d="M30 60q20 18 40 0"/>',
		'smile-closed' => '<path class="i" d="M26 44q10-10 20 0M54 44q10-10 20 0M32 62q18 14 36 0"/>',
		'grin'         => $eyes . '<path class="i" d="M26 58q24 26 48 0z"/><path class="a" d="M34 60h32q-16 14-32 0z"/>',
		'grin-crooked' => $eyes . '<path class="i" d="M28 58q18 22 44-8"/><path class="i" d="M66 56l6 4"/>',
		'frown'        => $eyes . '<path class="i" d="M30 68q20-16 40 0"/>',
		'flat'         => $eyes . '<path class="i" d="M32 64h36"/>',
		'brow'         => $eyes . '<path class="i" d="M26 28l20 6M56 30l18-6M34 66h32"/>',
		'side-eye'     => '<circle class="i" cx="36" cy="42" r="10"/><circle class="i" cx="64" cy="42" r="10"/><circle class="if" cx="41" cy="44" r="4.5"/><circle class="if" cx="69" cy="44" r="4.5"/><path class="i" d="M34 66h30"/>',
		'wink'         => '<circle class="if" cx="36" cy="42" r="6"/><path class="i" d="M56 42q9-7 18 0M30 60q20 16 40 0"/><circle class="a" cx="72" cy="60" r="6"/>',
		'sleepy'       => '<path class="i" d="M26 44q10 8 20 0M54 44q10 8 20 0M40 66h20"/><path class="i" d="M62 18h12l-12 12h12" stroke-width="5"/>',
		'open'         => $eyes . '<ellipse class="i" cx="50" cy="66" rx="9" ry="12"/>',
		'tongue'       => $eyes . '<path class="i" d="M28 58q22 22 44 0"/><path class="a" d="M46 66q8 18 16 2q-8 4-16-2z"/>',
		'grimace'      => $eyes . '<rect class="i" x="28" y="58" width="44" height="14" rx="3"/><path class="i" d="M39 58v14M50 58v14M61 58v14" stroke-width="4"/>',
		'tears'        => $eyes . '<path class="i" d="M32 70q18-12 36 0"/><path class="a" d="M64 50q6 10 6 14a6 6 0 0 1-12 0q0-4 6-14z"/>',
		'spiral-eyes'  => '<path class="i" d="M28 42a8 8 0 1 0 8-8a5 5 0 1 0-5 5M62 42a8 8 0 1 0 8-8a5 5 0 1 0-5 5" stroke-width="5"/><path class="i" d="M30 66q7-8 13 0t13 0t14 0"/>',
		'x-eyes'       => '<path class="i" d="M28 34l16 16M44 34l-16 16M56 34l16 16M72 34l-16 16M36 68h28"/>',
		'eye'          => '<path class="i" d="M14 50q36-32 72 0q-36 32-72 0z"/><circle class="if" cx="50" cy="50" r="10"/><circle class="a" cx="46" cy="46" r="3.5"/>',
		// ---- objects ----
		'battery-empty' => '<rect class="i" x="18" y="34" width="56" height="32" rx="5"/><rect class="if" x="76" y="44" width="8" height="12" rx="2"/><rect class="a" x="24" y="40" width="9" height="20" rx="2"/>',
		'cassette'     => '<rect class="i" x="12" y="28" width="76" height="44" rx="5"/><circle class="i" cx="36" cy="50" r="8"/><circle class="i" cx="64" cy="50" r="8"/><path class="a" d="M12 28h76v10H12z"/><path class="i" d="M30 72l6-10h28l6 10" stroke-width="5"/>',
		'mug'          => '<path class="i" d="M24 40h42v26a10 10 0 0 1-10 10H34a10 10 0 0 1-10-10z"/><path class="i" d="M66 46h8a8 8 0 0 1 0 16h-8"/><path class="as" d="M36 30q-4-6 0-12M48 30q-4-6 0-12" stroke-width="5"/>',
		'pencil-spark' => '<path class="i" d="M26 74l38-38 10 10-38 38-14 4z"/><path class="a" d="M26 74l5-14 9 9z"/><path class="a" d="M74 22l3 8 8 3-8 3-3 8-3-8-8-3 8-3z"/>',
		'brush'        => '<path class="i" d="M62 18l20 20-30 30-20-20z"/><path class="a" d="M32 48l20 20q-6 16-26 14 10-16 6-34z"/>',
		'checkbox'     => '<rect class="i" x="18" y="18" width="24" height="24" rx="4"/><path class="as" d="M22 30l7 7 11-13" stroke-width="6"/><rect class="i" x="18" y="58" width="24" height="24" rx="4"/><path class="i" d="M52 30h30M52 70h30"/>',
		'bulb'         => '<path class="i" d="M50 16a22 22 0 0 0-12 40v10h24V56a22 22 0 0 0-12-40z"/><path class="i" d="M40 76h20" stroke-width="5"/><path class="a" d="M50 28a10 10 0 0 0-8 16h16a10 10 0 0 0-8-16z"/>',
		'clock'        => '<circle class="i" cx="50" cy="52" r="30"/><path class="i" d="M50 34v18l12 8"/><path class="a" d="M50 22v-8M50 90v-8M20 52h-8M88 52h-8" stroke-width="5"/>',
		'alarm'        => '<circle class="i" cx="50" cy="56" r="26"/><path class="i" d="M50 42v14l9 6"/><path class="i" d="M22 32l10-8M78 32l-10-8" stroke-width="6"/><path class="as" d="M14 20l8 8M86 20l-8 8" stroke-width="5"/>',
		'magnifier'    => '<circle class="i" cx="42" cy="42" r="22"/><path class="i" d="M58 58l24 24" stroke-width="9"/><path class="a" d="M78 14l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>',
		'dice'         => '<rect class="i" x="22" y="22" width="56" height="56" rx="8"/><circle class="if" cx="36" cy="36" r="5"/><circle class="if" cx="64" cy="64" r="5"/><circle class="a" cx="50" cy="50" r="5"/><circle class="if" cx="64" cy="36" r="5"/><circle class="if" cx="36" cy="64" r="5"/>',
		'glass-drop'   => '<path class="i" d="M30 24h40l-6 52H36z"/><path class="a" d="M37 48h26l-3 24H40z"/><path class="a" d="M78 18q6 8 6 12a6 6 0 0 1-12 0q0-4 6-12z"/>',
		'plate-fork'   => '<circle class="i" cx="44" cy="52" r="26"/><circle class="a" cx="44" cy="52" r="12"/><path class="i" d="M82 18v64M76 18v16a6 6 0 0 0 12 0V18" stroke-width="5"/>',
		'wrench'       => '<path class="i" d="M70 18a16 16 0 0 0-14 24L20 78l10 10 36-36a16 16 0 0 0 22-16l-10 10-8-2-2-8z"/><path class="a" d="M24 78l6 6" stroke-width="0"/>',
		'brackets'     => '<path class="i" d="M36 26L18 50l18 24M64 26l18 24-18 24"/><path class="as" d="M56 22L44 78" stroke-width="6"/>',
		'glasses'      => '<circle class="i" cx="32" cy="52" r="15"/><circle class="i" cx="68" cy="52" r="15"/><path class="i" d="M47 50h6M17 46l-8-6M83 46l8-6" stroke-width="5"/><path class="a" d="M22 44h8M58 44h8" stroke-width="0"/><circle class="a" cx="26" cy="46" r="3"/><circle class="a" cx="62" cy="46" r="3"/>',
		'medal'        => '<path class="a" d="M36 10h28l-8 26H44z"/><circle class="i" cx="50" cy="62" r="22"/><path class="if" d="M50 48l4 9 10 1-7 7 2 10-9-5-9 5 2-10-7-7 10-1z"/>',
		'bandage'      => '<rect class="i" x="16" y="40" width="68" height="22" rx="11" transform="rotate(-35 50 51)"/><rect class="a" x="38" y="42" width="24" height="18" rx="2" transform="rotate(-35 50 51)"/>',
		'hourglass'    => '<path class="i" d="M28 16h44M28 84h44M32 16q0 24 18 34-18 10-18 34M68 16q0 24-18 34 18 10 18 34"/><path class="a" d="M40 72q10-8 20 0v8H40z"/>',
		'thermometer'  => '<path class="i" d="M42 18a8 8 0 0 1 16 0v40a14 14 0 1 1-16 0z"/><circle class="a" cx="50" cy="70" r="8"/><path class="as" d="M50 44v22" stroke-width="6"/>',
		'puzzle'       => '<path class="i" d="M24 30h20a8 8 0 0 1 16 0h16v20a8 8 0 0 0 0 16v18H56a8 8 0 0 1-16 0H24V60a8 8 0 0 1 0-16z"/><path class="a" d="M60 84q14-4 18-18" stroke-width="0"/>',
		'heart'        => '<path class="if" d="M50 82L22 54a16 16 0 0 1 28-18 16 16 0 0 1 28 18z"/><path class="a" d="M36 44a5 5 0 0 1 5-5" stroke-width="0"/><circle class="a" cx="38" cy="46" r="4"/>',
		'heart-rays'   => '<path class="if" d="M50 76L28 54a13 13 0 0 1 22-14 13 13 0 0 1 22 14z"/><path class="as" d="M50 22v-8M24 32l-6-6M76 32l6-6M14 54H6M94 54h-8" stroke-width="5"/>',
		'heart-broken' => '<path class="if" d="M46 82L22 54a16 16 0 0 1 26-18l-4 10 8 8-6 12z"/><path class="if" d="M54 84l-2-18 8-8-4-10a16 16 0 0 1 24 6z"/><path class="as" d="M50 34l-6 12 8 8-6 12 4 12" stroke-width="4"/>',
		'thumbs-up'    => '<path class="i" d="M22 48h14v32H22zM36 50l14-28q10 0 8 12l-3 10h18a8 8 0 0 1 8 8l-4 22a8 8 0 0 1-8 6H36z"/><path class="a" d="M40 56h30l-3 18H40z"/>',
		'fist'         => '<path class="i" d="M30 36h40a10 10 0 0 1 10 10v10a22 22 0 0 1-22 22H42a12 12 0 0 1-12-12z"/><path class="i" d="M42 36v-8a4 4 0 0 1 8 0v8M54 36v-8a4 4 0 0 1 8 0v8" stroke-width="5"/><path class="a" d="M36 62h30v10H36z"/>',
		'hands-pray'   => '<path class="i" d="M50 18q-16 16-18 46l18 20 18-20q-2-30-18-46z"/><path class="i" d="M50 26v50" stroke-width="4"/><path class="as" d="M18 34l8 6M82 34l-8 6M14 56h10M86 56H76" stroke-width="5"/>',
		// ---- weather ----
		'cloud'        => '<path class="i" d="M30 70a14 14 0 0 1-2-28 20 20 0 0 1 38-6 14 14 0 0 1 6 34z"/><path class="a" d="M34 50a10 10 0 0 1 20-6" stroke-width="0"/>',
		'rain-cloud'   => '<path class="i" d="M30 58a12 12 0 0 1-2-24 18 18 0 0 1 34-6 12 12 0 0 1 6 30z"/><path class="as" d="M34 68l-4 12M50 68l-4 12M66 68l-4 12" stroke-width="6"/>',
		'sun'          => '<circle class="a" cx="50" cy="50" r="16"/><circle class="i" cx="50" cy="50" r="16"/><path class="i" d="M50 14v10M50 76v10M14 50h10M76 50h10M25 25l7 7M68 68l7 7M25 75l7-7M68 32l7-7" stroke-width="6"/>',
		'horizon-sun'  => '<path class="a" d="M28 64a22 22 0 0 1 44 0z"/><path class="i" d="M28 64a22 22 0 0 1 44 0M12 64h76M12 78h20M68 78h20"/><path class="i" d="M50 24v-8M28 32l-6-6M72 32l6-6" stroke-width="5"/>',
		'lightning'    => '<path class="if" d="M56 10L26 56h20l-6 34 34-48H54z"/><path class="a" d="M52 22L38 46h14l-3 16 16-22h-12z"/>',
		'fog'          => '<path class="i" d="M16 34h52M28 50h56M16 66h40M40 82h44"/><path class="as" d="M72 34h12M18 82h14" stroke-width="6"/>',
		'wind'         => '<path class="i" d="M14 40h40a10 10 0 1 0-10-10M14 58h56a10 10 0 1 1-10 10"/><path class="as" d="M20 76h26" stroke-width="6"/>',
		'snowflake'    => '<path class="i" d="M50 14v72M19 32l62 36M19 68l62-36" stroke-width="6"/><path class="as" d="M50 22l-8 8M50 22l8 8M50 78l-8-8M50 78l8-8" stroke-width="5"/><circle class="a" cx="50" cy="50" r="7"/>',
		'flame'        => '<path class="i" d="M50 14q18 22 18 42a18 18 0 0 1-36 0q0-10 8-18 0 10 8 12-4-16 2-36z"/><path class="a" d="M50 52q8 8 8 14a8 8 0 0 1-16 0q0-6 8-14z"/>',
		// ---- motion / marks ----
		'burst'        => '<path class="a" d="M50 12l8 22 22-8-14 20 20 8-24 4 4 24-16-18-16 18 4-24-24-4 20-8-14-20 22 8z"/><path class="i" d="M50 12l8 22 22-8-14 20 20 8-24 4 4 24-16-18-16 18 4-24-24-4 20-8-14-20 22 8z" stroke-width="5"/>',
		'sparkles'     => '<path class="a" d="M50 14l6 18 18 6-18 6-6 18-6-18-18-6 18-6z"/><path class="i" d="M50 14l6 18 18 6-18 6-6 18-6-18-18-6 18-6z" stroke-width="5"/><path class="if" d="M22 66l3 8 8 3-8 3-3 8-3-8-8-3 8-3zM78 62l3 8 8 3-8 3-3 8-3-8-8-3 8-3z"/>',
		'stars'        => '<path class="if" d="M50 18l7 15 16 2-12 11 3 16-14-8-14 8 3-16-12-11 16-2z"/><path class="a" d="M20 64l4 8 8 4-8 4-4 8-4-8-8-4 8-4zM80 60l3 6 6 3-6 3-3 6-3-6-6-3 6-3z"/>',
		'confetti'     => '<path class="a" d="M22 30l10 4-4 10-10-4zM62 18l8 8-8 8-8-8zM70 60l12 2-2 12-12-2z"/><path class="i" d="M30 70q8-8 18 0t18 0M44 40q4-10 12-8M76 40l8-10" stroke-width="5"/><circle class="if" cx="30" cy="52" r="4"/><circle class="if" cx="54" cy="24" r="4"/>',
		'zigzag'       => '<path class="i" d="M12 60l14-24 14 24 14-24 14 24 14-24 10 18"/><path class="as" d="M18 76h24" stroke-width="6"/>',
		'vibrate'      => '<path class="i" d="M20 34q6-6 12 0t12 0t12 0t12 0t12 0M20 50q6-6 12 0t12 0t12 0t12 0t12 0M20 66q6-6 12 0t12 0t12 0t12 0t12 0" stroke-width="5"/><path class="as" d="M14 26l6 8M14 74l6-8M86 26l-6 8M86 74l-6-8" stroke-width="5"/>',
		'loop-arrows'  => '<path class="i" d="M30 62a20 20 0 1 1 6 14"/><path class="if" d="M34 64l2 18 14-12z"/><path class="as" d="M64 30l8-4-2 10" stroke-width="5"/>',
		'tangle'       => '<path class="i" d="M16 60q20-40 40-10t28-24M18 30q26 6 30 30t34 20"/><path class="if" d="M84 26l-2 14-12-6zM82 70l-14 2 6 12z"/>',
		'arrow-up'     => '<path class="i" d="M50 84V26"/><path class="if" d="M50 12l20 26H30z"/><path class="as" d="M26 60l-8 10M74 60l8 10" stroke-width="5"/>',
		'arrow-down'   => '<path class="i" d="M50 16v58"/><path class="if" d="M50 88L30 62h40z"/><path class="as" d="M22 30q10 6 20 0M58 30q10 6 20 0" stroke-width="5"/>',
		'spring'       => '<path class="i" d="M30 84q40-6 0-12t0-12 0-12 0-12 0-12 0-12q40-6 40 0"/><path class="a" d="M64 20l8-8 2 10zM64 34l10 4-8 6z"/>',
		'spiral'       => '<path class="i" d="M50 50a4 4 0 0 1 8 0 10 10 0 0 1-16 8 16 16 0 0 1 8-30 22 22 0 0 1 22 26 28 28 0 0 1-44 20" stroke-width="6"/><circle class="a" cx="78" cy="30" r="5"/>',
		'scribble'     => '<path class="i" d="M14 62q12-40 26-14t18-26 22 16 10 30" stroke-width="6"/><path class="as" d="M18 30l10 8M76 76l8 6" stroke-width="5"/>',
		'question'     => '<path class="i" d="M34 36a16 16 0 1 1 22 15q-6 4-6 12"/><circle class="if" cx="50" cy="78" r="5"/><path class="as" d="M74 24a8 8 0 1 1 10 8q-3 2-3 6M80 48v4" stroke-width="5"/>',
		'ellipsis'     => '<circle class="if" cx="26" cy="52" r="8"/><circle class="a" cx="50" cy="52" r="8"/><circle class="if" cx="74" cy="52" r="8"/><path class="i" d="M18 76h64" stroke-width="4"/>',
		'steam'        => '<path class="i" d="M30 70q0-14 12-12t12 12M46 40q-6-10 2-16t2-16M62 40q-6-10 2-16t2-16" stroke-width="6"/><path class="a" d="M22 82h56v6H22z"/>',
		'thought-lines' => '<path class="i" d="M20 66q30-40 60 0"/><circle class="a" cx="30" cy="80" r="5"/><circle class="a" cx="20" cy="90" r="3"/><path class="i" d="M34 44h32M40 32h20" stroke-width="5"/>',
		// ---- celestial / plant ----
		'moon'         => '<path class="if" d="M58 14a34 34 0 1 0 28 52 26 26 0 0 1-28-52z"/><path class="a" d="M74 26l2 6 6 2-6 2-2 6-2-6-6-2 6-2z"/>',
		'moon-zzz'     => '<path class="if" d="M40 22a26 26 0 1 0 22 40 20 20 0 0 1-22-40z"/><path class="as" d="M62 18h14l-14 14h14M74 42h8l-8 8h8" stroke-width="5"/>',
		'windmill'     => '<path class="i" d="M50 46v42M36 88h28"/><circle class="if" cx="50" cy="44" r="6"/><path class="a" d="M50 44L30 22l14-8zM50 44l22-20 8 14zM50 44l20 22-14 8zM50 44L28 64l-8-14z"/><path class="i" d="M50 44L30 22l14-8zM50 44l22-20 8 14zM50 44l20 22-14 8zM50 44L28 64l-8-14z" stroke-width="4"/>',
		'flower'       => '<circle class="a" cx="50" cy="40" r="9"/><path class="i" d="M50 22a9 9 0 0 1 0 18 9 9 0 0 1 0-18zM32 40a9 9 0 0 1 18 0 9 9 0 0 1-18 0zM50 40a9 9 0 0 1 18 0 9 9 0 0 1-18 0zM50 40a9 9 0 0 1 0 18 9 9 0 0 1 0-18z" stroke-width="5"/><path class="i" d="M50 58v28M50 74q-12-2-16-12M50 80q12-2 16-12" stroke-width="5"/>',
		'sprout'       => '<path class="i" d="M50 86V46"/><path class="a" d="M50 48q-2-22-26-22 2 22 26 22zM50 40q2-20 24-20-2 20-24 20z"/><path class="i" d="M50 48q-2-22-26-22 2 22 26 22zM50 40q2-20 24-20-2 20-24 20z" stroke-width="4"/>',
		'waves'        => '<path class="i" d="M12 40q12-12 25 0t25 0 25 0M12 60q12-12 25 0t25 0 25 0" stroke-width="6"/><path class="as" d="M20 80q12-12 25 0t25 0" stroke-width="6"/>',
		'wilted'       => '<path class="i" d="M30 86q20-30 44-40"/><path class="a" d="M74 46q6-20-6-30-14 10-8 30 6 4 14 0z"/><path class="i" d="M74 46q6-20-6-30-14 10-8 30 6 4 14 0z" stroke-width="4"/>',
	);
	return $lib;
}

/**
 * One motif's markup, or '' for an unknown id.
 *
 * @param string $id Motif id.
 * @return string
 */
function motif( string $id ): string {
	return library()[ $id ] ?? '';
}
