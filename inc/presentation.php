<?php
/**
 * Presentation archive, single record and embed paint.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'presentation';
const ENTRY_BLOCK  = 'post-kinds-indieweb/presentation-entry';
const ENTRY_STYLE  = 'cr-badge-wall';
const STYLE_HANDLE = 'courtneyr-presentation';

/** Whether the current request is the presentation archive. */
function is_presentation_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether the current request is a presentation single. */
function is_presentation_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/** Whether the current request is a Stream surface. */
function is_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
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

/** Register the presentation entry block style and editor paint. */
function register_block_styles(): void {
	if ( ! class_exists( '\\WP_Block_Type_Registry' ) || ! \WP_Block_Type_Registry::get_instance()->is_registered( ENTRY_BLOCK ) ) {
		return;
	}

	register_block_style(
		ENTRY_BLOCK,
		array(
			'name'  => ENTRY_STYLE,
			'label' => __( 'Badge wall', 'courtneyr-child' ),
		)
	);

	register_nav_dependency();
	wp_enqueue_block_style(
		ENTRY_BLOCK,
		array(
			'handle' => STYLE_HANDLE,
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-presentation.css',
			'deps'   => array( 'courtneyr-paper', 'courtneyr-nav' ),
			'ver'    => COURTNEYR_CHILD_VERSION,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles', 20 );

/**
 * Give the queried presentation record its H1 and public date.
 *
 * @param array<string, mixed> $args Record arguments.
 * @param \WP_Post             $post Record post.
 * @return array<string, mixed>
 */
function record_args( array $args, \WP_Post $post ): array {
	if ( is_presentation_single() && get_queried_object_id() === $post->ID ) {
		$args['title_level'] = 1;
		$args['show_date']   = true;
		$args['class']       = 'cr-badge cr-badge--single';
	}
	return $args;
}
add_filter( 'pkiw_presentation_record_args', __NAMESPACE__ . '\\record_args', 10, 2 );

/**
 * Remove the template title when the presentation record owns the H1.
 *
 * @param string               $html     Rendered title.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function drop_post_title( string $html, array $block, $instance ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( ! is_presentation_single() || ! class_exists( '\\PKIW\\Presentation' ) ) {
		return $html;
	}
	$queried_id = get_queried_object_id();
	if ( ! $instance instanceof \WP_Block || (int) ( $instance->context['postId'] ?? 0 ) !== $queried_id || ! empty( $instance->context['queryId'] ) ) {
		return $html;
	}
	$post = get_post( $queried_id );
	if ( ! $post instanceof \WP_Post || post_password_required( $post ) || ! has_term( KIND, 'kind', $post ) ) {
		return $html;
	}
	return '';
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\drop_post_title', 10, 3 );

/**
 * Add presentation chrome and a missing title to supported embeds.
 *
 * @param string               $html  Rendered embed.
 * @param array<string, mixed> $block Parsed block.
 */
function frame_embed( string $html, array $block ): string {
	if ( '' === $html || ! is_presentation_single() || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$provider = sanitize_key( (string) ( $block['attrs']['providerNameSlug'] ?? '' ) );
	$decks    = array( 'speaker-deck' );
	$videos   = array( 'wordpress-tv', 'wordpress-tv-embed', 'videopress', 'vimeo', 'dailymotion', 'youtube' );
	if ( ! in_array( $provider, array_merge( $decks, $videos ), true ) ) {
		return $html;
	}

	$post  = get_post( get_queried_object_id() );
	$title = $post instanceof \WP_Post ? trim( wp_strip_all_tags( get_the_title( $post ) ) ) : '';
	if ( '' === $title ) {
		$title = __( 'Presentation', 'courtneyr-child' );
	}
	if ( in_array( $provider, $decks, true ) ) {
		/* translators: %s: presentation title. */
		$frame_title = sprintf( __( 'Slides: %s', 'courtneyr-child' ), $title );
	} else {
		/* translators: %s: presentation title. */
		$frame_title = sprintf( __( 'Recording: %s', 'courtneyr-child' ), $title );
	}

	$tags  = new \WP_HTML_Tag_Processor( $html );
	$first = true;
	while ( $tags->next_tag() ) {
		if ( $first ) {
			$tags->add_class( 'cr-presentation-frame' );
			$first = false;
		}
		if ( 'IFRAME' === $tags->get_tag() ) {
			if ( ! $tags->get_attribute( 'title' ) ) {
				$tags->set_attribute( 'title', $frame_title );
			}
			if ( ! $tags->get_attribute( 'loading' ) ) {
				$tags->set_attribute( 'loading', 'lazy' );
			}
			break;
		}
	}
	return $first ? $html : $tags->get_updated_html();
}
add_filter( 'render_block_core/embed', __NAMESPACE__ . '\\frame_embed', 20, 2 );

/** Load presentation paint on every surface that can render its objects. */
function enqueue(): void {
	if ( ! is_admin() && ! is_presentation_archive() && ! is_presentation_single() && ! is_stream_surface() ) {
		return;
	}
	register_nav_dependency();
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-presentation.css',
		array( 'courtneyr-paper', 'courtneyr-nav' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue' );

/**
 * Keep conditional presentation sheets out of shared used CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return string[]
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-presentation.css';
	$exclusions[] = 'presentation.css';
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20 );
