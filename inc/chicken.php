<?php
/**
 * Chicken archive: an egg-carton calendar (PKIW issue 250).
 *
 * The plugin owns the month sections, the entry markup and the facts. This
 * file enqueues the carton paint, picks each egg's fill from the post ID, and
 * registers the chicken board with the editor parity registry.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Chicken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'chicken';
const STYLE_HANDLE = 'courtneyr-chicken';

/**
 * Palette tokens an egg may be filled with.
 *
 * Light gray, printer ivory and the rule color are left out: the carton and the
 * empty cups are flat light gray, so an egg in any of them would look like an
 * empty cup (decision 250, correction 71).
 */
const EGG_FILLS = array(
	'russian-violet',
	'periwinkle',
	'glaucous',
	'sky-blue',
	'blue-green',
	'cerulean',
	'prussian-blue',
	'selective-yellow',
	'ut-orange',
	'light-orange',
);

/** Whether this request is the chicken kind archive. */
function is_chicken_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is a single chicken post. */
function is_chicken_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Enqueue the chicken paint where chicken markup can appear.
 *
 * That is the archive, a chicken single, every Stream surface (/stream, the
 * front page and the stream-card preview) and the editor. /type/<format>/
 * archives keep the generic card, so they don't load it.
 */
function enqueue_styles(): void {
	if (
		! is_admin()
		&& ! is_chicken_archive()
		&& ! is_chicken_single()
		&& ! \Courtneyr\Child\HomeSections\is_stream_surface()
	) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-chicken.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Body classes for the chicken archive and single.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_chicken_archive() && ! in_array( 'cr-chicken-archive', $classes, true ) ) {
		$classes[] = 'cr-chicken-archive';
	}
	if ( is_chicken_single() && ! in_array( 'cr-chicken-single', $classes, true ) ) {
		$classes[] = 'cr-chicken-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * The palette token an egg is filled with.
 *
 * The same post gets the same token on every load and on every surface; the
 * color carries no meaning.
 *
 * @param int $post_id Post ID.
 * @return string A token name from EGG_FILLS, or '' when the stamps module isn't loaded.
 */
function egg_fill( int $post_id ): string {
	if ( ! function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		return '';
	}
	return \Courtneyr\Child\Stamps\seeded_paper( $post_id, EGG_FILLS );
}

/**
 * Give each egg its fill class.
 *
 * Eggs are found by the title link's id, `pkiw-chicken-t-{ID}`, and not by
 * class: the entry's hidden author link carries u-url too. The front end
 * renders one item per call, but a block-renderer preview hands this filter a
 * whole section, so every link gets its own post's fill. A preview
 * placeholder has no link and gets nothing.
 *
 * @param string               $html  Rendered chicken-entry block.
 * @param array<string, mixed> $block Parsed block (unused).
 * @return string
 */
function paint_eggs( string $html, array $block = array() ): string {
	unset( $block );

	if ( ! str_contains( $html, 'pkiw-chicken-t-' ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'a' ) ) {
		$id = (string) $tags->get_attribute( 'id' );
		if ( 1 !== preg_match( '/^pkiw-chicken-t-(\d+)$/', $id, $found ) ) {
			continue;
		}
		$fill = egg_fill( (int) $found[1] );
		if ( '' !== $fill ) {
			$tags->add_class( 'cr-egg--' . $fill );
		}
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_post-kinds-indieweb/chicken-entry', __NAMESPACE__ . '\\paint_eggs', 10, 2 );

/**
 * Register the chicken board with editor parity, so the editor canvas paints it.
 *
 * The registry already lists every kind term; this adds the board class to the
 * chicken entry (or creates the entry when the term isn't there yet).
 *
 * @param array<string, array{classes?: array<int, string>}> $kinds Registry so far.
 * @return array<string, array{classes?: array<int, string>}>
 */
function register_editor_parity( array $kinds ): array {
	$classes = isset( $kinds[ KIND ]['classes'] ) && is_array( $kinds[ KIND ]['classes'] )
		? $kinds[ KIND ]['classes']
		: array( 'single-post', 'kind-' . KIND );
	if ( ! in_array( 'cr-chicken-board', $classes, true ) ) {
		$classes[] = 'cr-chicken-board';
	}
	$kinds[ KIND ]['classes'] = $classes;

	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );
