#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { assetPaths, cssAssetPaths, isThemePath, themeRepoPath } from './capture/format.mjs';
import { archivePaths, diffInputs, engineId, git, sha256, themeInputs } from './capture/inputs.mjs';
import { resolveEnvironment } from './capture/resolve.mjs';
import { environments } from './recipes.mjs';

const REPO = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const FIXTURES = path.join( REPO, 'storybook', 'fixtures' );
const findings = [];
const find = ( subject, detail ) => findings.push( `${ subject }: ${ detail }` );

function filesUnder( dir, relative = '' ) {
	if ( ! fs.existsSync( path.join( dir, relative ) ) ) {
		return [];
	}
	return fs.readdirSync( path.join( dir, relative ), { withFileTypes: true } ).flatMap( ( entry ) =>
		entry.isDirectory() ? filesUnder( dir, path.join( relative, entry.name ) ) : [ path.join( relative, entry.name ) ]
	);
}

function checkEnvironment( name, theme, engine ) {
	const environment = resolveEnvironment( name, environments[ name ] );
	const dir = path.join( FIXTURES, name );
	if ( ! fs.existsSync( path.join( dir, 'manifest.json' ) ) ) {
		find( `STALE ${ name }`, 'storybook/recipes.mjs names this environment and no capture exists. Run node storybook/capture/capture.mjs.' );
		return null;
	}
	const manifest = JSON.parse( fs.readFileSync( path.join( dir, 'manifest.json' ), 'utf8' ) );
	for ( const line of diffInputs( manifest.inputs, { theme, recipe: environment.id, engine } ) ) {
		find( `STALE ${ name }`, `${ line }. The fixtures came from commit ${ manifest.source.commit.slice( 0, 12 ) }. Run node storybook/capture/capture.mjs and commit the result.` );
	}

	const expected = new Map( [ [ 'manifest.json', null ] ] );
	for ( const [ id, entry ] of Object.entries( manifest.fixtures ) ) {
		expected.set( path.join( 'docs', `${ id }.html` ), entry.doc );
	}
	for ( const [ assetPath, entry ] of Object.entries( manifest.assets ) ) {
		expected.set( path.join( 'site', assetPath ), entry );
	}
	const present = new Set( filesUnder( dir ) );
	for ( const file of present ) {
		if ( ! expected.has( file ) ) {
			find( `TAMPERED ${ name }`, `${ file } is not in the manifest.` );
		}
	}
	let bytes = fs.statSync( path.join( dir, 'manifest.json' ) ).size;
	for ( const [ file, recorded ] of expected ) {
		if ( ! recorded ) {
			continue;
		}
		if ( ! present.has( file ) ) {
			find( `TAMPERED ${ name }`, `${ file } is in the manifest and not on disk.` );
			continue;
		}
		const data = fs.readFileSync( path.join( dir, file ) );
		bytes += data.length;
		if ( sha256( data ) !== recorded.sha256 ) {
			find( `TAMPERED ${ name }`, `${ file } has SHA-256 ${ sha256( data ).slice( 0, 12 ) }; the capture wrote ${ recorded.sha256.slice( 0, 12 ) }. A fixture is WordPress's output, so it is never edited by hand.` );
		}
	}
	if ( bytes > environment.maxBytes ) {
		find( `TAMPERED ${ name }`, `the fixtures are ${ bytes } bytes, over the environment's ${ environment.maxBytes } byte budget.` );
	}

	const resolvable = ( sitePath, from ) => {
		if ( isThemePath( sitePath ) ) {
			if ( ! fs.existsSync( path.join( REPO, themeRepoPath( sitePath ) ) ) ) {
				find( `UNRESOLVED ${ name }`, `${ from } loads ${ sitePath }, which the theme no longer holds.` );
			}
		} else if ( ! manifest.assets[ sitePath.slice( 1 ) ] ) {
			find( `UNRESOLVED ${ name }`, `${ from } loads ${ sitePath }, which the capture did not mirror.` );
		}
	};
	for ( const [ id, entry ] of Object.entries( manifest.fixtures ) ) {
		const file = path.join( dir, 'docs', `${ id }.html` );
		if ( ! fs.existsSync( file ) ) {
			continue;
		}
		const doc = fs.readFileSync( file, 'utf8' );
		const residue = /localhost|127\.0\.0\.1|\/Users\//.exec( doc );
		if ( residue ) {
			find( `TAMPERED ${ name }`, `docs/${ id }.html holds "${ residue[ 0 ] }", which a capture never writes.` );
		}
		try {
			assetPaths( doc ).forEach( ( sitePath ) => resolvable( sitePath, `docs/${ id }.html` ) );
		} catch ( error ) {
			find( `UNSAFE ${ name }`, `docs/${ id }.html: ${ error.message }` );
		}
		for ( const source of entry.sources ) {
			if ( ! fs.existsSync( path.join( REPO, source ) ) ) {
				find( `UNRESOLVED ${ name }`, `${ id } names the source file ${ source }, which the theme no longer holds.` );
			}
		}
	}
	for ( const assetPath of Object.keys( manifest.assets ).filter( ( asset ) => asset.endsWith( '.css' ) && present.has( path.join( 'site', asset ) ) ) ) {
		try {
			cssAssetPaths( fs.readFileSync( path.join( dir, 'site', assetPath ), 'utf8' ), `/${ assetPath }` ).forEach( ( sitePath ) => resolvable( sitePath, `site/${ assetPath }` ) );
		} catch ( error ) {
			find( `UNSAFE ${ name }`, `site/${ assetPath }: ${ error.message }` );
		}
	}
	return manifest;
}

const theme = themeInputs( REPO, 'HEAD' );
const engine = engineId( REPO, 'HEAD' );
const captured = new Map();
for ( const name of Object.keys( environments ) ) {
	const manifest = checkEnvironment( name, theme, engine );
	if ( manifest ) {
		Object.keys( manifest.fixtures ).forEach( ( id ) => captured.set( `${ name }/${ id }`, manifest ) );
	}
}
for ( const entry of fs.existsSync( FIXTURES ) ? fs.readdirSync( FIXTURES ) : [] ) {
	if ( ! environments[ entry ] ) {
		find( `TAMPERED ${ entry }`, `storybook/fixtures/${ entry } has no environment in storybook/recipes.mjs.` );
	}
}

const told = new Set();
for ( const file of filesUnder( path.join( REPO, 'storybook', 'stories' ) ).filter( ( name ) => name.endsWith( '.stories.js' ) ) ) {
	for ( const match of fs.readFileSync( path.join( REPO, 'storybook', 'stories', file ), 'utf8' ).matchAll( /fixtureStory\(\s*'([^']+)'/g ) ) {
		told.add( match[ 1 ] );
		if ( ! captured.has( match[ 1 ] ) ) {
			find( 'UNLINKED', `storybook/stories/${ file } shows the fixture ${ match[ 1 ] }, which no capture holds.` );
		}
	}
}
for ( const key of captured.keys() ) {
	if ( ! told.has( key ) ) {
		find( 'UNLINKED', `the fixture ${ key } has no fixtureStory( '${ key }' ) in storybook/stories/.` );
	}
}

const shipped = archivePaths( REPO, 'HEAD' ).filter( ( file ) => file.startsWith( 'storybook/' ) );
if ( shipped.length ) {
	find( 'UNSAFE', `git archive exports ${ shipped.length } files under storybook/ (${ shipped[ 0 ] }, …). Restore "/storybook export-ignore" in .gitattributes.` );
}
for ( const file of git( REPO, [ 'ls-files', '--', 'storybook' ] ).split( '\n' ).filter( ( name ) => /\.php$|(^|\/)(block|theme)\.json$/.test( name ) ) ) {
	find( 'UNSAFE', `${ file } is a file WordPress or PHPCS can pick up from a theme directory. Keep PHP, block.json and theme.json out of storybook/.` );
}

const dirty = git( REPO, [ 'status', '--porcelain', '--', '.', ':(exclude)storybook/fixtures' ] ).trim();
if ( dirty ) {
	process.stdout.write( `# ${ dirty.split( '\n' ).length } uncommitted changes are not checked: theme files and the capture code are read from HEAD.\n` );
}
// A recapture clears these two. In CI, --advisory is passed when the change
// touches neither storybook/ nor its workflow (storybook/ci-mode.mjs).
const advisory = process.argv.includes( '--advisory' );
const blocking = advisory ? findings.filter( ( line ) => ! /^(STALE|UNRESOLVED) /.test( line ) ) : findings;
findings.forEach( ( line ) => process.stdout.write( `${ line }\n` ) );
if ( blocking.length ) {
	process.stdout.write( `FIXTURES CHECK FAIL: ${ findings.length } finding${ findings.length === 1 ? '' : 's' }\n` );
	process.exitCode = 1;
} else if ( findings.length ) {
	const message = [ ...findings, 'This check passes because the change touches neither storybook/ nor its workflow. To recapture, on a machine with WordPress Studio: node storybook/capture/capture.mjs' ].join( '\n' );
	process.stdout.write( `::warning title=Storybook fixtures are stale::${ message.replace( /%/g, '%25' ).replace( /\r/g, '%0D' ).replace( /\n/g, '%0A' ) }\n` );
	process.stdout.write( `FIXTURES STALE, NOT FAILING: ${ findings.length } finding${ findings.length === 1 ? '' : 's' } a recapture clears; this change touches neither storybook/ nor its workflow\n` );
} else {
	const commits = [ ...new Set( [ ...captured.values() ].map( ( manifest ) => manifest.source.commit.slice( 0, 12 ) ) ) ].join( ', ' );
	process.stdout.write( `FIXTURES FRESH: ${ captured.size } fixtures, captured from commit ${ commits }, match the theme at HEAD, the recipe and the capture code; ${ told.size } are shown by a story\n` );
}
