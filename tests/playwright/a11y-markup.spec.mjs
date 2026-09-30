// `npm run test:a11y-markup`: DOM assertions for the output-level accessibility fixes in
// inc/a11y-output.php and patterns/cr-hcard.php. Read-only GETs; needs a running site
// (CR_BASE_URL, CR_ALLOW_REMOTE=1 and CR_USER_AGENT for a Pantheon sandbox). Post paths
// default to dev fixtures and can be pointed elsewhere with the CR_*_PATH variables.
import { test, expect } from '@playwright/test';

const ABLEPLAYER_POST = process.env.CR_ABLEPLAYER_POST_PATH || '/?p=3010'; // Able Player YouTube shortcode
const ABLEPLAYER_URL_POST = process.env.CR_ABLEPLAYER_URL_POST_PATH || '/?p=1053'; // youtube-id written as a full watch URL
const COMMENTS_POST = process.env.CR_COMMENTS_POST_PATH || '/?p=38071'; // backfeed comments, incl. two with no avatar
const QUOTE_POST = process.env.CR_QUOTE_POST_PATH || '/?p=38049'; // Syndication Links plugin output
const BROWSE_PAGE = process.env.CR_BROWSE_ALL_PATH || '/stream/'; // cr-browse-all pattern (post_format list)
const TABLE_POST = process.env.CR_TABLE_POST_PATH || '/?p=551'; // legacy comparison table with headings and links in <th>
const TERM_ARCHIVE = process.env.CR_TERM_ARCHIVE_PATH || '/type/aside/'; // term archive with a description
const CUTOUT_ARCHIVES = ( process.env.CR_CUTOUT_ARCHIVE_PATHS || '/kind/mood/,/type/aside/,/?s=wordpress,/stream/' ).split( ',' ); // cut-paper archive titles
const PAGED_KIND_ARCHIVE = process.env.CR_PAGED_KIND_ARCHIVE_PATH || '/kind/note/page/2/'; // a /kind/* archive with at least three pages
const POST_NAV_POST = process.env.CR_POST_NAV_POST_PATH || '/?p=38071'; // a Blog post with a titled post on each side
const AUTOEMBED_CAPTIONED_POST = process.env.CR_AUTOEMBED_CAPTIONED_POST_PATH || '/?p=3010'; // bare YouTube URL whose video has a registered VTT (_cr_youtube_id)
// Dark-mode contrast fixtures: [ path, selector, text the element must contain ].
const DARK_FIXTURES = [
	[ process.env.CR_RESUME_PAGE_PATH || '/?page_id=37840', 'code', 'beta-rc' ], // inline code inside page content
	[ process.env.CR_ALERT_POST_PATH || '/?p=37492', 'p.is-style-sme-alert-success', 'Disclosure' ], // SME success alert
	[ process.env.CR_MARK_POST_PATH || '/?p=50', 'mark', 'love the Lord' ], // highlighted verse
	[ process.env.CR_MARK_POST2_PATH || '/?p=751', 'mark', 'FREE' ], // highlighted word
];

test( 'footer h-card photo is a decorative image inside the named link', async ( { page } ) => {
	await page.goto( '/', { waitUntil: 'load' } );
	const link = page.locator( '.cr-hcard a.u-url' );
	await expect( link ).toHaveCount( 1 );
	const img = link.locator( 'img.cr-hcard__photo' );
	await expect( img ).toHaveCount( 1 );
	await expect( img ).toHaveAttribute( 'alt', '' );
	expect( await img.getAttribute( 'aria-hidden' ) ).toBeNull();
	expect( ( await link.textContent() )?.trim().length ).toBeGreaterThan( 2 );
	expect( await page.locator( '.cr-hcard img[aria-hidden]' ).count() ).toBe( 0 );
} );

test( 'Able Player YouTube players carry the captions/transcript link', async ( { page } ) => {
	await page.goto( ABLEPLAYER_POST, { waitUntil: 'load' } );
	// Able Player swaps its <video data-youtube-id> for an iframe at init; count either form.
	const players = page.locator( '[data-youtube-id], iframe[id^="able_player_"][id$="_youtube"]' );
	const count = await players.count();
	expect( count, 'fixture post renders at least one Able Player YouTube player' ).toBeGreaterThan( 0 );
	// Each player gets a figcaption: the in-player captions/transcript note when the video has a
	// caption file (shortcode attribute or a registered _cr_youtube_id VTT), else the YouTube link.
	const captions = page.locator( 'figure.cr-media--ableplayer figcaption.cr-media__transcript' );
	expect( await captions.count() ).toBeGreaterThanOrEqual( count );
	for ( const caption of await captions.all() ) {
		expect( ( await caption.textContent() )?.toLowerCase() ).toContain( 'transcript' );
		for ( const href of await caption.locator( 'a' ).evaluateAll( ( as ) => as.map( ( a ) => a.href ) ) ) {
			expect( href ).toMatch( /^https:\/\/www\.youtube\.com\/watch\?v=[A-Za-z0-9_-]{11}$/ );
		}
	}
} );

test( 'Able Player shortcodes whose youtube-id is a full URL still get the transcript link', async ( { page } ) => {
	await page.goto( ABLEPLAYER_URL_POST, { waitUntil: 'load' } );
	const players = page.locator( '[data-youtube-id], iframe[id^="able_player_"][id$="_youtube"]' );
	const count = await players.count();
	expect( count, 'fixture post renders at least one Able Player YouTube player' ).toBeGreaterThan( 0 );
	// Every player gets a figcaption that names the transcript: a YouTube link when the
	// shortcode has no captions track, a sentence pointing at Able Player's Transcript
	// control when it has one (these fixture posts do).
	const captions = page.locator( 'figure.cr-media--ableplayer figcaption.cr-media__transcript' );
	expect( await captions.count() ).toBeGreaterThanOrEqual( count );
	for ( const text of await captions.allTextContents() ) {
		expect( text.toLowerCase() ).toContain( 'transcript' );
	}
	const links = captions.locator( 'a' );
	for ( const href of await links.evaluateAll( ( a ) => a.map( ( el ) => el.getAttribute( 'href' ) ) ) ) {
		// An eleven-character id, never the pasted URL nested inside another URL.
		expect( href ).toMatch( /^https:\/\/www\.youtube\.com\/watch\?v=[A-Za-z0-9_-]{11}$/ );
	}
} );

test( 'comment avatars are decorative and never render with an empty src', async ( { page } ) => {
	await page.goto( COMMENTS_POST, { waitUntil: 'load' } );
	const avatars = page.locator( '.wp-block-avatar img' );
	expect( await avatars.count() ).toBeGreaterThan( 0 );
	for ( const img of await avatars.all() ) {
		expect( ( await img.getAttribute( 'src' ) ) || ( await img.getAttribute( 'data-src' ) ) ).not.toBe( '' );
		await expect( img ).toHaveAttribute( 'alt', '' );
		await expect( img ).toHaveAttribute( 'aria-hidden', 'true' );
	}
	// A comment whose avatar resolves to nothing renders no <img> at all.
	expect( await page.locator( '.wp-block-avatar img[src=""]' ).count() ).toBe( 0 );
} );

test( 'syndication links are not smaller than 11px', async ( { page } ) => {
	await page.goto( QUOTE_POST, { waitUntil: 'load' } );
	const links = page.locator( '.syndication-links .syn-link' );
	expect( await links.count() ).toBeGreaterThan( 0 );
	for ( const a of await links.all() ) {
		const size = parseFloat( await a.evaluate( ( el ) => getComputedStyle( el ).fontSize ) );
		expect( size ).toBeGreaterThanOrEqual( 11 );
	}
} );

test( 'post-format list links name what they link to', async ( { page } ) => {
	await page.goto( BROWSE_PAGE, { waitUntil: 'load' } );
	const items = page.locator( '.cr-browse-all__list li.cat-item a[href*="/type/"]' );
	expect( await items.count() ).toBeGreaterThan( 0 );
	for ( const a of await items.all() ) {
		const name = ( await a.textContent() )?.replace( /\s+/g, ' ' ).trim() || '';
		expect( name.toLowerCase() ).toMatch( /\bposts$/ );
		expect( name.toLowerCase() ).not.toBe( 'link' );
	}
} );

test( 'headings and links inside table header cells take the cell colour', async ( { page } ) => {
	await page.goto( TABLE_POST, { waitUntil: 'load' } );
	// Links transition their colour when the component sheet applies after the token sheet;
	// read the settled values, not a frame from the 200 ms transition.
	await page.evaluate( () => Promise.all( document.getAnimations().map( ( a ) => a.finished ) ) );
	await page.waitForTimeout( 400 );
	const cells = page.locator( '.wp-block-post-content th, .entry-content th' );
	expect( await cells.count() ).toBeGreaterThan( 0 );
	for ( const th of await cells.all() ) {
		const thColor = await th.evaluate( ( el ) => getComputedStyle( el ).color );
		for ( const inner of await th.locator( 'h1, h2, h3, h4, h5, h6, a' ).all() ) {
			expect( await inner.evaluate( ( el ) => getComputedStyle( el ).color ) ).toBe( thColor );
		}
	}
} );

test( 'term archive description stays below heading size', async ( { page } ) => {
	// Accessibility Checker's possible_heading flags a <p> of 50 characters or fewer at 20px or more.
	await page.goto( TERM_ARCHIVE, { waitUntil: 'load' } );
	const lede = page.locator( '.cr-archive__description p' );
	expect( await lede.count() ).toBeGreaterThan( 0 );
	for ( const p of await lede.all() ) {
		const size = parseFloat( await p.evaluate( ( el ) => getComputedStyle( el ).fontSize ) );
		expect( size ).toBeLessThan( 20 );
		expect( size ).toBeGreaterThanOrEqual( 16 );
	}
} );

test( 'dark mode keeps AA contrast on inline code, the success alert and highlights', async ( { page } ) => {
	// The theme toggle reads localStorage before paint; force Dark regardless of the project's colour scheme.
	await page.addInitScript( () => { try { localStorage.setItem( 'courtneyr-theme', 'dark' ); } catch ( e ) {} } );
	for ( const [ path, selector, text ] of DARK_FIXTURES ) {
		await page.goto( path, { waitUntil: 'load' } );
		await page.evaluate( () => Promise.all( document.getAnimations().map( ( a ) => a.finished ) ) );
		const result = await page.evaluate( ( [ sel, needle ] ) => {
			const el = [ ...document.querySelectorAll( sel ) ].find( ( e ) => e.textContent.includes( needle ) );
			if ( ! el ) { return { missing: true }; }
			const channels = ( c ) => c.match( /\d+(\.\d+)?/g ).slice( 0, 3 ).map( Number );
			const lum = ( c ) => { const [ r, g, b ] = channels( c ).map( ( v ) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ); } ); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
			let node = el, bg = null;
			while ( node && node !== document.documentElement ) { const c = getComputedStyle( node ).backgroundColor; if ( c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent' ) { bg = c; break; } node = node.parentElement; }
			if ( ! bg ) { return { missing: false, noBackground: true }; }
			const l1 = lum( getComputedStyle( el ).color ), l2 = lum( bg );
			return { theme: document.documentElement.getAttribute( 'data-theme' ), ratio: ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 ) };
		}, [ selector, text ] );
		expect( result.missing, `${ selector } on ${ path }` ).toBeFalsy();
		expect( result.noBackground, `${ selector } on ${ path }` ).toBeFalsy();
		expect( result.theme ).toBe( 'dark' );
		expect( result.ratio, `${ selector } on ${ path }` ).toBeGreaterThanOrEqual( 4.5 );
	}
} );

test( 'a YouTube autoembed picks up the site caption file registered for its video', async ( { page } ) => {
	// Able Player replaces the <video> (and its <track>) with a YouTube iframe at init, so read the
	// server markup rather than the live DOM.
	const html = await ( await page.request.get( AUTOEMBED_CAPTIONED_POST ) ).text();
	const figure = html.match( /<figure class="cr-media cr-media--ableplayer">[\s\S]*?<\/figure>/ );
	expect( figure, 'fixture post renders an Able Player figure' ).not.toBeNull();
	const track = figure[ 0 ].match( /<track\b[^>]*\bkind="captions"[^>]*\bsrc="([^"]+)"/ ) || figure[ 0 ].match( /<track\b[^>]*\bsrc="([^"]+)"[^>]*\bkind="captions"/ );
	expect( track, 'the player carries a captions track' ).not.toBeNull();
	expect( track[ 1 ] ).toMatch( /\.vtt$/ );
	await page.goto( AUTOEMBED_CAPTIONED_POST, { waitUntil: 'load' } );
	await expect( page.locator( 'figure.cr-media--ableplayer figcaption.cr-media__transcript' ).first() ).toContainText( /transcript/i );
} );

test( 'archive titles are one h1 named by the exact title, with aria-hidden per-letter tiles', async ( { page } ) => {
	for ( const path of CUTOUT_ARCHIVES ) {
		await page.goto( path, { waitUntil: 'load' } );
		const h1 = page.locator( 'h1' );
		await expect( h1, path ).toHaveCount( 1 );
		await expect( h1, path ).toHaveClass( /cr-cutout/ );
		const title = ( await h1.locator( '.cr-cutout__text' ).textContent() )?.trim();
		expect( title?.length, path ).toBeGreaterThan( 0 );
		// The accessible name is the title alone: no tile letters, no flank sparkles.
		await expect( h1, path ).toHaveAccessibleName( title );
		await expect( h1.locator( '.cr-cutout__tiles' ), path ).toHaveAttribute( 'aria-hidden', 'true' );
		// One tile per non-space grapheme, in order.
		const glyphs = await h1.locator( '.cr-cutout__tile' ).allTextContents();
		const graphemes = [ ...new Intl.Segmenter().segment( title.replace( /\s+/g, '' ) ) ].map( ( g ) => g.segment );
		expect( glyphs, path ).toEqual( graphemes );
		expect( await h1.locator( 'a, button, input, [tabindex]' ).count(), path ).toBe( 0 );
	}
	// The front page has exactly one: the Field notes heading, still an h2 named by its text.
	await page.goto( '/', { waitUntil: 'load' } );
	const cut = page.locator( '.cr-cutout' );
	await expect( cut ).toHaveCount( 1 );
	await expect( cut ).toHaveClass( /cr-fieldnotes__title/ );
	expect( await cut.evaluate( ( el ) => el.tagName ) ).toBe( 'H2' );
	await expect( cut ).toHaveAccessibleName( ( await cut.locator( '.cr-cutout__text' ).textContent() )?.trim() );
} );

test( 'homepage lane titles lead with the same emoji as their nav links', async ( { page } ) => {
	await page.goto( '/', { waitUntil: 'load' } );
	const firstGrapheme = ( s ) => [ ...new Intl.Segmenter().segment( s.trim() ) ][ 0 ]?.segment;
	for ( const [ lane, path, word ] of [ [ 'blog', '/blog/', 'Blog' ], [ 'stream', '/stream/', 'Stream' ] ] ) {
		const navLabel = await page.locator( `header a[href$="${ path }"]` ).first().textContent( { timeout: 5000 } );
		const navEmoji = firstGrapheme( navLabel );
		const title = page.locator( `.cr-fieldnotes__lane-title--${ lane }` );
		const glyph = title.locator( '.cr-fieldnotes__lane-glyph' );
		await expect( glyph, lane ).toHaveCount( 1 );
		await expect( glyph, lane ).toBeVisible();
		await expect( glyph, lane ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( ( await glyph.textContent() )?.trim(), `${ lane } glyph matches nav "${ navLabel }"` ).toBe( navEmoji );
		await expect( title, lane ).toHaveAccessibleName( word );
	}
} );

test( 'kind archive pagination is the shared centered pager with the current page filled', async ( { page } ) => {
	await page.goto( PAGED_KIND_ARCHIVE, { waitUntil: 'load' } );
	const pager = page.locator( 'nav.wp-block-query-pagination' );
	await expect( pager ).toHaveCount( 1 );
	await expect( pager ).not.toHaveClass( /cr-stream__pagination/ );
	expect( await page.locator( '#courtneyr-nav-css, #courtneyr-nav-inline-css' ).count(), 'cr-nav.css is loaded' ).toBeGreaterThan( 0 );
	const layout = await pager.evaluate( ( nav ) => {
		const box = nav.getBoundingClientRect();
		const kids = [ ...nav.children ].map( ( el ) => el.getBoundingClientRect() );
		return {
			justify: getComputedStyle( nav ).justifyContent,
			left: Math.min( ...kids.map( ( k ) => k.left ) ) - box.left,
			right: box.right - Math.max( ...kids.map( ( k ) => k.right ) ),
		};
	} );
	expect( layout.justify ).toBe( 'center' );
	expect( Math.abs( layout.left - layout.right ), 'row is centered' ).toBeLessThanOrEqual( 2 );
	const current = pager.locator( '.page-numbers.current' );
	await expect( current ).toHaveCount( 1 );
	await expect( current ).toHaveAttribute( 'aria-current', 'page' );
	const colors = await current.evaluate( ( el ) => {
		const probe = ( value ) => { const d = document.createElement( 'span' ); d.style.color = value; document.body.append( d ); const c = getComputedStyle( d ).color; d.remove(); return c; };
		const cs = getComputedStyle( el );
		return { bg: cs.backgroundColor, color: cs.color, ink: probe( 'var(--cr-ink)' ), inverse: probe( 'var(--cr-ink-inverse)' ) };
	} );
	expect( colors.bg ).toBe( colors.ink );
	expect( colors.color ).toBe( colors.inverse );
	for ( const chip of await pager.locator( 'a' ).all() ) {
		const style = await chip.evaluate( ( el ) => ( { border: parseFloat( getComputedStyle( el ).borderTopWidth ), shadow: getComputedStyle( el ).boxShadow } ) );
		expect( style.border ).toBeGreaterThanOrEqual( 2 );
		expect( style.shadow ).not.toBe( 'none' );
	}
} );

test( 'single posts have a Post navigation landmark whose links name the adjacent post', async ( { page } ) => {
	await page.goto( POST_NAV_POST, { waitUntil: 'load' } );
	const nav = page.getByRole( 'navigation', { name: 'Post navigation', exact: true } );
	await expect( nav ).toHaveCount( 1 );
	const links = nav.getByRole( 'link' );
	expect( await links.count() ).toBeGreaterThan( 0 );
	for ( const link of await links.all() ) {
		const visible = ( await link.locator( '.post-navigation-link__label' ).textContent() )?.trim();
		expect( [ 'Previous', 'Next' ] ).toContain( visible );
		const title = ( await link.locator( '.post-navigation-link__title' ).textContent() )?.trim() || '';
		expect( title.length ).toBeGreaterThan( 0 );
		expect( title ).not.toMatch( /^(Previous|Next) Post$/ );
		// Label in name: the accessible name starts with the visible text and carries the title.
		await expect( link ).toHaveAccessibleName( `${ visible } ${ title }` );
		await expect( link.locator( '.post-navigation-link__title' ) ).toHaveClass( /screen-reader-text/ );
		// The title belongs to the page the link opens.
		const html = await ( await page.request.get( await link.getAttribute( 'href' ) ) ).text();
		const destination = html.match( /<title>([^<]*)<\/title>/ )?.[ 1 ] || '';
		const decode = ( s ) => s.replace( /&#8217;/g, '’' ).replace( /&#8211;/g, '–' ).replace( /&#8220;|&#8221;/g, '"' ).replace( /&amp;/g, '&' );
		expect( decode( destination ) ).toContain( title );
	}
} );

test( 'the Stream page has no pagination and no post navigation', async ( { page } ) => {
	await page.goto( '/stream/', { waitUntil: 'load' } );
	expect( await page.locator( '.wp-block-query-pagination, .page-numbers, .cr-post-nav' ).count() ).toBe( 0 );
} );
