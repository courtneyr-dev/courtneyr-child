// `npm run test:favorite-paint`: renders the Favorite mixed clipping (a clipping that also
// carries the "Also a check-in" label) and one plain clipping from the theme's checked-in
// sheets in headless Chrome, and asserts what is PAINTED against fixed values. It guards
// rule X1 (a metaphor object keeps its palette paper and violet ink in every scheme) and
// the link states the PHP checks in tests/php/favorite-css-test.php can only read as text.
//
// Offline: a loopback server serves the sheets and the fixture, and every other request is
// blocked. Needs Chrome: CR_CHROME=/path/to/chrome, or Google Chrome / Chromium in a
// standard location. Without one the test prints a SKIP banner and exits 0, unless
// CR_REQUIRE_BROWSER=1, which makes a missing Chrome a failure. It is not wired into CI.
//
// CR_FAVORITE_CSS=/path/to/cr-favorite.css renders that sheet in place of the checked-in
// one (mutation runs). `--record` prints the measured baseline instead of asserting.
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { existsSync, readFileSync } from 'node:fs';
import { inflateSync } from 'node:zlib';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const SHEETS = [ 'tokens', 'components', 'cr-blog', 'cr-paper', 'cr-sticker', 'cr-favorite' ];
const RECORD = process.argv.includes( '--record' );

// Fixed expectations. No tolerance on any color.
const IVORY = '#fbfaf5';
const VIOLET = '#241c4a';
const CERULEAN = '#126782';
// Recorded from origin/main (theme 0.7.175) in this fixture. favorite-clipping-paint.base.json holds,
// for every scheme and state, the plain clipping's values and the mixed clipping's shadow, height,
// border widths and label size. Re-record: CR_FAVORITE_CSS=<base cr-favorite.css> node <this file> --record.
const BASELINE = JSON.parse( readFileSync( resolve( dirname( fileURLToPath( import.meta.url ) ), 'favorite-clipping-paint.base.json' ), 'utf8' ) );
const WIDTH = 354;

const SCHEMES = [
	{ name: 'light', theme: 'light', os: 'dark' },
	{ name: 'manual-dark', theme: 'dark', os: 'light' },
	{ name: 'os-dark-no-attribute', theme: null, os: 'dark' },
	{ name: 'system-dark', theme: 'system', os: 'dark' },
];
const STATES = [ 'rest', 'hover', 'focus-visible', 'visited' ];

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

const executablePath = chromePath();
if ( ! executablePath ) {
	const banner = '*'.repeat( 72 );
	console.log( `${ banner }\nSKIP favorite-clipping-paint: no Chrome found. Set CR_CHROME or install Google Chrome.\nNothing was painted or asserted. ${ process.env.CR_REQUIRE_BROWSER ? 'CR_REQUIRE_BROWSER=1, so this fails.' : 'Exiting 0.' }\n${ banner }` );
	process.exit( process.env.CR_REQUIRE_BROWSER ? 1 : 0 );
}

const render = ( label ) => {
	const code = 'require "tests/php/favorite-bootstrap.php"; echo Courtneyr\\Child\\Favorite\\item_html(["name" => "Favorite archive clipping"], 3, 1, "--cr-rotate-1", $argv[1]);';
	const run = spawnSync( 'php', [ '-r', code, label ], { cwd: ROOT, encoding: 'utf8' } );
	if ( run.status !== 0 ) {
		throw new Error( 'php could not render the Favorite item: ' + run.stderr );
	}
	return run.stdout;
};
const MARKUP = {
	mixed: render( '<span class="cr-fav-clipping__checkin">Also a check-in</span>' ),
	plain: render( '' ),
};

const sheetPath = ( name ) => ( name === 'cr-favorite' && process.env.CR_FAVORITE_CSS ? resolve( process.env.CR_FAVORITE_CSS ) : resolve( ROOT, 'assets/css', name + '.css' ) );
const server = createServer( ( request, response ) => {
	const url = new URL( request.url, 'http://127.0.0.1' );
	const sheet = /^\/css\/([\w-]+)\.css$/.exec( url.pathname )?.[ 1 ];
	if ( sheet && SHEETS.includes( sheet ) ) {
		response.setHeader( 'Content-Type', 'text/css' );
		response.end( readFileSync( sheetPath( sheet ) ) );
		return;
	}
	if ( url.pathname === '/fixture' ) {
		const { kind, theme } = Object.fromEntries( url.searchParams );
		const card = MARKUP[ kind ].replace( /href="[^"]+"/, 'href="/never-visited"' );
		const attribute = theme ? ` data-theme="${ theme }"` : '';
		response.setHeader( 'Content-Type', 'text/html' );
		response.setHeader( 'Content-Security-Policy', "default-src 'self' data:; style-src 'self' 'unsafe-inline'; img-src 'self' data:" );
		response.end( `<!doctype html><html lang="en"${ attribute }><head><meta charset="utf-8"><title>Favorite paint</title>${ SHEETS.map( ( name ) => `<link rel="stylesheet" href="/css/${ name }.css">` ).join( '' ) }<style>
			html, body { background: #808080 !important; margin: 0; padding: 0; }
			body { padding: 36px; }
			.cr-favorite-chart { width: ${ WIDTH }px; }
			.cr-favorite-chart .pkiw-group__items { display: block; }
		</style></head><body class="archive tax-kind term-favorite"><main class="cr-favorite-chart"><section class="pkiw-group"><ul class="pkiw-group__items"><li>${ card }</li></ul></section></main></body></html>` );
		return;
	}
	response.writeHead( 404 ).end();
} );
await new Promise( ( done ) => server.listen( 0, '127.0.0.1', done ) );
const origin = `http://127.0.0.1:${ server.address().port }`;

const decodePng = ( data ) => {
	let width;
	let height;
	let bytes;
	const chunks = [];
	for ( let offset = 8; offset < data.length; ) {
		const size = data.readUInt32BE( offset );
		const type = data.toString( 'ascii', offset + 4, offset + 8 );
		const body = data.subarray( offset + 8, offset + 8 + size );
		if ( type === 'IHDR' ) {
			width = body.readUInt32BE( 0 );
			height = body.readUInt32BE( 4 );
			bytes = { 2: 3, 6: 4 }[ body[ 9 ] ];
		}
		if ( type === 'IDAT' ) {
			chunks.push( body );
		}
		offset += 12 + size;
	}
	const stride = width * bytes;
	const input = inflateSync( Buffer.concat( chunks ) );
	const pixels = Buffer.alloc( stride * height );
	let at = 0;
	for ( let row = 0; row < height; row++ ) {
		const filter = input[ at++ ];
		for ( let col = 0; col < stride; col++ ) {
			const pos = row * stride + col;
			const a = col >= bytes ? pixels[ pos - bytes ] : 0;
			const b = row ? pixels[ pos - stride ] : 0;
			const c = row && col >= bytes ? pixels[ pos - stride - bytes ] : 0;
			const estimate = a + b - c;
			const distances = [ Math.abs( estimate - a ), Math.abs( estimate - b ), Math.abs( estimate - c ) ];
			const paeth = [ a, b, c ][ distances.indexOf( Math.min( ...distances ) ) ];
			pixels[ pos ] = ( input[ at++ ] + [ 0, a, b, ( a + b ) >> 1, paeth ][ filter ] ) & 255;
		}
	}
	return {
		get: ( x, y ) => '#' + pixels.subarray( ( width * y + x ) * bytes, ( width * y + x ) * bytes + 3 ).toString( 'hex' ),
	};
};

const channels = ( hex ) => [ 1, 3, 5 ].map( ( i ) => parseInt( hex.slice( i, i + 2 ), 16 ) );
const distance = ( a, b ) => channels( a ).reduce( ( sum, value, i ) => sum + Math.abs( value - channels( b )[ i ] ), 0 );
const region = ( image, [ left, top, right, bottom ] ) => {
	const found = [];
	for ( let y = Math.ceil( top ); y < Math.floor( bottom ); y++ ) {
		for ( let x = Math.ceil( left ); x < Math.floor( right ); x++ ) {
			found.push( image.get( x, y ) );
		}
	}
	return found;
};
const dominant = ( colors, skip = [] ) => {
	const counts = new Map();
	for ( const color of colors ) {
		if ( ! skip.includes( color ) ) {
			counts.set( color, ( counts.get( color ) || 0 ) + 1 );
		}
	}
	return [ ...counts ].sort( ( a, b ) => b[ 1 ] - a[ 1 ] )[ 0 ]?.[ 0 ] ?? null;
};
// The solid ink of text or an underline: the painted pixel farthest from the paper under it.
const ink = ( colors, paper ) => colors.reduce( ( far, color ) => ( distance( color, paper ) > distance( far, paper ) ? color : far ), paper );

const BACKGROUND = '#808080';
const failures = [];
const record = {};
let cells = 0;
const context = await chromium.launchPersistentContext( '', {
	executablePath,
	viewport: { width: 440, height: 240 },
	deviceScaleFactor: 1,
	serviceWorkers: 'block',
	args: [ '--proxy-server=http://127.0.0.1:1', '--proxy-bypass-list=127.0.0.1', '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1', '--disable-background-networking', '--disable-sync', '--force-color-profile=srgb' ],
} );
try {
	await context.route( '**/*', ( route ) => ( route.request().url().startsWith( origin + '/' ) ? route.continue() : route.abort( 'blockedbyclient' ) ) );
	const page = context.pages()[ 0 ] ?? await context.newPage();
	const cdp = await context.newCDPSession( page );
	await cdp.send( 'DOM.enable' );
	await cdp.send( 'CSS.enable' );

	for ( const scheme of SCHEMES ) {
		await page.emulateMedia( { colorScheme: scheme.os, forcedColors: 'none', reducedMotion: 'reduce' } );
		for ( const kind of [ 'mixed', 'plain' ] ) {
			for ( const state of STATES ) {
				const key = [ scheme.name, kind, state ].join('/');
				await page.mouse.move( 0, 0 );
				await page.goto( origin + '/fixture?' + new URLSearchParams( { kind, theme: scheme.theme ?? '' } ) );
				await page.evaluate( () => document.fonts.ready );
				const link = page.locator( '.cr-fav-link' );
				if ( state === 'hover' ) {
					const box = await page.locator( '.cr-fav-clipping' ).boundingBox();
					await page.mouse.move( box.x + box.width / 2, box.y + box.height / 2 );
				}
				if ( state === 'focus-visible' ) {
					await page.keyboard.press( 'Tab' );
				}
				if ( state === 'visited' ) {
					const { root } = await cdp.send( 'DOM.getDocument' );
					const { nodeId } = await cdp.send( 'DOM.querySelector', { nodeId: root.nodeId, selector: '.cr-fav-link' } );
					await cdp.send( 'CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [ 'visited' ] } );
				}
				const facts = await link.evaluate( ( anchor ) => {
					const clipping = document.querySelector( '.cr-fav-clipping' );
					const label = document.querySelector( '.cr-fav-clipping__checkin' );
					const box = ( node ) => {
						const range = document.createRange();
						range.selectNodeContents( node );
						const r = range.getBoundingClientRect();
						return [ r.left, r.top, r.right, r.bottom ];
					};
					const whole = clipping.getBoundingClientRect();
					const style = getComputedStyle( clipping );
					return {
						clip: [ whole.left, whole.top, whole.right, whole.bottom ],
						height: whole.height,
						link: box( anchor ),
						label: label ? box( label ) : null,
						labelSize: label ? getComputedStyle( label ).fontSize : null,
						borders: [ 'Top', 'Right', 'Bottom', 'Left' ].map( ( side ) => style[ 'border' + side + 'Width' ] ),
						shadow: style.boxShadow,
						darkMedia: matchMedia( '(prefers-color-scheme: dark)' ).matches,
						hover: anchor.matches( ':hover' ),
						focus: anchor.matches( ':focus-visible' ),
					};
				} );
				if ( facts.darkMedia !== ( scheme.os === 'dark' ) || facts.hover !== ( state === 'hover' ) || facts.focus !== ( state === 'focus-visible' ) ) {
					failures.push( `${ key }: the fixture did not reach the state (dark ${ facts.darkMedia }, hover ${ facts.hover }, focus-visible ${ facts.focus })` );
					continue;
				}
				const image = decodePng( await page.screenshot() );
				const [ left, top, right, bottom ] = facts.clip;
				const paper = dominant( region( image, [ right - 40, top + 12, right - 10, bottom - 8 ] ) );
				// The tear's top row is fully inside its zig-zag mask, so it paints the tear color alone.
				const tear = dominant( region( image, [ left + 32, Math.ceil( bottom ), right - 4, Math.ceil( bottom ) + 1 ] ) );
				const linkRegion = [ ...facts.link ];
				linkRegion[ 3 ] += 5;
				const linkInk = ink( region( image, linkRegion ), paper );
				const labelInk = facts.label ? ink( region( image, facts.label ), paper ) : null;
				// The paper edge strips: a border painted inside the clipping would change these.
				const edges = [ dominant( region( image, [ left, top + 8, left + 2, bottom - 8 ] ) ), dominant( region( image, [ left + 8, top, right - 8, top + 2 ] ) ), dominant( region( image, [ right - 2, top + 8, right, bottom - 8 ] ) ) ];
				const measured = { paper, tear, link: linkInk, label: labelInk, labelSize: facts.labelSize, borders: facts.borders, shadow: facts.shadow, height: facts.height, edges };
				record[ key ] = measured;

				const base = BASELINE[ key ];
				const expected = kind === 'plain' ? base : {
					paper: IVORY,
					tear: IVORY,
					link: state === 'hover' ? VIOLET : CERULEAN,
					label: VIOLET,
					edges: [ IVORY, IVORY, IVORY ],
					labelSize: '14px',
					borders: base.borders,
					shadow: base.shadow,
					height: base.height,
				};
				for ( const field of Object.keys( expected ) ) {
					cells++;
					if ( JSON.stringify( measured[ field ] ) !== JSON.stringify( expected[ field ] ) ) {
						failures.push( `${ key } ${ field }: painted ${ JSON.stringify( measured[ field ] ) }, expected ${ JSON.stringify( expected[ field ] ) }` );
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
console.log( `favorite-clipping-paint: ${ cells } painted assertions across ${ SCHEMES.length } schemes, ${ failures.length } failed (Chrome ${ executablePath })` );
process.exit( failures.length ? 1 : 0 );
