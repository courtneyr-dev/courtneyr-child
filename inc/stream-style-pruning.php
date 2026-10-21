<?php
/**
 * Stream surfaces load only the kind sheets their cards use.
 *
 * Each kind module enqueues its artifact sheet wherever a card of that kind
 * could appear, and the front page and /stream/ count as such surfaces, so
 * both pages loaded every kind's stylesheet (30 render-blocking files, about
 * 230 KB compressed, on 2026-10-19) while rendering cards of five kinds.
 * PageSpeed put mobile LCP at 5.7 s, 1.3 s of it render delay.
 *
 * In a block theme the template renders before wp_head, so by the time
 * styles are printed every card on the page has already been rendered. This
 * file records the classes of every rendered block, then removes a kind
 * sheet when none of its distinctive classes were rendered. A class is
 * distinctive when the shared sheets (components.css, cr-post-kinds.css)
 * don't use it and at most two kind sheets do. A sheet without distinctive
 * classes always stays. Nothing happens on a request that rendered no
 * template part (embeds, feeds, REST), or outside the Stream surfaces.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamStylePruning;

use function Courtneyr\Child\HomeSections\is_stream_surface;

/**
 * Classes seen in rendered block markup during this request.
 *
 * @param string|null $html Block output to record, or null to read the set.
 * @return array<string, true>
 */
function rendered_classes( ?string $html = null ): array {
	static $seen = array();
	if ( null !== $html && '' !== $html && preg_match_all( '/\bclass\s*=\s*(["\'])(.*?)\1/s', $html, $m ) ) {
		foreach ( $m[2] as $attr ) {
			foreach ( preg_split( '/\s+/', $attr, -1, PREG_SPLIT_NO_EMPTY ) as $class ) {
				$seen[ $class ] = true;
			}
		}
	}
	return $seen;
}

/**
 * Record each rendered block's classes on the front end.
 *
 * @param string $block_content Rendered block markup.
 * @return string Unchanged markup.
 */
function record( $block_content ) {
	if ( is_string( $block_content ) && ! is_admin() ) {
		rendered_classes( $block_content );
	}
	return $block_content;
}
add_filter( 'render_block', __NAMESPACE__ . '\\record', 9999 );

/**
 * Class tokens used in a stylesheet's selectors.
 *
 * @param string $file Absolute path of the stylesheet.
 * @return array<string, true>
 */
function sheet_classes( string $file ): array {
	$css = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$out = array();
	if ( preg_match_all( '/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', (string) preg_replace( '/\{[^{}]*\}/', '{}', $css ), $m ) ) {
		foreach ( $m[1] as $class ) {
			$out[ $class ] = true;
		}
	}
	return $out;
}

/**
 * True for classes every kind's markup carries: microformats (h-, p-, u-,
 * dt-, e-), core block and alignment classes, and state classes. They can't
 * tell one kind's card from another's.
 *
 * @param string $name Class name.
 * @return bool
 */
function is_generic( string $name ): bool {
	return 1 === preg_match( '/^(?:(?:h|p|u|dt|e)-|wp-|align|has-|is-|screen-reader)/', $name );
}

/**
 * Distinctive classes per prunable sheet handle, cached per theme version.
 *
 * @param array<string, string> $sheets Handle => absolute path.
 * @return array<string, array<int, string>>
 */
function distinctive_classes( array $sheets ): array {
	$key    = 'stream-style-pruning-' . COURTNEYR_CHILD_VERSION . '-' . md5( implode( ',', array_keys( $sheets ) ) );
	$cached = wp_cache_get( $key, 'courtneyr' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$shared = sheet_classes( COURTNEYR_CHILD_DIR . '/assets/css/components.css' )
		+ sheet_classes( COURTNEYR_CHILD_DIR . '/assets/css/cr-post-kinds.css' );
	$per    = array();
	$count  = array();
	foreach ( $sheets as $handle => $path ) {
		$per[ $handle ] = sheet_classes( $path );
		foreach ( array_keys( $per[ $handle ] ) as $class ) {
			$count[ $class ] = ( $count[ $class ] ?? 0 ) + 1;
		}
	}
	$out = array();
	foreach ( $per as $handle => $classes ) {
		$out[ $handle ] = array_values(
			array_filter(
				array_map( 'strval', array_keys( $classes ) ),
				static fn( string $c ): bool => ! isset( $shared[ $c ] ) && ( $count[ $c ] ?? 0 ) <= 2 && ! is_generic( $c )
			)
		);
	}
	wp_cache_set( $key, $out, 'courtneyr', DAY_IN_SECONDS );
	return $out;
}

/**
 * Theme kind sheets eligible for pruning: theme files under assets/css whose
 * name starts with cr-, minus the shared and page-level sheets.
 *
 * @return array<string, string> Handle => absolute path.
 */
function prunable_sheets(): array {
	$keep  = array( 'cr-post-kinds.css', 'cr-home-sections.css', 'cr-nav.css', 'cr-print.css', 'cr-paper.css', 'cr-ticket.css', 'cr-sticker.css', 'cr-calendar.css' );
	$base  = COURTNEYR_CHILD_URI . '/assets/css/';
	$out   = array();
	$queue = wp_styles()->queue;
	foreach ( $queue as $handle ) {
		$style = wp_styles()->registered[ $handle ] ?? null;
		if ( ! $style || ! is_string( $style->src ) || ! str_starts_with( $style->src, $base ) ) {
			continue;
		}
		$file = basename( (string) wp_parse_url( $style->src, PHP_URL_PATH ) );
		if ( ! str_starts_with( $file, 'cr-' ) || in_array( $file, $keep, true ) ) {
			continue;
		}
		$out[ $handle ] = COURTNEYR_CHILD_DIR . '/assets/css/' . $file;
	}
	return $out;
}

/**
 * Dequeue the kind sheets no rendered card uses.
 *
 * @return void
 */
function prune(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_stream_surface() ) {
		return;
	}
	$seen = rendered_classes();
	if ( ! isset( $seen['wp-block-template-part'] ) ) {
		return;
	}
	foreach ( get_body_class() as $class ) {
		$seen[ $class ] = true;
	}
	$sheets = prunable_sheets();
	foreach ( distinctive_classes( $sheets ) as $handle => $classes ) {
		$used = array() === $classes;
		foreach ( $classes as $class ) {
			if ( isset( $seen[ $class ] ) ) {
				$used = true;
				break;
			}
		}
		if ( $used ) {
			kept_kind_sheets( $handle );
		} else {
			wp_dequeue_style( $handle );
		}
	}
}

/**
 * Kind sheet handles that survived pruning on this request.
 *
 * @param string|null $handle Handle to add, or null to read the list.
 * @return array<int, string>
 */
function kept_kind_sheets( ?string $handle = null ): array {
	static $kept = array();
	if ( null !== $handle ) {
		$kept[] = $handle;
	}
	return $kept;
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\prune', 9999 );

/**
 * Front page: load the card, home-section and kind sheets without blocking first paint.
 *
 * On the front page the card sheet (cr-post-kinds.css), the home sections
 * sheet and the kind sheets that survive pruning paint only what sits below
 * the hero and the newsletter block. With each blocked outright, the first
 * viewport is pixel-identical at 412 and 1440 px (2026-10-19; the same test on
 * /stream/ changes 20-29% of it, so the check is real), yet as the last
 * render-blocking files they held first paint and LCP until 3.6-4.3 s on a
 * throttled phone. The link keeps its place in the document and swaps from
 * print to all on load, so the cascade is unchanged; <noscript> keeps it for
 * visitors without JavaScript. /stream/ keeps it render-blocking because cards
 * are its first viewport.
 *
 * @param string $tag    The link tag.
 * @param string $handle Style handle.
 * @return string
 */
function defer_card_sheet_on_front_page( $tag, $handle ) {
	$deferrable = array_merge( array( 'courtneyr-post-kinds', 'courtneyr-home-sections', 'courtneyr-paper' ), kept_kind_sheets() );
	if ( ! in_array( $handle, $deferrable, true ) || ! is_front_page() || is_admin() || ! is_string( $tag ) || str_contains( $tag, 'onload=' ) ) {
		return $tag;
	}
	// v0.7.125: the request now starts after the window load event, not with
	// the HTML. Started early (media="print" swap), these sheets still shared
	// the slow-4G link with the HTML and fonts before the hero painted, and
	// PageSpeed's simulation counted all of them as LCP dependencies (mobile
	// LCP 4.2 s; render delay 3.5 s). load_deferred_sheets() swaps them in.
	$deferred = preg_replace( '/\shref=([\'"])/', ' data-cr-href=$1', $tag, 1 );
	if ( ! is_string( $deferred ) || $deferred === $tag ) {
		return $tag;
	}
	return $deferred . '<noscript>' . trim( $tag ) . "</noscript>\n";
}
add_filter( 'style_loader_tag', __NAMESPACE__ . '\\defer_card_sheet_on_front_page', 10, 2 );

/**
 * Front page: start the deferred sheets once the page has loaded.
 *
 * Each link keeps its place in the document, so the cascade is unchanged.
 */
function load_deferred_sheets(): void {
	if ( ! is_front_page() || is_admin() ) {
		return;
	}
	echo "<script id=\"cr-deferred-sheets\">addEventListener('load',function(){document.querySelectorAll('link[data-cr-href]').forEach(function(l){l.href=l.getAttribute('data-cr-href');l.removeAttribute('data-cr-href');});});</script>\n";
}
add_action( 'wp_footer', __NAMESPACE__ . '\\load_deferred_sheets', 99 );

/**
 * Front page only: have Perfmatters hold the full stylesheets until interaction.
 *
 * Perfmatters keys its used CSS by URL type, so every single post shares one
 * post.used.css built from whichever post it crawled first (likewise every
 * taxonomy archive shares tax.used.css). With the stylesheet behavior set to
 * "delay" site-wide, kind layouts missing from that shared file stayed
 * unstyled until the first interaction (Live, 2026-10-21 13:25-13:31 UTC).
 * The front page's used CSS comes from the front page alone, so delaying
 * there is safe; everywhere else keeps the site-wide setting (async).
 * Perfmatters reads the option while processing the output buffer, after
 * template_redirect.
 */
function delay_full_sheets_on_front_page(): void {
	if ( ! is_front_page() || ! class_exists( '\Perfmatters\Config' ) || ! isset( \Perfmatters\Config::$options['assets'] ) || ! is_array( \Perfmatters\Config::$options['assets'] ) ) {
		return;
	}
	\Perfmatters\Config::$options['assets']['rucss_stylesheet_behavior'] = '';
}
add_action( 'template_redirect', __NAMESPACE__ . '\\delay_full_sheets_on_front_page' );

/**
 * Stylesheets that load only on some posts or archives: Perfmatters inlines them whole.
 *
 * Perfmatters builds one used CSS per URL type (every single post shares
 * post.used.css, every kind archive tax.used.css) from whichever page it saw
 * first. A sheet that page didn't load can't be in the file, so on a mood post
 * the pin, on a photo post the frames, painted unstyled and jumped when the
 * full sheet arrived (throttled phone, 2026-10-21: CLS 0.45 on gtm-onsight,
 * 0.11–0.29 on archives). Excluded from RUCSS and inlined, each sheet is
 * whole at first paint on the pages that enqueue it and absent elsewhere.
 * Handles come from a crawl of every published post and archive on live
 * (2026-10-21); add a kind sheet here when the theme gains one. Not on the
 * front page, whose sheets defer_card_sheet_on_front_page() handles.
 */
const RUCSS_INLINE_HANDLES = array(
	'courtneyr-photo-frames',
	'courtneyr-photo',
	'courtneyr-paper',
	'courtneyr-nav',
	'courtneyr-mood-pin',
	'courtneyr-comic',
	'courtneyr-media-shelf',
	'courtneyr-gallery-zine',
	'courtneyr-ticket',
	'courtneyr-sticker',
	'courtneyr-eat-drink',
	'courtneyr-checkin-archive',
	'courtneyr-menu',
	'courtneyr-podcast',
	'courtneyr-note-board',
	'courtneyr-shelf-boards',
	// On every archive, but the cut-paper title's tile sizes are per-title
	// variants the first archive may not use: 36 px tiles grew to 54–91 px
	// when the full sheet landed (CLS 0.11–0.29). 6.7 KB, so whole.
	'courtneyr-cutout',
	'wp-block-image',
	'wp-block-gallery',
	'wp-block-cover',
	'wp-block-social-links',
	'ollie-block-core-navigation',
	'videoseo-youtube-embed-css',
	'pkiw-kind-layouts',
	'pkiw-block-checkins-feed',
	'leaflet',
	'leaflet-markercluster',
	'chatlog-block-style',
	'chatlog-conversation-style',
	'blablablocks-formats-styles',
);

/**
 * Rules in every-page sheets (components.css, PKIW blocks.css) that some page
 * of a type needs but the shared used CSS dropped, keyed by one class each.
 * Perfmatters keeps every rule whose selector names a listed class. Picked per
 * type from the same crawl as the cheapest class per missing rule, capped at
 * 4 KB of matched rules each: posts +35 KB of inline CSS (161 → 196 KB), kind
 * archives +10 KB, categories +23 KB, tags +8 KB.
 */
const RUCSS_KEEP_CLASSES = array(
	'post'     => 'relsyn aligncenter alignleft alignright cmplz-placeholder-parent cr-icon-avatar--audio cr-icon-avatar--link cr-icon-avatar--outline cr-icon-avatar--quote cr-icon-avatar--video cr-media__transcript cr-post-nav cr-pull-quote-wrap cr-pull-quote__copy cr-term-chip--1 cr-term-chip--2 cr-term-chip--3 cr-term-chip--5 fb-xfbml-parse-ignore format-standard has-text-align-right is-style-cr-marker-bar is-style-cr-pull-quote is-style-sme-alert-success pfbt-format-titleless pk-badge pk-body pk-card pk-dot pk-embed pk-embed--audio pk-embed--map pk-kindlabel pk-link pk-map-link pk-media pk-meta pk-mood pk-note pk-sr-only pk-stars pk-sub pk-thumb--poster pk-title reaction-avatar reaction-label single single-format-aside single-format-audio single-format-chat single-format-gallery single-format-image single-format-link single-format-quote single-format-status single-format-video single-post__title skip-link sloc-display sloc-location-icon sloc-map sme-highlighter svg-bluesky svg-linkedin taxonomy-post_tag wp-block-code wp-block-column wp-block-cover__image-background wp-block-gallery wp-block-post-terms wp-block-template-part wp-caption xfn-pill-acquaintance xfn-pill-co-worker xfn-pill-colleague xfn-pill-friend xfn-pill-met',
	'tax'      => 'cr-browse-all cr-icon-avatar--audio cr-icon-avatar--blog cr-icon-avatar--quote cr-icon-avatar--video cr-pull-quote-wrap cr-pull-quote__copy format-standard is-p0 is-p10 is-p5 is-r1 is-style-cr-pull-quote pk-badge pk-body pk-card pk-excerpt pk-kindlabel pk-link pk-media pk-media--stream pk-meta pk-sr-only pk-sub pk-thumb--poster pk-title skip-link sme-highlighter wp-block-post wp-block-query-pagination wp-block-template-part',
	'category' => 'cr-browse-all cr-cutout__gap cr-highlight cr-icon-avatar--outline cr-icon-avatar--quote cr-icon-avatar--video cr-sparkle cr-speaking-hero cr-speaking-hero__accent cr-speaking-hero__cta cr-speaking-hero__lead cr-speaking-hero__sparkles cr-speaking-hero__title cr-term-chip--2 cr-term-chip--3 cr-term-chip--5 format-aside format-gallery format-image format-quote format-standard format-status format-video is-p10 is-r3 is-style-cr-eyebrow pfbt-format-titleless skip-link wp-block-button__link wp-block-query-pagination wp-block-template-part',
	'tag'      => 'card__media cr-browse-all cr-icon-avatar--link cr-icon-avatar--outline cr-icon-avatar--speaking cr-term-chip--2 cr-term-chip--3 cr-term-chip--4 cr-term-chip--5 format-image format-link format-standard is-punct is-r3 skip-link wp-block-post-featured-image wp-block-query-pagination wp-block-template-part',
);

/**
 * The link-tag fragments Perfmatters matches (stripos on the whole tag). Core
 * prints id='…' and Perfmatters' minify rewrites it as id="…", so both.
 *
 * @return string[]
 */
function rucss_inline_ids(): array {
	$ids = array();
	foreach ( RUCSS_INLINE_HANDLES as $handle ) {
		$ids[] = "id='{$handle}-css'";
		$ids[] = "id=\"{$handle}-css\"";
	}
	return $ids;
}

/**
 * Excludes the conditional sheets from RUCSS so Perfmatters can inline them.
 *
 * @param string[] $exclusions Stylesheet fragments.
 * @return string[]
 */
function rucss_exclude_conditional_sheets( $exclusions ) {
	if ( is_front_page() || ! is_array( $exclusions ) ) {
		return $exclusions;
	}
	return array_merge( $exclusions, rucss_inline_ids() );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclude_conditional_sheets' );

/**
 * Inlines the excluded conditional sheets instead of leaving them render-blocking.
 *
 * @param string[] $inline Stylesheet fragments.
 * @return string[]
 */
function rucss_inline_conditional_sheets( $inline ) {
	if ( is_front_page() || ! is_array( $inline ) ) {
		return $inline;
	}
	return array_merge( $inline, rucss_inline_ids() );
}
add_filter( 'perfmatters_inline_stylesheets', __NAMESPACE__ . '\\rucss_inline_conditional_sheets' );

/**
 * Keeps the per-type classes in the shared used CSS.
 *
 * @param string[] $selectors Excluded selectors.
 * @return string[]
 */
function rucss_keep_type_classes( $selectors ) {
	if ( ! is_array( $selectors ) || is_front_page() ) {
		return $selectors;
	}
	$type = is_singular( 'post' ) ? 'post' : ( is_category() ? 'category' : ( is_tag() ? 'tag' : ( is_tax() ? 'tax' : '' ) ) );
	if ( '' === $type ) {
		return $selectors;
	}
	foreach ( explode( ' ', RUCSS_KEEP_CLASSES[ $type ] ) as $class ) {
		$selectors[] = '.' . $class;
	}
	return $selectors;
}
add_filter( 'perfmatters_rucss_excluded_selectors', __NAMESPACE__ . '\\rucss_keep_type_classes' );

/**
 * Print Perfmatters' used CSS at the end of <head>, not right after <title>.
 *
 * After <title> it sits before core's block-support and layout styles, so a
 * theme rule with the same specificity as core's (for example
 * .cr-newsletter-banner__inner { max-width: 1200px } against the constrained
 * layout's content width) lost until the full sheet, printed later, arrived:
 * the newsletter band rendered 1080 px wide and jumped to 1200 px at desktop
 * widths (CLS 0.07–0.12 on posts, 2026-10-21). At the end of <head> the used
 * rules take the place the theme's own stylesheets would.
 */
add_filter( 'perfmatters_used_css_below', '__return_true' );
