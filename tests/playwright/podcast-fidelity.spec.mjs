// `CR_SPEC=podcast-fidelity.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`:
// PKIW issue 236 podcast surfaces. Read-only GETs, logged out; each fixture pass is explicit and a missing
// fixture fails the run. Only the documented flags below skip anything.
//
// Fixture passes, in this order:
//   1. `wp eval-file seed-236.php seed archive`, then `-g "archive —|shelf —|privacy —"`.
//   2. `wp eval-file seed-236.php seed single`, map cr-local-embed-236.php for Q02, then `-g "single —"`.
//   3. `wp eval-file seed-236.php seed stream`, then `-g "stream —"`.
//   4. Empty environment: CR_PODCAST_STATE=empty with `-g "empty —"`.
//
// Optional flags: CR_PODCAST_ARCHIVE_PATH, CR_PODCAST_STREAM_PATH, CR_PODCAST_STATE=full|empty,
// CR_PODCAST_DEV=1, CR_PODCAST_SHELF_BASELINE, CR_PODCAST_MUSIC_BASELINE,
// CR_PODCAST_WRITE_BASELINE=1 and CR_PODCAST_MUSIC_SLUG.
//
// A/B protocol: on child-w4, set both baseline paths and CR_PODCAST_WRITE_BASELINE=1 to write normalized
// shelf and music-single markup. On this branch, use the same paths without write mode to compare.

import { createRequire } from 'node:module';
import { readFile, writeFile } from 'node:fs/promises';
import { test, expect } from '@playwright/test';
import { BASE_URL } from '../routes.mjs';

const require = createRequire( import.meta.url );
const ARCHIVE = process.env.CR_PODCAST_ARCHIVE_PATH || '/kind/listen/';
const STREAM = process.env.CR_PODCAST_STREAM_PATH || '/stream/';
const STATE = process.env.CR_PODCAST_STATE || 'full';
const DEV = process.env.CR_PODCAST_DEV === '1';
const SHELF_BASELINE = process.env.CR_PODCAST_SHELF_BASELINE || '';
const MUSIC_BASELINE = process.env.CR_PODCAST_MUSIC_BASELINE || '';
const WRITE_BASELINE = process.env.CR_PODCAST_WRITE_BASELINE === '1';
const MUSIC_SLUG = process.env.CR_PODCAST_MUSIC_SLUG || 'seed-236-m01';

const IVORY = 'rgb(251, 250, 245)';
const VIOLET = 'rgb(36, 28, 74)';
const PRUSSIAN = 'rgb(2, 48, 71)';
const CERULEAN = 'rgb(18, 103, 130)';
const ORANGE = 'rgb(251, 133, 0)';
const YELLOW = 'rgb(255, 183, 3)';
const NIGHT = 'rgb(37, 31, 28)';
const DARK_GROUND = 'rgb(26, 23, 20)';
const PERIWINKLE = 'rgb(188, 181, 227)';
const ARCHIVE_SEED = 'wp eval-file seed-236.php seed archive';
const SINGLE_SEED = 'wp eval-file seed-236.php seed single';
const STREAM_SEED = 'wp eval-file seed-236.php seed stream';
const WIDTHS = [ 1440, 800, 375, 320 ];

const withSlash = ( path ) => path.endsWith( '/' ) ? path : `${ path }/`;

function withQuery( path, name, value ) {
	const url = new URL( path, BASE_URL );
	url.searchParams.set( name, value );
	return `${ url.pathname }${ url.search }`;
}

function archivePage( number, podcast = true ) {
	const path = 1 === number ? ARCHIVE : `${ withSlash( ARCHIVE ) }page/${ number }/`;
	return podcast ? withQuery( path, 'pkiw_listen_type', 'podcast' ) : path;
}

function streamPage( number ) {
	return 1 === number ? STREAM : `${ withSlash( STREAM ) }page/${ number }/`;
}

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
		return { count: links.length, stray: stray.length, hrefs: links.map( ( anchor ) => anchor.href ), names: links.map( name ) };
	} );
}

function nameMatcher( name ) {
	const escaped = name.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	return new RegExp( `^${ escaped.replace( /\s*,\s*/g, '\\s*,\\s*' ) }$` );
}

async function normalizedMarkup( locator, removeSelector = '' ) {
	return locator.evaluate( ( root, selector ) => {
		const clone = root.cloneNode( true );
		if ( selector ) {
			for ( const element of clone.querySelectorAll( selector ) ) element.remove();
		}
		for ( const element of [ clone, ...clone.querySelectorAll( '*' ) ] ) {
			for ( const attribute of [ ...element.attributes ] ) {
				if ( [ 'id', 'href', 'aria-labelledby', 'src', 'srcset' ].includes( attribute.name ) ) {
					element.removeAttribute( attribute.name );
				} else if ( attribute.name === 'class' ) {
					element.setAttribute( 'class', attribute.value.replace( /\bpost-\d+\b/g, 'post-ID' ).replace( /\bwp-image-\d+\b/g, 'wp-image-ID' ) );
				} else if ( attribute.name === 'datetime' ) {
					element.setAttribute( attribute.name, 'DATE' );
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
	}, removeSelector );
}

async function axe( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations.map( ( violation ) => ( {
		id: violation.id,
		nodes: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
	} ) ) );
}

async function fixturePermalink( request, key, seed ) {
	const slug = `seed-236-${ key.toLowerCase() }`;
	const response = await request.get( `/wp-json/wp/v2/posts?slug=${ encodeURIComponent( slug ) }&_fields=link` );
	expect( response.status(), `REST lookup failed for ${ key} (run ${ seed })` ).toBe( 200 );
	const rows = await response.json();
	expect( rows, `fixture ${ key } is missing (run ${ seed })` ).toHaveLength( 1 );
	return rows[ 0 ].link;
}

async function openFixture( page, request, key, seed = SINGLE_SEED, scheme = 'light' ) {
	const href = await fixturePermalink( request, key, seed );
	expect( await visit( page, href, scheme ) ).toBe( 200 );
	return href;
}

async function findOnPages( page, href, paths ) {
	for ( const path of paths ) {
		await visit( page, path );
		const link = page.locator( `a[href="${ href }"]` );
		if ( await link.count() ) return link.first();
	}
	return null;
}

async function stationMap( page ) {
	return page.locator( 'section.pkiw-lineup__station' ).evaluateAll( ( sections ) => sections.map( ( section ) => [
		section.querySelector( 'h2' )?.textContent.trim() || '',
		section.querySelectorAll( 'li' ).length,
	] ) );
}

async function writeOrCompare( locator, path, removeSelector = '' ) {
	const markup = await normalizedMarkup( locator, removeSelector );
	if ( WRITE_BASELINE ) {
		await writeFile( path, `${ markup }\n`, 'utf8' );
		return;
	}
	const baseline = ( await readFile( path, 'utf8' ) ).trim();
	expect( markup ).toBe( baseline );
}

function expectFull() {
	test.skip( 'full' !== STATE, `CR_PODCAST_STATE=${ STATE }; use full after seeding the documented fixture pass` );
}

test.describe( 'podcast full-state surfaces', () => {
	test.beforeEach( async ( { page } ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		if ( 'full' === STATE ) {
			await visit( page, archivePage( 1 ) );
			expect( await page.locator( 'li .pkiw-episode' ).count(), `archive fixtures are missing (run ${ ARCHIVE_SEED })` ).toBeGreaterThan( 0 );
		}
	} );

	test( 'archive — page map', async ( { page } ) => {
		expectFull();
		const expected = {
			1: [ [ 'alder street radio', 5 ], [ 'Brass Tacks FM', 4 ], [ 'Copper Kettle Hour', 3 ] ],
			2: [ [ 'Copper Kettle Hour', 2 ], [ 'Delta Quiet Hours With A Remarkably Long Programme Title That Must Wrap At Every Width Without Clipping Or Ellipsis', 2 ], [ 'Open Source Hours', 1 ], [ 'Signal & Craft', 4 ], [ 'The Long Debug', 2 ], [ 'Other', 1 ] ],
			3: [ [ 'Other', 2 ] ],
		};
		for ( const number of [ 1, 2, 3 ] ) {
			expect( await visit( page, archivePage( number ) ) ).toBe( 200 );
			expect( await stationMap( page ), `archive page ${ number } differs (run ${ ARCHIVE_SEED })` ).toEqual( expected[ number ] );
		}
		expect( await visit( page, archivePage( 4 ) ) ).toBe( 404 );
		await visit( page, archivePage( 1 ) );
		for ( const link of await page.locator( 'nav[aria-label="Pagination"] a' ).all() ) expect( await link.getAttribute( 'href' ) ).toContain( 'pkiw_listen_type=podcast' );
		await visit( page, archivePage( 2 ) );
		for ( const link of await page.locator( 'nav[aria-label="Pagination"] a' ).all() ) expect( await link.getAttribute( 'href' ) ).toContain( 'pkiw_listen_type=podcast' );
		const labels = ( await stationMap( page ) ).map( ( row ) => row[ 0 ] );
		await visit( page, archivePage( 1 ) );
		expect( ( await stationMap( page ) )[ 0 ][ 0 ] ).toBe( 'alder street radio' );
		await visit( page, archivePage( 2 ) );
		expect( labels.at( -1 ) ).toBe( 'Other' );
	} );

	test( 'archive — one chassis and line anatomy', async ( { page, request } ) => {
		expectFull();
		await visit( page, archivePage( 1 ) );
		await expect( page.locator( '.pkiw-lineup' ) ).toHaveCount( 1 );
		for ( const section of await page.locator( 'section.pkiw-lineup__station' ).all() ) {
			await expect( section.locator( 'h2' ) ).toHaveCount( 1 );
			for ( const line of await section.locator( 'li' ).all() ) {
				const links = await visibleLinks( line );
				expect( links.count ).toBe( 1 );
				expect( links.stray ).toBe( 0 );
				await expect( line.locator( 'time[datetime]' ) ).toHaveCount( 1 );
				expect( await line.locator( 'time' ).getAttribute( 'datetime' ) ).toMatch( /^\d{4}-\d{2}-\d{2}$/ );
				await expect( line.locator( 'img, iframe, svg, button, video, audio' ) ).toHaveCount( 0 );
				expect( await line.innerText() ).not.toMatch( /Listened|[←→↗]/i );
				expect( ( await line.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
			}
			expect( await section.locator( 'h2' ).evaluate( ( heading ) => getComputedStyle( heading ).transform ) ).toBe( 'none' );
		}
		for ( const [ key, day ] of [ [ 'A03', '2026-08-02' ], [ 'A05', '2026-07-31' ] ] ) {
			const href = await fixturePermalink( request, key, ARCHIVE_SEED );
			await expect( page.locator( `a[href="${ href }"]` ).locator( 'xpath=ancestor::li[1]' ).locator( 'time' ) ).toHaveAttribute( 'datetime', day );
		}
		for ( const line of await page.getByRole( 'link', { name: /Season Finale/ } ).all() ) {
			const labelled = await line.getAttribute( 'aria-labelledby' );
			expect( labelled ).toBeTruthy();
			const parts = await line.evaluate( ( anchor ) => anchor.getAttribute( 'aria-labelledby' ).split( /\s+/ ).map( ( id ) => document.getElementById( id )?.textContent.trim() || '' ) );
			expect( parts ).toHaveLength( 2 );
			expect( parts[ 0 ] ).toBe( 'Season Finale' );
			expect( parts[ 1 ] ).toMatch( /\d/ );
			const name = ( await visibleLinks( line.locator( 'xpath=ancestor::li[1]' ) ) ).names[ 0 ];
			expect( name ).toMatch( nameMatcher( name ) );
			expect( name ).toMatch( /Season Finale.*\d/ );
		}
		const dial = page.locator( '.cr-podcast-dial' );
		await expect( dial ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( ( await dial.innerText() ).trim() ).toBe( '' );
	} );

	test( 'archive — covers', async ( { page } ) => {
		expectFull();
		for ( const number of [ 1, 2 ] ) {
			await visit( page, archivePage( number ) );
			const sections = page.locator( 'section.pkiw-lineup__station' );
			for ( const section of await sections.all() ) expect( await section.locator( '.pkiw-lineup__cover img' ).count() ).toBeLessThanOrEqual( 1 );
		}
		await visit( page, archivePage( 1 ) );
		const covers = page.locator( '.pkiw-lineup__cover img' );
		await expect( covers.first() ).toHaveAttribute( 'loading', 'eager' );
		await expect( covers.first() ).toHaveAttribute( 'fetchpriority', 'high' );
		for ( const image of await covers.all() ) await expect( image ).toHaveAttribute( 'alt', '' );
		for ( const image of ( await covers.all() ).slice( 1 ) ) await expect( image ).toHaveAttribute( 'loading', 'lazy' );
		const grille = page.locator( '.pkiw-lineup__cover--none' ).first();
		const grilleBox = await grille.boundingBox();
		expect( grilleBox.width ).toBeGreaterThan( 0 );
		expect( grilleBox.height ).toBeGreaterThan( 0 );
		await visit( page, archivePage( 2 ) );
		const delta = page.locator( 'section.pkiw-lineup__station', { has: page.getByRole( 'heading', { name: /^Delta Quiet Hours/ } ) } );
		await expect.poll( async () => delta.locator( '.pkiw-lineup__cover' ).getAttribute( 'class' ) ).toContain( 'cr-cover--failed' );
		const boxes = await page.locator( '.pkiw-lineup__cover' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height } ) ) );
		expect( boxes.every( ( box ) => box.width > 0 && box.height > 0 ) ).toBe( true );
	} );

	test( 'archive — layout at four widths', async ( { page, request } ) => {
		expectFull();
		const d01 = await fixturePermalink( request, 'D01', ARCHIVE_SEED );
		for ( const width of WIDTHS ) {
			await page.setViewportSize( { width, height: 900 } );
			await visit( page, 320 >= width ? archivePage( 2 ) : archivePage( 1 ) );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ) ).toBe( true );
			const station = page.locator( 'section.pkiw-lineup__station' ).first();
			const layout = await station.evaluate( ( node ) => {
				const heading = node.querySelector( 'h2' ).getBoundingClientRect();
				const cover = node.querySelector( '.pkiw-lineup__cover' ).getBoundingClientRect();
				const list = node.querySelector( '.pkiw-lineup__episodes' ).getBoundingClientRect();
				return { heading, cover, list, dial: getComputedStyle( document.querySelector( '.cr-podcast-dial' ) ).display };
			} );
			if ( width >= 800 ) expect( layout.list.left ).toBeGreaterThan( layout.cover.right - 1 );
			if ( width <= 375 ) {
				expect( layout.heading.top ).toBeLessThanOrEqual( layout.cover.top );
				expect( layout.cover.top ).toBeLessThanOrEqual( layout.list.top );
				expect( layout.dial ).toBe( 'none' );
			}
			await visit( page, archivePage( 2 ) );
			const heading = page.getByRole( 'heading', { name: /^Delta Quiet Hours/ } );
			const link = page.locator( `a[href="${ d01 }"]` );
			for ( const target of [ heading, link ] ) {
				const wrap = await target.evaluate( ( node ) => ( { scroll: node.scrollWidth, client: node.clientWidth, overflow: getComputedStyle( node ).textOverflow } ) );
				expect( wrap.scroll ).toBeLessThanOrEqual( wrap.client );
				expect( wrap.overflow ).not.toBe( 'ellipsis' );
			}
		}
		// A 320 CSS-pixel viewport at scale factor 1 represents the 400% reflow target.
		await page.setViewportSize( { width: 320, height: 640 } );
		await visit( page, archivePage( 1 ) );
		expect( await page.evaluate( () => ( { scale: window.devicePixelRatio, fits: document.documentElement.scrollWidth <= document.documentElement.clientWidth } ) ) ).toEqual( { scale: 1, fits: true } );
	} );

	test( 'archive — type nav', async ( { page, browser } ) => {
		expectFull();
		for ( const [ path, current ] of [ [ archivePage( 1 ), 'Podcasts' ], [ ARCHIVE, 'All' ] ] ) {
			await visit( page, path );
			const nav = page.locator( 'nav[aria-label="Listen types"]' );
			await expect( nav ).toHaveCount( 1 );
			expect( await nav.locator( 'a' ).allTextContents() ).toEqual( [ 'All', 'Podcasts' ] );
			await expect( nav.getByRole( 'link', { name: current, exact: true } ) ).toHaveAttribute( 'aria-current', 'page' );
			for ( const link of await nav.locator( 'a' ).all() ) {
				const box = await link.boundingBox();
				expect( box.width ).toBeGreaterThanOrEqual( 44 );
				expect( box.height ).toBeGreaterThanOrEqual( 44 );
				const type = await link.evaluate( ( node ) => ( { style: getComputedStyle( node ).fontStyle, family: getComputedStyle( node ).fontFamily } ) );
				expect( type.style ).toBe( 'normal' );
				expect( type.family.split( ',' )[ 0 ] ).toMatch( /Roboto Slab/ );
			}
			const paint = await nav.locator( '[aria-current="page"]' ).evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, color: getComputedStyle( node ).color } ) );
			expect( paint ).toEqual( { background: VIOLET, color: IVORY } );
		}
		const context = await browser.newContext( { baseURL: BASE_URL, javaScriptEnabled: false } );
		const noScript = await context.newPage();
		await visit( noScript, ARCHIVE );
		const navigation = noScript.waitForResponse( ( response ) => response.request().isNavigationRequest() && response.url().includes( 'pkiw_listen_type=podcast' ) );
		await noScript.getByRole( 'link', { name: 'Podcasts', exact: true } ).click();
		expect( ( await navigation ).status() ).toBe( 200 );
		expect( noScript.url() ).toContain( 'pkiw_listen_type=podcast' );
		await expect( noScript.locator( 'main' ) ).toBeVisible();
		const back = noScript.waitForResponse( ( response ) => response.request().isNavigationRequest() && ! response.url().includes( 'pkiw_listen_type=podcast' ) );
		await noScript.getByRole( 'link', { name: 'All', exact: true } ).click();
		expect( ( await back ).status() ).toBe( 200 );
		await context.close();
	} );

	test( 'archive — keyboard, focus and tab order', async ( { page } ) => {
		expectFull();
		await page.setViewportSize( { width: 1440, height: 900 } );
		await visit( page, archivePage( 1 ) );
		const expected = ( await page.locator( 'a.pkiw-episode__link' ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) ) ).slice( 0, 8 );
		await page.locator( 'nav[aria-label="Listen types"] a' ).last().focus();
		const seen = [];
		for ( let index = 0; index < 8; index++ ) {
			await page.keyboard.press( 'Tab' );
			seen.push( await page.evaluate( () => document.activeElement?.href || '' ) );
		}
		expect( seen ).toEqual( expected );
		const line = page.locator( '.pkiw-episode' ).first();
		await line.locator( 'a' ).focus();
		const focus = await line.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			const rect = node.getBoundingClientRect();
			const chassis = node.closest( '.pkiw-lineup' ).getBoundingClientRect();
			return { outline: `${ style.outlineWidth } ${ style.outlineStyle } ${ style.outlineColor }`, shadow: style.boxShadow, inside: rect.left - 8 >= chassis.left && rect.right + 8 <= chassis.right };
		} );
		expect( focus.outline ).toBe( `3px solid ${ YELLOW }` );
		expect( focus.shadow ).toMatch( /0px 0px 0px 7px/ );
		expect( focus.inside ).toBe( true );
	} );

	test( 'archive — color schemes, forced colors, reduced motion', async ( { page } ) => {
		expectFull();
		await visit( page, archivePage( 1 ), 'dark' );
		let paint = await page.locator( '.pkiw-lineup' ).evaluate( ( node ) => ( {
			background: getComputedStyle( node ).backgroundColor,
			border: getComputedStyle( node ).borderTopColor,
			line: getComputedStyle( node.querySelector( '.pkiw-lineup__episodes > li' ) ).backgroundColor,
			date: getComputedStyle( node.querySelector( '.pkiw-episode__date' ) ).color,
			ground: getComputedStyle( document.body ).backgroundColor,
		} ) );
		expect( paint ).toEqual( { background: PRUSSIAN, border: IVORY, line: NIGHT, date: PERIWINKLE, ground: DARK_GROUND } );
		expect( await axe( page ) ).toEqual( [] );
		await visit( page, archivePage( 1 ), 'light' );
		paint = await page.locator( '.pkiw-lineup' ).evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, border: getComputedStyle( node ).borderTopColor, line: getComputedStyle( node.querySelector( '.pkiw-lineup__episodes > li' ) ).backgroundColor } ) );
		expect( paint ).toEqual( { background: PRUSSIAN, border: VIOLET, line: 'rgb(255, 255, 255)' } );
		expect( await axe( page ) ).toEqual( [] );
		await page.emulateMedia( { forcedColors: 'active' } );
		for ( const selector of [ '.cr-podcast-dial', '.pkiw-lineup__cover--none', '.pkiw-lineup__heading' ] ) {
			const target = page.locator( selector ).first();
			if ( selector === '.pkiw-lineup__heading' ) expect( await target.evaluate( ( node ) => getComputedStyle( node, '::after' ).display ) ).toBe( 'none' ); else await expect( target ).toBeHidden();
		}
		expect( await page.locator( '.pkiw-lineup__station' ).first().evaluate( ( node ) => getComputedStyle( node ).outlineWidth ) ).toBe( '2px' );
		await page.emulateMedia( { forcedColors: 'none', reducedMotion: 'reduce' } );
		const motion = await page.locator( '.cr-archive--podcast *' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { animation: getComputedStyle( node ).animationName, transition: getComputedStyle( node ).transitionDuration } ) ) );
		expect( motion.every( ( item ) => item.animation === 'none' && Number.parseFloat( item.transition ) <= 0.000001 ) ).toBe( true );
	} );

	test( 'archive — header and shelf wiring', async ( { page } ) => {
		expectFull();
		await visit( page, archivePage( 1 ) );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1' ) ).toHaveText( 'Listen' );
		const podcastHeader = await normalizedMarkup( page.locator( '.cr-archive__header' ) );
		await visit( page, ARCHIVE );
		expect( await normalizedMarkup( page.locator( '.cr-archive__header' ) ) ).toBe( podcastHeader );
		await expect( page.locator( 'header.wp-block-template-part' ) ).toHaveCount( 1 );
		await expect( page.locator( 'footer.wp-block-template-part' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-podcast-tile, [class*="taped-margin"]' ) ).toHaveCount( 0 );
		expect( await page.locator( 'main' ).innerText() ).not.toMatch( /Podcasts\s+tile/i );
	} );

	test( 'shelf — only the type nav differs', async ( { page } ) => {
		expectFull();
		await visit( page, ARCHIVE );
		const nav = page.locator( 'nav.wp-block-post-kinds-indieweb-listen-types' );
		await expect( nav ).toHaveCount( 1 );
		expect( await page.locator( '.cr-archive__header' ).evaluate( ( header ) => header.nextElementSibling?.matches( 'nav.wp-block-post-kinds-indieweb-listen-types' ) ) ).toBe( true );
		if ( SHELF_BASELINE ) {
			await writeOrCompare( page.locator( 'main' ), SHELF_BASELINE, 'nav.wp-block-post-kinds-indieweb-listen-types' );
		}
		expect( await page.locator( '.cr-cassette' ).count(), `music cassettes are missing (run ${ ARCHIVE_SEED })` ).toBeGreaterThan( 0 );
	} );

	test( 'privacy — protected podcast', async ( { page, request } ) => {
		expectFull();
		const h01 = await fixturePermalink( request, 'H01', ARCHIVE_SEED );
		for ( const number of [ 1, 2, 3 ] ) {
			await visit( page, archivePage( number ) );
			expect( await page.locator( 'main' ).innerText() ).not.toContain( 'Hidden One' );
			expect( await page.locator( 'main' ).innerHTML() ).not.toContain( 'seed-236-h01' );
			for ( const key of [ 'M01', 'M02', 'M03', 'N01', 'U01', 'U02' ] ) expect( await page.locator( 'main' ).innerHTML() ).not.toContain( `seed-236-${ key.toLowerCase() }` );
		}
		const feed = await request.get( withQuery( `${ withSlash( ARCHIVE ) }feed/`, 'pkiw_listen_type', 'podcast' ) );
		expect( feed.status() ).toBe( 200 );
		const xml = await feed.text();
		expect( feed.headers()[ 'content-type' ] ).toMatch( /xml/ );
		expect( xml ).not.toMatch( /Hidden One|seed-236-h01/i );
		const dates = [ ...xml.matchAll( /<pubDate>([^<]+)<\/pubDate>/g ) ].map( ( match ) => Date.parse( match[ 1 ] ) );
		expect( dates.length ).toBeGreaterThan( 0 );
		expect( dates ).toEqual( [ ...dates ].sort( ( a, b ) => b - a ) );
		expect( await visit( page, h01 ) ).toBe( 200 );
		await expect( page.locator( 'form.post-password-form' ) ).toBeVisible();
		await expect( page.locator( '.cr-podcast-single, .cr-podcast-picture, .wp-block-post-featured-image' ) ).toHaveCount( 0 );
	} );

	test( 'single — record, order and dates', async ( { page, request } ) => {
		expectFull();
		for ( const key of [ 'Q01', 'Q02', 'Q03', 'Q04', 'Q05', 'Q05b', 'Q06', 'Q07', 'Q08', 'Q09', 'Q10' ] ) {
			await openFixture( page, request, key );
			await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
			const structure = await page.locator( '.cr-podcast-single' ).evaluate( ( section ) => [ ...section.children ].map( ( child ) => child.className ) );
			expect( structure ).toEqual( [ 'cr-podcast-single__record', expect.stringMatching( /^cr-podcast-tuner/ ), 'cr-podcast-single__media' ] );
			expect( await page.locator( '.wp-block-post-content' ).evaluate( ( root ) => root.firstElementChild?.matches( 'section.cr-podcast-single' ) ) ).toBe( true );
		}
		await openFixture( page, request, 'Q05' );
		expect( await page.locator( '.cr-podcast-record dt' ).allTextContents() ).not.toContain( 'Episode' );
		await openFixture( page, request, 'Q05b' );
		expect( await page.locator( '.cr-podcast-record dt' ).allTextContents() ).toContain( 'Episode' );
		await openFixture( page, request, 'Q06' );
		const q06Labels = await page.locator( '.cr-podcast-record dt' ).allTextContents();
		expect( q06Labels ).not.toContain( 'Tuned in' );
		expect( q06Labels ).not.toContain( 'Rating' );
		await openFixture( page, request, 'Q03' );
		await expect( page.locator( '.cr-podcast-record__row', { has: page.getByText( 'Tuned in', { exact: true } ) } ).locator( 'dd' ) ).toHaveText( 'September 26, 2026' );
		await openFixture( page, request, 'Q04' );
		await expect( page.locator( '.cr-podcast-record__row', { has: page.getByText( 'Tuned in', { exact: true } ) } ).locator( 'dd' ) ).toHaveText( 'September 27, 2026 at 12:30 AM' );
		const q04 = page.url();
		const archiveLink = await findOnPages( page, q04, [ 1, 2, 3, 4 ].map( ( number ) => archivePage( number ) ) );
		expect( archiveLink, 'Q04 archive line is missing' ).not.toBeNull();
		await expect( archiveLink.locator( 'xpath=ancestor::li[1]' ).locator( 'time' ) ).toHaveAttribute( 'datetime', '2026-09-27' );
		const streamLink = await findOnPages( page, q04, [ 1, 2, 3, 4, 5, 6, 7, 8 ].map( streamPage ) );
		expect( streamLink, 'Q04 Stream card is missing' ).not.toBeNull();
		await expect( streamLink.locator( 'xpath=ancestor::article[1]' ).locator( 'time' ) ).toHaveAttribute( 'datetime', '2026-09-27' );
		await openFixture( page, request, 'Q01' );
		await expect( page.locator( '.cr-podcast-record__row', { hasText: 'Rated 4 of 5' } ).locator( 'dd' ) ).toHaveText( 'Rated 4 of 5' );
		expect( await page.locator( '.cr-podcast-single__kind, .single-post__kind' ).count() ).toBe( 1 );
		await openFixture( page, request, 'Q07' );
		await expect( page.getByRole( 'heading', { name: 'Highlights from this episode' } ) ).toBeVisible();
		await expect( page.getByText( 'A fictional signal becomes clearer after one careful test.' ) ).toBeVisible();
		await openFixture( page, request, 'Q09' );
		await expect( page.locator( 'main img' ) ).toHaveCount( 1 );
		const l01 = await fixturePermalink( request, 'L01', SINGLE_SEED );
		expect( await findOnPages( page, l01, [ 1, 2, 3, 4 ].map( ( number ) => archivePage( number ) ) ) ).not.toBeNull();
		await visit( page, STREAM );
		await expect( page.locator( `a[href="${ l01 }"]` ) ).toHaveCount( 0 );
	} );

	test( 'single — media', async ( { page, request } ) => {
		expectFull();
		await openFixture( page, request, 'Q02' );
		await expect( page.locator( '.cr-podcast-embed iframe' ) ).toHaveCount( 1 );
		expect( new URL( await page.locator( '.cr-podcast-embed iframe' ).getAttribute( 'src' ) ).host ).toBe( 'embed.example.org' );
		await expect( page.locator( '.cr-podcast-picture' ) ).toHaveCount( 0 );
		await openFixture( page, request, 'Q01' );
		await expect( page.locator( '.cr-podcast-picture img.u-photo' ) ).toHaveCount( 1 );
		for ( const key of [ 'Q01', 'Q02', 'Q03', 'Q04', 'Q05', 'Q05b', 'Q06', 'Q07', 'Q08', 'Q09', 'Q10' ] ) {
			await openFixture( page, request, key );
			const rows = page.locator( '.cr-podcast-sources' );
			expect( await rows.count() ).toBeLessThanOrEqual( 1 );
			if ( await rows.count() ) {
				await expect( rows.locator( 'a' ) ).toHaveCount( 1 );
				await expect( rows.locator( 'a' ) ).toHaveAttribute( 'target', '_blank' );
				expect( await rows.innerText() ).not.toMatch( /Rating/i );
			}
			const tuner = page.locator( '.cr-podcast-tuner' );
			await expect( tuner ).toHaveAttribute( 'aria-hidden', 'true' );
			expect( ( await tuner.innerText() ).trim() ).toBe( '' );
		}
		await openFixture( page, request, 'M01', ARCHIVE_SEED );
		await expect( page.locator( '.cr-podcast-single' ) ).toHaveCount( 0 );
	} );

	test( 'single — layout', async ( { page, request } ) => {
		expectFull();
		for ( const width of WIDTHS ) {
			await page.setViewportSize( { width, height: 900 } );
			await openFixture( page, request, 'Q01' );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ) ).toBe( true );
			const layout = await page.locator( '.cr-podcast-single' ).evaluate( ( section ) => {
				const record = section.querySelector( '.cr-podcast-single__record' ).getBoundingClientRect();
				const tuner = section.querySelector( '.cr-podcast-tuner' );
				const media = section.querySelector( '.cr-podcast-single__media' ).getBoundingClientRect();
				const child = section.querySelector( '.cr-podcast-single__record > *' ).getBoundingClientRect();
				const panel = section.querySelector( '.cr-podcast-single__record' );
				const style = getComputedStyle( panel );
				return { record, tuner: tuner.getBoundingClientRect(), media, tunerDisplay: getComputedStyle( tuner ).display, gap: record.height - child.height - Number.parseFloat( style.paddingTop ) - Number.parseFloat( style.paddingBottom ) };
			} );
			if ( width >= 800 ) expect( [ layout.record.left, layout.tuner.left, layout.media.left ] ).toEqual( [ layout.record.left, layout.tuner.left, layout.media.left ].sort( ( a, b ) => a - b ) );
			if ( width <= 375 ) {
				expect( layout.record.top ).toBeLessThanOrEqual( layout.media.top );
				expect( layout.tunerDisplay ).toBe( 'none' );
			}
			expect( Math.abs( layout.gap ) ).toBeLessThanOrEqual( 24 );
		}
		await page.setViewportSize( { width: 320, height: 900 } );
		await openFixture( page, request, 'Q10' );
		const longSingle = await page.locator( '.cr-podcast-single__record' ).evaluate( ( node ) => ( { scroll: node.scrollWidth, client: node.clientWidth } ) );
		expect( longSingle.scroll ).toBeLessThanOrEqual( longSingle.client );
		const nav = page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		await expect( nav ).toHaveCount( 1 );
		await expect( nav.locator( '.wp-block-post-navigation-link' ) ).toHaveCount( 2 );
	} );

	test( 'single — music single is byte-identical', async ( { page, request } ) => {
		expectFull();
		if ( ! MUSIC_BASELINE ) test.skip( true, 'CR_PODCAST_MUSIC_BASELINE is not set' );
		const response = await request.get( `/wp-json/wp/v2/posts?slug=${ encodeURIComponent( MUSIC_SLUG ) }&_fields=link` );
		expect( response.status() ).toBe( 200 );
		const rows = await response.json();
		expect( rows, `music fixture ${ MUSIC_SLUG } is missing (run ${ ARCHIVE_SEED })` ).toHaveLength( 1 );
		await visit( page, rows[ 0 ].link );
		await writeOrCompare( page.locator( 'main' ), MUSIC_BASELINE );
		await expect( page.locator( '.cr-cassette' ) ).toHaveCount( 1 );
		await expect( page.getByText( 'Listen / find it', { exact: true } ) ).toHaveCount( 1 );
		await expect( page.locator( '[role="group"][aria-label*="Rating"], .pk-stars' ) ).toHaveCount( 1 );
	} );

	test( 'single — dark and forced colors', async ( { page, request } ) => {
		expectFull();
		await openFixture( page, request, 'Q01', SINGLE_SEED, 'dark' );
		let paint = await page.locator( '.cr-podcast-single' ).evaluate( ( section ) => ( {
			record: getComputedStyle( section.querySelector( '.cr-podcast-single__record' ) ).backgroundColor,
			text: getComputedStyle( section.querySelector( '.cr-podcast-single__record' ) ).color,
			tunerBorder: getComputedStyle( section.querySelector( '.cr-podcast-tuner' ) ).borderTopColor,
			tunerBackground: getComputedStyle( section.querySelector( '.cr-podcast-tuner' ) ).backgroundColor,
		} ) );
		expect( paint ).toEqual( { record: NIGHT, text: IVORY, tunerBorder: IVORY, tunerBackground: PRUSSIAN } );
		expect( await axe( page ) ).toEqual( [] );
		await visit( page, page.url(), 'light' );
		expect( await axe( page ) ).toEqual( [] );
		await page.emulateMedia( { forcedColors: 'active' } );
		await expect( page.locator( '.cr-podcast-tuner' ) ).toBeHidden();
		paint = await page.locator( '.cr-podcast-single__record, .cr-podcast-single__media' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).borderTopWidth ) );
		expect( paint ).toEqual( [ '2px', '2px' ] );
	} );

	test( 'stream — call-sign card', async ( { page, request } ) => {
		expectFull();
		await visit( page, STREAM );
		const s01 = await fixturePermalink( request, 'S01', STREAM_SEED );
		const card = page.locator( 'article.cr-podcast-card', { has: page.locator( `a[href="${ s01 }"]` ) } );
		expect( await card.count(), `S01 Stream card is missing (run ${ STREAM_SEED })` ).toBe( 1 );
		await expect( card ).toHaveClass( /\bcr-podcast-card\b/ );
		await expect( card ).not.toHaveClass( /\b(?:cr-cassette|k-listen)\b/ );
		const order = await card.evaluate( ( node ) => [ '.pk-kindlabel', '.cr-podcast-card__show', '.cr-podcast-card__title', '.pk-stream-date', '.cr-podcast-card__art' ].map( ( selector ) => [ ...node.querySelectorAll( '*' ) ].indexOf( node.querySelector( selector ) ) ) );
		expect( order ).toEqual( [ ...order ].sort( ( a, b ) => a - b ) );
		const focusables = await card.locator( 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => node.getClientRects().length && ! node.closest( '[hidden]' ) ).map( ( node ) => node.className ) );
		expect( focusables ).toEqual( [ 'u-url' ] );
		expect( await card.innerText() ).not.toContain( '5' );
		const cite = card.locator( '.u-listen-of[hidden]' );
		await expect( cite.locator( 'data.p-name' ) ).toHaveAttribute( 'value', 'First Stream Signal' );
		await expect( cite.locator( 'data.u-url' ) ).toHaveCount( 1 );
		await expect( cite.locator( '.p-author' ) ).toHaveCount( 1 );
		await expect( card.locator( 'time' ) ).toHaveAttribute( 'datetime', '2026-09-26' );
		const s02 = await fixturePermalink( request, 'S02', STREAM_SEED );
		const card2 = page.locator( 'article.cr-podcast-card', { has: page.locator( `a[href="${ s02 }"]` ) } );
		await expect( card2.locator( '.cr-podcast-card__art--knob' ) ).toHaveAttribute( 'aria-hidden', 'true' );
		const post2 = await ( await request.get( '/wp-json/wp/v2/posts?slug=seed-236-s02&_fields=date' ) ).json();
		await expect( card2.locator( 'time' ) ).toHaveAttribute( 'datetime', post2[ 0 ].date.slice( 0, 10 ) );
		const s03 = await fixturePermalink( request, 'S03', STREAM_SEED );
		const card3 = page.locator( 'article.cr-podcast-card', { has: page.locator( `a[href="${ s03 }"]` ) } );
		await expect.poll( async () => card3.locator( '.cr-podcast-card__art' ).getAttribute( 'class' ) ).toContain( 'cr-cover--failed' );
		for ( const target of [ card3.locator( '.cr-podcast-card__show' ), card3.locator( '.cr-podcast-card__title' ) ] ) {
			const wrap = await target.evaluate( ( node ) => ( { scroll: node.scrollWidth, client: node.clientWidth, overflow: getComputedStyle( node ).textOverflow } ) );
			expect( wrap.scroll ).toBeLessThanOrEqual( wrap.client );
			expect( wrap.overflow ).not.toBe( 'ellipsis' );
		}
		for ( const width of [ 375, 320 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await visit( page, STREAM );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ) ).toBe( true );
			for ( const target of [ card3.locator( '.cr-podcast-card__show' ), card3.locator( '.cr-podcast-card__title' ) ] ) {
				const wrap = await target.evaluate( ( node ) => ( { scroll: node.scrollWidth, client: node.clientWidth } ) );
				expect( wrap.scroll ).toBeLessThanOrEqual( wrap.client );
			}
		}
		await page.setViewportSize( { width: 1280, height: 900 } );
		await visit( page, STREAM );
		const focused = page.locator( 'article.cr-podcast-card', { has: page.locator( `a[href="${ s01 }"]` ) } );
		await focused.locator( 'a' ).focus();
		expect( await focused.evaluate( ( node ) => `${ getComputedStyle( node ).outlineWidth } ${ getComputedStyle( node ).outlineStyle } ${ getComputedStyle( node ).outlineColor }` ) ).toBe( `3px solid ${ YELLOW }` );
		const colors = await focused.evaluate( ( node ) => [ getComputedStyle( node.querySelector( 'a' ) ).color, getComputedStyle( node.querySelector( 'time' ) ).color ] );
		for ( const color of colors ) expect( [ 'rgb(33, 158, 188)', 'rgb(142, 202, 230)' ] ).not.toContain( color );
		await visit( page, STREAM, 'dark' );
		const paint = await page.locator( 'article.cr-podcast-card', { has: page.locator( `a[href="${ s01 }"]` ) } ).evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, date: getComputedStyle( node.querySelector( 'time' ) ).color } ) );
		expect( paint ).toEqual( { background: NIGHT, date: PERIWINKLE } );
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = page.locator( 'article.cr-podcast-card', { has: page.locator( `a[href="${ s02 }"]` ) } );
		for ( const decoration of await forced.locator( '.cr-podcast-card__art--knob, .cr-podcast-card__tape' ).all() ) await expect( decoration ).toBeHidden();
		expect( await forced.evaluate( ( node ) => getComputedStyle( node ).borderTopWidth ) ).toBe( '2px' );
		await page.emulateMedia( { forcedColors: 'none', colorScheme: 'light' } );
		const m01 = await fixturePermalink( request, 'M01', ARCHIVE_SEED );
		const music = await findOnPages( page, m01, [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ].map( streamPage ) );
		expect( music, 'M01 music cassette is missing from Stream pages' ).not.toBeNull();
		await expect( music.locator( 'xpath=ancestor::article[1]' ) ).toHaveClass( /\bcr-cassette\b/ );
	} );

	test( 'stream — no stray card furniture', async ( { page, request } ) => {
		expectFull();
		await visit( page, STREAM );
		const s01 = await fixturePermalink( request, 'S01', STREAM_SEED );
		const card = page.locator( 'article.cr-podcast-card', { has: page.locator( `a[href="${ s01 }"]` ) } );
		await expect( card.locator( 'img:not([alt])' ) ).toHaveCount( 0 );
		for ( const image of await card.locator( 'img[alt]' ).all() ) await expect( image ).toHaveAttribute( 'alt', '' );
		await expect( card.locator( '.pk-embed, iframe, .pk-excerpt, .p-summary, .pk-badge' ) ).toHaveCount( 0 );
		expect( await card.innerText() ).not.toMatch( /Listen \/ find it/i );
		expect( await card.locator( 'xpath=ancestor::li[1]' ).evaluate( ( node ) => getComputedStyle( node, '::after' ).content ) ).toBe( 'none' );
	} );

	test( 'stream — network', async ( { page, request } ) => {
		expectFull();
		await fixturePermalink( request, 'S03', STREAM_SEED );
		const hosts = new Set();
		page.on( 'request', ( request ) => hosts.add( new URL( request.url() ).host ) );
		await visit( page, archivePage( 2 ) );
		await visit( page, STREAM );
		const own = new URL( BASE_URL ).host;
		const unexpected = [ ...hosts ].filter( ( host ) => ! [ own, 'img.example.org' ].includes( host ) );
		expect( unexpected, `${ DEV ? 'dev' : 'local' } network hosts` ).toEqual( [] );
	} );
	} );

	test.describe( 'podcast empty state', () => {
		test( 'empty — no podcasts', async ( { page } ) => {
			test.skip( 'empty' !== STATE, 'CR_PODCAST_STATE is not empty' );
			await page.setViewportSize( { width: 1280, height: 900 } );
			expect( await visit( page, archivePage( 1 ) ) ).toBe( 200 );
			await expect( page.locator( '.pkiw-lineup, .cr-podcast-dial' ) ).toHaveCount( 0 );
			await expect( page.locator( '.cr-archive-stream__empty' ) ).toHaveText( /Nothing here yet/ );
			const nav = page.locator( 'nav[aria-label="Listen types"]' );
			await expect( nav ).toHaveCount( 1 );
			await expect( nav.getByRole( 'link', { name: 'Podcasts', exact: true } ) ).toHaveAttribute( 'aria-current', 'page' );
		} );
	} );
