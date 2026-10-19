<?php
/**
 * Video Stream tape spines.
 *
 * The plugin owns the entry semantics. This adapter removes the generic
 * card's media and summary while retaining its title, date and hidden data.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamVideo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add one class to the first article in a card fragment.
 *
 * @param string $html       Card fragment.
 * @param string $class_name Class to add.
 * @return string
 */
function add_article_class( string $html, string $class_name ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'article' ) ) {
		return $html;
	}
	$tags->add_class( $class_name );
	return $tags->get_updated_html();
}

/**
 * Whether the card's article carries one class.
 *
 * @param string $html       Card fragment.
 * @param string $class_name Class to find.
 * @return bool
 */
function article_has_class( string $html, string $class_name ): bool {
	$tags  = new \WP_HTML_Tag_Processor( $html );
	$query = array(
		'tag_name'   => 'article',
		'class_name' => $class_name,
	);
	return $tags->next_tag( $query );
}

/**
 * Insert one child immediately after the first article opening tag.
 *
 * @param string $html  Card fragment.
 * @param string $child Child markup.
 * @return string
 */
function prepend_article_child( string $html, string $child ): string {
	if ( ! preg_match( '/<article\b[^>]*>/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$at = (int) $match[0][1] + strlen( $match[0][0] );
	return substr( $html, 0, $at ) . $child . substr( $html, $at );
}

/**
 * Build a trusted Stream runtime from normalized facts.
 *
 * @param array<string, mixed> $facts Video facts.
 * @return string
 */
function runtime_html( array $facts ): string {
	if ( 0 >= (int) ( $facts['runtime_seconds'] ?? 0 ) ) {
		return '';
	}

	return '<time class="cr-tape-spine__runtime" datetime="' . esc_attr( (string) ( $facts['runtime_iso'] ?? '' ) ) . '">'
		. esc_html( (string) ( $facts['runtime_clock'] ?? '' ) )
		. '<span class="pk-sr-only"> (' . esc_html( (string) ( $facts['runtime_human'] ?? '' ) ) . ')</span></time>';
}

/**
 * Insert a runtime immediately after the plugin's stored-day line.
 *
 * @param string $html    Card fragment.
 * @param string $runtime Runtime markup.
 * @return string
 */
function insert_runtime( string $html, string $runtime ): string {
	if ( '' === $runtime ) {
		return $html;
	}

	$pattern = '/(<p\b[^>]*\bclass="[^"]*(?<![\w-])pk-stream-date(?![\w-])[^"]*"[^>]*>.*?<\/p>)/is';
	return (string) preg_replace( $pattern, '$1' . $runtime, $html, 1 );
}

/**
 * Turn the plugin's generic video card into a Stream tape spine.
 *
 * Runs at priority 11, after the archive tape adapter, so a card the tape
 * already shaped is left alone and the plugin's entry properties (99) follow.
 *
 * @param string $html     Rendered Stream card.
 * @param array  $block    Parsed Stream-card block.
 * @param mixed  $instance Block instance with post context.
 * @return string
 */
function spine_card( string $html, array $block, $instance ): string {
	if (
		! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		|| ! \Courtneyr\Child\HomeSections\is_stream_surface()
		|| ! function_exists( '\\PKIW\\kind_facts' )
	) {
		return $html;
	}

	// The archive tape (inc/video.php, priority 10) owns any card it already shaped.
	if ( article_has_class( $html, 'cr-shelf-tape' ) ) {
		return $html;
	}

	$post_id = is_object( $instance ) && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	if ( 0 >= $post_id || ! has_term( 'video', 'kind', $post_id ) ) {
		return $html;
	}

	$protected = article_has_class( $html, 'pk-card--protected' );
	if ( $protected ) {
		return add_article_class( $html, 'cr-tape-spine' );
	}
	if ( ! function_exists( '\\Courtneyr\\Child\\MediaShelf\\cut_elements' ) ) {
		return $html;
	}
	$html = add_article_class( $html, 'cr-tape-spine' );

	foreach ( array(
		array( 'div', 'pk-badge' ),
		array( 'div', 'pk-media' ),
		array( 'p', 'pk-excerpt' ),
		array( 'div', 'pk-meta' ),
	) as $cut ) {
		$html = \Courtneyr\Child\MediaShelf\cut_elements( $html, $cut[0], $cut[1] );
	}

	$facts = (array) \PKIW\kind_facts( $post_id );
	$html  = insert_runtime( $html, runtime_html( $facts ) );
	return prepend_article_child( $html, '<span class="cr-tape-spine__strip" aria-hidden="true"></span>' );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\spine_card', 11, 3 );
