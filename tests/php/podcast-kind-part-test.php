<?php
/**
 * `php tests/php/podcast-kind-part-test.php`: checks podcast suppression in
 * the single kind-part router without WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['cr_state'] = array(
		'terms'    => array(),
		'parts'    => array( 'kind-listen', 'kind-watch' ),
		'singular' => true,
		'queried'  => 0,
		'facts'    => array(),
		'hooks'    => array(),
	);

	function sanitize_key( $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_state']['hooks'][ $hook ][] = array( $callback, $priority, $accepted_args );
		return true;
	}

	function get_the_terms( $post_id, $taxonomy ) {
		return $GLOBALS['cr_state']['terms'][ $post_id ] ?? false;
	}

	function get_terms() {
		return array();
	}

	function get_stylesheet(): string {
		return 'courtneyr-child';
	}

	function get_block_template( $id, $type = 'wp_template' ) {
		$slug = substr( (string) $id, strlen( 'courtneyr-child//' ) );
		return 'wp_template_part' === $type && in_array( $slug, $GLOBALS['cr_state']['parts'], true ) ? (object) array( 'slug' => $slug ) : null;
	}

	function is_singular( $post_type = '' ): bool {
		return $GLOBALS['cr_state']['singular'] && ( '' === $post_type || 'post' === $post_type );
	}

	function get_queried_object_id(): int {
		return $GLOBALS['cr_state']['queried'];
	}

	function render_block( array $block ): string {
		return $block['attrs']['slug'];
	}

	require dirname( __DIR__, 2 ) . '/inc/kind-parts.php';
}

namespace Courtneyr\Child\PodcastKindPartTests {

	use function Courtneyr\Child\KindParts\route;

	$GLOBALS['cr_failures'] = array();
	$GLOBALS['cr_block']    = array(
		'blockName' => 'core/template-part',
		'attrs'     => array( 'slug' => \Courtneyr\Child\KindParts\ROUTER_SLUG ),
	);

	function check( bool $ok, string $message ): void {
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$GLOBALS['cr_failures'][] = $message;
		}
	}

	function set_post( int $id, string $kind, bool $singular = true ): void {
		$GLOBALS['cr_state']['queried']    = $id;
		$GLOBALS['cr_state']['singular']   = $singular;
		$GLOBALS['cr_state']['terms'][ $id ] = array( (object) array( 'slug' => $kind ) );
	}

	set_post( 1, 'listen' );
	check( 'kind-listen' === route( null, $GLOBALS['cr_block'] ), 'without the plugin, listen routing is unchanged' );
	set_post( 2, 'watch' );
	check( 'kind-watch' === route( null, $GLOBALS['cr_block'] ), 'without the plugin, watch routing is unchanged' );
}

namespace PKIW {
	if ( ! function_exists( __NAMESPACE__ . '\\kind_facts' ) ) {
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_state']['facts'][ $post_id ] ?? array();
		}
	}
}

namespace Courtneyr\Child\PodcastKindPartTests {
	use function Courtneyr\Child\KindParts\route;

	set_post( 3, 'listen' );
	$GLOBALS['cr_state']['facts'][3] = array( 'is_podcast' => true );
	check( '' === route( null, $GLOBALS['cr_block'] ), 'podcast listen renders no kind part' );

	$cases = array(
		4 => array( 'is_podcast' => false ),
		5 => array( 'name' => 'Old facts' ),
		6 => array(),
	);
	foreach ( $cases as $id => $facts ) {
		set_post( $id, 'listen' );
		$GLOBALS['cr_state']['facts'][ $id ] = $facts;
		check( 'kind-listen' === route( null, $GLOBALS['cr_block'] ), "listen post $id keeps kind-listen" );
	}

	set_post( 7, 'watch' );
	$GLOBALS['cr_state']['facts'][7] = array( 'is_podcast' => false );
	check( 'kind-watch' === route( null, $GLOBALS['cr_block'] ), 'watch routing is unchanged' );

	set_post( 8, 'listen', false );
	$GLOBALS['cr_state']['facts'][8] = array( 'is_podcast' => true );
	check( '' === route( null, $GLOBALS['cr_block'] ), 'podcast outside a singular request renders nothing' );
	check( ! empty( $GLOBALS['cr_state']['hooks']['pre_render_block'] ), 'router remains a pre_render_block filter' );

	echo "\n", count( $GLOBALS['cr_failures'] ) ? count( $GLOBALS['cr_failures'] ) . ' failed' : 'all passed', "\n";
	exit( count( $GLOBALS['cr_failures'] ) ? 1 : 0 );
}
