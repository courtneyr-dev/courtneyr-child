<?php
/**
 * Reply archive letters (Post Kinds for IndieWeb issue 253).
 *
 * The plugin owns reply facts and archive grouping. This adapter rebuilds
 * the archive card as one linked paper letter, then decorates the final
 * post-template list with deterministic paper and host treatments.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Letters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CARD_STYLE    = 'is-style-cr-letter';
const PAPERS        = array( 'sky-blue', 'periwinkle', 'light-orange', 'selective-yellow', 'printer-ivory' );
const EXCERPT_WORDS = 25;

/** Register the letter treatment for the plugin's stream card. */
function register_card_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => 'cr-letter',
				'label' => __( 'Letter', 'courtneyr-child' ),
			)
		);
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'init', __NAMESPACE__ . '\\register_card_style' );
}

/** Whether this request is the reply kind archive. */
function is_reply_archive(): bool {
	return function_exists( 'is_tax' ) && is_tax( 'kind', 'reply' );
}

/** Load letter paint on reply archives, reply singles, Stream surfaces and in the editor. */
function enqueue_styles(): void {
	$is_single = function_exists( '\\Courtneyr\\Child\\SingleReply\\is_reply_single' ) && \Courtneyr\Child\SingleReply\is_reply_single();
	$is_stream = function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' ) && \Courtneyr\Child\HomeSections\is_stream_surface();
	if ( ! function_exists( 'wp_enqueue_style' ) || ( ! is_admin() && ! is_reply_archive() && ! $is_single && ! $is_stream ) ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-letters',
		COURTNEYR_CHILD_URI . '/assets/css/cr-letters.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
}

/**
 * Reply facts normalized for theme rendering.
 *
 * @param \WP_Post $post Post.
 * @return array{url:string,host:string,name:string,protected:bool}
 */
function facts_for( \WP_Post $post ): array {
	$protected = function_exists( 'post_password_required' ) && post_password_required( $post );
	$title     = function_exists( 'get_the_title' ) ? (string) get_the_title( $post ) : (string) $post->post_title;
	if ( $protected ) {
		return array(
			'url'       => '',
			'host'      => '',
			'name'      => $title,
			'protected' => true,
		);
	}
	$facts = function_exists( '\\PKIW\\kind_facts' ) ? \PKIW\kind_facts( (int) $post->ID ) : array();
	if ( ! is_array( $facts ) ) {
		$facts = array();
	}
	$name = trim( (string) ( $facts['name'] ?? '' ) );
	if ( '' === $name ) {
		$name = $title;
	}
	return array(
		'url'       => (string) ( $facts['url'] ?? '' ),
		'host'      => (string) ( $facts['host'] ?? '' ),
		'name'      => $name,
		'protected' => false,
	);
}

/**
 * Whether a post stores its own visible title.
 *
 * @param \WP_Post $post Post.
 * @return bool
 */
function named( \WP_Post $post ): bool {
	return '' !== trim( (string) $post->post_title );
}

/**
 * Plain-text excerpt for a public reply.
 *
 * @param \WP_Post $post  Post.
 * @param int      $words Word limit.
 * @return string
 */
function excerpt_text( \WP_Post $post, int $words ): string {
	if ( function_exists( 'post_password_required' ) && post_password_required( $post ) ) {
		return '';
	}
	$text = function_exists( 'get_the_excerpt' ) ? (string) get_the_excerpt( $post ) : (string) $post->post_content;
	$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : (string) preg_replace( '/<[^>]*>/', '', $text );
	return function_exists( 'wp_trim_words' ) ? wp_trim_words( $text, $words, '' ) : trim( $text );
}

/**
 * Hidden reply target data.
 *
 * @param string $url Cited URL.
 * @return string
 */
function target_data( string $url ): string {
	if ( '' === trim( $url ) ) {
		return '';
	}
	$escaped = esc_url( $url );
	if ( '' === $escaped ) {
		return '';
	}
	return '<data class="u-in-reply-to" value="' . $escaped . '" hidden></data>';
}

/**
 * Clamp a block heading level.
 *
 * @param mixed $level Attribute value.
 * @param int   $fallback_level Default level.
 * @return int
 */
function heading_level( $level, int $fallback_level ): int {
	$level = is_numeric( $level ) ? (int) $level : $fallback_level;
	return max( 2, min( 4, $level ) );
}

/**
 * Whether a class attribute includes the archive letter style.
 *
 * @param string $class_name Class attribute.
 * @return bool
 */
function has_letter_style( string $class_name ): bool {
	return 1 === preg_match( '/(?:^|\\s)' . preg_quote( CARD_STYLE, '/' ) . '(?:\\s|$)/', $class_name );
}

/**
 * Resolve the current Query Loop post from a block instance.
 *
 * @param mixed $instance Block instance.
 * @return \WP_Post|null
 */
function instance_post( $instance ): ?\WP_Post {
	$post_id = is_object( $instance ) && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : ( function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0 );
	$post    = $post_id && function_exists( 'get_post' ) ? get_post( $post_id ) : null;
	return $post instanceof \WP_Post ? $post : null;
}

/**
 * Build one archive letter from reply facts.
 *
 * @param string $html     Rendered stream card.
 * @param array  $block    Parsed stream-card block.
 * @param mixed  $instance Block instance with post context.
 * @return string
 */
function letter_card( string $html, array $block, $instance ): string {
	if ( ! has_letter_style( (string) ( $block['attrs']['className'] ?? '' ) ) ) {
		return $html;
	}
	$post = instance_post( $instance );
	if ( ! $post instanceof \WP_Post || ! function_exists( 'has_term' ) || ! has_term( 'reply', 'kind', $post ) ) {
		return $html;
	}
	$facts     = facts_for( $post );
	$uid       = wp_unique_id( '' );
	$level     = heading_level( $block['attrs']['headingLevel'] ?? 3, 3 );
	$name      = '' !== $facts['name'] ? $facts['name'] : ( function_exists( 'get_the_title' ) ? (string) get_the_title( $post ) : (string) $post->post_title );
	$title_cls = 'cr-letter__title' . ( named( $post ) ? ' p-name' : '' );
	$classes   = 'cr-letter' . ( $facts['protected'] ? ' cr-letter--protected' : '' ) . ' k-reply';
	$out       = '<article class="' . esc_attr( $classes ) . '">';
	$out      .= '<h' . $level . ' class="' . esc_attr( $title_cls ) . '"><a id="cr-letter-t-' . esc_attr( $uid ) . '" class="u-url" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $name ) . '</a></h' . $level . '>';
	if ( ! $facts['protected'] ) {
		$excerpt = excerpt_text( $post, EXCERPT_WORDS );
		if ( '' !== $excerpt ) {
			$out .= '<p class="cr-letter__excerpt p-summary">' . esc_html( $excerpt ) . '</p>';
		}
		$out .= '<time id="cr-letter-d-' . esc_attr( $uid ) . '" class="dt-published cr-letter__date" datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '" hidden>' . esc_html( (string) get_the_date( '', $post ) ) . '</time>';
		$out .= target_data( $facts['url'] );
	}
	return $out . '</article>';
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\letter_card', 10, 3 );
}

/**
 * Stable host ink index.
 *
 * @param string $label Host heading label.
 * @return int
 */
function host_ink( string $label ): int {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		return \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( 'reply-host', strtolower( $label ) ), 3, 3 );
	}
	return abs( crc32( strtolower( $label ) ) ) % 3;
}

/**
 * Seeded paper token for one letter.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function paper_for( int $post_id ): string {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		return \Courtneyr\Child\Stamps\seeded_paper( $post_id, PAPERS );
	}
	return PAPERS[ abs( crc32( (string) $post_id ) ) % count( PAPERS ) ];
}

/**
 * Stable trim index.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function trim_for( int $post_id ): int {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		return \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( (string) $post_id ), 12, 4 );
	}
	return abs( crc32( (string) $post_id ) ) % 4;
}

/**
 * Add classes to an HTML class attribute.
 *
 * @param string   $class_attr Existing class attribute value.
 * @param string[] $classes    Classes to add.
 * @return string
 */
function add_classes( string $class_attr, array $classes ): string {
	$current = preg_split( '/\s+/', trim( $class_attr ) );
	$current = false === $current ? array() : array_filter( $current );
	foreach ( $classes as $class_name ) {
		if ( '' !== $class_name && ! in_array( $class_name, $current, true ) ) {
			$current[] = $class_name;
		}
	}
	return trim( implode( ' ', $current ) );
}

/**
 * Add a class list to one opening tag string.
 *
 * @param string   $tag     Opening tag.
 * @param string[] $classes Classes to add.
 * @return string
 */
function add_tag_classes( string $tag, array $classes ): string {
	if ( preg_match( '/\sclass="([^"]*)"/', $tag, $m ) ) {
		return str_replace( $m[0], ' class="' . esc_attr( add_classes( $m[1], $classes ) ) . '"', $tag );
	}
	return rtrim( $tag, '>' ) . ' class="' . esc_attr( implode( ' ', $classes ) ) . '">';
}

/**
 * Add aria-labelledby to duplicate-name letter anchors.
 *
 * @param string $html Post-template HTML.
 * @return string
 */
function label_duplicate_titles( string $html ): string {
	preg_match_all( '/<article\b[^>]*\bclass="[^"]*(?<![\\w-])cr-letter(?![\\w-])[^"]*"[^>]*>.*?<\/article>/is', $html, $matches, PREG_SET_ORDER );
	$counts = array();
	$items  = array();
	foreach ( $matches as $match ) {
		$letter = $match[0];
		if ( ! preg_match( '/<a\b([^>]*)>(.*?)<\/a>/is', $letter, $anchor_match ) || ! preg_match( '/\bid="([^"]+)"/', $anchor_match[1], $id_match ) ) {
			continue;
		}
		if ( ! preg_match( '/<time\b[^>]*\bid="([^"]+)"/is', $letter, $date_match ) ) {
			continue;
		}
		$text = strtolower( trim( html_entity_decode( wp_strip_all_tags( $anchor_match[2] ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $text ) {
			continue;
		}
		$counts[ $text ] = ( $counts[ $text ] ?? 0 ) + 1;
		$items[]         = array( $text, $id_match[1], $date_match[1] );
	}
	foreach ( $items as $item ) {
		if ( 2 > ( $counts[ $item[0] ] ?? 0 ) ) {
			continue;
		}
		$pattern = '/(<a\b(?=[^>]*\bid="' . preg_quote( $item[1], '/' ) . '")(?![^>]*\baria-labelledby=)([^>]*)>)/';
		$html    = (string) preg_replace( $pattern, '<a$2 aria-labelledby="' . esc_attr( $item[1] . ' ' . $item[2] ) . '">', $html, 1 );
	}
	return $html;
}

/**
 * Decorate the final archive post-template markup.
 *
 * @param string $html  Post-template HTML.
 * @param array  $block Parsed post-template block.
 * @return string
 */
function decorate_post_template( string $html, array $block ): string {
	unset( $block );

	if ( ! str_contains( $html, 'cr-letter' ) ) {
		return $html;
	}
	$html = (string) preg_replace_callback(
		'/<li\b([^>]*)\bclass="([^"]*\bwp-block-post\b[^"]*\bpost-(\d+)\b[^"]*)"([^>]*)>(.*?)<\/li>/is',
		static function ( array $m ): string {
			if ( ! str_contains( $m[5], 'cr-letter' ) ) {
				return $m[0];
			}
			$post_id   = (int) $m[3];
			$protected = str_contains( $m[5], 'cr-letter--protected' );
			$classes   = array( 'cr-paper-sheet', 'cr-paper--' . ( $protected ? 'printer-ivory' : paper_for( $post_id ) ) );
			if ( ! $protected ) {
				$classes[] = 'cr-letter-trim--' . trim_for( $post_id );
			}
			$class_attr = add_classes( $m[2], $classes );
			return '<li' . $m[1] . 'class="' . esc_attr( $class_attr ) . '"' . $m[4] . '>' . $m[5] . '</li>';
		},
		$html
	);
	$html = (string) preg_replace_callback(
		'/<h([2-4])\b([^>]*)\bclass="([^"]*(?<![\\w-])pkiw-group__heading(?![\\w-])[^"]*)"([^>]*)>(.*?)<\/h\1>/is',
		static function ( array $m ): string {
			$label      = trim( html_entity_decode( wp_strip_all_tags( $m[5] ), ENT_QUOTES, 'UTF-8' ) );
			$class_attr = add_classes( $m[3], array( 'cr-letters__host', 'cr-letters__host--ink-' . host_ink( $label ) ) );
			return '<h' . $m[1] . $m[2] . 'class="' . esc_attr( $class_attr ) . '"' . $m[4] . '>' . $m[5] . '</h' . $m[1] . '>';
		},
		$html
	);
	return label_duplicate_titles( $html );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\decorate_post_template', 20, 2 );
}
