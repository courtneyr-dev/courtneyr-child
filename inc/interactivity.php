<?php
/**
 * Interactivity API modules for the theme.
 *
 * v0.5.18 cleanup: the original aspirational IA scaffolding for the
 * theme toggle was removed — the working toggle is vanilla JS
 * (assets/js/theme-toggle/view.js, enqueued in inc/enqueue.php). The
 * theme toggle MUST run pre-paint to prevent a dark-mode flash, which
 * IA's hydrate-after-parse model can't satisfy. Keeping it vanilla.
 *
 * Active IA modules:
 *   - courtneyr/callout (v0.5.14) — collapsible callouts
 *   - courtneyr/stream-filter (v0.5.14) — cr-stream-loop format filter
 *   - courtneyr/reading-progress (v0.5.18) — single-post scroll bar
 *   - courtneyr/pull-quote (v0.5.18) — copy-to-clipboard button
 *
 * Pre-paint script for theme:
 *   The inline pre_paint_theme() below sets data-theme on <html>
 *   before any CSS loads. This stays inline because it must execute
 *   before first paint.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Interactivity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inline pre-paint script. Sets data-theme on <html> before CSS loads.
 *
 * Output position: <head>, before any stylesheet link.
 * Storage key: 'courtneyr-theme' (same key the kit uses, so the kit and
 * the live site share the user's preference if they happen to test in
 * both).
 */
function pre_paint_theme(): void {
	?>
<script id="courtneyr-pre-paint">
(function () {
	try {
		var stored = localStorage.getItem('courtneyr-theme');
		if (stored === 'dark' || stored === 'light') {
			document.documentElement.setAttribute('data-theme', stored);
			return;
		}
		// No explicit choice — fall through to OS pref via @media (prefers-color-scheme).
	} catch (e) {
		// localStorage may be blocked. Fall through silently.
	}
})();
</script>
	<?php
}
add_action( 'wp_head', __NAMESPACE__ . '\\pre_paint_theme', 1 );

/* ============================================================
 * v0.5.14 — Callout collapse/expand (IA module #1).
 *
 * The callout patterns (cr-callout-note.php, cr-callout-warn.php)
 * render as static wp:group blocks. To make them collapsible without
 * forcing IA syntax into the editor canvas, we transform the rendered
 * HTML server-side via render_block: inject data-wp-* directives on
 * the outer .cr-callout div, and rewrite the .cr-callout__label
 * paragraph as a <button> with toggle handlers.
 *
 * The IA store (assets/js/interactivity/callout/view.js) provides
 * an `actions.toggle` that flips context.open and a `state.isCollapsed`
 * computed property the wrapper's wp-class binding reads.
 * ============================================================ */

/**
 * Transform any rendered .cr-callout block to add IA wiring.
 *
 * Operates on core/group blocks whose className includes "cr-callout".
 * Pattern auto-discovery generates these blocks; the filter extends
 * them at render time without editing the patterns themselves.
 */
function transform_callout_block( string $block_content, array $block ): string {
	if ( ! isset( $block['blockName'] ) || 'core/group' !== $block['blockName'] ) {
		return $block_content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( ! str_contains( $class, 'cr-callout' ) ) {
		return $block_content;
	}

	// v0.5.32 — generate a per-callout unique id so the toggle button
	// can carry aria-controls pointing to the body region. Static
	// counter survives across multiple callouts on the same page.
	static $callout_counter = 0;
	$callout_counter++;
	$callout_id = 'cr-callout-body-' . $callout_counter;

	// Add IA directives to the outer wrapper. Uses single quotes
	// inside the data-wp-context JSON so the attr's outer quotes can
	// stay double; works in HTML5 without escaping. Also stamps the
	// id on the wrapper so aria-controls below has a real target.
	$block_content = preg_replace(
		'/^(\s*<div\s+class="[^"]*cr-callout[^"]*")/i',
		'$1 id="' . $callout_id . '" data-wp-interactive="courtneyr/callout" data-wp-context=\'{"open":true}\' data-wp-class--is-collapsed="state.isCollapsed"',
		$block_content,
		1
	);

	// Rewrite the label paragraph as a real <button> so keyboard +
	// screen-reader semantics work. Chevron rotates via CSS when the
	// callout is collapsed (driven by the parent's is-collapsed class).
	// v0.5.32 — aria-controls points to the wrapper id; body content is
	// every sibling of the toggle, so the wrapper is the load-bearing
	// element ATs need to know about.
	$button_open  = '<button type="button" class="cr-callout__toggle" data-wp-on--click="actions.toggle" data-wp-bind--aria-expanded="context.open" aria-expanded="true" aria-controls="' . $callout_id . '">';
	$chevron      = '<svg class="cr-callout__chevron" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	$button_close = '</button>';

	// v0.5.50 — class regex made permissive: WP 6.5+ adds
	// `wp-block-paragraph` to every <p> emitted by core/paragraph, so
	// the previous `class="cr-callout__label"` pin never matched in
	// production and the toggle button + aria-controls were silently
	// dropped. Character-class match around the BEM hook is the right
	// shape — works regardless of class order or what WP adds next.
	$block_content = preg_replace(
		'/<p\s+class="[^"]*cr-callout__label[^"]*">([^<]*)<\/p>/',
		$button_open . '<span class="cr-callout__label-text">$1</span>' . $chevron . $button_close,
		$block_content,
		1
	);

	return $block_content;
}
add_filter( 'render_block', __NAMESPACE__ . '\\transform_callout_block', 10, 2 );

/**
 * Register the callout IA view module.
 */
function register_callout_view(): void {
	wp_register_script_module(
		'courtneyr/callout',
		COURTNEYR_CHILD_URI . '/assets/js/interactivity/callout/view.js',
		array( '@wordpress/interactivity' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_callout_view' );

/**
 * Enqueue the callout IA module. The module is small (~400 bytes
 * minified) and the conditional-load via data-wp-interactive auto-
 * detection isn't reliable for our use case (filter-injected
 * directives), so we always enqueue.
 */
function maybe_enqueue_callout(): void {
	wp_enqueue_script_module( 'courtneyr/callout' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\maybe_enqueue_callout' );


/* ============================================================
 * v0.5.14 — Stream Loop format filter (IA module #2).
 *
 * The cr-stream-loop pattern wraps its query loop in a
 * .cr-stream-filter container with chip buttons. Clicking a chip
 * sets context.format; the wrapper's data-active-format attribute
 * (bound via wp-bind) drives CSS attribute selectors that hide non-
 * matching post-template li's (which carry post_class()-emitted
 * format-X classes).
 *
 * No per-item directives, no DOM mutation — just one bound
 * attribute on the wrapper that CSS reads to control visibility +
 * active-chip styling.
 * ============================================================ */

/**
 * Register the stream-filter IA view module.
 */
function register_stream_filter_view(): void {
	wp_register_script_module(
		'courtneyr/stream-filter',
		COURTNEYR_CHILD_URI . '/assets/js/interactivity/stream-filter/view.js',
		array( '@wordpress/interactivity' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_stream_filter_view' );

/**
 * Enqueue the stream-filter IA module on any page that might render
 * the cr-stream-loop pattern. Same always-enqueue rationale as the
 * callout module above.
 */
function maybe_enqueue_stream_filter(): void {
	wp_enqueue_script_module( 'courtneyr/stream-filter' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\maybe_enqueue_stream_filter' );


/* ============================================================
 * v0.5.18 — Reading-progress bar (IA module #3).
 *
 * Thin fixed-position bar at the top of single posts. Hooks window
 * scroll, computes the percent of page scrolled past, and binds the
 * value to the bar's width. Only enqueued on single-post pages —
 * no value on the homepage / archive views where the page isn't a
 * focused linear read.
 * ============================================================ */

function register_reading_progress_view(): void {
	wp_register_script_module(
		'courtneyr/reading-progress',
		COURTNEYR_CHILD_URI . '/assets/js/interactivity/reading-progress/view.js',
		array( '@wordpress/interactivity' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_reading_progress_view' );

function maybe_enqueue_reading_progress(): void {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	wp_enqueue_script_module( 'courtneyr/reading-progress' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\maybe_enqueue_reading_progress' );


/* ============================================================
 * v0.5.18 — Pull-quote copy-to-clipboard (IA module #4).
 *
 * Adds a "Copy quote" button after every blockquote inside a
 * .is-style-cr-pull-quote container. Click writes the quote's first
 * paragraph text to navigator.clipboard, then swaps the button
 * label to "Copied" for 2 seconds.
 *
 * Same render-time filter pattern used for the callout module:
 * patterns themselves stay simple wp:quote blocks; the filter
 * injects the IA wiring + button at render time.
 * ============================================================ */

/**
 * Inject a "Copy quote" button after each rendered cr-pull-quote.
 */
function transform_pull_quote_block( string $block_content, array $block ): string {
	if ( ! isset( $block['blockName'] ) || 'core/quote' !== $block['blockName'] ) {
		return $block_content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( ! str_contains( $class, 'is-style-cr-pull-quote' ) && ! str_contains( $class, 'cr-pull-quote' ) ) {
		return $block_content;
	}

	// Wrap the quote in a container with IA wiring, append a copy
	// button. context.copied=false initially; toggles to true for
	// 2s after a successful clipboard write.
	// v0.5.32 — moved aria-live off the button (buttons aren't live
	// regions; AT didn't announce the swap reliably). Now there's a
	// dedicated visually-hidden status span next to the button that
	// AT does watch — it picks up "Quote copied to clipboard" when
	// state.copyStatus transitions from "" to non-empty after the
	// click action flips context.copied.
	$button = '<button type="button" class="cr-pull-quote__copy" data-wp-on--click="actions.copyQuote" data-wp-class--is-copied="context.copied" aria-label="Copy quote to clipboard">'
		. '<span class="cr-pull-quote__copy-default" aria-hidden="true">Copy quote</span>'
		. '<span class="cr-pull-quote__copy-confirm" aria-hidden="true">Copied</span>'
		. '</button>'
		. '<span class="cr-pull-quote__status screen-reader-text" role="status" aria-live="polite" data-wp-text="state.copyStatus"></span>';

	// Wrap the rendered block in an IA-aware container. Keep the
	// original markup intact; just nest it.
	$wrapped = '<div class="cr-pull-quote-wrap" data-wp-interactive="courtneyr/pull-quote" data-wp-context=\'{"copied":false}\'>'
		. $block_content
		. $button
		. '</div>';

	return $wrapped;
}
add_filter( 'render_block', __NAMESPACE__ . '\\transform_pull_quote_block', 10, 2 );

function register_pull_quote_view(): void {
	wp_register_script_module(
		'courtneyr/pull-quote',
		COURTNEYR_CHILD_URI . '/assets/js/interactivity/pull-quote/view.js',
		array( '@wordpress/interactivity' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_pull_quote_view' );

function maybe_enqueue_pull_quote(): void {
	wp_enqueue_script_module( 'courtneyr/pull-quote' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\maybe_enqueue_pull_quote' );

/* ============================================================
 * v0.5.51 — Stream-item avatar typing (post-format-aware).
 *
 * The cr-stream-loop pattern emits a static "blog" SVG avatar inside
 * each core/post-template item. This filter inspects the rendered
 * post's get_post_format() and rewrites the avatar's class + use-href
 * fragment so the right post-type icon + accent color appears for each
 * post in the stream.
 *
 * Solid types (10): blog, video, audio, link, bookmark, quote,
 * speaking, like, reply, review — accent passes AA as a fill.
 *
 * Outline types (8): aside, image, gallery, status, chat, repost, book,
 * event — accent fails AA as a fill, so we use cr-icon-avatar--outline
 * with style="--type-color: var(--cr-type-X);" instead.
 *
 * Map: WP post-format slug → our post-type avatar slug. WP only ships 9
 * core formats (aside, image, gallery, link, quote, status, video,
 * audio, chat). The other 9 (blog, bookmark, speaking, like, reply,
 * repost, book, event, review) are mapped from category slugs or fall
 * back to "blog" for Standard format posts.
 * ============================================================ */

const STREAM_AVATAR_SOLID = array(
	'blog', 'video', 'audio', 'link', 'bookmark',
	'quote', 'speaking', 'like', 'reply', 'review',
);

const STREAM_AVATAR_OUTLINE = array(
	'aside', 'image', 'gallery', 'status',
	'chat', 'repost', 'book', 'event',
);

/**
 * Resolve a post's stream-item type slug (one of the 18 cr-icon-avatar
 * variants) from its post format and categories.
 */
function resolve_stream_item_type( int $post_id ): string {
	$format = \get_post_format( $post_id );
	if ( $format && in_array( $format, array_merge( STREAM_AVATAR_SOLID, STREAM_AVATAR_OUTLINE ), true ) ) {
		return $format;
	}
	// Some custom slugs (speaking, book, event, etc.) live as categories
	// in this site's IA. Inspect category slugs for a hit.
	$categories = \get_the_category( $post_id );
	foreach ( $categories as $cat ) {
		$slug = $cat->slug;
		if ( in_array( $slug, array_merge( STREAM_AVATAR_SOLID, STREAM_AVATAR_OUTLINE ), true ) ) {
			return $slug;
		}
	}
	return 'blog';
}

/**
 * Filter rendered post-template items in the cr-stream context to type
 * the avatar markup based on the current post's format/category.
 */
function transform_stream_item_avatar( string $block_content, array $block ): string {
	if ( ! isset( $block['blockName'] ) ) {
		return $block_content;
	}
	// The stream-loop pattern produces core/group blocks tagged with
	// .cr-stream-item. Match those, ignoring deeper nesting.
	if ( 'core/group' !== $block['blockName'] ) {
		return $block_content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( ! str_contains( $class, 'cr-stream-item' ) ) {
		return $block_content;
	}
	if ( ! str_contains( $block_content, 'data-cr-stream-avatar' ) ) {
		return $block_content;
	}
	$post_id = \get_the_ID();
	if ( ! $post_id ) {
		return $block_content;
	}
	$type = resolve_stream_item_type( $post_id );

	if ( in_array( $type, STREAM_AVATAR_OUTLINE, true ) ) {
		// Outline variant — replace solid class + add type-color custom prop.
		$replacement = sprintf(
			'<span class="cr-icon-avatar cr-icon-avatar--outline cr-stream-item__avatar" aria-hidden="true" data-cr-stream-avatar="%1$s" style="--type-color: var(--cr-type-%1$s);">',
			\esc_attr( $type )
		);
	} else {
		$replacement = sprintf(
			'<span class="cr-icon-avatar cr-icon-avatar--%1$s cr-stream-item__avatar" aria-hidden="true" data-cr-stream-avatar="%1$s">',
			\esc_attr( $type )
		);
	}

	// Swap the opening <span class="cr-icon-avatar..." for the typed version.
	$block_content = preg_replace(
		'/<span class="cr-icon-avatar[^"]*cr-stream-item__avatar"[^>]*data-cr-stream-avatar="[^"]*"[^>]*>/',
		$replacement,
		$block_content,
		1
	);
	// Swap the use-href fragment to the right icon.
	$block_content = preg_replace(
		'/(icons\.svg)#post-icon-[a-z-]+/',
		'$1#post-icon-' . preg_quote( $type, '/' ),
		$block_content,
		1
	);
	return $block_content;
}
add_filter( 'render_block', __NAMESPACE__ . '\\transform_stream_item_avatar', 10, 2 );


/* ============================================================
 * v0.5.59 — Single-post format avatar.
 *
 * Drops the same cr-icon-avatar that archives/streams render onto
 * single post views, positioned left of the post title (titled
 * formats) or left of the post content (titleless formats: aside,
 * status, quote — where the title is visually hidden via the PFBT
 * `pfbt-format-titleless` body class).
 *
 * Reuses resolve_stream_item_type() above so the format → icon-slug
 * mapping is identical to the archive treatment. The avatar markup
 * matches the stream variant exactly so the same CSS rules paint
 * both contexts; a `single-post__format-avatar` modifier lets the
 * single template position it absolutely to the left.
 *
 * Two filters here:
 *   - render_block on core/post-title — covers all titled formats
 *     (standard, blog, video, audio, link, image, gallery, chat,
 *     bookmark, etc.).
 *   - render_block on core/post-content — used only when the post
 *     format is titleless. The avatar floats left of the first
 *     content line via CSS in components.css.
 *
 * The format-badge Block Hooks injection is suppressed via
 * hooked_block_types (cr-icon-avatar covers it visually + the
 * format remains discoverable through the chip in the meta row).
 * ============================================================ */

const SINGLE_POST_TITLELESS_FORMATS = array( 'aside', 'status', 'quote' );

/**
 * Build the cr-icon-avatar HTML for a given post id.
 *
 * Returns an empty string when:
 *   - $post_id is invalid
 *   - the post type isn't `post`
 *   - the post format is Standard (no avatar — the chip carries enough)
 *
 * Solid vs outline variant matches transform_stream_item_avatar above.
 */
function render_single_post_avatar( int $post_id ): string {
	if ( ! $post_id || 'post' !== \get_post_type( $post_id ) ) {
		return '';
	}
	// Only paint the avatar when the post has a non-Standard format
	// or maps to one of the cr-icon-avatar types via category.
	$format = \get_post_format( $post_id );
	if ( ! $format ) {
		return '';
	}
	$type = resolve_stream_item_type( $post_id );

	if ( in_array( $type, STREAM_AVATAR_OUTLINE, true ) ) {
		$class = 'cr-icon-avatar cr-icon-avatar--outline single-post__format-avatar';
		$style = sprintf( ' style="--type-color: var(--cr-type-%s);"', \esc_attr( $type ) );
	} else {
		$class = sprintf( 'cr-icon-avatar cr-icon-avatar--%s single-post__format-avatar', \esc_attr( $type ) );
		$style = '';
	}

	return sprintf(
		'<span class="%1$s" aria-hidden="true"%2$s><svg viewBox="0 0 24 24" width="1.5em" height="1.5em" focusable="false"><use href="%3$s/assets/svg/icons.svg#post-icon-%4$s"></use></svg></span>',
		\esc_attr( $class ),
		$style,
		\esc_url( \get_stylesheet_directory_uri() ),
		\esc_attr( $type )
	);
}

/**
 * Inject the format avatar inside the post title on single post views
 * (titled formats only).
 *
 * v0.5.62: scope to post-titles whose className contains
 * `single-post__title` so the filter doesn't fire for related-posts
 * query loop iterations (which have className `related-post__title`
 * and would otherwise also receive the single-post avatar). Also
 * inject INSIDE the <h1> tag instead of before it, so the avatar
 * flows inline next to the title text — no float, no fight against
 * alignwide vs constrained layout.
 */
function inject_avatar_before_post_title( string $block_content, array $block ): string {
	if ( ! isset( $block['blockName'] ) || 'core/post-title' !== $block['blockName'] ) {
		return $block_content;
	}
	if ( ! \is_singular( 'post' ) ) {
		return $block_content;
	}
	$class_name = $block['attrs']['className'] ?? '';
	if ( ! str_contains( $class_name, 'single-post__title' ) ) {
		return $block_content;
	}
	$post_id = \get_the_ID();
	$format  = \get_post_format( $post_id );
	if ( in_array( $format, SINGLE_POST_TITLELESS_FORMATS, true ) ) {
		return $block_content;
	}
	$avatar = render_single_post_avatar( (int) $post_id );
	if ( '' === $avatar ) {
		return $block_content;
	}
	// Inject INSIDE the opening heading tag so the avatar flows inline
	// with the title text. Matches `<h1 ...>` ... `<h6 ...>`, no greedy
	// wildcards. Only the first opening heading tag in the rendered
	// post-title block is matched.
	$injected = preg_replace(
		'/(<h[1-6]\b[^>]*>)/',
		'$1' . $avatar,
		$block_content,
		1
	);
	return null === $injected ? $block_content : $injected;
}
add_filter( 'render_block', __NAMESPACE__ . '\\inject_avatar_before_post_title', 10, 2 );

/**
 * Inject the format avatar before the post content on single post views
 * (titleless formats only — aside, status, quote).
 *
 * v0.5.62: scope to post-content with className containing
 * `single-post__content` so the filter only fires on the main single
 * template's content block, not any other post-content block that
 * might appear elsewhere on the page.
 */
function inject_avatar_before_post_content( string $block_content, array $block ): string {
	if ( ! isset( $block['blockName'] ) || 'core/post-content' !== $block['blockName'] ) {
		return $block_content;
	}
	if ( ! \is_singular( 'post' ) ) {
		return $block_content;
	}
	$class_name = $block['attrs']['className'] ?? '';
	if ( ! str_contains( $class_name, 'single-post__content' ) ) {
		return $block_content;
	}
	$post_id = \get_the_ID();
	$format  = \get_post_format( $post_id );
	if ( ! in_array( $format, SINGLE_POST_TITLELESS_FORMATS, true ) ) {
		return $block_content;
	}
	$avatar = render_single_post_avatar( (int) $post_id );
	if ( '' === $avatar ) {
		return $block_content;
	}
	return $avatar . $block_content;
}
add_filter( 'render_block', __NAMESPACE__ . '\\inject_avatar_before_post_content', 10, 2 );

/**
 * v0.5.60 — type the avatar inside Related Posts query items.
 *
 * The single template's Related Posts loop emits each item as a
 * core/group with className `related-post`. Inside lives a wp:html
 * placeholder carrying `data-cr-related-avatar` — matching the
 * same general shape as the cr-stream-item avatar pattern.
 *
 * On render_block, we look up the current post's format → icon type
 * via resolve_stream_item_type() (shared with archives + single
 * post avatars so all three contexts paint identically), then swap
 * the placeholder span + use-href to the right post-type avatar.
 */
function transform_related_post_avatar( string $block_content, array $block, $wp_block = null ): string {
	if ( ! isset( $block['blockName'] ) || 'core/group' !== $block['blockName'] ) {
		return $block_content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( ! str_contains( $class, 'related-post' ) || str_contains( $class, 'related-post__' ) ) {
		// Match only the OUTER `related-post` group, not the inner
		// `related-post__text` group.
		return $block_content;
	}
	if ( ! str_contains( $block_content, 'data-cr-related-avatar' ) ) {
		return $block_content;
	}
	// v0.5.62: prefer the WP_Block context['postId'] over get_the_ID().
	// In a Query Loop iteration the context carries the iterated post id
	// deterministically, while get_the_ID() can momentarily return the
	// outer singular post id depending on render-tree timing — that
	// caused stale-type avatars on the first card (the_post() vs filter
	// firing race; see image #153).
	$post_id = 0;
	if ( $wp_block instanceof \WP_Block && isset( $wp_block->context['postId'] ) ) {
		$post_id = (int) $wp_block->context['postId'];
	}
	if ( ! $post_id ) {
		$post_id = (int) \get_the_ID();
	}
	if ( ! $post_id ) {
		return $block_content;
	}
	$type = resolve_stream_item_type( $post_id );

	if ( in_array( $type, STREAM_AVATAR_OUTLINE, true ) ) {
		$replacement_open = sprintf(
			'<span class="cr-icon-avatar cr-icon-avatar--outline related-post__avatar" aria-hidden="true" data-cr-related-avatar="%1$s" style="--type-color: var(--cr-type-%1$s);">',
			\esc_attr( $type )
		);
	} else {
		$replacement_open = sprintf(
			'<span class="cr-icon-avatar cr-icon-avatar--%1$s related-post__avatar" aria-hidden="true" data-cr-related-avatar="%1$s">',
			\esc_attr( $type )
		);
	}

	$block_content = preg_replace(
		'/<span class="cr-icon-avatar[^"]*related-post__avatar"[^>]*data-cr-related-avatar="[^"]*"[^>]*>/',
		$replacement_open,
		$block_content,
		1
	);
	// Placeholder href is `#post-icon-blog` (bare fragment); swap to the
	// full sprite URL with the per-post type fragment.
	$block_content = preg_replace(
		'/href="#post-icon-[a-z-]+"/',
		sprintf(
			'href="%s/assets/svg/icons.svg#post-icon-%s"',
			\esc_url( \get_stylesheet_directory_uri() ),
			\esc_attr( $type )
		),
		$block_content,
		1
	);
	return $block_content;
}
add_filter( 'render_block', __NAMESPACE__ . '\\transform_related_post_avatar', 10, 3 );

/**
 * Suppress PFBT's format-badge Block Hooks injection. The cr-icon-avatar
 * we render above carries the same signal (post format) and the chip
 * in the meta row carries the format label; the badge becomes redundant.
 */
function suppress_format_badge_hook( $hooked_blocks, $position, $anchor_block ) {
	if ( ! is_array( $hooked_blocks ) ) {
		return $hooked_blocks;
	}
	if ( 'before' === $position && 'core/post-title' === $anchor_block ) {
		return array_values( array_diff( $hooked_blocks, array( 'post-formats/format-badge' ) ) );
	}
	return $hooked_blocks;
}
add_filter( 'hooked_block_types', __NAMESPACE__ . '\\suppress_format_badge_hook', 20, 3 );
