<?php
/**
 * Repost Stream clippings (issue 255).
 *
 * Every repost on the Stream, card-only or with a note, from the meta box or
 * from Micropub, becomes one compact taped clipping with a single permalink
 * anchor. The card is rebuilt from the plugin's facts, so Courtney's note
 * never prints on it, and the cited source stays in the card's hidden h-cite
 * so a parser reads `repost-of` as the source rather than the permalink.
 *
 * Load after inc/repost.php, whose helpers this file uses.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamRepost;

use function Courtneyr\Child\HomeSections\is_stream_surface;
use function Courtneyr\Child\Repost\add_article_classes;
use function Courtneyr\Child\Repost\block_post_id;
use function Courtneyr\Child\Repost\cut_elements;
use function Courtneyr\Child\Repost\fact;
use function Courtneyr\Child\Repost\facts;
use function Courtneyr\Child\Repost\heading_level;
use function Courtneyr\Child\Repost\take_element;
use function Courtneyr\Child\Repost\tilt_index;
use const Courtneyr\Child\Repost\ITEM_CLASS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin helpers the rebuild needs.
 */
const PLUGIN_FUNCTIONS = array(
	'\\PKIW\\link_title_to_post',
	'\\PKIW\\inject_post_date_into_card',
	'\\PKIW\\apply_stream_heading_level',
	'\\PKIW\\get_kind_label',
);

/**
 * Whether every plugin helper the rebuild calls exists.
 *
 * @return bool
 */
function plugin_ready(): bool {
	foreach ( PLUGIN_FUNCTIONS as $name ) {
		if ( ! function_exists( $name ) ) {
			return false;
		}
	}
	return true;
}

/**
 * The visible kind word, from the plugin's label filter.
 *
 * @return string
 */
function kind_label(): string {
	// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- plugin string, so the plugin's translations apply.
	return (string) \PKIW\get_kind_label( __( 'Repost', 'post-kinds-for-indieweb-in-block-themes' ), 'repost', 'stream-card' );
}

/**
 * The synthetic repost card for a post, built from its facts alone.
 *
 * No image, no rel and no repostedAt: the Stream clipping prints none of
 * them, and a card with no repostedAt prints no .pk-meta, so the cite never
 * claims a publish date.
 *
 * @param array<string, mixed> $facts Repost facts.
 * @return string Card HTML, or ''.
 */
function synthetic_card( array $facts ): string {
	$attrs = array(
		'title'       => fact( $facts, 'cited_title' ),
		'url'         => fact( $facts, 'url' ),
		'author'      => fact( $facts, 'author' ),
		'description' => fact( $facts, 'summary' ),
	);
	$card  = render_block(
		array(
			'blockName'    => 'post-kinds-indieweb/repost-card',
			'attrs'        => array_filter( $attrs, static fn( string $value ): bool => '' !== $value ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
	return false !== strpos( $card, 'k-repost' ) ? $card : '';
}

/**
 * Move the injected date ahead of the title, and stop it claiming a source date.
 *
 * The plugin's date helper puts the date after the title heading. The
 * Stream order is label, date, title, so the date moves to just before the
 * caption, after the kind label. Its time loses dt-published: the entry
 * takes its own date from ensure_entry_properties().
 *
 * @param string $html Card HTML.
 * @return string
 */
function date_before_caption( string $html ): string {
	$date = take_element( $html, 'p', 'pk-stream-date' );
	if ( '' === $date ) {
		return $html;
	}
	$date = (string) preg_replace( '/\sclass="dt-published"/', '', $date, 1 );
	return (string) preg_replace_callback(
		'/<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-caption(?![\w-])[^"]*"[^>]*>/',
		static fn( array $m ): string => $date . $m[0],
		$html,
		1
	);
}

/**
 * Print the source host as plain text at the end of the caption.
 *
 * @param string $html Card HTML.
 * @param string $host Source host.
 * @return string
 */
function add_host( string $html, string $host ): string {
	if ( '' === $host ) {
		return $html;
	}
	$line = '<p class="pk-sub cr-repost-clip__host">' . esc_html( $host ) . '</p>';
	return (string) preg_replace_callback(
		'/(<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-caption(?![\w-])[^"]*"[^>]*>.*?)(<\/div>)/s',
		static fn( array $m ): string => $m[1] . $line . $m[2],
		$html,
		1
	);
}

/**
 * Replace the kind label's text with the plugin's label for the Stream.
 *
 * @param string $html Card HTML.
 * @return string
 */
function set_kind_label( string $html ): string {
	$label = kind_label();
	return (string) preg_replace_callback(
		'/(<span class="pk-kindlabel">)(.*?)(<\/span>)/s',
		static fn( array $m ): string => $m[1] . esc_html( $label ) . $m[3],
		$html,
		1
	);
}

/**
 * Rebuild a repost's Stream card as a compact clipping.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block.
 * @param \WP_Block $instance Block instance with the Query Loop's post ID.
 * @return string
 */
function clipping_card( string $html, array $block, $instance ): string {
	if ( '' === $html || ! is_stream_surface() ) {
		return $html;
	}
	// The archive's own clipping (Site Editor preview included) wins.
	if ( false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), ITEM_CLASS ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( 'repost', 'kind', $post ) || post_password_required( $post ) || ! plugin_ready() ) {
		return $html;
	}

	$facts = facts( $post_id );
	if ( '' === fact( $facts, 'url' ) && '' === fact( $facts, 'cited_title' ) ) {
		return $html;
	}

	$card = synthetic_card( $facts );
	if ( '' === $card ) {
		return $html;
	}

	$card = \PKIW\link_title_to_post( $card, $post );
	$card = \PKIW\inject_post_date_into_card( $card, $post );
	$card = \PKIW\apply_stream_heading_level( $card, heading_level( $block['attrs']['headingLevel'] ?? 2, 2 ) );
	$card = date_before_caption( $card );
	$card = add_host( $card, fact( $facts, 'host' ) );
	$card = set_kind_label( $card );
	$card = cut_elements( $card, 'div', 'pk-badge' );

	return add_article_classes(
		$card,
		'pk-card--stream',
		'cr-repost-clip--stream',
		'cr-repost-clip--tilt-' . tilt_index( $post_id, 6 )
	);
}

if ( function_exists( 'Courtneyr\\Child\\Repost\\facts' ) ) {
	add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\clipping_card', 10, 3 );
}
