<?php
/**
 * Core Table blocks as zine tables: column labels for the phone layout.
 *
 * Courtney approved the zine table mockup on 2026-11-04: a paper sheet on
 * desktop, and on a phone each row becomes its own card with every value
 * carrying its column's name. CSS alone can't print a column's name next to
 * a cell, so this filter copies each header cell's text onto the body cells
 * of its column as `data-cr-label`, which `assets/css/blocks/core-table.css`
 * shows with `attr()` below 37.5rem.
 *
 * The cards change the table's CSS display, which drops table semantics in
 * some browsers, so the filter also states the roles (table, rowgroup, row,
 * columnheader, rowheader, cell). Screen readers keep reading a table at
 * every width.
 *
 * Architecture: no Ollie or core primitive labels cells; a block style can't
 * add attributes. Selected: small integration PHP through the HTML API (no
 * regular expressions), plus the per-block sheet that already loads only where a Table
 * block renders (BLOCK_STYLE_MAP in inc/enqueue.php).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Header cell texts of the first header row, in column order.
 *
 * @param string $html Rendered table HTML.
 * @return string[]
 */
function header_labels( string $html ): array {
	$labels  = array();
	$current = null;
	$in_head = false;
	$rows    = 0;
	$tokens  = new \WP_HTML_Tag_Processor( $html );
	while ( $tokens->next_token() ) {
		$type = $tokens->get_token_type();
		if ( '#tag' === $type ) {
			$tag    = $tokens->get_tag();
			$closer = $tokens->is_tag_closer();
			if ( 'THEAD' === $tag ) {
				$in_head = ! $closer;
				if ( $closer ) {
					break;
				}
			} elseif ( $in_head && 'TR' === $tag && ! $closer ) {
				++$rows;
			} elseif ( $in_head && 1 === $rows && in_array( $tag, array( 'TH', 'TD' ), true ) ) {
				if ( $closer ) {
					$labels[] = trim( (string) $current );
					$current  = null;
				} else {
					$current = '';
				}
			}
		} elseif ( '#text' === $type && null !== $current ) {
			// The HTML API returns text with character references already decoded.
			$current .= $tokens->get_modifiable_text();
		}
	}
	return $labels;
}

/**
 * Label each body cell with its column's header and state the table roles.
 *
 * @param string $html Rendered table HTML.
 * @return string
 */
function label_cells( string $html ): string {
	$labels = header_labels( $html );
	if ( ! $labels ) {
		return $html;
	}
	$tags    = new \WP_HTML_Tag_Processor( $html );
	$section = '';
	$column  = 0;
	$roles   = array(
		'TABLE' => 'table',
		'THEAD' => 'rowgroup',
		'TBODY' => 'rowgroup',
		'TFOOT' => 'rowgroup',
		'TR'    => 'row',
	);
	while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag = $tags->get_tag();
		if ( $tags->is_tag_closer() ) {
			if ( in_array( $tag, array( 'THEAD', 'TBODY', 'TFOOT' ), true ) ) {
				$section = '';
			}
			continue;
		}
		if ( isset( $roles[ $tag ] ) ) {
			$tags->set_attribute( 'role', $roles[ $tag ] );
			if ( 'TR' === $tag ) {
				$column = 0;
			} elseif ( 'TABLE' !== $tag ) {
				$section = $tag;
			}
			continue;
		}
		if ( ! in_array( $tag, array( 'TH', 'TD' ), true ) ) {
			continue;
		}
		if ( 'THEAD' === $section ) {
			$tags->set_attribute( 'role', 'columnheader' );
		} else {
			$tags->set_attribute( 'role', 'TH' === $tag ? 'rowheader' : 'cell' );
			if ( isset( $labels[ $column ] ) && '' !== $labels[ $column ] ) {
				$tags->set_attribute( 'data-cr-label', $labels[ $column ] );
			}
		}
		$span    = (int) ( $tags->get_attribute( 'colspan' ) ?? 1 );
		$column += max( 1, $span );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/table', __NAMESPACE__ . '\\label_cells' );
