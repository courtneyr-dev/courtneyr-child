<?php
/**
 * Standalone checks for the contact form block.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['cr_contact_failures']        = array();
$GLOBALS['cr_contact_shortcode_exists'] = false;
$GLOBALS['cr_contact_can_edit']         = false;

function shortcode_exists( $tag ): bool {
	return 'cr_contact_form' === $tag && (bool) $GLOBALS['cr_contact_shortcode_exists'];
}

function do_shortcode( $content ): string {
	return '[cr_contact_form]' === $content ? '<form class="cr-contact__form"><input name="stub"></form>' : '';
}

function get_block_wrapper_attributes( $extra_attributes = array() ): string {
	$class = 'wp-block-courtneyr-contact-form';
	if ( ! empty( $extra_attributes['class'] ) ) {
		$class .= ' ' . $extra_attributes['class'];
	}
	return 'class="' . $class . '"';
}

function current_user_can( $capability ): bool {
	return 'edit_posts' === $capability && (bool) $GLOBALS['cr_contact_can_edit'];
}

function __( $text, $domain = null ): string {
	return (string) $text;
}

function esc_html__( $text, $domain = null ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function cr_contact_group( string $name, callable $checks ): void {
	$before = count( $GLOBALS['cr_contact_failures'] );
	$checks();
	$ok = $before === count( $GLOBALS['cr_contact_failures'] );
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
}

function cr_contact_assert( bool $ok, string $message ): void {
	if ( ! $ok ) {
		$GLOBALS['cr_contact_failures'][] = $message;
	}
}

function cr_contact_contains( string $needle, string $haystack, string $message ): void {
	cr_contact_assert( str_contains( $haystack, $needle ), $message . ': missing ' . $needle );
}

function cr_contact_not_contains( string $needle, string $haystack, string $message ): void {
	cr_contact_assert( ! str_contains( $haystack, $needle ), $message . ': found ' . $needle );
}

function cr_contact_render( bool $shortcode_exists, bool $can_edit ): string {
	$GLOBALS['cr_contact_shortcode_exists'] = $shortcode_exists;
	$GLOBALS['cr_contact_can_edit']         = $can_edit;
	ob_start();
	include dirname( __DIR__, 2 ) . '/blocks/contact-form/render.php';
	return (string) ob_get_clean();
}

cr_contact_group(
	'the available shortcode renders once inside the contact block wrapper',
	static function (): void {
		$output = cr_contact_render( true, false );
		cr_contact_assert( 1 === substr_count( $output, '<form class="cr-contact__form">' ), 'shortcode form renders once' );
		cr_contact_contains( 'class="wp-block-courtneyr-contact-form cr-contact-form"', $output, 'wrapper classes' );
	}
);

cr_contact_group(
	'an editor sees a status notice when the shortcode is unavailable',
	static function (): void {
		$output = cr_contact_render( false, true );
		cr_contact_contains( 'class="cr-contact__notice cr-contact__notice--err" role="status"', $output, 'editor status notice' );
		cr_contact_not_contains( '<form', $output, 'no unhandled form' );
	}
);

cr_contact_group(
	'a visitor sees no output when the shortcode is unavailable',
	static function (): void {
		cr_contact_assert( '' === cr_contact_render( false, false ), 'visitor output is empty' );
	}
);

cr_contact_group(
	'block metadata names the dynamic contact form renderer',
	static function (): void {
		$path = dirname( __DIR__, 2 ) . '/blocks/contact-form/block.json';
		$data = json_decode( (string) file_get_contents( $path ), true );
		cr_contact_assert( is_array( $data ), 'block.json parses' );
		cr_contact_assert( 'courtneyr/contact-form' === ( $data['name'] ?? null ), 'block name' );
		cr_contact_assert( 'file:./render.php' === ( $data['render'] ?? null ), 'render file' );
	}
);

cr_contact_group(
	'the theme adapter defines no shortcode or submission handler',
	static function (): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/contact-form.php' );
		cr_contact_not_contains( 'add_shortcode', $source, 'no shortcode registration' );
		cr_contact_not_contains( 'admin_post', $source, 'no admin-post hook' );
	}
);

if ( $GLOBALS['cr_contact_failures'] ) {
	foreach ( $GLOBALS['cr_contact_failures'] as $failure ) {
		fwrite( STDERR, '  ' . $failure . "\n" );
	}
	exit( 1 );
}
echo "all passed\n";
