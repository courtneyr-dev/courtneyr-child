// `CR_SPEC=exercise-245.spec.mjs`: W4-T245 exercise archive, single, Stream and promoted
// Blog/search contracts. Read-only GETs; needs a running site seeded by the vault's
// assets/245-exercise/seed-245.php. Remote runs use CR_BASE_URL, CR_ALLOW_REMOTE=1 and the
// config's optional CR_USER_AGENT; this spec never sets or changes a user agent.
//
//   CR_BASE_URL=http://localhost:8892 CR_SPEC=exercise-245.spec.mjs \
//     npx playwright test --config tests/playwright/playwright.config.mjs
//
// Paths can be changed with CR_EXERCISE_ARCHIVE_PATH, CR_EXERCISE_STREAM_PATHS and
// CR_EXERCISE_BLOG_PATH. Fixtures are found by cr-seed-245-<key> in their permalinks.
//
// Acceptance-map decision entries 1-22 are covered by the archive group, 23-38 by single,
// and 39-51 by Stream. Entry 22's authenticated Site Editor interaction remains covered by
// the PHP block-registration/editor tests; this logged-out spec verifies its rendered result.
// Entry 30's Simple Location REST/endpoints proof remains in ExerciseLocationTest and R-W4.
// Entry 45's exact pre/post-branch craft style comparison belongs to TT1; the Stream control
// below proves that K01 never receives exercise markup. A2 keeps the pager's reduced-motion
// hover-lift cleanup deferred, so this spec checks the shared motion clamp but not that lift.
import { test, expect } from '@playwright/test';

const ARCHIVE = ( process.env.CR_EXERCISE_ARCHIVE_PATH || '/kind/exercise/' ).replace( /\/?$/, '/' );
const ARCHIVE_PAGE_2 = `${ ARCHIVE }page/2/`;
const ARCHIVE_PAGE_3 = `${ ARCHIVE }page/3/`;
const STREAM_PATHS = ( process.env.CR_EXERCISE_STREAM_PATHS || '/stream/,/' ).split( ',' ).map( ( path ) => path.trim() ).filter( Boolean );
const BLOG = process.env.CR_EXERCISE_BLOG_PATH || '/blog/';

const slugOf = ( key ) => `cr-seed-245-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const BIB = '.pkiw-exercise-day__bibs > li.wp-block-post';
const WEEK = 'section.pkiw-exercise-week';
const PAPER_COLORS = [
	'rgb(142, 202, 230)',
	'rgb(188, 181, 227)',
	'rgb(254, 226, 195)',
	'rgb(235, 235, 235)',
	'rgb(251, 250, 245)',
];
const VIOLET = 'rgb(36, 28, 74)';
const IVORY = 'rgb(251, 250, 245)';
const YELLOW = 'rgb(255, 183, 3)';
const NIGHT = 'rgb(37, 31, 28)';
const NIGHT_RULE = 'rgb(58, 52, 47)';
const TILT_DEGREES = [ -1.2, 1.5, -2.5, 2.8 ];

const FIXED_WEEKS = [
	[ 'August 23-29, 2026', 6 ],
	[ 'August 16-22, 2026', 5 ],
	[ 'August 9-15, 2026', 2 ],
	[ 'August 2-8, 2026', 3 ],
	[ 'July 26-August 1, 2026', 1 ],
	[ 'December 28, 2025-January 3, 2026', 1 ],
];

// Visible text matches whole words. Class and title tokens match only as a whole token or a hyphen part,
// because core's own wp-block-* classes hold the letters of "lock" and must not trip the scan.
const VISIBILITY_WORDS = /\b(?:route|private|public|lock|globe)\b/i;
const VISIBILITY_TOKENS = /(?:^|[-_])(?:route|private|public|lock|globe)(?:[-_]|$)/i;
const visibilityLeaks = ( text, attributes ) => [
	...( VISIBILITY_WORDS.test( text ) ? [ `text: ${ text.match( VISIBILITY_WORDS )[ 0 ] }` ] : [] ),
	...attributes.split( /\s+/ ).filter( ( token ) => VISIBILITY_TOKENS.test( token ) ).map( ( token ) => `token: ${ token }` ),
];

const normalizeRange = ( text ) => text.replace( /[–—]/g, '-' ).replace( /\s*-\s*/g, '-' ).replace( /\s+/g, ' ' ).trim();
const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 390 ? 900 : 1000 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function seeded( page, path = ARCHIVE, width = 1440 ) {
	const response = await open( page, path, width );
	test.skip( response?.status() === 404, `no page at ${ path }` );
	test.skip( 0 === await page.locator( 'a[href*="cr-seed-245-"]' ).count(), 'the issue 245 fixtures are not seeded here (seed-245.php)' );
	return response;
}

const itemFor = ( page, key ) => page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();
const bibFor = ( page, key ) => page.locator( BIB ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

async function openSingle( page, key, width = 1440 ) {
	let response = await open( page, singlePath( key ), width );
	if ( response?.status() === 404 ) {
		for ( const archivePath of [ ARCHIVE, ARCHIVE_PAGE_2 ] ) {
			await open( page, archivePath, width );
			const link = page.locator( `a[href*="${ slugOf( key ) }"]` ).first();
			if ( await link.count() ) {
				response = await page.goto( await link.getAttribute( 'href' ), { waitUntil: 'load' } );
				break;
			}
		}
	}
	test.skip( response?.status() === 404 || 0 === await page.locator( 'main' ).count(), `no fixture ${ key }` );
	return page.locator( 'main.single-post' ).first();
}

// W3-G2: count a[href] outside [hidden], and reject tabindex=-1 outside [hidden].
const linkReport = ( locator ) => locator.evaluate( ( root ) => {
	const visible = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) );
	const stray = [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) );
	return {
		hrefs: visible.map( ( link ) => link.getAttribute( 'href' ) ),
		texts: visible.map( ( link ) => link.textContent.trim() ),
		stray: stray.length,
	};
} );

// DOM-level mf2 report. The repository's Playwright harness has no PHP mf2 parser, so the
// plugin suite performs the full parse while this catches the rendered root/property contract.
const mf2Report = ( locator ) => locator.evaluate( ( root ) => ( {
	entry: root.classList.contains( 'h-entry' ),
	urls: [ ...root.querySelectorAll( '.u-url' ) ].map( ( node ) => node.getAttribute( 'href' ) || node.getAttribute( 'value' ) || '' ),
	published: [ ...root.querySelectorAll( '.dt-published' ) ].map( ( node ) => node.getAttribute( 'datetime' ) || node.getAttribute( 'value' ) || node.textContent.trim() ),
	authors: root.querySelectorAll( '.p-author' ).length,
	exercise: [ ...root.querySelectorAll( '.p-exercise' ) ].map( ( node ) => node.textContent.trim() ),
	nestedEntries: root.querySelectorAll( '.h-entry' ).length,
} ) );

const parseRgb = ( value ) => {
	const match = /rgba?\(\s*(\d+)[ ,]+(\d+)[ ,]+(\d+)/.exec( value || '' );
	return match ? [ Number( match[ 1 ] ), Number( match[ 2 ] ), Number( match[ 3 ] ) ] : null;
};
const luminance = ( [ r, g, b ] ) => {
	const channel = ( value ) => ( ( value /= 255 ) <= 0.03928 ? value / 12.92 : ( ( value + 0.055 ) / 1.055 ) ** 2.4 );
	return 0.2126 * channel( r ) + 0.7152 * channel( g ) + 0.0722 * channel( b );
};
const contrast = ( foreground, background ) => {
	const [ high, low ] = [ luminance( parseRgb( foreground ) ), luminance( parseRgb( background ) ) ].sort( ( a, b ) => b - a );
	return ( high + 0.05 ) / ( low + 0.05 );
};

const degreeFromMatrix = ( transform ) => {
	const match = /matrix\(([^)]+)\)/.exec( transform );
	if ( ! match ) {
		return null;
	}
	const [ a, b ] = match[ 1 ].split( ',' ).map( Number );
	return ( Math.atan2( b, a ) * 180 ) / Math.PI;
};

async function expectNoBlur( page, root = 'main' ) {
	const offenders = await page.locator( `${ root }, ${ root } *` ).evaluateAll( ( nodes ) => {
		const topLevelParts = ( value ) => {
			const parts = [];
			let depth = 0;
			let start = 0;
			for ( let i = 0; i < value.length; i++ ) {
				depth += '(' === value[ i ] ? 1 : ')' === value[ i ] ? -1 : 0;
				if ( ',' === value[ i ] && 0 === depth ) {
					parts.push( value.slice( start, i ) );
					start = i + 1;
				}
			}
			parts.push( value.slice( start ) );
			return parts;
		};
		const lengths = ( value ) => [ ...value.matchAll( /-?(?:\d+\.?\d*|\.\d+)px/g ) ].map( ( match ) => Number.parseFloat( match[ 0 ] ) );
		const found = [];
		for ( const node of nodes ) {
			for ( const pseudo of [ null, '::before', '::after' ] ) {
				const style = getComputedStyle( node, pseudo );
				if ( 'none' !== style.boxShadow ) {
					for ( const shadow of topLevelParts( style.boxShadow ) ) {
						if ( Math.abs( lengths( shadow )[ 2 ] || 0 ) > 0.01 ) {
							found.push( `${ node.tagName }${ pseudo || '' } box-shadow ${ style.boxShadow }` );
						}
					}
				}
				if ( 'none' !== style.filter ) {
					if ( /\bblur\(/.test( style.filter ) || [ ...style.filter.matchAll( /drop-shadow\(([^)]+(?:\)[^)]*)?)\)/g ) ].some( ( match ) => Math.abs( lengths( match[ 1 ] )[ 2 ] || 0 ) > 0.01 ) ) {
						found.push( `${ node.tagName }${ pseudo || '' } filter ${ style.filter }` );
					}
				}
			}
		}
		return found;
	} );
	expect( offenders, 'every shadow and drop-shadow has a zero blur radius' ).toEqual( [] );
}

function expectedSummaryDistance( unit, week ) {
	const values = {
		km: { run: '8.0 km', august23: '10.0 km', august16: '22.4 km', august2: '6.0 km' },
		mi: { run: '5.0 mi', august23: '6.2 mi', august16: '13.9 mi', august2: '3.7 mi' },
	};
	return values[ unit ][ week ];
}

async function listingItem( page, key ) {
	const anchor = page.locator( `a[href*="${ slugOf( key ) }"]` ).first();
	if ( 0 === await anchor.count() ) {
		return null;
	}
	const item = page.locator( 'li.wp-block-post, article, .wp-block-post' ).filter( { has: anchor } ).first();
	return 0 < await item.count() ? item : anchor.locator( 'xpath=..' );
}

// --- Archive -----------------------------------------------------------------------------------

test.describe( 'exercise archive', () => {
	test( 'empty archive keeps the identity and no-results copy without weeks, medals or a pager', async ( { page } ) => {
		await open( page, ARCHIVE );
		test.skip( 0 < await page.locator( BIB ).count(), 'the archive is seeded; this case runs before seeding' );
		await expect( page.locator( 'main h1' ) ).toContainText( 'Exercise' );
		await expect( page.locator( '.wp-block-term-description' ) ).not.toBeEmpty();
		await expect( page.locator( 'main' ) ).toContainText( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' );
		await expect( page.locator( '#browse-all, .cr-browse-all' ).first() ).toBeVisible();
		expect( await page.locator( `${ WEEK }, .pkiw-medal-row, nav.wp-block-query-pagination` ).count() ).toBe( 0 );
	} );

	test( 'header identity has one H1, eight inert tiles, editable description, divider and inert badge', async ( { page } ) => {
		await seeded( page );
		const h1 = page.locator( 'main h1' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1.locator( '.cr-cutout__text' ) ).toHaveText( 'Exercise' );
		const tiles = h1.locator( '.cr-cutout__tile' );
		await expect( tiles ).toHaveCount( 8 );
		expect( ( await tiles.allTextContents() ).join( '' ) ).toBe( 'Exercise' );
		expect( await h1.locator( '.cr-cutout__tiles' ).getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		const transforms = await tiles.evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).transform ) );
		for ( let i = 1; i < transforms.length; i++ ) {
			expect( transforms[ i ], `tiles ${ i } and ${ i + 1 } use different adjacent tilts` ).not.toBe( transforms[ i - 1 ] );
		}
		await expect( page.locator( '.wp-block-term-description' ) ).not.toBeEmpty();
		await expect( page.locator( 'hr.is-style-cr-marker-bar-short' ) ).toHaveCount( 1 );
		const badge = page.locator( '.cr-archive-identity__glyph' );
		expect( await badge.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		expect( await page.locator( '.cr-archive-identity a, .cr-archive-identity [tabindex]' ).count() ).toBe( 0 );
		expect( await page.locator( '.cr-archive__header :is(.cr-tape-label, .cr-pin)' ).count() ).toBe( 0 );
		expect( await page.locator( '.cr-archive__header' ).innerText() ).not.toContain( 'Stream · Exercise' );
		expect( await page.locator( 'main' ).innerText() ).not.toMatch( /Sample content|sample note|slogan/i );
	} );

	test( 'pages hold four then three whole weeks, exact fixture counts and unit-dependent summaries', async ( { page } ) => {
		await seeded( page );
		const pageOne = await page.locator( WEEK ).evaluateAll( ( weeks ) => weeks.map( ( week ) => ( {
			heading: week.querySelector( ':scope > h2' ).textContent.trim(),
			bibs: week.querySelectorAll( '.pkiw-exercise-day__bibs > li.wp-block-post' ).length,
			summary: week.querySelector( '.pkiw-exercise-week__summary' ).textContent.replace( /\s+/g, ' ' ).trim(),
		} ) ) );
		expect( pageOne ).toHaveLength( 4 );
		expect( pageOne.slice( 1 ).map( ( week ) => [ normalizeRange( week.heading ), week.bibs ] ) ).toEqual( FIXED_WEEKS.slice( 0, 3 ) );
		expect( pageOne[ 0 ].bibs, 'run week contains S01-S03' ).toBe( 3 );
		for ( const week of pageOne ) {
			expect( week.summary.startsWith( `${ week.bibs } ${ 1 === week.bibs ? 'workout' : 'workouts' }` ), week.heading ).toBe( true );
		}
		const unit = pageOne[ 0 ].summary.match( /\b(mi|km)\b/ )?.[ 1 ];
		expect( unit, 'the first summary reports mi or km' ).toMatch( /^(mi|km)$/ );
		expect( pageOne[ 0 ].summary ).toContain( expectedSummaryDistance( unit, 'run' ) );
		expect( pageOne[ 1 ].summary ).toContain( expectedSummaryDistance( unit, 'august23' ) );
		expect( pageOne[ 2 ].summary ).toContain( expectedSummaryDistance( unit, 'august16' ) );
		expect( pageOne[ 3 ].summary, 'Aug 9-15 has no totals' ).toBe( '2 workouts' );

		await seeded( page, ARCHIVE_PAGE_2 );
		const pageTwo = await page.locator( WEEK ).evaluateAll( ( weeks ) => weeks.map( ( week ) => ( {
			heading: week.querySelector( ':scope > h2' ).textContent.trim(),
			bibs: week.querySelectorAll( '.pkiw-exercise-day__bibs > li.wp-block-post' ).length,
			summary: week.querySelector( '.pkiw-exercise-week__summary' ).textContent.replace( /\s+/g, ' ' ).trim(),
		} ) ) );
		expect( pageTwo.map( ( week ) => [ normalizeRange( week.heading ), week.bibs ] ) ).toEqual( FIXED_WEEKS.slice( 3 ) );
		for ( const week of pageTwo ) {
			expect( week.summary.startsWith( `${ week.bibs } workout` ), week.heading ).toBe( true );
		}
		expect( pageTwo[ 0 ].summary ).toContain( expectedSummaryDistance( unit, 'august2' ) );
		expect( pageTwo[ 2 ].summary ).toContain( '5 min' );
		expect( await page.locator( '.pkiw-exercise-week__summary img, .pkiw-exercise-week__summary svg' ).count(), 'summary lines have no icon' ).toBe( 0 );
		const firstSet = new Set( pageOne.map( ( week ) => normalizeRange( week.heading ) ) );
		expect( pageTwo.every( ( week ) => ! firstSet.has( normalizeRange( week.heading ) ) ), 'weeks never split or repeat' ).toBe( true );

		const response = await open( page, ARCHIVE_PAGE_3 );
		expect( response?.status(), 'page 3 is past the final week' ).toBe( 404 );
	} );

	test( 'heading outline is one H1, page-one medal H2, one H2 per week and one linked H3 per bib', async ( { page } ) => {
		await seeded( page );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( '.pkiw-medal-row > h2' ) ).toHaveText( 'Personal bests' );
		await expect( page.locator( `${ WEEK } > h2` ) ).toHaveCount( 4 );
		expect( await page.locator( `${ BIB } > h3.pkiw-exercise-bib__title` ).count() ).toBe( await page.locator( BIB ).count() );
		expect( await page.locator( `${ BIB } > h3 > a.pkiw-exercise-bib__link` ).count() ).toBe( await page.locator( BIB ).count() );
		await seeded( page, ARCHIVE_PAGE_2 );
		await expect( page.locator( '.pkiw-medal-row, h2:text-is("Personal bests")' ) ).toHaveCount( 0 );
		await expect( page.locator( `${ WEEK } > h2` ) ).toHaveCount( 3 );
	} );

	test( 'days carry one date, Aug 19 stacks three posts in time order and A06 stays on Aug 29', async ( { page } ) => {
		await seeded( page );
		const populatedDays = page.locator( '.pkiw-exercise-day:not(.pkiw-exercise-day--empty)' );
		for ( let i = 0; i < await populatedDays.count(); i++ ) {
			const day = populatedDays.nth( i );
			await expect( day.locator( ':scope > time[datetime]' ) ).toHaveCount( 1 );
		}
		const august19 = page.locator( '.pkiw-exercise-day' ).filter( { has: page.locator( ':scope > time[datetime="2026-08-19"]' ) } );
		expect( await august19.locator( `${ BIB } h3` ).allTextContents() ).toEqual( [
			'Morning Swim',
			'Lunch Walk',
			'The Long Way Around the Reservoir and Back Past the Old Mill Bridge',
		] );
		const august29 = page.locator( '.pkiw-exercise-day' ).filter( { has: page.locator( ':scope > time[datetime="2026-08-29"]' ) } );
		await expect( august29.locator( `a[href*="${ slugOf( 'A06' ) }"]` ) ).toHaveCount( 1 );
	} );

	test( 'every bib is title plus activity, one whole-card link, date-only metadata and no image or visibility marker', async ( { page } ) => {
		for ( const path of [ ARCHIVE, ARCHIVE_PAGE_2 ] ) {
			await seeded( page, path );
			const bibs = page.locator( BIB );
			for ( let i = 0; i < await bibs.count(); i++ ) {
				const bib = bibs.nth( i );
				const report = await linkReport( bib );
				expect( report.hrefs ).toHaveLength( 1 );
				expect( report.stray ).toBe( 0 );
				expect( await bib.locator( ':scope > :not(h3):not(.pkiw-exercise-bib__activity):not([hidden])' ).count(), `bib ${ i + 1 } has no extra visible field` ).toBe( 0 );
				const exactText = await bib.evaluate( ( root ) => {
					const expected = [ root.querySelector( ':scope > h3' )?.innerText, root.querySelector( ':scope > .pkiw-exercise-bib__activity' )?.innerText ].filter( Boolean ).join( '\n' ).replace( /\s+/g, ' ' ).trim();
					return { expected, actual: root.innerText.replace( /\s+/g, ' ' ).trim() };
				} );
				expect( exactText.actual, `bib ${ i + 1 } is title plus activity only` ).toBe( exactText.expected );
				const target = await bib.boundingBox();
				expect( Math.min( target.width, target.height ), `bib ${ i + 1 } target` ).toBeGreaterThanOrEqual( 44 );
				const corners = await bib.evaluate( ( root ) => {
					const link = root.querySelector( 'a[href]' );
					const box = root.getBoundingClientRect();
					return [ [ box.left + 3, box.top + 3 ], [ box.right - 3, box.top + 3 ], [ box.left + 3, box.bottom - 3 ], [ box.right - 3, box.bottom - 3 ] ].map( ( [ x, y ] ) => document.elementFromPoint( x, y )?.closest( 'a' ) === link );
				} );
				expect( corners, `bib ${ i + 1 } link overlay reaches all inner corners` ).toEqual( [ true, true, true, true ] );
				const published = await bib.locator( '.dt-published' ).getAttribute( 'value' );
				expect( published ).toMatch( /^\d{4}-\d{2}-\d{2}$/ );
			}
			const scan = await page.locator( '.cr-exercise' ).evaluate( ( root ) => ( {
				text: root.innerText,
				attributes: [ ...root.querySelectorAll( '*' ) ].flatMap( ( node ) => [ node.getAttribute( 'class' ) || '', node.getAttribute( 'title' ) || '' ] ).join( ' ' ),
			} ) );
			expect( visibilityLeaks( scan.text, scan.attributes ), 'no text, class or title says a field is hidden, private or public' ).toEqual( [] );
			expect( await page.locator( '.cr-exercise img, .cr-exercise svg' ).count() ).toBe( 0 );
		}
	} );

	test( 'A02 hidden details render like A03 never-stored details after post-specific tokens are removed', async ( { page } ) => {
		await seeded( page );
		const normalized = async ( key ) => bibFor( page, key ).evaluate( ( bib ) => {
			const copy = bib.cloneNode( true );
			copy.querySelectorAll( '[hidden]' ).forEach( ( node ) => node.remove() );
			copy.querySelectorAll( '[id]' ).forEach( ( node ) => node.removeAttribute( 'id' ) );
			copy.querySelectorAll( 'a[href]' ).forEach( ( node ) => node.setAttribute( 'href', 'PERMALINK' ) );
			copy.className = [ ...copy.classList ].filter( ( name ) => ! /^post-\d+$/.test( name ) && ! /^cr-bib--/.test( name ) ).sort().join( ' ' );
			return copy.outerHTML.replace( /\s+/g, ' ' );
		} );
		expect( await normalized( 'A02' ) ).toBe( await normalized( 'A03' ) );
		expect( await bibFor( page, 'A02' ).innerText() ).not.toContain( '7.77' );
	} );

	test( 'archive h-entry roots keep URL, date, author and activity without nested entry roots', async ( { page } ) => {
		await seeded( page );
		for ( const key of [ 'A01', 'A03', 'A05', 'C02' ] ) {
			const report = await mf2Report( bibFor( page, key ) );
			expect( report.entry, key ).toBe( true );
			expect( report.urls.length, `${ key } URL` ).toBeGreaterThan( 0 );
			expect( report.published[ 0 ], `${ key } date` ).toMatch( /^\d{4}-\d{2}-\d{2}$/ );
			expect( report.authors, `${ key } author` ).toBeGreaterThan( 0 );
			expect( report.nestedEntries, `${ key } no nested h-entry` ).toBe( 0 );
		}
		expect( ( await mf2Report( bibFor( page, 'A03' ) ) ).exercise ).toEqual( [ 'Walk' ] );
		expect( ( await mf2Report( bibFor( page, 'A05' ) ) ).exercise ).toEqual( [ 'Bike ride' ] );
		expect( ( await mf2Report( bibFor( page, 'C02' ) ) ).exercise ).toEqual( [] );
	} );

	test( 'week grids use seven, three and one columns with empty cells only at 64rem and up', async ( { page } ) => {
		for ( const [ width, columns, emptyDisplay ] of [ [ 1440, 7, 'block' ], [ 800, 3, 'none' ], [ 375, 1, 'none' ] ] ) {
			await seeded( page, ARCHIVE, width );
			const reports = await page.locator( `${ WEEK } .pkiw-exercise-week__days` ).evaluateAll( ( lists ) => lists.map( ( list ) => {
				const cells = [ ...list.children ];
				const empty = cells.filter( ( cell ) => cell.classList.contains( 'pkiw-exercise-day--empty' ) );
				return {
					columns: getComputedStyle( list ).gridTemplateColumns.split( ' ' ).length,
					cells: cells.length,
					emptyDisplay: empty.map( ( cell ) => getComputedStyle( cell ).display ),
					emptyHidden: empty.every( ( cell ) => 'true' === cell.getAttribute( 'aria-hidden' ) ),
					emptyText: empty.every( ( cell ) => '' === cell.textContent.trim() ),
					emptyFocus: empty.every( ( cell ) => ! cell.querySelector( 'a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])' ) ),
				};
			} ) );
			for ( const report of reports ) {
				expect( report.columns, `${ width }px columns` ).toBe( columns );
				expect( report.cells, `${ width }px seven calendar days` ).toBe( 7 );
				expect( report.emptyDisplay.every( ( value ) => value === emptyDisplay ), `${ width }px empty display` ).toBe( true );
				expect( report.emptyHidden && report.emptyText && report.emptyFocus, `${ width }px empty semantics` ).toBe( true );
			}
		}
	} );

	test( 'DOM, visual and keyboard order agree at 1440, 800, 375 and 320', async ( { page } ) => {
		for ( const width of [ 1440, 800, 375, 320 ] ) {
			await seeded( page, ARCHIVE, width );
			const links = page.locator( `${ BIB } .pkiw-exercise-bib__link` );
			// A calendar reads by day cell, row by row, and top to bottom inside a day.
			const order = await page.locator( WEEK ).evaluateAll( ( weeks ) => weeks.map( ( week ) => {
				const days = [ ...week.querySelectorAll( '.pkiw-exercise-day:not(.pkiw-exercise-day--empty)' ) ].map( ( day, index ) => {
					const box = day.getBoundingClientRect();
					return { index, top: Math.round( box.top ), left: Math.round( box.left ), bibs: [ ...day.querySelectorAll( '.pkiw-exercise-day__bibs > li' ) ].map( ( bib ) => Math.round( bib.getBoundingClientRect().top ) ) };
				} );
				const visual = [ ...days ].sort( ( x, y ) => x.top - y.top || x.left - y.left ).map( ( day ) => day.index );
				return { dom: days.map( ( day ) => day.index ), visual, stacked: days.every( ( day ) => day.bibs.every( ( top, at ) => 0 === at || top >= day.bibs[ at - 1 ] ) ) };
			} ) );
			for ( const week of order ) {
				expect( week.visual, `${ width }px visual reading order of the day cells` ).toEqual( week.dom );
				expect( week.stacked, `${ width }px bibs stack top to bottom inside a day` ).toBe( true );
			}

			const expectedHrefs = await links.evaluateAll( ( nodes ) => nodes.map( ( node ) => node.href ) );
			await page.locator( '.cr-exercise' ).evaluate( ( node ) => {
				node.setAttribute( 'tabindex', '-1' );
				node.focus();
			} );
			const visited = [];
			for ( let i = 0; i < expectedHrefs.length + 12 && visited.length < expectedHrefs.length; i++ ) {
				await page.keyboard.press( 'Tab' );
				const href = await page.evaluate( () => document.activeElement?.closest( '.pkiw-exercise-day__bibs' ) ? document.activeElement.href : '' );
				if ( href ) {
					visited.push( href );
				}
			}
			expect( visited, `${ width }px tab order` ).toEqual( expectedHrefs );
		}
	} );

	test( 'long titles wrap, three-post days do not overlap and 320 or 400 percent zoom does not scroll sideways', async ( { page } ) => {
		await seeded( page, ARCHIVE, 320 );
		const longTitle = bibFor( page, 'B03' ).locator( 'h3' );
		const title = await longTitle.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { ellipsis: style.textOverflow, clamp: style.webkitLineClamp, scroll: node.scrollWidth, client: node.clientWidth };
		} );
		expect( title.ellipsis ).not.toBe( 'ellipsis' );
		expect( title.clamp ).toBe( 'none' );
		expect( title.scroll ).toBeLessThanOrEqual( title.client + 1 );
		const aug19Rects = await page.locator( '.pkiw-exercise-day' ).filter( { has: page.locator( 'time[datetime="2026-08-19"]' ) } ).locator( BIB ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.getBoundingClientRect().toJSON() ) );
		for ( let i = 1; i < aug19Rects.length; i++ ) {
			expect( aug19Rects[ i ].top, `Aug 19 bib ${ i + 1 } follows the prior bib` ).toBeGreaterThanOrEqual( aug19Rects[ i - 1 ].bottom );
		}
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );

		// A 320px CSS viewport is the 1280px layout at the 400% reflow threshold.
		await seeded( page, ARCHIVE, 320 );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ), '1280 at 400 percent reflows without horizontal scroll' ).toBeLessThanOrEqual( 0 );
	} );

	test( 'bib papers use the five allowed fills; headings, day bars, summaries and pager stay upright with hard shadows', async ( { page } ) => {
		await seeded( page );
		const paints = await page.locator( BIB ).evaluateAll( ( bibs ) => bibs.map( ( bib ) => ( {
			background: getComputedStyle( bib ).backgroundColor,
			ink: getComputedStyle( bib ).color,
			pin: getComputedStyle( bib, '::before' ).content,
		} ) ) );
		for ( const paint of paints ) {
			expect( PAPER_COLORS ).toContain( paint.background );
			expect( paint.ink ).toBe( VIOLET );
			expect( paint.pin ).not.toBe( 'none' );
		}
		const upright = await page.locator( `${ WEEK } h2, .pkiw-exercise-day > time, .pkiw-exercise-week__summary, nav.wp-block-query-pagination` ).evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).transform ) );
		expect( upright.every( ( value ) => 'none' === value ) ).toBe( true );
		await expectNoBlur( page, '.cr-exercise' );

		const artAt1440 = await page.locator( '.cr-exercise' ).evaluate( ( node ) => [ getComputedStyle( node, '::before' ).backgroundImage, getComputedStyle( node, '::after' ).backgroundImage ] );
		expect( artAt1440.join( ' ' ) ).toContain( 'exercise-shoes.svg' );
		expect( artAt1440.join( ' ' ) ).toContain( 'exercise-towel.svg' );
		await seeded( page, ARCHIVE, 800 );
		const artAt800 = await page.locator( '.cr-exercise' ).evaluate( ( node ) => [ getComputedStyle( node, '::before' ).backgroundImage, getComputedStyle( node, '::after' ).backgroundImage ] );
		expect( artAt800.join( ' ' ) ).toContain( 'exercise-shoes.svg' );
		expect( artAt800.join( ' ' ) ).not.toContain( 'exercise-towel.svg' );
		await seeded( page, ARCHIVE, 375 );
		const artAt375 = await page.locator( '.cr-exercise' ).evaluate( ( node ) => [ getComputedStyle( node, '::before' ).backgroundImage, getComputedStyle( node, '::after' ).backgroundImage ] );
		expect( artAt375 ).toEqual( [ 'none', 'none' ] );
	} );

	test( 'medal row is A01 then B04 above the query, with one image-free 44px link per medal', async ( { page } ) => {
		await seeded( page );
		const row = page.locator( '.pkiw-medal-row' );
		const medals = await row.locator( 'li.pkiw-medal' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( {
			category: node.querySelector( '.pkiw-medal__category' ).textContent.trim(),
			time: node.querySelector( '.pkiw-medal__time' ).textContent.trim(),
			links: node.querySelectorAll( 'a[href]' ).length,
			size: Math.min( node.querySelector( 'a' ).getBoundingClientRect().width, node.querySelector( 'a' ).getBoundingClientRect().height ),
		} ) ) );
		expect( medals ).toEqual( [
			{ category: '5K', time: '29:12', links: 1, size: medals[ 0 ].size },
			{ category: '10K', time: '52:03', links: 1, size: medals[ 1 ].size },
		] );
		expect( medals.every( ( medal ) => medal.size >= 44 ) ).toBe( true );
		expect( await row.locator( 'img, svg' ).count() ).toBe( 0 );
		expect( await row.innerText() ).not.toMatch( /Track Night|Members Only Ride|2026/ );
		const order = await page.locator( '.cr-exercise' ).evaluate( ( root ) => {
			const medal = root.querySelector( '.pkiw-medal-row' );
			const query = root.querySelector( '.cr-exercise__query' );
			const pager = root.querySelector( 'nav.wp-block-query-pagination' );
			return !! ( medal.compareDocumentPosition( query ) & Node.DOCUMENT_POSITION_FOLLOWING ) && !! ( query.compareDocumentPosition( pager ) & Node.DOCUMENT_POSITION_FOLLOWING );
		} );
		expect( order ).toBe( true );
	} );

	test( 'native pager has correct edge links, 44px square chips, sharp corners, hard shadow and hover lift', async ( { page }, testInfo ) => {
		for ( const [ path, previous, next ] of [ [ ARCHIVE, 0, 1 ], [ ARCHIVE_PAGE_2, 1, 0 ] ] ) {
			await seeded( page, path );
			const nav = page.locator( 'nav[aria-label="Pagination"]' );
			await expect( nav ).toHaveCount( 1 );
			await expect( nav.locator( 'a.wp-block-query-pagination-previous' ) ).toHaveCount( previous );
			await expect( nav.locator( 'a.wp-block-query-pagination-next' ) ).toHaveCount( next );
			const current = nav.locator( '[aria-current="page"]' );
			const style = await current.evaluate( ( node ) => {
				const computed = getComputedStyle( node );
				const box = node.getBoundingClientRect();
				return { width: box.width, height: box.height, radius: computed.borderRadius, shadow: computed.boxShadow, family: computed.fontFamily, color: computed.color, background: computed.backgroundColor };
			} );
			expect( Math.min( style.width, style.height ) ).toBeGreaterThanOrEqual( 44 );
			expect( Number.parseFloat( style.radius ) ).toBe( 0 );
			expect( style.shadow ).toMatch( /3px/ );
			expect( style.family ).toContain( 'Roboto Slab' );
			if ( isDark( testInfo ) ) {
				expect( [ style.color, style.background ] ).toEqual( [ VIOLET, IVORY ] );
			} else {
				expect( [ style.color, style.background ] ).toEqual( [ IVORY, VIOLET ] );
			}
			const hoverTarget = nav.locator( 'a, [aria-current="page"]' ).first();
			await hoverTarget.hover();
			expect( await hoverTarget.evaluate( ( node ) => getComputedStyle( node ).transform ) ).not.toBe( 'none' );
		}
	} );

	test( 'focus rings the whole bib and medal with 3px yellow plus a 7px halo', async ( { page } ) => {
		await seeded( page );
		for ( const target of [ bibFor( page, 'A01' ), page.locator( '.pkiw-medal' ).first() ] ) {
			await target.locator( 'a' ).focus();
			const ring = await target.evaluate( ( node ) => {
				const style = getComputedStyle( node );
				return { width: style.outlineWidth, color: style.outlineColor, shadow: style.boxShadow };
			} );
			expect( ring.width ).toBe( '3px' );
			expect( ring.color ).toBe( YELLOW );
			expect( ring.shadow ).toMatch( /7px/ );
		}
	} );

	test( 'dark mode keeps paper bibs violet-on-paper and moves panels to night surfaces', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		await seeded( page );
		const paint = await page.evaluate( () => {
			const week = document.querySelector( 'section.pkiw-exercise-week' );
			const day = document.querySelector( '.pkiw-exercise-day:not(.pkiw-exercise-day--empty)' );
			const bib = document.querySelector( '.pkiw-exercise-day__bibs > li.wp-block-post' );
			const bar = day.querySelector( ':scope > time' );
			return {
				week: getComputedStyle( week ).backgroundColor,
				rule: getComputedStyle( week ).borderTopColor,
				day: getComputedStyle( day ).backgroundColor,
				paper: getComputedStyle( bib ).backgroundColor,
				ink: getComputedStyle( bib ).color,
				barColor: getComputedStyle( bar ).color,
				barBackground: getComputedStyle( bar ).backgroundColor,
				shadow: getComputedStyle( bib ).boxShadow,
			};
		} );
		expect( paint.week ).toBe( NIGHT );
		expect( paint.day ).toBe( NIGHT );
		expect( paint.rule ).toBe( NIGHT_RULE );
		expect( PAPER_COLORS ).toContain( paint.paper );
		expect( paint.ink ).toBe( VIOLET );
		expect( contrast( paint.barColor, paint.barBackground ) ).toBeGreaterThanOrEqual( 4.5 );
		expect( paint.shadow ).toContain( YELLOW );
	} );

	test( 'forced colors outlines content cells and removes art, pins and empty cells (the header burst belongs to the shared archive-identity block)', async ( { page } ) => {
		await seeded( page );
		await page.emulateMedia( { forcedColors: 'active' } );
		const report = await page.evaluate( () => {
			const bib = document.querySelector( '.pkiw-exercise-day__bibs > li.wp-block-post' );
			const medal = document.querySelector( '.pkiw-medal__link' );
			const day = document.querySelector( '.pkiw-exercise-day:not(.pkiw-exercise-day--empty)' );
			const wrapper = document.querySelector( '.cr-exercise' );
			return {
				borders: [ bib, medal, day ].map( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }` ),
				pin: getComputedStyle( bib, '::before' ).display,
				art: [ getComputedStyle( wrapper, '::before' ).display, getComputedStyle( wrapper, '::after' ).display ],
				empty: [ ...document.querySelectorAll( '.pkiw-exercise-day--empty' ) ].map( ( node ) => getComputedStyle( node ).display ),
			};
		} );
		expect( report.borders ).toEqual( [ '2px solid', '2px solid', '2px solid' ] );
		expect( report.pin ).toBe( 'none' );
		expect( report.art ).toEqual( [ 'none', 'none' ] );
		expect( report.empty.every( ( value ) => 'none' === value ) ).toBe( true );
	} );

	test( 'reduced motion removes bib and medal hover motion and clamps shared transitions', async ( { page } ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await seeded( page );
		for ( const target of [ bibFor( page, 'A01' ), page.locator( '.pkiw-medal' ).first() ] ) {
			await target.hover();
			expect( await target.evaluate( ( node ) => getComputedStyle( node ).transform ) ).toBe( 'none' );
			expect( await target.evaluate( ( node ) => Math.max( ...getComputedStyle( node ).transitionDuration.split( ',' ).map( Number.parseFloat ) ) ) ).toBeLessThanOrEqual( 0.000001 );
		}
		const pagerDuration = await page.locator( 'nav.wp-block-query-pagination a' ).first().evaluate( ( node ) => Math.max( ...getComputedStyle( node ).transitionDuration.split( ',' ).map( Number.parseFloat ) ) );
		expect( pagerDuration, 'A2: shared token clamps motion; pager lift itself remains deferred' ).toBeLessThanOrEqual( 0.000001 );
	} );
} );

// --- Single ------------------------------------------------------------------------------------

test.describe( 'exercise single', () => {
	test( 'A01 prints one titled H1 and one complete public record with ordered real splits', async ( { page } ) => {
		await openSingle( page, 'A01' );
		const h1 = page.locator( 'main h1' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1 ).toHaveText( 'Lakeside Loop' );
		expect( await h1.evaluate( ( node ) => getComputedStyle( node ).fontFamily ) ).toContain( 'Roboto Slab' );
		const card = page.locator( '.pkiw-exercise' );
		await expect( card.locator( '.pkiw-exercise__activity' ) ).toHaveText( 'Run' );
		await expect( card.locator( 'time.pkiw-exercise__date[datetime="2026-08-23"]' ) ).toHaveCount( 1 );
		await expect( card.locator( '.pkiw-exercise__distance' ) ).toHaveText( '5.00 km' );
		await expect( card.locator( '.pkiw-exercise__duration' ) ).toHaveText( '29:12' );
		await expect( card.locator( '.pkiw-exercise__best' ) ).toHaveText( 'Personal best: 5K, 29:12' );
		await expect( card.locator( 'h2.pkiw-exercise__splits-heading' ) ).toHaveText( 'Splits' );
		const table = card.locator( 'table.pkiw-exercise__splits' );
		expect( await table.getAttribute( 'aria-labelledby' ) ).toBe( await card.locator( 'h2.pkiw-exercise__splits-heading' ).getAttribute( 'id' ) );
		expect( await table.locator( 'th[scope="col"]' ).allTextContents() ).toEqual( [ 'km', 'Time' ] );
		expect( ( await table.locator( 'tbody tr' ).allTextContents() ).map( ( text ) => text.replace( /\s+/g, ' ' ).trim() ) ).toEqual( [ '1 5:58', '2 5:51', '3 5:49', '4 5:47', '5 5:47' ] );
		expect( await table.evaluate( ( node ) => getComputedStyle( node ).transform ) ).toBe( 'none' );
	} );

	test( 'hidden and absent fields leave no placeholder, privacy marker, route or map', async ( { page } ) => {
		await openSingle( page, 'A02' );
		const a02 = page.locator( '.pkiw-exercise' );
		expect( await a02.locator( '.pkiw-exercise__distance, .pkiw-exercise__duration, .pkiw-exercise__splits, .pkiw-exercise__best' ).count() ).toBe( 0 );
		expect( await page.locator( 'main' ).innerText() ).not.toMatch( /7\.77|Route hidden|Public workout details enabled|\bPublic\b|\bWhere\b/i );
		expect( await page.locator( 'main :is(.map, [class*="route"], [class*="lock"], [class*="globe"])' ).count() ).toBe( 0 );
		await openSingle( page, 'A04' );
		await expect( page.locator( '.pkiw-exercise__duration' ) ).not.toBeEmpty();
		expect( await page.locator( '.pkiw-exercise__distance, .pkiw-exercise__splits' ).count() ).toBe( 0 );
		await openSingle( page, 'B05' );
		expect( await page.locator( '.pkiw-exercise__best' ).count() ).toBe( 0 );
	} );

	test( 'A05 untitled single has one date H1 with no p-name and post navigation drops the clock', async ( { page } ) => {
		await openSingle( page, 'A05' );
		const h1 = page.locator( 'main h1' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1 ).toHaveText( 'Exercise, August 27, 2026' );
		expect( await h1.evaluate( ( node ) => node.classList.contains( 'p-name' ) ) ).toBe( false );
		await openSingle( page, 'A04' );
		const nav = page.locator( 'nav.cr-post-nav' );
		await expect( nav ).toHaveCount( 1 );
		const navLinks = nav.locator( 'a[href]' );
		await expect( navLinks ).toHaveCount( 2 );
		expect( ( await navLinks.evaluateAll( ( links ) => links.map( ( link ) => link.href ) ) ).every( ( href ) => href.includes( 'cr-seed-245-' ) ), 'both neighbours remain exercise fixtures' ).toBe( true );
		expect( ( await navLinks.evaluateAll( ( links ) => links.map( ( link ) => link.getBoundingClientRect().height ) ) ).every( ( height ) => height >= 44 ), 'both post-nav chips are 44px high' ).toBe( true );
		const a05 = nav.locator( `a[href*="${ slugOf( 'A05' ) }"]` );
		await expect( a05 ).toHaveCount( 1 );
		await expect( a05 ).not.toContainText( ' at ' );
		const placement = await nav.evaluate( ( node ) => {
			const comments = document.querySelector( '.single-post__comments-wrapper' );
			return ! comments || !! ( comments.compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING );
		} );
		expect( placement ).toBe( true );
	} );

	test( 'single h-entry has one activity property and the record keeps date-only health mf2', async ( { page } ) => {
		await openSingle( page, 'A01' );
		const card = page.locator( '.pkiw-exercise' );
		const entry = page.locator( '.h-entry' ).filter( { has: card } ).first();
		const report = await mf2Report( entry );
		expect( report.entry ).toBe( true );
		expect( report.urls.length ).toBeGreaterThan( 0 );
		expect( report.published.length ).toBeGreaterThan( 0 );
		expect( report.authors ).toBeGreaterThan( 0 );
		expect( report.exercise ).toEqual( [ 'Run' ] );
		expect( await entry.locator( '.p-exercise' ).count() ).toBe( 1 );
	} );

	test( 'single bib uses pseudo-element grommets, a solid marker and responsive side-by-side then stacked layout', async ( { page } ) => {
		for ( const [ width, sideBySide ] of [ [ 1440, true ], [ 800, false ] ] ) {
			await openSingle( page, 'A01', width );
			const card = page.locator( '.pkiw-exercise' );
			const geometry = await card.evaluate( ( node ) => {
				const band = node.querySelector( '.pkiw-exercise__band' ).getBoundingClientRect();
				const table = node.querySelector( '.pkiw-exercise__splits' ).getBoundingClientRect();
				const fields = [ ...node.querySelectorAll( '.pkiw-exercise__distance, .pkiw-exercise__duration, .pkiw-exercise__best, .pkiw-exercise__place' ) ].map( ( field ) => field.getBoundingClientRect() );
				return {
					band: band.toJSON(),
					table: table.toJSON(),
					bibBottom: Math.max( band.bottom, ...fields.map( ( field ) => field.bottom ) ),
					bottomGrommets: getComputedStyle( node, '::after' ).content,
					topGrommets: getComputedStyle( node.querySelector( '.pkiw-exercise__band' ), '::before' ).content,
					backgroundImage: getComputedStyle( node ).backgroundImage,
				};
			} );
			expect( geometry.bottomGrommets ).not.toBe( 'none' );
			expect( geometry.topGrommets ).not.toBe( 'none' );
			expect( geometry.backgroundImage, 'no scenery on the bib' ).toBe( 'none' );
			if ( sideBySide ) {
				expect( geometry.table.left ).toBeGreaterThanOrEqual( geometry.band.right );
			} else {
				expect( geometry.table.top ).toBeGreaterThanOrEqual( geometry.bibBottom );
			}
		}
		const marker = await page.locator( 'main h1' ).evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { style: style.borderBottomStyle, width: style.borderBottomWidth, image: style.backgroundImage };
		} );
		expect( marker.style ).toBe( 'solid' );
		expect( Number.parseFloat( marker.width ) ).toBeGreaterThan( 0 );
		expect( marker.image ).toBe( 'none' );
	} );

	test( 'sprig drops below 40rem and 320 or 400 percent zoom has no horizontal scroll', async ( { page } ) => {
		await openSingle( page, 'A01', 800 );
		expect( await page.locator( 'main.single-post' ).evaluate( ( node ) => getComputedStyle( node, '::after' ).backgroundImage ) ).toContain( 'exercise-sprig.svg' );
		for ( const width of [ 375, 320 ] ) {
			await openSingle( page, 'A01', width );
			expect( await page.locator( 'main.single-post' ).evaluate( ( node ) => getComputedStyle( node, '::after' ).backgroundImage ) ).toBe( 'none' );
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ), `${ width }px overflow` ).toBeLessThanOrEqual( 0 );
		}
	} );

	test( 'S03 keeps its photo after the card in the post content while activity appears once', async ( { page } ) => {
		await openSingle( page, 'S03' );
		// The Micropub builder prints the card first and the authored content and photo after it. The photo
		// belongs to the single only (the archive, medal row and Stream card print none).
		const content = page.locator( '.single-post__content' ).first();
		await expect( content.locator( '.pkiw-exercise' ) ).toHaveCount( 1 );
		await expect( content.locator( 'img' ) ).toHaveCount( 1 );
		const order = await content.evaluate( ( root ) => {
			const card = root.querySelector( '.pkiw-exercise' );
			const image = root.querySelector( 'img' );
			return { photoAfterCard: !! ( card.compareDocumentPosition( image ) & Node.DOCUMENT_POSITION_FOLLOWING ), cardInBody: !! card.closest( '.e-content' ) };
		} );
		expect( order.photoAfterCard ).toBe( true );
		expect( order.cardInBody, 'the card is the record, not authored body text' ).toBe( false );
		expect( await page.locator( '.h-entry .p-exercise' ).count(), 'p-exercise appears once in the entry' ).toBe( 1 );
	} );

	test( 'single has no sample content and preserves full authored content after the record', async ( { page } ) => {
		await openSingle( page, 'S01' );
		expect( await page.locator( 'main' ).innerText() ).not.toContain( 'Sample content' );
		const order = await page.locator( 'main' ).evaluate( ( root ) => {
			const card = root.querySelector( '.pkiw-exercise' );
			const content = root.querySelector( '.e-content' );
			return ! content || !! ( card.compareDocumentPosition( content ) & Node.DOCUMENT_POSITION_FOLLOWING ) || !! card.closest( '.e-content' );
		} );
		expect( order ).toBe( true );
	} );

	test( 'single dark mode keeps the bib light and makes the split table a night surface with a hard yellow shadow', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		await openSingle( page, 'A01' );
		const paint = await page.locator( '.pkiw-exercise' ).evaluate( ( card ) => {
			const paper = getComputedStyle( card, '::before' );
			const table = getComputedStyle( card.querySelector( '.pkiw-exercise__splits' ) );
			const head = getComputedStyle( card.querySelector( 'thead' ) );
			return { paper: paper.backgroundColor, ink: getComputedStyle( card ).color, shadow: paper.boxShadow, table: table.backgroundColor, tableInk: table.color, head: head.backgroundColor };
		} );
		expect( paint.paper ).toBe( IVORY );
		expect( paint.ink ).toBe( VIOLET );
		expect( paint.shadow ).toContain( YELLOW );
		expect( paint.table ).toBe( NIGHT );
		expect( paint.tableInk ).not.toBe( VIOLET );
		expect( paint.head ).not.toBe( 'rgba(0, 0, 0, 0)' );
	} );

	test( 'single forced colors outlines bib and cells and removes all grommets and sprig', async ( { page } ) => {
		await openSingle( page, 'A01' );
		await page.emulateMedia( { forcedColors: 'active' } );
		const report = await page.locator( '.pkiw-exercise' ).evaluate( ( card ) => ( {
			bib: `${ getComputedStyle( card, '::before' ).borderTopWidth } ${ getComputedStyle( card, '::before' ).borderTopStyle }`,
			table: `${ getComputedStyle( card.querySelector( 'table' ) ).borderTopWidth } ${ getComputedStyle( card.querySelector( 'table' ) ).borderTopStyle }`,
			cell: getComputedStyle( card.querySelector( 'td' ) ).borderTopWidth,
			bottom: getComputedStyle( card, '::after' ).display,
			top: getComputedStyle( card.querySelector( '.pkiw-exercise__band' ), '::before' ).display,
			sprig: getComputedStyle( document.querySelector( 'main.single-post' ), '::after' ).display,
		} ) );
		expect( report.bib ).toBe( '2px solid' );
		expect( report.table ).toBe( '2px solid' );
		expect( report.cell ).toBe( '1px' );
		expect( [ report.bottom, report.top, report.sprig ] ).toEqual( [ 'none', 'none', 'none' ] );
	} );
} );

// --- Stream ------------------------------------------------------------------------------------

test.describe( 'exercise Stream', () => {
	for ( const streamPath of STREAM_PATHS ) {
		test( `${ streamPath } shows S01-S03 as one-link exercise bib corners with tiered content`, async ( { page } ) => {
			await open( page, streamPath, 1440 );
			const found = await page.locator( `a[href*="cr-seed-245-s0"]` ).count();
			test.skip( 0 === found, `S01-S03 do not appear on ${ streamPath }` );
			for ( const key of [ 'S01', 'S02', 'S03' ] ) {
				const item = itemFor( page, key );
				const card = item.locator( 'article.pk-card.pk-card--stream.k-exercise.cr-bib-corner.h-entry' );
				await expect( card, `${ key } card` ).toHaveCount( 1 );
				await expect( card.locator( '.cr-bib-corner__kind' ) ).toHaveText( 'Exercise' );
				expect( await card.locator( '.cr-bib-corner__title' ).evaluate( ( node ) => getComputedStyle( node ).fontFamily ), `${ key } title voice` ).toContain( 'Roboto Slab' );
				const report = await linkReport( card );
				expect( report.hrefs ).toHaveLength( 1 );
				expect( report.stray ).toBe( 0 );
				expect( await card.locator( 'button, .pk-cta, img' ).count() ).toBe( 0 );
				expect( await card.locator( '.pkiw-exercise__duration, .pkiw-exercise__splits, .pkiw-exercise__best, .pkiw-exercise__place, .pk-note, .pk-excerpt' ).count(), `${ key } has no duration, splits, best, place or excerpt` ).toBe( 0 );
				expect( await card.locator( '.cr-bib-corner__activity.p-exercise' ).count() ).toBe( 1 );
				expect( await card.locator( '.cr-bib-corner__pin[aria-hidden="true"]' ).count() ).toBe( 1 );
				const mf2 = await mf2Report( card );
				expect( mf2.entry ).toBe( true );
				expect( mf2.urls.length ).toBeGreaterThan( 0 );
				expect( mf2.authors ).toBeGreaterThan( 0 );
				expect( mf2.published[ 0 ] ).toMatch( /^\d{4}-\d{2}-\d{2}$/ );
				expect( mf2.exercise ).toHaveLength( 1 );
			}
			await expect( itemFor( page, 'S01' ).locator( '.cr-bib-corner__title' ) ).toHaveText( 'Harbor Run' );
			await expect( itemFor( page, 'S01' ).locator( '.cr-bib-corner__activity' ) ).toHaveText( 'Run' );
			await expect( itemFor( page, 'S01' ).locator( '.cr-bib-corner__distance' ) ).toHaveText( '8.00 km' );
			await expect( itemFor( page, 'S02' ).locator( '.cr-bib-corner__title' ) ).toHaveText( 'Harbor Walk' );
			await expect( itemFor( page, 'S02' ).locator( '.cr-bib-corner__activity' ) ).toHaveText( 'Walk' );
			expect( await itemFor( page, 'S02' ).locator( '.cr-bib-corner__distance' ).count() ).toBe( 0 );
			const s03 = itemFor( page, 'S03' ).locator( '.cr-bib-corner' );
			await expect( s03.locator( '.cr-bib-corner__activity' ) ).toHaveText( 'Row' );
			const visibleLinkText = await s03.locator( '.cr-bib-corner__link' ).evaluate( ( link ) => {
				const copy = link.cloneNode( true );
				copy.querySelectorAll( '.screen-reader-text' ).forEach( ( node ) => node.remove() );
				return copy.textContent.trim();
			} );
			expect( visibleLinkText ).toMatch( /^[A-Z][a-z]+ \d{1,2}, 2026$/ );
			expect( await s03.locator( '.cr-bib-corner__title.p-name, .cr-bib-corner__link.p-name' ).count() ).toBe( 0 );
		} );
	}

	test( 'Stream shadow is on the li, torn clip is on the article, and pin plus two pseudo grommets sit inside', async ( { page } ) => {
		await open( page, STREAM_PATHS[ 0 ], 1440 );
		const item = itemFor( page, 'S01' );
		test.skip( 0 === await item.count(), 'S01 is not on this Stream page' );
		const card = item.locator( '.cr-bib-corner' );
		const paint = await Promise.all( [
			item.evaluate( ( node ) => ( { filter: getComputedStyle( node ).filter } ) ),
			card.evaluate( ( node ) => ( {
				filter: getComputedStyle( node ).filter,
				clip: getComputedStyle( node ).clipPath,
				before: getComputedStyle( node, '::before' ).content,
				after: getComputedStyle( node, '::after' ).content,
				pin: node.querySelectorAll( ':scope > .cr-bib-corner__pin' ).length,
			} ) ),
		] );
		expect( paint[ 0 ].filter ).toContain( 'drop-shadow' );
		const lengths = [ ...paint[ 0 ].filter.matchAll( /-?(?:\d+\.?\d*|\.\d+)px/g ) ].map( ( match ) => Number.parseFloat( match[ 0 ] ) );
		expect( lengths[ 2 ], 'drop-shadow blur radius' ).toBe( 0 );
		expect( paint[ 1 ].filter ).toBe( 'none' );
		expect( paint[ 1 ].clip ).toContain( 'polygon' );
		expect( paint[ 1 ].before ).not.toBe( 'none' );
		expect( paint[ 1 ].after ).not.toBe( 'none' );
		expect( paint[ 1 ].pin ).toBe( 1 );
	} );

	test( 'Stream card uses one rotation token, 80-92 percent width and stays shorter than the single record', async ( { page } ) => {
		await open( page, STREAM_PATHS[ 0 ], 1440 );
		const card = itemFor( page, 'S01' ).locator( '.cr-bib-corner' );
		test.skip( 0 === await card.count(), 'S01 is not on this Stream page' );
		const geometry = await card.evaluate( ( node ) => {
			const parent = node.parentElement;
			const style = getComputedStyle( parent );
			const inner = parent.clientWidth - Number.parseFloat( style.paddingLeft ) - Number.parseFloat( style.paddingRight );
			return { ratio: node.offsetWidth / inner, height: node.offsetHeight, transform: getComputedStyle( node ).transform };
		} );
		expect( geometry.ratio ).toBeGreaterThanOrEqual( 0.8 );
		expect( geometry.ratio ).toBeLessThanOrEqual( 0.92 );
		const degree = degreeFromMatrix( geometry.transform );
		expect( degree ).not.toBeNull();
		expect( TILT_DEGREES.some( ( token ) => Math.abs( token - degree ) < 0.15 ) ).toBe( true );
		await openSingle( page, 'A01', 1440 );
		const singleHeight = ( await page.locator( '.pkiw-exercise' ).boundingBox() ).height;
		expect( geometry.height ).toBeLessThan( singleHeight );
	} );

	test( 'whole Stream card is the hit area and focus rings it once', async ( { page } ) => {
		await open( page, STREAM_PATHS[ 0 ], 1440 );
		const card = itemFor( page, 'S01' ).locator( '.cr-bib-corner' );
		test.skip( 0 === await card.count(), 'S01 is not on this Stream page' );
		const corners = await card.evaluate( ( root ) => {
			const link = root.querySelector( 'a[href]' );
			const box = root.getBoundingClientRect();
			const at = ( fx, fy ) => document.elementFromPoint( box.left + box.width * fx, box.top + box.height * fy )?.closest( 'a' ) === link;
			return [ at( 0.2, 0.25 ), at( 0.8, 0.25 ), at( 0.2, 0.65 ), at( 0.8, 0.65 ) ];
		} );
		expect( corners ).toEqual( [ true, true, true, true ] );
		await card.locator( 'a' ).focus();
		const ring = await card.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { width: style.outlineWidth, color: style.outlineColor, shadow: style.boxShadow, target: Math.min( node.getBoundingClientRect().width, node.getBoundingClientRect().height ) };
		} );
		expect( ring.width ).toBe( '3px' );
		expect( ring.color ).toBe( YELLOW );
		expect( ring.shadow ).toMatch( /7px/ );
		expect( ring.target ).toBeGreaterThanOrEqual( 44 );
	} );

	test( 'Stream hides privacy state and connector UI while craft control keeps its own card', async ( { page } ) => {
		await open( page, STREAM_PATHS[ 0 ], 1440 );
		const s02 = itemFor( page, 'S02' );
		test.skip( 0 === await s02.count(), 'S02 is not on this Stream page' );
		const scan = await s02.evaluate( ( root ) => ( {
			text: root.innerText,
			attributes: [ ...root.querySelectorAll( '*' ) ].flatMap( ( node ) => [ node.className || '', node.getAttribute( 'title' ) || '' ] ).join( ' ' ),
		} ) );
		expect( visibilityLeaks( scan.text, scan.attributes ), 'no text, class or title marks S02 hidden, private or public' ).toEqual( [] );
		expect( await s02.locator( '[class*="timeline"], [class*="connector"], [class*="date-circle"]' ).count() ).toBe( 0 );
		const craft = itemFor( page, 'K01' );
		if ( await craft.count() ) {
			expect( await craft.locator( '.cr-bib-corner, .k-exercise' ).count() ).toBe( 0 );
			expect( await craft.locator( '.k-craft' ).count() ).toBeGreaterThan( 0 );
		}
	} );

	test( 'Stream kind label holds 4.5 to 1 and dark mode keeps paper, band text and yellow li shadow', async ( { page }, testInfo ) => {
		await open( page, STREAM_PATHS[ 0 ], 1440 );
		const item = itemFor( page, 'S01' );
		const card = item.locator( '.cr-bib-corner' );
		test.skip( 0 === await card.count(), 'S01 is not on this Stream page' );
		const paint = await card.evaluate( ( node ) => {
			const label = node.querySelector( '.cr-bib-corner__kind' );
			return {
				paper: getComputedStyle( node ).backgroundColor,
				ink: getComputedStyle( node ).color,
				label: getComputedStyle( label ).color,
				band: getComputedStyle( label ).backgroundColor,
			};
		} );
		expect( paint.paper ).toBe( IVORY );
		expect( paint.ink ).toBe( VIOLET );
		expect( contrast( paint.label, paint.band ) ).toBeGreaterThanOrEqual( 4.5 );
		if ( isDark( testInfo ) ) {
			expect( await item.evaluate( ( node ) => getComputedStyle( node ).filter ) ).toContain( YELLOW );
			expect( paint.label ).toBe( IVORY );
		}
	} );

	test( 'Stream forced colors gives a 2px outline and removes pin and both grommets', async ( { page } ) => {
		await open( page, STREAM_PATHS[ 0 ], 1440 );
		const item = itemFor( page, 'S01' );
		const card = item.locator( '.cr-bib-corner' );
		test.skip( 0 === await card.count(), 'S01 is not on this Stream page' );
		await page.emulateMedia( { forcedColors: 'active' } );
		const report = await card.evaluate( ( node ) => ( {
			border: `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }`,
			clip: getComputedStyle( node ).clipPath,
			before: getComputedStyle( node, '::before' ).display,
			after: getComputedStyle( node, '::after' ).display,
			pin: getComputedStyle( node.querySelector( '.cr-bib-corner__pin' ) ).display,
			filter: getComputedStyle( node.closest( 'li.wp-block-post' ) ).filter,
		} ) );
		expect( report.border ).toBe( '2px solid' );
		expect( report.clip ).toBe( 'none' );
		expect( [ report.before, report.after, report.pin ] ).toEqual( [ 'none', 'none', 'none' ] );
		expect( report.filter ).toBe( 'none' );
	} );

	test( 'Stream cards wrap in one 320px column without page overflow', async ( { page } ) => {
		await open( page, STREAM_PATHS[ 0 ], 320 );
		const cards = page.locator( 'article.cr-bib-corner' );
		test.skip( 0 === await cards.count(), 'no exercise cards on this Stream page' );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		const failures = await cards.evaluateAll( ( nodes ) => nodes.filter( ( node ) => node.getBoundingClientRect().right > innerWidth + 1 || node.scrollWidth > node.clientWidth + 1 ).length );
		expect( failures ).toBe( 0 );
	} );
} );

// --- Binding acceptance A1 ---------------------------------------------------------------------

test.describe( 'promoted exercise Blog and search surfaces', () => {
	// Exercise posts live on the Stream. A promoted post (pkiw_promote) is a Blog post, so it appears on the posts
	// index and in logged-out search with the Blog card: no hidden value, no place, no image. The Blog lists newest
	// first with a small page, so the August fixtures sit several pages in: walk the pages until the fixture shows.
	async function findOnBlog( page, key ) {
		const base = BLOG.replace( /\/?$/, '/' );
		const maxPages = Number( process.env.CR_EXERCISE_BLOG_PAGES || 20 );
		for ( let at = 1; at <= maxPages; at++ ) {
			const response = await open( page, 1 === at ? base : `${ base }page/${ at }/`, 1440 );
			if ( response?.status() === 404 ) {
				return null;
			}
			expect( await page.locator( `a[href*="${ slugOf( 'A03' ) }"]` ).count(), `A03 was never promoted, so it stays off the Blog (page ${ at })` ).toBe( 0 );
			const item = await listingItem( page, key );
			if ( item ) {
				return item;
			}
		}
		return null;
	}

	test( 'A02 titled promotion renders on the Blog and in search with no hidden value, place or image', async ( { page } ) => {
		const surfaces = [ [ 'Blog', await findOnBlog( page, 'A02' ) ] ];
		await open( page, '/?s=Evening+Walk', 1440 );
		surfaces.push( [ 'search', await listingItem( page, 'A02' ) ] );
		test.skip( surfaces.every( ( [ , item ] ) => null === item ), 'seed-245.php promote has not been run: A02 was not found on the Blog or in search' );
		for ( const [ name, item ] of surfaces ) {
			expect( item, `A02 appears on the ${ name }` ).not.toBeNull();
			const text = await item.innerText();
			expect( text, `${ name } hidden distance` ).not.toContain( '7.77' );
			expect( text, `${ name } no place` ).not.toMatch( /Maplewood Track|Fernvale|Example Lane/ );
			expect( await item.locator( 'img' ).count(), `${ name } no image` ).toBe( 0 );
		}
		// The never-promoted twin stays on the Stream, out of search.
		await open( page, '/?s=Evening+Walk', 1440 );
		expect( await page.locator( `a[href*="${ slugOf( 'A03' ) }"]` ).count(), 'A03 is not in search' ).toBe( 0 );
	} );

	test( 'A05 untitled promotion shows Entry from <date> with u-url and no p-name on the Blog and in search', async ( { page } ) => {
		// The plan calls A02 untitled, but A02 is titled Evening Walk. seed-245.php promote flags A02 and A05, so the
		// untitled half of binding acceptance A1 runs on A05.
		const surfaces = [ [ 'Blog', await findOnBlog( page, 'A05' ) ] ];
		await open( page, '/?s=Bike+ride', 1440 );
		surfaces.push( [ 'search', await listingItem( page, 'A05' ) ] );
		test.skip( surfaces.every( ( [ , item ] ) => null === item ), 'seed-245.php promote has not been run: A05 was not found on the Blog or in search' );
		for ( const [ name, item ] of surfaces ) {
			expect( item, `A05 appears on the ${ name }` ).not.toBeNull();
			const link = item.locator( `a.u-url[href*="${ slugOf( 'A05' ) }"]` ).first();
			await expect( link, `${ name } untitled permalink` ).toHaveText( /^Entry from [A-Z][a-z]+ 27, 2026$/ );
			expect( await link.evaluate( ( node ) => node.classList.contains( 'p-name' ) || !! node.closest( '.p-name' ) ), `${ name } no p-name` ).toBe( false );
			expect( await item.locator( 'img' ).count(), `${ name } no image` ).toBe( 0 );
		}
	} );
} );
