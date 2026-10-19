// Local stack only. Read-only GETs; requires assets/235-jams/seed-235.php.
// This spec never changes the browser identity.
//
//   CR_BASE_URL=http://localhost:8892 CR_SPEC=jam-235.spec.mjs \
//     npx playwright test --config tests/playwright/playwright.config.mjs
//
// CR_JAM_REQUIRE_SEED=1 turns missing-fixture skips into failures. Paths use
// CR_JAM_ARCHIVE_PATH and CR_JAM_STREAM_PATHS. CR_STORAGE_STATE may point
// to a logged-in Playwright storage-state file for the Site Editor check. Without it
// that check is skipped, which the PR records as an orchestrator-ruled deferral:
// the stack can't mint a login state (a WP-CLI auth cookie was refused in W4).
import { test, expect } from '@playwright/test';

const ARCHIVE = ( process.env.CR_JAM_ARCHIVE_PATH || '/kind/jam/' ).replace( /\/?$/, '/' );
const STREAM_PATHS = ( process.env.CR_JAM_STREAM_PATHS || '/stream/,/' ).split( ',' ).map( ( value ) => value.trim() ).filter( Boolean );
const STATE = process.env.CR_JAM_STATE || 'full';
const REQUIRE_SEED = '1' === process.env.CR_JAM_REQUIRE_SEED;
const EDITOR_COOKIE = process.env.CR_STORAGE_STATE || '';
const BASE_ORIGIN = new URL( process.env.CR_BASE_URL || 'http://localhost:8892' ).origin;
const slugOf = ( key ) => `seed-235-${ key.toLowerCase() }`;
const archivePath = ( pageNumber ) => 1 === pageNumber ? ARCHIVE : `${ ARCHIVE }page/${ pageNumber }/`;
const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );
const VIOLET = 'rgb(36, 28, 74)';
const SKY = 'rgb(142, 202, 230)';
const LEGACY = [ 'rgb(63, 47, 122)', 'rgb(176, 160, 240)', 'rgb(203, 195, 236)' ];

test.describe.configure( { mode: 'parallel' } );

test.beforeEach( ( {}, testInfo ) => {
	test.skip( 'full' !== STATE && ! testInfo.title.startsWith( 'state ' ), `CR_JAM_STATE=${ STATE } runs only state checks` );
} );

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_JAM_REQUIRE_SEED=1: ${ reason }` );
	}
	test.skip( missing, reason );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 390 ? 900 : 1000 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function seeded( page, path = ARCHIVE, width = 1440 ) {
	const response = await open( page, path, width );
	skipUnlessSeeded( response?.status() === 404, `no page at ${ path }` );
	skipUnlessSeeded( 0 === await page.locator( 'a[href*="seed-235-"]' ).count(), 'issue 235 fixtures are not seeded' );
	return response;
}

async function fixtureLink( request, key ) {
	const response = await request.get( `/wp-json/wp/v2/posts?slug=${ slugOf( key ) }&_fields=link,date,title,meta` );
	if ( ! response.ok() ) {
		return null;
	}
	const posts = await response.json();
	if ( posts[ 0 ] ) {
		return posts[ 0 ];
	}
	const fallback = await request.get( '/wp-json/wp/v2/posts?per_page=100&_fields=link,date,title,meta' );
	if ( ! fallback.ok() ) {
		return null;
	}
	return ( await fallback.json() ).find( ( post ) => new RegExp( `^${ slugOf( key ) }(?:-\\d+)?$` ).test( new URL( post.link ).pathname.replace( /\/$/, '' ).split( '/' ).pop() ) ) || null;
}

async function openSingle( page, request, key, width = 1440 ) {
	const fixture = await fixtureLink( request, key );
	skipUnlessSeeded( ! fixture?.link, `fixture ${ key } is absent from public REST` );
	const url = new URL( fixture.link );
	const response = await open( page, `${ url.pathname }${ url.search }`, width );
	skipUnlessSeeded( response?.status() === 404, `fixture ${ key } returned 404` );
	return fixture;
}

// The shipped plugin normally exposes kind at /wp/v2/kind. A custom REST base
// is discovered from /wp/v2/taxonomies; only a disabled controller falls back
// to the seed contract's 28 / 12,12,4 expectation.
async function databaseMap( request ) {
	let termResponse = await request.get( '/wp-json/wp/v2/kind?slug=jam&_fields=id' );
	if ( ! termResponse.ok() ) {
		const taxonomiesResponse = await request.get( '/wp-json/wp/v2/taxonomies' );
		if ( taxonomiesResponse.ok() ) {
			const restBase = ( await taxonomiesResponse.json() )?.kind?.rest_base;
			if ( restBase ) {
				termResponse = await request.get( `/wp-json/wp/v2/${ restBase }?slug=jam&_fields=id` );
			}
		}
	}
	if ( ! termResponse.ok() ) {
		return { fallback: true, total: 28, pages: [ 12, 12, 4 ], posts: [] };
	}
	const terms = await termResponse.json();
	if ( ! terms[ 0 ]?.id ) {
		return { fallback: true, total: 28, pages: [ 12, 12, 4 ], posts: [] };
	}
	const response = await request.get( `/wp-json/wp/v2/posts?kind=${ terms[ 0 ].id }&per_page=100&orderby=date&order=desc&_fields=id,link,date,title,meta` );
	if ( ! response.ok() ) {
		return { fallback: true, total: 28, pages: [ 12, 12, 4 ], posts: [] };
	}
	const posts = await response.json();
	return { fallback: false, total: Number( response.headers()[ 'x-wp-total' ] || posts.length ), pages: Array.from( { length: Math.ceil( posts.length / 12 ) }, ( _, index ) => posts.slice( index * 12, index * 12 + 12 ).length ), posts };
}

const itemFor = ( page, key ) => page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();
const stripFor = ( page, key ) => itemFor( page, key ).locator( 'article.k-jam' ).first();

// W3-G2: a rendered anchor has no hidden ancestor; tabindex=-1 is rejected outside hidden data.
const linkReport = ( locator ) => locator.evaluate( ( root ) => {
	const links = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) );
	return {
		hrefs: links.map( ( link ) => link.href ),
		stray: [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) ).length,
	};
} );

const parseRgb = ( value ) => {
	const match = /rgba?\(\s*(\d+)[ ,]+(\d+)[ ,]+(\d+)/.exec( value || '' );
	return match ? [ Number( match[ 1 ] ), Number( match[ 2 ] ), Number( match[ 3 ] ) ] : null;
};
const luminance = ( rgb ) => {
	const values = rgb.map( ( value ) => {
		const channel = value / 255;
		return channel <= 0.03928 ? channel / 12.92 : ( ( channel + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * values[ 0 ] + 0.7152 * values[ 1 ] + 0.0722 * values[ 2 ];
};
const contrast = ( foreground, background ) => {
	const values = [ luminance( parseRgb( foreground ) ), luminance( parseRgb( background ) ) ].sort( ( a, b ) => b - a );
	return ( values[ 0 ] + 0.05 ) / ( values[ 1 ] + 0.05 );
};

async function visibleCornersAreLink( card ) {
	return card.evaluate( ( root ) => {
		const link = root.querySelector( 'a[href]' );
		const box = root.getBoundingClientRect();
		return [ [ 0.12, 0.2 ], [ 0.88, 0.2 ], [ 0.12, 0.8 ], [ 0.88, 0.8 ] ].map( ( [ x, y ] ) => document.elementFromPoint( box.left + box.width * x, box.top + box.height * y )?.closest( 'a' ) === link );
	} );
}

async function findOnStream( page, key ) {
	for ( let at = 1; at <= 10; at++ ) {
		const response = await open( page, `/stream/?query-1-page=${ at }`, 1440 );
		if ( response?.status() === 404 ) {
			break;
		}
		const item = itemFor( page, key );
		if ( await item.count() ) {
			return item;
		}
	}
	return null;
}

test.describe( 'archive structure', () => {
	test( 'archive reads the total and 12, 12, 4 pages from public REST', async ( { page, request } ) => {
		const map = await databaseMap( request );
		expect( map.total ).toBe( 28 );
		expect( map.pages ).toEqual( [ 12, 12, 4 ] );
		for ( const [ index, count ] of map.pages.entries() ) {
			await seeded( page, archivePath( index + 1 ) );
			expect( await page.locator( '.cr-jukebox li.wp-block-post' ).count(), `archive page ${ index + 1 }` ).toBe( count );
		}
		expect( ( await open( page, archivePath( 4 ) ) )?.status() ).toBe( 404 );
	} );

	test( 'archive identity is Jam with three inert tiles, the stored description and divider', async ( { page } ) => {
		await seeded( page );
		const h1 = page.locator( 'main h1' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1 ).toContainText( 'Jam' );
		const tiles = h1.locator( '.cr-cutout__tile' );
		await expect( tiles ).toHaveCount( 3 );
		expect( ( await tiles.allTextContents() ).join( '' ) ).toBe( 'Jam' );
		await expect( h1.locator( '.cr-cutout__tiles' ) ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( page.locator( '.wp-block-term-description' ) ).toContainText( 'Current music highlight' );
		await expect( page.locator( 'hr.is-style-cr-marker-bar-short' ) ).toHaveCount( 1 );
	} );

	test( 'archive page one orders the H1, one Now Playing item, then month sections', async ( { page } ) => {
		await seeded( page );
		const outline = await page.locator( 'main h1 .cr-cutout__text, main h1:not(:has(.cr-cutout__text)), .cr-jukebox h2' ).allTextContents(); // the cutout tiles beside the text are aria-hidden
		expect( outline[ 0 ].trim() ).toBe( 'Jam' );
		expect( outline[ 1 ].trim() ).toBe( 'Now Playing' );
		await expect( page.locator( 'section.pkiw-lead[data-pkiw-lead="1"] li.wp-block-post' ) ).toHaveCount( 1 );
		await expect( page.locator( 'section.pkiw-lead' ) ).toContainText( 'Midnight Signal' );
		await expect( page.locator( 'section.pkiw-group' ).first() ).toBeVisible();
		for ( const pageNumber of [ 2, 3 ] ) {
			await seeded( page, archivePath( pageNumber ) );
			await expect( page.locator( 'section.pkiw-lead' ) ).toHaveCount( 0 );
		}
	} );

	test( 'archive Now Playing post appears nowhere else on page one and month headings do not repeat within a page', async ( { page } ) => {
		await seeded( page );
		await expect( page.locator( `a[href*="${ slugOf( 'J01' ) }"]` ) ).toHaveCount( 1 );
		for ( const pageNumber of [ 1, 2, 3 ] ) {
			await seeded( page, archivePath( pageNumber ) );
			const headings = ( await page.locator( '.pkiw-group__heading' ).allTextContents() ).map( ( text ) => text.trim() );
			expect( new Set( headings ).size, `page ${ pageNumber } month labels` ).toBe( headings.length );
		}
		await seeded( page );
		const lastPageOne = ( await page.locator( '.pkiw-group__heading' ).last().textContent() ).trim();
		await seeded( page, archivePath( 2 ) );
		expect( ( await page.locator( '.pkiw-group__heading' ).first().textContent() ).trim() ).toBe( lastPageOne );
	} );

	test( 'archive J05 files under its post month while its single Date row uses the next month', async ( { page, request } ) => {
		await seeded( page );
		const sectionHeading = await itemFor( page, 'J05' ).locator( 'xpath=ancestor::section[contains(@class,"pkiw-group")][1]/h2' ).textContent();
		await openSingle( page, request, 'J05' );
		const date = await page.locator( '.cr-jcard__row' ).filter( { hasText: /^Date/ } ).locator( 'time' ).getAttribute( 'datetime' );
		expect( sectionHeading ).not.toContain( date.slice( 0, 7 ) );
		expect( new Date( `${ date }T12:00:00` ).getUTCMonth() ).toBe( ( new Date( `${ sectionHeading.trim() } 1` ).getUTCMonth() + 1 ) % 12 );
	} );
} );

test.describe( 'archive cards and interaction', () => {
	test( 'archive each item has one rendered link, one tab stop and a title-then-artist name', async ( { page } ) => {
		for ( const pageNumber of [ 1, 2, 3 ] ) {
			await seeded( page, archivePath( pageNumber ) );
			const items = page.locator( '.cr-jukebox li.wp-block-post' );
			for ( let index = 0; index < await items.count(); index++ ) {
				const item = items.nth( index );
				const report = await linkReport( item );
				expect( report.hrefs, `page ${ pageNumber} item ${ index + 1 }` ).toHaveLength( 1 );
				expect( report.stray ).toBe( 0 );
			}
			expect( await page.locator( '.cr-jukebox__list' ).evaluate( ( root ) => [ ...root.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]' ) ].filter( ( node ) => ! node.closest( '[hidden]' ) && node.tabIndex >= 0 ).length ) ).toBe( await items.count() );
		}
		await seeded( page, archivePath( 3 ) );
		const artistless = stripFor( page, 'J25' ).locator( 'a.u-url' );
		await expect( artistless ).toHaveAccessibleName( 'Glass Map' );
		await seeded( page );
		await expect( stripFor( page, 'J02' ).locator( 'a.u-url' ) ).toHaveAccessibleName( 'Paper Satellites The Quiet Orchards' );
	} );

	test( 'archive cards expose only cover, title and artist', async ( { page } ) => {
		for ( const pageNumber of [ 1, 2, 3 ] ) {
			await seeded( page, archivePath( pageNumber ) );
			for ( const card of await page.locator( '.cr-jukebox article.k-jam' ).all() ) {
				expect( await card.locator( '.pk-album, time, .pk-note, .pk-link, iframe, audio, video, script' ).count() ).toBe( 0 );
				expect( await card.innerText() ).not.toMatch( /Jammed|Signal Bloom|Public feed note|player/i );
			}
		}
	} );

	test( 'archive selection codes stay out of text and the accessibility tree and never mark Now Playing', async ( { page } ) => {
		for ( const pageNumber of [ 1, 2, 3 ] ) {
			await seeded( page, archivePath( pageNumber ) );
			expect( await page.locator( '.cr-jukebox' ).innerText() ).not.toMatch( /\b[A-Z]\d+\b/ );
			expect( await page.locator( '.cr-jukebox' ).ariaSnapshot() ).not.toMatch( /\b[A-Z]\d+\b/ );
		}
		await seeded( page );
		expect( await page.locator( '.pkiw-lead .cr-jam-strip--listing' ).evaluate( ( node ) => getComputedStyle( node, '::before' ).content ) ).toMatch( /^(none|normal|"")$/ ); // the plugin's empty .pk-card::before box carries no code text
	} );

	test( 'archive grid is 3, 2, 1 and 1 columns at 1440, 800, 375 and 320', async ( { page } ) => {
		for ( const [ width, expected ] of [ [ 1440, 3 ], [ 800, 2 ], [ 375, 1 ], [ 320, 1 ] ] ) {
			await seeded( page, ARCHIVE, width );
			const columns = await page.locator( '.pkiw-group__items' ).first().evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length );
			expect( columns, `${ width }px` ).toBe( expected );
		}
	} );

	test( 'archive J19 wraps with no horizontal scroll at 320 and the 400 percent reflow width', async ( { page } ) => {
		await seeded( page, archivePath( 2 ), 320 );
		const title = stripFor( page, 'J19' ).locator( '.pk-title' );
		const size = await title.evaluate( ( node ) => ( { scroll: node.scrollWidth, client: node.clientWidth, overflow: getComputedStyle( node ).overflowWrap } ) );
		expect( size.scroll ).toBeLessThanOrEqual( size.client + 1 );
		expect( size.overflow ).toBe( 'anywhere' );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		await seeded( page, archivePath( 2 ), 320 );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
	} );

	test( 'archive headings, strips, covers and pager are upright at rest with at most two rotated layers', async ( { page } ) => {
		await seeded( page );
		const transforms = await page.locator( '.cr-jukebox h2, .cr-jukebox h3, .cr-jam-strip--listing, .cr-jam-strip--listing img, nav[aria-label="Pagination"]' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => getComputedStyle( node ).transform ) );
		expect( transforms.every( ( value ) => 'none' === value ) ).toBe( true );
		const rotations = await page.locator( '.cr-jukebox, .cr-jukebox *' ).evaluateAll( ( nodes ) => nodes.flatMap( ( node ) => [ getComputedStyle( node ).transform, getComputedStyle( node, '::before' ).transform, getComputedStyle( node, '::after' ).transform ] ).filter( ( value ) => 'none' !== value ).length );
		expect( rotations ).toBeLessThanOrEqual( 2 );
	} );

	test( 'archive pager has correct edge links', async ( { page } ) => {
		for ( const [ pageNumber, previous, next ] of [ [ 1, 0, 1 ], [ 2, 1, 1 ], [ 3, 1, 0 ] ] ) {
			await seeded( page, archivePath( pageNumber ) );
			const pager = page.locator( 'nav[aria-label="Pagination"]' );
			await expect( pager ).toHaveCount( 1 );
			await expect( pager.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( previous );
			await expect( pager.locator( '.wp-block-query-pagination-next' ) ).toHaveCount( next );
		}
	} );

	test( 'archive cover loading makes the lead high-priority, the next three eager and later covers lazy', async ( { page } ) => {
		await seeded( page );
		const cards = page.locator( '.cr-jukebox li.wp-block-post' );
		for ( let index = 0; index < await cards.count(); index++ ) {
			const image = cards.nth( index ).locator( 'img.pk-cover' );
			if ( ! await image.count() ) {
				continue;
			}
			expect( await image.getAttribute( 'loading' ), `card ${ index + 1 }` ).toBe( index < 4 ? 'eager' : 'lazy' );
			expect( await image.getAttribute( 'fetchpriority' ) ).toBe( 0 === index ? 'high' : null );
		}
	} );

	test( 'archive local failed covers J17 and J18 leave no broken image or empty frame', async ( { page } ) => {
		test.skip( 'dev' === process.env.CR_JAM_ENV, 'remote covers are intentionally empty on dev' );
		await seeded( page, archivePath( 2 ) );
		for ( const key of [ 'J17', 'J18' ] ) {
			const card = stripFor( page, key );
			await expect.poll( async () => card.locator( 'img.pk-cover' ).count() ).toBe( 0 );
			// The shared cover fallback removes the image and hides its frame with cr-cover--failed.
			await expect( card.locator( '.pk-media:not(.cr-cover--failed)' ) ).toHaveCount( 0 );
			expect( await card.locator( '.pk-media' ).evaluateAll( ( nodes ) => nodes.every( ( node ) => 'none' === getComputedStyle( node ).display ) ) ).toBe( true );
		}
	} );

	test( 'archive focus rings the whole strip with 3px and a 7 or 8px halo', async ( { page } ) => {
		await seeded( page );
		const card = stripFor( page, 'J02' );
		await card.locator( 'a' ).focus();
		const ring = await card.evaluate( ( node ) => ( { width: getComputedStyle( node ).outlineWidth, shadow: getComputedStyle( node ).boxShadow } ) );
		expect( ring.width ).toBe( '3px' );
		expect( ring.shadow ).toMatch( /(?:7|8)px/ );
		expect( await visibleCornersAreLink( card ) ).toEqual( [ true, true, true, true ] );
	} );

	test( 'archive forced colors uses 2px outlines and hides doodle layers', async ( { page } ) => {
		await seeded( page );
		await page.emulateMedia( { forcedColors: 'active' } );
		const report = await page.evaluate( () => {
			const cabinet = document.querySelector( '.pkiw-grouped' );
			const card = document.querySelector( '.cr-jam-strip--listing' );
			const archive = document.querySelector( '.cr-archive--jam' );
			return { cabinet: `${ getComputedStyle( cabinet ).borderTopWidth } ${ getComputedStyle( cabinet ).borderTopStyle }`, card: `${ getComputedStyle( card ).borderTopWidth } ${ getComputedStyle( card ).borderTopStyle }`, art: [ getComputedStyle( archive, '::before' ).display, getComputedStyle( archive, '::after' ).display ] };
		} );
		expect( report.cabinet ).toBe( '2px solid' );
		expect( report.card ).toBe( '2px solid' );
		expect( report.art ).toEqual( [ 'none', 'none' ] );
	} );

	test( 'archive dark text pairs hold 4.5 to 1', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		await seeded( page );
		const pairs = await page.locator( '.pkiw-lead__heading, .pkiw-group__heading, .cr-jam-strip--listing .pk-title, .cr-jam-strip--listing .pk-sub' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const style = getComputedStyle( node );
			const background = node.classList.contains( 'pkiw-lead__heading' )
				? getComputedStyle( node, '::before' ).backgroundColor
				: ( 'rgba(0, 0, 0, 0)' === style.backgroundColor ? getComputedStyle( node.closest( 'article, h2' ) ).backgroundColor : style.backgroundColor );
			return [ style.color, background ];
		} ) );
		pairs.push( ...await page.locator( '.pkiw-group__items .cr-jam-strip--listing' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => [ getComputedStyle( node, '::before' ).color, getComputedStyle( node, '::before' ).backgroundColor ] ) ) );
		for ( const pair of pairs ) {
			expect( contrast( pair[ 0 ], pair[ 1 ] ), pair.join( ' on ' ) ).toBeGreaterThanOrEqual( 4.5 );
		}
	} );

	test( 'archive reduced motion removes strip transitions', async ( { page } ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await seeded( page );
		const card = page.locator( '.cr-jam-strip--listing' ).first();
		expect( await card.evaluate( ( node ) => Math.max( ...getComputedStyle( node ).transitionDuration.split( ',' ).map( Number.parseFloat ) ) ) ).toBeLessThanOrEqual( 0.000001 );
	} );

	test( 'archive tab walk visits one loop stop per item in DOM order', async ( { page } ) => {
		await seeded( page );
		const expected = await page.locator( '.cr-jukebox li.wp-block-post a[href]' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => ! node.closest( '[hidden]' ) ).map( ( node ) => node.href ) );
		await page.locator( '.cr-jukebox' ).evaluate( ( node ) => { node.tabIndex = -1; node.focus(); } );
		const visited = [];
		for ( let count = 0; count < expected.length; count++ ) {
			await page.keyboard.press( 'Tab' );
			visited.push( await page.evaluate( () => document.activeElement?.href || '' ) );
		}
		expect( visited ).toEqual( expected );
	} );

	test( 'archive page one makes no request outside the site origin', async ( { page } ) => {
		const foreign = [];
		page.on( 'request', ( request ) => {
			if ( new URL( request.url() ).origin !== BASE_ORIGIN && ! request.url().startsWith( 'data:' ) ) {
				foreign.push( request.url() );
			}
		} );
		await seeded( page );
		expect( foreign ).toEqual( [] );
	} );
} );

test.describe( 'archive Site Editor preview', () => {
	test.use( EDITOR_COOKIE ? { storageState: EDITOR_COOKIE } : {} );
	test( 'archive editor preview renders Now Playing and month headings with twelve lines', async ( { page } ) => {
		test.skip( ! EDITOR_COOKIE, 'CR_STORAGE_STATE is not set' );
		await page.goto( '/wp-admin/site-editor.php?postType=wp_template&postId=courtneyr-child%2F%2Ftaxonomy-kind-jam&canvas=edit', { waitUntil: 'load' } );
		const frame = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect( frame.locator( '.pkiw-lead__heading' ) ).toHaveText( 'Now Playing' );
		await expect( frame.locator( '.pkiw-group__heading' ).first() ).toBeVisible();
		await expect( frame.locator( 'li.wp-block-post' ) ).toHaveCount( 12 );
	} );

	// The lead label and the page size are editor-editable (decision line 65). The edits stay in the
	// unsaved editor state: the test never saves the template, and a reload discards them.
	test( 'archive editor lets the lead label and page size change and the preview follows', async ( { page } ) => {
		test.skip( ! EDITOR_COOKIE, 'CR_STORAGE_STATE is not set' );
		await page.goto( '/wp-admin/site-editor.php?postType=wp_template&postId=courtneyr-child%2F%2Ftaxonomy-kind-jam&canvas=edit', { waitUntil: 'load' } );
		await page.waitForFunction( () => {
			const store = window.wp?.data?.select( 'core/block-editor' );
			return Boolean( store && store.getBlocks().length > 0 );
		} );
		const edited = await page.evaluate( () => {
			const flat = ( blocks ) => blocks.flatMap( ( block ) => [ block, ...flat( block.innerBlocks ) ] );
			const all = flat( window.wp.data.select( 'core/block-editor' ).getBlocks() );
			const marker = all.find( ( block ) => 'post-kinds-indieweb/archive-sections' === block.name );
			const query = all.find( ( block ) => 'core/query' === block.name );
			if ( ! marker || ! query ) {
				return null;
			}
			const dispatch = window.wp.data.dispatch( 'core/block-editor' );
			dispatch.updateBlockAttributes( marker.clientId, { leadLabel: 'Playing Now', linesPerPage: 6 } );
			dispatch.updateBlockAttributes( query.clientId, { query: { ...query.attributes.query, perPage: 6 } } );
			const after = flat( window.wp.data.select( 'core/block-editor' ).getBlocks() );
			return {
				leadLabel: after.find( ( block ) => block.clientId === marker.clientId ).attributes.leadLabel,
				linesPerPage: after.find( ( block ) => block.clientId === marker.clientId ).attributes.linesPerPage,
				perPage: after.find( ( block ) => block.clientId === query.clientId ).attributes.query.perPage,
			};
		} );
		expect( edited ).toEqual( { leadLabel: 'Playing Now', linesPerPage: 6, perPage: 6 } );
		const frame = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect( frame.locator( '.pkiw-lead__heading' ) ).toHaveText( 'Playing Now' );
		await expect( frame.locator( 'li.wp-block-post' ) ).toHaveCount( 6 );
	} );
} );

test.describe( 'state variants', () => {
	test( 'state one keeps only Now Playing in the cabinet', async ( { page } ) => {
		test.skip( 'one' !== STATE, 'CR_JAM_STATE is not one' );
		await seeded( page );
		await expect( page.locator( 'section.pkiw-lead li.wp-block-post' ) ).toHaveCount( 1 );
		await expect( page.locator( 'section.pkiw-group' ) ).toHaveCount( 0 );
	} );

	test( 'state empty has no cabinet and keeps editable no-results copy', async ( { page } ) => {
		test.skip( 'empty' !== STATE, 'CR_JAM_STATE is not empty' );
		await open( page, ARCHIVE );
		await expect( page.locator( '.pkiw-grouped' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-archive-stream__empty' ) ).toHaveText( 'No jams yet.' );
	} );
} );

test.describe( 'stream cards', () => {
	for ( const path of STREAM_PATHS ) {
		test( `stream ${ path } shows J01-J03 as compact one-link cards`, async ( { page } ) => {
			await open( page, path );
			for ( const key of [ 'J01', 'J02', 'J03' ] ) {
				const card = stripFor( page, key );
				skipUnlessSeeded( 0 === await card.count(), `${ key } is absent from ${ path }` );
				await expect( card ).toHaveClass( /pk-card--jam-stream/ );
				await expect( card ).toHaveClass( /cr-jam-strip--stream/ );
				const report = await linkReport( card );
				expect( report.hrefs ).toHaveLength( 1 );
				expect( report.stray ).toBe( 0 );
				expect( await card.locator( 'img, .pk-note, .pk-album, iframe, audio, video, script' ).count() ).toBe( 0 );
				expect( await card.innerText() ).not.toMatch( /Signal Bloom|A1|B1/ );
				expect( await card.innerText() ).not.toMatch( /\b[A-Z]\d+\b/ );
			}
		} );
	}

	test( 'stream whole card is the hit area and uses the stored jam day or post day', async ( { page, request } ) => {
		await open( page, STREAM_PATHS[ 0 ] );
		for ( const key of [ 'J01', 'J02', 'J03' ] ) {
			const card = stripFor( page, key );
			skipUnlessSeeded( 0 === await card.count(), `${ key } is absent from Stream` );
			expect( await visibleCornersAreLink( card ) ).toEqual( [ true, true, true, true ] );
			const fixture = await fixtureLink( request, key );
			const expectedDate = fixture.date.slice( 0, 10 );
			expect( await card.locator( 'time' ).getAttribute( 'datetime' ) ).toBe( expectedDate );
			await expect( card.locator( 'time' ) ).toHaveText( new Intl.DateTimeFormat( 'en-US', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' } ).format( new Date( `${ expectedDate }T12:00:00Z` ) ) );
		}
	} );

	test( 'stream J22 has no p-name while J23 has one', async ( { page } ) => {
		const j22 = await findOnStream( page, 'J22' );
		skipUnlessSeeded( null === j22, 'J22 was not found within ten Stream pages' );
		expect( await j22.locator( 'a.u-url.p-name' ).count() ).toBe( 0 );
		const j23 = await findOnStream( page, 'J23' );
		skipUnlessSeeded( null === j23, 'J23 was not found within ten Stream pages' );
		expect( await j23.locator( 'a.u-url.p-name' ).count() ).toBe( 1 );
	} );

	test( 'stream uses sky blue, violet and no legacy color', async ( { page }, testInfo ) => {
		test.skip( isDark( testInfo ), 'light-project assertion' );
		await open( page, STREAM_PATHS[ 0 ] );
		const card = stripFor( page, 'J01' );
		skipUnlessSeeded( 0 === await card.count(), 'J01 is absent from Stream' );
		const paint = await card.evaluate( ( node ) => {
			const values = [ ...node.querySelectorAll( '*' ), node ].flatMap( ( child ) => [ getComputedStyle( child ).color, getComputedStyle( child ).backgroundColor, getComputedStyle( child ).borderTopColor ] );
			return { background: getComputedStyle( node ).backgroundColor, title: getComputedStyle( node.querySelector( '.pk-title' ) ).color, values };
		} );
		expect( paint.background ).toBe( SKY );
		expect( paint.title ).toBe( VIOLET );
		for ( const color of LEGACY ) {
			expect( paint.values ).not.toContain( color );
		}
	} );

	test( 'stream has at most two rotated layers and no horizontal scroll at 320', async ( { page } ) => {
		await open( page, STREAM_PATHS[ 0 ], 320 );
		const card = stripFor( page, 'J01' );
		skipUnlessSeeded( 0 === await card.count(), 'J01 is absent from Stream' );
		const rotations = await card.evaluate( ( node ) => [ getComputedStyle( node ).transform, getComputedStyle( node, '::before' ).transform, getComputedStyle( node, '::after' ).transform ].filter( ( value ) => 'none' !== value ).length );
		expect( rotations ).toBeLessThanOrEqual( 2 );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
	} );

	test( 'stream dark and forced colors keep readable cards', async ( { page }, testInfo ) => {
		await open( page, STREAM_PATHS[ 0 ] );
		const card = stripFor( page, 'J01' );
		skipUnlessSeeded( 0 === await card.count(), 'J01 is absent from Stream' );
		if ( isDark( testInfo ) ) {
			const pairs = await card.locator( '.pk-kindlabel, .pk-title, .pk-sub' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => {
				const style = getComputedStyle( node );
				return [ style.color, 'rgba(0, 0, 0, 0)' === style.backgroundColor ? getComputedStyle( node.closest( 'article' ) ).backgroundColor : style.backgroundColor ];
			} ) );
			for ( const pair of pairs ) {
				expect( contrast( pair[ 0 ], pair[ 1 ] ), pair.join( ' on ' ) ).toBeGreaterThanOrEqual( 4.5 );
			}
		}
		await page.emulateMedia( { forcedColors: 'active' } );
		expect( await card.evaluate( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }` ) ).toBe( '2px solid' );
		expect( await card.evaluate( ( node ) => getComputedStyle( node, '::before' ).display ) ).toBe( 'none' );
	} );

	test( 'stream focus rings the whole strip and reduced motion removes transitions', async ( { page } ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( page, STREAM_PATHS[ 0 ] );
		const card = stripFor( page, 'J01' );
		skipUnlessSeeded( 0 === await card.count(), 'J01 is absent from Stream' );
		await card.locator( 'a' ).focus();
		const report = await card.evaluate( ( node ) => ( {
			outline: getComputedStyle( node ).outlineWidth,
			transition: Math.max( ...getComputedStyle( node ).transitionDuration.split( ',' ).map( Number.parseFloat ) ),
		} ) );
		expect( report.outline ).toBe( '3px' );
		expect( report.transition ).toBeLessThanOrEqual( 0.000001 );
	} );

	test( 'stream pages make no request outside the site origin', async ( { page } ) => {
		for ( const path of STREAM_PATHS ) {
			const foreign = [];
			page.on( 'request', ( request ) => {
				// Stored front-page content on the local database still points at the legacy *.myftpupload.com host; no jam card prints an image or iframe (asserted above).
				if ( new URL( request.url() ).origin !== BASE_ORIGIN && ! request.url().startsWith( 'data:' ) && ! /\.myftpupload\.com$/.test( new URL( request.url() ).hostname ) ) {
					foreign.push( request.url() );
				}
			} );
			await open( page, path );
			expect( foreign ).toEqual( [] );
		}
	} );
} );

test.describe( 'single J-card', () => {
	test( 'single fixture matrix has exactly one H1 and the expected card shape', async ( { page, request } ) => {
		for ( const key of [ 'J01', 'J05', 'J06', 'J07', 'J08', 'J09', 'J10', 'J11', 'J12', 'J13', 'J14', 'J15', 'J16', 'J19', 'J20', 'J21', 'J22', 'J23' ] ) {
			await openSingle( page, request, key );
			await expect( page.locator( 'main h1' ), key ).toHaveCount( 1 );
			const card = page.locator( 'article.cr-jcard' );
			const expectedCards = [ 'J22', 'J23' ].includes( key ) ? 0 : 1;
			await expect( card, key ).toHaveCount( expectedCards );
			if ( expectedCards ) {
				expect( ( await card.locator( '.cr-jcard__row dd' ).allTextContents() ).every( ( value ) => '' !== value.trim() ), key ).toBe( true );
				expect( await card.innerText(), key ).not.toMatch( /(?:Not set|Unknown|—)/i );
			}
		}
	} );

	test( 'single J09 has one H1 and the four panels in DOM order', async ( { page, request } ) => {
		await openSingle( page, request, 'J09' );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		const card = page.locator( 'article.cr-jcard' );
		const order = await card.evaluate( ( node ) => [ ...node.children ].map( ( child ) => child.className.split( ' ' ).find( ( name ) => name.startsWith( 'cr-jcard__' ) ) ) );
		expect( order ).toEqual( [ 'cr-jcard__insert', 'cr-jcard__details', 'cr-jcard__spine', 'cr-jcard__note' ] );
		await expect( card.locator( 'img.u-photo' ) ).toHaveCount( 1 );
	} );

	test( 'single responsive panel order is one row, two plus note, then stacked', async ( { page, request } ) => {
		for ( const width of [ 1280, 800, 375, 320 ] ) {
			await openSingle( page, request, 'J09', width );
			const boxes = await page.locator( '.cr-jcard > [class*="cr-jcard__"]' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { name: [ ...node.classList ].find( ( value ) => value.startsWith( 'cr-jcard__' ) ), box: node.getBoundingClientRect().toJSON(), display: getComputedStyle( node ).display } ) ) );
			const visible = boxes.filter( ( item ) => 'none' !== item.display );
			if ( width >= 1024 ) {
				expect( new Set( visible.map( ( item ) => Math.round( item.box.top ) ) ).size ).toBe( 1 );
				expect( visible.map( ( item ) => item.name ) ).toEqual( [ 'cr-jcard__insert', 'cr-jcard__details', 'cr-jcard__spine', 'cr-jcard__note' ] );
			} else if ( width >= 640 ) {
				expect( visible[ 0 ].box.top ).toBe( visible[ 1 ].box.top );
				expect( visible[ 2 ].box.top ).toBeGreaterThanOrEqual( visible[ 0 ].box.bottom );
				expect( visible.map( ( item ) => item.name ) ).toEqual( [ 'cr-jcard__insert', 'cr-jcard__details', 'cr-jcard__note' ] );
			} else {
				expect( visible.map( ( item ) => item.name ) ).toEqual( [ 'cr-jcard__insert', 'cr-jcard__details', 'cr-jcard__note' ] );
				expect( visible[ 1 ].box.top ).toBeGreaterThanOrEqual( visible[ 0 ].box.bottom );
				expect( visible[ 2 ].box.top ).toBeGreaterThanOrEqual( visible[ 1 ].box.bottom );
			}
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		}
	} );

	// Absent panels must not shift the rest: J16 has no cover and no note, J26 has no cover but a note.
	test( 'single without a cover keeps every panel in its own track (J16 and J26)', async ( { page, request } ) => {
		for ( const [ key, expected ] of [
			[ 'J16', [ 'cr-jcard__details', 'cr-jcard__spine' ] ],
			[ 'J26', [ 'cr-jcard__details', 'cr-jcard__spine', 'cr-jcard__note' ] ],
		] ) {
			for ( const width of [ 1280, 800, 375, 320 ] ) {
				await openSingle( page, request, key, width );
				await expect( page.locator( '.cr-jcard__insert' ) ).toHaveCount( 0 );
				const boxes = await page.locator( '.cr-jcard > [class*="cr-jcard__"]' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( { name: [ ...node.classList ].find( ( value ) => value.startsWith( 'cr-jcard__' ) ), box: node.getBoundingClientRect().toJSON(), display: getComputedStyle( node ).display } ) ) );
				const visible = boxes.filter( ( item ) => 'none' !== item.display );
				// Panels fill the card's content box (the card has its own side padding), so compare against that, not the border box.
				const card = await page.locator( '.cr-jcard' ).evaluate( ( node ) => {
					const style = getComputedStyle( node );
					return { width: node.clientWidth - Number.parseFloat( style.paddingLeft ) - Number.parseFloat( style.paddingRight ) };
				} );
				if ( width >= 1024 ) {
					expect( visible.map( ( item ) => item.name ) ).toEqual( expected );
					expect( new Set( visible.map( ( item ) => Math.round( item.box.top ) ) ).size ).toBe( 1 );
					const spine = visible.find( ( item ) => 'cr-jcard__spine' === item.name );
					expect( Math.round( spine.box.width ) ).toBeLessThanOrEqual( 84 );
					const details = visible.find( ( item ) => 'cr-jcard__details' === item.name );
					expect( details.box.width ).toBeGreaterThan( spine.box.width * 2 );
					const note = visible.find( ( item ) => 'cr-jcard__note' === item.name );
					if ( note ) {
						expect( note.box.width ).toBeGreaterThan( spine.box.width * 2 );
					}
				} else {
					expect( visible.map( ( item ) => item.name ) ).toEqual( expected.filter( ( name ) => 'cr-jcard__spine' !== name ) );
					for ( const item of visible ) {
						expect( item.box.width ).toBeGreaterThan( card.width * 0.98 );
					}
				}
				expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
			}
		}
	} );

	test( 'single rows omit empty values and Track only appears when it differs from H1', async ( { page, request } ) => {
		await openSingle( page, request, 'J06' );
		await expect( page.locator( '.cr-jcard__row--track' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-jcard__row--track dd' ) ).toContainText( 'Hollow Light' );
		await openSingle( page, request, 'J07' );
		await expect( page.locator( '.cr-jcard__row--track' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-jcard__details > data.p-name[hidden]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-jcard__details > data.u-url.u-jam-of[hidden]' ) ).toHaveCount( 1 );
		expect( await page.locator( '.cr-jcard [hidden] a' ).count() ).toBe( 0 );
		await openSingle( page, request, 'J16' );
		await expect( page.locator( '.cr-jcard__insert, img.u-photo' ) ).toHaveCount( 0 );
	} );

	test( 'single Date label is never Jammed and dt-published exists only for stored jammedAt', async ( { page, request } ) => {
		await openSingle( page, request, 'J01' );
		await expect( page.locator( '.cr-jcard__row' ).filter( { hasText: /^Date/ } ).locator( 'time.dt-published' ) ).toHaveCount( 1 );
		expect( await page.locator( '.cr-jcard' ).innerText() ).not.toContain( 'Jammed' );
		await openSingle( page, request, 'J08' );
		await expect( page.locator( '.cr-jcard__row' ).filter( { hasText: /^Date/ } ).locator( 'time:not(.dt-published)' ) ).toHaveCount( 1 );
	} );

	test( 'single cover alt, rel and URL omissions match the fixture data', async ( { page, request } ) => {
		await openSingle( page, request, 'J11' );
		for ( const rel of await page.locator( '.cr-jcard a' ).evaluateAll( ( links ) => links.map( ( link ) => link.rel ) ) ) {
			expect( rel.split( /\s+/ ) ).toEqual( expect.arrayContaining( [ 'noopener', 'noreferrer', 'nofollow' ] ) );
		}
		await openSingle( page, request, 'J12' );
		await expect( page.locator( 'img.u-photo' ) ).toHaveAttribute( 'alt', 'Abstract blue steps beneath an orange moon' );
		await openSingle( page, request, 'J13' );
		await expect( page.locator( 'img.u-photo' ) ).toHaveAttribute( 'alt', 'Quiet Mechanism by Soft Current' );
		await openSingle( page, request, 'J21' );
		await expect( page.locator( '.cr-jcard a.pk-link, .cr-jcard data.u-jam-of' ) ).toHaveCount( 0 );
	} );

	test( 'single J14 suppresses the repeated featured slot while J23 keeps it', async ( { page, request } ) => {
		await openSingle( page, request, 'J14' );
		await expect( page.locator( '.wp-block-post-featured-image' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-jcard img.u-photo' ) ).toHaveCount( 1 );
		await openSingle( page, request, 'J23' );
		await expect( page.locator( 'article.cr-jcard' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-post-featured-image img' ) ).toHaveCount( 1 );
	} );

	test( 'single J10 has no note panel or blank ruled paper and spine has no text', async ( { page, request } ) => {
		await openSingle( page, request, 'J10' );
		await expect( page.locator( '.cr-jcard__note' ) ).toHaveCount( 0 );
		const spine = page.locator( '.cr-jcard__spine' );
		await expect( spine ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( await spine.evaluate( ( node ) => node.childNodes.length ) ).toBe( 0 );
	} );

	test( 'single local embed prints once after J09 and preserves autoplay=1', async ( { page, request } ) => {
		test.skip( 'dev' === process.env.CR_JAM_ENV, 'positive embed proof is local only' );
		await openSingle( page, request, 'J09' );
		const embed = page.locator( '.cr-jcard + .pk-embed--audio' );
		await expect( embed ).toHaveCount( 1 );
		await expect( embed.locator( 'iframe[src*="autoplay=1"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.pk-embed--audio' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-jcard__note .p-content' ) ).toContainText( 'bright bridge' );
	} );

	test( 'single J24 has no iframe on the card or page', async ( { page, request } ) => {
		await openSingle( page, request, 'J24' );
		await expect( page.locator( 'iframe, .pk-embed--audio' ) ).toHaveCount( 0 );
	} );

	test( 'single card-less J22 and J23 draw no frame and keep their name rules', async ( { page, request } ) => {
		await openSingle( page, request, 'J22' );
		const h1 = page.locator( 'main h1' );
		await expect( h1 ).toHaveText( /^Jam, .+ at .+$/ );
		await expect( h1 ).toHaveClass( /cr-untitled-title/ );
		expect( await h1.evaluate( ( node ) => node.classList.contains( 'p-name' ) ) ).toBe( false );
		await expect( page.locator( '.cr-jcard, .cr-jcard__details' ) ).toHaveCount( 0 );
		await openSingle( page, request, 'J23' );
		await expect( page.locator( 'main h1' ) ).toHaveText( 'Quick Silver by Velvet Parlor' );
		await expect( page.locator( '.cr-jcard' ) ).toHaveCount( 0 );
	} );

	test( 'single long and escaped values stay readable at 320', async ( { page, request } ) => {
		await openSingle( page, request, 'J19', 320 );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		await openSingle( page, request, 'J20' );
		await expect( page.locator( 'main h1' ) ).toHaveText( 'Tea & <Tin>’s Echo' );
	} );

	test( 'single post navigation stays jam-scoped with both neighbours on J02 and no Previous on oldest jam', async ( { page, request } ) => {
		await openSingle( page, request, 'J02' );
		const nav = page.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		await expect( nav.locator( 'a' ) ).toHaveCount( 2 );
		expect( ( await nav.locator( 'a' ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) ) ).every( ( href ) => href.includes( 'seed-235-' ) ) ).toBe( true );
		// Core keeps an empty wrapper for a missing side, so count the link. Previous is the older post and Next the newer one: the oldest public jam (X03) has no Previous, the newest (J01) no Next.
		await openSingle( page, request, 'X03' );
		await expect( page.locator( 'nav.cr-post-nav .post-navigation-link-previous a' ) ).toHaveCount( 0 );
		await expect( page.locator( 'nav.cr-post-nav .post-navigation-link-next a' ) ).toHaveCount( 1 );
		await openSingle( page, request, 'J01' );
		await expect( page.locator( 'nav.cr-post-nav .post-navigation-link-next a' ) ).toHaveCount( 0 );
	} );

	test( 'single Jam link is 44px tall with a 3px focus ring', async ( { page, request } ) => {
		await openSingle( page, request, 'J01' );
		const link = page.locator( '.cr-jcard a.pk-link' );
		expect( ( await link.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		await link.focus();
		expect( await page.locator( '.cr-jcard__details' ).evaluate( ( node ) => getComputedStyle( node ).outlineWidth ) ).toBe( '3px' );
	} );

	test( 'single microformat classes survive on the rebuilt J-card', async ( { page, request } ) => {
		await openSingle( page, request, 'J09' );
		const card = page.locator( 'article.cr-jcard.h-cite' );
		// The author h-card's own p-name belongs to that nested card, not to the h-cite.
		await expect( card.locator( '.p-name:not(.h-card .p-name)' ) ).toHaveCount( 1 );
		await expect( card.locator( '.u-jam-of' ) ).toHaveCount( 1 );
		await expect( card.locator( '.p-author.h-card' ) ).toHaveCount( 1 );
		await expect( card.locator( '.u-photo' ) ).toHaveCount( 1 );
		await expect( card.locator( '.p-content' ) ).toHaveCount( 1 );
		await expect( card.locator( '.dt-published' ) ).toHaveCount( 1 );
	} );

	test( 'single dark, forced colors and reduced motion preserve the panel contract', async ( { page, request }, testInfo ) => {
		await openSingle( page, request, 'J09' );
		if ( isDark( testInfo ) ) {
			const pairs = await page.locator( '.cr-jcard__row dd, .cr-jcard__note .pk-note, .cr-jcard__label' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => [ getComputedStyle( node ).color, getComputedStyle( node.classList.contains( 'cr-jcard__label' ) ? node : node.closest( '.cr-paper-sheet' ) ).backgroundColor ] ) );
			for ( const pair of pairs ) {
				// The Note label is a chip with its own periwinkle ground; the others sit on the paper sheet.
				expect( contrast( pair[ 0 ], pair[ 1 ] ) ).toBeGreaterThanOrEqual( 4.5 );
			}
		}
		await page.emulateMedia( { forcedColors: 'active' } );
		expect( await page.locator( '.cr-jcard__details' ).evaluate( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }` ) ).toBe( '2px solid' );
		expect( await page.locator( '.cr-jcard__spine' ).evaluate( ( node ) => getComputedStyle( node ).display ) ).toBe( 'none' );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		expect( await page.locator( '.cr-jcard' ).evaluate( ( node ) => Math.max( ...getComputedStyle( node ).transitionDuration.split( ',' ).map( Number.parseFloat ) ) ) ).toBeLessThanOrEqual( 0.000001 );
	} );
} );

test.describe( 'cross-kind controls', () => {
	test( 'cross L01 stays on Listen and no jam appears there', async ( { page } ) => {
		await open( page, '/kind/listen/' );
		await expect( page.locator( `a[href*="${ slugOf( 'L01' ) }"]` ) ).toHaveCount( 1 );
		await expect( page.locator( 'article.k-jam, a[href*="seed-235-j"]' ) ).toHaveCount( 0 );
	} );

	test( 'cross X04 never appears on the Jam archive', async ( { page } ) => {
		for ( const pageNumber of [ 1, 2, 3 ] ) {
			await seeded( page, archivePath( pageNumber ) );
			await expect( page.locator( `a[href*="${ slugOf( 'X04' ) }"]` ) ).toHaveCount( 0 );
		}
	} );

	test( 'cross X03 is a title-only protected card with one link', async ( { page } ) => {
		await seeded( page, archivePath( 3 ) );
		const card = stripFor( page, 'X03' );
		await expect( card ).toHaveClass( /pk-card--protected/ );
		expect( ( await linkReport( card ) ).hrefs ).toHaveLength( 1 );
		await expect( card.locator( ':scope > :not(.pk-title):not([hidden])' ) ).toHaveCount( 0 );
	} );
} );
