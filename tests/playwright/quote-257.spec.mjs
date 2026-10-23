// `CR_SPEC=quote-257.spec.mjs`: issue 257 quote archive, single and Stream
// contracts. Read-only GETs; seed with the vault's assets/257-quotes/seed-257.php.
// Remote runs use CR_BASE_URL and CR_ALLOW_REMOTE=1 through the shared config;
// this spec never sets or changes a user agent.
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_QUOTE_ARCHIVE_PATH || '/kind/quote/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_QUOTE_STREAM_PATH || '/stream/';
const REQUIRE_SEED = '1' === process.env.CR_QUOTE_REQUIRE_SEED;
const DEV = '1' === process.env.CR_QUOTE_DEV;
const STATE = process.env.CR_QUOTE_STATE || 'archive';
const FLIP = process.env.CR_QUOTE_FLIP || '';
const OTHER_EXTRA = Number.parseInt( process.env.CR_QUOTE_OTHER_EXTRA || '0', 10 );
const REAL_SINGLE = process.env.CR_QUOTE_REAL_SINGLE_PATH || '/?p=30';

const VIOLET = 'rgb(36, 28, 74)';
const IVORY = 'rgb(251, 250, 245)';
const CERULEAN = 'rgb(18, 103, 130)';
const SELECTIVE_YELLOW = 'rgb(255, 183, 3)';
const PHOTOCOPIER_BLACK = 'rgb(26, 23, 20)';
const WIDTHS = [ 1440, 1024, 800, 375, 320 ];
const BASE_PAGE_GROUPS = [
	[ [ 'asta-lind', 1 ], [ 'devon-reed', 3 ], [ 'lena-park', 2 ], [ 'mira-sol', 2 ] ],
	[ [ 'mira-sol', 2 ], [ 'rowan-ellis', 6 ] ],
];

const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );
const slugOf = ( key ) => `seed-257-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const archivePath = ( number = 1 ) => 1 === number ? ARCHIVE : `${ ARCHIVE }page/${ number }/`;
const otherPages = () => Math.max( 1, Math.ceil( ( 4 + Math.max( 0, OTHER_EXTRA ) ) / 8 ) );
const lastPage = () => 2 + otherPages();

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_QUOTE_REQUIRE_SEED=1: ${ reason }` );
	}
	test.skip( missing, reason );
}

async function open( page, path, width = 1440 ) {
	await page.setViewportSize( { width, height: width <= 390 ? 900 : 1000 } );
	return page.goto( path, { waitUntil: 'load' } );
}

async function openSeeded( page, path = ARCHIVE, width = 1440 ) {
	const response = await open( page, path, width );
	skipUnlessSeeded( 404 === response?.status(), `no page at ${ path }` );
	skipUnlessSeeded( 0 === await page.locator( '.pkiw-quote-entry' ).count(), 'the issue 257 fixtures are not seeded here (seed-257.php)' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( 'main.single-post' ).count(), `no fixture ${ key }` );
	return page.locator( 'main.single-post' ).first();
}

async function axeContrast( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations.map( ( violation ) => ( {
		id: violation.id,
		targets: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
	} ) ) );
}

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

const cards = ( page ) => page.locator( '.pkiw-quote-entry:not(.pkiw-quote-entry--continued)' );

const cardFor = ( page, key ) => cards( page ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();

async function sectionMap( page ) {
	return page.locator( 'section.pkiw-quote-section' ).evaluateAll( ( sections ) => sections.map( ( section ) => [
		section.getAttribute( 'data-pkiw-group' ),
		section.querySelectorAll( ':scope > ul.pkiw-quote-section__items > li.wp-block-post' ).length,
	] ) );
}

async function visibleFocusableReport( root ) {
	return root.evaluate( ( node ) => {
		const rendered = ( element ) => ! element.closest( '[hidden]' ) && 0 < element.getClientRects().length;
		const focusables = [ ...node.querySelectorAll( 'a[href], button, input, select, textarea, [tabindex]' ) ].filter( ( element ) => rendered( element ) && ! element.disabled && 0 <= element.tabIndex );
		return {
			links: [ ...node.querySelectorAll( 'a[href]' ) ].filter( rendered ).length,
			tabStops: focusables.length,
		};
	} );
}

async function resolvedPaper( locator ) {
	return locator.evaluate( ( node ) => {
		const probe = document.createElement( 'span' );
		probe.style.cssText = 'position:absolute;background-color:var(--cr-quote-paper);';
		node.append( probe );
		const value = getComputedStyle( probe ).backgroundColor;
		probe.remove();
		return value;
	} );
}

async function focusPaint( focus, painted = focus ) {
	await focus.focus();
	return painted.evaluate( ( node ) => {
		const style = getComputedStyle( node );
		return { outlineColor: style.outlineColor, outlineWidth: style.outlineWidth, shadow: style.boxShadow };
	} );
}

// --- Header (15) ------------------------------------------------------------------------------

test.describe( 'quote header (15)', () => {
	test.skip( 'archive' !== STATE, 'archive fixture state' );

	test( 'archive identity names Quote with five inert tiles, description, divider and badge', async ( { page } ) => {
		await openSeeded( page );
		const header = page.locator( 'main .cr-archive__header' );
		const h1 = header.locator( 'h1' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1.locator( '.cr-cutout__text' ) ).toHaveText( 'Quote' );
		const tileLayer = h1.locator( '.cr-cutout__tiles' );
		await expect( tileLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		const rotations = await tileLayer.locator( '.cr-cutout__tile' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => [ ...node.classList ].find( ( token ) => /^is-r\d+$/.test( token ) ) || '' ) );
		expect( rotations ).toHaveLength( 5 );
		expect( rotations.every( ( token, index ) => 0 === index || token !== rotations[ index - 1 ] ), 'adjacent tiles use different rotation tokens' ).toBe( true );
		await expect( header.locator( '.wp-block-term-description' ) ).toContainText( 'Quoted excerpt from another work, with attribution.' );
		await expect( header.locator( '.is-style-cr-marker-bar-short' ) ).toHaveCount( 1 );
		const badge = header.locator( '.cr-archive-identity__glyph[aria-hidden="true"]' );
		await expect( badge ).toHaveCount( 1 );
		expect( await badge.locator( 'a[href], [tabindex]' ).count() ).toBe( 0 );
		await expect( header.locator( '.cr-archive__tape, .cr-archive-identity__kicker, .is-style-cr-tape-label' ) ).toHaveCount( 0 );
		expect( ( await header.innerText() ).toLowerCase() ).not.toContain( 'sample content' );
	} );
} );

// --- Outline (16) -----------------------------------------------------------------------------

test.describe( 'quote outline (16)', () => {
	test.skip( 'archive' !== STATE, 'archive fixture state' );

	test( 'the page has one H1, editable H2 text and H3 author groups without a skipped level', async ( { page } ) => {
		await openSeeded( page );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		await expect( page.locator( '.pkiw-quote-authors__heading' ) ).toHaveText( 'By author' );
		await expect( page.locator( '.pkiw-quote-works > h2' ) ).toHaveText( 'By source work' );
		await expect( page.locator( '.pkiw-quote-section > h3.pkiw-quote-section__heading' ) ).toHaveCount( 4 );
		const levels = await page.locator( '.cr-archive__header h1, .cr-quote-book :is(h2, h3, h4, h5, h6)' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => Number( node.tagName.slice( 1 ) ) ) );
		expect( levels[ 0 ] ).toBe( 1 );
		for ( let index = 1; index < levels.length; index++ ) {
			expect( levels[ index ] - levels[ index - 1 ], `heading ${ index + 1 } does not skip down` ).toBeLessThanOrEqual( 1 );
		}
	} );
} );

// --- DOM and reading order (17) ---------------------------------------------------------------

test.describe( 'quote DOM and reading order (17)', () => {
	test.skip( 'archive' !== STATE, 'archive fixture state' );

	test( 'book children, visual pages and tab stops follow the semantic order at every target width', async ( { page } ) => {
		for ( const width of WIDTHS ) {
			await openSeeded( page, ARCHIVE, width );
			const report = await page.locator( '.cr-quote-book' ).evaluate( ( book ) => {
				const direct = [ ...book.children ].map( ( child ) => child.className );
				const authors = book.querySelector( ':scope > .pkiw-quote-authors' ).getBoundingClientRect();
				const entries = book.querySelector( ':scope > .cr-quote-book__entries' ).getBoundingClientRect();
				const works = book.querySelector( ':scope > .pkiw-quote-works' ).getBoundingClientRect();
				const focusables = [ ...book.querySelectorAll( 'a[href], [tabindex]' ) ].filter( ( node ) => 0 <= node.tabIndex && 0 < node.getClientRects().length );
				const categories = focusables.map( ( node ) => node.closest( '.pkiw-quote-tabs' ) ? 0 : node.closest( '.cr-quote-book__entries' ) ? 1 : node.closest( '.pkiw-quote-works' ) ? 2 : 3 );
				return { direct, authors: authors.toJSON(), entries: entries.toJSON(), works: works.toJSON(), categories };
			} );
			expect( report.direct[ 0 ] ).toContain( 'pkiw-quote-authors' );
			expect( report.direct[ 1 ] ).toContain( 'cr-quote-book__entries' );
			expect( report.direct[ 2 ] ).toContain( 'pkiw-quote-works' );
			expect( report.categories ).toEqual( [ ...report.categories ].sort( ( a, b ) => a - b ) );
			if ( 1024 <= width ) {
				expect( report.works.left ).toBeGreaterThanOrEqual( report.entries.right - 2 );
			} else {
				expect( report.entries.top ).toBeGreaterThanOrEqual( report.authors.top );
				expect( report.works.top ).toBeGreaterThanOrEqual( report.entries.bottom - 2 );
			}
			const cardOrder = await cards( page ).evaluateAll( ( nodes ) => nodes.map( ( node, index ) => {
				const rect = node.getBoundingClientRect();
				return { index, top: Math.round( rect.top ), left: Math.round( rect.left ) };
			} ) );
			const visualOrder = [ ...cardOrder ].sort( ( a, b ) => 2 < Math.abs( a.top - b.top ) ? a.top - b.top : a.left - b.left ).map( ( item ) => item.index );
			expect( visualOrder, `${ width }px card visual order` ).toEqual( cardOrder.map( ( item ) => item.index ) );
		}
		const book = page.locator( '.cr-quote-book' );
		expect( await page.locator( '.cr-quote-book + nav[aria-label="Pagination"], .cr-quote-book + .wp-block-query-pagination' ).count(), 'pager is the book next sibling' ).toBe( 1 );
		expect( await book.locator( 'nav[aria-label="Pagination"], .wp-block-query-pagination' ).count() ).toBe( 0 );
	} );
} );

// --- Layout and tabs (18, 19) ----------------------------------------------------------------

test.describe( 'quote layout and tabs (18, 19)', () => {
	test( 'archive state switches at 40rem and 64rem and never overflows at 320px', async ( { page } ) => {
		test.skip( 'archive' !== STATE, 'archive fixture state' );
		for ( const width of WIDTHS ) {
			await openSeeded( page, ARCHIVE, width );
			const layout = await page.locator( '.cr-quote-book' ).evaluate( ( book ) => {
				const entryList = book.querySelector( 'ul.pkiw-quote-section__items' );
				const workList = book.querySelector( '.pkiw-quote-works nav ul' );
				const tabs = book.querySelector( '.pkiw-quote-tabs' );
				const authors = book.querySelector( ':scope > .pkiw-quote-authors' );
				return {
					bookColumns: getComputedStyle( book ).gridTemplateColumns.split( ' ' ).length,
					cardColumns: getComputedStyle( entryList ).gridTemplateColumns.split( ' ' ).length,
					workColumns: getComputedStyle( workList ).gridTemplateColumns.split( ' ' ).length,
					tabFlow: getComputedStyle( tabs ).position,
					tabsLeft: tabs.getBoundingClientRect().left,
					authorsLeft: authors.getBoundingClientRect().left,
				};
			} );
			expect( layout.bookColumns, `${ width }px book columns` ).toBe( 1024 <= width ? 2 : 1 );
			expect( layout.cardColumns, `${ width }px card columns` ).toBe( 640 <= width ? 2 : 1 );
			expect( layout.workColumns, `${ width }px work columns` ).toBe( 640 <= width ? 2 : 1 );
			if ( 1024 <= width ) {
				expect( layout.tabFlow ).toBe( 'absolute' );
				expect( layout.tabsLeft ).toBeLessThan( layout.authorsLeft );
			} else {
				expect( layout.tabFlow ).not.toBe( 'absolute' );
			}
			if ( 320 === width ) {
				await assertNoOverflow( page, '320px and 400 percent zoom proxy' );
			}
		}
	} );

	test( 'tabs match the collation-corrected initials and keep 44px upright Roboto Slab targets', async ( { page } ) => {
		test.skip( 'archive' !== STATE, 'archive fixture state' );
		await openSeeded( page );
		const links = page.locator( '.pkiw-quote-tabs a' );
		expect( await links.allTextContents() ).toEqual( [ 'All', 'Á', 'D', 'L', 'M', 'R' ] );
		for ( const link of await links.all() ) {
			const paint = await link.evaluate( ( node ) => {
				const box = node.getBoundingClientRect();
				const style = getComputedStyle( node );
				return { width: box.width, height: box.height, family: style.fontFamily, style: style.fontStyle, transform: style.transform };
			} );
			expect( paint.width ).toBeGreaterThanOrEqual( 44 );
			expect( paint.height ).toBeGreaterThanOrEqual( 44 );
			expect( paint.family ).toContain( 'Roboto Slab' );
			expect( paint.style ).toBe( 'normal' );
			expect( paint.transform ).toBe( 'none' );
		}
		const current = links.filter( { hasText: /^All$/ } );
		await expect( current ).toHaveAttribute( 'aria-current', 'page' );
		expect( await current.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( VIOLET );
		expect( await current.evaluate( ( node ) => getComputedStyle( node ).color ) ).toBe( IVORY );
	} );

	test( 'large state uses one page column for 26 source works at 1440px', async ( { page } ) => {
		test.skip( 'large' !== STATE, 'seed with --state=large and set CR_QUOTE_STATE=large' );
		await openSeeded( page );
		const works = page.locator( '.pkiw-quote-works.is-long' );
		await expect( works ).toHaveAttribute( 'data-pkiw-works', '26' );
		expect( await page.locator( '.cr-quote-book' ).evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length ) ).toBe( 1 );
		expect( await works.locator( 'nav ul' ).evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length ) ).toBe( 1 );
	} );
} );

// --- Cards (20, 21) ---------------------------------------------------------------------------

test.describe( 'quote cards (20, 21)', () => {
	test.skip( 'archive' !== STATE, 'archive fixture state' );

	test( 'cards use their quote paper, sharp rules and the specified quote and cite type', async ( { page } ) => {
		await openSeeded( page );
		for ( const card of await cards( page ).all() ) {
			const paint = await card.evaluate( ( node ) => {
				const style = getComputedStyle( node );
				const text = node.querySelector( '.pkiw-quote-entry__text' );
				const cite = node.querySelector( '.pkiw-quote-entry__cite' );
				return {
					background: style.backgroundColor,
					border: `${ style.borderTopWidth } ${ style.borderTopStyle }`,
					radius: style.borderRadius,
					shadow: style.boxShadow,
					transform: style.transform,
					textFamily: text ? getComputedStyle( text ).fontFamily : '',
					citeFamily: cite ? getComputedStyle( cite ).fontFamily : '',
					citeWeight: cite ? getComputedStyle( cite ).fontWeight : '',
					citeColor: cite ? getComputedStyle( cite ).color : '',
				};
			} );
			expect( paint.background ).toBe( await resolvedPaper( card ) );
			expect( paint.border ).toBe( '1px solid' );
			expect( paint.radius ).toBe( '0px' );
			expect( paint.shadow ).toBe( 'none' );
			expect( paint.transform ).toBe( 'none' );
			if ( paint.textFamily ) {
				expect( paint.textFamily ).toContain( 'Roboto Slab' );
			}
			if ( paint.citeFamily ) {
				expect( paint.citeFamily ).toContain( 'Barlow' );
				expect( paint.citeWeight ).toBe( '600' );
				expect( paint.citeColor ).toBe( CERULEAN );
			}
			expect( await card.locator( ':is(img, .pk-badge, .pk-kindlabel, .pk-stream-date, [class*="location"])' ).count() ).toBe( 0 );
			expect( ( await card.innerText() ).toLowerCase() ).not.toContain( 'saved' );
		}
		const q05 = cardFor( page, 'Q05' );
		await expect( q05 ).toHaveCount( 1 );
		expect( await q05.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( await resolvedPaper( q05 ) );
		const q07 = cardFor( page, 'Q07' ).locator( 'blockquote.pkiw-quote-entry__text' );
		await expect( q07 ).toHaveClass( /p-summary/ );
		expect( ( await q07.innerText() ).trim().endsWith( '…' ) ).toBe( true );
	} );

	test( 'every card has one tab stop and a unique name resolved from visible quote and cite text', async ( { page } ) => {
		await openSeeded( page );
		const names = [];
		for ( const card of await cards( page ).all() ) {
			expect( await visibleFocusableReport( card ) ).toEqual( { links: 1, tabStops: 1 } );
			const link = card.locator( 'a[href]' );
			const name = await link.evaluate( ( node ) => {
				const ids = ( node.getAttribute( 'aria-labelledby' ) || '' ).split( /\s+/ ).filter( Boolean );
				return ids.length ? ids.map( ( id ) => document.getElementById( id )?.textContent?.trim() || '' ).join( ' ' ).replace( /\s+/g, ' ' ).trim() : node.textContent.trim();
			} );
			expect( name.length ).toBeGreaterThan( 0 );
			names.push( name );
		}
		expect( new Set( names ).size, 'card accessible names are unique on the page' ).toBe( names.length );
		const q05 = cardFor( page, 'Q05' );
		await expect( q05 ).toHaveClass( /h-entry/ );
		await expect( q05.locator( 'time.dt-published[hidden]' ) ).toHaveCount( 1 );
		await expect( q05.locator( '.p-author.h-card' ) ).toHaveCount( 1 );
		await expect( q05.locator( '.u-quotation-of.h-cite[hidden] .p-name' ) ).toHaveText( 'Weather of Attention' );
		await expect( q05.locator( '.u-quotation-of.h-cite[hidden] .p-author' ) ).toHaveText( 'Lena Park' );
		await expect( q05.locator( '.u-quotation-of.h-cite[hidden] data.u-url' ) ).toHaveAttribute( 'value', 'https://example.org/q05' );
	} );

	test( 'each card ends at its own quote and cite, even beside a taller row partner', async ( { page } ) => {
		test.skip( '' !== FLIP, 'unflipped archive fixture state' );
		let unevenRows = 0;
		for ( const number of [ 1, 2, 3 ] ) {
			await openSeeded( page, archivePath( number ) );
			const report = await page.locator( 'ul.pkiw-quote-section__items' ).evaluateAll( ( lists ) => lists.flatMap( ( list ) => {
				const rows = new Map();
				for ( const card of list.querySelectorAll( ':scope > li > .pkiw-quote-entry' ) ) {
					const style = getComputedStyle( card );
					const box = card.getBoundingClientRect();
					const content = [ ...card.children ]
						.filter( ( child ) => ! child.hidden && 'absolute' !== getComputedStyle( child ).position && child.getBoundingClientRect().height > 0 )
						.reduce( ( bottom, child ) => Math.max( bottom, child.getBoundingClientRect().bottom ), box.top );
					const fitted = content - box.top + parseFloat( style.paddingBottom ) + parseFloat( style.borderBottomWidth );
					const top = Math.round( box.top );
					rows.set( top, [ ...( rows.get( top ) || [] ), box.height ] );
					card.dataset.fitSlack = String( Math.round( box.height - Math.max( fitted, parseFloat( style.minHeight ) || 0 ) ) );
				}
				return [ ...list.querySelectorAll( ':scope > li > .pkiw-quote-entry' ) ].map( ( card ) => ( {
					slack: Number( card.dataset.fitSlack ),
					uneven: ( rows.get( Math.round( card.getBoundingClientRect().top ) ) || [] ).some( ( height ) => Math.abs( height - card.getBoundingClientRect().height ) > 24 ),
				} ) );
			} ) );
			for ( const [ index, card ] of report.entries() ) {
				expect( card.slack, `page ${ number } card ${ index + 1 } blank paper below its content` ).toBeLessThanOrEqual( 2 );
			}
			unevenRows += report.filter( ( card ) => card.uneven ).length;
		}
		expect( unevenRows, 'the fixtures pair a short card with a long one somewhere on pages 1 to 3' ).toBeGreaterThan( 0 );
	} );
} );

// --- Privacy flips (22) -----------------------------------------------------------------------

test.describe( 'quote privacy flips (22)', () => {
	test.skip( ! [ 'Q16:private', 'Q04:password' ].includes( FLIP ), 'set CR_QUOTE_FLIP to the flip applied by seed-257.php' );

	test( 'the applied fixture flip changes groups, tabs and works without leaking stored citation data', async ( { page } ) => {
		await openSeeded( page );
		if ( 'Q16:private' === FLIP ) {
			await expect( page.locator( '.pkiw-quote-tabs a', { hasText: 'Á' } ) ).toHaveCount( 0 );
			await expect( page.locator( '[data-pkiw-group="asta-lind"]' ) ).toHaveCount( 0 );
			await expect( page.locator( '.pkiw-quote-work', { hasText: 'Northern Ledger' } ) ).toHaveCount( 0 );
			await expect( page.locator( '.pkiw-quote-works' ) ).toHaveAttribute( 'data-pkiw-works', '5' );
		} else {
			await expect( page.locator( '[data-pkiw-group="lena-park"] li.wp-block-post' ) ).toHaveCount( 1 );
			const weather = page.locator( '.pkiw-quote-work', { hasText: 'Weather of Attention' } );
			await expect( weather.locator( '.pkiw-quote-work__count' ) ).toHaveText( '1 quote' );
			let protectedQ04 = null;
			// Q04 is the newest Other post, so it lands on page 2 once Lena Park drops to one quote.
			for ( let number = 2; number <= lastPage(); number++ ) {
				await openSeeded( page, archivePath( number ) );
				const candidate = cardFor( page, 'Q04' );
				if ( await candidate.count() ) {
					protectedQ04 = candidate;
					break;
				}
			}
			expect( protectedQ04, 'Q04 remains visible as a protected title card' ).not.toBeNull();
			await expect( protectedQ04 ).toHaveClass( /pkiw-quote-entry--protected/ );
			expect( await protectedQ04.evaluate( ( node ) => node.closest( '[data-pkiw-group=""]' ) !== null ) ).toBe( true );
		}
	} );
} );

// --- Paint (23) -------------------------------------------------------------------------------

test.describe( 'quote paint (23)', () => {
	test.skip( 'archive' !== STATE, 'archive fixture state' );

	test( 'the book has hard token paint, no blur, gradient, filter, tiny text or display font', async ( { page } ) => {
		await openSeeded( page );
		const book = page.locator( '.cr-quote-book' );
		expect( await book.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( VIOLET );
		const violations = await book.evaluate( ( root ) => {
			const output = [];
			for ( const node of [ root, ...root.querySelectorAll( '*' ) ] ) {
				for ( const pseudo of [ null, '::before', '::after' ] ) {
					const style = getComputedStyle( node, pseudo );
					if ( 'none' !== style.backgroundImage && style.backgroundImage.includes( 'gradient' ) ) output.push( `${ node.tagName }${ pseudo || '' } gradient` );
					if ( 'none' !== style.filter ) output.push( `${ node.tagName }${ pseudo || '' } filter=${ style.filter }` );
					if ( 'none' !== style.boxShadow ) {
						const shadows = style.boxShadow.replace( /rgba?\([^)]*\)/g, '' ).split( /,(?![^()]*\))/ );
						for ( const shadow of shadows ) {
							const lengths = shadow.match( /-?\d*\.?\d+px/g ) || [];
							if ( 2 < lengths.length && 0 < Number.parseFloat( lengths[ 2 ] ) ) output.push( `${ node.tagName }${ pseudo || '' } blur=${ lengths[ 2 ] }` );
						}
					}
				}
				const style = getComputedStyle( node );
				if ( 0 < node.getClientRects().length && node.textContent.trim() && Number.parseFloat( style.fontSize ) < 12 ) output.push( `${ node.tagName } font-size=${ style.fontSize }` );
				if ( style.fontFamily.includes( 'Rock Salt' ) ) output.push( `${ node.tagName } font=${ style.fontFamily }` );
			}
			return output;
		} );
		expect( violations ).toEqual( [] );
		expect( await axeContrast( page ) ).toEqual( [] );
	} );
} );

// --- Dark mode (24) ---------------------------------------------------------------------------

test.describe( 'quote dark mode (24)', () => {
	test.skip( 'archive' !== STATE, 'archive fixture state' );

	test( 'both dark mechanisms keep the book light while the page and hard shadow switch', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		await openSeeded( page );
		const book = page.locator( '.cr-quote-book' );
		const paint = async () => ( {
			body: await page.locator( 'body' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ),
			page: await book.evaluate( ( node ) => getComputedStyle( node, '::before' ).backgroundColor ),
			shadow: await book.evaluate( ( node ) => getComputedStyle( node ).boxShadow ),
			stripLink: await page.locator( '.pkiw-quote-section__heading a' ).first().evaluate( ( node ) => getComputedStyle( node ).color ),
		} );
		let colors = await paint();
		expect( colors.body ).toBe( PHOTOCOPIER_BLACK );
		expect( colors.page ).toBe( IVORY );
		expect( colors.shadow ).toContain( SELECTIVE_YELLOW );
		expect( colors.stripLink ).toBe( VIOLET );
		let protectedLinkColor = '';
		for ( let number = 3; number <= lastPage(); number++ ) {
			await openSeeded( page, archivePath( number ) );
			const protectedLink = cardFor( page, 'C04' ).locator( 'a.u-url' );
			if ( await protectedLink.count() ) {
				protectedLinkColor = await protectedLink.evaluate( ( node ) => getComputedStyle( node ).color );
				break;
			}
		}
		expect( protectedLinkColor, 'link on a light quote paper' ).toBe( CERULEAN );
		await openSeeded( page );

		await page.locator( 'html' ).evaluate( ( root ) => root.removeAttribute( 'data-theme' ) );
		colors = await paint();
		expect( colors.page, 'prefers-color-scheme mechanism' ).toBe( IVORY );
		expect( colors.stripLink ).toBe( VIOLET );
		await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'data-theme', 'light' ) );
		expect( ( await paint() ).body, 'explicit light overrides dark media' ).not.toBe( PHOTOCOPIER_BLACK );
		await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'data-theme', 'dark' ) );
		expect( ( await paint() ).body, 'explicit dark mechanism' ).toBe( PHOTOCOPIER_BLACK );
	} );
} );

// --- Focus, forced colors and reduced motion (25) ---------------------------------------------

test.describe( 'quote focus, forced colors and reduced motion (25)', () => {
	test.skip( 'archive' !== STATE, 'archive fixture state' );

	test( 'interactive book parts get the site focus halo', async ( { page } ) => {
		await openSeeded( page );
		const targets = [
			[ page.locator( '.pkiw-quote-tabs a' ).first(), page.locator( '.pkiw-quote-tabs a' ).first() ],
			[ page.locator( '.pkiw-quote-entry__link' ).first(), page.locator( '.pkiw-quote-entry__link' ).first() ],
			[ page.locator( '.pkiw-quote-section__heading a' ).first(), page.locator( '.pkiw-quote-section__heading a' ).first() ],
			[ page.locator( '.pkiw-quote-work__title' ).first(), page.locator( 'li.pkiw-quote-work' ).first() ],
			[ page.locator( 'nav[aria-label="Pagination"] a' ).first(), page.locator( 'nav[aria-label="Pagination"] a' ).first() ],
		];
		for ( const [ focus, painted ] of targets ) {
			const result = await focusPaint( focus, painted );
			expect( result.outlineWidth ).toBe( '3px' );
			expect( result.outlineColor === SELECTIVE_YELLOW || result.shadow.includes( SELECTIVE_YELLOW ) ).toBe( true );
			expect( result.shadow ).toContain( '7px' );
		}
	} );

	test( 'forced colors removes decorative fills and reduced motion removes the lift', async ( { page } ) => {
		await openSeeded( page );
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await page.locator( '.cr-quote-book' ).evaluate( ( book ) => {
			const card = book.querySelector( '.pkiw-quote-entry' );
			const tab = book.querySelector( '.pkiw-quote-tabs a' );
			const current = book.querySelector( '.pkiw-quote-tabs a[aria-current="page"]' );
			const style = ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }`;
			return {
				book: style( book ), card: style( card ), tab: style( tab ),
				pageBlock: getComputedStyle( book, '::before' ).display,
				strip: getComputedStyle( book.querySelector( '.pkiw-quote-section__heading' ), '::before' ).display,
				currentAdjust: getComputedStyle( current ).forcedColorAdjust,
			};
		} );
		expect( forced ).toEqual( { book: '2px solid', card: '2px solid', tab: '2px solid', pageBlock: 'none', strip: 'none', currentAdjust: 'none' } );

		await page.emulateMedia( { forcedColors: 'none', reducedMotion: 'reduce' } );
		const card = page.locator( '.pkiw-quote-entry' ).first();
		await card.hover();
		expect( await card.evaluate( ( node ) => getComputedStyle( node ).transform ) ).toBe( 'none' );
		expect( Number.parseFloat( await card.evaluate( ( node ) => getComputedStyle( node ).transitionDuration ) ) ).toBeLessThanOrEqual( 0.001 );
	} );
} );

// --- States (26) ------------------------------------------------------------------------------

test.describe( 'quote states (26)', () => {
	test( 'archive page map includes the protected card and derives the Other tail from real quotes', async ( { page } ) => {
		test.skip( 'archive' !== STATE || '' !== FLIP, 'unflipped archive fixture state' );
		for ( let index = 0; index < BASE_PAGE_GROUPS.length; index++ ) {
			await openSeeded( page, archivePath( index + 1 ) );
			expect( await sectionMap( page ), `page ${ index + 1 } section map` ).toEqual( BASE_PAGE_GROUPS[ index ] );
		}
		let remaining = 4 + Math.max( 0, OTHER_EXTRA );
		for ( let number = 3; number <= lastPage(); number++ ) {
			await openSeeded( page, archivePath( number ) );
			const count = Math.min( 8, remaining );
			expect( await sectionMap( page ), `page ${ number } Other map` ).toEqual( [ [ '', count ] ] );
			remaining -= count;
		}
		const missing = await open( page, archivePath( lastPage() + 1 ) );
		expect( missing?.status() ).toBe( 404 );
	} );

	test( 'works list contains six author-aware pairs and duplicate titles carry both filters', async ( { page } ) => {
		test.skip( 'archive' !== STATE || '' !== FLIP, 'unflipped archive fixture state' );
		await openSeeded( page );
		const works = await page.locator( 'li.pkiw-quote-work' ).evaluateAll( ( items ) => items.map( ( item ) => ( {
			title: item.querySelector( '.pkiw-quote-work__title' ).textContent.trim(),
			author: item.querySelector( '.pkiw-quote-work__author' )?.textContent.trim() || '',
			count: item.querySelector( '.pkiw-quote-work__count' ).textContent.trim(),
			href: item.querySelector( 'a' ).href,
			described: item.querySelector( 'a' ).hasAttribute( 'aria-describedby' ),
		} ) ) );
		expect( works.map( ( work ) => [ work.title, work.author, work.count ] ) ).toEqual( [
			[ 'A Map of Returning', 'Devon Reed', '2 quotes' ],
			[ 'Northern Ledger', 'Ásta Lind', '1 quote' ],
			[ 'Small Fires', 'Rowan Ellis', '4 quotes' ],
			[ 'The Margins We Keep', 'Mira Sol', '3 quotes' ],
			[ 'The Margins We Keep', 'Rowan Ellis', '1 quote' ],
			[ 'Weather of Attention', 'Lena Park', '2 quotes' ],
		] );
		const shared = works.filter( ( work ) => 'The Margins We Keep' === work.title );
		expect( shared.every( ( work ) => work.described && work.href.includes( 'pkiw_quote_work=' ) && work.href.includes( 'pkiw_quote_author=' ) ) ).toBe( true );
	} );

	test( 'no-match keeps the author index and one-result filters omit pagination', async ( { page } ) => {
		test.skip( 'archive' !== STATE, 'archive fixture state' );
		await open( page, `${ ARCHIVE }?pkiw_quote_author=Nobody%20Example` );
		await expect( page.locator( '.pkiw-quote-tabs a', { hasText: /^All$/ } ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-archive-stream__empty' ) ).toContainText( 'Nothing here yet.' );
		await expect( cards( page ) ).toHaveCount( 0 );
		await open( page, `${ ARCHIVE }?pkiw_quote_author=%C3%81sta%20Lind` );
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( page.locator( 'nav[aria-label="Pagination"], .wp-block-query-pagination' ) ).toHaveCount( 0 );
	} );

	test( 'large filtered pagination preserves its author query variable', async ( { page } ) => {
		test.skip( 'large' !== STATE, 'large fixture state' );
		await openSeeded( page, `${ ARCHIVE }?pkiw_quote_author=Kit%20Example` );
		const pager = page.locator( 'nav[aria-label="Pagination"], .wp-block-query-pagination' );
		await expect( pager ).toHaveCount( 1 );
		const hrefs = await pager.locator( 'a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => link.href ) );
		expect( hrefs.length ).toBeGreaterThan( 0 );
		expect( hrefs.every( ( href ) => href.includes( 'pkiw_quote_author=Kit' ) ) ).toBe( true );
	} );

	test( 'dev pre-seed state is one uncited page with no tabs or works', async ( { page } ) => {
		test.skip( ! DEV, 'set CR_QUOTE_DEV=1 only for the pre-seed no-citation capture' );
		await open( page, ARCHIVE );
		await expect( page.locator( '.cr-quote-book' ) ).toHaveCount( 1 );
		await expect( page.locator( '.pkiw-quote-tabs, .pkiw-quote-works, nav[aria-label="Pagination"], .wp-block-query-pagination' ) ).toHaveCount( 0 );
		const groups = await page.locator( '.pkiw-quote-section' ).evaluateAll( ( sections ) => sections.map( ( section ) => section.getAttribute( 'data-pkiw-group' ) ) );
		expect( groups.every( ( group ) => '' === group ) ).toBe( true );
	} );
} );

// --- Single (27) ------------------------------------------------------------------------------

test.describe( 'quote single (27)', () => {
	test( 'Q05 keeps one titled quote sheet, one citation, standard post navigation and quote mf2', async ( { page } ) => {
		const main = await openSingle( page, 'Q05' );
		await expect( main.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( main.locator( '.cr-note-artifact--single blockquote' ) ).toHaveCount( 1 );
		await expect( main.locator( '.cr-note-artifact--single blockquote cite' ) ).toHaveCount( 1 );
		await expect( main.locator( 'nav.cr-post-nav[aria-label="Post navigation"]' ) ).toHaveCount( 1 );
		await expect( main.locator( 'nav:not(.cr-post-nav)' ) ).toHaveCount( 0 );
		await expect( main.locator( '.u-quotation-of.h-cite' ) ).toHaveCount( 1 );
		await expect( main.locator( '.u-quotation-of.h-cite .p-name' ) ).toHaveText( 'Weather of Attention' );
		await expect( main.locator( '.u-quotation-of.h-cite .p-author' ) ).toHaveText( 'Lena Park' );
	} );

	test( 'the retained Q05 and real quote sheets share the same computed layout in light and dark projects', async ( { page } ) => {
		await openSingle( page, 'Q05' );
		const selector = '.cr-note.cr-note--single';
		const properties = [ 'display', 'position', 'boxSizing', 'borderRadius', 'fontFamily', 'fontSize', 'lineHeight', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft' ];
		const q05 = await page.locator( selector ).evaluate( ( node, names ) => Object.fromEntries( names.map( ( name ) => [ name, getComputedStyle( node )[ name ] ] ) ), properties );
		const response = await open( page, REAL_SINGLE );
		test.skip( 404 === response?.status() || 0 === await page.locator( selector ).count(), `real quote control is unavailable at ${ REAL_SINGLE }` );
		const real = await page.locator( selector ).evaluate( ( node, names ) => Object.fromEntries( names.map( ( name ) => [ name, getComputedStyle( node )[ name ] ] ) ), properties );
		expect( real ).toEqual( q05 );
		await expect( page.locator( 'main.single-post .u-quotation-of.h-cite' ) ).toHaveCount( 0 );
	} );

	test( 'the title-less pullquote control records the pre-existing H1 behavior without duplicating the quote', async ( { page }, testInfo ) => {
		const main = await openSingle( page, 'Q18' );
		const h1Count = await main.locator( 'h1' ).count();
		expect( h1Count ).toBeLessThanOrEqual( 1 );
		if ( 0 === h1Count ) {
			testInfo.annotations.push( { type: 'pre-existing', description: 'The title-less quote single prints no H1.' } );
		}
		await expect( main.locator( 'figure.wp-block-pullquote' ) ).toHaveCount( 1 );
		await expect( main.locator( 'figure.wp-block-pullquote cite' ) ).toHaveCount( 1 );
	} );
} );

// --- Stream (28) ------------------------------------------------------------------------------

test.describe( 'quote Stream (28)', () => {
	test( 'stream state keeps Q05 on the shipped note and its sky paper matches archive and single', async ( { page } ) => {
		test.skip( 'stream' !== STATE, 'seed with --state=stream and set CR_QUOTE_STATE=stream' );
		await openSeeded( page, ARCHIVE );
		const archive = cardFor( page, 'Q05' );
		await expect( archive ).toHaveCount( 1 );
		const archivePaper = await archive.evaluate( ( node ) => getComputedStyle( node ).getPropertyValue( '--cr-quote-paper' ).trim() );

		await openSingle( page, 'Q05' );
		const single = page.locator( '.cr-note-artifact--single' );
		const singlePaper = await single.evaluate( ( node ) => getComputedStyle( node ).getPropertyValue( '--cr-quote-paper' ).trim() );

		await open( page, STREAM );
		const item = page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( 'Q05' ) }"]` ) } ).first();
		skipUnlessSeeded( 0 === await item.count(), 'Q05 is not on Stream page 1; seed with --state=stream' );
		const stream = item.locator( '.cr-note-artifact--stream:has(article.cr-note--stream)' );
		await expect( stream ).toHaveCount( 1 );
		const streamPaper = await stream.evaluate( ( node ) => getComputedStyle( node ).getPropertyValue( '--cr-quote-paper' ).trim() );
		expect( singlePaper ).toBe( archivePaper );
		expect( streamPaper ).toBe( archivePaper );
	} );
} );
