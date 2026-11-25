import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Pages/Stream' };

export const Overview = overviewStory( 'Stream', 't269/page-stream' );
export const Phone = fixtureStory( 't269/page-stream', { viewport: 'w390', scheme: 'light' } );
export const PhoneDark = fixtureStory( 't269/page-stream', { viewport: 'w390', scheme: 'dark' } );
export const Desktop = fixtureStory( 't269/page-stream', { viewport: 'w1280', scheme: 'light' } );
export const DesktopDark = fixtureStory( 't269/page-stream', { viewport: 'w1280', scheme: 'dark' } );
export const PhoneSystem = fixtureStory( 't269/page-stream', { viewport: 'w390', scheme: 'system' } );
export const DesktopSystem = fixtureStory( 't269/page-stream', { viewport: 'w1280', scheme: 'system' } );
