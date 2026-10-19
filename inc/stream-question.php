<?php
/**
 * Question Stream presentation for issue 256.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamQuestion;

use Courtneyr\Child\Question;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a Stream block's post ID.
 *
 * @param mixed $instance Block instance.
 * @return int
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Paint the plugin's question Stream card without changing its content.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance with Query Loop context.
 * @return string
 */
function paint_stream( string $html, array $block = array(), $instance = null ): string {
	unset( $block );

	if (
		'' === $html
		|| ! function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		|| ! \Courtneyr\Child\HomeSections\is_stream_surface()
	) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	if ( $post_id < 1 || ! has_term( Question\KIND, 'kind', $post_id ) || post_password_required( $post_id ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pk-card--stream',
		)
	) ) {
		if ( ! $tags->has_class( 'k-question' ) || $tags->has_class( 'pk-card--protected' ) ) {
			continue;
		}

		$classes = array( 'cr-question-slip', 'cr-paper-sheet', 'cr-paper-sheet--torn', Question\paper_class( $post_id ) );
		if (
			function_exists( '\\Courtneyr\\Child\\Stamps\\pick' )
			&& function_exists( '\\Courtneyr\\Child\\Stamps\\seed' )
			&& defined( 'Courtneyr\\Child\\Stamps\\TILTS' )
		) {
			$tilt      = \Courtneyr\Child\Stamps\pick(
				\Courtneyr\Child\Stamps\seed( (string) $post_id ),
				6,
				\Courtneyr\Child\Stamps\TILTS
			);
			$classes[] = 'cr-question-tilt-' . $tilt;
		}

		foreach ( $classes as $class_name ) {
			if ( '' !== $class_name ) {
				$tags->add_class( $class_name );
			}
		}
		return $tags->get_updated_html();
	}

	return $html;
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\paint_stream', 10, 3 );
