// `CR_SPEC=video-242.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs`:
// Read-only acceptance checks for the video archive, single and Stream treatment.
// The fixture scripts own all setup. This file never changes WordPress state or a user agent.
import { createRequire } from 'node:module';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join, relative } from 'node:path';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const HERE = dirname( fileURLToPath( import.meta.url ) );
const THEME = join( HERE, '../..' );
const ARCHIVE = process.env.CR_VIDEO_ARCHIVE_PATH || '/kind/video/';
const STREAM = process.env.CR_VIDEO_STREAM_PATH || '/stream/';
const STATE = process.env.CR_VIDEO_STATE || 'archive';
const REPLACE = process.env.CR_VIDEO_REPLACE || 'false';
const REQUIRE_SEED = '1' === process.env.CR_VIDEO_REQUIRE_SEED;
const WIDTHS = [ 1440, 800, 375, 320 ];
const THIRD_PARTY_MEDIA = /(?:youtube\.com|youtube-nocookie\.com|i\.ytimg\.com|\/oembed\b)/i;
const SELECTIVE_YELLOW = 'rgb(255, 183, 3)';
const PERIWINKLE = 'rgb(188, 181, 227)';
const PRINTER_IVORY = 'rgb(251, 250, 245)';
const RUSSIAN_VIOLET = 'rgb(36, 28, 74)';

function skipUnlessRequired( missing, message ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( message );
	}
	test.skip( missing, message );
}

const archivePage = ( number ) => number > 1 ? `${ ARCHIVE }page/${ number }/` : ARCHIVE;

async function fixtureLink( request, key ) {
	const response = await request.get( `/wp-json/wp/v2/posts?slug=seed-242-${ key.toLowerCase() }&_fields=link` );
	if ( ! response.ok() ) {
		return null;
	}
	const [ post ] = await response.json();
	if ( ! post?.link ) {
		return null;
	}
	const url = new URL( post.link );
	return `${ url.pathname }${ url.search }`;
}

async function openFixture( page, request, key ) {
	const path = await fixtureLink( request, key );
	skipUnlessRequired( ! path, `no fixture ${ key }; run seed-242.php first` );
	const response = await page.goto( path, { waitUntil: 'load' } );
	skipUnlessRequired( response?.status() === 404, `fixture ${ key } resolves to a 404` );
	return path;
}

async function openArchive( page, number = 1 ) {
	const response = await page.goto( archivePage( number ), { waitUntil: 'load' } );
	skipUnlessRequired( response?.status() === 404, `no video archive page ${ number }` );
	return response;
}

async function openStream( page ) {
	const response = await page.goto( STREAM, { waitUntil: 'load' } );
	skipUnlessRequired( response?.status() === 404, `no Stream at ${ STREAM }` );
	return response;
}

async function linkReport( locator ) {
	return locator.evaluate( ( root ) => {
		const hidden = ( element ) => Boolean( element.closest( '[hidden]' ) );
		const visible = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! hidden( link ) );
		const stray = [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( link ) => ! hidden( link ) );
		return { count: visible.length, stray: stray.length, names: visible.map( ( link ) => link.innerText.trim() ) };
	} );
}

async function resolvedToken( page, property, token ) {
	return page.evaluate( ( [ cssProperty, cssToken ] ) => {
		const probe = document.createElement( 'span' );
		probe.style.position = 'fixed';
		probe.style.setProperty( cssProperty, `var(${ cssToken })` );
		document.body.append( probe );
		const value = getComputedStyle( probe ).getPropertyValue( cssProperty ).trim();
		probe.remove();
		return value;
	}, [ property, token ] );
}

async function axeContrast( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations.map( ( violation ) => violation.id ) );
}

function filesUnder( root, predicate = () => true ) {
	const files = [];
	for ( const name of readdirSync( root ) ) {
		const path = join( root, name );
		if ( statSync( path ).isDirectory() ) {
			files.push( ...filesUnder( path, predicate ) );
		} else if ( predicate( path ) ) {
			files.push( path );
		}
	}
	return files;
}

async function settleTransitions( page ) {
	await page.evaluate( () => Promise.all( document.getAnimations().map( ( animation ) => animation.finished.catch( () => null ) ) ) );
}

async function expectNoHorizontalScroll( page, message ) {
	expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), message ).toBe( true );
}

async function expectVisualOrder( locator, message ) {
	const boxes = await locator.evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
		const box = node.getBoundingClientRect();
		return { index, top: Math.round( box.top + scrollY ), left: Math.round( box.left ) };
	} ) );
	const visual = [ ...boxes ].sort( ( a, b ) => Math.abs( a.top - b.top ) > 3 ? a.top - b.top : a.left - b.left ).map( ( box ) => box.index );
	expect( visual, message ).toEqual( boxes.map( ( box ) => box.index ) );
}

async function fixtureCard( page, key, selector = 'article' ) {
	const card = page.locator( `${ selector }:has(a[href*="seed-242-${ key.toLowerCase() }"])` ).first();
	skipUnlessRequired( 0 === await card.count(), `no ${ key } card on ${ page.url() }` );
	return card;
}

async function openStreamWith( page, key ) {
	for ( let number = 1; number <= 12; number++ ) {
		await page.goto( `${ STREAM }?query-1-page=${ number }`, { waitUntil: 'load' } );
		if ( await page.locator( `article.cr-tape-spine a[href*="seed-242-${ key.toLowerCase() }"]` ).count() ) {
			return;
		}
	}
	skipUnlessRequired( true, `no Stream card for ${ key } on pages 1 through 12` );
}

async function useStoredTheme( page, theme ) {
	await page.addInitScript( ( value ) => localStorage.setItem( 'courtneyr-theme', value ), theme );
}

test.describe( 'archive', () => {
	test.skip( ! [ 'archive', 'one' ].includes( STATE ), 'archive checks need the archive or one fixture set' );

	test( 'Archive page map and counts — 12, 5, repeated year and terminal 404', async ( { page } ) => {
		test.skip( 'archive' !== STATE, 'full page map needs the archive set' );
		await openArchive( page );
		const pageOne = await page.locator( '.cr-video-shelf section.pkiw-group' ).evaluateAll( ( sections ) => sections.map( ( section ) => ( {
			year: section.querySelector( 'h2.pkiw-group__heading' )?.textContent.trim(),
			count: section.querySelectorAll( ':scope > .pkiw-group__items > li.wp-block-post' ).length,
		} ) ) );
		expect( pageOne, 'A3 page 1 is 2026 times 8, then 2025 times 4' ).toEqual( [ { year: '2026', count: 8 }, { year: '2025', count: 4 } ] );
		expect( await page.locator( '.cr-video-shelf li.wp-block-post' ).count(), 'A1 page 1 has 12 real list items' ).toBe( 12 );
		await openArchive( page, 2 );
		const pageTwo = await page.locator( '.cr-video-shelf section.pkiw-group' ).evaluateAll( ( sections ) => sections.map( ( section ) => ( {
			year: section.querySelector( 'h2.pkiw-group__heading' )?.textContent.trim(),
			count: section.querySelectorAll( ':scope > .pkiw-group__items > li.wp-block-post' ).length,
		} ) ) );
		expect( pageTwo, 'A3 page 2 repeats 2025 times 4, then 2024 times 1' ).toEqual( [ { year: '2025', count: 4 }, { year: '2024', count: 1 } ] );
		expect( await page.locator( '.cr-video-shelf li.wp-block-post' ).count(), 'A18 page 2 has five real list items and no filler' ).toBe( 5 );
		const third = await page.goto( archivePage( 3 ), { waitUntil: 'load' } );
		expect( third?.status(), 'A1 page 3 is a 404' ).toBe( 404 );
	} );

	test( 'Archive header — block identity, one H1, five hidden tiles and inert badge', async ( { page } ) => {
		await openArchive( page );
		const h1 = page.locator( 'main h1.cr-archive__title' );
		await expect( h1, 'A2 has one archive H1' ).toHaveCount( 1 );
		await expect( h1, 'A2 archive H1 says Video' ).toContainText( 'Video' );
		const tiles = h1.locator( '.cr-cutout__tile' );
		await expect( tiles, 'A2 has five title tiles' ).toHaveCount( 5 );
		expect( ( await tiles.allTextContents() ).join( '' ).toLowerCase(), 'A2 tiles spell Video' ).toBe( 'video' );
		expect( await tiles.evaluateAll( ( nodes ) => nodes.every( ( node ) => Boolean( node.closest( '[aria-hidden="true"]' ) ) ) ), 'A2 every tile is aria-hidden' ).toBe( true );
		const badge = page.locator( 'main .cr-archive-identity__glyph' );
		await expect( badge, 'A2 badge is aria-hidden' ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( badge.locator( 'a, [tabindex]' ), 'A2 badge has no focus target' ).toHaveCount( 0 );
		expect( await page.locator( 'main' ).innerText(), 'A2 omits stale sample copy' ).not.toMatch( /Stream\s*[·-]\s*Kind|SAMPLE CONTENT/ );
		expect( ( await page.locator( 'main .cr-archive__description' ).innerText() ).trim().length, 'A2 term description prints' ).toBeGreaterThan( 0 );
	} );

	test( 'Archive stylesheet, headings, card structure, copy and loading policy', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( 'link[id="courtneyr-shelf-boards-css"]' ), 'A4 shelf-board stylesheet loads' ).toHaveCount( 1 );
		for ( const heading of await page.locator( '.pkiw-group__heading' ).all() ) {
			const style = await heading.evaluate( ( element ) => { const css = getComputedStyle( element ); return { tag: element.tagName, family: css.fontFamily, transform: css.transform }; } );
			expect( style.tag, 'A3 every year heading is H2' ).toBe( 'H2' );
			expect( style.family, 'A3 year heading uses Roboto Slab' ).toContain( 'Roboto Slab' );
			expect( style.transform, 'A3 heading text is upright' ).toBe( 'none' );
		}
		const cards = page.locator( 'article.cr-shelf-tape' );
		expect( await cards.count(), 'A4 every archive item renders one tape' ).toBe( 12 );
		for ( const card of await cards.all() ) {
			await expect( card.locator( ':scope > .cr-shelf-tape__shell' ), 'A5 shell is the tape first child' ).toHaveCount( 1 );
			await expect( card.locator( '.pk-title' ), 'A3 tape title is an H3' ).toHaveJSProperty( 'tagName', 'H3' );
			const title = card.locator( '.pk-title' );
			const titleNodes = await title.locator( 'a.u-url' ).evaluate( ( link ) => [ ...link.childNodes ].filter( ( node ) => Node.TEXT_NODE === node.nodeType && node.textContent.trim() ).length );
			expect( titleNodes, 'A6 title link has one title text node' ).toBe( 1 );
			const paint = await title.evaluate( ( element ) => { const css = getComputedStyle( element ); return { family: css.fontFamily, size: parseFloat( css.fontSize ), transform: css.transform, whiteSpace: css.whiteSpace, clamp: css.webkitLineClamp }; } );
			expect( paint.family, 'A6 title uses Rock Salt' ).toContain( 'Rock Salt' );
			expect( paint.size, 'A6 title is at least 16px' ).toBeGreaterThanOrEqual( 16 );
			expect( paint.transform, 'A6 title is upright' ).toBe( 'none' );
			expect( paint.whiteSpace, 'A6 title wraps normally' ).toBe( 'normal' );
			expect( paint.clamp, 'A6 title is not line-clamped' ).toMatch( /none|0/ );
			const links = await linkReport( card );
			expect( links.count, 'A9 tape has one rendered link' ).toBe( 1 );
			expect( links.stray, 'A9 hidden links stay hidden' ).toBe( 0 );
			await expect( card.locator( '.pk-badge, .pk-kindlabel, .pk-sub, .pk-media, .pk-excerpt, .pk-meta' ), 'A6 and A20 generic Stream furniture is absent' ).toHaveCount( 0 );
			expect( await card.locator( 'video, iframe, button, .able-wrapper' ).count(), 'A10 archive tape has no player or control' ).toBe( 0 );
		}
		const loading = await cards.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const image = node.querySelector( '.cr-shelf-tape__img' );
			return image ? { loading: image.getAttribute( 'loading' ), priority: image.getAttribute( 'fetchpriority' ) } : null;
		} ) );
		expect( loading.slice( 0, 3 ).filter( Boolean ).every( ( item ) => item.loading === 'eager' && item.priority === 'high' ), 'A20 first three tape positions use eager high-priority pictures' ).toBe( true );
		expect( loading.slice( 3 ).filter( Boolean ).every( ( item ) => item.loading === 'lazy' && item.priority !== 'high' ), 'A20 later pictures load lazily' ).toBe( true );
		expect( await page.locator( 'main' ).innerText(), 'A5 and A20 visible copy omits format labels and placeholders' ).not.toMatch( /Mini DV|Hi8|DIGITAL VIDEO CASSETTE|60 SP|120|SAMPLE CONTENT|Read more/ );
	} );

	test( 'Archive trusted runtime, pictures, duplicate names and empty slots', async ( { page } ) => {
		await openArchive( page );
		for ( const [ key, machine, clock, human ] of [ [ 'V01', 'PT2M14S', '2:14', '2 minutes, 14 seconds' ], [ 'V03', 'PT1H2M5S', '1:02:05', '1 hour, 2 minutes, 5 seconds' ] ] ) {
			const card = await fixtureCard( page, key, 'article.cr-shelf-tape' );
			const runtime = card.locator( '.cr-shelf-tape__runtime' );
			await expect( runtime, `A7 ${ key} runtime machine value` ).toHaveAttribute( 'datetime', machine );
			await expect( runtime, `A7 ${ key} runtime clock and hidden human text` ).toContainText( clock );
			await expect( runtime.locator( '.pk-sr-only' ), `A7 ${ key} human duration` ).toContainText( human );
		}
		for ( const key of [ 'V04', 'V05', 'V06', 'V11' ] ) {
			const card = await fixtureCard( page, key, 'article.cr-shelf-tape' );
			await expect( card.locator( '.cr-shelf-tape__runtime' ), `A7 ${ key} has no runtime element or slot` ).toHaveCount( 0 );
		}
		await openArchive( page, 2 );
		await expect( ( await fixtureCard( page, 'V13', 'article.cr-shelf-tape' ) ).locator( '.cr-shelf-tape__runtime' ), 'A7 V13 remote media has no runtime element' ).toHaveCount( 0 );
		await openArchive( page );
		const v01 = await fixtureCard( page, 'V01', 'article.cr-shelf-tape' );
		const image = v01.locator( '.cr-shelf-tape__img' );
		await expect( image, 'A8 V01 has a local picture' ).toHaveAttribute( 'alt', '' );
		expect( await image.evaluate( ( element ) => getComputedStyle( element ).filter ), 'A8 picture has no computed filter' ).toBe( 'none' );
		for ( const key of [ 'V03', 'V06' ] ) {
			await expect( ( await fixtureCard( page, key, 'article.cr-shelf-tape' ) ).locator( 'img' ), `A8 ${ key} has no picture` ).toHaveCount( 0 );
		}
		const v09 = await fixtureCard( page, 'V09', 'article.cr-shelf-tape' );
		const v10 = await fixtureCard( page, 'V10', 'article.cr-shelf-tape' );
		expect( await v09.locator( 'a.u-url' ).innerText(), 'A9 duplicate V09 name includes a hidden date' ).not.toBe( await v10.locator( 'a.u-url' ).innerText() );
		expect( ( await v01.locator( 'a.u-url' ).innerText() ).trim(), 'A9 V01 accessible name equals its title' ).toBe( 'Seed Orchard Walk' );
	} );

	for ( const [ width, columns ] of [ [ 1440, 3 ], [ 800, 2 ], [ 375, 1 ], [ 320, 1 ] ] ) {
		test( `Archive layout — ${ columns } column(s), board rows, DOM and tab order at ${ width }px`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openArchive( page );
			for ( const section of await page.locator( '.cr-video-shelf .pkiw-group' ).all() ) {
				const list = section.locator( '.pkiw-group__items' );
				const tracks = await list.evaluate( ( element ) => getComputedStyle( element ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length );
				expect( tracks, `A4 ${ width}: computed track count` ).toBe( columns );
				const rows = await list.locator( ':scope > li' ).evaluateAll( ( nodes ) => new Set( nodes.map( ( node ) => Math.round( node.getBoundingClientRect().top ) ) ).size );
				expect( rows, `A4 ${ width}: row count equals ceil(items / columns)` ).toBe( Math.ceil( await list.locator( ':scope > li' ).count() / columns ) );
				expect( await list.locator( ':scope > li' ).evaluateAll( ( nodes ) => nodes.every( ( node ) => getComputedStyle( node, '::after' ).content !== 'none' ) ), `A4 ${ width}: each li paints a shelf board` ).toBe( true );
				await expectVisualOrder( list.locator( ':scope > li' ), `A13 ${ width}: visual order follows DOM order` );
			}
			const hrefs = await page.locator( 'article.cr-shelf-tape a.u-url' ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) );
			const tabbed = [];
			await page.evaluate( () => { document.body.tabIndex = -1; document.body.focus(); } );
			for ( let press = 0; press < 100 && tabbed.length < hrefs.length; press++ ) {
				await page.keyboard.press( 'Tab' );
				const active = await page.evaluate( () => document.activeElement?.href || '' );
				if ( hrefs.includes( active ) && ! tabbed.includes( active ) ) {
					tabbed.push( active );
				}
			}
			expect( tabbed, `A13 ${ width}: tab order follows tape DOM order` ).toEqual( hrefs );
			await expectNoHorizontalScroll( page, `A15 ${ width}: no horizontal page scroll` );
			const longTitle = ( await fixtureCard( page, 'V07', 'article.cr-shelf-tape' ) ).locator( '.pk-title' );
			expect( await longTitle.evaluate( ( element ) => element.scrollWidth <= element.clientWidth ), `A18 ${ width}: 90-character title fits and wraps` ).toBe( true );
		} );
	}

	test( 'Archive focus and motion — whole-tape ring survives the injected checker style', async ( { page } ) => {
		await openArchive( page );
		const card = page.locator( 'article.cr-shelf-tape' ).first();
		const link = card.locator( 'a.u-url' );
		await link.evaluate( ( element ) => { element.style.outlineWidth = '2px'; element.style.outlineOffset = '2px'; } );
		await link.focus();
		await settleTransitions( page );
		const focus = await card.evaluate( ( element ) => {
			const own = getComputedStyle( element );
			const linkStyle = getComputedStyle( element.querySelector( 'a.u-url' ) );
			return { width: own.outlineWidth, color: own.outlineColor, shadow: own.boxShadow, linkOutline: linkStyle.outlineStyle, translate: own.translate };
		} );
		expect( focus.width, 'A14 ring is 3px' ).toBe( '3px' );
		expect( focus.color, 'A14 ring is selective yellow' ).toBe( SELECTIVE_YELLOW );
		expect( focus.shadow, 'A14 halo resolves around the tape' ).toMatch( /7px/ );
		expect( focus.linkOutline, 'A14 link has no competing outline' ).toBe( 'none' );
		expect( Math.abs( parseFloat( focus.translate.split( ' ' )[1] || '0' ) ), 'A14 focus lift is no more than 1px' ).toBeLessThanOrEqual( 1 );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		expect( await card.evaluate( ( element ) => getComputedStyle( element ).translate ), 'A14 reduced motion removes lift' ).toBe( 'none' );
	} );

	test( 'Archive pager — shared Pagination nav has terminal links and 44px targets', async ( { page } ) => {
		test.skip( 'archive' !== STATE, 'pager needs the full archive set' );
		await openArchive( page );
		const pager = page.locator( 'nav.wp-block-query-pagination[aria-label="Pagination"]' );
		await expect( pager.locator( '.wp-block-query-pagination-previous' ), 'A11 page 1 has no Previous' ).toHaveCount( 0 );
		await expect( pager.locator( '[aria-current="page"]' ), 'A11 current chip declares aria-current' ).toHaveCount( 1 );
		expect( await pager.locator( 'a, [aria-current="page"]' ).evaluateAll( ( nodes ) => nodes.every( ( node ) => node.getBoundingClientRect().width >= 44 && node.getBoundingClientRect().height >= 44 ) ), 'A11 pager targets are 44 by 44 or larger' ).toBe( true );
		await openArchive( page, 2 );
		await expect( page.locator( 'nav.wp-block-query-pagination .wp-block-query-pagination-next' ), 'A11 final page has no Next' ).toHaveCount( 0 );
	} );

	test( 'Archive one state — one year, one tape, no filler or pager', async ( { page } ) => {
		test.skip( 'one' !== STATE, 'set CR_VIDEO_STATE=one after seeding --state=one' );
		await openArchive( page );
		await expect( page.locator( '.pkiw-group' ), 'A18 one state has one year' ).toHaveCount( 1 );
		await expect( page.locator( 'article.cr-shelf-tape' ), 'A18 one state has one tape' ).toHaveCount( 1 );
		await expect( page.locator( 'nav.wp-block-query-pagination' ), 'A18 one state has no pager' ).toHaveCount( 0 );
	} );

	test( 'Archive 400 percent reflow proxy and third-party request policy', async ( { page } ) => {
		await page.setViewportSize( { width: 320, height: 256 } );
		const requests = [];
		page.on( 'request', ( request ) => requests.push( request.url() ) );
		await openArchive( page );
		await expectNoHorizontalScroll( page, 'A15 1280px at 400 percent reflows without clipping' );
		expect( requests.filter( ( url ) => THIRD_PARTY_MEDIA.test( url ) ), 'A8 and A10 archive makes no third-party media request' ).toEqual( [] );
	} );

	test( 'Archive dark modes, forced colors and contrast', async ( { page } ) => {
		await useStoredTheme( page, 'dark' );
		await openArchive( page );
		let paint = await page.locator( 'article.cr-shelf-tape' ).first().evaluate( ( element ) => ( {
			edge: getComputedStyle( element.querySelector( '.cr-shelf-tape__shell' ) ).borderTopColor,
			label: getComputedStyle( element.querySelector( '.cr-shelf-tape__label' ) ).backgroundColor,
			ink: getComputedStyle( element.querySelector( '.cr-shelf-tape__label' ) ).color,
		} ) );
		expect( paint, 'A16 stored dark mode keeps the approved edge and label colors' ).toEqual( { edge: PERIWINKLE, label: PRINTER_IVORY, ink: RUSSIAN_VIOLET } );
		expect( await axeContrast( page ), 'A16 stored dark archive has zero color-contrast violations' ).toEqual( [] );
		await page.emulateMedia( { colorScheme: 'dark' } );
		await page.evaluate( () => { localStorage.setItem( 'courtneyr-theme', 'system' ); document.documentElement.dataset.theme = 'system'; } );
		paint = await page.locator( 'article.cr-shelf-tape' ).first().evaluate( ( element ) => ( {
			edge: getComputedStyle( element.querySelector( '.cr-shelf-tape__shell' ) ).borderTopColor,
			label: getComputedStyle( element.querySelector( '.cr-shelf-tape__label' ) ).backgroundColor,
			ink: getComputedStyle( element.querySelector( '.cr-shelf-tape__label' ) ).color,
		} ) );
		expect( paint, 'A16 OS dark mode keeps the approved edge and label colors' ).toEqual( { edge: PERIWINKLE, label: PRINTER_IVORY, ink: RUSSIAN_VIOLET } );
		await page.emulateMedia( { forcedColors: 'active', colorScheme: 'light' } );
		const forced = await page.locator( 'article.cr-shelf-tape' ).first().evaluate( ( element ) => ( {
			border: getComputedStyle( element.querySelector( '.cr-shelf-tape__shell' ) ).borderTopWidth,
			reel: getComputedStyle( element.querySelector( '.cr-shelf-tape__reel' ) ).display,
			strip: element.querySelector( '.cr-shelf-tape__strip' ) ? getComputedStyle( element.querySelector( '.cr-shelf-tape__strip' ) ).display : 'none',
			picture: element.querySelector( 'img' ) ? getComputedStyle( element.querySelector( 'img' ) ).display : 'block',
		} ) );
		expect( forced, 'A17 forced colors keeps text and pictures but removes shell art' ).toEqual( { border: '2px', reel: 'none', strip: 'none', picture: 'block' } );
	} );
} );

test.describe( 'single', () => {
	test.skip( ! [ 'archive', 'edge' ].includes( STATE ), 'single checks need archive or edge fixtures' );

	test( 'Single V01 — deck order, one H1, player, prose, comments and shared nav', async ( { page, request } ) => {
		await openFixture( page, request, 'V01' );
		const deck = page.locator( 'div.cr-tape-deck' );
		await expect( deck.locator( ':scope > header.cr-tape-label' ), 'S1 label is the deck first child' ).toHaveCount( 1 );
		await expect( deck.locator( ':scope > div.cr-tape-deck__frame' ), 'S2 frame is the deck second child' ).toHaveCount( 1 );
		await expect( page.locator( 'main h1' ), 'S1 single has one H1' ).toHaveCount( 1 );
		await expect( deck.locator( 'header.cr-tape-label h1' ), 'S1 H1 is inside the label' ).toHaveText( 'Seed Orchard Walk' );
		await expect( deck.locator( '.cr-tape-label__date' ), 'S1 stored date uses the expected machine value' ).toHaveAttribute( 'datetime', '2026-09-18' );
		await expect( deck.locator( '.cr-tape-label__runtime' ), 'S13 runtime uses the expected machine value' ).toHaveAttribute( 'datetime', 'PT2M14S' );
		await expect( deck.locator( '.cr-tape-label__runtime .screen-reader-text' ), 'S13 runtime has human text' ).toContainText( '2 minutes, 14 seconds' );
		await expect( page.locator( 'main img.wp-post-image' ), 'S2 single has no separate featured image' ).toHaveCount( 0 );
		expect( await page.locator( 'main' ).evaluate( ( main ) => {
			const order = [ '.cr-tape-label', '.cr-tape-deck__frame', '.e-content', '.single-post__comments-wrapper', 'nav.cr-post-nav' ].map( ( selector ) => main.querySelector( selector ) );
			return order.every( Boolean ) && order.every( ( node, index ) => index === 0 || Boolean( order[index - 1].compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
		} ), 'S10 DOM order is label, player, prose, comments, nav' ).toBe( true );
		await expect( page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' ), 'S8 shared post navigation remains present' ).toHaveCount( 1 );
		const navText = ( await page.locator( 'nav.cr-post-nav' ).innerText() ).replace( /\s+/g, ' ' );
		expect( navText, 'S8 shared nav keeps the Previous label' ).toMatch( /Previous/ );
		expect( navText, 'S8 shared nav keeps the Next label' ).toMatch( /Next/ );
		const prose = await page.locator( '.e-content' ).evaluate( ( element ) => { const css = getComputedStyle( element ); return { family: css.fontFamily, transform: css.transform }; } );
		expect( prose.family, 'S7 prose uses Barlow' ).toContain( 'Barlow' );
		expect( prose.transform, 'S7 prose stays upright' ).toBe( 'none' );
	} );

	test( 'Single V12 — saved video attributes, tracks and poster survive while autoplay does not', async ( { page, request } ) => {
		await openFixture( page, request, 'V12' );
		const video = page.locator( '.cr-tape-deck__frame video' );
		await expect( video, 'S3 V12 has one video' ).toHaveCount( 1 );
		if ( 'false' === REPLACE ) {
			await expect( video, 'S3 V12 has controls' ).toHaveAttribute( 'controls', '' );
		} else {
			// Able Player removes the native attribute and draws its own control bar.
			await expect( page.locator( '.cr-tape-deck .able-controller' ), 'S3 V12 has the Able control bar' ).toHaveCount( 1 );
		}
		await expect( video, 'S3 V12 preloads metadata' ).toHaveAttribute( 'preload', 'metadata' );
		await expect( video, 'S3 V12 has no autoplay attribute' ).not.toHaveAttribute( 'autoplay', /.*/ );
		await expect( video.locator( 'track' ), 'S3 V12 keeps two authored tracks' ).toHaveCount( 2 );
		expect( ( await video.getAttribute( 'poster' ) || '' ).length, 'S3 V12 keeps the saved poster' ).toBeGreaterThan( 0 );
	} );

	test( 'Single player state — native ring or Able Player controls meet the active configuration', async ( { page, request } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await openFixture( page, request, 'V01' );
		if ( 'false' === REPLACE ) {
			const video = page.locator( '.cr-tape-deck__frame video' );
			await video.focus();
			const focus = await video.evaluate( ( element ) => { const css = getComputedStyle( element ); return { width: css.outlineWidth, color: css.outlineColor, shadow: css.boxShadow }; } );
			expect( focus.width, 'S6 native video ring is 3px' ).toBe( '3px' );
			expect( focus.color, 'S6 native video ring is selective yellow' ).toBe( SELECTIVE_YELLOW );
			expect( focus.shadow, 'S6 native video has a 7px halo' ).toMatch( /7px/ );
		} else {
			const controls = page.locator( '.cr-tape-deck .able-wrapper button:visible, .cr-tape-deck .able-wrapper [role="button"]:visible' );
			skipUnlessRequired( 0 === await controls.count(), 'Able Player replacement is enabled but no controls rendered' );
			for ( const width of [ 1440, 375 ] ) {
				await page.setViewportSize( { width, height: 900 } );
				for ( const control of await controls.all() ) {
					const box = await control.boundingBox();
					expect( box.width, `S6 ${ width}: Able control width is at least 44px` ).toBeGreaterThanOrEqual( 44 );
					expect( box.height, `S6 ${ width}: Able control height is at least 44px` ).toBeGreaterThanOrEqual( 44 );
				}
				await controls.first().focus();
				expect( await controls.first().evaluate( ( element ) => getComputedStyle( element ).outlineWidth ), `S6 ${ width}: Able focus ring is 3px` ).toBe( '3px' );
			}
		}
	} );

	test( 'Single YouTube transcript policy — V05 has the site track and V06 does not', async ( { page, request } ) => {
		test.skip( 'true' !== REPLACE, 'Transcript control is an Able Player replacement check' );
		await openFixture( page, request, 'V05' );
		await expect( page.getByRole( 'button', { name: /transcript/i } ), 'S4 and S5 V05 exposes Transcript' ).toHaveCount( 1 );
		expect( await page.content(), 'S4 V05 uses the privacy-enhanced YouTube host' ).toContain( 'youtube-nocookie' );
		expect( await page.content(), 'S4 V05 does not autoplay' ).not.toMatch( /autoplay(?:\s|=["']?(?!0))/i );
		await expect( page.locator( '.cr-tape-deck > button, .cr-tape-deck > [role="button"], .cr-tape-label button' ), 'S5 theme adds no controls outside the player frame' ).toHaveCount( 0 );
		await openFixture( page, request, 'V06' );
		await expect( page.getByRole( 'button', { name: /transcript/i } ), 'S5 V06 has no Transcript control' ).toHaveCount( 0 );
	} );

	for ( const width of WIDTHS ) {
		test( `Single reflow — player and label fit without overflow at ${ width }px`, async ( { page, request } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openFixture( page, request, 'V01' );
			await expectNoHorizontalScroll( page, `S10 ${ width}: no horizontal page scroll` );
			const frame = page.locator( '.cr-tape-deck__frame' );
			expect( await frame.evaluate( ( element ) => element.scrollWidth <= element.clientWidth ), `S10 ${ width}: frame content stays inside the frame` ).toBe( true );
			expect( await page.locator( '.cr-tape-label__title' ).evaluate( ( element ) => element.scrollWidth <= element.clientWidth ), `S10 ${ width}: label title wraps` ).toBe( true );
		} );
	}

	test( 'Single dark mechanisms, forced colors and contrast', async ( { page, request } ) => {
		await useStoredTheme( page, 'dark' );
		await openFixture( page, request, 'V01' );
		let paint = await page.locator( '.cr-tape-label' ).evaluate( ( element ) => { const css = getComputedStyle( element ); return [ css.backgroundColor, css.color ]; } );
		expect( paint, 'S11 stored dark label stays ivory with violet ink' ).toEqual( [ PRINTER_IVORY, RUSSIAN_VIOLET ] );
		expect( await axeContrast( page ), 'S11 dark single has zero contrast violations' ).toEqual( [] );
		await page.emulateMedia( { colorScheme: 'dark' } );
		await page.evaluate( () => { localStorage.setItem( 'courtneyr-theme', 'system' ); document.documentElement.dataset.theme = 'system'; } );
		paint = await page.locator( '.cr-tape-label' ).evaluate( ( element ) => { const css = getComputedStyle( element ); return [ css.backgroundColor, css.color ]; } );
		expect( paint, 'S11 OS dark label stays ivory with violet ink' ).toEqual( [ PRINTER_IVORY, RUSSIAN_VIOLET ] );
		await page.emulateMedia( { forcedColors: 'active', colorScheme: 'light' } );
		expect( await page.locator( '.cr-tape-label, .cr-tape-deck__frame' ).evaluateAll( ( nodes ) => nodes.every( ( node ) => getComputedStyle( node ).borderTopWidth === '2px' ) ), 'S12 forced colors gives label and frame 2px outlines' ).toBe( true );
		expect( await page.locator( '.cr-tape-label' ).evaluate( ( element ) => getComputedStyle( element, '::before' ).display ), 'S12 decorative strip disappears' ).toBe( 'none' );
	} );

	test( 'Markdown and Simple Location surfaces stay outside the video treatment', async ( { page, request } ) => {
		const v01 = await fixtureLink( request, 'V01' );
		skipUnlessRequired( ! v01, 'no V01 fixture for markdown checks' );
		for ( const path of [ `${ v01 }?format=markdown`, `${ v01.replace( /\/$/, '' ) }.md` ] ) {
			const response = await request.get( path );
			skipUnlessRequired( response.status() === 404, `markdown route is absent: ${ path }` );
			expect( await response.text(), `Markdown ${ path} has no theme deck or semantic wrapper` ).not.toMatch( /cr-tape|u-video|e-content/ );
		}
		for ( const [ key, publicOutput ] of [ [ 'E05', true ], [ 'E06', false ] ] ) {
			const path = await fixtureLink( request, key );
			skipUnlessRequired( ! path, `no ${ key } edge fixture` );
			await page.goto( path, { waitUntil: 'load' } );
			const location = page.locator( '.geo, .p-location, .h-geo, .h-adr, .sloc-location' );
			expect( await location.count() > 0, `Simple Location ${ key} output presence follows its public tier` ).toBe( publicOutput );
		}
		const coordinateLeak = /[>"'\s,]-?0\.5(?:[<"'\s,]|$)/;
		for ( const path of [ ARCHIVE, STREAM, `${ ARCHIVE }feed/`, '/wp-json/wp/v2/posts?slug=seed-242-e05&_fields=content' ] ) {
			const response = await request.get( path );
			if ( response.status() !== 404 ) {
				expect( await response.text(), `Simple Location coordinates stay out of ${ path}` ).not.toMatch( coordinateLeak );
			}
		}
	} );
} );

test.describe( 'Stream', () => {
	test.skip( 'stream' !== STATE, 'Stream checks need the stream fixture set' );

	test( 'Stream tape spines — content, links, semantics, height and request policy', async ( { page } ) => {
		const requests = [];
		page.on( 'request', ( request ) => requests.push( request.url() ) );
		await openStream( page );
		const cards = page.locator( 'article.cr-tape-spine' );
		await expect( cards, 'T1 Stream renders four tape spines' ).toHaveCount( 4 );
		for ( const card of await cards.all() ) {
			await expect( card.locator( ':scope > .cr-tape-spine__strip' ), 'T2 each unprotected spine has one strip' ).toHaveCount( 1 );
			await expect( card.locator( '.pk-kindlabel' ), 'T3 shared Video kind label remains' ).toHaveText( 'Video' );
			const links = await linkReport( card );
			expect( links.count, 'T4 spine has one rendered link' ).toBe( 1 );
			expect( links.stray, 'T4 hidden links stay hidden' ).toBe( 0 );
			await expect( card.locator( 'img, video, iframe, .able-wrapper, .pk-excerpt, .pk-meta' ), 'T3 and T5 spine has no media, excerpt or action' ).toHaveCount( 0 );
			expect( await card.getAttribute( 'class' ), 'T8 spine is an h-entry' ).toContain( 'h-entry' );
			await expect( card.locator( 'a.u-url' ), 'T8 spine has u-url' ).toHaveCount( 1 );
			await expect( card.locator( '.dt-published' ), 'T8 spine has dt-published' ).toHaveCount( 1 );
			const box = await card.boundingBox();
			expect( box.height, 'T9 spine is at least 44px tall' ).toBeGreaterThanOrEqual( 44 );
		}
		const s4 = await fixtureCard( page, 'S4', 'article.cr-tape-spine' );
		expect( ( await s4.boundingBox() ).height, 'T6 60-character title card is at most 12rem at 1440px' ).toBeLessThanOrEqual( 192 );
		const hardShadow = await resolvedToken( page, 'box-shadow', '--cr-shadow-hard' );
		const effects = await s4.evaluate( ( element ) => { const css = getComputedStyle( element ); return { image: css.backgroundImage, filter: css.filter, shadow: css.boxShadow }; } );
		expect( effects, 'T7 spine uses no image, filter or soft shadow' ).toEqual( { image: 'none', filter: 'none', shadow: hardShadow } );
		expect( requests.filter( ( url ) => THIRD_PARTY_MEDIA.test( url ) ), 'T5 Stream makes no third-party media request' ).toEqual( [] );
	} );

	for ( const width of WIDTHS ) {
		test( `Stream reflow — spines fit their collage columns at ${ width }px`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openStream( page );
			for ( const card of await page.locator( 'article.cr-tape-spine' ).all() ) {
				expect( await card.evaluate( ( element ) => element.scrollWidth <= element.clientWidth ), `T6 ${ width}: spine content fits` ).toBe( true );
			}
			await expectNoHorizontalScroll( page, `T6 ${ width}: page has no horizontal scroll` );
		} );
	}

	test( 'Stream rotation layers, focus, dark modes, forced colors and contrast', async ( { page } ) => {
		await useStoredTheme( page, 'dark' );
		await openStream( page );
		const card = page.locator( 'article.cr-tape-spine' ).first();
		const rotations = await card.evaluate( ( element ) => [ element, element.querySelector( '.pk-body' ), element.querySelector( '.cr-tape-spine__strip' ) ].filter( Boolean ).map( ( node ) => getComputedStyle( node ).transform ).filter( ( value ) => value !== 'none' ).length );
		expect( rotations, 'T2 at most two layers rotate' ).toBeLessThanOrEqual( 2 );
		const paint = await card.evaluate( ( element ) => ( {
			edge: getComputedStyle( element ).borderTopColor,
			label: getComputedStyle( element.querySelector( '.pk-body' ) ).backgroundColor,
			ink: getComputedStyle( element.querySelector( '.pk-body' ) ).color,
		} ) );
		expect( paint, 'T10 stored dark mode keeps edge and label colors' ).toEqual( { edge: PERIWINKLE, label: PRINTER_IVORY, ink: RUSSIAN_VIOLET } );
		const link = card.locator( 'a.u-url' );
		await link.focus();
		await settleTransitions( page );
		const focus = await card.evaluate( ( element ) => { const css = getComputedStyle( element ); return [ css.outlineWidth, css.outlineColor, css.boxShadow ]; } );
		expect( focus[0], 'T9 whole-card ring is 3px' ).toBe( '3px' );
		expect( focus[1], 'T9 ring is selective yellow' ).toBe( SELECTIVE_YELLOW );
		expect( focus[2], 'T9 card has a 7px halo' ).toMatch( /7px/ );
		expect( await axeContrast( page ), 'T10 dark Stream has zero contrast violations' ).toEqual( [] );
		await page.emulateMedia( { colorScheme: 'dark' } );
		await page.evaluate( () => { localStorage.setItem( 'courtneyr-theme', 'system' ); document.documentElement.dataset.theme = 'system'; } );
		expect( await page.locator( 'article.cr-tape-spine' ).first().evaluate( ( element ) => getComputedStyle( element ).borderTopColor ), 'T10 OS dark edge is periwinkle' ).toBe( PERIWINKLE );
		await page.emulateMedia( { forcedColors: 'active', colorScheme: 'light' } );
		const forcedCard = page.locator( 'article.cr-tape-spine' ).first();
		expect( await forcedCard.evaluate( ( element ) => getComputedStyle( element ).borderTopWidth ), 'T11 forced colors keeps a 2px outline' ).toBe( '2px' );
		expect( await forcedCard.locator( '.cr-tape-spine__strip' ).evaluate( ( element ) => getComputedStyle( element ).display ), 'T11 strip drops out' ).toBe( 'none' );
	} );
} );

test.describe( 'cross-surface and source policy', () => {
	test( 'Template source — one inherited query keeps the editable 12-line marker and data-driven years', () => {
		const template = readFileSync( join( THEME, 'templates/taxonomy-kind-video.html' ), 'utf8' );
		const pattern = readFileSync( join( THEME, 'patterns/cr-video-shelf-loop.php' ), 'utf8' );
		expect( ( pattern.match( /<!-- wp:query(?![\w-])/g ) || [] ).length, 'A1 and A19 pattern has one core Query block' ).toBe( 1 );
		expect( pattern, 'A1 query inherits the archive query' ).toMatch( /"inherit"\s*:\s*true/ );
		expect( pattern, 'A19 archive marker keeps linesPerPage 12' ).toMatch( /"linesPerPage"\s*:\s*12/ );
		expect( pattern, 'A3 years come from data' ).not.toMatch( /\b(?:19|20)[0-9]{2}\b/ );
		expect( template, 'A1 taxonomy template delegates to the video shelf pattern' ).toContain( 'courtneyr-child/cr-video-shelf-loop' );
	} );

	test( 'Color contrast — light archive, single and Stream have no violations when their fixtures exist', async ( { page, request } ) => {
		for ( const path of [ ARCHIVE, STREAM ] ) {
			const response = await page.goto( path, { waitUntil: 'load' } );
			if ( response?.status() !== 404 && await page.locator( 'article.cr-shelf-tape, article.cr-tape-spine' ).count() ) {
				expect( await axeContrast( page ), `${ path} light mode has zero color-contrast violations` ).toEqual( [] );
			}
		}
		const v01 = await fixtureLink( request, 'V01' );
		if ( v01 ) {
			await page.goto( v01, { waitUntil: 'load' } );
			expect( await axeContrast( page ), 'V01 light mode has zero color-contrast violations' ).toEqual( [] );
		}
	} );

	test( 'Source policy — lane files set no user agent, raw hex color or CSS filter declaration', () => {
		const themeFiles = [
			'inc/video.php',
			'inc/single-video.php',
			'inc/stream-video.php',
			'assets/css/cr-video.css',
			'templates/taxonomy-kind-video.html',
			'patterns/cr-video-shelf-loop.php',
			'tests/php/video-tape-test.php',
			'tests/php/video-template-test.php',
			'tests/php/video-css-test.php',
			'tests/php/video-single-test.php',
			'tests/php/video-stream-test.php',
			'tests/playwright/video-242.spec.mjs',
		].map( ( path ) => join( THEME, path ) );
		const vault = process.env.CR_VIDEO_VAULT_DIR || join( process.env.HOME || '', 'no-icloud/2nd Brain/1. Projects/Post Kinds for IndieWeb/assets/242-videos' );
		const files = [ ...themeFiles ];
		if ( vault && existsSync( vault ) ) {
			files.push( ...filesUnder( vault, ( file ) => /(?:seed|mf2-check)-242\.php$/.test( file ) ) );
		}
		const failures = [];
		for ( const file of files ) {
			const source = readFileSync( file, 'utf8' );
			const policyPatterns = [
				new RegExp( [ 'user', 'Agent' ].join( '' ) + '|' + [ 'CR', 'USER', 'AGENT' ].join( '_' ), 'g' ),
				/#[0-9a-f]{3,8}\b/gi,
				/^\s*filter\s*:/gmi,
			];
			for ( const pattern of policyPatterns ) {
				for ( const match of source.match( pattern ) || [] ) {
					failures.push( `${ relative( THEME, file ) }:${ match }` );
				}
			}
		}
		expect( failures, 'No lane source sets a user agent, raw hex color or filter declaration' ).toEqual( [] );
	} );
} );
