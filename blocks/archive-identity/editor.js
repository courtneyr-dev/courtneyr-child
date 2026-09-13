/**
 * courtneyr/archive-identity — editor side. ServerSideRender with the edited
 * template's slug so the site editor shows the family the template serves.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;
	wp.blocks.registerBlockType( 'courtneyr/archive-identity', {
		edit: function () {
			var blockProps = useBlockProps( { className: 'cr-archive-identity-editor' } );
			var slug = '';
			try { var site = wp.data.select( 'core/edit-site' ); if ( site && site.getEditedPostId ) { slug = String( site.getEditedPostId() || '' ).split( '//' ).pop(); } } catch ( e ) {}
			return el( 'div', blockProps, el( ServerSideRender, { block: 'courtneyr/archive-identity', attributes: {}, urlQueryArgs: { cr_template: slug } } ) );
		},
		save: function () { return null; },
	} );
} )( window.wp );
