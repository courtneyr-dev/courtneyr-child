/**
 * Mood pin, in the editor: a searchable "Pick a mood" control on the
 * mood-card block (inc/mood-pin.php feeds it the catalog) that sets the
 * block's own `mood` and `emoji` attributes, plus a live preview of the pin
 * rendered by the same PHP as the front end. The plugin's free-text Mood
 * field keeps working for moods outside the catalog.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.components || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ComboboxControl = wp.components.ComboboxControl;
	var data = window.courtneyrMoodPins || { moods: [] };
	var BLOCK = 'post-kinds-indieweb/mood-card';

	var options = data.moods.map( function ( mood ) {
		return { value: mood.label, label: mood.emoji + ' ' + mood.label + ( mood.family ? '  ·  ' + mood.family : '' ) };
	} );

	function normalize( mood ) {
		return String( mood || '' )
			.replace( /<[^>]+>/g, '' )
			.replace( /\s+/g, ' ' )
			.trim()
			.toLowerCase();
	}

	function findMood( label ) {
		var key = normalize( label );
		return data.moods.find( function ( mood ) {
			return mood.label === key;
		} );
	}

	function Preview( props ) {
		var state = useState( '' );
		var html = state[ 0 ];
		var setHtml = state[ 1 ];

		useEffect(
			function () {
				var live = true;
				wp.apiFetch( {
					path:
						'/courtneyr/v1/mood-pin?mood=' +
						encodeURIComponent( props.mood || '' ) +
						'&emoji=' +
						encodeURIComponent( props.emoji || '' ),
				} )
					.then( function ( response ) {
						if ( live && response && response.html ) {
							setHtml( response.html );
						}
					} )
					.catch( function () {} );
				return function () {
					live = false;
				};
			},
			[ props.mood, props.emoji ]
		);

		if ( ! html ) {
			return null;
		}
		// The endpoint returns markup the theme escaped server-side.
		return el( 'div', {
			className: 'cr-mood-pin-preview',
			dangerouslySetInnerHTML: { __html: html },
		} );
	}

	wp.hooks.addFilter( 'editor.BlockEdit', 'courtneyr/mood-pin', function ( BlockEdit ) {
		return function ( props ) {
			if ( props.name !== BLOCK ) {
				return el( BlockEdit, props );
			}
			var mood = props.attributes.mood;
			var emoji = props.attributes.emoji;
			var match = findMood( mood );

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Mood pin', 'courtneyr-child' ), initialOpen: true },
						el( ComboboxControl, {
							label: __( 'Pick a mood', 'courtneyr-child' ),
							help: __(
								'194 moods from the LiveJournal and Facebook lists. Type to search; the Mood field above still takes anything.',
								'courtneyr-child'
							),
							value: match ? match.label : null,
							options: options,
							allowReset: true,
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								var picked = findMood( value );
								if ( picked ) {
									props.setAttributes( { mood: picked.label, emoji: picked.emoji } );
								}
							},
						} ),
						el(
							'p',
							{ className: 'cr-mood-pin-help' },
							match
								? __( 'Printed from the catalog.', 'courtneyr-child' )
								: __(
										'Not in the catalog: the pin prints your mood word as typed, with the emoji you chose.',
										'courtneyr-child'
								  )
						),
						el( Preview, { mood: mood, emoji: emoji } )
					)
				)
			);
		};
	} );
} )( window.wp );
