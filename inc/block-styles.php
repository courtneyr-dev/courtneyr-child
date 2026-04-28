<?php
/**
 * Block styles registered for the courtneyr-child theme.
 *
 * These appear in the editor under the "Styles" panel for the Group block,
 * allowing the user to apply zine section treatments without typing
 * Custom Class names. They map to .is-style-cr-* selectors in components.css.
 *
 * @package courtneyr-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register block styles for core/group so users can pick zine treatments
 * from the editor sidebar.
 */
add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_style' ) ) {
			return;
		}

		// Inverse — dark bg, ivory text. Use on any group that should read
		// as a dark zine card (e.g., a callout on a light page).
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-inverse',
				'label' => __( 'Inverse (dark)', 'courtneyr-child' ),
			)
		);

		// Journey — sky blue in light, cerulean in dark. Use for the
		// "Start your journey" or other CTA sections that should pop with
		// a distinct color from the surrounding sections.
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-journey',
				'label' => __( 'Journey CTA (sky/cerulean)', 'courtneyr-child' ),
			)
		);

		// Notebook — peach with subtle horizontal rule lines, like ruled
		// paper. Good for long-form callouts.
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-notebook',
				'label' => __( 'Notebook (peach with rules)', 'courtneyr-child' ),
			)
		);

		// Tape — warm yellow band, slight rotation, evokes washi tape on a
		// scrapbook page.
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-tape',
				'label' => __( 'Tape strip (yellow)', 'courtneyr-child' ),
			)
		);
	}
);
