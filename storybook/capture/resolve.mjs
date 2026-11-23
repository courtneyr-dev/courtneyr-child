import { recipeId } from './inputs.mjs';

const ENTRY_KEYS = new Set( [ 'id', 'route', 'block', 'pattern', 'attrs', 'in', 'caps', 'empty', 'sources' ] );
const ID = /^(block|pattern|page)-[a-z0-9]+(?:-[a-z0-9]+)*(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?$/;

function blockMarkup( name, attrs ) {
	return `<!-- wp:${ name.replace( /^core\//, '' ) }${ attrs && Object.keys( attrs ).length ? ` ${ JSON.stringify( attrs ) }` : '' } /-->`;
}


export function resolveFixture( entry ) {
	const problem = ( text ) => new Error( `recipe entry ${ JSON.stringify( entry.id ) }: ${ text }` );
	const unknown = Object.keys( entry ).filter( ( key ) => ! ENTRY_KEYS.has( key ) );
	if ( unknown.length ) {
		throw problem( `unknown key ${ unknown.join( ', ' ) }` );
	}
	if ( typeof entry.id !== 'string' || ! ID.test( entry.id ) ) {
		throw problem( 'id must look like block-post-glyph--note, pattern-cr-hcard or page-home' );
	}
	if ( typeof entry.route !== 'string' || ! entry.route.startsWith( '/' ) || /[?#]/.test( entry.route ) ) {
		throw problem( 'route must be a site path that starts with / and has no query string' );
	}
	const kind = entry.id.split( '-' )[ 0 ];
	const named = entry.block ? 'block' : entry.pattern ? 'pattern' : 'page';
	if ( kind !== named || ( entry.block && entry.pattern ) ) {
		throw problem( `a ${ kind } id needs ${ kind === 'page' ? 'neither block nor pattern' : `the ${ kind } key and no other subject` }` );
	}
	const base = { id: entry.id, route: entry.route, empty: entry.empty ?? null, sources: entry.sources ?? [] };
	if ( kind === 'page' ) {
		if ( entry.in || entry.attrs || entry.empty ) {
			throw problem( 'a page takes id, route, caps and sources only' );
		}
		return { ...base, request: entry.caps ? { caps: entry.caps } : {}, subject: null, expect: null };
	}
	const focus = kind === 'block'
		? { name: entry.block, ...( entry.attrs ? { attrs: entry.attrs } : {} ) }
		: { name: 'core/pattern', attrs: { slug: entry.pattern } };
	if ( ! [ undefined, 'content', 'template' ].includes( entry.in ) ) {
		throw problem( 'in must be "content" (the default) or "template"' );
	}
	const request = entry.in === 'template' ? { focus } : { content: blockMarkup( focus.name, focus.attrs ) };
	return {
		...base,
		request: entry.caps ? { ...request, caps: entry.caps } : request,
		subject: kind === 'block' ? `block:${ entry.block }` : `pattern:${ entry.pattern }`,
		expect: entry.empty ? 'empty' : 'output',
	};
}

export function resolveEnvironment( name, environment ) {
	if ( ! /^[a-z0-9]+$/.test( name ) ) {
		throw new Error( `environment name ${ JSON.stringify( name ) } must be lowercase letters and digits` );
	}
	const fixtures = environment.fixtures.map( resolveFixture );
	const ids = fixtures.map( ( fixture ) => fixture.id );
	const repeated = ids.find( ( id, index ) => ids.indexOf( id ) !== index );
	if ( repeated ) {
		throw new Error( `environment ${ name } lists ${ repeated } twice` );
	}
	const plugins = [ ...environment.plugins ].sort();
	return {
		name,
		site: environment.site,
		seed: environment.seed,
		plugins,
		maxBytes: environment.maxBytes,
		fixtures,
		id: recipeId( { seed: environment.seed, plugins }, fixtures.map( ( { id, route, request, expect } ) => ( { id, route, request, expect } ) ) ),
	};
}
