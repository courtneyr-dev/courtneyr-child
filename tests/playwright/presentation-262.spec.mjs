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

function isReservedTestHost( hostname ) {
	const host = hostname.toLowerCase().replace( /\.$/, '' );
	// RFC 2606/6761 test names never identify a production third party.
	return [ 'invalid', 'example', 'test', 'localhost', 'example.com', 'example.org', 'example.net' ]
		.some( ( reserved ) => host === reserved || host.endsWith( `.${ reserved }` ) );
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

async function box( locator ) {
	const value = await locator.boundingBox();
	expect( value ).not.toBeNull();
	return { ...value, top: value.y, right: value.x + value.width, bottom: value.y + value.height, left: value.x };
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
		// One H2 per year inside the wall; the shared "Browse all" footer adds its own H2 on every archive.
		await expect( page.locator( 'main .cr-badge-wall h2, main .pkiw-presentation-wall h2' ) ).toHaveCount( 3 );
		// Badge H3s only; the shared "Browse all" footer has its own H3 group headings.
		await expect( page.locator( 'main .cr-badge-wall h3' ) ).toHaveCount( await badges( page ).count() );
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
		// Badge markup only: the kind's term description ("Slides or a talk you gave …") is site copy, not badge content.
		const html = ( await badges( page ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.outerHTML ) ) ).join( '\n' );
		for ( const banned of [ '<iframe', 'Slides', 'Recording', 'Sentinel Hall', 'Sentinel Locked Summit', 'Sentinel Private Summit', 'sentinel-slides.example', 'Sample content' ] ) {
			expect( html ).not.toContain( banned );
		}
		for ( const key of [ 'P09', 'P10' ] ) {
			const neutral = fixtureBadge( page, key );
			await expect( neutral.locator( '.pkiw-presentation-badge__type' ) ).toHaveCount( 0 );
			expect( await neutral.evaluate( ( node ) => getComputedStyle( node, '::after' ).backgroundColor ) ).toBe( await resolvedToken( neutral, '--cr-light-gray' ) );
		}
		await expect( fixtureBadge( page, 'P11' ).locator( 'a' ) ).toHaveAccessibleName( 'Shipping accessible blocks Quiet Forum' );
		await expect( fixtureBadge( page, 'P12' ).locator( 'a' ) ).toHaveAccessibleName( 'Shipping accessible blocks Cedar Camp' );
		await expect( page.locator( '.pkiw-presentation-badge:not(:has(a[href*="seed-262-p11"])):not(:has(a[href*="seed-262-p12"])) a[aria-labelledby]' ) ).toHaveCount( 0 );

		const typeTokens = new Map( [
			[ 'talk', '--cr-cerulean' ],
			[ 'keynote', '--cr-russian-violet' ],
			[ 'workshop', '--cr-blue-green' ],
			[ 'panel', '--cr-glaucous' ],
			[ 'lightning-talk', '--cr-selective-yellow' ],
			[ 'meetup', '--cr-ut-orange' ],
			[ 'podcast-guest', '--cr-coral' ],
		] );
		// Dark mode lifts the two darkest bands to meet 3:1 on the night surface.
		if ( await page.evaluate( () => 'dark' === document.documentElement.dataset.theme || ( 'light' !== document.documentElement.dataset.theme && matchMedia( '(prefers-color-scheme: dark)' ).matches ) ) ) {
			typeTokens.set( 'talk', '--cr-sky-blue' );
			typeTokens.set( 'keynote', '--cr-periwinkle' );
		}
		for ( const [ type, token ] of typeTokens ) {
			const typed = page.locator( `.pkiw-presentation-badge[data-pkiw-event-type="${ type }"]:has(.pkiw-presentation-badge__type)` );
			if ( await typed.count() ) {
				const expected = await resolvedToken( typed.first(), token );
				const actual = await typed.first().evaluate( ( node ) => [ getComputedStyle( node, '::before' ).backgroundColor, getComputedStyle( node, '::after' ).backgroundColor ] );
				expect( actual, `${ type } strap and band` ).toEqual( [ expected, expected ] );
			}
		}
	} );

	test( 'straps, year tape and long text stay inside their layout boxes', async ( { page } ) => {
		await openArchive( page, 1, 1440 );
		const wallBox = await box( page.locator( '.pkiw-presentation-wall' ).first() );
		for ( const badge of await badges( page ).all() ) {
			const badgeBox = await box( badge );
			const strap = await badge.evaluate( ( node ) => {
				const style = getComputedStyle( node, '::before' );
				return { top: Number.parseFloat( style.top ), height: Number.parseFloat( style.height ) };
			} );
			expect( badgeBox.top + strap.top ).toBeGreaterThanOrEqual( wallBox.top - 2 );
			expect( badgeBox.top + strap.top + strap.height ).toBeLessThanOrEqual( badgeBox.bottom + 2 );
		}
		const yearTape = await page.locator( '.pkiw-presentation-wall__year-label' ).evaluateAll( ( nodes ) => nodes.slice( 0, 2 ).map( ( node ) => {
			const before = getComputedStyle( node, '::before' );
			const after = getComputedStyle( node, '::after' );
			return `${ before.backgroundColor }|${ before.transform }|${ after.transform }`;
		} ) );
		expect( yearTape[0] ).not.toBe( yearTape[1] );
		await openArchive( page, 2, 1440 );
		const long = fixtureBadge( page, 'P13' ).locator( 'a' );
		await expect( long ).toHaveText( /A detailed presentation title that wraps across several lines/ );
		expect( await long.evaluate( ( node ) => node.scrollWidth <= node.clientWidth && node.scrollHeight <= node.getBoundingClientRect().height + 1 ) ).toBe( true );
	} );

	test( 'page 2 repeats its year, ends the pager and renders C04 as title-only', async ( { page } ) => {
		await openArchive( page, 2 );
		await expect( page.locator( '.pkiw-presentation-wall__year-label' ).first() ).toHaveText( '2024' );
		const protectedBadge = fixtureBadge( page, 'C04' );
		await expect( protectedBadge ).toHaveCount( 1 );
		// Core prefixes protected titles (protected_title_format), so the name ends with the stored title.
		await expect( protectedBadge.locator( 'a' ) ).toHaveAccessibleName( /Protected presentation control$/ );
		await expect( protectedBadge.locator( '.pkiw-presentation-badge__event, .pkiw-presentation-badge__type' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-query-pagination-next' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( 1 );
		const missing = await open( page, archivePath( 3 ) );
		expect( missing?.status() ).toBe( 404 );
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
		for ( const target of await page.locator( '.wp-block-query-pagination a, .wp-block-query-pagination .current' ).all() ) {
			const targetBox = await box( target );
			expect( targetBox.width ).toBeGreaterThanOrEqual( 44 );
			expect( targetBox.height ).toBeGreaterThanOrEqual( 44 );
		}

		await page.emulateMedia( { colorScheme: 'dark' } );
		await expect( first ).toHaveCSS( 'background-color', await resolvedToken( first, '--cr-night-elevated' ) );
		expect( await first.evaluate( ( node ) => getComputedStyle( node, '::before' ).borderTopColor ) ).toBe( await resolvedToken( first, '--cr-printer-ivory' ) );
		const darkBandContrast = await badges( page ).evaluateAll( ( nodes ) => {
			const luminance = ( color ) => {
				const channels = color.match( /[\d.]+/g ).slice( 0, 3 ).map( ( value ) => Number( value ) / 255 ).map( ( value ) => value <= 0.04045 ? value / 12.92 : ( ( value + 0.055 ) / 1.055 ) ** 2.4 );
				return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
			};
			return nodes.map( ( node ) => {
				const face = luminance( getComputedStyle( node ).backgroundColor );
				const band = luminance( getComputedStyle( node, '::after' ).backgroundColor );
				return ( Math.max( face, band ) + 0.05 ) / ( Math.min( face, band ) + 0.05 );
			} );
		} );
		expect( darkBandContrast.every( ( ratio ) => ratio >= 3 ) ).toBe( true );
		expect( await axeContrast( page ) ).toEqual( [] );

		await page.emulateMedia( { colorScheme: 'light' } );
		await page.evaluate( () => document.documentElement.dataset.theme = 'dark' );
		await expect( first ).toHaveCSS( 'background-color', await resolvedToken( first, '--cr-night-elevated' ) );
		expect( await axeContrast( page ) ).toEqual( [] );

		await page.emulateMedia( { forcedColors: 'active', colorScheme: 'light' } );
		expect( await page.evaluate( () => matchMedia( '(forced-colors: active)' ).matches ) ).toBe( true );
		await expect( first ).toHaveCSS( 'border-top-width', '2px' );
		expect( await first.evaluate( ( node ) => [ '::before', '::after' ].map( ( pseudo ) => getComputedStyle( node, pseudo ).display ) ) ).toEqual( [ 'none', 'none' ] );
		await expect( first.locator( '.pkiw-presentation-badge__type' ) ).toBeVisible();

		await page.emulateMedia( { forcedColors: 'none', reducedMotion: 'reduce' } );
		// The theme-wide reduced-motion reset uses a near-zero duration (0.001ms), not 0s.
		expect( parseFloat( await first.evaluate( ( node ) => getComputedStyle( node ).transitionDuration ) ) ).toBeLessThanOrEqual( 0.01 );
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
		await expect( page.locator( '.able-player, [data-able-player]' ) ).toHaveCount( 1 );
		for ( const target of await record.locator( '.pkiw-presentation-record__event, .pkiw-presentation-record__links a' ).all() ) {
			const targetBox = await box( target );
			expect( targetBox.height ).toBeGreaterThanOrEqual( 44 );
		}
		await expect( page.locator( '.u-slides' ) ).toHaveCount( 1 );
		await expect( page.locator( '.u-recording' ) ).toHaveCount( 1 );
		const ordered = await page.evaluate( () => {
			const nodes = [ '.single-post__lede-meta', '.pkiw-presentation-record', '.cr-presentation-frame', '.single-post__comments-wrapper', 'nav.cr-post-nav' ].map( ( selector ) => document.querySelector( selector ) );
			return nodes.every( Boolean ) && nodes.slice( 1 ).every( ( node, index ) => Boolean( nodes[ index ].compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) );
		} );
		expect( ordered ).toBe( true );
		const entry = page.locator( '.single-post__content > .pkiw-singular-entry' );
		const geometry = await entry.evaluate( ( node ) => {
			const record = node.querySelector( '.pkiw-presentation-record' )?.getBoundingClientRect();
			const content = [ ...node.children ].filter( ( child ) => ! child.classList.contains( 'pkiw-presentation-record' ) ).map( ( child ) => child.getBoundingClientRect() );
			return {
				entry: node.getBoundingClientRect().height,
				record: record?.height || 0,
				content: content.length ? Math.max( ...content.map( ( rect ) => rect.bottom ) ) - Math.min( ...content.map( ( rect ) => rect.top ) ) : 0,
			};
		} );
		expect( geometry.entry ).toBeLessThanOrEqual( Math.max( geometry.record, geometry.content ) + 4 );
		const text = await page.locator( 'main' ).innerText();
		expect( text ).not.toContain( 'Sentinel Hall' );
		expect( record.locator( 'dt' ) ).not.toContainText( /slide count|position|location/i );
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
		await expect( page.locator( '.single-post__date' ) ).toBeVisible();
	} );

	test( 'keyboard order reaches record links and embeds without a trap', async ( { page } ) => {
		await openSingle( page, 'S01' );
		const expected = await page.evaluate( () => {
			const targets = [ ...document.querySelectorAll( '.pkiw-presentation-record__event, .pkiw-presentation-record__links a' ) ];
			for ( const frame of document.querySelectorAll( '.cr-presentation-frame' ) ) {
				const focusable = frame.matches( 'iframe' ) ? frame : frame.querySelector( 'iframe, button, a[href], [tabindex]:not([tabindex="-1"])' );
				if ( focusable ) targets.push( focusable );
			}
			return targets.map( ( node, index ) => {
				node.dataset.crPresentationFocusOrder = String( index );
				return String( index );
			} );
		} );
		expect( expected.length ).toBeGreaterThanOrEqual( 5 );
		const reached = [];
		for ( let index = 0; index < 100 && reached.length < expected.length; index++ ) {
			await page.keyboard.press( 'Tab' );
			const state = await page.evaluate( () => {
				const active = document.activeElement;
				const frame = active?.closest?.( '.cr-presentation-frame' );
				const style = getComputedStyle( frame || active );
				return { order: active?.dataset?.crPresentationFocusOrder || null, outline: style.outlineWidth };
			} );
			if ( null !== state.order ) {
				reached.push( state.order );
				expect( state.outline ).toBe( '3px' );
			}
		}
		expect( reached ).toEqual( expected );
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
		expect( await card.evaluate( ( node ) => [ '.pk-kindlabel', '.cr-badge__title', '.cr-badge__event', '.cr-badge__date', '.cr-badge__type' ].map( ( selector ) => node.querySelector( selector ) ).every( ( item, index, items ) => 0 === index || Boolean( items[ index - 1 ].compareDocumentPosition( item ) & Node.DOCUMENT_POSITION_FOLLOWING ) ) ) ).toBe( true );
		await expect( card.locator( '.cr-badge__strap[aria-hidden="true"]' ) ).toHaveCount( 1 );
		await expect( card.locator( 'iframe, button' ) ).toHaveCount( 0 );
		const cardBox = await box( card );
		const strapBox = await box( card.locator( '.cr-badge__strap' ) );
		expect( strapBox.top ).toBeGreaterThanOrEqual( cardBox.top );
		expect( strapBox.bottom ).toBeLessThanOrEqual( cardBox.bottom );
		expect( await card.evaluate( ( node ) => getComputedStyle( node, '::before' ).content ) ).not.toBe( 'none' );
		const neighbour = page.locator( '.pk-card:not(.cr-badge--stream)' ).first();
		if ( await neighbour.count() ) {
			expect( Math.abs( cardBox.width - ( await box( neighbour ) ).width ) ).toBeLessThanOrEqual( 2 );
			expect( await card.evaluate( ( node ) => getComputedStyle( node ).transform ) ).not.toBe( 'none' );
		}
		await assertNoOverflow( page, '320px Stream' );
	} );
} );

test.describe( 'presentation request privacy', () => {
	test.skip( ! [ 'archive', 'stream' ].includes( SET ), 'archive or Stream fixture set' );

	test( 'archive and Stream make no third-party request', async ( { page } ) => {
		const thirdParty = [];
		page.on( 'request', ( request ) => {
			const url = new URL( request.url() );
			if ( [ 'http:', 'https:' ].includes( url.protocol ) && '127.0.0.1' !== url.hostname && ! isReservedTestHost( url.hostname ) ) {
				thirdParty.push( request.url() );
			}
		} );
		await open( page, 'archive' === SET ? ARCHIVE : STREAM );
		expect( thirdParty ).toEqual( [] );
	} );
} );
