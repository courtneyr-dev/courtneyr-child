// `CR_SPEC=rsvp-251.spec.mjs`: issue 251 RSVP archive, single and Stream contracts.
// Read-only GETs; seed with the vault's assets/251-rsvps/seed-251.php.
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_RSVP_ARCHIVE_PATH || '/kind/rsvp/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_RSVP_STREAM_PATH || '/stream/';
const SET = process.env.CR_RSVP_SET || 'archive';
const REQUIRE_SEED = '1' === process.env.CR_RSVP_REQUIRE_SEED;
const WIDTHS = [ 1440, 800, 375, 320 ];
const SENTINEL = 'Rv251 Sentinel Hall, 1 Sentinel Way';
const EVENT_SENTINEL = 'https://events.example/rv251-sentinel';
const YELLOW = 'rgb(255, 183, 3)';

const slugOf = ( key ) => `seed-251-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const archivePath = ( page = 1 ) => 1 === page ? ARCHIVE : `${ ARCHIVE }page/${ page }/`;

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_RSVP_REQUIRE_SEED=1: ${ reason }` );
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
	skipUnlessSeeded( 0 === await page.locator( '.pkiw-rsvp-entry' ).count(), 'the RSVP archive fixtures are not seeded' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( '.cr-rsvp-ticket' ).count(), `no single fixture ${ key }` );
	return page.locator( '.cr-rsvp-ticket' ).first();
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function axeContrast( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations );
}

async function focusPaint( locator ) {
	await locator.focus();
	return locator.evaluate( async ( node ) => {
		await Promise.all( node.getAnimations().map( ( animation ) => animation.finished ) );
		const style = getComputedStyle( node, '::after' );
		return {
			color: style.outlineColor,
			width: style.outlineWidth,
			shadow: style.boxShadow,
			inset: [ style.top, style.right, style.bottom, style.left ],
		};
	} );
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

const entries = ( page ) => page.locator( '.pkiw-rsvp-entry' );
const fixtureEntry = ( page, key ) => entries( page ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

test.describe( 'RSVP archive', () => {
	test.skip( 'archive' !== SET, 'archive fixture set' );

	test( 'header, outline and fixture visibility follow the archive contract', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'RSVP' );
		const tiles = page.locator( 'main h1 .cr-cutout__tiles[aria-hidden="true"] .cr-cutout__tile' );
		await expect( tiles ).toHaveCount( 4 );
		const rotations = await tiles.evaluateAll( ( nodes ) => nodes.map( ( node ) => [ ...node.classList ].find( ( token ) => /^is-r\d+$/.test( token ) ) ) );
		expect( rotations.every( ( token, index ) => 0 === index || token !== rotations[ index - 1 ] ) ).toBe( true );
		await expect( page.locator( '.wp-block-term-description' ) ).toContainText( 'Response to an event invitation (yes, no, maybe, interested).' );
		await expect( page.locator( '.pkiw-rsvp-section__heading' ) ).toHaveText( [ 'Upcoming', 'Past' ] );
		await expect( page.locator( '.pkiw-rsvp-entry--upcoming' ) ).toHaveCount( 4 );
		await expect( page.locator( '.pkiw-rsvp-entry--past' ) ).toHaveCount( 5 );
		for ( const key of [ 'C01', 'C02', 'C03', 'C05' ] ) {
			await expect( fixtureEntry( page, key ) ).toHaveCount( 0 );
		}
	} );

	test( 'the RSVP header keeps its decorative badge, star and cutout in one left lockup', async ( { page } ) => {
		for ( const width of [ 1440, 1280, 390 ] ) {
			for ( const theme of [ 'light', 'dark' ] ) {
				await openArchive( page, 1, width );
				await page.evaluate( ( value ) => {
					document.documentElement.dataset.theme = value;
					return document.fonts.ready;
				}, theme );

				const header = page.locator( 'main .cr-archive__header' );
				const h1 = header.locator( 'h1.cr-archive__title.cr-cutout' );
				await expect( page.locator( 'main h1' ), `${ width }px ${ theme}: one H1` ).toHaveCount( 1 );
				await expect( h1, `${ width }px ${ theme}: the H1 names the archive` ).toHaveAccessibleName( 'RSVP' );

				const lockup = await header.evaluate( ( node ) => {
					const badgeHolder = node.querySelector( '.cr-archive-identity--kind' );
					const badge = badgeHolder.querySelector( '.cr-archive-identity__glyph' );
					const title = node.querySelector( 'h1.cr-archive__title.cr-cutout' );
					const tiles = title.querySelector( '.cr-cutout__tiles' );
					const wall = document.querySelector( '.cr-rsvp-wall__query' );
					const description = node.querySelector( '.cr-archive__description' );
					const divider = node.querySelector( 'hr.is-style-cr-marker-bar-short' );
					const box = ( element ) => {
						const rect = element.getBoundingClientRect();
						return { top: rect.top, right: rect.right, bottom: rect.bottom, left: rect.left, width: rect.width, height: rect.height };
					};
					const pseudoBox = ( element, pseudo ) => {
						const parent = box( element );
						const style = getComputedStyle( element, pseudo );
						const width = parseFloat( style.width );
						const height = parseFloat( style.height );
						const left = parent.left + parseFloat( style.left );
						const top = parent.top + parseFloat( style.top );
						const origin = style.transformOrigin.split( ' ' ).map( parseFloat );
						const matrix = 'none' === style.transform ? new DOMMatrix() : new DOMMatrix( style.transform );
						const points = [ [ 0, 0 ], [ width, 0 ], [ width, height ], [ 0, height ] ].map( ( point ) => {
							const transformed = new DOMPoint( point[0] - origin[0], point[1] - origin[1] ).matrixTransform( matrix );
							return { x: left + origin[0] + transformed.x, y: top + origin[1] + transformed.y };
						} );
						return {
							top: Math.min( ...points.map( ( point ) => point.y ) ),
							right: Math.max( ...points.map( ( point ) => point.x ) ),
							bottom: Math.max( ...points.map( ( point ) => point.y ) ),
							left: Math.min( ...points.map( ( point ) => point.x ) ),
							content: style.content,
							display: style.display,
							pointerEvents: style.pointerEvents,
						};
					};
					const named = ( element ) => [ 'aria-label', 'aria-labelledby', 'title' ].some( ( attribute ) => element.hasAttribute( attribute ) )
						|| null !== element.querySelector( 'title' );
					const focusable = ( element ) => [ element, ...element.querySelectorAll( '*' ) ].some( ( child ) => child.tabIndex >= 0 );
					const linked = ( element ) => null !== element.closest( 'a' ) || null !== element.querySelector( 'a' );
					return {
						badge: box( badge ),
						tiles: box( tiles ),
						star: pseudoBox( title, '::before' ),
						header: box( node ),
						wall: box( wall ),
						description: box( description ),
						divider: box( divider ),
						badgeDecorative: badge.getAttribute( 'aria-hidden' ) === 'true' && [ 'presentation', 'none' ].includes( badge.getAttribute( 'role' ) ) && ! named( badge ) && ! focusable( badge ) && ! linked( badge ),
						cutoutDecorative: tiles.getAttribute( 'aria-hidden' ) === 'true' && ! named( tiles ) && ! focusable( tiles ) && ! linked( tiles ),
						titleLinked: linked( title ),
						titleFocusable: focusable( title ),
						overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
					};
				} );

				expect( lockup.badgeDecorative, `${ width }px ${ theme}: badge is unnamed, unfocusable and unlinked` ).toBe( true );
				expect( lockup.cutoutDecorative, `${ width }px ${ theme}: cutout tiles are unnamed, unfocusable and unlinked` ).toBe( true );
				expect( lockup.star.content, `${ width }px ${ theme}: the star has an empty text alternative` ).toMatch( /✺.*\/\s*["']{2}/ );
				expect( lockup.star.display, `${ width }px ${ theme}: the decorative star is painted` ).toBe( 'block' );
				expect( lockup.star.pointerEvents, `${ width }px ${ theme}: the star can't receive pointer focus` ).toBe( 'none' );
				expect( lockup.titleLinked, `${ width }px ${ theme}: the star's H1 isn't a link` ).toBe( false );
				expect( lockup.titleFocusable, `${ width }px ${ theme}: the star's H1 isn't a keyboard stop` ).toBe( false );

				if ( 390 < width ) {
					const overlapsVertically = ( first, second ) => Math.min( first.bottom, second.bottom ) - Math.max( first.top, second.top ) > 0;
					expect( overlapsVertically( lockup.badge, lockup.tiles ), `${ width }px ${ theme}: badge and cutout share one row` ).toBe( true );
					expect( overlapsVertically( lockup.star, lockup.tiles ), `${ width }px ${ theme}: star and cutout share one row` ).toBe( true );
					// The badge keeps the shared -1.2deg sticker tilt, which widens its box by about 0.7px a side.
					expect( Math.abs( lockup.badge.left - lockup.wall.left ), `${ width }px ${ theme}: lockup starts at the wall's wide column` ).toBeLessThanOrEqual( 2 );
				} else {
					const overlaps = ( first, second ) => Math.min( first.right, second.right ) - Math.max( first.left, second.left ) > 0
						&& Math.min( first.bottom, second.bottom ) - Math.max( first.top, second.top ) > 0;
					const headerParts = [ lockup.badge, lockup.star, lockup.tiles, lockup.description, lockup.divider ];
					for ( let index = 0; index < headerParts.length; index++ ) {
						for ( let next = index + 1; next < headerParts.length; next++ ) {
							expect( overlaps( headerParts[index], headerParts[next] ), `390px ${ theme}: header parts ${ index + 1 } and ${ next + 1 } don't overlap` ).toBe( false );
						}
					}
				}

				expect( lockup.overflow, `${ width }px ${ theme}: no horizontal overflow` ).toBeLessThanOrEqual( 0 );
			}
		}
	} );

	test( 'posters and stubs use the target columns without changing DOM order', async ( { page } ) => {
		const expected = new Map( [ [ 1440, [ 3, 6 ] ], [ 800, [ 2, 3 ] ], [ 375, [ 1, 2 ] ], [ 320, [ 1, 1 ] ] ] );
		for ( const width of WIDTHS ) {
			await openArchive( page, 1, width );
			const columns = await page.locator( '.pkiw-rsvp--sectioned' ).evaluate( ( root ) => {
				const upcoming = root.querySelector( '[data-pkiw-group="upcoming"] ul' );
				const past = root.querySelector( '[data-pkiw-group="past"] ul' );
				return [ getComputedStyle( upcoming ).gridTemplateColumns.split( ' ' ).length, getComputedStyle( past ).gridTemplateColumns.split( ' ' ).length ];
			} );
			expect( columns, `${ width }px columns` ).toEqual( expected.get( width ) );
			const order = await entries( page ).evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
				const rect = node.getBoundingClientRect();
				return { index, top: Math.round( rect.top ), left: Math.round( rect.left ) };
			} ) );
			const visual = [ ...order ].sort( ( a, b ) => 2 < Math.abs( a.top - b.top ) ? a.top - b.top : a.left - b.left ).map( ( item ) => item.index );
			expect( visual ).toEqual( order.map( ( item ) => item.index ) );
			await assertNoOverflow( page, `${ width }px archive` );
		}
	} );

	test( 'items expose one destination and no private or extra RSVP content', async ( { page } ) => {
		await openArchive( page );
		for ( const entry of await entries( page ).all() ) {
			expect( await entry.locator( 'a[href]' ).count() ).toBe( 1 );
			expect( await entry.locator( 'a[href], button, input, select, textarea, [tabindex]' ).count() ).toBe( 1 );
		}
		const html = await page.locator( 'main' ).innerHTML();
		for ( const banned of [ 'Location hidden', 'RSVPed', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'https://images.example/cedar.png' ] ) {
			expect( html ).not.toContain( banned );
		}
		await expect( fixtureEntry( page, 'U01' ).locator( 'img[alt=""][loading="eager"][fetchpriority="high"]' ) ).toHaveCount( 1 );
		await expect( fixtureEntry( page, 'U03' ).locator( 'img[alt=""][loading="eager"][fetchpriority="high"]' ) ).toHaveCount( 1 );
	} );

	test( 'paint, contrast and focus stay within the token contract', async ( { page } ) => {
		await openArchive( page );
		const paint = await entries( page ).evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const style = getComputedStyle( node );
			return { background: style.backgroundImage, shadow: style.boxShadow, transform: style.transform };
		} ) );
		expect( paint.every( ( item ) => 'none' === item.background && ! /\d+px\s+\d+px\s+[1-9]\d*px/.test( item.shadow ) ) ).toBe( true );
		expect( await axeContrast( page ) ).toEqual( [] );
		const link = entries( page ).first().locator( 'a' );
		const focus = await focusPaint( link );
		const halo = await resolvedToken( link, '--cr-focus-halo' );
		expect( focus.width ).toBe( '3px' );
		expect( focus.color ).toBe( YELLOW );
		expect( focus.shadow ).toContain( halo );
		expect( focus.inset ).toEqual( [ '0px', '0px', '0px', '0px' ] );
		// The shared pager fills the current page with --cr-ink on --cr-ink-inverse; both flip in dark mode.
		const current = page.locator( '.wp-block-query-pagination .current' );
		await expect( current ).toHaveCSS( 'background-color', await resolvedToken( current, '--cr-ink' ) );
		await expect( current ).toHaveCSS( 'color', await resolvedToken( current, '--cr-ink-inverse' ) );
	} );
} );

test.describe( 'RSVP single', () => {
	test.skip( 'single' !== SET, 'single fixture set' );

	test( 'public ticket keeps event order, microformats and one punched status', async ( { page } ) => {
		const ticket = await openSingle( page, 'S01' );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( ticket.locator( '.cr-rsvp-ticket__poster + .cr-rsvp-ticket__event-heading + .cr-rsvp-ticket__facts' ) ).toHaveCount( 1 );
		await expect( ticket.locator( '.cr-rsvp-ticket__fact dt' ) ).toHaveText( [ 'Date', 'Time', 'Location' ] );
		await expect( ticket.locator( '.p-location' ) ).toHaveText( 'Example Hall, 1 Example Way' );
		await expect( ticket.locator( '.cr-rsvp-ticket__punch' ) ).toHaveCount( 5 );
		await expect( ticket.locator( '.cr-rsvp-ticket__punch.is-punched' ) ).toHaveCount( 1 );
		await expect( ticket.locator( '.cr-rsvp-ticket__punches' ) ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( ticket.locator( '.p-rsvp, .u-in-reply-to, .dt-published, .h-event' ) ).not.toHaveCount( 0 );
	} );

	test( 'private location and banned card chrome never enter the ticket', async ( { page } ) => {
		const ticket = await openSingle( page, 'S02' );
		const html = await ticket.innerHTML();
		expect( html ).not.toContain( 'Back Booth, 4 Example Row' );
		expect( html ).not.toContain( 'p-location' );
		for ( const banned of [ 'Location hidden', 'Add location', 'Get directions', 'Share', 'RSVP card' ] ) {
			expect( html ).not.toContain( banned );
		}
	} );

	test( 'same-name event uses provider tape and every target width stays contained', async ( { page } ) => {
		const ticket = await openSingle( page, 'S03' );
		await expect( ticket.locator( '.cr-rsvp-ticket__event-link' ) ).toHaveText( 'fernwood.example' );
		await expect( ticket.locator( '.cr-rsvp-ticket__event-link' ) ).toHaveAttribute( 'rel', 'noopener noreferrer' );
		for ( const width of WIDTHS ) {
			await openSingle( page, 'S03', width );
			await assertNoOverflow( page, `${ width }px single` );
		}
	} );
} );

test.describe( 'RSVP Stream', () => {
	test.skip( 'stream' !== SET, 'Stream fixture set' );

	test( 'three RSVP records become one-link torn stubs with bounded stamps', async ( { page } ) => {
		await open( page, STREAM, 320 );
		const stubs = page.locator( '.cr-rsvp-stub' );
		skipUnlessSeeded( 0 === await stubs.count(), 'the RSVP Stream fixtures are not seeded' );
		await expect( stubs ).toHaveCount( 3 );
		for ( const stub of await stubs.all() ) {
			await expect( stub.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( stub.locator( '.cr-rsvp-stub__kind, .cr-rsvp-stub__name, .cr-rsvp-stub__day, .cr-rsvp-stub__status, .cr-stamp-mark svg[aria-hidden="true"]' ) ).not.toHaveCount( 0 );
			const status = ( await stub.locator( '.p-rsvp' ).innerText() ).toUpperCase();
			await expect( stub.locator( '.cr-stamp__big' ) ).toHaveText( status );
			expect( await stub.innerHTML() ).not.toContain( 'p-location' );
			const paint = await stub.evaluate( ( node ) => {
				const style = getComputedStyle( node );
				return { display: style.display, background: style.backgroundColor, padding: style.paddingTop };
			} );
			expect( paint.display ).toBe( 'grid' );
			expect( paint.background ).not.toBe( 'rgba(0, 0, 0, 0)' );
			expect( paint.padding ).not.toBe( '0px' );
		}
		await expect( stubs.filter( { hasText: 'Attending Remotely' } ).locator( '.cr-stamp__big[textLength]' ) ).toHaveCount( 1 );
		await assertNoOverflow( page, '320px Stream' );
		await page.emulateMedia( { colorScheme: 'dark' } );
		expect( await axeContrast( page ) ).toEqual( [] );
	} );
} );

test.describe( 'RSVP privacy set', () => {
	test.skip( 'privacy' !== SET, 'privacy fixture set' );

	for ( const path of [ ARCHIVE, `${ ARCHIVE }page/2/`, STREAM, '/feed/', '/kind/rsvp/feed/', '/wp-json/wp/v2/posts?per_page=50' ] ) {
		test( `${ path } omits the stored location sentinel`, async ( { page } ) => {
			const response = await open( page, path );
			skipUnlessSeeded( 404 === response?.status(), `no surface ${ path }` );
			expect( await page.content() ).not.toContain( SENTINEL );
		} );
	}

	test( 'archive keeps the event presence control without a linked event URL', async ( { page } ) => {
		await openArchive( page );
		const html = await page.locator( 'main' ).innerHTML();
		expect( html ).toContain( EVENT_SENTINEL );
		expect( html ).not.toContain( `href="${ EVENT_SENTINEL }"` );
	} );
} );
