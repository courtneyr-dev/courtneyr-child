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
	return page.locator( 'link[rel="stylesheet"]' ).evaluateAll( ( links ) =>
		links.map( ( link ) => ( link.getAttribute( 'href' ) || '' ).split( '?' )[ 0 ].split( '/' ).pop() )
	);
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
	expect( front ).toMatch( /id=['"]courtneyr-home-sections-css['"][^>]*media=['"]print['"][^>]*onload=/ );
	expect( front ).toMatch( /id=['"]courtneyr-post-kinds-css['"][^>]*media=['"]print['"][^>]*onload=/ );
	expect( front ).toMatch( /<noscript><link[^>]*id=['"]courtneyr-post-kinds-css['"]/ );
	const stream = await ( await request.get( '/stream/' ) ).text();
	expect( stream ).toMatch( /id=['"]courtneyr-post-kinds-css['"][^>]*media=['"]all['"]/ );
} );
