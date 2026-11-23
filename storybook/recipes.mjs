// The entry format and the steps after an edit are in docs/testing.md, under "Recipe entries".

const NOTE = '/seed-269-f1/';
const PAGE = '/sample-page/';
const READ_ARCHIVE = '/kind/read/';

export const environments = {
	t269: {
		site: 'courtneyr-verify',
		seed: 'courtneyr-x-t269',
		plugins: [ 'post-kinds-for-indieweb', 'wp-recipe-maker' ],
		maxBytes: 3 * 1024 * 1024,
		fixtures: [
			{ id: 'block-archive-identity--kind', block: 'courtneyr/archive-identity', route: READ_ARCHIVE, in: 'template', sources: [ 'inc/archives.php' ] },
			{ id: 'block-archive-identity--category', block: 'courtneyr/archive-identity', route: '/category/soup/', in: 'template', sources: [ 'inc/archives.php' ] },
			{ id: 'block-post-glyph--blog', block: 'courtneyr/post-glyph', route: NOTE, sources: [ 'inc/interactivity.php' ] },
			{ id: 'block-term-chips--category', block: 'courtneyr/term-chips', route: NOTE },
			{ id: 'block-term-chips--kind', block: 'courtneyr/term-chips', attrs: { term: 'kind' }, route: NOTE },
			{ id: 'block-weather-log--date-only', block: 'courtneyr/weather-log', route: NOTE, sources: [ 'inc/weather.php' ] },
			{
				id: 'block-series-guide--no-series',
				block: 'courtneyr/series-guide',
				attrs: { view: 'post-header' },
				route: NOTE,
				in: 'template',
				sources: [ 'inc/series-views.php' ],
				empty: 'The seed has no series taxonomy because PublishPress Series is not installed, so every view of the block prints nothing.',
			},
			{
				id: 'block-contact-form--visitor',
				block: 'courtneyr/contact-form',
				route: PAGE,
				empty: 'The seed has no contact-form plugin, so the cr_contact_form shortcode does not exist and a visitor gets no markup.',
			},
			{ id: 'block-contact-form--editor', block: 'courtneyr/contact-form', route: PAGE, caps: [ 'edit_posts' ] },

			{ id: 'pattern-cr-callout-note', pattern: 'courtneyr-child/cr-callout-note', route: PAGE },
			{ id: 'pattern-cr-callout-warn', pattern: 'courtneyr-child/cr-callout-warn', route: PAGE },
			{ id: 'pattern-cr-card-default', pattern: 'courtneyr-child/cr-card-default', route: PAGE },
			{ id: 'pattern-cr-pull-quote', pattern: 'courtneyr-child/cr-pull-quote', route: PAGE },
			{ id: 'pattern-cr-tape-label', pattern: 'courtneyr-child/cr-tape-label', route: PAGE },
			{ id: 'pattern-cr-stream-item', pattern: 'courtneyr-child/cr-stream-item', route: PAGE },
			{ id: 'pattern-cr-evidence-verified', pattern: 'courtneyr-child/cr-evidence-verified', route: PAGE },
			{ id: 'pattern-cr-hero-display', pattern: 'courtneyr-child/cr-hero-display', route: PAGE },
			{ id: 'pattern-cr-home-newsletter-reasons', pattern: 'courtneyr-child/cr-home-newsletter-reasons', route: '/' },
			{ id: 'pattern-cr-home-field-notes', pattern: 'courtneyr-child/cr-home-field-notes', route: '/' },
			{ id: 'pattern-cr-hcard', pattern: 'courtneyr-child/cr-hcard', route: PAGE },
			{ id: 'pattern-post-format-quote', pattern: 'courtneyr-child/post-format-quote', route: PAGE },
			{ id: 'pattern-cr-browse-all', pattern: 'courtneyr-child/cr-browse-all', route: READ_ARCHIVE, in: 'template' },
			{ id: 'pattern-cr-read-shelf-loop', pattern: 'courtneyr-child/cr-read-shelf-loop', route: READ_ARCHIVE, in: 'template' },

			{ id: 'page-home', route: '/' },
			{ id: 'page-kind-single', route: '/seed-269-s5/' },
			{ id: 'page-kind-archive', route: READ_ARCHIVE },
		],
	},
};
