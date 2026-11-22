// `npm run test:read-single`: renders the Read single journal (book, notes sheet, Reading record)
// from the theme's own PHP and checked-in sheets in headless Chrome, and asserts the measured
// geometry against fixed values at 1672, 1280, 1024, 768, 390 and 320 px, light and dark.
// It guards the desktop journal: the record values and the notes sheet must keep a readable column
// (they once collapsed to a one-character-per-line strip inside the 720px content column).
//
// Offline: a loopback server serves the sheets and the fixture; every other request is blocked.
// The fixture stands in for WordPress's constrained layout (content 720px, wide 1100px, from
// theme.json) because core generates that rule at runtime. Needs Chrome and the WordPress core
// checkout the PHP tests use (CR_WP_CORE, default ~/.wp-tests/wordpress). Without Chrome it prints
// a SKIP banner and exits 0, unless CR_REQUIRE_BROWSER=1.
//
// CR_POST_KINDS_CSS=/path/to/cr-post-kinds.css renders that sheet in place of the checked-in one
// (mutation runs against the base sheet). `--record` prints measurements instead of asserting.
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const SHEETS = [ 'tokens', 'components', 'cr-blog', 'cr-paper', 'cr-sticker', 'cr-post-kinds' ];
const RECORD = process.argv.includes( '--record' );
const WIDTHS = [ 1672, 1280, 1024, 768, 390, 320 ];
const SCHEMES = [ { name: 'light', theme: 'light', os: 'light' }, { name: 'dark', theme: 'dark', os: 'dark' } ];
const PAGE_PADDING = 24;
const WIDE = 1100;
const BOOK_COLUMN = 368; // minmax(17rem, 23rem) at a 16px root
const COLUMN_GAP = 44; // 2.75rem

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
		response.setHeader( 'Content-Type', 'text/html' );
		response.setHeader( 'Content-Security-Policy', "default-src 'self' data:; style-src 'self' 'unsafe-inline'; img-src 'self' data:" );
		response.end( `<!doctype html><html lang="en" data-theme="${ theme }"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Read single</title>${ SHEETS.map( ( name ) => `<link rel="stylesheet" href="/css/${ name }.css">` ).join( '' ) }<style>
			:root { --wp--style--global--content-size: 720px; --wp--style--global--wide-size: ${ WIDE }px; }
			html, body { margin: 0; padding: 0; }
			.has-global-padding { padding-left: ${ PAGE_PADDING }px; padding-right: ${ PAGE_PADDING }px; }
			.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) { max-width: var(--wp--style--global--content-size); margin-left: auto !important; margin-right: auto !important; }
		</style></head><body class="single single-post kind-read"><main class="single-post"><div class="entry-content align single-post__content wp-block-post-content has-global-padding is-layout-constrained"><div class="h-entry kind-read pkiw-singular-entry">${ MARKUP[ state ] }</div></div></main></body></html>` );
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
			for ( const state of [ 'finished', 'reading' ] ) {
				const key = [ scheme.name, width, state ].join( '/' );
				await page.goto( origin + '/fixture?' + new URLSearchParams( { state, theme: scheme.theme } ) );
				const facts = await page.evaluate( () => {
					const lines = ( node ) => new Set( [ ...( () => { const r = document.createRange(); r.selectNodeContents( node ); return r.getClientRects(); } )() ].map( ( rect ) => Math.round( rect.top ) ) ).size;
					const row = ( label ) => [ ...document.querySelectorAll( '.cr-record-row' ) ].find( ( el ) => el.querySelector( 'dt' ).textContent === label )?.querySelector( 'dd' ) ?? null;
					const notes = document.querySelector( '.cr-journal__notes' );
					const record = document.querySelector( '.cr-record' );
					const values = {};
					for ( const label of [ 'Started', 'Finished', 'Progress', 'ISBN', 'Publisher', 'Published' ] ) {
						const dd = row( label );
						if ( dd ) {
							values[ label ] = { lines: lines( dd ), width: dd.offsetWidth };
						}
					}
					return {
						scrollOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
						journal: document.querySelector( '.cr-journal' ).offsetWidth,
						book: document.querySelector( '.cr-book' ).offsetWidth,
						record: record.offsetWidth,
						notes: notes ? notes.offsetWidth : null,
						values,
						recordBackground: getComputedStyle( record ).backgroundColor,
						valueColor: getComputedStyle( row( 'ISBN' ) ).color,
					};
				} );
				record[ key ] = facts;
				const desktop = width >= 1024;
				const journal = desktop ? Math.min( WIDE, width - 2 * PAGE_PADDING ) : Math.min( 720, width - 2 * PAGE_PADDING );
				check( key, 'horizontal overflow', facts.scrollOverflow, 0 );
				check( key, 'journal width', facts.journal, journal );
				check( key, 'record background', facts.recordBackground, 'rgb(251, 247, 234)' );
				check( key, 'value ink', facts.valueColor, 'rgb(36, 28, 74)' );
				if ( desktop ) {
					const column = journal - BOOK_COLUMN - COLUMN_GAP;
					check( key, 'book column', facts.book, BOOK_COLUMN );
					check( key, 'record width', facts.record, column );
					if ( state === 'finished' ) {
						check( key, 'notes sheet width', facts.notes, column );
					}
				} else {
					check( key, 'record width (stacked)', facts.record, journal );
					if ( state === 'finished' ) {
						check( key, 'notes sheet width (stacked)', facts.notes, journal );
					}
				}
				for ( const label of [ 'ISBN', 'Published', state === 'finished' ? 'Finished' : 'Started' ] ) {
					check( key, label + ' lines', facts.values[ label ]?.lines, 1 );
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
console.log( `read-single-record: ${ cells } geometry assertions across ${ WIDTHS.length } widths x ${ SCHEMES.length } schemes, ${ failures.length } failed (Chrome ${ executablePath })` );
process.exit( failures.length ? 1 : 0 );
