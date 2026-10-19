<?php
/**
 * `php tests/php/wish-single-test.php`: standalone wish single checks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['cr_hooks'] = array();
	$GLOBALS['cr_single'] = false;
	$GLOBALS['cr_terms'] = array();
	$GLOBALS['cr_facts'] = array();

	class WP_Post {
		public $ID;
		public function __construct( int $id ) {
			$this->ID = $id;
		}
	}

	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][ (int) $priority ][] = array( $callback, (int) $args );
		return true;
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['cr_hooks'][ $hook ] ?? array() as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				$value = call_user_func_array( $registered[0], array_slice( array_merge( array( $value ), $args ), 0, $registered[1] ) );
			}
		}
		return $value;
	}
	function has_term( $term, $taxonomy, $post ) {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return 'wish' === $term && 'kind' === $taxonomy && ! empty( $GLOBALS['cr_terms'][ $id ] );
	}
}

namespace Courtneyr\Child\Wish {
	function is_wish_single(): bool {
		return (bool) $GLOBALS['cr_single'];
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return (array) ( $GLOBALS['cr_facts'][ $post_id ] ?? array() );
	}
}

namespace {
	require dirname( __DIR__, 2 ) . '/inc/single-wish.php';
	$failures = array();

	function cr_wish_single_check( string $name, callable $test ): void {
		global $failures;
		$GLOBALS['cr_case_count'] = ( $GLOBALS['cr_case_count'] ?? 0 ) + 1;
		try {
			$ok = (bool) $test();
		} catch ( Throwable $error ) {
			$ok = false;
			$name .= ' (' . $error->getMessage() . ')';
		}
		echo $ok ? 'PASS ' : 'FAIL ', $name, "\n";
		if ( ! $ok ) {
			$failures[] = $name;
		}
	}

	cr_wish_single_check(
		'body class appears only on wish singles',
		static function (): bool {
			$GLOBALS['cr_single'] = false;
			$plain = apply_filters( 'body_class', array( 'single' ) );
			$GLOBALS['cr_single'] = true;
			$wish = apply_filters( 'body_class', array( 'single' ) );
			return ! in_array( 'cr-wish-single', $plain, true ) && 1 === count( array_keys( $wish, 'cr-wish-single', true ) );
		}
	);

	cr_wish_single_check(
		'repeat kinds adds wish once and preserves non-arrays',
		static function (): bool {
			$once  = apply_filters( 'courtneyr_child_featured_repeat_kinds', array( 'craft' ) );
			$twice = apply_filters( 'courtneyr_child_featured_repeat_kinds', $once );
			return 1 === count( array_keys( $twice, 'wish', true ) ) && 'nope' === apply_filters( 'courtneyr_child_featured_repeat_kinds', 'nope' );
		}
	);

	cr_wish_single_check(
		'featured repeats follows wish picture facts only',
		static function (): bool {
			$wish = new WP_Post( 10 );
			$note = new WP_Post( 11 );
			$GLOBALS['cr_terms'][10] = true;
			$GLOBALS['cr_facts'][10] = array( 'picture' => array( 'suppress_featured' => true ) );
			$yes = apply_filters( 'courtneyr_child_featured_repeats', false, $wish );
			$GLOBALS['cr_facts'][10] = array( 'picture' => array( 'suppress_featured' => false ) );
			$no = apply_filters( 'courtneyr_child_featured_repeats', false, $wish );
			$GLOBALS['cr_facts'][10] = array();
			$empty = apply_filters( 'courtneyr_child_featured_repeats', false, $wish );
			return $yes && ! $no && ! $empty && ! apply_filters( 'courtneyr_child_featured_repeats', false, $note ) && apply_filters( 'courtneyr_child_featured_repeats', true, $note );
		}
	);

	cr_wish_single_check(
		'editor parity adds the wish class and no single-object H1 hook exists',
		static function (): bool {
			$registry = apply_filters( 'courtneyr_child_editor_parity_kinds', array() );
			return in_array( 'cr-wish-single', $registry['wish']['classes'] ?? array(), true )
				&& ! isset( $GLOBALS['cr_hooks']['courtneyr_child_single_object_holds_h1'] );
		}
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : ( $GLOBALS['cr_case_count'] ?? 0 ) . ' passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
