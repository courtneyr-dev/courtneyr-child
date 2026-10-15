// `CR_SPEC=favorite.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`:
// PKIW issue 259 favorite pages against the approved decision (decision-2026-10-06.md, with
// the X1 dark and X7 alt rulings): the star chart archive, the scrapbook single and the Stream
// clipping. Read-only GETs, logged out; needs a running site with the seed-259.php fixtures
// (CR_BASE_URL, CR_ALLOW_REMOTE=1 and CR_USER_AGENT for a Pantheon sandbox). Each test sets its
// own viewport, so one project is enough.
//
// Fixture labels follow assets/259-favorites/seed-259.php: S1-S4 on the Stream, F01-F16 dated
// 2026-03 to 2026-08, F16 private. Paths come from the archive page's own links, so the date
// permalink structure does not matter. CR_FAV_STATE=empty|lone|large runs the matching
// state test after `seed-259.php --state=...`.
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = process.env.CR_FAV_ARCHIVE_PATH || '/kind/favorite/';
const STREAM = process.env.CR_FAV_STREAM_PATH || '/stream/';
const STATE = process.env.CR_FAV_STATE || 'full';

const IVORY = 'rgb(251, 250, 245)';
const YELLOW = 'rgb(255, 183, 3)';
const VIOLET = 'rgb(36, 28, 74)';
const WIDTHS = [ [ 1440, 3 ], [ 768, 2 ], [ 375, 1 ], [ 320, 1 ] ];

async function visit( page, path, scheme = 'light' ) {
	await page.emulateMedia( { colorScheme: scheme } );
	await page.addInitScript( ( value ) => {
		try {
			window.localStorage.setItem( 'courtneyr-theme', value );
		} catch ( error ) {
			// Storage can be blocked; the media emulation above still applies.
		}
	}, scheme );
	const response = await page.goto( path, { waitUntil: 'load' } );
	return response.status();
}

// Item links of one archive page, in DOM order: [ { text, href, month } ].
async function items( page ) {
	return page.locator( '.cr-favorite-chart .pkiw-group' ).evaluateAll( ( bands ) => bands.flatMap( ( band ) => {
		const month = band.querySelector( '.pkiw-group__heading' )?.textContent.trim();
		return [ ...band.querySelectorAll( ':scope .pkiw-group__items > li' ) ].map( ( li ) => ( {
			month,
			text: li.querySelector( 'a' )?.textContent.trim(),
			href: li.querySelector( 'a' )?.getAttribute( 'href' ),
		} ) );
	} ) );
}

// "Exactly one a" counts rendered anchors: a[href] with no [hidden] on it or an ancestor, and every
// a[tabindex="-1"] sits inside a hidden subtree (orchestrator ruling W3-G2).
async function visibleLinks( locator ) {
	return locator.evaluate( ( root ) => {
		const hidden = ( el ) => Boolean( el.closest( '[hidden]' ) );
		const links = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( a ) => ! hidden( a ) && a.getClientRects().length > 0 );
		const stray = [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( a ) => ! hidden( a ) );
		return { count: links.length, stray: stray.length, names: links.map( ( a ) => ( a.getAttribute( 'aria-label' ) || a.textContent ).trim() ), targets: links.map( ( a ) => a.getAttribute( 'target' ) ) };
	} );
}

async function permalink( page, text ) {
	await visit( page, ARCHIVE );
	let link = page.locator( `.cr-favorite-chart .pkiw-group__items a:text-is("${ text }")` ).first();
	if ( 0 === await link.count() ) {
		await visit( page, `${ ARCHIVE }page/2/` );
		link = page.locator( `.cr-favorite-chart .pkiw-group__items a:text-is("${ text }")` ).first();
	}
	test.skip( 0 === await link.count(), `no favorite titled ${ text } (run seed-259.php first)` );
	return link.getAttribute( 'href' );
}

async function axe( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations.map( ( v ) => ( { id: v.id, nodes: v.nodes.map( ( n ) => n.target.join( ' ' ) ) } ) ) );
}

test.describe( 'archive', () => {
	test.skip( 'full' !== STATE, 'the full fixture set is not seeded' );

	test( 'the header is the approved shell: one h1 "Favorite", 8 decorative tiles, a badge, no sample marker', async ( { page } ) => {
		expect( await visit( page, ARCHIVE ) ).toBe( 200 );
		const h1 = page.locator( 'main h1.cr-archive__title' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1.locator( '.cr-cutout__text' ) ).toHaveText( 'Favorite' );
		const tiles = h1.locator( '.cr-cutout__tile' );
		await expect( tiles ).toHaveCount( 8 );
		for ( const tile of await tiles.all() ) {
			expect( await tile.evaluate( ( el ) => Boolean( el.closest( '[aria-hidden="true"]' ) ) ), 'tile is decorative' ).toBe( true );
		}
		const transforms = await tiles.evaluateAll( ( els ) => els.map( ( el ) => getComputedStyle( el ).transform ) );
		for ( let i = 1; i < transforms.length; i++ ) {
			expect( transforms[ i ], `tile ${ i } leans differently from tile ${ i - 1 }` ).not.toBe( transforms[ i - 1 ] );
		}
		await expect( page.locator( '.cr-archive__header .courtneyr-archive-identity, .cr-archive__header [class*="archive-identity"]' ).first() ).toBeVisible();
		expect( await page.locator( 'body' ).innerText() ).not.toMatch( /SAMPLE CONTENT|curated by month/i );
	} );

	test( 'page 1 holds the run month, August and one June clipping; page 2 the rest, with a repeated June heading', async ( { page } ) => {
		await visit( page, ARCHIVE );
		const one = await items( page );
		expect( one.length ).toBe( 12 );
		const counts = ( list ) => list.reduce( ( acc, item ) => ( { ...acc, [ item.month ]: ( acc[ item.month ] || 0 ) + 1 } ), {} );
		const first = counts( one );
		expect( first[ 'August 2026' ] ).toBe( 7 );
		expect( first[ 'June 2026' ] ).toBe( 1 );
		expect( Object.keys( first ).length ).toBe( 3 );
		await visit( page, `${ ARCHIVE }page/2/` );
		const two = await items( page );
		expect( counts( two ) ).toEqual( { 'June 2026': 6, 'March 2026': 1 } );
		expect( await page.locator( '.pkiw-group__heading' ).first().getAttribute( 'id' ) ).toBe( 'pkiw-group-2026-06' );
	} );

	test( 'the heading outline is h1, month h2s and item h3s; the pager is the next element after the last band', async ( { page } ) => {
		await visit( page, ARCHIVE );
		expect( await page.locator( 'main h1' ).count() ).toBe( 1 );
		expect( await page.locator( '.cr-favorite-chart .pkiw-group > h2.pkiw-group__heading' ).count() ).toBe( 3 );
		expect( await page.locator( '.cr-favorite-chart .pkiw-group__items h3.cr-fav-title' ).count() ).toBe( 12 );
		const pager = await page.locator( '.cr-favorite-chart .pkiw-grouped' ).evaluate( ( el ) => {
			const next = el.nextElementSibling;
			return { tag: next?.tagName, cls: next?.className, label: next?.getAttribute( 'aria-label' ) };
		} );
		expect( pager.tag ).toBe( 'NAV' );
		expect( pager.cls ).toContain( 'wp-block-query-pagination' );
		expect( pager.label ).toBe( 'Pagination' );
		await expect( page.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-query-pagination-next' ) ).toHaveCount( 1 );
		await expect( page.locator( '.wp-block-query-pagination .page-numbers.current' ) ).toHaveAttribute( 'aria-current', 'page' );
	} );

	test( 'each item has one visible focusable link and shows no author, date, image, label or external link', async ( { page } ) => {
		await visit( page, ARCHIVE );
		for ( const li of await page.locator( '.cr-favorite-chart .pkiw-group__items > li' ).all() ) {
			const links = await visibleLinks( li );
			expect( links.count, 'one visible link' ).toBe( 1 );
			expect( links.stray, 'no visible tabindex=-1 link' ).toBe( 0 );
			expect( links.targets[ 0 ], 'not a new-tab link' ).toBeNull();
			const absent = await li.evaluate( ( el ) => [ '.pk-kindlabel', '.pk-sub', '.pk-note', 'img', 'figure', '.pk-stars', 'a[target="_blank"]' ].filter( ( selector ) => el.querySelector( selector ) ) );
			expect( absent, 'cut in markup' ).toEqual( [] );
			expect( await li.evaluate( ( el ) => [ ...el.querySelectorAll( 'time' ) ].filter( ( t ) => ! t.closest( '[hidden]' ) ).length ), 'no visible date' ).toBe( 0 );
		}
		const dataOn = ( ) => page.locator( '.cr-favorite-chart .pkiw-group__items > li' ).evaluateAll( ( lis ) => lis.filter( ( li ) => li.querySelector( 'data.u-favorite-of' ) ).map( ( li ) => li.querySelector( 'a' ).textContent.trim() ) );
		const first = await dataOn();
		expect( first, 'F01 prints favorite-of data' ).toContain( 'Field Guide to Quiet Software' );
		expect( first, 'F05 has no URL' ).not.toContain( 'Kitchen Radio Hour' );
		expect( first, 'F08 has no URL' ).not.toContain( 'Favorite, June 30, 2026' );
		await visit( page, `${ ARCHIVE }page/2/` );
		const second = await dataOn();
		expect( second, 'F09 takes favorite-of from its meta' ).toContain( 'Seed Recipe Index' );
		expect( second, 'F10 stores no favorite meta' ).not.toContain( 'Favorite, June 24, 2026' );
	} );

	test( 'the clipping and the star center both hit the item link', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, ARCHIVE );
		for ( const li of await page.locator( '.cr-favorite-chart .pkiw-group__items > li' ).all() ) {
			await li.scrollIntoViewIfNeeded();
			const hit = await li.evaluate( ( el ) => {
				const a = el.querySelector( 'a' );
				const clip = el.querySelector( '.cr-fav-clipping' ).getBoundingClientRect();
				const star = el.querySelector( '.cr-fav-star' ).getBoundingClientRect();
				const at = ( x, y ) => a.contains( document.elementFromPoint( x, y ) );
				return { clip: at( clip.left + clip.width / 2, clip.top + clip.height / 2 ), star: at( star.left + star.width / 2, star.top + star.height / 2 ) };
			} );
			expect( hit.clip, 'the clipping center hits the link' ).toBe( true );
			expect( hit.star, 'the star center hits the link' ).toBe( true );
		}
	} );

	test( 'each item carries hidden url, date and author, and the named duplicates are told apart by month', async ( { page } ) => {
		await visit( page, `${ ARCHIVE }page/2/` );
		const li = page.locator( '.cr-favorite-chart .pkiw-group__items > li' ).first();
		expect( await li.evaluate( ( el ) => el.className ) ).toContain( 'h-entry' );
		expect( await li.locator( '.dt-published' ).count() ).toBeGreaterThan( 0 );
		expect( await li.locator( '.p-author' ).count() ).toBeGreaterThan( 0 );
		const names = await page.locator( '.cr-favorite-chart a:has-text("Garden Planner Notes")' ).evaluateAll( ( els ) => els.map( ( a ) => ( { label: a.getAttribute( 'aria-labelledby' ), name: a.getAttribute( 'aria-labelledby' ).split( ' ' ).map( ( id ) => document.getElementById( id )?.textContent.trim() ).join( ' ' ) } ) ) );
		expect( names.map( ( n ) => n.name ) ).toEqual( [ 'Garden Planner Notes June 2026', 'Garden Planner Notes March 2026' ] );
	} );

	for ( const [ width, columns ] of WIDTHS ) {
		test( `at ${ width }px the grid has ${ columns } column(s) and DOM, tab and visual order agree`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await visit( page, ARCHIVE );
			const grid = await page.locator( '.cr-favorite-chart .pkiw-group' ).nth( 1 ).locator( '.pkiw-group__items' ).evaluate( ( el ) => {
				const lis = [ ...el.children ];
				const lefts = [ ...new Set( lis.map( ( li ) => Math.round( li.getBoundingClientRect().left ) ) ) ];
				return { cols: getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length, distinctLefts: lefts.length, count: lis.length, lastLeft: Math.round( lis.at( -1 ).getBoundingClientRect().left ), firstLeft: Math.round( lis[ 0 ].getBoundingClientRect().left ) };
			} );
			expect( grid.cols ).toBe( columns );
			expect( grid.count ).toBe( 7 );
			if ( 3 === columns ) {
				expect( grid.lastLeft, 'the last row stays left-aligned' ).toBe( grid.firstLeft );
			}
			const boxes = await page.locator( '.cr-favorite-chart .pkiw-group__items > li' ).evaluateAll( ( lis ) => lis.map( ( li ) => { const r = li.getBoundingClientRect(); return { top: Math.round( r.top + window.scrollY ), left: Math.round( r.left ) }; } ) );
			const visual = [ ...boxes ].map( ( b, i ) => ( { ...b, i } ) ).sort( ( a, b ) => a.top - b.top || a.left - b.left ).map( ( b ) => b.i );
			expect( visual, 'visual order equals DOM order' ).toEqual( boxes.map( ( _, i ) => i ) );
			const order = [];
			for ( let i = 0; i < boxes.length; i++ ) {
				await page.keyboard.press( 'Tab' );
				const focused = await page.evaluate( () => document.activeElement?.closest( '.pkiw-group__items > li' ) ? [ ...document.activeElement.closest( '.pkiw-group__items' ).parentElement.parentElement.querySelectorAll( '.pkiw-group__items > li' ) ].indexOf( document.activeElement.closest( 'li' ) ) : -1 );
				order.push( focused );
			}
			const tabbed = order.filter( ( n ) => n >= 0 );
			expect( tabbed, 'Tab visits items in order' ).toEqual( [ ...tabbed ].sort( ( a, b ) => a - b ) );
		} );
	}

	test( 'paint: upright ivory clippings, flat yellow stars with a violet edge, no #013eac, no filter', async ( { page } ) => {
		await visit( page, ARCHIVE );
		const paint = await page.locator( '.cr-fav-item' ).first().evaluate( ( el ) => {
			const clip = getComputedStyle( el.querySelector( '.cr-fav-clipping' ) );
			const shape = getComputedStyle( el.querySelector( '.cr-fav-star__shape' ) );
			return { transform: clip.transform, bg: clip.backgroundColor, fill: shape.fill, stroke: shape.stroke, strokeWidth: shape.strokeWidth, filter: clip.filter, shadow: clip.boxShadow };
		} );
		expect( paint.transform ).toBe( 'none' );
		expect( paint.bg ).toBe( IVORY );
		expect( paint.fill ).toBe( YELLOW );
		expect( paint.stroke ).toBe( VIOLET );
		expect( paint.strokeWidth ).toBe( '2px' );
		expect( paint.filter ).toBe( 'none' );
		expect( paint.shadow ).not.toMatch( /\d+px \d+px [1-9]\d*px/ );
		const colors = await page.locator( '.cr-favorite-chart *' ).evaluateAll( ( els ) => [ ...new Set( els.flatMap( ( el ) => { const s = getComputedStyle( el ); return [ s.color, s.backgroundColor, s.borderTopColor ]; } ) ) ] );
		expect( colors ).not.toContain( 'rgb(1, 62, 172)' );
		const tilts = await page.locator( '.cr-fav-star' ).evaluateAll( ( els ) => els.map( ( el ) => el.getAttribute( 'style' ) ) );
		for ( let i = 1; i < tilts.length; i++ ) {
			expect( tilts[ i ], `star ${ i } leans differently from star ${ i - 1 }` ).not.toBe( tilts[ i - 1 ] );
		}
	} );

	for ( const scheme of [ 'light', 'dark' ] ) {
		test( `focus (${ scheme }): a 3px yellow ring and a 7px violet halo outline the clipping, the halo stays inside the band`, async ( { page } ) => {
			await page.setViewportSize( { width: 1440, height: 900 } );
			await visit( page, ARCHIVE, scheme );
			await page.locator( '.cr-fav-title a' ).first().focus();
			await page.keyboard.press( 'Shift+Tab' );
			await page.keyboard.press( 'Tab' );
			const focus = await page.locator( '.cr-fav-item' ).first().evaluate( ( el ) => {
				const clip = el.querySelector( '.cr-fav-clipping' );
				const style = getComputedStyle( clip );
				const band = clip.closest( '.pkiw-group' ).getBoundingClientRect();
				const box = clip.getBoundingClientRect();
				return { outline: `${ style.outlineWidth } ${ style.outlineStyle } ${ style.outlineColor }`, shadow: style.boxShadow, insideBand: box.left - 7 >= band.left && box.right + 7 <= band.right };
			} );
			expect( focus.outline ).toBe( `3px solid ${ YELLOW }` );
			expect( focus.shadow ).toContain( VIOLET );
			expect( focus.shadow ).toMatch( /0px 0px 0px 7px/ );
			expect( focus.insideBand ).toBe( true );
		} );

		test( `contrast (${ scheme }): axe finds no color-contrast violations`, async ( { page } ) => {
			await visit( page, ARCHIVE, scheme );
			expect( await axe( page ) ).toEqual( [] );
		} );
	}

	test( 'reflow: no horizontal scroll at 320px, long titles wrap, stars stay inside their band', async ( { page } ) => {
		await page.setViewportSize( { width: 320, height: 800 } );
		await visit( page, `${ ARCHIVE }page/2/` );
		expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ) ).toBe( true );
		const long = page.locator( '.cr-fav-title a:has-text("Supercalifragilisticexpialidocioustype")' ).first();
		await expect( long ).toBeVisible();
		expect( await long.evaluate( ( el ) => { const s = getComputedStyle( el.closest( '.cr-fav-title' ) ); return { overflow: s.overflowWrap, ellipsis: s.textOverflow }; } ) ).toEqual( { overflow: 'anywhere', ellipsis: 'clip' } );
		const outside = await page.locator( '.cr-fav-star' ).evaluateAll( ( stars ) => stars.filter( ( star ) => { const b = star.closest( '.pkiw-group' ).getBoundingClientRect(); const r = star.getBoundingClientRect(); return r.left < b.left || r.right > b.right || r.top < b.top; } ).length );
		expect( outside ).toBe( 0 );
	} );

	test( 'forced colors, print and reduced motion', async ( { page } ) => {
		await visit( page, ARCHIVE );
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await page.locator( '.cr-favorite-chart .pkiw-group' ).first().evaluate( ( band ) => ( {
			border: getComputedStyle( band ).borderTopWidth + ' ' + getComputedStyle( band ).borderTopStyle,
			star: getComputedStyle( band.querySelector( '.cr-fav-star__shape' ) ).display,
			constellation: getComputedStyle( band, '::before' ).display,
			heading: band.querySelector( '.pkiw-group__heading' ).textContent.trim().length > 0,
		} ) );
		expect( forced.border ).toBe( '2px solid' );
		expect( forced.star ).toBe( 'none' );
		expect( forced.constellation ).toBe( 'none' );
		expect( forced.heading ).toBe( true );
		await page.emulateMedia( { forcedColors: 'none', media: 'print' } );
		expect( await page.locator( '.cr-fav-star__shape' ).first().evaluate( ( el ) => getComputedStyle( el ).display ) ).toBe( 'none' );
		await page.emulateMedia( { media: 'screen', reducedMotion: 'reduce' } );
		expect( await page.locator( '.cr-fav-item' ).first().evaluate( ( el ) => getComputedStyle( el ).animationName ) ).toBe( 'none' );
	} );
} );

test.describe( 'archive states', () => {
	test( 'empty: no bands, no stars, no pager, the empty line shows', async ( { page } ) => {
		test.skip( 'empty' !== STATE, 'CR_FAV_STATE is not empty' );
		await visit( page, ARCHIVE );
		await expect( page.locator( '.pkiw-group' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-fav-star' ) ).toHaveCount( 0 );
		await expect( page.locator( 'nav.wp-block-query-pagination' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-archive-stream__empty' ) ).toBeVisible();
	} );

	test( 'lone: one band, one clipping, one row high, no pager', async ( { page } ) => {
		test.skip( 'lone' !== STATE, 'CR_FAV_STATE is not lone' );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, ARCHIVE );
		await expect( page.locator( '.pkiw-group' ) ).toHaveCount( 1 );
		await expect( page.locator( '.pkiw-group__items > li' ) ).toHaveCount( 1 );
		await expect( page.locator( 'nav.wp-block-query-pagination' ) ).toHaveCount( 0 );
		const rows = await page.locator( '.pkiw-group__items' ).evaluate( ( el ) => getComputedStyle( el ).gridTemplateRows.split( ' ' ).length );
		expect( rows ).toBe( 1 );
	} );

	test( 'large: the pager shows an ellipsis separator', async ( { page } ) => {
		test.skip( 'large' !== STATE, 'CR_FAV_STATE is not large' );
		await visit( page, ARCHIVE );
		await expect( page.locator( 'nav.wp-block-query-pagination span.page-numbers.dots' ).first() ).toHaveText( '…' );
	} );
} );

test.describe( 'single', () => {
	test.skip( 'full' !== STATE, 'the full fixture set is not seeded' );

	test( 'F01: one h1 with the exact title outside the h-cite, three rows, one outbound link', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, await permalink( page, 'Field Guide to Quiet Software' ) );
		const h1 = page.locator( 'h1' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1 ).toHaveText( 'Field Guide to Quiet Software' );
		expect( await h1.evaluate( ( el ) => ( { inLink: Boolean( el.closest( 'a' ) ), inCite: Boolean( el.closest( '.h-cite' ) ), color: getComputedStyle( el ).color, style: getComputedStyle( el ).fontStyle } ) ) ).toEqual( { inLink: false, inCite: false, color: VIOLET, style: 'normal' } );
		for ( const gone of [ '.single-post__header', '.single-post__featured', '.single-post__format-avatar' ] ) {
			await expect( page.locator( gone ) ).toHaveCount( 0 );
		}
		await expect( page.locator( '.cr-fav-row--author dd' ) ).toContainText( 'Rowan Vale' );
		await expect( page.locator( '.cr-fav-row--favorited time' ) ).toHaveAttribute( 'datetime', '2026-08-14' );
		await expect( page.locator( '.cr-fav-row--favorited time' ) ).toHaveText( 'August 14, 2026' );
		const source = page.locator( 'a[href="https://notes.example.com/quiet-software"]' );
		await expect( source ).toHaveCount( 1 );
		await expect( source ).toHaveAttribute( 'aria-label', 'notes.example.com (opens in a new tab)' );
		expect( await source.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer nofollow' );
		const text = await page.locator( 'main' ).innerText();
		for ( const banned of [ 'View favorite source', 'Curated favorite', 'Flowchart preview.', 'SAMPLE CONTENT' ] ) {
			expect( text ).not.toContain( banned );
		}
		await expect( page.locator( 'main figcaption, main .cr-fav-sheet button' ) ).toHaveCount( 0 );
	} );

	test( 'F01: the picture prints once, eager, with the stored alt; the record sits left of it from 64rem', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, await permalink( page, 'Field Guide to Quiet Software' ) );
		const img = page.locator( '.cr-fav-sheet__img' );
		await expect( img ).toHaveCount( 1 );
		expect( await img.getAttribute( 'alt' ) ).toBe( 'A flowchart of three yes-or-no questions' );
		expect( await img.getAttribute( 'loading' ) ).toBeNull();
		expect( await img.getAttribute( 'fetchpriority' ) ).toBe( 'high' );
		const left = await page.locator( '.cr-fav-sheet__record' ).evaluate( ( el ) => el.getBoundingClientRect().right );
		const right = await page.locator( '.cr-fav-sheet__mount' ).evaluate( ( el ) => el.getBoundingClientRect().left );
		expect( left ).toBeLessThanOrEqual( right );
		await page.setViewportSize( { width: 768, height: 900 } );
		const stacked = await page.evaluate( () => document.querySelector( '.cr-fav-sheet__record' ).getBoundingClientRect().bottom <= document.querySelector( '.cr-fav-sheet__mount' ).getBoundingClientRect().top + 1 );
		expect( stacked, 'one column below 64rem' ).toBe( true );
	} );

	test( 'F02, F03, F06, F07: the cited title prints once only when it differs; no second heading', async ( { page } ) => {
		const cases = [
			[ 'Why this zine stuck with me', 'Small Press Almanac, Issue 3', true ],
			[ 'Small Web Directory', 'small web directory', false ],
			[ 'Quiet Hours Handbook', 'Quiet Hours Handbook', false ],
			[ 'blog.example.net', 'Seed Meta Name', true ],
		];
		for ( const [ title, cited, prints ] of cases ) {
			await visit( page, await permalink( page, title ) );
			await expect( page.locator( 'h1' ), `${ title }: h1` ).toHaveText( title );
			expect( await page.locator( '.cr-fav-sheet__cited' ).count(), `${ title }: cited title` ).toBe( prints ? 1 : 0 );
			expect( await page.locator( 'main h2, main h3' ).filter( { hasText: cited } ).count(), `${ title }: no second heading` ).toBe( 0 );
		}
	} );

	test( 'untitled F06 and F07 give the H1 a synthetic name without p-name', async ( { page } ) => {
		for ( const title of [ 'Quiet Hours Handbook', 'blog.example.net' ] ) {
			await visit( page, await permalink( page, title ) );
			expect( await page.locator( 'h1.cr-fav-sheet__title.p-name' ).count() ).toBe( 0 );
		}
	} );

	test( 'F04 and F05: no Favorited row for a bad day, no Source row and no favorite-of without a URL', async ( { page } ) => {
		await visit( page, await permalink( page, 'Notes on Patient Tools' ) );
		await expect( page.locator( '.cr-fav-row--favorited' ) ).toHaveCount( 0 );
		await visit( page, await permalink( page, 'Kitchen Radio Hour' ) );
		await expect( page.locator( '.cr-fav-row--source' ) ).toHaveCount( 0 );
		await expect( page.locator( '.u-favorite-of' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-fav-sheet a[target="_blank"]' ) ).toHaveCount( 0 );
		expect( await page.locator( 'dt' ).allTextContents() ).toEqual( [ 'By' ] );
	} );

	test( 'F09 and F10: the card-less branches keep their content in the sheet with one favorite-of', async ( { page } ) => {
		await visit( page, await permalink( page, 'Seed Recipe Index' ) );
		await expect( page.locator( '.cr-fav-sheet h1' ) ).toHaveText( 'Seed Recipe Index' );
		await expect( page.locator( '.cr-fav-sheet .cr-fav-row--source a' ) ).toHaveCount( 1 );
		expect( await page.locator( '.cr-fav-row--source a' ).getAttribute( 'class' ) ).not.toContain( 'u-url' );
		await expect( page.locator( 'data.u-favorite-of, .u-favorite-of' ) ).toHaveCount( 1 );
		await visit( page, await permalink( page, 'Favorite, June 24, 2026' ) );
		await expect( page.locator( '.cr-fav-sheet h1' ) ).toHaveText( 'Favorite, June 24, 2026' );
		await expect( page.locator( 'dl.cr-fav-rows' ) ).toHaveCount( 0 );
		await expect( page.locator( 'a[href="https://blog.example.net/f10"]' ) ).toHaveCount( 1 );
	} );

	test( 'F11: a broken remote picture fails cleanly with no empty frame; F12 shows the featured picture once', async ( { page } ) => {
		await visit( page, await permalink( page, 'Broken Mount Sample' ) );
		await expect( page.locator( '.cr-fav-sheet__mount' ) ).toHaveClass( /cr-cover--failed/ );
		expect( await page.locator( '.cr-fav-sheet__mount' ).evaluate( ( el ) => getComputedStyle( el ).display ) ).toBe( 'none' );
		await visit( page, await permalink( page, 'Two Pictures Sample' ) );
		await expect( page.locator( '.cr-fav-sheet__img' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-fav-sheet__img' ) ).toHaveAttribute( 'src', /seed-259-b/ );
	} );

	test( 'tape word, star and tape clear the H1, and the page has no horizontal scroll at 375 and 320', async ( { page } ) => {
		for ( const width of [ 1440, 375, 320 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await visit( page, await permalink( page, 'Field Guide to Quiet Software' ) );
			const tape = await page.locator( '.cr-fav-sheet__tape .pk-kindlabel' ).evaluate( ( el ) => ( { text: el.textContent.trim(), family: getComputedStyle( el ).fontFamily, size: parseFloat( getComputedStyle( el ).fontSize ), bg: getComputedStyle( el.parentElement ).backgroundColor } ) );
			expect( tape.text ).toBe( 'Favorite' );
			expect( tape.family ).toMatch( /^"?Rock Salt/ );
			expect( tape.size ).toBeGreaterThanOrEqual( 16 );
			const clear = await page.evaluate( () => {
				const title = document.querySelector( '.cr-fav-sheet__title' ).getBoundingClientRect();
				return [ '.cr-fav-sheet__star', '.cr-fav-sheet__tape' ].every( ( selector ) => document.querySelector( selector ).getBoundingClientRect().bottom <= title.top + 2 );
			} );
			expect( clear, `${ width }: star and tape sit above the h1` ).toBe( true );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ) ).toBe( true );
		}
	} );

	for ( const scheme of [ 'light', 'dark' ] ) {
		test( `sheet colors (${ scheme }) stay put and axe finds no contrast violations`, async ( { page } ) => {
			await visit( page, await permalink( page, 'Field Guide to Quiet Software' ), scheme );
			const colors = await page.locator( '.cr-fav-sheet' ).evaluate( ( el ) => ( { bg: getComputedStyle( el ).backgroundColor, label: getComputedStyle( el.querySelector( 'dt' ) ).color, link: getComputedStyle( el.querySelector( '.cr-fav-row a' ) ).color } ) );
			expect( colors ).toEqual( { bg: 'rgb(255, 255, 255)', label: 'rgb(61, 82, 125)', link: 'rgb(18, 103, 130)' } );
			expect( await axe( page ) ).toEqual( [] );
		} );
	}

	test( 'post navigation follows the comments with Previous and Next chips scoped to favorites', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, await permalink( page, 'Field Guide to Quiet Software' ) );
		const nav = page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		await expect( nav ).toHaveCount( 1 );
		for ( const link of await nav.locator( 'a' ).all() ) {
			expect( ( await link.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		}
		await expect( nav.locator( '.post-navigation-link-previous' ) ).toContainText( 'Previous' );
		await expect( nav.locator( '.post-navigation-link-next' ) ).toContainText( 'Next' );
	} );

	test( 'microformats: an h-entry named by the H1 with a favorite-of h-cite that keeps its URL and author', async ( { page } ) => {
		await visit( page, await permalink( page, 'Field Guide to Quiet Software' ) );
		const mf = await page.evaluate( () => {
			const entry = document.querySelector( '.h-entry' );
			const cite = entry.querySelector( '.h-cite.u-favorite-of' );
			return { name: entry.querySelector( '.p-name' ).textContent.trim(), cite: Boolean( cite ), url: cite?.querySelector( '.u-url' )?.getAttribute( 'href' ), author: cite?.querySelector( '.p-author .p-name' )?.textContent.trim(), published: Boolean( entry.querySelector( '.dt-published' ) ) };
		} );
		expect( mf ).toEqual( { name: 'Field Guide to Quiet Software', cite: true, url: 'https://notes.example.com/quiet-software', author: 'Rowan Vale', published: true } );
	} );

	test( 'S3 (protected): the template header and its visible h1 stay; no sheet', async ( { page } ) => {
		await visit( page, STREAM );
		const link = page.locator( 'a:text-is("Locked Favorite Sample")' ).first();
		test.skip( 0 === await link.count(), 'S3 is not on the first Stream page' );
		await visit( page, await link.getAttribute( 'href' ) );
		await expect( page.locator( '.cr-fav-sheet' ) ).toHaveCount( 0 );
		const title = page.locator( 'h1' ).first();
		await expect( title ).toBeVisible();
		expect( ( await title.boundingBox() ).width ).toBeGreaterThan( 1 );
	} );
} );

test.describe( 'stream', () => {
	test.skip( 'full' !== STATE, 'the full fixture set is not seeded' );

	test( 'S1, S2 and S4: one link each named by the title, a star label, a date, and nothing else', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, STREAM );
		const cards = page.locator( 'article.cr-fav-card' );
		test.skip( 0 === await cards.count(), 'no favorite on the first Stream page' );
		const expected = [ 'Field Notes on Slow Feeds', 'zine.example.org' ];
		const names = [];
		for ( const card of await cards.all() ) {
			await card.scrollIntoViewIfNeeded();
			const links = await visibleLinks( card );
			expect( links.count, 'one visible link' ).toBe( 1 );
			expect( links.stray ).toBe( 0 );
			expect( links.targets[ 0 ], 'no new-tab link' ).toBeNull();
			names.push( links.names[ 0 ] );
			await expect( card.locator( '.cr-fav-star .pk-kindlabel' ) ).toHaveText( 'Favorite' );
			const absent = await card.evaluate( ( el ) => [ 'img', '.pk-note', '.pk-excerpt', '.pk-badge', 'a[target="_blank"]', '.pk-meta' ].filter( ( selector ) => el.querySelector( selector ) ) );
			expect( absent, 'cut in markup' ).toEqual( [] );
			expect( await card.locator( 'time.dt-published' ).count() ).toBe( 1 );
			const hit = await card.locator( 'a' ).first().evaluate( ( a ) => {
				const r = a.closest( '.cr-fav-card__clipping' ).getBoundingClientRect();
				// Inset 10% so a leaning card's bounding-box corners, which fall outside the rotated shape, are not probed.
				return [ [ r.left + r.width * 0.1, r.top + r.height * 0.1 ], [ r.left + r.width / 2, r.top + r.height / 2 ], [ r.right - r.width * 0.1, r.bottom - r.height * 0.1 ] ].every( ( [ x, y ] ) => a.contains( document.elementFromPoint( x, y ) ) );
			} );
			expect( hit, 'the clipping area hits the link' ).toBe( true );
			const starHit = await card.locator( 'a' ).first().evaluate( ( a ) => {
				const r = a.closest( 'article.cr-fav-card' ).querySelector( '.cr-fav-star' ).getBoundingClientRect();
				return a.contains( document.elementFromPoint( r.left + r.width / 2, r.top + r.height / 2 ) );
			} );
			expect( starHit, 'the star center hits the link' ).toBe( true );
		}
		for ( const name of expected ) {
			expect( names ).toContain( name );
		}
		await expect( page.locator( 'article.cr-fav-card:has(a:text-is("zine.example.org")) data.u-url[value="https://zine.example.org/issue-9"]' ) ).toHaveCount( 1 );
	} );

	test( 'none of the retired legacy colors is computed on a favorite Stream card', async ( { page } ) => {
		await visit( page, STREAM );
		const retired = [ 'rgb(150, 105, 10)', 'rgb(230, 192, 90)', 'rgb(241, 228, 192)', 'rgb(254, 247, 224)' ];
		const found = await page.locator( 'article.cr-fav-card, article.cr-fav-card *' ).evaluateAll( ( els, list ) => els.flatMap( ( el ) => { const s = getComputedStyle( el ); return [ s.color, s.backgroundColor, s.borderTopColor, s.outlineColor ].filter( ( value ) => list.includes( value ) ); } ), retired );
		expect( found ).toEqual( [] );
	} );

	test( 'size and layers: the star sits inside the card, two rotated layers at most, one column at 320 and 375', async ( { page } ) => {
		await visit( page, STREAM );
		const card = page.locator( 'article.cr-fav-card' ).first();
		test.skip( 0 === await card.count(), 'no favorite on the first Stream page' );
		const geometry = await card.evaluate( ( el ) => {
			const c = el.getBoundingClientRect();
			const s = el.querySelector( '.cr-fav-star' ).getBoundingClientRect();
			const layers = [ el, el.querySelector( '.cr-fav-star' ) ].filter( ( node ) => 'none' !== getComputedStyle( node ).transform ).length;
			const label = parseFloat( getComputedStyle( el.querySelector( '.pk-kindlabel' ) ).fontSize );
			return { inside: s.left >= c.left - 2 && s.right <= c.right + 2 && s.top >= c.top - 2, layers, label };
		} );
		expect( geometry.inside ).toBe( true );
		expect( geometry.layers ).toBeLessThanOrEqual( 2 );
		expect( geometry.label ).toBeGreaterThanOrEqual( 16 );
		for ( const width of [ 375, 320 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), `${ width }: no horizontal scroll` ).toBe( true );
		}
	} );

	test( 'dark, forced colors and focus', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, STREAM, 'dark' );
		const card = page.locator( 'article.cr-fav-card' ).first();
		test.skip( 0 === await card.count(), 'no favorite on the first Stream page' );
		expect( await card.locator( '.cr-fav-card__clipping' ).evaluate( ( el ) => getComputedStyle( el ).backgroundColor ) ).toBe( IVORY );
		expect( await axe( page ) ).toEqual( [] );
		await card.locator( 'a' ).first().focus();
		await page.keyboard.press( 'Shift+Tab' );
		await page.keyboard.press( 'Tab' );
		const ring = await card.locator( '.cr-fav-card__clipping' ).evaluate( ( el ) => `${ getComputedStyle( el ).outlineWidth } ${ getComputedStyle( el ).outlineColor }` );
		expect( ring ).toBe( `3px ${ YELLOW }` );
		await page.emulateMedia( { forcedColors: 'active' } );
		expect( await card.locator( '.cr-fav-card__clipping' ).evaluate( ( el ) => getComputedStyle( el ).borderTopWidth ) ).toBe( '2px' );
		await expect( card.locator( '.pk-kindlabel' ) ).toBeVisible();
	} );
} );
