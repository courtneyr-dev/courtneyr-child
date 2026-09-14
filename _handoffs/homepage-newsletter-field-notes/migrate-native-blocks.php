<?php
/**
 * Native-block migration for saved Site Editor overrides (R-13, R-15, R-24).
 *
 * A theme-file change does not reach a site that has a saved override of the
 * same template or template part. This script carries this branch's edits of
 * parts/header.html, parts/footer.html and templates/single.html into a saved
 * override of the active theme and changes only the targeted blocks; every
 * other saved byte stays as saved.
 *
 * Usage:
 *
 *   wp eval-file migrate-native-blocks.php header   [preview]
 *   wp eval-file migrate-native-blocks.php footer   [preview]
 *   wp eval-file migrate-native-blocks.php single   [preview]
 *   wp eval-file migrate-native-blocks.php rollback <backup-file> <object> [preview]
 *
 * header: the three core/html blocks of the saved `header` part become
 *   core/site-title (cr-wordmark style), the appearance-toggle template part
 *   and core/search (cr-header-search style, the saved label and placeholder).
 * footer: the colophon paragraph of the saved `footer` part that starts with
 *   "© <year>" becomes a Copyright group: a paragraph bound to
 *   courtneyr/current-year (prefix "© ") and the rest of the saved paragraph.
 * single: in the saved `single` template, the related-posts Query Loop gets the
 *   courtneyr/related-posts namespace (it must already carry pkiwSurface main
 *   and the microformat classes; run upgrade-templates in
 *   migrate-homepage-sections.php first), the Post Content block gets
 *   layout.contentSize 100%, and the `kind` template part goes right after it.
 *
 * Contract (shared with migrate-homepage-sections.php through cr-migration-backup.php):
 * - `preview` runs the same transform, prints the line diff and writes nothing.
 * - A write first stores a hash-verified backup with cr_migration_write_backup()
 *   (manifest object `wp_template_part:<id>` or `wp_template:<id>`), then
 *   updates through cr_migration_update_content(), which re-reads the bytes.
 * - `rollback` restores only through cr_migration_read_verified_backup(): the
 *   file needs a manifest line on this site whose sha256 matches and whose
 *   object is exactly <object>; the target must still exist with that post
 *   type. The current content is backed up first, so a rollback is reversible.
 * - Refusals change nothing: an override that does not survive
 *   parse_blocks() -> serialize_blocks() byte for byte (a block-level rewrite
 *   would then touch untargeted bytes), a missing, duplicated or unexpected
 *   target, or more than one override for the slug. No override for the active
 *   theme, or an override already in the new shape, reports success and writes
 *   nothing.
 *
 * Blocks are found with the block parser and the HTML API; no regular
 * expression reads or rewrites HTML. Run it with --user=<an administrator> so
 * kses does not filter the saved template markup on write.
 *
 * No strict_types declaration: WP-CLI eval-file evaluates this file inside its own scope.
 *
 * @package CourtneyrChild
 */

if ( ! function_exists( 'cr_nb_block' ) ) {
	/**
	 * A parsed block with no inner content (dynamic blocks serialize self-closing).
	 *
	 * @param string               $name  Block name.
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<string, mixed>
	 */
	function cr_nb_block( string $name, array $attrs ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Class names of a parsed block.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return string[]
	 */
	function cr_nb_classes( array $block ): array {
		$list = preg_split( '/\s+/', trim( (string) ( $block['attrs']['className'] ?? '' ) ) );
		return is_array( $list ) ? array_values( array_filter( $list ) ) : array();
	}

	/**
	 * Depth-first walk: children first, then $visit( $block ) returns the block to keep.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param callable                         $visit  Visitor.
	 * @return array<int, array<string, mixed>>
	 */
	function cr_nb_walk( array $blocks, callable $visit ): array {
		foreach ( $blocks as $i => $block ) {
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = cr_nb_walk( $block['innerBlocks'], $visit );
			}
			$blocks[ $i ] = $visit( $block );
		}
		return $blocks;
	}

	/**
	 * Count blocks matching a predicate anywhere in the tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param callable                         $predicate Predicate.
	 * @return int
	 */
	function cr_nb_count( array $blocks, callable $predicate ): int {
		$n = 0;
		foreach ( $blocks as $block ) {
			if ( $predicate( $block ) ) {
				++$n;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$n += cr_nb_count( $block['innerBlocks'], $predicate );
			}
		}
		return $n;
	}

	/**
	 * The one saved override of the active theme for a slug, or null.
	 *
	 * @param string $post_type wp_template or wp_template_part.
	 * @param string $slug      Template slug.
	 * @return WP_Post|null
	 */
	function cr_nb_find_override( string $post_type, string $slug ): ?WP_Post {
		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				// post_name__in, not name: a name query is singular and WP_Query skips tax_query for it.
				'post_name__in'  => array( $slug ),
				'posts_per_page' => 2,
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => get_stylesheet(),
					),
				),
			)
		);
		if ( count( $query->posts ) > 1 ) {
			WP_CLI::error( sprintf( 'More than one saved %s "%s" for %s (%s); resolve that by hand. Nothing changed.', $post_type, $slug, get_stylesheet(), implode( ', ', wp_list_pluck( $query->posts, 'ID' ) ) ) );
		}
		return $query->posts[0] ?? null;
	}

	/**
	 * Header: the three core/html blocks become native blocks.
	 *
	 * @param string $content Saved content.
	 * @return array{0: string, 1: array<string, int>}
	 */
	function cr_nb_transform_header( string $content ): array {
		$found    = array(
			'wordmark'   => 0,
			'appearance' => 0,
			'search'     => 0,
		);
		$blocks   = cr_nb_walk(
			parse_blocks( $content ),
			static function ( array $block ) use ( &$found ): array {
				if ( 'core/html' !== ( $block['blockName'] ?? '' ) ) {
					return $block;
				}
				$html = (string) $block['innerHTML'];
				$tags = new WP_HTML_Tag_Processor( $html );
				if ( ! $tags->next_tag() ) {
					return $block;
				}
				if ( 'A' === $tags->get_tag() && $tags->has_class( 'cr-wordmark' ) ) {
					++$found['wordmark'];
					return cr_nb_block(
						'core/site-title',
						array(
							'level'     => 0,
							'className' => 'is-style-cr-wordmark',
						)
					);
				}
				if ( 'DIV' === $tags->get_tag() && $tags->has_class( 'cr-theme-segments' ) ) {
					++$found['appearance'];
					return cr_nb_block(
						'core/template-part',
						array(
							'slug'      => 'appearance-toggle',
							'tagName'   => 'div',
							'className' => 'site-header__appearance',
						)
					);
				}
				if ( 'FORM' === $tags->get_tag() && $tags->has_class( 'site-header__search' ) ) {
					++$found['search'];
					$label       = 'Search the site';
					$placeholder = 'Search…';
					$fields      = new WP_HTML_Tag_Processor( $html );
					while ( $fields->next_tag() ) {
						if ( 'LABEL' === $fields->get_tag() && $fields->next_token() && '#text' === $fields->get_token_name() && '' !== trim( $fields->get_modifiable_text() ) ) {
							$label = trim( $fields->get_modifiable_text() );
						} elseif ( 'INPUT' === $fields->get_tag() && is_string( $fields->get_attribute( 'placeholder' ) ) ) {
							$placeholder = $fields->get_attribute( 'placeholder' );
						}
					}
					return cr_nb_block(
						'core/search',
						array(
							'label'          => $label,
							'showLabel'      => false,
							'placeholder'    => $placeholder,
							'buttonText'     => 'Search',
							'buttonPosition' => 'no-button',
							'className'      => 'site-header__search is-style-cr-header-search',
						)
					);
				}
				return $block;
			}
		);
		$migrated = cr_nb_count( $blocks, static fn( $b ) => 'core/site-title' === ( $b['blockName'] ?? '' ) && in_array( 'is-style-cr-wordmark', cr_nb_classes( $b ), true ) );
		if ( 0 === array_sum( $found ) && 1 === $migrated ) {
			return array( $content, $found );
		}
		if ( array( 1, 1, 1 ) !== array_values( $found ) ) {
			WP_CLI::error( sprintf( 'Expected exactly one wordmark, appearance and search html block; found %s. Inspect the override. Nothing changed.', wp_json_encode( $found ) ) );
		}
		return array( serialize_blocks( $blocks ), $found );
	}

	/**
	 * Footer: the "© <year> …" colophon paragraph becomes the bound Copyright group.
	 *
	 * @param string $content Saved content.
	 * @return array{0: string, 1: array<string, mixed>}
	 */
	function cr_nb_transform_footer( string $content ): array {
		$report = array(
			'colophon'  => 0,
			'copyright' => 0,
			'migrated'  => 0,
			'year'      => null,
		);
		$blocks = cr_nb_walk(
			parse_blocks( $content ),
			static function ( array $group ) use ( &$report ): array {
				if ( 'core/group' !== ( $group['blockName'] ?? '' ) || ! in_array( 'site-footer__colophon', cr_nb_classes( $group ), true ) ) {
					return $group;
				}
				++$report['colophon'];
				foreach ( $group['innerBlocks'] as $j => $child ) {
					if ( 'core/group' === ( $child['blockName'] ?? '' ) && in_array( 'site-footer__copyright', cr_nb_classes( $child ), true ) ) {
						++$report['migrated'];
						continue;
					}
					if ( 'core/paragraph' !== ( $child['blockName'] ?? '' ) ) {
						continue;
					}
					$tags = new WP_HTML_Tag_Processor( (string) $child['innerHTML'] );
					if ( ! $tags->next_tag( 'P' ) || ! $tags->next_token() || '#text' !== $tags->get_token_name() ) {
						continue;
					}
					$text = $tags->get_modifiable_text();
					// Plain text of the first text node, not HTML.
					if ( ! preg_match( '/\A©\s*([0-9]{4})\s*/u', $text, $m ) ) {
						continue;
					}
					++$report['copyright'];
					$report['year'] = $m[1];
					$tags->set_modifiable_text( substr( $text, strlen( $m[0] ) ) );
					$rest_html                  = $tags->get_updated_html();
					$rest                       = $child;
					$rest['innerHTML']          = $rest_html;
					$rest['innerContent']       = array( $rest_html );
					$year_html                  = sprintf( "\n<p class=\"site-footer__year\">© %s</p>\n", $m[1] );
					$year                       = array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(
							'metadata'  => array(
								'bindings' => array(
									'content' => array(
										'source' => 'courtneyr/current-year',
										'args'   => array( 'prefix' => '© ' ),
									),
								),
							),
							'className' => 'site-footer__year',
						),
						'innerBlocks'  => array(),
						'innerHTML'    => $year_html,
						'innerContent' => array( $year_html ),
					);
					$open                       = '<div class="wp-block-group site-footer__copyright cr-inline-run has-text-align-center">';
					$wrapper                    = array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'metadata'  => array( 'name' => 'Copyright' ),
							'className' => 'site-footer__copyright cr-inline-run has-text-align-center',
							'layout'    => array( 'type' => 'default' ),
						),
						'innerBlocks'  => array( $year, $rest ),
						'innerHTML'    => $open . "\n\n" . '</div>',
						'innerContent' => array( $open, null, "\n\n", null, '</div>' ),
					);
					$group['innerBlocks'][ $j ] = $wrapper;
				}
				return $group;
			}
		);
		if ( 1 === $report['colophon'] && 0 === $report['copyright'] && 1 === $report['migrated'] ) {
			return array( $content, $report );
		}
		if ( 1 !== $report['colophon'] || 1 !== $report['copyright'] || 0 !== $report['migrated'] ) {
			WP_CLI::error( sprintf( 'Expected one colophon group with one "© <year>" paragraph; found %s. Inspect the override. Nothing changed.', wp_json_encode( $report ) ) );
		}
		return array( serialize_blocks( $blocks ), $report );
	}

	/**
	 * Single: related-posts namespace, Post Content contentSize, `kind` part after Post Content.
	 *
	 * @param string $content Saved content.
	 * @return array{0: string, 1: array<string, mixed>}
	 */
	function cr_nb_transform_single( string $content ): array {
		$parsed = parse_blocks( $content );
		$report = array(
			'related_query' => cr_nb_count( $parsed, static fn( $b ) => 'core/query' === ( $b['blockName'] ?? '' ) && in_array( 'related-posts__query', cr_nb_classes( $b ), true ) ),
			'post_content'  => cr_nb_count( $parsed, static fn( $b ) => 'core/post-content' === ( $b['blockName'] ?? '' ) ),
			'kind_part'     => cr_nb_count( $parsed, static fn( $b ) => 'core/template-part' === ( $b['blockName'] ?? '' ) && 'kind' === ( $b['attrs']['slug'] ?? '' ) ),
			'changes'       => array(),
		);
		if ( 1 !== $report['related_query'] || 1 !== $report['post_content'] || $report['kind_part'] > 1 ) {
			WP_CLI::error( sprintf( 'Expected one related-posts Query Loop and one Post Content block; found %s. Inspect the override. Nothing changed.', wp_json_encode( $report ) ) );
		}
		$problems = array();
		$blocks   = cr_nb_walk(
			$parsed,
			static function ( array $block ) use ( &$report, &$problems ): array {
				$name = (string) ( $block['blockName'] ?? '' );
				if ( 'core/query' === $name && in_array( 'related-posts__query', cr_nb_classes( $block ), true ) ) {
					if ( 'main' !== ( $block['attrs']['query']['pkiwSurface'] ?? '' ) ) {
						$problems[] = 'the related-posts Query Loop has no pkiwSurface main (run upgrade-templates first)';
					}
					$has_title = cr_nb_count( $block['innerBlocks'], static fn( $b ) => 'core/post-title' === ( $b['blockName'] ?? '' ) && ! array_diff( array( 'p-name', 'cr-u-url' ), cr_nb_classes( $b ) ) );
					$has_date  = cr_nb_count( $block['innerBlocks'], static fn( $b ) => 'core/post-date' === ( $b['blockName'] ?? '' ) && in_array( 'cr-dt-published', cr_nb_classes( $b ), true ) );
					if ( ! $has_title || ! $has_date ) {
						$problems[] = 'the related-posts loop lacks the p-name cr-u-url title or the cr-dt-published date (run upgrade-templates first)';
					}
					$namespace = $block['attrs']['namespace'] ?? null;
					if ( null === $namespace ) {
						$block['attrs']['namespace'] = 'courtneyr/related-posts';
						$report['changes'][]         = 'related namespace';
					} elseif ( 'courtneyr/related-posts' !== $namespace ) {
						$problems[] = sprintf( 'the related-posts Query Loop already has namespace %s', $namespace );
					}
					return $block;
				}
				if ( 'core/post-content' === $name ) {
					$size = $block['attrs']['layout']['contentSize'] ?? null;
					if ( null === $size ) {
						$block['attrs']['layout'] = array_merge( (array) ( $block['attrs']['layout'] ?? array( 'type' => 'constrained' ) ), array( 'contentSize' => '100%' ) );
						$report['changes'][]      = 'post-content contentSize';
					} elseif ( '100%' !== $size ) {
						$problems[] = sprintf( 'the Post Content block already has contentSize %s', $size );
					}
					return $block;
				}
				if ( 0 === $report['kind_part'] && ! empty( $block['innerBlocks'] ) ) {
					foreach ( $block['innerBlocks'] as $j => $child ) {
						if ( 'core/post-content' !== ( $child['blockName'] ?? '' ) ) {
							continue;
						}
						$slots     = array_keys( $block['innerContent'], null, true );
						$slot      = $slots[ $j ];
						$before    = $block['innerContent'][ $slot - 1 ] ?? '';
						$separator = is_string( $before ) && '' === trim( $before ) && '' !== $before ? $before : "\n\n";
						array_splice( $block['innerContent'], $slot + 1, 0, array( $separator, null ) );
						array_splice( $block['innerBlocks'], $j + 1, 0, array( cr_nb_block( 'core/template-part', array( 'slug' => 'kind' ) ) ) );
						$report['kind_part'] = 1;
						$report['changes'][] = 'kind part';
						break;
					}
				}
				return $block;
			}
		);
		if ( $problems ) {
			WP_CLI::error( 'Refusing: ' . implode( '; ', $problems ) . '. Nothing changed.' );
		}
		if ( 1 !== $report['kind_part'] ) {
			WP_CLI::error( 'The Post Content block is not inside a group, so the kind part has no place to go. Inspect the override. Nothing changed.' );
		}
		return array( serialize_blocks( $blocks ), $report );
	}

	/**
	 * Preview or apply one mode against the active theme's saved override.
	 *
	 * @param string   $post_type wp_template or wp_template_part.
	 * @param string   $slug      Slug.
	 * @param callable $transform Transform returning [content, report].
	 * @param bool     $preview   Write nothing.
	 */
	function cr_nb_run( string $post_type, string $slug, callable $transform, bool $preview ): void {
		$post = cr_nb_find_override( $post_type, $slug );
		if ( ! $post instanceof WP_Post ) {
			WP_CLI::success( sprintf( 'No saved %s "%s" for %s; the theme file applies as is. Nothing changed.', $post_type, $slug, get_stylesheet() ) );
			return;
		}
		$object  = $post_type . ':' . $post->ID;
		$content = $post->post_content;
		WP_CLI::log( sprintf( 'Target: %s "%s" (%d bytes, sha256 %s)', $object, $post->post_title, strlen( $content ), hash( 'sha256', $content ) ) );
		if ( serialize_blocks( parse_blocks( $content ) ) !== $content ) {
			WP_CLI::error( sprintf( '%s does not re-serialize byte for byte, so a block-level rewrite would change untargeted bytes. Inspect it by hand. Nothing changed.', $object ) );
		}
		list( $new, $report ) = $transform( $content );
		WP_CLI::log( 'Targets: ' . wp_json_encode( $report, JSON_UNESCAPED_UNICODE ) );
		if ( $new === $content ) {
			WP_CLI::success( sprintf( '%s already has the new blocks. Nothing changed.', $object ) );
			return;
		}
		WP_CLI::log( cr_migration_diff_summary( $content, $new ) );
		WP_CLI::log( sprintf( 'After: %d bytes, sha256 %s', strlen( $new ), hash( 'sha256', $new ) ) );
		if ( $preview ) {
			WP_CLI::success( 'Preview only; nothing written.' );
			return;
		}
		$backup = cr_migration_write_backup( sprintf( '%s-%d-%s-native-blocks', $post_type, $post->ID, $slug ), $content, $object );
		cr_migration_update_content( $post->ID, $new );
		WP_CLI::success( sprintf( 'Migrated %s. Backup: %s (also a revision).', $object, basename( $backup ) ) );
	}
}

require_once __DIR__ . '/cr-migration-backup.php';

$cr_nb_mode    = (string) ( $args[0] ?? '' );
$cr_nb_preview = in_array( 'preview', array_map( 'strval', $args ), true );
$cr_nb_args    = array_values( array_filter( array_slice( $args, 1 ), static fn( $a ) => 'preview' !== $a ) );

if ( 'rollback' === $cr_nb_mode ) {
	$cr_nb_file   = (string) ( $cr_nb_args[0] ?? '' );
	$cr_nb_object = (string) ( $cr_nb_args[1] ?? '' );
	$cr_nb_parsed = cr_migration_parse_object( $cr_nb_object );
	if ( null === $cr_nb_parsed || 'post' === $cr_nb_parsed['type'] ) {
		WP_CLI::error( 'rollback needs <backup-file> <object>, where object is wp_template:<id> or wp_template_part:<id>. Nothing changed.' );
	}
	$cr_nb_contents = cr_migration_read_verified_backup( $cr_nb_file, $cr_nb_object );
	$cr_nb_target   = get_post( $cr_nb_parsed['id'] );
	if ( ! $cr_nb_target instanceof WP_Post || ! in_array( $cr_nb_target->post_type, $cr_nb_parsed['post_types'], true ) ) {
		WP_CLI::error( sprintf( 'Target %s no longer exists with the recorded type. Nothing changed.', $cr_nb_object ) );
	}
	WP_CLI::log( sprintf( 'Target: %s "%s" (%d bytes, sha256 %s)', $cr_nb_object, $cr_nb_target->post_title, strlen( $cr_nb_target->post_content ), hash( 'sha256', $cr_nb_target->post_content ) ) );
	if ( $cr_nb_target->post_content === $cr_nb_contents ) {
		WP_CLI::success( sprintf( '%s already matches %s. Nothing to restore.', $cr_nb_object, basename( $cr_nb_file ) ) );
		return;
	}
	WP_CLI::log( cr_migration_diff_summary( $cr_nb_target->post_content, $cr_nb_contents ) );
	if ( $cr_nb_preview ) {
		WP_CLI::success( 'Preview only; nothing written.' );
		return;
	}
	$cr_nb_before = cr_migration_write_backup( sprintf( '%s-%d-before-rollback', $cr_nb_target->post_type, $cr_nb_target->ID ), $cr_nb_target->post_content, $cr_nb_object );
	cr_migration_update_content( $cr_nb_target->ID, $cr_nb_contents );
	WP_CLI::success( sprintf( 'Restored %s into %s (sha256 %s). Pre-rollback content: %s.', basename( $cr_nb_file ), $cr_nb_object, hash( 'sha256', $cr_nb_contents ), basename( $cr_nb_before ) ) );
	return;
}

$cr_nb_modes = array(
	'header' => array( 'wp_template_part', 'header', 'cr_nb_transform_header' ),
	'footer' => array( 'wp_template_part', 'footer', 'cr_nb_transform_footer' ),
	'single' => array( 'wp_template', 'single', 'cr_nb_transform_single' ),
);
if ( ! isset( $cr_nb_modes[ $cr_nb_mode ] ) ) {
	WP_CLI::error( 'Usage: wp eval-file migrate-native-blocks.php header|footer|single [preview] | rollback <backup-file> <object> [preview]' );
}
cr_nb_run( $cr_nb_modes[ $cr_nb_mode ][0], $cr_nb_modes[ $cr_nb_mode ][1], $cr_nb_modes[ $cr_nb_mode ][2], $cr_nb_preview );
