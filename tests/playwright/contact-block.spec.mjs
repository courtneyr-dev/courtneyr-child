// `CR_SPEC=contact-block.spec.mjs npx playwright test --config tests/playwright/playwright.config.mjs --project=1280-light`:
// the delegated contact-form markup, submission results and keyboard order on CR_CONTACT_PATH
// (default `/contact/`). Markup is read-only. Submission tests send real mail and run only when
// CR_CONTACT_SUBMIT=1 and CR_BASE_URL points to localhost, loopback or a `.local` host.
import { test, expect } from '@playwright/test';

const CONTACT = process.env.CR_CONTACT_PATH || '/contact/';
const target = new URL( process.env.CR_BASE_URL || 'http://localhost:8894' );
const LOCAL_TARGET = [ 'localhost', '127.0.0.1', '[::1]' ].includes( target.hostname ) || target.hostname.endsWith( '.local' );
const CAN_SUBMIT = '1' === process.env.CR_CONTACT_SUBMIT && LOCAL_TARGET;

async function visit( page ) {
	const response = await page.goto( CONTACT, { waitUntil: 'load' } );
	expect( response.status() ).toBe( 200 );
}

async function fillRequiredFields( page ) {
	await page.locator( 'input[name="cr_name"]' ).fill( 'Contact block test' );
	await page.locator( 'input[name="cr_email"]' ).fill( 'contact-block@example.test' );
	await page.locator( 'textarea[name="cr_message"]' ).fill( 'Local contact block submission test.' );
}

function skipUnlessLocalSubmit() {
	test.skip( ! CAN_SUBMIT, 'set CR_CONTACT_SUBMIT=1 and target a local site to run mail-sending tests' );
}

test( 'the contact page exposes the plugin form contract', async ( { page } ) => {
	await visit( page );
	const form = page.locator( 'form.cr-contact__form' );
	await expect( form ).toHaveCount( 1 );
	await expect( form ).toHaveAttribute( 'action', /\/wp-admin\/admin-post\.php$/ );
	await expect( form ).toHaveAttribute( 'method', /post/i );
	await expect( form.locator( 'input[type="hidden"][name="action"]' ) ).toHaveValue( 'cr_contact_send' );
	await expect( form.locator( 'input[name="cr_contact_nonce"]' ) ).toHaveValue( /.+/ );

	const honeypot = form.locator( '.cr-contact__hp[aria-hidden] input[name="cr_hp"]' );
	await expect( honeypot ).toHaveCount( 1 );
	await expect( honeypot ).toHaveAttribute( 'tabindex', '-1' );

	for ( const [ selector, name ] of [ [ 'input[name="cr_name"]', 'name' ], [ 'input[name="cr_email"]', 'email' ], [ 'textarea[name="cr_message"]', 'message' ] ] ) {
		const field = form.locator( selector );
		const id = await field.getAttribute( 'id' );
		expect( id, `${ name } field has an id` ).toBeTruthy();
		await expect( form.locator( `label[for="${ id }"]` ), `${ name } field has a label` ).toHaveCount( 1 );
	}
	await expect( form.getByRole( 'button', { name: 'Send it →', exact: true } ) ).toHaveCount( 1 );
} );

test( 'a valid nonce submits and returns the success notice', async ( { page } ) => {
	skipUnlessLocalSubmit();
	await visit( page );
	await fillRequiredFields( page );
	await page.getByRole( 'button', { name: 'Send it →', exact: true } ).click();
	await expect( page ).toHaveURL( /[?&]cr_contact=sent(?:&|#|$)/ );
	await expect( page.locator( '.cr-contact__notice--ok[role="status"]' ) ).toBeVisible();
} );

test( 'a tampered nonce returns the error notice', async ( { page } ) => {
	skipUnlessLocalSubmit();
	await visit( page );
	await fillRequiredFields( page );
	await page.locator( 'input[name="cr_contact_nonce"]' ).evaluate( ( input ) => { input.value = 'x'; } );
	await page.getByRole( 'button', { name: 'Send it →', exact: true } ).click();
	await expect( page ).toHaveURL( /[?&]cr_contact=error(?:&|#|$)/ );
	await expect( page.locator( '.cr-contact__notice--err[role="alert"]' ) ).toBeVisible();
} );

test( 'a filled honeypot pretends the submission succeeded', async ( { page } ) => {
	skipUnlessLocalSubmit();
	await visit( page );
	await fillRequiredFields( page );
	await page.locator( 'input[name="cr_hp"]' ).evaluate( ( input ) => { input.value = 'bot'; } );
	await page.getByRole( 'button', { name: 'Send it →', exact: true } ).click();
	await expect( page ).toHaveURL( /[?&]cr_contact=sent(?:&|#|$)/ );
} );

test( 'keyboard order follows the fields and browser validation blocks an empty submit', async ( { page } ) => {
	skipUnlessLocalSubmit();
	await visit( page );
	const name = page.locator( 'input[name="cr_name"]' );
	const email = page.locator( 'input[name="cr_email"]' );
	const message = page.locator( 'textarea[name="cr_message"]' );
	const send = page.getByRole( 'button', { name: 'Send it →', exact: true } );

	await name.focus();
	await expect( name ).toBeFocused();
	await page.keyboard.press( 'Tab' );
	await expect( email ).toBeFocused();
	await page.keyboard.press( 'Tab' );
	await expect( message ).toBeFocused();
	await page.keyboard.press( 'Tab' );
	await expect( send ).toBeFocused();

	const emptyUrl = page.url();
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 250 );
	expect( page.url() ).toBe( emptyUrl );

	await fillRequiredFields( page );
	await send.focus();
	await page.keyboard.press( 'Enter' );
	await expect( page ).toHaveURL( /[?&]cr_contact=sent(?:&|#|$)/ );
} );
