// `CR_SPEC=cover-fallback.spec.mjs npm run captures -- --project=1280-light`: the
// failed-cover hook in assets/js/site-ux.js, run on a blank page with the script
// inlined, so it needs no site. Covers come from a routed host that answers 200 with
// a 1x1 PNG or 404.
import fs from 'node:fs';
import { test, expect } from '@playwright/test';

const SCRIPT = fs.readFileSync( new URL( '../../assets/js/site-ux.js', import.meta.url ), 'utf8' );
const PNG = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64' );
const OK = 'http://covers.test/ok.png';
const MISSING = 'http://covers.test/missing.jpg';

test.beforeEach( async ( { page } ) => {
	await page.route( 'http://covers.test/**', ( route ) =>
		route.request().url() === OK
			? route.fulfill( { status: 200, contentType: 'image/png', body: PNG } )
			: route.fulfill( { status: 404, contentType: 'text/plain', body: 'missing' } )
	);
} );

// The page's markup, then wait until every image has loaded or failed.
async function load( page, html ) {
	await page.setContent( `<!doctype html><html><body>${ html }</body></html>`, { waitUntil: 'load' } );
	await page.waitForFunction( () => [ ...document.images ].every( ( img ) => img.complete ) );
}

const markup = ( src, wrap = ( img ) => img ) => `
	<div class="cr-test-cover" data-cr-cover-fallback>
		${ wrap( `<img src="${ src }" alt="">` ) }
		<span class="cr-test-glyph" aria-hidden="true">G</span>
	</div>`;

// W1 contract (wave-lanes.md): a container with data-cr-cover-fallback gets
// cr-cover--failed and loses its img when the image fails.
test( 'a cover that fails after the script runs marks its container and leaves (error listener)', async ( { page } ) => {
	await load( page, '<main></main>' );
	await page.addScriptTag( { content: SCRIPT } );
	await page.evaluate( ( html ) => document.querySelector( 'main' ).insertAdjacentHTML( 'beforeend', html ), markup( MISSING ) );
	const cover = page.locator( '.cr-test-cover' );
	await expect( cover ).toHaveClass( /\bcr-cover--failed\b/ );
	await expect( cover.locator( 'img' ) ).toHaveCount( 0 );
	await expect( cover.locator( '.cr-test-glyph' ), 'the container keeps its other children' ).toHaveCount( 1 );
} );

test( 'a cover that failed before the script ran marks its container and leaves (complete check)', async ( { page } ) => {
	await load( page, markup( MISSING ) );
	expect( await page.locator( '.cr-test-cover img' ).evaluate( ( img ) => img.naturalWidth ), 'the cover failed before the script' ).toBe( 0 );
	await page.addScriptTag( { content: SCRIPT } );
	const cover = page.locator( '.cr-test-cover' );
	await expect( cover ).toHaveClass( /\bcr-cover--failed\b/ );
	await expect( cover.locator( 'img' ) ).toHaveCount( 0 );
} );

// Dev serves images inside <picture> with <source> siblings; the picture goes
// with its img, so no empty <picture> stays behind.
test( 'a failed cover inside a picture element takes the picture with it', async ( { page } ) => {
	await load( page, markup( MISSING, ( img ) => `<picture><source type="image/webp" srcset="${ MISSING }">${ img }</picture>` ) );
	await page.addScriptTag( { content: SCRIPT } );
	const cover = page.locator( '.cr-test-cover' );
	await expect( cover ).toHaveClass( /\bcr-cover--failed\b/ );
	await expect( cover.locator( 'picture, img' ) ).toHaveCount( 0 );
} );

test( 'a cover that loads keeps its image and its container stays unmarked', async ( { page } ) => {
	await load( page, markup( OK ) );
	await page.addScriptTag( { content: SCRIPT } );
	await page.evaluate( ( html ) => document.body.insertAdjacentHTML( 'beforeend', html ), markup( OK ).replace( 'cr-test-cover', 'cr-test-cover cr-test-late' ) );
	await page.waitForFunction( () => [ ...document.images ].every( ( img ) => img.complete && img.naturalWidth > 0 ) );
	await expect( page.locator( '.cr-test-cover img' ) ).toHaveCount( 2 );
	await expect( page.locator( '.cr-cover--failed' ) ).toHaveCount( 0 );
} );

test( 'a failed image outside any marked container is left alone', async ( { page } ) => {
	await load( page, `<figure class="cr-test-plain"><img src="${ MISSING }" alt=""></figure>` );
	await page.addScriptTag( { content: SCRIPT } );
	await page.evaluate( ( src ) => document.body.insertAdjacentHTML( 'beforeend', `<p class="cr-test-late"><img src="${ src }" alt=""></p>` ), MISSING );
	await page.waitForFunction( () => [ ...document.images ].every( ( img ) => img.complete ) );
	await expect( page.locator( 'img' ) ).toHaveCount( 2 );
	await expect( page.locator( '.cr-cover--failed' ) ).toHaveCount( 0 );
} );

// The shipped branches keep their behavior: the watch shelf drops its media
// box, the comic keeps its bag and is marked cr-comic--no-art.
test( 'the watch shelf and comic fallbacks are unchanged', async ( { page } ) => {
	await load( page, `
		<div class="cr-vhs-shelf"><article class="pk-card"><div class="pk-media"><img src="${ MISSING }" alt=""></div><h3 class="pk-title">Tape</h3></article></div>
		<article class="pk-card cr-comic"><div class="pk-media"><img src="${ MISSING }" alt=""></div><h3 class="pk-title">Comic</h3></article>` );
	await page.addScriptTag( { content: SCRIPT } );
	await expect( page.locator( '.cr-vhs-shelf .pk-media' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cr-vhs-shelf .pk-title' ) ).toHaveCount( 1 );
	await expect( page.locator( '.pk-card.cr-comic' ) ).toHaveClass( /\bcr-comic--no-art\b/ );
	await expect( page.locator( '.pk-card.cr-comic .pk-media' ) ).toHaveCount( 1 );
	await expect( page.locator( '.pk-card.cr-comic img' ) ).toHaveCount( 0 );
	await expect( page.locator( '.cr-cover--failed' ) ).toHaveCount( 0 );
} );
