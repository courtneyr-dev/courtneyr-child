// `CR_SPEC=zine-print.spec.mjs CR_ZINE_SET=<panel|print|cover|stream|dark|a11y>`:
// X-T269 zine controls and booklet paint. Read-only GETs against a local host.
import { test, expect } from '@playwright/test';
import { BASE_URL, assertLocalTarget } from '../routes.mjs';

assertLocalTarget( BASE_URL );

const SET = process.env.CR_ZINE_SET || 'panel';
const ARCHIVE = process.env.CR_ZINE_ARCHIVE_PATH || '/kind/recipe/';
const STREAM = process.env.CR_ZINE_STREAM_PATH || '/stream/';
const PROOF = process.env.CR_ZINE_PROOF_PATH || '';
const PANEL_PATH = PROOF || ARCHIVE;
const IVORY = 'rgb(251, 250, 245)';
const VIOLET = 'rgb(36, 28, 74)';
const ORANGE = 'rgb(251, 133, 0)';
const PRUSSIAN = 'rgb(2, 48, 71)';
const NIGHT = 'rgb(37, 31, 28)';
const BLACK = 'rgb(26, 23, 20)';
const YELLOW = 'rgb(255, 183, 3)';
const LOCAL_ORIGIN = new URL( BASE_URL ).origin;
const GUARDED_PAGES = new WeakSet();
const PRINT_TOKENS = [ '--cr-ink-soft', '--cr-border', '--cr-tape', '--cr-code-bg', '--cr-code-ink' ];
const LANDSCAPE_SHEETS_CSS_PX = { Letter: [ 1056, 816 ], A4: [ 1123, 794 ] };

function withPages( path, pages ) {
	const url = new URL( path, BASE_URL );
	url.searchParams.set( 'pkiw_zine_pages', String( pages ) );
	return `${ url.pathname }${ url.search }`;
}

async function open( page, path, width = 1440, height = 1000 ) {
	if ( ! GUARDED_PAGES.has( page ) ) {
		await page.route( '**/*', async ( route ) => {
			const request = route.request();
			const sameOriginGet = 'GET' === request.method() && new URL( request.url() ).origin === LOCAL_ORIGIN;
			await ( sameOriginGet ? route.continue() : route.abort() );
		} );
		GUARDED_PAGES.add( page );
	}
	await page.setViewportSize( { width, height } );
	const response = await page.goto( path, { waitUntil: 'load' } );
	expect( response?.status(), `${ path } status` ).toBe( 200 );
	await page.evaluate( () => document.fonts.ready );
}

async function noHorizontalOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function openPanel( page, path = PANEL_PATH, width = 1440 ) {
	await open( page, path, width );
	const details = page.locator( '.pkiw-zine-export' ).first();
	await expect( details ).toHaveCount( 1 );
	if ( ! await details.evaluate( ( node ) => node.open ) ) {
		await details.locator( 'summary' ).click();
	}
	return details;
}

async function tabOrder( page, expected ) {
	const summary = page.locator( '.pkiw-zine-export summary' ).first();
	if ( ! await summary.evaluate( ( node ) => node.parentElement.open ) ) {
		await summary.click();
	}
	await summary.focus();
	const actual = [ 'summary' ];
	for ( let press = 0; press < 12 && 'Preview zine' !== actual[ actual.length - 1 ]; press++ ) {
		await page.keyboard.press( 'Tab' );
		const stop = await page.evaluate( () => {
			const node = document.activeElement;
			return 'SUMMARY' === node?.tagName ? 'summary' : node?.id || node?.textContent?.trim();
		} );
		if ( stop !== actual[ actual.length - 1 ] ) {
			actual.push( stop );
		}
	}
	expect( actual, 'a native radio group is one tab stop and a date input repeats its id per segment' ).toEqual( expected );
	await page.locator( '#pkiw-zine-pages-8' ).focus();
	await page.keyboard.press( 'ArrowRight' );
	expect( await page.evaluate( () => document.activeElement?.id ), 'arrow key reaches 16 pages' ).toBe( 'pkiw-zine-pages-16' );
}

function countPdfPages( buffer ) {
	return ( buffer.toString( 'latin1' ).match( /\/Type\s*\/Page\b/g ) || [] ).length;
}

async function printGeometry( page ) {
	return page.locator( '.pkiw-zine-pages' ).evaluate( ( root ) => {
		const sheets = root.getBoundingClientRect();
		return {
			sheets: { left: sheets.left, top: sheets.top, width: sheets.width, height: sheets.height },
			pages: [ ...root.querySelectorAll( '.pkiw-zine-page' ) ].map( ( node ) => {
				const rect = node.getBoundingClientRect();
				const footer = node.querySelector( 'footer' )?.getBoundingClientRect() || null;
				return {
					page: node.dataset.pkiwZinePage,
					row: node.dataset.row,
					inverted: node.dataset.inverted,
					rect: { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height },
					footer: footer ? { left: footer.left, top: footer.top, right: footer.right, bottom: footer.bottom } : null,
					transform: getComputedStyle( node ).transform,
				};
			} ),
		};
	} );
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
			document.querySelector( '.pkiw-zine-page__content' )?.append( code );
		}
		const root = getComputedStyle( document.documentElement );
		const codeStyle = getComputedStyle( code );
		return {
			tokens: Object.fromEntries( properties.map( ( property ) => [ property, root.getPropertyValue( property ).trim() ] ) ),
			code: { background: codeStyle.backgroundColor, color: codeStyle.color },
		};
	}, { properties: PRINT_TOKENS, value: theme } );
}

async function printedText( page ) {
	return page.evaluate( () => {
		const SLACK = 1;
		const box = ( node ) => node.getBoundingClientRect();
		const kept = ( rect, clip ) => {
			if ( Math.min( rect.right, clip.right ) - Math.max( rect.left, clip.left ) <= SLACK || Math.min( rect.bottom, clip.bottom ) - Math.max( rect.top, clip.top ) <= SLACK ) {
				return 'none';
			}
			return rect.top >= clip.top - SLACK && rect.bottom <= clip.bottom + SLACK ? 'all' : 'part';
		};
		const clipAround = ( text, content ) => {
			let clip = box( content );
			for ( let parent = text.parentElement; parent !== content; parent = parent.parentElement ) {
				if ( 'visible' !== getComputedStyle( parent ).overflowY ) {
					const inner = box( parent );
					clip = { left: Math.max( clip.left, inner.left ), top: Math.max( clip.top, inner.top ), right: Math.min( clip.right, inner.right ), bottom: Math.min( clip.bottom, inner.bottom ) };
				}
			}
			return clip;
		};
		const lineBox = ( glyphBox, text ) => {
			const leading = parseFloat( getComputedStyle( text.parentElement ).lineHeight ) || glyphBox.height;
			const top = glyphBox.top + ( glyphBox.height - leading ) / 2;
			return { left: glyphBox.left, right: glyphBox.right, top, bottom: top + leading };
		};
		const cut = [];
		for ( const content of document.querySelectorAll( '.pkiw-zine-page__content' ) ) {
			const walker = document.createTreeWalker( content, NodeFilter.SHOW_TEXT );
			while ( walker.nextNode() ) {
				const text = walker.currentNode;
				if ( ! text.textContent.trim() ) {
					continue;
				}
				const range = document.createRange();
				range.selectNodeContents( text );
				for ( const glyphBox of range.getClientRects() ) {
					if ( 'part' === kept( lineBox( glyphBox, text ), clipAround( text, content ) ) ) {
						cut.push( `page ${ content.parentElement.dataset.pkiwZinePage }: ${ text.textContent.trim().slice( 0, 30 ) }` );
					}
				}
			}
		}
		const contents = document.querySelector( '[data-pkiw-zine-page="2"]' );
		const panel = box( contents.querySelector( '.pkiw-zine-page__content' ) );
		const listed = [ ...contents.querySelectorAll( 'ol > li' ) ].filter( ( item ) => {
			const number = box( item.lastElementChild );
			return 'all' === kept( box( item ), panel ) && number.left >= panel.left - SLACK && number.right <= panel.right + SLACK;
		} );
		return {
			folios: [ ...document.querySelectorAll( '.pkiw-zine-entry__folio' ) ].map( ( node ) => node.textContent.trim() ),
			listed: listed.map( ( item ) => item.lastElementChild.textContent.trim() ),
			cut,
		};
	} );
}

test.describe( 'Zine export panel', () => {
	test.skip( 'panel' !== SET, 'panel fixture set' );

	test( 'T3: summary and open panel use the light paint without layout rotation', async ( { page } ) => {
		await page.emulateMedia( { colorScheme: 'light' } );
		await open( page, PANEL_PATH );
		const details = page.locator( '.pkiw-zine-export' ).first();
		const summary = details.locator( 'summary' );
		await expect( details ).toHaveCount( 1 );
		const closed = await summary.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { height: node.getBoundingClientRect().height, font: style.fontFamily, border: style.borderTopWidth, shadow: style.boxShadow };
		} );
		expect( closed.height ).toBeGreaterThanOrEqual( 44 );
		expect( closed.font.split( ',' )[0].replaceAll( '"', '' ) ).toBe( 'Roboto Slab' );
		expect( closed.border ).toBe( '2px' );
		expect( closed.shadow ).toContain( '4px 4px' );

		await summary.click();
		const paint = await details.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { background: style.backgroundColor, border: style.borderTopWidth, borderColor: style.borderTopColor, shadow: style.boxShadow, transform: style.transform };
		} );
		expect( paint.background ).toBe( IVORY );
		expect( paint.border ).toBe( '2px' );
		expect( paint.borderColor ).toBe( VIOLET );
		expect( paint.shadow ).toContain( '4px 4px' );
		expect( paint.transform ).toBe( 'none' );

		const button = details.getByRole( 'button', { name: 'Preview zine' } );
		const cta = await button.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { background: style.backgroundColor, color: style.color, border: style.borderTopWidth, borderColor: style.borderTopColor, shadow: style.boxShadow, height: node.getBoundingClientRect().height };
		} );
		expect( cta ).toMatchObject( { background: ORANGE, color: PRUSSIAN, border: '2px', borderColor: VIOLET } );
		expect( cta.shadow ).toContain( '4px 4px' );
		expect( cta.height ).toBeGreaterThanOrEqual( 44 );
	} );

	test( 'T3: widths, field flow, targets, drawing and tab sequence stay usable', async ( { page } ) => {
		for ( const width of [ 320, 375, 800, 1440 ] ) {
			const details = await openPanel( page, PANEL_PATH, width );
			await noHorizontalOverflow( page, `${ width }px panel` );
			const summary = await details.locator( 'summary' ).boundingBox();
			const panel = await details.boundingBox();
			expect( Math.abs( summary.width - panel.width + ( await details.evaluate( ( node ) => parseFloat( getComputedStyle( node ).paddingLeft ) + parseFloat( getComputedStyle( node ).paddingRight ) ) ) ), `${ width }px summary spans panel content` ).toBeLessThanOrEqual( 4 );
		}

		let details = await openPanel( page, PANEL_PATH, 1024 );
		const wideSpans = await details.locator( 'fieldset, input[type="date"], button[type="submit"]' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const rect = node.getBoundingClientRect();
			return [ rect.top, rect.bottom ];
		} ) );
		expect( Math.max( ...wideSpans.map( ( span ) => span[ 0 ] ) ), '64rem fields share one row (vertical ranges overlap)' ).toBeLessThan( Math.min( ...wideSpans.map( ( span ) => span[ 1 ] ) ) );
		const drawing = await details.evaluate( ( node ) => getComputedStyle( node, '::before' ).maskImage );
		expect( drawing ).not.toBe( 'none' );

		details = await openPanel( page, PANEL_PATH, 639 );
		const narrowTops = await details.locator( 'fieldset, input[type="date"], button[type="submit"]' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => Math.round( node.getBoundingClientRect().top ) ) );
		expect( new Set( narrowTops ).size ).toBe( narrowTops.length );
		expect( await details.evaluate( ( node ) => getComputedStyle( node, '::before' ).display ) ).toBe( 'none' );

		for ( const target of await details.locator( 'summary, fieldset label, input[type="date"], button[type="submit"]' ).all() ) {
			expect( ( await target.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		}
		expect( await details.locator( 'label:has(input[type="radio"]:checked)' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( 'rgba(0, 0, 0, 0)' );

		await open( page, PANEL_PATH );
		await tabOrder( page, [ 'summary', 'pkiw-zine-pages-8', 'pkiw-zine-from', 'pkiw-zine-to', 'Preview zine' ] );
	} );
} );

test.describe( 'Zine dark and forced-color paint', () => {
	test.skip( ! [ 'dark', 'a11y' ].includes( SET ), 'dark or accessibility fixture set' );

	test( 'T3: stored dark mode uses night stock and token CTA colors', async ( { page } ) => {
		await page.emulateMedia( { colorScheme: 'dark' } );
		await page.addInitScript( () => localStorage.setItem( 'courtneyr-theme', 'dark' ) );
		const details = await openPanel( page );
		const paint = await details.evaluate( ( node ) => {
			const panel = getComputedStyle( node );
			const input = getComputedStyle( node.querySelector( 'input[type="date"]' ) );
			const button = getComputedStyle( node.querySelector( 'button' ) );
			return { panel: panel.backgroundColor, ink: panel.color, border: panel.borderTopColor, shadow: panel.boxShadow, input: input.backgroundColor, cta: button.backgroundColor, ctaInk: button.color };
		} );
		expect( paint ).toMatchObject( { panel: NIGHT, ink: IVORY, border: IVORY, input: BLACK, cta: YELLOW, ctaInk: VIOLET } );
		expect( paint.shadow ).toContain( YELLOW );
	} );

	test( 'T3: system dark mode uses night stock with ivory ink and yellow shadows', async ( { page } ) => {
		await page.emulateMedia( { colorScheme: 'dark' } );
		const details = await openPanel( page );
		await page.mouse.move( 0, 0 );
		const paint = await details.evaluate( ( node ) => {
			const panel = getComputedStyle( node );
			const summary = getComputedStyle( node.querySelector( 'summary' ) );
			const input = getComputedStyle( node.querySelector( 'input[type="date"]' ) );
			return { theme: document.documentElement.dataset.theme || 'unset', panel: panel.backgroundColor, ink: panel.color, border: panel.borderTopColor, borderWidth: panel.borderTopWidth, shadow: panel.boxShadow, summary: summary.backgroundColor, summaryInk: summary.color, summaryBorder: summary.borderTopColor, summaryShadow: summary.boxShadow, input: input.backgroundColor, inputInk: input.color };
		} );
		expect( [ 'unset', 'system' ], 'no stored choice, so the system scheme decides' ).toContain( paint.theme );
		expect( paint ).toMatchObject( { panel: NIGHT, ink: IVORY, border: IVORY, borderWidth: '2px', summary: NIGHT, summaryInk: IVORY, summaryBorder: IVORY, input: BLACK, inputInk: IVORY } );
		expect( paint.shadow, 'panel hard shadow is selective yellow' ).toContain( `${ YELLOW } 4px 4px 0px` );
		expect( paint.summaryShadow, 'summary hard shadow is selective yellow' ).toContain( `${ YELLOW } 4px 4px 0px` );
	} );

	test( 'T3: forced colors removes shadows and the booklet drawing but keeps focus', async ( { page } ) => {
		await page.emulateMedia( { forcedColors: 'active' } );
		const details = await openPanel( page );
		const forced = await details.evaluate( ( node ) => ( {
			border: getComputedStyle( node ).borderTopWidth,
			shadow: getComputedStyle( node ).boxShadow,
			drawing: getComputedStyle( node, '::before' ).display,
		} ) );
		expect( forced ).toEqual( { border: '2px', shadow: 'none', drawing: 'none' } );
		const summary = details.locator( 'summary' );
		await page.keyboard.press( 'Shift' );
		await summary.focus();
		expect( await summary.evaluate( ( node ) => getComputedStyle( node ).outlineStyle ) ).not.toBe( 'none' );
	} );

	test( 'T3: summary hover and focus paint stays visible in light, stored-dark and system-dark modes', async ( { page } ) => {
		await page.emulateMedia( { colorScheme: 'light', forcedColors: 'none' } );
		const details = await openPanel( page );
		const summary = details.locator( 'summary' );
		for ( const mode of [ 'light', 'dark', 'system' ] ) {
			await page.emulateMedia( { colorScheme: 'light' === mode ? 'light' : 'dark', forcedColors: 'none' } );
			await page.evaluate( ( theme ) => { document.documentElement.dataset.theme = theme; }, mode );
			await summary.hover();
			const hover = await summary.evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, color: getComputedStyle( node ).color } ) );
			expect( hover, `${ mode } hover paint` ).toEqual( 'light' === mode ? { background: VIOLET, color: IVORY } : { background: IVORY, color: VIOLET } );
			await page.mouse.move( 0, 0 );
			await page.keyboard.press( 'Shift' );
			await summary.focus();
			const focus = await summary.evaluate( ( node ) => {
				const style = getComputedStyle( node );
				return { width: style.outlineWidth, style: style.outlineStyle, color: style.outlineColor, shadow: style.boxShadow };
			} );
			expect( focus.width, `${ mode } focus ring width` ).toBe( '3px' );
			expect( focus.style, `${ mode } focus ring style` ).toBe( 'solid' );
			expect( focus.color, `${ mode } selective-yellow focus ring` ).toBe( YELLOW );
			expect( focus.shadow, `${ mode } focus halo` ).toContain( 'light' === mode ? VIOLET : IVORY );
			expect( focus.shadow, `${ mode } focus halo spread` ).toContain( '7px' );
		}
	} );
} );

test.describe( 'Zine cover and screen reading order', () => {
	test.skip( ! [ 'cover', 'stream' ].includes( SET ), 'cover or Stream fixture set' );

	test( 'T7: cover has one exact accessible title and nonrepeating tile tilts', async ( { page } ) => {
		const source = 'stream' === SET ? STREAM : ARCHIVE;
		await open( page, withPages( source, 8 ) );
		const title = page.locator( '.pkiw-zine__title.cr-cutout' );
		await expect( title ).toHaveCount( 1 );
		const copy = title.locator( '.cr-cutout__text.screen-reader-text' );
		const tiles = title.locator( '.cr-cutout__tiles[aria-hidden="true"]' );
		await expect( tiles ).toHaveCount( 1 );
		await expect( title ).toHaveAccessibleName( ( await copy.textContent() ).trim() );
		const rotations = await tiles.locator( '.cr-cutout__tile' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => [ ...node.classList ].find( ( name ) => /^is-r[0-3]$/.test( name ) ) ) );
		for ( let index = 1; index < rotations.length; index++ ) {
			expect( rotations[ index ] ).not.toBe( rotations[ index - 1 ] );
		}

		await page.emulateMedia( { media: 'print' } );
		const tileTransforms = await tiles.locator( '.cr-cutout__tile' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).transform ) );
		expect( tileTransforms.every( ( value ) => 'none' !== value ) ).toBe( true );
		const tileContrast = await tiles.locator( '.cr-cutout__tile' ).evaluateAll( ( nodes ) => {
			const rgb = ( value ) => ( value.match( /[\d.]+/g ) || [] ).slice( 0, 3 ).map( Number );
			const luminance = ( value ) => rgb( value ).map( ( channel ) => {
				const normalized = channel / 255;
				return normalized <= 0.04045 ? normalized / 12.92 : ( ( normalized + 0.055 ) / 1.055 ) ** 2.4;
			} ).reduce( ( sum, channel, index ) => sum + channel * [ 0.2126, 0.7152, 0.0722 ][ index ], 0 );
			return nodes.map( ( node ) => {
				const foreground = getComputedStyle( node ).color;
				const background = getComputedStyle( node.querySelector( '.cr-cutout__paper' ), '::before' ).backgroundColor;
				const values = [ luminance( foreground ), luminance( background ) ].sort( ( a, b ) => b - a );
				return { foreground, background, contrast: ( values[0] + 0.05 ) / ( values[1] + 0.05 ) };
			} );
		} );
		expect( tileContrast.length, 'cover fixture has cutout tiles' ).toBeGreaterThan( 0 );
		for ( const tile of tileContrast ) {
			expect( tile.foreground, 'tile ink stays violet when background graphics are disabled' ).toBe( VIOLET );
			expect( tile.background, 'tile paper stays light when background graphics are enabled' ).toBe( IVORY );
			expect( tile.contrast, `tile ${ tile.foreground } on ${ tile.background } remains legible with print backgrounds on or off` ).toBeGreaterThanOrEqual( 4.5 );
		}
		const otherTransforms = await page.locator( '.pkiw-zine-page :not(.cr-cutout__tile)' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => ! node.closest( '.cr-cutout__tile' ) ).map( ( node ) => getComputedStyle( node ).transform ).filter( ( value ) => 'none' !== value ) );
		expect( otherTransforms ).toEqual( [] );
		const pageTransforms = await page.locator( '.pkiw-zine-page' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { inverted: node.dataset.inverted, transform: getComputedStyle( node ).transform } ) ) );
		for ( const row of pageTransforms ) {
			expect( row.transform ).toBe( '1' === row.inverted ? 'matrix(-1, 0, 0, -1, 0, 0)' : 'none' );
		}
	} );

	test( 'T7: print-view pages stay upright in one screen column at 320 and 375', async ( { page } ) => {
		const source = 'stream' === SET ? STREAM : ARCHIVE;
		for ( const width of [ 320, 375 ] ) {
			await open( page, withPages( source, 8 ), width, 900 );
			await noHorizontalOverflow( page, `${ width }px print view` );
			const rows = await page.locator( '.pkiw-zine-page' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { left: Math.round( node.getBoundingClientRect().left ), top: Math.round( node.getBoundingClientRect().top ), transform: getComputedStyle( node ).transform } ) ) );
			expect( new Set( rows.map( ( row ) => row.left ) ).size ).toBe( 1 );
			expect( rows.every( ( row ) => 'none' === row.transform ) ).toBe( true );
			for ( let index = 1; index < rows.length; index++ ) {
				expect( rows[ index ].top ).toBeGreaterThan( rows[ index - 1 ].top );
			}
		}
	} );
} );

test.describe( 'Booklet imposition', () => {
	test.skip( 'print' !== SET, 'print fixture set' );

	for ( const format of [ 'Letter', 'A4' ] ) {
		for ( const pages of [ 8, 16 ] ) {
			test( `T7: ${ format } ${ pages }-page imposition is measurable; fold-by-hand remains unverifiable here`, async ( { page, browser } ) => {
				console.log( `browser engine: ${ browser.browserType().name() } ${ browser.version() }` );
				await page.emulateMedia( { colorScheme: 'dark' } );
				await open( page, withPages( ARCHIVE, pages ), 1100, 850 );
				await page.emulateMedia( { media: 'print', colorScheme: 'dark' } );
				const geometry = await printGeometry( page );
				const firstSide = geometry.pages.filter( ( row ) => Number( row.page ) <= 8 );
				for ( const row of firstSide ) {
					expect( Math.abs( row.rect.width - geometry.sheets.width * 0.25 ) ).toBeLessThanOrEqual( 1 );
					expect( Math.abs( row.rect.height - 850 * 0.5 ) ).toBeLessThanOrEqual( 1 );
					expect( row.transform ).toBe( 'top' === row.row ? 'matrix(-1, 0, 0, -1, 0, 0)' : 'none' );
					if ( row.footer ) {
						expect( row.footer.left ).toBeGreaterThanOrEqual( row.rect.left );
						expect( row.footer.right ).toBeLessThanOrEqual( row.rect.right );
						expect( row.footer.top ).toBeGreaterThanOrEqual( row.rect.top );
						expect( row.footer.bottom ).toBeLessThanOrEqual( row.rect.bottom );
					}
				}

				const sizes = await page.locator( '.pkiw-zine-page :is(p, li, span, figcaption)' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => 'none' !== getComputedStyle( node ).display && node.getClientRects().length ).map( ( node ) => parseFloat( getComputedStyle( node ).fontSize ) ) );
				expect( Math.min( ...sizes ) ).toBeGreaterThanOrEqual( 12 );
				const lightPalette = await printPalette( page, 'light' );
				const darkPalette = await printPalette( page, 'dark' );
				expect( darkPalette, 'dark booklet print restores light custom properties and code paint' ).toEqual( lightPalette );
				const paper = await page.locator( 'body' ).evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, color: getComputedStyle( node ).color } ) );
				expect( paper ).toEqual( { background: IVORY, color: VIOLET } );
				const shadows = await page.locator( '.pkiw-zine-pages, .pkiw-zine-pages *' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).boxShadow ).filter( ( value ) => 'none' !== value ) );
				expect( shadows ).toEqual( [] );
				const pageRule = await page.evaluate( () => [ ...document.styleSheets ].flatMap( ( sheet ) => {
					try { return [ ...sheet.cssRules ].map( ( rule ) => rule.cssText ); } catch { return []; }
				} ).join( '\n' ) );
				expect( pageRule ).toContain( '@page pkiw-zine' );
				expect( pageRule ).toContain( 'size: landscape' );
				expect( pageRule ).toContain( 'margin: 0' );

				const pdf = await page.pdf( { landscape: true, format, printBackground: false } );
				expect( countPdfPages( pdf ) ).toBe( 8 === pages ? 1 : 2 );
			} );
		}
	}

	for ( const [ format, sheet ] of Object.entries( LANDSCAPE_SHEETS_CSS_PX ) ) {
		for ( const pages of [ 8, 16 ] ) {
			for ( const [ source, path ] of [ [ 'archive', ARCHIVE ], [ 'Stream', STREAM ] ] ) {
				test( `T7: ${ format } ${ pages }-page ${ source } booklet lists every entry and clips between lines`, async ( { page } ) => {
					await open( page, withPages( path, pages ), ...sheet );
					await page.emulateMedia( { media: 'print' } );
					const booklet = await printedText( page );
					expect( booklet.folios.length, 'the fixture fills entry pages' ).toBeGreaterThan( 0 );
					expect.soft( booklet.listed, 'the contents panel prints every included entry with its page number' ).toEqual( booklet.folios );
					expect.soft( booklet.cut, 'no clip runs through a line of text' ).toEqual( [] );

					await page.locator( '[data-pkiw-zine-page="2"] li > :first-child' ).evaluateAll( ( titles ) => titles.forEach( ( title ) => {
						title.textContent = 'A title long enough to wrap onto three lines of a quarter-sheet panel when nothing limits it';
					} ) );
					const wordy = await printedText( page );
					expect.soft( wordy.listed, 'long titles leave every entry and its page number in the panel' ).toEqual( booklet.folios );
					expect( wordy.cut, 'no clip runs through a long title' ).toEqual( [] );
				} );
			}
		}
	}

	test( 'T7: a clipped overflow column keeps its list markers off the panel', async ( { page } ) => {
		await open( page, withPages( STREAM, 16 ), ...LANDSCAPE_SHEETS_CSS_PX.Letter );
		await page.emulateMedia( { media: 'print' } );
		const panel = page.locator( '.pkiw-zine-page:has(footer) .pkiw-zine-page__content' ).first();
		const overflowItems = ( node, hide ) => {
			const clip = node.getBoundingClientRect();
			const beside = [ ...node.querySelectorAll( 'li' ) ].filter( ( item ) => item.getBoundingClientRect().left >= clip.right || item.getBoundingClientRect().right <= clip.left );
			if ( hide ) {
				beside.forEach( ( item ) => {
					item.style.visibility = 'hidden';
				} );
			}
			return beside.length;
		};
		await panel.evaluate( ( node ) => {
			node.innerHTML = `<ol>${ '<li>Injected step</li>'.repeat( 30 ) }</ol>`;
		} );
		expect( await panel.evaluate( overflowItems, false ), 'the list runs into a clipped overflow column' ).toBeGreaterThan( 0 );
		const printed = await panel.screenshot();
		await panel.evaluate( overflowItems, true );
		expect( Buffer.compare( printed, await panel.screenshot() ), 'hiding the overflow column changes no pixel inside the panel' ).toBe( 0 );
	} );

	const MARGIN_04 = { margin: { top: '0.4in', right: '0.4in', bottom: '0.4in', left: '0.4in' } };
	for ( const [ name, format, options, items ] of [
		[ 'portrait A4', 'A4', {}, 0 ],
		[ 'portrait Letter', 'Letter', {}, 0 ],
		[ 'portrait A4 with long lists', 'A4', {}, 9 ],
		[ 'landscape Letter with 0.4in margins', 'Letter', { landscape: true, ...MARGIN_04 }, 9 ],
		[ 'landscape Letter without margins', 'Letter', { landscape: true }, 9 ],
	] ) {
		test( `T7: a ${ name } print of the Stream booklet returns`, async ( { page } ) => {
			await open( page, withPages( STREAM, 8 ) );
			const strip = await page.evaluate( ( count ) => {
				const lists = [ '.pkiw-wrapped__metrics', '.pkiw-wrapped__top' ].map( ( selector ) => document.querySelector( `.pkiw-zine-page ${ selector }` ) );
				lists.forEach( ( list ) => {
					while ( list && list.children.length < count ) {
						list.append( list.children[ list.children.length - 1 ].cloneNode( true ) );
					}
				} );
				return lists.every( Boolean );
			}, items );
			expect( strip, 'the Wrapped strip prints in the booklet' ).toBe( true );
			const hung = new Promise( ( resolve, reject ) => setTimeout( () => reject( new Error( 'page.pdf did not return in 30 s' ) ), 30000 ) );
			const pdf = await Promise.race( [ page.pdf( { format, preferCSSPageSize: true, printBackground: true, ...options } ), hung ] );
			expect( pdf.subarray( 0, 5 ).toString(), 'a PDF came back' ).toBe( '%PDF-' );
		} );
	}

	test( 'T7: a failed booklet image leaves no visible empty frame', async ( { page } ) => {
		await open( page, withPages( STREAM, 16 ) );
		const entryImages = page.locator( '.pkiw-zine-page img:not([data-cr-cover-fallback] img)' );
		await expect( entryImages, 'the print fixture includes an entry image, not a Wrapped cover, which the cover handler in site-ux.js replaces on error' ).not.toHaveCount( 0 );
		const image = entryImages.first();
		await image.evaluate( ( node ) => {
			node.hidden = false;
			node.removeAttribute( 'srcset' );
			node.removeAttribute( 'sizes' );
			node.src = `${ location.origin }/__cr-zine-missing-image-${ Date.now() }.jpg`;
		} );
		await expect.poll( () => image.evaluate( ( node ) => node.hidden ) ).toBe( true );
		const frame = await image.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			const rect = node.getBoundingClientRect();
			return { display: style.display, width: rect.width, height: rect.height };
		} );
		expect( frame ).toEqual( { display: 'none', width: 0, height: 0 } );
	} );
} );
