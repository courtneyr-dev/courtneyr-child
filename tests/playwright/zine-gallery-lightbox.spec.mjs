// `CR_ZINE_LIGHTBOX_PATH=/some-gallery-single/ CR_SPEC=zine-gallery-lightbox.spec.mjs npm run captures -- --project=1280-light`:
// a gallery-format single whose first gallery carries is-style-lightbox-slideshow.
import { test, expect } from '@playwright/test';

const PATH = process.env.CR_ZINE_LIGHTBOX_PATH || '';

test.skip( ! PATH, 'Set CR_ZINE_LIGHTBOX_PATH to a gallery-format single with is-style-lightbox-slideshow.' );

test( 'the zine keeps the gallery style class and the module builds one named trigger per panel', async ( { page } ) => {
	await page.goto( PATH );
	const wrapper = page.locator( '.cr-zine .wp-block-gallery.is-style-lightbox-slideshow' );
	const panels = page.locator( '.cr-zine__frame > .wp-block-image' );
	const triggers = wrapper.locator( '.pfbt-lightbox-trigger' );
	await expect( wrapper ).toHaveCount( 1 );
	const panelCount = await panels.count();
	expect( panelCount ).toBeGreaterThan( 0 );
	await expect( triggers ).toHaveCount( panelCount, { timeout: 15_000 } );
	for ( let index = 0; index < panelCount; index++ ) {
		await expect( triggers.nth( index ) ).toHaveAttribute( 'aria-label', /^Open .+ in lightbox$/ );
	}
	await expect( page.locator( '.cr-zine .lightbox-trigger' ) ).toHaveCount( 0 );
} );

test( 'Enter opens a modal dialog that fills the viewport; Escape closes it and focus returns to the trigger', async ( { page } ) => {
	await page.goto( PATH );
	const first = page.locator( '.cr-zine .pfbt-lightbox-trigger' ).first();
	await expect( first ).toBeVisible( { timeout: 15_000 } );
	await first.focus();
	await page.keyboard.press( 'Enter' );

	const dialog = page.locator( 'dialog.pfbt-lightbox-dialog' );
	await expect( dialog ).toBeVisible();
	expect( await dialog.evaluate( ( el ) => el.matches( ':modal' ) ) ).toBe( true );
	expect( await dialog.evaluate( ( el ) => el.contains( document.activeElement ) ) ).toBe( true );

	const box = await dialog.boundingBox();
	const viewport = page.viewportSize();
	const inner = await page.evaluate( () => ( { width: window.innerWidth, height: window.innerHeight } ) );
	expect( box ).not.toBeNull();
	expect( viewport ).not.toBeNull();
	expect( Math.abs( inner.width - viewport.width ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( inner.height - viewport.height ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( box.x ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( box.y ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( box.width - inner.width ) ).toBeLessThanOrEqual( 1 );
	expect( Math.abs( box.height - inner.height ) ).toBeLessThanOrEqual( 1 );

	const counter = dialog.locator( '.pfbt-lightbox-dialog__counter' );
	await expect( counter ).toHaveText( /^Image 1 of \d+$/ );
	const count = Number( ( await counter.textContent() )?.match( /^Image 1 of (\d+)$/ )?.[1] || 0 );
	expect( count ).toBeGreaterThan( 0 );
	if ( count > 1 ) {
		await page.keyboard.press( 'ArrowRight' );
		await expect( counter ).toHaveText( `Image 2 of ${ count }` );
	}

	await page.keyboard.press( 'Escape' );
	await expect( dialog ).toBeHidden();
	await expect( first ).toBeFocused();
} );
