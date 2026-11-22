// `npm run test:repost-single-scale`: renders the Repost single (the taped clipping, the
// kinds the G-1 judge listed as single-desktop/clipping-scale) from the theme's checked-in
// sheets in headless Chrome and asserts the clipping's GEOMETRY against fixed values taken from
// the approved single mockup and decision contract (#255): from 48rem the clipping uses the
// site's wide size (theme.json layout.wideSize, 1100px) instead of the 720px reading column,
// below 48rem it keeps the constrained width, and nothing else on the page moves.
//
// The fixture is the real single structure (templates/single.html: constrained main >
// constrained post-content > the plugin's h-entry wrapper > the article) with the CSS WordPress
// 7.1.4 generated for this theme, copied verbatim from a saved page into
// fixtures/wp-generated.css (the `.is-layout-constrained > :where(...) { max-width: content-size }`
// cap, the wide cap, `.has-global-padding`), and NO `max-width: none` overrides: the cap that
// squeezed the clipping to 720px lives in that generated CSS, so a fixture without it passes
// a fix that fails on the site. Every width is measured twice, with no scrollbar and with a
// forced 15px classic scrollbar, so a fix built on vw units fails.
//
// Offline: a loopback server serves the sheets, the fonts and the fixture; every other
// request is blocked. Needs Chrome: CR_CHROME=/path/to/chrome, or Google Chrome / Chromium in
// a standard location. Without one the test prints a SKIP banner and exits 0, unless
// CR_REQUIRE_BROWSER=1, which makes a missing Chrome a failure. It is not wired into CI.
//
//   CR_REPOST_CSS=/path/to/cr-repost.css   render that sheet in place of the checked-in one
//   CR_REPOST_BASE_CSS=/path/to/cr-repost.css   the pre-fix sheet the "nothing else moves"
//                                          checks compare against (default: git show acef24d)
//   --table      print the measured geometry as a table instead of asserting
//   --mutants    run the test against sheets with one rule broken each; every mutant must fail
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const SELF = fileURLToPath( import.meta.url );
const TABLE = process.argv.includes( '--table' );
const MUTANTS = process.argv.includes( '--mutants' );

// Fixed expectations. theme.json layout.wideSize / contentSize, spacing md (the root padding
// the constrained main keeps on each side), spacing lg (the clipping's padding and column gap),
// the 48rem breakpoint the contract names for the two-column split, and the classic scrollbar.
const WIDE = 1100;
const CONTENT = 720;
const GUTTER = 16;
const PAD = 24;
const GAP = 24;
const BREAK = 768;
const SCROLLBAR = 15;
const ROW_GAP = 16;
const WIDTHS = [ 1672, 1280, 1024, 768, 767, 390, 320 ];
// A sweep through the range where the wide cap and the viewport trade places, 1024 to 1200, plus
// the widths around 1050 where a vw-based rule and a classic scrollbar disagree by 6.5px.
const SWEEP = [ 1448, 1200, 1180, 1150, 1148, 1130, 1110, 1100, 1080, 1060, 1050, 1040, 1030, 1000, 900, 800 ];
// Pages that carry the kind-repost body class but no Repost clipping: the theme emits the first
// kind on the body, so a password form and another kind's card can sit under it.
const NO_CLIPPING = [ 'password', 'like-under-repost', 'repost-under-like' ];
const SCHEMES = [ { name: 'light', theme: 'light', os: 'light' }, { name: 'dark', theme: 'dark', os: 'dark' } ];
const VARIANTS = [ 'image', 'no-image', 'url-only', 'note' ];
// Every other kind's single. Its h-entry and card carry that kind's classes; none of them may
// move when cr-repost.css changes.
const OTHER_KINDS = [ 'like', 'favorite', 'jam', 'checkin', 'read', 'comic', 'quote', 'bookmark' ];
const SHEETS = [ 'tokens', 'components', 'cr-post-kinds', 'cr-paper', 'cr-sticker', 'cr-like', 'cr-favorite', 'cr-jam', 'cr-read-shelf', 'cr-comic', 'cr-bookmark', 'cr-repost' ];

const chromePath = () => {
	const candidates = [
		process.env.CR_CHROME,
		'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
		'/usr/bin/google-chrome',
		'/usr/bin/google-chrome-stable',
		'/usr/bin/chromium',
		'/usr/bin/chromium-browser',
	].filter( Boolean );
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

const THEME = JSON.parse( readFileSync( resolve( ROOT, 'theme.json' ), 'utf8' ) );
const HEAD_CSS = process.env.CR_REPOST_CSS ? resolve( process.env.CR_REPOST_CSS ) : resolve( ROOT, 'assets/css/cr-repost.css' );

const baseCss = () => {
	if ( process.env.CR_REPOST_BASE_CSS ) {
		return readFileSync( resolve( process.env.CR_REPOST_BASE_CSS ) );
	}
	const shown = spawnSync( 'git', [ 'show', 'acef24d:assets/css/cr-repost.css' ], { cwd: ROOT, maxBuffer: 1 << 24 } );
	if ( shown.status !== 0 ) {
		throw new Error( 'the pre-fix sheet is unavailable: set CR_REPOST_BASE_CSS or fetch commit acef24d (' + shown.stderr + ')' );
	}
	return shown.stdout;
};

if ( MUTANTS ) {
	// A mutant that survives means an assertion is missing. Each rewrite below breaks one thing
	// the fix does; the sheet must differ from the real one, and the test must fail on it.
	const real = readFileSync( HEAD_CSS, 'utf8' );
	const block = /\/\* Repost single clipping width[\s\S]*?\n\}\n(?=\n\/\* The Stream card's ring)/.exec( real )?.[ 0 ];
	if ( ! block ) {
		console.log( 'FAIL mutants: the fix block ("Repost single clipping width") is not in cr-repost.css' );
		process.exit( 1 );
	}
	const dir = mkdtempSync( join( tmpdir(), 'repost-mutants-' ) );
	const HAS_CONTENT = ':has(> .pkiw-singular-entry .cr-repost-clip--single)';
	const HAS_ENTRY = ':has(.cr-repost-clip--single)';
	const mutate = {
		'fix removed (the base sheet)': ( css ) => css.replace( block, '' ),
		'content column stays at 80ch': ( css ) => css.replace( block, block.replace( 'max-inline-size: var(--wp--style--global--wide-size);', 'max-inline-size: var(--cr-measure-wide);' ) ),
		'h-entry wrapper keeps the 720px cap': ( css ) => css.replace( block, block.replace( /(\.pkiw-singular-entry:has\(\.cr-repost-clip--single\) \{\s*max-inline-size:\s*)none/, '$1var(--wp--style--global--content-size)' ) ),
		'breakpoint moves to 40rem': ( css ) => css.replace( block, block.replace( '(min-width: 48rem)', '(min-width: 40rem)' ) ),
		'no :has() scope (widens password forms and other kinds under a kind-repost body)': ( css ) => css.replace( block, block.replaceAll( HAS_CONTENT, '' ).replaceAll( HAS_ENTRY, '' ) ),
		'vw sizing (breaks under a classic scrollbar)': ( css ) => css.replace( block, block.replace( 'max-inline-size: var(--wp--style--global--wide-size);', 'max-inline-size: min(100vw, var(--wp--style--global--wide-size));' ).replace( /(\.pkiw-singular-entry:has\(\.cr-repost-clip--single\) \{\s*max-inline-size:\s*)none/, '$1min(100vw, var(--wp--style--global--wide-size))' ) ),
		'vw-centered margin (right in a viewport with no scrollbar, off by 7.5px with one)': ( css ) => css.replace( block, block.replace( 'max-inline-size: var(--wp--style--global--wide-size);', 'max-inline-size: var(--wp--style--global--wide-size);\n\t\tmargin-inline-start: max(0px, calc(50vw - var(--wp--style--global--wide-size) / 2)) !important;' ) ),
		'fixed 1100px width (overflows below 1148px)': ( css ) => css.replace( block, block.replace( 'max-inline-size: var(--wp--style--global--wide-size);', 'inline-size: var(--wp--style--global--wide-size); max-inline-size: none;' ) ),
		'wide size widened by 40px': ( css ) => css.replace( block, block.replaceAll( 'var(--wp--style--global--wide-size)', 'calc(var(--wp--style--global--wide-size) + 40px)' ) ),
		'sparkle counter-offset removed (sparkle follows the wide column)': ( css ) => css.replace( block, block.replace( /\n\t\/\* components\.css pins[\s\S]*?\n\t\}\n/, '\n' ) ),
		'sparkle moves 20px': ( css ) => css.replace( block, block.replace( 'var(--cr-repost-measure))) / 2)', 'var(--cr-repost-measure))) / 2 + 20px)' ) ),
		'newsletter band moves 24px (clipping margin grows)': ( css ) => css.replace( block, block.replace( /\n\}\n$/, '\n\n\tbody.single-post.kind-repost .single-post__content .pk-card.k-repost.cr-repost-clip--single {\n\t\tmargin-block-end: calc(var(--cr-space-xl) + 24px);\n\t}\n}\n' ) ),
		'caption gap left to the stretched rows': ( css ) => css.replace( block, block.replace( 'grid-template-rows: auto 1fr;', 'grid-template-rows: auto;' ) ),
	};
	const attempt = async ( name, rewrite, index ) => {
		const mutated = rewrite( real );
		if ( name !== 'fix removed (the base sheet)' && mutated === real ) {
			return `ERROR     ${ name }: the rewrite changed nothing, so it proves nothing`;
		}
		const path = join( dir, `cr-repost-${ index }.css` );
		writeFileSync( path, mutated );
		// A run that dies before asserting anything (Chrome failed to start) is not a kill: retry once,
		// then report it as an error.
		const once = () => new Promise( ( done ) => {
			let out = '';
			const child = spawn( process.execPath, [ SELF ], { cwd: ROOT, env: { ...process.env, CR_REPOST_CSS: path } } );
			child.stdout.on( 'data', ( chunk ) => ( out += chunk ) );
			child.on( 'close', ( status ) => done( { status, out, failing: ( out.match( /^FAIL /gm ) || [] ).length, total: /(\d+) assertions, (\d+) failed/.test( out ) } ) );
		} );
		let run = await once();
		if ( run.status !== 0 && ! run.total ) {
			run = await once();
		}
		if ( run.status === 0 ) {
			return `SURVIVED  ${ name }`;
		}
		return run.total ? `KILLED    ${ name }  (${ run.failing } failed assertions)` : `ERROR     ${ name }: the run died before asserting`;
	};
	const names = Object.entries( mutate );
	const results = new Array( names.length );
	let next = 0;
	await Promise.all( Array.from( { length: 4 }, async () => {
		while ( next < names.length ) {
			const index = next++;
			results[ index ] = await attempt( names[ index ][ 0 ], names[ index ][ 1 ], index );
		}
	} ) );
	results.forEach( ( line ) => console.log( line ) );
	const survived = results.filter( ( line ) => ! line.startsWith( 'KILLED' ) ).length;
	rmSync( dir, { recursive: true, force: true } );
	console.log( survived ? `${ survived } mutant(s) survived or did not apply` : 'all mutants killed' );
	process.exit( survived ? 1 : 0 );
}

const executablePath = chromePath();
if ( ! executablePath ) {
	const banner = '*'.repeat( 72 );
	console.log( `${ banner }\nSKIP repost-single-clipping-scale: no Chrome found. Set CR_CHROME or install Google Chrome.\nNothing was rendered or asserted. ${ process.env.CR_REQUIRE_BROWSER ? 'CR_REQUIRE_BROWSER=1, so this fails.' : 'Exiting 0.' }\n${ banner }` );
	process.exit( process.env.CR_REQUIRE_BROWSER ? 1 : 0 );
}

// ---- WordPress's generated CSS, verbatim from a saved page of this theme (not reconstructed) ----
const WP_CSS = 'body{margin:0;}\n' + readFileSync( resolve( ROOT, 'tests/browser/fixtures/wp-generated.css' ), 'utf8' );

const fontFaces = ( THEME.settings.typography.fontFamilies || [] )
	.flatMap( ( family ) => family.fontFace || [] )
	.map( ( face ) => `@font-face{font-family:"${ face.fontFamily }";font-weight:${ face.fontWeight };font-style:${ face.fontStyle };font-display:block;src:url(/fonts/${ face.src[ 0 ].split( '/' ).pop() }) format("woff2");}` )
	.join( '' );

const CITE = {
	image: '<div data-cr-cover-fallback class="pk-embed pk-embed--photo"><img class="u-photo" alt="" width="640" height="480" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"></div>',
	text: '',
};
const caption = ( withAuthor ) => `<div class="pk-caption"><p class="pk-sub cr-repost-clip__from">From blog.example.net</p><h1 class="pk-title p-name"><a class="u-url" href="/never-visited">Paper Trails for the Web</a></h1>${ withAuthor ? '<p class="pk-sub">by <span class="p-author h-card"><span class="p-name">Jo Example</span></span></p>' : '' }</div>`;
const article = ( { image, author, summary } ) => `<article class="pk-card k-repost h-cite u-repost-of wp-block-post-kinds-indieweb-repost-card cr-repost-clip cr-repost-clip--single cr-paper-sheet cr-paper-sheet--torn"><div class="pk-body">${ image ? CITE.image : '' }${ caption( author ) }${ summary ? '<p class="pk-note p-content">A short summary of paper trails for the web.</p>' : '' }</div></article>`;
const HIDDEN = '<a class="u-url" href="/permalink" hidden></a><time class="dt-published" datetime="2026-08-28T10:00:00-04:00" hidden></time>';

const contentFor = ( variant ) => {
	switch ( variant ) {
		case 'image':
			return `<div class="h-entry kind-repost pkiw-singular-entry">${ article( { image: true, author: true, summary: true } ) }${ HIDDEN }</div>`;
		case 'no-image':
			return `<div class="h-entry kind-repost pkiw-singular-entry">${ article( { image: false, author: true, summary: false } ) }${ HIDDEN }</div>`;
		case 'url-only':
			// A Micropub post: the plugin wraps the card in its own constrained group.
			return `<div class="h-entry kind-repost pkiw-singular-entry"><div class="wp-block-group pkiw-entry has-global-padding is-layout-constrained wp-block-group-is-layout-constrained">${ article( { image: false, author: false, summary: false } ) }</div>${ HIDDEN }</div>`;
		case 'note':
			return `<div class="h-entry kind-repost pkiw-singular-entry">${ article( { image: true, author: true, summary: true } ) }<p class="wp-block-paragraph">Saving this because it names what I want from my own site. A second sentence keeps the note on more than one line at the narrow widths, so its measure is visible.</p>${ HIDDEN }</div>`;
		default:
			throw new Error( 'unknown variant ' + variant );
	}
};

const otherContent = ( kind ) => `<div class="h-entry kind-${ kind } pkiw-singular-entry"><article class="pk-card k-${ kind } wp-block-post-kinds-indieweb-${ kind }-card cr-${ kind }-clipping"><div class="pk-body"><div class="pk-media"><img alt="" width="320" height="240" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"></div><div class="pk-caption"><h2 class="pk-title">A ${ kind } entry</h2><p class="pk-sub">by Sample</p></div></div></article><p class="wp-block-paragraph">A note after the card, to give the wrapper something to lay out.</p>${ HIDDEN }</div>`;

const MAIN = ( content, kind ) => `<div class="wp-site-blocks"><main id="wp--skip-link--target" class="wp-block-group alignfull single-post has-main-accent-background-color has-background has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" style="padding-top:var(--wp--preset--spacing--xl);padding-bottom:var(--wp--preset--spacing--3xl)"><div class="wp-block-group alignwide single-post__header has-global-padding is-layout-constrained wp-block-group-is-layout-constrained"><div class="wp-block-group single-post__lede-meta has-small-font-size is-layout-flex"><div class="single-post__date cr-dt-published wp-block-post-date"><time class="dt-published" datetime="2026-08-28T10:00:00-04:00">August 28, 2026</time></div><div class="taxonomy-kind cr-chip single-post__kind wp-block-post-terms has-xs-font-size"><a href="/kind/${ kind }/" rel="tag">${ kind }</a></div></div></div><div class="entry-content align single-post__content wp-block-post-content has-global-padding is-layout-constrained wp-block-post-content-is-layout-constrained">${ content }</div><div class="wp-block-group alignfull cr-newsletter-banner has-global-padding is-layout-constrained" style="padding:2rem 1rem"><p>Newsletter band</p></div></main><div style="block-size:1600px"></div></div>`;

const PASSWORD = '<form action="/wp-login.php?action=postpass" class="post-password-form" method="post"><p>This content is password protected. To view it please enter your password below:</p><p><label for="pwbox-1">Password: <input name="post_password" id="pwbox-1" type="password" spellcheck="false" size="20"></label> <input type="submit" name="Submit" value="Enter"></p></form>';
const bodyClass = ( kind, variant ) => {
	const kinds = { 'like-under-repost': 'kind-repost kind-like', 'repost-under-like': 'kind-like kind-repost', password: 'kind-repost' }[ variant ] ?? `kind-${ kind }`;
	return `single single-post postid-1 ${ kinds }${ kind === 'comic' ? ' cr-comic-single' : '' }`;
};

const sheetPath = ( name, base ) => ( name === 'cr-repost' ? ( base ? null : HEAD_CSS ) : resolve( ROOT, 'assets/css', name + '.css' ) );
const server = createServer( ( request, response ) => {
	const url = new URL( request.url, 'http://127.0.0.1' );
	const sheet = /^\/css\/([\w-]+)\.css$/.exec( url.pathname )?.[ 1 ];
	if ( sheet === 'cr-repost-base' ) {
		response.setHeader( 'Content-Type', 'text/css' );
		response.end( baseCss() );
		return;
	}
	if ( sheet && SHEETS.includes( sheet ) && existsSync( sheetPath( sheet ) ) ) {
		response.setHeader( 'Content-Type', 'text/css' );
		response.end( readFileSync( sheetPath( sheet ) ) );
		return;
	}
	const font = /^\/fonts\/([\w.-]+\.woff2)$/.exec( url.pathname )?.[ 1 ];
	if ( font && existsSync( resolve( ROOT, 'assets/fonts', font ) ) ) {
		response.setHeader( 'Content-Type', 'font/woff2' );
		response.end( readFileSync( resolve( ROOT, 'assets/fonts', font ) ) );
		return;
	}
	if ( url.pathname === '/fixture' ) {
		const { kind, variant, theme, scrollbar, base } = Object.fromEntries( url.searchParams );
		const content = variant === 'password' ? PASSWORD : ( NO_CLIPPING.includes( variant ) ? otherContent( 'like' ) : ( kind === 'repost' ? contentFor( variant ) : otherContent( kind ) ) );
		const sheets = SHEETS.map( ( name ) => `<link rel="stylesheet" href="/css/${ name === 'cr-repost' && base === '1' ? 'cr-repost-base' : name }.css">` ).join( '' );
		response.setHeader( 'Content-Type', 'text/html' );
		response.setHeader( 'Content-Security-Policy', "default-src 'self' data:; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'" );
		response.end( `<!doctype html><html lang="en" data-theme="${ theme }"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Repost single scale</title><style>${ fontFaces }</style><style>${ WP_CSS }</style>${ sheets }<style>html::-webkit-scrollbar{width:${ scrollbar }px;height:${ scrollbar }px}html::-webkit-scrollbar-thumb{background:#888}</style></head><body class="${ bodyClass( kind, variant ) }">${ MAIN( content, kind ) }</body></html>` );
		return;
	}
	response.writeHead( 404 ).end();
} );
await new Promise( ( done ) => server.listen( 0, '127.0.0.1', done ) );
const origin = `http://127.0.0.1:${ server.address().port }`;

const failures = [];
let cases = 0;
const check = ( name, actual, expected ) => {
	cases++;
	const ok = JSON.stringify( actual ) === JSON.stringify( expected );
	console.log( ok ? 'PASS' : 'FAIL', name, ok ? '' : `expected ${ JSON.stringify( expected ) } got ${ JSON.stringify( actual ) }` );
	if ( ! ok ) {
		failures.push( name );
	}
};

const measureScript = () => {
	const rect = ( el ) => {
		if ( ! el ) {
			return null;
		}
		const box = el.getBoundingClientRect();
		return { left: box.left, right: box.right, width: box.width, top: box.top, height: box.height };
	};
	const style = ( el, ...props ) => ( el ? Object.fromEntries( props.map( ( prop ) => [ prop, getComputedStyle( el )[ prop ] ] ) ) : null );
	const q = ( selector, root = document ) => root.querySelector( selector );
	const content = q( '.single-post__content' );
	const entry = q( '.pkiw-singular-entry' );
	const card = q( 'article.pk-card' );
	const pkBody = card ? q( '.pk-body', card ) : null;
	const note = entry ? [ ...entry.children ].find( ( child ) => child.matches( 'p.wp-block-paragraph' ) ) : null;
	const all = [ ...document.querySelectorAll( 'main *' ) ].map( ( el ) => {
		const box = el.getBoundingClientRect();
		return [ el.tagName, el.className && el.className.baseVal === undefined ? el.className : '', box.left, box.top, box.width, box.height ];
	} );
	// The shared sparkle is the content block's ::before. A positioned pseudo-element reports used
	// left/right/top, so its box can be placed exactly from the content block's.
	const spark = content ? getComputedStyle( content, '::before' ) : null;
	const box = content ? content.getBoundingClientRect() : null;
	const sparkle = spark && spark.content !== 'none' ? { glyph: spark.content, left: box.left + parseFloat( spark.left ), right: box.right - parseFloat( spark.right ), top: box.top + parseFloat( spark.top ), width: spark.width } : null;
	const caption = card ? q( '.pk-caption', card ) : null;
	const summary = card ? q( '.pk-note', card ) : null;
	return {
		sparkle,
		captionGap: caption && summary ? summary.getBoundingClientRect().top - caption.getBoundingClientRect().bottom : null,
		lede: rect( q( '.single-post__lede-meta' ) ),
		date: rect( q( '.single-post__date' ) ),
		newsletter: rect( q( '.cr-newsletter-banner' ) ),
		fonts: document.fonts.check( '16px Barlow' ),
		innerWidth: window.innerWidth,
		clientWidth: document.documentElement.clientWidth,
		scrollbar: window.innerWidth - document.documentElement.clientWidth,
		header: rect( q( '.single-post__header' ) ),
		content: rect( content ),
		entry: rect( entry ),
		card: rect( card ),
		note: rect( note ),
		body: rect( pkBody ),
		contentStyle: style( content, 'maxInlineSize', 'containerType', 'boxSizing' ),
		entryStyle: style( entry, 'maxInlineSize', 'containerType' ),
		cardStyle: style( card, 'maxInlineSize', 'inlineSize', 'containerType', 'paddingLeft', 'paddingRight', 'columnGap' ),
		noteStyle: style( note, 'maxInlineSize' ),
		columns: pkBody ? getComputedStyle( pkBody ).gridTemplateColumns : null,
		all,
	};
};

const context = await chromium.launchPersistentContext( '', {
	executablePath,
	viewport: { width: 1280, height: 900 },
	deviceScaleFactor: 1,
	serviceWorkers: 'block',
	ignoreDefaultArgs: [ '--hide-scrollbars' ],
	args: [ '--proxy-server=http://127.0.0.1:1', '--proxy-bypass-list=127.0.0.1', '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1', '--disable-background-networking', '--disable-sync', '--force-color-profile=srgb' ],
} );
const measured = new Map();
try {
	await context.route( '**/*', ( route ) => ( route.request().url().startsWith( origin + '/' ) ? route.continue() : route.abort( 'blockedbyclient' ) ) );
	const page = context.pages()[ 0 ] ?? await context.newPage();
	const measure = async ( { kind, variant = 'image', scheme, width, scrollbar, base = false } ) => {
		const key = [ kind, variant, scheme.name, width, scrollbar, base ? 'base' : 'head' ].join( '/' );
		if ( ! measured.has( key ) ) {
			await page.setViewportSize( { width, height: 900 } );
			await page.emulateMedia( { colorScheme: scheme.os, forcedColors: 'none', reducedMotion: 'reduce' } );
			await page.goto( `${ origin }/fixture?kind=${ kind }&variant=${ variant }&theme=${ scheme.theme }&scrollbar=${ scrollbar }&base=${ base ? 1 : 0 }`, { waitUntil: 'load' } );
			await page.evaluate( () => document.fonts.ready );
			measured.set( key, await page.evaluate( measureScript ) );
		}
		return measured.get( key );
	};

	if ( TABLE ) {
		console.log( '| viewport | scrollbar | sheet | clipping left | right | width | sparkle left | sparkle right | sparkle top | caption gap | lede left | date left | newsletter top |\n| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |' );
		for ( const width of [ ...WIDTHS, ...SWEEP.filter( ( w ) => [ 1448, 1100, 1050 ].includes( w ) ) ] ) {
			for ( const scrollbar of [ 0, SCROLLBAR ] ) {
				for ( const base of [ true, false ] ) {
					const m = await measure( { kind: 'repost', variant: 'image', scheme: SCHEMES[ 0 ], width, scrollbar, base } );
					console.log( `| ${ width } | ${ scrollbar } | ${ base ? 'base' : 'head' } | ${ m.card.left } | ${ m.card.right } | ${ m.card.width } | ${ m.sparkle?.left } | ${ m.sparkle?.right } | ${ m.sparkle?.top } | ${ m.captionGap } | ${ m.lede.left } | ${ m.date.left } | ${ m.newsletter.top } |` );
				}
			}
		}
		console.log( '\n| page under a kind-repost body | viewport | scrollbar | sheet | content width | content left |\n| --- | --- | --- | --- | --- | --- |' );
		for ( const variant of NO_CLIPPING ) {
			for ( const width of [ 1280, 1448 ] ) {
				for ( const base of [ true, false ] ) {
					const m = await measure( { kind: 'repost', variant, scheme: SCHEMES[ 0 ], width, scrollbar: 0, base } );
					console.log( `| ${ variant } | ${ width } | 0 | ${ base ? 'base' : 'head' } | ${ m.content.width } | ${ m.content.left } |` );
				}
			}
		}
		process.exit( 0 );
	}

	// Chrome lays out in 1/64px units. The sparkle's offset is half of (1100 - 80ch), and 80ch is
	// 768.390625px, so its half is not a whole unit and the sparkle can land one unit (0.0156px) off
	// the base. That is the limit of "exact"; any real move is at least a pixel.
	const UNIT = 0.02;
	const near = ( a, b ) => typeof a === 'number' && typeof b === 'number' && Math.abs( a - b ) <= UNIT;

	// ---- Repost single: fixed expectations from the contract --------------------------------
	const cell = async ( variant, scheme, width, scrollbar ) => {
		const client = width - scrollbar;
		// components.css forces the full-bleed main to 100vw (an existing theme rule), so the
		// clipping's room is the viewport minus the gutters, centered on the client area.
		const inner = width - 2 * GUTTER;
		const wide = width >= BREAK;
		const m = await measure( { kind: 'repost', variant, scheme, width, scrollbar } );
		const base = await measure( { kind: 'repost', variant, scheme, width, scrollbar, base: true } );
		const id = `${ variant }/${ scheme.name }/${ width }px/scrollbar-${ scrollbar }`;
		const expectWidth = Math.min( inner, wide ? WIDE : CONTENT );
		const expectLeft = ( client - expectWidth ) / 2;

		check( `${ id }: fixture is sound (Barlow loaded, scrollbar ${ scrollbar }px)`, [ m.fonts, m.scrollbar, m.clientWidth ], [ true, scrollbar, client ] );
		check( `${ id }: clipping width`, m.card.width, expectWidth );
		check( `${ id }: clipping left edge, centered in the client area`, m.card.left, expectLeft );
		check( `${ id }: clipping right edge and gutters inside the client area`, [ m.card.right, m.card.right <= client, m.card.left >= 0 ], [ expectLeft + expectWidth, true, true ] );
		check( `${ id }: h-entry wrapper and content column ${ wide ? "are the clipping's width" : 'keep their constrained widths' }`, [ m.entry.width, m.content.width ], wide ? [ expectWidth, expectWidth ] : [ Math.min( inner, CONTENT ), inner ] );
		check( `${ id }: no layout container is introduced`, [ m.contentStyle.containerType, m.entryStyle.containerType, m.cardStyle.containerType ], [ 'normal', 'normal', 'normal' ] );
		check( `${ id }: clipping padding stays on the lg token`, [ m.cardStyle.paddingLeft, m.cardStyle.paddingRight ], [ `${ PAD }px`, `${ PAD }px` ] );

		if ( variant === 'image' || variant === 'note' ) {
			const text = wide ? ( expectWidth - 2 * PAD - GAP ) / 2 : expectWidth - 2 * PAD;
			check( `${ id }: pk-body columns ${ wide ? 'split in two equal tracks from 48rem' : 'stack in one track below 48rem' }`, m.columns, wide ? `${ text }px ${ text }px` : `${ text }px` );
			check( `${ id }: caption sits one row gap (${ ROW_GAP }px) above the description, whatever the image height`, m.captionGap, ROW_GAP );
		} else {
			check( `${ id }: pk-body is one track`, m.columns, `${ expectWidth - 2 * PAD }px` );
		}

		if ( wide ) {
			check( `${ id }: computed caps at 48rem and up (content ${ WIDE }px, h-entry none, clipping 100%)`, [ m.contentStyle.maxInlineSize, m.entryStyle.maxInlineSize, m.cardStyle.maxInlineSize ], [ `${ WIDE }px`, 'none', '100%' ] );
		}
		if ( variant === 'note' ) {
			check( `${ id }: Courtney's note keeps the width it had and starts at the clipping's left edge`, [ m.note.width, m.note.left ], [ base.note.width, expectLeft ] );
		}

		// Nothing but the clipping moves: the lede, date, kind chip and header are exactly where they
		// were, the band below keeps its left edge, width and height, and its top moves by exactly
		// the clipping's change in height.
		check( `${ id }: header, lede row and date do not move`, [ m.header, m.lede, m.date ], [ base.header, base.lede, base.date ] );
		check( `${ id }: newsletter band keeps its left, width and height`, [ m.newsletter.left, m.newsletter.width, m.newsletter.height ], [ base.newsletter.left, base.newsletter.width, base.newsletter.height ] );
		check( `${ id }: newsletter band moves by exactly the clipping's height change (${ m.card.height - base.card.height }px)`, m.newsletter.top - base.newsletter.top, m.card.height - base.card.height );

		// The shared sparkle (components.css, content::before) stays exactly where the base put it. It
		// only exists from 1100px up, so the test also proves it was measured there.
		if ( width >= 1100 ) {
			check( `${ id }: sparkle is present and measured`, [ m.sparkle?.glyph, base.sparkle?.glyph ], [ '"\u273A"', '"\u273A"' ] );
		}
		check( `${ id }: sparkle keeps its base box (left, right, top, size)`, [ near( m.sparkle?.left, base.sparkle?.left ) || m.sparkle === base.sparkle, near( m.sparkle?.right, base.sparkle?.right ) || m.sparkle === base.sparkle, m.sparkle?.top === base.sparkle?.top, m.sparkle?.width === base.sparkle?.width ], [ true, true, true, true ] );
	};
	for ( const scheme of SCHEMES ) {
		for ( const scrollbar of [ 0, SCROLLBAR ] ) {
			for ( const width of WIDTHS ) {
				for ( const variant of VARIANTS ) {
					await cell( variant, scheme, width, scrollbar );
				}
			}
		}
	}
	for ( const scrollbar of [ 0, SCROLLBAR ] ) {
		for ( const width of SWEEP ) {
			for ( const variant of [ 'image', 'url-only' ] ) {
				await cell( variant, SCHEMES[ 0 ], width, scrollbar );
			}
		}
	}

	// ---- Pages under a kind-repost body that hold no Repost clipping stay as they were ---------
	for ( const variant of NO_CLIPPING ) {
		for ( const scrollbar of [ 0, SCROLLBAR ] ) {
			for ( const width of [ ...WIDTHS, 1448, 1100, 1050 ] ) {
				const scheme = SCHEMES[ 0 ];
				const head = await measure( { kind: 'repost', variant, scheme, width, scrollbar } );
				const base = await measure( { kind: 'repost', variant, scheme, width, scrollbar, base: true } );
				check( `${ variant } under a kind-repost body: every element in main has the geometry it had before (${ width }px/scrollbar-${ scrollbar }, ${ head.all.length } elements)`, head.all, base.all );
				check( `${ variant } under a kind-repost body: content column and sparkle are the base's (${ width }px/scrollbar-${ scrollbar })`, [ head.content, head.sparkle ], [ base.content, base.sparkle ] );
			}
		}
		const wide = await measure( { kind: 'repost', variant, scheme: SCHEMES[ 0 ], width: 1280, scrollbar: 0 } );
		check( `${ variant } under a kind-repost body: at 1280px the content column is the base's, not ${ WIDE }px`, wide.content.width < WIDE, true );
	}

	// ---- Every other kind's single is identical to base ---------------------------------------
	for ( const kind of OTHER_KINDS ) {
		for ( const scrollbar of [ 0, SCROLLBAR ] ) {
			for ( const width of WIDTHS ) {
				const scheme = SCHEMES[ 0 ];
				const head = await measure( { kind, scheme, width, scrollbar } );
				const base = await measure( { kind, scheme, width, scrollbar, base: true } );
				check( `${ kind } single: every element in main has the geometry it had before (${ width }px/scrollbar-${ scrollbar }, ${ head.all.length } elements)`, head.all, base.all );
			}
		}
	}
} finally {
	await context.close();
	server.close();
}

// ---- The sheet itself: no viewport units in the single section ------------------------------
const sheet = readFileSync( HEAD_CSS, 'utf8' );
const single = sheet.slice( sheet.indexOf( 'Single: one taped clipping' ), sheet.indexOf( 'Forced colors' ) );
check( 'cr-repost.css single section uses no viewport units', /\d\s*(vw|vi|vb|vh|svw|lvw|dvw|cqw|cqi)\b/i.test( single ), false );
check( 'cr-repost.css stays inside the 30 KiB kind-sheet budget', readFileSync( HEAD_CSS ).length <= 30 * 1024, true );

console.log( `\n${ cases } assertions, ${ failures.length } failed` );
process.exit( failures.length ? 1 : 0 );
