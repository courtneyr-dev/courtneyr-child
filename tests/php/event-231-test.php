<?php
/**
 * `php tests/php/event-231-test.php`: W7-T231 Event theme contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'CR_EVENT_THEME_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_DIR', CR_EVENT_THEME_DIR );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );

	$GLOBALS['cr_event_hooks']      = array();
	$GLOBALS['cr_event_enqueued']   = array();
	$GLOBALS['cr_event_posts']      = array();
	$GLOBALS['cr_event_terms']      = array();
	$GLOBALS['cr_event_stream']     = true;
	$GLOBALS['cr_event_single']     = false;
	$GLOBALS['cr_event_queried']    = 231;
	$GLOBALS['cr_event_failures']   = array();

	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_password = '';
		public int $post_author = 1;
		public function __construct( int $id, string $title ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	class WP_Term {
		public string $name = 'Events';
	}

	class WP_Block {
		/** @var array<string, mixed> */
		public array $context;
		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context ) {
			$this->context = $context;
		}
	}

	class WP_Block_Type_Registry {
		private static ?self $instance = null;
		public static function get_instance(): self {
			self::$instance ??= new self();
			return self::$instance;
		}
		public function is_registered( string $name ): bool {
			return 'post-kinds-indieweb/event-card' === $name;
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_event_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_event_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_event_enqueued'][] = array( $handle, $src, $deps, $version );
	}

	function wp_enqueue_block_style( $block, $args ): void {
		$GLOBALS['cr_event_enqueued'][] = array( $block, $args );
	}

	function is_singular( $type = '' ): bool {
		return 'post' === $type && (bool) $GLOBALS['cr_event_single'];
	}

	function is_admin(): bool {
		return false;
	}

	function get_queried_object_id(): int {
		return (int) $GLOBALS['cr_event_queried'];
	}

	function get_the_ID(): int {
		return (int) $GLOBALS['cr_event_queried'];
	}

	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? get_the_ID() : (int) $post;
		return $GLOBALS['cr_event_posts'][ $id ] ?? null;
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && 'kind' === $taxonomy && in_array( $term, $GLOBALS['cr_event_terms'][ $post->ID ] ?? array(), true );
	}

	function get_term_by( $field, $value, $taxonomy ): WP_Term {
		return new WP_Term();
	}

	function get_the_title( $post = null ): string {
		$post = get_post( $post );
		return $post instanceof WP_Post ? $post->post_title : '';
	}

	function get_permalink( $post = null ): string {
		$post = get_post( $post );
		return 'https://example.test/events/' . ( $post instanceof WP_Post ? $post->ID : 0 ) . '/';
	}

	function get_post_time( $format, $gmt = false, $post = null ): string {
		return '2026-11-10T12:00:00+00:00';
	}

	function get_the_author_meta( $field, $user_id = 0 ): string {
		return 'Courtney';
	}

	function get_option( $key, $default = false ) {
		return 'time_format' === $key ? 'g:i A' : ( 'date_format' === $key ? 'F j, Y' : $default );
	}

	function wp_timezone(): \DateTimeZone {
		return new \DateTimeZone( 'America/New_York' );
	}

	function wp_date( $format, $timestamp = null, $timezone = null ): string {
		$timezone = $timezone instanceof \DateTimeZone ? $timezone : wp_timezone();
		return ( new \DateTimeImmutable( '@' . (string) $timestamp ) )->setTimezone( $timezone )->format( (string) $format );
	}

	function wp_strip_all_tags( $text, $remove_breaks = false ): string {
		return strip_tags( (string) $text );
	}

	function has_post_thumbnail( $post = null ): bool {
		return false;
	}

	function get_post_thumbnail_id( $post = null ): int {
		return 0;
	}

	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		return '';
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_url( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	function esc_html__( $text, $domain = null ): string {
		return esc_html( $text );
	}

	/** Plugin event-card markup copied from src/blocks/event-card/render.php:99-149. */
	function cr_event_card_markup( array $overrides = array() ): string {
		$values = array_merge(
			array(
				'name' => 'Allowed Example Talk',
				'start' => '2026-11-16T18:00:00-05:00',
				'start_text' => 'November 16, 2026 at 6:00 PM',
				'end' => '2026-11-16T19:00:00-05:00',
				'location' => 'Authored Venue',
				'status' => 'Cancelled',
				'summary' => 'Public summary',
				'url' => 'https://events.example/public',
			),
			$overrides
		);
		$name_value = '' !== $values['url'] ? '<a class="u-url" href="' . esc_url( $values['url'] ) . '" target="_blank" rel="noopener">' . $values['name'] . '</a>' : $values['name'];
		$name = '' !== $values['name'] ? '<h2 class="pk-title p-name">' . $name_value . '</h2>' : '';
		$date = '' !== $values['start'] ? '<p class="pk-sub pk-event-date"><time class="dt-start" datetime="' . esc_attr( $values['start'] ) . '">' . esc_html( $values['start_text'] ) . '</time>' . ( '' !== $values['end'] ? '<data class="dt-end" value="' . esc_attr( $values['end'] ) . '" hidden></data>' : '' ) . '</p>' : '';
		$status = '' !== $values['status'] ? '<p class="pk-sub pk-event-status">' . esc_html( $values['status'] ) . '</p>' : '';
		$location = '' !== $values['location'] ? '<p class="pk-sub pk-event-location"><span class="p-location">' . esc_html( $values['location'] ) . '</span></p>' : '';
		$summary = '' !== $values['summary'] ? '<p class="p-summary">' . esc_html( $values['summary'] ) . '</p>' : '';
		$hidden_url = '' === $values['name'] && '' !== $values['url'] ? '<data class="u-url" value="' . esc_attr( $values['url'] ) . '" hidden></data>' : '';
		return '<article class="wp-block-post-kinds-indieweb-event-card pk-card k-event h-event"><div class="pk-badge"></div><div class="pk-body"><span class="pk-kindlabel">Event</span><div class="pk-caption">' . $name . $date . $status . $location . '</div>' . $summary . '</div>' . $hidden_url . '</article>';
	}

	/** Core-shaped render path: parsed blocks have no context key; WP_Block owns context. */
	function render_block( array $parsed_block ): string {
		return \Courtneyr\Child\Event\single_flyer( (string) ( $parsed_block['rendered'] ?? '' ), $parsed_block, new WP_Block( array( 'postId' => get_queried_object_id() ) ) );
	}

	function cr_event_css_rule( string $css, string $selector ): string {
		$start = strpos( $css, $selector );
		if ( false === $start ) {
			return '';
		}
		$open = strpos( $css, '{', $start );
		$close = false === $open ? false : strpos( $css, '}', $open );
		return false === $open || false === $close ? '' : substr( $css, $start, $close - $start + 1 );
	}

	function cr_event_check( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_event_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_event_failures'][] = $name . ': ' . $error->getMessage();
		}
		echo ( $before === count( $GLOBALS['cr_event_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	function cr_event_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			$GLOBALS['cr_event_failures'][] = $message;
		}
	}

	function cr_event_contains( string $needle, string $haystack, string $message ): void {
		cr_event_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	function cr_event_lacks( string $needle, string $haystack, string $message ): void {
		cr_event_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		fwrite( STDERR, "FAIL: set CR_WP_CORE to a WordPress checkout\n" );
		exit( 1 );
	}
	require_once $core . '/wp-includes/compat.php';
	require_once $core . '/wp-includes/utf8.php';
	require_once $core . '/wp-includes/kses.php';
	require_once $core . '/wp-includes/class-wp-block-parser-block.php';
	require_once $core . '/wp-includes/class-wp-block-parser-frame.php';
	require_once $core . '/wp-includes/class-wp-block-parser.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}
}

namespace Courtneyr\Child\HomeSections {
	function is_stream_surface(): bool {
		return (bool) $GLOBALS['cr_event_stream'];
	}
}

namespace PKIW {
	function kind_facts( int $post_id ): array {
		return array();
	}

	function get_kind_label( string $fallback, string $kind, string $context = '' ): string {
		return 'stream-card' === $context ? 'Calendar event' : $fallback;
	}
}

namespace {
	require CR_EVENT_THEME_DIR . '/inc/event-calendar.php';
	require CR_EVENT_THEME_DIR . '/inc/event.php';

	cr_event_check(
		'A-T1 template parses with the shared header and calendar-only body',
		static function (): void {
			$template = (string) file_get_contents( CR_EVENT_THEME_DIR . '/templates/taxonomy-kind-event.html' );
			$blocks   = ( new WP_Block_Parser() )->parse( $template );
			cr_event_assert( 'core/group' === ( $blocks[0]['blockName'] ?? '' ), 'template starts with sticky header group' );
			foreach ( array( 'is-position-sticky', 'wp:template-part {"slug":"header"', 'courtneyr/archive-identity', '"showPrefix":false', 'cr-flank--blue', 'wp:term-description', 'is-style-cr-marker-bar-short', 'cr-archive cr-archive--event', 'post-kinds-indieweb/event-calendar', '"className":"cr-event-calendar"', 'courtneyr-child/cr-browse-all' ) as $token ) {
				cr_event_contains( $token, $template, 'template contract' );
			}
			cr_event_lacks( 'wp:query ', $template, 'Event archive has no post loop' );
		}
	);

	cr_event_check(
		'A-T1 every My Calendar variable maps to a theme token',
		static function (): void {
			$source = array(
				'--close-button' => '#fff', '--search-highlight-bg ' => '#fff', '--main-background' => '#fff', '--main-color' => '#fff', '--navbar-background' => '#fff', '--nav-button-bg' => '#fff', '--nav-button-color' => '#fff', '--nav-button-border' => '#fff', '--nav-input-border' => '#fff', '--nav-input-background' => '#fff', '--nav-input-color' => '#fff', '--grid-cell-border' => '#fff', '--grid-header-border' => '#fff', '--grid-header-color' => '#fff', '--grid-weekend-color' => '#fff', '--grid-header-bg' => '#fff', '--grid-weekend-bg' => '#fff', '--grid-cell-background' => '#fff', '--current-day-border' => '#fff', '--current-day-color' => '#fff', '--current-day-bg' => '#fff', '--date-has-events-bg' => '#fff', '--date-has-events-color' => '#fff', '--primary-dark' => '#fff', '--primary-light' => '#fff', '--secondary-light' => '#fff', '--secondary-dark' => '#fff', '--highlight-dark' => '#fff', '--highlight-light' => '#fff',
				'text' => array( '--calendar-heading' => '1rem', '--event-title' => '1rem', '--grid-date' => '1rem', '--grid-date-heading' => '1rem', '--modal-title' => '1rem', '--navigation-controls' => '1rem', '--card-heading' => '1rem', '--list-date' => '1rem', '--author-card' => '1rem', '--single-event-title' => '1rem', '--mini-time-text' => '1rem', '--list-event-date' => '1rem', '--list-event-title' => '1rem' ),
				'sizing' => array( '--grid-max-width' => '80rem', '--main-padding' => '1rem', '--main-margin' => '1rem' ),
				'list-presets' => array( '--list-preset-border-color' => '#fff', '--list-preset-stripe-background' => '#fff', '--list-preset-date-badge-background' => '#fff', '--list-preset-date-badge-color' => '#fff', '--list-preset-background' => '#fff', '--list-preset-color' => '#fff' ),
			);
			$mapped = \Courtneyr\Child\EventCalendar\style_variables( $source );
			$walk   = static function ( array $expected, array $actual, string $path = '' ) use ( &$walk ): void {
				foreach ( $expected as $key => $value ) {
					cr_event_assert( array_key_exists( $key, $actual ), 'missing variable ' . $path . $key );
					if ( is_array( $value ) ) {
						$walk( $value, is_array( $actual[ $key ] ?? null ) ? $actual[ $key ] : array(), $path . $key . '/' );
					} else {
						cr_event_assert( str_starts_with( (string) ( $actual[ $key ] ?? '' ), 'var(--cr-' ), $path . $key . ' is not a theme var' );
					}
				}
			};
			$walk( $source, $mapped );
		}
	);

	cr_event_check(
		'A-T1 assets stay gated to their blocks and RUCSS exclusions',
		static function (): void {
			cr_event_assert( array() === $GLOBALS['cr_event_enqueued'], 'requiring modules enqueues no style' );
			$html = \Courtneyr\Child\EventCalendar\enqueue( '<section>calendar</section>', array( 'blockName' => 'post-kinds-indieweb/event-calendar' ) );
			cr_event_assert( '<section>calendar</section>' === $html, 'calendar filter preserves output' );
			cr_event_assert( 'courtneyr-event-calendar' === ( $GLOBALS['cr_event_enqueued'][0][0] ?? '' ), 'calendar block enqueues calendar handle' );
			cr_event_assert( array( 'existing.css', 'cr-event-calendar.css' ) === \Courtneyr\Child\EventCalendar\rucss_exclusions( array( 'existing.css' ) ), 'calendar RUCSS exclusion' );
			cr_event_assert( array( 'existing.css', 'cr-event.css' ) === \Courtneyr\Child\Event\rucss_exclusions( array( 'existing.css' ) ), 'Event RUCSS exclusion' );
			cr_event_lacks( 'wp_enqueue_block_style', (string) file_get_contents( CR_EVENT_THEME_DIR . '/inc/event.php' ), 'Event paint is gated to singles and Stream surfaces' );
		}
	);

	cr_event_check(
		'A-T4 calendar CSS exposes target, focus, forced-color and motion contracts',
		static function (): void {
			$css       = (string) file_get_contents( CR_EVENT_THEME_DIR . '/assets/css/cr-event-calendar.css' );
			$event_css = (string) file_get_contents( CR_EVENT_THEME_DIR . '/assets/css/cr-event.css' );
			cr_event_assert( strlen( $css ) <= 30720, 'calendar sheet exceeds 30 KiB' );
			cr_event_assert( strlen( $event_css ) <= 30720, 'Event sheet exceeds 30 KiB' );
			foreach ( array( '@property --cr-event-calendar-focus', 'inherits: false;', '@media (forced-colors: active)', '@media (prefers-reduced-motion: reduce)', 'aria-current="date"', 'HighlightText', 'LinkText', 'ButtonText', 'CanvasText', 'min-inline-size: 44px;', 'min-block-size: 44px;', '.my-calendar-navigation', '.mc-format', '.mc-time', '.mc-date', '.event-title button', 'dialog.my-calendar-modal button.my-calendar-modal-close', '@container mcmain ( width >= 312px )' ) as $token ) {
				cr_event_contains( $token, $css, 'calendar accessibility CSS' );
			}
			foreach ( array( '.cr-event-calendar .mc-main.mc-main :is(.my-calendar-navigation, .my-calendar-nav, .mc-format, .mc-time) :is(a, button, input, select)', '.cr-event-calendar .mc-main .mc-date', '.cr-event-calendar .mc-main .event-title button', '.cr-event-calendar .mc-main .mc-list .event-date button', 'dialog.my-calendar-modal button.my-calendar-modal-close' ) as $selector ) {
				$rule = cr_event_css_rule( $css, $selector );
				cr_event_contains( 'min-inline-size: 44px;', $rule, $selector . ' width target' );
				cr_event_contains( 'min-block-size: 44px;', $rule, $selector . ' height target' );
			}
			foreach ( array( 'linear-gradient', 'radial-gradient', 'filter:' ) as $forbidden ) {
				cr_event_lacks( $forbidden, $css, 'calendar uses no forbidden effect' );
			}
			foreach ( array( '.mc-calendar-title', '[data-controls-modal]', '.cr-event-calendar .mc-events {' ) as $dead_selector ) {
				cr_event_lacks( $dead_selector, $css, 'calendar uses served My Calendar selectors' );
			}
			foreach ( array( 'rotate(3deg)', 'rotate(-3deg)', 'rotate(2deg)', 'rotate(-2deg)' ) as $literal_rotation ) {
				cr_event_lacks( $literal_rotation, $css . $event_css, 'Event art rotates through theme tokens' );
			}
			$tab_rule = cr_event_css_rule( $event_css, '.cr-stream-page .pk-card.k-event.cr-event-tab' );
			foreach ( array( 'clip-path: none;', 'filter: none;', 'text-align: start;' ) as $crest_reset ) {
				cr_event_contains( $crest_reset, $tab_rule, 'Stream tab resets the legacy Event crest' );
			}
			foreach ( array( '--cr-event-paper: var(--cr-printer-ivory);', 'border-block-start-color: transparent;', 'background: none;', 'box-shadow: var(--cr-shadow-hard), var(--cr-event-focus-shadow' ) as $tab_paint ) {
				cr_event_contains( $tab_paint, $tab_rule, 'Stream tab is ivory paper with the token hard shadow and a torn top edge' );
			}
			cr_event_lacks( '5px 5px 0 var(--cr-selective-yellow)', $event_css, 'Stream tab uses no raw hard shadow' );
			$torn_rule = cr_event_css_rule( $event_css, '.cr-stream-page .pk-card.cr-event-tab .cr-event-tab__event::before' );
			foreach ( array( '.cr-home-stream .pk-card.cr-event-tab .cr-event-tab__event::after', 'background: var(--cr-event-paper);', 'clip-path: polygon(0 0, 7% 0.4rem,', '100% 0, 100% 100%, 0 100%);' ) as $torn_edge ) {
				cr_event_contains( $torn_edge, $torn_rule, 'Stream tab draws its torn top edge on the edge and paper layers' );
			}
			$sticker_rule = cr_event_css_rule( $event_css, ".cr-event-flyer__status,\n.cr-event-tab__status" );
			foreach ( array( 'background: var(--cr-selective-yellow);', 'color: var(--cr-russian-violet);', 'font-family: var(--cr-font-mono);', 'font-size: var(--cr-text-sm);', 'clip-path: polygon(0 0, 100% 0, 100% 34%, 94% 50%, 100% 66%, 100% 100%, 0 100%, 0 66%, 6% 50%, 0 34%);' ) as $sticker ) {
				cr_event_contains( $sticker, $sticker_rule, 'Stream Cancelled label shares the notched status sticker' );
			}
			cr_event_contains( '.cr-stream-page .pk-card.cr-event-tab .cr-event-tab__name a::after', $event_css, 'Stream link overlay stays scoped to the Stream surface' );
			cr_event_contains( '.cr-home-stream .pk-card.cr-event-tab .cr-event-tab__name a::after', $event_css, 'home Stream link overlay stays scoped to the Stream surface' );
		}
	);

	cr_event_check(
		'A-T5 real Stream adapter omits rendered sentinel location and keeps an allowed fact',
		static function (): void {
			$post = new WP_Post( 231, 'Community talk' );
			$GLOBALS['cr_event_posts'][231] = $post;
			$GLOBALS['cr_event_terms'][231] = array( 'event' );
			$fixture = cr_event_card_markup( array( 'location' => 'Sentinel Hall, 1 Sentinel Way, 0.123456 / 0.654321', 'summary' => 'Sentinel private summary' ) );
			$out = \Courtneyr\Child\Event\stream_tab( $fixture, array(), new WP_Block( array( 'postId' => 231 ) ) );
			foreach ( array( 'cr-event-tab', 'Allowed Example Talk', 'Nov 16 · 6:00 PM', 'Cancelled', 'Calendar event', 'dt-published' ) as $allowed ) {
				cr_event_contains( $allowed, $out, 'Stream allowed fact' );
			}
			foreach ( array( 'Sentinel Hall', '1 Sentinel Way', '0.123456', '0.654321', 'Sentinel private summary', 'sentinel.jpg', 'events.example/sentinel', 'p-location' ) as $private ) {
				cr_event_lacks( $private, $out, 'Stream privacy contract' );
			}
			cr_event_assert( 1 === substr_count( $out, '<a ' ), 'Stream tab has exactly one link' );
			cr_event_contains( 'pk-title cr-event-tab__name', $out, 'outer Stream heading-level pass can still find the title' );
			$early = \Courtneyr\Child\Event\stream_tab( $fixture, array(), null );
			cr_event_contains( 'cr-event-tab', $early, 'two-argument event-card path transforms card-only output' );
			$generic = '<article class="pk-card pk-card--stream k-event"><h2 class="pk-title">Body-only event post</h2></article>';
			cr_event_assert( $generic === \Courtneyr\Child\Event\stream_tab( $generic, array( 'attrs' => array( 'headingLevel' => 4 ) ), new WP_Block( array( 'postId' => 231 ) ) ), 'generic Stream card without an event date remains unchanged' );
			$all_day = cr_event_card_markup( array( 'start' => '2026-11-16', 'start_text' => 'November 16, 2026', 'end' => '' ) );
			$all_day_out = \Courtneyr\Child\Event\stream_tab( $all_day, array(), new WP_Block( array( 'postId' => 231 ) ) );
			cr_event_contains( '>Nov 16</time>', $all_day_out, 'all-day Stream tab omits midnight' );
			cr_event_lacks( 'Nov 16 ·', $all_day_out, 'all-day Stream tab has no synthetic time' );
		}
	);

	cr_event_check(
		'A-T5 single flyer uses only values present in rendered plugin output',
		static function (): void {
			$GLOBALS['cr_event_single'] = true;
			$fixture = cr_event_card_markup( array( 'end' => '' ) );
			$out = render_block( array( 'blockName' => 'post-kinds-indieweb/event-card', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array(), 'rendered' => $fixture ) );
			foreach ( array( 'cr-event-flyer', 'Authored Venue', 'Cancelled', 'Public summary', 'events.example', 'h-event', 'p-location' ) as $allowed ) {
				cr_event_contains( $allowed, $out, 'single allowed fact' );
			}
			foreach ( array( 'Sentinel Hall', '0.123456', 'Add to calendar', 'ICS' ) as $private ) {
				cr_event_lacks( $private, $out, 'single excludes withheld or unsupported value' );
			}
			cr_event_contains( '<span class="p-location">Authored Venue</span>', $out, 'p-location wraps only the value' );
			$facts = \Courtneyr\Child\Event\rendered_facts( cr_event_card_markup( array( 'name' => 'Allowed <img src="x" alt=""> Name', 'url' => '' ) ) );
			cr_event_assert( 'Allowed  Name' === $facts['name'], 'void tags do not keep the title parser open' );
			$hidden_url = \Courtneyr\Child\Event\rendered_facts( cr_event_card_markup( array( 'name' => '', 'url' => 'https://events.example/data-url' ) ) );
			cr_event_assert( 'https://events.example/data-url' === $hidden_url['event_url'], 'hidden data.u-url reaches the flyer parser' );
		}
	);

	cr_event_check(
		'A-T1 and A-T5 HTML adapters contain no preg_ parser',
		static function (): void {
			foreach ( array( 'event-calendar.php', 'event.php' ) as $file ) {
				$source = (string) file_get_contents( CR_EVENT_THEME_DIR . '/inc/' . $file );
				cr_event_lacks( 'preg_', $source, $file . ' uses WP_HTML_Tag_Processor' );
			}
			$source = (string) file_get_contents( CR_EVENT_THEME_DIR . '/inc/event.php' );
			cr_event_contains( 'WP_HTML_Tag_Processor', $source, 'Event adapters use Tag Processor' );
			cr_event_contains( 'render_block_post-kinds-indieweb/stream-card', $source, 'Stream adapter owns stream-card filter' );
			cr_event_contains( 'render_block_post-kinds-indieweb/event-card', $source, 'single adapter owns event-card filter' );
		}
	);

	if ( $GLOBALS['cr_event_failures'] ) {
		fwrite( STDERR, "\n" . implode( "\n", $GLOBALS['cr_event_failures'] ) . "\n" );
		exit( 1 );
	}

	echo "\nPASS W7-T231 Event theme contracts\n";
}
