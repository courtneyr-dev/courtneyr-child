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
 *   untitled review/reply/like/bookmark/photo/acquisition/weather/craft/exercise/sleep/mood singles -> visible H1, no p-name
 *   repost singles whose card title is the H1 -> suppress Core template title
 *   review posts                              -> h-review is the entry root
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
 * Kinds whose untitled singles need a visible, non-mf2-name H1.
 */
const UNTITLED_SINGLE_KINDS = array( 'review', 'reply', 'like', 'bookmark', 'photo', 'acquisition', 'weather', 'craft', 'exercise', 'sleep', 'mood' );

/**
 * Untitled kinds whose single H1 is the kind and date name from Nav\kind_date_name().
 *
 * The time follows unless courtneyr_child_untitled_name_shows_time says no:
 * weather and craft print "Craft, August 20, 2026 at 3:05 pm", exercise and
 * sleep print "Sleep, August 19, 2026".
 */
const KIND_DATE_NAME_KINDS = array( 'reply', 'weather', 'craft', 'exercise', 'sleep' );

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

/**
 * Link text for an untitled entry on a card or list.
 *
 * A sleep post reads its facts name, "Sleep, August 20, 2026", from Post Kinds
 * (public tier, no clock time). Every other kind reads "Entry from <date>".
 *
 * @param int $post_id Post ID.
 * @return string
 */
function untitled_entry_label( int $post_id ): string {
	if ( has_term( 'sleep', 'kind', $post_id ) && function_exists( '\\PKIW\\kind_facts' ) ) {
		$facts = \PKIW\kind_facts( $post_id );
		$name  = is_array( $facts ) && is_string( $facts['name'] ?? null ) ? trim( $facts['name'] ) : '';
		if ( '' !== $name ) {
			return $name;
		}
	}
	return sprintf(
		/* translators: %s: post date */
		__( 'Entry from %s', 'courtneyr-child' ),
		get_the_date( '', $post_id )
	);
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
		$level   = (int) ( $block['attrs']['level'] ?? 2 );
		$level   = $level >= 1 && $level <= 6 ? $level : 2;
		$label   = untitled_entry_label( $post_id );
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

/**
 * Shared state for the temporary untitled-single title filter.
 *
 * @param int|null  $post_id        Post ID to store, or null to leave as-is.
 * @param bool|null $label_injected Whether the title callback injected a label.
 * @return array{post_id: int, label_injected: bool}
 */
function untitled_single_state( ?int $post_id = null, ?bool $label_injected = null ): array {
	static $state = array(
		'post_id'        => 0,
		'label_injected' => false,
	);

	if ( null !== $post_id ) {
		$state['post_id'] = $post_id;
	}
	if ( null !== $label_injected ) {
		$state['label_injected'] = $label_injected;
	}
	return $state;
}

/**
 * Return the current post ID when a post-title block needs the untitled H1.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return int Post ID, or 0 when the block is not the target.
 */
function untitled_single_post_id( array $block ): int {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || 1 !== (int) ( $block['attrs']['level'] ?? 0 ) ) {
		return 0;
	}
	if ( ! is_singular( 'post' ) ) {
		return 0;
	}
	$post_id = (int) get_the_ID();
	if ( 0 >= $post_id || (int) get_queried_object_id() !== $post_id ) {
		return 0;
	}
	if ( '' !== trim( (string) get_post_field( 'post_title', $post_id, 'raw' ) ) ) {
		return 0;
	}
	if ( ! has_term( UNTITLED_SINGLE_KINDS, 'kind', $post_id ) ) {
		return 0;
	}
	return $post_id;
}

/**
 * Label for an untitled single H1.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function untitled_single_label( int $post_id ): string {
	$post = get_post( $post_id );
	// A locked post names itself by kind and date only: nothing read from its facts or content.
	if ( $post instanceof \WP_Post && post_password_required( $post ) ) {
		if ( function_exists( '\Courtneyr\Child\Nav\kind_date_name' ) ) {
			return \Courtneyr\Child\Nav\kind_date_name( $post );
		}
		/* translators: %s: post date */
		return sprintf( __( 'Entry from %s', 'courtneyr-child' ), get_the_date( '', $post_id ) );
	}
	// Ruling X8: an untitled reply's H1 is its kind, date and time; the cited name belongs to the citation.
	// Weather, craft, exercise and sleep use the same name; exercise and sleep drop the time.
	if ( $post instanceof \WP_Post && function_exists( '\Courtneyr\Child\Nav\kind_date_name' ) && has_term( KIND_DATE_NAME_KINDS, 'kind', $post ) ) {
		return \Courtneyr\Child\Nav\kind_date_name( $post );
	}
	// An untitled like with no real cited title names its host in the clipping, so the H1 is its kind and date.
	if ( $post instanceof \WP_Post && function_exists( '\PKIW\untitled_fallback_name' ) && function_exists( '\Courtneyr\Child\Like\like_facts' ) && has_term( 'like', 'kind', $post ) ) {
		$facts = \Courtneyr\Child\Like\like_facts( $post_id );
		if ( '' === trim( (string) ( $facts['cited_title'] ?? '' ) ) ) {
			return (string) \PKIW\untitled_fallback_name( $post )['name'];
		}
	}
	if ( $post instanceof \WP_Post && function_exists( '\PKIW\untitled_name' ) ) {
		$label = (string) \PKIW\untitled_name( $post );
		if ( '' !== trim( $label ) ) {
			return $label;
		}
	}
	return sprintf(
		/* translators: %s: post date */
		__( 'Entry from %s', 'courtneyr-child' ),
		get_the_date( '', $post_id )
	);
}

/**
 * Whether the cited title already stands in for the single H1.
 *
 * True when the title is empty, or equals the cited title, or equals the host
 * when there is no cited title, all compared after wp_strip_all_tags, trim and
 * mb_strtolower.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function cited_title_is_h1( int $post_id ): bool {
	$post = get_post( $post_id );
	if ( ! $post instanceof \WP_Post ) {
		return false;
	}

	$normalize = static function ( $text ): string {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	};

	$title = $normalize( get_post_field( 'post_title', $post_id, 'raw' ) );
	if ( '' === $title ) {
		return true;
	}
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return false;
	}
	$facts = \PKIW\kind_facts( $post_id );
	if ( ! is_array( $facts ) ) {
		return false;
	}
	$h1_source = $normalize( $facts['cited_title'] ?? '' );
	if ( '' === $h1_source ) {
		$h1_source = $normalize( $facts['host'] ?? '' );
	}
	if ( '' === $h1_source ) {
		return false;
	}

	return $title === $h1_source;
}

/**
 * Suppress Core's title when the repost card already carries the single H1.
 *
 * The repost card (retagged to h1 by the repost lane) carries the single H1,
 * so Core's template title prints nothing for that post on its own single.
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Parsed block.
 * @return string
 */
function suppress_cited_title_post_title( string $content, array $block ): string {
	if ( 1 !== (int) ( $block['attrs']['level'] ?? 0 ) ) {
		return $content;
	}
	if ( ! is_singular( 'post' ) ) {
		return $content;
	}
	$post_id = (int) get_the_ID();
	if ( 0 >= $post_id || (int) get_queried_object_id() !== $post_id ) {
		return $content;
	}
	if ( isset( $block['context']['postId'] ) && (int) $block['context']['postId'] !== $post_id ) {
		return $content;
	}
	if ( ! has_term( 'repost', 'kind', $post_id ) || post_password_required( $post_id ) ) {
		return $content;
	}
	if ( true !== cited_title_is_h1( $post_id ) ) {
		return $content;
	}
	return '';
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\suppress_cited_title_post_title', 30, 2 );

/**
 * Temporarily provide a visible title while Core renders the single H1.
 *
 * @param array<string, mixed> $parsed_block Parsed block.
 * @return array<string, mixed>
 */
function prepare_untitled_single_title( array $parsed_block ): array {
	$post_id = untitled_single_post_id( $parsed_block );
	if ( $post_id > 0 ) {
		untitled_single_state( $post_id, false );
		untitled_title_format( '' );
		add_filter( 'protected_title_format', __NAMESPACE__ . '\\capture_untitled_title_format', PHP_INT_MAX, 2 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own filter.
		add_filter( 'private_title_format', __NAMESPACE__ . '\\capture_untitled_title_format', PHP_INT_MAX, 2 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own filter.
		add_filter( 'the_title', __NAMESPACE__ . '\\untitled_single_title', 10, 2 );
	}
	return $parsed_block;
}
add_filter( 'render_block_data', __NAMESPACE__ . '\\prepare_untitled_single_title', 10, 3 );

/**
 * Inject the temporary H1 label for the targeted untitled single only.
 *
 * @param string $title   Incoming title.
 * @param int    $post_id Post ID.
 * @return string
 */
function untitled_single_title( string $title, int $post_id ): string {
	$state = untitled_single_state();
	if ( (int) $post_id !== $state['post_id'] ) {
		return $title;
	}
	if ( '' === trim( $title ) ) {
		untitled_single_state( $state['post_id'], true );
		// Core prints the filtered post-title block content raw.
		return esc_html( untitled_single_label( $post_id ) );
	}
	// Core has already wrapped the empty title in its "Protected: %s" or
	// "Private: %s" format. Reuse the exact format Core applied, as Core
	// prints it, and escape only the label.
	$format = untitled_title_format();
	if ( '' !== $format && trim( $title ) === trim( sprintf( $format, '' ) ) ) {
		untitled_single_state( $state['post_id'], true );
		return sprintf( $format, esc_html( untitled_single_label( $post_id ) ) );
	}
	return $title;
}

/**
 * The protected or private title format Core applied to the targeted post.
 *
 * @param string|null $format Format to store, or null to read.
 * @return string
 */
function untitled_title_format( ?string $format = null ): string {
	static $stored = '';
	if ( null !== $format ) {
		$stored = $format;
	}
	return $stored;
}

/**
 * Record Core's protected or private title format for the targeted post.
 *
 * Runs last on protected_title_format and private_title_format, so the stored
 * value is the one Core passes to sprintf() in get_the_title().
 *
 * @param string   $format Title format.
 * @param \WP_Post $post   Post.
 * @return string Unchanged format.
 */
function capture_untitled_title_format( $format, $post = null ) {
	if ( $post instanceof \WP_Post && untitled_single_state()['post_id'] === (int) $post->ID ) {
		untitled_title_format( (string) $format );
	}
	return $format;
}

/**
 * Clean up the temporary title filter and remove p-name from the fallback H1.
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Parsed block.
 * @return string
 */
function render_untitled_single_title( string $content, array $block ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$state = untitled_single_state();
	remove_filter( 'the_title', __NAMESPACE__ . '\\untitled_single_title', 10 );
	remove_filter( 'protected_title_format', __NAMESPACE__ . '\\capture_untitled_title_format', PHP_INT_MAX );
	remove_filter( 'private_title_format', __NAMESPACE__ . '\\capture_untitled_title_format', PHP_INT_MAX );
	untitled_single_state( 0, false );
	untitled_title_format( '' );

	if ( empty( $state['label_injected'] ) || ! str_contains( $content, 'wp-block-post-title' ) ) {
		return $content;
	}
	$p = new \WP_HTML_Tag_Processor( $content );
	if ( $p->next_tag( array( 'class_name' => 'wp-block-post-title' ) ) ) {
		$p->remove_class( 'p-name' );
		$p->add_class( 'cr-untitled-title' );
		return $p->get_updated_html();
	}
	return $content;
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\render_untitled_single_title', 20, 2 );

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
	if ( ! empty( $GLOBALS['pkiw_stream_card_root_seen'][ $post_id ] ) || has_term( 'review', 'kind', $post_id ) ) {
		$classes = array_values( array_diff( $classes, array( 'h-entry', 'hentry' ) ) );
	}
	return $classes;
}
add_filter( 'post_class', __NAMESPACE__ . '\\single_entry_root_per_item', 100, 3 );

/**
 * Let the like card title remain a heading when the single H1 is synthetic.
 *
 * @param bool     $is   Whether the card title is a heading.
 * @param \WP_Post $post Post object.
 * @return bool
 */
function like_card_title_is_heading( bool $is, \WP_Post $post ): bool {
	if (
		has_term( 'like', 'kind', $post->ID )
		&& is_singular( 'post' )
		&& (int) get_queried_object_id() === (int) $post->ID
		&& '' === trim( (string) get_post_field( 'post_title', $post->ID, 'raw' ) )
		&& ! post_password_required( $post->ID )
	) {
		return true;
	}
	return $is;
}
add_filter( 'pkiw_like_card_title_is_heading', __NAMESPACE__ . '\\like_card_title_is_heading', 10, 2 );
