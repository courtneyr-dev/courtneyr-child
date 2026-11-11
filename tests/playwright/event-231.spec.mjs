// `CR_SPEC=event-231.spec.mjs`: W7-T231 Event archive, singles and Stream tab.
// Read-only GETs. Seed one set with the vault's assets/231-events/seed-231.php.
import { test, expect } from '@playwright/test';
import axe from 'axe-core';

const SET = process.env.CR_EVENT_SET || '';
const STATE = process.env.CR_EVENT_STATE || '';
const ARCHIVE = ( process.env.CR_EVENT_ARCHIVE_PATH || '/kind/event/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_EVENT_STREAM_PATH || '/stream/';
const WIDTHS = [ 1440, 800, 375, 320 ];
const SENTINELS = [ 'Sentinel Hall', '1 Sentinel Way', '0.123456', '0.654321' ];

const slugOf = ( key ) => `seed-231-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;

function fixtureDay( offset ) {
	const parts = Object.fromEntries( new Intl.DateTimeFormat( 'en-US', { timeZone: 'America/New_York', year: 'numeric', month: 'numeric', day: 'numeric' } ).formatToParts( new Date() ).filter( ( part ) => 'literal' !== part.type ).map( ( part ) => [ part.type, Number( part.value ) ] ) );
	const date = new Date( Date.UTC( parts.year, parts.month - 1, parts.day + offset, 16 ) );
	const result = Object.fromEntries( new Intl.DateTimeFormat( 'en-US', { timeZone: 'America/New_York', year: 'numeric', month: 'numeric', day: 'numeric' } ).formatToParts( date ).filter( ( part ) => 'literal' !== part.type ).map( ( part ) => [ part.type, part.value ] ) );
	return { year: result.year, month: String( Number( result.month ) ), day: result.day };
}

function monthPath( offset ) {
	const day = fixtureDay( offset );
	return `${ ARCHIVE }?month=${ day.month }&yr=${ day.year }`;
}

function skipUnlessSeeded( missing, requiredSet, reason ) {
	if ( ! missing ) {
		return;
	}
	if ( SET === requiredSet ) {
		throw new Error( `active fixture set ${ SET }: ${ reason }` );
	}
	test.skip( true, reason );
}

async function keepRequestsLocal( page ) {
	await page.route( ( url ) => [ 'http:', 'https:' ].includes( url.protocol ) && ! [ 'localhost', '127.0.0.1', '[::1]' ].includes( url.hostname ), ( route ) => route.abort() );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 400 ? 900 : 1100 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function openCalendar( page, offset = 3, width = 1440 ) {
	const response = await open( page, monthPath( offset ), width );
	skipUnlessSeeded( 404 === response?.status(), 'calendar', 'Event archive returned 404' );
	const calendar = page.locator( 'main.cr-archive--event .cr-event-calendar .pkiw-event-calendar, main.cr-archive--event .pkiw-event-calendar.cr-event-calendar' ).first();
	skipUnlessSeeded( 0 === await calendar.count(), 'calendar', 'calendar fixture block is missing' );
	return calendar;
}

async function openSingle( page, key ) {
	const response = await open( page, singlePath( key ) );
	const flyer = page.locator( 'main .cr-event-flyer' ).first();
	skipUnlessSeeded( 404 === response?.status() || 0 === await flyer.count(), 'calendar', `single fixture ${ key } is missing` );
	return flyer;
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

function seconds( value ) {
	return Math.max( ...value.split( ',' ).map( ( part ) => parseFloat( part ) || 0 ) );
}

async function runAxeContrast( page, root ) {
	await page.addScriptTag( { content: axe.source } );
	const result = await page.evaluate( async ( selector ) => window.axe.run( document.querySelector( selector ), { runOnly: { type: 'rule', values: [ 'color-contrast' ] } } ), root );
	expect( result.violations, 'axe color-contrast violations' ).toEqual( [] );
}

test.describe( 'Event calendar archive', () => {
	test.skip( 'calendar' !== SET || 'calendar-off' === STATE, 'calendar fixture set with calendar enabled' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'A-O3 keeps the shared header, one H1 and one named calendar region', async ( { page } ) => {
		const calendar = await openCalendar( page );
		const main = page.locator( 'main.cr-archive--event' );
		await expect( main.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( main.locator( '.cr-archive__header [class*="archive-identity"]' ) ).not.toHaveCount( 0 );
		await expect( main.locator( '.cr-archive__header .wp-block-term-description' ) ).toBeVisible();
		await expect( main.locator( '.cr-archive__header .is-style-cr-marker-bar-short' ) ).toBeVisible();
		await expect( calendar ).toHaveAttribute( 'aria-label', /event|calendar/i );
		await expect( calendar.locator( '.mc-main .heading, .mc-main .mc-calendar-title' ).first() ).toHaveJSProperty( 'tagName', 'H2' );
		await expect( main.locator( '.wp-block-query, .wp-block-query-pagination' ) ).toHaveCount( 0 );
		const order = await main.evaluate( ( node ) => [ node.querySelector( '.cr-event-calendar, .pkiw-event-calendar' ), node.querySelector( '[class*="browse-all"]' ) ].map( ( item ) => item ? [ ...node.children ].findIndex( ( child ) => child === item || child.contains( item ) ) : -1 ) );
		expect( order[0] ).toBeGreaterThanOrEqual( 0 );
		expect( order[1] ).toBeGreaterThan( order[0] );
	} );

	test( 'A-O3 preserves grid, visual and keyboard order at four widths', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			const calendar = await openCalendar( page, 3, width );
			const table = calendar.locator( '.my-calendar-table' );
			await expect( table ).toBeVisible();
			await expect( table.locator( 'thead th' ) ).toHaveCount( 7 );
			const columns = await table.locator( 'thead th' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => Math.round( node.getBoundingClientRect().x ) ) );
			expect( [ ...new Set( columns ) ], `${ width }px seven visual columns` ).toHaveLength( 7 );
			const tabOrder = await calendar.locator( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled])' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => node.getClientRects().length ).map( ( node ) => ( { top: node.getBoundingClientRect().top, left: node.getBoundingClientRect().left } ) ) );
			for ( let index = 1; index < tabOrder.length; index++ ) {
				expect( tabOrder[index].top + 2, `${ width }px tab order does not move upward` ).toBeGreaterThanOrEqual( tabOrder[index - 1].top );
			}
			await assertNoOverflow( page, `${ width }px Event calendar` );
		}
	} );

	test( 'A-O3 has no horizontal scroll at a 400 percent text-size proxy', async ( { page } ) => {
		await openCalendar( page, 3, 320 );
		await page.addStyleTag( { content: 'html { font-size: 400%; }' } );
		await assertNoOverflow( page, '320px at 400% root text' );
	} );

	test( 'A-O4 token paint works in light, system dark and manual dark states', async ( { page } ) => {
		const calendar = await openCalendar( page );
		const frame = calendar.locator( '.mc-main' );
		const event = calendar.locator( '.event-title, [data-controls-modal]' ).first();
		await expect( frame ).toHaveCSS( 'background-color', await resolvedToken( frame, '--cr-surface' ) );
		await expect( frame ).toHaveCSS( 'border-top-color', await resolvedToken( frame, '--cr-russian-violet' ) );
		if ( await event.count() ) {
			await expect( event ).toHaveCSS( 'background-color', await resolvedToken( event, '--cr-periwinkle' ) );
		}
		await runAxeContrast( page, 'main.cr-archive--event' );

		await page.emulateMedia( { colorScheme: 'dark' } );
		await expect( frame ).toHaveCSS( 'background-color', await resolvedToken( frame, '--cr-surface-elevated' ) );
		await expect( frame ).toHaveCSS( 'border-top-color', await resolvedToken( frame, '--cr-printer-ivory' ) );
		await runAxeContrast( page, 'main.cr-archive--event' );

		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		await expect( frame ).toHaveCSS( 'background-color', await resolvedToken( frame, '--cr-surface-elevated' ) );
		await expect( frame ).toHaveCSS( 'border-top-color', await resolvedToken( frame, '--cr-printer-ivory' ) );
		await runAxeContrast( page, 'main.cr-archive--event' );
	} );

	test( 'A-O4 targets, focus, forced colors and reduced motion stay visible', async ( { page } ) => {
		const calendar = await openCalendar( page );
		const targets = calendar.locator( '.my-calendar-navigation a, .my-calendar-navigation button, .mc-format a, .mc-format button, .mc-time a, .mc-time button, .mc-date[href], .event-title, [data-controls-modal], .mc-modal-close' );
		for ( const target of await targets.all() ) {
			if ( ! await target.isVisible() ) continue;
			const targetBox = await box( target );
			expect( targetBox.width ).toBeGreaterThanOrEqual( 44 );
			expect( targetBox.height ).toBeGreaterThanOrEqual( 44 );
		}
		for ( const target of [ calendar.locator( '.my-calendar-navigation a, .my-calendar-navigation button' ).first(), calendar.locator( '.mc-date[href], .event-title, [data-controls-modal]' ).first() ] ) {
			if ( ! await target.count() ) continue;
			await target.focus();
			await expect( target ).toHaveCSS( 'outline-width', '3px' );
			await expect( target ).toHaveCSS( 'outline-color', await resolvedToken( target, '--cr-focus-ring' ) );
			expect( await target.evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).not.toBe( 'none' );
		}

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		const motion = await calendar.locator( '.mc-main' ).evaluate( ( node ) => ( { transition: getComputedStyle( node ).transitionDuration, animation: getComputedStyle( node ).animationDuration } ) );
		expect( seconds( motion.transition ) ).toBeLessThanOrEqual( 0.01 );
		expect( seconds( motion.animation ) ).toBeLessThanOrEqual( 0.01 );

		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
		expect( await page.evaluate( () => matchMedia( '(forced-colors: active)' ).matches ) ).toBe( true );
		await expect( calendar.locator( '.mc-main' ) ).toHaveCSS( 'border-top-width', '2px' );
		await expect( calendar.locator( '.my-calendar-table td' ).first() ).toHaveCSS( 'border-top-width', '2px' );
		const today = calendar.locator( '[aria-current="date"], .current-day' ).first();
		if ( await today.count() ) await expect( today ).toHaveCSS( 'border-top-width', '3px' );
	} );

	test( 'A-O5 hides S01 location and shows the S02 positive control', async ( { page } ) => {
		const s01Calendar = await openCalendar( page, 3 );
		const s01Html = await s01Calendar.evaluate( ( node ) => node.outerHTML );
		expect( s01Html ).toContain( 'Sentinel Hidden Meetup' );
		for ( const sentinel of SENTINELS ) expect( s01Html ).not.toContain( sentinel );

		const s02Calendar = await openCalendar( page, 4 );
		const s02Html = await s02Calendar.evaluate( ( node ) => node.outerHTML );
		expect( s02Html ).toContain( 'Sentinel Switch Meetup' );
		for ( const sentinel of SENTINELS ) expect( s02Html ).toContain( sentinel );
		const legend = s02Calendar.locator( '.mc_bottomnav, .mc-category-key, [class*="category-key"]' );
		await expect( legend ).toContainText( 'General' );
		await expect( legend ).not.toContainText( 'Hide location' );
	} );
} );

test.describe( 'Event singles', () => {
	test.skip( 'calendar' !== SET, 'calendar fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'A-O12 E01 uses authored fallback location and one event URL', async ( { page } ) => {
		const flyer = await openSingle( page, 'E01' );
		await expect( flyer ).toContainText( 'Authored Venue' );
		for ( const sentinel of SENTINELS ) await expect( flyer ).not.toContainText( sentinel );
		await expect( flyer.locator( '.p-location' ) ).toHaveText( 'Authored Venue' );
		await expect( flyer.locator( 'a.u-url' ) ).toHaveCount( 1 );
		await expect( page.locator( '.single-post__lede-meta .single-post__date' ) ).toBeHidden();
		await expect( page.locator( '.h-entry .dt-published' ).first() ).toBeAttached();
	} );

	test( 'A-O12 E02 prints authored values only and E03 prints Cancelled', async ( { page } ) => {
		const e02 = await openSingle( page, 'E02' );
		for ( const value of [ 'Authored Members Event', 'Authored Members Venue', 'Authored members fallback.' ] ) await expect( e02 ).toContainText( value );
		await expect( e02 ).not.toContainText( 'SENTINEL_PRIVATE_CAL' );
		await expect( e02 ).not.toContainText( 'Private Hall' );

		const e03 = await openSingle( page, 'E03' );
		await expect( e03.locator( '.cr-event-flyer__status' ) ).toHaveText( 'Cancelled' );
		await expect( e03.locator( 'a[href*="vcal"], a' ).filter( { hasText: /add to calendar|ics/i } ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'Event Stream tabs', () => {
	test.skip( 'stream' !== SET, 'stream fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'one-link tab keeps public facts, omits location and covers both corners', async ( { page } ) => {
		const response = await open( page, STREAM );
		skipUnlessSeeded( 404 === response?.status(), 'stream', `Stream returned 404 at ${ STREAM }` );
		for ( const key of [ 'E01', 'E03' ] ) {
			const tab = page.locator( `.cr-event-tab:has(a[href*="${ slugOf( key ) }"])` ).first();
			skipUnlessSeeded( 0 === await tab.count(), 'stream', `Stream tab ${ key } is missing` );
			await expect( tab.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( tab.locator( '.cr-event-tab__kind' ) ).toContainText( /event/i );
			await expect( tab.locator( '.p-name' ) ).toHaveCount( 1 );
			await expect( tab.locator( '.dt-start' ) ).toHaveCount( 1 );
			await expect( tab.locator( '.p-location, [class*="location"], img, picture, figure, .p-summary' ) ).toHaveCount( 0 );
			for ( const sentinel of SENTINELS ) await expect( tab ).not.toContainText( sentinel );
			await expect( tab ).toHaveCSS( 'border-top-width', '2px' );
			const tabBox = await box( tab );
			const columnBox = await box( tab.locator( 'xpath=ancestor::li[1]' ) );
			expect( await tab.evaluate( ( node ) => parseFloat( getComputedStyle( node ).width ) ) ).toBeLessThanOrEqual( columnBox.width + 1 );
			expect( Math.abs( tabBox.width - columnBox.width ) ).toBeLessThanOrEqual( 30 );
			const corners = await tab.evaluate( ( node ) => {
				const rect = node.getBoundingClientRect();
				const link = node.querySelector( '.cr-event-tab__name a' );
				return [ [ rect.left + 10, rect.top + 10 ], [ rect.right - 10, rect.bottom - 10 ] ].map( ( [ x, y ] ) => document.elementFromPoint( x, y ) === link );
			} );
			expect( corners, `${ key } whole-tab hit area` ).toEqual( [ true, true ] );
		}
		await expect( page.locator( `.cr-event-tab:has(a[href*="${ slugOf( 'E03' ) }"]) .cr-event-tab__status` ) ).toHaveText( 'Cancelled' );
		await assertNoOverflow( page, 'Event Stream tab' );
	} );

	test( 'tab focus, motion, dark paint and the one rotate token stay bounded', async ( { page } ) => {
		await open( page, STREAM );
		const tab = page.locator( '.cr-event-tab' ).first();
		skipUnlessSeeded( 0 === await tab.count(), 'stream', 'Event Stream tab is missing' );
		const link = tab.locator( 'a' );
		await link.focus();
		await expect( tab ).toHaveCSS( 'outline-width', '3px' );
		expect( await tab.evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).not.toBe( 'none' );

		await page.emulateMedia( { colorScheme: 'dark' } );
		await expect( tab ).toHaveCSS( 'background-color', await resolvedToken( tab, '--cr-periwinkle' ) );
		await expect( tab ).toHaveCSS( 'border-top-color', await resolvedToken( tab, '--cr-printer-ivory' ) );
		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		await expect( tab ).toHaveCSS( 'background-color', await resolvedToken( tab, '--cr-periwinkle' ) );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		expect( seconds( await link.evaluate( ( node ) => getComputedStyle( node ).transitionDuration ) ) ).toBeLessThanOrEqual( 0.01 );
		const sheetText = await page.locator( 'link[href*="cr-event.css"]' ).evaluate( ( node ) => [ ...node.sheet.cssRules ].map( ( rule ) => rule.cssText ).join( '\n' ) );
		expect( sheetText.match( /rotate\(var\(--cr-rotate-neg\)\)/g ) || [] ).toHaveLength( 1 );
	} );
} );

test.describe( 'Event setting states', () => {
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	test( 'A-O13 calendar-off falls back and loads no My Calendar assets', async ( { page } ) => {
		test.skip( 'calendar-off' !== STATE, 'run after flip=calendar:off' );
		await open( page, ARCHIVE );
		await expect( page.locator( '.pkiw-event-calendar, .cr-event-calendar' ) ).toHaveCount( 0 );
		await expect( page.locator( 'main.cr-archive .wp-block-query' ) ).toHaveCount( 1 );
		await expect( page.locator( 'link[href*="my-calendar"], script[src*="my-calendar"], link[href*="cr-event-calendar.css"]' ) ).toHaveCount( 0 );
		for ( const path of [ '/kind/note/', '/kind/rsvp/', singlePath( 'E01' ), '/seed-231-does-not-exist/' ] ) {
			await open( page, path );
			await expect( page.locator( 'link[href*="my-calendar"], script[src*="my-calendar"]' ) ).toHaveCount( 0 );
		}
	} );

	test( 'A-O13 global location switch removes the S02 positive control and restores it off', async ( { page } ) => {
		test.skip( ! [ 'locations-on', 'locations-off' ].includes( STATE ), 'run after the matching location flip' );
		const response = await open( page, monthPath( 4 ) );
		skipUnlessSeeded( 404 === response?.status(), 'calendar', 'S02 month is missing' );
		const html = await page.locator( '.pkiw-event-calendar' ).evaluate( ( node ) => node.outerHTML );
		expect( html ).toContain( 'Sentinel Switch Meetup' );
		for ( const sentinel of SENTINELS ) {
			if ( 'locations-on' === STATE ) expect( html ).not.toContain( sentinel );
			else expect( html ).toContain( sentinel );
		}
	} );

	test( 'password-protected title keeps the core prefix', async ( { page } ) => {
		test.skip( 'E01-password' !== STATE, 'run after flip=E01:password' );
		await open( page, singlePath( 'E01' ) );
		await expect( page.locator( 'main h1' ) ).toHaveText( /^Protected: / );
		await expect( page.locator( '.cr-event-flyer' ) ).toHaveCount( 0 );
	} );
} );
