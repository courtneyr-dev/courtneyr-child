<?php
/**
 * Mood archive: the year grid's theme integration (PKIW issue 248).
 *
 * The plugin owns the year query, grid markup and mood facts. This file
 * attaches the theme paint, classifies catalog moods the vocabulary does not,
 * and applies the shared paper treatment to the legend and day cards.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\MoodYear;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLOCK        = 'post-kinds-indieweb/mood-year-grid';
const STYLE_HANDLE = 'courtneyr-mood-year';

/** Attach the mood year sheet to the plugin's grid block. */
function register_style(): void {
	$file = COURTNEYR_CHILD_DIR . '/assets/css/cr-mood-year.css';
	if ( ! is_readable( $file ) ) {
		return;
	}

	wp_enqueue_block_style(
		BLOCK,
		array(
			'handle' => STYLE_HANDLE,
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/cr-mood-year.css',
			'path'   => $file,
			'ver'    => COURTNEYR_CHILD_VERSION,
			'deps'   => array( 'courtneyr-calendar', 'courtneyr-paper' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_style', 10 );

/**
 * Classify an unplaced mood from the theme's catalog.
 *
 * @param mixed  $family Family key from the plugin.
 * @param string $key    Stored mood key (unused).
 * @param string $label  Stored mood label.
 * @return mixed The existing value, or a catalog family key.
 */
function catalog_family( $family, string $key, string $label ) {
	unset( $key );

	if ( ! is_string( $family ) || '' !== $family ) {
		return $family;
	}
	if (
		! function_exists( '\\Courtneyr\\Child\\MoodPin\\catalog' )
		|| ! function_exists( '\\Courtneyr\\Child\\MoodPin\\normalize_label' )
	) {
		return '';
	}

	$catalog = \Courtneyr\Child\MoodPin\catalog();
	$label   = \Courtneyr\Child\MoodPin\normalize_label( $label );

	return (string) ( $catalog[ $label ]['family'] ?? '' );
}
add_filter( 'pkiw_mood_family', __NAMESPACE__ . '\\catalog_family', 10, 3 );

/**
 * Apply the shared torn-paper treatment to plugin-owned archive pieces.
 *
 * @param string $html Rendered mood year grid.
 * @return string
 */
function add_paper_classes( string $html ): string {
	if ( ! str_contains( $html, 'pkiw-mood-year' ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( array( 'class_name' => 'pkiw-mood-legend' ) ) ) {
		$tags->add_class( 'cr-paper-sheet' );
		$tags->add_class( 'cr-paper-sheet--torn' );
	}
	$tags = new \WP_HTML_Tag_Processor( $tags->get_updated_html() );
	while (
		$tags->next_tag(
			array(
				'tag_name'   => 'li',
				'class_name' => 'pkiw-mood-daycard',
			)
		)
	) {
		$tags->add_class( 'cr-paper-sheet' );
		$tags->add_class( 'cr-paper-sheet--torn' );
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_' . BLOCK, __NAMESPACE__ . '\\add_paper_classes', 10 );

// Mood never joins courtneyr_child_kind_archive_page_sizes or alters posts_per_page.
