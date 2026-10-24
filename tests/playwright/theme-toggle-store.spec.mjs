// `CR_SPEC=theme-toggle-store.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`:
// The theme toggle after its migration from a classic footer script to the
// courtneyr/theme-toggle Interactivity API store. Covers the pre-paint inline
// initializer, desktop segments, mobile cycle control, keyboard input and the
// MutationObserver path used when another tab changes the stored preference.
import { test, expect } from '@playwright/test';

async function storeThemeBeforeLoad( page, theme ) {
	await page.addInitScript( ( value ) => {
		window.localStorage.setItem( 'courtneyr-theme', value );
	}, theme );
}

async function expectTheme( page, theme ) {
	await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', theme );
	expect( await page.evaluate( () => window.localStorage.getItem( 'courtneyr-theme' ) ) ).toBe( theme );
	await expect( page.locator( '[data-theme-set][aria-pressed="true"]' ) ).toHaveCount( 1 );
	await expect( page.locator( `[data-theme-set="${ theme }"]` ) ).toHaveAttribute( 'aria-pressed', 'true' );
	await expect( page.locator( `[data-theme-set="${ theme }"]` ) ).toHaveClass( /\bis-active\b/ );
	for ( const other of [ 'light', 'dark', 'system' ].filter( ( value ) => value !== theme ) ) {
		await expect( page.locator( `[data-theme-set="${ other }"]` ) ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( page.locator( `[data-theme-set="${ other }"]` ) ).not.toHaveClass( /\bis-active\b/ );
	}
}

test( 'The page loads the theme store module and keeps the no-flash initializer first', async ( { page } ) => {
	await page.goto( '/' );

	await expect( page.locator( 'script[src*="/js/theme-toggle/view.js"]' ) ).toHaveCount( 0 );
	const moduleLoaded = await page.evaluate( () => {
		const scripts = [ ...document.scripts ];
		const directModule = scripts.some( ( script ) => script.type === 'module' && (
			( script.id || '' ).includes( 'courtneyr/theme-toggle' ) ||
			( script.src || '' ).includes( 'interactivity/theme-toggle/view.js' )
		) );
		const importMap = scripts.some( ( script ) => script.type === 'importmap' && (
			script.textContent.includes( 'courtneyr/theme-toggle' ) ||
			script.textContent.includes( 'interactivity/theme-toggle/view.js' )
		) );
		const modulePreload = [ ...document.querySelectorAll( 'link[rel="modulepreload"]' ) ].some( ( link ) =>
			link.href.includes( 'courtneyr/theme-toggle' ) || link.href.includes( 'interactivity/theme-toggle/view.js' )
		);
		return directModule || importMap || modulePreload;
	} );
	expect( moduleLoaded ).toBe( true );

	const noFlash = page.locator( 'head #courtneyr-theme-no-flash' );
	await expect( noFlash ).toHaveCount( 1 );
	await expect( noFlash ).not.toHaveAttribute( 'src' );
	expect( await noFlash.evaluate( ( script ) => {
		const firstStylesheet = document.querySelector( 'link[rel="stylesheet"]' );
		return Boolean( firstStylesheet && ( script.compareDocumentPosition( firstStylesheet ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
	} ) ).toBe( true );
} );

test( 'The stored dark theme is the only theme applied before and after hydration', async ( { page } ) => {
	await page.addInitScript( () => {
		window.localStorage.setItem( 'courtneyr-theme', 'dark' );
		window.__themes = [];
		// <html> doesn't exist yet when an init script runs, so watch the document
		// subtree and keep only changes on the root element.
		new MutationObserver( ( records ) => {
			for ( const record of records ) {
				if ( record.target === document.documentElement ) {
					window.__themes.push( record.target.getAttribute( 'data-theme' ) );
				}
			}
		} ).observe( document, { attributes: true, subtree: true, attributeFilter: [ 'data-theme' ] } );
	} );

	await page.goto( '/' );
	await page.waitForFunction( () => window.__themes.length > 0 );
	expect( await page.evaluate( () => window.__themes ) ).toEqual( [ 'dark' ] );
	await page.waitForFunction( () => document.querySelector( '[data-theme-set="dark"]' ).getAttribute( 'aria-pressed' ) === 'true' );
	expect( await page.evaluate( () => window.__themes ) ).toEqual( [ 'dark' ] );
} );

test( 'The desktop segments apply light dark and system with one pressed control', async ( { page } ) => {
	await page.setViewportSize( { width: 1440, height: 900 } );
	await storeThemeBeforeLoad( page, 'system' );
	await page.goto( '/' );

	await expect( page.locator( '.cr-theme-segments' ) ).toBeVisible();
	for ( const theme of [ 'light', 'dark', 'system' ] ) {
		await page.locator( `[data-theme-set="${ theme }"]` ).click();
		await expectTheme( page, theme );
	}
} );

test( 'The keyboard changes themes without moving focus or changing accessible names', async ( { page } ) => {
	await page.setViewportSize( { width: 1440, height: 900 } );
	await storeThemeBeforeLoad( page, 'system' );
	await page.goto( '/' );

	const dark = page.locator( '[data-theme-set="dark"]' );
	await dark.focus();
	await page.keyboard.press( 'Enter' );
	await expectTheme( page, 'dark' );
	await expect( dark ).toBeFocused();

	const system = page.locator( '[data-theme-set="system"]' );
	await system.focus();
	await page.keyboard.press( 'Space' );
	await expectTheme( page, 'system' );
	await expect( system ).toBeFocused();

	expect( await page.locator( '[data-theme-set]' ).evaluateAll( ( buttons ) => buttons.map( ( button ) => button.getAttribute( 'aria-label' ) ) ) ).toEqual( [
		'Light theme',
		'Dark theme',
		'System theme (follows OS)',
	] );
} );

test( 'The mobile cycle control announces light dark and system in order', async ( { page } ) => {
	await page.setViewportSize( { width: 375, height: 812 } );
	await storeThemeBeforeLoad( page, 'system' );
	await page.goto( '/' );

	const cycle = page.locator( '[data-theme-cycle]' );
	const status = page.locator( '[data-theme-cycle-status]' );
	await expect( cycle ).toBeVisible();
	await expect( page.locator( '.cr-theme-segments' ) ).toBeHidden();
	await expect( status ).toHaveText( '' );

	for ( const expected of [
		{ theme: 'light', label: 'Appearance: light theme. Activate to change.', status: 'Light mode on' },
		{ theme: 'dark', label: 'Appearance: dark theme. Activate to change.', status: 'Dark mode on' },
		{ theme: 'system', label: 'Appearance: system theme. Activate to change.', status: 'Following system' },
	] ) {
		await cycle.click();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', expected.theme );
		await expect( cycle ).toHaveAttribute( 'aria-label', expected.label );
		await expect( status ).toHaveText( expected.status );
	}
} );

test( 'A root theme change from another tab updates the pressed segment', async ( { page } ) => {
	await page.setViewportSize( { width: 1440, height: 900 } );
	await storeThemeBeforeLoad( page, 'system' );
	await page.goto( '/' );
	await page.waitForFunction( () => document.querySelector( '[data-theme-set="system"]' ).getAttribute( 'aria-pressed' ) === 'true' );

	await page.evaluate( () => document.documentElement.setAttribute( 'data-theme', 'light' ) );
	await expect( page.locator( '[data-theme-set="light"]' ) ).toHaveAttribute( 'aria-pressed', 'true' );
} );
