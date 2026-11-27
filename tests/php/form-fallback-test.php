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
	use const Courtneyr\Child\Enqueue\OLLIE_FIELD_RULES;
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

	check( null !== scope_ollie_field_rules( str_replace( "\n", "\r\n", $ollie ) ), 'CRLF line endings still match' );
	check( null !== scope_ollie_field_rules( str_replace( array( ",\n", 'textarea {' ), array( ' ,  ', "textarea\n\n{" ), $ollie ) ), 'reflowed selector lists still match' );
	check( null === scope_ollie_field_rules( str_replace( "\nselect,\ntextarea,", "\ntextarea,\nselect,", $ollie ) ), 'reordered selectors are left alone' );
	check( null === scope_ollie_field_rules( 'form ' . str_replace( "label {\n\twidth: 100%;\n}\n\n", '', $ollie ) ), 'a longer selector list ending in the same selectors is left alone' );

	/*
	 * The Ollie stylesheet courtneyr.dev deploys: wp-content/themes/ollie/style.css
	 * from courtneyr-dev/car-courtneyr-dev at 2efcbba2d42d7df4e09199dbce7774d627cf9ba5,
	 * Ollie 1.6.5. CR_OLLIE_STYLE points the check at another copy. Updating Ollie
	 * means replacing this fixture; the checks below then say whether the rewrite
	 * still neutralizes both rules.
	 */
	$fixture = getenv( 'CR_OLLIE_STYLE' ) ?: __DIR__ . '/fixtures/ollie-1.6.5-style.css';
	$shipped = (string) file_get_contents( $fixture );
	check( '' !== $shipped, "Ollie stylesheet read from $fixture" );
	$scoped = scope_ollie_field_rules( $shipped );
	check( null !== $scoped, 'shipped Ollie: both field rules are found' );
	$scoped = (string) $scoped;
	foreach ( OLLIE_FIELD_RULES as $selectors ) {
		$rule = implode( OLLIE_FIELD_SCOPE . ",\n", $selectors ) . OLLIE_FIELD_SCOPE . ' {';
		check( 1 === substr_count( $scoped, $rule ), 'shipped Ollie: the rule starting "' . $selectors[0] . '" is scoped on every selector' );
	}
	check( 8 === substr_count( $scoped, OLLIE_FIELD_SCOPE ), 'shipped Ollie: exactly the 8 target selectors are scoped' );
	check( preg_replace( '/\s+/', '', str_replace( OLLIE_FIELD_SCOPE, '', $scoped ) ) === preg_replace( '/\s+/', '', $shipped ), 'shipped Ollie: nothing else changes' );

	$spaced = scope_ollie_field_rules( str_replace( '[type="submit"]', '[ type = "submit" ]', $shipped ) );
	check( null !== $spaced && 8 === substr_count( $spaced, OLLIE_FIELD_SCOPE ), 'shipped Ollie with [ type = "submit" ]: all 8 selectors are still scoped' );
	check( null === scope_ollie_field_rules( str_replace( '[type="submit"]', '[type="button"]', $shipped ) ), 'shipped Ollie with a renamed attribute value is left alone' );
	check( null === scope_ollie_field_rules( str_replace( '[type="submit"]', '[kind="submit"]', $shipped ) ), 'shipped Ollie with a renamed attribute is left alone' );

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
