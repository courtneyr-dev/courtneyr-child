<?php
/**
 * Local Studio only. Fixtures for the second verification round of the Favorite and Repost wide column:
 * synced patterns, authored HTML that names a card's class, and autosave previews. Run after
 * seed-kinds.php (it reuses that file's helpers by including it). Every post and pattern carries
 * `_cr_seed_wck2`; remove by that key.
 *
 *   studio wp eval-file seed-round2.php          (seed; prints label, ID, URL)
 *   studio wp eval-file seed-round2.php remove   (remove)
 */
if ( 'local' !== wp_get_environment_type() ) {
	echo "Refusing: not local.\n";
	return;
}
$key2 = '_cr_seed_wck2';

$admin2 = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
wp_set_current_user( $admin2 );

if ( isset( $args[0] ) && 'remove' === $args[0] ) {
	$ids = get_posts( array( 'post_type' => array( 'post', 'wp_block' ), 'post_status' => 'any', 'meta_key' => $key2, 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	echo 'removed ' . count( $ids ) . " posts and patterns\n";
	return;
}

// Reuse seed-kinds.php's helpers without seeding its posts again: they exist already and it only adds what is missing.
ob_start();
require __DIR__ . '/seed-kinds.php';
ob_end_clean();

$r2    = array();
$p     = static function ( string $text ): string {
	return "<!-- wp:paragraph -->\n<p>" . $text . "</p>\n<!-- /wp:paragraph -->";
};
$html  = static function ( string $markup ): string {
	return "<!-- wp:html -->\n" . $markup . "\n<!-- /wp:html -->";
};
$grp   = static function ( string $inner ): string {
	return "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">" . $inner . "</div>\n<!-- /wp:group -->";
};
$img2  = home_url( '/wp-content/uploads/2026/11/event.jpg' );
$card2 = static function ( string $kind, array $more = array() ) use ( $img2 ): string {
	return rst_card(
		$kind . '-card',
		array_merge(
			array(
				'title'                                     => 'Alt text decision tree',
				'url'                                       => 'https://example.org/alt-text',
				'author'                                    => 'Example Author',
				'description'                               => 'A practical flow for writing useful alternative text.',
				'image'                                     => $img2,
				'imageAlt'                                  => 'A flowchart of yes and no questions',
				( 'favorite' === $kind ? 'favoritedAt' : 'repostedAt' ) => '2026-09-14',
			),
			$more
		)
	);
};
$pattern = static function ( string $label, string $content ) use ( $key2 ): int {
	$have = get_posts( array( 'post_type' => 'wp_block', 'post_status' => 'any', 'meta_key' => $key2, 'meta_value' => $label, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $have ) {
		return (int) $have[0];
	}
	return (int) wp_insert_post( wp_slash( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => 'R2 ' . $label, 'post_content' => $content, 'meta_input' => array( $key2 => $label ) ) ) );
};
$post2 = static function ( string $label, string $kind, string $title, string $content, array $meta = array() ) use ( $key2, $admin2, &$r2 ): int {
	$have = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => $key2, 'meta_value' => $label, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $have ) {
		$r2[ $label ] = (int) $have[0];
		return (int) $have[0];
	}
	$date = gmdate( 'Y-m-d H:i:s', strtotime( '2026-04-30 18:00:00 UTC' ) - count( $r2 ) * 86400 );
	$id   = (int) wp_insert_post( wp_slash( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $admin2, 'post_title' => $title, 'post_name' => sanitize_title( $label ), 'post_date' => $date, 'post_date_gmt' => $date, 'post_content' => $content, 'comment_status' => 'closed', 'ping_status' => 'closed', 'meta_input' => array( $key2 => $label ) ) ), true );
	wp_set_object_terms( $id, array( rst_term( $kind ) ), 'kind' );
	wp_update_post( array( 'ID' => $id ) );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	$r2[ $label ] = $id;
	return $id;
};

$meta_facts = array(
	'_pkiw_cite_url'  => 'https://example.org/meta-only',
	'_pkiw_cite_name' => 'A page cited from the meta box',
);
$decoys     = array(
	'code'    => '<article id="review-authored-article"><div><code>%s</code></div></article>',
	'attr'    => '<div class="authored-note" title="%1$s"><a href="#%1$s">An authored link that names the class</a></div>',
	'comment' => '<!-- <article class="%s"></article> --><p class="authored-note">After an authored comment.</p>',
	'pre'     => '<pre class="wp-block-code"><code>&lt;article class="%s"&gt;&lt;/article&gt;</code></pre>',
	'data'    => '<div class="authored-note" data-card="%s"><p>An authored data attribute names the class.</p></div>',
	'class'   => '<article class="%s authored-note"><p>An authored article that carries the card class itself.</p></article>',
);

foreach ( array( 'favorite' => 'k-favorite', 'repost' => 'cr-repost-clip--single' ) as $kind => $class ) {
	$k = substr( $kind, 0, 3 );
	// B1 / B4: a card inside a synced pattern's Group; a synced pattern that is only the card; a synced pattern after a top-level card.
	$in_group = $pattern( "$k-synced-group", $grp( $p( 'Before, inside the synced group.' ) . $card2( $kind ) . $p( 'After, inside the synced group.' ) ) );
	$bare     = $pattern( "$k-synced-bare", $card2( $kind ) );
	$prose    = $pattern( "$k-synced-prose", $p( 'A synced paragraph after the card.' ) );
	// The Repost posts also carry meta-box facts, so a walker that saw the reference as a leaf took the meta-only path (the review's fixture).
	$facts = 'repost' === $kind ? $meta_facts : array();
	$post2( "r2-$k-synced", $kind, 'A card inside a synced pattern', '<!-- wp:block {"ref":' . $in_group . '} /-->', $facts );
	$post2( "r2-$k-synced-bare", $kind, 'A synced pattern that is only the card', '<!-- wp:block {"ref":' . $bare . '} /-->', $facts );
	$post2( "r2-$k-synced-after", $kind, 'A card, then a synced pattern', $card2( $kind ) . "\n" . '<!-- wp:block {"ref":' . $prose . '} /-->' );
	// B2: authored HTML that names the card's class, before a real card.
	foreach ( $decoys as $name => $markup ) {
		$post2( "r2-$k-decoy-$name", $kind, "Authored $name naming the card class", $p( 'Before the authored block.' ) . "\n" . $html( sprintf( $markup, $class ) ) . "\n" . $card2( $kind ) . "\n" . $p( 'After the card.' ) );
	}
}

// B3: autosave previews. The saved post and its autosave each print what the decision says.
$sentinel = 'R2 AUTOSAVE SENTINEL';
$as       = array(
	'r2-rep-as-remove'  => array( $p( 'A note beside the card.' ) . "\n" . $card2( 'repost' ), $p( "$sentinel: the card was removed in the editor." ), array() ),
	'r2-rep-as-add'     => array( $p( 'Only a note is saved; the source is in the meta box.' ), $p( "$sentinel: a card was added in the editor." ) . "\n" . $card2( 'repost', array( 'title' => 'An added card' ) ), $meta_facts ),
	'r2-rep-as-meta'    => array( $p( 'A meta-only repost.' ), $p( "$sentinel: the note was edited." ), $meta_facts ),
	'r2-rep-as-classic' => array( '<p>A Classic repost that quotes <code>k-repost</code> in its note.</p>', "<p>$sentinel: a Classic note that quotes <code>k-repost</code>.</p>", $meta_facts ),
);
// The no-decision path of the meta-only renderer (unknown structure), and a decision it must follow.
$prose_ref = '<!-- wp:block {"ref":' . $pattern( 'rep-synced-prose', $p( 'A synced paragraph after the card.' ) ) . '} /-->';
$as['r2-rep-as-synced'] = array( $p( 'A note beside the card.' ) . "\n" . $card2( 'repost' ), $p( "$sentinel: the card was removed and a synced pattern added." ) . "\n" . $prose_ref, $meta_facts );
$post2( 'r2-rep-meta-decoy', 'repost', 'Meta-only, with an authored element that carries the card class', $p( 'A meta-only repost.' ) . "\n" . $html( '<article class="pk-card k-repost authored-note"><p>An authored element with the card class.</p></article>' ), $meta_facts );
$post2( 'r2-rep-classic-synced', 'repost', 'Classic note quoting k-repost, then a synced pattern', '<p>A Classic note that quotes <code>k-repost</code>.</p>' . "\n" . $prose_ref, $meta_facts );

foreach ( $as as $label => list( $saved, $autosave, $meta ) ) {
	$id = $post2( $label, 'repost', ucfirst( str_replace( array( 'r2-rep-as-', '-' ), array( 'Autosave ', ' ' ), $label ) ), $saved, $meta );
	if ( ! wp_get_post_autosave( $id, $admin2 ) ) {
		wp_create_post_autosave( array( 'post_ID' => $id, 'post_title' => get_the_title( $id ), 'post_content' => $autosave, 'post_excerpt' => '' ) );
	}
}

foreach ( $r2 as $label => $id ) {
	printf( "%s\t%d\t%s\n", $label, $id, get_permalink( $id ) );
}
