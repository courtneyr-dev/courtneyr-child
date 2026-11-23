export const SENTINEL_ORIGIN = 'http://fixture.invalid';
export const SENTINEL_HOST = 'fixture.invalid';
export const THEME_URL_PATH = '/wp-content/themes/courtneyr-child/';
export const THEME_STATIC_DIR = 'assets';
export const ASSET_EXTENSIONS = [ 'css', 'woff2', 'woff', 'ttf', 'otf', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'ico' ];
export const MARK_BEGIN = '<!--cr-capture:begin-->';
export const MARK_END = '<!--cr-capture:end-->';

const escapeRegExp = ( text ) => text.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
const jsonSlashes = ( text ) => text.replace( /\//g, '\\/' );

export const RULES = [
	{ name: 'scripts', from: () => /<script\b[^>]*>[\s\S]*?<\/script\s*>|<noscript\b[^>]*>[\s\S]*?<\/noscript\s*>/gi, to: '' },
	{ name: 'origin', from: ( origin ) => new RegExp( escapeRegExp( origin ), 'g' ), to: SENTINEL_ORIGIN },
	{ name: 'origin-json', from: ( origin ) => new RegExp( escapeRegExp( jsonSlashes( origin ) ), 'g' ), to: jsonSlashes( SENTINEL_ORIGIN ) },
	{ name: 'origin-encoded', from: ( origin ) => new RegExp( escapeRegExp( encodeURIComponent( origin ) ), 'g' ), to: encodeURIComponent( SENTINEL_ORIGIN ) },
	{ name: 'origin-host', from: ( origin ) => new RegExp( `//${ escapeRegExp( new URL( origin ).hostname ) }(?::${ new URL( origin ).port })?(?=['"/])`, 'g' ), to: `//${ SENTINEL_HOST }` },
	{ name: 'wprm-id', from: () => /wprm-comment-rating-\d+/g, to: 'wprm-comment-rating-0' },
	{ name: 'ver-mtime', from: () => /([?&](?:amp;|#0?38;)?ver=)\d{10}\b/g, to: ( _, prefix ) => `${ prefix }0` },
];

export function normalize( raw, origin, forbidden = [] ) {
	const counts = {};
	let text = raw;
	for ( const rule of RULES ) {
		const pattern = rule.from( origin );
		const hits = text.match( pattern );
		if ( hits ) {
			counts[ rule.name ] = hits.length;
			text = text.replace( pattern, rule.to );
		}
	}
	const { host, hostname } = new URL( origin );
	for ( const residue of [ host, `//${ hostname }` ] ) {
		const at = text.indexOf( residue );
		if ( at !== -1 ) {
			throw new Error( `a normalized document still holds "${ residue }": …${ text.slice( Math.max( 0, at - 60 ), at + 60 ) }… Add a rule to RULES in storybook/capture/format.mjs.` );
		}
	}
	// A forbidden value can be the run's key, so the error names it by label and never prints it.
	const leaked = forbidden.find( ( { value } ) => text.includes( value ) );
	if ( leaked ) {
		throw new Error( `a response holds ${ leaked.label }, which must not reach a fixture or the evidence directory.` );
	}
	const begin = text.indexOf( MARK_BEGIN );
	const end = text.indexOf( MARK_END );
	if ( begin === -1 && end === -1 ) {
		return { doc: text, component: null, counts };
	}
	if ( begin === -1 || end < begin || text.indexOf( MARK_BEGIN, begin + 1 ) !== -1 || text.indexOf( MARK_END, end + 1 ) !== -1 ) {
		throw new Error( 'the response does not hold exactly one begin marker followed by one end marker.' );
	}
	return {
		doc: text.slice( 0, begin ) + text.slice( begin + MARK_BEGIN.length, end ) + text.slice( end + MARK_END.length ),
		component: text.slice( begin + MARK_BEGIN.length, end ),
		counts,
	};
}

const ASSET_ROOTS = [ '/wp-content/', '/wp-includes/' ];
const SEGMENT = String.raw`/(?!\.{1,2}(?:[/?#"'\s()<>,]|$))[^/\s"'()<>\\?#,]+`;
// WordPress ends each inlined stylesheet with a `sourceURL=` comment. The page never requests that URL.
const ASSET_URL = new RegExp( `(sourceURL=)?${ escapeRegExp( SENTINEL_ORIGIN ) }(/wp-(?:content|includes)(?:${ SEGMENT })*${ SEGMENT }\\.(?:${ ASSET_EXTENSIONS.join( '|' ) }))(?![\\w.-])`, 'g' );

// Page and stylesheet content is site data. Every path that reaches the mirror goes through here, so a
// reference cannot name a file outside wp-content and wp-includes.
export function assetSitePath( reference, base = `${ SENTINEL_ORIGIN }/` ) {
	if ( /[\\\0]|%(?:2e|2f|5c|00)/i.test( reference ) ) {
		throw new Error( `${ JSON.stringify( reference ) } holds a backslash, a NUL, or an encoded dot or slash, so it is not mirrored.` );
	}
	const url = new URL( reference, base );
	const segments = url.pathname.split( '/' );
	if ( url.origin !== SENTINEL_ORIGIN || segments.includes( '..' ) || segments.includes( '.' ) || ! ASSET_ROOTS.some( ( root ) => url.pathname.startsWith( root ) ) ) {
		throw new Error( `${ JSON.stringify( reference ) } resolves to ${ url.origin }${ url.pathname }, which is not under the site's wp-content or wp-includes.` );
	}
	return url.pathname;
}

export function assetPaths( doc ) {
	return [ ...new Set( [ ...doc.matchAll( ASSET_URL ) ].filter( ( match ) => ! match[ 1 ] ).map( ( match ) => assetSitePath( match[ 2 ] ) ) ) ].sort();
}

export function cssAssetPaths( css, cssPath ) {
	const paths = new Set();
	for ( const match of css.matchAll( /url\(\s*(['"]?)([^'")]+)\1\s*\)|@import\s+(['"])([^'"]+)\3/g ) ) {
		const reference = ( match[ 2 ] || match[ 4 ] ).trim();
		if ( /^(data:|#)/.test( reference ) ) {
			continue;
		}
		const url = new URL( reference, SENTINEL_ORIGIN + cssPath );
		if ( url.origin === SENTINEL_ORIGIN && ASSET_EXTENSIONS.includes( url.pathname.split( '.' ).pop().toLowerCase() ) ) {
			paths.add( assetSitePath( reference, SENTINEL_ORIGIN + cssPath ) );
		}
	}
	return [ ...paths ].sort();
}

export function mountAssets( doc, mirrorUrl ) {
	return doc.replace( ASSET_URL, ( _, comment = '', path ) => comment + mirrorUrl + path );
}

export const isThemePath = ( path ) => path.startsWith( THEME_URL_PATH );

export function themeRepoPath( path ) {
	const repoPath = path.slice( THEME_URL_PATH.length );
	if ( ! repoPath.startsWith( `${ THEME_STATIC_DIR }/` ) ) {
		throw new Error( `${ path } is a theme file outside ${ THEME_STATIC_DIR }/. The Storybook serves the theme's ${ THEME_STATIC_DIR }/ only.` );
	}
	return repoPath;
}
