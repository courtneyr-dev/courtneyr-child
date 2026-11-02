/**
 * courtneyr/series-guide — editor side. ServerSideRender prints a labelled
 * placeholder; the series data only exists on a real series request.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;
	wp.blocks.registerBlockType( 'courtneyr/series-guide', {
		edit: function ( props ) {
			return el( 'div', useBlockProps(), el( ServerSideRender, { block: 'courtneyr/series-guide', attributes: props.attributes } ) );
		},
		save: function () { return null; },
	} );
} )( window.wp );
