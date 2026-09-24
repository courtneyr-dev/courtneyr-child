<?php
/**
 * Microformat properties on Core post blocks.
 *
 * Core's Post Title renders the permalink anchor without `u-url`, and Post
 * Date renders `<time datetime>` without `dt-published`. Blocks that carry the
 * marker classes below get the property added to the right element with the
 * HTML API, so the saved content stays plain Core blocks and the editor
 * canvas is unchanged.
 *
 *   core/post-title  className "cr-u-url"        -> <a class="u-url">
 *   core/post-date   className "cr-dt-published" -> <time class="dt-published">
 *
 * @package CourtneyrChild
 * @since 0.7.46
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Microformats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add a class to the first tag of a kind inside the rendered block.
 */
function add_class_to_tag( string $html, string $tag, string $class ): string {
	$p = new \WP_HTML_Tag_Processor( $html );
	if ( $p->next_tag( $tag ) ) {
		$p->add_class( $class );
		return $p->get_updated_html();
	}
	return $html;
}

function post_title_u_url( string $content, array $block ): string {
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! str_contains( $class, 'cr-u-url' ) ) {
		return $content;
	}
	if ( ! str_contains( $content, '<a' ) ) {
		// Untitled entry (Core renders nothing): keep a visible link to the
		// post so the card stays reachable and the entry keeps its u-url.
		// No p-name: notes are intentionally title-less in microformats.
		$post_id = (int) ( $block['context']['postId'] ?? get_the_ID() );
		if ( $post_id <= 0 || '' !== trim( (string) get_the_title( $post_id ) ) ) {
			return $content;
		}
		$level = (int) ( $block['attrs']['level'] ?? 2 );
		$level = $level >= 1 && $level <= 6 ? $level : 2;
		$label = sprintf(
			/* translators: %s: post date */
			__( 'Entry from %s', 'courtneyr-child' ),
			get_the_date( '', $post_id )
		);
		$classes = trim( str_replace( array( 'cr-u-url', 'p-name' ), '', $class ) ) . ' cr-untitled-title';
		return sprintf(
			'<h%1$d class="wp-block-post-title %2$s"><a class="u-url" href="%3$s">%4$s</a></h%1$d>',
			$level,
			esc_attr( $classes ),
			esc_url( (string) get_permalink( $post_id ) ),
			esc_html( $label )
		);
	}
	return add_class_to_tag( $content, 'a', 'u-url' );
}
// Priority 20: after IndieBlocks' theme-mf2 pass, so the untitled fallback is not re-labelled p-name.
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\post_title_u_url', 20, 2 );

function post_date_dt_published( string $content, array $block ): string {
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! str_contains( $class, 'cr-dt-published' ) || ! str_contains( $content, '<time' ) ) {
		return $content;
	}
	$content = add_class_to_tag( $content, 'time', 'dt-published' );
	// R-21 authorship: every entry carries its author as a hidden h-card, so
	// parsers never fall back to page-level guesses. Hidden from AT (the card
	// already names the site); microformats parsers read the DOM regardless.
	$post_id = (int) ( $block['context']['postId'] ?? get_the_ID() );
	if ( $post_id > 0 && ! str_contains( $content, 'p-author' ) ) {
		$content .= entry_author_html( $post_id );
	}
	return $content;
}
add_filter( 'render_block_core/post-date', __NAMESPACE__ . '\\post_date_dt_published', 10, 2 );

/**
 * The site's author identity for entries: name, canonical URL, photo.
 *
 * Shared with Post Kinds' stream cards through the `pkiw_entry_author`
 * filter so both renderers emit the same h-card.
 *
 * @param int $post_id Post ID.
 * @return array{name: string, url: string, photo: string}
 */
function entry_author( int $post_id ): array {
	$author_id = (int) get_post_field( 'post_author', $post_id );
	$name      = (string) get_the_author_meta( 'display_name', $author_id );
	$photo     = (string) get_site_icon_url( 192 );
	return array(
		'name'  => '' !== $name ? $name : (string) get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
		'photo' => $photo,
	);
}
add_filter(
	'pkiw_entry_author',
	static function ( $author, $post ) {
		return $post instanceof \WP_Post ? entry_author( $post->ID ) : $author;
	},
	10,
	2
);

/**
 * Hidden `p-author h-card` markup for an entry.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function entry_author_html( int $post_id ): string {
	$a    = entry_author( $post_id );
	$html = '<span class="p-author h-card cr-entry-author" hidden><a class="u-url p-name" href="' . esc_url( $a['url'] ) . '" tabindex="-1">' . esc_html( $a['name'] ) . '</a>';
	if ( '' !== $a['photo'] ) {
		$html .= '<img class="u-photo" src="' . esc_url( $a['photo'] ) . '" alt="" loading="lazy" />';
	}
	return $html . '</span>';
}

/**
 * rel=author points parsers at the representative h-card in the footer.
 */
add_action(
	'wp_head',
	static function () {
		echo '<link rel="author" href="' . esc_url( home_url( '/' ) ) . '" />' . "\n";
	},
	5
);

/**
 * R-02 (one microformats root per page): IndieBlocks' "Enable microformats"
 * option adds `h-entry` to <body> on every singular view, which wraps the
 * Stream page's cards and the single post's own entry root in a second,
 * property-less entry. The card/entry roots are owned by Post Kinds and the
 * theme; keep IndieBlocks' `h-feed` on archives and drop the body-level
 * `h-entry`/`h-recipe`/`h-review`/`h-event` on singular views.
 *
 * @param string $class Class IndieBlocks wants to add.
 * @return string
 */
function indieblocks_body_class( string $class ): string {
	if ( is_singular() ) {
		return '';
	}
	return $class;
}
add_filter( 'indieblocks_body_class', __NAMESPACE__ . '\\indieblocks_body_class' );

/**
 * R-02 on archives: IndieBlocks (and any other emitter) adds `h-entry` to the
 * Query Loop item through post_class. When the Post Kinds stream card inside
 * that item already carries the entry root, the item must not: parsers would
 * see two entries per card. Runs after every other post_class callback.
 *
 * @param string[] $classes Post classes.
 * @param string[] $class   Extra classes (unused).
 * @param int      $post_id Post ID.
 * @return string[]
 */
function single_entry_root_per_item( array $classes, array $class, int $post_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( ! empty( $GLOBALS['pkiw_stream_card_root_seen'][ $post_id ] ) ) {
		$classes = array_values( array_diff( $classes, array( 'h-entry', 'hentry' ) ) );
	}
	return $classes;
}
add_filter( 'post_class', __NAMESPACE__ . '\\single_entry_root_per_item', 100, 3 );
