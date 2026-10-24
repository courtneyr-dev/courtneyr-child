#!/usr/bin/env node
// Enforces the sheet rules from the CSS guardrails (CLAUDE.md, "CSS performance guardrails"):
//   1. A sheet over 30 KiB must be in the baseline and may not grow past its recorded size.
//   2. No new selector list that names two kinds (.kind-watch, .kind-listen, ...). The pruning engine
//      only drops a sheet whose distinctive classes did not render, and a grouped rule defeats that.
//   3. Every courtneyr-* handle in RUCSS_INLINE_HANDLES has a sheet (assets/css/cr-<name>.css).
//   4. A new assets/css/cr-*.css sheet is either in RUCSS_INLINE_HANDLES or listed in the baseline
//      under "notInRucssHandles": a decision made on purpose that Perfmatters' shared used CSS can carry
//      it (it loads on every page of the types it is on, or another mechanism already covers it).
//
//   node tools/css-guardrails.mjs            check
//   node tools/css-guardrails.mjs --write-baseline   record today's state (review the diff by hand)
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const cssDir = path.join( root, 'assets/css' );
const baselineFile = path.join( root, 'tools/css-guardrails.baseline.json' );
const BUDGET = 30 * 1024;

const walk = ( d ) =>
	fs.readdirSync( d, { withFileTypes: true } ).flatMap( ( e ) =>
		e.isDirectory() ? walk( path.join( d, e.name ) ) : e.name.endsWith( '.css' ) ? [ path.join( d, e.name ) ] : []
	);
const sheets = walk( cssDir ).map( ( f ) => ( { rel: path.relative( cssDir, f ).split( path.sep ).join( '/' ), css: fs.readFileSync( f, 'utf8' ) } ) );

// Selector lists (rule preludes at any nesting depth) that name two or more different kinds.
const groupedKindRules = ( css ) => {
	const text = css.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /(["'])(?:\\.|(?!\1)[^\\])*\1/g, '""' );
	let n = 0;
	let start = 0;
	for ( let i = 0; i < text.length; i++ ) {
		const c = text[ i ];
		if ( c === '{' ) {
			const prelude = text.slice( start, i ).trim();
			if ( ! prelude.startsWith( '@' ) ) {
				const kinds = new Set( [ ...prelude.matchAll( /\.kind-([a-z0-9]+)/g ) ].map( ( m ) => m[ 1 ] ) );
				if ( kinds.size >= 2 ) {
					n++;
				}
			}
			start = i + 1;
		} else if ( c === '}' || c === ';' ) {
			start = i + 1;
		}
	}
	return n;
};

const pruning = fs.readFileSync( path.join( root, 'inc/stream-style-pruning.php' ), 'utf8' );
const block = pruning.match( /const RUCSS_INLINE_HANDLES = array\(([\s\S]*?)\n\);/ );
if ( ! block ) {
	console.error( 'css-guardrails: could not find RUCSS_INLINE_HANDLES in inc/stream-style-pruning.php' );
	process.exit( 1 );
}
const handles = [ ...block[ 1 ].replace( /\/\/.*$/gm, '' ).matchAll( /'([^']+)'/g ) ].map( ( m ) => m[ 1 ] );
const ownHandles = handles.filter( ( h ) => h.startsWith( 'courtneyr-' ) ).map( ( h ) => h.slice( 'courtneyr-'.length ) );
const crSheets = sheets.map( ( s ) => s.rel ).filter( ( r ) => /^cr-[a-z0-9-]+\.css$/.test( r ) );

const state = {
	oversized: Object.fromEntries( sheets.filter( ( s ) => Buffer.byteLength( s.css ) > BUDGET ).map( ( s ) => [ s.rel, Buffer.byteLength( s.css ) ] ).sort() ),
	groupedKindRules: Object.fromEntries( sheets.map( ( s ) => [ s.rel, groupedKindRules( s.css ) ] ).filter( ( [ , n ] ) => n > 0 ).sort() ),
	notInRucssHandles: crSheets.filter( ( r ) => ! ownHandles.includes( r.slice( 3, -4 ) ) ).sort(),
};

if ( process.argv.includes( '--write-baseline' ) ) {
	fs.writeFileSync( baselineFile, JSON.stringify( state, null, '\t' ) + '\n' );
	console.log( `css-guardrails: wrote ${ path.relative( root, baselineFile ) }` );
	process.exit( 0 );
}

const base = JSON.parse( fs.readFileSync( baselineFile, 'utf8' ) );
const problems = [];
for ( const [ rel, size ] of Object.entries( state.oversized ) ) {
	if ( ! ( rel in base.oversized ) ) {
		problems.push( `${ rel } is ${ size } B, over the ${ BUDGET } B budget. Split it by kind, or baseline it with a reason in the commit message.` );
	} else if ( size > base.oversized[ rel ] ) {
		problems.push( `${ rel } grew from ${ base.oversized[ rel ] } B to ${ size } B. Oversized sheets may only shrink.` );
	}
}
for ( const [ rel, n ] of Object.entries( state.groupedKindRules ) ) {
	if ( n > ( base.groupedKindRules[ rel ] ?? 0 ) ) {
		problems.push( `${ rel } has ${ n } selector lists naming two or more kinds (baseline ${ base.groupedKindRules[ rel ] ?? 0 } ). Give each kind its own rule or share the look through a custom property.` );
	}
}
for ( const name of ownHandles ) {
	if ( ! crSheets.includes( `cr-${ name }.css` ) ) {
		problems.push( `RUCSS_INLINE_HANDLES lists courtneyr-${ name } but assets/css/cr-${ name }.css does not exist.` );
	}
}
for ( const rel of state.notInRucssHandles ) {
	if ( ! base.notInRucssHandles.includes( rel ) ) {
		problems.push( `${ rel } is not in RUCSS_INLINE_HANDLES and not in the baseline's notInRucssHandles list. A sheet that loads on only some pages or page types needs its handle in the list (Perfmatters builds used CSS from the first page it sees); one that does not goes in the baseline on purpose.` );
	}
}

if ( problems.length ) {
	console.error( `css-guardrails: ${ problems.length } problem(s)\n- ${ problems.join( '\n- ' ) }` );
	process.exit( 1 );
}
console.log(
	`css-guardrails: ok (${ sheets.length } sheets, ${ Object.keys( state.oversized ).length } oversized at or under their baseline, ${ ownHandles.length } own handles, ${ state.notInRucssHandles.length } not in RUCSS handles)`
);
