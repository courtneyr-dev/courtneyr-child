import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Patterns/IndieWeb' };

export const Overview = overviewStory( 'IndieWeb', 't269/pattern-cr-hcard', 't269/pattern-post-format-quote' );
export const RepresentativeHCard = fixtureStory( 't269/pattern-cr-hcard' );
export const QuotePost = fixtureStory( 't269/pattern-post-format-quote' );
