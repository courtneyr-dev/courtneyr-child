/**
 * Theme toggle — Interactivity API view module.
 *
 * Binds reactive toggle behavior to any element with
 * data-wp-interactive="courtneyr/theme-toggle" in the rendered markup.
 *
 * Markup contract (in a pattern, template part, or block):
 *
 *   <div data-wp-interactive="courtneyr/theme-toggle">
 *     <button
 *       data-wp-on--click="actions.toggle"
 *       data-wp-bind--aria-pressed="state.isDark"
 *       data-wp-text="state.label"
 *     >Light</button>
 *   </div>
 *
 * State is rehydrated from localStorage by the inline pre-paint script
 * in inc/interactivity.php. This module only handles the click action
 * and the persisted-write side effect.
 */

import { store, getContext } from '@wordpress/interactivity';

const STORAGE_KEY = 'courtneyr-theme';

const { state, actions } = store('courtneyr/theme-toggle', {
	state: {
		get isDark() {
			return document.documentElement.getAttribute('data-theme') === 'dark';
		},
		get label() {
			return state.isDark ? 'Light mode' : 'Dark mode';
		},
	},
	actions: {
		toggle() {
			const next = state.isDark ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', next);

			try {
				localStorage.setItem(STORAGE_KEY, next);
			} catch (e) {
				// localStorage may be blocked (private browsing, third-party
				// frame). The visual toggle still works for the session.
			}
		},

		clearPreference() {
			document.documentElement.removeAttribute('data-theme');
			try {
				localStorage.removeItem(STORAGE_KEY);
			} catch (e) {
				// Same as above.
			}
		},
	},
});
