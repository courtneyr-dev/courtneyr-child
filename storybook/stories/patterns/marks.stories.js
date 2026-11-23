import { fixtureStory, overviewStory } from '../lib/fixture.js';

export default { title: 'Patterns/Cards and marks' };

export const Overview = overviewStory(
	'Cards and marks',
	't269/pattern-cr-callout-note',
	't269/pattern-cr-callout-warn',
	't269/pattern-cr-card-default',
	't269/pattern-cr-pull-quote',
	't269/pattern-cr-tape-label',
	't269/pattern-cr-stream-item',
	't269/pattern-cr-evidence-verified'
);
export const CalloutNote = fixtureStory( 't269/pattern-cr-callout-note' );
export const CalloutWarn = fixtureStory( 't269/pattern-cr-callout-warn' );
export const Card = fixtureStory( 't269/pattern-cr-card-default' );
export const PullQuote = fixtureStory( 't269/pattern-cr-pull-quote' );
export const TapeLabel = fixtureStory( 't269/pattern-cr-tape-label' );
export const StreamItem = fixtureStory( 't269/pattern-cr-stream-item' );
export const EvidenceVerified = fixtureStory( 't269/pattern-cr-evidence-verified' );
