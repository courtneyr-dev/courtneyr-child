// `CR_SPEC=shared-paint.spec.mjs npm run captures -- --project=1280-light`: the W1
// shared paint in cr-post-kinds.css, cr-media-shelf.css and inc/stamps.php, checked
// on a blank page with the stylesheets inlined, so it needs no site. The stamp test
// runs `php tests/php/stamps-test.php --svg` for its markup.
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const css = ( file ) => fs.readFileSync( new URL( `../../assets/css/${ file }`, import.meta.url ), 'utf8' );
const STYLES = [ 'tokens.css', 'cr-post-kinds.css', 'cr-media-shelf.css' ];

async function page( p, bodyClass, html, styles = STYLES ) {
	await p.setContent( `<!doctype html><html><head></head><body class="${ bodyClass }">${ html }</body></html>` );
	for ( const file of styles ) {
		await p.addStyleTag( { content: css( file ) } );
	}
}

// A card in the plugin's two-layer markup, every part the .k-play rules name.
const card = ( classes, media = true ) => `
	<article class="pk-card ${ classes }">
		<div class="pk-badge"></div>
		<div class="pk-body">
			<span class="pk-kindlabel">Play</span>
			<div class="pk-caption"><h2 class="pk-title p-name"><a href="#t">Title</a></h2><p class="pk-sub">Sub</p></div>
			<div class="pk-stars">★</div>
			${ media ? '<div class="pk-media"><img class="pk-thumb--poster pk-thumb" alt=""></div>' : '' }
			<div class="pk-excerpt">Excerpt</div>
			<div class="pk-meta"><a class="pk-link" href="#l">Link</a></div>
			<div class="pk-stream-date"><time>Date</time></div>
		</div>
	</article>`;

// The theme's play objects (TBOARD, TVIDEO) and PCARD's board card marker.
const OBJECTS = [ 'cr-spine', 'cr-cabinet', 'cr-cartridge', 'cr-scorepad', 'pk-card--tabletop' ];

// Top-level selectors of a selector list (commas inside :is()/:not() stay put).
function splitSelectors( list ) {
	const out = [];
	let depth = 0;
	let start = 0;
	for ( let i = 0; i < list.length; i++ ) {
		if ( '(' === list[ i ] ) {
			depth++;
		} else if ( ')' === list[ i ] ) {
			depth--;
		} else if ( ',' === list[ i ] && 0 === depth ) {
			out.push( list.slice( start, i ).trim() );
			start = i + 1;
		}
	}
	out.push( list.slice( start ).trim() );
	return out;
}

// Every style rule of the stylesheets on the page, @media and @supports included.
async function rulesMatching( p, needle ) {
	return p.evaluate( ( n ) => {
		const found = [];
		const walk = ( rules ) => {
			for ( const r of rules ) {
				if ( r.selectorText && new RegExp( n ).test( r.selectorText ) ) {
					found.push( r.selectorText );
				}
				if ( r.cssRules ) {
					walk( r.cssRules );
				}
			}
		};
		for ( const sheet of document.styleSheets ) {
			walk( sheet.cssRules );
		}
		return found;
	}, needle );
}

test( 'every .k-play rule paints the empty-group card and none of the play objects (W1-T0; PKIW 237 check gaps 4 and 5)', async ( { page: p } ) => {
	const tree = ( label ) => [ true, false ].map( ( media ) => `<li class="kind-play" data-tree="${ label }">${ card( label === 'plain' ? 'k-play h-entry' : `k-play h-entry ${ label }`, media ) }</li>` ).join( '' );
	const trees = [ 'plain', ...OBJECTS ];
	await page( p, 'single single-post kind-play', `
		<div class="cr-stream-page"><ul class="wp-block-post-template">${ trees.map( tree ).join( '' ) }</ul></div>
		<div class="single-post__content">${ trees.map( ( t ) => `<div data-tree="${ t }">${ card( t === 'plain' ? 'k-play' : `k-play ${ t }` ) }</div>` ).join( '' ) }</div>` );

	const selectors = ( await rulesMatching( p, 'k-play|kind-play' ) ).flatMap( splitSelectors ).filter( ( s ) => /k-play|kind-play/.test( s ) );
	expect( selectors.length, 'cr-post-kinds.css still has play rules' ).toBeGreaterThan( 40 );

	const result = await p.evaluate( ( list ) => {
		// matches() takes no pseudo-elements: test the element that carries them.
		const bare = ( s ) => s.replace( /::?(before|after|marker|placeholder)\b/g, '' );
		const cardParts = ( tree ) => [ ...document.querySelectorAll( `[data-tree="${ tree }"] article, [data-tree="${ tree }"] article *` ) ];
		const trees = [ ...new Set( [ ...document.querySelectorAll( '[data-tree]' ) ].map( ( e ) => e.dataset.tree ) ) ];
		const leaks = [];
		const unmatched = [];
		for ( const s of list ) {
			const b = bare( s );
			const onCard = /\.pk-card|k-play/.test( s ) && ! /li\.kind-play/.test( s );
			if ( ! onCard ) {
				continue;
			}
			if ( ! cardParts( 'plain' ).some( ( el ) => el.matches( b ) ) ) {
				unmatched.push( s );
			}
			for ( const tree of trees.filter( ( t ) => 'plain' !== t ) ) {
				if ( cardParts( tree ).some( ( el ) => el.matches( b ) ) ) {
					leaks.push( `${ tree }: ${ s }` );
				}
			}
		}
		return { leaks, unmatched };
	}, selectors );

	expect( result.unmatched, 'each card rule still paints the empty-group card (the fixture is complete)' ).toEqual( [] );
	expect( result.leaks, 'no play rule reaches a play object' ).toEqual( [] );
} );

test( 'the empty-group card keeps the shipped play paint', async ( { page: p } ) => {
	await page( p, 'single single-post kind-play', `<div class="cr-stream-page"><ul class="wp-block-post-template"><li class="kind-play">${ card( 'k-play h-entry' ) }</li></ul></div>` );
	const shell = await p.locator( '.cr-stream-page .pk-card' ).evaluate( ( el ) => {
		const cs = getComputedStyle( el );
		const title = getComputedStyle( el.querySelector( '.pk-title' ), '::before' );
		return { bg: cs.backgroundColor, radius: cs.borderRadius, kind: cs.getPropertyValue( '--pk-kind' ).trim(), meeple: title.content, meepleBg: title.backgroundColor };
	} );
	expect( shell ).toEqual( { bg: 'rgb(36, 28, 74)', radius: '5px', kind: '#5d72a3', meeple: '""', meepleBg: 'rgb(251, 133, 0)' } );
} );

test( 'read spines are flat Russian violet with a soft violet edge, and keep their hard page shadows (PKIW 234)', async ( { page: p } ) => {
	const book = '<div class="pk-media"><img class="cr-book__img" alt=""></div>';
	await page( p, 'single single-post kind-read', `
		<div class="single-post__content"><article class="pk-card k-read cr-book" id="single">${ book }</article></div>
		<div class="cr-stream-page"><ul><li class="kind-read"><article class="pk-card pk-card--stream k-read cr-book--stream" id="stream">${ book }</article></li></ul></div>` );
	const probe = await p.evaluate( () => {
		const out = {};
		for ( const id of [ 'single', 'stream' ] ) {
			const media = document.querySelector( `#${ id } .pk-media` );
			const spine = getComputedStyle( media, '::before' );
			const pages = getComputedStyle( media, '::after' );
			out[ id ] = { image: spine.backgroundImage, color: spine.backgroundColor, edge: spine.boxShadow, pages: pages.boxShadow };
		}
		return out;
	} );
	expect( probe.single.image ).toBe( 'none' );
	expect( probe.stream.image ).toBe( 'none' );
	for ( const id of [ 'single', 'stream' ] ) {
		expect( probe[ id ].color, `${ id } spine fill` ).toBe( 'rgb(36, 28, 74)' );
		expect( probe[ id ].edge, `${ id } spine edge` ).toContain( 'rgb(61, 47, 110)' );
	}
	expect( probe.single.pages ).toMatch( /6px 6px 0px 0px$/ );
	expect( probe.stream.pages ).toMatch( /4px 4px 0px 0px$/ );
	expect( await rulesMatching( p, 'cr-book__hand' ), 'no rule paints the Stream book slogan' ).toEqual( [] );
} );

// Inside the archive wrappers whose post divider (components.css, cr-archives.css)
// also paints each item's ::after.
const shelf = ( items ) => `
	<div class="cr-archive"><div class="cr-archive-stream cr-shelf-boards">
		<div class="wp-block-query">
			${ items ? `<div class="wp-block-post-template pkiw-grouped">
				<section class="pkiw-group" data-pkiw-group="board"><h2 class="pkiw-group__heading">Game Night</h2>
					<ul class="pkiw-group__items">${ Array.from( { length: items }, ( _, i ) => `<li class="wp-block-post post-${ i + 1 } kind-play"><article class="pk-card k-play"><h3 class="pk-title"><a href="#p${ i }">Game ${ i }</a></h3></article></li>` ).join( '' ) }</ul>
				</section>
			</div>` : '<p class="wp-block-query-no-results">No plays yet.</p>' }
		</div>
	</div></div>`;

const ARCHIVE_STYLES = [ ...STYLES, 'components.css', 'cr-archives.css' ];

test( 'shelf boards: .cr-shelf-boards section items stand on the shipped boards (X13)', async ( { page: p } ) => {
	await page( p, 'archive tax-kind', `${ shelf( 3 ) }
		<div class="cr-test-swatch" style="background: var(--cr-blue-green); box-shadow: inset 0 3px 0 var(--cr-sky-blue), 0 4px 0 var(--cr-cerulean); border-color: var(--cr-prussian-blue)"></div>
		<div class="cr-test-elsewhere"><section class="pkiw-group"><ul class="pkiw-group__items"><li class="wp-block-post kind-eat">Menu line</li></ul></section></div>`, ARCHIVE_STYLES );
	const got = await p.evaluate( () => {
		const swatch = getComputedStyle( document.querySelector( '.cr-test-swatch' ) );
		const items = [ ...document.querySelectorAll( '.cr-shelf-boards .pkiw-group__items > li' ) ];
		const list = getComputedStyle( document.querySelector( '.cr-shelf-boards .pkiw-group__items' ) );
		const elsewhere = getComputedStyle( document.querySelector( '.cr-test-elsewhere li' ), '::after' );
		return {
			swatch: { bg: swatch.backgroundColor, shadow: swatch.boxShadow, upright: swatch.borderTopColor },
			boards: items.map( ( li ) => {
				const b = getComputedStyle( li, '::after' );
				return { content: b.content, position: b.position, bg: b.backgroundColor, shadow: b.boxShadow, height: Math.round( parseFloat( b.height ) * 10 ) / 10, transform: b.transform, events: b.pointerEvents, li: getComputedStyle( li ).position, room: Math.round( parseFloat( getComputedStyle( li ).paddingBottom ) * 10 ) / 10 };
			} ),
			list: { clip: list.overflowX, left: list.borderLeftColor, leftWidth: list.borderLeftWidth, bottom: list.borderBottomColor, shadow: list.boxShadow },
			elsewhere: elsewhere.content,
		};
	} );
	expect( got.boards ).toHaveLength( 3 );
	for ( const b of got.boards ) {
		expect( b ).toEqual( { content: '""', position: 'absolute', bg: got.swatch.bg, shadow: got.swatch.shadow, height: 13.6, transform: 'none', events: 'none', li: 'relative', room: 25.6 } ); // 0.85rem board; board plus 0.75rem of room
	}
	expect( got.list.clip, 'the shelf clips each board at its edge' ).toBe( 'clip' );
	expect( got.list.left, 'Prussian uprights' ).toBe( got.swatch.upright );
	expect( got.list.bottom ).toBe( got.swatch.upright );
	expect( got.list.leftWidth ).not.toBe( '0px' );
	expect( got.elsewhere, 'a grouped list outside .cr-shelf-boards (the eat and drink menus) gets no board' ).toBe( 'none' );
} );

test( 'shelf boards: a zero-post play or read archive draws no bare boards', async ( { page: p } ) => {
	await page( p, 'archive tax-kind', `${ shelf( 0 ) }<div class="cr-media-shelf"><div class="wp-block-query"><p class="wp-block-query-no-results">No listens yet.</p></div></div>`, ARCHIVE_STYLES );
	const got = await p.evaluate( () => {
		const boards = [ ...document.querySelectorAll( '.cr-shelf-boards, .cr-shelf-boards *' ) ].flatMap( ( el ) => [ '::before', '::after' ].map( ( pe ) => getComputedStyle( el, pe ).content ) ).filter( ( c ) => 'none' !== c && 'normal' !== c );
		return { boards, mediaShelf: getComputedStyle( document.querySelector( '.cr-media-shelf .wp-block-query' ), '::before' ).content };
	} );
	expect( got.boards, 'no pseudo-element draws anything in the empty .cr-shelf-boards archive' ).toEqual( [] );
	expect( got.mediaShelf, 'the shipped listen and watch empty boards are unchanged' ).toBe( '""' );
} );

test( 'stamp text stays inside its viewBox, Currently Reading and Abandoned included (P21)', async ( { page: p } ) => {
	const stamps = JSON.parse( execFileSync( 'php', [ new URL( '../php/stamps-test.php', import.meta.url ).pathname, '--svg' ], { encoding: 'utf8' } ) );
	await page( p, 'single single-post kind-read', `<div class="cr-stream-page">${ stamps.map( ( s ) => `<div class="cr-test-stamp" data-key="${ s.key }" data-group="${ s.group }">${ s.svg }</div>` ).join( '' ) }</div>` );
	const boxes = await p.evaluate( () => [ ...document.querySelectorAll( '.cr-test-stamp' ) ].flatMap( ( wrap ) => {
		const svg = wrap.querySelector( 'svg' );
		const vb = svg.viewBox.baseVal;
		return [ ...svg.querySelectorAll( 'text' ) ].filter( ( t ) => ! t.querySelector( 'textPath' ) ).map( ( t ) => {
			const b = t.getBBox();
			const r = ( n ) => Math.round( n * 10 ) / 10;
			return { key: wrap.dataset.key, group: wrap.dataset.group, line: t.getAttribute( 'class' ), text: t.textContent, x: r( b.x ), right: r( b.x + b.width ), vbWidth: vb.width, inside: b.x >= -0.5 && b.x + b.width <= vb.width + 0.5 && b.y >= -0.5 && b.y + b.height <= vb.height + 0.5 };
		} );
	} ) );
	const outside = boxes.filter( ( b ) => ! b.inside );
	expect( boxes.filter( ( b ) => /Currently Reading|Abandoned/.test( b.key ) && /big/.test( b.line ) ).length, 'the long labels were drawn' ).toBeGreaterThanOrEqual( 6 );
	expect( outside, 'every stamp line sits inside its viewBox' ).toEqual( [] );
} );
