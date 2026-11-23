// Compare two extras.mjs runs. usage: node compare-extras.mjs <base.json> <head.json> [wide,page,names]
// Fails when head adds overflow (print, 200% text at 1280 and 720, RTL), a print record past the 816px page,
// an axe violation base did not have, or a change in heading order, focusable order or Tab order; when a
// focused element shows no ring or is off screen. On wide pages in RTL the record must mirror: a Favorite
// mount sits left of the H1. Prints one line per check group.
import { readFileSync } from 'node:fs';

const [, , basePath, headPath, wideArg = '' ] = process.argv;
const base = JSON.parse( readFileSync( basePath, 'utf8' ) );
const head = JSON.parse( readFileSync( headPath, 'utf8' ) );
const WIDE = new Set( wideArg.split( ',' ).filter( Boolean ) );
const groups = {};
const ok = ( group, cond, msg ) => {
	groups[ group ] ??= { pass: 0, fail: [] };
	cond ? groups[ group ].pass++ : groups[ group ].fail.push( msg );
};
for ( const [ page, h ] of Object.entries( head ) ) {
	const b = base[ page ];
	if ( ! b ) {
		continue;
	}
	ok( 'print', h.print.overflow <= 0 && ( ! h.print.record || h.print.record[ 2 ] <= 816.5 ), `${ page }: print overflow ${ h.print.overflow }, record right ${ h.print.record?.[ 2 ] }` );
	ok( '200% text', h.text200_1280.overflow <= 0 && h.text200_720.overflow <= 0, `${ page }: overflow ${ h.text200_1280.overflow } / ${ h.text200_720.overflow }` );
	ok( 'rtl', h.rtl.overflow <= 0, `${ page }: rtl overflow ${ h.rtl.overflow }` );
	if ( WIDE.has( page ) && h.rtl.mount && h.rtl.h1 ) {
		ok( 'rtl', h.rtl.mount[ 2 ] <= h.rtl.h1[ 0 ] + 0.5, `${ page }: rtl mount right ${ h.rtl.mount[ 2 ] } not left of h1 ${ h.rtl.h1[ 0 ] }` );
	}
	if ( WIDE.has( page ) && h.rtl.clip && h.rtl.chip && h.rtl.date ) {
		ok( 'rtl', Math.abs( h.rtl.chip[ 2 ] - h.rtl.clip[ 2 ] ) <= 1 && Math.abs( h.rtl.date[ 0 ] - h.rtl.clip[ 0 ] ) <= 1, `${ page }: rtl chip right ${ h.rtl.chip[ 2 ] } vs clip right ${ h.rtl.clip[ 2 ] }, date left ${ h.rtl.date[ 0 ] } vs clip left ${ h.rtl.clip[ 0 ] }` );
	}
	for ( const k of Object.keys( h.axe ) ) {
		const added = h.axe[ k ].filter( ( v ) => ! b.axe[ k ].includes( v ) );
		ok( 'axe', 0 === added.length, `${ page } ${ k }: new axe ${ added.join( ', ' ) } (base ${ b.axe[ k ].join( ', ' ) || 'none' })` );
	}
	ok( 'dom order', JSON.stringify( h.dom.headings ) === JSON.stringify( b.dom.headings ), `${ page }: headings ${ JSON.stringify( b.dom.headings ) } -> ${ JSON.stringify( h.dom.headings ) }` );
	ok( 'dom order', JSON.stringify( h.dom.focusable ) === JSON.stringify( b.dom.focusable ), `${ page }: focusable ${ JSON.stringify( b.dom.focusable ) } -> ${ JSON.stringify( h.dom.focusable ) }` );
	ok( 'keyboard', JSON.stringify( h.tabs.map( ( t ) => t.label ) ) === JSON.stringify( b.tabs.map( ( t ) => t.label ) ), `${ page }: tab order ${ JSON.stringify( b.tabs.map( ( t ) => t.label ) ) } -> ${ JSON.stringify( h.tabs.map( ( t ) => t.label ) ) }` );
	h.tabs.forEach( ( t, i ) => {
		const o = b.tabs[ i ] || { ring: true, visible: true };
		ok( 'keyboard', ( t.ring || ! o.ring ) && ( t.visible || ! o.visible ), `${ page }: ${ t.label } ring ${ t.ring } visible ${ t.visible } (base ${ o.ring } ${ o.visible })` );
		if ( ! t.ring || ! t.visible ) {
			console.log( `   note: ${ page }: ${ t.label } ring ${ t.ring } visible ${ t.visible } (base ${ o.ring } ${ o.visible })` );
		}
	} );
}
let fails = 0;
for ( const [ g, r ] of Object.entries( groups ) ) {
	console.log( `${ g.padEnd( 10 ) } ${ r.pass } passed, ${ r.fail.length } failed` );
	r.fail.slice( 0, 8 ).forEach( ( f ) => console.log( '   FAIL ' + f ) );
	fails += r.fail.length;
}
process.exit( fails ? 1 : 0 );
