#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { assertLocalTarget } from '../../../tests/routes.mjs';

const SKILL_DIR = path.dirname( fileURLToPath( import.meta.url ) );
const REPO = path.resolve( SKILL_DIR, '..', '..', '..' );
const STUDIO_ROOT = path.join( os.homedir(), 'Studio' );
const THEME_SLUG = 'courtneyr-child';
const DEFAULT_SITE = 'courtneyr-verify';
const DEFAULT_SEED = 'courtneyr-x-t269';
const MARKER = '.verify-site';
const LOCK = '.verify-lock';
const LIVE_PLUGIN = 'live-theme.php';
const LIVE_HEADER = 'x-verify-live';
const TREE_SKIP = new Set( [ '.git', 'node_modules', path.join( 'tests', 'output' ) ] );

const log = ( line ) => process.stderr.write( `${ line }\n` );

function sh( cmd, args, opts = {} ) {
	return execFileSync( cmd, args, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ], ...opts } );
}

function studioSites() {
	return JSON.parse( sh( 'studio', [ 'list', '--format', 'json' ] ) );
}

function studioSite( sitePath ) {
	return studioSites().find( ( s ) => s.path === sitePath ) || null;
}

function wpEval( sitePath, php ) {
	return sh( 'studio', [ 'wp', '--path', sitePath, 'eval', php ] ).trim();
}

function sha256( data ) {
	return crypto.createHash( 'sha256' ).update( data ).digest( 'hex' );
}

function* treeEntries( root, skip, rel = '' ) {
	for ( const name of fs.readdirSync( path.join( root, rel ) ).sort() ) {
		const child = path.join( rel, name );
		if ( skip.has( name ) || skip.has( child ) ) {
			continue;
		}
		const stat = fs.lstatSync( path.join( root, child ) );
		if ( stat.isDirectory() ) {
			yield* treeEntries( root, skip, child );
		} else {
			yield { child, stat };
		}
	}
}

export function treeHash( root, skip = new Set() ) {
	const rows = [];
	for ( const { child, stat } of treeEntries( root, skip ) ) {
		if ( stat.isSymbolicLink() ) {
			rows.push( `${ child }\0link:${ fs.readlinkSync( path.join( root, child ) ) }` );
		} else if ( stat.isFile() ) {
			rows.push( `${ child }\0${ sha256( fs.readFileSync( path.join( root, child ) ) ) }` );
		}
	}
	return sha256( rows.join( '\n' ) );
}

function themeVersion( css ) {
	return ( /^\s*Version:\s*(\S+)/m.exec( css ) || [] )[ 1 ] || null;
}

function parseCheckout( input ) {
	const checkout = fs.realpathSync( path.resolve( input ) );
	const css = fs.readFileSync( path.join( checkout, 'style.css' ), 'utf8' );
	if ( ! /^\s*Theme Name:\s*Courtneyr Child\s*$/m.test( css ) ) {
		throw new Error( `${ checkout }/style.css is not the Courtneyr Child theme header.` );
	}
	return { checkout, version: themeVersion( css ), styleSha: sha256( css ) };
}

function gitState( checkout ) {
	try {
		const head = sh( 'git', [ '-C', checkout, 'rev-parse', 'HEAD' ] ).trim();
		const dirty = sh( 'git', [ '-C', checkout, 'status', '--porcelain' ] ).trim() !== '';
		return { head, dirty };
	} catch {
		return { head: null, dirty: null };
	}
}

function checkoutState( checkout ) {
	return { checkout, treeHash: treeHash( checkout, TREE_SKIP ), git: gitState( checkout ) };
}

function sitePathFor( site ) {
	if ( ! /^[a-z0-9-]+$/.test( site ) ) {
		throw new Error( `Site name "${ site }" must be lowercase letters, digits and hyphens.` );
	}
	return path.join( STUDIO_ROOT, site );
}

function pidAlive( pid ) {
	try {
		process.kill( pid, 0 );
		return true;
	} catch ( error ) {
		return error.code === 'EPERM';
	}
}

function tryJson( read ) {
	try {
		return JSON.parse( read() );
	} catch {
		return null;
	}
}

function writeJson( file, data ) {
	fs.writeFileSync( `${ file }.tmp`, JSON.stringify( data, null, 2 ) );
	fs.renameSync( `${ file }.tmp`, file );
}

const lockFile = ( sitePath ) => path.join( sitePath, LOCK, 'target.json' );
const readLock = ( sitePath ) => tryJson( () => fs.readFileSync( lockFile( sitePath ), 'utf8' ) );
const markerFile = ( sitePath ) => path.join( sitePath, MARKER );
const readMarker = ( sitePath ) => tryJson( () => fs.readFileSync( markerFile( sitePath ), 'utf8' ) );

function buryStaleLock( lockDir, held ) {
	fs.renameSync( lockDir, `${ lockDir }.stale-${ held.launcherPid }-${ Date.parse( held.startedAt ) }` );
}

// rename(2) onto a non-empty directory fails, so the lock appears with its owner already inside.
function acquireLock( sitePath, owner, running ) {
	const lockDir = path.join( sitePath, LOCK );
	const held = readLock( sitePath );
	if ( held && held.runId === owner.runId ) {
		if ( held.launcherPid !== process.pid && pidAlive( held.launcherPid ) ) {
			throw new Error( `REFUSED: run ${ held.runId } on ${ owner.site } belongs to live process ${ held.launcherPid }. Wait until it exits.` );
		}
		writeJson( lockFile( sitePath ), owner );
		return;
	}
	if ( held ) {
		if ( running || pidAlive( held.launcherPid ) ) {
			throw new Error( `REFUSED: ${ owner.site } is held by run ${ held.runId } (checkout ${ held.checkout }, since ${ held.startedAt }). Wait for its cleanup, or launch with --site <another-name>.` );
		}
		try {
			buryStaleLock( lockDir, held );
		} catch ( error ) {
			throw new Error( `REFUSED: another launch is replacing the stale lock on ${ owner.site }. ${ error.code }` );
		}
		log( `Moved aside the stale lock from run ${ held.runId }: the site is stopped and launcher pid ${ held.launcherPid } is gone.` );
	} else if ( running ) {
		throw new Error( `REFUSED: ${ owner.site } is running without a verify lock, so another session started it. Leave it alone, or launch with --site <another-name>.` );
	}
	const tmp = `${ lockDir }.${ process.pid }`;
	fs.mkdirSync( tmp );
	fs.writeFileSync( path.join( tmp, 'target.json' ), JSON.stringify( owner, null, 2 ) );
	try {
		fs.renameSync( tmp, lockDir );
	} catch ( error ) {
		fs.rmSync( tmp, { recursive: true, force: true } );
		const winner = readLock( sitePath );
		throw new Error( `REFUSED: another launch took ${ owner.site } first (run ${ winner ? winner.runId : 'unknown' }). ${ error.code }` );
	}
}

function seedFingerprint( seedPath ) {
	return treeHash( path.join( seedPath, 'wp-content' ) );
}

function copySeed( site, sitePath, seedName ) {
	const seed = studioSites().find( ( s ) => s.name === seedName );
	if ( ! seed ) {
		throw new Error( `Seed site ${ seedName } is not registered in Studio.` );
	}
	if ( seed.running ) {
		throw new Error( `Seed site ${ seedName } is running. Copying a live SQLite file can tear it; wait until it stops.` );
	}
	for ( const name of fs.readdirSync( STUDIO_ROOT ) ) {
		const [ , pid ] = new RegExp( `^${ site }\\.copying-(\\d+)$` ).exec( name ) || [];
		if ( pid && ! pidAlive( Number( pid ) ) ) {
			log( `Removing ${ name }: launcher pid ${ pid } died while it copied the seed.` );
			fs.rmSync( path.join( STUDIO_ROOT, name ), { recursive: true, force: true } );
		}
	}
	const copy = `${ sitePath }.copying-${ process.pid }`;
	const before = seedFingerprint( seed.path );
	log( `Seed ${ seedName } wp-content hash before copy: ${ before }` );
	sh( 'cp', [ '-R', seed.path, copy ] );
	const after = seedFingerprint( seed.path );
	log( `Seed ${ seedName } wp-content hash after copy:  ${ after }` );
	if ( before !== after ) {
		fs.rmSync( copy, { recursive: true, force: true } );
		throw new Error( `Seed ${ seedName } changed during the copy. Launch again.` );
	}
	fs.rmSync( path.join( copy, 'wp-content', 'themes', THEME_SLUG ), { force: true, recursive: true } );
	writeJson( markerFile( copy ), { seed: seedName, contentUrl: seed.url, createdAt: new Date().toISOString() } );
	try {
		fs.renameSync( copy, sitePath );
	} catch ( error ) {
		fs.rmSync( copy, { recursive: true, force: true } );
		if ( ! [ 'ENOTEMPTY', 'EEXIST' ].includes( error.code ) ) {
			throw error;
		}
		log( `Another launch placed ${ sitePath } first. Dropped this copy.` );
	}
}

const themeLink = ( sitePath ) => path.join( sitePath, 'wp-content', 'themes', THEME_SLUG );

function linkId( link ) {
	const stat = fs.lstatSync( link, { throwIfNoEntry: false } );
	return stat ? `${ stat.ino }@${ stat.birthtimeMs }` : null;
}

function pointTheme( sitePath, checkout ) {
	const link = themeLink( sitePath );
	const tmp = `${ link }.${ process.pid }`;
	fs.rmSync( tmp, { force: true } );
	fs.symlinkSync( checkout, tmp );
	fs.renameSync( tmp, link );
	return linkId( link );
}

async function fetchBody( url ) {
	const response = await fetch( url, { redirect: 'manual' } );
	return { status: response.status, body: Buffer.from( await response.arrayBuffer() ), live: response.headers.get( LIVE_HEADER ) };
}

export function servedBy( header, { sitePath, checkout } ) {
	const live = tryJson( () => header );
	if ( ! live ) {
		return { ok: false, detail: `the response has no ${ LIVE_HEADER } header, so mu-plugins/verify-${ LIVE_PLUGIN } did not run` };
	}
	const ok = live.site === fs.realpathSync( sitePath ) && live.theme.length === 1 && live.theme[ 0 ] === checkout;
	return { ok, detail: `the PHP worker serves ${ live.site } and renders the theme from ${ live.theme.join( ' and ' ) }; expected ${ sitePath } and ${ checkout }` };
}

async function waitReady( baseUrl, timeoutMs = 120000 ) {
	const deadline = Date.now() + timeoutMs;
	let last = 'no response';
	while ( Date.now() < deadline ) {
		try {
			const { status, body } = await fetchBody( `${ baseUrl }/` );
			if ( status === 200 && body.includes( `/wp-content/themes/${ THEME_SLUG }/` ) ) {
				return;
			}
			last = `HTTP ${ status }`;
		} catch ( error ) {
			last = error.cause ? error.cause.code : error.message;
		}
		await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );
	}
	throw new Error( `${ baseUrl }/ was not ready after ${ timeoutMs / 1000 }s (last: ${ last }).` );
}

export async function launch( { checkout: checkoutInput, site = DEFAULT_SITE, seed = DEFAULT_SEED, run } ) {
	const { checkout, version } = parseCheckout( checkoutInput );
	const sitePath = sitePathFor( site );
	if ( ! fs.existsSync( sitePath ) ) {
		copySeed( site, sitePath, seed );
	}
	const marker = readMarker( sitePath );
	if ( ! marker ) {
		throw new Error( `REFUSED: ${ sitePath } has no ${ MARKER } marker, so this skill did not create it. Never launch into another session's site.` );
	}
	const runId = run || `${ new Date().toISOString().replace( /[-:.TZ]/g, '' ).slice( 0, 14 ) }-${ crypto.randomBytes( 3 ).toString( 'hex' ) }`;
	const startedAt = new Date().toISOString();
	const owner = {
		runId,
		...checkoutState( checkout ),
		version,
		site,
		sitePath,
		evidenceDir: path.join( REPO, 'tests', 'output', 'verify', runId ),
		launcherPid: process.pid,
		startedAt,
	};
	acquireLock( sitePath, owner, Boolean( studioSite( sitePath )?.running ) );
	try {
		// A PHP worker keeps resolving the theme link to its old target for minutes, so no worker may outlive a repoint.
		stopSite( sitePath );
		if ( ! studioSite( sitePath ) ) {
			sh( 'studio', [ 'create', '--path', sitePath, '--name', site, '--no-start', '--skip-browser' ] );
		}
		const studio = studioSite( sitePath );
		assertLocalTarget( studio.url );
		if ( marker.contentUrl !== studio.url ) {
			sh( 'studio', [ 'wp', '--path', sitePath, 'search-replace', marker.contentUrl, studio.url, '--all-tables', '--quiet' ] );
			writeJson( markerFile( sitePath ), { ...marker, contentUrl: studio.url } );
			log( `Rewrote ${ site } content from ${ marker.contentUrl } to ${ studio.url }.` );
		}
		const target = { ...owner, port: studio.port, baseUrl: studio.url, linkId: pointTheme( sitePath, checkout ) };
		fs.copyFileSync( path.join( SKILL_DIR, LIVE_PLUGIN ), path.join( sitePath, 'wp-content', 'mu-plugins', `verify-${ LIVE_PLUGIN }` ) );
		if ( wpEval( sitePath, 'echo get_option( "stylesheet" );' ) !== THEME_SLUG ) {
			sh( 'studio', [ 'wp', '--path', sitePath, 'theme', 'activate', THEME_SLUG ] );
		}
		// WordPress keys this cache by theme path and Version, so it outlives a checkout that shares both.
		wpEval( sitePath, 'wp_get_theme()->delete_pattern_cache();' );
		writeJson( lockFile( sitePath ), target );
		sh( 'studio', [ 'start', '--path', sitePath, '--skip-browser', '--skip-log-details' ] );
		await waitReady( target.baseUrl );
		const failed = ( await doctor( { site } ) ).checks.filter( ( c ) => ! c.ok );
		if ( failed.length ) {
			throw new Error( `REFUSED: ${ site } started, but doctor fails.\n${ failed.map( ( c ) => `FAIL ${ c.name }: ${ c.detail }` ).join( '\n' ) }` );
		}
		fs.mkdirSync( target.evidenceDir, { recursive: true } );
		return target;
	} catch ( error ) {
		stopSite( sitePath );
		fs.rmSync( path.join( sitePath, LOCK ), { recursive: true, force: true } );
		throw error;
	}
}

function check( name, ok, detail ) {
	return { name, ok: Boolean( ok ), detail };
}

export async function doctor( { site = DEFAULT_SITE, checkout: checkoutInput } = {} ) {
	const sitePath = sitePathFor( site );
	const lock = readLock( sitePath );
	const studio = studioSite( sitePath );
	const results = [];
	if ( ! studio ) {
		return { site, pass: false, checks: [ check( 'site-registered', false, `Studio has no site at ${ sitePath }` ) ] };
	}
	assertLocalTarget( studio.url );
	if ( ! checkoutInput && ! lock ) {
		throw new Error( `${ site } has no verify lock. Pass --checkout <path> to say which checkout it should serve.` );
	}
	const expected = parseCheckout( checkoutInput || lock.checkout );
	const marker = readMarker( sitePath );
	results.push( check( 'site-running', studio.running, `${ studio.url } running=${ studio.running }` ) );
	results.push( check( 'site-provisioned', marker?.contentUrl === studio.url, marker ? `site content uses ${ marker.contentUrl }; Studio serves ${ studio.url }` : `no ${ MARKER } marker, so this skill did not create the site` ) );
	results.push( check( 'lock-held', lock, lock ? `run ${ lock.runId } since ${ lock.startedAt }` : 'no verify lock; launch did not start this instance' ) );

	const link = themeLink( sitePath );
	if ( lock ) {
		const now = checkoutState( expected.checkout );
		const drift = Object.keys( now )
			.filter( ( key ) => JSON.stringify( now[ key ] ) !== JSON.stringify( lock[ key ] ) )
			.map( ( key ) => `${ key }: launch recorded ${ JSON.stringify( lock[ key ] ) }, now ${ JSON.stringify( now[ key ] ) }` );
		// PHP keeps running a file's old code for about 2 s after the file changes, so a file edited and then restored still counts.
		const touched = [ ...treeEntries( expected.checkout, TREE_SKIP ) ].filter( ( entry ) => entry.stat.ctimeMs > Date.parse( lock.startedAt ) ).map( ( entry ) => entry.child );
		if ( touched.length && now.checkout === lock.checkout ) {
			drift.push( `changed after launch: ${ touched.slice( 0, 5 ).join( ', ' ) }${ touched.length > 5 ? ` and ${ touched.length - 5 } more` : '' }` );
		}
		const linkNow = linkId( link );
		results.push( check( 'lock-url', lock.baseUrl === studio.url, `launch recorded ${ lock.baseUrl }; Studio serves ${ studio.url }` ) );
		results.push( check( 'checkout-unchanged', drift.length === 0, drift.length ? `${ drift.join( '; ' ) }. Relaunch to serve and record the change.` : `tree hash ${ now.treeHash.slice( 0, 12 ) } and git ${ JSON.stringify( now.git ) } are what launch recorded` ) );
		results.push( check( 'theme-link-unchanged', linkNow === lock.linkId, linkNow === lock.linkId ? `the theme link is the one launch made (${ linkNow })` : `theme link ${ linkNow } is not the one launch made (${ lock.linkId }). PHP workers started before a swap keep rendering the old target. Relaunch.` ) );
	}
	const linked = fs.existsSync( link ) ? fs.realpathSync( link ) : null;
	results.push( check( 'theme-dir-realpath', linked === expected.checkout, `site theme dir -> ${ linked }; expected ${ expected.checkout }` ) );

	let wp = null;
	try {
		wp = JSON.parse( wpEval( sitePath, `echo wp_json_encode( array( "stylesheet" => get_stylesheet(), "template" => get_template(), "dir" => realpath( get_stylesheet_directory() ), "version" => wp_get_theme()->get( "Version" ), "saved" => array_merge( array_values( array_map( fn( $t ) => $t->id, array_filter( array_merge( get_block_templates(), get_block_templates( array(), "wp_template_part" ) ), fn( $t ) => "custom" === $t->source ) ) ), array_map( fn( $key ) => "global styles " . $key, array_keys( array_diff_key( WP_Theme_JSON_Resolver::get_user_data()->get_raw_data(), array( "version" => 1 ) ) ) ) ) ) );` ) );
	} catch ( error ) {
		wp = { error: error.message.split( '\n' )[ 0 ] };
	}
	results.push( check( 'wp-active-theme', wp.stylesheet === THEME_SLUG && wp.template === 'ollie', `stylesheet=${ wp.stylesheet } template=${ wp.template }${ wp.error ? ` error=${ wp.error }` : '' }` ) );
	results.push( check( 'wp-theme-dir', wp.dir === expected.checkout, `WordPress loads ${ wp.dir }; expected ${ expected.checkout }` ) );
	results.push( check( 'wp-theme-version', wp.version === expected.version, `WordPress reports ${ wp.version }; checkout style.css says ${ expected.version }` ) );
	const saved = wp.saved || [ `unknown, ${ wp.error }` ];
	results.push( check( 'wp-theme-files-only', saved.length === 0, saved.length ? `the database holds saved copies that override theme files: ${ saved.join( ', ' ) }. Delete them, or launch a fresh --site.` : 'no template, template part or global style is saved in the database' ) );

	if ( studio.running ) {
		for ( const file of [ 'style.css', 'theme.json' ] ) {
			const served = await fetchBody( `${ studio.url }/wp-content/themes/${ THEME_SLUG }/${ file }` ).catch( ( error ) => ( { status: error.message, body: Buffer.alloc( 0 ) } ) );
			const local = sha256( fs.readFileSync( path.join( expected.checkout, file ) ) );
			const got = sha256( served.body );
			results.push( check( `served-${ file }`, served.status === 200 && got === local, `HTTP ${ served.status } sha256 ${ got.slice( 0, 12 ) }; checkout ${ local.slice( 0, 12 ) }${ file === 'style.css' ? ` (served Version ${ themeVersion( served.body.toString() ) })` : '' }` ) );
		}
		const home = await fetchBody( `${ studio.url }/` ).catch( ( error ) => ( { status: error.message, body: Buffer.alloc( 0 ) } ) );
		const stamp = `/wp-content/themes/${ THEME_SLUG }/assets/css/tokens.css?ver=${ expected.version }`;
		const served = servedBy( home.live, { sitePath, checkout: expected.checkout } );
		results.push( check( 'rendered-version', home.status === 200 && home.body.includes( stamp ), `GET / HTTP ${ home.status }; page ${ home.body.includes( stamp ) ? 'links' : 'does not link' } ${ stamp }` ) );
		results.push( check( 'live-theme', served.ok, `GET / ${ served.detail }` ) );
	}
	return { site, baseUrl: studio.url, expected, lock, pass: results.every( ( r ) => r.ok ), checks: results };
}

function stopSite( sitePath ) {
	if ( studioSite( sitePath )?.running ) {
		sh( 'studio', [ 'stop', '--path', sitePath ] );
	}
}

function ownedLock( site, run, force ) {
	const sitePath = sitePathFor( site );
	const lock = readLock( sitePath );
	if ( ! lock ) {
		throw new Error( `REFUSED: ${ site } has no verify lock. Launch it first.` );
	}
	if ( lock.runId !== run && ! force ) {
		throw new Error( `REFUSED: ${ site } belongs to run ${ lock.runId }, not ${ run }. Pass the run id that launch printed.` );
	}
	return { sitePath, lock };
}

export async function drive( { feature, site = DEFAULT_SITE, run } ) {
	const { lock } = ownedLock( site, run, false );
	const health = await doctor( { site } );
	if ( ! health.pass ) {
		throw new Error( `REFUSED: doctor failed (${ health.checks.filter( ( c ) => ! c.ok ).map( ( c ) => c.name ).join( ', ' ) }). Run doctor and fix the instance before driving it.` );
	}
	const journeyFile = path.join( SKILL_DIR, 'drive', `${ feature }.mjs` );
	if ( ! fs.existsSync( journeyFile ) ) {
		throw new Error( `No journey for "${ feature }". Known: ${ fs.readdirSync( path.join( SKILL_DIR, 'drive' ) ).map( ( f ) => f.replace( /\.mjs$/, '' ) ).join( ', ' ) }` );
	}
	const out = path.join( lock.evidenceDir, feature );
	fs.mkdirSync( out, { recursive: true } );
	fs.writeFileSync( path.join( out, 'doctor.json' ), JSON.stringify( health, null, 2 ) );
	fs.writeFileSync( path.join( out, 'target.json' ), JSON.stringify( lock, null, 2 ) );
	const { chromium } = await import( '@playwright/test' );
	const channel = process.env.CR_BROWSER_CHANNEL ?? 'chrome';
	const browser = await chromium.launch( channel ? { channel } : {} );
	const steps = [];
	let error = null;
	try {
		const journey = ( await import( pathToFileURL( journeyFile ).href ) ).default;
		await journey( { browser, baseUrl: lock.baseUrl, out, served: ( response ) => servedBy( response.headers()[ LIVE_HEADER ], lock ), step: ( s ) => {
			steps.push( s );
			log( `${ s.ok ? 'PASS' : 'FAIL' } ${ s.name }: ${ s.observed }` );
		} } );
	} catch ( e ) {
		error = e.message.split( '\n' )[ 0 ];
	} finally {
		await browser.close();
	}
	const result = { feature, runId: lock.runId, checkout: lock.checkout, git: lock.git, version: lock.version, treeHash: lock.treeHash, baseUrl: lock.baseUrl, pass: ! error && steps.length > 0 && steps.every( ( s ) => s.ok ), error, steps, finishedAt: new Date().toISOString() };
	fs.writeFileSync( path.join( out, 'result.json' ), JSON.stringify( result, null, 2 ) );
	return { ...result, out };
}

export async function cleanup( { site = DEFAULT_SITE, run, force = false } ) {
	const { sitePath, lock } = ownedLock( site, run, force );
	stopSite( sitePath );
	if ( studioSite( sitePath ).running ) {
		throw new Error( `${ site } still reports running after studio stop. The lock stays; rerun cleanup.` );
	}
	for ( const name of fs.readdirSync( sitePath ).filter( ( entry ) => entry === LOCK || entry.startsWith( `${ LOCK }.stale-` ) ) ) {
		fs.rmSync( path.join( sitePath, name ), { recursive: true, force: true } );
	}
	return { site, stopped: true, runId: lock.runId, evidenceDir: lock.evidenceDir, evidenceKept: fs.existsSync( lock.evidenceDir ) };
}

function parseArgs( argv ) {
	const [ command, ...rest ] = argv;
	const args = { command, positional: [] };
	for ( let i = 0; i < rest.length; i++ ) {
		if ( rest[ i ] === '--force' ) {
			args.force = true;
		} else if ( rest[ i ].startsWith( '--' ) ) {
			args[ rest[ i ].slice( 2 ) ] = rest[ ++i ];
		} else {
			args.positional.push( rest[ i ] );
		}
	}
	return args;
}

const commands = {
	async launch( args ) {
		if ( ! args.checkout ) {
			throw new Error( 'launch needs --checkout <path-to-theme-checkout>.' );
		}
		const target = await launch( args );
		log( `LAUNCH READY ${ target.baseUrl } run ${ target.runId } checkout ${ target.checkout }` );
		process.stdout.write( `${ JSON.stringify( target ) }\n` );
	},
	async doctor( args ) {
		const result = await doctor( args );
		for ( const c of result.checks ) {
			process.stdout.write( `${ c.ok ? 'PASS' : 'FAIL' } ${ c.name }: ${ c.detail }\n` );
		}
		const failed = result.checks.filter( ( c ) => ! c.ok ).map( ( c ) => c.name );
		process.stdout.write( result.pass ? `DOCTOR PASS ${ result.site } serves ${ result.expected.checkout }\n` : `DOCTOR FAIL ${ result.site }: ${ failed.join( ', ' ) }\n` );
		process.exitCode = result.pass ? 0 : 1;
	},
	async drive( args ) {
		const result = await drive( { ...args, feature: args.positional[ 0 ] } );
		process.stdout.write( `DRIVE ${ result.pass ? 'PASS' : 'FAIL' } ${ result.feature }${ result.error ? `: ${ result.error }` : '' }; evidence in ${ result.out }\n` );
		process.exitCode = result.pass ? 0 : 1;
	},
	async cleanup( args ) {
		const result = await cleanup( args );
		process.stdout.write( `CLEANUP DONE ${ result.site } stopped, lock released; evidence ${ result.evidenceKept ? 'kept' : 'MISSING' } at ${ result.evidenceDir }\n` );
	},
};

if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( fs.realpathSync( process.argv[ 1 ] ) ).href ) {
	const args = parseArgs( process.argv.slice( 2 ) );
	const command = commands[ args.command ];
	if ( ! command ) {
		log( 'Usage: verify.mjs launch --checkout <path> [--site <name>] [--seed <site>] [--run <id>]\n       verify.mjs doctor [--site <name>] [--checkout <path>]\n       verify.mjs drive <feature> --run <id> [--site <name>]\n       verify.mjs cleanup --run <id> [--site <name>] [--force]' );
		process.exit( 2 );
	}
	command( args ).catch( ( error ) => {
		log( error.message );
		process.exit( 1 );
	} );
}
