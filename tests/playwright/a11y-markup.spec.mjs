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
// (Light Gray, Periwinkle, Sky Blue at low opacity), the cover nearly fills
// it, a taller flap folds over the top, the corners are soft, and it tilts by
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
			film: { alpha: alpha( cs.backgroundColor ), rgb: rgb( cs.backgroundColor ) },
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
	for ( const card of await bagged.all() ) {
		await card.locator( '.pk-title a' ).focus();
		const o = await card.evaluate( measure );
		// Scale: about a fifth of the rack on a wide screen.
		if ( wide ) {
			expect( o.sleeve.width / o.rack, 'the bag is about 21% of the rack' ).toBeGreaterThanOrEqual( 0.19 );
			expect( o.sleeve.width / o.rack ).toBeLessThanOrEqual( 0.23 );
		}
		// Pale clear plastic: the rack shows through, lightly washed.
		expect( o.film.alpha, 'the plastic is see-through' ).toBeLessThanOrEqual( 0.3 );
		expect( o.film.alpha, 'the plastic washes the rack' ).toBeGreaterThanOrEqual( 0.1 );
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
// the menu on the first page; the menu is the Query Loop's own list, each
// post one line (name link, a leader hidden from assistive technology, the
// rating as text) under a section heading printed once per page. The paper,
// the columns and the frame are CSS. No street, coordinates or venue link
// prints anywhere on the page.
for ( const [ kind, path, perPage, wideColumns ] of [ [ 'eat', EAT_ARCHIVE, 6, 3 ], [ 'drink', DRINK_ARCHIVE, 8, 2 ] ] ) {
	test( `the ${ kind } archive is a menu: Recent Specials, then lines under section headings`, async ( { page } ) => {
		await page.goto( path, { waitUntil: 'load' } );
		test.skip( ( await page.locator( 'main li.wp-block-post' ).count() ) < 3, `fewer than three ${ kind } posts on this site` );
		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
		const menu = page.locator( `.cr-archive--${ kind } ul.cr-menu__list` );
		await expect( menu ).toHaveCount( 1 );
		const lines = menu.locator( ':scope > li' );
		const count = await lines.count();
		expect( count ).toBeGreaterThan( 0 );
		expect( count, `${ perPage } lines to a page` ).toBeLessThanOrEqual( perPage );
		for ( const line of await lines.all() ) {
			await expect( line.locator( 'a.pkiw-menu-entry__name' ) ).toHaveCount( 1 );
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
		const sections = ( await menu.locator( 'h2.pkiw-menu-entry__section' ).allTextContents() ).map( ( text ) => text.trim() );
		expect( sections.length, 'the menu has section headings' ).toBeGreaterThan( 0 );
		expect( new Set( sections ).size, 'a section is headed once on a page' ).toBe( sections.length );
		await expect( lines.first().locator( 'h2.pkiw-menu-entry__section' ) ).toHaveCount( 1 );

		const specials = page.locator( `.cr-archive--${ kind } section.pkiw-menu-specials` );
		await expect( specials ).toHaveCount( 1 );
		await expect( page.locator( `[id="${ await specials.getAttribute( 'aria-labelledby' ) }"]` ) ).toHaveText( 'Recent Specials' );
		const specialCount = await specials.locator( 'ul > li' ).count();
		expect( specialCount ).toBeGreaterThan( 0 );
		expect( specialCount, 'two specials at most' ).toBeLessThanOrEqual( 2 );
		expect( await specials.locator( 'ul > li h3 a' ).count(), 'each special is named by a linked heading' ).toBe( specialCount );

		const shape = await page.evaluate( ( k ) => {
			const list = document.querySelector( `.cr-archive--${ k } ul.cr-menu__list` );
			const box = document.querySelector( `.cr-archive--${ k } section.pkiw-menu-specials` );
			const cs = getComputedStyle( list );
			return {
				columns: cs.columnCount,
				paper: cs.backgroundColor,
				frame: parseFloat( getComputedStyle( box ).borderTopWidth ),
				specialsFirst: box.getBoundingClientRect().top < list.getBoundingClientRect().top,
				overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
				located: document.querySelectorAll( 'main .p-street-address, main .p-latitude, main .p-longitude, main .h-geo, main .pkiw-menu-entry a[href*="openstreetmap"]' ).length,
			};
		}, kind );
		expect( shape.specialsFirst, 'Recent Specials sits above the menu' ).toBe( true );
		expect( shape.columns, 'the menu flows in columns on a wide screen and one on a phone' ).toBe( page.viewportSize().width >= 1024 ? String( wideColumns ) : '1' );
		expect( shape.paper, 'the menu is on paper' ).not.toBe( 'rgba(0, 0, 0, 0)' );
		expect( shape.frame, 'Recent Specials is framed' ).toBeGreaterThanOrEqual( 2 );
		expect( shape.overflow ).toBe( 0 );
		expect( shape.located, 'no street, coordinates or map link on the menu' ).toBe( 0 );

		// The site's shared pager, and no specials past the first page.
		const next = page.locator( `.cr-archive--${ kind } nav.wp-block-query-pagination a.wp-block-query-pagination-next` );
		if ( await next.count() ) {
			await expect( page.locator( `.cr-archive--${ kind } nav.wp-block-query-pagination` ) ).not.toHaveClass( /cr-stream__pagination/ );
			await next.click();
			await page.waitForLoadState( 'load' );
			await expect( page.locator( `.cr-archive--${ kind } section.pkiw-menu-specials` ) ).toHaveCount( 0 );
			const second = page.locator( `.cr-archive--${ kind } ul.cr-menu__list > li` );
			expect( await second.count() ).toBeGreaterThan( 0 );
			await expect( second.first().locator( 'h2.pkiw-menu-entry__section' ) ).toHaveCount( 1 );
		}
	} );
}
