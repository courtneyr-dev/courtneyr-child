// `CR_SPEC=w2-t0-shared-paint.spec.mjs npm run captures -- --project=1280-light`:
// W2-T0 shared paint checks on blank pages with stylesheets inlined.
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const css = ( file ) => fs.readFileSync( new URL( `../../assets/css/${ file }`, import.meta.url ), 'utf8' );
const TOKEN_RE = /(^|[^A-Za-z0-9_-])(k|kind)-(review|reply|like|bookmark)([^A-Za-z0-9_-]|$)/;
const CONTROL_KINDS = [ 'article', 'note', 'quote', 'repost', 'favorite', 'listen', 'watch', 'read', 'play', 'checkin', 'eat', 'drink', 'mood', 'acquisition', 'rsvp', 'wish' ];
const PALETTE = [ 'sky-blue', 'periwinkle', 'light-orange', 'selective-yellow', 'ut-orange', 'printer-ivory', 'prussian-blue', 'cerulean', 'russian-violet' ];

async function pageWith( page, bodyClass, html, styles ) {
	await page.setContent( `<!doctype html><html><head></head><body class="${ bodyClass }">${ html }</body></html>` );
	for ( const style of styles ) {
		await page.addStyleTag( { content: style } );
	}
}

const card = ( classes ) => `
	<article class="pk-card ${ classes}">
		<div class="pk-badge"></div>
		<div class="pk-body">
			<span class="pk-kindlabel">Kind</span>
			<div class="pk-caption"><h2 class="pk-title p-name"><a href="#t">Title</a></h2><p class="pk-sub">Sub</p></div>
			<div class="pk-stars">Stars</div>
			<div class="pk-media"><img class="pk-thumb--poster pk-thumb" alt=""></div>
			<div class="pk-excerpt">Excerpt</div>
			<div class="pk-meta"><a class="pk-link" href="#l">Link</a></div>
			<div class="pk-stream-date"><time>Date</time></div>
		</div>
	</article>`;

function ruleBody( source, selector ) {
	const escaped = selector.replace( /[-/\\^$*+?.()|[\]{}]/g, '\\$&' );
	const match = source.match( new RegExp( `${ escaped }\\s*\\{([^}]*)\\}` ) );
	return match ? match[1].trim() : '';
}

function stripTokenRows( source ) {
	return source.split( '\n' ).filter( ( line ) => ! /^\.pk-card\.k-(like|reply|bookmark)\s+\{/.test( line ) ).join( '\n' );
}

test( 'removed kinds leave only the three shared token rows', async () => {
	const source = css( 'cr-post-kinds.css' );
	const withoutRows = stripTokenRows( source );
	for ( const kind of [ 'review', 'reply', 'like', 'bookmark' ] ) {
		expect( withoutRows.split( '\n' ).filter( ( line ) => new RegExp( `(^|[^A-Za-z0-9_-])(k|kind)-${ kind }([^A-Za-z0-9_-]|$)` ).test( line ) ), `${ kind } selectors removed outside token rows` ).toEqual( [] );
	}
	for ( const kind of [ 'like', 'reply', 'bookmark' ] ) {
		const rows = source.split( '\n' ).filter( ( line ) => line.startsWith( `.pk-card.k-${ kind }` ) );
		expect( rows, `${ kind } has exactly one token row` ).toHaveLength( 1 );
		expect( rows[0] ).toContain( `--pk-kind: var(--cr-type-${ kind })` );
		expect( rows[0] ).toContain( `--pk-kind-dark: var(--cr-type-${ kind })` );
		expect( rows[0] ).toContain( '--pk-badge-ink: #fff' );
		expect( rows[0] ).toContain( '--pk-tint: var(--cr-light-gray)' );
		expect( rows[0].match( /#[0-9a-fA-F]{3,6}/g ) ).toEqual( [ '#fff' ] );
	}
	const base = execFileSync( 'git', [ 'show', `${ process.env.CR_T0_BASE || 'addcfce' }:assets/css/cr-post-kinds.css` ], { encoding: 'utf8' } );
	const needle = '.k-play:not(:where(.cr-spine, .cr-cabinet, .cr-cartridge, .cr-scorepad, .pk-card--tabletop))';
	expect( source.split( needle ).length, 'play exclusion count is unchanged from base' ).toBe( base.split( needle ).length );
} );

test( 'reply, like and bookmark token rows follow type tokens in light and dark', async ( { page } ) => {
	await pageWith( page, '', [ 'reply', 'like', 'bookmark' ].map( ( kind ) => card( `k-${ kind}` ) ).join( '' ), [ css( 'tokens.css' ), css( 'cr-post-kinds.css' ) ] );
	for ( const kind of [ 'reply', 'like', 'bookmark' ] ) {
		const light = await page.locator( `.k-${ kind }` ).evaluate( ( el, k ) => {
			const cardStyles = getComputedStyle( el );
			const rootStyles = getComputedStyle( document.documentElement );
			return [ cardStyles.getPropertyValue( '--pk-kind' ).trim(), rootStyles.getPropertyValue( `--cr-type-${ k }` ).trim() ];
		}, kind );
		expect( light[0], `${ kind } light token` ).toBe( light[1] );
	}
	await page.evaluate( () => {
		document.documentElement.dataset.theme = 'dark';
	} );
	for ( const kind of [ 'reply', 'like', 'bookmark' ] ) {
		const dark = await page.locator( `.k-${ kind }` ).evaluate( ( el, k ) => {
			const cardStyles = getComputedStyle( el );
			const rootStyles = getComputedStyle( document.documentElement );
			return [ cardStyles.getPropertyValue( '--pk-kind' ).trim(), rootStyles.getPropertyValue( `--cr-type-${ k }` ).trim() ];
		}, kind );
		expect( dark[0], `${ kind } dark token` ).toBe( dark[1] );
	}
} );

function abMarkup( kind ) {
	const stream = `<div class="cr-stream-page"><ul class="wp-block-post-template">${ Array.from( { length: 4 }, ( _, i ) => `<li class="kind-${ kind }">${ card( `k-${ kind} h-entry` ) }<span class="probe">probe ${ i }</span></li>` ).join( '' ) }</ul></div>`;
	const single = `<div class="single-post__featured"></div><div class="single-post__kind"></div><div class="single-post__content">${ card( `k-${ kind}` ) }</div>`;
	return `${ stream }${ single }`;
}

// Applies the base stylesheet, reads every computed value of every element and
// pseudo-element, swaps in the working-tree stylesheet in the same document and
// reads again. Returns the first 20 differences as "path property: before -> after".
async function abDifferences( page, kind, baseCss, currentCss, width, dark ) {
	await page.setViewportSize( { width, height: 800 } );
	await pageWith( page, `single single-post kind-${ kind }`, abMarkup( kind ), [ css( 'tokens.css' ) ] );
	return page.evaluate( ( [ before, after, isDark ] ) => {
		if ( isDark ) {
			document.documentElement.dataset.theme = 'dark';
		}
		const style = document.createElement( 'style' );
		document.head.append( style );
		const path = ( el ) => {
			const parts = [];
			let node = el;
			while ( node && node.nodeType === Node.ELEMENT_NODE && 'HTML' !== node.tagName ) {
				const index = [ ...node.parentElement.children ].indexOf( node ) + 1;
				parts.unshift( `${ node.tagName.toLowerCase() }:nth-child(${ index })${ node.className ? '.' + String( node.className ).trim().replace( /\s+/g, '.' ) : '' }` );
				node = node.parentElement;
			}
			return parts.join( '>' );
		};
		const collect = () => {
			// Settle running transitions so a colour sampled mid-fade can't differ.
			document.getAnimations().forEach( ( animation ) => animation.finish() );
			const out = {};
			for ( const el of document.querySelectorAll( 'body, body *' ) ) {
				for ( const pseudo of [ '', '::before', '::after' ] ) {
					const cs = getComputedStyle( el, pseudo || null );
					const props = {};
					for ( const name of cs ) {
						props[ name ] = cs.getPropertyValue( name );
					}
					out[ `${ path( el ) }${ pseudo }` ] = props;
				}
			}
			return out;
		};
		style.textContent = before;
		const first = collect();
		style.textContent = after;
		const second = collect();
		const diffs = [];
		for ( const key of Object.keys( first ) ) {
			for ( const name of Object.keys( first[ key ] ) ) {
				if ( first[ key ][ name ] !== second[ key ]?.[ name ] ) {
					diffs.push( `${ key } ${ name }: ${ first[ key ][ name ] } -> ${ second[ key ]?.[ name ] }` );
				}
			}
		}
		return { count: diffs.length, first: diffs.slice( 0, 20 ) };
	}, [ baseCss, currentCss, dark ] );
}

function baseStylesheet() {
	try {
		return execFileSync( 'git', [ 'show', `${ process.env.CR_T0_BASE || 'addcfce' }:assets/css/cr-post-kinds.css` ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );
	} catch {
		return '';
	}
}

test( 'control kinds compute the same styles as the base stylesheet', async ( { page } ) => {
	test.setTimeout( 300000 );
	const base = baseStylesheet();
	test.skip( '' === base, 'baseline ref not available' );
	const current = css( 'cr-post-kinds.css' );
	for ( const width of [ 1280, 390 ] ) {
		for ( const dark of [ false, true ] ) {
			for ( const kind of CONTROL_KINDS ) {
				const result = await abDifferences( page, kind, base, current, width, dark );
				expect( result.first, `${ kind } ${ width } ${ dark ? 'dark' : 'light' }: ${ result.count } differences` ).toEqual( [] );
			}
		}
	}
} );

test( 'A/B harness detects changed reply paint', async ( { page } ) => {
	const base = baseStylesheet();
	test.skip( '' === base, 'baseline ref not available' );
	const result = await abDifferences( page, 'reply', base, css( 'cr-post-kinds.css' ), 1280, false );
	expect( result.count, 'removing the reply rules changes a reply card' ).toBeGreaterThan( 0 );
} );

test( 'cr-paper.css file contract', async () => {
	const source = css( 'cr-paper.css' );
	for ( const banned of [ /font-size\s*:/, /transition\s*:/, /animation\s*:/, /filter\s*:/, /radial-gradient\(/, /clip-path\s*:/, /box-shadow\s*:[^;]*(?:\d*\.?\d+)px\s+(?:\d*\.?\d+)px\s+(?!0px|0\b)/ ] ) {
		expect( source, `banned construct ${ banned }` ).not.toMatch( banned );
	}
	expect( source ).toContain( '.cr-paper-sheet' );
	expect( source ).toContain( '.cr-paper-sheet--flat' );
	expect( source ).toContain( '.cr-paper-sheet--torn' );
	expect( source ).toContain( '.cr-paper-sheet:has(:focus-visible)' );
	expect( source ).not.toMatch( /(^|})\s*\.cr-paper\s*\{/ );
	expect( source ).not.toMatch( /(^|})\s*\.is-style-cr-paper\s*\{/ );
	for ( const token of PALETTE ) {
		expect( source ).toContain( `.cr-paper--${ token }` );
		expect( ruleBody( source, `.cr-paper--${ token }` ) ).toBe( ruleBody( css( 'cr-play-board.css' ), `.cr-paper--${ token }` ) );
	}
} );

test( 'cr-paper.css renders the sheet, tear, palette and focus states', async ( { page } ) => {
	await pageWith( page, '', '<div class="cr-paper-sheet cr-paper-sheet--torn"><a href="#x">Link</a></div><div class="cr-paper-sheet cr-paper-sheet--flat"></div><div class="cr-paper-sheet cr-paper--sky-blue"></div>', [ css( 'tokens.css' ), css( 'cr-paper.css' ) ] );
	const base = await page.locator( '.cr-paper-sheet--torn' ).evaluate( ( el ) => {
		const cs = getComputedStyle( el );
		const after = getComputedStyle( el, '::after' );
		return { bg: cs.backgroundColor, shadow: cs.boxShadow, radius: cs.borderRadius, image: cs.backgroundImage, fiber: ( () => { const probe = document.createElement( 'div' ); probe.style.backgroundImage = 'var(--cr-fiber)'; document.body.append( probe ); const value = getComputedStyle( probe ).backgroundImage; probe.remove(); return value; } )(), after: { content: after.content, position: after.position, events: after.pointerEvents, block: after.blockSize }, margin: cs.marginBlockEnd, clip: cs.clipPath };
	} );
	expect( base.bg ).toBe( 'rgb(235, 235, 235)' );
	expect( base.shadow ).toBe( 'rgb(36, 28, 74) 4px 4px 0px 0px' );
	expect( base.radius ).toBe( '0px' );
	expect( base.image ).toBe( base.fiber );
	expect( base.after ).toEqual( { content: '""', position: 'absolute', events: 'none', block: '8px' } );
	expect( base.margin ).toBe( '8px' );
	expect( base.clip ).toBe( 'none' );
	await expect( page.locator( '.cr-paper-sheet--flat' ) ).toHaveCSS( 'box-shadow', 'none' );
	const sky = await page.locator( '.cr-paper--sky-blue' ).evaluate( ( el ) => {
		const cs = getComputedStyle( el );
		const root = getComputedStyle( document.documentElement );
		return { fill: cs.getPropertyValue( '--cr-paper' ).trim(), ink: cs.getPropertyValue( '--cr-paper-ink' ).trim(), skyBlue: root.getPropertyValue( '--cr-sky-blue' ).trim(), violet: root.getPropertyValue( '--cr-russian-violet' ).trim() };
	} );
	expect( sky.fill ).toBe( sky.skyBlue );
	expect( sky.ink ).toBe( sky.violet );
	await page.evaluate( () => {
		document.documentElement.dataset.theme = 'dark';
	} );
	const dark = await page.locator( '.cr-paper-sheet--torn' ).evaluate( ( el ) => {
		const cs = getComputedStyle( el );
		return { bg: cs.backgroundColor, color: cs.color, shadow: cs.boxShadow };
	} );
	expect( dark.bg ).toBe( 'rgb(235, 235, 235)' );
	expect( dark.color ).toBe( 'rgb(36, 28, 74)' );
	expect( dark.shadow ).toBe( 'rgb(255, 183, 3) 4px 4px 0px 0px' );
	await page.keyboard.press( 'Tab' );
	const focus = await page.locator( '.cr-paper-sheet--torn' ).evaluate( ( el ) => {
		const cs = getComputedStyle( el );
		return { outline: cs.outline, shadow: cs.boxShadow };
	} );
	expect( focus.outline ).toMatch( /3px/ );
	expect( focus.outline ).toContain( 'solid' );
	expect( focus.shadow ).toContain( '0px 0px 0px 7px' );
	await page.emulateMedia( { forcedColors: 'active' } );
	const forced = await page.locator( '.cr-paper-sheet--torn' ).evaluate( ( el ) => {
		const cs = getComputedStyle( el );
		const after = getComputedStyle( el, '::after' );
		return { border: cs.borderTopWidth, shadow: cs.boxShadow, after: after.display };
	} );
	expect( forced ).toEqual( { border: '2px', shadow: 'none', after: 'none' } );
} );
