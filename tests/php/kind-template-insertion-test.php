<?php
/**
 * Standalone check that every kind archive template carries the Wrapped strip
 * and the zine-export part directly after the archive header.
 *
 * Run: php tests/php/kind-template-insertion-test.php (exit 1 on any FAIL line).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$failed = 0;
function cr_kind_insertion_check( bool $condition, string $message ): void {
	global $failed;
	echo ( $condition ? 'ok   ' : 'FAIL ' ) . $message . "\n";
	$failed += $condition ? 0 : 1;
}

/**
 * Parse block comments into a tree of array{name, attrs, children}.
 *
 * @return array<int, array{name: string, attrs: string, children: array}>|null Null when unbalanced.
 */
function cr_kind_insertion_tree( string $html ): ?array {
	preg_match_all( '/<!--\s+(\/?)wp:([a-z0-9][a-z0-9\/_-]*)(\s+\{.*?\})?\s*(\/?)-->/s', $html, $matches, PREG_SET_ORDER );
	$root  = array();
	$stack = array( &$root );
	$names = array();
	foreach ( $matches as $m ) {
		$node = array(
			'name'     => $m[2],
			'attrs'    => $m[3],
			'children' => array(),
		);
		if ( '' !== $m[1] ) {
			if ( array() === $names || array_pop( $names ) !== $m[2] ) {
				return null;
			}
			array_pop( $stack );
			continue;
		}
		$top = &$stack[ count( $stack ) - 1 ];
		$top[] = $node;
		if ( '' === $m[4] ) {
			$names[] = $m[2];
			$stack[] = &$top[ count( $top ) - 1 ]['children'];
		}
		unset( $top );
	}
	return array() === $names ? $root : null;
}

/**
 * Find the node whose children include the archive header group.
 */
function cr_kind_insertion_parent( array $nodes ): ?array {
	foreach ( $nodes as $node ) {
		foreach ( $node['children'] as $child ) {
			if ( 'group' === $child['name'] && str_contains( $child['attrs'], 'cr-archive__header' ) ) {
				return $node;
			}
		}
		$found = cr_kind_insertion_parent( $node['children'] );
		if ( null !== $found ) {
			return $found;
		}
	}
	return null;
}

/**
 * Count nodes of one block name whose attributes contain a string, at any depth.
 */
function cr_kind_insertion_count( array $nodes, string $name, string $attr_part ): int {
	$count = 0;
	foreach ( $nodes as $node ) {
		if ( $name === $node['name'] && ( '' === $attr_part || str_contains( $node['attrs'], $attr_part ) ) ) {
			++$count;
		}
		$count += cr_kind_insertion_count( $node['children'], $name, $attr_part );
	}
	return $count;
}

$root  = dirname( __DIR__, 2 );
$files = glob( $root . '/templates/taxonomy-kind*.html' );
sort( $files );

cr_kind_insertion_check( 42 === count( $files ), 'found 42 kind archive templates (' . count( $files ) . ')' );

foreach ( $files as $file ) {
	$slug = basename( $file, '.html' );
	$html = (string) file_get_contents( $file );

	$tree = cr_kind_insertion_tree( $html );
	if ( null === $tree ) {
		cr_kind_insertion_check( false, "$slug: block comments are balanced" );
		continue;
	}
	cr_kind_insertion_check( 1 === cr_kind_insertion_count( $tree, 'post-kinds-indieweb/wrapped-strip', '' ), "$slug: one Wrapped strip block in the tree" );
	cr_kind_insertion_check( 1 === cr_kind_insertion_count( $tree, 'template-part', '"slug":"zine-export"' ), "$slug: one zine-export part block in the tree" );
	$parent = cr_kind_insertion_parent( $tree );
	if ( null === $parent ) {
		cr_kind_insertion_check( false, "$slug: archive header group found" );
		continue;
	}

	$kids = $parent['children'];
	$h    = null;
	foreach ( $kids as $i => $kid ) {
		if ( 'group' === $kid['name'] && str_contains( $kid['attrs'], 'cr-archive__header' ) ) {
			$h = $i;
		}
	}
	$strip = $kids[ $h + 1 ] ?? array( 'name' => '' );
	$zine  = $kids[ $h + 2 ] ?? array( 'name' => '', 'attrs' => '' );

	cr_kind_insertion_check(
		'post-kinds-indieweb/wrapped-strip' === $strip['name'],
		"$slug: Wrapped strip is the sibling right after the header"
	);
	cr_kind_insertion_check( '' === trim( $strip['attrs'] ?? '' ), "$slug: Wrapped strip carries no attributes" );
	cr_kind_insertion_check(
		'template-part' === $zine['name'] && '{"slug":"zine-export"}' === trim( $zine['attrs'] ),
		"$slug: zine-export part follows the strip"
	);
}

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit( $failed ? 1 : 0 );
