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

		// v0.3.0 — Phase B: design-system block styles.
		// CSS lives in assets/css/components.css via selector-list
		// extension on the existing .cr-* source rules. No style_handle
		// required — components.css is enqueued globally by inc/enqueue.php.

		// Buttons — 4 variants matching the kit's primary/secondary/outline/soft.
		register_block_style(
			'core/button',
			array(
				'name'  => 'cr-cta',
				'label' => __( 'CTA (primary)', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/button',
			array(
				'name'  => 'cr-button-secondary',
				'label' => __( 'Secondary', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/button',
			array(
				'name'  => 'cr-button-outline',
				'label' => __( 'Outline', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/button',
			array(
				'name'  => 'cr-button-soft',
				'label' => __( 'Soft', 'courtneyr-child' ),
			)
		);

		// Separators — UT Orange marker bars at full and short widths.
		register_block_style(
			'core/separator',
			array(
				'name'  => 'cr-marker-bar',
				'label' => __( 'Marker bar', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/separator',
			array(
				'name'  => 'cr-marker-bar-short',
				'label' => __( 'Marker bar (short)', 'courtneyr-child' ),
			)
		);

		// Group — zine card + halftone overlay.
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-card',
				'label' => __( 'Card (zine)', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-halftone',
				'label' => __( 'Halftone overlay', 'courtneyr-child' ),
			)
		);

		// Quote — big italic block quote with marker frame.
		register_block_style(
			'core/quote',
			array(
				'name'  => 'cr-pull-quote',
				'label' => __( 'Pull quote (zine)', 'courtneyr-child' ),
			)
		);

		// v0.4.0 — zine block styles for callouts, accent blocks,
		// paper texture, plus inline treatments for eyebrow / tape
		// label / Rock Salt display heading. All source CSS already
		// lives in components.css; we add selector-list aliases to
		// expose each as a one-click block-style picker.

		// Group — Callout family (default cerulean / note prussian / warn yellow).
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-callout',
				'label' => __( 'Callout', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-callout-note',
				'label' => __( 'Callout — Note', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-callout-warn',
				'label' => __( 'Callout — Warn', 'courtneyr-child' ),
			)
		);

		// Group — Accent blocks 1/2/3 (sky, periwinkle, peach).
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-accent-1',
				'label' => __( 'Accent — Sky', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-accent-2',
				'label' => __( 'Accent — Periwinkle', 'courtneyr-child' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-accent-3',
				'label' => __( 'Accent — Peach', 'courtneyr-child' ),
			)
		);

		// Group — Paper texture (halftone + fiber, the cr-hero bg layer
		// without the hero layout). Use on landing surfaces, section heads.
		register_block_style(
			'core/group',
			array(
				'name'  => 'cr-paper',
				'label' => __( 'Paper texture', 'courtneyr-child' ),
			)
		);

		// Paragraph — kicker / eyebrow above headings (block, accent
		// font, uppercase, marker color).
		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'cr-eyebrow',
				'label' => __( 'Eyebrow (kicker)', 'courtneyr-child' ),
			)
		);

		// Paragraph — inline masking-tape label for "NEW" / "FRESH"
		// callouts. Forces the paragraph to inline-block so it shrinks
		// to content width.
		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'cr-tape-label',
				'label' => __( 'Tape label', 'courtneyr-child' ),
			)
		);

		// Heading — Rock Salt brand-voice display heading. Use once
		// per page; the brand voice does not scale.
		register_block_style(
			'core/heading',
			array(
				'name'  => 'cr-display',
				'label' => __( 'Display (Rock Salt)', 'courtneyr-child' ),
			)
		);
	}
);
