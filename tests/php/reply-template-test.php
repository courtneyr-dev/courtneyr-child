<?php
/**
 * Static checks for the reply archive template and loop pattern.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

require __DIR__ . '/reply-bootstrap.php';

$root     = dirname( __DIR__, 2 );
$template = (string) file_get_contents( $root . '/templates/taxonomy-kind-reply.html' );
$pattern  = (string) file_get_contents( $root . '/patterns/cr-letters-loop.php' );

cr_reply_group(
	'template owns the reply archive structure',
	static function () use ( $template ): void {
		foreach ( array( 'template-part {"slug":"header"', 'template-part {"slug":"footer"', 'courtneyr/archive-identity', 'query-title', 'term-description', 'is-style-cr-marker-bar-short', 'courtneyr-child/cr-letters-loop', 'courtneyr-child/cr-browse-all', 'cr-archive cr-archive--stream cr-archive--reply' ) as $needle ) {
			cr_reply_contains( $needle, $template, $needle );
		}
		cr_reply_contains( '"metadata":{"name":"Reply archive"}', $template, 'metadata name' );
		cr_reply_not_contains( 'Read order', $template, 'no read order group' );
		cr_reply_not_contains( 'read-order', $template, 'no read order block' );
	}
);

cr_reply_group(
	'pattern declares the grouped letter loop',
	static function () use ( $pattern ): void {
		foreach ( array( 'Title: Letters loop', 'Slug: courtneyr-child/cr-letters-loop', 'Categories: cr-zine, cr-loops', 'Block Types: core/query', 'Inserter: false' ) as $needle ) {
			cr_reply_contains( $needle, $pattern, $needle );
		}
		foreach ( array( '"queryId":253', '"inherit":true', '"className":"cr-letters"', '"className":"cr-letters__query"', '"linesPerPage":9', '"headingLevel":2', '"emptyLabel":""', 'post-kinds-indieweb/stream-card {"headingLevel":3,"className":"is-style-cr-letter"}', 'query-no-results', 'query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}}', 'query-pagination-previous', 'query-pagination-numbers', 'query-pagination-next' ) as $needle ) {
			cr_reply_contains( $needle, $pattern, $needle );
		}
		cr_reply_assert( strpos( $pattern, 'query-no-results' ) < strpos( $pattern, 'query-pagination' ), 'no-results precedes pagination' );
		cr_reply_assert( strpos( $pattern, 'archive-sections' ) < strpos( $pattern, 'stream-card' ), 'sections precede card' );
		cr_reply_contains( "is_registered( 'post-kinds-indieweb/archive-sections' )", $pattern, 'archive-sections guard' );
		cr_reply_contains( "is_registered( 'post-kinds-indieweb/stream-card' )", $pattern, 'stream-card guard' );
		cr_reply_contains( 'wp:post-title', $pattern, 'fallback title' );
		cr_reply_contains( 'wp:post-excerpt', $pattern, 'fallback excerpt' );
		foreach ( array( 'cr-archive-stream ', 'cr-archive-stream"', 'cr-stories', 'SAMPLE CONTENT' ) as $gone ) {
			cr_reply_not_contains( $gone, $pattern, $gone . ' absent' );
		}
	}
);

cr_reply_finish();
