/**
 * courtneyr/contact-form — editor-side ServerSideRender of the same plugin
 * shortcode markup used on the front end.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'courtneyr/contact-form', {
		edit: function () {
			var blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el( ServerSideRender, { block: 'courtneyr/contact-form' } )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp );
