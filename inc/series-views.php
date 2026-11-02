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
 * Archive status line: published parts, seasons, and the collection a season belongs to.
 *
 * @param \WP_Term $term Queried series.
 */
function render_status( \WP_Term $term ): string {
	$c     = collection( $term );
	$items = array();
	if ( 'season' === $c['role'] || 'companion' === $c['role'] ) {
		$n     = season_number( $term );
		$label = 'season' === $c['role']
			/* translators: 1: season number, 2: number of seasons, 3: collection link. */
			? sprintf( __( 'Season %1$d of %2$d in %3$s', 'courtneyr-child' ), $n, count( $c['seasons'] ), '%s' )
			/* translators: %s: collection link. */
			: __( 'Companion series to %s', 'courtneyr-child' );
		$link    = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_term_link( $c['landing'] ) ), esc_html( $c['landing']->name ) );
		$items[] = sprintf( esc_html( $label ), $link );
		$items[] = esc_html( published_count_label( published_total( array( $term ) ) ) );
	} else {
		$items[] = esc_html( published_count_label( published_total( 'landing' === $c['role'] ? main_series( $c ) : array( $term ) ) ) );
		if ( $c['seasons'] ) {
			/* translators: %d: number of seasons. */
			$items[] = esc_html( sprintf( _n( '%d season', '%d seasons', count( $c['seasons'] ), 'courtneyr-child' ), count( $c['seasons'] ) ) );
		}
	}
	$html = '<ul class="cr-series-status" role="list">';
	foreach ( $items as $item ) {
		$html .= '<li>' . $item . '</li>';
	}
	return $html . '</ul>';
}

/**
 * Season index for a landing or a season archive. Seasons with published parts
 * link to their archive; the others are plain text, so there is no dead link.
 *
 * @param \WP_Term $term Queried series.
 */
function render_seasons( \WP_Term $term ): string {
	$c = collection( $term );
	if ( ! $c['seasons'] || ! in_array( $c['role'], array( 'landing', 'season' ), true ) ) {
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
		$inner .= '<span class="cr-series-seasons__count">' . esc_html( published_count_label( $count ) ) . '</span>';
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
	if ( 'landing' !== $c['role'] ) {
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
	$sep = '<span class="cr-series-sep" aria-hidden="true"> · </span>';
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
	$sep = '<span class="cr-series-sep" aria-hidden="true"> · </span>';
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
		$arrow = '<span class="cr-series-nav__arrow" aria-hidden="true">' . $arrow . '</span>';
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
