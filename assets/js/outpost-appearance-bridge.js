/**
 * Outpost composer appearance bridge (0.7.46, issue 17).
 *
 * The composer shell prints nothing from the theme, so this script rides the
 * plugin's shell script filter. It reads the same stored preference the site
 * and the admin use (localStorage "courtneyr-theme": light | dark | system),
 * mirrors it onto <html data-theme>, and maps it onto the composer's own
 * mode class (outpost-mode-day / night / system) which its tokens key on.
 * Other tabs changing the preference update this one through the storage
 * event. No new toggle: the site toggle is the single source.
 */
( function () {
	var KEY = 'courtneyr-theme';
	var MAP = { light: 'outpost-mode-day', dark: 'outpost-mode-night', system: 'outpost-mode-system' };
	function read() {
		try { var v = localStorage.getItem( KEY ); return MAP[ v ] ? v : 'system'; } catch ( e ) { return 'system'; }
	}
	function apply( pref ) {
		document.documentElement.setAttribute( 'data-theme', pref );
		var body = document.body;
		if ( ! body ) { return; }
		Object.keys( MAP ).forEach( function ( k ) { body.classList.remove( MAP[ k ] ); } );
		body.classList.add( MAP[ pref ] );
	}
	apply( read() );
	window.addEventListener( 'storage', function ( e ) { if ( e.key === KEY ) { apply( read() ); } } );
} )();
