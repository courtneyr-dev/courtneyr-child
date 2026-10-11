/**
 * Editor parity for kind-specific single post surfaces.
 */
( function ( root ) {
	var api = {};

	api.kindSlugFromIds = function ( ids, terms ) {
		var i;
		var j;

		if ( ! Array.isArray( ids ) || ! Array.isArray( terms ) ) {
			return '';
		}

		for ( i = 0; i < ids.length; i++ ) {
			for ( j = 0; j < terms.length; j++ ) {
				if ( terms[ j ] && terms[ j ].id === ids[ i ] ) {
					return terms[ j ].slug || '';
				}
			}
		}

		return '';
	};

	api.classesFor = function ( kindSlug, kinds ) {
		if ( ! kinds || ! kinds[ kindSlug ] || ! Array.isArray( kinds[ kindSlug ].classes ) ) {
			return [];
		}
		return kinds[ kindSlug ].classes.slice();
	};

	api.syncClasses = function ( body, wanted ) {
		var added = [];
		var next = [];
		var wantedMap = {};

		if ( ! body ) {
			return;
		}

		if ( ! Array.isArray( wanted ) ) {
			wanted = [];
		}

		( body.dataset.crEditorParity || '' ).split( ' ' ).forEach( function ( className ) {
			if ( className ) {
				added.push( className );
			}
		} );

		wanted.forEach( function ( className ) {
			wantedMap[ className ] = true;
		} );

		added.forEach( function ( className ) {
			if ( ! wantedMap[ className ] ) {
				body.classList.remove( className );
			}
		} );

		wanted.forEach( function ( className ) {
			if ( added.indexOf( className ) !== -1 ) {
				next.push( className );
			} else if ( ! body.classList.contains( className ) ) {
				body.classList.add( className );
				next.push( className );
			}
		} );

		body.dataset.crEditorParity = next.join( ' ' );
	};

	api.partOverride = function ( attributes, kindSlug, kinds, routerSlug ) {
		var next;
		var part = kinds && kinds[ kindSlug ] ? kinds[ kindSlug ].part : '';

		if ( ! attributes || attributes.slug !== routerSlug || ! part ) {
			return attributes;
		}

		next = Object.assign( {}, attributes );
		next.slug = part;
		return next;
	};

	api.guardSetAttributes = function ( setAttributes ) {
		return function ( attrs ) {
			var next = {};
			var key;

			for ( key in attrs ) {
				if ( Object.prototype.hasOwnProperty.call( attrs, key ) && 'slug' !== key && 'theme' !== key ) {
					next[ key ] = attrs[ key ];
				}
			}

			if ( Object.keys( next ).length ) {
				setAttributes( next );
			}
		};
	};

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = api;
		return;
	}

	if ( ! root.wp || ! root.courtneyrEditorParity ) {
		return;
	}

	( function () {
		var wp = root.wp;
		var data = root.courtneyrEditorParity;
		var lastBody = null;
		var lastClasses = '';

		function currentKindSlug( select ) {
			var core;
			var editor;
			var editSite;
			var ids = [];
			var context;
			var post;
			var terms;

			try {
				core = select( 'core' );
				editor = select( 'core/editor' );
				if ( editor && editor.getCurrentPostType && editor.getCurrentPostType() === 'post' ) {
					ids = editor.getEditedPostAttribute( 'kind' ) || [];
				} else {
					editSite = select( 'core/edit-site' );
					if ( editSite && editSite.getEditedPostContext ) {
						context = editSite.getEditedPostContext();
						if ( context && context.postType === 'post' && core && core.getEntityRecord ) {
							post = core.getEntityRecord( 'postType', 'post', context.postId );
							ids = post && post.kind ? post.kind : [];
						}
					}
				}

				terms = core && core.getEntityRecords ? core.getEntityRecords( 'taxonomy', 'kind', { per_page: -1 } ) : [];
				return api.kindSlugFromIds( ids, terms || [] );
			} catch ( e ) {
				return '';
			}
		}

		function sync() {
			var iframe;
			var body;
			var slug;
			var wanted;
			var classString;

			try {
				iframe = root.document.querySelector( 'iframe[name="editor-canvas"]' );
				body = iframe && iframe.contentDocument ? iframe.contentDocument.body : null;
				slug = currentKindSlug( wp.data.select );
				wanted = api.classesFor( slug, data.kinds );
				classString = wanted.join( ' ' );

				if ( body && body === lastBody && classString === lastClasses ) {
					return;
				}

				api.syncClasses( body, wanted );
				lastBody = body;
				lastClasses = classString;
			} catch ( e ) {}
		}

		function KindPartEdit( props ) {
			var slug = wp.data.useSelect( function ( select ) {
				return currentKindSlug( select );
			}, [] );
			var next = api.partOverride( props.attributes, slug, data.kinds, data.routerSlug );

			if ( next !== props.attributes ) {
				return wp.element.createElement(
					props.BlockEdit,
					Object.assign(
						{},
						props,
						{
							attributes: next,
							setAttributes: api.guardSetAttributes( props.setAttributes )
						}
					)
				);
			}

			return wp.element.createElement( props.BlockEdit, props );
		}

		try {
			wp.data.subscribe( sync );
		} catch ( e ) {}

		try {
			new root.MutationObserver( sync ).observe( root.document.body, { childList: true, subtree: true } );
		} catch ( e ) {}

		try {
			root.document.addEventListener( 'load', sync, true );
		} catch ( e ) {}

		try {
			wp.hooks.addFilter(
				'editor.BlockEdit',
				'courtneyr/editor-parity',
				wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
					return function ( props ) {
						if ( props.name !== 'core/template-part' ) {
							return wp.element.createElement( BlockEdit, props );
						}

						return wp.element.createElement( KindPartEdit, Object.assign( {}, props, { BlockEdit: BlockEdit } ) );
					};
				}, 'withKindPart' )
			);
		} catch ( e ) {}

		sync();
	} )();
} )( typeof window !== 'undefined' ? window : globalThis );
