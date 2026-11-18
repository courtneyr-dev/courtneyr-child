// `CR_SPEC=wrapped-267.spec.mjs CR_WRAPPED_SET=<single|strip|pattern|stream|a11y|quiet>`:
// X-T267 Wrapped theme acceptance. Read-only GETs.
import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const SET = process.env.CR_WRAPPED_SET || 'single';
const SINGLE = process.env.CR_WRAPPED_SINGLE_PATH || '';
const ARCHIVE = process.env.CR_WRAPPED_ARCHIVE_PATH || '';
const STREAM = process.env.CR_WRAPPED_STREAM_PATH || '/stream/';
const QUIET = process.env.CR_WRAPPED_QUIET_PATH || '';
const PATTERN = process.env.CR_WRAPPED_PATTERN_PATH || '';
const PERMALINK = process.env.CR_WRAPPED_PERMALINK || '';
const WIDTHS = [ 1440, 768, 375, 320 ];
const YELLOW = 'rgb(255, 183, 3)';
const VIOLET = 'rgb(36, 28, 74)';
const IVORY = 'rgb(251, 250, 245)';
const BLACK = 'rgb(26, 23, 20)';
const A11Y_BASELINE_PATH = fileURLToPath( new URL( '../a11y-expected-failures.json', import.meta.url ) );

const metrics = ( page ) => page.locator( '.pkiw-wrapped__metrics' );
const topItems = ( page ) => page.locator( '.pkiw-wrapped__top' );
const strip = ( page ) => page.locator( '.pkiw-wrapped-strip' );
const streamCard = ( page ) => page.locator( '.cr-wrapped-card' );

async function open( page, path, width = 1440 ) {
	expect( path, 'fixture path is configured' ).not.toBe( '' );
	await page.setViewportSize( { width, height: width <= 400 ? 900 : 1100 } );
	const response = await page.goto( path, { waitUntil: 'load' } );
	expect( response?.status(), `${ path } status` ).toBe( 200 );
	await page.evaluate( () => document.fonts.ready );
	return response;
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function gridColumns( locator ) {
	return locator.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length );
}

async function tabTo( page, locator, label ) {
	for ( let count = 0; count < 100; count++ ) {
		await page.keyboard.press( 'Tab' );
		if ( await locator.evaluate( ( node ) => node === document.activeElement ) ) {
			return;
		}
	}
	expect( false, `${ label } is reachable with Tab` ).toBe( true );
}

async function setTheme( page, theme ) {
	await page.emulateMedia( { colorScheme: 'dark' === theme || 'system' === theme ? 'dark' : 'light' } );
	await page.evaluate( ( value ) => {
		if ( 'system' === value ) {
			delete document.documentElement.dataset.theme;
		} else {
			document.documentElement.dataset.theme = value;
		}
	}, theme );
}

async function assertWholeHitArea( link, label ) {
	const box = await link.boundingBox();
	expect( box.height, `${ label } height` ).toBeGreaterThanOrEqual( 44 );
	expect( await link.evaluate( ( node ) => getComputedStyle( node, '::after' ).position ), `${ label } overlay` ).toBe( 'absolute' );
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

test.describe( 'Wrapped single', () => {
	test.skip( 'single' !== SET, 'single fixture set' );
	test.skip( ! SINGLE, 'CR_WRAPPED_SINGLE_PATH is required' );

	test( 'A20/A23: title, outline and grids follow the Wrapped contract at every width', async ( { page } ) => {
		const expectedMetrics = new Map( [ [ 1440, 4 ], [ 768, 2 ], [ 375, 1 ], [ 320, 1 ] ] );
		for ( const width of WIDTHS ) {
			await open( page, SINGLE, width );
			await expect( metrics( page ) ).toHaveCount( 1 );
			expect( await gridColumns( metrics( page ) ), `${ width }px metric columns` ).toBe( expectedMetrics.get( width ) );
			await expect( topItems( page ), 'Wrapped single fixture must include one top-items list' ).toHaveCount( 1 );
			expect( await gridColumns( topItems( page ) ), `${ width }px top-item columns` ).toBe( width >= 1440 ? 3 : width <= 375 ? 1 : 3 );
			await assertNoOverflow( page, `${ width }px Wrapped single` );
		}

		await open( page, SINGLE );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		const title = page.locator( 'main h1' );
		await expect( title.locator( '.cr-cutout__tiles[aria-hidden="true"]' ) ).toHaveCount( 1 );
		const screenReaderTitle = title.locator( '.cr-cutout__text.screen-reader-text' );
		await expect( screenReaderTitle ).not.toHaveText( '' );
		await expect( title ).toHaveAccessibleName( await screenReaderTitle.innerText() );
		await expect( page.locator( '.pkiw-wrapped > h2' ), 'Wrapped fixture with top items must render exactly one H2 inside the Wrapped block' ).toHaveCount( 1 );
		const topLinks = topItems( page ).locator( 'a[href]' );
		expect( await topLinks.count(), 'Wrapped single fixture must include linked top items' ).toBeGreaterThan( 0 );
		for ( const [ index, link ] of ( await topLinks.all() ).entries() ) {
			await assertWholeHitArea( link, `top-item link ${ index + 1 }` );
		}
	} );

	test( 'A23: 400% zoom reflows without horizontal overflow', async ( { page } ) => {
		await open( page, SINGLE, 360 );
		await expect( metrics( page ) ).toHaveCount( 1 );
		await assertNoOverflow( page, '400% zoom Wrapped single' );
	} );
} );

test.describe( 'Wrapped archive strip', () => {
	test.skip( 'strip' !== SET, 'archive-strip fixture set' );
	test.skip( ! ARCHIVE, 'CR_WRAPPED_ARCHIVE_PATH is required' );

	test( 'A22/A23: strip spans its content area, wraps and remains one link', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await open( page, ARCHIVE, width );
			await expect( strip( page ) ).toHaveCount( 1 );
			await expect( strip( page ).locator( 'a[href]' ) ).toHaveCount( 1 );
			const geometry = await strip( page ).evaluate( ( node ) => {
				const box = node.getBoundingClientRect();
				const parent = node.parentElement.getBoundingClientRect();
				return {
					widthDelta: Math.abs( box.width - parent.width ),
					countWraps: node.querySelector( '.pkiw-wrapped-strip__count' ).scrollWidth <= node.querySelector( '.pkiw-wrapped-strip__count' ).clientWidth,
				};
			} );
			expect( geometry.widthDelta, `${ width }px strip width` ).toBeLessThanOrEqual( 2 );
			expect( geometry.countWraps, `${ width }px count line wraps` ).toBe( true );
			await assertNoOverflow( page, `${ width }px Wrapped archive` );
		}
		await open( page, ARCHIVE );
		await assertWholeHitArea( strip( page ).locator( 'a[href]' ), 'Wrapped strip link' );
	} );

	test( 'A23: Tab focus draws the 3px ring and 400% zoom reflows', async ( { page } ) => {
		await open( page, ARCHIVE );
		await expect( strip( page ) ).toHaveCount( 1 );
		await tabTo( page, strip( page ).locator( 'a' ), 'Wrapped strip link' );
		const focus = await strip( page ).evaluate( ( node ) => ( {
			color: getComputedStyle( node ).outlineColor,
			width: getComputedStyle( node ).outlineWidth,
			shadow: getComputedStyle( node ).boxShadow,
		} ) );
		expect( focus.width ).toBe( '3px' );
		expect( focus.color ).toBe( YELLOW );
		expect( focus.shadow ).toMatch( /8px/ );
		expect( await strip( page ).locator( 'a' ).evaluate( ( node ) => getComputedStyle( node ).boxShadow ), 'focused strip link draws no rectangular halo of its own' ).toBe( 'none' );
		await open( page, ARCHIVE, 360 );
		await assertNoOverflow( page, '400% zoom Wrapped archive' );
	} );
} );

test.describe( 'Wrapped editable pattern', () => {
	test.skip( 'pattern' !== SET, 'pattern fixture set' );
	test.skip( ! PATTERN || ! ARCHIVE, 'CR_WRAPPED_PATTERN_PATH and CR_WRAPPED_ARCHIVE_PATH are required' );

	test( 'A22: bound pattern matches the default strip permalink and count line', async ( { page } ) => {
		await open( page, ARCHIVE );
		await expect( strip( page ) ).toHaveCount( 1 );
		const defaultLink = strip( page ).locator( 'a[href]' );
		await expect( defaultLink ).toHaveCount( 1 );
		const permalink = PERMALINK ? new URL( PERMALINK, page.url() ).href : await defaultLink.evaluate( ( node ) => node.href );
		const countLine = ( await strip( page ).locator( '.pkiw-wrapped-strip__count' ).innerText() ).trim();

		await open( page, PATTERN );
		await expect( strip( page ) ).toHaveCount( 1 );
		const patternLink = strip( page ).locator( 'a[href]' );
		await expect( patternLink ).toHaveCount( 1 );
		expect( await patternLink.evaluate( ( node ) => node.href ) ).toBe( permalink );
		await expect( strip( page ).locator( '.pkiw-wrapped-strip__count' ) ).toHaveText( countLine );
	} );
} );

test.describe( 'Wrapped Stream card', () => {
	test.skip( 'stream' !== SET, 'Stream fixture set' );

	test( 'A21/A23: card has one link, matches its column and keeps visible focus', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await open( page, STREAM, width );
			await expect( streamCard( page ) ).toHaveCount( 1 );
			await expect( streamCard( page ).locator( 'a[href]' ) ).toHaveCount( 1 );
			// Layout width (offsetWidth ignores the collage rotation) equals every sibling Stream card's.
			const widths = await page.locator( '.pk-card--stream:not(.pk-card--protected)' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { wrapped: node.classList.contains( 'cr-wrapped-card' ), width: node.offsetWidth } ) ) );
			const own = widths.find( ( row ) => row.wrapped );
			const sibling = widths.find( ( row ) => ! row.wrapped );
			expect( own && sibling, `${ width }px needs a sibling Stream card to compare` ).toBeTruthy();
			expect( Math.abs( own.width - sibling.width ), `${ width }px collage width` ).toBeLessThanOrEqual( 2 );
			await assertNoOverflow( page, `${ width }px Wrapped Stream` );
		}
		await open( page, STREAM );
		await tabTo( page, streamCard( page ).locator( 'a' ), 'Wrapped Stream card link' );
		const focus = await streamCard( page ).evaluate( ( node ) => ( {
			color: getComputedStyle( node ).outlineColor,
			width: getComputedStyle( node ).outlineWidth,
			shadow: getComputedStyle( node ).boxShadow,
		} ) );
		expect( focus.width ).toBe( '3px' );
		expect( focus.color ).toBe( YELLOW );
		expect( focus.shadow ).toMatch( /8px/ );
		expect( await streamCard( page ).locator( 'a' ).evaluate( ( node ) => getComputedStyle( node ).boxShadow ), 'focused card link draws no rectangular halo of its own' ).toBe( 'none' );
		await assertWholeHitArea( streamCard( page ).locator( 'a[href]' ), 'Wrapped Stream card link' );
		const layers = await streamCard( page ).evaluate( ( root ) => ( {
			card: getComputedStyle( root ).transform,
			paper: getComputedStyle( root, '::before' ).transform,
			tape: getComputedStyle( root, '::after' ).transform,
			descendants: [ ...root.querySelectorAll( '*' ) ].filter( ( node ) => ! node.matches( 'svg, svg *' ) && 'none' !== getComputedStyle( node ).transform ).length,
		} ) );
		// The card's own collage rotation comes from its column position (cr-post-kinds.css 4n+1 and 4n+3), so it may be none here.
		expect( layers.tape ).not.toBe( 'none' );
		expect( layers.descendants ).toBe( 0 );
		expect( layers.paper ).toBe( 'none' );
	} );
} );

test.describe( 'Wrapped appearance and accessibility', () => {
	test.skip( 'a11y' !== SET, 'appearance fixture set' );
	test.skip( ! SINGLE || ! ARCHIVE, 'single and archive fixture paths are required' );

	test( 'A23: dark, forced colors and reduced motion preserve required paint', async ( { page } ) => {
		await open( page, SINGLE );
		await expect( metrics( page ) ).toHaveCount( 1 );
		await setTheme( page, 'dark' );
		const dark = await metrics( page ).locator( ':scope > li' ).first().evaluate( ( node ) => ( {
			page: getComputedStyle( document.querySelector( '.wp-site-blocks' ) ).backgroundColor,
			outside: getComputedStyle( document.querySelector( '.pkiw-wrapped' ) ).color,
			paper: getComputedStyle( node, '::before' ).backgroundColor,
			ink: getComputedStyle( node ).color,
			shadow: getComputedStyle( node ).filter,
		} ) );
		expect( dark.page ).toBe( BLACK );
		expect( dark.outside ).toBe( IVORY );
		expect( dark.paper ).toBe( IVORY );
		expect( dark.ink ).toBe( VIOLET );
		expect( dark.shadow ).toContain( YELLOW );

		await open( page, SINGLE );
		await setTheme( page, 'system' );
		expect( await page.locator( 'html' ).getAttribute( 'data-theme' ) ).toBeNull();
		const systemDark = await metrics( page ).locator( ':scope > li' ).first().evaluate( ( node ) => ( {
			page: getComputedStyle( document.querySelector( '.wp-site-blocks' ) ).backgroundColor,
			outside: getComputedStyle( document.querySelector( '.pkiw-wrapped' ) ).color,
			paper: getComputedStyle( node, '::before' ).backgroundColor,
			ink: getComputedStyle( node ).color,
			shadow: getComputedStyle( node ).filter,
		} ) );
		expect( systemDark ).toEqual( dark );

		await open( page, STREAM );
		await expect( streamCard( page ) ).toHaveCount( 1 );
		await setTheme( page, 'dark' );
		const darkCard = await streamCard( page ).evaluate( ( node ) => ( {
			paper: getComputedStyle( node, '::before' ).backgroundColor,
			ink: getComputedStyle( node ).color,
			shadow: getComputedStyle( node ).filter,
		} ) );
		expect( darkCard.paper ).toBe( IVORY );
		expect( darkCard.ink ).toBe( VIOLET );
		expect( darkCard.shadow ).toContain( YELLOW );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( page, ARCHIVE );
		await expect( strip( page ) ).toHaveCount( 1 );
		expect( await strip( page ).evaluate( ( node ) => getComputedStyle( node ).transitionProperty ) ).toBe( 'none' );
		await open( page, STREAM );
		await expect( streamCard( page ) ).toHaveCount( 1 );
		expect( await streamCard( page ).evaluate( ( node ) => getComputedStyle( node ).transitionProperty ) ).toBe( 'none' );

		await setTheme( page, 'light' );
		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'no-preference' } );
		await open( page, SINGLE );
		const forcedMetric = await metrics( page ).locator( ':scope > li' ).first().evaluate( ( node ) => ( {
			border: getComputedStyle( node ).borderTopWidth,
			paper: getComputedStyle( node, '::before' ).display,
			shadow: getComputedStyle( node ).filter,
		} ) );
		expect( forcedMetric ).toEqual( { border: '2px', paper: 'none', shadow: 'none' } );
		const forcedTop = await topItems( page ).locator( ':scope > li' ).first().evaluate( ( node ) => ( {
			border: getComputedStyle( node ).borderTopWidth,
			shadow: getComputedStyle( node ).boxShadow,
		} ) );
		expect( forcedTop ).toEqual( { border: '2px', shadow: 'none' } );

		await open( page, ARCHIVE );
		await expect( strip( page ) ).toHaveCount( 1 );
		const forced = await strip( page ).evaluate( ( node ) => ( {
			border: getComputedStyle( node ).borderTopWidth,
			tape: getComputedStyle( node, '::before' ).display,
			shadow: getComputedStyle( node ).boxShadow,
		} ) );
		expect( forced.border ).toBe( '2px' );
		expect( forced.tape ).toBe( 'none' );
		expect( forced.shadow ).toBe( 'none' );
		await open( page, STREAM );
		await expect( streamCard( page ) ).toHaveCount( 1 );
		const forcedCard = await streamCard( page ).evaluate( ( node ) => ( {
			border: getComputedStyle( node ).borderTopWidth,
			paper: getComputedStyle( node, '::before' ).display,
			tape: getComputedStyle( node, '::after' ).display,
		} ) );
		// Ruling R-T267-1: components.css (forced-colors) sets `.pk-card { border: 1px solid CanvasText !important }`; the card keeps that CanvasText border, since this lane may not add !important.
		expect( forcedCard.border ).toBe( '1px' );
		expect( forcedCard.paper ).toBe( 'none' );
		expect( forcedCard.tape ).toBe( 'none' );
	} );

	test( 'A23: axe single/archive/Stream run adds no failures beyond the baseline', async ( { page } ) => {
		await open( page, SINGLE );
		await expect( metrics( page ) ).toHaveCount( 1 );
		expectNoNewA11y( await runAxe( page ), 'Wrapped single A' );
		await open( page, ARCHIVE );
		await expect( strip( page ) ).toHaveCount( 1 );
		expectNoNewA11y( await runAxe( page ), 'Wrapped archive B' );
		await open( page, STREAM );
		await expect( streamCard( page ) ).toHaveCount( 1 );
		expectNoNewA11y( await runAxe( page ), 'Wrapped Stream C' );
	} );
} );

test.describe( 'quiet_public Wrapped sentinel', () => {
	test.skip( 'quiet' !== SET, 'quiet-public fixture set' );
	test.skip( ! QUIET, 'CR_WRAPPED_QUIET_PATH is required' );

	test( 'A20: quiet-public single has no Wrapped title tiles or sheet', async ( { page } ) => {
		await open( page, QUIET );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1 .cr-cutout__tile' ) ).toHaveCount( 0 );
		await expect( page.locator( '#courtneyr-wrapped-css, #courtneyr-wrapped-inline-css, #courtneyr-cutout-css' ) ).toHaveCount( 0 );
		await expect( page.locator( 'link[href*="cr-wrapped.css"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.pkiw-wrapped, .pkiw-wrapped__metrics' ), 'a quiet_public Wrapped single prints no Wrapped block' ).toHaveCount( 0 );
		await open( page, STREAM );
		await expect( page.locator( `.cr-wrapped-card a[href$="${ new URL( QUIET, 'http://x.test' ).pathname }"]` ), 'the Stream holds no Wrapped card for the quiet post' ).toHaveCount( 0 );
	} );
} );
