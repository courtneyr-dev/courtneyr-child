// `CR_SPEC=tag-261.spec.mjs CR_TAG_SET=<archive|single|stream|privacy>`: issue 261
// tag archive, single and Stream contracts. Read-only GETs; seed with the vault's
// assets/261-tag-posts/seed-261.php (`seed set=<name>`; archive and stream exclude each other).
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_TAG_ARCHIVE_PATH || '/kind/tag/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_TAG_STREAM_PATH || '/stream/';
const SET = process.env.CR_TAG_SET || 'archive';
const REQUIRE_SEED = '1' === process.env.CR_TAG_REQUIRE_SEED;
const WIDTHS = [ 1440, 800, 375, 320 ];
const YELLOW = 'rgb(255, 183, 3)';
const ALLOWED_BANDS = [ 'russian-violet', 'prussian-blue', 'cerulean', 'ut-orange', 'sky-blue', 'selective-yellow', 'periwinkle', 'blue-green', 'light-orange' ];
const PRIVACY_KEYS = [ 'x1', 'x2', 'x3', 'x4', 'x5', 'x6', 'u1', 'u2' ];

const slugOf = ( key ) => `seed-261-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/${ slugOf( key ) }/`;
const archivePath = ( number = 1 ) => 1 === number ? ARCHIVE : `${ ARCHIVE }page/${ number }/`;

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_TAG_REQUIRE_SEED=1: ${ reason }` );
	}
	test.skip( missing, reason );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 400 ? 900 : 1100 } );
	const response = await page.goto( path, { waitUntil: 'load' } );
	await page.evaluate( () => document.fonts.ready );
	return response;
}

async function openArchive( page, number = 1, width = 1440 ) {
	const response = await open( page, archivePath( number ), width );
	skipUnlessSeeded( 404 === response?.status(), `no archive page ${ number }` );
	skipUnlessSeeded( 0 === await page.locator( '.pkiw-tag-entry' ).count(), 'the tag archive fixtures are not seeded' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status(), `no single fixture ${ key }` );
	return response;
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

// Scoped to the tag markup: the single template's term chips fail dark contrast on
// every post (pre-existing, recorded in the W8-T261 PR as an A/B), outside this lane.
async function axe( page, rules, root = 'main' ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async ( [ only, selector ] ) => ( await window.axe.run( document.querySelector( selector ) || document, only ? { runOnly: only } : {} ) ).violations.map( ( v ) => ( { id: v.id, nodes: v.nodes.map( ( n ) => n.target.join( ' ' ) ) } ) ), [ rules, root ] );
}

async function setTheme( page, theme ) {
	await page.evaluate( ( value ) => {
		document.documentElement.dataset.theme = value;
	}, theme );
}

async function focusPaint( locator ) {
	await locator.focus();
	return locator.evaluate( async ( node ) => {
		await Promise.all( node.getAnimations().map( ( animation ) => animation.finished ) );
		const style = getComputedStyle( node, '::after' );
		return { color: style.outlineColor, width: style.outlineWidth, shadow: style.boxShadow };
	} );
}

const stickers = ( page ) => page.locator( '.cr-tag-sheets .pkiw-tag-entry' );

test.describe( 'Tag archive', () => {
	test.skip( 'archive' !== SET, 'archive fixture set' );

	test( 'header, sheets and one article per post follow the archive contract', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'Tag' );
		const tiles = page.locator( 'main h1 .cr-cutout__tiles[aria-hidden="true"] .cr-cutout__tile' );
		await expect( tiles ).toHaveCount( 3 );
		await expect( page.locator( '.wp-block-term-description' ) ).toContainText( 'Tags external content or a person with a category or label.' );
		await expect( page.locator( '.cr-archive-identity__glyph' ) ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( page.locator( '.cr-archive-identity a, .cr-archive-identity [tabindex]' ) ).toHaveCount( 0 );

		const headings = await page.locator( '.pkiw-tag-section__heading' ).allInnerTexts();
		expect( headings.length ).toBeGreaterThan( 1 );
		const keys = headings.map( ( text ) => text.toLowerCase() );
		const other = keys.indexOf( 'other' );
		const sorted = keys.filter( ( key ) => 'other' !== key );
		expect( sorted ).toEqual( [ ...sorted ].sort( ( a, b ) => a.localeCompare( b ) ) );
		if ( -1 !== other ) {
			expect( other ).toBe( keys.length - 1 );
		}

		const hrefs = await stickers( page ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.querySelector( 'a' ).getAttribute( 'href' ) ) );
		expect( new Set( hrefs ).size ).toBe( hrefs.length );
		const a04 = page.locator( `.pkiw-tag-section:has(a[href*="${ slugOf( 'A04' ) }"]) .pkiw-tag-section__heading` );
		if ( await a04.count() ) {
			await expect( a04 ).toHaveText( 'IndieWeb' );
		}
	} );

	test( 'each sticker is an h-entry with one link in an H3, hidden props and no visible extras', async ( { page } ) => {
		await openArchive( page );
		for ( const entry of await stickers( page ).all() ) {
			await expect( entry ).toHaveClass( /\bh-entry\b/ );
			await expect( entry.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( entry.locator( 'h3 > a.u-url' ) ).toHaveCount( 1 );
			await expect( entry.locator( 'time.dt-published[hidden]' ) ).toHaveCount( 1 );
			await expect( entry.locator( '[hidden] .p-author, [hidden].p-author' ) ).not.toHaveCount( 0 );
			await expect( entry.locator( '.cr-name-tag__band[aria-hidden="true"]' ) ).toHaveText( /HELLO\s*my name is/i );
			const visible = await entry.evaluate( ( node ) => [ ...node.querySelectorAll( 'img, svg, time:not([hidden]), .p-category:not(data)' ) ].filter( ( child ) => ! child.closest( '[hidden]' ) && null !== child.offsetParent ).length );
			expect( visible ).toBe( 0 );
			const band = await entry.getAttribute( 'style' );
			const match = /--cr-name-tag-band:var\(--cr-([a-z-]+)\)/.exec( band || '' );
			expect( ALLOWED_BANDS ).toContain( match?.[ 1 ] );
		}
		const html = await page.locator( 'main' ).innerHTML();
		for ( const banned of [ 'Public consent', 'Request correction', 'person tag', 'notify the tagged site', 'I LABELED THIS' ] ) {
			expect( html ).not.toContain( banned );
		}
		const repeated = page.locator( `a[aria-labelledby]` );
		if ( await repeated.count() ) {
			const names = await repeated.evaluateAll( ( links ) => links.map( ( link ) => link.getAttribute( 'aria-labelledby' ).split( ' ' ).map( ( id ) => document.getElementById( id )?.textContent.trim() ).join( ' ' ) ) );
			expect( new Set( names ).size ).toBe( names.length );
		}
	} );

	test( 'sheets use 3, 2 and 1 columns with DOM order equal to reading order and no overflow', async ( { page } ) => {
		const expected = new Map( [ [ 1440, 3 ], [ 800, 2 ], [ 375, 1 ], [ 320, 1 ] ] );
		for ( const width of WIDTHS ) {
			await openArchive( page, 1, width );
			const columns = await page.locator( '.pkiw-tag--sectioned' ).evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length );
			expect( columns, `${ width }px columns` ).toBe( expected.get( width ) );
			const order = await page.locator( '.pkiw-tag-section' ).evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
				const rect = node.getBoundingClientRect();
				return { index, top: Math.round( rect.top ), left: Math.round( rect.left ) };
			} ) );
			const visual = [ ...order ].sort( ( a, b ) => 2 < Math.abs( a.top - b.top ) ? a.top - b.top : a.left - b.left ).map( ( item ) => item.index );
			expect( visual, `${ width }px reading order` ).toEqual( order.map( ( item ) => item.index ) );
			await assertNoOverflow( page, `${ width }px archive` );
		}
	} );

	test( 'hit area, focus ring, paint and contrast in light and dark', async ( { page } ) => {
		for ( const theme of [ 'light', 'dark' ] ) {
			await openArchive( page );
			await setTheme( page, theme );
			for ( const entry of await stickers( page ).all() ) {
				const box = await entry.locator( 'a' ).evaluate( ( link ) => {
					const rect = getComputedStyle( link, '::after' );
					const host = link.closest( 'article' ).getBoundingClientRect();
					return { position: rect.position, width: host.width, height: host.height };
				} );
				expect( box.position ).toBe( 'absolute' );
				expect( box.width ).toBeGreaterThanOrEqual( 44 );
				expect( box.height ).toBeGreaterThanOrEqual( 44 );
			}
			const focus = await focusPaint( stickers( page ).first().locator( 'a' ) );
			expect( focus.width ).toBe( '3px' );
			expect( focus.color ).toBe( YELLOW );
			expect( focus.shadow ).toMatch( /8px/ );
			const sheet = await page.locator( '.pkiw-tag-section' ).first().evaluate( ( node ) => {
				const style = getComputedStyle( node );
				return { radius: style.borderTopLeftRadius, shadow: style.boxShadow, image: style.backgroundImage };
			} );
			expect( sheet.radius ).toBe( '0px' );
			expect( sheet.shadow ).toMatch( /4px 4px 0px/ );
			expect( sheet.image ).toBe( 'none' );
			expect( await axe( page, [ 'color-contrast' ] ) ).toEqual( [] );
		}
	} );

	test( 'axe finds no violations and the pager follows the last sheet', async ( { page } ) => {
		await openArchive( page );
		expect( await axe( page ) ).toEqual( [] );
		const pager = page.locator( 'nav[aria-label="Pagination"]' );
		await expect( pager ).toHaveCount( 1 );
		await expect( pager.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
		const after = await page.evaluate( () => {
			const sheets = document.querySelector( '.cr-tag-sheets' );
			const nav = document.querySelector( 'nav[aria-label="Pagination"]' );
			return !! ( sheets.compareDocumentPosition( nav ) & Node.DOCUMENT_POSITION_FOLLOWING ) && ! sheets.contains( nav );
		} );
		expect( after ).toBe( true );
	} );

	test( 'forced colors and reduced motion keep outlines and drop lift', async ( { page } ) => {
		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
		await openArchive( page );
		const paint = await stickers( page ).first().evaluate( ( node ) => ( { border: getComputedStyle( node ).borderTopWidth, transition: getComputedStyle( node ).transitionProperty } ) );
		expect( paint.border ).toBe( '2px' );
		expect( paint.transition ).not.toContain( 'translate' );
	} );
} );

test.describe( 'Tag single', () => {
	test.skip( 'single' !== SET, 'single fixture set' );

	test( 'S01 content target: one external link, host, description, image and a three-label sticker', async ( { page } ) => {
		await openSingle( page, 'S01' );
		const single = page.locator( '.cr-name-tag-single' );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( single.locator( 'a[href]' ) ).toHaveCount( 1 );
		await expect( single.locator( 'a.u-tag-of[target="_blank"][rel="noopener noreferrer"]' ) ).toHaveCount( 1 );
		await expect( single.locator( 'a.u-tag-of svg[aria-hidden="true"]' ) ).toHaveCount( 1 );
		await expect( single.locator( '.cr-name-tag-clipping__host' ) ).toHaveText( 'guides.example.com' );
		await expect( single.locator( '.cr-name-tag-clipping__description' ) ).toHaveCount( 1 );
		await expect( single.locator( '.cr-name-tag-clipping__image img' ) ).not.toHaveAttribute( 'alt', '' );
		await expect( single.locator( '.cr-name-tag__write-in .p-category' ) ).toHaveText( [ 'Accessibility', 'Images', 'Writing' ] );
		await expect( single.locator( '.cr-name-tag__write-in .screen-reader-text' ) ).toHaveText( /Labels:/ );
		const fonts = await single.locator( '.cr-name-tag__write-in .p-category' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { family: getComputedStyle( node ).fontFamily, size: parseFloat( getComputedStyle( node ).fontSize ) } ) ) );
		for ( const font of fonts ) {
			expect( font.family ).toContain( 'Rock Salt' );
			expect( font.size ).toBeGreaterThanOrEqual( 16 );
		}
		expect( await single.innerHTML() ).not.toContain( 'View the tagged page' );
		await expect( page.locator( '.pkiw-singular-entry.h-entry, .h-entry' ).first() ).toHaveCount( 1 );
	} );

	test( 'S02 and S03 person targets: h-card with local photo or initial tile', async ( { page } ) => {
		await openSingle( page, 'S02' );
		await expect( page.locator( '.cr-name-tag-clipping .u-tag-of.h-card img.u-photo[alt=""]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-name-tag-clipping .h-card a.p-name.u-url' ) ).toHaveText( 'Example Person One' );
		await openSingle( page, 'S03' );
		await expect( page.locator( '.cr-name-tag-clipping__initial[aria-hidden="true"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-name-tag-clipping .u-photo' ) ).toHaveCount( 0 );
	} );

	test( 'S04 has no sticker and no empty boxes; only the sticker and tape rotate', async ( { page } ) => {
		await openSingle( page, 'S04' );
		await expect( page.locator( '.cr-name-tag-single__sticker' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-name-tag-clipping__description, .cr-name-tag-clipping__image' ) ).toHaveCount( 0 );
		await openSingle( page, 'S01' );
		const rotated = await page.locator( '.cr-name-tag-single' ).evaluate( ( root ) => [ ...root.querySelectorAll( '*' ) ].filter( ( node ) => 'none' !== getComputedStyle( node ).transform && ! node.matches( 'svg, svg *' ) ).map( ( node ) => node.className.baseVal ?? node.className ) );
		expect( rotated.every( ( name ) => /cr-name-tag-single__sticker|cr-name-tag__tape/.test( name ) ) ).toBe( true );
	} );

	test( 'layout, overflow, dark contrast and axe', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await openSingle( page, 'S01', width );
			await assertNoOverflow( page, `${ width }px single` );
			const side = await page.locator( '.cr-name-tag-single' ).evaluate( ( root ) => {
				const sticker = root.querySelector( '.cr-name-tag-single__mount' ).getBoundingClientRect();
				const clipping = root.querySelector( '.cr-name-tag-clipping' ).getBoundingClientRect();
				return Math.abs( sticker.top - clipping.top ) < 4 && sticker.right <= clipping.left;
			} );
			expect( side, `${ width }px sticker beside clipping` ).toBe( width >= 1024 );
		}
		for ( const theme of [ 'light', 'dark' ] ) {
			await openSingle( page, 'S01' );
			await setTheme( page, theme );
			expect( await axe( page, null, '.cr-name-tag-single' ) ).toEqual( [] );
		}
	} );
} );

test.describe( 'Tag Stream', () => {
	test.skip( 'stream' !== SET, 'Stream fixture set' );

	test( 'tag posts become one-link name-tag cards in the collage', async ( { page } ) => {
		for ( const width of [ 1440, 375, 320 ] ) {
			await open( page, STREAM, width );
			const cards = page.locator( '.cr-name-tag-card' );
			skipUnlessSeeded( 0 === await cards.count(), 'the tag Stream fixtures are not seeded' );
			for ( const card of await cards.all() ) {
				await expect( card.locator( 'a[href]' ) ).toHaveCount( 1 );
				await expect( card.locator( 'h2 > a.u-url, h3 > a.u-url, h4 > a.u-url' ) ).toHaveCount( 1 );
				await expect( card.locator( 'time.dt-published' ) ).toHaveCount( 1 );
				await expect( card.locator( '[hidden] .u-tag-of, [hidden] .p-author' ) ).not.toHaveCount( 0 );
				await expect( card.locator( 'img' ) ).toHaveCount( 0 );
				const order = await card.evaluate( ( node ) => [ ...node.children ].map( ( child ) => child.className.split( ' ' ).find( ( name ) => name.startsWith( 'cr-name-tag-card__' ) ) || child.tagName.toLowerCase() ) );
				const expected = [ 'cr-name-tag-card__kind', 'cr-name-tag-card__sticker', 'cr-name-tag-card__title', 'cr-name-tag-card__host', 'cr-name-tag-card__date', 'span' ].filter( ( name ) => order.includes( name ) );
				expect( order.filter( ( name ) => expected.includes( name ) ) ).toEqual( expected );
				expect( await card.innerHTML() ).not.toContain( 'View tag post' );
			}
			await assertNoOverflow( page, `${ width }px Stream` );
		}
		for ( const theme of [ 'light', 'dark' ] ) {
			await open( page, STREAM );
			await setTheme( page, theme );
			expect( await axe( page, [ 'color-contrast' ], '.cr-stream-page' ) ).toEqual( [] );
		}
	} );
} );

test.describe( 'Tag privacy set', () => {
	test.skip( 'privacy' !== SET, 'privacy fixture set' );

	for ( const path of [ ARCHIVE, STREAM, '/feed/', '/kind/tag/feed/', '/wp-json/wp/v2/posts?per_page=100', '/?s=Tg261' ] ) {
		test( `${ path } never names a hidden tag post's person or label`, async ( { page } ) => {
			const response = await open( page, path );
			skipUnlessSeeded( 404 === response?.status(), `no surface ${ path }` );
			const body = await page.content();
			for ( const key of PRIVACY_KEYS ) {
				expect( body ).not.toContain( `Tg261 Sentinel ${ key.toUpperCase() }` );
				expect( body ).not.toContain( `tg261-label-${ key }` );
				expect( body ).not.toContain( `tg261-${ key }.example.net` );
			}
		} );
	}

	test( 'hidden singles return 404 with no password form and the positive control prints its person', async ( { page } ) => {
		for ( const key of [ 'X1', 'X5' ] ) {
			const response = await open( page, singlePath( key ) );
			expect( response?.status(), `${ key } status` ).toBe( 404 );
			expect( await page.locator( 'form.post-password-form' ).count() ).toBe( 0 );
		}
		const response = await open( page, singlePath( 'P' ) );
		skipUnlessSeeded( 404 === response?.status(), 'no positive control' );
		await expect( page.locator( '.cr-name-tag-clipping .h-card .p-name' ) ).toHaveText( 'Tg261 Sentinel P' );
	} );
} );
