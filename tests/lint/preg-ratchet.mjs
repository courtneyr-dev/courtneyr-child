// Counts lines with `preg_` in inc/ and fails when the count rises above the checked-in
// baseline. A drop passes and says how to lower the baseline, so the number can only
// ratchet down: new regex parsing of block HTML should use the HTML API instead.
//
//   node tests/lint/preg-ratchet.mjs            check against tests/lint/preg-baseline.json
//   node tests/lint/preg-ratchet.mjs --update   rewrite the baseline from the current count
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const baselineFile = path.join( root, 'tests/lint/preg-baseline.json' );
const update = process.argv[ 2 ] === '--update';

function phpFiles( dir ) {
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			return phpFiles( full );
		}
		return entry.name.endsWith( '.php' ) ? [ full ] : [];
	} );
}

const perFile = {};
let total = 0;
for ( const file of phpFiles( path.join( root, 'inc' ) ) ) {
	// Lines, not matches, as `git grep -c preg_ -- inc/` counts them (collection-render-test uses that).
	const count = fs.readFileSync( file, 'utf8' ).split( '\n' ).filter( ( line ) => line.includes( 'preg_' ) ).length;
	if ( count ) {
		perFile[ path.relative( root, file ) ] = count;
		total += count;
	}
}

if ( update ) {
	fs.writeFileSync( baselineFile, JSON.stringify( { inc: total }, null, '\t' ) + '\n' );
	console.log( `preg_ baseline set to ${ total }` );
	process.exit( 0 );
}

const baseline = JSON.parse( fs.readFileSync( baselineFile, 'utf8' ) ).inc;
if ( total > baseline ) {
	const top = Object.entries( perFile )
		.sort( ( a, b ) => b[ 1 ] - a[ 1 ] )
		.slice( 0, 5 )
		.map( ( [ file, count ] ) => `  ${ count }  ${ file }` )
		.join( '\n' );
	console.error( `FAIL preg_ in inc/: ${ total }, baseline ${ baseline } (+${ total - baseline }). Use the HTML API (WP_HTML_Tag_Processor) for new block HTML work.\n${ top }` );
	process.exit( 1 );
}
if ( total < baseline ) {
	console.log( `preg_ in inc/: ${ total }, below the baseline ${ baseline }. Lower it with: npm run lint:preg:baseline` );
} else {
	console.log( `preg_ in inc/: ${ total } (baseline ${ baseline })` );
}
