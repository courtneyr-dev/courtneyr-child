import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Pages/Kind archive (Read)' };

export const Overview = overviewStory( 'Kind archive (Read)', 't269/page-kind-archive' );
export const Phone = fixtureStory( 't269/page-kind-archive', { viewport: 'w390', scheme: 'light' } );
export const PhoneDark = fixtureStory( 't269/page-kind-archive', { viewport: 'w390', scheme: 'dark' } );
export const Desktop = fixtureStory( 't269/page-kind-archive', { viewport: 'w1280', scheme: 'light' } );
export const DesktopDark = fixtureStory( 't269/page-kind-archive', { viewport: 'w1280', scheme: 'dark' } );
export const PhoneSystem = fixtureStory( 't269/page-kind-archive', { viewport: 'w390', scheme: 'system' } );
export const DesktopSystem = fixtureStory( 't269/page-kind-archive', { viewport: 'w1280', scheme: 'system' } );
