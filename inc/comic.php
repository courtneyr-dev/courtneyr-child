<?php
/**
 * Comics you read (PKIW #228): a comic-shop rack archive, a bagged comic in
 * the Stream, and a bagged comic beside its reading details on the single.
 *
 * The plugin's comic-card owns every fact (title, creators, series, volume,
 * issue, publisher, cover and its alt, link, status, rating, dates, note)
 * and its microformats. This file decides what each surface shows:
 *
 *   Archive  the bagged cover, its h2 title link on a shelf tag (with series,
 *            volume and issue when stored) and a status sticker. Creators,
 *            rating, dates, publisher and the note are cut from the markup
 *            and stay on the single.
 *   Stream   the bagged cover, kind label, title link, creators, rating,
 *            status and note. No dates.
 *   Single   the bag, with creators, rating and status under the H1, the
 *            note as "Notes from this read" and a library-card reading
 *            record. Body blocks that repeat the card's cover or note are
 *            not printed a second time.
 *
 * A comics post with no comic-card (a strip its author drew) keeps the
 * plugin's generic card and the default single; on the rack that card is
 * cut down to its picture and title link. assets/css/cr-comic.css paints
 * all three surfaces.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Comic;

use function Courtneyr\Child\HomeSections\is_stream_surface;
use function Courtneyr\Child\Journal\card_attrs;
use function Courtneyr\Child\Journal\notes_section;
use function Courtneyr\Child\MediaShelf\cut_elements;
use function Courtneyr\Child\SingleRead\book_label;
use function Courtneyr\Child\SingleRead\status_copy;
use function Courtneyr\Child\Stamps\pick;
use function Courtneyr\Child\Stamps\render;
use function Courtneyr\Child\Stamps\seed;
use const Courtneyr\Child\Stamps\INKS;
use const Courtneyr\Child\Stamps\TILTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLOCK = 'post-kinds-indieweb/comic-card';

/**
 * Comics in the first rack row on page 1; their covers load eagerly.
 */
const FIRST_ROW = 4;

/**
 * Comics per archive page: three tiers of four, which also fills whole
 * tiers at three and two across.
 */
const RACK_SIZE = 12;

/**
 * Fill the rack: the comics archive pages by RACK_SIZE, and the Site
 * Editor previews it at that size (inc/theme-supports.php applies both).
 *
 * The card-grid size of 5 would leave seven of twelve rack spaces empty on
 * every full page.
 *
 * @param array<string, int> $sizes Kind slug => posts per page.
 * @return array<string, int>
 */
function page_size( $sizes ): array {
	$sizes           = is_array( $sizes ) ? $sizes : array();
	$sizes['comics'] = RACK_SIZE;
	return $sizes;
}
add_filter( 'courtneyr_child_kind_archive_page_sizes', __NAMESPACE__ . '\\page_size' );

/**
 * Block style the rack's pattern gives its stream card.
 *
 * The editor asks the server for each card on its own, with no archive
 * query behind the request. The style travels with the block, so the card
 * is bagged for the rack in the Site Editor as it is on the archive.
 */
const RACK_CARD_STYLE = 'is-style-cr-rack-comic';

/**
 * Name the card style, so the editor lists it for the stream card.
 *
 * @return void
 */
function register_card_style(): void {
	if ( function_exists( 'register_block_style' ) ) {
		register_block_style(
			'post-kinds-indieweb/stream-card',
			array(
				'name'  => 'cr-rack-comic',
				'label' => __( 'Racked comic', 'courtneyr-child' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style' );

/**
 * The comic-card block in a post's content.
 *
 * @param \WP_Post $post Post.
 * @return array<string, mixed>|null
 */
function find_comic_block( \WP_Post $post ): ?array {
	$found = null;
	$walk  = static function ( array $blocks ) use ( &$walk, &$found ): void {
		foreach ( $blocks as $block ) {
			if ( null !== $found ) {
				return;
			}
			if ( BLOCK === ( $block['blockName'] ?? '' ) ) {
				$found = $block;
				return;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ) );
	return $found;
}

/**
 * Is this post a comic someone read (a comics post carrying the card)?
 *
 * @param \WP_Post|null $post Post.
 * @return array<string, mixed>|null The card block, or null.
 */
function comic_read( ?\WP_Post $post ): ?array {
	if ( ! $post instanceof \WP_Post || ! has_term( 'comics', 'kind', $post ) ) {
		return null;
	}
	return find_comic_block( $post );
}

/**
 * Is this the single view of a comic read?
 *
 * @return bool
 */
function is_comic_single(): bool {
	return \is_singular( 'post' ) && null !== comic_read( \get_post( \get_queried_object_id() ) );
}

/**
 * Is this the comics archive?
 *
 * @return bool
 */
function is_rack(): bool {
	return \is_tax( 'kind', 'comics' );
}

/**
 * The card's attributes, with stored meta filling what the block omits.
 *
 * @param \WP_Post             $post  Post.
 * @param array<string, mixed> $block Comic-card block.
 * @return array<string, mixed>
 */
function comic_attrs( \WP_Post $post, array $block ): array {
	$attrs = card_attrs( $post, BLOCK, (array) ( $block['attrs'] ?? array() ) );
	if ( ! in_array( $attrs['readStatus'] ?? '', array( 'to-read', 'reading', 'finished', 'abandoned' ), true ) ) {
		$attrs['readStatus'] = 'reading'; // The block's default, which the comment omits.
	}
	return $attrs;
}

/**
 * A stored date as the calendar day it holds: machine date and display.
 *
 * The plugin's helper reads the day from the text; strtotime() plus
 * wp_date() would print a bare date a day early west of UTC.
 *
 * @param string $raw Attribute value.
 * @return array{0: string, 1: string}
 */
function day( string $raw ): array {
	if ( function_exists( '\\PKIW\\card_calendar_date' ) ) {
		return \PKIW\card_calendar_date( $raw );
	}
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', trim( $raw ), $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return array( '', '' );
	}
	$ts = gmmktime( 12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] );
	return array( gmdate( 'Y-m-d', $ts ), (string) wp_date( (string) get_option( 'date_format' ), $ts, new \DateTimeZone( 'UTC' ) ) );
}

/**
 * Load the comic paint where a comic can appear.
 *
 * @return void
 */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_rack() && ! is_comic_single() && ! is_stream_surface() ) {
		return;
	}
	wp_enqueue_style(
		'courtneyr-comic',
		COURTNEYR_CHILD_URI . '/assets/css/cr-comic.css',
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );

/**
 * Mark the single view of a comic read for the stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_comic_single() ) {
		$classes[] = 'cr-comic-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Cut the first element of one tag that carries a class, and return it.
 *
 * @param string $html       Fragment, modified in place.
 * @param string $tag        Tag name.
 * @param string $class_name One class the element carries.
 * @return string The removed element, or '' when there is none.
 */
function take_element( string &$html, string $tag, string $class_name ): string {
	$open = '/<' . $tag . '\\b[^>]*\\bclass="[^"]*(?<![\\w-])' . preg_quote( $class_name, '/' ) . '(?![\\w-])[^"]*"[^>]*>/';
	if ( ! preg_match( $open, $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return '';
	}
	$start = (int) $m[0][1];
	$pos   = $start + strlen( $m[0][0] );
	$depth = 1;
	while ( $depth > 0 && preg_match( '/<(\\/?)' . $tag . '\\b[^>]*>/', $html, $t, PREG_OFFSET_CAPTURE, $pos ) ) {
		$pos    = (int) $t[0][1] + strlen( $t[0][0] );
		$depth += '/' === $t[1][0] ? -1 : 1;
	}
	if ( $depth > 0 ) {
		return '';
	}
	$taken = substr( $html, $start, $pos - $start );
	$html  = substr( $html, 0, $start ) . substr( $html, $pos );
	return $taken;
}

/**
 * A typographic cover for a comic with no stored artwork.
 *
 * The text is the card's own title and creators. It is hidden from
 * assistive technology because the card's heading already says it. The
 * outer box is the same bag and board a picture sits on; the cover inside
 * it takes the picture's place.
 *
 * @param array<string, mixed> $a             Attributes.
 * @param \WP_Post             $post          Post.
 * @param bool                 $with_creators Whether the cover names the creators.
 * @return string
 */
function type_cover( array $a, \WP_Post $post, bool $with_creators ): string {
	$title    = trim( (string) ( $a['title'] ?? '' ) );
	$creators = trim( (string) ( $a['creators'] ?? '' ) );
	$out      = '<div class="pk-media cr-comic__cover--type" aria-hidden="true"><div class="cr-comic__type-cover"><span class="cr-comic__type-title">' . esc_html( '' !== $title ? $title : get_the_title( $post ) ) . '</span>';
	if ( $with_creators && '' !== $creators ) {
		$out .= '<span class="cr-comic__type-creators">' . esc_html( $creators ) . '</span>';
	}
	return $out . '</div></div>';
}

/**
 * Does a card hold a cover box? Tested on the class, not on the exact
 * attribute string, so a class another filter adds does not hide it.
 *
 * @param string $html Card.
 * @return bool
 */
function has_cover( string $html ): bool {
	return 1 === preg_match( '/<div\b[^>]*\bclass="[^"]*(?<![\w-])pk-media(?![\w-])/', $html );
}

/**
 * Text as a reader sees it: no tags, entities decoded, whitespace folded.
 *
 * @param string $html Markup or text.
 * @return string
 */
function plain( string $html ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/**
 * Drop body blocks that repeat the card: an image block showing the
 * card's own cover, and a paragraph whose text is the card's own note.
 *
 * A comic posted through Micropub stores the card, then the picture, then
 * the note again as a paragraph. On the single the bag shows the cover and
 * the notes card shows the note, so the copies would print each twice.
 * Only exact repeats go; any other picture or paragraph stays. A group the
 * repeats leave empty goes with them (Micropub wraps the note in an
 * e-content group, and an empty e-content would read as the entry's text).
 * The stored post content is not touched.
 *
 * @param string               $html Rendered content around the card.
 * @param array<string, mixed> $a    Attributes.
 * @return string
 */
function drop_repeats( string $html, array $a ): string {
	$given = $html;
	$cover = trim( (string) ( $a['coverImage'] ?? '' ) );
	if ( '' !== $cover ) {
		$html = (string) preg_replace_callback(
			'#<figure\b[^>]*\bclass="[^"]*(?<![\w-])wp-block-image(?![\w-])[^"]*"[^>]*>.*?</figure>#s',
			static function ( array $m ) use ( $cover ): string {
				$tags = new \WP_HTML_Tag_Processor( $m[0] );
				while ( $tags->next_tag( 'img' ) ) {
					if ( in_array( $cover, array( $tags->get_attribute( 'src' ), $tags->get_attribute( 'data-src' ) ), true ) ) {
						return '';
					}
				}
				return $m[0];
			},
			$html
		);
	}
	$note = plain( (string) ( $a['review'] ?? '' ) );
	if ( '' !== $note ) {
		$html = (string) preg_replace_callback(
			'#<p\b[^>]*>(.*?)</p>#s',
			static fn( array $m ): string => plain( $m[1] ) === $note ? '' : $m[0],
			$html
		);
	}
	if ( $html !== $given ) {
		do {
			$html = (string) preg_replace( '#<div\b[^>]*\bclass="[^"]*(?<![\w-])wp-block-group(?![\w-])[^"]*"[^>]*>\s*</div>#', '', $html, -1, $emptied );
		} while ( $emptied > 0 );
	}
	return $html;
}

/**
 * Split markup at the first closing div it does not itself open: the end
 * of the container the card sits in.
 *
 * @param string $html Markup that follows the card.
 * @return array{0: string, 1: string} What is left inside the container, then the rest.
 */
function split_at_container_end( string $html ): array {
	$depth = 0;
	if ( preg_match_all( '#<(/?)div\b[^>]*>#i', $html, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		foreach ( $tags as $tag ) {
			if ( '' === $tag[1][0] ) {
				++$depth;
			} elseif ( 0 === $depth ) {
				return array( substr( $html, 0, (int) $tag[0][1] ), substr( $html, (int) $tag[0][1] ) );
			} else {
				--$depth;
			}
		}
	}
	return array( '', $html );
}

/**
 * Would this markup put anything on the page? Text or media counts;
 * comments, whitespace and elements carrying the hidden attribute (the
 * plugin's entry properties) do not.
 *
 * @param string $html Markup.
 * @return bool
 */
function shows_something( string $html ): bool {
	$html = (string) preg_replace( array( '/<!--.*?-->/s', '#<(\w+)\b[^>]*\shidden(?:=""|(?=[\s>/]))[^>]*>.*?</\1>#s' ), '', $html );
	return '' !== plain( $html ) || 1 === preg_match( '/<(?:img|picture|video|audio|iframe|svg|object|embed|canvas|hr|table|form)\b/i', $html );
}

/**
 * Put markup just before the card's meta row, the last thing in its body.
 *
 * @param string $html   Card.
 * @param string $insert Markup.
 * @return string
 */
function before_meta( string $html, string $insert ): string {
	$pos = strpos( $html, '<div class="pk-meta">' );
	return false === $pos ? $html : substr( $html, 0, $pos ) . $insert . substr( $html, $pos );
}

/**
 * Add classes to the card's article.
 *
 * @param string   $html    Card.
 * @param string[] $classes Classes.
 * @return string
 */
function card_classes( string $html, array $classes ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pk-card',
		)
	) ) {
		foreach ( $classes as $class_name ) {
			$tags->add_class( $class_name );
		}
		$html = $tags->get_updated_html();
	}
	return $html;
}

/**
 * Name the rack's title link by its own text and the shelf tag beside it.
 *
 * Volume and issue print next to the link, not in it. Named by its text
 * alone, two issues of one series are the same link to a screen reader, so
 * the link is labelled by both. The tag stays where it is: the heading's
 * p-name and the one-line layout don't change. Only a link that comes
 * before the tag is named, because the tag follows the heading.
 *
 * @param string $html Card with the tag after its title.
 * @return string
 */
function name_link_by_tag( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( array( 'class_name' => 'pk-title' ) ) || ! $tags->next_tag( 'a' ) ) {
		return $html;
	}
	$title = wp_unique_id( 'cr-comic-title-' );
	$label = wp_unique_id( 'cr-comic-label-' );
	$tags->set_attribute( 'id', $title );
	$tags->set_attribute( 'aria-labelledby', $title . ' ' . $label );
	if ( ! $tags->next_tag(
		array(
			'tag_name'   => 'p',
			'class_name' => 'cr-comic__label',
		)
	) ) {
		return $html;
	}
	$tags->set_attribute( 'id', $label );
	return $tags->get_updated_html();
}

/**
 * Dress a comic read's stream card for the rack or the Stream.
 *
 * @param string    $html     Rendered stream card.
 * @param array     $block    Parsed stream-card block.
 * @param \WP_Block $instance Block instance with the Query Loop's postId.
 * @return string
 */
function bag_card( string $html, array $block, $instance ): string {
	$rack = is_rack() || false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), RACK_CARD_STYLE );
	if ( ! $rack && ! is_stream_surface() ) {
		return $html;
	}
	$post_id = ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;
	$comic   = comic_read( $post );
	if ( null === $comic ) {
		// A strip its author drew stands on the rack as its picture and
		// title link; the excerpt, date and "Read more" stay off the rack.
		if ( $rack && $post instanceof \WP_Post && has_term( 'comics', 'kind', $post ) ) {
			foreach ( array( array( 'p', 'pk-stream-date' ), array( 'p', 'pk-excerpt' ), array( 'div', 'pk-meta' ), array( 'span', 'pk-kindlabel' ), array( 'div', 'pk-badge' ) ) as $cut ) {
				$html = cut_elements( $html, $cut[0], $cut[1] );
			}
			$html = card_classes( $html, array( 'cr-comic-strip' ) );
		}
		return $html;
	}

	// A comic whose body holds more than the card (the Micropub shape: the
	// card, the picture, the note as a paragraph) gets the plugin's generic
	// stream card. Render the comic card itself, with the plugin's own
	// stream helpers for the title link, the date and the heading level.
	if ( false === strpos( $html, 'pk-card k-comics' ) || false === strpos( $html, 'u-read-of' ) ) {
		$card = render_block( $comic );
		if ( '' === $card || false === strpos( $card, 'pk-card k-comics' ) || ! function_exists( '\\PKIW\\link_title_to_post' ) ) {
			return $html;
		}
		$card = \PKIW\link_title_to_post( $card, $post );
		if ( function_exists( '\\PKIW\\apply_stream_heading_level' ) ) {
			$card = \PKIW\apply_stream_heading_level( $card, max( 2, min( 4, (int) ( $block['attrs']['headingLevel'] ?? 2 ) ) ) );
		}
		// The plugin adds the entry's own URL, date and author after
		// adapters run, and makes the list item the entry root.
		$html = $card;
	}

	$a         = comic_attrs( $post, $comic );
	$status    = (string) $a['readStatus'];
	$has_cover = has_cover( $html );
	$classes   = array( 'cr-comic', $rack ? 'cr-comic--rack' : 'cr-comic--stream', 'cr-comic--' . $status );
	if ( $has_cover ) {
		$classes[] = 'has-cover';
	}

	// On the rack the title link covers the whole bag and names it, so the
	// cover's alt is emptied and the comic isn't announced twice. On the
	// Stream a stored description of the art stays; the plugin's fallback
	// ("Cover of …") repeats the heading, so it is emptied.
	$eager = $rack && ! is_paged() && in_the_loop() && $GLOBALS['wp_query']->current_post < FIRST_ROW;
	$tags  = new \WP_HTML_Tag_Processor( $html );
	if ( $has_cover && $tags->next_tag(
		array(
			'tag_name'   => 'img',
			'class_name' => 'u-photo',
		)
	) ) {
		if ( $rack || '' === trim( (string) ( $a['coverImageAlt'] ?? '' ) ) ) {
			$tags->set_attribute( 'alt', '' );
		}
		if ( $eager ) {
			// Perfmatters' lazy load skips an image whose fetchpriority is
			// high, so its fade-in can't hold the first row's bags empty.
			$tags->set_attribute( 'loading', 'eager' );
			$tags->set_attribute( 'fetchpriority', 'high' );
		}
		$html = $tags->get_updated_html();
	}

	take_element( $html, 'p', 'pk-stream-date' );

	if ( $rack ) {
		// The rack is for browsing covers: one bag, one link. The title
		// stays readable on the shelf tag under the bag, because cover art
		// does not always carry it. Series, volume and issue join it there
		// when stored, because they tell one issue from another.
		$issue = take_element( $html, 'p', 'pk-comic-issue' );
		foreach ( array( array( 'p', 'pk-sub' ), array( 'div', 'pk-stars' ), array( 'data', 'p-rating' ), array( 'div', 'pk-note' ), array( 'span', 'pk-kindlabel' ), array( 'div', 'pk-badge' ) ) as $cut ) {
			$html = cut_elements( $html, $cut[0], $cut[1] );
		}
		if ( '' !== $issue ) {
			// A series named like the title would read "Saga Saga".
			if ( 0 === strcasecmp( plain( (string) ( $a['series'] ?? '' ) ), plain( (string) ( $a['title'] ?? '' ) ) ) ) {
				$issue = cut_elements( $issue, 'span', 'pk-comic-series' );
			}
			if ( false !== strpos( $issue, '<span' ) ) {
				$label = str_replace( 'class="pk-sub pk-comic-issue"', 'class="cr-comic__label"', $issue );
				$html  = (string) preg_replace( '#</h[2-4]>#', '$0' . $label, $html, 1 );
				$html  = name_link_by_tag( $html );
			}
		}
		$extra = $has_cover ? '' : type_cover( $a, $post, false );
		if ( 'finished' !== $status ) {
			list( $label ) = status_copy( $status );
			$extra        .= '<p class="cr-comic__sticker cr-comic__sticker--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</p>';
		}
		$html = before_meta( $html, $extra );
		$html = cut_elements( $html, 'div', 'pk-meta' );
		return card_classes( $html, $classes );
	}

	// Stream: no publisher, no dates; the rating also reads as a number.
	$html = cut_elements( $html, 'span', 'pk-comic-publisher' );
	if ( ! $has_cover ) {
		$html = before_meta( $html, type_cover( $a, $post, true ) );
	}
	$html   = cut_elements( $html, 'div', 'pk-meta' );
	$rating = (int) ( $a['rating'] ?? 0 );
	$s_pos  = strpos( $html, '<div class="pk-stars"' );
	$s_end  = false !== $s_pos ? strpos( $html, '</div>', $s_pos ) : false;
	if ( $rating > 0 && false !== $s_end ) {
		$html = substr( $html, 0, $s_end ) . '<span class="pk-rating-value">' . esc_html( sprintf( '%d / 5', $rating ) ) . '</span>' . substr( $html, $s_end );
	}
	$classes[] = 'pk-card--stream';
	return card_classes( $html, $classes );
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\bag_card', 10, 3 );

/**
 * Creators, rating, status and its date under the H1.
 *
 * @param string               $html  Rendered block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function add_title_lede( string $html, array $block ): string {
	if ( 'core/post-title' !== ( $block['blockName'] ?? '' ) || ! is_comic_single() || get_the_ID() !== get_queried_object_id() ) {
		return $html;
	}
	$post  = \get_post();
	$comic = comic_read( $post );
	if ( null === $comic ) {
		return $html;
	}
	$a        = comic_attrs( $post, $comic );
	$creators = trim( (string) ( $a['creators'] ?? '' ) );
	$status   = (string) $a['readStatus'];
	$rating   = (int) ( $a['rating'] ?? 0 );
	$out      = '';

	if ( '' !== $creators ) {
		$out .= '<div class="cr-journal__lede cr-comic__creators">' . esc_html( $creators ) . '</div>';
	}
	if ( $rating > 0 ) {
		$out .= '<p class="cr-journal__rating" role="img" aria-label="' . esc_attr( sprintf( /* translators: %d: rating */ __( 'Rated %d of 5', 'courtneyr-child' ), $rating ) ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$out .= '<span class="cr-journal__star' . ( $i <= $rating ? '' : ' cr-journal__star--off' ) . '"><svg viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M12 2l3 6.5 7 .6-5.3 4.6 1.6 6.8L12 17l-6.9 3.5 1.6-6.8L1.4 9.1l7-.6z"/></svg></span>';
		}
		$out .= '<span class="cr-journal__rating-value">' . esc_html( sprintf( '%d / 5', $rating ) ) . '</span></p>';
	}

	// The date beside the status says what the status means: a comic still
	// being read shows when it was started, never a completion.
	list( $label ) = status_copy( $status );
	$when          = '';
	if ( 'reading' === $status ) {
		list( $iso, $disp ) = day( (string) ( $a['startedAt'] ?? '' ) );
		if ( '' !== $iso ) {
			$when = esc_html__( 'since', 'courtneyr-child' ) . ' <time datetime="' . esc_attr( $iso ) . '">' . esc_html( $disp ) . '</time>';
		}
	} elseif ( in_array( $status, array( 'finished', 'abandoned' ), true ) ) {
		list( $iso, $disp ) = day( (string) ( $a['finishedAt'] ?? '' ) );
		if ( '' !== $iso ) {
			$when = '<time datetime="' . esc_attr( $iso ) . '">' . esc_html( $disp ) . '</time>';
		}
	}
	$out .= '<p class="cr-comic__status cr-comic__status--' . esc_attr( $status ) . '"><span class="cr-comic__status-chip">' . esc_html( $label ) . '</span>'
		. ( '' !== $when ? '<span class="cr-comic__status-when">' . $when . '</span>' : '' ) . '</p>';

	return $html . $out;
}
add_filter( 'render_block', __NAMESPACE__ . '\\add_title_lede', 20, 2 );

/**
 * The library card: only stored facts, each labelled for what it is.
 *
 * @param array<string, mixed> $a    Attributes.
 * @param \WP_Post             $post Post.
 * @return string Empty when no fact is stored.
 */
function reading_record( array $a, \WP_Post $post ): string {
	$status = (string) $a['readStatus'];
	$ended  = in_array( $status, array( 'finished', 'abandoned' ), true );
	$rows   = array();

	list( $s_iso, $s_disp ) = 'to-read' === $status ? array( '', '' ) : day( (string) ( $a['startedAt'] ?? '' ) );
	list( $f_iso, $f_disp ) = $ended ? day( (string) ( $a['finishedAt'] ?? '' ) ) : array( '', '' );

	if ( '' !== $s_iso ) {
		$rows[] = array( __( 'Started', 'courtneyr-child' ), '<time datetime="' . esc_attr( $s_iso ) . '">' . esc_html( $s_disp ) . '</time>' );
	}
	if ( '' !== $f_iso ) {
		$rows[] = array( 'abandoned' === $status ? __( 'Set aside', 'courtneyr-child' ) : __( 'Finished', 'courtneyr-child' ), '<time datetime="' . esc_attr( $f_iso ) . '">' . esc_html( $f_disp ) . '</time>' );
	}
	foreach ( array(
		'series'      => __( 'Series', 'courtneyr-child' ),
		'volume'      => __( 'Volume', 'courtneyr-child' ),
		'issueNumber' => __( 'Issue', 'courtneyr-child' ),
		'publisher'   => __( 'Publisher', 'courtneyr-child' ),
	) as $key => $label ) {
		$value = trim( (string) ( $a[ $key ] ?? '' ) );
		if ( '' !== $value ) {
			$rows[] = array( $label, esc_html( $value ) );
		}
	}
	if ( empty( $rows ) ) {
		return '';
	}

	list( , $word ) = status_copy( $status );
	$stamp_day      = '' !== $f_iso ? $f_iso : $s_iso;
	$seed           = seed( (string) $post->ID, (string) ( $a['title'] ?? '' ) );
	$stamp          = render(
		array(
			'shape'  => 'seal',
			'big'    => $word,
			'small'  => '' !== $stamp_day ? gmdate( 'd M Y', (int) strtotime( $stamp_day . ' 12:00:00 UTC' ) ) : '',
			'ring'   => __( 'Reading record', 'courtneyr-child' ),
			'ink'    => INKS[ pick( $seed, 3, count( INKS ) ) ],
			'tilt'   => pick( $seed, 6, TILTS ),
			'uid'    => 'cr-comic-seal-' . $post->ID,
			'family' => 'reading-record',
		)
	);

	$out  = '<section class="cr-record"><h2 class="cr-record-title">' . esc_html__( 'Reading record', 'courtneyr-child' ) . '</h2>';
	$out .= '<p class="cr-record-eyebrow">' . esc_html( sprintf( /* translators: %s: site name */ __( 'From the library of %s', 'courtneyr-child' ), get_bloginfo( 'name' ) ) ) . '</p>';
	$out .= '<dl class="cr-record-rows">';
	foreach ( $rows as $row ) {
		$out .= '<div class="cr-record-row"><dt>' . esc_html( $row[0] ) . '</dt><dd>' . $row[1] . '</dd></div>';
	}
	return $out . '</dl><div class="cr-record-stamp">' . $stamp . '</div></section>';
}

/**
 * The stored link, as the "Read / find it" row.
 *
 * @param array<string, mixed> $a Attributes.
 * @return string Empty when no usable link is stored.
 */
function sources_row( array $a ): string {
	$url = trim( (string) ( $a['sourceUrl'] ?? '' ) );
	if ( '' === $url || '' === (string) wp_parse_url( $url, PHP_URL_HOST ) || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
		return '';
	}
	// A known shop or library is named; any other host reads "Comic".
	$label = book_label( $url );
	if ( __( 'Book', 'courtneyr-child' ) === $label ) {
		$label = __( 'Comic', 'courtneyr-child' );
	}
	return '<nav class="pk-sources pk-sources--read" aria-label="' . esc_attr__( 'Read or find it', 'courtneyr-child' ) . '">'
		. '<p class="pk-sources__label">' . esc_html__( 'Read / find it', 'courtneyr-child' ) . '</p><ul class="pk-sources__list">'
		. '<li><a class="pk-sources__link pk-sources__link--book" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">'
		. esc_html( $label )
		. '<span class="pk-sr-only"> ' . esc_html__( '(opens in a new tab)', 'courtneyr-child' ) . '</span>'
		. '<span class="pk-sources__arrow" aria-hidden="true">↗</span></a></li></ul></nav>';
}

/**
 * Build the single: the bagged comic, then its notes, record and link.
 *
 * @param string               $html  Rendered post content.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function comic_page( string $html, array $block ): string {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) || ! is_comic_single() ) {
		return $html;
	}
	$post  = \get_post();
	$comic = comic_read( $post );
	// The comic card's own article: another card may come before it.
	if ( null === $comic || ! preg_match( '/<article\b[^>]*\bclass="[^"]*(?<![\w-])k-comics(?![\w-])[^"]*"[^>]*>/', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$open  = (int) $m[0][1];
	$close = strpos( $html, '</article>', $open );
	if ( false === $close ) {
		return $html;
	}
	$close += 10;
	$a      = comic_attrs( $post, $comic );
	$before = drop_repeats( substr( $html, 0, $open ), $a );
	$card   = substr( $html, $open, $close - $open );
	$rest   = drop_repeats( substr( $html, $close ), $a );

	// The note leaves the bag for the notes card.
	$note = take_element( $card, 'div', 'pk-note' );

	$has_cover = has_cover( $card );
	if ( ! $has_cover ) {
		$card = before_meta( $card, type_cover( $a, $post, true ) );
	}
	$card = card_classes( $card, array_merge( array( 'cr-comic', 'cr-comic--single', 'cr-comic--' . $a['readStatus'] ), $has_cover ? array( 'has-cover' ) : array() ) );

	// The cover stands at the top of the page and is its largest image.
	// Eager with fetchpriority high: the browser fetches it first, and
	// Perfmatters' lazy load (which skips fetchpriority="high") leaves its
	// real src in place, so it also shows with scripting off.
	$tags = new \WP_HTML_Tag_Processor( $card );
	if ( $has_cover && $tags->next_tag(
		array(
			'tag_name'   => 'img',
			'class_name' => 'u-photo',
		)
	) ) {
		$tags->set_attribute( 'loading', 'eager' );
		$tags->set_attribute( 'fetchpriority', 'high' );
		$card = $tags->get_updated_html();
	}

	$after = '';
	if ( '' !== $note ) {
		$after .= notes_section( __( 'Notes from this read', 'courtneyr-child' ), $note );
	}
	$after .= reading_record( $a, $post ) . sources_row( $a );

	// Whatever the author wrote after the card follows the record and the
	// link in the same column, in the order it was written.
	list( $more, $tail ) = split_at_container_end( $rest );
	if ( shows_something( $more ) ) {
		$after .= '<div class="cr-journal__more is-layout-flow">' . $more . '</div>';
		$rest   = $tail;
	}

	return $before . '<div class="cr-journal cr-journal--comic">' . $card . $after . '</div>' . $rest;
}
add_filter( 'render_block', __NAMESPACE__ . '\\comic_page', 20, 2 );
