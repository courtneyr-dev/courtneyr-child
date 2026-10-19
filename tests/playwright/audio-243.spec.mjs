// `CR_SPEC=audio-243.spec.mjs npm run captures -- --project=1280-light`:
// PKIW issue 243 audio archive, single, Stream, microformats and privacy checks.
// Read-only GETs; served cases need seed-243.php fixtures and the audio plugin lane.
import { test, expect } from '@playwright/test';

const ARCHIVE = ( process.env.CR_AUDIO_ARCHIVE_PATH || '/kind/audio/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_AUDIO_STREAM_PATH || '/stream/';
const WIDTHS = [ 1440, 1024, 800, 375, 320 ];
const REQUIRED_WIDTHS = [ 1440, 800, 375, 320 ];
const EXTERNAL_AUDIO_HOSTS = [ 'audio.example.org', 'soundcloud.com' ];
const slugOf = ( key ) => `seed-243-${ key.toLowerCase() }`;
const archivePage = ( number ) => number > 1 ? `${ ARCHIVE }page/${ number }/` : ARCHIVE;

function skipMissing( missing, reason ) {
	test.skip( missing, reason );
}

async function fixturePath( request, key ) {
	const response = await request.get( `/wp-json/wp/v2/posts?slug=${ slugOf( key ) }&_fields=link` );
	if ( ! response.ok() ) {
		return null;
	}
	const [ post ] = await response.json();
	return post?.link ? new URL( post.link ).pathname : null;
}

async function openFixture( page, request, key, width = 1440 ) {
	const path = await fixturePath( request, key );
	skipMissing( ! path, `fixture ${ key } is absent; run seed-243.php --set=archive` );
	await page.setViewportSize( { width, height: width <= 375 ? 900 : 1000 } );
	const response = await page.goto( path, { waitUntil: 'load' } );
	skipMissing( response?.status() === 404, `fixture ${ key } resolves to a 404` );
	return path;
}

async function openArchive( page, number = 1, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 375 ? 900 : 1000 } );
	const response = await page.goto( archivePage( number ), { waitUntil: 'load' } );
	skipMissing( response?.status() === 404, `audio archive page ${ number } is absent at ${ archivePage( number ) }` );
	const archiveFixture = page.locator( 'a[href*="seed-243-a0"], a[href*="seed-243-b0"], a[href*="seed-243-c0"], a[href*="seed-243-d0"], a[href*="seed-243-x04"], a[href*="seed-243-x05"]' );
	skipMissing( 0 === await archiveFixture.count(), 'archive fixture set is absent; run seed-243.php --set=archive' );
	skipMissing( 0 === await page.locator( '.cr-audio-log .cr-cassette--log' ).count(), 'audio plugin archive blocks or the theme audio lane are absent' );
	return response;
}

async function openStream( page, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 375 ? 900 : 1000 } );
	const response = await page.goto( STREAM, { waitUntil: 'load' } );
	skipMissing( response?.status() === 404, `Stream is absent at ${ STREAM }` );
	return response;
}

const archiveItems = ( page ) => page.locator( '.cr-audio-log .pkiw-group__items > li' );
const itemFor = ( page, key ) => archiveItems( page ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();
const streamItemFor = ( page, key ) => page.locator( 'li.wp-block-post, article.pk-card' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

const linkReport = ( locator ) => locator.evaluate( ( root ) => {
	const rendered = [ ...root.querySelectorAll( 'a[href]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) );
	const stray = [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) );
	return { hrefs: rendered.map( ( link ) => link.href ), stray: stray.length };
} );

// A DOM mf2 parser for the rendered property contract. The PHP checker below runs php-mf2
// when it is installed; this browser-side parse catches roots and property-value placement.
const parseMf2Entry = ( locator ) => locator.evaluate( ( root ) => {
	const values = ( className ) => [ ...root.querySelectorAll( `.${ className }` ) ].filter( ( node ) => ! ( 'p-name' === className && node.closest( '.h-card' ) ) ).map( ( node ) => node.getAttribute( 'href' ) || node.getAttribute( 'value' ) || node.getAttribute( 'datetime' ) || node.textContent.trim() ).filter( Boolean );
	return {
		entry: root.classList.contains( 'h-entry' ),
		nested: root.querySelectorAll( '.h-entry' ).length,
		url: values( 'u-url' ),
		name: values( 'p-name' ),
		published: values( 'dt-published' ),
		author: root.querySelectorAll( '.p-author.h-card, .p-author .h-card' ).length,
		audio: values( 'u-audio' ),
	};
} );

async function expectNoHorizontalScroll( page, label ) {
	expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ), label ).toBeLessThanOrEqual( 0 );
}

async function expectVisualOrder( locator, label ) {
	const boxes = await locator.evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
		const box = node.getBoundingClientRect();
		return { index, top: Math.round( box.top + scrollY ), left: Math.round( box.left ) };
	} ) );
	const visual = [ ...boxes ].sort( ( a, b ) => Math.abs( a.top - b.top ) > 4 ? a.top - b.top : a.left - b.left ).map( ( box ) => box.index );
	expect( visual, label ).toEqual( boxes.map( ( box ) => box.index ) );
}

test.describe( 'audio archive', () => {
	test( 'page map is 12 then 10 locally or 11 on dev, with repeated August and terminal page 3', async ( { page } ) => {
		await openArchive( page );
		expect( await archiveItems( page ).count() ).toBe( 12 );
		const pageOneHeadings = ( await page.locator( '.cr-audio-log .pkiw-group__heading' ).allTextContents() ).map( ( text ) => text.trim() );
		expect( pageOneHeadings ).toEqual( [ 'September 2026', 'August 2026' ] );
		await openArchive( page, 2 );
		expect( [ 10, 11 ] ).toContain( await archiveItems( page ).count() );
		await expect( page.locator( '.cr-audio-log .pkiw-group__heading' ).first() ).toHaveText( 'August 2026' );
		const third = await page.goto( archivePage( 3 ), { waitUntil: 'load' } );
		expect( third?.status() ).toBe( 404 );
	} );

	test( 'private, draft and note controls stay out while X04 remains in the archive', async ( { page } ) => {
		for ( const number of [ 1, 2 ] ) {
			await openArchive( page, number );
			for ( const key of [ 'X01', 'X02', 'X03' ] ) {
				await expect( page.locator( `a[href*="${ slugOf( key ) }"]` ) ).toHaveCount( 0 );
			}
		}
		await openArchive( page, 2 );
		await expect( page.locator( `a[href*="${ slugOf( 'X04' ) }"]` ) ).toHaveCount( 1 );
	} );

	for ( const width of WIDTHS ) {
		test( `DOM, visual and Tab order agree at ${ width }px`, async ( { page } ) => {
			await openArchive( page, 1, width );
			await expectVisualOrder( archiveItems( page ), `${ width }px visual order follows DOM order` );
			const expected = await page.locator( '.cr-audio-log .pk-title a.u-url' ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) );
			await page.locator( '.cr-audio-log' ).evaluate( ( root ) => { root.tabIndex = -1; root.focus(); } );
			const visited = [];
			for ( let press = 0; press < expected.length + 20 && visited.length < expected.length; press++ ) {
				await page.keyboard.press( 'Tab' );
				const href = await page.evaluate( () => document.activeElement?.closest( '.cr-audio-log' ) ? document.activeElement.href || '' : '' );
				if ( href && expected.includes( href ) ) {
					visited.push( href );
				}
			}
			expect( visited, `${ width }px Tab order follows DOM order` ).toEqual( expected );
			await expectNoHorizontalScroll( page, `${ width }px archive has no horizontal scroll` );
		} );
	}

	test( 'month grids use two columns from 64rem and one below, with no synthetic final cell', async ( { page } ) => {
		for ( const [ width, columns ] of [ [ 1440, 2 ], [ 1024, 2 ], [ 800, 1 ], [ 375, 1 ], [ 320, 1 ] ] ) {
			await openArchive( page, 1, width );
			for ( const list of await page.locator( '.cr-audio-log .pkiw-group__items' ).all() ) {
				expect( await list.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length ), `${ width }px column count` ).toBe( columns );
				expect( await list.locator( ':scope > li' ).count(), 'the odd month ends after its last real item' ).toBe( await list.locator( ':scope > li .cr-cassette--log' ).count() );
			}
		}
	} );

	test( 'every item has one rendered link, one li entry root and complete mf2 properties', async ( { page } ) => {
		await openArchive( page );
		for ( const item of await archiveItems( page ).all() ) {
			const links = await linkReport( item );
			expect( links.hrefs ).toHaveLength( 1 );
			expect( links.stray ).toBe( 0 );
			if ( await item.locator( '.pk-card--protected' ).count() ) {
				// A protected post prints its title link and nothing else: no hidden author, date or audio data.
				await expect( item.locator( '.pk-entry-props, .u-audio, .p-author, time' ) ).toHaveCount( 0 );
				continue;
			}
			expect( await item.locator( '[hidden] a[tabindex="-1"]' ).count() ).toBe( await item.locator( 'a[tabindex="-1"]' ).count() );
			expect( await item.getAttribute( 'class' ) ).toContain( 'h-entry' );
			expect( await item.locator( 'article.cr-cassette--log.h-entry' ).count() ).toBe( 0 );
			const mf2 = await parseMf2Entry( item );
			expect( mf2.entry ).toBe( true );
			expect( mf2.nested ).toBe( 0 );
			expect( mf2.url.length ).toBeGreaterThan( 0 );
			expect( mf2.published.length ).toBeGreaterThan( 0 );
			expect( mf2.author ).toBeGreaterThan( 0 );
			expect( mf2.audio.length ).toBeGreaterThan( 0 );
			expect( mf2.published[ 0 ] ).toMatch( /^\d{4}-\d{2}-\d{2}$/ );
			const titled = await item.locator( '.pk-title' ).evaluate( ( node ) => node.classList.contains( 'p-name' ) );
			expect( mf2.name.length, 'p-name exists only for a real title' ).toBe( titled ? 1 : 0 );
			const metaOrder = await item.locator( '.cr-audio-log__meta > p' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.classList.contains( 'pk-stream-date' ) ? 'date' : node.classList.contains( 'pk-audio-duration' ) ? 'duration' : 'other' ) );
			expect( metaOrder[0] ).toBe( 'date' );
			expect( metaOrder.slice( 1 ).every( ( value ) => 'duration' === value ) ).toBe( true );
		}
	} );

	test( 'the title overlay covers each item and focus paints the li', async ( { page } ) => {
		await openArchive( page );
		for ( const item of await archiveItems( page ).all() ) {
			await item.scrollIntoViewIfNeeded();
			const hit = await item.evaluate( ( root ) => {
				const link = root.querySelector( '.pk-title a[href]' );
				const box = root.getBoundingClientRect();
				const points = [ [ box.left + 4, box.top + 4 ], [ box.right - 4, box.top + 4 ], [ box.left + 4, box.bottom - 4 ], [ box.right - 4, box.bottom - 4 ], [ box.left + box.width / 2, box.top + box.height / 2 ] ];
				return points.map( ( [ x, y ] ) => document.elementFromPoint( x, y )?.closest( 'a' ) === link );
			} );
			expect( hit ).toEqual( [ true, true, true, true, true ] );
		}
		const first = archiveItems( page ).first();
		await first.locator( '.pk-title a' ).focus();
		const ring = await first.evaluate( ( node ) => ( { width: getComputedStyle( node ).outlineWidth, shadow: getComputedStyle( node ).boxShadow } ) );
		expect( ring.width ).toBe( '3px' );
		expect( ring.shadow ).toContain( '7px' );
	} );

	test( 'A02 and B03 wrap at 320px and at the 400 percent reflow width', async ( { page } ) => {
		for ( const number of [ 1, 2 ] ) {
			await openArchive( page, number, 320 );
			for ( const key of [ 'A02', 'B03' ] ) {
				const item = itemFor( page, key );
				if ( 0 === await item.count() ) {
					continue;
				}
				expect( await item.locator( '.pk-title' ).evaluate( ( node ) => node.scrollWidth <= node.clientWidth + 1 ), `${ key } title fits` ).toBe( true );
			}
			await expectNoHorizontalScroll( page, `archive page ${ number } reflows at 320px` );
		}
	} );

	test( 'pager follows the grouped post template and has 44px targets', async ( { page } ) => {
		await openArchive( page );
		const nav = page.locator( 'nav.wp-block-query-pagination[aria-label="Pagination"]' );
		await expect( nav ).toHaveCount( 1 );
		await expect( nav.locator( '.wp-block-query-pagination-previous' ) ).toHaveCount( 0 );
		expect( await nav.evaluate( ( node ) => node.previousElementSibling?.matches( '.wp-block-post-template.pkiw-grouped' ) || Boolean( node.previousElementSibling?.querySelector( '.wp-block-post-template.pkiw-grouped' ) ) ) ).toBe( true );
		for ( const target of await nav.locator( 'a, .page-numbers' ).all() ) {
			const box = await target.boundingBox();
			expect( Math.min( box.width, box.height ) ).toBeGreaterThanOrEqual( 43.5 );
		}
	} );

	for ( const width of REQUIRED_WIDTHS ) {
		test( `light, both dark mechanisms, forced colors and reduced motion remain usable at ${ width }px`, async ( { page } ) => {
			await page.emulateMedia( { colorScheme: 'light' } );
			await openArchive( page, 1, width );
			const light = await page.locator( '.cr-archive--audio' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor );
			await page.emulateMedia( { colorScheme: 'dark' } );
			await page.reload( { waitUntil: 'load' } );
			const systemDark = await page.locator( '.cr-archive--audio' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor );
			await page.emulateMedia( { colorScheme: 'light' } );
			await page.evaluate( () => { document.documentElement.dataset.theme = 'dark'; } );
			const explicitDark = await page.locator( '.cr-archive--audio' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor );
			expect( systemDark ).toBe( explicitDark );
			expect( explicitDark ).not.toBe( light );
			await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
			const item = archiveItems( page ).first();
			expect( await item.evaluate( ( node ) => getComputedStyle( node ).borderTopWidth ) ).toBe( '2px' );
			expect( await item.evaluate( ( node ) => Math.max( ...getComputedStyle( node ).transitionDuration.split( ',' ).map( Number.parseFloat ) ) ) ).toBeLessThanOrEqual( 0.00001 );
			await expectNoHorizontalScroll( page, `${ width }px preference modes do not overflow` );
		} );
	}

	test( 'header art never overlaps the H1 and drops below 40rem', async ( { page } ) => {
		for ( const width of REQUIRED_WIDTHS ) {
			await openArchive( page, 1, width );
			const report = await page.locator( '.cr-archive__header' ).evaluate( ( header ) => {
				const h1 = header.querySelector( 'h1' ).getBoundingClientRect();
				const box = header.getBoundingClientRect();
				const style = getComputedStyle( header );
				return { art: getComputedStyle( header, '::after' ).display, h1Right: h1.right, safeRight: box.right - parseFloat( style.paddingRight || 0 ) };
			} );
			if ( width < 640 ) {
				expect( report.art ).toBe( 'none' );
			} else {
				expect( report.art ).not.toBe( 'none' );
				expect( report.h1Right ).toBeLessThanOrEqual( report.safeRight + 1 );
			}
		}
	} );

	test( 'empty archive uses the shared no-results state', async ( { page } ) => {
		test.skip( '1' !== process.env.CR_AUDIO_EMPTY, 'set CR_AUDIO_EMPTY=1 after trashing the archive fixtures' );
		await page.goto( ARCHIVE, { waitUntil: 'load' } );
		await expect( page.locator( '.pkiw-group' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cr-archive-stream__empty' ) ).toContainText( 'Nothing here yet' );
	} );
} );

test.describe( 'audio single', () => {
	test( 'A01 order, entry wrappers, transcript and one player match the single contract', async ( { page, request } ) => {
		await openFixture( page, request, 'A01' );
		const ordered = [ 'main h1', '.single-post__lede-meta time, .single-post__date time', '.cr-audio-machine', '.cr-audio-sheet', '.e-content > :not(.cr-audio-single):not([hidden])', '.single-post__comments-wrapper' ];
		expect( await page.evaluate( ( selectors ) => {
			const nodes = selectors.map( ( selector ) => document.querySelector( selector ) );
			return nodes.every( Boolean ) && nodes.every( ( node, index ) => 0 === index || Boolean( nodes[ index - 1 ].compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING ) || nodes[ index - 1 ].contains( node ) );
		}, ordered ) ).toBe( true );
		await expect( page.locator( '.cr-audio-machine__counter' ) ).toHaveText( '01' );
		await expect( page.locator( '.cr-audio-sheet > h2' ) ).toHaveText( 'Transcript' );
		expect( await page.locator( '.cr-audio-sheet' ).evaluate( ( node ) => Boolean( node.closest( '.e-content' ) ) ) ).toBe( true );
		expect( await page.locator( '.e-content [hidden] .u-audio, .e-content .pk-entry-props[hidden]' ).count(), 'trailing hidden entry data stays outside e-content' ).toBe( 0 );
		expect( await page.locator( 'audio' ).count() ).toBe( 1 );
		expect( await page.getByText( '[00:00]', { exact: false } ).count() ).toBe( 1 );
	} );

	test( 'single counters and transcript presence follow stored fixtures', async ( { page, request } ) => {
		for ( const [ key, counter, transcript ] of [ [ 'A01', '01', true ], [ 'A06', '02', true ], [ 'B07', '02', false ], [ 'A04', '01', false ], [ 'C01', '00', false ] ] ) {
			await openFixture( page, request, key );
			await expect( page.locator( '.cr-audio-machine__counter' ), key ).toHaveText( counter );
			await expect( page.locator( '.cr-audio-sheet > h2', { hasText: 'Transcript' } ), key ).toHaveCount( transcript ? 1 : 0 );
		}
	} );

	test( 'A01 uses Able Player when mapped, A03 is native, A04 is an iframe and D02 keeps its link', async ( { page, request } ) => {
		await openFixture( page, request, 'A01' );
		const able = page.locator( '.cr-audio-machine__player .able, .cr-audio-machine__player .able-player, .cr-audio-machine__player .able-wrapper' );
		test.skip( 0 === await able.count(), 'Able Player is not mapped in this stack; A01 enhancement cannot be checked' );
		await expect( able.first() ).toBeVisible();

		await openFixture( page, request, 'A03' );
		await expect( page.locator( '.cr-audio-machine__player audio' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-audio-machine__player .able, .cr-audio-machine__player iframe' ) ).toHaveCount( 0 );

		await openFixture( page, request, 'A04' );
		await expect( page.locator( '.cr-audio-machine__player iframe' ) ).toHaveCount( 1 );

		await openFixture( page, request, 'D02' );
		const authored = page.locator( '.e-content p a.u-audio[href="https://audio.example.org/clip-two.mp3"]' );
		await expect( authored ).toHaveCount( 1 );
		await expect( page.locator( '.cr-audio-machine__player audio[src="https://audio.example.org/clip-two.mp3"]' ) ).toHaveCount( 1 );
	} );

	test( 'all native audio drops autoplay and preloads metadata', async ( { page, request } ) => {
		for ( const key of [ 'A03', 'A06', 'D02', 'X04' ] ) {
			await openFixture( page, request, key );
			for ( const audio of await page.locator( 'audio' ).all() ) {
				expect( await audio.getAttribute( 'controls' ), `${ key } audio has controls` ).not.toBeNull();
				await expect( audio, `${ key } audio preloads metadata` ).toHaveAttribute( 'preload', 'metadata' );
				expect( await audio.getAttribute( 'autoplay' ), `${ key } audio has no autoplay` ).toBeNull();
			}
		}
	} );

	test( 'B05 keeps stored media behind the password form', async ( { page, request } ) => {
		await openFixture( page, request, 'B05' );
		await expect( page.locator( 'input[type="password"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-audio-single, .cr-audio-machine, audio, .cr-audio-sheet' ) ).toHaveCount( 0 );
	} );

	test( 'single grid is two columns from 64rem and stacked below, without overflow', async ( { page, request } ) => {
		for ( const [ width, columns ] of [ [ 1440, 2 ], [ 1024, 2 ], [ 800, 1 ], [ 375, 1 ], [ 320, 1 ] ] ) {
			await openFixture( page, request, 'A01', width );
			expect( await page.locator( '.cr-audio-single' ).evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length ), `${ width }px columns` ).toBe( columns );
			await expectNoHorizontalScroll( page, `${ width }px single has no horizontal scroll` );
		}
	} );
} );

test.describe( 'audio Stream and front page', () => {
	test( 'S1-S5 keep one link, duration only when stored, and no transcript or gallery furniture', async ( { page } ) => {
		await openStream( page );
		for ( const key of [ 'S1', 'S2', 'S3', 'S4', 'S5' ] ) {
			const item = streamItemFor( page, key );
			if ( 'S5' === key && 0 === await item.count() ) {
				test.skip( true, 'S5 is local-only and is absent from this stack' );
			}
			skipMissing( 0 === await item.count(), `Stream fixture ${ key } is absent; run seed-243.php --set=stream` );
			const card = item.locator( 'article.k-audio' ).first();
			const links = await linkReport( card );
			expect( links.hrefs ).toHaveLength( 1 );
			expect( await card.innerText() ).not.toMatch( /Transcript available|\+\d+/ );
			await expect( card.locator( 'button' ) ).toHaveCount( 0 );
			if ( 'S5' === key ) {
				await expect( card.locator( 'img' ) ).toHaveCount( 0 );
			}
			await expect( card.locator( '.pk-audio-duration' ) ).toHaveCount( 'S2' === key ? 0 : 1 );
			const ratio = await card.evaluate( ( node ) => {
				const li = node.closest( 'li' );
				const style = getComputedStyle( li );
				return node.offsetWidth / ( li.clientWidth - parseFloat( style.paddingLeft ) - parseFloat( style.paddingRight ) );
			} );
			expect( ratio ).toBeGreaterThanOrEqual( 0.8 );
			expect( ratio ).toBeLessThanOrEqual( 0.84 );
		}
	} );

	test( 'audio keeps the shipped cassette geometry beside artless listen and watch controls', async ( { page } ) => {
		await openStream( page );
		const audio = streamItemFor( page, 'S1' ).locator( 'article.k-audio' );
		const listen = page.locator( 'article.k-listen:not(:has(img))' ).first();
		const watch = page.locator( 'article.k-watch:not(:has(img))' ).first();
		test.skip( 0 === await audio.count(), 'no S1 audio Stream card' );
		test.skip( 0 === await listen.count() || 0 === await watch.count(), 'artless listen and watch control cards are absent from this Stream page' );
		const shape = ( locator ) => locator.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return { display: style.display, aspectRatio: style.aspectRatio, minHeight: style.minHeight, padding: style.padding };
		} );
		expect( await shape( audio ) ).toEqual( await shape( listen ) );
		expect( await shape( audio ) ).toEqual( await shape( watch ) );
	} );

	test( 'archive makes no request to fixture audio hosts', async ( { page } ) => {
		const hosts = [];
		page.on( 'request', ( request ) => hosts.push( new URL( request.url() ).hostname ) );
		await openArchive( page );
		for ( const host of EXTERNAL_AUDIO_HOSTS ) {
			expect( hosts, `no request reaches ${ host }` ).not.toContain( host );
		}
	} );

	test( 'Stream makes no request to fixture audio hosts', async ( { page } ) => {
		const hosts = [];
		page.on( 'request', ( request ) => hosts.push( new URL( request.url() ).hostname ) );
		await openStream( page );
		test.skip( 0 === await page.locator( `a[href*="${ slugOf( 'S1' ) }"]` ).count(), 'Stream fixture set is absent; run seed-243.php --set=stream' );
		for ( const host of EXTERNAL_AUDIO_HOSTS ) {
			expect( hosts, `no request reaches ${ host }` ).not.toContain( host );
		}
	} );

	test( 'front-page Stream lane can render an audio card', async ( { page } ) => {
		await page.goto( '/', { waitUntil: 'load' } );
		const card = page.locator( '.cr-fieldnotes article.k-audio, main article.k-audio' ).first();
		test.skip( 0 === await card.count(), 'the front-page lane has no audio fixture in its current page window' );
		await expect( card ).toBeVisible();
	} );
} );

test.describe( 'audio privacy', () => {
	test( 'A07, B05 and dev-only X05 do not expose fixture secrets across public HTML or REST', async ( { page, request } ) => {
		const forbidden = [ 'Maple Street', 'seed-243-pass', 'maple-street-cafe.m4a', '0.5000', '-30.5000' ];
		for ( const key of [ 'A07', 'B05', 'X05' ] ) {
			const path = await fixturePath( request, key );
			if ( 'X05' === key && ! path ) {
				test.skip( true, 'X05 is dev-only and is absent from this stack' );
			}
			skipMissing( ! path, `fixture ${ key } is absent` );
			const response = await page.goto( path, { waitUntil: 'load' } );
			expect( response?.status() ).toBeLessThan( 500 );
			const report = await page.evaluate( () => {
				const clone = document.documentElement.cloneNode( true );
				clone.querySelectorAll( '[hidden] .u-audio, audio' ).forEach( ( node ) => node.remove() );
				return { text: document.body.innerText, html: clone.outerHTML };
			} );
			for ( const secret of forbidden ) {
				expect( `${ report.text }\n${ report.html }`, `${ key } omits ${ secret } outside the two allowed audio values` ).not.toContain( secret );
			}
			const rest = await request.get( `/wp-json/wp/v2/posts?slug=${ slugOf( key ) }&context=view` );
			const posts = await rest.json();
			// The stored player's file URL is public by design (it is what the page shows), so the rendered fields lose their audio elements and u-audio nodes first.
			const body = await page.evaluate( ( items ) => JSON.stringify( items.map( ( item ) => {
				for ( const field of [ 'content', 'excerpt' ] ) {
					if ( item[ field ]?.rendered ) {
						const doc = new DOMParser().parseFromString( item[ field ].rendered, 'text/html' );
						doc.querySelectorAll( 'audio, .u-audio' ).forEach( ( node ) => node.remove() );
						item[ field ].rendered = doc.body.innerHTML;
					}
				}
				return item;
			} ) ), posts );
			for ( const secret of forbidden ) {
				expect( body, `${ key } REST omits ${ secret }` ).not.toContain( secret );
			}
		}
	} );
} );
