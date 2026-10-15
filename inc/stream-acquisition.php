<?php
/**
 * Stream acquisition stubs: a small receipt stub in the collage (PKIW issue 239).
 *
 * The plugin's Stream card for an acquisition prints the whole card. This
 * adapter replaces it with a stub built from the plugin's facts: an optional
 * local thumbnail, the title as the one link, the type label, the place as
 * plain text and one date. It prints no cost, notes or place URL.
 *
 * The stub is not an h-* root and its time carries no microformats class, so
 * the plugin's ensure_entry_properties() adds the hidden post date the entry
 * publishes, and the item's hidden h-cite comes from the plugin's own helper.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\StreamAcquisition;

use function Courtneyr\Child\Acquisition\block_post;
use function Courtneyr\Child\Acquisition\facts;
use function Courtneyr\Child\Acquisition\heading_level;
use function Courtneyr\Child\Acquisition\image_html;
use function Courtneyr\Child\Acquisition\local_picture;
use function Courtneyr\Child\Acquisition\name_of;
use function Courtneyr\Child\Acquisition\on_stream_surface;
use function Courtneyr\Child\Acquisition\plain_text;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND = 'acquisition';

/**
 * Build the stub for one acquisition.
 *
 * @param \WP_Post             $post  Post.
 * @param array<string, mixed> $facts Acquisition facts.
 * @param int                  $level Heading level.
 * @return string
 */
function stub_html( \WP_Post $post, array $facts, int $level ): string {
	$out = '<article class="cr-acq-stub cr-ticket">';

	$picture = local_picture( $facts );
	if ( array() !== $picture ) {
		$img = image_html(
			$picture,
			array(
				'class' => 'cr-acq-stub__img',
				'alt'   => '',
				'eager' => false,
				'size'  => 'medium',
				'sizes' => '(min-width: 64rem) 17rem, 80vw',
			)
		);
		if ( '' !== $img ) {
			$out .= '<figure class="cr-acq-stub__thumb" data-cr-cover-fallback>' . $img . '</figure>';
		}
	}

	$out .= '<h' . $level . ' class="cr-acq-stub__title"><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( name_of( $post, $facts ) ) . '</a></h' . $level . '>';

	$type = plain_text( (string) ( $facts['type_label'] ?? '' ) );
	if ( '' !== $type ) {
		$out .= '<p class="pk-kindlabel">' . esc_html( $type ) . '</p>';
	}
	$where = plain_text( (string) ( $facts['where'] ?? '' ) );
	if ( '' !== $where ) {
		$out .= '<p class="cr-acq-stub__where">' . esc_html( $where ) . '</p>';
	}

	// One date: the acquired day when the card stores it, else the post date. The time carries no mf2 class.
	if ( '' !== (string) ( $facts['acquired_at'] ?? '' ) ) {
		$iso     = (string) $facts['acquired_at'];
		$display = (string) ( $facts['acquired_display'] ?? '' );
	} else {
		$iso     = (string) get_the_date( 'Y-m-d', $post );
		$display = (string) get_the_date( '', $post );
	}
	$out .= '<p class="cr-acq-stub__date"><time datetime="' . esc_attr( $iso ) . '">' . esc_html( $display ) . '</time></p>';

	if ( function_exists( '\\PKIW\\stream_card_kind_cite' ) ) {
		$out .= (string) \PKIW\stream_card_kind_cite( $post );
	}
	return $out . '</article>';
}

/**
 * Replace an acquisition's Stream card with the stub.
 *
 * Acts on Stream surfaces only. An archive receipt, a protected post, a post
 * the reader returns nothing for and a site without the reader keep the card
 * the plugin printed.
 *
 * @param string               $html     Rendered stream card.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 * @return string
 */
function stub( string $html, array $block, $instance = null ): string {
	if ( '' === $html || ! on_stream_surface() ) {
		return $html;
	}
	if ( str_contains( (string) ( $block['attrs']['className'] ?? '' ), \Courtneyr\Child\Acquisition\RECEIPT_CLASS ) ) {
		return $html;
	}
	$post = block_post( $instance );
	if ( ! $post instanceof \WP_Post || ! has_term( KIND, 'kind', $post ) || post_password_required( $post ) ) {
		return $html;
	}
	$facts = facts( $post->ID );
	if ( array() === $facts ) {
		return $html;
	}
	return stub_html( $post, $facts, heading_level( $block, 2 ) );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\stub', 10, 3 );
