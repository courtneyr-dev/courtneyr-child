import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Blocks/Archive identity' };

export const Overview = overviewStory( 'Archive identity', 't269/block-archive-identity--kind', 't269/block-archive-identity--category' );
export const KindArchive = fixtureStory( 't269/block-archive-identity--kind' );
export const CategoryArchive = fixtureStory( 't269/block-archive-identity--category' );
