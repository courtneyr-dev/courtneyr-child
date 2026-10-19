<?php
/**
 * Audio single player and transcript layout (PKIW issue 243).
 *
 * The plugin marks the primary rendered player and reports public facts. This
 * file lifts that marked output and the first filled transcript into the audio
 * single object before the plugin wraps the post body in e-content.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SingleAudio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KIND          = 'audio';
const WRAPPER_CLASS = 'cr-audio-single';

/** Whether this is the queried audio post's plain main-loop content pass. */
function is_audio_single(): bool {
	$id = (int) get_queried_object_id();

	return is_singular( 'post' )
		&& is_main_query()
		&& in_the_loop()
		&& ! is_feed()
		// Core's automatic excerpt runs the_content with do_blocks removed, so nothing lifts and an empty machine would leak its counter into the excerpt.
		&& ! doing_filter( 'get_the_excerpt' )
		&& ! doing_filter( 'wp_trim_excerpt' )
		&& ( ! function_exists( 'wp_is_rest_endpoint' ) || ! wp_is_rest_endpoint() )
		&& 0 < $id
		&& (int) get_the_ID() === $id
		&& has_term( KIND, 'kind', $id )
		&& ! post_password_required( $id )
		&& null === get_query_var( 'atproto', null )
		&& empty( get_query_var( 'markdown_request' ) )
		&& 'markdown' !== get_query_var( 'format' );
}

/**
 * Public audio facts for a post.
 *
 * @param int $id Post ID.
 * @return array<string, mixed>
 */
function facts( int $id ): array {
	return function_exists( '\\PKIW\\kind_facts' ) ? (array) \PKIW\kind_facts( $id ) : array();
}

/**
 * Per-content-pass lifted output for one post.
 *
 * @param int $id Post ID.
 * @return array{facts: array<string, mixed>|null, player: string, transcript: string, player_lifted: bool, transcript_lifted: bool}
 */
function &state( int $id ): array {
	static $states = array();

	if ( ! isset( $states[ $id ] ) ) {
		$states[ $id ] = array(
			'facts'             => null,
			'player'            => '',
			'transcript'        => '',
			'player_lifted'     => false,
			'transcript_lifted' => false,
		);
	}

	return $states[ $id ];
}

/**
 * Public audio facts for one content pass.
 *
 * The plugin reader parses the post's blocks on every call, and this adapter
 * asks once per paragraph, group and media block, so the first answer is kept
 * until begin() starts the next pass.
 *
 * @param int $id Post ID.
 * @return array<string, mixed>
 */
function pass_facts( int $id ): array {
	$current =& state( $id );
	if ( null === $current['facts'] ) {
		$current['facts'] = facts( $id );
	}

	return $current['facts'];
}

/**
 * Reset lifted output at the start of the queried content pass.
 *
 * @param string $content Stored content.
 * @return string
 */
function begin( string $content ): string {
	if ( is_audio_single() ) {
		$id      = (int) get_queried_object_id();
		$current =& state( $id );
		$current = array(
			'facts'             => null,
			'player'            => '',
			'transcript'        => '',
			'player_lifted'     => false,
			'transcript_lifted' => false,
		);
	}

	return $content;
}
add_filter( 'the_content', __NAMESPACE__ . '\\begin', 8 );

/**
 * Able Player markup for an attachment when the shared helper can build it.
 *
 * @param array<string, mixed> $audio_facts Public audio facts.
 * @return string
 */
function able_player( array $audio_facts ): string {
	$attachment_id = (int) ( $audio_facts['audio_attachment_id'] ?? 0 );
	if ( 0 >= $attachment_id || ! function_exists( '\\Courtneyr\\Child\\PostKinds\\ableplayer_audio' ) ) {
		return '';
	}

	return (string) \Courtneyr\Child\PostKinds\ableplayer_audio( $attachment_id );
}

/**
 * Build a native player from a rendered Micropub audio link.
 *
 * @param string $html Rendered paragraph.
 * @return string
 */
function paragraph_player( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'A' ) ) {
		$href = $tags->get_attribute( 'href' );
		if ( ! $tags->has_class( 'u-audio' ) || ! is_string( $href ) ) {
			continue;
		}
		$url = esc_url( $href );
		if ( '' === $url || 1 !== preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		return '<figure class="wp-block-audio cr-audio-single__native"><audio controls preload="metadata" src="' . $url . '"></audio></figure>';
	}

	return '';
}

/**
 * Lift the plugin-marked primary player into the single object.
 *
 * @param string               $html  Rendered media block.
 * @param array<string, mixed> $block Parsed block.
 * @return string
 */
function lift_player( string $html, array $block ): string {
	if ( '' === $html || ! is_audio_single() ) {
		return $html;
	}

	$id          = (int) get_queried_object_id();
	$audio_facts = pass_facts( $id );
	$current     =& state( $id );
	$marker      = (string) ( $audio_facts['primary_marker'] ?? '' );
	$block_name  = (string) ( $block['blockName'] ?? '' );
	if (
		array() === $audio_facts
		|| '' === $marker
		|| $current['player_lifted']
		|| (string) ( $audio_facts['player_block'] ?? '' ) !== $block_name
	) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag() || null === $tags->get_attribute( $marker ) ) {
		return $html;
	}

	$player = able_player( $audio_facts );
	if ( 'core/paragraph' === $block_name ) {
		$current['player']        = '' !== $player ? $player : paragraph_player( $html );
		$current['player_lifted'] = true;
		return $html;
	}

	$current['player']        = '' !== $player ? $player : $html;
	$current['player_lifted'] = true;

	return '';
}
add_filter( 'render_block_core/audio', __NAMESPACE__ . '\\lift_player', 250, 2 );
add_filter( 'render_block_core/embed', __NAMESPACE__ . '\\lift_player', 250, 2 );
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\lift_player', 250, 2 );

/**
 * Lift the first filled transcript group into the single object.
 *
 * @param string               $html  Rendered group.
 * @param array<string, mixed> $block Parsed group, unused.
 * @return string
 */
function lift_transcript( string $html, array $block ): string {
	unset( $block );

	if ( '' === $html || ! is_audio_single() ) {
		return $html;
	}

	$id          = (int) get_queried_object_id();
	$audio_facts = pass_facts( $id );
	$current     =& state( $id );
	if ( array() === $audio_facts || empty( $audio_facts['has_transcript'] ) || $current['transcript_lifted'] ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag() || 'DIV' !== $tags->get_tag() || ! $tags->has_class( 'pkiw-transcript' ) ) {
		return $html;
	}

	$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = (string) preg_replace( '/[\s\x{00A0}]+/u', '', $text );
	if ( '' === $text ) {
		return $html;
	}

	$current['transcript']        = mark_timestamps( $html );
	$current['transcript_lifted'] = true;

	return '';
}
add_filter( 'render_block_core/group', __NAMESPACE__ . '\\lift_transcript', 250, 2 );

/**
 * Wrap an author-typed [m:ss] or [h:mm:ss] at the start of a transcript paragraph in a <time>.
 *
 * Render time only, inside the lifted sheet; the stored transcript is untouched.
 *
 * @param string $html Lifted transcript markup.
 * @return string
 */
function mark_timestamps( string $html ): string {
	return (string) preg_replace_callback(
		'/(<p\b[^>]*>)\[(?:(\d{1,2}):)?(\d{1,2}):([0-5]\d)\]/',
		static function ( array $m ): string {
			$parts = array(
				'H' => (int) $m[2],
				'M' => (int) $m[3],
				'S' => (int) $m[4],
			);
			$iso   = 'PT';
			foreach ( $parts as $unit => $value ) {
				if ( $value > 0 ) {
					$iso .= $value . $unit;
				}
			}
			if ( 'PT' === $iso ) {
				$iso = 'PT0S';
			}
			$label = '' === $m[2] ? $m[3] . ':' . $m[4] : $m[2] . ':' . $m[3] . ':' . $m[4];

			return $m[1] . '<time class="cr-audio-sheet__stamp" datetime="' . esc_attr( $iso ) . '">[' . esc_html( $label ) . ']</time>';
		},
		$html
	);
}

/**
 * Render the audio machine around an optional player.
 *
 * @param array<string, mixed> $audio_facts Public audio facts.
 * @param string               $player      Lifted player markup.
 * @return string
 */
function machine_html( array $audio_facts, string $player ): string {
	$count = max( 0, min( 99, (int) ( $audio_facts['player_count'] ?? 0 ) ) );
	$html  = '<div class="cr-audio-machine"><span class="cr-audio-machine__counter" aria-hidden="true">' . esc_html( str_pad( (string) $count, 2, '0', STR_PAD_LEFT ) ) . '</span><span class="cr-audio-machine__window" aria-hidden="true"></span>';
	if ( '' !== $player ) {
		$html .= '<div class="cr-audio-machine__player">' . $player . '</div>';
	}

	return $html . '</div>';
}

/**
 * Render an open transcript sheet when a transcript was lifted.
 *
 * @param string $transcript Lifted transcript markup.
 * @return string
 */
function sheet_html( string $transcript ): string {
	if ( '' === $transcript ) {
		return '';
	}

	return '<section class="cr-audio-sheet" aria-labelledby="cr-audio-sheet-title"><h2 id="cr-audio-sheet-title" class="cr-audio-sheet__title">' . esc_html( __( 'Transcript', 'courtneyr-child' ) ) . '</h2>' . $transcript . '</section>';
}

/**
 * Insert the audio object before the stored post body.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function insert( string $content ): string {
	if ( ! is_audio_single() || str_contains( $content, WRAPPER_CLASS ) ) {
		return $content;
	}

	$id          = (int) get_queried_object_id();
	$audio_facts = pass_facts( $id );
	if ( array() === $audio_facts ) {
		return $content;
	}

	$current =& state( $id );

	return '<div class="' . esc_attr( WRAPPER_CLASS ) . '">' . machine_html( $audio_facts, $current['player'] ) . sheet_html( $current['transcript'] ) . '</div>' . $content;
}
add_filter( 'the_content', __NAMESPACE__ . '\\insert', 97 );

/**
 * Mark audio singles for their stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function body_class( array $classes ): array {
	if ( is_singular( 'post' ) && has_term( KIND, 'kind', get_queried_object_id() ) && ! in_array( 'cr-single--audio', $classes, true ) ) {
		$classes[] = 'cr-single--audio';
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Let the shared featured-image filter suppress repeated audio covers.
 *
 * @param mixed $kinds Kind slugs registered so far.
 * @return mixed
 */
function repeat_kinds( $kinds ) {
	if ( ! is_array( $kinds ) ) {
		return $kinds;
	}
	if ( ! in_array( KIND, $kinds, true ) ) {
		$kinds[] = KIND;
	}

	return $kinds;
}
add_filter( 'courtneyr_child_featured_repeat_kinds', __NAMESPACE__ . '\\repeat_kinds' );
