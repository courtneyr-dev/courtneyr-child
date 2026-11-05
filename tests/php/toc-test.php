<?php
/**
 * `php tests/php/toc-test.php`: checks the table of contents emoji test and
 * the hooks in inc/toc.php without loading WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/wp-content/themes/courtneyr-child' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );

	$GLOBALS['cr_hooks'] = array();

	/** Record a hook callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cr_hooks'][ $hook ][] = $callback;
		return true;
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook, $callback, $priority, $accepted_args );
	}

	require dirname( __DIR__, 2 ) . '/inc/toc.php';
}

namespace Courtneyr\Child\Toc\Tests {

	use function Courtneyr\Child\Toc\all_start_with_emoji;
	use function Courtneyr\Child\Toc\starts_with_emoji;

	$failures = 0;

	/** Report one check. */
	function check( bool $ok, string $label ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
		if ( ! $ok ) {
			++$failures;
		}
	}

	// The six 1.2 headings, as Yoast prints them.
	$headings = array(
		'🍺 What does free as in freedom mean, and where does the beer come in?',
		'📜 Why did the GNU Manifesto call its own wording careless?',
		'💸 Can free as in freedom software cost money?',
		'🧩 Is WordPress free as in freedom, free as in beer, or both?',
		'🏷️ How do free software and open source differ if the programs are the same?',
		'❓ Which free as in freedom questions can you ask about any tool?',
	);
	foreach ( $headings as $heading ) {
		check( starts_with_emoji( $heading ), 'emoji first: ' . mb_substr( $heading, 0, 12 ) );
	}
	check( all_start_with_emoji( $headings ), 'all six 1.2 headings open with an emoji' );

	check( starts_with_emoji( '  ✉️ Contact' ), 'leading space is ignored' );
	check( ! starts_with_emoji( 'Free as in freedom' ), 'a letter is not an emoji' );
	check( ! starts_with_emoji( '1. Price' ), 'a digit is not an emoji' );
	check( ! starts_with_emoji( '“Quoted” heading' ), 'a curly quote is not an emoji' );
	check( ! starts_with_emoji( '' ), 'empty text is not an emoji' );
	check( ! all_start_with_emoji( array() ), 'no entries keeps the bullets' );
	check( ! all_start_with_emoji( array( '🍺 One', 'Two' ) ), 'one plain heading keeps the bullets' );

	check( isset( $GLOBALS['cr_hooks']['render_block_yoast-seo/table-of-contents'] ), 'render filter registered' );
	check( isset( $GLOBALS['cr_hooks']['init'] ), 'style registered on init' );

	exit( $failures ? 1 : 0 );
}
