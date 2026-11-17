// `CR_SPEC=follow-260.spec.mjs CR_FOLLOW_SET=<archive|single|stream|privacy>`:
// issue 260 follow archive, single and Stream contracts. Read-only GETs.
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_FOLLOW_ARCHIVE_PATH || '/kind/follow/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_FOLLOW_STREAM_PATH || '/stream/';
const SET = process.env.CR_FOLLOW_SET || 'archive';
const REQUIRE_SEED = '1' === process.env.CR_FOLLOW_REQUIRE_SEED;
const WIDTHS = [ 1440, 800, 375, 320 ];
const YELLOW = 'rgb(255, 183, 3)';
const IVORY = 'rgb(251, 250, 245)';
const VIOLET = 'rgb(36, 28, 74)';
const PAPERS = [ 'rgb(251, 250, 245)', 'rgb(254, 226, 195)', 'rgb(235, 235, 235)', 'rgb(188, 181, 227)' ];
// The bare host part also catches the slash-escaped URL in REST JSON.
const SENTINELS = [ 'Fw260 Sentinel Person', 'https://fw260-sentinel.example/', 'fw260-sentinel.png', 'fw260-sentinel' ];
const THEMES = [ 'light', 'dark', 'system-dark' ];

const slugOf = ( key ) => `seed-260-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/${ slugOf( key ) }/`;
const archivePath = ( number = 1 ) => 1 === number ? ARCHIVE : `${ ARCHIVE }page/${ number }/`;

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_FOLLOW_REQUIRE_SEED=1: ${ reason }` );
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
	skipUnlessSeeded( 404 === response?.status(), `no follow archive page ${ number }` );
	skipUnlessSeeded( 0 === await page.locator( '.pkiw-follow-entry' ).count(), 'the follow archive fixtures are not seeded' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status(), `no follow single fixture ${ key }` );
	return response;
}

async function openArchiveContaining( page, key ) {
	for ( let number = 1; number <= 10; number++ ) {
		const response = await open( page, archivePath( number ) );
		if ( 404 === response?.status() ) {
			break;
		}
		const locator = page.locator( `a[href*="${ slugOf( key ) }"]` );
		if ( await locator.count() ) {
			return locator;
		}
	}
	skipUnlessSeeded( true, `archive card ${ key } is missing` );
	return page.locator( 'a[data-missing-follow-fixture]' );
}

// `system-dark` reaches the `prefers-color-scheme: dark` rules the way a
// visitor with no stored choice does: no-flash.js writes `system`.
async function setTheme( page, theme ) {
	await page.emulateMedia( { colorScheme: 'system-dark' === theme ? 'dark' : 'light' } );
	await page.evaluate( ( value ) => {
		document.documentElement.dataset.theme = value;
	}, 'system-dark' === theme ? 'system' : theme );
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function axe( page, rules, root = 'main' ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async ( [ only, selector ] ) => {
		const context = document.querySelector( selector ) || document;
		return ( await window.axe.run( context, only ? { runOnly: only } : {} ) ).violations.map( ( violation ) => ( { id: violation.id, nodes: violation.nodes.map( ( node ) => node.target.join( ' ' ) ) } ) );
	}, [ rules, root ] );
}

// Measures each visible text element against the opaque fill behind it.
// axe reports the single card `incomplete` because its paper is an `::after`,
// so this reads that paper directly. `paper` names the element whose
// `::after` is the backdrop.
async function contrastPairs( page, root, paper = '' ) {
	return page.evaluate( ( [ selector, paperSelector ] ) => {
		const parse = ( value ) => {
			const match = value.match( /rgba?\(([^)]+)\)/ );
			if ( ! match ) {
				return null;
			}
			const [ r, g, b, a = 1 ] = match[1].split( /[ ,/]+/ ).filter( Boolean ).map( Number );
			return { r, g, b, a };
		};
		const luminance = ( { r, g, b } ) => {
			const channel = ( value ) => {
				const c = value / 255;
				return c <= 0.03928 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4;
			};
			return ( 0.2126 * channel( r ) ) + ( 0.7152 * channel( g ) ) + ( 0.0722 * channel( b ) );
		};
		const backdrop = ( node ) => {
			for ( let current = node; current; current = current.parentElement ) {
				if ( paperSelector && current.matches( paperSelector ) ) {
					const sheet = parse( getComputedStyle( current, '::after' ).backgroundColor );
					if ( sheet && sheet.a >= 0.99 ) {
						return sheet;
					}
				}
				const own = parse( getComputedStyle( current ).backgroundColor );
				if ( own && own.a >= 0.99 ) {
					return own;
				}
			}
			return parse( getComputedStyle( document.body ).backgroundColor );
		};
		const rows = [];
		for ( const scope of document.querySelectorAll( selector ) ) {
			for ( const node of [ scope, ...scope.querySelectorAll( '*' ) ] ) {
				const hasText = [ ...node.childNodes ].some( ( child ) => 3 === child.nodeType && '' !== child.textContent.trim() );
				const box = node.getBoundingClientRect();
				if ( ! hasText || box.width <= 1 || box.height <= 1 || ! node.checkVisibility( { visibilityProperty: true, opacityProperty: true } ) ) {
					continue;
				}
				const fg = parse( getComputedStyle( node ).color );
				const bg = backdrop( node );
				const [ hi, lo ] = [ luminance( fg ), luminance( bg ) ].sort( ( x, y ) => y - x );
				rows.push( { text: node.textContent.trim().slice( 0, 40 ), ratio: Math.round( ( ( hi + 0.05 ) / ( lo + 0.05 ) ) * 100 ) / 100 } );
			}
		}
		return rows;
	}, [ root, paper ] );
}

async function expectContrast( page, root, label, paper = '' ) {
	const rows = await contrastPairs( page, root, paper );
	expect( rows.length, `${ label }: measured text pairs` ).toBeGreaterThan( 0 );
	expect( rows.filter( ( row ) => row.ratio < 4.5 ), `${ label }: pairs under 4.5:1` ).toEqual( [] );
}

async function allArchiveEntries( page ) {
	const rows = [];
	for ( let number = 1; number <= 10; number++ ) {
		const response = await open( page, archivePath( number ) );
		if ( 404 === response?.status() ) {
			break;
		}
		const pageRows = await page.locator( '.pkiw-follow-entry' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( {
			name: node.querySelector( '.pkiw-follow-entry__name' )?.textContent.trim() || '',
			initial: node.dataset.pkiwFollowInitial || '',
			href: node.querySelector( '.pkiw-follow-entry__name a' )?.getAttribute( 'href' ) || '',
		} ) ) );
		if ( 0 === pageRows.length ) {
			break;
		}
		rows.push( ...pageRows );
		if ( 0 === await page.locator( 'nav[aria-label="Pagination"] a:has-text("Next")' ).count() ) {
			break;
		}
	}
	return rows;
}

const entries = ( page ) => page.locator( '.pkiw-follow-entry' );

test.describe( 'Follow archive', () => {
	test.skip( 'archive' !== SET, 'archive fixture set' );

	test( 'A-A1/A-A2: header and heading outline use the served singular term', async ( { page } ) => {
		await openArchive( page );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'Follow' );
		await expect( page.locator( 'main h1 .cr-cutout__tiles[aria-hidden="true"] .cr-cutout__tile' ) ).toHaveCount( 6 );
		const rotations = await page.locator( 'main h1 .cr-cutout__tile' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => [ ...node.classList ].find( ( name ) => name.startsWith( 'is-r' ) ) ) );
		for ( let index = 1; index < rotations.length; index++ ) {
			expect( rotations[ index ] ).not.toBe( rotations[ index - 1 ] );
		}
		await expect( page.locator( '.wp-block-term-description' ) ).not.toHaveText( '' );
		await expect( page.locator( '.cr-archive__header .is-style-cr-marker-bar-short' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-archive-identity__glyph[aria-hidden="true"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-archive-identity a, .cr-archive-identity [tabindex]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.pkiw-follow-entry h2' ) ).toHaveCount( await entries( page ).count() );
		await expect( page.locator( '.cr-rolodex h3' ) ).toHaveCount( 0 );
		const text = await page.locator( 'main' ).innerText();
		for ( const banned of [ 'Manage private follows', 'PUBLIC FOLLOW', 'Found via', 'Sample content', 'Showing public follow announcements' ] ) {
			expect( text ).not.toContain( banned );
		}
	} );

	test( 'A-A3: archive and letter URLs preserve the served A-Z contract', async ( { page } ) => {
		await openArchive( page );
		const letters = await page.locator( '.pkiw-follow-letters ul a' ).allInnerTexts();
		expect( letters ).toContain( 'T' );
		expect( letters ).toContain( 'V' );
		for ( const absent of [ 'Q', 'U', 'W', 'X', 'Y', 'Z' ] ) {
			expect( letters ).not.toContain( absent );
		}
		// A to Z with accented letters after their base letter, and # last (spec C4).
		expect( letters ).toEqual( [ ...letters.filter( ( letter ) => '#' !== letter ).sort( ( a, b ) => a.localeCompare( b ) ), ...letters.filter( ( letter ) => '#' === letter ) ] );
		await expect( page.locator( '.pkiw-follow-letters [aria-current]' ) ).toHaveCount( 0 );

		const rows = await allArchiveEntries( page );
		expect( new Set( rows.map( ( row ) => row.href ) ).size ).toBe( rows.length );
		const named = rows.map( ( row ) => row.name.toLocaleLowerCase() );
		// The database collation orders accented names (MariaDB puts Émile beside E, SQLite after Z), so the
		// plain names must be in A-Z order, and the name-less M01 row (host micro.example) must come last.
		const plain = named.filter( ( name ) => /^[a-z0-9]/.test( name ) && 'micro.example' !== name );
		expect( plain ).toEqual( [ ...plain ].sort( ( a, b ) => a.localeCompare( b ) ) );
		expect( named[ named.length - 1 ] ).toBe( 'micro.example' );

		await openArchive( page );
		const links = await page.locator( '.pkiw-follow-letters ul a' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { text: node.textContent.trim(), href: node.href } ) ) );
		for ( const link of links ) {
			await open( page, link.href );
			await expect( page.locator( '.pkiw-follow-letters a[aria-current="page"]' ) ).toHaveText( link.text );
			const initials = await entries( page ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.dataset.pkiwFollowInitial ) );
			expect( new Set( initials ) ).toEqual( new Set( [ link.text ] ) );
			for ( const href of await page.locator( 'nav[aria-label="Pagination"] a' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.getAttribute( 'href' ) ) ) ) {
				expect( new URL( href, page.url() ).searchParams.get( 'pkiw_follow_initial' ) ).toBe( link.text );
			}
		}
	} );

	test( 'A-A4: grid is 4/2/1, row-major, centered after the cards and never overflows', async ( { page } ) => {
		const expected = new Map( [ [ 1440, 4 ], [ 800, 2 ], [ 375, 1 ], [ 320, 1 ] ] );
		for ( const width of WIDTHS ) {
			await openArchive( page, 1, width );
			const columns = await page.locator( '.cr-rolodex__cards' ).evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length );
			expect( columns, `${ width }px columns` ).toBe( expected.get( width ) );
			const positions = await entries( page ).evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
				const box = node.getBoundingClientRect();
				return { index, top: Math.round( box.top ), left: Math.round( box.left ) };
			} ) );
			const visual = [ ...positions ].sort( ( a, b ) => 2 < Math.abs( a.top - b.top ) ? a.top - b.top : a.left - b.left ).map( ( item ) => item.index );
			expect( visual ).toEqual( positions.map( ( item ) => item.index ) );
			await assertNoOverflow( page, `${ width }px archive` );
		}
		await openArchive( page );
		const followsCards = await page.evaluate( () => {
			const cards = document.querySelector( '.cr-rolodex__cards' );
			const pager = document.querySelector( 'nav[aria-label="Pagination"]' );
			return !! ( cards.compareDocumentPosition( pager ) & Node.DOCUMENT_POSITION_FOLLOWING );
		} );
		expect( followsCards ).toBe( true );
	} );

	test( 'A-A5: cards have one whole-card link and fixture fallbacks remain distinguishable', async ( { page } ) => {
		await openArchive( page );
		for ( const entry of await entries( page ).all() ) {
			await expect( entry.locator( 'a[href]' ) ).toHaveCount( 1 );
			await expect( entry.locator( '.pkiw-follow-entry__host a' ) ).toHaveCount( 0 );
			const hit = await entry.locator( '.pkiw-follow-entry__name a' ).evaluate( ( link ) => getComputedStyle( link, '::after' ).position );
			expect( hit ).toBe( 'absolute' );
		}
		const f07 = await openArchiveContaining( page, 'F07' );
		await expect( f07.locator( 'xpath=ancestor::article[1]' ).locator( '.pkiw-follow-entry__initial' ) ).toBeVisible();
		for ( const key of [ 'F05', 'M01' ] ) {
			await expect( await openArchiveContaining( page, key ) ).toContainText( '.' );
		}
		await openArchiveContaining( page, 'F01' );
		const same = await page.locator( `a[href*="${ slugOf( 'F01' ) }"], a[href*="${ slugOf( 'F02' ) }"]` ).evaluateAll( ( links ) => links.map( ( link ) => link.getAttribute( 'aria-labelledby' ).split( ' ' ).map( ( id ) => document.getElementById( id )?.textContent.trim() ).join( ' ' ) ) );
		expect( same ).toHaveLength( 2 );
		expect( same[0] ).not.toBe( same[1] );
	} );

	test( 'A-A6/A-A10: no sentinel or remote photo leaks; hidden entry properties remain complete', async ( { page } ) => {
		const escaped = [];
		page.on( 'request', ( request ) => {
			const host = new URL( request.url() ).hostname;
			if ( ! [ 'localhost', '127.0.0.1', '::1' ].includes( host ) && ! host.endsWith( '.local' ) ) {
				escaped.push( request.url() );
			}
		} );
		await openArchive( page );
		const html = await page.content();
		for ( const sentinel of SENTINELS ) {
			expect( html ).not.toContain( sentinel );
		}
		expect( escaped ).toEqual( [] );
		for ( const entry of await entries( page ).all() ) {
			await expect( entry ).toHaveClass( /\bh-entry\b/ );
			await expect( entry.locator( 'a.u-url' ) ).toHaveCount( 1 );
			await expect( entry.locator( 'time.dt-published[hidden]' ) ).toHaveCount( 1 );
			await expect( entry.locator( '[hidden] .p-author, [hidden].p-author' ) ).not.toHaveCount( 0 );
			await expect( entry.locator( 'data.u-follow-of[hidden]' ) ).toHaveCount( 1 );
		}
	} );

	test( 'A-A7/A-A8: paint, targets, contrast, focus, dark and reduced motion', async ( { page } ) => {
		for ( const theme of THEMES ) {
			await openArchive( page );
			await setTheme( page, theme );
			for ( const entry of await entries( page ).all() ) {
				const paint = await entry.evaluate( ( node ) => {
					const style = getComputedStyle( node );
					return { bg: style.backgroundColor, border: style.borderTopWidth, shadow: style.boxShadow, transform: style.transform, image: style.backgroundImage };
				} );
				if ( 'light' === theme ) {
					expect( PAPERS ).toContain( paint.bg );
				} else {
					expect( paint.bg ).toBe( 'rgb(37, 31, 28)' );
				}
				expect( paint.border ).toBe( '2px' );
				expect( paint.shadow ).toMatch( /4px 4px 0px/ );
				expect( paint.transform ).toBe( 'none' );
				expect( paint.image ).toBe( 'none' );
			}
			for ( const target of await page.locator( '.pkiw-follow-letters a, nav[aria-label="Pagination"] a, nav[aria-label="Pagination"] [aria-current="page"]' ).all() ) {
				const box = await target.boundingBox();
				expect( box.width ).toBeGreaterThanOrEqual( 44 );
				expect( box.height ).toBeGreaterThanOrEqual( 44 );
			}
			expect( await axe( page, [ 'color-contrast' ], '.cr-rolodex' ) ).toEqual( [] );
			await expectContrast( page, '.pkiw-follow-entry', `archive ${ theme }` );
		}
		await page.emulateMedia( { colorScheme: 'light' } );
		await openArchive( page );
		const first = entries( page ).first();
		await first.locator( 'a' ).focus();
		const focus = await first.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { color: style.outlineColor, width: style.outlineWidth, shadow: style.boxShadow };
		} );
		expect( focus.width ).toBe( '3px' );
		expect( focus.color ).toBe( YELLOW );
		expect( focus.shadow ).toMatch( /8px/ );

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await openArchive( page );
		expect( await entries( page ).first().evaluate( ( node ) => getComputedStyle( node ).transitionProperty ) ).not.toContain( 'translate' );
	} );

	test( 'A-A8 forced colors drops paper and slots', async ( { page } ) => {
		await page.emulateMedia( { forcedColors: 'active' } );
		await openArchive( page );
		const paint = await entries( page ).first().evaluate( ( node ) => ( {
			border: getComputedStyle( node ).borderTopWidth,
			before: getComputedStyle( node, '::before' ).display,
			after: getComputedStyle( node, '::after' ).display,
		} ) );
		expect( paint.border ).toBe( '2px' );
		expect( paint.before ).toBe( 'none' );
		expect( paint.after ).toBe( 'none' );
	} );

	test( 'A-A9: archive photos are local and first-row loading differs from later rows', async ( { page } ) => {
		await openArchive( page );
		for ( let index = 0; index < await entries( page ).count(); index++ ) {
			const image = entries( page ).nth( index ).locator( '.pkiw-follow-entry__image' );
			if ( 0 === await image.count() ) {
				continue;
			}
			expect( new URL( await image.getAttribute( 'src' ), page.url() ).pathname ).toContain( '/wp-content/uploads/' );
			if ( index < 4 ) {
				await expect( image ).toHaveAttribute( 'loading', 'eager' );
				await expect( image ).toHaveAttribute( 'fetchpriority', 'high' );
			} else {
				await expect( image ).toHaveAttribute( 'loading', 'lazy' );
			}
		}
	} );
} );

test.describe( 'Follow single', () => {
	test.skip( 'single' !== SET, 'single fixture set' );

	test( 'A-S1/A-S2: H1 branches and card order match S01-S03', async ( { page } ) => {
		await openSingle( page, 'S01' );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main h1' ) ).toContainText( /.+/ );
		await expect( page.locator( '.cr-follow-card__name' ) ).toHaveText( 'Amara Okafor' );
		await expect( page.locator( '.cr-follow-card__photo img' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main img' ) ).toHaveCount( 1 );

		await openSingle( page, 'S02' );
		await expect( page.locator( 'main h1.cr-follow-card__name' ) ).toHaveText( 'Ines Duarte' );
		await expect( page.locator( '.cr-follow-card__note .cr-follow-card__label' ) ).toHaveCount( 0 );

		await openSingle( page, 'S03' );
		await expect( page.locator( 'main h1.cr-follow-card__name' ) ).toHaveText( 'micro.example' );
		await expect( page.locator( '.cr-follow-card__note' ) ).not.toContainText( /^\s*Followed\s+https?:/ );
		const order = await page.locator( '.cr-follow-single' ).evaluate( ( root ) => {
			const selectors = [ '.cr-follow-card__photo, .cr-follow-card__initial', '.cr-follow-card__name', '.cr-follow-card__site', '.cr-follow-card__followed', '.cr-follow-card__note' ];
			return selectors.map( ( selector ) => root.querySelector( selector ) ).filter( Boolean ).map( ( node ) => node.className );
		} );
		expect( order ).toEqual( [ 'cr-follow-card__initial', 'cr-follow-card__name', 'cr-follow-card__site', 'cr-follow-card__followed', 'cr-follow-card__note' ] );
	} );

	test( 'A-S3/A-S4: one provider-tape link and one published-day row', async ( { page } ) => {
		await openSingle( page, 'S01' );
		const card = page.locator( '.cr-follow-single' );
		await expect( card.locator( 'a.cr-follow-card__site' ) ).toHaveCount( 1 );
		await expect( card.locator( `a:not(.cr-follow-card__site)[href="${ await card.locator( 'a.cr-follow-card__site' ).getAttribute( 'href' ) }"]` ) ).toHaveCount( 0 );
		const tape = await card.locator( '.cr-follow-card__site' ).evaluate( ( node ) => {
			const style = getComputedStyle( node );
			const box = node.getBoundingClientRect();
			return { family: style.fontFamily, border: style.borderTopWidth, shadow: style.boxShadow, height: box.height };
		} );
		expect( tape.family ).toMatch( /Mono|Menlo|Consolas|monospace/i );
		// Chrome snaps a 1.5px border to whole device pixels, so a 1x run reports 1px.
		expect( [ '1px', '1.5px' ] ).toContain( tape.border );
		expect( tape.shadow ).toMatch( /2px 2px 0px/ );
		expect( tape.height ).toBeGreaterThanOrEqual( 44 );
		await expect( card.locator( '.cr-follow-card__followed .cr-follow-card__label' ) ).toHaveText( 'Followed' );
		await expect( card.locator( '.cr-follow-card__followed time' ) ).toHaveAttribute( 'datetime', /^\d{4}-\d{2}-\d{2}$/ );
	} );

	test( 'A-S5: type, wheel threshold and zoom-width layout', async ( { page } ) => {
		for ( const width of [ 800, 375, 320 ] ) {
			await openSingle( page, 'S01', width );
			const paint = await page.locator( '.cr-follow-single' ).evaluate( ( node ) => ( {
				wheel: getComputedStyle( node, '::before' ).display,
				name: getComputedStyle( node.querySelector( '.cr-follow-card__name' ) ).fontFamily,
				note: getComputedStyle( node.querySelector( '.cr-follow-card__note' ) ).fontFamily,
				label: getComputedStyle( node.querySelector( '.cr-follow-card__label' ) ).fontFamily,
			} ) );
			expect( paint.wheel ).toBe( width >= 640 ? 'block' : 'none' );
			expect( paint.name ).toContain( 'Roboto Slab' );
			expect( paint.note ).toContain( 'Barlow' );
			expect( paint.label ).toMatch( /Mono|Menlo|Consolas|monospace/i );
			await assertNoOverflow( page, `${ width }px single` );
		}
	} );

	test( 'A-S6/A-S7 DOM: nav, banned copy, hidden h-card, dark and forced colors', async ( { page } ) => {
		await openSingle( page, 'S01' );
		const nav = page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		await expect( nav ).toHaveCount( 1 );
		await expect( nav ).toContainText( 'Previous' );
		await expect( nav ).toContainText( 'Next' );
		const visibleNav = ( await nav.innerText() ).replace( /\s+/g, ' ' );
		expect( visibleNav ).not.toContain( 'Amara Okafor' );
		const html = await page.locator( 'main' ).innerHTML();
		for ( const banned of [ 'PUBLIC FOLLOW', 'public by author choice', 'Request correction or removal', 'Visit ' ] ) {
			expect( html ).not.toContain( banned );
		}
		await expect( page.locator( '.cr-follow-card__note .u-follow-of.h-card[hidden], .cr-follow-card__note [hidden] .u-follow-of.h-card' ) ).toHaveCount( 1 );
		for ( const theme of THEMES ) {
			await setTheme( page, theme );
			expect( await axe( page, [ 'color-contrast' ], '.cr-follow-single' ) ).toEqual( [] );
			await expectContrast( page, '.cr-follow-single', `single ${ theme }`, '.cr-follow-single' );
		}
		await setTheme( page, 'light' );
		await page.emulateMedia( { forcedColors: 'active' } );
		await openSingle( page, 'S01' );
		expect( await page.locator( '.cr-follow-single' ).evaluate( ( node ) => getComputedStyle( node, '::before' ).display ) ).toBe( 'none' );
		expect( await page.locator( '.cr-follow-single' ).evaluate( ( node ) => getComputedStyle( node, '::after' ).borderTopWidth ) ).toBe( '2px' );
	} );
} );

test.describe( 'Follow Stream', () => {
	test.skip( 'stream' !== SET, 'Stream fixture set' );

	test( 'A-R1/A-R2: cards keep the collage width and frozen one-link content', async ( { page } ) => {
		for ( const width of [ 1440, 375, 320 ] ) {
			await open( page, STREAM, width );
			const cards = page.locator( '.cr-follow-stream' );
			skipUnlessSeeded( 0 === await cards.count(), 'the follow Stream fixtures are not seeded' );
			for ( const card of await cards.all() ) {
				await expect( card.locator( 'a[href]' ) ).toHaveCount( 1 );
				await expect( card.locator( '.cr-follow-stream__host a' ) ).toHaveCount( 0 );
				await expect( card.locator( 'time.dt-published' ) ).toHaveCount( 1 );
				await expect( card.locator( '.cr-follow-stream__wheel[aria-hidden="true"]' ) ).toHaveCount( 1 );
				expect( await card.locator( '.cr-follow-stream__wheel' ).evaluate( ( node ) => getComputedStyle( node ).display ), `${ width }px Stream wheel` ).toBe( width >= 640 ? 'block' : 'none' );
				await expect( card.locator( '.cr-follow-card__note' ) ).toHaveCount( 0 );
				const text = await card.innerText();
				expect( text ).not.toContain( 'PUBLIC FOLLOW' );
				const rotated = await card.evaluate( ( root ) => [ root, ...root.querySelectorAll( '*' ) ].filter( ( node ) => 'none' !== getComputedStyle( node ).transform && ! node.matches( 'svg, svg *' ) ).map( ( node ) => node.className ) );
				expect( rotated.length ).toBeLessThanOrEqual( 2 );
			}
			await assertNoOverflow( page, `${ width }px Stream` );
		}
	} );

	test( 'A-R4: Stream contrast, focus and forced colors pass', async ( { page } ) => {
		for ( const theme of THEMES ) {
			await open( page, STREAM );
			await setTheme( page, theme );
			skipUnlessSeeded( 0 === await page.locator( '.cr-follow-stream' ).count(), 'the follow Stream fixtures are not seeded' );
			expect( await axe( page, [ 'color-contrast' ], '.cr-stream-page' ) ).toEqual( [] );
			await expectContrast( page, '.cr-follow-stream', `Stream ${ theme }` );
		}
		await setTheme( page, 'light' );
		const card = page.locator( '.cr-follow-stream' ).first();
		await card.locator( 'a' ).focus();
		const focus = await card.evaluate( ( node ) => ( { width: getComputedStyle( node ).outlineWidth, color: getComputedStyle( node ).outlineColor } ) );
		expect( focus.width ).toBe( '3px' );
		expect( focus.color ).toBe( YELLOW );
		await page.emulateMedia( { forcedColors: 'active' } );
		await open( page, STREAM );
		expect( await page.locator( '.cr-follow-stream__wheel' ).first().evaluate( ( node ) => getComputedStyle( node ).display ) ).toBe( 'none' );
	} );
} );

test.describe( 'Follow privacy set', () => {
	test.skip( 'privacy' !== SET, 'privacy fixture set' );
	test.describe.configure( { mode: 'serial' } );
	let seeded = false;

	test( 'A-R3/privacy: presence control Fw260 Visible Person prints on the archive', async ( { page } ) => {
		const link = await openArchiveContaining( page, 'V1' );
		await expect( link.first() ).toContainText( 'Fw260 Visible Person' );
		seeded = true;
	} );

	for ( const path of [ ARCHIVE, STREAM, '/feed/', '/kind/follow/feed/', '/wp-sitemap-posts-post-1.xml', '/wp-json/wp/v2/posts?per_page=100' ] ) {
		test( `A-R3/privacy: ${ path } contains no follow sentinel`, async ( { page } ) => {
			skipUnlessSeeded( ! seeded, 'the privacy fixtures are not seeded (no presence control)' );
			const response = await open( page, path );
			skipUnlessSeeded( 404 === response?.status(), `no surface ${ path }` );
			const body = await page.content();
			for ( const sentinel of SENTINELS ) {
				expect( body ).not.toContain( sentinel );
			}
		} );
	}
} );
