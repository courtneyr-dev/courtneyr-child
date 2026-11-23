import { VIEWPORTS, fixtureLoader, guardSentinelLinks, renderFixture, withScheme } from '../stories/lib/fixture.js';

guardSentinelLinks();

// WordPress prints a page's blocks as children of <body>. Storybook's root must not add a box between them.
document.head.append( Object.assign( document.createElement( 'style' ), { textContent: '#storybook-root { display: contents; }' } ) );

/** @type { import('@storybook/html-vite').Preview } */
export default {
	parameters: {
		layout: 'fullscreen',
		viewport: { options: VIEWPORTS },
		// addon-a11y fails a story's test on an axe violation only with 'error'. Its 'todo' value reports and passes.
		a11y: { test: 'error' },
	},
	globalTypes: {
		scheme: {
			description: 'Color scheme, as the theme reads it from <html data-theme>',
			toolbar: { title: 'Scheme', icon: 'mirror', items: [ { value: 'light', title: 'Light' }, { value: 'dark', title: 'Dark' }, { value: 'system', title: 'System' } ], dynamicTitle: true },
		},
	},
	initialGlobals: { scheme: 'light' },
	loaders: [ fixtureLoader ],
	decorators: [ withScheme ],
	render: renderFixture,
};
