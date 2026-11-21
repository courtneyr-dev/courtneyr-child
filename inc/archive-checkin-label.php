<?php
/**
 * Public Check-in disclosure on a selected kind archive row.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\ArchiveCheckin;

use PKIW\Grouped_Archive;
use PKIW\Kind_Facts;
use PKIW\Meta_Fields;
use PKIW\Public_Eligibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether PKIW explicitly selected this kind for this block and row.
 *
 * @param \WP_Post $post     Archive row.
 * @param mixed    $instance Rendered block instance.
 * @param string   $kind     Adapter's kind slug.
 */
function selected( \WP_Post $post, $instance, string $kind ): bool {
	return function_exists( '\\PKIW\\mixed_kind_archive_slug' )
		&& method_exists( Kind_Facts::class, 'get_for_archive_kind' )
		&& $instance instanceof \WP_Block
		&& (int) ( $instance->context['postId'] ?? 0 ) === (int) $post->ID
		&& \PKIW\mixed_kind_archive_slug( $post, $instance ) === $kind
		&& has_term( $kind, 'kind', $post )
		&& has_term( 'checkin', 'kind', $post );
}

/**
 * The real page map for duplicate names, including when this row is unmixed.
 *
 * @param int    $post_id  Archive row ID.
 * @param mixed  $instance Rendered block instance.
 * @param string $kind     Adapter's kind slug.
 * @return int[]
 */
function page_ids( int $post_id, $instance, string $kind ): array {
	if ( ! function_exists( '\\PKIW\\mixed_kind_archive_slug' )
		|| ! method_exists( Kind_Facts::class, 'get_for_archive_kind' )
		|| ! method_exists( Grouped_Archive::class, 'current_archive_kind' )
		|| ! $instance instanceof \WP_Block
		|| (int) ( $instance->context['postId'] ?? 0 ) !== $post_id
		|| Grouped_Archive::current_archive_kind() !== $kind
	) {
		return array();
	}
	$ids = Grouped_Archive::current_page_ids();
	return in_array( $post_id, $ids, true ) ? $ids : array();
}

/**
 * Selected-kind facts for a mixed peer in the validated page map.
 *
 * @param int    $post_id Peer row ID.
 * @param string $kind    Adapter's kind slug.
 * @return array<string, mixed>|null Null for an unmixed peer.
 */
function peer_facts( int $post_id, string $kind ): ?array {
	return has_term( 'checkin', 'kind', $post_id ) ? Kind_Facts::get_for_archive_kind( $post_id, $kind ) : null;
}

/**
 * Name for a selected card with sparse facts.
 *
 * @param \WP_Post $post Archive row.
 */
function fallback_name( \WP_Post $post ): string {
	$name = trim( \PKIW\visitor_post_title( $post ) );
	return '' !== $name ? $name : \PKIW\untitled_name( $post, false );
}

/**
 * Whether PKIW rendered a protected title-only shell.
 *
 * @param string $html PKIW's card before decoration.
 */
function is_protected( string $html ): bool {
	$tags = new \WP_HTML_Tag_Processor( $html );
	return $tags->next_tag() && $tags->has_class( 'pk-card--protected' );
}

/**
 * Render the secondary label only when the selected card may disclose it.
 *
 * @param \WP_Post $post     Archive row.
 * @param mixed    $instance Rendered block instance.
 * @param string   $kind     Adapter's kind slug.
 * @param string   $html     PKIW's card before decoration.
 */
function label( \WP_Post $post, $instance, string $kind, string $html ): string {
	$classes = array(
		'like'     => 'cr-like-sticker__checkin',
		'repost'   => 'cr-repost-clip__checkin',
		'favorite' => 'cr-fav-clipping__checkin',
		'jam'      => 'cr-jam-strip__checkin',
	);
	if ( ! isset( $classes[ $kind ] )
		|| '' === $html
		|| ! selected( $post, $instance, $kind )
		|| is_protected( $html )
		|| ! class_exists( Public_Eligibility::class )
		|| Public_Eligibility::hides( $post )
		|| ! Public_Eligibility::is_eligible( $post )
		|| ! method_exists( Meta_Fields::class, 'get_public_location_fields' )
		|| true !== ( Meta_Fields::get_public_location_fields( $post->ID )['name'] ?? null )
	) {
		return '';
	}

	$text     = __( 'Also a check-in', 'courtneyr-child' );
	$language = strtolower( explode( '-', get_bloginfo( 'language' ), 2 )[0] );
	$lang     = 'Also a check-in' === $text && 'en' !== $language ? ' lang="en-US"' : '';
	return '<span class="' . esc_attr( $classes[ $kind ] ) . '"' . $lang . '>' . esc_html( $text ) . '</span>';
}
