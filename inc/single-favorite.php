<?php
/**
 * Single favorite: the scrapbook sheet.
 *
 * A readable favorite single replaces the template header with one sheet that
 * holds a flat star, the kind tape, the post's one H1, the cited record and
 * the picture. The record is rebuilt from `\PKIW\kind_facts()` only, so the
 * plugin card's markup is dropped rather than restyled. A favorite with no
 * card (Quick Post, or a Micropub paragraph) keeps its content inside the same
 * sheet. A protected favorite keeps the template header, where
 * cr-favorite.css makes the title visible again.
 *
 * Shared helpers live in inc/favorite.php, which functions.php loads with
 * this file.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleFavorite;

use function Courtneyr\Child\Favorite\comparable;
use function Courtneyr\Child\Favorite\facts;
use function Courtneyr\Child\Favorite\is_favorite;
use function Courtneyr\Child\Favorite\kind_label;
use function Courtneyr\Child\Favorite\star_svg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The plugin's favorite card block. */
const CARD_BLOCK = 'post-kinds-indieweb/favorite-card';

/**
 * Facts for the queried favorite single, kept per post for the request.
 *
 * @param bool $refresh Whether to rebuild the kept answer.
 * @return array<string, mixed> Facts, or [] when this isn't a readable favorite single.
 */
function single_facts( bool $refresh = false ): array {
	static $kept = array();

	if ( ! \is_singular( 'post' ) ) {
		return array();
	}
	$post_id = (int) get_queried_object_id();
	if ( $refresh ) {
		unset( $kept[ $post_id ] );
	}
	if ( ! isset( $kept[ $post_id ] ) ) {
		$kept[ $post_id ] = $post_id > 0 && is_favorite( $post_id ) && ! post_password_required() ? facts( $post_id ) : array();
	}
	return $kept[ $post_id ];
}

/**
 * The wide column's resolver for favorites (inc/single-wide-column.php).
 *
 * The facts belong to the post, not the page, so every page of a readable
 * favorite prints the sheet and reads these facts from the decision. The
 * sheet takes the column when the page's first card is a top-level block, or
 * when the page has no card (the sheet then holds the page). A card inside an
 * authored block (a Group, say) keeps the sheet in that block, at its width.
 * When the page holds a block the walker cannot see inside (a synced pattern,
 * say) there is no decision: the sheet renders as it does without the column.
 *
 * @param \WP_Post $post    Post.
 * @param string   $content The content of the page being printed.
 * @return array{wide: bool, data: array<string, mixed>, flow: ?array{class: string, position: int}}|null Null when this isn't a readable favorite, or the page's structure is unknown.
 */
function page_sheet( \WP_Post $post, string $content ): ?array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	$facts = single_facts();
	if ( array() === $facts ) {
		return null;
	}
	$position = null;
	$place = \Courtneyr\Child\SingleWideColumn\first_block_place( $content, CARD_BLOCK, $position );
	if ( 'unknown' === $place ) {
		return null;
	}
	return array(
		'wide' => 'nested' !== $place,
		'data' => $facts,
		'flow' => null === $position ? null : array( 'class' => 'cr-fav-flow', 'position' => $position ),
	);
}

/**
 * Whether the sheet owns this page's H1.
 *
 * @return bool
 */
function is_sheet(): bool {
	return array() !== single_facts();
}

/**
 * Tell the shared single-object filter that the sheet owns the page H1.
 *
 * @param mixed    $holds Whether another lane already owns the H1.
 * @param \WP_Post $post  Post being rendered.
 * @return bool
 */
function holds_h1( $holds, \WP_Post $post ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return true === $holds || is_sheet();
}
add_filter( 'courtneyr_child_single_object_holds_h1', __NAMESPACE__ . '\\holds_h1', 10, 2 );

/**
 * Safe rel tokens for the source link.
 *
 * @param string $stored Stored rel string.
 * @return string Rel tokens.
 */
function rel_tokens( string $stored ): string {
	$out    = array( 'noopener', 'noreferrer' );
	$tokens = preg_split( '/\s+/', strtolower( $stored ) );
	foreach ( false === $tokens ? array() : $tokens as $token ) {
		if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $token ) || in_array( $token, $out, true ) ) {
			continue;
		}
		$out[] = $token;
	}
	return implode( ' ', $out );
}

/**
 * The cited URL as the one link out, named by its host.
 *
 * The visible text is the host, so the new-tab name starts with it too
 * (WCAG 2.5.3 Label in Name).
 *
 * @param array<string, mixed> $facts Favorite facts.
 * @param string               $classes Link class: 'u-url' inside the h-cite, '' elsewhere.
 * @return string Link HTML, or '' when no URL is stored.
 */
function source_link( array $facts, string $classes ): string {
	$url  = (string) ( $facts['url'] ?? '' );
	$host = (string) ( $facts['host'] ?? '' );
	if ( '' === $url || '' === esc_url( $url ) ) {
		return '';
	}
	if ( '' === $host ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	}
	$label = ' aria-label="' . esc_attr( $host . __( ' (opens in a new tab)', 'courtneyr-child' ) ) . '"';
	if ( function_exists( '\\PKIW\\pkiw_new_tab_label_attr' ) ) {
		$label = (string) \PKIW\pkiw_new_tab_label_attr( $host );
	}
	$attr = ' class="' . esc_attr( trim( $classes . ' cr-fav-link' ) ) . '"';
	return '<a' . $attr . ' href="' . esc_url( $url ) . '" target="_blank" rel="' . esc_attr( rel_tokens( (string) ( $facts['rel'] ?? '' ) ) ) . '"' . $label . '>' . esc_html( $host ) . '</a>';
}

/**
 * One definition-list row.
 *
 * @param string $key   Row key.
 * @param string $label Row label.
 * @param string $html  Row value, already escaped.
 * @return string
 */
function row_html( string $key, string $label, string $html ): string {
	return '<div class="cr-fav-row cr-fav-row--' . esc_attr( $key ) . '"><dt>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd></div>';
}

/**
 * The picture mount, or ''.
 *
 * The picture loads eagerly: it is the page's largest image beside the sheet.
 * An attachment goes through core so it keeps its sizes; a remote URL prints
 * as a plain image that the cover-fallback hook collapses when it fails.
 *
 * @param array<string, mixed> $picture Picture facts.
 * @return string
 */
function mount_html( array $picture ): string {
	$url = (string) ( $picture['url'] ?? '' );
	if ( '' === $url ) {
		return '';
	}
	$alt = (string) ( $picture['alt'] ?? '' );
	$img = '';
	if ( (int) ( $picture['attachment_id'] ?? 0 ) > 0 ) {
		$img = (string) wp_get_attachment_image(
			(int) $picture['attachment_id'],
			'large',
			false,
			array(
				'class'          => 'cr-fav-sheet__img skip-lazy',
				'alt'            => $alt,
				'loading'        => false,
				'fetchpriority'  => 'high',
				'decoding'       => 'async',
				'data-skip-lazy' => '1',
			)
		);
	}
	if ( '' === $img ) {
		$img = '<img class="cr-fav-sheet__img skip-lazy" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" fetchpriority="high" decoding="async" data-skip-lazy="1">';
	}
	return '<div class="cr-fav-sheet__mount" data-cr-cover-fallback>' . $img . '</div>';
}

/**
 * The rows both branches print, each only when it is stored.
 *
 * @param array<string, mixed> $facts Favorite facts.
 * @param bool                 $mf    Whether the rows sit inside the h-cite.
 * @return string
 */
function rows_html( array $facts, bool $mf ): string {
	$rows   = '';
	$author = (string) ( $facts['author'] ?? '' );
	if ( '' !== $author ) {
		$rows .= row_html( 'author', __( 'By', 'courtneyr-child' ), $mf ? '<span class="p-author h-card"><span class="p-name">' . esc_html( $author ) . '</span></span>' : esc_html( $author ) );
	}
	$source = source_link( $facts, $mf ? 'u-url' : '' );
	if ( '' !== $source ) {
		$rows .= row_html( 'source', __( 'Source', 'courtneyr-child' ), $source );
	}
	$day = is_array( $facts['favorited'] ?? null ) ? $facts['favorited'] : array( '', '' );
	if ( '' !== (string) ( $day[0] ?? '' ) ) {
		$rows .= row_html( 'favorited', __( 'Favorited', 'courtneyr-child' ), '<time' . ( $mf ? ' class="dt-published"' : '' ) . ' datetime="' . esc_attr( (string) $day[0] ) . '">' . esc_html( (string) ( $day[1] ?? '' ) ) . '</time>' );
	}
	return '' === $rows ? '' : '<dl class="cr-fav-rows">' . $rows . '</dl>';
}

/**
 * The cited title: printed once when it differs from the page's name.
 *
 * @param array<string, mixed> $facts Favorite facts.
 * @return array{0: string, 1: bool} Cited title and whether it prints.
 */
function cited_title( array $facts ): array {
	$cited = trim( (string) ( $facts['cited_title'] ?? '' ) );
	return array( $cited, '' !== $cited && comparable( $cited ) !== comparable( (string) ( $facts['name'] ?? '' ) ) );
}

/**
 * The record for a favorite whose content holds a card: the h-cite.
 *
 * @param array<string, mixed> $facts Favorite facts.
 * @return string
 */
function card_record( array $facts ): string {
	$has_url = '' !== (string) ( $facts['url'] ?? '' ) && '' !== esc_url( (string) $facts['url'] );

	list( $cited, $prints ) = cited_title( $facts );

	$out = '<article class="cr-fav-sheet__record h-cite' . ( $has_url ? ' u-favorite-of' : '' ) . '">';
	if ( $prints ) {
		$out .= '<p class="cr-fav-sheet__cited p-name">' . esc_html( $cited ) . '</p>';
	} elseif ( '' !== $cited ) {
		$out .= '<data class="p-name" value="' . esc_attr( $cited ) . '" hidden></data>';
	}
	$picture = is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array();
	if ( 'card' === ( $picture['source'] ?? '' ) && '' !== esc_url( (string) ( $picture['url'] ?? '' ) ) ) {
		$out .= '<data class="u-photo" value="' . esc_url( (string) $picture['url'] ) . '" hidden></data>';
	}
	$out .= rows_html( $facts, true );
	if ( '' !== trim( (string) ( $facts['summary'] ?? '' ) ) ) {
		$out .= '<p class="cr-fav-sheet__description p-content">' . nl2br( esc_html( trim( (string) $facts['summary'] ) ) ) . '</p>';
	}
	return $out . '</article>';
}

/**
 * The record for a favorite with no card: plain rows, and no h-cite of its own.
 *
 * The plugin prints the favorite-of property as hidden data on the entry.
 *
 * @param array<string, mixed> $facts Favorite facts.
 * @return string
 */
function plain_record( array $facts ): string {
	list( $cited, $prints ) = cited_title( $facts );

	$inner = ( $prints ? '<p class="cr-fav-sheet__cited">' . esc_html( $cited ) . '</p>' : '' ) . rows_html( $facts, false );
	return '' === $inner ? '' : '<div class="cr-fav-sheet__record">' . $inner . '</div>';
}

/**
 * The sheet's head: star, tape and the page's one H1.
 *
 * @param array<string, mixed> $facts Favorite facts.
 * @return string
 */
function sheet_head( array $facts ): string {
	$out  = '<span class="cr-fav-sheet__star" aria-hidden="true">' . star_svg() . '</span>';
	$out .= '<p class="cr-fav-sheet__tape"><span class="pk-kindlabel">' . esc_html( kind_label( 'favorite-card' ) ) . '</span><span class="cr-fav-sheet__marker" aria-hidden="true"></span></p>';
	$out .= '<h1 class="cr-fav-sheet__title' . ( ! empty( $facts['name_is_title'] ) ? ' p-name' : '' ) . '">' . esc_html( (string) ( $facts['name'] ?? '' ) ) . '</h1>';
	return $out;
}

/**
 * The record and picture side by side from 64rem.
 *
 * @param string $inner Record and mount markup.
 * @return string
 */
function body_html( string $inner ): string {
	return '' === $inner ? '' : '<div class="cr-fav-sheet__body">' . $inner . '</div>';
}

/**
 * Render core's post content, replacing its first real Favorite card with the sheet.
 *
 * Scope the replacement to this invocation, so an excerpt, a card for another
 * post in a nested loop, or a second render cannot consume the replacement.
 * PKIW's entry wrapper and hidden properties are added afterward as usual.
 *
 * @param callable             $render Core's Post Content render callback.
 * @param array<string, mixed> $attrs Core block attributes.
 * @param string               $content Incoming block content.
 * @param \WP_Block            $block The main post-content block.
 * @param array<string, mixed> $facts The sheet's facts.
 * @param int                  $depth Main content filter depth.
 * @return string
 */
function render_sheet( callable $render, array $attrs, string $content, \WP_Block $block, array $facts, int $depth ): string {
	$post_id   = (int) $block->context['postId'];
	$replaced  = false;
	$mount     = mount_html( is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array() );
	$head      = '<div class="cr-fav-sheet">' . sheet_head( $facts );
	$swap      = static function ( string $html, array $parsed, \WP_Block $card ) use ( $depth, $post_id, $head, $facts, $mount, &$replaced ): string {
		if ( $replaced || $depth !== \Courtneyr\Child\SingleWideColumn\content_depth()
			|| (int) ( $card->context['postId'] ?? 0 ) !== $post_id || '' === trim( $html )
		) {
			return $html;
		}
		$replaced = true;
		return $head . body_html( card_record( $facts ) . $mount ) . '</div>';
	};
	$fallback = static function ( string $html ) use ( $depth, $head, $facts, $mount, &$replaced ): string {
		if ( $replaced || $depth !== \Courtneyr\Child\SingleWideColumn\content_depth() ) {
			return $html;
		}
		return $head . body_html( plain_record( $facts ) . $mount ) . $html . '</div>';
	};
	\add_filter( 'render_block_' . CARD_BLOCK, $swap, 10, 3 );
	\add_filter( 'the_content', $fallback, 19 );
	try {
		return $render( $attrs, $content, $block );
	} finally {
		\remove_filter( 'render_block_' . CARD_BLOCK, $swap, 10 );
		\remove_filter( 'the_content', $fallback, 19 );
	}
}
