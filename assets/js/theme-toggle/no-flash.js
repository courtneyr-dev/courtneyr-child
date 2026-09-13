/* No-flash theme initializer.
 * MUST run synchronously in <head> before any stylesheet loads.
 * Reads stored theme preference and applies data-theme to <html>. */
(function () {
	try {
		var stored = localStorage.getItem('courtneyr-theme');
		if (stored === 'light' || stored === 'dark' || stored === 'system') {
			document.documentElement.setAttribute('data-theme', stored);
		} else {
			document.documentElement.setAttribute('data-theme', 'system');
		}
	} catch (e) {
		document.documentElement.setAttribute('data-theme', 'system');
	}
})();
// G-07: another tab changing the stored preference updates this document too
// (the toggle script, when present, keeps its own controls in sync from here).
try {
	window.addEventListener( 'storage', function ( e ) {
		if ( e.key !== 'courtneyr-theme' ) { return; }
		var v = e.newValue;
		document.documentElement.setAttribute( 'data-theme', ( v === 'light' || v === 'dark' || v === 'system' ) ? v : 'system' );
	} );
} catch ( e ) {}
