// `CR_SPEC=native-breadcrumbs.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs`
// Core's Breadcrumbs block on templates/single.html (inc/single-navigation.php), front end and editor.
// Needs a running local site with Post Kinds active, a published /stream/ page, Stream posts of two
// kinds and a Blog post (CR_P36_NOTE_PATH, CR_P36_PHOTO_PATH, CR_P36_BLOG_PATH; slugs below by default).
// The editor tests log in with CR_WP_USER and CR_WP_PASS and change nothing: the inserted block is
// never saved. With Yoast SEO active the Blog post's landmark is Yoast's trail; without it, none.
import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

const axeSource = readFileSync( new URL( '../../node_modules/axe-core/axe.min.js', import.meta.url ), 'utf8' );
const STREAM = [
	{ kind: 'Note', path: process.env.CR_P36_NOTE_PATH || '/p36-stream-note/' },
	{ kind: 'Photo', path: process.env.CR_P36_PHOTO_PATH || '/p36-stream-photo/' },
];
const BLOG = process.env.CR_P36_BLOG_PATH || '/p36-blog-article/';

async function open( page, path, colorScheme ) {
	await page.goto( path );
	if ( 'dark' === colorScheme ) {
		await page.evaluate( () => localStorage.setItem( 'courtneyr-theme', 'dark' ) );
		await page.reload();
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
	}
}

const trails = ( page ) => page.getByRole( 'navigation', { name: /breadcrumb/i } );

for ( const { kind, path } of STREAM ) {
	test( `front end: ${ kind } post has one native trail, current item last`, async ( { page }, info ) => {
		await open( page, path, info.project.use.colorScheme );
		await expect( trails( page ) ).toHaveCount( 1 );
		const nav = trails( page ).first();
		await expect( nav ).toHaveClass( /wp-block-breadcrumbs/ );
		const title = ( await page.locator( 'h1.wp-block-post-title' ).innerText() ).trim();
		await expect( nav.locator( 'li' ) ).toHaveText( [ 'Home', 'Stream', kind, title ] );
		await expect( nav.locator( 'li a' ) ).toHaveCount( 3 );
		await expect( nav.locator( '[aria-current]' ) ).toHaveCount( 1 );
		await expect( nav.locator( 'li' ).last().locator( '[aria-current="page"]' ) ).toHaveCount( 1 );

		await page.addScriptTag( { content: axeSource } );
		const result = await page.evaluate( () => window.axe.run( { include: [ '.wp-block-breadcrumbs' ] }, { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] } ) );
		expect( result.violations.map( ( v ) => `${ v.id }: ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` ) ).toEqual( [] );
		// axe leaves color-contrast incomplete over the dotted paper background, so measure it: each
		// item's color against the paper color and against a dot blended in at its own alpha.
		const ratios = await nav.evaluate( ( el ) => {
			const rgb = ( c ) => c.match( /[\d.]+/g ).map( Number );
			const lum = ( c ) => {
				const [ r, g, b ] = c.slice( 0, 3 ).map( ( v ) => ( v / 255 <= 0.03928 ? v / 255 / 12.92 : ( ( v / 255 + 0.055 ) / 1.055 ) ** 2.4 ) );
				return 0.2126 * r + 0.7152 * g + 0.0722 * b;
			};
			const ratio = ( a, b ) => ( Math.max( lum( a ), lum( b ) ) + 0.05 ) / ( Math.min( lum( a ), lum( b ) ) + 0.05 );
			let paper = [ 255, 255, 255 ];
			let dot = paper;
			for ( let e = el; e; e = e.parentElement ) {
				const s = getComputedStyle( e );
				if ( ! /rgba\(.*, 0\)$/.test( s.backgroundColor ) ) {
					paper = rgb( s.backgroundColor );
					const d = s.backgroundImage.match( /rgba\(([\d.]+), ([\d.]+), ([\d.]+), ([\d.]+)\)/ );
					dot = d ? [ 1, 2, 3 ].map( ( i ) => Number( d[ i ] ) * Number( d[ 4 ] ) + paper[ i - 1 ] * ( 1 - Number( d[ 4 ] ) ) ) : paper;
					break;
				}
			}
			return [ ...el.querySelectorAll( 'a, [aria-current]' ) ].map( ( item ) => Math.min( ratio( rgb( getComputedStyle( item ).color ), paper ), ratio( rgb( getComputedStyle( item ).color ), dot ) ) );
		} );
		expect( Math.min( ...ratios ), `contrast ratios ${ ratios.map( ( r ) => r.toFixed( 2 ) ).join( ', ' ) }` ).toBeGreaterThanOrEqual( 4.5 );

		const first = nav.locator( 'a' ).first();
		for ( let i = 0; i < 60 && ! ( await first.evaluate( ( a ) => a === document.activeElement ) ); i++ ) {
			await page.keyboard.press( 'Tab' );
		}
		await expect( first ).toBeFocused();
		const ring = await first.evaluate( ( a ) => {
			const s = getComputedStyle( a );
			return { outline: `${ s.outlineStyle } ${ s.outlineWidth }`, shadow: s.boxShadow };
		} );
		expect( 'none 0px' !== ring.outline.replace( /^none .*/, 'none 0px' ) || 'none' !== ring.shadow, `focus ring: ${ JSON.stringify( ring ) }` ).toBe( true );
	} );
}

test( 'front end: a Blog post keeps one trail, and it is not the native block', async ( { page }, info ) => {
	await open( page, BLOG, info.project.use.colorScheme );
	await expect( page.locator( '.wp-block-breadcrumbs' ) ).toHaveCount( 0 );
	expect( await trails( page ).count() ).toBeLessThanOrEqual( 1 );
} );

test.describe( 'editor', () => {
	test.skip( ( { colorScheme } ) => 'dark' === colorScheme, 'the editor runs once per width' );
	test.skip( ! process.env.CR_WP_USER || ! process.env.CR_WP_PASS, 'set CR_WP_USER and CR_WP_PASS' );

	test.beforeEach( async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', process.env.CR_WP_USER );
		await page.fill( '#user_pass', process.env.CR_WP_PASS );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
	} );

	const canvas = async ( page ) => {
		await page.locator( 'iframe[name="editor-canvas"], .editor-styles-wrapper' ).first().waitFor( { timeout: 30000 } );
		return ( await page.locator( 'iframe[name="editor-canvas"]' ).count() ) ? page.frameLocator( 'iframe[name="editor-canvas"]' ) : page;
	};

	for ( const { kind, path } of STREAM ) {
		test( `post editor: a Breadcrumbs block in a ${ kind } post draws the front-end trail`, async ( { page } ) => {
			await page.goto( path );
			const front = await trails( page ).first().locator( 'li' ).allInnerTexts();
			const id = await page.evaluate( () => Number( ( document.body.className.match( /postid-(\d+)/ ) || [] )[ 1 ] ) );
			await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
			await page.waitForFunction( () => window.wp?.data?.select( 'core/editor' )?.getCurrentPostId() );
			await page.evaluate( () => {
				window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
				window.wp.data.dispatch( 'core/block-editor' ).insertBlocks( window.wp.blocks.createBlock( 'core/breadcrumbs' ) );
			} );
			const frame = await canvas( page );
			const items = frame.locator( '.wp-block-breadcrumbs li' );
			await expect( items ).toHaveText( front.map( ( t ) => t.trim() ) );
			await expect( frame.locator( '.wp-block-breadcrumbs [aria-current]' ) ).toHaveCount( 1 );
		} );
	}

	test( 'site editor: the single template shows its Breadcrumbs block', async ( { page } ) => {
		await page.goto( '/wp-admin/site-editor.php?p=%2Fwp_template%2Fcourtneyr-child%2F%2Fsingle&canvas=edit' );
		const frame = await canvas( page );
		await expect( frame.locator( '.wp-block-breadcrumbs' ) ).toHaveCount( 1, { timeout: 30000 } );
		await expect( frame.locator( '.wp-block-breadcrumbs li' ).first() ).toHaveText( 'Home' );
	} );
} );
