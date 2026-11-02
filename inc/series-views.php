<?php
/**
 * Series views rendered by the `courtneyr/series-guide` block.
 *
 * Every list here comes from Series\parts(), so it holds published,
 * password-free posts only: no draft title, count or link reaches a visitor.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Series;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render one view.
 *
 * @param string    $view  View name from the block attribute.
 * @param \WP_Block $block Block instance (post context for single views).
 */
function render_view( string $view, $block ): string {
	if ( ! is_available() ) {
		return '';
	}
	if ( wp_is_serving_rest_request() ) {
		/* translators: %s: view name. */
		return '<p class="cr-series-placeholder">' . esc_html( sprintf( __( 'Series: %s (renders on the front end)', 'courtneyr-child' ), $view ) ) . '</p>';
	}
	if ( 'post-header' === $view || 'post-nav' === $view ) {
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		return 'post-header' === $view ? render_post_header( $post_id ) : render_post_nav( $post_id );
	}
	if ( 'overview' === $view ) {
		return render_overview();
	}
	$term = get_queried_object();
	if ( ! $term instanceof \WP_Term ) {
		return '';
	}
	if ( 'group' === $view ) {
		return GROUP_TAX === $term->taxonomy ? render_group( $term ) : '';
	}
	if ( TAX !== $term->taxonomy ) {
		return '';
	}
	switch ( $view ) {
		case 'status':
			return render_status( $term );
		case 'seasons':
			return render_seasons( $term );
		case 'launch':
			return render_launch( $term );
		case 'companions':
			return is_paged() ? '' : render_companions( $term );
		case 'start':
			return render_start( $term );
		case 'cover':
			return render_cover( $term );
		case 'contents':
			return render_contents( $term );
		case 'follow':
			return render_follow( $term );
	}
	return '';
}

/**
 * Published parts in a list of series.
 *
 * @param \WP_Term[] $series Series.
 */
function published_total( array $series ): int {
	return count( track( $series ) );
}

/**
 * Archive status line: the collection a season or companion belongs to. The
 * header carries no counts (Courtney, 2026-11-02); the parts list shows them.
 *
 * @param \WP_Term $term Queried series.
 */
function render_status( \WP_Term $term ): string {
	$c = collection( $term );
	if ( 'season' !== $c['role'] && 'companion' !== $c['role'] ) {
		return '';
	}
	$label = 'season' === $c['role']
		/* translators: 1: season number, 2: collection link. */
		? sprintf( __( 'Season %1$d of %2$s', 'courtneyr-child' ), season_number( $term ), '%s' )
		/* translators: %s: collection link. */
		: __( 'Companion series to %s', 'courtneyr-child' );
	$link = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_term_link( $c['landing'] ) ), esc_html( $c['landing']->name ) );
	return '<ul class="cr-series-status" role="list"><li>' . sprintf( esc_html( $label ), $link ) . '</li></ul>';
}

/**
 * The parts list as season sections (see group_parts()).
 *
 * @param \WP_Term                                 $term       Queried series.
 * @param array<int, array{id: int, html: string}> $rows       Rendered rows in order.
 * @param string                                   $list_attrs Attributes of the post template's list.
 */
function render_part_groups( \WP_Term $term, array $rows, string $list_attrs ): string {
	$c       = collection( $term );
	$grouped = 'landing' === $c['role'] && $c['seasons'];
	$groups  = array();
	foreach ( $rows as $row ) {
		$series = $grouped ? series_of_post( $row['id'] ) : null;
		$key    = $series ? $series->term_id : 0;
		$last   = array_key_last( $groups );
		if ( null === $last || $groups[ $last ]['key'] !== $key ) {
			$groups[] = array(
				'key'    => $key,
				'series' => $series,
				'rows'   => array(),
			);
			$last     = array_key_last( $groups );
		}
		$groups[ $last ]['rows'][] = $row;
	}
	$at         = strpos( $list_attrs, 'class="' );
	$list_attrs = false === $at ? ' class="cr-series-rows"' . $list_attrs : substr_replace( $list_attrs, 'class="cr-series-rows ', $at, strlen( 'class="' ) );
	$html       = '';
	foreach ( $groups as $group ) {
		$season = $group['series'] && $group['series']->term_id !== $c['landing']->term_id ? $group['series'] : null;
		$id     = 'cr-series-group-' . ( $season ? $season->term_id : $term->term_id );
		if ( $season ) {
			$first = parts( $season->term_id )[0]['id'] ?? 0;
			$title = $first === $group['rows'][0]['id']
				? $season->name
				/* translators: %s: season name. */
				: sprintf( __( '%s (continued)', 'courtneyr-child' ), $season->name );
			/* translators: %d: season number. */
			$head  = '<p class="cr-tape cr-series-season__tape">' . esc_html( sprintf( __( 'Season %d', 'courtneyr-child' ), season_number( $season ) ) ) . '</p>';
			$head .= sprintf( '<h2 class="cr-series-season__title" id="%s"><a href="%s">%s</a></h2>', esc_attr( $id ), esc_url( (string) get_term_link( $season ) ), esc_html( $title ) );
			$desc  = trim( wp_strip_all_tags( term_description( $season ) ) );
			$head .= '' !== $desc ? '<p class="cr-series-season__desc">' . esc_html( $desc ) . '</p>' : '';
		} else {
			$head = '<h2 class="screen-reader-text" id="' . esc_attr( $id ) . '">' . esc_html__( 'Parts', 'courtneyr-child' ) . '</h2>';
		}
		$html .= sprintf(
			'<section class="cr-series-season%s" aria-labelledby="%s"><div class="cr-series-season__head">%s</div><ol%s role="list">%s</ol></section>',
			$season ? '' : ' is-untitled',
			esc_attr( $id ),
			$head,
			$list_attrs,
			implode( '', array_column( $group['rows'], 'html' ) )
		);
	}
	return '<div class="cr-series-parts">' . $html . '</div>';
}

/**
 * Season index for a landing or a season archive. Seasons with published parts
 * link to their archive; the others are plain text, so there is no dead link.
 *
 * @param \WP_Term $term Queried series.
 */
function render_seasons( \WP_Term $term ): string {
	$c = collection( $term );
	if ( has_front_matter() || ! $c['seasons'] || ! in_array( $c['role'], array( 'landing', 'season' ), true ) ) {
		return '';
	}
	/* translators: %s: collection name. */
	$label = sprintf( __( 'Seasons of %s', 'courtneyr-child' ), $c['landing']->name );
	$html  = '<nav class="cr-series-seasons alignwide" aria-label="' . esc_attr( $label ) . '">';
	$html .= '<h2 class="cr-series-seasons__title">' . esc_html__( 'Seasons', 'courtneyr-child' ) . '</h2>';
	$html .= '<ol class="cr-series-seasons__list" role="list">';
	foreach ( $c['seasons'] as $i => $season ) {
		$count   = published_total( array( $season ) );
		$current = $season->term_id === $term->term_id;
		/* translators: %d: season number. */
		$inner  = '<span class="cr-series-seasons__num">' . esc_html( sprintf( __( 'Season %d', 'courtneyr-child' ), $i + 1 ) ) . '</span>';
		$inner .= '<span class="cr-series-seasons__name">' . esc_html( $season->name ) . '</span>';
		$inner .= $count ? '' : '<span class="cr-series-seasons__count">' . esc_html__( 'Coming later', 'courtneyr-child' ) . '</span>';
		$class  = 'cr-series-seasons__item' . ( $count ? '' : ' is-upcoming' ) . ( $current ? ' is-current' : '' );
		if ( $count ) {
			$inner = sprintf( '<a class="cr-series-seasons__link" href="%s"%s>%s</a>', esc_url( (string) get_term_link( $season ) ), $current ? ' aria-current="page"' : '', $inner );
		} else {
			$inner = '<span class="cr-series-seasons__link">' . $inner . '</span>';
		}
		$html .= '<li class="' . esc_attr( $class ) . '">' . $inner . '</li>';
	}
	return $html . '</ol></nav>';
}

/**
 * Launch state for an archive with no published parts. The date comes from a
 * scheduled part; without one, the date sentence is left out.
 *
 * @param \WP_Term $term Queried series.
 */
function render_launch( \WP_Term $term ): string {
	$c      = collection( $term );
	$series = 'landing' === $c['role'] ? main_series( $c ) : array( $term );
	$next   = next_scheduled( $series );
	if ( $next ) {
		$date = mysql2date( get_option( 'date_format' ), $next['date'] );
		$lede = null !== $next['part']
			/* translators: 1: part number, 2: date. */
			? sprintf( __( 'Part %1$d publishes on %2$s.', 'courtneyr-child' ), $next['part'], $date )
			/* translators: %s: date. */
			: sprintf( __( 'The first part publishes on %s.', 'courtneyr-child' ), $date );
	} else {
		$lede = __( 'The first part is being prepared.', 'courtneyr-child' );
	}
	// With front matter, the masthead's start line and the follow block already say this:
	// keep only a real scheduled date.
	if ( has_front_matter() ) {
		return $next ? '<div class="cr-series-launch"><p class="cr-series-launch__lede">' . esc_html( $lede ) . '</p></div>' : '';
	}
	$follow = sprintf(
		/* translators: 1: RSS feed link, 2: newsletter link. */
		__( 'Follow along by %1$s or the %2$s.', 'courtneyr-child' ),
		sprintf( '<a href="%s">%s</a>', esc_url( (string) get_term_feed_link( $c['landing']->term_id, TAX ) ), esc_html__( 'RSS', 'courtneyr-child' ) ),
		sprintf( '<a href="%s">%s</a>', esc_url( home_url( '/newsletter/' ) ), esc_html__( 'Field Notes newsletter', 'courtneyr-child' ) )
	);
	$html  = '<div class="cr-series-launch">';
	$html .= '<p class="cr-series-launch__lede">' . esc_html( $lede ) . '</p>';
	$html .= '<p class="cr-series-launch__follow">' . wp_kses( $follow, array( 'a' => array( 'href' => true ) ) ) . '</p>';
	return $html . '</div>';
}

/**
 * Index of series as a list of links with published counts.
 *
 * @param \WP_Term[] $terms         Series.
 * @param int        $heading_level Heading level for each item.
 * @param bool       $with_seasons  Count each landing's seasons into its total.
 */
function render_index( array $terms, int $heading_level = 3, bool $with_seasons = false ): string {
	$h    = 'h' . max( 2, min( 4, $heading_level ) );
	$html = '<ul class="cr-series-index" role="list">';
	foreach ( $terms as $term ) {
		$c     = collection( $term );
		$count = published_total( $with_seasons && 'landing' === $c['role'] ? main_series( $c ) : array( $term ) );
		$name  = esc_html( $term->name );
		$title = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_term_link( $term ) ), $name );
		$html .= '<li class="cr-series-index__item' . ( $count ? '' : ' is-upcoming' ) . '">';
		$html .= "<{$h} class=\"cr-series-index__title\">{$title}</{$h}>";
		$desc  = trim( wp_strip_all_tags( $term->description ) );
		if ( '' !== $desc ) {
			$html .= '<p class="cr-series-index__desc">' . esc_html( $desc ) . '</p>';
		}
		$meta = array( published_count_label( $count ) );
		if ( $with_seasons && $c['seasons'] ) {
			/* translators: %d: number of seasons. */
			$meta[] = sprintf( _n( '%d season', '%d seasons', count( $c['seasons'] ), 'courtneyr-child' ), count( $c['seasons'] ) );
		}
		$html .= '<p class="cr-series-index__count">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		$html .= '</li>';
	}
	return $html . '</ul>';
}

/**
 * Companion collections on a landing, listed apart from the numbered essays.
 * Only arcs with published parts appear; with none, nothing renders.
 *
 * @param \WP_Term $term Queried series.
 */
function render_companions( \WP_Term $term ): string {
	$c = collection( $term );
	if ( has_front_matter() || 'landing' !== $c['role'] ) {
		return '';
	}
	$body = '';
	foreach ( $c['companions'] as $companion ) {
		$arcs = array_values( array_filter( $companion['arcs'], static fn( \WP_Term $arc ): bool => published_total( array( $arc ) ) > 0 ) );
		if ( ! $arcs ) {
			continue;
		}
		$body .= '<div class="cr-series-companions__group">';
		$body .= '<h3 class="cr-series-companions__name">' . esc_html( $companion['group']->name ) . '</h3>';
		$desc  = trim( wp_strip_all_tags( $companion['group']->description ) );
		if ( '' !== $desc ) {
			$body .= '<p class="cr-series-companions__desc">' . esc_html( $desc ) . '</p>';
		}
		$body .= render_index( $arcs, 4 ) . '</div>';
	}
	if ( '' === $body ) {
		return '';
	}
	$html  = '<section class="cr-series-companions alignwide" aria-labelledby="cr-series-companions-title">';
	$html .= '<h2 class="cr-series-companions__title" id="cr-series-companions-title">' . esc_html__( 'Companion series', 'courtneyr-child' ) . '</h2>';
	/* translators: %s: collection name. */
	$html .= '<p class="cr-series-companions__note">' . esc_html( sprintf( __( 'Shorter pieces that run beside %s. They keep their own numbering.', 'courtneyr-child' ), $c['landing']->name ) ) . '</p>';
	return $html . $body . '</section>';
}

/**
 * Overview at /series-toc/: every collection and standalone series.
 */
function render_overview(): string {
	$terms = get_terms(
		array(
			'taxonomy'   => TAX,
			'hide_empty' => false,
		)
	);
	if ( ! is_array( $terms ) ) {
		return '';
	}
	$top = array_values(
		array_filter(
			order_terms( $terms ),
			static fn( \WP_Term $t ): bool => in_array( collection( $t )['role'], array( 'landing', 'standalone' ), true )
		)
	);
	if ( ! $top ) {
		return '<p class="cr-series-launch__lede">' . esc_html__( 'No series yet.', 'courtneyr-child' ) . '</p>';
	}
	return render_index( $top, 2, true );
}

/**
 * Series category archive: the series in the group, then its child groups.
 *
 * @param \WP_Term $group Queried series group.
 */
function render_group( \WP_Term $group ): string {
	$html    = '';
	$landing = get_term_by( 'slug', $group->slug, TAX );
	if ( $landing instanceof \WP_Term && ! $group->parent ) {
		/* translators: %s: collection link. */
		$html .= '<p class="cr-series-group__lead">' . sprintf( esc_html__( 'Read the whole collection on %s.', 'courtneyr-child' ), sprintf( '<a href="%s">%s</a>', esc_url( (string) get_term_link( $landing ) ), esc_html( $landing->name ) ) ) . '</p>';
	}
	$series = array_values(
		array_filter(
			series_in_group( $group->term_id ),
			static fn( \WP_Term $t ): bool => ! ( $landing instanceof \WP_Term && $t->term_id === $landing->term_id )
		)
	);
	if ( $series ) {
		$html .= render_index( $series, 2 );
	}
	$children = get_terms(
		array(
			'taxonomy'   => GROUP_TAX,
			'parent'     => $group->term_id,
			'hide_empty' => false,
		)
	);
	foreach ( is_array( $children ) ? order_terms( $children ) : array() as $child ) {
		$html .= '<h2 class="cr-series-group__child"><a href="' . esc_url( (string) get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a></h2>';
		$html .= render_index( series_in_group( $child->term_id ), 3 );
	}
	return '' !== $html ? $html : '<p class="cr-series-launch__lede">' . esc_html__( 'No series in this category yet.', 'courtneyr-child' ) . '</p>';
}

/**
 * Text naming a series inside its collection ("Merge Conflicts · Season 2").
 *
 * @param \WP_Term $series Series term.
 * @param bool     $link   Link the collection name to its landing.
 */
function series_name( \WP_Term $series, bool $link ): string {
	$c       = collection( $series );
	$landing = $c['landing'];
	$name    = esc_html( $landing->name );
	if ( $link ) {
		$name = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_term_link( $landing ) ), $name );
	}
	$sep = '<span class="cr-series-sep" aria-hidden="true" role="presentation"> · </span>';
	if ( 'season' === $c['role'] ) {
		/* translators: %d: season number. */
		$name .= $sep . esc_html( sprintf( __( 'Season %d', 'courtneyr-child' ), season_number( $series ) ) );
	} elseif ( 'companion' === $c['role'] ) {
		$name .= $sep . esc_html( $series->name );
	}
	return $name;
}

/**
 * Series header above a post: tape label, one-line description, and a
 * collapsed table of contents of published parts.
 *
 * @param int $post_id Post ID.
 */
function render_post_header( int $post_id ): string {
	$series = $post_id ? series_of_post( $post_id ) : null;
	if ( ! $series ) {
		return '';
	}
	$rows     = parts( $series->term_id );
	$total    = count( $rows );
	$position = 0;
	foreach ( $rows as $i => $row ) {
		if ( $row['id'] === $post_id ) {
			$position = $i + 1;
			break;
		}
	}
	$sep = '<span class="cr-series-sep" aria-hidden="true" role="presentation"> · </span>';
	if ( $position ) {
		$place = part_label( $rows[ $position - 1 ]['part'], $position, $total );
	} else {
		// A preview of an unpublished part, seen only by people who can edit it.
		$raw   = get_post_meta( $post_id, part_key( $series->term_id ), true );
		$place = is_numeric( $raw )
			/* translators: %d: part number. */
			? sprintf( __( 'Part %d · not published yet', 'courtneyr-child' ), (int) $raw )
			: __( 'Not published yet', 'courtneyr-child' );
	}
	$c    = collection( $series );
	$desc = trim( wp_strip_all_tags( $series->description ) );
	if ( '' === $desc ) {
		$desc = trim( wp_strip_all_tags( $c['landing']->description ) );
	}
	$html  = '<div class="cr-series-post">';
	$html .= '<p class="cr-tape cr-series-post__tape">' . series_name( $series, true ) . $sep . esc_html( $place ) . '</p>';
	if ( '' !== $desc ) {
		$html .= '<p class="cr-series-post__desc">' . esc_html( $desc ) . '</p>';
	}
	if ( $total ) {
		/* translators: %s: series name. */
		$label = sprintf( __( 'Parts of %s', 'courtneyr-child' ), wp_strip_all_tags( series_name( $series, false ) ) );
		$html .= '<details class="cr-series-post__toc">';
		$html .= '<summary class="cr-series-post__summary">' . esc_html__( 'In this series', 'courtneyr-child' ) . ' <span class="cr-series-post__summary-count">(' . esc_html( published_count_label( $total ) ) . ')</span></summary>';
		$html .= '<nav aria-label="' . esc_attr( $label ) . '"><ol class="cr-series-post__list" role="list">';
		foreach ( $rows as $i => $row ) {
			$current = $row['id'] === $post_id;
			/* translators: %d: part number. */
			$num   = sprintf( __( 'Part %d', 'courtneyr-child' ), $row['part'] ?? $i + 1 );
			$html .= '<li class="cr-series-post__item' . ( $current ? ' is-current' : '' ) . '">';
			$html .= sprintf(
				'<a href="%s"%s><span class="cr-series-post__num">%s</span> <span class="cr-series-post__title">%s</span></a>',
				esc_url( (string) get_permalink( $row['id'] ) ),
				$current ? ' aria-current="page"' : '',
				esc_html( $num ),
				esc_html( get_the_title( $row['id'] ) )
			);
			if ( $current ) {
				$html .= ' <span class="cr-series-post__here">' . esc_html__( 'You’re reading this', 'courtneyr-child' ) . '</span>';
			}
			$html .= '</li>';
		}
		$html .= '</ol></nav></details>';
	}
	return $html . '</div>';
}

/**
 * Previous and next published parts around a post in its reading order.
 * Gaps from unpublished parts are skipped; an end with no neighbour renders nothing.
 *
 * @param int $post_id Post ID.
 */
function render_post_nav( int $post_id ): string {
	$series = $post_id ? series_of_post( $post_id ) : null;
	if ( ! $series ) {
		return '';
	}
	$order = reading_order_for( $series );
	$rows  = track( $order );
	$index = null;
	foreach ( $rows as $i => $row ) {
		if ( $row['id'] === $post_id ) {
			$index = $i;
			break;
		}
	}
	if ( null === $index ) {
		// Preview: place the post by series, then stored part.
		$rank  = array_search( $series->term_id, wp_list_pluck( $order, 'term_id' ), true );
		$raw   = get_post_meta( $post_id, part_key( $series->term_id ), true );
		$part  = is_numeric( $raw ) ? (int) $raw : PHP_INT_MAX;
		$ranks = array_flip( wp_list_pluck( $order, 'term_id' ) );
		$at    = count( $rows );
		foreach ( $rows as $i => $row ) {
			$r = $ranks[ $row['series']->term_id ];
			if ( $r > $rank || ( $r === $rank && ( $row['part'] ?? PHP_INT_MAX ) > $part ) ) {
				$at = $i;
				break;
			}
		}
		$prev = $rows[ $at - 1 ] ?? null;
		$next = $rows[ $at ] ?? null;
	} else {
		$prev = $rows[ $index - 1 ] ?? null;
		$next = $rows[ $index + 1 ] ?? null;
	}
	if ( ! $prev && ! $next ) {
		return '';
	}
	/* translators: %s: series name. */
	$label = sprintf( __( '%s: previous and next part', 'courtneyr-child' ), wp_strip_all_tags( series_name( $series, false ) ) );
	$html  = '<nav class="cr-series-nav" aria-label="' . esc_attr( $label ) . '">';
	foreach ( array(
		'prev' => array( $prev, __( 'Previous part', 'courtneyr-child' ), '←' ),
		'next' => array( $next, __( 'Next part', 'courtneyr-child' ), '→' ),
	) as $dir => $spec ) {
		list( $row, $text, $arrow ) = $spec;
		if ( ! $row ) {
			continue;
		}
		$where = wp_strip_all_tags( series_name( $row['series'], false ) );
		/* translators: %d: part number. */
		$part  = sprintf( __( 'Part %d', 'courtneyr-child' ), $row['part'] ?? 0 );
		$meta  = $row['series']->term_id === $series->term_id ? $part : $where . ' · ' . $part;
		$arrow = '<span class="cr-series-nav__arrow" aria-hidden="true" role="presentation">' . $arrow . '</span>';
		$html .= sprintf(
			'<a class="cr-card cr-series-nav__link cr-series-nav__link--%1$s" href="%2$s" rel="%1$s"><span class="cr-series-nav__dir">%3$s</span><span class="cr-series-nav__part">%4$s</span><span class="cr-series-nav__title">%5$s</span></a>',
			esc_attr( $dir ),
			esc_url( (string) get_permalink( $row['id'] ) ),
			'prev' === $dir ? $arrow . ' ' . esc_html( $text ) : esc_html( $text ) . ' ' . $arrow,
			esc_html( $meta ),
			esc_html( get_the_title( $row['id'] ) )
		);
	}
	return $html . '</nav>';
}

/**
 * Whether the page carries the series front matter (the contents view). The
 * season chips, the bottom companion list and the launch note step aside then,
 * so each fact appears once.
 *
 * @param bool $set Mark the front matter as rendered.
 */
function has_front_matter( bool $set = false ): bool {
	static $rendered = false;
	if ( $set ) {
		$rendered = true;
	}
	return $rendered;
}

/**
 * Published parts of the series' main reading track, in reading order.
 *
 * @param \WP_Term $term Queried series.
 * @return array<int, array<string, mixed>>
 */
function main_track( \WP_Term $term ): array {
	$c = collection( $term );
	return track( 'landing' === $c['role'] ? main_series( $c ) : array( $term ) );
}

/**
 * "Season 2, Part 3" for a seasonal part, "Part 3" otherwise.
 *
 * @param array<string, mixed> $row  Row from track().
 * @param \WP_Term             $term Queried series.
 * @param int                  $i    Position within its series, for parts without a stored number.
 */
function track_label( array $row, \WP_Term $term, int $i = 0 ): string {
	$n      = (int) ( $row['part'] ?? $i + 1 );
	$season = $row['series']->term_id !== $term->term_id ? season_number( $row['series'] ) : 0;
	return $season
		/* translators: 1: season number, 2: part number. */
		? sprintf( __( 'Season %1$d, Part %2$d', 'courtneyr-child' ), $season, $n )
		/* translators: %d: part number. */
		: sprintf( __( 'Part %d', 'courtneyr-child' ), $n );
}

/**
 * The masthead's way in: the first published part, and the latest one once
 * there are two. With nothing published, a status line and a jump to the
 * follow block, or the scheduled date when a part is scheduled.
 *
 * @param \WP_Term $term Queried series.
 */
function render_start( \WP_Term $term ): string {
	wp_enqueue_style( 'courtneyr-series-front' );
	$rows = main_track( $term );
	if ( ! $rows ) {
		$next = next_scheduled( 'landing' === collection( $term )['role'] ? main_series( collection( $term ) ) : array( $term ) );
		$line = $next
			/* translators: %s: date. */
			? sprintf( __( 'The first part publishes on %s.', 'courtneyr-child' ), mysql2date( get_option( 'date_format' ), $next['date'] ) )
			: __( 'No parts are published yet.', 'courtneyr-child' );
		return '<p class="cr-series-start cr-series-start--empty">' . esc_html( $line ) . ' <a href="#cr-series-follow">' . esc_html__( 'Follow along', 'courtneyr-child' ) . '</a></p>';
	}
	$first = $rows[0];
	$html  = '<div class="cr-series-start">';
	$html .= sprintf(
		'<a class="cr-series-start__link" href="%1$s"><span class="cr-series-start__label">%2$s</span><span class="cr-series-start__title">%3$s</span></a>',
		esc_url( (string) get_permalink( $first['id'] ) ),
		/* translators: %s: "Part 1" or "Season 1, Part 1". */
		esc_html( sprintf( __( 'Start with %s', 'courtneyr-child' ), track_label( $first, $term ) ) ),
		esc_html( get_the_title( $first['id'] ) )
	);
	if ( count( $rows ) > 1 ) {
		$latest = $rows[0];
		foreach ( $rows as $row ) {
			if ( get_post_time( 'U', true, $row['id'] ) > get_post_time( 'U', true, $latest['id'] ) ) {
				$latest = $row;
			}
		}
		if ( $latest['id'] !== $first['id'] ) {
			$html .= sprintf(
				'<p class="cr-series-start__latest">%1$s <a href="%2$s">%3$s</a> <span class="cr-series-start__where">(%4$s)</span></p>',
				esc_html__( 'Latest:', 'courtneyr-child' ),
				esc_url( (string) get_permalink( $latest['id'] ) ),
				esc_html( get_the_title( $latest['id'] ) ),
				esc_html( track_label( $latest, $term ) )
			);
		}
	}
	return $html . '</div>';
}

/**
 * The contents page: every season in order with its scope and publication
 * state, then each companion series with its arcs. Seasons and arcs with
 * published parts link to their archive; planned ones are plain text.
 * Counts are published parts only. Renders nothing for a series without
 * seasons or companions, but still marks the front matter as present.
 *
 * @param \WP_Term $term Queried series.
 */
function render_contents( \WP_Term $term ): string {
	has_front_matter( true );
	wp_enqueue_style( 'courtneyr-series-front' );
	$c = collection( $term );
	if ( 'landing' !== $c['role'] || ( ! $c['seasons'] && ! $c['companions'] ) ) {
		return '';
	}
	/* translators: %s: series name. */
	$html = '<nav class="cr-series-contents" aria-label="' . esc_attr( sprintf( __( 'Contents of %s', 'courtneyr-child' ), $c['landing']->name ) ) . '">';
	if ( $c['seasons'] ) {
		$html .= '<ol class="cr-series-contents__list" role="list">';
		foreach ( $c['seasons'] as $i => $season ) {
			/* translators: %d: season number. */
			$html .= contents_item( $season, sprintf( __( 'Season %d', 'courtneyr-child' ), $i + 1 ) );
		}
		$html .= '</ol>';
	}
	foreach ( $c['companions'] as $companion ) {
		$id    = 'cr-series-companion-' . $companion['group']->term_id;
		$html .= '<div class="cr-series-contents__companion">';
		$html .= '<h3 class="cr-series-contents__companion-title" id="' . esc_attr( $id ) . '"><span class="cr-tape cr-series-contents__companion-tape">' . esc_html__( 'Companion series', 'courtneyr-child' ) . '</span> ' . esc_html( $companion['group']->name ) . '</h3>';
		$desc  = trim( wp_strip_all_tags( $companion['group']->description ) );
		$html .= '' !== $desc ? '<p class="cr-series-contents__companion-desc">' . esc_html( $desc ) . '</p>' : '';
		if ( $companion['arcs'] ) {
			$html .= '<ol class="cr-series-contents__list cr-series-contents__list--companion" role="list" aria-labelledby="' . esc_attr( $id ) . '">';
			foreach ( $companion['arcs'] as $j => $arc ) {
				/* translators: %d: arc number within the companion series. */
				$html .= contents_item( $arc, sprintf( __( 'Arc %d', 'courtneyr-child' ), $j + 1 ) );
			}
			$html .= '</ol>';
		}
		$html .= '</div>';
	}
	return $html . '</nav>';
}

/**
 * One contents row: number, name (a link once something is published), a
 * dotted leader, the state as a stamp, and the scope line.
 *
 * @param \WP_Term $series Season or arc.
 * @param string   $number "Season 3" or "Arc 1".
 */
function contents_item( \WP_Term $series, string $number ): string {
	$count = published_total( array( $series ) );
	$name  = $count
		? sprintf( '<a class="cr-series-contents__link" href="%s">%s</a>', esc_url( (string) get_term_link( $series ) ), esc_html( $series->name ) )
		: esc_html( $series->name );
	$state = $count
		/* translators: %d: published parts. */
		? sprintf( _n( '%d part', '%d parts', $count, 'courtneyr-child' ), $count )
		: __( 'Planned', 'courtneyr-child' );
	$desc = trim( wp_strip_all_tags( term_description( $series ) ) );
	return sprintf(
		'<li class="cr-series-contents__item %1$s"><span class="cr-series-contents__num">%2$s</span><span class="cr-series-contents__name">%3$s</span><span class="cr-series-contents__leader" aria-hidden="true" role="presentation"></span><span class="cr-series-stamp cr-series-contents__state">%4$s</span>%5$s</li>',
		$count ? 'is-published' : 'is-planned',
		esc_html( $number ),
		$name,
		esc_html( $state ),
		'' !== $desc ? '<span class="cr-series-contents__desc">' . esc_html( $desc ) . '</span>' : ''
	);
}

/**
 * The one follow block, at the end of the page: the series feed and the
 * Field Notes newsletter. The masthead's empty-state line links here.
 *
 * @param \WP_Term $term Queried series.
 */
function render_follow( \WP_Term $term ): string {
	wp_enqueue_style( 'courtneyr-series-front' );
	$c     = collection( $term );
	$html  = '<section class="cr-series-follow alignwide" id="cr-series-follow" aria-labelledby="cr-series-follow-title">';
	$html .= '<h2 class="cr-series-follow__title" id="cr-series-follow-title">' . esc_html__( 'Follow along', 'courtneyr-child' ) . '</h2>';
	$html .= '<p class="cr-series-follow__lede">' . esc_html__( 'Follow along by the series feed or the Field Notes newsletter.', 'courtneyr-child' ) . '</p>';
	$html .= '<ul class="cr-series-follow__links" role="list">';
	$html .= sprintf( '<li><a class="cr-series-follow__link" href="%s">%s</a></li>', esc_url( (string) get_term_feed_link( $c['landing']->term_id, TAX ) ), esc_html__( 'Series RSS feed', 'courtneyr-child' ) );
	$html .= sprintf( '<li><a class="cr-series-follow__link" href="%s">%s</a></li>', esc_url( home_url( '/newsletter/' ) ), esc_html__( 'Field Notes newsletter', 'courtneyr-child' ) );
	return $html . '</ul></section>';
}

/**
 * The series image from PublishPress Series' "Series featured image" field,
 * taped into the masthead under the title. Media-library images get srcset and
 * the library's alt text; an image outside the library is treated as
 * decoration (empty alt). Nothing renders without an image.
 *
 * @param \WP_Term $term Queried series.
 */
function render_cover( \WP_Term $term ): string {
	$image = series_image( collection( $term )['landing'] );
	if ( ! $image ) {
		return '';
	}
	wp_enqueue_style( 'courtneyr-series-front' );
	$attrs = array(
		'class'    => 'cr-series-cover__img',
		'sizes'    => '(min-width: 64rem) 34rem, 100vw',
		'loading'  => 'eager',
		'decoding' => 'async',
	);
	$img   = $image['id'] ? wp_get_attachment_image( $image['id'], 'large', false, $attrs ) : '';
	if ( '' === $img ) {
		$img = sprintf( '<img class="cr-series-cover__img" src="%s" alt="" loading="eager" decoding="async" width="1200" height="630">', esc_url( $image['url'] ) );
	}
	return '<figure class="cr-series-cover">' . $img . '</figure>';
}
