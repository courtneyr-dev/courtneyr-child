// `CR_SPEC=aria-hidden-sr.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`:
// cut-paper titles name their screen-reader copy with screen-reader-text, and titled Photo cards
// expose their photo with its alt (decision 2026-10-20). passes() is Accessibility Checker Pro's
// aria_hidden_valid_usage logic, copied from accessibility-checker/build/pageScanner.bundle.js.
// Read-only GETs, logged out. For a Pantheon environment set CR_BASE_URL, CR_ALLOW_REMOTE=1 and
// CR_BYPASS (the x-pantheon-bot-bypass token).
import { test, expect } from '@playwright/test';

if ( process.env.CR_BYPASS ) {
	test.use( { extraHTTPHeaders: { 'x-pantheon-bot-bypass': process.env.CR_BYPASS } } );
}

// The checker's logic is this file's own constant, installed as window.__acPasses on each page.
test.beforeEach( async ( { page } ) => {
	await page.addInitScript( { content: `window.__acPasses = ${ PASSES };` } );
} );

const PASSES = `( e ) => {
	const I = [ 'screen-reader-text', 'sr-only', 'show-for-sr', 'visuallyhidden', 'visually-hidden', 'hidden-visually', 'invisible', 'accessibly-hidden', 'hide', 'hidden' ];
	const t = getComputedStyle( e );
	if ( 'none' === t.display || 'hidden' === t.visibility ) return true;
	const role = e.getAttribute( 'role' );
	if ( role && role.split( /\\s+/ ).includes( 'presentation' ) ) return true;
	const n = e.parentElement;
	if ( ! n ) return false;
	const a = getComputedStyle( n );
	if ( 'none' === a.display || 'hidden' === a.visibility ) return true;
	if ( [ 'button', 'a' ].includes( n.tagName.toLowerCase() ) ) {
		if ( ( n.getAttribute( 'aria-label' ) || '' ).trim() || ( n.hasAttribute( 'aria-labelledby' ) && document.getElementById( n.getAttribute( 'aria-labelledby' ) ) ) ) return true;
		for ( const u of n.childNodes ) {
			if ( u === e ) continue;
			if ( 3 === u.nodeType && u.textContent.trim() ) return true;
			if ( 1 === u.nodeType && ! u.hasAttribute( 'aria-hidden' ) ) {
				const l = getComputedStyle( u );
				if ( 'none' !== l.display && 'hidden' !== l.visibility && u.textContent.trim() ) return true;
			}
		}
	}
	for ( const d of n.children ) {
		if ( d === e ) continue;
		for ( const m of I ) {
			if ( d.classList.contains( m ) || String( d.className ).toLowerCase().includes( m ) ) return true;
		}
	}
	return false;
}`;

test( 'cut-paper titles pass the checker and keep the exact title for screen readers', async ( { page } ) => {
	expect( ( await page.goto( '/kind/photo/', { waitUntil: 'domcontentloaded' } ) ).status() ).toBe( 200 );
	const titles = await page.locator( '.cr-cutout' ).evaluateAll( ( nodes ) => {
		const passes = window.__acPasses;
		return nodes.map( ( h ) => {
			const sr = h.querySelector( ':scope > .cr-cutout__text' );
			const tiles = h.querySelector( ':scope > .cr-cutout__tiles' );
			const letters = [ ...tiles.querySelectorAll( '.cr-cutout__tile' ) ].map( ( t ) => t.textContent ).join( '' );
			return { sr: sr?.className, srText: sr?.textContent.replace( /\s+/g, '' ), letters, passes: passes( tiles ) };
		} );
	} );
	expect( titles.length ).toBeGreaterThan( 0 );
	for ( const t of titles ) {
		expect( t.sr ).toContain( 'screen-reader-text' );
		expect( t.srText ).toBe( t.letters );
		expect( t.passes ).toBe( true );
	}
} );

test( 'titled photo frames expose the photo with its alt; hidden sleeves pass', async ( { page } ) => {
	await page.goto( '/kind/photo/', { waitUntil: 'domcontentloaded' } );
	const frames = await page.locator( '.cr-photo-frame__media' ).evaluateAll( ( nodes ) => {
		const passes = window.__acPasses;
		return nodes.map( ( m ) => {
			const img = m.querySelector( 'img.u-photo' );
			const sleeve = m.querySelector( '.cr-photo-frame__sleeve' );
			return {
				hidden: m.getAttribute( 'aria-hidden' ),
				mediaPasses: 'true' === m.getAttribute( 'aria-hidden' ) ? passes( m ) : true,
				alt: img ? img.getAttribute( 'alt' ) : null,
				sleevePasses: sleeve && 'true' === sleeve.getAttribute( 'aria-hidden' ) ? passes( sleeve ) : true,
			};
		} );
	} );
	expect( frames.length ).toBeGreaterThan( 0 );
	for ( const f of frames ) {
		if ( f.alt ) {
			expect( f.hidden ).not.toBe( 'true' );
		}
		expect( f.sleevePasses ).toBe( true );
	}
	expect( frames.filter( ( f ) => f.alt ).length ).toBeGreaterThan( 0 );
} );

test( 'titled Photo cards on the Stream carry their photo alt', async ( { page } ) => {
	await page.goto( '/stream/', { waitUntil: 'domcontentloaded' } );
	// Titled cards carry the kind label; untitled cards already used the alt as the link name.
	const alts = await page.locator( 'article.k-photo:has(.pk-kindlabel) .pk-media--stream img.u-photo' ).evaluateAll( ( imgs ) => imgs.map( ( i ) => i.getAttribute( 'alt' ) ) );
	test.skip( 0 === alts.length, 'no titled Photo card on /stream/ today' );
	// Every photo on the site has alt text (September 2026 remediation), so no titled card should print an empty one.
	expect( alts.filter( ( a ) => ! a || ! a.trim() ) ).toEqual( [] );
} );

test( 'gallery zine ornaments pass the checker', async ( { page } ) => {
	const res = await page.goto( '/2026/09/09/wild-turkeys-strutting-through-the-neighborhood/', { waitUntil: 'domcontentloaded' } );
	test.skip( 404 === res.status(), 'the gallery zine post is not on this environment' );
	const ornaments = await page.locator( '.cr-zine__num, .cr-zine__postmark, .cr-zine__imprint, .cr-zine-ephemera' ).evaluateAll( ( nodes ) => {
		const passes = window.__acPasses;
		return nodes.map( ( n ) => ( { cls: n.className, hidden: n.getAttribute( 'aria-hidden' ), passes: passes( n ) } ) );
	} );
	expect( ornaments.length ).toBeGreaterThan( 0 );
	for ( const o of ornaments ) {
		expect( o.hidden, o.cls ).toBe( 'true' );
		expect( o.passes, o.cls ).toBe( true );
	}
} );

// Third-party markup the theme does not emit: Complianz, Font Awesome, Simple Location, core's avatar and
// featured images (an <img> takes no role="presentation"), and Post Kinds (its own release).
const NOT_THEME = '.cmplz-icon, .cmplz-close, .svg-inline--fa, .sloc-location-icon, img, [class*="pkiw-"], [class^="leaflet"]';

for ( const path of [ '/', '/stream/', '/kind/photo/', '/kind/article/', '/2026/08/20/ted-lasso-season-4/', '/2026/09/15/gtm-onsight/', '/2026/07/06/american-obituary/' ] ) {
	test( `every theme aria-hidden ornament on ${ path } passes the checker`, async ( { page } ) => {
		const res = await page.goto( path, { waitUntil: 'load' } );
		test.skip( 404 === res.status(), `${ path } is not on this environment` );
		const failures = await page.evaluate( ( notTheme ) => {
			const passes = window.__acPasses;
			return [ ...document.querySelectorAll( '[aria-hidden="true"]' ) ]
				.filter( ( e ) => ! e.matches( notTheme ) && ! e.closest( '[class*="cmplz"], .wp-block-cover, .wp-block-spacer, .wp-block-separator' ) && ! passes( e ) )
				.map( ( e ) => e.tagName.toLowerCase() + '.' + String( e.className.baseVal ?? e.className ).split( ' ' )[ 0 ] );
		}, NOT_THEME );
		expect( failures ).toEqual( [] );
	} );
}

test( 'core lightbox buttons have a name before the lightbox opens', async ( { page } ) => {
	const res = await page.goto( '/2026/09/09/wild-turkeys-strutting-through-the-neighborhood/', { waitUntil: 'load' } );
	test.skip( 404 === res.status(), 'the gallery zine post is not on this environment' );
	// aria-labelledby: core binds aria-label to undefined until an image opens, which strips a static one.
	const names = await page.locator( '.wp-lightbox-close-button, .wp-lightbox-navigation-button' ).evaluateAll( ( buttons ) => buttons.map( ( b ) => document.getElementById( b.getAttribute( 'aria-labelledby' ) || '' )?.textContent.trim() ) );
	expect( names ).toEqual( [ 'Close', 'Previous', 'Next' ] );
} );

test( 'lightbox triggers carry a name in the server markup', async ( { request } ) => {
	const res = await request.get( '/2026/09/09/wild-turkeys-strutting-through-the-neighborhood/' );
	test.skip( 404 === res.status(), 'the gallery zine post is not on this environment' );
	// Before Perfmatters releases the Interactivity scripts, the server label is the only name.
	const triggers = ( await res.text() ).match( /<button[^>]*class="lightbox-trigger"[^>]*>/g ) || [];
	expect( triggers.length ).toBeGreaterThan( 0 );
	for ( const t of triggers ) {
		expect( t ).toMatch( /aria-label="Enlarge"/ );
	}
} );
