import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Pages/Home' };

export const Overview = overviewStory( 'Home', 't269/page-home' );
export const Phone = fixtureStory( 't269/page-home', { viewport: 'w390', scheme: 'light' } );
export const PhoneDark = fixtureStory( 't269/page-home', { viewport: 'w390', scheme: 'dark' } );
export const Desktop = fixtureStory( 't269/page-home', { viewport: 'w1280', scheme: 'light' } );
export const DesktopDark = fixtureStory( 't269/page-home', { viewport: 'w1280', scheme: 'dark' } );
