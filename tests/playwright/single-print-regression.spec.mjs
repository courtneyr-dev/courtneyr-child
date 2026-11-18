// `CR_SPEC=single-print-regression.spec.mjs CR_SINGLE_PRINT_MODE=<record|compare>`:
// X-T269 standalone-single print and protected-object regression checks. Read-only GETs.
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, dirname, extname, join } from 'node:path';
import { test, expect } from '@playwright/test';
import { BASE_URL, assertLocalTarget } from '../routes.mjs';

assertLocalTarget( BASE_URL );

const MODE = process.env.CR_SINGLE_PRINT_MODE || 'compare';
const BASELINE_INPUT = process.env.CR_SINGLE_PRINT_BASELINE || '';
const PATHS = new Map( ( process.env.CR_SINGLE_PATHS || '' ).split( ',' ).filter( Boolean ).map( ( item ) => {
	const separator = item.indexOf( '=' );
	return [ item.slice( 0, separator ).trim(), item.slice( separator + 1 ).trim() ];
} ) );
const BASELINE_JSON = '.json' === extname( BASELINE_INPUT ) ? BASELINE_INPUT : join( BASELINE_INPUT, 'single-print-baseline.json' );
const SCREENSHOT_DIR = '.json' === extname( BASELINE_INPUT ) ? join( dirname( BASELINE_INPUT ), `${ basename( BASELINE_INPUT, '.json' ) }-screenshots` ) : BASELINE_INPUT;
const WIDTHS = [ 320, 375, 768, 1440 ];
const SCHEMES = [ 'light', 'dark', 'forced' ];
const ORANGE = 'rgb(251, 133, 0)';
const IVORY = 'rgb(251, 250, 245)';
const VIOLET = 'rgb(36, 28, 74)';
const LOCAL_ORIGIN = new URL( BASE_URL ).origin;
const GUARDED_PAGES = new WeakSet();
const PRINT_TOKENS = [ '--cr-ink-soft', '--cr-border', '--cr-tape', '--cr-code-bg', '--cr-code-ink' ];
const PROTECTED = {
	placemat: '.cr-placemat',
	ticket: '.cr-order--ticket',
	coaster: '.cr-order--coaster .cr-order__sheet',
	mapSlip: '.cr-map-slip',
	recipeBinder: '.cr-recipe-binder',
	wprmBinder: '.cr-binder',
};

async function open( page, path, width = 1440, scheme = 'light' ) {
	if ( ! GUARDED_PAGES.has( page ) ) {
		await page.route( '**/*', async ( route ) => {
			const request = route.request();
			const sameOriginGet = 'GET' === request.method() && new URL( request.url() ).origin === LOCAL_ORIGIN;
			await ( sameOriginGet ? route.continue() : route.abort() );
		} );
		GUARDED_PAGES.add( page );
	}
	await page.setViewportSize( { width, height: width < 400 ? 900 : 1000 } );
	await page.emulateMedia( {
		media: 'screen',
		colorScheme: 'dark' === scheme ? 'dark' : 'light',
		forcedColors: 'forced' === scheme ? 'active' : 'none',
	} );
	await page.addInitScript( ( value ) => {
		try {
			localStorage.setItem( 'courtneyr-theme', value );
		} catch {}
	}, 'dark' === scheme ? 'dark' : 'light' );
	const response = await page.goto( path, { waitUntil: 'load' } );
	expect( response?.status(), `${ path } status` ).toBe( 200 );
	await page.evaluate( () => document.fonts.ready );
}

async function visibleBodyText( page, scope = 'body' ) {
	return page.evaluate( ( selector ) => {
		const visible = ( node ) => {
			const style = getComputedStyle( node );
			return 'none' !== style.display && 'hidden' !== style.visibility && node.getClientRects().length > 0;
		};
		const lines = [];
		for ( const root of document.querySelectorAll( selector ) ) {
			const walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT );
			while ( walker.nextNode() ) {
				const text = walker.currentNode.textContent.replace( /\s+/g, ' ' ).trim();
				if ( text && walker.currentNode.parentElement && visible( walker.currentNode.parentElement ) ) {
					lines.push( text );
				}
			}
		}
		return lines.join( '\n' );
	}, scope );
}

async function protectedStyles( page ) {
	const rows = {};
	for ( const [ name, selector ] of Object.entries( PROTECTED ) ) {
		const node = page.locator( selector ).first();
		if ( 0 === await node.count() ) {
			rows[ name ] = 'absent';
			continue;
		}
		rows[ name ] = await node.evaluate( ( element ) => {
			const paint = ( pseudo = null ) => {
				const style = getComputedStyle( element, pseudo );
				return {
					background: style.background,
					content: style.content,
					boxShadow: style.boxShadow,
					transform: style.transform,
				};
			};
			const style = getComputedStyle( element );
			return {
				display: style.display,
				position: style.position,
				transform: style.transform,
				boxShadow: style.boxShadow,
				filter: style.filter,
				backgroundColor: style.backgroundColor,
				borderTop: style.borderTop,
				overflow: style.overflow,
				before: paint( '::before' ),
				after: paint( '::after' ),
			};
		} );
	}
	return rows;
}

async function printedHeaderOrder( page ) {
	const header = page.locator( 'body.cr-recipe-single .single-post__header' ).first();
	if ( 0 === await header.count() ) {
		return 'absent';
	}
	return header.evaluate( ( element ) => ( {
		display: getComputedStyle( element ).display,
		children: [ ...element.children ].filter( ( child ) => ! child.classList.contains( 'cr-print-only' ) ).map( ( child, index ) => ( {
			domIndex: index,
			key: `${ child.tagName.toLowerCase() }.${ [ ...child.classList ].join( '.' ) }`,
			order: getComputedStyle( child ).order,
			top: Math.round( child.getBoundingClientRect().top ),
		} ) ),
	} ) );
}

async function printPalette( page, theme ) {
	await page.emulateMedia( { media: 'print', colorScheme: theme, forcedColors: 'none' } );
	return page.evaluate( ( { properties, value } ) => {
		document.documentElement.dataset.theme = value;
		let code = document.querySelector( '[data-cr-print-token-probe]' );
		if ( ! code ) {
			code = document.createElement( 'code' );
			code.dataset.crPrintTokenProbe = '';
			code.textContent = 'print token probe';
			document.querySelector( '.single-post__content' )?.append( code );
		}
		const root = getComputedStyle( document.documentElement );
		const codeStyle = getComputedStyle( code );
		return {
			tokens: Object.fromEntries( properties.map( ( property ) => [ property, root.getPropertyValue( property ).trim() ] ) ),
			code: { background: codeStyle.backgroundColor, color: codeStyle.color },
		};
	}, { properties: PRINT_TOKENS, value: theme } );
}

function withoutPermalink( bodyText, permalink ) {
	if ( ! permalink ) {
		return bodyText;
	}
	return bodyText.split( '\n' ).filter( ( line ) => line.trim() !== permalink.trim() ).join( '\n' );
}

function screenshotPath( name, width, scheme ) {
	return join( SCREENSHOT_DIR, `${ name }-${ width }-${ scheme }.png` );
}

test.describe( 'Protected single-print objects', () => {
	test.skip( ! BASELINE_INPUT || ! [ 'record', 'compare' ].includes( MODE ), 'baseline path and record|compare mode are required' );

	test( 'C13: record or compare protected styles and allow only the new permalink text', async ( { page } ) => {
		const captured = {};
		for ( const [ name, path ] of PATHS ) {
			if ( 'long' === name ) {
				continue;
			}
			await open( page, path );
			await page.emulateMedia( { media: 'print', colorScheme: 'light', forcedColors: 'none' } );
			const styles = await protectedStyles( page );
			const absent = Object.entries( styles ).filter( ( [ , value ] ) => 'absent' === value ).map( ( [ key ] ) => key );
			console.log( `${ name } absent protected objects: ${ absent.length ? absent.join( ', ' ) : 'none' }` );
			captured[ name ] = {
				styles,
				headerOrder: await printedHeaderOrder( page ),
				bodyText: await visibleBodyText( page ),
				contentLines: await visibleBodyText( page, '.single-post__content, .cr-placemat, .cr-order--ticket, .cr-order--coaster, .cr-map-slip, .cr-recipe-binder, .cr-binder' ),
				allowedByline: await page.locator( '.cr-print-byline .wp-block-post-author-name, .cr-print-byline .wp-block-post-date' ).evaluateAll( ( nodes ) => nodes.map( ( child ) => child.textContent.replace( /\s+/g, ' ' ).trim() ) ),
				permalink: ( await page.locator( '.cr-print-permalink' ).allTextContents() ).join( ' ' ).replace( /\s+/g, ' ' ).trim(),
			};
		}

		if ( 'record' === MODE ) {
			mkdirSync( dirname( BASELINE_JSON ), { recursive: true } );
			writeFileSync( BASELINE_JSON, `${ JSON.stringify( captured, null, 2 ) }\n` );
			return;
		}

		const baseline = JSON.parse( readFileSync( BASELINE_JSON, 'utf8' ) );
		for ( const [ name, row ] of Object.entries( captured ) ) {
			expect( row.styles, `${ name } protected computed styles` ).toEqual( baseline[ name ].styles );
			expect( row.headerOrder, `${ name } recipe header child DOM and printed order` ).toEqual( baseline[ name ].headerOrder );
			expect( row.permalink, `${ name } has the added permalink line` ).not.toBe( '' );
			const now = row.bodyText.split( '\n' );
			for ( const line of baseline[ name ].contentLines.split( '\n' ).filter( Boolean ) ) {
				expect( now, `${ name } post content and protected-object text still print: ${ line }` ).toContain( line );
			}
			const before = new Set( baseline[ name ].bodyText.split( '\n' ) );
			const added = withoutPermalink( now.filter( ( line ) => ! before.has( line ) ).join( '\n' ), row.permalink ).split( '\n' ).filter( Boolean );
			expect( added.filter( ( line ) => ! row.allowedByline.includes( line ) ), `${ name } added print lines beyond byline and permalink` ).toEqual( [] );
		}
	} );
} );

test.describe( 'Single screen regression', () => {
	test.skip( ! BASELINE_INPUT || ! [ 'record', 'compare' ].includes( MODE ), 'baseline path and record|compare mode are required' );

	for ( const [ name, path ] of PATHS ) {
		for ( const width of WIDTHS ) {
			for ( const scheme of SCHEMES ) {
				test( `T5: ${ name} screen is byte-stable at ${ width }px ${ scheme }`, async ( { page } ) => {
					await open( page, path, width, scheme );
					if ( 'compare' === MODE ) {
						const groups = page.locator( '.cr-print-only' );
						const orderSingle = [ 'eat', 'drink' ].includes( name );
						await expect( groups, orderSingle ? 'an order single keeps its approved header, so only the permalink group renders' : 'both print-only groups render' ).toHaveCount( orderSingle ? 1 : 2 );
						expect( await groups.evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).display ) ) ).toEqual( orderSingle ? [ 'none' ] : [ 'none', 'none' ] );
					}
					const first = await page.screenshot( { fullPage: true } );
					const control = await page.screenshot( { fullPage: true } );
					expect( first.equals( control ), 'same-CSS screenshot control is stable' ).toBe( true );
					const target = screenshotPath( name, width, scheme );
					if ( 'record' === MODE ) {
						mkdirSync( SCREENSHOT_DIR, { recursive: true } );
						writeFileSync( target, first );
					} else {
						expect( first.equals( readFileSync( target ) ), `${ name } ${ width }px ${ scheme } baseline screenshot` ).toBe( true );
					}
				} );
			}
		}
	}
} );

test.describe( 'Standalone long-post print', () => {
	test.skip( ! PATHS.has( 'long' ) || 'record' === MODE, 'CR_SINGLE_PATHS must include long=/path/, and record mode runs against the pre-change theme' );

	test( 'C13: print order, hides, plain H1, byline, permalink and quote match the contract', async ( { page } ) => {
		await open( page, PATHS.get( 'long' ) );
		await page.emulateMedia( { media: 'print', colorScheme: 'light', forcedColors: 'none' } );
		const hiddenSelectors = [ '.single-post__comments-wrapper', '.wp-block-comments', '.wp-block-post-comments-form', '.single-post__syndication', '.wp-block-indieblocks-facepile', '.cr-newsletter-banner', '.cr-reading-progress' ];
		let hiddenMatches = 0;
		for ( const selector of hiddenSelectors ) {
			const nodes = page.locator( selector );
			const count = await nodes.count();
			if ( 0 === count ) {
				console.log( `single-print fixture has no ${ selector }` );
				continue;
			}
			hiddenMatches += count;
			const paint = await nodes.evaluateAll( ( elements ) => elements.map( ( element ) => {
				const style = getComputedStyle( element );
				const rect = element.getBoundingClientRect();
				return { display: style.display, visibility: style.visibility, width: rect.width, height: rect.height };
			} ) );
			expect( paint.every( ( row ) => 'none' === row.display || 'hidden' === row.visibility || 0 === row.width || 0 === row.height ), `${ selector } is absent from print` ).toBe( true );
		}
		expect( hiddenMatches, 'the fixture exercises at least one print hide-list selector' ).toBeGreaterThan( 0 );

		for ( const selector of [ '.single-post__lede-meta .single-post__category', '.single-post__lede-meta .single-post__date' ] ) {
			const chip = page.locator( selector ).first();
			await expect( chip, `${ selector } exists in the print fixture` ).toHaveCount( 1 );
			const paint = await chip.evaluate( ( element ) => {
				const style = getComputedStyle( element );
				const rect = element.getBoundingClientRect();
				return { visibility: style.visibility, width: rect.width, height: rect.height };
			} );
			expect( 'hidden' === paint.visibility || 0 === paint.width || 0 === paint.height, `${ selector } does not print` ).toBe( true );
		}
		const kind = page.locator( '.single-post__lede-meta .single-post__kind' ).first();
		await expect( kind ).toBeVisible();
		expect( await kind.evaluate( ( element ) => getComputedStyle( element ).visibility ) ).toBe( 'visible' );

		const title = page.locator( 'h1.single-post__title' );
		const titlePaint = await title.evaluate( ( node ) => ( { font: getComputedStyle( node ).fontFamily, transform: getComputedStyle( node ).transform } ) );
		expect( titlePaint.font.split( ',' )[0].replaceAll( '"', '' ) ).toBe( 'Roboto Slab' );
		expect( titlePaint.transform ).toBe( 'none' );
		const titleTiles = title.locator( '.cr-cutout__tiles' );
		if ( await titleTiles.count() ) {
			expect( ( await titleTiles.evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).display ) ) ).every( ( display ) => 'none' === display ) ).toBe( true );
		} else {
			console.log( 'single-print fixture has no cutout H1; zine-template-part-test.php asserts the CSS fallback' );
		}
		const flankPaint = await title.evaluate( ( node ) => [ '::before', '::after' ].map( ( pseudo ) => {
			const style = getComputedStyle( node, pseudo );
			return { opacity: style.opacity, visibility: style.visibility };
		} ) );
		expect( flankPaint ).toEqual( [ { opacity: '0', visibility: 'hidden' }, { opacity: '0', visibility: 'hidden' } ] );

		const quote = page.locator( '.single-post__content .wp-block-quote, .single-post__content blockquote' ).first();
		await expect( quote ).toHaveCount( 1 );
		await page.emulateMedia( { media: 'screen', colorScheme: 'light', forcedColors: 'none' } );
		const screenQuoteWidth = await quote.evaluate( ( node ) => getComputedStyle( node ).borderLeftWidth );
		await page.emulateMedia( { media: 'print', colorScheme: 'light', forcedColors: 'none' } );
		const quotePaint = await quote.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { width: style.borderLeftWidth, style: style.borderLeftStyle, color: style.borderLeftColor, font: style.fontFamily, italic: style.fontStyle, size: parseFloat( style.fontSize ) };
		} );
		expect( quotePaint, 'the quote prints in the theme core/quote paint: the on-screen border width (the merged global style fixes it with !important, which this lane does not override), solid, marker orange, italic' ).toMatchObject( { width: screenQuoteWidth, style: 'solid', color: ORANGE, italic: 'italic' } );
		expect( quotePaint.font.split( ',' )[0].replaceAll( '"', '' ) ).toBe( 'Roboto Slab' );
		expect( quotePaint.size ).toBeGreaterThanOrEqual( 12 );

		const order = await page.evaluate( () => {
			const top = ( selector ) => document.querySelector( selector )?.getBoundingClientRect().top ?? Number.POSITIVE_INFINITY;
			return [
				top( 'h1.single-post__title' ),
				top( '.single-post__kind' ),
				top( '.cr-print-byline' ),
				top( '.single-post__featured' ),
				top( '.single-post__content' ),
				top( '.cr-print-permalink' ),
			];
		} );
		for ( let index = 1; index < order.length; index++ ) {
			expect( order[ index ], `print item ${ index + 1 } follows item ${ index }` ).toBeGreaterThanOrEqual( order[ index - 1 ] );
		}
		await expect( page.locator( '.cr-print-byline' ) ).toBeVisible();
		await expect( page.locator( '.cr-print-permalink' ) ).toBeVisible();
	} );

	test( 'C13: dark screen preference still prints light paper and a long post flows', async ( { page } ) => {
		await open( page, PATHS.get( 'long' ), 1280, 'dark' );
		const lightPalette = await printPalette( page, 'light' );
		const darkPalette = await printPalette( page, 'dark' );
		expect( darkPalette, 'dark print restores light custom properties and code paint' ).toEqual( lightPalette );
		const paper = await page.locator( 'body' ).evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, color: getComputedStyle( node ).color } ) );
		expect( paper ).toEqual( { background: IVORY, color: VIOLET } );

		const clipping = await page.locator( '.single-post__content' ).evaluate( ( content ) => {
			const rows = [];
			for ( let node = content; node; node = node.parentElement ) {
				const style = getComputedStyle( node );
				if ( [ style.overflow, style.overflowY ].some( ( value ) => [ 'hidden', 'clip' ].includes( value ) ) ) {
					rows.push( { node: node.className, overflow: style.overflow, overflowY: style.overflowY } );
				}
			}
			return rows;
		} );
		expect( clipping ).toEqual( [] );
		const pdf = await page.pdf( { format: 'Letter', printBackground: false } );
		const pages = ( pdf.toString( 'latin1' ).match( /\/Type\s*\/Page\b/g ) || [] ).length;
		expect( pages ).toBeGreaterThan( 1 );
	} );
} );
