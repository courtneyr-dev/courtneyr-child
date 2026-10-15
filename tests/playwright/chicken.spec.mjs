// `CR_SPEC=chicken.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs`: PKIW issue 250
// chicken pages against the approved decision (decision-2026-10-06.md): the egg-carton archive, the coop
// board single and the Stream egg. Read-only GETs; needs the local stack with the `_cr_seed_250` fixtures
// (vault assets/250-chicken/seed-250.php) and cr-local-stream-chicken.php mapped. The projects are the
// config's 390 and 1280 px, light and dark. Paths and the optional inputs can be set with the CR_CHICKEN_*
// variables below. Written for the W3-T250 lane; it has not been run against a stack yet.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const ARCHIVE = process.env.CR_CHICKEN_ARCHIVE_PATH || '/kind/chicken/';
const ARCHIVE_PAGE_2 = process.env.CR_CHICKEN_ARCHIVE_PAGE_2_PATH || '/kind/chicken/page/2/';
const STREAM = process.env.CR_CHICKEN_STREAM_PATH || '/stream/';
const BLOG = process.env.CR_CHICKEN_BLOG_PATH || '/blog/';
const single = ( label ) => `/?name=seed-250-${ label.toLowerCase() }`;
// {"<post id>": "<token>"} from `wp eval` of \Courtneyr\Child\Chicken\egg_fill(), when the run wants the exact check.
const EGG_FILLS = process.env.CR_CHICKEN_EGG_FILLS ? JSON.parse( readFileSync( process.env.CR_CHICKEN_EGG_FILLS, 'utf8' ) ) : null;
const ALLOWED_FILLS = [ 'russian-violet', 'periwinkle', 'glaucous', 'sky-blue', 'blue-green', 'cerulean', 'prussian-blue', 'selective-yellow', 'ut-orange', 'light-orange' ];

const RUSSIAN_VIOLET = 'rgb(36, 28, 74)';
const PRUSSIAN_BLUE = 'rgb(2, 48, 71)';
const LIGHT_GRAY = 'rgb(235, 235, 235)';
const SELECTIVE_YELLOW = 'rgb(255, 183, 3)';
const PERIWINKLE = 'rgb(188, 181, 227)';
const PRINTER_IVORY = 'rgb(251, 250, 245)';

const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );

// Expected entries per section on the default fixture map, by month label prefix. The seeding month is whatever month S01 is dated.
const AUGUST_POSTS = 14;
const JULY_PAGE_1_POSTS = 6;

/** Spare cups a section needs at a given column count. */
const spareCups = ( count, columns ) => ( count % columns === 0 ? 0 : columns - ( count % columns ) );

async function sections( page ) {
	return page.locator( 'section.pkiw-chicken__month' ).evaluateAll( ( nodes ) => nodes.map( ( section ) => {
		const ol = section.querySelector( 'ol.pkiw-chicken__entries' );
		const style = getComputedStyle( ol );
		const after = getComputedStyle( ol, '::after' );
		return {
			label: section.querySelector( '.pkiw-chicken__month-label' ).textContent.trim(),
			items: [ ...ol.children ].filter( ( child ) => child.tagName === 'LI' ).length,
			children: [ ...ol.children ].map( ( child ) => child.tagName ),
			columns: style.gridTemplateColumns.split( ' ' ).length,
			rows: style.gridTemplateRows.split( ' ' ).length,
			spare: after.display === 'none' ? 0 : Number( after.getPropertyValue( '--cr-spare' ) ),
		};
	} ) );
}

test.describe( 'archive', () => {
	test( 'the header is the approved identity, with the cut-paper Chicken title and no legend (decision 250)', async ( { page } ) => {
		const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no chicken archive at ${ ARCHIVE }` );

		const h1 = page.locator( 'main h1.cr-archive__title' );
		await expect( h1 ).toHaveCount( 1 );
		expect( ( await h1.locator( '.cr-cutout__text' ).textContent() ).trim() ).toBe( 'Chicken' );
		const wrapper = h1.locator( '.cr-cutout__tiles' );
		await expect( wrapper ).toHaveAttribute( 'aria-hidden', 'true' );
		const tiles = await wrapper.locator( '.cr-cutout__tile' ).evaluateAll( ( nodes ) => nodes.map( ( n ) => ( { text: n.textContent, tilt: [ ...n.classList ].find( ( c ) => /^is-r\d$/.test( c ) ) } ) ) );
		expect( tiles.map( ( t ) => t.text ).join( '' ) ).toBe( 'Chicken' );
		expect( tiles.map( ( t ) => t.tilt ) ).toEqual( [ 'is-r3', 'is-r0', 'is-r1', 'is-r3', 'is-r0', 'is-r1', 'is-r3' ] );
		for ( let i = 1; i < tiles.length; i++ ) {
			expect( tiles[ i ].tilt, `tile ${ i } differs from its neighbour` ).not.toBe( tiles[ i - 1 ].tilt );
		}

		expect( ( await page.locator( 'main .cr-archive__description' ).textContent() ).trim().length, 'the stored term description prints' ).toBeGreaterThan( 0 );
		const badge = page.locator( 'main .cr-archive-identity__glyph' );
		await expect( badge ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( badge.locator( 'a' ) ).toHaveCount( 0 );
		expect( await badge.getAttribute( 'tabindex' ) ).toBeNull();
		await expect( page.locator( 'main' ) ).not.toContainText( 'Stream · Kind' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'SAMPLE CONTENT' );
		await expect( page.locator( 'main' ) ).not.toContainText( 'One egg' );
	} );

	test( 'page 1 has 24 eggs in month cartons, page 2 has 16 and repeats July, and the pager follows the last carton', async ( { page } ) => {
		const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no chicken archive at ${ ARCHIVE }` );
		await expect( page.locator( '.pkiw-chicken__entries > li' ) ).toHaveCount( 24 );

		const first = await sections( page );
		expect( first.map( ( s ) => s.items ) ).toEqual( [ expect.any( Number ), AUGUST_POSTS, JULY_PAGE_1_POSTS ] );
		expect( first[ 1 ].label ).toMatch( /^August 2026$/i );
		expect( first[ 2 ].label ).toMatch( /^July 2026$/i );
		expect( first.reduce( ( sum, s ) => sum + s.items, 0 ) ).toBe( 24 );
		// 23:30 on August 31 files under August, not September.
		await expect( page.locator( 'section.pkiw-chicken__month', { hasText: /august 2026/i } ).locator( 'a', { hasText: 'Last light in the run' } ) ).toHaveCount( 1 );

		// Core's pager is the next element sibling of the grouped wrapper, with nothing printed between.
		const sibling = await page.locator( '.pkiw-grouped' ).evaluate( ( node ) => node.nextElementSibling && node.nextElementSibling.matches( 'nav.wp-block-query-pagination' ) );
		expect( sibling ).toBe( true );
		await expect( page.locator( 'nav.wp-block-query-pagination' ) ).toHaveAttribute( 'aria-label', 'Pagination' );
		await expect( page.locator( 'nav.wp-block-query-pagination .wp-block-query-pagination-previous' ) ).toHaveCount( 0 );
		await expect( page.locator( 'nav.wp-block-query-pagination .page-numbers.current' ) ).toHaveAttribute( 'aria-current', 'page' );

		await page.goto( ARCHIVE_PAGE_2, { waitUntil: 'load' } );
		await expect( page.locator( '.pkiw-chicken__entries > li' ) ).toHaveCount( 16 );
		const second = await sections( page );
		expect( second.map( ( s ) => s.label.toLowerCase() ) ).toEqual( [ 'july 2026', 'june 2026', 'may 2026', 'march 2026' ] );
		expect( second.map( ( s ) => s.items ) ).toEqual( [ 1, 12, 1, 2 ] );
		await expect( page.locator( 'nav.wp-block-query-pagination .wp-block-query-pagination-next' ) ).toHaveCount( 0 );
	} );

	for ( const width of [ 1440, 768, 375, 320 ] ) {
		test( `spare cups complete the last row at ${ width }px and never add a row of their own`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			for ( const path of [ ARCHIVE, ARCHIVE_PAGE_2 ] ) {
				const response = await page.goto( path, { waitUntil: 'load' } );
				test.skip( response.status() === 404, `no chicken archive at ${ path }` );
				for ( const section of await sections( page ) ) {
					const expectedColumns = width >= 1024 ? 12 : width >= 640 ? 6 : width >= 352 ? 3 : 2;
					expect( section.columns, `${ section.label }: columns` ).toBe( expectedColumns );
					expect( section.spare, `${ section.label }: spare cups` ).toBe( spareCups( section.items, section.columns ) );
					expect( section.rows, `${ section.label }: rows come from eggs only` ).toBe( Math.ceil( section.items / section.columns ) );
					expect( section.children.every( ( tag ) => tag === 'LI' ), `${ section.label }: every ol child is an li` ).toBe( true );
				}
			}
		} );
	}

	test( 'each egg takes its fill from its post, the same on every load, and no fill matches the carton', async ( { page } ) => {
		const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no chicken archive at ${ ARCHIVE }` );
		const read = () => page.locator( 'a[id^="pkiw-chicken-t-"]' ).evaluateAll( ( nodes ) => nodes.map( ( n ) => ( {
			id: n.id.replace( 'pkiw-chicken-t-', '' ),
			fill: [ ...n.classList ].find( ( c ) => c.startsWith( 'cr-egg--' ) ),
			background: getComputedStyle( n, '::before' ).backgroundColor,
			outline: getComputedStyle( n, '::before' ).borderTopColor,
		} ) ) );
		const first = await read();
		expect( first.length ).toBe( 24 );
		await page.reload( { waitUntil: 'load' } );
		expect( await read() ).toEqual( first );
		for ( const egg of first ) {
			expect( ALLOWED_FILLS, `post ${ egg.id }` ).toContain( egg.fill.replace( 'cr-egg--', '' ) );
			expect( egg.background, `post ${ egg.id } differs from the carton` ).not.toBe( LIGHT_GRAY );
			expect( egg.outline ).toBe( RUSSIAN_VIOLET );
			if ( EGG_FILLS ) {
				expect( egg.fill, `post ${ egg.id } matches egg_fill()` ).toBe( 'cr-egg--' + EGG_FILLS[ egg.id ] );
			}
		}
	} );

	test( 'links are unique by name, start with the title, and tab order follows rows then columns', async ( { page } ) => {
		const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no chicken archive at ${ ARCHIVE }` );
		const links = await page.locator( 'a[id^="pkiw-chicken-t-"]' ).evaluateAll( ( nodes ) => nodes.map( ( n ) => {
			const ids = ( n.getAttribute( 'aria-labelledby' ) || '' ).split( ' ' ).filter( Boolean );
			const name = ids.length ? ids.map( ( id ) => ( document.getElementById( id ) || {} ).textContent || '' ).join( ' ' ) : n.textContent;
			const li = n.closest( 'li' ).getBoundingClientRect();
			const link = n.getBoundingClientRect();
			return { name: name.replace( /\s+/g, ' ' ).trim(), title: n.textContent.trim(), labelledby: ids.length > 0, top: Math.round( li.top ), left: Math.round( li.left ), width: Math.max( li.width, link.width ), height: li.height };
		} ) );
		expect( new Set( links.map( ( l ) => l.name ) ).size, 'no two links share a name' ).toBe( links.length );
		for ( const link of links ) {
			expect( link.name.startsWith( link.title ), `${ link.name } starts with its title` ).toBe( true );
			expect( link.height, 'a 44px target' ).toBeGreaterThanOrEqual( 44 );
		}
		const duplicates = links.filter( ( l ) => links.filter( ( other ) => other.title === l.title ).length > 1 );
		expect( duplicates.every( ( l ) => l.labelledby ), 'a repeated title adds its date through aria-labelledby' ).toBe( true );
		const visual = [ ...links ].sort( ( a, b ) => a.top - b.top || a.left - b.left );
		expect( links.map( ( l ) => `${ l.top }:${ l.left }` ), 'DOM order equals visual order' ).toEqual( visual.map( ( l ) => `${ l.top }:${ l.left }` ) );
	} );

	test( 'the carton, chips, titles and focus use the approved palette', async ( { page }, testInfo ) => {
		const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no chicken archive at ${ ARCHIVE }` );
		const ol = page.locator( 'ol.pkiw-chicken__entries' ).first();
		const css = ( locator, property ) => locator.evaluate( ( node, p ) => getComputedStyle( node )[ p ], property );
		expect( await css( ol, 'backgroundColor' ), 'carton' ).toBe( LIGHT_GRAY );
		expect( await css( ol, 'borderTopColor' ) ).toBe( PRUSSIAN_BLUE );
		const link = page.locator( '.pkiw-chicken__title a' ).first();
		expect( await css( link, 'color' ), isDark( testInfo ) ? 'dark title' : 'light title' ).toBe( RUSSIAN_VIOLET );
		expect( await css( link, 'textDecorationLine' ) ).toBe( 'underline' );
		expect( await css( page.locator( '.pkiw-chicken__date' ).first(), 'fontSize' ), 'text floor' ).not.toMatch( /^(\d|1[01])(\.\d+)?px$/ );
		for ( const selector of [ '.pkiw-chicken__title', '.pkiw-chicken__date', '.pkiw-chicken__month-label' ] ) {
			expect( Number.parseFloat( await css( page.locator( selector ).first(), 'fontSize' ) ), `${ selector } font size` ).toBeGreaterThanOrEqual( 12 );
		}
		const shadow = await css( page.locator( '.pkiw-chicken__entries > li' ).first(), 'boxShadow' );
		expect( shadow === 'none' || / 0px 0px 0px /.test( shadow ) ).toBe( true );
		expect( await css( ol, 'backgroundImage' ), 'no gradient' ).toBe( 'none' );

		await page.keyboard.press( 'Tab' );
		await page.locator( '.pkiw-chicken__title a' ).first().focus();
		const item = page.locator( '.pkiw-chicken__entries > li' ).first();
		expect( await css( item, 'outlineWidth' ) ).toBe( '3px' );
		expect( await css( item, 'outlineColor' ) ).toBe( SELECTIVE_YELLOW );
		expect( await css( item, 'boxShadow' ), 'the halo is Russian violet in both schemes' ).toContain( RUSSIAN_VIOLET );
		const focused = page.locator( '.pkiw-chicken__title a' ).first();
		expect( await css( focused, 'boxShadow' ), 'the link adds no second halo over the item ring' ).toBe( 'none' );
		expect( await css( focused, 'outlineStyle' ) ).toBe( 'none' );
	} );

	test( 'the page holds its width and no cup text is clipped (A04, a 120-character title)', async ( { page } ) => {
		for ( const width of [ 320, 375, 768, 1440 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
			test.skip( response.status() === 404, `no chicken archive at ${ ARCHIVE }` );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), `${ width }px: no horizontal scroll` ).toBe( true );
			const clipped = await page.locator( '.pkiw-chicken__title a' ).evaluateAll( ( nodes ) => nodes.filter( ( n ) => n.scrollWidth > n.closest( 'li' ).clientWidth + 1 ).map( ( n ) => n.textContent ) );
			expect( clipped, `${ width }px: titles wrap inside their column` ).toEqual( [] );
		}
	} );

	test( 'reduced motion removes the title transition and forced colors drop the egg and cup frames', async ( { page } ) => {
		const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no chicken archive at ${ ARCHIVE }` );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		const duration = await page.locator( '.pkiw-chicken__title a' ).first().evaluate( ( n ) => Number.parseFloat( getComputedStyle( n ).transitionDuration ) );
		expect( duration ).toBeLessThanOrEqual( 0.001 );
		await page.emulateMedia( { reducedMotion: 'no-preference', forcedColors: 'active' } );
		const forced = await page.evaluate( () => {
			const ol = document.querySelector( 'ol.pkiw-chicken__entries' );
			const link = document.querySelector( '.pkiw-chicken__title a' );
			return { border: getComputedStyle( ol ).borderTopWidth, egg: getComputedStyle( link, '::before' ).display, cups: getComputedStyle( ol, '::after' ).display };
		} );
		expect( forced ).toEqual( { border: '2px', egg: 'none', cups: 'none' } );
	} );

	test( 'every egg is hidden-author safe: one rendered link, and any tabindex -1 link sits in a hidden subtree (W3-G2)', async ( { page } ) => {
		const response = await page.goto( ARCHIVE, { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no chicken archive at ${ ARCHIVE }` );
		const counts = await page.locator( '.pkiw-chicken__entries > li' ).evaluateAll( ( nodes ) => nodes.map( ( li ) => ( {
			links: [ ...li.querySelectorAll( 'a[href]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) ).length,
			stray: [ ...li.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) ).length,
		} ) ) );
		expect( counts.every( ( c ) => c.links === 1 && c.stray === 0 ) ).toBe( true );
	} );
} );

test.describe( 'single', () => {
	const open = async ( page, label ) => {
		const response = await page.goto( single( label ), { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no fixture ${ label }` );
	};

	test( 'S01: the board holds date, one H1, the picture once and the content, in that order', async ( { page }, testInfo ) => {
		await open( page, 'S01' );
		const board = page.locator( '.cr-coop__board' );
		await expect( board ).toHaveCount( 1 );
		const children = await board.evaluate( ( node ) => [ ...node.children ].map( ( c ) => c.tagName.toLowerCase() + ( c.matches( '.cr-coop__date' ) ? '.date' : '' ) ) );
		expect( children ).toEqual( [ 'div.date', 'h1', 'figure', 'div' ] );
		await expect( board.locator( 'h1' ) ).toHaveText( 'Crossing the garden path' );
		await expect( page.locator( 'main h1' ), 'one H1 on the page' ).toHaveCount( 1 );
		await expect( board.locator( 'img' ), 'the picture prints once' ).toHaveCount( 1 );
		await expect( board.locator( 'figcaption' ) ).toHaveText( 'Pepper on the path' );
		const css = ( selector, property ) => page.locator( selector ).first().evaluate( ( node, p ) => getComputedStyle( node )[ p ], property );
		expect( await css( '.cr-coop__board', 'backgroundColor' ) ).toBe( PRUSSIAN_BLUE );
		expect( await css( '.cr-coop', 'backgroundColor' ), isDark( testInfo ) ? 'dark frame' : 'light frame' ).toBe( LIGHT_GRAY );
		expect( await css( '.cr-coop', 'borderTopColor' ) ).toBe( RUSSIAN_VIOLET );
		expect( await css( '.cr-coop__board figcaption', 'color' ) ).toBe( PERIWINKLE );
		expect( await css( '.cr-coop__title', 'fontStyle' ) ).toBe( 'normal' );
		expect( await css( '.cr-coop__title', 'transform' ), 'upright' ).toBe( 'none' );
		await expect( page.locator( '.single-post__kind, .single-post__category, .single-post__header' ) ).toHaveCount( 0 );
		await expect( page.locator( 'main' ).getByText( 'Crossing the garden path', { exact: true } ), 'the title prints once outside nav' ).toHaveCount( 1 );
		// Below the board, in site order: tags, syndication, comments, then the post navigation.
		const order = await page.evaluate( () => {
			const nodes = [ '.cr-coop', '.single-post__meta', '.single-post__syndication', '.single-post__comments-wrapper', 'nav.cr-post-nav[aria-label="Post navigation"]' ].map( ( s ) => document.querySelector( s ) );
			return nodes.map( ( node, i ) => ( node ? ( i === 0 || ( nodes[ i - 1 ].compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) !== 0 ) : false ) );
		} );
		expect( order, 'each section exists and follows the one before it' ).toEqual( [ true, true, true, true, true ] );
	} );

	test( 'S03 and A03 (untitled): one plain H1 with kind, date and time, and no link or p-name', async ( { page } ) => {
		for ( const label of [ 'S03', 'A03' ] ) {
			await open( page, label );
			const h1 = page.locator( 'main h1' );
			await expect( h1 ).toHaveCount( 1 );
			await expect( h1 ).toHaveText( /^Chicken, [A-Z][a-z]+ \d{1,2}, \d{4} at \d{1,2}:\d{2} ?[ap]m$/i );
			await expect( h1.locator( 'a' ) ).toHaveCount( 0 );
			await expect( h1 ).not.toHaveClass( /p-name/ );
		}
	} );

	test( 'S03: a picture that fails to load leaves no empty frame (cover fallback)', async ( { page } ) => {
		await open( page, 'S03' );
		const figure = page.locator( '.cr-coop__board figure[data-cr-cover-fallback]' );
		await expect( figure ).toHaveCount( 1 );
		await expect( figure ).toHaveClass( /cr-cover--failed/ );
		await expect( figure.locator( 'img' ) ).toHaveCount( 0 );
		await expect( figure ).toBeHidden();
	} );

	test( 'S04 (protected): date, the protected title and the password form, with no picture and no PHP notice', async ( { page } ) => {
		await open( page, 'S04' );
		await expect( page.locator( 'main h1' ) ).toHaveText( 'Protected: Locked coop log' );
		await expect( page.locator( '.cr-coop__board form.post-password-form' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-coop__board img, .cr-coop__board figure' ) ).toHaveCount( 0 );
		await expect( page.locator( 'body' ) ).not.toContainText( /(Notice|Warning|Deprecated):/ );
	} );

	test( 'the picture prints once for each shape: J04, S01 (same attachment), A06 (two), A07 (featured only), A10 (content only), J01 (none)', async ( { page } ) => {
		const expected = { J04: 1, S01: 1, A06: 2, A07: 1, A10: 1, J01: 0 };
		for ( const [ label, images ] of Object.entries( expected ) ) {
			await open( page, label );
			await expect( page.locator( '.cr-coop__board img' ), `${ label } image count` ).toHaveCount( images );
		}
		await open( page, 'A07' );
		expect( await page.locator( '.cr-coop__board' ).evaluate( ( b ) => b.children[ 2 ].tagName ), 'A07: the featured image sits before the text' ).toBe( 'FIGURE' );
	} );

	test( 'the board holds its width at 320, 375, 768 and 1440 (A04)', async ( { page } ) => {
		for ( const width of [ 320, 375, 768, 1440 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await open( page, 'A04' );
			expect( await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth ), `${ width }px` ).toBe( true );
		}
	} );

	test( 'post navigation follows the chicken posts on the Stream surface (set CR_CHICKEN_STATE=full or lone)', async ( { page } ) => {
		const state = process.env.CR_CHICKEN_STATE;
		test.skip( ! [ 'full', 'lone' ].includes( state ), 'set CR_CHICKEN_STATE to the seed state' );
		await open( page, 'S01' );
		const nav = page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		const hrefs = await nav.locator( 'a' ).evaluateAll( ( nodes ) => nodes.map( ( n ) => n.href ) );
		if ( state === 'full' ) {
			expect( hrefs.some( ( href ) => href.includes( 'seed-250-s02' ) ), 'Previous is S02, the next older chicken post on the Stream' ).toBe( true );
			expect( hrefs.length, 'S01 is the newest chicken post, so there is no Next' ).toBe( 1 );
		} else {
			expect( hrefs.some( ( href ) => href.includes( 'seed-250-s05' ) ), 'with no chicken neighbour the nav links Stream posts of any kind' ).toBe( true );
			expect( hrefs.every( ( href ) => ! /seed-250-(s0[1-4]|a|j|f|m|r)/.test( href ) ) ).toBe( true );
		}
	} );

	test( 'headings and cite inside the board stay readable in both schemes', async ( { page } ) => {
		await open( page, 'S01' );
		const read = () => page.locator( '.cr-coop__board .wp-block-post-content' ).first().evaluate( ( content ) => {
			content.insertAdjacentHTML( 'beforeend', '<h2>Heading two</h2><h3>Heading three</h3><h4>Heading four</h4><h5>Heading five</h5><h6>Heading six</h6><blockquote><p>Quoted.</p><cite>Cited</cite></blockquote>' );
			const color = ( selector ) => getComputedStyle( content.querySelector( selector ) ).color;
			return { headings: [ 'h2', 'h3', 'h4', 'h5', 'h6' ].map( color ), cite: color( 'cite' ) };
		} );
		const seen = await read();
		expect( seen.headings, 'h2 to h6 are printer ivory on the Prussian board' ).toEqual( Array( 5 ).fill( PRINTER_IVORY ) );
		expect( seen.cite ).toBe( PERIWINKLE );
	} );

	test( 'a Details block keeps readable text, headings and links on its own surface in both schemes', async ( { page } ) => {
		await open( page, 'S01' );
		const rows = await page.locator( '.cr-coop__board .wp-block-post-content' ).first().evaluate( ( content ) => {
			// The seeded S01 carries a Details block; inject one when the stack still has the older seed.
			let details = content.querySelector( 'details' );
			if ( ! details ) {
				content.insertAdjacentHTML( 'beforeend', '<details open><summary>More</summary><p>Inside text.</p><h3>Inside heading</h3><ul><li>Inside item</li></ul><p><a href="#inside">Inside link</a></p><figure><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="" width="1" height="1"><figcaption>Inside caption</figcaption></figure></details>' );
				details = content.querySelector( 'details' );
			}
			details.open = true;
			const parse = ( value ) => value.match( /[\d.]+/g ).slice( 0, 3 ).map( Number );
			const channel = ( v ) => ( ( v /= 255 ) <= 0.03928 ? v / 12.92 : ( ( v + 0.055 ) / 1.055 ) ** 2.4 );
			const luminance = ( [ r, g, b ] ) => 0.2126 * channel( r ) + 0.7152 * channel( g ) + 0.0722 * channel( b );
			const ratio = ( a, b ) => {
				const [ hi, lo ] = [ luminance( a ), luminance( b ) ].sort( ( x, y ) => y - x );
				return ( hi + 0.05 ) / ( lo + 0.05 );
			};
			const surface = parse( getComputedStyle( details ).backgroundColor );
			return [ 'summary', 'p', 'h3', 'li', 'a', 'figcaption' ].map( ( selector ) => {
				const node = details.querySelector( selector );
				return { selector, ratio: node ? ratio( parse( getComputedStyle( node ).color ), surface ) : null };
			} );
		} );
		for ( const row of rows ) {
			expect( row.ratio, `${ row.selector } in a Details block has a ratio` ).not.toBeNull();
			expect( row.ratio, `${ row.selector } in a Details block reads at 4.5:1 on its surface` ).toBeGreaterThanOrEqual( 4.5 );
		}
	} );

	test( 'dark mode and forced colors keep the board', async ( { page }, testInfo ) => {
		await open( page, 'S01' );
		if ( isDark( testInfo ) ) {
			expect( await page.evaluate( () => getComputedStyle( document.querySelector( '.cr-coop__board' ) ).backgroundColor ) ).toBe( PRUSSIAN_BLUE );
		}
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await page.evaluate( () => ( { board: getComputedStyle( document.querySelector( '.cr-coop__board' ) ).borderTopWidth, chalk: getComputedStyle( document.querySelector( '.cr-coop__title' ), '::after' ).display, photo: document.querySelectorAll( '.cr-coop__board img' ).length } ) );
		expect( forced ).toEqual( { board: '2px', chalk: 'none', photo: 1 } );
	} );
} );

test.describe( 'stream', () => {
	const eggs = async ( page, path = STREAM ) => {
		const response = await page.goto( path, { waitUntil: 'load' } );
		test.skip( response.status() === 404, `no Stream at ${ path }` );
		return page.locator( 'article.cr-egg-card' );
	};
	const egg = ( page, label ) => page.locator( `article.cr-egg-card:has(a[href*="seed-250-${ label.toLowerCase() }"])` );

	test( 'S01 reads image, label, date, title, with the exact title, the year, and one link', async ( { page } ) => {
		await eggs( page );
		const s01 = egg( page, 'S01' );
		test.skip( ( await s01.count() ) === 0, 'S01 is not on this Stream page' );
		const children = await s01.evaluate( ( node ) => [ ...node.children ].map( ( c ) => c.tagName.toLowerCase() + ( c.className ? '.' + c.className.split( ' ' )[ 0 ] : '' ) ) );
		expect( children ).toEqual( [ 'figure.cr-egg-card__photo', 'span.pk-kindlabel', 'p.cr-egg-card__date', 'h2.pk-title', 'span.pk-entry-props' ] );
		await expect( s01.locator( '.pk-title' ) ).toHaveText( 'Crossing the garden path' );
		await expect( s01.locator( 'time.dt-published' ) ).toHaveText( /\d{4}$/ );
		const links = await s01.evaluate( ( node ) => ( {
			shown: [ ...node.querySelectorAll( 'a[href]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) ).length,
			stray: [ ...node.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) ).length,
		} ) );
		expect( links ).toEqual( { shown: 1, stray: 0 } );
		for ( const gone of [ '.pk-excerpt', '.pk-meta', '.pk-badge', '.pk-media__caption', '.pk-media', 'button' ] ) {
			await expect( s01.locator( gone ), gone ).toHaveCount( 0 );
		}
		const box = await s01.boundingBox();
		const neighbour = await page.locator( '.wp-block-post-template > li' ).first().boundingBox();
		expect( Math.abs( box.width - neighbour.width ), 'same column width as a neighbour' ).toBeLessThan( 2 );
	} );

	test( 'S02 has no picture and a 120-character title that stays inside the egg; S04 is a title link only', async ( { page } ) => {
		await eggs( page );
		const s02 = egg( page, 'S02' );
		if ( ( await s02.count() ) > 0 ) {
			await expect( s02.locator( 'img, figure' ) ).toHaveCount( 0 );
			for ( const width of [ 320, 375, 768, 1440 ] ) {
				await page.setViewportSize( { width, height: 900 } );
				const overflow = await s02.evaluate( ( n ) => n.scrollWidth > n.clientWidth + 1 );
				expect( overflow, `${ width }px` ).toBe( false );
			}
		}
		const s04 = egg( page, 'S04' );
		if ( ( await s04.count() ) > 0 ) {
			await expect( s04.locator( 'img, figure, time' ) ).toHaveCount( 0 );
			await expect( s04.locator( 'a[href]' ) ).toHaveCount( 1 );
		}
	} );

	test( 'S03 (untitled) prints a Chicken link with the date for assistive tech and no p-name', async ( { page } ) => {
		await eggs( page );
		const s03 = egg( page, 'S03' );
		test.skip( ( await s03.count() ) === 0, 'S03 is not on this Stream page' );
		await expect( s03.locator( '.pk-title a' ) ).toContainText( 'Chicken' );
		await expect( s03.locator( '.pk-title a .pk-sr-only' ) ).toHaveCount( 1 );
		await expect( s03.locator( '.p-name' ) ).toHaveCount( 0 );
		await expect( s03.locator( 'figure' ) ).toHaveClass( /cr-cover--failed/ );
		await expect( s03.locator( 'figure img' ) ).toHaveCount( 0 );
	} );

	test( 'only the egg rotates: the collage rotation lands on its list position and its children stay upright', async ( { page } ) => {
		await eggs( page );
		const positions = await page.locator( '.wp-block-post-template > li' ).evaluateAll( ( items ) => items.map( ( li, i ) => {
			const card = li.querySelector( 'article.cr-egg-card' );
			return card ? { position: i + 1, transform: getComputedStyle( card ).transform, children: [ ...card.children ].map( ( c ) => getComputedStyle( c ).transform ) } : null;
		} ).filter( Boolean ) );
		test.skip( positions.length === 0, 'no eggs on this Stream page' );
		const rotated = positions.filter( ( p ) => p.position % 4 === 1 || p.position % 4 === 3 );
		expect( rotated.length, 'an egg must sit on a rotated slot, or this test proves nothing; reseed with the Stream set first' ).toBeGreaterThan( 0 );
		for ( const p of positions ) {
			const shouldRotate = p.position % 4 === 1 || p.position % 4 === 3;
			expect( p.transform === 'none', `slot ${ p.position }` ).toBe( ! shouldRotate );
			expect( p.children.every( ( t ) => t === 'none' ) ).toBe( true );
		}
	} );

	test( 'the egg is selective yellow with Russian violet text and no shadow in both schemes; focus draws the ring', async ( { page } ) => {
		const all = await eggs( page );
		test.skip( ( await all.count() ) === 0, 'no eggs on this Stream page' );
		const first = all.first();
		const css = ( property ) => first.evaluate( ( node, p ) => getComputedStyle( node )[ p ], property );
		expect( await css( 'backgroundColor' ) ).toBe( SELECTIVE_YELLOW );
		expect( await css( 'color' ) ).toBe( RUSSIAN_VIOLET );
		const titleLink = first.locator( '.pk-title a' );
		expect( await titleLink.evaluate( ( node ) => getComputedStyle( node ).color ), 'the title link is Russian violet on the yellow egg in both schemes, not the inherited ink' ).toBe( RUSSIAN_VIOLET );
		expect( await first.locator( '.pk-title' ).evaluate( ( node ) => getComputedStyle( node ).color ) ).toBe( RUSSIAN_VIOLET );
		expect( await css( 'boxShadow' ) ).toBe( 'none' );
		expect( await css( 'borderTopColor' ) ).toBe( PRUSSIAN_BLUE );
		await first.locator( '.pk-title a' ).focus();
		expect( await css( 'outlineWidth' ) ).toBe( '3px' );
		expect( await css( 'outlineColor' ) ).toBe( SELECTIVE_YELLOW );
		expect( await titleLink.evaluate( ( node ) => getComputedStyle( node ).boxShadow ), 'the link adds no second halo over the card ring' ).toBe( 'none' );
		await page.emulateMedia( { forcedColors: 'active' } );
		expect( await css( 'borderTopWidth' ) ).toBe( '2px' );
	} );

	test( 'reduced motion: hovering an egg moves nothing', async ( { page } ) => {
		const all = await eggs( page );
		test.skip( ( await all.count() ) === 0, 'no eggs on this Stream page' );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await all.first().hover();
		expect( await all.first().evaluate( ( n ) => getComputedStyle( n ).translate ) ).toMatch( /^(none|0px)/ );
	} );

	test( 'S05 (a note control) is untouched; a standard chicken post shows on /stream and not on /blog, and A12 (4,200 characters) the reverse', async ( { page } ) => {
		await page.goto( STREAM, { waitUntil: 'load' } );
		test.skip( ( await page.locator( 'a[href*="seed-250-s05"]' ).count() ) === 0, 'S05 is not on this Stream page' );
		await expect( page.locator( 'article.cr-egg-card:has(a[href*="seed-250-s05"])' ) ).toHaveCount( 0 );
		if ( process.env.CR_CHICKEN_S05_BASELINE ) {
			const html = await page.locator( 'article:has(a[href*="seed-250-s05"])' ).first().evaluate( ( n ) => n.outerHTML );
			expect( html, 'S05 card equals the one saved from child-w2' ).toBe( readFileSync( process.env.CR_CHICKEN_S05_BASELINE, 'utf8' ).trim() );
		}
		await expect( page.locator( 'a[href*="seed-250-s01"]' ).first() ).toBeVisible();
		const blog = await page.goto( BLOG, { waitUntil: 'load' } );
		test.skip( blog.status() === 404, `no blog at ${ BLOG }` );
		await expect( page.locator( 'a[href*="seed-250-s01"]' ), 'S01 is not on /blog' ).toHaveCount( 0 );
		let onBlog = 0;
		for ( let n = 1; n <= 8 && ! onBlog; n++ ) {
			const response = await page.goto( n === 1 ? BLOG : `${ BLOG }page/${ n }/`, { waitUntil: 'load' } );
			if ( response.status() === 404 ) {
				break;
			}
			onBlog = await page.locator( 'a[href*="seed-250-a12"]' ).count();
		}
		test.skip( ! onBlog, 'A12 is not within the first eight /blog pages on this stack' );
		await page.goto( STREAM, { waitUntil: 'load' } );
		await expect( page.locator( 'a[href*="seed-250-a12"]' ) ).toHaveCount( 0 );
	} );
} );
