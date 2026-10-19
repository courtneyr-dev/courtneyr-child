<?php
/**
 * `php tests/php/photo-css-test.php`: static contract checks for
 * assets/css/cr-photo.css (issue 241): tokens only, no image filter, hard
 * shadows, the text floor, the counter, the breakpoints and the dark,
 * forced-colors and reduced-motion states.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme_dir = dirname( __DIR__, 2 );
$path      = $theme_dir . '/assets/css/cr-photo.css';
$failures  = array();

/** Print one named check. */
function check_case( string $name, callable $test ): void {
	global $failures;
	try {
		$ok = (bool) $test();
	} catch ( Throwable $error ) {
		$ok   = false;
		$name = $name . ' (' . $error->getMessage() . ')';
	}
	echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
	if ( ! $ok ) {
		$failures[] = $name;
	}
}

if ( ! file_exists( $path ) ) {
	check_case( 'cr-photo.css exists', static fn(): bool => false );
	echo count( $failures ) . " failed\n";
	exit( 1 );
}

$css = (string) file_get_contents( $path );
// Comments out, so a word in a comment never counts as a declaration.
$code = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

/** All declarations of one property: value strings. */
function declarations( string $code, string $property ): array {
	preg_match_all( '/(?<![\w-])' . preg_quote( $property, '/' ) . '\s*:\s*([^;{}]+);/', $code, $found );
	return array_map( 'trim', $found[1] );
}

/** The body of the first rule whose selector list contains $needle. */
function rule_body( string $code, string $needle ): string {
	if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $code, $rules, PREG_SET_ORDER ) ) {
		foreach ( $rules as $rule ) {
			if ( false !== strpos( $rule[1], $needle ) ) {
				return $rule[2];
			}
		}
	}
	return '';
}

check_case(
	'no raw color, filter, blend, blur, transition or animation',
	static function () use ( $code ): bool {
		return ! preg_match( '/#[0-9a-fA-F]{3,8}\b/', $code )
			&& ! preg_match( '/\b(rgb|rgba|hsl|hsla)\(/i', $code )
			&& ! preg_match( '/(?<![\w-])(filter|backdrop-filter|mix-blend-mode|background-blend-mode)\s*:/', $code )
			&& ! preg_match( '/blur\(/i', $code )
			&& ! preg_match( '/(?<![\w-])(transition|animation|animation-name|scroll-behavior)\s*:/', $code );
	}
);

check_case(
	'no filter or blend or tint overlay reaches an image selector',
	static function () use ( $code ): bool {
		return 0 === preg_match( '/img[^{}]*\{[^{}]*(filter|mix-blend-mode|opacity)\s*:/', $code );
	}
);

check_case(
	'every shadow is a hard-shadow token, the focus halo, or none',
	static function () use ( $code ): bool {
		foreach ( declarations( $code, 'box-shadow' ) as $value ) {
			$ok = 'none' === $value
				|| 1 === preg_match( '/^var\(--cr-shadow-hard(-sm|-2)?\)$/', $value )
				|| 1 === preg_match( '/^0 0 0 [78]px var\(--cr-focus-halo\)$/', $value );
			if ( ! $ok ) {
				return false;
			}
		}
		return count( declarations( $code, 'box-shadow' ) ) > 0;
	}
);

check_case(
	'type is one of the four voices and never below the text floor',
	static function () use ( $code ): bool {
		foreach ( declarations( $code, 'font-family' ) as $value ) {
			if ( ! preg_match( '/^var\(--cr-font-(accent|body|mono|display)\)$/', $value ) && 'inherit' !== $value ) {
				return false;
			}
		}
		foreach ( declarations( $code, 'font-size' ) as $value ) {
			if ( preg_match( '/^var\(--cr-text-(xs|sm|base|md|lg|xl|2xl)\)$/', $value ) || 'inherit' === $value ) {
				continue;
			}
			if ( preg_match( '/^(\d*\.?\d+)rem$/', $value, $m ) && (float) $m[1] >= 0.75 ) {
				continue;
			}
			return false;
		}
		return true;
	}
);

check_case(
	'the frame counter restarts per sheet and carries empty alternative text',
	static function () use ( $css ): bool {
		return false !== strpos( $css, 'counter-reset: cr-frame' )
			&& false !== strpos( $css, 'counter-increment: cr-frame' )
			&& false !== strpos( $css, 'content: counter(cr-frame, decimal-leading-zero) / "";' )
			&& false !== strpos( $css, 'content: counter(cr-frame, decimal-leading-zero);' );
	}
);

check_case(
	'4, 3 and 2 frames across at 64rem, 40rem and below, one size per breakpoint',
	static function () use ( $code ): bool {
		return false !== strpos( $code, '@media (min-width: 64rem)' )
			&& false !== strpos( $code, '@media (min-width: 40rem)' )
			&& 1 === preg_match( '/flex:\s*0 0 50%/', $code )
			&& 1 === preg_match( '/flex:\s*0 0 33\.3333%/', $code )
			&& 1 === preg_match( '/flex:\s*0 0 25%/', $code )
			&& ! preg_match( '/flex-grow\s*:\s*[1-9]/', $code )
			&& ! preg_match( '/flex:\s*1\b/', $code );
	}
);

check_case(
	'month heading text is upright and only its tape pseudo-element rotates, by token',
	static function () use ( $code ): bool {
		$heading = rule_body( $code, '.pkiw-group__heading' );
		if ( '' === $heading ) {
			preg_match( '/\.cr-photo-sheets \.pkiw-group__heading\s*\{([^{}]*)\}/', $code, $m );
			$heading = $m[1] ?? '';
		}
		if ( '' === $heading || 1 === preg_match( '/(?<![\w-])transform\s*:/', $heading ) || false === strpos( $heading, 'var(--cr-font-mono)' ) ) {
			return false;
		}
		preg_match( '/\.pkiw-group__heading::before\s*\{([^{}]*)\}/', $code, $tape );
		return isset( $tape[1] ) && 1 === preg_match( '/transform:\s*rotate\(var\(--cr-rotate-(1|2|neg|neg2)\)\)/', $tape[1] ) && false !== strpos( $tape[1], 'var(--cr-tape)' );
	}
);

check_case(
	'every rotation is a token',
	static function () use ( $code ): bool {
		foreach ( declarations( $code, 'transform' ) as $value ) {
			if ( 'none' === $value ) {
				continue;
			}
			if ( ! preg_match( '/^rotate\(var\(--cr-rotate-(1|2|neg|neg2)\)\)$/', $value ) ) {
				return false;
			}
		}
		return true;
	}
);

check_case(
	'the frame link covers the frame and takes the 3px yellow ring with a halo on its pseudo-element',
	static function () use ( $code ): bool {
		return 1 === preg_match( '/\.cr-photo-frame__title a::after\s*\{[^{}]*position:\s*absolute;[^{}]*inset:\s*0;/', $code )
			&& 1 === preg_match( '/a:focus-visible::after\s*\{[^{}]*outline:\s*3px solid var\(--cr-focus-ring\);[^{}]*box-shadow:\s*0 0 0 7px var\(--cr-focus-halo\)/', $code );
	}
);

check_case(
	'the projector paints the mount ivory with violet text and a hard shadow, and never hides the [hidden] slides',
	static function () use ( $code ): bool {
		$mount = rule_body( $code, '.pkiw-projector__mount {' );
		if ( '' === $mount ) {
			preg_match( '/body\.cr-photo-single [^{}]*\.pkiw-projector__mount\s*\{([^{}]*)\}/', $code, $m );
			$mount = $m[1] ?? '';
		}
		return false !== strpos( $mount, 'background-color: var(--cr-printer-ivory)' )
			&& false !== strpos( $mount, 'color: var(--cr-russian-violet)' )
			&& false !== strpos( $mount, 'box-shadow: var(--cr-shadow-hard)' )
			&& false !== strpos( $mount, 'border-radius:' )
			&& ! preg_match( '/(?<![\w-])display\s*:/', $mount )
			&& 1 === preg_match( '/\[hidden\]\s*\{\s*display:\s*none;/', $code );
	}
);

check_case(
	'controls are cerulean with ivory text, 44px, and aria-disabled ends read as disabled',
	static function () use ( $code ): bool {
		return 1 === preg_match( '/\.pkiw-projector__button\s*\{[^{}]*background-color:\s*var\(--cr-cerulean\);[^{}]*color:\s*var\(--cr-printer-ivory\);/', $code )
			&& 1 === preg_match( '/min-height:\s*2\.75rem;/', $code )
			&& 1 === preg_match( '/\.pkiw-projector__button\[aria-disabled="true"\]\s*\{/', $code );
	}
);

check_case(
	'the current rail button is outlined selective yellow through aria-current',
	static function () use ( $code ): bool {
		return 1 === preg_match( '/\.pkiw-projector__thumb\[aria-current="true"\]\s*\{[^{}]*outline:\s*3px solid var\(--cr-selective-yellow\)/', $code );
	}
);

check_case(
	'the rail sits right of the mount at 64rem and wraps below, with no horizontal scroll',
	static function () use ( $code ): bool {
		return false !== strpos( $code, 'grid-template-areas:' )
			&& 1 === preg_match( '/@media \(min-width: 64rem\)\s*\{[^@]*grid-template-columns:[^;]*11rem/s', $code )
			&& ! preg_match( '/overflow-x\s*:\s*(scroll|auto)/', $code );
	}
);

check_case(
	'tape and margin pieces are decorative: at most two rotated layers, gone below 40rem',
	static function () use ( $code ): bool {
		return 1 === preg_match( '/@media \(max-width: 39\.99rem\)\s*\{[^@]*\.pkiw-projector::before[^{}]*\{[^{}]*display:\s*none;/s', $code );
	}
);

check_case(
	'the Stream polaroid: white stock, violet anchor corners, hard shadow, 4:5 dashed picture, Rock Salt title',
	static function () use ( $code ): bool {
		$card = rule_body( $code, '.pk-card.cr-polaroid ' );
		$img  = rule_body( $code, '.pk-card.cr-polaroid .pk-media img' );
		$name = rule_body( $code, ':is(.cr-polaroid, .pk-card--gallery) .pk-title' );
		return false !== strpos( $card, 'var(--cr-white);' )
			&& 4 === substr_count( $card, 'var(--cr-russian-violet) 12px, transparent 12px)' )
			&& false !== strpos( $card, 'box-shadow: var(--cr-shadow-hard)' )
			&& false !== strpos( $card, 'width: 94%' )
			&& false !== strpos( $img, 'aspect-ratio: 4 / 5' )
			&& false !== strpos( $img, 'border: 2px dashed color-mix(in srgb, var(--cr-russian-violet) 45%, transparent)' )
			&& false !== strpos( $name, 'font-family: var(--cr-font-display)' );
	}
);

check_case(
	'the photo booth strip: 4:3 frames, a stamped date turned by a token, one title link stretched over the whole print',
	static function () use ( $code ): bool {
		$frame = rule_body( $code, '.pk-card.k-photo.pk-card--gallery .cr-booth__img' );
		$date  = rule_body( $code, '.pk-card.k-photo.pk-card--gallery .pk-stream-date time' );
		$link  = rule_body( $code, ':is(.cr-polaroid, .pk-card--gallery) .pk-title a::after' );
		$root  = rule_body( $code, '.pk-card.k-photo:is(.cr-polaroid, .pk-card--gallery) ' );
		$body  = rule_body( $code, ':is(.cr-polaroid, .pk-card--gallery) > .pk-body' );
		return false !== strpos( $root, 'position: relative' )
			&& false !== strpos( $body, 'position: static' )
			&& false !== strpos( $frame, 'aspect-ratio: 4 / 3' )
			&& false !== strpos( $date, 'font-family: var(--cr-font-mono)' )
			&& false !== strpos( $date, 'transform: rotate(var(--cr-rotate-1))' )
			&& false !== strpos( $link, 'inset: 0' );
	}
);

check_case(
	'the slide mount is gone from the Stream',
	static function () use ( $code ): bool {
		return false === strpos( $code, 'cr-slide-mount' ) && false === strpos( $code, 'data-cr-rotate' );
	}
);

check_case(
	'dark: the rail takes the soft violet in both dark blocks; the mount stays ivory',
	static function () use ( $code ): bool {
		return 1 === preg_match( '/:root\[data-theme="dark"\][^{}]*\.pkiw-projector__rail[^{}]*\{[^{}]*var\(--cr-russian-violet-soft\)/', $code )
			&& 1 === preg_match( '/@media \(prefers-color-scheme: dark\)\s*\{[^@]*:root:not\(\[data-theme="light"\]\)[^{}]*\.pkiw-projector__rail[^{}]*\{[^{}]*var\(--cr-russian-violet-soft\)/s', $code )
			&& ! preg_match( '/:root\[data-theme="dark"\][^{}]*\.pkiw-projector__mount\s*\{[^{}]*background/', $code );
	}
);

check_case(
	'forced colors: 2px CanvasText outlines, ButtonFace and ButtonText controls, decoration gone, photos kept',
	static function () use ( $code ): bool {
		if ( ! preg_match( '/@media \(forced-colors: active\)\s*\{(.*)\}\s*$/s', $code, $m ) ) {
			return false;
		}
		$forced = $m[1];
		return false !== strpos( $forced, 'CanvasText' )
			&& false !== strpos( $forced, 'ButtonFace' )
			&& false !== strpos( $forced, 'ButtonText' )
			&& 1 === preg_match( '/\.pkiw-projector::before[^{}]*\{[^{}]*display:\s*none;/', $forced )
			&& 1 === preg_match( '/\.pkiw-group__heading::before[^{}]*\{[^{}]*display:\s*none;/', $forced )
			&& ! preg_match( '/img[^{}]*\{[^{}]*display:\s*none/', $forced );
	}
);

check_case(
	'the sleeve is hidden while an image shows and appears for no image or a failed one',
	static function () use ( $code ): bool {
		return 1 === preg_match( '/\.cr-photo-frame__sleeve\s*\{[^{}]*display:\s*none;/', $code )
			&& 1 === preg_match( '/\.cr-photo-frame__media--paper \.cr-photo-frame__sleeve,\s*\.cr-photo-frame__media\.cr-cover--failed \.cr-photo-frame__sleeve\s*\{[^{}]*display:\s*grid;/', $code );
	}
);

check_case(
	'frame and mount photos keep their colors: no img rule sets a filter and every frame image is object-fit cover at 3:2',
	static function () use ( $code ): bool {
		return 1 === preg_match( '/aspect-ratio:\s*3 \/ 2;/', $code ) && 1 === preg_match( '/object-fit:\s*cover;/', $code );
	}
);

check_case(
	'frame rules beat the plugin card base: the root keys on .pk-card.cr-photo-frame and drops its tint, the title and screen-reader text key on the frame',
	static function () use ( $code ): bool {
		preg_match( '/(?:^|[}\s])\.pk-card\.cr-photo-frame\s*\{([^{}]*)\}/', $code, $found );
		$root = $found[1] ?? '';
		if ( '' === $root
			|| false === strpos( $root, 'grid-template-columns: minmax(0, 1fr)' )
			|| false === strpos( $root, 'padding: 0' )
			|| false === strpos( $root, 'border: 0' )
			|| false === strpos( $root, 'background: none' )
			|| false === strpos( $root, 'box-shadow: none' )
			|| false === strpos( $root, 'gap: 0' )
			|| false === strpos( $root, 'align-items: stretch' ) ) {
			return false;
		}
		if ( 1 !== preg_match( '/\.pk-card\.cr-photo-frame::before\s*\{[^{}]*content:\s*none;/', $code ) ) {
			return false;
		}
		// No frame selector may stand at the plugin card's one-class weight.
		foreach ( array( '.cr-photo-frame', '.cr-photo-frame--protected', '.cr-photo-frame__title', '.cr-photo-frame__title--object', '.cr-photo-frame__sr' ) as $bare ) {
			if ( 1 === preg_match( '/(^|[{};,])\s*' . preg_quote( $bare, '/' ) . '\s*\{/', $code ) ) {
				return false;
			}
		}
		return false !== strpos( $code, '.pk-card.cr-photo-frame.cr-photo-frame--protected {' )
			&& false !== strpos( $code, '.cr-photo-frame .cr-photo-frame__title {' )
			&& false !== strpos( $code, '.cr-photo-frame .cr-photo-frame__sr {' );
	}
);

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '  ' . $failure . "\n" );
	}
	echo count( $failures ) . " failed\n";
	exit( 1 );
}

echo "all passed\n";
