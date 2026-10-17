// `CR_SPEC=craft.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs`:
// PKIW issue 249 craft archive, single and Stream checks. Read-only GETs; needs the local stack with
// the `_cr_seed_249` fixtures and cr-local-craft-longform.php mapped. The config supplies 390/1280 px
// light/dark projects. CR_CRAFT_ARCHIVE_PATH defaults to /kind/craft/, CR_CRAFT_STREAM_PATH defaults
// to /stream/, and CR_CRAFT_STATE defaults to full; one, large and empty select their fixture maps.
import { createRequire } from 'node:module';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join, relative } from 'node:path';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const HERE = dirname( fileURLToPath( import.meta.url ) );
const THEME = join( HERE, '../..' );
const ARCHIVE = process.env.CR_CRAFT_ARCHIVE_PATH || '/kind/craft/';
const STREAM = process.env.CR_CRAFT_STREAM_PATH || '/stream/';
const STATE = process.env.CR_CRAFT_STATE || 'full';
// CR_CRAFT_REQUIRE_SEED=1 turns a missing fixture or a 404 into a failure instead of a skip.
const REQUIRE_SEED = '1' === process.env.CR_CRAFT_REQUIRE_SEED;
const WIDTHS = [ 1440, 800, 375, 320 ];

const RUSSIAN_VIOLET = 'rgb(36, 28, 74)';
const SKY_BLUE = 'rgb(142, 202, 230)';
const PRINTER_IVORY = 'rgb(251, 250, 245)';
const LIGHT_ORANGE = 'rgb(254, 226, 195)';
const PERIWINKLE = 'rgb(188, 181, 227)';
const CERULEAN = 'rgb(18, 103, 130)';
const SELECTIVE_YELLOW = 'rgb(255, 183, 3)';
const PRUSSIAN_BLUE = 'rgb(2, 48, 71)';

/** Skip a missing-fixture case, or fail it when CR_CRAFT_REQUIRE_SEED=1. */
function skipUnlessRequired( missing, message ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( message );
	}
	test.skip( missing, message );
}

const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );
const archivePage = ( number ) => number > 1 ? `${ ARCHIVE }page/${ number }/` : ARCHIVE;

/** Find a fixture single without assuming WordPress kept its requested slug unsuffixed. */
async function fixtureLink( request, key ) {
	const response = await request.get( `/wp-json/wp/v2/posts?slug=seed-249-${ key.toLowerCase() }&_fields=link` );
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

/** Open a fixture or skip the served assertion when it isn't seeded. */
async function openFixture( page, request, key ) {
	const path = await fixtureLink( request, key );
	skipUnlessRequired( ! path, `no fixture ${ key }; run seed-249.php first` );
	const response = await page.goto( path, { waitUntil: 'load' } );
	skipUnlessRequired( response?.status() === 404, `fixture ${ key } resolves to a 404` );
	return path;
}

/** Open an archive page or skip when the craft lane isn't served. */
async function openArchive( page, number = 1 ) {
	const response = await page.goto( archivePage( number ), { waitUntil: 'load' } );
	skipUnlessRequired( response?.status() === 404, `no craft archive page ${ number } at ${ archivePage( number ) }` );
	return response;
}

/** W3-G2's rendered-anchor rule. */
async function linkReport( locator ) {
	return locator.evaluate( ( root ) => {
		const hidden = ( element ) => Boolean( element.closest( '[hidden]' ) );
		const visible = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! hidden( link ) );
		const stray = [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( link ) => ! hidden( link ) );
		return { count: visible.length, stray: stray.length, hrefs: visible.map( ( link ) => link.href ) };
	} );
}

/** Resolve a token through the page's actual cascade. */
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

/** Run only axe's color-contrast rule. */
async function axeContrast( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations.map( ( violation ) => ( {
		id: violation.id,
		targets: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
	} ) ) );
}

/** Collect files recursively for the source-policy tests. */
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

/** Wait for running CSS transitions so a focus halo is read at its end value. */
async function settleTransitions( page ) {
	await page.evaluate( () => Promise.all( document.getAnimations().map( ( animation ) => animation.finished.catch( () => null ) ) ) );
}

/** Walk the Stream's `?query-1-page=N` pages until one links the fixture; returns that page number. */
async function openStreamPageWith( page, key ) {
	for ( let number = 1; number <= 12; number++ ) {
		const response = await page.goto( `${ STREAM }?query-1-page=${ number }`, { waitUntil: 'load' } );
		skipUnlessRequired( response?.status() === 404, `no Stream at ${ STREAM }` );
		if ( await page.locator( `a[href*="seed-249-${ key.toLowerCase() }"]` ).count() ) {
			return number;
		}
	}
	throw new Error( `fixture ${ key } is on no Stream page from 1 to 12` );
}

/** Assert that DOM order matches top-to-bottom, then left-to-right visual order. */
async function expectVisualOrder( locator, message ) {
	const boxes = await locator.evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
		const box = node.getBoundingClientRect();
		return { index, top: Math.round( box.top + scrollY ), left: Math.round( box.left ) };
	} ) );
	const visual = [ ...boxes ].sort( ( a, b ) => Math.abs( a.top - b.top ) > 3 ? a.top - b.top : a.left - b.left ).map( ( box ) => box.index );
	expect( visual, message ).toEqual( boxes.map( ( box ) => box.index ) );
}

/** Assert the document doesn't overflow horizontally. */
async function expectNoHorizontalScroll( page, message ) {
	expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), message ).toBe( true );
}

/** Assert forbidden editorial copy isn't present. */
async function expectNoStatusCopy( page, message ) {
	expect( await page.locator( 'html' ).innerText(), message ).not.toMatch( /Finished|In progress|SAMPLE CONTENT/ );
}

test.describe( 'archive', () => {
	test.skip( 'full' !== STATE, 'archive acceptance uses the full fixture map' );

	test( 'Archive page map — 12 then 5, stored grouping, exclusions and terminal 404', async ( { page } ) => {
		await openArchive( page );
		const pageOne = await page.locator( '.cr-pegboard__board > section.pkiw-group' ).evaluateAll( ( sections ) => sections.map( ( section ) => ( {
			label: section.querySelector( 'h2' )?.textContent.trim(),
			count: section.querySelectorAll( ':scope > .pkiw-group__items > li' ).length,
			hrefs: [ ...section.querySelectorAll( ':scope > .pkiw-group__items > li a.u-url' ) ].map( ( link ) => link.getAttribute( 'href' ) ),
		} ) ) );
		expect( pageOne.map( ( section ) => section.label ), 'Archive page map: page 1 H2 texts preserve required labels' ).toEqual( [ '3D printing', 'Bookbinding', 'Knitting', 'Pottery', 'woodworking' ] );
		expect( pageOne.map( ( section ) => section.count ), 'Archive page map: page 1 group counts are 1,2,5,1,3' ).toEqual( [ 1, 2, 5, 1, 3 ] );
		expect( await page.locator( '.cr-pegboard__board li' ).count(), 'Archive page map: page 1 contains 12 items' ).toBe( 12 );
		expect( pageOne[4].label, 'Archive page map: W01 keeps the stored lowercase woodworking heading' ).toBe( 'woodworking' );
		expect( pageOne[2].hrefs.some( ( href ) => href.includes( 'seed-249-k03' ) ), 'Archive page map: lowercase K03 files under Knitting' ).toBe( true );
		expect( pageOne[2].hrefs.some( ( href ) => href.includes( 'seed-249-k04' ) ), 'Archive page map: K04 files under Knitting after trimming storage padding' ).toBe( true );
		expect( pageOne[3].hrefs.some( ( href ) => href.includes( 'seed-249-p01' ) ), 'Archive page map: P01 files under Pottery after trimming storage padding' ).toBe( true );
		expect( await page.locator( 'main' ).innerText(), 'Archive page map: private X01 and draft X02 never appear' ).not.toMatch( /Private Embroidery Hoop|Draft Craft Sketch/ );
		expect( await page.locator( '.pkiw-group__heading', { hasText: 'Embroidery' } ).count(), 'Archive page map: no Embroidery H2 is rendered' ).toBe( 0 );

		await openArchive( page, 2 );
		const pageTwo = await page.locator( '.cr-pegboard__board > section.pkiw-group' ).evaluateAll( ( sections ) => sections.map( ( section ) => ( {
			label: section.querySelector( 'h2' )?.textContent.trim(),
			count: section.querySelectorAll( ':scope > .pkiw-group__items > li' ).length,
		} ) ) );
		expect( pageTwo, 'Archive page map: page 2 repeats woodworking then Craft with counts 2 and 3' ).toEqual( [ { label: 'woodworking', count: 2 }, { label: 'Craft', count: 3 } ] );
		expect( await page.locator( '.cr-pegboard__board li' ).count(), 'Archive page map: page 2 contains 5 items' ).toBe( 5 );
		const canonicalTitles = await page.locator( '.cr-peg-card__title' ).allTextContents();
		await page.goto( `${ ARCHIVE }?paged=2`, { waitUntil: 'load' } );
		expect( await page.locator( '.cr-peg-card__title' ).allTextContents(), 'Archive page map: pretty and query-string page 2 are identical' ).toEqual( canonicalTitles );
		const third = await page.goto( archivePage( 3 ), { waitUntil: 'load' } );
		expect( third?.status(), 'Archive page map: page 3 is a 404' ).toBe( 404 );
	} );

	test( 'Archive protected item — E03 is title-only under Craft', async ( { page } ) => {
		await openArchive( page, 2 );
		const item = page.locator( '.pkiw-group__items > li', { hasText: 'Protected: Locked Quilt Square' } );
		await expect( item, 'Archive page map: protected E03 appears once under Craft' ).toHaveCount( 1 );
		await expect( item.locator( 'a' ), 'Archive page map: protected E03 keeps one title link' ).toHaveCount( 1 );
		await expect( item.locator( 'figure' ), 'Archive page map: protected E03 prints no figure' ).toHaveCount( 0 );
		await expect( item.locator( 'xpath=ancestor::section[1]/h2' ), 'Archive page map: protected E03 belongs to the Craft group' ).toHaveText( 'Craft' );
	} );

	test( 'Archive header — approved identity, description and no stale labels', async ( { page } ) => {
		await openArchive( page );
		const h1 = page.locator( 'main h1.cr-archive__title' );
		await expect( h1, 'Archive header: one H1' ).toHaveCount( 1 );
		await expect( h1.locator( '.cr-cutout__text' ), 'Archive header: visible title is Craft' ).toHaveText( 'Craft' );
		const tiles = h1.locator( '.cr-cutout__tile' );
		await expect( tiles, 'Archive header: five decorative C r a f t tiles' ).toHaveCount( 5 );
		expect( ( await tiles.allTextContents() ).join( '' ), 'Archive header: tile letters spell Craft' ).toBe( 'Craft' );
		for ( const tile of await tiles.all() ) {
			expect( await tile.evaluate( ( element ) => Boolean( element.closest( '[aria-hidden="true"]' ) ) ), 'Archive header: every title tile is aria-hidden' ).toBe( true );
		}
		const tilts = await tiles.evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).transform ) );
		for ( let index = 1; index < tilts.length; index++ ) {
			expect( tilts[index], `Archive header: tile ${ index } tilt differs from its neighbor` ).not.toBe( tilts[index - 1] );
		}
		const badge = page.locator( 'main .cr-archive-identity__glyph' );
		await expect( badge, 'Archive header: badge is aria-hidden' ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( badge.locator( 'a' ), 'Archive header: badge carries no link' ).toHaveCount( 0 );
		expect( ( await page.locator( 'main .cr-archive__description' ).innerText() ).trim().length, 'Archive header: stored description prints' ).toBeGreaterThan( 0 );
		expect( await page.locator( 'main' ).innerText(), 'Archive header: no Stream kind label or sample marker' ).not.toMatch( /Stream · Kind|SAMPLE CONTENT/ );
	} );

	test( 'Archive query and pager — one inherited query with pagination outside the board', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( 'main .wp-block-query' ), 'Archive query: main contains exactly one inherited Query block' ).toHaveCount( 1 );
		expect( await page.locator( '.cr-pegboard' ).evaluate( ( node ) => node.nextElementSibling?.matches( 'nav.wp-block-query-pagination[aria-label="Pagination"]' ) ), 'Archive pager: Pagination nav is the next element sibling after the board' ).toBe( true );
		await expect( page.locator( 'nav.wp-block-query-pagination .wp-block-query-pagination-previous' ), 'Archive pager: page 1 has no Previous link' ).toHaveCount( 0 );
		await expect( page.locator( 'nav.wp-block-query-pagination [aria-current="page"]' ), 'Archive pager: current page declares aria-current page' ).toHaveCount( 1 );
		await openArchive( page, 2 );
		await expect( page.locator( 'nav.wp-block-query-pagination .wp-block-query-pagination-next' ), 'Archive pager: final page has no Next link' ).toHaveCount( 0 );
		await expect( page.locator( 'nav.wp-block-query-pagination [aria-current="page"]' ), 'Archive pager: page 2 declares aria-current page' ).toHaveCount( 1 );
	} );

	test( 'Archive items — one link, microformats, cut content, hit area and image policy', async ( { page } ) => {
		await openArchive( page );
		const items = page.locator( '.cr-pegboard__board li' );
		for ( const item of await items.all() ) {
			const links = await linkReport( item );
			expect( links.count, 'Archive items: every li has exactly one rendered a[href] (W3-G2)' ).toBe( 1 );
			expect( links.stray, 'Archive items: every tabindex -1 anchor stays under hidden markup (W3-G2)' ).toBe( 0 );
			expect( await item.getAttribute( 'class' ), 'Archive items: every li is an h-entry' ).toContain( 'h-entry' );
			expect( await item.locator( '.cr-peg-card__title a' ).getAttribute( 'class' ), 'Archive items: every title link is u-url' ).toContain( 'u-url' );
			expect( await item.locator( 'img' ).count() === 0 || await item.locator( 'img' ).first().getAttribute( 'alt' ) === '', 'Archive items: cover images have empty alt text' ).toBe( true );
			expect( await item.locator( '.pk-stream-date, .pk-excerpt, .pk-meta, .pk-kindlabel, .pk-craft-facts, .pk-badge' ).count(), 'Archive items: PHP removes generic Stream fields' ).toBe( 0 );
			expect( await item.innerText(), 'Archive items: status and Read more text never print' ).not.toMatch( /Finished|In progress|Read more/ );
		}
		const untitled = page.locator( '.cr-peg-card__title a', { hasText: /^Craft,/ } );
		if ( await untitled.count() ) {
			expect( await untitled.first().getAttribute( 'class' ), 'Archive items: E02 synthetic name has no p-name' ).not.toContain( 'p-name' );
		}
		for ( const titled of await page.locator( '.cr-peg-card__title a:not(:has-text("Craft,"))' ).all() ) {
			expect( await titled.getAttribute( 'class' ), 'Archive items: titled archive links carry p-name' ).toContain( 'p-name' );
		}
		const images = page.locator( '.cr-peg-card__cover img' );
		const loading = await images.evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { loading: node.getAttribute( 'loading' ), priority: node.getAttribute( 'fetchpriority' ) } ) ) );
		expect( loading.slice( 0, 3 ), 'Archive items: first three stored images are eager and high priority' ).toEqual( [
			{ loading: 'eager', priority: 'high' },
			{ loading: 'eager', priority: 'high' },
			{ loading: 'eager', priority: 'high' },
		] );
		expect( loading.slice( 3 ).every( ( image ) => image.loading === 'lazy' ), 'Archive items: images after the first three load lazily' ).toBe( true );
		await expect( page.locator( '.cr-peg-card', { hasText: 'Zine Binding Jig' } ).locator( 'figure' ), 'Archive items: B02 has no figure' ).toHaveCount( 0 );

		const firstCard = page.locator( '.cr-peg-card' ).first();
		const href = await firstCard.locator( 'a' ).getAttribute( 'href' );
		const box = await firstCard.boundingBox();
		expect( box, 'Archive items: first card has a measurable hit area' ).not.toBeNull();
		const permalink = new URL( href, page.url() ).href;
		await Promise.all( [ page.waitForURL( permalink, { waitUntil: 'load' } ), page.mouse.click( box.x + 4, box.y + 4 ) ] );
		expect( page.url(), 'Archive items: clicking the card top-left follows its permalink' ).toBe( permalink );
	} );

	test( 'Archive failed cover — W04 becomes a text-only card after the image error', async ( { page } ) => {
		await openArchive( page, 2 );
		const card = page.locator( '.cr-peg-card', { hasText: 'Step Stool' } );
		await expect( card, 'Archive items: W04 missing-file card exists' ).toHaveCount( 1 );
		await expect.poll( () => card.getAttribute( 'class' ), { message: 'Archive items: W04 receives cr-cover--failed from site-ux.js' } ).toContain( 'cr-cover--failed' );
		await expect( card.locator( 'img' ), 'Archive items: W04 failed image is removed' ).toHaveCount( 0 );
		expect( await card.locator( 'figure' ).evaluate( ( element ) => getComputedStyle( element ).display ), 'Archive items: W04 empty figure is hidden' ).toBe( 'none' );
	} );

	for ( const [ width, columns, spans ] of [
		[ 1440, 3, [ 1, 2, 3, 1, 3 ] ],
		[ 800, 2, [ 1, 2, 2, 1, 2 ] ],
		[ 375, 1, [ 1, 1, 1, 1, 1 ] ],
		[ 320, 1, [ 1, 1, 1, 1, 1 ] ],
	] ) {
		test( `Archive layout — ${ columns } tracks, required spans and matching DOM, visual and tab order at ${ width }px`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openArchive( page );
			const board = page.locator( '.cr-pegboard__board' );
			const tracks = await board.evaluate( ( element ) => getComputedStyle( element ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length );
			expect( tracks, `Archive layout ${ width }: board track count` ).toBe( columns );
			const actualSpans = await page.locator( '.cr-pegboard__board > .pkiw-group' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => Number( getComputedStyle( node ).gridColumnStart.match( /span\s+(\d+)/ )?.[1] || 1 ) ) );
			expect( actualSpans, `Archive layout ${ width }: page-1 section spans` ).toEqual( spans );
			await expectVisualOrder( page.locator( '.cr-pegboard__board > .pkiw-group' ), `Archive layout ${ width }: section visual order equals DOM order` );
			for ( const section of await page.locator( '.cr-pegboard__board > .pkiw-group' ).all() ) {
				await expectVisualOrder( section.locator( '.pkiw-group__items > li' ), `Archive layout ${ width }: card visual order equals DOM order` );
			}
			const hrefs = await page.locator( '.cr-peg-card__title a' ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) );
			const tabbed = [];
			await page.evaluate( () => {
				document.body.tabIndex = -1;
				document.body.focus();
			} );
			for ( let press = 0; press < 100 && tabbed.length < hrefs.length; press++ ) {
				await page.keyboard.press( 'Tab' );
				const active = await page.evaluate( () => document.activeElement?.href || '' );
				if ( hrefs.includes( active ) && ! tabbed.includes( active ) ) {
					tabbed.push( active );
				}
			}
			expect( tabbed, `Archive layout ${ width }: Tab order equals card DOM order` ).toEqual( hrefs );
			expect( await page.locator( '.cr-pegboard__board li' ).evaluateAll( ( items ) => items.every( ( item ) => item.getBoundingClientRect().height > 0 ) ), `Archive layout ${ width }: no li has zero height` ).toBe( true );
			await expectNoHorizontalScroll( page, `Archive layout ${ width }: page has no horizontal scroll` );
			const long = page.locator( '.cr-peg-card', { hasText: 'A Seed-Stitch Baby Blanket' } ).locator( '.cr-peg-card__title' );
			expect( await long.evaluate( ( element ) => element.scrollWidth <= element.clientWidth ), `Archive layout ${ width }: K05's 110-character title wraps inside its card` ).toBe( true );
		} );
	}

	test( 'Archive layout — 320 by 256 models a 1280 viewport at 400 percent zoom without overflow', async ( { page } ) => {
		// CSS pixels at a 320px viewport are the reflow equivalent of a 1280px viewport at 400% zoom.
		await page.setViewportSize( { width: 320, height: 256 } );
		await openArchive( page );
		await expectNoHorizontalScroll( page, 'Archive reflow: 1280px at 400% zoom has no horizontal scroll' );
	} );

	test( 'Archive paint — palette papers, hard shadows, upright type and untouched images', async ( { page }, testInfo ) => {
		await openArchive( page );
		const hardShadow = await resolvedToken( page, 'box-shadow', '--cr-shadow-hard' );
		const tape = await resolvedToken( page, 'background-color', '--cr-tape' );
		const tapeInk = await resolvedToken( page, 'color', '--cr-tape-ink' );
		const card = await page.locator( '.cr-peg-card' ).first().evaluate( ( element ) => {
			const style = getComputedStyle( element );
			const title = getComputedStyle( element.querySelector( '.cr-peg-card__title a' ) );
			return { border: style.borderTopWidth, radius: style.borderRadius, transform: style.transform, shadow: style.boxShadow, background: style.backgroundColor, family: title.fontFamily, titleColor: title.color, textTransform: title.textTransform, fontStyle: title.fontStyle };
		} );
		expect( card.border, 'Archive paint: cards have a 2px border' ).toBe( '2px' );
		expect( card.radius, 'Archive paint: cards have sharp corners' ).toBe( '0px' );
		expect( card.transform, "Archive paint: cards aren't rotated at rest" ).toBe( 'none' );
		expect( card.shadow, 'Archive paint: cards use the resolved hard-shadow token' ).toBe( hardShadow );
		expect( card.background, `Archive paint: card stays printer ivory in ${ isDark( testInfo ) ? 'dark' : 'light' } mode` ).toBe( PRINTER_IVORY );
		expect( card.titleColor, 'Archive paint: title link stays cerulean in both schemes' ).toBe( CERULEAN );
		expect( card.family, 'Archive paint: title uses Roboto Slab' ).toContain( 'Roboto Slab' );
		expect( [ card.textTransform, card.fontStyle ], 'Archive paint: title stays stored case and upright' ).toEqual( [ 'none', 'normal' ] );
		expect( await page.locator( '.cr-pegboard__board' ).evaluate( ( element ) => getComputedStyle( element ).backgroundColor ), 'Archive paint: board stays sky blue in both schemes' ).toBe( SKY_BLUE );
		for ( const heading of await page.locator( '.pkiw-group__heading' ).all() ) {
			const paint = await heading.evaluate( ( element ) => { const style = getComputedStyle( element ); return { bg: style.backgroundColor, color: style.color, family: style.fontFamily, textTransform: style.textTransform }; } );
			expect( paint.bg, 'Archive paint: every H2 uses the resolved tape token' ).toBe( tape );
			expect( paint.color, 'Archive paint: every H2 uses the resolved tape-ink token' ).toBe( tapeInk );
			expect( paint.family, 'Archive paint: every H2 uses Roboto Slab' ).toContain( 'Roboto Slab' );
			expect( paint.textTransform, 'Archive paint: every H2 keeps stored case' ).toBe( 'none' );
		}
		for ( const image of await page.locator( '.cr-peg-card img' ).all() ) {
			const paint = await image.evaluate( ( element ) => { const style = getComputedStyle( element ); return [ style.filter, style.mixBlendMode ]; } );
			expect( paint, 'Archive paint: stored images have no filter or blend' ).toEqual( [ 'none', 'normal' ] );
		}
		const effects = await page.locator( '.cr-pegboard__board, .pkiw-group__heading, .cr-peg-card' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => { const style = getComputedStyle( node ); return { filter: style.filter, shadow: style.boxShadow }; } ) );
		expect( effects.every( ( effect ) => effect.filter === 'none' ), 'Archive paint: board, headings and cards have no filter' ).toBe( true );
		expect( effects.every( ( effect ) => effect.shadow === 'none' || [ ...effect.shadow.matchAll( /(-?\d+(?:\.\d+)?)px/g ) ].every( ( match, index ) => index % 4 !== 2 || Number( match[1] ) === 0 ) ), 'Archive paint: board, headings and cards use no blurred shadow' ).toBe( true );
	} );

	test( 'Archive focus and motion — whole-card ring, halo and preference-aware transitions', async ( { page } ) => {
		await openArchive( page );
		const link = page.locator( '.cr-peg-card__title a' ).first();
		await link.focus();
		await settleTransitions( page );
		const focus = await link.evaluate( ( element ) => { const card = getComputedStyle( element.closest( '.cr-peg-card' ) ); const own = getComputedStyle( element ); return { width: card.outlineWidth, color: card.outlineColor, shadow: card.boxShadow, linkOutline: own.outlineStyle }; } );
		expect( focus.width, 'Archive focus: card ring is 3px' ).toBe( '3px' );
		expect( focus.color, 'Archive focus: card ring is selective yellow' ).toBe( SELECTIVE_YELLOW );
		expect( focus.shadow, 'Archive focus: card halo contains Russian violet' ).toContain( RUSSIAN_VIOLET );
		expect( focus.linkOutline, 'Archive focus: link itself has no competing outline' ).toBe( 'none' );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		expect( parseFloat( await page.locator( '.cr-peg-card' ).first().evaluate( ( element ) => getComputedStyle( element ).transitionDuration ) ), 'Archive motion: reduced-motion transition lasts at most 0.001 seconds' ).toBeLessThanOrEqual( 0.001 );
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		expect( await page.locator( '.cr-peg-card' ).first().evaluate( ( element ) => getComputedStyle( element ).transitionProperty ), 'Archive motion: no-preference transitions translate or box-shadow' ).toMatch( /translate|box-shadow/ );
	} );

	test( "Archive forced colors and print — decoration drops out and cards don't split", async ( { page } ) => {
		await openArchive( page );
		await page.emulateMedia( { forcedColors: 'active' } );
		expect( await page.locator( '.cr-pegboard__board' ).evaluate( ( element ) => getComputedStyle( element ).borderTopWidth ), 'Archive forced colors: board has a 2px border' ).toBe( '2px' );
		expect( await page.locator( '.cr-peg-card' ).first().evaluate( ( element ) => getComputedStyle( element ).borderTopWidth ), 'Archive forced colors: cards have a 2px border' ).toBe( '2px' );
		expect( await page.locator( '.cr-pegboard__board' ).evaluate( ( element ) => getComputedStyle( element ).backgroundImage ), 'Archive forced colors: board holes disappear' ).toBe( 'none' );
		expect( await page.locator( '.cr-peg-card' ).first().evaluate( ( element ) => [ getComputedStyle( element, '::before' ).display, getComputedStyle( element, '::after' ).display ] ), 'Archive forced colors: hooks and tape disappear' ).toEqual( [ 'none', 'none' ] );
		expect( await page.locator( '.cr-pegboard' ).evaluate( ( element ) => [ getComputedStyle( element, '::before' ).content, getComputedStyle( element, '::after' ).content ] ), 'Archive forced colors: both tool pseudo-elements have no content' ).toEqual( [ 'none', 'none' ] );
		await page.emulateMedia( { media: 'print', forcedColors: 'none' } );
		expect( await page.locator( '.cr-pegboard__board' ).evaluate( ( element ) => getComputedStyle( element ).backgroundImage ), 'Archive print: board background disappears' ).toBe( 'none' );
		expect( await page.locator( '.cr-peg-card' ).first().evaluate( ( element ) => getComputedStyle( element, '::before' ).display ), 'Archive print: peg hook disappears' ).toBe( 'none' );
		expect( await page.locator( '.cr-peg-card' ).first().evaluate( ( element ) => getComputedStyle( element ).breakInside ), 'Archive print: cards avoid page breaks' ).toBe( 'avoid' );
	} );

	for ( const pageNumber of [ 1, 2 ] ) {
		test( `Archive axe — page ${ pageNumber } has no color-contrast violations`, async ( { page } ) => {
			await openArchive( page, pageNumber );
			expect( await axeContrast( page ), `Archive axe: page ${ pageNumber } has zero color-contrast violations` ).toEqual( [] );
		} );
	}

	test( 'Archive served copy — no status, sample marker or craft-of leaks', async ( { page } ) => {
		for ( const number of [ 1, 2 ] ) {
			await openArchive( page, number );
			await expectNoStatusCopy( page, `Archive served copy: page ${ number } has no Finished, In progress or SAMPLE CONTENT` );
			expect( await page.locator( '.u-craft-of' ).count(), `Archive microformats: page ${ number } has no u-craft-of` ).toBe( 0 );
		}
	} );
} );

test.describe( 'tools and empty state', () => {
	for ( const [ width, layers ] of [ [ 1440, 2 ], [ 800, 1 ], [ 375, 0 ], [ 320, 0 ] ] ) {
		test( `Tools — each gutter paints ${ layers } SVG layer(s) at ${ width }px with no tool DOM`, async ( { page } ) => {
			test.skip( 'empty' === STATE, 'tool layer count needs a non-empty board' );
			await page.setViewportSize( { width, height: 900 } );
			await openArchive( page );
			const backgrounds = await page.locator( '.cr-pegboard' ).evaluate( ( element ) => [ getComputedStyle( element, '::before' ).backgroundImage, getComputedStyle( element, '::after' ).backgroundImage ] );
			for ( const background of backgrounds ) {
				expect( background === 'none' ? 0 : ( background.match( /url\(/g ) || [] ).length, `Tools ${ width}: each gutter has exactly ${ layers } URL layers` ).toBe( layers );
			}
			expect( await page.locator( '.cr-pegboard img[src*="cr-tool-"], .cr-pegboard svg' ).count(), `Tools ${ width}: no img or svg tool element exists inside the pegboard` ).toBe( 0 );
			await page.emulateMedia( { forcedColors: 'active' } );
			expect( await page.locator( '.cr-pegboard' ).evaluate( ( element ) => [ getComputedStyle( element, '::before' ).content, getComputedStyle( element, '::after' ).content ] ), `Tools ${ width}: forced colors removes both gutter layers` ).toEqual( [ 'none', 'none' ] );
		} );
	}

	test( 'Tools — five static SVG assets respond as SVG', async ( { request } ) => {
		for ( const name of [ 'needles', 'saw', 'ruler', 'bone-folder', 'scissors' ] ) {
			const response = await request.get( `/wp-content/themes/courtneyr-child/assets/img/cr-tool-${ name }.svg` );
			expect( response.status(), `Tools assets: cr-tool-${ name }.svg responds 200` ).toBe( 200 );
			expect( response.headers()['content-type'], `Tools assets: cr-tool-${ name }.svg uses image/svg+xml` ).toContain( 'image/svg+xml' );
		}
	} );

	test( 'Empty state — no board, no tools, core no-results copy and no pager', async ( { page } ) => {
		test.skip( 'empty' !== STATE, 'set CR_CRAFT_STATE=empty with every craft fixture trashed' );
		await page.goto( ARCHIVE, { waitUntil: 'load' } );
		await expect( page.locator( '.cr-pegboard__board' ), 'Empty state: no board element renders' ).toHaveCount( 0 );
		expect( await page.locator( '.cr-pegboard' ).evaluate( ( element ) => [ getComputedStyle( element, '::before' ).content, getComputedStyle( element, '::after' ).content ] ), 'Empty state: both tool pseudo-elements have no content' ).toEqual( [ 'none', 'none' ] );
		expect( ( await page.locator( '.wp-block-query-no-results' ).innerText() ).trim().length, 'Empty state: core Query No Results copy prints' ).toBeGreaterThan( 0 );
		await expect( page.locator( 'nav.wp-block-query-pagination' ), 'Empty state: no pager renders' ).toHaveCount( 0 );
	} );

	test( 'One state — one group, one card and a one-card-tall board', async ( { page } ) => {
		test.skip( 'one' !== STATE, 'set CR_CRAFT_STATE=one after seeding state one' );
		await openArchive( page );
		await expect( page.locator( '.pkiw-group' ), 'One state: one section renders' ).toHaveCount( 1 );
		await expect( page.locator( '.cr-peg-card' ), 'One state: one card renders' ).toHaveCount( 1 );
		const heights = await page.locator( '.cr-pegboard__board' ).evaluate( ( board ) => ( { board: board.getBoundingClientRect().height, card: board.querySelector( '.cr-peg-card' ).getBoundingClientRect().height, padding: parseFloat( getComputedStyle( board ).paddingTop ) + parseFloat( getComputedStyle( board ).paddingBottom ) } ) );
		expect( heights.board, 'One state: board is less than two card heights plus padding' ).toBeLessThan( heights.card * 2 + heights.padding );
	} );

	test( 'Large state — core pager shows an ellipsis', async ( { page } ) => {
		test.skip( 'large' !== STATE, 'set CR_CRAFT_STATE=large after seeding state large' );
		await openArchive( page );
		await expect( page.locator( 'nav.wp-block-query-pagination .page-numbers.dots' ), 'Large state: pager shows a page-number ellipsis' ).toHaveCount( 1 );
	} );
} );

test.describe( 'single', () => {
	test.skip( 'full' !== STATE, 'single acceptance uses the full fixture map' );

	test( 'Single K01 — title, facts, date and complete semantic content order', async ( { page, request } ) => {
		await openFixture( page, request, 'K01' );
		expect( await page.locator( 'body' ).getAttribute( 'class' ), 'Single K01: body carries cr-craft-single' ).toContain( 'cr-craft-single' );
		const h1 = page.locator( 'main h1' );
		await expect( h1, 'Single K01: one H1' ).toHaveCount( 1 );
		await expect( h1, 'Single K01: title keeps stored case Wool Scarf' ).toHaveText( 'Wool Scarf' );
		const titleStyle = await h1.evaluate( ( element ) => { const style = getComputedStyle( element ); return { transform: style.textTransform, fontStyle: style.fontStyle, family: style.fontFamily }; } );
		expect( titleStyle.transform, 'Single K01: title has no text transform' ).toBe( 'none' );
		expect( titleStyle.fontStyle, 'Single K01: title is upright' ).toBe( 'normal' );
		expect( titleStyle.family, 'Single K01: title uses Roboto Slab' ).toContain( 'Roboto Slab' );
		await expect( page.locator( '.single-post__featured img' ), 'Single K01: one featured image prints' ).toHaveCount( 1 );
		const facts = page.locator( 'dl.pkiw-craft-facts' );
		await expect( facts.locator( '.pkiw-craft-facts__row' ), 'Single K01: facts has two rows' ).toHaveCount( 2 );
		expect( await facts.locator( 'dt' ).allTextContents(), 'Single K01: fact labels are Craft type and Time spent' ).toEqual( [ 'Craft type', 'Time spent' ] );
		await expect( facts.locator( 'dd.pkiw-craft-facts__type' ), 'Single K01: craft type is Knitting' ).toHaveText( 'Knitting' );
		await expect( facts.locator( 'time[datetime="PT18H"]' ), 'Single K01: duration is machine-readable PT18H and visible 18 hr' ).toHaveText( '18 hr' );
		expect( await page.locator( 'main' ).innerText(), 'Single K01: no Status, Completed or Finished text' ).not.toMatch( /Status|Completed|Finished/ );
		await expect( page.locator( '.single-post__lede-meta time' ), 'Single K01: publish date prints exactly once in the lede' ).toHaveCount( 1 );
		const ordered = [ 'main h1', '.single-post__featured', '.pkiw-craft-facts', '.pkiw-craft-section--materials', '.pkiw-craft-section--tools', '.pkiw-craft-section--steps', '.single-post__comments-wrapper', 'nav.cr-post-nav' ];
		const domOrder = await page.evaluate( ( selectors ) => {
			const nodes = selectors.map( ( selector ) => document.querySelector( selector ) );
			return nodes.every( Boolean ) && nodes.every( ( node, index ) => index === 0 || Boolean( nodes[index - 1].compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
		}, ordered );
		expect( domOrder, 'Single K01: DOM order is H1, cover, facts, Materials, Tools, Steps, comments, nav' ).toBe( true );
		for ( const [ modifier, heading ] of [ [ 'materials', 'Materials' ], [ 'tools', 'Tools' ], [ 'steps', 'Steps' ] ] ) {
			await expect( page.locator( `.pkiw-craft-section--${ modifier } > h2` ), `Single K01: ${ heading } is an H2` ).toHaveText( heading );
		}
	} );

	test( 'Single K01 steps — ordered text, distinct accessible images and number-column clearance', async ( { page, request } ) => {
		await openFixture( page, request, 'K01' );
		const steps = page.locator( 'ol.pkiw-craft-steps > li.pkiw-craft-step' );
		await expect( steps, 'Single K01 steps: four step list items' ).toHaveCount( 4 );
		expect( await steps.locator( ':scope > p' ).allTextContents(), 'Single K01 steps: paragraph order follows block order' ).toEqual( [ 'Cast on', 'Ribbing', 'Main pattern', 'Finish and block' ] );
		const images = await steps.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const image = node.querySelector( 'figure img' );
			return { count: node.querySelectorAll( 'figure img' ).length, alt: image?.getAttribute( 'alt' ), src: image?.currentSrc || image?.src, clearance: image?.getBoundingClientRect().left - node.getBoundingClientRect().left };
		} ) );
		expect( images.every( ( image ) => image.count === 1 && image.alt ), 'Single K01 steps: each step has one image with non-empty alt' ).toBe( true );
		expect( new Set( images.map( ( image ) => image.src ) ).size, 'Single K01 steps: all four image sources are distinct' ).toBe( 4 );
		expect( images.every( ( image ) => image.clearance >= 32 ), 'Single K01 steps: every image starts at least 32px after the li edge' ).toBe( true );
	} );

	test( 'Single missing-data fixtures — K02, B01, E01 and B02 omit only absent fields', async ( { page, request } ) => {
		await openFixture( page, request, 'K02' );
		await expect( page.locator( 'time[datetime="PT2H30M"]' ), 'Single missing data: K02 formats 150 minutes as 2 hr 30 min' ).toHaveText( '2 hr 30 min' );
		await openFixture( page, request, 'B01' );
		await expect( page.locator( '.pkiw-craft-facts__row' ), 'Single missing data: B01 has exactly one facts row' ).toHaveCount( 1 );
		await expect( page.locator( '.pkiw-craft-facts dt' ), 'Single missing data: B01 keeps only Craft type' ).toHaveText( 'Craft type' );
		await expect( page.locator( '.pkiw-craft-facts time[datetime^="PT"]' ), 'Single missing data: B01 has no duration' ).toHaveCount( 0 );
		await openFixture( page, request, 'E01' );
		await expect( page.locator( '.pkiw-craft-facts__row' ), 'Single missing data: E01 has exactly one facts row' ).toHaveCount( 1 );
		await expect( page.locator( '.pkiw-craft-facts dt' ), 'Single missing data: E01 keeps only Time spent' ).toHaveText( 'Time spent' );
		await expect( page.locator( 'time[datetime="PT30M"]' ), 'Single missing data: E01 exposes PT30M' ).toHaveCount( 1 );
		await openFixture( page, request, 'B02' );
		await expect( page.getByRole( 'heading', { name: 'Steps', exact: true } ), 'Single missing data: B02 has no Steps H2' ).toHaveCount( 0 );
		await expect( page.locator( '.single-post__featured' ), 'Single missing data: B02 has no featured image element' ).toHaveCount( 0 );
		await expect( page.locator( '.pkiw-craft-steps' ), 'Single missing data: B02 has no steps list' ).toHaveCount( 0 );
		for ( const key of [ 'K02', 'B01', 'E01', 'B02' ] ) {
			await openFixture( page, request, key );
			expect( await page.locator( 'main' ).innerText(), `Single missing data: ${ key } never prints not set` ).not.toMatch( /not set/i );
		}
	} );

	test( 'Single W01 — repeated featured attachment prints once inside step two', async ( { page, request } ) => {
		await openFixture( page, request, 'W01' );
		await expect( page.locator( '.single-post__featured' ), 'Single featured repeat: W01 drops the template featured figure' ).toHaveCount( 0 );
		const second = page.locator( '.pkiw-craft-step' ).nth( 1 ).locator( 'img' );
		const src = await second.getAttribute( 'src' );
		expect( await page.locator( 'main img' ).evaluateAll( ( images, wanted ) => images.filter( ( image ) => image.getAttribute( 'src' ) === wanted ).length, src ), 'Single featured repeat: W01 featured src appears exactly once' ).toBe( 1 );
		expect( await second.evaluate( ( image ) => Boolean( image.closest( '.pkiw-craft-steps' ) ) ), 'Single featured repeat: W01 featured src remains inside the steps' ).toBe( true );
	} );

	test( 'Single E02 and titled names — synthetic H1 has no p-name; titles have one hidden p-name', async ( { page, request } ) => {
		await openFixture( page, request, 'E02' );
		const h1 = page.locator( 'main h1' );
		await expect( h1, 'Single E02: one synthetic H1' ).toHaveCount( 1 );
		expect( ( await h1.innerText() ).trim(), 'Single E02: synthetic name includes the stored date and time' ).toMatch( /^Craft, [A-Z][a-z]+ \d{1,2}, \d{4} at \d{1,2}:\d{2} ?[ap]m$/ );
		await expect( h1.locator( 'a' ), "Single E02: synthetic H1 isn't a link" ).toHaveCount( 0 );
		expect( await h1.getAttribute( 'class' ) || '', 'Single E02: synthetic H1 has no p-name class' ).not.toContain( 'p-name' );
		await expect( page.locator( '.pkiw-singular-entry .p-name:not(.p-author *)' ), 'Single E02: h-entry contains no entry p-name' ).toHaveCount( 0 );
		await expect( page.locator( '.pkiw-singular-entry a.u-craft-of' ), 'Single E02: Micropub content contains u-craft-of' ).toHaveCount( 1 );
		for ( const key of [ 'K01', 'K02', 'B01', 'E01', 'B02', 'W01', 'L01' ] ) {
			await openFixture( page, request, key );
			const names = page.locator( '.pkiw-singular-entry > .p-name[hidden]' );
			await expect( names, `Single microformats: titled ${ key } has exactly one hidden direct p-name` ).toHaveCount( 1 );
		}
	} );

	test( 'Single C01 — note control gets no craft scope or facts', async ( { page, request } ) => {
		await openFixture( page, request, 'C01' );
		expect( await page.locator( 'body' ).getAttribute( 'class' ) || '', 'Single scope: C01 note has no cr-craft-single body class' ).not.toContain( 'cr-craft-single' );
		await expect( page.locator( '.pkiw-craft-facts' ), 'Single scope: C01 note has no craft facts' ).toHaveCount( 0 );
	} );

	test( 'Single wide grid — three columns place cover, papers, comments and nav in their required areas', async ( { page, request } ) => {
		await page.setViewportSize( { width: 1440, height: 1000 } );
		await openFixture( page, request, 'K01' );
		expect( await page.locator( 'main.single-post' ).evaluate( ( element ) => getComputedStyle( element ).display ), 'Single grid: main.single-post is a grid at 1440' ).toBe( 'grid' );
		expect( await page.locator( '.single-post__content' ).evaluate( ( element ) => getComputedStyle( element ).display ), 'Single grid: content wrapper uses display contents' ).toBe( 'contents' );
		expect( await page.locator( '.single-post__content > .pkiw-singular-entry' ).evaluate( ( element ) => getComputedStyle( element ).display ), 'Single grid: h-entry wrapper uses display contents' ).toBe( 'contents' );
		const boxes = await page.evaluate( () => Object.fromEntries( Object.entries( {
			cover: '.single-post__featured', facts: '.pkiw-craft-facts', header: '.single-post__header', materials: '.pkiw-craft-section--materials', tools: '.pkiw-craft-section--tools', steps: '.pkiw-craft-section--steps', comments: '.single-post__comments-wrapper', nav: 'nav.cr-post-nav',
		} ).map( ( [ key, selector ] ) => { const box = document.querySelector( selector ).getBoundingClientRect(); return [ key, { left: Math.round( box.left ), top: Math.round( box.top ), bottom: Math.round( box.bottom ) } ]; } ) ) );
		expect( boxes.facts.left, 'Single grid: facts share the cover left edge' ).toBe( boxes.cover.left );
		expect( boxes.facts.top, 'Single grid: facts sit below the cover' ).toBeGreaterThanOrEqual( boxes.cover.bottom );
		expect( boxes.materials.left, 'Single grid: Materials share the header left edge' ).toBe( boxes.header.left );
		expect( boxes.tools.left, 'Single grid: Tools share the header left edge' ).toBe( boxes.header.left );
		expect( boxes.header.left, 'Single grid: middle column is right of facts' ).toBeGreaterThan( boxes.facts.left );
		expect( boxes.steps.left, 'Single grid: Steps are right of Materials' ).toBeGreaterThan( boxes.materials.left );
		expect( boxes.steps.top, 'Single grid: Steps start no lower than Materials' ).toBeLessThanOrEqual( boxes.materials.top );
		const contentBottom = Math.max( boxes.cover.bottom, boxes.facts.bottom, boxes.header.bottom, boxes.materials.bottom, boxes.tools.bottom, boxes.steps.bottom );
		expect( boxes.comments.top, 'Single grid: comments are below all three columns' ).toBeGreaterThanOrEqual( contentBottom );
		expect( boxes.nav.top, 'Single grid: post nav follows comments' ).toBeGreaterThanOrEqual( boxes.comments.bottom );
	} );

	for ( const width of [ 800, 375, 320 ] ) {
		test( `Single reflow — one DOM-order column and no horizontal scroll at ${ width }px`, async ( { page, request } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openFixture( page, request, 'K01' );
			const lefts = await page.locator( '.single-post__header, .single-post__featured, .pkiw-craft-facts, .pkiw-craft-section' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => { const box = node.getBoundingClientRect(); return Math.round( box.left + box.width / 2 ); } ) );
			expect( Math.max( ...lefts ) - Math.min( ...lefts ), `Single reflow ${ width }: content uses one column (shared center; alignwide header and photo are wider)` ).toBeLessThanOrEqual( 2 );
			await expectVisualOrder( page.locator( '.single-post__header, .single-post__featured, .pkiw-craft-facts, .pkiw-craft-section' ), `Single reflow ${ width }: visual order follows DOM order` );
			await expectNoHorizontalScroll( page, `Single reflow ${ width }: no horizontal scroll` );
		} );
	}

	test( 'Single paint — palette papers, hard shadows, labels and untouched photos in both schemes', async ( { page, request }, testInfo ) => {
		await openFixture( page, request, 'K01' );
		const hardShadow = await resolvedToken( page, 'box-shadow', '--cr-shadow-hard' );
		const papers = await page.evaluate( () => Object.fromEntries( Object.entries( { facts: '.pkiw-craft-facts', materials: '.pkiw-craft-section--materials', tools: '.pkiw-craft-section--tools', steps: '.pkiw-craft-section--steps' } ).map( ( [ key, selector ] ) => { const style = getComputedStyle( document.querySelector( selector ) ); return [ key, { background: style.backgroundColor, border: style.borderTopWidth, shadow: style.boxShadow, color: style.color } ]; } ) ) );
		expect( papers.facts.background, 'Single paint: facts paper stays ivory' ).toBe( PRINTER_IVORY );
		expect( papers.steps.background, 'Single paint: steps paper stays ivory' ).toBe( PRINTER_IVORY );
		expect( papers.materials.background, 'Single paint: Materials stays sky blue' ).toBe( SKY_BLUE );
		expect( papers.tools.background, 'Single paint: Tools stays light orange' ).toBe( LIGHT_ORANGE );
		for ( const [ name, paper ] of Object.entries( papers ) ) {
			expect( paper.border, `Single paint: ${ name } paper has a 2px border` ).toBe( '2px' );
			expect( paper.shadow, `Single paint: ${ name } paper uses the hard-shadow token` ).toBe( hardShadow );
			expect( paper.color, `Single paint: ${ name } ink stays Russian violet in ${ isDark( testInfo ) ? 'dark' : 'light' } mode` ).toBe( RUSSIAN_VIOLET );
		}
		expect( await page.locator( 'main h1' ).evaluate( ( element ) => getComputedStyle( element ).backgroundColor ), 'Single paint: H1 strip is periwinkle' ).toBe( PERIWINKLE );
		expect( await page.locator( '.single-post__header' ).evaluate( ( element ) => getComputedStyle( element, '::after' ).content ), 'Single paint: generic marker bar is removed' ).toBe( 'none' );
		for ( const image of await page.locator( '.single-post__featured img, .pkiw-craft-step img' ).all() ) {
			expect( await image.evaluate( ( element ) => [ getComputedStyle( element ).filter, getComputedStyle( element ).mixBlendMode ] ), 'Single paint: stored images have no filter or blend' ).toEqual( [ 'none', 'normal' ] );
		}
		const label = await page.locator( '.pkiw-craft-facts dt' ).first().evaluate( ( element ) => { const style = getComputedStyle( element ); return { size: parseFloat( style.fontSize ), family: style.fontFamily }; } );
		expect( label.size, 'Single paint: dt labels are at least 12px' ).toBeGreaterThanOrEqual( 12 );
		expect( label.family, 'Single paint: dt labels use a monospace stack' ).toMatch( /mono|Menlo|Consolas/i );
	} );

	test( 'Single forced colors and axe — outlined papers, no tape, no contrast violations', async ( { page, request } ) => {
		await openFixture( page, request, 'K01' );
		await page.emulateMedia( { forcedColors: 'active' } );
		expect( await page.locator( '.pkiw-craft-facts, .pkiw-craft-section' ).evaluateAll( ( nodes ) => nodes.every( ( node ) => getComputedStyle( node ).borderTopWidth === '2px' ) ), 'Single forced colors: every paper has a 2px border' ).toBe( true );
		expect( await page.locator( '.pkiw-craft-facts' ).evaluate( ( element ) => getComputedStyle( element, '::before' ).display ), 'Single forced colors: facts tape disappears' ).toBe( 'none' );
		await page.emulateMedia( { forcedColors: 'none' } );
		expect( await axeContrast( page ), 'Single axe: K01 has zero color-contrast violations' ).toEqual( [] );
		await openFixture( page, request, 'B02' );
		expect( await axeContrast( page ), 'Single axe: B02 has zero color-contrast violations' ).toEqual( [] );
	} );

	test( 'Single L01 — long exempt craft remains a Stream-surface single with craft-scoped nav', async ( { page, request } ) => {
		await openFixture( page, request, 'L01' );
		await expect( page.locator( '.pkiw-craft-step' ), 'Single L01: long post has six steps' ).toHaveCount( 6 );
		const hrefs = await page.locator( 'nav.cr-post-nav a' ).evaluateAll( ( links ) => links.map( ( link ) => link.getAttribute( 'href' ) ) );
		expect( hrefs.some( ( href ) => href.includes( 'seed-249-k01' ) ), 'Single L01 G8 exemption: nav includes K01 craft neighbor' ).toBe( true );
		expect( hrefs.some( ( href ) => href.includes( 'seed-249-b02' ) ), 'Single L01 G8 exemption: nav includes B02 craft neighbor' ).toBe( true );
	} );

	test( 'Single served copy — every named single omits status and sample copy; only E02 exposes craft-of', async ( { page, request } ) => {
		for ( const key of [ 'K01', 'K02', 'B01', 'E01', 'B02', 'W01', 'L01', 'E02' ] ) {
			await openFixture( page, request, key );
			await expectNoStatusCopy( page, `Single served copy: ${ key } has no Finished, In progress or SAMPLE CONTENT` );
			expect( await page.locator( '.u-craft-of' ).count(), `Single microformats: u-craft-of appears ${ key === 'E02' ? 'once' : 'zero times' } on ${ key }` ).toBe( key === 'E02' ? 1 : 0 );
		}
	} );
} );

test.describe( 'Stream', () => {
	test.skip( 'full' !== STATE, 'Stream acceptance uses the full fixture map' );

	test( 'Stream placement — K01, L01, B02 and E02 render tool tags; L01 stays by G8 exemption', async ( { page } ) => {
		const response = await page.goto( STREAM, { waitUntil: 'load' } );
		skipUnlessRequired( response?.status() === 404, `no Stream at ${ STREAM }` );
		const tags = page.locator( 'article.k-craft.cr-craft-tag' );
		await expect( tags.first(), 'Stream placement: page 1 renders craft tags' ).toBeVisible();
		const hrefs = await tags.locator( 'a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => link.getAttribute( 'href' ) ) );
		for ( const key of [ 'k01', 'l01', 'b02', 'e02' ] ) {
			expect( hrefs.some( ( href ) => href.includes( `seed-249-${ key }` ) ), `Stream placement: ${ key.toUpperCase() } appears on page 1${ key === 'l01' ? ' under Courtney’s G8 exemption' : '' }` ).toBe( true );
		}
		const note = page.locator( 'li', { has: page.locator( 'a[href*="seed-249-c01"]' ) } ).first();
		await expect( note.locator( '.cr-craft-tag' ), "Stream placement: C01 note isn't decorated as a craft tag" ).toHaveCount( 0 );
		// The Stream also carries book and comic cards whose status reads 'Finished'; scope the check to craft cards.
		expect( ( await page.locator( 'article.k-craft' ).allInnerTexts() ).join( '\n' ), 'Stream served copy: craft cards hold no Finished, In progress or SAMPLE CONTENT' ).not.toMatch( /Finished|In progress|SAMPLE CONTENT/ );
		await expect( page.locator( '.u-craft-of' ), 'Stream microformats: no craft tag exposes u-craft-of' ).toHaveCount( 0 );
	} );

	test( 'Stream protected card — E03 gets no tool-tag decoration and keeps one link', async ( { page } ) => {
		await openStreamPageWith( page, 'E03' );
		const protectedCard = page.locator( 'article.k-craft.pk-card--protected', { has: page.locator( 'a[href*="seed-249-e03"]' ) } ).first();
		await expect( protectedCard, 'Stream protected: E03 renders on a Stream page as a protected craft card' ).toHaveCount( 1 );
		await expect( protectedCard, 'Stream protected: E03 carries no cr-craft-tag class' ).not.toHaveClass( /cr-craft-tag/ );
		await expect( protectedCard.locator( '.cr-craft-tag__hole, .cr-craft-tag__tape' ), 'Stream protected: E03 has no hole or tape' ).toHaveCount( 0 );
		await expect( protectedCard.locator( 'a[href]' ), 'Stream protected: E03 keeps one link' ).toHaveCount( 1 );
	} );

	test( 'Stream links and media — one 44px target, contained unfiltered image and no generic furniture', async ( { page } ) => {
		await page.goto( STREAM, { waitUntil: 'load' } );
		for ( const tag of await page.locator( 'article.k-craft.cr-craft-tag' ).all() ) {
			const links = await linkReport( tag );
			expect( links.count, 'Stream links: every craft tag has one rendered a[href]' ).toBe( 1 );
			expect( links.stray, 'Stream links: tabindex -1 anchors stay hidden' ).toBe( 0 );
			await expect( tag.locator( 'button' ), 'Stream links: craft tags contain no button' ).toHaveCount( 0 );
			expect( await tag.evaluate( ( element ) => [ ...element.querySelectorAll( '[tabindex]' ) ].filter( ( node ) => ! node.closest( '[hidden]' ) ).length ), 'Stream links: craft tags contain no visible tabindex node' ).toBe( 0 );
			const box = await tag.boundingBox();
			expect( Math.min( box.width, box.height ), 'Stream links: tag hit target is at least 44 by 44' ).toBeGreaterThanOrEqual( 44 );
			await expect( tag.locator( '.pk-stream-date, .pk-excerpt, .pk-meta, .pk-badge' ), 'Stream content: generic furniture is removed in PHP' ).toHaveCount( 0 );
			for ( const image of await tag.locator( 'img' ).all() ) {
				const data = await image.evaluate( ( element ) => { const style = getComputedStyle( element ); const imageBox = element.getBoundingClientRect(); const tagBox = element.closest( '.cr-craft-tag' ).getBoundingClientRect(); return { alt: element.getAttribute( 'alt' ), height: element.offsetHeight, filter: style.filter, blend: style.mixBlendMode, inside: imageBox.left >= tagBox.left && imageBox.right <= tagBox.right && imageBox.top >= tagBox.top && imageBox.bottom <= tagBox.bottom }; } );
				expect( data.alt, 'Stream media: image alt is empty' ).toBe( '' );
				expect( data.height, 'Stream media: rendered image is at most 160px tall' ).toBeLessThanOrEqual( 160.5 );
				expect( [ data.filter, data.blend ], 'Stream media: image has no filter or blend' ).toEqual( [ 'none', 'normal' ] );
				expect( data.inside, 'Stream media: image stays inside the tag border box' ).toBe( true );
			}
		}
		const k01 = page.locator( 'article.cr-craft-tag', { has: page.locator( 'a[href*="seed-249-k01"]' ) } );
		await expect( k01.locator( 'img' ), 'Stream media: K01 has one image' ).toHaveCount( 1 );
		await expect( k01.locator( 'ul.pk-media__thumbs, .pk-media__caption' ), 'Stream media: K01 has no thumbnail row or caption' ).toHaveCount( 0 );
		expect( await k01.innerText(), 'Stream media: K01 has no +N link' ).not.toMatch( /\+\d+/ );
		for ( const key of [ 'b02', 'e02' ] ) {
			await expect( page.locator( 'article.cr-craft-tag', { has: page.locator( `a[href*="seed-249-${ key }"]` ) } ).locator( 'img' ), `Stream media: ${ key.toUpperCase() } has no image` ).toHaveCount( 0 );
		}
	} );

	test( 'Stream K01 and E02 content — required order, facts and synthetic-title minimum', async ( { page } ) => {
		await page.goto( STREAM, { waitUntil: 'load' } );
		const k01 = page.locator( 'article.cr-craft-tag', { has: page.locator( 'a[href*="seed-249-k01"]' ) } );
		await expect( k01.locator( '.pk-kindlabel' ), 'Stream content: K01 label is Craft' ).toHaveText( 'Craft' );
		expect( await k01.evaluate( ( tag ) => {
			const order = [ '.pk-kindlabel', '.pk-title a', '.pk-craft-facts', '.pk-media' ].map( ( selector ) => tag.querySelector( selector ) );
			return order.every( Boolean ) && order.every( ( node, index ) => index === 0 || Boolean( order[index - 1].compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
		} ), 'Stream content: K01 DOM order is label, title, facts, media' ).toBe( true );
		await expectVisualOrder( k01.locator( '.pk-kindlabel, .pk-title, .pk-craft-facts, .pk-media' ), 'Stream content: K01 visual order follows DOM order' );
		await expect( k01.locator( '.pk-craft-facts' ), 'Stream content: K01 facts include stored type Knitting' ).toContainText( 'Knitting' );
		await expect( k01.locator( '.pk-craft-facts time[datetime="PT18H"]' ), 'Stream content: K01 facts expose PT18H as 18 hr' ).toHaveText( '18 hr' );
		await expect( k01.locator( '.pk-craft-facts .pk-sr-only' ), 'Stream content: K01 time has the hidden Time spent label' ).toHaveText( 'Time spent' );
		const e02 = page.locator( 'article.cr-craft-tag', { has: page.locator( 'a[href*="seed-249-e02"]' ) } );
		await expect( e02.locator( '.pk-kindlabel' ), 'Stream content: E02 keeps the Craft label' ).toHaveText( 'Craft' );
		expect( ( await e02.locator( '.pk-title' ).innerText() ).trim(), 'Stream content: E02 synthetic title starts Craft' ).toMatch( /^Craft/ );
		await expect( e02.locator( '.pk-craft-facts, .pk-media' ), 'Stream content: E02 shows only label and title' ).toHaveCount( 0 );
	} );

	test( 'Stream microformats — article is the single h-entry root with hidden date', async ( { page } ) => {
		await page.goto( STREAM, { waitUntil: 'load' } );
		for ( const tag of await page.locator( 'article.k-craft.cr-craft-tag' ).all() ) {
			expect( await tag.getAttribute( 'class' ), 'Stream microformats: article carries h-entry' ).toContain( 'h-entry' );
			expect( await tag.locator( '.dt-published[hidden], [hidden] .dt-published' ).count(), 'Stream microformats: every tag carries a hidden dt-published' ).toBeGreaterThan( 0 );
			expect( await tag.evaluate( ( article ) => article.closest( 'li' ).classList.contains( 'h-entry' ) ), "Stream microformats: surrounding li isn't an h-entry" ).toBe( false );
			await expect( tag.locator( '.u-craft-of' ), 'Stream microformats: tag has no u-craft-of' ).toHaveCount( 0 );
		}
	} );

	test( 'Stream paint and focus — paper tag shape, token rotation, palette ink and accessible focus', async ( { page }, testInfo ) => {
		await page.goto( STREAM, { waitUntil: 'load' } );
		const tag = page.locator( 'article.k-craft.cr-craft-tag' ).first();
		const hardShadow = await resolvedToken( page, 'box-shadow', '--cr-shadow-hard' );
		const surface = await resolvedToken( page, 'background-color', '--cr-surface' );
		const allowedTapeRotations = await Promise.all( [ '--cr-rotate-1', '--cr-rotate-2', '--cr-rotate-neg', '--cr-rotate-neg2' ].map( ( token ) => resolvedToken( page, 'rotate', token ) ) );
		const paint = await tag.evaluate( ( element ) => { const style = getComputedStyle( element ); const title = getComputedStyle( element.querySelector( '.pk-title a' ) ); return { border: style.borderTopWidth, shadow: style.boxShadow, bg: style.backgroundColor, radius: style.borderRadius, transform: style.transform, color: style.color, titleColor: title.color, titleFamily: title.fontFamily, titleStyle: title.fontStyle, titleTransform: title.textTransform }; } );
		expect( paint.border, 'Stream shape: tag border is 2px' ).toBe( '2px' );
		expect( paint.shadow, 'Stream shape: tag uses the resolved hard shadow' ).toBe( hardShadow );
		expect( [ LIGHT_ORANGE, PRINTER_IVORY ], 'Stream shape: tag paper is light orange or ivory' ).toContain( paint.bg );
		expect( paint.radius, 'Stream shape: tag has sharp corners' ).toBe( '0px' );
		const matrix = paint.transform.match( /matrix\(([^)]+)\)/ );
		const values = matrix ? matrix[1].split( ',' ).map( Number ) : [];
		const angle = paint.transform === 'none' ? 0 : Math.round( Math.atan2( values[1], values[0] ) * 1800 / Math.PI ) / 10;
		expect( [ 0, -1.2, 1.5, -2.5, 2.8 ], 'Stream shape: tag transform comes only from the collage rule' ).toContain( angle );
		expect( paint.color, `Stream paint: tag ink stays Russian violet in ${ isDark( testInfo ) ? 'dark' : 'light' } mode` ).toBe( RUSSIAN_VIOLET );
		expect( paint.titleColor, 'Stream paint: title link stays cerulean' ).toBe( CERULEAN );
		expect( paint.titleFamily, 'Stream paint: title uses Roboto Slab' ).toContain( 'Roboto Slab' );
		expect( [ paint.titleStyle, paint.titleTransform ], 'Stream paint: title stays upright and stored case' ).toEqual( [ 'normal', 'none' ] );
		await expect( tag.locator( '.cr-craft-tag__hole' ), 'Stream shape: one aria-hidden hang hole' ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( await tag.locator( '.cr-craft-tag__hole' ).evaluate( ( element ) => getComputedStyle( element ).backgroundColor ), 'Stream shape: hang hole resolves the current surface token' ).toBe( surface );
		await expect( tag.locator( '.cr-craft-tag__tape' ), 'Stream shape: at most one tape piece' ).toHaveCount( 1 );
		expect( allowedTapeRotations, 'Stream shape: tape rotate uses one of the four token angles' ).toContain( await tag.locator( '.cr-craft-tag__tape' ).evaluate( ( element ) => getComputedStyle( element ).rotate ) );
		const link = tag.locator( '.pk-title a' );
		await link.focus();
		await settleTransitions( page );
		const focus = await tag.evaluate( ( element ) => { const style = getComputedStyle( element ); return { width: style.outlineWidth, color: style.outlineColor, shadow: style.boxShadow }; } );
		expect( focus.width, 'Stream focus: whole tag ring is 3px' ).toBe( '3px' );
		expect( focus.color, 'Stream focus: tag ring is selective yellow' ).toBe( SELECTIVE_YELLOW );
		expect( focus.shadow, 'Stream focus: tag halo contains Russian violet' ).toContain( RUSSIAN_VIOLET );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		expect( parseFloat( await tag.evaluate( ( element ) => getComputedStyle( element ).transitionDuration ) ), 'Stream motion: reduced-motion transition lasts at most 0.001 seconds' ).toBeLessThanOrEqual( 0.001 );
		await page.emulateMedia( { forcedColors: 'active' } );
		expect( await tag.evaluate( ( element ) => getComputedStyle( element ).borderTopWidth ), 'Stream forced colors: tag has a 2px border' ).toBe( '2px' );
		expect( await tag.locator( '.cr-craft-tag__hole' ).evaluate( ( element ) => getComputedStyle( element ).display ), 'Stream forced colors: hang hole disappears' ).toBe( 'none' );
		expect( await tag.locator( '.cr-craft-tag__tape' ).evaluate( ( element ) => getComputedStyle( element ).display ), 'Stream forced colors: tape disappears' ).toBe( 'none' );
	} );

	for ( const width of WIDTHS ) {
		test( `Stream reflow — column-width tags, wrapped titles and no overflow at ${ width }px`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await page.goto( STREAM, { waitUntil: 'load' } );
			for ( const tag of await page.locator( 'article.k-craft.cr-craft-tag' ).all() ) {
				const ratio = await tag.evaluate( ( element ) => { const item = element.closest( 'li' ); const style = getComputedStyle( item ); return element.offsetWidth / ( item.clientWidth - parseFloat( style.paddingLeft ) - parseFloat( style.paddingRight ) ); } );
				expect( ratio, `Stream reflow ${ width }: tag fills at least 95 percent of its li content box` ).toBeGreaterThanOrEqual( 0.95 );
			}
			const measureTitles = () => page.locator( 'article.cr-craft-tag .pk-title' ).evaluateAll( ( nodes ) => nodes.map( ( element ) => { const style = getComputedStyle( element ); return { fits: element.scrollWidth <= element.clientWidth, lines: element.scrollHeight / parseFloat( style.lineHeight ) }; } ) );
			const titles = await measureTitles();
			// K05's long title sits on a later Stream page, so measure that page too.
			await openStreamPageWith( page, 'K05' );
			titles.push( ...await measureTitles() );
			expect( titles.every( ( title ) => title.fits ), `Stream reflow ${ width }: every title wraps without horizontal overflow` ).toBe( true );
			if ( width <= 375 ) {
				expect( titles.some( ( title ) => title.lines > 1.5 ), `Stream reflow ${ width }: at least one long title visibly wraps to multiple lines` ).toBe( true );
			}
			await expectNoHorizontalScroll( page, `Stream reflow ${ width }: no horizontal scroll` );
		} );
	}

	test( 'Stream axe — page has no color-contrast violations', async ( { page } ) => {
		await page.goto( STREAM, { waitUntil: 'load' } );
		expect( await axeContrast( page ), 'Stream axe: zero color-contrast violations' ).toEqual( [] );
	} );
} );

test.describe( 'source greps', () => {
	test( 'Source policy — no craft-type names are hard-coded in theme implementation directories', () => {
		const roots = [ 'inc', 'patterns', 'templates', 'assets' ].map( ( root ) => join( THEME, root ) );
		const files = roots.flatMap( ( root ) => filesUnder( root, ( file ) => /\.(?:php|html|css|js|svg)$/i.test( file ) ) );
		const matches = files.flatMap( ( file ) => {
			const found = readFileSync( file, 'utf8' ).match( /knitting|woodworking|bookbinding|pottery/gi ) || [];
			return found.map( ( value ) => `${ relative( THEME, file ) }:${ value }` );
		} );
		expect( matches, 'Source greps decision 59: no craft-type name appears in inc, patterns, templates or assets' ).toEqual( [] );
	} );

	test( 'Source policy — craft CSS has only tokens, hard-stop hole patterns and no prohibited effects', () => {
		const css = readFileSync( join( THEME, 'assets/css/cr-craft.css' ), 'utf8' );
		expect( css.match( /#[0-9a-f]{3,8}\b/gi ) || [], 'Source greps CSS: cr-craft.css contains no raw hex color' ).toEqual( [] );
		expect( css, 'Source greps CSS: no backdrop-filter' ).not.toMatch( /backdrop-filter/i );
		expect( css, 'Source greps CSS: no blur function' ).not.toMatch( /\bblur\s*\(/i );
		expect( css, 'Source greps CSS: no drop-shadow function' ).not.toMatch( /drop-shadow\s*\(/i );
		expect( css, 'Source greps CSS: no linear gradient' ).not.toMatch( /linear-gradient/i );
		expect( css, 'Source greps CSS: no conic gradient' ).not.toMatch( /conic-gradient/i );
		const filters = [ ...css.matchAll( /^\s*filter\s*:\s*([^;]+);/gmi ) ].map( ( match ) => match[1].trim() );
		expect( filters.every( ( value ) => value === 'none' ), 'Source greps CSS: every filter declaration is filter none' ).toBe( true );
		const rotations = [ ...css.matchAll( /^\s*rotate\s*:\s*([^;]+);/gmi ) ].map( ( match ) => match[1].trim() );
		expect( rotations.every( ( value ) => /var\(\s*--cr-rotate-(?:1|2|neg|neg2)\s*\)/.test( value ) ), 'Source greps CSS: every rotate value uses a permitted rotation token' ).toBe( true );
		expect( ( css.match( /radial-gradient/g ) || [] ).length, 'Source greps CSS: exactly two hard-stop radial gradients draw board and paper holes' ).toBe( 2 );
	} );

	test( 'Source policy — tool SVGs contain no text and only raw-palette colors', () => {
		const tokens = readFileSync( join( THEME, 'assets/css/tokens.css' ), 'utf8' );
		const palette = tokens.match( /1\. RAW PALETTE[\s\S]*?:root\s*\{([\s\S]*?)\n\}/ )?.[1] || '';
		const allowed = new Set( ( palette.match( /#[0-9a-f]{6}\b/gi ) || [] ).map( ( value ) => value.toLowerCase() ) );
		expect( allowed.has( '#1a1714' ) && allowed.has( '#251f1c' ), 'Source greps SVG: parsed raw palette includes both dark surfaces' ).toBe( true );
		for ( const name of [ 'needles', 'saw', 'ruler', 'bone-folder', 'scissors' ] ) {
			const svg = readFileSync( join( THEME, `assets/img/cr-tool-${ name }.svg` ), 'utf8' );
			expect( svg, `Source greps SVG: ${ name } contains no text element` ).not.toMatch( /<text\b/i );
			for ( const color of svg.match( /#[0-9a-f]{6}\b/gi ) || [] ) {
				expect( allowed.has( color.toLowerCase() ), `Source greps SVG: ${ name } color ${ color } belongs to the raw palette` ).toBe( true );
			}
		}
	} );

	test( 'Source policy — print sheet still covers every craft selector used by this lane', () => {
		const print = readFileSync( join( THEME, 'assets/css/cr-print.css' ), 'utf8' );
		for ( const selector of [ '.cr-pegboard__board', '.cr-pegboard::before', '.cr-pegboard::after', '.cr-peg-card::before', '.cr-peg-card', '.pkiw-craft-step' ] ) {
			expect( print, `Source greps print: cr-print.css retains ${ selector }` ).toContain( selector );
		}
	} );
} );
