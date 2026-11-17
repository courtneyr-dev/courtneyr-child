// `CR_SPEC=otd-268.spec.mjs CR_OTD_SET=<archive|single|stream|headers|empty|a11y>`:
// X-T268 On This Day theme acceptance. Read-only GETs.
import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_OTD_ARCHIVE_PATH || '/on-this-day/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_OTD_STREAM_PATH || '/stream/';
const SET = process.env.CR_OTD_SET || 'archive';
const EDGE_STUB = '1' === process.env.CR_OTD_EDGE_STUB;
const WIDTHS = [ 1440, 800, 375, 320 ];
const STREAM_WIDTHS = [ 1440, 768, 375, 320 ];
const YELLOW = 'rgb(255, 183, 3)';
const DARK_PAGE = 'rgb(26, 23, 20)';
const DARK_CARD = 'rgb(37, 31, 28)';
const IVORY = 'rgb(251, 250, 245)';
const A11Y_BASELINE_PATH = fileURLToPath( new URL( '../a11y-expected-failures.json', import.meta.url ) );

const archivePath = ( number = 1 ) => 1 === number ? ARCHIVE : `${ ARCHIVE }page/${ number }/`;
const archiveCards = ( page ) => page.locator( '.pkiw-otd-year__items > li' );
const streamCard = ( page ) => page.locator( '.pkiw-otd--stream' );

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 400 ? 900 : 1100 } );
	const response = await page.goto( path, { waitUntil: 'load' } );
	await page.evaluate( () => document.fonts.ready );
	return response;
}

async function openArchive( page, number = 1, width = 1440 ) {
	const response = await open( page, archivePath( number ), width );
	expect( response?.status(), `archive page ${ number } status` ).toBe( 200 );
	return response;
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function gridColumns( locator ) {
	return locator.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length );
}

async function visualOrder( locator ) {
	return locator.evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
		const box = node.getBoundingClientRect();
		return { index, top: Math.round( box.top ), left: Math.round( box.left ) };
	} ) );
}

function sortedVisualIndexes( positions ) {
	return [ ...positions ]
		.sort( ( a, b ) => 2 < Math.abs( a.top - b.top ) ? a.top - b.top : a.left - b.left )
		.map( ( item ) => item.index );
}

async function setTheme( page, theme ) {
	await page.emulateMedia( { colorScheme: 'system-dark' === theme ? 'dark' : 'light' } );
	await page.evaluate( ( value ) => {
		document.documentElement.dataset.theme = value;
	}, 'system-dark' === theme ? 'system' : theme );
}

async function runAxe( page, root = 'main' ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async ( selector ) => {
		const context = document.querySelector( selector ) || document;
		return ( await window.axe.run( context ) ).violations.map( ( violation ) => ( {
			id: violation.id,
			nodes: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
		} ) );
	}, root );
}

function expectedA11yIds() {
	if ( ! existsSync( A11Y_BASELINE_PATH ) ) {
		return new Set();
	}
	const found = new Set();
	const visit = ( value ) => {
		if ( Array.isArray( value ) ) {
			value.forEach( visit );
		} else if ( value && 'object' === typeof value ) {
			if ( 'string' === typeof value.id ) {
				found.add( value.id );
			}
			Object.values( value ).forEach( visit );
		}
	};
	visit( JSON.parse( readFileSync( A11Y_BASELINE_PATH, 'utf8' ) ) );
	return found;
}

function expectNoNewA11y( violations, label ) {
	const expected = expectedA11yIds();
	const unexpected = violations.filter( ( violation ) => ! expected.has( violation.id ) );
	expect( unexpected, `${ label } axe violations outside tests/a11y-expected-failures.json` ).toEqual( [] );
}

async function firstArchiveLink( page ) {
	await openArchive( page );
	const link = archiveCards( page ).first().locator( '.pkiw-otd__title a' );
	await expect( link ).toHaveCount( 1 );
	return link.getAttribute( 'href' );
}

test.describe( 'On This Day archive', () => {
	test.skip( 'archive' !== SET, 'archive fixture set' );

	test( 'A19/A20: header, site-local date and archive content match the route contract', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'On This Day' );
		await expect( page.locator( 'main h1 .cr-cutout__tiles[aria-hidden="true"] .cr-cutout__tile' ) ).toHaveCount( 9 );
		const rotations = await page.locator( 'main h1 .cr-cutout__tile' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => [ ...node.classList ].find( ( name ) => name.startsWith( 'is-r' ) ) ) );
		for ( let index = 1; index < rotations.length; index++ ) {
			expect( rotations[ index ] ).not.toBe( rotations[ index - 1 ] );
		}
		await expect( page.locator( '.cr-archive-identity__kicker' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-archive-identity__glyph[aria-hidden="true"]' ) ).toHaveCount( 1 );
		const date = page.locator( '.cr-otd__date time' );
		await expect( date ).toHaveAttribute( 'datetime', /^--\d{2}-\d{2}$/ );
		expect( ( await date.innerText() ).trim() ).toMatch( /^[A-Z][a-z]+ \d{1,2}$/ );
		await expect( page.locator( '.cr-otd__description' ) ).toHaveCount( 0 );
		const mainText = await page.locator( 'main' ).innerText();
		for ( const banned of [ 'Field notes', 'Blog · Date', 'Public posts from this date', 'No public post this year', 'SAMPLE CONTENT', 'Private, draft' ] ) {
			expect( mainText ).not.toContain( banned );
		}
	} );

	test( 'A21: year shelves measure 2/1 columns, preserve DOM order and stack images below 40rem', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await openArchive( page, 1, width );
			const lists = page.locator( '.pkiw-otd-year__items' );
			expect( await lists.count(), `${ width }px year shelves` ).toBeGreaterThan( 0 );
			for ( const list of await lists.all() ) {
				expect( await gridColumns( list ), `${ width }px archive columns` ).toBe( width >= 1024 ? 2 : 1 );
				const cards = list.locator( ':scope > li' );
				const positions = await visualOrder( cards );
				expect( sortedVisualIndexes( positions ), `${ width }px visual order` ).toEqual( positions.map( ( item ) => item.index ) );
			}
			const imageCard = archiveCards( page ).filter( { has: page.locator( '.pkiw-otd__image' ) } ).first();
			if ( 0 < await imageCard.count() ) {
				const layout = await imageCard.evaluate( ( card ) => {
					const image = card.querySelector( '.pkiw-otd__image' ).getBoundingClientRect();
					const title = card.querySelector( '.pkiw-otd__title' ).getBoundingClientRect();
					return { above: image.bottom <= title.top + 2, beside: image.right <= title.left + 2 };
				} );
				expect( width < 640 ? layout.above : layout.beside, `${ width }px image layout` ).toBe( true );
			}
			await assertNoOverflow( page, `${ width }px archive` );
		}
	} );

	test( 'A21: every archive card has one 44px whole-card link and a 3px ring with halo', async ( { page } ) => {
		await openArchive( page );
		expect( await archiveCards( page ).count() ).toBeGreaterThan( 0 );
		for ( const card of await archiveCards( page ).all() ) {
			await expect( card.locator( 'a[href]' ) ).toHaveCount( 1 );
			const link = card.locator( 'a[href]' );
			const box = await link.boundingBox();
			expect( box.height ).toBeGreaterThanOrEqual( 44 );
			expect( await link.evaluate( ( node ) => getComputedStyle( node, '::after' ).position ) ).toBe( 'absolute' );
		}
		const first = archiveCards( page ).first();
		await first.locator( 'a' ).focus();
		const focus = await first.evaluate( ( node ) => ( {
			color: getComputedStyle( node ).outlineColor,
			width: getComputedStyle( node ).outlineWidth,
			shadow: getComputedStyle( node ).boxShadow,
		} ) );
		expect( focus.width ).toBe( '3px' );
		expect( focus.color ).toBe( YELLOW );
		expect( focus.shadow ).toMatch( /8px/ );
	} );

	test( 'A21: 320px and 400% viewport equivalents do not scroll horizontally', async ( { page } ) => {
		await openArchive( page, 1, 320 );
		await assertNoOverflow( page, '320px archive' );
		await openArchive( page, 1, 80 );
		await assertNoOverflow( page, '400% zoom equivalent' );
	} );

	test( 'A21: pagination follows the last shelf, centers, marks current and omits edge links', async ( { page } ) => {
		await openArchive( page );
		const pager = page.locator( 'nav[aria-label="Pagination"]' );
		await expect( pager ).toHaveCount( 1 );
		await expect( pager.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
		await expect( pager.getByText( 'Previous', { exact: false } ) ).toHaveCount( 0 );
		const geometry = await page.evaluate( () => {
			const shelf = document.querySelector( '.pkiw-otd-years' );
			const nav = document.querySelector( 'nav[aria-label="Pagination"]' );
			const box = nav.getBoundingClientRect();
			return {
				after: !! ( shelf.compareDocumentPosition( nav ) & Node.DOCUMENT_POSITION_FOLLOWING ),
				centerDelta: Math.abs( box.left + box.width / 2 - document.documentElement.clientWidth / 2 ),
			};
		} );
		expect( geometry.after ).toBe( true );
		expect( geometry.centerDelta ).toBeLessThanOrEqual( 2 );

		let lastPage = 1;
		for ( let number = 1; number <= 20; number++ ) {
			await openArchive( page, number );
			lastPage = number;
			if ( 0 === await page.locator( 'nav[aria-label="Pagination"] a' ).filter( { hasText: 'Next' } ).count() ) {
				break;
			}
		}
		expect( lastPage ).toBeGreaterThan( 1 );
		await expect( page.locator( 'nav[aria-label="Pagination"] [aria-current="page"]' ) ).toHaveText( String( lastPage ) );
		await expect( page.locator( 'nav[aria-label="Pagination"] a' ).filter( { hasText: 'Next' } ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'On This Day empty archive', () => {
	test.skip( 'empty' !== SET, 'empty fixture set' );

	test( 'A21: query no-results renders alone', async ( { page } ) => {
		await openArchive( page );
		await expect( page.getByText( 'Nothing here yet.', { exact: true } ) ).toBeVisible();
		await expect( page.locator( '.pkiw-otd-year__heading' ) ).toHaveCount( 0 );
		await expect( page.locator( 'nav[aria-label="Pagination"]' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'On This Day Stream card', () => {
	test.skip( 'stream' !== SET, 'Stream fixture set' );

	test( 'A21: memory card stays first in DOM, reading, tab and collage order at every width', async ( { page } ) => {
		const expected = new Map( [ [ 1440, 3 ], [ 768, 2 ], [ 375, 1 ], [ 320, 1 ] ] );
		for ( const width of STREAM_WIDTHS ) {
			await open( page, STREAM, width );
			await expect( streamCard( page ) ).toHaveCount( 1 );
			await expect( page.locator( '.cr-stream-page .wp-block-post-template > li' ).first() ).toHaveClass( /\bpkiw-otd-stream-item\b/ );
			const items = page.locator( '.cr-stream-page .wp-block-post-template > li:visible' );
			const positions = await items.evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
				const box = node.getBoundingClientRect();
				return { index, top: Math.round( box.top ), left: Math.round( box.left ) };
			} ) );
			expect( sortedVisualIndexes( positions )[0], `${ width }px first visual card` ).toBe( 0 );
			const columns = new Set( positions.map( ( item ) => item.left ) ).size;
			expect( columns, `${ width }px collage columns` ).toBe( expected.get( width ) );
			const firstListLink = page.locator( '.cr-stream-page .wp-block-post-template > li a[href]' ).first();
			await expect( firstListLink ).toHaveClass( /\bu-url\b/ );
			await assertNoOverflow( page, `${ width }px Stream` );
		}
	} );

	test( 'A21: Stream memory has one whole-card link, one rotated card and one tape', async ( { page } ) => {
		await open( page, STREAM );
		const card = streamCard( page );
		await expect( card ).toHaveCount( 1 );
		await expect( card.locator( 'a[href]' ) ).toHaveCount( 1 );
		const linkBox = await card.locator( 'a' ).boundingBox();
		expect( linkBox.height ).toBeGreaterThanOrEqual( 44 );
		const layers = await card.evaluate( ( root ) => ( {
			card: getComputedStyle( root ).transform,
			tape: getComputedStyle( root, '::before' ).transform,
			descendants: [ ...root.querySelectorAll( '*' ) ].filter( ( node ) => ! node.matches( 'svg, svg *' ) && 'none' !== getComputedStyle( node ).transform ).length,
		} ) );
		expect( layers.card ).not.toBe( 'none' );
		expect( layers.tape ).not.toBe( 'none' );
		expect( layers.descendants ).toBe( 0 );
		await expect( page.locator( 'link[href*="cr-on-this-day.css"]' ) ).toHaveCount( 1 );
	} );

	test( 'A21: Stream focus, dark, forced colors and reduced motion preserve the card', async ( { page } ) => {
		await open( page, STREAM );
		await setTheme( page, 'dark' );
		const card = streamCard( page );
		const dark = await card.evaluate( ( node ) => ( {
			background: getComputedStyle( node ).backgroundColor,
			border: getComputedStyle( node ).borderTopColor,
			shadow: getComputedStyle( node ).boxShadow,
		} ) );
		expect( dark.background ).toBe( DARK_CARD );
		expect( dark.border ).toBe( IVORY );
		expect( dark.shadow ).toMatch( /4px 4px 0px/ );
		await card.locator( 'a' ).focus();
		expect( await card.evaluate( ( node ) => getComputedStyle( node ).outlineWidth ) ).toBe( '3px' );
		expect( await card.evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).toMatch( /8px/ );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( page, STREAM );
		expect( await streamCard( page ).evaluate( ( node ) => getComputedStyle( node ).transitionProperty ) ).toBe( 'none' );

		await setTheme( page, 'light' );
		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'no-preference' } );
		await open( page, STREAM );
		const forced = await streamCard( page ).evaluate( ( node ) => {
			const probe = document.createElement( 'span' );
			probe.style.color = 'CanvasText';
			document.body.append( probe );
			const canvasText = getComputedStyle( probe ).color;
			probe.remove();
			return {
				border: getComputedStyle( node ).borderTopColor,
				canvasText,
				tape: getComputedStyle( node, '::before' ).display,
			};
		} );
		expect( forced.border ).toBe( forced.canvasText );
		expect( forced.tape ).toBe( 'none' );
	} );
} );

test.describe( 'On This Day single card', () => {
	test.skip( 'single' !== SET, 'single fixture set' );

	test( 'A19/A21: the edge stub alone controls single-card presence', async ( { page } ) => {
		const href = await firstArchiveLink( page );
		expect( href ).toBeTruthy();
		await open( page, href );
		await expect( page.locator( 'aside.pkiw-otd--single' ) ).toHaveCount( EDGE_STUB ? 1 : 0 );
		await expect( page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' ) ).toHaveCount( 1 );
		if ( EDGE_STUB ) {
			const order = await page.evaluate( () => {
				const card = document.querySelector( 'aside.pkiw-otd--single' );
				const comments = document.querySelector( '.single-post__comments-wrapper' );
				const nav = document.querySelector( 'nav.cr-post-nav' );
				return {
					beforeComments: !! ( card.compareDocumentPosition( comments ) & Node.DOCUMENT_POSITION_FOLLOWING ),
					navAfterComments: !! ( comments.compareDocumentPosition( nav ) & Node.DOCUMENT_POSITION_FOLLOWING ),
				};
			} );
			expect( order ).toEqual( { beforeComments: true, navAfterComments: true } );
			await expect( page.locator( 'aside.pkiw-otd--single a[href]' ) ).toHaveCount( 1 );
			const card = page.locator( 'aside.pkiw-otd--single' );
			const target = await card.locator( 'a' ).boundingBox();
			expect( target.height ).toBeGreaterThanOrEqual( 44 );
			for ( const width of [ 800, 375, 320, 80 ] ) {
				await open( page, href, width );
				await assertNoOverflow( page, `${ width }px single` );
				const imageCard = page.locator( 'aside.pkiw-otd--single:has(.pkiw-otd__image)' );
				if ( 0 < await imageCard.count() && 80 !== width ) {
					const layout = await imageCard.evaluate( ( node ) => {
						const image = node.querySelector( '.pkiw-otd__image' ).getBoundingClientRect();
						const title = node.querySelector( '.pkiw-otd__title' ).getBoundingClientRect();
						return { above: image.bottom <= title.top + 2, right: image.left >= title.right - 2 };
					} );
					expect( width < 640 ? layout.above : layout.right, `${ width }px single image layout` ).toBe( true );
				}
			}

			await open( page, href );
			await setTheme( page, 'dark' );
			expect( await card.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( DARK_CARD );
			await card.locator( 'a' ).focus();
			expect( await card.evaluate( ( node ) => getComputedStyle( node ).outlineWidth ) ).toBe( '3px' );
		expect( await card.evaluate( ( node ) => getComputedStyle( node ).boxShadow ) ).toMatch( /8px/ );
			await page.emulateMedia( { reducedMotion: 'reduce' } );
			await open( page, href );
			expect( await card.evaluate( ( node ) => getComputedStyle( node ).transitionProperty ) ).toBe( 'none' );
			await setTheme( page, 'light' );
			await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'no-preference' } );
			await open( page, href );
			const photo = card.locator( '.pkiw-otd__image' );
			if ( 0 < await photo.count() ) {
				expect( await photo.evaluate( ( node ) => getComputedStyle( node, '::before' ).display ) ).toBe( 'none' );
			}
		}
	} );
} );

test.describe( 'On This Day appearance modes', () => {
	test.skip( 'a11y' !== SET, 'accessibility fixture set' );

	test( 'A21: dark, forced colors and reduced motion keep the frozen paint contract', async ( { page } ) => {
		await openArchive( page );
		await setTheme( page, 'dark' );
		const dark = await archiveCards( page ).first().evaluate( ( node ) => ( {
			page: getComputedStyle( document.querySelector( 'main.cr-page-style' ) ).backgroundColor,
			card: getComputedStyle( node ).backgroundColor,
			border: getComputedStyle( node ).borderTopColor,
			shadow: getComputedStyle( node ).boxShadow,
		} ) );
		expect( dark.page ).toBe( DARK_PAGE );
		expect( dark.card ).toBe( DARK_CARD );
		expect( dark.border ).toBe( IVORY );
		expect( dark.shadow ).toMatch( /4px 4px 0px/ );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await openArchive( page );
		expect( await archiveCards( page ).first().evaluate( ( node ) => getComputedStyle( node ).transitionProperty ) ).toBe( 'none' );

		await setTheme( page, 'light' );
		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'no-preference' } );
		await openArchive( page );
		const forced = await archiveCards( page ).first().evaluate( ( node ) => {
			const canvas = document.createElement( 'span' );
			canvas.style.color = 'CanvasText';
			const highlight = document.createElement( 'span' );
			highlight.style.color = 'Highlight';
			document.body.append( canvas, highlight );
			const values = {
				border: getComputedStyle( node ).borderTopWidth,
				borderColor: getComputedStyle( node ).borderTopColor,
				canvasText: getComputedStyle( canvas ).color,
				highlight: getComputedStyle( highlight ).color,
				shadow: getComputedStyle( node ).boxShadow,
				board: getComputedStyle( node, '::before' ).display,
			};
			canvas.remove();
			highlight.remove();
			return values;
		} );
		expect( forced.border ).toBe( '2px' );
		expect( forced.borderColor ).toBe( forced.canvasText );
		expect( forced.shadow ).toBe( 'none' );
		expect( forced.board ).toBe( 'none' );
		await expect( page.locator( '.pkiw-otd__image:not(:has(img))' ) ).toHaveCount( 0 );
		const card = archiveCards( page ).first();
		await card.locator( 'a' ).focus();
		const focus = await card.evaluate( ( node ) => ( {
			color: getComputedStyle( node ).outlineColor,
			width: getComputedStyle( node ).outlineWidth,
		} ) );
		expect( focus.width ).toBe( '3px' );
		expect( focus.color ).toBe( forced.highlight );
	} );

	test( 'A21: axe archive/Stream A-B run adds no failures beyond the repository baseline', async ( { page } ) => {
		await openArchive( page );
		expectNoNewA11y( await runAxe( page ), 'archive A' );
		await open( page, STREAM );
		await expect( streamCard( page ) ).toHaveCount( 1 );
		expectNoNewA11y( await runAxe( page ), 'Stream B' );
	} );
} );

test.describe( 'On This Day headers', () => {
	test.skip( 'headers' !== SET, 'header fixture set' );

	for ( const [ label, path ] of [ [ 'archive', ARCHIVE ], [ 'Stream', STREAM ] ] ) {
		test( `A21: ${ label } anonymous response is private and uncached`, async ( { page } ) => {
			const response = await open( page, path );
			const cacheControl = response?.headers()['cache-control'] || '';
			expect( cacheControl ).toContain( 'no-store' );
			expect( cacheControl ).toContain( 'private' );
			expect( cacheControl ).not.toContain( 'public' );
			if ( 'Stream' === label ) {
				await expect( streamCard( page ) ).toHaveCount( 1 );
				await expect( page.locator( 'link[href*="cr-on-this-day.css"]' ) ).toHaveCount( 1 );
			}
		} );
	}
} );
