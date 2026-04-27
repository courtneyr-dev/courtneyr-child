/* Theme toggle — cycles system → dark → light → system on click.
 * Persists choice to localStorage. Updates aria-label dynamically.
 *
 * The CSS in tokens.css drives the actual color-flips via [data-theme]
 * attribute on <html>. This file just manages state + accessibility.
 */
(function () {
	var STORAGE_KEY = 'courtneyr-theme';
	var ORDER       = [ 'system', 'dark', 'light' ];
	var LABELS      = {
		system: 'System (follows OS)',
		dark:   'Dark',
		light:  'Light'
	};
	var root = document.documentElement;

	function getCurrent() {
		var v = root.getAttribute( 'data-theme' );
		return ( v === 'light' || v === 'dark' || v === 'system' ) ? v : 'system';
	}

	function applyTheme( value ) {
		root.setAttribute( 'data-theme', value );
		try { localStorage.setItem( STORAGE_KEY, value ); } catch ( e ) {}
		updateButtons( value );
	}

	function updateButtons( value ) {
		var buttons = document.querySelectorAll( '[data-theme-toggle]' );
		for ( var i = 0; i < buttons.length; i++ ) {
			var current = LABELS[ value ] || value;
			var nextValue = ORDER[ ( ORDER.indexOf( value ) + 1 ) % ORDER.length ];
			var nextLabel = LABELS[ nextValue ];
			buttons[ i ].setAttribute( 'aria-label',
				'Theme: ' + current + '. Click to switch to ' + nextLabel + '.'
			);
			buttons[ i ].setAttribute( 'title', 'Theme: ' + current );
		}
	}

	function onClick( ev ) {
		var btn = ev.target.closest( '[data-theme-toggle]' );
		if ( ! btn ) return;
		ev.preventDefault();
		var current = getCurrent();
		var next    = ORDER[ ( ORDER.indexOf( current ) + 1 ) % ORDER.length ];
		applyTheme( next );
	}

	// Wire on DOM ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			updateButtons( getCurrent() );
			document.addEventListener( 'click', onClick );
		} );
	} else {
		updateButtons( getCurrent() );
		document.addEventListener( 'click', onClick );
	}
})();
