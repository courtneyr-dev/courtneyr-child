// `CR_SPEC=question-256.spec.mjs`: issue 256 question archive, single and Stream
// contracts. Read-only GETs; needs a running site seeded by the vault's
// assets/256-questions/seed-256.php. Set CR_QUESTION_REQUIRE_SEED=1 for the
// orchestrator run. Remote runs use CR_BASE_URL and CR_ALLOW_REMOTE=1 through
// the shared config; this spec never sets or changes a user agent.
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';

const require = createRequire( import.meta.url );
const ARCHIVE = ( process.env.CR_QUESTION_ARCHIVE_PATH || '/kind/question/' ).replace( /\/?$/, '/' );
const STREAM = process.env.CR_QUESTION_STREAM_PATH || '/stream/';
const REQUIRE_SEED = '1' === process.env.CR_QUESTION_REQUIRE_SEED;
const DEV = '1' === process.env.CR_QUESTION_DEV;

const ANSWERED = 'answered';
const WAITING = 'waiting';
const OTHER = '';
const VIOLET = 'rgb(36, 28, 74)';
const IVORY = 'rgb(251, 250, 245)';
const SKY_BLUE = 'rgb(142, 202, 230)';
const LIGHT_ORANGE = 'rgb(254, 226, 195)';
const SELECTIVE_YELLOW = 'rgb(255, 183, 3)';
const PHOTOCOPIER_BLACK = 'rgb(26, 23, 20)';
const NIGHT_ELEVATED = 'rgb(37, 31, 28)';
const NIGHT_RULE = 'rgb(58, 52, 47)';
const TRANSPARENT = 'rgba(0, 0, 0, 0)';
const OLD_COLORS = [ 'rgb(252, 241, 197)', 'rgb(184, 95, 0)' ];
const PAPER_COLORS = {
	'light-gray': 'rgb(235, 235, 235)',
	'printer-ivory': IVORY,
	'light-orange': LIGHT_ORANGE,
	periwinkle: 'rgb(188, 181, 227)',
	'sky-blue': SKY_BLUE,
};
const PAGE_GROUPS = [
	[ [ ANSWERED, 8 ], [ WAITING, 4 ] ],
	[ [ WAITING, 11 ], [ OTHER, 1 ] ],
	[ [ OTHER, 2 ] ],
];
const WIDTHS = [ [ 1440, 3 ], [ 800, 2 ], [ 375, 1 ], [ 320, 1 ] ];

const isDark = ( testInfo ) => testInfo.project.name.endsWith( 'dark' );
const slugOf = ( key ) => `seed-256-${ key.toLowerCase() }`;
const singlePath = ( key ) => `/?name=${ slugOf( key ) }`;
const archivePath = ( number = 1 ) => 1 === number ? ARCHIVE : `${ ARCHIVE }page/${ number }/`;

function skipUnlessSeeded( missing, reason ) {
	if ( missing && REQUIRE_SEED ) {
		throw new Error( `CR_QUESTION_REQUIRE_SEED=1: ${ reason }` );
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
	skipUnlessSeeded( 0 === await page.locator( 'a[href*="seed-256-"]' ).count(), 'the issue 256 fixtures are not seeded here (seed-256.php)' );
	return response;
}

async function openSingle( page, key, width = 1440 ) {
	const response = await open( page, singlePath( key ), width );
	skipUnlessSeeded( 404 === response?.status() || 0 === await page.locator( 'main.single-post' ).count(), `no fixture ${ key }` );
	return page.locator( 'main.single-post' ).first();
}

const visibleLinks = ( scope ) => scope.evaluate( ( root ) => {
	const rendered = ( link ) => ! link.closest( '[hidden]' ) && 0 < link.getClientRects().length;
	return {
		hrefs: [ ...root.querySelectorAll( 'a[href]' ) ].filter( rendered ).map( ( link ) => link.getAttribute( 'href' ) ),
		stray: [ ...root.querySelectorAll( 'a[tabindex="-1"]' ) ].filter( ( link ) => ! link.closest( '[hidden]' ) ).length,
		tabStops: [ ...root.querySelectorAll( 'a[href], button, input, select, textarea, [tabindex]' ) ].filter( ( node ) => rendered( node ) && ! node.disabled && node.tabIndex >= 0 ).length,
	};
} );

async function assertNoOverflow( page, label ) {
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `${ label } horizontal overflow` ).toBeLessThanOrEqual( 0 );
}

async function axeContrast( page ) {
	await page.addScriptTag( { path: require.resolve( 'axe-core/axe.min.js' ) } );
	return page.evaluate( async () => ( await window.axe.run( document, { runOnly: [ 'color-contrast' ] } ) ).violations.map( ( violation ) => ( {
		id: violation.id,
		targets: violation.nodes.map( ( node ) => node.target.join( ' ' ) ),
	} ) ) );
}

async function assertNoOldColors( page, root ) {
	const leaks = await page.locator( root ).evaluate( ( scope, banned ) => {
		const found = [];
		for ( const node of [ scope, ...scope.querySelectorAll( '*' ) ] ) {
			for ( const pseudo of [ null, '::before', '::after' ] ) {
				const style = getComputedStyle( node, pseudo );
				for ( const property of [ 'color', 'backgroundColor', 'borderTopColor', 'outlineColor', 'boxShadow', 'textDecorationColor' ] ) {
					if ( banned.some( ( value ) => style[ property ].includes( value ) ) ) {
						found.push( `${ node.tagName }${ pseudo || '' } ${ property }=${ style[ property ] }` );
					}
				}
			}
		}
		return found;
	}, OLD_COLORS );
	expect( leaks, `${ root } contains no retired question colors` ).toEqual( [] );
}

function paperName( classes ) {
	const modifiers = classes.filter( ( token ) => token.startsWith( 'cr-paper--' ) ).map( ( token ) => token.slice( 10 ) );
	expect( modifiers.length, 'one paper modifier at most' ).toBeLessThanOrEqual( 1 );
	return modifiers[ 0 ] || 'light-gray';
}

function degrees( transform ) {
	const match = /matrix\(([^)]+)\)/.exec( transform );
	if ( ! match ) {
		return 'none' === transform ? 0 : Number.parseFloat( transform.match( /-?[\d.]+/ )?.[ 0 ] || 'NaN' );
	}
	const [ a, b ] = match[ 1 ].split( ',' ).map( Number );
	return ( Math.atan2( b, a ) * 180 ) / Math.PI;
}

async function archiveEntryData( page ) {
	return page.locator( '.pkiw-question-entry:not(.pkiw-question-entry--continued)' ).evaluateAll( ( entries ) => entries.map( ( entry ) => ( {
		classes: [ ...entry.classList ],
		background: getComputedStyle( entry ).backgroundColor,
		href: entry.querySelector( 'a[href]' )?.getAttribute( 'href' ) || '',
	} ) ) );
}

// --- Archive -----------------------------------------------------------------------------------

test.describe( 'question archive', () => {
	test( 'header and three pages match the grouped 26-post map and page four is a 404', async ( { page } ) => {
		for ( let index = 0; index < PAGE_GROUPS.length; index++ ) {
			await openSeeded( page, archivePath( index + 1 ) );
			await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
			await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'Question' );
			await expect( page.locator( 'main h1 .cr-cutout__tile' ) ).toHaveCount( 8 );
			await expect( page.locator( '.cr-archive__tape' ) ).toHaveCount( 0 );

			const groups = await page.locator( 'section.pkiw-question-section' ).evaluateAll( ( sections ) => sections.map( ( section ) => [
				section.getAttribute( 'data-pkiw-group' ),
				section.querySelectorAll( ':scope > ul.pkiw-question-section__items > li.wp-block-post' ).length,
			] ) );
			expect( groups, `page ${ index + 1 } section map` ).toEqual( PAGE_GROUPS[ index ] );
		}

		const response = await open( page, archivePath( 4 ) );
		expect( response?.status() ).toBe( 404 );
	} );

	test( 'each width uses the specified columns, one whole-slip link and matching DOM, visual and tab order', async ( { page } ) => {
		for ( const [ width, columns ] of WIDTHS ) {
			await openSeeded( page, ARCHIVE, width );
			const lists = page.locator( 'ul.pkiw-question-section__items' );
			await expect( lists ).toHaveCount( 2 );
			for ( const list of await lists.all() ) {
				expect( await list.evaluate( ( node ) => getComputedStyle( node ).gridTemplateColumns.split( ' ' ).length ), `${ width }px columns` ).toBe( columns );
			}

			const entries = page.locator( '.pkiw-question-entry:not(.pkiw-question-entry--continued)' );
			for ( const entry of await entries.all() ) {
				const report = await visibleLinks( entry );
				expect( report.hrefs ).toHaveLength( 1 );
				expect( report.stray ).toBe( 0 );
				expect( report.tabStops ).toBe( 1 );
				const box = await entry.boundingBox();
				expect( box ).not.toBeNull();
				expect( Math.min( box.width, box.height ) ).toBeGreaterThanOrEqual( 44 );
			}

			const anchors = entries.locator( 'h3.pkiw-question-entry__title > a.u-url' );
			const order = await anchors.evaluateAll( ( links ) => links.map( ( link, index ) => {
				const rect = link.closest( '.pkiw-question-entry' ).getBoundingClientRect();
				return { index, top: Math.round( rect.top ), left: Math.round( rect.left ) };
			} ) );
			const visual = [ ...order ].sort( ( a, b ) => Math.abs( a.top - b.top ) > 2 ? a.top - b.top : a.left - b.left ).map( ( item ) => item.index );
			expect( visual ).toEqual( order.map( ( item ) => item.index ) );

			await anchors.first().focus();
			for ( let index = 1; index < await anchors.count(); index++ ) {
				await page.keyboard.press( 'Tab' );
				expect( await anchors.nth( index ).evaluate( ( link ) => document.activeElement === link ), `${ width }px tab ${ index + 1 }` ).toBe( true );
			}
			if ( 320 === width ) {
				await assertNoOverflow( page, '320px and 400 percent zoom proxy' );
			}
		}
	} );

	test( 'light papers, marker strokes and whole-slip focus use the shipped palette', async ( { page }, testInfo ) => {
		test.skip( isDark( testInfo ), 'light-project assertion' );
		await openSeeded( page );
		for ( const entry of await archiveEntryData( page ) ) {
			expect( entry.classes ).toEqual( expect.arrayContaining( [ 'cr-question-slip', 'cr-paper-sheet', 'cr-paper-sheet--torn' ] ) );
			const name = paperName( entry.classes );
			expect( entry.background, entry.href ).toBe( PAPER_COLORS[ name ] );
		}

		const answered = page.locator( '[data-pkiw-group="answered"] .pkiw-question-section__heading' );
		const waiting = page.locator( '[data-pkiw-group="waiting"] .pkiw-question-section__heading' );
		expect( await answered.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( SKY_BLUE );
		expect( await waiting.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( LIGHT_ORANGE );
		expect( await answered.evaluate( ( node ) => getComputedStyle( node ).color ) ).toBe( VIOLET );
		expect( await waiting.evaluate( ( node ) => getComputedStyle( node ).color ) ).toBe( VIOLET );

		const first = page.locator( '.pkiw-question-entry.cr-question-slip' ).first();
		await first.locator( 'a' ).focus();
		const focus = await first.evaluate( ( node ) => ( { outline: getComputedStyle( node ).outlineColor, width: getComputedStyle( node ).outlineWidth, shadow: getComputedStyle( node ).boxShadow } ) );
		expect( focus.outline === SELECTIVE_YELLOW || focus.shadow.includes( SELECTIVE_YELLOW ) ).toBe( true );
		expect( '3px' === focus.width || focus.shadow.includes( '7px' ) ).toBe( true );
		await assertNoOldColors( page, 'main.cr-archive--question' );
	} );

	test( 'Other has no marker, and the shared pager stays centered outside slips with 44px chips', async ( { page } ) => {
		await openSeeded( page, archivePath( 2 ) );
		const other = page.locator( '[data-pkiw-group=""] .pkiw-question-section__heading' );
		expect( await other.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( TRANSPARENT );

		for ( const number of [ 1, 3 ] ) {
			await openSeeded( page, archivePath( number ), 320 );
			const pager = page.locator( 'nav[aria-label="Pagination"]' );
			await expect( pager ).toHaveCount( 1 );
			expect( await pager.evaluate( ( node ) => Boolean( node.closest( '.cr-question-slip' ) ) ) ).toBe( false );
			const centered = await pager.evaluate( ( node ) => {
				const rect = node.getBoundingClientRect();
				return Math.abs( rect.left + rect.width / 2 - innerWidth / 2 );
			} );
			expect( centered ).toBeLessThanOrEqual( 2 );
			for ( const chip of await pager.locator( 'a, .page-numbers' ).all() ) {
				const box = await chip.boundingBox();
				expect( Math.min( box.width, box.height ) ).toBeGreaterThanOrEqual( 44 );
			}
			await expect( pager.getByText( 'Previous', { exact: true } ) ).toHaveCount( 1 === number ? 0 : 1 );
			await expect( pager.getByText( 'Next', { exact: true } ) ).toHaveCount( 3 === number ? 0 : 1 );
			await assertNoOverflow( page, `pager page ${ number }` );
		}
	} );

	test( 'dark media and data-theme both flip slips while marker paint stays fixed', async ( { page }, testInfo ) => {
		test.skip( ! isDark( testInfo ), 'dark-project assertion' );
		await openSeeded( page );
		expect( await page.locator( 'body' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( PHOTOCOPIER_BLACK );
		expect( await page.locator( 'main h1' ).evaluate( ( node ) => getComputedStyle( node ).color ) ).toBe( IVORY );
		const slip = page.locator( '.pkiw-question-entry.cr-question-slip' ).first();
		const paint = async () => slip.evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, color: getComputedStyle( node ).color, border: getComputedStyle( node ).borderTopColor } ) );
		expect( await paint() ).toEqual( { background: NIGHT_ELEVATED, color: IVORY, border: NIGHT_RULE } );

		await page.locator( 'html' ).evaluate( ( root ) => root.removeAttribute( 'data-theme' ) );
		expect( ( await paint() ).background, 'prefers-color-scheme mechanism' ).toBe( NIGHT_ELEVATED );
		await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'data-theme', 'light' ) );
		expect( ( await paint() ).background, 'explicit light overrides dark media' ).not.toBe( NIGHT_ELEVATED );
		await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'data-theme', 'dark' ) );
		expect( ( await paint() ).background, 'explicit dark mechanism' ).toBe( NIGHT_ELEVATED );
		expect( await page.locator( '[data-pkiw-group="answered"] .pkiw-question-section__heading' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( SKY_BLUE );
		expect( await page.locator( '[data-pkiw-group="waiting"] .pkiw-question-section__heading' ).evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( LIGHT_ORANGE );
	} );

	test( 'forced colors removes marker clipping and reduced motion leaves slips without transitions', async ( { page } ) => {
		await openSeeded( page );
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await page.locator( '.pkiw-question-entry.cr-question-slip' ).first().evaluate( ( node ) => ( { border: `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }`, color: getComputedStyle( node ).borderTopColor } ) );
		expect( forced.border ).toBe( '2px solid' );
		expect( forced.color ).toBe( await page.locator( 'body' ).evaluate( ( node ) => getComputedStyle( node ).color ) );
		const marker = await page.locator( '[data-pkiw-group="answered"] .pkiw-question-section__heading' ).evaluate( ( node ) => ( { background: getComputedStyle( node ).backgroundColor, clip: getComputedStyle( node ).clipPath } ) );
		expect( marker.background ).toBe( TRANSPARENT );
		expect( marker.clip ).toBe( 'none' );

		await page.emulateMedia( { forcedColors: 'none', reducedMotion: 'reduce' } );
		expect( await page.locator( '.cr-question-slip' ).first().evaluate( ( node ) => getComputedStyle( node ).transitionDuration ) ).toBe( '0s' );
	} );

	test( 'archive has no axe color-contrast violations', async ( { page } ) => {
		await openSeeded( page );
		expect( await axeContrast( page ) ).toEqual( [] );
	} );
} );

// --- Single ------------------------------------------------------------------------------------

test.describe( 'question single', () => {
	test( 'S01 renders the stacked question and two classified answers without pending or spam text', async ( { page } ) => {
		await openSingle( page, 'S01' );
		await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
		const body = page.locator( '.pkiw-question-body' );
		await expect( body ).toHaveCount( 1 );
		const salutation = body.locator( 'p.is-style-cr-salutation' );
		await expect( salutation ).toHaveText( 'Dear readers,' );
		const salutationPaint = await salutation.evaluate( ( node ) => ( { family: getComputedStyle( node ).fontFamily, size: Number.parseFloat( getComputedStyle( node ).fontSize ), background: getComputedStyle( node ).backgroundColor } ) );
		expect( salutationPaint.family ).toContain( 'Rock Salt' );
		expect( salutationPaint.size ).toBeGreaterThanOrEqual( 16 );
		expect( salutationPaint.background ).toBe( SELECTIVE_YELLOW );

		const answers = page.locator( 'section.pkiw-answers' );
		await expect( answers ).toHaveCount( 1 );
		await expect( answers.locator( ':scope > ol.pkiw-answers__list > li.pkiw-answer' ) ).toHaveCount( 2 );
		await expect( answers.locator( 'li.pkiw-answer--reply' ) ).toHaveCount( 1 );
		expect( await answers.locator( 'img, .cr-chip, [class*="answer-chip"], [class*="source-chip"]' ).count() ).toBe( 0 );
		for ( const card of await answers.locator( 'li.pkiw-answer' ).all() ) {
			const paint = await card.evaluate( ( node ) => ( { border: `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }`, radius: getComputedStyle( node ).borderRadius } ) );
			expect( paint.border ).toBe( '1px solid' );
			expect( paint.radius ).toBe( '0px' );
		}
		const text = await page.locator( 'main' ).innerText();
		expect( text ).not.toContain( 'Pending answer from Corin Vale' );
		expect( text ).not.toContain( 'Spam answer from Dara Moss' );
		await expect( page.locator( '.wp-block-comments-title' ) ).toContainText( 'One response' );
		const respond = answers.locator( '.pkiw-answers__respond > a[href="#respond"]' );
		expect( ( await respond.boundingBox() ).height ).toBeGreaterThanOrEqual( 44 );
		expect( await answers.locator( 'ol.pkiw-answers__list a[href]' ).evaluateAll( ( links ) => links.map( ( link ) => link.closest( '.p-author' ) ? 'author' : link.querySelector( 'time.dt-published' ) ? 'date' : 'other' ) ) ).toEqual( [ 'author', 'date', 'author', 'date', 'author', 'date' ] );

		const ordered = await page.locator( 'section.pkiw-answers a[href], #respond :is(input:not([type="hidden"]), textarea, button), nav.cr-post-nav a[href]' ).evaluateAll( ( nodes ) => nodes.filter( ( node ) => 0 < node.getClientRects().length ).map( ( node ) => ( {
			answer: Boolean( node.closest( 'section.pkiw-answers' ) ),
			response: Boolean( node.matches( '.pkiw-answers__respond a' ) ),
			form: Boolean( node.closest( '#respond' ) ),
			nav: Boolean( node.closest( 'nav.cr-post-nav' ) ),
		} ) ) );
		const responseIndex = ordered.findIndex( ( item ) => item.response );
		const formIndex = ordered.findIndex( ( item ) => item.form );
		const navIndex = ordered.findIndex( ( item ) => item.nav );
		expect( ordered.slice( 0, responseIndex ).every( ( item ) => item.answer ) ).toBe( true );
		expect( responseIndex ).toBeGreaterThan( 0 );
		expect( formIndex ).toBeGreaterThan( responseIndex );
		expect( navIndex ).toBeGreaterThan( formIndex );
	} );

	test( 'stacked sheet aligns at all widths and answer cards stack below 48rem without overflow', async ( { page } ) => {
		for ( const [ width ] of WIDTHS ) {
			await openSingle( page, 'S01', width );
			const geometry = await page.locator( 'main.single-post' ).evaluate( ( root ) => {
				const header = root.querySelector( ':scope > .single-post__header' );
				const body = root.querySelector( '.pkiw-question-body' );
				const answer = root.querySelector( 'li.pkiw-answer' );
				const author = answer.querySelector( ':scope > .p-author' ).getBoundingClientRect();
				const date = answer.querySelector( ':scope > a.u-url' ).getBoundingClientRect();
				const content = answer.querySelector( ':scope > .e-content' ).getBoundingClientRect();
				return {
					header: header.getBoundingClientRect().toJSON(),
					body: body.getBoundingClientRect().toJSON(),
					headerBackground: getComputedStyle( header ).backgroundColor,
					bodyBackground: getComputedStyle( body ).backgroundColor,
					stackedAnswer: date.top >= author.bottom - 1 && content.top >= date.bottom - 1,
				};
			} );
			expect( Math.abs( geometry.header.bottom - geometry.body.top ), `${ width }px seam` ).toBeLessThanOrEqual( 1 );
			expect( Math.abs( geometry.header.width - geometry.body.width ), `${ width }px sheet width` ).toBeLessThanOrEqual( 1 );
			expect( geometry.headerBackground ).toBe( geometry.bodyBackground );
			if ( width < 768 ) {
				expect( geometry.stackedAnswer ).toBe( true );
				await assertNoOverflow( page, `${ width }px single` );
			}
		}
	} );

	test( 'S02 is an untitled question with one generated H1, no p-name, salutation or answers', async ( { page } ) => {
		await openSingle( page, 'S02' );
		const h1 = page.locator( 'main h1' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1 ).toHaveText( /^Question, / );
		expect( await h1.innerText() ).toMatch( / at / );
		await expect( page.locator( '.p-name' ) ).toHaveCount( 0 );
		await expect( page.locator( '.is-style-cr-salutation, li.pkiw-answer' ) ).toHaveCount( 0 );
	} );

	test( 'single dark and forced-color modes keep the question sheet and answer borders explicit', async ( { page }, testInfo ) => {
		await openSingle( page, 'S01' );
		const header = page.locator( 'main.single-post > .single-post__header' );
		const answer = page.locator( 'li.pkiw-answer' ).first();
		if ( isDark( testInfo ) ) {
			expect( await header.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( NIGHT_ELEVATED );
			expect( await answer.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( NIGHT_ELEVATED );
			expect( await header.evaluate( ( node ) => getComputedStyle( node ).borderTopColor ) ).toBe( NIGHT_RULE );
		}
		await assertNoOldColors( page, 'main.single-post' );
		await page.emulateMedia( { forcedColors: 'active' } );
		const canvasText = await page.locator( 'body' ).evaluate( ( node ) => getComputedStyle( node ).color );
		expect( await header.evaluate( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle } ${ getComputedStyle( node ).borderTopColor }` ) ).toBe( `2px solid ${ canvasText }` );
		expect( await answer.evaluate( ( node ) => `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle } ${ getComputedStyle( node ).borderTopColor }` ) ).toBe( `2px solid ${ canvasText }` );
	} );
} );

// --- Stream ------------------------------------------------------------------------------------

test.describe( 'question Stream cards', () => {
	test( 'S01-S04 use one-link question cards, while the protected card remains unpainted', async ( { page } ) => {
		await openSeeded( page, STREAM );
		for ( const key of [ 'S01', 'S02', 'S03', 'S04' ] ) {
			const item = page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();
			await expect( item ).toHaveCount( 1 );
			const card = item.locator( 'article.pk-card.pk-card--stream.k-question' );
			await expect( card ).toHaveCount( 1 );
			const links = await visibleLinks( card );
			expect( links.hrefs, key ).toHaveLength( 1 );
			expect( links.stray, key ).toBe( 0 );
			expect( await card.locator( '.pk-badge, .pk-link, svg' ).count(), key ).toBe( 0 );
			if ( 'S04' === key ) {
				await expect( card ).toHaveClass( /pk-card--protected/ );
				await expect( card ).not.toHaveClass( /cr-question-slip/ );
			} else {
				await expect( card ).toHaveClass( /cr-question-slip/ );
				expect( ( await card.getAttribute( 'class' ) ).match( /\bcr-question-tilt-[0-3]\b/g ) ).toHaveLength( 1 );
			}
		}

		const s01 = page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( 'S01' ) }"]` ) } ).locator( 'article' );
		await expect( s01.locator( '.pk-kindlabel' ) ).toHaveText( 'Question' );
		await expect( s01.locator( '.pk-answer-count' ) ).toHaveText( '2 answers' );
		const order = await s01.evaluate( ( card ) => [ '.pk-kindlabel', '.pk-title', '.pk-stream-date', '.pk-answer-count' ].map( ( selector ) => card.querySelector( selector ) ).map( ( node ) => [ ...card.querySelectorAll( '*' ) ].indexOf( node ) ) );
		expect( order ).toEqual( [ ...order ].sort( ( a, b ) => a - b ) );
	} );

	test( 'Stream tilt maps to one token, children stay upright and paper matches the archive', async ( { page } ) => {
		await openSeeded( page, STREAM );
		const stream = {};
		for ( const key of [ 'S01', 'S02', 'S03' ] ) {
			const card = page.locator( 'li.wp-block-post' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).locator( 'article.cr-question-slip' );
			const report = await card.evaluate( ( node ) => {
				const tilt = [ ...node.classList ].find( ( token ) => token.startsWith( 'cr-question-tilt-' ) );
				const tokenNames = [ '--cr-rotate-1', '--cr-rotate-2', '--cr-rotate-neg', '--cr-rotate-neg2' ];
				const token = tokenNames[ Number( tilt.slice( -1 ) ) ];
				return {
					classes: [ ...node.classList ],
					background: getComputedStyle( node ).backgroundColor,
					degree: getComputedStyle( node ).transform,
					expected: getComputedStyle( document.documentElement ).getPropertyValue( token ),
					innerTransforms: [ ...node.querySelectorAll( '*' ) ].filter( ( child ) => 'none' !== getComputedStyle( child ).transform ).map( ( child ) => child.className ),
				};
			} );
			expect( report.innerTransforms, key ).toEqual( [] );
			expect( Math.abs( degrees( report.degree ) - Number.parseFloat( report.expected ) ), key ).toBeLessThan( 0.05 );
			stream[ key ] = report;
		}

		await openSeeded( page, ARCHIVE );
		for ( const key of [ 'S01', 'S02', 'S03' ] ) {
			const entry = page.locator( '.pkiw-question-entry' ).filter( { has: page.locator( `a[href*="${ slugOf( key ) }"]` ) } ).first();
			const archive = await entry.evaluate( ( node ) => ( { classes: [ ...node.classList ], background: getComputedStyle( node ).backgroundColor } ) );
			expect( paperName( stream[ key ].classes ), key ).toBe( paperName( archive.classes ) );
			expect( stream[ key ].background, key ).toBe( archive.background );
		}
	} );

	test( 'Stream cards fit narrow columns, honor dark and forced colors, and contain no retired paint', async ( { page }, testInfo ) => {
		for ( const width of [ 375, 320 ] ) {
			await openSeeded( page, STREAM, width );
			await assertNoOverflow( page, `${ width }px Stream` );
			const failures = await page.locator( 'article.cr-question-slip' ).evaluateAll( ( cards ) => cards.filter( ( card ) => card.getBoundingClientRect().right > innerWidth + 1 || card.scrollWidth > card.clientWidth + 1 ).length );
			expect( failures ).toBe( 0 );
		}
		const card = page.locator( 'article.cr-question-slip' ).first();
		if ( isDark( testInfo ) ) {
			expect( await card.evaluate( ( node ) => getComputedStyle( node ).backgroundColor ) ).toBe( NIGHT_ELEVATED );
			expect( await card.evaluate( ( node ) => getComputedStyle( node ).color ) ).toBe( IVORY );
		}
		await assertNoOldColors( page, '.cr-stream-page' );
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await card.evaluate( ( node ) => ( { border: `${ getComputedStyle( node ).borderTopWidth } ${ getComputedStyle( node ).borderTopStyle }`, color: getComputedStyle( node ).borderTopColor, transform: getComputedStyle( node ).transform } ) );
		expect( forced.border ).toBe( '2px solid' );
		expect( forced.color ).toBe( await page.locator( 'body' ).evaluate( ( node ) => getComputedStyle( node ).color ) );
		expect( forced.transform ).toBe( 'none' );
	} );

	test( 'Stream has no axe color-contrast violations', async ( { page } ) => {
		await openSeeded( page, STREAM );
		expect( await axeContrast( page ) ).toEqual( [] );
	} );
} );

test.describe( 'dev-only empty question archive', () => {
	test.skip( ! DEV, 'set CR_QUESTION_DEV=1 before seeding the dev archive' );
	test( 'empty archive prints only its header', async ( { page } ) => {
		const response = await open( page, ARCHIVE );
		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'main h1 .cr-cutout__text' ) ).toHaveText( 'Question' );
		await expect( page.locator( '.pkiw-question-section, .pkiw-question-entry, nav[aria-label="Pagination"], .cr-browse-all' ) ).toHaveCount( 0 );
	} );
} );
