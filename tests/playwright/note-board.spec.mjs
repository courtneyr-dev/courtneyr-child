// Local:
// `CR_BASE_URL=http://localhost:8892 CR_SPEC=note-board.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`
// Dev, after the deploy go:
// `CR_ALLOW_REMOTE=1 CR_BASE_URL=https://dev-courtneyrdev.pantheonsite.io CR_SPEC=note-board.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`
//
// Served checks require CR_NOTE_MAP: a file containing seed-265.php's
// `PAGE_MAP_JSON {...}` line or the raw JSON document. CR_NOTE_DEV=1 enables
// dev-only real-note checks. CR_NOTE_SNAPSHOT=write|compare enables normalized
// `main` outerHTML regression files in CR_NOTE_SNAPSHOT_DIR (default
// tests/output/note-snapshots). Normalization removes nonce attributes, all id
// attributes (WordPress generates some per request), and insignificant
// inter-tag/duplicate whitespace.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';

const ARCHIVE = process.env.CR_NOTE_ARCHIVE_PATH || '/kind/note/';
const MAP_PATH = process.env.CR_NOTE_MAP || '';
const SNAPSHOT_MODE = process.env.CR_NOTE_SNAPSHOT || '';
const ROOT = fileURLToPath( new URL( '../..', import.meta.url ) );
const SNAPSHOT_DIR = process.env.CR_NOTE_SNAPSHOT_DIR || path.join( ROOT, 'tests/output/note-snapshots' );
const BOARD = '.wp-block-post-template.cr-note-board__list.is-style-cr-note-board.pkiw-grouped';
const ITEM = `${ BOARD } > section.pkiw-group > ul.pkiw-group__items > li.wp-block-post`;

function readMap() {
	if ( ! MAP_PATH || ! fs.existsSync( MAP_PATH ) ) {
		return null;
	}
	const input = fs.readFileSync( MAP_PATH, 'utf8' ).trim();
	const raw = input.includes( 'PAGE_MAP_JSON ' ) ? input.slice( input.lastIndexOf( 'PAGE_MAP_JSON ' ) + 14 ).split( /\r?\n/, 1 )[0] : input;
	try {
		const parsed = JSON.parse( raw );
		return 'cr-seed-265-page-map-v1' === parsed.schema ? parsed : null;
	} catch {
		return null;
	}
}

const NOTE_MAP = readMap();
const pages = () => NOTE_MAP?.logged_out?.pages || [];
const archivePath = ( number = 1 ) => number > 1 ? `${ ARCHIVE.replace( /\/?$/, '/' ) }page/${ number }/` : ARCHIVE;

// W3-G2: rendered means an a[href] with no hidden ancestor-or-self.
const renderedAnchors = ( scope ) => scope.evaluate( ( el ) => [ ...el.querySelectorAll( 'a[href]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) ).length );
const visibleNegativeTabindex = ( scope ) => scope.evaluate( ( el ) => [ ...el.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) ).length );

async function openArchive( page, number = 1 ) {
	test.skip( ! NOTE_MAP, 'CR_NOTE_MAP must name a valid PAGE_MAP_JSON file for served Note checks' );
	const response = await page.goto( archivePath( number ), { waitUntil: 'load' } );
	// With a seed map named, a missing page or board is a failure, not a skip.
	expect( response && response.status(), `Note archive page ${ number } at ${ archivePath( number ) }` ).toBe( 200 );
	expect( await page.locator( BOARD ).count(), 'the Note board lane renders on the seeded archive' ).toBeGreaterThan( 0 );
}

async function fixturePath( request, label ) {
	const slug = `cr-seed-265-${ label.toLowerCase() }`;
	const response = await request.get( `/wp-json/wp/v2/posts?slug=${ slug }&_fields=link,slug` );
	if ( ! response.ok() ) {
		return null;
	}
	const [ post ] = await response.json();
	return post?.link ? new URL( post.link ).pathname : null;
}

function fixturePage( label ) {
	return pages().find( ( entry ) => entry.items.includes( label ) )?.page || 0;
}

async function fixtureItem( page, request, label ) {
	const single = await fixturePath( request, label );
	const number = fixturePage( label );
	test.skip( ! single || ! number, `fixture ${ label } is missing from REST or CR_NOTE_MAP` );
	await openArchive( page, number );
	const item = page.locator( `${ ITEM }:has(a[href$="${ single }"])` );
	test.skip( 1 !== await item.count(), `fixture ${ label } is not rendered on mapped archive page ${ number }` );
	return item;
}

async function archiveDescription( request ) {
	const response = await request.get( '/wp-json/wp/v2/kind?slug=note' );
	if ( ! response.ok() ) {
		return '';
	}
	const [ term ] = await response.json();
	return ( term?.description || '' ).replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
}

const accessibleName = ( link ) => link.evaluate( ( el ) => {
	const labelledby = ( el.getAttribute( 'aria-labelledby' ) || '' ).trim().split( /\s+/ ).filter( Boolean );
	if ( labelledby.length ) {
		return labelledby.map( ( id ) => document.getElementById( id )?.textContent || '' ).join( ' ' ).replace( /\s+/g, ' ' ).trim();
	}
	return ( el.getAttribute( 'aria-label' ) || el.textContent || '' ).replace( /\s+/g, ' ' ).trim();
} );

function durationMs( value ) {
	return Math.max( ...value.split( ',' ).map( ( part ) => {
		const text = part.trim();
		return text.endsWith( 'ms' ) ? parseFloat( text ) : 1000 * parseFloat( text );
	} ) );
}

// ------------------------------------------------------------ served archive

test.describe( 'served Note archive', () => {
	test( 'header uses the shared identity, four Note cutouts, stored description, divider and decorative badge', async ( { page, request } ) => {
		await openArchive( page );
		const header = page.locator( 'main .cr-archive__header' );
		await expect( header ).toHaveCount( 1 );
		await expect( header.locator( '.cr-archive-identity.cr-archive-identity--kind' ) ).toHaveCount( 1 );
		await expect( header.getByRole( 'heading', { level: 1, name: 'Note', exact: true } ) ).toHaveCount( 1 );
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );

		const tiles = header.locator( 'h1 .cr-cutout__tiles[aria-hidden="true"] > .cr-cutout__tile' );
		await expect( tiles ).toHaveCount( 4 );
		expect( ( await tiles.allTextContents() ).join( '' ).replace( /\s/g, '' ) ).toBe( 'Note' );
		const rotations = await tiles.evaluateAll( ( els ) => els.map( ( el ) => [ ...el.classList ].find( ( name ) => /^is-r\d+$/.test( name ) ) || '' ) );
		for ( let index = 1; index < rotations.length; index++ ) {
			expect( rotations[index], `tile ${ index + 1 } does not repeat its neighbor's rotation class` ).not.toBe( rotations[index - 1] );
		}

		const description = await archiveDescription( request );
		test.skip( ! description, 'the Note term has no stored description' );
		await expect( header.locator( '.cr-archive__description' ) ).toHaveText( description );
		await expect( header.locator( 'hr.is-style-cr-marker-bar-short' ) ).toHaveCount( 1 );
		const badge = header.locator( '.cr-archive-identity__glyph[aria-hidden="true"]' );
		await expect( badge ).toHaveCount( 1 );
		expect( await badge.getAttribute( 'tabindex' ) ).toBeNull();
		await expect( badge.locator( 'a, [tabindex]' ) ).toHaveCount( 0 );
		expect( await badge.evaluate( ( el ) => null !== el.closest( 'a' ) ) ).toBe( false );
		await expect( header.locator( '.cr-archive-identity__kicker, .is-style-cr-tape-label, [class*="tape"]' ) ).toHaveCount( 0 );
	} );

	test( 'main order is header, grouped post template, adjacent pager, then browse-all', async ( { page } ) => {
		await openArchive( page );
		const ordered = await page.locator( 'main' ).evaluate( ( main ) => {
			const nodes = [
				main.querySelector( '.cr-archive__header' ),
				main.querySelector( '.wp-block-post-template.cr-note-board__list.is-style-cr-note-board.pkiw-grouped' ),
				main.querySelector( 'nav.wp-block-query-pagination[aria-label="Pagination"]' ),
				main.querySelector( 'nav.cr-browse-all' ),
			];
			return { present: nodes.every( Boolean ), sorted: nodes.every( ( node, i ) => 0 === i || Boolean( nodes[i - 1].compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) ), adjacent: nodes[1]?.nextElementSibling === nodes[2] };
		} );
		expect( ordered.present ).toBe( true );
		expect( ordered.sorted ).toBe( true );
		expect( ordered.adjacent, 'pagination is the grouped wrapper\'s next sibling' ).toBe( true );
	} );

	test( 'pager stays outside groups, has correct ends and 44px targets; one page beyond the map is 404', async ( { page } ) => {
		for ( const entry of pages() ) {
			await openArchive( page, entry.page );
			await expect( page.locator( `${ BOARD } .pkiw-group nav, ${ BOARD } .pkiw-group .page-numbers` ) ).toHaveCount( 0 );
			expect( await page.locator( BOARD ).innerText() ).not.toMatch( /\bpage \d+\b|\d+ of \d+/i );
		}
		await openArchive( page );
		await expect( page.locator( 'nav.wp-block-query-pagination a', { hasText: 'Previous' } ) ).toHaveCount( 0 );
		for ( const target of await page.locator( 'nav.wp-block-query-pagination a, nav.wp-block-query-pagination .page-numbers' ).all() ) {
			const box = await target.boundingBox();
			expect( box?.width || 0 ).toBeGreaterThanOrEqual( 43.5 );
			expect( box?.height || 0 ).toBeGreaterThanOrEqual( 43.5 );
		}
		await openArchive( page, pages().length );
		await expect( page.locator( 'nav.wp-block-query-pagination a', { hasText: 'Next' } ) ).toHaveCount( 0 );
		const missing = await page.goto( archivePath( pages().length + 1 ), { waitUntil: 'load' } );
		expect( missing?.status() ).toBe( 404 );
	} );

	test( 'logged-out counts and H2 texts equal the map, page 2 repeats its opening week, and no heading says continued', async ( { page } ) => {
		for ( const expected of pages() ) {
			await openArchive( page, expected.page );
			await expect( page.locator( ITEM ) ).toHaveCount( expected.count );
			expect( ( await page.locator( `${ BOARD } > .pkiw-group > .pkiw-group__heading` ).allTextContents() ).map( ( text ) => text.trim() ) ).toEqual( expected.headings );
			await expect( page.getByText( /continued/i ) ).toHaveCount( 0 );
		}
		expect( pages().length, 'the map has a second page' ).toBeGreaterThan( 1 );
		const repeated = pages()[1].headings[0];
		expect( pages()[0].headings ).toContain( repeated );
		await openArchive( page, 2 );
		await expect( page.locator( `${ BOARD } > .pkiw-group > h2` ).first() ).toHaveText( repeated );
	} );

	test( 'every li has one rendered h-entry link and date, no media, and neutral list-item cascade', async ( { page } ) => {
		for ( const entry of pages() ) {
			await openArchive( page, entry.page );
			for ( const item of await page.locator( ITEM ).all() ) {
				expect( await renderedAnchors( item ) ).toBe( 1 );
				expect( await visibleNegativeTabindex( item ) ).toBe( 0 );
				await expect( item ).toHaveClass( /\bh-entry\b/ );
				await expect( item.locator( '.pkiw-note-entry' ) ).toHaveCount( 1 );
				await expect( item.locator( 'time.dt-published' ) ).toHaveCount( 1 );
				await expect( item.locator( 'img, video, iframe' ) ).toHaveCount( 0 );
				const style = await item.evaluate( ( el ) => ( { padding: getComputedStyle( el ).paddingTop, duration: getComputedStyle( el ).transitionDuration, after: getComputedStyle( el, '::after' ).content } ) );
				expect( style ).toEqual( { padding: '0px', duration: '0s', after: 'none' } );
			}
		}
	} );

	test( 'archive pages reveal no controls, samples, location data, secret text or time of day', async ( { page } ) => {
		const forbidden = [ 'Read note', 'SAMPLE CONTENT', 'Example Lane', 'Sampletown', '-30.5', 'SECRET-265' ];
		for ( const entry of pages() ) {
			await openArchive( page, entry.page );
			const text = await page.locator( BOARD ).innerText();
			for ( const value of forbidden ) {
				expect( text ).not.toContain( value );
			}
			expect( text ).not.toMatch( /\d{1,2}:\d{2}\s?(?:AM|PM)/i );
			await expect( page.locator( `${ BOARD } .p-location, ${ BOARD } .h-geo, ${ BOARD } .h-adr` ) ).toHaveCount( 0 );
		}
	} );

	test( 'fixture names, protected omissions and accessible-name disambiguation hold', async ( { page, request } ) => {
		for ( const [ label, name ] of Object.entries( {
			N05: 'Note, August 21, 2026',
			N15: 'Note, June 29, 2026',
			N27: 'Check-in, August 20, 2025',
			N28: 'Protected: Check-in, August 6, 2025',
			N14: 'Protected: Locked Shelf',
		} ) ) {
			const item = await fixtureItem( page, request, label );
			await expect( item.locator( 'a.u-url' ) ).toHaveAccessibleName( name );
		}
		for ( const label of [ 'N14', 'N15', 'N28' ] ) {
			const item = await fixtureItem( page, request, label );
			await expect( item.locator( '.pkiw-note-entry__thought' ) ).toHaveCount( 0 );
		}

		await openArchive( page );
		expect( await fixturePath( request, 'P01' ) ).toBeNull();
		expect( await fixturePath( request, 'D01' ) ).toBeNull();
		const allNames = [];
		for ( const entry of pages() ) {
			await openArchive( page, entry.page );
			await expect( page.locator( 'a[href*="cr-seed-265-p01"], a[href*="cr-seed-265-d01"]' ) ).toHaveCount( 0 );
			const names = [];
			for ( const link of await page.locator( `${ITEM} a[href]` ).all() ) {
				if ( ! await link.evaluate( ( el ) => el.closest( '[hidden]' ) ) ) {
					names.push( await accessibleName( link ) );
				}
			}
			expect( names.every( Boolean ) ).toBe( true );
			expect( new Set( names ).size, `unique link names on page ${ entry.page }` ).toBe( names.length );
			allNames.push( ...names );
		}
		expect( new Set( allNames ).size, 'unique link names across every archive page' ).toBe( allNames.length );

		for ( const label of [ 'N04', 'N06', 'N22', 'N23' ] ) {
			const item = await fixtureItem( page, request, label );
			const link = item.locator( 'a.u-url' );
			const ids = ( await link.getAttribute( 'aria-labelledby' ) || '' ).split( /\s+/ ).filter( Boolean );
			expect( ids.length ).toBeGreaterThanOrEqual( 2 );
			const classes = await item.evaluate( ( el, references ) => references.map( ( id ) => document.getElementById( id )?.className || '' ), ids );
			expect( classes.some( ( value ) => /title|thought/.test( value ) ) ).toBe( true );
			expect( classes.some( ( value ) => /date/.test( value ) ) ).toBe( true );
		}
	} );

	test( 'board, strip, sticky and type use the approved computed paint', async ( { page } ) => {
		await openArchive( page );
		await page.evaluate( () => document.fonts.ready );
		const style = await page.locator( BOARD ).evaluate( ( root ) => {
			const value = ( selector, property ) => getComputedStyle( root.querySelector( selector ) )[property];
			const board = root.querySelector( '.pkiw-group' );
			const boardStyle = getComputedStyle( board );
			const artifactStyle = getComputedStyle( root.querySelector( '.cr-sticky-artifact--board' ) );
			return {
				board: [ boardStyle.backgroundColor, boardStyle.borderTopWidth, boardStyle.borderTopLeftRadius, boardStyle.boxShadow ],
				strip: [ value( '.pkiw-group__heading', 'backgroundImage' ), value( '.pkiw-group__heading', 'textTransform' ), value( '.pkiw-group__heading', 'fontFamily' ) ],
				title: [ value( '.pkiw-note-entry__title', 'fontFamily' ), value( '.pkiw-note-entry__title', 'fontWeight' ) ],
				thought: [ value( '.pkiw-note-entry__thought', 'fontFamily' ), value( '.pkiw-note-entry__thought', 'fontSize' ), value( '.pkiw-note-entry__thought', 'lineHeight' ), value( '.pkiw-note-entry__thought', 'webkitLineClamp' ) ],
				date: [ value( '.pkiw-note-entry__date', 'fontSize' ), value( '.pkiw-note-entry__date', 'textTransform' ) ],
				artifact: [ artifactStyle.transform, artifactStyle.filter ],
				decorations: root.querySelectorAll( '[class*="tape"], [class*="pin"], [class*="torn"]' ).length,
			};
		} );
		expect( style.board ).toEqual( [ 'rgb(142, 202, 230)', '2px', '0px', 'none' ] );
		expect( style.strip[0] ).toContain( 'rgb(251, 133, 0)' );
		expect( style.strip[1] ).toBe( 'uppercase' );
		expect( style.strip[2] ).toContain( 'Roboto Slab' );
		expect( style.title[0] ).toContain( 'Roboto Slab' );
		expect( style.title[1] ).toBe( '700' );
		expect( style.thought ).toEqual( expect.arrayContaining( [ '16px', '29.6px', '7' ] ) );
		expect( style.thought[0] ).toContain( 'Rock Salt' );
		expect( parseFloat( style.date[0] ) ).toBeGreaterThanOrEqual( 12 );
		expect( style.date[1] ).toBe( 'uppercase' );
		expect( style.artifact[0] ).toBe( 'matrix(1, 0, 0, 1, 0, 0)' );
		expect( style.artifact[1] ).toContain( 'drop-shadow' );
		expect( style.decorations ).toBe( 0 );
	} );

	for ( const [ width, columns ] of [ [ 1440, 3 ], [ 1024, 3 ], [ 800, 2 ], [ 768, 2 ], [ 375, 1 ], [ 320, 1 ] ] ) {
		test( `${ columns } columns at ${ width }px, with DOM and visual order aligned`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openArchive( page );
			const boxes = await page.locator( `${ BOARD } > .pkiw-group` ).evaluateAll( ( groups ) => {
				const group = groups.find( ( el ) => el.querySelectorAll( ':scope > .pkiw-group__items > li' ).length >= 3 ) || groups[0];
				return [ ...group.querySelectorAll( ':scope > .pkiw-group__items > li' ) ].map( ( el, index ) => {
					const rect = el.getBoundingClientRect();
					return { index, top: Math.round( rect.top + scrollY ), left: Math.round( rect.left ) };
				} );
			} );
			const firstTop = boxes[0].top;
			const firstRow = boxes.filter( ( box ) => Math.abs( box.top - firstTop ) < 3 );
			expect( new Set( firstRow.map( ( box ) => box.left ) ).size ).toBe( Math.min( columns, boxes.length ) );
			const visual = [ ...boxes ].sort( ( a, b ) => Math.abs( a.top - b.top ) < 3 ? a.left - b.left : a.top - b.top ).map( ( box ) => box.index );
			expect( visual ).toEqual( boxes.map( ( box ) => box.index ) );
			if ( 320 === width ) {
				expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
			}
		} );
	}

	test( 'tab order matches DOM order, 400% reflow has no horizontal scroll, and N08 wraps', async ( { page, request } ) => {
		await page.setViewportSize( { width: 1024, height: 900 } );
		await openArchive( page );
		const links = page.locator( `${ITEM} a.u-url` );
		const hrefs = await links.evaluateAll( ( els ) => els.map( ( el ) => el.href ) );
		await links.first().focus();
		const tabbed = [ hrefs[0] ];
		for ( let index = 1; index < Math.min( hrefs.length, 8 ); index++ ) {
			await page.keyboard.press( 'Tab' );
			tabbed.push( await page.evaluate( () => document.activeElement?.href || '' ) );
		}
		expect( tabbed ).toEqual( hrefs.slice( 0, tabbed.length ) );
		await page.setViewportSize( { width: 320, height: 800 } ); // 1280 CSS px at 400% exposes a 320px CSS viewport; project deviceScaleFactor remains 1.
		await openArchive( page );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		const n08 = await fixtureItem( page, request, 'N08' );
		expect( await n08.evaluate( ( el ) => el.scrollWidth <= el.clientWidth ) ).toBe( true );
	} );

	test( 'paper corners and curl flap belong to the link, while the transparent cut does not', async ( { page } ) => {
		await openArchive( page );
		const result = await page.locator( ITEM ).first().evaluate( ( item ) => {
			// elementFromPoint only answers inside the viewport.
			item.scrollIntoView( { block: 'center' } );
			const sticky = item.querySelector( '.cr-sticky--board' );
			const link = item.querySelector( 'a.u-url' );
			const rect = sticky.getBoundingClientRect();
			const curl = parseFloat( getComputedStyle( item.querySelector( '.cr-sticky__curl' ) ).width );
			const hit = ( x, y ) => document.elementFromPoint( x, y )?.closest( 'a' ) === link;
			return {
				paper: [ hit( rect.left + 4, rect.top + 4 ), hit( rect.right - 4, rect.top + 4 ), hit( rect.left + 4, rect.bottom - 4 ) ],
				flap: hit( rect.right - 0.6 * curl, rect.bottom - 0.6 * curl ),
				cut: hit( rect.right - 0.2 * curl, rect.bottom - 0.2 * curl ),
			};
		} );
		expect( result.paper ).toEqual( [ true, true, true ] );
		expect( result.flap ).toBe( true );
		expect( result.cut ).toBe( false );
	} );

	test( 'each paper tint gets the 3px yellow focus ring and identity transform on hover and focus', async ( { page } ) => {
		await openArchive( page );
		const artifacts = page.locator( '.cr-sticky-artifact--board' );
		const papers = await artifacts.evaluateAll( ( els ) => els.map( ( el ) => getComputedStyle( el ).getPropertyValue( '--cr-sticky-paper' ).trim() ) );
		for ( const paper of [ ...new Set( papers ) ] ) {
			const index = papers.indexOf( paper );
			const artifact = artifacts.nth( index );
			await artifact.locator( 'a.u-url' ).focus();
			const focused = await artifact.evaluate( ( el ) => {
				const cs = getComputedStyle( el );
				return [ cs.outlineWidth, cs.outlineColor, cs.transform ];
			} );
			expect( focused ).toEqual( [ '3px', 'rgb(255, 183, 3)', 'matrix(1, 0, 0, 1, 0, 0)' ] );
			await artifact.hover();
			expect( await artifact.evaluate( ( el ) => getComputedStyle( el ).transform ) ).toBe( 'matrix(1, 0, 0, 1, 0, 0)' );
		}
	} );

	test( 'dark preference and data-theme dark match; explicit light overrides dark preference', async ( { page } ) => {
		const collect = () => page.locator( BOARD ).evaluate( ( root ) => [ ...root.querySelectorAll( '.pkiw-group, .pkiw-group__heading, .cr-sticky--board, .pkiw-note-entry__title, .pkiw-note-entry__thought, .pkiw-note-entry__date' ) ].map( ( el ) => {
			const cs = getComputedStyle( el );
			return [ el.className, cs.backgroundColor, cs.borderTopColor, cs.color, cs.filter ];
		} ) );
		await page.emulateMedia( { colorScheme: 'light' } );
		await openArchive( page );
		const light = await collect();
		await page.emulateMedia( { colorScheme: 'dark' } );
		await page.evaluate( () => document.documentElement.removeAttribute( 'data-theme' ) );
		const preferenceDark = await collect();
		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.setAttribute( 'data-theme', 'dark' ) );
		const attributeDark = await collect();
		expect( attributeDark ).toEqual( preferenceDark );
		const darkValues = await page.locator( BOARD ).evaluate( ( root ) => {
			const color = ( selector, property = 'color' ) => getComputedStyle( root.querySelector( selector ) )[property];
			return [ color( '.pkiw-group', 'backgroundColor' ), color( '.pkiw-group', 'borderTopColor' ), color( '.pkiw-group__heading' ), color( '.pkiw-note-entry__title' ), color( '.pkiw-note-entry__thought' ), color( '.pkiw-note-entry__date' ) ];
		} );
		expect( darkValues ).toEqual( [ 'rgb(142, 202, 230)', 'rgb(251, 250, 245)', 'rgb(36, 28, 74)', 'rgb(36, 28, 74)', 'rgb(36, 28, 74)', 'rgb(36, 28, 74)' ] );
		await page.emulateMedia( { colorScheme: 'dark' } );
		await page.evaluate( () => document.documentElement.setAttribute( 'data-theme', 'light' ) );
		expect( await collect() ).toEqual( light );
	} );

	test( 'forced colors removes paper effects and keeps 2px outlines', async ( { page } ) => {
		await page.emulateMedia( { forcedColors: 'active' } );
		await openArchive( page );
		for ( const selector of [ '.pkiw-group', '.cr-sticky--board' ] ) {
			const values = await page.locator( `${ BOARD } ${ selector }` ).first().evaluate( ( el ) => {
				const cs = getComputedStyle( el );
				return [ cs.outlineWidth, cs.clipPath, cs.backgroundImage ];
			} );
			expect( values ).toEqual( [ '2px', 'none', 'none' ] );
		}
		await expect( page.locator( `${ BOARD } .cr-sticky__curl` ).first() ).toBeHidden();
	} );

	test( 'reduced motion makes every li and artifact transition at most 0.001ms', async ( { page } ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await openArchive( page );
		const durations = await page.locator( `${ ITEM }, ${ ITEM } .cr-sticky-artifact--board` ).evaluateAll( ( els ) => els.map( ( el ) => getComputedStyle( el ).transitionDuration ) );
		for ( const duration of durations ) {
			expect( durationMs( duration ) ).toBeLessThanOrEqual( 0.001 );
		}
	} );
} );

// ------------------------------------------------------- regression snapshots

function normalizedMain( html ) {
	return html
		.replace( /\s(?:nonce|data-nonce|data-wp-nonce)=(['"])[\s\S]*?\1/gi, '' )
		.replace( /\sid=(['"])[\s\S]*?\1/gi, '' )
		.replace( />\s+</g, '><' )
		.replace( /\s+/g, ' ' )
		.trim();
}

test.describe( 'regression snapshots', () => {
	test.skip( ! [ 'write', 'compare' ].includes( SNAPSHOT_MODE ), 'set CR_NOTE_SNAPSHOT=write or compare' );
	test( 'N19, N18, N10, N17 and Stream main DOM match normalized files', async ( { page, request } ) => {
		test.skip( ! NOTE_MAP, 'CR_NOTE_MAP is required for served regression snapshots' );
		const targets = [];
		for ( const label of [ 'N19', 'N18', 'N10', 'N17' ] ) {
			const href = await fixturePath( request, label );
			test.skip( ! href, `fixture ${ label } is absent` );
			targets.push( { href, slug: `cr-seed-265-${ label.toLowerCase() }` } );
		}
		targets.push( { href: '/stream/', slug: 'stream' } );
		const differences = [];
		if ( 'write' === SNAPSHOT_MODE ) {
			fs.mkdirSync( SNAPSHOT_DIR, { recursive: true } );
		}
		for ( const target of targets ) {
			const response = await page.goto( target.href, { waitUntil: 'load' } );
			test.skip( ! response || 200 !== response.status() || 0 === await page.locator( 'main' ).count(), `${ target.slug } is not served` );
			const current = normalizedMain( await page.locator( 'main' ).evaluate( ( el ) => el.outerHTML ) );
			const file = path.join( SNAPSHOT_DIR, `${ target.slug }.html` );
			if ( 'write' === SNAPSHOT_MODE ) {
				fs.writeFileSync( file, `${ current }\n` );
			} else if ( ! fs.existsSync( file ) || fs.readFileSync( file, 'utf8' ).trim() !== current ) {
				differences.push( target.slug );
			}
		}
		expect( differences, `normalized main snapshots differ: ${ differences.join( ', ' ) }` ).toEqual( [] );
	} );
} );

// ----------------------------------------------------- source-only, no website

test.describe( 'source-only CSS policy', () => {
	test( 'Note board CSS has palette-free literals, no effects, and a 0.75rem text floor', () => {
		const source = fs.readFileSync( path.join( ROOT, 'assets/css/cr-note-board.css' ), 'utf8' );
		expect( source ).not.toMatch( /#[0-9a-f]{3,8}\b/i );
		for ( const match of source.matchAll( /\bfilter\s*:\s*([^;]+);/gi ) ) {
			expect( match[1].trim() ).toBe( 'none !important' );
		}
		expect( source ).not.toMatch( /\bblur\s*\(/i );
		expect( source ).not.toMatch( /\brotate\s*\(/i );
		const withoutMarkerUnderline = source.replaceAll( /linear-gradient\(var\(--cr-marker\),\s*var\(--cr-marker\)\)/gi, '' );
		expect( withoutMarkerUnderline ).not.toMatch( /(?:repeating-)?(?:linear|radial|conic)-gradient\(/i );
		for ( const match of source.matchAll( /font-size\s*:\s*([^;]+);/gi ) ) {
			const value = match[1].trim();
			const rems = [ ...value.matchAll( /([0-9]*\.?[0-9]+)rem\b/g ) ].map( ( entry ) => Number( entry[1] ) );
			expect( /var\(--cr-text-floor\)|max\(/.test( value ) || ( rems.length > 0 && rems.every( ( rem ) => rem >= 0.75 ) ), `font-size ${ value } keeps the 0.75rem floor` ).toBe( true );
		}
	} );
} );

// --------------------------------------------------------------- dev-only

test.describe( 'dev-only real notes', () => {
	test.skip( '1' !== process.env.CR_NOTE_DEV, 'set CR_NOTE_DEV=1 after the dev deploy' );

	for ( const id of [ 3653, 3550, 8660 ] ) {
		test( `real note ${ id } single keeps one H1 and no archive board or week label`, async ( { page } ) => {
			test.skip( ! NOTE_MAP, 'CR_NOTE_MAP is required for served dev checks' );
			const response = await page.goto( `/?p=${ id }`, { waitUntil: 'load' } );
			test.skip( ! response || 200 !== response.status(), `real note ${ id } is absent` );
			await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
			await expect( page.locator( 'main .cr-note-board' ) ).toHaveCount( 0 );
			await expect( page.locator( 'main .pkiw-group__heading' ) ).toHaveCount( 0 );
		} );
	}

	test( 'note 8256 keeps location on its single and REST excerpt but not on the archive', async ( { page, request } ) => {
		test.skip( ! NOTE_MAP, 'CR_NOTE_MAP is required for served dev checks' );
		const single = await page.goto( '/?p=8256', { waitUntil: 'load' } );
		test.skip( ! single || 200 !== single.status(), 'real note 8256 is absent' );
		await expect( page.locator( 'main' ) ).toContainText( 'Hamilton Township, US' );
		for ( const entry of pages() ) {
			await openArchive( page, entry.page );
			await expect( page.locator( BOARD ) ).not.toContainText( 'Hamilton Township, US' );
			await expect( page.locator( `${ BOARD } .h-adr` ) ).toHaveCount( 0 );
		}
		const response = await request.get( '/wp-json/wp/v2/posts/8256' );
		expect( response.ok() ).toBe( true );
		const post = await response.json();
		expect( post.excerpt?.rendered || '' ).toContain( 'Hamilton Township, US' );
		for ( const key of [ 'latitude', 'longitude', 'geo_address' ] ) {
			expect( Object.hasOwn( post, key ), `no top-level ${ key }` ).toBe( false );
		}
		expect( post.meta?.geo_latitude ).toBe( '' );
		expect( post.meta?.geo_longitude ).toBe( '' );
		expect( post.meta?.geo_address ).toBe( '' );
	} );
} );
