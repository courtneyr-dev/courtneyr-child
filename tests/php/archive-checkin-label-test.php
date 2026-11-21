<?php
/**
 * Exercise the four theme adapters at the proposed PKIW archive seam.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace PKIW {
	function stream_card_kind_cite( \WP_Post $post, ?array $facts = null, string $kind = '' ): string {
		if ( null === $facts ) {
			$facts = kind_facts( $post->ID );
			$kind  = $GLOBALS['cr_jam_request']['post_kinds'][ $post->ID ][0];
		}
		$url = $facts['url'] ?? '';
		return '' === $url ? '' : '<span class="h-cite u-' . esc_attr( $kind ) . '-of" hidden><data class="u-url" value="' . esc_url( $url ) . '"></data></span>';
	}

	function visitor_post_title( \WP_Post $post ): string {
		return $post->safe_title;
	}

	function untitled_name( \WP_Post $post, bool $from_content = true ): string {
		return $from_content ? 'Foreign body sentinel' : 'Untitled fixture';
	}
}

namespace {
	use Courtneyr\Child\ArchiveCheckin;
	use Courtneyr\Child\Favorite;
	use Courtneyr\Child\Like;
	use Courtneyr\Child\Repost;
	use PKIW\Meta_Fields;
	use PKIW\Public_Eligibility;

	require __DIR__ . '/jam-bootstrap.php';
	require CR_JAM_THEME_DIR . '/inc/like.php';
	require CR_JAM_THEME_DIR . '/inc/repost.php';
	require CR_JAM_THEME_DIR . '/inc/favorite.php';

	const CR_ARCHIVE_CARDS = array(
		'like'     => array( 'cr-like-sticker', 'cr-like-sticker__checkin', Like\ITEM_CLASS, 'pk-caption' ),
		'repost'   => array( 'cr-repost-clip', 'cr-repost-clip__checkin', Repost\ITEM_CLASS, 'cr-repost-clip__paper' ),
		'favorite' => array( 'cr-fav-item', 'cr-fav-clipping__checkin', Favorite\ITEM_CLASS, 'cr-fav-clipping' ),
		'jam'      => array( 'cr-jam-strip--listing', 'cr-jam-strip__checkin', '', 'cr-jam-strip--listing' ),
	);

	final class CR_Archive_Post extends WP_Post {
		public string $post_status = 'publish';
		public string $post_password = '';
		public string $safe_title = 'Selected title';
	}

	function get_the_title( $post ): string {
		return get_post( $post )->post_title;
	}

	function get_the_date( $format, $post ): string {
		return 'October 17, 2026';
	}

	function get_bloginfo( $field ): string {
		return 'name' === $field ? 'Fixture site' : $GLOBALS['cr_archive_language'];
	}

	function get_post_field( $field, $post ): int {
		return 0;
	}

	function get_the_author_meta( $field, $author ): string {
		return '';
	}

	function get_site_icon_url( $size ): string {
		return '';
	}

	function home_url( $path ): string {
		return 'https://example.test' . $path;
	}

	function get_post_meta( $post_id, $key, $single ) {
		return $GLOBALS['cr_archive_meta'][ $post_id ][ $key ] ?? '';
	}

	function is_post_publicly_viewable( WP_Post $post ): bool {
		return 'publish' === $post->post_status;
	}

	function cr_archive_fixture( string $kind, array $terms, bool $locked = false ): CR_Archive_Post {
		static $id = 900;
		cr_jam_reset( ++$id );
		$post = new CR_Archive_Post( $id, 'Selected title' );
		$GLOBALS['cr_jam_posts'][ $id ]                  = $post;
		$GLOBALS['cr_jam_request']['kind_archive']       = $kind;
		$GLOBALS['cr_jam_request']['post_kinds'][ $id ]  = $terms;
		$GLOBALS['cr_jam_request']['protected'][ $id ]   = $locked;
		$GLOBALS['cr_jam_facts'][ $id ] = array(
			'name'        => 'Legacy title',
			'cited_title' => 'Legacy title',
			'url'         => 'https://source.example/legacy',
		);
		$GLOBALS['cr_archive_facts'][ $id ] = array(
			'name'        => 'Selected title',
			'cited_title' => 'Selected title',
			'author'      => 'Selected credit',
			'url'         => 'https://source.example/selected',
			'picture'     => array( 'url' => 'https://source.example/selected.jpg' ),
		);
		$GLOBALS['cr_archive_language'] = 'en-US';
		$GLOBALS['wp_query'] = (object) array( 'posts' => array( $post ), 'current_post' => 0 );
		Favorite\last_tilt( '' );
		return $post;
	}

	function cr_archive_card( string $kind, CR_Archive_Post $post, bool $shell, bool $sparse ): string {
		$link = '<a class="u-url" href="' . esc_url( get_permalink( $post ) ) . '">';
		if ( $shell ) {
			return '<article class="pk-card pk-card--protected h-entry k-' . $kind . '"><h3 class="pk-title">' . $link . 'Safe shell</a></h3></article>';
		}

		$title = esc_html( '' !== $post->safe_title ? $post->safe_title : 'Untitled fixture' );
		$facts = $sparse ? array() : $GLOBALS['cr_archive_facts'][ $post->ID ];
		$cite  = PKIW\stream_card_kind_cite( $post, $facts, $kind );
		if ( 'jam' === $kind ) {
			$title_id  = 'pkiw-jam-t-' . $post->ID;
			$artist_id = 'pkiw-jam-a-' . $post->ID;
			$names     = $title_id . ( $sparse ? '' : ' ' . $artist_id );
			$cover     = $sparse ? '' : '<div class="pk-media"><img class="pk-cover" src="https://source.example/selected.jpg" alt=""></div>';
			$artist    = $sparse ? '' : '<p class="pk-sub" id="' . $artist_id . '">Selected artist</p>';
			return '<article class="pk-card pk-card--jam-listing h-entry k-jam">' . $cover
				. '<h3 class="pk-title"><a class="u-url" href="' . get_permalink( $post ) . '" aria-labelledby="' . $names . '">'
				. '<span id="' . $title_id . '">' . $title . '</span></a></h3>' . $artist . $cite . '</article>';
		}

		return '<article class="pk-card pk-card--stream k-' . $kind . '"><div class="pk-body"><div class="pk-caption">'
			. '<h3 class="pk-title">' . $link . $title . '</a></h3></div></div>' . $cite . '</article>';
	}

	function cr_archive_render( string $kind, CR_Archive_Post $post, string $card, ?string $frame_kind = null, array $peers = array() ): string {
		$instance = new WP_Block( array( 'postId' => $post->ID ) );
		$GLOBALS['cr_archive_frame'] = array(
			'kind'     => $frame_kind ?? $kind,
			'block'    => $instance,
			'page_ids' => array_merge( array( $post->ID ), $peers ),
		);
		$html = apply_filters(
			'render_block_post-kinds-indieweb/stream-card',
			$card,
			array( 'attrs' => array( 'className' => CR_ARCHIVE_CARDS[ $kind ][2], 'headingLevel' => 3 ) ),
			$instance
		);
		$entry = str_contains( $html, 'h-entry' ) ? 'wp-block-post' : 'wp-block-post h-entry';
		return '' === $html ? '' : '<li class="' . $entry . '">' . $html . '</li>';
	}

	function cr_archive_dom( string $html ): DOMXPath {
		$document = new DOMDocument();
		$document->loadHTML( '<meta charset="UTF-8"><ul>' . $html . '</ul>', LIBXML_NOERROR | LIBXML_NOWARNING );
		return new DOMXPath( $document );
	}

	function cr_archive_with_class( string $class ): string {
		return 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")';
	}

	function cr_archive_assert_label( string $html, string $kind, bool $expected, string $case ): void {
		$dom = cr_archive_dom( $html );
		[ $artifact, $class, , $parent ] = CR_ARCHIVE_CARDS[ $kind ];
		$element = $dom->query( '//*[' . cr_archive_with_class( $class ) . ']' );
		cr_jam_same( $expected ? 1 : 0, $element->length, "$kind $case label count" );
		cr_jam_same( 1, $dom->query( '//*[' . cr_archive_with_class( $artifact ) . ']' )->length, "$kind $case keeps one artifact" );
		cr_jam_same( 1, $dom->query( '//li//a' )->length, "$kind $case keeps one link" );
		cr_jam_same( 1, $dom->query( '//h3/a' )->length, "$kind $case keeps one H3 link" );
		if ( ! $expected || 1 !== $element->length ) {
			return;
		}

		$span = $element->item( 0 );
		cr_jam_same( 'span', $span->nodeName, "$kind $case label is a span" );
		cr_jam_same( 'Also a check-in', $span->textContent, "$kind $case label text" );
		cr_jam_contains( $parent, $span->parentNode->getAttribute( 'class' ), "$kind $case label parent" );
		cr_jam_same( 1, $span->attributes->length, "$kind $case label has only its class" );
		$prior = $span->previousSibling;
		cr_jam_assert( 'h3' === $prior->nodeName || ( 'p' === $prior->nodeName && in_array( $kind, array( 'repost', 'jam' ), true ) ), "$kind $case label follows the title or credit" );
		cr_jam_not_contains( 'Also a check-in', $dom->query( '//h3/a' )->item( 0 )->textContent, "$kind $case link name unchanged" );

		$parsed = Mf2\parse( $html, 'https://example.test/' );
		$without = str_replace( '<span class="' . $class . '">Also a check-in</span>', '', $html );
		cr_jam_assert( Mf2\parse( $without, 'https://example.test/' ) === $parsed, "$kind $case mf2 unchanged by the label" );
		cr_jam_same( 1, count( $parsed['items'] ), "$kind $case has one mf2 entry" );
		cr_jam_same( array( 'h-entry' ), $parsed['items'][0]['type'] ?? array(), "$kind $case retains the entry type" );
		$properties = $parsed['items'][0]['properties'];
		cr_jam_same( array( $dom->query( '//h3/a' )->item( 0 )->getAttribute( 'href' ) ), $properties['url'] ?? array(), "$kind $case preserves entry permalink" );
		cr_jam_same( array( '2026-10-17' ), $properties['published'] ?? array(), "$kind $case preserves publication date" );
		cr_jam_same( array( 'Fixture site' ), $properties['author'][0]['properties']['name'] ?? array(), "$kind $case preserves the theme's fallback author" );
	}

	$legacy = array();
	foreach ( array_keys( CR_ARCHIVE_CARDS ) as $kind ) {
		foreach ( array( false, true ) as $shell ) {
			$post = cr_archive_fixture( $kind, array( 'checkin', $kind ), $shell );
			$card = cr_archive_card( $kind, $post, $shell, false );
			$legacy[] = cr_archive_render( $kind, $post, $card );
		}
	}
	$legacy_hash = hash( 'sha256', implode( "\n", $legacy ) );
	if ( in_array( '--legacy-snapshot', $argv, true ) ) {
		echo $legacy_hash . "\n";
		exit;
	}

	cr_jam_group(
		'Older PKIW renders the same eight full and protected cards as the pre-change theme',
		static function () use ( $legacy_hash ): void {
			cr_jam_same( 'd97bbc994f3527c0c29f811c3ae68c203f7f3672b3f3d6ab91b16bf2a49c796f', $legacy_hash, 'no-API HTML matches the pinned theme fixture output' );
		}
	);

	$pkiw = getenv( 'PKIW_DIR' ) ?: (string) getenv( 'HOME' ) . '/projects/post-kinds-for-indieweb';
	$mf2  = getenv( 'MF2_AUTOLOAD' ) ?: $pkiw . '/vendor/autoload.php';
	if ( ! is_file( $pkiw . '/includes/class-public-eligibility.php' ) || ! is_file( $mf2 ) ) {
		fwrite( STDERR, "Set PKIW_DIR and MF2_AUTOLOAD to PKIW's public policy classes and php-mf2.\n" );
		exit( 1 );
	}
	require $pkiw . '/includes/class-taxonomy.php';
	require $pkiw . '/includes/class-public-eligibility.php';
	require $mf2;
	require __DIR__ . '/fixtures/pkiw-mixed-archive-api.php';
	require CR_JAM_THEME_DIR . '/inc/microformats.php';

	// Reproduce the late plugin filter's property placement using the real theme author filter.
	add_filter(
		'render_block_post-kinds-indieweb/stream-card',
		static function ( string $html, array $block, WP_Block $instance ): string {
			if ( '' === $html ) {
				return $html;
			}
			$post = get_post( $instance->context['postId'] );
			$props = '<span class="pk-entry-props" hidden>';
			if ( ! post_password_required( $post ) ) {
				$author = apply_filters( 'pkiw_entry_author', array(), $post );
				$props .= '<span class="p-author h-card"><span class="p-name">' . esc_html( $author['name'] )
					. '</span><data class="u-url" value="' . esc_url( $author['url'] ) . '"></data></span>';
			}
			$props .= '<data class="dt-published" value="2026-10-17"></data></span>';
			return str_contains( $html, 'h-entry' ) ? substr_replace( $html, $props, strrpos( $html, '</article>' ), 0 ) : $html . $props;
		},
		99,
		3
	);

	cr_jam_group(
		'A selected row with no location disclosure API omits the label',
		static function (): void {
			$post = cr_archive_fixture( 'like', array( 'checkin', 'like' ) );
			cr_archive_assert_label( cr_archive_render( 'like', $post, cr_archive_card( 'like', $post, false, false ) ), 'like', false, 'unknown disclosure' );
		}
	);

	require $pkiw . '/includes/class-meta-fields.php';

	foreach ( array_keys( CR_ARCHIVE_CARDS ) as $kind ) {
		$privacy_key = Meta_Fields::PREFIX . 'geo_privacy';
		$locations = array(
			'public' => array( true, array( $privacy_key => 'public', 'geo_public' => '1' ) ),
			'approximate' => array( true, array( $privacy_key => 'approximate' ) ),
			'unset' => array( true, array() ),
			'Simple Location Protected' => array( true, array( $privacy_key => 'public', 'geo_public' => '2' ) ),
			'PKIW private' => array( false, array( $privacy_key => 'private', 'geo_public' => '1' ) ),
			'Simple Location private' => array( false, array( $privacy_key => 'public', 'geo_public' => '0' ) ),
		);
		foreach ( $locations as $case => [ $show, $meta ] ) {
			cr_jam_group(
				"$kind $case",
				static function () use ( $kind, $case, $show, $meta ): void {
					$terms = 'public' === $case ? array( 'checkin', $kind ) : array( $kind, 'checkin' );
					$post  = cr_archive_fixture( $kind, $terms );
					$post->post_title = 'Private venue sentinel';
					$GLOBALS['cr_archive_meta'][ $post->ID ] = $meta;
					$card = cr_archive_card( $kind, $post, false, false );
					$html = cr_archive_render( $kind, $post, $card );
					cr_archive_assert_label( $html, $kind, $show, $case );
					cr_jam_contains( 'Selected title', $html, "$kind $case retains selected title" );
					cr_jam_same( 1, substr_count( $html, 'https://source.example/selected"' ), "$kind $case one selected source URL" );
					cr_jam_not_contains( 'Private venue sentinel', $html, "$kind $case withholds the stored private title" );
					cr_jam_not_contains( 'source.example/legacy', $html, "$kind $case withholds the legacy source" );
				}
			);
		}

		cr_jam_group(
			"$kind stored password with a valid cookie",
			static function () use ( $kind ): void {
				$post = cr_archive_fixture( $kind, array( 'checkin', $kind ) );
				$post->post_password = 'fixture';
				$html = cr_archive_render( $kind, $post, cr_archive_card( $kind, $post, false, false ) );
				cr_archive_assert_label( $html, $kind, false, 'valid cookie' );
				cr_jam_contains( 'Selected title', $html, "$kind valid cookie keeps the full card" );
			}
		);

		foreach ( array( 'absent cookie', 'wrong cookie', 'title-only marker' ) as $case ) {
			cr_jam_group(
				"$kind $case",
				static function () use ( $kind, $case ): void {
					$locked = 'title-only marker' !== $case;
					$post = cr_archive_fixture( $kind, array( 'checkin', $kind ), $locked );
					$post->post_password = $locked ? 'fixture' : '';
					$card = cr_archive_card( $kind, $post, true, false );
					$html = cr_archive_render( $kind, $post, $card );
					cr_jam_contains( 'Safe shell</a>', $html, "$kind $case keeps the visitor-safe title link" );
					cr_jam_same( 1, cr_archive_dom( $html )->query( '//li//a' )->length, "$kind $case keeps one link" );
					cr_jam_not_contains( 'Selected title', $html, "$kind $case withholds full-card facts" );
					cr_jam_not_contains( 'source.example', $html, "$kind $case withholds source facts" );
					cr_jam_not_contains( 'Also a check-in', $html, "$kind $case omits the label" );
					cr_jam_same( '', ArchiveCheckin\label( $post, $GLOBALS['cr_archive_frame']['block'], $kind, $card ), "$kind $case shell never discloses Check-in" );
				}
			);
		}

		foreach ( array( 'hidden', 'draft', 'private' ) as $case ) {
			cr_jam_group(
				"$kind $case",
				static function () use ( $kind, $case ): void {
					$terms = 'hidden' === $case ? array( 'checkin', $kind, 'follow' ) : array( 'checkin', $kind );
					$post = cr_archive_fixture( $kind, $terms );
					$post->post_status   = 'hidden' === $case ? 'publish' : $case;
					$post->post_password = 'hidden' === $case ? 'fixture' : '';
					$html = Public_Eligibility::hides( $post ) || 'publish' !== $post->post_status ? '' : cr_archive_card( $kind, $post, false, false );
					cr_jam_same( '', cr_archive_render( $kind, $post, $html ), "$kind $case keeps the plugin's excluded row empty" );
					$selected_card = cr_archive_card( $kind, $post, false, false );
					cr_jam_same( '', ArchiveCheckin\label( $post, $GLOBALS['cr_archive_frame']['block'], $kind, $selected_card ), "$kind $case rejects disclosure even with card markup" );
				}
			);
		}

		cr_jam_group(
			"$kind sparse selected facts",
			static function () use ( $kind ): void {
				$post = cr_archive_fixture( $kind, array( 'checkin', $kind ) );
				$post->post_title = 'Private venue sentinel';
				$post->safe_title = '';
				$GLOBALS['cr_archive_facts'][ $post->ID ] = array();
				$html = cr_archive_render( $kind, $post, cr_archive_card( $kind, $post, false, true ) );
				cr_archive_assert_label( $html, $kind, true, 'sparse' );
				cr_jam_contains( 'Untitled fixture</a>', str_replace( '</span></a>', '</a>', $html ), "$kind sparse card keeps the safe fallback" );
				cr_jam_not_contains( 'source.example', $html, "$kind sparse card uses no unrelated citation or image" );
			}
		);

		cr_jam_group(
			"$kind outside the matching archive frame or without Check-in",
			static function () use ( $kind ): void {
				$post = cr_archive_fixture( $kind, array( 'checkin', $kind ) );
				$card = cr_archive_card( $kind, $post, false, false );
				$block = new WP_Block( array( 'postId' => $post->ID ) );
				$GLOBALS['cr_archive_frame'] = array( 'kind' => '', 'block' => $block );
				cr_jam_same( '', ArchiveCheckin\label( $post, $block, $kind, $card ), "$kind an unselected row omits the label" );
				$html = cr_archive_render( $kind, $post, $card, '' );
				cr_jam_not_contains( 'Also a check-in', $html, "$kind independent loop on the archive URL omits the label" );
				cr_jam_same( '', ArchiveCheckin\label( $post, new WP_Block(), $kind, $card ), "$kind missing row context omits the label" );
				$post = cr_archive_fixture( $kind, array( $kind ) );
				$html = cr_archive_render( $kind, $post, cr_archive_card( $kind, $post, false, false ) );
				cr_archive_assert_label( $html, $kind, false, 'unmixed' );
			}
		);
	}

	foreach ( array( 'like', 'repost', 'favorite' ) as $kind ) {
		cr_jam_group(
			"$kind mixed row uses selected names for peers in the grouped page map",
			static function () use ( $kind ): void {
				$post = cr_archive_fixture( $kind, array( 'checkin', $kind ) );
				$peer = new CR_Archive_Post( $post->ID + 1000, 'Selected title' );
				$GLOBALS['cr_jam_posts'][ $peer->ID ] = $peer;
				$GLOBALS['cr_jam_request']['post_kinds'][ $peer->ID ] = array( $kind );
				$GLOBALS['cr_jam_facts'][ $peer->ID ] = array( 'name' => 'Selected title', 'cited_title' => 'Selected title' );
				$html = cr_archive_render( $kind, $post, cr_archive_card( $kind, $post, false, false ), null, array( $peer->ID ) );
				$dom = cr_archive_dom( $html );
				$link = $dom->query( '//h3/a' )->item( 0 );
				$names = explode( ' ', $link->getAttribute( 'aria-labelledby' ) );
				cr_jam_same( 2, count( $names ), "$kind retained both duplicate-name references" );
				cr_jam_not_contains( 'checkin', implode( ' ', $names ), "$kind label does not name the permalink" );
			}
		);
	}

	cr_jam_group(
		'Jam keeps the first listing cover eager when the label follows the artist',
		static function (): void {
			$post = cr_archive_fixture( 'jam', array( 'checkin', 'jam' ) );
			$card = cr_archive_card( 'jam', $post, false, false );
			$lead = cr_archive_render( 'jam', $post, $card );
			$next = cr_archive_render( 'jam', $post, $card );
			cr_jam_contains( 'fetchpriority="high"', $lead, 'first Jam listing cover fetch priority' );
			cr_jam_not_contains( 'fetchpriority="high"', $next, 'subsequent Jam listing cover fetch priority' );
			cr_jam_contains( 'Selected artist</p><span class="cr-jam-strip__checkin">', $lead, 'Jam lead label follows the existing artist' );
		}
	);

	cr_jam_group(
		'An English fallback declares its language inside a non-English document',
		static function (): void {
			$post = cr_archive_fixture( 'like', array( 'checkin', 'like' ) );
			$GLOBALS['cr_archive_language'] = 'es-MX';
			$html = cr_archive_render( 'like', $post, cr_archive_card( 'like', $post, false, false ) );
			cr_jam_contains( '<span class="cr-like-sticker__checkin" lang="en-US">Also a check-in</span>', $html, 'fallback label language' );
		}
	);

	cr_jam_finish();
}
