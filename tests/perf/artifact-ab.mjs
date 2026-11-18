// N7 no-visual-change A/B harness (spec "No-visual-change check" steps 1 to 7, and R-N12 (m2)'s in-card classification).
//
//   node tests/perf/artifact-ab.mjs capture --role baseline --base-url http://localhost:8887 --map <baseline fidelity.json>
//        [--map-sha256 <hex>] [--spec <-ref spec.json>] [--expect <seed-n7 --expect json>]... [--studio <site dir>] [--loads 2] --out <dir>
//   node tests/perf/artifact-ab.mjs capture --role head --base-url http://localhost:8887 --map <frozen head fidelity.json>
//        --map-sha256 <recorded hex> --baseline <baseline capture dir> [--expect <json>]... [--studio <site dir>] --out <dir>
//   node tests/perf/artifact-ab.mjs compare --baseline <dir> --head <dir> --style <slug>[=<root class>]... [--out <dir>]
//   node tests/perf/artifact-ab.mjs classify --baseline <dir> --root <selector> --pairs <pairs.json> [--out <file>]
//
// Exit 0 clean, 1 a difference (fail), 2 tooling failure (never a verdict). classify exits 0 once it has classified.
// The view records come only from the maps; the harness has no width list of its own.
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import crypto from 'node:crypto';
import { spawnSync, execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const SELF = fileURLToPath( import.meta.url );
const HERE = path.dirname( SELF );

export class ToolingError extends Error {}

const LOCAL_HOSTS = [ 'localhost', '127.0.0.1', '[::1]' ];
const isLocalHost = ( hostname ) => LOCAL_HOSTS.includes( hostname ) || hostname.endsWith( '.local' );

// tests/routes.mjs assertLocalTarget()'s host list, without its CR_ALLOW_REMOTE escape: N7 never captures a remote host.
export function assertLocalBase( base ) {
	let url;
	try {
		url = new URL( base );
	} catch {
		throw new ToolingError( `--base-url ${ JSON.stringify( base ) } is not a URL` );
	}
	if ( ! isLocalHost( url.hostname ) ) {
		throw new ToolingError( `refusing ${ url.hostname }: the A/B harness reads only a local site (localhost, 127.0.0.1, [::1] or *.local)` );
	}
	return url.origin;
}

export const sha256 = ( bytes ) => crypto.createHash( 'sha256' ).update( bytes ).digest( 'hex' );

// Hashes the bytes it then hands back, so the caller parses exactly what was checked.
export function readVerified( file, expected ) {
	let bytes;
	try {
		bytes = fs.readFileSync( file );
	} catch ( e ) {
		throw new ToolingError( `cannot read ${ file }: ${ e.code || e.message }` );
	}
	const got = sha256( bytes );
	if ( expected && got !== String( expected ).toLowerCase() ) {
		throw new ToolingError( `${ file }: SHA-256 ${ got } differs from the recorded ${ expected }` );
	}
	return { bytes, sha256: got };
}

const RECORD_FIELDS = [ 'path', 'selector', 'width', 'height', 'fullPage', 'colorScheme' ];

function toRecord( v, source ) {
	const bad = ( why ) => {
		throw new ToolingError( `${ source }: view ${ JSON.stringify( v?.name ) } ${ why }` );
	};
	if ( 'string' !== typeof v?.name || ! /^[\w.-]+$/.test( v.name ) ) {
		bad( 'needs a name of letters, digits, dots, dashes or underscores' );
	}
	if ( 'string' !== typeof v.path || ! v.path.startsWith( '/' ) ) {
		bad( 'needs a site-relative path' );
	}
	if ( ! Number.isInteger( v.width ) || v.width < 1 || ! Number.isInteger( v.height ) || v.height < 1 ) {
		bad( 'needs integer width and height' );
	}
	if ( v.media ) {
		bad( `has media ${ v.media }; the N7 view set has no print views` );
	}
	if ( undefined !== v.colorScheme && ! [ 'light', 'dark' ].includes( v.colorScheme ) ) {
		bad( `has colorScheme ${ JSON.stringify( v.colorScheme ) }` );
	}
	return {
		name: v.name,
		path: v.path,
		selector: v.selector || '',
		width: v.width,
		height: v.height,
		fullPage: !! v.fullPage,
		colorScheme: 'dark' === v.colorScheme ? 'dark' : 'light',
	};
}

// A fidelity map keeps its records in groups[].views; a capture.mjs spec.json (a -ref kind's step 4) in views[].
export function viewRecords( doc, source ) {
	let views = null;
	if ( Array.isArray( doc?.groups ) ) {
		views = doc.groups.flatMap( ( g ) => g.views || [] );
	} else if ( Array.isArray( doc?.views ) ) {
		views = doc.views;
	}
	if ( ! views ) {
		throw new ToolingError( `${ source }: neither groups[].views nor views[]` );
	}
	return views.map( ( v ) => toRecord( v, source ) );
}

export function compareRecords( base, head ) {
	const problems = [];
	const index = ( list, side ) => {
		const byName = new Map();
		for ( const r of list ) {
			if ( byName.has( r.name ) ) {
				problems.push( `${ side } has two records named ${ r.name }` );
			}
			byName.set( r.name, r );
		}
		return byName;
	};
	const b = index( base, 'baseline' );
	const h = index( head, 'head' );
	if ( base.length !== head.length ) {
		problems.push( `record count: baseline ${ base.length }, head ${ head.length }` );
	}
	for ( const [ name, r ] of b ) {
		const o = h.get( name );
		if ( ! o ) {
			problems.push( `the head map has no record ${ name }` );
			continue;
		}
		for ( const f of RECORD_FIELDS ) {
			if ( r[ f ] !== o[ f ] ) {
				problems.push( `${ name }: ${ f } is ${ JSON.stringify( r[ f ] ) } at baseline, ${ JSON.stringify( o[ f ] ) } at head` );
			}
		}
	}
	for ( const name of h.keys() ) {
		if ( ! b.has( name ) ) {
			problems.push( `the head map has a record ${ name } the baseline doesn't` );
		}
	}
	return problems;
}

// The fixture template is the N7 view set's `<group>-full` record (1672 x 941, full page, light).
const FIXTURE_VIEW = { selector: '', width: 1672, height: 941, fullPage: true, colorScheme: 'light' };

// Judged records first, then one other-scheme twin per record the map doesn't already hold in that scheme,
// then each --expect post's single, logged out and (private or password-protected) as the seeding administrator.
export function capturePlan( records, posts = [] ) {
	const items = records.map( ( record ) => ( { key: record.name, role: 'judged', auth: 'anon', record } ) );
	const sameView = ( a, b ) => RECORD_FIELDS.every( ( f ) => 'colorScheme' === f || a[ f ] === b[ f ] );
	for ( const r of records ) {
		const scheme = 'dark' === r.colorScheme ? 'light' : 'dark';
		if ( records.some( ( o ) => o.colorScheme === scheme && sameView( o, r ) ) ) {
			continue;
		}
		const key = `${ r.name }~${ scheme }`;
		items.push( { key, role: 'twin', auth: 'anon', record: { ...r, name: key, colorScheme: scheme } } );
	}
	for ( const p of posts ) {
		for ( const auth of p.admin ? [ 'anon', 'admin' ] : [ 'anon' ] ) {
			const key = `post-${ p.id }~${ auth }`;
			items.push( { key, role: 'fixture', auth, post: p.id, variant: p.variant, record: { name: key, path: p.path, ...FIXTURE_VIEW } } );
		}
	}
	return items;
}

export function expectPosts( doc, source ) {
	if ( ! Array.isArray( doc?.posts ) ) {
		throw new ToolingError( `${ source }: no posts[] (seed-n7.php --expect output)` );
	}
	return doc.posts.map( ( p ) => {
		if ( ! Number.isInteger( p.id ) || 'string' !== typeof p.path || ! p.path.startsWith( '/' ) ) {
			throw new ToolingError( `${ source }: post ${ JSON.stringify( p ) } needs an integer id and a site-relative path` );
		}
		return { id: p.id, path: p.path, variant: p.variant || '', admin: 'private' === p.status || !! p.hasPassword };
	} );
}

// Everything that makes a comparison meaningless, checked before any view is diffed.
export function preflight( base, head ) {
	const problems = [];
	if ( 'baseline' !== base.role || 'head' !== head.role ) {
		problems.push( `roles are ${ base.role } and ${ head.role }, not baseline and head` );
	}
	if ( base.harness?.sha256 !== head.harness?.sha256 ) {
		problems.push( 'the baseline and head were captured by different harness files' );
	}
	if ( base.baseUrl !== head.baseUrl || base.loads !== head.loads ) {
		problems.push( 'the baseline and head differ in base URL or loads per view' );
	}
	problems.push( ...compareRecords( base.records, head.records ) );
	for ( const [ side, m ] of [ [ 'baseline', base ], [ 'head', head ] ] ) {
		for ( const r of m.records ) {
			const c = m.captures[ r.name ];
			if ( ! c || 'judged' !== c.role ) {
				const twins = Object.keys( m.captures ).filter( ( k ) => k.startsWith( `${ r.name }~` ) );
				problems.push( `the ${ side } has no capture of judged record ${ r.name }${ twins.length ? ` (${ twins.join( ', ' ) } is extra coverage and doesn't stand in)` : '' }` );
			}
		}
		for ( const [ key, c ] of Object.entries( m.captures ) ) {
			if ( c.error ) {
				problems.push( `the ${ side } capture ${ key } failed: ${ c.error }` );
			}
		}
	}
	const extra = ( m ) => Object.keys( m.captures ).filter( ( k ) => 'judged' !== m.captures[ k ].role ).sort().join( ' ' );
	if ( extra( base ) !== extra( head ) ) {
		problems.push( `the twin and fixture captures differ: baseline [${ extra( base ) }], head [${ extra( head ) }]` );
	}
	return problems;
}

const VOID = new Set( [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ] );
const RAW = new Set( [ 'script', 'style', 'textarea', 'title', 'noscript', 'xmp' ] );
const KEEP_SPACE = new Set( [ 'pre', 'textarea', 'script', 'style', 'noscript', 'xmp' ] );
const ATTR = /([^\s"'>/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/y;

// A forgiving tokenizer, enough to compare two renders of one template: comments and doctypes drop,
// raw-text elements keep their body, an end tag closes back to its nearest open match.
export function parseHtml( html ) {
	const root = { tag: '#root', attrs: [], children: [] };
	const stack = [ root ];
	const lower = html.toLowerCase();
	const top = () => stack[ stack.length - 1 ];
	const text = ( s ) => s && top().children.push( { text: s } );
	let i = 0;
	while ( i < html.length ) {
		const lt = html.indexOf( '<', i );
		if ( lt < 0 ) {
			text( html.slice( i ) );
			break;
		}
		text( html.slice( i, lt ) );
		i = lt;
		if ( html.startsWith( '<!--', i ) ) {
			const end = html.indexOf( '-->', i + 4 );
			i = end < 0 ? html.length : end + 3;
			continue;
		}
		if ( '!' === html[ i + 1 ] || '?' === html[ i + 1 ] ) {
			const end = html.indexOf( '>', i );
			i = end < 0 ? html.length : end + 1;
			continue;
		}
		const close = /^<\/([a-zA-Z][^\s/>]*)[^>]*>/.exec( html.slice( i, i + 256 ) );
		if ( close ) {
			const at = stack.findLastIndex( ( n ) => n.tag === close[ 1 ].toLowerCase() );
			if ( at > 0 ) {
				stack.length = at;
			}
			i += close[ 0 ].length;
			continue;
		}
		const open = /^<([a-zA-Z][^\s/>]*)/.exec( html.slice( i, i + 128 ) );
		if ( ! open ) {
			text( '<' );
			i++;
			continue;
		}
		const el = { tag: open[ 1 ].toLowerCase(), attrs: [], children: [] };
		i += open[ 0 ].length;
		let selfClosing = false;
		while ( i < html.length ) {
			if ( /\s/.test( html[ i ] ) ) {
				i++;
			} else if ( '>' === html[ i ] ) {
				i++;
				break;
			} else if ( '/' === html[ i ] ) {
				selfClosing = '>' === html[ i + 1 ];
				i += selfClosing ? 2 : 1;
				if ( selfClosing ) {
					break;
				}
			} else {
				ATTR.lastIndex = i;
				const m = ATTR.exec( html );
				if ( ! m ) {
					i++;
					continue;
				}
				el.attrs.push( [ m[ 1 ].toLowerCase(), m[ 2 ] ?? m[ 3 ] ?? m[ 4 ] ?? '' ] );
				i = ATTR.lastIndex;
			}
		}
		top().children.push( el );
		if ( VOID.has( el.tag ) || selfClosing ) {
			continue;
		}
		if ( RAW.has( el.tag ) ) {
			const end = lower.indexOf( `</${ el.tag }`, i );
			const stop = end < 0 ? html.length : end;
			if ( stop > i ) {
				el.children.push( { text: html.slice( i, stop ) } );
			}
			const gt = end < 0 ? -1 : html.indexOf( '>', end );
			i = gt < 0 ? html.length : gt + 1;
			continue;
		}
		stack.push( el );
	}
	return root;
}

// WordPress prints nonces into inline scripts and links; they rotate every 12 hours.
const VOLATILE = [ [ /(nonce["'\s:=(]+)[0-9a-f]{10}(?![0-9a-z])/gi, '$1<nonce>' ] ];

const attrOf = ( n, name ) => n.attrs.find( ( [ k ] ) => k === name )?.[ 1 ];
// Stylesheet <link> and <style> elements belong to step 6 (the handle set), which may shrink; the DOM diff skips them.
const isSheet = ( n ) => 'style' === n.tag || ( 'link' === n.tag && /(^|\s)stylesheet(\s|$)/i.test( attrOf( n, 'rel' ) || '' ) );

function normalizeNode( n, keep ) {
	const children = [];
	const keepSpace = keep || KEEP_SPACE.has( n.tag );
	for ( const c of n.children ) {
		if ( c.tag ) {
			if ( ! isSheet( c ) ) {
				children.push( normalizeNode( c, keepSpace ) );
			}
			continue;
		}
		const last = children[ children.length - 1 ];
		const joined = ( last && undefined !== last.text ? last.text : '' ) + c.text;
		const t = keepSpace ? joined : joined.replace( /\s+/g, ' ' );
		if ( last && undefined !== last.text ) {
			last.text = t;
		} else if ( t ) {
			children.push( { text: t } );
		}
	}
	const seen = new Set();
	const attrs = n.attrs
		.filter( ( [ k ] ) => ! seen.has( k ) && seen.add( k ) )
		.map( ( [ k, v ] ) => [ k, 'class' === k ? v.split( /\s+/ ).filter( Boolean ) : v ] )
		.sort( ( a, b ) => ( a[ 0 ] < b[ 0 ] ? -1 : 1 ) );
	return { tag: n.tag, attrs, children };
}

export function normalizeHtml( html ) {
	for ( const [ re, to ] of VOLATILE ) {
		html = html.replace( re, to );
	}
	return normalizeNode( parseHtml( html ), false );
}

const sameList = ( a, b ) => a.length === b.length && a.every( ( x, i ) => x === b[ i ] );

// The one whitelist entry: is-style-<slug> added to an element that carries the kind's cr-* root class.
export function classChange( base, head, styles ) {
	if ( sameList( base, head ) ) {
		return null;
	}
	for ( const { slug, root } of styles ) {
		const token = `is-style-${ slug }`;
		const at = head.indexOf( token );
		if ( at >= 0 && base.includes( root ) && ! base.includes( token ) && sameList( base, head.filter( ( _, i ) => i !== at ) ) ) {
			return null;
		}
	}
	return 'class tokens differ beyond the is-style-<slug> whitelist';
}

const label = ( n, i ) => {
	const cls = ( n.attrs.find( ( [ k ] ) => 'class' === k )?.[ 1 ] || [] ).slice( 0, 2 );
	return `${ n.tag }:${ i }${ cls.length ? `.${ cls.join( '.' ) }` : '' }`;
};
const clip = ( s ) => ( s.length > 160 ? `${ s.slice( 0, 157 ) }...` : s );

export function diffDom( base, head, styles = [], limit = 40 ) {
	const diffs = [];
	const add = ( d ) => diffs.length < limit && diffs.push( d );
	const walk = ( a, b, where ) => {
		if ( a.tag !== b.tag ) {
			return add( { where, what: 'tag', base: a.tag, head: b.tag } );
		}
		const am = new Map( a.attrs );
		const bm = new Map( b.attrs );
		for ( const k of new Set( [ ...am.keys(), ...bm.keys() ] ) ) {
			const x = am.get( k );
			const y = bm.get( k );
			if ( 'class' === k ) {
				const why = classChange( x || [], y || [], styles );
				if ( why ) {
					add( { where, what: 'class', base: x?.join( ' ' ) ?? null, head: y?.join( ' ' ) ?? null, why } );
				}
			} else if ( x !== y ) {
				add( { where, what: `attribute ${ k }`, base: x ?? null, head: y ?? null } );
			}
		}
		const n = Math.min( a.children.length, b.children.length );
		for ( let i = 0; i < n && diffs.length < limit; i++ ) {
			const x = a.children[ i ];
			const y = b.children[ i ];
			if ( undefined !== x.text || undefined !== y.text ) {
				if ( x.text !== y.text ) {
					add( { where: `${ where }>#${ i }`, what: 'text', base: clip( x.text ?? `<${ x.tag }>` ), head: clip( y.text ?? `<${ y.tag }>` ) } );
				}
			} else {
				walk( x, y, `${ where }>${ label( x, i ) }` );
			}
		}
		if ( a.children.length !== b.children.length ) {
			add( { where, what: 'children', base: a.children.length, head: b.children.length } );
		}
	};
	walk( base, head, 'document' );
	return diffs;
}

const classesOf = ( n ) => n.attrs.find( ( [ k ] ) => 'class' === k )?.[ 1 ] || [];
const textOf = ( n ) => ( undefined !== n.text ? n.text : n.children.map( textOf ).join( '' ) );
const MF2_ROOT = /^h-[a-z0-9]+(-[a-z0-9]+)*$/;
const MF2_PROP = /^(p|u|dt|e)-[a-z0-9]+(-[a-z0-9]+)*$/;

function mf2Value( prefix, n ) {
	const a = ( k ) => n.attrs.find( ( [ name ] ) => name === k )?.[ 1 ];
	const value = {
		p: () => a( 'title' ) ?? a( 'alt' ) ?? a( 'value' ),
		u: () => a( 'href' ) ?? a( 'src' ) ?? a( 'data' ) ?? a( 'value' ),
		dt: () => a( 'datetime' ) ?? a( 'value' ),
		e: () => undefined,
	}[ prefix ]();
	return ( value ?? textOf( n ) ).replace( /\s+/g, ' ' ).trim();
}

// h-* items with their p-/u-/dt-/e- properties (e-* as text), enough to tell whether two renders publish the same mf2.
export function mf2Items( tree ) {
	const items = [];
	const walk = ( el, item ) => {
		for ( const c of el.children ) {
			if ( ! c.tag ) {
				continue;
			}
			const cls = classesOf( c );
			const roots = cls.filter( ( t ) => MF2_ROOT.test( t ) ).sort();
			const props = cls.filter( ( t ) => MF2_PROP.test( t ) ).sort();
			if ( roots.length ) {
				const sub = { type: roots, properties: {} };
				walk( c, sub );
				if ( item && props.length ) {
					props.forEach( ( p ) => ( item.properties[ p ] ||= [] ).push( sub ) );
				} else if ( item ) {
					( item.children ||= [] ).push( sub );
				} else {
					items.push( sub );
				}
				continue;
			}
			if ( item ) {
				props.forEach( ( p ) => ( item.properties[ p ] ||= [] ).push( mf2Value( p.split( '-' )[ 0 ], c ) ) );
			}
			walk( c, item );
		}
	};
	walk( tree, null );
	return items;
}

// Computed-style dumps are { props: [names], rows: [[elementKey, pseudo, [values]]] }.
export function diffStyles( a, b, limit = 40 ) {
	const diffs = [];
	let count = 0;
	const add = ( d ) => {
		count++;
		if ( diffs.length < limit ) {
			diffs.push( d );
		}
	};
	const at = new Map( b.props.map( ( p, i ) => [ p, i ] ) );
	const rows = new Map( b.rows.map( ( [ key, pseudo, values ] ) => [ `${ key }${ pseudo }`, values ] ) );
	const seen = new Set();
	for ( const [ key, pseudo, values ] of a.rows ) {
		const id = `${ key }${ pseudo }`;
		seen.add( id );
		const other = rows.get( id );
		if ( ! other ) {
			add( { element: id, what: 'missing at head' } );
			continue;
		}
		a.props.forEach( ( prop, i ) => {
			const j = at.get( prop );
			const head = undefined === j ? undefined : other[ j ];
			if ( values[ i ] !== head ) {
				add( { element: id, prop, base: values[ i ], head: head ?? null } );
			}
		} );
	}
	for ( const [ key, pseudo ] of b.rows ) {
		if ( ! seen.has( `${ key }${ pseudo }` ) ) {
			add( { element: `${ key }${ pseudo }`, what: 'new at head' } );
		}
	}
	return { count, diffs };
}

// A screenshot pair passes when it is byte-identical or no worse than the same-CSS controls of either run.
export function withinControl( pair, controls ) {
	const bound = {
		count: Math.max( 0, ...controls.map( ( c ) => c.count ) ),
		maxDelta: Math.max( 0, ...controls.map( ( c ) => c.maxDelta ) ),
	};
	return { ok: pair.count <= bound.count && pair.maxDelta <= bound.maxDelta, bound };
}

// Step 6: the head may load the same theme handles or fewer, never one the baseline didn't.
export function handleChange( base, head ) {
	if ( base.status !== head.status ) {
		return `HTTP ${ base.status } at baseline, ${ head.status } at head`;
	}
	const added = [ ...( head.link || [] ).filter( ( x ) => ! ( base.link || [] ).includes( x ) ), ...( head.inline || [] ).filter( ( x ) => ! ( base.inline || [] ).includes( x ) ) ];
	return added.length ? `the head adds ${ added.join( ', ' ) }` : null;
}

const TIMESTAMP = /\b\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?|\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{2} (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4} \d{2}:\d{2}:\d{2} [+-]\d{4}/g;
export const normalizeTimestamps = ( s ) => s.replace( TIMESTAMP, '<time>' );

// R-N12 (m2), written against the DOM API so the browser runs it on DOMParser documents: every element the
// prefixed form matches on a view sits in the artifact root (closest), and on the two-kind fixture the
// prefix-free form matches nothing the prefixed form doesn't (matches). Self-contained: it is serialized into the page.
export function classifyM2( views, fixtures, pair, root ) {
	const matchable = ( s ) =>
		s.replace( /::?(?:before|after|marker|placeholder|selection|first-line|first-letter|backdrop|file-selector-button)\b|::-webkit-[\w-]+|:(?:hover|focus-visible|focus-within|focus|active|visited|link|target)(?![\w-])/g, '' );
	const describe = ( el ) => el.tagName.toLowerCase() + ( el.id ? `#${ el.id }` : '' ) + [ ...el.classList ].slice( 0, 3 ).map( ( c ) => `.${ c }` ).join( '' );
	const out = { prefixed: pair.prefixed, bare: pair.bare, matched: 0, outside: [], widened: [], verdict: 'in-card', reason: null };
	const prefixed = matchable( pair.prefixed );
	const bare = matchable( pair.bare );
	try {
		for ( const { name, doc } of views ) {
			for ( const el of doc.querySelectorAll( prefixed ) ) {
				out.matched++;
				if ( ! el.closest( root ) ) {
					out.outside.push( `${ name }: ${ describe( el ) }` );
				}
			}
		}
		for ( const { name, doc } of fixtures ) {
			for ( const el of doc.querySelectorAll( bare ) ) {
				if ( ! el.matches( prefixed ) ) {
					out.widened.push( `${ name }: ${ describe( el ) }` );
				}
			}
		}
	} catch ( e ) {
		return { ...out, verdict: 'stays', reason: `m2-unmatchable: ${ e.message }` };
	}
	if ( out.outside.length ) {
		return { ...out, verdict: 'stays', reason: 'm2-outside' };
	}
	if ( out.widened.length ) {
		return { ...out, verdict: 'stays', reason: 'm2-widens' };
	}
	if ( ! out.matched ) {
		return { ...out, verdict: 'stays', reason: 'm2-no-match' };
	}
	return out;
}

const pngSize = ( bytes ) => [ bytes.readUInt32BE( 16 ), bytes.readUInt32BE( 20 ) ];

function parseArgs( argv ) {
	const [ mode, ...rest ] = argv;
	const o = { mode, expect: [], style: [] };
	for ( let i = 0; i < rest.length; i++ ) {
		const m = /^--([a-z0-9-]+)$/.exec( rest[ i ] );
		if ( ! m || undefined === rest[ i + 1 ] ) {
			throw new ToolingError( `bad argument ${ JSON.stringify( rest[ i ] ) }` );
		}
		const v = rest[ ++i ];
		if ( Array.isArray( o[ m[ 1 ] ] ) ) {
			o[ m[ 1 ] ].push( v );
		} else {
			o[ m[ 1 ] ] = v;
		}
	}
	return o;
}

const need = ( o, ...names ) => {
	const missing = names.filter( ( n ) => ! o[ n ] );
	if ( missing.length ) {
		throw new ToolingError( `${ o.mode } needs ${ missing.map( ( n ) => `--${ n }` ).join( ', ' ) }` );
	}
};

function readManifest( dir ) {
	const file = path.join( dir, 'manifest.json' );
	try {
		return JSON.parse( fs.readFileSync( file, 'utf8' ) );
	} catch ( e ) {
		throw new ToolingError( `cannot read ${ file }: ${ e.code || e.message }` );
	}
}

async function fetchPage( url ) {
	const r = await fetch( url, { redirect: 'manual', signal: AbortSignal.timeout( 60000 ) } );
	return { status: r.status, location: r.headers.get( 'location' ), body: await r.text() };
}

// The seeding administrator's render, server side: no login form, no credentials, admin bar off.
function adminRender( studio, origin, uri ) {
	const php = `
$uri = base64_decode( '${ Buffer.from( uri ).toString( 'base64' ) }' );
$ids = get_users( array( 'role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $ids ) { fwrite( STDERR, "no administrator\\n" ); exit( 3 ); }
wp_set_current_user( (int) $ids[0] );
add_filter( 'show_admin_bar', '__return_false' );
add_filter( 'wp_using_themes', '__return_true' );
remove_action( 'template_redirect', 'redirect_canonical' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['HTTP_HOST'] = base64_decode( '${ Buffer.from( new URL( origin ).host ).toString( 'base64' ) }' );
$_SERVER['SERVER_NAME'] = base64_decode( '${ Buffer.from( new URL( origin ).hostname ).toString( 'base64' ) }' );
parse_str( (string) parse_url( $uri, PHP_URL_QUERY ), $_GET );
wp();
ob_start();
require ABSPATH . WPINC . '/template-loader.php';
$html = ob_get_clean();
echo "AB-STATUS:" . ( is_404() ? 404 : 200 ) . "\\n" . "AB-HTML:" . base64_encode( $html ) . "\\n";
`;
	const out = execFileSync( 'studio', [ 'wp', 'eval', php ], { cwd: studio, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024, timeout: 180000 } );
	const status = /^AB-STATUS:(\d+)$/m.exec( out );
	const html = /^AB-HTML:([A-Za-z0-9+/=]*)$/m.exec( out );
	if ( ! status || ! html ) {
		throw new Error( `studio wp eval printed no page for ${ uri }` );
	}
	return { status: Number( status[ 1 ] ), body: Buffer.from( html[ 1 ], 'base64' ).toString( 'utf8' ) };
}

function handleSets( html ) {
	const run = spawnSync( 'python3', [ '-c', 'import sys,json; sys.path.insert(0, sys.argv[1]); from handles import handle_sets; l, i = handle_sets(sys.stdin.read()); print(json.dumps({"link": l, "inline": i}))', HERE ], {
		input: html,
		encoding: 'utf8',
	} );
	if ( 0 !== run.status ) {
		throw new ToolingError( `handles.py failed: ${ run.stderr.slice( 0, 400 ) }` );
	}
	return JSON.parse( run.stdout );
}

// Runs in the page: one row per element under main, plus its ::before and ::after, across every computed property.
function dumpStyles() {
	const keyOf = ( el ) => {
		const parts = [];
		for ( let n = el; n && n.parentElement; n = n.parentElement ) {
			parts.unshift( `${ n.localName }:${ [ ...n.parentElement.children ].indexOf( n ) }` );
		}
		return parts.join( '>' );
	};
	let props = null;
	const rows = [];
	for ( const main of document.querySelectorAll( 'main' ) ) {
		for ( const el of [ main, ...main.querySelectorAll( '*' ) ] ) {
			const key = keyOf( el );
			for ( const pseudo of [ '', '::before', '::after' ] ) {
				const cs = getComputedStyle( el, pseudo || null );
				props ||= [ ...cs ];
				rows.push( [ key, pseudo, props.map( ( p ) => cs.getPropertyValue( p ) ) ] );
			}
		}
	}
	return { props: props || [], rows };
}

async function shoot( browser, origin, item, page0, out, load ) {
	const { record } = item;
	const context = await browser.newContext( { viewport: { width: record.width, height: record.height }, colorScheme: record.colorScheme, deviceScaleFactor: 1 } );
	const blocked = new Set();
	try {
		await context.addInitScript( ( value ) => {
			try {
				localStorage.setItem( 'courtneyr-theme', value );
			} catch ( e ) {}
		}, record.colorScheme );
		await context.route(
			( url ) => /^https?:$/.test( url.protocol ) && ! isLocalHost( url.hostname ),
			( route ) => {
				blocked.add( new URL( route.request().url() ).hostname );
				return route.abort();
			}
		);
		const page = await context.newPage();
		const url = origin + record.path;
		if ( 'admin' === item.auth ) {
			await page.route( url, ( route ) => route.fulfill( { status: page0.status, contentType: 'text/html; charset=UTF-8', body: page0.body } ) );
		}
		const response = await page.goto( url, { waitUntil: 'load', timeout: 60000 } );
		if ( 'fixture' !== item.role && 200 !== response?.status() ) {
			throw new Error( `the browser got HTTP ${ response?.status() } for ${ record.path }` );
		}
		const consent = page.locator( '.cmplz-close' ).first();
		if ( await consent.isVisible().catch( () => false ) ) {
			await consent.click().catch( () => {} );
		}
		await page.evaluate( async () => {
			for ( let y = 0; y < document.documentElement.scrollHeight; y += 600 ) {
				window.scrollTo( 0, y );
				await new Promise( ( r ) => setTimeout( r, 40 ) );
			}
			window.scrollTo( 0, 0 );
		} );
		await page.waitForFunction( () => [ ...document.images ].every( ( i ) => i.complete ), null, { timeout: 8000 } ).catch( () => {} );
		await page.evaluate( () => document.fonts.ready );
		await page.evaluate( () =>
			document.getAnimations().forEach( ( a ) => {
				try {
					a.finish();
				} catch {
					a.cancel();
				}
			} )
		);
		await page.waitForTimeout( 300 );
		const png = path.join( out, 'shots', `${ item.key }.${ load }.png` );
		if ( record.selector ) {
			const el = page.locator( record.selector ).first();
			if ( ! ( await el.count() ) ) {
				throw new Error( `selector not found: ${ record.selector }` );
			}
			await el.screenshot( { path: png, animations: 'disabled' } );
		} else {
			await page.screenshot( { path: png, fullPage: record.fullPage, animations: 'disabled' } );
		}
		const styles = path.join( out, 'styles', `${ item.key }.${ load }.json.gz` );
		fs.writeFileSync( styles, zlib.gzipSync( JSON.stringify( await page.evaluate( dumpStyles ) ) ) );
		const result = { png: path.relative( out, png ), sha256: sha256( fs.readFileSync( png ) ), styles: path.relative( out, styles ) };
		if ( 0 === load ) {
			const dom = path.join( out, 'dom', `${ item.key }.html` );
			fs.writeFileSync( dom, await page.evaluate( () => document.documentElement.outerHTML ) );
			result.dom = path.relative( out, dom );
		}
		return { ...result, blocked: [ ...blocked ].sort() };
	} finally {
		await context.close();
	}
}

async function capture( o ) {
	need( o, 'role', 'base-url', 'map', 'out' );
	if ( ! [ 'baseline', 'head' ].includes( o.role ) ) {
		throw new ToolingError( '--role is baseline or head' );
	}
	const origin = assertLocalBase( o[ 'base-url' ] );
	if ( 'head' === o.role && ! o[ 'map-sha256' ] ) {
		throw new ToolingError( 'a head capture needs --map-sha256, the value Procedure step 7 recorded for the frozen head map' );
	}
	const map = readVerified( o.map, o[ 'map-sha256' ] );
	let records = viewRecords( JSON.parse( map.bytes ), o.map );
	let spec = null;
	if ( o.spec ) {
		spec = readVerified( o.spec );
		records = records.concat( viewRecords( JSON.parse( spec.bytes ), o.spec ) );
	}
	const duplicates = compareRecords( records, records ).filter( ( p ) => p.includes( 'two records' ) );
	if ( duplicates.length ) {
		throw new ToolingError( duplicates.join( '; ' ) );
	}
	const expects = o.expect.map( ( f ) => ( { file: f, ...readVerified( f ) } ) );
	const posts = expects.flatMap( ( e ) => expectPosts( JSON.parse( e.bytes ), e.file ) );
	const plan = capturePlan( records, posts );
	if ( 'head' === o.role ) {
		const base = readManifest( o.baseline || '' );
		const problems = compareRecords( base.records, records );
		if ( base.harness?.sha256 !== sha256( fs.readFileSync( SELF ) ) ) {
			problems.push( 'this harness file differs from the one that took the baseline' );
		}
		const keys = ( list ) => list.sort().join( ' ' );
		if ( keys( Object.keys( base.captures ) ) !== keys( plan.map( ( i ) => i.key ) ) ) {
			problems.push( 'the head capture plan (records, twins, --expect posts) differs from the baseline capture' );
		}
		if ( problems.length ) {
			throw new ToolingError( `the head can't be compared with the baseline: ${ problems.join( '; ' ) }` );
		}
	}
	if ( plan.some( ( i ) => 'admin' === i.auth ) && ! o.studio ) {
		throw new ToolingError( 'private or password-protected --expect posts need --studio <site dir> for the administrator render' );
	}
	if ( fs.existsSync( path.join( o.out, 'manifest.json' ) ) ) {
		throw new ToolingError( `${ o.out } already holds a capture` );
	}
	const loads = Number( o.loads || 2 );
	if ( ! Number.isInteger( loads ) || loads < 2 ) {
		throw new ToolingError( '--loads is 2 or more: one capture plus at least one same-CSS control load' );
	}
	for ( const sub of [ 'html', 'shots', 'styles', 'dom', 'extra' ] ) {
		fs.mkdirSync( path.join( o.out, sub ), { recursive: true } );
	}

	const { chromium } = await import( '@playwright/test' );
	const browser = await chromium.launch();
	const captures = {};
	const pages = new Map();
	try {
		for ( const item of plan ) {
			const c = { role: item.role, auth: item.auth, record: item.record, ...( item.post ? { post: item.post, variant: item.variant } : {} ) };
			try {
				const id = `${ item.auth } ${ item.record.path }`;
				if ( ! pages.has( id ) ) {
					pages.set( id, 'admin' === item.auth ? adminRender( o.studio, origin, item.record.path ) : await fetchPage( origin + item.record.path ) );
				}
				const page0 = pages.get( id );
				c.status = page0.status;
				c.html = sha256( page0.body );
				fs.writeFileSync( path.join( o.out, 'html', `${ c.html }.html` ), page0.body );
				if ( 'fixture' !== item.role && 200 !== page0.status ) {
					throw new Error( `HTTP ${ page0.status }${ page0.location ? ` to ${ page0.location }` : '' } for ${ item.record.path }` );
				}
				c.loads = [];
				for ( let k = 0; k < loads; k++ ) {
					c.loads.push( await shoot( browser, origin, item, page0, o.out, k ) );
				}
			} catch ( e ) {
				c.error = String( e.message || e );
			}
			captures[ item.key ] = c;
			console.log( `${ c.error ? 'ERROR' : 'ok   ' } ${ item.key } ${ item.role } ${ item.auth } HTTP ${ c.status ?? '-' }${ c.error ? ` ${ c.error }` : '' }` );
		}
	} finally {
		await browser.close();
	}

	const extra = async ( name, uri ) => {
		const r = await fetchPage( origin + uri );
		fs.writeFileSync( path.join( o.out, 'extra', name ), r.body );
		return { status: r.status, file: `extra/${ name }` };
	};
	const handles = {};
	for ( const uri of [ '/', '/stream/' ] ) {
		const r = await fetchPage( origin + uri );
		handles[ uri ] = { status: r.status, ...( 200 === r.status ? handleSets( r.body ) : {} ) };
	}
	const ids = new Set( posts.map( ( p ) => p.id ) );
	for ( const c of Object.values( captures ) ) {
		if ( c.html && 'anon' === c.auth ) {
			const m = /<body[^>]*\bclass=["'][^"']*\bpostid-(\d+)\b/.exec( fs.readFileSync( path.join( o.out, 'html', `${ c.html }.html` ), 'utf8' ) );
			if ( m ) {
				ids.add( Number( m[ 1 ] ) );
			}
		}
	}
	const rest = {};
	for ( const id of [ ...ids ].sort( ( a, b ) => a - b ) ) {
		rest[ id ] = await extra( `rest-${ id }.json`, `/wp-json/wp/v2/posts/${ id }` );
	}
	const manifest = {
		harness: { file: SELF, sha256: sha256( fs.readFileSync( SELF ) ) },
		role: o.role,
		createdAt: new Date().toISOString(),
		baseUrl: origin,
		map: { path: path.resolve( o.map ), sha256: map.sha256 },
		spec: spec ? { path: path.resolve( o.spec ), sha256: spec.sha256 } : null,
		expect: expects.map( ( e ) => ( { path: path.resolve( e.file ), sha256: e.sha256 } ) ),
		loads,
		records,
		captures,
		handles,
		feed: await extra( 'feed.xml', '/feed/' ),
		rest,
	};
	fs.writeFileSync( path.join( o.out, 'manifest.json' ), JSON.stringify( manifest, null, 1 ) );
	const failed = Object.entries( captures ).filter( ( [ , c ] ) => c.error );
	console.log( `${ o.role } capture: ${ plan.length } captures (${ records.length } judged), ${ failed.length } failed, manifest ${ path.join( o.out, 'manifest.json' ) }` );
	if ( failed.length ) {
		throw new ToolingError( `${ failed.length } capture(s) failed: ${ failed.map( ( [ k ] ) => k ).join( ', ' ) }` );
	}
	return 0;
}

function pixview( pairs, dir ) {
	if ( ! pairs.length ) {
		return new Map();
	}
	fs.mkdirSync( path.join( dir, 'tabdiff' ), { recursive: true } );
	for ( const p of pairs ) {
		fs.copyFileSync( p.a, path.join( dir, 'tabdiff', `${ p.name }-src.png` ) );
		fs.copyFileSync( p.b, path.join( dir, 'tabdiff', `${ p.name }-min.png` ) );
	}
	const run = spawnSync( process.execPath, [ path.join( HERE, 'pixview.mjs' ), ...pairs.map( ( p ) => p.name ) ], { cwd: dir, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, timeout: 1800000 } );
	const results = new Map();
	for ( const m of run.stdout.matchAll( /^(\S+) px (\d+) maxDelta (\d+)$/gm ) ) {
		results.set( m[ 1 ], { count: Number( m[ 2 ] ), maxDelta: Number( m[ 3 ] ) } );
	}
	const missing = pairs.filter( ( p ) => ! results.has( p.name ) );
	if ( 0 !== run.status || missing.length ) {
		throw new ToolingError( `pixview.mjs exit ${ run.status }, no result for ${ missing.map( ( p ) => p.name ).join( ', ' ) || 'none' }: ${ ( run.stderr || '' ).slice( 0, 400 ) }` );
	}
	return results;
}

function parseStyles( list ) {
	if ( ! list.length ) {
		throw new ToolingError( 'compare needs at least one --style <slug>[=<root class>]' );
	}
	return list.map( ( s ) => {
		const [ slug, root = slug ] = s.split( '=' );
		if ( ! /^[a-z0-9-]+$/.test( slug ) || ! /^[a-z0-9_-]+$/i.test( root ) ) {
			throw new ToolingError( `--style ${ s } is not <slug>[=<root class>]` );
		}
		return { slug, root };
	} );
}

async function compare( o ) {
	need( o, 'baseline', 'head' );
	const styles = parseStyles( o.style );
	const base = readManifest( o.baseline );
	const head = readManifest( o.head );
	const outDir = o.out || path.join( o.head, 'compare' );
	const problems = preflight( base, head );
	for ( const [ side, m ] of [ [ 'baseline', base ], [ 'head', head ] ] ) {
		for ( const f of [ m.map, m.spec, ...( m.expect || [] ) ].filter( Boolean ) ) {
			try {
				readVerified( f.path, f.sha256 );
			} catch ( e ) {
				problems.push( `${ side }: ${ e.message }` );
			}
		}
	}
	const finish = ( verdict ) => {
		fs.mkdirSync( outDir, { recursive: true } );
		fs.writeFileSync( path.join( outDir, 'verdict.json' ), JSON.stringify( verdict, null, 1 ) );
		console.log( `RESULT ${ verdict.status } (${ path.join( outDir, 'verdict.json' ) })` );
		return { clean: 0, fail: 1, tooling: 2 }[ verdict.status ];
	};
	if ( problems.length ) {
		problems.forEach( ( p ) => console.log( `TOOLING ${ p }` ) );
		return finish( { status: 'tooling', problems } );
	}

	const trees = new Map();
	const tree = ( dir, sha ) => {
		if ( ! trees.has( sha ) ) {
			trees.set( sha, normalizeHtml( fs.readFileSync( path.join( dir, 'html', `${ sha }.html` ), 'utf8' ) ) );
		}
		return trees.get( sha );
	};
	const dump = ( dir, file ) => JSON.parse( zlib.gunzipSync( fs.readFileSync( path.join( dir, file ) ) ) );
	const views = [];
	const pairs = [];
	for ( const key of Object.keys( base.captures ) ) {
		const a = base.captures[ key ];
		const b = head.captures[ key ];
		const ta = tree( o.baseline, a.html );
		const tb = tree( o.head, b.html );
		const mfa = JSON.stringify( mf2Items( ta ) );
		const mfb = JSON.stringify( mf2Items( tb ) );
		const v = {
			key,
			role: a.role,
			auth: a.auth,
			http: a.status === b.status ? null : `HTTP ${ a.status } at baseline, ${ b.status } at head`,
			dom: diffDom( ta, tb, styles ),
			mf2: mfa === mfb ? null : { base: mfa.slice( 0, 2000 ), head: mfb.slice( 0, 2000 ) },
			style: diffStyles( dump( o.baseline, a.loads[ 0 ].styles ), dump( o.head, b.loads[ 0 ].styles ) ),
			styleControl: {
				baseline: diffStyles( dump( o.baseline, a.loads[ 0 ].styles ), dump( o.baseline, a.loads[ 1 ].styles ) ).count,
				head: diffStyles( dump( o.head, b.loads[ 0 ].styles ), dump( o.head, b.loads[ 1 ].styles ) ).count,
			},
		};
		const shots = ( dir, c ) => c.loads.map( ( l ) => ( { file: path.join( dir, l.png ), sha256: l.sha256 } ) );
		const [ sa, sb ] = [ shots( o.baseline, a ), shots( o.head, b ) ];
		const sizeA = pngSize( fs.readFileSync( sa[ 0 ].file ) );
		const sizeB = pngSize( fs.readFileSync( sb[ 0 ].file ) );
		if ( sa[ 0 ].sha256 === sb[ 0 ].sha256 ) {
			v.pixel = { ok: true, identical: true };
		} else if ( sizeA.join( 'x' ) !== sizeB.join( 'x' ) ) {
			v.pixel = { ok: false, size: { base: sizeA, head: sizeB } };
		} else {
			const name = key.replace( /[^\w.-]/g, '_' );
			v.pixel = { pending: { pair: `${ name }`, controls: [] } };
			pairs.push( { name, a: sa[ 0 ].file, b: sb[ 0 ].file } );
			for ( const [ side, s ] of [ [ 'base', sa ], [ 'head', sb ] ] ) {
				s.slice( 1 ).forEach( ( other, k ) => {
					if ( other.sha256 !== s[ 0 ].sha256 ) {
						const ctl = `${ name }.ctl-${ side }-${ k + 1 }`;
						v.pixel.pending.controls.push( ctl );
						pairs.push( { name: ctl, a: s[ 0 ].file, b: other.file } );
					}
				} );
			}
		}
		views.push( v );
	}
	const measured = pixview( pairs, path.join( outDir, 'pix' ) );
	for ( const v of views ) {
		if ( v.pixel.pending ) {
			const { pair, controls } = v.pixel.pending;
			const p = measured.get( pair );
			const c = controls.map( ( n ) => measured.get( n ) );
			v.pixel = { ...withinControl( p, c ), pair: p, controls: c, view: `pix/view-${ pair }.png` };
		}
		v.ok = ! v.http && ! v.dom.length && ! v.mf2 && ! v.style.count && v.pixel.ok;
		console.log( `${ v.ok ? 'PASS' : 'FAIL' } ${ v.key } [${ v.role } ${ v.auth }] dom ${ v.dom.length } style ${ v.style.count } (control ${ v.styleControl.baseline }/${ v.styleControl.head }) pixel ${ v.pixel.identical ? 'identical' : JSON.stringify( v.pixel.pair || v.pixel.size ) }${ v.pixel.bound ? ` bound ${ JSON.stringify( v.pixel.bound ) }` : '' }${ v.http ? ` ${ v.http }` : '' }${ v.mf2 ? ' mf2 differs' : '' }` );
	}
	const handles = Object.keys( base.handles ).map( ( uri ) => ( { uri, change: handleChange( base.handles[ uri ], head.handles[ uri ] || {} ) } ) );
	const same = ( a, b ) => a.status === b?.status && normalizeTimestamps( fs.readFileSync( path.join( o.baseline, a.file ), 'utf8' ) ) === normalizeTimestamps( fs.readFileSync( path.join( o.head, b.file ), 'utf8' ) );
	const feed = same( base.feed, head.feed );
	const ids = [ ...new Set( [ ...Object.keys( base.rest ), ...Object.keys( head.rest ) ] ) ];
	const rest = ids.map( ( id ) => ( { id, same: !! base.rest[ id ] && same( base.rest[ id ], head.rest[ id ] ) } ) );
	handles.forEach( ( h ) => console.log( `${ h.change ? 'FAIL' : 'PASS' } handles ${ h.uri }${ h.change ? ` ${ h.change }` : '' }` ) );
	console.log( `${ feed ? 'PASS' : 'FAIL' } /feed/` );
	rest.forEach( ( r ) => console.log( `${ r.same ? 'PASS' : 'FAIL' } /wp-json/wp/v2/posts/${ r.id }` ) );
	const clean = views.every( ( v ) => v.ok ) && handles.every( ( h ) => ! h.change ) && feed && rest.every( ( r ) => r.same );
	return finish( { status: clean ? 'clean' : 'fail', styles, views, handles, feed, rest } );
}

async function classify( o ) {
	need( o, 'baseline', 'root', 'pairs' );
	const base = readManifest( o.baseline );
	let pairs;
	try {
		pairs = JSON.parse( fs.readFileSync( o.pairs, 'utf8' ) );
	} catch ( e ) {
		throw new ToolingError( `cannot read ${ o.pairs }: ${ e.code || e.message }` );
	}
	if ( ! Array.isArray( pairs ) || ! pairs.every( ( p ) => 'string' === typeof p.prefixed && 'string' === typeof p.bare ) ) {
		throw new ToolingError( `${ o.pairs } is not an array of { prefixed, bare }` );
	}
	const doms = ( pick ) =>
		Object.entries( base.captures )
			.filter( ( [ , c ] ) => pick( c ) && c.loads?.[ 0 ]?.dom )
			.map( ( [ name, c ] ) => ( { name, html: fs.readFileSync( path.join( o.baseline, c.loads[ 0 ].dom ), 'utf8' ) } ) );
	const views = doms( ( c ) => 'judged' === c.role || 'twin' === c.role );
	const fixtures = doms( ( c ) => 'fixture' === c.role && 'two-kind' === c.variant && 'anon' === c.auth );
	if ( ! views.length || ! fixtures.length ) {
		throw new ToolingError( `classify needs judged views (${ views.length }) and a two-kind fixture capture (${ fixtures.length }) in ${ o.baseline }` );
	}
	const { chromium } = await import( '@playwright/test' );
	const browser = await chromium.launch();
	let results;
	try {
		const page = await browser.newPage( { javaScriptEnabled: true } );
		results = await page.evaluate(
			( { src, views: v, fixtures: f, pairs: p, root } ) => {
				const run = ( 0, eval )( `(${ src })` );
				const parse = ( x ) => ( { name: x.name, doc: new DOMParser().parseFromString( x.html, 'text/html' ) } );
				const vs = v.map( parse );
				const fs2 = f.map( parse );
				return p.map( ( pair ) => run( vs, fs2, pair, root ) );
			},
			{ src: classifyM2.toString(), views, fixtures, pairs, root: o.root }
		);
	} finally {
		await browser.close();
	}
	const counts = {};
	results.forEach( ( r ) => ( counts[ r.reason || 'in-card' ] = ( counts[ r.reason || 'in-card' ] || 0 ) + 1 ) );
	const report = { root: o.root, views: views.map( ( v ) => v.name ), fixtures: fixtures.map( ( f ) => f.name ), counts, results };
	if ( o.out ) {
		fs.writeFileSync( o.out, JSON.stringify( report, null, 1 ) );
	}
	console.log( `classified ${ results.length } selector pairs over ${ views.length } views and ${ fixtures.length } two-kind fixture(s): ${ JSON.stringify( counts ) }` );
	return 0;
}

async function main( argv ) {
	const o = parseArgs( argv );
	const modes = { capture, compare, classify };
	if ( ! modes[ o.mode ] ) {
		throw new ToolingError( 'usage: artifact-ab.mjs capture|compare|classify ... (see the header of this file)' );
	}
	return modes[ o.mode ]( o );
}

if ( process.argv[ 1 ] && path.resolve( process.argv[ 1 ] ) === SELF ) {
	main( process.argv.slice( 2 ) ).then(
		( code ) => process.exit( code ),
		( e ) => {
			console.error( `TOOLING ${ e instanceof ToolingError ? e.message : e.stack }` );
			process.exit( 2 );
		}
	);
}
