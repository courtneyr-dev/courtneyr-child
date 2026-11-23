// Where the Repost sparkle paints, with and without @property registration, in left-to-right and right-to-left.
// usage: node sparkle-no-property.mjs <url> <out.json> [widths]
// "no-property" serves cr-repost.css with every @property rule removed (Chrome request interception), as an engine
// without registration would read it. Sparkle x is measured as probe.mjs measures it: the content box's right edge,
// minus the glyph's resolved right offset, minus its width.
import { chromium } from '@playwright/test';
import { writeFileSync } from 'node:fs';

const [ , , url, out, widthArg ] = process.argv;
const widths = ( widthArg || '1672,1448,1280,1100,1024,768,390' ).split( ',' ).map( Number );
const browser = await chromium.launch( { executablePath: process.env.CR_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' } );
const result = {};
for ( const mode of [ 'normal', 'no-property' ] ) {
	for ( const dir of [ 'ltr', 'rtl' ] ) {
		const ctx = await browser.newContext( { viewport: { width: widths[ 0 ], height: 900 } } );
		if ( 'no-property' === mode ) {
			await ctx.route( /cr-repost\.css/, async ( route ) => {
				const response = await route.fetch();
				const css = ( await response.text() ).replace( /@property\s+[^{]+\{[^}]*\}/g, '' );
				await route.fulfill( { response, body: css } );
			} );
		}
		const page = await ctx.newPage();
		for ( const w of widths ) {
			await page.setViewportSize( { width: w, height: 900 } );
			await page.goto( url, { waitUntil: 'networkidle' } );
			await page.evaluate( ( d ) => {
				document.documentElement.dir = d;
			}, dir );
			await page.evaluate( () => document.fonts.ready );
			result[ `${ mode }|${ dir }|${ w }` ] = await page.evaluate( () => {
				const content = document.querySelector( 'main .single-post__content' );
				const s = getComputedStyle( content, '::before' );
				const cb = content.getBoundingClientRect();
				const rules = [ ...document.styleSheets ].flatMap( ( sheet ) => {
					try {
						return [ ...sheet.cssRules ];
					} catch ( e ) {
						return [];
					}
				} ).filter( ( r ) => r instanceof CSSPropertyRule ).length;
				return {
					x: Math.round( ( cb.right - parseFloat( s.right ) - parseFloat( s.width ) ) * 100 ) / 100,
					right: s.right,
					contentLeft: Math.round( cb.left * 100 ) / 100,
					propertyRules: rules,
				};
			} );
		}
		await ctx.close();
	}
}
await browser.close();
writeFileSync( out, JSON.stringify( result, null, 1 ) );
for ( const [ k, v ] of Object.entries( result ) ) {
	console.log( k.padEnd( 24 ), String( v.x ).padStart( 9 ), `content x ${ v.contentLeft }`, `@property rules ${ v.propertyRules }` );
}
