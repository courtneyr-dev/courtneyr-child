import fs from 'node:fs';
import path from 'node:path';

const PRESSED = '[role="group"][aria-label="Theme"] button[aria-pressed="true"]';

function readState( selector ) {
	return {
		theme: document.documentElement.getAttribute( 'data-theme' ),
		stored: window.localStorage.getItem( 'courtneyr-theme' ),
		pressed: [ ...document.querySelectorAll( selector ) ].map( ( b ) => b.getAttribute( 'aria-label' ) ),
	};
}

const timedOut = ( error ) => {
	if ( error.name !== 'TimeoutError' ) {
		throw error;
	}
};

async function settle( page, theme, pressedName ) {
	await page.locator( `html[data-theme="${ theme }"]` ).waitFor( { state: 'attached', timeout: 10000 } ).catch( timedOut );
	await page.getByRole( 'button', { name: pressedName, exact: true, pressed: true } ).waitFor( { timeout: 10000 } ).catch( timedOut );
	return page.evaluate( readState, PRESSED );
}

export default async function themeToggle( { browser, baseUrl, out, step, served } ) {
	const context = await browser.newContext( { viewport: { width: 1280, height: 800 }, colorScheme: 'light' } );
	const page = await context.newPage();
	const response = await page.goto( `${ baseUrl }/`, { waitUntil: 'load' } );
	fs.writeFileSync( path.join( out, 'home.server.html' ), await response.text() );
	const home = served( response );
	step( { name: 'home-html-from-checkout', ok: home.ok, observed: home.detail } );
	const group = page.getByRole( 'group', { name: 'Theme' } );

	const first = await settle( page, 'system', 'System theme (follows OS)' );
	step( { name: 'first-visit-system', ok: response.status() === 200 && first.theme === 'system' && first.stored === null && first.pressed.join() === 'System theme (follows OS)', observed: `HTTP ${ response.status() }, data-theme=${ first.theme }, stored=${ first.stored }, pressed=[${ first.pressed }]` } );
	await page.screenshot( { path: path.join( out, '1-before.png' ) } );
	fs.writeFileSync( path.join( out, '1-before.aria.yml' ), await group.ariaSnapshot() );

	await page.getByRole( 'button', { name: 'Dark theme', exact: true } ).click();
	const dark = await settle( page, 'dark', 'Dark theme' );
	step( { name: 'click-dark-applies', ok: dark.theme === 'dark' && dark.pressed.join() === 'Dark theme', observed: `data-theme=${ dark.theme }, pressed=[${ dark.pressed }]` } );
	step( { name: 'click-dark-stores', ok: dark.stored === 'dark', observed: `localStorage courtneyr-theme=${ dark.stored }` } );
	await page.screenshot( { path: path.join( out, '2-after-dark.png' ) } );
	fs.writeFileSync( path.join( out, '2-after-dark.aria.yml' ), await group.ariaSnapshot() );

	await page.reload( { waitUntil: 'load' } );
	const reloaded = await settle( page, 'dark', 'Dark theme' );
	step( { name: 'reload-keeps-dark', ok: reloaded.theme === 'dark' && reloaded.stored === 'dark' && reloaded.pressed.join() === 'Dark theme', observed: `after reload data-theme=${ reloaded.theme }, stored=${ reloaded.stored }, pressed=[${ reloaded.pressed }]` } );
	await page.screenshot( { path: path.join( out, '3-after-reload.png' ) } );

	await context.close();
}
