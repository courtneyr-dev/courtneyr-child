// `CR_SPEC=acquisition-239.spec.mjs`: PKIW issue 239 acquisition archive, single and Stream
// against the approved mockups and the decision's corrections, on the local `_cr_seed_239`
// fixtures (seed-239.php in the vault: assets/239-acquisitions/). Read-only GETs; needs a
// running site. It sets its own viewports, colour scheme, forced colors and reduced motion,
// so run it under one project:
//   CR_SPEC=acquisition-239.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light
// Paths can be pointed elsewhere with CR_ACQ_ARCHIVE_PATH and CR_ACQ_STREAM_PATH. One test per
// acceptance line of plan-239 (A0 to A58); lines that need a PHP parse (mf2 with mf2/mf2), a Site
// Editor session, WP-CLI or Pantheon dev say so in their name and live in the orchestrator's run.
import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const ARCHIVE = process.env.CR_ACQ_ARCHIVE_PATH || '/kind/acquisition/';
const STREAM = process.env.CR_ACQ_STREAM_PATH || '/stream/';
const WIDTHS = [ 1440, 1024, 800, 375, 320 ];
const BAG_COLUMNS = { 1440: 3, 1024: 3, 800: 2, 375: 1, 320: 1 };

const tokens = readFileSync( new URL( '../../assets/css/tokens.css', import.meta.url ), 'utf8' );

/** rgb() strings for every hex colour in tokens.css, so computed colours can be checked against the palette. */
function paletteAllowlist() {
	const allowed = new Set( [ 'rgba(0, 0, 0, 0)', 'transparent' ] );
	for ( const [ , hex ] of tokens.matchAll( /#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/g ) ) {
		const full = hex.length === 3 ? [ ...hex ].map( ( c ) => c + c ).join( '' ) : hex;
		const [ r, g, b ] = [ 0, 2, 4 ].map( ( at ) => parseInt( full.slice( at, at + 2 ), 16 ) );
		allowed.add( `rgb(${ r }, ${ g }, ${ b })` );
	}
	return allowed;
}
const PALETTE = paletteAllowlist();

const RED = { rgb: 'rgb(255, 183, 3)' }; // --cr-selective-yellow, the focus ring

function luminance( rgb ) {
	const [ r, g, b ] = rgb.match( /\d+(\.\d+)?/g ).slice( 0, 3 ).map( ( v ) => {
		const c = Number( v ) / 255;
		return c <= 0.03928 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast( a, b ) {
	const [ hi, lo ] = [ luminance( a ), luminance( b ) ].sort( ( x, y ) => y - x );
	return ( hi + 0.05 ) / ( lo + 0.05 );
}

async function open( page, path, { width = 1440, scheme = 'light', forced = 'none', motion = 'no-preference' } = {} ) {
	await page.setViewportSize( { width, height: 900 } );
	await page.emulateMedia( { colorScheme: scheme, forcedColors: forced, reducedMotion: motion } );
	const response = await page.goto( path, { waitUntil: 'load' } );
	test.skip( response.status() === 404, `nothing at ${ path }` );
	return response;
}

async function openArchive( page, number = 1, options = {} ) {
	return open( page, number === 1 ? ARCHIVE : `${ ARCHIVE }page/${ number }/`, options );
}

/** Scroll the page through so lazy images load or fail. */
async function settle( page ) {
	await page.evaluate( async () => {
		for ( let y = 0; y < document.body.scrollHeight; y += 600 ) {
			window.scrollTo( 0, y );
			await new Promise( ( done ) => setTimeout( done, 60 ) );
		}
		window.scrollTo( 0, 0 );
	} );
	await page.waitForLoadState( 'networkidle' );
}

/** The permalink of an archive item, found by its title across the archive's pages. */
async function singlePath( page, title ) {
	for ( const number of [ 1, 2, 3 ] ) {
		const response = await page.goto( number === 1 ? ARCHIVE : `${ ARCHIVE }page/${ number }/`, { waitUntil: 'domcontentloaded' } );
		if ( response.status() === 404 ) {
			break;
		}
		const href = await page.locator( '.cr-receipt__title a' ).evaluateAll( ( links, wanted ) => ( links.find( ( a ) => a.textContent.trim() === wanted ) || {} ).href || '', title );
		if ( href ) {
			return new URL( href ).pathname;
		}
	}
	test.skip( true, `no archive item titled ${ title }` );
	return '';
}

async function openSingle( page, title, options = {} ) {
	const path = await singlePath( page, title );
	await open( page, path, options );
}

/** The first opaque background above main, the page ground. */
function groundColor( page ) {
	return page.evaluate( () => {
		for ( let el = document.querySelector( 'main' ); el; el = el.parentElement ) {
			const color = getComputedStyle( el ).backgroundColor;
			if ( ! /rgba\(\d+, \d+, \d+, 0\)|transparent/.test( color ) ) {
				return color;
			}
		}
		return 'rgb(255, 255, 255)';
	} );
}

/** Anchors a visitor can see and tab to inside a scope. */
function visibleLinks( scope ) {
	return scope.locator( 'a[href]' ).evaluateAll( ( links ) => links.filter( ( a ) => ! a.closest( '[hidden]' ) && a.tabIndex !== -1 && a.getClientRects().length > 0 ).length );
}

async function hiddenLinksStayHidden( scope ) {
	return scope.locator( 'a[tabindex="-1"]' ).evaluateAll( ( links ) => links.every( ( a ) => a.closest( '[hidden]' ) ) );
}

async function computed( locator, props ) {
	return locator.evaluate( ( el, wanted ) => {
		const style = getComputedStyle( el );
		return Object.fromEntries( wanted.map( ( name ) => [ name, style.getPropertyValue( name ) ] ) );
	}, props );
}

// ---------------------------------------------------------------------------
// Archive
// ---------------------------------------------------------------------------
test.describe( 'archive (PKIW 239)', () => {
	test( 'A0 the theme template serves one inherited query with core pagination: 12, 12 and 2 receipts', async ( { page } ) => {
		const counts = [];
		for ( const number of [ 1, 2, 3 ] ) {
			await openArchive( page, number );
			counts.push( await page.locator( 'ul.pkiw-group__items > li' ).count() );
		}
		expect( counts ).toEqual( [ 12, 12, 2 ] );
		await openArchive( page, 1 );
		expect( await page.locator( 'main .wp-block-query' ).count(), 'one Query Loop' ).toBe( 1 );
		expect( await page.locator( 'nav[aria-label="Pagination"]' ).count() ).toBe( 1 );
	} );

	test( 'A1 the header is the shipped archive identity: blue burst, H1 "Acquisition", 11 mixed-case tiles', async ( { page } ) => {
		await openArchive( page );
		const h1 = page.locator( 'main h1.cr-archive__title' );
		await expect( h1 ).toHaveClass( /cr-flank--blue/ );
		expect( ( await h1.getAttribute( 'aria-label' ) ) || ( await h1.locator( '.cr-cutout__text' ).textContent() ).trim() ).toBe( 'Acquisition' );
		const tiles = h1.locator( '.cr-cutout__tile' );
		expect( await tiles.allTextContents() ).toEqual( [ ...'Acquisition' ] );
		expect( await tiles.evaluateAll( ( els ) => els.every( ( el ) => el.closest( '[aria-hidden="true"]' ) !== null && getComputedStyle( el ).textTransform === 'none' ) ) ).toBe( true );
		const rotations = await tiles.evaluateAll( ( els ) => els.map( ( el ) => [ ...el.classList ].find( ( c ) => /^is-r/.test( c ) ) || '' ) );
		for ( let i = 1; i < rotations.length; i++ ) {
			expect( rotations[ i ] === rotations[ i - 1 ] && rotations[ i ] !== '', `tiles ${ i - 1 } and ${ i } differ` ).toBe( false );
		}
		expect( await page.locator( '.cr-archive__description' ).count() ).toBe( 1 );
		expect( await page.locator( 'main .cr-archive__header' ).evaluate( ( el ) => el.textContent.includes( 'Stream ·' ) ) ).toBe( false );
	} );

	test( 'A2 month bags: real upright H2 labels from the post date, July repeated on page 2 with no "continued" copy', async ( { page } ) => {
		await openArchive( page, 1 );
		const first = await page.locator( 'h2.pkiw-group__heading' ).allTextContents();
		const seedMonth = new Intl.DateTimeFormat( 'en-US', { month: 'long', year: 'numeric', timeZone: 'America/New_York' } ).format( new Date() );
		test.skip( seedMonth === 'August 2026', 'the seeding month collides with the August fixtures' );
		expect( first.map( ( t ) => t.trim() ) ).toEqual( [ seedMonth, 'August 2026', 'July 2026' ] );
		await openArchive( page, 2 );
		const second = ( await page.locator( 'h2.pkiw-group__heading' ).allTextContents() ).map( ( t ) => t.trim() );
		expect( second ).toEqual( [ 'July 2026', 'June 2026', 'May 2026', 'April 2026' ] );
		expect( second.join( ' ' ) ).not.toMatch( /continued/i );
		expect( await page.locator( 'h2.pkiw-group__heading' ).evaluateAll( ( els ) => els.every( ( el ) => getComputedStyle( el ).transform === 'none' ) ) ).toBe( true );
	} );

	test( 'A3 each item has exactly one visible, focusable link and no card details in its markup', async ( { page } ) => {
		await openArchive( page );
		const items = page.locator( 'ul.pkiw-group__items > li' );
		const count = await items.count();
		for ( let i = 0; i < count; i++ ) {
			const item = items.nth( i );
			expect( await visibleLinks( item ), `item ${ i } visible links` ).toBe( 1 );
			expect( await hiddenLinksStayHidden( item ), `item ${ i } hidden links stay hidden` ).toBe( true );
			const html = await item.evaluate( ( el ) => el.outerHTML );
			for ( const leak of [ 'Purchase', 'Riverside Audio', 'Corner Hardware', 'Family attic', 'Office swap table', '$', 'Records the morning birds', 'August 1, 2026' ] ) {
				expect( html, `item ${ i } leaks ${ leak }` ).not.toContain( leak );
			}
			expect( await item.locator( 'data.u-url, data.dt-published' ).count(), `item ${ i } hidden props` ).toBeGreaterThanOrEqual( 2 );
			expect( await item.locator( 'span.p-author.h-card' ).count(), `item ${ i } author` ).toBe( 1 );
		}
	} );

	test( 'A4 duplicate titles are told apart by the post date, joined with one space', async ( { page } ) => {
		await openArchive( page, 2 );
		const names = await page.locator( '.cr-receipt__title a' ).evaluateAll( ( links ) => links.filter( ( a ) => a.textContent.trim() === 'Field Notebook' ).map( ( a ) => ( {
			label: a.getAttribute( 'aria-labelledby' ).split( ' ' ).map( ( id ) => document.getElementById( id ).textContent.trim() ).join( ' ' ),
			time: a.closest( '.cr-receipt' ).querySelector( 'time' ),
		} ) ).map( ( { label, time } ) => ( { label, mf2: time ? time.className : null, text: time ? time.textContent.trim() : '' } ) ) );
		expect( names.map( ( n ) => n.label ).sort() ).toEqual( [ 'Field Notebook May 20, 2026', 'Field Notebook May 28, 2026' ] );
		for ( const name of names ) {
			expect( name.text.length ).toBeGreaterThan( 0 );
			expect( name.mf2 ).not.toMatch( /dt-|u-|p-|h-/ );
		}
	} );

	test( 'A5 photos: empty alt, sizes, eager first row, lazy rest, failed file swaps to text-only, remote photo absent', async ( { page } ) => {
		const requested = [];
		page.on( 'request', ( request ) => requested.push( request.url() ) );
		await openArchive( page, 1 );
		const imgs = await page.locator( '.cr-receipt__photo img' ).evaluateAll( ( els ) => els.map( ( el ) => ( { alt: el.getAttribute( 'alt' ), w: el.getAttribute( 'width' ), h: el.getAttribute( 'height' ), loading: el.getAttribute( 'loading' ), prio: el.getAttribute( 'fetchpriority' ), skip: el.hasAttribute( 'data-skip-lazy' ), filter: getComputedStyle( el ).filter } ) ) );
		expect( imgs.length ).toBeGreaterThan( 0 );
		for ( const img of imgs ) {
			expect( img.alt ).toBe( '' );
			expect( img.filter ).toBe( 'none' );
		}
		const eager = imgs.filter( ( img ) => img.loading === 'eager' );
		expect( eager.length, 'a first row loads eagerly' ).toBeGreaterThan( 0 );
		expect( eager.length, 'at most 2 receipts in each of 3 sections' ).toBeLessThanOrEqual( 6 );
		expect( eager.every( ( img ) => img.prio === 'high' && img.skip ) ).toBe( true );
		expect( imgs.filter( ( img ) => img.loading !== 'eager' ).every( ( img ) => img.loading === 'lazy' && ! img.prio ) ).toBe( true );
		await openArchive( page, 2 );
		await settle( page );
		const tea = page.locator( '.cr-receipt', { has: page.getByRole( 'link', { name: 'Tea Tin' } ) } );
		const teaFigure = tea.locator( '.cr-receipt__photo' );
		const swapped = ( await teaFigure.count() ) === 0 || await teaFigure.evaluate( ( el ) => el.classList.contains( 'cr-cover--failed' ) );
		expect( swapped, 'a missing file ends as a text-only receipt' ).toBe( true );
		expect( await page.locator( '.cr-receipt', { has: page.getByRole( 'link', { name: 'Paper Lantern' } ) } ).locator( 'img, figure' ).count(), 'remote photo prints no frame' ).toBe( 0 );
		expect( requested.filter( ( url ) => url.includes( 'images.example.com' ) ), 'no request to images.example.com' ).toEqual( [] );
	} );

	test( 'A6 paint: every computed colour is a token, shadows are hard, bags tilt at most one layer, text never below 12px', async ( { page } ) => {
		await openArchive( page );
		const offenders = await page.evaluate( ( allowed ) => {
			const bad = [];
			const props = [ 'color', 'backgroundColor', 'borderTopColor', 'borderLeftColor' ];
			for ( const el of document.querySelectorAll( '.cr-acq .pkiw-group, .cr-acq .pkiw-group__heading, .cr-receipt, .cr-receipt__title a' ) ) {
				const style = getComputedStyle( el );
				for ( const name of props ) {
					if ( ! allowed.includes( style[ name ] ) ) {
						bad.push( `${ el.className } ${ name } ${ style[ name ] }` );
					}
				}
				const shadow = style.boxShadow;
				if ( shadow !== 'none' && /\d+px \d+px (?!0px)/.test( shadow.replace( /rgb[a]?\([^)]*\)/g, '' ) ) ) {
					bad.push( `${ el.className } box-shadow ${ shadow }` );
				}
				if ( parseFloat( style.fontSize ) < 12 && el.textContent.trim() ) {
					bad.push( `${ el.className } font-size ${ style.fontSize }` );
				}
			}
			return bad;
		}, [ ...PALETTE ] );
		expect( offenders ).toEqual( [] );
		const tilts = await page.locator( '.cr-acq .pkiw-group' ).evaluateAll( ( bags ) => bags.map( ( bag ) => [ bag, ...bag.querySelectorAll( '*' ) ].filter( ( el ) => { const t = getComputedStyle( el ).transform; return t !== 'none'; } ).length ) );
		expect( Math.max( ...tilts ) ).toBeLessThanOrEqual( 2 );
		expect( await page.locator( '.cr-receipt' ).evaluateAll( ( els ) => els.every( ( el ) => getComputedStyle( el ).transform === 'none' ) ) ).toBe( true );
	} );

	for ( const width of WIDTHS ) {
		test( `A7 layout: ${ BAG_COLUMNS[ width ] } bag(s) a row at ${ width }, receipts in DOM order`, async ( { page } ) => {
			await openArchive( page, 1, { width } );
			const tops = await page.locator( '.cr-acq .pkiw-group' ).evaluateAll( ( bags ) => bags.map( ( bag ) => Math.round( bag.getBoundingClientRect().top ) ) );
			expect( tops.filter( ( top ) => top === tops[ 0 ] ).length ).toBe( Math.min( BAG_COLUMNS[ width ], tops.length ) );
			const order = await page.locator( 'ul.pkiw-group__items' ).evaluateAll( ( lists ) => lists.map( ( list ) => {
				const boxes = [ ...list.children ].map( ( li, index ) => ( { index, ...li.getBoundingClientRect().toJSON() } ) );
				const sorted = [ ...boxes ].sort( ( a, b ) => ( Math.round( a.top ) - Math.round( b.top ) ) || ( a.left - b.left ) );
				return sorted.map( ( box ) => box.index ).join() === boxes.map( ( box ) => box.index ).join();
			} ) );
			expect( order.every( Boolean ), 'visual order equals DOM order' ).toBe( true );
			if ( width === 1440 ) {
				const august = page.locator( '.cr-acq .pkiw-group', { has: page.locator( 'h2', { hasText: 'August 2026' } ) } );
				const box = await august.boundingBox();
				expect( box.width ).toBeGreaterThanOrEqual( 288 );
				const columns = await august.locator( 'ul.pkiw-group__items' ).evaluate( ( ul ) => getComputedStyle( ul ).gridTemplateColumns.split( ' ' ).length );
				expect( columns ).toBe( 2 );
				expect( await august.locator( 'ul.pkiw-group__items > li' ).count() ).toBe( 5 );
			}
		} );
	}

	test( 'A8 reflow: no horizontal scroll at 320px or at 1280px with 400% zoom; a long title wraps whole; the pager is visible', async ( { page } ) => {
		// 400% zoom of a 1280px window is a 320 CSS px viewport, so one width covers both.
		await openArchive( page, 1, { width: 320 } );
		expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ) ).toBe( true );
		const long = page.locator( '.cr-receipt__title a', { hasText: 'A Very Long Item Name' } );
		const box = await long.boundingBox();
		expect( box.width, 'the long title stays inside the viewport' ).toBeLessThanOrEqual( 320 );
		expect( await long.evaluate( ( a ) => getComputedStyle( a ).textOverflow ) ).not.toBe( 'ellipsis' );
		expect( await page.locator( 'nav[aria-label="Pagination"]' ).isVisible() ).toBe( true );
	} );

	test( 'A9 pager: core Query Pagination after the bags, aria-current chip inverted, 44px boxes, Previous absent on page 1', async ( { page } ) => {
		await openArchive( page, 1 );
		const pager = page.locator( 'nav[aria-label="Pagination"]' );
		expect( await pager.evaluate( ( nav ) => ! nav.closest( 'section.pkiw-group' ) && ( document.querySelector( '.cr-acq .pkiw-grouped' ).compareDocumentPosition( nav ) & Node.DOCUMENT_POSITION_FOLLOWING ) !== 0 ) ).toBe( true );
		const current = pager.locator( '[aria-current="page"]' );
		const style = await computed( current, [ 'background-color', 'color', 'border-top-width', 'min-height' ] );
		expect( contrast( style[ 'background-color' ], style.color ) ).toBeGreaterThanOrEqual( 4.5 );
		expect( await pager.getByText( 'Previous' ).count() ).toBe( 0 );
		for ( const chip of await pager.locator( 'a, [aria-current]' ).all() ) {
			const box = await chip.boundingBox();
			expect( box.height, 'chip height' ).toBeGreaterThanOrEqual( 44 );
			expect( box.width, 'chip width' ).toBeGreaterThanOrEqual( 44 );
		}
		const next = pager.locator( 'a.wp-block-query-pagination-next' );
		await next.hover();
		expect( ( await computed( next, [ 'transform' ] ) ).transform ).toBe( 'matrix(1, 0, 0, 1, -1, -1)' );
	} );

	test( 'A10 focus: the receipt draws a 3px yellow ring plus an ink halo; one tab stop each', async ( { page } ) => {
		await openArchive( page );
		await page.keyboard.press( 'Tab' );
		const first = page.locator( '.cr-receipt__title a' ).first();
		await first.focus();
		const receipt = first.locator( 'xpath=ancestor::article[contains(@class,"cr-receipt")]' );
		const style = await computed( receipt, [ 'outline-width', 'outline-color', 'box-shadow' ] );
		expect( style[ 'outline-width' ] ).toBe( '3px' );
		expect( style[ 'outline-color' ] ).toBe( RED.rgb );
		expect( style[ 'box-shadow' ] ).toContain( '7px' );
		expect( await receipt.evaluate( ( el ) => el.querySelectorAll( 'a, button, input, [tabindex]:not([tabindex="-1"])' ).length ) ).toBe( 1 );
	} );

	test( 'A11 dark: the ground flips, bag fills and receipts keep their fills, text 4.5:1, bag edges 3:1', async ( { page } ) => {
		await openArchive( page, 1, { scheme: 'light' } );
		const bagLight = await page.locator( '.cr-acq .pkiw-group' ).first().evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
		await openArchive( page, 1, { scheme: 'dark' } );
		const bag = page.locator( '.cr-acq .pkiw-group' ).first();
		expect( await bag.evaluate( ( el ) => getComputedStyle( el ).backgroundColor ) ).toBe( bagLight );
		const ground = await groundColor( page );
		for ( const bagEl of await page.locator( '.cr-acq .pkiw-group' ).all() ) {
			const edge = await bagEl.evaluate( ( el ) => getComputedStyle( el ).borderTopColor );
			expect( contrast( edge, ground ), 'bag edge against the ground' ).toBeGreaterThanOrEqual( 3 );
		}
		const title = page.locator( '.cr-receipt__title a' ).first();
		const receiptText = await computed( title, [ 'color' ] );
		expect( contrast( receiptText.color, 'rgb(251, 250, 245)' ), 'receipt text on ivory' ).toBeGreaterThanOrEqual( 4.5 );
		const label = page.locator( 'h2.pkiw-group__heading' ).first();
		const labelStyle = await computed( label, [ 'color' ] );
		const tape = await label.evaluate( ( el ) => getComputedStyle( el, '::before' ).backgroundColor );
		expect( contrast( labelStyle.color, tape ), 'month label on its tape' ).toBeGreaterThanOrEqual( 4.5 );
		expect( ( await computed( bag, [ 'box-shadow' ] ) )[ 'box-shadow' ] ).toContain( RED.rgb );
	} );

	test( 'A12 forced colors: bags and receipts are 2px CanvasText outlines with no background images; labels are CanvasText', async ( { page } ) => {
		await openArchive( page, 1, { forced: 'active' } );
		const bag = page.locator( '.cr-acq .pkiw-group' ).first();
		expect( await computed( bag, [ 'border-top-width', 'background-image' ] ) ).toEqual( { 'border-top-width': '2px', 'background-image': 'none' } );
		expect( await page.locator( '.cr-receipt' ).evaluateAll( ( els ) => els.every( ( el ) => getComputedStyle( el ).borderTopWidth === '2px' ) ) ).toBe( true );
		expect( await bag.evaluate( ( el ) => getComputedStyle( el, '::before' ).display ) ).toBe( 'none' );
		expect( await page.locator( 'h2.pkiw-group__heading' ).first().evaluate( ( el ) => getComputedStyle( el ).color ) ).toBe( await page.evaluate( () => { const probe = document.createElement( 'span' ); probe.style.color = 'CanvasText'; document.body.append( probe ); const c = getComputedStyle( probe ).color; probe.remove(); return c; } ) );
	} );

	test( 'A13 reduced motion: receipts compute transform none on hover and focus; transitions at most 0.001ms', async ( { page } ) => {
		await openArchive( page, 1, { motion: 'reduce' } );
		const receipt = page.locator( '.cr-receipt' ).first();
		await receipt.hover();
		expect( ( await computed( receipt, [ 'transform' ] ) ).transform ).toBe( 'none' );
		await receipt.locator( 'a' ).focus();
		const style = await computed( receipt, [ 'transform', 'transition-duration' ] );
		expect( style.transform ).toBe( 'none' );
		expect( parseFloat( style[ 'transition-duration' ] ) ).toBeLessThanOrEqual( 0.000001 );
	} );

	test( 'A14 empty archive (run after seed-239 --state=empty): the no-results paragraph, no bags, no pager', async ( { page } ) => {
		await openArchive( page );
		test.skip( await page.locator( 'section.pkiw-group' ).count() > 0, 'the archive is seeded; this line runs after the empty state' );
		expect( await page.locator( 'p.cr-archive-stream__empty' ).count() ).toBe( 1 );
		expect( await page.locator( 'nav[aria-label="Pagination"]' ).count() ).toBe( 0 );
	} );

	test( 'A17 copy: only the H1 and tiles, description, month labels, receipt titles and pager labels, then shipped chrome', async ( { page } ) => {
		await openArchive( page );
		const html = await page.content();
		expect( html ).not.toContain( 'SAMPLE CONTENT' );
		const text = await page.locator( 'main .wp-block-query' ).evaluate( ( el ) => el.innerText );
		expect( text ).not.toMatch( /Acquired|Purchase|Visit store|Store\b/ );
	} );

	test( 'A18 cost never prints on the archive', async ( { page } ) => {
		for ( const number of [ 1, 2, 3 ] ) {
			await openArchive( page, number );
			const html = await page.content();
			for ( const cost of [ '$129.00', '$12.00', '$18.00' ] ) {
				expect( html, `page ${ number } leaks ${ cost }` ).not.toContain( cost );
			}
		}
	} );

	test( 'A16 microformats (DOM level; the mf2/mf2 parse runs in the orchestrator\'s PHP pass): items are h-entry with hidden url, date and author, and an h-cite with a name only', async ( { page } ) => {
		await openArchive( page );
		const items = page.locator( 'ul.pkiw-group__items > li' );
		const shapes = await items.evaluateAll( ( lis ) => lis.map( ( li ) => ( {
			entry: li.classList.contains( 'h-entry' ),
			cite: [ ...li.querySelectorAll( '.u-acquisition-of.h-cite' ) ].map( ( c ) => ( { name: !! c.querySelector( '.p-name' ), url: !! c.querySelector( '.u-url' ) } ) ),
			root: !! li.querySelector( 'article.h-cite, article.h-entry' ),
		} ) ) );
		expect( shapes.every( ( s ) => s.entry && ! s.root ) ).toBe( true );
		expect( shapes.every( ( s ) => s.cite.every( ( c ) => c.name && ! c.url ) ) ).toBe( true );
	} );
} );

// ---------------------------------------------------------------------------
// Single
// ---------------------------------------------------------------------------
test.describe( 'single (PKIW 239)', () => {
	test( 'A19 S01 has exactly one h1 inside the receipt and a hidden p-name, no visible card h2', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		const h1s = page.locator( 'main h1' );
		expect( await h1s.count() ).toBe( 1 );
		expect( ( await h1s.first().textContent() ).trim() ).toBe( 'Field Recorder' );
		expect( await h1s.first().evaluate( ( el ) => !! el.closest( '.cr-acq-single__receipt' ) ) ).toBe( true );
		expect( await page.locator( '.cr-acq-single h2.pk-title' ).count() ).toBe( 0 );
		expect( await page.locator( '.cr-acq-single data.p-name[hidden]' ).count() ).toBe( 1 );
	} );

	test( 'A20 photo: reader alt, once, width and height, filter none, aria-hidden tape; R01 has no frame and a full-width receipt', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		const img = page.locator( '.cr-acq-single__photo img' );
		expect( await img.getAttribute( 'alt' ) ).toBe( 'A handheld recorder with two microphones' );
		expect( await img.getAttribute( 'width' ) ).not.toBeNull();
		expect( ( await computed( img, [ 'filter' ] ) ).filter ).toBe( 'none' );
		expect( await page.locator( '.cr-acq-single__tape' ).getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		await openSingle( page, 'Garden Trowel' );
		expect( await page.locator( 'main img.u-photo, .cr-acq-single__photo img' ).count(), 'the featured and card photo print once' ).toBe( 1 );
		expect( await page.locator( '.single-post__featured img' ).count(), 'template featured image dropped' ).toBe( 0 );
		await openSingle( page, 'Map Poster' );
		expect( await page.locator( '.cr-acq-single__photo' ).count() ).toBe( 0 );
		const [ article, receipt ] = await Promise.all( [ page.locator( '.cr-acq-single' ).boundingBox(), page.locator( '.cr-acq-single__receipt' ).boundingBox() ] );
		expect( receipt.width ).toBeGreaterThanOrEqual( article.width - 2 );
	} );

	test( 'A21 receipt rows: Acquisition, From, Acquired, Cost; From opens safely; A01 reads August 1, 2026; A03 reads Inherited; S03 has a plain From', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		expect( ( await page.locator( '.cr-acq-single__facts dt' ).allTextContents() ).map( ( t ) => t.trim() ) ).toEqual( [ 'Acquisition', 'From', 'Acquired', 'Cost' ] );
		const link = page.locator( '.cr-acq-single__facts a.u-url' );
		expect( await link.getAttribute( 'href' ) ).toBe( 'https://shop.example.com/field-recorder' );
		expect( await link.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( await link.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer' );
		expect( await link.getAttribute( 'aria-label' ) ).toBe( 'Riverside Audio (opens in a new tab)' );
		expect( await link.locator( 'svg' ).count() ).toBeLessThanOrEqual( 1 );
		expect( await page.content() ).not.toContain( 'Visit store' );
		await openSingle( page, 'Brass Desk Lamp' );
		const when = page.locator( '.cr-acq-single__fact--acquired time' );
		expect( ( await when.textContent() ).trim() ).toBe( 'August 1, 2026' );
		expect( await when.getAttribute( 'datetime' ) ).toBe( '2026-08-01' );
		await openSingle( page, 'Wool Scarf' );
		expect( ( await page.locator( '.cr-acq-single__fact--type dd' ).textContent() ).trim() ).toBe( 'Inherited' );
		await openSingle( page, 'Ceramic Mug' );
		expect( await page.locator( '.cr-acq-single__fact--from a' ).count() ).toBe( 0 );
		expect( await page.locator( '.cr-acq-single__fact--acquired' ).count() ).toBe( 0 );
	} );

	test( 'A22 cost row: S01 prints $129.00 (toggle on); J02 prints none', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		expect( ( await page.locator( '.cr-acq-single__fact--cost dd' ).textContent() ).trim() ).toBe( '$129.00' );
		await openSingle( page, 'Pocket Notebook' );
		expect( await page.locator( '.cr-acq-single__fact--cost' ).count() ).toBe( 0 );
		expect( await page.content() ).not.toContain( '$12.00' );
	} );

	test( 'A23 notes: S01 has an upright "Notes" h2 then the text; R01 has no notes element', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		const heading = page.locator( '.cr-acq-single__notes h2' );
		expect( ( await heading.textContent() ).trim() ).toBe( 'Notes' );
		expect( ( await computed( heading, [ 'transform' ] ) ).transform ).toBe( 'none' );
		expect( ( await page.locator( '.cr-acq-single__notes p.p-content' ).textContent() ).trim() ).toBe( 'Records the morning birds.' );
		await openSingle( page, 'Map Poster' );
		expect( await page.locator( '.cr-acq-single__notes' ).count() ).toBe( 0 );
	} );

	test( 'A24 post navigation follows the comments and its chips are 44px tall', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		const nav = page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		expect( await nav.count() ).toBe( 1 );
		for ( const chip of await nav.locator( 'a' ).all() ) {
			expect( ( await chip.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		}
	} );

	for ( const width of [ 1440, 800, 375, 320 ] ) {
		test( `A25 layout at ${ width }: ${ width >= 1024 ? 'photo left, receipt right, notes under the receipt' : 'photo, receipt, notes stacked' }; DOM, visual and tab order agree; no overflow`, async ( { page } ) => {
			await openSingle( page, 'Field Recorder', { width } );
			const photo = await page.locator( '.cr-acq-single__photo' ).boundingBox();
			const receipt = await page.locator( '.cr-acq-single__receipt' ).boundingBox();
			const notes = await page.locator( '.cr-acq-single__notes' ).boundingBox();
			if ( width >= 1024 ) {
				expect( photo.x + photo.width ).toBeLessThanOrEqual( receipt.x + 2 );
				expect( notes.y ).toBeGreaterThanOrEqual( receipt.y + receipt.height - 2 );
				expect( Math.abs( notes.x - receipt.x ) ).toBeLessThanOrEqual( 2 );
			} else {
				expect( photo.y + photo.height ).toBeLessThanOrEqual( receipt.y + 2 );
				expect( receipt.y + receipt.height ).toBeLessThanOrEqual( notes.y + 2 );
			}
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ) ).toBe( true );
		} );
	}

	test( 'A26 paint: palette colours only, hard shadows, no background images but fibre or tape, upright headings', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		const offenders = await page.evaluate( ( allowed ) => {
			const bad = [];
			for ( const el of document.querySelectorAll( '.cr-acq-single__photo, .cr-acq-single__receipt, .cr-acq-single__notes, .cr-acq-single__title, .cr-acq-single__fact dt, .cr-acq-single__fact dd' ) ) {
				const style = getComputedStyle( el );
				for ( const name of [ 'color', 'backgroundColor', 'borderTopColor' ] ) {
					if ( ! allowed.includes( style[ name ] ) ) {
						bad.push( `${ el.className } ${ name } ${ style[ name ] }` );
					}
				}
				if ( el.matches( '.cr-acq-single__title, .cr-acq-single__fact dt' ) && style.transform !== 'none' ) {
					bad.push( `${ el.className } transform` );
				}
			}
			return bad;
		}, [ ...PALETTE ] );
		expect( offenders ).toEqual( [] );
	} );

	test( 'A27 dark: the receipt and photo frame keep ivory with violet text; the From link reads 4.5:1 and is underlined', async ( { page } ) => {
		await openSingle( page, 'Field Recorder', { scheme: 'dark' } );
		const link = page.locator( '.cr-acq-single__link' );
		const style = await computed( link, [ 'color', 'text-decoration-line' ] );
		expect( style[ 'text-decoration-line' ] ).toContain( 'underline' );
		expect( contrast( style.color, 'rgb(251, 250, 245)' ) ).toBeGreaterThanOrEqual( 4.5 );
		expect( ( await computed( page.locator( '.cr-acq-single__photo' ), [ 'background-color' ] ) )[ 'background-color' ] ).toBe( 'rgb(251, 250, 245)' );
	} );

	test( 'A28 forced colors: frames are 2px CanvasText outlines and the From link uses LinkText', async ( { page } ) => {
		await openSingle( page, 'Field Recorder', { forced: 'active' } );
		expect( ( await computed( page.locator( '.cr-acq-single__photo' ), [ 'border-top-width' ] ) )[ 'border-top-width' ] ).toBe( '2px' );
		expect( await page.locator( '.cr-acq-single__tape' ).evaluate( ( el ) => getComputedStyle( el ).display ) ).toBe( 'none' );
	} );

	test( 'A29 focus: the From link and both nav chips show the 3px yellow ring', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		for ( const link of [ page.locator( '.cr-acq-single__link' ), ...( await page.locator( 'nav.cr-post-nav a' ).all() ) ] ) {
			await link.focus();
			const style = await computed( link, [ 'outline-width', 'outline-color' ] );
			expect( style[ 'outline-width' ] ).toBe( '3px' );
			expect( style[ 'outline-color' ] ).toBe( RED.rgb );
		}
	} );

	test( 'A30 microformats (DOM level): the h-cite keeps name, photo, location, published and the where URL, and has no "acquired"', async ( { page } ) => {
		await openSingle( page, 'Field Recorder' );
		const cite = page.locator( 'article.h-cite.u-acquisition-of' );
		expect( await cite.count() ).toBe( 1 );
		expect( await cite.locator( '.p-name' ).count() ).toBeGreaterThan( 0 );
		expect( await cite.locator( 'img.u-photo' ).count() ).toBe( 1 );
		expect( ( await cite.locator( '.p-location' ).textContent() ).trim() ).toBe( 'Riverside Audio' );
		expect( await cite.locator( 'time.dt-published' ).getAttribute( 'datetime' ) ).toBe( await page.locator( '.cr-acq-single__fact--acquired time' ).getAttribute( 'datetime' ) );
		expect( await cite.locator( 'a.u-url' ).getAttribute( 'href' ) ).toBe( 'https://shop.example.com/field-recorder' );
		expect( await page.content() ).not.toMatch( /u-acquired\b/ );
	} );

	test( 'A53 an editor card with a blank post title reads its h1 from the card (J06 Watering Can)', async ( { page } ) => {
		await openSingle( page, 'Watering Can' );
		expect( await page.locator( 'main h1' ).count() ).toBe( 1 );
		expect( ( await page.locator( 'main h1' ).textContent() ).trim() ).toBe( 'Watering Can' );
		expect( await page.locator( 'main h1' ).evaluate( ( el ) => el.closest( '.cr-acq-single__receipt' ) !== null ) ).toBe( true );
	} );

	test( 'A54 a second card prints as the plugin card under the receipt, compact and upright (J04)', async ( { page } ) => {
		await openSingle( page, 'Bike Bell' );
		expect( await page.locator( 'main h1' ).count() ).toBe( 1 );
		expect( ( await page.locator( 'main h1' ).textContent() ).trim() ).toBe( 'Bike Bell' );
		const later = page.locator( '.pk-card.k-acquisition' );
		expect( await later.count() ).toBe( 1 );
		expect( await later.locator( 'h2.pk-title.p-name' ).textContent() ).toContain( 'Spare Bell' );
		expect( ( await computed( later, [ 'transform' ] ) ).transform ).toBe( 'none' );
		const offenders = await later.evaluate( ( el, allowed ) => [ el, ...el.querySelectorAll( '*' ) ].flatMap( ( node ) => {
			const style = getComputedStyle( node );
			const bad = [];
			if ( node.textContent.trim() && node.children.length === 0 && ! allowed.includes( style.color ) ) {
				bad.push( `${ node.className } ${ style.color }` );
			}
			if ( node.textContent.trim() && node.children.length === 0 && parseFloat( style.fontSize ) < 12 ) {
				bad.push( `${ node.className } ${ style.fontSize }` );
			}
			return bad;
		} ), [ ...PALETTE ] );
		expect( offenders ).toEqual( [] );
		await page.goto( ARCHIVE, { waitUntil: 'load' } );
		const photos = await page.locator( '.cr-receipt', { has: page.getByRole( 'link', { name: 'Bike Bell' } ) } ).locator( 'img' ).evaluateAll( ( els ) => els.map( ( el ) => el.getAttribute( 'src' ) ) );
		expect( photos.length, 'the archive receipt shows the first card photo only' ).toBe( 1 );
	} );

	test( 'A55 a remote card photo prints no frame and no request to images.example.com (A05 Paper Lantern)', async ( { page } ) => {
		const requested = [];
		page.on( 'request', ( request ) => requested.push( request.url() ) );
		await openSingle( page, 'Paper Lantern' );
		expect( await page.locator( '.cr-acq-single__photo, .cr-acq-single img' ).count() ).toBe( 0 );
		expect( requested.filter( ( url ) => url.includes( 'images.example.com' ) ) ).toEqual( [] );
	} );
} );

// ---------------------------------------------------------------------------
// Stream
// ---------------------------------------------------------------------------
test.describe( 'stream (PKIW 239)', () => {
	test( 'A32 to A34 stubs sit in the collage, read thumb, title, label, place, then one date, and hold one link', async ( { page } ) => {
		await open( page, STREAM );
		const stubs = page.locator( 'article.cr-acq-stub' );
		test.skip( await stubs.count() === 0, 'no acquisition stub on the first Stream page' );
		expect( await page.locator( '.cr-stream-page .wp-block-post-template' ).count() ).toBeGreaterThan( 0 );
		const stub = stubs.filter( { has: page.getByRole( 'link', { name: 'Field Recorder' } ) } ).first();
		const order = await stub.evaluate( ( el ) => [ ...el.children ].filter( ( c ) => ! c.hasAttribute( 'hidden' ) ).map( ( c ) => c.tagName.toLowerCase() + ( c.className ? '.' + c.className.split( ' ' )[ 0 ] : '' ) ) );
		expect( order ).toEqual( [ 'figure.cr-acq-stub__thumb', 'h2.cr-acq-stub__title', 'span.pk-kindlabel', 'p.cr-acq-stub__where', 'p.cr-acq-stub__date' ] );
		expect( ( await stub.locator( '.pk-kindlabel' ).textContent() ).trim() ).toBe( 'Purchase' );
		expect( ( await stub.locator( 'time' ).getAttribute( 'class' ) ) || '' ).toBe( '' );
		expect( await visibleLinks( stub ) ).toBe( 1 );
		const li = page.locator( 'li', { has: stub } ).first();
		expect( await hiddenLinksStayHidden( li ) ).toBe( true );
		expect( await li.evaluate( ( el ) => el.outerHTML ) ).not.toContain( 'shop.example.com' );
		const thumb = stub.locator( 'img' );
		expect( await thumb.getAttribute( 'alt' ) ).toBe( '' );
		expect( await thumb.getAttribute( 'loading' ) ).toBe( 'lazy' );
		expect( ( await computed( thumb, [ 'filter' ] ) ).filter ).toBe( 'none' );
		expect( ( await computed( stub.locator( '.cr-acq-stub__title a' ), [ 'font-family' ] ) )[ 'font-family' ] ).toContain( 'Roboto Slab' );
		const second = stubs.filter( { has: page.getByRole( 'link', { name: 'Ceramic Mug' } ) } ).first();
		if ( await second.count() ) {
			expect( await second.locator( 'time' ).count(), 'one date on S03' ).toBe( 1 );
		}
	} );

	test( 'A35 and A36 the Stream shows no cost or notes, and the label reads violet at 4.5:1 with no orange text', async ( { page } ) => {
		await open( page, STREAM );
		const stubs = page.locator( 'article.cr-acq-stub' );
		test.skip( await stubs.count() === 0, 'no acquisition stub on the first Stream page' );
		const html = await stubs.first().evaluate( ( el ) => el.closest( 'li' ).outerHTML );
		expect( html ).not.toContain( '$129.00' );
		expect( html ).not.toContain( 'Records the morning birds' );
		const label = stubs.first().locator( '.pk-kindlabel' );
		const style = await computed( label, [ 'color' ] );
		expect( style.color ).toBe( 'rgb(36, 28, 74)' );
		expect( contrast( style.color, 'rgb(251, 250, 245)' ) ).toBeGreaterThanOrEqual( 4.5 );
		expect( await stubs.first().evaluate( ( el ) => [ ...el.querySelectorAll( '*' ) ].filter( ( n ) => n.textContent.trim() && n.children.length === 0 ).every( ( n ) => getComputedStyle( n ).color !== 'rgb(251, 133, 0)' ) ) ).toBe( true );
	} );

	test( 'A37 to A39 paint: no old tint, one rotation token, 17rem column width, and a ring around the whole stub', async ( { page } ) => {
		await open( page, STREAM );
		const stub = page.locator( 'article.cr-acq-stub' ).first();
		test.skip( await stub.count() === 0, 'no acquisition stub on the first Stream page' );
		const colors = await stub.evaluate( ( el ) => [ el, ...el.querySelectorAll( '*' ) ].flatMap( ( n ) => [ getComputedStyle( n ).color, getComputedStyle( n ).backgroundColor ] ) );
		expect( colors ).not.toContain( 'rgb(207, 224, 230)' );
		expect( colors ).not.toContain( 'rgb(109, 155, 181)' );
		expect( ( await computed( stub, [ 'transform' ] ) ).transform ).not.toBe( 'none' );
		const widths = await stub.evaluate( ( el ) => ( { stub: el.offsetWidth, li: parseFloat( getComputedStyle( el.closest( 'li' ) ).width ) } ) );
		expect( Math.abs( widths.stub - widths.li ) ).toBeLessThanOrEqual( 1 );
		expect( ( await stub.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		await stub.locator( 'a' ).focus();
		const ring = await computed( stub, [ 'outline-width', 'outline-color' ] );
		expect( ring[ 'outline-width' ] ).toBe( '3px' );
		expect( ring[ 'outline-color' ] ).toBe( RED.rgb );
	} );

	test( 'A38 the front-page lane gives the stub a 15.625rem measure, centered', async ( { page } ) => {
		await open( page, '/' );
		const stub = page.locator( '.cr-fieldnotes .cr-home-stream .cr-acq-stub' ).first();
		test.skip( await stub.count() === 0, 'no acquisition stub in the front-page Stream lane' );
		const style = await computed( stub, [ 'max-width', 'margin-left', 'margin-right' ] );
		expect( style[ 'max-width' ] ).toBe( '250px' );
		expect( style[ 'margin-left' ] ).toBe( style[ 'margin-right' ] );
	} );

	test( 'A40 dark keeps ivory paper, violet text and a yellow shadow; forced colors draw a 2px CanvasText outline', async ( { page } ) => {
		await open( page, STREAM, { scheme: 'dark' } );
		const stub = page.locator( 'article.cr-acq-stub' ).first();
		test.skip( await stub.count() === 0, 'no acquisition stub on the first Stream page' );
		expect( ( await computed( stub.locator( '.cr-acq-stub__title a' ), [ 'color' ] ) ).color ).toBe( 'rgb(36, 28, 74)' );
		await open( page, STREAM, { forced: 'active' } );
		expect( ( await computed( page.locator( 'article.cr-acq-stub' ).first(), [ 'border-top-width' ] ) )[ 'border-top-width' ] ).toBe( '2px' );
	} );

	for ( const width of [ 375, 320 ] ) {
		test( `A41 at ${ width } the stub fits one column with no overflow and wraps long titles`, async ( { page } ) => {
			await open( page, STREAM, { width } );
			test.skip( await page.locator( 'article.cr-acq-stub' ).count() === 0, 'no acquisition stub on the first Stream page' );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ) ).toBe( true );
			expect( ( await page.locator( 'article.cr-acq-stub' ).first().boundingBox() ).width ).toBeLessThanOrEqual( width );
		} );
	}

	test( 'A42 microformats (DOM level): the stub is no h-* root and its time has no mf2 class, so the entry date is the post date', async ( { page } ) => {
		await open( page, STREAM );
		const stub = page.locator( 'article.cr-acq-stub' ).first();
		test.skip( await stub.count() === 0, 'no acquisition stub on the first Stream page' );
		const li = page.locator( 'li', { has: stub } ).first();
		expect( await li.evaluate( ( el ) => el.classList.contains( 'h-entry' ) ) ).toBe( true );
		expect( await stub.evaluate( ( el ) => el.className.split( ' ' ).some( ( c ) => /^(h|u|p|dt|e)-/.test( c ) ) ) ).toBe( false );
		expect( await li.locator( '.pk-entry-props data.dt-published' ).count() ).toBe( 1 );
		expect( await li.locator( 'span.h-cite.u-acquisition-of [class~="p-name"]' ).count() ).toBe( 1 );
	} );
} );
