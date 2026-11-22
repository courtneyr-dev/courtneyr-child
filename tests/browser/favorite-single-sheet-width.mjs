// `npm run test:favorite-single-width`: renders the Favorite single's real wrapper chain
// (templates/single.html: main > entry-content > the plugin's .pkiw-singular-entry > the sheet)
// in headless Chrome with WordPress's generated constrained-layout CSS and the theme's checked-in
// sheets, and asserts the sheet's width against fixed values from the approved contract: the
// sheet is the page's dominant object, at most the site's wide size (1100px), and fills the
// width on narrower screens. No `max-width: none` override is in the fixture.
//
// The layout CSS below is what WordPress 7.1 prints for this template (wp-includes/block-supports/
// layout.php constrained rules and class-wp-theme-json.php `.has-global-padding` rules) with
// theme.json's contentSize 720px, wideSize 1100px and root padding 1rem. The markup is the
// sheet captured from the served single (G-1 round 1, single-desktop.html), images swapped for data URIs.
//
// Each width is checked in light and dark, with overlay scrollbars and with a forced 15px
// classic scrollbar. No vw units anywhere: expected values come from the client width.
//
// Offline: a loopback server serves the sheets and fixtures; everything else is blocked. Needs
// Chrome: CR_CHROME=/path/to/chrome, or Google Chrome / Chromium in a standard location. Without
// one the test prints a SKIP banner and exits 0, unless CR_REQUIRE_BROWSER=1. Not wired into CI.
// CR_FAVORITE_CSS=/path/to/cr-favorite.css renders that sheet instead of the checked-in one (mutation runs).
import { createServer } from 'node:http';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const SHEETS = [ 'tokens', 'components', 'cr-blog', 'cr-paper', 'cr-sticker', 'cr-favorite', 'cr-post-kinds', 'cr-nav' ];
const SCROLLBAR = 15;
const VIEWPORTS = [ 1672, 1280, 1024, 768, 390, 320 ];

// Fixed expectations. Main keeps 1rem gutters (32px). The theme's full-bleed rule gives
// `main.alignfull` the viewport width even beside a classic scrollbar (components.css), so the parent
// is width - 32 in both scrollbar cases; this fix adds no viewport unit of its own.
// Favorite sheet: min( 1100, parent ). Everything else keeps min( 720, parent ).
const FAVORITE = { 1672: 1100, 1280: 1100, 1024: 992, 768: 736, 390: 358, 320: 288 };
const NARROW = { 1672: 720, 1280: 720, 1024: 720, 768: 720, 390: 358, 320: 288 };
// Record and mount columns (3fr / 2fr) inside sheet padding 24px + gap 24px, from 64rem.
const COLUMNS = { 1100: [ 617, 411 ], 992: [ 552, 368 ] };

const WP_LAYOUT_CSS = `
:root { --wp--style--global--content-size: 720px; --wp--style--global--wide-size: 1100px; --wp--preset--spacing--md: 1rem;
  --wp--style--root--padding-left: 1rem; --wp--style--root--padding-right: 1rem; }
.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) { max-width: var(--wp--style--global--content-size); margin-left: auto !important; margin-right: auto !important; }
.is-layout-constrained > .alignwide { max-width: var(--wp--style--global--wide-size); }
.wp-container-core-group-is-layout-5c5177d5 > :where(:not(.alignleft):not(.alignright):not(.alignfull)) { max-width: 720px; margin-left: auto !important; margin-right: auto !important; }
.wp-container-core-group-is-layout-5c5177d5 > .alignwide { max-width: 1100px; }
.wp-container-core-group-is-layout-5c5177d5 .alignfull { max-width: none; }
.has-global-padding { padding-right: var(--wp--style--root--padding-right); padding-left: var(--wp--style--root--padding-left); }
.has-global-padding > .alignfull { margin-right: calc(var(--wp--style--root--padding-right) * -1); margin-left: calc(var(--wp--style--root--padding-left) * -1); }
.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)) { padding-right: 0; padding-left: 0; }
.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)) > .alignfull { margin-left: 0; margin-right: 0; }
`;

const IMAGE = 'data:image/svg+xml;utf8,' + encodeURIComponent( '<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="640"><rect width="1024" height="640" fill="#9bb"/></svg>' );
const STAR = '<span role="presentation" class="cr-fav-sheet__star" aria-hidden="true"><svg role="presentation" class="cr-fav-star__shape" viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 2l3 6.5 7 .6-5.3 4.6 1.6 6.8L12 17l-6.9 3.5 1.6-6.8L1.4 9.1l7-.6z"></path></svg></span>';
const HEAD = STAR + '<p class="cr-fav-sheet__tape"><span class="pk-kindlabel">Favorite</span><span role="presentation" class="cr-fav-sheet__marker" aria-hidden="true"></span></p><h1 class="cr-fav-sheet__title p-name">Field Guide to Quiet Software</h1>';
const RECORD = '<article class="cr-fav-sheet__record h-cite u-favorite-of"><dl class="cr-fav-rows"><div class="cr-fav-row cr-fav-row--author"><dt>By</dt><dd><span class="p-author h-card"><span class="p-name">Rowan Vale</span></span></dd></div><div class="cr-fav-row cr-fav-row--source"><dt>Source</dt><dd><a class="u-url cr-fav-link" href="/never-visited" rel="noopener noreferrer nofollow">notes.example.com</a></dd></div><div class="cr-fav-row cr-fav-row--favorited"><dt>Favorited</dt><dd><time class="dt-published" datetime="2026-08-14">August 14, 2026</time></dd></div></dl><p class="cr-fav-sheet__description p-content">Three yes-or-no questions that decide whether a tool earns a place on the desk.</p></article>';
const MOUNT = `<div class="cr-fav-sheet__mount" data-cr-cover-fallback=""><img width="1024" height="640" src="${ IMAGE }" class="cr-fav-sheet__img skip-lazy" alt="A flowchart"></div>`;
const FAILED = `<div class="cr-fav-sheet__mount cr-cover--failed" data-cr-cover-fallback=""><img width="1024" height="640" src="${ IMAGE }" class="cr-fav-sheet__img skip-lazy" alt="A flowchart"></div>`;
const sheet = ( mount ) => `<div class="cr-fav-sheet">${ HEAD }<div class="cr-fav-sheet__body">${ RECORD }${ mount }</div></div>`;
const PROSE = '<div class="wp-block-group prose"><p>Why this one stayed with me.</p></div>';
const HIDDEN = '<a class="u-url" href="/x" hidden=""></a><time class="dt-published" datetime="2026-08-28T10:00:00-04:00" hidden=""></time>';
const OTHER = '<p class="prose">A short note in this kind of post.</p>';
const NAV = '<nav class="wp-block-group alignwide cr-post-nav is-layout-flow wp-block-group-is-layout-flow" aria-label="Post navigation"><div class="cr-post-nav__link"><a href="/a">Previous</a></div><div class="cr-post-nav__link"><a href="/b">Next</a></div></nav>';

// kind: body class; inner: the singular wrapper's content; narrow: expect the 720px content size.
const FIXTURES = {
	rich: { kind: 'favorite', inner: sheet( MOUNT ), favorite: true, mount: true },
	nomount: { kind: 'favorite', inner: `<div class="cr-fav-sheet">${ HEAD }<div class="cr-fav-sheet__body">${ RECORD }</div></div>`, favorite: true },
	failed: { kind: 'favorite', inner: sheet( FAILED ), favorite: true },
	prose: { kind: 'favorite', inner: PROSE + sheet( MOUNT ) + PROSE, favorite: true, prose: true },
	protected: { kind: 'favorite', inner: '<h1 class="single-post__title">Protected</h1><form class="post-password-form"><label>Password <input type="password"></label></form>' },
	scope: { kind: 'like', inner: sheet( MOUNT ), scope: true },
	like: { kind: 'like', inner: OTHER },
	repost: { kind: 'repost', inner: OTHER },
	jam: { kind: 'jam', inner: OTHER },
	checkin: { kind: 'checkin', inner: OTHER },
	read: { kind: 'read', inner: OTHER },
	comic: { kind: 'comic', inner: OTHER },
	quote: { kind: 'quote', inner: OTHER },
};

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
		const classic = scrollbar === '15' ? `html { overflow-y: scroll; } body::after { content: ""; display: block; block-size: 3000px; } html { scrollbar-width: auto !important; scrollbar-color: auto !important; } ::-webkit-scrollbar { width: ${ SCROLLBAR }px; }` : '';
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
		return { left: Math.round( r.left * 100 ) / 100, width: Math.round( r.width * 100 ) / 100, top: Math.round( r.top * 100 ) / 100, bottom: Math.round( r.bottom * 100 ) / 100, right: Math.round( r.right * 100 ) / 100 };
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
		prose: [ ...document.querySelectorAll( '.prose' ) ].map( box ),
		nav: box( document.querySelector( '.cr-post-nav' ) ),
		h1: box( document.querySelector( 'h1' ) ),
		layout: { entryMax: getComputedStyle( document.querySelector( '.entry-content' ) ).maxWidth, singularMax: getComputedStyle( document.querySelector( '.pkiw-singular-entry' ) ).maxWidth },
	};
};

const failures = [];
let checks = 0;
const same = ( actual, expected, label ) => {
	checks++;
	if ( actual !== expected ) {
		failures.push( `${ label }: expected ${ expected }, got ${ actual }` );
	}
};

const browser = await chromium.launch( {
	executablePath,
	ignoreDefaultArgs: [ '--hide-scrollbars' ],
	args: [ '--proxy-server=http://127.0.0.1:1', '--proxy-bypass-list=127.0.0.1', '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1', '--disable-background-networking', '--force-color-profile=srgb' ],
} );
try {
	for ( const scrollbar of [ 0, SCROLLBAR ] ) {
		for ( const theme of [ 'light', 'dark' ] ) {
			for ( const width of VIEWPORTS ) {
				const context = await browser.newContext( { viewport: { width, height: 900 }, deviceScaleFactor: 1, colorScheme: theme, reducedMotion: 'reduce', serviceWorkers: 'block' } );
				await context.route( '**/*', ( route ) => ( route.request().url().startsWith( origin + '/' ) ? route.continue() : route.abort( 'blockedbyclient' ) ) );
				const page = await context.newPage();
				for ( const [ key, f ] of Object.entries( FIXTURES ) ) {
					await page.goto( `${ origin }/fixture?name=${ key }&theme=${ theme }&scrollbar=${ scrollbar }`, { waitUntil: 'load' } );
					await page.evaluate( () => new Promise( ( done ) => requestAnimationFrame( () => requestAnimationFrame( done ) ) ) );
					const m = await page.evaluate( measure );
					const at = `${ key } ${ theme } ${ width }px sb${ scrollbar }`;
					const parent = width - 32;
					same( m.client, width - scrollbar, `${ at }: client width (proves the scrollbar)` );
					same( m.scrollWidth, m.client, `${ at }: no horizontal overflow` );
					same( m.nav.width, Math.min( 1100, parent ), `${ at }: Post navigation keeps the wide size` );
					const want = f.favorite ? FAVORITE[ width ] : NARROW[ width ];
					same( m.entry.width, want, `${ at }: post content width` );
					same( m.singular.width, want, `${ at }: singular wrapper width` );
					same( m.entry.left, ( m.client - want ) / 2, `${ at }: post content is centered` );
					if ( f.favorite ) {
						same( m.sheet.width, want, `${ at }: sheet width` );
						same( m.sheet.left, ( m.client - want ) / 2, `${ at }: sheet is centered` );
						same( m.record.left >= m.sheet.left, true, `${ at }: record inside the sheet` );
						same( m.h1.width > 0, true, `${ at }: one H1 prints` );
					}
					if ( f.scope ) {
						same( m.sheet.width, NARROW[ width ], `${ at }: a sheet outside a Favorite single keeps the 720px size` );
					}
					if ( f.mount ) {
						if ( width >= 1024 ) {
							same( Math.round( m.record.width ), COLUMNS[ want ][ 0 ], `${ at }: record column` );
							same( Math.round( m.mount.width ), COLUMNS[ want ][ 1 ], `${ at }: mount column` );
							same( m.mount.left >= m.record.right, true, `${ at }: record left, mount right` );
						} else {
							same( m.mount.top >= m.record.bottom, true, `${ at }: one column, mount under the record` );
							same( m.mount.width, want - 48, `${ at }: mount fills the sheet` );
						}
					}
					if ( f.kind === 'favorite' && ! f.favorite ) {
						same( m.sheet, null, `${ at }: protected favorite prints no sheet` );
					}
					if ( f.prose ) {
						const prose = NARROW[ width ];
						for ( const block of m.prose ) {
							same( block.width, prose, `${ at }: prose around the card keeps the content size` );
							same( block.left, ( m.client - prose ) / 2, `${ at }: prose is centered` );
						}
					}
					if ( key === 'like' ) {
						same( m.prose[ 0 ].left, ( m.client - want ) / 2, `${ at }: other-kind prose position` );
					}
				}
				await context.close();
			}
		}
	}
} finally {
	await browser.close();
	server.close();
}

if ( failures.length ) {
	console.log( failures.slice( 0, 40 ).join( '\n' ) );
	console.log( `FAIL favorite-single-sheet-width: ${ failures.length } of ${ checks } checks failed` );
	process.exit( 1 );
}
console.log( `PASS favorite-single-sheet-width: ${ checks } checks` );
