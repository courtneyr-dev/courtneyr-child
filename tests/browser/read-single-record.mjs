// `npm run test:read-single`: renders the Read single from the theme's PHP and checked-in sheets in
// headless Chrome and asserts measured geometry against fixed values at 1672, 1440, 1280, 1024, 800,
// 768, 390, 375 and 320 px, light and dark. It guards three things:
//   1. The desktop journal keeps a readable record and notes column (they once collapsed to a
//      one-character-per-line strip in the 720px content column).
//   2. The title and the book share a left edge, and the journal never leaves the viewport.
//   3. Only a journal that holds a record or notes changes. Locked posts, rowless reads and the other
//      kinds' journals keep the base geometry (720px entry, no widening).
// Visual order is asserted from painted rects: book, notes, record, sample (contract line 77).
//
// Offline: a loopback server serves the sheets and the fixture; every other request is blocked. The
// fixture stands in for WordPress's constrained layout (content 720px, wide 1100px, root padding from
// theme.json) because core generates that rule at runtime. Needs Chrome and the WordPress core checkout
// the PHP tests use (CR_WP_CORE, default ~/.wp-tests/wordpress). Without Chrome it prints a SKIP banner
// and exits 0, unless CR_REQUIRE_BROWSER=1.
//
// CR_POST_KINDS_CSS=/path/to/cr-post-kinds.css renders that sheet in place of the checked-in one
// (mutation runs). `--record` prints measurements instead of asserting.
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const SHEETS = [ 'tokens', 'components', 'cr-blog', 'cr-paper', 'cr-sticker', 'cr-post-kinds' ];
const RECORD = process.argv.includes( '--record' );
const WIDTHS = [ 1672, 1440, 1280, 1024, 800, 768, 390, 375, 320 ];
const SCHEMES = [ { name: 'light', theme: 'light', os: 'light' }, { name: 'dark', theme: 'dark', os: 'dark' } ];
const PAGE_PADDING = 18; // 1.125rem, the theme.json "md" spacing preset
const CONTENT = 720;
const WIDE = 1100;
const BOOK_COLUMN = 368; // minmax(17rem, 23rem) at a 16px root
const COLUMN_GAP = 44; // 2.75rem
const CONTROLS = [ 'checkin', 'watch', 'quote', 'comic' ];
const STATES = [ 'finished', 'reading', 'rowless', 'locked', ...CONTROLS.map( ( kind ) => 'control-' + kind ) ];

const candidates = [ process.env.CR_CHROME, '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/usr/bin/google-chrome', '/usr/bin/chromium' ].filter( Boolean );
let executablePath = candidates.find( ( path ) => existsSync( path ) ) ?? null;
if ( ! executablePath ) {
	try {
		const bundled = chromium.executablePath();
		executablePath = existsSync( bundled ) ? bundled : null;
	} catch {
		executablePath = null;
	}
}
if ( ! executablePath ) {
	const banner = '*'.repeat( 72 );
	console.log( `${ banner }\nSKIP read-single-record: no Chrome found. Set CR_CHROME. Nothing was measured.${ process.env.CR_REQUIRE_BROWSER ? ' CR_REQUIRE_BROWSER=1, so this fails.' : '' }\n${ banner }` );
	process.exit( process.env.CR_REQUIRE_BROWSER ? 1 : 0 );
}

const fixture = spawnSync( 'php', [ resolve( ROOT, 'tests/browser/read-single-fixture.php' ) ], { cwd: ROOT, encoding: 'utf8' } );
if ( fixture.status !== 0 || fixture.stdout.startsWith( 'SKIP' ) ) {
	console.log( ( fixture.stdout.startsWith( 'SKIP' ) ? fixture.stdout.trim() : 'php fixture failed: ' + fixture.stderr ) + '\nread-single-record: nothing measured.' );
	process.exit( fixture.stdout.startsWith( 'SKIP' ) && ! process.env.CR_REQUIRE_BROWSER ? 0 : 1 );
}
const MARKUP = JSON.parse( fixture.stdout );
MARKUP.finished = MARKUP.finished.replace( '<footer class="cr-journal__meta">', '<section class="cr-read__sample"><h2 class="cr-read__sample-title">Read a sample</h2><p>First pages of the book.</p></section><footer class="cr-journal__meta">' );
MARKUP.locked = '<form class="post-password-form" method="post"><p>This content is password protected. To view it please enter your password below:</p><p><label>Password: <input name="post_password" type="password" size="20"></label> <input type="submit" value="Enter"></p></form>';
for ( const kind of CONTROLS ) {
	MARKUP[ 'control-' + kind ] = `<div class="cr-journal"><article class="pk-card k-${ kind }"><p>The ${ kind } card.</p></article><section class="cr-journal__notes"><h2 class="cr-journal__notes-title">Notes</h2><p>Notes text.</p></section></div>`;
}

const sheetPath = ( name ) => ( name === 'cr-post-kinds' && process.env.CR_POST_KINDS_CSS ? resolve( process.env.CR_POST_KINDS_CSS ) : resolve( ROOT, 'assets/css', name + '.css' ) );
const server = createServer( ( request, response ) => {
	const url = new URL( request.url, 'http://127.0.0.1' );
	const sheet = /^\/css\/([\w-]+)\.css$/.exec( url.pathname )?.[ 1 ];
	if ( sheet && SHEETS.includes( sheet ) ) {
		response.setHeader( 'Content-Type', 'text/css' );
		response.end( readFileSync( sheetPath( sheet ) ) );
		return;
	}
	if ( url.pathname === '/fixture' ) {
		const { state, theme } = Object.fromEntries( url.searchParams );
		const kind = state.startsWith( 'control-' ) ? state.slice( 8 ) : 'read';
		response.setHeader( 'Content-Type', 'text/html' );
		response.setHeader( 'Content-Security-Policy', "default-src 'self' data:; style-src 'self' 'unsafe-inline'; img-src 'self' data:" );
		response.end( `<!doctype html><html lang="en" data-theme="${ theme }"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Read single</title>${ SHEETS.map( ( name ) => `<link rel="stylesheet" href="/css/${ name }.css">` ).join( '' ) }<style>
			:root { --wp--style--global--content-size: ${ CONTENT }px; --wp--style--global--wide-size: ${ WIDE }px; --wp--style--root--padding-left: 1.125rem; --wp--style--root--padding-right: 1.125rem; }
			html, body { margin: 0; padding: 0; }
			.has-global-padding { padding-left: var(--wp--style--root--padding-left); padding-right: var(--wp--style--root--padding-right); }
			.has-global-padding :where(.has-global-padding:not(.wp-block-block)) { padding-left: 0; padding-right: 0; }
			.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) { max-width: var(--wp--style--global--content-size); margin-left: auto !important; margin-right: auto !important; }
			main.is-layout-constrained > .single-post__content { max-width: none; }
			.is-layout-constrained > .alignwide { max-width: var(--wp--style--global--wide-size); margin-left: auto !important; margin-right: auto !important; }
		</style></head><body class="single single-post kind-${ kind }"><main class="single-post has-global-padding is-layout-constrained"><div class="alignwide single-post__header has-global-padding is-layout-constrained"><h1 class="single-post__title">A Treatise on Copper Kettles</h1></div><div class="entry-content align single-post__content wp-block-post-content has-global-padding is-layout-constrained"><div class="h-entry kind-${ kind } pkiw-singular-entry">${ MARKUP[ state ] ?? MARKUP.finished }</div></div></main></body></html>` );
		return;
	}
	response.writeHead( 404 ).end();
} );
await new Promise( ( done ) => server.listen( 0, '127.0.0.1', done ) );
const origin = `http://127.0.0.1:${ server.address().port }`;

const failures = [];
const record = {};
let cells = 0;
const check = ( key, field, measured, expected ) => {
	cells++;
	if ( JSON.stringify( measured ) !== JSON.stringify( expected ) ) {
		failures.push( `${ key } ${ field }: measured ${ JSON.stringify( measured ) }, expected ${ JSON.stringify( expected ) }` );
	}
};

const context = await chromium.launchPersistentContext( '', {
	executablePath,
	viewport: { width: 1672, height: 900 },
	deviceScaleFactor: 1,
	serviceWorkers: 'block',
	args: [ '--proxy-server=http://127.0.0.1:1', '--proxy-bypass-list=127.0.0.1', '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1', '--disable-background-networking', '--disable-sync', '--force-color-profile=srgb' ],
} );
try {
	await context.route( '**/*', ( route ) => ( route.request().url().startsWith( origin + '/' ) ? route.continue() : route.abort( 'blockedbyclient' ) ) );
	const page = context.pages()[ 0 ] ?? await context.newPage();
	for ( const scheme of SCHEMES ) {
		await page.emulateMedia( { colorScheme: scheme.os, reducedMotion: 'reduce' } );
		for ( const width of WIDTHS ) {
			await page.setViewportSize( { width, height: 900 } );
			for ( const state of STATES ) {
				const key = [ scheme.name, width, state ].join( '/' );
				await page.goto( origin + '/fixture?' + new URLSearchParams( { state, theme: scheme.theme } ) );
				const facts = await page.evaluate( () => {
					const rect = ( node ) => {
						if ( ! node ) {
							return null;
						}
						const r = node.getBoundingClientRect();
						const q = ( n ) => Math.round( n * 10 ) / 10;
						return { left: q( r.left ), top: q( r.top ), right: q( r.right ), bottom: q( r.bottom ) };
					};
					const lines = ( node ) => new Set( [ ...( () => { const r = document.createRange(); r.selectNodeContents( node ); return r.getClientRects(); } )() ].map( ( box ) => Math.round( box.top ) ) ).size;
					const row = ( label ) => [ ...document.querySelectorAll( '.cr-record-row' ) ].find( ( el ) => el.querySelector( 'dt' ).textContent === label )?.querySelector( 'dd' ) ?? null;
					const record = document.querySelector( '.cr-record' );
					const notes = document.querySelector( '.cr-journal__notes' );
					const journal = document.querySelector( '.cr-journal' );
					const values = {};
					for ( const label of [ 'Started', 'Finished', 'ISBN', 'Published' ] ) {
						const dd = row( label );
						if ( dd ) {
							values[ label ] = lines( dd );
						}
					}
					const book = document.querySelector( '.cr-book, .pk-card' );
					return {
						clientWidth: document.documentElement.clientWidth,
						scrollOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
						title: rect( document.querySelector( 'h1' ) ),
						entry: rect( document.querySelector( '.h-entry' ) ),
						journal: rect( journal ),
						book: rect( book ),
						bookWidth: book ? book.offsetWidth : null,
						recordWidth: record ? record.offsetWidth : null,
						notesWidth: notes ? notes.offsetWidth : null,
						notes: rect( notes ),
						record: rect( record ),
						sample: rect( document.querySelector( '.cr-read__sample' ) ),
						values,
						form: !! document.querySelector( '.post-password-form' ),
						recordBackground: record ? getComputedStyle( record ).backgroundColor : null,
						valueColor: record ? getComputedStyle( row( 'ISBN' ) ).color : null,
					};
				} );
				record[ key ] = facts;
				const entryWidth = Math.min( CONTENT, width - 2 * PAGE_PADDING );
				const entryLeft = PAGE_PADDING + Math.max( 0, width - 2 * PAGE_PADDING - CONTENT ) / 2;
				const desktop = width >= 1024;
				check( key, 'horizontal overflow', facts.scrollOverflow, 0 );
				check( key, 'entry width', facts.entry.right - facts.entry.left, entryWidth );
				check( key, 'entry left', facts.entry.left, entryLeft );
				if ( state === 'locked' ) {
					check( key, 'password form, no journal', [ facts.form, facts.journal ], [ true, null ] );
					continue;
				}
				if ( ! state.startsWith( 'control-' ) ) {
					check( key, 'title left == book left', facts.title.left, facts.book.left );
				}
				const widened = desktop && ( state === 'finished' || state === 'reading' );
				const journalWidth = widened ? Math.min( WIDE, width / 2 + CONTENT / 2 - PAGE_PADDING ) : entryWidth;
				check( key, 'journal width', facts.journal.right - facts.journal.left, journalWidth );
				check( key, 'journal left == title left', facts.journal.left, facts.title.left );
				check( key, 'journal inside the viewport', facts.journal.right <= facts.clientWidth - PAGE_PADDING, true );
				if ( state === 'rowless' && desktop ) {
					check( key, 'rowless book column', facts.bookWidth, BOOK_COLUMN );
				}
				if ( state === 'finished' || state === 'reading' ) {
					check( key, 'record background', facts.recordBackground, 'rgb(251, 247, 234)' );
					check( key, 'value ink', facts.valueColor, 'rgb(36, 28, 74)' );
					for ( const label of [ 'ISBN', 'Published', state === 'finished' ? 'Finished' : 'Started' ] ) {
						check( key, label + ' lines', facts.values[ label ], 1 );
					}
					if ( desktop ) {
						const column = journalWidth - BOOK_COLUMN - COLUMN_GAP;
						check( key, 'book column', facts.bookWidth, BOOK_COLUMN );
						check( key, 'record width', facts.recordWidth, column );
						if ( state === 'finished' ) {
							check( key, 'notes sheet width', facts.notesWidth, column );
						}
					} else {
						check( key, 'record width (stacked)', facts.recordWidth, entryWidth );
						if ( state === 'finished' ) {
							check( key, 'notes sheet width (stacked)', facts.notesWidth, entryWidth );
						}
					}
				}
				if ( state === 'finished' ) {
					// Visual order from painted rects: book, notes, record, sample.
					const { book, notes, record: sheet, sample } = facts;
					if ( desktop ) {
						check( key, 'book is left of notes', book.left < notes.left, true );
						check( key, 'notes above record', notes.bottom <= sheet.top, true );
						check( key, 'record above sample', sheet.bottom <= sample.top, true );
						check( key, 'book above sample', book.bottom <= sample.top, true );
					} else {
						check( key, 'book above notes', book.bottom <= notes.top, true );
						check( key, 'notes above record', notes.bottom <= sheet.top, true );
						check( key, 'record above sample', sheet.bottom <= sample.top, true );
					}
				}
			}
		}
	}
} finally {
	await context.close();
	await new Promise( ( done ) => server.close( done ) );
}

if ( RECORD ) {
	console.log( JSON.stringify( record, null, 2 ) );
	process.exit( 0 );
}
for ( const failure of failures ) {
	console.log( 'FAIL ' + failure );
}
console.log( `read-single-record: ${ cells } geometry assertions across ${ WIDTHS.length } widths x ${ SCHEMES.length } schemes x ${ STATES.length } states, ${ failures.length } failed (Chrome ${ executablePath })` );
process.exit( failures.length ? 1 : 0 );
