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

function mergeBase( repo, defaultBranch ) {
	try {
		return git( repo, [ 'merge-base', 'HEAD', `refs/remotes/origin/${ defaultBranch }` ] ).trim();
	} catch {
		return '';
	}
}

// `before` is the commit the push replaced, or HEAD^1 for a pull request. A new
// branch's first push has an all-zero `before` and a force push names a commit
// the checkout doesn't hold. A push passes `defaultBranch`, and those two are
// then compared with the commit where the branch left it.
export function comparison( repo, before, defaultBranch ) {
	const changed = before && ! /^0+$/.test( before ) ? changedPaths( repo, before ) : null;
	if ( changed || ! defaultBranch ) {
		return { base: before, changed };
	}
	const base = mergeBase( repo, defaultBranch );
	return { base, changed: base ? changedPaths( repo, base ) : null, from: `the merge base with ${ defaultBranch }` };
}

if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( fs.realpathSync( process.argv[ 1 ] ) ).href ) {
	const repo = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
	const before = process.argv[ 2 ] ?? '';
	const { base, changed, from } = comparison( repo, before, process.argv[ 3 ] ?? '' );
	const mode = ciMode( base, changed );
	const because = changed ? `${ changed.length } paths differ from ${ from ?? 'the before commit' } ${ base.slice( 0, 12 ) }` : `no comparison with "${ before.slice( 0, 12 ) }"${ from ? ` or ${ from }` : '' }`;
	process.stderr.write( `${ mode }: ${ because }\n` );
	process.stdout.write( `mode=${ mode }\n` );
}
