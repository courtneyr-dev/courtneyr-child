<?php
/**
 * Follow archive presentation and shared follow helpers.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Follow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'follow';
const ENTRY_BLOCK  = 'post-kinds-indieweb/follow-entry';
const ENTRY_STYLE  = 'cr-rolodex';
const ENTRY_CLASS  = 'is-style-cr-rolodex';
const STYLE_HANDLE = 'courtneyr-follow';
const STYLE_FILE   = 'cr-follow.css';
const XFN_HANDLE   = 'courtneyr-xfn';
const XFN_FILE     = 'cr-xfn.css';
const PAPERS       = array( 'printer-ivory', 'light-orange', 'light-gray', 'periwinkle' );

/** Whether the current request is the follow archive. */
function is_follow_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether the current request is a public follow single. */
function is_follow_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/** Whether the current request is a Stream surface. */
function is_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
}

/**
 * Resolve a block's post ID.
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
 * Read visitor-safe follow facts.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( $post_id < 1 || ! class_exists( '\\PKIW\\Follow_Archive' ) || ! method_exists( '\\PKIW\\Follow_Archive', 'facts' ) ) {
		return array();
	}
	$facts = \PKIW\Follow_Archive::facts( $post_id );
	return is_array( $facts ) ? $facts : array();
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

/**
 * Pick one approved paper from the post ID.
 *
 * @param int $post_id Post ID.
 */
function paper( int $post_id ): string {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		$paper = \Courtneyr\Child\Stamps\seeded_paper( $post_id, PAPERS );
		if ( in_array( $paper, PAPERS, true ) ) {
			return $paper;
		}
	}
	return PAPERS[ abs( $post_id ) % count( PAPERS ) ];
}

/** Register the Rolodex style and attach both block-only stylesheets. */
function register_block_styles(): void {
	if ( ! class_exists( '\\WP_Block_Type_Registry' ) ) {
		return;
	}
	$registry = \WP_Block_Type_Registry::get_instance();
	if ( $registry->is_registered( ENTRY_BLOCK ) ) {
		register_block_style(
			ENTRY_BLOCK,
			array(
				'name'  => ENTRY_STYLE,
				'label' => __( 'Rolodex card', 'courtneyr-child' ),
			)
		);
		wp_enqueue_block_style(
			ENTRY_BLOCK,
			array(
				'handle' => STYLE_HANDLE,
				'src'    => COURTNEYR_CHILD_URI . '/assets/css/' . STYLE_FILE,
				'deps'   => array(),
				'ver'    => COURTNEYR_CHILD_VERSION,
			)
		);
	}

	foreach ( array( 'xfn/blogroll', 'xfn/relationship-badge' ) as $block_name ) {
		if ( ! $registry->is_registered( $block_name ) ) {
			continue;
		}
		wp_enqueue_block_style(
			$block_name,
			array(
				'handle' => XFN_HANDLE,
				'src'    => COURTNEYR_CHILD_URI . '/assets/css/' . XFN_FILE,
				'deps'   => array(),
				'ver'    => COURTNEYR_CHILD_VERSION,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles', 20 );

/**
 * Paint one public plugin entry with its deterministic paper.
 *
 * @param string               $html     Rendered entry.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function entry_paint( string $html, array $block, $instance ): string {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( '' === $html || ! has_class_name( $class_name, ENTRY_CLASS ) || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	$post_id = block_post_id( $instance );
	if ( array() === facts( $post_id ) ) {
		return $html;
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pkiw-follow-entry',
		)
	) ) {
		return $html;
	}
	$tags->set_attribute( 'style', '--cr-follow-paper:var(--cr-' . paper( $post_id ) . ')' );
	return $tags->get_updated_html();
}
add_filter( 'render_block_' . ENTRY_BLOCK, __NAMESPACE__ . '\\entry_paint', 10, 3 );

/**
 * Set the follow archive's page size.
 *
 * @param mixed $sizes Existing kind sizes.
 * @return array<string, int>
 */
function page_size( $sizes ): array {
	$sizes         = is_array( $sizes ) ? $sizes : array();
	$sizes[ KIND ] = 8;
	return $sizes;
}
add_filter( 'courtneyr_child_kind_archive_page_sizes', __NAMESPACE__ . '\\page_size' );

/** Load follow paint only where follow objects can render. */
function enqueue(): void {
	if ( ! is_follow_archive() && ! is_follow_single() && ! is_stream_surface() ) {
		return;
	}
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/' . STYLE_FILE,
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue', 20 );

/**
 * Keep conditional follow and XFN paint out of shared used CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return string[]
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = STYLE_FILE;
	$exclusions[] = XFN_FILE;
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20 );
