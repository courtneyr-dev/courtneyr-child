<?php
/**
 * Standalone checks for podcast Stream cards.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/podcast-bootstrap.php';

use Courtneyr\Child\StreamMedia as StreamMedia;

/**
 * Complete public facts fixture.
 *
 * @param array<string, mixed> $overrides Fact overrides.
 * @return array<string, mixed>
 */
function cr_podcast_stream_facts( array $overrides = array() ): array {
	return array_replace_recursive(
		array(
			'is_podcast'    => true,
			'name'          => 'Harbor Signals',
			'name_is_title' => true,
			'episode_text'  => 'Harbor Signals',
			'show'          => 'Alder & Stone',
			'url'           => 'https://audio.example.test/episode?x=1&y=2',
			'day_iso'       => '2026-09-26',
			'day_display'   => 'September 26, 2026',
			'kind_label'    => 'Listen',
			'picture'       => array(
				'attachment_id' => 0,
				'url'           => 'https://img.example.test/cover.jpg',
			),
		),
		$overrides
	);
}

$post = cr_podcast_reset( 401 );
$facts = cr_podcast_stream_facts();
$html = StreamMedia\podcast_card( array( 'attrs' => array( 'headingLevel' => 3 ) ), $post, $facts );

preg_match( '/^<article class="([^"]+)">/', $html, $root );
cr_podcast_same( 'pk-card pk-card--stream k-podcast cr-podcast-card h-entry', $root[1] ?? '', 'card has the exact root class list' );
$kind_at    = strpos( $html, 'pk-kindlabel' );
$show_at    = strpos( $html, 'cr-podcast-card__show' );
$heading_at = strpos( $html, '<h3' );
$time_at    = strpos( $html, '<time' );
$art_at     = strpos( $html, 'cr-podcast-card__art' );
cr_podcast_check( $kind_at < $show_at && $show_at < $heading_at && $heading_at < $time_at && $time_at < $art_at, 'card DOM order is kind label, show, heading, time, art' );
cr_podcast_same( 1, substr_count( $html, '<a ' ), 'card has exactly one anchor' );
foreach ( array( 'k-listen', 'cr-cassette', 'pk-media', 'pk-body', 'pk-badge', 'pk-embed' ) as $banned ) {
	cr_podcast_check( ! str_contains( $html, $banned ), 'card omits class ' . $banned );
}
foreach ( array( '<iframe', '<button', 'tabindex', '<audio', '<script' ) as $banned ) {
	cr_podcast_check( ! str_contains( $html, $banned ), 'card omits element or attribute ' . $banned );
}

$low = StreamMedia\podcast_card( array( 'attrs' => array( 'headingLevel' => 1 ) ), $post, $facts );
$high = StreamMedia\podcast_card( array( 'attrs' => array( 'headingLevel' => 9 ) ), $post, $facts );
$default = StreamMedia\podcast_card( array(), $post, $facts );
cr_podcast_contains( '<h2 class=', $low, 'heading level one clamps to h2' );
cr_podcast_contains( '<h4 class=', $high, 'heading level nine clamps to h4' );
cr_podcast_contains( '<h2 class=', $default, 'missing heading level defaults to h2' );

cr_podcast_contains( 'class="pk-title cr-podcast-card__title p-name"', $html, 'heading has p-name when displayed text is the real title' );
$synthetic = StreamMedia\podcast_card( array(), $post, cr_podcast_stream_facts( array( 'name' => 'Listened to Harbor Signals' ) ) );
cr_podcast_contains( 'class="pk-title cr-podcast-card__title"', $synthetic, 'synthetic title heading keeps the base classes' );
cr_podcast_not_contains( 'cr-podcast-card__title p-name', $synthetic, 'synthetic title heading omits p-name' );

cr_podcast_check( 1 === substr_count( $html, '<img' ), 'remote picture art has one image' );
cr_podcast_contains( 'class="u-photo"', $html, 'picture image has u-photo' );
cr_podcast_contains( 'alt="" loading="lazy" decoding="async"', $html, 'picture image is decorative, lazy and async' );
$attachment = StreamMedia\podcast_card(
	array(),
	$post,
	cr_podcast_stream_facts( array( 'picture' => array( 'attachment_id' => 88, 'url' => 'https://example.test/media/88.jpg' ) ) )
);
cr_podcast_check( 1 === substr_count( $attachment, '<img' ) && str_contains( $attachment, '/media/88.jpg' ), 'attachment picture uses one core image' );
$knob = StreamMedia\podcast_card( array(), $post, cr_podcast_stream_facts( array( 'picture' => array( 'attachment_id' => 0, 'url' => '' ) ) ) );
cr_podcast_contains( 'class="cr-podcast-card__art cr-podcast-card__art--knob" aria-hidden="true" data-cr-cover-fallback="true"></div>', $knob, 'missing picture prints the empty aria-hidden knob art' );
cr_podcast_not_contains( '<img', $knob, 'knob fallback has no image' );

$cite = StreamMedia\podcast_cite( $facts );
$dom = new DOMDocument();
$previous = libxml_use_internal_errors( true );
$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $cite . '</div>' );
libxml_clear_errors();
libxml_use_internal_errors( $previous );
$xpath = new DOMXPath( $dom );
$root_cite = $xpath->query( '//*[contains(concat(" ",normalize-space(@class)," ")," u-listen-of ") and contains(concat(" ",normalize-space(@class)," ")," h-cite ")]' )->item( 0 );
cr_podcast_check( $root_cite instanceof DOMElement, 'hidden cite parses as u-listen-of h-cite' );
$name = $xpath->query( './/data[contains(concat(" ",normalize-space(@class)," ")," p-name ")]', $root_cite )->item( 0 );
$url = $xpath->query( './/data[contains(concat(" ",normalize-space(@class)," ")," u-url ")]', $root_cite )->item( 0 );
$author = $xpath->query( './/*[contains(concat(" ",normalize-space(@class)," ")," p-author ")]//data[contains(concat(" ",normalize-space(@class)," ")," p-name ")]', $root_cite )->item( 0 );
cr_podcast_same( 'Harbor Signals', $name instanceof DOMElement ? $name->getAttribute( 'value' ) : '', 'cite p-name value is the episode' );
cr_podcast_same( 'https://audio.example.test/episode?x=1&y=2', $url instanceof DOMElement ? $url->getAttribute( 'value' ) : '', 'cite u-url value is the episode URL' );
cr_podcast_same( 'Alder & Stone', $author instanceof DOMElement ? $author->getAttribute( 'value' ) : '', 'cite p-author value preserves an escaped ampersand' );
cr_podcast_same( '', StreamMedia\podcast_cite( array() ), 'empty cite facts return an empty string' );

$GLOBALS['cr_podcast_facts'][ $post->ID ] = $facts;
cr_podcast_same( $facts, StreamMedia\podcast_facts( $post ), 'podcast_facts returns public podcast facts' );
$GLOBALS['cr_podcast_locked'][ $post->ID ] = true;
cr_podcast_same( array(), StreamMedia\podcast_facts( $post ), 'podcast_facts returns empty for a locked post' );
$GLOBALS['cr_podcast_locked'] = array();
$GLOBALS['cr_podcast_facts'][ $post->ID ] = array( 'name' => 'Music' );
cr_podcast_same( array(), StreamMedia\podcast_facts( $post ), 'podcast_facts returns empty without is_podcast' );

$GLOBALS['cr_podcast_facts'][ $post->ID ] = $facts;
$replaced = StreamMedia\media_card( '<article>Plugin card</article>', array( 'attrs' => array( 'headingLevel' => 2 ) ), new WP_Block( array( 'postId' => $post->ID ) ) );
cr_podcast_contains( 'class="pk-card pk-card--stream k-podcast cr-podcast-card h-entry"', $replaced, 'media_card replaces a Stream podcast from facts alone' );
$GLOBALS['cr_podcast_locked'][ $post->ID ] = true;
$protected = '<article class="pk-card--protected">Protected title</article>';
cr_podcast_same( $protected, StreamMedia\media_card( $protected, array(), new WP_Block( array( 'postId' => $post->ID ) ) ), 'locked podcast keeps the incoming title-only card' );

$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/stream-media.php' );
cr_podcast_contains( "function_exists( '\\\\PKIW\\\\kind_facts' )", $source, 'podcast_facts guards a missing plugin function' );
cr_podcast_contains( 'cr-cassette--stream', $source, 'existing cassette path remains in the file' );
$post_guard = strpos( $source, 'if ( ! $post instanceof \\WP_Post )' );
$podcast_branch = strpos( $source, "if ( '' === \$shelf && ! \$shelf_card && has_term( 'listen', 'kind', \$post ) )" );
$kind_branch = strpos( $source, '$kind = has_term' );
cr_podcast_check( false !== $post_guard && $post_guard < $podcast_branch && $podcast_branch < $kind_branch, 'podcast branch sits after the WP_Post check and before kind detection' );
$gate = strpos( $source, "if ( '' === \$shelf && ! \$shelf_card && ! \\Courtneyr\\Child\\HomeSections\\is_stream_surface() )" );
cr_podcast_check( false !== $gate && $gate < $post_guard, 'media_card keeps its shelf and Stream surface gate' );

cr_podcast_finish();
