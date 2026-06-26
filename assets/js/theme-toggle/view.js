/* Theme toggle — segmented control with explicit Light / Dark / System
 * buttons. Replaces the v0.5.x cycling single-button implementation.
 *
 * Each button carries data-theme-set="light|dark|system". Clicking a
 * segment writes that value to localStorage + sets data-theme on <html>,
 * which the token CSS reads to flip color values. The active segment
 * gets `is-active` for styling.
 *
 * The pre-paint no-flash script in <head> handles the first-paint
 * data-theme attribute. This file only handles user interaction +
 * sync between segments and current state.
 */
(function () {
	var STORAGE_KEY = 'courtneyr-theme';
	var VALUES      = { light: 1, dark: 1, system: 1 };
	var root        = document.documentElement;

	function getCurrent() {
		var v = root.getAttribute( 'data-theme' );
		return ( v in VALUES ) ? v : 'system';
	}

	function applyTheme( value ) {
		if ( ! ( value in VALUES ) ) return;
		root.setAttribute( 'data-theme', value );
		try { localStorage.setItem( STORAGE_KEY, value ); } catch ( e ) {}
		syncSegments( value );
	}

	var ORDER  = [ 'light', 'dark', 'system' ];
	var LABELS = { light: 'light', dark: 'dark', system: 'system' };

	function syncSegments( value ) {
		var segments = document.querySelectorAll( '[data-theme-set]' );
		for ( var i = 0; i < segments.length; i++ ) {
			var seg    = segments[ i ];
			var target = seg.getAttribute( 'data-theme-set' );
			if ( target === value ) {
				seg.classList.add( 'is-active' );
				seg.setAttribute( 'aria-pressed', 'true' );
			} else {
				seg.classList.remove( 'is-active' );
				seg.setAttribute( 'aria-pressed', 'false' );
			}
		}
		// Mobile single-icon control: keep its accessible name in sync with the
		// current mode. The visible icon is swapped by CSS (keyed on data-theme).
		var cycles = document.querySelectorAll( '[data-theme-cycle]' );
		for ( var j = 0; j < cycles.length; j++ ) {
			cycles[ j ].setAttribute(
				'aria-label',
				'Appearance: ' + ( LABELS[ value ] || 'system' ) + ' theme. Activate to change.'
			);
		}
	}

	function onClick( ev ) {
		var cyc = ev.target.closest( '[data-theme-cycle]' );
		if ( cyc ) {
			ev.preventDefault();
			var idx = ORDER.indexOf( getCurrent() );
			applyTheme( ORDER[ ( idx + 1 ) % ORDER.length ] );
			return;
		}
		var btn = ev.target.closest( '[data-theme-set]' );
		if ( ! btn ) return;
		ev.preventDefault();
		applyTheme( btn.getAttribute( 'data-theme-set' ) );
	}

	function ready() {
		syncSegments( getCurrent() );
		document.addEventListener( 'click', onClick );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
})();
