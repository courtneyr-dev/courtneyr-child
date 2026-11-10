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

const slugOf = ( key ) => `seed-244-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const archivePath = ( kind, page = 1 ) => {
	const base = 'trip' === kind ? TRIP_ARCHIVE : ITINERARY_ARCHIVE;
	return 1 === page ? base : `${ base }page/${ page }/`;
};

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_TRAVEL_REQUIRE_SEED=1: ${ reason }` );
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

const archiveEntries = ( page, kind ) => page.locator( 'trip' === kind ? 'main.cr-archive--trip .pkiw-trip-entry' : 'main.cr-archive--itinerary .pkiw-itinerary-entry' );
const fixtureEntry = ( page, kind, key ) => archiveEntries( page, kind ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

test.describe( 'trip passport archive', () => {
	test.skip( 'trip' !== SET, 'trip fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'archive owns its main-scoped map and twelve-entry first page', async ( { page } ) => {
		await openArchive( page, 'trip' );
		await expect( page.locator( 'main.cr-archive--trip h1' ) ).toHaveCount( 1 );
		await expect( archiveEntries( page, 'trip' ) ).toHaveCount( 12 );
		await expect( page.locator( 'main.cr-archive--trip .pkiw-travel-year__heading' ) ).toHaveText( [ '2026', '2025' ] );
		await expect( page.locator( 'main.cr-archive--trip .cr-travel > .pkiw-travel-map' ) ).toBeVisible();
		await expect( page.locator( 'main.cr-archive--trip .cr-travel > .cr-travel__entries' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main.cr-archive--trip .wp-block-query-pagination' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main:not(.cr-archive--trip) .cr-travel' ) ).toHaveCount( 0 );
	} );

	test( 'passport rows keep number chips, photos and public text inside each page', async ( { page } ) => {
		await openArchive( page, 'trip' );
		const entries = archiveEntries( page, 'trip' );
		for ( const entry of await entries.all() ) {
			await expect( entry.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( entry.locator( '.pkiw-travel-map__num' ) ).toHaveCount( 1 );
			const entryBox = await box( entry );
			const linkBox = await box( entry.locator( 'a' ) );
			expect( linkBox.height ).toBeGreaterThanOrEqual( 44 );
			expect( linkBox.x ).toBeGreaterThanOrEqual( entryBox.x - 2 );
			expect( linkBox.x + linkBox.width ).toBeLessThanOrEqual( entryBox.x + entryBox.width + 2 );
		}
		const html = await entries.evaluateAll( ( nodes ) => nodes.map( ( node ) => node.outerHTML ).join( '\n' ) );
		for ( const sentinel of [ 'Sentinel Street', 'SEA-LOCKED', 'Sample content' ] ) {
			expect( html ).not.toContain( sentinel );
		}
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
		await entry.locator( 'a' ).focus();
		await expect( entry.locator( 'a' ) ).toHaveCSS( 'outline-color', await resolvedToken( entry, '--cr-focus-ring' ) );

		await page.emulateMedia( { colorScheme: 'dark' } );
		expect( await page.locator( '.pkiw-travel-year__items' ).first().evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).toContain( await resolvedToken( entry, '--cr-selective-yellow' ) );
		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		expect( await page.locator( '.pkiw-travel-year__items' ).first().evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).toContain( await resolvedToken( entry, '--cr-selective-yellow' ) );
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
		for ( const entry of await archiveEntries( page, 'itinerary' ).all() ) {
			await expect( entry.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( entry ).toHaveCSS( 'border-top-width', '2px' );
			expect( await entry.evaluate( ( node ) => getComputedStyle( node, '::before' ).content ) ).not.toBe( 'none' );
			const linkBox = await box( entry.locator( 'a' ) );
			expect( linkBox.height ).toBeGreaterThanOrEqual( 44 );
		}
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
		}
		const scripts = page.locator( 'script[src*="map-view"]' );
		await expect( scripts ).toHaveCount( 1 );
		await expect( scripts ).not.toHaveAttribute( 'type', 'module' );
	} );

	test( 'itinerary single renders stitched legs and visible route fields', async ( { page } ) => {
		test.skip( 'itinerary' !== SET, 'itinerary fixture set' );
		const card = await openSingle( page, 'I01', '.wp-block-post-kinds-indieweb-itinerary-card.pkiw-itinerary' );
		await expect( card.locator( '.pkiw-itinerary__leg' ) ).not.toHaveCount( 0 );
		await expect( card.locator( '.pkiw-itinerary__pass dt' ).first() ).toHaveText( /From|To|Departure|Arrival|Carrier/ );
		await expect( card ).toHaveCSS( 'border-top-width', '2px' );
	} );

	test( 'future fixtures omit embargoed details and exact coordinates', async ( { page } ) => {
		test.skip( 'future' !== SET, 'future fixture set' );
		for ( const key of [ 'F01', 'F02', 'F03', 'F04' ] ) {
			const response = await open( page, singlePath( key ) );
			skipUnlessSeeded( 404 === response?.status(), `no future fixture ${ key }` );
			const main = await page.locator( 'main' ).innerHTML();
			for ( const sentinel of [ 'Sentinel Street', 'SEA-LOCKED', '47.6062', '-122.3321' ] ) {
				expect( main, `${ key } embargo` ).not.toContain( sentinel );
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
			const angle = await object.evaluate( ( node ) => getComputedStyle( node ).transform );
			expect( Math.abs( transformAngle( angle ) ), 'object tilt' ).toBeLessThanOrEqual( 2.01 );
		}
		await expect( trip.locator( '.pkiw-route .screen-reader-text' ) ).toHaveText( ' to ' );
		await expect( itinerary.locator( '.cr-travel-card__count' ) ).toHaveText( /^\d+ legs?$/ );
		const html = `${ await trip.innerHTML() }\n${ await itinerary.innerHTML() }`;
		for ( const sentinel of [ 'Sentinel Street', 'SEA-LOCKED', 'Sample content' ] ) {
			expect( html ).not.toContain( sentinel );
		}
	} );

	test( 'focus, reduced motion and forced colors preserve access cues', async ( { page } ) => {
		await open( page, STREAM );
		const object = page.locator( '.cr-travel-card--stream' ).first();
		skipUnlessSeeded( 0 === await object.count(), 'no travel object on Stream' );
		await object.locator( 'a' ).focus();
		await expect( object.locator( 'a' ) ).toHaveCSS( 'outline-width', '3px' );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		expect( parseFloat( await object.evaluate( ( node ) => getComputedStyle( node ).transitionDuration ) ) ).toBeLessThanOrEqual( 0.01 );

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
		await expect( page.locator( 'main .wp-block-post-kinds-indieweb-trip-card.pkiw-trip' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main .pkiw-singular-entry > p' ).first() ).toHaveText( 'Paragraph before the travel card.' );
	} );
} );
