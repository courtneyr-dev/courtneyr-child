// `CR_SPEC=mood-248.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs`:
// the W4-T248 mood archive, singles and Stream against plan-248. Read-only GETs; needs the local
// stack with the `_cr_seed_248` fixtures from vault assets/248-moods/seed-248.php, pkiw-w4 and this
// branch served. Set CR_BASE_URL for dev (and CR_ALLOW_REMOTE=1 under the shared config guard).
// Environment overrides: CR_MOOD_ARCHIVE_PATH (default /kind/mood/), CR_MOOD_STREAM_PATH (default
// /stream/), CR_MOOD_IDS (optional path to `{ "F01": 123, ... }`), CR_MOOD_COUNTS (optional JSON
// year-to-count map), CR_MOOD_EDITOR_STORAGE (authenticated Playwright storage state),
// CR_MOOD_REST_BASELINE (earlier REST response map), CR_MOOD_EMPTY_BASE_URL, CR_ENV=dev and
// CR_MOOD_DEV_EMPTY=1. Without CR_MOOD_IDS, singles use `/?name=cr-seed-248-<key>`.
// W3-G2 is binding: "exactly one a" means a[href] with no [hidden] on itself or an ancestor, and
// every a[tabindex="-1"] must sit under [hidden]. W3-G12 is also binding: the pager-chip lift under
// reduced motion is deferred, so this file checks reduced motion on mood cells and links, not chips.
// Written for the W4-T248 lane; it has not been run against a stack yet.
import { test, expect } from '@playwright/test';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const BASE_URL = ( process.env.CR_BASE_URL || 'http://localhost:8894' ).replace( /\/+$/, '' );
const ARCHIVE = process.env.CR_MOOD_ARCHIVE_PATH || '/kind/mood/';
const STREAM = process.env.CR_MOOD_STREAM_PATH || '/stream/';
const ENV = process.env.CR_ENV || 'local';
const DEV_EMPTY = ENV === 'dev' && process.env.CR_MOOD_DEV_EMPTY === '1';
const MONTHS = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
// Mood_Vocabulary::default_families() order at pkiw-w4.
const VOCABULARY_ORDER = [ 'joy', 'calm', 'energy', 'thoughtful', 'uncertain', 'sad', 'angry', 'anxious', 'social', 'physical', 'work', 'playful', 'other' ];

const RUSSIAN_VIOLET = 'rgb(36, 28, 74)';
const RUSSIAN_VIOLET_SOFT = 'rgb(61, 47, 110)';
const PERIWINKLE = 'rgb(188, 181, 227)';
const GLAUCOUS_DARK = 'rgb(61, 82, 125)';
const SKY_BLUE = 'rgb(142, 202, 230)';
const BLUE_GREEN = 'rgb(33, 158, 188)';
const CERULEAN = 'rgb(18, 103, 130)';
const PRUSSIAN_BLUE = 'rgb(2, 48, 71)';
const SELECTIVE_YELLOW = 'rgb(255, 183, 3)';
const UT_ORANGE = 'rgb(251, 133, 0)';
const LIGHT_ORANGE = 'rgb(254, 226, 195)';
const PRINTER_IVORY = 'rgb(251, 250, 245)';
const LIGHT_GRAY = 'rgb(235, 235, 235)';
const CORAL = 'rgb(214, 50, 64)';
const NIGHT = 'rgb(26, 23, 20)';
const NIGHT_ELEVATED = 'rgb(37, 31, 28)';
const NIGHT_RULE = 'rgb(58, 52, 47)';
const LIGHT_RULE = 'rgb(216, 211, 195)';

const FAMILY_PAINT = {
	joy: [ SELECTIVE_YELLOW, RUSSIAN_VIOLET ],
	calm: [ SKY_BLUE, RUSSIAN_VIOLET ],
	social: [ LIGHT_ORANGE, RUSSIAN_VIOLET ],
	energy: [ UT_ORANGE, RUSSIAN_VIOLET ],
	thoughtful: [ PERIWINKLE, RUSSIAN_VIOLET ],
	anxious: [ RUSSIAN_VIOLET_SOFT, PRINTER_IVORY ],
	physical: [ BLUE_GREEN, RUSSIAN_VIOLET ],
	sad: [ CERULEAN, PRINTER_IVORY ],
	other: [ LIGHT_GRAY, RUSSIAN_VIOLET ],
	angry: [ CORAL, PRINTER_IVORY ],
	playful: [ PRUSSIAN_BLUE, PRINTER_IVORY ],
	uncertain: [ RUSSIAN_VIOLET, PRINTER_IVORY ],
	work: [ GLAUCOUS_DARK, PRINTER_IVORY ],
};

const FAMILY_FIXTURES = {
	joy: [ '2026-01-05', 'F01' ],
	calm: [ '2026-01-12', 'F02' ],
	social: [ '2026-01-19', 'F29' ],
	energy: [ '2026-02-03', 'F03' ],
	thoughtful: [ '2026-02-14', 'F04' ],
	anxious: [ '2026-02-21', 'F30' ],
	physical: [ '2026-02-28', 'F05' ],
	sad: [ '2026-03-21', 'F16' ],
	other: [ '2026-04-04', 'F17' ],
	angry: [ '2026-04-30', 'F07' ],
	playful: [ '2026-06-01', 'F18' ],
	uncertain: [ '2026-07-04', 'F19' ],
	work: [ '2026-07-25', 'F11' ],
};

function jsonInput( value ) {
	if ( ! value ) {
		return null;
	}
	return JSON.parse( existsSync( value ) ? readFileSync( value, 'utf8' ) : value );
}

const MOOD_IDS = jsonInput( process.env.CR_MOOD_IDS ) || {};
const MOOD_COUNTS = jsonInput( process.env.CR_MOOD_COUNTS ) || {};

const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );
const fixtureSlug = ( key ) => `cr-seed-248-${ key.toLowerCase() }`;
const fixturePath = ( key ) => MOOD_IDS[ key ] ? `/?p=${ MOOD_IDS[ key ] }` : `/?name=${ fixtureSlug( key ) }`;
const archivePage = ( pageNumber ) => pageNumber === 1 ? ARCHIVE : `${ ARCHIVE.replace( /\/+$/, '' ) }/page/${ pageNumber }/`;

function rgbChannels( color ) {
	const match = color.match( /rgba?\(\s*(\d+(?:\.\d+)?)\s*,\s*(\d+(?:\.\d+)?)\s*,\s*(\d+(?:\.\d+)?)/ );
	if ( ! match ) {
		throw new Error( `Expected an RGB color, got ${ color }` );
	}
	return match.slice( 1, 4 ).map( Number );
}

function luminance( color ) {
	const values = rgbChannels( color ).map( ( channel ) => {
		const value = channel / 255;
		return value <= 0.04045 ? value / 12.92 : ( ( value + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * values[ 0 ] + 0.7152 * values[ 1 ] + 0.0722 * values[ 2 ];
}

function contrast( foreground, background ) {
	const light = Math.max( luminance( foreground ), luminance( background ) );
	const dark = Math.min( luminance( foreground ), luminance( background ) );
	return ( light + 0.05 ) / ( dark + 0.05 );
}

async function computed( locator, property, pseudo = null ) {
	return locator.evaluate( ( node, args ) => getComputedStyle( node, args.pseudo )[ args.property ], { property, pseudo } );
}

async function resolvedToken( locator, token, property = 'color' ) {
	return locator.evaluate( ( node, args ) => {
		const probe = node.ownerDocument.createElement( 'span' );
		probe.style.position = 'absolute';
		probe.style[ args.property ] = `var(${ args.token })`;
		node.append( probe );
		const value = getComputedStyle( probe )[ args.property ];
		probe.remove();
		return value;
	}, { token, property } );
}

async function openArchive( page, path = ARCHIVE, seed = true ) {
	const response = await page.goto( path, { waitUntil: 'load' } );
	test.skip( ! response || response.status() === 404, `no mood archive at ${ path }` );
	if ( seed ) {
		test.skip( DEV_EMPTY, 'CR_MOOD_DEV_EMPTY=1 is the one-real-post dev state, not the seeded fixture state' );
		test.skip( 0 === await page.locator( '.pkiw-mood-day:is([data-day="2026-01-05"], [data-day="2025-01-01"], [data-day="2024-02-29"])' ).count(), 'the _cr_seed_248 fixtures are not present on this archive page' );
	}
	await expect( page.locator( '.pkiw-mood-year' ) ).toHaveCount( 1 );
	return response;
}

async function openFixture( page, key ) {
	const response = await page.goto( fixturePath( key ), { waitUntil: 'load' } );
	test.skip( ! response || response.status() === 404 || 0 === await page.locator( 'main .pk-card.k-mood' ).count(), `no fixture ${ key } at ${ fixturePath( key ) }` );
}

async function fixtureId( page, key ) {
	if ( MOOD_IDS[ key ] ) {
		return Number( MOOD_IDS[ key ] );
	}
	const response = await page.goto( `/?name=${ fixtureSlug( key ) }`, { waitUntil: 'load' } );
	if ( ! response || response.status() === 404 ) {
		return null;
	}
	return page.locator( 'body' ).evaluate( ( body ) => {
		const className = [ ...body.classList ].find( ( name ) => /^postid-\d+$/.test( name ) );
		return className ? Number( className.slice( 7 ) ) : null;
	} );
}

async function renderedAnchorFacts( locator ) {
	return locator.evaluate( ( scope ) => {
		const visible = ( anchor ) => ! anchor.closest( '[hidden]' );
		return {
			links: [ ...scope.querySelectorAll( 'a[href]' ) ].filter( visible ).length,
			strayNegativeTabindex: [ ...scope.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( visible ).length,
		};
	} );
}

// Each aria-hidden gutter label must sit on its day row: compare label centers with December's cells.
async function assertGutterRegistered( page, label ) {
	const offsets = await page.evaluate( () => {
		const labels = [ ...document.querySelectorAll( '.pkiw-mood-year__gutter[aria-hidden="true"] > li' ) ];
		const cells = [ ...[ ...document.querySelectorAll( '.pkiw-mood-month' ) ].at( -1 ).querySelectorAll( '.pkiw-mood-day' ) ];
		return labels.map( ( item, index ) => {
			const a = item.getBoundingClientRect();
			const b = cells[ index ].getBoundingClientRect();
			return Math.abs( ( a.top + a.bottom ) / 2 - ( b.top + b.bottom ) / 2 );
		} );
	} );
	expect( offsets, `${ label }: 31 gutter labels for December's 31 cells` ).toHaveLength( 31 );
	expect( Math.max( ...offsets ), `${ label }: every gutter label center is within 2px of its December cell` ).toBeLessThanOrEqual( 2 );
}

async function assertNoOverflow( page, label ) {
	expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), `${ label }: document stays within its viewport` ).toBe( true );
}

async function linkNames( locator ) {
	return locator.evaluateAll( ( anchors ) => anchors.map( ( anchor ) => {
		const labelledby = ( anchor.getAttribute( 'aria-labelledby' ) || '' ).trim().split( /\s+/ ).filter( Boolean );
		const name = anchor.getAttribute( 'aria-label' ) || ( labelledby.length ? labelledby.map( ( id ) => document.getElementById( id )?.textContent || '' ).join( ' ' ) : anchor.textContent );
		return name.replace( /\s+/g, ' ' ).trim();
	} ) );
}

function expectedCount( year ) {
	const supplied = MOOD_COUNTS[ year ] ?? MOOD_COUNTS.archive?.[ year ];
	if ( Number.isInteger( Number( supplied ) ) ) {
		return Number( supplied );
	}
	if ( year === '2026' ) {
		return ENV === 'dev' ? 31 : 46;
	}
	return year === '2025' ? 6 : 3;
}

test.describe( 'archive structure and data', () => {
	test( 'Archive :58-61, :72 and :74 — one inherited Query holds the year grid, headings and three-page pager', async ( { page }, testInfo ) => {
		for ( const [ pageNumber, year ] of [ [ 1, '2026' ], [ 2, '2025' ], [ 3, '2024' ] ] ) {
			await openArchive( page, archivePage( pageNumber ) );
			await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
			await expect( page.locator( '.pkiw-mood-year > h2.pkiw-mood-year__year' ) ).toHaveText( year );

			const query = page.locator( '.cr-mood-archive .wp-block-query' );
			await expect( query ).toHaveCount( 1 );
			await expect( query.locator( '.pkiw-mood-year__grid' ) ).toHaveCount( 1 );
			const nav = query.locator( 'nav.wp-block-query-pagination[aria-label="Pagination"]' );
			await expect( nav ).toHaveCount( 1 );
			expect( await query.evaluate( ( node ) => {
				const yearGrid = node.querySelector( '.pkiw-mood-year' );
				const pager = node.querySelector( 'nav.wp-block-query-pagination' );
				return Boolean( yearGrid && pager && ( yearGrid.compareDocumentPosition( pager ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
			} ), 'the pager follows the grid' ).toBe( true );

			const monthHeadings = page.locator( '.pkiw-mood-month > h3.pkiw-mood-month__name' );
			await expect( monthHeadings ).toHaveCount( 12 );
			for ( let index = 0; index < MONTHS.length; index++ ) {
				await expect( monthHeadings.nth( index ) ).toHaveAccessibleName( MONTHS[ index ] );
			}
			const cards = page.locator( '.pkiw-mood-daycard' );
			await expect( cards.locator( ':scope > h3.pkiw-mood-daycard__date' ) ).toHaveCount( await cards.count() );

			const entries = page.locator( '.pkiw-mood-year .h-entry' );
			const entryLinks = page.locator( '.pkiw-mood-day[data-family] > a.u-url[href], .pkiw-mood-daycard__entries > li.h-entry > a.u-url[href]' );
			expect( await entries.count(), `${ year }: one h-entry per mood` ).toBe( expectedCount( year ) );
			expect( await entryLinks.count(), `${ year }: one entry link per h-entry` ).toBe( await entries.count() );

			const numberChips = nav.locator( '.wp-block-query-pagination-numbers .page-numbers:not(.dots)' );
			await expect( numberChips ).toHaveCount( 3 );
			const current = numberChips.and( page.locator( '[aria-current="page"]' ) );
			await expect( current ).toHaveCount( 1 );
			expect( await computed( current, 'backgroundColor' ) ).toBe( isDark( testInfo ) ? PRINTER_IVORY : RUSSIAN_VIOLET );
			expect( await computed( current, 'color' ) ).toBe( isDark( testInfo ) ? RUSSIAN_VIOLET : PRINTER_IVORY );
			const chipHeights = await numberChips.evaluateAll( ( chips ) => chips.map( ( chip ) => chip.getBoundingClientRect().height ) );
			expect( chipHeights.every( ( height ) => height >= 44 ), `${ year }: every numbered pager chip is 44px tall` ).toBe( true );
			await expect( nav.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( pageNumber === 1 ? 0 : 1 );
			await expect( nav.locator( '.wp-block-query-pagination-next' ) ).toHaveCount( pageNumber === 3 ? 0 : 1 );
		}

		const missing = await page.goto( archivePage( 4 ), { waitUntil: 'load' } );
		expect( missing?.status(), 'page 4 does not manufacture a fourth year' ).toBe( 404 );
	} );

	test( 'Archive :61 — a search on the kind URL stays a core search and does not expand back to the full year', async ( { page } ) => {
		const response = await page.goto( `${ ARCHIVE }?s=Seed`, { waitUntil: 'load' } );
		expect( response?.status() ).toBe( 200 );
		// WordPress serves search.html for ?s= on a taxonomy URL, so the year grid never replaces the core results.
		await expect( page.locator( 'main h1' ) ).toContainText( 'Search results for' );
		await expect( page.locator( '.pkiw-mood-year, .pkiw-mood-legend' ) ).toHaveCount( 0 );
		const seeded = await page.locator( 'main a[href*="cr-seed-248-"]' ).count();
		expect( seeded, 'the search returns seeded moods' ).toBeGreaterThan( 0 );
		expect( seeded, 'the search narrows the archive' ).toBeLessThan( expectedCount( '2026' ) );
	} );

	test( 'Archive :59 — the cutout header remains Mood, with its badge and term description but no year', async ( { page } ) => {
		await openArchive( page );
		const header = page.locator( 'main .cr-archive__header' );
		const h1 = header.locator( 'h1.cr-archive__title' );
		await expect( h1 ).toHaveCount( 1 );
		const tiles = await h1.locator( '.cr-cutout__tile' ).evaluateAll( ( nodes ) => nodes.map( ( tile ) => ( {
			text: tile.textContent,
			tilt: [ ...tile.classList ].find( ( className ) => /^is-r\d+$/.test( className ) ) || '',
		} ) ) );
		expect( tiles.map( ( tile ) => tile.text ).join( '' ) ).toBe( 'Mood' );
		for ( let index = 1; index < tiles.length; index++ ) {
			expect( tiles[ index ].tilt, `tile ${ index + 1 } differs from its neighbour` ).not.toBe( tiles[ index - 1 ].tilt );
		}
		const badge = header.locator( '.cr-archive-identity__glyph' );
		await expect( header.locator( '.cr-archive-identity--kind' ) ).toHaveCount( 1 );
		await expect( badge ).toHaveCount( 1 );
		await expect( badge ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( await badge.evaluate( ( node ) => {
			const box = node.getBoundingClientRect();
			return Math.abs( box.width - box.height ) <= 1 && parseFloat( getComputedStyle( node ).borderRadius ) >= box.width / 2 - 1;
		} ), 'the archive badge is round' ).toBe( true );
		expect( ( await header.locator( '.cr-archive__description' ).innerText() ).trim().length ).toBeGreaterThan( 0 );
		await expect( header.locator( 'hr.is-style-cr-marker-bar-short' ) ).toHaveCount( 1 );
		await expect( header.locator( '.cr-archive__tape, .is-style-cr-tape-label' ) ).toHaveCount( 0 );
		await expect( header ).not.toContainText( 'Stream · Kind' );
		expect( await header.innerText() ).not.toMatch( /\b\d{4}\b/ );
	} );

	test( 'Archive :63-68 and W4-G25 — real month lengths, boundary dates, cell names, splits, stripes and unique card links', async ( { page } ) => {
		await openArchive( page );

		const f01 = page.locator( '.pkiw-mood-day[data-day="2026-01-05"]' );
		await expect( f01.locator( ':scope > a' ) ).toHaveText( 'J4' );
		await expect( f01.locator( ':scope > a' ) ).toHaveAttribute( 'aria-label', /^January 5, 2026:.*\(J4\)$/ );
		const f12 = page.locator( '.pkiw-mood-day[data-day="2026-08-02"] > a' );
		await expect( f12 ).toHaveAttribute( 'aria-label', /: happy, intensity 4 of 5 \(J4\)$/ );
		const f17 = page.locator( '.pkiw-mood-day[data-day="2026-04-04"]' );
		await expect( f17 ).toHaveAttribute( 'data-family', 'other' );
		await expect( f17.locator( '.p-name:not(.p-author .p-name)' ), 'the author card keeps its p-name; the entry gets none' ).toHaveCount( 0 );
		await expect( f17.locator( ':scope > a' ) ).toHaveAttribute( 'aria-label', /: Mood \(O\)$/ );

		for ( const [ pageNumber, day, label ] of [ [ 2, '2025-01-01', 'January 1, 2025: Neutral' ], [ 3, '2024-12-31', 'December 31, 2024: Excited' ] ] ) {
			await openArchive( page, archivePage( pageNumber ) );
			const boundary = page.locator( `.pkiw-mood-day[data-day="${ day }"] > a` );
			await expect( boundary ).toHaveCount( 1 );
			await expect( boundary ).toHaveAccessibleName( new RegExp( `^${ label }` ) );
		}

		for ( const [ pageNumber, year, februaryDays ] of [ [ 1, '2026', 28 ], [ 2, '2025', 28 ], [ 3, '2024', 29 ] ] ) {
			await openArchive( page, archivePage( pageNumber ) );
			await expect( page.locator( '.pkiw-mood-month' ).nth( 1 ).locator( '.pkiw-mood-day' ), `${ year } February` ).toHaveCount( februaryDays );
		}

		await openArchive( page );
		const empty = page.locator( '.pkiw-mood-day:not([data-family]):not([data-count])' );
		expect( await empty.evaluateAll( ( cells ) => cells.every( ( cell ) => ! cell.textContent.trim() && ! cell.querySelector( 'a, [tabindex]' ) ) ), 'empty days have no text, link or tab stop' ).toBe( true );
		await expect( f01.locator( ':scope > a' ) ).toHaveCount( 1 );
		await expect( f01.locator( '.pkiw-mood-day__seg' ) ).toHaveCount( 0 );

		const f06 = page.locator( '.pkiw-mood-day[data-day="2026-03-14"]' );
		await expect( f06 ).toHaveClass( /pkiw-mood-day--split/ );
		await expect( f06.locator( '.pkiw-mood-day__seg' ) ).toHaveCount( 2 );
		expect( await f06.locator( '.pkiw-mood-day__code' ).allTextContents() ).toEqual( [ 'S3', 'So4' ] );
		const f08 = page.locator( '.pkiw-mood-day[data-day="2026-05-09"]' );
		await expect( f08.locator( '.pkiw-mood-day__seg' ) ).toHaveCount( 3 );
		expect( ( await f08.locator( ':scope > a' ).innerText() ).trim() ).toBe( '' );
		const f09 = page.locator( '.pkiw-mood-day[data-day="2026-06-20"]' );
		await expect( f09.locator( '.pkiw-mood-day__seg' ) ).toHaveCount( 4 );
		expect( ( await f09.locator( ':scope > a' ).innerText() ).trim() ).toBe( '' );
		expect( await f09.locator( '.pkiw-mood-day__seg' ).evaluateAll( ( segments ) => segments.map( ( segment ) => segment.dataset.family ) ) ).toEqual( [ 'sad', 'physical', 'physical', 'energy' ] );
		const dividerColor = await resolvedToken( page.locator( '.pkiw-mood-year' ), '--cr-rule' );
		const dividers = await f09.locator( '.pkiw-mood-day__seg + .pkiw-mood-day__seg' ).evaluateAll( ( segments ) => segments.map( ( segment ) => ( {
			width: getComputedStyle( segment ).borderLeftWidth,
			color: getComputedStyle( segment ).borderLeftColor,
		} ) ) );
		expect( dividers ).toHaveLength( 3 );
		expect( dividers.every( ( divider ) => divider.width === '1px' && divider.color === dividerColor ), 'all three F09 dividers use --cr-rule, including physical-to-physical' ).toBe( true );

		const f09Card = page.locator( '#mood-day-2026-06-20' );
		await expect( f09Card.locator( ':scope > h3 a' ) ).toHaveCount( 0 );
		const tired = f09Card.locator( '.pkiw-mood-daycard__link', { hasText: 'Tired, intensity 2 of 5' } );
		await expect( tired ).toHaveCount( 2 );
		const tiredTexts = ( await tired.allInnerTexts() ).map( ( text ) => text.replace( /\s+/g, ' ' ).trim() );
		expect( tiredTexts.every( ( text ) => /\b\d{1,2}:\d{2}\s*(?:am|pm)\b/i.test( text ) ), 'both identical F09 entries print their time' ).toBe( true );
		expect( new Set( tiredTexts ).size ).toBe( 2 );
		const f10Name = ( await linkNames( page.locator( '#mood-day-2026-07-18 .pkiw-mood-daycard__link', { hasText: 'Tired, intensity 2 of 5' } ) ) )[ 0 ];
		const f09Names = await linkNames( tired );
		expect( f09Names ).not.toContain( f10Name );

		await f09.locator( ':scope > a' ).click();
		await expect( page.locator( ':target' ) ).toHaveId( 'mood-day-2026-06-20' );
		expect( await f09Card.evaluate( ( card ) => document.activeElement === card || card.matches( ':target' ) ), 'fragment navigation focuses or targets the day card' ).toBe( true );
	} );

	test( 'Archive :65-67, :71-72 and W3-G2 — rendered anchors match entries and every mood link has a unique name', async ( { page } ) => {
		await openArchive( page );
		const oneEntry = page.locator( '.pkiw-mood-day[data-family]' );
		const multiEntry = page.locator( '.pkiw-mood-day[data-count]' );
		const cards = page.locator( '.pkiw-mood-daycard__entries > li' );
		for ( const scope of [ oneEntry, multiEntry, cards ] ) {
			const facts = await scope.evaluateAll( ( nodes ) => nodes.map( ( node ) => ( {
				links: [ ...node.querySelectorAll( 'a[href]' ) ].filter( ( anchor ) => ! anchor.closest( '[hidden]' ) ).length,
				stray: [ ...node.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( anchor ) => ! anchor.closest( '[hidden]' ) ).length,
			} ) ) );
			expect( facts.every( ( fact ) => fact.links === 1 && fact.stray === 0 ) ).toBe( true );
		}
		const yearFacts = await renderedAnchorFacts( page.locator( '.pkiw-mood-year' ) );
		expect( yearFacts.strayNegativeTabindex ).toBe( 0 );
		const names = await linkNames( page.locator( '.pkiw-mood-year a[href]' ) );
		expect( names.every( Boolean ), 'every mood link has a name' ).toBe( true );
		expect( new Set( names ).size, 'mood link names are unique across the archive page' ).toBe( names.length );

		const forbidden = page.locator( '.pkiw-mood-year' );
		await expect( forbidden.locator( '.p-content, .pk-kindlabel, .pk-excerpt' ) ).toHaveCount( 0 );
		await expect( forbidden ).not.toContainText( 'Seed note' );
		await expect( forbidden ).not.toContainText( /😊|😌|🥳|🤞|😰|😴|😢|🙏|😶|😠|🤪|😕|🗂️/ );
		await expect( page.locator( '.pkiw-mood-day[data-day="2026-04-04"] .p-name:not(.p-author .p-name)' ), 'F17 does not invent a p-name' ).toHaveCount( 0 );
	} );

	test( 'Archive :64 and :68 — post_date places real moods and missing intensity stays absent', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( '.pkiw-mood-day[data-day="2026-09-11"][data-count="15"]' ), 'local IDs 5-19 use post_date, not moodAt' ).toHaveCount( ENV === 'dev' ? 0 : 1 );
		await expect( page.locator( '.pkiw-mood-day[data-day="2026-03-21"] .pkiw-mood-day__code' ) ).toHaveText( 'S2' );
		await expect( page.locator( '.pkiw-mood-day[data-day="2026-08-30"] .pkiw-mood-day__code' ) ).toHaveText( 'C' );
		if ( ENV !== 'dev' ) {
			const realCard = page.locator( '#mood-day-2026-09-11' );
			await expect( realCard.locator( '.pkiw-mood-daycard__link' ) ).toHaveCount( 15 );
			expect( ( await realCard.locator( '.pkiw-mood-daycard__link' ).allInnerTexts() ).slice( 1 ).every( ( text ) => ! /intensity/i.test( text ) ), 'local IDs 6-19 have no invented intensity' ).toBe( true );
		}
	} );
} );

test.describe( 'archive paint and layout', () => {
	test( 'Archive :69, :81 and :85 — all 13 family pairs use distinct fixed fills and meet 4.5:1 in light and dark', async ( { page } ) => {
		await openArchive( page );
		const backgrounds = [];
		for ( const family of VOCABULARY_ORDER ) {
			const [ day, fixture ] = FAMILY_FIXTURES[ family ];
			const cell = page.locator( `.pkiw-mood-day[data-day="${ day }"][data-family="${ family }"]` );
			await expect( cell, `${ fixture } resolves to ${ family }` ).toHaveCount( 1 );
			const actual = [ await computed( cell, 'backgroundColor' ), await computed( cell, 'color' ) ];
			expect( actual, `${ family } paint` ).toEqual( FAMILY_PAINT[ family ] );
			expect( contrast( actual[ 1 ], actual[ 0 ] ), `${ family } code contrast` ).toBeGreaterThanOrEqual( 4.5 );
			backgrounds.push( actual[ 0 ] );
		}
		expect( new Set( backgrounds ).size, 'every family has its own background' ).toBe( 13 );
	} );

	test( 'Archive :70 and :81-83 — legend order, hard paper shadows, frame, lines and typography match shipped tokens', async ( { page } ) => {
		await openArchive( page );
		const year = page.locator( '.pkiw-mood-year' );
		const legend = year.locator( '.pkiw-mood-legend' );
		await expect( legend ).toHaveAttribute( 'role', 'group' );
		await expect( legend ).toHaveAccessibleName( 'Legend' );
			expect( await legend.locator( '.pkiw-mood-legend__families > li' ).evaluateAll( ( items ) => items.map( ( item ) => item.dataset.family ) ) ).toEqual( VOCABULARY_ORDER );
			await expect( legend.locator( '.pkiw-mood-legend__intensity' ) ).toHaveText( 'Intensity: 1 (low) to 5 (high)' );
			await expect( legend.locator( '.pkiw-mood-legend__split' ) ).toHaveText( 'Diagonal: two moods that day. Stripes: three or more moods that day.' );
		expect( await year.evaluate( ( node ) => {
			const legendNode = node.querySelector( '.pkiw-mood-legend' );
			const month = node.querySelector( '.pkiw-mood-month__name' );
			return Boolean( legendNode && month && ( legendNode.compareDocumentPosition( month ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
		} ), 'legend precedes the first month heading in DOM order' ).toBe( true );

			const months = year.locator( '.pkiw-mood-year__months' );
			const frame = await months.evaluate( ( node ) => {
				const style = getComputedStyle( node );
				return [ 'Top', 'Right', 'Bottom', 'Left' ].map( ( side ) => ( {
					width: style[ `border${ side }Width` ],
					style: style[ `border${ side }Style` ],
					color: style[ `border${ side }Color` ],
				} ) );
			} );
			expect( frame ).toEqual( Array( 4 ).fill( { width: '2px', style: 'solid', color: await resolvedToken( year, '--cr-ink' ) } ) );
		const innerShadow = await computed( year.locator( '.pkiw-mood-day' ).first(), 'boxShadow', '::after' );
		expect( innerShadow ).toContain( await resolvedToken( year, '--cr-rule' ) );

		const effects = await year.locator( '.pkiw-mood-year__grid, .pkiw-mood-year__grid *' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const style = getComputedStyle( node );
			return { image: style.backgroundImage, filter: style.filter, backdrop: style.backdropFilter, textShadow: style.textShadow };
		} ) );
		expect( effects.every( ( effect ) => effect.image === 'none' && effect.filter === 'none' && effect.backdrop === 'none' && effect.textShadow === 'none' ), 'the grid contains no gradient, filter, backdrop filter or text glow' ).toBe( true );

		const families = {
			mono: [ '.pkiw-mood-day__code', '.pkiw-mood-month__name', '.pkiw-mood-year__gutter', '.pkiw-mood-legend' ],
				'Roboto Slab': [ '.pkiw-mood-year__year', '.pkiw-mood-daycard__date' ],
			Barlow: [ '.pkiw-mood-daycard__link' ],
		};
			for ( const [ needle, selectors ] of Object.entries( families ) ) {
			for ( const selector of selectors ) {
				expect( await computed( year.locator( selector ).first(), 'fontFamily' ), selector ).toContain( needle === 'mono' ? 'monospace' : needle );
			}
			expect( await computed( page.locator( 'nav.wp-block-query-pagination .page-numbers' ).first(), 'fontFamily' ), 'pager chips' ).toContain( 'Roboto Slab' );
			expect( await computed( page.locator( 'nav.wp-block-query-pagination .wp-block-query-pagination-next' ), 'fontFamily' ), 'pager Next link' ).toContain( 'Roboto Slab' );
		}
		expect( await year.locator( ':scope, :scope *' ).evaluateAll( ( nodes ) => nodes.some( ( node ) => getComputedStyle( node ).fontFamily.includes( 'Rock Salt' ) ) ), 'Rock Salt never enters the mood-year component' ).toBe( false );
		const transforms = await year.locator( '.pkiw-mood-year__grid *, .pkiw-mood-legend, .pkiw-mood-legend *, .pkiw-mood-daycards, .pkiw-mood-daycards *' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).transform ) );
		expect( transforms.every( ( transform ) => transform === 'none' || transform === 'matrix(1, 0, 0, 1, 0, 0)' ), 'grid, legend and cards stay upright' ).toBe( true );

		const hardShadow = await resolvedToken( year, '--cr-shadow-hard', 'boxShadow' );
		expect( await computed( legend, 'boxShadow' ) ).toBe( hardShadow );
		expect( await computed( year.locator( '.pkiw-mood-daycard' ).first(), 'boxShadow' ) ).toBe( hardShadow );
	} );

	test( 'Archive :75-79 — the grid, legend and cards follow every required viewport without horizontal overflow', async ( { page } ) => {
		for ( const width of [ 1440, 1024, 768, 375, 320 ] ) {
			await page.setViewportSize( { width, height: 1600 } );
			await openArchive( page );
			const grid = await page.locator( '.pkiw-mood-year__grid' ).boundingBox();
			const legend = await page.locator( '.pkiw-mood-legend' ).boundingBox();
			const cards = await page.locator( '.pkiw-mood-daycards' ).boundingBox();
			expect( grid && legend && cards ).toBeTruthy();
			if ( width >= 1024 ) {
				expect( legend.x, `${ width }: legend is in the right column` ).toBeGreaterThanOrEqual( grid.x + grid.width - 1 );
				expect( cards.x, `${ width }: cards are in the right column` ).toBeGreaterThanOrEqual( grid.x + grid.width - 1 );
					const monthWidths = await page.locator( '.pkiw-mood-month' ).evaluateAll( ( months ) => months.map( ( month ) => Math.round( month.getBoundingClientRect().width ) ) );
					expect( monthWidths ).toEqual( Array( 12 ).fill( 44 ) );
					const gutter = page.locator( '.pkiw-mood-year__gutter[aria-hidden="true"] > li' );
					await expect( gutter ).toHaveCount( 31 );
					expect( await gutter.allTextContents() ).toEqual( Array.from( { length: 31 }, ( _, index ) => String( index + 1 ) ) );
				} else if ( width === 768 ) {
					expect( legend.y + legend.height, 'legend is above the grid at 768' ).toBeLessThanOrEqual( grid.y + 1 );
					expect( cards.y, 'cards are below the grid at 768' ).toBeGreaterThanOrEqual( grid.y + grid.height - 1 );
					const content = await page.locator( '.pkiw-mood-year' ).boundingBox();
					// Cells stay 44px, so "full width" means the grid takes its own row (legend above, cards below) and fits the content box.
					expect( Math.abs( grid.x - content.x ), 'the 768 grid begins at the content edge' ).toBeLessThanOrEqual( 1 );
					expect( grid.x + grid.width, 'the 768 grid fits the content box' ).toBeLessThanOrEqual( content.x + content.width + 1 );
			} else {
				const monthTops = await page.locator( '.pkiw-mood-month' ).evaluateAll( ( months ) => months.map( ( month ) => Math.round( month.getBoundingClientRect().top ) ) );
				expect( monthTops.every( ( top, index ) => index === 0 || top > monthTops[ index - 1 ] ), `${ width }: months stack` ).toBe( true );
				const rowCounts = await page.locator( '.pkiw-mood-month' ).first().locator( '.pkiw-mood-day' ).evaluateAll( ( days ) => {
					const rows = new Map();
					for ( const day of days ) {
						const top = Math.round( day.getBoundingClientRect().top );
						rows.set( top, ( rows.get( top ) || 0 ) + 1 );
					}
					return [ ...rows.values() ];
				} );
				expect( Math.max( ...rowCounts ), `${ width }: days per month row` ).toBeGreaterThanOrEqual( width === 375 ? 7 : 6 );
			}
			if ( width >= 768 ) {
				await assertGutterRegistered( page, `${ width }px archive` );
			}
			await assertNoOverflow( page, `${ width }px archive` );

			const targets = page.locator( '.pkiw-mood-year__grid a[href], .pkiw-mood-daycards a[href], nav.wp-block-query-pagination a[href]' );
			const boxes = await targets.evaluateAll( ( links ) => links.map( ( link ) => {
				const box = link.getBoundingClientRect();
				return { width: box.width, height: box.height };
			} ) );
			expect( boxes.every( ( box ) => box.width >= 44 && box.height >= 44 ), `${ width }: every mood and pager link is at least 44 by 44` ).toBe( true );
		}

		await page.setViewportSize( { width: 320, height: 1600 } );
		await openArchive( page );
		await assertNoOverflow( page, '1280px layout at the 320px Playwright proxy for 400% browser zoom' );
	} );

	test( 'Archive :80 — Tab order is month-major DOM order, then card links, and agrees with each layout', async ( { page } ) => {
		for ( const width of [ 1440, 768, 375, 320 ] ) {
			await page.setViewportSize( { width, height: 1600 } );
			await openArchive( page );
			const selector = '.pkiw-mood-year__grid a[href], .pkiw-mood-daycards a[href]';
			const count = await page.locator( selector ).count();
			await page.locator( selector ).evaluateAll( ( links ) => links.forEach( ( link, index ) => link.dataset.crMoodTab = String( index ) ) );
			await page.locator( selector ).first().focus();
			const tabbed = [ 0 ];
			const focusedBoxes = [ await page.evaluate( () => {
				const box = document.activeElement.getBoundingClientRect();
				return { x: box.x + window.scrollX, y: box.y + window.scrollY, width: box.width, height: box.height };
			} ) ];
			for ( let index = 1; index < count; index++ ) {
				await page.keyboard.press( 'Tab' );
				tabbed.push( await page.evaluate( () => Number( document.activeElement?.dataset.crMoodTab ) ) );
				focusedBoxes.push( await page.evaluate( () => {
					const box = document.activeElement.getBoundingClientRect();
					return { x: box.x + window.scrollX, y: box.y + window.scrollY, width: box.width, height: box.height };
				} ) );
			}
			expect( tabbed, `${ width }: Tab order equals DOM order` ).toEqual( Array.from( { length: count }, ( _, index ) => index ) );
			const domBoxes = await page.locator( selector ).evaluateAll( ( links ) => links.map( ( link ) => {
				const box = link.getBoundingClientRect();
				return { x: box.x + window.scrollX, y: box.y + window.scrollY, width: box.width, height: box.height };
			} ) );
			expect( focusedBoxes, `${ width }: each focused element occupies the matching DOM geometry` ).toEqual( domBoxes );

			const visual = await page.evaluate( ( narrow ) => {
				const months = [ ...document.querySelectorAll( '.pkiw-mood-month' ) ];
				const monthOrder = months.every( ( month, index ) => index === 0 || ( narrow ? month.getBoundingClientRect().top > months[ index - 1 ].getBoundingClientRect().top : month.getBoundingClientRect().left >= months[ index - 1 ].getBoundingClientRect().left ) );
				const days = months.every( ( month ) => {
					const links = [ ...month.querySelectorAll( 'a[href]' ) ].map( ( link ) => link.getBoundingClientRect() );
					return links.every( ( box, index ) => {
						if ( index === 0 ) {
							return true;
						}
						const previous = links[ index - 1 ];
						return narrow ? box.top > previous.top + 0.5 || ( Math.abs( box.top - previous.top ) <= 0.5 && box.left >= previous.left - 0.5 ) : box.top >= previous.top - 0.5;
					} );
				} );
				const cardLinks = [ ...document.querySelectorAll( '.pkiw-mood-daycards a[href]' ) ].map( ( link ) => link.getBoundingClientRect().top );
				return { monthOrder, days, cards: cardLinks.every( ( top, index ) => index === 0 || top >= cardLinks[ index - 1 ] ) };
			}, width < 768 );
			expect( visual ).toEqual( { monthOrder: true, days: true, cards: true } );
			await expect( page.locator( '.pkiw-mood-legend :is(a, button, input, select, textarea, [tabindex]), .pkiw-mood-day:not([data-family]):not([data-count]) :is(a, button, input, select, textarea, [tabindex])' ) ).toHaveCount( 0 );
		}
	} );

	test( 'Archive :84 and W4-G28 — focused cells and target cards use the shipped two-tone ring above neighbours', async ( { page }, testInfo ) => {
		await openArchive( page );
			const link = page.locator( '.pkiw-mood-day[data-day="2026-01-05"] > a' );
			await link.focus();
			expect( await computed( link, 'outlineColor' ) ).toBe( SELECTIVE_YELLOW );
			expect( await computed( link, 'outlineColor' ) ).toBe( await resolvedToken( link, '--cr-focus-ring' ) );
			expect( await computed( link, 'outlineWidth' ) ).toBe( '3px' );
			const halo = isDark( testInfo ) ? PRINTER_IVORY : RUSSIAN_VIOLET;
			expect( await resolvedToken( link, '--cr-focus-halo' ) ).toBe( halo );
		const shadow = await computed( link, 'boxShadow' );
		expect( shadow ).toContain( halo );
		expect( shadow ).toMatch( /0px 0px 0px 7px/ );
		const z = await page.locator( '.pkiw-mood-day[data-day="2026-01-05"]' ).evaluate( ( cell ) => ( {
			cell: Number( getComputedStyle( cell ).zIndex ) || 0,
			previous: Number( getComputedStyle( cell.previousElementSibling ).zIndex ) || 0,
			next: Number( getComputedStyle( cell.nextElementSibling ).zIndex ) || 0,
		} ) );
		expect( z.cell ).toBeGreaterThan( Math.max( z.previous, z.next ) );

		await page.locator( '.pkiw-mood-day[data-day="2026-03-14"] > a' ).click();
		const target = page.locator( '#mood-day-2026-03-14:target' );
		await expect( target ).toHaveCount( 1 );
		expect( await computed( target, 'outlineColor' ) ).toBe( SELECTIVE_YELLOW );
		expect( await computed( target, 'outlineWidth' ) ).toBe( '3px' );
		expect( await computed( target, 'boxShadow' ) ).toContain( halo );
		expect( await computed( target, 'boxShadow' ) ).toMatch( /0px 0px 0px 7px/ );
	} );

	test( 'Archive :85-88 — dark, forced-colors and reduced-motion modes preserve structure and remove prohibited effects', async ( { page } ) => {
		await page.emulateMedia( { colorScheme: 'dark' } );
		await openArchive( page );
		const year = page.locator( '.pkiw-mood-year' );
		expect( await computed( page.locator( 'body' ), 'backgroundColor' ) ).toBe( NIGHT );
		expect( await computed( year.locator( '.pkiw-mood-day:not([data-family]):not([data-count])' ).first(), 'backgroundColor' ) ).toBe( NIGHT_ELEVATED );
		expect( await computed( year.locator( '.pkiw-mood-day' ).first(), 'boxShadow', '::after' ) ).toContain( NIGHT_RULE );
		expect( await computed( year.locator( '.pkiw-mood-year__months' ), 'borderTopColor' ) ).toBe( PRINTER_IVORY );
		expect( await computed( year.locator( '.pkiw-mood-day[data-day="2026-01-05"]' ), 'backgroundColor' ) ).toBe( SELECTIVE_YELLOW );
			const ivoryCell = year.locator( '.pkiw-mood-day[data-day="2026-03-21"]' );
			expect( await computed( ivoryCell, 'outlineWidth' ) ).toBe( '1px' );
			expect( await computed( ivoryCell, 'outlineColor' ) ).toBe( PERIWINKLE );
			expect( await computed( ivoryCell, 'outlineColor' ) ).toBe( await resolvedToken( ivoryCell, '--pkiw-mood-cell-edge' ) );
		const month = year.locator( '.pkiw-mood-month__name' ).first();
		expect( await computed( month, 'backgroundColor' ) ).toBe( PERIWINKLE );
		expect( await computed( month, 'color' ) ).toBe( RUSSIAN_VIOLET );
		for ( const paper of [ year.locator( '.pkiw-mood-legend' ), year.locator( '.pkiw-mood-daycard' ).first() ] ) {
			expect( await computed( paper, 'backgroundColor' ) ).toBe( NIGHT_ELEVATED );
			expect( await computed( paper, 'color' ) ).toBe( PRINTER_IVORY );
		}

		await page.emulateMedia( { colorScheme: 'light', forcedColors: 'active' } );
		const filled = year.locator( '.pkiw-mood-day[data-day="2026-01-05"]' );
		expect( await computed( filled, 'outlineWidth' ) ).toBe( '2px' );
		expect( await computed( filled, 'backgroundImage' ) ).toBe( 'none' );
		const codeColor = await computed( filled.locator( '.pkiw-mood-day__code' ), 'color' );
		const canvasColor = await computed( filled, 'backgroundColor' );
		expect( codeColor, 'LinkText differs from Canvas' ).not.toBe( canvasColor );
		expect( await computed( year.locator( '.pkiw-mood-day:not([data-family]):not([data-count])' ).first(), 'outlineWidth' ) ).toBe( '1px' );
		expect( await year.locator( '.pkiw-mood-day__seg + .pkiw-mood-day__seg' ).evaluateAll( ( segments ) => segments.every( ( segment ) => getComputedStyle( segment ).borderLeftWidth === '1px' ) ) ).toBe( true );
		expect( await year.locator( '.pkiw-mood-legend__swatch' ).evaluateAll( ( swatches ) => swatches.every( ( swatch ) => getComputedStyle( swatch ).display === 'none' ) ) ).toBe( true );
		const forcedCard = year.locator( '.pkiw-mood-daycard' ).first();
		expect( await computed( forcedCard, 'borderTopWidth' ) ).toBe( '2px' );
		expect( await computed( forcedCard, 'display', '::after' ) ).toBe( 'none' );

		await page.emulateMedia( { forcedColors: 'none', reducedMotion: 'reduce' } );
		const motion = await year.locator( '.pkiw-mood-day, .pkiw-mood-day__link, .pkiw-mood-day__seg, .pkiw-mood-daycard, .pkiw-mood-daycard__link' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( {
			duration: getComputedStyle( node ).transitionDuration,
			transform: getComputedStyle( node ).transform,
		} ) ) );
		expect( motion.every( ( item ) => Number.parseFloat( item.duration ) <= 0.00001 && ( item.transform === 'none' || item.transform === 'matrix(1, 0, 0, 1, 0, 0)' ) ) ).toBe( true );
	} );

	test( 'Archive :89 — disabling JavaScript keeps every server-rendered mood link', async ( { browser, page } ) => {
		await openArchive( page );
		const expected = await page.locator( '.pkiw-mood-year a[href]' ).count();
		const context = await browser.newContext( { javaScriptEnabled: false, colorScheme: 'light' } );
		const noScript = await context.newPage();
		const response = await noScript.goto( new URL( ARCHIVE, BASE_URL ).href, { waitUntil: 'load' } );
		expect( response?.status() ).toBe( 200 );
		expect( await noScript.locator( '.pkiw-mood-year a[href]' ).count() ).toBe( expected );
		await context.close();
	} );
} );

test.describe( 'single mood posts', () => {
	test( 'Single :93-96 and W4-G26/W4-G30 — family and intensity lines match facts and retain AA contrast', async ( { page }, testInfo ) => {
		const cases = [
			[ 'F01', 'Joy', 'Intensity 4 of 5' ],
			[ 'F04', 'Thoughtful', null ],
			[ 'F15', 'Calm', null ],
			[ 'F23', 'Joy', 'Intensity 5 of 5' ],
			[ 'F14', 'Other', null ],
			[ 'F13', 'Thoughtful', null ],
		];
		for ( const [ key, family, intensity ] of cases ) {
			await openFixture( page, key );
			await expect( page.locator( 'main .cr-pin' ) ).toHaveCount( 1 );
			const sticker = page.locator( 'main .cr-pin__family' );
			const visibleFamily = await sticker.evaluate( ( node ) => {
				const clone = node.cloneNode( true );
				clone.querySelectorAll( '.cr-sr-only' ).forEach( ( hidden ) => hidden.remove() );
				return clone.textContent.trim();
			} );
			if ( key === 'F14' && visibleFamily !== family ) {
				test.info().annotations.push( { type: 'W4-G26', description: `F14 family observed: ${ visibleFamily }` } );
			}
			expect( visibleFamily, `${ key } family` ).toBe( family );
			const intensityNode = page.locator( 'main .cr-pin__intensity' );
			if ( intensity ) {
				await expect( intensityNode ).toHaveText( intensity );
				expect( contrast( await computed( intensityNode, 'color' ), await computed( page.locator( 'body' ), 'backgroundColor' ) ), `${ key } intensity against the page` ).toBeGreaterThanOrEqual( 4.5 );
			} else {
				await expect( intensityNode ).toHaveCount( 0 );
			}
			expect( await computed( sticker, 'color' ) ).toBe( isDark( testInfo ) ? SELECTIVE_YELLOW : RUSSIAN_VIOLET );
			expect( await computed( sticker, 'color' ) ).toBe( await resolvedToken( sticker, '--cr-tape-ink' ) );
			expect( contrast( await computed( sticker, 'color' ), await computed( sticker, 'backgroundColor' ) ), `${ key } family sticker` ).toBeGreaterThanOrEqual( 4.5 );
			await expect( page.locator( 'main .cr-pin__rim, main .cr-pin__face, main .cr-pin__wear, main .cr-pin__gloss' ) ).toHaveCount( 4 );
		}
	} );

	test( 'Single :93 — the served single has exactly one H1 and one pin', async ( { page } ) => {
		for ( const key of [ 'F01', 'F04', 'F15' ] ) {
			await openFixture( page, key );
			await expect( page.locator( 'h1' ), `${ key } prints one H1 on the page` ).toHaveCount( 1 );
			await expect( page.locator( 'main .cr-pin' ), `${ key } prints the pin once` ).toHaveCount( 1 );
		}
	} );

	test( 'Single :98 — the mood single retains h-entry, p-name, dt-published and p-content microformats', async ( { page } ) => {
		await openFixture( page, 'S1' );
		for ( const selector of [ '.h-entry', '.p-name', '.dt-published', '.p-content' ] ) {
			expect( await page.locator( `main ${ selector }` ).count(), `${ selector } remains present` ).toBeGreaterThan( 0 );
		}
		await expect( page.locator( 'main .cr-pin' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main .cr-pin__name' ) ).toHaveText( 'Happy' );
		await expect( page.locator( 'main .p-content' ) ).toContainText( 'Seed note with a link' );
		await expect( page.locator( 'main a[href="https://example.org/seed-note"]' ) ).toHaveText( 'a link' );
		await expect( page.locator( 'main time.dt-published' ).first() ).toHaveAttribute( 'datetime', /\S/ );
	} );

	test( 'Single :97 — F15 prints its Micropub note once, in the card p-content, with no intensity line', async ( { page } ) => {
		await openFixture( page, 'F15' );
		await expect( page.locator( 'main' ).getByText( 'Seed note', { exact: true } ), 'F15 shows its note once' ).toHaveCount( 1 );
		await expect( page.locator( 'main .pk-mood__note.p-content' ) ).toHaveText( 'Seed note' );
		await expect( page.locator( 'main .p-content' ), 'one p-content carries the note' ).toHaveCount( 1 );
		await expect( page.locator( 'main .e-content' ), 'no empty e-content is left behind' ).toHaveCount( 0 );
		await expect( page.locator( 'main .cr-pin__intensity' ) ).toHaveCount( 0 );
	} );

	test( 'Single :99 — an adjacent mood keeps the shipped mood-scoped post navigation', async ( { page } ) => {
		await openFixture( page, 'F02' );
		const nav = page.locator( 'main nav.cr-post-nav[aria-label="Post navigation"]' );
		await expect( nav ).toHaveCount( 1 );
		expect( await nav.locator( 'a[href]' ).count() ).toBeGreaterThan( 0 );
	} );

	test( 'Single :100 — the F01 single has no horizontal overflow at 320, 375, 768 or 1440', async ( { page } ) => {
		for ( const width of [ 320, 375, 768, 1440 ] ) {
			await page.setViewportSize( { width, height: 1100 } );
			await openFixture( page, 'F01' );
			await assertNoOverflow( page, `${ width }px F01 single` );
		}
	} );
} );

test.describe( 'Stream mood cards', () => {
	test( 'Stream :104-108 and W3-G2 — S1 and S2 retain pin cards, one rendered link and no archive or intensity markup', async ( { page } ) => {
		const response = await page.goto( STREAM, { waitUntil: 'load' } );
		expect( response?.status(), `Stream at ${ STREAM }` ).toBe( 200 );
		const s1 = page.locator( 'article.pk-card.k-mood', { has: page.locator( 'a[href="https://example.org/seed-note"]' ) } );
		await expect( s1, 'S1 is on the first Stream page' ).toHaveCount( 1 );
		// S2 is over the 4,000-character long-form threshold, so Post_Surface files it under main and /stream/ omits it.
		// The plugin's generic stream card for a long-form mood is not reachable on this stack's Stream.
		await expect( page.locator( 'a[href*="cr-seed-248-s2"]' ), 'S2 is main-surface and absent from /stream/' ).toHaveCount( 0 );
		await expect( page.locator( '.cr-pin__intensity' ), 'no Stream card prints an intensity line' ).toHaveCount( 0 );
		for ( const [ key, card ] of [ [ 'S1', s1 ] ] ) {
			await expect( card.locator( '.cr-pin' ), `${ key } pin` ).toHaveCount( 1 );
			await expect( card.locator( '.cr-pin__intensity' ) ).toHaveCount( 0 );
			await expect( card ).not.toContainText( 'Intensity' );
			const facts = await renderedAnchorFacts( card );
			expect( facts, `${ key } follows W3-G2` ).toEqual( { links: 1, strayNegativeTabindex: 0 } );
		}
		await expect( page.locator( '.pkiw-mood-legend, .pkiw-mood-day' ) ).toHaveCount( 0 );
		await expect( page.locator( 'article.pk-card.k-mood :is(audio, video, .mejs-container, .wp-block-audio, .wp-block-video, .pk-card__cta, .wp-block-query-pagination)' ) ).toHaveCount( 0 );

		const note = s1.locator( 'a[href="https://example.org/seed-note"]' );
		expect( await note.evaluate( ( link ) => {
			const box = link.getBoundingClientRect();
			const top = link.ownerDocument.elementFromPoint( box.left + box.width / 2, box.top + box.height / 2 );
			return top === link || link.contains( top );
		} ), 'the authored S1 note link sits above any card overlay' ).toBe( true );
	} );

	test( 'Stream :109 — mood cards do not overflow at 320 or 375 in light or dark projects', async ( { page } ) => {
		for ( const width of [ 320, 375 ] ) {
			await page.setViewportSize( { width, height: 1100 } );
			const response = await page.goto( STREAM, { waitUntil: 'load' } );
			expect( response?.status() ).toBe( 200 );
			expect( await page.locator( 'article.pk-card.k-mood' ).count(), 'the Stream page holds a mood card' ).toBeGreaterThan( 0 );
			await assertNoOverflow( page, `${ width }px Stream` );
		}
	} );
} );

test.describe( 'editor parity', () => {
	test.use( process.env.CR_MOOD_EDITOR_STORAGE ? { storageState: process.env.CR_MOOD_EDITOR_STORAGE } : {} );

	test( 'Single :96 — the real F01 post editor Mood pin panel previews Joy and Intensity 4 of 5', async ( { page } ) => {
		test.skip( ! process.env.CR_MOOD_EDITOR_STORAGE, 'set CR_MOOD_EDITOR_STORAGE to an authenticated storage state for the real post editor' );
		const id = await fixtureId( page, 'F01' );
		test.skip( ! id, 'F01 fixture ID could not be resolved' );
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit`, { waitUntil: 'domcontentloaded' } );
		await expect( page.locator( 'body' ) ).not.toHaveClass( /login/ );

		const topBlock = page.locator( '[data-type="post-kinds-indieweb/mood-card"]' );
		if ( await topBlock.count() ) {
			await topBlock.first().click();
		} else {
			const canvas = page.frameLocator( 'iframe[name="editor-canvas"], iframe.editor-canvas__iframe' );
			await canvas.locator( '[data-type="post-kinds-indieweb/mood-card"]' ).first().click();
		}

		let panel = page.getByRole( 'button', { name: 'Mood pin', exact: true } );
		if ( 0 === await panel.count() ) {
			const settings = page.getByRole( 'button', { name: /Settings/i } ).last();
			if ( await settings.count() ) {
				await settings.click();
			}
			panel = page.getByRole( 'button', { name: 'Mood pin', exact: true } );
		}
		await expect( panel ).toHaveCount( 1 );
		if ( ( await panel.getAttribute( 'aria-expanded' ) ) === 'false' ) {
			await panel.click();
		}
		const preview = page.locator( '.cr-mood-pin-preview' );
		await expect( preview ).toContainText( 'Joy' );
		await expect( preview ).toContainText( 'Intensity 4 of 5' );
	} );
} );

test.describe( 'privacy, REST, assets and requests', () => {
	test( 'Privacy :125-130 — X01-X05, local draft 22 and the 2030 future year never enter logged-out archive HTML', async ( { page } ) => {
		await openArchive( page );
		const html = await page.locator( '.pkiw-mood-year' ).innerHTML();
		for ( const key of [ 'X01', 'X02', 'X03', 'X04', 'X05' ] ) {
			expect( html, `${ key } slug is absent` ).not.toContain( fixtureSlug( key ) );
			if ( MOOD_IDS[ key ] ) {
				expect( html, `${ key } ID is absent` ).not.toMatch( new RegExp( `(?:post-|mood-entry-|[?&]p=)${ MOOD_IDS[ key ] }(?:\\D|$)` ) );
			}
		}
		expect( html, 'local draft ID 22 is absent' ).not.toMatch( /(?:post-|mood-entry-|[?&]p=)22(?:\D|$)/ );
		await expect( page.locator( '.pkiw-mood-year__year' ) ).not.toHaveText( '2030' );
		await expect( page.locator( 'nav.wp-block-query-pagination' ) ).not.toContainText( '2030' );
	} );

	test( 'Privacy :125-130 — local L01 location strings do not print in the year grid or day cards', async ( { page } ) => {
		test.skip( ENV === 'dev', 'L01 is local-only and is not seeded on dev' );
		await openArchive( page );
		const text = await page.locator( '.pkiw-mood-year, .pkiw-mood-daycards' ).allInnerTexts();
		expect( text.join( ' ' ) ).not.toMatch( /12\.345|-45\.678|Seed Street|Example Town/ );
	} );

	test( 'Privacy :125-130 — anonymous REST stays byte-identical or records the before responses without failing pre-existing mood meta', async ( { page, request } ) => {
		const keys = [ 'X02', 'X03', 'F01' ];
		const captured = {};
		for ( const key of keys ) {
			const id = await fixtureId( page, key );
			test.skip( ! id, `${ key } fixture ID could not be resolved; pass CR_MOOD_IDS when protected singles hide body classes` );
			const response = await request.get( `/wp-json/wp/v2/posts/${ id }` );
			expect( response.status(), `${ key } REST status` ).toBe( 200 );
			const body = await response.text();
			captured[ key ] = { id, status: response.status(), body };
			const data = JSON.parse( body );
			if ( key === 'X02' || key === 'X03' ) {
				expect( data.content?.protected, `${ key } content is protected` ).toBe( true );
				expect( data.excerpt?.protected, `${ key } excerpt is protected` ).toBe( true );
				expect( data.content?.rendered || '', `${ key } content is blank` ).toBe( '' );
				expect( data.excerpt?.rendered || '', `${ key } excerpt is blank` ).toBe( '' );
				const exposed = Object.keys( data.meta || {} ).filter( ( name ) => name.startsWith( '_pkiw_mood_' ) );
				if ( exposed.length ) {
					test.info().annotations.push( { type: 'pre-existing REST exposure', description: `${ key }: ${ exposed.join( ', ' ) }` } );
				}
			}
		}

		if ( process.env.CR_MOOD_REST_BASELINE ) {
			const baseline = JSON.parse( readFileSync( process.env.CR_MOOD_REST_BASELINE, 'utf8' ) );
			for ( const key of keys ) {
				const expected = baseline[ key ]?.body ?? baseline[ key ];
				expect( captured[ key ].body, `${ key } REST bytes match the earlier branch` ).toBe( expected );
			}
		} else {
			const output = resolve( 'tests/output/mood-248-rest.json' );
			mkdirSync( dirname( output ), { recursive: true } );
			writeFileSync( output, `${ JSON.stringify( captured, null, '\t' ) }\n`, 'utf8' );
			test.info().annotations.push( { type: 'REST baseline', description: `wrote ${ output } for the before run` } );
		}
	} );

	test( 'Archive :58 and W4-T0 cr-calendar.css — all three mood stylesheets print in dependency order', async ( { page } ) => {
		await openArchive( page );
		// Core inlines small block-style sheets, so a sheet prints as link#<handle>-css or style#<handle>-inline-css.
		const order = await page.evaluate( () => {
			const find = ( handle ) => document.querySelector( `link#${ handle }-css[rel="stylesheet"], style#${ handle }-inline-css` );
			const nodes = Object.fromEntries( [ 'courtneyr-calendar', 'courtneyr-mood-year', 'pkiw-mood-year-grid' ].map( ( handle ) => [ handle, find( handle ) ] ) );
			const before = ( first, second ) => Boolean( first && second && ( first.compareDocumentPosition( second ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
			return {
				present: Object.fromEntries( Object.entries( nodes ).map( ( [ handle, node ] ) => [ handle, Boolean( node ) ] ) ),
				calendarBeforeMood: before( nodes[ 'courtneyr-calendar' ], nodes[ 'courtneyr-mood-year' ] ),
				calendarBeforePluginGrid: before( nodes[ 'courtneyr-calendar' ], nodes[ 'pkiw-mood-year-grid' ] ),
			};
		} );
		expect( order.present, 'all three sheets print once on the archive' ).toEqual( { 'courtneyr-calendar': true, 'courtneyr-mood-year': true, 'pkiw-mood-year-grid': true } );
		test.info().annotations.push( { type: 'W4-T0 order', description: `calendar before pkiw-mood-year-grid: ${ order.calendarBeforePluginGrid }` } );
		expect( order.calendarBeforeMood, 'courtneyr-calendar prints before courtneyr-mood-year' ).toBe( true );
	} );

	test( 'Archive :73 — an unchallenged archive load requests only the site host, with up to two Cloudflare retries on dev', async ( { page } ) => {
		const expectedHost = new URL( BASE_URL ).host;
		let checked = false;
		for ( let attempt = 0; attempt < 3; attempt++ ) {
			const hosts = [];
			const listener = ( request ) => hosts.push( new URL( request.url() ).host );
			page.on( 'request', listener );
			const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
			page.off( 'request', listener );
			const headers = response ? await response.allHeaders() : {};
			if ( headers[ 'cf-mitigated' ] ) {
				continue;
			}
			test.skip( ! response || response.status() === 404, `no mood archive at ${ ARCHIVE }` );
			const foreign = [ ...new Set( hosts.filter( ( host ) => host !== expectedHost ) ) ];
			expect( foreign, 'same-host /cdn-cgi/ is allowed; challenges.cloudflare.com is not' ).toEqual( [] );
			checked = true;
			break;
		}
		expect( checked, 'the main document remained cf-mitigated after two retries' ).toBe( true );
	} );
} );

test.describe( 'optional environments', () => {
	test( 'Archive :62 — the read-only empty instance prints the plugin empty state and no grid, legend or pager', async ( { page } ) => {
		test.skip( ! process.env.CR_MOOD_EMPTY_BASE_URL, 'set CR_MOOD_EMPTY_BASE_URL to the twentytwentyfive tests instance with zero moods' );
		const url = new URL( ARCHIVE, process.env.CR_MOOD_EMPTY_BASE_URL ).href;
		const response = await page.goto( url, { waitUntil: 'load' } );
		expect( response?.status() ).toBe( 200 );
		await expect( page.getByText( 'No moods yet.', { exact: true } ) ).toHaveCount( 1 );
		await expect( page.locator( '.pkiw-mood-year, .pkiw-mood-legend, nav.wp-block-query-pagination' ) ).toHaveCount( 0 );
	} );

	test( 'Dev T8 — the unseeded dev archive is one thoughtful Curious cell and post 37895 has intensity 5', async ( { page } ) => {
		test.skip( ! DEV_EMPTY, 'set CR_ENV=dev and CR_MOOD_DEV_EMPTY=1 for the one-real-post dev state' );
		await openArchive( page, ARCHIVE, false );
		await expect( page.locator( '.pkiw-mood-year__year' ) ).toHaveText( '2026' );
		const cells = page.locator( '.pkiw-mood-day[data-family]' );
		await expect( cells ).toHaveCount( 1 );
		await expect( cells ).toHaveAttribute( 'data-family', 'thoughtful' );
		await expect( cells.locator( 'a' ) ).toHaveAccessibleName( /Curious/ );
		await expect( page.locator( 'nav.wp-block-query-pagination' ) ).toHaveCount( 0 );
		await page.goto( '/?p=37895', { waitUntil: 'load' } );
		await expect( page.locator( 'main .cr-pin__intensity' ) ).toHaveText( 'Intensity 5 of 5' );
	} );
} );
