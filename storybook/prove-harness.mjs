#!/usr/bin/env node
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { environmentNamed, resolveCommit, withSite } from './capture/capture.mjs';
import { ALLOWED_CAPS, harnessHeaders } from './capture/harness.mjs';
import { sha256 } from './capture/inputs.mjs';

const ROUTE = '/sample-page/';
const NOTICE = 'cr-contact__notice';
const editorRequest = { content: '<!-- wp:courtneyr/contact-form /-->', caps: [ 'edit_posts' ] };

let failed = 0;
let count = 0;
function claim( ok, text, observed ) {
	count++;
	failed += ok ? 0 : 1;
	process.stdout.write( `${ ok ? 'ok' : 'not ok' } ${ count } - ${ text } (${ observed })\n` );
}

function send( baseUrl, { method = 'GET', route, headers = {}, connectTo } ) {
	const { hostname, port } = new URL( baseUrl );
	return new Promise( ( resolve ) => {
		const request = http.request( { host: connectTo || hostname, port, method, path: route, headers: { Host: new URL( baseUrl ).host, ...headers }, timeout: 20000 }, ( response ) => {
			const chunks = [];
			response.on( 'data', ( chunk ) => chunks.push( chunk ) );
			response.on( 'end', () => resolve( { status: response.statusCode, headers: response.headers, body: Buffer.concat( chunks ).toString( 'utf8' ) } ) );
		} );
		request.on( 'timeout', () => request.destroy( new Error( 'timeout' ) ) );
		request.on( 'error', ( error ) => resolve( { status: 0, headers: {}, body: '', error: error.code || error.message } ) );
		request.end();
	} );
}

const describe = ( response ) => `HTTP ${ response.status || response.error }, X-Cr-Capture ${ response.headers[ 'x-cr-capture' ] ? 'present' : 'absent' }, editor notice ${ response.body.includes( NOTICE ) ? 'present' : 'absent' }`;
const inert = ( response ) => ! response.headers[ 'x-cr-capture' ] && ! response.body.includes( NOTICE );

const site = process.argv[ 2 ] === '--site' ? process.argv[ 3 ] : undefined;
const environment = environmentNamed( 't269' );
let leftovers = null;

await withSite( { environment, sha: resolveCommit( 'HEAD' ), site }, async ( { target, harness } ) => {
	const keyed = ( request ) => harnessHeaders( harness.key, request ).headers;
	const secrets = [ harness.key, sha256( harness.key ), harness.keyHashFile ];
	const leaks = ( response ) => secrets.some( ( secret ) => response.body.includes( secret ) || JSON.stringify( response.headers ).includes( secret ) );
	leftovers = { harnessFile: path.join( target.sitePath, 'wp-content', 'mu-plugins', harness.file ), keyDir: path.dirname( harness.keyHashFile ) };

	const lock = await send( target.baseUrl, { route: '/.verify-lock/target.json' } );
	process.stdout.write( `# Studio ${ lock.status === 200 && lock.body.includes( target.runId ) ? 'SERVES' : 'does not serve' } /.verify-lock/target.json (HTTP ${ lock.status || lock.error }, ${ lock.body.length } bytes${ lock.body.includes( target.runId ) ? ', run id readable' : '' })\n` );
	claim( ! leaks( lock ), 'the verify lock holds no key, key hash or key path', `HTTP ${ lock.status || lock.error }` );

	const source = await send( target.baseUrl, { route: `/wp-content/mu-plugins/${ harness.file }` } );
	claim( ! leaks( source ) && ! source.body.includes( '<?php' ), 'the harness file is not readable over HTTP', `HTTP ${ source.status || source.error }, ${ source.body.length } bytes` );

	const outside = ! harness.keyHashFile.startsWith( fs.realpathSync( target.sitePath ) + path.sep );
	const mode = ( fs.statSync( harness.keyHashFile ).mode & 0o777 ).toString( 8 );
	const dirMode = ( fs.statSync( leftovers.keyDir ).mode & 0o777 ).toString( 8 );
	claim( outside && mode === '600' && dirMode === '700', 'the key hash sits outside the site directory, readable by this user only', `file mode ${ mode }, directory mode ${ dirMode }, ${ outside ? 'outside' : 'INSIDE' } the site` );
	claim( fs.readFileSync( harness.keyHashFile, 'utf8' ) === sha256( harness.key ) && ! fs.readFileSync( leftovers.harnessFile, 'utf8' ).includes( harness.key ), 'the site side holds the SHA-256 of the key, never the key', '64 hex characters on disk' );

	const control = await send( target.baseUrl, { route: ROUTE, headers: keyed( editorRequest ) } );
	claim( control.status === 200 && Boolean( control.headers[ 'x-cr-capture' ] ) && control.body.includes( NOTICE ), 'control: the key with edit_posts renders the editor notice', describe( control ) );

	const runIdOnly = await send( target.baseUrl, { route: ROUTE, headers: { 'X-Cr-Capture-Run': target.runId, 'X-Cr-Capture-Request': keyed( editorRequest )[ 'X-Cr-Capture-Request' ] } } );
	claim( runIdOnly.status === 200 && inert( runIdOnly ), 'the run id without the key grants nothing', describe( runIdOnly ) );

	const runIdAsKey = await send( target.baseUrl, { route: ROUTE, headers: { ...keyed( editorRequest ), 'X-Cr-Capture-Key': target.runId } } );
	claim( runIdAsKey.status === 200 && inert( runIdAsKey ), 'the run id sent as the key grants nothing', describe( runIdAsKey ) );

	const wrongKey = await send( target.baseUrl, { route: ROUTE, headers: { ...keyed( editorRequest ), 'X-Cr-Capture-Key': 'f'.repeat( 64 ) } } );
	claim( wrongKey.status === 200 && inert( wrongKey ), 'a wrong key grants nothing', describe( wrongKey ) );

	for ( const cap of [ 'manage_options', 'unfiltered_html', 'edit_plugins', 'promote_users' ] ) {
		const refused = await send( target.baseUrl, { route: ROUTE, headers: keyed( { ...editorRequest, caps: [ cap ] } ) } );
		claim( refused.status === 500 && refused.body.includes( 'not on the allowlist' ) && inert( refused ), `the key asking for ${ cap } is refused`, `HTTP ${ refused.status }: ${ refused.body.slice( 0, 90 ) }` );
	}
	process.stdout.write( `# allowlist: ${ ALLOWED_CAPS.join( ', ' ) }\n` );

	const post = await send( target.baseUrl, { method: 'POST', route: ROUTE, headers: { ...keyed( editorRequest ), 'Content-Length': '0' } } );
	claim( inert( post ), 'a POST with the key grants nothing', describe( post ) );

	for ( const route of [ '/wp-json/wp/v2/posts?context=edit', '/wp-json/wp/v2/settings', '/?rest_route=/wp/v2/posts&context=edit' ] ) {
		const rest = await send( target.baseUrl, { route, headers: keyed( { ...editorRequest } ) } );
		claim( rest.status === 401 && inert( rest ), `the REST API with the key stays unauthorized: ${ route }`, `HTTP ${ rest.status }, ${ ( /"code":"([^"]+)"/.exec( rest.body ) || [] )[ 1 ] ?? 'no error code' }` );
	}

	const ajax = await send( target.baseUrl, { route: '/wp-admin/admin-ajax.php?action=heartbeat', headers: keyed( editorRequest ) } );
	claim( inert( ajax ), 'admin-ajax.php with the key grants nothing', describe( ajax ) );

	const otherHost = await send( target.baseUrl, { route: ROUTE, headers: { ...keyed( editorRequest ), Host: `attacker.example:${ new URL( target.baseUrl ).port }` } } );
	claim( inert( otherHost ), 'the key under another Host header grants nothing', describe( otherHost ) );

	const lan = Object.values( os.networkInterfaces() ).flat().find( ( address ) => address.family === 'IPv4' && ! address.internal );
	if ( lan ) {
		const remote = await send( target.baseUrl, { route: ROUTE, headers: keyed( editorRequest ), connectTo: lan.address } );
		claim( inert( remote ), `the key over this machine's ${ lan.address } address grants nothing`, remote.status ? describe( remote ) : `no connection (${ remote.error }): the server listens on loopback only` );
	} else {
		process.stdout.write( '# no non-loopback IPv4 address on this machine, so the REMOTE_ADDR gate was not exercised\n' );
	}
} );

claim( ! fs.existsSync( leftovers.harnessFile ) && ! fs.existsSync( leftovers.keyDir ), 'teardown removed the harness file and the key hash', `harness ${ fs.existsSync( leftovers.harnessFile ) ? 'PRESENT' : 'gone' }, key hash directory ${ fs.existsSync( leftovers.keyDir ) ? 'PRESENT' : 'gone' }` );
process.stdout.write( `${ failed ? 'HARNESS PROOF FAIL' : 'HARNESS PROOF PASS' } ${ count - failed } of ${ count }\n` );
process.exitCode = failed ? 1 : 0;
