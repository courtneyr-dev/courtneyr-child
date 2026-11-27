<?php
/**
 * `php tests/php/form-fallback-test.php`: checks which form fallback sheets load
 * for each Global Styles engine, and the Ollie field-rule rewrite, without
 * WordPress.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'CR_THEME_DIR', dirname( __DIR__, 2 ) );

	function add_action() {}
	function add_filter() {}

	/** WordPress 7.1: no label element, no textInput states. */
	class Engine_Core_71 {
		const ELEMENTS                        = array( 'textInput' => 'textarea' );
		const VALID_ELEMENT_PSEUDO_SELECTORS = array( 'link' => array( ':hover' ) );
	}

	/** Gutenberg 24.2 on 7.1: the label element, no textInput states. */
	class Engine_Gutenberg_242 {
		const ELEMENTS                        = array( 'label' => 'label', 'textInput' => 'textarea' );
		const VALID_ELEMENT_PSEUDO_SELECTORS = array( 'link' => array( ':hover' ) );
	}

	/** WordPress 7.2: both. */
	class Engine_Core_72 {
		const ELEMENTS                        = array( 'label' => 'label', 'textInput' => 'textarea' );
		const VALID_ELEMENT_PSEUDO_SELECTORS = array( 'textInput' => array( ':required', ':user-valid', ':user-invalid', ':focus', ':focus-visible', '::placeholder' ) );
	}

	require CR_THEME_DIR . '/inc/enqueue.php';
}

namespace Courtneyr\Child\FormFallbackTests {

	use function Courtneyr\Child\Enqueue\missing_form_elements;
	use function Courtneyr\Child\Enqueue\scope_ollie_field_rules;
	use const Courtneyr\Child\Enqueue\OLLIE_FIELD_SCOPE;

	$failures = array();

	/** Print one result and remember failures. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	check( array( 'forms-label-fallback', 'forms-fallback' ) === missing_form_elements( 'Engine_Core_71' ), '7.1 core loads the label and the states fallback' );
	check( array( 'forms-fallback' ) === missing_form_elements( 'Engine_Gutenberg_242' ), 'Gutenberg 24.2 loads only the states fallback, so labels print once' );
	check( array() === missing_form_elements( 'Engine_Core_72' ), '7.2 core loads no fallback' );
	foreach ( array( 'forms-label-fallback', 'forms-fallback' ) as $sheet ) {
		check( is_readable( CR_THEME_DIR . "/assets/css/cr-$sheet.css" ), "assets/css/cr-$sheet.css exists" );
	}

	// The two Ollie 1.6.5 rules, as its style.css has them, between untouched rules.
	$ollie = "label {\n\twidth: 100%;\n}\n\n"
		. "input:not([type=\"submit\"]):not([type=\"radio\"]),\nselect,\ntextarea,\n.wp-block-post-comments-form input:not([type=\"submit\"]):not([type=\"checkbox\"]):not([type=\"radio\"]),\n.wp-block-post-comments-form textarea {\n\tbackground-color: #fff;\n}\n\n"
		. "input:focus-visible,\ntextarea:focus-visible,\nselect:focus-visible {\n\toutline-offset: 1px;\n}\n";
	$scoped = scope_ollie_field_rules( $ollie );
	check( null !== $scoped, 'Ollie 1.6.5 rules are found' );
	$scoped = (string) $scoped;
	check( str_contains( $scoped, 'input:not([type="submit"]):not([type="radio"])' . OLLIE_FIELD_SCOPE . ",\nselect" . OLLIE_FIELD_SCOPE . ",\ntextarea" . OLLIE_FIELD_SCOPE . ",\n" ), 'field rule: each selector gets the scope' );
	check( str_contains( $scoped, '.wp-block-post-comments-form textarea' . OLLIE_FIELD_SCOPE . " {\n\tbackground-color: #fff;" ), 'field rule: declarations unchanged' );
	check( str_contains( $scoped, "select:focus-visible" . OLLIE_FIELD_SCOPE . " {\n\toutline-offset: 1px;" ), 'focus rule: each selector gets the scope' );
	check( str_starts_with( $scoped, "label {\n\twidth: 100%;\n}" ), 'other rules unchanged' );
	check( 1 === substr_count( OLLIE_FIELD_SCOPE, ':not(:where(' ), 'scope adds no specificity (one :not(:where()))' );
	check( null === scope_ollie_field_rules( str_replace( 'select:focus-visible {', 'select:focus {', $ollie ) ), 'changed Ollie text is left alone' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
