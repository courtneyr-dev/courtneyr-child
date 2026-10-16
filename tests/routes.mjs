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

export const ROUTE_NAMES = [ 'home', 'stream', 'single-post', 'kind-archive', 'story-archive', 'play-archive', 'read-archive', 'review-archive', 'reply-archive', 'like-archive', 'bookmark-archive', 'chicken-archive', 'photo-archive', 'photo-archive-page-2', 'favorite-archive', 'favorite-archive-page-2', 'acquisition-archive', 'acquisition-archive-page-2', 'repost-archive', 'repost-archive-page-2', 'article-archive' ];

const fixed = {
	home: '/',
	stream: '/stream/',
	'kind-archive': process.env.CR_KIND_ARCHIVE_PATH || '/kind/mood/',
	'story-archive': process.env.CR_STORY_ARCHIVE_PATH || '/web-stories/',
	'play-archive': process.env.CR_PLAY_ARCHIVE_PATH || '/kind/play/',
	'read-archive': process.env.CR_READ_ARCHIVE_PATH || '/kind/read/',
	'review-archive': process.env.CR_REVIEW_ARCHIVE_PATH || '/kind/review/',
	'reply-archive': process.env.CR_REPLY_ARCHIVE_PATH || '/kind/reply/',
	'like-archive': process.env.CR_LIKE_ARCHIVE_PATH || '/kind/like/',
	'bookmark-archive': process.env.CR_BOOKMARK_ARCHIVE_PATH || '/kind/bookmark/',
	'chicken-archive': process.env.CR_CHICKEN_ARCHIVE_PATH || '/kind/chicken/',
	'photo-archive': process.env.CR_PHOTO_ARCHIVE_PATH || '/kind/photo/',
	'photo-archive-page-2': process.env.CR_PHOTO_ARCHIVE_PAGE_2_PATH || '/kind/photo/page/2/',
	'favorite-archive': process.env.CR_FAVORITE_ARCHIVE_PATH || '/kind/favorite/',
	'favorite-archive-page-2': process.env.CR_FAVORITE_ARCHIVE_PAGE_2_PATH || '/kind/favorite/page/2/',
	'acquisition-archive': process.env.CR_ACQUISITION_ARCHIVE_PATH || '/kind/acquisition/',
	'acquisition-archive-page-2': process.env.CR_ACQUISITION_ARCHIVE_PAGE_2_PATH || '/kind/acquisition/page/2/',
	'repost-archive': process.env.CR_REPOST_ARCHIVE_PATH || '/kind/repost/',
	'repost-archive-page-2': process.env.CR_REPOST_ARCHIVE_PAGE_2_PATH || '/kind/repost/page/2/',
	'article-archive': process.env.CR_ARTICLE_ARCHIVE_PATH || '/kind/article/',
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
