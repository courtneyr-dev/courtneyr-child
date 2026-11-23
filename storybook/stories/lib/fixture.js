import { SENTINEL_HOST, THEME_URL_PATH, mountAssets } from '../../capture/format.mjs';
import { FIXTURES_MOUNT } from './mount.js';

const REPO_URL = 'https://github.com/courtneyr-dev/courtneyr-child';
const OWNED = 'data-cr-fixture';

export const VIEWPORTS = {
	w390: { name: 'Phone, 390 px', styles: { width: '390px', height: '844px' }, type: 'mobile' },
	w1280: { name: 'Desktop, 1280 px', styles: { width: '1280px', height: '800px' }, type: 'desktop' },
};

const AXE_RULES_PAGE_STORIES_SKIP_PER_SEED = {
	t269: [
		{
			rule: 'label-content-name-mismatch',
			where: 'the site title link in the header',
			why: 'The seed names the site "courtneyr-x-t269". inc/site-title-wordmark.php gives that link the fixed label "Courtney Robertson — CourtneyR.dev home", which holds the visible text on courtneyr.dev and not on this seed.',
		},
		{
			rule: 'list',
			where: 'the Primary navigation in the header',
			why: 'The seed has no saved navigation menu, so WordPress falls back to a Page List and prints its <ul> directly inside the navigation <ul>.',
		},
	],
};

const fixtures = new Map();
for ( const manifest of Object.values( import.meta.glob( '../../fixtures/*/manifest.json', { eager: true, import: 'default' } ) ) ) {
	for ( const [ id, entry ] of Object.entries( manifest.fixtures ) ) {
		fixtures.set( `${ manifest.environment }/${ id }`, { environment: manifest.environment, id, entry, manifest } );
	}
}

function find( key ) {
	const found = fixtures.get( key );
	if ( ! found ) {
		throw new Error( `No captured fixture "${ key }". Add it to storybook/recipes.mjs and run the capture. Captured: ${ [ ...fixtures.keys() ].join( ', ' ) }` );
	}
	return found;
}

const mirrorUrl = ( environment ) => new URL( `${ FIXTURES_MOUNT }/${ environment }/site`, document.baseURI ).href;

let bodyClasses = [];

// One preview document shows every story in turn, so each story replaces the last one's head styles and body classes.
async function applyDocument( { styles, classes, lang } ) {
	const previous = [ ...document.head.querySelectorAll( `[${ OWNED }]` ) ];
	const loading = [];
	for ( const node of styles ) {
		node.setAttribute( OWNED, '' );
		if ( node.tagName === 'LINK' ) {
			loading.push( new Promise( ( resolve, reject ) => {
				node.addEventListener( 'load', resolve, { once: true } );
				node.addEventListener( 'error', () => reject( new Error( `The stylesheet ${ node.href } did not load.` ) ), { once: true } );
			} ) );
		}
		document.head.append( node );
	}
	await Promise.all( loading );
	previous.forEach( ( node ) => node.remove() );
	document.body.classList.remove( ...bodyClasses );
	bodyClasses = classes;
	document.body.classList.add( ...bodyClasses );
	document.documentElement.lang = lang;
	await loadFontsBeforeFontDisplayOptionalGivesUp();
}

const loadFontsBeforeFontDisplayOptionalGivesUp = () => Promise.all( [ ...document.fonts ].map( ( face ) => face.load().catch( () => null ) ) );

function hrefTheThemeSetsAfterLoad( link ) {
	if ( link.tagName === 'LINK' && ! link.getAttribute( 'href' ) && link.dataset.crHref ) {
		link.href = link.dataset.crHref;
	}
	return link;
}

// The capture removes scripts, so an onclick or onload attribute has nothing to call and must not run here either.
function withoutInlineHandlers( bodyHtml ) {
	const template = document.createElement( 'template' );
	template.innerHTML = bodyHtml;
	for ( const node of template.content.querySelectorAll( '*' ) ) {
		for ( const { name } of [ ...node.attributes ] ) {
			if ( name.startsWith( 'on' ) ) {
				node.removeAttribute( name );
			}
		}
	}
	return template.innerHTML;
}

async function sha256Hex( bytes ) {
	return [ ...new Uint8Array( await crypto.subtle.digest( 'SHA-256', bytes ) ) ].map( ( byte ) => byte.toString( 16 ).padStart( 2, '0' ) ).join( '' );
}

async function loadFixture( key ) {
	const { environment, id, entry } = find( key );
	const url = new URL( `${ FIXTURES_MOUNT }/${ environment }/docs/${ id }.html`, document.baseURI );
	const response = await fetch( url );
	if ( ! response.ok ) {
		throw new Error( `${ url } returned HTTP ${ response.status }.` );
	}
	const bytes = await response.arrayBuffer();
	if ( ! globalThis.crypto?.subtle ) {
		throw new Error( 'A fixture is shown only after its SHA-256 matches the manifest, and browsers offer crypto.subtle on https and on localhost only. Open this Storybook over one of those.' );
	}
	if ( ( await sha256Hex( bytes ) ) !== entry.doc.sha256 ) {
		throw new Error( `${ url } is not the document the manifest records (SHA-256 ${ entry.doc.sha256 }). Run the capture again.` );
	}
	const html = mountAssets( new TextDecoder().decode( bytes ), mirrorUrl( environment ) );
	const parsed = new DOMParser().parseFromString( html, 'text/html' );
	const styles = [ ...parsed.head.querySelectorAll( 'link[rel~="stylesheet"], style' ) ].map( ( source ) => hrefTheThemeSetsAfterLoad( document.importNode( source, true ) ) );
	await applyDocument( { styles, classes: [ ...parsed.body.classList ], lang: parsed.documentElement.lang || 'en' } );
	return { body: withoutInlineHandlers( /<body\b[^>]*>([\s\S]*)<\/body>/i.exec( html )[ 1 ] ) };
}

export async function fixtureLoader( { parameters } ) {
	if ( parameters.fixture ) {
		return { fixture: await loadFixture( parameters.fixture ) };
	}
	if ( parameters.overview ) {
		const link = Object.assign( document.createElement( 'link' ), { rel: 'stylesheet', href: `${ mirrorUrl( find( parameters.overview[ 0 ] ).environment ) }${ THEME_URL_PATH }assets/css/tokens.css` } );
		const style = Object.assign( document.createElement( 'style' ), { textContent: OVERVIEW_CSS } );
		await applyDocument( { styles: [ link, style ], classes: [], lang: 'en' } );
	}
	return {};
}

export const renderFixture = ( _args, { loaded } ) => loaded.fixture.body;

export function withScheme( story, { globals } ) {
	document.documentElement.dataset.theme = globals.scheme;
	return story();
}

export function guardSentinelLinks() {
	const onSentinel = ( url ) => {
		try {
			return new URL( url, document.baseURI ).hostname === SENTINEL_HOST;
		} catch {
			return false;
		}
	};
	document.addEventListener( 'click', ( event ) => {
		const link = event.target instanceof Element ? event.target.closest( 'a[href]' ) : null;
		if ( link && onSentinel( link.getAttribute( 'href' ) ) ) {
			event.preventDefault();
		}
	} );
	document.addEventListener( 'submit', ( event ) => {
		if ( event.target instanceof HTMLFormElement && onSentinel( event.target.getAttribute( 'action' ) || '' ) ) {
			event.preventDefault();
		}
	} );
}

/**
 * One story for one fixture.
 *
 * @param {string} key                 `<environment>/<fixture id>`, for example `t269/block-post-glyph--blog`.
 * @param {object} [presentation]
 * @param {'w390'|'w1280'} [presentation.viewport]
 * @param {'light'|'dark'} [presentation.scheme]
 * @return {object} A CSF story.
 */
export function fixtureStory( key, { viewport, scheme } = {} ) {
	const { environment, entry } = find( key );
	const skipped = entry.component === null ? AXE_RULES_PAGE_STORIES_SKIP_PER_SEED[ environment ] ?? [] : [];
	return {
		parameters: { fixture: key, ...( skipped.length ? { a11y: { config: { rules: skipped.map( ( { rule } ) => ( { id: rule, enabled: false } ) ) } } } : {} ) },
		globals: { ...( scheme ? { scheme } : {} ), ...( viewport ? { viewport: { value: viewport, isRotated: false } } : {} ) },
	};
}

const OVERVIEW_CSS = `
.cr-overview { box-sizing: border-box; min-height: 100vh; margin: 0; padding: 2rem clamp(1rem, 4vw, 3rem) 4rem; background: var(--cr-surface); color: var(--cr-ink); font-family: var(--cr-font-body); font-size: 1.0625rem; line-height: 1.6; }
.cr-overview > * { max-width: 52rem; }
.cr-overview h1, .cr-overview h2 { font-family: var(--cr-font-accent); line-height: 1.2; margin: 0 0 0.5rem; }
.cr-overview h1 { font-size: 2rem; }
.cr-overview h2 { font-size: 1.25rem; margin-top: 2.5rem; overflow-wrap: anywhere; }
.cr-overview p { margin: 0 0 1rem; }
.cr-overview dl { display: grid; grid-template-columns: minmax(7rem, max-content) 1fr; gap: 0.35rem 1.25rem; align-items: baseline; margin: 0; }
.cr-overview dt { font-weight: 700; margin: 0; }
.cr-overview dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
.cr-overview ul { margin: 0; padding-left: 1.1rem; }
.cr-overview code { font-family: var(--cr-font-mono); font-size: 0.875em; }
.cr-overview a { color: var(--cr-link); text-decoration: underline; text-underline-offset: 0.15em; }
.cr-overview a:hover { color: var(--cr-link-hover); }
.cr-overview a:focus-visible { outline: 3px solid var(--cr-ink); outline-offset: 2px; }
@media (max-width: 40rem) { .cr-overview dl { grid-template-columns: 1fr; } .cr-overview dd { margin-bottom: 0.5rem; } }
`;

function element( tag, content, attributes = {} ) {
	const node = Object.assign( document.createElement( tag ), attributes );
	node.append( ...[ content ].flat().filter( ( child ) => child !== null && child !== undefined ) );
	return node;
}

const code = ( text ) => element( 'code', text );

function definitions( rows ) {
	return element( 'dl', rows.filter( ( [ , value ] ) => value ).flatMap( ( [ term, value ] ) => [ element( 'dt', term ), element( 'dd', value ) ] ) );
}

function renderCall( { route, request } ) {
	if ( request.focus ) {
		const attrs = request.focus.attrs ? ` ${ JSON.stringify( request.focus.attrs ) }` : '';
		return [ 'GET ', code( route ), `, keeping only the template's `, code( `${ request.focus.name }${ attrs }` ), ' block and the wrappers above it' ];
	}
	if ( request.content ) {
		return [ 'GET ', code( route ), ', with ', code( request.content ), ' rendered as the content of that post' ];
	}
	return [ 'GET ', code( route ), ', the whole page' ];
}

function packageLine( name, state ) {
	const git = state.git === 'not a git checkout' ? 'not a git checkout' : `git ${ state.git.head.slice( 0, 12 ) }${ state.git.dirty ? ', with uncommitted changes' : '' }`;
	return `${ name } ${ state.version } (${ git })`;
}

function renderOverview( title, keys ) {
	const { manifest, environment } = find( keys[ 0 ] );
	const skipped = keys.some( ( key ) => find( key ).entry.component === null ) ? AXE_RULES_PAGE_STORIES_SKIP_PER_SEED[ environment ] ?? [] : [];
	const { source, site } = manifest;
	const sourceLink = ( file ) => element( 'a', file, { href: `${ REPO_URL }/blob/${ source.commit }/${ file }` } );
	return element( 'main', [
		element( 'h1', title ),
		element( 'p', [ 'Every story here shows HTML that WordPress rendered from theme commit ', element( 'a', code( source.commit.slice( 0, 12 ) ), { href: `${ REPO_URL }/commit/${ source.commit }` } ), `, Version ${ source.themeVersion }. Nothing was copied by hand. Scripts are removed, so controls that need JavaScript do not respond.` ] ),
		...keys.map( find ).map( ( { id, entry } ) => element( 'section', [
			element( 'h2', id ),
			entry.notes ? element( 'p', entry.notes ) : null,
			entry.empty ? element( 'p', `WordPress prints no markup for this state. ${ entry.empty }` ) : null,
			definitions( [
				[ 'Render call', renderCall( entry ) ],
				[ 'Template', code( entry.census.template ) ],
				[ 'Source files', element( 'ul', entry.sources.map( ( file ) => element( 'li', sourceLink( file ) ) ) ) ],
				[ 'Document', [ `${ entry.doc.bytes.toLocaleString( 'en-US' ) } bytes, SHA-256 `, code( entry.doc.sha256.slice( 0, 16 ) ) ] ],
			] ),
		] ) ),
		skipped.length ? element( 'section', [
			element( 'h2', 'Accessibility findings these stories do not test' ),
			element( 'p', `axe reports ${ skipped.length } findings in WordPress's output for this page that come from the seed's content. The page stories skip these ${ skipped.length } rules and test every other rule.` ),
			definitions( skipped.map( ( { rule, where, why } ) => [ code( rule ), `In ${ where }. ${ why }` ] ) ),
		] ) : null,
		element( 'section', [
			element( 'h2', 'Captured from' ),
			definitions( [
				[ 'Theme', [ `courtneyr-child ${ source.themeVersion }, commit `, code( source.commit ), ', tree hash ', code( source.treeHash.slice( 0, 16 ) ) ] ],
				[ 'Parent theme', packageLine( site.parentTheme.slug, site.parentTheme ) ],
				[ 'WordPress', `${ site.wordpress } on PHP ${ site.php }` ],
				[ 'Plugins', element( 'ul', Object.entries( site.plugins ).map( ( [ name, state ] ) => element( 'li', packageLine( name, state ) ) ) ) ],
				[ 'Site', `${ site.name }, copied from the seed ${ site.seed } (${ site.content.posts } published posts)` ],
			] ),
		] ),
	], { className: 'cr-overview' } );
}

/**
 * The first story of a stories file: what each fixture is, which theme files produced it, and what it was captured from.
 *
 * @param {string}    title  Heading of the page.
 * @param {...string} keys   Fixture keys, in the order of the file's stories.
 * @return {object} A CSF story.
 */
export function overviewStory( title, ...keys ) {
	keys.forEach( find );
	return { name: 'Overview', parameters: { overview: keys }, render: () => renderOverview( title, keys ) };
}
