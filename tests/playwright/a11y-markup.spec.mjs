// `npm run test:a11y-markup`: DOM assertions for the output-level accessibility fixes in
// inc/a11y-output.php and patterns/cr-hcard.php. Read-only GETs; needs a running site
// (CR_BASE_URL, CR_ALLOW_REMOTE=1 and CR_USER_AGENT for a Pantheon sandbox). Post paths
// default to dev fixtures and can be pointed elsewhere with the CR_*_PATH variables.
import { test, expect } from '@playwright/test';

const ABLEPLAYER_POST = process.env.CR_ABLEPLAYER_POST_PATH || '/?p=3010'; // Able Player YouTube shortcode
const ABLEPLAYER_URL_POST = process.env.CR_ABLEPLAYER_URL_POST_PATH || '/?p=1053'; // youtube-id written as a full watch URL
const COMMENTS_POST = process.env.CR_COMMENTS_POST_PATH || '/?p=38071'; // backfeed comments, incl. two with no avatar
const QUOTE_POST = process.env.CR_QUOTE_POST_PATH || '/?p=38049'; // Syndication Links plugin output
const BROWSE_PAGE = process.env.CR_BROWSE_ALL_PATH || '/stream/'; // cr-browse-all pattern (post_format list)
const TABLE_POST = process.env.CR_TABLE_POST_PATH || '/?p=551'; // legacy comparison table with headings and links in <th>
const TERM_ARCHIVE = process.env.CR_TERM_ARCHIVE_PATH || '/type/aside/'; // term archive with a description
const AUTOEMBED_CAPTIONED_POST = process.env.CR_AUTOEMBED_CAPTIONED_POST_PATH || '/?p=3010'; // bare YouTube URL whose video has a registered VTT (_cr_youtube_id)
// Dark-mode contrast fixtures: [ path, selector, text the element must contain ].
const DARK_FIXTURES = [
	[ process.env.CR_RESUME_PAGE_PATH || '/?page_id=37840', 'code', 'beta-rc' ], // inline code inside page content
	[ process.env.CR_ALERT_POST_PATH || '/?p=37492', 'p.is-style-sme-alert-success', 'Disclosure' ], // SME success alert
	[ process.env.CR_MARK_POST_PATH || '/?p=50', 'mark', 'love the Lord' ], // highlighted verse
	[ process.env.CR_MARK_POST2_PATH || '/?p=751', 'mark', 'FREE' ], // highlighted word
];

test( 'footer h-card photo is a decorative image inside the named link', async ( { page } ) => {
	await page.goto( '/', { waitUntil: 'load' } );
	const link = page.locator( '.cr-hcard a.u-url' );
	await expect( link ).toHaveCount( 1 );
	const img = link.locator( 'img.cr-hcard__photo' );
	await expect( img ).toHaveCount( 1 );
	await expect( img ).toHaveAttribute( 'alt', '' );
	expect( await img.getAttribute( 'aria-hidden' ) ).toBeNull();
	expect( ( await link.textContent() )?.trim().length ).toBeGreaterThan( 2 );
	expect( await page.locator( '.cr-hcard img[aria-hidden]' ).count() ).toBe( 0 );
} );

test( 'Able Player YouTube players carry the captions/transcript link', async ( { page } ) => {
	await page.goto( ABLEPLAYER_POST, { waitUntil: 'load' } );
	// Able Player swaps its <video data-youtube-id> for an iframe at init; count either form.
	const players = page.locator( '[data-youtube-id], iframe[id^="able_player_"][id$="_youtube"]' );
	const count = await players.count();
	expect( count, 'fixture post renders at least one Able Player YouTube player' ).toBeGreaterThan( 0 );
	const links = page.locator( '.cr-media__transcript a[href*="youtube.com/watch?v="]' );
	expect( await links.count() ).toBeGreaterThanOrEqual( count );
	for ( const text of await links.allTextContents() ) {
		expect( text.toLowerCase() ).toContain( 'transcript' );
	}
} );

test( 'Able Player shortcodes whose youtube-id is a full URL still get the transcript link', async ( { page } ) => {
	await page.goto( ABLEPLAYER_URL_POST, { waitUntil: 'load' } );
	const players = page.locator( '[data-youtube-id], iframe[id^="able_player_"][id$="_youtube"]' );
	const count = await players.count();
	expect( count, 'fixture post renders at least one Able Player YouTube player' ).toBeGreaterThan( 0 );
	// Every player gets a figcaption that names the transcript: a YouTube link when the
	// shortcode has no captions track, a sentence pointing at Able Player's Transcript
	// control when it has one (these fixture posts do).
	const captions = page.locator( 'figure.cr-media--ableplayer figcaption.cr-media__transcript' );
	expect( await captions.count() ).toBeGreaterThanOrEqual( count );
	for ( const text of await captions.allTextContents() ) {
		expect( text.toLowerCase() ).toContain( 'transcript' );
	}
	const links = captions.locator( 'a' );
	for ( const href of await links.evaluateAll( ( a ) => a.map( ( el ) => el.getAttribute( 'href' ) ) ) ) {
		// An eleven-character id, never the pasted URL nested inside another URL.
		expect( href ).toMatch( /^https:\/\/www\.youtube\.com\/watch\?v=[A-Za-z0-9_-]{11}$/ );
	}
} );

test( 'comment avatars are decorative and never render with an empty src', async ( { page } ) => {
	await page.goto( COMMENTS_POST, { waitUntil: 'load' } );
	const avatars = page.locator( '.wp-block-avatar img' );
	expect( await avatars.count() ).toBeGreaterThan( 0 );
	for ( const img of await avatars.all() ) {
		expect( ( await img.getAttribute( 'src' ) ) || ( await img.getAttribute( 'data-src' ) ) ).not.toBe( '' );
		await expect( img ).toHaveAttribute( 'alt', '' );
		await expect( img ).toHaveAttribute( 'aria-hidden', 'true' );
	}
	// A comment whose avatar resolves to nothing renders no <img> at all.
	expect( await page.locator( '.wp-block-avatar img[src=""]' ).count() ).toBe( 0 );
} );

test( 'syndication links are not smaller than 11px', async ( { page } ) => {
	await page.goto( QUOTE_POST, { waitUntil: 'load' } );
	const links = page.locator( '.syndication-links .syn-link' );
	expect( await links.count() ).toBeGreaterThan( 0 );
	for ( const a of await links.all() ) {
		const size = parseFloat( await a.evaluate( ( el ) => getComputedStyle( el ).fontSize ) );
		expect( size ).toBeGreaterThanOrEqual( 11 );
	}
} );

test( 'post-format list links name what they link to', async ( { page } ) => {
	await page.goto( BROWSE_PAGE, { waitUntil: 'load' } );
	const items = page.locator( '.cr-browse-all__list li.cat-item a[href*="/type/"]' );
	expect( await items.count() ).toBeGreaterThan( 0 );
	for ( const a of await items.all() ) {
		const name = ( await a.textContent() )?.replace( /\s+/g, ' ' ).trim() || '';
		expect( name.toLowerCase() ).toMatch( /\bposts$/ );
		expect( name.toLowerCase() ).not.toBe( 'link' );
	}
} );

test( 'headings and links inside table header cells take the cell colour', async ( { page } ) => {
	await page.goto( TABLE_POST, { waitUntil: 'load' } );
	// Links transition their colour when the component sheet applies after the token sheet;
	// read the settled values, not a frame from the 200 ms transition.
	await page.evaluate( () => Promise.all( document.getAnimations().map( ( a ) => a.finished ) ) );
	await page.waitForTimeout( 400 );
	const cells = page.locator( '.wp-block-post-content th, .entry-content th' );
	expect( await cells.count() ).toBeGreaterThan( 0 );
	for ( const th of await cells.all() ) {
		const thColor = await th.evaluate( ( el ) => getComputedStyle( el ).color );
		for ( const inner of await th.locator( 'h1, h2, h3, h4, h5, h6, a' ).all() ) {
			expect( await inner.evaluate( ( el ) => getComputedStyle( el ).color ) ).toBe( thColor );
		}
	}
} );

test( 'term archive description stays below heading size', async ( { page } ) => {
	// Accessibility Checker's possible_heading flags a <p> of 50 characters or fewer at 20px or more.
	await page.goto( TERM_ARCHIVE, { waitUntil: 'load' } );
	const lede = page.locator( '.cr-archive__description p' );
	expect( await lede.count() ).toBeGreaterThan( 0 );
	for ( const p of await lede.all() ) {
		const size = parseFloat( await p.evaluate( ( el ) => getComputedStyle( el ).fontSize ) );
		expect( size ).toBeLessThan( 20 );
		expect( size ).toBeGreaterThanOrEqual( 16 );
	}
} );

test( 'dark mode keeps AA contrast on inline code, the success alert and highlights', async ( { page } ) => {
	// The theme toggle reads localStorage before paint; force Dark regardless of the project's colour scheme.
	await page.addInitScript( () => { try { localStorage.setItem( 'courtneyr-theme', 'dark' ); } catch ( e ) {} } );
	for ( const [ path, selector, text ] of DARK_FIXTURES ) {
		await page.goto( path, { waitUntil: 'load' } );
		await page.evaluate( () => Promise.all( document.getAnimations().map( ( a ) => a.finished ) ) );
		const result = await page.evaluate( ( [ sel, needle ] ) => {
			const el = [ ...document.querySelectorAll( sel ) ].find( ( e ) => e.textContent.includes( needle ) );
			if ( ! el ) { return { missing: true }; }
			const channels = ( c ) => c.match( /\d+(\.\d+)?/g ).slice( 0, 3 ).map( Number );
			const lum = ( c ) => { const [ r, g, b ] = channels( c ).map( ( v ) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ); } ); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
			let node = el, bg = null;
			while ( node && node !== document.documentElement ) { const c = getComputedStyle( node ).backgroundColor; if ( c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent' ) { bg = c; break; } node = node.parentElement; }
			if ( ! bg ) { return { missing: false, noBackground: true }; }
			const l1 = lum( getComputedStyle( el ).color ), l2 = lum( bg );
			return { theme: document.documentElement.getAttribute( 'data-theme' ), ratio: ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 ) };
		}, [ selector, text ] );
		expect( result.missing, `${ selector } on ${ path }` ).toBeFalsy();
		expect( result.noBackground, `${ selector } on ${ path }` ).toBeFalsy();
		expect( result.theme ).toBe( 'dark' );
		expect( result.ratio, `${ selector } on ${ path }` ).toBeGreaterThanOrEqual( 4.5 );
	}
} );

test( 'a YouTube autoembed picks up the site caption file registered for its video', async ( { page } ) => {
	await page.goto( AUTOEMBED_CAPTIONED_POST, { waitUntil: 'load' } );
	const player = page.locator( 'figure.cr-media--ableplayer video[data-youtube-id]' ).first();
	await expect( player ).toHaveCount( 1 );
	const track = player.locator( 'track[kind="captions"]' );
	await expect( track ).toHaveCount( 1 );
	expect( await track.getAttribute( 'src' ) ).toMatch( /\.vtt$/ );
} );
