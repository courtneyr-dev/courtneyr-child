// `CR_SPEC=presentation-262.spec.mjs`: issue 262 presentation surfaces.
// Read-only GETs; seed with the vault's assets/262-presentations/seed-262.php.
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_PRESENTATION_ARCHIVE_PATH || '/kind/presentation/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_PRESENTATION_STREAM_PATH || '/stream/';
const SET = process.env.CR_PRESENTATION_SET || 'archive';
const REQUIRE_SEED = '1' === process.env.CR_PRESENTATION_REQUIRE_SEED;
const WIDTHS = [ 1440, 800, 375, 320 ];

const slugOf = ( key ) => `seed-262-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const archivePath = ( page = 1 ) => 1 === page ? ARCHIVE : `${ ARCHIVE }page/${ page }/`;

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_PRESENTATION_REQUIRE_SEED=1: ${ reason }` );
	}
	test.skip( missing, reason );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 400 ? 900 : 1100 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function openArchive( page, number = 1, width = 1440 ) {
	const response = await open( page, archivePath( number ), width );
	skipUnlessSeeded( 404 === response?.status(), `no archive page ${ number }` );
	skipUnlessSeeded( 0 === await page.locator( '.pkiw-presentation-badge' ).count(), 'presentation fixtures are not seeded' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( '.pkiw-presentation-record' ).count(), `no single fixture ${ key }` );
	return page.locator( '.pkiw-presentation-record' ).first();
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function axeContrast( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations );
}

async function resolvedToken( locator, token ) {
	return locator.evaluate( ( node, property ) => {
		const probe = node.ownerDocument.createElement( 'span' );
		probe.style.color = `var(${ property })`;
		node.append( probe );
		const value = getComputedStyle( probe ).color;
		probe.remove();
		return value;
	}, token );
}

const badges = ( page ) => page.locator( '.pkiw-presentation-badge' );
const fixtureBadge = ( page, key ) => badges( page ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

test.describe( 'presentation archive', () => {
	test.skip( 'archive' !== SET, 'archive fixture set' );

	test( 'archive identity, headings and fixture visibility follow the contract', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'Presentation' );
		const tiles = page.locator( 'main h1 .cr-cutout__tiles[aria-hidden="true"] .cr-cutout__tile' );
		await expect( tiles ).toHaveCount( 12 );
		const rotations = await tiles.evaluateAll( ( nodes ) => nodes.map( ( node ) => [ ...node.classList ].find( ( token ) => /^is-r\d+$/.test( token ) ) ) );
		expect( rotations.every( ( token, index ) => 0 === index || token !== rotations[ index - 1 ] ) ).toBe( true );
		await expect( page.locator( '.pkiw-presentation-wall__year-label' ) ).toHaveText( [ '2026', '2025', '2024' ] );
		await expect( page.locator( 'main h2' ) ).toHaveCount( 3 );
		await expect( page.locator( 'main h3' ) ).toHaveCount( await badges( page ).count() );
		for ( const key of [ 'C01', 'C02', 'C03', 'C05' ] ) {
			await expect( fixtureBadge( page, key ) ).toHaveCount( 0 );
		}
		await expect( page.locator( '.cr-badge-wall + .wp-block-query-pagination' ) ).toHaveCount( 1 );
	} );

	test( 'badge columns preserve DOM order without horizontal overflow', async ( { page } ) => {
		const expectedColumns = new Map( [ [ 1440, 3 ], [ 800, 2 ], [ 375, 1 ], [ 320, 1 ] ] );
		for ( const width of WIDTHS ) {
			await openArchive( page, 1, width );
			const firstList = page.locator( '.pkiw-presentation-wall__badges' ).first();
			const columns = await firstList.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length );
			expect( columns, `${ width }px columns` ).toBe( expectedColumns.get( width ) );
			const order = await badges( page ).evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
				const rect = node.getBoundingClientRect();
				return { index, top: Math.round( rect.top ), left: Math.round( rect.left ) };
			} ) );
			const visual = [ ...order ].sort( ( a, b ) => 2 < Math.abs( a.top - b.top ) ? a.top - b.top : a.left - b.left ).map( ( item ) => item.index );
			expect( visual ).toEqual( order.map( ( item ) => item.index ) );
			await assertNoOverflow( page, `${ width }px archive` );
		}
	} );

	test( 'badges expose one destination, token paint and no forbidden content', async ( { page } ) => {
		await openArchive( page );
		for ( const badge of await badges( page ).all() ) {
			await expect( badge.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( badge.locator( 'a[href], button, input, select, textarea, [tabindex]' ) ).toHaveCount( 1 );
			await expect( badge ).toHaveCSS( 'transform', 'none' );
		}
		const html = await page.locator( 'main' ).innerHTML();
		for ( const banned of [ '<iframe', 'Slides', 'Recording', 'Sentinel Hall', 'sentinel-slides.example', 'Sample content' ] ) {
			expect( html ).not.toContain( banned );
		}

		const typeTokens = new Map( [
			[ 'talk', '--cr-cerulean' ],
			[ 'keynote', '--cr-russian-violet' ],
			[ 'workshop', '--cr-blue-green' ],
			[ 'panel', '--cr-glaucous' ],
			[ 'lightning-talk', '--cr-selective-yellow' ],
			[ 'meetup', '--cr-ut-orange' ],
			[ 'podcast-guest', '--cr-coral' ],
		] );
		for ( const [ type, token ] of typeTokens ) {
			const typed = page.locator( `.pkiw-presentation-badge[data-pkiw-event-type="${ type }"]:has(.pkiw-presentation-badge__type)` );
			if ( await typed.count() ) {
				const expected = await resolvedToken( typed.first(), token );
				const actual = await typed.first().evaluate( ( node ) => getComputedStyle( node, '::after' ).backgroundColor );
				expect( actual, `${ type } band` ).toBe( expected );
			}
		}
	} );

	test( 'focus, dark paint, forced colors and pager use page tokens', async ( { page } ) => {
		await openArchive( page );
		const first = badges( page ).first();
		await first.locator( 'a' ).focus();
		const focus = await first.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { width: style.outlineWidth, color: style.outlineColor, shadow: style.boxShadow };
		} );
		expect( focus.width ).toBe( '3px' );
		expect( focus.color ).toBe( await resolvedToken( first, '--cr-focus-ring' ) );
		expect( focus.shadow ).toContain( await resolvedToken( first, '--cr-focus-halo' ) );

		const current = page.locator( '.wp-block-query-pagination .current' );
		await expect( current ).toHaveCSS( 'background-color', await resolvedToken( current, '--cr-ink' ) );
		await expect( current ).toHaveCSS( 'color', await resolvedToken( current, '--cr-ink-inverse' ) );
		expect( await axeContrast( page ) ).toEqual( [] );

		await page.emulateMedia( { colorScheme: 'dark' } );
		expect( await axeContrast( page ) ).toEqual( [] );
		await page.emulateMedia( { forcedColors: 'active' } );
		await expect( first ).toHaveCSS( 'border-top-width', '2px' );
	} );
} );

test.describe( 'presentation single', () => {
	test.skip( 'single' !== SET, 'single fixture set' );

	test( 'the record owns one H1 and keeps public facts before authored embeds', async ( { page } ) => {
		const record = await openSingle( page, 'S01' );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( record.locator( 'h1.pkiw-presentation-record__title' ) ).toHaveCount( 1 );
		await expect( page.locator( '.single-post__date' ) ).toBeHidden();
		await expect( record.locator( 'dt' ) ).toHaveText( [ 'Event', 'Date', 'Type' ] );
		await expect( record.locator( '.pkiw-presentation-record__links a' ) ).toHaveText( [ /Slides/, /Recording/ ] );
		await expect( page.locator( '.cr-presentation-frame' ) ).toHaveCount( 2 );
		for ( const frame of await page.locator( '.cr-presentation-frame iframe, iframe.cr-presentation-frame' ).all() ) {
			await expect( frame ).toHaveAttribute( 'title', /^(Slides|Recording): / );
			await expect( frame ).toHaveAttribute( 'loading', 'lazy' );
		}
		const text = await page.locator( 'main' ).innerText();
		expect( text ).not.toContain( 'Sentinel Hall' );
		expect( text ).not.toContain( 'slide count' );
	} );

	test( 'empty and protected singles keep their reduced output', async ( { page } ) => {
		const empty = await openSingle( page, 'S02' );
		await expect( empty.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( empty.locator( 'dt' ) ).toHaveText( [ 'Date' ] );
		await expect( empty.locator( '.pkiw-presentation-record__links' ) ).toHaveCount( 0 );

		const response = await open( page, singlePath( 'S03' ) );
		skipUnlessSeeded( 404 === response?.status(), 'no protected single fixture S03' );
		await expect( page.locator( 'form.post-password-form' ) ).toHaveCount( 1 );
		await expect( page.locator( '.pkiw-presentation-record' ) ).toHaveCount( 0 );
	} );

	test( 'keyboard order reaches record links and embeds without a trap', async ( { page } ) => {
		await openSingle( page, 'S01' );
		const expected = page.locator( '.pkiw-presentation-record__event, .pkiw-presentation-record__links a, .cr-presentation-frame iframe, iframe.cr-presentation-frame' );
		const count = await expected.count();
		for ( let index = 0; index < count; index++ ) {
			await page.keyboard.press( 'Tab' );
		}
		expect( count ).toBeGreaterThanOrEqual( 4 );
	} );
} );

test.describe( 'presentation Stream', () => {
	test.skip( 'stream' !== SET, 'Stream fixture set' );

	test( 'the presentation preview becomes one one-link conference badge', async ( { page } ) => {
		await open( page, STREAM, 320 );
		const card = page.locator( '.cr-badge--stream' );
		skipUnlessSeeded( 0 === await card.count(), 'presentation Stream fixture is not seeded' );
		await expect( card ).toHaveCount( 1 );
		await expect( card.locator( 'a[href]' ) ).toHaveCount( 1 );
		await expect( card.locator( '.pk-kindlabel, .cr-badge__title, .cr-badge__event, .cr-badge__date, .cr-badge__type' ) ).toHaveCount( 5 );
		await expect( card.locator( '.cr-badge__strap[aria-hidden="true"]' ) ).toHaveCount( 1 );
		await expect( card.locator( 'iframe, button' ) ).toHaveCount( 0 );
		await assertNoOverflow( page, '320px Stream' );
	} );
} );

test.describe( 'presentation request privacy', () => {
	test.skip( ! [ 'archive', 'stream' ].includes( SET ), 'archive or Stream fixture set' );

	test( 'archive and Stream make no third-party request', async ( { page } ) => {
		const thirdParty = [];
		page.on( 'request', ( request ) => {
			const url = new URL( request.url() );
			if ( ! [ 'localhost', '127.0.0.1' ].includes( url.hostname ) && ! url.hostname.endsWith( '.localhost' ) ) {
				thirdParty.push( request.url() );
			}
		} );
		await open( page, 'archive' === SET ? ARCHIVE : STREAM );
		expect( thirdParty ).toEqual( [] );
	} );
} );
