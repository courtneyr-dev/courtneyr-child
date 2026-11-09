<?php
/**
 * RSVP archive paint and shared presentation helpers.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Rsvp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND           = 'rsvp';
const ENTRY_BLOCK    = 'post-kinds-indieweb/rsvp-entry';
const ENTRY_STYLE    = 'cr-rsvp-wall';
const ENTRY_CLASS    = 'is-style-cr-rsvp-wall';
const STYLE_HANDLE   = 'courtneyr-rsvp';
const PAPERS         = array( 'periwinkle', 'sky-blue', 'light-orange', 'blue-green', 'ut-orange', 'light-gray' );
const STICKER_PAPERS = array(
	'no'         => 'periwinkle',
	'maybe'      => 'periwinkle',
	'interested' => 'blue-green',
	'remote'     => 'ut-orange',
);

/** Whether the current request is the RSVP archive. */
function is_rsvp_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether the current request is an RSVP single. */
function is_rsvp_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/** Whether the current request is a Stream surface. */
function is_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
}

/**
 * Resolve a Query Loop post ID.
 *
 * @param mixed $instance Block instance.
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Whether a className attribute contains one complete token.
 *
 * @param string $class_name Class attribute value.
 * @param string $wanted     Token to find.
 */
function has_class_name( string $class_name, string $wanted ): bool {
	$tokens = array_filter( explode( ' ', str_replace( array( "\t", "\n", "\r" ), ' ', $class_name ) ) );
	return in_array( $wanted, $tokens, true );
}

/** Register the navigation dependency when the nav module has not done so. */
function register_nav_dependency(): void {
	if ( function_exists( 'wp_style_is' ) && wp_style_is( 'courtneyr-nav', 'registered' ) ) {
		return;
	}
	if ( ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) || ! defined( 'COURTNEYR_CHILD_DIR' ) || ! function_exists( 'wp_register_style' ) ) {
		return;
	}
	wp_register_style( 'courtneyr-nav', COURTNEYR_CHILD_URI . '/assets/css/cr-nav.css', array(), COURTNEYR_CHILD_VERSION );
	if ( function_exists( 'wp_style_add_data' ) ) {
		wp_style_add_data( 'courtneyr-nav', 'path', COURTNEYR_CHILD_DIR . '/assets/css/cr-nav.css' );
	}
}

/** Register the archive entry style and attach its editor stylesheet. */
function register_block_styles(): void {
	if ( ! class_exists( '\\WP_Block_Type_Registry' ) || ! \WP_Block_Type_Registry::get_instance()->is_registered( ENTRY_BLOCK ) ) {
		return;
	}

	register_block_style(
		ENTRY_BLOCK,
		array(
			'name'  => ENTRY_STYLE,
			'label' => __( 'Poster and stub wall', 'courtneyr-child' ),
		)
	);

	register_nav_dependency();
	wp_enqueue_block_style(
		ENTRY_BLOCK,
		array(
			'handle' => STYLE_HANDLE,
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-rsvp.css',
			'deps'   => array( 'courtneyr-paper', 'courtneyr-nav' ),
			'ver'    => COURTNEYR_CHILD_VERSION,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles', 20 );

/**
 * Return a stable palette paper for one RSVP.
 *
 * @param int    $post_id Post ID.
 * @param string $status  RSVP status whose sticker must stay distinct.
 */
function paper( int $post_id, string $status = '' ): string {
	$paper = PAPERS[0];
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		$seeded = \Courtneyr\Child\Stamps\seeded_paper( $post_id, PAPERS );
		if ( in_array( $seeded, PAPERS, true ) ) {
			$paper = $seeded;
		}
	}
	$sticker_paper = STICKER_PAPERS[ $status ] ?? '';
	if ( $paper !== $sticker_paper ) {
		return $paper;
	}
	$index = array_search( $paper, PAPERS, true );
	return PAPERS[ ( (int) $index + 1 ) % count( PAPERS ) ];
}

/**
 * Pick a poster tilt and prevent an adjacent repeat.
 *
 * @param int      $post_id  Post ID.
 * @param int|null $previous Previous poster tilt.
 */
function poster_tilt( int $post_id, ?int $previous = null ): int {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		$tilt = \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( (string) $post_id ), 6, 4 );
	} else {
		$tilt = abs( $post_id ) % 4;
	}
	return null !== $previous && $tilt === $previous ? ( $tilt + 1 ) % 4 : $tilt;
}

/**
 * Paint the plugin entry without changing its content or privacy boundary.
 *
 * @param string               $html     Rendered RSVP entry.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function entry_paint( string $html, array $block, $instance ): string {
	static $previous_tilt = null;

	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( '' === $html || ! has_class_name( $class_name, ENTRY_CLASS ) || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$tags    = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pkiw-rsvp-entry',
		)
	) ) {
		return $html;
	}

	$status = (string) $tags->get_attribute( 'data-pkiw-rsvp-status' );
	$paper  = $tags->has_class( 'pkiw-rsvp-entry--protected' ) ? 'light-gray' : paper( $post_id, $status );
	$tags->set_attribute( 'style', '--cr-rsvp-paper:var(--cr-' . $paper . ')' );
	if ( $tags->has_class( 'pkiw-rsvp-entry--upcoming' ) && ! $tags->has_class( 'pkiw-rsvp-entry--protected' ) ) {
		$tilt = poster_tilt( $post_id, $previous_tilt );
		$tags->add_class( 'cr-rsvp-tilt-' . $tilt );
		$previous_tilt = $tilt;
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_post-kinds-indieweb/rsvp-entry', __NAMESPACE__ . '\\entry_paint', 10, 3 );

/** Load RSVP paint on every surface that can render RSVP objects. */
function enqueue(): void {
	if ( ! is_admin() && ! is_rsvp_archive() && ! is_rsvp_single() && ! is_stream_surface() ) {
		return;
	}
	register_nav_dependency();
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-rsvp.css',
		array( 'courtneyr-paper', 'courtneyr-nav' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue' );

/**
 * Keep the conditional RSVP sheet out of shared used CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return string[]
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-rsvp.css';
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20 );
