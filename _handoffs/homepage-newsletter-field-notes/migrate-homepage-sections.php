<?php
/**
 * Homepage sections migration: swap the inner blocks of the two homepage
 * sections for the courtneyr-child patterns, keeping each section wrapper
 * (its background colour, alignment, anchor-driven torn edges and position in
 * the page rotation) untouched.
 *
 * Usage:
 *
 *   wp eval-file migrate-homepage-sections.php dry-run [post_id]
 *   wp eval-file migrate-homepage-sections.php apply   [post_id]
 *   wp eval-file migrate-homepage-sections.php rollback <backup-file> [post_id]
 *   wp eval-file migrate-homepage-sections.php upgrade  [post_id]
 *   wp eval-file migrate-homepage-sections.php upgrade-templates
 *   wp eval-file migrate-homepage-sections.php lock     [post_id]
 *   wp eval-file migrate-homepage-sections.php unlock   [post_id]
 *
 * Append `preview` to upgrade, lock, unlock or upgrade-templates to run the
 * exact same transform, print a line-diff summary and write nothing (G-04).
 * Backups are verified and immutable (cr-migration-backup.php, G-03).
 *
 * `lock` / `unlock` (0.7.46, issue 04) add or remove `templateLock: all`
 * and the move/remove lock on the two section root Groups (the direct child of
 * each wrapper with class cr-reasons / cr-fieldnotes). Nothing else changes; a
 * backup and a revision are written.
 *
 * `upgrade-templates` (0.7.46) patches a saved `single` template override of
 * the active theme, if one exists, so its related-posts Query Loop carries
 * `pkiwSurface: main` and its title/date blocks carry the microformat
 * markers — the same edits templates/single.html received. Nothing else in
 * the override changes; a backup and a revision are written.
 *
 * `upgrade` (0.7.46) walks the saved page and swaps only the 0.7.44 html
 * placeholders: the four inline-SVG drawings become locked drawing Groups and
 * the two format-glyph spans become courtneyr/post-glyph blocks. Every other
 * block, including later editorial edits, is left byte-for-byte as saved.
 *
 * post_id defaults to the page set as the static front page. `apply` writes a
 * timestamped backup of the previous content to wp-content/cr-homepage-migration/
 * and updates the page through wp_update_post(), which also creates a revision.
 * `rollback` restores a backup file. Run locally first; production needs its own
 * authorization.
 *
 * No strict_types declaration: WP-CLI eval-file evaluates this file inside its own scope.
 *
 * @package CourtneyrChild
 */

require_once __DIR__ . '/cr-migration-backup.php';

$cr_mode    = (string) ( $args[0] ?? 'dry-run' );
$cr_preview = in_array( 'preview', array_map( 'strval', $args ), true ); // G-04: preview runs the same transform and writes nothing.
$cr_targets = array(
	'cr-home-features'   => 'courtneyr-child/cr-home-newsletter-reasons',
	'cr-home-fieldnotes' => 'courtneyr-child/cr-home-field-notes',
);
$cr_args    = array_values( array_filter( array_slice( $args, 1 ), static fn( $a ) => 'preview' !== $a ) );
$cr_post_id = (int) ( 'rollback' === $cr_mode ? ( $cr_args[1] ?? 0 ) : ( $cr_args[0] ?? 0 ) );
if ( ! $cr_post_id ) {
	$cr_post_id = (int) get_option( 'page_on_front' );
}
$cr_post = $cr_post_id ? get_post( $cr_post_id ) : null;

if ( ! $cr_post instanceof WP_Post || 'page' !== $cr_post->post_type ) {
	WP_CLI::error( 'No page found. Pass a page ID or set a static front page.' );
}
WP_CLI::log( sprintf( 'Target: page %d "%s" (%s)', $cr_post->ID, $cr_post->post_title, get_permalink( $cr_post ) ) );

$cr_backup_dir = WP_CONTENT_DIR . '/cr-homepage-migration';

/**
 * Upgrade: targeted block transforms, everything else untouched.
 *
 * @param array $blocks Parsed blocks.
 * @param int   $drawings Running count of drawings seen (by reference).
 * @param int   $glyphs   Running count of glyphs seen (by reference).
 * @return array
 */
function cr_upgrade_blocks( array $blocks, int &$drawings, int &$glyphs ): array {
	foreach ( $blocks as $i => $block ) {
		if ( 'core/html' === ( $block['blockName'] ?? '' ) ) {
			$html = (string) ( $block['innerHTML'] ?? '' );
			if ( str_contains( $html, 'cr-reasons__drawing' ) ) {
				++$drawings;
				$class     = sprintf( 'cr-reasons__drawing cr-reasons__drawing--%02d', $drawings );
				$markup    = sprintf( '<div class="wp-block-group %s"></div>', $class );
				$blocks[ $i ] = array(
					'blockName'    => 'core/group',
					'attrs'        => array(
						'className'    => $class,
						'templateLock' => 'all',
						'layout'       => array( 'type' => 'default' ),
					),
					'innerBlocks'  => array(),
					'innerHTML'    => $markup,
					'innerContent' => array( $markup ),
				);
				continue;
			}
			if ( str_contains( $html, 'data-cr-card-glyph' ) ) {
				++$glyphs;
				$blocks[ $i ] = array(
					'blockName'    => 'courtneyr/post-glyph',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);
				continue;
			}
		}
		if ( 'core/post-terms' === ( $block['blockName'] ?? '' ) && str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'cr-home-story__chips' ) ) {
			// 0.7.46: the chips block renders the same Core post-terms + theme
			// resolver on the server, so the editor preview matches the front end.
			$blocks[ $i ] = array(
				'blockName'    => 'courtneyr/term-chips',
				'attrs'        => array( 'className' => 'cr-home-story__chips' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
			continue;
		}
		if ( 'core/post-title' === ( $block['blockName'] ?? '' ) ) {
			$class = (string) ( $block['attrs']['className'] ?? '' );
			if ( str_contains( $class, 'cr-home-story__title' ) && ! str_contains( $class, 'cr-u-url' ) ) {
				$blocks[ $i ]['attrs']['className'] = trim( $class . ' cr-u-url' );
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$blocks[ $i ]['innerBlocks'] = cr_upgrade_blocks( $block['innerBlocks'], $drawings, $glyphs );
			// A homepage story without a date block gets the screen-reader-only
			// dt-published date right after its title row.
			if ( 'core/group' === ( $block['blockName'] ?? '' ) && str_contains( (string) ( $block['attrs']['className'] ?? '' ), 'cr-home-story' ) ) {
				$has_date = false;
				$row      = -1;
				foreach ( $blocks[ $i ]['innerBlocks'] as $j => $inner ) {
					if ( 'core/post-date' === ( $inner['blockName'] ?? '' ) ) {
						$has_date = true;
					}
					if ( 'core/group' === ( $inner['blockName'] ?? '' ) && str_contains( (string) ( $inner['attrs']['className'] ?? '' ), 'cr-home-story__title-row' ) ) {
						$row = $j;
					}
				}
				if ( ! $has_date && $row >= 0 ) {
					$date = array(
						'blockName'    => 'core/post-date',
						'attrs'        => array( 'className' => 'cr-home-story__date cr-dt-published screen-reader-text' ),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					);
					array_splice( $blocks[ $i ]['innerBlocks'], $row + 1, 0, array( $date ) );
					// innerContent holds one null per inner block; add a slot for the new block.
					$slot = array_search( null, $blocks[ $i ]['innerContent'], true );
					$nulls = array_keys( $blocks[ $i ]['innerContent'], null, true );
					if ( isset( $nulls[ $row ] ) ) {
						array_splice( $blocks[ $i ]['innerContent'], $nulls[ $row ] + 1, 0, array( null ) );
					}
				}
			}
		}
	}
	return $blocks;
}

if ( 'upgrade-templates' === $cr_mode ) {
	$cr_q = new WP_Query(
		array(
			'post_type'      => 'wp_template',
			'post_status'    => 'publish',
			'name'           => 'single',
			'posts_per_page' => 1,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => get_stylesheet(),
				),
			),
		)
	);
	if ( empty( $cr_q->posts ) ) {
		WP_CLI::success( 'No saved single template override for ' . get_stylesheet() . '; the theme file applies as is.' );
		return;
	}
	$cr_tpl     = $cr_q->posts[0];
	$cr_content = $cr_tpl->post_content;
	$cr_new     = $cr_content;
	// Related loop: add the surface key to the first query that lacks one inside the related-posts group.
	$cr_new = preg_replace_callback(
		'/(<!-- wp:query \{[^\n]*?"className":"related-posts__query"[^\n]*? -->)/',
		static function ( $m ) {
			$open = $m[1];
			if ( str_contains( $open, 'pkiwSurface' ) ) {
				return $open;
			}
			return str_replace( '"inherit":false}', '"inherit":false,"pkiwSurface":"main"}', $open );
		},
		$cr_new,
		1
	);
	// Older overrides may carry the query attrs before the className; try the generic shape too.
	if ( ! str_contains( $cr_new, 'pkiwSurface' ) ) {
		$cr_new = preg_replace( '/("className":"related-posts__query")/', '$1', $cr_new );
		$cr_new = preg_replace_callback(
			'/<!-- wp:query \{("queryId":1,)?[^\n]*"query":\{[^}]*"inherit":false\}[^\n]*related-posts__query[^\n]*-->/',
			static function ( $m ) {
				return str_replace( '"inherit":false}', '"inherit":false,"pkiwSurface":"main"}', $m[0] );
			},
			$cr_new,
			1
		);
	}
	$cr_new = str_replace( '"className":"related-post__title is-style-show-format-title"', '"className":"related-post__title is-style-show-format-title p-name cr-u-url"', $cr_new );
	$cr_new = str_replace( '"className":"related-post__date"', '"className":"related-post__date cr-dt-published"', $cr_new );
	if ( $cr_new === $cr_content ) {
		if ( str_contains( $cr_content, '"pkiwSurface":"main"' ) && str_contains( $cr_content, 'cr-dt-published' ) ) {
			WP_CLI::success( sprintf( 'Single template override %d already has the 0.7.46 shape.', $cr_tpl->ID ) );
			return;
		}
		WP_CLI::error( sprintf( 'Single template override %d does not contain the expected related-posts Query Loop (className related-posts__query with inherit:false); inspect it before migrating. Nothing changed.', $cr_tpl->ID ) );
	}
	if ( ! wp_mkdir_p( $cr_backup_dir ) ) {
		WP_CLI::error( 'Cannot create the backup directory.' );
	}
	$cr_backup = sprintf( '%s/template-single-%d-%s.html', $cr_backup_dir, $cr_tpl->ID, gmdate( 'Ymd-His' ) );
	WP_CLI::log( cr_migration_diff_summary( $cr_content, $cr_new ) );
	if ( $cr_preview ) {
		WP_CLI::success( 'Preview only; nothing written.' );
		return;
	}
	$cr_backup = cr_migration_write_backup( sprintf( 'template-%d-single-override', $cr_tpl->ID ), $cr_content, 'wp_template:' . $cr_tpl->ID );
	cr_migration_update_content( $cr_tpl->ID, $cr_new );
	WP_CLI::success( sprintf( 'Patched single template override %d (pkiwSurface on the related loop, microformat markers). Backup: %s.', $cr_tpl->ID, $cr_backup ) );
	return;
}

if ( 'lock' === $cr_mode || 'unlock' === $cr_mode ) {
	if ( ! $cr_post instanceof WP_Post ) {
		WP_CLI::error( 'No target page.' );
	}
	$cr_roots  = array( 'cr-reasons', 'cr-fieldnotes' );
	$cr_hits   = 0;
	$cr_walker = static function ( array $blocks ) use ( &$cr_walker, &$cr_hits, $cr_roots, $cr_mode ): array {
		foreach ( $blocks as $i => $block ) {
			$classes = preg_split( '/\s+/', trim( (string) ( $block['attrs']['className'] ?? '' ) ) );
			if ( 'core/group' === ( $block['blockName'] ?? '' ) && is_array( $classes ) && array_intersect( $classes, $cr_roots ) ) {
				if ( 'lock' === $cr_mode ) {
					$blocks[ $i ]['attrs'] = array_merge( array( 'templateLock' => 'all', 'lock' => array( 'move' => true, 'remove' => true ) ), $block['attrs'] );
					$blocks[ $i ]['attrs']['templateLock'] = 'all';
					$blocks[ $i ]['attrs']['lock']         = array( 'move' => true, 'remove' => true );
				} else {
					unset( $blocks[ $i ]['attrs']['templateLock'], $blocks[ $i ]['attrs']['lock'] );
				}
				++$cr_hits;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = $cr_walker( $block['innerBlocks'] );
			}
		}
		return $blocks;
	};
	$cr_blocks = $cr_walker( parse_blocks( $cr_post->post_content ) );
	$cr_new    = serialize_blocks( $cr_blocks );
	WP_CLI::log( sprintf( 'Target: page %d "%s" — %d section root(s) found.', $cr_post->ID, $cr_post->post_title, $cr_hits ) );
	if ( $cr_new === $cr_post->post_content ) {
		WP_CLI::success( 'Nothing to change.' );
		return;
	}
	WP_CLI::log( cr_migration_diff_summary( $cr_post->post_content, $cr_new ) );
	if ( $cr_preview ) {
		WP_CLI::success( 'Preview only; nothing written.' );
		return;
	}
	$cr_backup = cr_migration_write_backup( sprintf( 'page-%d-%s', $cr_post->ID, $cr_mode ), $cr_post->post_content, 'post:' . $cr_post->ID );
	cr_migration_update_content( $cr_post->ID, $cr_new );
	WP_CLI::success( sprintf( '%s applied to page %d. Backup: %s (also a revision).', ucfirst( $cr_mode ), $cr_post->ID, $cr_backup ) );
	return;
}

if ( 'upgrade' === $cr_mode ) {
	if ( ! $cr_post instanceof WP_Post ) {
		WP_CLI::error( 'No target page.' );
	}
	$cr_drawings = 0;
	$cr_glyphs   = 0;
	$cr_blocks   = cr_upgrade_blocks( parse_blocks( $cr_post->post_content ), $cr_drawings, $cr_glyphs );
	WP_CLI::log( sprintf( 'Target: page %d "%s" — %d drawing placeholder(s), %d glyph placeholder(s) found.', $cr_post->ID, $cr_post->post_title, $cr_drawings, $cr_glyphs ) );
	$cr_new = serialize_blocks( $cr_blocks );
	if ( $cr_new === $cr_post->post_content ) {
		WP_CLI::success( 'Nothing to upgrade; the page already has the 0.7.46 shape.' );
		return;
	}
	WP_CLI::log( cr_migration_diff_summary( $cr_post->post_content, $cr_new ) );
	if ( $cr_preview ) {
		WP_CLI::success( 'Preview only; nothing written.' );
		return;
	}
	$cr_backup = cr_migration_write_backup( sprintf( 'page-%d-upgrade', $cr_post->ID ), $cr_post->post_content, 'post:' . $cr_post->ID );
	cr_migration_update_content( $cr_post->ID, $cr_new );
	WP_CLI::success( sprintf( 'Upgraded page %d. Backup: %s (also available as a revision).', $cr_post->ID, $cr_backup ) );
	return;
}

if ( 'rollback' === $cr_mode ) {
	$cr_file = (string) ( $args[1] ?? '' );
	if ( '' === $cr_file || ! is_readable( $cr_file ) ) {
		WP_CLI::error( 'rollback needs a readable backup file path.' );
	}
	$cr_contents = (string) file_get_contents( $cr_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	cr_migration_update_content( $cr_post->ID, $cr_contents );
	WP_CLI::success( sprintf( 'Restored %s into page %d (sha256 %s).', basename( $cr_file ), $cr_post->ID, substr( hash( 'sha256', $cr_contents ), 0, 12 ) ) );
	return;
}

$cr_registry = WP_Block_Patterns_Registry::get_instance();
$cr_blocks   = parse_blocks( $cr_post->post_content );
$cr_replaced = array();

foreach ( $cr_blocks as $cr_index => $cr_block ) {
	$cr_class = (string) ( $cr_block['attrs']['className'] ?? '' );
	foreach ( $cr_targets as $cr_marker => $cr_pattern_slug ) {
		$cr_classes = preg_split( '/\s+/', trim( $cr_class ) );
		if ( ! is_array( $cr_classes ) || ! in_array( $cr_marker, $cr_classes, true ) ) {
			continue;
		}
		$cr_pattern = $cr_registry->get_registered( $cr_pattern_slug );
		if ( ! $cr_pattern ) {
			WP_CLI::error( sprintf( 'Pattern %s is not registered. Activate the theme version that ships it.', $cr_pattern_slug ) );
		}
		$cr_new_inner                           = array_values( array_filter( parse_blocks( $cr_pattern['content'] ), static fn( $b ) => ! empty( $b['blockName'] ) ) );
		$cr_strings                             = array_values( array_filter( $cr_block['innerContent'], 'is_string' ) );
		$cr_open                                = $cr_strings[0] ?? '';
		$cr_close                               = count( $cr_strings ) > 1 ? end( $cr_strings ) : '';
		$cr_content                             = array_merge( array( $cr_open ), array_fill( 0, count( $cr_new_inner ), null ), array( $cr_close ) );
		$cr_old_names                           = array_map( static fn( $b ) => (string) $b['blockName'], array_filter( $cr_block['innerBlocks'], static fn( $b ) => ! empty( $b['blockName'] ) ) );
		$cr_blocks[ $cr_index ]['innerBlocks']  = $cr_new_inner;
		$cr_blocks[ $cr_index ]['innerContent'] = $cr_content;
		$cr_replaced[]                          = sprintf( 'block #%d (%s): %d inner block(s) [%s] -> %d block(s) from %s', $cr_index, $cr_marker, count( $cr_old_names ), implode( ', ', $cr_old_names ), count( $cr_new_inner ), $cr_pattern_slug );
	}
}

if ( count( $cr_replaced ) !== count( $cr_targets ) ) {
	WP_CLI::warning( sprintf( 'Matched %d of %d sections. Top-level classes: %s', count( $cr_replaced ), count( $cr_targets ), implode( ' | ', array_map( static fn( $b ) => (string) ( $b['blockName'] ?? '(html)' ) . ' .' . (string) ( $b['attrs']['className'] ?? '' ), $cr_blocks ) ) ) );
}
foreach ( $cr_replaced as $cr_line ) {
	WP_CLI::log( '  ' . $cr_line );
}

if ( 'apply' !== $cr_mode ) {
	WP_CLI::success( 'Dry run only; nothing written. Re-run with "apply" to migrate.' );
	return;
}
if ( empty( $cr_replaced ) ) {
	WP_CLI::error( 'Nothing to replace; aborting.' );
}

$cr_backup = cr_migration_write_backup( sprintf( 'page-%d-apply', $cr_post->ID ), $cr_post->post_content, 'post:' . $cr_post->ID );
cr_migration_update_content( $cr_post->ID, serialize_blocks( $cr_blocks ) );
WP_CLI::success( sprintf( 'Migrated page %d. Backup: %s (also available as a revision).', $cr_post->ID, $cr_backup ) );
