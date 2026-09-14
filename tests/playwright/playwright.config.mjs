// `npm run captures`: five routes at 390 and 1280 px, light and dark, saved to
// tests/output/captures/. Needs a running site, so it stays out of CI (docs/testing.md).
import { defineConfig } from '@playwright/test';
import { BASE_URL, assertLocalTarget } from '../routes.mjs';

assertLocalTarget();

export default defineConfig( {
	testDir: '.',
	testMatch: 'captures.spec.mjs',
	outputDir: '../output/test-results',
	reporter: [ [ 'list' ] ],
	fullyParallel: true,
	workers: 4,
	retries: 0,
	timeout: 120000,
	use: { baseURL: BASE_URL, browserName: 'chromium', deviceScaleFactor: 1 },
	projects: [ 390, 1280 ].flatMap( ( width ) =>
		[ 'light', 'dark' ].map( ( colorScheme ) => ( {
			name: `${ width }-${ colorScheme }`,
			use: { viewport: { width, height: width === 390 ? 844 : 800 }, colorScheme },
		} ) )
	),
} );
