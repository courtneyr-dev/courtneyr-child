#!/usr/bin/env node
// Minifies the theme's stylesheets for deployment. The sources in this repo stay commented and
// readable; private/bin/sync-theme.sh in the deploy repo runs this on its export of HEAD before
// the rsync, so deployed files keep their names and paths (prunable_sheets() and its keep list in
// inc/stream-style-pruning.php match on file names). CI runs it with --check.
//
//   node tools/minify-css.mjs --dir assets/css --check
//   CR_TOOLS_ROOT=<checkout with node_modules> CR_SOURCE_SHA=<sha> \
//     node tools/minify-css.mjs --dir <export>/assets/css --manifest <export>/css-build.txt
//
// Tool: esbuild, pinned exactly in package.json. Options are the ones the equivalence test ran
// with (default minify, no syntax lowering). Nothing is written unless every file passes every gate.
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const args = process.argv.slice( 2 );
const opt = ( name ) => ( args.indexOf( name ) < 0 ? null : args[ args.indexOf( name ) + 1 ] );
const check = args.includes( '--check' );
const dir = path.resolve( opt( '--dir' ) ?? 'assets/css' );
const manifest = opt( '--manifest' );
const here = path.dirname( fileURLToPath( import.meta.url ) );
const treeRoot = path.resolve( here, '..' ); // the tree this script belongs to; its package.json holds the pin
const toolsRoot = path.resolve( process.env.CR_TOOLS_ROOT ?? treeRoot ); // where node_modules lives

const die = ( message ) => {
	console.error( `minify-css: ${ message }` );
	process.exit( 1 );
};

let esbuild;
try {
	esbuild = createRequire( path.join( toolsRoot, 'package.json' ) )( 'esbuild' );
} catch {
	die( `esbuild is not installed in ${ toolsRoot }. Run: npm install --ignore-scripts (in that folder).` );
}
const pin = JSON.parse( fs.readFileSync( path.join( treeRoot, 'package.json' ), 'utf8' ) ).devDependencies?.esbuild;
if ( esbuild.version !== pin ) {
	die( `installed esbuild ${ esbuild.version } does not match the ${ pin } pinned in package.json. Run: npm install --ignore-scripts in ${ toolsRoot }.` );
}
if ( ! fs.existsSync( dir ) ) {
	die( `${ dir } does not exist` );
}

const OPTIONS = { loader: 'css', minify: true, legalComments: 'none', logLevel: 'silent' };
const strip = ( css ) => css.replace( /\/\*[\s\S]*?\*\//g, '' );
const count = ( css, rx ) => ( strip( css ).match( rx ) || [] ).length;
// At-rules and url() the minifier must not add or drop. (!important and calc() counts legitimately
// change when it merges duplicate rules or folds constants.)
const GUARDS = [
	[ '@font-face', /@font-face\b/g ],
	[ '@keyframes', /@keyframes\b/g ],
	[ '@supports', /@supports\b/g ],
	[ '@container', /@container\b/g ],
	[ 'url()', /url\(/g ],
];
// Port of sheet_classes() in inc/stream-style-pruning.php: the class tokens the pruning engine reads from a sheet.
const sheetClasses = ( css ) =>
	new Set( [ ...strip( css ).replace( /\{[^{}]*\}/g, '{}' ).matchAll( /\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/g ) ].map( ( m ) => m[ 1 ] ) );

const walk = ( d ) =>
	fs.readdirSync( d, { withFileTypes: true } ).flatMap( ( e ) => {
		const p = path.join( d, e.name );
		if ( e.isDirectory() ) {
			return walk( p );
		}
		return e.name.endsWith( '.css' ) && ! e.name.endsWith( '.min.css' ) ? [ p ] : [];
	} );
const files = walk( dir ).sort();
if ( files.length === 0 ) {
	die( `no stylesheets under ${ dir }` );
}

const problems = [];
const results = [];
for ( const file of files ) {
	const rel = path.relative( dir, file ).split( path.sep ).join( '/' );
	const src = fs.readFileSync( file, 'utf8' );
	let out;
	try {
		const r = esbuild.transformSync( src, OPTIONS );
		out = r.code;
		if ( r.warnings.length ) {
			problems.push( `${ rel }: ${ r.warnings.length } warning(s), first: ${ r.warnings[ 0 ].text }` );
		}
	} catch ( e ) {
		problems.push( `${ rel }: ${ ( e.errors?.[ 0 ]?.text ?? e.message ).toString().slice( 0, 160 ) }` );
		continue;
	}
	if ( ! out.trim() && src.trim() ) {
		problems.push( `${ rel }: empty output` );
	}
	if ( Buffer.byteLength( out ) > Buffer.byteLength( src ) ) {
		problems.push( `${ rel }: output is larger than the source` );
	}
	if ( esbuild.transformSync( src, OPTIONS ).code !== out ) {
		problems.push( `${ rel }: two runs gave different output` );
	}
	for ( const [ name, rx ] of GUARDS ) {
		const a = count( src, rx );
		const b = count( out, rx );
		if ( a !== b ) {
			problems.push( `${ rel }: ${ name } count ${ a } -> ${ b }` );
		}
	}
	const before = sheetClasses( src );
	const after = sheetClasses( out );
	const gone = [ ...before ].filter( ( c ) => ! after.has( c ) );
	const added = [ ...after ].filter( ( c ) => ! before.has( c ) );
	if ( gone.length || added.length ) {
		problems.push( `${ rel }: class tokens differ (lost: ${ gone.slice( 0, 5 ).join( ' ' ) || '-' }; new: ${ added.slice( 0, 5 ).join( ' ' ) || '-' })` );
	}
	results.push( { file, rel, src, out } );
}

if ( problems.length ) {
	console.error( `minify-css: ${ problems.length } problem(s); nothing written.` );
	problems.forEach( ( p ) => console.error( `  ${ p }` ) );
	process.exit( 1 );
}

const bytes = ( s ) => Buffer.byteLength( s );
const raw = results.reduce( ( n, r ) => n + bytes( r.src ), 0 );
const min = results.reduce( ( n, r ) => n + bytes( r.out ), 0 );
console.log( `minify-css: esbuild ${ esbuild.version }, ${ results.length } files, ${ raw } -> ${ min } bytes (-${ ( ( 100 * ( raw - min ) ) / raw ).toFixed( 1 ) }%)${ check ? ' [check only]' : '' }` );
if ( check ) {
	process.exit( 0 );
}

const sha = ( s ) => crypto.createHash( 'sha256' ).update( s ).digest( 'hex' );
for ( const r of results ) {
	const tmp = `${ r.file }.minify-tmp`;
	fs.writeFileSync( tmp, r.out );
	fs.renameSync( tmp, r.file );
}
if ( manifest ) {
	const lines = results.map( ( r ) => `${ sha( r.out ) }  ${ r.rel }  ${ bytes( r.src ) } ${ bytes( r.out ) }` );
	const head = `esbuild ${ esbuild.version } minify source=${ process.env.CR_SOURCE_SHA ?? 'unknown' } files=${ results.length } raw=${ raw } min=${ min } sha256=${ sha( lines.join( '\n' ) ).slice( 0, 16 ) }`;
	fs.writeFileSync( manifest, `${ head }\n${ lines.join( '\n' ) }\n` );
}
