/**
 * Site Editor: the overlay part's Navigation (class `cr-nav-overlay__nav`)
 * shows the menu the header part's overlay-opening Navigation names. The id
 * goes to core's edit component as `ref` in props only, so the block's saved
 * attributes never get it; inc/navigation-overlay.php drops any `ref` the
 * block picks up on save. The header is read from the editor store, so a
 * menu picked in the header shows in the overlay without a reload; until
 * that record loads, the id PHP printed is used. A header with no menu
 * leaves the block to core.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.compose || ! wp.data || ! wp.element || ! wp.blocks ) {
		return;
	}

	var el = wp.element.createElement;
	var config = window.courtneyrNavigationOverlay || {};
	var last = { record: null, menu: config.headerMenuId || null };

	function menuOf( attributes ) {
		var ref = attributes.ref || attributes.navigationMenuId;
		return ref ? Number( ref ) : null;
	}

	function openerMenu( blocks ) {
		for ( var i = 0; i < blocks.length; i++ ) {
			if ( 'core/navigation' === blocks[ i ].name && blocks[ i ].attributes.overlay ) {
				return menuOf( blocks[ i ].attributes );
			}
			var inner = openerMenu( blocks[ i ].innerBlocks || [] );
			if ( null !== inner ) {
				return inner;
			}
		}
		return null;
	}

	function headerMenu( select ) {
		var record = select( 'core' ).getEditedEntityRecord( 'postType', 'wp_template_part', config.headerPart );
		if ( ! record || record === last.record ) {
			return last.menu;
		}
		var blocks = record.blocks || ( 'string' === typeof record.content ? wp.blocks.parse( record.content ) : null );
		if ( blocks ) {
			last = { record: record, menu: openerMenu( blocks ) };
		}
		return last.menu;
	}

	function isMarked( props ) {
		return 'core/navigation' === props.name && ( props.attributes.className || '' ).split( ' ' ).indexOf( config.marker ) !== -1;
	}

	var withHeaderMenu = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		function Marked( props ) {
			var menu = wp.data.useSelect( headerMenu, [] );
			if ( ! menu ) {
				return el( BlockEdit, props );
			}
			var attributes = Object.assign( {}, props.attributes, { ref: menu } );
			delete attributes.navigationMenuId;
			return el( BlockEdit, Object.assign( {}, props, { attributes: attributes } ) );
		}
		return function ( props ) {
			return isMarked( props ) ? el( Marked, props ) : el( BlockEdit, props );
		};
	}, 'withCourtneyrOverlayHeaderMenu' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'courtneyr/navigation-overlay-header-menu', withHeaderMenu );
} )( window.wp );
