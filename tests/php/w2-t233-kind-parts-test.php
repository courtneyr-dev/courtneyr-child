<?php
/**
 * `php tests/php/w2-t233-kind-parts-test.php`: checks the single template's
 * kind-parts router helpers without WordPress (PKIW issue 233).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['t233'] = array(
		'terms'      => array(),
		'kind_terms' => array(),
		'parts'      => array(),
		'singular'   => true,
		'queried'    => 0,
		'rendered'   => array(),
		'hooks'      => array(),
	);

	class WP_Error {}

	/** WordPress sanitize_key(). */
	function sanitize_key( $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
		$GLOBALS['t233']['hooks'][ $hook ][] = $callback;
		return true;
	}

	/** Terms for a post, set per test. */
	function get_the_terms( $post_id, $taxonomy ) {
		return $GLOBALS['t233']['terms'][ $post_id ] ?? false;
	}

	/** All kind term slugs, set per test. */
	function get_terms( $args ) {
		return $GLOBALS['t233']['kind_terms'];
	}

	/** WordPress is_wp_error(). */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}

	/** Active stylesheet. */
	function get_stylesheet(): string {
		return 'courtneyr-child';
	}

	/** A block template exists only for slugs listed in the test's parts. */
	function get_block_template( $id, $template_type = 'wp_template' ) {
		$slug = substr( (string) $id, strlen( 'courtneyr-child//' ) );
		return ( 'wp_template_part' === $template_type && in_array( $slug, $GLOBALS['t233']['parts'], true ) ) ? (object) array( 'slug' => $slug ) : null;
	}

	/** Singular check, set per test. */
	function is_singular( $post_types = '' ): bool {
		return $GLOBALS['t233']['singular'];
	}

	/** Queried post, set per test. */
	function get_queried_object_id(): int {
		return $GLOBALS['t233']['queried'];
	}

	/** Record the block a render call receives. */
	function render_block( array $block ): string {
		$GLOBALS['t233']['rendered'][] = $block;
		return '<part:' . $block['attrs']['slug'] . '>';
	}

	$GLOBALS['t233_failures'] = 0;

	/** Report one check. */
	function t233_check( string $label, bool $ok ): void {
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
		if ( ! $ok ) {
			++$GLOBALS['t233_failures'];
		}
	}

	/** Reset per-test state. */
	function t233_reset(): void {
		$GLOBALS['t233']['terms']      = array();
		$GLOBALS['t233']['kind_terms'] = array();
		$GLOBALS['t233']['parts']      = array();
		$GLOBALS['t233']['singular']   = true;
		$GLOBALS['t233']['queried']    = 0;
		$GLOBALS['t233']['rendered']   = array();
	}

	require dirname( __DIR__, 2 ) . '/inc/kind-parts.php';
}

namespace {
	use function Courtneyr\Child\KindParts\kind_slug_for_post;
	use function Courtneyr\Child\KindParts\kind_slugs;
	use function Courtneyr\Child\KindParts\part_slug_for;
	use function Courtneyr\Child\KindParts\part_slug_for_kind;
	use function Courtneyr\Child\KindParts\part_slugs;
	use function Courtneyr\Child\KindParts\route;

	t233_reset();
	$GLOBALS['t233']['terms'][5] = array( (object) array( 'slug' => 'watch' ), (object) array( 'slug' => 'listen' ) );
	t233_check( 'kind_slug_for_post returns the first kind term slug', 'watch' === kind_slug_for_post( 5 ) );
	t233_check( 'kind_slug_for_post is empty for a post with no kind terms', '' === kind_slug_for_post( 6 ) );
	$GLOBALS['t233']['terms'][7] = array();
	t233_check( 'kind_slug_for_post is empty for an empty term list', '' === kind_slug_for_post( 7 ) );
	$GLOBALS['t233']['terms'][8] = new WP_Error();
	t233_check( 'kind_slug_for_post is empty for a WP_Error', '' === kind_slug_for_post( 8 ) );
	$GLOBALS['t233']['terms'][9] = array( (object) array( 'slug' => 'Re Ad!' ) );
	t233_check( 'kind_slug_for_post sanitizes the slug', 'read' === kind_slug_for_post( 9 ) );

	t233_reset();
	$GLOBALS['t233']['parts'] = array( 'kind-listen' );
	t233_check( 'part_slug_for_kind returns the part slug when the theme ships the part', 'kind-listen' === part_slug_for_kind( 'listen' ) );
	t233_check( 'part_slug_for_kind is empty when the theme has no part for the kind', '' === part_slug_for_kind( 'watch' ) );
	t233_check( 'part_slug_for_kind is empty for an empty kind', '' === part_slug_for_kind( '' ) );
	t233_check( 'part_slug_for_kind sanitizes the kind', 'kind-listen' === part_slug_for_kind( 'Listen' ) );

	t233_reset();
	$GLOBALS['t233']['parts']    = array( 'kind-listen' );
	$GLOBALS['t233']['terms'][5] = array( (object) array( 'slug' => 'listen' ) );
	$GLOBALS['t233']['terms'][6] = array( (object) array( 'slug' => 'watch' ) );
	t233_check( 'part_slug_for keeps its contract for a kind with a part', 'kind-listen' === part_slug_for( 5 ) );
	t233_check( 'part_slug_for keeps its contract for a kind with no part', '' === part_slug_for( 6 ) );
	t233_check( 'part_slug_for keeps its contract for a post with no kind', '' === part_slug_for( 99 ) );

	t233_reset();
	$GLOBALS['t233']['kind_terms'] = array( 'listen', 'Wa tch!', 'listen', '' );
	t233_check( 'kind_slugs lists the sanitized kind term slugs once each', array( 'listen', 'watch' ) === kind_slugs() );
	$GLOBALS['t233']['kind_terms'] = new WP_Error();
	t233_check( 'kind_slugs is empty when the kind terms cannot be read', array() === kind_slugs() );
	$GLOBALS['t233']['kind_terms'] = array();
	t233_check( 'kind_slugs is empty when there are no kind terms', array() === kind_slugs() );

	t233_reset();
	$GLOBALS['t233']['parts']      = array( 'kind-listen', 'kind-watch', 'kind-orphan' );
	$GLOBALS['t233']['kind_terms'] = array( 'listen', 'watch', 'chicken' );
	t233_check(
		'part_slugs maps each kind term that has a theme part and leaves out the rest',
		array(
			'listen' => 'kind-listen',
			'watch'  => 'kind-watch',
		) === part_slugs()
	);
	$GLOBALS['t233']['kind_terms'] = new WP_Error();
	t233_check( 'part_slugs is empty when the kind terms cannot be read', array() === part_slugs() );
	$GLOBALS['t233']['kind_terms'] = array();
	t233_check( 'part_slugs is empty when there are no kind terms', array() === part_slugs() );

	// The router: unchanged behavior.
	t233_reset();
	$GLOBALS['t233']['parts']    = array( 'kind-listen' );
	$GLOBALS['t233']['terms'][5] = array( (object) array( 'slug' => 'listen' ) );
	$GLOBALS['t233']['terms'][6] = array( (object) array( 'slug' => 'watch' ) );
	$block                       = array(
		'blockName' => 'core/template-part',
		'attrs'     => array( 'slug' => 'kind' ),
	);

	$GLOBALS['t233']['queried'] = 5;
	t233_check( 'route renders the kind part of a singular post', '<part:kind-listen>' === route( null, $block ) );
	t233_check(
		'route renders with the slug swapped and the other attributes kept',
		1 === count( $GLOBALS['t233']['rendered'] ) && 'kind-listen' === $GLOBALS['t233']['rendered'][0]['attrs']['slug']
	);
	t233_check( 'route passes an earlier short-circuit through', 'early' === route( 'early', $block ) );
	t233_check(
		'route ignores other blocks',
		null === route(
			null,
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array( 'slug' => 'kind' ),
			)
		)
	);
	t233_check(
		'route ignores other template parts',
		null === route(
			null,
			array(
				'blockName' => 'core/template-part',
				'attrs'     => array( 'slug' => 'footer' ),
			)
		)
	);

	$GLOBALS['t233']['queried'] = 6;
	t233_check( 'route renders nothing for a kind with no part', '' === route( null, $block ) );
	$GLOBALS['t233']['queried']  = 5;
	$GLOBALS['t233']['singular'] = false;
	t233_check( 'route renders nothing outside a singular post', '' === route( null, $block ) );

	t233_check( 'the router hooks pre_render_block', ! empty( $GLOBALS['t233']['hooks']['pre_render_block'] ) );

	echo $GLOBALS['t233_failures'] ? "\n" . $GLOBALS['t233_failures'] . " failed\n" : "\nall passed\n";
	exit( $GLOBALS['t233_failures'] ? 1 : 0 );
}
