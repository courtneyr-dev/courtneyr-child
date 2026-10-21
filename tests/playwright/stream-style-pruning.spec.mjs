// `CR_SPEC=stream-style-pruning.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`:
// inc/stream-style-pruning.php against a running site. The front page and /stream/ load a kind
// sheet only when a card of that kind rendered; kind archives keep their own sheet. Read-only
// GETs, logged out. For a Pantheon environment set CR_BASE_URL, CR_ALLOW_REMOTE=1 and
// CR_BYPASS (the x-pantheon-bot-bypass token).
import { test, expect } from '@playwright/test';

if ( process.env.CR_BYPASS ) {
	test.use( { extraHTTPHeaders: { 'x-pantheon-bot-bypass': process.env.CR_BYPASS } } );
}

async function sheets( page, path ) {
	const response = await page.goto( path, { waitUntil: 'domcontentloaded' } );
	expect( response.status() ).toBe( 200 );
	const linked = await page.locator( 'link[rel="stylesheet"]' ).evaluateAll( ( links ) =>
		links.map( ( link ) => ( link.getAttribute( 'href' ) || '' ).split( '?' )[ 0 ].split( '/' ).pop() )
	);
	// Since 0.7.129 Perfmatters inlines the kind sheets as <style id="courtneyr-photo-css">;
	// count those as cr-photo.css so the loaded/skipped checks still hold.
	const inlined = await page.locator( 'style[id^="courtneyr-"][id$="-css"]' ).evaluateAll( ( styles ) =>
		styles.map( ( style ) => style.id.replace( /^courtneyr-/, 'cr-' ).replace( /-css$/, '.css' ) )
	);
	return [ ...linked, ...inlined ];
}

for ( const path of [ '/', '/stream/' ] ) {
	test( `${ path } skips kind sheets no card uses`, async ( { page } ) => {
		const loaded = await sheets( page, path );
		// The site has no wish or recipe posts (2026-10-19), so no card can use these.
		expect( loaded ).not.toContain( 'cr-wish.css' );
		expect( loaded ).not.toContain( 'cr-recipe.css' );
		// Shared sheets always load.
		expect( loaded ).toContain( 'cr-post-kinds.css' );
	} );

	test( `${ path } keeps the photo sheet when a photo card renders`, async ( { page } ) => {
		const loaded = await sheets( page, path );
		const photoCards = await page.locator( '.k-photo, .kind-photo, [class*="cr-photo"]' ).count();
		test.skip( 0 === photoCards, 'no photo card on this page today' );
		expect( loaded ).toContain( 'cr-photo.css' );
	} );
}

test( 'a kind archive keeps its own sheet', async ( { page } ) => {
	expect( await sheets( page, '/kind/comics/' ) ).toContain( 'cr-comic.css' );
} );

test( 'the front page loads the card sheet without blocking; /stream/ keeps it blocking', async ( { request } ) => {
	const front = await ( await request.get( '/' ) ).text();
	// Starts after the window load event (data-cr-href), not with the HTML.
	expect( front ).toMatch( /<link[^>]*id=['"]courtneyr-home-sections-css['"][^>]*data-cr-href=/ );
	expect( front ).toMatch( /<link[^>]*id=['"]courtneyr-post-kinds-css['"][^>]*data-cr-href=/ );
	expect( front ).toContain( 'id="cr-deferred-sheets"' );
	expect( front ).toMatch( /<noscript><link[^>]*id=['"]courtneyr-post-kinds-css['"]/ );
	const stream = await ( await request.get( '/stream/' ) ).text();
	expect( stream ).toMatch( /id=['"]courtneyr-post-kinds-css['"][^>]*media=['"]all['"]/ );
} );

test( 'Perfmatters delays the full sheets on the front page only', async ( { request } ) => {
	const front = await ( await request.get( '/' ) ).text();
	test.skip( ! front.includes( 'id="perfmatters-used-css"' ), 'Perfmatters used CSS is not active here' );
	// Front page: held until interaction (its used CSS is built from this page alone).
	expect( front ).toMatch( /<link[^>]*data-pmdelayedstyle=/ );
	// A single post shares post.used.css with every other post, so its full sheets
	// must still arrive on their own (async), never wait for interaction.
	const single = await ( await request.get( '/2026/07/06/american-obituary/' ) ).text();
	expect( single ).not.toMatch( /data-pmdelayedstyle=/ );
	expect( single ).toMatch( /<link[^>]*media=['"]print['"][^>]*onload=/ );
} );
