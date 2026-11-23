import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Patterns/Sections' };

export const Overview = overviewStory( 'Sections', 't269/pattern-cr-hero-display', 't269/pattern-cr-home-newsletter-reasons', 't269/pattern-cr-home-field-notes' );
export const HeroDisplay = fixtureStory( 't269/pattern-cr-hero-display' );
export const NewsletterReasons = fixtureStory( 't269/pattern-cr-home-newsletter-reasons' );
export const FieldNotes = fixtureStory( 't269/pattern-cr-home-field-notes' );
