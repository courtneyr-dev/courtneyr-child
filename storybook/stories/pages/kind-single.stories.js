import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Pages/Kind single (Watch)' };

export const Overview = overviewStory( 'Kind single (Watch)', 't269/page-kind-single' );
export const Phone = fixtureStory( 't269/page-kind-single', { viewport: 'w390', scheme: 'light' } );
export const PhoneDark = fixtureStory( 't269/page-kind-single', { viewport: 'w390', scheme: 'dark' } );
export const Desktop = fixtureStory( 't269/page-kind-single', { viewport: 'w1280', scheme: 'light' } );
export const DesktopDark = fixtureStory( 't269/page-kind-single', { viewport: 'w1280', scheme: 'dark' } );
