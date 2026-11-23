// Compare two probe.mjs runs. usage: node compare.mjs <base.json> <head.json> [--pages a,b] [--tol 0.02]
// Prints, per page, every measured key whose value differs between base and head (any width, either scheme),
// and the count of identical cases. Exit 0 always: the caller decides which differences are expected.
import { readFileSync } from 'node:fs';

const [, , basePath, headPath, ...rest] = process.argv;
const opt = ( k, d ) => {
	const i = rest.indexOf( k );
	return -1 === i ? d : rest[ i + 1 ];
};
const tol = Number( opt( '--tol', '0.02' ) );
const pages = opt( '--pages', '' ).split( ',' ).filter( Boolean );
const base = JSON.parse( readFileSync( basePath, 'utf8' ) );
const head = JSON.parse( readFileSync( headPath, 'utf8' ) );

const same = ( a, b ) => {
	if ( 'number' === typeof a && 'number' === typeof b ) {
		return Math.abs( a - b ) <= tol;
	}
	if ( Array.isArray( a ) && Array.isArray( b ) ) {
		return a.length === b.length && a.every( ( v, i ) => same( v, b[ i ] ) );
	}
	if ( a && b && 'object' === typeof a && 'object' === typeof b ) {
		const keys = new Set( [ ...Object.keys( a ), ...Object.keys( b ) ] );
		return [ ...keys ].every( ( k ) => same( a[ k ], b[ k ] ) );
	}
	return a === b;
};

const byPage = {};
for ( const key of Object.keys( base ) ) {
	const [ page ] = key.split( '|' );
	if ( pages.length && ! pages.includes( page ) ) {
		continue;
	}
	byPage[ page ] ??= { same: 0, diff: 0, keys: {} };
	const b = base[ key ];
	const h = head[ key ];
	if ( ! h ) {
		byPage[ page ].keys.missing = ( byPage[ page ].keys.missing || 0 ) + 1;
		continue;
	}
	const diffs = Object.keys( b ).filter( ( k ) => 'contentClass' !== k && ! same( b[ k ], h[ k ] ) );
	if ( diffs.length ) {
		byPage[ page ].diff++;
		for ( const k of diffs ) {
			byPage[ page ].keys[ k ] = ( byPage[ page ].keys[ k ] || 0 ) + 1;
		}
	} else {
		byPage[ page ].same++;
	}
}
for ( const [ page, s ] of Object.entries( byPage ) ) {
	console.log( `${ page.padEnd( 20 ) } identical ${ s.same }, differ ${ s.diff }${ s.diff ? '  ' + JSON.stringify( s.keys ) : '' }` );
}
