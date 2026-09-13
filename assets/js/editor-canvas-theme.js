/**
 * Editor canvas appearance (0.7.46, G-07): the iframed canvas is its own
 * document, so mirror <html data-theme> from the admin document into it (and
 * keep it mirrored) so the page previews in the scheme readers with the same
 * preference get. Admin chrome stays admin.css's business.
 */
( function () {
	function sync() {
		var v = document.documentElement.getAttribute( 'data-theme' );
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( f ) {
			try {
				var doc = f.contentDocument;
				if ( doc && doc.documentElement && doc.documentElement.getAttribute( 'data-theme' ) !== v ) {
					if ( v ) { doc.documentElement.setAttribute( 'data-theme', v ); } else { doc.documentElement.removeAttribute( 'data-theme' ); }
				}
			} catch ( e ) {}
		} );
	}
	new MutationObserver( sync ).observe( document.documentElement, { attributes: true, attributeFilter: [ 'data-theme' ] } );
	new MutationObserver( sync ).observe( document.body, { childList: true, subtree: true } );
	document.addEventListener( 'load', sync, true );
	sync();
} )();
