// `CR_SPEC=booth-print-style.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`:
// the author-chosen core/gallery photo booth print at desktop, mobile and dark, plus its Stream
// counterpart when CR_BOOTH_STREAM=1. Read-only GETs against CR_BOOTH_PATH (default
// /booth-print-fixture/) and /stream/; needs a running local fixture site.
import { test, expect } from '@playwright/test';

const BOOTH = process.env.CR_BOOTH_PATH || '/booth-print-fixture/';
const WHITE = 'rgb(255, 255, 255)';

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
	expect( response.status() ).toBe( 200 );
}

for ( const width of [ 1440, 375 ] ) {
	test( `the Gallery block is a stacked booth print at ${ width }px`, async ( { page } ) => {
		await page.setViewportSize( { width, height: 900 } );
		await visit( page, BOOTH );
		const gallery = page.locator( '.wp-block-gallery.is-style-cr-booth-print' ).first();
		await expect( gallery ).toBeVisible();

		const layout = await gallery.evaluate( ( el ) => {
			const figures = [ ...el.querySelectorAll( ':scope > figure.wp-block-image' ) ];
			const boxes = figures.map( ( figure ) => figure.getBoundingClientRect() );
			const ratios = figures.map( ( figure ) => {
				const box = figure.querySelector( 'img' ).getBoundingClientRect();
				return box.width / box.height;
			} );
			const style = getComputedStyle( el );
			const caption = el.querySelector( ':scope > figcaption.blocks-gallery-caption' );
			return {
				background: style.backgroundColor,
				shadow: style.boxShadow,
				maxWidth: parseFloat( style.maxWidth ),
				width: el.getBoundingClientRect().width,
				lefts: boxes.map( ( box ) => Math.round( box.left ) ),
				tops: boxes.map( ( box ) => Math.round( box.top ) ),
				ratios,
				caption: caption ? getComputedStyle( caption ).textAlign : null,
			};
		} );

		expect( layout.background ).toBe( WHITE );
		expect( layout.shadow ).not.toBe( 'none' );
		expect( layout.maxWidth ).toBeLessThanOrEqual( 240 );
		expect( layout.width ).toBeLessThanOrEqual( 240 );
		expect( new Set( layout.lefts ).size ).toBe( 1 );
		for ( let i = 1; i < layout.tops.length; i++ ) {
			expect( layout.tops[ i ] ).toBeGreaterThan( layout.tops[ i - 1 ] );
		}
		for ( const ratio of layout.ratios ) {
			expect( Math.abs( ratio - 4 / 3 ) ).toBeLessThanOrEqual( 0.02 );
		}
		expect( layout.caption ).toBe( 'center' );
	} );
}

test( 'the Gallery block stays white in dark mode', async ( { page } ) => {
	await visit( page, BOOTH, 'dark' );
	const gallery = page.locator( '.wp-block-gallery.is-style-cr-booth-print' ).first();
	await expect( gallery ).toBeVisible();
	expect( await gallery.evaluate( ( el ) => getComputedStyle( el ).backgroundColor ) ).toBe( WHITE );
} );

test( 'the Stream photo gallery card uses the same white stock', async ( { page } ) => {
	test.skip( '1' !== process.env.CR_BOOTH_STREAM, 'set CR_BOOTH_STREAM=1 when the Stream fixture is present' );
	await visit( page, '/stream/' );
	const card = page.locator( '.pk-card.k-photo.pk-card--gallery' ).first();
	await expect( card ).toBeVisible();
	expect( await card.evaluate( ( el ) => getComputedStyle( el ).backgroundColor ) ).toBe( WHITE );
} );
