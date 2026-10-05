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
const LISTEN_ARCHIVE = process.env.CR_LISTEN_ARCHIVE_PATH || '/kind/listen/'; // listen archive with at least one listen post (PKIW #226)
const WATCH_ARCHIVE = process.env.CR_WATCH_ARCHIVE_PATH || '/kind/watch/'; // watch archive page 1 (PKIW #227)
const COMICS_ARCHIVE = process.env.CR_COMICS_ARCHIVE_PATH || '/kind/comics/'; // comics archive with at least one comic read (PKIW #228)
const COMIC_SINGLE = process.env.CR_COMIC_SINGLE_PATH || '/2026/09/06/anzuelo/'; // a comic read (comic-card) still being read, with a cover and a stored start date
const RECIPE_ARCHIVE = process.env.CR_RECIPE_ARCHIVE_PATH || '/kind/recipe/'; // recipe archive (PKIW #229); its tests skip on a site with no recipe posts
const EAT_ARCHIVE = process.env.CR_EAT_ARCHIVE_PATH || '/kind/eat/'; // eat archive (PKIW #230); its test skips on a site with fewer than three eat posts
const DRINK_ARCHIVE = process.env.CR_DRINK_ARCHIVE_PATH || '/kind/drink/'; // drink archive (PKIW #230); its test skips on a site with fewer than three drink posts
const KIND_ARCHIVE_HEADERS = ( process.env.CR_KIND_ARCHIVE_HEADER_PATHS || '/kind/comics/,/kind/watch/,/kind/listen/,/kind/recipe/,/kind/eat/,/kind/drink/,/kind/note/' ).split( ',' ); // every dressed kind archive and one on the generic template; a path with no posts is skipped
const EAT_SINGLE = process.env.CR_EAT_SINGLE_PATH || '/2026/09/25/mushroom-tacos/'; // an eat post with the eat card, a photo and public coordinates (local fixture)
const DRINK_SINGLE = process.env.CR_DRINK_SINGLE_PATH || '/2026/09/24/honey-lavender-latte/'; // a drink post with the drink card, a photo and public coordinates (local fixture)
const ORDER_HIDDEN = ( process.env.CR_ORDER_HIDDEN_PATHS || '/2026/08/05/salmon-sashimi/,/2026/08/07/spicy-margarita/' ).split( ',' ); // eat and drink posts whose location privacy is private (local fixtures)
const ORDER_NO_COORDS = process.env.CR_ORDER_NO_COORDS_PATH || '/2026/09/06/seed-drink/'; // a drink post with a place name and town and no coordinates (local fixture)
const ORDER_SLOC = process.env.CR_ORDER_SLOC_PATH || '/2026/08/27/old-fashioned/'; // a drink post whose card has no location and whose Simple Location point is public (local fixture)
const ORDER_STREAM = process.env.CR_ORDER_STREAM_PATH || '/stream/'; // a Stream page that shows an eat or drink post (local fixture); the test skips when it shows none
const DRINK_UNSET = process.env.CR_DRINK_UNSET_PATH || '/2026/09/05/house-lemonade/'; // a drink post whose card has no drink type and whose `_pkiw_drink_type` is empty (local fixture)
const RECIPE_SINGLE = process.env.CR_RECIPE_SINGLE_PATH || '/2026/08/14/tomato-basil-soup/'; // a recipe post with a WP Recipe Maker recipe and a featured image (local fixture)
const RECIPE_PLAIN_SINGLE = process.env.CR_RECIPE_PLAIN_SINGLE_PATH || '/2026/08/01/campfire-chili/'; // a recipe post with no recipe card (local fixture)
const RECIPE_STREAM = process.env.CR_RECIPE_STREAM_PATH || '/stream/'; // a Stream page that shows a recipe post (local fixture); the test skips when it shows none
const COMIC_TRAILING = process.env.CR_COMIC_TRAILING_PATH || ''; // a comic read with body text after the card (no such post on dev: set it to run the test)
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

// /stream keeps the pager saved in its own page content (it had one before
// 0.7.70); what it must never gain is single-post navigation or an archive
// shelf/menu layout, and its cards stay in the loose multi-column collage.
test( 'the Stream page gains no post navigation or archive layout', async ( { page } ) => {
	await page.goto( '/stream/', { waitUntil: 'load' } );
	expect( await page.locator( '.cr-post-nav, .is-style-pkiw-shelf, .is-style-pkiw-menu, .pkiw-kind-archive' ).count() ).toBe( 0 );
	const columns = await page.locator( 'body.cr-stream-page .wp-block-post-template' ).first().evaluate( ( el ) => getComputedStyle( el ).columnWidth );
	expect( columns ).not.toBe( 'auto' );
} );

// PKIW #226: the listen archive stands the Stream's cassettes upright on Post
// Kinds shelf rows. One title link per case, no players, a board under every
// row that reaches past the case (empty shelf is CSS, not placeholder posts),
// and the boombox is a CSS background, not content.
test( 'the listen archive is a shelf of cassette cases, one title link each, no players', async ( { page } ) => {
	await page.goto( LISTEN_ARCHIVE, { waitUntil: 'load' } );
	const list = page.locator( 'ul.cr-media-shelf__list.is-style-pkiw-shelf' );
	await expect( list ).toHaveCount( 1 );
	const cases = list.locator( ':scope > li' );
	expect( await cases.count() ).toBeGreaterThan( 0 );
	expect( await list.locator( 'iframe' ).count(), 'no provider player on the shelf' ).toBe( 0 );
	for ( const item of await cases.all() ) {
		const card = item.locator( 'article.pk-card.cr-cassette--stream.cr-media--shelf' );
		await expect( card ).toHaveCount( 1 );
		await expect( card.locator( '.pk-kindlabel' ) ).toHaveText( 'Listen · Cassette' );
		await expect( card.locator( '.cr-media__mech' ) ).toHaveCount( 1 );
		await expect( card.locator( '.pk-title a' ) ).toHaveCount( 1 );
		const shape = await item.evaluate( ( li ) => {
			const board = getComputedStyle( li, '::after' );
			return {
				board: board.content,
				boardWidth: parseFloat( board.width ),
				itemWidth: li.getBoundingClientRect().width,
				tilt: getComputedStyle( li.querySelector( '.pk-body' ) ).transform,
			};
		} );
		expect( shape.board ).toBe( '""' );
		expect( shape.boardWidth, 'the board runs past the case to the row end' ).toBeGreaterThan( shape.itemWidth );
		expect( shape.tilt, 'cases stand upright' ).toBe( 'none' );
	}
	// The whole case is the title link's hit area.
	const first = cases.first();
	const hit = await first.evaluate( ( li ) => {
		const box = li.querySelector( '.pk-body' ).getBoundingClientRect();
		const el = document.elementFromPoint( box.left + box.width / 2, box.top + box.height * 0.3 );
		return el?.closest( 'a' ) === li.querySelector( '.pk-title a' );
	} );
	expect( hit ).toBe( true );
	expect( await page.locator( '.cr-archive--listen .cr-archive__header img, .cr-archive--listen .cr-archive__header svg[aria-label]' ).count(), 'the boombox adds no content' ).toBe( 0 );
	const boombox = await page.locator( '.cr-archive--listen .cr-archive__header' ).evaluate( ( el ) => getComputedStyle( el, '::after' ).backgroundImage );
	expect( boombox ).toContain( 'cr-boombox.svg' );
} );

// PKIW #227: the watch archive's page 1 is a "New releases" shelf of up to
// three face-out clamshells and an "All watches" shelf of spines, each a
// real h2 over card titles at h3. Later pages are all spines. A spine is
// one link (its title); no players anywhere on the shelf.
test( 'the watch archive is labelled VHS shelves, face-out new releases then titled spines', async ( { page } ) => {
	await page.goto( WATCH_ARCHIVE, { waitUntil: 'load' } );
	const shelf = page.locator( '.cr-archive--watch .cr-vhs-shelf' );
	await expect( shelf ).toHaveCount( 1 );
	expect( await shelf.locator( 'iframe' ).count(), 'no provider player on the shelf' ).toBe( 0 );
	const labels = shelf.locator( 'h2.cr-vhs-shelf__label' );
	const texts = await labels.allTextContents();
	expect( texts[ 0 ] ).toBe( 'New releases' );
	expect( await shelf.locator( '.pk-title:not(h3)' ).count(), 'every card title, face-out or spine, is an h3 under its shelf label' ).toBe( 0 );
	const face = shelf.locator( 'ul.cr-vhs-shelf__list--face[aria-labelledby="cr-vhs-new"] > li' );
	const faceCount = await face.count();
	expect( faceCount ).toBeGreaterThan( 0 );
	expect( faceCount ).toBeLessThanOrEqual( 3 );
	for ( const item of await face.all() ) {
		await expect( item.locator( 'h3.pk-title a' ) ).toHaveCount( 1 );
	}
	const spines = shelf.locator( 'ul.cr-vhs-shelf__list--spine[aria-labelledby="cr-vhs-all"] > li' );
	if ( await spines.count() ) {
		expect( texts[ 1 ] ).toBe( 'All watches' );
		for ( const item of await spines.all() ) {
			const visibleLinks = await item.evaluate( ( li ) => [ ...li.querySelectorAll( 'a' ) ].filter( ( a ) => a.getClientRects().length > 0 ).length );
			expect( visibleLinks, 'a spine is one link' ).toBe( 1 );
			const mode = await item.locator( '.pk-caption' ).evaluate( ( el ) => getComputedStyle( el ).writingMode );
			expect( mode ).toBe( 'vertical-rl' );
		}
	}
	const decor = await page.locator( '.cr-archive--watch .cr-vhs-shelf' ).evaluate( ( el ) => getComputedStyle( el, '::before' ).backgroundImage );
	expect( decor, 'the TV still life is a CSS background on the unit, not content' ).toContain( 'cr-tv-vcr.svg' );
	expect( await page.locator( '.cr-archive--watch .cr-vhs-shelf img, .cr-archive--watch .cr-vhs-shelf svg' ).evaluateAll( ( els ) => els.filter( ( e ) => ! e.closest( '.pk-card' ) ).length ), 'no decoration enters the DOM' ).toBe( 0 );
} );

// PKIW #228: the comics archive is a comic-shop rack filled by the archive
// query. Each comic read is its bagged cover and one link (the h2 title,
// stretched over the bag; the rack has no section labels, so titles sit
// directly under the h1); rating, dates, creators and notes stay on the
// single. Empty rack space is CSS, never placeholder items.
test( 'the comics archive is a rack of bagged comics, one title link each, no metadata under the covers', async ( { page } ) => {
	await page.goto( COMICS_ARCHIVE, { waitUntil: 'load' } );
	await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
	const list = page.locator( '.cr-archive--comics ul.cr-comic-rack__list' );
	await expect( list ).toHaveCount( 1 );
	const items = list.locator( ':scope > li' );
	const count = await items.count();
	expect( count ).toBeGreaterThan( 0 );
	expect( await list.locator( 'article.pk-card' ).count(), 'one card per query item, no filler' ).toBe( count );
	expect( await list.locator( '.pk-title:not(h2)' ).count(), 'every rack title is an h2 under the archive h1' ).toBe( 0 );
	const bagged = list.locator( 'article.pk-card.cr-comic--rack' );
	expect( await bagged.count(), 'the fixture archive holds at least one comic read' ).toBeGreaterThan( 0 );
	const issuesByName = new Map();
	for ( const card of await bagged.all() ) {
		const link = card.locator( 'h2.pk-title a' );
		await expect( link ).toHaveCount( 1 );
		expect( ( await link.textContent() )?.trim().length ).toBeGreaterThan( 0 );
		// Tabbing the rack or listing its links, a link's name alone tells one
		// issue of a series from the next. Volume and issue print beside the
		// link, so the link is named as the tag reads; a series the tag leaves
		// out (it equals the title) is not said twice.
		const tag = ( await card.locator( '.cr-comic__label span' ).allTextContents() ).map( ( t ) => t.trim() );
		await expect( link ).toHaveAccessibleName( [ ( await link.textContent() ).trim(), ...tag ].join( ' ' ) );
		// The aria snapshot's first line is the link's role and computed name.
		const name = ( await link.ariaSnapshot() ).split( '\n' )[ 0 ];
		const issue = ( await card.locator( '.pk-comic-volume, .pk-comic-number' ).allTextContents() ).map( ( t ) => t.trim() ).join( ' ' );
		issuesByName.set( name, ( issuesByName.get( name ) ?? new Set() ).add( issue ) );
		// Cover art need not carry the title, so the title is readable on the shelf tag.
		const shown = await link.evaluate( ( a ) => {
			const cs = getComputedStyle( a );
			const box = a.getBoundingClientRect();
			return { visible: cs.color !== 'rgba(0, 0, 0, 0)' && cs.textIndent === '0px' && box.width > 8 && box.height > 8, upright: getComputedStyle( a.closest( '.pk-caption' ) ).transform };
		} );
		expect( shown.visible, 'the title text is visible' ).toBe( true );
		expect( shown.upright ).toBe( 'none' );
		expect( await card.locator( 'a' ).count(), 'a racked comic is one link' ).toBe( 1 );
		expect( await card.locator( '.pk-stars, .p-rating, .pk-note, .pk-meta, .pk-kindlabel, .pk-stream-date, .pk-sources, .p-author, .pk-comic-status, .pk-comic-publisher, time' ).count(), 'no rating, date, creator, note or status paragraph on the rack' ).toBe( 0 );
		// The bag is the link's hit area, and the link is not wrapped around the cover.
		const hit = await card.evaluate( ( el ) => {
			// Instant: the site scrolls smoothly, and a smooth scroll has not moved yet.
			el.scrollIntoView( { block: 'center', behavior: 'instant' } );
			const bag = el.querySelector( '.pk-media' ).getBoundingClientRect();
			const at = document.elementFromPoint( bag.left + bag.width / 2, bag.top + bag.height / 2 );
			return { link: at?.closest( 'a' ) === el.querySelector( '.pk-title a' ), imgInLink: !! el.querySelector( 'a img' ) };
		} );
		expect( hit.link, 'the bag is the title link' ).toBe( true );
		expect( hit.imgInLink ).toBe( false );
		for ( const sticker of await card.locator( '.cr-comic__sticker' ).allTextContents() ) {
			expect( [ 'Currently reading', 'To read', 'Set aside' ] ).toContain( sticker.trim() );
		}
	}
	for ( const [ name, issues ] of issuesByName ) {
		expect( [ ...issues ], `rack links that read ${ name } are one volume and issue` ).toHaveLength( 1 );
	}
	// A strip its author drew is on the rack as its picture and one title link.
	for ( const strip of await list.locator( 'article.pk-card.cr-comic-strip' ).all() ) {
		// The entry's hidden author h-card link is not rendered; count what is.
		const rendered = await strip.evaluate( ( el ) => [ ...el.querySelectorAll( 'a' ) ].filter( ( a ) => a.getClientRects().length > 0 ).length );
		expect( rendered, 'an authored strip is one link' ).toBe( 1 );
		expect( await strip.locator( '.u-read-of, .h-cite' ).count(), 'and claims no read-of' ).toBe( 0 );
	}
	// The rack is as tall as its comics need: one tier per row of the grid.
	// A sparse page keeps the unused bays of its last row and holds no tier
	// for posts that don't exist yet.
	const rack = await list.evaluate( ( ul ) => {
		const cs = getComputedStyle( ul );
		return { tier: parseFloat( cs.gridAutoRows ), cols: cs.gridTemplateColumns.split( ' ' ).length, height: ul.clientHeight, tilt: getComputedStyle( ul.querySelector( '.pk-title' ) ).transform };
	} );
	expect( rack.tier ).toBeGreaterThan( 0 );
	expect( rack.cols, 'a row has more than one bay' ).toBeGreaterThan( 1 );
	expect( Math.abs( rack.height - Math.ceil( count / rack.cols ) * rack.tier ), `${ count } comics at ${ rack.cols } across stand on ${ Math.ceil( count / rack.cols ) } tier(s)` ).toBeLessThanOrEqual( 1 );
	expect( rack.tilt ).toBe( 'none' );
	expect( await page.locator( '.cr-archive--comics .cr-comic-rack img, .cr-archive--comics .cr-comic-rack svg' ).evaluateAll( ( els ) => els.filter( ( e ) => ! e.closest( '.pk-card' ) ).length ), 'no decoration enters the DOM' ).toBe( 0 );
} );

// PKIW #228, the polybag: on the rack a comic stands in a loose clear bag.
// The bag is about a fifth of the rack on a wide screen, its plastic is pale
// against the dark rack (Light Gray, Periwinkle and Sky Blue, each short of
// opaque), the cover nearly fills it and rests at its foot, a taller flap
// folds over the top, the corners are soft, and it tilts by
// one rotation token while the shelf tag stays upright. One broad reflection
// fades at both edges; one hard shadow lifts the bag off the rack. The status
// sticker is on the plastic: small, tilted, hand-lettered on two lines, with
// plastic showing above and beside it. Nothing here adds content.
test( 'a racked comic stands in a loose polybag: pale plastic, folded flap, soft reflection, small tilted hand-lettered sticker', async ( { page } ) => {
	await page.goto( COMICS_ARCHIVE, { waitUntil: 'load' } );
	await page.evaluate( () => document.fonts.ready );
	const bagged = page.locator( '.cr-archive--comics ul.cr-comic-rack__list article.pk-card.cr-comic--rack' );
	expect( await bagged.count(), 'the fixture archive holds at least one comic read' ).toBeGreaterThan( 0 );
	// Read in the page. Boxes are layout boxes (offset*), so the bag's tilt doesn't blur the geometry.
	const measure = ( el ) => {
		const alpha = ( c ) => ( c.includes( '/' ) ? parseFloat( c.split( '/' )[ 1 ] ) : c.startsWith( 'rgba' ) ? parseFloat( c.split( ',' )[ 3 ] ) : 1 );
		const rgb = ( c ) => c.match( /[\d.]+/g ).slice( 0, 3 ).map( ( n ) => Math.round( c.startsWith( 'color(' ) ? n * 255 : n ) ).join( ' ' );
		const blurs = ( n ) => [ ...getComputedStyle( n ).boxShadow.matchAll( /-?[\d.]+px -?[\d.]+px (-?[\d.]+)px/g ) ].map( ( m ) => parseFloat( m[ 1 ] ) );
		const probe = ( property, value ) => {
			const i = document.body.appendChild( document.createElement( 'i' ) );
			i.style[ property ] = value;
			const out = getComputedStyle( i )[ property ];
			i.remove();
			return out;
		};
		const colours = ( gradient ) => gradient.match( /(?:color|rgba?)\([^()]*\)/g ) || [];
		const angle = ( transform ) => {
			const m = transform.match( /matrix\(([^)]+)\)/ );
			if ( ! m ) {
				return 0;
			}
			const [ a, b ] = m[ 1 ].split( ',' ).map( parseFloat );
			return Math.round( Math.atan2( b, a ) * ( 180 / Math.PI ) * 10 ) / 10;
		};
		const media = el.querySelector( '.pk-media' );
		const cover = media.querySelector( 'img' );
		const sticker = el.querySelector( '.cr-comic__sticker' );
		const tag = el.querySelector( '.pk-caption' );
		const list = el.closest( 'ul' );
		const slot = el.closest( 'li' );
		const cs = getComputedStyle( media );
		const flap = getComputedStyle( media, '::before' );
		const shine = getComputedStyle( media, '::after' );
		const a = el.querySelector( '.pk-title a' );
		const ring = getComputedStyle( a, '::after' );
		const bounds = ( n ) => {
			const r = n.getBoundingClientRect();
			return { top: r.top, right: r.right, bottom: r.bottom, left: r.left };
		};
		const layers = shine.backgroundImage.split( /,\s*(?=linear-gradient)/ );
		const broad = colours( layers[ layers.length - 1 ] || '' ).map( alpha );
		const lines = ( n ) => {
			const range = document.createRange();
			range.selectNodeContents( n );
			return new Set( [ ...range.getClientRects() ].map( ( r ) => Math.round( r.top ) ) ).size;
		};
		return {
			rack: list.clientWidth,
			sleeve: { width: media.offsetWidth, height: media.offsetHeight, bounds: bounds( media ) },
			slot: bounds( slot ),
			cover: cover ? { width: cover.offsetWidth, left: cover.offsetLeft, top: cover.offsetTop, bottom: media.clientHeight - cover.offsetTop - cover.offsetHeight } : null,
			film: { alpha: alpha( cs.backgroundColor ), rgb: rgb( cs.backgroundColor ), rack: rgb( getComputedStyle( list ).backgroundColor ) },
			edge: { style: cs.borderTopStyle, width: parseFloat( cs.borderTopWidth ), rgb: rgb( cs.borderTopColor ) },
			radius: parseFloat( cs.borderTopLeftRadius ),
			tilt: angle( cs.transform ),
			tilts: [ '--cr-rotate-1', '--cr-rotate-2', '--cr-rotate-neg', '--cr-rotate-neg2' ].map( ( name ) => parseFloat( getComputedStyle( document.documentElement ).getPropertyValue( name ) ) ),
			tag: tag ? getComputedStyle( tag ).transform : null,
			shadow: { value: cs.boxShadow, token: probe( 'boxShadow', 'var(--cr-shadow-hard-sm)' ) },
			flap: { content: flap.content, height: parseFloat( flap.height ), gradient: flap.backgroundImage },
			shine: { content: shine.content, gradients: layers.length, alphas: colours( shine.backgroundImage ).map( alpha ), rgbs: [ ...colours( shine.backgroundImage ), ...colours( flap.backgroundImage ) ].filter( ( c ) => alpha( c ) > 0 ).map( rgb ), broad },
			plastic: [ '--cr-light-gray', '--cr-periwinkle', '--cr-sky-blue' ].map( ( name ) => rgb( probe( 'color', `var(${ name })` ) ) ),
			blurs: [ media, cover, sticker ].filter( Boolean ).flatMap( blurs ),
			art: cover ? [ getComputedStyle( cover ).opacity, getComputedStyle( cover ).filter ] : null,
			sticker: sticker ? {
				width: sticker.offsetWidth,
				bounds: bounds( sticker ),
				font: getComputedStyle( sticker ).fontFamily,
				tilt: angle( getComputedStyle( sticker ).transform ),
				fill: rgb( getComputedStyle( sticker ).backgroundColor ),
				ink: rgb( getComputedStyle( sticker ).color ),
				border: rgb( getComputedStyle( sticker ).borderTopColor ),
				lines: lines( sticker ),
				text: sticker.textContent.trim(),
			} : null,
			paint: { yellow: rgb( probe( 'color', 'var(--cr-selective-yellow)' ) ), violet: rgb( probe( 'color', 'var(--cr-russian-violet)' ) ) },
			ring: { style: ring.outlineStyle, offset: parseFloat( ring.outlineOffset ), box: ( () => {
				let held = a.parentElement;
				while ( getComputedStyle( held ).position === 'static' ) {
					held = held.parentElement;
				}
				const o = held.getBoundingClientRect();
				return { top: o.top + parseFloat( ring.top ), left: o.left + parseFloat( ring.left ), right: o.left + parseFloat( ring.left ) + parseFloat( ring.width ), bottom: o.top + parseFloat( ring.top ) + parseFloat( ring.height ) };
			} )() },
			clips: [ el, slot, list ].map( ( n ) => getComputedStyle( n ).overflow ),
		};
	};
	const wide = page.viewportSize().width >= 1280;
	// Relative luminance of an "r g b" string, and one colour laid over another at an alpha.
	const luminance = ( c ) => c.split( ' ' ).map( ( n ) => n / 255 ).map( ( n ) => ( n <= 0.03928 ? n / 12.92 : ( ( n + 0.055 ) / 1.055 ) ** 2.4 ) ).reduce( ( sum, n, i ) => sum + n * [ 0.2126, 0.7152, 0.0722 ][ i ], 0 );
	const over = ( top, a, under ) => top.split( ' ' ).map( ( n, i ) => Math.round( n * a + under.split( ' ' )[ i ] * ( 1 - a ) ) ).join( ' ' );
	for ( const card of await bagged.all() ) {
		await card.locator( '.pk-title a' ).focus();
		const o = await card.evaluate( measure );
		// Scale: about a fifth of the rack on a wide screen.
		if ( wide ) {
			expect( o.sleeve.width / o.rack, 'the bag is about 21% of the rack' ).toBeGreaterThanOrEqual( 0.19 );
			expect( o.sleeve.width / o.rack ).toBeLessThanOrEqual( 0.23 );
		}
		// Pale plastic: short of opaque, and light where it lies over the dark rack.
		expect( o.film.alpha, 'the plastic is not opaque' ).toBeLessThan( 1 );
		expect( luminance( o.film.rack ), 'the rack behind the bag is dark' ).toBeLessThan( 0.1 );
		expect( luminance( over( o.film.rgb, o.film.alpha, o.film.rack ) ), 'the plastic reads pale over the rack, not as dark glass' ).toBeGreaterThanOrEqual( 0.35 );
		expect( o.plastic, 'the film is Light Gray, Periwinkle or Sky Blue' ).toContain( o.film.rgb );
		expect( o.plastic, 'the edge is Light Gray, Periwinkle or Sky Blue' ).toContain( o.edge.rgb );
		expect( o.shine.rgbs.every( ( c ) => o.plastic.includes( c ) ), 'every highlight is Light Gray, Periwinkle or Sky Blue' ).toBe( true );
		expect( o.edge.style ).toBe( 'solid' );
		expect( o.edge.width, 'a thin edge, not a frame' ).toBeLessThanOrEqual( 1.5 );
		expect( o.radius, 'soft corners' ).toBeGreaterThan( 0 );
		// A slight tilt from one rotation token; the shelf tag stays upright.
		expect( o.tilt, 'the bag is tilted' ).not.toBe( 0 );
		expect( o.tilts, 'by a rotation token' ).toContain( o.tilt );
		expect( o.tag, 'the shelf tag is upright' ).toBe( 'none' );
		// One hard shadow from the token; nothing blurred.
		expect( o.shadow.value, 'a hard shadow token lifts the bag off the rack' ).toBe( o.shadow.token );
		expect( o.blurs.every( ( blur ) => blur === 0 ), 'no blurred shadow' ).toBe( true );
		// The bag stays inside its rack slot, tilt included.
		expect( o.sleeve.bounds.left, 'the bag stays inside its rack slot' ).toBeGreaterThanOrEqual( o.slot.left );
		expect( o.sleeve.bounds.right ).toBeLessThanOrEqual( o.slot.right );
		expect( o.sleeve.bounds.top ).toBeGreaterThanOrEqual( o.slot.top );
		expect( o.sleeve.bounds.bottom ).toBeLessThanOrEqual( o.slot.bottom );
		if ( o.cover ) {
			const rim = { left: o.cover.left, right: o.sleeve.width - 2 * o.edge.width - o.cover.left - o.cover.width, bottom: o.cover.bottom, top: o.cover.top };
			expect( Math.min( rim.left, rim.right, rim.bottom ), 'plastic shows on every side of the cover' ).toBeGreaterThan( 0 );
			expect( Math.max( rim.left, rim.right, rim.bottom ) / o.sleeve.width, 'the rim is narrow: the cover nearly fills the bag' ).toBeLessThanOrEqual( 0.05 );
			expect( rim.top / o.sleeve.width, 'a tall flap over the top' ).toBeGreaterThanOrEqual( 0.08 );
			expect( rim.top, 'the flap is at least three times the rim' ).toBeGreaterThanOrEqual( 3 * rim.left );
			expect( o.flap.height, 'the flap is the plastic above the cover' ).toBeGreaterThanOrEqual( rim.top - 1 );
			expect( rim.bottom, 'the comic rests at the foot of the bag' ).toBeLessThan( rim.left );
			expect( o.art, 'the cover art is not dimmed or filtered' ).toEqual( [ '1', 'none' ] );
		}
		// The flap and the reflections are pseudo-elements with no text.
		expect( o.flap.content ).toBe( '""' );
		expect( o.flap.gradient, 'the flap is folded plastic, not a ruled line' ).toContain( 'gradient' );
		expect( o.shine.content ).toBe( '""' );
		expect( o.shine.gradients, 'a reflection and a few creases' ).toBeGreaterThanOrEqual( 2 );
		expect( o.shine.gradients ).toBeLessThanOrEqual( 5 );
		expect( Math.max( ...o.shine.alphas ), 'the reflections are translucent' ).toBeLessThanOrEqual( 0.4 );
		expect( o.shine.broad[ 0 ], 'the broad reflection fades in' ).toBe( 0 );
		expect( o.shine.broad[ o.shine.broad.length - 1 ], 'and fades out' ).toBe( 0 );
		expect( o.shine.broad.length, 'through soft steps, not one hard band' ).toBeGreaterThanOrEqual( 5 );
		// The sticker is on the plastic: small, tilted, hand-lettered, with plastic above and beside it.
		if ( o.sticker ) {
			if ( wide ) {
				expect( o.sticker.width / o.sleeve.width, 'the sticker is 28 to 30% of the bag' ).toBeGreaterThanOrEqual( 0.275 );
				expect( o.sticker.width / o.sleeve.width ).toBeLessThanOrEqual( 0.305 );
			}
			expect( o.sticker.font, 'hand-lettered' ).toMatch( /^"?Rock Salt/ );
			expect( o.sticker.tilt, 'the sticker is tilted' ).not.toBe( 0 );
			expect( o.tilts, 'by a rotation token' ).toContain( o.sticker.tilt );
			expect( [ o.sticker.fill, o.sticker.ink, o.sticker.border ], 'yellow, or its status colour, with violet ink and border' ).toEqual( [ o.sticker.fill, o.paint.violet, o.paint.violet ] );
			if ( 'Currently reading' === o.sticker.text ) {
				expect( o.sticker.fill ).toBe( o.paint.yellow );
				expect( o.sticker.lines, '"Currently reading" sits on two lines' ).toBe( 2 );
			}
			expect( o.sticker.bounds.top - o.sleeve.bounds.top, 'plastic shows above the sticker' ).toBeGreaterThan( 2 );
			expect( o.sleeve.bounds.right - o.sticker.bounds.right, 'and beside it' ).toBeGreaterThan( 2 );
			expect( o.sticker.bounds.left, 'the sticker is inside the bag' ).toBeGreaterThanOrEqual( o.sleeve.bounds.left );
			expect( o.sticker.bounds.bottom ).toBeLessThanOrEqual( o.sleeve.bounds.bottom );
			expect( ( o.sticker.bounds.left + o.sticker.bounds.right ) / 2, 'on the right half' ).toBeGreaterThan( ( o.sleeve.bounds.left + o.sleeve.bounds.right ) / 2 );
			expect( ( o.sticker.bounds.top + o.sticker.bounds.bottom ) / 2, 'on the upper half' ).toBeLessThan( ( o.sleeve.bounds.top + o.sleeve.bounds.bottom ) / 2 );
		}
		// Cover, bag and sticker focus as one object: the focus outline, drawn
		// its offset outside the link's box, holds all three, and no ancestor
		// up to the rack clips it.
		expect( o.ring.style ).toBe( 'solid' );
		for ( const part of [ o.sleeve.bounds, o.sticker?.bounds ].filter( Boolean ) ) {
			expect( part.top ).toBeGreaterThanOrEqual( o.ring.box.top - o.ring.offset );
			expect( part.left ).toBeGreaterThanOrEqual( o.ring.box.left - o.ring.offset );
			expect( part.right ).toBeLessThanOrEqual( o.ring.box.right + o.ring.offset );
			expect( part.bottom ).toBeLessThanOrEqual( o.ring.box.bottom + o.ring.offset );
		}
		expect( o.clips, 'nothing clips the focus outline' ).toEqual( [ 'visible', 'visible', 'visible' ] );
	}
	// Forced colours: the film, the flap, the reflections and the shadow go; the bag keeps a clear outline.
	await page.emulateMedia( { forcedColors: 'active' } );
	const forced = await bagged.first().evaluate( measure );
	expect( forced.flap.content, 'no flap paint in forced colours' ).toBe( 'none' );
	expect( forced.shine.content, 'no reflections in forced colours' ).toBe( 'none' );
	expect( forced.film.alpha, 'no translucent film in forced colours' ).toBe( 1 );
	expect( forced.shadow.value, 'no shadow in forced colours' ).toBe( 'none' );
	expect( forced.edge.style ).toBe( 'solid' );
	expect( forced.edge.width ).toBeGreaterThanOrEqual( 1 );
} );

// PKIW #228: a comic read's single keeps the bagged comic left of the post
// header, notes card and reading record on wide screens. A comic still being
// read is labelled by its start, never as read or finished.
test( 'a comic single is a bagged comic beside its header, notes and reading record, with truthful date labels', async ( { page } ) => {
	await page.goto( COMIC_SINGLE, { waitUntil: 'load' } );
	await expect( page.locator( 'body.cr-comic-single' ) ).toHaveCount( 1 );
	await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
	const bag = page.locator( '.single-post__content article.pk-card.k-comics.cr-comic--single' );
	await expect( bag ).toHaveCount( 1 );
	await expect( bag.locator( '.pk-kindlabel' ) ).toHaveText( 'Comic' );
	// The cover is the page's largest image and sits at the top, so it loads
	// eagerly with a real src: lazy-load plugins skip fetchpriority="high",
	// and the cover still shows with scripting off. An optimizer may drop the
	// redundant loading="eager"; what matters is that it is never lazy.
	const cover = bag.locator( '.pk-media img' );
	await expect( cover ).toHaveAttribute( 'fetchpriority', 'high' );
	expect( await cover.getAttribute( 'loading' ) ).not.toBe( 'lazy' );
	expect( await cover.getAttribute( 'src' ) ).toMatch( /^https?:/ );
	const chip = page.locator( '.single-post__header .cr-comic__status-chip' );
	await expect( chip ).toHaveText( 'Currently reading' );
	const when = page.locator( '.single-post__header .cr-comic__status-when' );
	await expect( when ).toHaveCount( 1 );
	expect( ( await when.textContent() )?.trim() ).toMatch( /^since / );
	const terms = await page.locator( '.cr-record .cr-record-row dt' ).allTextContents();
	expect( terms ).toContain( 'Started' );
	for ( const term of terms ) {
		expect( [ 'Started', 'Finished', 'Set aside', 'Series', 'Volume', 'Issue', 'Publisher' ] ).toContain( term );
	}
	expect( terms, 'a comic still being read has no end date' ).not.toContain( 'Finished' );
	// The record's start is the stored calendar day, the same one the header shows.
	const started = await page.locator( '.cr-record .cr-record-row:has(dt:text-is("Started")) time' ).getAttribute( 'datetime' );
	expect( started ).toMatch( /^\d{4}-\d{2}-\d{2}$/ );
	expect( await when.locator( 'time' ).getAttribute( 'datetime' ) ).toBe( started );
	// Previous/Next name real posts. A kind with a single post on its surface
	// falls back to the Stream's own order, so the landmark is never empty.
	const nav = page.locator( 'nav.cr-post-nav' );
	await expect( nav ).toHaveCount( 1 );
	const navLinks = await nav.locator( 'a' ).allTextContents();
	expect( navLinks.length, 'Post navigation holds at least one link' ).toBeGreaterThan( 0 );
	for ( const text of navLinks ) {
		expect( text.trim().length ).toBeGreaterThan( 0 );
	}
	// A body that repeats the card's own cover and note (the Micropub shape)
	// shows each once: in the bag and on the notes card.
	const repeats = await page.evaluate( () => {
		const content = document.querySelector( '.single-post__content' );
		const cover = content.querySelector( '.cr-comic--single .pk-media img' );
		const src = ( img ) => ( img.getAttribute( 'data-src' ) || img.currentSrc || img.src ).split( '?' )[ 0 ];
		const note = content.querySelector( '.cr-journal__notes .pk-note' )?.textContent.trim().replace( /\s+/g, ' ' ) || '';
		return {
			covers: [ ...content.querySelectorAll( 'img' ) ].filter( ( img ) => src( img ) === src( cover ) ).length,
			notes: note ? [ ...content.querySelectorAll( 'p, .pk-note' ) ].filter( ( el ) => el.textContent.trim().replace( /\s+/g, ' ' ) === note ).length : 1,
		};
	} );
	expect( repeats.covers, 'the cover is shown once' ).toBe( 1 );
	expect( repeats.notes, 'the note is shown once' ).toBe( 1 );
	// Skipping the repeats leaves nothing behind: no empty body wrapper, and
	// no empty e-content for a microformats parser to read as the entry's text.
	const husks = await page.evaluate( () => {
		const empty = ( el ) => el.textContent.trim() === '' && ! el.querySelector( 'img, picture, video, audio, iframe, svg, object' );
		const content = document.querySelector( '.single-post__content' );
		return [ ...content.querySelectorAll( '.cr-journal__more, .e-content' ) ].filter( empty ).map( ( el ) => el.className );
	} );
	expect( husks, 'no empty wrapper is left where the repeats were' ).toEqual( [] );
	// The "Read / find it" link's focus ring holds 3:1 against the page: it has the halo.
	const sourceFocus = await page.evaluate( () => {
		const a = document.querySelector( '.cr-journal--comic .pk-sources__link' );
		if ( ! a ) return null;
		a.focus();
		return getComputedStyle( a ).boxShadow;
	} );
	if ( sourceFocus !== null ) {
		expect( sourceFocus ).toMatch( /0px 0px 0px 8px/ );
	}
	const spread = await page.evaluate( () => {
		const b = document.querySelector( 'article.cr-comic--single' ).getBoundingClientRect();
		const h = document.querySelector( 'h1' ).getBoundingClientRect();
		const n = document.querySelector( '.cr-journal__notes, .cr-record' ).getBoundingClientRect();
		return { bagRight: b.right, bagTop: b.top, h1Left: h.left, h1Top: h.top, notesLeft: n.left };
	} );
	// "Also on" (syndication links) follows the record in its column; it is
	// not a stray line at the page edge under the bag.
	const alsoOn = await page.evaluate( () => {
		const s = document.querySelector( '.single-post__content .syndication-links' );
		if ( ! s || s.getClientRects().length === 0 ) return null;
		const r = document.querySelector( '.cr-record' ).getBoundingClientRect();
		const b = s.getBoundingClientRect();
		return { left: b.left, top: b.top, recordLeft: r.left, recordBottom: r.bottom };
	} );
	if ( alsoOn !== null ) {
		expect( Math.abs( alsoOn.left - alsoOn.recordLeft ), 'syndication links start where the record starts' ).toBeLessThanOrEqual( 1 );
		expect( alsoOn.top ).toBeGreaterThan( alsoOn.recordBottom );
	}
	expect( spread.bagRight, 'the bag stands left of the header' ).toBeLessThanOrEqual( spread.h1Left );
	expect( spread.bagRight ).toBeLessThanOrEqual( spread.notesLeft );
	expect( spread.bagTop, 'the bag starts level with the header, not below it' ).toBeLessThan( spread.h1Top + 40 );
} );

// PKIW #228: body text an author writes after the card keeps its place in
// the reading order: under the record and the link, in their column, not
// as a full-width strip under the bag.
test( 'body text after a comic card reads in the details column, under the reading record', async ( { page } ) => {
	test.skip( ! COMIC_TRAILING, 'CR_COMIC_TRAILING_PATH is not set' );
	await page.goto( COMIC_TRAILING, { waitUntil: 'load' } );
	const more = page.locator( '.cr-journal--comic > .cr-journal__more' );
	await expect( more ).toHaveCount( 1 );
	const box = await page.evaluate( () => {
		const r = ( sel ) => document.querySelector( sel ).getBoundingClientRect();
		const m = r( '.cr-journal--comic > .cr-journal__more' );
		const d = r( '.cr-record' );
		const b = r( 'article.cr-comic--single' );
		return { left: m.left, right: m.right, top: m.top, recordLeft: d.left, recordRight: d.right, recordBottom: d.bottom, bagRight: b.right };
	} );
	expect( box.top, 'after the reading record' ).toBeGreaterThan( box.recordBottom );
	expect( Math.abs( box.left - box.recordLeft ), 'starts where the record starts' ).toBeLessThanOrEqual( 1 );
	expect( box.right ).toBeLessThanOrEqual( box.recordRight + 1 );
	// Side by side from 64rem up; stacked below it.
	if ( page.viewportSize().width >= 1024 ) {
		expect( box.left, 'clear of the bag' ).toBeGreaterThanOrEqual( box.bagRight );
	}
} );

// PKIW #228: on the Stream a comic read is one bagged card in the collage:
// cover, kind label, title link over the whole card, creators, rating,
// status and note. No post date, publisher or end date.
test( 'the Stream shows a comic read as one bagged card whose title link covers it', async ( { page } ) => {
	await page.goto( '/stream/', { waitUntil: 'load' } );
	test.skip( ( await page.locator( 'body.cr-stream-page li.kind-comics' ).count() ) === 0, 'no comics post on the first Stream page' );
	const cards = page.locator( 'body.cr-stream-page article.pk-card.k-comics.cr-comic--stream' );
	expect( await cards.count(), 'the first Stream page holds at least one comic read' ).toBeGreaterThan( 0 );
	for ( const card of await cards.all() ) {
		await expect( card.locator( '.pk-kindlabel' ) ).toHaveText( 'Comic' );
		await expect( card.locator( '.pk-title a' ) ).toHaveCount( 1 );
		expect( await card.locator( '.pk-stream-date, .pk-comic-publisher, .pk-meta time' ).count() ).toBe( 0 );
		const hit = await card.evaluate( ( el ) => {
			el.scrollIntoView( { block: 'center', behavior: 'instant' } );
			const box = el.querySelector( '.pk-media' ).getBoundingClientRect();
			const at = document.elementFromPoint( box.left + box.width / 2, box.top + box.height / 2 );
			return at?.closest( 'a' ) === el.querySelector( '.pk-title a' );
		} );
		expect( hit, 'the cover is part of the title link' ).toBe( true );
	}
	const columns = await page.locator( 'body.cr-stream-page .wp-block-post-template' ).first().evaluate( ( el ) => getComputedStyle( el ).columnWidth );
	expect( columns, 'the Stream stays a multi-column collage' ).not.toBe( 'auto' );
} );

// PKIW #229: the recipe archive is a ring binder of recipe cards, four to an
// archive page. A card is its picture, its h2 title link and the course and
// time the recipe plugin holds; the badge, kind label, date, excerpt and
// "Read more" stay off the archive. The course tabs and the A-Z tab are
// links that filter or reorder the same archive, and the pager keeps that
// state because it lives in the URL.
test( 'the recipe archive is a binder of four recipe cards, one title link each, with course tabs that filter it', async ( { page } ) => {
	await page.goto( RECIPE_ARCHIVE, { waitUntil: 'load' } );
	test.skip( ( await page.locator( 'main li.wp-block-post' ).count() ) === 0, 'no recipe posts on this site' );
	await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
	const list = page.locator( '.cr-archive--recipes ul.cr-recipe-binder__list' );
	await expect( list ).toHaveCount( 1 );
	const count = await list.locator( ':scope > li' ).count();
	expect( count, 'four recipes to an archive page' ).toBeGreaterThan( 0 );
	expect( count, 'four recipes to an archive page' ).toBeLessThanOrEqual( 4 );
	const cards = list.locator( 'article.pk-card.cr-recipe-card' );
	expect( await cards.count(), 'one card per query item, no filler' ).toBe( count );
	for ( const card of await cards.all() ) {
		const rendered = await card.evaluate( ( el ) => [ ...el.querySelectorAll( 'a' ) ].filter( ( a ) => a.getClientRects().length > 0 ).length );
		expect( rendered, 'a recipe card is one link' ).toBe( 1 );
		expect( await card.locator( '.pk-title:not(h2)' ).count(), 'every card title is an h2 under the archive h1' ).toBe( 0 );
		expect( await card.locator( '.pk-badge, .pk-kindlabel, .pk-stream-date, .pk-excerpt, .pk-meta' ).count(), 'no badge, kind label, date, excerpt or Read more on the archive' ).toBe( 0 );
		const covered = await card.evaluate( ( el ) => {
			el.scrollIntoView( { block: 'center', behavior: 'instant' } );
			const r = el.getBoundingClientRect();
			const a = el.querySelector( '.pk-title a' );
			const hit = document.elementFromPoint( r.left + r.width / 2, r.top + r.height - 12 );
			return hit === a || a.contains( hit );
		} );
		expect( covered, 'the title link covers the whole card' ).toBe( true );
	}
	// A card with no picture is text, not a broken image.
	expect( await list.locator( 'img' ).evaluateAll( ( imgs ) => imgs.filter( ( i ) => i.complete && i.naturalWidth === 0 ).length ) ).toBe( 0 );

	const shape = await list.evaluate( ( ul ) => ( { cols: getComputedStyle( ul ).gridTemplateColumns.split( ' ' ).length, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth } ) );
	expect( shape.cols, 'two binder pages side by side on a wide screen, one on a phone' ).toBe( page.viewportSize().width >= 1024 ? 2 : 1 );
	expect( shape.overflow ).toBe( 0 );

	// Tabs: every tab is a link to the same archive; one is current.
	const tabs = page.locator( '.cr-archive--recipes nav.cr-recipe-tabs' );
	await expect( tabs ).toHaveCount( 1 );
	expect( ( await tabs.getAttribute( 'aria-label' ) || '' ).trim() ).not.toBe( '' );
	await expect( tabs.locator( 'a[aria-current]' ) ).toHaveCount( 1 );
	const archivePath = new URL( page.url() ).pathname;
	for ( const href of await tabs.locator( 'a' ).evaluateAll( ( as ) => as.map( ( a ) => a.href ) ) ) {
		expect( new URL( href ).pathname, 'a tab stays on the recipe archive' ).toBe( archivePath );
	}

	// A course tab filters the archive, and the pager keeps the filter.
	const courseTab = tabs.locator( 'a[href*="pkiw_recipe_course="]' ).first();
	const course = ( await courseTab.textContent() ).trim();
	await courseTab.click();
	await page.waitForLoadState( 'load' );
	expect( new URL( page.url() ).searchParams.get( 'pkiw_recipe_course' ) ).toBeTruthy();
	await expect( page.locator( '.cr-archive--recipes nav.cr-recipe-tabs a[aria-current]' ) ).toHaveText( course );
	const filtered = page.locator( '.cr-archive--recipes ul.cr-recipe-binder__list article.cr-recipe-card' );
	expect( await filtered.count() ).toBeGreaterThan( 0 );
	for ( const text of await filtered.locator( '.pk-recipe-course' ).allTextContents() ) {
		expect( text.split( ',' ).map( ( t ) => t.trim() ), `every card on the ${ course } tab is filed under it` ).toContain( course );
	}
	expect( await filtered.locator( '.pk-recipe-course' ).count(), 'and every card names its course' ).toBe( await filtered.count() );
	const param = new URL( page.url() ).searchParams.get( 'pkiw_recipe_course' );
	for ( const href of await page.locator( '.cr-archive--recipes .wp-block-query-pagination a' ).evaluateAll( ( as ) => as.map( ( a ) => a.href ) ) ) {
		expect( new URL( href ).searchParams.get( 'pkiw_recipe_course' ), 'pager links keep the course' ).toBe( param );
	}

	// The A-Z tab is the same archive in title order, and the pager keeps the order.
	await page.locator( '.cr-archive--recipes nav.cr-recipe-tabs a[href*="orderby=title"]' ).click();
	await page.waitForLoadState( 'load' );
	const titles = await page.locator( '.cr-archive--recipes ul.cr-recipe-binder__list .pk-title a' ).allTextContents();
	expect( titles.map( ( t ) => t.trim() ) ).toEqual( [ ...titles ].map( ( t ) => t.trim() ).sort( ( a, b ) => a.localeCompare( b, 'en', { sensitivity: 'base' } ) ) );
	const pager = await page.locator( '.cr-archive--recipes .wp-block-query-pagination a' ).evaluateAll( ( as ) => as.map( ( a ) => a.href ) );
	expect( pager.length, 'the fixture archive runs past one page of four' ).toBeGreaterThan( 0 );
	for ( const href of pager ) {
		expect( new URL( href ).searchParams.get( 'orderby' ), 'pager links keep the title order' ).toBe( 'title' );
	}
} );

// PKIW #229: a recipe single is one binder page. The post title is the page
// heading and the featured image is the picture; the recipe plugin's card
// sits inside the page without repeating either. The plugin's own print
// link, section links and structured data stay as it renders them.
test( 'a recipe single is one binder page: title and picture once, the recipe plugin\'s card inside it, print and section links intact', async ( { page } ) => {
	const response = await page.goto( RECIPE_SINGLE, { waitUntil: 'load' } );
	test.skip( response.status() === 404, 'no recipe fixture on this site' );
	await expect( page.locator( 'body.cr-recipe-single' ) ).toHaveCount( 1 );
	const h1 = page.locator( 'main h1' );
	await expect( h1 ).toHaveCount( 1 );
	const title = ( await h1.textContent() ).trim();
	const recipe = page.locator( 'main .wprm-recipe.wprm-recipe-template-cr-binder' );
	await expect( recipe ).toHaveCount( 1 );

	// The name and the picture each show once: the post's own.
	expect( await recipe.locator( '.wprm-recipe-name, .wprm-recipe-image' ).count(), 'the recipe card repeats neither the name nor the picture' ).toBe( 0 );
	await expect( page.locator( 'main figure.single-post__featured img' ) ).toHaveCount( 1 );
	const named = await page.locator( 'main .single-post__header :is(h1, h2, h3, h4), main .single-post__content :is(h1, h2, h3, h4)' ).evaluateAll( ( hs, t ) => hs.filter( ( h ) => h.textContent.trim() === t ).length, title );
	expect( named, 'one heading carries the recipe name' ).toBe( 1 );

	// The recipe's sections are h2 under the page's h1.
	const sections = await recipe.locator( 'h2, h3, h4' ).evaluateAll( ( hs ) => hs.filter( ( h ) => h.getClientRects().length > 0 ).map( ( h ) => h.tagName + ' ' + h.textContent.trim() ) );
	expect( sections ).toEqual( expect.arrayContaining( [ 'H2 Ingredients', 'H2 Equipment', 'H2 Instructions' ] ) );

	// Summary and the plugin's print link sit under the title.
	await expect( page.locator( 'main .single-post__header .cr-recipe__summary' ) ).toHaveText( /\S/ );
	const print = page.locator( 'main .single-post__header a.wprm-recipe-print' );
	await expect( print ).toHaveCount( 1 );
	expect( await print.getAttribute( 'href' ) ).toContain( '/wprm_print/' );
	expect( await recipe.locator( 'a.wprm-recipe-print' ).count(), 'and the card does not print a second one' ).toBe( 0 );

	// Times, servings and course are text.
	const facts = ( await recipe.locator( '.cr-binder-recipe__facts' ).textContent() ).replace( /\s+/g, ' ' );
	for ( const label of [ 'Prep Time', 'Cook Time', 'Total Time', 'Servings', 'Course' ] ) {
		expect( facts ).toContain( label );
	}

	// Section links go to sections that exist.
	const jumps = await recipe.locator( 'a.wprm-recipe-jump-to-section' ).evaluateAll( ( as ) => as.map( ( a ) => a.getAttribute( 'href' ) ) );
	expect( jumps.length ).toBeGreaterThanOrEqual( 3 );
	for ( const href of jumps ) {
		expect( href.startsWith( '#' ), `${ href } is an in-page link` ).toBe( true );
		expect( await page.locator( `[id="${ href.slice( 1 ) }"]` ).count(), `${ href } has a target` ).toBe( 1 );
	}

	// The plugin's structured data is still there, once.
	expect( await page.locator( 'script[type="application/ld+json"]' ).evaluateAll( ( ss ) => ss.filter( ( s ) => s.textContent.includes( '"Recipe"' ) ).length ) ).toBe( 1 );

	const box = await page.evaluate( () => {
		const f = document.querySelector( 'main figure.single-post__featured' ).getBoundingClientRect();
		const h = document.querySelector( 'main h1' ).getBoundingClientRect();
		return { figureRight: f.right, figureTop: f.top, h1Left: h.left, h1Bottom: h.bottom, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
	} );
	if ( page.viewportSize().width >= 1024 ) {
		expect( box.figureRight, 'the picture stands left of the title' ).toBeLessThanOrEqual( box.h1Left );
	} else {
		expect( box.figureTop, 'on a phone the picture follows the title' ).toBeGreaterThanOrEqual( box.h1Bottom - 1 );
	}
	expect( box.overflow ).toBe( 0 );
	await expect( page.locator( 'main nav.cr-post-nav' ) ).toHaveCount( 1 );
} );

test( 'a recipe post with no recipe card keeps the default single', async ( { page } ) => {
	const response = await page.goto( RECIPE_PLAIN_SINGLE, { waitUntil: 'load' } );
	test.skip( response.status() === 404, 'no recipe fixture on this site' );
	await expect( page.locator( 'body.cr-recipe-single' ) ).toHaveCount( 0 );
	await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
	await expect( page.locator( 'main .wprm-recipe' ) ).toHaveCount( 0 );
	expect( await page.locator( 'main .single-post__content li' ).count(), 'its own lists render' ).toBeGreaterThan( 0 );
	expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBe( 0 );
} );

// PKIW #229: on the Stream a recipe is a 3x5 card: the picture, the kind
// label, the title link, and the course and time the recipe holds. The
// date and the excerpt stay off the card. The Stream itself is unchanged.
test( 'the Stream shows a recipe as a 3x5 card: picture, label, title link, course and time', async ( { page } ) => {
	await page.goto( RECIPE_STREAM, { waitUntil: 'load' } );
	const items = page.locator( 'main li.kind-recipe' );
	test.skip( ( await items.count() ) === 0, 'no recipe on this Stream' );
	const cards = items.locator( 'article.pk-card.cr-recipe-stream' );
	expect( await cards.count(), 'every recipe on the Stream is a recipe card' ).toBe( await items.count() );
	for ( const card of await cards.all() ) {
		expect( await card.locator( '.pk-stream-date, .pk-excerpt, .pk-badge' ).count(), 'no date, excerpt or badge on the card' ).toBe( 0 );
		await expect( card.locator( '.pk-kindlabel' ) ).toHaveText( /\S/ );
		await expect( card.locator( 'h2.pk-title a' ) ).toHaveCount( 1 );
		expect( await card.evaluate( ( el ) => getComputedStyle( el.querySelector( '.pk-title' ) ).transform ), 'the title is upright' ).toBe( 'none' );
	}
	// A recipe with a picture and stored facts: picture beside the text, course and time as text.
	const full = cards.filter( { has: page.locator( '.pk-recipe-facts' ) } ).filter( { has: page.locator( '.pk-media img' ) } ).first();
	await expect( full ).toHaveCount( 1 );
	await expect( full.locator( '.pk-recipe-course' ) ).toHaveText( /\S/ );
	await expect( full.locator( '.pk-recipe-time' ) ).toHaveText( /\d/ );
	const beside = await full.evaluate( ( el ) => {
		el.scrollIntoView( { block: 'center', behavior: 'instant' } );
		const img = el.querySelector( '.pk-media img' ).getBoundingClientRect();
		const title = el.querySelector( '.pk-title' ).getBoundingClientRect();
		return { imgRight: img.right, titleLeft: title.left, width: el.getBoundingClientRect().width };
	} );
	expect( beside.imgRight, 'the picture stands left of the title' ).toBeLessThanOrEqual( beside.titleLeft );
	expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBe( 0 );
} );

// Paper has no use for site navigation. In print the header, the footer, the
// Browse all lists, the pager, Previous/Next and the skip link are left out
// on every template; the page's own heading and content stay. On screen they
// are all still there.
for ( const [ label, path ] of [ [ 'a kind archive', LISTEN_ARCHIVE ], [ 'a single post', COMIC_SINGLE ] ] ) {
	test( `print leaves the site chrome out of ${ label } and keeps its content`, async ( { page } ) => {
		await page.goto( path, { waitUntil: 'load' } );
		const chrome = [ 'header.wp-block-template-part', 'footer.wp-block-template-part', '.cr-browse-all', 'nav.wp-block-query-pagination', '.cr-post-nav', '.skip-link' ];
		const displayed = () => page.evaluate( ( selectors ) => Object.fromEntries( selectors.map( ( selector ) => {
			const el = document.querySelector( selector );
			return [ selector, el ? getComputedStyle( el ).display : 'absent' ];
		} ) ), chrome );

		const onScreen = await displayed();
		expect( onScreen[ 'header.wp-block-template-part' ], 'the header shows on screen' ).not.toBe( 'none' );
		expect( onScreen[ 'footer.wp-block-template-part' ], 'the footer shows on screen' ).not.toBe( 'none' );

		await page.emulateMedia( { media: 'print' } );
		const inPrint = await displayed();
		const present = Object.entries( inPrint ).filter( ( [ , display ] ) => 'absent' !== display );
		expect( present.length, 'the page has chrome to leave out' ).toBeGreaterThanOrEqual( 3 );
		for ( const [ selector, display ] of present ) {
			expect( display, `${ selector } is left out of print` ).toBe( 'none' );
		}
		await expect( page.locator( 'main h1' ).first() ).toBeVisible();
		expect( await page.locator( 'main' ).evaluate( ( el ) => getComputedStyle( el ).display ) ).not.toBe( 'none' );
	} );
}

// PKIW #230: the eat and drink archives are menus. Recent Specials sits above
// the menu on the first page. The menu is the Query Loop's own posts, held in
// one section per cuisine or drink type: a heading, then that group's lines
// (name link in a heading, a leader hidden from assistive technology, the
// rating as text). A line never leaves its section, so no group runs into
// another column without its heading. The torn paper, the panels, the seams
// and the marks in the margins are CSS. No street, coordinates or venue link
// prints anywhere on the page.
for ( const [ kind, path, perPage, wideColumns ] of [ [ 'eat', EAT_ARCHIVE, 6, 3 ], [ 'drink', DRINK_ARCHIVE, 8, 3 ] ] ) {
	test( `the ${ kind } archive is a menu: Recent Specials, then a section of lines per group`, async ( { page } ) => {
		await page.goto( path, { waitUntil: 'load' } );
		test.skip( ( await page.locator( 'main li.wp-block-post' ).count() ) < 3, `fewer than three ${ kind } posts on this site` );
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
		const menu = page.locator( `.cr-archive--${ kind } .cr-menu__list` );
		await expect( menu ).toHaveCount( 1 );
		const lines = menu.locator( 'li.wp-block-post' );
		const count = await lines.count();
		expect( count ).toBeGreaterThan( 0 );
		expect( count, `${ perPage } lines to a page` ).toBeLessThanOrEqual( perPage );
		for ( const line of await lines.all() ) {
			await expect( line.locator( 'h3.pkiw-menu-entry__title > a.pkiw-menu-entry__name' ), 'a line is named by a linked h3' ).toHaveCount( 1 );
			// The plugin names the entry's author in a hidden h-card link; a visitor meets one link.
			const rendered = await line.evaluate( ( el ) => [ ...el.querySelectorAll( 'a' ) ].filter( ( a ) => a.getClientRects().length > 0 ).length );
			expect( rendered, 'a menu line is one link' ).toBe( 1 );
			expect( await line.locator( 'a[hidden], [hidden] a' ).evaluateAll( ( as ) => as.filter( ( a ) => a.getClientRects().length > 0 ).length ), 'hidden links stay hidden' ).toBe( 0 );
			expect( await line.locator( '.pkiw-menu-entry__leader' ).getAttribute( 'aria-hidden' ) ).toBe( 'true' );
			const rating = line.locator( '.pkiw-menu-entry__rating' );
			if ( await rating.count() ) {
				expect( ( await rating.textContent() ).trim() ).toMatch( /^Rated [1-5] of 5$/ );
			}
		}

		// Real section containers: every line sits in exactly one, under that section's heading,
		// and its box stays inside the section's box at every width.
		const sections = await menu.locator( ':scope > section.pkiw-menu-section' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => {
			const box = node.getBoundingClientRect();
			const heading = node.querySelector( ':scope > h2.pkiw-menu-section__heading' );
			const items = [ ...node.querySelectorAll( ':scope > ul.pkiw-menu-section__items > li.wp-block-post' ) ];
			return {
				heading: heading ? heading.textContent.trim() : '',
				headingFirst: heading === node.firstElementChild,
				lines: items.length,
				inside: items.every( ( li ) => {
					const r = li.getBoundingClientRect();
					return r.left >= box.left - 1 && r.right <= box.right + 1 && r.top >= heading.getBoundingClientRect().bottom - 1 && r.bottom <= box.bottom + 1;
				} ),
				left: Math.round( box.left ),
				top: Math.round( box.top ),
				width: Math.round( box.width ),
				bottom: Math.round( box.bottom ),
				// Layout position: each strip has its own slight tilt, which moves its painted corners but not its line.
				labelTop: heading ? heading.offsetTop : 0,
				count: node.dataset.pkiwSections,
				boxes: node.getClientRects().length,
			};
		} ) );
		expect( sections.length, 'the menu has sections' ).toBeGreaterThan( 0 );
		expect( await menu.locator( ':scope > *' ).count(), 'the menu holds sections and nothing else' ).toBe( sections.length );
		expect( sections.reduce( ( sum, section ) => sum + section.lines, 0 ), 'every line is in a section' ).toBe( count );
		for ( const section of sections ) {
			expect( section.heading, 'a section is headed' ).not.toBe( '' );
			expect( section.headingFirst, `${ section.heading }: the heading opens the section` ).toBe( true );
			expect( section.lines, `${ section.heading }: a section holds at least one line` ).toBeGreaterThan( 0 );
			expect( section.inside, `${ section.heading }: its lines stay under its heading, in its own panel` ).toBe( true );
			expect( section.heading, 'no invented slogan stands in for a group' ).not.toMatch( /good food|sample content/i );
		}
		expect( new Set( sections.map( ( section ) => section.heading ) ).size, 'a section is headed once on a page' ).toBe( sections.length );

		// Outline: the archive title, then Recent Specials and each section at h2, their items at h3.
		const outline = await page.evaluate( () => [ ...document.querySelectorAll( 'h1, .cr-menu h2, .cr-menu h3, .cr-menu h4, .cr-menu h5, .cr-menu h6' ) ].map( ( node ) => ( {
			tag: node.tagName,
			text: node.textContent.trim(),
			item: node.matches( '.pkiw-menu-entry__title, .pkiw-menu-specials__name' ),
			group: node.matches( '.pkiw-menu-section__heading, .pkiw-menu-specials__heading' ),
		} ) ) );
		expect( outline[ 0 ].tag, 'the archive title leads the outline' ).toBe( 'H1' );
		expect( outline.filter( ( heading ) => 'H1' === heading.tag ).length ).toBe( 1 );
		const inMenu = outline.slice( 1 );
		expect( inMenu.length ).toBeGreaterThan( 0 );
		expect( inMenu[ 0 ].tag, 'the menu opens with an h2' ).toBe( 'H2' );
		for ( const heading of inMenu ) {
			expect( heading.group || heading.item, `"${ heading.text }" is a group or an item heading` ).toBe( true );
			expect( heading.tag, `"${ heading.text }": groups are h2 and items h3` ).toBe( heading.group ? 'H2' : 'H3' );
		}

		const specials = page.locator( `.cr-archive--${ kind } section.pkiw-menu-specials` );
		await expect( specials ).toHaveCount( 1 );
		await expect( page.locator( `[id="${ await specials.getAttribute( 'aria-labelledby' ) }"]` ) ).toHaveText( 'Recent Specials' );
		const specialCount = await specials.locator( 'ul > li' ).count();
		expect( specialCount ).toBeGreaterThan( 0 );
		expect( specialCount, 'two specials at most' ).toBeLessThanOrEqual( 2 );
		expect( await specials.locator( 'ul > li h3 a' ).count(), 'each special is named by a linked heading' ).toBe( specialCount );

		const shape = await page.evaluate( ( k ) => {
			const list = document.querySelector( `.cr-archive--${ k } .cr-menu__list` );
			const box = document.querySelector( `.cr-archive--${ k } section.pkiw-menu-specials` );
			const wrap = document.querySelector( `.cr-archive--${ k } .cr-menu` );
			const cs = getComputedStyle( list );
			const sheet = getComputedStyle( list, '::before' );
			return {
				columns: cs.gridTemplateColumns.split( ' ' ).length,
				paper: sheet.backgroundColor,
				torn: sheet.clipPath,
				marks: [ '::before', '::after' ].map( ( pseudo ) => getComputedStyle( wrap, pseudo ).content ),
				decorated: wrap.querySelectorAll( 'svg, img:not(.pkiw-menu-specials__photo)' ).length,
				frame: parseFloat( getComputedStyle( box ).borderTopWidth ),
				sheetBottom: Math.round( list.getBoundingClientRect().bottom ),
				pagerNext: ( () => {
					let next = list.nextElementSibling;
					while ( next && 0 === next.getClientRects().length ) {
						next = next.nextElementSibling;
					}
					return ! next || next.matches( 'nav.wp-block-query-pagination' );
				} )(),
				specials: box.querySelectorAll( '.pkiw-menu-specials__item' ).length,
				photos: box.querySelectorAll( '.pkiw-menu-specials__item:not(.pkiw-menu-specials__item--no-photo) img.pkiw-menu-specials__photo, .pkiw-menu-specials__item:not(.pkiw-menu-specials__item--no-photo) img.wp-post-image' ).length,
				fallback: [ ...box.querySelectorAll( '.pkiw-menu-specials__item--no-photo' ) ].map( ( item ) => getComputedStyle( item, '::before' ).backgroundImage ),
				specialsFirst: box.getBoundingClientRect().top < list.getBoundingClientRect().top,
				overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
				located: document.querySelectorAll( 'main .p-street-address, main .p-latitude, main .p-longitude, main .h-geo, main .pkiw-menu-entry a[href*="openstreetmap"]' ).length,
			};
		}, kind );
		expect( shape.specialsFirst, 'Recent Specials sits above the menu' ).toBe( true );
		// Columns: one on a phone; on a wide screen as many equal columns as the page has
		// sections, up to three, every label on one line and the sheet as tall as its content.
		const wide = page.viewportSize().width >= 1024;
		const lefts = new Set( sections.map( ( section ) => section.left ) );
		for ( const section of sections ) {
			expect( section.count, 'each section says how many the page holds' ).toBe( String( sections.length ) );
			expect( section.boxes, `${ section.heading } is one box: never split between columns` ).toBe( 1 );
		}
		if ( wide ) {
			expect( new Set( sections.filter( ( section ) => section.top === sections[ 0 ].top ).map( ( section ) => section.left ) ).size, 'as many columns as sections, up to three' ).toBe( Math.min( wideColumns, sections.length ) );
			const rows = [ ...sections ].sort( ( a, b ) => ( Math.abs( a.top - b.top ) > 8 ? a.top - b.top : a.left - b.left ) );
			expect( rows.map( ( section ) => section.heading ), 'the rows read in DOM order' ).toEqual( sections.map( ( section ) => section.heading ) );
			if ( sections.length <= wideColumns ) {
				expect( new Set( sections.map( ( section ) => section.labelTop ) ).size, 'every label on the same top edge' ).toBe( 1 );
				expect( new Set( sections.map( ( section ) => section.top ) ).size, 'one row of panels' ).toBe( 1 );
				expect( Math.max( ...sections.map( ( section ) => section.width ) ) - Math.min( ...sections.map( ( section ) => section.width ) ), 'equal columns' ).toBeLessThanOrEqual( 2 );
				expect( sections.map( ( section ) => section.left ), 'reading order runs left to right' ).toEqual( [ ...sections.map( ( section ) => section.left ) ].sort( ( a, b ) => a - b ) );
			}
		} else {
			expect( lefts.size, 'one column on a phone' ).toBe( 1 );
		}
		expect( shape.sheetBottom - Math.max( ...sections.map( ( section ) => section.bottom ) ), 'the sheet is as tall as its content: no empty row' ).toBeLessThanOrEqual( 4 );
		expect( shape.pagerNext, 'pagination comes right after the sheet' ).toBe( true );
		expect( shape.fallback.every( ( plate ) => plate.includes( 'radial-gradient' ) ), 'a special with no featured image draws the plate' ).toBe( true );
		expect( shape.photos + shape.fallback.length, 'every special has a featured image or the plate' ).toBe( shape.specials );
		expect( shape.paper, 'the menu is on paper' ).not.toBe( 'rgba(0, 0, 0, 0)' );
		expect( shape.torn, 'the sheet is torn, not ruled' ).toContain( 'polygon' );
		// Marks in the margins are empty pseudo-elements: nothing for assistive technology, nothing in the DOM.
		expect( shape.marks.every( ( content ) => 'none' === content || '""' === content ), 'margin marks carry no content' ).toBe( true );
		expect( shape.decorated, 'no decorative image or SVG element in the menu' ).toBe( 0 );
		expect( shape.frame, 'Recent Specials is framed' ).toBeGreaterThanOrEqual( 2 );
		expect( shape.overflow ).toBe( 0 );
		expect( shape.located, 'no street, coordinates or map link on the menu' ).toBe( 0 );

		// Tablet width: two columns read in rows, so the eye meets the sections in the order
		// the keyboard and a screen reader do. A last section with no partner takes the row.
		if ( wide ) {
			await page.setViewportSize( { width: 800, height: 900 } );
			await page.waitForTimeout( 150 );
			const tablet = await menu.locator( ':scope > section.pkiw-menu-section' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => {
				const box = node.getBoundingClientRect();
				return { heading: node.querySelector( 'h2' ).textContent.trim(), left: Math.round( box.left ), top: Math.round( box.top ), width: Math.round( box.width ), boxes: node.getClientRects().length };
			} ) );
			const sheet = Math.round( ( await menu.boundingBox() ).width );
			const rows = [ ...tablet ].sort( ( a, b ) => ( Math.abs( a.top - b.top ) > 8 ? a.top - b.top : a.left - b.left ) );
			expect( rows.map( ( section ) => section.heading ), 'at tablet width the rows read in DOM order' ).toEqual( tablet.map( ( section ) => section.heading ) );
			expect( new Set( tablet.slice( 0, 2 ).map( ( section ) => section.left ) ).size, 'two columns at tablet width' ).toBe( Math.min( 2, tablet.length ) );
			if ( tablet.length > 1 ) {
				expect( tablet[ 0 ].top, 'the first two sections share a row' ).toBe( tablet[ 1 ].top );
				expect( Math.abs( tablet[ 0 ].width - tablet[ 1 ].width ), 'in equal columns' ).toBeLessThanOrEqual( 2 );
			}
			if ( 1 === tablet.length % 2 ) {
				expect( sheet - tablet[ tablet.length - 1 ].width, 'an unmatched last section spans both columns' ).toBeLessThanOrEqual( 2 );
			}
			expect( tablet.every( ( section ) => 1 === section.boxes ), 'no section split between columns' ).toBe( true );
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBe( 0 );
			await page.setViewportSize( { width: 1280, height: 900 } );
		}

		// The site's shared pager, and no specials past the first page.
		const next = page.locator( `.cr-archive--${ kind } nav.wp-block-query-pagination a.wp-block-query-pagination-next` );
		if ( await next.count() ) {
			await expect( page.locator( `.cr-archive--${ kind } nav.wp-block-query-pagination` ) ).not.toHaveClass( /cr-stream__pagination/ );
			await next.click();
			await page.waitForLoadState( 'load' );
			await expect( page.locator( `.cr-archive--${ kind } section.pkiw-menu-specials` ) ).toHaveCount( 0 );
			const second = page.locator( `.cr-archive--${ kind } .cr-menu__list > section.pkiw-menu-section` );
			expect( await second.count() ).toBeGreaterThan( 0 );
			await expect( second.first().locator( ':scope > h2.pkiw-menu-section__heading' ), 'page two opens with a headed section' ).toHaveCount( 1 );
		}
	} );
}

// PKIW #230: in one column a menu line is the name with its rating beneath,
// so a long name wraps without leaving a stub of leader or squeezing the
// rating. In two or three columns the dotted leader runs from name to rating.
for ( const [ kind, path ] of [ [ 'eat', EAT_ARCHIVE ], [ 'drink', DRINK_ARCHIVE ] ] ) {
	test( `a ${ kind } menu line puts its rating under the name on a phone and after a leader from tablet width`, async ( { page } ) => {
		const measure = () => page.locator( `.cr-archive--${ kind } .cr-menu__list .pkiw-menu-entry` ).evaluateAll( ( entries ) => entries.filter( ( entry ) => entry.querySelector( '.pkiw-menu-entry__rating' ) ).map( ( entry ) => {
			const name = entry.querySelector( '.pkiw-menu-entry__name' ).getBoundingClientRect();
			const rating = entry.querySelector( '.pkiw-menu-entry__rating' );
			const rated = rating.getBoundingClientRect();
			return {
				name: entry.querySelector( '.pkiw-menu-entry__name' ).textContent.trim(),
				href: entry.querySelector( '.pkiw-menu-entry__name' ).getAttribute( 'href' ),
				text: rating.textContent.trim(),
				leader: entry.querySelector( '.pkiw-menu-entry__leader' ).getClientRects().length,
				leaderWidth: Math.round( entry.querySelector( '.pkiw-menu-entry__leader' ).getBoundingClientRect().width ),
				under: rated.top >= name.bottom - 1,
				flush: Math.abs( rated.left - name.left ) <= 2,
				after: rated.left >= name.right && rated.top < name.bottom,
				inside: rated.right <= entry.getBoundingClientRect().right + 1,
			};
		} ) );
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( path, { waitUntil: 'load' } );
		test.skip( ( await page.locator( 'main li.wp-block-post' ).count() ) < 3, `fewer than three ${ kind } posts on this site` );
		const desktop = await measure();
		expect( desktop.length, 'the page has rated lines' ).toBeGreaterThan( 0 );
		for ( const width of [ 1280, 800 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await page.waitForTimeout( 150 );
			for ( const line of await measure() ) {
				expect( line.leader, `${ line.name } at ${ width }px keeps its leader` ).toBe( 1 );
				expect( line.after, `${ line.name } at ${ width }px: the rating follows the name on its line` ).toBe( true );
			}
		}
		for ( const width of [ 375, 320 ] ) {
			await page.setViewportSize( { width, height: 800 } );
			await page.waitForTimeout( 150 );
			const lines = await measure();
			for ( const [ index, line ] of lines.entries() ) {
				expect( line.leader, `${ line.name } at ${ width }px prints no leader` ).toBe( 0 );
				expect( line.under, `${ line.name } at ${ width }px: the rating is on its own line under the name` ).toBe( true );
				expect( line.flush, `${ line.name } at ${ width }px: the rating starts where the name does` ).toBe( true );
				expect( line.inside, `${ line.name } at ${ width }px: the rating stays in the line` ).toBe( true );
				expect( line.text, 'the rating reads the same at every width' ).toBe( desktop[ index ].text );
				expect( line.href, 'the name links to the same post at every width' ).toBe( desktop[ index ].href );
			}
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ), `no sideways scroll at ${ width }px` ).toBe( 0 );
		}
	} );

	// A phone gets each Recent Special as a row: a small picture, the copy beside it.
	// The featured image when the post has one, the plate when it has none. The whole
	// row is the link's target. In forced colours the plate is gone and leaves no frame.
	test( `Recent Specials on the ${ kind } menu are compact rows on a phone and leave no empty frame in forced colours`, async ( { page } ) => {
		const measure = () => page.locator( `.cr-archive--${ kind } .pkiw-menu-specials__item` ).evaluateAll( ( items ) => items.map( ( item ) => {
			const box = item.getBoundingClientRect();
			const body = item.querySelector( '.pkiw-menu-specials__body' ).getBoundingClientRect();
			const img = item.querySelector( 'img' );
			const plate = getComputedStyle( item, '::before' );
			const link = item.querySelector( '.pkiw-menu-specials__link' );
			const picture = img ? img.getBoundingClientRect() : null;
			// Where the item's own content starts: past the rule and gap between two specials side by side.
			const start = box.left + parseFloat( getComputedStyle( item ).paddingLeft ) + parseFloat( getComputedStyle( item ).borderLeftWidth );
			// The point a thumb lands on: the middle of the picture column.
			const tapped = document.elementFromPoint( start + Math.max( 2, ( body.left - start ) / 2 ), box.top + Math.min( box.height, 80 ) / 2 );
			return {
				name: link.textContent.trim(),
				plain: item.classList.contains( 'pkiw-menu-specials__item--no-photo' ),
				image: img ? { width: Math.round( picture.width ), height: Math.round( picture.height ), loaded: img.complete && img.naturalWidth > 0, alt: img.getAttribute( 'alt' ) } : null,
				plate: 'none' === plate.content ? null : { width: parseFloat( plate.width ), height: parseFloat( plate.height ), border: parseFloat( plate.borderTopWidth ), drawn: plate.backgroundImage.includes( 'radial-gradient' ) },
				pictureColumn: Math.round( body.left - start ),
				bodyWidth: Math.round( body.width ),
				height: Math.round( box.height ),
				bodyHeight: Math.round( body.height ),
				tapReachesLink: Boolean( tapped && ( tapped === link || link.contains( tapped ) ) ),
				font: parseFloat( getComputedStyle( item.querySelector( '.pkiw-menu-specials__body' ) ).fontSize ),
			};
		} ) );
		await page.goto( path, { waitUntil: 'load' } );
		test.skip( ( await page.locator( 'main li.wp-block-post' ).count() ) < 3, `fewer than three ${ kind } posts on this site` );
		for ( const width of [ 375, 320 ] ) {
			await page.setViewportSize( { width, height: 800 } );
			await page.waitForTimeout( 150 );
			const specials = await measure();
			expect( specials.length, 'both specials stay' ).toBeGreaterThan( 0 );
			for ( const special of specials ) {
				const picture = special.image || special.plate;
				expect( picture, `${ special.name }: a featured image or the plate` ).not.toBeNull();
				expect( Boolean( special.image ), `${ special.name }: a featured image wins over the plate` ).toBe( ! special.plain );
				if ( special.image ) {
					expect( special.image.loaded, `${ special.name }: its featured image loaded` ).toBe( true );
					expect( special.plate, `${ special.name }: no plate beside a featured image` ).toBeNull();
				}
				expect( picture.height, `${ special.name } at ${ width }px: a small picture, not a banner` ).toBeLessThanOrEqual( 96 );
				expect( special.pictureColumn, `${ special.name } at ${ width }px: the copy sits beside the picture` ).toBeGreaterThanOrEqual( Math.floor( picture.width ) );
				expect( special.height, `${ special.name } at ${ width }px: the row is as tall as its copy or its picture` ).toBeLessThanOrEqual( Math.max( special.bodyHeight, Math.ceil( picture.height ) ) + 2 );
				expect( special.bodyWidth, `${ special.name } at ${ width }px: the copy keeps a readable measure` ).toBeGreaterThanOrEqual( 140 );
				expect( special.font, 'and a readable size' ).toBeGreaterThanOrEqual( 13 );
				expect( special.tapReachesLink, `${ special.name } at ${ width }px: a tap on the picture opens the post` ).toBe( true );
			}
			expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ), `no sideways scroll at ${ width }px` ).toBe( 0 );
		}

		// A site that serves modern image formats wraps the image in a <picture> with
		// `display: contents` and a <source> before it (seen on Pantheon dev). Both of
		// its children then become grid items. The special is still a row.
		const wrapped = await page.locator( `.cr-archive--${ kind } .pkiw-menu-specials__item > img.pkiw-menu-specials__photo` ).evaluateAll( ( images ) => images.map( ( img ) => {
			const picture = document.createElement( 'picture' );
			picture.style.display = 'contents';
			const source = document.createElement( 'source' );
			source.type = 'image/x-not-served';
			source.srcset = img.currentSrc;
			img.before( picture );
			picture.append( source, img );
			return true;
		} ).length );
		if ( wrapped ) {
			for ( const special of ( await measure() ).filter( ( item ) => item.image ) ) {
				expect( special.image.height, `${ special.name }: wrapped in a picture element, still a small picture` ).toBeLessThanOrEqual( 96 );
				expect( special.pictureColumn, `${ special.name }: wrapped in a picture element, the copy still sits beside it` ).toBeGreaterThanOrEqual( special.image.width );
			}
		}

		for ( const width of [ 1280, 375 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			await page.emulateMedia( { forcedColors: 'active' } );
			await page.waitForTimeout( 150 );
			for ( const special of await measure() ) {
				if ( special.plain ) {
					expect( special.plate, `${ special.name } at ${ width }px in forced colours: the plate leaves no frame` ).toBeNull();
					expect( special.pictureColumn, `${ special.name } at ${ width }px in forced colours: the copy takes the row` ).toBe( 0 );
				} else {
					expect( special.image.loaded, `${ special.name } at ${ width }px in forced colours: the featured image keeps its pixels` ).toBe( true );
					expect( special.image.height ).toBeGreaterThan( 0 );
				}
			}
			await page.emulateMedia( { forcedColors: 'none' } );
		}
	} );
}

// PKIW #230: the map on a phone is tall enough that the embed's zoom buttons and
// its attribution leave most of the map showing. Measured at 320px: 14rem leaves
// the controls under a third of the frame. A wide map keeps its 16:10 shape.
test( 'the check-in map is at least 14rem tall on a phone and keeps its shape on a wide screen', async ( { page } ) => {
	await page.setViewportSize( { width: 320, height: 800 } );
	await page.goto( DRINK_SINGLE, { waitUntil: 'load' } );
	test.skip( ( await page.locator( 'main .cr-map-slip iframe' ).count() ) === 0, `no drink post with a map at ${ DRINK_SINGLE }` );
	const frame = page.locator( 'main .cr-map-slip iframe' );
	const rem = await page.evaluate( () => parseFloat( getComputedStyle( document.documentElement ).fontSize ) );
	for ( const width of [ 320, 375 ] ) {
		await page.setViewportSize( { width, height: 800 } );
		await page.waitForTimeout( 150 );
		const box = await frame.boundingBox();
		expect( box.height, `the map at ${ width }px` ).toBeGreaterThanOrEqual( 14 * rem - 1 );
		expect( box.x + box.width, 'inside the viewport' ).toBeLessThanOrEqual( width );
		expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBe( 0 );
	}
	await page.setViewportSize( { width: 1280, height: 900 } );
	await page.waitForTimeout( 150 );
	const wide = await frame.boundingBox();
	expect( Math.abs( wide.width / wide.height - 1.6 ), 'a wide map is 16:10' ).toBeLessThan( 0.05 );
} );

// A link's focus ring is 3px. Accessibility Checker's link-underline fix writes
// `outline-width: 2px` inline on focus (its frontendFixes bundle), and an inline
// style beats a stylesheet rule. This copies that write and expects 3px to hold.
test( 'a link keeps its 3px focus ring when a script writes a thinner outline inline', async ( { page } ) => {
	await page.goto( DRINK_ARCHIVE, { waitUntil: 'load' } );
	const links = [ '.cr-menu .pkiw-menu-entry__name', '.cr-menu .pkiw-menu-specials__link', 'main a' ];
	let checked = 0;
	for ( const selector of links ) {
		const link = page.locator( selector ).first();
		if ( 0 === await link.count() ) {
			continue;
		}
		await link.evaluate( ( el ) => {
			el.addEventListener( 'focusin', () => {
				el.style.outlineWidth = '2px';
				el.style.outlineOffset = '2px';
			} );
			const before = document.createElement( 'span' );
			before.tabIndex = -1;
			el.before( before );
			before.focus();
		} );
		await page.keyboard.press( 'Tab' );
		const ring = await link.evaluate( ( el ) => ( { focused: el === document.activeElement, inline: el.style.outlineWidth, width: getComputedStyle( el ).outlineWidth, style: getComputedStyle( el ).outlineStyle } ) );
		expect( ring.focused, `${ selector } took focus from the keyboard` ).toBe( true );
		expect( ring.inline, 'the script wrote its 2px' ).toBe( '2px' );
		expect( ring.style, `${ selector } shows an outline` ).toBe( 'solid' );
		expect( ring.width, `${ selector } keeps the theme's 3px` ).toBe( '3px' );
		checked++;
	}
	expect( checked, 'at least one link was checked' ).toBeGreaterThan( 0 );
} );

// PKIW #230: an eat single is an order ticket and a drink single is a taped
// photo beside a coaster. Each sits on one placemat with the check-in map
// slip under it. The post title is the page's one h1 and it is inside the
// order; the template's post header and featured image don't print. The map
// slip prints what the plugin's privacy rule let the card print.
for ( const [ kind, path, labels ] of [ [ 'eat', EAT_SINGLE, [ 'Dish', 'Restaurant', 'Cuisine', 'Ate', 'Rated' ] ], [ 'drink', DRINK_SINGLE, [ 'Drink', 'Type', 'Brand', 'Date', 'Rated' ] ] ] ) {
	test( `the ${ kind } single is one placemat: the order, then the map slip, with the title inside the order`, async ( { page } ) => {
		const osm = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'openstreetmap.org' ) ) {
				osm.push( request.url() );
			}
		} );
		await page.goto( path, { waitUntil: 'load' } );
		test.skip( ( await page.locator( `body.cr-order-single--${ kind }` ).count() ) === 0, `no ${ kind } post with its card at ${ path }` );

		const mat = page.locator( `main article.cr-placemat.cr-placemat--${ kind }` );
		await expect( mat ).toHaveCount( 1 );
		await expect( mat ).toHaveClass( /h-food/ );
		await expect( mat ).toHaveClass( 'eat' === kind ? /p-ate/ : /p-drank/ );

		// One title, inside the order; no second copy of the header or the picture above it.
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( mat.locator( '.cr-order h1.cr-order__title' ) ).toHaveCount( 1 );
		await expect( page.locator( 'main .single-post__header, main .single-post__featured, main .wp-block-post-featured-image' ) ).toHaveCount( 0 );
		await expect( mat.locator( `.cr-order.cr-order--${ 'eat' === kind ? 'ticket' : 'coaster' }` ) ).toHaveCount( 1 );
		expect( await mat.locator( '.cr-order__photo img' ).count(), 'one picture at most' ).toBeLessThanOrEqual( 1 );

		// Facts: labelled, in the approved order, each printed once.
		const facts = await mat.locator( 'dl.cr-order__facts > .cr-order__fact' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => [ node.querySelector( 'dt' ).textContent.trim(), node.querySelector( 'dd' ).textContent.trim() ] ) );
		const names = facts.map( ( [ label ] ) => label );
		expect( names.length ).toBeGreaterThan( 0 );
		expect( names, 'known facts in the approved order' ).toEqual( labels.filter( ( label ) => names.includes( label ) ) );
		expect( names ).toContain( 'eat' === kind ? 'Ate' : 'Date' );
		expect( names, 'the drink\'s date is labelled Date' ).not.toContain( 'Drank' );
		for ( const [ label, value ] of facts ) {
			expect( value, `${ label } has a value` ).not.toBe( '' );
		}
		const rated = facts.find( ( [ label ] ) => 'Rated' === label );
		if ( rated ) {
			expect( rated[ 1 ] ).toMatch( /^Rated [1-5] of 5$/ );
		}
		await expect( mat.locator( 'svg, [role="img"]' ), 'the rating is text, not a row of stars' ).toHaveCount( 0 );

		// The map slip: inside the placemat, under the order.
		const slip = mat.locator( ':scope > section.cr-map-slip' );
		await expect( slip ).toHaveCount( 1 );
		await expect( slip ).toHaveClass( /cr-map-slip--map/ );
		const frame = slip.locator( 'iframe' );
		await expect( frame ).toHaveCount( 1 );
		expect( await frame.getAttribute( 'title' ) ).toMatch( /^Map showing .+\.$/ );
		expect( await frame.getAttribute( 'loading' ) ).toBe( 'lazy' );
		expect( Number( await frame.getAttribute( 'width' ) ) ).toBeGreaterThan( 0 );
		expect( Number( await frame.getAttribute( 'height' ) ) ).toBeGreaterThan( 0 );
		expect( await frame.getAttribute( 'src' ) ).toContain( 'https://www.openstreetmap.org/export/embed.html' );
		expect( await frame.evaluate( ( el ) => null !== el.closest( 'a' ) ), 'the map is not inside a link' ).toBe( false );
		const links = page.locator( 'main a[href*="openstreetmap.org"]' );
		await expect( links, 'one OpenStreetMap link' ).toHaveCount( 1 );
		expect( ( await links.textContent() ).trim() ).toMatch( /^View .+ on OpenStreetMap \(opens in a new tab\)$/ );
		await expect( slip.locator( '.cr-map-slip__privacy' ) ).toHaveText( /privacy setting/ );
		await expect( slip.locator( '.cr-map-slip__place.p-location.h-card' ) ).toHaveCount( 1 );
		await expect( slip.locator( 'data.h-geo[hidden]' ) ).toHaveCount( 1 );

		const shape = await mat.evaluate( ( el ) => {
			const box = ( node ) => node.getBoundingClientRect();
			const order = el.querySelector( '.cr-order' );
			const slipEl = el.querySelector( '.cr-map-slip' );
			const map = slipEl.querySelector( '.cr-map-slip__map' );
			const caption = slipEl.querySelector( '.cr-map-slip__caption' );
			const frameEl = slipEl.querySelector( 'iframe' );
			const link = slipEl.querySelector( 'a' );
			const nav = document.querySelector( 'nav.cr-post-nav' );
			const visible = ( node ) => [ ...node.querySelectorAll( '*' ) ].filter( ( n ) => ! n.closest( '[hidden]' ) && n.children.length === 0 ).map( ( n ) => n.textContent.trim() ).filter( Boolean );
			return {
				slipUnderOrder: box( slipEl ).top >= box( order ).bottom - 1,
				slipInside: box( slipEl ).left >= box( el ).left && box( slipEl ).right <= box( el ).right && box( slipEl ).bottom <= box( el ).bottom,
				mapShare: box( map ).width / box( slipEl ).width,
				captionBeside: box( caption ).left >= box( map ).right - 1,
				captionUnder: box( caption ).top >= box( map ).bottom - 1,
				frameRight: box( frameEl ).right,
				frameLeft: box( frameEl ).left,
				order: [ ...el.querySelectorAll( 'h1, h2, iframe, a' ) ].map( ( n ) => ( 'A' === n.tagName ? ( n.href.includes( 'openstreetmap' ) ? 'osm' : 'link' ) : n.tagName.toLowerCase() + ( 'IFRAME' === n.tagName ? '' : ':' + n.textContent.trim() ) ) ),
				linkAfterCaption: Boolean( slipEl.querySelector( '.cr-map-slip__privacy' ).compareDocumentPosition( link ) & Node.DOCUMENT_POSITION_FOLLOWING ),
				navInside: nav ? el.contains( nav ) : false,
				navBelow: nav ? box( nav ).top >= box( el ).bottom : true,
				overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
				words: visible( el ),
			};
		} );
		expect( shape.slipUnderOrder, 'the slip sits under the order' ).toBe( true );
		expect( shape.slipInside, 'on the same placemat' ).toBe( true );
		const wide = page.viewportSize().width >= 768;
		if ( wide ) {
			expect( shape.mapShare, 'the map takes about 60 to 65% of the slip' ).toBeGreaterThanOrEqual( 0.58 );
			expect( shape.mapShare ).toBeLessThanOrEqual( 0.66 );
			expect( shape.captionBeside, 'the place is at the map\'s right' ).toBe( true );
		} else {
			expect( shape.captionUnder, 'the map stacks above its caption' ).toBe( true );
		}
		expect( shape.frameLeft, 'the map stays in the viewport' ).toBeGreaterThanOrEqual( 0 );
		expect( shape.frameRight ).toBeLessThanOrEqual( page.viewportSize().width );
		expect( shape.overflow ).toBe( 0 );
		// Reading and tab order: the title, Notes, the map, Where, then the OpenStreetMap link last.
		expect( shape.order[ 0 ] ).toBe( 'link' );
		expect( shape.order[ 1 ] ).toMatch( /^h1:/ );
		expect( shape.order.indexOf( 'iframe' ) ).toBeGreaterThan( shape.order.findIndex( ( item ) => item.startsWith( 'h1:' ) ) );
		expect( shape.order.indexOf( 'h2:Where' ) ).toBeGreaterThan( shape.order.indexOf( 'iframe' ) );
		expect( shape.order[ shape.order.length - 1 ], 'the OpenStreetMap link is the placemat\'s last stop' ).toBe( 'osm' );
		expect( shape.linkAfterCaption, 'the link follows the caption' ).toBe( true );
		expect( shape.order.filter( ( item ) => item.startsWith( 'h1:' ) ).length ).toBe( 1 );
		expect( shape.navInside, 'post navigation is outside the placemat' ).toBe( false );
		expect( shape.navBelow, 'and below it' ).toBe( true );
		// Each fact once: no visible text repeats inside the placemat.
		const repeated = shape.words.filter( ( word, index ) => word.length > 3 && shape.words.indexOf( word ) !== index );
		expect( repeated, 'no fact prints twice' ).toEqual( [] );
		expect( shape.words.join( ' ' ), 'no invented slogan' ).not.toMatch( /good food|good company|support local|sample content/i );

		// Keyboard: the kind link and the OpenStreetMap link show a 3px outline that nothing clips.
		for ( const selector of [ '.cr-order__kind-link', '.cr-map-slip__link' ] ) {
			const target = mat.locator( selector );
			await target.focus();
			const ring = await target.evaluate( ( el ) => {
				const cs = getComputedStyle( el );
				let clipped = false;
				for ( let up = el.parentElement; up && up !== document.body; up = up.parentElement ) {
					if ( 'visible' !== getComputedStyle( up ).overflow && up.getBoundingClientRect().right < el.getBoundingClientRect().right + 6 ) {
						clipped = true;
					}
				}
				return { style: cs.outlineStyle, width: parseFloat( cs.outlineWidth ), clipped };
			} );
			expect( ring.style, `${ selector } shows a focus outline` ).toBe( 'solid' );
			expect( ring.width ).toBeGreaterThanOrEqual( 3 );
			expect( ring.clipped, `${ selector }'s outline isn't clipped` ).toBe( false );
		}
		expect( osm.length, 'a public location loads the map' ).toBeGreaterThan( 0 );
	} );
}

// PKIW #230: a drink with no type claims none. The coaster prints no Type fact
// and the word Coffee appears nowhere on it; the plugin files the post under a
// "Drink" heading on the menu.
test( 'a drink with no type prints no Type fact and is never called Coffee', async ( { page } ) => {
	await page.goto( DRINK_UNSET, { waitUntil: 'load' } );
	test.skip( ( await page.locator( 'body.cr-order-single--drink' ).count() ) === 0, `no drink post with its card at ${ DRINK_UNSET }` );
	const mat = page.locator( 'main article.cr-placemat.cr-placemat--drink' );
	await expect( mat ).toHaveCount( 1 );
	const labels = await mat.locator( 'dl.cr-order__facts dt' ).allTextContents();
	expect( labels.map( ( label ) => label.trim() ), 'no Type fact' ).not.toContain( 'Type' );
	expect( labels.length, 'the other facts still print' ).toBeGreaterThan( 0 );
	expect( await mat.innerText(), 'the coaster never says Coffee' ).not.toMatch( /coffee/i );
	const words = await mat.evaluate( ( el ) => [ ...el.querySelectorAll( '*' ) ].filter( ( n ) => ! n.closest( '[hidden]' ) && n.children.length === 0 ).map( ( n ) => n.textContent.trim() ).filter( Boolean ) );
	expect( words.filter( ( word, index ) => word.length > 3 && words.indexOf( word ) !== index ), 'no fact prints twice' ).toEqual( [] );
} );

// PKIW #230: a hidden location prints nothing about the place: no slip, no
// link, no coordinates, no restaurant, and the browser never asks
// OpenStreetMap for anything.
for ( const path of ORDER_HIDDEN ) {
	test( `a hidden location prints no map slip and makes no OpenStreetMap request (${ path })`, async ( { page } ) => {
		const osm = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'openstreetmap' ) ) {
				osm.push( request.url() );
			}
		} );
		await page.goto( path, { waitUntil: 'networkidle' } );
		test.skip( ( await page.locator( 'body.cr-order-single' ).count() ) === 0, `no eat or drink post with its card at ${ path }` );
		await page.mouse.wheel( 0, 4000 );
		await page.waitForTimeout( 500 );

		await expect( page.locator( 'main article.cr-placemat' ) ).toHaveCount( 1 );
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cr-map-slip' ) ).toHaveCount( 0 );
		await expect( page.locator( 'main iframe' ) ).toHaveCount( 0 );
		await expect( page.locator( 'main a[href*="openstreetmap"]' ) ).toHaveCount( 0 );
		await expect( page.locator( 'main .cr-placemat' ).locator( '.p-location, .h-geo, .p-latitude, .p-longitude, .p-street-address, .p-locality' ) ).toHaveCount( 0 );
		expect( await page.locator( 'main .cr-placemat dt' ).allTextContents(), 'no restaurant fact' ).not.toContain( 'Restaurant' );
		expect( ( await page.content() ).includes( 'openstreetmap' ), 'the page source never names OpenStreetMap' ).toBe( false );
		expect( osm, 'no request to OpenStreetMap' ).toEqual( [] );
	} );
}

// PKIW #230: a map describes one check-in, so it needs coordinates. A post
// with a place and no coordinates has no map region at all: no frame, no
// "Where", no link.
test( 'a post with no coordinates has no map region', async ( { page } ) => {
	const osm = [];
	page.on( 'request', ( request ) => {
		if ( request.url().includes( 'openstreetmap' ) ) {
			osm.push( request.url() );
		}
	} );
	await page.goto( ORDER_NO_COORDS, { waitUntil: 'networkidle' } );
	test.skip( ( await page.locator( 'body.cr-order-single' ).count() ) === 0, `no eat or drink post with its card at ${ ORDER_NO_COORDS }` );
	const mat = page.locator( 'main article.cr-placemat' );
	await expect( mat ).toHaveCount( 1 );
	await expect( mat.locator( '.cr-order' ) ).toHaveCount( 1 );
	await expect( page.locator( '.cr-map-slip, .cr-map-slip__map' ) ).toHaveCount( 0 );
	await expect( page.locator( 'main iframe' ) ).toHaveCount( 0 );
	await expect( page.locator( 'main a[href*="openstreetmap"]' ) ).toHaveCount( 0 );
	// Nothing is left where the slip would be: the order is the placemat's last visible child.
	expect( await mat.evaluate( ( el ) => [ ...el.children ].filter( ( child ) => child.getClientRects().length > 0 ).length ), 'no blank frame or empty slip' ).toBe( 1 );
	expect( osm ).toEqual( [] );
	expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBe( 0 );
} );

// PKIW #230: a post whose card carries no location takes its point from
// Simple Location, when the plugin's privacy rule shows coordinates for it.
test( 'a Simple Location point the visitor may see gives the single its map', async ( { page } ) => {
	await page.goto( ORDER_SLOC, { waitUntil: 'load' } );
	test.skip( ( await page.locator( 'body.cr-order-single' ).count() ) === 0, `no eat or drink post with its card at ${ ORDER_SLOC }` );
	const slip = page.locator( 'main article.cr-placemat > section.cr-map-slip' );
	await expect( slip ).toHaveCount( 1 );
	await expect( slip.locator( 'iframe' ) ).toHaveCount( 1 );
	expect( await slip.locator( 'iframe' ).getAttribute( 'title' ) ).toBe( 'Map showing this location.' );
	expect( await slip.locator( 'iframe' ).getAttribute( 'src' ) ).toContain( 'https://www.openstreetmap.org/export/embed.html' );
	await expect( slip.locator( 'a[href*="openstreetmap.org"]' ) ).toHaveCount( 1 );
	await expect( slip.locator( '.cr-map-slip__address' ) ).not.toHaveText( '' );
	await expect( slip.locator( 'data.h-geo[hidden]' ) ).toHaveCount( 1 );
} );

// PKIW #230: on the Stream an eat or drink post is one compact card. Its
// title link covers the card, so there is one keyboard stop and one link
// name. No map link, address or coordinate prints, hidden or not.
test( 'the Stream shows an eat or drink post as one compact card with one link and no map', async ( { page } ) => {
	await page.goto( ORDER_STREAM, { waitUntil: 'load' } );
	const cards = page.locator( 'body.cr-stream-page article.pk-card.cr-chit' );
	test.skip( ( await cards.count() ) === 0, 'no eat or drink post on this Stream page' );
	for ( const card of await cards.all() ) {
		const o = await card.evaluate( ( el ) => {
			const link = el.querySelector( 'a.cr-chit__link' );
			const cover = getComputedStyle( link, '::after' );
			const box = el.getBoundingClientRect();
			return {
				links: el.querySelectorAll( 'a' ).length,
				stops: [ ...el.querySelectorAll( 'a, button, iframe, [tabindex]' ) ].filter( ( n ) => n.tabIndex >= 0 ).length,
				name: link.textContent.trim(),
				heading: link.parentElement.tagName,
				covers: 'absolute' === cover.position && Math.abs( parseFloat( cover.width ) - el.clientWidth ) < 2 && Math.abs( parseFloat( cover.height ) - el.clientHeight ) < 2,
				located: el.querySelectorAll( '.h-geo, .p-latitude, .p-longitude, .p-street-address, .p-locality, [href*="openstreetmap"], iframe' ).length,
				source: el.outerHTML.includes( 'openstreetmap' ),
				rating: el.querySelector( '.cr-chit__rating' )?.textContent.trim() ?? null,
				stars: el.querySelectorAll( 'svg, [role="img"]' ).length,
				date: el.querySelectorAll( 'time.dt-published' ).length,
				food: el.matches( '.h-food.p-ate, .h-food.p-drank' ),
				width: box.width,
			};
		} );
		expect( o.links, 'one link' ).toBe( 1 );
		expect( o.stops, 'one keyboard stop' ).toBe( 1 );
		expect( o.name, 'the link is named by the dish or drink' ).not.toBe( '' );
		expect( o.heading ).toBe( 'H2' );
		expect( o.covers, 'the link\'s box is the whole card' ).toBe( true );
		expect( o.located, 'no address, coordinate, map link or frame' ).toBe( 0 );
		expect( o.source ).toBe( false );
		if ( null !== o.rating ) {
			expect( o.rating ).toMatch( /^Rated [1-5] of 5$/ );
		}
		expect( o.stars ).toBe( 0 );
		expect( o.date ).toBe( 1 );
		expect( o.food ).toBe( true );
		await card.locator( 'a.cr-chit__link' ).focus();
		const ring = await card.locator( 'a.cr-chit__link' ).evaluate( ( el ) => {
			const cs = getComputedStyle( el, '::after' );
			return [ cs.outlineStyle, parseFloat( cs.outlineWidth ) ];
		} );
		expect( ring, 'the site\'s 3px focus ring, around the whole card' ).toEqual( [ 'solid', 3 ] );
	}
	expect( await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth ) ).toBe( 0 );
} );

// The shared kind-archive header: no "Stream · Kind" tape. The round badge is
// decorative (the h1 names the archive), sits on the upper-left corner of the
// title's first letter, and takes its circle and its glyph from two tokens,
// so the glyph can't take the circle's colour. Stream cards keep their labels.
for ( const path of KIND_ARCHIVE_HEADERS ) {
	test( `the kind archive header has no tape, and its badge is decorative and legible (${ path })`, async ( { page } ) => {
		const response = await page.goto( path, { waitUntil: 'load' } );
		test.skip( 200 !== response.status() || ( await page.locator( '.cr-archive__header' ).count() ) === 0, `no kind archive at ${ path }` );
		await page.evaluate( () => document.fonts.ready );
		const header = page.locator( 'main .cr-archive__header' );
		await expect( header ).toHaveCount( 1 );

		// 1. The tape is gone from the DOM, not hidden.
		expect( ( await header.textContent() ).includes( 'Stream · Kind' ), '"Stream · Kind" is not in the header' ).toBe( false );
		expect( ( await page.content() ).includes( 'Stream · Kind' ), '"Stream · Kind" is not in the page' ).toBe( false );
		await expect( header.locator( '.cr-archive-identity__kicker, .is-style-cr-tape-label' ) ).toHaveCount( 0 );

		// 4. One h1, named for the archive, and no other heading in the header.
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( header.locator( 'h1, h2, h3, h4, h5, h6' ) ).toHaveCount( 1 );
		await expect( header.getByRole( 'heading', { level: 1 } ) ).toHaveAccessibleName( /^(?!.*Stream · Kind)\S.*$/ );

		const o = await header.evaluate( ( el ) => {
			const holder = el.querySelector( '.cr-archive-identity' );
			const glyph = holder.querySelector( '.cr-archive-identity__glyph' );
			const svg = glyph.querySelector( 'svg' );
			const title = el.querySelector( 'h1' );
			const tile = title.querySelector( '.cr-cutout__tile' ) || title;
			const box = ( node ) => node.getBoundingClientRect();
			const cs = getComputedStyle( glyph );
			const pad = parseFloat( getComputedStyle( el ).paddingTop );
			const probe = ( value ) => {
				const i = document.body.appendChild( document.createElement( 'i' ) );
				i.style.color = value;
				const out = getComputedStyle( i ).color;
				i.remove();
				return out;
			};
			return {
				kind: holder.classList.contains( 'cr-archive-identity--kind' ),
				children: holder.children.length,
				holderHeight: box( holder ).height,
				bandAboveTitle: box( title ).top - box( el ).top,
				pad,
				glyph: { top: box( glyph ).top, left: box( glyph ).left, right: box( glyph ).right, bottom: box( glyph ).bottom, width: box( glyph ).width },
				tile: { top: box( tile ).top, left: box( tile ).left },
				hidden: glyph.getAttribute( 'aria-hidden' ),
				named: [ glyph.getAttribute( 'aria-label' ), glyph.getAttribute( 'title' ), glyph.getAttribute( 'role' ), svg ? svg.querySelector( 'title' ) : null ].filter( Boolean ).length,
				linked: null !== glyph.closest( 'a' ) || glyph.querySelectorAll( 'a' ).length > 0,
				stops: [ glyph, ...glyph.querySelectorAll( '*' ) ].filter( ( n ) => n.tabIndex >= 0 ).length,
				circle: cs.backgroundColor,
				ink: svg ? getComputedStyle( svg ).color : cs.color,
				radius: cs.borderTopLeftRadius,
				tokens: { badge: probe( 'var(--cr-archive-badge)' ), ink: probe( 'var(--cr-archive-badge-ink)' ), violet: probe( 'var(--cr-russian-violet)' ), yellow: probe( 'var(--cr-selective-yellow)' ), gray: probe( 'var(--cr-light-gray)' ) },
				dark: 'dark' === document.documentElement.dataset.theme || ( 'light' !== document.documentElement.dataset.theme && matchMedia( '(prefers-color-scheme: dark)' ).matches ),
				overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			};
		} );
		expect( o.kind, 'the shared kind-archive identity' ).toBe( true );

		// 2. No empty wrapper and no reserved row: the holder has only the badge and no height,
		//    and the band above the title is the room the badge's overhang stands in.
		expect( o.children, 'the holder holds the badge and nothing else' ).toBe( 1 );
		expect( o.holderHeight, 'the holder takes no row' ).toBe( 0 );
		expect( o.bandAboveTitle, 'nothing but the badge above the title' ).toBeLessThanOrEqual( o.glyph.width );
		expect( o.glyph.bottom, 'the badge reaches the title' ).toBeGreaterThan( o.tile.top );

		// The badge anchors the title's upper-left corner.
		expect( o.glyph.top, 'the badge starts above the first letter' ).toBeLessThan( o.tile.top );
		expect( o.glyph.left, 'and at its left' ).toBeLessThanOrEqual( o.tile.left );
		expect( o.glyph.left, 'inside the viewport' ).toBeGreaterThanOrEqual( 0 );
		expect( o.radius ).toBe( '50%' );

		// Decorative: hidden from assistive technology, unnamed, no link, no keyboard stop.
		expect( o.hidden ).toBe( 'true' );
		expect( o.named, 'no accessible name' ).toBe( 0 );
		expect( o.linked, 'not a link' ).toBe( false );
		expect( o.stops, 'no keyboard stop' ).toBe( 0 );

		// 3. Circle and glyph take their own tokens and never the same colour.
		expect( o.circle, 'the circle is the badge token' ).toBe( o.tokens.badge );
		expect( o.ink, 'the glyph is the badge ink token' ).toBe( o.tokens.ink );
		expect( o.circle, 'the glyph differs from its circle' ).not.toBe( o.ink );
		expect( [ o.circle, o.ink ], o.dark ? 'dark: yellow circle, violet glyph' : 'light: violet circle, light gray glyph' ).toEqual( o.dark ? [ o.tokens.yellow, o.tokens.violet ] : [ o.tokens.violet, o.tokens.gray ] );
		expect( o.overflow ).toBe( 0 );

		// Forced colours: the system pair and a real border.
		await page.emulateMedia( { forcedColors: 'active' } );
		const forced = await header.locator( '.cr-archive-identity__glyph' ).evaluate( ( el ) => {
			const cs = getComputedStyle( el );
			return { style: cs.borderTopStyle, width: parseFloat( cs.borderTopWidth ), same: cs.backgroundColor === cs.color };
		} );
		expect( forced.style, 'a visible border in forced colours' ).toBe( 'solid' );
		expect( forced.width ).toBeGreaterThanOrEqual( 2 );
		expect( forced.same, 'Canvas and CanvasText differ' ).toBe( false );
		await page.emulateMedia( { forcedColors: 'none' } );

		// 5. No horizontal overflow at 320px.
		await page.setViewportSize( { width: 320, height: 800 } );
		await page.waitForTimeout( 150 );
		const narrow = await page.evaluate( () => ( { overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, left: document.querySelector( '.cr-archive-identity__glyph' ).getBoundingClientRect().left } ) );
		expect( narrow.overflow, 'no horizontal overflow at 320px' ).toBe( 0 );
		expect( narrow.left, 'the badge stays on screen at 320px' ).toBeGreaterThanOrEqual( 0 );
	} );
}

// 6. The correction is the kind archive's alone: Stream cards keep their kind
// labels, and an archive of another family keeps its kicker.
test( 'Stream cards keep their kind labels and a format archive keeps its kicker', async ( { page } ) => {
	await page.goto( '/stream/', { waitUntil: 'load' } );
	await expect( page.locator( '.cr-archive-identity--kind' ) ).toHaveCount( 0 );
	expect( await page.locator( 'body.cr-stream-page .pk-kindlabel, body.cr-stream-page .cr-chit__kind' ).count(), 'Stream cards name their kind' ).toBeGreaterThan( 0 );
	await page.goto( TERM_ARCHIVE, { waitUntil: 'load' } );
	const kicker = page.locator( 'main .cr-archive__header .cr-archive-identity__kicker' );
	test.skip( ( await page.locator( 'main .cr-archive__header' ).count() ) === 0, `no archive header at ${ TERM_ARCHIVE }` );
	await expect( kicker ).toHaveCount( 1 );
	await expect( kicker ).toHaveText( /·/ );
	await expect( page.locator( '.cr-archive-identity--kind' ) ).toHaveCount( 0 );
} );
