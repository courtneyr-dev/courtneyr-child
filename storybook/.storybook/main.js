import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { THEME_STATIC_DIR, THEME_URL_PATH } from '../capture/format.mjs';
import { FIXTURES_MOUNT } from '../stories/lib/mount.js';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const environments = fs.readdirSync( path.join( root, 'fixtures' ), { withFileTypes: true } ).filter( ( entry ) => entry.isDirectory() ).map( ( entry ) => entry.name );
const themeAssetsWhereEachSiteServesThem = environments.map( ( environment ) => ( {
	from: `../../${ THEME_STATIC_DIR }`,
	to: `/${ FIXTURES_MOUNT }/${ environment }/site${ THEME_URL_PATH }${ THEME_STATIC_DIR }`,
} ) );

/** @type { import('@storybook/html-vite').StorybookConfig } */
export default {
	stories: [ '../stories/**/*.stories.js' ],
	addons: [ '@storybook/addon-a11y', '@storybook/addon-vitest' ],
	framework: { name: '@storybook/html-vite', options: {} },
	core: { disableTelemetry: true, disableWhatsNewNotifications: true },
	staticDirs: [ { from: '../fixtures', to: `/${ FIXTURES_MOUNT }` }, ...themeAssetsWhereEachSiteServesThem ],
};
