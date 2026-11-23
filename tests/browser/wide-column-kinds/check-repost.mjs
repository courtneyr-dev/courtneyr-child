// Assertions for the Repost single in the wide column, from two probe.mjs runs of the same pages.
// usage: node check-repost.mjs <base.json> <head.json>   (exit 1 on any failure)
//
// Wide pages (the page prints a top-level clipping, or the meta-only one) from 64rem: the clipping fills
// the column, the kind chip starts at the clipping's start edge, the date ends at its end edge, the title
// starts at the column's edge, the clipping keeps its base top, and the blocks under the clipping (Courtney's note)
// start at the clipping's start edge. Under 64rem and on every page that is not
// wide, the column, the clipping and the header are where base put them. Everywhere: blocks beside the
// clipping keep their base boxes, the sparkle and post navigation do not move, one H1, no horizontal
// overflow, no console error base did not have.
import { readFileSync } from 'node:fs';

const [, , basePath, headPath] = process.argv;
const base = JSON.parse( readFileSync( basePath, 'utf8' ) );
const head = JSON.parse( readFileSync( headPath, 'utf8' ) );
const NOT_WIDE = new Set( [ 'rep-nested', 'rep-locked', 'rep-and-like', 'like-and-rep', 'rep-and-bookmark', 'rep-two-pages-2', 'rep-page-two' ] );
const T = 0.02;
let pass = 0;
const fails = [];
const ok = ( cond, msg ) => ( cond ? pass++ : fails.push( msg ) );
const eq = ( a, b, t = T ) => Math.abs( a - b ) <= t;
const boxEq = ( a, b ) => ( ! a && ! b ) || ( a && b && a.every( ( v, i ) => eq( v, b[ i ] ) ) );
const right = ( r ) => r[ 0 ] + r[ 2 ];
const bottom = ( r ) => r[ 1 ] + r[ 3 ];

for ( const key of Object.keys( head ) ) {
	const [ page, w ] = key.split( '|' );
	const h = head[ key ];
	const b = base[ key ];
	if ( ! b ) {
		fails.push( `${ key }: no base case` );
		continue;
	}
	const widePage = ! NOT_WIDE.has( page ) && !! b.clip;
	const wideHere = widePage && Number( w ) >= 1024;
	ok( 1 === h.h1Count, `${ key }: one h1 (${ h.h1Count })` );
	ok( h.overflow <= 0, `${ key }: no horizontal overflow (${ h.overflow })` );
	ok( h.errors.every( ( e ) => b.errors.includes( e ) ), `${ key }: no new console error (${ h.errors.join( ' | ' ) })` );
	ok( JSON.stringify( h.sparkle && [ h.sparkle.top, h.sparkle.width, Math.round( h.sparkle.left * 10 ) / 10 ] ) === JSON.stringify( b.sparkle && [ b.sparkle.top, b.sparkle.width, Math.round( b.sparkle.left * 10 ) / 10 ] ), `${ key }: sparkle where base put it (${ b.sparkle && b.sparkle.left } -> ${ h.sparkle && h.sparkle.left })` );
	ok( boxEq( h.postNav && [ h.postNav[ 0 ], h.postNav[ 2 ] ], b.postNav && [ b.postNav[ 0 ], b.postNav[ 2 ] ] ), `${ key }: post navigation keeps its column` );
	ok( h.wideSheet === widePage, `${ key }: cr-wide-column.css loads exactly on wide pages (${ h.wideSheet })` );

	if ( ! wideHere ) {
		for ( const part of [ 'content', 'clip', 'header', 'h1', 'date', 'chip' ] ) {
			ok( boxEq( h[ part ], b[ part ] ), `${ key }: ${ part } as base (${ JSON.stringify( b[ part ] ) } -> ${ JSON.stringify( h[ part ] ) })` );
		}
		ok( boxEq( h.nestedKids?.map( ( k ) => k.box ).flat(), b.nestedKids?.map( ( k ) => k.box ).flat() ), `${ key }: blocks in the authored group as base` );
	} else {
		ok( h.content[ 2 ] <= 1100 + T && h.content[ 2 ] > b.content[ 2 ], `${ key }: column widens to at most wideSize (${ b.content[ 2 ] } -> ${ h.content[ 2 ] })` );
		ok( eq( h.clip[ 0 ], h.content[ 0 ] ) && eq( h.clip[ 2 ], h.content[ 2 ] ), `${ key }: clipping fills the column (${ h.clip } in ${ h.content })` );
		ok( eq( h.clip[ 1 ], b.clip[ 1 ] ), `${ key }: clipping top as base (${ b.clip[ 1 ] } -> ${ h.clip[ 1 ] })` );
		ok( ! h.clipNote || ! h.clipCaption || h.clipNote[ 1 ] - bottom( h.clipCaption ) <= 16 + T, `${ key }: the description follows the caption by one row gap (${ h.clipNote && h.clipCaption && h.clipNote[ 1 ] - bottom( h.clipCaption ) })` );
		ok( eq( h.chip[ 0 ], h.clip[ 0 ], 1 ), `${ key }: kind chip starts at the clipping's start edge (${ h.chip[ 0 ] } vs ${ h.clip[ 0 ] })` );
		ok( eq( right( h.date ), right( h.clip ), 1 ), `${ key }: date ends at the clipping's end edge (${ right( h.date ) } vs ${ right( h.clip ) })` );
		ok( eq( h.date[ 1 ], b.date[ 1 ], 0.5 ) && eq( h.chip[ 1 ], b.chip[ 1 ], 0.5 ), `${ key }: date and chip keep their row` );
		const inClip = ( r, c ) => r && c && r[ 0 ] >= c[ 0 ] && right( r ) <= right( c ) + T && r[ 1 ] >= c[ 1 ] && bottom( r ) <= bottom( c ) + T;
		if ( inClip( b.h1, b.clip ) ) {
			ok( inClip( h.h1, h.clip ), `${ key }: the cited title stays the clipping's h1 (${ h.h1 } in ${ h.clip })` );
		} else {
			ok( eq( h.h1[ 0 ], h.content[ 0 ], 0.5 ) && eq( h.h1[ 2 ], b.h1[ 2 ] ), `${ key }: title starts at the column's edge at its base width (${ h.h1 })` );
		}
	}

	// Blocks beside the clipping: same left and width as base, same top before it, same distance below it after.
	const isClip = ( k ) => k.cls.split( ' ' ).includes( 'k-repost' );
	const kidsH = ( h.entryKids || [] ).filter( ( k ) => k !== ( h.entryKids || [] ).find( isClip ) );
	const kidsB = ( b.entryKids || [] ).filter( ( k ) => k !== ( b.entryKids || [] ).find( isClip ) );
	ok( kidsH.length === kidsB.length, `${ key }: same blocks beside the clipping (${ kidsB.length } -> ${ kidsH.length })` );
	let note = true;
	kidsH.forEach( ( k, i ) => {
		const o = kidsB[ i ];
		if ( ! o ) {
			return;
		}
		const after = h.clip && b.clip && k.box[ 1 ] > h.clip[ 1 ];
		// Courtney's ruling: on a wide page the blocks under the clipping start at its start edge, keeping their base
		// offset from the reading column's start and their base size. Full-width blocks still bleed as on base.
		const flush = wideHere && after && ! k.cls.split( ' ' ).includes( 'alignfull' );
		const left = flush ? eq( k.box[ 0 ] - h.clip[ 0 ], o.box[ 0 ] - b.entry[ 0 ] ) : eq( k.box[ 0 ], o.box[ 0 ] );
		ok( left && eq( k.box[ 2 ], o.box[ 2 ] ) && eq( k.box[ 3 ], o.box[ 3 ] ), `${ key }: ${ k.tag }.${ k.cls } box as base${ flush ? ', from the clipping\'s start edge' : '' } (${ o.box } -> ${ k.box })` );
		if ( flush && note && 'p' === k.tag ) {
			note = false;
			ok( eq( k.box[ 0 ], h.clip[ 0 ] ), `${ key }: the note under the clipping starts at the clipping's start edge (${ k.box[ 0 ] } vs ${ h.clip[ 0 ] })` );
		}
		ok( after ? eq( k.box[ 1 ] - bottom( h.clip ), o.box[ 1 ] - bottom( b.clip ) ) : eq( k.box[ 1 ], o.box[ 1 ] ), `${ key }: ${ k.tag }.${ k.cls } vertical place as base` );
	} );
}
for ( const f of fails.slice( 0, 40 ) ) {
	console.log( 'FAIL ' + f );
}
console.log( `${ pass } passed, ${ fails.length } failed` );
process.exit( fails.length ? 1 : 0 );
