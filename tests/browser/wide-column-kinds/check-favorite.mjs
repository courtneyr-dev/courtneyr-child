// Assertions for the Favorite single in the wide column, from two probe.mjs runs of the same pages.
// usage: node check-favorite.mjs <base.json> <head.json>   (exit 1 on any failure)
//
// Wide pages (a readable favorite whose first kind is favorite) from 64rem: the sheet fills the column,
// the mount starts at the sheet's midpoint and above the H1, the H1 stays left of the mount. Under 64rem
// and on every page that is not wide, the sheet, its parts and everything beside it are where base put
// them. Everywhere: blocks beside the sheet keep their base boxes, the sparkle and post navigation do
// not move, one H1, no horizontal overflow, no console error base did not have.
import { readFileSync } from 'node:fs';

const [, , basePath, headPath] = process.argv;
const base = JSON.parse( readFileSync( basePath, 'utf8' ) );
const head = JSON.parse( readFileSync( headPath, 'utf8' ) );
const NOT_WIDE = new Set( [ 'fav-locked', 'fav-nested', 'fav-and-bookmark' ] );
const T = 0.02;
let pass = 0;
const fails = [];
const ok = ( cond, msg ) => ( cond ? pass++ : fails.push( msg ) );
const eq = ( a, b ) => Math.abs( a - b ) <= T;
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
	const wideHere = ! NOT_WIDE.has( page ) && Number( w ) >= 1024 && !! h.sheet;
	ok( 1 === h.h1Count, `${ key }: one h1 (${ h.h1Count })` );
	ok( h.overflow <= 0, `${ key }: no horizontal overflow (${ h.overflow })` );
	ok( h.errors.every( ( e ) => b.errors.includes( e ) ), `${ key }: no new console error (${ h.errors.join( ' | ' ) })` );
	ok( JSON.stringify( h.sparkle && [ h.sparkle.top, h.sparkle.width, Math.round( h.sparkle.left * 10 ) / 10 ] ) === JSON.stringify( b.sparkle && [ b.sparkle.top, b.sparkle.width, Math.round( b.sparkle.left * 10 ) / 10 ] ), `${ key }: sparkle where base put it` );
	ok( boxEq( h.postNav && [ h.postNav[ 0 ], h.postNav[ 2 ] ], b.postNav && [ b.postNav[ 0 ], b.postNav[ 2 ] ] ), `${ key }: post navigation keeps its column` );
	ok( h.wideSheet === ( ! NOT_WIDE.has( page ) && !! b.sheet ), `${ key }: cr-wide-column.css loads exactly on wide pages (${ h.wideSheet })` );

	if ( ! wideHere ) {
		for ( const part of [ 'sheet', 'sheetH1', 'star', 'tape', 'record', 'mount' ] ) {
			ok( boxEq( h[ part ], b[ part ] ), `${ key }: ${ part } as base (${ JSON.stringify( b[ part ] ) } -> ${ JSON.stringify( h[ part ] ) })` );
		}
		ok( boxEq( h.nestedKids?.map( ( k ) => k.box ).flat(), b.nestedKids?.map( ( k ) => k.box ).flat() ), `${ key }: blocks in the authored group as base` );
	} else {
		ok( eq( h.sheet[ 0 ], h.content[ 0 ] ) && eq( h.sheet[ 2 ], h.content[ 2 ] ), `${ key }: sheet fills the column (${ h.sheet } in ${ h.content })` );
		ok( h.sheet[ 2 ] <= 1100 + T, `${ key }: sheet at most wideSize 1100 (${ h.sheet[ 2 ] })` );
		ok( ! h.sheetProse || h.sheetProse[ 2 ] <= 720 + T, `${ key }: a card-less sheet's prose keeps the reading width (${ h.sheetProse && h.sheetProse[ 2 ] })` );
		ok( eq( h.sheet[ 1 ], b.sheet[ 1 ] ), `${ key }: sheet top as base` );
		ok( h.sheetH1[ 0 ] >= h.sheet[ 0 ] && right( h.sheetH1 ) <= right( h.sheet ) + T, `${ key }: h1 inside the sheet` );
		ok( eq( h.sheetH1[ 0 ] - h.sheet[ 0 ], 48 ), `${ key }: the sheet's inline padding is 3rem in the column (${ h.sheetH1[ 0 ] - h.sheet[ 0 ] })` );
		ok( eq( h.star[ 1 ] - h.sheet[ 1 ], b.star[ 1 ] - b.sheet[ 1 ] ) && eq( h.star[ 0 ] - h.sheet[ 0 ], b.star[ 0 ] - b.sheet[ 0 ] ), `${ key }: star keeps its corner` );
		ok( bottom( h.tape ) <= h.sheetH1[ 1 ] && bottom( h.star ) <= h.sheetH1[ 1 ], `${ key }: star and tape clear the h1` );
		if ( h.mount ) {
			const mid = h.sheet[ 0 ] + h.sheet[ 2 ] / 2;
			ok( h.mount[ 0 ] >= mid - T && h.mount[ 0 ] <= mid + 24, `${ key }: mount starts at the sheet's midpoint (${ h.mount[ 0 ] } vs ${ mid })` );
			ok( h.mount[ 1 ] < h.sheetH1[ 1 ], `${ key }: mount starts above the h1 (${ h.mount[ 1 ] } < ${ h.sheetH1[ 1 ] })` );
			ok( h.mount[ 1 ] > h.sheet[ 1 ], `${ key }: mount inside the sheet's top edge` );
			ok( right( h.mount ) <= right( h.sheet ) - 16, `${ key }: mount inside the sheet's right padding` );
			ok( right( h.sheetH1 ) <= h.mount[ 0 ] && right( h.record ) <= h.mount[ 0 ], `${ key }: h1 and record stay left of the mount` );
			ok( bottom( h.mount ) <= bottom( h.sheet ), `${ key }: mount inside the sheet's bottom` );
		}
	}

	// Blocks beside the sheet: same left and width as base, same top before the sheet, same distance below it after.
	const kidsH = ( h.entryKids || [] ).filter( ( k ) => ! k.cls.includes( 'cr-fav-sheet' ) );
	const kidsB = ( b.entryKids || [] ).filter( ( k ) => ! k.cls.includes( 'cr-fav-sheet' ) );
	ok( kidsH.length === kidsB.length, `${ key }: same blocks beside the sheet` );
	kidsH.forEach( ( k, i ) => {
		const o = kidsB[ i ];
		if ( ! o ) {
			return;
		}
		ok( eq( k.box[ 0 ], o.box[ 0 ] ) && eq( k.box[ 2 ], o.box[ 2 ] ) && eq( k.box[ 3 ], o.box[ 3 ] ), `${ key }: ${ k.tag }.${ k.cls } box as base (${ o.box } -> ${ k.box })` );
		const after = h.sheet && k.box[ 1 ] > h.sheet[ 1 ];
		ok( after ? eq( k.box[ 1 ] - bottom( h.sheet ), o.box[ 1 ] - bottom( b.sheet ) ) : eq( k.box[ 1 ], o.box[ 1 ] ), `${ key }: ${ k.tag }.${ k.cls } vertical place as base` );
	} );
}
for ( const f of fails.slice( 0, 40 ) ) {
	console.log( 'FAIL ' + f );
}
console.log( `${ pass } passed, ${ fails.length } failed` );
process.exit( fails.length ? 1 : 0 );
