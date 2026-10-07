// `CR_SPEC=checkin-fidelity.spec.mjs`: PKIW #224 check-in pages against the approved
// round 2 mockups: the archive's title, one time per check-in, and the Stream card's
// order. Read-only GETs; needs a running site (CR_BASE_URL, CR_ALLOW_REMOTE=1 and
// CR_USER_AGENT for a Pantheon sandbox). Paths default to the local `_cr_seed_224`
// fixtures and can be pointed elsewhere with the CR_*_PATH variables.
import { test, expect } from '@playwright/test';

const CHECKIN_ARCHIVE = process.env.CR_CHECKIN_ARCHIVE_PATH || '/kind/checkin/';
const CHECKIN_MONTH = process.env.CR_CHECKIN_MONTH_QUERY || '?monthnum=7'; // narrows the archive to July (local fixtures 576 and 583)
const CHECKIN_SINGLES = ( process.env.CR_CHECKIN_SINGLE_PATHS || '/2026/09/12/evening-walk/,/2026/08/20/train-day/,/2026/07/20/quiet-afternoon/,/2026/08/09/checked-in-at-hidden-venue/' ).split( ',' ); // public, approximate, private, private with a generated title
const CHECKIN_STREAMS = ( process.env.CR_CHECKIN_STREAM_PATHS || '/stream/,/stream/?query-1-page=2,/stream/?query-1-page=3' ).split( ',' ); // Stream pages that show public, approximate and private check-ins

const MONTHS = [ 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC' ];

async function archiveTitle( page ) {
	const h1 = page.locator( 'main h1.cr-archive__title' );
	return {
		text: ( await h1.locator( '.cr-cutout__text' ).textContent() ).trim(),
		tiles: ( await h1.locator( '.cr-cutout__tile' ).allTextContents() ).join( '' ),
	};
}

// Core's get_the_archive_title() tests is_month() before is_tax(), so a month
// query on the check-in archive titled the page "July 2026". Mockup 05 keeps
// "Check-in"; the month only narrows the list.
test( 'a month-filtered check-in archive keeps the term name as its title and tiles (PKIW #224)', async ( { page } ) => {
	const plain = await page.goto( CHECKIN_ARCHIVE, { waitUntil: 'load' } );
	test.skip( plain.status() === 404, `no check-in archive at ${ CHECKIN_ARCHIVE }` );
	const expected = await archiveTitle( page );

	const response = await page.goto( CHECKIN_ARCHIVE + CHECKIN_MONTH, { waitUntil: 'load' } );
	test.skip( response.status() === 404, `no check-ins at ${ CHECKIN_ARCHIVE + CHECKIN_MONTH }` );
	const filtered = await archiveTitle( page );
	expect( filtered.text ).toBe( expected.text );
	expect( filtered.tiles, 'the same tiles as the unfiltered archive' ).toBe( expected.tiles );

	const dates = await page.locator( '.pkiw-checkin-archive__date' ).allTextContents();
	expect( dates.length, 'the month still filters the list' ).toBeGreaterThan( 0 );
	for ( const date of dates ) {
		expect( date ).toMatch( /^July / );
	}
} );

// The editor saves checkinAt with no offset, a site wall-clock time. The card
// read it that way and the journal footer read it as UTC, so an America/New_York
// site printed 6:30 pm on the card and 2:30 pm in the footer.
test( 'a check-in single prints one time on its card, its journal footer and its stamps (PKIW #224)', async ( { page } ) => {
	let checked = 0;
	for ( const path of CHECKIN_SINGLES ) {
		const response = await page.goto( path, { waitUntil: 'load' } );
		if ( response.status() === 404 ) {
			continue;
		}
		const card = page.locator( 'article.k-checkin .pk-meta time.dt-published' );
		if ( 0 === await card.count() ) {
			continue;
		}
		checked++;
		const cardTime = await card.first().getAttribute( 'datetime' );
		const footer = page.locator( '.cr-journal__meta-item--time time' );
		expect( await footer.getAttribute( 'datetime' ), `${ path }: footer and card` ).toBe( cardTime );

		const [ year, month, day ] = cardTime.slice( 0, 10 ).split( '-' );
		const stampDate = `${ day } ${ MONTHS[ Number( month ) - 1 ] } ${ year }`;
		const stamped = ( await page.locator( 'main .cr-passport__stamps svg .cr-stamp__small' ).allTextContents() ).map( ( text ) => text.trim() );
		expect( stamped.length, `${ path }: stamps` ).toBeGreaterThan( 0 );
		for ( const text of stamped ) {
			expect( text, `${ path }: stamp date` ).toMatch( new RegExp( `^${ stampDate }( · \\S+)?$` ) );
		}
	}
	test.skip( 0 === checked, 'no check-in single with a check-in time' );
} );

// Mockup 13: the kind label, the map thumbnail when there is one, the date strip,
// then the post title, in every privacy state. The DOM carries that order, so a
// screen reader meets it as a sighted reader does.
test( 'every check-in card on the Stream reads label, map, date, then title, in the DOM and on screen (PKIW #224)', async ( { page } ) => {
	let checked = 0;
	for ( const path of CHECKIN_STREAMS ) {
		await page.goto( path, { waitUntil: 'load' } );
		const cards = await page.locator( 'article.cr-passport' ).evaluateAll( ( els ) => els.filter( ( el ) => el.querySelector( '.cr-passport__title' ) ).map( ( el ) => {
			const body = el.querySelector( '.pk-body' );
			const parts = [ ...body.children ].filter( ( child ) => child.getClientRects().length > 0 ).map( ( child ) => {
				if ( child.matches( '.pk-kindlabel' ) ) {
					return 'label';
				}
				if ( child.matches( '.cr-passport__thumb' ) ) {
					return 'map';
				}
				if ( child.querySelector( 'time.dt-published' ) ) {
					return 'date';
				}
				return child.matches( '.cr-passport__title' ) ? 'title' : 'other';
			} );
			const top = ( selector ) => body.querySelector( selector ).getBoundingClientRect().top;
			return {
				title: el.querySelector( '.cr-passport__title' ).textContent.trim(),
				lead: parts.slice( 0, parts.indexOf( 'title' ) + 1 ).join( ' > ' ),
				dateAbove: top( ':scope > :has(time.dt-published)' ) < top( '.cr-passport__title' ),
			};
		} ) );
		for ( const card of cards ) {
			checked++;
			expect( card.lead, `${ path } ${ card.title }` ).toMatch( /^label > (map > )?date > title$/ );
			expect( card.dateAbove, `${ path } ${ card.title }: the date sits above the title` ).toBe( true );
		}
	}
	test.skip( 0 === checked, 'no check-in card with a post title on the Stream pages' );
} );
