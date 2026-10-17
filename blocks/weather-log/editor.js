/**
 * courtneyr/weather-log — editor-side ServerSideRender of the same PHP that
 * paints the station log on the front end.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'courtneyr/weather-log', {
		edit: function ( props ) {
			var blockProps = useBlockProps( { className: 'cr-weather-station-editor' } );
			var postId = props.context && props.context.postId;
			if ( ! postId ) {
				return el( 'div', blockProps, __( 'Weather log: select a post to preview.', 'courtneyr-child' ) );
			}
			return el(
				'div',
				blockProps,
				el( ServerSideRender, {
					block: 'courtneyr/weather-log',
					urlQueryArgs: { post_id: postId },
					EmptyResponsePlaceholder: function () { return el( 'span', { className: 'cr-weather-station-editor__empty' }, __( 'No weather log.', 'courtneyr-child' ) ); },
				} )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp );
