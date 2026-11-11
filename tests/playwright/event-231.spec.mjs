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
		// Append beside the node: a <select> doesn't render children, so a child probe resolves to "".
		( node.parentElement || node ).append( probe );
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

async function contrastRatio( foreground, background ) {
	const parse = ( value ) => ( value.match( /[\d.]+/g ) || [] ).slice( 0, 3 ).map( Number );
	const luminance = ( value ) => {
		const channels = parse( value ).map( ( channel ) => {
			const normalized = channel / 255;
			return normalized <= 0.04045 ? normalized / 12.92 : ( ( normalized + 0.055 ) / 1.055 ) ** 2.4;
		} );
		return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
	};
	const values = [ luminance( foreground ), luminance( background ) ].sort( ( a, b ) => b - a );
	return ( values[0] + 0.05 ) / ( values[1] + 0.05 );
}

async function assertSentinels( locator, present, label ) {
	const html = await locator.evaluate( ( node ) => node.outerHTML );
	for ( const sentinel of SENTINELS ) {
		expect( html.includes( sentinel ), `${ label }: ${ sentinel }` ).toBe( present );
	}
}

function eventEntry( root, title ) {
	return root.locator( 'article.mc-event' ).filter( { hasText: title } ).first();
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
		await expect( calendar.locator( '.mc-main .heading' ).first() ).toHaveJSProperty( 'tagName', 'H2' );
		await expect( page.locator( 'link[href*="my-calendar"], script[src*="my-calendar"]' ) ).not.toHaveCount( 0 );
		await expect( main.locator( '.wp-block-query, .wp-block-query-pagination' ) ).toHaveCount( 0 );
		const order = await main.evaluate( ( node ) => [ node.querySelector( '.cr-event-calendar, .pkiw-event-calendar' ), node.querySelector( '[class*="browse-all"]' ) ].map( ( item ) => item ? [ ...node.children ].findIndex( ( child ) => child === item || child.contains( item ) ) : -1 ) );
		expect( order[0] ).toBeGreaterThanOrEqual( 0 );
		expect( order[1] ).toBeGreaterThan( order[0] );
	} );

	test( 'A-O3 preserves grid, visual and keyboard order at four widths', async ( { page } ) => {
		for ( const width of WIDTHS.slice( 0, 3 ) ) {
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

		// Under a 344px content box (a 374px viewport) nine-letter event words no longer fit seven columns, so 360px stacks like 320px.
		for ( const stacked of [ 320, 360 ] ) {
			const calendar = await openCalendar( page, 3, stacked );
			const table = calendar.locator( '.my-calendar-table' );
			await expect( table.locator( 'thead th' ).first() ).toBeHidden();
			const visibleCells = table.locator( 'td:visible' );
			expect( await visibleCells.count(), `${ stacked }px stacked layout has event-day cells` ).toBeGreaterThan( 0 );
			await expect( visibleCells.first() ).toHaveCSS( 'display', 'block' );
			const tabOrder = await calendar.locator( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled])' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => node.getClientRects().length ).map( ( node ) => node.getBoundingClientRect().top ) );
			expect( tabOrder.length, `${ stacked }px has keyboard targets` ).toBeGreaterThan( 0 );
			for ( let index = 1; index < tabOrder.length; index++ ) expect( tabOrder[index] + 2, `${ stacked }px tab order does not move upward` ).toBeGreaterThanOrEqual( tabOrder[index - 1] );
			const targets = calendar.locator( 'a[href]:visible, button:not([disabled]):visible, input:not([disabled]):visible, select:not([disabled]):visible' );
			for ( const target of await targets.all() ) {
				const targetBox = await box( target );
				expect( targetBox.width, `${ stacked }px target width` ).toBeGreaterThanOrEqual( 44 );
				expect( targetBox.height, `${ stacked }px target height` ).toBeGreaterThanOrEqual( 44 );
		}
		await assertNoOverflow( page, `${ stacked }px stacked Event calendar` );
		}
	} );

	test( 'A-O3 keeps event words whole in the 375px and 390px grid', async ( { page } ) => {
		// The seeded events run from +3 to +8 days, so near a month's end they span two months.
		const offsets = [ 3, 6, 8 ].filter( ( offset, index, all ) => all.findIndex( ( other ) => monthPath( other ) === monthPath( offset ) ) === index );
		for ( const width of [ 375, 390 ] ) {
			const seen = new Set();
			for ( const offset of offsets ) {
				const calendar = await openCalendar( page, offset, width );
				await page.evaluate( () => document.fonts.ready );
				const table = calendar.locator( '.my-calendar-table' );
				await expect( table.locator( 'thead th:visible' ), `${ width }px seven columns` ).toHaveCount( 7 );
				const report = await table.locator( '.event-title button' ).evaluateAll( ( buttons ) => {
					const result = { words: [], split: [], clipped: [], small: [], hyphens: [] };
					for ( const button of buttons ) {
						const rect = button.getBoundingClientRect();
						const label = button.textContent.trim();
						if ( button.scrollWidth > button.clientWidth || rect.right > button.closest( 'td' ).getBoundingClientRect().right + 0.5 ) result.clipped.push( label );
						if ( rect.width < 44 || rect.height < 44 ) result.small.push( label );
						result.hyphens.push( ...[ button, ...button.querySelectorAll( 'div' ) ].map( ( node ) => getComputedStyle( node ).hyphens ) );
						const walker = document.createTreeWalker( button, NodeFilter.SHOW_TEXT );
						for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
							for ( const match of node.data.matchAll( /\S+/g ) ) {
								const range = document.createRange();
								range.setStart( node, match.index );
								range.setEnd( node, match.index + match[0].length );
								result.words.push( match[0] );
								// A word that wraps mid-word paints one client rect per line.
								if ( [ ...range.getClientRects() ].filter( ( line ) => line.width > 0 ).length > 1 ) result.split.push( match[0] );
							}
						}
					}
					return result;
				} );
				report.words.forEach( ( word ) => seen.add( word.replace( /:$/, '' ) ) );
				expect( report.split, `${ width }px words split across lines` ).toEqual( [] );
				expect( report.clipped, `${ width }px event names past their button or cell` ).toEqual( [] );
				expect( report.small, `${ width }px event buttons under 44px` ).toEqual( [] );
				expect( [ ...new Set( report.hyphens ) ], `${ width }px automatic hyphenation is off` ).toEqual( [ 'manual' ] );
				await assertNoOverflow( page, `${ width }px Event calendar words` );
			}
			for ( const word of [ 'Cancelled', 'Recurring', 'Sentinel', 'Meetup' ] ) {
				expect( [ ...seen ], `${ width }px fixture word was measured` ).toContain( word );
			}
		}
	} );

	test( 'A-O3 has no horizontal scroll at a 400 percent text-size proxy', async ( { page } ) => {
		await openCalendar( page, 3, 320 );
		await page.addStyleTag( { content: 'html { font-size: 400%; }' } );
		await assertNoOverflow( page, '320px at 400% root text' );
	} );

	test( 'A-O4 token paint works in light, system dark and manual dark states', async ( { page } ) => {
		// The dark projects start in dark; the light expectations below need an explicit light scheme.
		await page.emulateMedia( { colorScheme: 'light' } );
		const calendar = await openCalendar( page );
		const frame = calendar.locator( '.mc-main' );
		const event = calendar.locator( '.event-title button' ).first();
		await expect( frame ).toHaveCSS( 'background-color', await resolvedToken( frame, '--cr-surface' ) );
		await expect( frame ).toHaveCSS( 'border-top-color', await resolvedToken( frame, '--cr-russian-violet' ) );
		await expect( event ).toHaveCount( 1 );
		// The approved comps split the entry paper by mode: printer ivory in light, periwinkle in dark.
		await expect( event ).toHaveCSS( 'background-color', await resolvedToken( event, '--cr-printer-ivory' ) );
		await runAxeContrast( page, 'main.cr-archive--event' );

		await page.emulateMedia( { colorScheme: 'dark' } );
		await expect( frame ).toHaveCSS( 'background-color', await resolvedToken( frame, '--cr-surface-elevated' ) );
		await expect( frame ).toHaveCSS( 'border-top-color', await resolvedToken( frame, '--cr-printer-ivory' ) );
		await expect( event ).toHaveCSS( 'background-color', await resolvedToken( event, '--cr-periwinkle' ) );
		await runAxeContrast( page, 'main.cr-archive--event' );

		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		await expect( frame ).toHaveCSS( 'background-color', await resolvedToken( frame, '--cr-surface-elevated' ) );
		await expect( frame ).toHaveCSS( 'border-top-color', await resolvedToken( frame, '--cr-printer-ivory' ) );
		await expect( event ).toHaveCSS( 'background-color', await resolvedToken( event, '--cr-periwinkle' ) );
		await runAxeContrast( page, 'main.cr-archive--event' );
	} );

	test( 'A-O4 targets, focus, forced colors and reduced motion stay visible', async ( { page } ) => {
		const calendar = await openCalendar( page );
		const targets = calendar.locator( '.my-calendar-navigation a:visible, .my-calendar-navigation button:visible, .my-calendar-navigation input:visible, .my-calendar-navigation select:visible, .mc-format button:visible, .mc-time button:visible, .event-title button:visible, .mc-list .event-date button:visible' );
		expect( await targets.count(), 'calendar target selector matches controls' ).toBeGreaterThan( 0 );
		for ( const target of await targets.all() ) {
			const targetBox = await box( target );
			expect( targetBox.width ).toBeGreaterThanOrEqual( 44 );
			expect( targetBox.height ).toBeGreaterThanOrEqual( 44 );
		}
		// Every calendar control family: view toggle, month select, date-switcher submit, event button.
		const focusTargets = [ calendar.locator( '.my-calendar-navigation a, .my-calendar-navigation button' ).first(), calendar.locator( '.my-calendar-navigation select' ).first(), calendar.locator( '.my-calendar-navigation input[type="submit"]' ).first(), calendar.locator( '.event-title button' ).first() ];
		const assertFocusRing = async ( label ) => {
			for ( const target of focusTargets ) {
				await expect( target, `${ label } focus target exists` ).toHaveCount( 1 );
				await target.focus();
				const ring = await resolvedToken( target, '--cr-focus-ring' );
				const halo = await resolvedToken( target, '--cr-focus-halo' );
				await expect( target, `${ label } ring style` ).toHaveCSS( 'outline-style', 'solid' );
				await expect( target, `${ label } ring width` ).toHaveCSS( 'outline-width', '3px' );
				await expect( target, `${ label } ring is the site token` ).toHaveCSS( 'outline-color', ring );
				await expect.poll( () => target.evaluate( ( node ) => getComputedStyle( node ).boxShadow ), `${ label } halo is part of the box-shadow list` ).toContain( halo );
				// WCAG 2.4.11 / 1.4.11: the ring meets 3:1 against the halo bands on both sides of it.
				expect( await contrastRatio( ring, halo ), `${ label } ring vs halo contrast` ).toBeGreaterThanOrEqual( 3 );
			}
		};
		await page.emulateMedia( { colorScheme: 'light' } );
		await assertFocusRing( 'light' );
		await page.emulateMedia( { colorScheme: 'dark' } );
		await assertFocusRing( 'system dark' );
		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		await assertFocusRing( 'manual dark' );
		await page.evaluate( () => delete document.documentElement.dataset.theme );
		await calendar.locator( '.event-title button' ).first().click();
		const modal = page.locator( 'dialog.my-calendar-modal' );
		const close = modal.locator( 'button.my-calendar-modal-close' );
		await expect( modal ).toBeVisible();
		const closeBox = await box( close );
		expect( closeBox.width ).toBeGreaterThanOrEqual( 44 );
		expect( closeBox.height ).toBeGreaterThanOrEqual( 44 );
		// The button transitions colors after a scheme switch, so poll until the settled values meet the ratios.
		const assertCloseContrast = async ( label ) => {
			const read = () => close.evaluate( ( node ) => ( { glyph: getComputedStyle( node ).color, fill: getComputedStyle( node ).backgroundColor, boundary: getComputedStyle( node ).borderTopColor, modal: getComputedStyle( node.closest( 'dialog' ) ).backgroundColor } ) );
			await expect.poll( async () => { const c = await read(); return contrastRatio( c.glyph, c.fill ); }, { message: `${ label } close glyph contrast`, timeout: 4000 } ).toBeGreaterThanOrEqual( 4.5 );
			await expect.poll( async () => { const c = await read(); return contrastRatio( c.boundary, c.modal ); }, { message: `${ label } close boundary contrast`, timeout: 4000 } ).toBeGreaterThanOrEqual( 3 );
		};
		await assertCloseContrast( 'light' );
		await page.emulateMedia( { colorScheme: 'dark' } );
		await assertCloseContrast( 'system dark' );
		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		await assertCloseContrast( 'manual dark' );
		await page.evaluate( () => delete document.documentElement.dataset.theme );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		const motion = await calendar.locator( '.mc-main' ).evaluate( ( node ) => ( { transition: getComputedStyle( node ).transitionDuration, animation: getComputedStyle( node ).animationDuration } ) );
		expect( seconds( motion.transition ) ).toBeLessThanOrEqual( 0.01 );
		expect( seconds( motion.animation ) ).toBeLessThanOrEqual( 0.01 );

		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
		expect( await page.evaluate( () => matchMedia( '(forced-colors: active)' ).matches ) ).toBe( true );
		await expect( calendar.locator( '.mc-main' ) ).toHaveCSS( 'border-top-width', '2px' );
		await expect( calendar.locator( '.my-calendar-table td' ).first() ).toHaveCSS( 'border-top-width', '2px' );
		await open( page, ARCHIVE );
		const today = page.locator( '.cr-event-calendar .my-calendar-table td[aria-current="date"], .cr-event-calendar .my-calendar-table td.current-day' ).first();
		await expect( today ).toHaveCount( 1 );
		await expect( today ).toHaveCSS( 'border-top-width', '3px' );
	} );

	test( 'A-O5 hides S01 location and shows the S02 positive control', async ( { page } ) => {
		const calendar = await openCalendar( page, 3 );
		const s01 = eventEntry( calendar, 'Sentinel Hidden Meetup' );
		const s02 = eventEntry( calendar, 'Sentinel Switch Meetup' );
		await expect( s01 ).toHaveCount( 1 );
		await expect( s02 ).toHaveCount( 1 );
		await assertSentinels( s01, false, 'S01 grid entry' );
		await assertSentinels( s02, true, 'S02 grid entry' );
		for ( const [ title, present ] of [ [ 'Sentinel Hidden Meetup', false ], [ 'Sentinel Switch Meetup', true ] ] ) {
			await eventEntry( calendar, title ).locator( '.event-title button' ).click();
			const modal = page.locator( 'dialog.my-calendar-modal' );
			await expect( modal ).toBeVisible();
			await expect( modal ).toContainText( title );
			await assertSentinels( modal, present, `${ title } modal` );
			await modal.locator( 'button.my-calendar-modal-close' ).click();
		}
		await open( page, `${ monthPath( 3 ) }&format=list` );
		const list = page.locator( '.cr-event-calendar .mc-list' );
		await assertSentinels( eventEntry( list, 'Sentinel Hidden Meetup' ), false, 'S01 list entry' );
		await assertSentinels( eventEntry( list, 'Sentinel Switch Meetup' ), true, 'S02 list entry' );
		const legend = page.locator( '.cr-event-calendar .mc_bottomnav .category-key' );
		await expect( legend ).toHaveCount( 1 );
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

	test( 'A-O12 E04 keeps its wall clock and stays unlinked from O01', async ( { page } ) => {
		const e04 = await openSingle( page, 'E04' );
		// The site prints "6:00 pm"; the value sits in its own <dd>, so the label is a separate dt, not glued text.
		await expect( e04.locator( '.cr-event-flyer__time dt' ) ).toHaveText( 'Time' );
		await expect( e04.locator( '.cr-event-flyer__time dd' ) ).toHaveText( /^6:00\s*pm\b/i );
		await expect( e04.locator( '.dt-start' ) ).toHaveAttribute( 'datetime', /T18:00:00-0[45]:00$/ );
		await expect( e04 ).not.toContainText( 'Recurring Example Talk' );
	} );
} );

test.describe( 'Event Stream tabs', () => {
	test.skip( 'stream' !== SET, 'stream fixture set' );
	test.beforeEach( async ( { page } ) => keepRequestsLocal( page ) );

	// The tab paints no paper itself: `.cr-event-tab__event::before` is the ink edge and `::after` the paper, clipped to the torn top edge.
	const layerStyle = ( tab, pseudo, property ) => tab.evaluate( ( node, [ which, name ] ) => getComputedStyle( node.querySelector( '.cr-event-tab__event' ), which )[ name ], [ pseudo, property ] );
	// Matches one layer of a computed box-shadow: the resolved color, then its offsets, blur and spread.
	const shadowLayer = ( color, geometry ) => new RegExp( `${ color.replace( /[()]/g, '\\$&' ) } ${ geometry }` );

	test( 'one-link tab keeps public facts, omits location and covers both corners', async ( { page } ) => {
		const response = await open( page, STREAM );
		skipUnlessSeeded( 404 === response?.status(), 'stream', `Stream returned 404 at ${ STREAM }` );
		for ( const key of [ 'E01', 'E03' ] ) {
			const tab = page.locator( `.cr-event-tab:has(a[href*="${ slugOf( key ) }"])` ).first();
			skipUnlessSeeded( 0 === await tab.count(), 'stream', `Stream tab ${ key } is missing` );
			await expect( tab.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( tab.locator( '.cr-event-tab__kind' ) ).toContainText( /event/i );
			// The plugin's hidden p-author card also carries a .p-name, so count the event name by its own class.
			await expect( tab.locator( '.cr-event-tab__name.p-name' ) ).toHaveCount( 1 );
			await expect( tab.locator( '.dt-start' ) ).toHaveCount( 1 );
			// One visible date: the event's; the publication date stays hidden in the DOM.
			await expect( tab.locator( 'time:visible' ) ).toHaveCount( 1 );
			await expect( tab.locator( 'time.dt-published' ) ).toHaveCount( 1 );
			await expect( tab.locator( '.p-location, [class*="location"], img, picture, figure, .p-summary' ) ).toHaveCount( 0 );
			for ( const sentinel of SENTINELS ) await expect( tab ).not.toContainText( sentinel );
			await expect( tab ).toHaveCSS( 'border-top-width', '2px' );
			// The torn top edge is one polygon on both layers, and the tab's own top border paints nothing.
			await expect( tab ).toHaveCSS( 'border-top-color', 'rgba(0, 0, 0, 0)' );
			for ( const pseudo of [ '::before', '::after' ] ) expect( await layerStyle( tab, pseudo, 'clipPath' ), `${ key } torn edge ${ pseudo }` ).toMatch( /^polygon\(0px 0px, 7% / );
			const tabBox = await box( tab );
			const columnBox = await box( tab.locator( 'xpath=ancestor::li[1]' ) );
			expect( await tab.evaluate( ( node ) => parseFloat( getComputedStyle( node ).width ) ) ).toBeLessThanOrEqual( columnBox.width + 1 );
			expect( Math.abs( tabBox.width - columnBox.width ) ).toBeLessThanOrEqual( 30 );
			const corners = await tab.evaluate( ( node ) => {
				const rect = node.getBoundingClientRect();
				const link = node.querySelector( '.cr-event-tab__name a' );
					return [ [ rect.left + 24, rect.top + 24 ], [ rect.right - 24, rect.bottom - 24 ] ].map( ( [ x, y ] ) => document.elementFromPoint( x, y ) === link );
			} );
			expect( corners, `${ key } whole-tab hit area` ).toEqual( [ true, true ] );
		}
		const status = page.locator( `.cr-event-tab:has(a[href*="${ slugOf( 'E03' ) }"]) .cr-event-tab__status` );
		await expect( status ).toHaveText( 'Cancelled' );
		// The Cancelled label is the notched status sticker: selective-yellow fill, Russian-violet ink, a notch on each side.
		await expect( status ).toHaveCSS( 'background-color', await resolvedToken( status, '--cr-selective-yellow' ) );
		await expect( status ).toHaveCSS( 'color', await resolvedToken( status, '--cr-russian-violet' ) );
		await expect( status ).toHaveCSS( 'clip-path', /94% 50%.*6% 50%/ );
		await assertNoOverflow( page, 'Event Stream tab' );
	} );

	test( 'tab focus, motion, dark paint and the one rotate token stay bounded', async ( { page } ) => {
		await open( page, STREAM );
		const tab = page.locator( '.cr-event-tab' ).first();
		skipUnlessSeeded( 0 === await tab.count(), 'stream', 'Event Stream tab is missing' );
		const link = tab.locator( 'a' );
		await link.focus();
		await expect( tab ).toHaveCSS( 'outline-width', '3px' );
		await expect( tab ).toHaveCSS( 'outline-color', await resolvedToken( tab, '--cr-focus-ring' ) );
		// The light hard shadow is ink too, so the halo is told apart by its 8px spread.
		await expect.poll( () => tab.evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).toMatch( shadowLayer( await resolvedToken( tab, '--cr-russian-violet' ), '0px 0px 0px 8px' ) );

		// Light paint: printer-ivory paper, a Russian-violet edge and the ink --cr-shadow-hard.
		await page.emulateMedia( { colorScheme: 'light' } );
		const violet = await resolvedToken( tab, '--cr-russian-violet' );
		const ivory = await resolvedToken( tab, '--cr-printer-ivory' );
		await expect.poll( () => layerStyle( tab, '::after', 'backgroundColor' ) ).toBe( ivory );
		await expect.poll( () => layerStyle( tab, '::before', 'backgroundColor' ) ).toBe( violet );
		await expect( tab ).toHaveCSS( 'border-bottom-color', violet );
		await expect( tab ).toHaveCSS( 'box-shadow', shadowLayer( violet, '4px 4px 0px 0px' ) );

		// Dark paint: periwinkle paper, an ivory edge and the selective-yellow --cr-shadow-hard.
		await page.emulateMedia( { colorScheme: 'dark' } );
		const periwinkle = await resolvedToken( tab, '--cr-periwinkle' );
		await expect.poll( () => layerStyle( tab, '::after', 'backgroundColor' ) ).toBe( periwinkle );
		await expect.poll( () => layerStyle( tab, '::before', 'backgroundColor' ) ).toBe( ivory );
		await expect( tab ).toHaveCSS( 'border-bottom-color', ivory );
		await expect( tab ).toHaveCSS( 'box-shadow', shadowLayer( await resolvedToken( tab, '--cr-selective-yellow' ), '4px 4px 0px 0px' ) );
		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		await expect.poll( () => layerStyle( tab, '::after', 'backgroundColor' ) ).toBe( periwinkle );

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
		const html = await page.content();
		expect( html ).toContain( 'Sentinel Hidden Meetup' );
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
