// `CR_SPEC=travel-244.spec.mjs`: issue 244 trip and itinerary surfaces.
// Read-only GETs. Seed one set with the vault's assets/244-trips-itineraries/seed-244.php.
import { test, expect } from '@playwright/test';

const SET = process.env.CR_TRAVEL_SET || '';
const REQUIRE_SEED = '1' === process.env.CR_TRAVEL_REQUIRE_SEED;
const STREAM = process.env.CR_TRAVEL_STREAM_PATH || '/stream/';
const TRIP_ARCHIVE = ( process.env.CR_TRAVEL_TRIP_ARCHIVE_PATH || '/kind/trip/' ).replace( /\/?$/, '/' );
const ITINERARY_ARCHIVE = ( process.env.CR_TRAVEL_ITINERARY_ARCHIVE_PATH || '/kind/itinerary/' ).replace( /\/?$/, '/' );
const TILE_FIXTURE = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64' );
const WIDTHS = [ 1440, 800, 375, 320 ];
const FIXTURE_TABLE = {
	T01: { kind: 'trip', year: '2026', mapped: true }, T02: { kind: 'trip', year: '2026', mapped: true },
	T03: { kind: 'trip', year: '2026', mapped: false }, T04: { kind: 'trip', year: '2026', mapped: true },
	T05: { kind: 'trip', year: '2026', mapped: true }, T06: { kind: 'trip', year: '2026', mapped: false },
	T07: { kind: 'trip', year: '2025', mapped: true }, T08: { kind: 'trip', year: '2025', mapped: true },
	T09: { kind: 'trip', year: '2025', mapped: true }, T10: { kind: 'trip', year: '2025', mapped: true },
	T11: { kind: 'trip', year: '2025', mapped: false }, T12: { kind: 'trip', year: '2024', mapped: false },
	T13: { kind: 'trip', year: '2024', mapped: false }, T14: { kind: 'trip', year: '2024', mapped: false },
};
const tripKeys = Object.keys( FIXTURE_TABLE );
const expectedPageKeys = ( page ) => tripKeys.slice( ( page - 1 ) * 12, page * 12 );
const expectedYears = ( page ) => [ ...new Set( expectedPageKeys( page ).map( ( key ) => FIXTURE_TABLE[ key ].year ) ) ];

const slugOf = ( key ) => `seed-244-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const archivePath = ( kind, page = 1 ) => {
	const base = 'trip' === kind ? TRIP_ARCHIVE : ITINERARY_ARCHIVE;
	return 1 === page ? base : `${ base }page/${ page }/`;
};

function skipUnlessSeeded( missing, reason ) {
	if ( missing && ( SET || REQUIRE_SEED ) ) {
		throw new Error( `active fixture set ${ SET || '(required)' }: ${ reason }` );
	}
	test.skip( missing, reason );
}

async function keepRequestsLocal( page ) {
	await page.route( ( url ) => [ 'http:', 'https:' ].includes( url.protocol ) && ! [ 'localhost', '127.0.0.1', '[::1]' ].includes( url.hostname ), ( route ) => {
		const url = new URL( route.request().url() );
		return /(^|\.)tile\.openstreetmap\.org$/i.test( url.hostname )
			? route.fulfill( { body: TILE_FIXTURE, contentType: 'image/png' } )
			: route.abort();
	} );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 400 ? 900 : 1100 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function openArchive( page, kind, number = 1, width = 1440 ) {
	const response = await open( page, archivePath( kind, number ), width );
	skipUnlessSeeded( 404 === response?.status(), `no ${ kind } archive page ${ number }` );
	const selector = 'trip' === kind ? '.pkiw-trip-entry' : '.pkiw-itinerary-entry';
	skipUnlessSeeded( 0 === await page.locator( selector ).count(), `${ kind } fixtures are not seeded` );
	return response;
}

async function openSingle( page, key, selector, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( selector ).count(), `no single fixture ${ key }` );
	return page.locator( selector ).first();
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function resolvedToken( locator, token ) {
	return locator.evaluate( ( node, property ) => {
		const probe = node.ownerDocument.createElement( 'span' );
		probe.style.color = `var(${ property })`;
		node.append( probe );
		const value = getComputedStyle( probe ).color;
		probe.remove();
		return value;
	}, token );
}

async function box( locator ) {
	const value = await locator.boundingBox();
	expect( value ).not.toBeNull();
	return value;
}

function transformAngle( transform ) {
	if ( 'none' === transform ) {
		return 0;
	}
	const values = transform.slice( transform.indexOf( '(' ) + 1, -1 ).split( ',' ).map( Number );
	return Math.atan2( values[1], values[0] ) * 180 / Math.PI;
}

function rgb( color ) {
	const values = color.match( /[\d.]+/g )?.slice( 0, 3 ).map( Number );
	if ( ! values || 3 !== values.length ) {
		throw new Error( `Could not parse color ${ color }` );
	}
	return values;
}

function luminance( color ) {
	return rgb( color ).map( ( value ) => value / 255 ).map( ( value ) => value <= 0.03928 ? value / 12.92 : ( ( value + 0.055 ) / 1.055 ) ** 2.4 ).reduce( ( sum, value, index ) => sum + value * [ 0.2126, 0.7152, 0.0722 ][ index ], 0 );
}

function contrast( first, second ) {
	const [ light, dark ] = [ luminance( first ), luminance( second ) ].sort( ( a, b ) => b - a );
	return ( light + 0.05 ) / ( dark + 0.05 );
}

async function assertFocusContrast( page, target, paper, label ) {
	await target.focus();
	const colors = await target.evaluate( ( node, paperNode ) => {
		const style = getComputedStyle( node );
		const pageStyle = getComputedStyle( node.ownerDocument.body );
		return {
			outline: style.outlineColor,
			paper: getComputedStyle( paperNode ).backgroundColor,
			page: pageStyle.backgroundColor,
			shadows: [ ...style.boxShadow.matchAll( /rgba?\([^)]*\)/g ) ].map( ( match ) => match[0] ),
		};
	}, await paper.elementHandle() );
	expect( contrast( colors.outline, colors.paper ), `${ label } ring against paper` ).toBeGreaterThanOrEqual( 3 );
	expect( Math.max( ...colors.shadows.map( ( color ) => contrast( color, colors.page ) ) ), `${ label } halo against page` ).toBeGreaterThanOrEqual( 3 );
}

const archiveEntries = ( page, kind ) => page.locator( 'trip' === kind ? 'main.cr-archive--trip .pkiw-trip-entry' : 'main.cr-archive--itinerary .pkiw-itinerary-entry' );
const fixtureEntry = ( page, kind, key ) => archiveEntries( page, kind ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

test.describe( 'trip passport archive', () => {
	test.skip( 'trip' !== SET, 'trip fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'archive owns its main-scoped map and twelve-entry first page', async ( { page } ) => {
		await openArchive( page, 'trip' );
		await expect( page.locator( 'main.cr-archive--trip h1' ) ).toHaveCount( 1 );
		await expect( archiveEntries( page, 'trip' ) ).toHaveCount( 12 );
		await expect( page.locator( 'main.cr-archive--trip .pkiw-travel-year__heading' ) ).toHaveText( expectedYears( 1 ) );
		await expect( page.locator( 'main.cr-archive--trip .cr-travel > .pkiw-travel-map' ) ).toBeVisible();
		await expect( page.locator( 'main.cr-archive--trip .cr-travel > .cr-travel__entries' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main.cr-archive--trip .wp-block-query-pagination' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main:not(.cr-archive--trip) .cr-travel' ) ).toHaveCount( 0 );
	} );

	test( 'passport rows keep number chips, photos and public text inside each page', async ( { page } ) => {
		await openArchive( page, 'trip' );
		const entries = archiveEntries( page, 'trip' );
		for ( const [ index, entry ] of ( await entries.all() ).entries() ) {
			const key = expectedPageKeys( 1 )[ index ];
			await expect( entry.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( entry.locator( '.pkiw-travel-map__num' ) ).toHaveCount( FIXTURE_TABLE[ key ].mapped ? 1 : 0 );
			const entryBox = await box( entry );
			const linkBox = await box( entry.locator( 'a' ) );
			expect( linkBox.height ).toBeGreaterThanOrEqual( 44 );
			expect( linkBox.x ).toBeGreaterThanOrEqual( entryBox.x - 2 );
			expect( linkBox.x + linkBox.width ).toBeLessThanOrEqual( entryBox.x + entryBox.width + 2 );
		}
		const html = await entries.evaluateAll( ( nodes ) => nodes.map( ( node ) => node.outerHTML ).join( '\n' ) );
		for ( const sentinel of [ 'Sentinel Quay', '1 Sentinel Way', 'Sentinelburg', 'SE244', 'sentinel.example', 'osm-244244', 'venue-244244', '0.244244', '0.442442', 'Sentinel Air' ] ) {
			expect( html ).not.toContain( sentinel );
		}
		expect( html ).toContain( 'Trip fixture T01' );
		const stamp = entries.locator( '.cr-stamp' ).first();
		const stampShape = stamp.locator( 'rect, circle' ).first();
		await expect( stamp ).toBeVisible();
		expect( await stampShape.evaluate( ( node ) => getComputedStyle( node ).stroke ) ).not.toBe( 'none' );
		expect( await stampShape.evaluate( ( node ) => getComputedStyle( node ).fill ) ).not.toBe( 'rgb(0, 0, 0)' );
		const photo = entries.locator( '.pkiw-trip-entry__image' ).first();
		if ( await photo.count() ) {
			await expect( photo ).toHaveAttribute( 'alt', '' );
			expect( await photo.evaluate( ( node ) => getComputedStyle( node ).borderTopWidth ) ).toBe( '2px' );
		}
	} );

	test( 'pagination repeats 2024 and ends after fourteen trips', async ( { page } ) => {
		await openArchive( page, 'trip', 2 );
		await expect( archiveEntries( page, 'trip' ) ).toHaveCount( 2 );
		await expect( page.locator( 'main.cr-archive--trip .pkiw-travel-year__heading' ).first() ).toHaveText( '2024' );
		const protectedEntry = fixtureEntry( page, 'trip', 'T14' );
		await expect( protectedEntry.locator( 'a' ) ).toHaveAccessibleName( /^Protected: / );
		await expect( protectedEntry.locator( '.cr-stamp-mark, .pkiw-travel-map__num, img' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-query-pagination-next' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( 1 );
		const missing = await open( page, archivePath( 'trip', 3 ) );
		expect( missing?.status() ).toBe( 404 );
	} );

	test( 'map summary follows the map and trip rows keep chip, photo, title and stamp on one row', async ( { page } ) => {
		await openArchive( page, 'trip' );
		const mapBox = await box( page.locator( 'main.cr-archive--trip .pkiw-travel-map' ) );
		const summaryBox = await box( page.locator( 'main.cr-archive--trip .pkiw-travel-map__summary' ) );
		expect( Math.abs( summaryBox.y - ( mapBox.y + mapBox.height ) ), 'summary directly below map' ).toBeLessThanOrEqual( 3 );
		const row = fixtureEntry( page, 'trip', 'T07' );
		const centers = [];
		for ( const selector of [ '.pkiw-travel-map__num', '.pkiw-trip-entry__image', '.pkiw-travel-entry__title', '.cr-stamp-mark' ] ) {
			const item = await box( row.locator( selector ) );
			centers.push( item.y + item.height / 2 );
		}
		expect( Math.max( ...centers ) - Math.min( ...centers ), 'row item vertical centers' ).toBeLessThanOrEqual( 12 );
	} );

	test( 'archive changes from stacked to map-and-passport without overflow', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await openArchive( page, 'trip', 1, width );
			const columns = await page.locator( 'main.cr-archive--trip .cr-travel' ).evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length );
			expect( columns, `${ width }px columns` ).toBe( width >= 769 ? 2 : 1 );
			await assertNoOverflow( page, `${ width }px trip archive` );
		}
	} );

	test( 'JavaScript off keeps the complete list and hides the empty map frame', async ( { browser } ) => {
		const context = await browser.newContext( { javaScriptEnabled: false, viewport: { width: 1440, height: 1100 } } );
		const page = await context.newPage();
		await keepRequestsLocal( page );
		await openArchive( page, 'trip' );
		await expect( archiveEntries( page, 'trip' ) ).toHaveCount( 12 );
		await expect( page.locator( '.pkiw-travel-map' ) ).toBeHidden();
		await expect( page.locator( '.pkiw-travel-map__summary' ) ).toBeHidden();
		await context.close();
	} );

	test( 'map controls, pins, focus and dark paint use tokens', async ( { page } ) => {
		await openArchive( page, 'trip' );
		const map = page.locator( 'main.cr-archive--trip .pkiw-travel-map' ).first();
		const pin = map.locator( '.pkiw-pin' ).first();
		const entry = archiveEntries( page, 'trip' ).first();
		for ( const target of await map.locator( '.leaflet-bar a, .pkiw-pin' ).all() ) {
			const targetBox = await box( target );
			expect( targetBox.width ).toBeGreaterThanOrEqual( 44 );
			expect( targetBox.height ).toBeGreaterThanOrEqual( 44 );
		}
		if ( await pin.count() ) {
			await pin.focus();
			await expect( pin ).toHaveCSS( 'outline-width', '3px' );
			await expect( pin ).toHaveCSS( 'background-color', await resolvedToken( pin, '--cr-selective-yellow' ) );
		}
		const link = entry.locator( 'a' );
		const paper = entry.locator( 'xpath=ancestor::*[contains(@class,"pkiw-travel-year__items")][1]' );
		const chip = entry.locator( '.pkiw-travel-map__num' );
		const chipShadow = await chip.evaluate( ( node ) => getComputedStyle( node ).boxShadow );
		await assertFocusContrast( page, link, paper, 'light trip link' );
		expect( await chip.evaluate( ( node ) => getComputedStyle( node ).boxShadow ), 'year focus does not leak halo into chip' ).toBe( chipShadow );

		await page.emulateMedia( { colorScheme: 'dark' } );
		await assertFocusContrast( page, link, paper, 'dark trip link' );
		expect( await page.locator( '.pkiw-travel-year__items' ).first().evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).toContain( await resolvedToken( entry, '--cr-selective-yellow' ) );
		const attribution = map.locator( '.leaflet-control-attribution a' ).first();
		if ( await attribution.count() ) {
			const colors = await attribution.evaluate( ( node ) => ( { text: getComputedStyle( node ).color, background: getComputedStyle( node.parentElement ).backgroundColor } ) );
			expect( contrast( colors.text, colors.background ), 'dark attribution link contrast' ).toBeGreaterThanOrEqual( 4.5 );
		}
		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		expect( await page.locator( '.pkiw-travel-year__items' ).first().evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).toContain( await resolvedToken( entry, '--cr-selective-yellow' ) );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		const animatedMapPart = map.locator( '.leaflet-zoom-animated' ).first();
		if ( await animatedMapPart.count() ) {
			await expect( animatedMapPart ).toHaveCSS( 'transition-duration', '0s' );
		}
		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
		const route = map.locator( 'svg .pkiw-route' ).first();
		if ( await route.count() ) {
			const colors = await route.evaluate( ( node ) => ( { stroke: getComputedStyle( node ).stroke, canvasText: getComputedStyle( node.ownerDocument.body ).color } ) );
			expect( colors.stroke ).toBe( colors.canvasText );
		}
	} );
} );

test.describe( 'itinerary ticket-wallet archive', () => {
	test.skip( 'itinerary' !== SET, 'itinerary fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'wallet years and eight entries follow the five-plus-three fixture split', async ( { page } ) => {
		await openArchive( page, 'itinerary' );
		await expect( archiveEntries( page, 'itinerary' ) ).toHaveCount( 8 );
		await expect( page.locator( 'main.cr-archive--itinerary .pkiw-travel-year__heading' ) ).toHaveText( [ '2026', '2025' ] );
		await expect( page.locator( 'main.cr-archive--itinerary .pkiw-travel-map' ) ).toBeVisible();
		await expect( page.locator( 'main.cr-archive--itinerary .wp-block-query-pagination-next' ) ).toHaveCount( 0 );
	} );

	test( 'ticket wallets keep one destination and stitched paper paint', async ( { page } ) => {
		await openArchive( page, 'itinerary' );
		const yearList = page.locator( 'main.cr-archive--itinerary .pkiw-travel-year__items' ).first();
		await expect( yearList ).toHaveCSS( 'border-top-width', '0px' );
		await expect( yearList ).toHaveCSS( 'box-shadow', 'none' );
		for ( const entry of await archiveEntries( page, 'itinerary' ).all() ) {
			await expect( entry.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( entry ).toHaveCSS( 'border-top-width', '2px' );
			expect( await entry.evaluate( ( node ) => getComputedStyle( node, '::before' ).content ) ).not.toBe( 'none' );
			const linkBox = await box( entry.locator( 'a' ) );
			expect( linkBox.height ).toBeGreaterThanOrEqual( 44 );
		}
		const firstFill = await archiveEntries( page, 'itinerary' ).nth( 0 ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor );
		const secondFill = await archiveEntries( page, 'itinerary' ).nth( 1 ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor );
		expect( firstFill, 'alternating wallet fills' ).not.toBe( secondFill );
		await assertNoOverflow( page, 'itinerary archive' );
	} );

	test( 'wallets use two columns above 608px and one below', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await openArchive( page, 'itinerary', 1, width );
			const list = page.locator( 'main.cr-archive--itinerary .pkiw-travel-year__items' ).first();
			const columns = await list.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length );
			expect( columns, `${ width }px wallet columns` ).toBe( width >= 608 ? 2 : 1 );
			await assertNoOverflow( page, `${ width }px itinerary archive` );
		}
	} );
} );

test.describe( 'travel singles and embargoes', () => {
	test.skip( ! [ 'trip', 'itinerary', 'future' ].includes( SET ), 'single or future fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'trip single renders ruled stops, public dates and one classic map script', async ( { page } ) => {
		test.skip( 'trip' !== SET, 'trip fixture set' );
		const card = await openSingle( page, 'T01', '.wp-block-post-kinds-indieweb-trip-card.pkiw-trip' );
		await expect( card.locator( '.pkiw-trip__stop' ) ).not.toHaveCount( 0 );
		for ( const stop of await card.locator( '.pkiw-trip__stop' ).all() ) {
			await expect( stop.locator( '.cr-stamp[aria-hidden="true"]' ) ).toHaveCount( 1 );
			await expect( stop ).toHaveCSS( 'border-left-width', '0px' );
		}
		const stampShape = card.locator( '.cr-stamp rect, .cr-stamp circle' ).first();
		expect( await stampShape.evaluate( ( node ) => getComputedStyle( node ).stroke ) ).not.toBe( 'none' );
		expect( await stampShape.evaluate( ( node ) => getComputedStyle( node ).fill ) ).not.toBe( 'rgb(0, 0, 0)' );
		const singleMap = card.locator( ':scope > .pkiw-travel-map' );
		await expect( singleMap ).toHaveCSS( 'border-top-width', '2px' );
		await expect( singleMap.locator( '.leaflet-bar a' ).first() ).toHaveCSS( 'width', '44px' );
		const scripts = page.locator( 'script[src*="map-view"]' );
		await expect( scripts ).toHaveCount( 1 );
		await expect( scripts ).not.toHaveAttribute( 'type', 'module' );
	} );

	test( 'itinerary single renders stitched legs and visible route fields', async ( { page } ) => {
		test.skip( 'itinerary' !== SET, 'itinerary fixture set' );
		const card = await openSingle( page, 'I01', '.wp-block-post-kinds-indieweb-itinerary-card.pkiw-itinerary' );
		await expect( card.locator( '.pkiw-itinerary__leg' ) ).not.toHaveCount( 0 );
		await expect( card.locator( '.pkiw-itinerary__leg-label' ).first() ).toHaveCSS( 'font-weight', '900' );
		await expect( card.locator( '.pkiw-itinerary__pass dt' ).first() ).toHaveText( /From|To|Departure|Arrival|Carrier/ );
		await expect( card ).toHaveCSS( 'border-top-width', '2px' );
	} );

	test( 'future fixtures omit embargoed details and exact coordinates', async ( { page } ) => {
		test.skip( 'future' !== SET, 'future fixture set' );
		for ( const key of [ 'F01', 'F02', 'F03', 'F04' ] ) {
			const response = await open( page, singlePath( key ) );
			skipUnlessSeeded( 404 === response?.status(), `no future fixture ${ key }` );
			const main = await page.locator( 'main' ).innerHTML();
			for ( const sentinel of [ 'Sentinel Quay', '1 Sentinel Way', 'Sentinelburg', 'SE244', 'sentinel.example', 'osm-244244', 'venue-244244', '0.244244', '0.442442', 'Sentinel Air' ] ) {
				expect( main, `${ key } embargo` ).not.toContain( sentinel );
			}
			if ( 'F02' === key ) {
				expect( main, 'F02 allowed past stop' ).toContain( 'Pine Harbor' );
			}
		}
	} );
} );

test.describe( 'travel Stream objects', () => {
	test.skip( 'stream' !== SET, 'stream fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'trip luggage tag and itinerary ticket use public facts only', async ( { page } ) => {
		const response = await open( page, STREAM );
		skipUnlessSeeded( 404 === response?.status(), `no Stream at ${ STREAM }` );
		const trip = page.locator( `.cr-travel-tag--trip:has(a[href*="${ slugOf( 'S01' ) }"])` ).first();
		const itinerary = page.locator( `.cr-travel-ticket--itinerary:has(a[href*="${ slugOf( 'S02' ) }"])` ).first();
		skipUnlessSeeded( 0 === await trip.count() || 0 === await itinerary.count(), 'travel Stream fixtures are not visible' );
		for ( const object of [ trip, itinerary ] ) {
			await expect( object.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( object.locator( 'img, picture, figure, svg' ) ).toHaveCount( 0 );
			await expect( object ).toHaveCSS( 'border-top-width', '2px' );
			expect( await object.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).not.toBe( 'rgba(0, 0, 0, 0)' );
			expect( await object.evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).not.toBe( 'none' );
			expect( await object.evaluate( ( node ) => getComputedStyle( node, '::before' ).content ) ).not.toBe( 'none' );
			const angle = await object.evaluate( ( node ) => getComputedStyle( node ).transform );
			expect( Math.abs( transformAngle( angle ) ), 'object tilt' ).toBeLessThanOrEqual( 2.01 );
		}
		await expect( trip.locator( '.pkiw-route .screen-reader-text' ) ).toHaveText( ' to ' );
		await expect( itinerary.locator( '.cr-travel-card__count' ) ).toHaveText( /^\d+ legs?$/ );
		const html = `${ await trip.innerHTML() }\n${ await itinerary.innerHTML() }`;
		for ( const sentinel of [ 'Sentinel Quay', '1 Sentinel Way', 'Sentinelburg', 'SE244', 'sentinel.example', 'osm-244244', 'venue-244244', '0.244244', '0.442442', 'Sentinel Air' ] ) {
			expect( html ).not.toContain( sentinel );
		}
		expect( html ).toContain( 'Pine Harbor' );
		expect( html ).toContain( 'Rainport' );
	} );

	test( 'focus, reduced motion and forced colors preserve access cues', async ( { page } ) => {
		await open( page, STREAM );
		const object = page.locator( '.cr-travel-card--stream' ).first();
		skipUnlessSeeded( 0 === await object.count(), 'no travel object on Stream' );
		const link = object.locator( 'a' );
		await assertFocusContrast( page, link, object, 'light Stream object' );
		await expect( link ).toHaveCSS( 'outline-width', '3px' );
		await page.emulateMedia( { colorScheme: 'dark' } );
		await assertFocusContrast( page, link, object, 'dark Stream object' );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		expect( await object.evaluate( ( node ) => [ getComputedStyle( node ).transitionDuration, getComputedStyle( node ).animationDuration ] ) ).toEqual( [ '0s', '0s' ] );

		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
		expect( await page.evaluate( () => matchMedia( '(forced-colors: active)' ).matches ) ).toBe( true );
		expect( await object.evaluate( ( node ) => [ '::before', '::after' ].map( ( pseudo ) => getComputedStyle( node, pseudo ).display ) ) ).toEqual( [ 'none', 'none' ] );
		await expect( object.locator( '.cr-travel-card__title' ) ).toBeVisible();
	} );
} );

test.describe( 'travel controls', () => {
	test.skip( 'controls' !== SET, 'controls fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'non-public controls stay out of archives and the note keeps its leading paragraph', async ( { page } ) => {
		await open( page, TRIP_ARCHIVE );
		for ( const key of [ 'C01', 'C03' ] ) {
			await expect( page.locator( `main.cr-archive--trip a[href*="${ slugOf( key ) }"]` ) ).toHaveCount( 0 );
		}
		await open( page, ITINERARY_ARCHIVE );
		await expect( page.locator( `main.cr-archive--itinerary a[href*="${ slugOf( 'C02' ) }"]` ) ).toHaveCount( 0 );

		const response = await open( page, singlePath( 'C04' ) );
		skipUnlessSeeded( 404 === response?.status(), 'no control fixture C04' );
		await expect( page.locator( 'body.kind-note' ) ).toHaveCount( 1 );
		await expect( page.locator( 'body.kind-trip' ) ).toHaveCount( 0 );
		await expect( page.locator( 'main .wp-block-post-kinds-indieweb-trip-card.pkiw-trip' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main .wp-block-post-kinds-indieweb-trip-card.pkiw-trip' ) ).toHaveCSS( 'border-top-width', '2px' );
		const stampShape = page.locator( 'main .pkiw-trip .cr-stamp rect, main .pkiw-trip .cr-stamp circle' ).first();
		expect( await stampShape.evaluate( ( node ) => getComputedStyle( node ).stroke ) ).not.toBe( 'none' );
		await expect( page.locator( 'main .pkiw-singular-entry > p' ).first() ).toHaveText( 'Paragraph before the travel card.' );
	} );
} );
