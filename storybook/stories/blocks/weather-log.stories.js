import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Blocks/Weather log' };

export const Overview = overviewStory( 'Weather log', 't269/block-weather-log--date-only' );
export const DateOnly = fixtureStory( 't269/block-weather-log--date-only' );
