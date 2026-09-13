/**
 * courtneyr/term-chips — editor side: ServerSideRender of the same PHP that
 * paints the chips on the front end (Core post-terms + the theme's chip
 * resolver), so colours come from the real term data in both contexts.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'courtneyr/term-chips', {
		edit: function ( props ) {
			var blockProps = useBlockProps( { className: 'cr-term-chips-editor' } );
			var postId = props.context && props.context.postId;
			if ( ! postId ) {
				return el( 'div', blockProps, __( 'Term chips: place inside a Query Loop’s Post Template.', 'courtneyr-child' ) );
			}
			return el(
				'div',
				blockProps,
				el( ServerSideRender, {
					block: 'courtneyr/term-chips',
					attributes: { term: props.attributes.term, fontSize: props.attributes.fontSize, className: props.attributes.className },
					urlQueryArgs: { post_id: postId },
					EmptyResponsePlaceholder: function () { return el( 'span', { className: 'cr-term-chips-editor__empty' }, __( 'No terms.', 'courtneyr-child' ) ); },
				} )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp );
