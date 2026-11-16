<?php
/**
 * `php tests/php/tag-purge-test.php`: series and collection edge purge paths (A-T6, C15, finding 6).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	require __DIR__ . '/tag-bootstrap.php';
	require CR_TAG_THEME_DIR . '/inc/stamps.php';
	require CR_TAG_THEME_DIR . '/inc/tag.php';

	use Courtneyr\Child\Tag;

	/** A `$wpdb` stub that answers the collection scan from post content. */
	class CR_Tag_Wpdb {
		public string $posts = 'wp_posts';
		/** @var array<int, array{status: string, content: string}> */
		public array $rows = array();
		public array $queries = array();

		public function esc_like( string $text ): string {
			return addcslashes( $text, '_%\\' );
		}

		public function prepare( string $query, ...$args ): string {
			$this->queries[] = array( $query, $args );
			return $query;
		}

		public function get_col( string $query ): array {
			list( , $args ) = end( $this->queries );
			$like           = static fn( string $pattern ): string => stripcslashes( trim( $pattern, '%' ) );
			$ids            = array();
			foreach ( $this->rows as $id => $row ) {
				if ( 'publish' !== $row['status'] ) {
					continue;
				}
				$content = $row['content'];
				if ( str_contains( $content, $like( $args[0] ) ) && ( str_contains( $content, $like( $args[1] ) ) || str_contains( $content, $like( $args[2] ) ) ) ) {
					$ids[] = (string) $id;
				}
			}
			return $ids;
		}
	}

	$GLOBALS['wpdb'] = new CR_Tag_Wpdb();
	$GLOBALS['cr_tag_not_public'] = array();

	function is_post_publicly_viewable( $post = null ): bool {
		$id = $post instanceof WP_Post ? $post->ID : (int) $post;
		return ! in_array( $id, $GLOBALS['cr_tag_not_public'], true );
	}

	$base = array(
		'urls'  => array( 'https://example.test/tag-301/', 'https://example.test/kind/tag/' ),
		'regex' => array( '^/kind/tag/page/' ),
	);

	cr_tag_check(
		'without the plugin class the paths pass through unchanged',
		static function () use ( $base ): void {
			cr_tag_same( $base, Tag\purge_urls( $base, new WP_Post( 301 ) ), 'no PKIW' );
		}
	);
}

namespace PKIW {
	if ( ! class_exists( '\\PKIW\\Public_Eligibility' ) ) {
		/** Minimal stand-in: only its presence matters to purge_urls(). */
		final class Public_Eligibility {
		}
	}
}

namespace {
	use Courtneyr\Child\Tag;

	cr_tag_check(
		'purge_urls is registered on the plugin filter at 10 with two arguments',
		static function (): void {
			$hook = cr_tag_hook( 'pkiw_public_eligibility_purge_urls', 'Courtneyr\\Child\\Tag\\purge_urls' );
			cr_tag_assert( null !== $hook, 'registered' );
			cr_tag_same( 10, $hook[2] ?? 0, 'priority' );
			cr_tag_same( 2, $hook[3] ?? 0, 'accepted args' );
		}
	);

	cr_tag_check(
		'a post in a series and one collection adds the series link, its page regex and the collection permalink',
		static function () use ( $base ): void {
			$GLOBALS['cr_tag_terms']['series'][301] = array( new WP_Term( 41, 'example-series', 'Example series' ) );
			$GLOBALS['cr_tag_term_links']           = array( 'example-series' => 'https://example.test/series/example-series/' );
			$GLOBALS['wpdb']->rows                  = array(
				401 => array(
					'status'  => 'publish',
					'content' => '<!-- wp:post-kinds-indieweb/collection-item {"postId":301} /--><!-- wp:post-kinds-indieweb/collection-item {"postId":3010,"title":"x"} /-->',
				),
				402 => array(
					'status'  => 'draft',
					'content' => '<!-- wp:post-kinds-indieweb/collection-item {"postId":301} /-->',
				),
				403 => array(
					'status'  => 'publish',
					'content' => '<!-- wp:post-kinds-indieweb/collection-item {"postId":3011} /-->',
				),
				404 => array(
					'status'  => 'publish',
					'content' => '<!-- wp:post-kinds-indieweb/collection-item {"postId":301,"title":"Kept"} /-->',
				),
			);
			$out = Tag\purge_urls( $base, new WP_Post( 301 ) );
			cr_tag_same(
				array(
					'urls'  => array(
						'https://example.test/tag-301/',
						'https://example.test/kind/tag/',
						'https://example.test/series/example-series/',
						'https://example.test/tag-401/',
						'https://example.test/tag-404/',
					),
					'regex' => array( '^/kind/tag/page/', '^/series/example-series/page/' ),
				),
				$out,
				'added paths'
			);
		}
	);

	cr_tag_check(
		'a non-public post holding the block (a synced pattern) is not added',
		static function () use ( $base ): void {
			$GLOBALS['wpdb']->rows        = array(
				601 => array(
					'status'  => 'publish',
					'content' => '<!-- wp:post-kinds-indieweb/collection-item {"postId":303} /-->',
				),
				602 => array(
					'status'  => 'publish',
					'content' => '<!-- wp:post-kinds-indieweb/collection-item {"postId":303} /-->',
				),
			);
			$GLOBALS['cr_tag_not_public'] = array( 602 );
			$out                          = Tag\purge_urls( $base, new WP_Post( 303 ) );
			cr_tag_contains( 'https://example.test/tag-601/', implode( ' ', $out['urls'] ), 'public collection kept' );
			cr_tag_not_contains( 'https://example.test/tag-602/', implode( ' ', $out['urls'] ), 'non-public holder dropped' );
			$GLOBALS['cr_tag_not_public'] = array();
		}
	);

	cr_tag_check(
		'a post in neither adds nothing; non-array input passes through',
		static function () use ( $base ): void {
			$GLOBALS['wpdb']->rows = array(
				501 => array(
					'status'  => 'publish',
					'content' => '<!-- wp:post-kinds-indieweb/collection-item {"postId":3020} /-->',
				),
			);
			cr_tag_same( $base, Tag\purge_urls( $base, new WP_Post( 302 ) ), 'nothing added' );
			cr_tag_same( 'x', Tag\purge_urls( 'x', new WP_Post( 302 ) ), 'non-array' );
		}
	);

	cr_tag_finish();
}
