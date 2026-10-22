<?php
/**
 * Issue archive tickets and shared issue presentation helpers (PKIW issue 264).
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\Issue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND         = 'issue';
const CARD_STYLE   = 'cr-claim-ticket';
const CARD_CLASS   = 'is-style-cr-claim-ticket';
const STYLE_HANDLE = 'courtneyr-issue';
const PAPERS       = array(
	'peach' => 'var(--cr-light-orange)',
	'sky'   => 'var(--cr-sky-blue)',
	'ivory' => 'var(--cr-printer-ivory)',
);

/** Whether this request is the issue archive. */
function is_issue_archive(): bool {
	return is_tax( 'kind', KIND );
}

/** Whether this request is an issue single. */
function is_issue_single(): bool {
	return is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() );
}

/** Whether this request is a Stream surface. */
function on_stream_surface(): bool {
	return function_exists( '\\Courtneyr\\Child\\HomeSections\\is_stream_surface' )
		&& \Courtneyr\Child\HomeSections\is_stream_surface();
}

/** Register the claim-ticket variation and its stylesheet once. */
function register_card_style(): void {
	static $registered = false;

	if ( $registered ) {
		return;
	}
	if ( ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) || ! function_exists( 'wp_register_style' ) || ! function_exists( 'register_block_style' ) ) {
		return;
	}

	// The handle keeps both dependencies: a later wp_enqueue_style() with a src never replaces a registered handle's deps.
	register_nav_dependency();
	wp_register_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-issue.css',
		array( 'courtneyr-paper', 'courtneyr-nav' ),
		COURTNEYR_CHILD_VERSION
	);
	if ( defined( 'COURTNEYR_CHILD_DIR' ) && function_exists( 'wp_style_add_data' ) ) {
		wp_style_add_data( STYLE_HANDLE, 'path', COURTNEYR_CHILD_DIR . '/assets/css/cr-issue.css' );
	}
	register_block_style(
		'post-kinds-indieweb/stream-card',
		array(
			'name'         => CARD_STYLE,
			'label'        => __( 'Claim ticket', 'courtneyr-child' ),
			'style_handle' => STYLE_HANDLE,
		)
	);
	$registered = true;
}
add_action( 'init', __NAMESPACE__ . '\\register_card_style' );

/**
 * Resolve the Query Loop post ID.
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
 * Return public plugin facts for an issue.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function facts( int $post_id ): array {
	if ( ! function_exists( '\\PKIW\\kind_facts' ) ) {
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
 * Read the rendered title's heading level.
 *
 * @param string $html          Rendered card HTML.
 * @param int    $default_level Level used when no title heading exists.
 */
function heading_level( string $html, int $default_level = 4 ): int {
	if ( ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return max( 2, min( 4, $default_level ) );
	}
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$tag = (string) $tags->get_tag();
		if ( in_array( $tag, array( 'H2', 'H3', 'H4' ), true ) && $tags->has_class( 'pk-title' ) ) {
			return max( 2, min( 4, (int) substr( $tag, 1 ) ) );
		}
	}
	return max( 2, min( 4, $default_level ) );
}

/**
 * Pick a stable, non-semantic paper for one issue.
 *
 * @param \WP_Post $post Issue post.
 */
function paper_for( \WP_Post $post ): string {
	if ( function_exists( '\\Courtneyr\\Child\\Stamps\\seeded_paper' ) ) {
		$paper = \Courtneyr\Child\Stamps\seeded_paper( $post->ID, array_keys( PAPERS ) );
		if ( is_string( $paper ) && array_key_exists( $paper, PAPERS ) ) {
			return $paper;
		}
	}
	return 'peach';
}

/**
 * The visitor-facing issue name and whether it is the post title.
 *
 * @param \WP_Post             $post        Issue post.
 * @param array<string, mixed> $issue_facts Public issue facts.
 */
function issue_name( \WP_Post $post, array $issue_facts ): array {
	$name = trim( (string) ( $issue_facts['name'] ?? '' ) );
	if ( '' === $name ) {
		$name = trim( (string) get_the_title( $post ) );
	}
	if ( '' === $name ) {
		$name = __( 'Issue', 'courtneyr-child' );
	}
	return array( $name, array() === $issue_facts || ! empty( $issue_facts['name_is_title'] ) );
}

/** IDs for the visible posts on the current grouped page. */
function current_page_ids(): array {
	if ( class_exists( '\\PKIW\\Grouped_Archive' ) && method_exists( '\\PKIW\\Grouped_Archive', 'current_page_ids' ) ) {
		$ids = \PKIW\Grouped_Archive::current_page_ids();
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	$posts = isset( $GLOBALS['wp_query']->posts ) && is_array( $GLOBALS['wp_query']->posts )
		? $GLOBALS['wp_query']->posts
		: array();
	$ids   = array();
	foreach ( $posts as $post ) {
		if ( $post instanceof \WP_Post ) {
			$ids[] = $post->ID;
		} elseif ( is_numeric( $post ) ) {
			$ids[] = (int) $post;
		}
	}
	return $ids;
}

/**
 * Normalize a title for duplicate comparison.
 *
 * @param string $name Visitor-facing issue name.
 */
function comparable_name( string $name ): string {
	$name = trim( $name );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
}

/**
 * Whether another visible issue ticket has this title.
 *
 * @param \WP_Post $post Issue post.
 * @param string   $name Visitor-facing issue name.
 */
function has_duplicate_name( \WP_Post $post, string $name ): bool {
	$wanted = comparable_name( $name );
	foreach ( current_page_ids() as $post_id ) {
		if ( $post->ID === $post_id ) {
			continue;
		}
		$other = get_post( $post_id );
		if ( ! $other instanceof \WP_Post || ! has_term( KIND, 'kind', $other ) || post_password_required( $other ) ) {
			continue;
		}
		list( $other_name ) = issue_name( $other, facts( $other->ID ) );
		if ( 0 === strcmp( $wanted, comparable_name( $other_name ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Hidden entry properties retained by the replacement ticket.
 *
 * @param \WP_Post             $post        Issue post.
 * @param array<string, mixed> $issue_facts Public issue facts.
 */
function hidden_properties( \WP_Post $post, array $issue_facts ): string {
	$date_iso = (string) ( $issue_facts['date_iso'] ?? '' );
	if ( '' === $date_iso ) {
		$date_iso = (string) get_the_date( 'c', $post );
	}
	$out = '<time class="dt-published" datetime="' . esc_attr( $date_iso ) . '"></time>';
	if ( function_exists( '\\PKIW\\entry_author_html' ) ) {
		$out .= (string) \PKIW\entry_author_html( $post );
	}
	if ( class_exists( '\\PKIW\\Issue_Archive' ) && method_exists( '\\PKIW\\Issue_Archive', 'cite' ) ) {
		$out .= (string) \PKIW\Issue_Archive::cite( $issue_facts, $post );
	}
	return '<span class="cr-claim-ticket__props" hidden>' . $out . '</span>';
}

/**
 * Replace an issue archive stream-card with a one-link claim ticket.
 *
 * @param string $html     Rendered stream-card HTML.
 * @param array  $block    Parsed block.
 * @param mixed  $instance Block instance.
 */
function ticket( string $html, array $block, $instance ): string {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( '' === $html || ( ! has_class_name( $class_name, CARD_CLASS ) && ! is_issue_archive() ) ) {
		return $html;
	}

	$post_id = block_post_id( $instance );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof \WP_Post || ! has_term( KIND, 'kind', $post ) || post_password_required( $post ) || str_contains( $html, 'pk-card--protected' ) ) {
		return $html;
	}

	$issue_facts = facts( $post->ID );

	list( $name, $name_is_title ) = issue_name( $post, $issue_facts );

	$number_text = trim( (string) ( $issue_facts['number_text'] ?? '' ) );
	$title_id    = 'cr-claim-title-' . $post->ID;
	$number_id   = '' !== $number_text ? 'cr-claim-number-' . $post->ID : '';
	$link_attrs  = has_duplicate_name( $post, $name ) ? ' data-cr-claim-dup="' . esc_attr( $number_id ) . '"' : '';
	$link_class  = $name_is_title ? 'u-url p-name' : 'u-url';
	$level       = heading_level( $html, 4 );

	$out  = '<article class="pk-card cr-claim-ticket cr-claim-ticket--' . esc_attr( paper_for( $post ) ) . ' h-entry">';
	$out .= '<h' . $level . ' class="cr-claim-ticket__title"><a class="' . esc_attr( $link_class ) . '" id="' . esc_attr( $title_id ) . '" href="' . esc_url( (string) get_permalink( $post ) ) . '"' . $link_attrs . '>' . esc_html( $name ) . '</a></h' . $level . '>';
	if ( '' !== $number_text ) {
		$out .= '<span class="cr-claim-ticket__number" id="' . esc_attr( $number_id ) . '">' . esc_html( $number_text ) . '</span>';
	}
	$out .= hidden_properties( $post, $issue_facts );
	return $out . '</article>';
}
add_filter( 'render_block_post-kinds-indieweb/stream-card', __NAMESPACE__ . '\\ticket', 10, 3 );

/**
 * Add the number and repository heading to duplicate ticket link names.
 *
 * @param string $html  Rendered post-template HTML.
 * @param array  $block Parsed block.
 */
function label_duplicates( string $html, array $block ): string {
	$class_name = (string) ( $block['attrs']['className'] ?? '' );
	if ( '' === $html || ! has_class_name( $class_name, 'cr-issue-rack' ) || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$heading_id = '';
	$tags       = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( 'H2' === $tags->get_tag() && $tags->has_class( 'pkiw-group__heading' ) ) {
			$heading_id = (string) $tags->get_attribute( 'id' );
			continue;
		}
		if ( 'A' !== $tags->get_tag() || null === $tags->get_attribute( 'data-cr-claim-dup' ) ) {
			continue;
		}
		$ids = array_filter(
			array(
				(string) $tags->get_attribute( 'id' ),
				(string) $tags->get_attribute( 'data-cr-claim-dup' ),
				$heading_id,
			)
		);
		$tags->set_attribute( 'aria-labelledby', implode( ' ', $ids ) );
		$tags->remove_attribute( 'data-cr-claim-dup' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-template', __NAMESPACE__ . '\\label_duplicates', 10, 2 );

/**
 * Add issue archive and single body classes.
 *
 * @param string[] $classes Body classes.
 */
function body_class( array $classes ): array {
	if ( is_issue_archive() && ! in_array( 'cr-issue-archive', $classes, true ) ) {
		$classes[] = 'cr-issue-archive';
	}
	if ( is_issue_single() && ! in_array( 'cr-issue-single', $classes, true ) ) {
		$classes[] = 'cr-issue-single';
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Register the issue board with editor parity.
 *
 * @param array<string, array{classes?: array<int, string>}> $kinds Kind registry.
 */
function register_editor_parity( array $kinds ): array {
	$classes = isset( $kinds[ KIND ]['classes'] ) && is_array( $kinds[ KIND ]['classes'] )
		? $kinds[ KIND ]['classes']
		: array( 'single-post', 'kind-' . KIND );
	if ( ! in_array( 'cr-issue-board', $classes, true ) ) {
		$classes[] = 'cr-issue-board';
	}
	$kinds[ KIND ]['classes'] = $classes;
	return $kinds;
}
add_filter( 'courtneyr_child_editor_parity_kinds', __NAMESPACE__ . '\\register_editor_parity' );

/**
 * Keep the issue sheet when Perfmatters removes unused CSS.
 *
 * @param mixed $exclusions Existing exclusions.
 */
function perfmatters_rucss_exclusions( $exclusions ): array {
	$exclusions   = is_array( $exclusions ) ? $exclusions : array();
	$exclusions[] = 'cr-issue.css';
	return array_values( array_unique( $exclusions ) );
}
add_filter( 'perfmatters_rucss_excluded_stylesheets', __NAMESPACE__ . '\\perfmatters_rucss_exclusions' );

/** Register the navigation dependency when the nav module has not done so. */
function register_nav_dependency(): void {
	if ( function_exists( 'wp_style_is' ) && wp_style_is( 'courtneyr-nav', 'registered' ) ) {
		return;
	}
	if ( ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) || ! defined( 'COURTNEYR_CHILD_DIR' ) || ! function_exists( 'wp_register_style' ) ) {
		return;
	}
	wp_register_style( 'courtneyr-nav', COURTNEYR_CHILD_URI . '/assets/css/cr-nav.css', array(), COURTNEYR_CHILD_VERSION );
	if ( function_exists( 'wp_style_add_data' ) ) {
		wp_style_add_data( 'courtneyr-nav', 'path', COURTNEYR_CHILD_DIR . '/assets/css/cr-nav.css' );
	}
}

/** Load issue paint on every surface where issue markup can render. */
function enqueue_styles(): void {
	if ( ! is_admin() && ! is_issue_archive() && ! is_issue_single() && ! on_stream_surface() ) {
		return;
	}
	if ( ! defined( 'COURTNEYR_CHILD_URI' ) || ! defined( 'COURTNEYR_CHILD_VERSION' ) || ! function_exists( 'wp_enqueue_style' ) ) {
		return;
	}
	register_nav_dependency();
	wp_enqueue_style(
		STYLE_HANDLE,
		COURTNEYR_CHILD_URI . '/assets/css/cr-issue.css',
		array( 'courtneyr-paper', 'courtneyr-nav' ),
		COURTNEYR_CHILD_VERSION
	);
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_styles' );
