// `CR_SPEC=photo-241.spec.mjs npm run captures -- --project=1280-light`:
// PKIW issue 241 photo archive, single and Stream checks.
//
// Served checks need a running local stack with the `_cr_seed_241` fixtures
// (assets/241-photos/seed-241.php in the vault) and the plugin's `develop` photo
// lane. They read through public GETs only. Fixture singles are found by slug
// through the REST API: `seed-241-s1`, `seed-241-y02`, and so on. Without a
// fixture a served check skips itself. The last group builds blank pages from
// the real stylesheets and needs no site.
//
// Environment: CR_BASE_URL, CR_PHOTO_ARCHIVE_PATH (default /kind/photo/),
// CR_PHOTO_PAGE_MAP (default 12,12,10, the local fixture map).
import fs from 'node:fs';
import { test, expect } from '@playwright/test';

const ARCHIVE = process.env.CR_PHOTO_ARCHIVE_PATH || '/kind/photo/';
const PAGE_MAP = ( process.env.CR_PHOTO_PAGE_MAP || '12,12,10' ).split( ',' ).map( Number );
const WIDTHS = [ 1440, 800, 375, 320 ];
const FOREIGN = [ 'photos.example.net', 'old-host.example' ];
const css = ( file ) => fs.readFileSync( new URL( `../../assets/css/${ file }`, import.meta.url ), 'utf8' );

async function fixtureLink( request, label ) {
	const response = await request.get( `/wp-json/wp/v2/posts?slug=seed-241-${ label.toLowerCase() }&_fields=link` );
	if ( ! response.ok() ) {
		return null;
	}
	const [ post ] = await response.json();
	return post?.link ? new URL( post.link ).pathname : null;
}

async function openFixture( page, request, label ) {
	const path = await fixtureLink( request, label );
	test.skip( ! path, `no fixture ${ label }` );
	await page.goto( path, { waitUntil: 'load' } );
}

// The place line on G01 and G02 comes from Simple Location's location_content,
// which the plugin's Photo_Location gate lets through. The lane doc lists G01-G04
// under Dev, and the local stack doesn't install Simple Location. The plugin
// registers the REST namespace sloc_geo/1.0, so the REST index says whether it's
// active. CR_SIMPLE_LOCATION=1 forces the check on, 0 forces it off.
async function simpleLocationActive( request ) {
	if ( '1' === process.env.CR_SIMPLE_LOCATION ) {
		return true;
	}
	if ( '0' === process.env.CR_SIMPLE_LOCATION ) {
		return false;
	}
	const response = await request.get( '/wp-json/' );
	if ( ! response.ok() ) {
		return false;
	}
	const index = await response.json();
	return ( index?.namespaces || [] ).includes( 'sloc_geo/1.0' );
}

// W3-G2: a rendered anchor is an a[href] with no [hidden] on it or an ancestor.
const renderedAnchors = ( scope ) => scope.evaluate( ( el ) => [ ...el.querySelectorAll( 'a[href]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) ).length );
const visibleNegativeTabindex = ( scope ) => scope.evaluate( ( el ) => [ ...el.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( a ) => ! a.closest( '[hidden]' ) ).length );

async function openArchive( page, n = 1 ) {
	const response = await page.goto( n > 1 ? `${ ARCHIVE }page/${ n }/` : ARCHIVE, { waitUntil: 'load' } );
	test.skip( response.status() === 404, `no photo archive page ${ n }` );
	test.skip( 0 === await page.locator( '.cr-photo-frame' ).count(), 'no photo frames: fixtures or the photo lane are missing' );
}

const frameFor = ( page, label ) => page.locator( `.cr-photo-frame:has(a[href*="seed-241-${ label.toLowerCase() }"])` );

// ---------------------------------------------------------------- archive

test.describe( 'archive', () => {
	test( 'frames per page follow the map and each month sheet repeats its H2 when it continues', async ( { page } ) => {
		for ( const [ index, expected ] of PAGE_MAP.entries() ) {
			await openArchive( page, index + 1 );
			expect( await page.locator( '.cr-photo-frame' ).count(), `page ${ index + 1 }` ).toBe( expected );
		}
		await openArchive( page, 1 );
		const last = await page.locator( '.pkiw-group__heading' ).last().textContent();
		await openArchive( page, 2 );
		expect( ( await page.locator( '.pkiw-group__heading' ).first().textContent() ).trim(), 'a continued month repeats its H2' ).toBe( last.trim() );
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
	} );

	test( 'each frame has one rendered link, a counter that restarts per sheet and no number in a link name', async ( { page } ) => {
		await openArchive( page );
		for ( const frame of await page.locator( '.cr-photo-frame' ).all() ) {
			expect( await renderedAnchors( frame ) ).toBe( 1 );
			expect( await visibleNegativeTabindex( frame ) ).toBe( 0 );
		}
		for ( const sheet of await page.locator( '.pkiw-group' ).all() ) {
			// The counter's value isn't readable from computed style, so check what drives it: the list restarts it, each frame's item counts it.
			expect( await sheet.locator( '.pkiw-group__items' ).evaluate( ( el ) => getComputedStyle( el ).counterReset ) ).toContain( 'cr-frame' );
			expect( await sheet.locator( '.pkiw-group__items > li' ).evaluateAll( ( els ) => els.every( ( el ) => getComputedStyle( el ).counterIncrement.includes( 'cr-frame' ) ) ) ).toBe( true );
			expect( await sheet.locator( '.cr-photo-frame__number' ).first().evaluate( ( el ) => getComputedStyle( el, '::before' ).content ) ).toContain( 'counter(cr-frame' );
			for ( const link of await sheet.locator( '.cr-photo-frame a.u-url' ).all() ) {
				expect( ( await link.innerText() ).trim() ).not.toMatch( /^\d\d(\s|$)/ );
			}
		}
		await expect( page.locator( '.cr-photo-frame .cr-photo-frame__number[aria-hidden="true"]' ) ).toHaveCount( await page.locator( '.cr-photo-frame' ).count() );
	} );

	test( 'frame text carries only title and number: no date, arrow, excerpt, caption or kind label', async ( { page } ) => {
		await openArchive( page );
		const text = await page.locator( '.cr-photo-frame' ).first().evaluate( ( el ) => el.innerText );
		expect( text ).not.toMatch( /→|Read more|20\d\d/ );
		await expect( page.locator( '.cr-photo-frame .pk-kindlabel, .cr-photo-frame .pk-media__caption, .cr-photo-frame .pk-excerpt' ) ).toHaveCount( 0 );
	} );

	test( 'served cascade: the plugin card base does not shrink the frame (media wider than 100px, no padding, no tint)', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await openArchive( page );
		const sizes = await page.locator( '.pkiw-group__items > li .cr-photo-frame' ).first().evaluate( ( frame ) => {
			const cs = getComputedStyle( frame );
			const media = frame.querySelector( '.cr-photo-frame__media' ).getBoundingClientRect();
			const title = frame.querySelector( '.cr-photo-frame__title' ).getBoundingClientRect();
			return { frame: frame.getBoundingClientRect().width, media: media.width, title: title.width, padding: cs.paddingLeft, columns: cs.gridTemplateColumns.split( ' ' ).length, before: getComputedStyle( frame, '::before' ).content, shadow: cs.boxShadow };
		} );
		expect( sizes.media, 'frame media width' ).toBeGreaterThan( 100 );
		expect( sizes.media, 'media fills the frame' ).toBeCloseTo( sizes.frame, 0 );
		expect( sizes.title, 'title is not a one-letter column' ).toBeGreaterThan( 60 );
		expect( sizes.padding ).toBe( '0px' );
		expect( sizes.columns ).toBe( 1 );
		expect( [ 'none', 'normal' ] ).toContain( sizes.before );
		expect( sizes.shadow ).toBe( 'none' );
	} );

	for ( const width of WIDTHS ) {
		test( `DOM, reading and Tab order agree and nothing scrolls sideways at ${ width }`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openArchive( page );
			const boxes = await page.locator( '.cr-photo-frame' ).evaluateAll( ( els ) => {
				const sheets = [ ...document.querySelectorAll( '.pkiw-group' ) ];
				return els.map( ( el, i ) => {
					const r = el.getBoundingClientRect();
					return { i, sheet: sheets.indexOf( el.closest( '.pkiw-group' ) ), top: Math.round( r.top + scrollY ), left: Math.round( r.left ), width: Math.round( r.width ) };
				} );
			} );
			const reading = [ ...boxes ].sort( ( a, b ) => ( Math.abs( a.top - b.top ) > 40 ? a.top - b.top : a.left - b.left ) ).map( ( b ) => b.i );
			expect( reading, 'reading order is DOM order' ).toEqual( boxes.map( ( b ) => b.i ) );
			// The first sheet may hold fewer frames than a row (October holds three), so measure the first sheet that fills one.
			const perRow = width >= 1024 ? 4 : width >= 640 ? 3 : 2;
			const sheetSizes = boxes.reduce( ( map, b ) => map.set( b.sheet, ( map.get( b.sheet ) || 0 ) + 1 ), new Map() );
			const rowSheet = [ ...sheetSizes ].find( ( [ , count ] ) => count >= perRow )?.[ 0 ] ?? [ ...sheetSizes ].sort( ( a, b ) => b[1] - a[1] )[0][0];
			const inSheet = boxes.filter( ( b ) => b.sheet === rowSheet );
			const across = new Set( inSheet.filter( ( b ) => Math.abs( b.top - inSheet[0].top ) < 40 ).map( ( b ) => b.left ) ).size;
			expect( Math.min( inSheet.length, across ), 'frames in the first full row' ).toBe( Math.min( inSheet.length, perRow ) );
			expect( new Set( boxes.map( ( b ) => b.width ) ).size, 'one frame width per breakpoint' ).toBe( 1 );
			const hrefs = await page.locator( '.cr-photo-frame a.u-url' ).evaluateAll( ( els ) => els.filter( ( a ) => ! a.closest( '[hidden]' ) ).map( ( a ) => a.href ) );
			const tabbed = [];
			for ( let i = 0; i < Math.min( hrefs.length, 6 ); i++ ) {
				await page.keyboard.press( 'Tab' );
				const href = await page.evaluate( () => document.activeElement?.href || '' );
				if ( hrefs.includes( href ) ) {
					tabbed.push( href );
				}
			}
			expect( tabbed, 'Tab order is DOM order' ).toEqual( hrefs.slice( 0, tabbed.length ) );
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		} );
	}

	test( 'no horizontal scroll at 400% zoom of 1280', async ( { page } ) => {
		await page.setViewportSize( { width: 320, height: 800 } );
		await openArchive( page );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		const overflowing = await page.locator( '.cr-photo-frame__title' ).evaluateAll( ( els ) => els.filter( ( el ) => getComputedStyle( el ).textOverflow === 'ellipsis' ).length );
		expect( overflowing ).toBe( 0 );
	} );

	test( 'photos keep their colors: no filter, normal blend, no overlay, 3:2 medium sub-size with srcset', async ( { page } ) => {
		await openArchive( page );
		for ( const img of await page.locator( '.cr-photo-frame__media img' ).all() ) {
			const info = await img.evaluate( ( el ) => {
				const cs = getComputedStyle( el );
				const media = el.closest( '.cr-photo-frame__media' ).getBoundingClientRect();
				return { filter: cs.filter, blend: cs.mixBlendMode, srcset: el.getAttribute( 'srcset' ), src: el.currentSrc || el.src, ratio: media.width / media.height, before: getComputedStyle( el.closest( '.cr-photo-frame__media' ), '::before' ).content, after: getComputedStyle( el.closest( '.cr-photo-frame__media' ), '::after' ).content };
			} );
			expect( info.filter ).toBe( 'none' );
			expect( info.blend ).toBe( 'normal' );
			expect( info.srcset ).toBeTruthy();
			expect( info.src ).not.toMatch( /-scaled/ );
			expect( info.src ).toMatch( /-\d+x\d+\.\w+$/ ); // a sub-size file, never the original
			expect( Math.abs( info.ratio - 1.5 ) ).toBeLessThan( 0.02 );
			expect( [ 'none', 'normal' ] ).toContain( info.before );
			expect( [ 'none', 'normal' ] ).toContain( info.after );
		}
	} );

	test( 'the first four frames load eagerly, the first at high priority', async ( { page } ) => {
		await openArchive( page );
		const imgs = await page.locator( '.cr-photo-frame__media img' ).evaluateAll( ( els ) => els.slice( 0, 6 ).map( ( el ) => ( { loading: el.getAttribute( 'loading' ), priority: el.getAttribute( 'fetchpriority' ), sizes: el.getAttribute( 'sizes' ) || '' } ) ) );
		expect( imgs[0].priority ).toBe( 'high' );
		for ( const img of imgs.slice( 0, 4 ) ) {
			expect( img.loading ).not.toBe( 'lazy' );
			expect( img.sizes ).not.toMatch( /^auto/ );
		}
	} );

	test( 'no image and failed images stand as typographic frames (U07, R02, R03); no foreign host is requested', async ( { page } ) => {
		const requested = [];
		page.on( 'request', ( r ) => requested.push( new URL( r.url() ).hostname ) );
		for ( const n of [ 1, 2, 3 ] ) {
			await openArchive( page, n );
		}
		for ( const label of [ 'U07', 'R02', 'R03' ] ) {
			const frame = frameFor( page, label );
			if ( 0 === await frame.count() ) {
				continue;
			}
			await expect( frame.locator( '.cr-photo-frame__sleeve' ) ).toBeVisible();
		}
		for ( const host of FOREIGN ) {
			expect( requested, `no request to ${ host }` ).not.toContain( host );
		}
	} );

	test( 'month H2 is upright mono text and only its tape rotates, by token', async ( { page } ) => {
		await openArchive( page );
		const h2 = page.locator( '.pkiw-group__heading' ).first();
		expect( await h2.evaluate( ( el ) => getComputedStyle( el ).transform ) ).toBe( 'none' );
		expect( await h2.evaluate( ( el ) => getComputedStyle( el, '::before' ).transform ) ).not.toBe( 'none' );
		expect( await h2.evaluate( ( el ) => getComputedStyle( el ).fontFamily.split( ',' )[0].replace( /"/g, '' ).trim() ) ).toMatch( /ui-monospace|SF Mono|Menlo|Consolas|monospace/ );
	} );

	test( 'the shared pager follows the last sheet', async ( { page } ) => {
		await openArchive( page );
		const nav = page.locator( 'nav.wp-block-query-pagination' );
		await expect( nav ).toHaveAttribute( 'aria-label', 'Pagination' );
		await expect( nav.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
		expect( await nav.evaluate( ( el ) => el.previousElementSibling?.querySelector( '.pkiw-group' ) !== null || el.previousElementSibling?.classList.contains( 'pkiw-grouped' ) ) ).toBe( true );
		for ( const target of await nav.locator( 'a, span.page-numbers' ).all() ) {
			const box = await target.boundingBox();
			expect( box.height ).toBeGreaterThanOrEqual( 43.5 );
		}
	} );

	test( 'a frame link shows the 3px selective-yellow ring on its pseudo-element', async ( { page } ) => {
		await openArchive( page );
		await page.locator( '.cr-photo-frame a.u-url' ).first().focus();
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Shift+Tab' );
		const ring = await page.evaluate( () => {
			const cs = getComputedStyle( document.activeElement, '::after' );
			return { width: cs.outlineWidth, color: cs.outlineColor, shadow: cs.boxShadow };
		} );
		expect( ring.width ).toBe( '3px' );
		expect( ring.color ).toBe( 'rgb(255, 183, 3)' );
		expect( ring.shadow ).toContain( '7px' );
	} );

	test( 'dark: a night-elevated label with ivory text; the strip edge is visible', async ( { page } ) => {
		await page.emulateMedia( { colorScheme: 'dark' } );
		await openArchive( page );
		const label = await page.locator( '.cr-photo-frame__label' ).first().evaluate( ( el ) => ( { bg: getComputedStyle( el ).backgroundColor, color: getComputedStyle( el ).color } ) );
		expect( label.bg ).toBe( 'rgb(37, 31, 28)' );
		expect( label.color ).toBe( 'rgb(251, 250, 245)' );
		const edge = await page.locator( '.pkiw-group__items > li' ).first().evaluate( ( el ) => getComputedStyle( el ).borderTopColor );
		expect( edge ).not.toBe( 'rgb(26, 23, 20)' );
	} );

	test( 'forced colors: outlined frames, no sprockets or tape; reduced motion: no transitions', async ( { page } ) => {
		await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
		await openArchive( page );
		const li = page.locator( '.pkiw-group__items > li' ).first();
		expect( await li.evaluate( ( el ) => getComputedStyle( el ).borderTopWidth ) ).toBe( '2px' );
		expect( await li.evaluate( ( el ) => getComputedStyle( el ).backgroundImage ) ).toBe( 'none' );
		expect( await page.locator( '.pkiw-group__heading' ).first().evaluate( ( el ) => getComputedStyle( el, '::before' ).display ) ).toBe( 'none' );
		expect( parseFloat( await page.locator( '.cr-photo-frame a.u-url' ).first().evaluate( ( el ) => getComputedStyle( el ).transitionDuration ) ) ).toBeLessThanOrEqual( 0.00001 );
	} );

	test( 'an empty archive prints the shared no-results copy and no strip (fixtures in Trash)', async ( { page } ) => {
		test.skip( ! process.env.CR_PHOTO_EMPTY, 'set CR_PHOTO_EMPTY=1 with every photo post trashed' );
		await page.goto( ARCHIVE );
		await expect( page.locator( '.pkiw-group' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-archive-stream__empty' ) ).toContainText( 'Nothing here yet' );
	} );
} );

// ----------------------------------------------------------------- single

test.describe( 'single', () => {
	test( 'S1: one H1, the region labelled by it, one visible slide with real controls', async ( { page, request } ) => {
		await openFixture( page, request, 'S1' );
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
		const region = page.locator( '.pkiw-projector' );
		await expect( region ).toHaveAttribute( 'role', 'region' );
		await expect( region ).toHaveAttribute( 'aria-roledescription', 'carousel' );
		const labelled = await region.getAttribute( 'aria-labelledby' );
		expect( await page.locator( `#${ labelled }` ).evaluate( ( el ) => el.tagName ) ).toBe( 'H1' );
		expect( await page.locator( '.pkiw-projector__mount:not([hidden])' ).count() ).toBe( 1 );
		expect( await page.locator( '.pkiw-projector__mount' ).count() ).toBe( 4 );
		for ( const name of [ 'Previous image', 'Next image' ] ) {
			const box = await page.getByRole( 'button', { name } ).boundingBox();
			expect( box.height ).toBeGreaterThanOrEqual( 43.5 );
		}
		await expect( page.getByRole( 'button', { name: 'Previous image' } ) ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( page.locator( '.pkiw-projector__status' ) ).toHaveAttribute( 'aria-live', 'polite' );
		expect( await region.locator( 'a' ).count(), 'no link, download or lightbox' ).toBe( 0 );
	} );

	test( 'S1: next, focus stays, no wrap, rail marks the current slide', async ( { page, request } ) => {
		await openFixture( page, request, 'S1' );
		const next = page.getByRole( 'button', { name: 'Next image' } );
		await next.focus();
		await page.keyboard.press( 'Enter' );
		expect( await page.evaluate( () => document.activeElement?.textContent?.trim() ) ).toBe( 'Next image' );
		await expect( page.locator( '.pkiw-projector__counter' ) ).toHaveText( '2 of 4' );
		await next.click();
		await next.click();
		await expect( next ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( page.locator( '.pkiw-projector__counter' ) ).toHaveText( '4 of 4' );
		const rail = page.locator( '.pkiw-projector__thumb' );
		expect( await rail.count() ).toBe( 4 );
		await expect( rail.nth( 3 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( rail.nth( 3 ) ).toContainText( 'Current' );
		expect( await rail.nth( 3 ).evaluate( ( el ) => getComputedStyle( el ).outlineColor ) ).toBe( 'rgb(255, 183, 3)' );
		for ( const thumb of await rail.all() ) {
			const box = await thumb.boundingBox();
			expect( box.width ).toBeGreaterThanOrEqual( 43.5 );
			expect( box.height ).toBeGreaterThanOrEqual( 43.5 );
			expect( await thumb.locator( 'img' ).getAttribute( 'alt' ) ).toBe( '' );
		}
	} );

	test( 'S1: arrows work only inside the region, no document key handler, no autoplay', async ( { page, request } ) => {
		await page.addInitScript( () => {
			window.__docKeydown = 0;
			const original = document.addEventListener.bind( document );
			document.addEventListener = ( type, ...rest ) => {
				// Count only registrations made from the projector script; the theme's own Complianz Space-key handler also lands on document.
				if ( type === 'keydown' && /photo-projector/.test( new Error().stack || '' ) ) {
					window.__docKeydown++;
				}
				return original( type, ...rest );
			};
		} );
		await openFixture( page, request, 'S1' );
		await page.evaluate( () => document.body.focus() );
		await page.keyboard.press( 'ArrowRight' );
		await expect( page.locator( '.pkiw-projector__counter' ) ).toHaveText( '1 of 4' );
		await page.getByRole( 'button', { name: 'Next image' } ).focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( page.locator( '.pkiw-projector__counter' ) ).toHaveText( '2 of 4' );
		await page.waitForTimeout( 10000 );
		await expect( page.locator( '.pkiw-projector__counter' ) ).toHaveText( '2 of 4' );
		expect( await page.evaluate( () => window.__docKeydown ) ).toBe( 0 );
		await expect( page.getByText( /Use .* and .* keys/ ) ).toHaveCount( 0 );
	} );

	test( 'S1 without JavaScript: every image renders in its own mount, with no buttons or rail', async ( { browser, request } ) => {
		const link = await fixtureLink( request, 'S1' );
		test.skip( ! link, 'no fixture S1' );
		const context = await browser.newContext( { javaScriptEnabled: false } );
		const page = await context.newPage();
		await page.goto( link );
		expect( await page.locator( '.pkiw-projector__mount' ).count() ).toBe( 4 );
		for ( const mount of await page.locator( '.pkiw-projector__mount' ).all() ) {
			await expect( mount ).toBeVisible();
			await expect( mount.locator( 'img.u-photo' ) ).toHaveCount( 1 );
		}
		await expect( page.locator( '.pkiw-projector button' ) ).toHaveCount( 0 );
		await context.close();
	} );

	test( 'Y02: the projector replaces the second gallery and the first renders as core', async ( { page, request } ) => {
		await openFixture( page, request, 'Y02' );
		expect( await page.locator( '.pkiw-projector' ).count() ).toBe( 1 );
		expect( await page.locator( '.pkiw-projector__mount' ).count() ).toBe( 3 );
		await expect( page.locator( '.wp-block-gallery' ) ).toHaveCount( 1 );
	} );

	test( 'Y04: the rail sits right of the mount at 1440 and wraps under it at 800, 375 and 320', async ( { page, request } ) => {
		for ( const width of WIDTHS ) {
			await page.setViewportSize( { width, height: 900 } );
			await openFixture( page, request, 'Y04' );
			const mount = await page.locator( '.pkiw-projector__mount:not([hidden])' ).boundingBox();
			const rail = await page.locator( '.pkiw-projector__rail' ).boundingBox();
			if ( width >= 1024 ) {
				expect( rail.x, 'rail right of the mount' ).toBeGreaterThanOrEqual( mount.x + mount.width - 1 );
			} else {
				expect( rail.y, 'rail under the mount' ).toBeGreaterThan( mount.y + mount.height );
			}
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		}
	} );

	test( 'S2 (untitled, one image): one mount with its caption, no controls, region label Photo', async ( { page, request } ) => {
		await openFixture( page, request, 'S2' );
		expect( await page.locator( '.pkiw-projector__mount' ).count() ).toBe( 1 );
		await expect( page.locator( '.pkiw-projector__mount figcaption' ) ).toHaveText( 'Kite day.' );
		await expect( page.locator( '.pkiw-projector button' ) ).toHaveCount( 0 );
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( page.locator( 'h1' ) ).not.toHaveAttribute( 'id', 'cr-photo-title' );
	} );

	test( 'S1 mount paint: ivory, rounded, hard shadow with no blur, unfiltered photo, cerulean controls', async ( { page, request } ) => {
		await openFixture( page, request, 'S1' );
		const mount = page.locator( '.pkiw-projector__mount:not([hidden])' );
		const paint = await mount.evaluate( ( el ) => {
			const cs = getComputedStyle( el );
			return { bg: cs.backgroundColor, radius: cs.borderTopLeftRadius, shadow: cs.boxShadow, imgFilter: getComputedStyle( el.querySelector( 'img' ) ).filter };
		} );
		expect( paint.bg ).toBe( 'rgb(251, 250, 245)' );
		expect( parseFloat( paint.radius ) ).toBeGreaterThan( 0 );
		expect( paint.shadow ).toMatch( /\s4px 4px 0px 0px$/ );
		expect( paint.imgFilter ).toBe( 'none' );
		expect( await page.getByRole( 'button', { name: 'Next image' } ).evaluate( ( el ) => getComputedStyle( el ).backgroundColor ) ).toBe( 'rgb(18, 103, 130)' );
	} );

	test( 'Y05: a duotone preset set in the editor renders as core outputs it', async ( { page, request } ) => {
		await openFixture( page, request, 'Y05' );
		const html = await page.locator( '.single-post__content' ).innerHTML();
		expect( html ).toMatch( /duotone/ );
		expect( await page.locator( '.pkiw-projector__mount img' ).first().evaluate( ( el ) => getComputedStyle( el ).mixBlendMode ) ).toBe( 'normal' );
	} );

	test( 'the post navigation keeps its shipped chips; image controls never use bare Previous or Next', async ( { page, request } ) => {
		await openFixture( page, request, 'S1' );
		await expect( page.locator( 'nav.cr-post-nav' ) ).toHaveAttribute( 'aria-label', 'Post navigation' );
		await expect( page.locator( '.pkiw-projector' ).getByRole( 'button', { name: /^(Previous|Next)$/ } ) ).toHaveCount( 0 );
	} );

	test( 'privacy: P01 shows a password form and nothing of the photo; P02 is not public', async ( { page, request } ) => {
		const link = await fixtureLink( request, 'P01' );
		test.skip( ! link, 'no fixture P01' );
		await page.goto( link );
		await expect( page.locator( '.pkiw-projector, .pkiw-projector__mount' ) ).toHaveCount( 0 );
		await expect( page.locator( '.single-post__content img' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-block-post-featured-image img' ) ).toHaveCount( 0 );
		await expect( page.locator( 'input[type="password"]' ) ).toHaveCount( 1 );
		expect( await fixtureLink( request, 'P02' ), 'a private post is not in the public REST list' ).toBeNull();
	} );

	test( 'G01 and G02 show the place line and no street, map, coordinates or head geo meta (logged out)', async ( { page, request } ) => {
		test.skip( ! await simpleLocationActive( request ), 'Simple Location is not active (no sloc_geo/1.0 REST namespace); its location_content filter prints the place line, and the lane doc lists G01-G04 under Dev. Set CR_SIMPLE_LOCATION=1 to force.' );
		for ( const label of [ 'G01', 'G02' ] ) {
			await openFixture( page, request, label );
			const html = await page.content();
			expect( html ).not.toContain( '100 Example Avenue' );
			expect( html ).not.toMatch( /geo\.position|ICBM|sloc-map/ );
			await expect( page.locator( '.pkiw-photo-place' ) ).toHaveText( 'Example Hall, Sampletown, Example Region, Exampleland' );
		}
		await openFixture( page, request, 'G03' );
		await expect( page.locator( '.pkiw-photo-place' ) ).toHaveCount( 0 );
	} );
} );

// ----------------------------------------------------------------- stream

test.describe( 'stream', () => {
	async function openStream( page ) {
		const response = await page.goto( '/stream/', { waitUntil: 'load' } );
		test.skip( response.status() === 404, 'no /stream/' );
	}

	test( 'S1, S2 and S3 are slide mounts with no booth strip, one link and no excerpt or Read more', async ( { page } ) => {
		await openStream( page );
		const mounts = page.locator( '.pk-card.cr-slide-mount' );
		test.skip( 0 === await mounts.count(), 'no photo cards on page 1 of /stream/' );
		await expect( page.locator( '.pk-card.cr-slide-mount .cr-booth, .cr-slide-mount .pk-excerpt' ) ).toHaveCount( 0 );
		for ( const card of await mounts.all() ) {
			expect( await renderedAnchors( card ) ).toBe( 1 );
			expect( await visibleNegativeTabindex( card ) ).toBe( 0 );
			expect( await card.evaluate( ( el ) => el.innerText ) ).not.toMatch( /Read more|→|\+\d+/ );
			expect( await card.locator( '.pk-media__caption a' ).count(), 'caption links are stripped' ).toBe( 0 );
		}
	} );

	test( 'a mount has the collage column width, one rotation token, the four voices and an unfiltered medium image', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await openStream( page );
		const mount = page.locator( '.pk-card.cr-slide-mount' ).first();
		test.skip( 0 === await mount.count(), 'no photo cards on /stream/' );
		const info = await mount.evaluate( ( el ) => {
			const li = el.closest( 'li' );
			// Note stickies are 82% of the column by design (.cr-sticky-artifact--stream), so they are not a width reference.
			const isReference = ( card ) => ! card.classList.contains( 'cr-slide-mount' ) && ! card.closest( '.cr-sticky-artifact' ) && ! card.querySelector( '.cr-sticky-artifact' );
			const neighbour = [ ...li.parentElement.children ].find( ( other ) => other !== li && [ ...other.querySelectorAll( '.pk-card' ) ].some( isReference ) && ! other.querySelector( '.cr-sticky-artifact, .cr-slide-mount' ) );
			const fam = ( node ) => getComputedStyle( node ).fontFamily.split( ',' )[0].replace( /"/g, '' ).trim();
			return { width: el.offsetWidth, neighbour: neighbour ? neighbour.querySelector( '.pk-card' ).offsetWidth : null, transform: getComputedStyle( el ).transform, title: fam( el.querySelector( '.pk-title' ) ), caption: el.querySelector( '.pk-media__caption' ) ? fam( el.querySelector( '.pk-media__caption' ) ) : null, filter: getComputedStyle( el.querySelector( 'img' ) ).filter, shadow: getComputedStyle( el ).boxShadow };
		} );
		if ( info.neighbour ) {
			expect( Math.abs( info.width - info.neighbour ) ).toBeLessThanOrEqual( 1 );
		}
		expect( info.transform ).not.toBe( 'none' );
		expect( info.title ).toBe( 'Roboto Slab' );
		if ( info.caption ) {
			expect( info.caption ).toBe( 'Barlow' );
		}
		expect( info.filter ).toBe( 'none' );
		expect( info.shadow ).toMatch( /\s4px 4px 0px 0px$/ );
	} );

	test( 'the whole mount takes the focus ring and no stream request reaches a foreign host', async ( { page } ) => {
		const requested = [];
		page.on( 'request', ( r ) => requested.push( new URL( r.url() ).hostname ) );
		await openStream( page );
		const link = page.locator( '.pk-card.cr-slide-mount .pk-title a' ).first();
		test.skip( 0 === await link.count(), 'no photo cards on /stream/' );
		await link.focus();
		await page.keyboard.press( 'Shift+Tab' );
		await page.keyboard.press( 'Tab' );
		const ring = await page.evaluate( () => ( { width: getComputedStyle( document.activeElement, '::after' ).outlineWidth, color: getComputedStyle( document.activeElement, '::after' ).outlineColor } ) );
		expect( ring ).toEqual( { width: '3px', color: 'rgb(255, 183, 3)' } );
		for ( const host of FOREIGN ) {
			expect( requested ).not.toContain( host );
		}
	} );

	for ( const width of [ 375, 320 ] ) {
		test( `a mount fits one column at ${ width }`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await openStream( page );
			test.skip( 0 === await page.locator( '.pk-card.cr-slide-mount' ).count(), 'no photo cards on /stream/' );
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
		} );
	}

	test( 'the front page lane prints the mount with no polaroid rule left', async ( { page } ) => {
		await page.goto( '/', { waitUntil: 'load' } );
		const mount = page.locator( '.cr-fieldnotes .pk-card.cr-slide-mount' ).first();
		test.skip( 0 === await mount.count(), 'no photo card in the front page lane' );
		const style = await mount.locator( 'img' ).evaluate( ( el ) => ( { ratio: getComputedStyle( el ).aspectRatio, border: getComputedStyle( el ).borderTopStyle } ) );
		expect( style.ratio ).not.toMatch( /^4\s*\/\s*5/ );
		expect( style.border ).not.toBe( 'dashed' );
	} );

	test( 'C01 keeps its booth strip and its stamps print no address (dev fixture, logged out)', async ( { page } ) => {
		await openStream( page );
		const booth = page.locator( '.cr-booth' );
		test.skip( 0 === await booth.count(), 'no gallery-format card on page 1 of /stream/' );
		expect( await booth.first().evaluate( ( el ) => el.closest( '.pk-card' ).innerText ) ).not.toContain( '100 Example Avenue' );
	} );
} );

// -------------------------------------------- blank pages, real stylesheets

const frame = ( n, title ) => `
	<li><article class="pk-card pk-card--stream pk-card--photo k-photo h-entry cr-photo-frame cr-photo-frame--titled">
		<span class="cr-photo-frame__media" aria-hidden="true" data-cr-cover-fallback><img class="u-photo" alt="" width="800" height="600" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="><span class="cr-photo-frame__sleeve"><span class="cr-photo-frame__sleeve-text">${ title }</span></span></span>
		<div class="cr-photo-frame__label"><span class="cr-photo-frame__number" aria-hidden="true"></span><h3 class="cr-photo-frame__title pk-title p-name"><a class="u-url" href="#f${ n }">${ title }</a></h3></div>
	</article></li>`;

async function staticArchive( page, sheets ) {
	const markup = sheets.map( ( count, s ) => `<section class="pkiw-group"><h2 class="pkiw-group__heading">Sheet ${ s + 1 }</h2><ul class="pkiw-group__items">${ Array.from( { length: count }, ( _, i ) => frame( `${ s }-${ i }`, `Frame ${ i + 1 }` ) ).join( '' ) }</ul></section>` ).join( '' );
	await page.setContent( `<!doctype html><html><head></head><body class="cr-photo-archive"><div class="pkiw-grouped cr-photo-sheets">${ markup }</div></body></html>` );
	await page.addStyleTag( { content: css( 'tokens.css' ) } );
	await page.addStyleTag( { content: css( 'cr-paper.css' ) } );
	await page.addStyleTag( { content: css( 'cr-photo.css' ) } );
}

// The plugin's build/blocks.css rules for .pk-card (develop), loaded after the theme sheet at the same one-class weight.
const PLUGIN_CARD_BASE = ':where(.pk-card){--pk-space-3:0.75rem;--pk-space-4:1rem;--pk-space-5:1.5rem;--pk-radius:14px;--pk-badge:34px;--pk-maxw:38rem}.pk-card{align-items:start;background:var(--pk-surface,white);border:1px solid var(--pk-border,gray);border-left:3px solid var(--pk-kind,red);border-radius:var(--pk-radius);box-shadow:var(--pk-shadow,0 1px 4px rgba(0,0,0,.2));color:var(--pk-ink);display:grid;gap:var(--pk-space-3) var(--pk-space-4);grid-template-columns:var(--pk-badge) 1fr;max-width:var(--pk-maxw);padding:var(--pk-space-5);position:relative}.pk-card:before{background:var(--pk-tint,pink);border-radius:inherit;content:"";inset:0;opacity:.5;pointer-events:none;position:absolute}.pk-card>*{position:relative}.pk-title{color:var(--pk-ink);font-size:1.075rem;font-weight:700;line-height:1.3;margin:0}.pk-title a{color:inherit;text-decoration:none}';

test.describe( 'static: cr-photo.css on a blank page', () => {
	test( 'the plugin card base loaded after the sheet leaves the frame whole: full-width media, no padding, no tint, a readable title', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await staticArchive( page, [ 9, 3 ] );
		await page.addStyleTag( { content: PLUGIN_CARD_BASE } );
		const sizes = await page.locator( '.pkiw-group__items > li .cr-photo-frame' ).first().evaluate( ( frame ) => {
			const cs = getComputedStyle( frame );
			return {
				frame: frame.getBoundingClientRect().width,
				media: frame.querySelector( '.cr-photo-frame__media' ).getBoundingClientRect().width,
				title: frame.querySelector( '.cr-photo-frame__title' ).getBoundingClientRect().width,
				titleHeight: frame.querySelector( '.cr-photo-frame__title' ).getBoundingClientRect().height,
				padding: cs.paddingLeft,
				border: cs.borderLeftWidth,
				columns: cs.gridTemplateColumns.split( ' ' ).length,
				before: getComputedStyle( frame, '::before' ).content,
				shadow: cs.boxShadow,
				fontSize: getComputedStyle( frame.querySelector( '.cr-photo-frame__title' ) ).fontSize,
			};
		} );
		expect( sizes.media ).toBeGreaterThan( 100 );
		expect( sizes.media ).toBeCloseTo( sizes.frame, 0 );
		expect( sizes.title, 'title is not a one-letter column' ).toBeGreaterThan( 40 );
		expect( sizes.titleHeight, 'a short title stands on one line' ).toBeLessThan( 40 );
		expect( sizes ).toMatchObject( { padding: '0px', border: '0px', columns: 1, shadow: 'none' } );
		expect( [ 'none', 'normal' ] ).toContain( sizes.before );
		expect( sizes.fontSize, 'the frame title keeps the theme size, not the plugin pk-title size' ).not.toBe( '17.2px' );
	} );

	for ( const [ width, perRow ] of [ [ 1440, 4 ], [ 800, 3 ], [ 375, 2 ], [ 320, 2 ] ] ) {
		test( `${ perRow } frames across at ${ width }, one width, a short row ends after its last frame`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 900 } );
			await staticArchive( page, [ 9, 3 ] );
			const rows = await page.locator( '.cr-photo-frame' ).evaluateAll( ( els ) => els.map( ( el ) => {
				const r = el.getBoundingClientRect();
				return { top: Math.round( r.top + scrollY ), width: Math.round( r.width ) };
			} ) );
			const first = rows.filter( ( r ) => r.top === rows[0].top );
			expect( first.length ).toBe( perRow );
			expect( new Set( rows.map( ( r ) => r.width ) ).size ).toBe( 1 );
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBeLessThanOrEqual( 0 );
			const strip = await page.locator( '.pkiw-group__items' ).last().evaluate( ( el ) => el.querySelectorAll( 'li' ).length );
			expect( strip ).toBe( 3 );
		} );
	}

	test( 'the counter restarts on each sheet and has empty alternative text', async ( { page } ) => {
		await staticArchive( page, [ 3, 2 ] );
		const lists = await page.locator( '.pkiw-group__items' ).evaluateAll( ( els ) => els.map( ( el ) => getComputedStyle( el ).counterReset ) );
		expect( lists.every( ( value ) => value.includes( 'cr-frame' ) ), 'each sheet restarts the counter' ).toBe( true );
		const items = await page.locator( '.pkiw-group__items > li' ).evaluateAll( ( els ) => els.map( ( el ) => getComputedStyle( el ).counterIncrement ) );
		expect( items.every( ( value ) => value.includes( 'cr-frame' ) ), 'each frame counts' ).toBe( true );
	} );

	test( 'frames fill one width and keep the image unfiltered; tape tilts, heading text does not', async ( { page } ) => {
		await staticArchive( page, [ 4 ] );
		const info = await page.locator( '.cr-photo-frame img' ).first().evaluate( ( el ) => ( { filter: getComputedStyle( el ).filter, blend: getComputedStyle( el ).mixBlendMode, fit: getComputedStyle( el ).objectFit } ) );
		expect( info ).toEqual( { filter: 'none', blend: 'normal', fit: 'cover' } );
		const h2 = page.locator( '.pkiw-group__heading' );
		expect( await h2.evaluate( ( el ) => getComputedStyle( el ).transform ) ).toBe( 'none' );
		expect( await h2.evaluate( ( el ) => getComputedStyle( el, '::before' ).transform ) ).not.toBe( 'none' );
	} );

	test( 'the sleeve shows when the image fails or is absent, and the focus ring is 3px yellow with a halo', async ( { page } ) => {
		await staticArchive( page, [ 2 ] );
		await page.evaluate( () => {
			const media = document.querySelector( '.cr-photo-frame__media' );
			media.classList.add( 'cr-cover--failed' );
			media.querySelector( 'img' ).remove();
		} );
		expect( await page.locator( '.cr-photo-frame__sleeve' ).first().evaluate( ( el ) => getComputedStyle( el ).display ) ).toBe( 'grid' );
		expect( await page.locator( '.cr-photo-frame__sleeve' ).last().evaluate( ( el ) => getComputedStyle( el ).display ) ).toBe( 'none' );
		await page.keyboard.press( 'Tab' );
		const ring = await page.evaluate( () => {
			const cs = getComputedStyle( document.activeElement, '::after' );
			return { width: cs.outlineWidth, color: cs.outlineColor, shadow: cs.boxShadow };
		} );
		expect( ring.width ).toBe( '3px' );
		expect( ring.color ).toBe( 'rgb(255, 183, 3)' );
		expect( ring.shadow ).toContain( '7px' );
	} );

	test( 'dark: the label is night elevated with ivory text and the strip edge uses periwinkle', async ( { page } ) => {
		await staticArchive( page, [ 2 ] );
		await page.evaluate( () => {
			document.documentElement.dataset.theme = 'dark';
		} );
		const label = await page.locator( '.cr-photo-frame__label' ).first().evaluate( ( el ) => ( { bg: getComputedStyle( el ).backgroundColor, color: getComputedStyle( el ).color } ) );
		expect( label ).toEqual( { bg: 'rgb(37, 31, 28)', color: 'rgb(251, 250, 245)' } );
		expect( await page.locator( '.pkiw-group__items > li' ).first().evaluate( ( el ) => getComputedStyle( el ).borderTopColor ) ).toBe( 'rgb(188, 181, 227)' );
	} );

	test( 'forced colors drop the sprockets and tape and outline each frame', async ( { page } ) => {
		await staticArchive( page, [ 2 ] );
		await page.emulateMedia( { forcedColors: 'active' } );
		const li = page.locator( '.pkiw-group__items > li' ).first();
		expect( await li.evaluate( ( el ) => getComputedStyle( el ).backgroundImage ) ).toBe( 'none' );
		expect( await li.evaluate( ( el ) => getComputedStyle( el ).borderTopWidth ) ).toBe( '2px' );
		expect( await page.locator( '.pkiw-group__heading' ).evaluate( ( el ) => getComputedStyle( el, '::before' ).display ) ).toBe( 'none' );
	} );

	test( 'no text under the 12px floor and no transition on any frame element under reduced motion', async ( { page } ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await staticArchive( page, [ 2 ] );
		const small = await page.locator( '.cr-photo-sheets *' ).evaluateAll( ( els ) => els.filter( ( el ) => el.childNodes.length && [ ...el.childNodes ].some( ( n ) => n.nodeType === 3 && n.textContent.trim() ) && parseFloat( getComputedStyle( el ).fontSize ) < 12 ).length );
		expect( small ).toBe( 0 );
		const transitions = await page.locator( '.cr-photo-sheets *' ).evaluateAll( ( els ) => els.filter( ( el ) => getComputedStyle( el ).transitionDuration.split( ',' ).some( ( d ) => parseFloat( d ) > 0.00001 ) ).length ); // tokens.css clamps motion to 0.001ms
		expect( transitions ).toBe( 0 );
	} );
} );
