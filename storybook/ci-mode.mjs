#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { git } from './capture/inputs.mjs';

// A change to one of these can break the Storybook, so CI fails on any finding
// and builds it. A change to anything else can only leave the fixtures stale,
// and a recapture needs a local WordPress Studio site, so CI warns instead.
export const STRICT_PATHS = [ /^storybook\//, /^\.github\/workflows\/storybook\.yml$/ ];

// `changed` holds the paths that differ from `base`, or null when git could not
// compare the two. A change CI cannot see counts as a strict one.
export function ciMode( base, changed ) {
	if ( ! base || /^0+$/.test( base ) || ! changed ) {
		return 'strict';
	}
	return changed.some( ( file ) => STRICT_PATHS.some( ( pattern ) => pattern.test( file ) ) ) ? 'strict' : 'advisory';
}

export function changedPaths( repo, base ) {
	try {
		return git( repo, [ 'diff', '--name-only', '-z', base, 'HEAD', '--' ] ).split( '\0' ).filter( Boolean );
	} catch {
		return null;
	}
}

if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( fs.realpathSync( process.argv[ 1 ] ) ).href ) {
	const repo = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
	const base = process.argv[ 2 ] ?? '';
	const changed = base && ! /^0+$/.test( base ) ? changedPaths( repo, base ) : null;
	const mode = ciMode( base, changed );
	const because = changed ? `${ changed.length } paths differ from ${ base.slice( 0, 12 ) }` : `no comparison with "${ base.slice( 0, 12 ) }"`;
	process.stderr.write( `${ mode }: ${ because }\n` );
	process.stdout.write( `mode=${ mode }\n` );
}
