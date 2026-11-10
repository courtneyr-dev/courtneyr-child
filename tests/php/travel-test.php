<?php
/**
 * `php tests/php/travel-test.php`: travel theme contracts.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_TRAVEL_THEME_DIR', dirname( __DIR__, 2 ) );
	define( 'COURTNEYR_CHILD_DIR', CR_TRAVEL_THEME_DIR );

	$GLOBALS['cr_travel_hooks']      = array();
	$GLOBALS['cr_travel_styles']     = array();
	$GLOBALS['cr_travel_enqueued']   = array();
	$GLOBALS['cr_travel_registered'] = array();
	$GLOBALS['cr_travel_posts']      = array();
	$GLOBALS['cr_travel_facts']      = array();
	$GLOBALS['cr_travel_terms']      = array();
	$GLOBALS['cr_travel_passwords']  = array();
	$GLOBALS['cr_travel_stream']     = true;
	$GLOBALS['cr_travel_taxonomy']   = '';
	$GLOBALS['cr_travel_singular']   = true;
	$GLOBALS['cr_travel_admin']      = false;
	$GLOBALS['cr_travel_feed']       = false;
	$GLOBALS['cr_travel_rest']       = false;
	$GLOBALS['cr_travel_hidden']     = array();
	$GLOBALS['cr_travel_failures']   = array();

	class WP_Post {
		public int $ID;
		public string $post_title;
		public function __construct( int $id, string $title ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	class WP_Term {
		public string $name;
		public function __construct( string $name ) {
			$this->name = $name;
		}
	}

	class WP_Block {
		/** @var array<string, mixed> */
		public array $context;
		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context = array() ) {
			$this->context = $context;
		}
	}

	class WP_Block_Type_Registry {
		private static ?self $instance = null;
		public bool $registered = true;
		public static function get_instance(): self {
			self::$instance ??= new self();
			return self::$instance;
		}
		public function is_registered( string $name ): bool {
			return $this->registered && in_array( $name, array( 'post-kinds-indieweb/trip-entry', 'post-kinds-indieweb/itinerary-entry', 'post-kinds-indieweb/trip-card', 'post-kinds-indieweb/itinerary-card' ), true );
		}
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_travel_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_travel_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	function register_block_style( $block, $args ): void {
		$GLOBALS['cr_travel_styles'][] = array( $block, $args );
	}

	function wp_enqueue_block_style( $block, $args ): void {
		$GLOBALS['cr_travel_enqueued'][] = array( $block, $args );
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_travel_enqueued'][] = array( $handle, compact( 'src', 'deps', 'version' ) );
	}

	function wp_style_is( $handle, $status = 'enqueued' ): bool {
		return 'registered' === $status && isset( $GLOBALS['cr_travel_registered'][ $handle ] );
	}

	function wp_register_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_travel_registered'][ $handle ] = compact( 'src', 'deps', 'version' );
	}

	function wp_style_add_data( $handle, $key, $value ): void {
		$GLOBALS['cr_travel_registered'][ $handle ][ $key ] = $value;
	}

	function is_tax( $taxonomy = '', $term = '' ): bool {
		return 'kind' === $taxonomy && $term === $GLOBALS['cr_travel_taxonomy'];
	}

	function is_singular( $type = '' ): bool {
		return 'post' === $type && (bool) $GLOBALS['cr_travel_singular'];
	}

	function is_admin(): bool {
		return (bool) $GLOBALS['cr_travel_admin'];
	}

	function is_feed(): bool {
		return (bool) $GLOBALS['cr_travel_feed'];
	}

	function wp_is_serving_rest_request(): bool {
		return (bool) $GLOBALS['cr_travel_rest'];
	}

	function has_term( $term, $taxonomy, $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && 'kind' === $taxonomy && in_array( $term, $GLOBALS['cr_travel_terms'][ $post->ID ] ?? array(), true );
	}

	function get_queried_object_id(): int {
		return 244;
	}

	function get_the_ID(): int {
		return 244;
	}

	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = null === $post ? 244 : (int) $post;
		return $GLOBALS['cr_travel_posts'][ $id ] ?? null;
	}

	function post_password_required( $post = null ): bool {
		$post = get_post( $post );
		return $post instanceof WP_Post && ! empty( $GLOBALS['cr_travel_passwords'][ $post->ID ] );
	}

	function get_the_title( $post = null ): string {
		$post = get_post( $post );
		return $post instanceof WP_Post ? $post->post_title : '';
	}

	function get_permalink( $post = null ): string {
		$post = get_post( $post );
		return 'https://example.test/travel/' . ( $post instanceof WP_Post ? $post->ID : 0 ) . '/';
	}

	function get_the_date( $format = '', $post = null ): string {
		return 'c' === $format ? '2026-06-11T08:00:00-04:00' : 'June 11, 2026';
	}

	function get_term_by( $field, $value, $taxonomy ): WP_Term {
		return new WP_Term( 'itinerary' === $value ? 'Itinerary' : 'Trip' );
	}

	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function esc_url( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}

	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	function _n( $single, $plural, $number, $domain = null ): string {
		return 1 === (int) $number ? (string) $single : (string) $plural;
	}

	function number_format_i18n( $number ): string {
		return number_format( (int) $number );
	}

	function esc_html_e( $text, $domain = null ): void {
		echo esc_html( $text );
	}

	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}

	function cr_travel_post( int $id, string $kind, array $facts = array() ): WP_Post {
		$post = new WP_Post( $id, 'Night train to Rainport' );
		$GLOBALS['cr_travel_posts'][ $id ]     = $post;
		$GLOBALS['cr_travel_terms'][ $id ]     = array( $kind );
		$GLOBALS['cr_travel_passwords'][ $id ] = false;
		$GLOBALS['cr_travel_facts'][ $id ]     = array_merge(
			array(
				'origin'           => 'Pine Harbor',
				'destination'      => 'Rainport',
				'range'            => array( 'start' => '2026-06-11', 'end' => '2026-06-14', 'display' => 'June 11–14, 2026' ),
				'count'            => 3,
					'stops'             => array(
						array( 'label' => 'Pine Harbor', 'place' => array( 'name' => 'Pine Harbor' ), 'lat' => 45.5152, 'lng' => -122.6784 ),
						array( 'label' => 'Sentinel Quay', 'place' => array( 'street' => '1 Sentinel Way', 'url' => 'https://sentinel.example/quay' ), 'lat' => 0.244244, 'lng' => 0.442442 ),
					),
					'legs'              => array(
						array( 'from' => array( 'label' => 'Pine Harbor' ), 'to' => array( 'label' => 'Sentinel Quay', 'place' => array( 'postal_code' => 'SE244' ) ), 'carrier' => 'Sentinel Air' ),
					),
			),
			$facts
		);
		return $post;
	}

	function cr_travel_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_travel_failures'][] = $message;
		}
	}

	function cr_travel_same( $expected, $actual, string $message ): void {
		cr_travel_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	function cr_travel_contains( string $needle, string $haystack, string $message ): void {
		cr_travel_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	function cr_travel_not_contains( string $needle, string $haystack, string $message ): void {
		cr_travel_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	function cr_travel_has_hook( string $hook, string $callback, int $priority, int $accepted_args ): bool {
		foreach ( $GLOBALS['cr_travel_hooks'] as $registered ) {
			if ( array( $hook, $callback, $priority, $accepted_args ) === $registered ) {
				return true;
			}
		}
		return false;
	}

	function cr_travel_check( string $name, callable $test ): void {
		$before = count( $GLOBALS['cr_travel_failures'] );
		try {
			$test();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_travel_failures'][] = $name . ': ' . $error->getMessage();
		}
		echo ( $before === count( $GLOBALS['cr_travel_failures'] ) ? 'ok   ' : 'FAIL ' ) . $name . "\n";
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
		return (bool) $GLOBALS['cr_travel_stream'];
	}
}

namespace PKIW {
	final class Travel {
		public static function route_html( string $from, string $to ): string {
			if ( '' === $from || '' === $to ) {
				return '';
			}
			return '<span class="pkiw-route"><span class="pkiw-route__from">' . \esc_html( $from ) . '</span> <span aria-hidden="true">→</span><span class="screen-reader-text"> to </span> <span class="pkiw-route__to">' . \esc_html( $to ) . '</span></span>';
		}
	}

	function kind_facts( int $post_id ): array {
		return $GLOBALS['cr_travel_facts'][ $post_id ] ?? array();
	}

	function get_kind_label( string $fallback, string $kind, string $context = '' ): string {
		return 'stream-card' === $context ? 'Travel log' : $fallback;
	}

	function entry_author_html( \WP_Post $post ): string {
		return '<span class="p-author h-card">Author</span>';
	}

	function untitled_name( \WP_Post $post, bool $with_kind = true ): string {
		return 'Untitled travel post';
	}

	function post_title_is_hidden( \WP_Post $post ): bool {
		return ! empty( $GLOBALS['cr_travel_hidden'][ $post->ID ] );
	}
}

namespace {
	require CR_TRAVEL_THEME_DIR . '/inc/stamps.php';
	require CR_TRAVEL_THEME_DIR . '/inc/travel.php';
	require CR_TRAVEL_THEME_DIR . '/inc/stream-travel.php';

	cr_travel_check(
		'travel hooks keep required priorities and accepted arguments',
		static function (): void {
			$expected = array(
				array( 'init', 'Courtneyr\\Child\\Travel\\register_block_styles', 20, 1 ),
				array( 'pkiw_trip_entry_badge_html', 'Courtneyr\\Child\\Travel\\entry_stamp', 10, 2 ),
				array( 'pkiw_trip_stop_stamp_html', 'Courtneyr\\Child\\Travel\\stop_stamp', 10, 3 ),
				array( 'enqueue_block_assets', 'Courtneyr\\Child\\Travel\\enqueue', 10, 1 ),
				array( 'perfmatters_rucss_excluded_stylesheets', 'Courtneyr\\Child\\Travel\\rucss_exclusions', 20, 1 ),
				array( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StreamTravel\\travel_card', 10, 3 ),
			);
			foreach ( $expected as $hook ) {
				cr_travel_assert( cr_travel_has_hook( ...$hook ), 'missing hook ' . $hook[0] . ' at priority ' . $hook[2] );
			}
		}
	);

	cr_travel_check(
		'trip and itinerary block styles share the editor travel sheet',
		static function (): void {
			\Courtneyr\Child\Travel\register_block_styles();
			cr_travel_same( 'post-kinds-indieweb/trip-entry', $GLOBALS['cr_travel_styles'][0][0] ?? '', 'trip block target' );
			cr_travel_same( 'cr-passport', $GLOBALS['cr_travel_styles'][0][1]['name'] ?? '', 'trip style slug' );
			cr_travel_same( 'post-kinds-indieweb/itinerary-entry', $GLOBALS['cr_travel_styles'][1][0] ?? '', 'itinerary block target' );
			cr_travel_same( 'cr-wallet', $GLOBALS['cr_travel_styles'][1][1]['name'] ?? '', 'itinerary style slug' );
			cr_travel_same( 'courtneyr-travel', $GLOBALS['cr_travel_enqueued'][0][1]['handle'] ?? '', 'trip editor handle' );
			cr_travel_same( 'courtneyr-travel', $GLOBALS['cr_travel_enqueued'][1][1]['handle'] ?? '', 'itinerary editor handle' );
				cr_travel_same( 'post-kinds-indieweb/trip-card', $GLOBALS['cr_travel_enqueued'][2][0] ?? '', 'embedded trip card target' );
				cr_travel_same( 'courtneyr-travel', $GLOBALS['cr_travel_enqueued'][2][1]['handle'] ?? '', 'embedded trip card handle' );
				cr_travel_same( 'post-kinds-indieweb/itinerary-card', $GLOBALS['cr_travel_enqueued'][3][0] ?? '', 'embedded itinerary card target' );
		}
	);

	cr_travel_check(
		'decorative stamps use only approved public text and stable seeds',
		static function (): void {
			$post  = cr_travel_post( 244, 'trip' );
			$post->post_title = 'Sentinel place, date, route and 99 stops';
			$entry = \Courtneyr\Child\Travel\entry_stamp( '', $post );
			cr_travel_contains( '>TRIP<', $entry, 'entry stamp headline' );
			cr_travel_contains( 'cr-stamp__glyph', $entry, 'entry stamp glyph' );
			foreach ( array( 'Sentinel', 'place', 'date', 'route', '99 stops' ) as $forbidden ) {
				cr_travel_not_contains( $forbidden, $entry, 'entry stamp prints no travel fact' );
			}
			cr_travel_same( $entry, \Courtneyr\Child\Travel\entry_stamp( '', $post ), 'entry stamp stable output' );
				$stop = array( 'number' => 2, 'label' => 'Rainport', 'day_display' => 'June 12', 'place' => array( 'street' => '1 Sentinel Way' ), 'lat' => 0.244244, 'lng' => 0.442442 );
			$stamp = \Courtneyr\Child\Travel\stop_stamp( '', $stop, 244 );
			cr_travel_contains( 'RAINPORT', $stamp, 'stop visible label' );
			cr_travel_contains( 'JUNE 12', $stamp, 'stop visible day' );
			cr_travel_not_contains( 'Sentinel', $stamp, 'stop street sentinel' );
				cr_travel_not_contains( '0.244244', $stamp, 'stop latitude sentinel' );
				cr_travel_not_contains( '0.442442', $stamp, 'stop longitude sentinel' );
			cr_travel_same( $stamp, \Courtneyr\Child\Travel\stop_stamp( '', $stop, 244 ), 'stop stable output' );
			$next_stop           = $stop;
			$next_stop['number'] = 3;
			cr_travel_assert( $stamp !== \Courtneyr\Child\Travel\stop_stamp( '', $next_stop, 244 ), 'printed stop number changes the seed' );
			cr_travel_same( '', \Courtneyr\Child\Travel\stop_stamp( '', array(), 244 ), 'empty stop has no empty stamp outline' );
				$GLOBALS['cr_travel_feed'] = true;
				cr_travel_same( '', \Courtneyr\Child\Travel\stop_stamp( '', $stop, 244 ), 'feed omits repeated decorative stamp text' );
				$GLOBALS['cr_travel_feed'] = false;
				$GLOBALS['cr_travel_rest'] = true;
				cr_travel_same( '', \Courtneyr\Child\Travel\stop_stamp( '', $stop, 244 ), 'REST render omits repeated decorative stamp text' );
				$GLOBALS['cr_travel_rest'] = false;
		}
	);

	cr_travel_check(
		'the travel sheet loads on archive, single, Stream and editor surfaces only',
		static function (): void {
			$GLOBALS['cr_travel_enqueued'] = array();
			$GLOBALS['cr_travel_stream'] = false;
			$GLOBALS['cr_travel_singular'] = false;
			$GLOBALS['cr_travel_taxonomy'] = 'trip';
			\Courtneyr\Child\Travel\enqueue();
			cr_travel_same( 'courtneyr-travel', $GLOBALS['cr_travel_enqueued'][0][0] ?? '', 'archive handle' );

			$GLOBALS['cr_travel_enqueued'] = array();
			$GLOBALS['cr_travel_taxonomy'] = '';
			$GLOBALS['cr_travel_singular'] = true;
			cr_travel_post( 244, 'itinerary' );
			\Courtneyr\Child\Travel\enqueue();
			cr_travel_same( 'courtneyr-travel', $GLOBALS['cr_travel_enqueued'][0][0] ?? '', 'single handle' );

				$GLOBALS['cr_travel_enqueued'] = array();
				$GLOBALS['cr_travel_terms'][244] = array( 'note' );
				\Courtneyr\Child\Travel\enqueue();
				cr_travel_same( array(), $GLOBALS['cr_travel_enqueued'], 'singular non-travel post does not globally load the sheet' );
				$GLOBALS['cr_travel_terms'][244] = array( 'itinerary' );

			$GLOBALS['cr_travel_enqueued'] = array();
			$GLOBALS['cr_travel_singular'] = false;
			$GLOBALS['cr_travel_stream'] = true;
			\Courtneyr\Child\Travel\enqueue();
			cr_travel_same( 'courtneyr-travel', $GLOBALS['cr_travel_enqueued'][0][0] ?? '', 'Stream handle' );

			$GLOBALS['cr_travel_enqueued'] = array();
			$GLOBALS['cr_travel_stream'] = false;
			$GLOBALS['cr_travel_admin'] = true;
			\Courtneyr\Child\Travel\enqueue();
			cr_travel_same( 'courtneyr-travel', $GLOBALS['cr_travel_enqueued'][0][0] ?? '', 'editor handle' );

			$GLOBALS['cr_travel_enqueued'] = array();
			$GLOBALS['cr_travel_admin'] = false;
			\Courtneyr\Child\Travel\enqueue();
			cr_travel_same( array(), $GLOBALS['cr_travel_enqueued'], 'unrelated front-end request does not load the sheet' );
			$GLOBALS['cr_travel_stream'] = true;
			$GLOBALS['cr_travel_singular'] = true;
		}
	);

	cr_travel_check(
		'Stream trip and itinerary cards print one public link and omit sentinels',
		static function (): void {
			$trip = cr_travel_post( 244, 'trip' );
			$card = '<article class="pk-card"><h3 class="pk-title">Old</h3><img src="sentinel.jpg"></article>';
			$html = \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => $trip->ID ) ) );
			foreach ( array( 'cr-travel-tag--trip', 'Travel log', 'Night train to Rainport', 'Pine Harbor', 'Rainport', 'June 11–14, 2026', 'screen-reader-text"> to ' ) as $token ) {
				cr_travel_contains( $token, $html, 'trip Stream token' );
			}
			cr_travel_same( 1, substr_count( $html, '<a ' ), 'trip Stream link count' );
				foreach ( array( 'Sentinel Quay', '1 Sentinel Way', 'sentinel.example', '0.244244', '0.442442', 'Sentinel Air', '<img' ) as $sentinel ) {
				cr_travel_not_contains( $sentinel, $html, 'trip Stream sentinel' );
			}

			$itinerary = cr_travel_post( 245, 'itinerary', array( 'count' => 4 ) );
			$ticket = \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => $itinerary->ID ) ) );
			cr_travel_contains( 'cr-travel-ticket--itinerary', $ticket, 'itinerary ticket object' );
			cr_travel_contains( '4 legs', $ticket, 'itinerary leg count' );
			cr_travel_same( 1, substr_count( $ticket, '<a ' ), 'itinerary Stream link count' );
				cr_travel_not_contains( 'Sentinel Air', $ticket, 'itinerary private sentinel' );

				$title_only = cr_travel_post( 246, 'trip', array( 'origin' => '', 'destination' => '', 'range' => array( 'display' => '' ), 'count' => 0 ) );
				$title_html = \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => $title_only->ID ) ) );
				cr_travel_contains( 'Night train to Rainport', $title_html, 'zero-count card keeps its title' );
				cr_travel_not_contains( 'cr-travel-card__route', $title_html, 'zero-count card has no route' );
				cr_travel_not_contains( 'cr-travel-card__range', $title_html, 'zero-count card has no range' );

				$one_stop = cr_travel_post( 247, 'trip', array( 'origin' => 'Pine Harbor', 'destination' => '', 'range' => array( 'display' => 'June 11, 2026' ), 'count' => 1 ) );
				$one_stop_html = \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => $one_stop->ID ) ) );
				cr_travel_contains( 'Pine Harbor', wp_json_encode( $GLOBALS['cr_travel_facts'][247] ), 'one-stop allowed value exists in facts' );
				cr_travel_not_contains( 'cr-travel-card__route', $one_stop_html, 'one-stop trip has no route' );

				$one_leg = cr_travel_post( 248, 'itinerary', array( 'count' => 1 ) );
				$one_leg_html = \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => $one_leg->ID ) ) );
				cr_travel_contains( '<h3 class="cr-travel-card__title">', $one_leg_html, 'incoming H3 level is preserved' );
				cr_travel_contains( '1 leg', $one_leg_html, 'singular leg count' );

				$untitled = cr_travel_post( 249, 'trip', array( 'origin' => '', 'destination' => '', 'range' => array( 'display' => '' ), 'count' => 0 ) );
				$untitled->post_title = '';
				$untitled_html = \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => $untitled->ID ) ) );
				cr_travel_contains( 'Untitled travel post', $untitled_html, 'untitled fallback' );
				cr_travel_contains( 'class="u-url"', $untitled_html, 'untitled link omits p-name' );

				$hidden = cr_travel_post( 250, 'trip' );
				$GLOBALS['cr_travel_hidden'][250] = true;
				$hidden_html = \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => $hidden->ID ) ) );
				cr_travel_contains( 'class="u-url"', $hidden_html, 'hidden title link omits p-name' );
				$GLOBALS['cr_travel_hidden'][250] = false;

			$protected = '<article class="pk-card pk-card--protected">Protected</article>';
			cr_travel_same( $protected, \Courtneyr\Child\StreamTravel\travel_card( $protected, array(), new WP_Block( array( 'postId' => 244 ) ) ), 'protected class passes through' );
			$GLOBALS['cr_travel_passwords'][244] = true;
			cr_travel_same( $card, \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => 244 ) ) ), 'password-required post passes through' );
			$GLOBALS['cr_travel_passwords'][244] = false;
			$GLOBALS['cr_travel_terms'][244] = array( 'note' );
			cr_travel_same( $card, \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => 244 ) ) ), 'other kind passes through' );
			$GLOBALS['cr_travel_terms'][244] = array( 'trip' );
			$GLOBALS['cr_travel_facts'][244] = array();
			cr_travel_same( $card, \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => 244 ) ) ), 'empty facts pass through' );
			$GLOBALS['cr_travel_stream'] = false;
			cr_travel_same( $card, \Courtneyr\Child\StreamTravel\travel_card( $card, array(), new WP_Block( array( 'postId' => 245 ) ) ), 'non-Stream surface passes through' );
			$GLOBALS['cr_travel_stream'] = true;
		}
	);

	cr_travel_check(
		'templates and patterns parse with map, query, pager and fallback contracts',
		static function (): void {
			$parser = new WP_Block_Parser();
			foreach ( array( 'trip' => 244, 'itinerary' => 245 ) as $kind => $query_id ) {
				$template = (string) file_get_contents( CR_TRAVEL_THEME_DIR . '/templates/taxonomy-kind-' . $kind . '.html' );
				$blocks   = $parser->parse( $template );
				cr_travel_same( 'core/group', $blocks[0]['blockName'] ?? null, $kind . ' template starts with header group' );
				cr_travel_contains( 'cr-archive cr-archive--' . $kind, $template, $kind . ' archive class' );
				cr_travel_contains( 'courtneyr-child/cr-' . $kind . '-', $template, $kind . ' pattern reference' );

				$file = 'trip' === $kind ? 'cr-trip-passport-loop.php' : 'cr-itinerary-wallet-loop.php';
				ob_start();
				require CR_TRAVEL_THEME_DIR . '/patterns/' . $file;
				$pattern = (string) ob_get_clean();
				$parsed  = $parser->parse( $pattern );
				cr_travel_same( 'core/query', $parsed[0]['blockName'] ?? null, $kind . ' pattern starts with Query' );
				foreach ( array( '"queryId":' . $query_id, '"perPage":12', '"inherit":true', 'cr-travel', 'post-kinds-indieweb/travel-map', 'cr-travel__entries', '"linesPerPage":12', '"headingLevel":2', 'wp:query-pagination', 'wp:query-no-results' ) as $token ) {
					cr_travel_contains( $token, $pattern, $kind . ' pattern token' );
				}
			}

			WP_Block_Type_Registry::get_instance()->registered = false;
			ob_start();
			require CR_TRAVEL_THEME_DIR . '/patterns/cr-trip-passport-loop.php';
			$fallback = (string) ob_get_clean();
			cr_travel_same( 'core/pattern', $parser->parse( $fallback )[0]['blockName'] ?? null, 'fallback parses as pattern block' );
			cr_travel_contains( 'courtneyr-child/cr-archive-stream-loop', $fallback, 'fallback pattern' );
		}
	);

	cr_travel_check(
		'CSS budget, specificity, theme states, fidelity and performance exclusions are explicit',
		static function (): void {
			cr_travel_same( array( 'existing.css', 'cr-travel.css', 'travel-archive.css', 'travel-card.css' ), \Courtneyr\Child\Travel\rucss_exclusions( array( 'existing.css' ) ), 'RUCSS exclusions' );
			foreach ( array( 'travel.php', 'stream-travel.php' ) as $file ) {
				cr_travel_assert( ! str_contains( (string) file_get_contents( CR_TRAVEL_THEME_DIR . '/inc/' . $file ), 'preg_' ), $file . ' adds no preg_' );
			}
			$css = (string) file_get_contents( CR_TRAVEL_THEME_DIR . '/assets/css/cr-travel.css' );
			cr_travel_assert( strlen( $css ) <= 30 * 1024, 'cr-travel.css stays within 30 KB' );
			cr_travel_contains( ':root:not([data-theme="light"]) .cr-archive--trip', $css, 'system dark trip selector' );
			cr_travel_contains( ':root[data-theme="dark"] .cr-archive--trip', $css, 'manual dark trip selector' );
			cr_travel_contains( ':root:not([data-theme="light"]) .cr-archive--itinerary', $css, 'system dark itinerary selector' );
			cr_travel_contains( ':root[data-theme="dark"] .cr-archive--itinerary', $css, 'manual dark itinerary selector' );
			cr_travel_contains( ':where(.cr-archive--trip', $css, 'trip defaults have zero specificity' );
			cr_travel_contains( ':where(.cr-archive--itinerary', $css, 'itinerary defaults have zero specificity' );
				cr_travel_contains( '@property --cr-travel-focus-halo', $css, 'focus halo is registered' );
				cr_travel_contains( 'inherits: false;', $css, 'focus halo does not inherit into pins and chips' );
				cr_travel_contains( '--cr-travel-focus-halo: 0 0 0 3px var(--cr-russian-violet), 0 0 0 7px var(--cr-printer-ivory);', $css, 'light paper gets a dark inner ring and page-contrast halo' );
			cr_travel_contains( 'var(--cr-travel-focus-halo, 0 0 0 0 transparent)', $css, 'physical objects append the focus halo' );
			cr_travel_contains( '.cr-travel > .pkiw-travel-map', $css, 'archive map grid is scoped to the travel group' );
				cr_travel_contains( '.pkiw-trip > .pkiw-travel-map', $css, 'single trip map shares the themed map scope' );
				foreach ( array( '.cr-stream-page .pk-card.cr-travel-tag--trip {', '.cr-stream-page .pk-card.cr-travel-ticket--itinerary {' ) as $selector ) {
					$start = strpos( $css, $selector );
					cr_travel_assert( false !== $start, 'missing winning Stream selector ' . $selector );
					if ( false !== $start ) {
						cr_travel_contains( 'border: 2px solid var(--cr-russian-violet);', substr( $css, $start, 900 ), $selector . ' restores border after reset' );
						cr_travel_contains( 'background:', substr( $css, $start, 900 ), $selector . ' restores background after reset' );
						cr_travel_contains( 'box-shadow:', substr( $css, $start, 900 ), $selector . ' restores shadow after reset' );
					}
				}
				cr_travel_contains( ':is(.cr-archive--trip, .pkiw-trip, .pkiw-itinerary) .cr-stamp > path', $css, 'travel-owned stamp paint reaches archive and both single roots' );
				cr_travel_contains( 'stroke: var(--cr-stamp-ink, #241c4a);', $css, 'stamp paint supplies a stroke' );
				cr_travel_contains( '.pkiw-trip-entry.is-style-cr-passport', $css, 'passport block style has paint' );
				cr_travel_contains( '.pkiw-itinerary-entry.is-style-cr-wallet', $css, 'wallet block style has paint' );
				cr_travel_contains( 'grid-template-rows: auto 1fr;', $css, 'archive summary stays directly below map' );
				cr_travel_contains( 'stroke: CanvasText;', $css, 'forced colors restores route stroke' );
				cr_travel_not_contains( '.pkiw-travel-map__viewport', $css, 'no selector for nonexistent map viewport' );
				cr_travel_not_contains( '.pkiw-itinerary__label', $css, 'no selector for nonexistent itinerary label' );
				cr_travel_not_contains( '--cr-travel-edge', $css, 'no unused travel edge token' );
				cr_travel_not_contains( '--cr-sticky-header-clearance', $css, 'no undefined sticky-header token' );
			cr_travel_contains( '@media (forced-colors: active)', $css, 'forced colors' );
			cr_travel_contains( '@media (prefers-reduced-motion: reduce)', $css, 'reduced motion' );
				cr_travel_contains( '.leaflet-zoom-animated', $css, 'reduced motion disables Leaflet zoom transitions' );
				cr_travel_not_contains( 'transition-duration: .01ms', $css, 'no vacuous transition rule on static paper objects' );
				$checkin = (string) file_get_contents( CR_TRAVEL_THEME_DIR . '/assets/css/cr-checkin-archive.css' );
				$matches = array();
				cr_travel_assert( 1 === preg_match( '/@media \(min-width: ([0-9.]+rem)\)/', $checkin, $matches ), 'check-in breakpoint can be read' );
				if ( isset( $matches[1] ) ) {
					cr_travel_contains( '@media (min-width: ' . $matches[1] . ')', $css, 'archive map breakpoint matches check-in source' );
				}
			$pruning = (string) file_get_contents( CR_TRAVEL_THEME_DIR . '/inc/stream-style-pruning.php' );
			cr_travel_contains( "'courtneyr-travel',", $pruning, 'theme travel sheet is inlined on Stream' );
			$stream = (string) file_get_contents( CR_TRAVEL_THEME_DIR . '/inc/stream-travel.php' );
			cr_travel_contains( '\\PKIW\\get_kind_label( $fallback, $kind, \'stream-card\' )', $stream, 'Stream kind label uses the plugin source' );
		}
	);

	if ( $GLOBALS['cr_travel_failures'] ) {
		foreach ( $GLOBALS['cr_travel_failures'] as $failure ) {
			fwrite( STDERR, '  ' . $failure . "\n" );
		}
		exit( 1 );
	}
	echo "all passed\n";
}
