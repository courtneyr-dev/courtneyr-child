/**
 * Outpost composer appearance bridge (0.7.46, issue 17; gap review G-07).
 *
 * The composer shell prints nothing from the theme, so this script rides the
 * plugin's shell script filter. Precedence (browser-local policy):
 *   1. A stored site preference (localStorage "courtneyr-theme": light | dark |
 *      system) wins: it is mirrored onto <html data-theme> and mapped onto the
 *      composer's mode class (outpost-mode-day / night / system).
 *   2. No stored preference, or storage unavailable: the server-printed class
 *      (Outpost's per-user mode, default system) stays untouched and no
 *      data-theme is written.
 * Other tabs changing the preference update this one through the storage
 * event. No new toggle: the site toggle is the single source.
 */
( function () {
	var KEY = 'courtneyr-theme';
	var MAP = { light: 'outpost-mode-day', dark: 'outpost-mode-night', system: 'outpost-mode-system' };
	function read() {
		try { var v = localStorage.getItem( KEY ); return MAP[ v ] ? v : null; } catch ( e ) { return null; }
	}
	function apply( pref ) {
		if ( ! pref ) { return; }
		document.documentElement.setAttribute( 'data-theme', pref );
		var body = document.body;
		if ( ! body ) { return; }
		Object.keys( MAP ).forEach( function ( k ) { body.classList.remove( MAP[ k ] ); } );
		body.classList.add( MAP[ pref ] );
	}
	apply( read() );
	window.addEventListener( 'storage', function ( e ) { if ( e.key === KEY ) { apply( read() ); } } );
} )();
