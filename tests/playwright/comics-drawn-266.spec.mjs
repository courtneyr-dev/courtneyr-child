// `CR_SPEC=comics-drawn-266.spec.mjs`: issue 266 drawn-comic archive, single and Stream contracts.
// Read-only GETs; requires a running site seeded by the vault's seed-266.php fixtures.
// Remote access uses the shared config. This file never sets or changes a user agent.
//
//   CR_BASE_URL=http://localhost:8892 CR_COMICS_REQUIRE_SEED=1 CR_SPEC=comics-drawn-266.spec.mjs \
//     npx playwright test --config tests/playwright/playwright.config.mjs
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_COMICS_ARCHIVE_PATH || '/kind/comics/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_COMICS_STREAM_PATH || '/stream/';
const REQUIRE_SEED = '1' === process.env.CR_COMICS_REQUIRE_SEED;
const DRAWN_QUERY = 'pkiw_comic_view=drawn';
const SERIES_QUERY = 'pkiw_comic_view=drawn&pkiw_comic_series=Field+Notes';
const WIDTHS = [ 1440, 800, 375, 320 ];
const D01_ALTS = [
	'Panel 1: a person at a desk, thinking',
	'Panel 2: a pencil circles a missing image description',
	'Panel 3: a speech bubble points to a blank alt field',
	'Panel 4: a check mark appears beside the finished description',
];

const slugOf = ( key ) => `seed-266-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const drawnPath = ( page = 1 ) => `${ ARCHIVE }${ 1 === page ? '' : `page/${ page }/` }?${ DRAWN_QUERY }`;
const seriesPath = () => `${ ARCHIVE }?${ SERIES_QUERY }`;

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_COMICS_REQUIRE_SEED=1: ${ reason }` );
	}
	test.skip( missing, reason );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 390 ? 900 : 1000 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function openSeeded( page, path = drawnPath(), width = 1440 ) {
	const response = await open( page, path, width );
	skipUnlessSeeded( 404 === response?.status(), `no page at ${ path }` );
	skipUnlessSeeded( 0 === await page.locator( 'a[href*="seed-266-"]' ).count(), 'issue 266 archive fixtures are not seeded' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( '.cr-strip--single, form.post-password-form' ).count(), `no fixture ${ key }` );
	return page.locator( 'main.single-post, main' ).first();
}

const itemFor = ( page, key ) => page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

async function outsideLinks( locator ) {
	return locator.evaluate( ( root ) => [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) && 0 < link.getClientRects().length ) );
}

async function expectNoHorizontalOverflow( page, selector = 'main' ) {
	expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
	if ( null === selector ) {
		return;
	}
	const offenders = await page.locator( selector ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => node.scrollWidth > node.clientWidth + 1 || node.getBoundingClientRect().right > innerWidth + 1 ).length );
	expect( offenders ).toBe( 0 );
}

async function axeMain( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => {
		const main = document.querySelector( 'main' );
		return ( await window.axe.run( main ) ).violations.map( ( violation ) => ( {
			id: violation.id,
			targets: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
		} ) );
	} );
}

async function accessibleName( link ) {
	return link.evaluate( ( anchor ) => {
		const ids = ( anchor.getAttribute( 'aria-labelledby' ) || '' ).split( /\s+/ ).filter( Boolean );
		return ids.length ? ids.map( ( id ) => document.getElementById( id )?.textContent.trim() || '' ).join( ' ' ) : anchor.textContent.trim();
	} );
}

test.describe( 'drawn comics archive', () => {
	test( 'native page map, series filter and unfiltered rack stay separate', async ( { page } ) => {
		const expected = {
			1: [ 'D01', 'D02', 'D03', 'D04', 'D05', 'D06', 'D07', 'D08', 'D09', 'D10', 'D11', 'X04' ],
			2: [ 'D12', 'D13', 'D14' ],
		};
		for ( const number of [ 1, 2 ] ) {
			await openSeeded( page, drawnPath( number ) );
			const hrefs = await page.locator( '.cr-sketch-item a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) );
			const keys = hrefs.map( ( href ) => href.match( /seed-266-([a-z]\d{2})/ )?.[ 1 ]?.toUpperCase() ).filter( Boolean ).sort();
			expect( keys, `page ${ number } fixture keys` ).toEqual( expected[ number ].sort() );
		}
		expect( ( await open( page, drawnPath( 3 ) ) )?.status() ).toBe( 404 );

		await openSeeded( page, seriesPath() );
		expect( await page.locator( '.cr-sketch-item' ).count() ).toBe( 7 );
		expect( await page.locator( 'nav.wp-block-query-pagination' ).count() ).toBe( 0 );

		await open( page, ARCHIVE );
		skipUnlessSeeded( 0 === await page.locator( '.cr-comic-rack' ).count(), 'unfiltered comics rack is unavailable' );
		expect( await page.locator( '.pk-comic-series, .pkiw-group, .cr-sketchbook' ).count() ).toBe( 0 );
	} );

	test( 'header and series navigation preserve the shared archive shell', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await openSeeded( page, drawnPath(), width );
			await expect( page.locator( 'main h1.cr-archive__title .cr-cutout__text' ) ).toHaveText( 'Comics' );
			await expect( page.locator( 'main h1.cr-archive__title .cr-cutout__tiles[aria-hidden="true"] .cr-cutout__tile' ) ).toHaveCount( 6 );
			expect( ( await page.locator( 'main' ).innerText() ).includes( 'SAMPLE CONTENT' ) ).toBe( false );
			const nav = page.locator( 'nav.pk-comic-series' );
			await expect( nav ).toHaveCount( 1 );
			await expect( nav.locator( 'a[aria-current="page"]' ) ).toHaveCount( 1 );
			const targets = await nav.locator( 'a' ).evaluateAll( ( links ) => links.map( ( link ) => link.getBoundingClientRect().height ) );
			expect( targets.every( ( height ) => height >= 44 ) ).toBe( true );
			await expectNoHorizontalOverflow( page, 'nav.pk-comic-series, .cr-sketchbook-loop' );
		}
	} );

	test( 'sketchbook groups and items use the planned responsive geometry', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await openSeeded( page, drawnPath(), width );
			const sketchbook = page.locator( '.cr-sketchbook' );
			const style = await sketchbook.evaluate( ( node ) => ( {
				border: getComputedStyle( node ).borderTopWidth,
				style: getComputedStyle( node ).borderTopStyle,
			} ) );
			expect( style ).toEqual( { border: '2px', style: 'solid' } );
			const headings = page.locator( '.cr-sketchbook .pkiw-group__heading' );
			for ( const heading of await headings.all() ) {
				const type = await heading.evaluate( ( node ) => ( { family: getComputedStyle( node ).fontFamily, style: getComputedStyle( node ).fontStyle } ) );
				expect( type.family ).toContain( 'Roboto Slab' );
				expect( type.style ).toBe( 'normal' );
			}
			const rows = await page.locator( '.cr-sketchbook .pkiw-group__items > li' ).evaluateAll( ( nodes ) => {
				const grouped = new Map();
				for ( const node of nodes ) {
					const top = Math.round( node.getBoundingClientRect().top );
					grouped.set( top, [ ...( grouped.get( top ) || [] ), node ] );
				}
				return [ ...grouped.values() ].map( ( row ) => row.length );
			} );
			expect( Math.max( ...rows ) ).toBe( width >= 800 ? 2 : 1 );
			const fill = await page.locator( '.cr-sketchbook .pkiw-group__items' ).evaluateAll( ( lists ) => lists.map( ( list ) => list.getBoundingClientRect().width / list.parentElement.getBoundingClientRect().width ) );
			expect( Math.min( ...fill ), 'item grid spans the sketchbook page' ).toBeGreaterThan( 0.99 );
			const order = await page.locator( '.cr-sketch-item' ).evaluateAll( ( nodes ) => {
				const dom = nodes.map( ( node ) => node.querySelector( 'a' )?.href );
				const visual = [ ...nodes ].sort( ( a, b ) => {
					const x = a.getBoundingClientRect();
					const y = b.getBoundingClientRect();
					return Math.round( x.top ) - Math.round( y.top ) || Math.round( x.left ) - Math.round( y.left );
				} ).map( ( node ) => node.querySelector( 'a' )?.href );
				return { dom, visual };
			} );
			expect( order.visual ).toEqual( order.dom );
			await expectNoHorizontalOverflow( page, '.cr-sketchbook, .cr-sketch-item' );
		}
	} );

	test( 'items keep stored panel order, atomic links and empty-panel behavior', async ( { page } ) => {
		await openSeeded( page );
		const items = page.locator( '.cr-sketch-item' );
		for ( const item of await items.all() ) {
			const shape = await item.evaluate( ( node ) => ( {
				images: node.querySelectorAll( 'img' ).length,
				links: [ ...node.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) ).length,
				times: node.querySelectorAll( 'time' ).length,
				text: node.textContent,
				forbidden: node.querySelectorAll( '.pk-kindlabel, .pk-meta, .pk-excerpt' ).length,
			} ) );
			expect( shape.images ).toBeLessThanOrEqual( 2 );
			expect( shape.links ).toBe( 1 );
			expect( shape.times ).toBe( 0 );
			expect( shape.text ).not.toContain( 'Transcript available' );
			expect( shape.forbidden ).toBe( 0 );
		}
		const d09 = itemFor( page, 'D09' );
		expect( await d09.locator( 'img' ).evaluateAll( ( images ) => images.map( ( image ) => image.getAttribute( 'alt' ) ) ) ).toEqual( [ expect.any( String ), '' ] );
		await openSeeded( page, drawnPath( 2 ) );
		await expect( itemFor( page, 'D13' ).locator( 'img' ) ).toHaveCount( 0 );
		const blank = await itemFor( page, 'D13' ).locator( '.cr-sketch-item' ).evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return {
				title: node.querySelector( '.pk-title' ).getBoundingClientRect().width,
				content: node.clientWidth - parseFloat( style.paddingLeft ) - parseFloat( style.paddingRight ),
			};
		} );
		expect( Math.abs( blank.title - blank.content ), 'no-panel title spans the item, not a badge column' ).toBeLessThanOrEqual( 1 );

		await openSeeded( page );
		const d03Name = await accessibleName( itemFor( page, 'D03' ).locator( 'a' ) );
		const d08Name = await accessibleName( itemFor( page, 'D08' ).locator( 'a' ) );
		expect( d03Name ).not.toBe( d08Name );
	} );

	test( 'pager follows the sketchbook and first-row panels are prioritized', async ( { page } ) => {
		await openSeeded( page );
		const pager = page.locator( 'nav.wp-block-query-pagination' );
		await expect( pager ).toHaveAttribute( 'aria-label', 'Pagination' );
		await expect( pager.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( 0 );
		await expect( pager.locator( '.wp-block-query-pagination-next' ) ).toHaveCount( 1 );
		await expect( pager.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
		expect( await pager.evaluate( ( node ) => Boolean( document.querySelector( '.cr-sketchbook' )?.compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) ) ).toBe( true );
		const firstImages = page.locator( '.cr-sketch-item' ).nth( 0 ).locator( 'img' );
		for ( const image of await firstImages.all() ) {
			await expect( image ).toHaveAttribute( 'loading', 'eager' );
			await expect( image ).toHaveAttribute( 'fetchpriority', 'high' );
		}
		const link = itemFor( page, 'D01' ).locator( 'a' );
		await link.focus();
		expect( await itemFor( page, 'D01' ).locator( '.cr-sketch-item' ).evaluate( ( node ) => getComputedStyle( node ).outlineWidth ) ).toBe( '3px' );

		await openSeeded( page, drawnPath( 2 ) );
		await expect( page.locator( '.wp-block-query-pagination-next' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( 1 );
	} );
} );

test.describe( 'drawn comic single', () => {
	test( 'D01 prints one ordered spread with stored alts and transcript', async ( { page } ) => {
		const main = await openSingle( page, 'D01' );
		const spread = main.locator( '.cr-strip--single' );
		await expect( spread.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( spread.locator( 'h1.p-name' ) ).toHaveText( 'The missing alt text' );
		expect( await spread.locator( '.cr-strip__row > dt' ).allTextContents() ).toEqual( [ 'Date', 'Series' ] );
		await expect( spread.locator( '.cr-strip__row a' ) ).toHaveAttribute( 'href', /pkiw_comic_view=drawn.*pkiw_comic_series=/ );
		await expect( spread.locator( '.cr-strip__panel' ) ).toHaveCount( 4 );
		expect( await spread.locator( 'img.u-photo' ).evaluateAll( ( images ) => images.map( ( image ) => image.getAttribute( 'alt' ) ) ) ).toEqual( D01_ALTS );
		await expect( spread.locator( 'figcaption' ) ).toHaveCount( 0 );
		await expect( spread.locator( 'h2.cr-strip__transcript-title' ) ).toHaveText( 'Transcript' );
		expect( await spread.locator( '.cr-strip__entry > dt' ).allTextContents() ).toEqual( [ 'Panel 1', 'Panel 2', 'Panel 3', 'Panel 4' ] );
		for ( const [ index, panel ] of ( await spread.locator( '.cr-strip__panel' ).all() ).entries() ) {
			const tab = panel.locator( '.cr-strip__tab' );
			await expect( tab ).toHaveText( `Panel ${ index + 1 }` );
			const boxes = { tab: await tab.boundingBox(), figure: await panel.locator( 'figure' ).boundingBox() };
			expect( boxes.tab.y + boxes.tab.height ).toBeLessThanOrEqual( boxes.figure.y + 1 );
		}
		const entry = main.locator( '.h-entry.pkiw-singular-entry' ).first();
		await expect( entry.locator( '.p-name:visible' ) ).toHaveCount( 1 );
		await expect( entry.locator( '.u-photo' ) ).toHaveCount( 4 );
		await expect( entry.locator( '.dt-published:visible' ) ).toHaveCount( 1 );
		await expect( entry.locator( '.u-read-of' ) ).toHaveCount( 0 );
	} );

	test( 'spread changes at 64rem without changing DOM order', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			const main = await openSingle( page, 'D01', width );
			const spread = main.locator( '.cr-strip--single' );
			const geometry = await spread.evaluate( ( root ) => {
				const head = root.querySelector( '.cr-strip__head' ).getBoundingClientRect();
				const panels = root.querySelector( '.cr-strip__panels' ).getBoundingClientRect();
				return { head: head.toJSON(), panels: panels.toJSON(), order: [ ...root.children ].map( ( child ) => child.className ) };
			} );
			expect( geometry.order.slice( 0, 2 ) ).toEqual( [ 'cr-strip__head', 'cr-strip__panels' ] );
			if ( width >= 1024 ) {
				expect( geometry.panels.right ).toBeLessThanOrEqual( geometry.head.left + 2 );
			} else {
				expect( geometry.panels.top ).toBeGreaterThanOrEqual( geometry.head.bottom );
			}
			await expectNoHorizontalOverflow( page, '.cr-strip--single' );
		}
	} );

	test( 'D06, D14 and X04 cover transcript, one-panel and zine precedence', async ( { page } ) => {
		let main = await openSingle( page, 'D06' );
		await expect( main.locator( '.cr-strip__transcript' ) ).toHaveCount( 0 );

		main = await openSingle( page, 'D14', 1440 );
		await expect( main.locator( '.cr-strip__panel' ) ).toHaveCount( 1 );
		const sizes = await main.locator( '.cr-strip__panel' ).evaluate( ( panel ) => ( { panel: panel.getBoundingClientRect().width, list: panel.parentElement.getBoundingClientRect().width } ) );
		expect( sizes.panel / sizes.list ).toBeGreaterThan( 0.9 );

		main = await openSingle( page, 'X04' );
		await expect( main.locator( '.cr-strip--single' ) ).toHaveCount( 1 );
		await expect( main.locator( '.cr-zine' ) ).toHaveCount( 0 );
	} );

	test( 'post navigation follows content and protected X01 keeps the core form', async ( { page } ) => {
		const main = await openSingle( page, 'D01' );
		const nav = main.locator( 'nav.cr-post-nav' );
		if ( await nav.count() ) {
			await expect( nav ).toHaveAttribute( 'aria-label', 'Post navigation' );
			expect( await nav.evaluate( ( node ) => Boolean( document.querySelector( '.single-post__content' )?.compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) ) ).toBe( true );
			const heights = await nav.locator( 'a' ).evaluateAll( ( links ) => links.map( ( link ) => link.getBoundingClientRect().height ) );
			expect( heights.every( ( height ) => height >= 44 ) ).toBe( true );
		}

		await open( page, singlePath( 'X01' ) );
		skipUnlessSeeded( 0 === await page.locator( 'form.post-password-form' ).count(), 'protected X01 fixture is unavailable' );
		await expect( page.locator( '.cr-strip--single' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'drawn comic Stream card', () => {
	test( 'S1-S3 have one-link notebook cards and transcript disclosures', async ( { page } ) => {
		await open( page, STREAM );
		skipUnlessSeeded( 0 === await page.locator( `a[href*="${ slugOf( 'S1' ) }"]` ).count(), 'S1-S3 are not on the Stream' );
		const archiveName = 'Comics';
		for ( const key of [ 'S1', 'S2', 'S3' ] ) {
			const card = itemFor( page, key ).locator( '.cr-strip-card' );
			await expect( card ).toHaveCount( 1 );
			expect( await outsideLinks( card ) ).toHaveLength( 1 );
			await expect( card.locator( '.pk-kindlabel' ) ).toHaveText( archiveName );
			expect( ( await card.locator( '.pk-kindlabel' ).innerText() ).trim() ).not.toBe( 'Comic' );
			await expect( card.locator( '.cr-strip__tab' ) ).toHaveAttribute( 'aria-hidden', 'true' );
			expect( await card.locator( 'img' ).getAttribute( 'alt' ), 'Stream panel alt is empty; the full alt lives on the single' ).toBe( '' );
			const paper = await card.evaluate( ( node ) => {
				const style = getComputedStyle( node );
				const probe = document.createElement( 'span' );
				probe.style.cssText = 'background: var(--cr-surface-elevated); box-shadow: var(--cr-shadow-hard)';
				node.appendChild( probe );
				const expected = { background: getComputedStyle( probe ).backgroundColor, shadow: getComputedStyle( probe ).boxShadow };
				probe.remove();
				return {
					border: `${ style.borderTopWidth } ${ style.borderTopStyle }`,
					background: style.backgroundColor,
					shadow: style.boxShadow,
					perforations: getComputedStyle( node, '::before' ).content,
					expected,
				};
			} );
			expect( paper.border, 'Stream paper keeps its 2px ink edge' ).toBe( '2px solid' );
			expect( paper.background, 'Stream paper is the elevated surface' ).toBe( paper.expected.background );
			expect( paper.shadow, 'Stream paper keeps the hard shadow token' ).toBe( paper.expected.shadow );
			expect( paper.perforations, 'Stream paper keeps its perforations' ).toBe( '""' );
			expect( ( await card.innerText() ).includes( 'Read comic' ) ).toBe( false );
			expect( ( await card.innerText() ).includes( 'Transcript available' ) ).toBe( false );
			await expect( card.locator( 'details' ) ).toHaveCount( 'S2' === key ? 0 : 1 );
		}
	} );

	test( 'native summary opens from the keyboard without navigating', async ( { page } ) => {
		await open( page, STREAM, 375 );
		const card = itemFor( page, 'S1' ).locator( '.cr-strip-card' );
		skipUnlessSeeded( 0 === await card.count(), 'S1 is not on the Stream' );
		const summary = card.locator( 'summary' );
		expect( ( await summary.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		const before = page.url();
		await summary.focus();
		await page.keyboard.press( 'Enter' );
		await expect( card.locator( 'details' ) ).toHaveAttribute( 'open', '' );
		expect( page.url() ).toBe( before );
		await page.keyboard.press( 'Space' );
		expect( page.url() ).toBe( before );
	} );

	test( 'cards fit phones and keep every computed text size at 12px or above', async ( { page } ) => {
		for ( const width of [ 375, 320 ] ) {
			await open( page, STREAM, width );
			const cards = page.locator( '.cr-strip-card' );
			skipUnlessSeeded( 0 === await cards.count(), 'drawn Stream cards are absent' );
			await expectNoHorizontalOverflow( page, '.cr-strip-card' );
			const small = await cards.evaluateAll( ( roots ) => roots.flatMap( ( root ) => [ root, ...root.querySelectorAll( '*' ) ] ).filter( ( node ) => node.childElementCount === 0 && node.textContent.trim() && Number.parseFloat( getComputedStyle( node ).fontSize ) < 12 ).map( ( node ) => `${ node.tagName }:${ getComputedStyle( node ).fontSize }` ) );
			expect( small ).toEqual( [] );
		}
	} );
} );

test.describe( 'cross-cutting states', () => {
	test( 'archive, single and Stream have no axe violations, overflow or console errors', async ( { page } ) => {
		const errors = [];
		page.on( 'console', ( message ) => {
			if ( 'error' === message.type() ) {
				errors.push( message.text() );
			}
		} );
		for ( const path of [ drawnPath(), singlePath( 'D01' ), STREAM ] ) {
			await open( page, path, 320 );
			skipUnlessSeeded( 0 === await page.locator( '.cr-sketchbook, .cr-strip--single, .cr-strip-card' ).count(), `fixtures absent at ${ path }` );
			expect( await axeMain( page ), `axe at ${ path }` ).toEqual( [] );
			// Page-level scroll only: the site-wide newsletter band is 100vw and rotated, so it overruns main without scrolling the page.
			await expectNoHorizontalOverflow( page, null );
		}
		expect( errors ).toEqual( [] );
	} );

	test( 'dark, forced-colors and reduced-motion modes retain visible content', async ( { page } ) => {
		for ( const path of [ drawnPath(), singlePath( 'D01' ), STREAM ] ) {
			await page.emulateMedia( { colorScheme: 'dark', reducedMotion: 'reduce' } );
			await open( page, path );
			skipUnlessSeeded( 0 === await page.locator( '.cr-sketchbook, .cr-strip--single, .cr-strip-card' ).count(), `fixtures absent at ${ path }` );
			await page.evaluate( () => {
				document.documentElement.dataset.theme = 'dark';
			} );
			expect( await page.locator( '.cr-sketchbook, .cr-strip--single, .cr-strip-card' ).first().evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).not.toBe( 'rgba(0, 0, 0, 0)' );
			await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
			const frame = page.locator( '.cr-sketchbook, .cr-strip--single, .cr-strip-card' ).first();
			expect( await frame.evaluate( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }` ) ).toBe( '2px solid' );
			await expect( page.locator( 'main img' ).first() ).toBeVisible();
		}
	} );

	test( 'private fixture series never enter public navigation', async ( { page } ) => {
		await openSeeded( page );
		const nav = await page.locator( 'nav.pk-comic-series' ).innerText();
		for ( const secret of [ 'Secret Series', 'Private Series', 'Draft Series' ] ) {
			expect( nav ).not.toContain( secret );
		}
	} );
} );
