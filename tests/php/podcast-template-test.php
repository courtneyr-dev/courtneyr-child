<?php
/**
 * Standalone checks for the podcast listen templates.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$failures = array();

function cr_podcast_template_check( bool $ok, string $label ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		$failures[] = $label;
	}
}

/**
 * Tokenize block comments and retain the open block's parent stack.
 *
 * @param string $html Block template HTML.
 * @return array<int, array{name:string, parents:string[]}>
 */
function cr_podcast_template_tokens( string $html ): array {
	if ( ! preg_match_all( '/<!--\s*(\/?)wp:([^\s]+)(.*?)-->/s', $html, $matches, PREG_SET_ORDER ) ) {
		return array();
	}

	$tokens = array();
	$stack  = array();
	foreach ( $matches as $match ) {
		$name = str_contains( $match[2], '/' ) ? $match[2] : 'core/' . $match[2];
		if ( '/' === $match[1] ) {
			array_pop( $stack );
			continue;
		}

		$tokens[] = array(
			'name'    => $name,
			'parents' => $stack,
		);
		if ( 1 !== preg_match( '/\/\s*$/', trim( $match[3] ) ) ) {
			$stack[] = $name;
		}
	}

	return $tokens;
}

$root     = dirname( __DIR__, 2 );
$shelf    = (string) file_get_contents( $root . '/templates/taxonomy-kind-listen.html' );
$podcast  = (string) file_get_contents( $root . '/templates/taxonomy-kind-listen-podcast.html' );
$nav      = '<!-- wp:post-kinds-indieweb/listen-types /-->';
$baseline = str_replace( "\n\t" . $nav . "\n", '', $shelf );

cr_podcast_template_check( 'eb2b16505d25ec2f7dd77af967dc16a277f3e90e' === sha1( $baseline ), 'shelf minus the nav line and adjacent blank line matches d78302f' );
cr_podcast_template_check( str_ends_with( $shelf, "\n" ), 'shelf template ends with a newline' );
cr_podcast_template_check( 1 === substr_count( $shelf, $nav ), 'shelf holds the listen-types block once' );
$shelf_nav    = strpos( $shelf, $nav );
$shelf_header = strpos( $shelf, "\t<!-- /wp:group -->", strpos( $shelf, 'Archive Header' ) );
$shelf_loop   = strpos( $shelf, 'courtneyr-child/cr-media-shelf-loop' );
cr_podcast_template_check( false !== $shelf_header && $shelf_header < $shelf_nav && $shelf_nav < $shelf_loop, 'shelf nav follows the archive header and precedes the shelf pattern' );

$tokens = cr_podcast_template_tokens( $podcast );
$names  = array_column( $tokens, 'name' );
$wanted = array(
	'core/template-part',
	'courtneyr/archive-identity',
	'core/query-title',
	'core/term-description',
	'core/separator',
	'post-kinds-indieweb/listen-types',
	'core/query',
	'core/post-template',
	'post-kinds-indieweb/podcast-episode',
	'core/query-pagination',
	'core/query-pagination-previous',
	'core/query-pagination-numbers',
	'core/query-pagination-next',
	'core/query-no-results',
	'core/template-part',
);
$filtered = array_values( array_filter( $names, static fn( string $name ): bool => in_array( $name, $wanted, true ) ) );
cr_podcast_template_check( $wanted === $filtered, 'podcast template block names follow the required order' );

$pagination = null;
foreach ( $tokens as $token ) {
	if ( 'core/query-pagination' === $token['name'] ) {
		$pagination = $token;
		break;
	}
}
cr_podcast_template_check( is_array( $pagination ) && ! in_array( 'core/post-template', $pagination['parents'], true ), 'pagination follows the post template outside its chassis' );
cr_podcast_template_check( str_contains( $podcast, '"inherit":true' ), 'podcast query inherits the archive query' );
cr_podcast_template_check( str_contains( $podcast, '"namespace":"pkiw-kind-lineup"' ), 'podcast query uses the lineup namespace' );
cr_podcast_template_check( str_contains( $podcast, '"linesPerPage":12' ), 'podcast episode block sets twelve lines per page' );
cr_podcast_template_check( str_contains( $podcast, 'query-pagination-previous {"label":"Previous"}' ) && str_contains( $podcast, 'query-pagination-next {"label":"Next"}' ), 'pagination carries literal Previous and Next labels' );
cr_podcast_template_check( str_contains( $podcast, '<p class="cr-archive-stream__empty">Nothing here yet. Browse every kind and format below, or jump to the Stream.</p>' ), 'podcast no-results copy matches the shelf pattern' );
foreach ( array( 'wp:pattern', 'cr-browse-all', 'Podcasts', 'tape', 'wp:post-title', 'wp:post-content', 'wp:post-featured-image' ) as $banned ) {
	cr_podcast_template_check( ! str_contains( $podcast, $banned ), 'podcast template omits ' . $banned );
}
cr_podcast_template_check( str_contains( $podcast, 'cr-archive cr-archive--stream cr-archive--listen cr-archive--podcast' ), 'podcast main group carries the podcast archive class' );
cr_podcast_template_check( str_contains( $podcast, '"metadata":{"name":"Podcast listens archive"}' ), 'podcast main group has the required metadata name' );

echo "\n", $failures ? count( $failures ) . ' failed' : 'all passed', "\n";
exit( $failures ? 1 : 0 );
