// `node tests/browser/read-single-wide-column.mjs`: renders REAL pages of a WordPress site in headless Chrome and
// asserts the Read single's wide column (inc/single-wide-column.php, assets/css/cr-wide-column.css) against fixed
// values. The pages are captured from the local Studio site (see tests/browser/read-single-wide-column/README.md),
// not rebuilt: the served HTML and every stylesheet, font and script the page loaded, including the layout CSS
// WordPress generated (is-layout-constrained, the wp-container-* rules in core-block-supports-inline-css).
//
//   CR_READ_FIXTURE=<dir>   a capture folder holding base/ (theme acef24d) and head/ (this branch), each with pages/ and assets/
//   CR_READ_TREE=head|base  the tree under test (default head). base is the reference; run with base to see it fail.
//   CR_MUTANT=<name>        applies one mutant to the tree under test; `--mutants` runs them all and requires each to fail
//                           in its own assertion class. `--list` prints the mutants.
//   CR_LEGACY=1             strips every rule that needs :has(), cqw or @property from both trees (an old engine)
//   CR_CHROME=<path>        Chrome binary. No fixture or no Chrome: a SKIP banner and exit 0, or exit 1 with CR_REQUIRE_BROWSER=1.
//
// Offline: a loopback server serves the capture and every other request is blocked. The harness adds one thing to a
// page: the rule that gives it a classic 15px scrollbar (`html[data-sb]`). It overrides no layout.
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const FIXTURE = process.env.CR_READ_FIXTURE ? resolve( process.env.CR_READ_FIXTURE ) : null;
const TREE = process.env.CR_READ_TREE || 'head';
const MUTANT = process.env.CR_MUTANT || '';
const LEGACY = Boolean( process.env.CR_LEGACY );
const SITE = 'http://localhost:8887';

const banner = ( text ) => `${ '*'.repeat( 72 ) }\n${ text }\n${ '*'.repeat( 72 ) }`;
const chromePath = () => [ process.env.CR_CHROME, '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/usr/bin/google-chrome', '/usr/bin/chromium' ].filter( Boolean ).find( existsSync ) || null;
const executablePath = chromePath();
if ( ! FIXTURE || ! existsSync( join( FIXTURE, 'head/pages' ) ) || ! executablePath ) {
	console.log( banner( `SKIP read-single-wide-column: ${ ! FIXTURE ? 'CR_READ_FIXTURE is not set' : ! executablePath ? 'no Chrome found (set CR_CHROME)' : 'no head/pages in the fixture' }. Nothing was painted or asserted. ${ process.env.CR_REQUIRE_BROWSER ? 'CR_REQUIRE_BROWSER=1, so this fails.' : 'Exiting 0.' }` ) );
	process.exit( process.env.CR_REQUIRE_BROWSER ? 1 : 0 );
}

// ---- Fixed expectations (px). From the approved mockup's two-column spread and theme.json (contentSize 720, wideSize 1100).
const READING = 720;
const WIDE = 1100;
const PAD = 16; // root padding the page leaves on each side
const BOOK = 368; // 23rem, the book column's maximum
const GAP = 44; // 2.75rem
const GRID_FROM = 1024; // 64rem
const SCROLLBAR = 15;
const WIDTHS = [ 1672, 1448, 1280, 1024, 768, 390, 320 ];
const SWEEP = [ 320, 360, 390, 414, 480, 600, 768, 800, 900, 1000, 1023, 1024, 1025, 1040, 1050, 1060, 1080, 1100, 1120, 1131, 1132, 1133, 1200, 1280, 1366, 1448, 1600, 1672, 1920 ];
const SCHEMES = [ 'light', 'dark' ];
// A mutant run needs only the pages its assertion class reads, so it finishes in seconds.
const RECORD_PAGES = process.env.CR_MUTANT ? [ 'read-finished', 'read-prose', 'read-sample' ] : [ 'read-finished', 'read-reading', 'read-abandoned', 'read-prose', 'read-sample', 'read-watch', 'read-loop', 'read-paged' ];
// A Read with notes but no record, with a sample but no record, page 2 of a paged Read, and a Read whose first kind is Like must render as the base does.
const BASE_IDENTICAL = [ 'read-locked', 'read-rowless', 'read-rowless-notes', 'read-rowless-sample', 'read-paged-2', 'read-like', 'checkin', 'comic', 'quote', 'watch', 'listen', 'like', 'eat', 'drink', 'repost', 'favorite', 'jam', 'kind-read', 'kind-like', 'kind-repost', 'kind-favorite', 'kind-checkin', 'stream', 'home', 'hello' ];
// Responses that are not pages (a feed, REST, search): the same bytes as the base.
const BYTES_ONLY = [ 'search', 'feed-read', 'rest-posts' ];

const MUTANTS = {
	'sparkle-plus-20px': { cls: 'sparkle', sheet: 'cr-wide-column.css', swap: [ '- 1rem)', '- 1rem + 20px)' ] },
	'sparkle-top-shift': { cls: 'sparkle', sheet: 'cr-wide-column.css', append: 'body.single-post .cr-wide-column.single-post__content::before { top: -2rem; }' },
	'newsletter-plus-24px': { cls: 'siblings', sheet: 'cr-wide-column.css', append: 'body.single-post .cr-newsletter-banner__inner { position: relative; left: 24px; }' },
	'content-from-100vw': { cls: 'edge', sheet: 'cr-wide-column.css', append: 'body.single-post .cr-wide-column.single-post__content { width: min(1100px, calc(100vw - 1.5rem)); max-width: none; }' },
	'dark-only-record-narrow': { cls: 'widths', sheet: 'cr-wide-column.css', append: '@media (prefers-color-scheme: dark) { body.single-post.kind-read .cr-journal > .cr-record { max-width: 600px; } }' },
	'locked-read-widened': { cls: 'identical', page: 'read-locked', swap: [ 'entry-content align ', 'entry-content alignwide ' ] },
	'rowless-read-widened': { cls: 'identical', page: 'read-rowless', swap: [ 'entry-content align ', 'entry-content alignwide ' ] },
	'other-kind-widened': { cls: 'identical', page: 'like', swap: [ 'entry-content align ', 'entry-content alignwide ' ] },
	'order-swap': { cls: 'order', sheet: 'cr-wide-column.css', append: '@media (min-width: 64rem) { body.single-post.kind-read .cr-journal > .cr-record { grid-row: 1; } body.single-post.kind-read .cr-journal > .cr-journal__notes { grid-row: 2; } }' },
	'divider-capped-at-720': { cls: 'divider', sheet: 'cr-wide-column.css', append: 'body.single-post.kind-read .cr-journal > .cr-journal__meta { max-width: 720px; }' },
	'sample-capped-at-524': { cls: 'sample', sheet: 'cr-wide-column.css', append: 'body.single-post.kind-read .cr-wide-column .cr-read__sample { max-width: 524px; }' },
	'sample-hidden': { cls: 'painted', sheet: 'cr-wide-column.css', append: 'body.single-post.kind-read .cr-wide-column .cr-read__sample { display: none; }' },
	'record-opacity-dark': { cls: 'painted', sheet: 'cr-wide-column.css', append: '@media (prefers-color-scheme: dark) { body.single-post.kind-read .cr-record { opacity: 0.07; } }' },
	'rowless-grid-unscoped': { cls: 'identical', sheet: 'cr-post-kinds.css', swap: [ 'minmax(17rem, 23rem) minmax(0, 1fr) 9.5rem', 'minmax(17rem, 23rem) minmax(0, 1fr)' ], identical: [ 'read-rowless-notes' ] },
	'rowless-sample-span-changed': { cls: 'identical', sheet: 'cr-post-kinds.css', swap: [ '.cr-read__sample { grid-column: 1 / 3; grid-row: 3; }', '.cr-read__sample { grid-column: 1 / 2; grid-row: 3; }' ], identical: [ 'read-rowless-sample' ] },
	'paged-page-2-widened': { cls: 'identical', page: 'read-paged-2', swap: [ 'entry-content align ', 'entry-content alignwide ' ] },
	'like-first-widened': { cls: 'identical', page: 'read-like', swap: [ 'entry-content align ', 'entry-content alignwide ' ] },
	'secondary-loop-marked': { cls: 'loop', page: 'read-loop', swap: [ 'entry-content single-post__content wp-block-post-content has-global-padding', 'entry-content alignwide single-post__content wp-block-post-content cr-wide-column has-global-padding' ] },
	'header-physical-margin': { cls: 'rtl', sheet: 'cr-wide-column.css', swap: [ 'margin-inline-start: 0 !important;', 'margin-left: 0 !important;' ] },
	'record-column-narrow': { cls: 'widths', sheet: 'cr-wide-column.css', append: '@media (min-width: 64rem) { body.single-post.kind-read .cr-wide-column .cr-journal { grid-template-columns: minmax(17rem, 23rem) 20rem; } }' },
	'notes-narrower-than-record': { cls: 'widths', sheet: 'cr-wide-column.css', append: 'body.single-post.kind-read .cr-journal__notes { width: 90%; }' },
	'title-not-aligned-desktop': { cls: 'title', sheet: 'cr-wide-column.css', swap: [ 'margin-inline-start: 0 !important;', 'margin-inline-start: auto !important;' ] },
	'header-class-missing': { cls: 'title', page: 'read-finished', swap: [ 'wp-block-group-is-layout-constrained cr-wide-column"', 'wp-block-group-is-layout-constrained"' ] },
	'horizontal-overflow': { cls: 'edge', sheet: 'cr-wide-column.css', append: 'body.single-post.kind-read .cr-record { min-width: 1300px; }' },
	'prose-widened': { cls: 'prose', sheet: 'cr-wide-column.css', swap: [ 'max-width: var(--wp--style--global--content-size);\n\tmargin-right: auto;\n\tmargin-left: auto;', 'max-width: none;' ] },
	'tablet-cap-removed': { cls: 'widths', sheet: 'cr-wide-column.css', swap: [ /@media not all and \(min-width: 64rem\) \{[\s\S]*?\n\}\n/, '' ] },
	'isbn-wraps': { cls: 'oneline', sheet: 'cr-wide-column.css', append: 'body.single-post.kind-read .cr-record-row dd { max-inline-size: 3ch; overflow-wrap: anywhere; }' },
};

if ( process.argv.includes( '--list' ) ) {
	console.log( Object.keys( MUTANTS ).join( '\n' ) );
	process.exit( 0 );
}
if ( process.argv.includes( '--mutants' ) ) {
	let bad = 0;
	for ( const [ name, mutant ] of Object.entries( MUTANTS ) ) {
		const run = spawnSync( process.execPath, [ fileURLToPath( import.meta.url ) ], { env: { ...process.env, CR_MUTANT: name }, encoding: 'utf8', cwd: ROOT } );
		const summary = /^CLASSES (.*)$/m.exec( run.stdout );
		const byClass = summary ? JSON.parse( summary[ 1 ] ) : {};
		const classes = Object.keys( byClass );
		const failed = Object.values( byClass );
		const killed = run.status !== 0 && classes.includes( mutant.cls );
		bad += killed ? 0 : 1;
		console.log( `${ killed ? 'KILLED  ' : 'SURVIVED' } ${ name.padEnd( 28 ) } expects [${ mutant.cls }] fails: ${ classes.join( ', ' ) || 'none' } (${ failed.length } assertions)` );
	}
	console.log( bad ? `${ bad } mutant(s) survived` : `all ${ Object.keys( MUTANTS ).length } mutants killed` );
	process.exit( bad ? 1 : 0 );
}

// ---- The loopback server.
const strip = ( css ) => {
	// Old-engine simulation: drop every rule whose prelude needs :has(), cqw/cqi/cqb units or @property.
	let out = '';
	let depth = 0;
	let start = 0;
	let skip = -1;
	for ( let i = 0; i < css.length; i++ ) {
		const c = css[ i ];
		if ( c === '{' ) {
			const prelude = css.slice( start, i );
			if ( skip < 0 && ( /:has\(|@property/.test( prelude ) ) ) {
				skip = depth;
			}
			depth++;
			if ( skip < 0 ) {
				out += css.slice( start, i + 1 );
			}
			start = i + 1;
		} else if ( c === '}' ) {
			const text = css.slice( start, i );
			if ( skip < 0 ) {
				out += text + '}';
			}
			depth--;
			if ( skip === depth ) {
				skip = -1;
			}
			start = i + 1;
		} else if ( c === ';' && skip < 0 && depth > 0 ) {
			out += css.slice( start, i + 1 );
			start = i + 1;
		} else if ( c === ';' ) {
			start = i + 1;
		}
	}
	return out;
};
const sheetText = ( tree, name ) => readFileSync( join( FIXTURE, tree, 'assets/wp-content/themes/courtneyr-child/assets/css', name ), 'utf8' );
const mutate = ( kind, name, text ) => {
	const mutant = MUTANTS[ MUTANT ];
	if ( ! mutant ) {
		return text;
	}
	let result = text;
	if ( kind === 'sheet' && mutant.sheet === name ) {
		if ( mutant.swap ) {
			result = result.replace( mutant.swap[ 0 ], mutant.swap[ 1 ] );
			if ( result === text ) {
				throw new Error( `mutant ${ MUTANT } did not change ${ name }` );
			}
		}
		if ( mutant.append ) {
			result += '\n' + mutant.append + '\n';
		}
	}
	if ( kind === 'page' && mutant.page === name && mutant.swap ) {
		result = result.replace( mutant.swap[ 0 ], mutant.swap[ 1 ] );
		if ( result === text ) {
			throw new Error( `mutant ${ MUTANT } did not change ${ name }` );
		}
	}
	return result;
};
const server = createServer( ( request, response ) => {
	const url = new URL( request.url, 'http://127.0.0.1' );
	const origin = `http://127.0.0.1:${ server.address().port }`;
	const page = /^\/__(base|head)\/([\w-]+)$/.exec( url.pathname );
	const asset = /^\/__(base|head)(\/.+)$/.exec( url.pathname );
	const send = ( type, body ) => {
		response.setHeader( 'Content-Type', type );
		response.end( body );
	};
	if ( page ) {
		const [ , tree, name ] = page;
		let html = readFileSync( join( FIXTURE, tree, 'pages', name + '.html' ), 'utf8' );
		if ( tree === TREE ) {
			html = mutate( 'page', name, html );
		}
		html = html.replaceAll( SITE, `${ origin }/__${ tree }` );
		html = html.replace( '</head>', '<style id="cr-harness-scrollbar">html[data-sb]{overflow-y:scroll}html[data-sb]::-webkit-scrollbar{width:15px;height:15px}</style></head>' );
		if ( LEGACY ) {
			html = html.replace( /(<style[^>]*>)([\s\S]*?)(<\/style>)/g, ( m, a, b, c ) => ( b.includes( 'cr-harness-scrollbar' ) ? m : a + strip( b ) + c ) );
		}
		return send( 'text/html; charset=utf-8', html );
	}
	if ( asset ) {
		const [ , tree, path ] = asset;
		const file = join( FIXTURE, tree, 'assets', decodeURIComponent( path ) );
		if ( ! file.startsWith( join( FIXTURE, tree, 'assets' ) ) || ! existsSync( file ) ) {
			return response.writeHead( 404 ).end();
		}
		const type = { css: 'text/css', js: 'text/javascript', woff2: 'font/woff2', svg: 'image/svg+xml', png: 'image/png', jpg: 'image/jpeg', json: 'application/json' }[ file.split( '.' ).pop() ] || 'application/octet-stream';
		if ( type === 'text/css' || type === 'text/javascript' ) {
			let text = readFileSync( file, 'utf8' ).replaceAll( SITE, `${ origin }/__${ tree }` );
			if ( type === 'text/css' ) {
				if ( tree === TREE && file.includes( '/themes/courtneyr-child/assets/css/' ) ) {
					text = mutate( 'sheet', file.split( '/' ).pop(), text );
				}
				if ( LEGACY ) {
					text = strip( text );
				}
			}
			return send( type, text );
		}
		return send( type, readFileSync( file ) );
	}
	response.writeHead( 404 ).end();
} );
await new Promise( ( done ) => server.listen( 0, '127.0.0.1', done ) );
const ORIGIN = `http://127.0.0.1:${ server.address().port }`;

// ---- What the page reports.
const measure = () => {
	const q = ( s, root = document ) => root.querySelector( s );
	const box = ( e ) => {
		if ( ! e ) {
			return null;
		}
		const b = e.getBoundingClientRect();
		return { l: b.left, t: b.top + scrollY, w: b.width, h: b.height, r: b.right };
	};
	const content = q( '.single-post__content' );
	const header = q( '.single-post__header' );
	const spark = content ? getComputedStyle( content, '::before' ) : null;
	const cb = content ? content.getBoundingClientRect() : null;
	const entry = q( '.pkiw-singular-entry' );
	const lines = ( e ) => {
		const range = document.createRange();
		range.selectNodeContents( e );
		return new Set( [ ...range.getClientRects() ].map( ( r ) => Math.round( r.top ) ) ).size;
	};
	const parts = [ '.cr-book', '.cr-journal__notes', '.cr-record', '.cr-read__sample' ].map( ( s ) => [ s, q( s ) ] ).filter( ( [ , e ] ) => e );
	const all = ( skipInside ) => [ ...document.body.querySelectorAll( '*' ) ].filter( ( e ) => ! skipInside.some( ( s ) => s && s !== e && s.contains( e ) ) ).map( ( e ) => {
		const b = e.getBoundingClientRect();
		return { i: e, l: b.left, t: b.top + scrollY, w: b.width, h: b.height };
	} );
	const outside = all( [ header, content ] ).filter( ( e ) => e.i !== header && e.i !== content );
	const before = content ? outside.filter( ( e ) => e.i.compareDocumentPosition( content ) & Node.DOCUMENT_POSITION_FOLLOWING && ! e.i.contains( content ) ) : [];
	const prose = q( '.pkiw-singular-entry > p' );
	return {
		innerWidth,
		clientWidth: document.documentElement.clientWidth,
		scrollWidth: document.documentElement.scrollWidth,
		main: { ...box( q( 'main' ) ), r: q( 'main' ).getBoundingClientRect().right },
		header: box( header ),
		h1: box( q( 'h1' ) ),
		content: box( content ),
		entry: box( entry ),
		journal: box( q( '.cr-journal' ) ),
		meta: box( q( '.cr-journal__meta' ) ),
		prose: box( prose ),
		parts: Object.fromEntries( parts.map( ( [ s, e ] ) => [ s, box( e ) ] ) ),
		domOrder: parts.map( ( [ s ] ) => s ),
		painted: parts.map( ( [ s, e ] ) => {
			const c = getComputedStyle( e );
			return [ s, c.display !== 'none' && c.visibility === 'visible' && c.opacity === '1' && e.getBoundingClientRect().width > 0 ];
		} ),
		loop: box( q( '.wp-block-query .single-post__content' ) ),
		loopClass: q( '.wp-block-query .single-post__content' )?.className ?? null,
		dir: getComputedStyle( document.documentElement ).direction,
		rowLines: [ ...document.querySelectorAll( '.cr-record-row dd' ) ].map( lines ),
		spark: spark && spark.position === 'absolute' ? { x: cb.right - parseFloat( spark.right ), y: cb.top + scrollY + parseFloat( spark.top ) } : { position: spark ? spark.position : null },
		leftmost: Math.min( ...[ header, content, entry, q( '.cr-journal' ), ...parts.map( ( [ , e ] ) => e ) ].filter( Boolean ).map( ( e ) => e.getBoundingClientRect().left ) ),
		right: Math.max( ...[ q( 'main' ), header, ...[ ...( header ? header.children : [] ) ], content, entry, q( '.cr-journal' ), ...parts.map( ( [ , e ] ) => e ) ].filter( Boolean ).slice( 1 ).map( ( e ) => e.getBoundingClientRect().right ) ),
		outside: outside.map( ( e ) => [ e.l, e.w ] ),
		before: before.map( ( e ) => [ e.l, e.t, e.w, e.h ] ),
		everything: all( [] ).map( ( e ) => [ e.l, e.t, e.w, e.h ] ),
	};
};

const failures = [];
const record = {};
let checks = 0;
// An old engine also loses the sheets' own :has() rules (the journal's grid among them), so its layout is not the design. It must still not overflow, keep the sparkle and leave every other box where the base leaves it.
const DESIGN = [ 'widths', 'title', 'order', 'oneline', 'divider', 'prose', 'rtl', 'sample' ];
const check = ( cls, ok, label, detail = '' ) => {
	if ( LEGACY && DESIGN.includes( cls ) ) {
		return;
	}
	checks++;
	if ( ! ok ) {
		failures.push( `FAIL [${ cls }] ${ label }${ detail ? ' :: ' + detail : '' }` );
	}
};
const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

const browser = await chromium.launch( { executablePath, ignoreDefaultArgs: [ '--hide-scrollbars' ], args: [ '--proxy-server=http://127.0.0.1:1', '--proxy-bypass-list=127.0.0.1', '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1', '--disable-background-networking', '--force-color-profile=srgb' ] } );
const context = await browser.newContext( { viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1, serviceWorkers: 'block' } );
await context.route( '**/*', ( route ) => ( route.request().url().startsWith( ORIGIN + '/' ) ? route.continue() : route.abort( 'blockedbyclient' ) ) );
const opened = {};
const open = async ( tree, name ) => {
	const key = tree + '/' + name;
	if ( ! opened[ key ] ) {
		const page = await context.newPage();
		await page.goto( `${ ORIGIN }/__${ tree }/${ name }`, { waitUntil: 'load' } );
		await page.evaluate( () => document.fonts.ready );
		opened[ key ] = page;
	}
	return opened[ key ];
};
const reading = async ( tree, name, vw, scheme, sb, dir = '' ) => {
	const page = await open( tree, name );
	await page.evaluate( ( d ) => {
		window.__dir0 = 'dir0' in window ? window.__dir0 : document.documentElement.getAttribute( 'dir' );
		const original = window.__dir0;
		if ( d ) {
			document.documentElement.setAttribute( 'dir', d );
		} else if ( original === null ) {
			document.documentElement.removeAttribute( 'dir' );
		} else {
			document.documentElement.setAttribute( 'dir', original );
		}
	}, dir );
	await page.setViewportSize( { width: vw, height: 900 } );
	await page.emulateMedia( { colorScheme: scheme, reducedMotion: 'reduce' } );
	await page.evaluate( ( on ) => document.documentElement.toggleAttribute( 'data-sb', on ), sb > 0 );
	await page.evaluate( () => new Promise( ( done ) => requestAnimationFrame( () => requestAnimationFrame( done ) ) ) );
	const got = await page.evaluate( measure );
	return got;
};

// Static: the real-fixture and no-vw rules.
const headCss = sheetText( 'head', 'cr-wide-column.css' );
check( 'static', ! /\bvw\b|\d\s*vw|\bdvw|\bsvw|\blvw/.test( headCss ) && ! /\d(vw|vh|vi|vb|cqw|cqi|cqh|cqb)\b/.test( headCss ), 'cr-wide-column.css uses no vw, vh or container units' );
check( 'static', ! /:has\(|@property|@container|cqw/.test( headCss ), 'cr-wide-column.css needs no :has(), @container, cqw or @property' );
for ( const name of RECORD_PAGES ) {
	for ( const tree of [ 'base', 'head' ] ) {
		const html = readFileSync( join( FIXTURE, tree, 'pages', name + '.html' ), 'utf8' );
		check( 'static', html.includes( ".is-layout-constrained > .alignwide{max-width: var(--wp--style--global--wide-size);}" ) && /core-block-supports-inline-css/.test( html ), `${ tree }/${ name } carries WordPress's own generated layout CSS` );
	}
	const headHtml = readFileSync( join( FIXTURE, 'head', 'pages', name + '.html' ), 'utf8' );
	check( 'static', /\{max-width:var\(--wp--style--global--content-size\);margin-left:auto !important;margin-right:auto !important;\}/.test( headHtml ), `head/${ name } holds the generated header rule (reading width)` );
	check( 'static', /wp-container-core-post-content-is-layout-[0-9a-f]+ > :where\(:not\(\.alignleft\):not\(\.alignright\):not\(\.alignfull\)\)\{max-width:100%/.test( headHtml ), `head/${ name } holds the generated post-content container rule (contentSize 100%)` );
}

// 1. Geometry of the record-carrying reads against the fixed design.
for ( const name of RECORD_PAGES ) {
	for ( const scheme of SCHEMES ) {
		for ( const sb of [ 0, SCROLLBAR ] ) {
			for ( const vw of WIDTHS ) {
				const label = `${ name } ${ vw }px ${ scheme } scrollbar ${ sb }`;
				const m = await reading( TREE, name, vw, scheme, sb );
				const b = await reading( 'base', name, vw, scheme, sb );
				check( 'harness', m.innerWidth - m.clientWidth === sb, `${ label }: the page reports a ${ sb }px scrollbar`, `${ m.innerWidth - m.clientWidth }` );
				// The theme sizes main.alignfull with 100vw, so with a classic scrollbar main is wider than the visible page (the header and the base's own boxes sit 7.5px left of the visible centre). The wide column is centered in main like the header is.
				const wide = vw >= GRID_FROM;
				const content = Math.min( m.main.w - 2 * PAD, wide ? WIDE : READING );
				const left = m.main.l + PAD + ( m.main.w - 2 * PAD - content ) / 2;
				check( 'widths', m.content.w === content && m.content.l === left, `${ label }: content is ${ content } wide at ${ left }`, `${ m.content.w } at ${ m.content.l }` );
				const book = m.parts[ '.cr-book' ];
				const notes = m.parts[ '.cr-journal__notes' ];
				const rec = m.parts[ '.cr-record' ];
				const column = wide ? content - GAP - BOOK : content;
				check( 'widths', book.w === ( wide ? BOOK : content ), `${ label }: book is ${ wide ? BOOK : content } wide`, `${ book.w }` );
				check( 'widths', rec.w === column, `${ label }: record is ${ column } wide`, `${ rec.w }` );
				if ( notes ) {
					check( 'widths', notes.w === column, `${ label }: notes sheet is ${ column } wide`, `${ notes.w }` );
					check( 'widths', notes.w === rec.w, `${ label }: notes sheet is as wide as the record`, `${ notes.w } vs ${ rec.w }` );
				}
				check( 'title', m.h1.w === b.h1.w && m.h1.t === b.h1.t, `${ label }: the title keeps its base width ${ b.h1.w } and line breaks`, `${ m.h1.w } vs ${ b.h1.w }, top ${ m.h1.t } vs ${ b.h1.t }` );
				check( 'title', m.h1.l === book.l, `${ label }: title left edge is the book's left edge`, `${ m.h1.l } vs ${ book.l }` );
				const visual = Object.entries( m.parts ).sort( ( [ , x ], [ , y ] ) => x.t - y.t || x.l - y.l ).map( ( [ s ] ) => s );
				check( 'order', same( visual, m.domOrder ) && same( m.domOrder, [ '.cr-book', ...( notes ? [ '.cr-journal__notes' ] : [] ), '.cr-record', ...( m.parts[ '.cr-read__sample' ] ? [ '.cr-read__sample' ] : [] ) ] ), `${ label }: book, notes, record, sample in DOM and visual order`, `${ visual.join( ' ' ) } / ${ m.domOrder.join( ' ' ) }` );
				check( 'scroll', m.scrollWidth <= m.clientWidth, `${ label }: no horizontal scroll`, `${ m.scrollWidth } > ${ m.clientWidth }` );
				check( 'oneline', m.rowLines.every( ( n ) => n === 1 ) || vw < 390, `${ label }: every record row value is on one line`, m.rowLines.join( ',' ) );
				check( 'divider', m.meta.l === m.journal.l && m.meta.w === m.journal.w, `${ label }: the divider spans both columns of the spread (${ m.journal.w })`, `${ m.meta.l },${ m.meta.w } vs ${ m.journal.l },${ m.journal.w }` );
				if ( m.parts[ '.cr-read__sample' ] ) {
					const sample = m.parts[ '.cr-read__sample' ];
					check( 'sample', sample.l === m.journal.l && sample.w === m.journal.w && sample.t >= rec.t + rec.h, `${ label }: the sample takes both columns, below the record (${ m.journal.w })`, `${ sample.l },${ sample.w },${ sample.t } vs ${ m.journal.l },${ m.journal.w },${ rec.t + rec.h }` );
				} else {
					check( 'sample', name !== 'read-sample', `${ label }: the sample renders` );
				}
				check( 'painted', m.painted.every( ( [ , ok ] ) => ok ) && m.painted.length === m.domOrder.length, `${ label }: the book, notes, record and sample are shown at full opacity`, JSON.stringify( m.painted ) );
				check( 'sparkle', same( m.spark.position ? m.spark : { x: m.spark.x, y: m.spark.y }, b.spark.position ? b.spark : { x: b.spark.x, y: b.spark.y } ) && ( m.spark.position || m.spark.y === b.spark.y ), `${ label }: sparkle is where the base put it`, `${ JSON.stringify( m.spark ) } vs ${ JSON.stringify( b.spark ) }` );
				check( 'siblings', same( m.outside, b.outside ), `${ label }: every box outside the header and content keeps its base left edge and width` );
				check( 'siblings', same( m.before, b.before ), `${ label }: every box above the content keeps its base position` );
				if ( name === 'read-prose' ) {
					check( 'prose', m.prose.w === b.prose.w && m.prose.l === b.prose.l, `${ label }: prose after the spread keeps the reading width ${ b.prose.w } at ${ b.prose.l }`, `${ m.prose.w } at ${ m.prose.l } vs ${ b.prose.w } at ${ b.prose.l }` );
				}
				if ( name === 'read-finished' && scheme === 'light' ) {
					record[ `${ vw }/${ sb }` ] = { head: { content: [ m.content.l, m.content.w ], h1: m.h1.l, book: [ book.l, book.w ], notes: notes && [ notes.l, notes.w ], record: [ rec.l, rec.w ], divider: [ m.meta.l, m.meta.w ], sparkle: m.spark }, base: { content: [ b.content.l, b.content.w ], h1: b.h1.l, book: [ b.parts[ '.cr-book' ].l, b.parts[ '.cr-book' ].w ], notes: b.parts[ '.cr-journal__notes' ] && [ b.parts[ '.cr-journal__notes' ].l, b.parts[ '.cr-journal__notes' ].w ], record: [ b.parts[ '.cr-record' ].l, b.parts[ '.cr-record' ].w ], divider: [ b.meta.l, b.meta.w ], sparkle: b.spark } };
				}
			}
		}
	}
}

// 1b. A plain post a secondary loop renders inside a record Read keeps its base block and box.
{
	const headHtml = mutate( 'page', 'read-loop', readFileSync( join( FIXTURE, TREE, 'pages', 'read-loop.html' ), 'utf8' ) );
	const baseHtml = readFileSync( join( FIXTURE, 'base', 'pages', 'read-loop.html' ), 'utf8' );
	const inner = ( html ) => [ ...html.matchAll( /<div class="entry-content[^"]*"/g ) ].map( ( x ) => x[ 0 ] )[ 1 ];
	check( 'loop', inner( headHtml ) !== undefined && inner( headHtml ) === inner( baseHtml ) && ! inner( headHtml ).includes( 'cr-wide-column' ), 'read-loop: the secondary loop block keeps the base class list', `${ inner( headHtml ) }` );
	for ( const vw of WIDTHS ) {
		const m = await reading( TREE, 'read-loop', vw, 'light', 0 );
		const b = await reading( 'base', 'read-loop', vw, 'light', 0 );
		check( 'loop', m.loop && b.loop && m.loop.l === b.loop.l && m.loop.w === b.loop.w, `read-loop ${ vw }px: the secondary loop block keeps its base left edge and width`, `${ JSON.stringify( m.loop ) } vs ${ JSON.stringify( b.loop ) }` );
	}
}

// 1c. Right to left: the header's lines start where the grid starts, so the title and the book share a right edge.
for ( const vw of [ 1672, 1280, 1024, 768 ] ) {
	for ( const name of [ 'read-finished', 'read-reading' ] ) {
		const m = await reading( TREE, name, vw, 'light', 0, 'rtl' );
		const b = await reading( 'base', name, vw, 'light', 0, 'rtl' );
		check( 'rtl', m.dir === 'rtl', `${ name } ${ vw }px rtl: the page is right to left`, m.dir );
		check( 'rtl', m.h1.r === m.parts[ '.cr-book' ].r, `${ name } ${ vw }px rtl: title right edge is the book's right edge`, `${ m.h1.r } vs ${ m.parts[ '.cr-book' ].r }` );
		check( 'rtl', m.h1.w === b.h1.w, `${ name } ${ vw }px rtl: the title keeps its base width`, `${ m.h1.w } vs ${ b.h1.w }` );
	}
}

// 2. No horizontal scroll and the right edge inside the page padding, at 29 widths, with and without a classic scrollbar.
for ( const sb of [ 0, SCROLLBAR ] ) {
	for ( const vw of SWEEP ) {
		const m = await reading( TREE, 'read-finished', vw, 'light', sb );
		const layout = vw - sb;
		check( 'edge', m.right <= m.main.r - PAD + 0.001 && m.leftmost >= m.main.l + PAD - 0.001 && m.right <= layout, `read-finished ${ vw }px scrollbar ${ sb }: header, content and spread sit inside main's page padding and the visible page`, `${ m.leftmost }..${ m.right } in ${ m.main.l }..${ m.main.r }, visible ${ layout }` );
		check( 'edge', m.scrollWidth <= m.clientWidth, `read-finished ${ vw }px scrollbar ${ sb }: no horizontal scroll`, `${ m.scrollWidth } > ${ m.clientWidth }` );
		const wide = vw >= GRID_FROM;
		check( 'widths', m.content.w === Math.min( m.main.w - 2 * PAD, wide ? WIDE : READING ), `read-finished ${ vw }px scrollbar ${ sb }: content width`, `${ m.content.w }` );
	}
}

// 3. Locked and rowless reads, the other kinds, archives and non-single pages: the same HTML bytes and the same geometry as the base.
for ( const name of BASE_IDENTICAL ) {
	if ( MUTANT && ! ( MUTANTS[ MUTANT ]?.identical || [ MUTANTS[ MUTANT ]?.page ] ).includes( name ) ) {
		continue;
	}
	const headHtml = readFileSync( join( FIXTURE, TREE, 'pages', name + '.html' ), 'utf8' );
	const baseHtml = readFileSync( join( FIXTURE, 'base', 'pages', name + '.html' ), 'utf8' );
	const served = mutate( 'page', name, headHtml );
	const cls = /^read-/.test( name ) ? 'identical' : 'identical';
	check( cls, served === baseHtml, `${ name }: served HTML is byte-identical to the base` );
	for ( const scheme of SCHEMES ) {
		for ( const sb of [ 0, SCROLLBAR ] ) {
			for ( const vw of WIDTHS ) {
				const m = await reading( TREE, name, vw, scheme, sb );
				const b = await reading( 'base', name, vw, scheme, sb );
				check( cls, same( m.everything, b.everything ), `${ name } ${ vw }px ${ scheme } scrollbar ${ sb }: every box matches the base` );
			}
		}
	}
}

for ( const name of BYTES_ONLY ) {
	if ( MUTANT ) {
		break;
	}
	check( 'identical', readFileSync( join( FIXTURE, TREE, 'pages', name + '.html' ), 'utf8' ) === readFileSync( join( FIXTURE, 'base', 'pages', name + '.html' ), 'utf8' ), `${ name }: served bytes are identical to the base` );
}

await browser.close();
server.close();
const phase = `${ TREE }${ MUTANT ? ' + mutant ' + MUTANT : '' }${ LEGACY ? ' (legacy engine)' : '' }`;
if ( process.argv.includes( '--record' ) ) {
	console.log( JSON.stringify( record, null, 1 ) );
}
if ( failures.length ) {
	const byClass = {};
	for ( const f of failures ) {
		const c = /^FAIL \[(\w+)\]/.exec( f )[ 1 ];
		byClass[ c ] = ( byClass[ c ] || 0 ) + 1;
	}
	console.log( failures.slice( 0, 40 ).join( '\n' ) );
	console.log( 'CLASSES ' + JSON.stringify( byClass ) );
	console.log( `${ failures.length } of ${ checks } assertions failed on ${ phase }: ${ JSON.stringify( byClass ) }` );
	process.exit( 1 );
}
console.log( `PASS ${ checks } assertions on ${ phase }` );
