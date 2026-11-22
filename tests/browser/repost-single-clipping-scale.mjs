// `npm run test:repost-single-scale`: renders the Repost single (the taped clipping, the
// kinds the G-1 judge listed as single-desktop/clipping-scale) from the theme's checked-in
// sheets in headless Chrome and asserts the clipping's GEOMETRY against fixed values taken from
// the approved single mockup and decision contract (#255): from 48rem the clipping uses the
// site's wide size (theme.json layout.wideSize, 1100px) instead of the 720px reading column,
// below 48rem it keeps the constrained width, and nothing else on the page moves.
//
// The fixture is the real single structure (templates/single.html: constrained main >
// constrained post-content > the plugin's h-entry wrapper > the article) with WordPress's own
// generated layout CSS (the `.is-layout-constrained > :where(...) { max-width: content-size }`
// cap, the wide cap, `.has-global-padding`) and NO `max-width: none` overrides: the cap that
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
import { spawnSync } from 'node:child_process';
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
const WIDTHS = [ 1672, 1280, 1024, 768, 767, 390, 320 ];
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
	const mutate = {
		'fix removed (the base sheet)': ( css ) => css.replace( block, '' ),
		'content column stays at 80ch': ( css ) => css.replace( /(\.single-post__content\s*\{\s*max-inline-size:\s*)var\(--wp--style--global--wide-size\)/, '$1var(--cr-measure-wide)' ),
		'h-entry wrapper keeps the 720px cap': ( css ) => css.replace( /(\.pkiw-singular-entry\s*\{\s*max-inline-size:\s*)none/, '$1var(--wp--style--global--content-size)' ),
		'breakpoint moves to 40rem': ( css ) => css.replace( block, block.replace( '(min-width: 48rem)', '(min-width: 40rem)' ) ),
		'selector loses .kind-repost (hits every kind)': ( css ) => css.replace( block, block.replaceAll( 'body.single-post.kind-repost', 'body.single-post' ) ),
		'vw sizing (breaks under a classic scrollbar)': ( css ) => css.replace( block, block.replace( /max-inline-size:\s*var\(--wp--style--global--wide-size\);/, 'max-inline-size: min(100vw, var(--wp--style--global--wide-size));' ).replace( /(\.pkiw-singular-entry\s*\{\s*max-inline-size:\s*)none/, '$1min(100vw, var(--wp--style--global--wide-size))' ) ),
		'fixed 1100px width (overflows below 1148px)': ( css ) => css.replace( block, block.replace( /(\.single-post__content\s*\{\s*)max-inline-size:\s*var\(--wp--style--global--wide-size\);/, '$1inline-size: var(--wp--style--global--wide-size); max-inline-size: none;' ) ),
		'wide size widened by 40px': ( css ) => css.replace( block, block.replaceAll( 'var(--wp--style--global--wide-size)', 'calc(var(--wp--style--global--wide-size) + 40px)' ) ),
	};
	let survived = 0;
	for ( const [ name, rewrite ] of Object.entries( mutate ) ) {
		const mutated = rewrite( real );
		if ( name !== 'fix removed (the base sheet)' && mutated === real ) {
			console.log( `ERROR  ${ name }: the rewrite changed nothing, so it proves nothing` );
			survived++;
			continue;
		}
		const path = join( dir, 'cr-repost.css' );
		writeFileSync( path, mutated );
		const run = spawnSync( process.execPath, [ SELF ], { cwd: ROOT, encoding: 'utf8', env: { ...process.env, CR_REPOST_CSS: path }, maxBuffer: 1 << 26 } );
		const failing = ( run.stdout.match( /^FAIL /gm ) || [] ).length;
		if ( run.status === 0 ) {
			survived++;
			console.log( `SURVIVED  ${ name }` );
		} else {
			console.log( `KILLED    ${ name }  (${ failing } failed assertions)` );
		}
	}
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

// ---- WordPress's generated CSS, as the core emits it (wp-includes/class-wp-theme-json.php for
// root padding, wp-includes/block-supports/layout.php for the layout rules; WordPress 7.1.4) ----
const spacing = ( THEME.settings.spacing.spacingSizes || [] ).map( ( size ) => `--wp--preset--spacing--${ size.slug }:${ size.size };` ).join( '' );
const WP_CSS = `
:root{${ spacing }--wp--style--global--content-size:${ THEME.settings.layout.contentSize };--wp--style--global--wide-size:${ THEME.settings.layout.wideSize };--wp--style--root--padding-top:0;--wp--style--root--padding-right:var(--wp--preset--spacing--md);--wp--style--root--padding-bottom:0;--wp--style--root--padding-left:var(--wp--preset--spacing--md);--wp--style--block-gap:var(--wp--preset--spacing--lg);}
body{margin:0;}
.has-global-padding{padding-right:var(--wp--style--root--padding-right);padding-left:var(--wp--style--root--padding-left);}
.has-global-padding > .alignfull{margin-right:calc(var(--wp--style--root--padding-right) * -1);margin-left:calc(var(--wp--style--root--padding-left) * -1);}
.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)){padding-right:0;padding-left:0;}
.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)) > .alignfull{margin-left:0;margin-right:0;}
.is-layout-constrained > .alignleft{float:left;margin-inline-start:0;margin-inline-end:2em;}
.is-layout-constrained > .alignright{float:right;margin-inline-start:2em;margin-inline-end:0;}
.is-layout-constrained > .aligncenter{margin-left:auto !important;margin-right:auto !important;}
.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width:var(--wp--style--global--content-size);margin-left:auto !important;margin-right:auto !important;}
.is-layout-constrained > .alignwide{max-width:var(--wp--style--global--wide-size);}
:root :where(.is-layout-constrained) > *{margin-block-start:0;margin-block-end:0;}
.is-layout-flex{display:flex;flex-wrap:wrap;align-items:center;gap:var(--wp--preset--spacing--sm);}
`;

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

const bodyClass = ( kind ) => `single single-post postid-1 kind-${ kind }${ kind === 'comic' ? ' cr-comic-single' : '' }`;

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
		const content = kind === 'repost' ? contentFor( variant ) : otherContent( kind );
		const sheets = SHEETS.map( ( name ) => `<link rel="stylesheet" href="/css/${ name === 'cr-repost' && base === '1' ? 'cr-repost-base' : name }.css">` ).join( '' );
		response.setHeader( 'Content-Type', 'text/html' );
		response.setHeader( 'Content-Security-Policy', "default-src 'self' data:; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'" );
		response.end( `<!doctype html><html lang="en" data-theme="${ theme }"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Repost single scale</title><style>${ fontFaces }</style><style>${ WP_CSS }</style>${ sheets }<style>html::-webkit-scrollbar{width:${ scrollbar }px;height:${ scrollbar }px}html::-webkit-scrollbar-thumb{background:#888}</style></head><body class="${ bodyClass( kind ) }">${ MAIN( content, kind ) }</body></html>` );
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
	return {
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
		console.log( '| viewport | scrollbar | sheet | content | h-entry | article left | article right | article width | pk-body columns |\n| --- | --- | --- | --- | --- | --- | --- | --- | --- |' );
		for ( const width of WIDTHS ) {
			for ( const scrollbar of [ 0, SCROLLBAR ] ) {
				for ( const base of [ true, false ] ) {
					const m = await measure( { kind: 'repost', scheme: SCHEMES[ 0 ], width, scrollbar, base } );
					console.log( `| ${ width } | ${ scrollbar } | ${ base ? 'base' : 'head' } | ${ m.content.width } | ${ m.entry.width } | ${ m.card.left } | ${ m.card.right } | ${ m.card.width } | ${ m.columns } |` );
				}
			}
		}
		process.exit( 0 );
	}

	// ---- Repost single: fixed expectations from the contract --------------------------------
	for ( const scheme of SCHEMES ) {
		for ( const scrollbar of [ 0, SCROLLBAR ] ) {
			for ( const width of WIDTHS ) {
				const client = width - scrollbar;
				// components.css forces the full-bleed main to 100vw (an existing theme rule), so the
				// clipping's room is the viewport minus the gutters, centered on the client area.
				const inner = width - 2 * GUTTER;
				const wide = width >= BREAK;
				for ( const variant of VARIANTS ) {
					const m = await measure( { kind: 'repost', variant, scheme, width, scrollbar } );
					const id = `${ variant }/${ scheme.name }/${ width }px/scrollbar-${ scrollbar }`;
					const expectWidth = Math.min( inner, wide ? WIDE : CONTENT );
					const expectLeft = ( client - expectWidth ) / 2;

					check( `${ id }: fixture is sound (Barlow loaded, scrollbar ${ scrollbar }px)`, [ m.fonts, m.scrollbar, m.clientWidth ], [ true, scrollbar, client ] );
					check( `${ id }: clipping width`, m.card.width, expectWidth );
					check( `${ id }: clipping left edge, centered in the client area`, m.card.left, expectLeft );
					check( `${ id }: clipping right edge stays inside the client area`, [ m.card.right, m.card.right <= client ], [ expectLeft + expectWidth, true ] );
					check( `${ id }: h-entry wrapper and content column ${ wide ? "are the clipping's width" : 'keep their constrained widths' }`, [ m.entry.width, m.content.width ], wide ? [ expectWidth, expectWidth ] : [ Math.min( inner, CONTENT ), inner ] );
					check( `${ id }: no layout container is introduced`, [ m.contentStyle.containerType, m.entryStyle.containerType, m.cardStyle.containerType ], [ 'normal', 'normal', 'normal' ] );
					check( `${ id }: clipping padding and column gap stay on the lg token`, [ m.cardStyle.paddingLeft, m.cardStyle.paddingRight ], [ `${ PAD }px`, `${ PAD }px` ] );

					if ( variant === 'image' || variant === 'note' ) {
						const text = wide ? ( expectWidth - 2 * PAD - GAP ) / 2 : expectWidth - 2 * PAD;
						check( `${ id }: pk-body columns ${ wide ? 'split in two equal tracks from 48rem' : 'stack in one track below 48rem' }`, m.columns, wide ? `${ text }px ${ text }px` : `${ text }px` );
					} else {
						check( `${ id }: pk-body is one track`, m.columns, `${ expectWidth - 2 * PAD }px` );
					}

					if ( wide ) {
						check( `${ id }: computed caps at 48rem and up (content ${ WIDE }px, h-entry none, clipping 100%)`, [ m.contentStyle.maxInlineSize, variant === 'url-only' ? m.entryStyle.maxInlineSize : m.entryStyle.maxInlineSize, m.cardStyle.maxInlineSize ], [ `${ WIDE }px`, 'none', '100%' ] );
					}
					if ( variant === 'note' ) {
						const base = await measure( { kind: 'repost', variant, scheme, width, scrollbar, base: true } );
						check( `${ id }: Courtney's note keeps the width it had and starts at the clipping's left edge`, [ m.note.width, m.note.left ], [ base.note.width, expectLeft ] );
					}
				}
			}
		}
	}

	// ---- Nothing else moves: the header and every other kind's single are identical to base --
	for ( const scheme of SCHEMES ) {
		for ( const scrollbar of [ 0, SCROLLBAR ] ) {
			for ( const width of WIDTHS ) {
				const head = await measure( { kind: 'repost', variant: 'note', scheme, width, scrollbar } );
				const base = await measure( { kind: 'repost', variant: 'note', scheme, width, scrollbar, base: true } );
				// Above the clipping everything must match exactly. The band below it only shifts down by
				// the clipping's new height, so its left edge and width are compared, not its top.
				const rows = ( m ) => m.all.filter( ( row ) => /newsletter|single-post__lede|taxonomy|single-post__date/.test( row[ 1 ] ) ).map( ( row ) => ( /newsletter/.test( row[ 1 ] ) ? [ row[ 0 ], row[ 1 ], row[ 2 ], row[ 4 ] ] : row ) );
				check( `header, meta row and newsletter band keep their horizontal geometry (${ scheme.name }/${ width }px/scrollbar-${ scrollbar })`, [ head.header, rows( head ) ], [ base.header, rows( base ) ] );
			}
		}
	}
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
