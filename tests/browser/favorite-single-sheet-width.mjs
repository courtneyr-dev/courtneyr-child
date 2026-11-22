// `npm run test:favorite-single-width`: renders the Favorite single's real wrapper chain
// (templates/single.html: main > entry-content > the plugin's .pkiw-singular-entry > the sheet)
// in headless Chrome with the layout CSS WordPress printed for a served single (fixtures/
// wp-generated-layout.css, extracted verbatim, nothing added or removed) and the theme's checked-in
// sheets, and asserts the sheet's width against fixed values from the approved contract: the sheet is
// the page's dominant object, at most the site's wide size (1100px), and fills the width on narrower
// screens. Sheet edges equal the Post navigation's edges (also an .alignwide child of main).
//
// What else it asserts:
//   - 29 widths from 320 to 1920 (1024-1100 and the 1131/1132 cap boundary included), with overlay
//     scrollbars and a forced 15px classic scrollbar: client width, no horizontal overflow, equal
//     left and right gutters, right edge inside the client width, record left and mount right from 64rem.
//   - the star and the tape never intersect the H1.
//   - every other block in the post (paragraph, wide group, full group, left and right floats, a group with
//     an authored margin, an Evidence group, image, quote, list, embed, and a Group that holds the sheet and
//     paragraphs) keeps the geometry origin/main gave it, in light and dark, with and without the classic
//     scrollbar: left edge, width, and the top edge (before the sheet) or the gap under it (after the sheet)
//     equal favorite-single-sheet-width.base.json. So do the sheet's top edge and the gap from the sheet to
//     Post navigation. The rendered markup is origin/main's; this fix is CSS only.
//   - other kinds' singles, a protected favorite, and a .cr-fav-sheet outside a Favorite single keep 720px.
//
// Offline: a loopback server serves the sheets and fixtures; everything else is blocked. Needs
// Chrome: CR_CHROME=/path/to/chrome, or Google Chrome / Chromium in a standard location. Without
// one the test prints a SKIP banner and exits 0, unless CR_REQUIRE_BROWSER=1. Not wired into CI.
// CR_FAVORITE_CSS=/path/to/cr-favorite.css renders that sheet instead of the checked-in one (mutation
// runs). `--record` with CR_FAVORITE_CSS=<origin/main cr-favorite.css> prints the origin/main baseline.
import { createServer } from 'node:http';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const HERE = dirname( fileURLToPath( import.meta.url ) );
const ROOT = resolve( HERE, '../..' );
const SHEETS = [ 'tokens', 'components', 'cr-blog', 'cr-paper', 'cr-sticker', 'cr-favorite', 'cr-post-kinds', 'cr-nav', 'cr-evidence-labels' ];
const SCROLLBAR = 15;
const RECORD = process.argv.includes( '--record' );
const KEY_WIDTHS = [ 1672, 1280, 1024, 768, 390, 320 ];
const DENSE_WIDTHS = [ 320, 340, 360, 375, 390, 414, 480, 600, 768, 800, 900, 1000, 1024, 1040, 1050, 1060, 1080, 1100, 1120, 1131, 1132, 1140, 1150, 1200, 1280, 1366, 1448, 1672, 1920 ];
const SIBLING_WIDTHS = [ 1672, 1448, 1280, 1024, 768, 390, 320 ];

// Fixed expectations. Main keeps 1rem gutters (32px). The theme's full-bleed rule gives `main.alignfull`
// the viewport width even beside a classic scrollbar (components.css), so the parent is width - 32 in
// both scrollbar cases; this fix adds no viewport unit of its own. Favorite sheet: min( 1100, parent ).
// Everything else keeps min( 720, parent ).
const FAVORITE = { 1672: 1100, 1280: 1100, 1024: 992, 768: 736, 390: 358, 320: 288 };
const NARROW = { 1672: 720, 1280: 720, 1024: 720, 768: 720, 390: 358, 320: 288 };
const favoriteWidth = ( width ) => Math.min( 1100, width - 32 );
const narrowWidth = ( width ) => Math.min( 720, width - 32 );
// Record and mount columns (3fr / 2fr) inside sheet padding 24px + gap 24px, from 64rem.
const COLUMNS = { 1100: [ 617, 411 ], 992: [ 552, 368 ] };
const BASELINE = RECORD ? {} : JSON.parse( readFileSync( resolve( HERE, 'favorite-single-sheet-width.base.json' ), 'utf8' ) );

const WP_LAYOUT_CSS = readFileSync( resolve( HERE, 'fixtures/wp-generated-layout.css' ), 'utf8' );

const IMAGE = 'data:image/svg+xml;utf8,' + encodeURIComponent( '<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="640"><rect width="1024" height="640" fill="#9bb"/></svg>' );
const STAR = '<span role="presentation" class="cr-fav-sheet__star" aria-hidden="true"><svg role="presentation" class="cr-fav-star__shape" viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 2l3 6.5 7 .6-5.3 4.6 1.6 6.8L12 17l-6.9 3.5 1.6-6.8L1.4 9.1l7-.6z"></path></svg></span>';
const HEAD = STAR + '<p class="cr-fav-sheet__tape"><span class="pk-kindlabel">Favorite</span><span role="presentation" class="cr-fav-sheet__marker" aria-hidden="true"></span></p><h1 class="cr-fav-sheet__title p-name">Field Guide to Quiet Software</h1>';
const RECORD_HTML = '<article class="cr-fav-sheet__record h-cite u-favorite-of"><dl class="cr-fav-rows"><div class="cr-fav-row cr-fav-row--author"><dt>By</dt><dd><span class="p-author h-card"><span class="p-name">Rowan Vale</span></span></dd></div><div class="cr-fav-row cr-fav-row--source"><dt>Source</dt><dd><a class="u-url cr-fav-link" href="/never-visited" rel="noopener noreferrer nofollow">notes.example.com</a></dd></div><div class="cr-fav-row cr-fav-row--favorited"><dt>Favorited</dt><dd><time class="dt-published" datetime="2026-08-14">August 14, 2026</time></dd></div></dl><p class="cr-fav-sheet__description p-content">Three yes-or-no questions that decide whether a tool earns a place on the desk.</p></article>';
const MOUNT = `<div class="cr-fav-sheet__mount" data-cr-cover-fallback=""><img width="1024" height="640" src="${ IMAGE }" class="cr-fav-sheet__img skip-lazy" alt="A flowchart"></div>`;
const FAILED = `<div class="cr-fav-sheet__mount cr-cover--failed" data-cr-cover-fallback=""><img width="1024" height="640" src="${ IMAGE }" class="cr-fav-sheet__img skip-lazy" alt="A flowchart"></div>`;
const sheet = ( mount ) => `<div class="cr-fav-sheet">${ HEAD }<div class="cr-fav-sheet__body">${ RECORD_HTML }${ mount }</div></div>`;
const HIDDEN = '<a class="u-url" href="/x" hidden=""></a><time class="dt-published" datetime="2026-08-28T10:00:00-04:00" hidden=""></time>';
const OTHER = '<p class="prose">A short note in this kind of post.</p>';
const NAV = '<nav class="wp-block-group alignwide cr-post-nav is-layout-flow wp-block-group-is-layout-flow" aria-label="Post navigation"><div class="cr-post-nav__link"><a href="/a">Previous</a></div><div class="cr-post-nav__link"><a href="/b">Next</a></div></nav>';

const SIBLINGS = {
	'sib-paragraph': '<p class="sib">Why this one stayed with me, in a sentence that wraps onto a second line when the column is narrow enough.</p>',
	'sib-wide': '<div class="wp-block-group alignwide sib" style="block-size:20px">Wide group</div>',
	'sib-full': '<div class="wp-block-group alignfull sib" style="block-size:20px">Full group</div>',
	'sib-left': '<figure class="wp-block-image alignleft sib" style="inline-size:100px;block-size:50px;margin:0 2em 0 0">Left</figure>',
	'sib-right': '<figure class="wp-block-image alignright sib" style="inline-size:100px;block-size:50px;margin:0 0 0 2em">Right</figure>',
	'sib-margin': '<div class="wp-block-group sib" style="margin-right:32px;block-size:20px">Authored margin</div>',
	'sib-evidence': '<div class="wp-block-group alignwide cr-evidence sib"><div class="cr-evidence__row">Evidence row</div></div>',
	'sib-image': `<figure class="wp-block-image sib"><img src="${ IMAGE }" width="1024" height="640" alt=""></figure>`,
	'sib-quote': '<blockquote class="wp-block-quote sib"><p>A quoted line that is long enough to wrap in a narrow column.</p></blockquote>',
	'sib-list': '<ul class="wp-block-list sib"><li>One item</li><li>Another item that wraps when the column is narrow</li></ul>',
	'sib-embed': '<figure class="wp-block-embed is-type-video sib"><div class="wp-block-embed__wrapper" style="block-size:120px">Embed</div></figure>',
};
const NESTED = `<div class="wp-block-group sib"><p class="inner-a">Before the card, inside the group.</p>${ sheet( MOUNT ) }<p class="inner-b">After the card, inside the group.</p></div>`;

// kind: body class. favorite: prints the sheet in a Favorite single. narrow: keeps the 720px content size.
const FIXTURES = {
	rich: { kind: 'favorite', inner: sheet( MOUNT ), favorite: true, mount: true },
	nomount: { kind: 'favorite', inner: `<div class="cr-fav-sheet">${ HEAD }<div class="cr-fav-sheet__body">${ RECORD_HTML }</div></div>`, favorite: true },
	failed: { kind: 'favorite', inner: sheet( FAILED ), favorite: true },
	protected: { kind: 'favorite', inner: '<h1 class="single-post__title">Protected</h1><form class="post-password-form"><label>Password <input type="password"></label></form>' },
	scope: { kind: 'like', inner: sheet( MOUNT ), scope: true },
	like: { kind: 'like', inner: OTHER },
	repost: { kind: 'repost', inner: OTHER },
	jam: { kind: 'jam', inner: OTHER },
	checkin: { kind: 'checkin', inner: OTHER },
	read: { kind: 'read', inner: OTHER },
	comic: { kind: 'comic', inner: OTHER },
	quote: { kind: 'quote', inner: OTHER },
	...Object.fromEntries( Object.entries( SIBLINGS ).map( ( [ key, html ] ) => [ key, { kind: 'favorite', inner: html + sheet( MOUNT ) + html.replace( 'class="', 'class="after ' ) + '<div style="clear:both"></div>', favorite: true, sibling: true, evidence: key === 'sib-evidence' } ] ) ),
	'sib-nested': { kind: 'favorite', inner: NESTED, favorite: true, sibling: true, nested: true },
};
const FIXTURE_KEYS = RECORD ? Object.keys( FIXTURES ).filter( ( key ) => FIXTURES[ key ].sibling || key === 'rich' ) : Object.keys( FIXTURES );

const chromePath = () => {
	const candidates = [ process.env.CR_CHROME, '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser' ].filter( Boolean );
	const found = candidates.find( ( path ) => existsSync( path ) );
	if ( found ) {
		return found;
	}
	try {
		const bundled = chromium.executablePath();
		return existsSync( bundled ) ? bundled : null;
	} catch {
		return null;
	}
};
const executablePath = chromePath();
if ( ! executablePath ) {
	const banner = '*'.repeat( 72 );
	console.log( `${ banner }\nSKIP favorite-single-sheet-width: no Chrome found. Set CR_CHROME or install Google Chrome.\nNothing was rendered or asserted. ${ process.env.CR_REQUIRE_BROWSER ? 'CR_REQUIRE_BROWSER=1, so this fails.' : 'Exiting 0.' }\n${ banner }` );
	process.exit( process.env.CR_REQUIRE_BROWSER ? 1 : 0 );
}

const sheetPath = ( name ) => ( name === 'cr-favorite' && process.env.CR_FAVORITE_CSS ? resolve( process.env.CR_FAVORITE_CSS ) : resolve( ROOT, 'assets/css', name + '.css' ) );
const server = createServer( ( request, response ) => {
	const url = new URL( request.url, 'http://127.0.0.1' );
	const name = /^\/css\/([\w-]+)\.css$/.exec( url.pathname )?.[ 1 ];
	if ( name && SHEETS.includes( name ) ) {
		response.setHeader( 'Content-Type', 'text/css' );
		response.end( readFileSync( sheetPath( name ) ) );
		return;
	}
	if ( url.pathname === '/fixture' ) {
		const { name: key, theme, scrollbar } = Object.fromEntries( url.searchParams );
		const f = FIXTURES[ key ];
		// A tall page, so the viewport scrolls and Chrome reserves the classic scrollbar.
		const classic = scrollbar === '15' ? `body::after { content: ""; display: block; block-size: 3000px; } html { overflow-y: scroll; scrollbar-width: auto !important; scrollbar-color: auto !important; } ::-webkit-scrollbar { width: ${ SCROLLBAR }px; }` : '';
		response.setHeader( 'Content-Type', 'text/html' );
		response.setHeader( 'Content-Security-Policy', "default-src 'self' data:; style-src 'self' 'unsafe-inline'; img-src 'self' data:" );
		response.end( `<!doctype html><html lang="en" data-theme="${ theme }"><head><meta charset="utf-8"><title>Favorite single width</title>${ SHEETS.map( ( s ) => `<link rel="stylesheet" href="/css/${ s }.css">` ).join( '' ) }<style>${ WP_LAYOUT_CSS }${ classic }</style></head>
<body class="single single-post kind-${ f.kind } wp-singular"><div class="wp-site-blocks">
<main class="wp-block-group alignfull single-post has-global-padding is-layout-constrained wp-container-core-group-is-layout-5c5177d5 wp-block-group-is-layout-constrained">
<div class="entry-content align single-post__content wp-block-post-content has-global-padding is-layout-constrained wp-block-post-content-is-layout-constrained"><div class="h-entry kind-${ f.kind } pkiw-singular-entry">${ f.inner }${ HIDDEN }</div></div>
${ NAV }</main></div></body></html>` );
		return;
	}
	response.writeHead( 404 ).end();
} );
await new Promise( ( done ) => server.listen( 0, '127.0.0.1', done ) );
const origin = `http://127.0.0.1:${ server.address().port }`;

const measure = () => {
	const box = ( element ) => {
		if ( ! element ) {
			return null;
		}
		const r = element.getBoundingClientRect();
		const round = ( n ) => Math.round( n * 100 ) / 100;
		return { left: round( r.left ), width: round( r.width ), top: round( r.top ), bottom: round( r.bottom ), right: round( r.right ) };
	};
	const root = document.documentElement;
	return {
		client: root.clientWidth,
		scrollWidth: root.scrollWidth,
		main: box( document.querySelector( 'main' ) ),
		entry: box( document.querySelector( '.entry-content' ) ),
		singular: box( document.querySelector( '.pkiw-singular-entry' ) ),
		sheet: box( document.querySelector( '.cr-fav-sheet' ) ),
		record: box( document.querySelector( '.cr-fav-sheet__record' ) ),
		mount: box( document.querySelector( '.cr-fav-sheet__mount:not(.cr-cover--failed)' ) ),
		star: box( document.querySelector( '.cr-fav-sheet__star' ) ),
		tape: box( document.querySelector( '.cr-fav-sheet__tape' ) ),
		prose: [ ...document.querySelectorAll( '.prose' ) ].map( box ),
		sib: box( document.querySelector( '.sib:not(.after)' ) ),
		after: box( document.querySelector( '.after' ) ),
		innerA: box( document.querySelector( '.inner-a' ) ),
		innerB: box( document.querySelector( '.inner-b' ) ),
		nav: box( document.querySelector( '.cr-post-nav' ) ),
		h1: box( document.querySelector( 'h1' ) ),
	};
};

const round = ( n ) => Math.round( n * 100 ) / 100;
// The geometry of what the sheet sits among, compared with origin/main's. Edges before the sheet are
// absolute tops; edges after it are gaps under the sheet, which don't depend on the sheet's own height.
const metrics = ( m, f ) => {
	const out = { sheetTop: m.sheet.top };
	const lowest = Math.max( m.sheet.bottom, m.after?.bottom ?? 0, f.nested ? m.sib.bottom : 0 );
	out.navGap = round( m.nav.top - lowest );
	out.mainTailGap = round( m.main.bottom - m.nav.bottom );
	if ( m.sib ) {
		Object.assign( out, { sibLeft: m.sib.left, sibWidth: m.sib.width, sibTop: m.sib.top } );
	}
	if ( m.after ) {
		Object.assign( out, { afterLeft: m.after.left, afterWidth: m.after.width, afterGap: round( m.after.top - m.sheet.bottom ) } );
	}
	if ( m.innerA ) {
		Object.assign( out, { aLeft: m.innerA.left, aWidth: m.innerA.width, aTop: m.innerA.top, bLeft: m.innerB.left, bWidth: m.innerB.width, bGap: round( m.innerB.top - m.sheet.bottom ) } );
	}
	return out;
};
const failures = [];
const recorded = {};
let checks = 0;
const same = ( actual, expected, label ) => {
	checks++;
	if ( actual !== expected ) {
		failures.push( `${ label }: expected ${ expected }, got ${ actual }` );
	}
};
const apart = ( a, b ) => a.right <= b.left || b.right <= a.left || a.bottom <= b.top || b.bottom <= a.top;

const browser = await chromium.launch( {
	executablePath,
	ignoreDefaultArgs: [ '--hide-scrollbars' ],
	args: [ '--proxy-server=http://127.0.0.1:1', '--proxy-bypass-list=127.0.0.1', '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1', '--disable-background-networking', '--force-color-profile=srgb' ],
} );
const frames = ( page ) => page.evaluate( () => new Promise( ( done ) => requestAnimationFrame( () => requestAnimationFrame( done ) ) ) );
try {
	for ( const scrollbar of [ 0, SCROLLBAR ] ) {
		for ( const theme of RECORD ? [ 'light' ] : [ 'light', 'dark' ] ) {
			const context = await browser.newContext( { viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1, colorScheme: theme, reducedMotion: 'reduce', serviceWorkers: 'block' } );
			await context.route( '**/*', ( route ) => ( route.request().url().startsWith( origin + '/' ) ? route.continue() : route.abort( 'blockedbyclient' ) ) );
			const page = await context.newPage();
			for ( const key of FIXTURE_KEYS ) {
				const f = FIXTURES[ key ];
				// Dark runs the seven sibling widths on the sheet fixtures; light runs every width on every fixture.
				const widths = RECORD ? SIBLING_WIDTHS : theme === 'dark' ? ( f.favorite ? SIBLING_WIDTHS : [] ) : [ ...new Set( [ ...DENSE_WIDTHS, ...SIBLING_WIDTHS ] ) ];
				if ( ! widths.length ) {
					continue;
				}
				await page.goto( `${ origin }/fixture?name=${ key }&theme=${ theme }&scrollbar=${ scrollbar }`, { waitUntil: 'load' } );
				for ( const width of widths ) {
					await page.setViewportSize( { width, height: 900 } );
					await frames( page );
					const m = await page.evaluate( measure );
					const at = `${ key } ${ theme } ${ width }px sb${ scrollbar }`;
					if ( RECORD ) {
						recorded[ `${ key }|${ width }|${ scrollbar }` ] = metrics( m, f );
						continue;
					}
					same( m.client, width - scrollbar, `${ at }: client width (proves the scrollbar)` );
					same( m.scrollWidth, m.client, `${ at }: no horizontal overflow` );
					same( m.nav.width, Math.min( 1100, width - 32 ), `${ at }: Post navigation keeps the wide size` );
					const want = f.favorite ? favoriteWidth( width ) : narrowWidth( width );
					same( m.entry.width, want, `${ at }: post content width` );
					// The singular wrapper keeps the size WordPress gave it (the Evidence sheet widens it).
					const singularWant = f.favorite && ! f.evidence ? narrowWidth( width ) : want;
					same( m.singular.width, singularWant, `${ at }: singular wrapper width` );
					same( m.singular.left, ( m.client - singularWant ) / 2, `${ at }: singular wrapper is centered` );
					same( m.entry.left, ( m.client - want ) / 2, `${ at }: post content is centered` );
					same( m.entry.right <= m.client, true, `${ at }: post content right edge inside the client width` );
					if ( KEY_WIDTHS.includes( width ) ) {
						same( want, ( f.favorite ? FAVORITE : NARROW )[ width ], `${ at }: contract width` );
					}
					if ( f.favorite ) {
						same( m.sheet.width, want, `${ at }: sheet width` );
						same( m.sheet.left, ( m.client - want ) / 2, `${ at }: sheet left gutter` );
						same( m.client - m.sheet.right, m.sheet.left, `${ at }: sheet right gutter equals the left gutter` );
						same( m.sheet.right <= m.client, true, `${ at }: sheet right edge inside the client width` );
						same( m.sheet.left, m.nav.left, `${ at }: sheet left edge equals Post navigation` );
						same( m.sheet.right, m.nav.right, `${ at }: sheet right edge equals Post navigation` );
						same( m.record.left >= m.sheet.left, true, `${ at }: record inside the sheet` );
						same( m.h1.width > 0, true, `${ at }: one H1 prints` );
						same( apart( m.h1, m.star ), true, `${ at }: the star does not touch the H1` );
						same( apart( m.h1, m.tape ), true, `${ at }: the tape does not touch the H1` );
					}
					if ( f.scope ) {
						same( m.sheet.width, narrowWidth( width ), `${ at }: a sheet outside a Favorite single keeps the 720px size` );
					}
					if ( f.mount ) {
						if ( width >= 1024 ) {
							if ( COLUMNS[ want ] ) {
								same( Math.round( m.record.width ), COLUMNS[ want ][ 0 ], `${ at }: record column` );
								same( Math.round( m.mount.width ), COLUMNS[ want ][ 1 ], `${ at }: mount column` );
							}
							same( m.mount.left >= m.record.right, true, `${ at }: record left, mount right` );
						} else {
							same( m.mount.top >= m.record.bottom, true, `${ at }: one column, mount under the record` );
							same( m.mount.width, want - 48, `${ at }: mount fills the sheet` );
						}
					}
					if ( f.kind === 'favorite' && ! f.favorite ) {
						same( m.sheet, null, `${ at }: protected favorite prints no sheet` );
					}
					if ( ( f.sibling || key === 'rich' ) && SIBLING_WIDTHS.includes( width ) ) {
						const base = BASELINE[ `${ key }|${ width }|${ scrollbar }` ];
						same( Boolean( base ), true, `${ at }: a recorded origin/main baseline exists` );
						const now = metrics( m, f );
						for ( const name of Object.keys( base ?? {} ) ) {
							same( now[ name ], base[ name ], `${ at }: ${ name } as origin/main` );
						}
					}
					if ( key === 'like' ) {
						same( m.prose[ 0 ].left, ( m.client - want ) / 2, `${ at }: other-kind prose position` );
					}
				}
			}
			await context.close();
		}
	}
} finally {
	await browser.close();
	server.close();
}

if ( RECORD ) {
	console.log( JSON.stringify( recorded, null, '\t' ) );
	process.exit( 0 );
}
if ( failures.length ) {
	console.log( failures.slice( 0, 40 ).join( '\n' ) );
	console.log( `FAIL favorite-single-sheet-width: ${ failures.length } of ${ checks } checks failed` );
	process.exit( 1 );
}
console.log( `PASS favorite-single-sheet-width: ${ checks } checks` );
