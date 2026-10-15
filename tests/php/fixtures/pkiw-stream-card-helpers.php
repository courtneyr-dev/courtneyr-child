<?php
/**
 * Fixture: verbatim copies of the Post Kinds Stream card helpers the repost Stream adapter
 * calls (includes/functions-stream-card.php at plugin origin/develop 6f93b8f), so the tests run
 * the adapter against the plugin's real link, date and heading code. Refresh them when the
 * plugin's helpers change.
 */

namespace PKIW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hidden h-cite markup for an entry's citation property.
 *
 * Each part is a property class and its value or values. `p-author`
 * prints as a nested h-card with that name; every other class prints as
 * data, with `u-*` values escaped as URLs. A value that is empty, or
 * that esc_url() empties, is dropped.
 *
 * @since 1.9.0
 *
 * @param string                         $property Entry property class, e.g. `u-play-of`.
 * @param array<string, string|string[]> $parts    Property class => value or values.
 * @return string Hidden span, or '' when every value is empty.
 */
function hidden_cite_html( string $property, array $parts ): string {
	$inner = '';
	foreach ( $parts as $class => $values ) {
		foreach ( (array) $values as $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			if ( 'p-author' === $class ) {
				$inner .= '<span class="p-author h-card"><data class="p-name" value="' . esc_attr( $value ) . '"></data></span>';
				continue;
			}
			// esc_url() empties a disallowed protocol, and php-mf2 reads an
			// empty u-* value as the page URL, so skip what escaping empties.
			$escaped = str_starts_with( $class, 'u-' ) ? esc_url( $value ) : esc_attr( $value );
			if ( '' === $escaped ) {
				continue;
			}
			$inner .= '<data class="' . esc_attr( $class ) . '" value="' . $escaped . '"></data>';
		}
	}

	return '' === $inner ? '' : '<span class="pk-kind-cite h-cite ' . esc_attr( $property ) . '" hidden>' . $inner . '</span>';
}

/**
 * Point a rendered card's title at the post permalink.
 *
 * On the Stream a card title should click through to the post, not to the
 * watched/listened URL. Repoints the `.pk-title` anchor at the post (same
 * tab), or wraps a plain-text title in one. For an h-cite card, the original
 * URL and its u-* classes move to hidden data immediately before the title,
 * keeping the citation target separate from the post's own URL.
 *
 * @param string   $html Rendered card HTML.
 * @param \WP_Post $post Post the card represents.
 * @return string HTML with the title linked to the post.
 */
function link_title_to_post( string $html, \WP_Post $post ): string {
	$permalink = esc_url( (string) get_permalink( $post ) );
	if ( '' === $permalink ) {
		return $html;
	}

	$is_h_cite = static function ( int $heading_offset ) use ( $html ): bool {
		$before = substr( $html, 0, $heading_offset );
		if ( ! preg_match_all( '#<article\b[^>]*\bclass=(?:"([^"]*)"|\'([^\']*)\')[^>]*>#i', $before, $articles, PREG_SET_ORDER ) ) {
			return false;
		}

		$article = end( $articles );
		$classes = (string) ( $article[1] ?? '' );
		if ( '' === $classes ) {
			$classes = (string) ( $article[2] ?? '' );
		}
		$class_tokens = preg_split( '/\s+/', trim( $classes ) );

		return in_array( 'h-cite', false !== $class_tokens ? $class_tokens : [], true );
	};

	// Title already linked → repoint the anchor at the post.
	if ( preg_match( '#(<h[1-6] class="pk-title[^"]*">\s*)(<a\b[^>]*>)#s', $html, $linked, PREG_OFFSET_CAPTURE ) ) {
		$heading_offset = (int) $linked[1][1];
		$cite           = $is_h_cite( $heading_offset );
		$anchor         = $cite ? '<a href="' . $permalink . '">' : '<a class="u-url" href="' . $permalink . '">';
		$data           = '';
		$original_href  = '';

		if ( $cite ) {
			$processor = new \WP_HTML_Tag_Processor( $linked[2][0] );
			if ( $processor->next_tag( [ 'tag_name' => 'a' ] ) ) {
				$original_href = esc_url( (string) $processor->get_attribute( 'href' ) );
				$class_tokens  = preg_split( '/\s+/', trim( (string) $processor->get_attribute( 'class' ) ) );
				$class_tokens  = false !== $class_tokens ? $class_tokens : [];
				$url_classes   = array_values(
					array_filter(
						$class_tokens,
						static fn( string $class_token ): bool => str_starts_with( $class_token, 'u-' )
					)
				);

				if ( '' !== $original_href && $permalink !== $original_href && $url_classes ) {
					$data = '<data class="' . esc_attr( implode( ' ', $url_classes ) ) . '" value="' . $original_href . '" hidden></data>';
				}
			}
		}

		$replacement = $data . $linked[1][0] . $anchor;

		return substr_replace( $html, $replacement, (int) $linked[0][1], strlen( $linked[0][0] ) );
	}

	// Plain-text title → wrap it in a link to the post.
	if ( preg_match( '#(<h[1-6] class="pk-title[^"]*">)(\s*)([^<]+?)(\s*)(</h[1-6]>)#s', $html, $plain, PREG_OFFSET_CAPTURE ) ) {
		$cite        = $is_h_cite( (int) $plain[1][1] );
		$anchor      = $cite ? '<a href="' . $permalink . '">' : '<a class="u-url" href="' . $permalink . '">';
		$replacement = $plain[1][0] . $plain[2][0] . $anchor . $plain[3][0] . '</a>' . $plain[4][0] . $plain[5][0];

		return substr_replace( $html, $replacement, (int) $plain[0][1], strlen( $plain[0][0] ) );
	}

	return $html;
}

/**
 * Re-level a rendered card's title heading.
 *
 * Every card render.php hardcodes `<h2 class="pk-title …">` since a card
 * rendered on its own (single post view, a non-Stream block) is the page's
 * first heading under the title. On the Stream, though, the surrounding
 * theme may already be at h2 or h3, and every card in one Stream should
 * share a level regardless of which branch rendered it — a card block
 * rendered via do_blocks(), a synthetic watch-card block, or the generic
 * stream card (which sets its own level directly).
 *
 * The play card's Review heading (`pk-scorepad__heading`) moves by the same
 * offset, capped at h6, so it stays one level below the card title.
 *
 * @param string $html  Rendered card HTML.
 * @param int    $level Clamped heading level (2–4).
 * @return string HTML with the title heading re-leveled.
 */
function apply_stream_heading_level( string $html, int $level ): string {
	if ( 2 === $level ) {
		return $html;
	}

	$out = preg_replace_callback(
		'#<h2( class="pk-title[^"]*")>(.*?)</h2>#s',
		static function ( $matches ) use ( $level ) {
			return '<h' . $level . $matches[1] . '>' . $matches[2] . '</h' . $level . '>';
		},
		$html,
		1,
		$count
	);

	$html = ( null !== $out && $count > 0 ) ? $out : $html;

	$offset = $level - 2;
	$out    = preg_replace_callback(
		'#<h([2-6])( class="pk-scorepad__heading")>(.*?)</h\1>#s',
		static function ( $matches ) use ( $offset ) {
			$to = min( 6, (int) $matches[1] + $offset );
			return '<h' . $to . $matches[2] . '>' . $matches[3] . '</h' . $to . '>';
		},
		$html
	);

	return null !== $out ? $out : $html;
}

/**
 * Whether the card's first title sits inside a nested microformats object.
 *
 * A cite card's title belongs to its h-cite (or h-event, h-food...), so a
 * property placed beside it attaches to that object, not to the entry. A
 * title whose nearest root is an h-entry, or that has no root in the card,
 * belongs to the entry.
 *
 * @param string $html Rendered card HTML.
 * @return bool
 */
function card_title_in_nested_object( string $html ): bool {
	if ( ! preg_match( '/\bh-[a-z]+/', $html ) ) {
		return false;
	}
	$doc      = new \DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?><div id="pkiw-title-scope">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$title = ( new \DOMXPath( $doc ) )->query( '//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][starts-with(@class, "pk-title")]' )->item( 0 );
	if ( ! $title instanceof \DOMElement ) {
		return false;
	}
	for ( $p = $title->parentNode; $p instanceof \DOMElement; $p = $p->parentNode ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
		if ( 'pkiw-title-scope' === $p->getAttribute( 'id' ) ) {
			return false;
		}
		$classes = ' ' . $p->getAttribute( 'class' ) . ' ';
		if ( preg_match( '/\sh-[a-z]+(-[a-z]+)*\s/', $classes ) ) {
			return ! str_contains( $classes, ' h-entry ' );
		}
	}
	return false;
}

/**
 * Insert the post's published date inside the card, under the title.
 *
 * On the Stream the date reads inside each card rather than floating above
 * it, so the Post Template no longer renders its own post-date block. The
 * date is the entry's: inside a nested object (a like card's h-cite) it
 * prints without dt-published, and ensure_entry_properties() gives the
 * entry its hidden date.
 *
 * @param string   $html Rendered card HTML.
 * @param \WP_Post $post Post the card represents.
 * @return string HTML with the post date under the title.
 */
function inject_post_date_into_card( string $html, \WP_Post $post ): string {
	$display = get_the_date( '', $post );
	if ( '' === $display ) {
		return $html;
	}

	$date_html = '<p class="pk-sub pk-stream-date"><time'
		. ( card_title_in_nested_object( $html ) ? '' : ' class="dt-published"' )
		. ' datetime="' . esc_attr( (string) get_post_time( 'c', true, $post ) ) . '">'
		. esc_html( $display ) . '</time></p>';

	$out = preg_replace_callback(
		'#<h[1-6] class="pk-title[^"]*">.*?</h[1-6]>#s',
		static function ( $matches ) use ( $date_html ) {
			return $matches[0] . $date_html;
		},
		$html,
		1,
		$count
	);
	return ( null !== $out && $count > 0 ) ? $out : $html;
}
