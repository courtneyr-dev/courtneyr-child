<?php
/**
 * Standalone checks for podcast singles and lineup decorations.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

$podcast_style_dir = sys_get_temp_dir() . '/courtneyr-podcast-style-' . getmypid();
$podcast_asset_dir = $podcast_style_dir . '/assets/css';
if ( ! is_dir( $podcast_asset_dir ) ) {
	mkdir( $podcast_asset_dir, 0700, true );
}
file_put_contents( $podcast_asset_dir . '/cr-podcast.css', '/* test */' );

define( 'COURTNEYR_CHILD_DIR', $podcast_style_dir );
define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
define( 'COURTNEYR_CHILD_VERSION', 'test' );

$GLOBALS['cr_podcast_block_styles'] = array();

function wp_enqueue_block_style( $block_name, $args ): void {
	$GLOBALS['cr_podcast_block_styles'][] = array( $block_name, $args );
}

require __DIR__ . '/podcast-bootstrap.php';

use Courtneyr\Child\SinglePodcast as SinglePodcast;

/**
 * Complete public facts fixture.
 *
 * @param array<string, mixed> $overrides Fact overrides.
 * @return array<string, mixed>
 */
function cr_podcast_single_facts( array $overrides = array() ): array {
	return array_replace_recursive(
		array(
			'is_podcast'        => true,
			'name'              => 'Listened to Harbor Signals',
			'name_is_title'     => true,
			'episode'           => 'Harbor Signals',
			'episode_text'      => 'Harbor Signals',
			'episode_differs'   => true,
			'show'              => 'Alder Street Radio',
			'url'               => 'https://open.spotify.com/episode/123',
			'rating'            => 4.0,
			'rating_label'      => 'Rated 4 of 5',
			'listened_value'    => '2026-09-26T23:30:00-05:00',
			'listened_has_time' => true,
			'listened_stored'   => true,
			'day_iso'           => '2026-09-26',
			'day_display'       => 'September 26, 2026',
			'time_display'      => '11:30 PM',
			'picture'           => array(
				'source'            => 'remote',
				'attachment_id'     => 0,
				'url'               => 'https://img.example.test/cover.jpg',
				'suppress_featured' => false,
			),
			'cover_alt'         => 'Harbor Signals cover',
			'embed_html'        => '',
			'kind_label'        => 'Listen',
		),
		$overrides
	);
}

$post = cr_podcast_reset( 301 );
$facts = cr_podcast_single_facts();
$GLOBALS['cr_podcast_facts'][ $post->ID ] = $facts;
$html = SinglePodcast\record_html( $post, $facts );

$show_at    = strpos( $html, '<dt>Show</dt>' );
$episode_at = strpos( $html, '<dt>Episode</dt>' );
$tuned_at   = strpos( $html, '<dt>Tuned in</dt>' );
$rating_at  = strpos( $html, '>Rating</dt>' );
cr_podcast_check( false !== $show_at && $show_at < $episode_at && $episode_at < $tuned_at && $tuned_at < $rating_at, 'record rows print Show, Episode, Tuned in and Rating in order' );
cr_podcast_contains( '<time datetime="2026-09-26T23:30:00-05:00">September 26, 2026</time> at 11:30 PM', $html, 'timestamp keeps the stored datetime and plugin display values' );
cr_podcast_contains( '<dd>Rated 4 of 5</dd>', $html, 'rating dd text is exact' );
cr_podcast_check( strpos( $html, 'cr-podcast-single__record' ) < strpos( $html, 'cr-podcast-tuner' ) && strpos( $html, 'cr-podcast-tuner' ) < strpos( $html, 'cr-podcast-single__media' ), 'section child order is record, tuner, media' );

$without_episode = SinglePodcast\record_html( $post, cr_podcast_single_facts( array( 'episode_differs' => false ) ) );
cr_podcast_not_contains( '<dt>Episode</dt>', $without_episode, 'episode row is absent when the episode does not differ' );
$without_tuned = SinglePodcast\record_html( $post, cr_podcast_single_facts( array( 'listened_stored' => false ) ) );
cr_podcast_not_contains( '<dt>Tuned in</dt>', $without_tuned, 'Tuned in is absent without a stored value' );
$date_only = SinglePodcast\record_html(
	$post,
	cr_podcast_single_facts(
		array(
			'listened_value'    => '2026-09-26',
			'listened_has_time' => false,
			'time_display'      => '',
		)
	)
);
cr_podcast_contains( '<time datetime="2026-09-26">September 26, 2026</time>', $date_only, 'date-only value remains date-only' );
cr_podcast_not_contains( ' at ', $date_only, 'date-only value prints no time phrase' );
$zero_rating = SinglePodcast\record_html( $post, cr_podcast_single_facts( array( 'rating' => 0.0, 'rating_label' => '' ) ) );
cr_podcast_not_contains( '>Rating</dt>', $zero_rating, 'rating row is absent at zero' );

$embed = SinglePodcast\record_html(
	$post,
	cr_podcast_single_facts( array( 'embed_html' => '<iframe src="https://player.example.test/e"></iframe>' ) )
);
cr_podcast_contains( '<div class="cr-podcast-embed"><iframe', $embed, 'provider embed prints verbatim in its wrapper' );
cr_podcast_not_contains( 'cr-podcast-picture', $embed, 'picture is absent when an embed prints' );
cr_podcast_check( 0 === substr_count( $embed, '<img' ), 'embed path prints no image' );

$no_media = SinglePodcast\record_html(
	$post,
	cr_podcast_single_facts(
		array(
			'url'     => '',
			'picture' => array( 'url' => '' ),
		)
	)
);
cr_podcast_not_contains( 'cr-podcast-single__media', $no_media, 'no media wrapper prints without a picture, embed or URL' );
cr_podcast_contains( 'cr-podcast-tuner', $no_media, 'record and tuner still print without media' );

$picture = SinglePodcast\record_html( $post, $facts );
cr_podcast_contains( '<figure class="cr-podcast-picture" data-cr-cover-fallback="true">', $picture, 'picture wrapper carries the failed-cover marker' );
cr_podcast_check( 1 === substr_count( $picture, '<img' ) && str_contains( $picture, 'class="u-photo"' ), 'picture path prints one u-photo image' );
cr_podcast_not_contains( 'cr-podcast-embed', $picture, 'picture path prints no embed' );

cr_podcast_contains( '<p class="cr-listen-sources__label">Listen / find it</p>', $html, 'sources row prints with a stored URL' );
cr_podcast_contains( '>Spotify<span class="cr-podcast-sr"> (opens in a new tab)</span>', $html, 'Spotify URL uses its provider label and new-tab hint' );
cr_podcast_contains( 'target="_blank" rel="noopener noreferrer"', $html, 'source link has the required new-tab protections' );
cr_podcast_not_contains( 'Rating group', $html, 'sources row adds no Rating group' );
$unknown = SinglePodcast\record_html( $post, cr_podcast_single_facts( array( 'url' => 'https://unknown.example.test/episode' ) ) );
cr_podcast_contains( '>Listen<span class="cr-podcast-sr"> (opens in a new tab)</span>', $unknown, 'unknown source URL falls back to Listen' );
$no_source = SinglePodcast\record_html( $post, cr_podcast_single_facts( array( 'url' => '' ) ) );
cr_podcast_not_contains( 'cr-podcast-sources', $no_source, 'sources row is absent without a URL' );

$input = '<p>Listened to "X" from Y.</p>';
$locked = cr_podcast_reset( 302 );
$GLOBALS['cr_podcast_facts'][ $locked->ID ] = array();
cr_podcast_same( $input, SinglePodcast\prepend_record( $input ), 'locked post facts leave content byte-identical' );
$nonpodcast = cr_podcast_reset( 303 );
$GLOBALS['cr_podcast_facts'][ $nonpodcast->ID ] = array( 'name' => 'Music listen' );
cr_podcast_same( $input, SinglePodcast\prepend_record( $input ), 'facts without is_podcast leave content byte-identical' );

$conditions = array(
	'outside the loop'       => array( 'cr_podcast_loop', false ),
	'outside the main query' => array( 'cr_podcast_main', false ),
	'in a feed'              => array( 'cr_podcast_feed', true ),
);
$condition_id = 310;
foreach ( $conditions as $label => $state ) {
	$post = cr_podcast_reset( $condition_id++ );
	$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts();
	$GLOBALS[ $state[0] ] = $state[1];
	cr_podcast_same( $input, SinglePodcast\prepend_record( $input ), $label . ' leaves content byte-identical' );
}
$post = cr_podcast_reset( 320 );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts();
$GLOBALS['cr_podcast_queried'] = 999;
cr_podcast_same( $input, SinglePodcast\prepend_record( $input ), 'a different loop post leaves content byte-identical' );

$post = cr_podcast_reset( 321 );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts();
$once = SinglePodcast\prepend_record( $input );
$twice = SinglePodcast\prepend_record( $input );
cr_podcast_check( 1 === substr_count( $once, '<section class="cr-podcast-single">' ) && $input === $twice, 'record prints once for a post in one request' );
cr_podcast_check( str_ends_with( $once, $input ) && 1 === substr_count( $once, $input ), 'Q07-shaped authored paragraph remains untouched after the section' );

$post = cr_podcast_reset( 330 );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts( array( 'picture' => array( 'suppress_featured' => true ) ) );
$featured_block = array( 'blockName' => 'core/post-featured-image' );
cr_podcast_same( '', SinglePodcast\drop_repeated_featured_image( '<figure>Featured</figure>', $featured_block ), 'repeated featured image is dropped for a podcast' );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts( array( 'picture' => array( 'suppress_featured' => false ) ) );
cr_podcast_same( '<figure>Featured</figure>', SinglePodcast\drop_repeated_featured_image( '<figure>Featured</figure>', $featured_block ), 'featured image remains when suppression is false' );

$kind_block = array( 'blockName' => 'core/post-terms', 'attrs' => array( 'className' => 'single-post__kind extra' ) );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts();
cr_podcast_same( '', SinglePodcast\drop_kind_chip( '<div>Listen</div>', $kind_block ), 'podcast single kind chip is dropped' );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = array( 'is_podcast' => false );
cr_podcast_same( '<div>Listen</div>', SinglePodcast\drop_kind_chip( '<div>Listen</div>', $kind_block ), 'music single kind chip remains' );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts();
$other_terms = array( 'blockName' => 'core/post-terms', 'attrs' => array( 'className' => 'single-post__category' ) );
cr_podcast_same( '<div>Category</div>', SinglePodcast\drop_kind_chip( '<div>Category</div>', $other_terms ), 'another post-terms block remains' );

// The authored listen card prints its own kind label; the record's is the one that stays.
$card_block = array( 'blockName' => 'post-kinds-indieweb/listen-card', 'attrs' => array() );
$card_html  = '<article class="pk-card k-listen"><span class="pk-kindlabel">Listen</span><h2 class="pk-title">Card</h2></article>';
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts();
$stripped = SinglePodcast\strip_card_kind_label( $card_html, $card_block );
cr_podcast_not_contains( 'pk-kindlabel', $stripped, 'authored card loses its kind label on a podcast single' );
cr_podcast_contains( '<h2 class="pk-title">Card</h2>', $stripped, 'authored card keeps its title' );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = array( 'is_podcast' => false );
cr_podcast_same( $card_html, SinglePodcast\strip_card_kind_label( $card_html, $card_block ), 'music card keeps its kind label' );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts();
cr_podcast_same( $card_html, SinglePodcast\strip_card_kind_label( $card_html, array( 'blockName' => 'post-kinds-indieweb/watch-card', 'attrs' => array() ) ), 'other cards are untouched' );

// An untitled podcast single gets one plain H1 from the facts name; titled posts and music are untouched.
$untitled = cr_podcast_reset( 340 );
$untitled->post_title = '';
$GLOBALS['cr_podcast_facts'][ $untitled->ID ] = cr_podcast_single_facts( array( 'name' => 'Listen, September 18, 2026', 'name_is_title' => false ) );
$title_block = array( 'blockName' => 'core/post-title', 'attrs' => array( 'level' => 1, 'className' => 'cr-flank single-post__title' ) );
$h1 = SinglePodcast\untitled_h1( '', $title_block );
cr_podcast_contains( '>Listen, September 18, 2026</h1>', $h1, 'untitled podcast single prints the facts name as its H1' );
cr_podcast_not_contains( 'p-name', $h1, 'untitled podcast H1 carries no p-name' );
cr_podcast_not_contains( '<a', $h1, 'untitled podcast H1 is not a link' );
cr_podcast_same( '<h1>Kept</h1>', SinglePodcast\untitled_h1( '<h1>Kept</h1>', $title_block ), 'a rendered title is left alone' );
cr_podcast_same( '', SinglePodcast\untitled_h1( '', array( 'blockName' => 'core/post-title', 'attrs' => array( 'level' => 2 ) ) ), 'only the level 1 title is filled' );
$GLOBALS['cr_podcast_facts'][ $untitled->ID ] = array( 'is_podcast' => false );
cr_podcast_same( '', SinglePodcast\untitled_h1( '', $title_block ), 'an untitled music single gets no podcast H1' );

$classes = SinglePodcast\body_class( array( 'cr-single--podcast' ) );
cr_podcast_same( 1, count( array_keys( $classes, 'cr-single--podcast', true ) ), 'podcast body class is added once' );

$lineup = '<div class="wp-block-post-template pkiw-lineup"><section class="pkiw-lineup__station"><div class="pkiw-lineup__cover" data-pkiw-cover><img></div><div class="pkiw-lineup__cover pkiw-lineup__cover--none" data-pkiw-cover aria-hidden="true"></div></section></div>';
$marked = SinglePodcast\mark_lineup( $lineup );
cr_podcast_same( 2, substr_count( $marked, 'data-cr-cover-fallback="true"' ), 'all data-pkiw-cover divs receive the fallback marker' );
$unrelated = '<div class="other">Unrelated</div>';
cr_podcast_same( $unrelated, SinglePodcast\mark_lineup( $unrelated ), 'unrelated HTML is byte-identical after mark_lineup' );

$dialed = SinglePodcast\add_dial( $lineup );
cr_podcast_contains( '<div class="wp-block-post-template pkiw-lineup"><span class="cr-podcast-dial"', $dialed, 'dial is the chassis first child' );
cr_podcast_same( 1, substr_count( SinglePodcast\add_dial( $dialed ), 'class="cr-podcast-dial"' ), 'dial inserts once' );
cr_podcast_same( $unrelated, SinglePodcast\add_dial( $unrelated ), 'non-lineup Post Template HTML is byte-identical' );
cr_podcast_check( ! str_contains( $html . $marked . $dialed, 'style=' ) && ! str_contains( strtolower( $html . $marked . $dialed ), 'rotation' ), 'generated markup has no inline style or rotation' );

$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/single-podcast.php' );
$class_tokens = array_filter( token_get_all( $source ), static fn( $token ): bool => is_array( $token ) && T_CLASS === $token[0] );
cr_podcast_check( array() === $class_tokens, 'single adapter declares no class' );
foreach ( array( 'get_post_meta', 'PKIW\\Listen', 'Kind_Facts' ) as $banned ) {
	cr_podcast_check( ! str_contains( $source, $banned ), 'single adapter omits ' . $banned );
}
cr_podcast_contains( '\\PKIW\\kind_facts', $source, 'single adapter calls the plugin through kind_facts only' );

SinglePodcast\register_type_nav_style();
cr_podcast_same( 1, count( $GLOBALS['cr_podcast_block_styles'] ), 'readable podcast sheet registers one block style' );
$type_nav_style = $GLOBALS['cr_podcast_block_styles'][0] ?? array();
cr_podcast_same( 'post-kinds-indieweb/listen-types', $type_nav_style[0] ?? '', 'type nav style targets the listen-types block' );
cr_podcast_same( 'courtneyr-podcast', $type_nav_style[1]['handle'] ?? '', 'type nav style reuses the podcast handle' );
cr_podcast_same( COURTNEYR_CHILD_URI . '/assets/css/cr-podcast.css', $type_nav_style[1]['src'] ?? '', 'type nav style uses the podcast stylesheet URL' );
cr_podcast_same( COURTNEYR_CHILD_DIR . '/assets/css/cr-podcast.css', $type_nav_style[1]['path'] ?? '', 'type nav style passes the readable filesystem path' );
cr_podcast_same( COURTNEYR_CHILD_VERSION, $type_nav_style[1]['ver'] ?? '', 'type nav style uses the theme version' );

unlink( COURTNEYR_CHILD_DIR . '/assets/css/cr-podcast.css' );
SinglePodcast\register_type_nav_style();
cr_podcast_same( 1, count( $GLOBALS['cr_podcast_block_styles'] ), 'unreadable podcast sheet registers no additional block style' );
rmdir( $podcast_asset_dir );
rmdir( $podcast_style_dir . '/assets' );
rmdir( COURTNEYR_CHILD_DIR );

$post = cr_podcast_reset( 340 );
$GLOBALS['cr_podcast_facts'][ $post->ID ] = cr_podcast_single_facts();
define( 'REST_REQUEST', true );
cr_podcast_same( $input, SinglePodcast\prepend_record( $input ), 'REST request leaves content byte-identical' );

cr_podcast_finish();
