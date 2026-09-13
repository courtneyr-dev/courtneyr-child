/**
 * courtneyr/post-glyph — editor side.
 *
 * ServerSideRender asks the block-renderer endpoint for the glyph of the loop
 * post (post_id -> postId/postType context), so the canvas shows the real
 * format icon, not a placeholder. Plain script; WordPress globals only.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'courtneyr/post-glyph', {
		edit: function ( props ) {
			var blockProps = useBlockProps( { className: 'cr-post-glyph-editor' } );
			var postId = props.context && props.context.postId;
			if ( ! postId ) {
				return el( 'span', blockProps, __( 'Post glyph: place inside a Query Loop’s Post Template.', 'courtneyr-child' ) );
			}
			return el(
				'span',
				blockProps,
				el( ServerSideRender, {
					block: 'courtneyr/post-glyph',
					attributes: {},
					urlQueryArgs: { post_id: postId },
					LoadingResponsePlaceholder: function () { return el( 'span', { className: 'media-glyph cr-icon-avatar cr-icon-avatar--blog', 'aria-hidden': 'true' } ); },
				} )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp );
