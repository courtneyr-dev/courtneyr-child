<?php
/**
 * Shared standalone support for the review archive, single and Stream tests.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_REVIEW_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_review_hooks']       = array();
	$GLOBALS['cr_review_enqueued']    = array();
	$GLOBALS['cr_review_failures']    = array();
	$GLOBALS['cr_review_request']     = array();
	$GLOBALS['cr_review_post']        = null;
	$GLOBALS['cr_review_posts']       = array();
	$GLOBALS['cr_review_facts']       = array();
	$GLOBALS['cr_review_pictures']    = array();
	$GLOBALS['cr_review_attachments'] = array();
	$GLOBALS['cr_review_no_plugin']   = in_array( '--no-plugin', $argv ?? array(), true );

	class WP_Post {
		public int $ID;
		public string $post_title;
		public string $post_content;
		public string $post_name;

		/** Build a small post stand-in. */
		public function __construct( int $id = 42, string $title = 'Review title', string $content = '' ) {
			$this->ID           = $id;
			$this->post_title   = $title;
			$this->post_content = $content;
			$this->post_name    = sanitize_title( $title );
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

	/** Record a hook callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_review_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_review_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Stub: block styles are irrelevant here. */
	function register_block_style( $block_name, $args ): void {}

	/** Current admin flag. */
	function is_admin(): bool {
		return ! empty( $GLOBALS['cr_review_request']['admin'] );
	}

	/** Current taxonomy flag. */
	function is_tax( $taxonomy = '', $term = '' ): bool {
		$current = (string) ( $GLOBALS['cr_review_request']['kind_archive'] ?? '' );
		if ( '' === $current || ( '' !== $taxonomy && 'kind' !== $taxonomy ) ) {
			return false;
		}
		return '' === $term || in_array( $current, (array) $term, true );
	}

	/** Current singular flag. */
	function is_singular( $post_type = '' ): bool {
		if ( ! empty( $post_type ) && 'post' !== $post_type ) {
			return false;
		}
		return ! empty( $GLOBALS['cr_review_request']['single'] );
	}

	/** Current kind membership. */
	function has_term( $term, $taxonomy, $post = null ): bool {
		if ( 'kind' !== $taxonomy ) {
			return false;
		}
		$id    = cr_review_post_id( $post );
		$kinds = $GLOBALS['cr_review_request']['post_kinds'][ $id ] ?? array( $GLOBALS['cr_review_request']['post_kind'] ?? 'review' );
		return in_array( (string) $term, array_map( 'strval', (array) $kinds ), true );
	}

	/** Queried post ID. */
	function get_queried_object_id(): int {
		return $GLOBALS['cr_review_post'] instanceof WP_Post ? $GLOBALS['cr_review_post']->ID : (int) ( $GLOBALS['cr_review_request']['queried_id'] ?? 0 );
	}

	/** Fetch a post stand-in. */
	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = is_numeric( $post ) ? (int) $post : get_queried_object_id();
		if ( isset( $GLOBALS['cr_review_posts'][ $id ] ) ) {
			return $GLOBALS['cr_review_posts'][ $id ];
		}
		return $GLOBALS['cr_review_post'] instanceof WP_Post ? $GLOBALS['cr_review_post'] : null;
	}

	/** Current loop post ID. */
	function get_the_ID(): int {
		return (int) ( $GLOBALS['cr_review_request']['the_id'] ?? get_queried_object_id() );
	}

	/** Password protection flag. */
	function post_password_required( $post = null ): bool {
		return ! empty( $GLOBALS['cr_review_request']['protected'][ cr_review_post_id( $post ) ] );
	}

	/** Record an enqueued style. */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ): void {
		$GLOBALS['cr_review_enqueued'][] = array( $handle, $src, $deps, $version );
	}

	/** Escape text. */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape an attribute. */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Escape a URL. */
	function esc_url( $url ): string {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}

	/** Translate text. */
	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	/** Translate and escape text. */
	function esc_html__( $text, $domain = null ): string {
		return esc_html( $text );
	}

	/** Translate, escape and print text. */
	function esc_html_e( $text, $domain = null ): void {
		echo esc_html( $text );
	}

	/** Return attachment HTML from the attrs supplied by the theme. */
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ): string {
		if ( isset( $GLOBALS['cr_review_attachments'][ (int) $attachment_id ] ) && '' === $GLOBALS['cr_review_attachments'][ (int) $attachment_id ] ) {
			return '';
		}
		$attrs = array_merge(
			array( 'src' => $GLOBALS['cr_review_attachments'][ (int) $attachment_id ] ?? 'https://example.test/attachment-' . (int) $attachment_id . '.jpg' ),
			(array) $attr
		);
		$out   = '<img';
		foreach ( $attrs as $name => $value ) {
			$out .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out . ' />';
	}

	/** Test permalink. */
	function get_permalink( $post = 0 ): string {
		return 'https://example.test/review/' . cr_review_post_id( $post ) . '/';
	}

	/** Strip tags. */
	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}

	/** Slugify text. */
	function sanitize_title( $title ): string {
		return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $title ) ), '-' );
	}

	/** Reset request state. */
	function cr_review_reset(): WP_Post {
		$post                                  = new WP_Post();
		$GLOBALS['cr_review_request']          = array(
			'kind_archive' => '',
			'single'       => false,
			'stream'       => false,
			'admin'        => false,
			'post_kind'    => 'review',
			'post_kinds'   => array( $post->ID => array( 'review' ) ),
			'protected'    => array(),
			'the_id'       => $post->ID,
			'queried_id'   => $post->ID,
		);
		$GLOBALS['cr_review_post']             = $post;
		$GLOBALS['cr_review_posts']            = array( $post->ID => $post );
		$GLOBALS['cr_review_facts']            = array( $post->ID => cr_review_default_facts() );
		$GLOBALS['cr_review_pictures']         = array();
		$GLOBALS['cr_review_attachments']      = array();
		$GLOBALS['cr_review_enqueued']         = array();
		return $post;
	}

	/** Default review facts. */
	function cr_review_default_facts(): array {
		return array(
			'rating'       => 4.0,
			'best'         => 5,
			'item_name'    => 'The City We Became',
			'item_url'     => 'https://example.test/item',
			'rating_label' => 'Rated 4 of 5',
			'stars'        => array( 'value' => 4.0, 'best' => 5, 'full' => 4, 'half' => false, 'empty' => 1 ),
			'pullquote'    => 'A messy love letter to a city that fights back.',
			'category'     => array( 'id' => 7, 'name' => 'Books', 'slug' => 'books', 'link' => 'https://example.test/category/books/' ),
		);
	}

	/** Resolve a post ID from any accepted post value. */
	function cr_review_post_id( $post = null ): int {
		if ( $post instanceof WP_Post ) {
			return $post->ID;
		}
		if ( is_numeric( $post ) && (int) $post > 0 ) {
			return (int) $post;
		}
		return get_queried_object_id();
	}

	/** Print one grouped result. */
	function cr_review_group( string $name, callable $checks ): void {
		$before = count( $GLOBALS['cr_review_failures'] );
		try {
			$checks();
		} catch ( \Throwable $error ) {
			$GLOBALS['cr_review_failures'][] = $name . ': ' . $error->getMessage();
		}
		$ok = $before === count( $GLOBALS['cr_review_failures'] );
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	}

	/** Record an assertion. */
	function cr_review_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			$GLOBALS['cr_review_failures'][] = $message;
		}
	}

	/** Assert strict equality. */
	function cr_review_same( $expected, $actual, string $message ): void {
		cr_review_assert( $expected === $actual, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	/** Assert a string contains a substring. */
	function cr_review_contains( string $needle, string $haystack, string $message ): void {
		cr_review_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
	}

	/** Assert a string does not contain a substring. */
	function cr_review_not_contains( string $needle, string $haystack, string $message ): void {
		cr_review_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
	}

	/** Run callbacks registered to one hook. */
	function cr_review_fire_hook( string $hook, $value = null, ...$args ) {
		$result = $value;
		foreach ( $GLOBALS['cr_review_hooks'] as $registered ) {
			if ( $registered[0] !== $hook ) {
				continue;
			}
			$callback = $registered[1];
			$accepted = (int) $registered[3];
			$params   = array_slice( array_merge( array( $result ), $args ), 0, $accepted );
			$result   = call_user_func_array( $callback, $params );
		}
		return $result;
	}

	/** Finish with a summary and exit code. */
	function cr_review_finish(): void {
		if ( $GLOBALS['cr_review_failures'] ) {
			foreach ( $GLOBALS['cr_review_failures'] as $failure ) {
				fwrite( STDERR, '  ' . $failure . "\n" );
			}
			echo count( $GLOBALS['cr_review_failures'] ) . " failed\n";
			exit( 1 );
		}
		echo "all passed\n";
		exit( 0 );
	}

	$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
	$api  = $core . '/wp-includes/html-api';
	if ( ! is_dir( $api ) ) {
		echo "SKIP: set CR_WP_CORE to a WordPress checkout\n";
		exit( 0 );
	}
	require_once $core . '/wp-includes/compat.php';
	require_once $core . '/wp-includes/utf8.php';
	require_once $core . '/wp-includes/kses.php';
	foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
		require_once $api . '/' . $file;
	}

	cr_review_reset();
}

namespace PKIW {
	if ( empty( $GLOBALS['cr_review_no_plugin'] ) ) {
		/** Plugin review facts stand-in. */
		function kind_facts( int $post_id ): array {
			return $GLOBALS['cr_review_facts'][ $post_id ] ?? array();
		}

		/** Plugin picture stand-in. */
		function kind_picture( int $post_id ): array {
			return $GLOBALS['cr_review_pictures'][ $post_id ] ?? array( 'source' => '', 'attachment_id' => 0, 'url' => '', 'alt' => '', 'remote' => false, 'suppress_featured' => false );
		}

		/** Rating markup stand-in. */
		class Review_Archive {
			/** Build realistic review rating markup. */
			public static function review_rating_html( array $facts ): string {
				$rating = (float) ( $facts['rating'] ?? 0 );
				if ( $rating <= 0 ) {
					return '';
				}
				$best  = (int) ( $facts['best'] ?? 5 );
				$label = (string) ( $facts['rating_label'] ?? card_rating_label( $rating, $best ) );
				$stars = card_star_counts( $rating, $best );
				$path  = 'M12 2l3 6.5 7 .6-5.3 4.6 1.6 6.8L12 17l-6.9 3.5 1.6-6.8L1.4 9.1l7-.6z';
				$out   = '<p class="pkiw-review-rating"><span class="pkiw-review-rating__label">' . \esc_html( $label ) . '</span> <span class="pk-stars" aria-hidden="true">';
				$out  .= str_repeat( '<svg class="" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="' . $path . '"/></svg>', (int) $stars['full'] );
				if ( ! empty( $stars['half'] ) ) {
					$out .= '<svg class="half" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path class="off" d="' . $path . '"/><path d="' . $path . '" style="clip-path:inset(0 50% 0 0)"/></svg>';
				}
				$out .= str_repeat( '<svg class="off" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="' . $path . '"/></svg>', (int) $stars['empty'] );
				return $out . '</span></p>';
			}
		}

		/** Card star count stand-in. */
		function card_star_counts( float $rating, int $best = 5 ): array {
			$value = max( 0.0, min( (float) $best, $rating ) );
			$full  = (int) floor( $value );
			$half  = ( $value - $full ) >= 0.5;
			return array( 'value' => $value, 'best' => $best, 'full' => $full, 'half' => $half, 'empty' => max( 0, $best - $full - ( $half ? 1 : 0 ) ) );
		}

		/** Card rating label stand-in. */
		function card_rating_label( float $rating, int $best = 5 ): string {
			$value = card_star_counts( $rating, $best )['value'];
			return $value > 0 ? 'Rated ' . rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) . ' of ' . $best : '';
		}
	}
}

namespace Courtneyr\Child\HomeSections {
	/** Current stream surface flag. */
	function is_stream_surface(): bool {
		return ! empty( $GLOBALS['cr_review_request']['stream'] );
	}
}

namespace {
	require CR_REVIEW_THEME_DIR . '/inc/media-shelf.php';
	require CR_REVIEW_THEME_DIR . '/inc/stream-read.php';
	require CR_REVIEW_THEME_DIR . '/inc/review.php';
	require CR_REVIEW_THEME_DIR . '/inc/single-review.php';
	require CR_REVIEW_THEME_DIR . '/inc/stream-review.php';
}
