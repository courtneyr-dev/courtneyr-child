<?php
/**
 * Repost archive clippings and single presentation (issue 255).
 *
 * The archive prints each repost as a photocopied clipping taped onto its
 * month's zine spread, the single turns the plugin's repost card into one
 * clipping, and inc/stream-repost.php covers the Stream. Presentation only:
 * the plugin owns the data, so every value reaches this file through
 * `\PKIW\kind_facts()` and every call into the plugin sits behind
 * function_exists() or class_exists().
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Repost;

use function Courtneyr\Child\HomeSections\is_stream_surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The stream-card block style name used by the repost archive.
 */
const ITEM_STYLE = 'cr-repost-clipping';

/**
 * The class WordPress gives the repost archive's block style.
 */
const ITEM_CLASS = 'is-style-cr-repost-clipping';

/**
 * The Post Template class that marks the archive's month spreads.
 */
const SPREADS_CLASS = 'cr-repost-zine__spreads';

/**
 * Stylesheet handle.
 */
const STYLE_HANDLE = 'courtneyr-repost';

/**
 * How many items of the page's first section load their image eagerly.
 */
const EAGER_ITEMS = 4;

/**
 * Number of tape and card tilt variants (the --cr-rotate-* tokens).
 */
const TILTS = 4;

/**
 * Register the repost archive's stream-card block style.
 *
 * @return void
 */
function register_item_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => ITEM_STYLE,
				'label' => __( 'Repost clipping', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_item_style' );

/**
 * Whether the current request is the repost kind archive.
 *
 * @return bool
 */
function is_repost_archive(): bool {
	return \is_tax( 'kind', 'repost' );
}

/**
 * Whether the current request is a single repost post.
 *
 * @return bool
 */
function is_repost_single(): bool {
	return \is_singular( 'post' ) && has_term( 'repost', 'kind', get_queried_object_id() );
}

/**
 * Resolve a block's post ID.
 *
 * @param mixed $instance Block instance.
 * @return int
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Clamp a heading level to the plugin's 2-4 range.
 *
 * @param mixed $level   Block attribute value.
 * @param int   $fallback Level when the attribute is missing.
 * @return int
 */
function heading_level( $level, int $fallback = 3 ): int {
	$level = is_numeric( $level ) ? (int) $level : $fallback;
	return max( 2, min( 4, $level ) );
}

/**
 * Repost facts from the plugin, or [] when it can't show them.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Read one trimmed string from a facts array.
 *
 * @param array<string, mixed> $facts Facts.
 * @param string               $key   Key.
 * @return string
 */
function fact( array $facts, string $key ): string {
	return isset( $facts[ $key ] ) && is_scalar( $facts[ $key ] ) ? trim( (string) $facts[ $key ] ) : '';
}

/**
 * The name an archive item or Stream card shows.
 *
 * The plugin's `name` fact holds the order: cited title, post title, source
 * host, then the dated fallback. The post title stands in only when a plugin
 * without the key is installed.
 *
 * @param \WP_Post             $post  Post.
 * @param array<string, mixed> $facts Repost facts.
 * @return string
 */
function display_name( \WP_Post $post, array $facts ): string {
	$name = fact( $facts, 'name' );
	if ( '' !== $name ) {
		return $name;
	}
	foreach ( array( fact( $facts, 'cited_title' ), trim( (string) get_the_title( $post ) ), fact( $facts, 'host' ) ) as $candidate ) {
		if ( '' !== $candidate ) {
			return $candidate;
		}
	}
	return __( 'Repost', 'courtneyr-child' );
}

/**
 * The one credit line: the cited author, else the source host.
 *
 * @param array<string, mixed> $facts Repost facts.
 * @return string
 */
function credit_text( array $facts ): string {
	$author = fact( $facts, 'author' );
	return '' !== $author ? $author : fact( $facts, 'host' );
}

/**
 * A stable tilt index from a post ID.
 *
 * @param int $post_id Post ID.
 * @param int $shift   Bits to discard, so two picks from one post differ.
 * @return int 0 to TILTS - 1.
 */
function tilt_index( int $post_id, int $shift = 3 ): int {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		return \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( (string) $post_id ), $shift, TILTS );
	}
	return $post_id % TILTS;
}

/**
 * Normalize text for name comparison.
 *
 * @param string $text Text or HTML.
 * @return string
 */
function comparable_text( string $text ): string {
	$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}

/**
 * The post IDs on the page being rendered, in display order.
 *
 * The engine's page ids when a grouped page is known, else the main query's
 * posts.
 *
 * @return int[]
 */
function page_ids(): array {
	if ( class_exists( '\\PKIW\\Grouped_Archive' ) && method_exists( '\\PKIW\\Grouped_Archive', 'current_page_ids' ) ) {
		$ids = \PKIW\Grouped_Archive::current_page_ids();
		if ( is_array( $ids ) && array() !== $ids ) {
			return array_map( 'intval', array_values( $ids ) );
		}
	}
	$query = $GLOBALS['wp_query'] ?? null;
	if ( ! is_object( $query ) || ! isset( $query->posts ) || ! is_array( $query->posts ) ) {
		return array();
	}
	return array_map(
		static function ( $post ): int {
			return is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
		},
		array_values( $query->posts )
	);
}

/**
 * The group key of a repost under the month source, or '' when unknown.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function group_key( int $post_id ): string {
	if ( class_exists( '\\PKIW\\Grouped_Archive' ) && method_exists( '\\PKIW\\Grouped_Archive', 'group_of_post' ) ) {
		$group = \PKIW\Grouped_Archive::group_of_post( 'repost', $post_id );
		if ( is_object( $group ) && method_exists( $group, 'key' ) ) {
			return (string) $group->key();
		}
	}
	return '';
}

/**
 * Whether an item sits in the first EAGER_ITEMS places of the page's first section.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function is_first_row( int $post_id ): bool {
	$ids      = page_ids();
	$position = array_search( $post_id, $ids, true );
	if ( false === $position || $position >= EAGER_ITEMS ) {
		return false;
	}
	return group_key( $post_id ) === group_key( (int) $ids[0] );
}

/**
 * Which displayed names repeat on this page.
 *
 * @return array{names: array<int, string>, counts: array<string, int>}
 */
function page_titles(): array {
	static $cache = array();

	$ids = page_ids();
	$key = implode( ',', $ids );
	if ( ! isset( $cache[ $key ] ) ) {
		$names = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || ! has_term( 'repost', 'kind', $post ) || post_password_required( $post ) ) {
				continue;
			}
			$facts = facts( $id );
			if ( array() === $facts ) {
				continue;
			}
			$names[ $id ] = comparable_text( display_name( $post, $facts ) );
		}
		$counts = array();
		foreach ( $names as $name ) {
			if ( '' !== $name ) {
				$counts[ $name ] = ( $counts[ $name ] ?? 0 ) + 1;
			}
		}
		$cache[ $key ] = array(
			'names'  => $names,
			'counts' => $counts,
		);
	}
	return $cache[ $key ];
}

/**
 * The id of the section heading the engine prints for the item's month.
 *
 * Mirrors Grouped_Archive::heading_id(): `pkiw-group-<slug>` with the slug cut
 * to id-safe characters.
 *
 * @return string '' when no group is known.
 */
function section_heading_id(): string {
	if ( ! class_exists( '\\PKIW\\Grouped_Archive' ) || ! method_exists( '\\PKIW\\Grouped_Archive', 'current_group' ) ) {
		return '';
	}
	$group = \PKIW\Grouped_Archive::current_group();
	if ( ! is_object( $group ) || ! method_exists( $group, 'slug' ) ) {
		return '';
	}
	$slug = strtolower( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', str_replace( '%', '', (string) $group->slug() ) ) );
	return 'pkiw-group-' . ( '' !== $slug ? $slug : 'group' );
}

/**
 * The aria-labelledby value that tells two items with one name apart.
 *
 * @param int                  $post_id Post ID.
 * @param array<string, mixed> $facts   Repost facts.
 * @return string '' when the item's name is unique on the page.
 */
function repeated_name_labels( int $post_id, array $facts ): string {
	$titles = page_titles();
	$name   = $titles['names'][ $post_id ] ?? '';
	if ( '' === $name || ( $titles['counts'][ $name ] ?? 0 ) < 2 ) {
		return '';
	}
	$second = '' !== credit_text( $facts ) ? 'cr-repost-credit-' . $post_id : section_heading_id();
	return trim( 'cr-repost-title-' . $post_id . ' ' . $second );
}

/**
 * The clipping's picture frame, or '' when the item has no image.
 *
 * @param array<string, mixed> $facts Repost facts.
 * @param bool                 $eager Whether the image loads before the fold.
 * @return string
 */
function frame_markup( array $facts, bool $eager ): string {
	$picture = isset( $facts['picture'] ) && is_array( $facts['picture'] ) ? $facts['picture'] : array();
	$url     = isset( $picture['url'] ) && is_scalar( $picture['url'] ) ? trim( (string) $picture['url'] ) : '';
	if ( '' === $url ) {
		return '';
	}

	$image         = '';
	$attachment_id = isset( $picture['attachment_id'] ) ? (int) $picture['attachment_id'] : 0;
	if ( $attachment_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
		$attrs = array(
			'class'    => 'cr-repost-clip__img' . ( $eager ? ' skip-lazy' : '' ),
			'alt'      => '',
			'loading'  => $eager ? 'eager' : 'lazy',
			'decoding' => 'async',
		);
		if ( $eager ) {
			$attrs['fetchpriority']  = 'high';
			$attrs['data-skip-lazy'] = '';
		}
		$image = (string) wp_get_attachment_image( $attachment_id, 'medium_large', false, $attrs );
	}
	if ( '' === $image ) {
		$image = '<img class="cr-repost-clip__img' . ( $eager ? ' skip-lazy' : '' ) . '" src="' . esc_url( $url ) . '" alt="" loading="' . ( $eager ? 'eager' : 'lazy' ) . '" decoding="async"' . ( $eager ? ' fetchpriority="high" data-skip-lazy' : '' ) . '>';
	}

	return '<div class="cr-repost-clip__frame" data-cr-cover-fallback>' . $image . '</div>';
}

/**
 * The paper element's classes, shared by every archive clipping.
 */
const PAPER_CLASSES = 'cr-repost-clip__paper cr-paper-sheet cr-paper-sheet--torn cr-paper-sheet--flat';

/**
 * Dress a repost stream card as an archive clipping.
 *
 * Replaces the plugin's card with one title link inside an h3, one credit
 * line and the stored picture. The li the engine prints keeps PKIW's h-entry,
 * so the clipping carries no h-entry of its own and no p-name: the cited title
 * isn't the entry's name.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block.
 * @param \WP_Block $instance Block instance with the Query Loop's post ID.
 * @return string
 */
function clipping( string $html, array $block, $instance ): string {
	if ( '' === $html || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), ITEM_CLASS ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'repost', 'kind', $post ) ) {
		return $html;
	}

	$tape = 'cr-repost-clip--tape-' . tilt_index( $post_id );

	// A protected post keeps the plugin's card whole: its one title link
	// already reads "Protected: <stored title>" and it reads no facts.
	if ( post_password_required( $post ) ) {
		return '<div class="cr-repost-clip cr-repost-clip--protected ' . esc_attr( $tape ) . '"><div class="' . PAPER_CLASSES . '">' . $html . '</div></div>';
	}

	$facts = facts( $post_id );
	if ( array() === $facts ) {
		return $html;
	}

	$level  = heading_level( $block['attrs']['headingLevel'] ?? 3 );
	$labels = repeated_name_labels( $post_id, $facts );
	$credit = credit_text( $facts );

	$link  = '<a id="cr-repost-title-' . esc_attr( (string) $post_id ) . '" class="u-url" href="' . esc_url( (string) get_permalink( $post ) ) . '"';
	$link .= '' !== $labels ? ' aria-labelledby="' . esc_attr( $labels ) . '"' : '';
	$link .= '>' . esc_html( display_name( $post, $facts ) ) . '</a>';

	$out  = '<div class="cr-repost-clip ' . esc_attr( $tape ) . '"><div class="' . PAPER_CLASSES . '">';
	$out .= frame_markup( $facts, is_first_row( $post_id ) );
	$out .= '<h' . $level . ' class="cr-repost-clip__title">' . $link . '</h' . $level . '>';
	if ( '' !== $credit ) {
		$out .= '<p class="cr-repost-clip__credit" id="cr-repost-credit-' . esc_attr( (string) $post_id ) . '">' . esc_html( $credit ) . '</p>';
	}
	$out .= '</div>';
	$out .= function_exists( '\\PKIW\\stream_card_kind_cite' ) ? (string) \PKIW\stream_card_kind_cite( $post ) : '';
	return $out . '</div>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\clipping', 10, 3 );

/**
 * Name each month spread by its heading.
 *
 * The engine prints `section.pkiw-group` with the heading's id and leaves the
 * section unnamed. Decision 255 asks for section[aria-labelledby], so this
 * reads the id of the first heading in each section and sets it on the section.
 *
 * @param string $html  Rendered Post Template.
 * @param array  $block Parsed block.
 * @return string
 */
function label_sections( string $html, array $block ): string {
	if ( '' === $html || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), SPREADS_CLASS ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag(
		array(
			'tag_name'   => 'section',
			'class_name' => 'pkiw-group',
		)
	) ) {
		if ( ! $tags->set_bookmark( 'cr-repost-section' ) ) {
			break;
		}
		$id = '';
		while ( $tags->next_tag() ) {
			if ( 1 === preg_match( '/^H[1-6]$/', (string) $tags->get_tag() ) ) {
				$heading_id = $tags->get_attribute( 'id' );
				$id         = is_string( $heading_id ) ? $heading_id : '';
				break;
			}
		}
		if ( ! $tags->seek( 'cr-repost-section' ) ) {
			break;
		}
		if ( '' !== $id ) {
			$tags->set_attribute( 'aria-labelledby', $id );
		}
		$tags->release_bookmark( 'cr-repost-section' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\label_sections', 10, 2 );

/**
 * Cut every element of one tag that carries a class from a fragment.
 *
 * @param string $html       Fragment.
 * @param string $tag        Tag name.
 * @param string $class_name One class the element carries.
 * @return string
 */
function cut_elements( string $html, string $tag, string $class_name ): string {
	$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
	while ( preg_match( $open, $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$start = (int) $m[0][1];
		$pos   = $start + strlen( $m[0][0] );
		$depth = 1;
		while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
			$pos    = (int) $t[0][1] + strlen( $t[0][0] );
			$depth += '/' === $t[1][0] ? -1 : 1;
		}
		if ( $depth > 0 ) {
			return $html;
		}
		$html = substr( $html, 0, $start ) . substr( $html, $pos );
	}
	return $html;
}

/**
 * Remove the first element of one tag that carries a class and return it.
 *
 * @param string $html       Fragment; the element is gone from it afterwards.
 * @param string $tag        Tag name.
 * @param string $class_name One class the element carries.
 * @return string The element's markup, or '' when absent or unclosed.
 */
function take_element( string &$html, string $tag, string $class_name ): string {
	$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
	if ( ! preg_match( $open, $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}
	$start = (int) $m[0][1];
	$pos   = $start + strlen( $m[0][0] );
	$depth = 1;
	while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
		$pos    = (int) $t[0][1] + strlen( $t[0][0] );
		$depth += '/' === $t[1][0] ? -1 : 1;
	}
	if ( $depth > 0 ) {
		return '';
	}
	$element = substr( $html, $start, $pos - $start );
	$html    = substr( $html, 0, $start ) . substr( $html, $pos );
	return $element;
}

/**
 * Add classes to the first pk-card article.
 *
 * @param string $html    Fragment.
 * @param string ...$add Classes to add.
 * @return string
 */
function add_article_classes( string $html, string ...$add ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pk-card',
		)
	) ) {
		foreach ( $add as $class_name ) {
			$tags->add_class( $class_name );
		}
		return $tags->get_updated_html();
	}
	return $html;
}

/**
 * Put markup at the start of the card's caption.
 *
 * @param string $html   Fragment.
 * @param string $insert Markup to insert.
 * @return string
 */
function insert_into_caption( string $html, string $insert ): string {
	return (string) preg_replace_callback(
		'/(<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-caption(?![\w-])[^"]*"[^>]*>\s*)/',
		static fn( array $m ): string => $m[1] . $insert,
		$html,
		1
	);
}

/**
 * Whether the post is a repost the single clipping should dress.
 *
 * @param mixed $instance Block instance.
 * @return int The post ID, or 0.
 */
function single_post_id( $instance ): int {
	if ( ! is_repost_single() ) {
		return 0;
	}
	$post_id = block_post_id( $instance );
	if ( ! $post_id || get_queried_object_id() !== $post_id || ! has_term( 'repost', 'kind', $post_id ) ) {
		return 0;
	}
	return $post_id;
}

/**
 * Turn the plugin's repost card into the single's clipping.
 *
 * Markup changes only: the card's label and badge go, the date stays in the
 * DOM but hidden, the host line and "by" credit appear when stored, the image
 * moves ahead of the text, and the cited title becomes the page's h1 when the
 * post has no title of its own.
 *
 * @param string    $html     Rendered repost card.
 * @param array     $block    Parsed block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function single_card( string $html, array $block, $instance ): string {
	if ( '' === $html || false === strpos( $html, 'k-repost' ) ) {
		return $html;
	}
	$post_id = single_post_id( $instance );
	if ( 0 === $post_id || post_password_required( $post_id ) ) {
		return $html;
	}
	$facts = facts( $post_id );

	$html = cut_elements( $html, 'div', 'pk-badge' );
	$html = cut_elements( $html, 'span', 'pk-kindlabel' );

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'div',
			'class_name' => 'pk-meta',
		)
	) ) {
		$tags->set_attribute( 'hidden', true );
		$html = $tags->get_updated_html();
	}

	$host = fact( $facts, 'host' );
	if ( '' !== fact( $facts, 'url' ) && '' !== $host ) {
		/* translators: %s: the cited site's host name. */
		$from = '<p class="pk-sub cr-repost-clip__from">' . esc_html( sprintf( __( 'From %s', 'courtneyr-child' ), $host ) ) . '</p>';
		$html = insert_into_caption( $html, $from );
	}

	$html = (string) preg_replace_callback(
		'/(<p\b[^>]*\bclass="[^"]*(?<![\w-])pk-sub(?![\w-])[^"]*"[^>]*>\s*)(<span class="p-author h-card">.*?<\/span><\/span>)/s',
		static function ( array $m ): string {
			/* translators: %s: the cited author, as markup. */
			return $m[1] . sprintf( esc_html__( 'by %s', 'courtneyr-child' ), $m[2] );
		},
		$html,
		1
	);

	// Image first, then text: the DOM order is the reading order.
	$embed = take_element( $html, 'div', 'pk-embed--photo' );
	if ( '' !== $embed ) {
		$embed = (string) preg_replace( '/^<div\b/', '<div data-cr-cover-fallback', $embed, 1 );
		$html  = (string) preg_replace_callback(
			'/<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-caption(?![\w-])[^"]*"[^>]*>/',
			static fn( array $m ): string => $embed . $m[0],
			$html,
			1
		);
	}

	if ( function_exists( '\\Courtneyr\\Child\\Microformats\\cited_title_is_h1' ) && \Courtneyr\Child\Microformats\cited_title_is_h1( $post_id ) ) {
		$html = (string) preg_replace( '/<h2(\b[^>]*\bpk-title\b[^>]*>.*?)<\/h2>/s', '<h1$1</h1>', $html, 1 );
	}

	return add_article_classes( $html, 'cr-repost-clip', 'cr-repost-clip--single', 'cr-paper-sheet', 'cr-paper-sheet--torn' );
}
add_filter( 'render_block_post-kinds-indieweb/repost-card', __NAMESPACE__ . '\\single_card', 10, 3 );

/**
 * Print a clipping on the single of a repost that has no card block.
 *
 * A repost filed from the classic meta box holds its source in post meta and
 * no repost-card, so nothing would print the clipping or its u-repost-of
 * h-cite. A synthetic card, built from the plugin's facts, goes ahead of the
 * content at priority 20: after do_blocks and wpautop, before the plugin's
 * hidden data (99) and the singular wrapper (100), so it lands inside the
 * h-entry and wpautop never sees it.
 *
 * @param string $content Post content.
 * @return string
 */
function meta_only_clipping( string $content ): string {
	if ( ! is_repost_single() || ! in_the_loop() || is_feed() || doing_filter( 'get_the_excerpt' ) ) {
		return $content;
	}
	$post_id = (int) get_the_ID();
	if ( ! $post_id || get_queried_object_id() !== $post_id || '' !== (string) get_query_var( 'atproto', '' ) ) {
		return $content;
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof \WP_Post || post_password_required( $post ) || has_block( 'post-kinds-indieweb/repost-card', $post ) || false !== strpos( $content, 'k-repost' ) ) {
		return $content;
	}

	$facts = facts( $post_id );
	$attrs = array(
		'title'       => fact( $facts, 'cited_title' ),
		'url'         => fact( $facts, 'url' ),
		'author'      => fact( $facts, 'author' ),
		'description' => fact( $facts, 'summary' ),
		'image'       => fact( $facts, 'card_image' ),
	);
	if ( '' === $attrs['url'] && '' === $attrs['title'] ) {
		return $content;
	}

	$card = render_block(
		array(
			'blockName'    => 'post-kinds-indieweb/repost-card',
			'attrs'        => array_filter( $attrs, static fn( string $value ): bool => '' !== $value ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
	return $card . $content;
}
add_filter( 'the_content', __NAMESPACE__ . '\\meta_only_clipping', 20 );

/**
 * Treat a repost's card image as the picture the featured image would repeat.
 *
 * `featured_in_content` only matches a core/image, and a card image is a URL.
 *
 * @param bool     $repeats Whether an earlier filter found a repeat.
 * @param \WP_Post $post    Post.
 * @return bool
 */
function featured_repeats( bool $repeats, $post ): bool {
	if ( $repeats || ! $post instanceof \WP_Post || ! has_term( 'repost', 'kind', $post ) ) {
		return $repeats;
	}
	return '' !== fact( facts( $post->ID ), 'card_image' );
}
add_filter( 'courtneyr_child_featured_repeats', __NAMESPACE__ . '\\featured_repeats', 10, 2 );

/**
 * Register repost with the editor parity registry.
 *
 * The registry already lists every kind term; this keeps the repost canvas
 * classes when the term list is filtered.
 *
 * @param mixed $kinds Registry keyed by kind slug.
 * @return array<string, mixed>
 */
function register_editor_parity( $kinds ): array {
	$kinds = is_array( $kinds ) ? $kinds : array();
	if ( ! isset( $kinds['repost'] ) || ! is_array( $kinds['repost'] ) ) {
		$kinds['repost'] = array( 'classes' => array( 'single-post', 'kind-repost' ) );
	}
	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );

/**
 * Enqueue the repost stylesheet.
 *
 * @return void
 */
function enqueue_styles(): void {
	$deps = array();
	foreach ( array( 'courtneyr-components', 'courtneyr-post-kinds', 'courtneyr-paper' ) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			$deps[] = $handle;
		}
	}
	wp_enqueue_style( STYLE_HANDLE, COURTNEYR_CHILD_URI . '/assets/css/cr-repost.css', $deps, COURTNEYR_CHILD_VERSION );
}

/**
 * Load the stylesheet where a repost object can appear on the front end.
 *
 * Runs after the theme's own sheets are enqueued, so the dependencies below
 * that are registered by then print first.
 *
 * @return void
 */
function enqueue_front_end_styles(): void {
	if ( is_admin() ) {
		return;
	}
	$is_stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) && is_stream_surface();
	if ( ! is_repost_archive() && ! is_repost_single() && ! $is_stream ) {
		return;
	}
	enqueue_styles();
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_front_end_styles', 20 );

/**
 * Load the stylesheet in the editor, where the repost template is edited.
 *
 * @return void
 */
function enqueue_editor_styles(): void {
	if ( is_admin() ) {
		enqueue_styles();
	}
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_editor_styles' );
