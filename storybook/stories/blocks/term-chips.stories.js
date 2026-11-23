import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Blocks/Term chips' };

export const Overview = overviewStory( 'Term chips', 't269/block-term-chips--category', 't269/block-term-chips--kind' );
export const Categories = fixtureStory( 't269/block-term-chips--category' );
export const Kinds = fixtureStory( 't269/block-term-chips--kind' );
