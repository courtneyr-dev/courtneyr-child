// Print, text enlargement, RTL, axe, DOM order and keyboard focus for kind singles on the live Studio site.
// usage: node extras.mjs <urls.tsv> <names,comma|all> <out.json>
// Writes per page: overflow and the record's right edge in print (816px), overflow at root font 32px (1280px
// and the 720px zoom proxy), RTL geometry at 1448, axe violations (WCAG 2.2 A/AA) in light and dark at 1448
// and 390, headings and focusable elements in DOM order, and the order Tab visits them.
import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const AXE = readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );
const [, , tsv, pick, out ] = process.argv;
const rows = readFileSync( tsv, 'utf8' ).trim().split( '\n' ).map( ( l ) => l.split( '\t' ) )
	.filter( ( [ n ] ) => 'all' === pick || pick.split( ',' ).includes( n ) );
const browser = await chromium.launch( { executablePath: process.env.CR_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' } );

const RECORD = '.cr-fav-sheet, .cr-repost-clip--single, .single-post__content';
const geo = ( page ) => page.evaluate( ( sel ) => {
	const r = ( s ) => {
		const el = document.querySelector( s );
		if ( ! el ) {
			return null;
		}
		const b = el.getBoundingClientRect();
		return [ b.left, b.top, b.right, b.bottom ].map( ( v ) => Math.round( v * 100 ) / 100 );
	};
	const de = document.documentElement;
	return {
		overflow: de.scrollWidth - de.clientWidth,
		record: r( sel ),
		content: r( 'main .single-post__content' ),
		h1: r( 'main h1' ),
		mount: r( '.cr-fav-sheet__mount' ),
		clip: r( '.cr-repost-clip--single' ),
		date: r( 'main .single-post__date' ),
		chip: r( 'main .single-post__kind' ),
	};
}, RECORD );

const page = async ( opts = {} ) => {
	const ctx = await browser.newContext( { viewport: { width: opts.width || 1448, height: 900 }, colorScheme: opts.scheme || 'light' } );
	await ctx.addInitScript( ( s ) => {
		try {
			localStorage.setItem( 'courtneyr-theme', s );
		} catch ( e ) {}
	}, opts.scheme || 'light' );
	return [ ctx, await ctx.newPage() ];
};

const result = {};
for ( const [ name, url ] of rows ) {
	const res = {};
	// Print at a letter page width.
	{
		const [ ctx, p ] = await page( { width: 816 } );
		await p.emulateMedia( { media: 'print' } );
		await p.goto( url, { waitUntil: 'networkidle' } );
		res.print = await geo( p );
		await ctx.close();
	}
	// 200% text: root font 32px at 1280, and the 720px viewport the theme uses as its zoom proxy.
	for ( const width of [ 1280, 720 ] ) {
		const [ ctx, p ] = await page( { width } );
		await p.goto( url, { waitUntil: 'networkidle' } );
		await p.addStyleTag( { content: 'html { font-size: 32px !important; }' } );
		res[ 'text200_' + width ] = await geo( p );
		await ctx.close();
	}
	// RTL.
	{
		const [ ctx, p ] = await page( { width: 1448 } );
		await p.goto( url, { waitUntil: 'networkidle' } );
		await p.evaluate( () => {
			document.documentElement.dir = 'rtl';
		} );
		res.rtl = await geo( p );
		await ctx.close();
	}
	// axe, light and dark, desktop and phone.
	res.axe = {};
	for ( const scheme of [ 'light', 'dark' ] ) {
		for ( const width of [ 1448, 390 ] ) {
			const [ ctx, p ] = await page( { width, scheme } );
			await p.goto( url, { waitUntil: 'networkidle' } );
			await p.addScriptTag( { content: AXE } );
			const v = await p.evaluate( async () => {
				const r = await window.axe.run( document, { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] } );
				return r.violations.map( ( x ) => `${ x.id }:${ x.nodes.length }` ).sort();
			} );
			res.axe[ `${ scheme }_${ width }` ] = v;
			await ctx.close();
		}
	}
	// DOM order and keyboard.
	{
		const [ ctx, p ] = await page( { width: 1448 } );
		await p.goto( url, { waitUntil: 'networkidle' } );
		res.dom = await p.evaluate( () => {
			const label = ( el ) => `${ el.tagName.toLowerCase() }:${ ( el.getAttribute( 'aria-label' ) || el.textContent || '' ).trim().replace( /\s+/g, ' ' ).slice( 0, 40 ) }`;
			const main = document.querySelector( 'main' );
			return {
				headings: [ ...main.querySelectorAll( 'h1,h2,h3,h4' ) ].map( label ),
				focusable: [ ...main.querySelectorAll( 'a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])' ) ].filter( ( e ) => e.offsetParent || 'fixed' === getComputedStyle( e ).position ).map( label ),
				mainText: main.innerText.replace( /\s+/g, ' ' ).slice( 0, 600 ),
			};
		} );
		// Tab from the top of main through its links; record what gets focus and whether a ring shows.
		// Scrolling is made instant so a focused link is measured where it lands, not mid-scroll.
		await p.addStyleTag( { content: 'html, body { scroll-behavior: auto !important; }' } );
		await p.evaluate( () => {
			const m = document.querySelector( 'main' );
			m.setAttribute( 'tabindex', '-1' );
			m.focus();
		} );
		const tabs = [];
		for ( let i = 0; i < 12; i++ ) {
			await p.keyboard.press( 'Tab' );
			const f = await p.evaluate( () => {
				const el = document.activeElement;
				if ( ! el || ! document.querySelector( 'main' ).contains( el ) ) {
					return null;
				}
				const cs = getComputedStyle( el );
				const b = el.getBoundingClientRect();
				return {
					label: `${ el.tagName.toLowerCase() }:${ ( el.getAttribute( 'aria-label' ) || el.textContent || '' ).trim().replace( /\s+/g, ' ' ).slice( 0, 40 ) }`,
					ring: 'none' !== cs.outlineStyle || 'none' !== cs.boxShadow,
					visible: b.width > 0 && b.height > 0 && b.bottom > 0 && b.top < innerHeight,
				};
			} );
			if ( ! f ) {
				break;
			}
			tabs.push( f );
		}
		res.tabs = tabs;
		await ctx.close();
	}
	result[ name ] = res;
	console.log( 'done', name );
}
await browser.close();
writeFileSync( out, JSON.stringify( result, null, 1 ) );
