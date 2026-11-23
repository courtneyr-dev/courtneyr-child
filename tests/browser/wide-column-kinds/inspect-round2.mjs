// Second-round fixtures (seed-round2.php), measured in Chrome at one width: synced patterns, authored HTML
// that names a card's class, and autosave previews. Every page is held to the invariant that the decision
// and the record it causes agree: a marked (wide) page prints exactly one record, a top-level child of
// the entry at the column's width; an unmarked page prints no reading-width box and no record wider than
// base's. Pages named in EXPECT are also held to their fixture's own expectation.
// usage: node inspect-round2.mjs <rows.tsv> <out.json> [width]
//   rows.tsv: name<TAB>url. A name starting "p-" is a preview: its url is a ?cr_r2_preview_of=<ID>&cr_r2_login=1
//   link (preview-login-mu.php), which this script follows to the real preview link first.
import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';

const [ , , tsv, out, widthArg ] = process.argv;
const width = Number( widthArg || 1448 );
const rows = readFileSync( tsv, 'utf8' ).trim().split( '\n' ).map( ( l ) => l.split( '\t' ) );

// What each fixture must show at a desktop width (1024 px and wider), beyond the invariant.
const synced = { marked: false, recordInGroup: true, beforeInGroup: true, afterInGroup: true };
const decoy = { marked: true, recordTop: true, decoyInFlow: true };
const EXPECT = {
	'r2-fav-synced': synced,
	'r2-rep-synced': synced,
	'r2-fav-synced-bare': { marked: false },
	'r2-rep-synced-bare': { marked: false },
	'r2-fav-synced-after': { marked: true, recordTop: true },
	'r2-rep-synced-after': { marked: true, recordTop: true },
	'r2-rep-as-remove': { marked: true, records: 1 },
	'r2-rep-as-add': { marked: true, records: 1 },
	'r2-rep-as-meta': { marked: true, records: 1 },
	'r2-rep-as-classic': { marked: true, records: 1 },
	'p-r2-rep-as-remove': { sentinel: true },
	'p-r2-rep-as-add': { sentinel: true, marked: true, records: 1 },
	'p-r2-rep-as-meta': { sentinel: true, marked: true, records: 1 },
	'p-r2-rep-as-classic': { sentinel: true, marked: true, records: 1 },
};
for ( const k of [ 'fav', 'rep' ] ) {
	for ( const d of [ 'code', 'attr', 'comment', 'pre', 'data', 'class' ] ) {
		EXPECT[ `r2-${ k }-decoy-${ d }` ] = decoy;
	}
}

const browser = await chromium.launch( { executablePath: process.env.CR_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' } );
const page = await browser.newPage( { viewport: { width, height: 900 } } );
const result = {};
let fails = 0;
let pass = 0;
const ok = ( c, m ) => ( c ? pass++ : ( fails++, console.log( 'FAIL', m ) ) );
for ( let [ name, url ] of rows ) {
	if ( name.startsWith( 'p-' ) ) {
		url = ( await ( await fetch( url ) ).text() ).trim();
	}
	const response = await page.goto( url, { waitUntil: 'networkidle' } );
	const html = await response.text();
	const g = await page.evaluate( () => {
		const w = ( el ) => ( el ? Math.round( el.getBoundingClientRect().width * 100 ) / 100 : null );
		const entry = document.querySelector( 'main .pkiw-singular-entry' );
		const content = document.querySelector( 'main .single-post__content' );
		const records = [ ...document.querySelectorAll( 'main .cr-fav-sheet, main article.cr-repost-clip--single:not(.authored-note)' ) ];
		const record = records[ 0 ] || null;
		const group = entry ? entry.querySelector( '.wp-block-group:not(.alignwide):not(.alignfull)' ) : null;
		const para = ( text ) => [ ...document.querySelectorAll( 'main p' ) ].find( ( el ) => el.textContent.includes( text ) ) || null;
		const inGroup = ( el ) => !! ( el && group && group.contains( el ) );
		const decoyEl = document.querySelector( 'main #review-authored-article, main .authored-note, main pre.wp-block-code' );
		return {
			marked: !! ( content && content.classList.contains( 'cr-wide-column' ) ),
			contentWidth: w( content ),
			records: records.length,
			recordWidth: w( record ),
			recordParent: record && record.parentElement ? record.parentElement.className : null,
			recordTop: !! ( record && record.parentElement === entry ),
			recordInGroup: inGroup( record ),
			groupWidth: w( group ),
			beforeInGroup: inGroup( para( 'Before, inside the synced group' ) ),
			afterInGroup: inGroup( para( 'After, inside the synced group' ) ),
			flowBoxes: document.querySelectorAll( 'main .cr-fav-flow, main .cr-repost-flow' ).length,
			decoyParent: decoyEl && decoyEl.parentElement ? decoyEl.parentElement.className : null,
			decoyInFlow: !! ( decoyEl && decoyEl.closest( '.cr-fav-flow, .cr-repost-flow' ) ),
			sentinel: document.body.textContent.includes( 'R2 AUTOSAVE SENTINEL' ),
			overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );
	g.status = response.status();
	g.markLeak = html.includes( 'data-cr-wide-card' );
	g.wideSheet = html.includes( "id='courtneyr-wide-column-css'" ) || html.includes( 'id="courtneyr-wide-column-css"' );
	result[ name ] = g;

	ok( 200 === g.status, `${ name }: HTTP 200 (${ g.status })` );
	ok( ! g.markLeak, `${ name }: no card mark left in the HTML` );
	ok( g.overflow <= 0, `${ name }: no horizontal overflow` );
	ok( g.marked === g.wideSheet, `${ name }: marker and wide stylesheet together (${ g.marked }, ${ g.wideSheet })` );
	if ( g.marked ) {
		ok( g.records >= 1, `${ name }: marked wide and a record prints (${ g.records })` );
		ok( g.recordTop && Math.abs( g.recordWidth - g.contentWidth ) <= 1, `${ name }: marked wide and the record is a top-level box at the column's width (${ g.recordWidth } of ${ g.contentWidth }, parent ${ g.recordParent })` );
	} else {
		ok( 0 === g.flowBoxes, `${ name }: not marked, no reading-width box (${ g.flowBoxes })` );
		ok( null === g.recordWidth || g.recordWidth <= 768.5, `${ name }: not marked, the record keeps a base width (${ g.recordWidth })` );
	}
	for ( const [ prop, want ] of Object.entries( width >= 1024 ? EXPECT[ name ] || {} : {} ) ) {
		ok( g[ prop ] === want, `${ name }: ${ prop } is ${ want } (${ g[ prop ] })` );
	}
}
await browser.close();
writeFileSync( out, JSON.stringify( result, null, 1 ) );
console.log( `${ pass } passed, ${ fails } failed (${ rows.length } pages at ${ width } px)` );
process.exit( fails ? 1 : 0 );
