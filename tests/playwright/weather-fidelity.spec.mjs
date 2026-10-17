// `CR_WEATHER_REQUIRE_SEED=1 CR_WEATHER_START_OF_WEEK=$(wp option get start_of_week) CR_SPEC=weather-fidelity.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs`:
// weather surfaces for issue 246. Read-only GETs; needs the local stack with `_cr_seed_246`
// fixtures from the vault seed-246.php, cr-local-sloc-weather-stub.php, and
// cr-local-stream-weather.php (both in the vault 246-weather folder, mapped as local-only mu-plugins).
// Set start_of_week 0, sloc_measurements imperial, and geo_public private. Optional inputs:
// CR_WEATHER_ARCHIVE_PATH (default /kind/weather/), CR_WEATHER_STREAM_PATH (default /stream/),
// CR_WEATHER_REQUIRE_SEED (1 fails a missing fixture, an absent weather card, and the logged-in
// block instead of skipping them), CR_WEATHER_START_OF_WEEK (pass `wp option get start_of_week`; when
// unset, the weekday order the archive serves decides it), and CR_WEATHER_STORAGE_STATE (an
// administrator Playwright storageState path; logged-in checks skip when unset, fail under
// CR_WEATHER_REQUIRE_SEED). Simple Location head, feed, and REST gate proofs stay dev-only in
// privacy-check-246.php.
import { createRequire } from 'node:module';
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const THEME_ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const ARCHIVE = process.env.CR_WEATHER_ARCHIVE_PATH || '/kind/weather/';
const STREAM = process.env.CR_WEATHER_STREAM_PATH || '/stream/';
const REQUIRE_SEED = '1' === process.env.CR_WEATHER_REQUIRE_SEED;
const WEEKDAYS = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
const STORAGE_STATE = process.env.CR_WEATHER_STORAGE_STATE;
const single = ( key ) => '/?name=cr-seed-' + '246-' + key.toLowerCase();
const archivePage = ( number ) => number === 1 ? ARCHIVE : `${ ARCHIVE }page/${ number }/`;

const INK = 'rgb(36, 28, 74)';
const IVORY = 'rgb(251, 250, 245)';
const LIGHT_ORANGE = 'rgb(254, 226, 195)';
const SKY_BLUE = 'rgb(142, 202, 230)';
const PERIWINKLE = 'rgb(188, 181, 227)';
const SELECTIVE_YELLOW = 'rgb(255, 183, 3)';
const PAGE_DARK = 'rgb(26, 23, 20)';
const CERULEAN = 'rgb(18, 103, 130)';
const ROTATIONS = [ -2.5, -1.2, 1.5, 2.8 ];
const AUGUST_KEYS = [
	'A05', 'A06', 'A01', 'A02', 'A07', 'A08', 'A09', 'A10', 'A11', 'A12',
	'A03', 'A04', 'A14', 'A15', 'A16', 'A17', 'A18', 'M01',
];
const EXPECTED_SVGS = [
	'cloud-halftone.svg', 'cloud.svg', 'fog.svg', 'leaves.svg', 'mast.svg', 'rain.svg',
	'snow.svg', 'storm.svg', 'sun-wheel.svg', 'sun.svg', 'thermometer.svg',
	'torn-paper.svg', 'wind.svg',
];

const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );

async function openSeeded( page, path, label = path ) {
	const response = await page.goto( path, { waitUntil: 'load' } );
	const missing = response?.status() === 404;
	if ( REQUIRE_SEED ) {
		expect( missing, `${ label } returned 404; seed issue 246 fixtures first` ).toBe( false );
	} else {
		test.skip( missing, `${ label } returned 404; seed issue 246 fixtures first` );
	}
	return response;
}

// An absent fixture is a skip on an unseeded site and a failure when CR_WEATHER_REQUIRE_SEED is set.
function requireFixture( absent, reason ) {
	if ( REQUIRE_SEED ) {
		expect( absent, reason ).toBe( false );
	} else {
		test.skip( absent, reason );
	}
}

// The site's start_of_week: the env value the harness read with WP-CLI, else the first weekday
// the archive header serves (paint_almanac() prints it from the same option).
async function startOfWeek( page ) {
	const given = process.env.CR_WEATHER_START_OF_WEEK;
	if ( given !== undefined && given !== '' ) {
		return ( ( Number.parseInt( given, 10 ) % 7 ) + 7 ) % 7;
	}
	const first = ( await page.locator( '.pkiw-almanac__weekdays span' ).first().textContent() ).trim();
	const index = WEEKDAYS.indexOf( first );
	expect( index, `weekday header starts with a known abbreviation (got "${ first }")` ).toBeGreaterThanOrEqual( 0 );
	return index;
}

const streamPath = ( number ) => number === 1 ? STREAM : `${ STREAM }${ STREAM.includes( '?' ) ? '&' : '?' }query-1-page=${ number }`;

// Finds the Stream card for a fixture by walking the Stream query's pages.
async function openStreamCard( page, key ) {
	for ( let number = 1; number <= 12; number++ ) {
		await openSeeded( page, streamPath( number ), `Stream page ${ number }` );
		const card = streamCard( page, key );
		if ( await card.count() ) {
			return card;
		}
	}
	requireFixture( true, `${ key } Stream card is not on Stream pages 1-12` );
	return streamCard( page, key );
}

async function visibleText( locator ) {
	return locator.evaluate( ( node ) => {
		const copy = node.cloneNode( true );
		copy.querySelectorAll( '[hidden], [aria-hidden="true"], .screen-reader-text, .pk-sr-only' ).forEach( ( hidden ) => hidden.remove() );
		return ( copy.textContent || '' ).replace( /\s+/g, ' ' ).trim();
	} );
}

async function renderedLinks( locator ) {
	return locator.evaluate( ( root ) => {
		const links = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) );
		const stray = [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) );
		return {
			count: links.length,
			stray: stray.length,
			hrefs: links.map( ( link ) => link.getAttribute( 'href' ) ),
			names: links.map( ( link ) => ( link.textContent || '' ).replace( /\s+/g, ' ' ).trim() ),
		};
	} );
}

async function skeleton( locator ) {
	return locator.evaluate( ( root ) => {
		const walk = ( node ) => [
			node.tagName.toLowerCase(),
			[ ...node.classList ].sort(),
			node.matches( 'mark' ) || Boolean( node.querySelector( ':scope > mark' ) ),
			node.matches( '.p-weather' ) || Boolean( node.querySelector( ':scope > .p-weather' ) ),
			[ ...node.children ].map( walk ),
		];
		return walk( root );
	} );
}

function localMonth( datetime ) {
	const parts = new Intl.DateTimeFormat( 'en-CA', {
		timeZone: 'America/New_York', year: 'numeric', month: '2-digit',
	} ).formatToParts( new Date( datetime ) );
	const values = Object.fromEntries( parts.map( ( part ) => [ part.type, part.value ] ) );
	return `${ values.year }-${ values.month }`;
}

function monthName( key, short = false ) {
	const [ year, month ] = key.split( '-' ).map( Number );
	return new Intl.DateTimeFormat( 'en-US', {
		timeZone: 'UTC', month: short ? 'short' : 'long', year: short ? undefined : 'numeric',
	} ).format( new Date( Date.UTC( year, month - 1, 1, 12 ) ) );
}

function luminance( rgb ) {
	const [ r, g, b ] = rgb.match( /\d+(?:\.\d+)?/g ).slice( 0, 3 ).map( ( value ) => {
		const channel = Number( value ) / 255;
		return channel <= 0.03928 ? channel / 12.92 : ( ( channel + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast( first, second ) {
	const [ high, low ] = [ luminance( first ), luminance( second ) ].sort( ( a, b ) => b - a );
	return ( high + 0.05 ) / ( low + 0.05 );
}

function rotation( transform ) {
	if ( ! transform || transform === 'none' ) {
		return 0;
	}
	const values = transform.match( /matrix\(([^)]+)\)/ )?.[ 1 ].split( ',' ).map( Number );
	return values?.length === 6 ? Math.atan2( values[ 1 ], values[ 0 ] ) * 180 / Math.PI : Number.NaN;
}

function allowedRotation( value, allowNone = false ) {
	return ( allowNone && Math.abs( value ) < 0.05 ) || ROTATIONS.some( ( expected ) => Math.abs( value - expected ) <= 0.05 );
}

async function axeGatingViolations( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => {
		const result = await window.axe.run( document );
		return result.violations
			.filter( ( violation ) => [ 'serious', 'critical' ].includes( violation.impact ) )
			.map( ( violation ) => ( {
				id: violation.id,
				impact: violation.impact,
				targets: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
			} ) );
	} );
}

function pageEntry( page, key ) {
	return page.locator( `li.pkiw-almanac__entry:has(a[href*="cr-seed-246-${ key.toLowerCase() }"])` );
}

// The plugin's protected Stream card (pk-card--protected) is a different object: no reading, no thermometer.
const UNPROTECTED_CARD = 'article.pk-card.k-weather:not(.pk-card--protected)';

function streamCard( page, key ) {
	return page.locator( `article.pk-card.k-weather:has(a[href*="cr-seed-246-${ key.toLowerCase() }"])` );
}

function parseSvgXml( source ) {
	const tokens = source.match( /<!--[\s\S]*?-->|<\?[\s\S]*?\?>|<![^>]*>|<[^>]+>/g ) || [];
	const remainder = source.replace( /<!--[\s\S]*?-->|<\?[\s\S]*?\?>|<![^>]*>|<[^>]+>/g, '' );
	if ( /[<>]/.test( remainder ) ) {
		throw new Error( 'unparsed angle bracket' );
	}
	const stack = [];
	let root = null;
	for ( const token of tokens ) {
		if ( token.startsWith( '<!--' ) || token.startsWith( '<?' ) || token.startsWith( '<!' ) ) {
			continue;
		}
		let quoted = null;
		for ( let index = 1; index < token.length - 1; index++ ) {
			if ( quoted && token[ index ] === quoted ) {
				quoted = null;
			} else if ( ! quoted && ( token[ index ] === '"' || token[ index ] === "'" ) ) {
				quoted = token[ index ];
			}
		}
		if ( quoted ) {
			throw new Error( 'unclosed attribute quote' );
		}
		const closing = token.match( /^<\/\s*([A-Za-z_:][\w:.-]*)\s*>$/ );
		if ( closing ) {
			if ( stack.pop() !== closing[ 1 ] ) {
				throw new Error( `mismatched closing tag ${ closing[ 1 ] }` );
			}
			continue;
		}
		const opening = token.match( /^<\s*([A-Za-z_:][\w:.-]*)\b/ );
		if ( ! opening ) {
			throw new Error( `invalid tag ${ token }` );
		}
		root ||= { name: opening[ 1 ], token };
		if ( ! /\/\s*>$/.test( token ) ) {
			stack.push( opening[ 1 ] );
		}
	}
	if ( stack.length ) {
		throw new Error( `unclosed tag ${ stack.at( -1 ) }` );
	}
	if ( root?.name !== 'svg' ) {
		throw new Error( 'root element is not svg' );
	}
	return root;
}

export function checkWeatherCss( root = THEME_ROOT ) {
	const source = readFileSync( resolve( root, 'assets/css/cr-weather.css' ), 'utf8' );
	const failures = [];
	if ( /#[0-9a-fA-F]{3,8}\b/.test( source ) ) failures.push( 'literal hex color' );
	for ( const forbidden of [ /blur\(/i, /rgba\(/i, /hsla\(/i, /color-mix\(/i, /gradient/i, /filter/i, /opacity/i, /aspect-ratio/i ] ) {
		if ( forbidden.test( source ) ) failures.push( `forbidden ${ forbidden.source }` );
	}
	const allowed = new Set( [
		'var(--cr-rotate-1)', 'var(--cr-rotate-2)', 'var(--cr-rotate-neg)', 'var(--cr-rotate-neg2)',
	] );
	for ( const match of source.matchAll( /rotate\(([^;\n]+)\)/gi ) ) {
		if ( ! allowed.has( match[ 1 ].trim() ) ) failures.push( `rotation ${ match[ 0 ] }` );
	}
	for ( const match of source.matchAll( /font-size\s*:\s*(-?\d*\.?\d+)(rem|px)\b/gi ) ) {
		const pixels = match[ 2 ].toLowerCase() === 'rem' ? Number( match[ 1 ] ) * 16 : Number( match[ 1 ] );
		if ( pixels < 12 ) failures.push( `font floor ${ match[ 0 ] }` );
	}
	return failures;
}

export function checkWeatherSvgs( root = THEME_ROOT ) {
	const directory = resolve( root, 'assets/img/weather' );
	const names = readdirSync( directory ).filter( ( name ) => name.endsWith( '.svg' ) ).sort();
	const failures = [];
	if ( JSON.stringify( names ) !== JSON.stringify( [ ...EXPECTED_SVGS ].sort() ) ) {
		failures.push( `file names ${ names.join( ', ' ) }` );
	}
	const tokens = readFileSync( resolve( root, 'assets/css/tokens.css' ), 'utf8' );
	const paletteStart = tokens.indexOf( ':root {' );
	const blackStart = tokens.indexOf( '--cr-black:', paletteStart );
	const paletteBlock = tokens.slice( paletteStart, tokens.indexOf( ';', blackStart ) + 1 );
	const palette = new Set( [ ...paletteBlock.matchAll( /--cr-[\w-]+:\s*(#[0-9a-fA-F]{3,8})\b/g ) ].map( ( match ) => match[ 1 ].toLowerCase() ) );
	for ( const name of names ) {
		const source = readFileSync( resolve( directory, name ), 'utf8' );
		if ( /<text\b/i.test( source ) ) failures.push( `${ name }: text element` );
		if ( /gradient|filter|opacity/i.test( source ) ) failures.push( `${ name }: forbidden effect` );
		for ( const hex of source.match( /#[0-9a-fA-F]{3,8}\b/g ) || [] ) {
			if ( hex !== hex.toLowerCase() ) failures.push( `${ name }: uppercase ${ hex }` );
			if ( ! palette.has( hex.toLowerCase() ) ) failures.push( `${ name }: off-palette ${ hex }` );
		}
		try {
			const rootElement = parseSvgXml( source );
			if ( ! /\bviewBox\s*=\s*["'][^"']+["']/.test( rootElement.token ) ) failures.push( `${ name }: no viewBox` );
		} catch ( error ) {
			failures.push( `${ name }: XML ${ error.message }` );
		}
	}
	return failures;
}

export function checkWeatherPhp( root = THEME_ROOT ) {
	const source = readFileSync( resolve( root, 'inc/weather.php' ), 'utf8' );
	const failures = [];
	if ( ! /wp_enqueue_block_style\s*\(\s*'post-kinds-indieweb\/almanac-month'[\s\S]*?'deps'\s*=>\s*array\(\s*'courtneyr-calendar'\s*\)/.test( source ) ) {
		failures.push( 'almanac style lacks courtneyr-calendar dependency' );
	}
	for ( const writer of [ 'update_post_meta', 'add_post_meta', 'wp_set_object_terms', 'wp_insert_post', 'wp_update_post' ] ) {
		if ( new RegExp( `\\b${ writer }\\s*\\(` ).test( source ) ) failures.push( `writes through ${ writer }` );
	}
	return failures;
}

test.describe( 'archive', () => {
	test( 'header has the Weather cutout, seven nonrepeating decorative tiles, a description, and no sample marker', async ( { page } ) => {
		await openSeeded( page, ARCHIVE );
		const h1 = page.locator( 'main h1.cr-archive__title' );
		await expect( h1, 'one archive title' ).toHaveCount( 1 );
		await expect( h1.locator( '.cr-cutout__text' ), 'title text' ).toHaveText( 'Weather' );
		const tiles = h1.locator( '.cr-cutout__tile' );
		await expect( tiles, 'seven letter tiles' ).toHaveCount( 7 );
		const values = await tiles.evaluateAll( ( nodes ) => nodes.map( ( node ) => ( {
			decorative: Boolean( node.closest( '[aria-hidden="true"]' ) ),
			rotation: [ ...node.classList ].find( ( name ) => /^is-r\d$/.test( name ) ) || '',
		} ) ) );
		expect( values.every( ( value ) => value.decorative ), 'all tiles are aria-hidden' ).toBe( true );
		for ( let index = 1; index < values.length; index++ ) {
			expect( values[ index ].rotation, `tile ${ index } differs from its neighbor` ).not.toBe( values[ index - 1 ].rotation );
		}
		expect( ( await page.locator( 'main .cr-archive__description' ).textContent() ).trim().length, 'term description is non-empty' ).toBeGreaterThan( 0 );
		const badge = page.locator( 'main .cr-archive-identity__glyph' );
		await expect( badge, 'archive badge is decorative' ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( badge.locator( 'a' ), 'archive badge has no link' ).toHaveCount( 0 );
		expect( await badge.getAttribute( 'tabindex' ), 'archive badge has no tab stop' ).toBeNull();
		await expect( page.locator( 'main .cr-archive__header .wp-block-separator' ), 'archive divider remains' ).toHaveCount( 1 );
		expect( await page.locator( 'body' ).innerText(), 'sample marker is absent' ).not.toMatch( /SAMPLE CONTENT|Sample content/ );
	} );

	test( 'month pages map the six run fixtures, eighteen August fixtures, two June fixtures, and one December fixture before a 404', async ( { page } ) => {
		await openSeeded( page, single( 'S1' ), 'S1 single' );
		const datetime = await page.locator( 'main time.dt-published, main time[datetime]' ).first().getAttribute( 'datetime' );
		expect( datetime, 'S1 exposes a machine-readable date' ).toBeTruthy();
		const runMonth = localMonth( datetime );
		const expected = [
			{ page: 1, month: runMonth, count: 6, keys: [ 'S1', 'S2', 'S3', 'S4', 'S5', 'S6' ] },
			{ page: 2, month: '2026-08', count: 18, keys: AUGUST_KEYS },
			{ page: 3, month: '2026-06', count: 2, keys: [ 'J01', 'J02' ] },
			{ page: 4, month: '2025-12', count: 1, keys: [ 'D01' ] },
		];
		for ( const state of expected ) {
			await openSeeded( page, archivePage( state.page ), `archive page ${ state.page }` );
			const section = page.locator( 'section.pkiw-almanac' );
			await expect( section, `page ${ state.page } month` ).toHaveAttribute( 'data-pkiw-month', state.month );
			await expect( section.locator( 'li.pkiw-almanac__entry' ), `page ${ state.page } entry count` ).toHaveCount( state.count );
			const hrefs = await section.locator( 'li.pkiw-almanac__entry a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => link.getAttribute( 'href' ) ) );
			for ( const key of state.keys ) expect( hrefs.some( ( href ) => href.includes( `cr-seed-246-${ key.toLowerCase() }` ) ), `${ key } is on page ${ state.page }` ).toBe( true );
		}
		await expect( pageEntry( page, 'D01' ).locator( 'xpath=ancestor::li[contains(@class,"pkiw-almanac__day")]' ), 'D01 is in December 31' ).toHaveAttribute( 'data-pkiw-date', '2025-12-31' );
		const response = await page.goto( archivePage( 5 ), { waitUntil: 'load' } );
		expect( response?.status(), 'page 5 is core 404' ).toBe( 404 );
	} );

	test( 'pagination follows the sheet, starts without Previous, exposes its current page, and keeps 44px light-mode chips', async ( { page } ) => {
		await page.emulateMedia( { colorScheme: 'light' } );
		await openSeeded( page, ARCHIVE );
		const pager = page.locator( 'nav[aria-label="Pagination"]' );
		await expect( pager, 'one labeled pager' ).toHaveCount( 1 );
		const placement = await page.locator( 'section.pkiw-almanac' ).evaluate( ( section ) => section.nextElementSibling?.matches( 'nav[aria-label="Pagination"]' ) === true );
		expect( placement, 'pager is the section following sibling' ).toBe( true );
		await expect( pager.locator( '.wp-block-query-pagination-previous' ), 'page 1 has no Previous link' ).toHaveCount( 0 );
		await expect( pager.locator( '[aria-current="page"]' ), 'current chip' ).toHaveCount( 1 );
		const targets = await pager.locator( 'a, [aria-current="page"]' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( {
			width: node.getBoundingClientRect().width,
			height: node.getBoundingClientRect().height,
			shadow: getComputedStyle( node ).boxShadow,
		} ) ) );
		expect( targets.every( ( target ) => target.width >= 44 && target.height >= 44 ), 'all pager targets are at least 44px square' ).toBe( true );
		for ( const target of targets.filter( ( item ) => item.shadow !== 'none' ) ) {
			expect( target.shadow, 'pager hard shadow comes from cr-nav.css' ).toBe( `${ INK } 3px 3px 0px 0px` );
		}
	} );

	test( 'page 2 year strip follows the days, precedes the pager, labels twelve months, and marks August', async ( { page } ) => {
		await openSeeded( page, single( 'S1' ), 'S1 single' );
		const runMonth = localMonth( await page.locator( 'main time.dt-published, main time[datetime]' ).first().getAttribute( 'datetime' ) );
		await openSeeded( page, archivePage( 2 ) );
		const nav = page.locator( 'nav.pkiw-year-strip' );
		await expect( nav, 'year strip has a label' ).toHaveAttribute( 'aria-label', /\S+/ );
		await expect( nav.locator( 'li.pkiw-year-strip__item' ), 'twelve month items' ).toHaveCount( 12 );
		const order = await nav.evaluate( ( node ) => {
			const days = document.querySelector( 'ol.pkiw-almanac__days' );
			const pager = document.querySelector( 'nav[aria-label="Pagination"]' );
			return Boolean( days && pager && ( days.compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) && ( node.compareDocumentPosition( pager ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
		} );
		expect( order, 'days then year strip then pager' ).toBe( true );
		const expectedLinks = new Set( [ 'Jun', 'Aug' ] );
		if ( runMonth.startsWith( '2026-' ) ) expectedLinks.add( monthName( runMonth, true ) );
		const links = nav.locator( 'a.pkiw-year-strip__link' );
		expect( new Set( await links.locator( '> span:first-child' ).allTextContents() ), 'only populated months link' ).toEqual( expectedLinks );
		for ( const link of await links.all() ) {
			const short = ( await link.locator( '> span:first-child' ).textContent() ).trim();
			const month = [ ...Array( 12 ) ].findIndex( ( _, index ) => new Intl.DateTimeFormat( 'en-US', { month: 'short', timeZone: 'UTC' } ).format( new Date( Date.UTC( 2026, index, 1 ) ) ) === short ) + 1;
			await expect( link, `${ short } accessible name carries full month and year` ).toHaveAccessibleName( new RegExp( `${ monthName( `2026-${ String( month ).padStart( 2, '0' ) }` ) }` ) );
			const box = await link.boundingBox();
			expect( box.width >= 44 && box.height >= 44, `${ short } target is at least 44px square` ).toBe( true );
		}
		const current = nav.locator( 'a[aria-current="page"]' );
		await expect( current, 'August is current' ).toHaveAccessibleName( /August 2026/ );
		const paint = await current.evaluate( ( node ) => ( { border: getComputedStyle( node ).borderTopWidth, background: getComputedStyle( node ).backgroundColor } ) );
		expect( paint, 'current month has two-channel paint' ).toEqual( { border: '2px', background: SELECTIVE_YELLOW } );
	} );

	for ( const width of [ 1440, 800 ] ) {
		test( `at ${ width }px the August grid has seven columns, 31 days, one first-day offset, bounded chips, and stable stacks`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 1000 } );
			await openSeeded( page, archivePage( 2 ) );
			const days = page.locator( 'ol.pkiw-almanac__days' );
			expect( await days.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length ), 'seven grid tracks' ).toBe( 7 );
			const header = page.locator( '.pkiw-almanac__weekdays' );
			await expect( header, 'weekday row is decorative' ).toHaveAttribute( 'aria-hidden', 'true' );
			const start = await startOfWeek( page );
			await expect( header.locator( 'span' ).first(), 'first weekday follows start_of_week' ).toHaveText( WEEKDAYS[ start ] );
			await expect( header.locator( 'span' ), 'seven weekday labels in site order' ).toHaveText( [ ...Array( 7 ) ].map( ( _, index ) => WEEKDAYS[ ( start + index ) % 7 ] ) );
			await expect( days.locator( '> li.pkiw-almanac__day' ), 'all August days' ).toHaveCount( 31 );
			const first = days.locator( '> li.pkiw-almanac__day' ).first();
			const expectedColumn = ( ( 6 - start + 7 ) % 7 ) + 1;
			const measured = await first.evaluate( ( node ) => {
				const grid = node.parentElement.getBoundingClientRect();
				const cell = node.getBoundingClientRect();
				return Number.parseInt( getComputedStyle( node ).gridColumnStart, 10 ) || Math.round( ( cell.left - grid.left ) / ( grid.width / 7 ) ) + 1;
			} );
			expect( measured, 'August 1 column' ).toBe( expectedColumn );
			const offsets = await days.locator( '> li.pkiw-almanac__day' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.style.getPropertyValue( '--cr-almanac-offset' ) ) );
			expect( offsets.filter( Boolean ), 'one inline offset' ).toEqual( [ String( expectedColumn ) ] );
			const firstTwo = await days.locator( '> li.pkiw-almanac__day' ).evaluateAll( ( nodes ) => nodes.slice( 0, 2 ).map( ( node ) => node.getBoundingClientRect().top ) );
			expect( firstTwo[ 1 ], 'August 2 begins the next row' ).toBeGreaterThan( firstTwo[ 0 ] );
			const emptySafe = await days.locator( '> li.pkiw-almanac__day--empty' ).evaluateAll( ( nodes ) => nodes.every( ( node ) => node.getAttribute( 'aria-hidden' ) === 'true' && ! node.querySelector( 'a' ) ) );
			expect( emptySafe, 'empty cells are hidden and link-free' ).toBe( true );
			const bounded = await days.locator( 'li.pkiw-almanac__entry' ).evaluateAll( ( entries ) => entries.every( ( entry ) => {
				const card = entry.getBoundingClientRect();
				const cell = entry.closest( 'li.pkiw-almanac__day' ).getBoundingClientRect();
				return card.left >= cell.left - 1 && card.right <= cell.right + 1 && card.top >= cell.top - 1 && card.bottom <= cell.bottom + 1;
			} ) );
			expect( bounded, 'every chip stays inside its day' ).toBe( true );
			for ( const [ date, keys ] of [ [ '2026-08-03', [ 'A01', 'A02' ] ], [ '2026-08-16', [ 'A18', 'M01' ] ] ] ) {
				const items = days.locator( `li[data-pkiw-date="${ date }"] li.pkiw-almanac__entry` );
				const order = await items.locator( 'a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => link.getAttribute( 'href' ) ) );
				expect( order.map( ( href ) => keys.find( ( key ) => href.includes( key.toLowerCase() ) ) ), `${ date } DOM order` ).toEqual( keys );
				const tops = await items.evaluateAll( ( nodes ) => nodes.map( ( node ) => node.getBoundingClientRect().top ) );
				expect( tops[ 1 ], `${ date } visual order` ).toBeGreaterThanOrEqual( tops[ 0 ] );
			}
		} );
	}

	test( 'June 1 uses the configured Monday column relative to start_of_week', async ( { page } ) => {
		await page.setViewportSize( { width: 800, height: 900 } );
		await openSeeded( page, archivePage( 3 ) );
		const start = await startOfWeek( page );
		const expectedColumn = ( ( 1 - start + 7 ) % 7 ) + 1;
		const measured = await page.locator( 'li[data-pkiw-date="2026-06-01"]' ).evaluate( ( node ) => Number.parseInt( getComputedStyle( node ).gridColumnStart, 10 ) || Math.round( ( node.getBoundingClientRect().left - node.parentElement.getBoundingClientRect().left ) / ( node.parentElement.getBoundingClientRect().width / 7 ) ) + 1 );
		expect( measured, 'June 1 column' ).toBe( expectedColumn );
	} );

	for ( const width of [ 375, 320 ] ) {
		test( `at ${ width }px the almanac becomes one chronological track with no empty days or horizontal scroll`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openSeeded( page, archivePage( 2 ) );
			const days = page.locator( 'ol.pkiw-almanac__days' );
			expect( await days.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length ), 'one grid track' ).toBe( 1 );
			expect( await page.locator( '.pkiw-almanac__weekdays' ).evaluate( ( node ) => getComputedStyle( node ).display ), 'weekday row hidden' ).toBe( 'none' );
			const counts = await days.locator( '> li.pkiw-almanac__day' ).evaluateAll( ( nodes ) => ( {
				populated: nodes.filter( ( node ) => ! node.classList.contains( 'pkiw-almanac__day--empty' ) ).length,
				visible: nodes.filter( ( node ) => getComputedStyle( node ).display !== 'none' ).length,
				emptyHidden: nodes.filter( ( node ) => node.classList.contains( 'pkiw-almanac__day--empty' ) ).every( ( node ) => getComputedStyle( node ).display === 'none' ),
			} ) );
			expect( counts.visible, 'only populated days show' ).toBe( counts.populated );
			expect( counts.emptyHidden, 'every empty cell computes display none' ).toBe( true );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), `${ width }px page does not overflow` ).toBe( true );
			for ( const [ date, keys ] of [ [ '2026-08-03', [ 'A01', 'A02' ] ], [ '2026-08-16', [ 'A18', 'M01' ] ] ] ) {
				const items = days.locator( `li[data-pkiw-date="${ date }"] li.pkiw-almanac__entry` );
				const hrefs = await items.locator( 'a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => link.getAttribute( 'href' ) ) );
				expect( hrefs.map( ( href ) => keys.find( ( key ) => href.includes( key.toLowerCase() ) ) ), `${ date } DOM order` ).toEqual( keys );
				const tops = await items.evaluateAll( ( nodes ) => nodes.map( ( node ) => node.getBoundingClientRect().top ) );
				expect( tops[ 1 ], `${ date } visual order` ).toBeGreaterThanOrEqual( tops[ 0 ] );
			}
			if ( width === 320 ) await expect( pageEntry( page, 'A05' ), 'the 140-character title remains visible' ).toBeVisible();
		} );
	}

	test( 'entries have one rendered link, full-chip hit areas, unique names, one weather value, and no archive detail leaks', async ( { page } ) => {
		await openSeeded( page, archivePage( 2 ) );
		const entries = page.locator( 'li.pkiw-almanac__entry' );
		for ( let index = 0; index < await entries.count(); index++ ) {
			const entry = entries.nth( index );
			const links = await renderedLinks( entry );
			expect( links.count, `entry ${ index } has one rendered link` ).toBe( 1 );
			expect( links.stray, `entry ${ index } has no exposed tabindex=-1 link` ).toBe( 0 );
			// At most one: an entry with no visible reading (A10, S5 style controls) prints none.
			expect( await entry.locator( '.p-weather' ).count(), `entry ${ index } has at most one weather property` ).toBeLessThanOrEqual( 1 );
			const overlay = await entry.locator( 'a[href]' ).evaluate( ( link ) => {
				const style = getComputedStyle( link, '::after' );
				return { position: style.position, insets: [ style.top, style.right, style.bottom, style.left ] };
			} );
			expect( overlay.position, `entry ${ index } overlay is absolute` ).toBe( 'absolute' );
			expect( overlay.insets, `entry ${ index } overlay reaches every edge` ).toEqual( [ '0px', '0px', '0px', '0px' ] );
		}
		const names = await entries.locator( 'a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => ( { href: link.getAttribute( 'href' ), name: link.textContent.replace( /\s+/g, ' ' ).trim() } ) ) );
		const duplicates = [ ...new Set( names.filter( ( item, index ) => names.findIndex( ( other ) => other.name === item.name ) !== index ).map( ( item ) => item.name ) ) ];
		expect( duplicates.length, 'only one accessible-name collision' ).toBe( 1 );
		expect( names.filter( ( item ) => item.name === duplicates[ 0 ] ).every( ( item ) => /cr-seed-246-a0[34]/.test( item.href ) ), 'the collision is A03 and A04' ).toBe( true );
		const text = await visibleText( page.locator( 'main' ) );
		expect( text, 'archive omits observation details' ).not.toMatch( /Observed|wind|\bPlace\b|Sampletown|Example Hall|Example Avenue|→|←|↗|\bHigh\b|\bLow\b/i );
		expect( await page.locator( 'main .p-author' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => ! node.closest( '[hidden]' ) ).length ), 'no visible author' ).toBe( 0 );
		await expect( page.locator( 'main .pk-excerpt, main .wp-block-post-excerpt' ), 'no excerpt' ).toHaveCount( 0 );
		for ( const number of [ 1, 2, 3, 4 ] ) {
			await openSeeded( page, archivePage( number ) );
			for ( const key of [ 'A13', 'A19', 'A20' ] ) await expect( page.locator( `a[href*="cr-seed-246-${ key.toLowerCase() }"]` ), `${ key } is absent from page ${ number }` ).toHaveCount( 0 );
			const pageEntries = page.locator( 'li.pkiw-almanac__entry' );
			for ( let index = 0; index < await pageEntries.count(); index++ ) {
				expect( await renderedLinks( pageEntries.nth( index ) ), `page ${ number } entry ${ index } link contract` ).toMatchObject( { count: 1, stray: 0 } );
				expect( await pageEntries.nth( index ).locator( '.p-weather' ).count(), `page ${ number } entry ${ index } weather property` ).toBeLessThanOrEqual( 1 );
			}
			const pageNames = await pageEntries.locator( 'a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => ( link.textContent || '' ).replace( /\s+/g, ' ' ).trim() ) );
			const pageDuplicates = pageNames.filter( ( name, index ) => pageNames.indexOf( name ) !== index );
			expect( pageDuplicates.length === 0 || ( number === 2 && new Set( pageDuplicates ).size === 1 ), `page ${ number } accessible-name uniqueness` ).toBe( true );
		}
	} );

	test( 'logged-out privacy controls share skeletons, expose no privacy or place labels, and condition marks follow stored codes', async ( { page } ) => {
		const requestedHosts = [];
		page.on( 'request', ( request ) => requestedHosts.push( new URL( request.url() ).host ) );
		await openSeeded( page, archivePage( 2 ) );
		const control = await skeleton( pageEntry( page, 'A10' ) );
		for ( const key of [ 'A08', 'A09', 'A18' ] ) expect( await skeleton( pageEntry( page, key ) ), `${ key } matches A10` ).toEqual( control );
		const text = await visibleText( page.locator( 'main' ) );
		expect( text, 'privacy labels stay out of visible text' ).not.toMatch( /\b(?:Public|Private|hidden|observation)\b/ );
		for ( const leak of [ 'Sampletown', 'Example Hall', 'Example Avenue' ] ) expect( text, `${ leak } is absent` ).not.toContain( leak );
		const a01 = pageEntry( page, 'A01' ).locator( '.pkiw-almanac__mark' );
		await expect( a01, 'A01 mark code' ).toHaveAttribute( 'data-pkiw-weather-icon', 'wi-day-cloudy' );
		const a01Image = await a01.evaluate( ( node ) => getComputedStyle( node ).backgroundImage );
		expect( a01Image, 'A01 uses cloud art' ).toContain( 'cloud.svg' );
		expect( a01Image, 'A01 uses sun art' ).toContain( 'sun.svg' );
		expect( await pageEntry( page, 'A02' ).locator( '.pkiw-almanac__mark' ).evaluate( ( node ) => getComputedStyle( node ).backgroundImage ), 'A02 uses rain art' ).toContain( 'rain.svg' );
		await expect( pageEntry( page, 'A14' ).locator( '.pkiw-almanac__mark' ), 'A14 has no code-backed mark' ).toHaveCount( 0 );
		await openSeeded( page, ARCHIVE );
		expect( await skeleton( pageEntry( page, 'S4' ) ), 'S4 matches S5' ).toEqual( await skeleton( pageEntry( page, 'S5' ) ) );
		for ( const number of [ 1, 2, 3, 4 ] ) {
			await openSeeded( page, archivePage( number ) );
			const pageText = await visibleText( page.locator( 'main' ) );
			expect( pageText, `page ${ number } has no place text` ).not.toMatch( /Sampletown|Example Hall|Example Avenue/ );
			await expect( page.locator( '.sloc-map, .leaflet-container' ), `page ${ number } has no map` ).toHaveCount( 0 );
		}
		const ownHost = new URL( page.url() ).host;
		const tileLikeHosts = [ ...new Set( requestedHosts ) ].filter( ( host ) => host !== ownHost && /tile|mapbox|openstreetmap|weather|geocod/i.test( host ) );
		expect( tileLikeHosts, 'archive makes no weather, geocoding, or tile-host request' ).toEqual( [] );
	} );

	test( 'sheet and chips keep paper paint in both schemes, with hard scheme-aware shadows and 44px entries', async ( { page }, testInfo ) => {
		await openSeeded( page, archivePage( 2 ) );
		const sheet = page.locator( '.pkiw-almanac' );
		const paint = await sheet.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { background: style.backgroundColor, color: style.color, border: style.borderTopWidth, shadow: style.boxShadow };
		} );
		expect( paint.background, 'paper stays ivory' ).toBe( IVORY );
		expect( paint.color, 'paper keeps violet ink' ).toBe( INK );
		expect( paint.border, 'sheet border' ).toBe( '2px' );
		expect( paint.shadow, 'scheme-aware hard shadow' ).toBe( `${ isDark( testInfo ) ? SELECTIVE_YELLOW : INK } 4px 4px 0px 0px` );
		expect( await pageEntry( page, 'A01' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ), 'marked chip is sky blue' ).toBe( SKY_BLUE );
		expect( await pageEntry( page, 'A10' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ), 'plain chip is light orange' ).toBe( LIGHT_ORANGE );
		expect( await page.locator( 'li.pkiw-almanac__entry' ).evaluateAll( ( nodes ) => nodes.every( ( node ) => node.getBoundingClientRect().height >= 44 ) ), 'all entries are 44px tall' ).toBe( true );
		if ( isDark( testInfo ) ) expect( await page.locator( 'body' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ), 'dark page ground' ).toBe( PAGE_DARK );
	} );

	test( 'tab order runs from entries through year links to pager links, focus rings the chip, and reduced motion removes lift', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await openSeeded( page, archivePage( 2 ) );
		const expected = await page.evaluate( () => [
			...document.querySelectorAll( 'li.pkiw-almanac__entry a[href]' ),
			...document.querySelectorAll( 'nav.pkiw-year-strip a[href]' ),
			...document.querySelectorAll( 'nav[aria-label="Pagination"] a[href]' ),
		].filter( ( node ) => ! node.closest( '[hidden]' ) ).map( ( node ) => node.href ) );
		const first = page.locator( 'li.pkiw-almanac__entry a[href]' ).first();
		await first.focus();
		const actual = [ await first.getAttribute( 'href' ) ];
		for ( let index = 1; index < expected.length; index++ ) {
			await page.keyboard.press( 'Tab' );
			actual.push( await page.evaluate( () => document.activeElement?.getAttribute( 'href' ) || '' ) );
		}
		expect( actual.map( ( href ) => new URL( href, 'https://example.invalid' ).pathname + new URL( href, 'https://example.invalid' ).search ), 'tab sequence' ).toEqual( expected.map( ( href ) => new URL( href ).pathname + new URL( href ).search ) );
		await first.focus();
		const ring = await first.locator( 'xpath=ancestor::li[contains(@class,"pkiw-almanac__entry")]' ).evaluate( ( node ) => ( {
			width: getComputedStyle( node ).outlineWidth,
			color: getComputedStyle( node ).outlineColor,
			shadow: getComputedStyle( node ).boxShadow,
		} ) );
		expect( ring.width, 'focus outline is 3px' ).toBe( '3px' );
		expect( ring.color, 'focus outline is yellow' ).toBe( SELECTIVE_YELLOW );
		expect( ring.shadow, 'focus halo is ink' ).toContain( INK );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		const entry = page.locator( 'li.pkiw-almanac__entry' ).first();
		await entry.hover();
		expect( await entry.evaluate( ( node ) => getComputedStyle( node ).translate ), 'reduced motion removes lift' ).toBe( 'none' );
	} );

	test( 'forced colors keeps two-pixel sheet and chip borders, drops art, and preserves current-month and pager borders', async ( { page } ) => {
		await openSeeded( page, archivePage( 2 ) );
		await page.emulateMedia( { forcedColors: 'active' } );
		for ( const locator of [ page.locator( '.pkiw-almanac' ), page.locator( 'li.pkiw-almanac__entry' ).first() ] ) {
			const border = await locator.evaluate( ( node ) => ( { width: getComputedStyle( node ).borderTopWidth, style: getComputedStyle( node ).borderTopStyle } ) );
			expect( border, 'forced-colors border' ).toEqual( { width: '2px', style: 'solid' } );
		}
		expect( await page.locator( '.pkiw-almanac__mark' ).first().evaluate( ( node ) => getComputedStyle( node ).display ), 'marks drop' ).toBe( 'none' );
		expect( await page.locator( '.pkiw-almanac__weekdays' ).evaluate( ( node ) => getComputedStyle( node ).display ), 'weekday decoration drops' ).toBe( 'none' );
		expect( await page.locator( '.cr-weather-almanac' ).evaluate( ( node ) => [ getComputedStyle( node, '::before' ).display, getComputedStyle( node, '::after' ).display ] ), 'still life drops' ).toEqual( [ 'none', 'none' ] );
		expect( await page.locator( '.pkiw-year-strip [aria-current="page"]' ).evaluate( ( node ) => Number.parseFloat( getComputedStyle( node ).borderTopWidth ) > 0 ), 'current month stays bordered' ).toBe( true );
		expect( await page.locator( 'nav[aria-label="Pagination"] [aria-current="page"]' ).evaluate( ( node ) => Number.parseFloat( getComputedStyle( node ).borderTopWidth ) > 0 ), 'current page stays bordered' ).toBe( true );
	} );

	test( 'still life is text-free, uses side gutters from 1024, a six-rem bottom strip at 1000, and disappears at 639', async ( { page } ) => {
		for ( const width of [ 1440, 1024, 1000, 639 ] ) {
			await page.setViewportSize( { width, height: 1000 } );
			await openSeeded( page, archivePage( 2 ) );
			const state = await page.locator( '.cr-weather-almanac' ).evaluate( ( node ) => {
				const before = getComputedStyle( node, '::before' );
				const after = getComputedStyle( node, '::after' );
				return { before: { content: before.content, display: before.display, width: before.width, height: before.height }, after: { content: after.content, display: after.display, width: after.width, height: after.height } };
			} );
			expect( state.before.content, `${ width } before has no text` ).toBe( '""' );
			expect( state.after.content, `${ width } after has no text` ).toBe( '""' );
			if ( width >= 1024 ) {
				expect( [ state.before.display, state.after.display ], `both side pieces show at ${ width }` ).toEqual( [ 'block', 'block' ] );
				const clear = await page.locator( '.cr-weather-almanac' ).evaluate( ( wrapper ) => {
					const box = wrapper.getBoundingClientRect();
					const sheet = wrapper.querySelector( 'section.pkiw-almanac' ).getBoundingClientRect();
					const pager = wrapper.querySelector( 'nav[aria-label="Pagination"]' ).getBoundingClientRect();
					const before = getComputedStyle( wrapper, '::before' );
					const after = getComputedStyle( wrapper, '::after' );
					const left = { left: box.left, right: box.left + parseFloat( before.width ), top: box.top, bottom: box.bottom };
					const right = { left: box.right - parseFloat( after.width ), right: box.right, top: box.top, bottom: box.bottom };
					const intersects = ( a, b ) => a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
					return ! intersects( left, sheet ) && ! intersects( right, sheet ) && ! intersects( left, pager ) && ! intersects( right, pager );
				} );
				expect( clear, 'side pieces do not overlap the sheet or pager' ).toBe( true );
			} else if ( width === 1000 ) {
				expect( state.before.display, '1000 has no top or side piece' ).toBe( 'none' );
				expect( state.after.display, '1000 shows the bottom strip' ).toBe( 'block' );
				expect( state.after.height, 'bottom strip is six rem' ).toBe( '96px' );
			} else {
				expect( [ state.before.display, state.after.display ], 'all still life drops below 40rem' ).toEqual( [ 'none', 'none' ] );
			}
		}
	} );

	test( 'empty archive uses only the existing no-results copy and no almanac, year strip, or pager', async ( { page } ) => {
		await page.goto( ARCHIVE, { waitUntil: 'load' } );
		const count = await page.locator( 'li.pkiw-almanac__entry' ).count();
		test.skip( count !== 0, 'empty archive runs before seeding and after release, not on the seeded stack' );
		await expect( page.locator( 'main' ), 'empty copy' ).toContainText( 'Nothing here yet.' );
		await expect( page.locator( '.pkiw-almanac, .pkiw-year-strip, nav[aria-label="Pagination"]' ), 'no archive furniture' ).toHaveCount( 0 );
	} );

	test( 'page 2 has no serious or critical axe violations in the project scheme', async ( { page } ) => {
		await openSeeded( page, archivePage( 2 ) );
		const violations = await axeGatingViolations( page );
		expect( violations, `axe gating violations: ${ JSON.stringify( violations ) }` ).toEqual( [] );
	} );
} );

test.describe( 'single', () => {
	test( 'S1 has the safe untitled heading, one station log in order, decorative art, and no maps or outbound tile requests', async ( { page } ) => {
		const hosts = [];
		page.on( 'request', ( request ) => hosts.push( new URL( request.url() ).host ) );
		await openSeeded( page, single( 'S1' ) );
		const headings = page.locator( 'main h1' );
		await expect( headings, 'one main heading' ).toHaveCount( 1 );
		await expect( headings, 'untitled weather name' ).toHaveText( /^Weather, .+ at .+$/ );
		expect( await headings.evaluate( ( node ) => Boolean( node.closest( '.p-name' ) || node.matches( '.p-name' ) ) ), 'H1 is not p-name' ).toBe( false );
		for ( const value of await page.evaluate( () => [
			document.title,
			document.querySelector( 'meta[property="og:title"]' )?.content || '',
			[ ...document.querySelectorAll( 'script[type="application/ld+json"]' ) ].map( ( node ) => node.textContent ).join( ' ' ),
		] ) ) expect( value, 'head title data omits weather and place facts' ).not.toMatch( /Partly Cloudy|°F|Sampletown/ );
		const log = page.locator( 'dl.cr-weather-log' );
		await expect( log, 'one station log' ).toHaveCount( 1 );
		const order = await log.evaluate( ( node ) => {
			const content = document.querySelector( '.single-post__content' );
			const meta = document.querySelector( '.single-post__meta' );
			return Boolean( content && meta && ( content.compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) && ( node.compareDocumentPosition( meta ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
		} );
		expect( order, 'log follows content and precedes meta' ).toBe( true );
		await expect( log.locator( 'dt' ).first(), 'Date is first' ).toHaveText( 'Date' );
		const text = await visibleText( page.locator( 'main' ) );
		expect( text, 'single omits internal labels and highs or lows' ).not.toMatch( /\b(?:Observed|Saved observation|Privacy|City-level|High|Low)\b/i );
		const art = page.locator( '.cr-weather-art' );
		await expect( art, 'condition art exists' ).toHaveCount( 1 );
		await expect( art, 'condition art is decorative' ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( await log.evaluate( ( node ) => Boolean( node.compareDocumentPosition( document.querySelector( '.cr-weather-art' ) ) & Node.DOCUMENT_POSITION_FOLLOWING ) ), 'art follows the log' ).toBe( true );
		await expect( page.locator( '.sloc-map, .leaflet-container' ), 'no map' ).toHaveCount( 0 );
		const ownHost = new URL( page.url() ).host;
		expect( [ ...new Set( hosts ) ], 'all requests stay on the site host' ).toEqual( [ ownHost ] );
	} );

	test( 'A15 and A16 expose the shipped row labels and values in their fixed order', async ( { page } ) => {
		await openSeeded( page, single( 'A15' ) );
		expect( await page.locator( 'dl.cr-weather-log dt' ).allTextContents(), 'A15 row order' ).toEqual( [ 'Date', 'Weather Summary', 'Temperature', 'Wind Speed', 'Wind Direction', 'Humidity', 'Atmospheric Pressure', 'Cloudiness', 'Rainfall', 'Place' ] );
		const direction = page.locator( 'dl.cr-weather-log dt', { hasText: /^Wind Direction$/ } ).locator( 'xpath=following-sibling::dd[1]' );
		await expect( direction, 'A15 wind direction' ).toHaveText( '315°' );
		await openSeeded( page, single( 'A16' ) );
		expect( await page.locator( 'dl.cr-weather-log dt' ).allTextContents(), 'A16 row order' ).toEqual( [ 'Date', 'Temperature', 'Place' ] );
	} );

	test( 'place tiers keep venue detail, streets, coordinates, and private observations within their contracts', async ( { page } ) => {
		await openSeeded( page, single( 'A12' ) );
		const place = page.locator( 'dl.cr-weather-log dt', { hasText: /^Place$/ } ).locator( 'xpath=following-sibling::dd[1]' );
		await expect( place, 'A12 public-tier place' ).toHaveText( 'Example Hall, Sampletown, Example Region, Exampleland' );
		expect( await page.locator( 'dl.cr-weather-log' ).innerHTML(), 'A12 omits street' ).not.toContain( '100 Example Avenue' );
		await openSeeded( page, single( 'M01' ) );
		await expect( page.locator( 'dl.cr-weather-log dt', { hasText: /^Place$/ } ), 'M01 has no Place row' ).toHaveCount( 0 );
		const html = await page.content();
		expect( html, 'M01 omits street' ).not.toContain( '200 Sample Street' );
		expect( html, 'M01 omits coordinate pair' ).not.toMatch( /0\.2\s*[,;]?\s*0\.2/ );
		expect( html, 'M01 omits geo.position' ).not.toContain( 'geo.position' );
		const m01Rows = await page.locator( 'dl.cr-weather-log dt' ).allTextContents();
		expect( m01Rows, 'M01 prints Weather Summary' ).toContain( 'Weather Summary' );
		expect( m01Rows, 'M01 prints Temperature' ).toContain( 'Temperature' );
		await openSeeded( page, single( 'A08' ) );
		// wave-lanes-w4.md says date-only for logged-out A08, but shipped class-meta-fields.php exposes the approximate tier when privacy is unset.
		expect( await page.locator( 'dl.cr-weather-log dt' ).allTextContents(), 'A08 follows shipped approximate-tier behavior' ).toEqual( [ 'Date', 'Place' ] );
		await openSeeded( page, single( 'A09' ) );
		expect( await page.locator( 'dl.cr-weather-log dt' ).allTextContents(), 'A09 is date-only' ).toEqual( [ 'Date' ] );
	} );

	test( 'protected and hidden-art fixtures expose only permitted log rows and instruments', async ( { page } ) => {
		await openSeeded( page, single( 'A13' ) );
		expect( await page.locator( 'input[type="password"], .post-password-form' ).count(), 'A13 password form' ).toBeGreaterThan( 0 );
		const protectedRows = await page.locator( 'dl.cr-weather-log dt' ).allTextContents();
		expect( protectedRows, 'A13 log is absent or date-only' ).toEqual( protectedRows.length ? [ 'Date' ] : [] );
		expect( await page.content(), 'A13 has no weather rows in the document' ).not.toMatch( /Weather Summary|Temperature|Wind Speed|Humidity|Atmospheric Pressure|Cloudiness|Rainfall/ );
		await openSeeded( page, single( 'A14' ) );
		await expect( page.locator( '.cr-weather-art' ), 'A14 has no code-backed art' ).toHaveCount( 0 );
		await expect( page.locator( '.cr-weather-thermo' ), 'A14 keeps its thermometer' ).toHaveCount( 1 );
		for ( const key of [ 'A15', 'A16' ] ) {
			await openSeeded( page, single( key ) );
			await expect( page.locator( '.cr-weather-thermo' ), `${ key } has a thermometer` ).toHaveCount( 1 );
		}
		await openSeeded( page, single( 'A18' ) );
		await expect( page.locator( '.cr-weather-art, .cr-weather-thermo' ), 'A18 has no logged-out instruments' ).toHaveCount( 0 );
	} );

	test( 'station log is a two-column board at 1440 and stacks definition pairs without overflow at 375 and 320', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await openSeeded( page, single( 'A15' ) );
		const station = page.locator( '.cr-weather-station' );
		expect( await station.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length ), 'station has two columns' ).toBe( 2 );
		expect( await station.locator( 'dl.cr-weather-log' ).evaluate( ( node ) => getComputedStyle( node ).gridColumnStart ), 'log is in column one' ).toBe( '1' );
		for ( const width of [ 375, 320 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await openSeeded( page, single( 'A15' ) );
			const pair = await page.locator( 'dl.cr-weather-log' ).evaluate( ( node ) => {
				const dt = node.querySelector( 'dt' ).getBoundingClientRect();
				const dd = node.querySelector( 'dd' ).getBoundingClientRect();
				return { dtBottom: dt.bottom, ddTop: dd.top };
			} );
			expect( pair.dtBottom, `${ width }px dt sits above dd` ).toBeLessThanOrEqual( pair.ddTop + 1 );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), `${ width }px no horizontal scroll` ).toBe( true );
		}
	} );

	test( 'station-log paper keeps light-orange and violet paint, scheme-aware hard shadow, and AA cerulean labels', async ( { page }, testInfo ) => {
		await openSeeded( page, single( 'A15' ) );
		const paint = await page.locator( 'dl.cr-weather-log' ).evaluate( ( node ) => {
			const style = getComputedStyle( node );
			const dt = getComputedStyle( node.querySelector( 'dt' ) );
			return { background: style.backgroundColor, color: style.color, shadow: style.boxShadow, label: dt.color };
		} );
		expect( paint.background, 'log paper' ).toBe( LIGHT_ORANGE );
		expect( paint.color, 'log ink' ).toBe( INK );
		expect( paint.shadow, 'log shadow' ).toBe( `${ isDark( testInfo ) ? SELECTIVE_YELLOW : INK } 4px 4px 0px 0px` );
		expect( paint.label, 'dt cerulean' ).toBe( CERULEAN );
		expect( contrast( paint.label, paint.background ), 'dt contrast' ).toBeGreaterThanOrEqual( 4.5 );
	} );

	test( 'forced colors gives the log a two-pixel border and removes thermometer and condition art', async ( { page } ) => {
		await openSeeded( page, single( 'A15' ) );
		await page.emulateMedia( { forcedColors: 'active' } );
		const border = await page.locator( 'dl.cr-weather-log' ).evaluate( ( node ) => ( { width: getComputedStyle( node ).borderTopWidth, style: getComputedStyle( node ).borderTopStyle } ) );
		expect( border, 'forced log border' ).toEqual( { width: '2px', style: 'solid' } );
		expect( await page.locator( '.cr-weather-thermo' ).evaluate( ( node ) => getComputedStyle( node ).display ), 'thermometer drops' ).toBe( 'none' );
		expect( await page.locator( '.cr-weather-art' ).evaluate( ( node ) => getComputedStyle( node ).display ), 'condition art drops' ).toBe( 'none' );
	} );

	test( 'post navigation follows comments and names untitled weather neighbors with date and time', async ( { page } ) => {
		await openSeeded( page, single( 'S1' ) );
		const nav = page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		await expect( nav, 'one post nav' ).toHaveCount( 1 );
		expect( await nav.evaluate( ( node ) => {
			const comments = document.querySelector( '.single-post__comments-wrapper' );
			return ! comments || Boolean( comments.compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING );
		} ), 'nav follows comments' ).toBe( true );
		for ( const link of await nav.locator( 'a' ).all() ) {
			const text = ( await link.textContent() ).replace( /\s+/g, ' ' ).trim();
			if ( /Weather/.test( text ) ) expect( text, 'untitled neighbor name' ).toMatch( /Weather, .+ at .+/ );
		}
	} );

	for ( const key of [ 'S1', 'A15' ] ) {
		test( `${ key } has no serious or critical axe violations in the project scheme`, async ( { page } ) => {
			await openSeeded( page, single( key ) );
			const violations = await axeGatingViolations( page );
			expect( violations, `axe gating violations: ${ JSON.stringify( violations ) }` ).toEqual( [] );
		} );
	}

	test.describe( 'logged in', () => {
		if ( REQUIRE_SEED ) {
			test( 'CR_WEATHER_STORAGE_STATE is set', () => {
				expect( STORAGE_STATE, 'CR_WEATHER_STORAGE_STATE is not set' ).toBeTruthy();
			} );
		} else {
			test.skip( ! STORAGE_STATE, 'CR_WEATHER_STORAGE_STATE is not set' );
		}
		test.use( { storageState: STORAGE_STATE || undefined } );

		test( 'administrator sees A08 and A09 observations while A09 keeps Place private, and can read private A19', async ( { page } ) => {
			await openSeeded( page, single( 'A08' ) );
			let rows = await page.locator( 'dl.cr-weather-log dt' ).allTextContents();
			expect( rows, 'A08 administrator weather summary' ).toContain( 'Weather Summary' );
			expect( rows, 'A08 administrator temperature' ).toContain( 'Temperature' );
			await openSeeded( page, single( 'A09' ) );
			rows = await page.locator( 'dl.cr-weather-log dt' ).allTextContents();
			expect( rows, 'A09 administrator weather summary' ).toContain( 'Weather Summary' );
			expect( rows, 'A09 administrator temperature' ).toContain( 'Temperature' );
			expect( rows, 'A09 keeps Place private' ).not.toContain( 'Place' );
			const response = await page.goto( single( 'A19' ), { waitUntil: 'load' } );
			expect( response?.status(), 'A19 private single is viewable' ).toBe( 200 );
			await expect( page.locator( 'main' ), 'A19 page renders' ).toBeVisible();
		} );
	} );
} );

test.describe( 'stream', () => {
	test( 'weather cards use one-link compact content, correct readings, matching private skeletons, and no media or detail leaks', async ( { page } ) => {
		await openSeeded( page, STREAM );
		const cards = page.locator( UNPROTECTED_CARD );
		requireFixture( await cards.count() === 0, 'no weather card is on this Stream page; load the stand-in or enable weather routing' );
		for ( let index = 0; index < await cards.count(); index++ ) {
			const card = cards.nth( index );
			expect( await renderedLinks( card ), `card ${ index } rendered links` ).toMatchObject( { count: 1, stray: 0 } );
			await expect( card, `card ${ index } thermometer class` ).toHaveClass( /\bcr-thermo\b/ );
			const text = await visibleText( card );
			expect( ( text.match( /\bWeather\b/g ) || [] ).length, `card ${ index } Weather count` ).toBeLessThanOrEqual( 1 );
			expect( text, `card ${ index } detail cuts` ).not.toMatch( /Read more|→|←|↗|Sampletown|Example Hall|Example Avenue|\bat\s+\d{1,2}:\d{2}\b/i );
			await expect( card.locator( '.pk-meta, .pk-excerpt, .wp-block-post-excerpt' ), `card ${ index } no metadata or excerpt` ).toHaveCount( 0 );
			await expect( card.locator( 'nav[aria-label="Pagination"]' ), `card ${ index } no pager` ).toHaveCount( 0 );
		}
		await expect( streamCard( page, 'S1' ).locator( '.pk-title a' ), 'S1 heading' ).toContainText( 'Partly Cloudy, 80 °F' );
		await expect( streamCard( page, 'S2' ).locator( '.pk-title a' ), 'S2 heading' ).toContainText( 'Light rain, cool' );
		await expect( streamCard( page, 'S3' ).locator( '.p-weather' ), 'S3 body reading' ).toContainText( 'Partly Cloudy, 80 °F' );
		for ( const key of [ 'S4', 'S5' ] ) await expect( streamCard( page, key ).locator( '.pk-title a' ), `${ key } heading` ).toContainText( 'Weather' );
		expect( await skeleton( streamCard( page, 'S4' ) ), 'S4 matches S5' ).toEqual( await skeleton( streamCard( page, 'S5' ) ) );
		await expect( streamCard( page, 'S6' ).locator( 'img' ), 'S6 card has no image' ).toHaveCount( 0 );
		await expect( streamCard( page, 'S1' ).locator( '.cr-thermo__glass' ), 'S1 has one glass' ).toHaveCount( 1 );
		await expect( streamCard( page, 'S1' ).locator( '.cr-thermo__glass' ), 'glass is decorative and empty' ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( ( await streamCard( page, 'S1' ).locator( '.cr-thermo__glass' ).textContent() ).trim(), 'glass has no text' ).toBe( '' );
		for ( const key of [ 'S2', 'S5' ] ) await expect( streamCard( page, key ).locator( '.cr-thermo__glass' ), `${ key } has no glass` ).toHaveCount( 0 );
	} );

	test( 'the plugin\'s protected A13 card gets no thermometer, no glass, and exactly one rendered link', async ( { page } ) => {
		const card = await openStreamCard( page, 'A13' );
		await expect( card, 'A13 is the plugin protected card' ).toHaveClass( /\bpk-card--protected\b/ );
		await expect( card, 'protected card has no cr-thermo class' ).not.toHaveClass( /(?:^|\s)cr-thermo(?:\s|$)/ );
		await expect( card.locator( '.cr-thermo__glass' ), 'protected card has no glass' ).toHaveCount( 0 );
		expect( await renderedLinks( card ), 'protected card rendered links' ).toMatchObject( { count: 1, stray: 0 } );
		const text = await visibleText( card );
		expect( text, 'protected card prints no reading or place' ).not.toMatch( /Partly Cloudy|°F|Sampletown|Example Hall|Example Avenue/ );
	} );

	test( 'cards fit collage columns and content, keep paper paint and two rotated layers, and the collage has no timeline', async ( { page }, testInfo ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await openSeeded( page, STREAM );
		const cards = page.locator( UNPROTECTED_CARD );
		requireFixture( await cards.count() === 0, 'no weather card is on this Stream page' );
		for ( let index = 0; index < await cards.count(); index++ ) {
			const card = cards.nth( index );
			const state = await card.evaluate( ( node ) => {
				const style = getComputedStyle( node );
				const tape = getComputedStyle( node, '::before' );
				const li = node.closest( 'li' );
				const list = node.closest( '.wp-block-post-template' );
				return {
					aspect: style.aspectRatio,
					width: node.getBoundingClientRect().width,
					column: li.getBoundingClientRect().width,
					heightFits: node.scrollHeight <= node.clientHeight + 1,
					background: style.backgroundColor,
					border: [ style.borderTopWidth, style.borderTopColor ],
					shadow: style.boxShadow,
					cardTransform: style.transform,
					tapeTransform: tape.transform,
					rotatedChildren: [ ...node.querySelectorAll( '*' ) ].filter( ( child ) => ! [ 'none', 'matrix(1, 0, 0, 1, 0, 0)' ].includes( getComputedStyle( child ).transform ) ).length,
					columnWidth: list ? getComputedStyle( list ).columnWidth : 'auto',
					timeline: Boolean( node.closest( '.cr-timeline' ) ),
				};
			} );
			expect( state.aspect, `card ${ index } aspect ratio` ).toBe( 'auto' );
			expect( state.width, `card ${ index } fits its li` ).toBeLessThanOrEqual( state.column + 1 );
			expect( state.heightFits, `card ${ index } fits content` ).toBe( true );
			expect( state.background, `card ${ index } paper` ).toBe( IVORY );
			expect( state.border, `card ${ index } violet border` ).toEqual( [ '2px', INK ] );
			expect( state.shadow, `card ${ index } scheme shadow` ).toBe( `${ isDark( testInfo ) ? SELECTIVE_YELLOW : INK } 4px 4px 0px 0px` );
			expect( allowedRotation( rotation( state.tapeTransform ) ), `card ${ index } tape rotation` ).toBe( true );
			expect( allowedRotation( rotation( state.cardTransform ), true ), `card ${ index } collage rotation` ).toBe( true );
			expect( state.rotatedChildren, `card ${ index } has no third rotated layer` ).toBe( 0 );
			expect( state.columnWidth, `card ${ index } sits in a multicolumn list` ).not.toBe( 'auto' );
			expect( state.timeline, `card ${ index } has no timeline ancestor` ).toBe( false );
		}
	} );

	test( 'card focus rings the article, and forced colors keeps a two-pixel border while dropping glass, marks, and tape', async ( { page } ) => {
		await openSeeded( page, STREAM );
		const card = page.locator( UNPROTECTED_CARD ).first();
		requireFixture( await card.count() === 0, 'no weather card is on this Stream page' );
		await card.locator( 'a[href]' ).focus();
		const ring = await card.evaluate( ( node ) => ( { width: getComputedStyle( node ).outlineWidth, color: getComputedStyle( node ).outlineColor, shadow: getComputedStyle( node ).boxShadow } ) );
		expect( ring.width, 'card focus outline' ).toBe( '3px' );
		expect( ring.color, 'card focus color' ).toBe( SELECTIVE_YELLOW );
		expect( ring.shadow, 'card focus halo' ).toContain( INK );
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await card.evaluate( ( node ) => ( {
			border: [ getComputedStyle( node ).borderTopWidth, getComputedStyle( node ).borderTopStyle ],
			tape: getComputedStyle( node, '::before' ).display,
		} ) );
		expect( forced.border, 'forced card border' ).toEqual( [ '2px', 'solid' ] );
		expect( forced.tape, 'tape drops' ).toBe( 'none' );
		for ( const selector of [ '.cr-thermo__glass', '.pk-weather-mark' ] ) {
			const item = card.locator( selector );
			await expect( item, `${ selector } exists on the visible-observation card` ).toHaveCount( 1 );
			expect( await item.evaluate( ( node ) => getComputedStyle( node ).display ), `${ selector } drops` ).toBe( 'none' );
		}
	} );

	test( 'weather cards and their collage columns do not overflow at 320px', async ( { page } ) => {
		await page.setViewportSize( { width: 320, height: 900 } );
		await openSeeded( page, STREAM );
		const cards = page.locator( UNPROTECTED_CARD );
		requireFixture( await cards.count() === 0, 'no weather card is on this Stream page' );
		expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), 'page does not overflow' ).toBe( true );
		expect( await cards.evaluateAll( ( nodes ) => nodes.every( ( node ) => {
			const li = node.closest( 'li' );
			return node.scrollWidth <= node.clientWidth + 1 && li.scrollWidth <= li.clientWidth + 1;
		} ) ), 'cards and list items do not overflow' ).toBe( true );
	} );
} );

test.describe( 'static files', () => {
	test( 'weather CSS uses tokens, approved rotations, no forbidden effects, and no text below 0.75rem', async () => {
		expect( checkWeatherCss(), 'cr-weather.css static failures' ).toEqual( [] );
	} );

	test( 'thirteen weather SVGs parse with viewBoxes, no text or effects, and lowercase palette colors', async () => {
		expect( checkWeatherSvgs(), 'weather SVG static failures' ).toEqual( [] );
	} );

	test( 'weather PHP attaches the calendar dependency and contains no post, meta, or term writes', async () => {
		expect( checkWeatherPhp(), 'inc/weather.php static failures' ).toEqual( [] );
	} );
} );
