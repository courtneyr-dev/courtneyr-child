<?php
/**
 * Archive family (0.7.46): one identity block, one stylesheet, and the
 * template family that gives every category, format and kind archive the
 * surface it belongs to. Blog-family archives (categories, dates, tags,
 * authors) keep the blog grid; Stream-family archives (kinds and the
 * site's Stream formats) render the plugin's stream cards in the same
 * collage the Stream page and the homepage lane use.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Archives;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Kinds without a sprite of their own borrow the nearest format glyph/colour. */
const KIND_TYPE_ALIASES = array(
	'article'  => 'blog',
	'note'     => 'status',
	'photo'    => 'image',
	'read'     => 'book',
	'listen'   => 'audio',
	'jam'      => 'audio',
	'watch'    => 'video',
	'play'     => 'video',
	'checkin'  => 'event',
	'rsvp'     => 'event',
	'favorite' => 'like',
);

/**
 * Stream formats from the site policy (cr-content-surfaces); falls back to
 * every format but standard/chat when the MU plugin is absent.
 *
 * @return string[]
 */
function stream_formats(): array {
	if ( defined( 'CR_STREAM_FORMATS' ) ) {
		return (array) \CR_STREAM_FORMATS;
	}
	return array( 'status', 'image', 'gallery', 'aside', 'link', 'quote', 'video', 'audio' );
}

/**
 * Sprite types the theme ships (assets/svg/icons.svg#post-icon-*).
 *
 * @return string[]
 */
function sprite_types(): array {
	$solid   = defined( '\Courtneyr\Child\Interactivity\STREAM_AVATAR_SOLID' ) ? \Courtneyr\Child\Interactivity\STREAM_AVATAR_SOLID : array();
	$outline = defined( '\Courtneyr\Child\Interactivity\STREAM_AVATAR_OUTLINE' ) ? \Courtneyr\Child\Interactivity\STREAM_AVATAR_OUTLINE : array();
	return array_merge( $solid, $outline );
}

/**
 * Sprite glyph markup for a type.
 */
function sprite_glyph( string $type ): string {
	return sprintf(
		'<svg viewBox="0 0 24 24" focusable="false"><use href="%1$s/assets/svg/icons.svg#post-icon-%2$s"></use></svg>',
		esc_url( get_stylesheet_directory_uri() ),
		esc_attr( $type )
	);
}

/**
 * Resolve the identity of the archive being rendered (or previewed).
 *
 * A kind archive has no kicker: its h1 names it, and its round badge sits on
 * the title's upper-left corner.
 *
 * @return array{family:string,kicker:string,type:string,glyph:string,accent:string,kind:bool}
 */
function resolve_identity(): array {
	$sprites = sprite_types();
	$object  = get_queried_object();
	$family  = 'blog';
	$kicker  = __( 'Field notes', 'courtneyr-child' );
	$type    = 'blog';
	$glyph   = '';
	$kind    = false;

	if ( $object instanceof \WP_Term ) {
		if ( 'kind' === $object->taxonomy ) {
			$family = 'stream';
			$kicker = '';
			$kind   = true;
			$slug   = $object->slug;
			$type   = KIND_TYPE_ALIASES[ $slug ] ?? ( in_array( $slug, $sprites, true ) ? $slug : 'blog' );
			if ( function_exists( '\PKIW\get_kind_icon_svg' ) ) {
				$glyph = \PKIW\get_kind_icon_svg( $slug );
			}
		} elseif ( 'post_format' === $object->taxonomy ) {
			$slug   = str_replace( 'post-format-', '', $object->slug );
			$family = in_array( $slug, stream_formats(), true ) ? 'stream' : 'blog';
			$kicker = 'stream' === $family ? __( 'Stream · Format', 'courtneyr-child' ) : __( 'Blog · Format', 'courtneyr-child' );
			$type   = in_array( $slug, $sprites, true ) ? $slug : 'blog';
		} elseif ( 'category' === $object->taxonomy ) {
			$kicker = __( 'Blog · Topic', 'courtneyr-child' );
			$type   = in_array( $object->slug, $sprites, true ) ? $object->slug : 'blog';
		} elseif ( 'post_tag' === $object->taxonomy ) {
			$kicker = __( 'Blog · Tag', 'courtneyr-child' );
		} else {
			$kicker = __( 'Blog · Archive', 'courtneyr-child' );
		}
	} elseif ( $object instanceof \WP_Post_Type ) {
		if ( 'web-story' === $object->name ) {
			$family = 'stories';
			$kicker = __( 'Stories', 'courtneyr-child' );
			$type   = 'gallery';
		} else {
			$kicker = $object->labels->name;
		}
	} elseif ( is_date() ) {
		$kicker = __( 'Blog · Date', 'courtneyr-child' );
	} elseif ( is_author() ) {
		$kicker = __( 'Blog · Author', 'courtneyr-child' );
	} elseif ( is_search() ) {
		$kicker = __( 'Search', 'courtneyr-child' );
	} elseif ( isset( $_GET['cr_template'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- editor preview hint, read-only.
		// Site-editor preview: no queried object, so show the family the template serves.
		$template = sanitize_key( wp_unslash( (string) $_GET['cr_template'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( str_starts_with( $template, 'taxonomy-kind' ) ) {
			$family = 'stream';
			$kicker = '';
			$kind   = true;
			$type   = 'status';
		} elseif ( str_starts_with( $template, 'taxonomy-post_format' ) ) {
			$family = 'stream';
			$kicker = __( 'Stream · Format', 'courtneyr-child' );
			$type   = 'quote';
		} elseif ( str_starts_with( $template, 'category' ) ) {
			$kicker = __( 'Blog · Topic', 'courtneyr-child' );
		} elseif ( str_starts_with( $template, 'archive-web-story' ) ) {
			$family = 'stories';
			$kicker = __( 'Stories', 'courtneyr-child' );
			$type   = 'gallery';
		}
	}

	if ( '' === $glyph ) {
		$glyph = sprite_glyph( $type );
	}
	return array(
		'family' => $family,
		'kicker' => $kicker,
		'type'   => $type,
		'glyph'  => $glyph,
		'accent' => sprintf( 'var(--cr-type-%s, var(--cr-cerulean))', $type ),
		'kind'   => $kind,
	);
}

/** Tile papers in cr-archives.css; strides are coprime so neighbours never repeat. */
const CUTOUT_PAPERS = 11;
const CUTOUT_VOICES = 4;
const CUTOUT_TILTS  = 4;
const CUTOUT_EDGES  = 5;

/**
 * Split plain text into grapheme clusters (not bytes, not code points).
 *
 * @param string $text Plain text.
 * @return string[]
 */
function graphemes( string $text ): array {
	if ( function_exists( 'grapheme_str_split' ) ) {
		$parts = grapheme_str_split( $text );
		if ( is_array( $parts ) ) {
			return $parts;
		}
	}
	// Plain text, not HTML: \X is PCRE's extended grapheme cluster.
	return preg_match_all( '/\X/u', $text, $m ) ? $m[0] : array();
}

/**
 * The visual per-letter title: one torn-paper tile per grapheme, words kept
 * together so lines break only at spaces. Style indexes come from the title's
 * crc32 and the tile position, so the same title always prints the same way.
 * Decorative: the caller pairs it with the unchanged title as text.
 *
 * @param string $title Exact title text.
 * @return array{html:string,longest:int}
 */
function cutout_tiles( string $title ): array {
	$seed    = crc32( $title );
	$words   = array();
	$word    = '';
	$letters = 0;
	$longest = 0;
	$i       = 0;
	foreach ( graphemes( $title ) as $g ) {
		if ( preg_match( '/^[\s\x{00A0}]+$/u', $g ) ) {
			if ( '' !== $word ) {
				$words[] = $word;
				$longest = max( $longest, $letters );
			}
			$word    = '';
			$letters = 0;
			continue;
		}
		$voice = ( ( $seed >> 4 ) + $i * 3 ) % CUTOUT_VOICES;
		// Rock Salt draws i, j, l and thin marks as slivers; those take the slab voice.
		if ( 0 === $voice && preg_match( '/^[ijlI1|!.,:;\'’‘`]$/u', $g ) ) {
			$voice = 1;
		}
		$classes = sprintf(
			'cr-cutout__tile is-p%d is-v%d is-r%d is-e%d',
			( $seed + $i * 5 ) % CUTOUT_PAPERS,
			$voice,
			( ( $seed >> 8 ) + $i + intdiv( $i, 3 ) ) % CUTOUT_TILTS,
			( ( $seed >> 12 ) + $i * 2 ) % CUTOUT_EDGES
		);
		if ( preg_match( '/^[\p{P}\p{S}]/u', $g ) ) {
			$classes .= ' is-punct';
		}
		$word .= '<span class="' . esc_attr( $classes ) . '"><span class="cr-cutout__paper"></span>' . esc_html( $g ) . '</span>';
		++$letters;
		++$i;
	}
	if ( '' !== $word ) {
		$words[] = $word;
		$longest = max( $longest, $letters );
	}
	$html = implode(
		'<span class="cr-cutout__gap"> </span>',
		array_map( static fn( $w ) => '<span class="cr-cutout__word">' . $w . '</span>', $words )
	);
	return array(
		'html'    => $html,
		'longest' => max( 1, $longest ),
	);
}

/**
 * Rebuild a rendered heading as a cut-paper title. The heading keeps its tag
 * and every attribute; its text becomes one screen-reader-only copy of the
 * exact title plus an aria-hidden tile layer. Anything that is not a heading
 * with text comes back unchanged.
 *
 * @param string $html Rendered heading markup.
 */
function cutout_heading( string $html ): string {
	if ( ! class_exists( '\WP_HTML_Processor' ) ) {
		return $html;
	}
	$p = \WP_HTML_Processor::create_fragment( $html );
	if ( ! $p || ! $p->next_tag() || ! in_array( $p->get_tag(), array( 'H1', 'H2' ), true ) ) {
		return $html;
	}
	$tag   = strtolower( (string) $p->get_tag() );
	$attrs = array();
	foreach ( (array) $p->get_attribute_names_with_prefix( '' ) as $name ) {
		$attrs[ $name ] = $p->get_attribute( $name );
	}
	$text = '';
	while ( $p->next_token() ) {
		if ( '#tag' === $p->get_token_type() && strtolower( (string) $p->get_tag() ) === $tag && $p->is_tag_closer() ) {
			break;
		}
		if ( '#text' === $p->get_token_type() ) {
			$text .= $p->get_modifiable_text();
		}
	}
	$title = trim( $text );
	if ( '' === $title || null !== $p->get_last_error() ) {
		return $html;
	}
	$tiles = cutout_tiles( $title );

	$attrs['class'] = trim( ( is_string( $attrs['class'] ?? null ) ? $attrs['class'] : '' ) . ' cr-cutout' );
	$attrs['style'] = ( is_string( $attrs['style'] ?? null ) && '' !== trim( $attrs['style'] ) ? rtrim( trim( $attrs['style'] ), ';' ) . ';' : '' ) . '--cr-cutout-longest:' . $tiles['longest'];
	$open           = '';
	foreach ( $attrs as $name => $value ) {
		$open .= true === $value ? ' ' . esc_attr( $name ) : sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
	}
	return sprintf(
		'<%1$s%2$s><span class="cr-cutout__text">%3$s</span><span class="cr-cutout__tiles" aria-hidden="true">%4$s</span></%1$s>',
		$tag,
		$open,
		esc_html( $title ),
		$tiles['html'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html'd graphemes and fixed class names.
	);
}

/**
 * Archive titles print as cut-paper titles: the query-title block that the
 * archive-family templates mark `cr-archive__title` (categories, tags, dates,
 * formats, kinds, Stories, search), the posts page's `cr-blog__title`
 * masthead, the Stream page's `cr-stream__title` masthead and the front
 * page's `cr-fieldnotes__title` heading. The last two live in page content or
 * a pattern and are matched by class, so content stays untouched. Nothing
 * else on the front page matches. Front end only.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 */
function cutout_archive_title( string $content, array $block ): string {
	if ( is_admin() || wp_is_serving_rest_request() ) {
		return $content;
	}
	$classes    = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
	$is_heading = 'core/heading' === $block['blockName'];
	if ( is_front_page() ) {
		$match = $is_heading && in_array( 'cr-fieldnotes__title', $classes, true );
	} else {
		$match = ( 'core/query-title' === $block['blockName'] && in_array( 'cr-archive__title', $classes, true ) )
			|| ( $is_heading && in_array( 'cr-blog__title', $classes, true ) && is_home() )
			|| ( $is_heading && in_array( 'cr-stream__title', $classes, true ) && is_page( 'stream' ) );
	}
	if ( ! $match ) {
		return $content;
	}
	return cutout_heading( $content );
}
add_filter( 'render_block_core/query-title', __NAMESPACE__ . '\\cutout_archive_title', 10, 2 );
add_filter( 'render_block_core/heading', __NAMESPACE__ . '\\cutout_archive_title', 10, 2 );

/**
 * Register the identity block.
 */
function register_blocks(): void {
	register_block_type( COURTNEYR_CHILD_DIR . '/blocks/archive-identity' );
}
add_action( 'init', __NAMESPACE__ . '\\register_blocks' );

/**
 * Archive-family stylesheet: front end on archives, search, the posts page
 * (never when it is also the front page) and the Stream page; always in the
 * editor canvas (enqueue_block_assets runs there).
 */
function enqueue_styles(): void {
	$posts_page = is_home() && ! is_front_page();
	if ( ! is_admin() && ! is_archive() && ! is_search() && ! $posts_page && ! is_page( 'stream' ) ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-archives',
		COURTNEYR_CHILD_URI . '/assets/css/cr-archives.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Cut-paper title stylesheet: everywhere cutout_archive_title() can match
 * (archives, search, posts page, Stream page, front page) and the editor.
 */
function enqueue_cutout_styles(): void {
	if ( ! is_admin() && ! is_archive() && ! is_search() && ! is_home() && ! is_front_page() && ! is_page( 'stream' ) ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-cutout',
		COURTNEYR_CHILD_URI . '/assets/css/cr-cutout.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_cutout_styles' );

/**
 * The Stories archive is theme-rendered (poster grid, playback on the story
 * URL), so the plugin's standalone amp-story-player assets are dead weight
 * there — and a third-party host (cdn.ampproject.org) the page never uses.
 */
function dequeue_story_player_on_archive(): void {
	if ( ! is_post_type_archive( 'web-story' ) ) {
		return;
	}
	wp_dequeue_script( 'standalone-amp-story-player' );
	wp_dequeue_style( 'standalone-amp-story-player' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\dequeue_story_player_on_archive', 100 );

/**
 * The plugin enqueues the player while rendering the archive's excerpt/content
 * filters (after wp_enqueue_scripts), so also drop it at print time and remove
 * its dns-prefetch hint.
 */
function drop_story_player_at_print(): void {
	if ( is_post_type_archive( 'web-story' ) ) {
		wp_dequeue_script( 'standalone-amp-story-player' );
		wp_dequeue_style( 'standalone-amp-story-player' );
	}
}
add_action( 'wp_print_scripts', __NAMESPACE__ . '\\drop_story_player_at_print', 100 );
add_action( 'wp_print_styles', __NAMESPACE__ . '\\drop_story_player_at_print', 100 );
add_action( 'wp_print_footer_scripts', __NAMESPACE__ . '\\drop_story_player_at_print', 1 );

/**
 * @param string[] $urls Resource hint URLs.
 * @param string   $relation_type Hint type.
 * @return string[]
 */
function drop_story_player_hints( array $urls, string $relation_type ): array {
	if ( 'dns-prefetch' === $relation_type && is_post_type_archive( 'web-story' ) ) {
		$urls = array_values( array_filter( $urls, static fn( $u ) => ! str_contains( is_array( $u ) ? (string) ( $u['href'] ?? '' ) : (string) $u, 'ampproject.org' ) ) );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', __NAMESPACE__ . '\\drop_story_player_hints', 20, 2 );
