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
 * Where the plugin's hidden entry meta starts in the singular wrapper.
 *
 * @param string $html  Post-content HTML.
 * @param int    $start Offset of the wrapper's inner start.
 * @return int Offset of the hidden meta, or the wrapper's last closing tag.
 */
function meta_offset( string $html, int $start ): int {
	if ( preg_match_all( '/<a class="u-url" href="[^"]*" hidden><\/a><time class="dt-published"/', $html, $found, PREG_OFFSET_CAPTURE, $start ) && ! empty( $found[0] ) ) {
		return (int) end( $found[0] )[1];
	}
	$close = strrpos( $html, '</div>' );
	return false === $close ? strlen( $html ) : (int) $close;
}

/**
 * Rebuild the main post content as the sheet.
 *
 * @param string               $html  Rendered post-content block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function sheet( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__content' ) ) {
		return $html;
	}
	$facts = single_facts();
	if ( array() === $facts ) {
		return $html;
	}

	// The format avatar is aria-hidden decoration the sheet's star replaces.
	$html = (string) preg_replace( '/<span class="[^"]*\bsingle-post__format-avatar\b[^"]*"[^>]*>.*?<\/svg><\/span>/s', '', $html, 1 );

	$start = 0;
	if ( preg_match( '/<div class="[^"]*\bpkiw-singular-entry\b[^"]*">/', $html, $open, PREG_OFFSET_CAPTURE ) ) {
		$start = (int) $open[0][1] + strlen( $open[0][0] );
	}
	$end     = meta_offset( $html, $start );
	$content = (string) substr( $html, $start, $end - $start );
	$mount   = mount_html( is_array( $facts['picture'] ?? null ) ? $facts['picture'] : array() );

	$before = '';
	$after  = '';
	if ( preg_match( '/<article\b[^>]*\bclass="[^"]*\bk-favorite\b[^"]*"[^>]*>.*?<\/article>/s', $content, $card, PREG_OFFSET_CAPTURE ) ) {
		$before = (string) substr( $content, 0, (int) $card[0][1] );
		$after  = (string) substr( $content, (int) $card[0][1] + strlen( $card[0][0] ) );
		$body   = body_html( card_record( $facts ) . $mount );
	} else {
		$body = body_html( plain_record( $facts ) . $mount ) . $content;
	}

	$sheet = '<div class="cr-fav-sheet">' . sheet_head( $facts ) . $body . '</div>';
	return (string) substr( $html, 0, $start ) . $before . $sheet . $after . (string) substr( $html, $end );
}
add_filter( 'render_block', __NAMESPACE__ . '\\sheet', 20, 2 );
