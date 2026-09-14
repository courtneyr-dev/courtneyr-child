/** @type {import('stylelint').Config} */
export default {
	extends: [ '@wordpress/stylelint-config' ],
	rules: {
		// The theme names classes block__element--modifier, as WordPress core does (.components-panel__body); WordPress also emits .taxonomy-post_tag.
		'selector-class-pattern': [
			'^[a-z][a-z0-9]*(?:(?:-|--|__|_)[a-z0-9]+)*$',
			{ message: 'Use lowercase BEM class names: block__element--modifier (selector-class-pattern)' },
		],
		// wp-admin markup the theme styles but does not own uses underscores (#dashboard_right_now).
		'selector-id-pattern': [
			'^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$',
			{ message: 'Use lowercase ids separated by hyphens or underscores (selector-id-pattern)' },
		],
	},
};
