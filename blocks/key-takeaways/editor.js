( function ( wp ) {
	const { registerBlockType, createBlock } = wp.blocks;
	const { useBlockProps, useInnerBlocksProps } = wp.blockEditor;
	const { createElement } = wp.element;
	const { __ } = wp.i18n;
	const name = 'courtneyr/key-takeaways';
	const defaultHeading = __( 'Key takeaways', 'courtneyr-child' );

	registerBlockType( name, {
		edit( { attributes } ) {
			const blockProps = useBlockProps();
			const { children, ...props } = useInnerBlocksProps( blockProps, {
				allowedBlocks: [ 'core/list' ],
				template: [ [ 'core/list', {}, [ [ 'core/list-item' ] ] ] ],
				renderAppender: false,
			} );

			return createElement(
				'section',
				props,
				createElement( 'h2', null, attributes.heading || defaultHeading ),
				children
			);
		},

		save( { attributes } ) {
			const blockProps = useInnerBlocksProps.save( useBlockProps.save() );
			const { children, ...props } = blockProps;

			return createElement(
				'section',
				props,
				createElement( 'h2', null, attributes.heading || defaultHeading ),
				children
			);
		},

		transforms: {
			from: [
				{
					type: 'block',
					blocks: [ 'yoast-seo/ai-summarize' ],
					isMatch( attributes, block ) {
						return attributes.status === 'loaded' && block.isValid &&
							block.innerBlocks.length === 1 && block.innerBlocks[ 0 ].name === 'core/list';
					},
					transform( { anchor, className }, [ list ] ) {
						const listClasses = ( list.attributes.className || '' )
							.split( /\s+/ )
							.filter( ( value ) => value && value !== 'yoast-ai-summarize-list' );
						const takeaways = createBlock(
							'core/list',
							{ ...list.attributes, className: listClasses.join( ' ' ) || undefined },
							list.innerBlocks
						);

						return createBlock( name, { anchor, className }, [ takeaways ] );
					},
				},
			],
		},
	} );
} )( window.wp );
