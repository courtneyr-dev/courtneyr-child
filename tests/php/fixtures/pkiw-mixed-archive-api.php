<?php
/**
 * Test double for the public PKIW archive API proposed in repair PR 2.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace PKIW;

final class Grouped_Archive {
	public static function current_archive_kind(): string {
		return $GLOBALS['cr_archive_frame']['kind'] ?? '';
	}

	public static function current_page_ids(): array {
		return $GLOBALS['cr_archive_frame']['page_ids'] ?? array();
	}

	public static function group_of_post( string $kind, int $post_id ): object {
		return self::current_group();
	}

	public static function current_group(): object {
		return new class() {
			public function key(): string {
				return 'fixture-group';
			}

			public function slug(): string {
				return 'fixture-group';
			}
		};
	}
}

final class Kind_Facts {
	public static function get_for_archive_kind( int $post_id, string $kind ): array {
		if ( $kind !== Grouped_Archive::current_archive_kind() || ! in_array( $post_id, Grouped_Archive::current_page_ids(), true ) ) {
			throw new \LogicException( 'Read selected facts outside the archive page map.' );
		}
		return $GLOBALS['cr_archive_facts'][ $post_id ] ?? array();
	}
}

function mixed_kind_archive_slug( \WP_Post $post, \WP_Block $instance ): string {
	if ( $instance !== ( $GLOBALS['cr_archive_frame']['block'] ?? null ) || $post->ID !== ( $instance->context['postId'] ?? 0 ) ) {
		return '';
	}
	return Grouped_Archive::current_archive_kind();
}
