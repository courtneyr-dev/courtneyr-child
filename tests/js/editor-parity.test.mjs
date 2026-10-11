// `node tests/js/editor-parity.test.mjs`: checks the pure helpers of
// assets/js/editor-parity.js without a browser (PKIW issue 233).
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const require = createRequire( import.meta.url );
const api = require( path.join( root, 'assets/js/editor-parity.js' ) );

let failed = 0;
function check( label, fn ) {
	try {
		fn();
		console.log( 'ok   ' + label );
	} catch ( error ) {
		failed++;
		console.log( 'FAIL ' + label + '\n     ' + error.message.split( '\n' )[ 0 ] );
	}
}

class FakeClassList {
	constructor( names = [] ) {
		this.names = new Set( names );
	}
	add( ...list ) {
		list.forEach( ( name ) => this.names.add( name ) );
	}
	remove( ...list ) {
		list.forEach( ( name ) => this.names.delete( name ) );
	}
	contains( name ) {
		return this.names.has( name );
	}
}

function fakeBody( names = [] ) {
	return { classList: new FakeClassList( names ), dataset: {} };
}

const kinds = {
	listen: { classes: [ 'single-post', 'kind-listen' ], part: 'kind-listen' },
	watch: { classes: [ 'single-post', 'kind-watch', 'cr-extra' ], part: '' },
};
const terms = [
	{ id: 4, slug: 'watch' },
	{ id: 9, slug: 'listen' },
];

check( 'kindSlugFromIds returns the slug of the first id found in the terms', () => {
	assert.equal( api.kindSlugFromIds( [ 9, 4 ], terms ), 'listen' );
	assert.equal( api.kindSlugFromIds( [ 4 ], terms ), 'watch' );
} );
check( 'kindSlugFromIds skips ids that are not in the terms', () => {
	assert.equal( api.kindSlugFromIds( [ 77, 4 ], terms ), 'watch' );
} );
check( 'kindSlugFromIds is empty for no match, no ids or bad input', () => {
	assert.equal( api.kindSlugFromIds( [ 77 ], terms ), '' );
	assert.equal( api.kindSlugFromIds( [], terms ), '' );
	assert.equal( api.kindSlugFromIds( undefined, terms ), '' );
	assert.equal( api.kindSlugFromIds( [ 4 ], null ), '' );
} );

check( 'classesFor returns the classes of a registered kind', () => {
	assert.deepEqual( api.classesFor( 'watch', kinds ), [ 'single-post', 'kind-watch', 'cr-extra' ] );
} );
check( 'classesFor is empty for an unknown kind, no kind or no registry', () => {
	assert.deepEqual( api.classesFor( 'nope', kinds ), [] );
	assert.deepEqual( api.classesFor( '', kinds ), [] );
	assert.deepEqual( api.classesFor( 'watch', undefined ), [] );
} );

check( 'syncClasses adds the wanted classes and records them', () => {
	const body = fakeBody( [ 'editor-styles-wrapper' ] );
	api.syncClasses( body, [ 'single-post', 'kind-listen' ] );
	assert.ok( body.classList.contains( 'single-post' ) );
	assert.ok( body.classList.contains( 'kind-listen' ) );
	assert.ok( body.classList.contains( 'editor-styles-wrapper' ) );
	assert.equal( body.dataset.crEditorParity, 'single-post kind-listen' );
} );
check( 'syncClasses swaps kinds and removes only the classes it added', () => {
	const body = fakeBody( [ 'editor-styles-wrapper' ] );
	api.syncClasses( body, [ 'single-post', 'kind-listen' ] );
	api.syncClasses( body, [ 'single-post', 'kind-watch' ] );
	assert.ok( ! body.classList.contains( 'kind-listen' ) );
	assert.ok( body.classList.contains( 'kind-watch' ) );
	assert.ok( body.classList.contains( 'single-post' ) );
	assert.ok( body.classList.contains( 'editor-styles-wrapper' ) );
	assert.equal( body.dataset.crEditorParity, 'single-post kind-watch' );
} );
check( 'syncClasses with no classes clears what it added and nothing else', () => {
	const body = fakeBody( [ 'editor-styles-wrapper', 'post-type-post' ] );
	api.syncClasses( body, [ 'single-post', 'kind-listen' ] );
	api.syncClasses( body, [] );
	assert.ok( ! body.classList.contains( 'single-post' ) );
	assert.ok( ! body.classList.contains( 'kind-listen' ) );
	assert.ok( body.classList.contains( 'editor-styles-wrapper' ) );
	assert.ok( body.classList.contains( 'post-type-post' ) );
	assert.equal( body.dataset.crEditorParity, '' );
} );
check( 'syncClasses keeps a wanted class the page already carried when the kind changes', () => {
	const body = fakeBody( [ 'single-post' ] );
	api.syncClasses( body, [ 'single-post', 'kind-listen' ] );
	api.syncClasses( body, [] );
	assert.ok( body.classList.contains( 'single-post' ), 'a class that was there before the sync stays' );
	assert.ok( ! body.classList.contains( 'kind-listen' ) );
} );
check( 'syncClasses is a no-op for a missing body', () => {
	api.syncClasses( null, [ 'kind-listen' ] );
} );

check( 'partOverride swaps the router slug for the kind part', () => {
	const next = api.partOverride( { slug: 'kind', theme: 'courtneyr-child', tagName: 'div' }, 'listen', kinds, 'kind' );
	assert.deepEqual( next, { slug: 'kind-listen', theme: 'courtneyr-child', tagName: 'div' } );
} );
check( 'partOverride returns the same attributes when nothing applies', () => {
	const attributes = { slug: 'kind' };
	assert.equal( api.partOverride( attributes, 'watch', kinds, 'kind' ), attributes, 'kind with no part' );
	assert.equal( api.partOverride( attributes, '', kinds, 'kind' ), attributes, 'no kind' );
	assert.equal( api.partOverride( attributes, 'nope', kinds, 'kind' ), attributes, 'unknown kind' );
	const footer = { slug: 'footer' };
	assert.equal( api.partOverride( footer, 'listen', kinds, 'kind' ), footer, 'another template part' );
} );
check( 'partOverride does not mutate the attributes it is given', () => {
	const attributes = { slug: 'kind' };
	api.partOverride( attributes, 'listen', kinds, 'kind' );
	assert.deepEqual( attributes, { slug: 'kind' } );
} );

check( 'guardSetAttributes passes other attributes through and drops slug and theme', () => {
	const calls = [];
	const guarded = api.guardSetAttributes( ( next ) => calls.push( next ) );
	guarded( { slug: 'kind-listen', theme: 'courtneyr-child', area: 'uncategorized' } );
	assert.deepEqual( calls, [ { area: 'uncategorized' } ] );
} );
check( 'guardSetAttributes makes no call when only slug and theme change', () => {
	const calls = [];
	const guarded = api.guardSetAttributes( ( next ) => calls.push( next ) );
	guarded( { slug: 'kind-listen', theme: 'courtneyr-child' } );
	assert.deepEqual( calls, [] );
} );

if ( failed ) {
	console.log( `\n${ failed } failed` );
	process.exit( 1 );
}
console.log( '\nall passed' );
