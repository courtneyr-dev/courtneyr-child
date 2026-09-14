// Base URL and routes shared by `npm run evidence` and `npm run captures`.
// Both send read-only GET requests and never log in.
export const BASE_URL = ( process.env.CR_BASE_URL || 'http://localhost:8894' ).replace( /\/+$/, '' );

// The default target is the disposable Studio replica. Staging and live are out of scope.
export function assertLocalTarget( base = BASE_URL ) {
	const { hostname } = new URL( base );
	const local = [ 'localhost', '127.0.0.1', '[::1]' ].includes( hostname ) || hostname.endsWith( '.local' );
	if ( ! local && process.env.CR_ALLOW_REMOTE !== '1' ) {
		throw new Error( `Refusing to run against ${ hostname }: these scripts target a local copy of the site. Set CR_ALLOW_REMOTE=1 only for a host you are cleared to test.` );
	}
}

export const ROUTE_NAMES = [ 'home', 'stream', 'single-post', 'kind-archive', 'story-archive' ];

const fixed = {
	home: '/',
	stream: '/stream/',
	'kind-archive': process.env.CR_KIND_ARCHIVE_PATH || '/kind/mood/',
	'story-archive': process.env.CR_STORY_ARCHIVE_PATH || '/web-stories/',
};

// Newest published post from the public REST API, unless CR_SINGLE_POST_PATH is set.
async function singlePostPath() {
	if ( process.env.CR_SINGLE_POST_PATH ) {
		return process.env.CR_SINGLE_POST_PATH;
	}
	const response = await fetch( `${ BASE_URL }/wp-json/wp/v2/posts?per_page=1&_fields=link` );
	if ( ! response.ok ) {
		return null;
	}
	const [ post ] = await response.json();
	return post?.link ? new URL( post.link ).pathname : null;
}

export async function resolveRoute( name ) {
	return name === 'single-post' ? singlePostPath() : fixed[ name ];
}
