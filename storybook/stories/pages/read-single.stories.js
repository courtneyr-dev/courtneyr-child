import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Pages/Kind single (Read)' };

export const Overview = overviewStory( 'Kind single (Read)', 't269/page-read-single' );
export const Phone = fixtureStory( 't269/page-read-single', { viewport: 'w390', scheme: 'light' } );
export const PhoneDark = fixtureStory( 't269/page-read-single', { viewport: 'w390', scheme: 'dark' } );
export const Desktop = fixtureStory( 't269/page-read-single', { viewport: 'w1280', scheme: 'light' } );
export const DesktopDark = fixtureStory( 't269/page-read-single', { viewport: 'w1280', scheme: 'dark' } );
export const PhoneSystem = fixtureStory( 't269/page-read-single', { viewport: 'w390', scheme: 'system' } );
export const DesktopSystem = fixtureStory( 't269/page-read-single', { viewport: 'w1280', scheme: 'system' } );
