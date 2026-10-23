// `CR_SPEC=collection-240.spec.mjs`: read-only issue 240 collection checks.
// Seed with assets/240-collections/seed-240.php and set
// CR_COLLECTION_REQUIRE_SEED=1 for the orchestrator run. This file performs
// GET requests only and never sets or changes a user agent.
import { test, expect } from '@playwright/test';

const ARCHIVE = ( process.env.CR_COLLECTION_ARCHIVE_PATH || '/kind/collection/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_COLLECTION_STREAM_PATH || '/stream/';
const REQUIRE_SEED = '1' === process.env.CR_COLLECTION_REQUIRE_SEED;
const WIDTHS = [ [ 1440, 3 ], [ 800, 2 ], [ 375, 1 ], [ 320, 1 ] ];
const CASE_WIDTHS = [ [ 1440, 4 ], [ 800, 2 ], [ 375, 1 ], [ 320, 1 ] ];
const PRUSSIAN = 'rgb(2, 48, 71)';
const IVORY = 'rgb(251, 250, 245)';
const VIOLET = 'rgb(36, 28, 74)';
const BLACK = 'rgb(26, 23, 20)';
const YELLOW = 'rgb(255, 183, 3)';
const HIDDEN_TITLES = [ 'Draft secret title', 'Private secret title', 'Password secret title', 'Pending secret title', 'Trashed secret title' ];

const slugOf = ( key ) => `seed-240-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const archivePath = ( page = 1 ) => 1 === page ? ARCHIVE : `${ ARCHIVE }page/${ page }/`;
const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_COLLECTION_REQUIRE_SEED=1: ${ reason }` );
	}
	test.skip( missing, reason );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 390 ? 900 : 1000 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function openArchive( page, number = 1, width = 1440 ) {
	const response = await open( page, archivePath( number ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( `a[href*="${ slugOf( 'C01' ) }"]` ).count() && 1 === number, 'issue 240 archive fixtures are not seeded' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( 'main.single-post' ).count(), `fixture ${ key } is not seeded` );
	return page.locator( 'main.single-post' ).first();
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function assertImagesSameOrigin( page, selector ) {
	for ( const src of await page.locator( selector ).evaluateAll( ( images ) => images.map( ( image ) => image.currentSrc || image.src ) ) ) {
		expect( new URL( src ).origin ).toBe( new URL( page.url() ).origin );
	}
}

const visibleControls = ( scope ) => scope.evaluate( ( root ) => {
	const rendered = ( node ) => ! node.closest( '[hidden]' ) && node.getClientRects().length > 0;
	return [ ...root.querySelectorAll( 'a[href], button, input, select, textarea, [tabindex]' ) ].filter( ( node ) => rendered( node ) && ! node.disabled && node.tabIndex >= 0 ).length;
} );

// Blur radius of each layer: the third length (offset-x, offset-y, blur, spread).
const shadowBlurs = ( shadow ) => 'none' === shadow ? [] : shadow.replace( /rgba?\([^)]*\)/g, '' ).split( ',' ).map( ( layer ) => layer.trim().split( /\s+/ ).filter( ( token ) => /px$/.test( token ) ).map( Number.parseFloat )[ 2 ] ?? 0 );

const gridColumns = ( locator ) => locator.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length );

test.describe( 'collection archive', () => {
	test( 'three pages hold 6, 6 and 3 cabinets and page four is a 404', async ( { page } ) => {
		for ( const [ number, count ] of [ [ 1, 6 ], [ 2, 6 ], [ 3, 3 ] ] ) {
			await openArchive( page, number );
			await expect( page.locator( '.is-style-cr-cabinet-hall > li.wp-block-post' ) ).toHaveCount( count );
		}
		const response = await open( page, archivePath( 4 ) );
		expect( response?.status() ).toBe( 404 );
	} );

	test( 'header, cabinet outline and hidden archive content match the contract', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'Collection' );
		await expect( page.locator( 'main h1 > .cr-cutout__tiles[aria-hidden="true"] .cr-cutout__tile' ) ).toHaveCount( 10 );
		await expect( page.locator( 'span.cr-archive-identity__glyph[aria-hidden="true"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-archive__description' ) ).not.toBeEmpty();
		await expect( page.locator( '.is-style-cr-marker-bar-short' ) ).toHaveCount( 1 );

		const cabinets = page.locator( '.is-style-cr-cabinet-hall article.pk-card--collection-listing' );
		for ( const cabinet of await cabinets.all() ) {
			await expect( cabinet.locator( ':scope > h2.pk-title' ) ).toHaveCount( 1 );
			expect( await visibleControls( cabinet ) ).toBe( 1 );
			expect( await cabinet.locator( '.pk-collection__img' ).count() ).toBeLessThanOrEqual( 4 );
			for ( const image of await cabinet.locator( '.pk-collection__img' ).all() ) {
				expect( await image.getAttribute( 'alt' ) ).toBe( '' );
			}
			const forbidden = await cabinet.locator( '.pk-stream-date, .pk-excerpt, .pk-kindlabel, .h-cite, [class*="arrow"]' ).count();
			expect( forbidden ).toBe( 0 );
			await expect( cabinet.locator( '.p-author:visible' ) ).toHaveCount( 0 );
		}
		const text = ( await cabinets.allInnerTexts() ).join( ' ' );
		for ( const word of [ 'MUG', 'PENCIL', 'NOTEBOOK' ] ) {
			expect( text ).not.toContain( word );
		}
	} );

	test( 'grid columns, visual order, Tab order and equal row heights work at every width', async ( { page } ) => {
		for ( const [ width, columns ] of WIDTHS ) {
			await openArchive( page, 1, width );
			const grid = page.locator( '.wp-block-post-template.is-style-cr-cabinet-hall' );
			expect( await gridColumns( grid ), `${ width }px columns` ).toBe( columns );
			const cabinets = grid.locator( ':scope > li > article.pk-card--collection-listing' );
			const order = await cabinets.evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
				const box = node.getBoundingClientRect();
				return { index, top: Math.round( box.top ), left: Math.round( box.left ), height: Math.round( box.height ) };
			} ) );
			const visual = [ ...order ].sort( ( a, b ) => Math.abs( a.top - b.top ) > 2 ? a.top - b.top : a.left - b.left );
			expect( visual.map( ( item ) => item.index ) ).toEqual( order.map( ( item ) => item.index ) );
			for ( const rowTop of [ ...new Set( order.map( ( item ) => item.top ) ) ] ) {
				expect( new Set( order.filter( ( item ) => item.top === rowTop ).map( ( item ) => item.height ) ).size ).toBe( 1 );
			}
			const links = cabinets.locator( '.pk-collection__link' );
			await links.first().focus();
			for ( let index = 1; index < await links.count(); index++ ) {
				await page.keyboard.press( 'Tab' );
				expect( await links.nth( index ).evaluate( ( link ) => document.activeElement === link ), `${ width }px tab ${ index + 1 }` ).toBe( true );
			}
			if ( 320 === width ) {
				await assertNoOverflow( page, 'archive at 320px' );
			}
		}
	} );

	test( 'glass uses sharp token paint with hard shadows and upright cabinets', async ( { page } ) => {
		await openArchive( page );
		const report = await page.locator( 'article.pk-card--collection-listing' ).first().evaluate( ( card ) => {
			const style = getComputedStyle( card );
			const bay = getComputedStyle( card.querySelector( '.pk-collection__bay' ) );
			return {
				frame: style.borderTopColor,
				width: Number.parseFloat( style.borderTopWidth ),
				radius: style.borderRadius,
				transform: style.transform,
				shadow: style.boxShadow,
				image: bay.backgroundImage,
			};
		} );
		expect( report.frame ).toBe( PRUSSIAN );
		expect( report.width ).toBeGreaterThanOrEqual( 2 );
		expect( report.radius ).toBe( '0px' );
		expect( report.transform ).toBe( 'none' );
		expect( report.image ).not.toMatch( /gradient/i );
		expect( shadowBlurs( report.shadow ).every( ( blur ) => 0 === blur ) ).toBe( true );
	} );

	test( 'native pager follows the grid and exposes 44px current-page semantics', async ( { page } ) => {
		for ( const number of [ 1, 3 ] ) {
			await openArchive( page, number, 320 );
			const grid = page.locator( '.is-style-cr-cabinet-hall' );
			const pager = page.locator( 'nav[aria-label="Pagination"]' );
			await expect( pager ).toHaveCount( 1 );
			expect( await grid.evaluate( ( node ) => node.nextElementSibling?.contains( document.querySelector( 'nav[aria-label="Pagination"]' ) ) || node.nextElementSibling === document.querySelector( 'nav[aria-label="Pagination"]' ) ) ).toBe( true );
			await expect( pager.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
			await expect( pager.getByRole( 'link', { name: 'Previous', exact: true } ) ).toHaveCount( 1 === number ? 0 : 1 );
			await expect( pager.getByRole( 'link', { name: 'Next', exact: true } ) ).toHaveCount( 3 === number ? 0 : 1 );
			for ( const chip of await pager.locator( 'a, .page-numbers' ).all() ) {
				const box = await chip.boundingBox();
				expect( Math.min( box.width, box.height ) ).toBeGreaterThanOrEqual( 44 );
			}
		}
	} );

	test( 'archive media is same-origin and C04 has an empty text-free bay', async ( { page } ) => {
		await openArchive( page, 1 );
		await assertImagesSameOrigin( page, '.pk-card--collection-listing img[src]' );
		await openArchive( page, 2 );
		await assertImagesSameOrigin( page, '.pk-card--collection-listing img[src]' );
		const c04 = page.locator( 'article.pk-card--collection-listing' ).filter( { has: page.locator( `a[href*="${ slugOf( 'C04' ) }"]` ) } );
		await expect( c04.locator( '.pk-collection__bay img' ) ).toHaveCount( 0 );
		expect( ( await c04.locator( '.pk-collection__bay' ).innerText() ).toLowerCase() ).not.toContain( 'no image' );
	} );
} );

test.describe( 'collection singles', () => {
	test( 'C06 and C11 use 4, 2 and 1 case columns with one link per case', async ( { page } ) => {
		for ( const key of [ 'C06', 'C11' ] ) {
			for ( const [ width, columns ] of CASE_WIDTHS ) {
				await openSingle( page, key, width );
				const cabinet = page.locator( '.wp-block-group.is-style-cr-cabinet' );
				await expect( cabinet ).toHaveCount( 1 );
				expect( await gridColumns( cabinet ), `${ key } at ${ width }px` ).toBe( columns );
				for ( const item of await cabinet.locator( '.pkiw-collection-item' ).all() ) {
					expect( await item.locator( 'a[href]' ).count() ).toBeLessThanOrEqual( 1 );
					const label = item.locator( '.pkiw-collection-item__label' );
					if ( await label.count() ) {
						const paint = await label.evaluate( ( node ) => ( { size: Number.parseFloat( getComputedStyle( node ).fontSize ), transform: getComputedStyle( node ).transform } ) );
						expect( paint.size ).toBeGreaterThanOrEqual( 12 );
						expect( paint.transform ).toBe( 'none' );
					}
				}
				if ( 320 === width ) {
					await assertNoOverflow( page, `${ key} at 320px` );
				}
			}
		}
	} );

	test( 'C11 has two occupied rows at desktop and no filler case', async ( { page } ) => {
		await openSingle( page, 'C11' );
		const items = page.locator( '.wp-block-group.is-style-cr-cabinet > .pkiw-collection-item' );
		await expect( items ).toHaveCount( 7 );
		const tops = await items.evaluateAll( ( nodes ) => nodes.map( ( node ) => Math.round( node.getBoundingClientRect().top ) ) );
		expect( new Set( tops ).size ).toBe( 2 );
	} );

	test( 'empty, safe-title and leak-probe singles preserve privacy', async ( { page } ) => {
		await openSingle( page, 'C03' );
		await expect( page.locator( '.pkiw-collection, .pkiw-collection-item' ) ).toHaveCount( 0 );

		await openSingle( page, 'C10' );
		const c10 = await page.locator( 'main' ).innerText();
		expect( c10 ).toMatch( /check-in, /i );
		expect( c10 ).not.toContain( '84.00' );
		expect( c10 ).not.toContain( '219.00' );

		await openSingle( page, 'C14' );
		const html = await page.content();
		expect( html ).not.toContain( 'seed-240-private-attachment' );
		expect( html ).toContain( 'Leak probe external' );
		expect( html ).toContain( 'Probe post' );
		for ( const title of HIDDEN_TITLES ) {
			expect( html ).not.toContain( title );
		}
		for ( const item of await page.locator( '.pkiw-collection-item' ).all() ) {
			await expect( item.locator( 'img' ) ).toHaveCount( 0 );
		}
	} );

	test( 'post navigation follows comments', async ( { page } ) => {
		await openSingle( page, 'C06' );
		const order = await page.locator( 'main.single-post' ).evaluate( ( root ) => {
			const comments = root.querySelector( '.wp-block-comments, .wp-block-comments-query-loop' );
			const nav = root.querySelector( 'nav.cr-post-nav' );
			return Boolean( comments && nav && comments.compareDocumentPosition( nav ) & Node.DOCUMENT_POSITION_FOLLOWING );
		} );
		expect( order ).toBe( true );
	} );

	test( 'single collection images use same-origin media-library URLs', async ( { page } ) => {
		await openSingle( page, 'C07' );
		await assertImagesSameOrigin( page, '.pkiw-collection-item img[src]' );
	} );
} );

test.describe( 'collection Stream cards', () => {
	test( 'S1-S3 fit one column with ordered one-link specimen labels', async ( { page } ) => {
		const response = await open( page, STREAM );
		skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( 'article.pk-card--collection-stream' ).count(), 'collection Stream fixtures are not routed' );
		const cards = page.locator( [ 'C07', 'C08', 'C09' ].map( ( key ) => `article.pk-card--collection-stream:has(a[href*="${ slugOf( key ) }"])` ).join( ', ' ) );
		await expect( cards ).toHaveCount( 3 );
		await assertImagesSameOrigin( page, 'article.pk-card--collection-stream img[src]' );
		for ( const card of await cards.all() ) {
			expect( await visibleControls( card ) ).toBe( 1 );
			await expect( card.locator( '.pk-kindlabel' ) ).toHaveText( 'Collection' );
			await expect( card.locator( '.pk-title' ) ).toHaveCount( 1 );
			await expect( card.locator( '.pk-collection__newest-label' ) ).toHaveText( 'Newest item' );
			await expect( card.locator( '.pk-collection__count, [class*="arrow"], [class*="sprig"]' ) ).toHaveCount( 0 );
			const geometry = await card.evaluate( ( node ) => ( { width: node.getBoundingClientRect().width, parent: node.parentElement.getBoundingClientRect().width } ) );
			expect( geometry.width ).toBeLessThanOrEqual( geometry.parent + 1 );
		}
		const c07 = cards.filter( { has: page.locator( `a[href*="${ slugOf( 'C07' ) }"]` ) } );
		await expect( c07 ).toContainText( 'Old reel' );
		await expect( c07.locator( 'time[datetime="2020-03-04"]' ) ).toHaveCount( 1 );
	} );
} );

test.describe( 'collection display modes', () => {
	test( 'dark mode keeps cabinet object paint fixed and the page dark', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		await openArchive( page );
		expect( await page.locator( 'body' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( BLACK );
		const cabinet = page.locator( 'article.pk-card--collection-listing' ).first();
		const paint = await cabinet.evaluate( ( node ) => {
			const plaque = node.querySelector( '.pk-title' );
			return { frame: getComputedStyle( node ).borderTopColor, paper: getComputedStyle( plaque ).backgroundColor, ink: getComputedStyle( plaque ).color };
		} );
		expect( paint ).toEqual( { frame: PRUSSIAN, paper: IVORY, ink: VIOLET } );
		await cabinet.locator( 'a' ).focus();
		const focus = await cabinet.evaluate( ( node ) => ( { outline: getComputedStyle( node ).outlineColor, shadow: getComputedStyle( node ).boxShadow } ) );
		expect( focus.outline === YELLOW || focus.shadow.includes( YELLOW ) ).toBe( true );
	} );

	test( 'forced colors preserves cabinet, case and card outlines', async ( { page } ) => {
		await openArchive( page );
		await page.emulateMedia( { forcedColors: 'active' } );
		const cabinet = page.locator( 'article.pk-card--collection-listing' ).first();
		expect( await cabinet.evaluate( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }` ) ).toBe( '2px solid' );
		await openSingle( page, 'C06' );
		const item = page.locator( '.pkiw-collection-item' ).first();
		expect( await item.evaluate( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }` ) ).toBe( '2px solid' );
		await open( page, STREAM );
		skipUnlessSeeded( 0 === await page.locator( 'article.pk-card--collection-stream' ).count(), 'collection Stream fixtures are not routed' );
		const card = page.locator( `article.pk-card--collection-stream:has(a[href*="${ slugOf( 'C07' ) }"])` );
		expect( await card.evaluate( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }` ) ).toBe( '2px solid' );
	} );

	test( 'reduced motion removes transitions and focus uses a 3px outline plus 7px halo', async ( { page } ) => {
		await openArchive( page );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		const cabinet = page.locator( 'article.pk-card--collection-listing' ).first();
		expect( Number.parseFloat( await cabinet.evaluate( ( node ) => getComputedStyle( node ).transitionDuration ) ) ).toBeLessThanOrEqual( 0.001 );
		await cabinet.locator( 'a' ).focus();
		const focus = await cabinet.evaluate( ( node ) => ( { width: getComputedStyle( node ).outlineWidth, shadow: getComputedStyle( node ).boxShadow } ) );
		expect( focus.width ).toBe( '3px' );
		expect( focus.shadow ).toContain( '7px' );
		await openSingle( page, 'C06' );
		const item = page.locator( '.pkiw-collection-item' ).first();
		expect( Number.parseFloat( await item.evaluate( ( node ) => getComputedStyle( node ).transitionDuration ) ) ).toBeLessThanOrEqual( 0.001 );
		await item.locator( 'a' ).focus();
		expect( await item.evaluate( ( node ) => getComputedStyle( node ).outlineWidth ) ).toBe( '3px' );
		await open( page, STREAM );
		skipUnlessSeeded( 0 === await page.locator( 'article.pk-card--collection-stream' ).count(), 'collection Stream fixtures are not routed' );
		const card = page.locator( `article.pk-card--collection-stream:has(a[href*="${ slugOf( 'C07' ) }"])` );
		expect( Number.parseFloat( await card.evaluate( ( node ) => getComputedStyle( node ).transitionDuration ) ) ).toBeLessThanOrEqual( 0.001 );
		await card.locator( 'a' ).focus();
		expect( await card.evaluate( ( node ) => getComputedStyle( node ).outlineWidth ) ).toBe( '3px' );
	} );
} );
