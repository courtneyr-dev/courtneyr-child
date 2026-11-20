<?php
/**
 * Theme styles for the Post Kinds card blocks.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\ArtifactStyle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The theme's artifact registrations, keyed by style slug.
 *
 * @return array<string, array{block: string, label: string, kind: string}>
 */
function &styles(): array {
	static $styles = array();
	return $styles;
}

/**
 * Register an artifact style for a kind's card.
 *
 * @param string $block The card's block name.
 * @param string $slug  The style slug.
 * @param string $label The editor label.
 * @param string $kind  The post kind.
 */
function register( string $block, string $slug, string $label, string $kind ): void {
	$styles =& styles();
	if ( array() === $styles ) {
		add_action( 'init', __NAMESPACE__ . '\\register_styles', 20 );
	}
	$styles[ $slug ] = array(
		'block' => $block,
		'label' => $label,
		'kind'  => $kind,
	);
}

/** Register styles after PKIW registers its card blocks. */
function register_styles(): void {
	$registry = \WP_Block_Type_Registry::get_instance();
	foreach ( styles() as $slug => $style ) {
		$block = $style['block'];
		$label = $style['label'];
		if ( $registry->is_registered( $block ) ) {
			// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- The N7 scanner checks this registration expression.
			register_block_style( $block, array( 'name' => $slug, 'label' => $label ) );
		}
	}
}

/**
 * Return the post's kind slugs in term order.
 *
 * @param int $post_id Post ID.
 * @return string[]
 */
function kind_slugs( int $post_id ): array {
	static $cache = array();
	if ( ! isset( $cache[ $post_id ] ) ) {
		$terms             = get_the_terms( $post_id, 'kind' );
		$cache[ $post_id ] = is_array( $terms ) ? array_column( $terms, 'slug' ) : array();
	}
	return $cache[ $post_id ];
}

/**
 * Whether the post carries the style's kind.
 *
 * @param \WP_Post|int $post Post or post ID.
 * @param string       $slug Artifact style slug.
 */
function applies( \WP_Post|int $post, string $slug ): bool {
	$style = styles()[ $slug ] ?? null;
	return null !== $style && in_array( $style['kind'], kind_slugs( $post instanceof \WP_Post ? $post->ID : $post ), true );
}

/**
 * Find the first registered artifact style in term order.
 *
 * @param \WP_Post|int $post Post or post ID.
 */
function resolve( \WP_Post|int $post ): string {
	$kinds_by_style = array_map( static fn( array $style ): string => $style['kind'], styles() );
	foreach ( kind_slugs( $post instanceof \WP_Post ? $post->ID : $post ) as $kind ) {
		$slug = array_search( $kind, $kinds_by_style, true );
		if ( false !== $slug ) {
			return $slug;
		}
	}
	return '';
}
