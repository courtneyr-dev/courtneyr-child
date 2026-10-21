<?php
/**
 * Collection archive and Stream card paint.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamCollection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LISTING_CLASS = 'pk-card--collection-listing';
const STREAM_CLASS  = 'pk-card--collection-stream';
const FIRST_ROW     = 3;
const ROTATIONS     = 4;

/** Return the request-level archive-card position state. */
function &page_state(): array {
	static $state = array(
		'position' => 0,
		'image'    => 0,
	);
	return $state;
}

/** Reset request-level archive-card state between requests and tests. */
function reset_page_state(): void {
	$state = &page_state();
	$state = array(
		'position' => 0,
		'image'    => 0,
	);
}

/**
 * Paint one plugin-owned collection card without changing its visible text.
 *
 * @param string               $html     Rendered Stream card.
 * @param array<string, mixed> $block    Parsed block, unused.
 * @param mixed                $instance Block instance with post context.
 * @return string
 */
function paint_card( string $html, array $block = array(), $instance = null ): string {
	unset( $block );
	if ( '' === $html ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if (
		! $tags->next_tag()
		|| 'ARTICLE' !== $tags->get_tag()
		|| ! $tags->has_class( 'pk-card--collection' )
		|| $tags->has_class( 'pk-card--protected' )
	) {
		return $html;
	}

	if ( $tags->has_class( LISTING_CLASS ) ) {
		$state = &page_state();
		++$state['position'];
		$eager = $state['position'] <= FIRST_ROW;
		$tags->add_class( 'cr-cabinet-plaque' );
		while (
			$tags->next_tag(
				array(
					'tag_name'   => 'IMG',
					'class_name' => 'pk-collection__img',
				)
			)
		) {
			++$state['image'];
			$tags->set_attribute( 'alt', '' );
			$tags->set_attribute( 'loading', $eager ? 'eager' : 'lazy' );
			if ( 1 === $state['image'] ) {
				$tags->set_attribute( 'fetchpriority', 'high' );
			} else {
				$tags->remove_attribute( 'fetchpriority' );
			}
		}
		return $tags->get_updated_html();
	}

	if ( ! $tags->has_class( STREAM_CLASS ) ) {
		return $html;
	}
	foreach ( array( 'cr-collection-card', 'cr-paper-sheet', 'cr-paper-sheet--torn', 'cr-paper--printer-ivory' ) as $class_name ) {
		$tags->add_class( $class_name );
	}

	$post_id = is_object( $instance ) && isset( $instance->context['postId'] )
		? (int) $instance->context['postId']
		: ( function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0 );
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) ) {
		$tags->set_attribute(
			'data-cr-rotate',
			(string) \Courtneyr\Child\Stamps\pick(
				\Courtneyr\Child\Stamps\seed( 'collection-card', (string) $post_id ),
				3,
				ROTATIONS
			)
		);
	}

	if (
		$tags->next_tag(
			array(
				'tag_name'   => 'DIV',
				'class_name' => 'pk-media',
			)
		)
	) {
		$tags->set_attribute( 'data-cr-cover-fallback', 'true' );
		if ( $tags->next_tag( 'IMG' ) ) {
			$tags->set_attribute( 'alt', '' );
			$tags->set_attribute( 'loading', 'lazy' );
			$tags->remove_attribute( 'fetchpriority' );
		}
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\paint_card', 10, 3 );

reset_page_state();
