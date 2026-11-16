<?php
/**
 * Tag archive as name-tag sticker sheets, plus shared tag presentation helpers.
 *
 * The plugin owns every fact and the privacy boundary: `\PKIW\kind_facts()`
 * returns nothing for a tag post that isn't public, and this file paints only
 * what those facts and the plugin's own entry markup already allow.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Tag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'tag';
const ENTRY_BLOCK  = 'post-kinds-indieweb/tag-entry';
const ENTRY_STYLE  = 'cr-name-tag-sheet';
const ENTRY_CLASS  = 'is-style-cr-name-tag-sheet';
const STYLE_HANDLE = 'courtneyr-tag';
const STYLE_FILE   = 'cr-tag.css';

/**
 * Band fill => band ink. Every pair measures at least 4.5:1 (decision
 * correction "Allowed band pairs"). Glaucous is left out on purpose; the
 * fill is seeded from the post ID and carries no meaning.
 */
const BAND_PAIRS = array(
	'russian-violet'   => 'printer-ivory',
	'prussian-blue'    => 'printer-ivory',
	'cerulean'         => 'printer-ivory',
	'ut-orange'        => 'russian-violet',
	'sky-blue'         => 'russian-violet',
	'selective-yellow' => 'russian-violet',
	'periwinkle'       => 'russian-violet',
	'blue-green'       => 'russian-violet',
	'light-orange'     => 'russian-violet',
);

/** Labels this long or shorter print in the Rock Salt display face (Interpretation I7). */
const DISPLAY_LABEL_MAX = 24;

/** Whether the current request is the tag archive. */
function is_tag_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether the current request is a tag single. */
function is_tag_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/** Whether the current request is a Stream surface. */
function is_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
}

/**
 * Resolve a Query Loop post ID.
 *
 * @param mixed $instance Block instance.
 */
function block_post_id( $instance ): int {
	if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/**
 * Read visitor-safe tag facts; empty for any post that isn't public.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( $post_id < 1 || ! function_exists( '\\PKIW\\kind_facts' ) ) {
		return array();
	}
	$facts = \PKIW\kind_facts( $post_id );
	return is_array( $facts ) ? $facts : array();
}

/**
 * Whether a className attribute contains one complete token.
 *
 * @param string $class_name Class attribute value.
 * @param string $wanted     Token to find.
 */
function has_class_name( string $class_name, string $wanted ): bool {
	$tokens = array_filter( explode( ' ', str_replace( array( "\t", "\n", "\r" ), ' ', $class_name ) ) );
	return in_array( $wanted, $tokens, true );
}

/**
 * The seeded band pair for one post.
 *
 * @param int $post_id Post ID.
 * @return array{band: string, ink: string}
 */
function band_pair( int $post_id ): array {
	$bands = array_keys( BAND_PAIRS );
	$band  = $bands[ abs( $post_id ) % count( $bands ) ];
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		$seeded = \Courtneyr\Child\Stamps\seeded_paper( $post_id, $bands );
		if ( isset( BAND_PAIRS[ $seeded ] ) ) {
			$band = $seeded;
		}
	}
	return array(
		'band' => $band,
		'ink'  => BAND_PAIRS[ $band ],
	);
}

/**
 * The inline custom properties that paint one sticker band.
 *
 * @param int $post_id Post ID.
 */
function band_style( int $post_id ): string {
	$pair = band_pair( $post_id );
	return '--cr-name-tag-band:var(--cr-' . $pair['band'] . ');--cr-name-tag-ink:var(--cr-' . $pair['ink'] . ')';
}

/**
 * Pick a sticker tilt (0-3, one --cr-rotate-* token each), never the previous one.
 *
 * @param int      $post_id  Post ID.
 * @param int|null $previous Previous sticker's tilt on this page.
 */
function tilt( int $post_id, ?int $previous = null ): int {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seed' ) && function_exists( '\\Courtneyr\\Child\\Stamps\\pick' ) ) {
		$tilt = \Courtneyr\Child\Stamps\pick( \Courtneyr\Child\Stamps\seed( (string) $post_id ), 6, 4 );
	} else {
		$tilt = abs( $post_id ) % 4;
	}
	return null !== $previous && $tilt === $previous ? ( $tilt + 1 ) % 4 : $tilt;
}

/** The printed "HELLO / my name is" band: live, translatable, aria-hidden. */
function band_html(): string {
	return '<span class="cr-name-tag__band" aria-hidden="true"><span class="cr-name-tag__hello">' . esc_html__( 'HELLO', 'courtneyr-child' ) . '</span><span class="cr-name-tag__my-name">' . esc_html__( 'my name is', 'courtneyr-child' ) . '</span></span>';
}

/**
 * Whether a label prints in the display face.
 *
 * @param string $label Stored label.
 */
function is_display_label( string $label ): bool {
	return mb_strlen( trim( $label ) ) <= DISPLAY_LABEL_MAX;
}

/** Register the sheet style and attach its editor stylesheet. */
function register_block_styles(): void {
	if ( ! class_exists( '\\WP_Block_Type_Registry' ) || ! \WP_Block_Type_Registry::get_instance()->is_registered( ENTRY_BLOCK ) ) {
		return;
	}

	register_block_style(
		ENTRY_BLOCK,
		array(
			'name'  => ENTRY_STYLE,
			'label' => __( 'Name-tag sheets', 'courtneyr-child' ),
		)
	);

	wp_enqueue_block_style(
		ENTRY_BLOCK,
		array(
			'handle' => STYLE_HANDLE,
			'src'    => COURTNEYR_CHILD_URI . '/assets/css/' . STYLE_FILE,
			'deps'   => array(),
			'ver'    => COURTNEYR_CHILD_VERSION,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_styles', 20 );

/**
 * Byte offset just after the opening tag of the first article carrying a class token.
 *
 * @param string $html       Markup.
 * @param string $class_name Class token the article must carry.
 */
function after_open_tag( string $html, string $class_name ): int {
	$offset = 0;
	while ( false !== ( $start = strpos( $html, '<article', $offset ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		$end = strpos( $html, '>', $start );
		if ( false === $end ) {
			return -1;
		}
		$open = substr( $html, $start, $end - $start );
		if ( str_contains( ' ' . str_replace( '"', ' ', $open ) . ' ', ' ' . $class_name . ' ' ) ) {
			return $end + 1;
		}
		$offset = $end;
	}
	return -1;
}

/**
 * Mark sheet headings whose stored label is too long for the display face.
 *
 * @param string $html Rendered entry markup, which may open a new sheet.
 */
function mark_long_headings( string $html ): string {
	if ( ! str_contains( $html, 'pkiw-tag-section__heading' ) ) {
		return $html;
	}
	// First pass reads each heading's text; the second adds the classes.
	$texts   = array();
	$reader  = new \WP_HTML_Tag_Processor( $html );
	$current = -1;
	$name    = '';
	while ( $reader->next_token() ) {
		$type = $reader->get_token_type();
		if ( '#tag' === $type ) {
			if ( -1 === $current && ! $reader->is_tag_closer() && $reader->has_class( 'pkiw-tag-section__heading' ) ) {
				$texts[] = '';
				$current = count( $texts ) - 1;
				$name    = (string) $reader->get_tag();
			} elseif ( -1 !== $current && $reader->is_tag_closer() && $reader->get_tag() === $name ) {
				$current = -1;
			}
		} elseif ( '#text' === $type && -1 !== $current ) {
			$texts[ $current ] .= $reader->get_modifiable_text();
		}
	}

	$tags  = new \WP_HTML_Tag_Processor( $html );
	$index = 0;
	while ( $tags->next_tag( array( 'class_name' => 'pkiw-tag-section__heading' ) ) ) {
		$tags->add_class( 'cr-name-tag-sheet__heading' );
		if ( ! is_display_label( $texts[ $index ] ?? '' ) ) {
			$tags->add_class( 'cr-name-tag-sheet__heading--long' );
		}
		++$index;
	}
	return $tags->get_updated_html();
}

/**
 * Paint one plugin entry as a die-cut name-tag sticker.
 *
 * @param string               $html     Rendered tag entry.
 * @param array<string, mixed> $block    Parsed block.
 * @param mixed                $instance Block instance.
 */
function entry_paint( string $html, array $block, $instance ): string {
	static $previous_tilt = null;

	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( '' === $html || ! has_class_name( $class_name, ENTRY_CLASS ) || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	if ( array() === facts( $post_id ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag(
		array(
			'tag_name'   => 'article',
			'class_name' => 'pkiw-tag-entry',
		)
	) || $tags->has_class( 'cr-name-tag' ) ) {
		return $html;
	}

	$tilt = tilt( $post_id, $previous_tilt );
	$tags->add_class( 'cr-name-tag' );
	$tags->add_class( 'cr-name-tag-tilt-' . $tilt );
	$tags->set_attribute( 'style', band_style( $post_id ) );
	$previous_tilt = $tilt;

	$html = $tags->get_updated_html();
	$at   = after_open_tag( $html, 'pkiw-tag-entry' );
	if ( $at < 0 ) {
		return $html;
	}
	return substr( $html, 0, $at ) . band_html() . substr( $html, $at );
}
add_filter( 'render_block_' . ENTRY_BLOCK, __NAMESPACE__ . '\\entry_paint', 10, 3 );

/**
 * Leave the sticker article as each item's only h-entry root.
 *
 * Core's post template copies `get_post_class()` onto every `li.wp-block-post`,
 * and that list carries `h-entry` and core's classic `hentry` (which mf1
 * backcompat upgrades to h-entry), so an mf2 parser saw an empty outer entry
 * wrapping the plugin's populated `article.h-entry`. Both classes come off an
 * item only when the item holds its own article root.
 *
 * @param string $html Rendered post template.
 */
function single_entry_roots( string $html ): string {
	if ( ! str_contains( $html, 'wp-block-post' ) ) {
		return $html;
	}
	$tags    = new \WP_HTML_Tag_Processor( $html );
	$pending = false;
	while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag = $tags->get_tag();
		if ( 'LI' === $tag ) {
			$pending = ! $tags->is_tag_closer() && $tags->has_class( 'wp-block-post' ) && ( $tags->has_class( 'h-entry' ) || $tags->has_class( 'hentry' ) ) && $tags->set_bookmark( 'cr-tag-item' );
			continue;
		}
		if ( $pending && 'ARTICLE' === $tag && ! $tags->is_tag_closer() && $tags->has_class( 'h-entry' ) ) {
			$pending = false;
			if ( $tags->set_bookmark( 'cr-tag-article' ) && $tags->seek( 'cr-tag-item' ) ) {
				$tags->remove_class( 'h-entry' );
				$tags->remove_class( 'hentry' );
				$tags->seek( 'cr-tag-article' );
			}
		}
	}
	return $tags->get_updated_html();
}

/**
 * Mark long sheet headings in the grouped post template of the sheet pattern.
 *
 * The plugin engine prints each sheet's H2 outside the entry, in the post
 * template's sections, so the face choice happens here.
 *
 * @param string               $html  Rendered post template.
 * @param array<string, mixed> $block Parsed block.
 */
function sheet_headings( string $html, array $block ): string {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( '' === $html || ! has_class_name( $class_name, 'cr-tag-sheets__entries' ) || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	return mark_long_headings( single_entry_roots( $html ) );
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\sheet_headings', 20, 2 );

/** Load the tag paint on every surface that can render tag objects. */
function enqueue(): void {
	if ( ! is_tag_archive() && ! is_tag_single() && ! is_stream_surface() ) {
		return;
	}
	// The single's backing and clipping are shared cr-paper.css sheets.
	if ( is_tag_single() ) {
		wp_enqueue_style( 'courtneyr-paper' );
	}
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/' . STYLE_FILE,
		array(),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue', 20 );

/**
 * Keep the conditional tag sheet out of shared used CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 * @return string[]
 */
function rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = STYLE_FILE;
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\rucss_exclusions', 20 );

/**
 * Published posts whose collection-item blocks reference one post.
 *
 * @param int $post_id Referenced post ID.
 * @return int[]
 */
function collection_posts_referencing( int $post_id ): array {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}
	global $wpdb;
	if ( $post_id < 1 || ! is_object( $wpdb ) ) {
		$cache[ $post_id ] = array();
		return array();
	}
	$block = '%' . $wpdb->esc_like( '<!-- wp:post-kinds-indieweb/collection-item ' ) . '%';
	$comma = '%' . $wpdb->esc_like( '"postId":' . $post_id . ',' ) . '%';
	$brace = '%' . $wpdb->esc_like( '"postId":' . $post_id . '}' ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one request-cached scan, only when a tag or follow post's eligibility purge runs.
	$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s AND ( post_content LIKE %s OR post_content LIKE %s ) ORDER BY ID ASC", $block, $comma, $brace ) );

	$cache[ $post_id ] = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	return $cache[ $post_id ];
}

/**
 * Add series and collection pages to the edge purge for a tag or follow post (finding 6).
 *
 * @param mixed    $paths Purge paths: urls and regex lists.
 * @param \WP_Post $post  Tag or follow post whose eligibility changed.
 * @return mixed
 */
function purge_urls( $paths, $post ) {
	if ( ! is_array( $paths ) || ! $post instanceof \WP_Post || ! class_exists( '\\PKIW\\Public_Eligibility' ) ) {
		return $paths;
	}
	$paths['urls']  = isset( $paths['urls'] ) ? (array) $paths['urls'] : array();
	$paths['regex'] = isset( $paths['regex'] ) ? (array) $paths['regex'] : array();

	$taxonomy = defined( '\\Courtneyr\\Child\\Series\\TAX' ) ? \Courtneyr\Child\Series\TAX : 'series';
	$terms    = get_the_terms( $post, $taxonomy );
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$link = get_term_link( $term, $taxonomy );
		if ( ! is_string( $link ) || '' === $link ) {
			continue;
		}
		$paths['urls'][] = $link;
		$path            = (string) wp_parse_url( $link, PHP_URL_PATH );
		if ( '' !== $path ) {
			$paths['regex'][] = '^' . trailingslashit( $path ) . 'page/';
		}
	}

	foreach ( collection_posts_referencing( (int) $post->ID ) as $collection_id ) {
		// Synced patterns and other non-public types can hold the block too; only public pages are cached.
		if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $collection_id ) ) {
			continue;
		}
		$link = get_permalink( $collection_id );
		if ( is_string( $link ) && '' !== $link ) {
			$paths['urls'][] = $link;
		}
	}

	$paths['urls']  = array_values( array_unique( $paths['urls'] ) );
	$paths['regex'] = array_values( array_unique( $paths['regex'] ) );
	return $paths;
}
add_filter( 'pkiw_public_eligibility_purge_urls', __NAMESPACE__ . '\\purge_urls', 10, 2 );
