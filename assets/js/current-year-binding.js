/**
 * Editor values for the `courtneyr/current-year` bindings source
 * (inc/block-bindings.php registers the server side). Without this the editor
 * shows the source label in a bound paragraph instead of the year.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.blocks.registerBlockBindingsSource ) {
		return;
	}
	wp.blocks.registerBlockBindingsSource( {
		name: 'courtneyr/current-year',
		getValues: function ( args ) {
			var year = String( new Date().getFullYear() );
			var values = {};
			Object.keys( args.bindings ).forEach( function ( attribute ) {
				var sourceArgs = args.bindings[ attribute ].args || {};
				values[ attribute ] = ( typeof sourceArgs.prefix === 'string' ? sourceArgs.prefix : '' ) + year;
			} );
			return values;
		},
	} );
} )( window.wp );
