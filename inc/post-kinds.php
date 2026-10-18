<?php
/**
 * Post Kinds + video accessibility integration.
 *
 * Renders YouTube videos through Able Player instead of the raw oEmbed iframe,
 * so every video on the site is an accessible, keyboard-operable player with
 * captions and (when a WebVTT file is supplied) an interactive transcript.
 *
 * Three entry points, all sharing the same Able Player builder:
 *   1. Post Kinds watch/video CARD previews — via the plugin's
 *      `pkiw_card_embed_html` short-circuit filter (carries a caption file).
 *   2. Core Embed BLOCKS (wp:embed) in post content — via `render_block`.
 *   3. Bare-URL autoembeds — via `embed_oembed_html`.
 *
 * Everything falls back to the default embed whenever Able Player is inactive
 * or the URL isn't YouTube, so nothing breaks without Able Player.
 *
 * Caption files: a WebVTT attachment whose `_cr_youtube_id` meta holds the
 * video's 11-character ID is attached to every player for that video (embed
 * blocks, bare-URL autoembeds and cards without their own file), so captions and
 * the interactive transcript come from the site's own track rather than
 * YouTube's auto-generated one.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\PostKinds;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build an Able Player embed for a YouTube video ID.
 *
 * @param string $youtube_id 11-character YouTube video ID.
 * @param string $captions   Optional WebVTT caption file (URL or attachment ID).
 * @return string Rendered Able Player markup.
 */
function ableplayer_youtube( string $youtube_id, string $captions = '' ): string {
	$captions_attr = ( '' !== $captions )
		? sprintf( ' captions="%s"', esc_attr( $captions ) )
		: '';

	return do_shortcode(
		sprintf(
			'[ableplayer youtube-id="%s" youtube-nocookie="true"%s]',
			esc_attr( $youtube_id ),
			$captions_attr
		)
	);
}

/**
 * Build an Able Player for an audio attachment.
 *
 * URL-only audio stays with Core because Able Player needs an attachment ID.
 *
 * @param int $attachment_id Audio attachment ID.
 * @return string Rendered Able Player markup, or an empty string.
 */
function ableplayer_audio( int $attachment_id ): string {
	if ( $attachment_id <= 0 || ! shortcode_exists( 'ableplayer' ) || ! wp_attachment_is( 'audio', $attachment_id ) ) {
		return '';
	}

	return do_shortcode( sprintf( '[ableplayer media-id=%d preload=metadata autoplay=false]', $attachment_id ) );
}

/**
 * Find the site's WebVTT caption file for a YouTube video.
 *
 * Looks up a `text/vtt` attachment whose `_cr_youtube_id` meta equals the video
 * ID. Set the meta when importing a caption file (see the remediation tools'
 * import-captions.sh). Memoised per request; one query per distinct video.
 *
 * @param string $youtube_id 11-character YouTube video ID.
 * @return string Attachment ID as a string, or '' when no file is registered.
 */
function captions_for_youtube_id( string $youtube_id ): string {
	static $cache = array();

	if ( '' === $youtube_id ) {
		return '';
	}
	if ( isset( $cache[ $youtube_id ] ) ) {
		return $cache[ $youtube_id ];
	}

	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'text/vtt',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_cr_youtube_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $youtube_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'no_found_rows'  => true,
		)
	);

	$cache[ $youtube_id ] = empty( $ids ) ? '' : (string) $ids[0];

	return $cache[ $youtube_id ];
}

/**
 * Extract an 11-character YouTube video ID from common URL shapes
 * (watch?v=, youtu.be/, embed/, shorts/, live/).
 *
 * @param string $url The URL to parse.
 * @return string The video ID, or '' when the URL isn't a YouTube video.
 */
function youtube_id( string $url ): string {
	$pattern = '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

	if ( preg_match( $pattern, $url, $matches ) ) {
		return $matches[1];
	}

	return '';
}

/**
 * Substitute an Able Player embed for a watch/video card's YouTube URL.
 *
 * When the card supplies a WebVTT caption file (context['captions']), it's
 * passed to Able Player, which renders accessible captions AND an interactive
 * transcript from it — even for a YouTube video.
 *
 * @param string|null $pre     Replacement embed HTML, or null to use oEmbed.
 * @param mixed       $url     The URL being embedded.
 * @param string      $kind    The card kind slug.
 * @param array       $context Extra card data (e.g. 'captions' => VTT URL).
 * @return string|null Able Player markup, or the incoming value to fall back.
 */
function card_embed( $pre, $url, $kind, $context = array() ) {
	if ( null !== $pre ) {
		return $pre;
	}

	if ( ! is_string( $url ) || '' === $url ) {
		return $pre;
	}

	if ( ! in_array( $kind, array( 'watch', 'video' ), true ) ) {
		return $pre;
	}

	if ( ! shortcode_exists( 'ableplayer' ) ) {
		return $pre;
	}

	$youtube_id = youtube_id( $url );

	if ( '' === $youtube_id ) {
		return $pre;
	}

	$captions = ( isset( $context['captions'] ) && is_string( $context['captions'] ) )
		? $context['captions']
		: captions_for_youtube_id( $youtube_id );

	return ableplayer_youtube( $youtube_id, $captions );
}
add_filter( 'pkiw_card_embed_html', __NAMESPACE__ . '\\card_embed', 10, 4 );

/**
 * Whether the request is a REST block-renderer editor preview.
 *
 * @return bool
 */
function is_block_renderer_request(): bool {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return false;
	}
	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never output.
	$route = isset( $_GET['rest_route'] ) ? (string) wp_unslash( $_GET['rest_route'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended -- read-only routing check.
	return str_contains( $uri, '/block-renderer/' ) || str_contains( $route, '/block-renderer/' );
}

/**
 * Remove every autoplay query argument without normalizing the URL.
 *
 * @param string $url Embed URL.
 * @return string URL without autoplay, or the original URL when absent.
 */
function strip_autoplay_query_arg( string $url ): string {
	$fragment_at = strpos( $url, '#' );
	$fragment    = false === $fragment_at ? '' : substr( $url, $fragment_at );
	$before_hash = false === $fragment_at ? $url : substr( $url, 0, $fragment_at );
	$query_at    = strpos( $before_hash, '?' );
	if ( false === $query_at ) {
		return $url;
	}

	$base    = substr( $before_hash, 0, $query_at );
	$query   = substr( $before_hash, $query_at + 1 );
	$parts   = explode( '&', $query );
	$kept    = array();
	$removed = false;
	foreach ( $parts as $part ) {
		$name = explode( '=', $part, 2 )[0];
		if ( 0 === strcasecmp( $name, 'autoplay' ) ) {
			$removed = true;
			continue;
		}
		$kept[] = $part;
	}
	if ( ! $removed ) {
		return $url;
	}

	return $base . ( empty( $kept ) ? '' : '?' . implode( '&', $kept ) ) . $fragment;
}

/**
 * Disable saved autoplay on video, audio and embed blocks for media kinds.
 *
 * Priority 9 matters because Core runs every generic `render_block` callback
 * before any `render_block_{name}` callback. This runs before embed_block()
 * (generic priority 10), before every named-hook callback, and covers Able
 * Player output produced when a player is lifted into another render path.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function strip_media_autoplay( string $html, array $block ): string {
	$block_name = (string) ( $block['blockName'] ?? '' );
	if ( ! in_array( $block_name, array( 'core/video', 'core/audio', 'core/embed' ), true ) ) {
		return $html;
	}
	if ( is_admin() || is_block_renderer_request() ) {
		return $html;
	}

	$post_id = (int) get_the_ID();
	if ( 0 === $post_id ) {
		$post_id = (int) get_queried_object_id();
	}
	if ( 0 === $post_id ) {
		return $html;
	}

	$kinds = apply_filters( 'courtneyr_child_strip_autoplay_kinds', array( 'video', 'audio' ) );
	$kinds = array_values(
		array_unique(
			array_filter(
				array_map(
					static fn( $kind ): string => is_string( $kind ) ? trim( $kind ) : '',
					(array) $kinds
				),
				static fn( string $kind ): bool => '' !== $kind
			)
		)
	);
	if ( empty( $kinds ) || ! has_term( $kinds, 'kind', $post_id ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( 'core/video' === $block_name || 'core/audio' === $block_name ) {
		$tag = 'core/video' === $block_name ? 'VIDEO' : 'AUDIO';
		if ( $tags->next_tag( $tag ) ) {
			$tags->remove_attribute( 'autoplay' );
			$tags->set_attribute( 'controls', true );
			$tags->set_attribute( 'preload', 'metadata' );
			return $tags->get_updated_html();
		}
		return $html;
	}

	$changed = false;
	while ( $tags->next_tag( 'IFRAME' ) ) {
		$src = $tags->get_attribute( 'src' );
		if ( ! is_string( $src ) ) {
			continue;
		}
		$clean = strip_autoplay_query_arg( $src );
		if ( $clean !== $src ) {
			$tags->set_attribute( 'src', $clean );
			$changed = true;
		}
	}
	return $changed ? $tags->get_updated_html() : $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\strip_media_autoplay', 9, 2 );

/**
 * Render core Embed blocks (wp:embed) that point at YouTube through Able Player.
 *
 * Core embeds carry no caption file of their own; the site's registered WebVTT
 * for the video (captions_for_youtube_id) is attached when one exists, otherwise
 * the player falls back to YouTube's own captions.
 *
 * @param string $content The block's rendered HTML.
 * @param array  $block   The parsed block (blockName, attrs, …).
 * @return string Able Player markup for YouTube embeds, else the original HTML.
 */
function embed_block( $content, $block ) {
	if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== 'core/embed' ) {
		return $content;
	}

	if ( ! shortcode_exists( 'ableplayer' ) ) {
		return $content;
	}

	$url = $block['attrs']['url'] ?? '';

	if ( ! is_string( $url ) ) {
		return $content;
	}

	$youtube_id = youtube_id( $url );

	if ( '' === $youtube_id ) {
		return $content;
	}

	return ableplayer_youtube( $youtube_id, captions_for_youtube_id( $youtube_id ) );
}
add_filter( 'render_block', __NAMESPACE__ . '\\embed_block', 10, 2 );

/**
 * Render bare-URL YouTube autoembeds through Able Player.
 *
 * Fires when WordPress builds oEmbed HTML for a URL on the front end (e.g. a
 * YouTube link on its own line in content). Skipped in admin/REST so the block
 * editor keeps its normal, editable embed preview.
 *
 * @param string $html The oEmbed HTML.
 * @param string $url  The embedded URL.
 * @return string Able Player markup for YouTube, else the original HTML.
 */
function oembed_html( $html, $url ) {
	if ( is_admin() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
		return $html;
	}

	if ( ! shortcode_exists( 'ableplayer' ) || ! is_string( $url ) ) {
		return $html;
	}

	$youtube_id = youtube_id( $url );

	if ( '' === $youtube_id ) {
		return $html;
	}

	return ableplayer_youtube( $youtube_id, captions_for_youtube_id( $youtube_id ) );
}
add_filter( 'embed_oembed_html', __NAMESPACE__ . '\\oembed_html', 10, 2 );

/**
 * Drop template-owned heading/image blocks when the single object holds the H1.
 *
 * Kind lanes declare H1 ownership through
 * `courtneyr_child_single_object_holds_h1`. This shared render filter stays
 * kind-agnostic and drops only the template's post header group and featured
 * image after a lane returns exactly true.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function drop_template_header( string $html, array $block ): string {
	$name  = (string) ( $block['blockName'] ?? '' );
	$class = (string) ( $block['attrs']['className'] ?? '' );
	$drop  = ( 'core/group' === $name && str_contains( $class, 'single-post__header' ) )
		|| ( 'core/post-featured-image' === $name && str_contains( $class, 'single-post__featured' ) );
	if ( ! $drop || ! is_singular( 'post' ) ) {
		return $html;
	}

	$post = get_post( get_queried_object_id() );
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}

	return true === apply_filters( 'courtneyr_child_single_object_holds_h1', false, $post ) ? '' : $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\drop_template_header', 10, 2 );

/**
 * Drop repeated featured images for kinds whose object already prints them.
 *
 * Kind lanes opt into the listed-kind gate through
 * `courtneyr_child_featured_repeat_kinds` and into custom repeat detection
 * through `courtneyr_child_featured_repeats`. The theme reads the plugin only
 * through `\PKIW\kind_facts()`, specifically the `featured_in_content` fact.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function suppress_repeated_featured_image( string $html, array $block ): string {
	if ( 'core/post-featured-image' !== ( $block['blockName'] ?? '' ) || ! is_singular( 'post' ) ) {
		return $html;
	}

	$post = get_post();
	if ( ! $post instanceof \WP_Post ) {
		return $html;
	}

	$kinds = apply_filters( 'courtneyr_child_featured_repeat_kinds', array( 'photo', 'chicken', 'repost' ) );
	$kinds = array_values(
		array_unique(
			array_filter(
				array_map(
					static fn( $kind ): string => is_string( $kind ) ? trim( $kind ) : '',
					(array) $kinds
				),
				static fn( string $kind ): bool => '' !== $kind
			)
		)
	);
	if ( empty( $kinds ) || ! has_term( $kinds, 'kind', $post ) ) {
		return $html;
	}

	if ( post_password_required( $post ) ) {
		return '';
	}

	if ( function_exists( '\\PKIW\\kind_facts' ) ) {
		$facts = \PKIW\kind_facts( $post->ID );
		if ( is_array( $facts ) && ! empty( $facts['featured_in_content'] ) ) {
			return '';
		}
	}

	return true === apply_filters( 'courtneyr_child_featured_repeats', false, $post ) ? '' : $html;
}
add_filter( 'render_block', __NAMESPACE__ . '\\suppress_repeated_featured_image', 10, 2 );

/**
 * Drop the featured image block when the read object prints that picture.
 *
 * @param string               $html  Rendered block HTML.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function suppress_featured_image_on_read_single( string $html, array $block ): string {
	if ( 'core/post-featured-image' !== ( $block['blockName'] ?? '' ) || ! is_singular( 'post' ) ) {
		return $html;
	}
	$post = get_post();
	if ( ! $post instanceof \WP_Post || ! has_term( 'read', 'kind', $post ) ) {
		return $html;
	}
	return '';
}
add_filter( 'render_block', __NAMESPACE__ . '\\suppress_featured_image_on_read_single', 10, 2 );
