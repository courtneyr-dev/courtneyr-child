#!/usr/bin/env node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { cleanup, doctor, launch, servedBy, treeHash } from '../../.claude/skills/verify-courtneyr-child/verify.mjs';
import { environments } from '../recipes.mjs';
import { RULES, assetPaths, assetSitePath, cssAssetPaths, isThemePath, normalize, themeRepoPath } from './format.mjs';
import { harnessHeaders, installHarness, removeHarness } from './harness.mjs';
import { engineId, ENGINE_DIR, git, sha256, themeInputs } from './inputs.mjs';
import { resolveEnvironment } from './resolve.mjs';

const REPO = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const RECIPES = 'storybook/recipes.mjs';
const FIXTURES = path.join( REPO, 'storybook', 'fixtures' );

const log = ( line ) => process.stderr.write( `${ line }\n` );
const pass = ( step, observed ) => log( `PASS ${ step }: ${ observed }` );
const hashOf = ( text ) => ( { sha256: sha256( text ), bytes: Buffer.byteLength( text ) } );
const sortKeys = ( object ) => Object.fromEntries( Object.keys( object ).sort().map( ( key ) => [ key, object[ key ] ] ) );

function firstDifference( a, b ) {
	let at = 0;
	while ( at < a.length && a[ at ] === b[ at ] ) {
		at++;
	}
	return `offset ${ at }: …${ a.slice( Math.max( 0, at - 40 ), at + 40 ) }… against …${ b.slice( Math.max( 0, at - 40 ), at + 40 ) }…`;
}

const RENDER_WORKTREE = `${ REPO }.capture`;

function removeRenderWorktree() {
	try {
		git( REPO, [ 'worktree', 'remove', '--force', RENDER_WORKTREE ] );
	} catch {}
	fs.rmSync( RENDER_WORKTREE, { recursive: true, force: true } );
	git( REPO, [ 'worktree', 'prune' ] );
}

function addRenderWorktree( sha ) {
	removeRenderWorktree();
	git( REPO, [ 'worktree', 'add', '--detach', RENDER_WORKTREE, sha ] );
	return fs.realpathSync( RENDER_WORKTREE );
}

async function siteGet( { target, harness }, route, request ) {
	const { headers, requestHash } = request === null ? { headers: {}, requestHash: null } : harnessHeaders( harness.key, request );
	const response = await fetch( target.baseUrl + route, { redirect: 'manual', headers } );
	const body = await response.text();
	if ( response.status !== 200 ) {
		throw new Error( `GET ${ route } returned HTTP ${ response.status }${ response.headers.get( 'location' ) ? ` to ${ response.headers.get( 'location' ) }` : '' }: ${ body.slice( 0, 200 ) }` );
	}
	const served = servedBy( response.headers.get( 'x-verify-live' ), target );
	if ( ! served.ok ) {
		throw new Error( `GET ${ route }: ${ served.detail }` );
	}
	let census = null;
	if ( request !== null && request !== 'site' ) {
		census = JSON.parse( response.headers.get( 'x-cr-capture' ) || 'null' );
		if ( ! census || census.harness !== harness.file || census.request !== requestHash ) {
			throw new Error( `GET ${ route }: the response did not come through ${ harness.file } for this request (X-Cr-Capture: ${ response.headers.get( 'x-cr-capture' ) }).` );
		}
	}
	return { body, census };
}

function gitState( dir ) {
	try {
		if ( git( dir, [ 'ls-files', '--', '.' ] ).trim() === '' ) {
			return 'not a git checkout';
		}
		return { head: git( dir, [ 'rev-parse', 'HEAD' ] ).trim(), dirty: git( dir, [ 'status', '--porcelain', '--', '.' ] ).trim() !== '' };
	} catch {
		return 'not a git checkout';
	}
}

const packageState = ( version, dir ) => ( { version, git: gitState( dir ), treeHash: treeHash( dir, new Set( [ '.git', 'node_modules' ] ) ) } );

async function probeSite( session, environment ) {
	const { target, harness } = session;
	const facts = JSON.parse( ( await siteGet( session, '/', 'site' ) ).body );
	const active = facts.plugins.map( ( plugin ) => plugin.slug ).sort();
	if ( active.join() !== environment.plugins.join() ) {
		throw new Error( `environment ${ environment.name } expects the plugins ${ environment.plugins.join( ', ' ) }; ${ target.site } has ${ active.join( ', ' ) } active.` );
	}
	const marker = JSON.parse( fs.readFileSync( path.join( target.sitePath, '.verify-site' ), 'utf8' ) );
	if ( marker.seed !== environment.seed ) {
		throw new Error( `environment ${ environment.name } expects the seed ${ environment.seed }; ${ target.site } was copied from ${ marker.seed }.` );
	}
	const muPlugins = {};
	for ( const name of fs.readdirSync( facts.muDir ).sort() ) {
		if ( name === harness.file ) {
			continue;
		}
		const entry = path.join( facts.muDir, name );
		muPlugins[ name ] = fs.statSync( entry ).isDirectory() ? treeHash( entry ) : sha256( fs.readFileSync( entry ) );
	}
	return {
		name: target.site,
		seed: marker.seed,
		wordpress: facts.wordpress,
		php: facts.php,
		parentTheme: { slug: facts.parent.slug, ...packageState( facts.parent.version, facts.parent.dir ) },
		plugins: Object.fromEntries( facts.plugins.sort( ( a, b ) => a.slug.localeCompare( b.slug ) ).map( ( plugin ) => [ plugin.slug, packageState( plugin.version, plugin.dir ) ] ) ),
		muPlugins,
		content: facts.content,
	};
}

function patternFiles( checkout ) {
	const files = new Map();
	for ( const name of fs.readdirSync( path.join( checkout, 'patterns' ) ).filter( ( file ) => file.endsWith( '.php' ) ) ) {
		const header = fs.readFileSync( path.join( checkout, 'patterns', name ), 'utf8' ).slice( 0, 2000 );
		const slug = /^\s*\*\s*Slug:\s*(\S+)/m.exec( header );
		if ( slug ) {
			files.set( slug[ 1 ], { path: `patterns/${ name }`, notes: ( /^\s*\*\s*Description:\s*(.+)$/m.exec( header ) || [] )[ 1 ]?.trim() ?? '' } );
		}
	}
	return files;
}

function sourcesAndNotesFromCensus( fixture, census, checkout, patterns ) {
	const exists = ( repoPath ) => fs.existsSync( path.join( checkout, repoPath ) );
	const sources = new Set();
	const [ templateTheme, templateSlug ] = census.template.split( '//' );
	if ( templateTheme === 'courtneyr-child' && exists( `templates/${ templateSlug }.html` ) ) {
		sources.add( `templates/${ templateSlug }.html` );
	}
	for ( const key of Object.keys( census.rendered ).sort() ) {
		const [ type, name ] = [ key.slice( 0, key.indexOf( ':' ) ), key.slice( key.indexOf( ':' ) + 1 ) ];
		if ( type === 'block' ) {
			const dir = `blocks/${ name.split( '/' )[ 1 ] }`;
			[ `${ dir }/block.json`, `${ dir }/render.php` ].filter( exists ).forEach( ( file ) => sources.add( file ) );
		} else if ( type === 'pattern' && patterns.has( name ) ) {
			sources.add( patterns.get( name ).path );
		} else if ( type === 'part' && exists( `parts/${ name }.html` ) ) {
			sources.add( `parts/${ name }.html` );
		}
	}
	for ( const extra of fixture.sources ) {
		if ( ! exists( extra ) ) {
			throw new Error( `${ fixture.id }: the recipe names the source ${ extra }, which the commit does not hold.` );
		}
		sources.add( extra );
	}
	let notes = '';
	if ( fixture.subject?.startsWith( 'block:' ) ) {
		notes = JSON.parse( fs.readFileSync( path.join( checkout, 'blocks', fixture.subject.split( '/' )[ 1 ], 'block.json' ), 'utf8' ) ).description ?? '';
	} else if ( fixture.subject ) {
		notes = patterns.get( fixture.subject.slice( 'pattern:'.length ) )?.notes ?? '';
	}
	return { sources: [ ...sources ].sort(), notes };
}

async function captureFixture( session, fixture, forbidden, rawDir ) {
	const { target } = session;
	const first = await siteGet( session, fixture.route, fixture.request );
	const secondAsBrowserSends = fixture.subject === null && ! fixture.request.caps;
	const second = await siteGet( session, fixture.route, secondAsBrowserSends ? null : fixture.request );
	const normalized = normalize( first.body, target.baseUrl, forbidden );
	const again = normalize( second.body, target.baseUrl, forbidden );
	writeInside( rawDir, `${ fixture.id }.html`, first.body );
	if ( normalized.doc !== again.doc ) {
		throw new Error( `${ fixture.id }: two GETs of ${ fixture.route } differ after normalizing${ secondAsBrowserSends ? ' (the second sent no harness header)' : '' }, at ${ firstDifference( normalized.doc, again.doc ) }. Add a rule to RULES in ${ ENGINE_DIR }/format.mjs.` );
	}
	if ( fixture.subject === null ) {
		if ( normalized.component !== null || ! first.census.template ) {
			throw new Error( `${ fixture.id }: ${ fixture.route } did not render as a page through a block template (template "${ first.census.template }").` );
		}
	} else {
		const seen = first.census.rendered[ fixture.subject ];
		if ( ! seen || normalized.component === null ) {
			throw new Error( `${ fixture.id }: WordPress did not render ${ fixture.subject } on ${ fixture.route }. It rendered ${ Object.keys( first.census.rendered ).join( ', ' ) || 'nothing the census counts' }.` );
		}
		const printed = normalized.component.trim() !== '';
		if ( printed !== ( fixture.expect === 'output' ) ) {
			throw new Error( `${ fixture.id }: ${ fixture.subject } printed ${ printed ? `${ normalized.component.length } bytes` : 'nothing' } on ${ fixture.route }, and the recipe ${ fixture.empty ? 'says it prints nothing' : 'has no `empty` reason' }.` );
		}
	}
	return { fixture, census: first.census, ...normalized };
}

async function mirrorAssets( target, checkout, docs ) {
	const assets = new Map();
	const seen = new Set();
	const queue = [ ...new Set( docs.flatMap( assetPaths ) ) ].sort();
	while ( queue.length ) {
		const sitePath = assetSitePath( queue.shift() );
		if ( seen.has( sitePath ) ) {
			continue;
		}
		seen.add( sitePath );
		const response = await fetch( target.baseUrl + sitePath, { redirect: 'manual' } );
		const bytes = Buffer.from( await response.arrayBuffer() );
		if ( response.status !== 200 ) {
			throw new Error( `a fixture needs ${ sitePath }, and the site returned HTTP ${ response.status } for it.` );
		}
		if ( isThemePath( sitePath ) ) {
			const file = path.join( checkout, themeRepoPath( sitePath ) );
			if ( ! fs.existsSync( file ) || sha256( fs.readFileSync( file ) ) !== sha256( bytes ) ) {
				throw new Error( `the site serves ${ sitePath } with other bytes than the commit holds, and the Storybook serves theme files from the repo.` );
			}
			continue;
		}
		const text = bytes.toString( 'utf8' );
		if ( sitePath.endsWith( '.css' ) ) {
			if ( text.includes( new URL( target.baseUrl ).host ) ) {
				throw new Error( `${ sitePath } holds the site origin. Mirrored files are stored as served, so this needs a rule.` );
			}
			queue.push( ...cssAssetPaths( text, sitePath ) );
		}
		assets.set( sitePath.slice( 1 ), bytes );
	}
	return assets;
}

export function writeInside( root, relative, data ) {
	const file = path.resolve( root, relative );
	if ( ! file.startsWith( path.resolve( root ) + path.sep ) ) {
		throw new Error( `refusing to write ${ JSON.stringify( relative ) }: it resolves to ${ file }, outside ${ root }.` );
	}
	fs.mkdirSync( path.dirname( file ), { recursive: true } );
	fs.writeFileSync( file, data );
}

function swapEnvironment( name, manifest, captured, assets ) {
	const dir = path.join( FIXTURES, name );
	const old = fs.existsSync( path.join( dir, 'manifest.json' ) ) ? JSON.parse( fs.readFileSync( path.join( dir, 'manifest.json' ), 'utf8' ) ) : { fixtures: {} };
	fs.mkdirSync( FIXTURES, { recursive: true } );
	for ( const entry of fs.readdirSync( FIXTURES ).filter( ( item ) => item.startsWith( `${ name }.tmp-` ) || item.startsWith( `${ name }.old-` ) ) ) {
		fs.rmSync( path.join( FIXTURES, entry ), { recursive: true, force: true } );
	}
	const next = path.join( FIXTURES, `${ name }.tmp-${ process.pid }` );
	const previous = path.join( FIXTURES, `${ name }.old-${ process.pid }` );
	for ( const item of captured ) {
		writeInside( path.join( next, 'docs' ), `${ item.fixture.id }.html`, item.doc );
	}
	for ( const [ assetPath, bytes ] of assets ) {
		writeInside( path.join( next, 'site' ), assetPath, bytes );
	}
	writeInside( next, 'manifest.json', `${ JSON.stringify( manifest, null, '\t' ) }\n` );
	if ( fs.existsSync( dir ) ) {
		fs.renameSync( dir, previous );
	}
	fs.renameSync( next, dir );
	fs.rmSync( previous, { recursive: true, force: true } );
	const changes = { new: 0, changed: 0, unchanged: 0 };
	for ( const [ id, entry ] of Object.entries( manifest.fixtures ) ) {
		changes[ ! old.fixtures[ id ] ? 'new' : old.fixtures[ id ].doc.sha256 === entry.doc.sha256 ? 'unchanged' : 'changed' ]++;
	}
	return { ...changes, removed: Object.keys( old.fixtures ).filter( ( id ) => ! manifest.fixtures[ id ] ).length };
}

const runIdThisCheckoutCanRetake = ( environmentName ) => `storybook-capture-${ environmentName }-${ sha256( fs.realpathSync( REPO ) ).slice( 0, 8 ) }`;

export async function withSite( { environment, sha, site }, work ) {
	const runId = runIdThisCheckoutCanRetake( environment.name );
	let target = null;
	const teardown = async () => {
		for ( const [ step, run ] of [
			[ 'remove-harness', () => target && removeHarness( target.sitePath ) ],
			[ 'cleanup', async () => target && pass( 'cleanup', `${ ( await cleanup( { site: target.site, run: runId } ) ).site } stopped, lock released, harness and key hash removed` ) ],
			[ 'remove-worktree', removeRenderWorktree ],
		] ) {
			try {
				await run();
			} catch ( error ) {
				log( `FAIL ${ step }: ${ error.message }` );
				process.exitCode = 1;
			}
		}
	};
	const interrupted = () => teardown().finally( () => process.exit( 130 ) );
	const signals = [ 'SIGINT', 'SIGTERM', 'SIGHUP' ];
	signals.forEach( ( signal ) => process.once( signal, interrupted ) );
	try {
		const checkout = addRenderWorktree( sha );
		pass( 'worktree', `${ sha.slice( 0, 12 ) } detached at ${ checkout }` );
		target = await launch( { checkout, site: site || environment.site, seed: environment.seed, run: runId } );
		if ( target.git.head !== sha || target.git.dirty !== false ) {
			throw new Error( `verify launched ${ JSON.stringify( target.git ) }; expected a clean ${ sha }.` );
		}
		pass( 'launch', `Doctor passed; ${ target.site } serves ${ sha.slice( 0, 12 ) } (theme ${ target.version }, tree ${ target.treeHash.slice( 0, 12 ) }) at ${ target.baseUrl }` );
		return await work( { target, checkout, harness: installHarness( target.sitePath, target.baseUrl ) } );
	} finally {
		signals.forEach( ( signal ) => process.removeListener( signal, interrupted ) );
		await teardown();
	}
}

export function resolveCommit( commit ) {
	return git( REPO, [ 'rev-parse', '--verify', `${ commit }^{commit}` ] ).trim();
}

export function environmentNamed( env ) {
	if ( ! environments[ env ] ) {
		throw new Error( `storybook/recipes.mjs has no environment "${ env }". It has: ${ Object.keys( environments ).join( ', ' ) }.` );
	}
	return resolveEnvironment( env, environments[ env ] );
}

export async function capture( { env, commit, site } ) {
	const environment = environmentNamed( env );
	const sha = resolveCommit( commit );
	const uncommitted = git( REPO, [ 'status', '--porcelain', '--', ENGINE_DIR, RECIPES ] ).trim();
	if ( uncommitted ) {
		throw new Error( `Commit these first, so the manifest can name the capture code and recipe that ran:\n${ uncommitted }` );
	}
	const inputs = { theme: themeInputs( REPO, sha ), recipe: environment.id, engine: engineId( REPO, 'HEAD' ) };

	const { target, captured, assets, siteFacts } = await withSite( { environment, sha, site }, async ( session ) => {
		const { target, checkout, harness } = session;
		const forbidden = [
			{ label: "the run's key", value: harness.key },
			{ label: "the hash of the run's key", value: sha256( harness.key ) },
			{ label: 'the path of the key hash file', value: harness.keyHashFile },
			{ label: 'the home directory', value: os.homedir() },
			{ label: 'the site directory', value: target.sitePath },
			{ label: 'the render worktree path', value: checkout },
		];
		const rawDir = path.join( target.evidenceDir, 'raw' );
		fs.rmSync( rawDir, { recursive: true, force: true } );

		const siteFacts = await probeSite( session, environment );
		pass( 'site', `WordPress ${ siteFacts.wordpress }, PHP ${ siteFacts.php }, ${ siteFacts.parentTheme.slug } ${ siteFacts.parentTheme.version }, plugins ${ Object.entries( siteFacts.plugins ).map( ( [ slug, state ] ) => `${ slug } ${ state.version }` ).join( ', ' ) }, ${ siteFacts.content.posts } published posts` );

		const patterns = patternFiles( checkout );
		const captured = [];
		for ( const fixture of environment.fixtures ) {
			const item = await captureFixture( session, fixture, forbidden, rawDir );
			captured.push( { ...item, ...sourcesAndNotesFromCensus( fixture, item.census, checkout, patterns ) } );
			pass( fixture.id, `${ fixture.route } ${ Buffer.byteLength( item.doc ) } bytes${ item.component === null ? '' : `, component ${ Buffer.byteLength( item.component ) } bytes` }, two GETs agree` );
		}
		const assets = await mirrorAssets( target, checkout, captured.map( ( item ) => item.doc ) );
		pass( 'assets', `${ assets.size } files mirrored from the site` );

		const after = await probeSite( session, environment );
		if ( JSON.stringify( after ) !== JSON.stringify( siteFacts ) ) {
			throw new Error( `the site changed during the capture: ${ firstDifference( JSON.stringify( siteFacts ), JSON.stringify( after ) ) }` );
		}
		const health = await doctor( { site: target.site } );
		fs.writeFileSync( path.join( target.evidenceDir, 'doctor.json' ), JSON.stringify( health, null, 2 ) );
		fs.writeFileSync( path.join( target.evidenceDir, 'target.json' ), JSON.stringify( target, null, 2 ) );
		if ( ! health.pass ) {
			throw new Error( `Doctor fails after the capture: ${ health.checks.filter( ( check ) => ! check.ok ).map( ( check ) => `${ check.name }: ${ check.detail }` ).join( '; ' ) }` );
		}
		pass( 'doctor', `${ health.checks.length } of ${ health.checks.length } checks pass after the last request` );
		return { target, captured, assets, siteFacts };
	} );

	const manifest = {
		schema: 1,
		environment: env,
		source: { commit: sha, treeHash: target.treeHash, themeVersion: target.version },
		inputs,
		site: siteFacts,
		rules: RULES.map( ( rule ) => rule.name ),
		fixtures: Object.fromEntries( captured.map( ( item ) => [ item.fixture.id, {
			route: item.fixture.route,
			request: item.fixture.request,
			...( item.fixture.empty ? { empty: item.fixture.empty } : {} ),
			doc: hashOf( item.doc ),
			component: item.component === null ? null : hashOf( item.component ),
			census: { template: item.census.template, rendered: sortKeys( item.census.rendered ) },
			sources: item.sources,
			notes: item.notes,
			normalized: item.counts,
		} ] ) ),
		assets: Object.fromEntries( [ ...assets ].sort( ( [ a ], [ b ] ) => a.localeCompare( b ) ).map( ( [ assetPath, bytes ] ) => [ assetPath, { sha256: sha256( bytes ), bytes: bytes.length } ] ) ),
	};
	const total = Buffer.byteLength( JSON.stringify( manifest, null, '\t' ) ) + captured.reduce( ( sum, item ) => sum + Buffer.byteLength( item.doc ), 0 ) + [ ...assets.values() ].reduce( ( sum, bytes ) => sum + bytes.length, 0 );
	if ( total > environment.maxBytes ) {
		throw new Error( `the capture is ${ total } bytes, over the ${ environment.maxBytes } byte budget of environment ${ env }. Every worktree checks these files out.` );
	}
	const changes = swapEnvironment( env, manifest, captured, assets );
	return { env, sha, fixtures: captured.length, assets: assets.size, bytes: total, changes, evidenceDir: target.evidenceDir };
}

function parseArgs( argv ) {
	const args = { commit: 'HEAD' };
	for ( let i = 0; i < argv.length; i += 2 ) {
		if ( ! [ '--env', '--commit', '--site' ].includes( argv[ i ] ) || argv[ i + 1 ] === undefined ) {
			throw new Error( 'Usage: capture.mjs [--env <name>] [--commit <rev>] [--site <studio-site>]' );
		}
		args[ argv[ i ].slice( 2 ) ] = argv[ i + 1 ];
	}
	return args;
}

if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( fs.realpathSync( process.argv[ 1 ] ) ).href ) {
	try {
		const args = parseArgs( process.argv.slice( 2 ) );
		for ( const env of args.env ? [ args.env ] : Object.keys( environments ) ) {
			const report = await capture( { ...args, env } );
			process.stdout.write( `CAPTURE DONE ${ report.env }: ${ report.fixtures } fixtures (${ report.changes.new } new, ${ report.changes.changed } changed, ${ report.changes.unchanged } unchanged, ${ report.changes.removed } removed), ${ report.assets } mirrored assets, ${ report.bytes } bytes, from commit ${ report.sha }; evidence in ${ report.evidenceDir }\n` );
		}
	} catch ( error ) {
		log( `CAPTURE FAIL: ${ error.message }` );
		process.exitCode = 1;
	}
}
