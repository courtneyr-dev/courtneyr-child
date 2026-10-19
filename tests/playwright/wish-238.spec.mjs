// `CR_SPEC=wish-238.spec.mjs`: W5-T238 wish archive, single and Stream contracts.
// Read-only GETs; needs a running site seeded by the vault's assets/238-wishes/seed-238.php.
// Remote runs use CR_BASE_URL and CR_ALLOW_REMOTE from the shared config. This file never
// sets or changes a user agent, and the W5-T238 lane runs syntax checks only, not browsers.
//
//   CR_BASE_URL=http://localhost:8892 CR_WISH_REQUIRE_SEED=1 CR_SPEC=wish-238.spec.mjs \
//     npx playwright test --config tests/playwright/playwright.config.mjs
import { test, expect } from '@playwright/test';

const ARCHIVE = ( process.env.CR_WISH_ARCHIVE_PATH || '/kind/wish/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_WISH_STREAM_PATH || '/stream/';
const REQUIRE_SEED = '1' === process.env.CR_WISH_REQUIRE_SEED;
const slugOf = ( key ) => `cr-seed-238-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const SECTION = '.cr-wish-catalog > section.pkiw-group';
const LISTING = '.cr-wish-listing';
const BANNED = [
	'rgb(138, 74, 106)',
	'rgb(208, 137, 163)',
	'rgb(236, 214, 224)',
];

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_WISH_REQUIRE_SEED=1: ${ reason }` );
	}
	test.skip( missing, reason );
}

const archivePage = ( number ) => 1 === number ? ARCHIVE : `${ ARCHIVE }page/${ number }/`;

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 390 ? 900 : 1000 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function seeded( page, path = ARCHIVE, width = 1440 ) {
	const response = await open( page, path, width );
	skipUnlessSeeded( response?.status() === 404, `no page at ${ path }` );
	skipUnlessSeeded( 0 === await page.locator( 'a[href*="cr-seed-238-"]' ).count(), 'issue 238 fixtures are not seeded' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	let response = await open( page, singlePath( key ), width );
	if ( response?.status() === 404 ) {
		for ( const number of [ 1, 2, 3 ] ) {
			await open( page, archivePage( number ), width );
			const link = page.locator( `a[href*="${ slugOf( key ) }"]` ).first();
			if ( await link.count() ) {
				response = await page.goto( await link.getAttribute( 'href' ), { waitUntil: 'load' } );
				break;
			}
		}
	}
	skipUnlessSeeded( response?.status() === 404 || 0 === await page.locator( 'main.single-post' ).count(), `no fixture ${ key }` );
	return page.locator( 'main.single-post' ).first();
}

const itemFor = ( page, key ) => page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

const outsideLinks = ( locator ) => locator.evaluate( ( root ) => [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) ).map( ( link ) => ( { href: link.href, text: link.textContent.trim() } ) ) );

async function visualOrder( page ) {
	return page.locator( SECTION ).evaluateAll( ( nodes ) => {
		const dom = nodes.map( ( node ) => node.querySelector( ':scope > h2' )?.textContent.trim() );
		const visual = [ ...nodes ].sort( ( a, b ) => {
			const x = a.getBoundingClientRect();
			const y = b.getBoundingClientRect();
			return Math.round( x.top ) - Math.round( y.top ) || Math.round( x.left ) - Math.round( y.left );
		} ).map( ( node ) => node.querySelector( ':scope > h2' )?.textContent.trim() );
		return { dom, visual };
	} );
}

async function expectNoHorizontalOverflow( page, selector ) {
	expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
	const offenders = await page.locator( selector ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => node.scrollWidth > node.clientWidth + 1 || node.getBoundingClientRect().right > innerWidth + 1 ).length );
	expect( offenders ).toBe( 0 );
}

async function computedBanReport( page, selector ) {
	return page.locator( `${ selector }, ${ selector } *` ).evaluateAll( ( nodes ) => {
		const found = [];
		for ( const node of nodes ) {
			for ( const pseudo of [ null, '::before', '::after' ] ) {
				const style = getComputedStyle( node, pseudo );
				const values = [ style.color, style.backgroundColor, style.borderColor, style.boxShadow, style.opacity, style.rotate, style.transform ];
				if ( values.some( ( value ) => [ 'rgb(138, 74, 106)', 'rgb(208, 137, 163)', 'rgb(236, 214, 224)' ].includes( value ) ) || '0.75' === style.opacity || /8deg/.test( `${ style.rotate } ${ style.transform }` ) ) {
					found.push( `${ node.tagName }${ pseudo || '' }: ${ values.join( ' | ' ) }` );
				}
			}
		}
		return found;
	} );
}

test.describe( 'wish archive', () => {
	test( 'empty archive shows core no-results with no catalog or pager', async ( { page } ) => {
		await open( page, ARCHIVE );
		test.skip( 0 < await page.locator( LISTING ).count(), 'archive is seeded; run before seeding for the empty proof' );
		await expect( page.locator( 'main' ) ).toContainText( 'Nothing wished for yet.' );
		expect( await page.locator( '.cr-wish-catalog, nav.wp-block-query-pagination' ).count() ).toBe( 0 );
	} );

	test( 'Pass A maps W01-W36 across three native pages and page four is 404', async ( { page } ) => {
		const expected = {
			1: [ 'W01', 'W02', 'W03', 'W04', 'W05', 'W06', 'W07', 'W08', 'W09', 'W10', 'W11', 'W12' ],
			2: [ 'W13', 'W14', 'W15', 'W16', 'W17', 'W18', 'W19', 'W20', 'W21', 'W22', 'W23', 'W24' ],
			3: [ 'W25', 'W26', 'W27', 'W28', 'W29', 'W30', 'W31', 'W32', 'W33', 'W34', 'W35', 'W36' ],
		};
		for ( const number of [ 1, 2, 3 ] ) {
			await seeded( page, archivePage( number ) );
			const hrefs = await page.locator( `${ LISTING } a[href]` ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) );
			expect( hrefs ).toHaveLength( 12 );
			const keys = hrefs.map( ( href ) => href.match( /cr-seed-238-(w\d+)/ )?.[ 1 ]?.toUpperCase() ).sort();
			expect( keys, `page ${ number } holds the planned fixtures` ).toEqual( expected[ number ] );
		}
		const response = await open( page, archivePage( 4 ) );
		expect( response?.status() ).toBe( 404 );
	} );

	test( 'page headings group aliases once and keep Other last', async ( { page } ) => {
		await seeded( page, archivePage( 1 ) );
		expect( await page.locator( `${ SECTION } > h2` ).allTextContents() ).toEqual( [ 'Book', 'Experience', 'Food', 'Game' ] );
		await seeded( page, archivePage( 2 ) );
		expect( ( await page.locator( `${ SECTION } > h2` ).allTextContents() ).filter( ( heading ) => 'Movie/Show' === heading ) ).toHaveLength( 1 );
		await seeded( page, archivePage( 3 ) );
		const headings = await page.locator( `${ SECTION } > h2` ).allTextContents();
		expect( headings.filter( ( heading ) => 'Other' === heading ) ).toHaveLength( 1 );
		expect( headings.at( -1 ) ).toBe( 'Other' );
	} );

	test( 'responsive sections fill 3 plus 1, 2 plus 2 and one-column rows in DOM order', async ( { page } ) => {
		for ( const [ width, rowCounts ] of [ [ 1440, [ 3, 1 ] ], [ 800, [ 2, 2 ] ], [ 375, [ 1, 1, 1, 1 ] ], [ 320, [ 1, 1, 1, 1 ] ] ] ) {
			await seeded( page, ARCHIVE, width );
			const order = await visualOrder( page );
			expect( order.visual, `${ width }px visual order` ).toEqual( order.dom );
			const rows = await page.locator( SECTION ).evaluateAll( ( nodes ) => {
				const grouped = new Map();
				for ( const node of nodes ) {
					const top = Math.round( node.getBoundingClientRect().top );
					grouped.set( top, [ ...( grouped.get( top ) || [] ), node ] );
				}
				return [ ...grouped.values() ].map( ( row ) => ( { count: row.length, left: Math.min( ...row.map( ( node ) => node.getBoundingClientRect().left ) ), right: Math.max( ...row.map( ( node ) => node.getBoundingClientRect().right ) ) } ) );
			} );
			expect( rows.map( ( row ) => row.count ) ).toEqual( rowCounts );
			if ( width >= 800 ) {
				expect( Math.abs( rows[ 0 ].left - rows.at( -1 ).left ) ).toBeLessThanOrEqual( 1 );
				expect( Math.abs( rows[ 0 ].right - rows.at( -1 ).right ) ).toBeLessThanOrEqual( 1 );
			}
		}
	} );

	test( 'listings have one link, high-only circles and fixed source privacy', async ( { page } ) => {
		await seeded( page );
		// One atomic read per page: site-ux.js removes a failed image, so per-listing locator calls would race it.
		const report = await page.locator( LISTING ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( {
			links: [ ...node.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) ).length,
			alts: [ ...node.querySelectorAll( 'img' ) ].map( ( image ) => image.getAttribute( 'alt' ) ),
			priority: node.getAttribute( 'data-priority' ),
			circle: getComputedStyle( node.querySelector( '.cr-wish-listing__priority' ) || node, '::after' ).content,
		} ) ) );
		expect( report.length ).toBeGreaterThan( 0 );
		for ( const item of report ) {
			expect( item.links ).toBe( 1 );
			expect( item.alts.every( ( alt ) => '' === alt ) ).toBe( true );
			expect( 'high' === item.priority ? item.circle !== 'none' : item.circle === 'none' ).toBe( true );
		}
		const remoteKeys = new Set( [ 'w02', 'w17', 'w20' ] );
		for ( const number of [ 1, 2, 3 ] ) {
			await seeded( page, archivePage( number ) );
			// One atomic read: site-ux.js removes a failed image, so a per-image locator loop would race it.
			const pictures = await page.locator( `${ LISTING } img` ).evaluateAll( ( images ) => images.map( ( image ) => ( {
				href: image.closest( 'article' )?.querySelector( 'a[href]' )?.href || '',
				policy: image.getAttribute( 'referrerpolicy' ),
			} ) ) );
			for ( const picture of pictures ) {
				const key = picture.href.match( /cr-seed-238-(w\d+)/ )?.[ 1 ];
				expect( picture.policy, key ).toBe( remoteKeys.has( key ) ? 'no-referrer' : null );
			}
		}
	} );

	test( 'unsafe URL stays noninteractive and a broken remote picture swaps to its tile', async ( { page } ) => {
		await seeded( page, archivePage( 3 ) );
		const unsafe = itemFor( page, 'W27' );
		expect( await unsafe.locator( 'a[href^="javascript:"]' ).count() ).toBe( 0 );
		await seeded( page, archivePage( 2 ) );
		const broken = itemFor( page, 'W17' ).locator( '.cr-wish-listing__media' );
		await expect.poll( () => broken.evaluate( ( node ) => node.classList.contains( 'cr-cover--failed' ) ) ).toBe( true );
		await expect( broken.locator( '.cr-wish-listing__tile' ) ).toBeVisible();
	} );

	test( 'long W16 title wraps at 320 and focused listing gets the ring and halo', async ( { page } ) => {
		await seeded( page, archivePage( 2 ), 320 );
		await expectNoHorizontalOverflow( page, LISTING );
		const w16 = itemFor( page, 'W16' ).locator( LISTING );
		const title = await w16.locator( '.cr-wish-listing__title' ).evaluate( ( node ) => ( { scroll: node.scrollWidth, client: node.clientWidth, overflow: getComputedStyle( node ).overflowWrap } ) );
		expect( title.scroll ).toBeLessThanOrEqual( title.client + 1 );
		expect( title.overflow ).toBe( 'anywhere' );
		await w16.locator( 'a' ).focus();
		const ring = await w16.evaluate( ( node ) => ( { width: getComputedStyle( node ).outlineWidth, shadow: getComputedStyle( node ).boxShadow } ) );
		expect( ring.width ).toBe( '3px' );
		expect( ring.shadow ).toMatch( /7px/ );
	} );

	test( 'archive has no banned computed paint in light or dark and forced colors outlines content', async ( { page }, testInfo ) => {
		await seeded( page );
		expect( await computedBanReport( page, '.cr-wish' ) ).toEqual( [] );
		if ( testInfo.project.name.endsWith( 'dark' ) ) {
			expect( await page.locator( '.cr-wish-catalog' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( 'rgb(251, 250, 245)' );
		}
		await page.emulateMedia( { forcedColors: 'active' } );
		const borders = await page.locator( `${ SECTION }, ${ LISTING }` ).evaluateAll( ( nodes ) => nodes.map( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }` ) );
		expect( borders.every( ( border ) => '2px solid' === border ) ).toBe( true );
		expect( BANNED ).toHaveLength( 3 );
	} );
} );

test.describe( 'wish single', () => {
	test( 'W01 has one H1, one picture, ordered rows, a 44px View item and high circles', async ( { page } ) => {
		const main = await openSingle( page, 'W01' );
		await expect( main.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( main.locator( '.pk-wish img.u-photo' ) ).toHaveCount( 1 );
		expect( await main.locator( '.pk-wish-row > dt' ).allTextContents() ).toEqual( [ 'Type', 'Price', 'Priority', 'Wished' ] );
		for ( const target of [ '.pk-wish-price', '.pk-wish-priority' ] ) {
			expect( await main.locator( target ).evaluate( ( node ) => getComputedStyle( node, '::after' ).content ) ).not.toBe( 'none' );
		}
		const link = main.locator( '.pk-original a.u-url' );
		expect( ( await link.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		expect( ( await link.getAttribute( 'rel' ) ).split( /\s+/ ) ).toEqual( expect.arrayContaining( [ 'noopener', 'noreferrer' ] ) );
	} );

	test( 'no-photo no-reason single omits both panels and every width follows the responsive contract', async ( { page } ) => {
		for ( const width of [ 1440, 800, 375, 320 ] ) {
			const main = await openSingle( page, 'W03', width );
			expect( await main.locator( '.pk-wish-photo, .pk-wish-reason' ).count() ).toBe( 0 );
			await expectNoHorizontalOverflow( page, '.pk-wish' );
		}
	} );

	test( 'photo, listing and reason use desktop, tablet and phone geometry without badge stickers', async ( { page } ) => {
		for ( const [ width, mode ] of [ [ 1440, 'three' ], [ 800, 'two' ], [ 375, 'one' ], [ 320, 'one' ] ] ) {
			const main = await openSingle( page, 'W01', width );
			const boxes = await main.locator( '.pk-wish' ).evaluate( ( card ) => {
				const rect = ( selector ) => card.querySelector( selector ).getBoundingClientRect().toJSON();
				return { photo: rect( '.pk-wish-photo' ), listing: rect( '.pk-wish-listing' ), reason: rect( '.pk-wish-reason' ) };
			} );
			if ( 'three' === mode ) {
				expect( boxes.photo.left ).toBeLessThan( boxes.listing.left );
				expect( boxes.listing.left ).toBeLessThan( boxes.reason.left );
			} else if ( 'two' === mode ) {
				expect( boxes.photo.left ).toBeLessThan( boxes.listing.left );
				expect( boxes.reason.top ).toBeGreaterThanOrEqual( Math.max( boxes.photo.bottom, boxes.listing.bottom ) );
			} else {
				expect( boxes.listing.top ).toBeGreaterThanOrEqual( boxes.photo.bottom );
				expect( boxes.reason.top ).toBeGreaterThanOrEqual( boxes.listing.bottom );
			}
			expect( await main.locator( '.pk-wish-photo .pk-badge, .pk-wish-photo .pk-kindlabel' ).count() ).toBe( 0 );
		}
	} );

	test( 'post navigation follows content and stays wish-scoped when neighbors exist', async ( { page } ) => {
		const main = await openSingle( page, 'W01' );
		const nav = main.locator( 'nav.cr-post-nav' );
		if ( await nav.count() ) {
			const after = await nav.evaluate( ( node ) => {
				const content = document.querySelector( '.single-post__content' );
				return ! content || !! ( content.compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING );
			} );
			expect( after ).toBe( true );
			const hrefs = await nav.locator( 'a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) );
			expect( hrefs.every( ( href ) => href.includes( 'cr-seed-238-' ) ) ).toBe( true );
		}
	} );
} );

test.describe( 'wish Stream', () => {
	test( 'S1-S3 become one-link clippings with ordered chips and no empty media frame', async ( { page } ) => {
		await open( page, STREAM, 1440 );
		skipUnlessSeeded( 0 === await page.locator( `a[href*="${ slugOf( 'S1' ) }"]` ).count(), 'S1-S3 are not on the Stream' );
		for ( const key of [ 'S1', 'S2', 'S3' ] ) {
			const card = itemFor( page, key ).locator( 'article.cr-wish-clipping' );
			await expect( card ).toHaveCount( 1 );
			expect( await outsideLinks( card ) ).toHaveLength( 1 );
			await expect( card.locator( '.cr-wish-clipping__tape[aria-hidden="true"]' ) ).toHaveCount( 1 );
		}
		const s1 = itemFor( page, 'S1' ).locator( '.cr-wish-clipping' );
		const order = await s1.locator( '.pk-caption' ).innerText();
		expect( order ).toMatch( /Tech[\s\S]*High priority[\s\S]*\$[\s\S]*\d{4}/ );
		expect( await s1.locator( '.pk-wish-chip--priority' ).evaluate( ( node ) => getComputedStyle( node, '::after' ).content ) ).not.toBe( 'none' );
		expect( await itemFor( page, 'S2' ).locator( '.pk-media, [data-cr-cover-fallback]' ).count() ).toBe( 0 );
	} );

	test( 'Stream clippings fit 320 and 375 and contain no banned paint', async ( { page } ) => {
		for ( const width of [ 375, 320 ] ) {
			await open( page, STREAM, width );
			const cards = page.locator( 'article.cr-wish-clipping' );
			skipUnlessSeeded( 0 === await cards.count(), 'wish Stream cards are absent' );
			await expectNoHorizontalOverflow( page, 'article.cr-wish-clipping' );
			expect( await computedBanReport( page, 'article.cr-wish-clipping' ) ).toEqual( [] );
		}
	} );
} );

test.describe( 'wish controls', () => {
	test( 'C01 protected wish is title-only and files under Other without leaking elsewhere', async ( { page } ) => {
		let found = null;
		for ( const number of [ 1, 2, 3, 4 ] ) {
			const response = await open( page, archivePage( number ) );
			if ( response?.status() === 404 ) {
				break;
			}
			const item = itemFor( page, 'C01' );
			if ( await item.count() ) {
				found = item;
				await expect( item.locator( 'article.pk-card--protected' ) ).toHaveCount( 1 );
				expect( await item.innerText() ).not.toMatch( /seed238|Reason|Price|\$1299/i );
				await expect( item.locator( 'xpath=ancestor::section[1]/h2' ) ).toHaveText( 'Other' );
			}
		}
		skipUnlessSeeded( null === found, 'C01 control is not seeded' );
	} );

	test( 'C07 untitled single has one Fictional Lantern H1 and no second p-name', async ( { page } ) => {
		const main = await openSingle( page, 'C07' );
		await expect( main.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( main.locator( 'h1' ) ).toHaveText( 'Fictional Lantern' );
		expect( await main.locator( '.h-entry .p-name' ).count() ).toBeLessThanOrEqual( 1 );
	} );
} );
