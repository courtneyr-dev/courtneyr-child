// Live-site geometry for the wide-column design: print, text enlargement, RTL and the page-splitting cases that the
// frozen-fixture harness (../read-single-wide-column.mjs) does not cover. Runs against the local Studio site.
//   node live-geometry.mjs <urls.tsv> [wide-names-comma-separated]   (exit 1 on any failed assertion)
// A name in the wide list must widen to the wide size at >= 1280px; every other page must keep the reading size.
import { readFileSync } from 'node:fs';
import { chromium } from '@playwright/test';

const [, , tsv, wideArg = ''] = process.argv;
const WIDE = new Set( wideArg.split( ',' ).filter( Boolean ) );
const rows = readFileSync( tsv, 'utf8' ).trim().split( '\n' ).map( ( l ) => l.split( '\t' ) );
const browser = await chromium.launch( { executablePath: process.env.CR_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' } );
let fails = 0;
const check = ( ok, msg ) => { if ( ! ok ) { fails++; console.log( 'FAIL', msg ); } };

const measure = ( page ) => page.evaluate( () => {
	const r = ( el ) => ( el ? el.getBoundingClientRect() : null );
	const c = r( document.querySelector( '.single-post__content' ) );
	const t = r( document.querySelector( '.single-post__title' ) );
	const b = r( document.querySelector( '.cr-book' ) );
	const rec = r( document.querySelector( '.cr-record' ) );
	const de = document.documentElement;
	return {
		content: c && { l: c.left, r: c.right, w: c.width },
		title: t && { l: t.left, r: t.right },
		book: b && { l: b.left, r: b.right },
		record: rec && { l: rec.left, r: rec.right, w: rec.width },
		overflow: de.scrollWidth - de.clientWidth,
		wideClass: document.querySelector( '.single-post__content' )?.classList.contains( 'cr-wide-column' ) ?? false,
	};
} );

for ( const [ name, url ] of rows ) {
	const wide = WIDE.has( name );
	// 1. Screen widths, light, LTR.
	for ( const width of [ 1448, 1280, 1024, 768, 390 ] ) {
		const page = await browser.newPage( { viewport: { width, height: 900 } } );
		await page.goto( url, { waitUntil: 'networkidle' } );
		const m = await measure( page );
		check( m.overflow <= 0, `${ name } @${ width }: horizontal overflow ${ m.overflow }px` );
		check( m.wideClass === wide, `${ name } @${ width }: cr-wide-column class is ${ m.wideClass }, expected ${ wide }` );
		if ( m.content && width >= 1280 ) {
			check( wide ? Math.abs( m.content.w - 1100 ) < 0.5 : Math.abs( m.content.w - 720 ) < 0.5, `${ name } @${ width }: content ${ m.content.w }px, expected ${ wide ? 1100 : 720 }` );
		}
		if ( wide && width >= 1280 && m.title && m.book ) {
			check( Math.abs( m.title.l - m.book.l ) < 0.5, `${ name } @${ width }: title left ${ m.title.l } vs book left ${ m.book.l }` );
			check( m.record && Math.abs( m.record.r - m.content.r ) < 0.5, `${ name } @${ width }: record edge does not reach the column edge` );
		}
		if ( width <= 768 && m.content ) {
			check( m.content.w <= Math.min( 720, width ) + 0.5, `${ name } @${ width }: content wider than reading size (${ m.content.w })` );
		}
		await page.close();
	}
	// 2. Print: the column must fit the sheet of paper.
	{
		const page = await browser.newPage( { viewport: { width: 816, height: 1056 } } );
		await page.emulateMedia( { media: 'print' } );
		await page.goto( url, { waitUntil: 'networkidle' } );
		const m = await measure( page );
		check( m.overflow <= 0, `${ name } print 816px: horizontal overflow ${ m.overflow }px` );
		check( ! m.content || m.content.r <= 816.5, `${ name } print: content right edge ${ m.content?.r } past the 816px page` );
		await page.close();
	}
	// 3. Text enlargement: root font 32px (200% of 16px) at 1280px; the spread stacks, nothing overflows.
	{
		const page = await browser.newPage( { viewport: { width: 1280, height: 900 } } );
		await page.goto( url, { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: 'html { font-size: 32px !important; }' } );
		const m = await measure( page );
		check( m.overflow <= 0, `${ name } 200% text: horizontal overflow ${ m.overflow }px` );
		await page.close();
	}
	// 4. RTL: the title starts where the book starts, on the inline-start side.
	{
		const page = await browser.newPage( { viewport: { width: 1448, height: 900 } } );
		await page.goto( url, { waitUntil: 'networkidle' } );
		await page.evaluate( () => { document.documentElement.dir = 'rtl'; } );
		const m = await measure( page );
		check( m.overflow <= 0, `${ name } rtl: horizontal overflow ${ m.overflow }px` );
		if ( wide && m.title && m.book ) {
			check( Math.abs( m.title.r - m.book.r ) < 0.5, `${ name } rtl: title right ${ m.title.r } vs book right ${ m.book.r }` );
		}
		await page.close();
	}
	console.log( 'done', name, wide ? '(wide)' : '(reading)' );
}
await browser.close();
console.log( fails ? `${ fails } assertions failed` : 'all assertions passed' );
process.exit( fails ? 1 : 0 );
