import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Blocks/Contact form' };

export const Overview = overviewStory( 'Contact form', 't269/block-contact-form--visitor', 't269/block-contact-form--editor' );
export const VisitorWithoutThePlugin = fixtureStory( 't269/block-contact-form--visitor' );
export const EditorWithoutThePlugin = fixtureStory( 't269/block-contact-form--editor' );
