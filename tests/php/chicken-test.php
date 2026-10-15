<?php
/**
 * `php tests/php/chicken-test.php`: standalone checks for the chicken archive
 * module (inc/chicken.php), the carton template and pattern, and the
 * stylesheet's contract. Also run with `--no-plugin`.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/chicken-bootstrap.php';

use Courtneyr\Child\Chicken;

/** Return the enqueued styles for one request. */
function cr_chk_assets_for( array $request ): array {
	cr_chk_reset();
	$GLOBALS['cr_chk_request'] = array_merge( $GLOBALS['cr_chk_request'], $request );
	$GLOBALS['cr_chk_enqueued'] = array();
	cr_chk_fire( 'enqueue_block_assets' );
	return $GLOBALS['cr_chk_enqueued'];
}

/** Read a theme file. */
function cr_chk_file( string $relative ): string {
	$text = file_get_contents( CR_CHICKEN_THEME_DIR . '/' . $relative );
	return false === $text ? '' : $text;
}

/** Block names in order of appearance in block markup. */
function cr_chk_block_names( string $markup ): array {
	preg_match_all( '/<!-- wp:([a-z0-9\/-]+)/', $markup, $found );
	return $found[1];
}

cr_chk_group(
	'enqueue_styles loads the sheet on the archive, a chicken single, every Stream surface and the editor only',
	static function (): void {
		$expected = array( array( 'courtneyr-chicken', COURTNEYR_CHILD_URI . '/assets/css/cr-chicken.css', array(), COURTNEYR_CHILD_VERSION ) );
		foreach ( array(
			'archive' => array( 'kind_archive' => 'chicken' ),
			'single'  => array( 'single' => true ),
			'stream'  => array( 'stream' => true ),
			'editor'  => array( 'admin' => true ),
		) as $label => $request ) {
			cr_chk_same( $expected, cr_chk_assets_for( $request ), $label );
		}
		foreach ( array(
			'plain page'          => array(),
			'another kind'        => array( 'kind_archive' => 'read' ),
			'a non-chicken single' => array( 'single' => true, 'post_kinds' => array( 42 => array( 'read' ) ) ),
		) as $label => $request ) {
			cr_chk_same( array(), cr_chk_assets_for( $request ), $label . ' loads nothing' );
		}
	}
);

cr_chk_group(
	'body_class adds each class once',
	static function (): void {
		cr_chk_reset();
		cr_chk_same( array( 'a' ), Chicken\body_class( array( 'a' ) ), 'plain page' );
		$GLOBALS['cr_chk_request']['kind_archive'] = 'chicken';
		$once = Chicken\body_class( Chicken\body_class( array( 'a' ) ) );
		cr_chk_same( array( 'a', 'cr-chicken-archive' ), $once, 'archive class, once' );
		$GLOBALS['cr_chk_request']['kind_archive'] = '';
		$GLOBALS['cr_chk_request']['single']       = true;
		cr_chk_same( array( 'cr-chicken-single' ), Chicken\body_class( array() ), 'single class' );
	}
);

cr_chk_group(
	'egg_fill is stable and draws only from the ten allowed palette tokens',
	static function (): void {
		cr_chk_same( 10, count( Chicken\EGG_FILLS ), 'ten fills' );
		foreach ( array( 'light-gray', 'printer-ivory', 'rule', 'white' ) as $banned ) {
			cr_chk_assert( ! in_array( $banned, Chicken\EGG_FILLS, true ), $banned . ' must not be an egg fill (decision 250, correction 71)' );
		}
		$seen = array();
		for ( $id = 1; $id <= 300; $id++ ) {
			$fill = Chicken\egg_fill( $id );
			cr_chk_assert( in_array( $fill, Chicken\EGG_FILLS, true ), 'post ' . $id . ' fill ' . $fill . ' is allowed' );
			cr_chk_same( $fill, Chicken\egg_fill( $id ), 'post ' . $id . ' fill is stable' );
			$seen[ $fill ] = true;
		}
		cr_chk_same( 10, count( $seen ), 'all ten fills occur over 300 posts' );
		cr_chk_same( \Courtneyr\Child\Stamps\seeded_paper( 77, Chicken\EGG_FILLS ), Chicken\egg_fill( 77 ), 'it is the shared seeded paper' );
	}
);

cr_chk_group(
	'paint_eggs classes each title link by its own id and nothing else',
	static function (): void {
		$item = static fn( int $id ): string => '<li class="wp-block-post"><time class="dt-published pkiw-chicken__date" datetime="2026-08-01T10:00:00+00:00">AUG 01</time><h3 class="pkiw-chicken__title"><a class="u-url" id="pkiw-chicken-t-' . $id . '" href="https://example.test/' . $id . '/">Hen</a></h3><span hidden><a class="u-url p-name" tabindex="-1" href="https://example.test/author/">Courtney</a></span></li>';
		$html = '<section>' . $item( 7 ) . $item( 8 ) . '</section>';
		$out  = Chicken\paint_eggs( $html, array() );

		cr_chk_contains( 'class="u-url cr-egg--' . Chicken\egg_fill( 7 ) . '" id="pkiw-chicken-t-7"', $out, 'post 7 title link', );
		preg_match_all( '/cr-egg--[a-z-]+/', $out, $found );
		cr_chk_same( array( 'cr-egg--' . Chicken\egg_fill( 7 ), 'cr-egg--' . Chicken\egg_fill( 8 ) ), $found[0], 'two classes, one per title link' );
		cr_chk_contains( '<a class="u-url p-name" tabindex="-1" href="https://example.test/author/">', $out, 'the hidden author link is untouched' );

		$placeholder = '<li><span class="pkiw-chicken__continued">Continued</span></li>';
		cr_chk_same( $placeholder, Chicken\paint_eggs( $placeholder, array() ), 'a placeholder has no link and no change' );
		cr_chk_same( $out, Chicken\paint_eggs( $out, array() ), 'a second pass changes nothing' );
	}
);

cr_chk_group(
	'the block render filter and the editor parity filter are registered',
	static function (): void {
		cr_chk_same( array( 10 ), cr_chk_priorities( 'render_block_post-kinds-indieweb/chicken-entry', 'Courtneyr\\Child\\Chicken\\paint_eggs' ), 'paint_eggs at 10' );

		$kinds = Chicken\register_editor_parity( array() );
		cr_chk_same( array( 'single-post', 'kind-chicken', 'cr-chicken-board' ), $kinds['chicken']['classes'], 'creates the entry' );
		$kinds = Chicken\register_editor_parity( array( 'chicken' => array( 'classes' => array( 'single-post', 'kind-chicken' ) ), 'read' => array( 'classes' => array( 'single-post' ) ) ) );
		cr_chk_same( array( 'single-post', 'kind-chicken', 'cr-chicken-board' ), $kinds['chicken']['classes'], 'appends to an existing entry' );
		cr_chk_same( array( 'single-post' ), $kinds['read']['classes'], 'leaves other kinds alone' );
		cr_chk_same( $kinds, Chicken\register_editor_parity( $kinds ), 'idempotent' );
		cr_chk_assert( in_array( 'Courtneyr\\Child\\Chicken\\register_editor_parity', array_column( array_filter( $GLOBALS['cr_chk_hooks'], static fn( array $h ): bool => 'courtneyr_child_editor_parity_kinds' === $h[0] ), 1 ), true ), 'hooked to courtneyr_child_editor_parity_kinds' );
	}
);

cr_chk_group(
	'the archive template follows the kind shell and prints the approved header',
	static function (): void {
		$template = cr_chk_file( 'templates/taxonomy-kind-chicken.html' );
		cr_chk_contains( 'cr-archive cr-archive--stream cr-archive--chicken', $template, 'archive classes' );
		cr_chk_contains( '<!-- wp:courtneyr/archive-identity /-->', $template, 'burst, tiles, description and badge come from archive-identity' );
		cr_chk_contains( '"type":"archive","showPrefix":false', $template, 'query title without a prefix' );
		cr_chk_contains( '<!-- wp:term-description', $template, 'term description' );
		cr_chk_contains( 'is-style-cr-marker-bar-short', $template, 'orange divider' );
		cr_chk_contains( 'courtneyr-child/cr-chicken-carton-loop', $template, 'carton loop pattern' );
		cr_chk_contains( 'courtneyr-child/cr-browse-all', $template, 'browse all' );
		cr_chk_lacks( 'SAMPLE CONTENT', $template, 'no sample copy' );
		cr_chk_lacks( 'Stream · Kind', $template, 'no tape' );
	}
);

cr_chk_group(
	'the carton loop is one inherited query, the entry block, then the pager with nothing between',
	static function (): void {
		$pattern = cr_chk_file( 'patterns/cr-chicken-carton-loop.php' );
		$names   = cr_chk_block_names( $pattern );
		cr_chk_same( 1, count( array_keys( $names, 'query', true ) ), 'one query' );
		cr_chk_contains( '"inherit":true', $pattern, 'inherits the main query' );
		cr_chk_contains( '<!-- wp:post-kinds-indieweb/chicken-entry {"linesPerPage":24} /-->', $pattern, '24 entries a page' );
		cr_chk_contains( "is_registered( 'post-kinds-indieweb/chicken-entry' )", $pattern, 'guarded for a missing plugin' );
		$post_template_end = strpos( $pattern, '<!-- /wp:post-template -->' );
		$pagination_start  = strpos( $pattern, '<!-- wp:query-pagination ' );
		cr_chk_assert( false !== $post_template_end && false !== $pagination_start && $post_template_end < $pagination_start, 'pagination follows the post template' );
		$between = substr( $pattern, (int) $post_template_end, (int) $pagination_start - (int) $post_template_end );
		cr_chk_same( array( 'query-no-results', 'paragraph' ), array_values( array_filter( cr_chk_block_names( $between ), static fn( string $n ): bool => 'query-no-results' === $n || 'paragraph' === $n ) ), 'only the empty-state block can sit before the pager' );
		cr_chk_lacks( 'One egg', $pattern, 'no legend line' );
		cr_chk_lacks( 'SAMPLE CONTENT', $pattern, 'no sample copy' );
		cr_chk_same( array( 'previous', 'numbers', 'next' ), array_map( static fn( string $n ): string => substr( $n, strlen( 'query-pagination-' ) ), array_values( array_filter( $names, static fn( string $n ): bool => str_starts_with( $n, 'query-pagination-' ) ) ) ), 'previous, numbers, next' );
	}
);

cr_chk_group(
	'the starter pattern holds an Image and a Paragraph and no Heading',
	static function (): void {
		$pattern = cr_chk_file( 'patterns/cr-chicken-note.php' );
		cr_chk_same( array( 'image', 'paragraph' ), cr_chk_block_names( $pattern ), 'exactly an image and a paragraph' );
	}
);

cr_chk_group(
	'cr-chicken.css: one spare-cup rule per remainder, spans that complete the row, and nothing off-palette',
	static function (): void {
		$css = cr_chk_file( 'assets/css/cr-chicken.css' );
		foreach ( array( 12, 6, 3, 2 ) as $columns ) {
			preg_match_all( '/nth-child\(' . $columns . 'n\+(\d+)\)\)::after \{\s*--cr-spare: (\d+);/', $css, $found, PREG_SET_ORDER );
			cr_chk_same( $columns - 1, count( $found ), $columns . ' columns: one rule for each remainder' );
			foreach ( $found as $rule ) {
				cr_chk_same( $columns - (int) $rule[1], (int) $rule[2], $columns . ' columns: remainder ' . $rule[1] . ' spans the rest of the row' );
			}
		}
		cr_chk_assert( 1 === preg_match( '/\(min-width: 64rem\) \{\s*\.cr-chicken \{\s*--cr-cups: 12;/', $css ), '12 cups from 64rem' );
		cr_chk_assert( 1 === preg_match( '/\(min-width: 40rem\) \{\s*\.cr-chicken \{\s*--cr-cups: 6;/', $css ), '6 cups from 40rem' );
		cr_chk_assert( 1 === preg_match( '/\(min-width: 22rem\) \{\s*\.cr-chicken \{\s*--cr-cups: 3;/', $css ), '3 cups from 22rem' );
		cr_chk_assert( 1 === preg_match( '/\.cr-chicken \{\s*--cr-cups: 2;/', $css ), '2 cups below 22rem' );

		cr_chk_same( 0, preg_match( '/#[0-9a-fA-F]{3,8}\b/', $css ), 'no literal hex colors' );
		cr_chk_same( 0, preg_match( '/gradient|filter:|backdrop-filter|rgba?\(|hsla?\(/i', $css ), 'no gradient, filter or literal color function' );
		cr_chk_same( 0, preg_match( '/#0437c9|#b5b9b5|#cdcecd|#c2c5ce/i', $css ), 'none of the off-palette samples the review found' );
		preg_match_all( '/box-shadow:\s*([^;]+);/', $css, $shadows );
		foreach ( $shadows[1] as $shadow ) {
			cr_chk_assert( in_array( trim( $shadow ), array( 'none', '0 0 0 7px var(--cr-focus-halo)' ), true ), 'box-shadow is none or the zero-blur focus halo: ' . $shadow );
		}
		preg_match_all( '/font-size:\s*([^;]+);/', $css, $sizes );
		foreach ( $sizes[1] as $size ) {
			cr_chk_assert( 1 === preg_match( '/^var\(--cr-text-(xs|sm|base|md|lg|xl|2xl|3xl)\)$/', trim( $size ) ), 'font-size comes from a token at or above the text floor: ' . $size );
		}
		cr_chk_lacks( 'var(--cr-link)', $css, 'titles do not take the cerulean link token' );
		cr_chk_lacks( 'night-elevated', $css, 'the carton and board do not flip to the night surface (X1)' );
		cr_chk_contains( 'var(--cr-light-gray)', $css, 'carton and frame are light gray' );
		cr_chk_contains( 'var(--cr-prussian-blue)', $css, 'board is Prussian blue' );
		cr_chk_contains( 'prefers-reduced-motion: no-preference', $css, 'motion only under no-preference' );
		cr_chk_contains( 'forced-colors: active', $css, 'forced colors handled' );
	}
);

cr_chk_group(
	'nothing in the theme prints sample copy',
	static function (): void {
		$hits = array();
		foreach ( array( 'assets/css', 'inc', 'templates', 'patterns' ) as $folder ) {
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( CR_CHICKEN_THEME_DIR . '/' . $folder, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
				if ( $file->isFile() && str_contains( (string) file_get_contents( $file->getPathname() ), 'SAMPLE CONTENT' ) ) {
					$hits[] = $file->getPathname();
				}
			}
		}
		cr_chk_same( array(), $hits, 'files with SAMPLE CONTENT' );
	}
);

cr_chk_finish();
