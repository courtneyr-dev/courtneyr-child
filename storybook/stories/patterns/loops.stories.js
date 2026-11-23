import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Patterns/Loops and directories' };

export const Overview = overviewStory( 'Loops and directories', 't269/pattern-cr-browse-all', 't269/pattern-cr-read-shelf-loop' );
export const BrowseAll = fixtureStory( 't269/pattern-cr-browse-all' );
export const ReadShelf = fixtureStory( 't269/pattern-cr-read-shelf-loop' );
