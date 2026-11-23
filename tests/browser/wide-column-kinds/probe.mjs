// Geometry of kind singles on the live Studio site, measured in Chrome.
// usage: node probe.mjs <urls.tsv> <names,comma,separated|all> <out.json> [widths] [schemes]
// Records, per page, width and color scheme: the rectangles of the column, the kind's record and every
// block beside it, the sparkle's box, page overflow and console errors. Compare two runs with compare.mjs.
import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';

const [, , tsv, pick, out, widthArg, schemeArg] = process.argv;
const widths = ( widthArg || '1672,1448,1280,1100,1024,768,390,320' ).split( ',' ).map( Number );
const schemes = ( schemeArg || 'light,dark' ).split( ',' );
const rows = readFileSync( tsv, 'utf8' ).trim().split( '\n' ).map( ( l ) => l.split( '\t' ) )
	.filter( ( [ n ] ) => 'all' === pick || pick.split( ',' ).includes( n ) );

const browser = await chromium.launch( { executablePath: process.env.CR_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' } );
const result = {};
for ( const scheme of schemes ) {
	const context = await browser.newContext( { colorScheme: 'dark' === scheme ? 'dark' : 'light' } );
	await context.addInitScript( ( s ) => {
		try {
			localStorage.setItem( 'courtneyr-theme', s );
		} catch ( e ) {}
	}, scheme );
	const page = await context.newPage();
	const errors = [];
	page.on( 'console', ( m ) => 'error' === m.type() && errors.push( m.text() ) );
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
	for ( const [ name, url ] of rows ) {
		for ( const w of widths ) {
			errors.length = 0;
			await page.setViewportSize( { width: w, height: 900 } );
			await page.goto( url, { waitUntil: 'networkidle' } );
			await page.evaluate( () => document.fonts.ready );
			const g = await page.evaluate( () => {
				const r = ( el ) => {
					if ( ! el ) {
						return null;
					}
					const b = el.getBoundingClientRect();
					return [ b.left, b.top + scrollY, b.width, b.height ].map( ( v ) => Math.round( v * 1000 ) / 1000 );
				};
				const q = ( s ) => document.querySelector( s );
				const content = q( 'main .single-post__content' );
				const entry = q( 'main .pkiw-singular-entry' );
				const kids = ( el ) => el ? [ ...el.children ].flatMap( ( c ) => /(^| )cr-(fav|repost)-flow( |$)/.test( c.className ) ? [ ...c.children ] : [ c ] ).filter( ( c ) => ! c.hidden && 'none' !== getComputedStyle( c ).display ).map( ( c ) => ( { tag: c.tagName.toLowerCase(), cls: ( c.className && c.className.baseVal === undefined ? c.className : '' ).split( ' ' ).filter( ( x ) => /^(cr-|wp-block|align|pk-|k-)/.test( x ) ).slice( 0, 3 ).join( ' ' ), box: r( c ) } ) ) : null;
				const sparkle = content ? getComputedStyle( content, '::before' ) : null;
				const cb = content ? content.getBoundingClientRect() : null;
				return {
					main: r( q( 'main' ) ),
					content: r( content ),
					contentClass: content ? content.className : null,
					entry: r( entry ),
					entryKids: kids( entry ),
					nestedKids: kids( q( 'main .pkiw-singular-entry > .wp-block-group:not(.alignwide):not(.alignfull)' ) ),
					sheet: r( q( '.cr-fav-sheet' ) ),
					sheetH1: r( q( '.cr-fav-sheet__title' ) ),
					star: r( q( '.cr-fav-sheet__star' ) ),
					tape: r( q( '.cr-fav-sheet__tape' ) ),
					record: r( q( '.cr-fav-sheet__record' ) ),
					mount: r( q( '.cr-fav-sheet__mount' ) ),
					sheetProse: r( q( '.cr-fav-sheet > p.wp-block-paragraph' ) ),
					clip: r( q( '.cr-repost-clip--single' ) ),
					header: r( q( 'main .single-post__header' ) ),
					h1: r( q( 'main h1' ) ),
					date: r( q( 'main .single-post__date' ) ),
					chip: r( q( 'main .single-post__kind' ) ),
					postNav: r( q( '.cr-post-nav' ) ),
					sparkle: sparkle && 'none' !== sparkle.content && 'none' !== sparkle.display ? { right: sparkle.right, top: sparkle.top, width: sparkle.width, left: cb ? cb.right - parseFloat( sparkle.right ) - parseFloat( sparkle.width ) : null } : null,
					h1Count: document.querySelectorAll( 'h1' ).length,
					overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
					wideSheet: !! q( 'link#courtneyr-wide-column-css' ),
				};
			} );
			g.errors = [ ...errors ];
			result[ `${ name }|${ w }|${ scheme }` ] = g;
		}
	}
	await context.close();
}
await browser.close();
writeFileSync( out, JSON.stringify( result, null, 1 ) );
console.log( Object.keys( result ).length, 'cases ->', out );
