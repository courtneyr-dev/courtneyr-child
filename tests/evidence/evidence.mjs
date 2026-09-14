// `npm run evidence`: loads each route from tests/routes.mjs in Chromium at 1280 px, light and
// dark, saves a viewport screenshot and console errors, and runs axe-core's default rule set.
//
// Exit 1: a route has a serious or critical axe violation (the gate).
// Exit 2: no gating axe violation, but a route did not load with HTTP 200.
// Moderate and minor violations are listed in the table and summary.json and do not fail the run yet.
//
// Adapted from the SG-10 accessibility harness in Courtney's vault,
// 1. Projects/courtneyr.dev/homepage-newsletter-field-notes/evidence/implementation/harness/:
// a11y-common.mjs (route list, safeGoto, settle), a11y-axe-matrix.mjs (axe run and the
// serious/critical pass rule) and shoot.mjs (console capture, dark-mode init script).
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { BASE_URL, ROUTE_NAMES, assertLocalTarget, resolveRoute } from '../routes.mjs';

assertLocalTarget();

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const axeSource = fs.readFileSync( createRequire( import.meta.url ).resolve( 'axe-core/axe.min.js' ), 'utf8' );
const stamp = new Date().toISOString().replace( /[:.]/g, '-' );
const out = process.env.CR_EVIDENCE_OUT || path.join( root, 'tests', 'output', 'evidence', stamp );
fs.mkdirSync( out, { recursive: true } );

async function safeGoto( page, url ) {
	try {
		const response = await page.goto( url, { waitUntil: 'load', timeout: 90000 } );
		return { status: response ? response.status() : null };
	} catch ( error ) {
		return { status: null, error: String( error ).slice( 0, 160 ) };
	}
}

async function settle( page ) {
	await page.waitForTimeout( 800 );
	await page.evaluate( () => document.fonts && document.fonts.ready ).catch( () => {} );
}

const routes = [];
for ( const name of ROUTE_NAMES ) {
	routes.push( [ name, await resolveRoute( name ) ] );
}

const browser = await chromium.launch();
const rows = [];
for ( const scheme of [ 'light', 'dark' ] ) {
	const context = await browser.newContext( { viewport: { width: 1280, height: 800 }, colorScheme: scheme } );
	await context.addInitScript( ( value ) => {
		try {
			localStorage.setItem( 'courtneyr-theme', value );
		} catch ( e ) {}
	}, scheme );
	for ( const [ name, route ] of routes ) {
		if ( ! route ) {
			rows.push( { route: name, scheme, path: null, status: null, error: 'no path found', critical: 0, serious: 0, moderate: 0, minor: 0, gating: [], consoleErrors: [] } );
			continue;
		}
		const page = await context.newPage();
		const consoleErrors = [];
		page.on( 'console', ( m ) => m.type() === 'error' && consoleErrors.push( m.text().slice( 0, 200 ) ) );
		page.on( 'pageerror', ( e ) => consoleErrors.push( `pageerror: ${ String( e ).slice( 0, 200 ) }` ) );
		const nav = await safeGoto( page, BASE_URL + route );
		await settle( page );
		await page.screenshot( { path: path.join( out, `${ name }-1280-${ scheme }.png` ) } ).catch( () => {} );
		const theme = await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ).catch( () => null );
		await page.addScriptTag( { content: axeSource } ).catch( () => {} );
		const axe = await page
			.evaluate( async () => {
				const result = await window.axe.run( document );
				return {
					version: window.axe.version,
					violations: result.violations.map( ( v ) => ( {
						id: v.id,
						impact: v.impact,
						help: v.help,
						count: v.nodes.length,
						nodes: v.nodes.slice( 0, 3 ).map( ( n ) => ( { target: n.target.join( ' ' ), html: n.html.slice( 0, 160 ) } ) ),
					} ) ),
				};
			} )
			.catch( ( error ) => ( { error: String( error ).slice( 0, 160 ) } ) );
		const violations = axe.violations ?? [];
		const byImpact = ( impact ) => violations.filter( ( v ) => v.impact === impact );
		rows.push( {
			route: name,
			scheme,
			path: route,
			status: nav.status,
			error: nav.error ?? axe.error,
			theme,
			axeVersion: axe.version,
			critical: byImpact( 'critical' ).length,
			serious: byImpact( 'serious' ).length,
			moderate: byImpact( 'moderate' ).length,
			minor: byImpact( 'minor' ).length,
			gating: [ ...byImpact( 'critical' ), ...byImpact( 'serious' ) ].map( ( v ) => `${ v.id } x${ v.count }` ),
			consoleErrors,
			violations,
		} );
		await page.close();
	}
	await context.close();
}
await browser.close();

fs.writeFileSync( path.join( out, 'summary.json' ), JSON.stringify( { base: BASE_URL, generated: new Date().toISOString(), rows }, null, 1 ) );

console.log( `Base ${ BASE_URL }, axe-core ${ rows.find( ( r ) => r.axeVersion )?.axeVersion ?? 'n/a' }, output ${ path.relative( root, out ) }\n` );
console.log( [ 'route', 'scheme', 'path', 'status', 'theme', 'critical', 'serious', 'moderate', 'minor', 'console', 'gating' ].join( '\t' ) );
for ( const r of rows ) {
	console.log( [ r.route, r.scheme, r.path, r.status ?? r.error, r.theme, r.critical, r.serious, r.moderate, r.minor, r.consoleErrors.length, r.gating.join( ', ' ) || '-' ].join( '\t' ) );
}

const axeFailed = rows.filter( ( r ) => r.critical || r.serious );
const loadFailed = rows.filter( ( r ) => r.status !== 200 || r.error );
if ( loadFailed.length ) {
	console.log( `\n${ loadFailed.length } route runs did not load with HTTP 200: ${ loadFailed.map( ( r ) => `${ r.route }/${ r.scheme } ${ r.path } ${ r.status ?? r.error }` ).join( '; ' ) }` );
}
if ( axeFailed.length ) {
	console.log( `\nAxe gate FAILED: ${ axeFailed.length } route runs have serious or critical violations.` );
	process.exit( 1 );
}
console.log( '\nAxe gate passed: no serious or critical violations. Moderate and minor violations are reported, not gating.' );
process.exit( loadFailed.length ? 2 : 0 );
