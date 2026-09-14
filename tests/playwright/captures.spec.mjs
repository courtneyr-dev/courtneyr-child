import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';
import { ROUTE_NAMES, resolveRoute } from '../routes.mjs';

const out = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', 'output', 'captures' );
// CR_CAPTURE_FULL_PAGE=0 captures the viewport only.
const fullPage = process.env.CR_CAPTURE_FULL_PAGE !== '0';

for ( const name of ROUTE_NAMES ) {
	test( name, async ( { page, colorScheme, viewport } ) => {
		const route = await resolveRoute( name );
		expect( route, `no path found for ${ name }` ).toBeTruthy();

		await page.addInitScript( ( value ) => {
			try {
				localStorage.setItem( 'courtneyr-theme', value );
			} catch ( e ) {}
		}, colorScheme );
		const response = await page.goto( route, { waitUntil: 'load' } );

		// Full-page captures race lazy image decode (harness shoot.mjs): scroll every image into range first.
		await page.evaluate( async () => {
			for ( let y = 0; y < document.documentElement.scrollHeight; y += 600 ) {
				window.scrollTo( 0, y );
				await new Promise( ( resolve ) => setTimeout( resolve, 40 ) );
			}
			window.scrollTo( 0, 0 );
		} );
		await page.waitForFunction( () => [ ...document.images ].every( ( i ) => i.complete ), null, { timeout: 8000 } ).catch( () => {} );
		await page.evaluate( () => document.fonts.ready );

		fs.mkdirSync( out, { recursive: true } );
		const file = path.join( out, `${ name }-${ viewport.width }-${ colorScheme }.png` );
		await page.screenshot( { path: file, fullPage } );
		const theme = await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) );
		console.log( `${ name } ${ viewport.width } ${ colorScheme }: ${ route } HTTP ${ response?.status() } data-theme=${ theme } -> ${ path.basename( file ) }` );

		expect( response?.status(), `${ route } returned HTTP ${ response?.status() }` ).toBe( 200 );
	} );
}
