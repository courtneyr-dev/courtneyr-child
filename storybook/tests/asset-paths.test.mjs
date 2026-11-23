import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { writeInside } from '../capture/capture.mjs';
import { SENTINEL_ORIGIN, assetPaths, assetSitePath, cssAssetPaths, normalize } from '../capture/format.mjs';

const HOSTILE = [
	'/wp-content/../../../tmp/x',
	'/wp-content/%2e%2e/%2e%2e/x',
	'/wp-content\\..\\x',
	'/wp-content/../../../tmp/x.css',
	'/wp-content/%2E%2E/%2E%2E/x.css',
	'/wp-content\\..\\x.css',
	'/wp-content/uploads/..%2f..%2fx.png',
	'/wp-content/uploads/\0x.png',
	'/etc/passwd',
	'//attacker.example/wp-content/x.css',
	'http://attacker.example/wp-content/x.css',
];

test( 'a path that leaves wp-content or wp-includes is rejected', () => {
	for ( const reference of HOSTILE ) {
		assert.throws( () => assetSitePath( reference ), /not mirrored|not under the site's/, reference );
	}
	assert.equal( assetSitePath( '/wp-content/themes/ollie/style.css' ), '/wp-content/themes/ollie/style.css' );
	assert.equal( assetSitePath( '../fonts/a.woff2', `${ SENTINEL_ORIGIN }/wp-content/themes/ollie/assets/styles/x.css` ), '/wp-content/themes/ollie/assets/fonts/a.woff2' );
} );

test( 'a document that names such a path gives the mirror nothing to fetch for it', () => {
	const urls = HOSTILE.filter( ( reference ) => reference.startsWith( '/' ) && ! reference.startsWith( '//' ) && ! reference.includes( '\0' ) );
	for ( const reference of urls ) {
		const doc = `<img src="${ SENTINEL_ORIGIN }${ reference }"><img src="${ SENTINEL_ORIGIN }/wp-content/uploads/ok.png">`;
		let paths = null;
		try {
			paths = assetPaths( doc );
		} catch ( error ) {
			assert.match( error.message, /not mirrored|not under the site's/, reference );
			continue;
		}
		assert.deepEqual( paths, [ '/wp-content/uploads/ok.png' ], reference );
	}
} );

test( 'a stylesheet reference that climbs out of the site roots is rejected', () => {
	assert.throws( () => cssAssetPaths( 'a{background:url(../../../../../../tmp/x.png)}', '/wp-content/themes/ollie/assets/styles/x.css' ), /not under the site's/ );
	assert.throws( () => cssAssetPaths( 'a{background:url(/wp-content/%2e%2e/%2e%2e/x.png)}', '/wp-content/themes/ollie/style.css' ), /not mirrored/ );
	assert.deepEqual( cssAssetPaths( 'a{background:url(https://example.com/x.png)}', '/wp-content/themes/ollie/style.css' ), [] );
} );

test( 'nothing is written outside the directory a file belongs in', () => {
	const sandbox = fs.mkdtempSync( path.join( fs.realpathSync( os.tmpdir() ), 'cr-capture-test-' ) );
	const site = path.join( sandbox, 'fixtures', 't269.tmp-1', 'site' );
	try {
		for ( const relative of [ 'wp-content/../../../../escaped.css', '../escaped.css', '../../escaped.css', '/etc/escaped.css', path.join( sandbox, 'escaped.css' ), 'wp-content/../..', '' ] ) {
			assert.throws( () => writeInside( site, relative, 'x' ), /refusing to write/, relative );
		}
		writeInside( site, 'wp-content/themes/ollie/style.css', 'ok' );
		const written = fs.readdirSync( sandbox, { recursive: true } ).filter( ( entry ) => fs.statSync( path.join( sandbox, entry ) ).isFile() );
		assert.deepEqual( written, [ path.join( 'fixtures', 't269.tmp-1', 'site', 'wp-content', 'themes', 'ollie', 'style.css' ) ] );
		assert.equal( fs.existsSync( '/etc/escaped.css' ), false );
	} finally {
		fs.rmSync( sandbox, { recursive: true, force: true } );
	}
} );

test( "a response that holds the run's key fails without printing it", () => {
	const key = 'k'.repeat( 64 );
	assert.throws(
		() => normalize( `<html><body>${ key }</body></html>`, 'http://localhost:8889', [ { label: "the run's key", value: key } ] ),
		( error ) => error.message.includes( "the run's key" ) && ! error.message.includes( key )
	);
} );
