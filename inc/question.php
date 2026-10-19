<?php
/**
 * Question archive and single presentation for issue 256.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Question;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'question';
const STYLE_HANDLE = 'courtneyr-question';
const PAPERS       = array( 'light-gray', 'printer-ivory', 'light-orange', 'periwinkle', 'sky-blue' );

/** Whether the current request is the question kind archive. */
function is_question_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether the current request is a single question post. */
function is_question_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/**
 * Return the palette-paper modifier for one question.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function paper_class( int $post_id ): string {
	if ( ! function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) || ! defined( __NAMESPACE__ . '\\PAPERS' ) ) {
		return '';
	}

	$paper = \Courtneyr\Child\Stamps\seeded_paper( $post_id, PAPERS );
	return 'light-gray' === $paper || '' === $paper ? '' : 'cr-paper--' . $paper;
}

/** Register the editable salutation paragraph style. */
function register_salutation_style(): void {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'cr-salutation',
			'label' => __( 'Salutation', 'courtneyr-child' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_salutation_style' );

/**
 * Paint the plugin's visible question entry without changing its content.
 *
 * @param string               $html     Rendered question entry.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance with Query Loop context.
 * @return string
 */
function paint_entry( string $html, array $block, $instance ): string {
	unset( $block );

	if ( '' === $html ) {
		return $html;
	}

	$post_id = $instance instanceof \WP_Block && ! empty( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$tags    = new \WP_HTML_Tag_Processor( $html );

	while ( $tags->next_tag(
		array(
			'tag_name'   => 'div',
			'class_name' => 'pkiw-question-entry',
		)
	) ) {
		if ( $tags->has_class( 'pkiw-question-entry--continued' ) ) {
			continue;
		}

		foreach ( array( 'cr-question-slip', 'cr-paper-sheet', 'cr-paper-sheet--torn', paper_class( $post_id ) ) as $class_name ) {
			if ( '' !== $class_name ) {
				$tags->add_class( $class_name );
			}
		}
		return $tags->get_updated_html();
	}

	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/question-entry', __NAMESPACE__ . '\\paint_entry', 10, 3 );

/** Load question paint on every surface that can render it. */
function enqueue_styles(): void {
	if (
		! is_admin()
		&& ! is_question_archive()
		&& ! is_question_single()
		&& ! \Courtneyr\Child\HomeSections\is_stream_surface()
	) {
		return;
	}

	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-question.css',
		array( 'courtneyr-paper' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
