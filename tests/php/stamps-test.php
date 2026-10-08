<?php
/**
 * `php tests/php/stamps-test.php`: checks inc/stamps.php without WordPress.
 *
 * seeded_paper() (P26) and the big-line fit guard (P21). Stubs only the two
 * escaping functions the file calls. Exits 1 on the first failed group.
 *
 * `php tests/php/stamps-test.php --svg` prints the stamps the Playwright
 * stamp-fit test measures, as JSON.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	/**
	 * Stub: WordPress's attribute escaping.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Stub: WordPress's HTML escaping.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	require dirname( __DIR__, 2 ) . '/inc/stamps.php';
}

namespace Courtneyr\Child\Stamps\Tests {

	use function Courtneyr\Child\Stamps\pick;
	use function Courtneyr\Child\Stamps\render;
	use function Courtneyr\Child\Stamps\seed;
	use function Courtneyr\Child\Stamps\seeded_paper;

	/**
	 * Every stamp the theme draws today, by adapter, plus the plugin's read
	 * labels the read lanes switch to (G7). Key: shape|glyph|big.
	 */
	const SPECS = array(
		'shipped' => array(
			array( 'seal', 'pin', 'ARRIVED' ),
			array( 'octagon', 'pin', 'ARRIVED' ),
			array( 'rect', '', 'CHECKED IN' ),
			array( 'seal', 'pine', '36' ),
			array( 'seal', 'camera', '128' ),
			array( 'octagon', 'film', 'ON FILM' ),
			array( 'rect', 'camera', 'PHOTO LOG' ),
			array( 'seal', '', 'Watched' ),
			array( 'seal', '', 'Reading' ),
			array( 'seal', '', 'Finished' ),
			array( 'seal', '', 'Set aside' ),
			array( 'seal', '', 'To read' ),
			array( 'rect', '', 'Finished' ),
			array( 'rect', '', 'Set aside' ),
		),
		'labels'  => array(
			array( 'seal', '', 'Currently Reading' ),
			array( 'seal', '', 'To Read' ),
			array( 'seal', '', 'Abandoned' ),
			array( 'rect', '', 'Currently Reading' ),
			array( 'rect', '', 'Abandoned' ),
			array( 'octagon', '', 'Currently Reading' ),
			array( 'rect', 'camera', 'Currently Reading' ),
		),
	);

	/**
	 * Draw one spec from SPECS.
	 *
	 * @param array{0: string, 1: string, 2: string} $s Shape, glyph, big line.
	 * @param bool                                   $postmark Seal only: draw the postmark waves.
	 * @return string
	 */
	function draw( array $s, bool $postmark = false ): string {
		return render(
			array(
				'shape'    => $s[0],
				'glyph'    => $s[1],
				'big'      => $s[2],
				'mid'      => 'Mid line',
				'small'    => '14 SEP 2026',
				'ring'     => 'Reading record',
				'postmark' => $postmark,
				'uid'      => 'cr-test-' . md5( implode( '|', $s ) . ( $postmark ? 'p' : '' ) ),
			)
		);
	}

	/**
	 * The big line's text element.
	 *
	 * @param string $svg Stamp markup.
	 * @return string
	 */
	function big_text( string $svg ): string {
		return preg_match( '/<text[^>]*class="cr-stamp__big[^"]*"[^>]*>/', $svg, $m ) ? $m[0] : '';
	}

	if ( in_array( '--svg', $argv, true ) ) {
		$out = array();
		foreach ( SPECS as $group => $specs ) {
			foreach ( $specs as $s ) {
				$out[] = array(
					'group' => $group,
					'key'   => implode( '|', $s ),
					'svg'   => draw( $s ),
				);
				if ( 'seal' === $s[0] ) {
					$out[] = array(
						'group' => $group,
						'key'   => implode( '|', $s ) . '|postmark',
						'svg'   => draw( $s, true ),
					);
				}
			}
		}
		echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
		exit( 0 );
	}

	$failures = array();

	/**
	 * Record a failed check.
	 *
	 * @param bool   $ok      Whether the check passed.
	 * @param string $message What was checked.
	 * @return void
	 */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	// ---- seeded_paper(): P26, a post-ID crc32 pick from the caller's set ----
	$set = array( 'sky-blue', 'periwinkle', 'light-orange', 'selective-yellow', 'ut-orange', 'printer-ivory' );
	check( function_exists( 'Courtneyr\Child\Stamps\seeded_paper' ), 'seeded_paper() exists' );
	if ( function_exists( 'Courtneyr\Child\Stamps\seeded_paper' ) ) {
		check( seeded_paper( 37996, $set ) === seeded_paper( 37996, $set ), 'seeded_paper() is the same for the same post on every call' );
		check( seeded_paper( 37996, $set ) === $set[ pick( seed( '37996' ), 9, count( $set ) ) ], 'seeded_paper() is pick( seed( (string) $post_id ), 9, count( $set ) )' );
		$seen = array();
		for ( $id = 1; $id <= 300; $id++ ) {
			$paper = seeded_paper( $id, $set );
			if ( ! in_array( $paper, $set, true ) ) {
				check( false, "seeded_paper( $id ) returns a member of the set, got '$paper'" );
				break;
			}
			$seen[ $paper ] = true;
		}
		check( count( $seen ) === count( $set ), 'seeded_paper() reaches every paper across posts 1-300 (' . count( $seen ) . ' of ' . count( $set ) . ')' );
		check( '' === seeded_paper( 37996, array() ), 'seeded_paper() returns "" for an empty set' );
		check( 'cerulean' === seeded_paper( 37996, array( 'k' => 'cerulean' ) ), 'seeded_paper() accepts a keyed set' );
	}

	// ---- big-line fit guard: P21 ----
	foreach ( SPECS['shipped'] as $s ) {
		foreach ( 'seal' === $s[0] ? array( false, true ) : array( false ) as $postmark ) {
			$text = big_text( draw( $s, $postmark ) );
			check( '' !== $text && false === strpos( $text, 'textLength' ), 'shipped stamp ' . implode( '|', $s ) . ( $postmark ? '|postmark' : '' ) . ' keeps its natural big line: ' . $text );
		}
	}
	foreach ( array( array( 'seal', '', 'Currently Reading' ), array( 'rect', '', 'Currently Reading' ), array( 'octagon', '', 'Currently Reading' ), array( 'rect', 'camera', 'Currently Reading' ) ) as $s ) {
		$text = big_text( draw( $s ) );
		check( 1 === preg_match( '/textLength="\d+(\.\d+)?" lengthAdjust="spacingAndGlyphs"/', $text ), 'long big line ' . implode( '|', $s ) . ' is fitted: ' . $text );
	}
	check( false === strpos( big_text( draw( array( 'seal', '', 'Abandoned' ) ) ), 'textLength' ), 'seal|Abandoned fits the seal without squeezing' );
	check( false === strpos( big_text( draw( array( 'rect', '', 'Abandoned' ) ) ), 'textLength' ), 'rect|Abandoned fits the rect without squeezing' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
