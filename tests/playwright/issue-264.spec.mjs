// `CR_SPEC=issue-264.spec.mjs`: W6-T264 issue archive, single and Stream
// contracts. Read-only GETs; needs fixtures from assets/264-issues/seed-264.php.
// Set CR_ISSUE_REQUIRE_SEED=1 for an orchestrated fixture run. Remote runs use
// CR_BASE_URL and CR_ALLOW_REMOTE through the shared config. This file never
// sets or changes a user agent.
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_ISSUE_ARCHIVE_PATH || '/kind/issue/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_ISSUE_STREAM_PATH || '/stream/';
const REQUIRE_SEED = '1' === process.env.CR_ISSUE_REQUIRE_SEED;
const DEV = '1' === process.env.CR_ISSUE_DEV;

const RACK = '.cr-issue-rack > section.pkiw-group';
const LANE = '.pkiw-issue-lane';
const TICKET = 'article.cr-claim-ticket';
const VIOLET = 'rgb(36, 28, 74)';
const IVORY = 'rgb(251, 250, 245)';
const SKY_BLUE = 'rgb(142, 202, 230)';
const LIGHT_ORANGE = 'rgb(254, 226, 195)';
const SELECTIVE_YELLOW = 'rgb(255, 183, 3)';
const CERULEAN = 'rgb(18, 103, 130)';
const PRUSSIAN_BLUE = 'rgb(2, 48, 71)';
const PAPER_COLORS = {
	peach: LIGHT_ORANGE,
	sky: SKY_BLUE,
	ivory: IVORY,
};
const PAGE_MAP = {
	1: [
		[ 'acorn-ledger', [ [ 'open', 'Waiting — Open', 5 ], [ 'closed', 'Picked up — Closed', 3 ], [ 'none', 'Issue', 1 ] ] ],
		[ 'birch-notes', [ [ 'open', 'Waiting — Open', 3 ] ] ],
	],
	2: [
		[ 'birch-notes', [ [ 'open', 'Waiting — Open', 1 ], [ 'closed', 'Picked up — Closed', 4 ] ] ],
		[ 'cedar-kit-with-an-extraordinarily-long-repository-name', [ [ 'closed', 'Picked up — Closed', 3 ] ] ],
		[ 'Issue', [ [ 'open', 'Waiting — Open', 1 ], [ 'none', 'Issue', 3 ] ] ],
	],
};
const BANNED_TEXT = [
	'SAMPLE CONTENT',
	'No closed issues',
	'REPAIR SHOP',
	'POST KINDS ISSUE',
	'NO.',
	'saved post data',
	'View issue post',
	'Updated',
];

const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );
const slugOf = ( key ) => `seed-264-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const archivePath = ( number = 1 ) => 1 === number ? ARCHIVE : `${ ARCHIVE }page/${ number }/`;
const itemFor = ( page, key ) => page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_ISSUE_REQUIRE_SEED=1: ${ reason }` );
	}
	test.skip( missing, reason );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 390 ? 1000 : 1100 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function openSeeded( page, path = ARCHIVE, width = 1440 ) {
	const response = await open( page, path, width );
	skipUnlessSeeded( 404 === response?.status(), `no page at ${ path }` );
	skipUnlessSeeded( 0 === await page.locator( 'a[href*="seed-264-"]' ).count(), 'the issue 264 fixtures are not seeded here (seed-264.php)' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( 'main.single-post' ).count(), `no fixture ${ key }` );
	return page.locator( 'main.single-post' ).first();
}

async function openStream( page, width = 1440 ) {
	const response = await open( page, STREAM, width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( `a[href*="${ slugOf( 'S1' ) }"]` ).count(), 'the optional S1-S3 Stream set is not seeded' );
	return response;
}

async function assertNoOverflow( page, label ) {
	const report = await page.evaluate( () => ( {
		document: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		body: document.body.scrollWidth - document.body.clientWidth,
	} ) );
	expect( report.document, `${ label } document overflow` ).toBeLessThanOrEqual( 0 );
	expect( report.body, `${ label } body overflow` ).toBeLessThanOrEqual( 0 );
}

async function axeContrast( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations.map( ( violation ) => ( {
		id: violation.id,
		targets: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
	} ) ) );
}

async function renderedLinks( scope ) {
	return scope.locator( 'a[href]' ).evaluateAll( ( links ) => links.filter( ( link ) => ! link.closest( '[hidden]' ) && 0 < link.getClientRects().length ).map( ( link ) => link.href ) );
}

async function scopedText( scope, remove ) {
	return scope.evaluate( ( root, selectors ) => {
		const copy = root.cloneNode( true );
		copy.querySelectorAll( selectors ).forEach( ( node ) => node.remove() );
		return copy.textContent;
	}, remove );
}

function assertBannedTextAbsent( text, label ) {
	for ( const banned of BANNED_TEXT ) {
		expect( text, `${ label } must not contain ${ banned }` ).not.toContain( banned );
	}
	expect( text, `${ label } must not contain a Notes heading` ).not.toMatch( /(?:^|\n)\s*Notes\s*(?:\n|$)/i );
}

async function assertOneLinkCards( cards ) {
	for ( const card of await cards.all() ) {
		expect( await renderedLinks( card ) ).toHaveLength( 1 );
	}
}

test.describe( 'issue archive', () => {
	test( 'two pages expose the repository, lane and ticket map; page three is a 404', async ( { page } ) => {
		for ( const number of [ 1, 2 ] ) {
			await openSeeded( page, archivePath( number ) );
			const racks = page.locator( RACK );
			await expect( racks ).toHaveCount( PAGE_MAP[ number ].length );
			for ( const [ rackIndex, [ repository, lanes ] ] of PAGE_MAP[ number ].entries() ) {
				const rack = racks.nth( rackIndex );
				await expect( rack.locator( ':scope > h2.pkiw-group__heading' ) ).toHaveText( repository );
				await expect( rack.locator( LANE ) ).toHaveCount( lanes.length );
				for ( const [ laneIndex, [ status, label, count ] ] of lanes.entries() ) {
					const lane = rack.locator( LANE ).nth( laneIndex );
					expect( await lane.getAttribute( 'data-pkiw-status' ) ).toBe( status );
					await expect( lane.locator( ':scope > h3.pkiw-issue-lane__heading' ) ).toHaveText( label );
					await expect( lane.locator( ':scope > ul > li > article h4.cr-claim-ticket__title, :scope > ul > li > article.pk-card--protected h4, :scope > ul > li > article.pk-card--protected .pk-title' ) ).toHaveCount( count );
				}
			}
		}

		await openSeeded( page, archivePath( 2 ) );
		const r3 = page.locator( RACK ).filter( { has: page.getByRole( 'heading', { name: 'cedar-kit-with-an-extraordinarily-long-repository-name', exact: true } ) } );
		await expect( r3.locator( `${ LANE }[data-pkiw-status="open"]` ) ).toHaveCount( 0 );
		await expect( r3.getByRole( 'heading', { name: 'Waiting — Open', exact: true } ) ).toHaveCount( 0 );
		const hook = await r3.locator( '.pkiw-issue-lanes' ).evaluate( ( node ) => ( {
			before: getComputedStyle( node, '::before' ).content,
			after: getComputedStyle( node, '::after' ).content,
		} ) );
		expect( [ '""', 'none' ] ).toContain( hook.before );
		expect( [ '""', 'none' ] ).toContain( hook.after );

		const response = await open( page, archivePath( 3 ) );
		expect( response?.status() ).toBe( 404 );
	} );

	test( 'tickets contain only their link, number and hidden microformat properties', async ( { page } ) => {
		for ( const number of [ 1, 2 ] ) {
			await openSeeded( page, archivePath( number ) );
			const tickets = page.locator( TICKET );
			await assertOneLinkCards( tickets );
			for ( const ticket of await tickets.all() ) {
				const report = await ticket.evaluate( ( node ) => ( {
					visibleChildren: [ ...node.children ].filter( ( child ) => ! child.hidden ).map( ( child ) => child.className ),
					dates: node.querySelectorAll( '.cr-claim-ticket__props[hidden] time.dt-published[datetime]' ).length,
					authors: node.querySelectorAll( '.cr-claim-ticket__props[hidden] .p-author.h-card' ).length,
					forbidden: node.querySelectorAll( '.cr-claim-ticket__repo, .cr-claim-ticket__status, .pk-kindlabel, .pk-excerpt, .pk-stream-date, .pk-link' ).length,
				} ) );
				expect( report.visibleChildren.every( ( value ) => /cr-claim-ticket__(?:title|number)/.test( value ) ) ).toBe( true );
				expect( report.dates ).toBe( 1 );
				expect( report.authors ).toBe( 1 );
				expect( report.forbidden ).toBe( 0 );
			}

			for ( const ticket of await tickets.all() ) {
				const repository = await ticket.locator( 'xpath=ancestor::section[contains(@class,"pkiw-group")][1]/h2' ).innerText();
				await expect( ticket.locator( '.cr-claim-ticket__props[hidden] .u-in-reply-to.h-cite' ) ).toHaveCount( 'Issue' === repository.trim() ? 0 : 1 );
			}
		}

		await openSeeded( page, archivePath( 2 ) );
		const x01 = itemFor( page, 'X01' ).locator( 'article' );
		await expect( x01 ).toHaveCount( 1 );
		expect( await renderedLinks( x01 ) ).toHaveLength( 1 );
		await expect( x01.locator( '.cr-claim-ticket__number, .u-in-reply-to' ) ).toHaveCount( 0 );
		const x01Lines = ( await x01.innerText() ).split( '\n' ).map( ( line ) => line.trim() ).filter( Boolean );
		expect( x01Lines ).toHaveLength( 1 );
		expect( x01Lines[ 0 ] ).toContain( 'Locked fictional issue' );
	} );

	test( 'responsive lanes, ticket grids and rack height match the four target widths', async ( { page } ) => {
		for ( const width of [ 1440, 800, 375, 320 ] ) {
			await openSeeded( page, ARCHIVE, width );
			const firstRack = page.locator( RACK ).first();
			const lanes = firstRack.locator( LANE );
			const geometry = await lanes.evaluateAll( ( nodes ) => nodes.map( ( node ) => {
				const box = node.getBoundingClientRect();
				const list = node.querySelector( '.pkiw-issue-lane__items' );
				return {
					status: node.dataset.pkiwStatus,
					top: Math.round( box.top ),
					left: Math.round( box.left ),
					bottom: Math.round( box.bottom ),
					columns: getComputedStyle( list ).gridTemplateColumns.split( ' ' ).length,
				};
			} ) );
			expect( geometry.map( ( lane ) => lane.status ) ).toEqual( [ 'open', 'closed', 'none' ] );
			if ( width >= 640 ) {
				expect( Math.abs( geometry[ 0 ].top - geometry[ 1 ].top ) ).toBeLessThanOrEqual( 2 );
				expect( geometry[ 0 ].left ).toBeLessThan( geometry[ 1 ].left );
				expect( geometry[ 2 ].top ).toBeGreaterThanOrEqual( Math.max( geometry[ 0 ].bottom, geometry[ 1 ].bottom ) - 2 );
			} else {
				expect( geometry[ 1 ].top ).toBeGreaterThanOrEqual( geometry[ 0 ].bottom - 2 );
				expect( geometry[ 2 ].top ).toBeGreaterThanOrEqual( geometry[ 1 ].bottom - 2 );
			}
			expect( geometry.every( ( lane ) => lane.columns === ( width >= 1024 ? 2 : 1 ) ) ).toBe( true );

			const contains = await firstRack.evaluate( ( rack ) => {
				const rackBox = rack.getBoundingClientRect();
				const laneBottom = Math.max( ...[ ...rack.querySelectorAll( '.pkiw-issue-lane' ) ].map( ( lane ) => lane.getBoundingClientRect().bottom ) );
				return rackBox.bottom >= laneBottom - 1;
			} );
			expect( contains, `${ width }px rack contains its tallest lane` ).toBe( true );
			if ( 320 === width ) {
				await assertNoOverflow( page, '320px and 400 percent zoom proxy' );
			}
		}
	} );

	test( 'tab-stop count, whole-ticket focus and duplicate names remain distinct', async ( { page } ) => {
		for ( const number of [ 1, 2 ] ) {
			await openSeeded( page, archivePath( number ) );
			const query = page.locator( '.cr-issue__query' );
			const ticketItems = query.locator( `${ RACK } li.wp-block-post` );
			const pagerLinks = query.locator( 'nav[aria-label="Pagination"] a[href]' );
			const stops = await query.locator( 'a[href], button, input, select, textarea, [tabindex]' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => ! node.closest( '[hidden]' ) && 0 < node.getClientRects().length && ! node.disabled && node.tabIndex >= 0 ).length );
			expect( stops ).toBe( await ticketItems.count() + await pagerLinks.count() );

			for ( const ticket of await page.locator( TICKET ).all() ) {
				const link = ticket.locator( '.cr-claim-ticket__title > a' );
				await link.focus();
				const ring = await link.evaluate( ( node ) => {
					const style = getComputedStyle( node, '::after' );
					const ticket = node.closest( '.cr-claim-ticket' ).getBoundingClientRect();
					const anchor = node.getBoundingClientRect();
					return {
						outline: style.outlineWidth,
						insets: [ style.top, style.right, style.bottom, style.left ],
						contains: ticket.left <= anchor.left && ticket.top <= anchor.top && ticket.right >= anchor.right && ticket.bottom >= anchor.bottom,
					};
				} );
				expect( ring.outline ).toBe( '3px' );
				expect( ring.insets ).toEqual( [ '0px', '0px', '0px', '0px' ] );
				expect( ring.contains ).toBe( true );
			}
		}

		await openSeeded( page );
		const labels = [];
		for ( const [ key, repository, number ] of [ [ 'A02', 'acorn-ledger', '#102' ], [ 'B02', 'birch-notes', '#22' ] ] ) {
			let link = page.locator( `a[href*="${ slugOf( key ) }"]` ).first();
			if ( 0 === await link.count() ) {
				await openSeeded( page, archivePath( 2 ) );
				link = page.locator( `a[href*="${ slugOf( key ) }"]` ).first();
			}
			const labelledBy = ( await link.getAttribute( 'aria-labelledby' ) || '' ).trim().split( /\s+/ ).filter( Boolean );
			expect( labelledBy ).toHaveLength( 3 );
			const text = await link.evaluate( ( node, ids ) => ids.map( ( id ) => document.getElementById( id )?.textContent.trim() || '' ).join( ' ' ), labelledBy );
			expect( text ).toContain( 'Export drops the last row' );
			expect( text ).toContain( repository );
			expect( text ).toContain( number );
			labels.push( text );
		}
		expect( labels[ 0 ] ).not.toBe( labels[ 1 ] );
	} );

	test( 'DOM hierarchy and each ticket row match visual order', async ( { page } ) => {
		for ( const width of [ 1440, 800, 375, 320 ] ) {
			for ( const number of [ 1, 2 ] ) {
				await openSeeded( page, archivePath( number ), width );
				for ( const rack of await page.locator( RACK ).all() ) {
					const hierarchy = await rack.evaluate( ( node ) => {
						const h2 = node.querySelector( ':scope > h2' ).getBoundingClientRect();
						return [ ...node.querySelectorAll( '.pkiw-issue-lane' ) ].map( ( lane ) => {
							const h3 = lane.querySelector( ':scope > h3' ).getBoundingClientRect();
							const tickets = [ ...lane.querySelectorAll( ':scope > ul > li' ) ];
							const dom = tickets.map( ( ticket, index ) => ( { index, box: ticket.getBoundingClientRect() } ) );
							const visual = [ ...dom ].sort( ( a, b ) => Math.abs( a.box.top - b.box.top ) > 2 ? a.box.top - b.box.top : a.box.left - b.box.left ).map( ( item ) => item.index );
							return { headings: h2.top <= h3.top, ticketsAfterHeading: dom.every( ( item ) => item.box.top >= h3.bottom - 2 ), dom: dom.map( ( item ) => item.index ), visual };
						} );
					} );
					for ( const lane of hierarchy ) {
						expect( lane.headings ).toBe( true );
						expect( lane.ticketsAfterHeading ).toBe( true );
						expect( lane.visual ).toEqual( lane.dom );
					}
				}
			}
		}
	} );

	test( 'the pager follows every rack and exposes core pagination semantics', async ( { page } ) => {
		for ( const number of [ 1, 2 ] ) {
			await openSeeded( page, archivePath( number ) );
			const pager = page.locator( 'nav[aria-label="Pagination"]' );
			await expect( pager ).toHaveCount( 1 );
			expect( await pager.evaluate( ( node ) => Boolean( node.closest( 'section.pkiw-group' ) ) ) ).toBe( false );
			expect( await pager.evaluate( ( node ) => {
				const rack = document.querySelector( '.cr-issue-rack' );
				return !! ( rack.compareDocumentPosition( node ) & Node.DOCUMENT_POSITION_FOLLOWING );
			} ) ).toBe( true );
			await expect( pager.locator( '[aria-current="page"]' ) ).toHaveText( String( number ) );
			await expect( pager.getByRole( 'link', { name: 'Previous', exact: true } ) ).toHaveCount( 1 === number ? 0 : 1 );
		}
	} );

	test( 'archive has no banned interface copy or axe contrast violations in every color mode', async ( { page } ) => {
		await openSeeded( page );
		assertBannedTextAbsent( await scopedText( page.locator( '.cr-issue' ), '.cr-claim-ticket__title, .pk-title, .pk-excerpt, .e-content' ), 'archive chrome' );
		for ( const media of [ { colorScheme: 'light', forcedColors: 'none' }, { colorScheme: 'dark', forcedColors: 'none' }, { forcedColors: 'active' } ] ) {
			await page.emulateMedia( media );
			expect( await axeContrast( page ), JSON.stringify( media ) ).toEqual( [] );
		}
	} );

	test( 'reduced motion removes the lift and no-preference lifts by two pixels', async ( { page } ) => {
		await openSeeded( page );
		const ticket = page.locator( TICKET ).first();
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await ticket.hover();
		expect( await ticket.evaluate( ( node ) => getComputedStyle( node ).transform ) ).toBe( 'none' );

		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await ticket.hover();
		await expect.poll( () => ticket.evaluate( ( node ) => new DOMMatrix( getComputedStyle( node ).transform ).m42 ) ).toBe( -2 );
	} );

	test( 'dark mode keeps physical paper, ink and rack colors fixed', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		await openSeeded( page );
		const papers = new Set();
		for ( const ticket of await page.locator( TICKET ).all() ) {
			const paint = await ticket.evaluate( ( node ) => ( {
				paper: [ ...node.classList ].find( ( name ) => name.startsWith( 'cr-claim-ticket--' ) )?.replace( 'cr-claim-ticket--', '' ),
				background: getComputedStyle( node ).backgroundColor,
				color: getComputedStyle( node ).color,
				title: getComputedStyle( node.querySelector( '.cr-claim-ticket__title a' ) ).color,
				tint: getComputedStyle( node, '::before' ).content,
			} ) );
			expect( paint.background ).toBe( PAPER_COLORS[ paint.paper ] );
			expect( paint.color ).toBe( VIOLET );
			expect( paint.title ).toBe( VIOLET );
			expect( paint.tint ).toBe( 'none' );
			papers.add( paint.background );
		}
		expect( [ ...papers ].sort() ).toEqual( Object.values( PAPER_COLORS ).sort() );
		for ( const rack of await page.locator( RACK ).all() ) {
			expect( await rack.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( PRUSSIAN_BLUE );
		}
	} );
} );

test.describe( 'issue singles', () => {
	test( 'A01 and A06 render one ticket h1, one date and ordered facts with status paint', async ( { page } ) => {
		for ( const [ key, status, background, color ] of [ [ 'A01', 'Open', SELECTIVE_YELLOW, VIOLET ], [ 'A06', 'Closed', CERULEAN, IVORY ] ] ) {
			const main = await openSingle( page, key );
			await expect( main.locator( '.cr-claim-ticket-head h1' ) ).toHaveCount( 1 );
			await expect( main.locator( 'h1' ) ).toHaveCount( 1 );
			await expect( main.locator( '.single-post__header, .single-post__featured' ) ).toHaveCount( 0 );
			expect( await main.locator( '.pkiw-issue-facts__row > dt' ).allTextContents() ).toEqual( [ 'Repository', 'Issue', 'Status', 'Date' ] );
			const date = ( await main.locator( '.pkiw-issue-facts time.dt-published' ).innerText() ).trim();
			const visible = await main.innerText();
			expect( visible.split( date ).length - 1 ).toBe( 1 );
			const pill = main.locator( `.pkiw-issue-status--${ 'Open' === status ? 'open' : 'closed' }` );
			await expect( pill ).toHaveText( status );
			expect( await pill.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( background );
			expect( await pill.evaluate( ( node ) => getComputedStyle( node ).color ) ).toBe( color );
		}

		const main = await openSingle( page, 'A01' );
		const stub = main.locator( '.cr-claim-ticket-head__stub[aria-hidden="true"]' );
		await expect( stub ).toHaveText( '#101' );
		const nav = main.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' );
		await expect( nav ).toHaveCount( 1 );
		for ( const chip of await nav.locator( 'a[href]' ).all() ) {
			const box = await chip.boundingBox();
			expect( Math.min( box.width, box.height ) ).toBeGreaterThanOrEqual( 44 );
		}
	} );

	test( 'N02 and L01 keep the template header without an empty facts sheet or stub', async ( { page } ) => {
		for ( const key of [ 'N02', 'L01' ] ) {
			const main = await openSingle( page, key );
			await expect( main.locator( ':scope > .single-post__header' ) ).toHaveCount( 1 );
			await expect( main.locator( 'h1' ) ).toHaveCount( 1 );
			await expect( main.locator( '.cr-claim-ticket-head, .cr-claim-ticket-head__stub, .pkiw-issue-facts, .cr-claim-facts' ) ).toHaveCount( 0 );
		}
	} );

	test( 'X01 exposes only the core password form', async ( { page } ) => {
		const main = await openSingle( page, 'X01' );
		await expect( main.locator( 'form.post-password-form' ) ).toHaveCount( 1 );
		await expect( main.locator( '.cr-claim-ticket-head, .pkiw-issue-facts, .cr-claim-body, .cr-claim-ticket-head__stub' ) ).toHaveCount( 0 );
		expect( await main.innerText() ).not.toMatch( /acorn-ledger|#110|Waiting|Open/i );
	} );

	test( 'the four required singles fit 320 pixels', async ( { page } ) => {
		for ( const key of [ 'A01', 'N02', 'L01', 'X01' ] ) {
			await openSingle( page, key, 320 );
			await assertNoOverflow( page, `${ key } single at 320px` );
		}
	} );

	test( 'single chrome has no banned copy and A01 has no axe contrast violations', async ( { page } ) => {
		const main = await openSingle( page, 'A01' );
		assertBannedTextAbsent( await scopedText( main, '.cr-claim-ticket-head__title, .cr-claim-body, .e-content' ), 'single chrome' );
		for ( const media of [ { colorScheme: 'light', forcedColors: 'none' }, { colorScheme: 'dark', forcedColors: 'none' }, { forcedColors: 'active' } ] ) {
			await page.emulateMedia( media );
			expect( await axeContrast( page ), JSON.stringify( media ) ).toEqual( [] );
		}
	} );

	test( 'dark mode keeps the single ticket and sheet paper colors fixed', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		const main = await openSingle( page, 'A01' );
		const head = await main.locator( '.cr-claim-ticket-head' ).evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, color: getComputedStyle( node ).color } ) );
		const sheet = await main.locator( '.cr-claim-sheet' ).evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, color: getComputedStyle( node ).color } ) );
		expect( head ).toEqual( { background: 'rgb(188, 181, 227)', color: VIOLET } );
		expect( sheet ).toEqual( { background: IVORY, color: VIOLET } );
	} );
} );

test.describe( 'issue Stream stubs', () => {
	test( 'S1-S3 are one-link, text-free-stub cards with the intended typography', async ( { page } ) => {
		await openStream( page );
		for ( const key of [ 'S1', 'S2', 'S3' ] ) {
			const card = itemFor( page, key ).locator( 'article.cr-claim-stub' );
			await expect( card ).toHaveCount( 1 );
			expect( await renderedLinks( card ) ).toHaveLength( 1 );
			await expect( card.locator( 'button, .pk-link' ) ).toHaveCount( 0 );
			expect( ( await card.innerText() ) ).not.toContain( 'Updated' );
			const stub = card.locator( '.cr-claim-stub__tear[aria-hidden="true"]' );
			await expect( stub ).toHaveText( '' );
			const kind = await card.locator( '.cr-claim-stub__kind' ).evaluate( ( node ) => ( { family: getComputedStyle( node ).fontFamily, size: Number.parseFloat( getComputedStyle( node ).fontSize ) } ) );
			expect( kind.family ).toContain( 'Rock Salt' );
			expect( kind.size ).toBeGreaterThanOrEqual( 16 );
		}
	} );

	test( 'Stream cards remain inside their collage columns at all four widths', async ( { page } ) => {
		for ( const width of [ 1440, 768, 375, 320 ] ) {
			await openStream( page, width );
			const failures = await page.locator( 'article.cr-claim-stub' ).evaluateAll( ( cards ) => cards.filter( ( card ) => {
				const item = card.closest( 'li.wp-block-post' ).getBoundingClientRect();
				const box = card.getBoundingClientRect();
				return box.left < item.left - 1 || box.right > item.right + 1 || card.scrollWidth > card.clientWidth + 1;
			} ).length );
			expect( failures, `${ width }px collage containment` ).toBe( 0 );
			await assertNoOverflow( page, `${ width }px Stream` );
		}
	} );

	test( 'Stream chrome has no banned copy or axe contrast violations', async ( { page } ) => {
		await openStream( page );
		assertBannedTextAbsent( await scopedText( page.locator( '.cr-stream-page' ), '.cr-claim-stub__title, .pk-excerpt, .e-content' ), 'Stream chrome' );
		for ( const media of [ { colorScheme: 'light', forcedColors: 'none' }, { colorScheme: 'dark', forcedColors: 'none' }, { forcedColors: 'active' } ] ) {
			await page.emulateMedia( media );
			expect( await axeContrast( page ), JSON.stringify( media ) ).toEqual( [] );
		}
	} );

	test( 'dark Stream paper remains ivory with violet ink', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		await openStream( page );
		for ( const card of await page.locator( 'article.cr-claim-stub' ).all() ) {
			const paint = await card.evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, color: getComputedStyle( node ).color } ) );
			expect( paint ).toEqual( { background: IVORY, color: VIOLET } );
		}
	} );
} );

test.describe( 'dev-only empty issue archive', () => {
	test.skip( ! DEV, 'set CR_ISSUE_DEV=1 before seeding the dev archive' );
	test( 'empty archive prints its header without racks or pagination', async ( { page } ) => {
		const response = await open( page, ARCHIVE );
		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'Issue' );
		await expect( page.locator( `${ RACK }, ${ TICKET }, nav[aria-label="Pagination"]` ) ).toHaveCount( 0 );
	} );
} );
