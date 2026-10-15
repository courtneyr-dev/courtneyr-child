// `CR_SPEC=repost-fidelity.spec.mjs`: PKIW #255 repost pages against the approved mockups and the
// decision's acceptance contract: the month spreads on /kind/repost/, the single clipping and the
// Stream clipping. Read-only GETs; needs a running site seeded by the vault's
// assets/255-reposts/seed-255.php (CR_BASE_URL, and CR_ALLOW_REMOTE=1 plus CR_USER_AGENT for a
// Pantheon sandbox). Fixtures are found by their slug, cr-seed-255-<key>, so any permalink structure
// works. Paths can be pointed elsewhere with CR_REPOST_ARCHIVE_PATH and CR_REPOST_STREAM_PATHS.
//
//   CR_BASE_URL=http://localhost:8892 CR_SPEC=repost-fidelity.spec.mjs \
//     npx playwright test --config tests/playwright/playwright.config.mjs
//
// Link counts follow W3-G2: an anchor counts when it has an href and sits outside any [hidden]
// element, and every a[tabindex="-1"] has a [hidden] ancestor.
import { test, expect } from '@playwright/test';

const ARCHIVE = ( process.env.CR_REPOST_ARCHIVE_PATH || '/kind/repost/' ).replace( /\/?$/, '/' );
const ARCHIVE_PAGE_2 = `${ ARCHIVE }page/2/`;
const STREAM_PATHS = ( process.env.CR_REPOST_STREAM_PATHS || '/stream/,/' ).split( ',' );

const slugOf = ( key ) => `cr-seed-255-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const SPREAD = '.cr-repost-zine__spreads';
const GRAY = 'rgb(235, 235, 235)';
const VIOLET = 'rgb(36, 28, 74)';
const IVORY = 'rgb(251, 250, 245)';
const YELLOW = 'rgb(255, 183, 3)';
const DARK_TAPE = 'rgb(74, 63, 28)';
const TILT_DEGREES = [ -1.2, 1.5, -2.5, 2.8 ];

// What the plan's page maps hold at 12 a page, logged out. The first section is the seeding month.
const PAGE_1 = [ [ null, 4 ], [ 'August 2026', 6 ], [ 'July 2026', 2 ] ];
const PAGE_2 = [ [ 'July 2026', 3 ], [ 'June 2026', 5 ], [ 'May 2026', 1 ] ];

const parseRgb = ( value ) => {
	const m = /rgba?\(\s*(\d+)[ ,]+(\d+)[ ,]+(\d+)/.exec( value || '' );
	return m ? [ Number( m[ 1 ] ), Number( m[ 2 ] ), Number( m[ 3 ] ) ] : null;
};
const luminance = ( [ r, g, b ] ) => {
	const channel = ( c ) => ( ( c /= 255 ) <= 0.03928 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4 );
	return 0.2126 * channel( r ) + 0.7152 * channel( g ) + 0.0722 * channel( b );
};
const contrast = ( a, b ) => {
	const [ hi, lo ] = [ luminance( parseRgb( a ) ), luminance( parseRgb( b ) ) ].sort( ( x, y ) => y - x );
	return ( hi + 0.05 ) / ( lo + 0.05 );
};
const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );
const monthIndex = ( label ) => {
	const [ name, year ] = label.split( ' ' );
	return Number( year ) * 12 + [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ].indexOf( name );
};

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width < 700 ? 844 : 900 } );
	const response = await page.goto( path, { waitUntil: 'load' } );
	return response;
}

// The li for one fixture on an archive or Stream page, found by its permalink's slug.
const itemFor = ( page, key ) => page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

// The count of anchors that count under W3-G2, and the tabindex check, run inside the page.
const linkReport = ( locator ) =>
	locator.evaluate( ( root ) => {
		const visible = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) );
		const stray = [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) );
		return { hrefs: visible.map( ( a ) => a.getAttribute( 'href' ) ), texts: visible.map( ( a ) => a.textContent.trim() ), stray: stray.length };
	} );

async function seeded( page, path = ARCHIVE ) {
	const response = await open( page, path );
	test.skip( response.status() === 404, `no page at ${ path }` );
	test.skip( 0 === ( await page.locator( `a[href*="cr-seed-255-"]` ).count() ), 'the #255 fixtures are not seeded here (seed-255.php)' );
}

// --- Archive -----------------------------------------------------------------------------------

test( 'the empty archive shows the header and the shipped no-results copy, with no spread or pager', async ( { page } ) => {
	await open( page, ARCHIVE );
	test.skip( 0 < ( await page.locator( 'li.wp-block-post' ).count() ), 'the archive has posts; this runs before seeding' );
	await expect( page.locator( 'main h1' ) ).toHaveText( /Repost/ );
	await expect( page.locator( '.wp-block-term-description' ) ).toContainText( 'Reshare of external content with attribution.' );
	await expect( page.locator( 'main' ) ).toContainText( 'Nothing here yet. Browse every kind and format below, or jump to the Stream.' );
	await expect( page.locator( '#browse-all, .cr-browse-all' ).first() ).toBeVisible();
	expect( await page.locator( `${ SPREAD }, section.pkiw-group, .wp-block-query-pagination` ).count() ).toBe( 0 );
} );

test( 'the header keeps the identity pieces: one h1, six tiles, the description, the marker and an inert badge', async ( { page } ) => {
	await seeded( page );
	const h1 = page.locator( 'main h1' );
	await expect( h1 ).toHaveCount( 1 );
	await expect( h1.locator( '.cr-cutout__text' ) ).toHaveText( 'Repost' );
	const tiles = h1.locator( '.cr-cutout__tile' );
	expect( await tiles.count(), 'one tile per grapheme of Repost' ).toBe( 6 );
	expect( await h1.locator( '.cr-cutout__tiles' ).getAttribute( 'aria-hidden' ) ).toBe( 'true' );
	const rotations = await tiles.evaluateAll( ( els ) => els.map( ( el ) => getComputedStyle( el ).transform ) );
	for ( let i = 1; i < rotations.length; i++ ) {
		expect( rotations[ i ], `tiles ${ i } and ${ i + 1 } do not share a rotation` ).not.toBe( rotations[ i - 1 ] );
	}
	await expect( page.locator( '.wp-block-term-description' ) ).toHaveText( /Reshare of external content with attribution\./ );
	await expect( page.locator( 'hr.is-style-cr-marker-bar-short' ) ).toHaveCount( 1 );
	const badge = page.locator( '.cr-archive-identity__glyph' );
	expect( await badge.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
	expect( await page.locator( '.cr-archive-identity a, .cr-archive-identity [tabindex]' ).count(), 'the badge is not a link and has no tab stop' ).toBe( 0 );
	expect( ( await page.locator( 'main' ).innerText() ).toUpperCase() ).not.toContain( 'SAMPLE CONTENT' );
	expect( await page.locator( '.cr-tape-label' ).count(), 'no Stream tape in the header' ).toBe( 0 );
} );

for ( const [ path, map ] of [ [ ARCHIVE, PAGE_1 ], [ ARCHIVE_PAGE_2, PAGE_2 ] ] ) {
	test( `${ path } holds the planned month sections, named by their headings`, async ( { page } ) => {
		await seeded( page, path );
		const sections = await page.locator( `${ SPREAD } > section.pkiw-group` ).evaluateAll( ( els ) =>
			els.map( ( el ) => {
				const heading = el.querySelector( ':scope > h2' );
				return { month: heading.textContent.trim(), id: heading.id, labelledby: el.getAttribute( 'aria-labelledby' ), items: el.querySelectorAll( ':scope > ul > li.wp-block-post' ).length };
			} )
		);
		expect( sections.length, 'sections' ).toBe( map.length );
		sections.forEach( ( section, i ) => {
			if ( null !== map[ i ][ 0 ] ) {
				expect( section.month, `section ${ i + 1 } month` ).toBe( map[ i ][ 0 ] );
			} else {
				expect( section.month, 'the seeding month' ).toMatch( /^[A-Z][a-z]+ \d{4}$/ );
				expect( monthIndex( section.month ), 'the seeding month is after August 2026' ).toBeGreaterThan( monthIndex( 'August 2026' ) );
			}
			expect( section.items, `${ section.month } items` ).toBe( map[ i ][ 1 ] );
			expect( section.id, `${ section.month } heading id` ).not.toBe( '' );
			expect( section.labelledby, `${ section.month } section is named by its h2` ).toBe( section.id );
		} );
		expect( await page.locator( `${ SPREAD } li.wp-block-post` ).count() ).toBe( map.reduce( ( sum, [ , count ] ) => sum + count, 0 ) );
	} );
}

test( 'the native page URL answers for page 2', async ( { page } ) => {
	const response = await open( page, ARCHIVE_PAGE_2 );
	test.skip( response.status() === 404 && 0 === ( await page.locator( 'a[href*="cr-seed-255-"]' ).count() ), 'nothing seeded' );
	expect( response.status() ).toBe( 200 );
} );

test( 'each item has one visible link in an h3, one credit and none of the cut parts', async ( { page } ) => {
	for ( const path of [ ARCHIVE, ARCHIVE_PAGE_2 ] ) {
		await seeded( page, path );
		const items = page.locator( `${ SPREAD } li.wp-block-post` );
		const count = await items.count();
		for ( let i = 0; i < count; i++ ) {
			const item = items.nth( i );
			const report = await linkReport( item );
			expect( report.hrefs.length, `${ path } item ${ i + 1 }: one anchor outside [hidden]` ).toBe( 1 );
			expect( report.stray, `${ path } item ${ i + 1 }: no visible tabindex=-1 link` ).toBe( 0 );
			const heading = item.locator( 'h3, h2, h4' );
			expect( await heading.count(), `${ path } item ${ i + 1 }: one heading` ).toBe( 1 );
			expect( await heading.locator( 'a' ).count(), 'the heading holds the link' ).toBe( 1 );
			const visibleText = ( await item.innerText() ).trim();
			for ( const gone of [ 'REPOSTED', 'View the original' ] ) {
				expect( visibleText, `${ path } item ${ i + 1 }: no ${ gone.trim() }` ).not.toContain( gone );
			}
			expect( await item.locator( 'time:visible, .pk-kindlabel:visible, .pk-note:visible, .pk-badge:visible, svg:visible' ).count(), `${ path } item ${ i + 1 }: no visible date, label, description or icon` ).toBe( 0 );
			expect( await item.locator( 'img[alt]:not([alt=""])' ).count(), `${ path } item ${ i + 1 }: image alt is empty` ).toBe( 0 );
		}
	}
} );

test( 'names and credits: the cited title, the host fallback, the protected title and the disambiguated pair', async ( { page } ) => {
	await seeded( page );
	const a01 = itemFor( page, 'A01' );
	await expect( a01.locator( 'h3 a' ) ).toHaveText( 'Paper Trails for the Web' );
	await expect( a01.locator( '.cr-repost-clip__credit' ) ).toHaveText( 'Jo Example' );
	const a04 = itemFor( page, 'A04' );
	const a05 = itemFor( page, 'A05' );
	await expect( a04.locator( '.cr-repost-clip__credit' ) ).toHaveText( 'Sample Author' );
	await expect( a05.locator( '.cr-repost-clip__credit' ) ).toHaveText( 'blog.example.net' );
	const [ nameA04, nameA05 ] = await Promise.all( [ a04.locator( 'h3 a' ).ariaSnapshot(), a05.locator( 'h3 a' ).ariaSnapshot() ] );
	expect( nameA04, 'A04 and A05 have different accessible names' ).not.toBe( nameA05 );
	expect( nameA04 ).toContain( 'Sample Author' );
	expect( nameA05 ).toContain( 'blog.example.net' );
	await expect( itemFor( page, 'A06' ).locator( 'h3 a' ), 'a URL-only repost is named by its host' ).toHaveText( 'quiet.example.com' );
	await expect( itemFor( page, 'A03' ).locator( 'img' ), 'a repost with no image is text only' ).toHaveCount( 0 );
	await open( page, ARCHIVE_PAGE_2 );
	const p1 = itemFor( page, 'P1' );
	await expect( p1.locator( 'a' ) ).toHaveText( 'Protected: Locked Clipping' );
	expect( await p1.locator( 'img, .cr-repost-clip__credit' ).count(), 'the protected item shows no image or credit' ).toBe( 0 );
} );

test( 'the picture is a 1.91 to 1 frame, grayscale on the archive, eager in the first row and gone when it fails', async ( { page } ) => {
	await seeded( page );
	for ( const key of [ 'S1', 'S2' ] ) {
		const img = itemFor( page, key ).locator( 'img' );
		expect( await img.getAttribute( 'loading' ), `${ key } is eager` ).toBe( 'eager' );
		expect( await img.getAttribute( 'fetchpriority' ), `${ key } has high fetch priority` ).toBe( 'high' );
	}
	expect( await itemFor( page, 'A01' ).locator( 'img' ).getAttribute( 'loading' ), 'A01 is lazy' ).toBe( 'lazy' );
	const frame = itemFor( page, 'A04' ).locator( '.cr-repost-clip__frame' );
	const box = await frame.boundingBox();
	expect( Math.abs( box.width / box.height - 1.91 ), 'a 1.91 to 1 frame' ).toBeLessThan( 0.03 );
	const style = await frame.locator( 'img' ).evaluate( ( img ) => {
		const s = getComputedStyle( img );
		return { fit: s.objectFit, filter: s.filter };
	} );
	expect( style.fit ).toBe( 'cover' );
	expect( style.filter, 'the one S8 exception' ).toBe( 'grayscale(1)' );
	// A07 names a host that does not resolve: the frame goes and a text clipping stays.
	await page.waitForTimeout( 800 );
	const failed = itemFor( page, 'A07' );
	expect( await failed.locator( '.cr-repost-clip__frame:visible' ).count(), 'no empty frame for a failed image' ).toBe( 0 );
	await expect( failed.locator( 'h3 a' ) ).toBeVisible();
} );

test( 'a long title wraps in full on the archive', async ( { page } ) => {
	for ( const width of [ 1440, 320 ] ) {
		await open( page, ARCHIVE, width );
		test.skip( 0 === ( await page.locator( 'a[href*="cr-seed-255-"]' ).count() ), 'not seeded' );
		const title = itemFor( page, 'A02' ).locator( 'h3' );
		const info = await title.evaluate( ( el ) => {
			const s = getComputedStyle( el );
			return { overflow: s.textOverflow, clamp: s.webkitLineClamp, scroll: el.scrollHeight, client: el.clientHeight };
		} );
		expect( info.overflow, `${ width }: no ellipsis` ).not.toBe( 'ellipsis' );
		expect( info.clamp, `${ width }: no clamp` ).toBe( 'none' );
		expect( info.scroll, `${ width }: the whole title shows` ).toBeLessThanOrEqual( info.client + 1 );
	}
} );

for ( const [ width, columns, fold ] of [ [ 1440, 4, true ], [ 768, 2, true ], [ 375, 1, false ], [ 320, 1, false ] ] ) {
	test( `at ${ width }px the sheets run ${ columns } across, ${ fold ? 'with' : 'with no' } fold, in reading order and without sideways scroll`, async ( { page } ) => {
		await open( page, ARCHIVE, width );
		test.skip( 0 === ( await page.locator( 'a[href*="cr-seed-255-"]' ).count() ), 'not seeded' );
		const lists = page.locator( `${ SPREAD } ul.pkiw-group__items` );
		const report = await lists.evaluateAll( ( els ) =>
			els.map( ( el ) => {
				const s = getComputedStyle( el );
				const pseudo = getComputedStyle( el, '::before' );
				const rect = el.getBoundingClientRect();
				const items = [ ...el.children ].map( ( li ) => li.getBoundingClientRect() );
				return {
					columns: s.gridTemplateColumns.split( ' ' ).length,
					foldContent: pseudo.content,
					foldLeft: parseFloat( pseudo.left ),
					width: rect.width,
					tops: [ ...new Set( items.map( ( r ) => Math.round( r.top ) ) ) ].length,
					count: items.length,
					order: items.map( ( r, i ) => ( { i, top: Math.round( r.top ), left: Math.round( r.left ) } ) ),
				};
			} )
		);
		for ( const list of report ) {
			expect( list.columns, 'columns' ).toBe( columns );
			if ( fold ) {
				expect( list.foldContent, 'a fold' ).not.toBe( 'none' );
				expect( Math.abs( list.foldLeft - list.width / 2 ), 'the fold is at the sheet centre' ).toBeLessThan( 3 );
			} else {
				expect( list.foldContent, 'no fold' ).toBe( 'none' );
			}
			const sorted = [ ...list.order ].sort( ( a, b ) => a.top - b.top || a.left - b.left ).map( ( o ) => o.i );
			expect( sorted, 'DOM order is reading order' ).toEqual( list.order.map( ( o ) => o.i ) );
		}
		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		expect( overflow, 'no horizontal scroll' ).toBeLessThanOrEqual( 0 );
	} );
}

test( 'at 1440px each sheet is only as tall as its rows and trailing cells stay blank', async ( { page } ) => {
	for ( const [ path, blanks ] of [ [ ARCHIVE, [ 0, 2, 2 ] ], [ ARCHIVE_PAGE_2, [ 1, 3, 3 ] ] ] ) {
		await open( page, path, 1440 );
		test.skip( 0 === ( await page.locator( 'a[href*="cr-seed-255-"]' ).count() ), 'not seeded' );
		const sheets = await page.locator( `${ SPREAD } > section.pkiw-group` ).evaluateAll( ( els ) =>
			els.map( ( el ) => {
				const items = [ ...el.querySelectorAll( ':scope > ul > li' ) ].map( ( li ) => li.getBoundingClientRect() );
				const rows = [ ...new Set( items.map( ( r ) => Math.round( r.top ) ) ) ].length;
				const last = Math.max( ...items.map( ( r ) => r.bottom ) );
				return { rows, count: items.length, tail: el.getBoundingClientRect().bottom - last };
			} )
		);
		sheets.forEach( ( sheet, i ) => {
			expect( sheet.rows * 4 - sheet.count, `${ path } sheet ${ i + 1 } blank cells` ).toBe( blanks[ i ] );
			expect( sheet.tail, `${ path } sheet ${ i + 1 }: no filler below its last row` ).toBeLessThan( 80 );
		} );
	}
} );

test( 'clippings, headings, credits and the pager stay upright; li has no marker bar, lift or transition', async ( { page } ) => {
	await seeded( page );
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	const targets = {
		li: itemFor( page, 'A01' ),
		heading: page.locator( `${ SPREAD } h2.pkiw-group__heading` ).first(),
		credit: itemFor( page, 'A01' ).locator( '.cr-repost-clip__credit' ),
		pager: page.locator( 'nav.wp-block-query-pagination' ),
	};
	for ( const [ name, locator ] of Object.entries( targets ) ) {
		expect( await locator.evaluate( ( el ) => getComputedStyle( el ).transform ), `${ name } at rest` ).toBe( 'none' );
	}
	await targets.li.hover();
	for ( const [ name, locator ] of Object.entries( targets ) ) {
		expect( await locator.evaluate( ( el ) => getComputedStyle( el ).transform ), `${ name } on hover with reduced motion` ).toBe( 'none' );
	}
	const li = await targets.li.evaluate( ( el ) => ( { after: getComputedStyle( el, '::after' ).content, transition: getComputedStyle( el ).transitionDuration } ) );
	expect( li.after, 'no orange marker bar' ).toBe( 'none' );
	expect( parseFloat( li.transition ), 'no transition (reduced motion rounds 0s to a microsecond)' ).toBeLessThan( 0.001 );
	const clip = itemFor( page, 'A01' ).locator( '.cr-repost-clip' );
	expect( await clip.evaluate( ( el ) => getComputedStyle( el ).transform ), 'no hover lift under reduced motion' ).toBe( 'none' );
	const tape = await clip.evaluate( ( el ) => getComputedStyle( el, '::before' ).transform );
	expect( tape, 'the tape tilts by a token' ).toMatch( /^matrix\(/ );
} );

test( 'the month strip, credit and heading hold 4.5:1 on their paper', async ( { page }, testInfo ) => {
	await seeded( page );
	const colors = await page.evaluate( () => {
		const heading = document.querySelector( '.cr-repost-zine__spreads h2.pkiw-group__heading' );
		const credit = document.querySelector( '.cr-repost-clip__credit' );
		const title = document.querySelector( '.cr-repost-clip__title' );
		return {
			heading: getComputedStyle( heading ).color,
			strip: getComputedStyle( heading, '::before' ).backgroundColor,
			credit: getComputedStyle( credit ).color,
			title: getComputedStyle( title ).color,
			paper: getComputedStyle( credit.closest( '.cr-repost-clip__paper' ) ).backgroundColor,
		};
	} );
	expect( contrast( colors.heading, colors.strip ), 'month heading on its strip' ).toBeGreaterThanOrEqual( 4.5 );
	expect( contrast( colors.credit, colors.paper ), 'credit on paper' ).toBeGreaterThanOrEqual( 4.5 );
	expect( contrast( colors.title, colors.paper ), 'title on paper' ).toBeGreaterThanOrEqual( 4.5 );
	expect( colors.paper, `paper keeps its fill in ${ isDark( testInfo ) ? 'dark' : 'light' } mode` ).toBe( GRAY );
	expect( colors.title, 'ink is pinned to Russian violet' ).toBe( VIOLET );
} );

test( 'objects keep their palette fill in dark mode while the shadow and tape follow the scheme', async ( { page }, testInfo ) => {
	await seeded( page );
	const dark = isDark( testInfo );
	const probe = await page.evaluate( () => {
		const sheet = document.querySelector( '.cr-repost-zine__spreads section.pkiw-group' );
		const clip = document.querySelector( '.cr-repost-clip' );
		const li = clip.closest( 'li' );
		return {
			sheet: getComputedStyle( sheet ).backgroundColor,
			tape: getComputedStyle( clip, '::before' ).backgroundColor,
			filter: getComputedStyle( li ).filter,
			imgFilter: getComputedStyle( document.querySelector( '.cr-repost-clip__frame img' ) ).filter,
		};
	} );
	expect( probe.sheet, 'the sheet keeps its ivory fill' ).toBe( IVORY );
	if ( dark ) {
		expect( probe.tape, 'the dark tape token' ).toBe( DARK_TAPE );
		expect( probe.filter, 'hard shadows turn selective yellow' ).toContain( YELLOW );
	} else {
		expect( probe.filter, 'the hard shadow is ink' ).toContain( VIOLET );
	}
	expect( probe.imgFilter, 'images are never inverted' ).toBe( 'grayscale(1)' );
} );

test( 'the pager follows the last spread, outside it, with 44px targets and no Previous on page 1', async ( { page } ) => {
	for ( const [ path, hasPrevious ] of [ [ ARCHIVE, false ], [ ARCHIVE_PAGE_2, true ] ] ) {
		await seeded( page, path );
		const nav = page.locator( 'nav[aria-label="Pagination"]' );
		await expect( nav ).toHaveCount( 1 );
		const placement = await nav.evaluate( ( el ) => {
			const sections = [ ...document.querySelectorAll( 'section.pkiw-group' ) ];
			const last = sections[ sections.length - 1 ];
			return {
				after: !! ( last.compareDocumentPosition( el ) & Node.DOCUMENT_POSITION_FOLLOWING ),
				inside: !! el.closest( '.cr-repost-zine__spreads' ),
			};
		} );
		expect( placement.after, `${ path }: after the last section` ).toBe( true );
		expect( placement.inside, `${ path }: not inside the spreads` ).toBe( false );
		expect( await nav.locator( 'a.wp-block-query-pagination-previous' ).count(), `${ path }: Previous` ).toBe( hasPrevious ? 1 : 0 );
		expect( await nav.locator( '[aria-current="page"]' ).count(), `${ path }: current chip` ).toBe( 1 );
		const sizes = await nav.locator( 'a, .page-numbers' ).evaluateAll( ( els ) => els.map( ( el ) => el.getBoundingClientRect() ).map( ( r ) => [ r.width, r.height ] ) );
		for ( const [ w, h ] of sizes ) {
			expect( Math.min( w, h ), `${ path }: pager target size` ).toBeGreaterThanOrEqual( 44 );
		}
		const inSections = await page.locator( `${ SPREAD }` ).evaluate( ( el ) => /\bPage\b/.test( el.innerText ) || !! el.querySelector( 'a[href*="/page/"], a[href*="paged="]' ) );
		expect( inSections, `${ path }: no page count or page link inside a spread` ).toBe( false );
	}
} );

test( 'Tab skips the badge, visits the item links in order, then the pager', async ( { page } ) => {
	await seeded( page );
	await page.locator( 'main' ).evaluate( ( el ) => {
		el.setAttribute( 'tabindex', '-1' );
		el.focus();
	} );
	const visited = [];
	for ( let i = 0; i < 60; i++ ) {
		await page.keyboard.press( 'Tab' );
		const info = await page.evaluate( () => {
			const el = document.activeElement;
			return el && el.closest( 'main' ) ? { href: el.getAttribute( 'href' ) || '', badge: !! el.closest( '.cr-archive-identity' ), pager: !! el.closest( '.wp-block-query-pagination' ), item: !! el.closest( 'li.wp-block-post' ) } : null;
		} );
		if ( ! info ) {
			break;
		}
		visited.push( info );
	}
	expect( visited.some( ( v ) => v.badge ), 'the badge never takes focus' ).toBe( false );
	const items = visited.filter( ( v ) => v.item );
	expect( items.length, 'every item link is a tab stop' ).toBe( 12 );
	const dom = await page.locator( `${ SPREAD } li.wp-block-post h3 a, ${ SPREAD } li.wp-block-post h3 a, ${ SPREAD } li.wp-block-post .pk-title a` ).evaluateAll( ( els ) => els.map( ( el ) => el.getAttribute( 'href' ) ) );
	expect( items.map( ( v ) => v.href ), 'item links come in DOM order' ).toEqual( dom );
	const firstPager = visited.findIndex( ( v ) => v.pager );
	expect( firstPager, 'the pager links come after the items' ).toBeGreaterThan( visited.lastIndexOf( items[ items.length - 1 ] ) - 1 );
} );

test( 'focus draws a 3px selective-yellow ring with an ink halo round the whole clipping', async ( { page } ) => {
	await seeded( page );
	await itemFor( page, 'A01' ).locator( 'h3 a' ).focus();
	await page.keyboard.press( 'Shift+Tab' );
	await page.keyboard.press( 'Tab' );
	const ring = await itemFor( page, 'A01' ).locator( '.cr-repost-clip' ).evaluate( ( el ) => {
		const s = getComputedStyle( el );
		return { width: s.outlineWidth, style: s.outlineStyle, color: s.outlineColor, shadow: s.boxShadow };
	} );
	expect( ring.width ).toBe( '3px' );
	expect( ring.style ).toBe( 'solid' );
	expect( ring.color, 'selective yellow' ).toBe( YELLOW );
	expect( ring.shadow, 'a 7px halo' ).toMatch( /7px/ );
	const link = await itemFor( page, 'A01' ).locator( 'h3 a' ).evaluate( ( el ) => getComputedStyle( el ).outlineStyle );
	expect( link, 'the link draws no second ring' ).toBe( 'none' );
	const target = await itemFor( page, 'A01' ).locator( '.cr-repost-clip' ).boundingBox();
	expect( target.height, 'the target is at least 44px' ).toBeGreaterThanOrEqual( 44 );
} );

test( 'clicking the clipping, image included, opens the single', async ( { page } ) => {
	await seeded( page );
	// The title link's ::after covers the picture, so a real click at the picture's centre lands on the link.
	const box = await itemFor( page, 'A01' ).locator( '.cr-repost-clip__frame' ).boundingBox();
	await Promise.all( [ page.waitForURL( new RegExp( slugOf( 'A01' ) ) ), page.mouse.click( box.x + box.width / 2, box.y + box.height / 2 ) ] );
	expect( page.url() ).toContain( slugOf( 'A01' ) );
} );

test( 'forced colors: 2px outlines, no tape, fold or torn edge, and the picture stays', async ( { page } ) => {
	await seeded( page );
	await page.emulateMedia( { forcedColors: 'active' } );
	const probe = await page.evaluate( () => {
		const sheet = document.querySelector( '.cr-repost-zine__spreads section.pkiw-group' );
		const clip = document.querySelector( '.cr-repost-clip' );
		const list = sheet.querySelector( 'ul' );
		return {
			sheet: getComputedStyle( sheet ).borderTopWidth + ' ' + getComputedStyle( sheet ).borderTopStyle,
			paper: getComputedStyle( clip.querySelector( '.cr-repost-clip__paper' ) ).borderTopWidth,
			tape: getComputedStyle( clip, '::before' ).display,
			fold: getComputedStyle( list, '::before' ).display,
			strip: getComputedStyle( sheet.querySelector( 'h2' ), '::before' ).display,
			tear: getComputedStyle( clip.querySelector( '.cr-repost-clip__paper' ), '::after' ).display,
			img: getComputedStyle( clip.querySelector( 'img' ) || document.createElement( 'img' ) ).filter,
			imgShown: !! document.querySelector( '.cr-repost-clip__frame img' ),
		};
	} );
	expect( probe.sheet ).toBe( '2px solid' );
	expect( probe.paper ).toBe( '2px' );
	expect( [ probe.tape, probe.fold, probe.strip, probe.tear ], 'tape, fold, strip and tear drop out' ).toEqual( [ 'none', 'none', 'none', 'none' ] );
	expect( probe.imgShown, 'images stay' ).toBe( true );
	expect( await page.locator( '.cr-repost-clip__frame img' ).first().evaluate( ( img ) => getComputedStyle( img ).filter ), 'no grayscale under forced colors' ).toBe( 'none' );
} );

// --- Single ------------------------------------------------------------------------------------

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	test.skip( response.status() === 404, `no fixture ${ key }` );
	return page.locator( 'main article.cr-repost-clip--single' ).first();
}

test( 'every single has exactly one h1', async ( { page } ) => {
	const expected = { A11: 'Why I Kept This', A08: 'Meta Only Clipping', A12: 'Quiet Web, Loud Links', A13: 'The Untitled Repost', A06: 'quiet.example.com', A01: 'Paper Trails for the Web' };
	for ( const [ key, text ] of Object.entries( expected ) ) {
		await openSingle( page, key );
		const h1 = page.locator( 'h1' );
		expect( await h1.count(), `${ key }: one h1` ).toBe( 1 );
		expect( ( await h1.textContent() ).trim(), `${ key }: its text` ).toBe( text );
		expect( await page.locator( 'main h1.wp-block-post-title' ).count() === 1, `${ key }: core/post-title is the h1 only when it holds a title` ).toBe( key === 'A11' );
	}
	// A11's cited title stays an h2 inside the clipping.
	const a11 = await openSingle( page, 'A11' );
	await expect( a11.locator( 'h2.pk-title' ) ).toHaveText( 'Quiet Web, Loud Links' );
} );

test( 'the clipping prints From and by lines, one source link, no cut parts and a hidden card date', async ( { page } ) => {
	const a01 = await openSingle( page, 'A01' );
	await expect( a01.locator( '.cr-repost-clip__from' ) ).toHaveText( 'From blog.example.net' );
	await expect( a01.locator( '.pk-sub' ).last() ).toContainText( 'by Jo Example' );
	const report = await linkReport( a01 );
	expect( report.hrefs, 'one anchor, to the source' ).toEqual( [ 'https://blog.example.net/paper-trails' ] );
	expect( report.stray ).toBe( 0 );
	const text = await a01.innerText();
	for ( const gone of [ 'View the original', 'REPOSTED' ] ) {
		expect( text ).not.toContain( gone );
	}
	expect( await a01.locator( 'figcaption' ).count() ).toBe( 0 );
	expect( await a01.locator( '.pk-kindlabel:visible, .pk-badge:visible' ).count() ).toBe( 0 );
	expect( await page.locator( 'main :is(.single-post__kind, .cr-chip)' ).filter( { hasText: /^Repost$/ } ).count(), 'the kind word prints once, in the lede chip' ).toBe( 1 );
	const a14 = await openSingle( page, 'A14' );
	expect( await a14.locator( 'a[href^="https://example.com/a14"]' ).getAttribute( 'rel' ) ).toBe( 'noopener noreferrer nofollow ugc' );
	expect( await a14.locator( '.pk-meta[hidden] time.dt-published' ).count(), 'the card date is hidden but kept' ).toBe( 1 );
	expect( await page.locator( '.single-post__date' ).count(), 'one visible date, in the lede' ).toBe( 1 );
	const a16 = await openSingle( page, 'A16' );
	expect( ( await a16.innerText() ) ).toContain( 'by R. Placeholder' );
	expect( await a16.locator( '.cr-repost-clip__from' ).count(), 'no host line without a URL' ).toBe( 0 );
	expect( ( await linkReport( a16 ) ).hrefs, 'no source anchor without a URL' ).toEqual( [] );
} );

test( 'the picture prints once: featured is dropped when the card has an image, kept when it does not', async ( { page } ) => {
	const a01 = await openSingle( page, 'A01' );
	expect( await page.locator( 'main .wp-block-post-featured-image' ).count(), 'A01 has no featured block' ).toBe( 0 );
	expect( await page.locator( 'main .single-post__content img' ).count(), 'one picture in the content' ).toBe( 1 );
	expect( await a01.locator( 'img' ).count() ).toBe( 1 );
	const a13 = await openSingle( page, 'A13' );
	expect( await page.locator( 'main .wp-block-post-featured-image img' ).count(), 'A13 prints its featured image' ).toBe( 1 );
	expect( await a13.locator( '.pk-embed, img' ).count(), 'its clipping has no frame' ).toBe( 0 );
} );

test( 'a repost with no card block gets a clipping from its facts, then its own paragraph', async ( { page } ) => {
	const a08 = await openSingle( page, 'A08' );
	await expect( a08.locator( 'h1.pk-title' ) ).toHaveText( 'Meta Only Clipping' );
	await expect( a08.locator( '.cr-repost-clip__from' ) ).toHaveText( 'From wiki.example.com' );
	await expect( a08 ).toContainText( 'by Sample Author' );
	const order = await page.evaluate( () => {
		const clipping = document.querySelector( 'main article.cr-repost-clip--single' );
		const paragraph = [ ...document.querySelectorAll( 'main .single-post__content p' ) ].find( ( p ) => p.textContent.includes( 'Filed from the classic editor.' ) );
		return !! ( clipping.compareDocumentPosition( paragraph ) & Node.DOCUMENT_POSITION_FOLLOWING );
	} );
	expect( order, 'the paragraph follows the clipping' ).toBe( true );
} );

test( 'Courtney\'s note renders after the clipping once, and a Micropub note stays out of the cite', async ( { page } ) => {
	const s2 = await openSingle( page, 'S2' );
	expect( await page.locator( 'main .single-post__content' ).getByText( 'Saving this for the next zine.' ).count(), 'S2 note prints once' ).toBe( 1 );
	expect( await s2.getByText( 'Saving this for the next zine.' ).count(), 'outside the clipping' ).toBe( 0 );
	const s4 = await openSingle( page, 'S4' );
	expect( await page.locator( 'main .single-post__content' ).getByText( 'Keeping this one for later.' ).count(), 'S4 note prints once' ).toBe( 1 );
	expect( await s4.locator( 'p.p-content' ).count(), 'S4 clipping has no p.p-content' ).toBe( 0 );
	expect( await s4.getByText( 'Keeping this one for later.' ).count() ).toBe( 0 );
} );

for ( const [ width, sideBySide ] of [ [ 1440, true ], [ 768, true ], [ 375, false ], [ 320, false ] ] ) {
	test( `at ${ width }px the single ${ sideBySide ? 'sets image left and text right' : 'stacks image then text' } with no sideways scroll`, async ( { page } ) => {
		const a01 = await openSingle( page, 'A01', width );
		const img = await a01.locator( 'img' ).boundingBox();
		const text = await a01.locator( '.pk-caption' ).boundingBox();
		if ( sideBySide ) {
			expect( img.x + img.width, 'image left of text' ).toBeLessThanOrEqual( text.x + 1 );
			expect( Math.abs( img.y - text.y ), 'same row' ).toBeLessThan( img.height );
		} else {
			expect( img.y + img.height, 'image above text' ).toBeLessThanOrEqual( text.y + 1 );
		}
		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		expect( overflow, 'no horizontal scroll' ).toBeLessThanOrEqual( 0 );
		const box = await a01.boundingBox();
		const body = await a01.locator( '.pk-body' ).boundingBox();
		expect( box.y + box.height - ( body.y + body.height ), 'the lower tape stays inside the clipping' ).toBeGreaterThanOrEqual( 16 );
		const tilt = await a01.evaluate( ( el ) => getComputedStyle( el ).transform );
		expect( tilt, 'the clipping stays upright' ).toBe( 'none' );
		const noImage = await openSingle( page, 'A03', width );
		const caption = await noImage.locator( '.pk-caption' ).boundingBox();
		const clip = await noImage.boundingBox();
		expect( caption.width / clip.width, 'with no image the text takes the full width' ).toBeGreaterThan( 0.8 );
	} );
}

test( 'the single uses Roboto Slab, Barlow and mono, and leaves the picture unfiltered', async ( { page } ) => {
	const a01 = await openSingle( page, 'A01' );
	const fonts = await page.evaluate( () => {
		const family = ( selector ) => getComputedStyle( document.querySelector( selector ) ).fontFamily;
		return {
			title: family( 'main article.cr-repost-clip--single .pk-title' ),
			description: family( 'main article.cr-repost-clip--single .pk-note' ),
			credit: family( 'main article.cr-repost-clip--single .pk-sub:not(.cr-repost-clip__from)' ),
			host: family( 'main article.cr-repost-clip--single .cr-repost-clip__from' ),
			date: family( '.single-post__date' ),
			note: family( 'main .single-post__content' ),
		};
	} );
	expect( fonts.title ).toContain( 'Roboto Slab' );
	for ( const key of [ 'description', 'credit', 'note' ] ) {
		expect( fonts[ key ], key ).toContain( 'Barlow' );
	}
	for ( const key of [ 'host', 'date' ] ) {
		expect( fonts[ key ], key ).toMatch( /monospace|Menlo|Consolas/ );
	}
	expect( await a01.locator( 'img' ).evaluate( ( img ) => getComputedStyle( img ).filter ), 'unfiltered' ).toBe( 'none' );
} );

test( 'the source link takes the 3px selective-yellow ring, and forced colors flatten the clipping', async ( { page } ) => {
	const a01 = await openSingle( page, 'A01' );
	await a01.locator( '.pk-title a' ).focus();
	await page.keyboard.press( 'Shift+Tab' );
	await page.keyboard.press( 'Tab' );
	const ring = await a01.locator( '.pk-title a' ).evaluate( ( el ) => {
		const s = getComputedStyle( el );
		return { width: s.outlineWidth, color: s.outlineColor };
	} );
	expect( ring.width ).toBe( '3px' );
	expect( ring.color ).toBe( YELLOW );
	await page.emulateMedia( { forcedColors: 'active' } );
	const flat = await a01.evaluate( ( el ) => ( { border: getComputedStyle( el ).borderTopWidth, tape: getComputedStyle( el, '::before' ).display, tear: getComputedStyle( el, '::after' ).display } ) );
	expect( flat.border ).toBe( '2px' );
	expect( [ flat.tape, flat.tear ] ).toEqual( [ 'none', 'none' ] );
} );

test( 'on paper the single keeps its fill and ink in dark mode', async ( { page }, testInfo ) => {
	const a01 = await openSingle( page, 'A01' );
	const paint = await a01.evaluate( ( el ) => ( { paper: getComputedStyle( el ).backgroundColor, ink: getComputedStyle( el.querySelector( '.pk-sub:not(.cr-repost-clip__from)' ) ).color, link: getComputedStyle( el.querySelector( '.pk-title a' ) ).color } ) );
	expect( paint.paper, isDark( testInfo ) ? 'dark' : 'light' ).toBe( GRAY );
	expect( paint.ink ).toBe( VIOLET );
	expect( contrast( paint.link, paint.paper ), 'the source link on paper' ).toBeGreaterThanOrEqual( 4.5 );
} );

test( 'Previous and Next are the only post nav, after the comments, 44px chips scoped to reposts', async ( { page } ) => {
	await openSingle( page, 'A04' );
	const nav = page.locator( 'nav.cr-post-nav' );
	await expect( nav ).toHaveCount( 1 );
	const placement = await nav.evaluate( ( el ) => {
		const comments = document.querySelector( '.single-post__comments-wrapper' );
		return { after: ! comments || !! ( comments.compareDocumentPosition( el ) & Node.DOCUMENT_POSITION_FOLLOWING ) };
	} );
	expect( placement.after, 'after the comments group' ).toBe( true );
	const links = await nav.locator( 'a' ).evaluateAll( ( els ) => els.map( ( el ) => ( { href: el.getAttribute( 'href' ), height: el.getBoundingClientRect().height } ) ) );
	expect( links.length, 'both neighbours' ).toBe( 2 );
	expect( links.map( ( l ) => l.href ).join( ' ' ) ).toContain( slugOf( 'A03' ) );
	expect( links.map( ( l ) => l.href ).join( ' ' ) ).toContain( slugOf( 'A05' ) );
	for ( const link of links ) {
		expect( link.height ).toBeGreaterThanOrEqual( 44 );
	}
	expect( await page.locator( 'main .pk-original, main a.pk-link' ).filter( { hasText: /View the original/ } ).count() ).toBe( 0 );
} );

// --- Stream ------------------------------------------------------------------------------------

const STREAM_SEQUENCES = {
	S1: [ 'pk-kindlabel', 'pk-stream-date', 'pk-title', 'pk-sub', 'cr-repost-clip__host', 'pk-note' ],
	S2: [ 'pk-kindlabel', 'pk-stream-date', 'pk-title', 'cr-repost-clip__host', 'pk-note' ],
	S3: [ 'pk-kindlabel', 'pk-stream-date', 'pk-title', 'pk-sub', 'cr-repost-clip__host', 'pk-note' ],
	S4: [ 'pk-kindlabel', 'pk-stream-date', 'pk-title', 'cr-repost-clip__host' ],
};
const NOTES = { S2: 'Saving this for the next zine.', S3: 'Worth a read.', S4: 'Keeping this one for later.' };

for ( const streamPath of STREAM_PATHS ) {
	for ( const width of [ 1440, 320 ] ) {
		test( `${ streamPath } at ${ width }px shows each repost as one taped clipping`, async ( { page }, testInfo ) => {
			await open( page, streamPath, width );
			const found = [];
			for ( const key of Object.keys( STREAM_SEQUENCES ) ) {
				const item = itemFor( page, key );
				if ( await item.count() ) {
					found.push( key );
				}
			}
			test.skip( 0 === found.length, `none of S1-S4 shows on ${ streamPath }` );
			for ( const key of found ) {
				const item = itemFor( page, key );
				const card = item.locator( 'article.pk-card--stream.cr-repost-clip--stream' );
				await expect( card, `${ key }: one clipping` ).toHaveCount( 1 );
				const report = await linkReport( card );
				expect( report.hrefs.length, `${ key }: one anchor` ).toBe( 1 );
				expect( report.hrefs[ 0 ], `${ key }: to the permalink` ).toContain( slugOf( key ) );
				expect( report.stray ).toBe( 0 );
				const sequence = await card.evaluate( ( el ) =>
					[ ...el.querySelectorAll( '.pk-kindlabel, .pk-stream-date, .pk-title, .pk-caption .pk-sub, .pk-note' ) ]
						.filter( ( node ) => ! node.closest( '[hidden]' ) )
						.map( ( node ) => ( node.classList.contains( 'cr-repost-clip__host' ) ? 'cr-repost-clip__host' : [ 'pk-kindlabel', 'pk-stream-date', 'pk-title', 'pk-note' ].find( ( c ) => node.classList.contains( c ) ) || 'pk-sub' ) )
				);
				expect( sequence, `${ key }: label, date, title, author, host, description` ).toEqual( STREAM_SEQUENCES[ key ] );
				expect( await card.locator( 'img, .pk-embed, .pk-badge' ).count(), `${ key }: no image, player or badge` ).toBe( 0 );
				expect( await card.innerText(), `${ key }: no View the original` ).not.toContain( 'View the original' );
				if ( NOTES[ key ] ) {
					expect( await card.innerText(), `${ key }: the note never prints on the card` ).not.toContain( NOTES[ key ] );
				}
				const paint = await card.evaluate( ( el ) => {
					const s = getComputedStyle( el );
					const before = getComputedStyle( el, '::before' );
					const mf = ( selector ) => ( el.querySelector( selector ) ? getComputedStyle( el.querySelector( selector ) ).color : null );
					return {
						paper: s.backgroundColor,
						shadow: s.boxShadow,
						clip: s.clipPath,
						transform: s.transform,
						beforeImage: before.backgroundImage,
						beforeShadow: before.boxShadow,
						beforeBackground: before.backgroundColor,
						colors: [ '.pk-kindlabel', '.pk-stream-date time', '.pk-title', '.pk-caption .pk-sub:not(.cr-repost-clip__host)', '.cr-repost-clip__host', '.pk-note' ].map( mf ).filter( Boolean ),
						width: el.offsetWidth / ( el.parentElement.clientWidth - parseFloat( getComputedStyle( el.parentElement ).paddingLeft ) - parseFloat( getComputedStyle( el.parentElement ).paddingRight ) ),
						scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
					};
				} );
				expect( paint.beforeImage, `${ key }: the tape has no gradient` ).toBe( 'none' );
				expect( paint.beforeShadow, `${ key }: no soft shadow on the tape` ).toBe( 'none' );
				expect( paint.shadow, `${ key }: a 4px hard shadow with no blur` ).toMatch( /4px 4px 0px( 0px)?$/ );
				expect( paint.clip, `${ key }: nothing clips the shadow` ).toBe( 'none' );
				expect( paint.paper, `${ key }: --cr-light-gray paper in both schemes` ).toBe( GRAY );
				for ( const color of paint.colors ) {
					expect( contrast( color, paint.paper ), `${ key }: text holds 4.5:1` ).toBeGreaterThanOrEqual( 4.5 );
				}
				const matrix = /matrix\(([^)]+)\)/.exec( paint.transform );
				expect( matrix, `${ key }: the card is tilted` ).not.toBeNull();
				const [ a, b ] = matrix[ 1 ].split( ',' ).map( Number );
				const degrees = ( Math.atan2( b, a ) * 180 ) / Math.PI;
				expect( TILT_DEGREES.some( ( tilt ) => Math.abs( tilt - degrees ) < 0.15 ), `${ key }: one --cr-rotate token (${ degrees.toFixed( 2 ) }deg)` ).toBe( true );
				if ( width === 1440 ) {
					expect( paint.width, `${ key }: 80% of its column` ).toBeGreaterThan( 0.78 );
					expect( paint.width ).toBeLessThan( 0.82 );
				}
				expect( paint.scroll, `${ key }: no horizontal scroll` ).toBeLessThanOrEqual( 0 );
				if ( isDark( testInfo ) ) {
					expect( paint.beforeBackground, `${ key }: the dark tape token` ).toBe( DARK_TAPE );
				}
				const targetBox = await card.boundingBox();
				expect( targetBox.height, `${ key }: a target of at least 44px` ).toBeGreaterThanOrEqual( 44 );
			}
		} );
	}
}

test( 'the Stream clipping clamps a long description to three lines and rings the whole card on focus', async ( { page } ) => {
	await open( page, '/stream/', 1440 );
	const card = itemFor( page, 'S2' ).locator( 'article.cr-repost-clip--stream' );
	test.skip( 0 === ( await card.count() ), 'S2 is not on /stream/' );
	const note = await card.locator( '.pk-note' ).evaluate( ( el ) => {
		const s = getComputedStyle( el );
		return { clamp: s.webkitLineClamp, lines: Math.round( el.offsetHeight / parseFloat( s.lineHeight ) ) };
	} );
	expect( note.clamp ).toBe( '3' );
	expect( note.lines ).toBeLessThanOrEqual( 3 );
	await card.locator( '.pk-title a' ).focus();
	await page.keyboard.press( 'Shift+Tab' );
	await page.keyboard.press( 'Tab' );
	const ring = await card.evaluate( ( el ) => ( { width: getComputedStyle( el ).outlineWidth, color: getComputedStyle( el ).outlineColor, shadow: getComputedStyle( el ).boxShadow } ) );
	expect( ring.width ).toBe( '3px' );
	expect( ring.color ).toBe( YELLOW );
	expect( ring.shadow ).toMatch( /7px/ );
	await page.emulateMedia( { forcedColors: 'active' } );
	const flat = await card.evaluate( ( el ) => ( { border: getComputedStyle( el ).borderTopWidth, tape: getComputedStyle( el, '::before' ).display } ) );
	expect( flat.border ).toBe( '2px' );
	expect( flat.tape ).toBe( 'none' );
} );

test( 'the like control and the protected repost keep their own Stream cards', async ( { page } ) => {
	await open( page, '/stream/', 1440 );
	for ( const key of [ 'N1', 'P1' ] ) {
		const item = itemFor( page, key );
		if ( 0 === ( await item.count() ) ) {
			continue;
		}
		expect( await item.locator( 'article.cr-repost-clip--stream' ).count(), `${ key } is not a repost clipping` ).toBe( 0 );
	}
} );
