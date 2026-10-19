<?php
/**
 * Podcast single adapter and lane-owned archive decorations.
 *
 * The lineup cover fallback mark and dial live here because this lane owns
 * no other new PHP adapter file.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SinglePodcast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attach podcast paint to the listen-type navigation block.
 *
 * The shared handle matches the surface enqueue, so WordPress loads the sheet
 * once when a podcast page also renders the block.
 *
 * @return void
 */
function register_type_nav_style(): void {
	$file = COURTNEYR_CHILD_DIR . '/assets/css/cr-podcast.css';
	if ( ! is_readable( $file ) ) {
		return;
	}

	wp_enqueue_block_style(
		'post-kinds-indieweb/listen-types',
		array(
			'handle' => 'courtneyr-podcast',
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-podcast.css',
			'path'   => $file,
			'ver'    => COURTNEYR_CHILD_VERSION,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_type_nav_style' );

/**
 * Read public podcast facts for a post.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	// A cheap term check first: the adapter runs on every single, and only listens can be podcasts.
	if ( ! function_exists( '\\PKIW\\kind_facts' ) || ! has_term( 'listen', 'kind', $post_id ) ) {
		return array();
	}

	$facts = \PKIW\kind_facts( $post_id );
	if ( ! is_array( $facts ) || empty( $facts['is_podcast'] ) ) {
		return array();
	}

	return $facts;
}

/**
 * Whether the request is a podcast post single.
 *
 * @return bool
 */
function is_podcast_single(): bool {
	return is_singular( 'post' ) && array() !== facts( (int) get_queried_object_id() );
}

/**
 * Add the podcast single body class once.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_podcast_single() && ! in_array( 'cr-single--podcast', $classes, true ) ) {
		$classes[] = 'cr-single--podcast';
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Prepend the podcast record to the main singular content once.
 *
 * @param string $content Post content.
 * @return string
 */
function prepend_record( string $content ): string {
	if (
		! is_singular( 'post' )
		|| ! in_the_loop()
		|| ! is_main_query()
		|| is_feed()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
	) {
		return $content;
	}

	$post_id = (int) get_the_ID();
	if ( 0 >= $post_id || (int) get_queried_object_id() !== $post_id ) {
		return $content;
	}

	$post       = get_post( $post_id );
	$post_facts = facts( $post_id );
	if ( ! $post instanceof \WP_Post || array() === $post_facts ) {
		return $content;
	}

	/**
	 * Post IDs whose record has printed during this request.
	 *
	 * @var array<int, bool> $printed
	 */
	static $printed = array();
	if ( isset( $printed[ $post_id ] ) ) {
		return $content;
	}
	$printed[ $post_id ] = true;

	return record_html( $post, $post_facts ) . $content;
}
add_filter( 'the_content', __NAMESPACE__ . '\\prepend_record', 8 );

/**
 * Build the podcast record, tuner, media and sources regions.
 *
 * @param \WP_Post             $post       Podcast post.
 * @param array<string, mixed> $post_facts Public podcast facts.
 * @return string
 */
function record_html( \WP_Post $post, array $post_facts ): string {
	$rows = '';
	$show = trim( (string) ( $post_facts['show'] ?? '' ) );
	if ( '' !== $show ) {
		$rows .= '<div class="cr-podcast-record__row"><dt>' . esc_html__( 'Show', 'courtneyr-child' ) . '</dt><dd>' . esc_html( $show ) . '</dd></div>';
	}

	$episode = trim( (string) ( $post_facts['episode'] ?? '' ) );
	if ( ! empty( $post_facts['episode_differs'] ) && '' !== $episode ) {
		$rows .= '<div class="cr-podcast-record__row"><dt>' . esc_html__( 'Episode', 'courtneyr-child' ) . '</dt><dd>' . esc_html( $episode ) . '</dd></div>';
	}

	if ( ! empty( $post_facts['listened_stored'] ) ) {
		$date = '<time datetime="' . esc_attr( (string) ( $post_facts['listened_value'] ?? '' ) ) . '">' . esc_html( (string) ( $post_facts['day_display'] ?? '' ) ) . '</time>';
		$time = (string) ( $post_facts['time_display'] ?? '' );
		if ( ! empty( $post_facts['listened_has_time'] ) && '' !== $time ) {
			$date = sprintf(
				/* translators: 1: displayed listen day, 2: displayed listen time. */
				esc_html__( '%1$s at %2$s', 'courtneyr-child' ),
				$date,
				esc_html( $time )
			);
		}
		$rows .= '<div class="cr-podcast-record__row"><dt>' . esc_html__( 'Tuned in', 'courtneyr-child' ) . '</dt><dd>' . $date . '</dd></div>';
	}

	$rating       = (float) ( $post_facts['rating'] ?? 0 );
	$rating_label = trim( (string) ( $post_facts['rating_label'] ?? '' ) );
	if ( $rating > 0 && '' !== $rating_label ) {
		$rows .= '<div class="cr-podcast-record__row"><dt class="cr-podcast-sr">' . esc_html__( 'Rating', 'courtneyr-child' ) . '</dt><dd>' . esc_html( $rating_label ) . '</dd></div>';
	}

	$position = 4;
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		$position = \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( (string) $post->ID, 'podcast' ), 3, 7 ) + 1;
	}

	$media       = '';
	$embed_html  = (string) ( $post_facts['embed_html'] ?? '' );
	$picture     = is_array( $post_facts['picture'] ?? null ) ? $post_facts['picture'] : array();
	$picture_url = trim( (string) ( $picture['url'] ?? '' ) );
	if ( '' !== $embed_html ) {
		$media = '<div class="cr-podcast-embed">' . $embed_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The plugin supplies provider embed markup in public facts.
	} elseif ( '' !== $picture_url ) {
		$attachment_id = (int) ( $picture['attachment_id'] ?? 0 );
		if ( $attachment_id > 0 ) {
			$image = wp_get_attachment_image(
				$attachment_id,
				'large',
				false,
				array(
					'class'    => 'u-photo',
					'alt'      => (string) ( $post_facts['cover_alt'] ?? '' ),
					'decoding' => 'async',
				)
			);
		} else {
			$image = '<img class="u-photo" src="' . esc_url( $picture_url ) . '" alt="' . esc_attr( (string) ( $post_facts['cover_alt'] ?? '' ) ) . '" decoding="async" loading="lazy">';
		}
		$media = '<figure class="cr-podcast-picture" data-cr-cover-fallback="true">' . $image . '</figure>';
	}

	$url = trim( (string) ( $post_facts['url'] ?? '' ) );
	if ( '' !== $url ) {
		$label  = function_exists( '\\Courtneyr\\Child\\StreamMedia\\provider_label' )
			? \Courtneyr\Child\StreamMedia\provider_label( $url )
			: __( 'Listen', 'courtneyr-child' );
		$media .= '<div class="wp-block-group cr-listen-sources cr-podcast-sources"><p class="cr-listen-sources__label">' . esc_html__( 'Listen / find it', 'courtneyr-child' ) . '</p><div class="wp-block-buttons cr-listen-sources__links"><div class="wp-block-button is-style-cr-button-outline cr-listen-sources__link"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '<span class="cr-podcast-sr"> ' . esc_html__( '(opens in a new tab)', 'courtneyr-child' ) . '</span></a></div></div></div>';
	}

	return '<section class="cr-podcast-single">'
		. '<div class="cr-podcast-single__record"><p class="cr-podcast-single__kind">' . esc_html( (string) ( $post_facts['kind_label'] ?? '' ) ) . '</p><dl class="cr-podcast-record">' . $rows . '</dl></div>'
		. '<span class="cr-podcast-tuner cr-podcast-tuner--pos-' . esc_attr( (string) $position ) . '" aria-hidden="true"></span>'
		. '<div class="cr-podcast-single__media">' . $media . '</div>'
		. '</section>';
}

/**
 * Drop the featured image when the podcast record prints the same image.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function drop_repeated_featured_image( string $html, array $block ): string {
	if ( 'core/post-featured-image' !== ( $block['blockName'] ?? '' ) || ! is_podcast_single() ) {
		return $html;
	}

	$post_facts = facts( (int) get_queried_object_id() );
	$picture    = is_array( $post_facts['picture'] ?? null ) ? $post_facts['picture'] : array();

	return ! empty( $picture['suppress_featured'] ) ? '' : $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\drop_repeated_featured_image', 10, 2 );

/**
 * Print the facts name as the H1 of an untitled podcast single.
 *
 * Core's Post Title renders nothing for an empty title, and 'listen' is not in
 * the shared untitled-single kind list, so an untitled podcast would have no H1.
 * The name is the plugin's untitled name, so it carries no p-name and no link.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function untitled_h1( string $html, array $block ): string {
	if ( '' !== trim( $html ) || 1 !== (int) ( $block['attrs']['level'] ?? 0 ) || ! is_podcast_single() ) {
		return $html;
	}

	$post_id = (int) get_queried_object_id();
	if ( '' !== trim( (string) get_post_field( 'post_title', $post_id, 'raw' ) ) ) {
		return $html;
	}

	$post_facts = facts( $post_id );
	$name       = is_string( $post_facts['name'] ?? null ) ? trim( $post_facts['name'] ) : '';
	if ( '' === $name ) {
		return $html;
	}

	$class_name = is_array( $block['attrs'] ?? null ) ? trim( (string) ( $block['attrs']['className'] ?? '' ) ) : '';

	return sprintf(
		'<h1 class="wp-block-post-title %1$s has-accent-font-family">%2$s</h1>',
		esc_attr( $class_name ),
		esc_html( $name )
	);
}
add_filter( 'render_block_core/post-title', __NAMESPACE__ . '\\untitled_h1', 25, 2 );

/**
 * Drop the single kind chip so the podcast label prints once.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function drop_kind_chip( string $html, array $block ): string {
	if ( 'core/post-terms' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}

	// The template's className marks the kind chip; the term attribute is not read.
	$class_name = is_array( $block['attrs'] ?? null ) ? (string) ( $block['attrs']['className'] ?? '' ) : '';
	if ( ! str_contains( $class_name, 'single-post__kind' ) ) {
		return $html;
	}

	return is_podcast_single() ? '' : $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\drop_kind_chip', 10, 2 );

/**
 * Drop the authored listen card's kind label on a podcast single.
 *
 * Card-authored podcasts print the stored card beside the record, and both
 * would say 'Listen'. The record's label stays, so the page shows it once.
 *
 * @param string               $html  Rendered card HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function strip_card_kind_label( string $html, array $block ): string {
	if ( 'post-kinds-indieweb/listen-card' !== ( $block['blockName'] ?? '' ) || ! str_contains( $html, 'pk-kindlabel' ) || ! is_podcast_single() ) {
		return $html;
	}

	$stripped = preg_replace( '#<(span|p)\s+class="pk-kindlabel"[^>]*>.*?</\1>#s', '', $html );

	return is_string( $stripped ) ? $stripped : $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\strip_card_kind_label', 10, 2 );

/**
 * Mark every lineup cover for the theme's failed-image fallback script.
 *
 * Regex is limited to opening div tags carrying the plugin's data attribute,
 * which keeps the file loadable under the standalone PHP test stubs.
 *
 * @param string $html Rendered lineup HTML.
 * @return string
 */
function mark_lineup( string $html ): string {
	if ( ! str_contains( $html, 'data-pkiw-cover' ) ) {
		return $html;
	}

	$marked = preg_replace_callback(
		'/<div\b(?=[^>]*\bdata-pkiw-cover(?:\s|=|>))[^>]*>/i',
		static function ( array $tag ): string {
			if ( str_contains( $tag[0], 'data-cr-cover-fallback' ) ) {
				return $tag[0];
			}

			return substr( $tag[0], 0, -1 ) . ' data-cr-cover-fallback="true">';
		},
		$html
	);

	return is_string( $marked ) ? $marked : $html;
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\mark_lineup' );
add_filter( 'render_block_post-kinds-indieweb/podcast-episode', __NAMESPACE__ . '\\mark_lineup' );

/**
 * Add the radio dial as the lineup chassis's first child once.
 *
 * @param string $html Rendered Post Template HTML.
 * @return string
 */
function add_dial( string $html ): string {
	if ( ! str_contains( $html, 'pkiw-lineup__station' ) || str_contains( $html, 'cr-podcast-dial' ) ) {
		return $html;
	}
	if ( 1 !== preg_match( '/^\s*<div\b[^>]*>/i', $html, $opening, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	if ( 1 !== preg_match( '/\bclass=(?:"([^"]*)"|\'([^\']*)\')/i', $opening[0][0], $class_match ) ) {
		return $html;
	}

	$classes = preg_split( '/\s+/', trim( (string) ( $class_match[1] ?? $class_match[2] ?? '' ) ) );
	if ( ! is_array( $classes ) || ! in_array( 'pkiw-lineup', $classes, true ) ) {
		return $html;
	}

	$at   = (int) $opening[0][1] + strlen( $opening[0][0] );
	$dial = '<span class="cr-podcast-dial" aria-hidden="true"><span class="cr-podcast-dial__grille"></span><span class="cr-podcast-dial__knob"></span><span class="cr-podcast-dial__window"><span class="cr-podcast-dial__needle"></span></span><span class="cr-podcast-dial__knob"></span><span class="cr-podcast-dial__grille"></span></span>';

	return substr( $html, 0, $at ) . $dial . substr( $html, $at );
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\add_dial' );
