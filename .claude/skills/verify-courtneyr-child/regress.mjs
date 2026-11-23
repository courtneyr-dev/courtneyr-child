#!/usr/bin/env node
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SKILL_DIR = path.dirname( fileURLToPath( import.meta.url ) );
const THEME_SLUG = 'courtneyr-child';
const PART = 'verify-regress-head-part';
const PHP = 'verify-regress-head-php';
const PATTERN = 'verify-regress-head-pattern';
const SAVED = 'verify-regress-saved-footer';

const flags = {};
for ( let i = 2; i < process.argv.length; i += 2 ) {
	flags[ process.argv[ i ].replace( /^--/, '' ) ] = process.argv[ i + 1 ];
}
const verify = path.resolve( flags.verify || path.join( SKILL_DIR, 'verify.mjs' ) );
const site = flags.site || 'courtneyr-verify';
const base = fs.realpathSync( path.resolve( flags.checkout || path.join( SKILL_DIR, '..', '..', '..' ) ) );
const run = `regress-${ process.pid }`;
const head = path.join( fs.mkdtempSync( path.join( fs.realpathSync( os.tmpdir() ), 'verify-regress-' ) ), 'head' );

let failed = 0;
let count = 0;
function assert( ok, claim, observed ) {
	count++;
	failed += ok ? 0 : 1;
	process.stdout.write( `${ ok ? 'ok' : 'not ok' } ${ count } - ${ claim }${ observed ? ` (${ observed })` : '' }\n` );
	return ok;
}

function cli( ...argv ) {
	const result = spawnSync( process.execPath, [ verify, ...argv, '--site', site ], { encoding: 'utf8' } );
	const failing = [ ...result.stdout.matchAll( /^FAIL (\S+):/gm ) ].map( ( m ) => m[ 1 ] );
	return { code: result.status, stdout: result.stdout, lastLine: ( result.stdout + result.stderr ).trim().split( '\n' ).pop(), failing };
}

// A kept-alive socket dies when launch restarts the site, so each request opens its own.
function get( url ) {
	return new Promise( ( resolve, reject ) => {
		http.get( url, { agent: false }, ( response ) => {
			const chunks = [];
			response.on( 'data', ( chunk ) => chunks.push( chunk ) );
			response.on( 'end', () => resolve( Buffer.concat( chunks ).toString() ) );
		} ).on( 'error', reject );
	} );
}

async function renders( baseUrl, ...markers ) {
	const pages = await Promise.all( Array.from( { length: 8 }, () => get( `${ baseUrl }/` ) ) );
	return pages.filter( ( html ) => markers.every( ( marker ) => html.includes( marker ) ) ).length;
}

function writeHead() {
	fs.cpSync( base, head, { recursive: true, filter: ( source ) => ! [ '.git', 'node_modules', path.join( 'tests', 'output' ) ].includes( path.relative( base, source ) ) } );
	fs.appendFileSync( path.join( head, 'parts', 'footer.html' ), `\n<!-- wp:paragraph -->\n<p>${ PART }</p>\n<!-- /wp:paragraph -->\n` );
	fs.appendFileSync( path.join( head, 'functions.php' ), `\nadd_action( 'wp_head', static function () { echo '<meta name="${ PHP }">'; } );\n` );
}

function addPattern() {
	fs.writeFileSync( path.join( head, 'patterns', 'verify-regress.php' ), `<?php\n/**\n * Title: Verify regress\n * Slug: ${ THEME_SLUG }/verify-regress\n * Inserter: no\n */\n?>\n<!-- wp:paragraph -->\n<p>${ PATTERN }</p>\n<!-- /wp:paragraph -->\n` );
	fs.appendFileSync( path.join( head, 'parts', 'footer.html' ), `\n<!-- wp:pattern {"slug":"${ THEME_SLUG }/verify-regress"} /-->\n` );
}

function wp( sitePath, php ) {
	return spawnSync( 'studio', [ 'wp', '--path', sitePath, 'eval', php ], { encoding: 'utf8' } ).stdout.trim();
}

function pointLinkAt( sitePath, checkout ) {
	const link = path.join( sitePath, 'wp-content', 'themes', THEME_SLUG );
	fs.symlinkSync( checkout, `${ link }.regress` );
	fs.renameSync( `${ link }.regress`, link );
}

async function main() {
	writeHead();
	process.stdout.write( `# verify ${ verify }\n# base ${ base }\n# head ${ head } (same style.css and theme.json)\n` );

	const first = cli( 'launch', '--checkout', base, '--run', run );
	if ( ! assert( first.code === 0, 'launch serves the base checkout', first.lastLine ) ) {
		return;
	}
	const { baseUrl, sitePath } = JSON.parse( first.stdout );
	assert( await renders( baseUrl, PART ) === 0, 'the base page has no head marker' );

	const second = cli( 'launch', '--checkout', head, '--run', run );
	assert( second.code === 0, 'relaunch with the head checkout on the running site', second.lastLine );
	const headPages = await renders( baseUrl, PART, PHP );
	assert( headPages === 8, 'all of 8 concurrent requests render the head checkout after the relaunch', `${ headPages } of 8 do` );
	const afterRelaunch = cli( 'doctor' );
	assert( ( afterRelaunch.code === 0 ) === ( headPages === 8 ), 'doctor passes only while the page renders the checkout in the lock', `${ afterRelaunch.lastLine }; ${ headPages } of 8 pages render head` );

	addPattern();
	const afterEdit = cli( 'doctor' );
	assert( afterEdit.code === 1 && afterEdit.failing.includes( 'checkout-unchanged' ), 'doctor fails after an edit in the launched checkout', afterEdit.lastLine );

	const third = cli( 'launch', '--checkout', head, '--run', run );
	assert( third.code === 0 && cli( 'doctor' ).code === 0, 'relaunch records the edit and doctor passes again', third.lastLine );
	assert( third.code === 0 && JSON.parse( third.stdout ).treeHash !== JSON.parse( second.stdout ).treeHash, 'the relaunched target holds the edited tree hash' );
	const patternPages = await renders( baseUrl, PATTERN );
	assert( patternPages === 8, 'all of 8 concurrent requests render the pattern added after the first head launch', `${ patternPages } of 8 do` );

	const savedId = wp( sitePath, `$id = wp_insert_post( array( "post_type" => "wp_template_part", "post_name" => "footer", "post_title" => "footer", "post_status" => "publish", "post_content" => "<!-- wp:paragraph --><p>${ SAVED }</p><!-- /wp:paragraph -->" ) ); wp_set_object_terms( $id, "${ THEME_SLUG }", "wp_theme" ); echo $id;` );
	const savedPages = await renders( baseUrl, SAVED );
	const overridden = cli( 'doctor' );
	wp( sitePath, `wp_delete_post( ${ Number( savedId ) }, true );` );
	assert( overridden.code === 1 && overridden.failing.includes( 'wp-theme-files-only' ), 'doctor fails while a footer saved in the database overrides the checkout', `${ overridden.lastLine }; ${ savedPages } of 8 pages render the saved footer` );

	const functionsFile = path.join( head, 'functions.php' );
	const functionsBytes = fs.readFileSync( functionsFile );
	fs.appendFileSync( functionsFile, '\n' );
	fs.writeFileSync( functionsFile, functionsBytes );
	const afterRestore = cli( 'doctor' );
	assert( afterRestore.code === 1 && afterRestore.failing.includes( 'checkout-unchanged' ), 'doctor fails after a file in the launched checkout is edited and restored', afterRestore.lastLine );

	pointLinkAt( sitePath, base );
	const stale = cli( 'doctor', '--checkout', base );
	assert( stale.code === 1 && stale.failing.includes( 'live-theme' ), 'doctor --checkout base fails while PHP workers still render head behind a link swapped by hand', `${ stale.lastLine }; ${ await renders( baseUrl, PART ) } of 8 pages render head` );
	assert( cli( 'doctor' ).failing.includes( 'theme-dir-realpath' ), 'doctor fails for the lock too once the link no longer points at its checkout' );
}

try {
	await main();
} catch ( error ) {
	assert( false, 'the scenario ran to its end', `${ error.message }${ error.cause ? `: ${ error.cause.code || error.cause.message }` : '' }` );
} finally {
	const cleanup = cli( 'cleanup', '--run', run );
	assert( cleanup.code === 0, 'cleanup stops the site', cleanup.lastLine );
	fs.rmSync( path.dirname( head ), { recursive: true, force: true } );
}
process.stdout.write( `${ failed ? `REGRESS FAIL ${ failed } of ${ count }` : `REGRESS PASS ${ count } of ${ count }` }\n` );
process.exit( failed ? 1 : 0 );
