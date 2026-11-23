import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';

export const ENGINE_DIR = 'storybook/capture';

export const NOT_RENDERED = [ /\.md$/, /^LICENSE$/, /^package\.json$/, /^tools\// ];

export const sha256 = ( data ) => crypto.createHash( 'sha256' ).update( data ).digest( 'hex' );

export function git( cwd, args, options = {} ) {
	return execFileSync( 'git', [ '-C', cwd, ...args ], { encoding: 'utf8', maxBuffer: 512 * 1024 * 1024, stdio: [ 'ignore', 'pipe', 'pipe' ], ...options } );
}

function tarString( block, start, length ) {
	const end = block.indexOf( 0, start );
	return block.toString( 'utf8', start, end === -1 || end > start + length ? start + length : end );
}

export function archivePaths( cwd, rev ) {
	const tar = git( cwd, [ 'archive', '--format=tar', rev ], { encoding: 'buffer' } );
	const paths = [];
	let longPath = null;
	for ( let offset = 0; offset + 512 <= tar.length; ) {
		const header = tar.subarray( offset, offset + 512 );
		if ( header.every( ( byte ) => byte === 0 ) ) {
			break;
		}
		const size = parseInt( tarString( header, 124, 12 ).trim() || '0', 8 );
		const type = String.fromCharCode( header[ 156 ] );
		const body = tar.subarray( offset + 512, offset + 512 + size );
		if ( type === 'x' ) {
			// A pax record is "<length> path=<value>\n". git writes one for a path over 100 bytes.
			const record = /(?:^|\n)\d+ path=([^\n]*)\n/.exec( body.toString( 'utf8' ) );
			longPath = record ? record[ 1 ] : null;
		} else if ( type === '0' || type === '\0' || type === '2' ) {
			const prefix = tarString( header, 345, 155 );
			paths.push( longPath ?? ( prefix ? `${ prefix }/` : '' ) + tarString( header, 0, 100 ) );
			longPath = null;
		}
		offset += 512 + Math.ceil( size / 512 ) * 512;
	}
	return paths;
}

export function themeInputs( cwd, rev ) {
	const blobs = new Map();
	for ( const row of git( cwd, [ 'ls-tree', '-r', '-z', rev ] ).split( '\0' ) ) {
		const [ meta, path ] = row.split( '\t' );
		if ( path ) {
			blobs.set( path, meta.split( ' ' )[ 2 ] );
		}
	}
	const rows = {};
	for ( const path of archivePaths( cwd, rev ).sort() ) {
		if ( NOT_RENDERED.some( ( pattern ) => pattern.test( path ) ) ) {
			continue;
		}
		const entry = path.split( '/' )[ 0 ];
		( rows[ entry ] ??= [] ).push( `${ path }\0${ blobs.get( path ) }` );
	}
	return Object.fromEntries( Object.keys( rows ).sort().map( ( entry ) => [ entry, sha256( rows[ entry ].join( '\n' ) ) ] ) );
}

export const engineId = ( cwd, rev ) => git( cwd, [ 'rev-parse', `${ rev }:${ ENGINE_DIR }` ] ).trim();

export const recipeId = ( environment, requests ) => sha256( JSON.stringify( { environment, requests } ) );

export function diffInputs( recorded, current ) {
	const lines = [];
	const entries = new Set( [ ...Object.keys( recorded.theme ), ...Object.keys( current.theme ) ] );
	const changed = [ ...entries ].filter( ( entry ) => recorded.theme[ entry ] !== current.theme[ entry ] ).sort();
	if ( changed.length ) {
		lines.push( `theme files changed since the capture, under: ${ changed.join( ', ' ) }` );
	}
	if ( recorded.recipe !== current.recipe ) {
		lines.push( `storybook/recipes.mjs asks for other renders than the capture made (recipe ${ recorded.recipe.slice( 0, 12 ) } recorded, ${ current.recipe.slice( 0, 12 ) } now)` );
	}
	if ( recorded.engine !== current.engine ) {
		lines.push( `${ ENGINE_DIR }/ changed since the capture (tree ${ recorded.engine.slice( 0, 12 ) } recorded, ${ current.engine.slice( 0, 12 ) } now)` );
	}
	return lines;
}
