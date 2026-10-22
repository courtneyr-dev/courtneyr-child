/**
 * Interactivity API store for the desktop and mobile theme controls.
 *
 * The synchronous no-flash script sets the initial data-theme value before
 * styles load. This module handles hydrated controls and keeps them in sync
 * when the no-flash script mirrors a storage change from another tab.
 */

import { store, getElement } from '@wordpress/interactivity';

const STORAGE_KEY = 'courtneyr-theme';
const VALUES = [ 'light', 'dark', 'system' ];
const ORDER = [ 'light', 'dark', 'system' ];
const STATUS_TEXT = {
	light: 'Light mode on',
	dark: 'Dark mode on',
	system: 'Following system',
};
let watching = false;

function readTheme() {
	const value = document.documentElement.getAttribute( 'data-theme' );
	return VALUES.includes( value ) ? value : 'system';
}

function apply( value ) {
	document.documentElement.setAttribute( 'data-theme', value );
	try {
		localStorage.setItem( STORAGE_KEY, value );
	} catch ( error ) {
		// Storage can be unavailable; the in-page theme still changes.
	}
	state.theme = value;
	state.announcement = STATUS_TEXT[ value ];
}

const { state } = store( 'courtneyr/theme-toggle', {
	state: {
		theme: readTheme(),
		announcement: '',
		get isActive() {
			return getElement().ref.getAttribute( 'data-theme-set' ) === state.theme;
		},
		get pressed() {
			return getElement().ref.getAttribute( 'data-theme-set' ) === state.theme ? 'true' : 'false';
		},
		get cycleLabel() {
			return 'Appearance: ' + state.theme + ' theme. Activate to change.';
		},
	},
	actions: {
		set() {
			const value = getElement().ref.getAttribute( 'data-theme-set' );
			if ( ! VALUES.includes( value ) ) {
				return;
			}
			apply( value );
		},
		cycle() {
			const index = ORDER.indexOf( state.theme );
			apply( ORDER[ ( index + 1 ) % ORDER.length ] );
		},
	},
	callbacks: {
		watch() {
			if ( watching ) {
				return;
			}
			watching = true;
			new MutationObserver( () => {
				state.theme = readTheme();
			} ).observe( document.documentElement, {
				attributes: true,
				attributeFilter: [ 'data-theme' ],
			} );
		},
	},
} );
