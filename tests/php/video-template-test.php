<?php
/**
 * Static checks for the video archive template and Query pattern.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$theme    = dirname( __DIR__, 2 );
$failures = array();
$checks   = 0;

/** Record one check. */
function cr_video_template_check( bool $condition, string $label ): void {
	global $failures, $checks;
	++$checks;
	echo $condition ? 'PASS ' : 'FAIL ', $label, "\n";
	if ( ! $condition ) {
		$failures[] = $label;
	}
}

/** Read one theme file. */
function cr_video_template_read( string $path ): string {
	global $theme;
	$text = is_file( $theme . '/' . $path ) ? file_get_contents( $theme . '/' . $path ) : '';
	return false === $text ? '' : $text;
}

$template = cr_video_template_read( 'templates/taxonomy-kind-video.html' );
$pattern  = cr_video_template_read( 'patterns/cr-video-shelf-loop.php' );

cr_video_template_check( '' !== $template, 'template exists' );
cr_video_template_check( str_contains( $template, '<!-- wp:courtneyr/archive-identity /-->' ), 'template uses archive identity' );
cr_video_template_check( str_contains( $template, '<!-- wp:query-title ' ), 'template uses query title' );
cr_video_template_check( str_contains( $template, '<!-- wp:term-description ' ), 'template uses term description' );
cr_video_template_check( str_contains( $template, 'courtneyr-child/cr-video-shelf-loop' ), 'template inserts the video pattern' );
cr_video_template_check( str_contains( $template, 'cr-archive cr-archive--stream cr-archive--video' ), 'template carries the video archive classes' );
cr_video_template_check( str_contains( $template, 'metadata":{"name":"Video archive"}' ), 'template names the archive main element' );
cr_video_template_check( ! str_contains( $template, 'cr-browse-all' ), 'template omits browse-all' );
cr_video_template_check( ! str_contains( $template, 'SAMPLE CONTENT' ), 'template omits sample copy' );

cr_video_template_check( '' !== $pattern, 'pattern exists' );
cr_video_template_check( str_contains( $pattern, 'Slug: courtneyr-child/cr-video-shelf-loop' ), 'pattern declares the required slug' );
cr_video_template_check( str_contains( $pattern, 'Inserter: false' ), 'pattern stays out of the inserter' );
cr_video_template_check( str_contains( $pattern, 'Block Types: core/query' ), 'pattern declares its query block type' );
cr_video_template_check( str_contains( $pattern, 'cr-archive-stream cr-video-shelf cr-shelf-boards' ), 'pattern wraps the shared boards' );
cr_video_template_check( 1 === preg_match_all( '/<!-- wp:query\s/', $pattern ), 'pattern has one core query' );
cr_video_template_check( str_contains( $pattern, '"queryId":242' ) && str_contains( $pattern, '"inherit":true' ), 'query id and inheritance are fixed' );
cr_video_template_check( str_contains( $pattern, 'cr-archive-stream__query' ), 'query carries the archive class' );
cr_video_template_check( str_contains( $pattern, 'cr-archive-stream__list cr-video-shelf__list' ), 'post template carries the video list classes' );
cr_video_template_check( 1 === preg_match_all( '/wp:post-kinds-indieweb\/archive-sections\s+\{"linesPerPage":12,"emptyLabel":""\}/', $pattern ), 'one archive marker uses twelve lines and an empty label' );
cr_video_template_check( 1 === preg_match_all( '/wp:post-kinds-indieweb\/stream-card\s+\{"headingLevel":3,"className":"is-style-cr-shelf-tape"\}/', $pattern ), 'one styled stream card uses h3' );
cr_video_template_check( str_contains( $pattern, '<!-- wp:query-pagination-previous <?php echo wp_json_encode( array( \'label\' => __( \'Previous\', \'courtneyr-child\' ) ) );' ), 'previous block carries translated label JSON' );
cr_video_template_check( str_contains( $pattern, '<!-- wp:query-pagination-numbers /-->' ), 'pagination includes numbers' );
cr_video_template_check( str_contains( $pattern, '<!-- wp:query-pagination-next <?php echo wp_json_encode( array( \'label\' => __( \'Next\', \'courtneyr-child\' ) ) );' ), 'next block carries translated label JSON' );
cr_video_template_check( str_contains( $pattern, "esc_html_e( 'No videos yet.', 'courtneyr-child' )" ), 'no-results copy matches the contract' );
cr_video_template_check( ! preg_match( '/\b(?:19|20)\d{2}\b/', $pattern ), 'pattern hard-codes no year' );
cr_video_template_check( ! str_contains( $pattern, 'SAMPLE CONTENT' ), 'pattern omits sample copy' );

echo 'TOTAL ', $checks, ' checks, ', count( $failures ), " failed\n";
exit( empty( $failures ) ? 0 : 1 );
