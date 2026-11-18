// node --test tests/perf/artifact-ab.test.mjs   (offline: canned pairs in fixtures/artifact-ab, no browser, no site)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import {
	ToolingError,
	assertLocalBase,
	capturePlan,
	classifyM2,
	compareRecords,
	diffDom,
	diffStyles,
	handleChange,
	mf2Items,
	normalizeHtml,
	normalizeTimestamps,
	parseHtml,
	preflight,
	sha256,
	viewRecords,
	withinControl,
} from './artifact-ab.mjs';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const FIX = path.join( HERE, 'fixtures', 'artifact-ab' );
const HARNESS = path.join( HERE, 'artifact-ab.mjs' );
const MAP = path.join( FIX, 'map-read.json' );
const read = ( f ) => fs.readFileSync( path.join( FIX, f ), 'utf8' );
const json = ( f ) => JSON.parse( read( f ) );
const READ = [ { slug: 'cr-sample', root: 'cr-book' } ];
const domAgainstBase = ( head ) => diffDom( normalizeHtml( read( 'single-base.html' ) ), normalizeHtml( read( head ) ), READ );
const records = () => viewRecords( json( 'map-read.json' ), 'map-read.json' );
const harness = ( args, env = {} ) => spawnSync( process.execPath, [ HARNESS, ...args ], { encoding: 'utf8', env: { ...process.env, ...env } } );
const scratch = () => fs.mkdtempSync( path.join( os.tmpdir(), 'artifact-ab-' ) );

// What capture writes, reduced to the fields preflight and compare's tooling checks read.
const manifest = ( role, recs ) => ( {
	role,
	harness: { sha256: sha256( fs.readFileSync( HARNESS ) ) },
	baseUrl: 'http://localhost:8887',
	loads: 2,
	map: { path: MAP, sha256: sha256( fs.readFileSync( MAP ) ) },
	spec: null,
	expect: [],
	records: recs,
	captures: Object.fromEntries( capturePlan( recs ).map( ( i ) => [ i.key, { role: i.role, auth: i.auth, record: i.record } ] ) ),
} );

test( 'a pair that differs only by is-style-<slug> on the root element is clean', () => {
	assert.deepEqual( domAgainstBase( 'single-head-style.html' ), [] );
	assert.deepEqual( mf2Items( normalizeHtml( read( 'single-head-style.html' ) ) ), mf2Items( normalizeHtml( read( 'single-base.html' ) ) ) );
} );

test( 'any other class or attribute change is a DOM fail', () => {
	const another = domAgainstBase( 'single-head-class.html' );
	assert.equal( another.length, 1 );
	assert.equal( another[ 0 ].what, 'class' );
	assert.match( another[ 0 ].head, /cr-book--wide/ );

	const attribute = domAgainstBase( 'single-head-attr.html' );
	assert.deepEqual( attribute.map( ( d ) => [ d.what, d.base, d.head ] ), [ [ 'attribute data-style', null, 'cr-book' ] ] );

	const offRoot = domAgainstBase( 'single-head-misplaced.html' );
	assert.equal( offRoot.length, 1, 'is-style-cr-sample on an element without the cr-book root class is not whitelisted' );
	assert.match( offRoot[ 0 ].where, /single-post__content/ );
} );

test( 'a text change is a DOM fail and the whitelist names the slug, not any is-style', () => {
	const base = normalizeHtml( read( 'single-base.html' ) );
	assert.equal( diffDom( base, normalizeHtml( read( 'single-base.html' ).replace( 'The Fictional Atlas', 'The Fictional Atlas!' ) ), READ )[ 0 ].what, 'text' );
	assert.equal( diffDom( base, normalizeHtml( read( 'single-head-style.html' ) ), [ { slug: 'cr-passport-page', root: 'cr-book' } ] ).length, 1 );
} );

test( 'one computed property is a style fail', () => {
	const result = diffStyles( json( 'styles-base.json' ), json( 'styles-head.json' ) );
	assert.equal( result.count, 1 );
	assert.deepEqual( result.diffs[ 0 ], { element: 'body:1>main:0>div:0>div:1::before', prop: 'color', base: 'rgb(120, 72, 40)', head: 'rgb(17, 17, 17)' } );
	assert.equal( diffStyles( json( 'styles-base.json' ), json( 'styles-base.json' ) ).count, 0 );
} );

test( 'a head map missing a baseline view record is a tooling failure, not clean', () => {
	const head = records().filter( ( r ) => 'single-phone' !== r.name );
	const problems = compareRecords( records(), head );
	assert.ok( problems.includes( 'the head map has no record single-phone' ), problems.join( '; ' ) );
	assert.ok( preflight( manifest( 'baseline', records() ), manifest( 'head', head ) ).length > 0 );
} );

for ( const [ field, value ] of [ [ 'width', 1440 ], [ 'height', 900 ], [ 'fullPage', false ], [ 'colorScheme', 'dark' ] ] ) {
	test( `a head record whose ${ field } differs from the baseline's is a tooling failure`, () => {
		const head = records().map( ( r ) => ( 'single-full' === r.name ? { ...r, [ field ]: value } : r ) );
		const problems = compareRecords( records(), head );
		assert.equal( problems.length, 1 );
		assert.match( problems[ 0 ], new RegExp( `^single-full: ${ field } ` ) );
	} );
}

test( 'extra other-scheme captures present but one judged record missing is a tooling failure', () => {
	const dir = scratch();
	const [ baseDir, headDir ] = [ path.join( dir, 'base' ), path.join( dir, 'head' ) ];
	const head = manifest( 'head', records() );
	delete head.captures[ 'single-full' ];
	assert.ok( head.captures[ 'single-full~dark' ], 'the twin is still there' );
	const problems = preflight( manifest( 'baseline', records() ), head );
	assert.deepEqual( problems, [ 'the head has no capture of judged record single-full (single-full~dark is extra coverage and doesn\'t stand in)' ] );

	fs.mkdirSync( baseDir );
	fs.mkdirSync( headDir );
	fs.writeFileSync( path.join( baseDir, 'manifest.json' ), JSON.stringify( manifest( 'baseline', records() ) ) );
	fs.writeFileSync( path.join( headDir, 'manifest.json' ), JSON.stringify( head ) );
	const run = harness( [ 'compare', '--baseline', baseDir, '--head', headDir, '--style', 'cr-sample=cr-book' ] );
	assert.equal( run.status, 2, run.stdout + run.stderr );
	assert.equal( JSON.parse( fs.readFileSync( path.join( headDir, 'compare', 'verdict.json' ), 'utf8' ) ).status, 'tooling' );
} );

test( 'a -ref spec.json adds its views[] as records, and a print view is a tooling failure', () => {
	const ref = viewRecords( { views: [ { name: 'single-ref-desktop', path: '/a-seeded-read/', width: 1672, height: 941, colorScheme: 'dark' } ] }, 'spec.json' );
	assert.deepEqual( ref, [ { name: 'single-ref-desktop', path: '/a-seeded-read/', selector: '', width: 1672, height: 941, fullPage: false, colorScheme: 'dark' } ] );
	assert.throws( () => viewRecords( { views: [ { name: 'zine', path: '/zine/', width: 800, height: 600, media: 'print' } ] }, 'spec.json' ), ToolingError );
} );

test( 'twins cover the other scheme only where the map has no record in it', () => {
	const recs = records();
	recs.push( { ...recs.find( ( r ) => 'single-phone' === r.name ), name: 'single-phone-dark', colorScheme: 'dark' } );
	const plan = capturePlan( recs, [ { id: 7, path: '/private-read/', variant: 'pair-private-tag', admin: true } ] );
	assert.deepEqual( plan.filter( ( i ) => 'twin' === i.role ).map( ( i ) => i.key ), [ 'archive-desktop~dark', 'single-full~dark', 'stream-desktop~dark' ] );
	assert.deepEqual( plan.filter( ( i ) => 'fixture' === i.role ).map( ( i ) => i.key ), [ 'post-7~anon', 'post-7~admin' ] );
} );

test( 'the harness refuses a non-local host', () => {
	for ( const host of [ 'https://dev-courtneyrdev.pantheonsite.io', 'https://live-courtneyrdev.pantheonsite.io', 'https://courtneyr.dev' ] ) {
		assert.throws( () => assertLocalBase( host ), ToolingError );
	}
	assert.equal( assertLocalBase( 'http://localhost:8887/' ), 'http://localhost:8887' );
	const out = path.join( scratch(), 'never' );
	const run = harness( [ 'capture', '--role', 'baseline', '--base-url', 'https://dev-courtneyrdev.pantheonsite.io', '--map', MAP, '--out', out ], { CR_ALLOW_REMOTE: '1' } );
	assert.equal( run.status, 2 );
	assert.match( run.stderr, /refusing dev-courtneyrdev\.pantheonsite\.io/ );
	assert.equal( fs.existsSync( out ), false );
} );

test( 'a head map whose SHA-256 differs from the value passed in is a tooling failure with no capture', () => {
	const out = path.join( scratch(), 'head' );
	const run = harness( [ 'capture', '--role', 'head', '--base-url', 'http://localhost:9', '--map', MAP, '--map-sha256', '0'.repeat( 64 ), '--baseline', path.join( FIX, 'no-such-capture' ), '--out', out ] );
	assert.equal( run.status, 2 );
	assert.match( run.stderr, /SHA-256 [0-9a-f]{64} differs from the recorded 0{64}/ );
	assert.equal( fs.existsSync( out ), false );
} );

test( 'a / or /stream/ handle page that is not HTTP 200 is a tooling failure, not a pass with nothing compared', () => {
	const ok = { status: 200, link: [], inline: [ 'courtneyr-tokens-css' ] };
	const withHandles = ( role, stream ) => ( { ...manifest( role, records() ), handles: { '/': ok, '/stream/': stream } } );
	assert.deepEqual( preflight( withHandles( 'baseline', ok ), withHandles( 'head', ok ) ), [] );
	for ( const [ base, head ] of [ [ { status: 404 }, { status: 404 } ], [ ok, { status: 500 } ], [ { status: 301 }, ok ] ] ) {
		const problems = preflight( withHandles( 'baseline', base ), withHandles( 'head', head ) );
		assert.deepEqual( problems, [ `step 6 can't compare the /stream/ handle set: HTTP ${ base.status } at baseline, ${ head.status } at head` ] );
	}
	const dir = scratch();
	for ( const role of [ 'baseline', 'head' ] ) {
		fs.mkdirSync( path.join( dir, role ) );
		fs.writeFileSync( path.join( dir, role, 'manifest.json' ), JSON.stringify( withHandles( role, { status: 404 } ) ) );
	}
	const run = harness( [ 'compare', '--baseline', path.join( dir, 'baseline' ), '--head', path.join( dir, 'head' ), '--style', 'cr-sample=cr-book' ] );
	assert.equal( run.status, 2, run.stdout + run.stderr );
	assert.equal( JSON.parse( fs.readFileSync( path.join( dir, 'head', 'compare', 'verdict.json' ), 'utf8' ) ).status, 'tooling' );
} );

test( 'a screenshot pair passes only within the same-CSS controls', () => {
	assert.equal( withinControl( { count: 12, maxDelta: 40 }, [ { count: 20, maxDelta: 57 } ] ).ok, true );
	assert.equal( withinControl( { count: 12, maxDelta: 60 }, [ { count: 20, maxDelta: 57 } ] ).ok, false );
	assert.equal( withinControl( { count: 1, maxDelta: 1 }, [] ).ok, false, 'identical controls leave no room' );
} );

test( 'the handle set may shrink but never grow, and feeds compare after timestamps normalize', () => {
	const base = { status: 200, link: [ 'cr-read-css|href|cr-read.css' ], inline: [ 'courtneyr-tokens-css' ] };
	assert.equal( handleChange( base, { status: 200, link: [], inline: [ 'courtneyr-tokens-css' ] } ), null );
	assert.match( handleChange( base, { ...base, link: [ ...base.link, 'cr-book-css|href|cr-book.css' ] } ), /adds cr-book-css/ );
	assert.equal(
		normalizeTimestamps( '<lastBuildDate>Wed, 18 Nov 2026 09:00:00 +0000</lastBuildDate>{"modified":"2026-11-18T09:00:00"}' ),
		normalizeTimestamps( '<lastBuildDate>Wed, 18 Nov 2026 10:30:12 +0000</lastBuildDate>{"modified":"2026-11-18T10:30:12"}' )
	);
} );

// A DOM double over parseHtml for classifyM2: type, class and id compounds with descendant and child combinators.
function fakeDocument( html ) {
	const all = [];
	const compound = ( el, c ) => {
		const m = /^(\*|[a-z][\w-]*)?((?:[.#][\w-]+)*)$/i.exec( c );
		if ( ! m ) {
			throw new SyntaxError( `the test double can't match ${ c }` );
		}
		const parts = m[ 2 ].match( /[.#][\w-]+/g ) || [];
		return ( ! m[ 1 ] || '*' === m[ 1 ] || m[ 1 ] === el.tag ) && parts.every( ( p ) => ( '#' === p[ 0 ] ? el.id === p.slice( 1 ) : el.classList.includes( p.slice( 1 ) ) ) );
	};
	const complex = ( el, tokens, i ) => {
		if ( ! compound( el, tokens[ i ] ) ) {
			return false;
		}
		if ( 0 === i ) {
			return true;
		}
		if ( '>' === tokens[ i - 1 ] ) {
			return !! el.parent && complex( el.parent, tokens, i - 2 );
		}
		for ( let p = el.parent; p; p = p.parent ) {
			if ( complex( p, tokens, i - 1 ) ) {
				return true;
			}
		}
		return false;
	};
	const matches = ( el, selector ) =>
		selector.split( ',' ).some( ( one ) => {
			const tokens = one.replace( />/g, ' > ' ).trim().split( /\s+/ );
			return complex( el, tokens, tokens.length - 1 );
		} );
	const wrap = ( n, parent ) => {
		const attr = ( k ) => n.attrs.find( ( [ name ] ) => name === k )?.[ 1 ] || '';
		const el = { tag: n.tag, tagName: n.tag.toUpperCase(), parent, id: attr( 'id' ), classList: attr( 'class' ).split( /\s+/ ).filter( Boolean ) };
		el.matches = ( s ) => matches( el, s );
		el.closest = ( s ) => {
			for ( let e = el; e; e = e.parent ) {
				if ( e.matches( s ) ) {
					return e;
				}
			}
			return null;
		};
		all.push( el );
		n.children.filter( ( c ) => c.tag ).forEach( ( c ) => wrap( c, el ) );
	};
	parseHtml( html ).children.filter( ( c ) => c.tag ).forEach( ( c ) => wrap( c, null ) );
	return { querySelectorAll: ( s ) => all.filter( ( e ) => e.matches( s ) ) };
}

test( 'R-N12 (m2) classifies each selector from the baseline DOM with closest() and matches(), two-kind fixture included', () => {
	const views = [ { name: 'single-full', doc: fakeDocument( read( 'm2-checkin-single.html' ) ) } ];
	const fixtures = [ { name: 'post-63~anon', doc: fakeDocument( read( 'm2-two-kind.html' ) ) } ];
	const prefix = 'body.single-post.kind-checkin ';
	const classify = ( rest ) => classifyM2( views, fixtures, { prefixed: prefix + rest, bare: rest }, '.cr-passport' );

	const inCard = classify( '.cr-passport__visa' );
	assert.deepEqual( [ inCard.verdict, inCard.reason, inCard.matched ], [ 'in-card', null, 1 ] );

	const widens = classify( '.single-post__content .cr-passport--single .cr-passport__stamp' );
	assert.deepEqual( [ widens.verdict, widens.reason, widens.widened ], [ 'stays', 'm2-widens', [ 'post-63~anon: span.cr-passport__stamp' ] ], 'the two-kind body class names watch, so only the prefix-free form reaches the stamp' );

	const outside = classify( '.single-post__content > h1' );
	assert.deepEqual( [ outside.verdict, outside.reason, outside.outside ], [ 'stays', 'm2-outside', [ 'single-full: h1.wp-block-post-title' ] ] );

	const states = classify( '.cr-passport__visa:hover::before' );
	assert.deepEqual( [ states.verdict, states.matched ], [ 'in-card', 1 ], 'user-action pseudo-classes and pseudo-elements match their originating element' );

	assert.equal( classify( '.cr-passport__missing' ).reason, 'm2-no-match' );
	assert.match( classify( '.cr-passport__visa[' ).reason, /^m2-unmatchable/, 'a selector the engine rejects stays' );
} );
