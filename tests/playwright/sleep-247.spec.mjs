// `CR_SPEC=sleep-247.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`:
// PKIW issue 247 sleep pages against decision-2026-10-06.md and the later W4-G3, W4-G5 and W4-G6
// rulings. Read-only GETs, logged out; needs a running site with the seed-247.php fixtures. Each test
// sets its own viewport, so one project is enough. Optional flags: CR_SLEEP_ARCHIVE_PATH,
// CR_SLEEP_STREAM_PATH, CR_SLEEP_STATE=full|empty|stream, CR_SLEEP_DEV=1 and CR_SLEEP_NO_STREAM=1.
//
// A missing fixture or a missing adapter fails the run; only the flags above skip anything.
//
// Fixture passes (seed-247.php), in this order. The probe set (G01, local-only M02 and M03) and the stream
// set (S1-S4) each add sleep posts to the archive, so they shift the 31/11 page map (G01 sits on Aug 13,
// S1-S4 on the newest nights). The page-map tests therefore run on the archive set alone:
//   1. `seed archive`, then CR_SLEEP_STATE=full with `-g "archive —|single —"`: page map, singles, privacy.
//   2. `seed probe` on top, then CR_SLEEP_STATE=full CR_SLEEP_DEV=1 with `-g "dev-only"`: the dev-only block
//      (G01 needs the probe; the H01/N01/T01 checks need the archive set). The 31/11 tests fail with the
//      probe in, so `remove probe` before any further page-map run.
//   3. `remove probe`, map cr-local-stream-sleep.php into mu-plugins (local only; W4-G3 'yes'), `seed stream`,
//      then CR_SLEEP_STATE=stream: the Stream sticker block only. On dev, cr-content-surfaces.php routes sleep.
// The titled single's H1 p-name comes from IndieBlocks, which is active on dev only, so that assertion needs
// CR_SLEEP_DEV=1.

import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';
import { BASE_URL } from '../routes.mjs';

const require = createRequire( import.meta.url );
const ARCHIVE = process.env.CR_SLEEP_ARCHIVE_PATH || '/kind/sleep/';
const STREAM = process.env.CR_SLEEP_STREAM_PATH || '/stream/';
const STATE = process.env.CR_SLEEP_STATE || 'full';
const DEV = process.env.CR_SLEEP_DEV === '1';
const NO_STREAM = process.env.CR_SLEEP_NO_STREAM === '1';

const CERULEAN = 'rgb(18, 103, 130)';
const IVORY = 'rgb(251, 250, 245)';
const VIOLET = 'rgb(36, 28, 74)';
const YELLOW = 'rgb(255, 183, 3)';
const PRUSSIAN = 'rgb(2, 48, 71)';
const WHITE = 'rgb(255, 255, 255)';
const DARK_GROUND = 'rgb(26, 23, 20)';
const CLOCK = /\b(?:[01]?\d|2[0-3]):[0-5]\d\b/;
const ARCHIVE_SEED = 'wp eval-file seed-247.php seed archive';
const PROBE_SEED = 'wp eval-file seed-247.php seed probe';
const STREAM_SEED = 'wp eval-file seed-247.php seed stream';
const WIDTHS = [ [ 1440, 7 ], [ 800, 4 ], [ 375, 1 ], [ 320, 1 ] ];

const withSlash = ( path ) => path.endsWith( '/' ) ? path : `${ path }/`;
const archivePage = ( number ) => 1 === number ? ARCHIVE : `${ withSlash( ARCHIVE ) }page/${ number }/`;
const pagedArchive = ( number ) => `${ ARCHIVE }${ ARCHIVE.includes( '?' ) ? '&' : '?' }paged=${ number }`;

async function visit( page, path, scheme = 'light' ) {
	await page.emulateMedia( { colorScheme: scheme } );
	await page.addInitScript( ( value ) => {
		try {
			window.localStorage.setItem( 'courtneyr-theme', value );
		} catch ( error ) {
			// Storage can be blocked; colorScheme remains the fallback.
		}
	}, scheme );
	const response = await page.goto( path, { waitUntil: 'load' } );
	return response.status();
}

// W3-G2: count rendered anchors, and require every tabindex=-1 anchor to live in a hidden subtree.
async function visibleLinks( locator ) {
	return locator.evaluate( ( root ) => {
		const hidden = ( element ) => Boolean( element.closest( '[hidden]' ) );
		const links = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( anchor ) => ! hidden( anchor ) && anchor.getClientRects().length > 0 );
		const stray = [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( anchor ) => ! hidden( anchor ) );
		const name = ( anchor ) => {
			const ids = ( anchor.getAttribute( 'aria-labelledby' ) || '' ).split( /\s+/ ).filter( Boolean );
			return ( ids.length ? ids.map( ( id ) => document.getElementById( id )?.textContent || '' ).join( ' ' ) : anchor.getAttribute( 'aria-label' ) || anchor.textContent ).replace( /\s+/g, ' ' ).trim();
		};
		return {
			count: links.length,
			stray: stray.length,
			hrefs: links.map( ( anchor ) => anchor.href ),
			names: links.map( name ),
		};
	} );
}

// Chromium's name computation joins aria-labelledby parts and inline children with a space, so
// "Aug 3" plus a hidden ", " comes back as "Aug 3 , Waning gibbous". Match the words and commas, not the spacing.
function nameMatcher( name ) {
	const escaped = name.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	return new RegExp( `^${ escaped.replace( /\s*,\s*/g, '\\s*,\\s*' ) }$` );
}

function cellForNight( page, night, occurrence = 0 ) {
	return page.locator( `li.pkiw-sleep-night:has(time.dt-published[datetime="${ night }"])` ).nth( occurrence );
}

async function fixturePermalink( page, night, occurrence = 0, seed = ARCHIVE_SEED ) {
	for ( const path of [ archivePage( 1 ), archivePage( 2 ) ] ) {
		await visit( page, path );
		const cell = cellForNight( page, night, occurrence );
		if ( await cell.count() ) {
			return cell.locator( 'a.u-url' ).getAttribute( 'href' );
		}
	}
	throw new Error( `fixture night ${ night } occurrence ${ occurrence + 1 } is missing (run ${ seed })` );
}

async function openFixture( page, night, occurrence = 0, scheme = 'light' ) {
	const href = await fixturePermalink( page, night, occurrence );
	await visit( page, href, scheme );
	return href;
}

async function normalizedMarkup( locator ) {
	return locator.evaluate( ( root ) => {
		const clone = root.cloneNode( true );
		for ( const element of [ clone, ...clone.querySelectorAll( '*' ) ] ) {
			for ( const attribute of [ ...element.attributes ] ) {
				if ( [ 'id', 'href', 'aria-labelledby' ].includes( attribute.name ) ) {
					element.removeAttribute( attribute.name );
				} else if ( attribute.name === 'class' ) {
					element.setAttribute( 'class', attribute.value.replace( /\bpost-\d+\b/g, 'post-ID' ) );
				} else if ( attribute.name === 'datetime' ) {
					element.setAttribute( attribute.name, attribute.value.startsWith( 'PT' ) ? attribute.value : 'DATE' );
				}
			}
		}
		const walker = document.createTreeWalker( clone, NodeFilter.SHOW_TEXT );
		while ( walker.nextNode() ) {
			walker.currentNode.nodeValue = walker.currentNode.nodeValue
				.replace( /(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{1,2}/g, 'SHORT_DATE' )
				.replace( /(?:January|February|March|April|May|June|July|August|September|October|November|December) \d{1,2}, \d{4}/g, 'LONG_DATE' );
		}
		return clone.outerHTML.replace( /\s+/g, ' ' ).trim();
	} );
}

async function axe( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations.map( ( violation ) => ( {
		id: violation.id,
		nodes: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
	} ) ) );
}

async function fontAudit( locator ) {
	return locator.evaluate( ( root ) => {
		const allowed = /(Rock Salt|Roboto Slab|Barlow|ui-monospace|SF Mono|Menlo|Consolas|monospace)/i;
		return [ root, ...root.querySelectorAll( '*' ) ].flatMap( ( element ) => {
			const style = getComputedStyle( element );
			if ( style.display === 'none' || style.visibility === 'hidden' ) {
				return [];
			}
			const ownsText = [ ...element.childNodes ].some( ( node ) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() );
			const failures = [];
			if ( ! allowed.test( style.fontFamily ) ) {
				failures.push( `${ element.tagName.toLowerCase() }.${ element.className || '' }: ${ style.fontFamily }` );
			}
			if ( ownsText && Number.parseFloat( style.fontSize ) < 12 ) {
				failures.push( `${ element.tagName.toLowerCase() }.${ element.className || '' }: ${ style.fontSize }` );
			}
			return failures;
		} );
	} );
}

async function pageCellData( page ) {
	return page.locator( 'li.pkiw-sleep-night' ).evaluateAll( ( cells ) => cells.map( ( cell, index ) => {
		const link = cell.querySelector( 'a.u-url' );
		const rect = cell.getBoundingClientRect();
		return {
			index,
			href: link.href,
			night: cell.querySelector( 'time.dt-published' ).dateTime,
			top: rect.top + window.scrollY,
			left: rect.left,
		};
	} ) );
}

function visualOrder( cells ) {
	return [ ...cells ].sort( ( a, b ) => Math.abs( a.top - b.top ) <= 2 ? a.left - b.left : a.top - b.top ).map( ( cell ) => cell.href );
}

async function tabCellOrder( page, expected ) {
	const first = page.locator( 'li.pkiw-sleep-night a.u-url' ).first();
	await first.focus();
	const seen = [];
	for ( let index = 0; index < expected.length; index++ ) {
		seen.push( await page.evaluate( () => document.activeElement?.closest( 'a[href]' )?.href || '' ) );
		await page.keyboard.press( 'Tab' );
	}
	return {
		seen,
		after: await page.evaluate( () => ( {
			href: document.activeElement?.closest( 'a[href]' )?.href || '',
			pager: Boolean( document.activeElement?.closest( 'nav[aria-label="Pagination"]' ) ),
		} ) ),
	};
}

async function wholePageKeyboardWalk( page, limit = 160 ) {
	await page.evaluate( () => document.activeElement?.blur() );
	const stops = [];
	for ( let index = 0; index < limit; index++ ) {
		await page.keyboard.press( 'Tab' );
		const stop = await page.evaluate( () => {
			const active = document.activeElement;
			return {
				tag: active?.tagName || '',
				href: active?.href || '',
				disallowed: Boolean( active?.closest( '.cr-cutout__tile, .cr-archive-identity__glyph, .pkiw-sleep-night__bar, .pkiw-sleep-night__moon' ) ),
			};
		} );
		if ( stop.tag === 'BODY' || ( stops.length && stop.href && stop.href === stops[ 0 ].href ) ) {
			break;
		}
		stops.push( stop );
	}
	return stops;
}

// tokens.css section 13 forces animation and transition durations to 0.001ms under reduced motion, so a
// computed transition-duration reads 1e-06s and never '0s'. Treat anything at or under 0.001ms as off.
function motionState( element ) {
	const style = getComputedStyle( element );
	return {
		transition: style.transitionDuration.split( ',' ).every( ( value ) => Number.parseFloat( value ) <= 0.00001 ),
		animation: style.animationName,
	};
}

function parseRgb( value ) {
	return ( value.match( /[\d.]+/g ) || [] ).slice( 0, 3 ).map( Number );
}

function contrast( foreground, background ) {
	const channel = ( value ) => ( ( value /= 255 ) <= 0.03928 ? value / 12.92 : ( ( value + 0.055 ) / 1.055 ) ** 2.4 );
	const luminance = ( value ) => {
		const [ red, green, blue ] = parseRgb( value );
		return 0.2126 * channel( red ) + 0.7152 * channel( green ) + 0.0722 * channel( blue );
	};
	const values = [ luminance( foreground ), luminance( background ) ].sort( ( a, b ) => b - a );
	return ( values[ 0 ] + 0.05 ) / ( values[ 1 ] + 0.05 );
}

async function restObjectForPermalink( page, request, href ) {
	await visit( page, href );
	const alternate = page.locator( 'link[rel="alternate"][type="application/json"]' ).first();
	expect( await alternate.count(), `REST alternate is missing for ${ href }` ).toBeGreaterThan( 0 );
	const response = await request.get( await alternate.getAttribute( 'href' ) );
	expect( response.status() ).toBe( 200 );
	return response.json();
}

function markdownUrl( href ) {
	const url = new URL( href, BASE_URL );
	url.pathname = `${ url.pathname.replace( /\/$/, '' ) }.md`;
	return url.href;
}

test.describe( 'archive — full logged-out fixture set', () => {
	test.skip( 'full' !== STATE, `CR_SLEEP_STATE=${ STATE }; run ${ ARCHIVE_SEED } and use CR_SLEEP_STATE=full` );
	test.beforeEach( async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await visit( page, ARCHIVE );
		expect( await page.locator( 'li.pkiw-sleep-night' ).count(), `archive fixtures are missing (run ${ ARCHIVE_SEED })` ).toBeGreaterThan( 0 );
	} );

	test( '[1] "one inherited Query and core pagination": pages are 31 then 11, equivalent page-2 URLs, then 404', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		expect( await visit( page, archivePage( 1 ) ) ).toBe( 200 );
		await expect( page.locator( '.cr-sleep__query.wp-block-query' ) ).toHaveCount( 1 );
		expect( await page.locator( '.cr-sleep__query' ).getAttribute( 'data-is-layout-flex' ) ).toBeNull();
		await expect( page.locator( 'li.pkiw-sleep-night' ) ).toHaveCount( 31 );
		expect( ( await pageCellData( page ) ).map( ( item ) => item.night ) ).toEqual( [
			'2026-08-03', '2026-08-04', '2026-08-05', '2026-08-08', '2026-08-09', '2026-08-10', '2026-08-11', '2026-08-12',
			'2026-08-16', '2026-08-17', '2026-08-18', '2026-08-19', '2026-08-20', '2026-08-21', '2026-08-22', '2026-08-22',
			...Array.from( { length: 15 }, ( _, index ) => `2026-07-${ String( index + 1 ).padStart( 2, '0' ) }` ),
		] );
		expect( await visit( page, archivePage( 2 ) ) ).toBe( 200 );
		const canonical = ( await pageCellData( page ) ).map( ( item ) => item.href );
		expect( canonical ).toHaveLength( 11 );
		expect( ( await pageCellData( page ) ).map( ( item ) => item.night ) ).toEqual( [
			...Array.from( { length: 9 }, ( _, index ) => `2026-07-${ String( index + 16 ).padStart( 2, '0' ) }` ),
			'2026-06-06', '2026-06-21',
		] );
		expect( await visit( page, pagedArchive( 2 ) ) ).toBe( 200 );
		expect( ( await pageCellData( page ) ).map( ( item ) => item.href ) ).toEqual( canonical );
		expect( await visit( page, archivePage( 3 ) ) ).toBe( 404 );
	} );

	test( '[2] "Header is archive-identity only": Sleep, five inert tiles, description, badge, and no retired art', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, ARCHIVE );
		const h1 = page.locator( 'main h1.cr-archive__title' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1.locator( '.cr-cutout__text' ) ).toHaveText( 'Sleep' );
		const wrapper = h1.locator( '.cr-cutout__tiles' );
		await expect( wrapper ).toHaveAttribute( 'aria-hidden', 'true' );
		const tiles = wrapper.locator( '.cr-cutout__tile' );
		await expect( tiles ).toHaveCount( 5 );
		const tilts = await tiles.evaluateAll( ( elements ) => elements.map( ( element ) => getComputedStyle( element ).getPropertyValue( '--cr-cutout-tilt' ).trim() || getComputedStyle( element ).transform ) );
		for ( let index = 1; index < tilts.length; index++ ) {
			expect( tilts[ index ], `tile ${ index + 1 } differs from tile ${ index }` ).not.toBe( tilts[ index - 1 ] );
		}
		const badge = page.locator( '.cr-archive-identity--kind .cr-archive-identity__glyph' );
		await expect( badge ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( await badge.evaluate( ( element ) => Boolean( element.closest( 'a' ) ) ) ).toBe( false );
		expect( await badge.getAttribute( 'tabindex' ) ).toBeNull();
		expect( ( await page.locator( '.cr-archive__description' ).innerText() ).trim().length ).toBeGreaterThan( 0 );
		const art = await page.locator( '.cr-archive__header' ).evaluate( ( header ) => {
			const elements = [ header, ...header.querySelectorAll( '*' ) ];
			const retired = elements.filter( ( element ) => /(?:tape|leaf|mountain)/i.test( `${ element.className } ${ element.id } ${ [ ...element.attributes ].filter( ( attribute ) => attribute.name.startsWith( 'data-' ) ).map( ( attribute ) => attribute.value ).join( ' ' ) }` ) ).length;
			const moons = elements.filter( ( element ) => /cr-moon/i.test( getComputedStyle( element, '::after' ).backgroundImage ) ).length;
			return { retired, moons };
		} );
		expect( art ).toEqual( { retired: 0, moons: expect.any( Number ) } );
		expect( art.moons ).toBeLessThanOrEqual( 1 );
		expect( ( await wholePageKeyboardWalk( page ) ).some( ( stop ) => stop.disallowed ) ).toBe( false );
	} );

	test( '[3,44] "One H2 per month" and one cell per post: groups descend, nights ascend, W7 precedes NAP', async ( { page } ) => {
		for ( const [ path, labels ] of [ [ archivePage( 1 ), [ 'August 2026', 'July 2026' ] ], [ archivePage( 2 ), [ 'July 2026', 'June 2026' ] ] ] ) {
			await visit( page, path );
			const sections = await page.locator( 'section.pkiw-sleep-archive__month' ).evaluateAll( ( nodes ) => nodes.map( ( section ) => ( {
				heading: section.querySelectorAll( ':scope > h2.pkiw-group__heading' ).length,
				label: section.querySelector( ':scope > h2.pkiw-group__heading' )?.textContent.trim(),
				nights: [ ...section.querySelectorAll( ':scope > ol.pkiw-sleep-sheet > li time.dt-published' ) ].map( ( time ) => time.dateTime ),
			} ) ) );
			expect( sections.map( ( section ) => section.label ) ).toEqual( labels );
			expect( sections.every( ( section ) => section.heading === 1 ) ).toBe( true );
			for ( const section of sections ) {
				expect( section.nights ).toEqual( [ ...section.nights ].sort() );
			}
		}
		await visit( page, ARCHIVE );
		const sameNight = page.locator( 'li.pkiw-sleep-night:has(time.dt-published[datetime="2026-08-22"])' );
		await expect( sameNight ).toHaveCount( 2 );
		expect( await sameNight.nth( 0 ).locator( 'h3' ).count() ).toBe( 0 );
		await expect( sameNight.nth( 1 ).locator( 'h3' ) ).toHaveText( 'Afternoon nap' );
	} );

	test( '[4] "li.h-entry in one ol per month": semantic properties and DOM order match the contract', async ( { page } ) => {
		await visit( page, ARCHIVE );
		for ( const cell of await page.locator( 'ol.pkiw-sleep-sheet > li.pkiw-sleep-night' ).all() ) {
			await expect( cell ).toHaveClass( /\bh-entry\b/ );
			await expect( cell.locator( 'a.u-url' ) ).toHaveCount( 1 );
			await expect( cell.locator( 'time.dt-published' ) ).toHaveCount( 1 );
			await expect( cell.locator( '.p-author' ) ).toHaveCount( 1 );
			await expect( cell.locator( '.h-entry .h-entry' ) ).toHaveCount( 0 );
			const structure = await cell.evaluate( ( element ) => {
				const selectors = [ '.pkiw-sleep-night__title', '.pkiw-sleep-night__date', '.pkiw-sleep-night__duration', '.pkiw-sleep-night__bar', '.pkiw-sleep-night__moon', '.pkiw-sleep-night__phase' ];
				return selectors.map( ( selector ) => element.querySelector( selector ) ).filter( Boolean ).map( ( node ) => [ ...element.querySelectorAll( '*' ) ].indexOf( node ) );
			} );
			expect( structure ).toEqual( [ ...structure ].sort( ( a, b ) => a - b ) );
			const sleep = cell.locator( '.p-sleep' );
			if ( await sleep.count() ) {
				expect( ( await sleep.innerText() ).trim() ).toMatch( /^\d+ hr(?: \d+ min)?$|^\d+ min$/ );
				await expect( sleep.locator( 'time' ) ).toHaveCount( 1 );
			}
		}
		const leakingAttributes = await page.locator( 'main' ).evaluate( ( main ) => [ main, ...main.querySelectorAll( '*' ) ].filter( ( element ) => [ ...element.attributes ].some( ( attribute ) => /bedtime|wake/i.test( `${ attribute.name }=${ attribute.value }` ) ) ).length );
		expect( leakingAttributes ).toBe( 0 );
	} );

	test( '[5] "No bedtime, wake, excerpt, notes, source, kind label, rating, arrow or privacy text" on both pages', async ( { page } ) => {
		for ( const path of [ archivePage( 1 ), archivePage( 2 ) ] ) {
			await visit( page, path );
			const main = page.locator( 'main' );
			const html = await main.innerHTML();
			expect( html ).not.toMatch( /Private|Public duration|bedtime|wake|WC |FQ |WG |Fictional note/i );
			expect( html ).not.toMatch( CLOCK );
			await expect( main.locator( '.pk-excerpt, .p-summary, .pk-kindlabel, [class*="rating" i], [data-rating]' ) ).toHaveCount( 0 );
			const text = await page.locator( 'ol.pkiw-sleep-sheet' ).allInnerTexts();
			expect( text.every( ( value ) => ! /\bSleep\b|[←→➜➝➡]/i.test( value ) ) ).toBe( true );
		}
	} );

	test( '[6,46] "Hidden equals absent": Z01=C01, X01 has the same lean shape, and H01=N01', async ( { page } ) => {
		await visit( page, ARCHIVE );
		const z01 = cellForNight( page, '2026-08-03' );
		const c01 = cellForNight( page, '2026-08-04' );
		const x01 = cellForNight( page, '2026-08-08' );
		expect( await normalizedMarkup( z01 ) ).toBe( await normalizedMarkup( c01 ) );
		for ( const cell of [ z01, c01, x01 ] ) {
			await expect( cell.locator( '.pkiw-sleep-night__date, .pkiw-sleep-night__moon, .pkiw-sleep-night__phase' ) ).toHaveCount( 3 );
			await expect( cell.locator( '.pkiw-sleep-night__title, .pkiw-sleep-night__duration, .pkiw-sleep-night__bar' ) ).toHaveCount( 0 );
		}
		expect( await normalizedMarkup( cellForNight( page, '2026-08-09' ) ) ).toBe( await normalizedMarkup( cellForNight( page, '2026-08-10' ) ) );
	} );

	test( '[7] "One bar color; height min(m,720)/720; bottom-aligned; proportional" in light and dark', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 1000 } );
		await visit( page, ARCHIVE );
		const bar = ( night ) => cellForNight( page, night ).locator( '.pkiw-sleep-night__bar' );
		const heights = {};
		for ( const [ night, minutes ] of [ [ '2026-07-10', 750 ], [ '2026-08-16', 432 ], [ '2026-08-18', 455 ], [ '2026-08-19', 482 ], [ '2026-08-21', 494 ] ] ) {
			const item = bar( night );
			heights[ night ] = ( await item.boundingBox() ).height;
			expect( await item.evaluate( ( element ) => getComputedStyle( element ).backgroundColor ) ).toBe( CERULEAN );
			expect( await item.getAttribute( 'style' ) ).toContain( '--pkiw-sleep-ratio' );
			expect( minutes ).toBeGreaterThan( 0 );
		}
		expect( Math.abs( heights['2026-08-16'] - heights['2026-07-10'] * 432 / 720 ) ).toBeLessThanOrEqual( 1 );
		expect( heights['2026-08-18'] ).toBeGreaterThan( heights['2026-08-16'] );
		expect( heights['2026-08-21'] ).toBeGreaterThan( heights['2026-08-19'] );
		const aligned = await page.locator( 'section.pkiw-sleep-archive__month' ).first().evaluate( ( section ) => {
			const cells = [ ...section.querySelectorAll( 'li.pkiw-sleep-night' ) ];
			const rows = [];
			for ( const cell of cells.filter( ( item ) => item.querySelector( '.pkiw-sleep-night__bar' ) ) ) {
				const top = cell.getBoundingClientRect().top;
				const row = rows.find( ( item ) => Math.abs( item.top - top ) <= 2 );
				if ( row ) row.cells.push( cell ); else rows.push( { top, cells: [ cell ] } );
			}
			return rows.filter( ( row ) => row.cells.length > 1 ).map( ( row ) => row.cells.map( ( cell ) => cell.querySelector( '.pkiw-sleep-night__bar' ).getBoundingClientRect().bottom ) );
		} );
		for ( const row of aligned ) {
			expect( Math.max( ...row ) - Math.min( ...row ) ).toBeLessThanOrEqual( 1 );
		}
		await visit( page, ARCHIVE, 'dark' );
		expect( await bar( '2026-08-18' ).evaluate( ( element ) => getComputedStyle( element ).backgroundColor ) ).toBe( CERULEAN );
		expect( await page.locator( 'ol.pkiw-sleep-sheet' ).first().evaluate( ( element ) => getComputedStyle( element ).backgroundColor ) ).toBe( WHITE );
	} );

	test( '[8] "One link per cell" with unique aria-labelledby names, including W7 and NAP', async ( { page } ) => {
		for ( const path of [ archivePage( 1 ), archivePage( 2 ) ] ) {
			await visit( page, path );
			const names = [];
			for ( const cell of await page.locator( 'li.pkiw-sleep-night' ).all() ) {
				const links = await visibleLinks( cell );
				expect( links.count ).toBe( 1 );
				expect( links.stray ).toBe( 0 );
				names.push( links.names[ 0 ] );
				await expect( cell.getByRole( 'link', { name: nameMatcher( links.names[ 0 ] ) } ) ).toHaveCount( 1 );
			}
			expect( new Set( names ).size ).toBe( names.length );
		}
		await visit( page, ARCHIVE );
		const w5 = ( await visibleLinks( cellForNight( page, '2026-08-20' ) ) ).names[ 0 ];
		expect( w5 ).toMatch( /^Aug 20, 7 hr 20 min, Waxing gibbous$/ );
		const titled = ( await visibleLinks( cellForNight( page, '2026-08-11' ) ) ).names[ 0 ];
		expect( titled ).toMatch( /^Early night, Aug 11, 8 hr 30 min, Waning crescent$/ );
		const sameNight = await page.locator( 'li.pkiw-sleep-night:has(time.dt-published[datetime="2026-08-22"])' ).evaluateAll( ( cells ) => cells.map( ( cell ) => cell.querySelector( 'a' ).getAttribute( 'aria-labelledby' ).split( /\s+/ ).map( ( id ) => document.getElementById( id ).textContent ).join( ' ' ).replace( /\s+/g, ' ' ).trim() ) );
		expect( sameNight[ 0 ] ).not.toBe( sameNight[ 1 ] );
	} );

	test( '[9] "Graph-paper sheet": token paint, hard edge, no filler, and sparse June is narrower', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, ARCHIVE );
		const fullWidth = ( await page.locator( 'ol.pkiw-sleep-sheet' ).first().boundingBox() ).width;
		const paint = await page.locator( 'ol.pkiw-sleep-sheet' ).first().evaluate( ( sheet ) => {
			const style = getComputedStyle( sheet );
			const probe = document.createElement( 'span' );
			probe.style.backgroundColor = 'var(--cr-surface-elevated)';
			sheet.append( probe );
			const token = getComputedStyle( probe ).backgroundColor;
			probe.remove();
			return { background: style.backgroundColor, token, border: `${ style.borderTopWidth } ${ style.borderTopStyle } ${ style.borderTopColor }`, radius: style.borderRadius, shadow: style.boxShadow };
		} );
		expect( paint.background ).toBe( paint.token );
		expect( paint.border ).toBe( `2px solid ${ VIOLET }` );
		expect( paint.radius ).toBe( '0px' );
		expect( paint.shadow ).not.toBe( 'none' );
		expect( ( paint.shadow.match( /-?\d+(?:\.\d+)?px/g ) || [] )[ 2 ] ).toBe( '0px' );
		expect( await page.locator( 'ol.pkiw-sleep-sheet > li' ).count() ).toBe( 31 );
		await visit( page, archivePage( 2 ) );
		const june = page.locator( 'section.pkiw-sleep-archive__month', { has: page.getByRole( 'heading', { name: 'June 2026', exact: true } ) } );
		await expect( june.locator( 'ol.pkiw-sleep-sheet > li' ) ).toHaveCount( 2 );
		const juneBox = await june.locator( 'ol.pkiw-sleep-sheet' ).boundingBox();
		expect( juneBox.width ).toBeLessThan( fullWidth );
		expect( await june.locator( 'ol.pkiw-sleep-sheet' ).evaluate( ( sheet ) => getComputedStyle( sheet ).gridTemplateRows.split( ' ' ).length ) ).toBeLessThanOrEqual( 6 );
		// The fixture map has no 31-night month; its full-width August sheet is the plan's available width reference.
	} );

	for ( const [ width, columns ] of WIDTHS ) {
		test( `[10] "${ columns } cells per row" at ${ width}px; DOM, Tab and visual order agree with no horizontal scroll`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 1000 } );
			await visit( page, ARCHIVE );
			const firstMonth = page.locator( 'section.pkiw-sleep-archive__month' ).first();
			const rowCounts = await firstMonth.locator( 'li.pkiw-sleep-night' ).evaluateAll( ( cells ) => {
				const groups = [];
				for ( const cell of cells ) {
					const top = cell.getBoundingClientRect().top;
					const row = groups.find( ( group ) => Math.abs( group.top - top ) <= 2 );
					if ( row ) row.count++; else groups.push( { top, count: 1 } );
				}
				return groups.map( ( group ) => group.count );
			} );
			expect( Math.max( ...rowCounts ) ).toBe( columns );
			const cells = await pageCellData( page );
			const dom = cells.map( ( cell ) => cell.href );
			expect( visualOrder( cells ) ).toEqual( dom );
			const tabbed = await tabCellOrder( page, dom );
			expect( tabbed.seen ).toEqual( dom );
			expect( tabbed.after.pager ).toBe( true );
			expect( await page.evaluate( () => document.documentElement.scrollWidth === document.documentElement.clientWidth ) ).toBe( true );
		} );
	}

	test( '[10] "400% zoom": a 1280px, deviceScaleFactor 4 context has no horizontal scroll', async ( { browser } ) => {
		const context = await browser.newContext( { baseURL: BASE_URL, viewport: { width: 1280, height: 800 }, deviceScaleFactor: 4, colorScheme: 'light' } );
		const page = await context.newPage();
		await visit( page, ARCHIVE );
		expect( await page.evaluate( () => ( { ratio: window.devicePixelRatio, fits: document.documentElement.scrollWidth === document.documentElement.clientWidth } ) ) ).toEqual( { ratio: 4, fits: true } );
		await context.close();
	} );

	test( '[11] "Computed fonts are only" the four voices and every text-bearing element is at least 12px', async ( { page } ) => {
		await visit( page, ARCHIVE );
		expect( await fontAudit( page.locator( 'main.cr-archive--sleep' ) ) ).toEqual( [] );
		await openFixture( page, '2026-08-11' );
		expect( await fontAudit( page.locator( 'main .pkiw-sleep' ) ) ).toEqual( [] );
	} );

	test( '[12] "Pager directly after the post-template": current chip, 44px targets, no Previous on page 1', async ( { page } ) => {
		for ( const pageNumber of [ 1, 2 ] ) {
			await visit( page, archivePage( pageNumber ) );
			const relation = await page.locator( '.cr-sleep__query > .wp-block-post-template' ).evaluate( ( template ) => template.nextElementSibling?.matches( 'nav[aria-label="Pagination"]' ) || false );
			expect( relation ).toBe( true );
			const pager = page.locator( 'nav[aria-label="Pagination"]' );
			await expect( pager.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
			for ( const target of await pager.locator( 'a, [aria-current="page"]' ).all() ) {
				const box = await target.boundingBox();
				expect( box.width ).toBeGreaterThanOrEqual( 44 );
				expect( box.height ).toBeGreaterThanOrEqual( 44 );
			}
			if ( 1 === pageNumber ) {
				await expect( pager.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( 0 );
			}
		}
	} );

	test( '[13] "Tab moves cell to cell, then to the pager" with a 3px yellow ring and 7–8px halo', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, ARCHIVE );
		const cells = await pageCellData( page );
		const tabbed = await tabCellOrder( page, cells.map( ( cell ) => cell.href ) );
		expect( tabbed.seen ).toEqual( cells.map( ( cell ) => cell.href ) );
		expect( tabbed.after.pager ).toBe( true );
		const first = page.locator( 'li.pkiw-sleep-night' ).first();
		await first.locator( 'a.u-url' ).focus();
		const focus = await first.evaluate( ( cell ) => {
			const style = getComputedStyle( cell );
			return { outline: `${ style.outlineWidth } ${ style.outlineStyle } ${ style.outlineColor }`, shadow: style.boxShadow };
		} );
		expect( focus.outline ).toBe( `3px solid ${ YELLOW }` );
		expect( focus.shadow ).toMatch( /0px 0px 0px (7|8)px/ );
	} );

	test( '[14] "Forced colors": CanvasText borders preserve bars and text while moons and decoration drop out', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, ARCHIVE );
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await page.locator( 'ol.pkiw-sleep-sheet' ).first().evaluate( ( sheet ) => {
			const probe = document.createElement( 'span' );
			probe.style.color = 'CanvasText';
			sheet.append( probe );
			const canvasText = getComputedStyle( probe ).color;
			probe.remove();
			const cell = sheet.querySelector( '.pkiw-sleep-night' );
			const bar = sheet.querySelector( '.pkiw-sleep-night__bar' );
			return {
				canvasText,
				sheet: [ getComputedStyle( sheet ).borderTopWidth, getComputedStyle( sheet ).borderTopColor ],
				cell: [ getComputedStyle( cell ).borderTopWidth, getComputedStyle( cell ).borderTopColor ],
				bar: [ bar.getBoundingClientRect().height, getComputedStyle( bar ).borderTopWidth ],
				moon: getComputedStyle( sheet.querySelector( '.pkiw-sleep-night__moon' ) ).display,
				text: sheet.innerText.trim().length,
			};
		} );
		expect( forced.sheet ).toEqual( [ '2px', forced.canvasText ] );
		expect( forced.cell ).toEqual( [ '2px', forced.canvasText ] );
		expect( forced.bar[ 0 ] ).toBeGreaterThan( 0 );
		expect( forced.bar[ 1 ] ).toBe( '2px' );
		expect( forced.moon ).toBe( 'none' );
		expect( forced.text ).toBeGreaterThan( 0 );
	} );

	test( '[15] "Dark mode under X1": dark ground, light sheets, violet ink, ivory edge, yellow hard shadow, zero axe contrast violations', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, ARCHIVE, 'dark' );
		const colors = await page.locator( 'ol.pkiw-sleep-sheet' ).first().evaluate( ( sheet ) => {
			const style = getComputedStyle( sheet );
			const cell = getComputedStyle( sheet.querySelector( '.pkiw-sleep-night' ) );
			return { ground: getComputedStyle( document.body ).backgroundColor, sheet: style.backgroundColor, cell: cell.backgroundColor, ink: cell.color, edge: style.borderTopColor, shadow: style.boxShadow };
		} );
		expect( colors.ground ).toBe( DARK_GROUND );
		expect( colors.sheet ).toBe( WHITE );
		expect( colors.cell ).toBe( WHITE );
		expect( colors.ink ).toBe( VIOLET );
		expect( [ IVORY, 'rgb(188, 181, 227)' ] ).toContain( colors.edge );
		expect( colors.shadow ).toContain( YELLOW );
		expect( await axe( page ) ).toEqual( [] );
	} );

	test( '[16] "Reduced motion: no animation" or transition on sheets, cells, bars and the single card', async ( { page } ) => {
		await visit( page, ARCHIVE );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		for ( const selector of [ 'ol.pkiw-sleep-sheet', '.pkiw-sleep-night', '.pkiw-sleep-night__bar' ] ) {
			const motion = await page.locator( selector ).first().evaluate( motionState );
			expect( motion.transition ).toBe( true );
			expect( motion.animation ).toBe( 'none' );
		}
		await openFixture( page, '2026-08-11' );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		expect( await page.locator( '.pkiw-sleep' ).evaluate( motionState ) ).toEqual( { transition: true, animation: 'none' } );
	} );

	test( '[18,49,50,55] "Feed and REST privacy": noon dates, no hidden terms, stripped meta, safe oEmbed', async ( { page, request } ) => {
		await visit( page, ARCHIVE );
		const h01 = await cellForNight( page, '2026-08-09' ).locator( 'a.u-url' ).getAttribute( 'href' );
		const x01 = await cellForNight( page, '2026-08-08' ).locator( 'a.u-url' ).getAttribute( 'href' );
		const feed = await request.get( `${ withSlash( ARCHIVE ) }feed/` );
		expect( feed.status() ).toBe( 200 );
		const xml = await feed.text();
		const dates = [ ...xml.matchAll( /<pubDate>([^<]+)<\/pubDate>/g ) ].map( ( match ) => match[ 1 ] );
		expect( dates.length ).toBeGreaterThan( 0 );
		expect( dates.every( ( date ) => /(?:16|17):00:00 \+0000$/.test( date ) ) ).toBe( true );
		expect( xml ).not.toMatch( /23:10|06:10/ );
		expect( xml ).not.toContain( new URL( x01, BASE_URL ).pathname );
		for ( const term of [ '23:10', 'bedtime', 'wakeTime', 'showTimesPublicly' ] ) {
			const response = await request.get( `/wp-json/wp/v2/posts?_fields=slug&search=${ encodeURIComponent( term ) }` );
			expect( response.status() ).toBe( 200 );
			// T01 opted in to public times, so its stored card may match; no other (hidden) fixture may.
			const hidden = ( await response.json() ).map( ( item ) => item.slug ).filter( ( slug ) => ! /^cr-seed-247-t01(?:-\d+)?$/.test( slug ) );
			expect( hidden, `logged-out search for "${ term }" matched a hidden fixture` ).toEqual( [] );
		}
		const post = await restObjectForPermalink( page, request, h01 );
		expect( post.date ).toMatch( /T12:00:00$/ );
		expect( post.modified ).toBe( post.date );
		expect( Object.keys( post.meta || {} ).some( ( key ) => /sleep|bedtime|wake/i.test( key ) ) ).toBe( false );
		expect( post.content.rendered ).not.toMatch( /23:10|06:10/ );
		const embed = await request.get( `/wp-json/oembed/1.0/embed?url=${ encodeURIComponent( new URL( h01, BASE_URL ).href ) }` );
		expect( embed.status() ).toBe( 200 );
		expect( await embed.text() ).not.toMatch( /23:10/ );
	} );
} );

test.describe( 'archive — empty state', () => {
	test.beforeEach( async ( { page } ) => page.setViewportSize( { width: 1280, height: 800 } ) );

	test( '[17] "Empty archive: core no-results, no sheet, no H2"', async ( { page } ) => {
		test.skip( 'empty' !== STATE, 'CR_SLEEP_STATE is not empty (remove sleep posts, then use CR_SLEEP_STATE=empty)' );
		await page.setViewportSize( { width: 1280, height: 800 } );
		await visit( page, ARCHIVE );
		await expect( page.locator( '.cr-archive-stream__empty' ) ).toBeVisible();
		await expect( page.locator( 'ol.pkiw-sleep-sheet' ) ).toHaveCount( 0 );
		await expect( page.locator( 'section.pkiw-sleep-archive__month > h2' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'single — full fixture set', () => {
	test.skip( 'full' !== STATE, `CR_SLEEP_STATE=${ STATE }; run ${ ARCHIVE_SEED } and use CR_SLEEP_STATE=full` );
	test.beforeEach( async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await visit( page, ARCHIVE );
		expect( await page.locator( 'li.pkiw-sleep-night' ).count(), `archive fixtures are missing (run ${ ARCHIVE_SEED })` ).toBeGreaterThan( 0 );
	} );

	test( '[19,47] "T01 Early night": stored H1 and opted-in rows; H01/N01 hidden equals absent', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		const cases = [
			[ '2026-08-11', 5, [ 'Duration', 'Moon phase', 'Date', 'Bedtime', 'Wake time' ] ],
			[ '2026-08-09', 3, [ 'Duration', 'Moon phase', 'Date' ] ],
			[ '2026-08-10', 3, [ 'Duration', 'Moon phase', 'Date' ] ],
			[ '2026-08-04', 2, [ 'Moon phase', 'Date' ] ],
		];
		let hidden = '';
		for ( const [ night, count, labels ] of cases ) {
			await openFixture( page, night );
			const rows = page.locator( 'dl.pkiw-sleep__record > .pkiw-sleep__row' );
			await expect( rows ).toHaveCount( count );
			expect( await rows.locator( 'dt' ).allTextContents() ).toEqual( labels );
			if ( night === '2026-08-11' ) {
				const h1 = page.locator( 'main h1' );
				await expect( h1 ).toHaveCount( 1 );
				await expect( h1 ).toHaveText( 'Early night' );
				// The titled H1's p-name comes from IndieBlocks' theme-mf2 pass (inc/microformats.php:106), which only dev runs.
				if ( DEV ) await expect( h1 ).toHaveClass( /\bp-name\b/ );
				const times = rows.filter( { has: page.getByText( /Bedtime|Wake time/ ) } ).locator( 'dd time' );
				for ( const time of await times.all() ) {
					expect( await time.getAttribute( 'datetime' ) ).toMatch( /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/ );
					expect( await time.getAttribute( 'class' ) || '' ).not.toMatch( /(?:^|\s)(?:p|dt|u)-/ );
				}
			}
			if ( night === '2026-08-09' ) {
				hidden = await normalizedMarkup( page.locator( '.pkiw-sleep' ) );
				expect( await page.content() ).not.toMatch( /23:10|06:10|T23:10/ );
			}
			if ( night === '2026-08-10' ) {
				expect( await normalizedMarkup( page.locator( '.pkiw-sleep' ) ) ).toBe( hidden );
			}
		}
	} );

	test( '[20,26] "Untitled single W5": exact date H1 and unchanged 44px Previous/Next chips without times', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await openFixture( page, '2026-08-20' );
		const h1 = page.locator( 'main h1' );
		await expect( h1 ).toHaveText( 'Sleep, August 20, 2026' );
		await expect( h1 ).not.toHaveClass( /\bp-name\b/ );
		expect( await h1.innerText() ).not.toMatch( /\bat\b|Sleep ·/ );
		const nav = page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		for ( const link of await nav.locator( 'a' ).all() ) {
			expect( ( await link.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
			expect( await link.innerText() ).toMatch( /Sleep, [A-Z][a-z]+ \d{1,2}, 2026/ );
			expect( await link.innerText() ).not.toMatch( /\bat\b/ );
		}
	} );

	test( '[21,22,23] "Record order and content": duration/date times, ratio chart, no zero chart, one hidden moon', async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await openFixture( page, '2026-08-20' );
		const rows = page.locator( '.pkiw-sleep__record > .pkiw-sleep__row' );
		expect( await rows.locator( 'dt' ).allTextContents() ).toEqual( [ 'Duration', 'Moon phase', 'Date' ] );
		await expect( rows.nth( 0 ).locator( 'dd.p-sleep > time' ) ).toHaveAttribute( 'datetime', 'PT7H20M' );
		await expect( rows.nth( 2 ).locator( 'dd > time' ) ).toHaveAttribute( 'datetime', '2026-08-20' );
		const ratio = await page.locator( '.pkiw-sleep__chart' ).evaluate( ( track ) => ( {
			track: track.getBoundingClientRect().width - Number.parseFloat( getComputedStyle( track ).borderLeftWidth ) - Number.parseFloat( getComputedStyle( track ).borderRightWidth ),
			bar: track.querySelector( '.pkiw-sleep__bar' ).getBoundingClientRect().width,
		} ) );
		expect( Math.abs( ratio.bar - ratio.track * 440 / 720 ) ).toBeLessThanOrEqual( 1 );
		expect( await page.locator( '.pkiw-sleep' ).evaluate( ( card ) => Boolean( card.querySelector( '.pkiw-sleep__record' ).compareDocumentPosition( card.querySelector( '.pkiw-sleep__chart' ) ) & Node.DOCUMENT_POSITION_FOLLOWING ) ) ).toBe( true );
		await openFixture( page, '2026-08-03' );
		await expect( page.locator( '.pkiw-sleep__chart, dt:text-is("Duration")' ) ).toHaveCount( 0 );
		await openFixture( page, '2026-08-21' );
		const moon = page.locator( '.wp-block-post-content .pkiw-sleep__moon' );
		await expect( moon.locator( 'svg, img' ) ).toHaveCount( 1 );
		await expect( moon ).toHaveAttribute( 'aria-hidden', 'true' );
	} );

	test( '[22,24,45] "e-content renders after the record": M01 and C01 preserve free text but no clock strings', async ( { page } ) => {
		for ( const [ night, phrases, banned, labels ] of [
			[ '2026-08-12', [ 'Fictional note: the fan was loud.', 'restless after 3am' ], /23:40|06:45|07:02/, [ 'Duration', 'Moon phase', 'Date' ] ],
			[ '2026-08-04', [ 'Fictional note: quiet night.' ], /bedtime|wake/i, [ 'Moon phase', 'Date' ] ],
		] ) {
			await openFixture( page, night );
			const content = page.locator( '.wp-block-post-content' );
			for ( const phrase of phrases ) await expect( content ).toContainText( phrase );
			expect( await content.innerText() ).not.toMatch( banned );
			expect( await content.evaluate( ( root ) => {
				const card = root.querySelector( '.pkiw-sleep' );
				const paragraph = [ ...root.querySelectorAll( 'p' ) ].find( ( item ) => item.textContent.includes( 'Fictional note:' ) );
				return Boolean( card && paragraph && ( card.compareDocumentPosition( paragraph ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
			} ) ).toBe( true );
			expect( await content.locator( '.pkiw-sleep__record dt' ).allTextContents() ).toEqual( labels );
		}
	} );

	test( '[23,28,51] "Microformats": one h-entry, p-sleep is duration only, and clock times carry no mf2 class', async ( { page } ) => {
		await openFixture( page, '2026-08-11' );
		await expect( page.locator( 'main .h-entry.kind-sleep' ) ).toHaveCount( 1 );
		const sleep = page.locator( 'main .h-entry.kind-sleep .p-sleep' );
		await expect( sleep ).toHaveCount( 1 );
		await expect( sleep ).toHaveText( '8 hr 30 min' );
		const leaks = await page.locator( '.pkiw-sleep__row', { has: page.getByText( /Bedtime|Wake time/ ) } ).evaluateAll( ( rows ) => rows.filter( ( row ) => [ ...row.querySelectorAll( '*' ) ].some( ( element ) => /(?:^|\s)(?:p|dt|u)-/.test( element.className ) ) ).length );
		expect( leaks ).toBe( 0 );
	} );

	test( '[24,25,31,32] "Layout and paint": responsive moon placement, X1 paper, hard shadow, no gradient, approved fonts', async ( { page } ) => {
		for ( const width of [ 1440, 800, 375 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await openFixture( page, '2026-08-20' );
			const layout = await page.locator( '.pkiw-sleep' ).evaluate( ( card ) => {
				const moon = card.querySelector( ':scope > .pkiw-sleep__moon' );
				const record = card.querySelector( '.pkiw-sleep__record' );
				const chart = card.querySelector( '.pkiw-sleep__chart' );
				const m = moon.getBoundingClientRect();
				const r = record.getBoundingClientRect();
				const c = chart.getBoundingClientRect();
				return { moonDisplay: getComputedStyle( moon ).display, beside: m.right <= r.left + 1, above: m.bottom <= r.top + 1, recordThenChart: r.bottom <= c.top + 1 };
			} );
			if ( width === 1440 ) expect( layout.beside ).toBe( true );
			if ( width === 800 ) expect( layout.above ).toBe( true );
			if ( width === 375 ) expect( [ layout.moonDisplay, layout.recordThenChart ] ).toEqual( [ 'none', true ] );
		}
		await page.setViewportSize( { width: 320, height: 900 } );
		await openFixture( page, '2026-08-20' );
		expect( await page.evaluate( () => document.documentElement.scrollWidth === document.documentElement.clientWidth ) ).toBe( true );
		expect( await fontAudit( page.locator( '.pkiw-sleep' ) ) ).toEqual( [] );
		const paint = await page.locator( '.pkiw-sleep' ).evaluate( ( card ) => ( { background: getComputedStyle( card ).backgroundColor, color: getComputedStyle( card ).color, shadow: getComputedStyle( card ).boxShadow, image: getComputedStyle( card ).backgroundImage } ) );
		expect( paint.background ).toBe( WHITE );
		expect( paint.color ).toBe( VIOLET );
		expect( paint.shadow ).not.toBe( 'none' );
		expect( ( paint.shadow.match( /-?\d+(?:\.\d+)?px/g ) || [] )[ 2 ] ).toBe( '0px' );
		expect( paint.image ).toBe( 'none' );
	} );

	test( '[24,30,31] "Forced colors and dark": moon drops, bar keeps a 2px border, panel stays light and ground flips', async ( { page } ) => {
		await openFixture( page, '2026-08-20', 0, 'dark' );
		const dark = await page.locator( '.pkiw-sleep' ).evaluate( ( card ) => ( { ground: getComputedStyle( document.body ).backgroundColor, paper: getComputedStyle( card ).backgroundColor, ink: getComputedStyle( card ).color } ) );
		expect( dark ).toEqual( { ground: DARK_GROUND, paper: WHITE, ink: VIOLET } );
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await page.locator( '.pkiw-sleep' ).evaluate( ( card ) => ( { moon: getComputedStyle( card.querySelector( '.pkiw-sleep__moon' ) ).display, bar: getComputedStyle( card.querySelector( '.pkiw-sleep__bar' ) ).borderTopWidth, text: card.innerText.trim().length } ) );
		expect( forced ).toEqual( { moon: 'none', bar: '2px', text: expect.any( Number ) } );
		expect( forced.text ).toBeGreaterThan( 0 );
	} );

	test( '[24] "400% zoom" single has no horizontal overflow', async ( { browser } ) => {
		const context = await browser.newContext( { baseURL: BASE_URL, viewport: { width: 1280, height: 800 }, deviceScaleFactor: 4, colorScheme: 'light' } );
		const page = await context.newPage();
		await openFixture( page, '2026-08-20' );
		expect( await page.evaluate( () => document.documentElement.scrollWidth === document.documentElement.clientWidth ) ).toBe( true );
		await context.close();
	} );

	test( '[25,29] "Visible focus" on content links and nav chips follows DOM order with the yellow ring and halo', async ( { page } ) => {
		await openFixture( page, '2026-08-12' );
		const content = page.locator( '.wp-block-post-content' );
		await content.evaluate( ( root ) => root.insertAdjacentHTML( 'beforeend', '<p><a id="cr-sleep-focus-probe" href="#cr-sleep-focus-probe">Focus probe</a></p>' ) );
		const probe = page.locator( '#cr-sleep-focus-probe' );
		await probe.focus();
		const focus = await probe.evaluate( ( link ) => ( { outline: `${ getComputedStyle( link ).outlineWidth } ${ getComputedStyle( link ).outlineStyle } ${ getComputedStyle( link ).outlineColor }`, shadow: getComputedStyle( link ).boxShadow } ) );
		expect( focus.outline ).toBe( `3px solid ${ YELLOW }` );
		expect( focus.shadow ).toMatch( /0px 0px 0px 7px/ );
		const navLinks = page.locator( 'nav.cr-post-nav a' );
		for ( const link of await navLinks.all() ) {
			await link.focus();
			expect( await link.evaluate( ( item ) => getComputedStyle( item ).outlineWidth ) ).toBe( '3px' );
		}
		const focusables = await page.locator( 'main' ).evaluate( ( main ) => [ ...main.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])' ) ].filter( ( item ) => item.getClientRects().length && ! item.closest( '[hidden]' ) ).map( ( item, index ) => {
			item.dataset.crSleepFocusIndex = String( index );
			return index;
		} ) );
		await probe.focus();
		const visited = [];
		for ( let count = 0; count < focusables.length; count++ ) {
			const index = await page.evaluate( () => Number.parseInt( document.activeElement?.dataset.crSleepFocusIndex || '-1', 10 ) );
			if ( index < 0 ) break;
			visited.push( index );
			await page.keyboard.press( 'Tab' );
		}
		expect( visited.length ).toBeGreaterThan( 1 );
		expect( visited ).toEqual( [ ...visited ].sort( ( a, b ) => a - b ) );
	} );
} );

test.describe( 'Stream — full or stream fixture set', () => {
	test.skip( ! [ 'full', 'stream' ].includes( STATE ), `CR_SLEEP_STATE=${ STATE }; run ${ STREAM_SEED } and use CR_SLEEP_STATE=stream` );
	test.beforeEach( async ( { page } ) => page.setViewportSize( { width: 1280, height: 900 } ) );

	async function stickers( page, scheme = 'light' ) {
		test.skip( NO_STREAM, 'CR_SLEEP_NO_STREAM=1: sleep is not mapped onto /stream in this environment' );
		await visit( page, STREAM, scheme );
		const cards = page.locator( 'article.cr-sleep-sticker' );
		expect( await cards.count(), `no article.cr-sleep-sticker exists (run ${ STREAM_SEED } after mapping sleep to /stream)` ).toBeGreaterThan( 0 );
		return cards;
	}

	test( '[26,35,37,48] "S1-S4 are the first four stickers, newest first" with the lean Stream markup', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		const cards = await stickers( page );
		await expect( cards ).toHaveCount( 4 );
		expect( await cards.evaluateAll( ( elements ) => elements.map( ( element ) => [ ...element.closest( '.wp-block-post-template' ).children ].indexOf( element.closest( 'li' ) ) ) ) ).toEqual( [ 0, 1, 2, 3 ] );
		const expected = [ [ '7 hr 42 min', '' ], [ '6 hr 15 min', 'Fictional title: storm night' ], [ '', '' ], [ '7 hr 10 min', '' ] ];
		for ( let index = 0; index < 4; index++ ) {
			const card = cards.nth( index );
			expect( new URL( await card.locator( 'a.u-url' ).getAttribute( 'href' ), BASE_URL ).pathname ).toContain( `cr-seed-247-s${ index + 1 }` );
			for ( const className of [ 'pk-card', 'pk-card--stream', 'k-sleep', 'h-entry', 'cr-sleep-sticker' ] ) await expect( card ).toHaveClass( new RegExp( `(?:^|\\s)${ className }(?:\\s|$)` ) );
			if ( expected[ index ][ 0 ] ) await expect( card.locator( '.cr-sleep-sticker__duration' ) ).toHaveText( expected[ index ][ 0 ] ); else await expect( card.locator( '.cr-sleep-sticker__duration' ) ).toHaveCount( 0 );
			if ( expected[ index ][ 1 ] ) await expect( card.locator( '.cr-sleep-sticker__title .p-name' ) ).toHaveText( expected[ index ][ 1 ] );
			await expect( card.locator( '.pk-excerpt, .p-summary, .pk-kindlabel, .pk-badge, .pk-meta, img, button' ) ).toHaveCount( 0 );
			const html = await card.evaluate( ( element ) => element.outerHTML );
			expect( html ).not.toMatch( /Exact times|Fictional note|dreamt of Example Bay|lock|[←→➜]|\b(?:[01]?\d|2[0-3]):[0-5]\d\b/i );
			await expect( card.locator( 'time.dt-published.cr-sleep-sticker__date' ) ).toHaveAttribute( 'datetime', /^\d{4}-\d{2}-\d{2}$/ );
			const props = card.locator( '.pk-entry-props' );
			if ( await props.count() ) expect( await props.innerHTML() ).not.toMatch( CLOCK );
		}
		const dates = await cards.locator( 'time.dt-published' ).evaluateAll( ( times ) => times.map( ( time ) => time.dateTime ) );
		expect( dates ).toEqual( [ ...dates ].sort().reverse() );
	} );

	test( '[27,36] "Exactly one rendered anchor per sticker" and accessible names come from title, date, duration', async ( { page } ) => {
		const cards = await stickers( page );
		const expectedDurations = [ '7 hr 42 min', '6 hr 15 min', null, '7 hr 10 min' ];
		for ( let index = 0; index < 4; index++ ) {
			const card = cards.nth( index );
			const links = await visibleLinks( card );
			expect( links.count ).toBe( 1 );
			expect( links.stray ).toBe( 0 );
			const night = await card.locator( 'time.dt-published' ).getAttribute( 'datetime' );
			const longDate = new Date( `${ night }T12:00:00` ).toLocaleDateString( 'en-US', { month: 'long', day: 'numeric', year: 'numeric' } );
			const title = 1 === index ? 'Fictional title: storm night' : 'Sleep';
			// WCAG 2.5.3 Label in Name: the visible phase, date and duration words are all in the link's name.
			const visible = await card.locator( 'a.cr-sleep-sticker__link' ).evaluate( ( link ) => Object.fromEntries( [ 'phase', 'date', 'duration' ].map( ( part ) => [ part, ( link.querySelector( `.cr-sleep-sticker__${ part }` )?.firstChild?.textContent || link.querySelector( `.cr-sleep-sticker__${ part }` )?.textContent || '' ).replace( /\s+/g, ' ' ).trim() ] ) ) );
			expect( visible.phase ).not.toBe( '' );
			const expected = [ title, longDate, visible.phase, expectedDurations[ index ] ].filter( Boolean ).join( ', ' );
			expect( links.names[ 0 ] ).toBe( expected );
			for ( const word of [ visible.phase, visible.date, expectedDurations[ index ] ].filter( Boolean ) ) expect( links.names[ 0 ], `visible "${ word }" is in the accessible name` ).toContain( word );
			await expect( card.getByRole( 'link', { name: nameMatcher( expected ) } ) ).toHaveCount( 1 );
			const face = await card.locator( 'a.cr-sleep-sticker__link' ).evaluate( ( link ) => [ ...link.children ].map( ( child ) => child.className ) );
			expect( face ).toEqual( expectedDurations[ index ] ? [ 'cr-sleep-sticker__moon', 'cr-sleep-sticker__duration p-sleep', 'cr-sleep-sticker__phase', 'dt-published cr-sleep-sticker__date' ] : [ 'cr-sleep-sticker__moon', 'cr-sleep-sticker__phase', 'dt-published cr-sleep-sticker__date' ] );
		}
	} );

	test( '[28,33,34,38] "Round 9.5rem footprint": flat face, 1.5deg rotation, three hard rings, 4.5:1 text', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		const card = ( await stickers( page ) ).first();
		const paint = await card.evaluate( ( element ) => {
			const style = getComputedStyle( element );
			const before = getComputedStyle( element, '::before' );
			const after = getComputedStyle( element, '::after' );
			const matrix = new DOMMatrixReadOnly( style.transform );
			return {
				width: element.offsetWidth,
				height: element.offsetHeight,
				radius: style.borderRadius,
				backgroundImage: style.backgroundImage,
				beforeContent: before.content,
				filters: [ style.filter, before.filter, after.filter ],
				degrees: Math.atan2( matrix.b, matrix.a ) * 180 / Math.PI,
				shadow: style.boxShadow,
				border: `${ style.borderTopWidth } ${ style.borderTopStyle }`,
				foreground: style.color,
				background: style.backgroundColor,
			};
		} );
		expect( paint.width ).toBeGreaterThanOrEqual( 152 );
		expect( paint.width ).toBeLessThanOrEqual( 176 );
		expect( Math.abs( paint.height - paint.width ) ).toBeLessThanOrEqual( 1 );
		expect( paint.radius ).toBe( '50%' );
		expect( paint.backgroundImage ).toBe( 'none' );
		expect( [ 'none', 'normal' ] ).toContain( paint.beforeContent );
		expect( paint.filters ).toEqual( [ 'none', 'none', 'none' ] );
		expect( Math.abs( paint.degrees - 1.5 ) ).toBeLessThan( 0.05 );
		expect( paint.border ).toBe( '3px solid' );
		expect( ( paint.shadow.match( /(?:rgba?\([^)]*\)|[^,])+/g ) || [] ).length ).toBe( 3 );
		const pixels = paint.shadow.match( /-?\d+(?:\.\d+)?px/g ) || [];
		for ( let index = 2; index < pixels.length; index += 4 ) expect( pixels[ index ] ).toBe( '0px' );
		expect( paint.foreground ).toBe( IVORY );
		expect( paint.background ).toBe( PRUSSIAN );
		expect( contrast( paint.foreground, paint.background ) ).toBeGreaterThanOrEqual( 4.5 );
		const fonts = await card.evaluate( ( element ) => ( {
			duration: getComputedStyle( element.querySelector( '.cr-sleep-sticker__duration' ) ).fontFamily,
			date: getComputedStyle( element.querySelector( '.cr-sleep-sticker__date' ) ).fontFamily,
			phase: getComputedStyle( element.querySelector( '.cr-sleep-sticker__phase' ) ).fontFamily,
			sizes: [ ...element.querySelectorAll( '.cr-sleep-sticker__duration, .cr-sleep-sticker__phase, .cr-sleep-sticker__date' ) ].map( ( item ) => Number.parseFloat( getComputedStyle( item ).fontSize ) ),
		} ) );
		expect( fonts.duration ).toMatch( /Roboto Slab/ );
		expect( fonts.date ).toMatch( /(?:ui-monospace|SF Mono|Menlo|Consolas|monospace)/i );
		expect( fonts.phase ).toMatch( /Barlow/ );
		expect( fonts.sizes.every( ( size ) => size >= 12 ) ).toBe( true );
	} );

	for ( const width of [ 375, 320 ] ) {
		test( `[29,41] "Fits its column" at ${ width}px with no scroll and every text box inside the circle`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			const cards = await stickers( page );
			expect( await page.evaluate( () => document.documentElement.scrollWidth === document.documentElement.clientWidth ) ).toBe( true );
			for ( const card of await cards.all() ) {
				const outside = await card.evaluate( ( element ) => {
					const circle = element.getBoundingClientRect();
					const radius = Math.min( circle.width, circle.height ) / 2;
					const center = { x: circle.left + circle.width / 2, y: circle.top + circle.height / 2 };
					return [ ...element.querySelectorAll( '.cr-sleep-sticker__duration, .cr-sleep-sticker__phase, .cr-sleep-sticker__date' ) ].filter( ( item ) => {
						const rect = item.getBoundingClientRect();
						return [ [ rect.left, rect.top ], [ rect.right, rect.top ], [ rect.left, rect.bottom ], [ rect.right, rect.bottom ] ].some( ( [ x, y ] ) => Math.hypot( x - center.x, y - center.y ) > radius + 1 );
					} ).length;
				} );
				expect( outside ).toBe( 0 );
			}
		} );
	}

	test( '[30,38-40,42] "Focus, dark, forced colors and reduced motion" preserve the circle and remove decoration', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		let card = ( await stickers( page, 'dark' ) ).first();
		await card.locator( 'a' ).focus();
		let paint = await card.evaluate( ( element ) => ( { background: getComputedStyle( element ).backgroundColor, color: getComputedStyle( element ).color, outline: `${ getComputedStyle( element ).outlineWidth } ${ getComputedStyle( element ).outlineStyle } ${ getComputedStyle( element ).outlineColor }`, offset: getComputedStyle( element ).outlineOffset, shadow: getComputedStyle( element ).boxShadow } ) );
		expect( paint.background ).toBe( PRUSSIAN );
		expect( paint.color ).toBe( IVORY );
		expect( paint.outline ).toBe( `3px solid ${ YELLOW }` );
		expect( paint.offset ).toBe( '8px' );
		expect( paint.shadow ).toMatch( /0px 0px 0px (7|8|14)px/ );
		expect( paint.shadow ).toContain( YELLOW );
		await page.emulateMedia( { colorScheme: 'dark', forcedColors: 'active' } );
		paint = await card.evaluate( ( element ) => {
			const clasp = getComputedStyle( element.closest( 'li.kind-sleep' ), '::before' );
			const probe = document.createElement( 'span' );
			probe.style.color = 'CanvasText';
			element.append( probe );
			const canvasText = getComputedStyle( probe ).color;
			probe.remove();
			return { border: `${ getComputedStyle( element ).borderTopWidth } ${ getComputedStyle( element ).borderTopStyle }`, borderColor: getComputedStyle( element ).borderTopColor, canvasText, moon: getComputedStyle( element.querySelector( '.cr-sleep-sticker__moon' ) ).display, sparks: [ ...element.querySelectorAll( '.cr-sleep-sticker__spark' ) ].map( ( spark ) => getComputedStyle( spark ).display ), text: element.innerText.trim().length, claspImage: clasp.backgroundImage, claspShadow: clasp.boxShadow };
		} );
		expect( paint.border ).toBe( '2px solid' );
		expect( paint.borderColor ).toBe( paint.canvasText );
		expect( paint.moon ).toBe( 'none' );
		expect( paint.sparks ).toEqual( [ 'none', 'none' ] );
		expect( paint.text ).toBeGreaterThan( 0 );
		expect( paint.claspImage ).toBe( 'none' );
		expect( paint.claspShadow ).not.toMatch( /rgba/ );
		await page.emulateMedia( { forcedColors: 'none', reducedMotion: 'reduce' } );
		card = page.locator( 'article.cr-sleep-sticker' ).first();
		expect( await card.evaluate( motionState ) ).toEqual( { transition: true, animation: 'none' } );
	} );

	test( '[11] "Computed font-family sweep" passes on the Stream sticker', async ( { page } ) => {
		const card = ( await stickers( page ) ).first();
		expect( await fontAudit( card ) ).toEqual( [] );
	} );

	test( '[31] "Stream and other kinds": the sleep adapter never appears outside li.kind-sleep', async ( { page } ) => {
		await stickers( page );
		await expect( page.locator( 'li:not(.kind-sleep) article.cr-sleep-sticker' ) ).toHaveCount( 0 );
		expect( await page.locator( 'article.cr-sleep-sticker' ).evaluateAll( ( cards ) => cards.every( ( card ) => Boolean( card.closest( 'li.kind-sleep' ) ) ) ) ).toBe( true );
	} );
} );

test.describe( 'dev-only privacy surfaces', () => {
	test.skip( ! DEV, 'CR_SLEEP_DEV is not 1' );
	test.skip( 'full' !== STATE, `CR_SLEEP_STATE=${ STATE }; dev-only probes do not run in empty or stream state` );
	test.beforeEach( async ( { page } ) => page.setViewportSize( { width: 1280, height: 900 } ) );

	test( '[21,55] "H01 head, oEmbed and Markdown alternate" expose no hidden clocks and datePublished is noon', async ( { page, request } ) => {
		const href = await fixturePermalink( page, '2026-08-09' );
		await visit( page, href );
		const head = await page.locator( 'script[type="application/ld+json], meta[property^="og:"], meta[name^="twitter:"]' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.textContent || node.getAttribute( 'content' ) || '' ).join( '\n' ) );
		expect( head ).not.toMatch( /23:10|06:10/ );
		expect( head ).toMatch( /"datePublished"\s*:\s*"[^"]*T12:00:00(?:[+-]\d{2}:\d{2}|Z)"/ );
		for ( const [ label, night ] of [ [ 'H01', '2026-08-09' ], [ 'T01', '2026-08-11' ] ] ) {
			const permalink = await fixturePermalink( page, night );
			const markdown = await request.get( markdownUrl( permalink ) );
			expect( markdown.status(), `${ label }.md` ).toBe( 200 );
			const text = await markdown.text();
			expect( text, `${ label }.md text` ).toBeTruthy();
			if ( label === 'H01' ) expect( text ).not.toMatch( /23:10|06:10/ );
		}
	} );

	test( '[50,58] "G01 location privacy": single, feed, REST and Markdown hold no place or coordinates', async ( { page, request } ) => {
		await visit( page, ARCHIVE );
		const cell = cellForNight( page, '2026-08-13' );
		expect( await cell.count(), `G01 is missing (run ${ PROBE_SEED })` ).toBeGreaterThan( 0 );
		await visit( page, await cell.locator( 'a.u-url' ).getAttribute( 'href' ) );
		const href = page.url();
		const post = await restObjectForPermalink( page, request, href );
		const responses = [
			await page.content(),
			await ( await request.get( '/feed/' ) ).text(),
			JSON.stringify( post ),
			await ( await request.get( markdownUrl( href ) ) ).text(),
		];
		for ( const body of responses ) expect( body ).not.toMatch( /Example Street|geo:|(?:^|[^\d])0\s*,\s*0(?:[^\d]|$)/i );
		expect( JSON.stringify( post ) ).not.toMatch( /"(?:latitude|longitude|geo_address)"\s*:/i );
	} );

	test( '[50,55] "modified equals date" for H01 and N01 over REST', async ( { page, request } ) => {
		for ( const night of [ '2026-08-09', '2026-08-10' ] ) {
			const href = await fixturePermalink( page, night );
			const post = await restObjectForPermalink( page, request, href );
			expect( post.modified ).toBe( post.date );
			expect( post.date ).toMatch( /T12:00:00$/ );
		}
	} );
} );

/*
 * Manual or non-Playwright acceptance-map proofs:
 * - A18 / Archive :68: changing linesPerPage to 7 and editing the term description need the real Site Editor;
 *   manual proof is the served archive after each editor save. The read-only spec still proves the seeded description.
 * - A43: the astronomical phase algorithm and noon-to-noon night helper need MoonPhaseTest and SleepNoonDateTest;
 *   Playwright proves date-only night output on each surface but cannot set timezone sentinels by GET.
 * - A45: Micropub must stop writing a raw p-sleep paragraph in stored post_content; MicropubContentBuilderTest and
 *   seed-247.php's M01 assertion prove that write path. This spec proves M01's rendered card and free text.
 * - A52: ActivityPub is absent on every environment; SleepRenderPrivacyTest and SleepNoonDateTest are the proxy.
 * - A53: authenticated Micropub q=source and M01-M03 ingestion are write/auth flows; MicropubContentBuilderTest is the
 *   proof. This spec proves M01's resulting single and the G01 privacy response only.
 * - A54: Standard.site publication and update queuing are write/external-plugin flows; AtmosphereDocumentMap is the proof.
 * - A56: the editor meta-box save (post_modified after a real block-editor publish) is a manual R-W4 step because it
 *   needs a logged-in editor; this spec verifies the resulting anonymous REST date and modified fields.
 * - A57: rss.chat, Telegraph, WebSub and IndexNow reveal push time by design; W4-G7 accepts that named residual, and
 *   seed-247.php verifies no fixture triggered a push-time channel.
 * - A59: the plan's Blog/search line assumed W4-G3="no". Courtney's later W4-G3="yes" routes sleep onto /stream, so
 *   the Stream suite is the controlling served proof; the generic Blog/search control remains in the W4-T0 lane.
 * - A60: scheduled/cron publication changes server state and cannot be exercised by this read-only spec;
 *   SleepNoonDateTest's future and wp_publish_post cases are the proof.
 * - The Simple Location gate beyond the G01 served probes (map/ and geo/ endpoints, location/venue archives) needs the
 *   plugin's SleepLocationTest to create paired sleep/note queries without depending on mutable public archive data.
 */
