// The wide-column decision, checked on real WordPress responses (curl-level, no browser).
//   node decision-invariants.mjs <urls.tsv> <preview.tsv> <wide-names,comma> [kind-read-pages,comma]
// For every page: the wide marker, the stylesheet and the reading record appear together or not at all (the one decision
// feeds the layout and the renderer), and the set of wide pages equals the expected list. Pages in the 4th argument print a
// record under a body class of another kind (a Like that is also a Read): they must stay unmarked.
import { readFileSync } from 'node:fs';
const [, , urls, prev, wideArg, otherArg = ''] = process.argv;
const WIDE = new Set( wideArg.split( ',' ).filter( Boolean ) );
const OTHER = new Set( otherArg.split( ',' ).filter( Boolean ) );
const rows = [ ...readFileSync( urls, 'utf8' ).trim().split( '\n' ), ...readFileSync( prev, 'utf8' ).trim().split( '\n' ).map( ( l ) => 'p-' + l.replace( /\t(.*)/, '\t$1&wcd_login=1' ) ) ].map( ( l ) => l.split( '\t' ) );
let fails = 0;
const fail = ( m ) => { fails++; console.log( 'FAIL', m ); };
for ( const [ name, url ] of rows ) {
	if ( /^(search|feed-read|rest-posts)$/.test( name ) ) continue;
	const html = await ( await fetch( url ) ).text();
	const marked = ( html.match( /cr-wide-column/g ) || [] ).length;
	const sheet = html.includes( 'courtneyr-wide-column' );
	const record = ( html.match( /cr-record-rows/g ) || [] ).length;
	const isWide = marked > 0;
	if ( isWide !== sheet ) fail( `${ name }: marker ${ marked } but sheet ${ sheet }` );
	if ( isWide && 3 !== marked ) fail( `${ name }: expected 3 markers (content class, header class, stylesheet id), saw ${ marked }` );
	if ( isWide && 1 !== record ) fail( `${ name }: marked wide but ${ record } records print` );
	if ( ! isWide && record > 0 && /^(read|d-)/.test( name ) && ! OTHER.has( name ) && ! /^d-(record-then-rowless-2)$/.test( name ) ) fail( `${ name }: a record prints but the column is not wide` );
	if ( isWide !== WIDE.has( name ) ) fail( `${ name }: wide=${ isWide }, expected ${ WIDE.has( name ) }` );
}
console.log( fails ? `${ fails } invariant failures` : `all invariants hold on ${ rows.length } responses` );
process.exit( fails ? 1 : 0 );
